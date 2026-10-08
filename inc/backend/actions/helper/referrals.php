<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Token-only referral link helper.
 */
class YOWCL_Helper_Referrals {

    // Query parameter for referral links: https://example.com/?ref=TOKEN
    const QUERY_VAR       = 'ref';
    // Cookie key that stores the persistent referral token
    const COOKIE_KEY      = 'yowcl_ref';
    // User meta to store the persistent referral token
    const USER_META_TOKEN = '_yo_referral_token';

    /**
     * Bootstrap front-end capture: if a page is hit with ?ref=TOKEN, set cookie.
     */
    public static function init() {
        if ( ! YOWCL_Free_Core::owns() ) {
			return;
		}

        add_action( 'init', [ __CLASS__, 'maybe_capture_referral' ] );



    }

    /**
     * Build a referral link to any target URL (defaults to home).
     */
    public static function build_referral_link( $user_id, $target_url = '', array $extra_args = [] ) {
        if ( ! YOWCL_Free_Core::owns() ) {
            return '';
        }

        $user_id = absint( $user_id );
        if ( $user_id <= 0 ) { return ''; }

        $target_url = $target_url ?: home_url( '/' );
        $token      = self::ensure_user_token( $user_id );
        if ( ! is_string( $token ) || ! preg_match( '/^[a-zA-Z0-9]{12}$/D', $token ) ) { return ''; }

        $args = array_merge( $extra_args, [ self::QUERY_VAR => $token ] );
        return add_query_arg( $args, $target_url );
    }

    /** Convenience helpers */
    public static function link_to_home( $user_id, array $extra_args = [] ) {
        return self::build_referral_link( $user_id, home_url( '/' ), $extra_args );
    }
    public static function link_to_shop( $user_id, array $extra_args = [] ) {
        $shop = function_exists('wc_get_page_permalink') ? wc_get_page_permalink('shop') : home_url('/');
        return self::build_referral_link( $user_id, $shop, $extra_args );
    }
    public static function link_to_url( $user_id, $url, array $extra_args = [] ) {
        return self::build_referral_link( $user_id, $url, $extra_args );
    }

    /**
     * Ensure a persistent token exists for this user and return it.
     */
    public static function ensure_user_token( $user_id ) {
        if ( ! YOWCL_Free_Core::owns() ) {
            return '';
        }

        $config = YOWCL_Free_Referral::settings();
        if ( ! $config['enabled'] || ! get_userdata( (int) $user_id ) ) { return ''; }
        return YOWCL_Free_Migrations::locked( static function () use ( $user_id ) {
            global $wpdb;
            $db = $wpdb->dbh; $name = 'yowclrt_' . hash( 'sha224', DB_NAME . ':' . $wpdb->usermeta );
            if ( '1' !== (string) YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT GET_LOCK(%s,0)', $name ) ) ) { throw new RuntimeException( 'referral_token_busy' ); }
            try {
            $tokens = self::tokens( (int) $user_id );
            if ( $tokens ) {
                if ( 1 !== count( $tokens ) || ! is_string( $tokens[0] ) || self::resolve_referrer_user_id( $tokens[0] ) !== (int) $user_id ) { throw new DomainException( 'referral_token_ambiguous' ); }
                return $tokens[0];
            }
            for ( $i=0; $i<10; $i++ ) {
                $token = self::generate_token();
                $found = $wpdb->get_var( $wpdb->prepare( "SELECT umeta_id FROM {$wpdb->usermeta} WHERE meta_key=%s AND meta_value=%s LIMIT 1", self::USER_META_TOKEN, $token ) );
                if ( $wpdb->last_error ) { throw new RuntimeException( 'referral_token_storage_failed' ); }
                if ( $found ) { continue; }
                if ( $wpdb->dbh !== $db || (string) YOWCL_Points_Lock::scalar( $db, 'SELECT CONNECTION_ID()' ) !== (string) YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT IS_USED_LOCK(%s)', $name ) ) ) { throw new RuntimeException( 'referral_token_ownership_lost' ); }
                if ( ! add_user_meta( (int) $user_id, self::USER_META_TOKEN, $token, true ) || self::resolve_referrer_user_id( $token ) !== (int) $user_id ) { throw new RuntimeException( 'referral_token_not_saved' ); }
                return $token;
            }
            throw new RuntimeException( 'referral_token_unavailable' );
            } finally { try { YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); } catch ( Throwable $ignored ) {} }
        } );
    }

    /**
     * Resolve referrer user ID from a token.
     */
    public static function resolve_referrer_user_id( $token ) {
        $token = is_string( $token ) ? trim( $token ) : '';
        if ( ! preg_match( '/^[a-zA-Z0-9]{12}$/D', $token ) ) { return 0; }

        global $wpdb;
        $rows = $wpdb->get_results( $wpdb->prepare( "SELECT m.user_id,m.meta_value FROM {$wpdb->usermeta} m INNER JOIN {$wpdb->users} u ON u.ID=m.user_id WHERE m.meta_key=%s AND m.meta_value=%s LIMIT 2", self::USER_META_TOKEN, $token ), ARRAY_A );
        if ( $wpdb->last_error ) { throw new RuntimeException( 'referral_token_storage_failed' ); }
        if ( 1 !== count( $rows ) || ! hash_equals( (string) $rows[0]['meta_value'], $token ) ) { return 0; }
        $all = self::tokens( (int) $rows[0]['user_id'] );
        return 1 === count( $all ) && $all[0] === $token ? (int) $rows[0]['user_id'] : 0;
    }

    private static function tokens( $user ) {
        global $wpdb;
        $tokens = $wpdb->get_col( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id=%d AND meta_key=%s", $user, self::USER_META_TOKEN ) );
        if ( $wpdb->last_error ) { throw new RuntimeException( 'referral_token_storage_failed' ); }
        return $tokens;
    }

    /**
     * If ?ref=TOKEN is present, set a cookie holding the validated token.
     * Free cookie lifetime is fixed at 30 days.
     */
    public static function maybe_capture_referral() {
        if ( ! YOWCL_Free_Core::owns() ) {
            return;
        }

        if ( is_admin() ) { return; }
        if ( ! YOWCL_Free_Referral::settings()['enabled'] || empty( $_GET[ self::QUERY_VAR ] ) || ! is_string( $_GET[ self::QUERY_VAR ] ) ) { return; }

	        $token    = wp_unslash( $_GET[ self::QUERY_VAR ] );
	        $user_id  = self::resolve_referrer_user_id( $token );
	        if ( $user_id > 0 ) {
            self::set_referral_cookie( $token, 30 );
	        }
	    }

    /**
     * Get referrer user ID from cookie (0 if none).
     */
    public static function get_referrer_from_cookie() : int {
        return isset( $_COOKIE[ self::COOKIE_KEY ] ) && is_string( $_COOKIE[ self::COOKIE_KEY ] ) ? self::resolve_referrer_user_id( wp_unslash( $_COOKIE[ self::COOKIE_KEY ] ) ) : 0;
    }

    /**
     * Set the referral cookie for N days (default 30).
     */
    public static function set_referral_cookie( $token, $days = 30 ) {
        if ( ! YOWCL_Free_Core::owns() ) {
            return;
        }

        if ( ! is_string( $token ) || self::resolve_referrer_user_id( $token ) <= 0 ) { return; }

        $expire  = time() + 30 * DAY_IN_SECONDS;
        $secure  = is_ssl();
        $httponly= true;
        $path    = defined( 'COOKIEPATH' ) ? COOKIEPATH : '/';
        $domain  = defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '';

        setcookie( self::COOKIE_KEY, $token, array( 'expires' => $expire, 'path' => $path, 'domain' => $domain, 'secure' => $secure, 'httponly' => $httponly, 'samesite' => 'Lax' ) );
        $_COOKIE[ self::COOKIE_KEY ] = $token; // immediate availability
    }

    /**
     * Attach referrer (from cookie) to order meta so awarding can happen later.
     * Call this during checkout/order creation.
     */
    public static function attach_referrer_to_order( $order_id ) {
        YOWCL_Referral_Rewards::attach( wc_get_order( $order_id ) );
    }

    /** Generate a short, URL-friendly token (no lookalike chars). */
    protected static function generate_token( $length = 12 ) {
        $alphabet = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $max      = strlen( $alphabet ) - 1;
        $bytes    = random_bytes( $length );
        $out      = '';
        for ( $i = 0; $i < $length; $i++ ) {
            $out .= $alphabet[ ord( $bytes[$i] ) % ( $max + 1 ) ];
        }
        return $out;
    }
}

// Register capture before priority 10 is visited on this same native init pass.
add_action( 'init', [ 'YOWCL_Helper_Referrals', 'init' ], 1 );

<?php
/** Free edition admission and read compatibility over the single canonical writer. */
defined( 'ABSPATH' ) || exit;
class YOWCL_Free_Core {
    const CUTOVER = 'loyf_core_cutover_v1';
    public static function owns() {
        // The activation sandbox must not load canonical definitions from both editions.
        if ( is_admin() && in_array( $_REQUEST['action'] ?? '', array( 'activate', 'activate-selected' ), true ) && ( ( $_REQUEST['plugin'] ?? '' ) === 'wc-loyalty/wc-loyalty.php' || in_array( 'wc-loyalty/wc-loyalty.php', (array) ( $_REQUEST['checked'] ?? array() ), true ) ) ) { return false; }
        if ( defined( 'WP_CLI' ) && WP_CLI && in_array( 'activate', (array) ( $_SERVER['argv'] ?? array() ), true ) && in_array( 'wc-loyalty', (array) ( $_SERVER['argv'] ?? array() ), true ) ) { return false; }
        $active = (array) get_option( 'active_plugins', array() );
        $network = (array) get_site_option( 'active_sitewide_plugins', array() );
        return ! in_array( 'wc-loyalty/wc-loyalty.php', $active, true ) && ! isset( $network['wc-loyalty/wc-loyalty.php'] ) && ! class_exists( 'YOWCL_Loyalty', false );
    }
    public static function cutover() {
        global $wpdb;
        $value = get_option( self::CUTOVER, null );
        if ( null === $value ) {
            $users = $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->users}" );
            if ( $wpdb->last_error ) { throw new RuntimeException( 'cutover_storage_unavailable' ); }
            $comments = $wpdb->get_var( "SELECT MAX(comment_ID) FROM {$wpdb->comments}" );
            if ( $wpdb->last_error ) { throw new RuntimeException( 'cutover_storage_unavailable' ); }
            add_option( self::CUTOVER, array( 'version' => 1, 'users' => (int) $users, 'comments' => (int) $comments ), '', false );
            $value = get_option( self::CUTOVER, null );
        }
        if ( ! is_array( $value ) || array_keys( $value ) !== array( 'version', 'users', 'comments' ) || 1 !== $value['version'] || ! is_int( $value['users'] ) || ! is_int( $value['comments'] ) || $value['users'] < 0 || $value['comments'] < 0 ) { throw new RuntimeException( 'cutover_review_required' ); }
        return $value;
    }
    public static function admitted( $user, $key, $action ) {
        if ( ! self::owns() ) { return false; }
        $cutover = self::cutover();
        if ( 'daily_login_reward' === $action ) { return true; }
        if ( 'review_reward' === $action ) {
            $comment = get_comment( (int) substr( $key, strlen( 'reward:review:' ) ) );
            return $comment && (int) $comment->user_id === (int) $user && (int) $comment->comment_ID > $cutover['comments'];
        }
        return (int) $user > $cutover['users'];
    }
    public static function hold( $user, $code ) {
        if ( $code ) { update_user_meta( (int) $user, '_loyf_economic_hold', sanitize_key( $code ) ); }
        do_action( 'loyf_economic_recovery_required', (int) $user, $code );
    }
    public static function user_reward( $user, $key, $action, $points, $description = null ) {
        if ( ! self::owns() ) { return; }
        $user = (int) $user;
        try {
            $project = YOWCL_Core_Rewards::user_projection( $user, $key, $action );
            if ( YOWCL_Core_Rewards::recover( $user, $key, $action, $project ) ) { return; }
            if ( ! self::admitted( $user, $key, $action ) ) { return; }
            if ( 'daily_login_reward' === $action ) {
                $day = substr( $key, -10 );
                if ( get_user_meta( $user, 'loyalty_last_daily_login', true ) === $day || get_user_meta( $user, 'yol_last_daily_login', true ) === $day ) { return; }
            }
            if ( 'sign_up_reward' === $action && get_user_meta( $user, '_yol_signup_awarded', true ) ) { return; }
            if ( 'review_reward' === $action && get_comment_meta( (int) substr( $key, 14 ), '_yowcl_review_reward_awarded', true ) ) { return; }
            if ( 'level_up_reward' === $action && in_array( substr( $key, strlen( 'reward:level_up:' . $user . ':' ) ), (array) get_user_meta( $user, '_yo_loyalty_levelup_awarded_roles', true ), true ) ) { return; }
            if ( $points <= 0 ) { return; }
            $labels = array( 'sign_up_reward' => __( 'Sign-up bonus', 'loyalty-for-woocommerce' ), 'daily_login_reward' => __( 'Daily login bonus', 'loyalty-for-woocommerce' ), 'review_reward' => __( 'Product review bonus', 'loyalty-for-woocommerce' ), 'level_up_reward' => __( 'Level up bonus', 'loyalty-for-woocommerce' ) );
            YOWCL_Core_Rewards::award( $user, (int) $points, $key, $action, $description ?? $labels[$action], $project, array( 'YOWCL_Core_Rewards', 'notify_user' ) );
        } catch ( Throwable $e ) { self::hold( $user, $e->getMessage() ); }
    }
    public static function level_bonus( $user, $role ) {
        $rules = maybe_unserialize( get_option( 'loyalty_extra_levelup_points_rules', array() ) );
        $merged = get_option( 'loyalty_extra_reviews_gamification_rules', array() );
        if ( isset( $merged['levelup_enabled'] ) ) { $rules = 'yes' === $merged['levelup_enabled'] ? ( $merged['levelup_points'] ?? array() ) : array(); }
        $role = sanitize_key( $role );
        if ( '' !== $role ) { self::user_reward( $user, 'reward:level_up:' . (int) $user . ':' . $role, 'level_up_reward', (int) ( $rules[$role]['awarded'] ?? 0 ), __( 'Level up bonus for role:', 'loyalty-for-woocommerce' ) . ' ' . ( wp_roles()->roles[$role]['name'] ?? $role ) ); }
    }
    public static function history_amount( $row ) {
        $row = (array) $row;
        $value = $row['available_delta'] ?? null;
        if ( null === $value ) { return null; }
        if ( ! YOWCL_Ledger_V2::integer_valid( $value, 99999999 ) ) { return null; }
        return ( (int) $value >= 0 ? '+' : '-' ) . abs( (int) $value );
    }
    public static function extra( $kind ) {
        $rules = maybe_unserialize( get_option( 'loyalty_extra_points_rules', array() ) );
        if ( 'review' === $kind ) {
            $merged = get_option( 'loyalty_extra_reviews_gamification_rules', array() );
            return isset( $merged['review_enabled'] ) ? ( 'yes' === $merged['review_enabled'] ? (int) ( $merged['review_points'] ?? 0 ) : 0 ) : (int) ( $rules['review_points'] ?? 0 );
        }
        $flag = 'signup' === $kind ? 'signup_enabled' : 'login_enabled';
        if ( isset( $rules[$flag] ) && 'yes' !== $rules[$flag] ) { return 0; }
        return (int) ( $rules[$kind . '_points'] ?? 0 );
    }
}

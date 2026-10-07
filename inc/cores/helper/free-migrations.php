<?php
/** One-time Free semantic conversions. No balance, history or role ownership writes. */
defined( 'ABSPATH' ) || exit;
class YOWCL_Free_Migrations {
    private static $errors = array();
    public static function witness( $feature ) { return 'loyf_migration_' . $feature . '_v1'; }
    public static function read( $name ) {
        global $wpdb;
        $raw = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $name ) );
        if ( $wpdb->last_error ) { throw new RuntimeException( 'migration_storage_unavailable' ); }
        return $raw;
    }
    public static function ready( $feature ) {
        try { return '1' === self::read( self::witness( $feature ) ); } catch ( Throwable $e ) { return false; }
    }
    private static function decode( $raw ) {
        if ( null === $raw ) { return array(); }
        $value = maybe_unserialize( maybe_unserialize( $raw ) );
        if ( ! is_array( $value ) ) { throw new RuntimeException( 'migration_malformed_option' ); }
        return $value;
    }
    /** Preserve an existing legacy serialized wrapper while merging only owned fields. */
    private static function encode( $value, $raw ) {
        $old = null === $raw ? null : maybe_unserialize( $raw );
        return maybe_serialize( is_string( $old ) && is_serialized( $old ) ? serialize( $value ) : $value );
    }
    private static function put( $name, $raw, $before ) {
        global $wpdb;
        if ( $raw !== $before ) {
            if ( null === $before ) {
                $result = $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $name, $raw ) );
            } else {
                $result = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND BINARY option_value = BINARY %s", $raw, $name, $before ) );
            }
            if ( 1 !== $result ) { throw new RuntimeException( 'migration_write_failed' ); }
            wp_cache_delete( $name, 'options' );
            wp_cache_delete( 'alloptions', 'options' );
            wp_cache_delete( 'notoptions', 'options' );
        }
        if ( self::read( $name ) !== $raw ) { throw new RuntimeException( 'migration_readback_failed' ); }
    }
    public static function locked( $callback ) {
        global $wpdb;
        $lock = 'loyf-options:' . md5( $wpdb->options );
        if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 5)', $lock ) ) ) { throw new RuntimeException( 'migration_lock_unavailable' ); }
        try { return $callback(); } finally { $wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) ); }
    }
    private static function points( $value ) {
        if ( '' === $value ) { return $value; }
        if ( ! is_scalar( $value ) || ! is_numeric( $value ) || ! is_finite( (float) $value ) || (float) $value < 0 || (float) $value > 2147483647 ) { throw new RuntimeException( 'migration_malformed_points' ); }
        return $value;
    }
    private static function specification( $feature ) {
        $account = 'loyalty_extra_points_rules';
        $merged = 'loyalty_extra_reviews_gamification_rules';
        if ( in_array( $feature, array( 'signup', 'login', 'review' ), true ) ) {
            $raw = self::read( $account );
            $source = self::decode( $raw );
            $points = self::points( $source[$feature . '_points'] ?? 0 );
            return array( 'source' => $raw, 'target' => 'review' === $feature ? $merged : $account, 'patch' => array( $feature . '_points' => $points, $feature . '_enabled' => (float) $points > 0 ? 'yes' : 'no' ) );
        }
        if ( 'levelup' === $feature ) {
            $raw = self::read( 'loyalty_extra_levelup_points_rules' );
            $map = self::decode( $raw );
            $enabled = false;
            foreach ( $map as $role => $rule ) {
                if ( ! is_string( $role ) || sanitize_key( $role ) !== $role || ! is_array( $rule ) ) { throw new RuntimeException( 'migration_malformed_role_map' ); }
                $points = self::points( $rule['awarded'] ?? 0 );
                $enabled = $enabled || (float) $points > 0;
            }
            return array( 'source' => $raw, 'target' => $merged, 'patch' => array( 'levelup_points' => $map, 'levelup_enabled' => $enabled ? 'yes' : 'no' ) );
        }
        if ( 'redemption' === $feature ) {
            $raw = self::read( 'loyalty_points_using_rules' );
            $rules = self::decode( $raw );
            $patch = array();
            foreach ( array( 'min_points', 'max_points', 'min_cart' ) as $key ) { if ( ! array_key_exists( $key, $rules ) ) { $patch[$key] = ''; } }
            return array( 'source' => $raw, 'target' => 'loyalty_points_using_rules', 'patch' => $patch );
        }
        $ids = array( 'email_reward' => 'points_reward', 'email_deduct' => 'points_deduct', 'email_level' => 'level_update' );
        if ( ! isset( $ids[$feature] ) ) { throw new RuntimeException( 'migration_unknown_feature' ); }
        $raw = self::read( 'loyalty_notification_email' );
        $source = self::decode( $raw );
        $key = 'email_level' === $feature ? 'level_update' : 'points_update';
        if ( isset( $source[$key] ) && ! is_scalar( $source[$key] ) ) { throw new RuntimeException( 'migration_malformed_email' ); }
        // Exactly the old Free sender's !empty semantics, including disabled preferences.
        return array( 'source' => $raw, 'target' => 'woocommerce_yowcl_loyalty_' . $ids[$feature] . '_settings', 'patch' => array( 'enabled' => ! empty( $source[$key] ) ? 'yes' : 'no' ) );
    }
    private static function migrate( $feature ) {
        $name = self::witness( $feature );
        $witness = self::read( $name );
        if ( '1' === $witness ) { return; }
        if ( null !== $witness ) { throw new RuntimeException( 'migration_malformed_witness' ); }
        $evidence_name = $name . '_before';
        $evidence_raw = self::read( $evidence_name );
        if ( null === $evidence_raw ) {
            $spec = self::specification( $feature );
            $spec['before'] = self::read( $spec['target'] );
            self::decode( $spec['before'] );
            self::put( $evidence_name, serialize( $spec ), null );
        } else {
            $spec = self::decode( $evidence_raw );
            if ( array_keys( $spec ) !== array( 'source', 'target', 'patch', 'before' ) || ! is_array( $spec['patch'] ) || ! is_string( $spec['target'] ) ) { throw new RuntimeException( 'migration_malformed_evidence' ); }
        }
        $targets = array('signup' => 'loyalty_extra_points_rules', 'login' => 'loyalty_extra_points_rules', 'review' => 'loyalty_extra_reviews_gamification_rules', 'levelup' => 'loyalty_extra_reviews_gamification_rules', 'redemption' => 'loyalty_points_using_rules', 'email_reward' => 'woocommerce_yowcl_loyalty_points_reward_settings', 'email_deduct' => 'woocommerce_yowcl_loyalty_points_deduct_settings', 'email_level' => 'woocommerce_yowcl_loyalty_level_update_settings');
        $keys = 'redemption' === $feature ? array('min_points', 'max_points', 'min_cart') : (0 === strpos($feature, 'email_') ? array('enabled') : array($feature . '_points', $feature . '_enabled'));
        if ($spec['target'] !== $targets[$feature] || array_diff(array_keys($spec['patch']), $keys) || ('redemption' !== $feature && count($spec['patch']) !== count($keys))) { throw new RuntimeException('migration_malformed_evidence'); }
        $before = self::read( $spec['target'] );
        $current = self::decode( $before );
        $expected = array_replace( $current, $spec['patch'] );
        self::put( $spec['target'], self::encode( $expected, $before ), $before );
        if ( serialize( self::decode( self::read( $spec['target'] ) ) ) !== serialize( $expected ) ) { throw new RuntimeException( 'migration_semantics_readback_failed' ); }
        self::put( $name, '1', null );
    }
    public static function run() {
        if ( ! YOWCL_Free_Core::owns() ) { return; }
        foreach ( array( 'signup', 'login', 'review', 'levelup', 'redemption', 'email_reward', 'email_deduct', 'email_level' ) as $feature ) {
            try { self::locked( function() use ( $feature ) { self::migrate( $feature ); } ); unset( self::$errors[$feature] ); }
            catch ( Throwable $e ) { self::$errors[$feature] = $e->getMessage(); }
        }
    }
    public static function notices() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        foreach ( self::$errors as $feature => $code ) {
            echo '<div class="notice notice-error"><p>' . esc_html( sprintf( __( 'Loyalty compatibility migration needs attention (%1$s: %2$s). This feature is held until storage/settings are repaired and migration succeeds.', 'loyalty-for-woocommerce' ), $feature, $code ) ) . '</p></div>';
        }
    }
    /** Canonical merchant saves cannot race or precede unfinished initial migration. */
    public static function save( $feature, $target, $patch ) {
        self::locked( function() use ( $feature, $target, $patch ) {
            if ( ! self::ready( $feature ) ) { throw new RuntimeException( 'migration_incomplete' ); }
            $raw = self::read( $target );
            self::put( $target, self::encode( array_replace( self::decode( $raw ), $patch ), $raw ), $raw );
        } );
    }
    /** Old uncommitted cart evidence is cleared; canonical attempts/returns stay untouched. */
    public static function session() {
        if ( ! WC()->session || null !== WC()->session->get( 'loyf_funded_selection' ) ) { return; }
        if ( null === WC()->session->get( 'yoswc_loyalty_applied_points' ) && null === WC()->session->get( 'yoswc_loyalty_discount_amount' ) ) { return; }
        WC()->session->__unset( 'yoswc_loyalty_applied_points' );
        WC()->session->__unset( 'yoswc_loyalty_discount_amount' );
        wc_add_notice( __( 'Please reapply loyalty points after the upgrade. Your points balance has not changed.', 'loyalty-for-woocommerce' ), 'notice' );
    }
}

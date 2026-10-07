<?php
defined( 'ABSPATH' ) || exit;

/** Free-only admission policy; this witness never proves economic value. */
class YOWCL_Free_First_Purchase {
    const RULES = 'loyalty_extra_purchase_points_rules';
    const WITNESS = 'loyf_first_purchase_epoch_v1';
    public static function points( $value ) {
        if ( ! is_scalar( $value ) || ! preg_match( '/^[0-9]{1,8}$/D', (string) $value ) ) { throw new DomainException( 'first_purchase_points_invalid' ); }
        return (int) $value;
    }
    private static function decode( $raw ) {
        $value = null === $raw ? array() : maybe_unserialize( maybe_unserialize( $raw ) );
        if ( ! is_array( $value ) ) { throw new RuntimeException( 'first_purchase_settings_invalid' ); }
        return $value;
    }
    private static function fingerprint( array $rules ) {
        return hash( 'sha256', wp_json_encode( array( $rules['first_purchase_enabled'] ?? 'no', self::points( $rules['first_purchase_points'] ?? 0 ) ) ) );
    }
    private static function valid( $epoch, array $rules ) {
        return is_array( $epoch ) && 1 === ( $epoch['version'] ?? null ) && true === ( $epoch['enabled'] ?? null ) && is_int( $epoch['cutoff'] ?? null ) && $epoch['cutoff'] > 0 && $epoch['cutoff'] <= time() && ( $epoch['terms_hash'] ?? '' ) === self::fingerprint( $rules );
    }
    private static function raw( $name, $db ) {
        global $wpdb;
        return YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", $name ) );
    }
    public static function configuration() {
        global $wpdb;
        $rules = self::decode( self::raw( self::RULES, $wpdb->dbh ) );
        $points = self::points( $rules['first_purchase_points'] ?? 0 );
        $epoch = maybe_unserialize( self::raw( self::WITNESS, $wpdb->dbh ) );
        return array( 'points'=>$points, 'effective'=>'yes' === ( $rules['first_purchase_enabled'] ?? 'no' ) && $points > 0, 'epoch'=>$epoch, 'ready'=>self::valid( $epoch, $rules ) );
    }
    public static function save( $enabled, $points ) {
        if ( ! current_user_can( 'manage_options' ) ) { throw new DomainException( 'first_purchase_save_denied' ); }
        $points = self::points( $points );
        YOWCL_Free_Migrations::locked( static function () use ( $enabled, $points ) {
            global $wpdb;
            $db = $wpdb->dbh;
            if ( ! ( $db instanceof mysqli ) || YOWCL_Points_Lock::has_transaction( $db ) || 'InnoDB' !== YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $wpdb->options ) ) ) { throw new RuntimeException( 'first_purchase_options_storage_required' ); }
            $raw = self::raw( self::RULES, $db ); $old = self::decode( $raw );
            $previous = maybe_unserialize( self::raw( self::WITNESS, $db ) );
            // Invalid dormant amounts are ineffective, not a veto of a valid authorized replacement.
            try { $old_effective = 'yes' === ( $old['first_purchase_enabled'] ?? 'no' ) && self::points( $old['first_purchase_points'] ?? 0 ) > 0; }
            catch ( DomainException $invalid_old_amount ) { $old_effective = false; }
            $rules = array_replace( $old, array( 'first_purchase_enabled'=>$enabled ? 'yes' : 'no', 'first_purchase_points'=>(string) $points ) );
            $effective = $enabled && $points > 0;
            $epoch = array( 'version'=>1, 'enabled'=>(bool) $effective, 'cutoff'=>$effective ? ( $old_effective && self::valid( $previous, $old ) ? $previous['cutoff'] : time() ) : 0, 'terms_hash'=>self::fingerprint( $rules ) );
            // Keep a legacy serialized wrapper, and every unowned field, byte-semantically intact.
            $wrapped = null !== $raw && is_string( maybe_unserialize( $raw ) ) && is_serialized( maybe_unserialize( $raw ) );
            $writes = array( self::RULES=>maybe_serialize( $wrapped ? serialize( $rules ) : $rules ), self::WITNESS=>maybe_serialize( $epoch ) );
            $started = false;
            try {
                YOWCL_Points_Lock::query( $db, 'START TRANSACTION' ); $started = true;
                foreach ( $writes as $name=>$value ) {
                    YOWCL_Points_Lock::query( $db, $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'no') ON DUPLICATE KEY UPDATE option_value=VALUES(option_value)", $name, $value ) );
                    if ( self::raw( $name, $db ) !== $value ) { throw new RuntimeException( 'first_purchase_options_write_failed' ); }
                    YOWCL_Core_Rewards::checkpoint( 'first_purchase_settings_write', $name );
                }
                if ( $wpdb->dbh !== $db ) { throw new RuntimeException( 'first_purchase_options_connection_lost' ); }
                YOWCL_Points_Lock::query( $db, 'COMMIT' ); $started = false;
            } finally {
                if ( $started ) { try { YOWCL_Points_Lock::query( $db, 'ROLLBACK' ); } catch ( Throwable $ignored ) {} }
                foreach ( array_keys( $writes ) as $name ) { wp_cache_delete( $name, 'options' ); }
                wp_cache_delete( 'alloptions', 'options' ); wp_cache_delete( 'notoptions', 'options' );
            }
        } );
    }
    public static function admission( $order ) {
        return YOWCL_Free_Migrations::locked( static function () use ( $order ) {
            $config = self::configuration();
            $date = $order->get_date_created();
            return $config['effective'] && $config['ready'] && $date && $date->getTimestamp() > $config['epoch']['cutoff'] ? $config : null;
        } );
    }
    public static function skipped( $order ) {
        $skip = (bool) $order->get_parent_id() || 'shop_subscription' === $order->get_type();
        foreach ( array( 'wcs_order_contains_renewal', 'wcs_order_contains_resubscribe', 'wcs_order_contains_switch' ) as $function ) { if ( function_exists( $function ) && $function( $order ) ) { $skip = true; } }
        return (bool) apply_filters( 'yowcl_skip_points_for_order', $skip, $order, null, null );
    }
    /** Complete bounded native pagination; database errors throughout the query cannot mean empty. */
    public static function prior( $order ) {
        global $wpdb;
        $limit = 100; $page = 1; $total = null;
        $date = $order->get_date_created(); if ( ! $date ) { throw new RuntimeException( 'first_purchase_order_date_missing' ); }
        do {
            $error = false;
            $observe = static function ( $query ) use ( &$error ) { global $wpdb; if ( $wpdb->last_error ) { $error = true; } return $query; };
            add_filter( 'query', $observe, PHP_INT_MAX );
            try {
                $result = wc_get_orders( array( 'customer_id'=>$order->get_user_id(), 'status'=>array( 'wc-processing','wc-completed' ), 'exclude'=>array( $order->get_id() ), 'date_created'=>'<=' . $date->getTimestamp(), 'limit'=>$limit, 'page'=>$page, 'paginate'=>true, 'return'=>'ids', 'orderby'=>'ID', 'order'=>'ASC', 'cache_results'=>false ) );
                if ( $error || $wpdb->last_error ) { throw new RuntimeException( 'first_purchase_history_query_failed' ); }
            } finally { remove_filter( 'query', $observe, PHP_INT_MAX ); }
            $result = YOWCL_Advanced_Rewards::pagination( $result, $limit, $page );
            if ( null !== $total && $total !== $result['total'] ) { throw new RuntimeException( 'first_purchase_history_changed' ); }
            $total = $result['total'];
            foreach ( $result['orders'] as $id ) {
                $prior = wc_get_order( $id );
                if ( (int) $id === (int) $order->get_id() || ! $prior || (int) $prior->get_user_id() !== (int) $order->get_user_id() || ! $prior->has_status( array( 'processing','completed' ) ) || ! $prior->get_date_created() || $prior->get_date_created()->getTimestamp() > $date->getTimestamp() ) { throw new RuntimeException( 'first_purchase_history_invalid' ); }
                if ( $prior->get_date_created()->getTimestamp() === $date->getTimestamp() && $prior->get_id() > $order->get_id() ) { continue; }
                if ( ! self::skipped( $prior ) ) { return true; }
            }
            if ( $page >= 100 ) { throw new RuntimeException( 'first_purchase_history_incomplete' ); }
            $page++;
        } while ( $page <= $result['max_num_pages'] );
        return false;
    }
    public static function notices() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        try { $config = self::configuration(); $held = $config['effective'] && ! $config['ready']; } catch ( Throwable $e ) { $held = true; }
        if ( $held ) { echo '<div class="notice notice-warning"><p>' . esc_html__( 'First Purchase rewards are held. Explicitly save an enabled First Purchase configuration in Loyalty Extra points settings to establish its activation cutoff.', 'loyalty-for-woocommerce' ) . '</p></div>'; }
    }
}

<?php
/** Free Referral Lite policy: scalar terms, registered link recipients only. */
defined( 'ABSPATH' ) || exit;
class YOWCL_Free_Referral {
    const OPTION = 'loyf_referral_lite_v1';
    public static function settings() {
        global $wpdb;
        $rows = $wpdb->get_col( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s", self::OPTION ) );
        if ( $wpdb->last_error || count( $rows ) > 1 ) { throw new RuntimeException( 'referral_settings_unavailable' ); }
        $value = $rows ? maybe_unserialize( $rows[0] ) : array();
        if ( ! is_array( $value ) ) { return array( 'enabled'=>false, 'points'=>0 ); }
        try { $points = YOWCL_Free_First_Purchase::points( $value['points'] ?? 0 ); } catch ( DomainException $e ) { $points = 0; }
        return array( 'enabled'=>'yes' === ( $value['enabled'] ?? '' ) && $points > 0, 'points'=>$points );
    }
    public static function save( $enabled, $points ) {
        if ( ! current_user_can( 'manage_options' ) || ! YOWCL_Free_Core::owns() ) { throw new DomainException( 'referral_save_denied' ); }
        $points = YOWCL_Free_First_Purchase::points( $points );
        YOWCL_Free_Migrations::locked( static function () use ( $enabled, $points ) {
            $value = array( 'enabled'=>$enabled ? 'yes' : 'no', 'points'=>$points );
            update_option( self::OPTION, $value, false );
            if ( get_option( self::OPTION ) !== $value ) { throw new RuntimeException( 'referral_settings_not_saved' ); }
        } );
    }
    public static function discover( $order ) {
        $config = self::settings();
        $referee = (int) $order->get_user_id();
        $referrer = $config['enabled'] && $referee > 0 ? YOWCL_Helper_Referrals::get_referrer_from_cookie() : 0;
        $self = $referrer > 0 && $referrer === $referee;
        $terms = array( 'version'=>1, 'channel'=>$referrer ? 'link' : 'none', 'referrer'=>$referrer, 'referee'=>$referee,
            'identity'=>$referee > 0 ? 'user:' . $referee : '', 'email'=>'', 'role'=>'', 'frequency'=>'first_order',
            'award_statuses'=>array( 'wc-processing','wc-completed' ), 'reversal_statuses'=>array( 'wc-failed','wc-cancelled','wc-refunded' ),
            'expiration_policy'=>null, 'coupons'=>array(), 'self_referral'=>$self, 'events'=>array() );
        if ( $referrer && ! $self && get_userdata( $referrer ) && get_userdata( $referee ) ) {
            $terms['events'][] = array( 'key'=>'referral:link:' . $order->get_id() . ':referrer', 'user_id'=>$referrer, 'points'=>$config['points'],
                'action'=>'referral_link_referrer_reward', 'marker'=>'_yo_link_referrer_awarded',
                'description'=>sprintf( __( 'Referral %1$s reward (%2$s) for order %3$s', 'loyalty-for-woocommerce' ), 'link', 'referrer', $order->get_order_number() ) );
        }
        return $terms;
    }
    public static function terms( $order ) {
        $rows = array_values( $order->get_meta( YOWCL_Referral_Rewards::TERMS, false, 'edit' ) );
        if ( 1 !== count( $rows ) ) { throw new DomainException( 'referral_terms_ambiguous' ); }
        $t = $rows[0]->value;
        if ( ! is_array( $t ) || 1 !== ( $t['version'] ?? null ) || ! in_array( $t['channel'] ?? null, array( 'none','link' ), true ) ||
            ! is_int( $t['referee'] ?? null ) || $t['referee'] < 0 || ! is_int( $t['referrer'] ?? null ) || $t['referrer'] < 0 ||
            ( $t['identity'] ?? null ) !== ( $t['referee'] > 0 ? 'user:' . $t['referee'] : '' ) || 'first_order' !== ( $t['frequency'] ?? null ) ||
            ( $t['award_statuses'] ?? null ) !== array( 'wc-processing','wc-completed' ) || ( $t['reversal_statuses'] ?? null ) !== array( 'wc-failed','wc-cancelled','wc-refunded' ) ||
            null !== ( $t['expiration_policy'] ?? null ) || ! empty( $t['coupons'] ) || ! is_bool( $t['self_referral'] ?? null ) || ! is_array( $t['events'] ?? null ) || count( $t['events'] ) > 1 ) { throw new DomainException( 'referral_terms_unsupported' ); }
        foreach ( $t['events'] as $e ) {
            if ( 'link' !== $t['channel'] || $t['self_referral'] || $t['referee'] <= 0 || $t['referrer'] <= 0 || $t['referrer'] === $t['referee'] ||
                ! is_array( $e ) || ( $e['user_id'] ?? null ) !== $t['referrer'] || ! is_int( $e['points'] ?? null ) || $e['points'] <= 0 || $e['points'] > 99999999 ||
                'referral:link:' . $order->get_id() . ':referrer' !== ( $e['key'] ?? null ) || 'referral_link_referrer_reward' !== ( $e['action'] ?? null ) ||
                '_yo_link_referrer_awarded' !== ( $e['marker'] ?? null ) || ! is_string( $e['description'] ?? null ) ) { throw new DomainException( 'referral_terms_unsupported' ); }
        }
        return $t;
    }
    public static function legacy_attribution( $order ) {
        foreach ( array( '_yo_link_referrer_user_id','_yo_coupon_referrer_user_id','_yo_referrer_user_id','_yo_link_referral_awarded','_yo_coupon_referral_awarded','_yo_link_referrer_awarded','_yo_link_referee_awarded','_yo_coupon_referrer_awarded','_yo_referral_rewards_reversed' ) as $key ) {
            foreach ( $order->get_meta( $key, false, 'edit' ) as $m ) { if ( $m->value ) { return true; } }
        }
        return false;
    }
    public static function marked( $order, $key ) {
        foreach ( $order->get_meta( $key, false, 'edit' ) as $m ) { if ( 'yes' === $m->value ) { return true; } }
        return false;
    }
    public static function native_marked( $db, $table, $column, $id, $key ) {
        global $wpdb;
        $r = YOWCL_Points_Lock::query( $db, $wpdb->prepare( "SELECT meta_value FROM {$table} WHERE {$column}=%d AND meta_key=%s FOR UPDATE", $id, $key ) );
        try { while ( $row = $r->fetch_assoc() ) { if ( 'yes' === maybe_unserialize( $row['meta_value'] ) ) { return true; } } return false; } finally { $r->free(); }
    }
    public static function render_link( $scope ) {
        if ( ! is_user_logged_in() || ! YOWCL_Free_Core::owns() ) { return; }
        try { $link = YOWCL_Helper_Referrals::link_to_home( get_current_user_id() ); } catch ( Throwable $e ) { return; }
        if ( ! $link ) { return; }
        $id = 'loyf-referral-' . sanitize_key( $scope );
        echo '<div class="loyf-referral"><label for="' . esc_attr( $id ) . '">' . esc_html__( 'Your referral link', 'loyalty-for-woocommerce' ) . '</label> <input id="' . esc_attr( $id ) . '" type="text" readonly value="' . esc_attr( $link ) . '"> <button type="button" data-loyf-referral-copy="' . esc_attr( $id ) . '" data-copied="' . esc_attr__( 'Link copied', 'loyalty-for-woocommerce' ) . '" data-failed="' . esc_attr__( 'Select and copy the link', 'loyalty-for-woocommerce' ) . '">' . esc_html__( 'Copy link', 'loyalty-for-woocommerce' ) . '</button> <span role="status" aria-live="polite"></span></div>';
    }
}
add_action( 'woocommerce_checkout_order_created', array( 'YOWCL_Referral_Rewards', 'attach' ), PHP_INT_MAX );
add_action( 'woocommerce_store_api_checkout_order_processed', array( 'YOWCL_Referral_Rewards', 'attach' ), 2 );
add_action( 'woocommerce_order_status_changed', static function ( $id, $old, $new ) {
    if ( ! YOWCL_Free_Core::owns() ) { return; }
    try { YOWCL_Referral_Rewards::record_transition( $id, $new ); YOWCL_Referral_Rewards::process( $id, $new ); }
    catch ( Throwable $e ) { YOWCL_Core_Rewards::report( 'referral:order:' . $id, $e ); if ( ! $e instanceof DomainException ) { YOWCL_Referral_Rewards::queue( $id, $new ); } }
}, 20, 3 );
add_action( 'woocommerce_payment_complete', static function ( $id ) {
    try { YOWCL_Referral_Rewards::process( $id ); } catch ( Throwable $e ) { YOWCL_Core_Rewards::report( 'referral:order:' . $id, $e ); }
}, 20 );

add_action( 'wp_enqueue_scripts', static function () {
    if ( is_user_logged_in() && YOWCL_Free_Core::owns() ) {
        wp_enqueue_script( 'loyf-referral-lite', plugins_url( '../../../js/referral-lite.js', __FILE__ ), array(), hash_file( 'sha256', dirname( __DIR__, 3 ) . '/js/referral-lite.js' ), true );
    }
} );

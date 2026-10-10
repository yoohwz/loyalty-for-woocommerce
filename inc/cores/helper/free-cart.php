<?php
defined( 'ABSPATH' ) || exit;

/** Selection adapter only; order-redemption is the sole debit/return owner. */
class YOWCL_Free_Cart {
    const FEE_ID = 'loyf-redemption';
    public static function balance() {
        global $wpdb;
        $result = YOWCL_Points_Lock::query( $wpdb->dbh, $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id=%d AND meta_key='user_points'", get_current_user_id() ) );
        $values = array(); while ( $row = mysqli_fetch_row( $result ) ) { $values[] = $row[0]; } mysqli_free_result( $result );
        if ( count( $values ) > 1 || ( $values && ( ! YOWCL_Ledger_V2::integer_valid( $values[0], 99999999 ) || (int) $values[0] < 0 ) ) ) { throw new DomainException( 'invalid_balance_storage' ); }
        return array( 'available' => (int) ( $values[0] ?? 0 ) );
    }
    public static function rules() {
        $rules = YOWCL_Free_Migrations::redemption_policy();
        if ( (float) ( $rules['points'] ?? 0 ) <= 0 || (float) ( $rules['amount'] ?? 0 ) <= 0 ) { return array(); }
        foreach ( array( 'points', 'amount' ) as $key ) { if ( ! is_numeric( $rules[$key] ) || ! is_finite( (float) $rules[$key] ) ) { return array(); } }
        return array( 'points' => (float) $rules['points'], 'amount' => (float) $rules['amount'] );
    }
    private static function signature() {
        $items = array();
        foreach ( WC()->cart->get_cart() as $key => $item ) { $items[$key] = array( $item['product_id'], $item['variation_id'], $item['quantity'], $item['data']->get_price() ); }
        return hash( 'sha256', wp_json_encode( array( $items, WC()->cart->get_applied_coupons(), self::rules(), get_woocommerce_currency() ) ) );
    }
    public static function selection() {
        if ( ! YOWCL_Free_Core::owns() || ! WC()->session || ! WC()->cart || ! is_user_logged_in() ) { return null; }
        YOWCL_Order_Redemption::retire_paid_session();
        $selection = WC()->session->get( 'loyf_funded_selection' );
        if ( ! is_array( $selection ) || $selection['owner'] !== YOWCL_Order_Redemption::session_owner() || $selection['currency'] !== get_woocommerce_currency() ) { return null; }
        // Immutable, funded recovery uses original terms even after rules/cart change.
        $record = YOWCL_Order_Redemption::record( $selection['id'] );
        if ( $record ) { return in_array( $record['state'], array( 'prepared', 'active' ), true ) ? $selection : null; }
        if ( ! isset( $selection['signature'] ) || $selection['signature'] !== self::signature() ) { return null; }
        return $selection;
    }
    public static function apply( $points, $id = null ) {
        // One options owner covers quote, discount and signature through unfunded admission.
        return YOWCL_Free_Migrations::locked( static function() use ( $points, $id ) { return self::apply_locked( $points, $id ); } );
    }
    private static function apply_locked( $points, $id ) {
        if ( ! YOWCL_Free_Core::owns() || ! is_user_logged_in() || ! WC()->session || ! WC()->cart ) { throw new DomainException( __( 'Please sign in to use points.', 'loyalty-for-woocommerce' ) ); }
        if ( ! is_scalar( $points ) || ! preg_match( '/^[0-9]{1,8}$/D', (string) $points ) ) { throw new DomainException( __( 'A valid whole points amount is required.', 'loyalty-for-woocommerce' ) ); }
        $points = (int) $points;
        if ( null !== $id && ! YOWCL_Order_Redemption::valid_id( $id ) ) { throw new DomainException( 'invalid_redemption_operation' ); }
        $previous = WC()->session->get( 'loyf_funded_selection' );
        if ( $id && is_array( $previous ) && $previous['id'] === $id ) {
            if ( ! self::selection() || ( $previous['requested'] ?? $previous['points'] ) !== $points ) { throw new DomainException( 'redemption_operation_conflict' ); }
            return;
        }
        if ( $id && YOWCL_Order_Redemption::record( $id ) ) { throw new DomainException( 'redemption_operation_conflict' ); }
        $rules = self::rules(); $balance = self::balance();
        if ( ! $rules || $points < $rules['points'] || $points > $balance['available'] ) { throw new DomainException( __( 'Invalid points amount.', 'loyalty-for-woocommerce' ) ); }
        $discount = $points / $rules['points'] * $rules['amount'];
        $limit = max( 0, WC()->cart->get_subtotal() - WC()->cart->get_discount_total() );
        if ( $discount > $limit ) { $points = (int) floor( $limit / $rules['amount'] * $rules['points'] ); $discount = $points / $rules['points'] * $rules['amount']; }
        if ( $points < $rules['points'] || $discount <= 0 ) { throw new DomainException( __( 'Invalid points amount.', 'loyalty-for-woocommerce' ) ); }
        $id = $id ?: wp_generate_uuid4();
        self::detach_frozen_draft();
        WC()->session->set( 'yowcl_checkout_id', $id );
        WC()->session->set( 'loyf_funded_selection', array( 'id' => $id, 'requested' => (int) func_get_arg( 0 ), 'points' => $points, 'discount' => wc_format_decimal( $discount, wc_get_price_decimals() ), 'currency' => get_woocommerce_currency(), 'owner' => YOWCL_Order_Redemption::session_owner(), 'signature' => self::signature() ) );
        WC()->session->set( 'yoswc_loyalty_applied_points', $points );
        WC()->session->set( 'yoswc_loyalty_discount_amount', $discount );
    }
    private static function detach_frozen_draft() {
        $draft = wc_get_order( WC()->session->get( 'store_api_draft_order' ) );
        if ( $draft && YOWCL_Order_Redemption::record( $draft->get_meta( YOWCL_Order_Redemption::META ) ) ) { WC()->session->set( 'store_api_draft_order', null ); }
    }
    public static function clear() {
        if ( ! WC()->session ) { return; }
        self::detach_frozen_draft();
        foreach ( array( 'loyf_funded_selection', 'yowcl_checkout_id', 'yoswc_loyalty_applied_points', 'yoswc_loyalty_discount_amount' ) as $key ) { WC()->session->__unset( $key ); }
    }
    public static function fee( $cart ) {
        if ( is_admin() && ! wp_doing_ajax() && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) { return; }
        $selection = self::selection();
        if ( ! $selection ) { return; }
        try { $balance = self::balance(); } catch ( Throwable $e ) { return; }
        if ( $selection['points'] > $balance['available'] + YOWCL_Order_Redemption::funded_selection_points() || (float) $selection['discount'] > max( 0, $cart->get_subtotal() - $cart->get_discount_total() ) ) { return; }
        $cart->fees_api()->add_fee( array( 'id'=>self::FEE_ID, 'name'=>__( 'Points used', 'loyalty-for-woocommerce' ), 'amount'=>-(float) $selection['discount'], 'taxable'=>false ) );
    }
}

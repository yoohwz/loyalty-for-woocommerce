<?php
defined( 'ABSPATH' ) || exit;
class YOSWC_Loyalty_Using_Point_New_Order {
    const SESSION_POINTS = 'yoswc_loyalty_applied_points';
    const SESSION_DISCOUNT = 'yoswc_loyalty_discount_amount';
    public function __construct() {
        add_action( 'woocommerce_checkout_create_order', array( $this, 'store_applied_points_on_order' ), 20, 2 );
        add_action( 'woocommerce_thankyou', array( $this, 'check_loyalty_coupon_in_order' ), 10, 1 );
    }
    public function store_applied_points_on_order( $order, $data = array() ) {
        if ( ! YOWCL_Free_Core::owns() || ! WC()->session || ! $order instanceof WC_Order ) { return; }
        $selection = WC()->session->get( 'loyf_funded_selection' );
        if ( ! is_array( $selection ) || $selection['owner'] !== YOWCL_Order_Redemption::session_owner() || $selection['currency'] !== $order->get_currency() || (int) $order->get_user_id() !== get_current_user_id() ) { return; }
        $order->update_meta_data( '_used_points', $selection['points'] );
        $order->update_meta_data( '_used_points_discount', $selection['discount'] );
    }
    public function check_loyalty_coupon_in_order( $order_id ) {
        if ( ! YOWCL_Free_Core::owns() ) { return; }
        $order = wc_get_order( $order_id );
        if ( $order && $order->get_meta( YOWCL_Order_Redemption::META ) ) { YOWCL_Order_Redemption::commit( $order ); }
    }
}
new YOSWC_Loyalty_Using_Point_New_Order();

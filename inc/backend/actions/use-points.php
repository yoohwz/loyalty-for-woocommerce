<?php
defined( 'ABSPATH' ) || exit;
class YOSWC_Loyalty_Using_Point_New_Order {
    const SESSION_POINTS = 'yoswc_loyalty_applied_points';
    const SESSION_DISCOUNT = 'yoswc_loyalty_discount_amount';
    public function __construct() {
        add_action( 'woocommerce_store_api_checkout_update_order_meta', array( $this, 'store_applied_points_on_order' ), 20 );
        add_action( 'woocommerce_checkout_create_order', array( $this, 'store_applied_points_on_order' ), 20, 2 );
        add_action( 'woocommerce_checkout_create_order_fee_item', static function( $item, $key ) { if ( YOWCL_Free_Cart::FEE_ID === $key ) { $item->update_meta_data( '_loyf_redemption_fee', '1' ); } }, 10, 2 );
        // The Free adapter owns the scoped policy boundary; the canonical service remains the value writer.
        foreach ( array(
            'woocommerce_checkout_create_order' => array( 'prepare', PHP_INT_MAX ),
            'woocommerce_store_api_checkout_update_order_meta' => array( 'store_api_stage', PHP_INT_MAX ),
            'woocommerce_store_api_checkout_order_processed' => array( 'store_api_commit', 1 ),
        ) as $hook => $boundary ) {
            remove_action( $hook, array( 'YOWCL_Order_Redemption', $boundary[0] ), $boundary[1] );
            add_action( $hook, function( $order ) use ( $boundary ) { return $this->with_current_quote( $order, $boundary[0] ); }, $boundary[1] );
        }
        add_action( 'woocommerce_thankyou', array( $this, 'check_loyalty_coupon_in_order' ), 10, 1 );
    }
    private function with_current_quote( $order, $method ) {
        if ( null === YOWCL_Free_Migrations::read( YOWCL_Free_Migrations::witness( 'redemption' ) . '_automatic' ) ) { return YOWCL_Order_Redemption::$method( $order ); }
        return YOWCL_Free_Migrations::locked( static function() use ( $order, $method ) {
            if ( null !== YOWCL_Free_Migrations::read( YOWCL_Free_Migrations::witness( 'redemption' ) . '_automatic' ) && ! YOWCL_Order_Redemption::record( (string) $order->get_meta( YOWCL_Order_Redemption::META, true ) ) ) {
                $discount = 0.0;
                foreach ( $order->get_fees() as $fee ) { if ( '1' === $fee->get_meta( '_loyf_redemption_fee', true ) && (float) $fee->get_total() < 0 ) { $discount -= (float) $fee->get_total(); } }
                if ( $discount > 0 || (float) $order->get_meta( '_used_points' ) > 0 ) {
                    $selection = YOWCL_Free_Cart::selection();
                    if ( ! $selection || wc_format_decimal( $discount, wc_get_price_decimals() ) !== $selection['discount'] ) {
                        // Store API may already have saved its mutable draft. It cannot become payable without admission.
                        if ( 'store_api_commit' === $method && YOWCL_Free_Core::owns() ) { $order->set_status( 'checkout-draft' ); $order->save(); }
                        throw new RuntimeException( __( 'Your cart or redemption rules changed. Please reapply points.', 'loyalty-for-woocommerce' ) );
                    }
                }
            }
            return YOWCL_Order_Redemption::$method( $order );
        } );
    }
    public function store_applied_points_on_order( $order, $data = array() ) {
        if ( ! YOWCL_Free_Core::owns() || ! WC()->session || ! $order instanceof WC_Order ) { return; }
        $selection = YOWCL_Free_Cart::selection();
        $order->delete_meta_data( '_used_points' ); $order->delete_meta_data( '_used_points_discount' );
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

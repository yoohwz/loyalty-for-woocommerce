<?php
defined( 'ABSPATH' ) || exit;

class YOWCL_Free_Blocks {
    const NS = 'loyf-redemption';
    public static function boot() {
        add_action( 'woocommerce_blocks_loaded', array( __CLASS__, 'register' ) );
        if ( did_action( 'woocommerce_blocks_loaded' ) ) { self::register(); }
    }
    public static function register() {
        woocommerce_store_api_register_endpoint_data( array( 'endpoint' => Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER, 'namespace' => self::NS, 'data_callback' => array( __CLASS__, 'data' ), 'schema_callback' => array( __CLASS__, 'schema' ), 'schema_type' => ARRAY_A ) );
        woocommerce_store_api_register_update_callback( array( 'namespace' => self::NS, 'callback' => array( __CLASS__, 'update' ) ) );
        require_once __DIR__ . '/free-blocks-integration.php';
        foreach ( array( 'cart', 'checkout' ) as $block ) { add_action( 'woocommerce_blocks_' . $block . '_block_registration', static function ( $registry ) { $registry->register( new YOWCL_Free_Blocks_Integration() ); } ); }
    }
    public static function schema() {
        $schema = array();
        foreach ( array( 'enabled'=>'boolean', 'available'=>'integer', 'selected'=>'integer', 'minimum'=>'integer', 'earned'=>'integer', 'show_earned_cart'=>'boolean', 'show_earned_checkout'=>'boolean', 'discount'=>'string', 'operation_id'=>'string', 'message'=>'string' ) as $key=>$type ) { $schema[$key] = array( 'type'=>$type, 'readonly'=>true ); }
        return $schema;
    }
    public static function data() {
        $data = array( 'enabled'=>false, 'available'=>0, 'selected'=>0, 'minimum'=>1, 'earned'=>0, 'show_earned_cart'=>false, 'show_earned_checkout'=>false, 'discount'=>'', 'operation_id'=>'', 'message'=>'' );
        if ( ! is_user_logged_in() || ! YOWCL_Free_Core::owns() ) { return $data; }
        $display = maybe_unserialize( get_option( 'loyalty_customization_cart_checkout', array() ) );
        $data['show_earned_cart'] = is_array( $display ) && isset( $display['cart'] ) && 1 == $display['cart'];
        $data['show_earned_checkout'] = is_array( $display ) && isset( $display['checkout'] ) && 1 == $display['checkout'];
        try {
            $rules = YOWCL_Free_Cart::rules(); $balance = YOWCL_Free_Cart::balance(); $selection = YOWCL_Free_Cart::selection();
            $data['available'] = $balance['available']; $data['enabled'] = (bool) $rules;
            $data['minimum'] = $rules ? (int) ceil( $rules['points'] ) : 1;
            $data['earned'] = ( new YOSWC_Loyalty_Using_Point_Cart_Checkout( false ) )->calculate_potential_earned_points( get_current_user_id() );
            if ( $selection ) { $data['selected'] = $selection['points']; $data['discount'] = html_entity_decode( wp_strip_all_tags( wc_price( $selection['discount'] ) ), ENT_QUOTES, 'UTF-8' ); $data['operation_id'] = $selection['id']; }
            elseif ( WC()->session->get( 'loyf_funded_selection' ) ) { $data['message'] = __( 'Your cart or redemption rules changed. Please reapply points.', 'loyalty-for-woocommerce' ); }
        } catch ( Throwable $e ) { $data['enabled'] = false; $data['message'] = __( 'Points are unavailable. Please retry or reapply points.', 'loyalty-for-woocommerce' ); }
        return $data;
    }
    public static function update( $data ) {
        if ( ! is_user_logged_in() || ! YOWCL_Free_Core::owns() ) { throw new Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'loyf_sign_in_required', __( 'Please sign in to use points.', 'loyalty-for-woocommerce' ), 403 ); }
        try {
            if ( ! is_array( $data ) || array_diff( array_keys( $data ), array( 'action', 'points', 'operation_id' ) ) || ! YOWCL_Order_Redemption::valid_id( $data['operation_id'] ?? null ) ) { throw new DomainException( 'invalid_redemption_operation' ); }
            if ( 'apply' === ( $data['action'] ?? null ) ) { YOWCL_Free_Cart::apply( $data['points'] ?? null, $data['operation_id'] ); }
            elseif ( 'remove' === ( $data['action'] ?? null ) ) {
                $selection = WC()->session->get( 'loyf_funded_selection' );
                if ( $selection && $selection['id'] !== $data['operation_id'] ) { throw new DomainException( 'redemption_operation_conflict' ); }
                YOWCL_Free_Cart::clear();
            } else { throw new DomainException( 'unsupported_redemption_operation' ); }
        } catch ( Throwable $e ) { throw new Automattic\WooCommerce\StoreApi\Exceptions\RouteException( 'loyf_redemption_rejected', __( 'Points could not be applied. Check your balance and reapply points.', 'loyalty-for-woocommerce' ), 409 ); }
    }
}

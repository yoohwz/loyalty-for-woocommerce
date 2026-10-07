<?php
defined( 'ABSPATH' ) || exit;
class YOWCL_Actions_Earn_Points {
    public function __construct( $register = true ) { if ( $register ) { add_action( 'woocommerce_order_status_changed', array( $this, 'on_order_status_changed' ), 10, 3 ); } }
    public function on_order_status_changed( $order_id, $old_status, $new_status ) {
        if ( ! YOWCL_Free_Core::owns() ) { return; }
        YOWCL_Order_Rewards::locked( (int) $order_id, static function ( $order, $owner ) use ( $old_status, $new_status ) {
            $user = (int) $order->get_user_id(); $id = (int) $order->get_id();
            if ( ! $user ) { return; }
            $key = 'reward:order:' . $id;
            $retry = static function () use ( $id, $old_status, $new_status ) { YOWCL_Order_Rewards::defer( $id, array( $old_status, $new_status ) ); };
            $project = static function ( $row ) use ( $order, $owner, $user ) {
                $owner();
                $terms = $order->get_meta( YOWCL_Order_Rewards::TERMS_META, true );
                if ( ! is_array( $terms ) || (int) $terms['user_id'] !== $user || (int) $terms['points'] !== (int) $row['available_delta'] ) { throw new RuntimeException( 'order_reward_terms_missing' ); }
                YOWCL_Order_Rewards::meta( $order, '_points_awarded', (int) $row['available_delta'] );
                YOWCL_Order_Rewards::level( $user, $terms['levels'] ); $owner();
            };
            if ( YOWCL_Core_Rewards::recover( $user, $key, 'order_reward', $project, $id, $retry ) ) { return; }
            $statuses = maybe_unserialize( get_option( 'loyalty_points_earning_status', array() ) );
            if ( $order->get_status() !== $new_status || ! is_array( $statuses ) || ! in_array( 'wc-' . $new_status, $statuses, true ) || $order->meta_exists( '_points_awarded' ) || $order->meta_exists( '_points_deducted' ) ) { return; }
            $rules = maybe_unserialize( get_option( 'loyalty_points_earning_rules', array() ) );
            $role = YOWCL_Helper_Roles::get_highest_loyalty_user_role( $user );
            $rule = $rules[$role] ?? array();
            if ( empty( $rule['amount'] ) || $rule['amount'] <= 0 || empty( $rule['points'] ) || $rule['points'] <= 0 ) { return; }
            // Retain the Free calculation; advanced product/category rules are absent.
            $base = (float) $order->get_subtotal();
            $options = (array) maybe_unserialize( get_option( 'loyalty_points_earning_option', array() ) );
            if ( in_array( 'coupons', $options, true ) ) { $base -= $order->get_discount_total(); }
            if ( in_array( 'taxes', $options, true ) ) { $base -= $order->get_total_tax(); }
            $raw = max( 0, $base ) / $rule['amount'] * $rule['points'];
            $points = 'round_up' === get_option( 'loyalty_points_rounding', 'round_down' ) ? ceil( $raw ) : floor( $raw );
            if ( ! is_finite( $points ) || $points <= 0 || $points > 99999999 ) { return; }
            $levels = (array) maybe_unserialize( get_option( 'loyalty_levels_rules', array() ) );
            YOWCL_Order_Rewards::meta( $order, YOWCL_Order_Rewards::TERMS_META, array( 'user_id' => $user, 'points' => (int) $points, 'levels' => $levels, 'campaigns' => array() ) ); $owner();
            YOWCL_Core_Rewards::award( $user, (int) $points, $key, 'order_reward', __( 'Purchase an order', 'loyalty-for-woocommerce' ), $project,
                static function ( $row, $balance ) { YOWCL_Points_Events::reward( (int) $row['user_id'], (int) $row['available_delta'], $balance['new_points'], (int) $row['order_id'] ); }, $id, $retry );
        }, array( $old_status, $new_status ) );
    }
}

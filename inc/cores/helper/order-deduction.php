<?php

defined('ABSPATH') || exit;

class YOWCL_Actions_Deduct_Points {
	const ORDER_LOCK_META = '_yowcl_points_deduct_lock';
    
    public function __construct( $register = true ) {
        if ( $register ) { add_action('woocommerce_order_status_changed', [$this, 'on_order_status_changed'], 10, 3); }
    }

    public function on_order_status_changed($order_id, $old_status, $new_status) {
        if ( ! YOWCL_Free_Core::owns() ) { return; }

        YOWCL_Order_Rewards::locked( (int) $order_id, static function ( $order, $owner ) use ( $old_status, $new_status ) {
            $user_id = (int) $order->get_user_id();
            if ( ! $user_id ) { return; }
            $order_id = (int) $order->get_id();
            $key = 'reward:order:' . $order_id . ':reversal';
            $retry = static function () use ( $order_id, $old_status, $new_status ) { YOWCL_Order_Rewards::defer( $order_id, array( $old_status, $new_status ) ); };
            $project = static function ( $row ) use ( $order, $owner, $user_id ) {
                $owner();
                $original = YOWCL_Points_Transaction::find( 'reward:order:' . $order->get_id() );
                YOWCL_Order_Rewards::meta( $order, '_points_deducted', (int) $original['available_delta'] );
                $terms = $order->get_meta( YOWCL_Order_Rewards::TERMS_META, true );
                $rules = is_array( $terms ) ? $terms['levels'] : get_option( 'loyalty_levels_rules', array() );
                YOWCL_Order_Rewards::level( $user_id, is_array( $rules ) ? $rules : array() );
                $owner();
            };
            if ( YOWCL_Core_Rewards::recover( $user_id, $key, 'points_deducted', $project, $order_id, $retry ) ) { return; }
            // A historical terminal marker guards value only; repair its current level independently.
            if ( $order->get_meta( '_points_deducted', true ) && ! YOWCL_Points_Transaction::find( 'reward:order:' . $order_id ) ) {
                $rules = maybe_unserialize( get_option( 'loyalty_levels_rules', array() ) );
                YOWCL_Order_Rewards::level( $user_id, is_array( $rules ) ? $rules : array() );
                return;
            }
            $statuses = maybe_unserialize( get_option( 'loyalty_points_deduction_status', array() ) );
            if ( ! is_array( $statuses ) || ! in_array( 'wc-' . $new_status, $statuses, true ) || $order->get_meta( '_points_deducted', true ) ) { return; }
            $descriptions = array( 'cancelled' => __( 'Order is cancelled', 'loyalty-for-woocommerce' ), 'refunded' => __( 'Order is refunded', 'loyalty-for-woocommerce' ), 'failed' => __( 'Order is failed', 'loyalty-for-woocommerce' ) );
            $description = $descriptions[ $new_status ] ?? __( 'Order status change', 'loyalty-for-woocommerce' );
            $original = YOWCL_Points_Transaction::find( 'reward:order:' . $order_id );
            $owner();
            if ( ! $original ) {
                if ( $order->meta_exists( self::ORDER_LOCK_META ) ) { return; }
                if ( YOWCL_Order_Rewards::legacy_deduct( $order, $description ) ) {
                    $rules = maybe_unserialize( get_option( 'loyalty_levels_rules', array() ) );
                    YOWCL_Order_Rewards::level( $user_id, is_array( $rules ) ? $rules : array() );
                }
                return;
            }
            $result = YOWCL_Points_Transaction::reverse_order_reward( $user_id, $order_id, $description );
            if ( 'busy' === $result['status'] ) { throw new RuntimeException( 'order_reward_value_busy' ); }
            if ( ! in_array( $result['status'], array( 'applied', 'already_applied' ), true ) ) { return; }
            $row = YOWCL_Points_Transaction::find( $key );
            YOWCL_Core_Rewards::checkpoint( 'committed', $key );
            YOWCL_Core_Rewards::finish( $row, $project );
            if ( 'applied' === $result['status'] ) {
                try { YOWCL_Points_Events::deduct( $user_id, -(int) $row['available_delta'], $result['new_points'], $order_id ); }
                catch ( Throwable $e ) { YOWCL_Core_Rewards::report( $key, $e ); }
            }
        }, array( $old_status, $new_status ) );
    }
}


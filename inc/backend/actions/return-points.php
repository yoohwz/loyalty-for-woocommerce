<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Return_Used_Point {
    public function __construct() {
        add_action('woocommerce_order_status_cancelled', array($this, 'return_points_to_user'), 10, 1);
        add_action('woocommerce_order_status_refunded', array($this, 'return_points_to_user'), 10, 1);
        add_action('woocommerce_order_status_failed', array($this, 'return_points_to_user'), 10, 1);
    }

    public function return_points_to_user($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $user_id = $order->get_user_id();
        if (!$user_id) {
            return;
        }

        $used_points = $order->get_meta('_used_points');

        if (!empty($used_points) && $used_points > 0) {
            $current_points = get_user_meta($user_id, 'user_points', true);
            $current_points = !empty($current_points) ? (float)$current_points : 0;

            $new_points = $current_points + (float)$used_points;

            update_user_meta($user_id, 'user_points', $new_points);

            $order->delete_meta_data('_used_points');
            $order->delete_meta_data('_loyalty_points_processed');
            $order->save();

			YOSWC_Loyalty_Database::insert_points_log(
				array(
					'user_id' => $user_id,
					'action' => 'points_return',
					'order_id' => $order_id,
					'amount' => $used_points,
					'description' => __('Order is incomplete', 'loyalty-for-woocommerce'),
				)
			);
        }
    }
}

new YOSWC_Loyalty_Return_Used_Point();

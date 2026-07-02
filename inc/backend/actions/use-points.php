<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Using_Point_New_Order {
    const SESSION_POINTS = 'yoswc_loyalty_applied_points';
    const SESSION_DISCOUNT = 'yoswc_loyalty_discount_amount';

    public function __construct() {
        add_action('woocommerce_checkout_create_order', array($this, 'store_applied_points_on_order'), 20, 2);
        add_action('woocommerce_thankyou', array($this, 'check_loyalty_coupon_in_order'), 10, 1);
    }

    public function store_applied_points_on_order($order, $data = array()) {
        if (!WC()->session || !$order instanceof WC_Order) {
            return;
        }

        $used_points = (float) WC()->session->get(self::SESSION_POINTS, 0);
        $discount = (float) WC()->session->get(self::SESSION_DISCOUNT, 0);
        $user_id = (int) $order->get_user_id();

        if ($used_points <= 0 || $discount <= 0 || !$user_id || $user_id !== get_current_user_id()) {
            return;
        }

        $order->update_meta_data('_used_points', $used_points);
        $order->update_meta_data('_used_points_discount', $discount);
    }

    public function check_loyalty_coupon_in_order($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $user_id = $order->get_user_id();
        if (!$user_id) {
            return;
        }

        if ($order->get_meta('_loyalty_points_processed')) {
            return;
        }

        $used_points = (float) $order->get_meta('_used_points');
        if ($used_points <= 0) {
            return;
        }

        $current_points = get_user_meta($user_id, 'user_points', true);
        $new_points = !empty($current_points) ? (float) $current_points - $used_points : 0;

        if ($new_points < 0) {
            $new_points = 0;
        }

        update_user_meta($user_id, 'user_points', $new_points);

        $order->update_meta_data('_loyalty_points_processed', 'yes');
        $order->save();

        if (WC()->session && get_current_user_id() === (int) $user_id) {
            WC()->session->__unset(self::SESSION_POINTS);
            WC()->session->__unset(self::SESSION_DISCOUNT);
        }

        YOSWC_Loyalty_Database::insert_points_log(
            array(
                'user_id' => $user_id,
                'action' => 'points_used',
                'order_id' => $order_id,
                'amount' => $used_points,
                'description' => __('Get a discount by using points', 'loyalty-for-woocommerce'),
            )
        );
    }
}

new YOSWC_Loyalty_Using_Point_New_Order();

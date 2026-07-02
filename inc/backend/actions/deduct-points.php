<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Deducting_Point_Order_Status_Updated {
    
    public function __construct() {
        add_action('woocommerce_order_status_changed', [$this, 'on_order_status_changed'], 10, 3);
    }

    public function on_order_status_changed($order_id, $old_status, $new_status) {
        $deduction_statuses = maybe_unserialize(get_option('loyalty_points_deduction_status', []));
        $new_status_with_wc = 'wc-' . $new_status;

        if (!is_array($deduction_statuses) || !in_array($new_status_with_wc, $deduction_statuses)) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        $user_id = $order->get_user_id();
        if (!$user_id) {
            return;
        }

        $user = new WP_User($user_id);

        $deducted_points = get_post_meta($order_id, '_points_awarded', true);
        if (!$deducted_points) {
            return;
        }

        if (get_post_meta($order_id, '_points_deducted', true)) {
            return;
        }

        $current_points = (int) get_user_meta($user_id, 'user_points', true);
        $current_earning_points = (int) get_user_meta($user_id, 'user_earning_points', true);
        $new_points = max(0, $current_points - $deducted_points); 
        $new_earning_points = max(0, $current_earning_points - $deducted_points);
        
        update_user_meta($user_id, 'user_points', $new_points);
        update_user_meta($user_id, 'user_earning_points', $new_earning_points);

		do_action('yoswc_loyalty_points_deduct', $user_id, $deducted_points, $new_points, $order_id);

        update_post_meta($order_id, '_points_deducted', $deducted_points);

        switch ($new_status) {
            case 'cancelled':
                $description = __('Order is cancelled', 'loyalty-for-woocommerce');
                break;
            case 'refunded':
                $description = __('Order is refunded', 'loyalty-for-woocommerce');
                break;
            case 'failed':
                $description = __('Order is failed', 'loyalty-for-woocommerce');
                break;
            default:
                $description = __('Order status change', 'loyalty-for-woocommerce');
        }

        $loyalty_levels_rules = maybe_unserialize(get_option('loyalty_levels_rules', []));
        if (is_array($loyalty_levels_rules) && !empty($loyalty_levels_rules)) {
            $sorted_rules = [];
            foreach ($loyalty_levels_rules as $level => $rule) {
                $sorted_rules[$level] = (int) $rule['from'];
            }
            asort($sorted_rules);

            $updated_level = null;
            foreach ($sorted_rules as $level => $threshold) {
                if ($new_earning_points >= $threshold) {
                    $updated_level = $level;
                }
            }

            if ($updated_level && !in_array($updated_level, $user->roles)) {
                $user->set_role($updated_level);
                $description .= " - Level updated: {$updated_level}";

                do_action('yoswc_loyalty_level_update', $user_id, $updated_level, $new_earning_points);
            }
        }

        YOSWC_Loyalty_Database::insert_points_log(
            array(
                'user_id' => $user_id,
                'action' => 'points_deducted',
                'order_id' => $order_id,
                'amount' => $deducted_points,
                'description' => $description,
            )
        );
    }
}

new YOSWC_Loyalty_Deducting_Point_Order_Status_Updated();

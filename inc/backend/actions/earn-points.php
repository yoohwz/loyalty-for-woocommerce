<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Earning_Point_Order_Status_Updated {
    
    public function __construct() {
        add_action('woocommerce_order_status_changed', [$this, 'on_order_status_changed'], 10, 3);
    }

    public function on_order_status_changed($order_id, $old_status, $new_status) {
        $earning_statuses = maybe_unserialize(get_option('loyalty_points_earning_status', []));

        $new_status_with_wc = 'wc-' . $new_status;

        if (!is_array($earning_statuses) || !in_array($new_status_with_wc, $earning_statuses)) {
            return;
        }

        $order = wc_get_order($order_id);
        if (!$order) {
            return;
        }

        if ($order && $order->get_user_id()) {
            $user_id = $order->get_user_id();
            $user = new WP_User($user_id);
            $user_role = $user->roles[0];
            
            if (get_post_meta($order_id, '_points_awarded', true)) {
                return;
            }

            $earning_rules = maybe_unserialize(get_option('loyalty_points_earning_rules', []));

            if (isset($earning_rules[$user_role])) {
                $rule = $earning_rules[$user_role];
                $points = (int) $rule['points'];
                $amount = (int) $rule['amount'];

                $earning_options = maybe_unserialize(get_option('loyalty_points_earning_option', []));

                $order_subtotal = $order->get_subtotal();
                if (is_array($earning_options)) {
                    if (in_array('coupons', $earning_options)) {
                        $order_subtotal -= $order->get_discount_total();
                    }
                    if (in_array('taxes', $earning_options)) {
                        $order_subtotal -= $order->get_total_tax();
                    }
                }

                $earned_points = ($order_subtotal / $amount) * $points;

                $rounding_option = get_option('loyalty_points_rounding', 'round_down');
                if ($rounding_option === 'round_up') {
                    $earned_points = ceil($earned_points);
                } else {
                    $earned_points = floor($earned_points);
                }

                $current_points = (int) get_user_meta($user_id, 'user_points', true);
                $earning_points = intval(get_user_meta($user_id, 'user_earning_points', true));
                $new_points = $current_points + $earned_points;
                $new_earning_points =  $earning_points + $earned_points;
                update_user_meta($user_id, 'user_points', $new_points);
                update_user_meta($user_id, 'user_earning_points', $new_earning_points);

                do_action('yoswc_loyalty_points_reward', $user_id, $earned_points, $new_points, $order_id);

                update_post_meta($order_id, '_points_awarded', $earned_points);

                $description = __('Purchase an order', 'loyalty-for-woocommerce');

                $loyalty_levels_rules = maybe_unserialize(get_option('loyalty_levels_rules', []));
                if (is_array($loyalty_levels_rules)) {
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

                    if ($updated_level && $user->roles[0] !== $updated_level) {
                        $user->set_role($updated_level);
                        $description .= " - Level updated: {$updated_level}";

                        do_action('yoswc_loyalty_level_update', $user_id, $updated_level, $new_earning_points);
                    }
                }

                YOSWC_Loyalty_Database::insert_points_log(
                    array(
                        'user_id' => $user_id,
                        'action' => 'order_reward',
                        'order_id' => $order_id,
                        'amount' => $earned_points,
                        'description' => $description,
                    )
                );
            }
        }
    }
}

new YOSWC_Loyalty_Earning_Point_Order_Status_Updated();

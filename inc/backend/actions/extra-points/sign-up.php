<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Extra_Points_Sign_Up {
    public function __construct() {
        add_action('user_register', [$this, 'reward_points_on_sign_up'], 10, 1);
    }

    public function reward_points_on_sign_up($user_id) {
        $extra_points_rules = maybe_unserialize(get_option('loyalty_extra_points_rules', []));
        
        $sign_up_points = isset($extra_points_rules['signup_points']) ? intval($extra_points_rules['signup_points']) : 0;

        if ($sign_up_points <= 0) {
            return;
        }

        $current_points = intval(get_user_meta($user_id, 'user_points', true));
        $earning_points = intval(get_user_meta($user_id, 'user_earning_points', true));
        $new_points = $current_points + $sign_up_points;
        $new_earning_points = $earning_points + $sign_up_points;

        update_user_meta($user_id, 'user_points', $new_points);
        update_user_meta($user_id, 'user_earning_points', $new_earning_points);

        $description = __('Sign-up bonus', 'loyalty-for-woocommerce');

        YOSWC_Loyalty_Database::insert_points_log(
            array(
                'user_id' => $user_id,
                'action' => 'sign_up_reward',
                'amount' => $sign_up_points,
                'description' => $description,
            )
        );

        $order_id = null;
        do_action('yoswc_loyalty_points_reward', $user_id, $sign_up_points, $new_points, $order_id);
    }
}

new YOSWC_Loyalty_Extra_Points_Sign_Up();

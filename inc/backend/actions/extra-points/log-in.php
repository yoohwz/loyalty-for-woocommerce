<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Extra_Points_Daily_Login {
    public function __construct() {
        // Hook into the login event. Note: wp_login provides both the username and WP_User object.
        add_action('wp_login', [$this, 'reward_points_on_daily_login'], 10, 2);
    }

    public function reward_points_on_daily_login($user_login, $user) {
        // Retrieve extra points rules from options.
        $extra_points_rules = maybe_unserialize(get_option('loyalty_extra_points_rules', []));
        $daily_login_points = isset($extra_points_rules['login_points']) ? intval($extra_points_rules['login_points']) : 0;

        // If no points are set, or the value is non-positive, exit.
        if ($daily_login_points <= 0) {
            return;
        }

        // Check if the bonus has already been awarded today.
        $last_reward_date = get_user_meta($user->ID, 'loyalty_last_daily_login', true);
        $today = current_time('Y-m-d');
        if ($last_reward_date === $today) {
            return;
        }

        // Record today's date so the bonus isn't awarded again.
        update_user_meta($user->ID, 'loyalty_last_daily_login', $today);

        // Update the user's points.
        $current_points = intval(get_user_meta($user->ID, 'user_points', true));
        $earning_points = intval(get_user_meta($user->ID, 'user_earning_points', true));
        $new_points = $current_points + $daily_login_points;
        $new_earning_points = $earning_points + $daily_login_points;

        update_user_meta($user->ID, 'user_points', $new_points);
        update_user_meta($user->ID, 'user_earning_points', $new_earning_points);

        $description = __('Daily login bonus', 'loyalty-for-woocommerce');

        YOSWC_Loyalty_Database::insert_points_log(
            array(
                'user_id'     => $user->ID,
                'action'      => 'daily_login_reward',
                'amount'      => $daily_login_points,
                'description' => $description,
            )
        );

        // Trigger any additional actions if needed.
        $order_id = null;
        do_action('yoswc_loyalty_points_reward', $user->ID, $daily_login_points, $new_points, $order_id);
    }
}

new YOSWC_Loyalty_Extra_Points_Daily_Login();

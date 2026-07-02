<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Notifications_Email {
    public function __construct() {
        $notification_options = maybe_unserialize(get_option('loyalty_notification_email', []));

        if (is_array($notification_options) && !empty($notification_options['points_update'])) {
            add_action('yoswc_loyalty_points_reward', [$this, 'send_points_reward_email'], 10, 4);
            add_action('yoswc_loyalty_points_deduct', [$this, 'send_points_deduct_email'], 10, 4);
        }

        if (is_array($notification_options) && !empty($notification_options['level_update'])) {
            add_action('yoswc_loyalty_level_update', [$this, 'send_level_update_email'], 10, 3);
        }
    }

    public function send_points_reward_email($user_id, $earned_points, $new_points, $order_id) {
        $user_info = get_userdata($user_id);
        if (!$user_info) {
            return;
        }
        $to_email = $user_info->user_email;
        $first_name = $user_info->first_name;

        // Get the points page slug from 'loyalty_customization_my_account' option
        $loyalty_options = get_option('loyalty_customization_my_account');
        $points_slug = '';
        if (!empty($loyalty_options)) {
            $loyalty_options = maybe_unserialize($loyalty_options); // Decode serialized data
            $points_slug = isset($loyalty_options['my_account_slug']) ? $loyalty_options['my_account_slug'] : '';
        }

        // Build the full URL for the points page
        $account_url = wc_get_page_permalink('myaccount');
        $points_url = trailingslashit($account_url) . $points_slug;

        $subject = __('You have earned new points!', 'loyalty-for-woocommerce');
        $heading = __('Your points was updated', 'loyalty-for-woocommerce');
        $account_url = wc_get_page_permalink('myaccount');
        $message = sprintf(
            /* translators: 1: Customer first name, 2: Earned points, 3: New total points, 4: Account URL */
            __(
                'Hi %1$s,<br><br>Your loyalty points have been updated!<br><br>You have earned <strong>%2$d points</strong> and now have a total of <strong>%3$d points</strong>.<br><br><a href="%4$s">Click here to see points details.</a><br><br>Thank you for purchasing.',
                'loyalty-for-woocommerce'
            ),
            $first_name,
            $earned_points,
            $new_points,
            esc_url($points_url)
        );        

        $mailer = WC()->mailer();

        $wrapped_message = $mailer->wrap_message($heading, $message);

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $mailer->send($to_email, $subject, $wrapped_message, $headers);
    }

	public function send_points_deduct_email($user_id, $deducted_points, $new_points, $order_id) {
        $user_info = get_userdata($user_id);
        if (!$user_info) {
            return;
        }
        $to_email = $user_info->user_email;
        $first_name = $user_info->first_name;

        // Get the points page slug from 'loyalty_customization_my_account' option
        $loyalty_options = get_option('loyalty_customization_my_account');
        $points_slug = '';
        if (!empty($loyalty_options)) {
            $loyalty_options = maybe_unserialize($loyalty_options); // Decode serialized data
            $points_slug = isset($loyalty_options['my_account_slug']) ? $loyalty_options['my_account_slug'] : '';
        }

        // Build the full URL for the points page
        $account_url = wc_get_page_permalink('myaccount');
        $points_url = trailingslashit($account_url) . $points_slug;

        $subject = __('You have deducted points!', 'loyalty-for-woocommerce');
        $heading = __('Your points was updated', 'loyalty-for-woocommerce');
        $account_url = wc_get_page_permalink('myaccount');
        $message = sprintf(
            /* translators: 1: Customer first name, 2: Deducted points, 3: New total points, 4: Account URL */
            __(
                'Hi %1$s,<br><br>Your loyalty points have been updated!<br><br>You have deducted <strong>%2$d points</strong> and now have a total of <strong>%3$d points</strong>.<br><br><a href="%4$s">Click here to see points details.</a><br><br>Thank you for reading.',
                'loyalty-for-woocommerce'
            ),
            $first_name,
            $deducted_points,
            $new_points,
            esc_url($points_url)
        );
        
        $mailer = WC()->mailer();

        $wrapped_message = $mailer->wrap_message($heading, $message);

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $mailer->send($to_email, $subject, $wrapped_message, $headers);
    }

    public function send_level_update_email($user_id, $new_level, $new_earning_points) {
        $user_info = get_userdata($user_id);
        if (!$user_info) {
            return;
        }
        $to_email = $user_info->user_email;
        $first_name = $user_info->first_name;

        // Get the role name from the slug
        $roles = get_option('wp_user_roles');
        $level_name = isset($roles[$new_level]['name']) ? $roles[$new_level]['name'] : ucfirst($new_level);

        // Get the points page slug from 'loyalty_customization_my_account' option
        $loyalty_options = get_option('loyalty_customization_my_account');
        $points_slug = '';
        if (!empty($loyalty_options)) {
            $loyalty_options = maybe_unserialize($loyalty_options); // Decode serialized data
            $points_slug = isset($loyalty_options['my_account_slug']) ? $loyalty_options['my_account_slug'] : '';
        }

        // Build the full URL for the points page
        $account_url = wc_get_page_permalink('myaccount');
        $points_url = trailingslashit($account_url) . $points_slug;

        $subject = __('Your loyalty level has been updated', 'loyalty-for-woocommerce');
        $heading = __('Loyalty level update', 'loyalty-for-woocommerce');
        $account_url = wc_get_page_permalink('myaccount');
        $message = sprintf(
            /* translators: 1: Customer first name, 2: New loyalty level, 3: Total earned points, 4: Account URL */
            __(
                'Hi %1$s,<br><br>Your loyalty level has been updated to <strong>%2$s</strong>.<br><br>You have now earned a total of <strong>%3$d points</strong>.<br><br><a href="%4$s">Click here to see your loyalty level details.</a><br><br>Keep up the great work and enjoy your rewards!',
                'loyalty-for-woocommerce'
            ),
            $first_name,
            $level_name,
            $new_earning_points,
            esc_url($points_url)
        );
        
        $mailer = WC()->mailer();

        $wrapped_message = $mailer->wrap_message($heading, $message);

        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $mailer->send($to_email, $subject, $wrapped_message, $headers);
    }
}

new YOSWC_Loyalty_Notifications_Email();

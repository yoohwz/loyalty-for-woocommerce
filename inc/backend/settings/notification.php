<?php

defined('ABSPATH') || exit;
class YOSWC_Loyalty_Settings_Notification {
    public function display_notification_settings() {
        echo '<h2>' . esc_html__('Email notifications', 'loyalty-for-woocommerce') . '</h2>';
        echo '<p>' . esc_html__('Manage loyalty email enablement and customization in WooCommerce email settings.', 'loyalty-for-woocommerce') . '</p>';
        foreach (array('Points earned' => 'points_reward', 'Points deducted' => 'points_deduct', 'Level update' => 'level_update') as $label => $family) {
            echo '<p><a class="button" href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=email&section=yowcl_wc_email_loyalty_' . $family)) . '">' . esc_html($label) . '</a></p>';
        }
    }
    // Kept as a public facade; retained legacy preferences are read-only evidence.
    public function save_notification_settings() {
        if (!current_user_can('manage_options') || !isset($_POST['loyalty_notification_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['loyalty_notification_nonce'])), 'save_loyalty_notification_settings')) { return; }
    }
}

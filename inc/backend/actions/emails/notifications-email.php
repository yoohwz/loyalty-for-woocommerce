<?php
/** Woo-native delivery; legacy hooks remain observations, never a second sender. */
defined('ABSPATH') || exit;
class YOSWC_Loyalty_Notifications_Email {
    public function __construct() {
        add_filter('woocommerce_email_classes', array($this, 'register'));
        foreach (array('points_reward','points_deduct','level_update') as $family) {
            $id='yowcl_loyalty_'.$family;
            add_filter('pre_option_woocommerce_'.$id.'_settings', array($this,'committed_settings'), PHP_INT_MAX, 2);
            add_filter('woocommerce_email_enabled_'.$id, array($this,'refresh_enabled'), PHP_INT_MIN, 3);
            add_filter('woocommerce_email_enabled_'.$id, array($this,'guard_enabled'), PHP_INT_MAX, 3);
        }
        foreach (array('points_reward' => 4, 'points_deduct' => 4, 'level_update' => 3) as $event => $argc) {
            foreach (array('yowcl_', 'woocommerce_') as $prefix) { add_action($prefix . 'loyalty_' . $event, array($this, 'send_' . $event . '_email'), 5, $argc); }
        }
        if (did_action('woocommerce_email') && WC()->mailer()) { WC()->mailer()->emails = $this->register(WC()->mailer()->emails); }
    }
    /** Preserve native option shape while bypassing pre-transaction cache images. */
    public function committed_settings($pre, $option) {
        try { $raw=YOWCL_Free_Migrations::read($option); return null===$raw ? array() : maybe_unserialize($raw); }
        catch (Throwable $e) { return array(); }
    }
    private function enabled_policy($email) {
        $feature='yowcl_loyalty_level_update'===$email->id ? 'email_level' : ('yowcl_loyalty_points_deduct'===$email->id ? 'email_deduct' : 'email_reward');
        return 'yes'===(YOWCL_Free_Migrations::canonical($feature)['enabled']??'no');
    }
    public function refresh_enabled($enabled, $object = null, $email = null) {
        $email=$email instanceof WC_Email ? $email : $object;
        if (!$email instanceof WC_Email) { return false; }
        // Warm WC_Email instances may predate the feature resolution.
        $current=$this->enabled_policy($email);$email->enabled=$current ? 'yes' : 'no';return $current;
    }
    public function guard_enabled($enabled, $object = null, $email = null) {
        $email=$email instanceof WC_Email ? $email : $object;
        if (!$email instanceof WC_Email) { return false; }
        return $enabled && $this->enabled_policy($email);
    }
    public function register($emails) {
        require_once __DIR__ . '/class-yowcl-wc-email-loyalty-base.php';
        foreach (array('Points_Reward', 'Points_Deduct', 'Level_Update') as $family) {
            require_once __DIR__ . '/class-yowcl-wc-email-loyalty-' . str_replace('_', '-', strtolower($family)) . '.php';
            $class = 'YOWCL_WC_Email_Loyalty_' . $family;
            if (!isset($emails[$class])) { $emails[$class] = new $class(); }
        }
        return $emails;
    }
    private function trigger($family, $args) {
        $emails = WC()->mailer()->get_emails();
        $class = 'YOWCL_WC_Email_Loyalty_' . $family;
        if (isset($emails[$class])) { call_user_func_array(array($emails[$class], 'trigger'), $args); }
    }
    public function send_points_reward_email($user_id, $points, $balance, $order_id = null) { $this->trigger('Points_Reward', func_get_args()); }
    public function send_points_deduct_email($user_id, $points, $balance, $order_id = null) { $this->trigger('Points_Deduct', func_get_args()); }
    public function send_level_update_email($user_id, $level, $earning) { $this->trigger('Level_Update', func_get_args()); }
}
new YOSWC_Loyalty_Notifications_Email();

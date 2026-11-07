<?php
/** Standalone presentation regression: php tests/email-settings.php */
define('ABSPATH', __DIR__ . '/');
set_error_handler(static function($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
// Priority-ordered hook doubles keep this test independent of an installed WordPress site.
function add_filter($name, $callback, $priority = 10, $args = 1) { $GLOBALS['hooks'][$name][$priority][] = array($callback, $args); }
function add_action(...$args) { add_filter(...$args); }
function apply_filters($name, $value, ...$args) {
    $hooks = $GLOBALS['hooks'][$name] ?? array(); ksort($hooks);
    foreach ($hooks as $callbacks) { foreach ($callbacks as $entry) { $value = call_user_func_array($entry[0], array_slice(array_merge(array($value), $args), 0, $entry[1])); } }
    return $value;
}
function did_action($name) { return 0; }
function __($text, $domain = '') { return $text; }
function esc_html($text) { return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8'); }
function esc_attr($text) { return esc_html($text); }
function esc_html__($text, $domain = '') { return esc_html($text); }
function esc_attr__($text, $domain = '') { return esc_attr($text); }
function esc_url($url) { return esc_attr($url); }
function admin_url($path) { return '/wp-admin/' . $path; }
function wp_unslash($value) { return stripslashes($value); }
function sanitize_key($value) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower($value)); }
function wc_help_tip($text) { return ''; }
function WC() { return new class { public function mailer() { return new class { public function get_emails() { return $GLOBALS['emails']; } }; } }; }
class YOWCL_Free_Migrations {
    public static function ready($feature) { return empty($GLOBALS['held']); }
    public static function readable($feature) { return empty($GLOBALS['held']); }
    public static function held_settings_message($feature) { return 'Settings preserved'; }
}
class YOWCL_Free_Reader_Admin { public static function premium_card($context) { echo 'Premium card'; } }
class Test_Email {
    public $id;
    public $email_group;
    public function __construct($id, $group) { $this->id = $id; $this->email_group = $group; }
    public function is_enabled() { return true; }
    public function is_manual() { return false; }
    public function is_customer_email() { return false; }
    public function get_title() { return $this->id; }
    public function get_description() { return ''; }
    public function get_content_type() { return 'text/html'; }
    public function get_recipient() { return 'admin@example.test'; }
}
function check($condition, $message) { if (!$condition) { throw new RuntimeException($message); } }
require dirname(__DIR__) . '/inc/backend/actions/emails/notifications-email.php';
$notifications = (new ReflectionClass('YOSWC_Loyalty_Notifications_Email'))->newInstanceWithoutConstructor();
$base = array(array('type'=>'title','id'=>'email_options'), array('type'=>'email_notification','id'=>'notifications'), array('type'=>'text','id'=>'woocommerce_email_from_name'), array('type'=>'sectionend','id'=>'email_options'));
$GLOBALS['emails'] = array('WC_Email_New_Order'=>new Test_Email('new_order',''), 'YOWCL_WC_Email_Loyalty_Points_Reward'=>new Test_Email('yowcl_loyalty_points_reward','loyalty'), 'YOWCL_WC_Email_Loyalty_Points_Deduct'=>new Test_Email('yowcl_loyalty_points_deduct','loyalty'), 'YOWCL_WC_Email_Loyalty_Level_Update'=>new Test_Email('yowcl_loyalty_level_update','loyalty'));
foreach (array(false, true) as $cit) {
    if ($cit) {
        // CIT's shared presentation contract: CRM group plus field replacement at priority 20.
        add_filter('woocommerce_email_groups', static function($groups) { $groups['crm'] = 'CRM'; return $groups; });
        add_filter('woocommerce_email_settings', static function($settings) {
            foreach ($settings as &$field) {
                if (in_array($field['type'] ?? '', array('email_notification','email_notification_block_emails','yowcl_grouped_email_notification'), true)) { $field['type'] = 'yoohw_cos_grouped_email_notification'; }
            }
            unset($field);
            return $settings;
        }, 20);
        $GLOBALS['emails']['YoOhw_COS_Email_Task_Assigned'] = new Test_Email('crm_task_assigned','crm');
    }
    foreach (array('general','loyalty','crm','unknown',array('bad')) as $group) {
        $_GET['email_group'] = $group;
        foreach (array('email_notification','email_notification_block_emails') as $type) {
            $settings = $base;
            $settings[1]['type'] = $type;
            $settings = apply_filters('woocommerce_email_settings', $settings);
            check(count($settings) === count($base) && $settings[2] === $base[2] && $settings[1]['id'] === 'notifications', 'Other Woo settings were changed');
            check($settings[1]['type'] === ($type === 'email_notification_block_emails' && 'loyalty' !== $group && !($cit && 'crm' === $group) ? 'loyf_general_block_emails' : 'loyf_loyalty_emails'), 'Email renderer lost in filter chain');
        }
        unset($GLOBALS['hide_save_button']);
        ob_start(); $notifications->loyalty_email_table(); $html = ob_get_clean();
        check(empty($GLOBALS['hide_save_button']), 'Shared editable Woo settings must keep their Save control');
        $expected = 'loyalty' === $group ? 'yowcl_loyalty_points_reward' : ($cit && 'crm' === $group ? 'crm_task_assigned' : 'new_order');
        $expected_key = array_keys(array_filter($GLOBALS['emails'], static function($email) use ($expected) { return $email->id === $expected; }))[0];
        check(strpos($html, 'section=' . strtolower($expected_key) . '&amp;email_group=' . ('loyalty' === $group ? 'loyalty' : ($cit && 'crm' === $group ? 'crm' : 'general'))) !== false, 'Manage link missing or wrong group');
        foreach (array('new_order','yowcl_loyalty_points_reward','crm_task_assigned') as $id) { check((strpos($html, '>' . ($id === 'yowcl_loyalty_points_reward' ? 'Points earned' : $id) . '</strong>') !== false) === ($expected === $id), 'Email leaked between groups: ' . $id); }
        check(strpos($html, 'Loyalty <span class="count">(3)') !== false, 'Loyalty count missing');
        check((strpos($html, 'CRM <span class="count">(1)') !== false) === $cit, 'CRM navigation missing or spurious');
        check((strpos($html, 'Premium card') !== false) === ('loyalty' === $group), 'Premium card in wrong group');
    }
    echo 'PASS: ' . ($cit ? 'LOYF + CIT' : 'LOYF alone') . " groups, counts, Manage links, preserved Woo settings and malformed group fallback\n";
}
$_GET['email_group'] = 'loyalty';
$GLOBALS['held'] = true;
ob_start(); $notifications->loyalty_email_table(); $html = ob_get_clean();
check(substr_count($html, 'data-loyf-status="Settings preserved"') === 3, 'Migration hold status lost');
echo "PASS: migration hold presentation preserved\n";

check(empty($GLOBALS['hooks']['woocommerce_email_settings_before']), 'Redundant Loyalty backlink is still registered');

$_GET['email_group'] = 'loyalty';
ob_start(); $notifications->loyalty_email_table(array('group'=>'general')); $html = ob_get_clean();
check(strpos($html, '>new_order</strong>') !== false && strpos($html, '>Points earned</strong>') === false, 'Explicit renderer group contract lost');
check(strpos($html, 'email_group=general" class="current') !== false, 'Explicit renderer navigation group mismatch');

// Tag-processor and native settings-page doubles verify the payload contract, not WP's parser.
class WP_HTML_Tag_Processor {
    private $html;
    private $visited = false;
    public function __construct($html) { $this->html = $html; }
    public function next_tag() { if ($this->visited) { return false; } $this->visited = true; return true; }
    public function get_attribute($name) { preg_match('/' . preg_quote($name, '/') . '="([^"]*)"/', $this->html, $match); return isset($match[1]) ? html_entity_decode($match[1], ENT_QUOTES, 'UTF-8') : null; }
    public function set_attribute($name, $value) { $this->html = preg_replace('/' . preg_quote($name, '/') . '="[^"]*"/', $name . '="' . esc_attr($value) . '"', $this->html); }
    public function get_updated_html() { return $this->html; }
}
function wp_json_encode($value) { return json_encode($value); }
class WC_Admin_Settings {
    public static function get_settings_pages() { return array(new class {
        public function get_id() { return 'email'; }
        public function email_notification_setting_block_emails() {
            $types = array_map(static function($email) { return array('id'=>$email->id); }, array_values($GLOBALS['emails']));
            echo '<div id="wc_settings_email_listing_slotfill" data-email-types="' . esc_attr(json_encode($types)) . '" data-edit-template-url="post.php?post=42">Edit template</div>';
        }
    }); }
}
$_GET['email_group'] = 'general';
ob_start(); $notifications->general_block_emails(); $html = ob_get_clean();
check(strpos($html, 'data-edit-template-url="post.php?post=42"') !== false, 'Native template editor entry lost');
preg_match('/data-email-types="([^"]*)"/', $html, $match);
$types = json_decode(html_entity_decode($match[1], ENT_QUOTES, 'UTF-8'), true);
check(array_column($types, 'id') === array('new_order'), 'General block listing must exclude both CRM and Loyalty groups');
echo "PASS: native General template entry and group isolation with CIT active\n";

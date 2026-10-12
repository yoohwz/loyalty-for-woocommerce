<?php
/** Woo-native delivery; legacy hooks remain observations, never a second sender. */
defined('ABSPATH') || exit;
class YOSWC_Loyalty_Notifications_Email {
    public function __construct() {
        add_filter('woocommerce_email_classes', array($this, 'register'));
        add_action('woocommerce_email_settings_before', array($this, 'email_group_backlink'));
        add_filter('woocommerce_email_settings', array($this, 'grouped_settings'));
        add_action('woocommerce_admin_field_loyf_email_groups', array($this, 'group_navigation'));
        add_action('woocommerce_admin_field_loyf_loyalty_emails', array($this, 'loyalty_email_table'));
        add_action('woocommerce_admin_field_loyf_general_block_emails', array($this, 'general_block_emails'));
        foreach (array('points_reward','points_deduct','level_update') as $family) {
            $id='yowcl_loyalty_'.$family;
            $feature='level_update'===$family ? 'email_level' : ('points_deduct'===$family ? 'email_deduct' : 'email_reward');
            add_action('woocommerce_update_options_email_'.$id, static function() use($feature) {
                if (!YOWCL_Free_Migrations::ready($feature) || !YOWCL_Free_Migrations::readable($feature)) { WC_Admin_Settings::add_error(YOWCL_Free_Migrations::held_settings_message($feature)); }
            }, 0);
            add_filter('woocommerce_settings_api_form_fields_'.$id, function($fields) use($feature,$id,$family) {
                if (YOWCL_Free_Migrations::ready($feature) && YOWCL_Free_Migrations::readable($feature)) { return $fields; }
                $section=$GLOBALS['current_section']??($_GET['section']??'');
                if (in_array($section,array($id,'yowcl_wc_email_loyalty_'.$family),true)) { $GLOBALS['hide_save_button']=true; }
                return array('loyf_preserved_settings'=>array('type'=>'title','default'=>'','title'=>__('Settings preserved','loyalty-for-woocommerce'),'description'=>YOWCL_Free_Migrations::held_settings_message($feature).' '.YOWCL_Free_Migrations::held_link($feature)));
            });
            add_filter('pre_update_option_woocommerce_'.$id.'_settings', function($value,$old) use($feature) {
                if (YOWCL_Free_Migrations::readable($feature)) { return $value; }
                if (class_exists('WC_Admin_Settings')) { WC_Admin_Settings::add_error(YOWCL_Free_Migrations::held_settings_message($feature)); }
                return $old; // WordPress declines the unchanged value before any row/autoload write.
            }, PHP_INT_MAX, 2);
            add_filter('pre_option_woocommerce_' . $id.'_settings', function($pre,$option) use($feature) { return YOWCL_Free_Migrations::readable($feature) ? $this->committed_settings($pre,$option) : array(); }, PHP_INT_MAX, 2);
            add_filter('woocommerce_email_enabled_'.$id, array($this,'refresh_enabled'), PHP_INT_MIN, 3);
            add_filter('woocommerce_email_enabled_'.$id, array($this,'guard_enabled'), PHP_INT_MAX, 3);
        }
        foreach (array('points_reward' => 4, 'points_deduct' => 4, 'level_update' => 3) as $event => $argc) {
            foreach (array('yowcl_', 'woocommerce_') as $prefix) { add_action($prefix . 'loyalty_' . $event, array($this, 'send_' . $event . '_email'), 5, $argc); }
        }
        if (did_action('woocommerce_email') && WC()->mailer()) { WC()->mailer()->emails = $this->register(WC()->mailer()->emails); }
    }
    /** Group only the settings presentation; the mailer registry and native editors stay intact. */
    public function grouped_settings($settings) {
        $loyalty = isset($_GET['email_group']) && is_string($_GET['email_group']) && 'loyalty' === wp_unslash($_GET['email_group']);
        if ($loyalty) {
            return array(
                array('type'=>'title','title'=>__('Loyalty emails','loyalty-for-woocommerce'),'desc'=>__('Manage the three Free loyalty email notifications.','loyalty-for-woocommerce')),
                array('type'=>'loyf_email_groups'),
                array('type'=>'loyf_loyalty_emails','group'=>'loyalty'),
                array('type'=>'sectionend'),
            );
        }
        $result = array();
        foreach ($settings as $field) {
            if (in_array($field['type'] ?? '', array('email_notification','email_notification_block_emails'), true)) {
                $result[] = array('type'=>'loyf_email_groups');
                $field = array('type'=>'email_notification_block_emails' === $field['type'] ? 'loyf_general_block_emails' : 'loyf_loyalty_emails','group'=>'general');
            }
            $result[] = $field;
        }
        return $result;
    }
    /** Retain Woo's block listing/template editor, filtering only its presentation data. */
    public function general_block_emails() {
        foreach (WC_Admin_Settings::get_settings_pages() as $page) {
            if ('email' !== $page->get_id()) { continue; }
            ob_start();
            $page->email_notification_setting_block_emails();
            $html = new WP_HTML_Tag_Processor(ob_get_clean());
            while ($html->next_tag()) {
                if ('wc_settings_email_listing_slotfill' !== $html->get_attribute('id')) { continue; }
                $types = json_decode($html->get_attribute('data-email-types'),true);
                if (is_array($types)) {
                    $types = array_values(array_filter($types, static function($email) { return !in_array($email['id'] ?? '', array('yowcl_loyalty_points_reward','yowcl_loyalty_points_deduct','yowcl_loyalty_level_update'),true); }));
                    $html->set_attribute('data-email-types',wp_json_encode($types));
                }
                break;
            }
            echo $html->get_updated_html(); // Native Woo markup; the tag processor escapes the changed attribute.
            return;
        }
    }
    public function group_navigation() {
        $loyalty = isset($_GET['email_group']) && is_string($_GET['email_group']) && 'loyalty' === wp_unslash($_GET['email_group']);
        echo '<tr><td colspan="2"><nav aria-label="' . esc_attr__('Email groups','loyalty-for-woocommerce') . '"><ul class="subsubsub">';
        foreach (array(''=>__('General','loyalty-for-woocommerce'),'loyalty'=>__('Loyalty','loyalty-for-woocommerce')) as $group=>$label) {
            $current = $loyalty === ('loyalty' === $group);
            echo '<li><a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=email' . ($group ? '&email_group=loyalty' : ''))) . '"' . ($current ? ' class="current" aria-current="page"' : '') . '>' . esc_html($label) . '</a>' . ($group ? '' : ' | ') . '</li>';
        }
        echo '</ul></nav><br class="clear" /></td></tr>';
    }
    public function email_group_backlink($email) {
        if (in_array($email->id, array('yowcl_loyalty_points_reward','yowcl_loyalty_points_deduct','yowcl_loyalty_level_update'), true)) {
            echo '<p><a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=email&email_group=loyalty')) . '">' . esc_html__('Back to Loyalty emails','loyalty-for-woocommerce') . '</a></p>';
        }
    }
    public function loyalty_email_table($value = array()) {
        $loyalty = 'loyalty' === ($value['group'] ?? 'loyalty');
        if ($loyalty) { $GLOBALS['hide_save_button'] = true; }
        $emails = WC()->mailer()->get_emails();
        $labels = array('YOWCL_WC_Email_Loyalty_Points_Reward'=>__('Points earned','loyalty-for-woocommerce'),'YOWCL_WC_Email_Loyalty_Points_Deduct'=>__('Points deducted','loyalty-for-woocommerce'),'YOWCL_WC_Email_Loyalty_Level_Update'=>__('Level update','loyalty-for-woocommerce'));
        $columns = apply_filters('woocommerce_email_setting_columns', array('status'=>'','name'=>__('Email','woocommerce'),'email_type'=>__('Content type','woocommerce'),'recipient'=>__('Recipient(s)','woocommerce'),'actions'=>''));
        $rows = $loyalty ? array_intersect_key($emails,$labels) : array_diff_key($emails,$labels);
        echo '<tr><td class="wc_emails_wrapper" colspan="2"><div class="loyf-email-list"><table class="widefat wc_emails ' . ($loyalty ? 'loyf-loyalty-emails' : 'loyf-general-emails') . '"><thead><tr>';
        foreach ($columns as $key=>$heading) { echo '<th scope="col" class="wc-email-settings-table-' . esc_attr($key) . '">' . esc_html($heading) . '</th>'; }
        echo '</tr></thead><tbody>';
        foreach ($rows as $key=>$email) {
            $feature = 'yowcl_loyalty_level_update' === $email->id ? 'email_level' : ('yowcl_loyalty_points_deduct' === $email->id ? 'email_deduct' : 'email_reward');
            $held = $loyalty && (!YOWCL_Free_Migrations::ready($feature) || !YOWCL_Free_Migrations::readable($feature));
            $enabled = !$held && $email->is_enabled();
            $status = $held ? __('Settings preserved','loyalty-for-woocommerce') : ($email->is_manual() ? __('Manually sent','woocommerce') : ($enabled ? __('Enabled','woocommerce') : __('Disabled','woocommerce')));
            $label = $labels[$key] ?? $email->get_title();
            $url = admin_url('admin.php?page=wc-settings&tab=email&section=' . strtolower($key) . '&email_group=' . ($loyalty ? 'loyalty' : 'general'));
            echo '<tr' . ($loyalty ? ' data-loyf-email="' . esc_attr(strtolower(substr($key,strlen('YOWCL_WC_Email_Loyalty_')))) . '"' : '') . ' data-loyf-status="' . esc_attr($status) . '">';
            foreach ($columns as $column=>$heading) {
                if (!in_array($column,array('status','name','email_type','recipient','actions'),true)) { do_action('woocommerce_email_setting_column_' . $column,$email); continue; }
                echo '<td class="wc-email-settings-table-' . esc_attr($column) . '" data-title="' . esc_attr('status' === $column ? __('Status','loyalty-for-woocommerce') : $heading) . '">';
                switch ($column) {
                    case 'status':
                        $class = $held ? 'dashicons dashicons-warning' : ($email->is_manual() ? 'status-manual' : ($enabled ? 'status-enabled' : 'status-disabled'));
                        echo '<span class="' . esc_attr($class) . ' tips" data-tip="' . esc_attr($status) . '" aria-label="' . esc_attr($status) . '">' . ($held ? '' : esc_html($status)) . '</span>';
                        break;
                    case 'name':
                        echo '<a href="' . esc_url($url) . '"><strong>' . esc_html($label) . '</strong></a> ' . wc_help_tip($email->get_description());
                        if ($held) { echo '<p class="description">' . esc_html(YOWCL_Free_Migrations::held_settings_message($feature)) . '</p>'; }
                        break;
                    case 'email_type': echo esc_html($email->get_content_type()); break;
                    case 'recipient':
                        echo esc_html($email->is_customer_email() ? __('Customer','woocommerce') : $email->get_recipient());
                        if (class_exists('Automattic\\WooCommerce\\Utilities\\FeaturesUtil') && \Automattic\WooCommerce\Utilities\FeaturesUtil::feature_is_enabled('email_improvements')) {
                            foreach (array('get_cc_recipient','get_bcc_recipient') as $method) { if (method_exists($email,$method) && ($recipient=$email->$method())) { echo '<br>' . esc_html($recipient); } }
                        }
                        break;
                    case 'actions':
                        /* translators: Name of the email notification. */
                        echo '<a class="button alignright" aria-label="' . esc_attr(sprintf(__('Manage %s','loyalty-for-woocommerce'),$label)) . '" href="' . esc_url($url) . '">' . esc_html__('Manage','woocommerce') . '</a>';
                        break;
                }
                echo '</td>';
            }
            echo '</tr>';
        }
        echo '</tbody></table></div></td></tr>';
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

<?php
if (!defined('LOYF_RUNTIME_DISPOSABLE')) { throw new RuntimeException('Disposable required'); }
global $wpdb;
// Save immutable pre-upgrade accounting and customization after actual old-Free execution.
$account = maybe_unserialize(get_option('loyalty_extra_points_rules'));
$account['signup_enabled'] = 'no'; $account['login_enabled'] = 'no';
$account['birthday_points'] = '29'; $account['unknown'] = array('exact' => '07');
$account['unknown_object'] = (object) array('exact' => '007');
update_option('loyalty_extra_points_rules', $account);
update_option('loyalty_extra_reviews_gamification_rules', array('review_enabled' => 'no', 'review_points' => '99', 'levelup_enabled' => 'no', 'levelup_points' => array('loyf_gold' => array('awarded' => '99')), 'context_rules' => array('keep' => '001'), 'unknown_object' => (object) array('exact' => '009')));
update_option('woocommerce_yowcl_loyalty_points_reward_settings', array('enabled' => 'no', 'subject' => 'Custom {earned_points}', 'heading' => 'Fixture heading', 'email_type' => 'plain', 'unknown' => '001', 'unknown_object' => (object) array('exact' => '011')));
update_option('woocommerce_yowcl_loyalty_points_deduct_settings', array('enabled' => 'no', 'subject' => 'Deduct fixture'));
update_option('woocommerce_yowcl_loyalty_level_update_settings', array('enabled' => 'no', 'heading' => 'Level fixture'));
$using=maybe_unserialize(get_option('loyalty_points_using_rules'));$using['amount']='0.7010';$using['unknown']=array('exact'=>'003');update_option('loyalty_points_using_rules',serialize($using));
$state = array(
 'meta' => $wpdb->get_results("SELECT * FROM {$wpdb->usermeta} ORDER BY umeta_id", ARRAY_A),
 'rows' => $wpdb->get_results("SELECT * FROM {$wpdb->prefix}yo_loyalty_points_log ORDER BY id", ARRAY_A),
 'orders' => $wpdb->get_results("SELECT * FROM {$wpdb->postmeta} WHERE meta_key IN ('_used_points','_loyalty_points_processed','_yo_loyalty_earned_points','_yo_loyalty_returned_points') ORDER BY meta_id", ARRAY_A),
 'options' => array(), 'roles' => get_option($wpdb->prefix . 'user_roles'),
);
foreach (array('loyalty_extra_points_rules','loyalty_extra_reviews_gamification_rules','loyalty_extra_levelup_points_rules','loyalty_notification_email','loyalty_customization_my_account','loyalty_customization_membercard','loyalty_levels_roles','loyalty_levels_rules') as $name) { $state['options'][$name] = get_option($name); }
add_option('loyf_upgrade_before', $state, '', false);
echo "Actual old-Free upgrade fixture captured\n";

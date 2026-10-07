<?php
require __DIR__ . '/assertions.php';
global $wpdb;
$before = get_option('loyf_upgrade_before');
loyf_assert(is_array($before), 'Actual old-Free fixture');
loyf_equal($before['meta'], $wpdb->get_results("SELECT * FROM {$wpdb->usermeta} ORDER BY umeta_id", ARRAY_A), 'All user meta byte preservation on boot');
$rows = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}yo_loyalty_points_log ORDER BY id", ARRAY_A);
loyf_equal(count($before['rows']), count($rows), 'No synthetic history');
foreach ($before['rows'] as $i => $old) {
    foreach ($old as $key => $value) { loyf_equal($value, $rows[$i][$key], 'Historical field ' . $key); }
    foreach (array('event_key','available_delta','earning_delta','ledger_version','source_event_key','allocation_receipt') as $key) { loyf_equal(null, $rows[$i][$key], 'Legacy nullable field'); }
}
loyf_equal($before['roles'], get_option($wpdb->prefix . 'user_roles'), 'No physical ownership adoption');
loyf_equal($before['orders'], $wpdb->get_results("SELECT * FROM {$wpdb->postmeta} WHERE meta_key IN ('_used_points','_loyalty_points_processed','_yo_loyalty_earned_points','_yo_loyalty_returned_points') ORDER BY meta_id", ARRAY_A), 'Order evidence retained');
foreach (array('loyalty_notification_email','loyalty_extra_levelup_points_rules','loyalty_customization_my_account','loyalty_customization_membercard','loyalty_levels_roles','loyalty_levels_rules') as $name) { loyf_equal($before['options'][$name], get_option($name), 'Retained legacy/customization ' . $name); }
foreach (array('signup','login','review','levelup','redemption','email_reward','email_deduct','email_level') as $feature) { loyf_assert(YOWCL_Free_Migrations::ready($feature), 'Witness ' . $feature); }
loyf_equal(5, YOWCL_Free_Core::extra('signup'), 'Legacy positive wins canonical no');
loyf_equal(3, YOWCL_Free_Core::extra('login'), 'Legacy login');
loyf_equal(7, YOWCL_Free_Core::extra('review'), 'Legacy review wins 99');
$merged = get_option('loyalty_extra_reviews_gamification_rules');
loyf_equal($before['options']['loyalty_extra_levelup_points_rules'], $merged['levelup_points'], 'Exact role map');
loyf_equal(array('keep'=>'001'), $merged['context_rules'], 'Dormant merged keys');
loyf_equal('29', get_option('loyalty_extra_points_rules')['birthday_points'], 'Dormant account');
loyf_equal('3', get_option('wc_loyalty_db_version'), 'Existing canonical schema certification');
$old_signup = get_option('loyf_migration_signup_v1_before');
loyf_equal('no', maybe_unserialize($old_signup['before'])['signup_enabled'], 'Pre-target rollback evidence');
// Existing users/reviews/level ambiguity never becomes an automatic re-award.
foreach ($before['meta'] as $meta) { if ('user_points' === $meta['meta_key']) { do_action('user_register', (int)$meta['user_id']); YOWCL_Free_Core::level_bonus((int)$meta['user_id'], 'loyf_gold'); } }
loyf_equal(array_values(array_filter($before['meta'], function($row) { return 'dismissed_wp_pointers' !== $row['meta_key']; })), $wpdb->get_results("SELECT * FROM {$wpdb->usermeta} WHERE meta_key <> 'dismissed_wp_pointers' ORDER BY umeta_id", ARRAY_A), 'Historical economic/role metadata preserved (Woo user_register may append dismissed pointers)');
loyf_equal(count($rows), (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yo_loyalty_points_log"), 'No historical reward event');
// Read-back failure after target persistence cannot make a witness. Retry uses frozen source.
function loyf_reset_feature($feature) { delete_option(YOWCL_Free_Migrations::witness($feature)); delete_option(YOWCL_Free_Migrations::witness($feature) . '_before'); }
loyf_reset_feature('review');
$source = get_option('loyalty_extra_points_rules'); $source['review_points'] = '0'; update_option('loyalty_extra_points_rules', $source);
$merged['review_enabled']='yes'; $merged['review_points']='99'; update_option('loyalty_extra_reviews_gamification_rules', $merged);
$fail = function($sql) use ($wpdb) { return false !== strpos($sql, "INSERT INTO {$wpdb->options}") && false !== strpos($sql, "'loyf_migration_review_v1',") ? 'SELECT * FROM loyf_missing_failure_table' : $sql; };
$wpdb->suppress_errors(true); add_filter('query', $fail); YOWCL_Free_Migrations::run(); remove_filter('query', $fail); $wpdb->suppress_errors(false);
loyf_assert(!YOWCL_Free_Migrations::ready('review'), 'Failed witness held'); loyf_equal(0, YOWCL_Free_Core::extra('review'), 'Affected producer held');
$source['review_points']='88'; update_option('loyalty_extra_points_rules', $source);
YOWCL_Free_Migrations::run(); loyf_equal(0, YOWCL_Free_Core::extra('review'), 'Retry frozen zero disables conflicting canonical target');
YOWCL_Free_Migrations::save('review', 'loyalty_extra_reviews_gamification_rules', array('review_enabled'=>'yes','review_points'=>'13'));
YOWCL_Free_Migrations::run(); loyf_equal(13,YOWCL_Free_Core::extra('review'),'Witness rerun preserves merchant terms');
// Both email directions; canonical customization and legacy preference retained.
foreach (array('points_reward','points_deduct','level_update') as $family) { loyf_equal('yes', get_option('woocommerce_yowcl_loyalty_' . $family . '_settings')['enabled'], 'Legacy mail enabled'); }
loyf_reset_feature('email_reward'); $legacy = get_option('loyalty_notification_email'); $original_legacy=$legacy; $legacy['points_update']=false; update_option('loyalty_notification_email',$legacy);
YOWCL_Free_Migrations::run(); $mail=get_option('woocommerce_yowcl_loyalty_points_reward_settings'); loyf_equal('no',$mail['enabled'],'Legacy false overrides native yes'); loyf_equal('Custom {earned_points}',$mail['subject'],'Custom subject survives'); loyf_equal('001',$mail['unknown'],'Unknown native field');
YOWCL_Free_Migrations::save('email_reward','woocommerce_yowcl_loyalty_points_reward_settings',array('enabled'=>'yes'));
YOWCL_Free_Migrations::run(); loyf_equal('yes',get_option('woocommerce_yowcl_loyalty_points_reward_settings')['enabled'],'Merchant mail enablement authoritative');
update_option('loyalty_notification_email',$original_legacy);
// One native transport for paired hooks, retaining public old observations.
$mailer=WC()->mailer(); $emails=$mailer->get_emails();
$families=array_filter(array_keys($emails),function($class){return 0===strpos($class,'YOWCL_WC_Email_Loyalty_');}); loyf_equal(3,count($families),'Only Free email families');
$emails['YOWCL_WC_Email_Loyalty_Points_Reward']->init_settings(); $emails['YOWCL_WC_Email_Loyalty_Points_Reward']->enabled='yes';
$user=(int)$before['rows'][0]['user_id']; $GLOBALS['loyf_mail']=array(); $observations=0;
add_action('yoswc_loyalty_points_reward',function()use(&$observations){++$observations;},10,4);
do_action('yowcl_loyalty_points_reward',$user,25,125,null); do_action('woocommerce_loyalty_points_reward',$user,25,125,null); do_action('yoswc_loyalty_points_reward',$user,25,125,null);
loyf_equal(1,count($GLOBALS['loyf_mail']),'Single native delivery across paired hooks'); loyf_equal(1,$observations,'Public legacy observation');
loyf_equal('Custom 25',$GLOBALS['loyf_mail'][0]['subject'],'Woo custom placeholders');
// Old in-progress cart selection clears explicitly, never debit; canonical selection is retained.
wp_set_current_user($user); WC()->initialize_session(); WC()->initialize_cart();
$points=get_user_meta($user,'user_points',true); WC()->session->set('yoswc_loyalty_applied_points',20); WC()->session->set('yoswc_loyalty_discount_amount',2);
YOWCL_Free_Migrations::session(); loyf_equal(null,WC()->session->get('yoswc_loyalty_applied_points'),'Legacy selection cleared'); loyf_equal($points,get_user_meta($user,'user_points',true),'Clear changes no value'); loyf_assert(wc_notice_count('notice')>0,'Explicit reapply feedback');
WC()->session->set('loyf_funded_selection',array('id'=>'fixture')); WC()->session->set('yoswc_loyalty_applied_points',20); YOWCL_Free_Migrations::session(); loyf_equal(20,WC()->session->get('yoswc_loyalty_applied_points'),'Canonical attempt retained');
echo "Actual old-Free → refreshed-Free upgrade PASS\n";

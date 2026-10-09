<?php
require __DIR__ . '/assertions.php';
global $wpdb;
wp_set_current_user(1);
$unexpected_die=function(){return function($message){throw new RuntimeException(is_wp_error($message)?$message->get_error_message():(string)$message);};};
add_filter('wp_die_handler',$unexpected_die);add_filter('wp_die_ajax_handler',$unexpected_die);
// Later synthetic resets model explicit merchant choices, not a new origin assertion.
function loyf_upgrade_migrate() {
    $actor=get_current_user_id(); wp_set_current_user(1);
    YOWCL_Free_Migrations::run();
    foreach(YOWCL_Free_Migrations::features() as $feature) {
        if(YOWCL_Free_Migrations::ready($feature)) { continue; }
        try {
            $spec=YOWCL_Free_Migrations::preview($feature,'legacy');
            YOWCL_Free_Migrations::resolve($feature,'legacy',hash('sha256',serialize($spec)),wp_create_nonce('loyf_resolve_'.$feature));
        } catch(Throwable $e) { /* Fault fixtures retain the native hold. */ }
    }
    wp_set_current_user($actor);
}
// Captured upgrade boot schedules only; the native AS runner completes the retained specs.
foreach(YOWCL_Free_Migrations::features() as $feature) { loyf_assert(!YOWCL_Free_Migrations::ready($feature),'No synchronous captured conversion at boot'); }
YOWCL_Free_Migrations::schedule();
$actions=as_get_scheduled_actions(array('hook'=>YOWCL_Free_Migrations::HOOK,'group'=>YOWCL_Free_Migrations::GROUP,'status'=>'pending','per_page'=>20),'ids');
loyf_equal(8,count($actions),'Eight bounded captured actions');
foreach($actions as $action){ActionScheduler_QueueRunner::instance()->process_action($action,'LOYF-27 captured runtime');}
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
loyf_equal(serialize($before['options']['loyalty_extra_points_rules']['unknown_object']),serialize(get_option('loyalty_extra_points_rules')['unknown_object']),'Unknown account object values preserved');
loyf_equal(serialize($before['options']['loyalty_extra_reviews_gamification_rules']['unknown_object']),serialize($merged['unknown_object']),'Unknown merged object values preserved');
loyf_upgrade_migrate(); loyf_assert(YOWCL_Free_Migrations::ready('signup') && YOWCL_Free_Migrations::ready('login'),'Valid object fields cannot cause perpetual holds');
loyf_equal('3', get_option('wc_loyalty_db_version'), 'Existing canonical schema certification');
$old_signup = get_option('loyf_migration_signup_v1_before');
loyf_equal('no', maybe_unserialize($old_signup['before'])['signup_enabled'], 'Pre-target rollback evidence');
// Existing users/reviews/level ambiguity never becomes an automatic re-award.
foreach ($before['meta'] as $meta) { if ('user_points' === $meta['meta_key']) { do_action('user_register', (int)$meta['user_id']); YOWCL_Free_Core::level_bonus((int)$meta['user_id'], 'loyf_gold'); } }
loyf_equal(array_values(array_filter($before['meta'], function($row) { return 'dismissed_wp_pointers' !== $row['meta_key']; })), $wpdb->get_results("SELECT * FROM {$wpdb->usermeta} WHERE meta_key <> 'dismissed_wp_pointers' ORDER BY umeta_id", ARRAY_A), 'Historical economic/role metadata preserved (Woo user_register may append dismissed pointers)');
loyf_equal(count($rows), (int)$wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}yo_loyalty_points_log"), 'No historical reward event');
// Read-back failure after target persistence cannot make a witness. Retry uses frozen source.
function loyf_reset_feature($feature) { delete_option(YOWCL_Free_Migrations::witness($feature)); delete_option(YOWCL_Free_Migrations::witness($feature) . '_before'); delete_option(YOWCL_Free_Migrations::witness($feature) . '_resolution'); }
loyf_reset_feature('review');
$old_target=get_option('loyalty_extra_reviews_gamification_rules');
$failure=function($sql) use($wpdb) { return false !== strpos($sql, 'SELECT option_value') && false !== strpos($sql, "'loyalty_extra_points_rules'") ? 'SELECT * FROM loyf_missing_source_table' : $sql; };
$wpdb->suppress_errors(true); add_filter('query',$failure); loyf_upgrade_migrate(); remove_filter('query',$failure); $wpdb->suppress_errors(false);
$failure_bubble=new YOSWC_Loyalty_Info_Bubble(); $failure_method=new ReflectionMethod($failure_bubble,'get_earning_section'); $failure_method->setAccessible(true); loyf_assert(false===strpos(json_encode($failure_method->invoke($failure_bubble,'Points')),'Product review:'),'Bubble does not advertise held review feature');
loyf_equal(serialize($old_target),serialize(get_option('loyalty_extra_reviews_gamification_rules')),'Failed source read cannot replace target'); loyf_assert(!YOWCL_Free_Migrations::ready('review'),'Failed read no witness');
update_option('loyalty_extra_reviews_gamification_rules','malformed'); loyf_upgrade_migrate(); loyf_assert(!YOWCL_Free_Migrations::ready('review'),'Malformed target held'); loyf_equal('malformed',get_option('loyalty_extra_reviews_gamification_rules'),'Malformed data not erased');
ob_start(); (new YOSWC_Loyalty_Settings_Extra_Points())->display_extra_points_settings(); YOWCL_Free_Migrations::notices(); $malformed_ui=ob_get_clean(); loyf_assert(false!==strpos($malformed_ui,'Extra points settings'),'Malformed target renderer does not fatal');
update_option('loyalty_extra_reviews_gamification_rules',$old_target);
$old_target['review_points']='99'; update_option('loyalty_extra_reviews_gamification_rules',$old_target);
$failure=function($sql) use($wpdb) { return false !== strpos($sql, "UPDATE {$wpdb->options}") && false !== strpos($sql, "'loyalty_extra_reviews_gamification_rules'") ? 'SELECT * FROM loyf_missing_target_table' : $sql; };
$wpdb->suppress_errors(true); add_filter('query',$failure); loyf_upgrade_migrate(); remove_filter('query',$failure); $wpdb->suppress_errors(false);
loyf_assert(!YOWCL_Free_Migrations::ready('review'),'Failed target write no witness'); loyf_equal(serialize($old_target),serialize(get_option('loyalty_extra_reviews_gamification_rules')),'Failed target write retains bytes');
loyf_reset_feature('review');
$source = get_option('loyalty_extra_points_rules'); $source['review_points'] = '0'; update_option('loyalty_extra_points_rules', $source);
$merged['review_enabled']='yes'; $merged['review_points']='99'; update_option('loyalty_extra_reviews_gamification_rules', $merged);
$fail = function($sql) use ($wpdb) { return false !== strpos($sql, "INSERT INTO {$wpdb->options}") && false !== strpos($sql, "'loyf_migration_review_v1',") ? 'SELECT * FROM loyf_missing_failure_table' : $sql; };
$wpdb->suppress_errors(true); add_filter('query', $fail); loyf_upgrade_migrate(); remove_filter('query', $fail); $wpdb->suppress_errors(false);
loyf_assert(!YOWCL_Free_Migrations::ready('review'), 'Failed witness held'); loyf_equal(0, YOWCL_Free_Core::extra('review'), 'Affected producer held');
$source['review_points']='88'; update_option('loyalty_extra_points_rules', $source);
loyf_upgrade_migrate(); loyf_equal(0, YOWCL_Free_Core::extra('review'), 'Retry frozen zero disables conflicting canonical target');
YOWCL_Free_Migrations::save('review', 'loyalty_extra_reviews_gamification_rules', array('review_enabled'=>'yes','review_points'=>'13'));
loyf_upgrade_migrate(); loyf_equal(13,YOWCL_Free_Core::extra('review'),'Witness rerun preserves merchant terms');
// Negative legacy terms must never inherit positive dormant canonical enablement.
loyf_reset_feature('signup');
$source['signup_points']='0'; $source['signup_enabled']='yes'; update_option('loyalty_extra_points_rules',$source);
loyf_upgrade_migrate(); loyf_equal(0,YOWCL_Free_Core::extra('signup'),'Zero signup remains disabled');
loyf_reset_feature('levelup');
update_option('loyalty_extra_levelup_points_rules',array('loyf_gold'=>array('awarded'=>'0','unknown'=>'007')));
$merged=get_option('loyalty_extra_reviews_gamification_rules'); $merged['levelup_enabled']='yes'; update_option('loyalty_extra_reviews_gamification_rules',$merged);
loyf_upgrade_migrate(); loyf_equal('no',get_option('loyalty_extra_reviews_gamification_rules')['levelup_enabled'],'Zero level map disabled');
loyf_equal('007',get_option('loyalty_extra_reviews_gamification_rules')['levelup_points']['loyf_gold']['unknown'],'Exact nested role terms');
// Real settings entry point writes canonical terms, preserves evidence and dormant keys.
wp_set_current_user(1); $_POST=array('extra_points_settings_nonce'=>wp_create_nonce('save_extra_points_settings_action'),'loyalty_extra_signup_points'=>'9','loyalty_extra_login_points'=>'4','loyalty_extra_review_points'=>'12','loyalty_extra_levelup_loyf_gold'=>'6');
foreach(YOWCL_Free_Migrations::features() as $feature){loyf_assert(YOWCL_Free_Migrations::ready($feature),'Pre-save feature held '.$feature);}
$legacy_map=get_option('loyalty_extra_levelup_points_rules'); (new YOSWC_Loyalty_Settings_Extra_Points())->save_extra_points_settings(); $_POST=array();
loyf_upgrade_migrate(); loyf_equal(9,YOWCL_Free_Core::extra('signup'),'Authorized canonical signup save'); loyf_equal(12,YOWCL_Free_Core::extra('review'),'Review UI/runtime aligned');
loyf_equal($legacy_map,get_option('loyalty_extra_levelup_points_rules'),'Legacy role evidence read-only'); loyf_equal('29',get_option('loyalty_extra_points_rules')['birthday_points'],'Save preserves dormant account'); loyf_equal(array('keep'=>'001'),get_option('loyalty_extra_reviews_gamification_rules')['context_rules'],'Save preserves dormant merged');
// Denied direct save facade remains independently guarded, even with a valid nonce.
$canonical=get_option('loyalty_extra_points_rules'); wp_set_current_user((int)$before['rows'][0]['user_id']); $nonce=wp_create_nonce('save_extra_points_settings_action');
$_POST=array('extra_points_settings_nonce'=>$nonce,'loyalty_extra_signup_points'=>'99');
$die=function(){return function(){throw new LOYF_Test_Die();};}; add_filter('wp_die_handler',$die,PHP_INT_MAX); add_filter('wp_die_ajax_handler',$die,PHP_INT_MAX);
try {(new YOSWC_Loyalty_Settings_Extra_Points())->save_extra_points_settings(); throw new RuntimeException('Denied save admitted');} catch(LOYF_Test_Die $e) {} finally {remove_filter('wp_die_handler',$die,PHP_INT_MAX);remove_filter('wp_die_ajax_handler',$die,PHP_INT_MAX);$_POST=array();}
loyf_equal(serialize($canonical),serialize(get_option('loyalty_extra_points_rules')),'Denied save no mutation');
// Existing legacy serialization wrappers retain unknown bytes/semantics through narrow merge.
loyf_reset_feature('redemption'); $using=array('points'=>'10','amount'=>'1','unknown'=>array('exact'=>'009')); update_option('loyalty_points_using_rules',serialize($using)); loyf_upgrade_migrate();
$using_raw=YOWCL_Free_Migrations::read('loyalty_points_using_rules'); loyf_assert(is_serialized(maybe_unserialize($using_raw)),'Serialized wrapper retained');
$using_after=maybe_unserialize(get_option('loyalty_points_using_rules')); loyf_equal($using['unknown'],$using_after['unknown'],'Wrapped unknown values retained');
// Wrapped shared targets use canonical terms after witness/save; retained legacy never wins.
$wrapped_account=get_option('loyalty_extra_points_rules'); update_option('loyalty_extra_points_rules',serialize($wrapped_account));
$wrapped_merged=get_option('loyalty_extra_reviews_gamification_rules'); update_option('loyalty_extra_reviews_gamification_rules',serialize($wrapped_merged));
update_option('loyalty_extra_levelup_points_rules',$before['options']['loyalty_extra_levelup_points_rules']);
foreach(array('signup','login','review','levelup') as $feature) { loyf_reset_feature($feature); }
loyf_upgrade_migrate();
wp_set_current_user(1); $_POST=array('extra_points_settings_nonce'=>wp_create_nonce('save_extra_points_settings_action'),'loyalty_extra_signup_points'=>'9','loyalty_extra_login_points'=>'4','loyalty_extra_review_points'=>'13','loyalty_extra_levelup_loyf_gold'=>'0');
(new YOSWC_Loyalty_Settings_Extra_Points())->save_extra_points_settings(); $_POST=array();
loyf_upgrade_migrate(); loyf_equal(13,YOWCL_Free_Core::extra('review'),'Wrapped canonical review overrides retained legacy88');
$wrapped_user=wp_insert_user(array('user_login'=>'wrapped_upgrade','user_email'=>'wrapped@example.invalid','user_pass'=>'disposable-only','role'=>'customer'));
$wrapped_points=get_user_meta($wrapped_user,'user_points',true); YOWCL_Free_Core::level_bonus($wrapped_user,'loyf_gold'); loyf_equal($wrapped_points,get_user_meta($wrapped_user,'user_points',true),'Wrapped disabled level never reads retained legacy terms');
ob_start(); (new YOSWC_Loyalty_Settings_Extra_Points())->display_extra_points_settings(); $wrapped_form=ob_get_clean(); loyf_assert(false!==strpos($wrapped_form,'value="13"'),'Wrapped canonical settings renderer');
ob_start(); (new YOSWC_Loyalty_Settings_Using_Point_Rules())->render_using_point_field(array('name'=>'Fixture')); $using_form=ob_get_clean(); loyf_assert(false!==strpos($using_form,'value="10"'),'Wrapped redemption settings renderer');
$bubble=new YOSWC_Loyalty_Info_Bubble(); $earning_method=new ReflectionMethod($bubble,'get_earning_section'); $earning_method->setAccessible(true); $earning_output=json_encode($earning_method->invoke($bubble,'Points'));
loyf_assert(false!==strpos($earning_output,'Product review: 13 Points'),'Bubble canonical review terms'); loyf_assert(false===strpos($earning_output,'Reach Fixture Gold:'),'Bubble excludes disabled level reward');
$wrapped_saved=get_option('loyalty_extra_reviews_gamification_rules'); delete_option('loyalty_extra_reviews_gamification_rules'); loyf_equal(0,YOWCL_Free_Core::extra('review'),'Missing witnessed canonical pair cannot reactivate legacy'); update_option('loyalty_extra_reviews_gamification_rules',$wrapped_saved);
// Both email directions; canonical customization and legacy preference retained.
foreach (array('points_reward','points_deduct','level_update') as $family) { loyf_equal('yes', get_option('woocommerce_yowcl_loyalty_' . $family . '_settings')['enabled'], 'Legacy mail enabled'); }
loyf_reset_feature('email_reward'); $legacy = get_option('loyalty_notification_email'); $original_legacy=$legacy; $legacy['points_update']=false; update_option('loyalty_notification_email',$legacy);
loyf_upgrade_migrate(); $mail=get_option('woocommerce_yowcl_loyalty_points_reward_settings'); loyf_equal('no',$mail['enabled'],'Legacy false overrides native yes'); loyf_equal('Custom {earned_points}',$mail['subject'],'Custom subject survives'); loyf_equal('001',$mail['unknown'],'Unknown native field');
YOWCL_Free_Migrations::save('email_reward','woocommerce_yowcl_loyalty_points_reward_settings',array('enabled'=>'yes'));
loyf_upgrade_migrate(); loyf_equal('yes',get_option('woocommerce_yowcl_loyalty_points_reward_settings')['enabled'],'Merchant mail enablement authoritative');
update_option('loyalty_notification_email',$original_legacy);
// Native settings wrappers retain canonical customization and enablement in Woo consumers.
foreach(array('email_reward'=>'Points_Reward','email_deduct'=>'Points_Deduct','email_level'=>'Level_Update') as $feature=>$family) {
    $id='yowcl_loyalty_' . strtolower($family); $option='woocommerce_' . $id . '_settings'; $native_wrapped=get_option($option); update_option($option,serialize($native_wrapped)); loyf_reset_feature($feature);
}
loyf_upgrade_migrate();
$native_classes=WC()->mailer()->get_emails();
foreach(array('email_reward'=>'Points_Reward','email_deduct'=>'Points_Deduct','email_level'=>'Level_Update') as $feature=>$family) {
    $class='YOWCL_WC_Email_Loyalty_' . $family; $probe=new $class();
    loyf_assert(YOWCL_Free_Migrations::ready($feature) && $probe->is_enabled(),'Wrapped native family enabled ' . $family);
    $stored=maybe_unserialize(get_option('woocommerce_' . $probe->id . '_settings'));
    foreach(array('subject','heading','email_type','unknown','unknown_object') as $field) { if(isset($stored[$field])) { loyf_equal(serialize($stored[$field]),serialize($probe->get_option($field)),'Wrapped native custom field ' . $family . ':' . $field); } }
    foreach(array('yowcl_','woocommerce_') as $prefix) { remove_action($prefix . 'loyalty_' . strtolower($family),array($probe,'trigger'),10); }
    remove_filter('woocommerce_settings_api_sanitized_fields_' . $probe->id,array($probe,'preserve_settings'));
}
// Already-warm native mailer recovers registration without duplicate instances/hooks.
$warm=WC()->mailer(); $count=count($warm->get_emails()); $warm_ids=array_map('spl_object_hash',$warm->get_emails());
$bridge=new YOSWC_Loyalty_Notifications_Email(); loyf_equal($count,count($warm->get_emails()),'Warm mailer count stable'); loyf_equal($warm_ids,array_map('spl_object_hash',$warm->get_emails()),'Warm instances reused');
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

loyf_assert( ! YOWCL_Free_Onboarding::writable(), "Actual old-Free upgrade is review-only" );

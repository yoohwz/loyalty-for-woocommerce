<?php
if (!defined('LOY_RUNTIME_DISPOSABLE') || !defined('WC_LOYALTY_VERSION')) { throw new RuntimeException('native_premium_required'); }
$settings = array(
 'loyalty_levels_roles'=>array('subscriber','customer'),
 'loyalty_levels_rules'=>array('subscriber'=>array('from'=>0),'customer'=>array('from'=>100)),
 'loyalty_points_using_rules'=>serialize(array('points'=>10,'amount'=>1,'unknown'=>'009')),
 'loyalty_extra_points_rules'=>array('signup_points'=>17,'signup_enabled'=>'no','login_points'=>19,'login_enabled'=>'no'),
 'loyalty_extra_reviews_gamification_rules'=>array('review_points'=>23,'review_enabled'=>'yes','levelup_enabled'=>'yes','levelup_points'=>array('subscriber'=>array('awarded'=>29))),
 'woocommerce_yowcl_loyalty_points_reward_settings'=>array('enabled'=>'yes','subject'=>'Merchant reward subject'),
 'woocommerce_yowcl_loyalty_points_deduct_settings'=>array('enabled'=>'yes','subject'=>'Merchant deduct subject'),
 'woocommerce_yowcl_loyalty_level_update_settings'=>array('enabled'=>'yes','subject'=>'Merchant level subject'),
);
$settings['loyalty_extra_points_rules']['signup_enabled']='yes';$settings['loyalty_extra_points_rules']['login_enabled']='yes';
foreach ($settings as $name=>$value) { update_option($name,$value); }
delete_option('loyalty_notification_email');
update_option('yowcl_license_key','synthetic-disposable-license');update_option('yowcl_license_expired','synthetic-expiry');
$settings['yowcl_license_key']='synthetic-disposable-license';$settings['yowcl_license_expired']='synthetic-expiry';
global $wpdb;
$existing=wp_insert_user(array('user_login'=>'premium_existing','user_email'=>'premium-existing@example.invalid','user_pass'=>'disposable-only','role'=>'subscriber'));if(is_wp_error($existing)){throw new RuntimeException('Premium origin fixture user failed');}
update_user_meta($existing,'user_points','12.75');update_user_meta($existing,'user_earning_points','55.25');update_user_meta($existing,'_yoswc_role_claims',array('subscriber'=>array('fixture_companion'=>array('context'=>array('keep'=>'009')))));
update_user_meta($existing,'_yowcl_dormant_fixture',serialize(array('keep'=>'007')));
if(false===$wpdb->insert($wpdb->prefix.'yo_loyalty_points_log',array('user_id'=>$existing,'action'=>'admin_reward','order_id'=>0,'amount'=>'12.75','description'=>'Synthetic pre-canonical Premium history','date'=>'2024-01-01 00:00:00'))){throw new RuntimeException('Premium nullable history fixture failed');}
$persistence=array('meta'=>$wpdb->get_results("SELECT * FROM {$wpdb->usermeta} ORDER BY umeta_id",ARRAY_A),'rows'=>$wpdb->get_results("SELECT * FROM {$wpdb->prefix}yo_loyalty_points_log ORDER BY id",ARRAY_A),'roles'=>$wpdb->get_row($wpdb->prepare("SELECT option_value,autoload FROM {$wpdb->options} WHERE option_name=%s",$wpdb->prefix.'user_roles'),ARRAY_A));
if(false===file_put_contents(getenv('LOYF13_SNAPSHOT').'.persistence',serialize($persistence))){throw new RuntimeException('Premium persistence snapshot failed');}
$rows = $wpdb->get_results('SELECT option_name,option_value,autoload FROM '.$wpdb->options.' WHERE option_name IN ('.implode(',',array_map(function($n)use($wpdb){return $wpdb->prepare('%s',$n);},array_keys($settings))).') ORDER BY option_name',ARRAY_A);
file_put_contents(getenv('LOYF13_SNAPSHOT'),wp_json_encode($rows,JSON_PRETTY_PRINT));
echo "Native Premium-origin fixture seeded; validator simulated, transport blocked.\n";

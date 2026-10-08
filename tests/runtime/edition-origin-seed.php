<?php
if (!defined('LOY_RUNTIME_DISPOSABLE') || !defined('WC_LOYALTY_VERSION')) { throw new RuntimeException('native_premium_required'); }
$settings = array(
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
$rows = $wpdb->get_results('SELECT option_name,option_value,autoload FROM '.$wpdb->options.' WHERE option_name IN ('.implode(',',array_map(function($n)use($wpdb){return $wpdb->prepare('%s',$n);},array_keys($settings))).') ORDER BY option_name',ARRAY_A);
file_put_contents(getenv('LOYF13_SNAPSHOT'),wp_json_encode($rows,JSON_PRETTY_PRINT));
echo "Native Premium-origin fixture seeded; validator simulated, transport blocked.\n";

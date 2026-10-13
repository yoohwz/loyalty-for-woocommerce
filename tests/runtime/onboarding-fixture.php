<?php
// Retained activation runner: native retirement and bootstrap safety, no wizard execution.
require_once __DIR__ . '/assertions.php';
$case=getenv('LOYF11_CASE');$phase=getenv('LOYF11_PHASE');
function loyf37_state() {
 global $wpdb;
 $options=$wpdb->get_results("SELECT option_name,option_value,autoload FROM {$wpdb->options} WHERE option_name LIKE 'loyalty\_%' OR option_name LIKE 'loyf\_%' OR option_name LIKE 'woocommerce_yowcl\_%' ORDER BY option_name",ARRAY_A);
 return array($options,$wpdb->get_results("SELECT * FROM {$wpdb->usermeta} WHERE meta_key IN ('user_points','user_earning_points','_yowcl_loyalty_level','_yoswc_role_claims') ORDER BY umeta_id",ARRAY_A),get_option($wpdb->prefix.'user_roles'),$wpdb->get_results("SELECT * FROM {$wpdb->prefix}yo_loyalty_points_log ORDER BY id",ARRAY_A));
}
if ( 'seed' === $phase ) {
    global $wpdb;
    // Ordinary pre-adoption users and orders are deliberately present in every case.
    $user = wp_insert_user( array( 'user_login'=>'onboarding_customer','user_pass'=>'disposable-only','user_email'=>'onboarding@example.invalid','role'=>'customer' ) );
    $p = new WC_Product_Simple(); $p->set_name( 'Onboarding fixture' ); $p->set_regular_price( '100' ); $p->save();
    $o = wc_create_order( array( 'customer_id'=>$user ) ); $o->add_product( $p,1 ); $o->set_date_created( time()-100 ); $o->calculate_totals(); $o->save();
    $fixture = array( 'user'=>$user,'order'=>$o->get_id(),'product'=>$p->get_id() );
    if ( 'options' === $case ) { add_option( 'loyalty_points_earning_rules',array( 'customer'=>array( 'points'=>'17','amount'=>'23' ),'unknown'=>array( 'nested'=>true ) ) ); }
    if ( 'premium' === $case ) { add_option( 'loyalty_referral_coupon',serialize( array( 'enabled'=>'yes','opaque'=>array( 'preserve'=>'exact' ) ) ) ); }
    if ( 'user-marker' === $case ) { add_user_meta( $user,'_yowcl_loyalty_level','gold' ); }
    if ( 'used-marker' === $case ) { $o->update_meta_data('_used_points',31); $o->save_meta_data(); }
    if ( 'discount-marker' === $case ) { $o->update_meta_data('_used_points_discount','7.25'); $o->save_meta_data(); }
    if ( 'hpos-used-marker' === $case ) { $o->update_meta_data('_used_points',31); $o->save_meta_data(); }
    if ( 'order-marker' === $case ) { $o->update_meta_data( '_points_awarded',31 ); $o->save_meta_data(); }
    if ( 'hpos-marker' === $case ) { $o=wc_get_order( $o->get_id() ); $o->update_meta_data( '_yowcl_referral_terms',array( 'legacy'=>'preserve' ) ); $o->save_meta_data(); }
    if ( 'version' === $case ) { add_option( 'yoswc_loyalty_version','1.2.2' ); }
    if ( 'bad-cutover' === $case ) { add_option( 'loyf_core_cutover_v1',array( 'malformed'=>true ) ); }
    if ( 'migration' === $case ) { add_option( 'loyf_migration_signup_v1','broken' ); }
    if ( 'bad-witness' === $case ) { add_option( 'loyf_onboarding_v1',array( 'version'=>1,'status'=>'fresh' ) ); }
    if ( 'orphan-role' === $case ) { update_user_meta($user,$wpdb->prefix.'capabilities',array('deleted_loyalty_gold'=>true)); }
    if ( 'as-group' === $case ) { $wpdb->insert($wpdb->prefix.'actionscheduler_groups',array('slug'=>'yowcl-core-rewards')); }
    if ( 'cron' === $case ) { wp_schedule_single_event(time()+3600,'yowcl_legacy_fixture'); }
    if ( 'license' === $case ) { add_option('yowcl_license_key','dormant-premium-fixture'); }
    if ( 'role' === $case ) { add_role( 'old_loyalty','Old Loyalty',array( 'read'=>true ) ); }
    if ( 'balance' === $case ) { add_user_meta( $user,'user_points','9.5' ); add_user_meta( $user,'user_earning_points','17.25' ); }
    if ( 'log' === $case ) {
        $wpdb->query( "CREATE TABLE {$wpdb->prefix}yo_loyalty_points_log (id mediumint NOT NULL AUTO_INCREMENT,user_id bigint NOT NULL,action varchar(255) NOT NULL,order_id bigint NOT NULL,amount decimal(10,2) NOT NULL,description text NOT NULL,date datetime NOT NULL,PRIMARY KEY(id)) ENGINE=InnoDB" );
        $wpdb->insert( $wpdb->prefix . 'yo_loyalty_points_log',array( 'id'=>77,'user_id'=>$user,'action'=>'legacy_reward','order_id'=>$o->get_id(),'amount'=>'9.50','description'=>'Untouched legacy','date'=>'2020-01-01 00:00:00' ) );
    }
    if (in_array($case,array('proven','started','complete','dismissed','review'),true)) {
        add_option('loyf_onboarding_v1',array('version'=>1,'status'=>$case,'id'=>'historical-attempt','terms'=>array('redeem'=>true),'before'=>array('opaque'=>'preserve')), '', false);
        add_option('loyalty_points_earning_rules',array('customer'=>array('points'=>'17','amount'=>'23')));
        add_option('loyalty_points_using_rules',array('points'=>'100','amount'=>'1','unknown'=>'partial-launch'));
        add_option('loyalty_points_using_point','yes');
        foreach(array('signup','login','review','levelup','redemption','email_reward','email_deduct','email_level') as $feature){add_option('loyf_migration_'.$feature.'_v1','1','',false);}
    }
    $fixture['protected'] = $wpdb->get_results( "SELECT option_name,option_value,autoload FROM {$wpdb->options} WHERE option_name IN ('loyalty_points_earning_rules','loyalty_points_using_rules','loyalty_points_using_point','loyalty_referral_coupon','yowcl_license_key','loyf_core_cutover_v1','loyf_migration_signup_v1','loyf_onboarding_v1') ORDER BY option_name",ARRAY_A );
    $fixture['meta'] = $wpdb->get_results("SELECT * FROM {$wpdb->usermeta} WHERE meta_key IN ('user_points','user_earning_points','_yowcl_loyalty_level','_yoswc_role_claims') ORDER BY umeta_id",ARRAY_A);
    $fixture['roles'] = get_option($wpdb->prefix.'user_roles');
    file_put_contents( getenv( 'LOYF11_FIXTURE' ),wp_json_encode( $fixture ) ); return;
}

wp_set_current_user(get_user_by('login','loyf_admin')->ID);
global $wpdb;
$fixture=json_decode(file_get_contents(getenv('LOYF11_FIXTURE')),true);
loyf_assert(!class_exists('YOWCL_Free_Onboarding',false),'No retired setup class');
loyf_equal(false,has_action('admin_post_loyf_onboarding'),'No retired POST writer');
loyf_assert(!file_exists(WP_PLUGIN_DIR.'/loyalty-for-woocommerce/js/onboarding.js'),'No retired JS');
foreach($fixture['protected'] as $row){loyf_equal($row,$wpdb->get_row($wpdb->prepare("SELECT option_name,option_value,autoload FROM {$wpdb->options} WHERE option_name=%s",$row['option_name']),ARRAY_A),'Protected option '.$row['option_name']);}
loyf_equal($fixture['meta'],$wpdb->get_results("SELECT * FROM {$wpdb->usermeta} WHERE meta_key IN ('user_points','user_earning_points','_yowcl_loyalty_level','_yoswc_role_claims') ORDER BY umeta_id",ARRAY_A),'Economic metadata exact');
loyf_equal($fixture['roles'],get_option($wpdb->prefix.'user_roles'),'Physical roles exact');
if('bad-cutover'===$case){loyf_assert(!class_exists('YOWCL_Points_Transaction',false),'Invalid cutover prevents writer inclusion');echo "Native invalid cutover safety PASS\n";return;}
loyf_assert(class_exists('YOWCL_Points_Transaction',false),'Core writer booted behind cutover');
if('log'===$case){$row=$wpdb->get_row("SELECT * FROM {$wpdb->prefix}yo_loyalty_points_log WHERE id=77",ARRAY_A);loyf_equal('9.50',$row['amount'],'Historical fractional row');loyf_equal(null,$row['event_key'],'No fabricated identity');}
else{loyf_equal(array(),$wpdb->get_results("SELECT * FROM {$wpdb->prefix}yo_loyalty_points_log",ARRAY_A),'Activation awards no points');}
if('reactivation-before'===$phase){file_put_contents(getenv('LOYF11_FIXTURE').'.reactivation',wp_json_encode(loyf37_state()));return;}
if('reactivation-after'===$phase){loyf_equal(json_decode(file_get_contents(getenv('LOYF11_FIXTURE').'.reactivation'),true),loyf37_state(),'Reactivation exact state');return;}
if('browser-before'===$phase){
 // Warm the unchanged admin_init defaults before the read-only HTTP comparison.
 YOSWC_Loyalty_Backend::check_version();
 YOSWC_Loyalty_Settings_Customization::set_default_message_settings();
 file_put_contents(getenv('LOYF11_FIXTURE').'.browser',wp_json_encode(loyf37_state()));return;}
if('browser-after'===$phase){loyf_equal(json_decode(file_get_contents(getenv('LOYF11_FIXTURE').'.browser'),true),loyf37_state(),'Retired HTTP routes cannot write');return;}
// Ordinary boot never manufactures synchronous fresh migration authority.
if(!in_array($case,array('proven','started','complete','dismissed','review'),true)){
 foreach(YOWCL_Free_Migrations::features() as $feature){loyf_equal(null,YOWCL_Free_Migrations::read(YOWCL_Free_Migrations::witness($feature).'_before'),'No fresh evidence '.$feature);}
}
if('fresh'===$case){
 loyf_equal(null,get_option('loyf_onboarding_v1',null),'Fresh install creates no setup state');
 loyf_equal(null,get_option('loyalty_points_earning_rules',null),'No default earning conversion');
 loyf_equal(null,get_option('loyalty_points_using_point',null),'No default redemption enablement');
 loyf_equal(null,get_option('loyalty_points_using_rules',null),'No default redemption conversion');
 // Use the existing registered scheduler/worker to settle inactive policies before manual configuration.
 YOWCL_Free_Migrations::schedule();
 foreach(as_get_scheduled_actions(array('hook'=>YOWCL_Free_Migrations::HOOK,'status'=>'pending','per_page'=>100)) as $action){do_action(YOWCL_Free_Migrations::HOOK,...array_values($action->get_args()));}
 foreach(YOWCL_Free_Migrations::features() as $feature){loyf_assert(YOWCL_Free_Migrations::ready($feature),'Inactive migration ready '.$feature);}
 $_POST=array('earning_point_rules_nonce'=>wp_create_nonce('save_earning_point_rules'),'loyalty_earning_points'=>array('customer'=>'7'),'loyalty_earning_amount'=>array('customer'=>'5'));
 (new YOSWC_Loyalty_Settings())->save_earning_point_rules();
 loyf_equal('7',get_option('loyalty_points_earning_rules')['customer']['points'],'Native manual earning save');
 loyf_equal(null,get_option('loyf_onboarding_v1',null),'Manual save never revives setup');
}
echo 'Native setup retirement/activation PASS '.$case."\n";

<?php
require_once __DIR__ . '/assertions.php';
$case = getenv( 'LOYF11_CASE' ); $phase = getenv( 'LOYF11_PHASE' );
function loyf11_input() {
    return array( 'attempt'=>YOWCL_Free_Onboarding::state()['id'] ?? '', 'currency'=>get_woocommerce_currency(),'role'=>'customer','earn_points'=>'2','earn_amount'=>'5','rounding'=>'round_down','earn_status'=>array( 'wc-processing','wc-completed' ),'deduct_status'=>array( 'wc-failed','wc-cancelled','wc-refunded' ),'bubble'=>'yes','account'=>'yes','redeem'=>'yes','redeem_points'=>'100','redeem_amount'=>'1','first'=>'yes','first_points'=>'25','referral'=>'yes','referral_points'=>'10' );
}
function loyf11_snapshot() {
    global $wpdb;
    $o = array(); foreach ( YOWCL_Free_Onboarding::names() as $name ) { $o[$name] = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",$name ) ); }
    return array( $o,$wpdb->get_results( "SELECT * FROM {$wpdb->usermeta} WHERE meta_key IN ('user_points','user_earning_points','_yowcl_loyalty_level','_yoswc_role_claims') ORDER BY umeta_id",ARRAY_A ),$wpdb->get_results( "SELECT * FROM {$wpdb->prefix}yo_loyalty_points_log ORDER BY id",ARRAY_A ),get_option( $wpdb->prefix . 'user_roles' ),$wpdb->get_results( "SELECT action_id,hook,args,status FROM {$wpdb->prefix}actionscheduler_actions WHERE hook LIKE 'yowcl%' ORDER BY action_id",ARRAY_A ) );
}
function loyf11_render() { ob_start(); YOWCL_Free_Onboarding::render(); return ob_get_clean(); }
function loyf11_denied( $callback,$label ) { try { $callback(); } catch ( Throwable $e ) { return; } throw new LogicException( 'Expected denial: ' . $label ); }
if ( 'worker' === $phase ) { return; }
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
    if ( 'order-marker' === $case ) { $o->update_meta_data( '_points_awarded',31 ); $o->save_meta_data(); }
    if ( 'hpos-marker' === $case ) { $o=wc_get_order( $o->get_id() ); $o->update_meta_data( '_yowcl_referral_terms',array( 'legacy'=>'preserve' ) ); $o->save_meta_data(); }
    if ( 'version' === $case ) { add_option( 'yoswc_loyalty_version','1.2.2' ); }
    if ( 'bad-cutover' === $case ) { add_option( 'loyf_core_cutover_v1',array( 'malformed'=>true ) ); }
    if ( 'migration' === $case ) { add_option( 'loyf_migration_signup_v1','broken' ); }
    if ( 'bad-witness' === $case ) { add_option( 'loyf_onboarding_v1',array( 'version'=>1,'status'=>'fresh' ) ); }
    if ( 'role' === $case ) { add_role( 'old_loyalty','Old Loyalty',array( 'read'=>true ) ); }
    if ( 'balance' === $case ) { add_user_meta( $user,'user_points','9.5' ); add_user_meta( $user,'user_earning_points','17.25' ); }
    if ( 'log' === $case ) {
        $wpdb->query( "CREATE TABLE {$wpdb->prefix}yo_loyalty_points_log (id mediumint NOT NULL AUTO_INCREMENT,user_id bigint NOT NULL,action varchar(255) NOT NULL,order_id bigint NOT NULL,amount decimal(10,2) NOT NULL,description text NOT NULL,date datetime NOT NULL,PRIMARY KEY(id)) ENGINE=InnoDB" );
        $wpdb->insert( $wpdb->prefix . 'yo_loyalty_points_log',array( 'id'=>77,'user_id'=>$user,'action'=>'legacy_reward','order_id'=>$o->get_id(),'amount'=>'9.50','description'=>'Untouched legacy','date'=>'2020-01-01 00:00:00' ) );
    }
    if ( 'assessment-failure' === $case ) { file_put_contents( getenv( 'LOYF11_FAIL_FILE' ),'once' ); }
    $fixture['protected'] = $wpdb->get_results( "SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name IN ('loyalty_points_earning_rules','loyalty_referral_coupon','loyf_core_cutover_v1','loyf_migration_signup_v1','loyf_onboarding_v1') ORDER BY option_name",ARRAY_A );
    file_put_contents( getenv( 'LOYF11_FIXTURE' ),wp_json_encode( $fixture ) ); return;
}
wp_set_current_user( get_user_by( 'login','loyf_admin' )->ID ); do_action( 'admin_init' );
$fixture = json_decode( file_get_contents( getenv( 'LOYF11_FIXTURE' ) ),true );
if ( 'browser-check' === $phase ) {
    loyf_equal( 'complete',YOWCL_Free_Onboarding::state()['status'],'Browser Launch completed' );
    loyf_equal( '7',get_option( 'loyalty_points_earning_rules' )['customer']['points'],'Stale tab preserves later native Woo save' );
    loyf_equal( null,get_option( YOWCL_Free_First_Purchase::WITNESS,null ),'Browser skipped First Purchase' );
    loyf_equal( null,get_option( YOWCL_Free_Referral::OPTION,null ),'Browser skipped Referral Lite' );
    loyf_equal( array(),loyf_rows( $fixture['user'] ),'Browser previews/Launch never mint value' );
    echo "Native onboarding browser saved choices PASS\n"; return;
}
if ( ! in_array( $case,array( 'fresh','skip','late-setting','late-balance','failure','first-failure','referral-failure','unknown-referral','disconnect','parallel','merchant-race','browser','dismiss' ),true ) ) {
    loyf_assert( ! YOWCL_Free_Onboarding::writable(),'Footprint is review-only: ' . $case );
    $html = loyf11_render(); loyf_assert( false === strpos( $html,'id="loyf-quick-start"' ),'No writable review form' );
    global $wpdb;
    foreach ( $fixture['protected'] as $row ) { loyf_equal( $row['option_value'],$wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",$row['option_name'] ) ),'Footprint preserved ' . $row['option_name'] ); }
    if ( 'balance' === $case ) { loyf_equal( '9.5',get_user_meta( $fixture['user'],'user_points',true ),'Fractional balance unchanged' ); }
    if ( 'log' === $case ) { $r=$wpdb->get_row( "SELECT * FROM {$wpdb->prefix}yo_loyalty_points_log WHERE id=77",ARRAY_A ); loyf_equal( '9.50',$r['amount'],'History amount' ); loyf_equal( null,$r['event_key'],'Historical NULL identity' ); }
    echo 'Native onboarding footprint PASS ' . $case . "\n"; return;
}
loyf_assert( YOWCL_Free_Onboarding::writable(),'Unseeded activation is fresh: ' . $case );
$before = loyf11_snapshot(); $html = loyf11_render(); loyf_equal( $before,loyf11_snapshot(),'Rendering is business read-only' ); loyf_assert( false !== strpos( $html,'id="loyf-quick-start"' ),'Shipped wizard' );
$input = loyf11_input();
foreach ( array( 'earn_points'=>array( '-1','1.5','1e3','999999999',array() ),'earn_amount'=>array( '0','INF','0.001','1e100',array() ),'rounding'=>array( 'unknown' ),'currency'=>array( 'INVALID' ),'role'=>array( 'administrator' ),'earn_status'=>array( array( 'wc-pending' ),array( array() ) ) ) as $key=>$values ) {
    foreach ( $values as $value ) { $bad=$input; $bad[$key]=$value; loyf11_denied( static function () use ( $bad ) { YOWCL_Free_Onboarding::launch( $bad ); },'Bad ' . $key ); loyf_equal( $before,loyf11_snapshot(),'Invalid input has no writes' ); }
}
wp_set_current_user( $fixture['user'] ); loyf11_denied( static function () use ( $input ) { YOWCL_Free_Onboarding::launch( $input ); },'Customer denied' ); wp_set_current_user( get_user_by( 'login','loyf_admin' )->ID );
if ( 'browser' === $case ) { echo "Browser fixture fresh PASS\n"; return; }
if ( 'late-setting' === $case ) { update_option( 'loyalty_extra_points_rules',array( 'signup_enabled'=>'yes','signup_points'=>'13','merchant'=>'preserve' ) ); $before=loyf11_snapshot(); loyf11_denied( static function () use ( $input ) { YOWCL_Free_Onboarding::launch( $input ); },'Prior merchant settings' ); loyf_equal( $before,loyf11_snapshot(),'No stale launch writes' ); return; }
if ( 'late-balance' === $case ) { update_user_meta( $fixture['user'],'user_points',19 ); $before=loyf11_snapshot(); loyf11_denied( static function () use ( $input ) { YOWCL_Free_Onboarding::launch( $input ); },'Used program' ); loyf_equal( $before,loyf11_snapshot(),'No used-program writes' ); return; }
if ( 'skip' === $case || 'dismiss' === $case ) { unset( $input['first'],$input['referral'],$input['redeem'] ); }
if ( 'dismiss' === $case ) {
    $s=YOWCL_Free_Onboarding::state(); $s['status']='dismissed'; update_option( YOWCL_Free_Onboarding::OPTION,$s,false );
    loyf11_denied( static function () use ( $input ) { YOWCL_Free_Onboarding::launch( $input ); },'Dismissed invitation' ); loyf_equal( $before,loyf11_snapshot(),'Dismiss no business writes' ); return;
}
$faults=array( 'failure'=>'onboarding_settings_write','first-failure'=>'first_purchase_settings_write','referral-failure'=>'onboarding_referral_save','unknown-referral'=>'onboarding_referral_saved','disconnect'=>'onboarding_settings_write' );
if ( isset( $faults[$case] ) ) {
    $hook=static function ( $step,$key ) use ( $case,$faults ) { if ( $step !== $faults[$case] ) { return; } if ( 'disconnect' === $case ) { global $wpdb; $killer=new mysqli( getenv('LOY_DB_HOST'),getenv('LOY_DB_USER'),getenv('LOY_DB_PASSWORD'),DB_NAME,(int)getenv('LOY_DB_PORT') ?: 3306 ); $killer->query('KILL CONNECTION '.mysqli_thread_id($wpdb->dbh)); $killer->close(); } throw new RuntimeException( 'injected_onboarding_failure' ); };
    add_action( 'yowcl_reward_test_checkpoint',$hook,10,2 );
    loyf11_denied( static function () use ( $input ) { YOWCL_Free_Onboarding::launch( $input ); },'Injected interruption' ); remove_action( 'yowcl_reward_test_checkpoint',$hook,10 );
    if ( 'disconnect' === $case ) { global $wpdb; $wpdb->check_connection( false ); }
    loyf_equal( 'started',YOWCL_Free_Onboarding::state()['status'],'Failure keeps consumed incomplete invitation' );
    $partial=loyf11_snapshot(); loyf11_denied( static function () use ( $input ) { YOWCL_Free_Onboarding::launch( $input ); },'Replay cannot rerun services' ); loyf_equal( $partial,loyf11_snapshot(),'Failed replay preserves partial outcomes' );
    if ( in_array( $case,array( 'failure','disconnect' ),true ) ) { loyf_equal( $before,$partial,'Atomic baseline rollback' ); }
    if ( 'first-failure' === $case ) { loyf_equal( null,get_option( YOWCL_Free_First_Purchase::WITNESS,null ),'Native pair rollback no fake epoch' ); }
    loyf_equal( array(),loyf_rows( $fixture['user'] ),'Failed wizard never minted value' ); echo 'Native onboarding interruption PASS ' . $case . "\n"; return;
}
if ( in_array( $case,array( 'parallel','merchant-race' ),true ) ) {
    $barrier=tempnam(sys_get_temp_dir(),'loyf11-worker-'); unlink($barrier);
    $env=getenv(); $env['LOYF11_PHASE']='worker'; $env['LOYF11_BARRIER']=$barrier;
    $pipes=array(); $worker=proc_open(array(PHP_BINARY,getenv('LOYF_WP_CLI_PHAR'),'--path='.ABSPATH,'eval-file',__DIR__.'/onboarding-worker.php','--quiet'),array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes,null,$env);
    loyf_assert(is_resource($worker),'Native independent worker'); fclose($pipes[0]);
    $until=microtime(true)+15; while(!file_exists($barrier.'.ready')&&microtime(true)<$until){usleep(20000);} loyf_assert(file_exists($barrier.'.ready'),'Independent worker booted before Launch');
    $dispatched=false;
    $pause=static function($step,$key)use($case,$barrier,&$dispatched){
        if('onboarding_settings_write'===$step&&!$dispatched){$dispatched=true;file_put_contents($barrier.'.go','go');usleep(150000);loyf_assert(!file_exists($barrier.'.done'),'Contender blocked by actual native ownership');}
        if('merchant-race'===$case&&'onboarding_baseline_committed'===$step){$until=microtime(true)+10;while(!file_exists($barrier.'.done')&&microtime(true)<$until){usleep(20000);}loyf_assert(file_exists($barrier.'.done'),'Native merchant save completed after row lock');}
    };
    add_action('yowcl_reward_test_checkpoint',$pause,10,2);
    try {
        if('merchant-race'===$case){loyf11_denied(static function()use($input){YOWCL_Free_Onboarding::launch($input);},'Concurrent native settings change');}
        else{YOWCL_Free_Onboarding::launch($input);}
    } finally {remove_action('yowcl_reward_test_checkpoint',$pause,10);}
    $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);loyf_equal(0,proc_close($worker),'Native worker '.$out.$err);
    foreach(array('.ready','.go','.done')as$suffix){if(file_exists($barrier.$suffix)){unlink($barrier.$suffix);}}
    if('merchant-race'===$case){loyf_equal('started',YOWCL_Free_Onboarding::state()['status'],'Concurrent changed settings never complete');loyf_equal('7',get_option('loyalty_points_earning_rules')['customer']['points'],'Native merchant value retained');$partial=loyf11_snapshot();loyf11_denied(static function()use($input){YOWCL_Free_Onboarding::launch($input);},'No concurrent retry clobber');loyf_equal($partial,loyf11_snapshot(),'No concurrent clobber');echo "Native onboarding merchant concurrency PASS\n";return;}
    echo "Native onboarding parallel Launch PASS\n";
}
YOWCL_Free_Onboarding::launch( $input );
loyf_equal( 'complete',YOWCL_Free_Onboarding::state()['status'],'Confirmed completion' );
loyf_equal( array( 'customer' ),get_option( 'loyalty_levels_roles' ),'Selected customer baseline' );
loyf_equal( array(),loyf_rows( $fixture['user'] ),'Launch no history/value' );
loyf_equal( $before[1],loyf11_snapshot()[1],'Launch no user/role meta' ); loyf_equal( $before[3],loyf11_snapshot()[3],'Launch no physical role configuration' ); loyf_equal( $before[4],loyf11_snapshot()[4],'Launch no AS economic delivery' );
$saved=loyf11_snapshot(); YOWCL_Free_Onboarding::launch( $input ); loyf_equal( $saved,loyf11_snapshot(),'Idempotent same attempt' );
update_option( 'loyalty_points_earning_rules',array( 'customer'=>array( 'points'=>'7','amount'=>'5' ) ) ); $later=loyf11_snapshot(); YOWCL_Free_Onboarding::launch( $input ); loyf_equal( $later,loyf11_snapshot(),'Replay preserves later authoritative save' );
if ( 'skip' === $case ) { loyf_equal( null,get_option( YOWCL_Free_First_Purchase::WITNESS,null ),'Skipped First creates no epoch' ); loyf_equal( null,get_option( YOWCL_Free_Referral::OPTION,null ),'Skipped referral creates no setting' ); }
else {
    $config=YOWCL_Free_First_Purchase::configuration(); loyf_assert( $config['ready'] && $config['effective'],'Native real First epoch' );
    $old=wc_get_order( $fixture['order'] ); $old->update_status( 'processing' ); loyf_assert( ! get_user_meta( $fixture['user'],'first_purchase_rewarded',true ),'Pre-Launch pending excluded from First Purchase' );
    loyf_equal( array( 'enabled'=>true,'points'=>10 ),YOWCL_Free_Referral::settings(),'Selected native referral terms' );
}
echo 'Native onboarding Launch PASS ' . $case . "\n";

<?php
require __DIR__ . '/assertions.php';
global $wpdb;
function loyf7_user($name) {
    $rules=get_option('loyalty_extra_points_rules'); $off=$rules; $off['signup_enabled']='no'; update_option('loyalty_extra_points_rules',$off);
    $id=wp_insert_user(array('user_login'=>'cert7_'.$name,'user_email'=>'cert7_'.$name.'@example.invalid','user_pass'=>'disposable-only','role'=>'customer'));
    update_option('loyalty_extra_points_rules',$rules); loyf_assert(!is_wp_error($id),'User fixture'); return (int)$id;
}
function loyf7_row($key,$delta,$earning) {
    $row=YOWCL_Points_Transaction::find($key); loyf_assert(is_array($row),'Committed key '.$key);
    loyf_equal($delta,(int)$row['available_delta'],'Available delta '.$key); loyf_equal($earning,(int)$row['earning_delta'],'Earning delta '.$key);
    global $wpdb; loyf_equal(1,(int)$wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM '.YOWCL_Points_Log::table_name().' WHERE event_key=%s',$key)),'Unique key '.$key); return $row;
}
function loyf7_run_jobs($hook,$match) {
    $ids=as_get_scheduled_actions(array('hook'=>$hook,'status'=>'pending','per_page'=>100),'ids'); $matched=array();
    foreach($ids as $id) { $action=ActionScheduler::store()->fetch_action($id); if($match($action->get_args())) { $matched[]=$id; } }
    loyf_assert(count($matched)>0,'Durable matching retry '.$hook);
    foreach($matched as $id) { ActionScheduler::runner()->process_action($id,'LOYF-7 native retry'); loyf_equal('complete',ActionScheduler::store()->get_status($id),'AS completion'); }
}
function loyf7_workers($mode,$user,$object,$key,$points) {
    $barrier=tempnam(sys_get_temp_dir(),'loyf7-workers-'); unlink($barrier); $workers=array();
    foreach(array(0,1) as $index) {
        $env=getenv(); foreach(array('USER'=>$user,'OBJECT'=>$object,'MODE'=>$mode,'KEY'=>$key,'BARRIER'=>$barrier,'INDEX'=>$index) as $name=>$value){$env['LOYF7_'.$name]=(string)$value;}
        $pipes=array(); $process=proc_open(array(PHP_BINARY,getenv('LOYF_WP_CLI_PHAR'),'--path='.ABSPATH,'eval-file',__DIR__.'/economic-worker.php','--quiet'),array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes,null,$env);
        loyf_assert(is_resource($process),'Producer process'); fclose($pipes[0]); $workers[]=array($process,$pipes);
    }
    try { foreach($workers as $worker) {
        $out=stream_get_contents($worker[1][1]); $err=stream_get_contents($worker[1][2]); fclose($worker[1][1]); fclose($worker[1][2]); loyf_equal(0,proc_close($worker[0]),'Producer worker: '.$err);
        $result=json_decode($out,true); loyf_assert(is_array($result),'Producer JSON '.$out); loyf_equal($key,$result['key'],'Producer identity'); loyf_equal($points,$result['delta'],'Producer delta');
    }} finally { foreach(array(0,1) as $index){if(file_exists($barrier.'.'.$index)){unlink($barrier.'.'.$index);}} }
    wp_cache_delete($user,'user_meta'); loyf_balance($user,$points,$points,'Concurrent '.$mode); return loyf7_row($key,$points,$points);
}
$rules=get_option('loyalty_extra_points_rules'); $rules['signup_enabled']='yes'; $rules['signup_points']=5; $rules['login_enabled']='yes'; $rules['login_points']=3; update_option('loyalty_extra_points_rules',$rules);
$merged=get_option('loyalty_extra_reviews_gamification_rules'); $merged['review_enabled']='yes'; $merged['review_points']=7; $merged['levelup_enabled']='yes'; $merged['levelup_points']=array('loyf_gold'=>array('awarded'=>11)); update_option('loyalty_extra_reviews_gamification_rules',$merged);
$product=new WC_Product_Simple(); $product->set_name('Economic certification'); $product->set_regular_price('100'); $product->set_virtual(true); $product->set_status('publish'); $product->save();
$identity_rows=array();
foreach(array('signup'=>5,'login'=>3,'review'=>7,'level'=>11) as $mode=>$points) {
    $user=loyf7_user('race_'.$mode); $object=0;
    if('review'===$mode){$object=wp_insert_comment(array('comment_post_ID'=>$product->get_id(),'user_id'=>$user,'comment_type'=>'review','comment_approved'=>1,'comment_content'=>'Race review'));}
    $keys=array('signup'=>'reward:signup:'.$user,'login'=>'reward:daily_login:'.$user.':'.current_time('Y-m-d'),'review'=>'reward:review:'.$object,'level'=>'reward:level_up:'.$user.':loyf_gold');
    $identity_rows[]=loyf7_workers($mode,$user,$object,$keys[$mode],$points);
    // Native AS can replay a queued same-key observation but cannot change committed value.
    foreach(as_get_scheduled_actions(array('hook'=>YOWCL_Core_Rewards::RETRY_HOOK,'status'=>'pending','per_page'=>100),'ids') as $id) {
        if((int)ActionScheduler::store()->fetch_action($id)->get_args()[0]['user_id']===$user){ActionScheduler::runner()->process_action($id,'LOYF-7 concurrent recovery');}
    }
    loyf_balance($user,$points,$points,'Concurrent retry '.$mode);
}
$merged['levelup_enabled']='no'; update_option('loyalty_extra_reviews_gamification_rules',$merged);
// Site-day identity, not server date, admits a separate real login on a distinct site day.
$login=loyf7_user('site_day'); $days=array();
foreach(array('Pacific/Kiritimati','Etc/GMT+12') as $zone) { update_option('timezone_string',$zone); $day=current_time('Y-m-d'); $days[]=$day; $account=get_userdata($login); do_action('wp_login',$account->user_login,$account); do_action('wp_login',$account->user_login,$account); $identity_rows[]=loyf7_row('reward:daily_login:'.$login.':'.$day,3,3); }
loyf_assert($days[0]!==$days[1],'Two site days'); loyf_balance($login,6,6,'Daily day identities'); update_option('timezone_string','UTC');
// Contended review retains exact intent through native AS delivery even after rules are disabled.
$review_user=loyf7_user('review_retry'); $review=wp_insert_comment(array('comment_post_ID'=>$product->get_id(),'user_id'=>$review_user,'comment_type'=>'review','comment_approved'=>1)); $review_key='reward:review:'.$review;
$lock=YOWCL_Points_Lock::acquire($review_user); do_action('comment_post',$review,1); YOWCL_Points_Lock::release($lock); loyf_assert(!YOWCL_Points_Transaction::find($review_key),'Contention creates no event');
$off=$merged; $off['review_enabled']='no'; update_option('loyalty_extra_reviews_gamification_rules',$off);
loyf7_run_jobs(YOWCL_Core_Rewards::RETRY_HOOK,function($args)use($review_key){return $args[0]['key']===$review_key;}); loyf_balance($review_user,7,7,'Frozen review'); $identity_rows[]=loyf7_row($review_key,7,7); update_option('loyalty_extra_reviews_gamification_rules',$merged);
// Actual order status races, then partial projection repair and clamped reversal.
$order_user=loyf7_user('order_race'); $order=loyf_order($order_user,$product); $listeners=array();
foreach($GLOBALS['wp_filter']['woocommerce_order_status_changed']->callbacks[10] as $callback) { $fn=$callback['function']; if(is_array($fn)&&($fn[0] instanceof YOWCL_Actions_Earn_Points || $fn[0] instanceof YOWCL_Actions_Deduct_Points)){ $listeners[]=$callback; remove_action('woocommerce_order_status_changed',$fn,10); } }
try{$order->update_status('processing');}finally{foreach($listeners as $callback){add_action('woocommerce_order_status_changed',$callback['function'],10,$callback['accepted_args']);}}
$identity_rows[]=loyf7_workers('order',$order_user,$order->get_id(),'reward:order:'.$order->get_id(),100);
$partial=loyf7_user('order_partial'); get_userdata($partial)->add_role('subscriber'); $partial_order=loyf_order($partial,$product); $partial_key='reward:order:'.$partial_order->get_id();
$failure=function($step,$key)use($partial_key){if('projecting'===$step && $key===$partial_key){throw new RuntimeException('reward_level_projection_retry_required');}};
add_action('yowcl_reward_test_checkpoint',$failure,10,2); $partial_order->update_status('processing'); remove_action('yowcl_reward_test_checkpoint',$failure,10);
$purchase=loyf7_row($partial_key,100,100); loyf_balance($partial,100,100,'Committed projection fault');
loyf7_run_jobs(YOWCL_Order_Rewards::RETRY_HOOK,function($args)use($partial_order){return (int)$args[0]===$partial_order->get_id();});
$fresh=wc_get_order($partial_order->get_id()); $fresh->read_meta_data(true); loyf_equal(100,(int)$fresh->get_meta('_points_awarded'),'Recovered order marker'); loyf_assert(in_array('subscriber',get_userdata($partial)->roles,true),'Unrelated role retained');
YOWCL_Points_Transaction::apply($partial,-93,0,'cert7:spent:'.$partial); $fresh->update_status('cancelled'); $fresh->update_status('refunded');
loyf_balance($partial,0,0,'Clamped reversal'); $identity_rows[]=loyf7_row($partial_key.':reversal',-7,-100); loyf_assert(in_array('subscriber',get_userdata($partial)->roles,true),'Reversal role preservation');
loyf_equal($purchase['id'],YOWCL_Points_Transaction::find($partial_key)['id'],'Reward row stable'); $identity_rows[]=$purchase;
// Cancellation before a busy award is admitted cannot resurrect obsolete earning on retry.
$obsolete=loyf7_user('obsolete'); $obsolete_order=loyf_order($obsolete,$product); $lock=YOWCL_Points_Lock::acquire($obsolete); $obsolete_order->update_status('processing'); YOWCL_Points_Lock::release($lock); $obsolete_order->update_status('cancelled');
loyf7_run_jobs(YOWCL_Order_Rewards::RETRY_HOOK,function($args)use($obsolete_order){return (int)$args[0]===$obsolete_order->get_id();}); loyf_balance($obsolete,0,0,'Obsolete earning refused'); loyf_assert(!YOWCL_Points_Transaction::find('reward:order:'.$obsolete_order->get_id()),'No obsolete positive event');
// Real COMMIT, injected lost application response: same key recovers the committed outcome.
$unknown=loyf7_user('unknown'); $unknown_key='cert7:unknown:'.$unknown;
$lose=function($step){if('commit_response'===$step){throw new RuntimeException('Lost commit response');}};
add_action('yowcl_transaction_test_checkpoint',$lose); $result=YOWCL_Points_Transaction::apply($unknown,17,17,$unknown_key); remove_action('yowcl_transaction_test_checkpoint',$lose);
loyf_equal('commit_outcome_unknown',$result['code'],'Unknown commit classification'); loyf_balance($unknown,17,17,'Unknown commit persisted once'); loyf_equal('already_applied',YOWCL_Points_Transaction::apply($unknown,17,17,$unknown_key)['status'],'Same-key unknown recovery'); loyf7_row($unknown_key,17,17);
// Producer-level lost outcome retains durable retry and converges its legacy marker without another credit.
$unknown_signup=loyf7_user('unknown_signup'); add_action('yowcl_transaction_test_checkpoint',$lose); do_action('user_register',$unknown_signup); remove_action('yowcl_transaction_test_checkpoint',$lose);
loyf7_run_jobs(YOWCL_Core_Rewards::RETRY_HOOK,function($args)use($unknown_signup){return (int)$args[0]['user_id']===$unknown_signup;}); loyf_balance($unknown_signup,5,5,'Unknown producer credit once'); loyf_equal('1',get_user_meta($unknown_signup,'_yol_signup_awarded',true),'Unknown marker recovery'); $identity_rows[]=loyf7_row('reward:signup:'.$unknown_signup,5,5);
// Actual privileged AJAX clamp/replay/conflict, including committed-but-lost response.
wp_set_current_user(1); $admin=loyf7_user('admin'); $uuid=wp_generate_uuid4(); $request=array('security'=>wp_create_nonce('ajax_nonce'),'user_id'=>$admin,'points'=>'9','operation_id'=>$uuid);
add_action('yowcl_transaction_test_checkpoint',$lose); $response=loyf_ajax('wp_ajax_reward_user_points',$request); remove_action('yowcl_transaction_test_checkpoint',$lose); loyf_equal(false,$response['success'],'Unknown admin response is recoverable');
loyf_equal(true,loyf_ajax('wp_ajax_reward_user_points',$request)['success'],'Admin same-ID retry'); loyf_balance($admin,9,9,'Admin unknown once'); $identity_rows[]=loyf7_row('admin:1:'.$admin.':'.$uuid,9,9);
$request['points']='10'; loyf_equal(false,loyf_ajax('wp_ajax_reward_user_points',$request)['success'],'Changed admin terms denied');
$deduct=array('security'=>wp_create_nonce('ajax_nonce'),'user_id'=>$admin,'points'=>'20','operation_id'=>wp_generate_uuid4()); loyf_equal(true,loyf_ajax('wp_ajax_deduct_user_points',$deduct)['success'],'Admin clamp'); loyf_equal(true,loyf_ajax('wp_ajax_deduct_user_points',$deduct)['success'],'Admin clamp replay'); loyf_balance($admin,0,0,'Nonnegative manual clamp'); $identity_rows[]=loyf7_row('admin:1:'.$admin.':'.$deduct['operation_id'],-9,-9);
// Actual Classic checkout retains same-ID recovery after a committed debit's lost response.
$checkout_user=loyf7_user('checkout_unknown'); YOWCL_Points_Transaction::apply($checkout_user,50,50,'cert7:checkout_seed'); wp_set_current_user($checkout_user);
WC()->initialize_session(); WC()->initialize_cart(); WC()->cart->empty_cart(); WC()->cart->add_to_cart($product->get_id(),1);
$apply=array('loyalty_points_nonce'=>wp_create_nonce('apply_loyalty_points'),'loyalty_points_input'=>'20'); loyf_equal(true,loyf_ajax('wp_ajax_applying_points',$apply)['success'],'Classic selection');
loyf_equal(true,loyf_ajax('wp_ajax_delete_loyalty_coupon',array('loyalty_points_nonce'=>$apply['loyalty_points_nonce']))['success'],'Classic remove'); loyf_balance($checkout_user,50,50,'Apply/remove no debit');
loyf_equal(true,loyf_ajax('wp_ajax_applying_points',$apply)['success'],'Classic reapply'); $attempt=WC()->session->get('yowcl_checkout_id');
add_action('yowcl_transaction_test_checkpoint',$lose); $failed=WC()->checkout()->create_order(array('billing_email'=>'checkout@example.invalid','payment_method'=>'cod')); remove_action('yowcl_transaction_test_checkpoint',$lose);
loyf_assert(is_wp_error($failed),'Lost debit response blocks checkout'); $record=YOWCL_Order_Redemption::record($attempt); loyf_equal('prepared',$record['state'],'Prepared attempt retained'); loyf_balance($checkout_user,30,50,'Unknown debit persisted');
WC()->cart->calculate_totals(); $_POST['yowcl_checkout_id']=$attempt; try{$recovered=WC()->checkout()->create_order(array('billing_email'=>'checkout@example.invalid','payment_method'=>'cod'));}finally{unset($_POST['yowcl_checkout_id']);}
loyf_equal($record['order_id'],$recovered,'Classic same order recovered'); loyf_balance($checkout_user,30,50,'Classic debit once'); $identity_rows[]=loyf7_row('checkout_redeem:'.$attempt,-20,0);
$redeemed=wc_get_order($recovered); add_action('yowcl_transaction_test_checkpoint',$lose); $redeemed->update_status('cancelled'); remove_action('yowcl_transaction_test_checkpoint',$lose); loyf_balance($checkout_user,50,50,'Unknown return persisted');
YOWCL_Points_Transaction::apply($checkout_user,5,5,'cert7:after_return'); YOWCL_Order_Redemption::return_points($recovered); YOWCL_Order_Redemption::return_points($recovered); loyf_balance($checkout_user,55,55,'Return replay preserves later credit'); $identity_rows[]=loyf7_row('checkout_redeem:'.$attempt.':return',20,0);
// A valid selection can become unaffordable before checkout; no value artifact or synthetic return.
WC()->cart->empty_cart(); WC()->cart->add_to_cart($product->get_id(),1); loyf_equal(true,loyf_ajax('wp_ajax_applying_points',$apply)['success'],'Insufficient fixture selection'); $insufficient_attempt=WC()->session->get('yowcl_checkout_id');
YOWCL_Points_Transaction::apply($checkout_user,-50,0,'cert7:concurrent_spend'); $insufficient=WC()->checkout()->create_order(array('billing_email'=>'checkout@example.invalid','payment_method'=>'cod'));
loyf_assert(is_wp_error($insufficient),'Unaffordable checkout refused'); loyf_assert(!YOWCL_Points_Transaction::find('checkout_redeem:'.$insufficient_attempt),'No unfunded debit'); loyf_assert(!YOWCL_Points_Transaction::find('checkout_redeem:'.$insufficient_attempt.':return'),'No synthetic compensation'); loyf_balance($checkout_user,5,55,'Insufficient checkout preserves spend');
// Consumers and public legacy observations cannot write accounting or reinterpret nullable history.
$before_rows=$wpdb->get_results('SELECT * FROM '.YOWCL_Points_Log::table_name().' ORDER BY id',ARRAY_A); $before_meta=$wpdb->get_results("SELECT * FROM {$wpdb->usermeta} WHERE meta_key IN ('user_points','user_earning_points') ORDER BY umeta_id",ARRAY_A);
wp_set_current_user($partial); $history=loyf_ajax('wp_ajax_load_more_points_log',array('security'=>wp_create_nonce('load_more_points_nonce'),'offset'=>0)); loyf_equal(true,$history['success'],'Native My Account history');
foreach($history['data'] as $row){loyf_assert(!isset($row['event_key']) && !isset($row['available_delta']),'Consumer hides internal accounting');}
do_action('yoswc_loyalty_points_reward',$partial,999,999,null); do_action('yoswc_loyalty_points_deduct',$partial,999,999,null);
loyf_equal($before_rows,$wpdb->get_results('SELECT * FROM '.YOWCL_Points_Log::table_name().' ORDER BY id',ARRAY_A),'Observations/readers do not append history'); loyf_equal($before_meta,$wpdb->get_results("SELECT * FROM {$wpdb->usermeta} WHERE meta_key IN ('user_points','user_earning_points') ORDER BY umeta_id",ARRAY_A),'Consumers cannot write value');
require __DIR__ . '/upstream-replay.php';
echo "Retained Free economic certification PASS\n";

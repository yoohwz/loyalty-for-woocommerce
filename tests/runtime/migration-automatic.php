<?php
/** Native scoped automatic redemption, funded recovery and ordinary settings serialization. */
require_once __DIR__.'/assertions.php';
global $wpdb;
if('quote'===($args[0]??'')) {
    if(isset($args[1])){add_filter('query',static function($sql)use($args){static $held=false;if(!$held&&strpos($sql,"loyf_migration_redemption_v1_automatic_enabled")!==false){$held=true;file_put_contents($args[1],'owned');usleep(500000);}return $sql;},PHP_INT_MAX);}
    echo wp_json_encode(YOWCL_Free_Cart::rules());return;
}
wp_set_current_user(1);
$names=array('loyalty_extra_points_rules','loyalty_extra_reviews_gamification_rules','loyalty_points_using_rules','loyalty_points_using_point');
foreach(array('','_before','_resolution','_background','_supersession','_automatic','_automatic_background','_automatic_enabled')as$suffix){$names[]=YOWCL_Free_Migrations::witness('redemption').$suffix;}
$saved=array();foreach($names as$name){$saved[$name]=$wpdb->get_row($wpdb->prepare("SELECT option_value,autoload FROM {$wpdb->options} WHERE option_name=%s",$name),ARRAY_A);}
$user=0;$product=null;$order=null;$id=null;$children=array();
$decode=static function($raw){return maybe_unserialize(maybe_unserialize($raw));};
$patch=static function($name,$fields)use($wpdb,$decode){$raw=YOWCL_Free_Migrations::read($name);$value=array_replace($decode($raw),$fields);$wrapped=is_string(maybe_unserialize($raw));$new=$wrapped?serialize(serialize($value)):serialize($value);$wpdb->update($wpdb->options,array('option_value'=>$new),array('option_name'=>$name));wp_cache_delete('alloptions','options');wp_cache_delete($name,'options');};
$configure=static function($enabled,$callback=null) {
    wp_set_current_user(1);$_POST=array('using_point_rules_nonce'=>wp_create_nonce('save_using_point_rules'));if($enabled){$_POST['loyalty_points_using_point']='1';}
    try {YOWCL_Free_Migrations::redemption_settings(static function()use($enabled,$callback){if($callback){$callback();}update_option('loyalty_points_using_point',$enabled?'yes':'no');YOWCL_Free_Migrations::configure_redemption($enabled);});}
    finally{$_POST=array();}
};
try {
    // An actual current kernel completes a previously unproven readable feature without any POST.
    foreach(array('','_automatic','_automatic_background','_automatic_enabled')as$suffix){delete_option(YOWCL_Free_Migrations::witness('redemption').$suffix);}
    $patch('loyalty_points_using_rules',array('points'=>'10','amount'=>'0.7010'));
    $terms=YOWCL_Free_Migrations::read('loyalty_points_using_rules');$pending=YOWCL_Free_Migrations::read('loyf_migration_redemption_v1_resolution');
    YOWCL_Free_Migrations::schedule();
    foreach(as_get_scheduled_actions(array('hook'=>YOWCL_Free_Migrations::HOOK,'group'=>YOWCL_Free_Migrations::GROUP,'status'=>'pending','per_page'=>30),'ids')as$action){$a=ActionScheduler::store()->fetch_action($action)->get_args();if('redemption'===($a[0]??'')){ActionScheduler_QueueRunner::instance()->process_action($action,'LOYF-29 scoped guard');}}
    loyf_assert(YOWCL_Free_Migrations::ready('redemption'),'Native automatic inactive witness');loyf_equal($terms,YOWCL_Free_Migrations::read('loyalty_points_using_rules'),'Automatic inactive preserves exact rate/wrapper/bounds');loyf_equal($pending,YOWCL_Free_Migrations::read('loyf_migration_redemption_v1_resolution'),'Historical resolution retained');loyf_equal(array(),YOWCL_Free_Cart::rules(),'Inactive new quote');
    $evidence=YOWCL_Free_Migrations::read('loyf_migration_redemption_v1_automatic');
    $configure(true);loyf_assert(YOWCL_Free_Cart::rules(),'Ordinary deliberate configuration enables future redemption');loyf_equal($evidence,YOWCL_Free_Migrations::read('loyf_migration_redemption_v1_automatic'),'Settings do not renew migration evidence');
    $patch('loyalty_extra_points_rules',array('signup_enabled'=>'no'));$patch('loyalty_extra_reviews_gamification_rules',array('levelup_enabled'=>'no'));
    $user=wp_insert_user(array('user_login'=>'loyf29_'.str_replace('-','',wp_generate_uuid4()),'user_email'=>'loyf29@example.invalid','user_pass'=>'disposable-only','role'=>'customer'));loyf_assert(!is_wp_error($user),'Fixture customer');
    YOWCL_Points_Transaction::apply($user,100,100,'loyf29:guard:seed:'.$user);wp_set_current_user($user);
    if(!WC()->session){WC()->initialize_session();}if(!WC()->cart){WC()->initialize_cart();}
    $product=new WC_Product_Simple();$product->set_name('LOYF29 guard');$product->set_regular_price('100');$product->set_virtual(true);$product->set_status('publish');$product->save();
    WC()->cart->empty_cart();WC()->cart->add_to_cart($product->get_id());WC()->cart->calculate_totals();$id=wp_generate_uuid4();YOWCL_Free_Cart::apply('20',$id);WC()->cart->calculate_totals();
    $order_id=WC()->checkout()->create_order(array('billing_email'=>'loyf29@example.invalid','payment_method'=>'cod'));loyf_assert(!is_wp_error($order_id),'Native funded Classic order');$order=wc_get_order($order_id);loyf_balance($user,80,100,'Funded debit once');
    $configure(false);wp_set_current_user($user);$before=loyf_rows($user);
    loyf_equal(array(),YOWCL_Free_Cart::rules(),'Scoped inactive denies new rules');loyf_equal($id,YOWCL_Free_Cart::selection()['id'],'Funded selection survives future inactivity');
    $reply=loyf_ajax('wp_ajax_applying_points',array('loyalty_points_nonce'=>wp_create_nonce('apply_loyalty_points'),'loyalty_points_input'=>'10'));loyf_equal(false,$reply['success'],'Registered Classic new selection denied');
    $request=new WP_REST_Request('POST','/wc/store/v1/cart/extensions');$request->set_header('Nonce',wp_create_nonce('wc_store_api'));$request->set_body_params(array('namespace'=>YOWCL_Free_Blocks::NS,'data'=>array('action'=>'apply','points'=>'10','operation_id'=>wp_generate_uuid4())));$reply=rest_do_request($request);loyf_equal('loyf_redemption_rejected',$reply->get_data()['code'],'Native Store API new selection denied by scoped guard');
    $data=YOWCL_Free_Blocks::data();loyf_equal(false,$data['enabled'],'Registered Blocks data is inactive');loyf_equal(20,$data['selected'],'Funded Blocks selection is retained');
    loyf_balance($user,80,100,'Denied selections create no value');loyf_equal($before,loyf_rows($user),'Denied selections create no history');
    YOWCL_Order_Redemption::commit($order);$order->update_status('cancelled');loyf_balance($user,100,100,'Funded return still works');$returned=loyf_rows($user);YOWCL_Order_Redemption::return_points($order_id);loyf_equal($returned,loyf_rows($user),'Return replay adds no history/value');
    // Both lock orders for a quote versus ordinary scoped settings writes.
    $configure(true);$gate=tempnam(sys_get_temp_dir(),'loyf29-reader-');unlink($gate);$log=tempnam(sys_get_temp_dir(),'loyf29-reader-log-');
    $process=proc_open(array(PHP_BINARY,getenv('LOYF_WP_CLI_PHAR'),'--path='.ABSPATH,'eval-file',__FILE__,'quote',$gate,'--quiet'),array(0=>array('file','/dev/null','r'),1=>array('file',$log,'w'),2=>array('file',$log,'a')),$pipes);$children[]=array($process,$gate,$log);
    $until=microtime(true)+10;while(!file_exists($gate)&&microtime(true)<$until){usleep(10000);}loyf_assert(file_exists($gate),'Independent quote owns read scope');
    $configure(false,static function()use($patch){$patch('loyalty_points_using_rules',array('amount'=>'999'));});loyf_equal(0,proc_close($process),'Reader-first process');$children=array();$quoted=json_decode(file_get_contents($log),true);loyf_equal(0.701,(float)$quoted['amount'],'Earlier quote sees original enabled rate');unlink($gate);unlink($log);loyf_equal(array(),YOWCL_Free_Cart::rules(),'Completed disable cannot expose the newly stored rate');
    $configure(true);$gate=tempnam(sys_get_temp_dir(),'loyf29-writer-');unlink($gate);$log=tempnam(sys_get_temp_dir(),'loyf29-writer-log-');$process=null;
    $configure(false,static function()use($patch,$gate,$log,&$process){
        $patch('loyalty_points_using_rules',array('amount'=>'888'));
        $exec="file_put_contents('".str_replace("'","\\'",$gate)."','starting');";
        $process=proc_open(array(PHP_BINARY,getenv('LOYF_WP_CLI_PHAR'),'--exec='.$exec,'--path='.ABSPATH,'eval-file',__FILE__,'quote','--quiet'),array(0=>array('file','/dev/null','r'),1=>array('file',$log,'w'),2=>array('file',$log,'a')),$pipes);
        $until=microtime(true)+10;while(!file_exists($gate)&&microtime(true)<$until){usleep(10000);}loyf_assert(file_exists($gate),'Independent quote started while writer owns transaction');usleep(200000);
    });$children[]=array($process,$gate,$log);loyf_equal(0,proc_close($process),'Writer-first process');$children=array();loyf_equal(array(),json_decode(file_get_contents($log),true),'Later quote never sees an enabled transient new rate');unlink($gate);unlink($log);
    loyf_equal($evidence,YOWCL_Free_Migrations::read('loyf_migration_redemption_v1_automatic'),'Immutable scoped evidence survives ordinary edits and both lock orders');
    echo 'Automatic guard native PASS '.getenv('LOYF_STORAGE').': raw terms/old intent, actual Classic/Store API/Blocks denial, funded finalize/return/replay, ordinary enable/disable and two independent quote/settings lock orders. No migration POST.' . "\n";
} finally {
    foreach($children as$child){proc_terminate($child[0]);proc_close($child[0]);foreach(array($child[1],$child[2])as$file){if(file_exists($file)){unlink($file);}}}
    if(WC()->cart){WC()->cart->empty_cart();}YOWCL_Free_Cart::clear();if($order){$order->delete(true);}if($product){$product->delete(true);}if($id){delete_option('yowcl_order_redemption_'.$id);}
    if($user&&!is_wp_error($user)){require_once ABSPATH.'wp-admin/includes/user.php';$wpdb->delete($wpdb->prefix.'yo_loyalty_points_log',array('user_id'=>$user));wp_delete_user($user);}
    foreach($saved as$name=>$row){$wpdb->delete($wpdb->options,array('option_name'=>$name));if($row){$wpdb->insert($wpdb->options,array_merge(array('option_name'=>$name),$row));}wp_cache_delete($name,'options');}
    wp_cache_delete('alloptions','options');wp_cache_delete('notoptions','options');wp_set_current_user(1);
}

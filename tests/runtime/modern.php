<?php
require __DIR__ . '/assertions.php';
global $wpdb;
function loyf8_api($method,$route,$body=array()) {
    $_POST=array(); $request=new WP_REST_Request($method,'/wc/store/v1/'.$route); $request->set_header('Nonce',wp_create_nonce('wc_store_api')); $request->set_body_params($body); return rest_do_request($request);
}
function loyf8_ok($response) { loyf_assert($response->get_status()<300,'Native Store API: '.wp_json_encode($response->get_data())); return json_decode(wp_json_encode($response->get_data()),true); }
function loyf8_user($name) {
    $rules=get_option('loyalty_extra_points_rules'); $off=$rules; $off['signup_enabled']='no'; update_option('loyalty_extra_points_rules',$off);
    $user=wp_insert_user(array('user_login'=>'modern_'.$name,'user_email'=>'modern_'.$name.'@example.invalid','user_pass'=>'disposable-only','role'=>'customer')); loyf_assert(!is_wp_error($user),'Native modern customer'); update_option('loyalty_extra_points_rules',$rules); return (int)$user;
}
$merged=get_option('loyalty_extra_reviews_gamification_rules'); $merged['levelup_enabled']='no'; update_option('loyalty_extra_reviews_gamification_rules',$merged);
$hpos='hpos'===getenv('LOYF_STORAGE');
loyf_equal($hpos,Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(),'Authoritative storage'); loyf_equal('no',get_option('woocommerce_custom_orders_table_data_sync_enabled'),'Sync disabled');
foreach(array('custom_order_tables','cart_checkout_blocks') as $feature){loyf_assert(in_array(YOSWC_LOYALTY_PLUGIN_BASENAME,Automattic\WooCommerce\Utilities\FeaturesUtil::get_compatible_plugins_for_feature($feature)['compatible'],true),'Native compatibility declaration '.$feature);}
$product=new WC_Product_Simple(); $product->set_name('Modern redemption'); $product->set_regular_price('100'); $product->set_virtual(true); $product->set_status('publish'); $product->save();
$address=array('first_name'=>'Native','last_name'=>'Customer','address_1'=>'1 Test Road','city'=>'San Francisco','state'=>'CA','postcode'=>'94103','country'=>'US','email'=>'modern@example.invalid','phone'=>'4155550100');
update_option('woocommerce_cod_settings',array('enabled'=>'yes')); WC()->payment_gateways=new WC_Payment_Gateways();
$body=array('billing_address'=>$address,'shipping_address'=>$address,'payment_method'=>'cod');
$pay=function($context,&$result){if('cod'===$context->payment_method){$result->set_status('success');$result->set_redirect_url($context->order->get_checkout_order_received_url());}};
add_action('woocommerce_rest_checkout_process_payment_with_context',$pay,1,2);
foreach(array('classic','store') as $adapter) {
    $user=loyf8_user($adapter); wp_set_current_user($user); YOWCL_Points_Transaction::apply($user,100,100,'modern:seed:'.$user);
    if(!WC()->session){WC()->initialize_session();} if(!WC()->cart){WC()->initialize_cart();} WC()->cart->empty_cart(); WC()->cart->add_to_cart($product->get_id()); WC()->cart->calculate_totals();
    // Selection crosses adapters and remains the same economic UUID.
    $id=wp_generate_uuid4(); $apply=array('namespace'=>YOWCL_Free_Blocks::NS,'data'=>array('action'=>'apply','points'=>'20','operation_id'=>$id));
    $cart=loyf8_ok(loyf8_api('POST','cart/extensions',$apply)); loyf_equal(20,$cart['extensions'][YOWCL_Free_Blocks::NS]['selected'],'Native extension selection'); loyf_balance($user,100,100,'Selection no debit');
    loyf8_ok(loyf8_api('POST','cart/extensions',$apply)); loyf_equal($id,WC()->session->get('yowcl_checkout_id'),'Apply same operation replay');
    $conflict=$apply; $conflict['data']['points']='21'; loyf_assert(loyf8_api('POST','cart/extensions',$conflict)->get_status()>=400,'Changed same-ID terms denied');
    $replacement=$apply;$id=wp_generate_uuid4();$replacement['data']['operation_id']=$id;$replacement['data']['points']='30';$changed=loyf8_ok(loyf8_api('POST','cart/extensions',$replacement));loyf_equal(30,$changed['extensions'][YOWCL_Free_Blocks::NS]['selected'],'Explicit new selection changes points');loyf_balance($user,100,100,'Selection change no debit');
    loyf8_ok(loyf8_api('POST','cart/extensions',array('namespace'=>YOWCL_Free_Blocks::NS,'data'=>array('action'=>'remove','operation_id'=>$id)))); loyf_balance($user,100,100,'Remove no debit');
    $nonce=wp_create_nonce('apply_loyalty_points'); loyf_equal(true,loyf_ajax('wp_ajax_applying_points',array('loyalty_points_nonce'=>$nonce,'loyalty_points_input'=>'20'))['success'],'Classic selection for both adapters'); $id=WC()->session->get('yowcl_checkout_id');
    $selection=YOWCL_Free_Cart::selection(); $rules=get_option('loyalty_points_using_rules'); $changed=$rules; $changed['amount']=99; update_option('loyalty_points_using_rules',$changed); WC()->cart->calculate_totals(); loyf_equal(null,YOWCL_Free_Cart::selection(),'Rule drift requires reapply'); loyf_balance($user,100,100,'Rule drift no debit'); update_option('loyalty_points_using_rules',$rules); WC()->cart->calculate_totals(); loyf_equal($selection['id'],YOWCL_Free_Cart::selection()['id'],'Original selection recovery');
    foreach(array('owner'=>'wrong-session','currency'=>'EUR') as $field=>$wrong){$bad=$selection;$bad[$field]=$wrong;WC()->session->set('loyf_funded_selection',$bad);WC()->cart->calculate_totals();loyf_equal(null,YOWCL_Free_Cart::selection(),'Selection ownership/currency drift held');loyf_balance($user,100,100,'Ownership/currency drift no value');}WC()->session->set('loyf_funded_selection',$selection);WC()->cart->calculate_totals();
    if('classic'===$adapter) {$order_id=WC()->checkout()->create_order(array('billing_email'=>$address['email'],'payment_method'=>'cod')); loyf_assert(!is_wp_error($order_id),'Classic create order');}
    else {
        $draft=loyf8_ok(loyf8_api('GET','checkout')); $order_id=$draft['order_id']; loyf_assert(!YOWCL_Order_Redemption::record($id),'GET draft not frozen'); loyf_balance($user,100,100,'Draft no debit');
        $failure=function($step){if('before_finalize'===$step){throw new RuntimeException('Modern finalization fault');}};
        add_action('yowcl_order_redemption_test_checkpoint',$failure); $failed=loyf8_api('POST','checkout',$body); remove_action('yowcl_order_redemption_test_checkpoint',$failure);
        $bound=YOWCL_Order_Redemption::record($id)['order_id']; if($order_id){loyf_equal($order_id,$bound,'Existing draft bound');} $order_id=$bound;
        loyf_assert($failed->get_status()>=400,'Native POST failure'); loyf_balance($user,80,100,'Unknown finalization debit once'); loyf_equal('prepared',YOWCL_Order_Redemption::record($id)['state'],'Prepared recovery retained');
        $original=new WC_Order($order_id); $items=array_map(function($item){return array($item->get_id(),$item->get_quantity(),$item->get_total());},$original->get_items());
        $key=array_key_first(WC()->cart->get_cart()); loyf8_ok(loyf8_api('POST','cart/update-item',array('key'=>$key,'quantity'=>2)));
        foreach(array('GET','PUT') as $method){loyf_assert(loyf8_api($method,'checkout',$body)->get_status()>=400,'Frozen cart mutation denied'); $fresh=new WC_Order($order_id); loyf_equal($items,array_map(function($item){return array($item->get_id(),$item->get_quantity(),$item->get_total());},$fresh->get_items()),'Frozen items preserved');}
        loyf8_ok(loyf8_api('POST','cart/update-item',array('key'=>$key,'quantity'=>1))); $retry=loyf8_ok(loyf8_api('POST','checkout',$body)); loyf_equal($order_id,$retry['order_id'],'Same Store order recovery');
    }
    loyf_balance($user,80,100,'Funded adapter debit once'); YOWCL_Order_Redemption::commit(wc_get_order($order_id)); loyf_balance($user,80,100,'Cross-adapter primitive replay');
    $order=wc_get_order($order_id); loyf_assert(strpos($order->get_edit_order_url(),$hpos?'wc-orders':'post.php')!==false,'Storage-native admin order URL'); loyf_equal($id,$order->get_meta(YOWCL_Order_Redemption::META),'Shared attempt identity');
    if($hpos){loyf_equal('',$wpdb->get_var($wpdb->prepare("SELECT meta_value FROM {$wpdb->postmeta} WHERE post_id=%d AND meta_key=%s",$order_id,YOWCL_Order_Redemption::META))??'','No CPT mirror authority');}
    $order->update_status('processing'); $order->update_status('completed'); loyf_balance($user,180,200,'Purchase once both stores/adapters'); $order->update_status('cancelled'); $order->update_status('refunded'); loyf_balance($user,100,100,'Return and reversal once');
    YOWCL_Points_Transaction::apply($user,5,5,'modern:later:'.$user); YOWCL_Order_Redemption::return_points($order_id); loyf_balance($user,105,105,'Return preserves later credit');
    $legacy=loyf_order($user,$product);$legacy->update_meta_data('_used_points',20);$legacy->save();$legacy->update_status('cancelled');loyf_balance($user,105,105,'Legacy marker cannot credit');loyf_equal('yes',wc_get_order($legacy->get_id())->get_meta('_yowcl_legacy_return_review'),'Legacy manual review');
    WC()->cart->empty_cart();WC()->cart->add_to_cart($product->get_id());WC()->cart->calculate_totals();$new=wp_generate_uuid4();$unfunded=array('namespace'=>YOWCL_Free_Blocks::NS,'data'=>array('action'=>'apply','points'=>'20','operation_id'=>$new));loyf8_ok(loyf8_api('POST','cart/extensions',$unfunded));YOWCL_Points_Transaction::apply($user,-100,0,'modern:spend:'.$user);WC()->cart->calculate_totals();
    $result='classic'===$adapter?WC()->checkout()->create_order(array('billing_email'=>$address['email'],'payment_method'=>'cod')):loyf8_api('POST','checkout',$body);
    loyf_assert('classic'===$adapter?is_wp_error($result):$result->get_status()>=400,'Concurrent spend refuses funded discount');loyf_assert(!YOWCL_Points_Transaction::find('checkout_redeem:'.$new),'No unfunded debit');loyf_assert(!YOWCL_Points_Transaction::find('checkout_redeem:'.$new.':return'),'No fabricated return');
    $premium=$unfunded;$premium['data']['product']=100;loyf_assert(loyf8_api('POST','cart/extensions',$premium)->get_status()>=400,'Premium extension request denied');loyf_balance($user,5,105,'Unsupported request no value');
}
// Independent native POSTs share the selection mutex before any draft order exists.
$user=loyf8_user('contention');wp_set_current_user($user);YOWCL_Points_Transaction::apply($user,100,100,'modern:contention:seed');WC()->cart->empty_cart();WC()->cart->add_to_cart($product->get_id());WC()->cart->calculate_totals();
$id=wp_generate_uuid4();loyf8_ok(loyf8_api('POST','cart/extensions',array('namespace'=>YOWCL_Free_Blocks::NS,'data'=>array('action'=>'apply','points'=>'20','operation_id'=>$id))));WC()->session->set('store_api_draft_order',null);
$file=tempnam(sys_get_temp_dir(),'loyf8-workers-');$session=array();foreach(array('yowcl_checkout_id','loyf_funded_selection','yoswc_loyalty_applied_points','yoswc_loyalty_discount_amount') as $key){$session[$key]=WC()->session->get($key);}file_put_contents($file,wp_json_encode(array('user'=>$user,'product'=>$product->get_id(),'session'=>$session,'body'=>$body,'id'=>$id,'barrier'=>$file)));
$workers=array();foreach(array(0,1) as $index){$env=getenv();$env['LOYF8_WORKER_FIXTURE']=$file;$env['LOYF8_WORKER_INDEX']=(string)$index;$pipes=array();$process=proc_open(array(PHP_BINARY,getenv('LOYF_WP_CLI_PHAR'),'--path='.ABSPATH,'eval-file',__DIR__.'/modern-worker.php','--quiet'),array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes,null,$env);loyf_assert(is_resource($process),'Native checkout process');fclose($pipes[0]);$workers[]=array($process,$pipes);}
try{foreach($workers as $worker){$out=stream_get_contents($worker[1][1]);$err=stream_get_contents($worker[1][2]);fclose($worker[1][1]);fclose($worker[1][2]);loyf_equal(0,proc_close($worker[0]),'Native checkout worker '.$err.' '.$out);}
wp_cache_delete($user,'user_meta');loyf_balance($user,80,100,'Contended original debit once');loyf_equal('prepared',YOWCL_Order_Redemption::record($id)['state'],'Contended original prepared');
$env['LOYF8_WORKER_INDEX']='2';$pipes=array();$process=proc_open(array(PHP_BINARY,getenv('LOYF_WP_CLI_PHAR'),'--path='.ABSPATH,'eval-file',__DIR__.'/modern-worker.php','--quiet'),array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes,null,$env);loyf_assert(is_resource($process),'Fresh native recovery process');fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);loyf_equal(0,proc_close($process),'Native fresh recovery '.$err);$recovered=json_decode($out,true)['data'];
}finally{foreach(array('','.owned','.denied')as$suffix){if(file_exists($file.$suffix)){unlink($file.$suffix);}}}
wp_cache_delete($user,'user_meta');loyf_balance($user,80,100,'Concurrent POST debit once');$record=YOWCL_Order_Redemption::record($id);loyf_equal('active',$record['state'],'Concurrent original recovered');loyf_equal($record['order_id'],$recovered['order_id'],'Concurrent same-order recovery');loyf_balance($user,80,100,'Concurrent recovery no second debit');
wp_set_current_user(0);loyf_assert(loyf8_api('POST','cart/extensions',array('namespace'=>YOWCL_Free_Blocks::NS,'data'=>array('action'=>'apply','points'=>'1','operation_id'=>wp_generate_uuid4())))->get_status()>=400,'Guest denied');
echo 'Native Classic/Store API × '.getenv('LOYF_STORAGE').' PASS Woo'.WC_VERSION.' WP'.get_bloginfo('version')." sync-off\n";

// First Purchase policy across the real Classic create_order and Store API draft/POST origins.
$first_rules=get_option(YOWCL_Free_First_Purchase::RULES,null);$first_epoch=get_option(YOWCL_Free_First_Purchase::WITNESS,null);
try {
    foreach(array('classic','store') as $adapter) {
        wp_set_current_user(1);YOWCL_Free_First_Purchase::save(true,17);$cutoff=YOWCL_Free_First_Purchase::configuration()['epoch']['cutoff'];while(time()<=$cutoff){usleep(100000);}
        $user=loyf8_user('first_'.$adapter);wp_set_current_user($user);WC()->cart->empty_cart();WC()->cart->add_to_cart($product->get_id());WC()->cart->calculate_totals();WC()->session->set('store_api_draft_order',null);
        if('classic'===$adapter){$order_id=WC()->checkout()->create_order(array('billing_email'=>$address['email'],'payment_method'=>'cod'));loyf_assert(!is_wp_error($order_id),'First Classic create');}
        else{$draft=loyf8_ok(loyf8_api('GET','checkout'));loyf_assert(!YOWCL_Points_Transaction::find('reward:first_purchase:'.$user),'Store draft no first value');$posted=loyf8_ok(loyf8_api('POST','checkout',$body));$order_id=$posted['order_id'];}
        $order=wc_get_order($order_id);$order->update_status('processing');$order->update_status('completed');do_action('woocommerce_payment_complete',$order_id);$row=YOWCL_Points_Transaction::find('reward:first_purchase:'.$user);loyf_assert(is_array($row),'Native origin First award');loyf_equal(17,(int)$row['available_delta'],'Native origin fixed bonus');loyf_equal($order_id,(int)$row['order_id'],'Native origin winner');
        wp_set_current_user(1);YOWCL_Free_First_Purchase::save(false,17);
    }
    // Store API draft created before effective enable stays excluded after real POST finalization.
    $user=loyf8_user('first_old_draft');wp_set_current_user($user);WC()->cart->empty_cart();WC()->cart->add_to_cart($product->get_id());WC()->cart->calculate_totals();WC()->session->set('store_api_draft_order',null);$draft=loyf8_ok(loyf8_api('GET','checkout'));$draft_id=(int)$draft['order_id'];if(!$draft_id){$pause=function($order)use(&$draft_id){$draft_id=$order->get_id();throw new RuntimeException('First pre-enable persisted draft pause');};add_action('woocommerce_store_api_checkout_update_order_from_request',$pause,1);try{$failed=loyf8_api('POST','checkout',$body);loyf_assert($failed->get_status()>=400,'Native draft pause');}finally{remove_action('woocommerce_store_api_checkout_update_order_from_request',$pause,1);}}$old_draft=wc_get_order($draft_id);loyf_assert($old_draft instanceof WC_Order,'Native persisted draft');$date=$old_draft->get_date_created()->getTimestamp();while(time()<=$date){usleep(100000);}wp_set_current_user(1);YOWCL_Free_First_Purchase::save(true,17);wp_set_current_user($user);$posted=loyf8_ok(loyf8_api('POST','checkout',$body));$order=wc_get_order($posted['order_id']);loyf_equal($draft_id,$order->get_id(),'Same pre-enable draft finalized');$order->update_status('processing');loyf_equal(null,YOWCL_Points_Transaction::find('reward:first_purchase:'.$user),'Native pre-enable Store draft denied');
    echo "Native First Purchase Classic/Store API origins PASS\n";
} finally {foreach(array(YOWCL_Free_First_Purchase::RULES=>$first_rules,YOWCL_Free_First_Purchase::WITNESS=>$first_epoch)as$name=>$value){if(null===$value){delete_option($name);}else{update_option($name,$value);}}wp_set_current_user(0);}

<?php
if (!defined('LOYF_RUNTIME_DISPOSABLE')) { define('LOYF_RUNTIME_DISPOSABLE', true); }
require_once __DIR__.'/assertions.php';
global $wpdb;
if (!defined('DOING_AJAX')) { define('DOING_AJAX', true); }
add_filter('wp_die_handler', static function(){ return static function($message){ throw new RuntimeException(is_wp_error($message)?$message->get_error_message():(string)$message); }; });
function edition_snapshot(){
 global $wpdb;
 // Native wp_login/user_register and Woo's qualifying-order read refresh these
 // presentation/activity caches. All Loyalty, role and other raw meta stays exact.
 $excluded=array('dismissed_wp_pointers','wc_last_active','wc_order_count_'.rtrim($wpdb->get_blog_prefix(),'_'));
 $where=implode(',',array_map(static function($key)use($wpdb){return $wpdb->prepare('%s',$key);},$excluded));
 $markers=array();foreach(array('_used_points','_yo','_yowcl','_yoswc','_loyf','_loyalty','first_purchase') as $prefix){$markers[]=$wpdb->prepare('meta_key LIKE %s',$wpdb->esc_like($prefix).'%');}$marker_sql=implode(' OR ',$markers);
 $order_meta=$wpdb->get_results("SELECT * FROM {$wpdb->postmeta} WHERE $marker_sql ORDER BY meta_id",ARRAY_A);
 $hpos_meta=array();if('hpos'===getenv('LOYF_STORAGE')){$hpos_meta=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_orders_meta WHERE $marker_sql ORDER BY id",ARRAY_A);}
 $names=array('loyalty_levels_roles','loyalty_levels_rules','loyalty_points_earning_status','loyalty_points_deduction_status','loyalty_points_earning_rules','loyalty_points_using_rules','loyalty_extra_points_rules','loyalty_extra_reviews_gamification_rules','loyalty_customization_my_account','loyalty_customization_membercard','woocommerce_yowcl_loyalty_points_reward_settings','woocommerce_yowcl_loyalty_points_deduct_settings','woocommerce_yowcl_loyalty_level_update_settings');
 $options=implode(',',array_map(static function($name)use($wpdb){return $wpdb->prepare('%s',$name);},$names));
 return array('rows'=>$wpdb->get_results("SELECT * FROM {$wpdb->prefix}yo_loyalty_points_log ORDER BY id",ARRAY_A),'meta'=>$wpdb->get_results("SELECT * FROM {$wpdb->usermeta} WHERE meta_key NOT IN ($where) ORDER BY umeta_id",ARRAY_A),'roles'=>get_option($wpdb->prefix.'user_roles'),'order_meta'=>$order_meta,'hpos_meta'=>$hpos_meta,'comment_meta'=>$wpdb->get_results("SELECT m.* FROM {$wpdb->commentmeta} m INNER JOIN {$wpdb->comments} c ON c.comment_ID=m.comment_id WHERE c.comment_type IN ('review','') ORDER BY m.meta_id",ARRAY_A),'options'=>$wpdb->get_results("SELECT option_name,option_value,autoload FROM {$wpdb->options} WHERE option_name IN ($options) ORDER BY option_name",ARRAY_A));
}
loyf_assert('no'===get_option('woocommerce_custom_orders_table_data_sync_enabled'),'Edition synchronization disabled');
$expected='hpos'===getenv('LOYF_STORAGE');loyf_equal($expected,Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled(),'Native edition storage owner');
$phase=getenv('LOYF13_EDITION_PHASE');$file=getenv('LOYF13_EDITION_FIXTURE');
if('free-seed'===$phase){
 loyf_assert(class_exists('YOSWC_Loyalty',false)&&!class_exists('YOWCL_Loyalty',false),'Separate Free seed owner');
 wp_set_current_user(1);$fixture=json_decode(file_get_contents(__DIR__.'/../fixtures/free-1.2.2.json'),true);
 foreach($fixture['options'] as $n=>$value){update_option($n,$value);}add_role('loyf_gold','Fixture Gold',array('read'=>true));
 foreach(YOWCL_Free_Migrations::features() as $f){if(!YOWCL_Free_Migrations::ready($f)){$spec=YOWCL_Free_Migrations::preview($f,'legacy');YOWCL_Free_Migrations::resolve($f,'legacy',YOWCL_Free_Migrations::resolution_fingerprint($f,$spec),wp_create_nonce('loyf_resolve_'.$f));}}
 $_POST=array('extra_points_settings_nonce'=>wp_create_nonce('save_extra_points_settings_action'),'loyalty_extra_signup_points'=>'5','loyalty_extra_login_points'=>'3','loyalty_extra_review_points'=>'7','loyalty_extra_levelup_loyf_gold'=>'11');(new YOSWC_Loyalty_Settings_Extra_Points())->save_extra_points_settings();$_POST=array();
 YOWCL_Free_First_Purchase::save(true,25);YOWCL_Free_Referral::save(true,17);$cutoff=YOWCL_Free_First_Purchase::configuration()['epoch']['cutoff'];while(time()<=$cutoff){usleep(100000);}
 $run=substr(wp_generate_uuid4(),0,8);
 $ref=(int)wp_insert_user(array('user_login'=>'edition_referrer_'.$run,'user_email'=>'edition-referrer-'.$run.'@example.invalid','user_pass'=>'disposable-only','role'=>'customer'));
 $buyer=(int)wp_insert_user(array('user_login'=>'edition_buyer_'.$run,'user_email'=>'edition-buyer-'.$run.'@example.invalid','user_pass'=>'disposable-only','role'=>'customer'));
 $manual=array('security'=>wp_create_nonce('ajax_nonce'),'user_id'=>$buyer,'points'=>'50','operation_id'=>wp_generate_uuid4());loyf_equal(true,loyf_ajax('wp_ajax_reward_user_points',$manual)['success'],'Native Free manual seed');
 $product=new WC_Product_Simple();$product->set_name('Edition continuity');$product->set_regular_price(100);$product->set_virtual(true);$product->save();
 $review=wp_insert_comment(array('comment_post_ID'=>$product->get_id(),'user_id'=>$buyer,'comment_type'=>'review','comment_approved'=>1,'comment_content'=>'Synthetic edition review'));do_action('comment_post',$review,1,get_comment($review,ARRAY_A));
 do_action('wp_login','edition_buyer_'.$run,get_userdata($buyer));
 wp_set_current_user($buyer);WC()->initialize_session();WC()->initialize_cart();WC()->cart->add_to_cart($product->get_id());WC()->cart->calculate_totals();$_COOKIE['yowcl_ref']=YOWCL_Helper_Referrals::ensure_user_token($ref);
 $apply=array('loyalty_points_nonce'=>wp_create_nonce('apply_loyalty_points'),'loyalty_points_input'=>'20');loyf_equal(true,loyf_ajax('wp_ajax_applying_points',$apply)['success'],'Native Free selection');
 $order_id=WC()->checkout()->create_order(array('billing_email'=>'edition-buyer-'.$run.'@example.invalid','payment_method'=>'cod'));loyf_assert(!is_wp_error($order_id),'Native Free checkout');$o=wc_get_order($order_id);$o->update_status('processing');$o->update_status('completed');do_action('woocommerce_payment_complete',$order_id);unset($_COOKIE['yowcl_ref']);
 wp_set_current_user(1);$deduct=array('security'=>wp_create_nonce('ajax_nonce'),'user_id'=>$buyer,'points'=>'3','operation_id'=>wp_generate_uuid4());loyf_equal(true,loyf_ajax('wp_ajax_deduct_user_points',$deduct)['success'],'Native Free deduct');
 $tools=new YOSWC_Loyalty_Settings_Tools();$identity=new ReflectionMethod($tools,'import_identity');$identity->setAccessible(true);$id=$identity->invoke($tools);$_POST=array('loyf_start_new_import_nonce'=>wp_create_nonce('loyf_start_new_import'),'previous_operation_id'=>$id);$rotate=new ReflectionMethod($tools,'start_new_import');$rotate->setAccessible(true);$rotate->invoke($tools);$_POST=array();$id=$identity->invoke($tools);$csv=tempnam(sys_get_temp_dir(),'loyf13-edition-csv-');$available=(int)get_user_meta($buyer,'user_points',true)+5;$earning=(int)get_user_meta($buyer,'user_earning_points',true)+5;file_put_contents($csv,"user_id,user_points,user_earning_points\n$buyer,$available,$earning\n");
 $_FILES=array('import_file'=>array('name'=>'edition.csv','tmp_name'=>$csv));$_POST=array('wc_loyalty_import_nonce'=>wp_create_nonce('wc_loyalty_import_action'),'operation_id'=>$id);$import=new ReflectionMethod($tools,'import_csv');$import->setAccessible(true);ob_start();$import->invoke($tools);ob_end_clean();unlink($csv);$_FILES=$_POST=array();
 $events=$wpdb->get_results("SELECT action,event_key FROM {$wpdb->prefix}yo_loyalty_points_log WHERE event_key IS NOT NULL AND user_id IN ($buyer,$ref) ORDER BY id",ARRAY_A);$actions=array_column($events,'action');
 foreach(array('sign_up_reward','daily_login_reward','review_reward','level_up_reward','order_reward','first_purchase_reward','referral_link_referrer_reward','admin_reward','admin_deduct','points_import','points_used') as $action){loyf_assert(in_array($action,$actions,true),'Native event missing '.$action);}
 file_put_contents($file,wp_json_encode(array('buyer_login'=>get_userdata($buyer)->user_login,'buyer'=>$buyer,'referrer'=>$ref,'review'=>$review,'order'=>$order_id,'snapshot'=>edition_snapshot()),JSON_PRETTY_PRINT));echo "Native Free positive edition fixture PASS (registered rewards, Classic funded checkout, privileged manual AJAX, native guarded CSV ingestion).\n";return;
}
$data=json_decode(file_get_contents($file),true);$before=edition_snapshot();
if(in_array($phase,array('premium-replay','premium-return-replay'),true)){loyf_assert(class_exists('YOWCL_Loyalty',false)&&!class_exists('YOSWC_Loyalty',false),'Separate Premium owner');loyf_assert(YOWCL_Premium_Gate::is_active(),'Native Premium gate enabled by explicit simulated validator');}
else{loyf_assert(class_exists('YOSWC_Loyalty',false)&&!class_exists('YOWCL_Loyalty',false),'Separate Free owner');}
$u=$data['buyer'];$o=wc_get_order($data['order']);do_action('user_register',$u);do_action('wp_login',$data['buyer_login'],get_userdata($u));do_action('comment_post',$data['review'],1,get_comment($data['review'],ARRAY_A));do_action('woocommerce_order_status_completed',$o->get_id(),$o);do_action('woocommerce_payment_complete',$o->get_id());
loyf_equal($data['snapshot']['rows'],edition_snapshot()['rows'],'Cross-edition replay exact log rows/IDs/NULLs');
loyf_equal($data['snapshot']['meta'],edition_snapshot()['meta'],'Cross-edition replay exact user meta/roles/markers');loyf_equal($data['snapshot']['roles'],edition_snapshot()['roles'],'Cross-edition physical roles');
foreach(array('order_meta','hpos_meta') as $surface){
 $actual=edition_snapshot()[$surface];$expected=$data['snapshot'][$surface];
 if('premium-replay'===$phase){
  // Actual pinned Premium records these disabled/no-op advanced assessments.
  // Existing raw rows/IDs/terms must remain exact; no other addition is admitted.
  $key='hpos_meta'===$surface?'id':'meta_id';$ids=array_column($expected,$key);$existing=array();$added=array();
  $terminal=array('_yowcl_milestone_checked'=>'yes','_yowcl_spending_milestones_checked'=>'yes','_yowcl_high_cart_value_awarded'=>'disabled','_yowcl_payment_method_bonus_awarded'=>'disabled','_yowcl_inactivity_return_awarded'=>'disabled');
  foreach($actual as $row){if(in_array($row[$key],$ids,true)){$existing[]=$row;continue;}$order_key='hpos_meta'===$surface?'order_id':'post_id';loyf_equal($data['order'],(int)$row[$order_key],'Premium no-op assessment belongs to current order');loyf_assert(isset($terminal[$row['meta_key']])&&!isset($added[$row['meta_key']])&&$terminal[$row['meta_key']]===$row['meta_value'],'Only pinned disabled Premium assessments may be appended');$added[$row['meta_key']]=$row['meta_value'];}
  loyf_equal($expected,$existing,'Existing cross-edition raw markers/IDs '.$surface);
 }else{loyf_equal($expected,$actual,'Cross-edition raw '.$surface);}
}
foreach(array('comment_meta','options') as $surface){loyf_equal($data['snapshot'][$surface],edition_snapshot()[$surface],'Cross-edition raw '.$surface);}
foreach($data['snapshot']['rows'] as $row){$key=$row['event_key'];if(null===$key){continue;}$event=array('action'=>$row['action'],'order_id'=>(int)$row['order_id'],'source_event_key'=>$row['source_event_key']);
 if(in_array($row['action'],YOWCL_Points_Transaction::REWARD_ACTIONS,true)){$result=YOWCL_Points_Transaction::reward((int)$row['user_id'],(int)$row['available_delta'],$key,$event);}
 elseif('points_deducted'===$row['action']){$result=YOWCL_Points_Transaction::reverse_order_reward((int)$row['user_id'],(int)$row['order_id'],'Replay');}
 elseif('referral_reward_reversal'===$row['action']){$result=YOWCL_Points_Transaction::reverse_referral_reward((int)$row['user_id'],(int)$row['order_id'],$row['source_event_key'],'referral_link_referrer_reward','Replay');}
 elseif(in_array($row['action'],array('admin_reward','admin_deduct','points_import'),true)){$request=YOWCL_Points_Allocation::decode($row['allocation_receipt'])['request'];$result=YOWCL_Points_Transaction::mutate((int)$row['user_id'],$request['available'],$request['earning'],$key,$event,$request['mode']);}
 else{$result=YOWCL_Points_Transaction::apply((int)$row['user_id'],(int)$row['available_delta'],(int)$row['earning_delta'],$key,$event);}
 loyf_equal('already_applied',$result['status'],'Installed edition recognizes committed identity '.$key);loyf_equal((int)$row['id'],$result['log_id'],'Installed edition retains log ID');}
loyf_equal($data['snapshot']['rows'],edition_snapshot()['rows'],'Installed native primitive does not append');
if('premium-replay'===$phase){$o=wc_get_order($data['order']);$o->update_status('cancelled');$rows=edition_snapshot()['rows'];foreach(array('points_transaction','points_deducted','referral_reward_reversal') as $action){loyf_assert(count(array_filter($rows,static function($row)use($action,$data){return $row['action']===$action&&(int)$row['order_id']===$data['order']&&null!==$row['source_event_key'];}))===1,'Native Premium cancellation creates each bounded return/reversal once');}$data['snapshot']=edition_snapshot();file_put_contents($file,wp_json_encode($data,JSON_PRETTY_PRINT));}
echo "Installed $phase native replay PASS; simulated entitlement; no external transport.\n";

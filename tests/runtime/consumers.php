<?php
require __DIR__ . '/assertions.php';
global $wpdb;
wp_set_current_user(1);
update_option('loyalty_extra_points_rules',array('signup_enabled'=>'no','login_enabled'=>'no'));
update_option('loyalty_extra_reviews_gamification_rules',array('review_enabled'=>'no','levelup_enabled'=>'no'));
update_option('loyalty_points_earning_rules',array());
YOWCL_Free_First_Purchase::save(false,0);YOWCL_Free_Referral::save(false,0);
update_option('timezone_string','America/New_York');
update_option('loyalty_levels_roles',array('customer','loyf_gold'));
update_option('loyalty_levels_rules',array('customer'=>array('from'=>'0'),'loyf_gold'=>array('from'=>'100')));
function cr_user($name){$u=wp_insert_user(array('user_login'=>'consumer_'.$name,'user_email'=>'consumer_'.$name.'@example.invalid','user_pass'=>'disposable-only','role'=>'customer'));loyf_assert(!is_wp_error($u),'Create consumer fixture');return(int)$u;}
function cr_fail($callback,$message){$thrown=false;try{$callback();}catch(Throwable$e){$thrown=true;}loyf_assert($thrown,$message);}
function cr_snapshot(){global$wpdb;return array($wpdb->get_results('SELECT * FROM '.YOWCL_Points_Log::table_name().' ORDER BY id',ARRAY_A),$wpdb->get_results("SELECT * FROM {$wpdb->usermeta} ORDER BY umeta_id",ARRAY_A),$wpdb->get_results("SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE 'loyf_%' OR option_name LIKE 'loyalty_%' ORDER BY option_name",ARRAY_A));}
function cr_csv(){ $stream=fopen('php://temp','w+');YOWCL_Free_Readers::snapshot(static function()use($stream){YOWCL_Free_Reader_Admin::stream($stream);});rewind($stream);$rows=array();while(false!==($row=fgetcsv($stream,0,',','"',''))){$rows[]=$row;}fclose($stream);return$rows;}
loyf_equal(0,YOWCL_Free_Readers::member_count(),'Empty admitted population');
loyf_equal('0',YOWCL_Free_Readers::outstanding()['value'],'Empty stock');
loyf_equal('0',YOWCL_Free_Readers::flows()['issued'],'Empty canonical store');
$a=cr_user('a');$b=cr_user('b');(new WP_User($a))->add_role('loyf_gold');
update_user_meta($a,'user_points','9007199254740993.125');update_user_meta($a,'user_earning_points','999999999999999999.500');
update_user_meta($b,'user_points','0.875');update_user_meta($b,'user_earning_points','1');
loyf_equal(2,YOWCL_Free_Readers::member_count(),'Distinct multi-role population');
loyf_equal('9007199254740994',YOWCL_Free_Readers::outstanding()['value'],'Exact set-based fractional stock beyond JS precision');
loyf_equal('9007199254740993.125',YOWCL_Free_Readers::customer($a)['available']['value'],'Exact customer spelling');
loyf_equal('loyf_gold',YOWCL_Free_Readers::customer($a)['level'],'Highest admitted role, not arbitrary first role');loyf_equal(YOWCL_Helper_Roles::get_highest_loyalty_user_role($a),YOWCL_Free_Readers::customer($a)['level'],'Snapshot level agrees with accepted helper');
update_option('loyalty_levels_roles',array('loyf_gold'));loyf_equal(1,YOWCL_Free_Readers::member_count(),'Saved roles do not force a customer fallback');update_option('loyalty_levels_roles',serialize(array('customer','loyf_gold')));loyf_equal(2,YOWCL_Free_Readers::member_count(),'Accepted wrapped My Account role admission');update_option('loyalty_levels_roles',array('customer','loyf_gold'));
update_user_meta($a,'_loyf_economic_hold','fixture_hold');loyf_equal(true,YOWCL_Free_Readers::customer($a)['held'],'Held disclosed');
loyf_equal(2,YOWCL_Free_Readers::outstanding()['held'],'Review-marker/unsupported-spending stock explicit');loyf_equal(true,YOWCL_Free_Readers::customer($b)['held'],'Fractional stored value does not promise spendability');
add_user_meta($b,'user_points','12');loyf_equal(null,YOWCL_Free_Readers::customer($b)['available']['value'],'Duplicate value unavailable');cr_fail(static function(){YOWCL_Free_Readers::outstanding();},'Duplicate stock unavailable');delete_user_meta($b,'user_points','12');
foreach(array('broken','-1','1e10','1,000','NaN',"123\n","1.25\r\n","\t1")as$value){update_user_meta($b,'user_points',$value);cr_fail(static function(){YOWCL_Free_Readers::outstanding();},'Invalid stock unavailable '.$value);loyf_equal(null,YOWCL_Free_Readers::customer($b)['available']['value'],'Invalid individual unavailable');}
delete_user_meta($b,'user_points');cr_fail(static function(){YOWCL_Free_Readers::outstanding();},'Missing stock is not zero');update_user_meta($b,'user_points','0.875');
$caps_key=$wpdb->get_blog_prefix().'capabilities';$caps=get_user_meta($b,$caps_key,true);
add_user_meta($b,$caps_key,$caps);cr_fail(static function(){YOWCL_Free_Readers::member_count();},'Ambiguous membership not exact');delete_user_meta($b,$caps_key);update_user_meta($b,$caps_key,$caps);
$wpdb->update($wpdb->usermeta,array('meta_value'=>'a:1:{s:9:"customer";b:1;}'),array('user_id'=>$b,'meta_key'=>$caps_key));cr_fail(static function(){YOWCL_Free_Readers::member_count();},'Malformed serialized key length unavailable');$wpdb->update($wpdb->usermeta,array('meta_value'=>serialize($caps)),array('user_id'=>$b,'meta_key'=>$caps_key));wp_cache_delete($b,'user_meta');
$wpdb->update($wpdb->usermeta,array('meta_value'=>serialize(array('customer'=>true,'read'=>true))),array('user_id'=>$b,'meta_key'=>$caps_key));loyf_equal(2,YOWCL_Free_Readers::member_count(),'Known native individual capability does not hide valid role membership');
$wpdb->update($wpdb->usermeta,array('meta_value'=>serialize(array('customer'=>1))),array('user_id'=>$b,'meta_key'=>$caps_key));cr_fail(static function(){YOWCL_Free_Readers::member_count();},'Truthful nonboolean role grants cannot silently disappear from population');$wpdb->update($wpdb->usermeta,array('meta_value'=>serialize($caps)),array('user_id'=>$b,'meta_key'=>$caps_key));wp_cache_delete($b,'user_meta');
// Native WordPress roles are registered capability keys, including boolean-false grants.
$a_caps=get_user_meta($a,$caps_key,true);$wpdb->update($wpdb->usermeta,array('meta_value'=>serialize(array('customer'=>false))),array('user_id'=>$b,'meta_key'=>$caps_key));$wpdb->update($wpdb->usermeta,array('meta_value'=>serialize(array('customer'=>true,'loyf_gold'=>false))),array('user_id'=>$a,'meta_key'=>$caps_key));wp_cache_delete($a,'user_meta');wp_cache_delete($b,'user_meta');
loyf_equal(array('customer'),(new WP_User($b))->roles,'Native false role key membership');loyf_assert(in_array('loyf_gold',(new WP_User($a))->roles,true),'Native multi-role false grant');$before=cr_snapshot();loyf_equal(2,YOWCL_Free_Readers::member_count(),'False role users remain in admitted population');loyf_equal('9007199254740994',YOWCL_Free_Readers::outstanding()['value'],'False role stock remains exact');loyf_equal('customer',YOWCL_Free_Readers::customer($b)['level'],'False customer role level');loyf_equal(YOWCL_Helper_Roles::get_highest_loyalty_user_role($a),YOWCL_Free_Readers::customer($a)['level'],'Native highest-role fallback includes false role keys');loyf_equal('loyf_gold',YOWCL_Free_Readers::customer($a)['level'],'False higher role fallback');$false_csv=cr_csv();loyf_equal(4,count($false_csv),'Complete export includes both false-role members');loyf_equal('complete',end($false_csv)[0],'False role export complete');loyf_equal("\t0.875",$false_csv[2][3],'False role exported balance');
wp_set_current_user($b);$_SERVER['REQUEST_METHOD']='POST';$false_ajax=loyf_ajax('wp_ajax_loyf_customer_components',array('nonce'=>wp_create_nonce('wp_rest'),'kinds'=>array('points-balance','level-progress')));loyf_equal(true,$false_ajax['success'],'Native false-role customer AJAX admitted');loyf_assert(false!==strpos($false_ajax['data']['points-balance'],'0.875'),'False-role own balance');wp_set_current_user(1);loyf_equal($before,cr_snapshot(),'Role semantics reads preserve persisted false grants');
$wpdb->update($wpdb->usermeta,array('meta_value'=>serialize($caps)),array('user_id'=>$b,'meta_key'=>$caps_key));$wpdb->update($wpdb->usermeta,array('meta_value'=>serialize($a_caps)),array('user_id'=>$a,'meta_key'=>$caps_key));wp_cache_delete($a,'user_meta');wp_cache_delete($b,'user_meta');
// Native canonical writer produces rewards, source-linked returns/reversals and replace targets.
$u=cr_user('canonical');update_user_meta($u,'user_points','0');update_user_meta($u,'user_earning_points','0');
$issued=0;
foreach(array_merge(YOWCL_Points_Transaction::REWARD_ACTIONS,array('admin_reward'))as$i=>$action){$points=10+$i;$issued+=$points;$result='admin_reward'===$action ? YOWCL_Points_Transaction::mutate($u,$points,$points,'consumer:award:'.$i,array('action'=>$action),'credit') : YOWCL_Points_Transaction::reward($u,$points,'consumer:award:'.$i,array('action'=>$action));loyf_equal('applied',$result['status'],'Canonical '.$action);}
$uuid=wp_generate_uuid4();$order=wc_create_order(array('customer_id'=>$u));$order->save();$key=YOWCL_Order_Redemption::event_key($uuid,'cart');
$result=YOWCL_Points_Transaction::apply($u,-20,0,$key,array('action'=>'points_used','order_id'=>$order->get_id()));loyf_equal('applied',$result['status'],'Canonical checkout debit fixture');
$result=YOWCL_Points_Transaction::apply($u,20,0,$key.':return',array('action'=>'points_transaction','order_id'=>$order->get_id(),'source_event_key'=>$key));loyf_equal('applied',$result['status'],'Source-linked return');
YOWCL_Points_Transaction::apply($u,5,5,'consumer:manual',array('action'=>'points_transaction'));
YOWCL_Points_Transaction::mutate($u,250,250,'consumer:import',array('action'=>'points_import'),'replace');
$r=cr_user('reversal');
foreach(array('partial','zero')as$case){
 $ro=wc_create_order(array('customer_id'=>$r));$ro->save();$rk='referral:link:'.$ro->get_id().':referrer';$points='partial'===$case?16:12;$issued+=$points;
 loyf_equal('applied',YOWCL_Points_Transaction::reward($r,$points,$rk,array('action'=>'referral_link_referrer_reward','order_id'=>$ro->get_id()))['status'],'Referral source');
 loyf_equal('applied',YOWCL_Points_Transaction::mutate($r,'partial'===$case?3:0,'partial'===$case?5:0,'consumer:target:'.$case,array('action'=>'points_import'),'replace')['status'],'Native spent/imported target fixture');
 loyf_equal('applied',YOWCL_Points_Transaction::reverse_referral_reward($r,$ro->get_id(),$rk,'referral_link_referrer_reward','Consumer fixture reversal')['status'],'Native referral reversal');
 $rr=YOWCL_Points_Transaction::find($rk.':reversal');loyf_equal('partial'===$case?-3:0,(int)$rr['available_delta'],'Partial/zero reversal exact');
 if('partial'===$case){$partialkey=$rk.':reversal';}
}
loyf_equal('applied',YOWCL_Points_Transaction::apply($u,-7,0,'consumer:unfunded',array('action'=>'points_used'))['status'],'Canonical debit without checkout identity');
$flows=YOWCL_Free_Readers::flows();loyf_equal((string)$issued,$flows['issued'],'Explicit gross award actions');loyf_equal('20',$flows['redeemed'],'Return/import/reversal not gross redemption');loyf_equal(1,$flows['orders'],'Distinct proven funding');loyf_equal(1,$flows['unproven_debits'],'Unproven funding coverage is explicit');
$debit=YOWCL_Points_Transaction::find($key);$debit_table=YOWCL_Points_Log::table_name();$wpdb->update($debit_table,array('earning_delta'=>1),array('id'=>$debit['id']));cr_fail(static function(){YOWCL_Free_Readers::flows();},'Malformed native debit cannot claim funding');$wpdb->update($debit_table,array('earning_delta'=>0),array('id'=>$debit['id']));
$before=cr_snapshot();YOWCL_Points_Transaction::apply($u,-20,0,$key,array('action'=>'points_used','order_id'=>$order->get_id()));loyf_equal($before,cr_snapshot(),'Replay accounting immutable');
$table=YOWCL_Points_Log::table_name();$old=YOWCL_Points_Transaction::find('consumer:award:0');$receipt=YOWCL_Points_Allocation::decode($old['allocation_receipt']);$original=$old['allocation_receipt'];
$receipt['admitted_at']=(time()-40*DAY_IN_SECONDS)*1000000;$wpdb->update($table,array('allocation_receipt'=>wp_json_encode($receipt)),array('id'=>$old['id']));
loyf_equal((string)($issued-10),YOWCL_Free_Readers::flows(true)['issued'],'30-day UTC receipt boundary');loyf_equal((string)$issued,YOWCL_Free_Readers::flows()['issued'],'Lifetime unchanged');
$wpdb->update($table,array('allocation_receipt'=>null,'ledger_version'=>null),array('id'=>$old['id']));loyf_equal((string)$issued,YOWCL_Free_Readers::flows()['issued'],'Proven pre-v2 lifetime');cr_fail(static function(){YOWCL_Free_Readers::flows(true);},'Missing recent time proof not inferred from wall date');
$wpdb->update($table,array('allocation_receipt'=>$original,'ledger_version'=>2),array('id'=>$old['id']));
$legacy=$old;unset($legacy['id']);foreach(array('event_key','available_delta','earning_delta','ledger_version','source_event_key','allocation_receipt')as$f){$legacy[$f]=null;}$legacy['amount']=999999;$wpdb->insert($table,$legacy);$legacy_id=$wpdb->insert_id;
loyf_equal(1,YOWCL_Free_Readers::flows()['legacy'],'Legacy-only coverage');loyf_equal(null,YOWCL_Free_Core::history_amount($legacy),'Same history contract');loyf_equal((string)$issued,YOWCL_Free_Readers::flows()['issued'],'Legacy amount never awarded');
$wpdb->update($table,array('allocation_receipt'=>'{}'),array('id'=>$old['id']));cr_fail(static function(){YOWCL_Free_Readers::flows();},'Malformed receipt not zero');$wpdb->update($table,array('allocation_receipt'=>$original),array('id'=>$old['id']));
$partial=YOWCL_Points_Transaction::find($partialkey);$wpdb->update($table,array('source_event_key'=>'missing'),array('id'=>$partial['id']));cr_fail(static function(){YOWCL_Free_Readers::flows();},'Missing source unavailable');$wpdb->update($table,array('source_event_key'=>$partial['source_event_key']),array('id'=>$partial['id']));
// Every component shares neutral cache/editor output even when supplied a foreign identity.
wp_set_current_user($a);$_SERVER['REQUEST_METHOD']='POST';
$before=cr_snapshot();
foreach(YOWCL_Free_Customer_Components::KINDS as$kind){$html=do_shortcode('[loyf_'.str_replace('-','_',$kind).' user_id="'.$b.'"]');loyf_assert(false!==strpos($html,'data-loyf-component'),'Registered shortcode '.$kind);loyf_assert(false===strpos($html,'9007199254740993'),'Neutral shortcode');$block=render_block(array('blockName'=>'loyf/'.$kind,'attrs'=>array('user_id'=>$b),'innerBlocks'=>array(),'innerHTML'=>'','innerContent'=>array()));loyf_assert(false!==strpos($block,'data-loyf-component'),'Real dynamic block callback');}
$fields=array('nonce'=>wp_create_nonce('wp_rest'),'kinds'=>YOWCL_Free_Customer_Components::KINDS,'user_id'=>$b,'email'=>'foreign@example.invalid');
$ajax=loyf_ajax('wp_ajax_loyf_customer_components',$fields);loyf_equal(true,$ajax['success'],'Native self-only AJAX');loyf_assert(false!==strpos($ajax['data']['points-balance'],'9007199254740993.125'),'Identity attributes ignored');loyf_assert(false!==strpos($ajax['data']['points-balance'],'held'),'Held presentation');
loyf_equal($before,cr_snapshot(),'Component reads never create economic state');
$account=get_option('loyalty_customization_my_account');update_option('loyalty_customization_my_account',array('my_account'=>false));$disabled=loyf_ajax('wp_ajax_loyf_customer_components',array('nonce'=>wp_create_nonce('wp_rest'),'kinds'=>array('points-history')));loyf_assert(false!==strpos($disabled['data']['points-history'],'not enabled'),'Disabled endpoint state');update_option('loyalty_customization_my_account',$account);
$wrapped_account=serialize(array('my_account'=>true,'my_account_slug'=>'loyalty-history','my_account_label'=>'Your loyalty history'));update_option('loyalty_customization_my_account',$wrapped_account);$before=cr_snapshot();$wrapped=loyf_ajax('wp_ajax_loyf_customer_components',array('nonce'=>wp_create_nonce('wp_rest'),'kinds'=>array('points-history')));loyf_equal(true,$wrapped['success'],'Wrapped My Account native AJAX');loyf_assert(false!==strpos($wrapped['data']['points-history'],'loyalty-history'),'Wrapped account endpoint slug');loyf_assert(false!==strpos($wrapped['data']['points-history'],'Your loyalty history'),'Wrapped account label');loyf_equal($before,cr_snapshot(),'Wrapped account read does not normalize stored options');update_option('loyalty_customization_my_account',$account);
$earning_statuses=get_option('loyalty_points_earning_status');$earning_rules=get_option('loyalty_points_earning_rules');update_option('loyalty_points_earning_rules',array('loyf_gold'=>array('points'=>10,'amount'=>5)));
foreach(array(array(), 'malformed', array(array('wc-completed')), array('wc-unknown'))as$statuses){update_option('loyalty_points_earning_status',$statuses);$before=cr_snapshot();$ways=loyf_ajax('wp_ajax_loyf_customer_components',array('nonce'=>wp_create_nonce('wp_rest'),'kinds'=>array('ways-to-earn')));loyf_equal(true,$ways['success'],'Disabled/malformed purchase statuses native AJAX');loyf_assert(false===strpos($ways['data']['ways-to-earn'],'Eligible purchases'),'Disabled/malformed statuses do not advertise purchase rewards');loyf_equal($before,cr_snapshot(),'Purchase enablement read preserves state');}
foreach(array(array('wc-completed'),serialize(array('wc-completed')))as$statuses){update_option('loyalty_points_earning_status',$statuses);$before=cr_snapshot();$ways=loyf_ajax('wp_ajax_loyf_customer_components',array('nonce'=>wp_create_nonce('wp_rest'),'kinds'=>array('ways-to-earn')));loyf_assert(false!==strpos($ways['data']['ways-to-earn'],'Eligible purchases'),'Enabled native purchase statuses including wrapper');loyf_equal($before,cr_snapshot(),'Enabled purchase offer is read-only');}update_option('loyalty_points_earning_status',$earning_statuses);update_option('loyalty_points_earning_rules',$earning_rules);
$account_page=get_option('woocommerce_myaccount_page_id');update_option('woocommerce_myaccount_page_id',0);$missing=loyf_ajax('wp_ajax_loyf_customer_components',array('nonce'=>wp_create_nonce('wp_rest'),'kinds'=>array('points-history')));loyf_assert(false!==strpos($missing['data']['points-history'],'not enabled'),'Missing account page state');update_option('woocommerce_myaccount_page_id',$account_page);
$levels=get_option('loyalty_levels_rules');update_option('loyalty_levels_rules',array('loyf_gold'=>array('from'=>'bad')));loyf_equal(null,YOWCL_Free_Readers::customer($a)['level'],'Unknown level configuration is not guessed');update_option('loyalty_levels_rules',$levels);
wp_set_current_user(0);loyf_equal(false,loyf_ajax('wp_ajax_loyf_customer_components',$fields)['success'],'Guest denied');wp_set_current_user($b);loyf_equal(false,loyf_ajax('wp_ajax_loyf_customer_components',$fields)['success'],'A nonce cannot authorize B');wp_set_current_user(1);
// Static Premium discovery is read-only and enforces admin + edition ownership.
$preview_editor=wp_insert_user(array('user_login'=>'preview_editor','user_email'=>'preview-editor@example.invalid','user_pass'=>'disposable-only','role'=>'editor'));loyf_assert(!is_wp_error($preview_editor),'Create preview editor fixture');
$before=cr_snapshot();
foreach(array('account','bubble') as $scope){ob_start();YOWCL_Free_Referral::render_link($scope);loyf_equal('',ob_get_clean(),'Disabled referral has no empty card');}
foreach(array('campaigns_preview','history_preview') as $method){
    ob_start();YOWCL_Free_Reader_Admin::$method();$preview=ob_get_clean();
    loyf_assert(false!==strpos($preview,'Premium preview') && false!==strpos($preview,'Example only'),'Explicit illustrative preview');
    loyf_assert(false===strpos($preview,'consumer_') && false===strpos($preview,'<form') && false===strpos($preview,'<input'),'No real identities or execution controls');
}
foreach(array('overview','emails','referrals','customization','tools') as $context){ob_start();YOWCL_Free_Reader_Admin::premium_card($context);$html=ob_get_clean();loyf_assert(false!==strpos($html,'<details>'),'Native disclosure '.$context);}
loyf_equal($before,cr_snapshot(),'Preview renders preserve accounting and settings');
$preview_die=static function(){return static function(){throw new LOYF_Test_Die();};};
add_filter('wp_die_handler',$preview_die,PHP_INT_MAX);add_filter('wp_die_ajax_handler',$preview_die,PHP_INT_MAX);
foreach(array(0,$a,$b,$preview_editor) as $viewer){
    wp_set_current_user($viewer);
    foreach(array('campaigns_preview','history_preview') as $method){$denied=false;try{YOWCL_Free_Reader_Admin::$method();}catch(LOYF_Test_Die $e){$denied=true;}loyf_assert($denied,'Preview permission denied');}
    ob_start();YOWCL_Free_Reader_Admin::premium_card('referrals');loyf_equal('',ob_get_clean(),'No customer promotional markup');
}
wp_set_current_user(1);$active=get_option('active_plugins');update_option('active_plugins',array_merge($active,array('wc-loyalty/wc-loyalty.php')));
foreach(array('campaigns_preview','history_preview') as $method){$denied=false;try{YOWCL_Free_Reader_Admin::$method();}catch(LOYF_Test_Die $e){$denied=true;}loyf_assert($denied,'Premium owner denies Free preview');}
ob_start();YOWCL_Free_Reader_Admin::menu();YOWCL_Free_Reader_Admin::premium_card('overview');loyf_equal('',ob_get_clean(),'Premium ownership hides Free discovery');
update_option('active_plugins',$active);remove_filter('wp_die_handler',$preview_die,PHP_INT_MAX);remove_filter('wp_die_ajax_handler',$preview_die,PHP_INT_MAX);
loyf_equal($before,cr_snapshot(),'Denied discovery preserves business state');
// CSV does not touch import retry identity, has deterministic rows and preserves strings.
$before=cr_snapshot();$csv=cr_csv();loyf_equal('complete',end($csv)[0],'CSV explicit completion');loyf_equal("\t9007199254740993.125",$csv[1][3],'CSV fractional exact available');loyf_equal("\t999999999999999999.500",$csv[1][4],'CSV exact earning spelling');loyf_equal('held',$csv[1][6],'CSV hold state');loyf_equal($csv,cr_csv(),'Repeated CSV deterministic');loyf_equal($before,cr_snapshot(),'CSV never changes import/accounting identity');
foreach(array('=1+1','+1','-1','@SUM(A1)','＝1','＋1','－1','＠1',"\t=1","\r\n=1",'";=1,escaped')as$payload){$stream=fopen('php://temp','w+');YOWCL_Free_Reader_Admin::csv_row($stream,array('customer',$payload));rewind($stream);$parsed=fgetcsv($stream,0,',','"','');loyf_equal(YOWCL_Free_Reader_Admin::csv_cell($payload),$parsed[1],'Raw CSV parser fidelity');if(preg_match('/^[=+@\-＝＋－＠]/u',$payload)||preg_match('/[\x00-\x1f]/',$payload)){loyf_equal("\t",substr($parsed[1],0,1),'Spreadsheet guard');}fclose($stream);}
$fail=static function($sql){if(false!==strpos($sql,' FROM '.YOWCL_Points_Log::table_name())){return'SELECT * FROM loyf12_missing_table';}return$sql;};$wpdb->suppress_errors(true);add_filter('query',$fail,PHP_INT_MAX);cr_fail(static function(){YOWCL_Free_Readers::flows();},'Query failure not zero');remove_filter('query',$fail,PHP_INT_MAX);
$fail=static function($sql){if(false!==strpos($sql,'SELECT u.ID,u.user_email')){return'SELECT * FROM loyf12_missing_table';}return$sql;};add_filter('query',$fail,PHP_INT_MAX);$csv=cr_csv();loyf_equal('error',end($csv)[0],'CSV failed prefix has error terminal');remove_filter('query',$fail,PHP_INT_MAX);$wpdb->suppress_errors(false);
$oversized=str_repeat('x',YOWCL_Free_Readers::BYTE_LIMIT+1);$wpdb->update($table,array('allocation_receipt'=>$oversized),array('id'=>$old['id']));cr_fail(static function(){YOWCL_Free_Readers::flows();},'Proof payload byte bound');$wpdb->update($table,array('allocation_receipt'=>$original),array('id'=>$old['id']));
$saved_b=get_user_meta($b,'user_points',true);update_user_meta($b,'user_points',$oversized);cr_fail(static function(){YOWCL_Free_Readers::customer(get_user_by('login','consumer_b')->ID);},'Metadata payload byte bound');$incomplete=cr_csv();loyf_equal('error',end($incomplete)[0],'Oversized batch is visibly incomplete');update_user_meta($b,'user_points',$saved_b);unset($oversized);
// Bound many persisted rows without inventing a partial exact result.
$ids=array();for($i=0;$i<YOWCL_Free_Readers::LOG_LIMIT;$i++){$wpdb->insert($table,$legacy);$ids[]=$wpdb->insert_id;}cr_fail(static function(){YOWCL_Free_Readers::flows();},'Log safe upper bound');$wpdb->query('DELETE FROM '.$table.' WHERE id IN ('.implode(',',array_map('intval',$ids)).')');
$before_queries=$wpdb->num_queries;YOWCL_Free_Readers::snapshot(static function(){YOWCL_Free_Readers::member_count();YOWCL_Free_Readers::outstanding();YOWCL_Free_Readers::flows();});loyf_assert($wpdb->num_queries-$before_queries<=8,'Set-based bounded report queries');
// Native keyset pagination and safe member bound: no all-user PHP fetch or truncated export.
$stress=array();$next=(int)$wpdb->get_var("SELECT MAX(ID) FROM {$wpdb->users}")+1;
for($offset=0;$offset<10001;$offset+=500){
 $users=array();$metadata=array();$end=min(10001,$offset+500);
 for($n=$offset;$n<$end;$n++){$id=$next+$n;$stress[]=$id;$users[]=$wpdb->prepare('(%d,%s,%s,%s,%s)', $id,'consumer_stress_'.$id,'stress_'.$id.'@example.invalid','invalid-disposable-password','2026-10-01 00:00:00');foreach(array($caps_key=>serialize(array('customer'=>true)),'user_points'=>'1.25','user_earning_points'=>'2')as$k=>$v){$metadata[]=$wpdb->prepare('(%d,%s,%s)',$id,$k,$v);}}
 $wpdb->query("INSERT INTO {$wpdb->users} (ID,user_login,user_email,user_pass,user_registered) VALUES ".implode(',',$users));$wpdb->query("INSERT INTO {$wpdb->usermeta} (user_id,meta_key,meta_value) VALUES ".implode(',',$metadata));
 if(0===$offset){$queries=$wpdb->num_queries;$paged=cr_csv();loyf_equal('complete',end($paged)[0],'Multi-batch keyset completion');loyf_equal(506,count($paged),'500 stress members plus existing four, header/footer');loyf_assert($wpdb->num_queries-$queries<=20,'CSV queries grow by batch, not customer');$previous=0;foreach(array_slice($paged,1,-1)as$row){loyf_assert((int)$row[1]>$previous,'Keyset deterministic order');$previous=(int)$row[1];}}
}
cr_fail(static function(){YOWCL_Free_Readers::member_count();},'Member upper bound does not claim exact population');cr_fail(static function(){cr_csv();},'CSV safe upper bound does not silently truncate');
$wpdb->query("DELETE FROM {$wpdb->usermeta} WHERE user_id IN (".implode(',',$stress).')');$wpdb->query("DELETE FROM {$wpdb->users} WHERE ID IN (".implode(',',$stress).')');
// Nontransactional snapshots and enclosing writers are denied without conversion/commit.
$wpdb->query('START TRANSACTION');cr_fail(static function(){YOWCL_Free_Readers::snapshot(static function(){});},'No nested transaction authority');$wpdb->query('ROLLBACK');
// Overview presentation retains exact reader values, failures and read-only state.
wp_set_current_user(1);$_GET=array();$before=cr_snapshot();
ob_start();YOWCL_Free_Reader_Admin::overview();$overview=ob_get_clean();
loyf_equal(5,substr_count($overview,'class="loyf-overview-card"'),'Five Free metrics only');
loyf_assert(false!==strpos($overview,esc_html(YOWCL_Free_Readers::outstanding()['value'])),'Exact stored decimal in overview');
loyf_assert(false!==strpos($overview,'Accounts with held balances:'),'Held coverage remains visible');
$_GET=array('period'=>'30');ob_start();YOWCL_Free_Reader_Admin::overview();$recent_overview=ob_get_clean();
loyf_assert(false!==strpos($recent_overview,'value="30" selected='),'Existing recent filter remains selected');
loyf_equal($before,cr_snapshot(),'Overview/filter rendering does not mutate stored state');
$wpdb->suppress_errors(true);
// Fail all reader queries without turning failed evidence into zero.
$reader_failure=static function($sql){return false!==strpos($sql,'yo_loyalty_points_log') ? 'SELECT * FROM loyf31_missing_table' : $sql;};
add_filter('query',$reader_failure,PHP_INT_MAX);ob_start();YOWCL_Free_Reader_Admin::overview();$failed_overview=ob_get_clean();remove_filter('query',$reader_failure,PHP_INT_MAX);$wpdb->suppress_errors(false);
loyf_assert(false!==strpos($failed_overview,'<strong>Unavailable</strong>'),'Failed flow proof visibly unavailable');
$_GET=array();wp_set_current_user($a);loyf_equal(false,YOWCL_Free_Reader_Admin::allowed(),'Customer cannot access admin overview/settings');wp_set_current_user(1);
// Existing no-level availability messages remain intact, without editable bonus/customization controls.
$roles=get_option('loyalty_levels_roles');update_option('loyalty_levels_roles',array());$before=cr_snapshot();
foreach(array(new YOSWC_Loyalty_Settings_Extra_Points(),new YOSWC_Loyalty_Settings_Customization())as$screen){ob_start();$screen instanceof YOSWC_Loyalty_Settings_Extra_Points ? $screen->display_extra_points_settings() : $screen->display_customization_settings();$html=ob_get_clean();loyf_assert(false!==strpos($html,'Before start'),'No-level availability guidance retained');loyf_assert(false===strpos($html,'<input'),'No-level screen has no editable settings');}
loyf_equal($before,cr_snapshot(),'No-level rendering is read-only');update_option('loyalty_levels_roles',$roles);
// Grouped email presentation cannot change native registry, stored settings or held policy.
$mailer=WC()->mailer();$keys=array_keys($mailer->get_emails());$options=array();
foreach(array('email_reward'=>'points_reward','email_deduct'=>'points_deduct','email_level'=>'level_update')as$feature=>$family){$options[$feature]=array('name'=>'woocommerce_yowcl_loyalty_'.$family.'_settings');$options[$feature]['raw']=YOWCL_Free_Migrations::read($options[$feature]['name']);}
$before=cr_snapshot();$_GET=array('email_group'=>'loyalty');ob_start();do_action('woocommerce_admin_field_loyf_loyalty_emails');$group=ob_get_clean();
loyf_equal(3,substr_count($group,'data-loyf-email='),'Only three existing Free email rows');
loyf_equal($keys,array_keys($mailer->get_emails()),'Grouping preserves complete mailer registry');
loyf_equal($before,cr_snapshot(),'Grouped email rendering is read-only');
foreach(array_keys($options)as$feature){$witness=YOWCL_Free_Migrations::read(YOWCL_Free_Migrations::witness($feature));update_option(YOWCL_Free_Migrations::witness($feature),'0');$held_before=cr_snapshot();ob_start();do_action('woocommerce_admin_field_loyf_loyalty_emails');$held=ob_get_clean();loyf_assert(false!==strpos($held,'data-loyf-status="Settings preserved"'),'Held email never appears active');loyf_equal($held_before,cr_snapshot(),'Held group rendering preserves settings');update_option(YOWCL_Free_Migrations::witness($feature),$witness);loyf_equal($options[$feature]['raw'],YOWCL_Free_Migrations::read($options[$feature]['name']),'Held row leaves raw option unchanged');}
// Native extension columns own the whole cell, in both General and Loyalty tables.
$extension_columns=static function($columns){$columns['loyf_test_extension']='Test extension';return $columns;};
$extension_cell=static function($email){echo '<td class="loyf-test-extension">'.esc_html($email->id).'</td>';};
add_filter('woocommerce_email_setting_columns',$extension_columns);add_action('woocommerce_email_setting_column_loyf_test_extension',$extension_cell);
foreach(array('general','loyalty')as$group){ob_start();do_action('woocommerce_admin_field_loyf_loyalty_emails',array('group'=>$group));$custom=ob_get_clean();$expected='loyalty'===$group?3:count($keys)-3;loyf_equal($expected,substr_count($custom,'class="loyf-test-extension"'),'Native custom column owns exactly one cell per email');loyf_assert(!preg_match('/<td[^>]*>\s*<td\b/',$custom),'No nested extension cells');}
remove_filter('woocommerce_email_setting_columns',$extension_columns);remove_action('woocommerce_email_setting_column_loyf_test_extension',$extension_cell);
$_GET=array();
echo 'Native Basic Free consumers PASS Woo'.WC_VERSION.' '.getenv('LOYF_STORAGE')."\n";

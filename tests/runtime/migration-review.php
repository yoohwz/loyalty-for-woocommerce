<?php
/** Native ordinary upgrade and UI fixtures; only disposable sites may execute. */
require_once __DIR__.'/assertions.php';
global $wpdb;
$path=getenv('LOYF25_SNAPSHOT');
$mode=$args[0]??'upgrade';
$names=array('loyalty_extra_points_rules','loyalty_extra_reviews_gamification_rules','loyalty_extra_levelup_points_rules','loyalty_notification_email','loyalty_points_using_rules');
foreach(array('points_reward','points_deduct','level_update') as $id){$names[]='woocommerce_yowcl_loyalty_'.$id.'_settings';}
$snapshot=static function()use($wpdb,$names){$result=array('options'=>array(),'meta'=>$wpdb->get_results("SELECT * FROM {$wpdb->usermeta} WHERE meta_key IN ('user_points','user_earning_points') ORDER BY umeta_id",ARRAY_A),'logs'=>$wpdb->get_results("SELECT * FROM {$wpdb->prefix}yo_loyalty_points_log ORDER BY id",ARRAY_A));foreach($names as $name){$result['options'][$name]=$wpdb->get_row($wpdb->prepare("SELECT option_value,autoload FROM {$wpdb->options} WHERE option_name=%s",$name),ARRAY_A);}return $result;};
if ('before'===$mode) { foreach(array('signup','login','review','levelup','redemption','email_reward','email_deduct','email_level') as $f){loyf_equal(null,$wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name=%s",'loyf_migration_'.$f.'_v1_before')),'No pre-capture');}file_put_contents($path,wp_json_encode($snapshot()));echo "Original Free before uncaptured upgrade recorded.\n";return; }
wp_set_current_user(1);
if ('upgrade'===$mode) {
 $before=json_decode(file_get_contents($path),true);
 foreach(array('resolve_migration','confirm_migrations','replace_migration','retry_migration')as$action){loyf_assert(!has_action('admin_post_loyf_'.$action),'No retired migration HTTP endpoint '.$action);}
 loyf_assert(!method_exists('YOWCL_Free_Migrations','render_review') && !method_exists('YOWCL_Free_Migrations','register_review'),'No migration page or direct renderer');
 $old_records=array();foreach(YOWCL_Free_Migrations::features()as$f){$old_records[$f]=array();foreach(array('_before','_resolution','_background','_supersession')as$suffix){$old_records[$f][$suffix]=YOWCL_Free_Migrations::read(YOWCL_Free_Migrations::witness($f).$suffix);}}
 YOWCL_Free_Migrations::schedule();
 $actions=as_get_scheduled_actions(array('hook'=>YOWCL_Free_Migrations::HOOK,'group'=>YOWCL_Free_Migrations::GROUP,'status'=>'pending','per_page'=>30),'ids');
 loyf_equal(8,count($actions),'All eight ordinary uncaptured features automatically queued');
 foreach($actions as$id){ActionScheduler_QueueRunner::instance()->process_action($id,'LOYF-29 ordinary uncaptured upgrade');}
 foreach(YOWCL_Free_Migrations::features()as$f){loyf_assert(YOWCL_Free_Migrations::ready($f),'Verified automatic inactive witness '.$f);$a=get_option(YOWCL_Free_Migrations::witness($f).'_automatic');loyf_equal(29,$a['automatic'],'New policy evidence without fabricated capture '.$f);foreach($old_records[$f]as$suffix=>$raw){loyf_equal($raw,YOWCL_Free_Migrations::read(YOWCL_Free_Migrations::witness($f).$suffix),'Original evidence/intent/work untouched '.$f.$suffix);}}
 loyf_equal(0,YOWCL_Free_Core::extra('signup'),'Previously positive old-Free signup now inactive');loyf_equal(0,YOWCL_Free_Core::extra('login'),'Previously positive login inactive');loyf_equal(0,YOWCL_Free_Core::extra('review'),'Dormant canonical positive review inactive');loyf_equal(array(),YOWCL_Free_Core::level_rules(),'Positive role map not activated');loyf_equal(array(),YOWCL_Free_Cart::rules(),'New redemption inactive without zeroing rate');
 foreach(array('email_reward','email_deduct','email_level')as$f){loyf_equal('no',YOWCL_Free_Migrations::canonical($f)['enabled'],'New email delivery inactive');}
 $current=$snapshot();foreach($current['logs']as$i=>$row){$current['logs'][$i]=array_intersect_key($row,$before['logs'][$i]??array());}
 loyf_equal($before['meta'],$current['meta'],'Original balances unchanged');loyf_equal($before['logs'],$current['logs'],'Every original log row/column/ID unchanged');
 foreach($before['options']as$n=>$row){$now=$current['options'][$n];
  if(in_array($n,array('loyalty_extra_points_rules','loyalty_extra_reviews_gamification_rules'),true)||strpos($n,'woocommerce_yowcl_loyalty_')===0){
   $decode=static function($v){return null===$v?array():maybe_unserialize(maybe_unserialize($v));};$original=$decode($row['option_value']??null);$actual=$decode($now['option_value']??null);
   foreach(array('signup_enabled','login_enabled','review_enabled','levelup_enabled','enabled')as$key){unset($original[$key],$actual[$key]);}
   loyf_equal(serialize($original),serialize($actual),'Amounts/maps/native email subjects/unknown siblings preserved '.$n);
   if($row){loyf_equal($row['autoload'],$now['autoload'],'Autoload unchanged '.$n);}
  }else{loyf_equal($row,$now,'Raw legacy/redemption bytes unchanged '.$n);}
 }
 set_current_screen('dashboard');ob_start();YOWCL_Free_Migrations::notices();$notice=ob_get_clean();loyf_assert(strpos($notice,'previous configuration could not be verified')!==false,'Meaningful policy pause is explained');loyf_equal(1,substr_count($notice,'id="loyf-migration-notice"'),'One dismissible notice');loyf_assert(strpos($notice,'loyf-migration-review')===false,'No stale notice link');
 $after=$snapshot();YOWCL_Free_Migrations::schedule();foreach($actions as$id){$args=ActionScheduler::store()->fetch_action($id)->get_args();YOWCL_Free_Migrations::worker($args[0],$args[1]);}loyf_equal($after,$snapshot(),'Terminal replays do not rewrite policy/data');
 echo "Actual old-Free upgrade WITHOUT capture/admin migration POST PASS: eight verified inactive features; exact amounts/maps/rates/autoload/balances/logs; no migration route; truthful policy notice.\n";return;
}
$store=static function($name,$value)use($wpdb){$wpdb->delete($wpdb->options,array('option_name'=>$name));if(null!==$value){$wpdb->insert($wpdb->options,array('option_name'=>$name,'option_value'=>maybe_serialize($value),'autoload'=>'no'));}wp_cache_delete($name,'options');wp_cache_delete('alloptions','options');wp_cache_delete('notoptions','options');};
if (in_array($mode,array('browser-seed','background-seed'),true)) {
 foreach(YOWCL_Free_Migrations::features() as $f){foreach(array('','_before','_resolution','_background','_supersession','_automatic','_automatic_background','_automatic_enabled') as $suffix){$store(YOWCL_Free_Migrations::witness($f).$suffix,null);}}
 if ('background-seed'===$mode) { delete_user_meta(1,'loyf_migration_notice_dismissed'); }
 $store('loyalty_extra_points_rules',array('signup_points'=>'17','signup_enabled'=>'no','login_points'=>'19','login_enabled'=>'yes','unknown'=>'private-dormant'));
 $store('loyalty_extra_reviews_gamification_rules',array('review_points'=>'23','review_enabled'=>'yes','levelup_points'=>array('platinum'=>array('awarded'=>'50','dormant'=>'private-dormant')),'levelup_enabled'=>'yes','unknown'=>'private-dormant'));
 $store('loyalty_extra_levelup_points_rules',array('platinum'=>array('awarded'=>'30')));
 $store('loyalty_notification_email',array('points_update'=>'yes','level_update'=>'yes','unknown'=>'private-dormant'));
 $store('loyalty_points_using_rules',array('points'=>'10','amount'=>0.7,'min_points'=>'','max_points'=>'','min_cart'=>'','unknown'=>'private-dormant'));
 $store('loyalty_points_using_point','yes');
 foreach(array('points_reward','points_deduct','level_update') as $id){$store('woocommerce_yowcl_loyalty_'.$id.'_settings',array('enabled'=>'yes','subject'=>'Merchant subject','heading'=>'Merchant heading','unknown'=>'private-dormant'));}
 $store('woocommerce_onboarding_profile',array('skipped'=>true));
 $store('loyalty_levels_roles',array('customer','platinum'));
 $store('loyalty_levels_rules',array('customer'=>array('from'=>'0'),'platinum'=>array('from'=>'100')));
 $store('loyalty_points_earning_rules',array('customer'=>array('points'=>'1','amount'=>'1'),'platinum'=>array('points'=>'1','amount'=>'1')));
 $store('loyalty_points_rounding','round_down');
 file_put_contents($path,wp_json_encode($snapshot()));echo "Native browser fixtures seeded; normal boot admits the automatic policy without migration POST.\n";return;
}
if ('replacement-seed'===$mode) {
 $f='signup';foreach(array('','_before','_resolution','_background','_supersession','_automatic','_automatic_background','_automatic_enabled') as$suffix){$store(YOWCL_Free_Migrations::witness($f).$suffix,null);}
 $v=get_option('loyalty_extra_points_rules');$v['signup_points']='17';$v['signup_enabled']='no';$store('loyalty_extra_points_rules',$v);
 $spec=YOWCL_Free_Migrations::preview($f,'canonical');YOWCL_Free_Migrations::admit(array($f=>array('mode'=>'canonical','fingerprint'=>hash('sha256',serialize($spec)))),wp_generate_uuid4(),wp_create_nonce('loyf_confirm_migrations'));
 $v['signup_points']='90';$v['signup_enabled']='yes';$store('loyalty_extra_points_rules',$v);
 file_put_contents($path.'.replacement',wp_json_encode(array('pending'=>$wpdb->get_row("SELECT option_value,autoload FROM {$wpdb->options} WHERE option_name='loyf_migration_signup_v1_resolution'",ARRAY_A),'business'=>array($snapshot()['meta'],$snapshot()['logs']))));return;
}
if ('verify-replacement'===$mode) {
 $before=json_decode(file_get_contents($path.'.replacement'),true);loyf_assert(YOWCL_Free_Migrations::ready('signup'),'Browser replacement completed');loyf_equal(0,YOWCL_Free_Core::extra('signup'),'Browser deliberately disables live reward');loyf_equal($before['pending'],$wpdb->get_row("SELECT option_value,autoload FROM {$wpdb->options} WHERE option_name='loyf_migration_signup_v1_resolution'",ARRAY_A),'Browser original pending row immutable');$audit=get_option('loyf_migration_signup_v1_supersession');loyf_equal('disable',$audit['mode'],'One completed replacement audit');loyf_equal($before['business'],array($snapshot()['meta'],$snapshot()['logs']),'Browser no value/log mutation');return;
}
if ('drain-background'===$mode) {
 for($i=0;$i<4;$i++) { $ids=as_get_scheduled_actions(array('hook'=>YOWCL_Free_Migrations::HOOK,'group'=>YOWCL_Free_Migrations::GROUP,'status'=>'pending','per_page'=>20),'ids');if(!$ids){break;}foreach($ids as$id){ActionScheduler_QueueRunner::instance()->process_action($id,'LOYF-27 browser');} }
 return;
}
if ('verify-background'===$mode) { $status=YOWCL_Free_Migrations::status();loyf_equal('completed',$status['state'],'Browser background verified convergence '.wp_json_encode($status['features']));$before=json_decode(file_get_contents($path),true);$now=$snapshot();loyf_equal($before['meta'],$now['meta'],'Automatic browser transition preserves balances');loyf_equal($before['logs'],$now['logs'],'Automatic browser transition preserves history');foreach($before['options']as$name=>$row){$actual=$now['options'][$name];if($row===null && $actual===null){continue;}$decode=static function($raw){return null===$raw?array():maybe_unserialize(maybe_unserialize($raw));};$a=$decode($row['option_value']??null);$b=$decode($actual['option_value']??null);foreach(array('signup_enabled','login_enabled','review_enabled','levelup_enabled','enabled')as$key){unset($a[$key],$b[$key]);}loyf_equal(serialize($a),serialize($b),'Configured terms preserved '.$name);if($row){loyf_equal($row['autoload'],$actual['autoload'],'Autoload preserved '.$name);}}return; }
if ('verify-queued'===$mode) { loyf_equal(8,count(as_get_scheduled_actions(array('hook'=>YOWCL_Free_Migrations::HOOK,'group'=>YOWCL_Free_Migrations::GROUP,'status'=>'pending','per_page'=>20),'ids')),'Dismiss does not cancel queue');foreach(YOWCL_Free_Migrations::features()as$f){loyf_assert(!YOWCL_Free_Migrations::ready($f),'Dismiss is not consent completion');}return; }
if ('stale'===$mode){$r=maybe_unserialize(YOWCL_Free_Migrations::read('loyalty_extra_points_rules'));$r['login_points']='21';$store('loyalty_extra_points_rules',$r);return;}
if ('verify-held'===$mode){$before=json_decode(file_get_contents($path),true);$now=$snapshot();loyf_equal($before,$now,'Held native settings POST keeps raw options/balances/logs exact');loyf_equal('round_up',get_option('loyalty_points_rounding'),'Independent native General option saved');return;}
if ('fresh-blank'===$mode){$store('loyalty_points_using_rules',array('min_points'=>'','max_points'=>'','min_cart'=>''));$store('loyalty_points_using_point','no');file_put_contents($path.'.blank',YOWCL_Free_Migrations::read('loyalty_points_using_rules'));return;}
if ('verify-blank'===$mode){loyf_equal(file_get_contents($path.'.blank'),YOWCL_Free_Migrations::read('loyalty_points_using_rules'),'Unconfigured disabled redemption preserves missing fields');loyf_equal('no',YOWCL_Free_Migrations::read('loyalty_points_using_point'),'Unconfigured redemption remains disabled');loyf_equal('round_down',get_option('loyalty_points_rounding'),'Independent General field saves with unconfigured redemption');return;}
if ('read-fault'===$mode){file_put_contents($path.'.read-fault',serialize(array(YOWCL_Free_Migrations::read('loyalty_points_using_rules'),YOWCL_Free_Migrations::read('loyalty_points_using_point'))));return;}
if ('verify-read-fault'===$mode){loyf_equal(file_get_contents($path.'.read-fault'),serialize(array(YOWCL_Free_Migrations::read('loyalty_points_using_rules'),YOWCL_Free_Migrations::read('loyalty_points_using_point'))),'Late witness read failure preserves redemption pair and using flag');return;}
if ('subprecision'===$mode){$r=YOWCL_Free_Migrations::canonical('redemption');$r['amount']='0.0010';$store('loyalty_points_using_rules',serialize($r));file_put_contents($path.'.subprecision',YOWCL_Free_Migrations::read('loyalty_points_using_rules'));return;}
if ('verify-subprecision'===$mode){loyf_equal(file_get_contents($path.'.subprecision'),YOWCL_Free_Migrations::read('loyalty_points_using_rules'),'Positive subprecision raw amount stays exact despite zero display');loyf_equal('yes',YOWCL_Free_Migrations::read('loyalty_points_using_point'),'Positive subprecision redemption remains enabled');loyf_assert(YOWCL_Free_Migrations::new_redemption_allowed(),'Ordinary explicit enablement keeps scoped guard active');return;}
if ('precision'===$mode){$r=YOWCL_Free_Migrations::canonical('redemption');$r['amount']='0.7010';$store('loyalty_points_using_rules',serialize($r));file_put_contents($path.'.precision',YOWCL_Free_Migrations::read('loyalty_points_using_rules'));return;}
if ('verify-precision'===$mode){loyf_equal(file_get_contents($path.'.precision'),YOWCL_Free_Migrations::read('loyalty_points_using_rules'),'Unchanged currency display preserves extra-precision raw string, wrapper and dormant fields');return;}
if ('verify-partial'===$mode){$r=get_option('loyalty_points_earning_rules');loyf_equal('2',$r['customer']['points'],'Native independent earning save survives rounding UPDATE failure');loyf_equal('round_up',get_option('loyalty_points_rounding'),'Rejected native rounding remains old value');return;}
if ('verify-first'===$mode){$c=YOWCL_Free_First_Purchase::configuration();loyf_assert($c['effective'] && $c['ready'],'Independent First Purchase enabled with activation cutoff');loyf_equal(31,(int)$c['points'],'Independent First Purchase amount');return;}
if ('verify-email'===$mode){$c=YOWCL_Free_Migrations::canonical('email_reward');loyf_equal('Merchant updated subject',$c['subject'],'Ready native email field saved');loyf_equal('Merchant heading',$c['heading'],'Ready native email preserves heading');loyf_equal('private-dormant',$c['unknown'],'Ready native email preserves dormant fields');return;}
if ('verify-ready'===$mode){loyf_equal('1',YOWCL_Free_Migrations::read(YOWCL_Free_Migrations::witness('redemption')),'Redemption witnessed explicitly');$r=YOWCL_Free_Migrations::canonical('redemption');loyf_equal(0.7,$r['amount'],'Native fractional save keeps float term');loyf_equal('private-dormant',$r['unknown'],'Fractional save preserves dormant term');return;}
if ('verify-value'===$mode){$before=json_decode(file_get_contents($path),true);$now=$snapshot();loyf_equal($before['meta'],$now['meta'],'Browser decisions create no points');loyf_equal($before['logs'],$now['logs'],'Browser decisions create no logs');return;}
throw new RuntimeException('Unknown migration review fixture');

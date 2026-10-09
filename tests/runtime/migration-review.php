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
 $before=json_decode(file_get_contents($path),true); $current=$snapshot();
 // Additive schema columns are admitted; every original row/ID/column stays exact.
 foreach($current['logs'] as $i=>$row){$current['logs'][$i]=array_intersect_key($row,$before['logs'][$i]??array());}
 loyf_equal($before,$current,'Actual old-Free upgrade without capture preserves existing raw terms, balances and logs');
 foreach(YOWCL_Free_Migrations::features() as $f){loyf_assert(!YOWCL_Free_Migrations::ready($f),'Actual uncaptured upgrade held '.$f);loyf_equal(null,YOWCL_Free_Migrations::read(YOWCL_Free_Migrations::witness($f).'_before'),'No manufactured provenance '.$f);}
 ob_start();YOWCL_Free_Migrations::render_review();$html=ob_get_clean();loyf_assert(strpos($html,'Loyalty Migration Review')!==false && strpos($html,'<pre')===false,'Native bounded review without raw JSON');
 $after=$snapshot();foreach($after['logs'] as $i=>$row){$after['logs'][$i]=array_intersect_key($row,$before['logs'][$i]??array());}
 loyf_equal($before,$after,'Review GET does not write reviewed data');
 echo "Actual old-Free upgrade WITHOUT pre-capture PASS: eight held features, exact raw options/balances/logs, read-only review.\n";return;
}
$store=static function($name,$value)use($wpdb){$wpdb->delete($wpdb->options,array('option_name'=>$name));if(null!==$value){$wpdb->insert($wpdb->options,array('option_name'=>$name,'option_value'=>maybe_serialize($value),'autoload'=>'no'));}wp_cache_delete($name,'options');wp_cache_delete('alloptions','options');wp_cache_delete('notoptions','options');};
if ('browser-seed'===$mode) {
 foreach(YOWCL_Free_Migrations::features() as $f){foreach(array('','_before','_resolution') as $suffix){$store(YOWCL_Free_Migrations::witness($f).$suffix,null);}}
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
 file_put_contents($path,wp_json_encode($snapshot()));echo "Native review browser fixtures seeded; no automatic resolutions.\n";return;
}
if ('stale'===$mode){$r=maybe_unserialize(YOWCL_Free_Migrations::read('loyalty_extra_points_rules'));$r['login_points']='21';$store('loyalty_extra_points_rules',$r);return;}
if ('verify-held'===$mode){$before=json_decode(file_get_contents($path),true);$now=$snapshot();loyf_equal($before,$now,'Held native settings POST keeps raw options/balances/logs exact');loyf_equal('round_up',get_option('loyalty_points_rounding'),'Independent native General option saved');return;}
if ('fresh-blank'===$mode){$store('loyalty_points_using_rules',array('min_points'=>'','max_points'=>'','min_cart'=>''));$store('loyalty_points_using_point','no');file_put_contents($path.'.blank',YOWCL_Free_Migrations::read('loyalty_points_using_rules'));return;}
if ('verify-blank'===$mode){loyf_equal(file_get_contents($path.'.blank'),YOWCL_Free_Migrations::read('loyalty_points_using_rules'),'Unconfigured disabled redemption preserves missing fields');loyf_equal('no',YOWCL_Free_Migrations::read('loyalty_points_using_point'),'Unconfigured redemption remains disabled');loyf_equal('round_up',get_option('loyalty_points_rounding'),'Independent General field saves with unconfigured redemption');return;}
if ('read-fault'===$mode){file_put_contents($path.'.read-fault',serialize(array(YOWCL_Free_Migrations::read('loyalty_points_using_rules'),YOWCL_Free_Migrations::read('loyalty_points_using_point'))));return;}
if ('verify-read-fault'===$mode){loyf_equal(file_get_contents($path.'.read-fault'),serialize(array(YOWCL_Free_Migrations::read('loyalty_points_using_rules'),YOWCL_Free_Migrations::read('loyalty_points_using_point'))),'Late witness read failure preserves redemption pair and using flag');return;}
if ('precision'===$mode){$r=YOWCL_Free_Migrations::canonical('redemption');$r['amount']='0.7010';$store('loyalty_points_using_rules',serialize($r));file_put_contents($path.'.precision',YOWCL_Free_Migrations::read('loyalty_points_using_rules'));return;}
if ('verify-precision'===$mode){loyf_equal(file_get_contents($path.'.precision'),YOWCL_Free_Migrations::read('loyalty_points_using_rules'),'Unchanged currency display preserves extra-precision raw string, wrapper and dormant fields');return;}
if ('verify-partial'===$mode){$r=get_option('loyalty_points_earning_rules');loyf_equal('2',$r['customer']['points'],'Native independent earning save survives rounding UPDATE failure');loyf_equal('round_up',get_option('loyalty_points_rounding'),'Rejected native rounding remains old value');return;}
if ('verify-first'===$mode){$c=YOWCL_Free_First_Purchase::configuration();loyf_assert($c['effective'] && $c['ready'],'Independent First Purchase enabled with activation cutoff');loyf_equal(31,(int)$c['points'],'Independent First Purchase amount');return;}
if ('verify-email'===$mode){$c=YOWCL_Free_Migrations::canonical('email_reward');loyf_equal('Merchant updated subject',$c['subject'],'Ready native email field saved');loyf_equal('Merchant heading',$c['heading'],'Ready native email preserves heading');loyf_equal('private-dormant',$c['unknown'],'Ready native email preserves dormant fields');return;}
if ('verify-ready'===$mode){loyf_equal('1',YOWCL_Free_Migrations::read(YOWCL_Free_Migrations::witness('redemption')),'Redemption witnessed explicitly');$r=YOWCL_Free_Migrations::canonical('redemption');loyf_equal(0.7,$r['amount'],'Native fractional save keeps float term');loyf_equal('private-dormant',$r['unknown'],'Fractional save preserves dormant term');return;}
if ('verify-value'===$mode){$before=json_decode(file_get_contents($path),true);$now=$snapshot();loyf_equal($before['meta'],$now['meta'],'Browser decisions create no points');loyf_equal($before['logs'],$now['logs'],'Browser decisions create no logs');return;}
throw new RuntimeException('Unknown migration review fixture');

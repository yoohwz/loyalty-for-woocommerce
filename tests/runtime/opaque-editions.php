<?php
/** SQL seeding/restoration is confined to the disposable edition runner. */
if(!defined('LOY_RUNTIME_DISPOSABLE')||!LOY_RUNTIME_DISPOSABLE||strpos((string)realpath(ABSPATH),'/Local Sites/')!==false){throw new RuntimeException('Disposable edition runtime required');}
global $wpdb;
function opaque_edition_equal($expected,$actual,$message){if($expected!==$actual){throw new RuntimeException($message);}}
$targets=array('signup'=>'loyalty_extra_points_rules','login'=>'loyalty_extra_points_rules','review'=>'loyalty_extra_reviews_gamification_rules','levelup'=>'loyalty_extra_reviews_gamification_rules','redemption'=>'loyalty_points_using_rules','email_reward'=>'woocommerce_yowcl_loyalty_points_reward_settings','email_deduct'=>'woocommerce_yowcl_loyalty_points_deduct_settings','email_level'=>'woocommerce_yowcl_loyalty_level_update_settings');
$names=array_values(array_unique(array_values($targets)));$names=array_merge($names,array('loyalty_notification_email','loyalty_extra_levelup_points_rules'));
foreach($targets as$f=>$target){foreach(array('','_before','_resolution')as$s){$names[]='loyf_migration_'.$f.'_v1'.$s;}}
$snapshot=static function()use($wpdb,$names){$rows=array();foreach($names as$n){$rows[$n]=$wpdb->get_row($wpdb->prepare("SELECT option_value,autoload FROM {$wpdb->options} WHERE option_name=%s",$n),ARRAY_A);}return array('options'=>$rows,'ledger'=>$wpdb->get_results('SELECT * FROM '.YOWCL_Points_Log::table_name().' ORDER BY id',ARRAY_A),'meta'=>$wpdb->get_results("SELECT * FROM {$wpdb->usermeta} WHERE meta_key IN ('user_points','user_earning_points','{$wpdb->prefix}capabilities','{$wpdb->prefix}user_level') OR meta_key LIKE 'loyalty\\_%' OR meta_key LIKE 'yol\\_%' OR meta_key LIKE '\\_yol\\_%' OR meta_key LIKE '\\_yo\\_loyalty\\_%' OR meta_key LIKE '\\_yowcl\\_%' OR meta_key LIKE '\\_loyf\\_%' ORDER BY umeta_id",ARRAY_A));};
$file=getenv('LOYF13_OPAQUE_SNAPSHOT');if(!$file){throw new RuntimeException('Opaque fixture snapshot path required');}$phase=$args[0]??'';
if('seed'===$phase){
 $original=$snapshot();$raws=array('opaque-private-literal',serialize('opaque-private-scalar'),serialize((object)array('unknown'=>'opaque-private-object')),'a:2:{s:7:"unknown";s:21:"opaque-private-broken";');
 foreach(array_values(array_unique(array_values($targets)))as$i=>$n){$wpdb->delete($wpdb->options,array('option_name'=>$n));$wpdb->insert($wpdb->options,array('option_name'=>$n,'option_value'=>$raws[$i%4],'autoload'=>$i%2?'no':'yes'));}
 wp_cache_flush();file_put_contents($file,serialize(array('original'=>$original,'held'=>$snapshot())));echo "Opaque edition fixture seeded; test-only SQL, original rows retained outside plugin.\n";return;
}
$data=unserialize(file_get_contents($file));opaque_edition_equal($data['held'],$snapshot(),'Opaque actual edition bootstrap changed target/source/evidence/witness/balance/log/role data');
if('free'===$phase){
 if(!class_exists('YOWCL_Free_Migrations',false)||!YOWCL_Free_Core::owns()){throw new RuntimeException('Free edition owner required');}wp_set_current_user(1);YOWCL_Free_Migrations::run();
 foreach($targets as$f=>$n){if(YOWCL_Free_Migrations::readable($f)||YOWCL_Free_Migrations::canonical($f)!==array()){throw new RuntimeException('Opaque edition admitted policy');}foreach(array('canonical','legacy','disable')as$mode){try{YOWCL_Free_Migrations::preview($f,$mode);throw new LogicException('Opaque edition resolution offered');}catch(RuntimeException$e){opaque_edition_equal('migration_opaque_target',$e->getMessage(),'Opaque edition diagnostic');}}}
 ob_start();YOWCL_Free_Migrations::notices();$html=ob_get_clean();if(strpos($html,'unsupported format')===false||strpos($html,'opaque-private')!==false||strpos($html,'<form')!==false){throw new RuntimeException('Opaque edition notice unsafe');}
 opaque_edition_equal($data['held'],$snapshot(),'Free opaque retry changed retained rows');
}elseif('restore'===$phase){
 foreach(array_unique(array_values($targets))as$n){$row=$data['original']['options'][$n];$wpdb->delete($wpdb->options,array('option_name'=>$n));if(null!==$row){$wpdb->insert($wpdb->options,array('option_name'=>$n,'option_value'=>$row['option_value'],'autoload'=>$row['autoload']));}}
 wp_cache_flush();opaque_edition_equal($data['original'],$snapshot(),'Disposable opaque fixture restoration is exact');
}else{throw new RuntimeException('Unknown opaque edition phase');}
echo "Installed opaque edition {$phase} PASS: exact raw/autoload/evidence/witness/value/log/roles; entitlement SIMULATED.\n";

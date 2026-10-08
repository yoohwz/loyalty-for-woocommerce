<?php
require_once __DIR__.'/assertions.php';
global $wpdb;
wp_set_current_user(1);
$account='loyalty_extra_points_rules'; $merged='loyalty_extra_reviews_gamification_rules';
$names=array($account,$merged,'loyalty_points_using_rules','loyalty_notification_email','loyalty_extra_levelup_points_rules');
foreach(YOWCL_Free_Migrations::features() as $f){$names[]=YOWCL_Free_Migrations::witness($f);$names[]=YOWCL_Free_Migrations::witness($f).'_before';$names[]=YOWCL_Free_Migrations::witness($f).'_resolution';}
$names=array_merge($names,array('wc_loyalty_version','yowcl_email_legacy_options_migrated','woocommerce_yowcl_loyalty_points_reward_settings','woocommerce_yowcl_loyalty_points_deduct_settings','woocommerce_yowcl_loyalty_level_update_settings'));
$saved=array();foreach($names as $n){$saved[$n]=$wpdb->get_row($wpdb->prepare("SELECT option_value,autoload FROM {$wpdb->options} WHERE option_name=%s",$n),ARRAY_A);}
$clear=function($f)use($wpdb){foreach(array('','_before','_resolution') as $suffix){delete_option(YOWCL_Free_Migrations::witness($f).$suffix);}};
$choose=function($f,$mode){$spec=YOWCL_Free_Migrations::preview($f,$mode);YOWCL_Free_Migrations::resolve($f,$mode,hash('sha256',serialize($spec)),wp_create_nonce('loyf_resolve_'.$f));};
try {
 foreach(YOWCL_Free_Migrations::features() as $f){$clear($f);}
 update_option('wc_loyalty_version','1.2.2');
 update_option($account,serialize(array('signup_points'=>17,'signup_enabled'=>'no','login_points'=>19,'login_enabled'=>'no','unknown'=>'009')));
 update_option($merged,array('review_points'=>23,'review_enabled'=>'yes','levelup_points'=>array('subscriber'=>array('awarded'=>29)),'levelup_enabled'=>'yes','unknown'=>'007'));
 update_option('loyalty_points_using_rules',serialize(array('points'=>10,'amount'=>1,'unknown'=>'003')));
 delete_option('loyalty_notification_email');
 foreach(array('points_reward','points_deduct','level_update') as $id){update_option('woocommerce_yowcl_loyalty_'.$id.'_settings',array('enabled'=>'yes','subject'=>'Merchant '.$id));}
 $before=array();foreach(array($account,$merged,'loyalty_points_using_rules','woocommerce_yowcl_loyalty_points_reward_settings','woocommerce_yowcl_loyalty_points_deduct_settings','woocommerce_yowcl_loyalty_level_update_settings') as $n){$before[$n]=YOWCL_Free_Migrations::read($n);}
 YOWCL_Free_Migrations::run();
 foreach($before as $n=>$raw){loyf_equal($raw,YOWCL_Free_Migrations::read($n),'Premium raw target preservation '.$n);}
 foreach(YOWCL_Free_Migrations::features() as $f){loyf_assert(!YOWCL_Free_Migrations::ready($f),'Ambiguous feature must hold '.$f);loyf_equal(null,YOWCL_Free_Migrations::read(YOWCL_Free_Migrations::witness($f).'_before'),'No ambiguous first evidence '.$f);}
 foreach(array('signup','login','review') as $f){loyf_equal(0,YOWCL_Free_Core::extra($f),'Held reward '.$f);}
 loyf_equal(array(),YOWCL_Free_Core::level_rules(),'Held level terms');
 loyf_equal(array(),YOWCL_Free_Cart::rules(),'Held new redemption');
 foreach(array('Points_Reward','Points_Deduct','Level_Update') as $id){$class='YOWCL_WC_Email_Loyalty_'.$id;$mail=new $class();loyf_assert(!$mail->is_enabled(),'Held native mail '.$id);}
 $choose('signup','canonical');
 loyf_equal($before[$account],YOWCL_Free_Migrations::read($account),'Keep canonical exact wrapper bytes');
 loyf_assert(YOWCL_Free_Migrations::ready('signup')&&!YOWCL_Free_Migrations::ready('login'),'Per-feature resolution isolation');
 // A stale reviewed source/target cannot be adopted.
 $stale=YOWCL_Free_Migrations::preview('login','legacy');$terms=maybe_unserialize(get_option($account));$terms['login_points']=31;update_option($account,serialize($terms));
 try{YOWCL_Free_Migrations::resolve('login','legacy',hash('sha256',serialize($stale)),wp_create_nonce('loyf_resolve_login'));throw new RuntimeException('Stale choice accepted');}catch(RuntimeException $e){loyf_equal('migration_resolution_stale',$e->getMessage(),'Stale choice denial');}
 $choose('login','legacy');loyf_equal(31,YOWCL_Free_Core::extra('login'),'Explicit legacy adoption');
 // Malformed witness is never silently reset by a resolution.
 update_option(YOWCL_Free_Migrations::witness('review'),'broken');
 try{$choose('review','canonical');throw new RuntimeException('Malformed witness resolved');}catch(RuntimeException $e){loyf_equal('migration_malformed_witness',$e->getMessage(),'Malformed witness denial');}
 delete_option(YOWCL_Free_Migrations::witness('review'));
 $choose('review','disable');loyf_equal('no',get_option($merged)['review_enabled'],'Explicit disable');
 // Unknown response after target write: same reviewed intent may complete only
 // its exact post-image, preserving another pair and unknown container fields.
 $spec=YOWCL_Free_Migrations::preview('levelup','disable');
 $fail=function($sql)use($wpdb){return strpos($sql,"INSERT INTO {$wpdb->options}")!==false && strpos($sql,"'loyf_migration_levelup_v1',")!==false?'SELECT * FROM loyf_missing_witness':$sql;};
 $wpdb->suppress_errors(true);add_filter('query',$fail);try{$choose('levelup','disable');}catch(Throwable $e){}remove_filter('query',$fail);$wpdb->suppress_errors(false);
 loyf_assert(!YOWCL_Free_Migrations::ready('levelup'),'Failed witness holds');
 loyf_equal($spec['before'],YOWCL_Free_Migrations::read($merged),'Witness failure rolls target back');
 // Model a retained pre-atomic target commit without its witness.
 $terms=get_option($merged);$terms=array_replace($terms,$spec['patch']);update_option($merged,$terms);
 $terms=get_option($merged);$terms['review_points']=41;$terms['unknown']='keep';update_option($merged,$terms);
 $choose('levelup','disable');loyf_assert(YOWCL_Free_Migrations::ready('levelup'),'Exact intended post-image retry');loyf_equal(41,get_option($merged)['review_points'],'Other feature survives retry');loyf_equal('keep',get_option($merged)['unknown'],'Unknown survives retry');
 $clear('levelup');$spec=YOWCL_Free_Migrations::preview('levelup','disable');update_option(YOWCL_Free_Migrations::witness('levelup').'_resolution',$spec);
 $terms=get_option($merged);$terms['levelup_points']=array('subscriber'=>array('awarded'=>51));$terms['levelup_enabled']='yes';update_option($merged,$terms);
 try{$choose('levelup','disable');throw new RuntimeException('Later merchant edit overwritten');}catch(RuntimeException $e){loyf_equal('migration_target_changed',$e->getMessage(),'Changed owned pair holds');}
 loyf_equal(51,get_option($merged)['levelup_points']['subscriber']['awarded'],'Later owned edit retained');
 wp_set_current_user(0);try{$choose('email_reward','canonical');throw new RuntimeException('Unauthorized resolution');}catch(RuntimeException $e){loyf_equal('migration_resolution_denied',$e->getMessage(),'Capability denial');}
 echo "Migration boundaries: PASS (Premium hold, wrapped bytes, per-feature choices, stale/denied POST terms, target/witness retry and intervening owned edits).\n";
}finally{
 foreach($saved as $n=>$row){$wpdb->delete($wpdb->options,array('option_name'=>$n));if(null!==$row){$wpdb->insert($wpdb->options,array('option_name'=>$n,'option_value'=>$row['option_value'],'autoload'=>$row['autoload']));}}
 wp_cache_flush();wp_set_current_user(1);
}

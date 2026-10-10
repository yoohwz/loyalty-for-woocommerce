<?php
require_once __DIR__.'/assertions.php';
global $wpdb;
wp_set_current_user(1);
$die=static function(){return static function($message){throw new RuntimeException(is_wp_error($message)?$message->get_error_message():(string)$message);};};add_filter('wp_die_handler',$die);add_filter('wp_die_ajax_handler',$die);
$account='loyalty_extra_points_rules'; $merged='loyalty_extra_reviews_gamification_rules';
$names=array($account,$merged,'loyalty_points_using_rules','loyalty_notification_email','loyalty_extra_levelup_points_rules');
foreach(YOWCL_Free_Migrations::features() as $f){$names[]=YOWCL_Free_Migrations::witness($f);$names[]=YOWCL_Free_Migrations::witness($f).'_before';$names[]=YOWCL_Free_Migrations::witness($f).'_resolution';foreach(array('_background','_supersession','_automatic','_automatic_background','_automatic_enabled')as$s){$names[]=YOWCL_Free_Migrations::witness($f).$s;}}
$names=array_merge($names,array('wc_loyalty_version','yowcl_email_legacy_options_migrated','woocommerce_yowcl_loyalty_points_reward_settings','woocommerce_yowcl_loyalty_points_deduct_settings','woocommerce_yowcl_loyalty_level_update_settings'));
$saved=array();foreach($names as $n){$saved[$n]=$wpdb->get_row($wpdb->prepare("SELECT option_value,autoload FROM {$wpdb->options} WHERE option_name=%s",$n),ARRAY_A);}
$recovery_user=0;
$clear=function($f)use($wpdb){foreach(array('','_before','_resolution','_background','_supersession','_automatic','_automatic_background','_automatic_enabled') as $suffix){delete_option(YOWCL_Free_Migrations::witness($f).$suffix);}};
$choose=function($f,$mode){$spec=YOWCL_Free_Migrations::preview($f,$mode);YOWCL_Free_Migrations::resolve($f,$mode,YOWCL_Free_Migrations::resolution_fingerprint($f,$spec),wp_create_nonce('loyf_resolve_'.$f));};
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
 // Held level terms must still repair an independently proven committed marker.
 $choose('levelup','canonical');$recovery_user=wp_insert_user(array('user_login'=>'migration_recovery_'.substr(wp_generate_uuid4(),0,8),'user_email'=>'migration-recovery@example.invalid','user_pass'=>'disposable-only','role'=>'subscriber'));loyf_assert(!is_wp_error($recovery_user),'Native recovery customer');
 YOWCL_Free_Core::level_bonus($recovery_user,'subscriber');$key='reward:level_up:'.$recovery_user.':subscriber';$committed=YOWCL_Points_Transaction::find($key);loyf_assert(is_array($committed),'Committed level event established');$available=get_user_meta($recovery_user,'user_points',true);$earned=get_user_meta($recovery_user,'user_earning_points',true);
 $clear('levelup');delete_user_meta($recovery_user,'_yo_loyalty_levelup_awarded_roles');YOWCL_Free_Core::level_bonus($recovery_user,'subscriber');
 loyf_equal($committed,YOWCL_Points_Transaction::find($key),'Held level recovery exact committed row/ID');loyf_equal($available,get_user_meta($recovery_user,'user_points',true),'Held level recovery no new available value');loyf_equal($earned,get_user_meta($recovery_user,'user_earning_points',true),'Held level recovery no new earned value');loyf_assert(in_array('subscriber',(array)get_user_meta($recovery_user,'_yo_loyalty_levelup_awarded_roles',true),true),'Held level recovery restores native compatibility marker');loyf_assert(!YOWCL_Free_Migrations::ready('levelup'),'Recovery does not fabricate feature witness');
 // Existing origin-less partial evidence cannot authorize a Premium target write.
 $partial=YOWCL_Free_Migrations::preview('signup','legacy');update_option(YOWCL_Free_Migrations::witness('signup').'_before',$partial);YOWCL_Free_Migrations::run();
 loyf_equal($before[$account],YOWCL_Free_Migrations::read($account),'Origin-less frozen evidence holds unchanged');loyf_assert(!YOWCL_Free_Migrations::ready('signup'),'Origin-less partial never completes');
 delete_option(YOWCL_Free_Migrations::witness('signup').'_before');
 // Malformed native POSTs cannot change reviewed terms or create a resolution.
 $valid=YOWCL_Free_Migrations::preview('signup','canonical');$post=array('feature'=>'signup','mode'=>'canonical','fingerprint'=>hash('sha256',serialize($valid)),'_wpnonce'=>wp_create_nonce('loyf_resolve_signup'));
 $method=$_SERVER['REQUEST_METHOD']??null;
 foreach(array(array($post,'GET'),array(array_replace($post,array('_wpnonce'=>'invalid')),'POST'),array(array_replace($post,array('feature'=>array('signup'))),'POST'),array(array_replace($post,array('fingerprint'=>array('invalid'))),'POST')) as $denied){
  $_POST=$denied[0];$_SERVER['REQUEST_METHOD']=$denied[1];try{loyf_retained_protocol('resolve');throw new RuntimeException('Denied native POST accepted');}catch(RuntimeException $e){loyf_equal('migration_resolution_denied',$e->getMessage(),'Native POST denial');}
 }
 $_POST=array();if(null===$method){unset($_SERVER['REQUEST_METHOD']);}else{$_SERVER['REQUEST_METHOD']=$method;}
 loyf_equal($before[$account],YOWCL_Free_Migrations::read($account),'Denied native POST leaves raw target intact');loyf_equal(null,YOWCL_Free_Migrations::read(YOWCL_Free_Migrations::witness('signup').'_resolution'),'Denied native POST leaves no intent');
 $choose('signup','canonical');
 loyf_equal($before[$account],YOWCL_Free_Migrations::read($account),'Keep canonical exact wrapper bytes');
 loyf_assert(YOWCL_Free_Migrations::ready('signup')&&!YOWCL_Free_Migrations::ready('login'),'Per-feature resolution isolation');
 // Previously completed origin-less evidence is visible without rollback or unknown fields.
 $historical=YOWCL_Free_Migrations::preview('signup','canonical');update_option(YOWCL_Free_Migrations::witness('signup').'_before',$historical);
 $evidence=YOWCL_Free_Migrations::historical_evidence('signup');loyf_assert(is_array($evidence)&&isset($evidence['before'],$evidence['migration'],$evidence['current']),'Completed historical evidence visible');
 loyf_assert(false===strpos(wp_json_encode($evidence),'unknown'),'Historical diagnostic excludes dormant unknown fields');ob_start();YOWCL_Free_Migrations::notices();$notice=ob_get_clean();loyf_assert(false===strpos($notice,'Historical Loyalty migration evidence'),'Historical evidence is not a persistent notice');loyf_assert(!has_action('admin_menu',array('YOWCL_Free_Migrations','register_review')),'Historical evidence has no migration page');
 loyf_equal($before[$account],YOWCL_Free_Migrations::read($account),'Historical diagnostic performs no rollback');wp_set_current_user(0);loyf_equal(null,YOWCL_Free_Migrations::historical_evidence('signup'),'No unauthenticated historical assessment');wp_set_current_user(1);
 // A stale reviewed source/target cannot be adopted.
 $stale=YOWCL_Free_Migrations::preview('login','legacy');$terms=maybe_unserialize(get_option($account));$terms['login_points']=31;update_option($account,serialize($terms));
 try{YOWCL_Free_Migrations::resolve('login','legacy',hash('sha256',serialize($stale)),wp_create_nonce('loyf_resolve_login'));throw new RuntimeException('Stale choice accepted');}catch(RuntimeException $e){loyf_equal('migration_resolution_stale',$e->getMessage(),'Stale choice denial');}
 $choose('login','legacy');loyf_equal(31,YOWCL_Free_Core::extra('login'),'Explicit legacy adoption');
 // Malformed witness is never silently reset by a resolution.
 update_option(YOWCL_Free_Migrations::witness('review'),'broken');
 try{$choose('review','canonical');throw new RuntimeException('Malformed witness resolved');}catch(RuntimeException $e){loyf_equal('migration_malformed_witness',$e->getMessage(),'Malformed witness denial');}
 delete_option(YOWCL_Free_Migrations::witness('review'));
 $choose('review','disable');loyf_equal('no',get_option($merged)['review_enabled'],'Explicit disable');
 // Actual loss of the original connection after target UPDATE must not replay
 // a standalone success witness on wpdb's replacement/autocommit connection.
 $clear('levelup');$atomic=YOWCL_Free_Migrations::preview('levelup','disable');$killed=false;
 $disconnect=static function($sql)use($wpdb,&$killed){
  if(!$killed&&strpos($sql,"INSERT INTO {$wpdb->options}")!==false&&strpos($sql,"'loyf_migration_levelup_v1',")!==false){
   $killer=new mysqli(getenv('LOY_DB_HOST'),DB_USER,DB_PASSWORD,DB_NAME,(int)(getenv('LOY_DB_PORT')?:3306));$killer->query('KILL '.(int)mysqli_thread_id($wpdb->dbh));$killer->close();$killed=true;
  }return $sql;
 };
 add_filter('query',$disconnect,PHP_INT_MAX);try{$choose('levelup','disable');throw new RuntimeException('Disconnected migration reported success');}catch(RuntimeException $e){loyf_assert(in_array($e->getMessage(),array('migration_ownership_lost','migration_write_failed'),true),'Original connection loss classified');}finally{remove_filter('query',$disconnect,PHP_INT_MAX);}
 loyf_assert($killed,'Native witness boundary disconnected actual MySQL connection');$wpdb->check_connection(false);wp_cache_flush();loyf_equal($atomic['before'],YOWCL_Free_Migrations::read($merged),'Disconnected target transaction rolls back');loyf_assert(!YOWCL_Free_Migrations::ready('levelup'),'Replacement connection cannot fabricate witness');$choose('levelup','disable');loyf_assert(YOWCL_Free_Migrations::ready('levelup'),'Explicit reviewed intent recovers on new owner');$clear('levelup');
 // A merchant callback's outer transaction must never be implicitly committed.
 $outer=$wpdb->dbh;mysqli_query($outer,'START TRANSACTION');$wpdb->query($wpdb->prepare("INSERT INTO {$wpdb->options} (option_name,option_value,autoload) VALUES (%s,%s,'no')",'loyf13_outer_transaction_probe','uncommitted'));
 try{$choose('email_reward','canonical');throw new RuntimeException('Outer transaction committed by migration');}catch(RuntimeException $e){loyf_equal('migration_transaction_unavailable',$e->getMessage(),'Outer transaction denied');}finally{mysqli_query($outer,'ROLLBACK');wp_cache_flush();}
 loyf_equal(null,YOWCL_Free_Migrations::read('loyf13_outer_transaction_probe'),'Outer caller rollback remains authoritative');loyf_assert(!YOWCL_Free_Migrations::ready('email_reward'),'No outer-transaction feature success');
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
 echo "Migration boundaries: PASS (Premium hold, wrapped bytes, per-feature choices, stale/denied POST terms, original-connection loss/outer transaction, committed level recovery, target/witness retry and intervening owned edits).\n";
}finally{
 if(is_int($recovery_user)&&$recovery_user>0){$wpdb->delete(YOWCL_Points_Log::table_name(),array('user_id'=>$recovery_user));require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($recovery_user);}
 foreach($saved as $n=>$row){$wpdb->delete($wpdb->options,array('option_name'=>$n));if(null!==$row){$wpdb->insert($wpdb->options,array('option_name'=>$n,'option_value'=>$row['option_value'],'autoload'=>$row['autoload']));}}
 remove_filter('wp_die_handler',$die);remove_filter('wp_die_ajax_handler',$die);wp_cache_flush();wp_set_current_user(1);
}

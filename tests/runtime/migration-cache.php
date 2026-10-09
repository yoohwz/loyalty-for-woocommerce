<?php
require_once __DIR__.'/assertions.php';
global $wpdb;
$base=getenv('LOYF_CACHE_BARRIER');$role=$args[0]??'';
$wait=static function($path){$until=microtime(true)+20;while(!is_file($path)&&microtime(true)<$until){usleep(20000);}loyf_assert(is_file($path),'Cache handover barrier '.$path);};
if('reader'===$role){
 $user=(int)$args[1];$old=(int)$args[2];$fresh=(int)$args[3];
 $emails=array();foreach(array('Points_Reward','Points_Deduct','Level_Update')as$id){$class='YOWCL_WC_Email_Loyalty_'.$id;$emails[]=new $class();}
 $cached=maybe_unserialize(get_option('loyalty_extra_reviews_gamification_rules'));loyf_equal(99,$cached['review_points'],'Reader warms native pre-image');
 foreach($emails as$email){loyf_equal('yes',$email->enabled,'Native Woo instance warmed before resolution');loyf_equal('Retained merchant subject',$email->settings['subject'],'Held custom email settings retained');loyf_assert(!$email->is_enabled(),'Held email before handover');}
 file_put_contents($base.'.reader','ready');$wait($base.'.go');
 loyf_equal(99,maybe_unserialize(get_option('loyalty_extra_reviews_gamification_rules'))['review_points'],'In-flight reader retains local pre-image');
 foreach(array('signup','login','review')as$f){loyf_assert(YOWCL_Free_Migrations::ready($f),'Committed witness '.$f);loyf_equal(0,YOWCL_Free_Core::extra($f),'Committed disabled policy ignores local cache '.$f);}
 loyf_equal(array(),YOWCL_Free_Core::level_rules(),'Disabled level policy ignores local cache');loyf_equal(array(),YOWCL_Free_Cart::rules(),'Disabled redemption policy ignores local cache');
 $rows=loyf_rows($user);do_action('comment_post',$fresh,1);loyf_equal($rows,loyf_rows($user),'Actual registered review callback creates no fresh value');
 do_action('comment_post',$old,1);loyf_equal($rows,loyf_rows($user),'Committed review recovery creates no new row');loyf_equal('1',get_comment_meta($old,'_yowcl_review_reward_awarded',true),'Committed review marker recovered');
 foreach($emails as$email){loyf_assert(!$email->is_enabled(),'Warm native email honors committed disable');}
 $mail=count($GLOBALS['loyf_mail']);$emails[0]->trigger($user,13,99);loyf_equal($mail,count($GLOBALS['loyf_mail']),'Warm native reward email does not send after disable');
 $failure=static function($sql){return strpos($sql,'SELECT option_value')!==false&&strpos($sql,'loyalty_extra_reviews_gamification_rules')!==false?'SELECT * FROM loyf_cache_unavailable_target':$sql;};
 $old_errors=$wpdb->suppress_errors(true);add_filter('query',$failure,PHP_INT_MAX);try{loyf_equal(0,YOWCL_Free_Core::extra('review'),'Unavailable committed target holds new review value');loyf_equal(array(),YOWCL_Free_Core::level_rules(),'Unavailable committed target holds new level value');}finally{remove_filter('query',$failure,PHP_INT_MAX);$wpdb->suppress_errors($old_errors);}
 echo "In-flight local-cache handover PASS.\n";return;
}
if('bootstrap'===$role){
 loyf_assert(YOWCL_Free_Migrations::ready('review'),'Bootstrap waiter sees committed witness');
 loyf_equal(0,maybe_unserialize(get_option('loyalty_extra_reviews_gamification_rules'))['review_points'],'Completed bootstrap refreshes pre-lock local cache');
 loyf_equal(0,YOWCL_Free_Core::extra('review'),'Bootstrap waiter uses disabled policy');echo "Pre-lock bootstrap handover PASS.\n";return;
}
wp_set_current_user(1);
$account='loyalty_extra_points_rules';$merged='loyalty_extra_reviews_gamification_rules';$redemption='loyalty_points_using_rules';
$targets=array($account=>serialize(array('signup_enabled'=>'yes','signup_points'=>99,'login_enabled'=>'yes','login_points'=>99,'unknown'=>'009')),$merged=>serialize(array('review_enabled'=>'yes','review_points'=>99,'levelup_enabled'=>'yes','levelup_points'=>array('customer'=>array('awarded'=>99)),'unknown'=>'007')),$redemption=>serialize(array('points'=>10,'amount'=>1,'min_points'=>'','max_points'=>'','min_cart'=>'','unknown'=>'003')));
foreach(array('points_reward','points_deduct','level_update')as$id){$targets['woocommerce_yowcl_loyalty_'.$id.'_settings']=array('enabled'=>'yes','subject'=>'Retained merchant subject');}
$names=array_keys($targets);foreach(YOWCL_Free_Migrations::features()as$f){foreach(array('','_before','_resolution')as$s){$names[]=YOWCL_Free_Migrations::witness($f).$s;}}
$saved=array();foreach($names as$n){$saved[$n]=$wpdb->get_row($wpdb->prepare("SELECT option_value,autoload FROM {$wpdb->options} WHERE option_name=%s",$n),ARRAY_A);}
$base=tempnam(sys_get_temp_dir(),'loyf-cache-');unlink($base);putenv('LOYF_CACHE_BARRIER='.$base);
$children=array();$user=0;$product=0;$comments=array();$mu=ABSPATH.'wp-content/mu-plugins/loyf-cache-prefill.php';
$spawn=static function($role,$extra=array())use(&$children,$base){
 $cli=getenv('LOYF_WP_CLI_PHAR');loyf_assert($cli&&is_file($cli),'Native WP CLI process available');
 $log=$base.'.'.$role.'.log';$command=array_merge(array(PHP_BINARY,$cli,'--path='.ABSPATH,'eval-file',__FILE__,$role),$extra);
 $process=proc_open($command,array(0=>array('file','/dev/null','r'),1=>array('file',$log,'a'),2=>array('file',$log,'a')),$pipes);loyf_assert(is_resource($process),'Native independent cache reader');
 $children[]=array($process,$log);return count($children)-1;
};
$join=static function($index)use(&$children){list($process,$log)=$children[$index];$until=microtime(true)+25;do{$state=proc_get_status($process);if(!$state['running']){break;}usleep(20000);}while(microtime(true)<$until);if($state['running']){proc_terminate($process);throw new RuntimeException('Cache reader timeout');}$code=$state['exitcode'];proc_close($process);$children[$index][0]=null;loyf_equal(0,$code,'Native child process: '.file_get_contents($log));echo file_get_contents($log);};
$clear=static function($f){foreach(array('','_before','_resolution')as$s){delete_option(YOWCL_Free_Migrations::witness($f).$s);}};
$choose=static function($f){$spec=YOWCL_Free_Migrations::preview($f,'disable');YOWCL_Free_Migrations::resolve($f,'disable',hash('sha256',serialize($spec)),wp_create_nonce('loyf_resolve_'.$f));};
try{
 foreach(YOWCL_Free_Migrations::features()as$f){$clear($f);}foreach($targets as$n=>$value){update_option($n,$value);$wpdb->update($wpdb->options,array('autoload'=>'yes'),array('option_name'=>$n));}wp_cache_flush();
 $user=wp_insert_user(array('user_login'=>'cache_'.substr(wp_generate_uuid4(),0,8),'user_email'=>'cache@example.invalid','user_pass'=>'disposable-only','role'=>'customer'));loyf_assert(is_int($user),'Native cache probe customer');
 $p=new WC_Product_Simple();$p->set_name('Cache handover probe');$p->set_regular_price(10);$product=$p->save();
 update_option(YOWCL_Free_Migrations::witness('review'),'1');
 foreach(array('committed','fresh')as$content){$comments[]=wp_insert_comment(array('comment_post_ID'=>$product,'user_id'=>$user,'comment_type'=>'review','comment_approved'=>1,'comment_content'=>$content));}
 do_action('comment_post',$comments[0],1);loyf_balance($user,99,99,'Positive native registered review control');loyf_assert(is_array(YOWCL_Points_Transaction::find('reward:review:'.$comments[0])),'Positive review ledger control');
 $clear('review');delete_comment_meta($comments[0],'_yowcl_review_reward_awarded');
 $reader=$spawn('reader',array((string)$user,(string)$comments[0],(string)$comments[1]));$wait($base.'.reader');
 // A native cache API pre-image refill after early invalidation models shared cache refill.
 $refill=static function($sql)use($targets){if('COMMIT'===$sql){$all=wp_load_alloptions();foreach($targets as$n=>$v){$all[$n]=maybe_serialize($v);wp_cache_set($n,$v,'options');}wp_cache_set('alloptions',$all,'options');}return $sql;};
 add_filter('query',$refill,PHP_INT_MAX);try{foreach(YOWCL_Free_Migrations::features()as$f){$choose($f);}}finally{remove_filter('query',$refill,PHP_INT_MAX);}
 loyf_equal(0,maybe_unserialize(get_option($merged))['review_points'],'Post-COMMIT invalidation removes native cache pre-image refill');
 foreach(array('email_reward','email_deduct','email_level')as$f){loyf_equal('no',YOWCL_Free_Migrations::canonical($f)['enabled'],'Committed email disable');}
 file_put_contents($base.'.go','continue');$join($reader);
 foreach(array('Points_Reward'=>'email_reward','Points_Deduct'=>'email_deduct','Level_Update'=>'email_level')as$family=>$feature){
  $class='YOWCL_WC_Email_Loyalty_'.$family;$email=new $class();loyf_assert(!$email->is_enabled(),'Disabled native email control');
  YOWCL_Free_Migrations::save($feature,$email->get_option_key(),array('enabled'=>'yes'));loyf_assert($email->is_enabled(),'Warm disabled instance honors newly enabled canonical policy');
  $filter='woocommerce_email_enabled_'.$email->id;add_filter($filter,'__return_false',10);try{loyf_assert(!$email->is_enabled(),'Native email filter can still suppress delivery');}finally{remove_filter($filter,'__return_false',10);}
  YOWCL_Free_Migrations::save($feature,$email->get_option_key(),array('enabled'=>'no'));loyf_assert(!$email->is_enabled(),'Warm enabled instance honors later canonical disable');
 }
 // WordPress loads alloptions before Free waits on the options lock.
 $clear('review');update_option($merged,$targets[$merged]);wp_cache_flush();
 $source='<?php if (getenv("LOYF_CACHE_PREFILL")) { $v=maybe_unserialize(get_option("loyalty_extra_reviews_gamification_rules")); if (99!==$v["review_points"]) { throw new RuntimeException("Bootstrap pre-image absent"); } file_put_contents(getenv("LOYF_CACHE_BARRIER").".prefill","ready"); }';
 loyf_assert(!file_exists($mu),'No unrelated cache MU file');file_put_contents($mu,$source);$bootstrap=null;
 $handover=static function($sql)use(&$bootstrap,$spawn,$wait,$base){if('COMMIT'===$sql&&null===$bootstrap){putenv('LOYF_CACHE_PREFILL=1');try{$bootstrap=$spawn('bootstrap');$wait($base.'.prefill');}finally{putenv('LOYF_CACHE_PREFILL');}}return $sql;};
 add_filter('query',$handover,PHP_INT_MAX);try{$choose('review');}finally{remove_filter('query',$handover,PHP_INT_MAX);unlink($mu);}loyf_assert(null!==$bootstrap,'Bootstrap child launched during transaction');$join($bootstrap);
 echo "Native migration cache handover PASS: separate MySQL connections; eight disabled policies; actual review admission/recovery; warm Woo mail; pre-COMMIT cache refill and pre-lock bootstrap. External Redis/Memcached transport is not certified.\n";
}finally{
 foreach($children as$child){if(is_resource($child[0])){proc_terminate($child[0]);proc_close($child[0]);}}
 if(is_file($mu)){unlink($mu);}foreach($comments as$id){wp_delete_comment($id,true);}if($product){wp_delete_post($product,true);}if($user){$wpdb->delete(YOWCL_Points_Log::table_name(),array('user_id'=>$user));require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($user);}
 foreach($saved as$n=>$row){$wpdb->delete($wpdb->options,array('option_name'=>$n));if(null!==$row){$wpdb->insert($wpdb->options,array('option_name'=>$n,'option_value'=>$row['option_value'],'autoload'=>$row['autoload']));}}
 foreach(glob($base.'.*')as$file){unlink($file);}putenv('LOYF_CACHE_BARRIER');putenv('LOYF_CACHE_PREFILL');wp_cache_flush();wp_set_current_user(1);
}

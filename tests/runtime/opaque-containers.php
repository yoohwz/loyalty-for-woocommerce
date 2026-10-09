<?php
require_once __DIR__.'/assertions.php';
remove_filter('wp_redirect', 'WP_CLI\\Utils\\wp_redirect_handler');
global $wpdb;
$targets=array('signup'=>'loyalty_extra_points_rules','login'=>'loyalty_extra_points_rules','review'=>'loyalty_extra_reviews_gamification_rules','levelup'=>'loyalty_extra_reviews_gamification_rules','redemption'=>'loyalty_points_using_rules','email_reward'=>'woocommerce_yowcl_loyalty_points_reward_settings','email_deduct'=>'woocommerce_yowcl_loyalty_points_deduct_settings','email_level'=>'woocommerce_yowcl_loyalty_level_update_settings');
$names=array_values(array_unique(array_values($targets)));$names=array_merge($names,array('loyalty_notification_email','loyalty_extra_levelup_points_rules','loyalty_levels_roles'));
foreach(YOWCL_Free_Migrations::features()as$f){foreach(array('','_before','_resolution')as$s){$names[]=YOWCL_Free_Migrations::witness($f).$s;}}
$snapshot=static function()use($wpdb,$names){$rows=array();foreach($names as$n){$rows[$n]=$wpdb->get_row($wpdb->prepare("SELECT option_value,autoload FROM {$wpdb->options} WHERE option_name=%s",$n),ARRAY_A);}return $rows;};
if('cold'===($args[0]??'')){
 $expected=json_decode(file_get_contents($args[1]),true);wp_set_current_user(1);loyf_equal($expected,$snapshot(),'Actual cold bootstrap preserves exact opaque/evidence/witness rows');
 ob_start();YOWCL_Free_Migrations::render_review();$html=ob_get_clean();loyf_assert(strpos($html,'unsupported format')!==false&&strpos($html,'opaque-private')===false,'Cold bootstrap diagnostic without raw contents');echo "Opaque actual cold bootstrap PASS.\n";return;
}
require_once WC_ABSPATH.'includes/admin/class-wc-admin-settings.php';
wp_set_current_user(1);$original=$snapshot();$user=0;$product=0;$comments=array();$temp=tempnam(sys_get_temp_dir(),'loyf-opaque-cold-');
$store=static function($n,$raw,$autoload='yes')use($wpdb){$wpdb->delete($wpdb->options,array('option_name'=>$n));if(null!==$raw){$wpdb->insert($wpdb->options,array('option_name'=>$n,'option_value'=>$raw,'autoload'=>$autoload));}wp_cache_delete($n,'options');wp_cache_delete('alloptions','options');wp_cache_delete('notoptions','options');};
$valid=array('loyalty_extra_points_rules'=>serialize(array('signup_points'=>17,'signup_enabled'=>'yes','login_points'=>19,'login_enabled'=>'yes','review_points'=>23,'unknown'=>'003')),'loyalty_extra_reviews_gamification_rules'=>serialize(array('review_points'=>23,'review_enabled'=>'yes','levelup_points'=>array('customer'=>array('awarded'=>29,'unknown'=>'007')),'levelup_enabled'=>'yes','unknown'=>'009')),'loyalty_points_using_rules'=>serialize(array('points'=>10,'amount'=>1,'min_points'=>'','max_points'=>'','min_cart'=>'','unknown'=>'005')));
foreach(array('points_reward','points_deduct','level_update')as$id){$valid['woocommerce_yowcl_loyalty_'.$id.'_settings']=serialize(array('enabled'=>'yes','subject'=>'Merchant subject','unknown'=>'011'));}
$prime=static function()use($store,$valid){foreach($valid as$n=>$raw){$store($n,maybe_serialize($raw));}foreach(YOWCL_Free_Migrations::features()as$f){$store(YOWCL_Free_Migrations::witness($f),'1','no');$store(YOWCL_Free_Migrations::witness($f).'_before','historical-private-before','no');$store(YOWCL_Free_Migrations::witness($f).'_resolution',null);}};
$choose=static function($f){$spec=YOWCL_Free_Migrations::preview($f,'disable');YOWCL_Free_Migrations::resolve($f,'disable',YOWCL_Free_Migrations::resolution_fingerprint($f,$spec),wp_create_nonce('loyf_resolve_'.$f));};
$die=static function(){return static function($message){throw new RuntimeException(is_wp_error($message)?$message->get_error_message():(string)$message);};};add_filter('wp_die_handler',$die);add_filter('wp_die_ajax_handler',$die);
$old_post=$_POST;$old_method=$_SERVER['REQUEST_METHOD']??null;$table=null;
try{
 $prime();foreach(YOWCL_Free_Migrations::features()as$f){$store(YOWCL_Free_Migrations::witness($f),null);}
 $store('loyalty_levels_roles',serialize(array('customer')));
 $user=wp_insert_user(array('user_login'=>'opaque_'.substr(wp_generate_uuid4(),0,8),'user_email'=>'opaque@example.invalid','user_pass'=>'disposable-only','role'=>'customer'));loyf_assert(is_int($user),'Native opaque customer');
 $p=new WC_Product_Simple();$p->set_name('Opaque probe product');$p->set_regular_price(10);$product=$p->save();
 foreach(array('committed','fresh')as$content){$comments[]=wp_insert_comment(array('comment_post_ID'=>$product,'user_id'=>$user,'comment_type'=>'review','comment_approved'=>1,'comment_content'=>$content));}
 $store(YOWCL_Free_Migrations::witness('review'),'1','no');do_action('comment_post',$comments[0],1);loyf_balance($user,23,23,'Committed native review positive control');
 $ledger=$wpdb->get_results('SELECT * FROM '.YOWCL_Points_Log::table_name().' ORDER BY id',ARRAY_A);
 $meta_snapshot=static function()use($wpdb,$user){$patterns=array('loyalty_','yol_','_yol_','_yo_loyalty_','_yowcl_','_loyf_','loyf_');$clauses=array('meta_key IN (%s,%s,%s,%s)');$values=array($user,'user_points','user_earning_points',$wpdb->prefix.'capabilities',$wpdb->prefix.'user_level');foreach($patterns as$prefix){$clauses[]='meta_key LIKE %s';$values[]=$wpdb->esc_like($prefix).'%';}return $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->usermeta} WHERE user_id=%d AND (".implode(' OR ',$clauses).") ORDER BY umeta_id",$values),ARRAY_A);};
 $meta=$meta_snapshot();
 $raws=array('opaque-private-literal',serialize('opaque-private-scalar'),serialize((object)array('unknown'=>'opaque-private-object')),'a:2:{s:7:"unknown";s:21:"opaque-private-broken";');$count=0;
 foreach($raws as$i=>$raw){foreach($targets as$f=>$target){
  $prime();$store('loyalty_levels_roles',serialize(array('customer')));$store(YOWCL_Free_Migrations::witness($f),null);
  $pending=YOWCL_Free_Migrations::preview($f,'disable');$store(YOWCL_Free_Migrations::witness($f).'_resolution',serialize($pending),'no');
  // Alternate absent/success/malformed witness; shared siblings retain success witnesses.
  $witness=0===$i?null:(1===$i?'1':(2===$i?'malformed-witness':null));$store(YOWCL_Free_Migrations::witness($f),$witness,'no');
  get_option($target);$store($target,$raw,0===$i%2?'yes':'no');$before=$snapshot();
  YOWCL_Free_Migrations::run();YOWCL_Free_Migrations::run();loyf_equal($before,$snapshot(),'Boot/retry preserves opaque target and all evidence/witnesses');
  ob_start();YOWCL_Free_Migrations::render_review();$page=ob_get_clean();preg_match('/<section id="loyf-review-'.preg_quote($f,'/').'".*?<\/section>/s',$page,$match);$html=$match[0]??'';loyf_assert(strpos($html,'unsupported format')!==false&&strpos($html,$target)!==false,'Specific native opaque diagnostic');loyf_assert(strpos($html,'<form')===false&&strpos($html,'opaque-private')===false,'No misleading choice or raw payload in native notice');
  foreach(array('canonical','legacy','disable')as$mode){
   try{YOWCL_Free_Migrations::preview($f,$mode);throw new LogicException('Native opaque preview offered');}catch(RuntimeException$e){loyf_equal('migration_opaque_target',$e->getMessage(),'Native opaque preview denied');}
   $_SERVER['REQUEST_METHOD']='POST';$_POST=array('feature'=>$f,'mode'=>$mode,'fingerprint'=>str_repeat('a',64),'_wpnonce'=>wp_create_nonce('loyf_resolve_'.$f));
   $redirect=static function($url){throw new RuntimeException($url);};add_filter('wp_redirect',$redirect,0);
   try{do_action('admin_post_loyf_resolve_migration');throw new LogicException('Native opaque POST accepted');}catch(RuntimeException$e){loyf_assert(strpos($e->getMessage(),'result=unconfirmed')!==false && strpos($e->getMessage(),'#loyf-review-'.$f)!==false,'Authorized native opaque POST returns to held item without success');}finally{remove_filter('wp_redirect',$redirect,0);}
   loyf_equal($before,$snapshot(),'Denied native choice creates no intent, witness or target write');$count++;
  }
  foreach(array('GET','POST')as$method){$_SERVER['REQUEST_METHOD']=$method;$_POST['_wpnonce']='invalid';try{do_action('admin_post_loyf_resolve_migration');throw new LogicException('Invalid opaque POST accepted');}catch(RuntimeException$e){loyf_equal('migration_resolution_denied',$e->getMessage(),'Method/nonce denial');}}
  wp_set_current_user(0);try{do_action('admin_post_loyf_resolve_migration');throw new LogicException('Unauthorized opaque POST accepted');}catch(RuntimeException$e){loyf_equal('migration_resolution_denied',$e->getMessage(),'Capability denial');}wp_set_current_user(1);
  if(in_array($f,array('signup','login'),true)){
   loyf_equal(0,YOWCL_Free_Core::extra('signup'),'Opaque signup value held');loyf_equal(0,YOWCL_Free_Core::extra('login'),'Witnessed sibling login value held');
   do_action('user_register',$user);do_action('wp_login',get_userdata($user)->user_login,get_userdata($user));
  }elseif(in_array($f,array('review','levelup'),true)){
   do_action('comment_post',$comments[1],1);YOWCL_Free_Core::level_bonus($user,'customer');
   loyf_equal(0,YOWCL_Free_Core::extra('review'),'Opaque review held');loyf_equal(array(),YOWCL_Free_Core::level_rules(),'Opaque witnessed level sibling held');
   delete_comment_meta($comments[0],'_yowcl_review_reward_awarded');do_action('comment_post',$comments[0],1);loyf_equal('1',get_comment_meta($comments[0],'_yowcl_review_reward_awarded',true),'Opaque terms retain committed review recovery');
  }elseif('redemption'===$f){
   wp_set_current_user($user);WC()->initialize_session();WC()->initialize_cart();loyf_equal(array(),YOWCL_Free_Cart::rules(),'Opaque redemption has no fresh terms');try{YOWCL_Free_Cart::apply(10);throw new LogicException('Opaque new selection admitted');}catch(DomainException$e){}wp_set_current_user(1);
   $_POST=array('using_point_rules_nonce'=>wp_create_nonce('save_using_point_rules'),'loyalty_using_points'=>'17','loyalty_using_amount'=>'2');(new YOSWC_Loyalty_Settings())->save_using_point_rules();
   ob_start();do_action('woocommerce_admin_field_set_using_point_rules',array('name'=>'Redemption'));$fields=ob_get_clean();loyf_assert(strpos($fields,'disabled')!==false,'Opaque redemption controls read-only');
  }else{
   $families=array('email_reward'=>'Points_Reward','email_deduct'=>'Points_Deduct','email_level'=>'Level_Update');$class='YOWCL_WC_Email_Loyalty_'.$families[$f];$mail=new $class();$messages=count($GLOBALS['loyf_mail']??array());loyf_assert(!$mail->is_enabled(),'Opaque native email disabled');
   if('email_level'===$f){$mail->trigger($user,'customer',23);}else{$mail->trigger($user,7,23);}loyf_equal($messages,count($GLOBALS['loyf_mail']??array()),'No opaque native mail');
   $GLOBALS['current_section']=strtolower($class);$GLOBALS['hide_save_button']=false;ob_start();$mail->admin_options();$fields=ob_get_clean();loyf_assert(strpos($fields,'unsupported format')!==false&&strpos($fields,'opaque-private')===false&&!preg_match('/<(?:input|textarea|select)[^>]*name=["\']woocommerce_'.preg_quote($mail->id,'/').'_/',$fields),'Native opaque email settings show only diagnostic');loyf_assert($GLOBALS['hide_save_button'],'Opaque email save button hidden');
   $_POST=array('woocommerce_'.$mail->id.'_enabled'=>'1','woocommerce_'.$mail->id.'_subject'=>'forged');loyf_assert(true!==$mail->process_admin_options(),'Opaque witnessed native email save cannot report success');loyf_assert(false===update_option($target,array('enabled'=>'yes','subject'=>'forged'),'yes'),'WordPress denies opaque email option update');
   WC_Admin_Settings::add_message('opaque-fake-success');ob_start();WC_Admin_Settings::show_messages();$notice=ob_get_clean();if('1'===$witness){loyf_assert(strpos($notice,'unsupported format')!==false&&strpos($notice,'opaque-fake-success')===false,'Native error suppresses false Woo saved status');}
  }
  loyf_equal($before,$snapshot(),'Native producer/admin save preserves every opaque and evidence row');
  // Save an independent readable container; forged opaque fields are ignored.
  if(in_array($f,array('signup','login','review','levelup'),true)){
   $_POST=array('extra_points_settings_nonce'=>wp_create_nonce('save_extra_points_settings_action'),'loyalty_extra_signup_points'=>'41','loyalty_extra_login_points'=>'43','loyalty_extra_review_points'=>'47','loyalty_extra_levelup_customer'=>'53');(new YOSWC_Loyalty_Settings_Extra_Points())->save_extra_points_settings();
   loyf_equal($before[$target],$snapshot()[$target],'Witnessed opaque siblings remain exact while unrelated settings save');
   if('loyalty_extra_points_rules'===$target){loyf_equal(47,YOWCL_Free_Core::extra('review'),'Independent review saved');}else{loyf_equal(41,YOWCL_Free_Core::extra('signup'),'Independent signup saved');}
   ob_start();(new YOSWC_Loyalty_Settings_Extra_Points())->display_extra_points_settings();$fields=ob_get_clean();$field=in_array($f,array('signup','login'),true)?'loyalty_extra_signup_points':'loyalty_extra_review_points';preg_match('~<input[^>]*name="'.$field.'"[^>]*>~',$fields,$m);loyf_assert(isset($m[0])&&strpos($m[0],'disabled=')!==false,'Opaque Extra points inputs read-only');
  }
  loyf_equal($ledger,$wpdb->get_results('SELECT * FROM '.YOWCL_Points_Log::table_name().' ORDER BY id',ARRAY_A),'No opaque new reward/mail/debit ledger event');loyf_equal($meta,$meta_snapshot(),'Opaque producer preserves balances/roles/user metadata');
  if('signup'===$f||0===$i&&!in_array($f,array('login','levelup'),true)){
   file_put_contents($temp,json_encode($snapshot()));$cli=getenv('LOYF_WP_CLI_PHAR');loyf_assert($cli&&is_file($cli),'Cold native WPCLI supplied');$log=$temp.'.log';$process=proc_open(array(PHP_BINARY,$cli,'--path='.ABSPATH,'eval-file',__FILE__,'cold',$temp),array(0=>array('file','/dev/null','r'),1=>array('file',$log,'w'),2=>array('file',$log,'a')),$pipes);loyf_assert(is_resource($process),'Independent native cold bootstrap');$until=microtime(true)+60;do{$state=proc_get_status($process);if(!$state['running']){break;}usleep(20000);}while(microtime(true)<$until);if($state['running']){proc_terminate($process);proc_close($process);throw new RuntimeException('Opaque cold bootstrap timeout');}$code=$state['exitcode'];proc_close($process);loyf_equal(0,$code,'Native cold bootstrap: '.file_get_contents($log));loyf_equal(json_decode(file_get_contents($temp),true),$snapshot(),'Independent cold bootstrap exact row preservation');
  }
 }}
 // Invalid owned terms inside an array cannot become a synthetic one-point award or fatal.
 $prime();$store($targets['review'],serialize(array('review_enabled'=>'yes','review_points'=>array('bad'),'levelup_enabled'=>'yes','levelup_points'=>array('customer'=>(object)array('awarded'=>29)),'unknown'=>'009')));
 loyf_equal(0,YOWCL_Free_Core::extra('review'),'Malformed review amount has no fresh value');loyf_equal(array(),YOWCL_Free_Core::level_rules(),'Malformed role rule has no fresh value');do_action('comment_post',$comments[1],1);YOWCL_Free_Core::level_bonus($user,'customer');
 loyf_equal($ledger,$wpdb->get_results('SELECT * FROM '.YOWCL_Points_Log::table_name().' ORDER BY id',ARRAY_A),'Malformed owned terms cannot create new ledger value');
 // Supported array malformed-pair repair keeps sibling/unknown bytes and wrappers.
 $prime();$store(YOWCL_Free_Migrations::witness('signup'),null);$raw=maybe_serialize(serialize(array('signup_enabled'=>'invalid','signup_points'=>array('bad'),'login_enabled'=>'yes','login_points'=>'31','unknown'=>'007')));$store($targets['signup'],$raw);$store(YOWCL_Free_Migrations::witness('signup').'_resolution',null);$choose('signup');$current=maybe_unserialize(maybe_unserialize(YOWCL_Free_Migrations::read($targets['signup'])));loyf_equal(array('signup_enabled'=>'no','signup_points'=>0,'login_enabled'=>'yes','login_points'=>'31','unknown'=>'007'),$current,'Native narrow malformed-pair disable');loyf_assert(is_string(maybe_unserialize(YOWCL_Free_Migrations::read($targets['signup']))),'Native legacy wrapper retained');
 $prime();$store(YOWCL_Free_Migrations::witness('email_reward'),null);$store(YOWCL_Free_Migrations::witness('email_reward').'_resolution',null);$store($targets['email_reward'],null);$choose('email_reward');loyf_equal('no',YOWCL_Free_Migrations::canonical('email_reward')['enabled'],'Native absent target remains distinct supported path');
 // Duplicate rows exist only in a temporary probe table, never the site's options schema.
 $real=$wpdb->options;$table=$wpdb->prefix.'loyf_opaque_'.substr(md5(wp_generate_uuid4()),0,8);$wpdb->query("CREATE TEMPORARY TABLE {$table} LIKE {$real}");$wpdb->query("ALTER TABLE {$table} DROP INDEX option_name");
 $wpdb->insert($table,array('option_name'=>YOWCL_Free_Migrations::witness('signup'),'option_value'=>'1','autoload'=>'no'));$wpdb->insert($table,array('option_name'=>YOWCL_Free_Migrations::witness('signup'),'option_value'=>'1','autoload'=>'no'));$wpdb->options=$table;
 try{loyf_assert(!YOWCL_Free_Migrations::ready('signup'),'Native duplicate witness unavailable');try{YOWCL_Free_Migrations::preview('signup','disable');throw new LogicException('Duplicate witness preview admitted');}catch(RuntimeException$e){loyf_equal('migration_storage_unavailable',$e->getMessage(),'Native duplicate witness read fails closed');}}finally{$wpdb->options=$real;$wpdb->query("DROP TEMPORARY TABLE {$table}");$table=null;}
 echo "Native opaque containers PASS: {$count} denied choices; exact rows/autoload/evidence/witnesses; admin GET/POST, warm/cold boot, retries, shared siblings, native mail/redemption/review recovery, independent settings and supported controls.\n";
}finally{
 $_POST=$old_post;if(null===$old_method){unset($_SERVER['REQUEST_METHOD']);}else{$_SERVER['REQUEST_METHOD']=$old_method;}unset($GLOBALS['current_section'],$GLOBALS['hide_save_button']);
 foreach($comments as$id){wp_delete_comment($id,true);}if($product){wp_delete_post($product,true);}if($user){$wpdb->delete(YOWCL_Points_Log::table_name(),array('user_id'=>$user));require_once ABSPATH.'wp-admin/includes/user.php';wp_delete_user($user);}
 foreach($original as$n=>$row){$wpdb->delete($wpdb->options,array('option_name'=>$n));if(null!==$row){$wpdb->insert($wpdb->options,array('option_name'=>$n,'option_value'=>$row['option_value'],'autoload'=>$row['autoload']));}}
 remove_filter('wp_die_handler',$die);remove_filter('wp_die_ajax_handler',$die);wp_cache_flush();wp_set_current_user(1);unlink($temp);if(is_file($temp.'.log')){unlink($temp.'.log');}
}

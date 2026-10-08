<?php
require_once __DIR__.'/assertions.php';
global $wpdb;
wp_set_current_user(1);
$account='loyalty_extra_points_rules';$merged='loyalty_extra_reviews_gamification_rules';
$features=array('signup','login','review','levelup');
$names=array($account,$merged,'loyalty_levels_roles',YOWCL_Free_First_Purchase::RULES,YOWCL_Free_First_Purchase::WITNESS);
foreach($features as $f){foreach(array('','_before','_resolution')as$s){$names[]=YOWCL_Free_Migrations::witness($f).$s;}}
$snapshot=static function()use($wpdb,$names){$rows=array();foreach($names as$n){$rows[$n]=$wpdb->get_row($wpdb->prepare("SELECT option_value,autoload FROM {$wpdb->options} WHERE option_name=%s",$n),ARRAY_A);}return $rows;};
$original=$snapshot();$ledger=$wpdb->get_results('SELECT * FROM '.YOWCL_Points_Log::table_name().' ORDER BY id',ARRAY_A);
$die=static function(){return static function($message){throw new RuntimeException((string)$message);};};add_filter('wp_die_handler',$die);add_filter('wp_die_ajax_handler',$die);
$choose=static function($f){$spec=YOWCL_Free_Migrations::preview($f,'canonical');YOWCL_Free_Migrations::resolve($f,'canonical',hash('sha256',serialize($spec)),wp_create_nonce('loyf_resolve_'.$f));};
$save=static function(){(new YOSWC_Loyalty_Settings_Extra_Points())->save_extra_points_settings();};
$pair=static function($f)use($account,$merged){$terms=maybe_unserialize(get_option(in_array($f,array('signup','login'),true)?$account:$merged));return array($terms[$f.'_enabled'],$terms[$f.'_points']);};
try{
 update_option('loyalty_levels_roles',array('customer'));
 foreach($features as$held){
  foreach($features as$f){foreach(array('','_before','_resolution')as$s){delete_option(YOWCL_Free_Migrations::witness($f).$s);}}
  update_option($account,serialize(array('signup_points'=>'17','signup_enabled'=>'yes','login_points'=>'19','login_enabled'=>'yes','review_points'=>'73','unknown'=>array('keep'=>'009'))));
  update_option($merged,serialize(array('review_points'=>'23','review_enabled'=>'yes','levelup_points'=>array('customer'=>array('awarded'=>'29','dormant'=>'007')),'levelup_enabled'=>'yes','unknown'=>array('keep'=>'003'))));
  foreach($features as$f){if($f!==$held){$choose($f);}}
  YOWCL_Free_First_Purchase::save(false,'0');$before=$snapshot();$held_pair=$pair($held);
  ob_start();(new YOSWC_Loyalty_Settings_Extra_Points())->display_extra_points_settings();$html=ob_get_clean();
  loyf_assert(strpos($html,'Rewards on hold are read-only.')!==false,'Native hold explanation');
  foreach($features as$f){$field='levelup'===$f?'loyalty_extra_levelup_customer':'loyalty_extra_'.$f.'_points';loyf_assert(1===preg_match('~<input[^>]*name="'.preg_quote($field,'~').'"[^>]*>~',$html,$match),'Native reward control '.$f);loyf_equal($f===$held,strpos($match[0],'disabled=')!==false,'Only held control disabled '.$f);}
  $_POST=array('extra_points_settings_nonce'=>wp_create_nonce('save_extra_points_settings_action'),'loyalty_extra_signup_points'=>'31','loyalty_extra_login_points'=>'37','loyalty_extra_review_points'=>'41','loyalty_extra_levelup_customer'=>'43','loyalty_extra_first_purchase_enabled'=>'yes','loyalty_extra_first_purchase_points'=>'25');
  $field='levelup'===$held?'loyalty_extra_levelup_customer':'loyalty_extra_'.$held.'_points';$_POST[$field]=array('forged');
  // Validate all editable terms before any write, including the First Purchase epoch.
  $safe='review'===$held?'login':'review';$good=$_POST['loyalty_extra_'.$safe.'_points'];$_POST['loyalty_extra_'.$safe.'_points']=array('invalid');
  try{$save();throw new LogicException('Invalid editable points admitted');}catch(RuntimeException$e){loyf_assert(strpos($e->getMessage(),'A valid whole points amount is required.')!==false,'Native editable validation');}
  loyf_equal($before,$snapshot(),'Invalid editable terms leave every rule/witness/First Purchase epoch intact');$_POST['loyalty_extra_'.$safe.'_points']=$good;
  $save();loyf_equal($held_pair,$pair($held),'Held owned pair remains exact '.$held);
  foreach(array('','_before','_resolution')as$s){$n=YOWCL_Free_Migrations::witness($held).$s;loyf_equal($before[$n],$snapshot()[$n],'Held witness/evidence untouched');}
  foreach(array('signup'=>31,'login'=>37,'review'=>41)as$f=>$points){if($f!==$held){loyf_equal($points,YOWCL_Free_Core::extra($f),'Native independently ready save '.$f);}}
  if('levelup'!==$held){loyf_equal('43',YOWCL_Free_Core::level_rules()['customer']['awarded'],'Native independent level save');}
  $a=get_option($account);$m=get_option($merged);loyf_assert(is_string($a)&&is_string($m),'Both serialized wrappers retained');$a=maybe_unserialize($a);$m=maybe_unserialize($m);
  loyf_equal(array('keep'=>'009'),$a['unknown'],'Dormant account untouched');loyf_equal('73',$a['review_points'],'Preserved legacy review source');loyf_equal(array('keep'=>'003'),$m['unknown'],'Dormant merged untouched');loyf_equal('007',$m['levelup_points']['customer']['dormant'],'Dormant level field untouched');
  $first=YOWCL_Free_First_Purchase::configuration();loyf_assert($first['effective']&&$first['ready']&&25===$first['points'],'Native First Purchase save succeeds with unrelated held reward');
  // An omitted ready control must not silently clear its existing terms.
  $after=$snapshot();$_POST=array('extra_points_settings_nonce'=>wp_create_nonce('save_extra_points_settings_action'));$save();loyf_equal($after,$snapshot(),'Absent controls grant no mutation');
 }
 // A stale pre-lock option cache cannot erase another role or dormant fields.
 $choose('levelup');$cached=get_option($merged);$live=maybe_unserialize($cached);$live['levelup_points']['customer']['dormant']='fresh';$live['levelup_points']['subscriber']=array('awarded'=>'11','dormant'=>'preserve');
 $wpdb->query($wpdb->prepare("UPDATE {$wpdb->options} SET option_value=%s WHERE option_name=%s",maybe_serialize(serialize($live)),$merged));
 loyf_equal($cached,get_option($merged),'Native option cache remains stale before save');
 $_POST=array('extra_points_settings_nonce'=>wp_create_nonce('save_extra_points_settings_action'),'loyalty_extra_levelup_customer'=>'67');$save();$live['levelup_points']['customer']['awarded']='67';
 loyf_equal(maybe_serialize(serialize($live)),YOWCL_Free_Migrations::read($merged),'Live owned merge preserves concurrent dormant and other-role fields');
 // An unsubmitted feature's source failure cannot veto another ready save.
 $unavailable=static function($sql)use($merged){return strpos($sql,'SELECT option_value')!==false&&strpos($sql,"'{$merged}'")!==false?'SELECT * FROM loyf13_unsubmitted_level_source':$sql;};
 add_filter('query',$unavailable,PHP_INT_MAX);try{$_POST=array('extra_points_settings_nonce'=>wp_create_nonce('save_extra_points_settings_action'),'loyalty_extra_signup_points'=>'47');$save();}finally{remove_filter('query',$unavailable,PHP_INT_MAX);}
 loyf_equal(47,YOWCL_Free_Core::extra('signup'),'Unsubmitted level target is not read or saved');
 // Native storage failure after one completed setting reports that partial result.
 YOWCL_Free_First_Purchase::save(false,'0');$first_before=array(YOWCL_Free_Migrations::read(YOWCL_Free_First_Purchase::RULES),YOWCL_Free_Migrations::read(YOWCL_Free_First_Purchase::WITNESS));
 $_POST=array('extra_points_settings_nonce'=>wp_create_nonce('save_extra_points_settings_action'),'loyalty_extra_signup_points'=>'51','loyalty_extra_login_points'=>'53','loyalty_extra_review_points'=>'59','loyalty_extra_first_purchase_enabled'=>'yes','loyalty_extra_first_purchase_points'=>'61');$writes=0;
 $fail=static function($sql)use($wpdb,$account,&$writes){if(strpos($sql,"UPDATE {$wpdb->options} SET option_value")!==false&&strpos($sql,"'{$account}'")!==false&&++$writes===2){return 'SELECT * FROM loyf13_extra_points_missing_table';}return $sql;};
 add_filter('query',$fail,PHP_INT_MAX);try{$save();throw new LogicException('Native write failure certified');}catch(RuntimeException$e){loyf_assert(strpos($e->getMessage(),'Settings saved before this error: Sign-up.')!==false,'Completed feature named in partial-save error');}finally{remove_filter('query',$fail,PHP_INT_MAX);}
 loyf_equal(51,YOWCL_Free_Core::extra('signup'),'Completed native setting is durable');loyf_equal(37,YOWCL_Free_Core::extra('login'),'Failed native setting remains original');loyf_equal($first_before,array(YOWCL_Free_Migrations::read(YOWCL_Free_First_Purchase::RULES),YOWCL_Free_Migrations::read(YOWCL_Free_First_Purchase::WITNESS)),'First Purchase not changed before later storage failure');
 $before=$snapshot();$_POST=array('extra_points_settings_nonce'=>array('invalid'),'loyalty_extra_first_purchase_points'=>'99');try{$save();throw new LogicException('Typed nonce denial missing');}catch(RuntimeException$e){}loyf_equal($before,$snapshot(),'Malformed nonce changes no option');
 wp_set_current_user(0);$_POST=array('extra_points_settings_nonce'=>wp_create_nonce('save_extra_points_settings_action'),'loyalty_extra_first_purchase_points'=>'99');try{$save();throw new LogicException('Capability denial missing');}catch(RuntimeException$e){}loyf_equal($before,$snapshot(),'Denied actor changes no option');
 loyf_equal($ledger,$wpdb->get_results('SELECT * FROM '.YOWCL_Points_Log::table_name().' ORDER BY id',ARRAY_A),'Settings create no accounting events');
 echo "Native Extra points mixed-witness save PASS: each held reward isolated; ready/absent/forged controls; exact dormant/wrapped terms; First Purchase rule/epoch; validation and partial-storage result.\n";
}finally{
 foreach($original as$n=>$row){$wpdb->delete($wpdb->options,array('option_name'=>$n));if(null!==$row){$wpdb->insert($wpdb->options,array('option_name'=>$n,'option_value'=>$row['option_value'],'autoload'=>$row['autoload']));}}
 $_POST=array();remove_filter('wp_die_handler',$die);remove_filter('wp_die_ajax_handler',$die);wp_cache_flush();wp_set_current_user(1);
}

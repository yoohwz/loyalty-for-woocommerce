<?php
/** Production protocol with WP/storage stand-ins; native evidence is separate. */
define('ABSPATH','/loyf-stand-in/');
class YOWCL_Free_Core { public static function owns(){return true;} }
function is_serialized($v){return is_string($v)&&(preg_match('/^(?:a|O|s|i|d|b|C):/',$v)||'N;'===$v);}
function maybe_unserialize($v){if(!is_serialized($v)){return $v;}$r=@unserialize($v);return false===$r&&'b:0;'!==$v?$v:$r;}
function maybe_serialize($v){return is_array($v)||is_object($v)||is_serialized($v)?serialize($v):(string)$v;}
function sanitize_key($v){return preg_replace('/[^a-z0-9_-]/','',strtolower($v));}
function current_user_can($v){return true;}
function get_woocommerce_currency(){return 'USD';}
function wc_get_price_decimals(){return 2;}
function wc_format_decimal($v,$dp,$trim=false){return rtrim(rtrim(number_format((float)$v,$dp,'.',''),'0'),'.');}
function __($v,$d=null){return $v;}
function esc_html($v){return htmlspecialchars($v);}
function esc_html__($v,$d=null){return esc_html($v);}
function esc_attr($v){return htmlspecialchars($v);}
function esc_url($v){return $v;}
function add_action(...$args){}
function wp_roles(){return (object)array('roles'=>array());}
function admin_url($v){return '/'.$v;}
function wp_json_encode($v){return json_encode($v);}
function wp_generate_uuid4(){return '00000000-0000-4000-8000-000000000027';}
function wp_create_nonce($v){return 'stand-in';}
function wp_nonce_field($v){echo '<input name="nonce" value="stand-in">';}
class LOYF_Opaque_DB {
 public $options='stand_in_options',$last_error='',$rows=array();
 public function prepare($sql,...$args){return $args[0];}
 public function get_col($name){return $this->rows[$name]??array();}
}
function opaque_assert($condition,$message){if(!$condition){throw new RuntimeException($message);}}
$wpdb=new LOYF_Opaque_DB();require __DIR__.'/../inc/cores/helper/free-migrations.php';
$targets=array('signup'=>'loyalty_extra_points_rules','login'=>'loyalty_extra_points_rules','review'=>'loyalty_extra_reviews_gamification_rules','levelup'=>'loyalty_extra_reviews_gamification_rules','redemption'=>'loyalty_points_using_rules','email_reward'=>'woocommerce_yowcl_loyalty_points_reward_settings','email_deduct'=>'woocommerce_yowcl_loyalty_points_deduct_settings','email_level'=>'woocommerce_yowcl_loyalty_level_update_settings');
$raws=array('opaque-private-literal',serialize('opaque-private-scalar'),serialize((object)array('unknown'=>'opaque-private-object')),'a:2:{s:7:"unknown";s:21:"opaque-private-broken";');
$count=0;
foreach($targets as$f=>$target){
 $wpdb->rows=array($target=>array(serialize(array())));$pending=serialize(YOWCL_Free_Migrations::preview($f,'disable'));
 foreach($raws as$raw){foreach(array(array(),array('1'),array('invalid-witness'),array('1','1'))as$witness){
  $wpdb->rows=array($target=>array($raw),YOWCL_Free_Migrations::witness($f)=>$witness,YOWCL_Free_Migrations::witness($f).'_before'=>array('opaque-private-before'),YOWCL_Free_Migrations::witness($f).'_resolution'=>array($pending));$before=$wpdb->rows;
  opaque_assert(!YOWCL_Free_Migrations::readable($f),'Opaque container is not readable');opaque_assert(array()===YOWCL_Free_Migrations::canonical($f),'Opaque canonical policy admits no new terms');
  foreach(array('canonical','legacy','disable')as$mode){try{YOWCL_Free_Migrations::preview($f,$mode);throw new LogicException('Opaque choice offered');}catch(RuntimeException$e){opaque_assert('migration_opaque_target'===$e->getMessage(),'Specific opaque diagnostic');}$count++;}
  opaque_assert($before===$wpdb->rows,'Raw target/source/evidence/witness rows preserved');
  $errors=new ReflectionProperty('YOWCL_Free_Migrations','errors');if(PHP_VERSION_ID<80100){$errors->setAccessible(true);}$errors->setValue(null,array($f=>'migration_opaque_target'));
  $html=YOWCL_Free_Migrations::held_link($f);opaque_assert(false!==strpos($html,'Restore or repair the original complete container'),'Specific opaque diagnosis');opaque_assert(false!==strpos($html,$target),'Container identified');opaque_assert(false===strpos($html,'<form')&&false===strpos($html,'opaque-private'),'No controls or raw contents');
 }}
 $wpdb->rows=array();$spec=YOWCL_Free_Migrations::preview($f,'disable');opaque_assert(null===$spec['before']&&YOWCL_Free_Migrations::readable($f),'Absent target remains supported');
}
$raw=serialize(serialize(array('signup_points'=>array('invalid'),'signup_enabled'=>'invalid','login_points'=>'31','login_enabled'=>'yes','unknown'=>(object)array('keep'=>'009'))));$wpdb->rows=array('loyalty_extra_points_rules'=>array($raw));$spec=YOWCL_Free_Migrations::preview('signup','disable');opaque_assert(array('signup_points'=>0,'signup_enabled'=>'no')===$spec['patch']&&$raw===$spec['before'],'Malformed owned pair in wrapped array supports narrow disable with exact before');
foreach(array(array('invalid'),array('1','1'))as$witness){$wpdb->rows=array('loyalty_extra_points_rules'=>array(serialize(array())),YOWCL_Free_Migrations::witness('signup')=>$witness);opaque_assert(!YOWCL_Free_Migrations::ready('signup'),'Malformed/duplicate witness never ready');try{YOWCL_Free_Migrations::preview('signup','disable');throw new LogicException('Unsafe witness choice');}catch(RuntimeException$e){opaque_assert(in_array($e->getMessage(),array('migration_malformed_witness','migration_storage_unavailable'),true),'Unsafe witness refused');}}
$wpdb->rows=array('loyalty_extra_points_rules'=>array(serialize(array())),YOWCL_Free_Migrations::witness('signup').'_resolution'=>array('invalid-evidence'));$before=$wpdb->rows;try{YOWCL_Free_Migrations::preview('signup','disable');throw new LogicException('Malformed pending evidence admitted');}catch(RuntimeException$e){opaque_assert('migration_malformed_option'===$e->getMessage()&&$before===$wpdb->rows,'Malformed pending evidence remains untouched');}
echo "Opaque migration protocol PASS: {$count} denied choices, pending intent and witness variants, private diagnostics, malformed-pair/wrapper and absent-target controls. Stand-ins; not native certification.\n";

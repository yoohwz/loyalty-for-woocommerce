<?php
if(!defined('LOY_RUNTIME_DISPOSABLE')||true!==LOY_RUNTIME_DISPOSABLE||false!==strpos(realpath(ABSPATH),'/Local Sites/')){throw new RuntimeException('Disposable native lifecycle required');}
global $wpdb;
$patterns=array();foreach(array('loyalty_','loyf_','yoswc_','yowcl_','yol_','wc_loyalty_','woocommerce_yowcl_') as $prefix){$patterns[]=$wpdb->prepare('option_name LIKE %s',$wpdb->esc_like($prefix).'%');}$patterns[]=$wpdb->prepare('option_name=%s',$wpdb->prefix.'user_roles');$where=implode(' OR ',$patterns);
$snapshot=array('rows'=>$wpdb->get_results("SELECT * FROM {$wpdb->prefix}yo_loyalty_points_log ORDER BY id",ARRAY_A),'users'=>$wpdb->get_results("SELECT * FROM {$wpdb->usermeta} ORDER BY umeta_id",ARRAY_A),'orders'=>$wpdb->get_results("SELECT * FROM {$wpdb->postmeta} ORDER BY meta_id",ARRAY_A),'comments'=>$wpdb->get_results("SELECT * FROM {$wpdb->commentmeta} ORDER BY meta_id",ARRAY_A),'options'=>$wpdb->get_results("SELECT option_name,option_value,autoload FROM {$wpdb->options} WHERE $where ORDER BY option_name",ARRAY_A));
if('hpos'===getenv('LOYF_STORAGE')){$snapshot['hpos']=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}wc_orders_meta ORDER BY id",ARRAY_A);}
if($wpdb->last_error){throw new RuntimeException('Lifecycle storage unavailable');}
$file=getenv('LOYF13_LIFECYCLE_SNAPSHOT');if(!is_string($file)||''===$file){throw new RuntimeException('Lifecycle snapshot path required');}
if('before'===getenv('LOYF13_LIFECYCLE_PHASE')){if(false===file_put_contents($file,serialize($snapshot))){throw new RuntimeException('Lifecycle evidence write failed');}}
else{if(serialize($snapshot)!==file_get_contents($file)){throw new RuntimeException('Native Free deactivation/uninstall changed Loyalty persistence');}echo "Native Free deactivation/uninstall persistence PASS: exact value/log/meta/roles/settings/dormant bytes.\n";}

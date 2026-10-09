<?php
global $wpdb;
$before=json_decode(file_get_contents(getenv('LOYF13_SNAPSHOT')),true);
$changes=array();
foreach($before as $row){$after=$wpdb->get_row($wpdb->prepare('SELECT option_name,option_value,autoload FROM '.$wpdb->options.' WHERE option_name=%s',$row['option_name']),ARRAY_A);if($row!==$after){$changes[]=array('name'=>$row['option_name'],'before'=>maybe_unserialize($row['option_value']),'after'=>maybe_unserialize($after['option_value']));}}
if($changes){throw new RuntimeException('Installed Premium reactivation changed dormant canonical terms');}
$persistence=unserialize(file_get_contents(getenv('LOYF13_SNAPSHOT').'.persistence'));$now=array('meta'=>$wpdb->get_results("SELECT * FROM {$wpdb->usermeta} ORDER BY umeta_id",ARRAY_A),'rows'=>$wpdb->get_results("SELECT * FROM {$wpdb->prefix}yo_loyalty_points_log ORDER BY id",ARRAY_A),'roles'=>$wpdb->get_row($wpdb->prepare("SELECT option_value,autoload FROM {$wpdb->options} WHERE option_name=%s",$wpdb->prefix.'user_roles'),ARRAY_A));if(serialize($persistence)!==serialize($now)){throw new RuntimeException('Premium reactivation changed preserved metadata/history/roles');}
echo wp_json_encode(array('result'=>$changes?'FAIL':'PASS','changes'=>$changes),JSON_PRETTY_PRINT)."\n";

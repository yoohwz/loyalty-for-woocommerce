<?php
global $wpdb;
$before=json_decode(file_get_contents(getenv('LOYF13_SNAPSHOT')),true);
$changes=array();
foreach($before as $row){$after=$wpdb->get_row($wpdb->prepare('SELECT option_name,option_value,autoload FROM '.$wpdb->options.' WHERE option_name=%s',$row['option_name']),ARRAY_A);if($row!==$after){$changes[]=array('name'=>$row['option_name'],'before'=>maybe_unserialize($row['option_value']),'after'=>maybe_unserialize($after['option_value']));}}
if($changes){throw new RuntimeException('Installed Premium reactivation changed dormant canonical terms');}
echo wp_json_encode(array('result'=>$changes?'FAIL':'PASS','changes'=>$changes),JSON_PRETTY_PRINT)."\n";

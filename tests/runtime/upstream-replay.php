<?php
// Always prove same-key Free replay. Optional local proof evaluates the exact read-only
// upstream primitive under a test-only class name, with the retained Free dependencies.
foreach($identity_rows as $row) {
    $key=$row['event_key']; $event=array('action'=>$row['action'],'order_id'=>(int)$row['order_id'],'source_event_key'=>$row['source_event_key']);
    if(in_array($row['action'],YOWCL_Points_Transaction::REWARD_ACTIONS,true)) { $result=YOWCL_Points_Transaction::reward((int)$row['user_id'],(int)$row['available_delta'],$key,$event); }
    elseif('referral_reward_reversal'===$row['action']) {$source=YOWCL_Points_Transaction::find($row['source_event_key']);$result=YOWCL_Points_Transaction::reverse_referral_reward((int)$row['user_id'],(int)$row['order_id'],$row['source_event_key'],$source['action'],'Replay');}
    elseif('points_deducted'===$row['action']) {$result=YOWCL_Points_Transaction::reverse_order_reward((int)$row['user_id'],(int)$row['order_id'],'Replay');}
    elseif(in_array($row['action'],array('admin_reward','admin_deduct','points_import'),true)) {
        $request=YOWCL_Points_Allocation::decode($row['allocation_receipt'])['request'];
        $result=YOWCL_Points_Transaction::mutate((int)$row['user_id'],$request['available'],$request['earning'],$key,$event,$request['mode']);
    } else {$result=YOWCL_Points_Transaction::apply((int)$row['user_id'],(int)$row['available_delta'],(int)$row['earning_delta'],$key,$event);}
    loyf_equal('already_applied',$result['status'],'Free committed event replay'); loyf_equal((int)$row['id'],$result['log_id'],'Same event row ID');
}
$path=getenv('LOYF_UPSTREAM_TRANSACTION_SOURCE');
if(!$path) { echo "Upstream primitive native replay UNAVAILABLE (no private read-only source supplied; Free replay executed)\n"; return; }
$manifest=json_decode(file_get_contents(dirname(__DIR__,2).'/config/free-import-manifest.json'),true);
$expected=null; foreach($manifest['imports'] as $entry){if('inc/cores/helper/points-transaction.php'===$entry['source']){$expected=$entry['sha256'];}}
$source=file_get_contents($path); loyf_assert(is_string($expected)&&hash('sha256',$source)===$expected,'Exact pinned upstream primitive hash');
$tokens=token_get_all($source); $output=''; $class=false; $changed=0;
foreach($tokens as $token) {
    if(is_array($token)) {
        if(T_CLASS===$token[0]){$class=true;}
        if($class&&T_STRING===$token[0]&&'YOWCL_Points_Transaction'===$token[1]){$token[1]='LOYF7_Upstream_Transaction';$class=false;++$changed;}
        $output.=$token[1];
    }else{$output.=$token;}
}
loyf_equal(1,$changed,'Only declaration renamed'); eval('?>'.$output);
$before=$wpdb->get_results('SELECT * FROM '.YOWCL_Points_Log::table_name().' ORDER BY id',ARRAY_A);
$balances=$wpdb->get_results("SELECT * FROM {$wpdb->usermeta} WHERE meta_key IN ('user_points','user_earning_points') ORDER BY umeta_id",ARRAY_A);
foreach($identity_rows as $row) {
    $event=array('action'=>$row['action'],'order_id'=>(int)$row['order_id'],'source_event_key'=>$row['source_event_key']); $key=$row['event_key'];
    if(in_array($row['action'],LOYF7_Upstream_Transaction::REWARD_ACTIONS,true)) {$result=LOYF7_Upstream_Transaction::reward((int)$row['user_id'],(int)$row['available_delta'],$key,$event);}
    elseif('referral_reward_reversal'===$row['action']) {$source=YOWCL_Points_Transaction::find($row['source_event_key']);$result=LOYF7_Upstream_Transaction::reverse_referral_reward((int)$row['user_id'],(int)$row['order_id'],$row['source_event_key'],$source['action'],'Replay');}
    elseif('points_deducted'===$row['action']){$result=LOYF7_Upstream_Transaction::reverse_order_reward((int)$row['user_id'],(int)$row['order_id'],'Replay');}
    elseif(in_array($row['action'],array('admin_reward','admin_deduct','points_import'),true)) {
        $request=YOWCL_Points_Allocation::decode($row['allocation_receipt'])['request'];
        $result=LOYF7_Upstream_Transaction::mutate((int)$row['user_id'],$request['available'],$request['earning'],$key,$event,$request['mode']);
    }else{$result=LOYF7_Upstream_Transaction::apply((int)$row['user_id'],(int)$row['available_delta'],(int)$row['earning_delta'],$key,$event);}
    loyf_equal('already_applied',$result['status'],'Exact upstream recognizes '.$key); loyf_equal((int)$row['id'],$result['log_id'],'Upstream retains ID');
}
loyf_equal($before,$wpdb->get_results('SELECT * FROM '.YOWCL_Points_Log::table_name().' ORDER BY id',ARRAY_A),'Upstream replay appends no row');
loyf_equal($balances,$wpdb->get_results("SELECT * FROM {$wpdb->usermeta} WHERE meta_key IN ('user_points','user_earning_points') ORDER BY umeta_id",ARRAY_A),'Upstream replay changes no balance');
echo "Exact upstream shared primitive replay PASS (no Premium activation; Free dependencies)\n";

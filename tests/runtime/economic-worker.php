<?php
if (!defined('LOYF_RUNTIME_DISPOSABLE')) { throw new RuntimeException('Disposable required'); }
$user=(int)getenv('LOYF7_USER'); $object=(int)getenv('LOYF7_OBJECT'); $mode=getenv('LOYF7_MODE'); $key=getenv('LOYF7_KEY'); $barrier=getenv('LOYF7_BARRIER'); $index=(int)getenv('LOYF7_INDEX');
get_user_meta($user,'user_points',true); get_user_meta($user,'user_earning_points',true); get_userdata($user);
file_put_contents($barrier . '.' . $index,'ready'); $until=microtime(true)+15;
while (!file_exists($barrier . '.' . (1-$index))) { if(microtime(true)>$until) { throw new RuntimeException('Producer barrier timeout'); } usleep(10000); }
add_action('yowcl_transaction_test_checkpoint',function($step){if('event_inserted'===$step){usleep(200000);}});
for($attempt=0;$attempt<20;++$attempt) {
    switch($mode) {
        case 'signup': do_action('user_register',$user); break;
        case 'login': $account=get_userdata($user); do_action('wp_login',$account->user_login,$account); break;
        case 'review': do_action('comment_post',$object,1); break;
        case 'level': get_userdata($user)->set_role('loyf_gold'); do_action('set_user_role',$user,'loyf_gold',array('customer')); break;
        case 'order': do_action('woocommerce_order_status_changed',$object,'pending','processing',wc_get_order($object)); break;
        default: throw new RuntimeException('Unknown producer');
    }
    if(YOWCL_Points_Transaction::find($key)) { break; } usleep(100000);
}
$row=YOWCL_Points_Transaction::find($key);
if(!$row){throw new RuntimeException('Producer did not converge');}
echo json_encode(array('key'=>$row['event_key'],'id'=>$row['id'],'delta'=>(int)$row['available_delta']));

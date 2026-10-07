<?php
if(!defined('LOYF_RUNTIME_DISPOSABLE')){throw new RuntimeException('Disposable required');}
$barrier=getenv('LOYF9_BARRIER');$index=(int)getenv('LOYF9_INDEX');file_put_contents($barrier.'.'.$index,'ready');$until=microtime(true)+15;
while(!file_exists($barrier.'.'.(1-$index))){if(microtime(true)>$until){throw new RuntimeException('First race barrier timeout');}usleep(10000);}
add_action('yowcl_transaction_test_checkpoint',function($step){if('event_inserted'===$step){usleep(200000);}});
(new YOWCL_Extra_Points_First_Purchase(false))->maybe_award_on_order((int)getenv('LOYF9_ORDER'));

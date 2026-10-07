<?php
if(!defined('LOYF_RUNTIME_DISPOSABLE')){throw new RuntimeException('Disposable required');}
$barrier=getenv('LOYF10_BARRIER');$index=(int)getenv('LOYF10_INDEX');
if(1===$index){$until=microtime(true)+20;while(!file_exists($barrier)){if(microtime(true)>$until){throw new RuntimeException('Referral election barrier timeout');}usleep(10000);}}
else{add_action('yowcl_reward_test_checkpoint',function($step)use($barrier){if('referral_claimed'===$step){file_put_contents($barrier,'claimed');usleep(250000);}},10,2);}
YOWCL_Referral_Rewards::process((int)getenv('LOYF10_ORDER'));

<?php
if(!defined('LOYF_RUNTIME_DISPOSABLE')){throw new RuntimeException('Disposable required');}
$user=(int)getenv('LOYF10_TOKEN_USER');$barrier=getenv('LOYF10_TOKEN_BARRIER');$i=(int)getenv('LOYF10_TOKEN_INDEX');
wp_set_current_user($user);get_user_meta($user,'_yo_referral_token',false);file_put_contents($barrier.'.'.$i,'primed');$until=microtime(true)+15;
while(!file_exists($barrier.'.'.(1-$i))){if(microtime(true)>$until){throw new RuntimeException('Token barrier timeout');}usleep(10000);}
for($n=0;$n<100;$n++){try{$token=YOWCL_Helper_Referrals::ensure_user_token($user);echo$token;return;}catch(RuntimeException$e){usleep(10000);}}
throw new RuntimeException('Token admission remained busy');

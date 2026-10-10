<?php
/** Automatic policy selection with production readers and storage stand-ins. Native proof is separate. */
require __DIR__.'/migration-opaque.php';
$select=new ReflectionMethod('YOWCL_Free_Migrations','automatic_spec');
if(PHP_VERSION_ID<80100){$select->setAccessible(true);}
foreach(array(false,true) as $wrapped){foreach($targets as $f=>$target){
 $account=array('signup_points'=>'17','signup_enabled'=>'yes','login_points'=>'19','login_enabled'=>'no','review_points'=>'7','unknown'=>(object)array('keep'=>'007'));
 $map=array('gold'=>array('awarded'=>'30','unknown'=>'009'));
 $merged=array('review_enabled'=>'yes','review_points'=>'99','levelup_enabled'=>'yes','levelup_points'=>$map,'unknown'=>'001');
 $using=array('points'=>'100','amount'=>'0.7010','min_points'=>'500','max_points'=>'1000','min_cart'=>'100','unknown'=>'003');
 $rows=array('loyalty_extra_points_rules'=>$account,'loyalty_extra_reviews_gamification_rules'=>$merged,'loyalty_extra_levelup_points_rules'=>$map,'loyalty_notification_email'=>array('points_update'=>true,'level_update'=>false),'loyalty_points_using_rules'=>$using);
 foreach(array('points_reward','points_deduct','level_update')as$id){$rows['woocommerce_yowcl_loyalty_'.$id.'_settings']=array('enabled'=>'yes','subject'=>'Merchant subject','heading'=>'Merchant heading','unknown'=>'005');}
 $wpdb->rows=array();foreach($rows as$n=>$v){$wpdb->rows[$n]=array($wrapped?serialize(serialize($v)):serialize($v));}
 $wpdb->rows['woocommerce_currency']=array('USD');$wpdb->rows['loyalty_points_using_point']=array('yes');$before=$wpdb->rows;
 $spec=$select->invoke(null,$f);opaque_assert(29===$spec['automatic']&&$spec['before']===$before[$target][0],'Inactive intent binds exact pre-image '.$f);
 $flag='redemption'===$f?null:(strpos($f,'email_')===0?'enabled':$f.'_enabled');opaque_assert(($flag?array($flag=>'no'):array())===$spec['patch'],'Only owned enablement changes '.$f);
 opaque_assert($before===$wpdb->rows,'Read-only proposal preserves source/target/evidence '.$f);
 opaque_assert($spec===$select->invoke(null,$f),'Proposal has no arbitrary timestamp or synthetic origin');
 $wpdb->rows[YOWCL_Free_Migrations::witness($f).'_automatic']=array(serialize($spec));
 opaque_assert($spec===$select->invoke(null,$f),'Recorded immutable decision is reused');
 $wpdb->rows[YOWCL_Free_Migrations::witness($f)]=array('1');opaque_assert(null===$select->invoke(null,$f),'Witness is terminal, not legacy replay');
 if('redemption'===$f){opaque_assert(!YOWCL_Free_Migrations::new_redemption_allowed(),'Dormant positive rate is inactive');$wpdb->rows[YOWCL_Free_Migrations::witness($f).'_automatic_enabled']=array('yes');opaque_assert(YOWCL_Free_Migrations::new_redemption_allowed(),'Scoped ordinary configuration can enable future redemption');unset($wpdb->rows[YOWCL_Free_Migrations::witness($f).'_automatic']);opaque_assert(YOWCL_Free_Migrations::new_redemption_allowed(),'Unrelated witnessed redemption does not acquire the gate');}
}}
echo "Automatic inactive selection PASS: eight families, direct/wrapped raw terms, immutable decision and scoped redemption guard. Stand-ins, not native certification.\n";

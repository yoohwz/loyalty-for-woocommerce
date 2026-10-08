<?php
if(!defined('LOYF_RUNTIME_DISPOSABLE')){throw new RuntimeException('Disposable required');}
wp_set_current_user(1);update_option('woocommerce_coming_soon','no');
// This reader/editor fixture represents an already configured Woo store.
update_option('woocommerce_onboarding_profile',array('completed'=>true));delete_transient('_wc_activation_redirect');
update_option('loyalty_customization_loyalty_bubble',array('enabled'=>false));
update_option('loyalty_customization_my_account',array('my_account'=>true,'my_account_slug'=>'my-points','my_account_label'=>'My Points'));
WC_Install::create_pages();YOWCL_Free_Referral::save(true,31);
$a=get_user_by('login','consumer_a')->ID;$b=get_user_by('login','consumer_b')->ID;
$token_a=YOWCL_Helper_Referrals::ensure_user_token($a);$token_b=YOWCL_Helper_Referrals::ensure_user_token($b);
$shortcodes='';foreach(YOWCL_Free_Customer_Components::KINDS as$kind){$shortcodes.='[loyf_'.str_replace('-','_',$kind).' user_id="'.$b.'"]';}
$page=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'Consumer shortcodes','post_content'=>$shortcodes));
$editor=wp_insert_post(array('post_type'=>'page','post_status'=>'draft','post_title'=>'Consumer editor'));
// Formula payload uses raw persisted email to verify escaping at the actual export boundary.
global$wpdb;$wpdb->update($wpdb->users,array('user_email'=>'=1+1,"payload"'),array('ID'=>$b));clean_user_cache($b);
file_put_contents(getenv('LOYF_BROWSER_FIXTURE'),wp_json_encode(array('page'=>$page,'editor'=>$editor,'user_a'=>$a,'user_b'=>$b,'token_a'=>$token_a,'token_b'=>$token_b,'version'=>WC_VERSION,'storage'=>getenv('LOYF_STORAGE'))));

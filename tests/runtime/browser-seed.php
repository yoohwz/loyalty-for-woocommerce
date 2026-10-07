<?php
if(!defined('LOYF_RUNTIME_DISPOSABLE')){throw new RuntimeException('Disposable required');}
$rules=get_option('loyalty_extra_points_rules');$rules['signup_enabled']='no';$rules['login_enabled']='no';update_option('loyalty_extra_points_rules',$rules);
$user=wp_insert_user(array('user_login'=>'blocks_browser','user_email'=>'blocks-browser@example.invalid','user_pass'=>'disposable-only','role'=>'customer'));if(is_wp_error($user)){throw new RuntimeException($user->get_error_message());}
YOWCL_Points_Transaction::apply((int)$user,50,50,'browser:seed');
$product=new WC_Product_Simple();$product->set_name('Blocks browser product');$product->set_regular_price('100');$product->set_virtual(true);$product->set_status('publish');$product->save();
WC_Install::create_pages();
$cart=get_option('woocommerce_cart_page_id');$checkout=get_option('woocommerce_checkout_page_id');
foreach(array('cart'=>$cart,'checkout'=>$checkout) as $kind=>$id){$method=new ReflectionMethod('WC_Install','get_'.$kind.'_block_content');$method->setAccessible(true);wp_update_post(array('ID'=>$id,'post_content'=>$method->invoke(null)));}
file_put_contents(getenv('LOYF_BROWSER_FIXTURE'),wp_json_encode(array('product'=>$product->get_id(),'cart'=>$cart,'checkout'=>$checkout,'user'=>$user,'version'=>WC_VERSION,'storage'=>getenv('LOYF_STORAGE'))));

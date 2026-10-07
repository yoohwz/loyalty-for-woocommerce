<?php
if (!defined('LOYF_RUNTIME_DISPOSABLE')) { throw new RuntimeException('Disposable required'); }
$f=json_decode(file_get_contents(getenv('LOYF8_WORKER_FIXTURE')),true);$index=(int)getenv('LOYF8_WORKER_INDEX');$barrier=$f['barrier'];
wp_set_current_user($f['user']);WC()->initialize_session();WC()->initialize_cart();WC()->cart->empty_cart();WC()->cart->add_to_cart($f['product']);WC()->cart->calculate_totals();
foreach($f['session'] as $key=>$value){WC()->session->set($key,$value);}
$until=microtime(true)+20;
if(1===$index){while(!file_exists($barrier.'.owned')){if(microtime(true)>$until){throw new RuntimeException('Owner barrier timeout');}usleep(10000);}}
elseif(0===$index){add_action('yowcl_order_redemption_test_checkpoint',function($step)use($barrier){if('store_api_request_owned'===$step){file_put_contents($barrier.'.owned','owned');$until=microtime(true)+20;while(!file_exists($barrier.'.denied')){if(microtime(true)>$until){throw new RuntimeException('Contender barrier timeout');}usleep(10000);}}if('before_finalize'===$step){throw new RuntimeException('Concurrent finalization fault');}});}
add_action('woocommerce_rest_checkout_process_payment_with_context',function($context,&$result){if('cod'===$context->payment_method){$result->set_status('success');$result->set_redirect_url($context->order->get_checkout_order_received_url());}},1,2);
$request=new WP_REST_Request('POST','/wc/store/v1/checkout');$request->set_header('Nonce',wp_create_nonce('wc_store_api'));$request->set_body_params($f['body']);$response=rest_do_request($request);
if(1===$index){file_put_contents($barrier.'.denied','denied');if(409!==$response->get_status()){throw new RuntimeException('Contending request admitted: '.wp_json_encode($response->get_data()));}}
elseif(0===$index){if($response->get_status()<400||!YOWCL_Order_Redemption::record($f['id'])){throw new RuntimeException('Original worker did not reach recoverable debit');}}
elseif(200!==$response->get_status()){throw new RuntimeException('Fresh request recovery failed: '.wp_json_encode($response->get_data()));}
echo wp_json_encode(array('status'=>$response->get_status(),'data'=>$response->get_data()));

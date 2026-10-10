<?php
// Retained historical authorized kernel for synthetic ready-store fixtures; no HTTP migration action.
require_once __DIR__.'/assertions.php';
if (!defined('LOYF_RUNTIME_DISPOSABLE')) { throw new RuntimeException('Disposable required'); }
$feature=$args[0]??'';
remove_filter('wp_redirect','WP_CLI\\Utils\\wp_redirect_handler');
$unexpected_die=function(){return function($message){throw new RuntimeException(is_wp_error($message)?$message->get_error_message():(string)$message);};};
add_filter('wp_die_handler',$unexpected_die);add_filter('wp_die_ajax_handler',$unexpected_die);
register_shutdown_function(function()use($feature){if(!YOWCL_Free_Migrations::ready($feature)){throw new RuntimeException('Native feature resolution failed '.$feature);}});
wp_set_current_user(1);
$spec=YOWCL_Free_Migrations::preview($feature,'legacy');
$_SERVER['REQUEST_METHOD']='POST';
$_POST=array('feature'=>$feature,'mode'=>'legacy','fingerprint'=>YOWCL_Free_Migrations::resolution_fingerprint($feature,$spec),'_wpnonce'=>wp_create_nonce('loyf_resolve_'.$feature));
foreach(array('_automatic','_automatic_background','_automatic_enabled') as $suffix){delete_option(YOWCL_Free_Migrations::witness($feature).$suffix);}
YOWCL_Free_Migrations::resolve($feature,'legacy',YOWCL_Free_Migrations::resolution_fingerprint($feature,$spec),$_POST['_wpnonce']);
$_POST=array();

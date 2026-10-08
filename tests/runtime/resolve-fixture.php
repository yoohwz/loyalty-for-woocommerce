<?php
// Explicit native per-feature administrator choice for synthetic existing-store fixtures.
if (!defined('LOYF_RUNTIME_DISPOSABLE')) { throw new RuntimeException('Disposable required'); }
$feature=$args[0]??'';
remove_filter('wp_redirect','WP_CLI\\Utils\\wp_redirect_handler');
$unexpected_die=function(){return function($message){throw new RuntimeException((string)$message);};};
add_filter('wp_die_handler',$unexpected_die);add_filter('wp_die_ajax_handler',$unexpected_die);
register_shutdown_function(function()use($feature){if(!YOWCL_Free_Migrations::ready($feature)){throw new RuntimeException('Native feature resolution failed '.$feature);}});
wp_set_current_user(1);
$spec=YOWCL_Free_Migrations::preview($feature,'legacy');
$_SERVER['REQUEST_METHOD']='POST';
$_POST=array('feature'=>$feature,'mode'=>'legacy','fingerprint'=>hash('sha256',serialize($spec)),'_wpnonce'=>wp_create_nonce('loyf_resolve_'.$feature));
do_action('admin_post_loyf_resolve_migration');
throw new RuntimeException('Native resolution handler missing');

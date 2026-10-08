<?php
// Explicit native per-feature administrator choice for synthetic existing-store fixtures.
if (!defined('LOYF_RUNTIME_DISPOSABLE')) { throw new RuntimeException('Disposable required'); }
$feature=$args[0]??'';
wp_set_current_user(1);
$spec=YOWCL_Free_Migrations::preview($feature,'legacy');
$_SERVER['REQUEST_METHOD']='POST';
$_POST=array('feature'=>$feature,'mode'=>'legacy','fingerprint'=>hash('sha256',serialize($spec)),'_wpnonce'=>wp_create_nonce('loyf_resolve_'.$feature));
do_action('admin_post_loyf_resolve_migration');
throw new RuntimeException('Native resolution handler missing');

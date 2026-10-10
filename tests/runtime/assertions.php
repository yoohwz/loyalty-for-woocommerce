<?php
// Execute native WP/Woo entry points. Expectations describe legacy behavior, not desired vNext semantics.
if (!defined('LOYF_RUNTIME_DISPOSABLE') || !LOYF_RUNTIME_DISPOSABLE) {
    throw new RuntimeException('Disposable runtime required.');
}
function loyf_assert($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
function loyf_equal($expected, $actual, $message) {
    loyf_assert($expected === $actual, $message . ': expected ' . json_encode($expected) . ', got ' . json_encode($actual));
}
function loyf_balance($id, $available, $earning, $label) {
    loyf_equal((float) $available, (float) get_user_meta($id, 'user_points', true), $label . ' available');
    loyf_equal((float) $earning, (float) get_user_meta($id, 'user_earning_points', true), $label . ' earning');
}
function loyf_rows($id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE user_id=%d ORDER BY id', YOSWC_Loyalty_Database::get_table_name(), $id), ARRAY_A);
}
function loyf_order($id, $product) {
    $order = wc_create_order(array('customer_id' => $id));
    $order->add_product($product, 1);
    $order->calculate_totals();
    $order->save();
    return $order;
}
class LOYF_Test_Die extends RuntimeException {}
function loyf_ajax($hook, $fields) {
    $old_request = $_REQUEST;
    $_POST = $fields; $_REQUEST = $fields;
    $die = function () { return function () { throw new LOYF_Test_Die(); }; };
    add_filter('wp_die_handler', $die, PHP_INT_MAX);
    add_filter('wp_die_ajax_handler', $die, PHP_INT_MAX);
    ob_start();
    try { do_action($hook); } catch (LOYF_Test_Die $e) { /* Capture native JSON termination. */ }
    finally {
        $json = ob_get_clean();
        remove_filter('wp_die_handler', $die, PHP_INT_MAX);
        remove_filter('wp_die_ajax_handler', $die, PHP_INT_MAX);
        $_POST = array(); $_REQUEST = $old_request;
    }
    $result = json_decode($json, true);
    loyf_assert(is_array($result) && isset($result['success']), 'Native AJAX response: ' . $json);
    return $result;
}

/** Retained historical protocol kernel tests. No HTTP endpoint or migration UI is registered. */
function loyf_retained_protocol($action) {
    if ('POST'!==($_SERVER['REQUEST_METHOD']??'') || !current_user_can('manage_options') || !YOWCL_Free_Core::owns()) { throw new RuntimeException('migration_resolution_denied'); }
    $f=$_POST['feature']??'';$mode=$_POST['mode']??'';$nonce=$_POST['_wpnonce']??'';
    $label=is_string($f)?$f:'';
    $nonce_action='confirm'===$action?'loyf_confirm_migrations':('retry'===$action?'loyf_retry_'.$label:('replace'===$action?'loyf_replace_'.$label:'loyf_resolve_'.$label));
    if(!is_string($nonce) || !wp_verify_nonce($nonce,$nonce_action)){throw new RuntimeException('migration_resolution_denied');}
    if('retry'===$action){throw new RuntimeException('retired:#loyf-status');}
    if('confirm'!==$action && (!is_string($f)||!in_array($f,YOWCL_Free_Migrations::features(),true)||!is_string($mode)||!in_array($mode,array('canonical','legacy','disable'),true)||!is_string($_POST['fingerprint']??null)||!preg_match('/^[a-f0-9]{64}$/D',$_POST['fingerprint']))){throw new RuntimeException('migration_resolution_denied');}
    try {
        if('confirm'===$action){YOWCL_Free_Migrations::admit($_POST['choices']??array(),$_POST['batch']??'',wp_unslash($nonce));$result='admitted';}
        elseif('replace'===$action){$result=YOWCL_Free_Migrations::replace_pending($f,$mode,wp_unslash($_POST['fingerprint']),wp_unslash($nonce));}
        else{$result=YOWCL_Free_Migrations::resolve($f,$mode,wp_unslash($_POST['fingerprint']),wp_unslash($nonce));}
    } catch(Throwable $e){$result=in_array($e->getMessage(),array('migration_completion_unknown','migration_admission_unknown'),true)?'unknown':('resolve'===$action&&in_array($e->getMessage(),array('migration_resolution_stale','migration_target_changed'),true)?$e->getMessage():'unconfirmed');}
    throw new RuntimeException('retained-kernel:result='.$result.'&batch_result='.$result.'#loyf-review-'.$label);
}

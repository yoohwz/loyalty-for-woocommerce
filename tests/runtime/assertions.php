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
    $_POST = $fields;
    $die = function () { return function () { throw new LOYF_Test_Die(); }; };
    add_filter('wp_die_handler', $die, PHP_INT_MAX);
    add_filter('wp_die_ajax_handler', $die, PHP_INT_MAX);
    ob_start();
    try { do_action($hook); } catch (LOYF_Test_Die $e) { /* Capture native JSON termination. */ }
    finally {
        $json = ob_get_clean();
        remove_filter('wp_die_handler', $die, PHP_INT_MAX);
        remove_filter('wp_die_ajax_handler', $die, PHP_INT_MAX);
        $_POST = array();
    }
    $result = json_decode($json, true);
    loyf_assert(is_array($result) && isset($result['success']), 'Native AJAX response: ' . $json);
    return $result;
}

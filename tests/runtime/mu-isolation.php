<?php
// Test-only isolation; never shipped in the plugin package.
defined('ABSPATH') || exit;
define('LOYF_RUNTIME_DISPOSABLE', true);
define('LOY_RUNTIME_DISPOSABLE', true);
$GLOBALS['loyf_mail'] = array();
add_filter('pre_wp_mail', function ($return, $atts) {
    $GLOBALS['loyf_mail'][] = $atts;
    return true;
}, PHP_INT_MAX, 2);
add_filter('pre_http_request', function () {
    return new WP_Error('loyf_runtime_http_blocked', 'External HTTP is disabled in the disposable runtime.');
}, PHP_INT_MAX);
// Only disposable cookie-header probes may simulate SSL before native init capture.
if (isset($_GET['loyf10_ssl'])) { $_SERVER['HTTPS']='on'; }
add_action('template_redirect',function(){
    if(isset($_GET['loyf10_capture'])) { wp_send_json(array('referrer'=>YOWCL_Helper_Referrals::get_referrer_from_cookie(),'cookie'=>$_COOKIE['yowcl_ref']??'')); }
},0);
// Test-only native Woo General POST failure: a rejected scalar UPDATE must not certify all saves.
add_filter('query',static function($sql){
    static $failed=false;
    if (!$failed && isset($_POST['_loyf25_rounding_fault']) && 0===strpos($sql,'UPDATE ') && false!==strpos($sql,"'loyalty_points_rounding'") && function_exists('wp_get_current_user') && current_user_can('manage_options') && is_string($_POST['_wpnonce']??null) && wp_verify_nonce($_POST['_wpnonce'],'woocommerce-settings')) {
        $failed=true;global $wpdb;$wpdb->suppress_errors(true);return 'SELECT * FROM loyf25_deliberately_unavailable_rounding';
    }
    return $sql;
},PHP_INT_MAX);

// Fail a later witness read only inside the custom redemption save.
add_filter('query',static function($sql){
    if (isset($_POST['_loyf25_redemption_read_fault']) && 0===strpos($sql,'SELECT ') && false!==strpos($sql,"loyf_migration_redemption_v1") && function_exists('wp_get_current_user') && current_user_can('manage_options') && is_string($_POST['_wpnonce']??null) && wp_verify_nonce($_POST['_wpnonce'],'woocommerce-settings')) {
        foreach(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if ('save_using_point_rules'===($frame['function']??'')) { global $wpdb;$wpdb->suppress_errors(true);return 'SELECT * FROM loyf25_deliberately_unavailable_witness'; }
        }
    }
    return $sql;
},PHP_INT_MAX);

// One transient target read fault at the raw-preservation read, not initial readiness.
add_filter('query',static function($sql){
    static $failed=false;
    if (!$failed && isset($_POST['_loyf25_redemption_term_fault']) && 0===strpos($sql,'SELECT ') && false!==strpos($sql,"loyalty_points_using_rules") && function_exists('wp_get_current_user') && current_user_can('manage_options') && is_string($_POST['_wpnonce']??null) && wp_verify_nonce($_POST['_wpnonce'],'woocommerce-settings')) {
        $frames=array_column(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),'function');
        if (!in_array('save_using_point_rules',$frames,true)) { return $sql; }
        foreach(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
            if ('canonical'===($frame['function']??'')) { $failed=true;global $wpdb;$wpdb->suppress_errors(true);return 'SELECT * FROM loyf25_deliberately_unavailable_term'; }
        }
    }
    return $sql;
},PHP_INT_MAX);

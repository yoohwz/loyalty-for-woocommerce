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

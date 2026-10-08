<?php
// Native administration regression; every transport is intercepted in a disposable site.
require_once __DIR__ . '/assertions.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once WP_PLUGIN_DIR . '/woocommerce/includes/admin/wc-admin-functions.php';
global $wpdb;
$name = 'yoswc_loyalty_subscription_pushed';
$original = $wpdb->get_row( $wpdb->prepare( "SELECT option_value,autoload FROM {$wpdb->options} WHERE option_name=%s", $name ), ARRAY_A );
$read = static function () use ( $wpdb, $name ) {
    return $wpdb->get_row( $wpdb->prepare( "SELECT option_value,autoload FROM {$wpdb->options} WHERE option_name=%s", $name ), ARRAY_A );
};
$restore = static function ( $row ) use ( $wpdb, $name ) {
    $wpdb->delete( $wpdb->options, array( 'option_name' => $name ) );
    if ( null !== $row ) { $wpdb->insert( $wpdb->options, array( 'option_name'=>$name, 'option_value'=>$row['option_value'], 'autoload'=>$row['autoload'] ) ); }
    wp_cache_flush();
};
$calls = array();
$intercept = static function ( $pre, $args, $url ) use ( &$calls ) {
    $plugin_origin = false;
    foreach ( debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS ) as $frame ) {
        if ( isset( $frame['file'] ) && 0 === strpos( $frame['file'], YOSWC_LOYALTY_PLUGIN_DIR ) ) { $plugin_origin = true; }
    }
    $calls[] = array( 'url'=>$url, 'plugin_origin'=>$plugin_origin );
    return new WP_Error( 'loyf_disposable_http_blocked', 'No external transport in this fixture.' );
};
add_filter( 'pre_http_request', $intercept, PHP_INT_MAX, 3 );
$snapshot = new ReflectionMethod( 'YOWCL_Free_Onboarding', 'program_snapshot' );
$snapshot->setAccessible( true );
$version = get_option( 'yoswc_loyalty_version', null );
try {
    loyf_assert( ! class_exists( 'YOSWC_Loyalty_Push_Subscription', false ), 'Retired reporting symbol loaded' );
    loyf_assert( ! file_exists( YOSWC_LOYALTY_PLUGIN_DIR . 'inc/cores/api/push-subscription.php' ), 'Retired reporting file packaged' );
    loyf_assert( false !== has_action( 'admin_init', array( 'YOSWC_Loyalty_Backend', 'check_version' ) ), 'Native backend callback missing' );
    foreach ( array( null, array('option_value'=>'yes','autoload'=>'no'), array('option_value'=>'no','autoload'=>'yes'), array('option_value'=>'a:broken;\x00historical','autoload'=>'no') ) as $row ) {
        $restore( $row );
        delete_option( 'yoswc_loyalty_version' );
        // Repeated absent/changed-version admin requests must retain dormant bytes.
        do_action( 'admin_init' );
        loyf_equal( YOSWC_LOYALTY_VERSION, get_option( 'yoswc_loyalty_version' ), 'Absent version bookkeeping' );
        loyf_equal( $row, $read(), 'Dormant reporting option mutated on absent-version boot' );
        $program = $snapshot->invoke( null );
        loyf_assert( ! array_key_exists( $name, $program ), 'Historical reporting option entered onboarding authority' );
        $restore( null );
        loyf_equal( $program, $snapshot->invoke( null ), 'Reporting option changed program snapshot' );
        $restore( $row );
        update_option( 'yoswc_loyalty_version', 'historical-version' );
        do_action( 'admin_init' );
        do_action( 'admin_init' );
        loyf_equal( YOSWC_LOYALTY_VERSION, get_option( 'yoswc_loyalty_version' ), 'Changed version bookkeeping' );
        loyf_equal( $row, $read(), 'Dormant reporting option mutated' );
    }
    // WordPress/Woo may perform their own administration HTTP requests. Any
    // request originating in this plugin would be an unexpected replacement.
    foreach ( $calls as $call ) {
        loyf_assert( ! $call['plugin_origin'], 'Plugin-origin administration HTTP: ' . $call['url'] );
        loyf_assert( in_array( $call['url'], array( 'https://api.wordpress.org/plugins/update-check/1.1/', 'http://api.wordpress.org/plugins/update-check/1.1/' ), true ), 'Unclassified administration HTTP: ' . $call['url'] );
    }
    echo "Installation reporting retirement: PASS (absent/yes/no/malformed; native admin_init; unchanged version handling and onboarding snapshot).\n";
} finally {
    $restore( $original );
    if ( null === $version ) { delete_option( 'yoswc_loyalty_version' ); }
    else { update_option( 'yoswc_loyalty_version', $version ); }
    remove_filter( 'pre_http_request', $intercept, PHP_INT_MAX );
}

<?php
/* Loaded only from the disposable site's mu-plugins directory. */
defined( 'ABSPATH' ) || exit;
define( 'LOY_RUNTIME_DISPOSABLE', true );

if ( '1' !== getenv( 'LOY_RUNTIME_REAL_LICENSE' ) ) {
class YOWCL_License_Validator {
	public static function is_premium_active() {
		return true;
	}
}
}

add_filter( 'pre_wp_mail', '__return_true', PHP_INT_MAX );
add_filter( 'pre_http_request', function () {
	return new WP_Error( 'loy_runtime_http_blocked', 'External HTTP is disabled in the disposable runtime.' );
}, PHP_INT_MAX );

// Core reward errors remain visible in the disposable harness, never on the installed site.
add_action( 'yowcl_reward_test_error', static function ( $key, $message ) {
	if ( ! in_array( $message, array( 'reward_injected_failure', 'reward_projection_busy', 'reward_level_projection_busy' ), true ) ) {
		fwrite( STDERR, 'Core reward recovery: ' . $key . ' ' . $message . "\n" );
	}
}, 10, 2 );

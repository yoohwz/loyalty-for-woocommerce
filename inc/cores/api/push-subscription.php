<?php

defined( 'ABSPATH' ) || exit;

class YOSWC_Loyalty_Push_Subscription {
	const ENDPOINT = 'https://yoohw.com/wp-json/yoohw/v1/plugin-subscription';
	const OPTION_PUSHED = 'yoswc_loyalty_subscription_pushed';

	public static function maybe_push() {
		if ( 'yes' === get_option( self::OPTION_PUSHED, 'no' ) ) {
			return false;
		}

		$result = self::push_subscription();

		if ( false !== $result && ! is_wp_error( $result ) ) {
			update_option( self::OPTION_PUSHED, 'yes', false );
		}

		return $result;
	}

	public static function push_subscription() {
		$site_url = home_url();
		$payload  = array(
			'plugin'      => 'loyalty-for-woocommerce',
			'version'     => defined( 'YOSWC_LOYALTY_VERSION' ) ? YOSWC_LOYALTY_VERSION : '',
			'site_url'    => esc_url_raw( $site_url ),
			'site_domain' => sanitize_text_field( (string) wp_parse_url( $site_url, PHP_URL_HOST ) ),
			'admin_email' => sanitize_email( get_option( 'admin_email' ) ),
		);

		return wp_remote_post(
			self::ENDPOINT,
			array(
				'timeout'     => 5,
				'redirection' => 3,
				'blocking'    => false,
				'user-agent'  => 'Loyalty for WooCommerce/' . $payload['version'] . '; ' . $payload['site_url'],
				'body'        => $payload,
			)
		);
	}
}

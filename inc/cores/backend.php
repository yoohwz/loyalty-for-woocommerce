<?php

if (!defined('ABSPATH')) {
	exit;
}

class YOSWC_Loyalty_Backend {
	private static $version = '1.0.0';

	public function __construct() {
		if ( defined( 'YOSWC_LOYALTY_VERSION' ) ) {
			self::$version = YOSWC_LOYALTY_VERSION;
		}

		add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
		add_action( 'admin_init', [ __CLASS__, 'check_version' ] );
		
		$this->includes();
	}

	public function enqueue_scripts() {
		if (is_admin()) {
			wp_enqueue_style('loyalty-backend-css', plugin_dir_url(__FILE__) . '../../css/backend-style.css', array(), '1.4.1');
		}
	}

	public static function check_version() : void {
		$installed_version = get_option( 'yoswc_loyalty_version', '' );
		$is_first_install = '' === $installed_version;

		if ( $installed_version !== self::$version ) {
			update_option( 'yoswc_loyalty_version', self::$version );

			if ( $is_first_install && class_exists( 'YOSWC_Loyalty_Push_Subscription' ) ) {
				YOSWC_Loyalty_Push_Subscription::maybe_push();
			}
		}
	}

	public function includes() {
		include_once plugin_dir_path(__FILE__) . '../backend/settings.php';
		include_once plugin_dir_path(__FILE__) . '../backend/users.php';
		include_once plugin_dir_path(__FILE__) . '../backend/yoohw-woo-settings-tabs-reorder.php';
		include_once plugin_dir_path(__FILE__) . '../backend/actions/emails/notifications-email.php';
		include_once plugin_dir_path(__FILE__) . 'api/push-subscription.php';
	}
}

new YOSWC_Loyalty_Backend();

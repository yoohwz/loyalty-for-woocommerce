<?php
/**
 * Plugin Name: Loyalty for WooCommerce
 * Plugin URI: https://wordpress.org/plugins/loyalty-for-woocommerce/
 * Description: Create a flexible loyalty program for WooCommerce—reward customers with points to boost repeat sales and customer retention.
 * Version: 1.2.2
 * Author: YoOhw.com
 * Author URI: https://yoohw.com
 * Requires at least: 6.3
 * Requires PHP: 7.4
 * Text Domain: loyalty-for-woocommerce
 * License: GPLv2 or later
 * License URI: http://www.gnu.org/licenses/gpl-2.0.html
 * Requires Plugins: woocommerce
 */

if (!defined('ABSPATH')) {
	exit;
}

class YOSWC_Loyalty {
	public function __construct() {
		$yoswc_loyalty_plugin_data = get_file_data(__FILE__, ['Version' => 'Version'], false);
		$yoswc_loyalty_plugin_version = isset($yoswc_loyalty_plugin_data['Version']) ? $yoswc_loyalty_plugin_data['Version'] : '';

		define('YOSWC_LOYALTY_VERSION', $yoswc_loyalty_plugin_version);
		define('YOSWC_LOYALTY_PLUGIN_FILE', __FILE__);
		define('YOSWC_LOYALTY_PLUGIN_DIR', plugin_dir_path(__FILE__));
		define('YOSWC_LOYALTY_PLUGIN_BASENAME', plugin_basename(__FILE__));

		add_filter('plugin_action_links_' . plugin_basename(__FILE__), [$this, 'add_action_links']);

		$this->includes();
	}

	public function includes() {
		include_once plugin_dir_path(__FILE__) . 'inc/cores/notices.php';
		include_once plugin_dir_path(__FILE__) . 'inc/cores/database.php';

		if ( ! $this->is_woocommerce_active() ) {
			return;
		}

		include_once plugin_dir_path(__FILE__) . 'inc/cores/backend.php';
		include_once plugin_dir_path(__FILE__) . 'inc/cores/frontend.php';
	}

	private function is_woocommerce_active() {
		if ( class_exists( 'WooCommerce' ) || function_exists( 'WC' ) ) {
			return true;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( 'woocommerce/woocommerce.php' ) || is_plugin_active_for_network( 'woocommerce/woocommerce.php' );
	}

	public function add_action_links($links) {
		$settings_link = '<a href="admin.php?page=wc-settings&tab=loyalty">Settings</a>';
		array_unshift($links, $settings_link);
		return $links;
	}
}

new YOSWC_Loyalty();

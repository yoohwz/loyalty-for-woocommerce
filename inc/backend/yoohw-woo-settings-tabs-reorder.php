<?php

defined('ABSPATH') || exit;

if (!class_exists('YoOhw_WooCommerce_Settings_Tabs_Reorder')) {
	class YoOhw_WooCommerce_Settings_Tabs_Reorder {
		public function __construct() {
			add_filter('woocommerce_settings_tabs_array', array($this, 'reorder_woocommerce_settings_tabs'), 50);
		}

		public function reorder_woocommerce_settings_tabs($tabs) {
			// Define the desired order of tabs
			$desired_order = array(
				'general',     // General tab
				'products',    // Products tab
				'tax',         // Tax tab
				'shipping',    // Shipping tab
				'orders',      // Custom Orders tab
				'checkout',    // Checkout tab
				'account',     // Accounts & Privacy tab
				'loyalty',     // Custom Loyalty tab
				'email',       // Email tab
				'integration', // Integration tab
				'site-visibility', // Custom Site Visibility tab
				'advanced',    // Advanced tab
			);
		
			// Create a new array with the tabs reordered
			$reordered_tabs = array();
			foreach ($desired_order as $tab_key) {
				if (isset($tabs[$tab_key])) {
					$reordered_tabs[$tab_key] = $tabs[$tab_key];
				}
			}
		
			// Add any remaining tabs that are not included in the desired order
			foreach ($tabs as $key => $label) {
				if (!isset($reordered_tabs[$key])) {
					$reordered_tabs[$key] = $label;
				}
			}
		
			return $reordered_tabs;
		}
	}

	new YoOhw_WooCommerce_Settings_Tabs_Reorder();
}
<?php

if (!defined('ABSPATH')) {
	exit;
}

class YOSWC_Loyalty_Notices {

	public function __construct() {
		add_action('admin_notices', [$this, 'display_notices']);
		add_action('wp_ajax_never_show_yoswc_loyalty_notice', [$this, 'never_show_notice']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_inline_scripts']);
	}

	public function display_notices() {
		if ( $this->woocommerce_missing_notice() ) {
			return;
		}

		if ( ! $this->is_loyalty_admin_screen() ) {
			return;
		}

		$this->admin_notice();
	}

	private function is_loyalty_admin_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'woocommerce_page_wc-settings' !== $screen->id ) {
			return false;
		}

		$tab = sanitize_key( (string) filter_input( INPUT_GET, 'tab', FILTER_UNSAFE_RAW ) );
		return 'loyalty' === $tab;
	}

	private function woocommerce_missing_notice() {
		if ( class_exists( 'WooCommerce' ) || function_exists( 'WC' ) ) {
			return false;
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return true;
		}

		echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Loyalty for WooCommerce requires WooCommerce to be installed and active.', 'loyalty-for-woocommerce' ) . '</strong></p></div>';

		return true;
	}

	public function admin_notice() {
		$user_id = get_current_user_id();
		$activation_time = get_user_meta($user_id, 'yoswc_loyalty_activation_time', true);
		$current_time = current_time('timestamp');
	
		if (get_user_meta($user_id, 'yoswc_loyalty_never_show_again', true) === 'yes') {
			return;
		}
	
		if (!$activation_time) {
			update_user_meta($user_id, 'yoswc_loyalty_activation_time', $current_time);
			return;
		}
	
		$time_since_activation = $current_time - $activation_time;
		$days_since_activation = floor($time_since_activation / DAY_IN_SECONDS);
	
			if (current_user_can('manage_options') && $days_since_activation >= 1) {
				echo '<div class="notice notice-info yol-review is-dismissible">
						<p>Thank you for using WooCommerce Loyalty! Please support us by <a href="https://wordpress.org/plugins/loyalty-for-woocommerce/#reviews/#new-post" target="_blank">leaving a review</a> <span style="color: #e26f56;">&#9733;&#9733;&#9733;&#9733;&#9733;</span> to keep updating & improving.</p>
						<p><a href="#" onclick="YOSWC_LoyaltyNotice.dismissForever()">Never show this again</a></p>
					  </div>';
		}
	}

	public function enqueue_inline_scripts() {
		$nonce_never_show = wp_create_nonce('never_show_yoswc_loyalty_notice_nonce');

		$script = "
			var YOSWC_LoyaltyNotice = {
				dismissForever: function() {
					jQuery.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'never_show_yoswc_loyalty_notice',
							security: '{$nonce_never_show}'
						},
						success: function() {
							jQuery('.notice.notice-info.yol-review').hide();
						}
					});
				}
			};
		";

		wp_add_inline_script('jquery', $script);
	}

	public function never_show_notice() {
		check_ajax_referer('never_show_yoswc_loyalty_notice_nonce', 'security');
		$user_id = get_current_user_id();
		update_user_meta($user_id, 'yoswc_loyalty_never_show_again', 'yes');
	}
}

new YOSWC_Loyalty_Notices();

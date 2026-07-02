<?php

if (!defined('ABSPATH')) {
	exit;
}

class YOSWC_Loyalty_Frontend {

	public function __construct() {
		add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

		$this->includes();
	}

	public function enqueue_scripts() {
		wp_enqueue_style(
			'loyalty-frontend-css',
			plugin_dir_url(__FILE__) . '../../css/frontend-style.css',
			array(),
			defined('YOSWC_LOYALTY_VERSION') ? YOSWC_LOYALTY_VERSION : '1.0'
		);
	
		$shop_page_message_style = maybe_unserialize(get_option('loyalty_customization_shop_page'));
		$product_page_message_style = maybe_unserialize(get_option('loyalty_customization_product_page'));
		$loyalty_levels = get_option('loyalty_customization_levels');
		$membercard = maybe_unserialize(get_option('loyalty_customization_membercard'));
		$user_role = $this->get_user_role();
	
		if ($product_page_message_style && is_array($product_page_message_style)) {
			$border_style = isset($product_page_message_style['product_page_border_style']) ? esc_attr($product_page_message_style['product_page_border_style']) : 'solid';
			$border_width = isset($product_page_message_style['product_page_border_width']) ? esc_attr($product_page_message_style['product_page_border_width']) : '1px';
			$border_radius = isset($product_page_message_style['product_page_border_radius']) ? esc_attr($product_page_message_style['product_page_border_radius']) : '5px';
			$text_color = isset($product_page_message_style['product_page_text_color']) ? esc_attr($product_page_message_style['product_page_text_color']) : '#000000';
			$background_color = isset($product_page_message_style['product_page_background_color']) ? esc_attr($product_page_message_style['product_page_background_color']) : '#ffffff';
			$border_color = isset($product_page_message_style['product_page_border_color']) ? esc_attr($product_page_message_style['product_page_border_color']) : '#636363';
	
			$product_page_message_css = "
				.loyalty-earning-points-message-content {
					border-style: {$border_style};
					border-width: {$border_width};
					border-radius: {$border_radius};
					color: {$text_color};
					background-color: {$background_color};
					border-color: {$border_color};
				}
			";
	
			wp_add_inline_style('loyalty-frontend-css', $product_page_message_css);
		}
	
		if ($shop_page_message_style && is_array($shop_page_message_style)) {
			$border_style = isset($shop_page_message_style['shop_page_border_style']) ? esc_attr($shop_page_message_style['shop_page_border_style']) : 'solid';
			$border_width = isset($shop_page_message_style['shop_page_border_width']) ? esc_attr($shop_page_message_style['shop_page_border_width']) : '1px';
			$border_radius = isset($shop_page_message_style['shop_page_border_radius']) ? esc_attr($shop_page_message_style['shop_page_border_radius']) : '5px';
			$text_color = isset($shop_page_message_style['shop_page_text_color']) ? esc_attr($shop_page_message_style['shop_page_text_color']) : '#000000';
			$background_color = isset($shop_page_message_style['shop_page_background_color']) ? esc_attr($shop_page_message_style['shop_page_background_color']) : '#ffffff';
			$border_color = isset($shop_page_message_style['shop_page_border_color']) ? esc_attr($shop_page_message_style['shop_page_border_color']) : '#636363';
	
			$shop_page_message_css = "
				.loyalty-shop-message-content {
					border-style: {$border_style};
					border-width: {$border_width};
					border-radius: {$border_radius};
					color: {$text_color};
					background-color: {$background_color};
					border-color: {$border_color};
				}
			";
	
			wp_add_inline_style('loyalty-frontend-css', $shop_page_message_css);
		}
	
		if ($loyalty_levels && isset($loyalty_levels[$user_role]) && !empty($loyalty_levels[$user_role]['text_color'])) {
			$stroke_color = esc_attr($loyalty_levels[$user_role]['text_color']);
			$my_account_my_points_css = "
				.circle {
					stroke: {$stroke_color};
				}
				.progress-bar-fill {
					background-color: {$stroke_color};
				}
			";
			wp_add_inline_style('loyalty-frontend-css', $my_account_my_points_css);
		}

		if ($membercard && is_array($membercard)) {
			$text_color = isset($membercard['membercard_text_color']) ? esc_attr($membercard['membercard_text_color']) : '#000000';
			$background_color = isset($membercard['membercard_background_color']) ? esc_attr($membercard['membercard_background_color']) : '#f9f9f9';
			$border_color = isset($membercard['membercard_border_color']) ? esc_attr($membercard['membercard_border_color']) : '#cccccc';
	
			$membercard_css = "
				.loyalty-points-card {
					color: {$text_color};
					background-color: {$background_color};
					border: 1px solid {$border_color};
				}
				.loyalty-points-card h3 {
					color: {$text_color};
				}
			";
	
			wp_add_inline_style('loyalty-frontend-css', $membercard_css);
		}
	}
		

	public function includes() {
		include_once plugin_dir_path(__FILE__) . '../frontend/cart-checkout.php';
		include_once plugin_dir_path(__FILE__) . '../frontend/product-message.php';
		include_once plugin_dir_path(__FILE__) . '../frontend/shop-message.php';
		include_once plugin_dir_path(__FILE__) . '../frontend/loyalty-info-bubble.php';
		include_once plugin_dir_path(__FILE__) . '../frontend/my-account.php';
	}

	public function get_user_role() {
		$user = wp_get_current_user();
		return !empty($user->roles) ? $user->roles[0] : 'customer';
	}
	
}

new YOSWC_Loyalty_Frontend();

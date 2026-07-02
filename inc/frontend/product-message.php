<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Product_Message_Earning_Points {

	public function __construct() {
		$customization_options = maybe_unserialize(get_option('loyalty_customization_product_page'));
	
		if (isset($customization_options['product_page_position'])) {
			$position = $customization_options['product_page_position'];
	
			switch ($position) {
				case 'before_add_to_cart':
					add_action('woocommerce_before_add_to_cart_button', [$this, 'display_earning_points_message']);
					break;
	
				case 'after_add_to_cart':
					add_action('woocommerce_after_add_to_cart_button', [$this, 'display_earning_points_message']);
					break;
	
				case 'before_excerpt':
					add_action('woocommerce_single_product_summary', [$this, 'display_earning_points_message'], 11);
					break;
	
				case 'after_excerpt':
					add_action('woocommerce_single_product_summary', [$this, 'display_earning_points_message'], 21);
					break;
	
				case 'after_product_meta':
					add_action('woocommerce_product_meta_end', [$this, 'display_earning_points_message']);
					break;
	
				default:
					add_action('woocommerce_after_add_to_cart_button', [$this, 'display_earning_points_message']);
					break;
			}
		} else {
			add_action('woocommerce_after_add_to_cart_button', [$this, 'display_earning_points_message']);
		}
	}	

	public function display_earning_points_message() {
		$customization_options = maybe_unserialize( get_option( 'loyalty_customization_product_page' ) );
	
		// Only proceed if product_page is enabled
		if ( empty( $customization_options['product_page'] ) || $customization_options['product_page'] !== true ) {
			return;
		}
	
		global $product;
		$is_logged_in = is_user_logged_in();
	
		// If guest, only show when product_page_guest === '1'
		if ( ! $is_logged_in ) {
			if ( empty( $customization_options['product_page_guest'] )
			  || (string) $customization_options['product_page_guest'] !== '1'
			) {
				return;
			}
		}
	
		// Determine user ID (0 for guests)
		$user_id = $is_logged_in ? get_current_user_id() : 0;
		$points  = $this->calculate_earning_points( $product, $user_id );
	
		if ( $points > 0 ) :
			// Try custom icon; fall back to default reward.svg
			$custom_icon = get_option( 'loyalty_customization_message_icon' );
			if ( ! empty( $custom_icon ) ) {
				$icon_url = esc_url( $custom_icon );
			} else {
				$icon_url = esc_url( plugin_dir_url( __FILE__ ) . '../../img/reward.svg' );
			}
			?>
			<div class="loyalty-earning-points-message">
				<span class="loyalty-earning-points-message-content">
						<img src="<?php echo esc_url($icon_url); ?>"
						alt="<?php esc_attr_e( 'Reward Icon', 'loyalty-for-woocommerce' ); ?>"
						width="20" height="auto">
					<?php
					echo wp_kses_post( sprintf(
						/* translators: %d: Points customers can earn by purchasing the product. */
						__( 'Earn<b>&nbsp;%d points&nbsp;</b>when you purchase this product', 'loyalty-for-woocommerce' ),
						$points
					) );
					?>
				</span>
			</div>
		<?php endif;
	}
	
	private function calculate_earning_points( $product, $user_id ) {
		// If we have a real user, use their role; otherwise default to 'customer'
		$user = $user_id ? get_userdata( $user_id ) : false;
		$user_role = ( $user && ! empty( $user->roles ) ) ? $user->roles[0] : 'customer';
	
		$earning_rules   = maybe_unserialize( get_option( 'loyalty_points_earning_rules', [] ) );
		$earned_points   = 0;
	
		if ( isset( $earning_rules[ $user_role ] ) ) {
			$rule            = $earning_rules[ $user_role ];
			$points          = (int) $rule['points'];
			$amount          = (int) $rule['amount'];
			$earning_options = maybe_unserialize( get_option( 'loyalty_points_earning_option', [] ) );
			$rounding_option = get_option( 'loyalty_points_rounding', 'round_down' );
	
			// Exclude taxes if selected
			$exclude_tax = is_array( $earning_options ) && in_array( 'taxes', $earning_options, true );
	
			if ( $exclude_tax ) {
				$product_price = wc_get_price_excluding_tax( $product );
			} else {
				$product_price = wc_get_price_including_tax( $product );
			}
	
			if ( $amount > 0 ) {
				$earned_points = ( $product_price / $amount ) * $points;
	
				switch ( $rounding_option ) {
					case 'round_up':
						$earned_points = ceil( $earned_points );
						break;
					case 'round_down':
						$earned_points = floor( $earned_points );
						break;
					default:
						$earned_points = round( $earned_points );
				}
			}
		}
	
		return (int) $earned_points;
	}
		
}

new YOSWC_Loyalty_Product_Message_Earning_Points();

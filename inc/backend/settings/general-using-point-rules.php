<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Settings_Using_Point_Rules {
	public function __construct() {
		add_action('woocommerce_admin_field_set_using_point_rules', [$this, 'render_using_point_field']);
	}

	public function render_using_point_field( $value ) {
		$saved_using_points = get_option('loyalty_points_using_rules', array('points' => '', 'amount' => ''));

		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php echo esc_html( $value['name'] ); ?></label>
			</th>
			<td class="forminp">
				<div id="loyalty_using_points">
					<?php 
					echo wp_kses(
						$this->get_point_html( $saved_using_points ),
						array(
							'div' => array( 'class' => array() ),
							'input' => array(
								'type' => array(),
								'name' => array(),
								'style' => array(),
								'value' => array(),
								'placeholder' => array(),
								'min' => array()
							),
							'span' => array(),
							'strong' => array(),
							'em' => array(),
						)
					);
					?>
				</div>
				<?php wp_nonce_field('save_using_point_rules', 'using_point_rules_nonce'); ?>
				<p class="description">
					<?php echo wp_kses_post( __( 'Set how many points to exchange for a discount to the customers.', 'loyalty-for-woocommerce' ) ); ?>
				</p>
			</td>
		</tr>

		<?php
	}

	private function get_point_html( $settings ) {
		ob_start();
		?>
		<div class="loyalty_using_point">
			<input type="number" name="loyalty_using_points" style="width: 84px;" value="<?php echo esc_attr($settings['points']); ?>" placeholder="<?php esc_attr_e('points', 'loyalty-for-woocommerce'); ?>" min="1" />
			<?php echo esc_html__( 'point(s) for', 'loyalty-for-woocommerce' ); ?> 
			<input type="number" name="loyalty_using_amount" style="width: 84px;" value="<?php echo esc_attr($settings['amount']); ?>" placeholder="<?php esc_attr_e('amount', 'loyalty-for-woocommerce'); ?>" min="1" /> 
			<?php echo esc_html( get_woocommerce_currency_symbol() ); ?>
		</div>
		<?php
		return ob_get_clean();
	}
}

new YOSWC_Loyalty_Settings_Using_Point_Rules ();

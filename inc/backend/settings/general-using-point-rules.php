<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Settings_Using_Point_Rules {
	public function __construct() {
		add_action('woocommerce_admin_field_set_using_point_rules', [$this, 'render_using_point_field']);
	}

	public function render_using_point_field( $value ) {
		$saved_using_points = YOWCL_Free_Migrations::readable('redemption') ? maybe_unserialize(get_option('loyalty_points_using_rules', array('points' => '', 'amount' => ''))) : array();
        $saved_using_points = is_array($saved_using_points) ? $saved_using_points : array('points' => '', 'amount' => '');

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
								'min' => array(), 'step' => array(), 'aria-label' => array(), 'disabled' => array()
							),
							'span' => array(),
							'strong' => array(),
							'em' => array(),
						)
					);
					?>
				</div>
				<?php if (!YOWCL_Free_Migrations::ready('redemption') || !YOWCL_Free_Migrations::readable('redemption')) { echo '<p>'.YOWCL_Free_Migrations::held_link('redemption').'</p>'; } ?>
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
        <?php $blocked=!YOWCL_Free_Migrations::ready('redemption') || !YOWCL_Free_Migrations::readable('redemption'); ?>
			<input type="number" name="loyalty_using_points" step="any" aria-label="<?php esc_attr_e('Exchange points', 'loyalty-for-woocommerce'); ?>" style="width: 84px;" value="<?php echo esc_attr($settings['points'] ?? ''); ?>" placeholder="<?php esc_attr_e('points', 'loyalty-for-woocommerce'); ?>" min="0" <?php disabled($blocked); ?> />
			<?php echo esc_html__( 'point(s) for', 'loyalty-for-woocommerce' ); ?>
			<input type="number" name="loyalty_using_amount" style="width: 84px;" value="<?php echo esc_attr(is_numeric($settings['amount'] ?? '') ? wc_format_decimal($settings['amount'],wc_get_price_decimals(),true) : ''); ?>" aria-label="<?php esc_attr_e('Discount amount', 'loyalty-for-woocommerce'); ?>" placeholder="<?php esc_attr_e('amount', 'loyalty-for-woocommerce'); ?>" min="0" step="<?php echo esc_attr(pow(10, -wc_get_price_decimals())); ?>" <?php disabled($blocked); ?> />
			<?php echo esc_html( get_woocommerce_currency_symbol() ); ?>
		</div>
		<?php
		return ob_get_clean();
	}
}

new YOSWC_Loyalty_Settings_Using_Point_Rules ();

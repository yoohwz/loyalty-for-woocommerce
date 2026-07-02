<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Settings_Earning_Point_Rules {
	public function __construct() {
		add_action('woocommerce_admin_field_set_earning_point_rules', [$this, 'render_point_field']);

		$this->includes();
	}

	public function render_point_field( $value ) {
		$loyalty_roles = get_option('loyalty_levels_roles', array());

		if (empty($loyalty_roles)) {
			?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label><?php echo esc_html( $value['name'] ); ?></label>
				</th>
				<td colspan="2">
					<p class="description"><?php esc_html_e('You have to set at least one role for the loyalty level to set the rule.', 'loyalty-for-woocommerce'); ?></p>
				</td>
			</tr>
			<?php
			return;
		}

		$saved_earning_points = get_option('loyalty_points_earning_rules', array());

		if (empty($saved_earning_points)) {
			foreach ($loyalty_roles as $role) {
				$saved_earning_points[$role] = array('points' => '', 'amount' => '');
			}
		}

		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php echo esc_html( $value['name'] ); ?></label>
			</th>
			<td class="forminp">
				<div id="loyalty_points">
					<?php foreach ($loyalty_roles as $role): ?>
						<?php 
						$level = isset($saved_earning_points[$role]) ? $saved_earning_points[$role] : array('points' => '', 'amount' => '');
						echo wp_kses(
							$this->get_level_html($role, $level),
							array(
								'div' => array('class' => array()),
								'table' => array(),
								'tr' => array(),
								'th' => array(),
								'td' => array(),
								'label' => array(),
								'span' => array('class' => array()),
								'input' => array(
									'type' => array(),
									'name' => array(),
									'style' => array(),
									'value' => array(),
									'placeholder' => array(),
									'min' => array(),
								),
							)
						);
						?>
					<?php endforeach; ?>
				</div>
				<?php wp_nonce_field('save_earning_point_rules', 'earning_point_rules_nonce'); ?>
				<p class="description">
					<?php echo wp_kses_post( __( 'Set how many points per order will be earned based on the order value.<br><b>Note</b>: Points are started to calculate from the subtotal.', 'loyalty-for-woocommerce' ) ); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	private function get_level_html( $role, $level ) {
		ob_start();
		?>
		<div class="loyalty_point">
			<table>
				<tr>
					<th>
						<label><span class="loyalty_level_role"><?php echo esc_html( ucfirst($role) ); ?></span></label>
					</th>
					<td>
						<?php echo esc_html__( 'Assign', 'loyalty-for-woocommerce' ); ?> 
						<input type="number" name="loyalty_earning_points[<?php echo esc_attr($role); ?>]" style="width: 84px;" value="<?php echo esc_attr($level['points']); ?>" placeholder="<?php esc_attr_e('points', 'loyalty-for-woocommerce'); ?>" min="1" />
						<?php echo esc_html__( 'point(s) for each', 'loyalty-for-woocommerce' ); ?> 
						<input type="number" name="loyalty_earning_amount[<?php echo esc_attr($role); ?>]" style="width: 84px;" value="<?php echo esc_attr($level['amount']); ?>" placeholder="<?php esc_attr_e('amount', 'loyalty-for-woocommerce'); ?>" min="1" /> 
						<?php echo esc_html( get_woocommerce_currency_symbol() ); ?>
					</td>
				</tr>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}

	public function includes() {
		include_once plugin_dir_path(__FILE__) . '../actions/earn-points.php';
		include_once plugin_dir_path(__FILE__) . '../actions/deduct-points.php';
	}
}

new YOSWC_Loyalty_Settings_Earning_Point_Rules();

<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Settings_Level_Rules {
	public function __construct() {
		add_action('woocommerce_admin_field_set_level_rules', [$this, 'render_level_field']);
	}

	public function render_level_field($value) {
		$loyalty_roles = get_option('loyalty_levels_roles', array());
	
		if (empty($loyalty_roles)) {
			?>
			<tr valign="top">
				<th scope="row" class="titledesc">
					<label><?php echo esc_html($value['name']); ?></label>
				</th>
				<td colspan="2">
					<p class="description"><?php esc_html_e('You have to set at least one role for the loyalty level to set the rule. Go to "Add new / Remove" to add new roles, then set at least one role for loyalty levels above and "Save changes".', 'loyalty-for-woocommerce'); ?></p>
				</td>
			</tr>
			<?php
			return;
		}
	
		$saved_levels = get_option('loyalty_levels_rules', array());
	
		foreach ($loyalty_roles as $role) {
			if ($role === 'customer') {
				// Set default for "customer" to 0 if not already set
				$saved_levels[$role] = array('from' => '0');
			} elseif (empty($saved_levels[$role])) {
				$saved_levels[$role] = array('from' => '');
			}
		}
	
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label><?php echo esc_html($value['name']); ?></label>
			</th>
			<td class="forminp">
				<div id="loyalty_levels">
					<?php foreach ($loyalty_roles as $role): ?>
						<?php 
						$level = isset($saved_levels[$role]) ? $saved_levels[$role] : array('from' => '');
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
									'disabled' => array(),
								),
							)
						);
						?>
					<?php endforeach; ?>
				</div>
				<?php wp_nonce_field('save_loyalty_level_rules', 'loyalty_level_rules_nonce'); ?>
				<p class="description">
					<?php echo wp_kses_post(__('Set the target points to reach each loyalty level.<br><b>For example</b>: Silver 10, Gold 100, Platinum 1000.', 'loyalty-for-woocommerce')); ?>
				</p>
			</td>
		</tr>
		<?php
	}

	private function get_level_html($role, $level) {
		ob_start();
		?>
		<div class="loyalty_level">
			<table>
				<tr>
					<th>
						<label><span class="loyalty_level_role"><?php echo esc_html(ucfirst($role)); ?></span></label>
					</th>
					<td>
						<?php echo esc_html__('From', 'loyalty-for-woocommerce'); ?>
						<input type="number" 
							   name="loyalty_level_from[<?php echo esc_attr($role); ?>]" 
							   style="width: 84px;" 
							   value="<?php echo esc_attr($role === 'customer' ? '0' : $level['from']); ?>" 
							   placeholder="<?php esc_attr_e('From', 'loyalty-for-woocommerce'); ?>" 
							   min="0"
							   <?php echo $role === 'customer' ? 'disabled' : ''; ?> /> 
						<?php echo esc_html__('point(s).', 'loyalty-for-woocommerce'); ?>
					</td>
				</tr>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}
}

new YOSWC_Loyalty_Settings_Level_Rules();

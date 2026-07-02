<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Settings_Notification {

	public function display_notification_settings() {
		$notification_points_update = get_option('loyalty_notification_email', array());
		$notification_level_update = get_option('loyalty_notification_email', array());
		?>

		<h2><?php esc_html_e('Email notifications', 'loyalty-for-woocommerce'); ?></h2>

		<!-- Email Notification Settings -->
		<table class="form-table">
		<?php wp_nonce_field('save_loyalty_notification_settings', 'loyalty_notification_nonce'); ?>
			<tbody>
				<tr valign="top">
					<th scope="row">
						<label for="loyalty_notification_points_update_enable">
							<?php esc_html_e('Points update', 'loyalty-for-woocommerce'); ?>
						</label>
					</th>
					<td>
						<input type="checkbox" name="loyalty_notification_points_update_enable" id="loyalty_notification_points_update_enable" 
							value="1" <?php checked($notification_points_update['points_update'] ?? '' , true); ?> />
						<?php esc_html_e('Enable to send email to the customer when the points updated.', 'loyalty-for-woocommerce'); ?>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="loyalty_notification_level_update_enable">
							<?php esc_html_e('Level update', 'loyalty-for-woocommerce'); ?>
						</label>
					</th>
					<td>
						<input type="checkbox" name="loyalty_notification_level_update_enable" id="loyalty_notification_level_update_enable" 
							value="1" <?php checked($notification_level_update['level_update'] ?? '' , true); ?> />
						<?php esc_html_e('Enable to send email to the customer when their level updated.', 'loyalty-for-woocommerce'); ?>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	public function save_notification_settings() {
		if (!isset($_POST['loyalty_notification_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['loyalty_notification_nonce'])), 'save_loyalty_notification_settings')) {
			wp_die(esc_html__('Nonce verification failed. Please try again.', 'loyalty-for-woocommerce'));
		}
	
		$notification_points_update = array(
			'points_update' => isset($_POST['loyalty_notification_points_update_enable']) && sanitize_text_field(wp_unslash($_POST['loyalty_notification_points_update_enable'])) === '1',
			'level_update' => isset($_POST['loyalty_notification_level_update_enable']) && sanitize_text_field(wp_unslash($_POST['loyalty_notification_level_update_enable'])) === '1',
		);
	
		update_option('loyalty_notification_email', $notification_points_update);
	}	
}

new YOSWC_Loyalty_Settings_Notification();

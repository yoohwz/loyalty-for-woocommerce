<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_User_Profile_Points {
    
    public function __construct() {
        add_action('show_user_profile', [$this, 'display_user_points']);
        add_action('edit_user_profile', [$this, 'display_user_points']);
        add_action('wp_ajax_reward_user_points', [$this, 'reward_user_points']);
        add_action('wp_ajax_deduct_user_points', [$this, 'deduct_user_points']);
		add_action('wp_ajax_get_points_log', [$this, 'get_points_log']);
    }

	public function display_user_points($user) {
		$allowed_roles = get_option('loyalty_levels_roles', []);
		$allowed_roles = maybe_unserialize($allowed_roles);
	
		if (!is_array($allowed_roles)) {
			$allowed_roles = [];
		}
		$user_roles = $user->roles;
		$has_allowed_role = !empty(array_intersect($user_roles, $allowed_roles));
	
		// Display the "User Points" section only if the user has one of the allowed roles.
		if (!$has_allowed_role) {
			return;
		}

		$user_points = intval(get_user_meta($user->ID, 'user_points', true));
		$user_earning_points = intval(get_user_meta($user->ID, 'user_earning_points', true));
		
		$editing_user_id = absint( filter_input( INPUT_GET, 'user_id', FILTER_UNSAFE_RAW ) );
		if (is_admin() && $editing_user_id) {
			$user_id = $editing_user_id;
			$user = get_userdata($user_id);
	
			if ($user) {
				$script_version = '1.2';
					wp_enqueue_script('points-modal-script', plugin_dir_url(__FILE__) . '../../../js/user-profile-points-modal.js', ['jquery'], $script_version, true);
	
				wp_localize_script('points-modal-script', 'ajax_object', [
					'user_id' => $user->ID,
					'ajaxurl' => admin_url('admin-ajax.php'),
					'security' => wp_create_nonce('ajax_nonce'), 
					'add_points_text' => esc_js(__('Add points to the user', 'loyalty-for-woocommerce')),
					'remove_points_text' => esc_js(__('Remove points from the user', 'loyalty-for-woocommerce')),
					'empty_points_alert' => esc_js(__('The points field cannot be empty.', 'loyalty-for-woocommerce')),
					'error_prefix' => esc_js(__('Error:', 'loyalty-for-woocommerce')),
					'request_error' => esc_js(__('An error occurred while processing your request. Please try again.', 'loyalty-for-woocommerce')),
					'unexpected_error' => esc_js(__('An unexpected error occurred.', 'loyalty-for-woocommerce')),
					'points_log_error' => esc_js(__('An error occurred while fetching the points log. Please try again.', 'loyalty-for-woocommerce')),
				]);
			}
		}
		
		?>
		<h3><?php esc_html_e('User Points', 'loyalty-for-woocommerce'); ?></h3>
		<div class="user-points">
			<table class="form-table">
				<tr>
					<th><?php esc_html_e('Current points', 'loyalty-for-woocommerce'); ?></th>
					<td>
						<?php echo esc_html($user_points); ?>
					</td>
				</tr>
				<tr>
					<th><?php esc_html_e('Total earned', 'loyalty-for-woocommerce'); ?></th>
					<td>
						<?php echo esc_html($user_earning_points); ?>
					</td>
				</tr>
				<tr>
					<th></th>
					<td>
						<button id="reward-points" class="button reward-points"><span class="dashicons dashicons-plus-alt2"></span></button>
						<button id="deduct-points" class="button deduct-points"><span class="dashicons dashicons-minus"></span></button>
						<button id="points-log" class="button deduct-points"><?php esc_html_e('Points log', 'loyalty-for-woocommerce'); ?></span></button>
					</td>
				</tr>
			</table>
		</div>
		<?php $this->render_point_operations_upsell_card(); ?>

		<!-- Modal for Points Log -->
		<div id="points-log-modal" style="display:none;">
            <div class="log-modal-content">
                <span class="close-modal-log">&times;</span>
                <h2><?php esc_html_e('Points Log', 'loyalty-for-woocommerce'); ?></h2>
                <table id="points-log-table">
                    <thead class="points-log-header">
                        <tr>
                            <th><?php esc_html_e('Date', 'loyalty-for-woocommerce'); ?></th>
                            <th><?php esc_html_e('Amount', 'loyalty-for-woocommerce'); ?></th>
                            <th><?php esc_html_e('Order ID', 'loyalty-for-woocommerce'); ?></th>
                            <th><?php esc_html_e('Description', 'loyalty-for-woocommerce'); ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

		<!-- Modal for Points Input -->
		<div id="points-modal" style="display:none;">
			<div class="modal-content">
				<span class="close-modal">&times;</span>

				<h2 id="modal-heading"><?php esc_html_e('Enter Points and Description', 'loyalty-for-woocommerce'); ?></h2>
				
				<table>
					<tr>
						<th>
							<label for="points-amount"><?php esc_html_e('Points:', 'loyalty-for-woocommerce'); ?></label>
						</th>
						<td>
							<input type="number" id="points-amount" placeholder="1" required min="1">
						</td>
					</tr>
					<tr>
						<th>
							<label for="points-description"><?php esc_html_e('Description:', 'loyalty-for-woocommerce'); ?></label>
						</th>
						<td>
							<input type="text" id="points-description" placeholder="Enter description" required>
						</td>
					</tr>
				</table>
				<div class="point-modal-footer">
					<button id="submit-points" class="button button-primary"><?php esc_html_e('Submit', 'loyalty-for-woocommerce'); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	private function render_point_operations_upsell_card() {
		if ( (bool) apply_filters( 'yoswc_loyalty_is_premium', false ) ) {
			return;
		}
		?>
		<div class="yoswc-user-points-upsell">
			<h4><?php esc_html_e( 'Need bulk or automated point operations?', 'loyalty-for-woocommerce' ); ?></h4>
			<p><?php esc_html_e( 'Premium adds tools for larger point management workflows beyond one-off manual rewards and deductions.', 'loyalty-for-woocommerce' ); ?></p>
			<ul>
				<li><?php esc_html_e( 'Bulk reward campaigns', 'loyalty-for-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Scheduled point rewards', 'loyalty-for-woocommerce' ); ?></li>
				<li><?php esc_html_e( 'Advanced point history log', 'loyalty-for-woocommerce' ); ?></li>
			</ul>
			<p class="yoswc-user-points-upsell__actions">
				<a class="button" href="<?php echo esc_url( $this->get_premium_purchase_url() ); ?>" target="_blank" rel="noopener">
					<?php esc_html_e( 'View Premium features', 'loyalty-for-woocommerce' ); ?>
				</a>
			</p>
		</div>
		<?php
	}

	private function get_premium_purchase_url() {
		return apply_filters( 'yoswc_loyalty_premium_url', 'https://yoohw.com/product/woocommerce-loyalty-points-and-rewards/' );
	}

	public function reward_user_points() {
		check_ajax_referer('ajax_nonce', 'security');
		if (!current_user_can('manage_options')) {
			wp_send_json_error('Unauthorized user');
			return;
		}
	
		$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
		$earned_points = isset($_POST['points']) ? intval($_POST['points']) : 0;
		$description = isset($_POST['description']) ? sanitize_text_field(wp_unslash($_POST['description'])) : '';		
	
		$current_points = intval(get_user_meta($user_id, 'user_points', true));
		$earning_points = intval(get_user_meta($user_id, 'user_earning_points', true));
		$new_points = $current_points + $earned_points;
		$new_earning_points =  $earning_points + $earned_points;
	
		update_user_meta($user_id, 'user_points', $new_points);
		update_user_meta($user_id, 'user_earning_points', $new_earning_points);

		$loyalty_levels_rules = maybe_unserialize(get_option('loyalty_levels_rules', []));
		if (is_array($loyalty_levels_rules)) {
			$sorted_rules = [];
			foreach ($loyalty_levels_rules as $level => $rule) {
				$sorted_rules[$level] = (int) $rule['from'];
			}
			asort($sorted_rules);
	
			$updated_level = null;
			foreach ($sorted_rules as $level => $threshold) {
				if ($new_earning_points >= $threshold) {
					$updated_level = $level;
				}
			}
	
			$user = new WP_User($user_id);
			if ($updated_level && $user->roles[0] !== $updated_level) {
				$user->set_role($updated_level);
				$description .= sprintf(
					/* translators: %s: Loyalty level role. */
					__('Level updated: %s', 'loyalty-for-woocommerce'),
					$updated_level
				);

				do_action('yoswc_loyalty_level_update', $user_id, $updated_level, $new_earning_points);
			}
		}

		$order_id = null;

		do_action('yoswc_loyalty_points_reward', $user_id, $earned_points, $new_points, $order_id);
	
		$log_status = YOSWC_Loyalty_Database::insert_points_log(
			array(
				'user_id' => $user_id,
				'action' => 'admin_reward',
				'amount' => $earned_points,
				'description' => $description,
			)
		);
	
		if ($log_status === false) {
			wp_send_json_error(__('Could not log points action.', 'loyalty-for-woocommerce'));
		}
	
		wp_send_json_success(['message' => __('Points rewarded successfully!', 'loyalty-for-woocommerce')]);
	}
	
	public function deduct_user_points() {
		check_ajax_referer('ajax_nonce', 'security');
		if (!current_user_can('manage_options')) {
			wp_send_json_error(__('Unauthorized user', 'loyalty-for-woocommerce'));
			return;
		}
	
		$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
		$deducted_points = isset($_POST['points']) ? intval($_POST['points']) : 0;
		$description = isset($_POST['description']) ? sanitize_text_field(wp_unslash($_POST['description'])) : '';
		
		$current_points = intval(get_user_meta($user_id, 'user_points', true));
		$earning_points = intval(get_user_meta($user_id, 'user_earning_points', true));
		$new_points = max(0, $current_points - $deducted_points);
		$new_earning_points = max(0, $earning_points - $deducted_points);
	
		update_user_meta($user_id, 'user_points', $new_points);
		update_user_meta($user_id, 'user_earning_points', $new_earning_points);

		$loyalty_levels_rules = maybe_unserialize(get_option('loyalty_levels_rules', []));
		if (is_array($loyalty_levels_rules)) {
			$sorted_rules = [];
			foreach ($loyalty_levels_rules as $level => $rule) {
				$sorted_rules[$level] = (int) $rule['from'];
			}
			asort($sorted_rules);
	
			$updated_level = null;
			foreach ($sorted_rules as $level => $threshold) {
				if ($new_earning_points >= $threshold) {
					$updated_level = $level;
				}
			}
	
			$user = new WP_User($user_id);
			if ($updated_level && $user->roles[0] !== $updated_level) {
				$user->set_role($updated_level);
					$description .= sprintf(
						/* translators: %s: Loyalty level role. */
						__('Level updated: %s', 'loyalty-for-woocommerce'),
						$updated_level
					);

				do_action('yoswc_loyalty_level_update', $user_id, $updated_level, $new_earning_points);
			}
		}

		$order_id = null;

		do_action('yoswc_loyalty_points_deduct', $user_id, $deducted_points, $new_points, $order_id);
	
		$log_status = YOSWC_Loyalty_Database::insert_points_log(
			array(
				'user_id' => $user_id,
				'action' => 'admin_deduct',
				'amount' => $deducted_points,
				'description' => $description,
			)
		);
	
		if ($log_status === false) {
			wp_send_json_error(__('Could not log points action.', 'loyalty-for-woocommerce'));
		}
	
		wp_send_json_success(['message' => __('Points deducted successfully!', 'loyalty-for-woocommerce')]);
	}
	
	public function get_points_log() {
		check_ajax_referer('ajax_nonce', 'security');
	
		if (!current_user_can('manage_options')) {
			wp_send_json_error(['message' => __('Unauthorized user', 'loyalty-for-woocommerce')]);
			return;
		}
	
		$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
	
		if ($user_id === 0) {
			wp_send_json_error(['message' => __('Invalid user ID.', 'loyalty-for-woocommerce')]);
			return;
		}
	
		$results = YOSWC_Loyalty_Database::get_points_log( $user_id, null, null, OBJECT );
	
		if ($results) {
			$log_entries = [];
			$user_roles = get_option('wp_user_roles');
	
			foreach ($results as $entry) {
				$formatted_date = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($entry->date));
	
				$amount = (in_array($entry->action, ['admin_reward', 'order_reward', 'points_return', 'sign_up_reward', 'daily_login_reward', 'review_reward', 'level_up_reward']) ? '+' : '-') . abs($entry->amount);
	
				if ($entry->order_id == 0) {
					$order_id = '-';
				} else {
					$order_id = sprintf('<a href="%s" target="_blank">#%d</a>', esc_url(admin_url('post.php?post=' . intval($entry->order_id) . '&action=edit')), intval($entry->order_id));
				}
	
				$description_prefix = '';
				if ($entry->action === 'admin_reward') {
					$description_prefix = __('Admin rewarded', 'loyalty-for-woocommerce');
				} elseif ($entry->action === 'admin_deduct') {
					$description_prefix = __('Admin deducted', 'loyalty-for-woocommerce');
				}
	
				$formatted_description = $entry->description;
				if ($entry->action === 'admin_reward' || $entry->action === 'admin_deduct') {
					$formatted_description = !empty($entry->description) ? $description_prefix . ' - ' . esc_html($entry->description) : esc_html($description_prefix);
				}
	
				if (strpos($formatted_description, 'Level updated: ') !== false) {
					preg_match('/Level updated: ([^\s]+)/', $formatted_description, $matches);
					if (!empty($matches[1])) {
						$role_slug = $matches[1];
						if (isset($user_roles[$role_slug])) {
							$role_name = $user_roles[$role_slug]['name']; 
							$formatted_description = str_replace(
								$matches[0],
									sprintf(
										/* translators: %s: Loyalty level role. */
										__('Level updated: %s', 'loyalty-for-woocommerce'),
										esc_html($role_name)
									),
								$formatted_description
							);
							
						}
					}
				}
	
				$log_entries[] = [
					'date' => esc_html($formatted_date),
					'amount' => esc_html($amount),
					'order_id' => $order_id,
					'description' => esc_html($formatted_description)
				];
			}
	
			wp_send_json_success($log_entries);
		} else {
			wp_send_json_error(['message' => __('No points log found.', 'loyalty-for-woocommerce')]);
		}
	}		
}

new YOSWC_Loyalty_User_Profile_Points();

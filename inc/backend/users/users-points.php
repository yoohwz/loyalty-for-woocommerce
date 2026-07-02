<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Users_Points {

    public function __construct() {
        add_filter('manage_users_columns', [$this, 'add_custom_user_columns']);
		add_filter('manage_users_sortable_columns', [$this, 'sortable_columns']);
        add_action('pre_get_users', [$this, 'sort_user_points']);
        add_action('manage_users_custom_column', [$this, 'display_custom_user_columns'], 10, 3);
        add_action('admin_footer', [$this, 'add_modal_html']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
		add_action('wp_ajax_get_user_name', [$this, 'get_user_name']);
		add_action('wp_ajax_reward_user_points', [$this, 'reward_user_points']);
		add_action('wp_ajax_deduct_user_points', [$this, 'deduct_user_points']);
    }

    public function add_custom_user_columns($columns) {
        $columns['current_points'] = __('Points', 'loyalty-for-woocommerce');
        $columns['total_earned'] = __('Total', 'loyalty-for-woocommerce');
        $columns['actions'] = __('Actions', 'loyalty-for-woocommerce');
        return $columns;
    }

    public function sortable_columns($columns) {
        $columns['current_points'] = 'current_points';
        $columns['total_earned'] = 'total_earned';
        return $columns;
    }

    public function sort_user_points($query) {
        if (!is_admin() || !$query->is_main_query() || $query->get('orderby')) {
            return;
        }

        if ('current_points' === $query->get('orderby')) {
            $query->set('meta_key', 'user_points');
            $query->set('orderby', 'meta_value_num');
        }

        if ('total_earned' === $query->get('orderby')) {
            $query->set('meta_key', 'user_earning_points');
            $query->set('orderby', 'meta_value_num');
        }
    }

	public function display_custom_user_columns($value, $column_name, $user_id) {
		$allowed_roles = get_option('loyalty_levels_roles', []);
		$allowed_roles = maybe_unserialize($allowed_roles);
	
		if (!is_array($allowed_roles)) {
			$allowed_roles = [];
		}
	
		// Check if the user's role matches any of the allowed roles.
		$user = get_userdata($user_id);
		if (!$user || empty(array_intersect($user->roles, $allowed_roles))) {
			return $value;
		}

		switch ($column_name) {
			case 'current_points':
				$points = get_user_meta($user_id, 'user_points', true);
				return esc_html($points ? $points : '0');
			case 'total_earned':
				$earned = get_user_meta($user_id, 'user_earning_points', true);
				return esc_html($earned ? $earned : '0');
			case 'actions':
				return sprintf(
					'<button class="button reward-points" data-user-id="%s">%s</button>
					 <button class="button deduct-points" data-user-id="%s">%s</button>',
					esc_attr($user_id),
					'<span class="dashicons dashicons-plus-alt2"></span>',
					esc_attr($user_id),
					'<span class="dashicons dashicons-minus"></span>',
				);
			default:
				return $value;
		}
	}	

    public function add_modal_html() {
		$screen = get_current_screen();
		if (is_admin() && isset($screen) && $screen->id === 'users') {

			?>
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
					<?php $this->render_point_operations_upsell_card(); ?>
				</div>
			</div>
			<?php
		}
    }

	private function render_point_operations_upsell_card() {
		if ( (bool) apply_filters( 'yoswc_loyalty_is_premium', false ) ) {
			return;
		}
		?>
		<div class="yoswc-user-points-upsell yoswc-user-points-upsell--modal">
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

	public function enqueue_scripts() {
		$script_version = '1.1';
		$screen = get_current_screen();
		if (is_admin() && isset($screen) && $screen->id === 'users') {
			wp_enqueue_script('users-points-modal-script', plugin_dir_url(__FILE__) . '../../../js/users-points-modal.js', ['jquery'], $script_version, true);
	
			wp_localize_script('users-points-modal-script', 'ajax_object', [
				'user_id' => get_current_user_id(),
				'ajaxurl' => admin_url('admin-ajax.php'), 
				'security' => wp_create_nonce('ajax_nonce'),
				'add_points_text' => esc_js(__('Add points to the user', 'loyalty-for-woocommerce')),
				'remove_points_text' => esc_js(__('Remove points from the user', 'loyalty-for-woocommerce')), 
				'empty_points_alert' => esc_js(__('The points field cannot be empty.', 'loyalty-for-woocommerce')),
				'error_prefix' => esc_js(__('Error:', 'loyalty-for-woocommerce')),
				'request_error' => esc_js(__('An error occurred while processing your request. Please try again.', 'loyalty-for-woocommerce')),
				'username_error' => esc_js(__('Failed to get username.', 'loyalty-for-woocommerce')),
				'username_fetch_error' => esc_js(__('Error fetching username.', 'loyalty-for-woocommerce')),
			]);
		}
	}
	

	public function get_user_name() {
		check_ajax_referer('ajax_nonce', 'security');
	
		if (!current_user_can('manage_options')) {
			wp_send_json_error(__('Unauthorized user', 'loyalty-for-woocommerce'));
			return;
		}
	
		$user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
		$user_info = get_userdata($user_id); 
	
		if ($user_info) {
			wp_send_json_success(['username' => $user_info->display_name]);
		} else {
			wp_send_json_error(__('User not found', 'loyalty-for-woocommerce'));
		}
	}

	public function reward_user_points() {
		check_ajax_referer('ajax_nonce', 'security');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(__('Unauthorized user', 'loyalty-for-woocommerce'));
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
					$description .= ' - ' . sprintf(
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
				$description .= ' - ' . sprintf(
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
}

new YOSWC_Loyalty_Users_Points();

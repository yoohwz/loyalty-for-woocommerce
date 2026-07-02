<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_My_Account_My_Points {

	public $table_name;

	public function __construct() {
		$this->table_name = YOSWC_Loyalty_Database::get_table_name();

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_my_points_scripts' ] );
		add_filter( 'woocommerce_account_menu_items', [ $this, 'add_my_points_tab' ] );
		add_action( 'init', [ $this, 'add_my_points_endpoint' ] );
		add_action( 'wp_loaded', [ $this, 'add_my_points_content_hook' ] );
		add_action( 'update_option_loyalty_customization_my_account', [ $this, 'flush_rewrite_rules_on_option_update' ] );
		register_activation_hook( __FILE__, [ $this, 'flush_rewrite_rules' ] );
		register_deactivation_hook( __FILE__, [ $this, 'flush_rewrite_rules' ] );
		add_action( 'wp_ajax_load_more_points_log', [ $this, 'load_more_points_log' ] );
	}

	public function enqueue_my_points_scripts() {
		if ( ! is_account_page() || ! is_user_logged_in() ) {
			return;
		}

		$this->ensure_plugin_helpers_available();

		// Get the current slug from settings (must match what you added in add_my_points_tab)
		$settings = maybe_unserialize( get_option( 'loyalty_customization_my_account', [] ) );
		$slug     = ! empty( $settings['my_account_slug'] ) ? $settings['my_account_slug'] : 'my-points';

		if ( is_plugin_active( 'wc-advanced-accounts/wc-advanced-accounts.php' ) || is_plugin_active( 'wc-advanced-accounts-premium/wc-advanced-accounts-premium.php' ) ) {
			$slug = 'my-points';
		}

		if ( is_wc_endpoint_url( $slug ) ) {
			wp_enqueue_script(
				'my-points-script',
				plugin_dir_url( __FILE__ ) . '../../js/my-acount-my-points.js',
				array( 'jquery' ),
				'1.0',
				true
			);
		}
	}
		
	public function add_my_points_tab( $items ) {
		$allowed_roles = get_option('loyalty_levels_roles', []);
		$allowed_roles = maybe_unserialize($allowed_roles);
		if (!is_array($allowed_roles)) {
			$allowed_roles = [];
		}
	
		// Check if the current user's role matches any of the allowed roles.
		$current_user = wp_get_current_user();
		if (!$current_user || empty(array_intersect($current_user->roles, $allowed_roles))) {
			return $items;
		}

		$loyalty_settings = get_option( 'loyalty_customization_my_account' );
		$loyalty_settings = maybe_unserialize( $loyalty_settings );
	
		if ( is_array( $loyalty_settings ) && isset( $loyalty_settings['my_account'] ) && $loyalty_settings['my_account'] ) {
			$default_tab_label = __( 'My Points', 'loyalty-for-woocommerce' );
			$tab_label = isset( $loyalty_settings['my_account_label'] ) && is_string( $loyalty_settings['my_account_label'] ) ? trim( $loyalty_settings['my_account_label'] ) : '';
			if ( '' === $tab_label || in_array( $tab_label, array( 'My Points', 'My points' ), true ) ) {
				$tab_label = $default_tab_label;
			}
			$this->ensure_plugin_helpers_available();
			if ( is_plugin_active( 'wc-advanced-accounts/wc-advanced-accounts.php' ) || is_plugin_active( 'wc-advanced-accounts-premium/wc-advanced-accounts-premium.php' ) ) {
				$tab_slug = 'my-points';
			} else {
				$tab_slug = isset( $loyalty_settings['my_account_slug'] ) ? $loyalty_settings['my_account_slug'] : 'my-points';
			}
	
			$new_items = [];
			foreach ( $items as $key => $value ) {
				$new_items[ $key ] = $value;
				if ( 'orders' === $key ) {
					$new_items[ $tab_slug ] = esc_html( $tab_label );
				}
			}
	
			return $new_items;
		}
	
		return $items;
	}
	
	/**
	 * Register the custom endpoint for "My Points".
	 */
	public function add_my_points_endpoint() {
		$loyalty_settings = get_option( 'loyalty_customization_my_account' );
		$loyalty_settings = maybe_unserialize( $loyalty_settings );
	
		if ( is_array( $loyalty_settings ) && isset( $loyalty_settings['my_account'] ) && $loyalty_settings['my_account'] ) {
			$this->ensure_plugin_helpers_available();
			if ( is_plugin_active( 'wc-advanced-accounts/wc-advanced-accounts.php' ) || is_plugin_active( 'wc-advanced-accounts-premium/wc-advanced-accounts-premium.php' )) {
				$tab_slug = 'my-points';
			} else {
				$tab_slug = isset( $loyalty_settings['my_account_slug'] ) ? $loyalty_settings['my_account_slug'] : 'my-points';
			}
			add_rewrite_endpoint( $tab_slug, EP_ROOT | EP_PAGES );
		}
	}

	/**
	 * Dynamically add the action for displaying the content for "My Points".
	 */
	public function add_my_points_content_hook() {
		$this->ensure_plugin_helpers_available();

		$loyalty_settings = get_option( 'loyalty_customization_my_account' );
		$loyalty_settings = maybe_unserialize( $loyalty_settings );

		if ( is_array( $loyalty_settings ) && ! empty( $loyalty_settings['my_account'] ) ) {
			// if WC Advanced Accounts plugin is active, force 'my-points'
			if ( is_plugin_active( 'wc-advanced-accounts/wc-advanced-accounts.php' ) || is_plugin_active( 'wc-advanced-accounts-premium/wc-advanced-accounts-premium.php' ) ) {
				$tab_slug = 'my-points';
			} else {
				// otherwise use your saved slug or default
				$tab_slug = ! empty( $loyalty_settings['my_account_slug'] )
					? $loyalty_settings['my_account_slug']
					: 'my-points';
			}

			add_action(
				'woocommerce_account_' . $tab_slug . '_endpoint',
				[ $this, 'my_points_content' ]
			);
		}
	}

	private function ensure_plugin_helpers_available() {
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
	}
		
	/**
	 * Flush rewrite rules when the option is updated.
	 */
	public function flush_rewrite_rules_on_option_update() {
		$this->add_my_points_endpoint();
		flush_rewrite_rules();
	}
	
	/**
	 * Flush rewrite rules on activation and deactivation.
	 */
	public function flush_rewrite_rules() {
		flush_rewrite_rules();
	}
	

	/**
	 * Display the content for "My Points" tab.
	 */
	public function my_points_content() {
		$user_id = get_current_user_id();
		$user_role = $this->get_user_role();

		$first_name = get_user_meta($user_id, 'first_name', true);
		$heading = ! empty( $first_name ) 
			? sprintf(
				/* translators: %s: Customer first name. */
				__( "%s's Reward Summary", 'loyalty-for-woocommerce' ),
				esc_html( $first_name )
			)
			: __( 'My Reward Summary', 'loyalty-for-woocommerce' );
		$loyalty_levels = get_option( 'loyalty_customization_levels' );
			$loyalty_levels_rules = get_option( 'loyalty_levels_rules' );
	
		// Sort the levels based on the 'from' value
		uasort($loyalty_levels_rules, function($a, $b) {
			return $a['from'] - $b['from'];
		});
	
		$total_levels = count($loyalty_levels_rules);
		$level_index = 0;
		$points_for_next_level = 0;
		$next_level_name = '';
		$circle_progress_percent = 0;
		$found_current_level = false;

		foreach ($loyalty_levels_rules as $level => $data) {
			if ($found_current_level) {
				$points_for_next_level = $data['from'];
				$next_level_name = $level;
				break;
			}
			if ($user_role === $level) {
				$found_current_level = true;
			}
			$level_index++;
		}

		$circle_progress_percent = (($level_index) / $total_levels) * 100;

		wp_localize_script('my-points-script', 'ajax_object', array(
			'nonce'                => wp_create_nonce('load_more_points_nonce'),
			'ajaxurl'              => admin_url('admin-ajax.php'),
			'view_order_url'       => wc_get_endpoint_url('view-order', '', wc_get_page_permalink('myaccount')),
			'circle_progress_percent' => $circle_progress_percent,
			'loading_text'         => esc_html__('Loading...', 'loyalty-for-woocommerce'),
			'load_more_text'       => esc_html__('Load more', 'loyalty-for-woocommerce'),
			'no_more_points_text'  => esc_html__('No more points', 'loyalty-for-woocommerce'),
			'error_text'           => esc_html__('Error', 'loyalty-for-woocommerce'),
		));

		if (!$found_current_level) {
			$circle_progress_percent = 0;
		}

		if (!$points_for_next_level) {
			$last_level = end($loyalty_levels_rules);
			$points_for_next_level = $last_level['from'];
			$last_level_name = key($loyalty_levels_rules);
		}

		$wp_user_roles = get_option('wp_user_roles');
		$next_level_display_name = isset($wp_user_roles[$next_level_name]['name']) ? $wp_user_roles[$next_level_name]['name'] : ucfirst($next_level_name);
		$current_points = get_user_meta( $user_id, 'user_points', true );
		$current_points = ! empty( $current_points ) ? $current_points : 0;	
		$earned_points = get_user_meta( $user_id, 'user_earning_points', true );
		$earned_points = ! empty( $earned_points ) ? $earned_points : 0;
		$points_to_next_level = max(0, $points_for_next_level - $earned_points);
		$bar_progress_percent = ($earned_points / $points_for_next_level) * 100;
		$bar_progress_percent = min($bar_progress_percent, 100);

		$initial_limit = 10;

		$points_log = $this->get_user_points_log_paginated( $user_id, 0, $initial_limit );
	
		?>
	
		<div class="loyalty-points-card">
			<h3><?php echo esc_html( $heading ); ?></h3>
			<div class="loyalty-card-container">
	
				<!-- Combined Circular Progress Graph and Loyalty Level -->
				<div class="circle-container">
					<svg viewBox="0 0 36 36" class="circular-chart">
						<path class="circle-bg"
							d="M18 2.0845
							a 15.9155 15.9155 0 0 1 0 31.831
							a 15.9155 15.9155 0 0 1 0 -31.831"/>
						<path id="circle-progress" class="circle"
							stroke-dasharray="0, 100"
							d="M18 2.0845
							a 15.9155 15.9155 0 0 1 0 31.831
							a 15.9155 15.9155 0 0 1 0 -31.831"
							style="stroke-dasharray: <?php echo esc_attr( $circle_progress_percent ); ?>, 100;" />
					</svg>
					<div class="level-text">
						<?php
						if ( isset( $loyalty_levels[ $user_role ] ) ) {
							$icon = $loyalty_levels[ $user_role ]['icon'];
							$text_color = $loyalty_levels[ $user_role ]['text_color'];

							if ( ! empty( $icon ) ) {
								echo '<img src="' . esc_url( $icon ) . '" alt="' . esc_attr( ucfirst( $user_role ) ) . '" width="80" height="80">';
							} else {
								echo '<span style="color:' . esc_attr( $text_color ) . ';">' . esc_html( ucfirst( $user_role ) ) . '</span>';
							}
						} else {
							echo esc_html( ucfirst( $user_role ) );
						}
						?>
					</div>
				</div>
	
				<div class="loyalty-card-details">
					<div class="loyalty-card-points-details">
						<div class="loyalty-card-available-points">
							<?php esc_html_e( 'Current points', 'loyalty-for-woocommerce' ); ?>
							<span class="loyalty-card-points">
								<?php echo esc_html( $current_points ); ?>
							</span>
						</div>
						<div class="loyalty-card-earning-points">
							<?php esc_html_e( 'Earned points', 'loyalty-for-woocommerce' ); ?>
							<span class="loyalty-card-points">
								<?php echo esc_html( $earned_points ); ?>
							</span>
						</div>
					</div>
					<div class="loyalty-levels-bar">
						<?php if ( $points_to_next_level > 0) : ?>
							<?php esc_html_e( 'To next level', 'loyalty-for-woocommerce' ); ?> <strong><?php echo esc_html( $next_level_display_name ); ?></strong>* - <?php echo esc_html( $points_for_next_level ); ?> <?php esc_html_e( 'points', 'loyalty-for-woocommerce' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Congratulations! You reached the highest level.', 'loyalty-for-woocommerce' ); ?>
						<?php endif; ?>
						<div class="progress-bar">
							<div class="progress-bar-fill" style="width: <?php echo esc_attr( $bar_progress_percent ); ?>%;"></div>
						</div>
						<?php if ( $points_to_next_level > 0) : ?>
							<div class="loyalty-level-notes">
								*<?php 
								echo sprintf(
									/* translators: 1: Points left to reach next level, 2: Next level. */
									esc_html__( 'Earn %1$s points more to reach %2$s level.', 'loyalty-for-woocommerce' ), 
									esc_html( $points_to_next_level ), 
									esc_html( $next_level_display_name )
								); 
								?>
							</div>
						<?php else : ?>
							<div class="loyalty-level-notes">
								<?php esc_html_e( 'Thank you for your loyalty. Hope you enjoy the benefits!', 'loyalty-for-woocommerce' ); ?>
						</div>
						<?php endif; ?>
					</div>
				</div>
			</div>
		</div>

		<table id="my-points-table" class="my_account_orders">
			<thead class="my-points-header">
				<tr>
					<th><?php esc_html_e('Date', 'loyalty-for-woocommerce'); ?></th>
					<th><?php esc_html_e('Order', 'loyalty-for-woocommerce'); ?></th>
					<th><?php esc_html_e('Description', 'loyalty-for-woocommerce'); ?></th>
					<th><?php esc_html_e('Amount', 'loyalty-for-woocommerce'); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $points_log ) ) : ?>
					<?php foreach ( $points_log as $log ) : ?>
						<?php
						$formatted_description = $log['description'];
						if ( strpos( $formatted_description, 'Level updated: ' ) !== false ) {
							preg_match( '/Level updated: ([^\s]+)/', $formatted_description, $matches );
							if ( ! empty( $matches[1] ) ) {
								$role_slug = $matches[1];
								if ( isset( $wp_user_roles[ $role_slug ] ) ) {
									$role_name = $wp_user_roles[ $role_slug ]['name'];
									$formatted_description = str_replace( $matches[0], 'Level updated: ' . esc_html( $role_name ), $formatted_description );
								}
							}
						}
						
						$amount_color = ( in_array( $log['action'], ['admin_reward', 'order_reward', 'points_return', 'sign_up_reward', 'daily_login_reward', 'review_reward', 'level_up_reward'] ) ? '#00a32a' : '#d63638' );
						$formatted_amount = ( in_array( $log['action'], ['admin_reward', 'order_reward', 'points_return', 'sign_up_reward', 'daily_login_reward', 'review_reward', 'level_up_reward'] ) ? '+' : '-' ) . abs( $log['amount'] );
						?>
						<tr>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $log['date'] ) ) ); ?></td>
							<td>
								<?php if ( $log['order_id'] > 0 ) : ?>
									<a href="<?php echo esc_url( wc_get_endpoint_url( 'view-order', $log['order_id'], wc_get_page_permalink( 'myaccount' ) ) ); ?>">
										<?php echo esc_html( '#' . $log['order_id'] ); ?>
									</a>
								<?php else : ?>
									-
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $formatted_description ); ?></td>
							<td style="color: <?php echo esc_attr( $amount_color ); ?>;"><?php echo esc_html( $formatted_amount ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="4"><?php esc_html_e( 'No points log found.', 'loyalty-for-woocommerce' ); ?></td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>

		<!-- Load More Button -->
		<?php if ( count( $points_log ) === $initial_limit ) : ?>
			<div class="loyalty-points-table-button">
				<button id="load-more-points-log" data-offset="<?php echo esc_attr( $initial_limit ); ?>" class="button">
					<?php esc_html_e( 'Load more', 'loyalty-for-woocommerce' ); ?>
				</button>
			</div>
		<?php endif; ?>
		<?php
	}
	
	private function get_user_role() {
		$user = wp_get_current_user();
		return $user->roles ? $user->roles[0] : 'customer';
	}

	public function get_user_points_log( $user_id ) {
		return YOSWC_Loyalty_Database::get_points_log( $user_id );
	}
	
	public function load_more_points_log() {
		check_ajax_referer( 'load_more_points_nonce', 'security' );
	
		$user_id = get_current_user_id();
		$offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
		$limit = 10;
	
		$user_roles = get_option( 'wp_user_roles' );
		$points_log = $this->get_user_points_log_paginated( $user_id, $offset, $limit );
	
		if ( empty( $points_log ) ) {
			wp_send_json_error( esc_html__( 'No more points log found.', 'loyalty-for-woocommerce' ) );
			wp_die();
		}
	
		foreach ( $points_log as &$log ) {
			$log['date'] = date_i18n( get_option( 'date_format' ), strtotime( $log['date'] ) );
	
			$log['amount'] = ( in_array( $log['action'], ['admin_reward', 'order_reward', 'points_return'] ) ? '+' : '-' ) . abs( $log['amount'] );
	
			$formatted_description = $log['description'];
			if ( strpos( $formatted_description, 'Level updated: ' ) !== false ) {
				preg_match( '/Level updated: ([^\s]+)/', $formatted_description, $matches );
				if ( ! empty( $matches[1] ) ) {
					$role_slug = $matches[1];
					if ( isset( $user_roles[ $role_slug ] ) ) {
						$role_name = $user_roles[ $role_slug ]['name'];
						$formatted_description = str_replace( $matches[0], 'Level updated: ' . esc_html( $role_name ), $formatted_description );
					}
				}
			}
	
			$log['description'] = $formatted_description;
		}
	
		wp_send_json_success( $points_log );
		wp_die();
	}
	
	public function get_user_points_log_paginated( $user_id, $offset = 0, $limit = 10 ) {
		return YOSWC_Loyalty_Database::get_points_log( $user_id, $offset, $limit );
	}	
}

new YOSWC_Loyalty_My_Account_My_Points();

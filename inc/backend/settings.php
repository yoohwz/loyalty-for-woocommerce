<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Settings {

	private $role_manager;

	public function __construct() {
		require_once plugin_dir_path(__FILE__) . '/settings/general-add-remove-user-role.php';
		$this->role_manager = new YOSWC_Loyalty_Settings_Add_Remove_User_Role();

		add_filter('woocommerce_settings_tabs_array', array($this, 'add_loyalty_settings_tab'), 40);
		add_action('woocommerce_settings_tabs_loyalty', array($this, 'loyalty_settings_tab'), 10);
		add_action('woocommerce_update_options_loyalty', array($this, 'update_loyalty_settings'));
		add_action('woocommerce_admin_field_yoswc_premium_preview', [$this, 'render_premium_preview_card']);

		$this->includes();
	}

	public function add_loyalty_settings_tab($settings_tabs) {
		if (!current_user_can('manage_options')) {
			return;
		}
		
		$settings_tabs['loyalty'] = __('Loyalty', 'loyalty-for-woocommerce');
		return $settings_tabs;
	}

    public function loyalty_settings_tab() {
		$current_section = $this->get_query_value( 'section', 'general' );
		$current_subsection = $this->get_query_value( 'subsection' );

        echo '<ul class="subsubsub">';
        $this->output_sub_sub_tabs($current_section, $current_subsection);
		echo '</ul><br class="clear" />';

        if ($current_section === 'general' && $current_subsection === 'add_remove_role') {
            $this->role_manager->display_add_remove_role();
        } elseif ('extra_points' === $current_section) {
            $this->output_extra_points_settings();
		} elseif ('referrals' === $current_section) {
            $this->output_referrals_settings();
		} elseif ('customization' === $current_section) {
			$this->output_customization_settings();
		} elseif ('notification' === $current_section) {
			$this->output_notification_settings();
        } elseif ('tools' === $current_section) {
			$this->output_tools_settings();
        } elseif ('premium' === $current_section) {
			$this->output_premium_settings();
        } else {
            $this->output_loyalty_level_point_settings();
        }
    }

	public function output_sub_sub_tabs($current_section) {
		$sub_sub_tabs = array(
			'general' => __('General', 'loyalty-for-woocommerce'),
			'extra_points' => __('Extra points', 'loyalty-for-woocommerce'),
			'referrals'     => __('Referrals', 'loyalty-for-woocommerce'),
			'customization' => __('Customization', 'loyalty-for-woocommerce'),
			'notification' => __('Notification', 'loyalty-for-woocommerce'),
			'tools' => __('Tools', 'loyalty-for-woocommerce'),
			'premium' => __('Premium', 'loyalty-for-woocommerce')
		);

		$count = count($sub_sub_tabs);
		$i = 1;

		foreach ($sub_sub_tabs as $section_id => $section_label) {
			$class = ($current_section === $section_id) ? 'current' : '';
			echo '<li><a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=loyalty&section=' . $section_id ) ) . '" class="' . esc_attr( $class ) . '">' . esc_html( $section_label ) . '</a>';
			if ($i < $count) {
				echo ' | ';
			}
			echo '</li>';
			$i++;
		}		
	}

	public function output_loyalty_level_point_settings() {
		woocommerce_admin_fields($this->get_loyalty_levels_settings());
		woocommerce_admin_fields($this->get_loyalty_points_settings());
	}

	public function output_extra_points_settings() {
		$extra_points_settings = new YOSWC_Loyalty_Settings_Extra_Points();
		$extra_points_settings->display_extra_points_settings();
	}

	public function output_referrals_settings() {
		$referrals_settings = new YOSWC_Loyalty_Settings_Referrals();
		$referrals_settings->display_referrals_settings();
	}

	public function output_customization_settings() {
		$customization_settings = new YOSWC_Loyalty_Settings_Customization();
		$customization_settings->display_customization_settings();
	}	

	public function output_notification_settings() {
		$notification_settings = new YOSWC_Loyalty_Settings_Notification();
		$notification_settings->display_notification_settings();
	}

	public function output_tools_settings() {
		$tools_settings = new YOSWC_Loyalty_Settings_Tools();
		$tools_settings->display_tools_settings();
	}

	public function output_premium_settings() {
		$is_premium = $this->is_premium_active();
		$premium_url = apply_filters( 'yoswc_loyalty_premium_url', 'https://yoohw.com/product/woocommerce-loyalty-points-and-rewards/' );
		$docs_url = apply_filters( 'yoswc_loyalty_premium_docs_url', 'https://yoohw.com/docs/category/woocommerce-loyalty/' );

		$premium_features = array(
			__( 'Referral links and referral coupons', 'loyalty-for-woocommerce' ),
			__( 'Point expiration and advanced redemption rules', 'loyalty-for-woocommerce' ),
			__( 'Birthday, account anniversary, and profile completion rewards', 'loyalty-for-woocommerce' ),
			__( 'Purchase milestone and lifetime spend rewards', 'loyalty-for-woocommerce' ),
			__( 'Advanced earning rules by product, category, and payment method', 'loyalty-for-woocommerce' ),
			__( 'Level discounts and scheduled level resets', 'loyalty-for-woocommerce' ),
		);
		?>
		<h2><?php esc_html_e( 'Premium', 'loyalty-for-woocommerce' ); ?></h2>
		<div class="yoswc-premium-settings">
			<div class="yoswc-premium-settings__card">
				<h3><?php esc_html_e( 'Upgrade when your loyalty program needs more automation', 'loyalty-for-woocommerce' ); ?></h3>
				<p>
					<?php esc_html_e( 'The free plugin includes the core loyalty point workflow. Premium adds referral campaigns, advanced earning rules, and automated rewards for stores that need deeper retention tools.', 'loyalty-for-woocommerce' ); ?>
				</p>
				<ul>
					<?php foreach ( $premium_features as $premium_feature ) : ?>
						<li><?php echo esc_html( $premium_feature ); ?></li>
					<?php endforeach; ?>
				</ul>
				<p class="yoswc-premium-settings__actions">
					<?php if ( $is_premium ) : ?>
						<span class="yoswc-premium-settings__status"><?php esc_html_e( 'Premium add-on detected.', 'loyalty-for-woocommerce' ); ?></span>
					<?php else : ?>
						<a class="button button-primary" href="<?php echo esc_url( $premium_url ); ?>" target="_blank" rel="noopener">
							<?php esc_html_e( 'View Premium features', 'loyalty-for-woocommerce' ); ?>
						</a>
						<a class="button" href="<?php echo esc_url( $docs_url ); ?>" target="_blank" rel="noopener">
							<?php esc_html_e( 'Read documentation', 'loyalty-for-woocommerce' ); ?>
						</a>
					<?php endif; ?>
				</p>
			</div>
		</div>
		<style>
			.yoswc-premium-settings {
				display: grid;
				grid-template-columns: minmax(0, 1fr);
				gap: 16px;
				max-width: 960px;
			}
			.yoswc-premium-settings__card {
				background: #fff;
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				padding: 18px 20px;
			}
			.yoswc-premium-settings__card h3 {
				margin: 0 0 8px;
				font-size: 16px;
			}
			.yoswc-premium-settings__card p {
				margin-top: 0;
			}
			.yoswc-premium-settings__card ul {
				list-style: disc;
				margin: 12px 0 0 20px;
			}
			.yoswc-premium-settings__actions {
				display: flex;
				flex-wrap: wrap;
				gap: 8px;
				margin: 16px 0 0;
			}
			.yoswc-premium-settings__status {
				display: inline-block;
				font-weight: 600;
				color: #008a20;
			}
		</style>
		<?php
	}

	public function render_premium_preview_card( $value ) {
		if ( $this->is_premium_active() ) {
			return;
		}

		$title = isset( $value['title'] ) ? $value['title'] : __( 'Premium features', 'loyalty-for-woocommerce' );
		$description = isset( $value['desc'] ) ? $value['desc'] : '';
		$features = isset( $value['features'] ) && is_array( $value['features'] ) ? $value['features'] : array();
		?>
		<tr valign="top" class="yoswc-premium-preview-row">
			<th scope="row" class="titledesc">
				<span class="yoswc-premium-preview-row__label"><?php esc_html_e( 'Premium', 'loyalty-for-woocommerce' ); ?></span>
			</th>
			<td class="forminp">
				<div class="yoswc-premium-preview-card">
					<h3><?php echo esc_html( $title ); ?></h3>
					<?php if ( '' !== $description ) : ?>
						<p><?php echo esc_html( $description ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $features ) ) : ?>
						<ul>
							<?php foreach ( $features as $feature ) : ?>
								<li><?php echo esc_html( $feature ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
					<p class="yoswc-premium-preview-card__actions">
						<a class="button" href="<?php echo esc_url( $this->get_premium_purchase_url() ); ?>" target="_blank" rel="noopener">
							<?php esc_html_e( 'View Premium features', 'loyalty-for-woocommerce' ); ?>
						</a>
					</p>
				</div>
			</td>
		</tr>
		<?php
	}

	private function is_premium_active() {
		return (bool) apply_filters( 'yoswc_loyalty_is_premium', false );
	}

	private function get_premium_purchase_url() {
		return apply_filters( 'yoswc_loyalty_premium_url', 'https://yoohw.com/product/woocommerce-loyalty-points-and-rewards/' );
	}

	public function get_loyalty_levels_settings() {
		$nonce = wp_create_nonce('loyalty_levels_nonce');
		$settings = array(
			array(
				'name'     => __( 'Loyalty levels', 'loyalty-for-woocommerce' ),
				'type'     => 'title',
				'desc'     => '<a href="' . esc_url(admin_url('admin.php?page=wc-settings&tab=loyalty&section=general&subsection=add_remove_role&_wpnonce=' . $nonce)) . '" class="button button-secondary">' . __('Add new / Remove', 'loyalty-for-woocommerce') . '</a>',
				'id'       => 'loyalty_levels_section_title'
			),
			'loyalty_levels' => array(
				'name'     => __('Set loyalty levels', 'loyalty-for-woocommerce'),
				'type'     => 'multiselect',
				'desc_tip' => __('Select user roles to set those are loyalty levels', 'loyalty-for-woocommerce'),
				'id'       => 'loyalty_levels_roles',
				'options'  => $this->wc_loyalty_get_user_roles(),
				'custom_attributes' => array(
					'data-placeholder' => __('Select user roles', 'loyalty-for-woocommerce')
				),
				'class'    => 'wc-enhanced-select',
				'css'      => 'min-width:300px;',
			),
			array(
				'name' => __( 'Level rules', 'loyalty-for-woocommerce' ),
				'id'   => 'loyalty_levels_rules',
				'type' => 'set_level_rules',
			),
			array(
				'type'     => 'yoswc_premium_preview',
				'title'    => __( 'Advanced loyalty level controls', 'loyalty-for-woocommerce' ),
				'desc'     => __( 'Premium adds scheduled level resets and automatic discounts based on loyalty levels.', 'loyalty-for-woocommerce' ),
				'features' => array(
					__( 'Reset levels monthly, quarterly, or yearly', 'loyalty-for-woocommerce' ),
					__( 'Apply percentage discounts by loyalty level', 'loyalty-for-woocommerce' ),
					__( 'Exclude coupons or taxes from level discounts', 'loyalty-for-woocommerce' ),
				),
			),
			'section_end' => array(
				'type' => 'sectionend',
				'id'   => 'loyalty_levels_section_end'
			),
		);
			wp_nonce_field('loyalty_levels_nonce_action', 'loyalty_levels_nonce');
		
		return $settings;
	}

	public function get_loyalty_points_settings() {
		$settings = array(
			array(
				'name'        => __( 'Points', 'loyalty-for-woocommerce' ),
				'type'     => 'title',
				'desc'     => '',
				'id'       => 'loyalty_points_section_title'
			),
			'loyalty_earning_status' => array(
				'name'     => __('Earning status', 'loyalty-for-woocommerce'),
				'type'     => 'multiselect',
				'desc_tip' => __('Select the order status so that the customer can be earned their points.', 'loyalty-for-woocommerce'),
				'id'       => 'loyalty_points_earning_status',
				'options'  => $this->wc_loyalty_get_earning_order_status(),
				'custom_attributes' => array(
					'data-placeholder' => __('Select order status', 'loyalty-for-woocommerce')
				),
				'class'    => 'wc-enhanced-select',
				'css'      => 'min-width:300px;',
			),
			array(
				'name' => __( 'Earning rules', 'loyalty-for-woocommerce' ),
				'id'   => 'loyalty_points_earning_rules',
				'type' => 'set_earning_point_rules',
			),
			array(
				'type'     => 'yoswc_premium_preview',
				'title'    => __( 'Advanced earning and display controls', 'loyalty-for-woocommerce' ),
				'desc'     => __( 'Premium adds more control over how points are named, displayed, and earned.', 'loyalty-for-woocommerce' ),
				'features' => array(
					__( 'Customize the point label shown to customers', 'loyalty-for-woocommerce' ),
					__( 'Create product and category-specific earning rules', 'loyalty-for-woocommerce' ),
					__( 'Reward selected payment methods and high-value orders', 'loyalty-for-woocommerce' ),
				),
			),
			array(
				'title'    => __('Earning options', 'loyalty-for-woocommerce'),
				'desc_tip' => __('Check the option that you want to calculate include/exclude in order value for the earning point.', 'loyalty-for-woocommerce'),
				'id'       => 'loyalty_points_earning_option',
				'default'  => array('tax'),
				'type'     => 'yol_multi_checkbox',
				'options'  => array(
					'coupons'   => __('Price after using coupons', 'loyalty-for-woocommerce'),
					'taxes'       => __('Excluding taxes', 'loyalty-for-woocommerce'),
				),
			),
			array(
				'name' => __('Point rounding', 'loyalty-for-woocommerce'),
				'id' => 'loyalty_points_rounding',
				'type' => 'select',
				'options' => array(
					'round_up' => __('Round up', 'loyalty-for-woocommerce'),
					'round_down' => __('Round down', 'loyalty-for-woocommerce'),
				),
				'default' => 'round_down',
				'desc_tip' => true,
				'description' => __('Choose how to round the earning points amount.', 'loyalty-for-woocommerce'),
			),
			'loyalty_deduction_status' => array(
				'name'     => __('Deduction status', 'loyalty-for-woocommerce'),
				'type'     => 'multiselect',
				'desc_tip' => __('Select the order status so that the customer can be deducted their points.', 'loyalty-for-woocommerce'),
				'id'       => 'loyalty_points_deduction_status',
				'options'  => $this->wc_loyalty_get_deduction_order_status(),
				'custom_attributes' => array(
					'data-placeholder' => __('Select order status', 'loyalty-for-woocommerce')
				),
				'class'    => 'wc-enhanced-select',
				'css'      => 'min-width:300px;',
			),
			array(
				'type'     => 'yoswc_premium_preview',
				'title'    => __( 'Point expiration controls', 'loyalty-for-woocommerce' ),
				'desc'     => __( 'Premium helps keep balances fresh with configurable point expiration rules.', 'loyalty-for-woocommerce' ),
				'features' => array(
					__( 'Expire points after a fixed lifetime', 'loyalty-for-woocommerce' ),
					__( 'Expire points after customer inactivity', 'loyalty-for-woocommerce' ),
					__( 'Set expiration duration in days, weeks, months, or years', 'loyalty-for-woocommerce' ),
				),
			),
			'loyalty_using_point' => array(
				'name'     => __('Using points', 'loyalty-for-woocommerce'),
				'type'     => 'checkbox',
				'desc'     => __('Allow users to using points', 'loyalty-for-woocommerce'),
				'id'       => 'loyalty_points_using_point',
				'default'  => 'no',
			),
			array(
				'name' => __( 'Using rules', 'loyalty-for-woocommerce' ),
				'id'   => 'loyalty_points_using_rules',
				'type' => 'set_using_point_rules',
			),
			array(
				'type'     => 'yoswc_premium_preview',
				'title'    => __( 'Advanced redemption controls', 'loyalty-for-woocommerce' ),
				'desc'     => __( 'Premium adds more ways to control how customers redeem points.', 'loyalty-for-woocommerce' ),
				'features' => array(
					__( 'Set minimum and maximum points per redemption', 'loyalty-for-woocommerce' ),
					__( 'Require a minimum cart value before points can be used', 'loyalty-for-woocommerce' ),
					__( 'Redeem points for selected products, coupon codes, or free shipping', 'loyalty-for-woocommerce' ),
					__( 'Control whether point discounts combine with coupons', 'loyalty-for-woocommerce' ),
					__( 'Set expiration rules for redeemed rewards', 'loyalty-for-woocommerce' ),
				),
			),
			'section_end' => array(
				'type' => 'sectionend',
				'id'   => 'loyalty_points_section_end'
			),
		);
		return $settings;
	}

	public function update_loyalty_settings() {
		$current_section = $this->get_query_value( 'section', 'general' );
		$current_subsection = $this->get_query_value( 'subsection' );
	
		if ($current_section === 'general' && $current_subsection !== 'add_remove_role') {

			if (!isset($_POST['loyalty_levels_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['loyalty_levels_nonce'])), 'loyalty_levels_nonce_action')) {
				return;
			}
			
			woocommerce_update_options($this->get_loyalty_levels_settings());
			woocommerce_update_options($this->get_loyalty_points_settings());
	
			$selected_roles = isset($_POST['loyalty_levels_roles']) ? array_map('sanitize_text_field', wp_unslash((array) $_POST['loyalty_levels_roles'])) : array();
	
			if (!in_array('customer', $selected_roles, true)) {
				array_unshift($selected_roles, 'customer');
			} else {
				$index = array_search('customer', $selected_roles, true);
				if ($index !== 0) {
					unset($selected_roles[$index]);
					array_unshift($selected_roles, 'customer');
				}
			}
	
			update_option('loyalty_levels_roles', $selected_roles);
	
			if (isset($_POST['loyalty_points_earning_option'])) {
				$loyalty_points_earning_option = array_map('sanitize_text_field', wp_unslash((array) $_POST['loyalty_points_earning_option']));
				update_option('loyalty_points_earning_option', $loyalty_points_earning_option);
			} else {
				update_option('loyalty_points_earning_option', array());
			}
	
			$this->save_level_rules();
			$this->save_earning_point_rules();
			$this->save_using_point_rules();
		}
	
		if ($current_section === 'extra_points') {
			$extra_points_settings = new YOSWC_Loyalty_Settings_Extra_Points();
			$extra_points_settings->save_extra_points_settings();
		}
	
		if ($current_section === 'customization') {
			$customization_settings = new YOSWC_Loyalty_Settings_Customization();
			$customization_settings->save_customization_settings();
		}
	
		if ($current_section === 'notification') {
			$notification_settings = new YOSWC_Loyalty_Settings_Notification();
			$notification_settings->save_notification_settings();
		}

	}
	
	public function save_level_rules() {
		if (!isset($_POST['loyalty_level_rules_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['loyalty_level_rules_nonce'])), 'save_loyalty_level_rules')) {
			return;
		}
	
		$levels_from = isset($_POST['loyalty_level_from']) ? array_map('sanitize_text_field', wp_unslash((array) $_POST['loyalty_level_from'])) : array();
	
		$loyalty_levels_roles = get_option('loyalty_levels_roles', array());
		$loyalty_levels_rules = array();
	
		$loyalty_levels_rules['customer'] = array('from' => '0');
	
		foreach ($levels_from as $role => $from_value) {
			if (in_array($role, $loyalty_levels_roles, true)) {
				$loyalty_levels_rules[$role] = array(
					'from' => $from_value,
				);
			}
		}
	
		update_option('loyalty_levels_rules', $loyalty_levels_rules);
	}
		

	public function save_earning_point_rules() {
		if (!isset($_POST['earning_point_rules_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['earning_point_rules_nonce'])), 'save_earning_point_rules')) {
			return;
		}

		$earning_points = isset($_POST['loyalty_earning_points']) ? array_map('sanitize_text_field', wp_unslash((array) $_POST['loyalty_earning_points'])) : array();
		$earning_amounts = isset($_POST['loyalty_earning_amount']) ? array_map('sanitize_text_field', wp_unslash((array) $_POST['loyalty_earning_amount'])) : array();		

		$loyalty_points_earning_rules = array();

		foreach ($earning_points as $role => $points) {
			$amount = isset($earning_amounts[$role]) ? $earning_amounts[$role] : '';
			$loyalty_points_earning_rules[$role] = array(
				'points' => $points,
				'amount' => $amount,
			);
		}

		update_option('loyalty_points_earning_rules', $loyalty_points_earning_rules);
	}
	
	public function save_using_point_rules() {
		if (!isset($_POST['using_point_rules_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['using_point_rules_nonce'])), 'save_using_point_rules')) {
			return;
		}

		$points = isset($_POST['loyalty_using_points']) ? floatval(wp_unslash($_POST['loyalty_using_points'])) : 0;
		$amount = isset($_POST['loyalty_using_amount']) ? floatval(wp_unslash($_POST['loyalty_using_amount'])) : 0;		

		$loyalty_points_using_rules = array(
			'points' => $points,
			'amount' => $amount,
		);

		update_option('loyalty_points_using_rules', $loyalty_points_using_rules);
	}

	public function wc_loyalty_get_user_roles() {
		$roles = wp_roles()->roles;
		$editable_roles = function_exists( 'get_editable_roles' ) ? get_editable_roles() : $roles;
		$role_options = [];
		$exclude_roles = ['administrator', 'editor', 'author', 'contributor', 'subscriber', 'shop_manager', 'customer', 'translator'];
	
		foreach ($editable_roles as $role_id => $role_info) {
			if (!in_array($role_id, $exclude_roles)) {
				$role_options[$role_id] = $role_info['name'];
			}
		}
		return $role_options;
	}

	public function wc_loyalty_get_earning_order_status() {
		$all_statuses = wc_get_order_statuses();
		$exclude_statuses = [
			'wc-pending',
			'wc-cancelled',
			'wc-refunded', 
			'wc-failed',
			'wc-checkout-draft' 
		];
	
		$filtered_statuses = array_diff_key($all_statuses, array_flip($exclude_statuses));
	
		return $filtered_statuses;
	}

	public function wc_loyalty_get_deduction_order_status() {
		$all_statuses = wc_get_order_statuses();
		$exclude_statuses = [
			'wc-completed', 
			'wc-on-hold', 
			'wc-processing',
			'wc-checkout-draft' 
		];
	
		$filtered_statuses = array_diff_key($all_statuses, array_flip($exclude_statuses));
	
		return $filtered_statuses;
	}

	private function get_query_value( $key, $default = '' ) {
		$value = filter_input( INPUT_GET, $key, FILTER_UNSAFE_RAW );

		if ( null === $value || false === $value ) {
			return $default;
		}

		return sanitize_key( $value );
	}

	public function includes() {
		include_once plugin_dir_path(__FILE__) . '/settings/general-level-rules.php';
		include_once plugin_dir_path(__FILE__) . '/settings/general-earning-point-rules.php';
		include_once plugin_dir_path(__FILE__) . '/settings/general-using-point-rules.php';
		include_once plugin_dir_path(__FILE__) . '/settings/general-multi-checkbox.php';
		include_once plugin_dir_path(__FILE__) . '/settings/extra-points.php';
		include_once plugin_dir_path(__FILE__) . '/settings/referrals.php';
		include_once plugin_dir_path(__FILE__) . '/settings/customization.php';
		include_once plugin_dir_path(__FILE__) . '/settings/notification.php';
		include_once plugin_dir_path(__FILE__) . '/settings/tools.php';

		include_once plugin_dir_path(__FILE__) . '/actions/extra-points/sign-up.php';
		include_once plugin_dir_path(__FILE__) . '/actions/extra-points/log-in.php';
		include_once plugin_dir_path(__FILE__) . '/actions/extra-points/review.php';
		include_once plugin_dir_path(__FILE__) . '/actions/extra-points/level-up.php';
	}
}

new YOSWC_Loyalty_Settings();

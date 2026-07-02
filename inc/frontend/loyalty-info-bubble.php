<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Info_Bubble {

	public function __construct() {
		add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
		add_action('wp_footer', array($this, 'render_bubble'), 30);
	}

	public function enqueue_scripts() {
		if (!$this->should_render()) {
			return;
		}

		wp_enqueue_script(
			'yoswc-loyalty-info-bubble',
			plugin_dir_url(__FILE__) . '../../js/loyalty-info-bubble.js',
			array(),
			defined('YOSWC_LOYALTY_VERSION') ? YOSWC_LOYALTY_VERSION : '1.0',
			true
		);
	}

	public function render_bubble() {
		if (!$this->should_render()) {
			return;
		}

		$point_label = $this->get_point_label();
		$sections = array_filter($this->get_sections($point_label));

		if (empty($sections)) {
			return;
		}

		$icon_url = get_option('loyalty_customization_message_icon');
		if (empty($icon_url)) {
			$icon_url = plugin_dir_url(__FILE__) . '../../img/reward.svg';
		}

		$bubble_settings = $this->get_loyalty_bubble_settings();
		$position_class = 'bottom_left' === $bubble_settings['position']
			? 'yoswc-loyalty-info--bottom-left'
			: 'yoswc-loyalty-info--bottom-right';
		?>
		<div class="yoswc-loyalty-info <?php echo esc_attr($position_class); ?>" data-yoswc-loyalty-info style="<?php echo esc_attr($this->get_bubble_style_attribute()); ?>">
			<button
				type="button"
				class="yoswc-loyalty-info__bubble"
				aria-expanded="false"
				aria-controls="yoswc-loyalty-info-panel"
				aria-label="<?php esc_attr_e('Open loyalty program information', 'loyalty-for-woocommerce'); ?>"
			>
				<span class="yoswc-loyalty-info__bubble-icon" aria-hidden="true">
					<img src="<?php echo esc_url($icon_url); ?>" alt="" width="22" height="22">
				</span>
				<span class="yoswc-loyalty-info__bubble-text"><?php esc_html_e('Loyalty', 'loyalty-for-woocommerce'); ?></span>
			</button>

			<div
				id="yoswc-loyalty-info-panel"
				class="yoswc-loyalty-info__panel"
				role="dialog"
				aria-modal="false"
				aria-labelledby="yoswc-loyalty-info-title"
				hidden
			>
				<div class="yoswc-loyalty-info__header">
					<div>
						<p class="yoswc-loyalty-info__eyebrow"><?php esc_html_e('Rewards program', 'loyalty-for-woocommerce'); ?></p>
						<h3 id="yoswc-loyalty-info-title"><?php esc_html_e('Loyalty information', 'loyalty-for-woocommerce'); ?></h3>
						<p class="yoswc-loyalty-info__intro"><?php esc_html_e('Earn points, move up levels, and use rewards as you shop.', 'loyalty-for-woocommerce'); ?></p>
					</div>
					<button type="button" class="yoswc-loyalty-info__close" aria-label="<?php esc_attr_e('Close loyalty information', 'loyalty-for-woocommerce'); ?>">
						<span aria-hidden="true">&times;</span>
					</button>
				</div>

				<div class="yoswc-loyalty-info__body">
					<?php $section_index = 0; ?>
					<?php foreach ($sections as $section) : ?>
						<?php
						if (empty($section['title']) || empty($section['items']) || !is_array($section['items'])) {
							continue;
						}

						$section_id = 'yoswc-loyalty-info-section-' . $section_index;
						$is_open = 0 === $section_index;
						?>
						<section class="yoswc-loyalty-info__section <?php echo $is_open ? 'is-open' : ''; ?>">
							<button
								type="button"
								class="yoswc-loyalty-info__section-toggle"
								aria-expanded="<?php echo $is_open ? 'true' : 'false'; ?>"
								aria-controls="<?php echo esc_attr($section_id); ?>"
							>
								<span class="yoswc-loyalty-info__section-main">
									<span class="yoswc-loyalty-info__section-icon" aria-hidden="true">
										<?php echo esc_html(str_pad((string) ($section_index + 1), 2, '0', STR_PAD_LEFT)); ?>
									</span>
									<span>
										<span class="yoswc-loyalty-info__section-title"><?php echo esc_html($section['title']); ?></span>
										<span class="yoswc-loyalty-info__section-subtitle">
											<?php
											printf(
												/* translators: %d: Number of visible reward details. */
												esc_html__('%d details', 'loyalty-for-woocommerce'),
												(int) $this->get_section_item_count($section['items'])
											);
											?>
										</span>
									</span>
								</span>
								<span class="yoswc-loyalty-info__section-control" aria-hidden="true">
									<span class="yoswc-loyalty-info__switch"><span></span></span>
									<span class="yoswc-loyalty-info__chevron"></span>
								</span>
							</button>

							<div
								id="<?php echo esc_attr($section_id); ?>"
								class="yoswc-loyalty-info__section-panel"
								aria-hidden="<?php echo $is_open ? 'false' : 'true'; ?>"
							>
								<div class="yoswc-loyalty-info__section-grid">
									<?php foreach ($section['items'] as $item) : ?>
										<?php
										if (empty($item['label']) || !array_key_exists('value', $item)) {
											continue;
										}
										?>
										<article class="yoswc-loyalty-info__row">
											<div class="yoswc-loyalty-info__row-label">
												<span class="yoswc-loyalty-info__dot" aria-hidden="true"></span>
												<span><?php echo esc_html($item['label']); ?></span>
											</div>
											<div class="yoswc-loyalty-info__row-value"><?php $this->render_value($item['value']); ?></div>
										</article>
									<?php endforeach; ?>
								</div>
							</div>
						</section>
						<?php $section_index++; ?>
					<?php endforeach; ?>
				</div>
			</div>

				<?php if (!empty($bubble_settings['powered_by'])) : ?>
					<p class="yoswc-loyalty-info__powered">
						<?php esc_html_e('Powered by', 'loyalty-for-woocommerce'); ?> <a href="https://yoohw.com/product/woocommerce-loyalty-points-and-rewards/" target="_blank" rel="noopener noreferrer">YoOhw</a>.
					</p>
				<?php endif; ?>
			</div>
			<?php
		}

	private function should_render() {
		if (is_admin() || wp_doing_ajax()) {
			return false;
		}

		if (function_exists('wp_is_json_request') && wp_is_json_request()) {
			return false;
		}

		$bubble_settings = $this->get_loyalty_bubble_settings();
		return !empty($bubble_settings['enabled']);
	}

	private function get_sections($point_label) {
		return array(
			$this->get_account_section($point_label),
			$this->get_levels_section($point_label),
			$this->get_earning_section($point_label),
			$this->get_using_points_section($point_label),
		);
	}

	private function get_account_section($point_label) {
		if (!is_user_logged_in()) {
			return array(
				'title' => __('Your rewards', 'loyalty-for-woocommerce'),
				'items' => array(
					$this->row(
						__('Not a member yet?', 'loyalty-for-woocommerce'),
						array(
							'type' => 'cta',
							'text' => __('Join now', 'loyalty-for-woocommerce'),
							'url' => $this->get_guest_join_url(),
							'description' => __('Create an account to start earning points and tracking your reward level.', 'loyalty-for-woocommerce'),
						)
					),
				),
			);
		}

		$user_id = get_current_user_id();
		$current_points = (int) get_user_meta($user_id, 'user_points', true);
		$earned_points = (int) get_user_meta($user_id, 'user_earning_points', true);
		$user_role = $this->get_current_loyalty_role($user_id);

		return array(
			'title' => __('Your rewards', 'loyalty-for-woocommerce'),
			'items' => array(
				$this->row(__('Your level', 'loyalty-for-woocommerce'), $this->get_role_label($user_role)),
				$this->row(__('Ready to use', 'loyalty-for-woocommerce'), $this->format_points($current_points, $point_label)),
				$this->row(__('Level progress', 'loyalty-for-woocommerce'), $this->format_points($earned_points, $point_label)),
				$this->row(__('Next reward level', 'loyalty-for-woocommerce'), $this->get_next_level_message($user_role, $earned_points, $point_label)),
			),
		);
	}

	private function get_levels_section($point_label) {
		$rules = $this->get_level_rules();
		$items = array();

		foreach ($rules as $role => $rule) {
			$from = isset($rule['from']) ? (int) $rule['from'] : 0;
			$items[] = $this->row(
				$this->get_role_label($role),
				$from > 0
					? sprintf(
						/* translators: %s: Points amount. */
						__('Unlock at %s earned.', 'loyalty-for-woocommerce'),
						$this->format_points($from, $point_label)
					)
					: __('Starting reward level.', 'loyalty-for-woocommerce')
			);
		}

		if (empty($items)) {
			$items[] = $this->row(__('Customer', 'loyalty-for-woocommerce'), __('Starting reward level.', 'loyalty-for-woocommerce'));
		}

		return array(
			'title' => __('Reward levels', 'loyalty-for-woocommerce'),
			'items' => $items,
		);
	}

	private function get_earning_section($point_label) {
		$items = array();
		$earning_rules = maybe_unserialize(get_option('loyalty_points_earning_rules', array()));
		$earning_lines = $this->format_earning_rules($earning_rules, $point_label);

		if (!empty($earning_lines)) {
			$items[] = $this->row(__('Purchases', 'loyalty-for-woocommerce'), $earning_lines);
		}

		$earning_options = maybe_unserialize(get_option('loyalty_points_earning_option', array()));
		$option_lines = array();
		if (is_array($earning_options) && in_array('coupons', $earning_options, true)) {
			$option_lines[] = __('Points are calculated after coupons.', 'loyalty-for-woocommerce');
		}
		if (is_array($earning_options) && in_array('taxes', $earning_options, true)) {
			$option_lines[] = __('Taxes are excluded from point calculations.', 'loyalty-for-woocommerce');
		}
		$rounding = get_option('loyalty_points_rounding', 'round_down');
		$option_lines[] = 'round_up' === $rounding
			? __('Point totals are rounded up.', 'loyalty-for-woocommerce')
			: __('Point totals are rounded down.', 'loyalty-for-woocommerce');

		$items[] = $this->row(__('Calculation', 'loyalty-for-woocommerce'), $option_lines);

		$extra_points = maybe_unserialize(get_option('loyalty_extra_points_rules', array()));
		$levelup_points = maybe_unserialize(get_option('loyalty_extra_levelup_points_rules', array()));
		$bonus_lines = array();

		$this->append_bonus($bonus_lines, $extra_points, 'signup_points', __('Create an account', 'loyalty-for-woocommerce'), $point_label);
		$this->append_bonus($bonus_lines, $extra_points, 'login_points', __('Daily login', 'loyalty-for-woocommerce'), $point_label);
		$this->append_bonus($bonus_lines, $extra_points, 'review_points', __('Product review', 'loyalty-for-woocommerce'), $point_label);

		if (is_array($levelup_points)) {
			foreach ($levelup_points as $role => $rule) {
				$points = isset($rule['awarded']) ? (int) $rule['awarded'] : 0;
				if ($points <= 0) {
					continue;
				}

				$bonus_lines[] = sprintf(
					/* translators: 1: Loyalty level, 2: Points amount. */
					__('Reach %1$s: %2$s', 'loyalty-for-woocommerce'),
					$this->get_role_label($role),
					$this->format_points($points, $point_label)
				);
			}
		}

		if (!empty($bonus_lines)) {
			$items[] = $this->row(__('Bonus rewards', 'loyalty-for-woocommerce'), $bonus_lines);
		}

		return array(
			'title' => __('Earn points', 'loyalty-for-woocommerce'),
			'items' => $items,
		);
	}

	private function get_using_points_section($point_label) {
		if ('yes' !== get_option('loyalty_points_using_point', 'no')) {
			return array();
		}

		$items = array();
		$using_rules = maybe_unserialize(get_option('loyalty_points_using_rules', array()));
		$points = isset($using_rules['points']) ? (float) $using_rules['points'] : 0;
		$amount = isset($using_rules['amount']) ? (float) $using_rules['amount'] : 0;

		if ($points > 0 && $amount > 0) {
			$items[] = $this->row(
				__('Point value', 'loyalty-for-woocommerce'),
				sprintf(
					/* translators: 1: Points amount, 2: Money amount. */
					__('%1$s can be used for %2$s off.', 'loyalty-for-woocommerce'),
					$this->format_points($points, $point_label),
					$this->format_money($amount)
				)
			);
		}

		$cart_checkout = maybe_unserialize(get_option('loyalty_customization_cart_checkout', array()));
		$places = array();
		if (!empty($cart_checkout['cart'])) {
			$places[] = __('Cart', 'loyalty-for-woocommerce');
		}
		if (!empty($cart_checkout['checkout'])) {
			$places[] = __('Checkout', 'loyalty-for-woocommerce');
		}

		if (!empty($places)) {
			$items[] = $this->row(
				__('Where to use', 'loyalty-for-woocommerce'),
				sprintf(
					/* translators: %s: Place list. */
					__('Customers can use points on: %s.', 'loyalty-for-woocommerce'),
					implode(', ', $places)
				)
			);
		}

		if (empty($items)) {
			$items[] = $this->row(__('Use points', 'loyalty-for-woocommerce'), __('Use available points for cart discounts when configured.', 'loyalty-for-woocommerce'));
		}

		return array(
			'title' => __('Use points', 'loyalty-for-woocommerce'),
			'items' => $items,
		);
	}

	private function append_bonus(&$lines, $options, $key, $label, $point_label) {
		$points = is_array($options) && isset($options[$key]) ? (int) $options[$key] : 0;
		if ($points <= 0) {
			return;
		}

		$lines[] = sprintf(
			/* translators: 1: Bonus label, 2: Points amount. */
			__('%1$s: %2$s', 'loyalty-for-woocommerce'),
			$label,
			$this->format_points($points, $point_label)
		);
	}

	private function format_earning_rules($rules, $point_label) {
		if (empty($rules) || !is_array($rules)) {
			return array(__('Earn points on eligible purchases.', 'loyalty-for-woocommerce'));
		}

		$formatted = array();
		foreach ($this->get_loyalty_roles() as $role) {
			$points = isset($rules[$role]['points']) ? (int) $rules[$role]['points'] : 0;
			$amount = isset($rules[$role]['amount']) ? (float) $rules[$role]['amount'] : 0;

			if ($points <= 0 || $amount <= 0) {
				continue;
			}

			$formatted[] = sprintf(
				/* translators: 1: Loyalty level, 2: Points amount, 3: Money amount. */
				__('%1$s members earn %2$s for every %3$s spent.', 'loyalty-for-woocommerce'),
				$this->get_role_label($role),
				$this->format_points($points, $point_label),
				$this->format_money($amount)
			);
		}

		return !empty($formatted) ? $formatted : array(__('Earn points on eligible purchases.', 'loyalty-for-woocommerce'));
	}

	private function get_next_level_message($current_role, $earned_points, $point_label) {
		$rules = $this->get_level_rules();
		$found_current = false;

		foreach ($rules as $role => $rule) {
			$from = isset($rule['from']) ? (int) $rule['from'] : 0;

			if ($found_current && $from > $earned_points) {
				return sprintf(
					/* translators: 1: Points amount, 2: Loyalty level. */
					__('%1$s to reach %2$s.', 'loyalty-for-woocommerce'),
					$this->format_points($from - $earned_points, $point_label),
					$this->get_role_label($role)
				);
			}

			if ($role === $current_role) {
				$found_current = true;
			}
		}

		return __('Highest level reached.', 'loyalty-for-woocommerce');
	}

	private function get_level_rules() {
		$rules = maybe_unserialize(get_option('loyalty_levels_rules', array()));
		if (!is_array($rules)) {
			$rules = array();
		}

		if (empty($rules)) {
			$rules['customer'] = array('from' => 0);
		}

		uasort($rules, function ($a, $b) {
			return (int) ($a['from'] ?? 0) - (int) ($b['from'] ?? 0);
		});

		return $rules;
	}

	private function get_current_loyalty_role($user_id) {
		$user = get_userdata($user_id);
		$roles = $user && !empty($user->roles) ? (array) $user->roles : array('customer');
		$loyalty_roles = $this->get_loyalty_roles();

		foreach ($roles as $role) {
			$role = sanitize_key($role);
			if (in_array($role, $loyalty_roles, true)) {
				return $role;
			}
		}

		return 'customer';
	}

	private function get_loyalty_roles() {
		$roles = maybe_unserialize(get_option('loyalty_levels_roles', array()));
		$roles = is_array($roles) ? array_map('sanitize_key', $roles) : array();

		if (!in_array('customer', $roles, true)) {
			array_unshift($roles, 'customer');
		}

		return array_values(array_unique($roles));
	}

	private function get_role_label($role) {
		$role = sanitize_key($role);
		$wp_roles = wp_roles();
		if ($wp_roles && isset($wp_roles->roles[$role]['name'])) {
			return translate_user_role($wp_roles->roles[$role]['name']);
		}

		return ucwords(str_replace(array('-', '_'), ' ', $role));
	}

	private function get_point_label() {
		$default_label = __( 'Points', 'loyalty-for-woocommerce' );
		$label = get_option( 'loyalty_point_label', $default_label );
		$label = is_string( $label ) ? trim( $label ) : '';

		if ( '' === $label || 'Points' === $label ) {
			return $default_label;
		}

		return $label;
	}

	private function get_bubble_style_attribute() {
		$user_role = is_user_logged_in() ? $this->get_current_loyalty_role(get_current_user_id()) : 'customer';
		$levels = maybe_unserialize(get_option('loyalty_customization_levels', array()));
		$membercard = maybe_unserialize(get_option('loyalty_customization_membercard', array()));
		$message_style = $this->get_context_message_style();

		$accent = is_array($levels) && !empty($levels[$user_role]['text_color']) ? sanitize_hex_color($levels[$user_role]['text_color']) : '';
		if (empty($accent) || $this->is_near_white($accent)) {
			$accent = '#79a70a';
		}

		$panel_bg = is_array($membercard) && !empty($membercard['membercard_background_color']) ? sanitize_hex_color($membercard['membercard_background_color']) : '#f8fafc';
		$text_color = is_array($membercard) && !empty($membercard['membercard_text_color']) ? sanitize_hex_color($membercard['membercard_text_color']) : '#1f2937';
		$border_color = is_array($membercard) && !empty($membercard['membercard_border_color']) ? sanitize_hex_color($membercard['membercard_border_color']) : '#d9dee5';
		$card_bg = '#ffffff';
		$card_text = $text_color;
		$card_border = $border_color;
		$border_style = 'solid';
		$line_size = '1px';
		$radius = '16px';

		if (!empty($message_style)) {
			$prefix = $message_style['prefix'];
			$settings = $message_style['settings'];
			$card_text = !empty($settings[$prefix . '_text_color']) ? sanitize_hex_color($settings[$prefix . '_text_color']) : $card_text;
			$card_bg = !empty($settings[$prefix . '_background_color']) ? sanitize_hex_color($settings[$prefix . '_background_color']) : $card_bg;
			$card_border = !empty($settings[$prefix . '_border_color']) ? sanitize_hex_color($settings[$prefix . '_border_color']) : $card_border;
			$border_style = !empty($settings[$prefix . '_border_style']) ? sanitize_key($settings[$prefix . '_border_style']) : $border_style;
			$line_size = !empty($settings[$prefix . '_border_width']) ? $this->sanitize_css_size($settings[$prefix . '_border_width'], $line_size) : $line_size;
			$radius = !empty($settings[$prefix . '_border_radius']) ? $this->sanitize_css_size($settings[$prefix . '_border_radius'], $radius) : $radius;
		}

		$allowed_styles = array('none', 'solid', 'dashed', 'dotted', 'double', 'groove', 'ridge', 'inset', 'outset');
		if (!in_array($border_style, $allowed_styles, true)) {
			$border_style = 'solid';
		}

		$vars = array(
			'--yoswc-info-accent' => $accent,
			'--yoswc-info-accent-contrast' => $this->get_contrast_color($accent),
			'--yoswc-info-panel-bg' => $panel_bg ?: '#f8fafc',
			'--yoswc-info-text' => $text_color ?: '#1f2937',
			'--yoswc-info-border' => $border_color ?: '#d9dee5',
			'--yoswc-info-card-bg' => $card_bg ?: '#ffffff',
			'--yoswc-info-card-text' => $card_text ?: '#1f2937',
			'--yoswc-info-card-border' => $card_border ?: '#d9dee5',
			'--yoswc-info-line-style' => $border_style,
			'--yoswc-info-line-size' => $line_size,
			'--yoswc-info-radius' => $radius,
		);

		$style = '';
		foreach ($vars as $name => $value) {
			$style .= $name . ':' . $value . ';';
		}

		return $style;
	}

	private function get_context_message_style() {
		if (function_exists('is_product') && is_product()) {
			$product_page = maybe_unserialize(get_option('loyalty_customization_product_page', array()));
			if (is_array($product_page) && !empty($product_page['product_page'])) {
				return array('prefix' => 'product_page', 'settings' => $product_page);
			}
		}

		if ((function_exists('is_shop') && is_shop()) || (function_exists('is_product_taxonomy') && is_product_taxonomy())) {
			$shop_page = maybe_unserialize(get_option('loyalty_customization_shop_page', array()));
			if (is_array($shop_page) && !empty($shop_page['shop_page'])) {
				return array('prefix' => 'shop_page', 'settings' => $shop_page);
			}
		}

		return array();
	}

	private function sanitize_css_size($value, $default) {
		$value = is_string($value) ? trim($value) : '';
		if ('' === $value) {
			return $default;
		}

		return preg_match('/^\d+(\.\d+)?(px|em|rem|%)?$/', $value) ? $value : $default;
	}

	private function is_near_white($hex) {
		$hex = ltrim((string) $hex, '#');
		if (3 === strlen($hex)) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if (6 !== strlen($hex)) {
			return false;
		}

		return hexdec(substr($hex, 0, 2)) > 238 && hexdec(substr($hex, 2, 2)) > 238 && hexdec(substr($hex, 4, 2)) > 238;
	}

	private function get_contrast_color($hex) {
		$hex = ltrim((string) $hex, '#');
		if (3 === strlen($hex)) {
			$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
		}

		if (6 !== strlen($hex)) {
			return '#ffffff';
		}

		$r = hexdec(substr($hex, 0, 2));
		$g = hexdec(substr($hex, 2, 2));
		$b = hexdec(substr($hex, 4, 2));
		$brightness = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;

		return $brightness > 150 ? '#111827' : '#ffffff';
	}

	private function get_loyalty_bubble_settings() {
		$settings = maybe_unserialize(get_option('loyalty_customization_loyalty_bubble', array()));
		$settings = is_array($settings) ? $settings : array();
		$position = isset($settings['position']) ? sanitize_key($settings['position']) : 'bottom_right';

		if (!in_array($position, array('bottom_right', 'bottom_left'), true)) {
			$position = 'bottom_right';
		}

			return array(
				'enabled' => !array_key_exists('enabled', $settings) || !empty($settings['enabled']),
				'position' => $position,
				'powered_by' => !empty($settings['powered_by']),
			);
		}

	private function get_section_item_count($items) {
		$count = 0;

		foreach ((array) $items as $item) {
			if (!array_key_exists('value', (array) $item)) {
				continue;
			}

			if (is_array($item['value']) && !isset($item['value']['type'])) {
				$count += max(1, count($item['value']));
			} else {
				$count++;
			}
		}

		return $count;
	}

	private function row($label, $value) {
		return array(
			'label' => $label,
			'value' => $value,
		);
	}

	private function render_value($value) {
		if (is_array($value) && isset($value['type']) && 'cta' === $value['type']) {
			$url = !empty($value['url']) ? esc_url($value['url']) : '';
			$text = !empty($value['text']) ? (string) $value['text'] : __('Join now', 'loyalty-for-woocommerce');
			$description = !empty($value['description']) ? (string) $value['description'] : '';

			echo '<div class="yoswc-loyalty-info__cta">';
			if ('' !== $description) {
				echo '<p>' . esc_html($description) . '</p>';
			}
			if ('' !== $url) {
				echo '<a class="yoswc-loyalty-info__cta-button" href="' . esc_url($url) . '">' . esc_html($text) . '</a>';
			}
			echo '</div>';
			return;
		}

		if (is_array($value)) {
			if (empty($value)) {
				echo esc_html__('Not configured', 'loyalty-for-woocommerce');
				return;
			}

			echo '<div class="yoswc-loyalty-info__chips">';
			foreach ($value as $line) {
				echo '<span class="yoswc-loyalty-info__chip"><span aria-hidden="true"></span>' . esc_html((string) $line) . '</span>';
			}
			echo '</div>';
			return;
		}

		echo '<span class="yoswc-loyalty-info__text-value">' . esc_html((string) $value) . '</span>';
	}

	private function get_guest_join_url() {
		if (function_exists('wc_get_page_permalink')) {
			$url = wc_get_page_permalink('myaccount');
			if ($url) {
				return $url;
			}
		}

		$registration_url = wp_registration_url();
		return $registration_url ? $registration_url : wp_login_url();
	}

	private function format_points($points, $point_label) {
		return sprintf(
			/* translators: 1: Points number, 2: Point label. */
			__('%1$s %2$s', 'loyalty-for-woocommerce'),
			wc_format_decimal((float) $points, 0),
			$point_label
		);
	}

	private function format_money($amount) {
		if (function_exists('wc_price')) {
			return wp_strip_all_tags(wc_price((float) $amount));
		}

		return wc_format_decimal((float) $amount);
	}
}

new YOSWC_Loyalty_Info_Bubble();

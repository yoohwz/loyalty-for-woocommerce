<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Settings_Customization {

	public function __construct() {
		add_action('admin_init', [__CLASS__, 'set_default_message_settings']);
		add_action('admin_enqueue_scripts', [$this, 'enqueue_media_uploader']);
	}

	public function enqueue_media_uploader($hook) {
		$tab = (string) filter_input( INPUT_GET, 'tab', FILTER_UNSAFE_RAW );
		$section = (string) filter_input( INPUT_GET, 'section', FILTER_UNSAFE_RAW );

		if (
			$hook === 'woocommerce_page_wc-settings' &&
			'loyalty' === sanitize_key( $tab ) &&
			'customization' === sanitize_key( $section )
		) {
			wp_enqueue_media();
	
			wp_enqueue_script(
				'loyalty-customization-script',
				plugins_url('../../../js/settings-customzation.js', __FILE__),
				array('jquery'),
				'1.1.1',
				true
			);

			wp_localize_script(
				'loyalty-customization-script',
				'loyaltyCustomizationMessages',
				array(
					'mediaTitle'      => __( 'Select or Upload an Image', 'loyalty-for-woocommerce' ),
					'mediaButtonText' => __( 'Use this image', 'loyalty-for-woocommerce' ),
					'mediaError'      => __( 'The WordPress media library is not available.', 'loyalty-for-woocommerce' ),
				)
			);
		}
	}

		public static function set_default_message_settings() {
			$default_loyalty_bubble = array(
				'enabled' => true,
				'position' => 'bottom_right',
				'powered_by' => false,
			);

		if (!get_option('loyalty_customization_loyalty_bubble')) {
			update_option('loyalty_customization_loyalty_bubble', $default_loyalty_bubble);
		}

		$default_shop_page = array(
			'shop_page' => false,
			'shop_page_guest' => false,
			'shop_page_border_style' => 'solid',
			'shop_page_border_width' => '1px',
			'shop_page_border_radius' => '5px',
			'shop_page_text_color' => '#000000',
			'shop_page_background_color' => '#ffffff',
			'shop_page_border_color' => '#000000',
		);

		if (!get_option('loyalty_customization_shop_page')) {
			update_option('loyalty_customization_shop_page', $default_shop_page);
		}

		$default_product_page = array(
			'product_page' => false,
			'product_page_guest' => false,
			'product_page_position' => 'before_add_to_cart',
			'product_page_border_style' => 'solid',
			'product_page_border_width' => '1px',
			'product_page_border_radius' => '5px',
			'product_page_text_color' => '#000000',
			'product_page_background_color' => '#ffffff',
			'product_page_border_color' => '#000000',
		);

		if (!get_option('loyalty_customization_product_page')) {
			update_option('loyalty_customization_product_page', $default_product_page);
		}

		$default_my_account = array(
			'my_account' => false,
			'my_account_label' => '',
			'my_account_slug' => 'my-points',
		);

		if (!get_option('loyalty_customization_my_account')) {
			update_option('loyalty_customization_my_account', $default_my_account);
		}

		$default_cart_checkout = array(
			'cart' => false,
			'checkout' => false,
		);

		if (!get_option('loyalty_customization_cart_checkout')) {
			update_option('loyalty_customization_cart_checkout', $default_cart_checkout);
		}
	}

	public function display_customization_settings() {
		$loyalty_roles = get_option('loyalty_levels_roles', array());

		if (empty($loyalty_roles)) {
			?>
			<h2><?php esc_html_e('Customization Settings', 'loyalty-for-woocommerce'); ?></h2>
			<table class="form-table">
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label><?php esc_html_e('Before start', 'loyalty-for-woocommerce'); ?></label>
					</th>
					<td colspan="2">
						<p class="description"><?php esc_html_e('You have to set at least one role for the loyalty level to set this options.', 'loyalty-for-woocommerce'); ?></p>
					</td>
				</tr>
			</table>
			<?php
			return;
		}

		$customization = get_option('loyalty_customization_levels', array());
		$membercard = get_option('loyalty_customization_membercard', array());
			$loyalty_bubble = get_option('loyalty_customization_loyalty_bubble', array(
				'enabled' => true,
				'position' => 'bottom_right',
				'powered_by' => false,
			));
			if (!is_array($loyalty_bubble)) {
				$loyalty_bubble = array(
					'enabled' => true,
					'position' => 'bottom_right',
					'powered_by' => false,
				);
			}
		$message_shop_page = get_option('loyalty_customization_shop_page', array());
		$message_product_page = get_option('loyalty_customization_product_page', array());
		$message_icon = get_option( 'loyalty_customization_message_icon', '' );
		$my_account_display = get_option('loyalty_customization_my_account', array());
		$cart_checkout_display = get_option('loyalty_customization_cart_checkout', array());

		$default_my_account_label = __( 'My Points', 'loyalty-for-woocommerce' );
		$my_account_display = is_array( $my_account_display ) ? $my_account_display : array();
		$my_account_display = wp_parse_args(
			$my_account_display,
			array(
				'my_account' => false,
				'my_account_label' => '',
				'my_account_slug' => 'my-points',
			)
		);
		$my_account_label = is_string( $my_account_display['my_account_label'] ) ? trim( $my_account_display['my_account_label'] ) : '';
		if ( '' === $my_account_label || in_array( $my_account_label, array( 'My Points', 'My points' ), true ) ) {
			$my_account_display['my_account_label'] = $default_my_account_label;
		}
		
		?>

		<h2><?php esc_html_e('Levels & Membercard', 'loyalty-for-woocommerce'); ?></h2>

		<?php wp_nonce_field('save_loyalty_customization', 'loyalty_customization_nonce'); ?>
		<!-- Icons and Text Color -->
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row">
						<label for="loyalty_customizations"><?php esc_html_e('Level icons', 'loyalty-for-woocommerce'); ?></label>
					</th>
					<td>
						<div class="loyalty_customization">
							<table>
								<?php foreach ($loyalty_roles as $role): ?>
									<tr valign="top">
										<th scope="row">
											<label for="loyalty_level_icon_<?php echo esc_attr($role); ?>">
												<?php 
												/* translators: This displays the user role name with the first letter capitalized. */
												echo esc_html( ucfirst($role) ); 
												?>
											</label>
										</th>
										<td>
											<!-- Input field for the image URL -->
											<input type="text" name="loyalty_level_icon_<?php echo esc_attr($role); ?>" id="loyalty_level_icon_<?php echo esc_attr($role); ?>" 
												style="width: 300px;" value="<?php echo esc_url($customization[$role]['icon'] ?? ''); ?>" placeholder="<?php esc_attr_e('Image URL', 'loyalty-for-woocommerce'); ?>" />

											<!-- Button for uploading the image -->
											<button type="button" class="upload_image_button button" data-role="<?php echo esc_attr($role); ?>">
												<?php esc_html_e('Upload Image', 'loyalty-for-woocommerce'); ?>
											</button>
										</td>
									</tr>
								<?php endforeach; ?>
							</table>
						</div>
						<p class="description"><?php esc_html_e('Add an icon badge for each level. If this is not set, then the level name will display instead.', 'loyalty-for-woocommerce'); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="loyalty_customizations"><?php esc_html_e('Text colors', 'loyalty-for-woocommerce'); ?></label>
					</th>
					<td>
						<div class="loyalty_customization">
							<table>
								<?php foreach ($loyalty_roles as $role): ?>
									<tr valign="top">
										<th scope="row">
											<label for="loyalty_level_text_color_<?php echo esc_attr($role); ?>">
												<?php 
												/* translators: This displays the user role name with the first letter capitalized. */
												echo esc_html( ucfirst($role) ); 
												?>
											</label>
										</th>
										<td>
											<!-- Color picker input field -->
											<input type="color" name="loyalty_level_text_color_<?php echo esc_attr($role); ?>" id="loyalty_level_text_color_<?php echo esc_attr($role); ?>"
												value="<?php echo esc_attr($customization[$role]['text_color'] ?? '#ffffff'); ?>" style="width: 30.2px; height: 30.2px;" />

											<!-- Hex color input field -->
											<input type="text" name="loyalty_level_text_color_hex_<?php echo esc_attr($role); ?>" id="loyalty_level_text_color_hex_<?php echo esc_attr($role); ?>"
												style="width: 80px;" value="<?php echo esc_attr($customization[$role]['text_color'] ?? '' ); ?>" 
												placeholder="#ffffff" maxlength="7" />
										</td>
									</tr>
								<?php endforeach; ?>
							</table>
							<p class="description"><?php esc_html_e('Set colors for level name, process bar, and circle level progress.', 'loyalty-for-woocommerce'); ?></p>
						</div>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="loyalty_customizations"><?php esc_html_e('Membercard', 'loyalty-for-woocommerce'); ?></label>
					</th>
					<td class="loyalty_customization_membercard">
						<!-- Text Color -->
						<?php esc_html_e('Text:', 'loyalty-for-woocommerce'); ?> 
						<input type="color" name="loyalty_membercard_text_color" id="loyalty_membercard_text_color"
							value="<?php echo esc_attr($membercard['membercard_text_color'] ?? '#000000'); ?>" style="width: 30.2px; height: 30.2px;" />
						<input type="text" name="loyalty_membercard_text_color_hex" id="loyalty_membercard_text_color_hex"
							style="width: 80px; margin-right: 15px;" value="<?php echo esc_attr($membercard['membercard_text_color'] ?? '' ); ?>" 
							placeholder="#000000" maxlength="7" />

						<!-- Background Color -->
						<?php esc_html_e('Background:', 'loyalty-for-woocommerce'); ?> 
						<input type="color" name="loyalty_membercard_background_color" id="loyalty_membercard_background_color"
							value="<?php echo esc_attr($membercard['membercard_background_color'] ?? '#f9f9f9'); ?>" style="width: 30.2px; height: 30.2px;" />
						<input type="text" name="loyalty_membercard_background_color_hex" id="loyalty_membercard_background_color_hex"
							style="width: 80px; margin-right: 15px;" value="<?php echo esc_attr($membercard['membercard_background_color'] ?? '' ); ?>" 
							placeholder="#f9f9f9" maxlength="7" />

						<!-- Border Color -->
						<?php esc_html_e('Border:', 'loyalty-for-woocommerce'); ?> 
						<input type="color" name="loyalty_membercard_border_color" id="loyalty_membercard_border_color"
							value="<?php echo esc_attr($membercard['membercard_border_color'] ?? '#cccccc'); ?>" style="width: 30.2px; height: 30.2px;" />
						<input type="text" name="loyalty_membercard_border_color_hex" id="loyalty_membercard_border_color_hex"
							style="width: 80px;" value="<?php echo esc_attr($membercard['membercard_border_color'] ?? '' ); ?>" 
							placeholder="#cccccc" maxlength="7" />
					</td>
				</tr>
				<tr valign="top">
					<th scope="row">
						<label for="loyalty_bubble_enable"><?php esc_html_e('Loyalty bubble', 'loyalty-for-woocommerce'); ?></label>
					</th>
					<td>
						<input type="checkbox" name="loyalty_bubble_enable" id="loyalty_bubble_enable"
							value="1" <?php checked(!array_key_exists('enabled', $loyalty_bubble) || !empty($loyalty_bubble['enabled']), true); ?> />
						<?php esc_html_e('Enable the floating Loyalty bubble on the frontend.', 'loyalty-for-woocommerce'); ?>
					</td>
				</tr>
				<tr valign="top" id="loyalty_bubble_position_row" style="<?php echo (!array_key_exists('enabled', $loyalty_bubble) || !empty($loyalty_bubble['enabled'])) ? '' : 'display: none;'; ?>">
					<th scope="row">
						<label for="loyalty_bubble_position" class="label-child">
							<?php esc_html_e('Bubble position', 'loyalty-for-woocommerce'); ?>
						</label>
					</th>
					<td>
						<select id="loyalty_bubble_position" name="loyalty_bubble_position">
							<option value="bottom_right" <?php selected($loyalty_bubble['position'] ?? 'bottom_right', 'bottom_right'); ?>><?php esc_html_e('Bottom right', 'loyalty-for-woocommerce'); ?></option>
							<option value="bottom_left" <?php selected($loyalty_bubble['position'] ?? 'bottom_right', 'bottom_left'); ?>><?php esc_html_e('Bottom left', 'loyalty-for-woocommerce'); ?></option>
						</select>
					</td>
				</tr>
					<tr valign="top" id="loyalty_bubble_powered_by_row" style="<?php echo (!array_key_exists('enabled', $loyalty_bubble) || !empty($loyalty_bubble['enabled'])) ? '' : 'display: none;'; ?>">
						<th scope="row">
							<label for="loyalty_bubble_powered_by_enable" class="label-child">
								<?php esc_html_e('Powered by', 'loyalty-for-woocommerce'); ?>
							</label>
						</th>
						<td>
							<input type="checkbox" name="loyalty_bubble_powered_by_enable" id="loyalty_bubble_powered_by_enable"
								value="1" <?php checked(!empty($loyalty_bubble['powered_by']), true); ?> />
							<?php esc_html_e('Show a small YoOhw credit below the Loyalty panel.', 'loyalty-for-woocommerce'); ?>
						</td>
					</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e('Messages & Display', 'loyalty-for-woocommerce'); ?></h2>

		<!-- Shop & Product Message Settings -->
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row">
						<label for="loyalty_customizations"><?php esc_html_e('Shop & Product messages', 'loyalty-for-woocommerce'); ?></label>
					</th>
					<td>
						<div class="loyalty_customization_message">
							<table>
								<tr valign="top">
									<th scope="row">
										<label for="loyalty_message_shop_page_enable">
											<?php esc_html_e('Shop page', 'loyalty-for-woocommerce'); ?>
										</label>
									</th>
									<td>
										<input type="checkbox" name="loyalty_message_shop_page_enable" id="loyalty_message_shop_page_enable" 
											value="1" <?php checked($message_shop_page['shop_page'], true); ?> />
										<?php esc_html_e('Enable to display message in the shop (products list) page.', 'loyalty-for-woocommerce'); ?>
									</td>
								</tr>
								<tr valign="top" id="loyalty_message_shop_page_border_row" style="<?php echo ($message_shop_page['shop_page']) ? '' : 'display: none;'; ?>">
									<th scope="row">
										<label for="loyalty_message_shop_page_border" class="label-child">
											<?php esc_html_e('Border', 'loyalty-for-woocommerce'); ?>
										</label>
									</th>
									<td>
										<?php esc_html_e('Style:', 'loyalty-for-woocommerce'); ?> 
										<select id="loyalty_message_shop_page_border_style" name="loyalty_message_shop_page_border_style" style="width: 80px; margin-right: 15px;">
											<option value="none" <?php selected($message_shop_page['shop_page_border_style'], 'none'); ?>><?php echo esc_html__('None', 'loyalty-for-woocommerce'); ?></option>
											<option value="solid" <?php selected($message_shop_page['shop_page_border_style'], 'solid'); ?>><?php echo esc_html__('Solid', 'loyalty-for-woocommerce'); ?></option>
											<option value="dashed" <?php selected($message_shop_page['shop_page_border_style'], 'dashed'); ?>><?php echo esc_html__('Dashed', 'loyalty-for-woocommerce'); ?></option>
											<option value="dotted" <?php selected($message_shop_page['shop_page_border_style'], 'dotted'); ?>><?php echo esc_html__('Dotted', 'loyalty-for-woocommerce'); ?></option>
											<option value="double" <?php selected($message_shop_page['shop_page_border_style'], 'double'); ?>><?php echo esc_html__('Double', 'loyalty-for-woocommerce'); ?></option>
											<option value="groove" <?php selected($message_shop_page['shop_page_border_style'], 'groove'); ?>><?php echo esc_html__('Groove', 'loyalty-for-woocommerce'); ?></option>
											<option value="ridge" <?php selected($message_shop_page['shop_page_border_style'], 'ridge'); ?>><?php echo esc_html__('Ridge', 'loyalty-for-woocommerce'); ?></option>
											<option value="inset" <?php selected($message_shop_page['shop_page_border_style'], 'inset'); ?>><?php echo esc_html__('Inset', 'loyalty-for-woocommerce'); ?></option>
											<option value="outset" <?php selected($message_shop_page['shop_page_border_style'], 'outset'); ?>><?php echo esc_html__('Outset', 'loyalty-for-woocommerce'); ?></option>
										</select>
										<?php esc_html_e('Width:', 'loyalty-for-woocommerce'); ?> 
										<input type="text" name="loyalty_message_shop_page_border_width" id="loyalty_message_shop_page_border_width" style="width: 80px; margin-right: 15px;" value="<?php echo esc_attr($message_shop_page['shop_page_border_width'] ?? ''); ?>">
										<?php esc_html_e('Radius:', 'loyalty-for-woocommerce'); ?> 
										<input type="text" name="loyalty_message_shop_page_border_radius" id="loyalty_message_shop_page_border_radius" style="width: 80px;" value="<?php echo esc_attr($message_shop_page['shop_page_border_radius'] ?? ''); ?>">
									</td>
								</tr>
								<tr valign="top" id="loyalty_message_shop_page_colors_row" style="<?php echo ($message_shop_page['shop_page']) ? '' : 'display: none;'; ?>">
									<th scope="row">
										<label for="loyalty_message_shop_page_colors" class="label-child">
											<?php esc_html_e('Colors', 'loyalty-for-woocommerce'); ?>
										</label>
									</th>
									<td>
										<!-- Text Color -->
										<?php esc_html_e('Text:', 'loyalty-for-woocommerce'); ?> 
										<input type="color" name="loyalty_message_shop_page_text_color" id="loyalty_message_shop_page_text_color"
											value="<?php echo esc_attr($message_shop_page['shop_page_text_color'] ?? '#ffffff'); ?>" style="width: 30.2px; height: 30.2px;" />
										<input type="text" name="loyalty_message_shop_page_text_color_hex" id="loyalty_message_shop_page_text_color_hex"
											style="width: 80px; margin-right: 15px;" value="<?php echo esc_attr($message_shop_page['shop_page_text_color']); ?>" 
											placeholder="#ffffff" maxlength="7" />

										<!-- Background Color -->
										<?php esc_html_e('Background:', 'loyalty-for-woocommerce'); ?> 
										<input type="color" name="loyalty_message_shop_page_background_color" id="loyalty_message_shop_page_background_color"
											value="<?php echo esc_attr($message_shop_page['shop_page_background_color'] ?? '#ffffff'); ?>" style="width: 30.2px; height: 30.2px;" />
										<input type="text" name="loyalty_message_shop_page_background_color_hex" id="loyalty_message_shop_page_background_color_hex"
											style="width: 80px; margin-right: 15px;" value="<?php echo esc_attr($message_shop_page['shop_page_background_color']); ?>" 
											placeholder="#ffffff" maxlength="7" />

										<!-- Border Color -->
										<?php esc_html_e('Border:', 'loyalty-for-woocommerce'); ?> 
										<input type="color" name="loyalty_message_shop_page_border_color" id="loyalty_message_shop_page_border_color"
											value="<?php echo esc_attr($message_shop_page['shop_page_border_color'] ?? '#ffffff'); ?>" style="width: 30.2px; height: 30.2px;" />
										<input type="text" name="loyalty_message_shop_page_border_color_hex" id="loyalty_message_shop_page_border_color_hex"
											style="width: 80px;" value="<?php echo esc_attr($message_shop_page['shop_page_border_color']); ?>" 
											placeholder="#ffffff" maxlength="7" />
									</td>
								</tr>
								<tr valign="top" id="loyalty_message_shop_page_guest_row" style="<?php echo ($message_shop_page['shop_page']) ? '' : 'display: none;'; ?>">
									<th scope="row">
										<label for="loyalty_message_shop_page_guest_enable" class="label-child">
											<?php esc_html_e('Guest', 'loyalty-for-woocommerce'); ?>
										</label>
									</th>
									<td>
										<input type="checkbox" name="loyalty_message_shop_page_guest_enable" id="loyalty_message_shop_page_guest_enable" 
											value="1" <?php checked($message_shop_page['shop_page_guest'], true); ?> />
										<?php esc_html_e('Enable to display message for guest visitors.', 'loyalty-for-woocommerce'); ?>
									</td>
								</tr>
								<tr valign="top">
									<th scope="row">
										<label for="loyalty_message_product_page_enable">
											<?php esc_html_e('Product page', 'loyalty-for-woocommerce'); ?>
										</label>
									</th>
									<td>
										<input type="checkbox" name="loyalty_message_product_page_enable" id="loyalty_message_product_page_enable" 
											value="1" <?php checked($message_product_page['product_page'], true); ?> />
										<?php esc_html_e('Enable to display message in the single product page.', 'loyalty-for-woocommerce'); ?>
									</td>
								</tr>
								<tr valign="top" id="loyalty_message_product_page_position_row" style="<?php echo ($message_product_page['product_page']) ? '' : 'display: none;'; ?>">
									<th scope="row">
										<label for="loyalty_message_product_page_position" class="label-child">
											<?php esc_html_e('Position', 'loyalty-for-woocommerce'); ?>
										</label>
									</th>
									<td>
										<select id="loyalty_message_product_page_position" name="loyalty_message_product_page_position">
											<option value="before_add_to_cart" <?php selected($message_product_page['product_page_position'], 'before_add_to_cart'); ?>><?php echo esc_html__('Before Add to Cart button', 'loyalty-for-woocommerce'); ?></option>
											<option value="after_add_to_cart" <?php selected($message_product_page['product_page_position'], 'after_add_to_cart'); ?>><?php echo esc_html__('After Add to Cart button', 'loyalty-for-woocommerce'); ?></option>
											<option value="before_excerpt" <?php selected($message_product_page['product_page_position'], 'before_excerpt'); ?>><?php echo esc_html__('Before short description', 'loyalty-for-woocommerce'); ?></option>
											<option value="after_excerpt" <?php selected($message_product_page['product_page_position'], 'after_excerpt'); ?>><?php echo esc_html__('After short description', 'loyalty-for-woocommerce'); ?></option>
											<option value="after_product_meta" <?php selected($message_product_page['product_page_position'], 'after_product_meta'); ?>><?php echo esc_html__('After product meta', 'loyalty-for-woocommerce'); ?></option>
										</select>
									</td>
								</tr>
								<tr valign="top" id="loyalty_message_product_page_border_row" style="<?php echo ($message_product_page['product_page']) ? '' : 'display: none;'; ?>">
									<th scope="row">
										<label for="loyalty_message_product_page_border" class="label-child">
											<?php esc_html_e('Border', 'loyalty-for-woocommerce'); ?>
										</label>
									</th>
									<td>
										<?php esc_html_e('Style:', 'loyalty-for-woocommerce'); ?> 
										<select id="loyalty_message_product_page_border_style" name="loyalty_message_product_page_border_style" style="width: 80px; margin-right: 15px;">
											<option value="none" <?php selected($message_product_page['product_page_border_style'], 'none'); ?>><?php echo esc_html__('None', 'loyalty-for-woocommerce'); ?></option>
											<option value="solid" <?php selected($message_product_page['product_page_border_style'], 'solid'); ?>><?php echo esc_html__('Solid', 'loyalty-for-woocommerce'); ?></option>
											<option value="dashed" <?php selected($message_product_page['product_page_border_style'], 'dashed'); ?>><?php echo esc_html__('Dashed', 'loyalty-for-woocommerce'); ?></option>
											<option value="dotted" <?php selected($message_product_page['product_page_border_style'], 'dotted'); ?>><?php echo esc_html__('Dotted', 'loyalty-for-woocommerce'); ?></option>
											<option value="double" <?php selected($message_product_page['product_page_border_style'], 'double'); ?>><?php echo esc_html__('Double', 'loyalty-for-woocommerce'); ?></option>
											<option value="groove" <?php selected($message_product_page['product_page_border_style'], 'groove'); ?>><?php echo esc_html__('Groove', 'loyalty-for-woocommerce'); ?></option>
											<option value="ridge" <?php selected($message_product_page['product_page_border_style'], 'ridge'); ?>><?php echo esc_html__('Ridge', 'loyalty-for-woocommerce'); ?></option>
											<option value="inset" <?php selected($message_product_page['product_page_border_style'], 'inset'); ?>><?php echo esc_html__('Inset', 'loyalty-for-woocommerce'); ?></option>
											<option value="outset" <?php selected($message_product_page['product_page_border_style'], 'outset'); ?>><?php echo esc_html__('Outset', 'loyalty-for-woocommerce'); ?></option>
										</select>
										<?php esc_html_e('Width:', 'loyalty-for-woocommerce'); ?> 
										<input type="text" name="loyalty_message_product_page_border_width" id="loyalty_message_product_page_border_width" style="width: 80px; margin-right: 15px;" value="<?php echo esc_attr($message_product_page['product_page_border_width'] ?? ''); ?>">
										<?php esc_html_e('Radius:', 'loyalty-for-woocommerce'); ?> 
										<input type="text" name="loyalty_message_product_page_border_radius" id="loyalty_message_product_page_border_radius" style="width: 80px;" value="<?php echo esc_attr($message_product_page['product_page_border_radius'] ?? ''); ?>">
									</td>
								</tr>
								<tr valign="top" id="loyalty_message_product_page_color_row" style="<?php echo ($message_product_page['product_page']) ? '' : 'display: none;'; ?>">
									<th scope="row">
										<label for="loyalty_message_product_page_color" class="label-child">
											<?php esc_html_e('Colors', 'loyalty-for-woocommerce'); ?>
										</label>
									</th>
									<td>
										<!-- Text Color -->
										<?php esc_html_e('Text:', 'loyalty-for-woocommerce'); ?> 
										<input type="color" name="loyalty_message_product_page_text_color" id="loyalty_message_product_page_text_color"
											value="<?php echo esc_attr($message_product_page['product_page_text_color'] ?? '#ffffff'); ?>" style="width: 30.2px; height: 30.2px;" />
										<input type="text" name="loyalty_message_product_page_color_hex" id="loyalty_message_product_page_text_color_hex"
											style="width: 80px; margin-right: 15px;" value="<?php echo esc_attr($message_product_page['product_page_text_color']); ?>" 
											placeholder="#ffffff" maxlength="7" />

										<!-- Background Color -->
										<?php esc_html_e('Background:', 'loyalty-for-woocommerce'); ?> 
										<input type="color" name="loyalty_message_product_page_background_color" id="loyalty_message_product_page_background_color"
											value="<?php echo esc_attr($message_product_page['product_page_background_color'] ?? '#ffffff'); ?>" style="width: 30.2px; height: 30.2px;" />
										<input type="text" name="loyalty_message_product_page_background_color_hex" id="loyalty_message_product_page_background_color_hex"
											style="width: 80px; margin-right: 15px;" value="<?php echo esc_attr($message_product_page['product_page_background_color']); ?>" 
											placeholder="#ffffff" maxlength="7" />

										<!-- Border Color -->
										<?php esc_html_e('Border:', 'loyalty-for-woocommerce'); ?> 
										<input type="color" name="loyalty_message_product_page_border_color" id="loyalty_message_product_page_border_color"
											value="<?php echo esc_attr($message_product_page['product_page_border_color'] ?? '#ffffff'); ?>" style="width: 30.2px; height: 30.2px;" />
										<input type="text" name="loyalty_message_product_page_border_color_hex" id="loyalty_message_product_page_border_color_hex"
											style="width: 80px;" value="<?php echo esc_attr($message_product_page['product_page_border_color']); ?>" 
											placeholder="#ffffff" maxlength="7" />
									</td>
								</tr>
								<tr valign="top" id="loyalty_message_product_page_guest_row" style="<?php echo ($message_product_page['product_page']) ? '' : 'display: none;'; ?>">
									<th scope="row">
										<label for="loyalty_message_product_page_guest_enable" class="label-child">
											<?php esc_html_e('Guest', 'loyalty-for-woocommerce'); ?>
										</label>
									</th>
									<td>
										<input type="checkbox" name="loyalty_message_product_page_guest_enable" id="loyalty_message_product_page_guest_enable" 
											value="1" <?php checked($message_product_page['product_page_guest'], true); ?> />
										<?php esc_html_e('Enable to display message for guest visitors.', 'loyalty-for-woocommerce'); ?>
									</td>
								</tr>
								<tr valign="top" class="loyalty_customization">
									<th scope="row">
										<label for="loyalty_message_icon">
										<?php esc_html_e( 'Message icon', 'loyalty-for-woocommerce' ); ?>
										</label>
									</th>
									<td>
										<!-- Input field for the image URL -->
										<input
										type="text"
										name="loyalty_message_icon"
										id="loyalty_message_icon"
										style="width:300px;"
										value="<?php echo esc_url( $message_icon ); ?>"
										placeholder="<?php esc_attr_e( 'Image URL', 'loyalty-for-woocommerce' ); ?>"
										/>

										<!-- Button for uploading the image -->
										<button type="button" class="upload_image_button button">
										<?php esc_html_e( 'Upload Image', 'loyalty-for-woocommerce' ); ?>
										</button>
									</td>
								</tr>
							</table>
						</div>
					</td>
				</tr>
			</tbody>
		</table>

		<!-- My Account display Settings -->
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row">
						<label for="loyalty_customizations"><?php esc_html_e('My Account display', 'loyalty-for-woocommerce'); ?></label>
					</th>
					<?php if (!is_plugin_active( 'wc-advanced-accounts/wc-advanced-accounts.php' ) && !is_plugin_active( 'wc-advanced-accounts-premium/wc-advanced-accounts-premium.php' )): ?>
						<td>
							<div class="loyalty_customization_display">
								<table>
									<tr valign="top">
										<th scope="row">
											<label for="loyalty_my_account_display_enable">
												<?php esc_html_e('Points display', 'loyalty-for-woocommerce'); ?>
											</label>
										</th>
										<td>
											<input type="checkbox" name="loyalty_my_account_display_enable" id="loyalty_my_account_display_enable" 
												value="1" <?php checked($my_account_display['my_account'], true); ?> />
											<?php esc_html_e('Enable to display points in My Account page.', 'loyalty-for-woocommerce'); ?>
										</td>
									</tr>
									<tr valign="top" id="loyalty_my_account_display_label_row" style="<?php echo ($my_account_display['my_account']) ? '' : 'display: none;'; ?>">
										<th scope="row">
											<label for="loyalty_my_account_display_label" class="label-child">
												<?php esc_html_e('Points label', 'loyalty-for-woocommerce'); ?>
											</label>
										</th>
										<td>
											<input type="text" name="loyalty_my_account_display_label" id="loyalty_my_account_display_label" style="width: 200px;" value="<?php echo esc_attr($my_account_display['my_account_label']); ?>" placeholder="<?php esc_attr_e( 'My Points', 'loyalty-for-woocommerce' ); ?>">
										</td>
									</tr>
									<tr valign="top" id="loyalty_my_account_display_slug_row" style="<?php echo ($my_account_display['my_account']) ? '' : 'display: none;'; ?>">
										<th scope="row">
											<label for="loyalty_my_account_display_slug" class="label-child">
												<?php esc_html_e('Points slug', 'loyalty-for-woocommerce'); ?>
											</label>
										</th>
										<td>
											<input type="text" name="loyalty_my_account_display_slug" id="loyalty_my_account_display_slug" style="width: 200px;" value="<?php echo esc_attr($my_account_display['my_account_slug']); ?>" placeholder="my-points">
										</td>
									</tr>
								</table>
								<p class="description">
									<?php 
									printf(
										/* translators: %s: Link to Permalink Settings page */
										esc_html__('You may have to save changes at %s to make it work probably.', 'loyalty-for-woocommerce'),
										'<a href="' . esc_url(site_url('/wp-admin/options-permalink.php')) . '" target="_blank">' . esc_html__('Permalink Settings', 'loyalty-for-woocommerce') . '</a>'
									); 
									?>
								</p>
							</div>
						</td>
						<?php else: ?>
						<td>
							<?php 
							printf(
								/* translators: %s: Link to Permalink Settings page */
								esc_html__('You can setup %s.', 'loyalty-for-woocommerce'),
								'<a href="' . esc_url(site_url('/wp-admin/admin.php?page=wc-settings&tab=account&section=endpoints')) . '" target="_self">' . esc_html__('the endpoint here', 'loyalty-for-woocommerce') . '</a>'
							); 
							?>
						</td>
					<?php endif; ?>
				</tr>
			</tbody>
		</table>

		<!-- Cart & Checkout display Settings -->
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row">
						<label for="loyalty_customizations"><?php esc_html_e('Cart & Checkout messages', 'loyalty-for-woocommerce'); ?></label>
					</th>
					<td>
						<div class="loyalty_customization_display">
							<table>
								<tr valign="top">
									<th scope="row">
										<label for="loyalty_cart_display_enable">
											<?php esc_html_e('Cart', 'loyalty-for-woocommerce'); ?>
										</label>
									</th>
									<td>
										<input type="checkbox" name="loyalty_cart_display_enable" id="loyalty_cart_display_enable" 
											value="1" <?php checked($cart_checkout_display['cart'], true); ?> />
										<?php esc_html_e('Enable to display message in Cart page.', 'loyalty-for-woocommerce'); ?>
									</td>
								</tr>
								<tr valign="top">
									<th scope="row">
										<label for="loyalty_checkout_display_enable">
											<?php esc_html_e('Checkout', 'loyalty-for-woocommerce'); ?>
										</label>
									</th>
									<td>
										<input type="checkbox" name="loyalty_checkout_display_enable" id="loyalty_checkout_display_enable" 
											value="1" <?php checked($cart_checkout_display['checkout'], true); ?> />
										<?php esc_html_e('Enable to display message in Checkout page.', 'loyalty-for-woocommerce'); ?>
									</td>
								</tr>
							</table>
						</div>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	public function save_customization_settings() {
		$loyalty_roles = get_option('loyalty_levels_roles', array());
		$customization = array();

		if (!isset($_POST['loyalty_customization_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['loyalty_customization_nonce'])), 'save_loyalty_customization')) {
			wp_die(esc_html__('Nonce verification failed. Please try again.', 'loyalty-for-woocommerce'));
		}

		foreach ($loyalty_roles as $role) {
			$icon_url = isset($_POST['loyalty_level_icon_' . $role]) ? sanitize_text_field(wp_unslash($_POST['loyalty_level_icon_' . $role])) : '';
			$text_color = isset($_POST['loyalty_level_text_color_' . $role]) ? sanitize_hex_color(wp_unslash($_POST['loyalty_level_text_color_' . $role])) : '#ffffff';

			$customization[$role] = array(
				'icon' => $icon_url,
				'text_color' => $text_color,
			);
		}
		update_option('loyalty_customization_levels', $customization);

		$membercard = array(
			'membercard_text_color' => isset($_POST['loyalty_membercard_text_color']) ? sanitize_hex_color(wp_unslash($_POST['loyalty_membercard_text_color'])) : '#000000',
			'membercard_background_color' => isset($_POST['loyalty_membercard_background_color']) ? sanitize_hex_color(wp_unslash($_POST['loyalty_membercard_background_color'])) : '#f9f9f9',
			'membercard_border_color' => isset($_POST['loyalty_membercard_border_color']) ? sanitize_hex_color(wp_unslash($_POST['loyalty_membercard_border_color'])) : '#cccccc',
		);
		update_option('loyalty_customization_membercard', $membercard);

		$loyalty_bubble = array(
			'enabled' => isset($_POST['loyalty_bubble_enable']) && $_POST['loyalty_bubble_enable'] === '1',
			'position' => isset($_POST['loyalty_bubble_position']) && in_array(sanitize_key(wp_unslash($_POST['loyalty_bubble_position'])), array('bottom_right', 'bottom_left'), true)
				? sanitize_key(wp_unslash($_POST['loyalty_bubble_position']))
				: 'bottom_right',
			'powered_by' => isset($_POST['loyalty_bubble_powered_by_enable']) && $_POST['loyalty_bubble_powered_by_enable'] === '1',
		);
		update_option('loyalty_customization_loyalty_bubble', $loyalty_bubble);

		$message_shop_page = array(
			'shop_page' => isset($_POST['loyalty_message_shop_page_enable']),
			'shop_page_border_style' => isset($_POST['loyalty_message_shop_page_border_style']) ? sanitize_text_field(wp_unslash($_POST['loyalty_message_shop_page_border_style'])) : 'none',
			'shop_page_border_width' => isset($_POST['loyalty_message_shop_page_border_width']) ? sanitize_text_field(wp_unslash($_POST['loyalty_message_shop_page_border_width'])) : '',
			'shop_page_border_radius' => isset($_POST['loyalty_message_shop_page_border_radius']) ? sanitize_text_field(wp_unslash($_POST['loyalty_message_shop_page_border_radius'])) : '',
			'shop_page_text_color' => isset($_POST['loyalty_message_shop_page_text_color']) ? sanitize_hex_color(wp_unslash($_POST['loyalty_message_shop_page_text_color'])) : '#ffffff',
			'shop_page_background_color' => isset($_POST['loyalty_message_shop_page_background_color']) ? sanitize_hex_color(wp_unslash($_POST['loyalty_message_shop_page_background_color'])) : '#ffffff',
			'shop_page_border_color' => isset($_POST['loyalty_message_shop_page_border_color']) ? sanitize_hex_color(wp_unslash($_POST['loyalty_message_shop_page_border_color'])) : '#ffffff',
			'shop_page_guest' => isset($_POST['loyalty_message_shop_page_guest_enable']) && $_POST['loyalty_message_shop_page_guest_enable'] === '1',
		);
		update_option('loyalty_customization_shop_page', $message_shop_page);

		$message_product_page = array(
			'product_page' => isset($_POST['loyalty_message_product_page_enable']) && $_POST['loyalty_message_product_page_enable'] === '1',
			'product_page_position' => isset($_POST['loyalty_message_product_page_position']) ? sanitize_text_field(wp_unslash($_POST['loyalty_message_product_page_position'])) : '',
			'product_page_border_style' => isset($_POST['loyalty_message_product_page_border_style']) ? sanitize_text_field(wp_unslash($_POST['loyalty_message_product_page_border_style'])) : 'none',
			'product_page_border_width' => isset($_POST['loyalty_message_product_page_border_width']) ? sanitize_text_field(wp_unslash($_POST['loyalty_message_product_page_border_width'])) : '',
			'product_page_border_radius' => isset($_POST['loyalty_message_product_page_border_radius']) ? sanitize_text_field(wp_unslash($_POST['loyalty_message_product_page_border_radius'])) : '',
			'product_page_text_color' => isset($_POST['loyalty_message_product_page_text_color']) ? sanitize_hex_color(wp_unslash($_POST['loyalty_message_product_page_text_color'])) : '#ffffff',
			'product_page_background_color' => isset($_POST['loyalty_message_product_page_background_color']) ? sanitize_hex_color(wp_unslash($_POST['loyalty_message_product_page_background_color'])) : '#ffffff',
			'product_page_border_color' => isset($_POST['loyalty_message_product_page_border_color']) ? sanitize_hex_color(wp_unslash($_POST['loyalty_message_product_page_border_color'])) : '#ffffff',
			'product_page_guest' => isset($_POST['loyalty_message_product_page_guest_enable']) && $_POST['loyalty_message_product_page_guest_enable'] === '1',
		);
		update_option('loyalty_customization_product_page', $message_product_page);

		$message_icon = isset( $_POST['loyalty_message_icon'] )
			? sanitize_text_field( wp_unslash( $_POST['loyalty_message_icon'] ) )
			: '';
		update_option( 'loyalty_customization_message_icon', $message_icon );

		$my_account_label = isset($_POST['loyalty_my_account_display_label']) ? sanitize_text_field(wp_unslash($_POST['loyalty_my_account_display_label'])) : '';
		if ( '' === trim( $my_account_label ) || in_array( $my_account_label, array( __( 'My Points', 'loyalty-for-woocommerce' ), 'My Points', 'My points' ), true ) ) {
			$my_account_label = '';
		}

		$my_account_display = array(
			'my_account' => isset($_POST['loyalty_my_account_display_enable']) && $_POST['loyalty_my_account_display_enable'] === '1',
			'my_account_label' => $my_account_label,
			'my_account_slug' => isset($_POST['loyalty_my_account_display_slug']) ? sanitize_title(wp_unslash($_POST['loyalty_my_account_display_slug'])) : 'my-points',
		);
		update_option('loyalty_customization_my_account', $my_account_display);

		$my_account_display = array(
			'cart' => isset($_POST['loyalty_cart_display_enable']) && $_POST['loyalty_cart_display_enable'] === '1',
			'checkout' => isset($_POST['loyalty_checkout_display_enable']) && $_POST['loyalty_checkout_display_enable'] === '1',
		);
		update_option('loyalty_customization_cart_checkout', $my_account_display);

		flush_rewrite_rules();
	}
}

new YOSWC_Loyalty_Settings_Customization();

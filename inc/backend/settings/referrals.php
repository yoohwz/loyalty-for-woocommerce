<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Settings_Referrals {
	public function display_referrals_settings() {
		$loyalty_roles = get_option('loyalty_levels_roles', array());

		if ( empty($loyalty_roles) ) {
			?>
			<h2><?php esc_html_e('Referrals settings', 'loyalty-for-woocommerce'); ?></h2>
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

		$is_premium = (bool) apply_filters( 'yoswc_loyalty_is_premium', false );

		if ( ! $is_premium ) {
			$this->render_premium_banner_referrals();
			return;
		}

		$referral_link_enabled = get_option( 'loyalty_referral_link_enabled', 'no' ) === 'yes';
		$link_referrer_points = maybe_unserialize( get_option( 'loyalty_link_referrer_points', array() ) );
		$link_referee_points = maybe_unserialize( get_option( 'loyalty_link_referee_points', array() ) );
		$referral_coupon_enabled = get_option( 'loyalty_referral_coupon_enabled', 'no' ) === 'yes';
		$coupon_format = get_option( 'loyalty_referral_coupon_format', 'random' );
		$discount_type = get_option( 'loyalty_referee_discount_type', 'percent' );
		$coupon_referrer_points = maybe_unserialize( get_option( 'loyalty_coupon_referrer_points', array() ) );
		$coupon_referee_discounts = maybe_unserialize( get_option( 'loyalty_coupon_referee_discounts', array() ) );
		?>

			<div id="yowcl-referrals-premium-gate">
			<!-- Referral via link -->
			<h2><?php esc_html_e('Referral via link', 'loyalty-for-woocommerce'); ?></h2>
			<p><?php esc_html_e('Activates link-based referrals. Rewards are given to the referrer and/or referee upon purchase via the referral link.', 'loyalty-for-woocommerce'); ?></p>
			
			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row">
							<label for="loyalty_referral_link_enabled"><?php esc_html_e('Enable/Disable', 'loyalty-for-woocommerce'); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox"
									   id="loyalty_referral_link_enabled"
									   name="loyalty_referral_link_enabled"
										   value="yes" <?php checked($referral_link_enabled); ?> />
								<?php esc_html_e('Enable referral via link', 'loyalty-for-woocommerce'); ?>
							</label>
						</td>
					</tr>

					<tr valign="top" class="loyalty_referral_link_row">
						<th scope="row">
							<label><?php esc_html_e('Referrer earnings', 'loyalty-for-woocommerce'); ?></label>
						</th>
						<td>
							<table>
								<?php foreach ( $loyalty_roles as $role ) :
									$role_key = sanitize_key($role);
									$awarded  = isset($link_referrer_points[$role_key]) ? (int) $link_referrer_points[$role_key] : '';
								?>
								<tr valign="top">
									<th scope="row">
										<label for="loyalty_link_referrer_points_<?php echo esc_attr($role_key); ?>">
											<?php echo esc_html( ucfirst($role) ); ?>
										</label>
									</th>
									<td>
										<input type="number"
											name="loyalty_link_referrer_points_<?php echo esc_attr($role_key); ?>"
											id="loyalty_link_referrer_points_<?php echo esc_attr($role_key); ?>"
											style="width:84px;"
											value="<?php echo esc_attr($awarded); ?>"
											placeholder="<?php esc_attr_e('points', 'loyalty-for-woocommerce'); ?>"
											min="0" />
									</td>
								</tr>
								<?php endforeach; ?>
							</table>
							<p class="description"><?php esc_html_e('Set the number of points the referrer will receive based on their loyalty level.', 'loyalty-for-woocommerce'); ?></p>
						</td>
					</tr>

					<tr valign="top" class="loyalty_referral_link_row">
						<th scope="row">
							<label><?php esc_html_e('Referee earnings', 'loyalty-for-woocommerce'); ?></label>
						</th>
						<td>
							<table>
								<?php foreach ( $loyalty_roles as $role ) :
									$role_key = sanitize_key($role);
									$awarded  = isset($link_referee_points[$role_key]) ? (int) $link_referee_points[$role_key] : '';
								?>
								<tr valign="top">
									<th scope="row">
										<label for="loyalty_link_referee_points_<?php echo esc_attr($role_key); ?>">
											<?php echo esc_html( ucfirst($role) ); ?>
										</label>
									</th>
									<td>
										<input type="number"
											name="loyalty_link_referee_points_<?php echo esc_attr($role_key); ?>"
											id="loyalty_link_referee_points_<?php echo esc_attr($role_key); ?>"
											style="width:84px;"
											value="<?php echo esc_attr($awarded); ?>"
											placeholder="<?php esc_attr_e('points', 'loyalty-for-woocommerce'); ?>"
											min="0" />
									</td>
								</tr>
								<?php endforeach; ?>
							</table>
							<p class="description"><?php esc_html_e('Set the number of points the referee will receive based on the referrer loyalty level.', 'loyalty-for-woocommerce'); ?></p>
						</td>
					</tr>
				</tbody>
			</table>

			<!-- Referral via coupon -->
			<h2><?php esc_html_e('Referral via coupon', 'loyalty-for-woocommerce'); ?></h2>
			<p><?php esc_html_e('Activates coupon-based referrals. Rewards the referrer and applies a discount to the referee at checkout.', 'loyalty-for-woocommerce'); ?></p>

			<table class="form-table">
				<tbody>
					<tr valign="top">
						<th scope="row">
							<label for="loyalty_referral_coupon_enabled"><?php esc_html_e('Enable/Disable', 'loyalty-for-woocommerce'); ?></label>
						</th>
						<td>
							<label>
								<input type="checkbox"
									id="loyalty_referral_coupon_enabled"
									name="loyalty_referral_coupon_enabled"
									value="yes" <?php checked($referral_coupon_enabled); ?> />
								<?php esc_html_e('Enable referral via coupon', 'loyalty-for-woocommerce'); ?>
							</label>
						</td>
					</tr>

					<tr valign="top" class="loyalty_referral_coupon_row">
						<th scope="row">
							<label for="loyalty_referral_coupon_format">
								<?php esc_html_e('Coupon format', 'loyalty-for-woocommerce'); ?>
							</label>
						</th>
						<td>
							<select id="loyalty_referral_coupon_format" name="loyalty_referral_coupon_format">
								<option value="random"   <?php selected( $coupon_format, 'random' );   ?>><?php echo esc_html__('Random', 'loyalty-for-woocommerce'); ?></option>
								<option value="user_id"  <?php selected( $coupon_format, 'user_id' );  ?>><?php echo esc_html__('User ID', 'loyalty-for-woocommerce'); ?></option>
								<option value="username" <?php selected( $coupon_format, 'username' ); ?>><?php echo esc_html__('Username', 'loyalty-for-woocommerce'); ?></option>
							</select>
						</td>
					</tr>

					<tr valign="top" class="loyalty_referral_coupon_row">
						<th scope="row">
							<label for="loyalty_referee_discount_type">
								<?php esc_html_e('Discount type', 'loyalty-for-woocommerce'); ?>
							</label>
						</th>
						<td>
							<select id="loyalty_referee_discount_type" name="loyalty_referee_discount_type">
								<option value="percent"       <?php selected( $discount_type, 'percent' );       ?>><?php echo esc_html__('Percentage discount', 'loyalty-for-woocommerce'); ?></option>
								<option value="fixed_cart"    <?php selected( $discount_type, 'fixed_cart' );    ?>><?php echo esc_html__('Fixed cart discount', 'loyalty-for-woocommerce'); ?></option>
								<option value="fixed_product" <?php selected( $discount_type, 'fixed_product' ); ?>><?php echo esc_html__('Fixed product discount', 'loyalty-for-woocommerce'); ?></option>
							</select>
						</td>
					</tr>

					<tr valign="top" class="loyalty_referral_coupon_row">
						<th scope="row">
							<label><?php esc_html_e('Referrer earnings', 'loyalty-for-woocommerce'); ?></label>
						</th>
						<td>
							<table>
								<?php foreach ( $loyalty_roles as $role ) :
									$role_key = sanitize_key($role);
									$awarded  = isset($coupon_referrer_points[$role_key]) ? (int) $coupon_referrer_points[$role_key] : '';
								?>
								<tr valign="top">
									<th scope="row">
										<label for="loyalty_coupon_referrer_points_<?php echo esc_attr($role_key); ?>">
											<?php echo esc_html( ucfirst($role) ); ?>
										</label>
									</th>
									<td>
										<input type="number"
											name="loyalty_coupon_referrer_points_<?php echo esc_attr($role_key); ?>"
											id="loyalty_coupon_referrer_points_<?php echo esc_attr($role_key); ?>"
											style="width:84px;"
											value="<?php echo esc_attr($awarded); ?>"
											placeholder="<?php esc_attr_e('points', 'loyalty-for-woocommerce'); ?>"
											min="0" />
									</td>
								</tr>
								<?php endforeach; ?>
							</table>
							<p class="description"><?php esc_html_e('Set the number of points the referrer will receive based on their loyalty level.', 'loyalty-for-woocommerce'); ?></p>
						</td>
					</tr>

					<tr valign="top" class="loyalty_referral_coupon_row">
						<th scope="row">
							<label><?php esc_html_e('Referee discounts', 'loyalty-for-woocommerce'); ?></label>
						</th>
						<td>
							<table>
								<?php foreach ( $loyalty_roles as $role ) :
									$role_key  = sanitize_key($role);
									$discounts = isset($coupon_referee_discounts[$role_key]) ? (float) $coupon_referee_discounts[$role_key] : '';
								?>
								<tr valign="top">
									<th scope="row">
										<label for="loyalty_coupon_referee_discounts_<?php echo esc_attr($role_key); ?>">
											<?php echo esc_html( ucfirst($role) ); ?>
										</label>
									</th>
									<td>
										<input type="number"
											name="loyalty_coupon_referee_discounts_<?php echo esc_attr($role_key); ?>"
											id="loyalty_coupon_referee_discounts_<?php echo esc_attr($role_key); ?>"
											style="width:84px;"
											value="<?php echo esc_attr($discounts); ?>"
											placeholder="<?php esc_attr_e('discount', 'loyalty-for-woocommerce'); ?>"
											step="0.01" min="0" />
									</td>
								</tr>
								<?php endforeach; ?>
							</table>
							<p class="description">
								<?php
								echo esc_html__(
									'If type is Percentage, use 0–100. If Fixed, enter the currency amount. The discount based on the referrer loyalty level.',
									'loyalty-for-woocommerce'
								);
								?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
		</div><!-- /#yowcl-referrals-premium-gate -->

		<?php
	}

	private function render_premium_banner_referrals() {
		$premium_url = apply_filters( 'yoswc_loyalty_premium_url', 'https://yoohw.com/product/woocommerce-loyalty-points-and-rewards/' );
		?>
		<div class="yowcl-premium-card">
			<div class="yowcl-premium-card__label" aria-hidden="true"><?php esc_html_e( 'Premium', 'loyalty-for-woocommerce' ); ?></div>
			<div class="yowcl-premium-card__content">
				<h2><?php esc_html_e('Referral features are available in Premium', 'loyalty-for-woocommerce'); ?></h2>
				<p class="yowcl-premium-card__subtitle">
					<?php esc_html_e('Premium adds referral links and referral coupons for referrer and referee rewards.', 'loyalty-for-woocommerce'); ?>
				</p>
				<p class="yowcl-premium-card__cta">
					<a href="<?php echo esc_url( $premium_url ); ?>" target="_blank" rel="noopener" class="button">
						<?php esc_html_e('View Premium features', 'loyalty-for-woocommerce'); ?>
					</a>
				</p>
			</div>
		</div>
		<style>
			/* Reuse the same card look as in display_extra_points_settings */
			.yowcl-premium-card {
				display:flex; gap:16px; align-items:center;
				background:#fff; border:1px solid #ccd0d4; border-radius:8px;
				box-shadow:0 1px 1px rgba(0,0,0,.04); padding:16px; margin:16px 0;
			}
			.yowcl-premium-card__label {
				background:#f6f7f7;
				border:1px solid #e2e4e7;
				border-radius:4px;
				color:#50575e;
				font-size:12px;
				font-weight:600;
				line-height:1;
				padding:8px 10px;
			}
			.yowcl-premium-card__content h2 { margin:0 0 4px; }
			.yowcl-premium-card__subtitle { margin:0 0 10px; color:#50575e; }
			.yowcl-premium-card__cta .button + .button { margin-left:8px; }
		</style>
		<?php
	}
}

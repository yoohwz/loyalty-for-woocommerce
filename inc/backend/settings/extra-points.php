<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Settings_Extra_Points {

    public function display_extra_points_settings() {
        $loyalty_roles = get_option('loyalty_levels_roles', array());
        $extra_points = get_option('loyalty_extra_points_rules', array());
        $levelup_points = get_option('loyalty_extra_levelup_points_rules', array());

        $is_premium = (bool) apply_filters( 'yoswc_loyalty_is_premium', false );

        if (empty($loyalty_roles)) {
			?>
			<h2><?php esc_html_e('Extra points settings', 'loyalty-for-woocommerce'); ?></h2>
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

        ?>
        <h2><?php esc_html_e('Extra points settings', 'loyalty-for-woocommerce'); ?></h2>
        
        <table class="form-table">
        <?php wp_nonce_field('save_extra_points_settings_action', 'extra_points_settings_nonce'); ?>
            <tbody>
                <tr valign="top">
                    <th scope="row">
                        <label for="loyalty_extra_signup_points"><?php esc_html_e('Sign-up', 'loyalty-for-woocommerce'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="loyalty_extra_signup_points" style="width: 84px;" value="<?php echo esc_attr($extra_points['signup_points'] ?? ''); ?>" placeholder="<?php esc_attr_e('Points', 'loyalty-for-woocommerce'); ?>" min="0" />
                        <p class="description"><?php esc_html_e('Points awarded to customers when they sign up.', 'loyalty-for-woocommerce'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="loyalty_extra_login_points"><?php esc_html_e('Log-in', 'loyalty-for-woocommerce'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="loyalty_extra_login_points" style="width: 84px;" value="<?php echo esc_attr($extra_points['login_points'] ?? ''); ?>" placeholder="<?php esc_attr_e('Points', 'loyalty-for-woocommerce'); ?>" min="0" />
                        <p class="description"><?php esc_html_e('Points awarded to customers when they log-in daily.', 'loyalty-for-woocommerce'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="loyalty_extra_review_points"><?php esc_html_e('Product review', 'loyalty-for-woocommerce'); ?></label>
                    </th>
                    <td>
                        <input type="number" name="loyalty_extra_review_points" style="width: 84px;" value="<?php echo esc_attr($extra_points['review_points'] ?? ''); ?>" placeholder="<?php esc_attr_e('Points', 'loyalty-for-woocommerce'); ?>" min="0" />
                        <p class="description"><?php esc_html_e('Points awarded to customers for leaving a product review.', 'loyalty-for-woocommerce'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
					<th scope="row">
						<label for="loyalty_extra_levelup_points"><?php esc_html_e('Level up', 'loyalty-for-woocommerce'); ?></label>
					</th>
					<td>
						<div class="loyalty_extra_levelup_points">
							<table>
								<?php foreach ($loyalty_roles as $role): ?>
									<tr valign="top">
										<th scope="row">
											<label for="loyalty_extra_levelup_<?php echo esc_attr($role); ?>">
												<?php 
												/* translators: This displays the user role name with the first letter capitalized. */
												echo esc_html( ucfirst($role) ); 
												?>
											</label>
										</th>
										<td>
                                            <input type="number"
                                                name="loyalty_extra_levelup_<?php echo esc_attr($role); ?>"
                                                id="loyalty_extra_levelup_<?php echo esc_attr($role); ?>"
                                                style="width: 84px;"
                                                value="<?php echo esc_attr( $levelup_points[$role]['awarded'] ?? '' ); ?>"
                                                placeholder="<?php esc_attr_e('Points', 'loyalty-for-woocommerce'); ?>"
                                                min="0" />
										</td>
									</tr>
								<?php endforeach; ?>
							</table>
							<p class="description"><?php esc_html_e('Points awarded to customers for reaching a level role.', 'loyalty-for-woocommerce'); ?></p>
						</div>
					</td>
				</tr>
            </tbody>
        </table>
        <?php $this->render_extra_points_automation_card( $is_premium ); ?>
        <?php
    }

    private function render_extra_points_automation_card( $is_premium ) {
        if ( $is_premium ) {
            return;
        }
        ?>
        <style>
            .yoswc-contextual-premium-card {
                background: #fff;
                border: 1px solid #c3c4c7;
                border-left: 4px solid #2271b1;
                border-radius: 4px;
                margin: 16px 0 22px;
                max-width: 960px;
                padding: 14px 16px;
            }
            .yoswc-contextual-premium-card h3 {
                margin: 0 0 6px;
                font-size: 15px;
            }
            .yoswc-contextual-premium-card p {
                margin: 0 0 10px;
            }
            .yoswc-contextual-premium-card ul {
                list-style: disc;
                margin: 0 0 12px 20px;
            }
            .yoswc-contextual-premium-card__actions {
                margin: 0;
            }
        </style>
        <div class="yoswc-contextual-premium-card">
            <h3><?php echo esc_html__( 'Automate more customer reward actions in Premium', 'loyalty-for-woocommerce' ); ?></h3>
            <p><?php echo esc_html__( 'These core rules cover sign-up, daily login, product review, and level-up rewards. Premium adds lifecycle and purchase-based automations for deeper retention workflows.', 'loyalty-for-woocommerce' ); ?></p>
            <ul>
                <li><?php echo esc_html__( 'Birthday, account anniversary, and profile completion rewards', 'loyalty-for-woocommerce' ); ?></li>
                <li><?php echo esc_html__( 'First order, purchase milestone, and lifetime spend rewards', 'loyalty-for-woocommerce' ); ?></li>
                <li><?php echo esc_html__( 'Inactivity win-back campaigns and achievement points', 'loyalty-for-woocommerce' ); ?></li>
            </ul>
            <p class="yoswc-contextual-premium-card__actions">
                <a class="button" href="<?php echo esc_url( $this->get_premium_purchase_url() ); ?>" target="_blank" rel="noopener">
                    <?php echo esc_html__( 'View Premium features', 'loyalty-for-woocommerce' ); ?>
                </a>
            </p>
        </div>
        <?php
    }

    private function get_premium_purchase_url() {
        return apply_filters( 'yoswc_loyalty_premium_url', 'https://yoohw.com/product/woocommerce-loyalty-points-and-rewards/' );
    }

    public function save_extra_points_settings() {
        if (!isset($_POST['extra_points_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['extra_points_settings_nonce'])), 'save_extra_points_settings_action')) {
            wp_die(esc_html__('Nonce verification failed. Please try again.', 'loyalty-for-woocommerce'));
        }
    
        $signup_points = isset($_POST['loyalty_extra_signup_points']) ? sanitize_text_field(wp_unslash($_POST['loyalty_extra_signup_points'])) : 0;
        $login_points = isset($_POST['loyalty_extra_login_points']) ? sanitize_text_field(wp_unslash($_POST['loyalty_extra_login_points'])) : 0;
        $review_points = isset($_POST['loyalty_extra_review_points']) ? sanitize_text_field(wp_unslash($_POST['loyalty_extra_review_points'])) : 0;
    
        $extra_points = array(
            'signup_points' => $signup_points,
            'login_points' => $login_points,
            'review_points' => $review_points,
        );
    
        update_option('loyalty_extra_points_rules', $extra_points);

        $loyalty_roles = get_option('loyalty_levels_roles', array());
		$levelup_points = array();

		foreach ($loyalty_roles as $role) {
			$points_awarded = isset($_POST['loyalty_extra_levelup_' . $role]) ? sanitize_text_field(wp_unslash($_POST['loyalty_extra_levelup_' . $role])) : '';

			$levelup_points[$role] = array(
				'awarded' => $points_awarded,
			);
		}
		update_option('loyalty_extra_levelup_points_rules', $levelup_points);
    }    
}

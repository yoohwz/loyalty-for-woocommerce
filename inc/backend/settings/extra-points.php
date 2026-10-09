<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Settings_Extra_Points {

    public function display_extra_points_settings() {
        $loyalty_roles = get_option('loyalty_levels_roles', array());
        $extra_points = YOWCL_Free_Migrations::readable('signup') ? maybe_unserialize(get_option('loyalty_extra_points_rules', array())) : array();
        $extra_points = is_array($extra_points) ? $extra_points : array();
        $merged = YOWCL_Free_Migrations::readable('review') ? maybe_unserialize(get_option('loyalty_extra_reviews_gamification_rules', array())) : array();
        $merged = is_array($merged) ? $merged : array();
        $extra_points['review_points'] = 'yes' === ($merged['review_enabled'] ?? 'no') ? ($merged['review_points'] ?? 0) : 0;
        foreach (array('signup', 'login') as $kind) { if ('yes' !== ($extra_points[$kind . '_enabled'] ?? 'no')) { $extra_points[$kind . '_points'] = 0; } }
        $levelup_points = 'yes' === ($merged['levelup_enabled'] ?? 'no') ? ($merged['levelup_points'] ?? array()) : array();

        foreach (array('signup','login','review') as $kind) { if (!is_scalar($extra_points[$kind.'_points']??'')) { $extra_points[$kind.'_points']=''; } }
        $levelup_points=is_array($levelup_points)?$levelup_points:array();
        foreach ($levelup_points as $role=>$rule) { if (!is_array($rule)||!is_scalar($rule['awarded']??'')) { $levelup_points[$role]=array('awarded'=>''); } }

        $is_premium = (bool) apply_filters( 'yoswc_loyalty_is_premium', false );
        $first_purchase = maybe_unserialize( get_option( YOWCL_Free_First_Purchase::RULES, array() ) );
        $first_purchase = is_array( $first_purchase ) ? $first_purchase : array();
        $ready = array();
        foreach ( array( 'signup','login','review','levelup' ) as $feature ) { $ready[$feature] = YOWCL_Free_Migrations::ready( $feature ) && YOWCL_Free_Migrations::readable( $feature ); }

        if (empty($loyalty_roles)) {
            foreach ($ready as $feature=>$is_ready) { if (!$is_ready) { echo '<p>'.YOWCL_Free_Migrations::held_link($feature).'</p>'; } }
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
        <?php if ( in_array( false, $ready, true ) ) { echo '<p class="description">' . esc_html__( 'Rewards on hold are read-only. Review each affected reward in Migration Review. You can save the other rewards.', 'loyalty-for-woocommerce' ) . '</p>'; } ?>
        
        <table class="form-table">
        <?php wp_nonce_field('save_extra_points_settings_action', 'extra_points_settings_nonce'); ?>
            <tbody>
                <tr>
                    <th scope="row"><label for="loyalty_extra_first_purchase_enabled"><?php esc_html_e( 'First purchase', 'loyalty-for-woocommerce' ); ?></label></th>
                    <td>
                        <label><input type="checkbox" id="loyalty_extra_first_purchase_enabled" name="loyalty_extra_first_purchase_enabled" value="yes" <?php checked( 'yes', $first_purchase['first_purchase_enabled'] ?? 'no' ); ?> /> <?php esc_html_e( 'Enable First Purchase bonus', 'loyalty-for-woocommerce' ); ?></label>
                        <input type="number" name="loyalty_extra_first_purchase_points" aria-label="<?php esc_attr_e( 'First Purchase points', 'loyalty-for-woocommerce' ); ?>" min="0" max="99999999" step="1" value="<?php echo esc_attr( $first_purchase['first_purchase_points'] ?? 0 ); ?>" />
                        <p class="description"><?php esc_html_e( 'A fixed bonus for the first qualifying purchase. Only orders created after activation qualify; existing pending orders are excluded.', 'loyalty-for-woocommerce' ); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="loyalty_extra_signup_points"><?php esc_html_e('Sign-up', 'loyalty-for-woocommerce'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="loyalty_extra_signup_points" name="loyalty_extra_signup_points" style="width: 84px;" value="<?php echo esc_attr($extra_points['signup_points'] ?? ''); ?>" placeholder="<?php esc_attr_e('Points', 'loyalty-for-woocommerce'); ?>" min="0" <?php disabled( ! $ready['signup'] ); ?> />
                        <?php if (!$ready['signup']) { echo '<p>'.YOWCL_Free_Migrations::held_link('signup').'</p>'; } ?>
                        <p class="description"><?php esc_html_e('Points awarded to customers when they sign up.', 'loyalty-for-woocommerce'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="loyalty_extra_login_points"><?php esc_html_e('Log-in', 'loyalty-for-woocommerce'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="loyalty_extra_login_points" name="loyalty_extra_login_points" style="width: 84px;" value="<?php echo esc_attr($extra_points['login_points'] ?? ''); ?>" placeholder="<?php esc_attr_e('Points', 'loyalty-for-woocommerce'); ?>" min="0" <?php disabled( ! $ready['login'] ); ?> />
                        <?php if (!$ready['login']) { echo '<p>'.YOWCL_Free_Migrations::held_link('login').'</p>'; } ?>
                        <p class="description"><?php esc_html_e('Points awarded to customers when they log-in daily.', 'loyalty-for-woocommerce'); ?></p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        <label for="loyalty_extra_review_points"><?php esc_html_e('Product review', 'loyalty-for-woocommerce'); ?></label>
                    </th>
                    <td>
                        <input type="number" id="loyalty_extra_review_points" name="loyalty_extra_review_points" style="width: 84px;" value="<?php echo esc_attr($extra_points['review_points'] ?? ''); ?>" placeholder="<?php esc_attr_e('Points', 'loyalty-for-woocommerce'); ?>" min="0" <?php disabled( ! $ready['review'] ); ?> />
                        <?php if (!$ready['review']) { echo '<p>'.YOWCL_Free_Migrations::held_link('review').'</p>'; } ?>
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
                                                min="0" <?php disabled( ! $ready['levelup'] ); ?> />
										</td>
									</tr>
								<?php endforeach; ?>
							</table>
							<?php if (!$ready['levelup']) { echo '<p>'.YOWCL_Free_Migrations::held_link('levelup').'</p>'; } ?>
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
            <p><?php echo esc_html__( 'These core rules cover sign-up, daily login, product review, level-up, and First Purchase rewards. Premium adds lifecycle and purchase-based automations for deeper retention workflows.', 'loyalty-for-woocommerce' ); ?></p>
            <ul>
                <li><?php echo esc_html__( 'Birthday, account anniversary, and profile completion rewards', 'loyalty-for-woocommerce' ); ?></li>
                <li><?php echo esc_html__( 'Purchase milestone and lifetime spend rewards', 'loyalty-for-woocommerce' ); ?></li>
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
        if (!current_user_can('manage_options') || !YOWCL_Free_Core::owns() || !isset($_POST['extra_points_settings_nonce']) || !is_string($_POST['extra_points_settings_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['extra_points_settings_nonce'])), 'save_extra_points_settings_action')) {
            wp_die(esc_html__('Nonce verification failed. Please try again.', 'loyalty-for-woocommerce'));
        }
        $saved = array();
        try {
            YOWCL_Free_Migrations::locked( static function () use ( &$saved ) {
                $values = array(); $level_changed = false;
                $first = $_POST['loyalty_extra_first_purchase_points'] ?? null;
                if ( null !== $first ) { YOWCL_Free_First_Purchase::points( $first ); }
                // Disabled/absent fields grant no mutation or migration authority.
                foreach ( array( 'signup','login','review' ) as $kind ) {
                    $field = 'loyalty_extra_' . $kind . '_points';
                    if ( !YOWCL_Free_Migrations::ready( $kind ) || !array_key_exists( $field, $_POST ) || !YOWCL_Free_Migrations::readable( $kind ) ) { continue; }
                    $value = $_POST[$field];
                    if ( !is_scalar( $value ) || ( '' !== (string) $value && ( !preg_match( '/^[0-9]+$/D', (string) $value ) || strlen( (string) $value ) > 8 ) ) ) { throw new DomainException( __( 'A valid whole points amount is required.', 'loyalty-for-woocommerce' ) ); }
                    $values[$kind] = (string) $value;
                }
                $map = array();
                if ( YOWCL_Free_Migrations::ready( 'levelup' ) ) {
                    foreach ( (array) get_option( 'loyalty_levels_roles', array() ) as $role ) {
                        $field = 'loyalty_extra_levelup_' . $role;
                        if ( !array_key_exists( $field, $_POST ) ) { continue; }
                        if ( !YOWCL_Free_Migrations::readable( 'levelup' ) ) { break; }
                        $value = $_POST[$field];
                        if ( !is_scalar( $value ) || ( '' !== (string) $value && ( !preg_match( '/^[0-9]+$/D', (string) $value ) || strlen( (string) $value ) > 8 ) ) ) { throw new DomainException( __( 'A valid whole points amount is required.', 'loyalty-for-woocommerce' ) ); }
                        if ( ! $level_changed ) {
                            // Build an explicitly edited map from live rows after ownership.
                            $merged = maybe_unserialize( maybe_unserialize( YOWCL_Free_Migrations::read( 'loyalty_extra_reviews_gamification_rules' ) ) );
                            $map = $merged['levelup_points'] ?? array();
                        }
                        if ( !is_array( $map ) || ( isset( $map[$role] ) && !is_array( $map[$role] ) ) ) { throw new RuntimeException( 'migration_malformed_option' ); }
                        $map[$role] = array_replace( $map[$role] ?? array(), array( 'awarded'=>(string) $value ) ); $level_changed = true;
                    }
                }
                $labels = array( 'signup'=>__( 'Sign-up','loyalty-for-woocommerce' ),'login'=>__( 'Log-in','loyalty-for-woocommerce' ),'review'=>__( 'Product review','loyalty-for-woocommerce' ) );
                foreach ( $values as $kind=>$value ) {
                    YOWCL_Free_Migrations::save( $kind, 'review' === $kind ? 'loyalty_extra_reviews_gamification_rules' : 'loyalty_extra_points_rules', array( $kind.'_points'=>$value,$kind.'_enabled'=>(int) $value > 0 ? 'yes' : 'no' ) );
                    $saved[] = $labels[$kind];
                }
                if ( $level_changed ) {
                    $enabled = false;
                    foreach ( $map as $rule ) { $enabled = $enabled || (int) ( $rule['awarded'] ?? 0 ) > 0; }
                    YOWCL_Free_Migrations::save( 'levelup','loyalty_extra_reviews_gamification_rules',array( 'levelup_points'=>$map,'levelup_enabled'=>$enabled ? 'yes' : 'no' ) );
                    $saved[] = __( 'Level up','loyalty-for-woocommerce' );
                }
                if ( null !== $first ) { YOWCL_Free_First_Purchase::save( isset( $_POST['loyalty_extra_first_purchase_enabled'] ),$first ); $saved[] = __( 'First purchase','loyalty-for-woocommerce' ); }
            } );
            $held=array(); foreach(array('signup','login','review','levelup') as $feature) { if (!YOWCL_Free_Migrations::ready($feature) || !YOWCL_Free_Migrations::readable($feature)) { $held[]=$feature; } }
            if ($held && class_exists('WC_Admin_Settings')) {
                /* translators: Names of settings whose saves completed. */
                WC_Admin_Settings::add_error(sprintf(__('Held rewards were skipped. Confirmed saved settings: %s. Review the held rewards in Migration Review.','loyalty-for-woocommerce'),$saved?implode(', ',$saved):__('None submitted','loyalty-for-woocommerce')));
            }
        } catch ( Throwable $e ) {
            $message = $e->getMessage();
            if ( $saved ) {
                /* translators: Comma-separated names of settings whose saves completed. */
                $message .= ' ' . sprintf( __( 'Settings saved before this error: %s. Reload this page to review them.', 'loyalty-for-woocommerce' ),implode( ', ',$saved ) );
            }
            wp_die( esc_html( $message ) );
        }
    }
}

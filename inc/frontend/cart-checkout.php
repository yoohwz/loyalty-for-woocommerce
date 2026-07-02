<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Using_Point_Cart_Checkout {
    const SESSION_POINTS = 'yoswc_loyalty_applied_points';
    const SESSION_DISCOUNT = 'yoswc_loyalty_discount_amount';

	    public function __construct() {
	        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
	        add_action('woocommerce_before_checkout_form', array($this, 'display_user_points_notice'));
	        add_action('woocommerce_before_cart', array($this, 'display_user_points_notice'));
	        add_action('wp_ajax_applying_points', array($this, 'handle_ajax_applying_points'));
	        add_action('woocommerce_cart_calculate_fees', array($this, 'apply_points_to_cart_total'), 10, 1);
	        add_action('wp_ajax_get_earned_points', array($this, 'ajax_get_earned_points'));
	        add_action('wp_ajax_delete_loyalty_coupon', array($this, 'delete_loyalty_coupon'));

	        $this->includes();
	    }

    public function enqueue_scripts() {
        if (!wp_script_is('jquery', 'enqueued')) {
            wp_enqueue_script('jquery');
        }

        $custom_icon = get_option( 'loyalty_customization_message_icon' );
        if ( ! empty( $custom_icon ) ) {
            $icon_url = esc_url( $custom_icon );
        } else {
            $icon_url = esc_url( plugin_dir_url( __FILE__ ) . '../../img/reward.svg' );
        }
    
        if (is_checkout()) {
            $script_version = '1.0';
        
            wp_enqueue_script('using-points-checkout-js', plugin_dir_url(__FILE__) . '../../js/using-point-checkout.js', array('jquery'), $script_version, true);
        
            $nonce = wp_create_nonce('apply_loyalty_points');
        
            wp_localize_script('using-points-checkout-js', 'loyaltyPointsMessages', array(
                /* translators: %d: Minimum points. */
                'minPointsError' => __('You must apply at least %d points.', 'loyalty-for-woocommerce'),
                /* translators: %d: Maximum points. */
                'maxPointsError' => __('You cannot apply more than %d points.', 'loyalty-for-woocommerce'),
                'applyPointsAjax' => admin_url('admin-ajax.php'),
                'ajax_url' => admin_url('admin-ajax.php'),
                'user_id' => get_current_user_id(),
                /* translators: %d: Earning points. */
                'rewardMessage' => __('You will earn %d points with this purchase.', 'loyalty-for-woocommerce'),
                'rewardImageUrl' => $icon_url,
                'rewardIconAlt' => __( 'Reward Icon', 'loyalty-for-woocommerce' ),
                'loyaltyPointsNonce' => $nonce
            ));
        }        
        
        if (is_cart()) {
            $script_version = '1.0';

            wp_enqueue_script('using-points-cart-js', plugin_dir_url(__FILE__) . '../../js/using-point-cart.js', array('jquery'), $script_version, true);
        
            $nonce = wp_create_nonce('apply_loyalty_points');
        
            wp_localize_script('using-points-cart-js', 'loyaltyPointsMessages', array(
                /* translators: %d: Minimum points. */
                'minPointsError' => __('You must apply at least %d points.', 'loyalty-for-woocommerce'),
                /* translators: %d: Maximum points. */
                'maxPointsError' => __('You cannot apply more than %d points.', 'loyalty-for-woocommerce'),
                'applyPointsCartAjax' => admin_url('admin-ajax.php'),
                'ajax_url' => admin_url('admin-ajax.php'),
                'user_id' => get_current_user_id(),
                'loyaltyPointsNonce' => $nonce,
                /* translators: %d: Earning points. */
                'rewardMessage' => __('You will earn %d points with this purchase.', 'loyalty-for-woocommerce'),
                'rewardImageUrl' => $icon_url,
                'rewardIconAlt' => __( 'Reward Icon', 'loyalty-for-woocommerce' ),
                'requestError' => __( 'An error occurred. Please try again.', 'loyalty-for-woocommerce' ),
            ));
        }        
    }
    

    public function display_user_points_notice() {
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $user_points = get_user_meta($user_id, 'user_points', true);
            $earned_points = 0; 
            
            $customization_options = maybe_unserialize(get_option('loyalty_customization_cart_checkout', []));
    
            $is_cart_page = is_cart() && isset($customization_options['cart']) && $customization_options['cart'] == 1;
            $is_checkout_page = is_checkout() && isset($customization_options['checkout']) && $customization_options['checkout'] == 1;
            $display_reward_message = $is_cart_page || $is_checkout_page;
    
            $loyalty_points_rules = get_option('loyalty_points_using_rules');
            $min_points = 1;

            $custom_icon = get_option( 'loyalty_customization_message_icon' );
			if ( ! empty( $custom_icon ) ) {
				$icon_url = esc_url( $custom_icon );
			} else {
				$icon_url = esc_url( plugin_dir_url( __FILE__ ) . '../../img/reward.svg' );
			}
    
            if ($loyalty_points_rules) {
                $rules = maybe_unserialize($loyalty_points_rules);
                if (isset($rules['points']) && isset($rules['amount'])) {
                    $min_points = (int) $rules['points'];
                }
            }
    
	            $applied_points = $this->get_applied_points();
	    
	            $script_handle = is_cart() ? 'using-points-cart-js' : 'using-points-checkout-js';
	            wp_localize_script($script_handle, 'loyaltyPointsData', array(
	                'isCouponApplied' => $applied_points > 0,
	                'isPointsApplied' => $applied_points > 0,
	                'appliedPoints' => $applied_points,
	                'userPoints' => $user_points,
	                'minPoints' => $min_points,
	            ));
        
            if ($user_points >= $min_points) {
                ?>
                <div id="loyalty-points-message"></div>
                
                <div id="loyalty-using-points" class="woocommerce-info">
                    <p style="margin: 0;">
                        <?php printf(
                            /* translators: %d: Available points. */
                            esc_html__( 'You have %d points available.', 'loyalty-for-woocommerce' ), 
                            esc_html( $user_points )
                        ); ?>
                        <a href="#" class="toggle-loyalty-points">
                            <?php esc_html_e('Click here to use your points', 'loyalty-for-woocommerce'); ?>
                        </a>
                    </p>
	                    <?php if ($applied_points > 0) : ?>
	                        <p style="margin: 10px 0 0;">
	                            <?php
	                            printf(
	                                /* translators: %d: Applied points. */
	                                esc_html__('%d points are applied to this cart.', 'loyalty-for-woocommerce'),
	                                esc_html($applied_points)
	                            );
	                            ?>
	                            <a href="#" class="remove-loyalty-points"><?php esc_html_e('Remove points', 'loyalty-for-woocommerce'); ?></a>
	                        </p>
	                    <?php else : ?>
	                        <div class="loyalty-points-toggle-content" style="display: none; margin-top: 10px;">
	                            <input type="number" id="loyalty_points_input" name="loyalty_points_input" min="<?php echo esc_attr($min_points); ?>" max="<?php echo esc_attr($user_points); ?>" value="<?php echo esc_attr($user_points); ?>" style="width: 80px; margin-right: 10px;" />
	                            <button type="button" id="apply_loyalty_points" class="button"><?php esc_html_e('Apply points', 'loyalty-for-woocommerce'); ?></button>
	                        </div>
	                    <?php endif; ?>
	                </div>
    
                <!-- Display the reward message if earned points are greater than zero -->
                <?php if ($display_reward_message && $earned_points > 0) : ?>
                    <div id="loyalty-reward-message" class="woocommerce-info">
                        <div class="loyalty-reward-message">
	                            <img src="<?php echo esc_url($icon_url); ?>"
	                                alt="<?php esc_attr_e( 'Reward Icon', 'loyalty-for-woocommerce' ); ?>"
                                width="22" height="auto">
                            <?php printf(
                                /* translators: %d: Earning points. */
                                esc_html__( 'You will earn %d points with this purchase.', 'loyalty-for-woocommerce' ), 
                                esc_html( $earned_points )
                            ); ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php
            }

            $earned_points = $this->calculate_potential_earned_points($user_id);

            $user = wp_get_current_user();
            $user_roles = (array) $user->roles;
            
            $loyalty_levels_roles = get_option('loyalty_levels_roles', array());

            if ($display_reward_message && $earned_points > 0 && array_intersect($user_roles, $loyalty_levels_roles)) {
                ?>
                <div id="loyalty-reward-message" class="woocommerce-info">
                    <div class="loyalty-reward-message">
	                        <img src="<?php echo esc_url($icon_url); ?>"
	                            alt="<?php esc_attr_e( 'Reward Icon', 'loyalty-for-woocommerce' ); ?>"
                            width="22" height="auto">
                        <?php printf(
                            /* translators: %d: Earning points. */
                            esc_html__( 'You will earn %d points with this purchase.', 'loyalty-for-woocommerce' ), 
                            esc_html( $earned_points )
                        ); ?>
                    </div>
                </div>
                <?php
            }
        }
    }
    
	    public function handle_ajax_applying_points() {
	        if ( ! isset( $_POST['loyalty_points_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['loyalty_points_nonce'] ) ), 'apply_loyalty_points' ) ) {
	            wp_send_json_error(['message' => __('Nonce verification failed.', 'loyalty-for-woocommerce')]);
	            wp_die();
	        }

	        if (!is_user_logged_in()) {
	            wp_send_json_error(['message' => __('User not logged in.', 'loyalty-for-woocommerce')]);
	            wp_die();
	        }
	    
	        $loyalty_points_input = isset($_POST['loyalty_points_input']) ? floatval( wp_unslash( $_POST['loyalty_points_input'] ) ) : 0;
	    
	        $user_id = get_current_user_id();
	        $user_points = get_user_meta($user_id, 'user_points', true);
	        $user_points = !empty($user_points) ? floatval($user_points) : 0.0;

	        $rules = $this->get_using_rules();
	        if (empty($rules)) {
	            wp_send_json_error(['message' => __('Point redemption is not configured.', 'loyalty-for-woocommerce')]);
	            wp_die();
	        }

	        if ($loyalty_points_input <= 0 || $loyalty_points_input < $rules['points'] || $loyalty_points_input > $user_points) {
	            wp_send_json_error(['message' => __('Invalid points amount.', 'loyalty-for-woocommerce')]);
	            wp_die();
	        }

	        $redemption = $this->calculate_redemption_for_cart($loyalty_points_input, $rules);
	        if (empty($redemption)) {
	            wp_send_json_error(['message' => __('Invalid points amount.', 'loyalty-for-woocommerce')]);
	            wp_die();
	        }

	        $this->set_applied_points($redemption['points'], $redemption['discount']);
	        WC()->cart->calculate_totals();

	        wp_send_json_success(['message' => __('Points applied successfully.', 'loyalty-for-woocommerce')]);
	    
	        wp_die();
	    }
	    
	    public function apply_points_to_cart_total($cart = null) {
	        if (!is_user_logged_in()) {
	            return;
	        }

	        if (is_admin() && !wp_doing_ajax()) {
	            return;
	        }

	        $cart = $cart instanceof WC_Cart ? $cart : WC()->cart;
	        if (!$cart) {
	            return;
	        }

	        $applied_points = $this->get_applied_points();
	        $discount = $this->get_applied_discount();
	        if ($applied_points <= 0 || $discount <= 0) {
	            return;
	        }

	        $user_points = (float) get_user_meta(get_current_user_id(), 'user_points', true);
	        if ($applied_points > $user_points) {
	            $this->clear_applied_points();
	            return;
	        }

	        $discount_limit = $this->get_cart_discount_limit($cart);
	        if ($discount <= 0 || $discount > $discount_limit) {
	            $this->clear_applied_points();
	            return;
	        }

	        $cart->add_fee(__('Points used', 'loyalty-for-woocommerce'), -1 * $discount, false);
	    }
    
    public function ajax_get_earned_points() {
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => __('User not logged in', 'loyalty-for-woocommerce')]);
        }
    
        $user_id = get_current_user_id();
        $earned_points = $this->calculate_potential_earned_points($user_id);
    
        wp_send_json_success(['earned_points' => $earned_points]);
    }

	    private function calculate_potential_earned_points($user_id) {
	        $user = get_userdata($user_id);
	        $user_role = !empty($user->roles) ? $user->roles[0] : '';

        $earning_rules = maybe_unserialize(get_option('loyalty_points_earning_rules', []));

        $earned_points = 0;

        if (isset($earning_rules[$user_role])) {
            $rule = $earning_rules[$user_role];
            $points = (int) $rule['points'];
            $amount = (int) $rule['amount'];

            $earning_options = maybe_unserialize(get_option('loyalty_points_earning_option', []));

            $rounding_option = get_option('loyalty_points_rounding', 'round_down');

            $cart = WC()->cart;
            $order_subtotal = $cart->get_subtotal();
            $subtotal_tax = $cart->get_subtotal_tax();

            if (is_array($earning_options)) {
                if (in_array('coupons', $earning_options)) {
                    $discount_total = $cart->get_discount_total();
                    $order_subtotal -= $discount_total;
                }

                if (!in_array('taxes', $earning_options)) {
                    $order_subtotal += $subtotal_tax; 
                }
            }

            if ($amount > 0) {
                $earned_points = ($order_subtotal / $amount) * $points;

                switch ($rounding_option) {
                    case 'round_up':
                        $earned_points = ceil($earned_points); 
                        break;
                    case 'round_down':
                        $earned_points = floor($earned_points);
                        break;
                    default:
                        $earned_points = round($earned_points);
                        break;
                }
            }
        }
	
	        return (int) $earned_points;
	    }

	    private function get_using_rules() {
	        $rules = maybe_unserialize(get_option('loyalty_points_using_rules'));
	        if (!is_array($rules) || empty($rules['points']) || empty($rules['amount'])) {
	            return array();
	        }

	        $points = (float) $rules['points'];
	        $amount = (float) $rules['amount'];
	        if ($points <= 0 || $amount <= 0) {
	            return array();
	        }

	        return array(
	            'points' => $points,
	            'amount' => $amount,
	        );
	    }

	    private function calculate_redemption_for_cart($points, $rules) {
	        $discount = ($points / $rules['points']) * $rules['amount'];
	        $limit = $this->get_cart_discount_limit(WC()->cart);

	        if ($limit <= 0) {
	            return array();
	        }

	        if ($discount > $limit) {
	            $points = floor(($limit / $rules['amount']) * $rules['points']);
	            $discount = ($points / $rules['points']) * $rules['amount'];
	        }

	        if ($points < $rules['points'] || $discount <= 0) {
	            return array();
	        }

	        return array(
	            'points' => $points,
	            'discount' => wc_format_decimal($discount),
	        );
	    }

	    private function get_cart_discount_limit($cart) {
	        if (!$cart) {
	            return 0.0;
	        }

	        $subtotal = (float) $cart->get_subtotal();
	        $discounts = (float) $cart->get_discount_total();

	        return max(0.0, $subtotal - $discounts);
	    }

	    private function get_applied_points() {
	        if (!WC()->session) {
	            return 0.0;
	        }

	        return (float) WC()->session->get(self::SESSION_POINTS, 0);
	    }

	    private function get_applied_discount() {
	        if (!WC()->session) {
	            return 0.0;
	        }

	        return (float) WC()->session->get(self::SESSION_DISCOUNT, 0);
	    }

	    private function set_applied_points($points, $discount) {
	        if (!WC()->session) {
	            return;
	        }

	        WC()->session->set(self::SESSION_POINTS, (float) $points);
	        WC()->session->set(self::SESSION_DISCOUNT, (float) $discount);
	    }

	    private function clear_applied_points() {
	        if (!WC()->session) {
	            return;
	        }

	        WC()->session->__unset(self::SESSION_POINTS);
	        WC()->session->__unset(self::SESSION_DISCOUNT);
	    }
	    
	    public function delete_loyalty_coupon() {
	        if (!is_user_logged_in()) {
	            wp_send_json_error(['message' => __('User not logged in.', 'loyalty-for-woocommerce')]);
            return;
        }
    
        if ( ! isset( $_POST['loyalty_points_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['loyalty_points_nonce'] ) ), 'apply_loyalty_points' ) ) {
	            wp_send_json_error(['message' => __('Nonce verification failed.', 'loyalty-for-woocommerce')]);
	            return;
	        }

	        $this->clear_applied_points();
	        if (WC()->cart) {
	            WC()->cart->calculate_totals();
	        }

	        wp_send_json_success(['message' => __('Points removed successfully.', 'loyalty-for-woocommerce')]);
	    }    
    
    public function includes() {
		include_once plugin_dir_path(__FILE__) . '../backend/actions/use-points.php';
        include_once plugin_dir_path(__FILE__) . '../backend/actions/return-points.php';
	}
}

new YOSWC_Loyalty_Using_Point_Cart_Checkout();

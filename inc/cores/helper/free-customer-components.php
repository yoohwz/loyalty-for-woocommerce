<?php
/** Shared dynamic block and shortcode presentation. Cacheable output is always anonymous. */
defined( 'ABSPATH' ) || exit;
class YOWCL_Free_Customer_Components {
    const KINDS = array( 'points-balance','points-history','level-progress','ways-to-earn','referral-link' );
    public static function boot() {
        add_action( 'init', array( __CLASS__, 'register' ) );
        add_action( 'wp_ajax_loyf_customer_components', array( __CLASS__, 'ajax' ) );
    }
    public static function titles() {
        return array( 'points-balance'=>__( 'Points balance', 'loyalty-for-woocommerce' ),'points-history'=>__( 'Points history', 'loyalty-for-woocommerce' ),'level-progress'=>__( 'Loyalty level', 'loyalty-for-woocommerce' ),'ways-to-earn'=>__( 'Ways to earn', 'loyalty-for-woocommerce' ),'referral-link'=>__( 'Referral link', 'loyalty-for-woocommerce' ) );
    }
    public static function register() {
        $url = plugin_dir_url( YOSWC_LOYALTY_PLUGIN_FILE );
        wp_register_script( 'loyf-customer-editor', $url . 'js/customer-editor.js', array( 'wp-blocks','wp-element','wp-block-editor','wp-server-side-render','wp-i18n' ), hash_file( 'sha256', YOSWC_LOYALTY_PLUGIN_DIR . 'js/customer-editor.js' ), true );
        wp_register_script( 'loyf-referral-lite', $url . 'js/referral-lite.js', array(), hash_file( 'sha256', YOSWC_LOYALTY_PLUGIN_DIR . 'js/referral-lite.js' ), true );
        wp_register_script( 'loyf-customer-components', $url . 'js/customer-components.js', array(), hash_file( 'sha256', YOSWC_LOYALTY_PLUGIN_DIR . 'js/customer-components.js' ), true );
        // Only public URLs and public messages: no user ID, nonce or data in page caches.
        wp_localize_script( 'loyf-customer-components', 'loyfCustomerComponents', array( 'url'=>admin_url( 'admin-ajax.php' ),'failure'=>__( 'Sign in to view your loyalty information. If already signed in, reload to try again.', 'loyalty-for-woocommerce' ) ) );
        wp_register_style( 'loyf-customer-components', $url . 'css/customer-components.css', array(), hash_file( 'sha256', YOSWC_LOYALTY_PLUGIN_DIR . 'css/customer-components.css' ) );
        foreach ( self::KINDS as $kind ) {
            register_block_type( YOSWC_LOYALTY_PLUGIN_DIR . 'inc/blocks/' . $kind, array( 'render_callback'=>static function () use ( $kind ) { return self::shell( $kind ); } ) );
            add_shortcode( 'loyf_' . str_replace( '-', '_', $kind ), static function () use ( $kind ) { return self::shell( $kind ); } );
        }
    }
    public static function shell( $kind ) {
        if ( ! in_array( $kind, self::KINDS, true ) ) { return ''; }
        wp_enqueue_script( 'loyf-customer-components' ); wp_enqueue_style( 'loyf-customer-components' );
        if ( 'referral-link' === $kind ) { wp_enqueue_script( 'loyf-referral-lite' ); }
        return '<section class="loyf-customer-component wp-block-loyf-' . esc_attr( $kind ) . '" data-loyf-component="' . esc_attr( $kind ) . '"><h3>' . esc_html( self::titles()[$kind] ) . '</h3><div class="loyf-customer-content" aria-live="polite"><p>' . esc_html__( 'Your loyalty information appears here after signing in.', 'loyalty-for-woocommerce' ) . '</p></div></section>';
    }
    public static function ajax() {
        nocache_headers(); header( 'Cache-Control: private, no-store, max-age=0' ); header( 'Vary: Cookie' );
        if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! is_user_logged_in() || ! YOWCL_Free_Core::owns() || ! isset( $_POST['nonce'] ) || ! is_string( $_POST['nonce'] ) || ! check_ajax_referer( 'wp_rest', 'nonce', false ) ) { wp_send_json_error( array( 'message'=>__( 'Loyalty information is unavailable.', 'loyalty-for-woocommerce' ) ), 403 ); }
        // No caller-provided identity or component attributes are ever consulted.
        try {
            $customer = YOWCL_Free_Readers::snapshot( static function () { return YOWCL_Free_Readers::customer( get_current_user_id() ); } );
            $markup = array();
            $requested = $_POST['kinds'] ?? array();
            if ( ! is_array( $requested ) || count( $requested ) > count( self::KINDS ) || count( array_filter( $requested, 'is_string' ) ) !== count( $requested ) ) { throw new DomainException( 'reader_components_invalid' ); }
            $kinds = array_intersect( self::KINDS, $requested );
            foreach ( $kinds as $kind ) {
                try { $markup[$kind] = self::present( $kind, $customer ); }
                catch ( Throwable $e ) { $markup[$kind] = self::text( __( 'Unavailable — contact the store for review.', 'loyalty-for-woocommerce' ) ); }
            }
        } catch ( Throwable $e ) { wp_send_json_error( array( 'message'=>__( 'Loyalty information needs review or this account is not enrolled.', 'loyalty-for-woocommerce' ) ), 503 ); return; }
        wp_send_json_success( $markup );
    }
    public static function text( $text ) { return '<p>' . esc_html( $text ) . '</p>'; }
    public static function present( $kind, array $customer ) {
        if ( ! is_user_logged_in() || ! YOWCL_Free_Core::owns() ) { return ''; }
        $unavailable = __( 'Unavailable — contact the store for review.', 'loyalty-for-woocommerce' );
        if ( 'points-balance' === $kind ) {
            $label = get_option( 'loyalty_point_label', '' );
            $label = is_string( $label ) && '' !== trim( $label ) ? $label : __( 'Points', 'loyalty-for-woocommerce' );
            $markup = self::text( null === $customer['available']['value'] ? $unavailable : $customer['available']['value'] . ' ' . $label );
            if ( $customer['held'] ) { $markup .= self::text( __( 'Stored balance; spending is held for review.', 'loyalty-for-woocommerce' ) ); }
            return $markup;
        }
        if ( 'points-history' === $kind ) {
            $settings = get_option( 'loyalty_customization_my_account', array() );
            if ( ! is_array( $settings ) || empty( $settings['my_account'] ) || wc_get_page_id( 'myaccount' ) <= 0 ) { return self::text( __( 'The store has not enabled the points history page.', 'loyalty-for-woocommerce' ) ); }
            $slug = $settings['my_account_slug'] ?? 'my-points';
            if ( ! is_string( $slug ) || '' === $slug || sanitize_title( $slug ) !== $slug ) { return self::text( $unavailable ); }
            if ( is_plugin_active( 'wc-advanced-accounts/wc-advanced-accounts.php' ) || is_plugin_active( 'wc-advanced-accounts-premium/wc-advanced-accounts-premium.php' ) ) { $slug = 'my-points'; }
            $label = is_string( $settings['my_account_label'] ?? null ) && '' !== trim( $settings['my_account_label'] ) ? $settings['my_account_label'] : __( 'My Points', 'loyalty-for-woocommerce' );
            return '<p><a href="' . esc_url( wc_get_account_endpoint_url( $slug ) ) . '">' . esc_html( $label ) . '</a></p>';
        }
        if ( 'level-progress' === $kind ) {
            if ( null === $customer['level'] ) { return self::text( $unavailable ); }
            $role = $customer['level']; $rules = $customer['levels'];
            $markup = self::text( wp_roles()->roles[$role]['name'] ?? $role );
            if ( null === $customer['earned']['value'] ) { return $markup . self::text( $unavailable ); }
            $markup .= self::text( sprintf( __( 'Earned points: %s', 'loyalty-for-woocommerce' ), $customer['earned']['value'] ) );
            $next = null;
            foreach ( $rules as $slug => $rule ) { if ( (int) $rule['from'] > (int) ( $rules[$role]['from'] ?? 0 ) && ( null === $next || (int) $rule['from'] < (int) $rules[$next]['from'] ) ) { $next = $slug; } }
            if ( null !== $next ) { $markup .= self::text( sprintf( __( 'Next level: %1$s at %2$s earned points.', 'loyalty-for-woocommerce' ), wp_roles()->roles[$next]['name'] ?? $next, (string) $rules[$next]['from'] ) ); }
            return $markup;
        }
        if ( 'ways-to-earn' === $kind ) {
            $items = array(); $rules = maybe_unserialize( get_option( 'loyalty_points_earning_rules', array() ) ); $role = $customer['level'];
            if ( ! is_array( $rules ) ) { return self::text( $unavailable ); }
            if ( $role && isset( $rules[$role] ) ) {
                $rule = $rules[$role];
                if ( ! is_array( $rule ) || ! is_scalar( $rule['points'] ?? null ) || ! is_scalar( $rule['amount'] ?? null ) || ! is_numeric( $rule['points'] ) || ! is_numeric( $rule['amount'] ) || ! is_finite( (float) $rule['points'] ) || ! is_finite( (float) $rule['amount'] ) ) { return self::text( $unavailable ); }
                if ( (int) $rule['points'] > 0 && (float) $rule['amount'] > 0 ) { $items[] = __( 'Eligible purchases', 'loyalty-for-woocommerce' ); }
            }
            foreach ( array( 'signup'=>__( 'Create an account', 'loyalty-for-woocommerce' ),'login'=>__( 'Daily login', 'loyalty-for-woocommerce' ),'review'=>__( 'Product review', 'loyalty-for-woocommerce' ) ) as $type => $label ) { if ( YOWCL_Free_Core::extra( $type ) > 0 ) { $items[] = $label; } }
            foreach ( YOWCL_Free_Core::level_rules() as $rule ) { if ( (int) ( $rule['awarded'] ?? 0 ) > 0 ) { $items[] = __( 'Reach a new loyalty level', 'loyalty-for-woocommerce' ); break; } }
            $first = YOWCL_Free_First_Purchase::configuration();
            if ( $first['effective'] && $first['ready'] ) { $items[] = __( 'First eligible purchase', 'loyalty-for-woocommerce' ); }
            if ( YOWCL_Free_Referral::settings()['enabled'] ) { $items[] = __( 'Refer a registered customer', 'loyalty-for-woocommerce' ); }
            if ( ! $items ) { return self::text( __( 'No Free earning offers are currently enabled.', 'loyalty-for-woocommerce' ) ); }
            return '<ul><li>' . implode( '</li><li>', array_map( 'esc_html', $items ) ) . '</li></ul>';
        }
        if ( 'referral-link' === $kind ) {
            if ( ! YOWCL_Free_Referral::settings()['enabled'] ) { return self::text( __( 'Referrals are currently disabled.', 'loyalty-for-woocommerce' ) ); }
            ob_start();
            try { YOWCL_Free_Referral::render_link( 'customer-components' ); $markup = ob_get_contents(); return '' === $markup ? self::text( $unavailable ) : $markup; }
            finally { ob_end_clean(); }
        }
        return '';
    }
}

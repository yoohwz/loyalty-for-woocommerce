<?php
defined( 'ABSPATH' ) || exit;
class YOWCL_Free_Blocks_Integration implements Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface {
    public function get_name() { return 'loyf-redemption'; }
    public function initialize() {
        add_action( 'wp_enqueue_scripts', static function () {
            if ( has_block( 'woocommerce/cart' ) || has_block( 'woocommerce/checkout' ) ) {
                $file = YOSWC_LOYALTY_PLUGIN_DIR . 'css/blocks-redemption.css';
                wp_enqueue_style( 'loyf-blocks-redemption', plugins_url( 'css/blocks-redemption.css', YOSWC_LOYALTY_PLUGIN_FILE ), array( 'wc-blocks-packages-style' ), substr( hash_file( 'sha256', $file ), 0, 12 ) );
            }
        } );
        $file = YOSWC_LOYALTY_PLUGIN_DIR . 'js/blocks-redemption.js';
        wp_register_script( 'loyf-blocks-redemption', plugins_url( 'js/blocks-redemption.js', YOSWC_LOYALTY_PLUGIN_FILE ), array( 'wp-element', 'wp-data', 'wp-plugins', 'wp-i18n', 'wc-blocks-components', 'wc-blocks-checkout', 'wc-blocks-data-store' ), substr( hash_file( 'sha256', $file ), 0, 12 ), true );
    }
    public function get_script_handles() { return array( 'loyf-blocks-redemption' ); }
    public function get_editor_script_handles() { return array(); }
    public function get_script_data() { return array(); }
}

<?php
defined( 'ABSPATH' ) || exit;
class YOSWC_Loyalty_Settings_Referrals {
    public function display_referrals_settings() {
        try { $c = YOWCL_Free_Referral::settings(); } catch ( Throwable $e ) { $c = array( 'enabled'=>false, 'points'=>0 ); }
        ?>
        <h2><?php esc_html_e( 'Referral Lite', 'loyalty-for-woocommerce' ); ?></h2>
        <p><?php esc_html_e( 'Earn fixed points when a registered customer follows your link and completes their first qualifying order. Cancelled, failed or refunded orders reverse the referral reward.', 'loyalty-for-woocommerce' ); ?></p>
        <?php wp_nonce_field( 'loyf_referral_save', 'loyf_referral_nonce' ); ?>
        <table class="form-table"><tbody>
            <tr><th scope="row"><?php esc_html_e( 'Referral Lite', 'loyalty-for-woocommerce' ); ?></th>
                <td><label for="loyf_referral_enabled"><input type="checkbox" id="loyf_referral_enabled" name="loyf_referral_enabled" value="yes" <?php checked( $c['enabled'] ); ?>> <?php esc_html_e( 'Enable Referral Lite', 'loyalty-for-woocommerce' ); ?></label></td></tr>
            <tr><th scope="row"><label for="loyf_referral_points"><?php esc_html_e( 'Referrer points', 'loyalty-for-woocommerce' ); ?></label></th>
                <td><input type="number" id="loyf_referral_points" name="loyf_referral_points" style="width: 84px;" min="0" max="99999999" step="1" value="<?php echo esc_attr( $c['points'] ); ?>"></td></tr>
        </tbody></table>
        <input type="hidden" name="loyf_referral_save" value="1">
        <?php
    }
}
add_action( 'admin_init', static function () {
    if ( ! isset( $_POST['loyf_referral_save'] ) ) { return; }
    if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['loyf_referral_nonce'] ) || ! is_string( $_POST['loyf_referral_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['loyf_referral_nonce'] ), 'loyf_referral_save' ) ) { wp_die( 'referral_save_denied' ); }
    try { YOWCL_Free_Referral::save( isset( $_POST['loyf_referral_enabled'] ), $_POST['loyf_referral_points'] ?? null ); }
    catch ( Throwable $e ) { wp_die( esc_html( $e->getMessage() ) ); }
} );

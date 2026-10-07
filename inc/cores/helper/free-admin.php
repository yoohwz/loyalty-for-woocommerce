<?php
defined( 'ABSPATH' ) || exit;
class YOWCL_Free_Admin {
    public static function operate( $deduct = false ) {
        check_ajax_referer( 'ajax_nonce', 'security' );
        if ( ! current_user_can( 'manage_options' ) || ! YOWCL_Free_Core::owns() ) { wp_send_json_error( __( 'Unauthorized user', 'loyalty-for-woocommerce' ) ); }
        $user = absint( $_POST['user_id'] ?? 0 );
        $raw = $_POST['points'] ?? '';
        $id = $_POST['operation_id'] ?? '';
        if ( ! is_string( $raw ) || ! preg_match( '/^[1-9][0-9]{0,7}$/D', $raw ) || ! is_string( $id ) || ! YOWCL_Order_Redemption::valid_id( $id ) || ! get_userdata( $user ) ) { wp_send_json_error( __( 'A valid whole points amount and operation identity are required.', 'loyalty-for-woocommerce' ) ); }
        $description = isset( $_POST['description'] ) && is_string( $_POST['description'] ) ? sanitize_text_field( wp_unslash( $_POST['description'] ) ) : '';
        $key = 'admin:' . get_current_user_id() . ':' . $user . ':' . $id;
        $points = (int) $raw * ( $deduct ? -1 : 1 );
        $result = YOWCL_Points_Transaction::mutate( $user, $points, $points, $key, array( 'action' => $deduct ? 'admin_deduct' : 'admin_reward', 'description' => $description ), $deduct ? 'clamp' : 'credit' );
        if ( ! in_array( $result['status'], array( 'applied', 'already_applied' ), true ) ) { YOWCL_Free_Core::hold( $user, $result['code'] ); wp_send_json_error( __( 'Points require recovery. Retry the same operation after resolving the reported condition.', 'loyalty-for-woocommerce' ) . ' ' . $result['code'] ); }
        try { YOWCL_Order_Rewards::level( $user, (array) maybe_unserialize( get_option( 'loyalty_levels_rules', array() ) ) ); }
        catch ( Throwable $e ) { YOWCL_Free_Core::hold( $user, $e->getMessage() ); wp_send_json_error( __( 'Points were committed; level projection requires retry of the same operation.', 'loyalty-for-woocommerce' ) ); }
        if ( 'applied' === $result['status'] ) {
            try {
                if ( $deduct ) { YOWCL_Points_Events::deduct( $user, $result['old_points'] - $result['new_points'], $result['new_points'], null ); }
                else { YOWCL_Points_Events::reward( $user, $points, $result['new_points'], null ); }
            } catch ( Throwable $ignored ) {}
        }
        wp_send_json_success( array( 'message' => $deduct ? __( 'Points deducted successfully!', 'loyalty-for-woocommerce' ) : __( 'Points rewarded successfully!', 'loyalty-for-woocommerce' ) ) );
    }
}

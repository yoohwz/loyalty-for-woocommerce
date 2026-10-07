<?php
require __DIR__ . '/assertions.php';
global $wpdb;
loyf_equal( '1.2.2', YOSWC_LOYALTY_VERSION, 'Identity/version' );
loyf_assert( class_exists( 'YOWCL_Points_Transaction' ), 'Hardened core boot' );
$pre = get_option( 'loyf_fixture_pre' ); $table = YOSWC_Loyalty_Database::get_table_name();
$row = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE id=%d", $pre['row']['id'] ), ARRAY_A );
foreach ( $pre['row'] as $key => $value ) { loyf_equal( $value, $row[$key], 'Historical row bytes ' . $key ); }
foreach ( array( 'event_key', 'available_delta', 'earning_delta', 'ledger_version', 'source_event_key', 'allocation_receipt' ) as $key ) { loyf_equal( null, $row[$key], 'Nullable historical field ' . $key ); }
loyf_balance( $pre['user'], 37, 37, 'Existing value preserved' );
do_action( 'user_register', $pre['user'] );
YOWCL_Free_Core::level_bonus( $pre['user'], 'loyf_gold' );
loyf_balance( $pre['user'], 37, 37, 'Historical rewards suppressed' );
loyf_equal( 1, count( loyf_rows( $pre['user'] ) ), 'No fabricated event' );
loyf_equal( '3', get_option( 'wc_loyalty_db_version' ), 'Canonical schema witness' );
$user = wp_insert_user( array( 'user_login' => 'new_cutover', 'user_email' => 'new@example.invalid', 'user_pass' => 'disposable-only', 'role' => 'customer' ) );
wp_set_current_user( $user );
loyf_balance( $user, 5, 5, 'New signup' ); do_action( 'user_register', $user ); loyf_balance( $user, 5, 5, 'Signup once' );
$account = get_userdata( $user ); do_action( 'wp_login', $account->user_login, $account ); loyf_balance( $user, 8, 8, 'Daily login' );
do_action( 'wp_login', $account->user_login, $account ); loyf_balance( $user, 8, 8, 'Daily login once' );
$product = new WC_Product_Simple(); $product->set_name( 'Hardened product' ); $product->set_regular_price( '100' ); $product->set_virtual( true ); $product->set_status( 'publish' ); $product->save();
$comment = wp_insert_comment( array( 'comment_post_ID' => $product->get_id(), 'comment_type' => 'review', 'comment_approved' => 1, 'comment_content' => 'New review', 'user_id' => $user ) );
do_action( 'comment_post', $comment, 1 ); do_action( 'comment_post', $comment, 1 ); loyf_balance( $user, 15, 15, 'Review once' );
wp_update_comment( array( 'comment_ID' => $pre['comment'], 'comment_post_ID' => $product->get_id() ) );
do_action( 'comment_post', $pre['comment'], 1 ); loyf_balance( $pre['user'], 37, 37, 'Historical comment suppressed' );
$order = loyf_order( $user, $product ); $order->update_status( 'processing' ); loyf_balance( $user, 126, 126, 'Purchase and first level bonus' );
$order->update_status( 'completed' ); loyf_balance( $user, 126, 126, 'Order reward once' );
$order->update_status( 'cancelled' ); loyf_balance( $user, 26, 26, 'Order reversal' );
$order->update_status( 'completed' ); loyf_balance( $user, 26, 26, 'No resurrection' );
$account = get_userdata( $user ); $account->set_role( 'loyf_gold' ); loyf_balance( $user, 26, 26, 'Role lifetime once' );
$account->set_role( 'customer' );
// A legacy marker cannot mint value, with or without the old processed flag.
foreach ( array( false, true ) as $processed ) {
    $old = loyf_order( $user, $product ); $old->update_meta_data( '_used_points', 20 );
    if ( $processed ) { $old->update_meta_data( '_loyalty_points_processed', 'yes' ); }
    $old->save(); $old->update_status( 'cancelled' );
    $notes = count( wc_get_order_notes( array( 'order_id' => $old->get_id() ) ) );
    YOWCL_Order_Redemption::return_points( $old->get_id() ); loyf_balance( $user, 26, 26, 'Historical return held' );
    loyf_equal( $notes, count( wc_get_order_notes( array( 'order_id' => $old->get_id() ) ) ), 'Manual review indication once' );
    loyf_equal( 'yes', wc_get_order( $old->get_id() )->get_meta( '_yowcl_legacy_return_review' ), 'Explicit legacy hold' );
}
WC()->initialize_session(); WC()->initialize_cart(); WC()->cart->empty_cart(); WC()->cart->add_to_cart( $product->get_id(), 1 );
$nonce = wp_create_nonce( 'apply_loyalty_points' );
loyf_equal( true, loyf_ajax( 'wp_ajax_applying_points', array( 'loyalty_points_nonce' => $nonce, 'loyalty_points_input' => '20' ) )['success'], 'Native cart apply' );
loyf_balance( $user, 26, 26, 'Selection no debit' );
$checkout = WC()->checkout()->create_order( array( 'billing_email' => 'new@example.invalid', 'payment_method' => 'cod' ) );
loyf_assert( ! is_wp_error( $checkout ), 'Native checkout: ' . ( is_wp_error( $checkout ) ? $checkout->get_error_message() : '' ) );
loyf_balance( $user, 6, 26, 'Debit before gateway' );
$redeemed = wc_get_order( $checkout ); YOWCL_Order_Redemption::commit( $redeemed ); loyf_balance( $user, 6, 26, 'Debit replay' );
$redeemed->update_status( 'cancelled' ); loyf_balance( $user, 26, 26, 'Proven debit return' );
YOWCL_Order_Redemption::return_points( $checkout ); loyf_balance( $user, 26, 26, 'Return once' );
// Fractional storage is preserved and the primitive fails closed.
update_user_meta( $pre['user'], 'user_points', '37.5' );
$result = YOWCL_Points_Transaction::apply( (int) $pre['user'], 1, 1, 'fixture:fraction' );
loyf_equal( 'invalid_balance_storage', $result['code'], 'Fraction condition' );
loyf_equal( '37.5', get_user_meta( $pre['user'], 'user_points', true ), 'Exact fractional bytes' );
// Rollback boundaries use the real mysqli transaction, never a fake balance writer.
foreach ( array( 'event_inserted', 'available_written', 'balances_written' ) as $step ) {
    $failure = function ( $checkpoint ) use ( $step ) { if ( $checkpoint === $step ) { throw new RuntimeException( 'Injected failure' ); } };
    add_action( 'yowcl_transaction_test_checkpoint', $failure );
    $result = YOWCL_Points_Transaction::apply( (int) $user, 1, 1, 'fixture:rollback:' . $step );
    remove_action( 'yowcl_transaction_test_checkpoint', $failure );
    loyf_equal( 'busy', $result['status'], 'Rollback retry classification' ); loyf_balance( $user, 26, 26, 'Rollback ' . $step );
    loyf_equal( null, YOWCL_Points_Transaction::find( 'fixture:rollback:' . $step ), 'Rollback removes event' );
}
// Forbidden product surface is absent; generic primitive cannot enable its modes/actions.
foreach ( array( 'YOWCL_Premium_Gate', 'YOWCL_Campaign_Rules', 'YOWCL_Actions_Points_Expiration', 'YOWCL_Helper_Product_Earning_Rules', 'YOWCL_Referral_Rewards', 'YOWCL_Coupon_Redemption' ) as $class ) { loyf_equal( false, class_exists( $class ), 'Forbidden class ' . $class ); }
foreach ( array( 'expire', 'zero', 'reset' ) as $mode ) { loyf_equal( 'invalid_operation', YOWCL_Points_Transaction::mutate( (int) $user, 0, 0, 'fixture:' . $mode, array( 'action' => 'points_expired' ), $mode )['code'], 'Forbidden mutation mode' ); }
foreach ( array( 'loyalty_extra_points_rules', 'loyalty_extra_levelup_points_rules', 'loyalty_customization' ) as $option ) { $fixture = json_decode( file_get_contents( getenv( 'LOYF_FIXTURE' ) ), true ); if ( isset( $fixture['options'][$option] ) ) { loyf_equal( $fixture['options'][$option], get_option( $option ), 'No option migration' ); } }
file_put_contents( getenv( 'LOYF_SNAPSHOT' ), json_encode( array( 'hardened_core' => 'PASS' ) ) );
file_put_contents( getenv( 'LOYF_RAW_SNAPSHOT' ), json_encode( array( 'rows' => loyf_rows( $user ), 'cutover' => YOWCL_Free_Core::cutover() ) ) );
echo "Hardened candidate native boundaries PASS\n";

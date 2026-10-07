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
// Post-commit observers cannot undo value or turn an applied operation into failure.
$observer = function () { throw new RuntimeException( 'Observer failure' ); };
add_action( 'yowcl_points_log_created', $observer );
$result = YOWCL_Points_Transaction::apply( (int) $user, 1, 1, 'fixture:observer' );
remove_action( 'yowcl_points_log_created', $observer );
loyf_equal( 'applied', $result['status'], 'Observer cannot undo commit' ); loyf_balance( $user, 27, 27, 'Observer value committed' );
// Privileged native AJAX uses the original nonce/capability and a stable canonical UUID.
$operation = wp_generate_uuid4(); $admin_nonce = wp_create_nonce( 'ajax_nonce' );
loyf_equal( false, loyf_ajax( 'wp_ajax_reward_user_points', array( 'security' => $admin_nonce, 'user_id' => $user, 'points' => '4', 'operation_id' => $operation ) )['success'], 'Customer admin denial' );
loyf_balance( $user, 27, 27, 'Denied request no value' );
wp_set_current_user( 1 ); $admin_nonce = wp_create_nonce( 'ajax_nonce' );
$request = array( 'security' => $admin_nonce, 'user_id' => $user, 'points' => '4', 'operation_id' => $operation );
loyf_equal( true, loyf_ajax( 'wp_ajax_reward_user_points', $request )['success'], 'Authorized manual credit' );
loyf_equal( true, loyf_ajax( 'wp_ajax_reward_user_points', $request )['success'], 'Manual credit replay' );
loyf_balance( $user, 31, 31, 'Manual credit once' );
$request['points'] = '5'; loyf_equal( false, loyf_ajax( 'wp_ajax_reward_user_points', $request )['success'], 'Conflicting terms denied' ); loyf_balance( $user, 31, 31, 'Conflict no value' );
foreach ( array( '1junk', array( $user ), '-1', '0' ) as $invalid_user ) {
    $invalid_request = $request; $invalid_request['user_id'] = $invalid_user;
    loyf_equal( false, loyf_ajax( 'wp_ajax_reward_user_points', $invalid_request )['success'], 'Malformed admin target denied' );
    loyf_balance( $user, 31, 31, 'Malformed target changes no value' );
}
// Same absolute import identity never erases a later healthy credit on replay.
$import_key = 'import:1:' . wp_generate_uuid4() . ':' . $user;
loyf_equal( 'applied', YOWCL_Points_Transaction::mutate( (int) $user, 40, 40, $import_key, array( 'action' => 'points_import' ), 'replace' )['status'], 'Import target' );
YOWCL_Points_Transaction::apply( (int) $user, 5, 5, 'fixture:later-credit' );
loyf_equal( 'already_applied', YOWCL_Points_Transaction::mutate( (int) $user, 40, 40, $import_key, array( 'action' => 'points_import' ), 'replace' )['status'], 'Import replay' ); loyf_balance( $user, 45, 45, 'Replay preserves later credit' );
// Exercise the actual private CSV writer with native WP upload/type/nonce/capability APIs.
$csv_path = tempnam( sys_get_temp_dir(), 'loyf-csv-' );
$csv = "user_id,user_points,user_earning_points\n" . $user . ",50,50\n" . $pre['user'] . ",40,40\n";
file_put_contents( $csv_path, $csv );
$csv_form_id = function () {
    ob_start(); ( new YOSWC_Loyalty_Settings_Tools() )->display_tools_settings(); $html = ob_get_clean();
    loyf_assert( 1 === preg_match( '/name="operation_id" value="([a-f0-9-]+)"/', $html, $match ), 'Native CSV form operation' );
    return $match[1];
};
$csv_operation = $csv_form_id();
$run_csv = function () use ( $csv_path, $csv_operation, $csv_form_id ) {
    $rendered = $csv_form_id(); loyf_equal( $csv_operation, $rendered, 'CSV rendered recovery identity' );
    $_POST = array( 'wc_loyalty_import_nonce' => wp_create_nonce( 'wc_loyalty_import_action' ), 'operation_id' => $rendered );
    $_FILES = array( 'import_file' => array( 'tmp_name' => $csv_path, 'name' => 'fixture.csv' ) );
    $handler = new ReflectionMethod( 'YOSWC_Loyalty_Settings_Tools', 'import_csv' ); $handler->setAccessible( true );
    ob_start(); try { $handler->invoke( new YOSWC_Loyalty_Settings_Tools() ); } finally { ob_end_clean(); $_POST = array(); $_FILES = array(); }
};
$run_csv(); loyf_balance( $user, 50, 50, 'CSV applies valid target' ); loyf_equal( '37.5', get_user_meta( $pre['user'], 'user_points', true ), 'CSV cannot normalize fractional storage' );
YOWCL_Points_Transaction::apply( (int) $user, 5, 5, 'fixture:csv-later-credit' );
$run_csv(); loyf_balance( $user, 55, 55, 'CSV rendered retry preserves later credit' );
$_POST = array( 'start_new_import' => 1, 'previous_operation_id' => $csv_operation, 'loyf_start_new_import_nonce' => wp_create_nonce( 'loyf_start_new_import' ) );
$next_import = $csv_form_id(); $_POST = array();
loyf_assert( $next_import !== $csv_operation, 'Explicit new operation is separate' );
loyf_equal( hash( 'sha256', $csv ), get_option( 'loyf_import_1_' . $csv_operation ), 'Original immutable import witness retained' );
loyf_assert( YOWCL_Points_Transaction::find( 'import:1:' . $csv_operation . ':' . $user ), 'Original import event retained' );
loyf_balance( $user, 55, 55, 'New-operation rendering does not mutate value' ); unlink( $csv_path );
// Contention retains the producer's application retry with frozen terms.
$rules = get_option( 'loyalty_extra_points_rules' ); $off = $rules; $off['signup_points'] = 0; update_option( 'loyalty_extra_points_rules', $off );
$retry_user = wp_insert_user( array( 'user_login' => 'retry_user', 'user_email' => 'retry@example.invalid', 'user_pass' => 'disposable-only', 'role' => 'customer' ) );
update_option( 'loyalty_extra_points_rules', $rules );
$held = YOWCL_Points_Lock::acquire( (int) $retry_user ); do_action( 'user_register', $retry_user ); YOWCL_Points_Lock::release( $held );
$pending = as_get_scheduled_actions( array( 'hook' => YOWCL_Core_Rewards::RETRY_HOOK, 'status' => 'pending', 'per_page' => 20 ), 'ids' );
loyf_assert( count( $pending ) > 0, 'Retained native AS delivery' );
update_option( 'loyalty_extra_points_rules', $off );
foreach ( $pending as $action_id ) { $action = ActionScheduler::store()->fetch_action( $action_id ); do_action( $action->get_hook(), ...$action->get_args() ); }
loyf_balance( $retry_user, 5, 5, 'Frozen signup recovered under disabled config' ); update_option( 'loyalty_extra_points_rules', $rules );
// Independent PHP/WP processes prime caches, contend under the native lock, then converge.
foreach ( array( true, false ) as $same ) {
    $worker_user = wp_insert_user( array( 'user_login' => 'worker_' . ( $same ? 'same' : 'distinct' ), 'user_email' => ( $same ? 'same' : 'distinct' ) . '@example.invalid', 'user_pass' => 'disposable-only', 'role' => 'customer' ) );
    YOWCL_Points_Transaction::apply( (int) $worker_user, 35, 35, 'fixture:worker-seed:' . $worker_user );
    $barrier = tempnam( sys_get_temp_dir(), 'loyf-worker-' ); unlink( $barrier ); $workers = array();
    foreach ( array( 0, 1 ) as $index ) {
        $env = getenv(); $env['LOYF_WORKER_USER'] = (string) $worker_user; $env['LOYF_WORKER_KEY'] = 'fixture:concurrent:' . $worker_user . ':' . ( $same ? 0 : $index ); $env['LOYF_WORKER_BARRIER'] = $barrier; $env['LOYF_WORKER_INDEX'] = (string) $index;
        $pipes = array(); $process = proc_open( array( PHP_BINARY, getenv( 'LOYF_WP_CLI_PHAR' ), '--path=' . ABSPATH, 'eval-file', __DIR__ . '/worker.php', '--quiet' ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes, null, $env );
        fclose( $pipes[0] ); $workers[] = array( $process, $pipes );
    }
    $statuses = array();
    foreach ( $workers as $worker ) { $out = stream_get_contents( $worker[1][1] ); $err = stream_get_contents( $worker[1][2] ); fclose( $worker[1][1] ); fclose( $worker[1][2] ); loyf_equal( 0, proc_close( $worker[0] ), 'Worker exit: ' . $err ); $decoded = json_decode( $out, true ); loyf_assert( is_array( $decoded ), 'Worker JSON: ' . $out . $err ); $statuses[] = $decoded['status']; }
    foreach ( array( 0, 1 ) as $index ) { if ( file_exists( $barrier . '.' . $index ) ) { unlink( $barrier . '.' . $index ); } }
    sort( $statuses ); $expected = $same ? array( 'already_applied', 'applied' ) : array( 'applied', 'insufficient_balance' ); sort( $expected ); loyf_equal( $expected, $statuses, 'Independent concurrency outcome' );
    wp_cache_delete( $worker_user, 'user_meta' ); loyf_balance( $worker_user, 10, 40, 'Concurrent strict debit' );
}
require __DIR__ . '/cutover-boundaries.php';
// Forbidden product surface is absent; generic primitive cannot enable its modes/actions.
foreach ( array( 'YOWCL_Premium_Gate', 'YOWCL_Campaign_Rules', 'YOWCL_Actions_Points_Expiration', 'YOWCL_Helper_Product_Earning_Rules', 'YOWCL_Referral_Rewards', 'YOWCL_Coupon_Redemption' ) as $class ) { loyf_equal( false, class_exists( $class ), 'Forbidden class ' . $class ); }
foreach ( array( 'expire', 'zero', 'reset' ) as $mode ) { loyf_equal( 'invalid_operation', YOWCL_Points_Transaction::mutate( (int) $user, 0, 0, 'fixture:' . $mode, array( 'action' => 'points_expired' ), $mode )['code'], 'Forbidden mutation mode' ); }
foreach ( array( 'loyalty_extra_levelup_points_rules', 'loyalty_customization' ) as $option ) { $fixture = json_decode( file_get_contents( getenv( 'LOYF_FIXTURE' ) ), true ); if ( isset( $fixture['options'][$option] ) ) { loyf_equal( $fixture['options'][$option], get_option( $option ), 'No option migration' ); } }
foreach (array('signup_points','login_points','review_points') as $key) { loyf_equal($fixture['options']['loyalty_extra_points_rules'][$key], get_option('loyalty_extra_points_rules')[$key], 'Legacy account terms preserved'); }
file_put_contents( getenv( 'LOYF_SNAPSHOT' ), json_encode( array( 'hardened_core' => 'PASS' ) ) );
file_put_contents( getenv( 'LOYF_RAW_SNAPSHOT' ), json_encode( array( 'rows' => loyf_rows( $user ), 'cutover' => YOWCL_Free_Core::cutover() ) ) );
echo "Hardened candidate native boundaries PASS\n";

<?php
// Native compatibility holds must preserve unrelated roles and dormant value evidence.
$role_user = get_userdata( $user ); $role_user->add_role( 'subscriber' ); $role_user->add_role( 'loyf_gold' );
update_user_meta( $user, YOWCL_Helper_Roles::LOYALTY_LEVEL_META, 'loyf_gold' );
loyf_equal( 'loyf_gold', YOWCL_Helper_Roles::get_user_loyalty_role_for_rules( $user ), 'Canonical level among unrelated roles' );
loyf_assert( in_array( 'subscriber', get_userdata( $user )->roles, true ), 'Unrelated access preserved' );
$role_user->remove_role( 'loyf_gold' ); update_user_meta( $user, YOWCL_Helper_Roles::LOYALTY_LEVEL_META, 'customer' );
$active = get_option( 'active_plugins' ); // Prime stale option cache, then change durable ownership.
$foreign = $active; $foreign[] = 'wc-loyalty/wc-loyalty.php';
$wpdb->update( $wpdb->options, array( 'option_value' => maybe_serialize( $foreign ) ), array( 'option_name' => 'active_plugins' ) );
loyf_equal( false, YOWCL_Free_Core::owns(), 'Durable Premium ownership defeats stale cache' );
$before_owner = get_user_meta( $user, 'user_points', true );
YOWCL_Free_Core::user_reward( $user, 'reward:daily_login:' . $user . ':2099-01-01', 'daily_login_reward', 9 );
loyf_equal( $before_owner, get_user_meta( $user, 'user_points', true ), 'Inactive Free producer no value' );
$wpdb->update( $wpdb->options, array( 'option_value' => maybe_serialize( $active ) ), array( 'option_name' => 'active_plugins' ) );
loyf_equal( true, YOWCL_Free_Core::owns(), 'Ownership restored' );
$uuid = wp_generate_uuid4(); $dormant = wp_json_encode( array( 'state' => 'active', 'order_id' => $checkout, 'terms' => array( 'product' => 10, 'coupon' => '', 'items' => array() ) ) );
add_option( 'yowcl_order_redemption_' . $uuid, $dormant, '', false );
try { YOWCL_Order_Redemption::record( $uuid ); throw new RuntimeException( 'Dormant product record accepted' ); }
catch ( RuntimeException $error ) { loyf_equal( 'unsupported_redemption_review_required', $error->getMessage(), 'Dormant mode held' ); }
loyf_equal( $dormant, get_option( 'yowcl_order_redemption_' . $uuid ), 'Dormant record bytes preserved' );
$ordinary = loyf_order( $user, $product );
WC()->session->set( 'yoswc_loyalty_applied_points', 0 ); YOWCL_Free_Core::deny_store_api_redemption( $ordinary );
WC()->session->set( 'yoswc_loyalty_applied_points', 1 );
try { YOWCL_Free_Core::deny_store_api_redemption( $ordinary ); throw new RuntimeException( 'Unsupported points checkout accepted' ); }
catch ( \Automattic\WooCommerce\StoreApi\Exceptions\RouteException $error ) { loyf_equal( 400, $error->getCode(), 'Store API redemption safety hold' ); }
WC()->session->set( 'yoswc_loyalty_applied_points', 0 );
// Existing public role controller denies unprivileged writes and never deletes legacy/shared access.
$controller = new YOSWC_Loyalty_Settings_Add_Remove_User_Role();
wp_set_current_user( $user ); $_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array( 'add_new_role_submit' => 1, 'new_role_name' => 'Denied', 'new_role_slug' => 'loyf_denied', 'add_new_role_nonce' => wp_create_nonce( 'add_new_role_action' ) );
$controller->handle_role_actions(); loyf_equal( null, get_role( 'loyf_denied' ), 'Role capability guard' );
wp_set_current_user( 1 ); $_POST = array( 'add_new_role_submit' => 1, 'new_role_name' => 'Owned', 'new_role_slug' => 'loyf_owned', 'add_new_role_nonce' => wp_create_nonce( 'add_new_role_action' ) );
$controller->handle_role_actions(); loyf_assert( YOSWC_Role_Ownership::owns( 'wc-loyalty', 'loyf_owned' ), 'New role canonical ownership' );
foreach ( array( 'customer', 'loyf_gold', 'loyf_owned' ) as $slug ) {
    $_POST = array( 'remove_role_submit' => 1, 'role_to_remove' => $slug, 'remove_role_nonce' => wp_create_nonce( 'remove_role_action' ) );
    $controller->handle_role_actions(); loyf_assert( get_role( $slug ) instanceof WP_Role, 'Role/access retained ' . $slug );
}
loyf_equal( null, YOSWC_Role_Ownership::record( 'loyf_gold' ), 'No historical creator backfill' );
loyf_equal( 'retired', YOSWC_Role_Ownership::record( 'loyf_owned' )['state'], 'Owned role retirement' );
$_POST = array(); unset( $_SERVER['REQUEST_METHOD'] );

// A native legacy cancellation must inspect every stored balance row before any write.
foreach ( array( 'user_points', 'user_earning_points' ) as $key ) {
    $duplicate_user = wp_insert_user( array( 'user_login' => 'duplicate_' . $key, 'user_email' => $key . '@example.invalid', 'user_pass' => 'disposable-only', 'role' => 'customer' ) );
    update_user_meta( $duplicate_user, 'user_points', '37' ); update_user_meta( $duplicate_user, 'user_earning_points', '37' );
    add_user_meta( $duplicate_user, $key, '37.5' );
    $before = get_user_meta( $duplicate_user, $key, false ); $before_rows = loyf_rows( $duplicate_user );
    $historical = loyf_order( $duplicate_user, $product ); $historical->update_meta_data( '_points_awarded', 10 ); $historical->save();
    $historical->update_status( 'cancelled' );
    wp_cache_delete( $duplicate_user, 'user_meta' );
    loyf_equal( $before, get_user_meta( $duplicate_user, $key, false ), 'Historical cancellation preserves duplicate fractional ' . $key );
    loyf_equal( $before_rows, loyf_rows( $duplicate_user ), 'Held cancellation creates no history' );
    loyf_equal( '', wc_get_order( $historical->get_id() )->get_meta( '_points_deducted' ), 'Held cancellation creates no marker' );
    loyf_equal( 'invalid_balance_storage', get_user_meta( $duplicate_user, '_loyf_economic_hold', true ), 'Cancellation diagnostic' );
}
// Native guest/missing-user product readers preserve the Customer rule without PHP warnings.
wp_set_current_user( 0 );
set_error_handler( function ( $severity, $message ) { throw new RuntimeException( $message ); }, E_WARNING );
try {
    foreach ( array( 'YOSWC_Loyalty_Product_Message_Earning_Points', 'YOSWC_Loyalty_Shop_Message_Earning_Points' ) as $class ) {
        $reader = new ReflectionMethod( $class, 'calculate_earning_points' ); $reader->setAccessible( true );
        foreach ( array( 0, 999999999 ) as $missing ) { loyf_assert( $reader->invoke( new $class(), $product, $missing ) >= 0, 'Native guest/missing-user calculation ' . $class ); }
    }
    $cart_reader = new ReflectionMethod( 'YOSWC_Loyalty_Using_Point_Cart_Checkout', 'calculate_potential_earned_points' ); $cart_reader->setAccessible( true );
    loyf_assert( $cart_reader->invoke( new YOSWC_Loyalty_Using_Point_Cart_Checkout(), 0 ) >= 0, 'Guest cart calculation' );
} finally { restore_error_handler(); wp_set_current_user( 1 ); }
// A funded pending Classic attempt remains retryable at zero balance until gateway completion.
$pending_user = wp_insert_user( array( 'user_login' => 'pending_retry', 'user_email' => 'pending@example.invalid', 'user_pass' => 'disposable-only', 'role' => 'customer' ) );
YOWCL_Points_Transaction::apply( (int) $pending_user, 20, 20, 'fixture:pending-seed' );
wp_set_current_user( $pending_user ); WC()->cart->empty_cart(); WC()->cart->add_to_cart( $product->get_id(), 1 );
loyf_equal( true, loyf_ajax( 'wp_ajax_applying_points', array( 'loyalty_points_nonce' => wp_create_nonce( 'apply_loyalty_points' ), 'loyalty_points_input' => '25' ) )['success'], 'Pending retry selection' );
$pending_id = WC()->session->get( 'yowcl_checkout_id' );
$_POST['yowcl_checkout_id'] = $pending_id;
$pending_checkout = WC()->checkout()->create_order( array( 'billing_email' => 'pending@example.invalid', 'payment_method' => 'cod' ) );
loyf_assert( ! is_wp_error( $pending_checkout ), 'Pending native order' );
loyf_balance( $pending_user, 0, 25, 'Pending fully debited' );
loyf_assert( is_array( WC()->session->get( 'loyf_funded_selection' ) ), 'Pending selection retained' );
$pending_hash = WC()->cart->get_cart_hash(); WC()->cart->calculate_totals();
loyf_equal( $pending_hash, WC()->cart->get_cart_hash(), 'Pending zero-balance totals preserve original terms' );
$pending_retry = WC()->checkout()->create_order( array( 'billing_email' => 'pending@example.invalid', 'payment_method' => 'cod' ) );
loyf_equal( $pending_checkout, $pending_retry, 'Native same-identity pending retry reuses order' );
loyf_balance( $pending_user, 0, 25, 'Pending retry does not debit twice' );
$earning_statuses = get_option( 'loyalty_points_earning_status' ); update_option( 'loyalty_points_earning_status', array() );
$cod = WC()->payment_gateways()->payment_gateways()['cod']; $payment = $cod->process_payment( $pending_checkout );
loyf_equal( 'success', $payment['result'], 'Native COD completion' );
loyf_equal( null, WC()->session->get( 'loyf_funded_selection' ), 'Gateway completion retires selection' );
loyf_balance( $pending_user, 0, 25, 'Gateway does not duplicate debit' );
WC()->cart->add_to_cart( $product->get_id(), 1 ); WC()->cart->calculate_totals();
loyf_equal( 0, count( WC()->cart->get_fees() ), 'Next identical cart has no stale points fee' );
unset( $_POST['yowcl_checkout_id'] );
$ordinary_next = WC()->checkout()->create_order( array( 'billing_email' => 'pending@example.invalid', 'payment_method' => 'cod' ) );
loyf_assert( ! is_wp_error( $ordinary_next ) && $ordinary_next !== $pending_checkout, 'Next ordinary native order is distinct' );
loyf_equal( '', wc_get_order( $ordinary_next )->get_meta( '_used_points' ), 'Next ordinary order has no stale redemption' );
loyf_balance( $pending_user, 0, 25, 'Next ordinary order creates no points debit' );
update_option( 'loyalty_points_earning_status', $earning_statuses ); wp_set_current_user( 1 );

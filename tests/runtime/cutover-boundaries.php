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

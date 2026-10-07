<?php
// Execute native WP/Woo entry points. Expectations describe legacy behavior, not desired vNext semantics.
if (!defined('LOYF_RUNTIME_DISPOSABLE') || !LOYF_RUNTIME_DISPOSABLE) {
    throw new RuntimeException('Disposable runtime required.');
}
function loyf_assert($condition, $message) {
    if (!$condition) { throw new RuntimeException($message); }
}
function loyf_equal($expected, $actual, $message) {
    loyf_assert($expected === $actual, $message . ': expected ' . json_encode($expected) . ', got ' . json_encode($actual));
}
function loyf_balance($id, $available, $earning, $label) {
    loyf_equal((float) $available, (float) get_user_meta($id, 'user_points', true), $label . ' available');
    loyf_equal((float) $earning, (float) get_user_meta($id, 'user_earning_points', true), $label . ' earning');
}
function loyf_rows($id) {
    global $wpdb;
    return $wpdb->get_results($wpdb->prepare('SELECT * FROM %i WHERE user_id=%d ORDER BY id', YOSWC_Loyalty_Database::get_table_name(), $id), ARRAY_A);
}
function loyf_order($id, $product) {
    $order = wc_create_order(array('customer_id' => $id));
    $order->add_product($product, 1);
    $order->calculate_totals();
    $order->save();
    return $order;
}
class LOYF_Test_Die extends RuntimeException {}
function loyf_ajax($hook, $fields) {
    $_POST = $fields;
    $die = function () { return function () { throw new LOYF_Test_Die(); }; };
    add_filter('wp_die_handler', $die, PHP_INT_MAX);
    add_filter('wp_die_ajax_handler', $die, PHP_INT_MAX);
    ob_start();
    try { do_action($hook); } catch (LOYF_Test_Die $e) { /* Capture native JSON termination. */ }
    finally {
        $json = ob_get_clean();
        remove_filter('wp_die_handler', $die, PHP_INT_MAX);
        remove_filter('wp_die_ajax_handler', $die, PHP_INT_MAX);
        $_POST = array();
    }
    $result = json_decode($json, true);
    loyf_assert(is_array($result) && isset($result['success']), 'Native AJAX response: ' . $json);
    return $result;
}
$fixture = json_decode(file_get_contents(getenv('LOYF_FIXTURE')), true);
loyf_equal($fixture['version'], YOSWC_LOYALTY_VERSION, 'Free version');
loyf_assert(class_exists('YOSWC_Loyalty_Database'), 'Native Free boot');
$settings = new YOSWC_Loyalty_Settings();
$filter_calls = array();
foreach (array('yoswc_loyalty_is_premium', 'yoswc_loyalty_premium_url', 'yoswc_loyalty_premium_docs_url') as $filter) {
    $callback = function ($value) use (&$filter_calls, $filter) {
        $filter_calls[$filter] = $value;
        return $filter === 'yoswc_loyalty_is_premium' ? false : 'https://example.invalid/' . $filter;
    };
    add_filter($filter, $callback);
    $filter_callbacks[$filter] = $callback;
}
ob_start();
$settings->output_premium_settings();
$premium_html = ob_get_clean();
loyf_equal(false, $filter_calls['yoswc_loyalty_is_premium'], 'Free entitlement filter default');
foreach (array('yoswc_loyalty_premium_url', 'yoswc_loyalty_premium_docs_url') as $filter) {
    loyf_assert(strpos($filter_calls[$filter], 'https://yoohw.com/') === 0, 'Legacy public URL default');
    loyf_assert(strpos($premium_html, 'https://example.invalid/' . $filter) !== false, 'Production filter consumer');
}
foreach ($filter_callbacks as $filter => $callback) { remove_filter($filter, $callback); }
$events = array();
foreach (array('yoswc_loyalty_points_reward', 'yoswc_loyalty_points_deduct', 'yoswc_loyalty_level_update') as $hook) {
    add_action($hook, function (...$args) use (&$events, $hook) { $events[] = array('hook' => $hook, 'args' => $args); }, PHP_INT_MAX, 4);
}
$GLOBALS['loyf_mail'] = array();
$id = wp_insert_user(array('user_login' => 'loyf_customer', 'user_email' => 'fixture@example.invalid', 'user_pass' => 'disposable-only', 'role' => 'customer', 'first_name' => 'Fixture'));
loyf_assert(!is_wp_error($id), 'Customer creation');
wp_set_current_user($id);
loyf_balance($id, 5, 5, 'Native signup');
// Signup has no replay identity in 1.2.2; freeze this limitation explicitly.
do_action('user_register', $id);
loyf_balance($id, 10, 10, 'Signup replay legacy double credit');
$user = get_userdata($id);
do_action('wp_login', $user->user_login, $user);
loyf_balance($id, 13, 13, 'Login');
loyf_equal(current_time('Y-m-d'), get_user_meta($id, 'loyalty_last_daily_login', true), 'Daily witness');
do_action('wp_login', $user->user_login, $user);
loyf_balance($id, 13, 13, 'Same-day login replay');
update_user_meta($id, 'loyalty_last_daily_login', '2000-01-01');
do_action('wp_login', $user->user_login, $user);
loyf_balance($id, 16, 16, 'Later-day login');
$product = new WC_Product_Simple();
$product->set_name('Characterization product');
$product->set_regular_price('100');
$product->set_virtual(true);
$product->set_status('publish');
$product->save();
$comment = wp_insert_comment(array('comment_post_ID' => $product->get_id(), 'comment_type' => 'review', 'comment_approved' => 1, 'comment_content' => 'Fixture review', 'user_id' => $id));
// wp_insert_comment dispatches wp_insert_comment, not comment_post. Use the native review submission event.
do_action('comment_post', $comment, 0);
loyf_balance($id, 16, 16, 'Unapproved review');
do_action('comment_post', $comment, 1);
loyf_balance($id, 23, 23, 'Review');
do_action('comment_post', $comment, 1);
loyf_balance($id, 30, 30, 'Review replay legacy double credit');
$orders = array();
$states = array();
foreach (array('cancelled', 'refunded', 'failed') as $terminal) {
    $before = (float) get_user_meta($id, 'user_points', true);
    $before_earning = (float) get_user_meta($id, 'user_earning_points', true);
    $order = loyf_order($id, $product);
    $orders[$terminal] = $order->get_id();
    $order->update_status('processing');
    $bonus = 11; // Every customer→Gold transition awards again in legacy Free.
    // Status handler overwrites earning after level bonus only through the role hook; read persisted values.
    loyf_balance($id, $before + 100 + $bonus, $before_earning + 100 + $bonus, 'Purchase ' . $terminal);
    loyf_equal('100', (string) get_post_meta($order->get_id(), '_points_awarded', true), 'Award marker');
    loyf_equal(array('loyf_gold'), array_values(get_userdata($id)->roles), 'Gold role');
    $count = count(loyf_rows($id));
    $order->update_status('completed');
    loyf_equal($count, count(loyf_rows($id)), 'Processing/completed replay');
    $order->update_status($terminal);
    loyf_balance($id, $before + $bonus, $before_earning + $bonus, 'Deduction ' . $terminal);
    loyf_equal('100', (string) get_post_meta($order->get_id(), '_points_deducted', true), 'Deduction marker');
    loyf_equal(array('customer'), array_values(get_userdata($id)->roles), 'Customer role restored');
    $count = count(loyf_rows($id));
    do_action('woocommerce_order_status_changed', $order->get_id(), 'completed', $terminal, $order);
    loyf_equal($count, count(loyf_rows($id)), 'Deduction replay');
    $order->update_status('completed');
    loyf_equal($count, count(loyf_rows($id)), 'Award marker retained after cancellation/recompletion');
    $states[$terminal] = array('available' => (float) get_user_meta($id, 'user_points', true), 'earning' => (float) get_user_meta($id, 'user_earning_points', true));
}
// Explicit role transitions: no per-level lifetime marker; leaving/re-entering re-awards.
$user = get_userdata($id);
$user->set_role('loyf_gold');
loyf_balance($id, 74, 74, 'Explicit level up');
$user->set_role('loyf_gold');
loyf_balance($id, 74, 74, 'Unchanged native role no callback');
$user->set_role('customer');
$user->set_role('loyf_gold');
loyf_balance($id, 85, 85, 'Level re-entry legacy double credit');
// Native Woo session/cart, registered AJAX, fee calculation, Classic checkout creation and thankyou.
WC()->initialize_session();
WC()->initialize_cart();
WC()->cart->empty_cart();
WC()->cart->add_to_cart($product->get_id(), 1);
WC()->cart->calculate_totals();
$nonce = wp_create_nonce('apply_loyalty_points');
$ajax = loyf_ajax('wp_ajax_applying_points', array('loyalty_points_nonce' => 'invalid', 'loyalty_points_input' => 20));
loyf_equal(false, $ajax['success'], 'Denied AJAX nonce');
$ajax = loyf_ajax('wp_ajax_applying_points', array('loyalty_points_nonce' => $nonce, 'loyalty_points_input' => 999));
loyf_equal(false, $ajax['success'], 'Insufficient AJAX amount');
$ajax = loyf_ajax('wp_ajax_applying_points', array('loyalty_points_nonce' => $nonce, 'loyalty_points_input' => 20));
loyf_equal(true, $ajax['success'], 'Apply AJAX');
loyf_equal(20.0, (float) WC()->session->get('yoswc_loyalty_applied_points'), 'Session selection');
loyf_equal(2.0, (float) WC()->session->get('yoswc_loyalty_discount_amount'), 'Session discount');
loyf_balance($id, 85, 85, 'Selection does not debit');
$fees = array_values(WC()->cart->get_fees());
loyf_equal(1, count($fees), 'Single discount fee');
loyf_equal(-2.0, (float) $fees[0]->amount, 'Discount fee amount');
loyf_equal(98.0, (float) WC()->cart->get_total('edit'), 'Native cart total');
$ajax = loyf_ajax('wp_ajax_delete_loyalty_coupon', array('loyalty_points_nonce' => $nonce));
loyf_equal(true, $ajax['success'], 'Remove AJAX');
loyf_equal(0.0, (float) WC()->session->get('yoswc_loyalty_applied_points'), 'Remove selection');
loyf_equal(0, count(WC()->cart->get_fees()), 'Remove fee');
loyf_balance($id, 85, 85, 'Remove does not debit');
loyf_ajax('wp_ajax_applying_points', array('loyalty_points_nonce' => $nonce, 'loyalty_points_input' => 20));
$order_id = WC()->checkout()->create_order(array('billing_email' => 'fixture@example.invalid', 'payment_method' => 'cod'));
loyf_assert(!is_wp_error($order_id) && $order_id > 0, 'Native checkout order');
$orders['redemption'] = $order_id;
$order = wc_get_order($order_id);
loyf_equal(20.0, (float) $order->get_meta('_used_points'), 'Checkout used marker');
loyf_equal(2.0, (float) $order->get_meta('_used_points_discount'), 'Checkout discount marker');
loyf_balance($id, 85, 85, 'Checkout creation does not debit');
ob_start();
do_action('woocommerce_thankyou', $order_id);
ob_end_clean();
loyf_balance($id, 65, 85, 'Thankyou debit');
$order = wc_get_order($order_id);
loyf_equal('yes', $order->get_meta('_loyalty_points_processed'), 'Processed marker');
loyf_equal(0.0, (float) WC()->session->get('yoswc_loyalty_applied_points'), 'Debit clears session');
$count = count(loyf_rows($id));
ob_start();
do_action('woocommerce_thankyou', $order_id);
ob_end_clean();
loyf_balance($id, 65, 85, 'Thankyou replay');
loyf_equal($count, count(loyf_rows($id)), 'Debit log once');
$order->update_status('cancelled');
loyf_balance($id, 85, 85, 'Redemption return');
$order = wc_get_order($order_id);
loyf_equal('', $order->get_meta('_used_points'), 'Return deletes used marker');
loyf_equal('', $order->get_meta('_loyalty_points_processed'), 'Return deletes processed marker');
loyf_equal(2.0, (float) $order->get_meta('_used_points_discount'), 'Return retains discount marker');
do_action('woocommerce_order_status_refunded', $order_id);
loyf_balance($id, 85, 85, 'Return replay');
// Reader contracts and exact historical rows/IDs survive schema rerun.
$rows = loyf_rows($id);
loyf_equal(19, count($rows), 'Legacy history row count');
$before_rows = $rows;
new YOSWC_Loyalty_Database();
loyf_equal($before_rows, loyf_rows($id), 'Schema rerun preserves history bytes/IDs');
$reader = new YOSWC_Loyalty_My_Account_My_Points();
loyf_equal(count($rows), count($reader->get_user_points_log($id)), 'My Account history reader');
$menu = apply_filters('woocommerce_account_menu_items', array('dashboard' => 'Dashboard', 'orders' => 'Orders', 'customer-logout' => 'Logout'));
loyf_equal('Fixture Rewards', $menu['fixture-rewards'], 'Custom My Account slug and label');
foreach ($fixture['options'] as $key => $value) {
    loyf_equal($value, get_option($key), 'Option shape preserved: ' . $key);
}
$emails = array();
foreach ($GLOBALS['loyf_mail'] as $mail) {
    // Woo order emails are not Loyalty compatibility evidence.
    if (in_array($mail['subject'], array('You have earned new points!', 'You have deducted points!', 'Your loyalty level has been updated'), true)) {
        loyf_assert(strpos($mail['message'], 'fixture-rewards') !== false, 'Legacy email custom slug');
        loyf_equal('fixture@example.invalid', $mail['to'], 'Legacy email recipient');
        $emails[] = $mail['subject'];
    }
}
loyf_equal(23, count($events), 'Public event observations');
loyf_equal(23, count($emails), 'Legacy email hook delivery');
$markers = array();
foreach ($orders as $label => $order_id) {
    $order = wc_get_order($order_id);
    foreach (array('_points_awarded', '_points_deducted', '_used_points', '_used_points_discount', '_loyalty_points_processed') as $key) {
        $markers[$label][$key] = (string) $order->get_meta($key);
    }
}
$raw = array('fixture' => $fixture, 'user_id' => $id, 'product_id' => $product->get_id(), 'comment_id' => $comment, 'orders' => $orders, 'rows' => $rows, 'markers' => $markers, 'meta' => get_user_meta($id), 'events' => $events);
loyf_assert(file_put_contents(getenv('LOYF_RAW_SNAPSHOT'), wp_json_encode($raw, JSON_PRETTY_PRINT)) !== false, 'Raw fixture export');
$order_labels = array_flip($orders);
foreach ($rows as $index => &$row) {
    $row['id'] = $index + 1;
    $row['user_id'] = 'customer';
    $row['order_id'] = isset($order_labels[$row['order_id']]) ? $order_labels[$row['order_id']] : 'none';
    $row['date'] = '<runtime-date>';
}
unset($row);
foreach ($events as &$event) {
    $event['args'][0] = 'customer';
    if (count($event['args']) === 4 && $event['args'][3]) { $event['args'][3] = $order_labels[$event['args'][3]]; }
}
unset($event);
$snapshot = array('states' => $states, 'balance' => 85, 'earning' => 85, 'roles' => array_values(get_userdata($id)->roles), 'rows' => $rows, 'markers' => $markers, 'options' => $fixture['options'], 'events' => $events, 'emails' => $emails, 'menu' => $menu);
loyf_assert(file_put_contents(getenv('LOYF_SNAPSHOT'), wp_json_encode($snapshot, JSON_PRETTY_PRINT)) !== false, 'Normalized export');
echo "FREE-1.2.2 native characterization PASS rows=" . count($rows) . " events=" . count($events) . "\n";

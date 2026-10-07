<?php
if (!defined('LOYF_RUNTIME_DISPOSABLE') || !LOYF_RUNTIME_DISPOSABLE) {
    throw new RuntimeException('Disposable runtime required.');
}
$fixture = json_decode(file_get_contents(getenv('LOYF_FIXTURE')), true);
if (!$fixture || empty($fixture['options'])) {
    throw new RuntimeException('Invalid Free fixture.');
}
update_option('woocommerce_custom_orders_table_enabled', 'no');
update_option('woocommerce_custom_orders_table_data_sync_enabled', 'no');
update_option('woocommerce_currency', 'USD');
update_option('woocommerce_calc_taxes', 'no');
update_option('timezone_string', 'UTC');
foreach ($fixture['options'] as $key => $value) {
    update_option($key, $value);
}
add_role('loyf_gold', 'Fixture Gold', array('read' => true));

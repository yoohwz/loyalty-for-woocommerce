<?php
defined( 'ABSPATH' ) || exit;
class YOSWC_Loyalty_Database {
    public function __construct() { if ( class_exists( 'YOWCL_Database' ) ) { ( new YOWCL_Database() )->check_version(); } }
    public static function get_table_name() { return YOWCL_Points_Log::table_name(); }
    public static function insert_points_log( $args ) {
        global $wpdb;

        $data = wp_parse_args(
            $args,
            array(
                'user_id'     => 0,
                'action'      => '',
                'order_id'    => 0,
                'amount'      => 0,
                'description' => '',
                'date'        => current_time( 'mysql' ),
            )
        );

        $data = array(
            'user_id'     => absint( $data['user_id'] ),
            'action'      => sanitize_key( $data['action'] ),
            'order_id'    => absint( $data['order_id'] ),
            'amount'      => (float) $data['amount'],
            'description' => sanitize_text_field( $data['description'] ),
            'date'        => sanitize_text_field( $data['date'] ),
        );

        if ( empty( $data['user_id'] ) || '' === $data['action'] ) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- This plugin owns the custom loyalty log table and uses explicit formats.
        $inserted = YOWCL_Points_Log::insert(
            self::get_table_name(),
            $data,
            array( '%d', '%s', '%d', '%f', '%s', '%s' )
        );

        if ( function_exists( 'wp_cache_set_last_changed' ) ) {
            wp_cache_set_last_changed( 'yoswc_loyalty_points' );
        }

        return $inserted;
    }

    public static function get_points_log( $user_id, $offset = null, $limit = null, $output = ARRAY_A ) {
        global $wpdb;
        $user_id = absint( $user_id );
        if ( ! $user_id ) { return array(); }
        $sql = $wpdb->prepare( 'SELECT * FROM ' . self::get_table_name() . ' WHERE user_id = %d ORDER BY date DESC, id DESC', $user_id );
        if ( null !== $offset && null !== $limit ) { $sql .= $wpdb->prepare( ' LIMIT %d, %d', absint( $offset ), max( 1, absint( $limit ) ) ); }
        return $wpdb->get_results( $sql, $output ) ?: array();
    }
}

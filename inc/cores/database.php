<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Database {
    private $table_name;

    public function __construct() {
        global $wpdb;
        $this->table_name = self::get_table_name();
        $this->create_table();
    }

    public static function get_table_name() {
        global $wpdb;

        return $wpdb->prefix . 'yo_loyalty_points_log';
    }

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
        $inserted = $wpdb->insert(
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
        if ( ! $user_id ) {
            return array();
        }

        $has_pagination = null !== $offset && null !== $limit;
        $offset         = $has_pagination ? absint( $offset ) : null;
        $limit          = $has_pagination ? max( 1, absint( $limit ) ) : null;
        $last_changed   = function_exists( 'wp_cache_get_last_changed' ) ? wp_cache_get_last_changed( 'yoswc_loyalty_points' ) : '1';
        $cache_key      = 'points_log_' . $user_id . '_' . ( $has_pagination ? $offset . '_' . $limit : 'all' ) . '_' . ( ARRAY_A === $output ? 'array' : 'object' ) . '_' . $last_changed;
        $cached_results = wp_cache_get( $cache_key, 'yoswc_loyalty_points' );

        if ( false !== $cached_results ) {
            return $cached_results;
        }

        if ( $has_pagination ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- This plugin owns the custom loyalty log table; the query is prepared and cached.
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT * FROM %i WHERE user_id = %d ORDER BY date DESC LIMIT %d, %d',
                    self::get_table_name(),
                    $user_id,
                    $offset,
                    $limit
                ),
                $output
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- This plugin owns the custom loyalty log table; the query is prepared and cached.
            $results = $wpdb->get_results(
                $wpdb->prepare(
                    'SELECT * FROM %i WHERE user_id = %d ORDER BY date DESC',
                    self::get_table_name(),
                    $user_id
                ),
                $output
            );
        }

        if ( ! is_array( $results ) ) {
            $results = array();
        }

        wp_cache_set( $cache_key, $results, 'yoswc_loyalty_points', HOUR_IN_SECONDS );

        return $results;
    }

    private function create_table() {
        global $wpdb;
    
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$this->table_name} (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            action varchar(255) NOT NULL,
            order_id bigint(20) NOT NULL,
            amount decimal(10,2) NOT NULL,
            description text NOT NULL,
            date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY  (id)
        ) $charset_collate;";
    
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }    
}

new YOSWC_Loyalty_Database();

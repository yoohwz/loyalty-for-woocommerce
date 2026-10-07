<?php

defined('ABSPATH') || exit;

class YOWCL_Database {
	private $table_name;
	private $version;

	public function __construct() {
		global $wpdb;
		$this->table_name = $wpdb->prefix . 'yo_loyalty_points_log';
		$this->version    = WC_LOYALTY_DB_VERSION;
		register_activation_hook( YOSWC_LOYALTY_PLUGIN_FILE, [ $this, 'activate' ] );
		add_action( 'admin_init', [ $this, 'check_version' ] );
	}

	public function activate() {
		$this->check_version();
		// Retain the legacy installed-package witness for Free/Premium compatibility.
		update_option( 'yoswc_loyalty_version', YOSWC_LOYALTY_VERSION );
	}

	public function check_version() {
		$installed = (string) get_option( 'wc_loyalty_db_version', '' );
		// Older code must never migrate or lower a newer schema witness.
		if ( '' !== $installed && version_compare( $installed, $this->version, '>' ) ) { return; }
		if ( $this->has_required_indexes() && $this->has_transaction_columns() ) {
			if ( $installed !== $this->version ) { update_option( 'wc_loyalty_db_version', $this->version ); }
			return;
		}
		$this->create_table();
	}

	private function has_required_indexes() {
		global $wpdb;

		$table_exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table_name ) );
		if ( $table_exists !== $this->table_name ) {
			return false;
		}

		$indexes = $wpdb->get_results( "SHOW INDEX FROM {$this->table_name}", ARRAY_A );
		if ( empty( $indexes ) ) {
			return false;
		}

		$index_names = array_unique( wp_list_pluck( $indexes, 'Key_name' ) );
		$required    = array(
			'PRIMARY',
			'user_date',
			'user_action_date',
			'order_action',
			'expired_date',
			'event_key',
			'source_event_key',
		);

		foreach ( $required as $required_index ) {
			if ( ! in_array( $required_index, $index_names, true ) ) {
				return false;
			}
		}

		foreach ( array( 'event_key', 'source_event_key' ) as $key ) {
			$parts = array_values( array_filter( $indexes, static function ( $index ) use ( $key ) { return $key === $index['Key_name']; } ) );
			if ( 1 !== count( $parts ) || $key !== $parts[0]['Column_name'] || null !== $parts[0]['Sub_part'] || ( 'event_key' === $key ? 0 !== (int) $parts[0]['Non_unique'] : 1 !== (int) $parts[0]['Non_unique'] ) ) { return false; }
		}
		return true;
	}

	private function has_transaction_columns() {
		global $wpdb;
		$columns = $wpdb->get_results( "SHOW COLUMNS FROM {$this->table_name}", ARRAY_A );
		$fields = array_column( $columns ?: array(), null, 'Field' );
		foreach ( array( 'event_key' => '/^varbinary\(191\)$/', 'source_event_key' => '/^varbinary\(191\)$/', 'available_delta' => '/^bigint(?:\(20\))?$/', 'earning_delta' => '/^bigint(?:\(20\))?$/', 'ledger_version' => '/^smallint(?:\(6\))?$/', 'allocation_receipt' => '/^mediumtext$/' ) as $key => $type ) {
			if ( ! isset( $fields[ $key ] ) || ! preg_match( $type, $fields[ $key ]['Type'] ) || 'YES' !== $fields[ $key ]['Null'] || null !== $fields[ $key ]['Default'] ) { return false; }
		}
		return true;
	}

	private function create_table() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$this->table_name} (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			user_id bigint(20) NOT NULL,
			action varchar(255) NOT NULL,
			order_id bigint(20) NOT NULL,
			amount decimal(10,2) NOT NULL,
			description text NOT NULL,
			date datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
			expired_date datetime DEFAULT NULL,
			event_key varbinary(191) DEFAULT NULL,
			available_delta bigint(20) DEFAULT NULL,
			earning_delta bigint(20) DEFAULT NULL,
			ledger_version smallint DEFAULT NULL,
			source_event_key varbinary(191) DEFAULT NULL,
			allocation_receipt mediumtext DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY user_date (user_id, date),
			KEY user_action_date (user_id, action(32), date),
			KEY order_action (order_id, action(32)),
			KEY expired_date (expired_date),
			KEY source_event_key (source_event_key),
			UNIQUE KEY event_key (event_key)
		) $charset_collate;";

		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table_name ) );
		if ( $exists === $this->table_name ) {
			$columns = $wpdb->get_col( "SHOW COLUMNS FROM {$this->table_name}" );
			foreach ( array( 'expired_date' => 'datetime', 'event_key' => 'varbinary(191)', 'available_delta' => 'bigint(20)', 'earning_delta' => 'bigint(20)', 'ledger_version' => 'smallint', 'source_event_key' => 'varbinary(191)', 'allocation_receipt' => 'mediumtext' ) as $key => $type ) {
				if ( ! in_array( $key, $columns ?: array(), true ) ) { $wpdb->query( "ALTER TABLE {$this->table_name} ADD COLUMN $key $type DEFAULT NULL" ); }
			}
			$indexes = $wpdb->get_col( "SHOW INDEX FROM {$this->table_name}", 2 );
			foreach ( array( 'user_date' => 'KEY user_date (user_id, date)', 'user_action_date' => 'KEY user_action_date (user_id, action(32), date)', 'order_action' => 'KEY order_action (order_id, action(32))', 'expired_date' => 'KEY expired_date (expired_date)', 'event_key' => 'UNIQUE KEY event_key (event_key)', 'source_event_key' => 'KEY source_event_key (source_event_key)' ) as $key => $definition ) {
				if ( ! in_array( $key, $indexes ?: array(), true ) ) { $wpdb->query( "ALTER TABLE {$this->table_name} ADD $definition" ); }
			}
		} else {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}

		if ( $this->has_required_indexes() && $this->has_transaction_columns() ) {
			update_option( 'wc_loyalty_db_version', $this->version );
		}
	}
}

new YOWCL_Database();

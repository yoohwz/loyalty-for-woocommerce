<?php

defined( 'ABSPATH' ) || exit;

/** Semantic envelope only: no eligibility, mutation, or inferred source identities. */
class YOWCL_Ledger_V2 {
	const CHECKPOINT_META = '_yowcl_ledger_v2_checkpoint';

	public static function actions() {
		return array_merge( YOWCL_Points_Transaction::REWARD_ACTIONS, array( 'points_transaction', 'points_used', 'points_deducted', 'admin_reward', 'admin_deduct', 'points_import', 'referral_reward_reversal' ) );
	}

	public static function key_valid( $key ) {
		return is_string( $key ) && '' !== $key && strlen( $key ) <= 191;
	}

	/** Exact integers, including mysqli decimal strings; never coerce fractions/overflow/null. */
	public static function integer_valid( $value, $limit = 999999999999999999 ) {
		return ( is_int( $value ) || is_string( $value ) ) && preg_match( '/^-?(0|[1-9][0-9]*)$/D', (string) $value ) && strlen( ltrim( (string) $value, '-' ) ) <= 18 && abs( (int) $value ) <= $limit;
	}

	/** Local shape classification. A v2 source link also requires validate_pair at the read boundary. */
	public static function classify( $row ) {
		$row = (array) $row;
		$version = $row['ledger_version'] ?? null;
		$complete = self::key_valid( $row['event_key'] ?? null ) &&
			self::integer_valid( $row['available_delta'] ?? null, 99999999 ) && self::integer_valid( $row['earning_delta'] ?? null ) &&
			self::integer_valid( $row['user_id'] ?? null ) && (int) $row['user_id'] > 0 &&
			in_array( $row['action'] ?? null, self::actions(), true );
		// An advertised receipt is proof-bearing even on an unversioned compatibility row.
		if ( null !== ( $row['allocation_receipt'] ?? null ) ) { try { YOWCL_Points_Allocation::decode( $row['allocation_receipt'] ); } catch ( DomainException $e ) { return 'malformed_v2'; } }
		if ( null !== $version ) {
			if ( ! in_array( $version, array( 2, '2' ), true ) || ! $complete ||
				( null !== ( $row['source_event_key'] ?? null ) && ( ! self::key_valid( $row['source_event_key'] ) || $row['source_event_key'] === $row['event_key'] ) ) ) { return 'malformed_v2'; }
			return 'v2';
		}
		return $complete && null === ( $row['source_event_key'] ?? null ) ? 'transaction_pre_v2' : 'legacy';
	}

	/** The caller supplies an exact lookup on its owning connection/snapshot. Each lookup is indexed. */
	public static function inspect( $row, $lookup = null ) {
		$row = (array) $row;
		$kind = self::classify( $row );
		if ( 'v2' === $kind && null !== ( $row['source_event_key'] ?? null ) ) {
			$source = $lookup ? $lookup( $row['source_event_key'] ) : self::lookup( $row['source_event_key'] );
			if ( ! self::validate_pair( $row, $source ) || 'malformed_v2' === self::inspect( $source, $lookup )['kind'] ) { $kind = 'malformed_v2'; }
		}
		return array( 'kind' => $kind, 'available_delta' => in_array( $kind, array( 'v2', 'transaction_pre_v2' ), true ) ? (int) $row['available_delta'] : null,
			'earning_delta' => in_array( $kind, array( 'v2', 'transaction_pre_v2' ), true ) ? (int) $row['earning_delta'] : null,
			'source_event_key' => $row['source_event_key'] ?? null );
	}

	public static function validate_pair( $derived, $source ) {
		$derived = (array) $derived; $source = (array) $source;
		return 'v2' === self::classify( $derived ) && in_array( self::classify( $source ), array( 'v2', 'transaction_pre_v2' ), true ) &&
			( $derived['source_event_key'] ?? null ) === ( $source['event_key'] ?? null ) && $derived['event_key'] !== $source['event_key'] &&
			(int) $derived['user_id'] === (int) $source['user_id'] &&
			self::integer_valid( $source['id'] ?? null ) && (int) $source['id'] > 0 &&
			( ! isset( $derived['id'] ) || ( self::integer_valid( $derived['id'] ) && (int) $source['id'] < (int) $derived['id'] ) );
	}

	private static function lookup( $key ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . YOWCL_Points_Log::table_name() . ' WHERE event_key = %s', $key ), ARRAY_A );
	}

	/** Compatibility SQL keeps exact DECIMAL aggregation and existing unversioned action rules. */
	public static function explicit_sql( $table, array $columns ) {
		global $wpdb;
		if ( array_diff( array( 'available_delta', 'earning_delta' ), $columns ) ) { return '0=1'; }
		$actions = $wpdb->prepare( implode( ', ', array_fill( 0, count( self::actions() ), '%s' ) ), self::actions() );
		$base = "$table.action IN ($actions) AND $table.available_delta IS NOT NULL AND $table.earning_delta IS NOT NULL";
		if ( ! in_array( 'ledger_version', $columns, true ) ) { return $base; }
		if ( array_diff( array( 'event_key', 'source_event_key' ), $columns ) ) { return "($base AND $table.ledger_version IS NULL)"; }
		// Source validity is independently inspectable; SQL uses the same exact local/pair facts.
		$v2 = "$base AND $table.user_id > 0 AND OCTET_LENGTH($table.event_key) BETWEEN 1 AND 191 AND ABS($table.available_delta) <= 99999999 AND ABS($table.earning_delta) <= 999999999999999999 AND ($table.source_event_key IS NULL OR EXISTS (SELECT 1 FROM $table AS ledger_source WHERE ledger_source.event_key = $table.source_event_key AND ledger_source.event_key <> $table.event_key AND ledger_source.user_id = $table.user_id AND ledger_source.id < $table.id AND ledger_source.action IN ($actions) AND ledger_source.available_delta IS NOT NULL AND ledger_source.earning_delta IS NOT NULL AND ABS(ledger_source.available_delta) <= 99999999 AND ABS(ledger_source.earning_delta) <= 999999999999999999 AND (ledger_source.ledger_version = 2 OR (ledger_source.ledger_version IS NULL AND ledger_source.source_event_key IS NULL))))";
		return "(($table.ledger_version IS NULL AND $base) OR ($table.ledger_version = 2 AND $v2))";
	}

	/** Structural witness readiness; does not certify that legacy writers have been migrated. */
	public static function checkpoint_decode( array $values ) {
		if ( ! $values ) { return array( 'status' => 'none', 'checkpoint' => null ); }
		$value = 1 === count( $values ) && is_string( $values[0] ) && strlen( $values[0] ) <= 1024 ? json_decode( $values[0], true ) : null;
		$keys = array( 'version', 'cutoff_log_id', 'available_balance', 'earning_balance', 'created_at' );
		$timestamp_valid = is_array( $value ) && ( ! array_key_exists( 'created_timestamp', $value ) || ( YOWCL_Points_Allocation::timestamp_valid( $value['created_timestamp'] ) && YOWCL_Points_Allocation::timezone_valid( $value['created_timezone'] ?? null ) ) );
		$valid = $timestamp_valid && count( $value ) === count( $keys ) + ( array_key_exists( 'created_timestamp', $value ) ? 2 : 0 ) && ! array_diff( $keys, array_keys( $value ) ) && 2 === $value['version'];
		foreach ( array( 'cutoff_log_id', 'available_balance', 'earning_balance' ) as $key ) {
			$valid = $valid && is_int( $value[ $key ] ?? null ) && $value[ $key ] >= 0 && $value[ $key ] <= 999999999999999999;
		}
		$date = $valid && is_string( $value['created_at'] ) && preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2} [0-9]{2}:[0-9]{2}:[0-9]{2}$/D', $value['created_at'] ) ? DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $value['created_at'], new DateTimeZone( 'UTC' ) ) : false;
		if ( $valid && array_key_exists( 'created_timestamp', $value ) ) { $valid = wp_date( 'Y-m-d H:i:s', intdiv( $value['created_timestamp'], 1000000 ), new DateTimeZone( $value['created_timezone'] ) ) === $value['created_at']; }
		$valid = $valid && $date && $date->format( 'Y-m-d H:i:s' ) === $value['created_at'] && wp_json_encode( $value ) === $values[0];
		return array( 'status' => $valid ? 'valid' : 'malformed', 'checkpoint' => $valid ? $value : null );
	}
}

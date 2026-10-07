<?php

defined( 'ABSPATH' ) || exit;

/** Narrow atomic points primitive; callers still own authorization and business eligibility. */
class YOWCL_Points_Transaction {
	private static $active = false;
	const REWARD_ACTIONS = array( 'order_reward', 'sign_up_reward', 'daily_login_reward', 'review_reward', 'level_up_reward' );

	/** Recovery reads fail closed: a database/schema error is never a missing event. */
	public static function find( $event_key ) {
		global $wpdb;
		$db = $wpdb->dbh;
		if ( ! ( $db instanceof mysqli ) ) { throw new RuntimeException( 'transaction_storage_unavailable' ); }
		self::require_event_schema( $db, YOWCL_Points_Log::table_name() );
		$table = YOWCL_Points_Log::table_name();
		$row = self::event( $db, $table, $event_key );
		if ( $row && null !== ( $row['ledger_version'] ?? null ) && 'v2' !== YOWCL_Ledger_V2::inspect( $row, static function ( $key ) use ( $db, $table ) { return self::event( $db, $table, $key ); } )['kind'] ) { throw new DomainException( 'malformed_ledger_v2_row' ); }
		return $row;
	}

	/** Explicit compatibility request for an exact pre-v2 row; never permission to write one. */
	public static function replay_event( $event_key, array $event ) {
		try { $row = self::find( $event_key ); } catch ( DomainException $e ) {
			// Preserve the primitive's schema failure/result contract; this is not a missing-event verdict.
			if ( 'transaction_schema_required' !== $e->getMessage() ) { throw $e; }
			return $event;
		}
		if ( $row && 'transaction_pre_v2' === YOWCL_Ledger_V2::inspect( $row )['kind'] ) {
			$event['ledger_version'] = null;
			$event['source_event_key'] = null;
		}
		return $event;
	}

	/** Producer replay uses committed value, even if its rules have changed. */
	public static function reward( $user_id, $points, $event_key, array $event, $guard = null ) {
		if ( ! in_array( $event['action'] ?? '', self::REWARD_ACTIONS, true ) ) {
			return self::result( 'failed', 'invalid_event_action' );
		}
		return self::execute( $user_id, $points, $points, $event_key, $event, 'reward', $guard );
	}

	/** The clamp is calculated inside the same user lock as the original-event proof. */
	public static function reverse_order_reward( $user_id, $order_id, $description ) {
		if ( ! is_int( $order_id ) || $order_id <= 0 ) { return self::result( 'failed', 'invalid_order' ); }
		$key = 'reward:order:' . $order_id . ':reversal';
		try { $event = self::replay_event( $key, array(
			'action' => 'points_deducted', 'order_id' => $order_id, 'description' => $description, 'source_event_key' => 'reward:order:' . $order_id,
		) ); } catch ( Throwable $e ) { return self::result( 'busy', 'reversal_read_failed' ); }
		return self::execute( $user_id, 0, 0, $key, $event, 'reversal' );
	}

	/** Referral-only sibling: the exact original component supplies recipient and clamp limits. */
	/**
	 * Opaque event keys are global to this site's log and case/byte sensitive (1..191 bytes).
	 * No value artifact or business marker should be created until status is applied.
	 */
	public static function apply( $user_id, $available_delta, $earning_delta, $event_key, array $event = array() ) {
		return self::execute( $user_id, $available_delta, $earning_delta, $event_key, $event );
	}

	/** Bounded task-8 operations; callers own capability, occurrence and eligibility. */
	public static function mutate( $user, $available, $earning, $key, array $event, $mode ) {
		$allowed = array( 'clamp' => array( 'admin_deduct' ), 'replace' => array( 'points_import' ), 'credit' => array( 'admin_reward' ) );
		if ( ! isset( $allowed[ $mode ] ) || ! in_array( $event['action'] ?? '', $allowed[ $mode ], true ) ) { return self::result( 'failed', 'invalid_operation' ); }
		return self::execute( $user, $available, $earning, $key, $event, $mode );
	}

	/** Locked, fresh projection recomputation. Never creates an economic event. */
	private static function execute( $user_id, $available_delta, $earning_delta, $event_key, array $event, $mode = 'strict', $guard = null ) {
        if ( ! YOWCL_Free_Core::owns() ) { return self::result( 'failed', 'free_owner_inactive' ); }
		global $wpdb;
		if ( self::$active ) {
			return self::result( 'busy', 'nested_transaction' );
		}
		if ( ! is_int( $user_id ) || $user_id <= 0 || ! is_int( $available_delta ) || ! is_int( $earning_delta ) || ! is_string( $event_key ) || '' === $event_key || strlen( $event_key ) > 191 || abs( $available_delta ) > 99999999 ) {
			return self::result( 'failed', 'invalid_arguments' );
		}
		$requested_version = array_key_exists( 'ledger_version', $event ) ? $event['ledger_version'] : 2;
		$source_key = $event['source_event_key'] ?? null;
		if ( ( ! in_array( $requested_version, array( 2, null ), true ) || ( null === $requested_version && null !== $source_key ) ) || ( null !== $source_key && ( ! YOWCL_Ledger_V2::key_valid( $source_key ) || $source_key === $event_key ) ) ) { return self::result( 'failed', 'invalid_event_relation' ); }
		$action = $event['action'] ?? 'points_transaction';
		$is_reward = in_array( $action, self::REWARD_ACTIONS, true );
		if ( ( $is_reward && ( $available_delta <= 0 || $earning_delta <= 0 ) ) ||
			( ! $is_reward && 'strict' === $mode && ( ! in_array( $action, array( 'points_transaction', 'points_used' ), true ) || ( 'points_transaction' !== $action && ( $available_delta >= 0 || 0 !== $earning_delta ) ) ) ) ) {
			return self::result( 'failed', 'invalid_event_action' );
		}
		$request = array( 'mode' => $mode, 'available' => $available_delta, 'earning' => $earning_delta, 'decision' => $event['decision'] ?? null, 'expiration' => null );
		$expired_date = $event['expired_date'] ?? null;

		$lock = YOWCL_Points_Lock::acquire( $user_id );
		if ( false === $lock ) {
			return self::result( 'busy', 'lock_unavailable' );
		}
		$db = $lock['db'];
		$table = YOWCL_Points_Log::table_name();
		$started = false;
		$committing = false;
		$cache_failed = false;
		self::$active = true;
		try {
			foreach ( array( $wpdb->usermeta, $table ) as $required_table ) {
				$engine = YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $required_table ) );
				if ( 'InnoDB' !== $engine ) {
					return self::result( 'failed', 'transactional_storage_required' );
				}
			}
			self::require_event_schema( $db, $table );
			YOWCL_Points_Lock::query( $db, 'START TRANSACTION' );
			$started = true;
			if ( ! YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( "SELECT ID FROM {$wpdb->users} WHERE ID = %d", $user_id ) ) ) {
				return self::result( 'failed', 'invalid_user' );
			}
			$existing = self::event( $db, $table, $event_key );
			if ( $existing ) {
				if ( ( null === $requested_version ? 'transaction_pre_v2' : 'v2' ) !== YOWCL_Ledger_V2::inspect( $existing, static function ( $key ) use ( $db, $table ) { return self::event( $db, $table, $key ); } )['kind'] || $source_key !== $existing['source_event_key'] || $user_id !== (int) $existing['user_id'] || $action !== $existing['action'] || (int) ( $event['order_id'] ?? 0 ) !== (int) $existing['order_id'] || ( 'strict' === $mode && ( $available_delta !== (int) $existing['available_delta'] || $earning_delta !== (int) $existing['earning_delta'] ) ) ) {
					return self::result( 'failed', 'event_conflict' );
				}
				if ( ! in_array( $mode, array( 'strict', 'reward', 'reversal' ), true ) ) {
					$committed = YOWCL_Points_Allocation::decode( $existing['allocation_receipt'] ?? null )['request'];
					foreach ( array( 'mode', 'available', 'earning', 'decision' ) as $field ) { if ( $committed[ $field ] !== $request[ $field ] ) { return self::result( 'failed', 'event_conflict' ); } }
				}

				$checkpoint = self::checkpoint_state( $user_id, $db );
				if ( 'malformed' === $checkpoint['status'] ) { throw new DomainException( 'malformed_checkpoint' ); }
				if ( 'valid' === $checkpoint['status'] ) { throw new DomainException( 'legacy_allocation_review_required' ); }
				return array( 'status' => 'already_applied', 'code' => '', 'log_id' => (int) $existing['id'] );
			}
			if ( null === $requested_version ) { return self::result( 'failed', 'pre_v2_replay_required' ); }
			if ( null !== $source_key ) {
				$source = self::event( $db, $table, $source_key );
				$derived = array( 'ledger_version' => 2, 'event_key' => $event_key, 'source_event_key' => $source_key, 'user_id' => $user_id, 'action' => $action, 'available_delta' => $available_delta, 'earning_delta' => $earning_delta );
				if ( ! YOWCL_Ledger_V2::validate_pair( $derived, $source ) || 'malformed_v2' === YOWCL_Ledger_V2::inspect( $source, static function ( $key ) use ( $db, $table ) { return self::event( $db, $table, $key ); } )['kind'] ) { return self::result( 'failed', 'invalid_source_event' ); }
			}
			// Advanced delivery guards run under the same user lock, before any new value.
			if ( $guard && ! $guard( $db ) ) { return self::result( 'skipped', 'reward_delivery_obsolete' ); }

			if ( null !== $expired_date ) { return self::result( 'failed', 'unsupported_event_terms' ); }
			$before = self::balances( $db, $user_id );
			$cp = self::checkpoint_state( $user_id, $db );
			if ( 'none' !== $cp['status'] ) { throw new DomainException( 'legacy_allocation_review_required' ); }
			$target_source = null;
			if ( 'replace' === $mode ) {
				if ( $available_delta < 0 || $earning_delta < 0 ) { throw new DomainException( 'invalid_target' ); }
				$available_delta -= $before['user_points']; $earning_delta -= $before['user_earning_points'];
			} elseif ( 'clamp' === $mode ) {
				if ( $available_delta > 0 || $earning_delta > 0 ) { throw new DomainException( 'invalid_deduction' ); }
				$available_delta = -min( $before['user_points'], -$available_delta ); $earning_delta = -min( $before['user_earning_points'], -$earning_delta );
			} elseif ( 'credit' === $mode && ( $available_delta <= 0 || $earning_delta <= 0 ) ) { throw new DomainException( 'invalid_credit' ); }
			if ( 'reversal' === $mode ) {
				$original = self::event( $db, $table, $event['original_key'] ?? ( 'reward:order:' . $event['order_id'] ) );
				if ( ! $original || ( $event['original_action'] ?? 'order_reward' ) !== $original['action'] || (int) $original['user_id'] !== $user_id || (int) $original['order_id'] !== $event['order_id'] || (int) $original['available_delta'] <= 0 || $original['available_delta'] !== $original['earning_delta'] ) {
					return self::result( 'failed', 'original_reward_required' );
				}
				$available_delta = -min( $before['user_points'], (int) $original['available_delta'] );
				$earning_delta = -min( $before['user_earning_points'], (int) $original['earning_delta'] );

			}
			if ( $available_delta < 0 && $before['user_points'] < -$available_delta ) {
				return self::result( 'insufficient_balance', 'insufficient_balance' );
			}
			$receipt = array( 'version' => 1, 'cutoff' => 0, 'admitted_at' => YOWCL_Points_Allocation::now(), 'timezone' => wp_timezone()->getName(), 'request' => $request, 'available' => null, 'earning' => null );
			if ( abs( $available_delta ) > 99999999 ) { throw new DomainException( 'balance_range' ); }
			$receipt_json = wp_json_encode( $receipt );
			YOWCL_Points_Allocation::decode( $receipt_json );
			if ( strlen( $receipt_json ) > YOWCL_Points_Allocation::MAX_RECEIPT ) { throw new DomainException( 'allocation_receipt_limit' ); }
			$new_points = $before['user_points'] + $available_delta;
			$new_earning = $before['user_earning_points'] + $earning_delta;
			// Bound new events to the legacy decimal amount column and exact integer arithmetic.
			if ( ! is_int( $new_points ) || ! is_int( $new_earning ) || $new_points < 0 || $new_earning < 0 || $new_points > 999999999999999999 || $new_earning > 999999999999999999 ) {
				return self::result( 'failed', 'balance_range' );
			}
			$data = array(
				'user_id' => $user_id,
				'action' => $action,
				'order_id' => absint( $event['order_id'] ?? 0 ),
				'amount' => $available_delta,
				'description' => (string) ( $event['description'] ?? '' ),
				'date' => wp_date( 'Y-m-d H:i:s', intdiv( $receipt['admitted_at'], 1000000 ), new DateTimeZone( $receipt['timezone'] ) ),
				'event_key' => $event_key,
				'available_delta' => $available_delta,
				'earning_delta' => $earning_delta,
				'expired_date' => $expired_date,
				'ledger_version' => 2,
				'source_event_key' => $source_key,
				'allocation_receipt' => $receipt_json,
			);
			$values = array( $user_id, $action, $data['order_id'], $available_delta, $data['description'], $data['date'], $event_key, $available_delta, $earning_delta );
			if ( null !== $expired_date ) { $values[] = $expired_date; }
			if ( null !== $source_key ) { $values[] = $source_key; }
			$values[] = $receipt_json;
			YOWCL_Points_Lock::query( $db, $wpdb->prepare(
				"INSERT INTO {$table} (user_id, action, order_id, amount, description, date, event_key, available_delta, earning_delta, expired_date, ledger_version, source_event_key, allocation_receipt) VALUES (%d, %s, %d, %d, %s, %s, %s, %d, %d, " . ( null === $expired_date ? 'NULL' : '%s' ) . ', 2, ' . ( null === $source_key ? 'NULL' : '%s' ) . ', %s)', $values
			) );
			$log_id = (int) mysqli_insert_id( $db );
			self::checkpoint( 'event_inserted' );
			self::write_balance( $db, $user_id, 'user_points', $new_points );
			self::checkpoint( 'available_written' );
			self::write_balance( $db, $user_id, 'user_earning_points', $new_earning );
			self::checkpoint( 'balances_written' );
			$committing = true;
			YOWCL_Points_Lock::query( $db, 'COMMIT' );
			$started = false;
			$result = array(
				'status' => 'applied', 'code' => '', 'log_id' => $log_id,
				'old_points' => $before['user_points'], 'old_earning_points' => $before['user_earning_points'],
				'new_points' => $new_points, 'new_earning_points' => $new_earning,
			);
		} catch ( DomainException $e ) {
			$result = self::result( 'failed', $e->getMessage() );
		} catch ( Throwable $e ) {
			// A commit response lost to disconnect has unknown outcome: replay this same key.
			$retryable = $committing || in_array( (int) $e->getCode(), array( 0, 1062, 1205, 1213, 2006, 2013, 2055 ), true );
			$result = self::result( $retryable ? 'busy' : 'failed', $committing ? 'commit_outcome_unknown' : ( $retryable ? 'transaction_rolled_back' : 'database_failure' ) );
		} finally {
			if ( $started ) {
				try {
					YOWCL_Points_Lock::query( $db, 'ROLLBACK' );
				} catch ( Throwable $ignored ) {
				}
			}
			self::$active = false;
			YOWCL_Points_Lock::release( $lock );
			try {
				wp_cache_delete( $user_id, 'user_meta' );
			} catch ( Throwable $e ) {
				$cache_failed = true;
			}
		}
		if ( 'applied' === $result['status'] ) {
			try {
				YOWCL_Points_Log_Cache::invalidate_user( $user_id );
			} catch ( Throwable $e ) {
				$cache_failed = true;
			}
			if ( $cache_failed ) {
				$result['code'] = 'cache_failed_after_commit';
			}
			// Observer failures after commit must never turn an applied debit into a failed one.
			try {
				YOWCL_Points_Log::fire_created( $log_id, $data, $event );
			} catch ( Throwable $e ) {
				$result['code'] = 'observer_failed_after_commit';
			}
		}
		return $result;
	}

	/** Explicit per-user baseline API, used by bounded reconciliation migration; never at boot. */
	/** Read the witness without trusting cached meta or hiding duplicate records. */
	public static function checkpoint_state( $user_id, $db = null ) {
		global $wpdb;
		if ( ! is_int( $user_id ) || $user_id <= 0 ) { throw new DomainException( 'invalid_user' ); }
		$db = $db ?? $wpdb->dbh;
		if ( ! ( $db instanceof mysqli ) ) { throw new RuntimeException( 'transaction_storage_unavailable' ); }
		$result = YOWCL_Points_Lock::query( $db, $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s ORDER BY umeta_id LIMIT 2", $user_id, YOWCL_Ledger_V2::CHECKPOINT_META ) );
		$values = array();
		while ( $row = mysqli_fetch_row( $result ) ) { $values[] = $row[0]; }
		mysqli_free_result( $result );
		return YOWCL_Ledger_V2::checkpoint_decode( $values );
	}

	private static function require_event_schema( $db, $table ) {
		$indexes = YOWCL_Points_Lock::query( $db, "SHOW INDEX FROM {$table} WHERE Key_name = 'event_key'" );
		$index = mysqli_fetch_assoc( $indexes );
		$valid = 1 === mysqli_num_rows( $indexes ) && 0 === (int) ( $index['Non_unique'] ?? 1 ) && 'event_key' === ( $index['Column_name'] ?? '' ) && null === ( $index['Sub_part'] ?? null );
		mysqli_free_result( $indexes );
		$source_indexes = YOWCL_Points_Lock::query( $db, "SHOW INDEX FROM {$table} WHERE Key_name = 'source_event_key'" );
		$source_index = mysqli_fetch_assoc( $source_indexes );
		$valid = $valid && 1 === (int) ( $source_index['Non_unique'] ?? 0 ) && 1 === mysqli_num_rows( $source_indexes ) && 'source_event_key' === ( $source_index['Column_name'] ?? '' ) && null === ( $source_index['Sub_part'] ?? null );
		mysqli_free_result( $source_indexes );
		$columns = YOWCL_Points_Lock::query( $db, "SHOW COLUMNS FROM {$table}" );
		$types = array();
		$nullable = array();
		while ( $column = mysqli_fetch_assoc( $columns ) ) {
			$types[ $column['Field'] ] = $column['Type'];
			$nullable[ $column['Field'] ] = 'YES' === $column['Null'] && null === $column['Default'];
		}
		mysqli_free_result( $columns );
		foreach ( array( 'event_key', 'available_delta', 'earning_delta', 'ledger_version', 'source_event_key' ) as $field ) { $valid = $valid && ( $nullable[ $field ] ?? false ); }
		if ( ! ( $nullable['allocation_receipt'] ?? false ) || 'mediumtext' !== ( $types['allocation_receipt'] ?? '' ) || ! $valid || ! ( $nullable['ledger_version'] ?? false ) || ! ( $nullable['source_event_key'] ?? false ) || ! preg_match( '/^smallint(?:\(6\))?$/', $types['ledger_version'] ?? '' ) || 'varbinary(191)' !== ( $types['source_event_key'] ?? '' ) || 'varbinary(191)' !== ( $types['event_key'] ?? '' ) || ! preg_match( '/^bigint(?:\(20\))?$/', $types['available_delta'] ?? '' ) || ! preg_match( '/^bigint(?:\(20\))?$/', $types['earning_delta'] ?? '' ) || 'datetime' !== ( $types['expired_date'] ?? '' ) ) {
			throw new DomainException( 'transaction_schema_required' );
		}
	}

	private static function event( $db, $table, $key ) {
		global $wpdb;
		$result = YOWCL_Points_Lock::query( $db, $wpdb->prepare( "SELECT * FROM {$table} WHERE event_key = %s", $key ) );
		$row = mysqli_fetch_assoc( $result );
		mysqli_free_result( $result );
		return $row;
	}

	private static function balances( $db, $user_id, $projection = false ) {
		global $wpdb;
		$result = YOWCL_Points_Lock::query( $db, $wpdb->prepare( "SELECT meta_key, meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key IN ('user_points', 'user_earning_points') ORDER BY umeta_id FOR UPDATE", $user_id ) );
		$balances = array( 'user_points' => 0, 'user_earning_points' => 0 );
		$seen = array();
		while ( $row = mysqli_fetch_assoc( $result ) ) {
			$key = $row['meta_key'];
			if ( isset( $seen[ $key ] ) || ! preg_match( $projection ? '/^-?[0-9]+$/D' : '/^[0-9]+$/D', $row['meta_value'] ) || strlen( ltrim( $row['meta_value'], '-' ) ) > 18 ) {
				mysqli_free_result( $result );
				throw new DomainException( 'invalid_balance_storage' );
			}
			$seen[ $key ] = true;
			$balances[ $key ] = (int) $row['meta_value'];
		}
		mysqli_free_result( $result );
		return $balances;
	}

	private static function write_balance( $db, $user_id, $key, $value ) {
		global $wpdb;
		$count = YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s", $user_id, $key ) );
		$sql = $count
			? $wpdb->prepare( "UPDATE {$wpdb->usermeta} SET meta_value = %s WHERE user_id = %d AND meta_key = %s", (string) $value, $user_id, $key )
			: $wpdb->prepare( "INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value) VALUES (%d, %s, %s)", $user_id, $key, (string) $value );
		YOWCL_Points_Lock::query( $db, $sql );
	}

	private static function checkpoint( $step ) {
		// Only the disposable runtime MU plugin defines this; no production filter/endpoint.
		if ( defined( 'LOY_RUNTIME_DISPOSABLE' ) && true === LOY_RUNTIME_DISPOSABLE ) {
			do_action( 'yowcl_transaction_test_checkpoint', $step );
		}
	}

	private static function result( $status, $code ) {
		return array( 'status' => $status, 'code' => $code );
	}
}

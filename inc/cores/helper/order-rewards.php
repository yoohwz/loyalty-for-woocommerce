<?php

defined( 'ABSPATH' ) || exit;

/** Order-owned serialization and compatibility projections for core rewards only. */
class YOWCL_Order_Rewards {
	const LEVEL_BEFORE_META = '_yowcl_level_projection_before';
	const TERMS_META = '_yowcl_order_reward_terms';
	const RETRY_HOOK = 'yowcl_order_reward_transition_retry';
	const RETRY_GROUP = 'yowcl-core-rewards';
	private static $held = array();

	public static function locked( $order_id, $callback, array $transition = array(), $retry = null ) {
		global $wpdb;
		$db = $wpdb->dbh;
		$name = 'yowclrw_' . hash( 'sha224', DB_NAME . ':' . $wpdb->prefix . ':' . $order_id );
		if ( isset( self::$held[ $order_id ] ) ) { if ( $retry ) { $retry(); } else { self::defer( $order_id, $transition ); } return; }
		if ( ! ( $db instanceof mysqli ) || YOWCL_Points_Lock::has_transaction( $db ) ) { return; }
		try {
			if ( '1' !== (string) YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $name ) ) ) { if ( $retry ) { $retry(); } else { self::defer( $order_id, $transition ); } return; }
			self::$held[ $order_id ] = true;
			$order = wc_get_order( $order_id );
			if ( ! $order ) { return; }
			$order->read_meta_data( true );
			$owner = static function () use ( $db, $name ) {
				global $wpdb;
				if ( $wpdb->dbh !== $db || (string) YOWCL_Points_Lock::scalar( $db, 'SELECT CONNECTION_ID()' ) !== (string) YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT IS_USED_LOCK(%s)', $name ) ) ) {
					throw new RuntimeException( 'order_reward_ownership_lost' );
				}
			};
			$owner();
			return $callback( $order, $owner );
		} catch ( Throwable $e ) {
			YOWCL_Core_Rewards::report( 'reward:order:' . $order_id, $e );
			if ( $retry ) { $retry( $e ); } elseif ( in_array( $e->getMessage(), array( 'order_reward_value_busy', 'reward_level_projection_busy', 'reward_level_projection_retry_required', 'reward_expiration_schedule_retry' ), true ) ) { self::defer( $order_id, $transition ); }
		} finally {
			if ( isset( self::$held[ $order_id ] ) ) {
				unset( self::$held[ $order_id ] );
				try { YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); } catch ( Throwable $ignored ) {}
			}
		}
	}

	/** Persist a contended native transition; an in-progress retry may enqueue its successor. */
	public static function defer( $order_id, array $transition ) {
		if ( 2 !== count( $transition ) ) { return; }
		$args = array( (int) $order_id, (string) $transition[0], (string) $transition[1] );
		try {
			if ( ! function_exists( 'as_get_scheduled_actions' ) || ! function_exists( 'as_schedule_single_action' ) ) { throw new RuntimeException( 'order_reward_retry_unavailable' ); }
			if ( as_get_scheduled_actions( array( 'hook' => self::RETRY_HOOK, 'group' => self::RETRY_GROUP, 'args' => $args, 'status' => 'pending', 'per_page' => 1 ), 'ids' ) ) { return; }
			// Do not use AS unique=true: its in-progress match would discard a worker's retry.
			if ( ! as_schedule_single_action( time() + 5, self::RETRY_HOOK, $args, self::RETRY_GROUP ) ) { throw new RuntimeException( 'order_reward_retry_not_saved' ); }
		} catch ( Throwable $e ) { YOWCL_Core_Rewards::report( 'reward:order:' . $order_id, $e ); }
	}

	public static function retry_transition( $order_id, $old_status, $new_status ) {
		// Retry only the admitted core producers, not unrelated status-change observers.
		( new YOWCL_Actions_Earn_Points( false ) )->on_order_status_changed( $order_id, $old_status, $new_status );
		( new YOWCL_Actions_Deduct_Points( false ) )->on_order_status_changed( $order_id, $old_status, $new_status );
	}

	/** Verify persisted Woo CRUD metadata, not only the object's pending changes. */
	public static function meta( $order, $key, $value ) {
		$order->update_meta_data( $key, $value );
		$order->save_meta_data();
		$order->read_meta_data( true );
		if ( (string) maybe_serialize( $order->get_meta( $key, true ) ) !== (string) maybe_serialize( $value ) ) {
			throw new RuntimeException( 'order_reward_meta_not_saved' );
		}
	}

	public static function level( $user_id, array $rules ) {
		// Project current earning state: a late reward replay must not undo a reversal.
		$lock = YOWCL_Points_Lock::acquire( (int) $user_id );
		if ( ! $lock ) { throw new RuntimeException( 'reward_level_projection_busy' ); }
		$changed = false;
		try {
			global $wpdb;
			$stored_earning = YOWCL_Points_Lock::scalar( $lock['db'], $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = 'user_earning_points' ORDER BY umeta_id LIMIT 1", $user_id ) );
            if ( null !== $stored_earning && ( ! preg_match( '/^[0-9]+$/D', $stored_earning ) || strlen( $stored_earning ) > 18 ) ) { throw new RuntimeException( 'invalid_balance_storage' ); }
            $earning = (int) $stored_earning;
			wp_cache_delete( $user_id, 'user_meta' );
			$sorted = array();
			foreach ( $rules as $role => $rule ) { $sorted[ $role ] = (int) ( $rule['from'] ?? 0 ); }
			asort( $sorted );
			$level = null;
			foreach ( $sorted as $role => $threshold ) { if ( $earning >= $threshold ) { $level = $role; } }
			// Capture the canonical before-level once, even if role/claim writes partially fail.
			$previous_level = YOWCL_Helper_Roles::get_highest_loyalty_user_role( $user_id );
			$pending = get_user_meta( $user_id, self::LEVEL_BEFORE_META, true );
			if ( '' !== $pending ) {
				if ( ! is_array( $pending ) || 1 !== count( $pending ) || ! isset( $pending['level'] ) || ! is_string( $pending['level'] ) || sanitize_key( $pending['level'] ) !== $pending['level'] ) { throw new RuntimeException( 'reward_level_projection_retry_required' ); }
				$previous_level = $pending['level'];
			}
			$user = new WP_User( $user_id );
			$marker = (string) get_user_meta( $user_id, YOWCL_Helper_Roles::LOYALTY_LEVEL_META, true );
			$claim_missing = $level && class_exists( 'YOSWC_Role_Claims' ) && ! YOSWC_Role_Claims::has_role_claim( $user_id, $level, YOWCL_Helper_Roles::CLAIM_SOURCE );
			$projection_needed = $level && ( $marker !== $level || ! in_array( $level, (array) $user->roles, true ) || $claim_missing );
			if ( $projection_needed || ( $level && '' !== $pending ) ) {
				if ( $previous_level !== $level && '' === $pending ) {
					$pending = array( 'level' => $previous_level );
					update_user_meta( $user_id, self::LEVEL_BEFORE_META, $pending );
					wp_cache_delete( $user_id, 'user_meta' );
					if ( get_user_meta( $user_id, self::LEVEL_BEFORE_META, true ) !== $pending ) { throw new RuntimeException( 'reward_level_projection_retry_required' ); }
				}
				if ( $projection_needed ) { YOWCL_Helper_Roles::set_user_loyalty_role( $user_id, $level ); }
				wp_cache_delete( $user_id, 'user_meta' );
				$user = new WP_User( $user_id );
				if ( (string) get_user_meta( $user_id, YOWCL_Helper_Roles::LOYALTY_LEVEL_META, true ) !== $level || ! in_array( $level, (array) $user->roles, true ) || ( class_exists( 'YOSWC_Role_Claims' ) && ! YOSWC_Role_Claims::has_role_claim( $user_id, $level, YOWCL_Helper_Roles::CLAIM_SOURCE ) ) ) { throw new RuntimeException( 'reward_level_projection_retry_required' ); }
				if ( '' !== $pending ) {
					// Consume under the lock before observational delivery, as on normal projection.
					delete_user_meta( $user_id, self::LEVEL_BEFORE_META );
					wp_cache_delete( $user_id, 'user_meta' );
					if ( '' !== get_user_meta( $user_id, self::LEVEL_BEFORE_META, true ) ) { throw new RuntimeException( 'reward_level_projection_retry_required' ); }
				}
				$changed = $previous_level !== $level;
			}
		} finally { YOWCL_Points_Lock::release( $lock ); }
		if ( $level ) { YOWCL_Free_Core::level_bonus( $user_id, $level ); }
		if ( $changed ) {
			try { YOWCL_Points_Events::level_update( $user_id, $level, $earning ); } catch ( Throwable $e ) { YOWCL_Core_Rewards::report( 'reward:level_projection:' . $user_id, $e ); }
		}
	}

	/** Historical rows stay nullable; atomically commit their clamp, log and Woo marker. */
	public static function legacy_deduct( $order, $description, array $unused = array() ) {
		global $wpdb;
		$user_id = (int) $order->get_user_id();
		$marker = '_points_deducted';
		$action = 'points_deducted';
		$checkpoint = YOWCL_Points_Transaction::checkpoint_state( $user_id );
		if ( 'malformed' === $checkpoint['status'] ) { throw new RuntimeException( 'malformed_checkpoint' ); }
		if ( 'none' !== $checkpoint['status'] ) { throw new RuntimeException( 'legacy_allocation_review_required' ); }
		$lock = YOWCL_Points_Lock::acquire( $user_id );
		if ( ! $lock ) { throw new RuntimeException( 'order_reward_value_busy' ); }
		$original_wpdb = $wpdb;
		$started = false;
		try {
			$db = $lock['db'];
			if ( 'none' !== YOWCL_Points_Transaction::checkpoint_state( $user_id, $db )['status'] ) { throw new RuntimeException( 'order_reward_value_busy' ); }
			$table = YOWCL_Points_Log::table_name();
			$hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
			foreach ( array( $wpdb->usermeta, $table, $hpos ? $wpdb->prefix . 'wc_orders_meta' : $wpdb->postmeta ) as $required ) {
				if ( 'InnoDB' !== YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $required ) ) ) { throw new RuntimeException( 'legacy_reward_transactional_storage_required' ); }
			}
			// Woo CRUD remains the metadata writer. Disable wpdb reconnect for this bounded
			// transaction so it cannot publish a marker in autocommit after losing the debit.
			// A live result resource must not be shared by two wpdb instances.
			$original_wpdb->flush();
			$guard = ( new ReflectionClass( 'YOWCL_Reward_Transaction_WPDB' ) )->newInstanceWithoutConstructor();
			foreach ( ( new ReflectionObject( $wpdb ) )->getProperties() as $property ) {
				if ( ! $property->isStatic() ) { $property->setAccessible( true ); $property->setValue( $guard, $property->getValue( $wpdb ) ); }
			}
			$wpdb = $guard;
			YOWCL_Points_Lock::query( $db, 'START TRANSACTION' );
			$started = true;
			$order->read_meta_data( true );
			if ( $order->get_meta( $marker, true ) ) { return false; }
			$points = (int) $order->get_meta( '_points_awarded', true );
			if ( $points <= 0 ) { return false; }
			$balances = array();
			foreach ( array( 'user_points', 'user_earning_points' ) as $key ) {
				$raw = YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s ORDER BY umeta_id LIMIT 1 FOR UPDATE", $user_id, $key ) );
				if ( null !== $raw && ( ! preg_match( '/^[0-9]+$/D', $raw ) || strlen( $raw ) > 18 ) ) { throw new RuntimeException( 'invalid_balance_storage' ); }
				$balances[ $key ] = max( 0, (int) $raw - $points );
				if ( null === $raw ) {
					YOWCL_Points_Lock::query( $db, $wpdb->prepare( "INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value) VALUES (%d, %s, %s)", $user_id, $key, (string) $balances[ $key ] ) );
				} else {
					YOWCL_Points_Lock::query( $db, $wpdb->prepare( "UPDATE {$wpdb->usermeta} SET meta_value = %s WHERE user_id = %d AND meta_key = %s", (string) $balances[ $key ], $user_id, $key ) );
				}
			}
			$data = array( 'user_id' => $user_id, 'action' => $action, 'order_id' => $order->get_id(), 'amount' => $points, 'description' => $description, 'date' => current_time( 'mysql' ) );
			YOWCL_Points_Lock::query( $db, $wpdb->prepare( "INSERT INTO {$table} (user_id, action, order_id, amount, description, date) VALUES (%d, %s, %d, %d, %s, %s)", array_values( $data ) ) );
			$log_id = (int) mysqli_insert_id( $db );
			YOWCL_Core_Rewards::checkpoint( 'legacy_before_marker', 'reward:order:' . $order->get_id() );
			self::meta( $order, $marker, $points );
			if ( $wpdb->dbh !== $db ) { throw new RuntimeException( 'legacy_reward_connection_lost' ); }
			YOWCL_Points_Lock::query( $db, 'COMMIT' );
			$started = false;
		} finally {
			if ( isset( $guard ) ) { try { $guard->flush(); } catch ( Throwable $ignored ) {} }
			$wpdb = $original_wpdb;
			if ( $started ) { try { YOWCL_Points_Lock::query( $lock['db'], 'ROLLBACK' ); } catch ( Throwable $ignored ) {} }
			YOWCL_Points_Lock::release( $lock );
			wp_cache_delete( $user_id, 'user_meta' );
		}
		YOWCL_Core_Rewards::checkpoint( 'legacy_committed', 'reward:order:' . $order->get_id() );
		try {
			YOWCL_Points_Log_Cache::invalidate_user( $user_id );
			YOWCL_Points_Log::fire_created( $log_id, $data );
			YOWCL_Points_Events::deduct( $user_id, $points, $balances['user_points'], $order->get_id() );
		} catch ( Throwable $e ) { YOWCL_Core_Rewards::report( 'reward:order:' . $order->get_id(), $e ); }
		return true;
	}
}

class YOWCL_Reward_Transaction_WPDB extends wpdb {
	public function check_connection( $allow_bail = true ) { return false; }
}

add_action( YOWCL_Order_Rewards::RETRY_HOOK, array( 'YOWCL_Order_Rewards', 'retry_transition' ), 10, 3 );

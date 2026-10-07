<?php

defined( 'ABSPATH' ) || exit;

/** Finalization for the admitted core producers; no receipt store or eligibility policy. */
class YOWCL_Core_Rewards {
	const RETRY_HOOK = 'yowcl_core_reward_delivery_retry';
	const RETRY_GROUP = 'yowcl-core-rewards';
	const USER_ACTIONS = array( 'sign_up_reward', 'daily_login_reward', 'review_reward', 'level_up_reward' );
	public static function recover( $user_id, $key, $action, $project, $order_id = 0, $retry = null ) {
		try {
			$row = YOWCL_Points_Transaction::find( $key );
			if ( ! $row ) { return false; }
			if ( (int) $row['user_id'] !== (int) $user_id || $row['action'] !== $action || (int) $row['order_id'] !== (int) $order_id ) {
				throw new RuntimeException( 'reward_event_conflict' );
			}
			self::finish( $row, $project );
		} catch ( Throwable $e ) {
			self::report( $key, $e );
			if ( in_array( $e->getMessage(), array( 'reward_projection_busy', 'reward_level_projection_busy', 'reward_level_projection_retry_required', 'reward_expiration_schedule_retry' ), true ) ) {
				if ( $retry ) { $retry(); }
				elseif ( isset( $row ) && 0 === (int) $order_id && in_array( $action, self::USER_ACTIONS, true ) ) {
					$policy = ! empty( $row['allocation_receipt'] ) ? YOWCL_Points_Allocation::decode( $row['allocation_receipt'] )['request']['expiration'] : null;
					self::queue_user( array( 'user_id' => (int) $row['user_id'], 'points' => (int) $row['available_delta'], 'key' => $key, 'action' => $action, 'description' => $row['description'], 'expired_date' => $row['expired_date'], 'expiration_policy' => $policy ) );
				}
			}
		}
		// Lookup errors must never authorize a fresh award.
		return true;
	}

	public static function award( $user_id, $points, $key, $action, $description, $project, $notify, $order_id = 0, $retry = null, $expiration = false, $expiration_policy = false ) {
		if ( ! $retry && 0 === (int) $order_id && in_array( $action, self::USER_ACTIONS, true ) ) {
			$intent = array( 'user_id' => (int) $user_id, 'points' => (int) $points, 'key' => $key, 'action' => $action, 'description' => $description, 'expired_date' => $expiration, 'expiration_policy' => $expiration_policy );
			$retry = static function () use ( $intent ) { self::queue_user( $intent ); };
		}
		try {
			$expiration = null; $expiration_policy = null;
			self::checkpoint( 'prepared', $key );
			$result = YOWCL_Points_Transaction::reward( (int) $user_id, (int) $points, $key, YOWCL_Points_Transaction::replay_event( $key, array(
				'action' => $action, 'description' => $description, 'order_id' => (int) $order_id,
				'expired_date' => $expiration, 'expiration_policy' => $expiration_policy,
			) ) );
			if ( ! in_array( $result['status'], array( 'applied', 'already_applied' ), true ) ) {
				YOWCL_Free_Core::hold( $user_id, $result['code'] );
				if ( 'busy' === $result['status'] && $retry ) { $retry(); }
				return false;
			}
			$row = YOWCL_Points_Transaction::find( $key );
			self::checkpoint( 'committed', $key );
			self::finish( $row, $project );
			if ( 'applied' === $result['status'] ) {
				$notify( $row, $result );
			}
			return true;
		} catch ( Throwable $e ) {
			self::report( $key, $e );
			if ( $retry && in_array( $e->getMessage(), array( 'reward_projection_busy', 'reward_level_projection_busy', 'reward_level_projection_retry_required', 'reward_expiration_schedule_retry' ), true ) ) { $retry(); }
			return false; // An event that committed remains recoverable by its key.
		}
	}

	/** Delivery intents retain admitted hook identity/terms; the transaction remains value authority. */
	private static function queue_user( array $intent ) {
		try {
			self::user_projection( $intent['user_id'], $intent['key'], $intent['action'] );
			if ( ! function_exists( 'as_get_scheduled_actions' ) || ! function_exists( 'as_schedule_single_action' ) ) { throw new RuntimeException( 'core_reward_retry_unavailable' ); }
			$args = array( $intent );
			if ( as_get_scheduled_actions( array( 'hook' => self::RETRY_HOOK, 'group' => self::RETRY_GROUP, 'args' => $args, 'status' => 'pending', 'per_page' => 1 ), 'ids' ) ) { return true; }
			if ( ! as_schedule_single_action( time() + 5, self::RETRY_HOOK, $args, self::RETRY_GROUP ) ) { throw new RuntimeException( 'core_reward_retry_not_saved' ); }
			return true;
		} catch ( Throwable $e ) { self::report( $intent['key'], $e ); return false; }
	}

	public static function retry_user( array $intent ) {
		if ( ! YOWCL_Free_Core::admitted( $intent['user_id'], $intent['key'], $intent['action'] ) && ! YOWCL_Points_Transaction::find( $intent['key'] ) ) { return; }
		$project = self::user_projection( $intent['user_id'], $intent['key'], $intent['action'] );
		// A queued intent is not value proof; a newly observed legacy marker still guards history.
		if ( ! YOWCL_Points_Transaction::find( $intent['key'] ) && self::legacy_user_marker( $intent ) && ! YOWCL_Points_Transaction::find( $intent['key'] ) ) { return; }
		$deferred = false;
		$retry = static function () use ( $intent, &$deferred ) { $deferred = self::queue_user( $intent ); };
		$done = self::award( $intent['user_id'], $intent['points'], $intent['key'], $intent['action'], $intent['description'], $project, array( __CLASS__, 'notify_user' ), 0, $retry, $intent['expired_date'], $intent['expiration_policy'] ?? null );
		if ( ! $done && ! $deferred ) { throw new RuntimeException( 'core_reward_delivery_failed' ); }
	}

	private static function legacy_user_marker( array $intent ) {
		$user_id = (int) $intent['user_id'];
		wp_cache_delete( $user_id, 'user_meta' );
		switch ( $intent['action'] ) {
			case 'sign_up_reward': return (bool) get_user_meta( $user_id, '_yol_signup_awarded', true );
			case 'daily_login_reward':
				$day = substr( $intent['key'], -10 );
				return get_user_meta( $user_id, 'loyalty_last_daily_login', true ) === $day || get_user_meta( $user_id, 'yol_last_daily_login', true ) === $day || (bool) get_user_meta( $user_id, '_yowcl_daily_login_lock_' . $day, true );
			case 'level_up_reward': return in_array( substr( $intent['key'], strlen( 'reward:level_up:' . $user_id . ':' ) ), (array) get_user_meta( $user_id, '_yo_loyalty_levelup_awarded_roles', true ), true );
			case 'review_reward': return (bool) get_comment_meta( (int) substr( $intent['key'], strlen( 'reward:review:' ) ), '_yowcl_review_reward_awarded', true );
		}
		return false;
	}

	/** Pure identity decoding, never a substitute for the producer's eligibility/authorization. */
	public static function user_projection( $user_id, $key, $action ) {
		$user_id = (int) $user_id;
		if ( $user_id <= 0 || ! in_array( $action, self::USER_ACTIONS, true ) ) { throw new RuntimeException( 'invalid_core_reward_identity' ); }
		$value = 1; $merge = '';
		switch ( $action ) {
			case 'sign_up_reward': $expected = 'reward:signup:' . $user_id; $meta = '_yol_signup_awarded'; break;
			case 'daily_login_reward':
				$prefix = 'reward:daily_login:' . $user_id . ':'; $value = substr( $key, strlen( $prefix ) );
				if ( ! preg_match( '/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/D', $value ) ) { throw new RuntimeException( 'invalid_core_reward_identity' ); }
				$expected = $prefix . $value; $meta = 'yol_last_daily_login'; $merge = 'day'; break;
			case 'level_up_reward':
				$prefix = 'reward:level_up:' . $user_id . ':'; $value = substr( $key, strlen( $prefix ) );
				if ( '' === $value || sanitize_key( $value ) !== $value ) { throw new RuntimeException( 'invalid_core_reward_identity' ); }
				$expected = $prefix . $value; $meta = '_yo_loyalty_levelup_awarded_roles'; $merge = 'roles'; break;
			case 'review_reward':
				$comment_id = (int) substr( $key, strlen( 'reward:review:' ) ); $expected = 'reward:review:' . $comment_id;
				if ( $comment_id <= 0 || $key !== $expected ) { throw new RuntimeException( 'invalid_core_reward_identity' ); }
				return static function () use ( $comment_id ) { update_comment_meta( $comment_id, '_yowcl_review_reward_awarded', 1 ); };
		}
		if ( $key !== $expected ) { throw new RuntimeException( 'invalid_core_reward_identity' ); }
		return static function ( $row ) use ( $user_id, $action, $meta, $value, $merge ) {
			self::user_marker( $user_id, $meta, $value, $merge );
		};
	}

	public static function notify_user( $row, $balance ) { YOWCL_Points_Events::reward( (int) $row['user_id'], (int) $row['available_delta'], $balance['new_points'], null ); }

	public static function finish( array $row, $project ) {
		self::checkpoint( 'projecting', $row['event_key'] );
		$project( $row );

	}

	/** Serialize the compatibility list/day merge on the same native user connection. */
	public static function user_marker( $user_id, $key, $value, $merge = '' ) {
		global $wpdb;
		$lock = YOWCL_Points_Lock::acquire( (int) $user_id );
		if ( ! $lock ) { throw new RuntimeException( 'reward_projection_busy' ); }
		try {
			$db = $lock['db'];
			$raw = YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s ORDER BY umeta_id LIMIT 1", $user_id, $key ) );
			$old = maybe_unserialize( $raw );
			if ( in_array( $merge, array( 'roles', 'thresholds' ), true ) ) {
				$value = array_values( array_unique( array_merge( is_array( $old ) ? $old : array(), array( $value ) ) ) );
			} elseif ( 'year' === $merge && (int) $old > (int) $value ) {
				$value = $old;
			} elseif ( 'day' === $merge && is_string( $old ) && $old > $value ) {
				$value = $old;
			}
			$sql = null === $raw
				? $wpdb->prepare( "INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value) VALUES (%d, %s, %s)", $user_id, $key, maybe_serialize( $value ) )
				: $wpdb->prepare( "UPDATE {$wpdb->usermeta} SET meta_value = %s WHERE user_id = %d AND meta_key = %s", maybe_serialize( $value ), $user_id, $key );
			YOWCL_Points_Lock::query( $db, $sql );
		} finally {
			YOWCL_Points_Lock::release( $lock );
			wp_cache_delete( $user_id, 'user_meta' );
		}
	}

	public static function checkpoint( $step, $key ) {
		if ( defined( 'LOY_RUNTIME_DISPOSABLE' ) && true === LOY_RUNTIME_DISPOSABLE ) {
			do_action( 'yowcl_reward_test_checkpoint', $step, $key );
		}
	}

	public static function report( $key, Throwable $e ) {
		if ( defined( 'LOY_RUNTIME_DISPOSABLE' ) && true === LOY_RUNTIME_DISPOSABLE ) { do_action( 'yowcl_reward_test_error', $key, $e->getMessage() ); }
		if ( function_exists( 'yoohw_diagnostic_log' ) ) {
			try { yoohw_diagnostic_log( 'wc_loyalty_points', 'core_reward_recovery_required', array( 'event_key' => $key, 'error' => $e->getMessage() ), 'warning' ); } catch ( Throwable $ignored ) {}
		}
	}
}

add_action( YOWCL_Core_Rewards::RETRY_HOOK, array( 'YOWCL_Core_Rewards', 'retry_user' ), 10, 1 );

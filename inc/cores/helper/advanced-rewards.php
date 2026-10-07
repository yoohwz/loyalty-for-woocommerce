<?php

defined( 'ABSPATH' ) || exit;

/** Bounded delivery for the admitted First Purchase producer; the canonical transaction owns new value. */
class YOWCL_Advanced_Rewards {
	const ORDER_HOOK = 'yowcl_advanced_order_reward_retry';
	const GROUP = 'yowcl-advanced-rewards';

	public static function intent( $user_id, $points, $key, $action, $description, $order_id = 0, array $facts = array() ) {
		return array( 'user_id' => (int) $user_id, 'points' => (int) $points, 'key' => $key, 'action' => $action,
			'description' => $description, 'order_id' => (int) $order_id, 'facts' => $facts,
			'expiration_policy' => null, 'expired_date' => null );
	}

	public static function queue( $hook, array $args ) {
		if ( ! function_exists( 'as_get_scheduled_actions' ) || ! function_exists( 'as_schedule_single_action' ) ) { throw new RuntimeException( 'advanced_reward_retry_unavailable' ); }
		if ( as_get_scheduled_actions( array( 'hook' => $hook, 'group' => self::GROUP, 'args' => $args, 'status' => 'pending', 'per_page' => 1 ), 'ids' ) ) { return; }
		if ( ! as_schedule_single_action( time() + 5, $hook, $args, self::GROUP ) ) { throw new RuntimeException( 'advanced_reward_retry_not_saved' ); }
	}

	public static function order( $order, $producer, $marker, $discover ) {
		if ( ! YOWCL_Free_Core::owns() ) { return; }
		$id = $order instanceof WC_Order ? $order->get_id() : (int) $order;
		if ( $id <= 0 ) { return; }
		$retry = static function () use ( $id, $producer ) { self::queue( self::ORDER_HOOK, array( $id, $producer ) ); };
		return YOWCL_Order_Rewards::locked( $id, static function ( $order, $owner ) use ( $producer, $marker, $discover, $retry ) {
			$key = '_yowcl_advanced_terms_' . $producer;
			$receipt = $order->get_meta( $key, true );
            if ( 'first_purchase' !== $producer || '_yowcl_first_purchase_awarded' !== $marker ) { throw new RuntimeException( 'invalid_advanced_reward_producer' ); }
            if ( '' === $receipt && $order->get_user_id() > 0 ) {
                $committed = YOWCL_Points_Transaction::find( 'reward:first_purchase:' . $order->get_user_id() );
                if ( $committed ) {
                    $intent = self::intent( (int) $order->get_user_id(), (int) $committed['available_delta'], $committed['event_key'], 'first_purchase_reward', $committed['description'], (int) $committed['order_id'] );
                    $status = self::deliver( $intent, $marker, $owner );
                    if ( 'busy' === $status ) { $retry(); }
                    elseif ( 'failed' !== $status ) { $owner(); YOWCL_Order_Rewards::meta( $order, $marker, 'yes' ); }
                    return 'failed' !== $status;
                }
            }
            if ( '' === $receipt ) {
                // Serialize fresh admission and durable terms with the effective-enable save.
                $receipt = YOWCL_Free_Migrations::locked( static function () use ( $discover, $order, $owner, $key ) {
                    $terms = $discover( $order );
                    if ( ! empty( $terms['events'] ) ) { $owner(); YOWCL_Order_Rewards::meta( $order, $key, $terms ); }
                    return $terms;
                } );
                if ( empty( $receipt['events'] ) ) {
                    if ( null !== $receipt['terminal'] ) { $owner(); YOWCL_Order_Rewards::meta( $order, $marker, $receipt['terminal'] ); }
                    return;
                }
            }
			if ( ! is_array( $receipt ) || ! isset( $receipt['events'] ) || ! is_array( $receipt['events'] ) || 1 !== count( $receipt['events'] ) || 'yes' !== ( $receipt['terminal'] ?? null ) ) { throw new RuntimeException( 'advanced_reward_terms_invalid' ); }
            foreach ( $receipt['events'] as $intent ) {
                self::validate( $intent );
                if ( (int) $intent['user_id'] !== (int) $order->get_user_id() || (int) $intent['order_id'] !== (int) $order->get_id() ) { throw new RuntimeException( 'advanced_reward_terms_invalid' ); }
            }
			// Persist recovery ownership before any value; even a killed hook leaves delivery.
			foreach ( $receipt['events'] as $intent ) {
				if ( ! YOWCL_Points_Transaction::find( $intent['key'] ) ) {
					$args = array( (int) $order->get_id(), $producer );
					if ( ! as_get_scheduled_actions( array( 'hook' => self::ORDER_HOOK, 'group' => self::GROUP, 'args' => $args, 'status' => 'in-progress', 'per_page' => 1 ), 'ids' ) ) { $retry(); }
					break;
				}
			}
			$complete = true; $busy = false;
			foreach ( $receipt['events'] as $intent ) {
				$owner();
				$status = self::deliver( $intent, $marker, $owner );
				if ( in_array( $status, array( 'busy', 'failed' ), true ) ) { $complete = false; }
				if ( 'busy' === $status ) { $busy = true; }
			}
			if ( $busy ) { $retry(); }
			if ( $complete && null !== $receipt['terminal'] ) {
				$owner();
				YOWCL_Order_Rewards::meta( $order, $marker, $receipt['terminal'] );
			}
			return $complete || $busy;
		}, array(), $retry );
	}

	public static function retry_order( $id, $producer ) {
        if ( 'first_purchase' !== $producer ) { throw new RuntimeException( 'invalid_advanced_reward_producer' ); }
        if ( false === ( new YOWCL_Extra_Points_First_Purchase( false ) )->maybe_award_on_order( $id ) ) { throw new RuntimeException( 'advanced_order_delivery_failed' ); }
    }

	/** The legacy no-backfill check is serialized with the new event, not a cached precheck. */
	private static function guard( array $intent, $db, $marker ) {
		global $wpdb;
		$user = $intent['user_id'];
		$meta = self::projection( $intent );
		if ( $meta ) {
			if ( self::legacy_marked( $db, $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key = %s FOR UPDATE", $user, $meta[0] ) ) ) { return false; }
		}
		if ( $intent['order_id'] ) {
			$hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
			$table = $hpos ? $wpdb->prefix . 'wc_orders' : $wpdb->posts;
			$column = $hpos ? 'status' : 'post_status';
			if ( 'InnoDB' !== YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) ) ) { throw new DomainException( 'advanced_reward_order_storage_required' ); }
			// Lock the native status row through commit so cancellation cannot overtake it.
			$status = YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( "SELECT {$column} FROM {$table} WHERE id = %d FOR UPDATE", $intent['order_id'] ) );
			if ( ! in_array( $status, array( 'wc-processing', 'wc-completed' ), true ) ) { return false; }
			$table = $hpos ? $wpdb->prefix . 'wc_orders_meta' : $wpdb->postmeta;
			$id_column = $hpos ? 'order_id' : 'post_id';
			if ( self::legacy_marked( $db, $wpdb->prepare( "SELECT meta_value FROM {$table} WHERE {$id_column} = %d AND meta_key = %s FOR UPDATE", $intent['order_id'], $marker ), true ) ) { return false; }
		}
		return true;
	}

    /** Every persisted row participates: a false duplicate cannot hide legacy suppression. */
    private static function legacy_marked( $db, $sql, $order_marker = false ) {
        $result = YOWCL_Points_Lock::query( $db, $sql );
        try {
            while ( $row = $result->fetch_assoc() ) {
                $value = maybe_unserialize( $row['meta_value'] );
                if ( $order_marker ? 'yes' === $value : (bool) $value ) { return true; }
            }
            return false;
        } finally { $result->free(); }
    }

	private static function deliver( array $intent, $marker = '', $owner = null ) {
		try {
            self::validate( $intent );
			$row = YOWCL_Points_Transaction::find( $intent['key'] );
			$result = array( 'status' => 'already_applied' );
			if ( ! $row ) {
				YOWCL_Core_Rewards::checkpoint( 'prepared', $intent['key'] );
				if ( $owner ) { $owner(); }
				$result = YOWCL_Points_Transaction::reward( $intent['user_id'], $intent['points'], $intent['key'], array(
					'action' => $intent['action'], 'order_id' => $intent['order_id'], 'description' => $intent['description'], 'expired_date' => $intent['expired_date'], 'expiration_policy' => $intent['expiration_policy'] ?? null,
				), static function ( $db ) use ( $intent, $marker ) { return self::guard( $intent, $db, $marker ); } );
				// A different order can win a user-wide event between the lookup and commit.
				$row = YOWCL_Points_Transaction::find( $intent['key'] );
				if ( ! $row ) {
					if ( 'skipped' === $result['status'] ) { return 'skipped'; }
					if ( 'busy' === $result['status'] ) { return 'busy'; }
					YOWCL_Core_Rewards::report( $intent['key'], new DomainException( 'advanced_reward_value_failed:' . $result['code'] ) );
					return 'failed';
				}
				YOWCL_Core_Rewards::checkpoint( 'committed', $intent['key'] );
			}
			if ( (int) $row['user_id'] !== $intent['user_id'] || $row['action'] !== $intent['action'] ) { throw new RuntimeException( 'advanced_reward_event_conflict' ); }
			$user_wide = 'first_purchase_reward' === $intent['action'];
			if ( ! $user_wide && (int) $row['order_id'] !== $intent['order_id'] ) { throw new RuntimeException( 'advanced_reward_event_conflict' ); }
            if ( ! in_array( YOWCL_Ledger_V2::inspect( $row )['kind'], array( 'v2', 'transaction_pre_v2' ), true ) || (int) $row['available_delta'] <= 0 || $row['available_delta'] !== $row['earning_delta'] || (int) $row['order_id'] <= 0 ) { throw new RuntimeException( 'advanced_reward_event_conflict' ); }
			// A verified winner is completed recovery, even if this contender lost at commit.
			if ( 'applied' !== $result['status'] ) { $result['status'] = 'already_applied'; }
			YOWCL_Core_Rewards::finish( $row, static function () use ( $intent, $row ) {
				$meta = self::projection( $intent );
				if ( $meta ) { YOWCL_Core_Rewards::user_marker( $intent['user_id'], $meta[0], $meta[1], $meta[2] ); }
				// Repair the winning order, including after a different first-order callback.
				if ( 'first_purchase_reward' === $intent['action'] ) {
					$order = wc_get_order( $row['order_id'] );
					if ( ! $order || (int) $order->get_user_id() !== (int) $intent['user_id'] ) { throw new RuntimeException( 'advanced_reward_winner_missing' ); }
					YOWCL_Order_Rewards::meta( $order, '_yowcl_first_purchase_awarded', 'yes' );
				}
			} );
			if ( 'applied' === $result['status'] && class_exists( 'YOWCL_Points_Events' ) ) {
				try {
                    YOWCL_Points_Events::context_reward( $intent['user_id'], (int) $row['available_delta'], $result['new_points'], $row['order_id'] ?: null,
                        array( 'context'=>$row['action'], 'reward_label'=>$intent['facts']['label'] ?? '', 'message'=>$intent['facts']['message'] ?? '' ) );
				} catch ( Throwable $observer ) { YOWCL_Core_Rewards::report( $intent['key'], $observer ); }
			}
			return $result['status'];
		} catch ( Throwable $e ) {
			YOWCL_Core_Rewards::report( $intent['key'], $e );
			return $e instanceof DomainException ? 'failed' : 'busy'; // Never authorize fresh value after an uncertain lookup.
		}
	}

    private static function validate( $intent ) {
        if ( ! is_array( $intent ) || ! is_int( $intent['user_id'] ?? null ) || $intent['user_id'] <= 0 || ! is_int( $intent['order_id'] ?? null ) || $intent['order_id'] <= 0 || ! is_int( $intent['points'] ?? null ) || $intent['points'] <= 0 || $intent['points'] > 99999999 || 'first_purchase_reward' !== ( $intent['action'] ?? '' ) || 'reward:first_purchase:' . $intent['user_id'] !== ( $intent['key'] ?? '' ) || ! is_string( $intent['description'] ?? null ) || ! is_array( $intent['facts'] ?? null ) || null !== ( $intent['expired_date'] ?? null ) || null !== ( $intent['expiration_policy'] ?? null ) ) { throw new DomainException( 'advanced_reward_terms_invalid' ); }
    }
	private static function projection( array $intent ) { return array( 'first_purchase_rewarded', 1, '' ); }

	/** Native object and equivalent filtered arrays; malformed aggregates never authorize value. */
	public static function pagination( $result, $limit = 100, $page = 1 ) {
		if ( ! is_int( $limit ) || $limit <= 0 || ! is_int( $page ) || $page <= 0 ) { throw new RuntimeException( 'advanced_reward_pagination_invalid' ); }
		if ( is_object( $result ) ) { $result = get_object_vars( $result ); }
		if ( ! is_array( $result ) || ! isset( $result['orders'], $result['total'], $result['max_num_pages'] ) || ! is_array( $result['orders'] ) ||
			! is_int( $result['total'] ) || $result['total'] < 0 || ! is_int( $result['max_num_pages'] ) || $result['max_num_pages'] < 0 ||
			( $result['total'] > 0 && ! $result['orders'] ) || count( $result['orders'] ) > $result['total'] || ( $result['total'] > 0 && $result['max_num_pages'] < 1 ) || ( 0 === $result['total'] && ( $result['orders'] || $result['max_num_pages'] ) ) ) {
			throw new RuntimeException( 'advanced_reward_pagination_invalid' );
		}
		$pages = (int) ceil( $result['total'] / $limit );
		$expected = max( 0, min( $limit, $result['total'] - ( $page - 1 ) * $limit ) );
		if ( $result['max_num_pages'] !== $pages || count( $result['orders'] ) !== $expected || count( array_unique( $result['orders'] ) ) !== $expected ) {
			throw new RuntimeException( 'advanced_reward_pagination_invalid' );
		}
		foreach ( $result['orders'] as $id ) { if ( ! is_numeric( $id ) || (int) $id <= 0 || (string) (int) $id !== (string) $id ) { throw new RuntimeException( 'advanced_reward_pagination_invalid' ); } }
		return $result;
	}
}

add_action( YOWCL_Advanced_Rewards::ORDER_HOOK, array( 'YOWCL_Advanced_Rewards', 'retry_order' ), 10, 2 );

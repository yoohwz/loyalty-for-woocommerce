<?php

defined( 'ABSPATH' ) || exit;

/** Bounded link/referrer kernel; Free policy supplies immutable admission. */
class YOWCL_Referral_Rewards {
	const TERMS = '_yowcl_referral_terms';
	const IDENTITY = '_yowcl_referral_customer_identity';
	const USER_WINNER = '_yowcl_referral_first_order';
	const QUALIFIED = '_yowcl_referral_qualified';
	const TERMINAL = '_yowcl_referral_terminal';
	const RETRY = 'yowcl_referral_order_retry';
	const GROUP = 'yowcl-referrals';
	public static function queue( $id, $intent = '' ) {
		$args = array( (int) $id, (string) $intent );
		if ( ! function_exists( 'as_get_scheduled_actions' ) || ! function_exists( 'as_schedule_single_action' ) ) { throw new RuntimeException( 'referral_retry_unavailable' ); }
		if ( as_get_scheduled_actions( array( 'hook' => self::RETRY, 'group' => self::GROUP, 'args' => $args, 'status' => 'pending', 'per_page' => 1 ), 'ids' ) ) { return; }
		if ( ! as_schedule_single_action( time() + 5, self::RETRY, $args, self::GROUP ) ) { throw new RuntimeException( 'referral_retry_not_saved' ); }
	}

	/** Final checkout only: never called from Store API GET/PUT draft staging. */
	public static function attach( $order ) {
		if ( ! $order instanceof WC_Order || ! YOWCL_Free_Core::owns() ) { return; }
		$done = false;
		YOWCL_Order_Rewards::locked( $order->get_id(), static function ( $fresh, $owner ) use ( &$done ) {
			if ( null === YOWCL_Free_Referral::receipt( $fresh ) && ! YOWCL_Free_Referral::legacy_attribution( $fresh ) ) {
				$terms = YOWCL_Free_Migrations::locked( static function () use ( $fresh ) { return self::discover( $fresh ); } );
				$owner(); self::store_terms( $fresh, $terms );
			}
			$done = true;
		}, array(), static function () {} );
		if ( ! $done ) { throw new RuntimeException( 'referral_attribution_not_saved' ); }
		self::process( $order->get_id() );
	}

	/** Monotone qualification evidence is retained even when order delivery is busy. */
	public static function record_transition( $id, $new_status ) {
		if ( ! YOWCL_Free_Core::owns() ) { return; }
		$order = wc_get_order( $id );
		if ( ! $order ) { return; }
        $receipt = YOWCL_Free_Referral::receipt( $order );
        if ( in_array( $new_status, array( 'failed', 'cancelled', 'refunded' ), true ) ) { YOWCL_Order_Rewards::meta( $order, self::TERMINAL, 'yes' ); }
        if ( in_array( $new_status, array( 'processing', 'completed' ), true ) ) {
            self::qualify( $order, null !== $receipt ? $receipt : self::qualification_terms( $order, array() ) );
        }
    }

    private static function qualification_terms( $order, array $rules ) {
        $qualified = YOWCL_Free_Referral::qualification( $order );
        if ( is_array( $qualified ) ) { return $qualified; }
        return array( 'identity'=>self::identity( $order ), 'referee'=>(int) $order->get_user_id(), 'email'=>'', 'frequency'=>'first_order', 'award_statuses'=>array( 'wc-processing','wc-completed' ) );
    }

	private static function qualify( $order, array $terms ) {
		if ( YOWCL_Free_Referral::qualification( $order ) ) { return; }
		$qualified = array_intersect_key( $terms, array_flip( array( 'identity', 'referee', 'email', 'frequency', 'award_statuses' ) ) );
		YOWCL_Order_Rewards::meta( $order, self::IDENTITY, $qualified['identity'] );
		YOWCL_Order_Rewards::meta( $order, self::QUALIFIED, $qualified );
	}

    private static function identity( $order ) { return $order->get_user_id() > 0 ? 'user:' . $order->get_user_id() : ''; }

    private static function discover( $order ) { return YOWCL_Free_Referral::discover( $order ); }

	private static function store_terms( $order, array $terms ) {
		YOWCL_Order_Rewards::meta( $order, self::TERMS, $terms );
		YOWCL_Order_Rewards::meta( $order, self::IDENTITY, $terms['identity'] );
		if ( $terms['referrer'] && 'link' === $terms['channel'] ) {
			YOWCL_Order_Rewards::meta( $order, '_yo_' . $terms['channel'] . '_referrer_user_id', $terms['referrer'] );
			YOWCL_Order_Rewards::meta( $order, '_yo_referrer_user_id', $terms['referrer'] );
		}
	}

    private static function terms( $order ) { return YOWCL_Free_Referral::terms( $order ); }

	private static function storage() {
		global $wpdb;
		$hpos = \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		return array( $hpos ? $wpdb->prefix . 'wc_orders' : $wpdb->posts,
			$hpos ? 'status' : 'post_status', $hpos ? $wpdb->prefix . 'wc_orders_meta' : $wpdb->postmeta,
			$hpos ? 'order_id' : 'post_id', $hpos ? 'date_created_gmt' : 'post_date_gmt', $hpos );
	}

	private static function status( $db, $id, $lock = false ) {
		global $wpdb;
		list( $table, $column ) = self::storage();
		if ( 'InnoDB' !== YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $table ) ) ) { throw new RuntimeException( 'referral_order_storage_required' ); }
		return YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( "SELECT {$column} FROM {$table} WHERE id = %d" . ( $lock ? ' FOR UPDATE' : '' ), $id ) );
	}

	/** The customer lock protects one eligibility witness, independently of points delivery. */
	private static function winner( $order, array $terms, $owner ) {
		if ( '' === $terms['identity'] ) { return 0; }
		global $wpdb;
		$db = $wpdb->dbh;
		$name = 'yowclrf_' . hash( 'sha224', DB_NAME . ':' . $wpdb->prefix . ':' . $terms['identity'] );
		if ( '1' !== (string) YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $name ) ) ) { throw new RuntimeException( 'referral_customer_busy' ); }
		try {
			$owner();
			list( $table, $status, $meta, $id_column, $date, $hpos ) = self::storage();
			$user = $terms['referee'];
            $claims = YOWCL_Points_Lock::query( $db, $wpdb->prepare( "SELECT meta_value FROM {$wpdb->usermeta} WHERE user_id=%d AND meta_key=%s", $user, self::USER_WINNER ) );
            $values = array(); while ( $r = $claims->fetch_assoc() ) { $values[] = $r['meta_value']; } $claims->free();
            if ( $values ) {
                if ( 1 !== count( $values ) || ! ctype_digit( $values[0] ) || (int) $values[0] <= 0 ) { throw new DomainException( 'referral_claim_invalid' ); }
                return self::deliver_winner( (int) $values[0], $order->get_id() );
            }
			// Query customer orders before referral filtering: a non-referred first order also wins.
				$customer = $hpos ? $wpdb->prepare( 'o.customer_id = %d', $user ) : $wpdb->prepare( "EXISTS (SELECT 1 FROM {$meta} u WHERE u.{$id_column} = o.id AND u.meta_key = '_customer_user' AND u.meta_value = %s)", (string) $user );
				$customer = $wpdb->prepare( "(EXISTS (SELECT 1 FROM {$meta} i WHERE i.{$id_column} = o.id AND i.meta_key = %s AND i.meta_value = %s) OR ({$customer}))", self::IDENTITY, $terms['identity'] );
			$current_rules = array( 'award_statuses'=>array( 'wc-processing','wc-completed' ) );
			$unreceipted_statuses = array_values( array_unique( array_merge( $current_rules['award_statuses'], array( 'wc-processing', 'wc-completed' ) ) ) );
			$statuses = array_values( array_unique( array_merge( $terms['award_statuses'], $unreceipted_statuses ) ) );
			$in = $wpdb->prepare( implode( ',', array_fill( 0, count( $statuses ), '%s' ) ), $statuses );
			$type = $hpos ? "o.type = 'shop_order'" : "o.post_type = 'shop_order'";
			$query = YOWCL_Points_Lock::query( $db, "SELECT o.id, o.{$status} AS status, EXISTS(SELECT 1 FROM {$meta} q WHERE q.{$id_column}=o.id AND q.meta_key='_yowcl_referral_qualified') AS has_qualified, EXISTS(SELECT 1 FROM {$meta} r WHERE r.{$id_column}=o.id AND r.meta_key='_yowcl_referral_terms') AS has_terms, EXISTS(SELECT 1 FROM {$meta} i WHERE i.{$id_column}=o.id AND i.meta_key='_yowcl_referral_customer_identity') AS has_identity FROM {$table} o WHERE {$type} AND {$customer} AND (o.{$status} IN ({$in}) OR EXISTS (SELECT 1 FROM {$meta} q WHERE q.{$id_column} = o.id AND q.meta_key = '_yowcl_referral_qualified') OR EXISTS (SELECT 1 FROM {$meta} r WHERE r.{$id_column} = o.id AND r.meta_key = '_yowcl_referral_terms')) ORDER BY o.{$date} ASC, o.id ASC LIMIT 10001" );
			if ( $query->num_rows > 10000 ) { $query->free(); throw new RuntimeException( 'referral_history_incomplete' ); }
			$winner = 0;
			while ( $row = mysqli_fetch_assoc( $query ) ) {
				$candidate = wc_get_order( (int) $row['id'] );
				if ( ! $candidate ) { mysqli_free_result( $query ); throw new RuntimeException( 'referral_candidate_unavailable' ); }
				$candidate->read_meta_data( true );
				if ( ! $candidate->get_date_created() || $candidate->get_date_created()->getTimestamp() <= 0 ) { $query->free(); throw new RuntimeException( 'referral_history_date_invalid' ); }
				$receipt = YOWCL_Free_Referral::receipt( $candidate );
                $qualification = YOWCL_Free_Referral::qualification( $candidate );
                if ( ( $row['has_qualified'] && ! $qualification ) || ( $row['has_terms'] && null === $receipt ) || ( $row['has_identity'] && ! $candidate->get_meta( self::IDENTITY, false, 'edit' ) ) ) { $query->free(); throw new RuntimeException( 'referral_history_metadata_unavailable' ); }
				foreach ( array( $receipt, $qualification ) as $frozen ) {
					if ( is_array( $frozen ) && ( $frozen['identity'] ?? '' ) !== $terms['identity'] ) { continue 2; }
				}
				$award_statuses = is_array( $receipt ) ? ( $receipt['award_statuses'] ?? array() ) : ( null === $receipt ? $unreceipted_statuses : array() );
				if ( $qualification || in_array( $row['status'], $award_statuses, true ) || ( null === $receipt && in_array( $row['status'], array( 'wc-processing', 'wc-completed' ), true ) ) ) { $winner = (int) $row['id']; break; }
			}
			mysqli_free_result( $query );
			if ( ! $winner ) { return 0; }
			$owner();
			// Verify customer ownership on the original connection before retaining the witness.
			if ( $wpdb->dbh !== $db || (string) YOWCL_Points_Lock::scalar( $db, 'SELECT CONNECTION_ID()' ) !== (string) YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT IS_USED_LOCK(%s)', $name ) ) ) { throw new RuntimeException( 'referral_customer_ownership_lost' ); }
				YOWCL_Points_Lock::query( $db, $wpdb->prepare( "INSERT INTO {$wpdb->usermeta} (user_id, meta_key, meta_value) VALUES (%d, %s, %s)", $user, self::USER_WINNER, (string) $winner ) );
				wp_cache_delete( $user, 'user_meta' );
			YOWCL_Core_Rewards::checkpoint( 'referral_claimed', 'referral:customer:' . $terms['identity'] );
			return self::deliver_winner( $winner, $order->get_id() );
		} finally { try { YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); } catch ( Throwable $ignored ) {} }
	}

	/** A retained claim must also recover delivery lost after witness persistence. */
	private static function deliver_winner( $winner, $contender ) {
		// A deleted registered winner still consumes eligibility without queuing a missing order.
		if ( $winner !== (int) $contender && wc_get_order( $winner ) ) { self::queue( $winner ); }
		return $winner;
	}

	/** Woo CRUD must not reconnect and publish a guest witness after customer ownership was lost. */
	public static function process( $id, $intent = '' ) {
		if ( ! YOWCL_Free_Core::owns() || (int) $id <= 0 ) { return; }
		if ( '' !== $intent ) { self::record_transition( $id, $intent ); }
		$done = null; $deferred = false;
		$retry = static function ( $error = null ) use ( $id, $intent, &$deferred, &$done ) {

            if ( $error instanceof DomainException ) { $done = true; return; }
			self::queue( $id, $intent ); $deferred = true;
		};
		YOWCL_Order_Rewards::locked( (int) $id, static function ( $order, $owner ) use ( $id, $intent, $retry, &$done ) {
			global $wpdb;
			$rules = array( 'reward_frequency'=>'first_order','award_statuses'=>array( 'wc-processing','wc-completed' ),'reversal_statuses'=>array( 'wc-failed','wc-cancelled','wc-refunded' ) );
			$raw = YOWCL_Free_Referral::receipt( $order );
			$status = self::status( $wpdb->dbh, $id );
			if ( null === $raw ) {
				if ( 'first_order' === $rules['reward_frequency'] && in_array( $status, $rules['award_statuses'], true ) ) { self::qualify( $order, self::qualification_terms( $order, $rules ) ); }
				$qualified = $order->get_meta( self::QUALIFIED, true );
				if ( is_array( $qualified ) && 'first_order' === $qualified['frequency'] ) { self::retain_delivery( $id, $intent ); self::winner( $order, $qualified, $owner ); }
                // Only final checkout attachment can read a cookie or admit positive terms.
                if ( in_array( $status, $rules['reversal_statuses'], true ) ) { YOWCL_Order_Rewards::meta( $order, self::TERMINAL, 'yes' ); }
                $done = true; return;
            }
			$terms = $raw;
            // A proven single component recovers/reverses before eligibility storage or settings.
            if ( $terms['events'] ) {
                $event = reset( $terms['events'] ); $committed = YOWCL_Points_Transaction::find( $event['key'] );
                if ( $committed ) {
                    self::prove( $committed, $event, $id );
                    if ( YOWCL_Free_Referral::marked( $order, self::TERMINAL ) || in_array( $status, $terms['reversal_statuses'], true ) || in_array( 'wc-' . $intent, $terms['reversal_statuses'], true ) ) {
                        $owner(); YOWCL_Order_Rewards::meta( $order, self::TERMINAL, 'yes' ); self::retain_delivery( $id, $intent ); self::reverse( $order, $terms, $owner );
                    } else { self::finish( $order, $committed, $event ); $owner(); YOWCL_Order_Rewards::meta( $order, '_yo_link_referral_awarded', 'yes' ); }
                    $done = true; return;
                }
            }
			// Repair a interrupted attachment before any eligibility query/value.
			$owner(); YOWCL_Order_Rewards::meta( $order, self::IDENTITY, $terms['identity'] );
			// Retained native qualification survives a later terminal status before delivery.
			if ( in_array( $status, $terms['award_statuses'], true ) || in_array( 'wc-' . $intent, $terms['award_statuses'], true ) ) {
				$owner(); self::qualify( $order, $terms );
			}
			if ( 'first_order' === $terms['frequency'] && $order->get_meta( self::QUALIFIED, true ) ) {
				self::retain_delivery( $id, $intent );
				$winner = self::winner( $order, $terms, $owner );
			}
			$reverse = YOWCL_Free_Referral::marked( $order, self::TERMINAL ) || in_array( $status, $terms['reversal_statuses'], true ) || in_array( 'wc-' . $intent, $terms['reversal_statuses'], true );
			if ( $reverse ) {
				$owner(); YOWCL_Order_Rewards::meta( $order, self::TERMINAL, 'yes' );
				self::retain_delivery( $id, $intent );
				self::reverse( $order, $terms, $owner ); $done = true; return;
			}
			// Current eligibility gates new value only; committed events retain finalization.
			$admit = in_array( $status, $terms['award_statuses'], true ) && ( 'first_order' !== $terms['frequency'] || ( $winner ?? 0 ) === (int) $id );
			if ( ! $terms['events'] ) { $done = true; return; }
			$complete = true;
			$expiration = null; $expiration_ready = false;
			foreach ( $terms['events'] as $event ) {
				$owner();
				$row = YOWCL_Points_Transaction::find( $event['key'] );
				if ( ! $row && YOWCL_Free_Referral::marked( $order, $event['marker'] ) ) { continue; } // Late historical evidence is still a no-backfill guard.
				if ( ! $row && ! $admit ) { $complete = false; continue; }
				self::retain_delivery( $id, $intent );
				if ( ! $row ) {
					if ( ! $expiration_ready ) { $expiration = null; $expiration_ready = true; }
					YOWCL_Core_Rewards::checkpoint( 'prepared', $event['key'] );
					$result = YOWCL_Points_Transaction::reward( $event['user_id'], $event['points'], $event['key'], array( 'action' => $event['action'], 'order_id' => (int) $id, 'description' => $event['description'], 'expired_date' => $expiration, 'expiration_policy' => $terms['expiration_policy'] ), static function ( $db ) use ( $id, $terms, $owner ) {
						$owner(); $status = self::status( $db, $id, true );
                        if ( ! YOWCL_Points_Lock::scalar( $db, $GLOBALS['wpdb']->prepare( "SELECT ID FROM {$GLOBALS['wpdb']->users} WHERE ID=%d FOR UPDATE", $terms['referrer'] ) ) ) { return false; }
						global $wpdb;
						list( , , $meta, $id_column ) = self::storage();
						$terminal = YOWCL_Free_Referral::native_marked( $db, $meta, $id_column, $id, self::TERMINAL );
                        if ( YOWCL_Free_Referral::native_marked( $db, $meta, $id_column, $id, '_yo_link_referrer_awarded' ) ) { return false; }
						return ! $terminal && in_array( $status, $terms['award_statuses'], true ) && ! in_array( $status, $terms['reversal_statuses'], true );
					} );
					if ( 'skipped' === $result['status'] ) { $retry(); $done = true; return; }
					self::require_result( $result );
					$row = YOWCL_Points_Transaction::find( $event['key'] );
					if ( 'applied' === $result['status'] ) { self::notify( $row, $result ); }
				}
				self::prove( $row, $event, $id );
				YOWCL_Core_Rewards::checkpoint( 'committed', $event['key'] );
				self::finish( $order, $row, $event );
			}
			if ( ! $complete ) { $done = true; return; }
			$owner();

			YOWCL_Order_Rewards::meta( $order, '_yo_link_referral_awarded', 'yes' );
			$done = true;
		}, array(), $retry );
		if ( true !== $done && ! $deferred ) { throw new RuntimeException( 'referral_delivery_failed' ); }
	}

	private static function retain_delivery( $id, $intent ) {
		$args = array( (int) $id, (string) $intent );
		if ( ! as_get_scheduled_actions( array( 'hook' => self::RETRY, 'group' => self::GROUP, 'args' => $args, 'status' => 'in-progress', 'per_page' => 1 ), 'ids' ) ) { self::queue( $id, $intent ); }
	}

	private static function require_result( array $result ) {
		if ( ! in_array( $result['status'], array( 'applied', 'already_applied' ), true ) ) { throw new RuntimeException( 'referral_value_' . $result['status'] . ':' . $result['code'] ); }
	}

	private static function prove( $row, array $event, $id ) {
		if ( ! $row || ! in_array( YOWCL_Ledger_V2::inspect( $row )['kind'], array( 'v2','transaction_pre_v2' ), true ) || (int) $row['user_id'] !== $event['user_id'] || $row['action'] !== $event['action'] || (int) $row['order_id'] !== (int) $id || (int) $row['available_delta'] !== $event['points'] || (int) $row['earning_delta'] !== $event['points'] ) { throw new RuntimeException( 'referral_event_conflict' ); }
	}

	private static function finish( $order, array $row, array $event ) {
		YOWCL_Core_Rewards::finish( $row, static function () use ( $order, $row, $event ) {
			foreach ( array( '' => 'yes', '_user_id' => (int) $row['user_id'], '_amount' => (int) $row['available_delta'], '_action' => $row['action'], '_log_id' => (int) $row['id'] ) as $suffix => $value ) { YOWCL_Order_Rewards::meta( $order, $event['marker'] . $suffix, $value ); }
		} );
	}

    private static function notify( array $row, array $result ) {
        try { YOWCL_Points_Events::reward( (int) $row['user_id'], (int) $row['available_delta'], $result['new_points'], (int) $row['order_id'] ); }
        catch ( Throwable $e ) { YOWCL_Core_Rewards::report( $row['event_key'], $e ); }
    }

	private static function reverse( $order, array $terms, $owner ) {
		foreach ( $terms['events'] as $event ) {
			$owner(); $row = YOWCL_Points_Transaction::find( $event['key'] );
			if ( ! $row ) { continue; } // Terminal order blocks the missing positive component forever.
			self::prove( $row, $event, $order->get_id() );
			$result = YOWCL_Points_Transaction::reverse_referral_reward( $event['user_id'], $order->get_id(), $event['key'], $event['action'], sprintf( __( 'Referral reward reversed for order %s', 'loyalty-for-woocommerce' ), $order->get_order_number() ) );
			self::require_result( $result );
			YOWCL_Core_Rewards::checkpoint( 'committed', $event['key'] . ':reversal' );
			YOWCL_Order_Rewards::meta( $order, $event['marker'] . '_reversed', 'yes' );
			if ( 'applied' === $result['status'] ) {
				try { $reversed = YOWCL_Points_Transaction::find( $event['key'] . ':reversal' ); YOWCL_Points_Events::deduct( $event['user_id'], -(int) $reversed['available_delta'], $result['new_points'], $order->get_id() ); } catch ( Throwable $e ) { YOWCL_Core_Rewards::report( $event['key'], $e ); }
			}
		}
		$owner(); YOWCL_Order_Rewards::meta( $order, '_yo_referral_rewards_reversed', 'yes' );
	}

}

add_action( YOWCL_Referral_Rewards::RETRY, array( 'YOWCL_Referral_Rewards', 'process' ), 10, 2 );

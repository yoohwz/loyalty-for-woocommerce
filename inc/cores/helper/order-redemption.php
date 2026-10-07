<?php

defined( 'ABSPATH' ) || exit;

/** One bounded order redemption, with terminal compensation; not a second points ledger. */
class YOWCL_Order_Redemption {
	const META = '_yowcl_checkout_redemption_id';
	private static $held = array();
	private static $payments = array();
	private static $request_lock = null;

	public static function register() {
		add_action( 'woocommerce_cart_emptied', array( __CLASS__, 'retire_session' ) );
		foreach ( array( 'on-hold', 'processing', 'completed' ) as $status ) {
			add_action( 'woocommerce_order_status_' . $status, array( __CLASS__, 'retire_order_session' ), PHP_INT_MAX );
		}
		add_action( 'woocommerce_review_order_before_submit', array( __CLASS__, 'checkout_field' ) );
		add_filter( 'woocommerce_create_order', array( __CLASS__, 'recover_checkout' ), 5 );
		add_action( 'woocommerce_resume_order', array( __CLASS__, 'reject_resume' ), 1 );
		add_action( 'woocommerce_checkout_create_order', array( __CLASS__, 'prepare' ), PHP_INT_MAX, 2 );
		add_action( 'woocommerce_checkout_order_created', array( __CLASS__, 'commit' ), PHP_INT_MAX );
		add_action( 'woocommerce_checkout_order_exception', array( __CLASS__, 'checkout_exception' ), 1 );
		foreach ( array( 'failed', 'cancelled', 'refunded' ) as $status ) {
			add_action( 'woocommerce_order_status_' . $status, array( __CLASS__, 'return_points' ), 1 );
		}
		add_filter( 'woocommerce_order_needs_payment', array( __CLASS__, 'needs_payment' ), PHP_INT_MAX, 2 );
		add_action( 'woocommerce_before_pay_action', array( __CLASS__, 'before_pay' ), 1 );
		add_action( 'woocommerce_pre_payment_complete', array( __CLASS__, 'payment_lock' ), 1, 2 );
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'payment_confirmed' ), PHP_INT_MIN );
		add_action( 'woocommerce_payment_complete', array( __CLASS__, 'payment_finished' ), PHP_INT_MAX );
		foreach ( array( 'failed', 'cancelled', 'refunded', 'pending', 'on-hold', 'processing', 'completed' ) as $status ) {
			add_action( 'woocommerce_payment_complete_order_status_' . $status, array( __CLASS__, 'payment_confirmed' ), PHP_INT_MIN );
			add_action( 'woocommerce_payment_complete_order_status_' . $status, array( __CLASS__, 'payment_finished' ), PHP_INT_MAX );
		}
		foreach ( array( 'cod', 'bacs', 'cheque' ) as $gateway ) {
			add_filter( 'woocommerce_' . $gateway . '_process_payment_order_status', array( __CLASS__, 'gateway_status' ), PHP_INT_MAX, 2 );
		}
		add_action( 'woocommerce_order_status_changed', array( __CLASS__, 'gateway_finished' ), PHP_INT_MAX, 4 );
		add_action( 'woocommerce_before_order_object_save', array( __CLASS__, 'payment_before_save' ), 1 );
		add_action( 'woocommerce_after_order_object_save', array( __CLASS__, 'payment_saved' ), 1 );
		foreach ( array( 'on-hold', 'processing', 'completed' ) as $status ) {
			add_action( 'woocommerce_order_status_' . $status, array( __CLASS__, 'payment_transition' ), PHP_INT_MIN, 2 );
		}
		add_filter( 'woocommerce_payment_complete_order_status', array( __CLASS__, 'payment_status' ), PHP_INT_MAX, 3 );
	}

	public static function session_owner() {
		return hash( 'sha256', get_current_user_id() . ':' . wp_get_session_token() );
	}

	public static function checkout_field() {
		self::retire_paid_session();
		$id = WC()->session ? WC()->session->get( 'yowcl_checkout_id' ) : '';
		if ( self::valid_id( $id ) ) {
			$record = self::record( $id );
			if ( $record && in_array( $record['state'], array( 'returning', 'returned' ), true ) ) { $id = ''; }
		}
		if ( ! self::valid_id( $id ) ) { $id = wp_generate_uuid4(); }
		echo '<input type="hidden" name="yowcl_checkout_id" value="' . esc_attr( $id ) . '" />';
	}

	/** Retire only the current active selection; durable order evidence remains untouched. */
	public static function retire_session() {
		if ( ! WC()->session ) { return; }
		$id = WC()->session->get( 'yowcl_checkout_id' );
		try { $record = self::record( $id ); } catch ( Throwable $e ) { return; }
		if ( ! $record || 'active' !== $record['state'] || ! $record['order_id'] ) { return; }
		WC()->session->set( 'yowcl_checkout_id', null );
		WC()->session->set( 'loyf_funded_selection', null );
		WC()->session->set( 'yoswc_loyalty_applied_points', null );
		WC()->session->set( 'yoswc_loyalty_discount_amount', null );
		foreach ( array( 'order_awaiting_payment', 'store_api_draft_order' ) as $key ) {
			if ( (int) WC()->session->get( $key ) === $record['order_id'] ) { WC()->session->set( $key, null ); }
		}
	}

	public static function retire_order_session( $order_id ) {
		if ( ! WC()->session ) { return; }
		try { $record = self::record( WC()->session->get( 'yowcl_checkout_id' ) ); } catch ( Throwable $e ) { return; }
		if ( $record && $record['order_id'] === (int) $order_id ) { self::retire_session(); }
	}

	public static function retire_paid_session() {
		if ( ! WC()->session ) { return; }
		try { $record = self::record( WC()->session->get( 'yowcl_checkout_id' ) ); } catch ( Throwable $e ) { return; }
		if ( ! $record || 'active' !== $record['state'] || ! $record['order_id'] ) { return; }
		$order = wc_get_order( $record['order_id'] );
		if ( $order && ( $order->is_paid() || $order->has_status( 'on-hold' ) ) ) { self::retire_session(); }
	}

	public static function valid_id( $id ) {
		return is_string( $id ) && 1 === preg_match( '/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/D', $id );
	}

	private static function id( $order ) {
		return (string) $order->get_meta( self::META, true );
	}

	private static function key( $id ) {
		return 'yowcl_order_redemption_' . $id;
	}

	/** Always read durable recovery state, bypassing the options cache. Unknown state fails closed. */
	public static function record( $id, $db = null ) {
		global $wpdb;
		if ( ! self::valid_id( $id ) ) {
			return null;
		}
		$db = $db ?: $wpdb->dbh;
		if ( ! ( $db instanceof mysqli ) ) {
			throw new RuntimeException( 'order_redemption_storage_unavailable' );
		}
		$raw = YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", self::key( $id ) ) );
		if ( null === $raw ) {
			return null;
		}
		$record = json_decode( $raw, true );
		if ( ! is_array( $record ) || ! isset( $record['state'], $record['terms'], $record['order_id'] ) ) {
			throw new RuntimeException( 'order_redemption_record_invalid' );
		}
		return $record;
	}

	private static function locked( $id, $callback ) {
		global $wpdb;
		$db = $wpdb->dbh;
		if ( isset( self::$held[ $id ] ) || ! ( $db instanceof mysqli ) || YOWCL_Points_Lock::has_transaction( $db ) ) {
			throw new RuntimeException( 'order_redemption_busy' );
		}
		$name = 'yowcl_o_' . hash( 'sha224', DB_NAME . ':' . $wpdb->options . ':' . $id );
		if ( '1' !== (string) YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $name ) ) ) {
			throw new RuntimeException( 'order_redemption_busy' );
		}
		self::$held[ $id ] = true;
		try {
			if ( 'InnoDB' !== YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $wpdb->options ) ) ) {
				throw new RuntimeException( 'order_redemption_transactional_storage_required' );
			}
			// The order lock must survive the primitive's independently named user lock.
			$probe = 'yowclp_' . hash( 'sha224', $name );
			try {
				if ( '1' !== (string) YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $probe ) ) ) {
					throw new RuntimeException( 'order_redemption_lock_probe_failed' );
				}
				self::owner( $db, $name );
			} finally {
				YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $probe ) );
			}
			return $callback( $db, $name );
		} finally {
			unset( self::$held[ $id ] );
			try { YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); } catch ( Throwable $ignored ) {}
		}
	}

	private static function owner( $db, $name ) {
		global $wpdb;
		if ( $wpdb->dbh !== $db || (string) YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT IS_USED_LOCK(%s)', $name ) ) !== (string) YOWCL_Points_Lock::scalar( $db, 'SELECT CONNECTION_ID()' ) ) {
			throw new RuntimeException( 'order_redemption_ownership_lost' );
		}
	}

	private static function write( $id, $record, $db, $name ) {
		global $wpdb;
		self::owner( $db, $name );
		YOWCL_Points_Lock::query( $db, $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)", self::key( $id ), wp_json_encode( $record ) ) );
	}

	private static function terms( $order, $id ) {
        $selection = WC()->session ? WC()->session->get( 'loyf_funded_selection' ) : null;
        if ( ! is_array( $selection ) || $selection['id'] !== $id || $selection['owner'] !== self::session_owner() || $selection['currency'] !== $order->get_currency() ) { throw new RuntimeException( 'legacy_selection_reapply_required' ); }
        $discount = wc_format_decimal( $order->get_meta( '_used_points_discount' ), wc_get_price_decimals() );
        if ( (int) $order->get_meta( '_used_points' ) !== $selection['points'] || $discount !== $selection['discount'] ) { throw new RuntimeException( 'order_redemption_terms_conflict' ); }
        return array( 'user' => (int) $order->get_user_id(), 'owner' => self::session_owner(), 'currency' => $order->get_currency(), 'cart' => $selection['points'], 'product' => 0, 'coupon' => '', 'discount' => $discount, 'items' => array(), 'cart_hash' => $order->get_cart_hash(), 'total' => wc_format_decimal( $order->get_total(), wc_get_price_decimals() ) );
    }

	public static function prepare( $order ) {
		if ( ! YOWCL_Free_Core::owns() || (float) $order->get_meta( '_used_points' ) <= 0 ) { return; }
		self::request_owner();
		self::assert_identity( $order );
		$id = self::id( $order );
		if ( ! self::valid_id( $id ) ) {
			$id = isset( $_POST['yowcl_checkout_id'] ) && is_string( $_POST['yowcl_checkout_id'] ) ? sanitize_text_field( wp_unslash( $_POST['yowcl_checkout_id'] ) ) : '';
			if ( ! self::valid_id( $id ) ) {
				$id = WC()->session ? WC()->session->get( 'yowcl_checkout_id' ) : '';
			}
			if ( ! self::valid_id( $id ) ) {
				$id = wp_generate_uuid4();
			}
		}
		$terms = self::terms( $order, $id );
		if ( 0 === $terms['cart'] && 0 === $terms['product'] ) {
			return;
		}
		if ( ! YOWCL_Free_Core::owns() || ! is_user_logged_in() || $terms['user'] !== get_current_user_id() ) {
			throw new Exception( __( 'Please sign in and reapply your Loyalty redemption.', 'loyalty-for-woocommerce' ) );
		}
		self::locked( $id, static function ( $db, $name ) use ( $id, $terms, $order ) {
			$record = self::record( $id, $db );
			if ( $record && ( $record['terms'] !== $terms || in_array( $record['state'], array( 'returning', 'returned' ), true ) || ( $record['order_id'] && $record['order_id'] !== $order->get_id() ) ) ) {
				throw new Exception( __( 'This Loyalty redemption cannot be reused. Please start a new checkout.', 'loyalty-for-woocommerce' ) );
			}
			if ( $record && 'active' === $record['state'] ) { return; }
			if ( ! $record ) {
				self::write( $id, array( 'terms' => $terms, 'order_id' => $order->get_id(), 'state' => 'prepared' ), $db, $name );
			}
			$order->update_meta_data( self::META, $id );
			$order->update_meta_data( '_yowcl_redemption_state', 'prepared' );
			// An existing Woo status, never a payable or fulfillable staging order.
		} );
		if ( WC()->session ) {
			WC()->session->set( 'yowcl_checkout_id', $id );
		}
	}

	/** Original item terms remain authoritative on replay; never use the current cart for debit. */
	public static function commit( $order ) {
		if ( ! $order || ! self::valid_id( self::id( $order ) ) ) {
			return;
		}
		$id = self::id( $order );
		$notify = self::locked( $id, static function ( $db, $name ) use ( $id, $order ) {
			$record = self::record( $id, $db );
			if ( ! $record || ! $order->get_id() || $record['terms']['user'] !== (int) $order->get_user_id() || ( $record['order_id'] && $record['order_id'] !== $order->get_id() ) ) {
				throw new Exception( 'order_redemption_order_conflict' );
			}
			if ( in_array( $record['state'], array( 'returning', 'returned' ), true ) ) {
				self::restore( $id, $record, $order, $db, $name );
				throw new Exception( __( 'The points for this order were returned. Please create a new order.', 'loyalty-for-woocommerce' ) );
			}
			if ( ! empty( $record['payment_guard'] ) ) { throw new Exception( 'order_redemption_payment_recovery_required' ); }
			self::request_owner();
			self::assert_identity( $order );
			if ( 'active' === $record['state'] ) {
				self::verify_order( $record['terms'], $order );
				if ( $order->has_status( 'checkout-draft' ) ) { $order->set_status( 'pending' ); $order->save(); }
				return;
			}
			self::request_owner();
			self::assert_identity( $order );
			// Validate stored line/coupon economics without needing mutable session terms.
			self::verify_order( $record['terms'], $order );
			$record['order_id'] = $order->get_id();
			self::write( $id, $record, $db, $name );
			self::checkpoint( 'order_bound' );
			foreach ( array( 'cart' => 'points_used' ) as $component => $action ) {
				$points = $record['terms'][ $component ];
				if ( ! $points ) { continue; }
				self::owner( $db, $name );
				$result = YOWCL_Points_Transaction::apply( $record['terms']['user'], -$points, 0, self::event_key( $id, $component ), YOWCL_Points_Transaction::replay_event( self::event_key( $id, $component ), array( 'action' => $action, 'order_id' => $order->get_id(), 'description' => __( 'Get a discount by using points', 'loyalty-for-woocommerce' ) ) ) );
				if ( ! in_array( $result['status'], array( 'applied', 'already_applied' ), true ) ) {
					// Unknown responses retain preparation. No compensation without original proof.
					if ( 'insufficient_balance' === $result['status'] ) {
						self::restore( $id, $record, $order, $db, $name );
					}
					throw new Exception( __( 'Could not commit Loyalty points. Please retry this checkout.', 'loyalty-for-woocommerce' ) );
				}
				self::checkpoint( $component . '_debited' );
			}
			self::owner( $db, $name );
			$order->update_meta_data( '_used_points', $record['terms']['cart'] );
			$order->update_meta_data( '_yowcl_loyalty_coupon_code', $record['terms']['coupon'] );
			$order->update_meta_data( '_yowcl_redemption_state', 'active' );
			$order->update_meta_data( '_loyalty_points_processed', 'yes' );
			$order->set_status( 'pending' );
			self::checkpoint( 'before_finalize' );
			if ( ! $order->save() ) {
				throw new Exception( 'order_redemption_save_failed' );
			}
			self::owner( $db, $name );
			self::verify_order( $record['terms'], self::fresh_order( $order->get_id() ) );
			self::checkpoint( 'order_finalized' );
			$record['state'] = 'active';
			$record['notified'] = true;
			self::write( $id, $record, $db, $name );
			self::checkpoint( 'active_committed' );
			return 0;
		} );

        self::retire_order_session( $order->get_id() );
	}

	private static function request_owner() {}
	private static function assert_identity( $order ) {
		if ( ! $order || ! $order->get_id() ) { return; }
		$fresh = self::fresh_order( $order->get_id() );
		$id = $fresh ? self::id( $fresh ) : '';
		$record = self::record( $id );
		if ( $record && $record['order_id'] === $order->get_id() && $id !== self::id( $order ) ) { throw new RuntimeException( 'order_redemption_order_identity_conflict' ); }
	}

	private static function verify_order( $terms, $order ) {
		if ( ! $order || (int) $order->get_user_id() !== $terms['user'] || $order->get_currency() !== $terms['currency'] || $order->get_cart_hash() !== $terms['cart_hash'] || wc_format_decimal( $order->get_total(), wc_get_price_decimals() ) !== $terms['total'] ) {
			throw new Exception( 'order_redemption_terms_conflict' );
		}
        $fees = 0;
        foreach ( $order->get_items( 'fee' ) as $fee ) { if ( (float) $fee->get_total() < 0 ) { $fees += -(float) $fee->get_total(); } }
        if ( wc_format_decimal( $fees, wc_get_price_decimals() ) !== $terms['discount'] ) { throw new Exception( 'order_redemption_discount_conflict' ); }

	}

	private static function fresh_order( $order_id ) {
		clean_post_cache( $order_id );
		wp_cache_delete( $order_id, 'post_meta' );
		if ( class_exists( 'Automattic\\WooCommerce\\Caches\\OrderCache' ) ) {
			wc_get_container()->get( 'Automattic\\WooCommerce\\Caches\\OrderCache' )->remove( $order_id );
		}
		$store = WC_Data_Store::load( 'order' );
		if ( method_exists( $store->get_current_class_name(), 'clear_cached_data' ) ) { $store->clear_cached_data( array( $order_id ) ); }
		return new WC_Order( $order_id );
	}

	public static function event_key( $id, $component ) {
		if ( 'cart' !== $component ) { throw new DomainException( 'unsupported_component' ); }
		return 'checkout_redeem:' . $id;
	}

	/** Persist cancellation intent before credit. Every retry consults the original transaction row. */
	private static function restore( $id, $record, $order, $db, $name ) {
		global $wpdb;
		if ( ! empty( $record['payment_guard'] ) ) { throw new Exception( 'order_redemption_payment_recovery_required' ); }
		$record['state'] = 'returning';
		self::write( $id, $record, $db, $name );
		$returned = array( 'cart' => 0 );
		foreach ( array( 'cart' => 'points_used' ) as $component => $action ) {
			$key = self::event_key( $id, $component );
			$result = YOWCL_Points_Lock::query( $db, $wpdb->prepare( 'SELECT user_id, order_id, action, available_delta, earning_delta FROM ' . YOWCL_Points_Log::table_name() . ' WHERE event_key = %s', $key ) );
			$debit = mysqli_fetch_assoc( $result );
			mysqli_free_result( $result );
			if ( ! $debit ) { continue; }
			$points = $record['terms'][ $component ];
			if ( (int) $debit['user_id'] !== $record['terms']['user'] || (int) $debit['order_id'] !== $record['order_id'] || $debit['action'] !== $action || (int) $debit['available_delta'] !== -$points || $points <= 0 || (int) $debit['earning_delta'] !== 0 ) {
				throw new Exception( 'order_redemption_original_debit_conflict' );
			}
			self::owner( $db, $name );
			$credit = YOWCL_Points_Transaction::apply( $record['terms']['user'], $points, 0, $key . ':return', YOWCL_Points_Transaction::replay_event( $key . ':return', array( 'source_event_key' => $key, 'order_id' => $record['order_id'], 'description' => __( 'Order is incomplete; redeemed points returned', 'loyalty-for-woocommerce' ) ) ) );
			if ( ! in_array( $credit['status'], array( 'applied', 'already_applied' ), true ) ) {
				throw new Exception( 'order_redemption_return_retry_required' );
			}
			$returned[ $component ] = $points;
			self::checkpoint( $component . '_returned' );
		}
		self::owner( $db, $name );
		$order->update_meta_data( '_yowcl_points_returned', $returned['cart'] );
		$order->update_meta_data( '_yowcl_redemption_state', 'returned' );
		$order->delete_meta_data( '_used_points' );
		$order->delete_meta_data( '_loyalty_points_processed' );
		$order->set_status( $order->has_status( array( 'failed', 'cancelled', 'refunded' ) ) ? $order->get_status() : 'failed' );
		self::checkpoint( 'before_return_marker' );
		$order->save();
		self::owner( $db, $name );
		$notify = empty( $record['return_notified'] );
		$record['state'] = 'returned';
		$record['return_notified'] = true;
		self::write( $id, $record, $db, $name );

	}

	public static function return_points( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) { return; }
		if ( ! self::valid_id( self::id( $order ) ) ) {
			if ( (int) $order->get_meta( '_used_points' ) > 0 && ! $order->get_meta( '_yowcl_legacy_return_review' ) ) {
				$order->update_meta_data( '_yowcl_legacy_return_review', 'yes' );
				$order->save();
				$order->add_order_note( __( 'Historical Loyalty redemption has no atomic debit proof. Manual points-return review required.', 'loyalty-for-woocommerce' ) );
			}
			return;
		}
		if ( isset( self::$held[ self::id( $order ) ] ) ) { return; }
		$id = self::id( $order );
		try {
			self::locked( $id, static function ( $db, $name ) use ( $id, $order ) {
				$order = self::fresh_order( $order->get_id() );
				$record = self::record( $id, $db );
				// A freshly saved staging order has never been authorized.
				if ( ! $record || ! $record['order_id'] || $record['order_id'] !== $order->get_id() ) { return; }
				if ( ! $order->has_status( array( 'failed', 'cancelled', 'refunded' ) ) ) { return; }
				if ( 'prepared' === $record['state'] ) {
					global $wpdb;
					$table = YOWCL_Points_Log::table_name();
					$exists = YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( "SELECT id FROM {$table} WHERE event_key = %s LIMIT 1", self::event_key( $id, 'cart' ) ) );
					if ( ! $exists ) { return; }
				}
				self::restore( $id, $record, $order, $db, $name );
			} );
		} catch ( Throwable $e ) {
			if ( 'order_redemption_payment_recovery_required' === $e->getMessage() ) {
				$order->update_meta_data( '_yowcl_payment_recovery_review', 'yes' );
				$order->save_meta_data();
				$order->add_order_note( __( 'Loyalty payment outcome is uncertain. Points remain charged; merchant must reconcile the payment and order before returning points.', 'loyalty-for-woocommerce' ) );
				return;
			}
			// Durable intent/event remains retryable; do not fabricate a success marker.
			$order->add_order_note( __( 'Loyalty points return requires recovery. Retry the order status action after restoring storage.', 'loyalty-for-woocommerce' ) );
		}
	}

	public static function checkout_exception( $order ) {
		if ( ! self::valid_id( self::id( $order ) ) || ! $order->get_id() ) { return; }
		$id = self::id( $order );
		try {
			self::locked( $id, static function ( $db, $name ) use ( $id, $order ) {
				$record = self::record( $id, $db );
				if ( $record && $record['order_id'] === $order->get_id() ) {
					$fresh = self::fresh_order( $order->get_id() );
					// Payment may have already finalized on another core request. Preserve its funded outcome.
					if ( 'active' === $record['state'] && ( $fresh->is_paid() || $fresh->has_status( 'on-hold' ) ) ) { return; }
					self::restore( $id, $record, $fresh, $db, $name );
				}
			} );
		} catch ( Throwable $e ) {
			// Lost connection preserves preparation for same-ID recovery, not a new checkout debit.
		}
	}

	public static function recover_checkout( $value ) {
		if ( $value ) { return $value; }
		if ( WC()->session ) {
			$awaiting = wc_get_order( WC()->session->get( 'order_awaiting_payment' ) );
			if ( $awaiting && self::terminal( $awaiting ) ) { WC()->session->set( 'order_awaiting_payment', null ); }
		}
		$id = isset( $_POST['yowcl_checkout_id'] ) && is_string( $_POST['yowcl_checkout_id'] ) ? sanitize_text_field( wp_unslash( $_POST['yowcl_checkout_id'] ) ) : '';
		$record = self::record( $id );
		if ( ! $record ) { return $value; }
		if ( $record['terms']['user'] !== get_current_user_id() || $record['terms']['owner'] !== self::session_owner() ) { throw new Exception( 'order_redemption_owner_conflict' ); }
		if ( in_array( $record['state'], array( 'returning', 'returned' ), true ) ) { throw new Exception( __( 'Your points were returned. Reapply redemption and start a new checkout.', 'loyalty-for-woocommerce' ) ); }
		if ( $record['order_id'] ) {
			$order = wc_get_order( $record['order_id'] );
			if ( $order && ( $order->is_paid() || $order->has_status( 'on-hold' ) ) ) { throw new Exception( 'order_redemption_order_already_placed' ); }
			if ( ! $order || $order->get_cart_hash() !== WC()->cart->get_cart_hash() ) { throw new Exception( 'order_redemption_retry_terms_conflict' ); }
			self::commit( $order );
			return $order->get_id();
		}
		return $value;
	}

	private static function terminal( $order ) {
		try {
			$record = self::record( self::id( $order ) );
			return $record && in_array( $record['state'], array( 'returning', 'returned' ), true );
		} catch ( Throwable $e ) { return true; }
	}

	public static function reject_resume( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && self::terminal( $order ) ) {
			WC()->session->set( 'order_awaiting_payment', null );
			throw new Exception( __( 'The points for this order were returned. Please start a new checkout.', 'loyalty-for-woocommerce' ) );
		}
	}

	public static function needs_payment( $needs, $order ) {
		if ( ! self::valid_id( self::id( $order ) ) ) { return $needs; }
		try {
			$record = self::record( self::id( $order ) );
			return $needs && $record && empty( $record['payment_guard'] ) && 'active' === $record['state'] && $record['order_id'] === $order->get_id();
		} catch ( Throwable $e ) { return false; }
	}

	public static function before_pay( $order ) {
		if ( self::valid_id( self::id( $order ) ) && ! self::needs_payment( true, $order ) ) {
			throw new Exception( __( 'This Loyalty order cannot be paid. Please start a new checkout.', 'loyalty-for-woocommerce' ) );
		}
	}

	/** Fence the core payment_complete save against concurrent return. The connection owns the lock. */
	public static function payment_lock( $order_id, $reference = '', $callback = true ) {
		global $wpdb;
		$order = wc_get_order( $order_id );
		if ( ! $order || ! self::valid_id( self::id( $order ) ) ) { return; }
		$id = self::id( $order );
		$db = $wpdb->dbh;
		$name = 'yowcl_o_' . hash( 'sha224', DB_NAME . ':' . $wpdb->options . ':' . $id );
		if ( isset( self::$held[ $id ] ) || ! ( $db instanceof mysqli ) || YOWCL_Points_Lock::has_transaction( $db ) || '1' !== (string) YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $name ) ) ) {
			throw new Exception( 'order_redemption_payment_busy' );
		}
		self::$held[ $id ] = true;
		self::$payments[ $order_id ] = array( $db, $name, $id, wp_generate_uuid4(), $callback, $order->is_paid() ? $order->get_status() : null );
		register_shutdown_function( array( __CLASS__, 'payment_unlock' ), $order_id );
		self::owner( $db, $name );
		$record = self::record( $id, $db );
		if ( $record && 'active' === $record['state'] && $record['order_id'] === $order_id ) {
			if ( ! empty( $record['payment_guard'] ) ) { throw new Exception( 'order_redemption_payment_recovery_required' ); }
			// Survives disconnect/process death: no return may race an unfenced core save.
			$record['payment_guard'] = self::$payments[ $order_id ][3];
			$record['payment_reference'] = (string) $reference;
			self::write( $id, $record, $db, $name );
		} else {
			if ( ! $callback ) { throw new Exception( 'order_redemption_terminal_save_blocked' ); }
			// pre_payment_complete covers refunded too; the status filter is not always called.
			$order->update_meta_data( '_yowcl_late_payment_review', 'yes' );
			$evidence = (string) $reference ?: (string) $order->get_meta( '_yowcl_late_payment_reference' );
			$evidence = $evidence ?: (string) $order->get_transaction_id();
			$order->update_meta_data( '_yowcl_late_payment_reference', $evidence );
			$order->save_meta_data();
			/* translators: %s: Gateway transaction reference, when supplied. */
			$order->add_order_note( sprintf( __( 'Late payment after Loyalty redemption return or incomplete recovery; manual review required. Merchant must resolve the payment. Reference: %s', 'loyalty-for-woocommerce' ), $evidence ) );
			// A terminal status alone does not stop Woo's completion/reward observers.
			self::payment_unlock( $order_id );
			throw new RuntimeException( 'order_redemption_terminal_payment_blocked' );
		}

	}

	public static function payment_unlock( $order_id ) {
		global $wpdb;
		if ( ! isset( self::$payments[ $order_id ] ) ) { return; }
		list( $db, $name, $id ) = self::$payments[ $order_id ];
		unset( self::$payments[ $order_id ], self::$held[ $id ] );
		try { YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); } catch ( Throwable $ignored ) {}
	}

	/** Core offline gateways change status directly, without payment_complete(). */
	public static function gateway_status( $status, $order ) {
		if ( ! self::valid_id( self::id( $order ) ) ) { return $status; }
		self::payment_lock( $order->get_id(), $order->get_transaction_id(), false );
		return $status;
	}

	public static function gateway_finished( $order_id, $from, $to, $order ) {
		if ( ! isset( self::$payments[ $order_id ] ) || self::$payments[ $order_id ][4] || ! in_array( $to, array( 'on-hold', 'processing', 'completed' ), true ) ) { return; }
		list( $db, $name ) = self::$payments[ $order_id ];
		try {
			self::owner( $db, $name );
			if ( self::fresh_order( $order_id )->get_status() !== $to ) { return; }
		} catch ( Throwable $ignored ) { return; }
		self::payment_finished( $order_id );
	}

	public static function payment_before_save( $order ) {
		$id = self::id( $order );
		try {
			self::request_owner();
			self::assert_identity( $order );
			if ( ! self::valid_id( $id ) ) { return; }
			if ( isset( self::$payments[ $order->get_id() ] ) ) {
				$changes = $order->get_changes();
				// HPOS saves coupon bookkeeping from transition hooks. Rejecting these safe,
				// metadata-only saves would recursively emit failed transitions after reconnect.
				if ( ! isset( $changes['status'] ) || ! in_array( $changes['status'], array( 'on-hold', 'processing', 'completed' ), true ) ) { return; }
				self::$payments[ $order->get_id() ][5] = null;
				list( $db, $name ) = self::$payments[ $order->get_id() ];
				self::owner( $db, $name );
				if ( isset( $changes['status'] ) && in_array( $changes['status'], array( 'on-hold', 'processing', 'completed' ), true ) ) {
					$record = self::record( $id, $db );
					if ( ! $record || 'active' !== $record['state'] || $record['order_id'] !== $order->get_id() || empty( $record['payment_guard'] ) || $record['payment_guard'] !== self::$payments[ $order->get_id() ][3] ) { throw new Exception( 'order_redemption_terminal_save_blocked' ); }
				}
				return;
			}
			// Commit/return already own the same order lock and verify their own economic boundary.
			if ( isset( self::$held[ $id ] ) ) { return; }
			$changes = $order->get_changes();
			if ( ! isset( $changes['status'] ) || ! in_array( $changes['status'], array( 'on-hold', 'processing', 'completed' ), true ) ) { return; }
			self::payment_lock( $order->get_id(), $order->get_transaction_id(), false );
		} catch ( Throwable $e ) {
			// WC swallows save exceptions and still emits the in-memory status transition.
			// Replace that transition too, so no fulfillable status hook runs on a rejected save.
			try { $fresh = self::fresh_order( $order->get_id() ); } catch ( Throwable $ignored ) { $fresh = null; }
			$order->set_status( $fresh && $fresh->has_status( array( 'failed', 'cancelled', 'refunded' ) ) ? $fresh->get_status() : 'failed' );
			throw new RuntimeException( $e->getMessage() );
		}
	}

	/** The after-save hook is absent when Woo swallows a save exception. */
	public static function payment_saved( $order ) {
		$order_id = $order->get_id();
		if ( ! isset( self::$payments[ $order_id ] ) || ! $order->has_status( array( 'on-hold', 'processing', 'completed' ) ) ) { return; }
		list( $db, $name ) = self::$payments[ $order_id ];
		try {
			self::owner( $db, $name );
			if ( self::fresh_order( $order_id )->get_status() === $order->get_status() ) { self::$payments[ $order_id ][5] = $order->get_status(); }
		} catch ( Throwable $ignored ) {}
	}

	/** Stop all fulfillment observers if core emits an unsaved in-memory transition. */
	public static function payment_transition( $order_id, $order ) {
		if ( ! isset( self::$payments[ $order_id ] ) ) { return; }
		list( $db, $name ) = self::$payments[ $order_id ];
		self::owner( $db, $name );
		if ( self::$payments[ $order_id ][5] !== $order->get_status() || self::fresh_order( $order_id )->get_status() !== $order->get_status() ) { throw new RuntimeException( 'order_redemption_payment_save_unconfirmed' ); }
	}

	/** Woo also emits payment_complete after a swallowed save failure. */
	public static function payment_confirmed( $order_id ) {
		if ( ! isset( self::$payments[ $order_id ] ) ) { return; }
		list( $db, $name, $id, $token ) = self::$payments[ $order_id ];
		try {
			self::owner( $db, $name );
			$record = self::record( $id, $db );
			if ( $record && ! empty( $record['payment_guard'] ) && $token === $record['payment_guard'] && ( ! self::$payments[ $order_id ][5] || self::fresh_order( $order_id )->get_status() !== self::$payments[ $order_id ][5] ) ) { throw new RuntimeException( 'order_redemption_payment_save_unconfirmed' ); }
		} catch ( Throwable $e ) {
			// Persist uncertainty and release native ownership before stopping downstream observers.
			self::payment_finished( $order_id );
			throw new RuntimeException( $e->getMessage() );
		}
	}

	/** Only a successful core completion on the original connection clears the durable fence. */
	public static function payment_finished( $order_id ) {
		if ( ! isset( self::$payments[ $order_id ] ) ) { return; }
		list( $db, $name, $id, $token ) = self::$payments[ $order_id ];
		try {
			self::owner( $db, $name );
			$record = self::record( $id, $db );
			if ( $record && isset( $record['payment_guard'] ) && $token === $record['payment_guard'] ) {
				if ( ! self::$payments[ $order_id ][5] || self::fresh_order( $order_id )->get_status() !== self::$payments[ $order_id ][5] ) { throw new RuntimeException( 'order_redemption_payment_save_unconfirmed' ); }
				unset( $record['payment_guard'], $record['payment_reference'] );
				self::write( $id, $record, $db, $name );
			}
		} catch ( Throwable $e ) {
			// Unknown save outcome retains the durable guard for merchant reconciliation.
			$order = self::fresh_order( $order_id );
			$order->update_meta_data( '_yowcl_payment_recovery_review', 'yes' );
			$order->save_meta_data();
			throw new RuntimeException( $e->getMessage() );
		} finally { self::payment_unlock( $order_id ); }
		self::return_points( $order_id );
	}

	public static function payment_status( $status, $order_id, $order ) {
		if ( ! self::valid_id( self::id( $order ) ) ) { return $status; }
		$db = null;
		if ( isset( self::$payments[ $order_id ] ) ) {
			list( $db, $name ) = self::$payments[ $order_id ];
			self::owner( $db, $name );
		}
		try {
			$record = self::record( self::id( $order ), $db );
			if ( $record && 'active' === $record['state'] && $record['order_id'] === $order->get_id() ) { return $status; }
		} catch ( Throwable $e ) {}
		return $order->has_status( array( 'failed', 'cancelled', 'refunded' ) ) ? $order->get_status() : 'failed';
	}

	private static function checkpoint( $step ) {
		if ( defined( 'LOY_RUNTIME_DISPOSABLE' ) && true === LOY_RUNTIME_DISPOSABLE ) { do_action( 'yowcl_order_redemption_test_checkpoint', $step ); }
	}
}
YOWCL_Order_Redemption::register();

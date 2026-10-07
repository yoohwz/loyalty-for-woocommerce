<?php

defined('ABSPATH') || exit;

/**
 * Award extra points on a customer's FIRST purchase.
 */
class YOWCL_Extra_Points_First_Purchase {

	const USER_FLAG_META  = 'first_purchase_rewarded';      // bool/int timestamp (awarded already)
	const ORDER_FLAG_META = '_yowcl_first_purchase_awarded';// to avoid double-trigger on one order

	private $epoch;
	private $intents = array();
	private $terminal = null;

	public function __construct( $register = true ) {
		if ( ! $register ) { return; }
		if ( ! YOWCL_Free_Core::owns() ) {
			return;
		}

		/**
		 * Hook when payment completes (covers most gateways).
		 * We also hook on status transitions to be extra safe.
		 */
		add_action( 'woocommerce_payment_complete', [ $this, 'maybe_award_on_order' ], 10, 1 );
		add_action( 'woocommerce_order_status_processing', [ $this, 'maybe_award_on_order' ], 10, 1 );
		add_action( 'woocommerce_order_status_completed',  [ $this, 'maybe_award_on_order' ], 10, 1 );
	}

	/**
	 * Entry point from WC order hooks.
	 * @param int|\WC_Order $order Order ID or WC_Order
	 */
	public function maybe_award_on_order( $order ) {
		return YOWCL_Advanced_Rewards::order( $order, 'first_purchase', self::ORDER_FLAG_META, function ( $fresh ) {
			$this->intents = array(); $this->terminal = null;
			$this->discover( $fresh );
			return array( 'events' => $this->intents, 'terminal' => $this->terminal );
		} );
	}

	private function discover( $order ) {
		if ( ! YOWCL_Free_Core::owns() ) {
			return;
		}

		$order = is_numeric( $order ) ? wc_get_order( $order ) : $order;
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

        if ( YOWCL_Free_First_Purchase::skipped( $order ) ) { return; }

		// Skip if already handled for this order (idempotency)
		if ( 'yes' === $order->get_meta( self::ORDER_FLAG_META ) ) {
			return;
		}

		// Only logged-in customers (guests have no user account to credit)
		$user_id = (int) $order->get_user_id();
		if ( $user_id <= 0 ) {
			return;
		}

		/* === Role eligibility check === */
		$allowed_roles = get_option( 'loyalty_levels_roles', array() );
		if ( ! is_array( $allowed_roles ) || empty( $allowed_roles ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		$user_roles = $user ? (array) $user->roles : array();

		// Normalize role slugs from option just in case
		$allowed_roles = array_map( 'sanitize_key', $allowed_roles );

		// If the user has none of the allowed roles, stop here
		if ( empty( array_intersect( $user_roles, $allowed_roles ) ) ) {
			return;
		}

        if ( ! $order->has_status( array( 'processing','completed' ) ) ) { return; }
        $config = YOWCL_Free_First_Purchase::admission( $order );
        if ( ! $config ) { return; }
        $points = $config['points'];
        $this->epoch = $config['epoch'];

		// Has the user already received this bonus?
		$already = get_user_meta( $user_id, self::USER_FLAG_META, true );
		if ( $already ) {
			// Mark order to prevent repeated checks
			$this->terminal = 'yes';
			return;
		}

        if ( YOWCL_Free_First_Purchase::prior( $order ) ) { $this->terminal = 'yes'; return; }

		// Award the bonus
		if ( ! $this->award_points_to_user( $user_id, $points, $order ) ) {
			return;
		}

		// Mark flags
		$this->terminal = 'yes';
	}

	/**
	 * Capture reward terms before any value commit for one user (for first purchase).
	 */
	private function award_points_to_user( $user_id, $points, \WC_Order $order ) {
		/* translators: %s: order number */
		$description = sprintf( __( 'First purchase bonus (Order #%s)', 'loyalty-for-woocommerce' ), $order->get_order_number() );
		$facts = array( 'epoch' => $this->epoch );
		$facts['label'] = __( 'First purchase reward', 'loyalty-for-woocommerce' );
		$facts['message'] = __( 'Thanks for your first purchase. Your loyalty reward has been added.', 'loyalty-for-woocommerce' );
		$this->intents[] = YOWCL_Advanced_Rewards::intent( $user_id, $points, 'reward:first_purchase:' . $user_id, 'first_purchase_reward', $description, $order->get_id(), $facts );
		return true;
	}
	}

new YOWCL_Extra_Points_First_Purchase();

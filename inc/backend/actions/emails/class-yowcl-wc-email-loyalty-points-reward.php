<?php

defined( 'ABSPATH' ) || exit;

class YOWCL_WC_Email_Loyalty_Points_Reward extends YOWCL_WC_Email_Loyalty_Base {

	public function __construct() {
		$this->id             = 'yowcl_loyalty_points_reward';
		$this->title          = __( 'Points earned', 'loyalty-for-woocommerce' );
		$this->description    = __( 'Sent to customers when points are added to their account.', 'loyalty-for-woocommerce' );
		$this->template_html  = 'emails/loyalty-points-reward.php';
		$this->template_plain = 'emails/plain/loyalty-points-reward.php';
		$this->placeholders   = [
			'{earned_points}'  => (string) $this->points,
			'{points_balance}' => (string) $this->points_balance,
			'{order_number}'   => '',
		];

		add_action( 'yowcl_loyalty_points_reward', [ $this, 'trigger' ], 10, 4 );
		add_action( 'woocommerce_loyalty_points_reward', [ $this, 'trigger' ], 10, 4 );

		parent::__construct();
	}

	public function get_default_subject() {
		return __( 'You earned {earned_points} points', 'loyalty-for-woocommerce' );
	}

	public function get_default_heading() {
		return __( 'Points earned', 'loyalty-for-woocommerce' );
	}

	protected function get_order_number() {
		// Woo supplies its non-persistent preview order as the email object.
		if ( ! $this->user_id && ! $this->order && $this->object instanceof WC_Order ) {
			return $this->object->get_order_number();
		}

		return parent::get_order_number();
	}

	public function trigger( $user_id, $earned_points, $new_points, $order_id = null ) {
		$args = func_get_args();
		if ( $this->is_duplicate_event( $args ) ) {
			return;
		}

		$this->setup_locale();

			if ( $this->prepare_customer( $user_id, $order_id ) ) {
				$this->points         = (int) $earned_points;
				$this->points_balance = (int) $new_points;

				$this->placeholders['{earned_points}']  = (string) $this->points;
				$this->placeholders['{points_balance}'] = (string) $this->points_balance;
			$this->placeholders['{order_number}']   = (string) $this->get_order_number();

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}
		}

		$this->restore_locale();
	}
}

<?php

defined( 'ABSPATH' ) || exit;

class YOWCL_WC_Email_Loyalty_Points_Deduct extends YOWCL_WC_Email_Loyalty_Base {

	public function __construct() {
		$this->id             = 'yowcl_loyalty_points_deduct';
		$this->title          = __( 'Points deducted', 'loyalty-for-woocommerce' );
		$this->description    = __( 'Sent to customers when points are deducted from their account.', 'loyalty-for-woocommerce' );
		$this->template_html  = 'emails/loyalty-points-deduct.php';
		$this->template_plain = 'emails/plain/loyalty-points-deduct.php';
		$this->placeholders   = [
			'{deducted_points}' => '',
			'{points_balance}'  => '',
			'{order_number}'    => '',
		];

		add_action( 'yowcl_loyalty_points_deduct', [ $this, 'trigger' ], 10, 4 );
		add_action( 'woocommerce_loyalty_points_deduct', [ $this, 'trigger' ], 10, 4 );

		parent::__construct();
	}

	public function get_default_subject() {
		return __( 'Your points balance changed', 'loyalty-for-woocommerce' );
	}

	public function get_default_heading() {
		return __( 'Points deducted', 'loyalty-for-woocommerce' );
	}

	public function trigger( $user_id, $deducted_points, $new_points, $order_id = null ) {
		$args = func_get_args();
		if ( $this->is_duplicate_event( $args ) ) {
			return;
		}

		$this->setup_locale();

		if ( $this->prepare_customer( $user_id, $order_id ) ) {
			$this->points         = (int) $deducted_points;
			$this->points_balance = (int) $new_points;

			$this->placeholders['{deducted_points}'] = (string) $this->points;
			$this->placeholders['{points_balance}']  = (string) $this->points_balance;
			$this->placeholders['{order_number}']    = (string) $this->get_order_number();

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}
		}

		$this->restore_locale();
	}
}

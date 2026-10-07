<?php

defined( 'ABSPATH' ) || exit;

class YOWCL_WC_Email_Loyalty_Level_Update extends YOWCL_WC_Email_Loyalty_Base {

	public $level_slug         = '';
	public $level_name         = '';
	public $new_earning_points = 0;

	public function __construct() {
		$this->id             = 'yowcl_loyalty_level_update';
		$this->title          = __( 'Level updated', 'loyalty-for-woocommerce' );
		$this->description    = __( 'Sent to customers when their loyalty level changes.', 'loyalty-for-woocommerce' );
		$this->template_html  = 'emails/loyalty-level-update.php';
		$this->template_plain = 'emails/plain/loyalty-level-update.php';
		$this->placeholders   = [
			'{loyalty_level}'        => '',
			'{total_earning_points}' => '',
		];

		add_action( 'yowcl_loyalty_level_update', [ $this, 'trigger' ], 10, 3 );
		add_action( 'woocommerce_loyalty_level_update', [ $this, 'trigger' ], 10, 3 );

		parent::__construct();
		$this->set_preview_context();
	}

	public function get_default_subject() {
		return __( 'Your level is now {loyalty_level}', 'loyalty-for-woocommerce' );
	}

	public function get_default_heading() {
		return __( 'Level updated', 'loyalty-for-woocommerce' );
	}

	public function trigger( $user_id, $new_level, $new_earning_points ) {
		$args = func_get_args();
		if ( $this->is_duplicate_event( $args ) ) {
			return;
		}

		$this->setup_locale();

		if ( $this->prepare_customer( $user_id ) ) {
			$this->level_slug         = sanitize_key( $new_level );
			$this->level_name         = $this->get_level_name( $this->level_slug );
			$this->new_earning_points = (int) $new_earning_points;

			$this->set_level_placeholders();

			if ( $this->is_enabled() && $this->get_recipient() ) {
				$this->send( $this->get_recipient(), $this->get_subject(), $this->get_content(), $this->get_headers(), $this->get_attachments() );
			}
		}

		$this->restore_locale();
	}

	protected function get_level_name( $level_slug ) {
		$roles = get_option( 'wp_user_roles', [] );

		if ( is_array( $roles ) && isset( $roles[ $level_slug ]['name'] ) ) {
			return $roles[ $level_slug ]['name'];
		}

		return ucfirst( str_replace( [ '-', '_' ], ' ', $level_slug ) );
	}

	protected function set_preview_context() {
		$level_slug = $this->get_preview_level_slug();

		if ( '' !== $level_slug ) {
			$this->level_slug = $level_slug;
			$this->level_name = $this->get_level_name( $level_slug );
		}

		$this->new_earning_points = $this->get_preview_earning_points( $this->level_slug );
		$this->set_level_placeholders();
	}

	protected function get_preview_level_slug() {
		$levels = $this->get_array_option( 'loyalty_customization_levels' );

		foreach ( $levels as $level_slug => $level_data ) {
			if ( is_array( $level_data ) && ! empty( $level_data['icon'] ) ) {
				return sanitize_key( $level_slug );
			}
		}

		if ( ! empty( $levels ) ) {
			$level_slugs = array_keys( $levels );
			return sanitize_key( (string) reset( $level_slugs ) );
		}

		$roles = $this->get_array_option( 'loyalty_levels_roles' );
		if ( ! empty( $roles ) ) {
			return sanitize_key( (string) reset( $roles ) );
		}

		return '';
	}

	protected function get_preview_earning_points( $level_slug ) {
		$rules      = $this->get_array_option( 'loyalty_levels_rules' );
		$level_slug = sanitize_key( $level_slug );

		if ( isset( $rules[ $level_slug ]['from'] ) ) {
			return max( 0, (int) $rules[ $level_slug ]['from'] );
		}

		return 320;
	}

	protected function set_level_placeholders() {
		$this->placeholders['{loyalty_level}']        = $this->level_name;
		$this->placeholders['{total_earning_points}'] = (string) $this->new_earning_points;
	}

	protected function get_template_args( $plain_text = false ) {
		if ( 0 === (int) $this->user_id || '' === $this->level_slug ) {
			$this->set_preview_context();
		}

		$customization_args = $this->get_customization_template_args( $this->level_slug, $this->new_earning_points );

		return array_merge(
			parent::get_template_args( $plain_text ),
			$customization_args,
			[
				'level_slug'         => $this->level_slug,
				'level_name'         => $this->level_name,
				'level_icon_url'     => $customization_args['current_level_icon_url'] ?? '',
				'new_earning_points' => (int) $this->new_earning_points,
			]
		);
	}
}

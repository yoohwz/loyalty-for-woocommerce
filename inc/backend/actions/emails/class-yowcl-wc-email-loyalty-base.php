<?php

defined( 'ABSPATH' ) || exit;

abstract class YOWCL_WC_Email_Loyalty_Base extends WC_Email {

	protected $triggered_events  = [];

	public $user_id        = 0;
	public $user           = false;
	public $points         = 0;
	public $points_balance = 0;
	public $point_label    = '';
	public $points_url     = '';
	public $order_id       = 0;
	public $order          = false;

	public function __construct() {
		$this->customer_email = true;
		$this->email_group    = 'loyalty';
		$this->template_base  = trailingslashit( YOSWC_LOYALTY_PLUGIN_DIR ) . 'templates/';
		$this->point_label    = $this->get_point_label();

		parent::__construct();
		add_filter( 'woocommerce_settings_api_sanitized_fields_' . $this->id, array( $this, 'preserve_settings' ) );
	}

    private function migration_feature() {
        return 'yowcl_loyalty_level_update' === $this->id ? 'email_level' : ('yowcl_loyalty_points_deduct' === $this->id ? 'email_deduct' : 'email_reward');
    }
    public function is_enabled() {
        return YOWCL_Free_Migrations::ready($this->migration_feature()) && parent::is_enabled();
    }
    public function preserve_settings($settings) {
        $existing = maybe_unserialize(get_option('woocommerce_' . $this->id . '_settings', array()));
        return array_replace(is_array($existing) ? $existing : array(), $settings);
    }
    public function process_admin_options() {
        if (!current_user_can('manage_woocommerce') || !YOWCL_Free_Migrations::ready($this->migration_feature())) { return false; }
        return parent::process_admin_options();
    }
	public function init_form_fields() {
		parent::init_form_fields();

		if ( isset( $this->form_fields['enabled'] ) ) {
			$this->form_fields['enabled']['default'] = 'no';
		}
	}

	protected function is_duplicate_event( $args ) {
		$key = md5( $this->id . '|' . wp_json_encode( $args ) );

		if ( isset( $this->triggered_events[ $key ] ) ) {
			return true;
		}

		$this->triggered_events[ $key ] = true;
		return false;
	}

	protected function prepare_customer( $user_id, $order_id = null ) {
		$this->user_id = absint( $user_id );
		$this->user    = get_userdata( $this->user_id );

		if ( ! $this->user ) {
			return false;
		}

		$this->recipient      = $this->user->user_email;
		$this->points_balance = (int) get_user_meta( $this->user_id, 'user_points', true );
		$this->points_url     = $this->get_points_page_url();
		$this->order_id       = absint( $order_id );
		$this->order          = $this->order_id ? wc_get_order( $this->order_id ) : false;

		return true;
	}

	protected function get_points_page_url() {
		$loyalty_options = maybe_unserialize( get_option( 'loyalty_customization_my_account', [] ) );
		$points_slug     = is_array( $loyalty_options ) ? ( $loyalty_options['my_account_slug'] ?? '' ) : '';
		$account_url     = wc_get_page_permalink( 'myaccount' );

		return trailingslashit( $account_url ) . ltrim( (string) $points_slug, '/' );
	}

	protected function get_array_option( $option_name, $default = [] ) {
		$value = maybe_unserialize( get_option( $option_name, $default ) );

		return is_array( $value ) ? $value : $default;
	}

	protected function get_hex_color( $color, $fallback ) {
		$color = is_string( $color ) ? trim( $color ) : '';
		$color = function_exists( 'sanitize_hex_color' ) ? sanitize_hex_color( $color ) : $color;
		$color = is_string( $color ) ? $color : '';

		if ( preg_match( '/^#(?:[0-9a-fA-F]{3}){1,2}$/', $color ) ) {
			return strtolower( $color );
		}

		return $fallback;
	}

	protected function mix_hex_color( $foreground, $background, $percentage ) {
		$foreground = $this->normalize_hex_color( $foreground );
		$background = $this->normalize_hex_color( $background );
		$percentage = max( 0, min( 100, (float) $percentage ) ) / 100;

		if ( ! $foreground || ! $background ) {
			return $background ? '#' . $background : '#ffffff';
		}

		$foreground_rgb = str_split( $foreground, 2 );
		$background_rgb = str_split( $background, 2 );
		$mixed          = [];

		foreach ( [ 0, 1, 2 ] as $index ) {
			$foreground_value = hexdec( $foreground_rgb[ $index ] );
			$background_value = hexdec( $background_rgb[ $index ] );
			$mixed[]          = str_pad( dechex( (int) round( ( $foreground_value * $percentage ) + ( $background_value * ( 1 - $percentage ) ) ) ), 2, '0', STR_PAD_LEFT );
		}

		return '#' . implode( '', $mixed );
	}

	protected function normalize_hex_color( $color ) {
		$color = $this->get_hex_color( $color, '' );

		if ( '' === $color ) {
			return '';
		}

		$color = ltrim( $color, '#' );

		if ( 3 === strlen( $color ) ) {
			$color = $color[0] . $color[0] . $color[1] . $color[1] . $color[2] . $color[2];
		}

		return $color;
	}

	protected function get_woocommerce_email_palette_args() {
		$defaults = [
			'base'        => '#720eec',
			'bg'          => '#f7f7f7',
			'body_bg'     => '#ffffff',
			'body_text'   => '#3c3c3c',
			'footer_text' => '#3c3c3c',
		];

		if ( class_exists( '\Automattic\WooCommerce\Internal\Email\EmailColors' ) ) {
			$defaults = array_replace( $defaults, \Automattic\WooCommerce\Internal\Email\EmailColors::get_default_colors() );
		}

		$accent_color     = $this->get_hex_color( get_option( 'woocommerce_email_base_color', $defaults['base'] ), $defaults['base'] );
		$email_background = $this->get_hex_color( get_option( 'woocommerce_email_background_color', $defaults['bg'] ), $defaults['bg'] );
		$content_bg       = $this->get_hex_color( get_option( 'woocommerce_email_body_background_color', $defaults['body_bg'] ), $defaults['body_bg'] );
		$text_color       = $this->get_hex_color( get_option( 'woocommerce_email_text_color', $defaults['body_text'] ), $defaults['body_text'] );
		$secondary_color  = $this->get_hex_color( get_option( 'woocommerce_email_footer_text_color', $defaults['footer_text'] ), $defaults['footer_text'] );
		$panel_background = $email_background !== $content_bg ? $email_background : $this->mix_hex_color( $accent_color, $content_bg, 6 );

		return [
			'email_palette_accent_color'              => $accent_color,
			'email_palette_background_color'          => $email_background,
			'email_palette_body_background_color'     => $content_bg,
			'email_palette_text_color'                => $text_color,
			'email_palette_secondary_text_color'      => $secondary_color,
			'email_palette_panel_background_color'    => $panel_background,
			'email_palette_border_color'              => $this->mix_hex_color( $accent_color, $content_bg, 24 ),
			'email_palette_progress_background_color' => $this->mix_hex_color( $text_color, $content_bg, 12 ),
		];
	}

	protected function get_points_page_label() {
		$options = $this->get_array_option( 'loyalty_customization_my_account' );
		$label   = isset( $options['my_account_label'] ) ? trim( (string) $options['my_account_label'] ) : '';

		return '' !== $label ? $label : __( 'My points', 'loyalty-for-woocommerce' );
	}

	protected function get_user_loyalty_level_slug() {
		if ( ! $this->user ) {
			return '';
		}

		if ( class_exists( 'YOWCL_Helper_Roles' ) ) {
			$highest_role = YOWCL_Helper_Roles::get_highest_loyalty_user_role( $this->user_id );

			if ( '' !== $highest_role ) {
				return $highest_role;
			}
		}

		$loyalty_roles = $this->get_array_option( 'loyalty_levels_roles' );
		foreach ( (array) $this->user->roles as $role ) {
			if ( empty( $loyalty_roles ) || in_array( $role, $loyalty_roles, true ) ) {
				return $role;
			}
		}

		return '';
	}

	protected function get_loyalty_level_name( $level_slug ) {
		$level_slug = sanitize_key( $level_slug );

		if ( '' === $level_slug ) {
			return __( 'Member', 'loyalty-for-woocommerce' );
		}

		$roles = get_option( 'wp_user_roles', [] );

		if ( is_array( $roles ) && isset( $roles[ $level_slug ]['name'] ) ) {
			return $roles[ $level_slug ]['name'];
		}

		return ucfirst( str_replace( [ '-', '_' ], ' ', $level_slug ) );
	}

	protected function get_level_customization( $level_slug ) {
		$levels     = $this->get_array_option( 'loyalty_customization_levels' );
		$level_data = isset( $levels[ $level_slug ] ) && is_array( $levels[ $level_slug ] ) ? $levels[ $level_slug ] : [];

		return [
			'icon'       => ! empty( $level_data['icon'] ) ? esc_url_raw( $level_data['icon'] ) : '',
			'text_color' => $this->get_hex_color( $level_data['text_color'] ?? '', '#1d2327' ),
		];
	}

	protected function get_next_level_context( $earned_points ) {
		$rules         = $this->get_array_option( 'loyalty_levels_rules' );
		$earned_points = max( 0, (int) $earned_points );
		$context       = [
			'next_level_name'       => '',
			'points_for_next_level' => 0,
			'points_to_next_level'  => 0,
			'level_progress'        => 0,
			'is_highest_level'      => false,
		];

		if ( empty( $rules ) ) {
			return $context;
		}

		uasort(
			$rules,
			function( $a, $b ) {
				$a_from = isset( $a['from'] ) ? (float) $a['from'] : 0;
				$b_from = isset( $b['from'] ) ? (float) $b['from'] : 0;

				return $a_from <=> $b_from;
			}
		);

		foreach ( $rules as $level_slug => $rule ) {
			$threshold = isset( $rule['from'] ) ? (int) $rule['from'] : 0;

			if ( $threshold > $earned_points ) {
				$context['next_level_name']       = $this->get_loyalty_level_name( $level_slug );
				$context['points_for_next_level'] = $threshold;
				$context['points_to_next_level']  = max( 0, $threshold - $earned_points );
				$context['level_progress']        = $threshold > 0 ? min( 100, (int) floor( ( $earned_points / $threshold ) * 100 ) ) : 0;

				return $context;
			}
		}

		$context['level_progress']   = 100;
		$context['is_highest_level'] = true;

		return $context;
	}

	protected function get_customization_template_args( $level_slug = '', $earned_points = null ) {
		$membercard    = $this->get_array_option( 'loyalty_customization_membercard' );
		$level_slug    = '' !== $level_slug ? sanitize_key( $level_slug ) : $this->get_user_loyalty_level_slug();
		$level         = $this->get_level_customization( $level_slug );
		$earned_points = null === $earned_points ? (int) get_user_meta( $this->user_id, 'user_earning_points', true ) : (int) $earned_points;
		$next_level    = $this->get_next_level_context( $earned_points );
		$point_icon    = get_option( 'loyalty_customization_message_icon', '' );

		return array_merge(
			[
				'point_icon_url'              => is_string( $point_icon ) ? esc_url_raw( $point_icon ) : '',
				'points_page_label'           => $this->get_points_page_label(),
				'membercard_text_color'       => $this->get_hex_color( $membercard['membercard_text_color'] ?? '', '#1d2327' ),
				'membercard_background_color' => $this->get_hex_color( $membercard['membercard_background_color'] ?? '', '#f6f7f7' ),
				'membercard_border_color'     => $this->get_hex_color( $membercard['membercard_border_color'] ?? '', '#dcdcde' ),
				'current_level_slug'          => $level_slug,
				'current_level_name'          => $this->get_loyalty_level_name( $level_slug ),
				'current_level_icon_url'      => $level['icon'],
				'current_level_color'         => $level['text_color'],
				'earned_points_total'         => $earned_points,
			],
			$next_level,
			$this->get_woocommerce_email_palette_args()
		);
	}

	protected function get_customer_first_name() {
		if ( ! $this->user ) {
			return '';
		}

		if ( ! empty( $this->user->first_name ) ) {
			return $this->user->first_name;
		}

		return $this->user->display_name;
	}

	protected function get_order_number() {
		return $this->order ? $this->order->get_order_number() : '';
	}

	protected function get_point_label() {
		$point_label = get_option( 'loyalty_point_label', 'Points' );
		$point_label = is_string( $point_label ) ? trim( $point_label ) : '';

		return '' !== $point_label ? $point_label : __( 'Points', 'loyalty-for-woocommerce' );
	}

	protected function get_template_args( $plain_text = false ) {
		$points_url = '' !== $this->points_url ? $this->points_url : $this->get_points_page_url();

		return array_merge(
			[
				'email_heading'       => $this->get_heading(),
				'additional_content'  => $this->get_additional_content(),
				'sent_to_admin'       => false,
				'plain_text'          => $plain_text,
				'email'               => $this,
			'customer'            => $this->user,
			'user'                => $this->user,
			'customer_first_name' => $this->get_customer_first_name(),
				'points'              => (int) $this->points,
				'points_balance'      => (int) $this->points_balance,
				'point_label'         => $this->point_label,
				'points_url'          => $points_url,
				'order'               => $this->order,
				'order_id'            => (int) $this->order_id,
				'order_number'        => $this->get_order_number(),
			],
			$this->get_customization_template_args()
		);
	}

	public function admin_options() {
		ob_start();
		parent::admin_options();
		$output = ob_get_clean();

		$default_back_url = esc_url( admin_url( 'admin.php?page=wc-settings&tab=email' ) );
		$loyalty_back_url = esc_url( admin_url( 'admin.php?page=wc-settings&tab=email&email_group=loyalty' ) );

		echo str_replace( $default_back_url, $loyalty_back_url, $output ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	public function get_content_html() {
		return wc_get_template_html( $this->template_html, $this->get_template_args( false ), '', $this->template_base );
	}

	public function get_content_plain() {
		return wc_get_template_html( $this->template_plain, $this->get_template_args( true ), '', $this->template_base );
	}

	public function get_default_additional_content() {
		return '';
	}
}

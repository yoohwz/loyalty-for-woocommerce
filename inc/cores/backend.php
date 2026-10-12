<?php

if (!defined('ABSPATH')) {
	exit;
}

class YOSWC_Loyalty_Backend {
	private static $version = '1.0.0';

	public function __construct() {
		if ( defined( 'YOSWC_LOYALTY_VERSION' ) ) {
			self::$version = YOSWC_LOYALTY_VERSION;
		}

		add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
		add_action( 'admin_init', [ __CLASS__, 'check_version' ] );
		
		$this->includes();
	}

	public function enqueue_scripts() {
		if (is_admin()) {
			wp_enqueue_style('loyalty-backend-css', plugin_dir_url(__FILE__) . '../../css/backend-style.css', array(), hash_file( 'sha256', plugin_dir_path(__FILE__) . '../../css/backend-style.css' ));
            if ( 'wc-settings' === ( $_GET['page'] ?? '' ) && 'loyalty' === ( $_GET['tab'] ?? '' ) ) {
                wp_enqueue_script( 'jquery' );
                // Read the native selected menu color, including custom admin schemes.
                wp_add_inline_script( 'jquery-core', <<<'JS'
jQuery(function () {
    var workspace = document.querySelector('.loyf-settings');
    var menu = document.querySelector('#adminmenu a.wp-has-current-submenu,#adminmenu li.current a.menu-top');
    if (workspace && menu) {
        workspace.style.setProperty('--loyf-admin-accent', getComputedStyle(menu).backgroundColor);
    }
});
JS
                );
            }
		}
	}

	public static function check_version() : void {
		$installed_version = get_option( 'yoswc_loyalty_version', '' );

		if ( $installed_version !== self::$version ) {
			update_option( 'yoswc_loyalty_version', self::$version );
		}
	}

	public function includes() {
		include_once plugin_dir_path(__FILE__) . '../backend/settings.php';
		include_once plugin_dir_path(__FILE__) . '../backend/users.php';
		include_once plugin_dir_path(__FILE__) . '../backend/yoohw-woo-settings-tabs-reorder.php';
		include_once plugin_dir_path(__FILE__) . '../backend/actions/emails/notifications-email.php';
	}
}

new YOSWC_Loyalty_Backend();

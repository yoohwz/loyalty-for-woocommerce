<?php

if (!defined('ABSPATH')) {
	exit;
}

class YOSWC_Loyalty_Users {

	public function __construct() {		
		$this->includes();
	}

	public function includes() {
		include_once plugin_dir_path(__FILE__) . '/users/user-profile-points.php';
		include_once plugin_dir_path(__FILE__) . '/users/users-points.php';
	}
}

new YOSWC_Loyalty_Users();

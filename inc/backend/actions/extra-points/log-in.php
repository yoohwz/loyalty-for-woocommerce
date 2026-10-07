<?php
defined( 'ABSPATH' ) || exit;
/** Legacy public facade; the canonical core is the only value writer. */
class YOSWC_Loyalty_Extra_Points_Daily_Login {
    public function __construct() { add_action( 'wp_login', array( $this, 'reward_points_on_daily_login' ), 10, 2 ); }
    public function reward_points_on_daily_login( $user_login, $user ) {
        if ( $user instanceof WP_User && $user->exists() ) { YOWCL_Free_Core::user_reward( $user->ID, 'reward:daily_login:' . $user->ID . ':' . current_time( 'Y-m-d' ), 'daily_login_reward', YOWCL_Free_Core::extra( 'login' ) ); }
    }
}
new YOSWC_Loyalty_Extra_Points_Daily_Login();

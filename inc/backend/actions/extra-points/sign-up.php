<?php
defined( 'ABSPATH' ) || exit;
/** Legacy public facade; the canonical core is the only value writer. */
class YOSWC_Loyalty_Extra_Points_Sign_Up {
    public function __construct() { add_action( 'user_register', array( $this, 'reward_points_on_sign_up' ), 10, 1 ); }
    public function reward_points_on_sign_up( $user_id ) {
        YOWCL_Free_Core::user_reward( $user_id, 'reward:signup:' . (int) $user_id, 'sign_up_reward', YOWCL_Free_Core::extra( 'signup' ) );
    }
}
new YOSWC_Loyalty_Extra_Points_Sign_Up();

<?php
defined( 'ABSPATH' ) || exit;
/** Legacy public facade; the canonical core is the only value writer. */
class YOSWC_Loyalty_Extra_Points_Level_Up {
    public function __construct() { add_action( 'set_user_role', array( $this, 'reward_points_on_level_up' ), 10, 3 ); }
    public function reward_points_on_level_up( $user_id, $new_role, $old_roles ) {
        YOWCL_Free_Core::level_bonus( $user_id, $new_role );
    }
}
new YOSWC_Loyalty_Extra_Points_Level_Up();

<?php
defined( 'ABSPATH' ) || exit;
class YOSWC_Loyalty_Return_Used_Point {
    public function __construct() {} // Canonical register() owns native terminal hooks.
    public function return_points_to_user( $order_id ) {
        if ( YOWCL_Free_Core::owns() ) { YOWCL_Order_Redemption::return_points( $order_id ); }
    }
}
new YOSWC_Loyalty_Return_Used_Point();

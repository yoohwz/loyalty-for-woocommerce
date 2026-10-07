<?php
defined( 'ABSPATH' ) || exit;
/** Legacy public facade; the canonical core is the only value writer. */
class YOSWC_Loyalty_Extra_Points_Product_Review {
    public function __construct() { add_action( 'comment_post', array( $this, 'reward_points_on_review' ), 10, 2 ); }
    public function reward_points_on_review( $comment_id, $comment_approved ) {
        $comment = get_comment( $comment_id );
        if ( 1 != $comment_approved || ! $comment || 'review' !== $comment->comment_type || 'product' !== get_post_type( $comment->comment_post_ID ) || ! $comment->user_id ) { return; }
        YOWCL_Free_Core::user_reward( $comment->user_id, 'reward:review:' . (int) $comment_id, 'review_reward', YOWCL_Free_Core::extra( 'review' ) );
    }
}
new YOSWC_Loyalty_Extra_Points_Product_Review();

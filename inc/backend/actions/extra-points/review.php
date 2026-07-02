<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Extra_Points_Product_Review {
    public function __construct() {
        add_action('comment_post', [$this, 'reward_points_on_review'], 10, 2);
    }

    public function reward_points_on_review($comment_id, $comment_approved) {
        if ($comment_approved != 1) {
            return;
        }

        $comment = get_comment($comment_id);
        if (!$comment) {
            return;
        }

        if ($comment->comment_type !== 'review') {
            return;
        }

        $post_type = get_post_type($comment->comment_post_ID);
        if ($post_type !== 'product') {
            return;
        }

        $user_id = $comment->user_id;
        if (!$user_id) {
            return;
        }

        $extra_points_rules = maybe_unserialize(get_option('loyalty_extra_points_rules', []));

        $earned_points = isset($extra_points_rules['review_points']) ? intval($extra_points_rules['review_points']) : 0;

        if ($earned_points <= 0) {
            return;
        }

        $current_points = intval(get_user_meta($user_id, 'user_points', true));
        $earning_points = intval(get_user_meta($user_id, 'user_earning_points', true));
        $new_points = $current_points + $earned_points;
        $new_earning_points = $earning_points + $earned_points;

        update_user_meta($user_id, 'user_points', $new_points);
        update_user_meta($user_id, 'user_earning_points', $new_earning_points);

        $description = __('Product review bonus', 'loyalty-for-woocommerce');

        YOSWC_Loyalty_Database::insert_points_log(
            array(
                'user_id' => $user_id,
                'action' => 'review_reward',
                'amount' => $earned_points,
                'description' => $description,
            )
        );

        $order_id = null;
        do_action('yoswc_loyalty_points_reward', $user_id, $earned_points, $new_points, $order_id);
    }
}

new YOSWC_Loyalty_Extra_Points_Product_Review();

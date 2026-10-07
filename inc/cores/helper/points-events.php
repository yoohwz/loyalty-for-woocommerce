<?php

defined( 'ABSPATH' ) || exit;

class YOWCL_Points_Events {

	public static function reward( $user_id, $points, $new_points, $order_id = null ) {
		do_action( 'yowcl_loyalty_points_reward', $user_id, $points, $new_points, $order_id );
		do_action( 'woocommerce_loyalty_points_reward', $user_id, $points, $new_points, $order_id );
		do_action( 'yoswc_loyalty_points_reward', $user_id, $points, $new_points, $order_id );
	}

	public static function deduct( $user_id, $points, $new_points, $order_id = null ) {
		do_action( 'yowcl_loyalty_points_deduct', $user_id, $points, $new_points, $order_id );
		do_action( 'woocommerce_loyalty_points_deduct', $user_id, $points, $new_points, $order_id );
		do_action( 'yoswc_loyalty_points_deduct', $user_id, $points, $new_points, $order_id );
	}

	public static function level_update( $user_id, $updated_level, $new_earning_points ) {
		do_action( 'yowcl_loyalty_level_update', $user_id, $updated_level, $new_earning_points );
		do_action( 'woocommerce_loyalty_level_update', $user_id, $updated_level, $new_earning_points );
		do_action( 'yoswc_loyalty_level_update', $user_id, $updated_level, $new_earning_points );
	}

}

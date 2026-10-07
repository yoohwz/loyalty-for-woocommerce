<?php

defined( 'ABSPATH' ) || exit;

class YOWCL_Points_Log {

	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'yo_loyalty_points_log';
	}

	public static function insert( $table, array $data, $format = null, array $context = array() ) {
		global $wpdb;

		$result = null === $format
			? $wpdb->insert( $table, $data )
			: $wpdb->insert( $table, $data, $format );

		if ( false === $result || self::table_name() !== $table ) {
			return $result;
		}

		self::fire_created( (int) $wpdb->insert_id, $data, $context );

		return $result;
	}

	public static function fire_created( $log_id, array $data, array $context = array() ) {
		$log_id = absint( $log_id );

		if ( $log_id <= 0 ) {
			return;
		}

		$user_id = absint( $data['user_id'] ?? 0 );
		$payload = array_merge(
			$data,
			array(
				'id'             => $log_id,
				'log_id'         => $log_id,
				'user_id'        => $user_id,
				'action'         => sanitize_key( (string) ( $data['action'] ?? '' ) ),
				'amount'         => isset( $data['amount'] ) ? (float) $data['amount'] : 0,
				'order_id'       => absint( $data['order_id'] ?? 0 ),
				'points_balance' => $user_id ? (int) get_user_meta( $user_id, 'user_points', true ) : 0,
				'earning_points' => $user_id ? (int) get_user_meta( $user_id, 'user_earning_points', true ) : 0,
			)
		);

		do_action( 'yowcl_points_log_created', $log_id, $payload, $context );
		do_action( 'yowcl_loyalty_points_log_created', $log_id, $payload, $context );
		do_action( 'woocommerce_loyalty_points_log_created', $log_id, $payload, $context );
	}
}

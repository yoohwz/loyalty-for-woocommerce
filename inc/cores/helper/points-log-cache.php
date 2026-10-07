<?php

defined( 'ABSPATH' ) || exit;

class YOWCL_Points_Log_Cache {

	public static function get_version( $user_id ) {
		$user_id = absint( $user_id );

		if ( $user_id <= 0 ) {
			return 1;
		}

		$version = wp_cache_get( 'user_points_log_version_' . $user_id, 'user_points' );
		if ( false === $version ) {
			$version = (int) get_user_meta( $user_id, '_yowcl_points_log_cache_version', true );
			$version = $version > 0 ? $version : 1;
			wp_cache_set( 'user_points_log_version_' . $user_id, $version, 'user_points', 3600 );
		}

		return (int) $version;
	}

	public static function invalidate_user( $user_id ) {
		$user_id = absint( $user_id );

		if ( $user_id <= 0 ) {
			return;
		}

		$version = self::get_version( $user_id ) + 1;
		update_user_meta( $user_id, '_yowcl_points_log_cache_version', $version );
		wp_cache_set( 'user_points_log_version_' . $user_id, $version, 'user_points', 3600 );
	}
}

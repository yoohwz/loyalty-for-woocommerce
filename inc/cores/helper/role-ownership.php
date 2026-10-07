<?php
defined( 'ABSPATH' ) || exit;
if ( ! class_exists( 'YOSWC_Role_Ownership' ) ) {
final class YOSWC_Role_Ownership {
	const VERSION = 1;
	const PREFIX = 'yoswc_role_owner_';
	const LOCK = 'yoswc_role_management_lock';
	const BATCH = 100;
	const WINDOWS = 20;
	private static $owners = array(
		'wc-advanced-accounts-premium' => array( 'yoaa_membership_created_roles', 'yoaa_membership_role' ),
		'wc-advanced-accounts' => array( 'yoswc_loyalty_created_roles', 'yoswc_loyalty_role' ),
		'wc-loyalty' => array( 'yowcl_loyalty_created_roles', 'yowcl_loyalty_role' ),
	);

	public static function protected_role( $slug ) {
		return in_array( $slug, array( 'administrator', 'editor', 'author', 'contributor', 'subscriber', 'customer', 'shop_manager', 'translator' ), true );
	}
	private static function fresh_option( $key, $default = false ) {
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		global $wpdb;
		$wpdb->last_error = '';
		$value = get_option( $key, $default );
		if ( $wpdb->last_error ) { throw new RuntimeException( 'Role dependency option read failed.' ); }
		return $value;
	}
	private static function save( $key, $value ) {
		update_option( $key, $value, false );
		return self::fresh_option( $key ) === $value;
	}
	private static function role_data( $slug ) {
		try { $roles = self::fresh_option( wp_roles()->role_key, null ); } catch ( Throwable $error ) { return false; }
		if ( ! is_array( $roles ) ) { return false; }
		if ( ! array_key_exists( $slug, $roles ) ) { return null; }
		return is_array( $roles[ $slug ] ) ? $roles[ $slug ] : false;
	}
	private static function valid( $record, $slug ) {
		return is_array( $record ) && array_keys( $record ) === array( 'version', 'owner', 'slug', 'origin', 'state', 'generation', 'created' )
			&& 1 === $record['version'] && $slug === $record['slug'] && is_string( $record['created'] )
			&& is_string( $record['owner'] ) && is_string( $record['origin'] ) && is_string( $record['state'] )
			&& ( ( isset( self::$owners[ $record['owner'] ] ) && 'plugin-created' === $record['origin']
				&& in_array( $record['state'], array( 'pending', 'active', 'retired', 'deleting' ), true )
				&& is_string( $record['generation'] ) && preg_match( '/^[a-f0-9]{32}$/D', $record['generation'] ) )
				|| ( 'unknown' === $record['owner'] && 'legacy-unknown' === $record['origin'] && 'retired' === $record['state'] && '' === $record['generation'] ) );
	}
	public static function record( $slug ) {
		if ( is_int( $slug ) ) { $slug = (string) $slug; }
		try { $record = self::fresh_option( self::PREFIX . $slug, null ); } catch ( Throwable $error ) { return false; }
		return null === $record ? null : ( self::valid( $record, $slug ) ? $record : false );
	}
	public static function owns( $owner, $slug ) {
		if ( is_int( $slug ) ) { $slug = (string) $slug; }
		$record = self::record( $slug );
		$data = self::role_data( $slug );
		return is_array( $record ) && $owner === $record['owner'] && 'plugin-created' === $record['origin']
			&& in_array( $record['state'], array( 'active', 'retired' ), true ) && is_array( $data )
			&& true === ( $data['capabilities'][ self::PREFIX . $record['generation'] ] ?? null );
	}
	private static function locked( $operation ) {
		global $wpdb;
		if ( ! current_user_can( 'manage_woocommerce' ) ) { return 'permission'; }
		try { $old = self::fresh_option( self::LOCK, null ); } catch ( Throwable $error ) { return 'query'; }
		if ( null !== $old ) {
			if ( ! is_array( $old ) || ( $old['until'] ?? PHP_INT_MAX ) >= time() ) { return 'busy'; }
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND BINARY option_value = %s", self::LOCK, maybe_serialize( $old ) ) );
		}
		$lock = array( 'token' => wp_generate_uuid4(), 'until' => time() + 120 );
		if ( ! add_option( self::LOCK, $lock, '', 'no' ) ) { return 'busy'; }
		try { return $operation(); }
		catch ( Throwable $error ) { return 'storage'; }
		finally {
			$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND BINARY option_value = %s", self::LOCK, maybe_serialize( $lock ) ) );
			wp_cache_delete( self::LOCK, 'options' );
		}
	}
	public static function create( $owner, $slug, $name ) {
		if ( is_int( $slug ) ) { $slug = (string) $slug; }
		return self::locked( static function () use ( $owner, $slug, $name ) {
			if ( ! isset( self::$owners[ $owner ] ) || ! $slug || sanitize_key( $slug ) !== $slug || self::protected_role( $slug ) ) { return 'protected'; }
			if ( null !== self::role_data( $slug ) || null !== self::record( $slug ) ) { return 'exists'; }
			foreach ( self::$owners as $keys ) {
				$reg = self::fresh_option( $keys[0], array() );
				if ( ! self::role_map( $reg ) || array_key_exists( $slug, $reg ) ) { return 'ownership'; }
			}
			$customer = self::role_data( 'customer' );
			if ( ! is_array( $customer ) || ! is_array( $customer['capabilities'] ?? null ) ) { return 'customer'; }
			$caps = $customer['capabilities'];
			foreach ( array_keys( $caps ) as $cap ) {
				if ( 0 === strpos( $cap, self::PREFIX ) || in_array( $cap, array_column( self::$owners, 1 ), true ) ) { unset( $caps[ $cap ] ); }
			}
			$generation = str_replace( '-', '', wp_generate_uuid4() );
			$record = array( 'version' => 1, 'owner' => $owner, 'slug' => $slug, 'origin' => 'plugin-created', 'state' => 'pending', 'generation' => $generation, 'created' => current_time( 'mysql' ) );
			if ( ! self::save( self::PREFIX . $slug, $record ) ) { return 'storage'; }
			$caps[ self::$owners[ $owner ][1] ] = true;
			$caps[ self::PREFIX . $generation ] = true;
			wp_roles()->for_site();
			if ( ! ( add_role( $slug, $name, $caps ) instanceof WP_Role ) || ( self::role_data( $slug )['capabilities'] ?? null ) !== $caps ) { return 'storage'; }
			$reg_key = self::$owners[ $owner ][0];
			$reg = self::fresh_option( $reg_key, array() );
			$reg[ $slug ] = array( 'name' => $name, 'r5_generation' => $generation );
			if ( ! self::save( $reg_key, $reg ) ) { return 'storage'; }
			$record['state'] = 'active';
			return self::save( self::PREFIX . $slug, $record ) && self::owns( $owner, $slug ) ? true : 'storage';
		} );
	}
	public static function retire( $owner, $slug ) {
		if ( is_int( $slug ) ) { $slug = (string) $slug; }
		return self::locked( static function () use ( $owner, $slug ) {
			if ( ! isset( self::$owners[ $owner ] ) || self::protected_role( $slug ) || ! self::role_data( $slug ) ) { return 'protected'; }
			$record = self::record( $slug );
			if ( false === $record || ( is_array( $record ) && ! in_array( $record['owner'], array( $owner, 'unknown' ), true ) ) ) { return 'ownership'; }
			if ( ! self::owns( $owner, $slug ) ) { return 'ownership'; }
			$record['state'] = 'retired';
			return self::save( self::PREFIX . $slug, $record ) ? true : 'storage';
		} );
	}
	private static function identifier_key( $key ) {
		// PHP casts canonical decimal string array keys to integers. Do not rename stored identifiers.
		return ( is_string( $key ) || is_int( $key ) ) && '' !== (string) $key && sanitize_key( (string) $key ) === (string) $key;
	}
	private static function role_map( $value ) {
		if ( ! is_array( $value ) ) { return false; }
		foreach ( $value as $role => $settings ) {
			if ( ! self::identifier_key( $role ) || ! is_array( $settings ) ) { return false; }
			// Registry and role-setting entries are named-field records, never nested role lists.
			foreach ( array_keys( $settings ) as $field ) { if ( ! is_string( $field ) || '' === $field || sanitize_key( $field ) !== $field ) { return false; } }
		}
		return true;
	}
}
}

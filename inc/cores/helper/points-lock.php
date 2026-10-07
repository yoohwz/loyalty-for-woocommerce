<?php

defined( 'ABSPATH' ) || exit;

/** Connection ownership fences an expired lease while its original worker is still alive. */
class YOWCL_Points_Lock {
	const LEASE_SECONDS = 60;
	private static $held = array();

	public static function acquire( $user_id ) {
		global $wpdb;
		if ( isset( self::$held[ $user_id ] ) || ! ( $wpdb->dbh instanceof mysqli ) ) {
			return false;
		}
		$name = 'yowcl_' . hash( 'sha224', DB_NAME . ':' . $wpdb->usermeta . ':' . $user_id );
		$db = $wpdb->dbh;
		if ( self::has_transaction( $db ) ) {
			return false;
		}
		try {
			if ( '1' !== (string) self::scalar( $db, $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $name ) ) ) {
				return false;
			}
		} catch ( Throwable $e ) {
			return false;
		}
		$key = 'yowcl_points_lock_' . $user_id;
		$token = wp_generate_uuid4();
		try {
			$value = $token . ':' . ( (int) self::scalar( $db, 'SELECT UNIX_TIMESTAMP()' ) + self::LEASE_SECONDS );
			// The named lock serializes this check, including stale lease replacement.
			$old = self::scalar( $db, $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) );
			if ( null !== $old ) {
				$expires = (int) substr( $old, strrpos( $old, ':' ) + 1 );
				if ( $expires > (int) self::scalar( $db, 'SELECT UNIX_TIMESTAMP()' ) ) {
					self::scalar( $db, $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
					return false;
				}
				self::query( $db, $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, $old ) );
			}
			self::query( $db, $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'no')", $key, $value ) );
			$lock = array( 'db' => $db, 'name' => $name, 'key' => $key, 'value' => $value, 'user_id' => $user_id );
			self::$held[ $user_id ] = true;
			return $lock;
		} catch ( Throwable $e ) {
			try {
				self::scalar( $db, $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) );
			} catch ( Throwable $ignored ) {
				// A disconnected connection no longer owns a named lock.
			}
			return false;
		}
	}

	/** SAVEPOINT has no lasting effect in autocommit, and never commits an outer transaction. */
	public static function has_transaction( $db ) {
		try {
			if ( '1' !== (string) self::scalar( $db, 'SELECT @@session.autocommit' ) ) {
				return true;
			}
			$name = 'yowcl_probe_' . str_replace( '-', '', wp_generate_uuid4() );
			self::query( $db, 'SAVEPOINT ' . $name );
			try {
				$result = mysqli_query( $db, 'RELEASE SAVEPOINT ' . $name );
				return false !== $result || 1305 !== mysqli_errno( $db );
			} catch ( mysqli_sql_exception $e ) {
				return 1305 !== $e->getCode();
			}
		} catch ( Throwable $e ) {
			return true;
		}
	}

	public static function release( array $lock ) {
		global $wpdb;
		try {
			self::query( $lock['db'], $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $lock['key'], $lock['value'] ) );
		} catch ( Throwable $ignored ) {
			// The lease remains bounded if the original connection has gone away.
		} finally {
			try {
				self::scalar( $lock['db'], $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock['name'] ) );
			} catch ( Throwable $ignored ) {
			}
			unset( self::$held[ $lock['user_id'] ] );
		}
	}

	/** Native queries never auto-reconnect into autocommit halfway through a transaction. */
	public static function query( $db, $sql ) {
		$result = mysqli_query( $db, $sql );
		if ( false === $result ) {
			throw new RuntimeException( 'points_database_failure', mysqli_errno( $db ) );
		}
		return $result;
	}

	public static function scalar( $db, $sql ) {
		$result = self::query( $db, $sql );
		$row = mysqli_fetch_row( $result );
		mysqli_free_result( $result );
		return $row ? $row[0] : null;
	}
}

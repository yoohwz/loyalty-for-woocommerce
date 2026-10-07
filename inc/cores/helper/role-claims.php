<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'YOSWC_Role_Claims' ) ) {
	final class YOSWC_Role_Claims {
		const META_KEY = '_yoswc_role_claims';

		public static function get_claims( $user_id ) {
			$user_id = absint( $user_id );
			if ( ! $user_id ) {
				return array();
			}

			$claims = get_user_meta( $user_id, self::META_KEY, true );
			return self::sanitize_claims( $claims );
		}

		public static function has_role_claim( $user_id, $role_slug, $source ) {
			$role_slug = sanitize_key( $role_slug );
			$source    = sanitize_key( $source );
			$claims    = self::get_claims( $user_id );

			return isset( $claims[ $role_slug ][ $source ] );
		}

		public static function grant_role_claim( $user_id, $role_slug, $source, array $context = array() ) {
			$user_id   = absint( $user_id );
			$role_slug = sanitize_key( $role_slug );
			$source    = sanitize_key( $source );

			if ( ! $user_id || '' === $role_slug || '' === $source || ! get_role( $role_slug ) ) {
				self::diagnostic_log(
					'grant_invalid',
					array(
						'user_id' => $user_id,
						'role'    => $role_slug,
						'source'  => $source,
					),
					'warning'
				);
				return false;
			}

			$user = new WP_User( $user_id );
			if ( ! $user || ! $user->exists() ) {
				self::diagnostic_log(
					'grant_missing_user',
					array(
						'user_id' => $user_id,
						'role'    => $role_slug,
						'source'  => $source,
					),
					'warning'
				);
				return false;
			}

			$claims = self::get_claims( $user_id );
			if ( ! isset( $claims[ $role_slug ] ) || ! is_array( $claims[ $role_slug ] ) ) {
				$claims[ $role_slug ] = array();
			}

			$claims[ $role_slug ][ $source ] = array(
				'updated_at' => current_time( 'mysql' ),
				'context'    => self::sanitize_context( $context ),
			);

			update_user_meta( $user_id, self::META_KEY, $claims );

			if ( ! in_array( $role_slug, (array) $user->roles, true ) ) {
				$user->add_role( $role_slug );
			}

			do_action( 'yoswc_role_claim_granted', $user_id, $role_slug, $source, $claims[ $role_slug ][ $source ] );

			self::diagnostic_log(
				'claim_granted',
				array(
					'user_id' => $user_id,
					'role'    => $role_slug,
					'source'  => $source,
					'context' => $claims[ $role_slug ][ $source ]['context'],
				)
			);

			return true;
		}

		public static function revoke_role_claim( $user_id, $role_slug, $source, $fallback_role = 'customer' ) {
			$user_id       = absint( $user_id );
			$role_slug     = sanitize_key( $role_slug );
			$source        = sanitize_key( $source );
			$fallback_role = sanitize_key( $fallback_role );

			if ( ! $user_id || '' === $role_slug || '' === $source ) {
				return false;
			}

			$claims          = self::get_claims( $user_id );
			$had_source_claim = isset( $claims[ $role_slug ][ $source ] );

			if ( ! $had_source_claim ) {
				self::diagnostic_log(
					'revoke_missing_claim',
					array(
						'user_id' => $user_id,
						'role'    => $role_slug,
						'source'  => $source,
					),
					'warning'
				);
				return false;
			}

			unset( $claims[ $role_slug ][ $source ] );

			if ( empty( $claims[ $role_slug ] ) ) {
				unset( $claims[ $role_slug ] );
			}

			update_user_meta( $user_id, self::META_KEY, $claims );

			$user = new WP_User( $user_id );
			if ( $user && $user->exists() && ! isset( $claims[ $role_slug ] ) && in_array( $role_slug, (array) $user->roles, true ) ) {
				$user->remove_role( $role_slug );
			}

			self::ensure_user_has_role( $user_id, $fallback_role );

			do_action( 'yoswc_role_claim_revoked', $user_id, $role_slug, $source );

			self::diagnostic_log(
				'claim_revoked',
				array(
					'user_id'       => $user_id,
					'role'          => $role_slug,
					'source'        => $source,
					'fallback_role' => $fallback_role,
				)
			);

			return true;
		}

		public static function replace_source_claims( $user_id, $new_role, $source, array $managed_roles, $fallback_role = 'customer' ) {
			$user_id       = absint( $user_id );
			$new_role      = sanitize_key( $new_role );
			$source        = sanitize_key( $source );
			$fallback_role = sanitize_key( $fallback_role );
			$managed_roles = self::sanitize_role_list( $managed_roles );

			if ( ! $user_id || '' === $source || empty( $managed_roles ) ) {
				return false;
			}

			self::backfill_user_claims( $user_id, $source, $managed_roles );

			foreach ( $managed_roles as $role_slug ) {
				if ( $role_slug === $new_role ) {
					continue;
				}

				self::revoke_role_claim( $user_id, $role_slug, $source, $fallback_role );
			}

			if ( '' !== $new_role ) {
				return self::grant_role_claim( $user_id, $new_role, $source );
			}

			self::ensure_user_has_role( $user_id, $fallback_role );

			return true;
		}

		public static function clear_source_claims( $user_id, $source, array $managed_roles, $fallback_role = 'customer' ) {
			$user_id       = absint( $user_id );
			$source        = sanitize_key( $source );
			$fallback_role = sanitize_key( $fallback_role );
			$managed_roles = self::sanitize_role_list( $managed_roles );

			if ( ! $user_id || '' === $source || empty( $managed_roles ) ) {
				return false;
			}

			self::backfill_user_claims( $user_id, $source, $managed_roles );

			foreach ( $managed_roles as $role_slug ) {
				self::revoke_role_claim( $user_id, $role_slug, $source, $fallback_role );
			}

			self::ensure_user_has_role( $user_id, $fallback_role );

			return true;
		}

		public static function backfill_user_claims( $user_id, $source, array $managed_roles ) {
			$user_id       = absint( $user_id );
			$source        = sanitize_key( $source );
			$managed_roles = self::sanitize_role_list( $managed_roles );

			if ( ! $user_id || '' === $source || empty( $managed_roles ) ) {
				return false;
			}

			$user = new WP_User( $user_id );
			if ( ! $user || ! $user->exists() || empty( $user->roles ) ) {
				return false;
			}

			$current_roles = array_values( array_intersect( self::sanitize_role_list( (array) $user->roles ), $managed_roles ) );
			if ( empty( $current_roles ) ) {
				return false;
			}

			$claims  = self::get_claims( $user_id );
			$changed = false;

			foreach ( $current_roles as $role_slug ) {
				if ( isset( $claims[ $role_slug ][ $source ] ) ) {
					continue;
				}

				if ( ! isset( $claims[ $role_slug ] ) || ! is_array( $claims[ $role_slug ] ) ) {
					$claims[ $role_slug ] = array();
				}

				$claims[ $role_slug ][ $source ] = array(
					'updated_at' => current_time( 'mysql' ),
					'context'    => array(
						'backfilled' => true,
					),
				);
				$changed = true;
			}

			if ( $changed ) {
				update_user_meta( $user_id, self::META_KEY, $claims );
				self::diagnostic_log(
					'claims_backfilled',
					array(
						'user_id' => $user_id,
						'source'  => $source,
						'roles'   => $current_roles,
					)
				);
			}

			return $changed;
		}

		public static function ensure_user_has_role( $user_id, $fallback_role = 'customer' ) {
			$user_id       = absint( $user_id );
			$fallback_role = sanitize_key( $fallback_role );

			if ( ! $user_id || '' === $fallback_role || ! get_role( $fallback_role ) ) {
				return false;
			}

			$user = new WP_User( $user_id );
			if ( ! $user || ! $user->exists() ) {
				return false;
			}

			if ( empty( $user->roles ) ) {
				$user->add_role( $fallback_role );
				return true;
			}

			return false;
		}

		public static function sanitize_role_list( array $roles ) {
			$roles = array_map( 'sanitize_key', $roles );
			$roles = array_filter( $roles );

			return array_values( array_unique( $roles ) );
		}

		private static function sanitize_claims( $claims ) {
			if ( ! is_array( $claims ) ) {
				return array();
			}

			$clean = array();
			foreach ( $claims as $role_slug => $sources ) {
				$role_slug = sanitize_key( $role_slug );
				if ( '' === $role_slug || ! is_array( $sources ) ) {
					continue;
				}

				foreach ( $sources as $source => $data ) {
					$source = sanitize_key( $source );
					if ( '' === $source ) {
						continue;
					}

					$clean[ $role_slug ][ $source ] = is_array( $data ) ? $data : array();
				}
			}

			return $clean;
		}

		private static function sanitize_context( array $context ) {
			$clean = array();

			foreach ( $context as $key => $value ) {
				$key = sanitize_key( $key );
				if ( '' === $key ) {
					continue;
				}

				if ( is_scalar( $value ) || null === $value ) {
					$clean[ $key ] = sanitize_text_field( (string) $value );
				}
			}

			return $clean;
		}

		private static function diagnostic_log( $event, array $data = array(), $level = 'info' ) {
			if ( function_exists( 'yoohw_diagnostic_log' ) ) {
				yoohw_diagnostic_log( 'wc_loyalty_role_claims', $event, $data, $level );
			}
		}
	}
}

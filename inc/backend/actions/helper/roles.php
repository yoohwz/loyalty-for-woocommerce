<?php

if (!defined('ABSPATH')) {
	exit;
}

class YOWCL_Helper_Roles {
	const LOYALTY_LEVEL_META = '_yowcl_loyalty_level';
	const CLAIM_SOURCE       = 'loyalty';

	/**
	 * Get the highest loyalty role for a user based on loyalty level rules.
	 *
	 * If the user has multiple roles and more than one exists in the loyalty rules,
	 * this returns the one with the highest "from" value.
	 *
	 * @param int $user_id User ID.
	 * @return string
	 */
	public static function get_highest_loyalty_user_role( $user_id ) {
		$user_id = absint( $user_id );

		if ( ! $user_id ) {
			return '';
		}

		$loyalty_levels_rules = get_option( 'loyalty_levels_rules', array() );
		$loyalty_levels_rules = array_merge( array( 'customer' => array( 'from' => 0 ) ), is_array( $loyalty_levels_rules ) ? $loyalty_levels_rules : array() );

		$stored_level = sanitize_key( (string) get_user_meta( $user_id, self::LOYALTY_LEVEL_META, true ) );
		if ( '' !== $stored_level && isset( $loyalty_levels_rules[ $stored_level ] ) ) {
			return $stored_level;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || empty( $user->roles ) || ! is_array( $user->roles ) ) {
			return '';
		}

		$user_roles = array_map( 'sanitize_key', $user->roles );

		$highest_role = '';
		$highest_from = -1;

		foreach ( $loyalty_levels_rules as $role_slug => $rule ) {
			if ( ! in_array( $role_slug, $user_roles, true ) ) {
				continue;
			}

			$from = isset( $rule['from'] ) ? (float) $rule['from'] : 0;

			if ( $from > $highest_from ) {
				$highest_from = $from;
				$highest_role = $role_slug;
			}
		}

		return $highest_role;
	}

	public static function get_configured_loyalty_roles() {
		$roles = get_option( 'loyalty_levels_roles', array() );
		$roles = is_array( $roles ) ? $roles : array();
		$roles = array_map( 'sanitize_key', $roles );

		if ( ! in_array( 'customer', $roles, true ) ) {
			array_unshift( $roles, 'customer' );
		}

		return array_values( array_unique( array_filter( $roles ) ) );
	}

	public static function backfill_user_loyalty_claims( $user_id ) {
		if ( class_exists( 'YOSWC_Role_Claims' ) ) {
			YOSWC_Role_Claims::backfill_user_claims(
				$user_id,
				self::CLAIM_SOURCE,
				self::get_configured_loyalty_roles()
			);
		}
	}

	public static function set_user_loyalty_role( $user_id, $role_slug ) {
		$user_id   = absint( $user_id );
		$role_slug = sanitize_key( $role_slug );

		if ( ! $user_id || '' === $role_slug || ! get_role( $role_slug ) ) {
			return false;
		}

		$managed_roles = self::get_configured_loyalty_roles();
		if ( ! in_array( $role_slug, $managed_roles, true ) ) {
			$managed_roles[] = $role_slug;
		}

		$old_role = sanitize_key( (string) get_user_meta( $user_id, self::LOYALTY_LEVEL_META, true ) );

		if ( class_exists( 'YOSWC_Role_Claims' ) ) {
			self::backfill_active_membership_claims( $user_id );

			YOSWC_Role_Claims::replace_source_claims(
				$user_id,
				$role_slug,
				self::CLAIM_SOURCE,
				$managed_roles,
				'customer'
			);
		} else {
			$user = new WP_User( $user_id );
			if ( $user && $user->exists() && ! in_array( $role_slug, (array) $user->roles, true ) ) {
				$user->add_role( $role_slug );
			}
		}

		update_user_meta( $user_id, self::LOYALTY_LEVEL_META, $role_slug );

		do_action( 'yowcl_user_loyalty_role_updated', $user_id, $role_slug, $old_role );

		return true;
	}

	private static function backfill_active_membership_claims( $user_id ) {
		if ( ! class_exists( 'YOSWC_Role_Claims' ) ) {
			return;
		}

		$membership_roles = get_option( 'yoaa_wc_membership_roles', array() );
		if ( ! is_array( $membership_roles ) || empty( $membership_roles ) ) {
			return;
		}

		$user = get_userdata( $user_id );
		if ( ! $user || empty( $user->roles ) || ! is_array( $user->roles ) ) {
			return;
		}

		$user_roles = array_map( 'sanitize_key', $user->roles );

		foreach ( $membership_roles as $membership_role ) {
			$membership_role = sanitize_key( $membership_role );
			if ( '' === $membership_role || ! in_array( $membership_role, $user_roles, true ) ) {
				continue;
			}

			$active = get_user_meta( $user_id, '_yoaa_membership_role_' . $membership_role . '_active', true );
			if ( '1' !== (string) $active ) {
				continue;
			}

			$end = get_user_meta( $user_id, '_yoaa_membership_role_' . $membership_role . '_end', true );
			if ( '' !== $end ) {
				$end_timestamp = strtotime( $end );
				if ( $end_timestamp && $end_timestamp < current_time( 'timestamp' ) ) {
					continue;
				}
			}

			YOSWC_Role_Claims::grant_role_claim(
				$user_id,
				$membership_role,
				'membership',
				array(
					'backfilled_from' => 'yoaa_active_meta',
				)
			);
		}
	}

	public static function get_user_loyalty_role_for_rules( $user_id ) {
		$role = self::get_highest_loyalty_user_role( $user_id );
		if ( '' !== $role ) {
			return $role;
		}

		$user = get_userdata( $user_id );
		if ( $user && ! empty( $user->roles ) && is_array( $user->roles ) ) {
			return sanitize_key( $user->roles[0] );
		}

		return 'customer';
	}
}

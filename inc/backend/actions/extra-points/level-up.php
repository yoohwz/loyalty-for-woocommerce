<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Extra_Points_Level_Up {

    public function __construct() {
        // Hook into role changes. The action passes user_id, new role, and old roles.
        add_action('set_user_role', [$this, 'reward_points_on_level_up'], 10, 3);
    }

    /**
     * Award level-up bonus points when a user's role is changed.
     *
     * @param int    $user_id   The ID of the user whose role has changed.
     * @param string $new_role  The new role assigned to the user.
     * @param array  $old_roles The previous roles of the user.
     */
    public function reward_points_on_level_up($user_id, $new_role, $old_roles) {
        // Retrieve the level-up points rules option.
        $levelup_rules = maybe_unserialize(get_option('loyalty_extra_levelup_points_rules', []));
        
        // If the new role is not defined in the rules, exit.
        if (!isset($levelup_rules[$new_role])) {
            return;
        }

        // Retrieve the awarded points for this role.
        $awarded_points = isset($levelup_rules[$new_role]['awarded']) ? intval($levelup_rules[$new_role]['awarded']) : 0;
        if ($awarded_points <= 0) {
            return;
        }

        // Update user's points meta.
        $current_points = intval(get_user_meta($user_id, 'user_points', true));
        $earning_points = intval(get_user_meta($user_id, 'user_earning_points', true));
        $new_points = $current_points + $awarded_points;
        $new_earning_points = $earning_points + $awarded_points;

        update_user_meta($user_id, 'user_points', $new_points);
        update_user_meta($user_id, 'user_earning_points', $new_earning_points);

        global $wp_roles;
		if ( ! isset($wp_roles) || ! is_object($wp_roles) ) {
			$wp_roles = new WP_Roles();
		}
		$role_name = isset($wp_roles->roles[$new_role]['name']) ? $wp_roles->roles[$new_role]['name'] : $new_role;
		$description = __('Level up bonus for role:', 'loyalty-for-woocommerce') . ' ' . $role_name;

        YOSWC_Loyalty_Database::insert_points_log(
            array(
                'user_id'     => $user_id,
                'action'      => 'level_up_reward',
                'amount'      => $awarded_points,
                'description' => $description,
            )
        );

        // Trigger any additional actions.
        $order_id = null;
        do_action('yoswc_loyalty_points_reward', $user_id, $awarded_points, $new_points, $order_id);
    }
}

new YOSWC_Loyalty_Extra_Points_Level_Up();

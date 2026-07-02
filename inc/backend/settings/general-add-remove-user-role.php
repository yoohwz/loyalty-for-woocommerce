<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Settings_Add_Remove_User_Role {
    
    public function __construct() {
        add_action('admin_init', [$this, 'handle_role_actions']);
		add_action('admin_head', [$this, 'hide_save_button']);
		add_action('admin_notices', [$this, 'display_admin_notices']);
    }

    public function handle_role_actions() {
        if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
            if (isset($_POST['add_new_role_submit'], $_POST['new_role_name'], $_POST['new_role_slug'], $_POST['add_new_role_nonce'])) {
                if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['add_new_role_nonce'])), 'add_new_role_action')) {
                    wp_die(esc_html__('Nonce verification failed. Please try again.', 'loyalty-for-woocommerce'));
                }
                $this->add_new_role();
            }
    
            if (isset($_POST['remove_role_submit'], $_POST['role_to_remove'], $_POST['remove_role_nonce'])) {
                if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['remove_role_nonce'])), 'remove_role_action')) {
                    wp_die(esc_html__('Nonce verification failed. Please try again.', 'loyalty-for-woocommerce'));
                }
                $this->remove_role();
            }
        }
    }
    
    private function add_new_role() {
        if ( ! isset( $_POST['add_new_role_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['add_new_role_nonce'] ) ), 'add_new_role_action' ) ) {
            wp_die('Security check failed');
        }
    
        if ( ! isset( $_POST['new_role_name'], $_POST['new_role_slug'] ) ) {
            set_transient('yoswc_loyalty_admin_notice', 'yoswc_error_fields_empty', 30);
            return;
        }
    
        $new_role_name = sanitize_text_field( wp_unslash( $_POST['new_role_name'] ) );
        $new_role_slug = sanitize_text_field( wp_unslash( $_POST['new_role_slug'] ) );
    
        if (empty($new_role_name) || empty($new_role_slug)) {
            set_transient('yoswc_loyalty_admin_notice', 'yoswc_error_fields_empty', 30);
            return;
        }
    
        $customer_role = get_role('customer');
        if ($customer_role) {
            add_role($new_role_slug, $new_role_name, $customer_role->capabilities);
            set_transient('yoswc_loyalty_admin_notice', 'yoswc_success_role_added', 30);
        } else {
            set_transient('yoswc_loyalty_admin_notice', 'yoswc_error_customer_role_not_found', 30);
        }
    }    

    private function remove_role() {
        if ( ! isset( $_POST['remove_role_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['remove_role_nonce'] ) ), 'remove_role_action' ) ) {
            wp_die('Security check failed');
        }
    
        if ( ! isset( $_POST['role_to_remove'] ) ) {
            set_transient('yoswc_loyalty_admin_notice', 'error_role_not_selected', 30);
            return;
        }
    
        $role_to_remove = sanitize_text_field( wp_unslash( $_POST['role_to_remove'] ) );
        $protected_roles = ['administrator', 'editor', 'author', 'contributor', 'subscriber', 'shop_manager', 'translator'];
    
        if (!in_array($role_to_remove, $protected_roles)) {
            remove_role($role_to_remove);
            set_transient('yoswc_loyalty_admin_notice', 'yoswc_success_role_removed', 30);
        } else {
            set_transient('yoswc_loyalty_admin_notice', 'yoswc_error_protected_role', 30);
        }
    }
    
    public function display_add_remove_role() {
        ?>
        <h2><a href="<?php echo esc_url(admin_url('admin.php?page=wc-settings&tab=loyalty')); ?>"><?php esc_html_e('Loyalty levels', 'loyalty-for-woocommerce'); ?></a> &gt; <?php esc_html_e('Add / Remove role', 'loyalty-for-woocommerce'); ?></h2>
        
        <form method="post" action="">
            <h3><?php esc_html_e('Add new', 'loyalty-for-woocommerce'); ?></h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Role name', 'loyalty-for-woocommerce'); ?></th>
                    <td><input type="text" name="new_role_name" style="width: 150px;" placeholder="Silver Member" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Role slug', 'loyalty-for-woocommerce'); ?></th>
                    <td><input type="text" name="new_role_slug" style="width: 150px;" placeholder="silver-member" /></td>
                </tr>
            </table>
            <?php wp_nonce_field('add_new_role_action', 'add_new_role_nonce'); ?>
            <?php submit_button(__('Add new role', 'loyalty-for-woocommerce'), 'primary', 'add_new_role_submit'); ?>
            
            <h3><?php esc_html_e('Remove', 'loyalty-for-woocommerce'); ?></h3>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row"><?php esc_html_e('Remove role', 'loyalty-for-woocommerce'); ?></th>
                    <td>
                        <select name="role_to_remove" style="width: 150px;">
                            <?php 
                            global $wp_roles;
                            $roles = $wp_roles->role_names;
                            $protected_roles = ['administrator', 'editor', 'author', 'contributor', 'subscriber', 'customer', 'shop_manager', 'translator'];
                            foreach ($protected_roles as $protected_role) {
                                unset($roles[$protected_role]);
                            }
                            foreach ($roles as $slug => $name) {
                                echo '<option value="' . esc_attr($slug) . '">' . esc_html($name) . '</option>';
                            }
                            ?>
                        </select>
                    </td>
                </tr>
            </table>
            <?php wp_nonce_field('remove_role_action', 'remove_role_nonce'); ?>
            <?php submit_button(__('Remove role', 'loyalty-for-woocommerce'), 'primary', 'remove_role_submit'); ?>
        </form>
        <?php
    }

    public function hide_save_button() {
        global $pagenow;
        $page = sanitize_key( (string) filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW ) );
        $tab = sanitize_key( (string) filter_input( INPUT_GET, 'tab', FILTER_UNSAFE_RAW ) );
        $section = sanitize_key( (string) filter_input( INPUT_GET, 'section', FILTER_UNSAFE_RAW ) );
        $subsection = sanitize_key( (string) filter_input( INPUT_GET, 'subsection', FILTER_UNSAFE_RAW ) );

        if ( 
            $pagenow === 'admin.php'
            && $page === 'wc-settings'
            && $tab === 'loyalty'
        ) {
            // Now check if we’re on either:
            // 1) section=general & subsection=add_remove_role
            // 2) section=tools
            $is_add_remove_role = (
                $section === 'general'
                && $subsection === 'add_remove_role'
            );
            $is_tools_section = (
                $section === 'tools'
            );
    
            if ( $is_add_remove_role || $is_tools_section ) {
                // Register + enqueue a dummy CSS handle
                wp_register_style('admin-css', false, array(), '1.0');
                wp_enqueue_style('admin-css');
    
                // Hide the Save button
                $inline_css = '.woocommerce .woocommerce-save-button { display: none; }';
                wp_add_inline_style('admin-css', $inline_css);
            }
        }
    }

    public function display_admin_notices() {
        $notice = get_transient('yoswc_loyalty_admin_notice');

        if ($notice === 'yoswc_error_fields_empty') {
            echo '<div class="notice notice-error"><p>Please fill in all fields for adding a role.</p></div>';
        } elseif ($notice === 'yoswc_success_role_added') {
            echo '<div class="notice notice-success"><p>New role added successfully.</p></div>';
        } elseif ($notice === 'yoswc_error_customer_role_not_found') {
            echo '<div class="notice notice-error"><p>Error: The customer role does not exist.</p></div>';
        } elseif ($notice === 'yoswc_success_role_removed') {
            echo '<div class="notice notice-success"><p>Role removed successfully.</p></div>';
        } elseif ($notice === 'yoswc_error_protected_role') {
            echo '<div class="notice notice-error"><p>Failed to remove role. Certain roles cannot be removed.</p></div>';
        }

        delete_transient('yoswc_loyalty_admin_notice');
    }
}

new YOSWC_Loyalty_Settings_Add_Remove_User_Role();

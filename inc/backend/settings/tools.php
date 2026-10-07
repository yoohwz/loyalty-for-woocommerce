<?php

defined('ABSPATH') || exit;

class YOSWC_Loyalty_Settings_Tools {
	public function __construct() {
		add_action('admin_post_redefine_loyalty_level', [$this, 'handle_redefine_loyalty_level']);
		add_action('admin_notices', [$this, 'maybe_show_admin_notices']);
	}

	public function display_tools_settings() {
		if ( null !== filter_input( INPUT_POST, 'import_csv', FILTER_UNSAFE_RAW ) ) {
			$this->import_csv();
		}

        if ( isset( $_POST['start_new_import'] ) ) { $this->start_new_import(); }
        $operation_id = $this->import_identity();

		$sample_url = plugins_url('files/yol_import_sample.csv', __FILE__);
		$redefine_url = wp_nonce_url(
			admin_url('admin-post.php?action=redefine_loyalty_level'),
			'redefine_loyalty_level',
			'redefine_loyalty_level_nonce'
		);
		?>
		<h2><?php esc_html_e('Import', 'loyalty-for-woocommerce'); ?></h2>

		<table class="form-table">
			<tr>
				<th><?php esc_html_e('Import CSV', 'loyalty-for-woocommerce'); ?></th>
				<td>
					<form method="post" enctype="multipart/form-data">
						<span class="yobm-upload-form">
							<input type="file" name="import_file" id="import_file" accept=".csv">
							<?php wp_nonce_field('wc_loyalty_import_action', 'wc_loyalty_import_nonce'); ?>
                            <input type="hidden" name="operation_id" value="<?php echo esc_attr( $operation_id ); ?>">
							<input type="submit" name="import_csv" id="import_csv" class="button-primary" value="<?php esc_attr_e('Import', 'loyalty-for-woocommerce'); ?>" disabled>
						</span>
					</form>
                    <form method="post">
                        <?php wp_nonce_field( 'loyf_start_new_import', 'loyf_start_new_import_nonce' ); ?>
                        <input type="hidden" name="previous_operation_id" value="<?php echo esc_attr( $operation_id ); ?>">
                        <button type="submit" name="start_new_import" class="button"><?php esc_html_e( 'Start another import', 'loyalty-for-woocommerce' ); ?></button>
                    </form>
                    <p class="description"><?php esc_html_e( 'Retry the original file to continue the current import. Start another import only after reviewing the previous results.', 'loyalty-for-woocommerce' ); ?></p>
					<p class="description" style="margin-top: 20px;">
						<?php
						echo wp_kses(
							sprintf(
								/* translators: %1$s: URL to download the CSV sample file. */
								__('Download the <a href="%1$s" target="_blank" rel="noopener">CSV sample file</a> and add the entries to the correct column.', 'loyalty-for-woocommerce'),
								esc_url($sample_url)
							),
							array(
								'a' => array(
									'href' => array(),
									'target' => array(),
									'rel' => array(),
								),
							)
						);
						?>
					</p>
				</td>
			</tr>
		</table>

		<h2><?php esc_html_e('Calculation', 'loyalty-for-woocommerce'); ?></h2>

		<table class="form-table">
			<tr>
				<th><?php esc_html_e('Loyalty level', 'loyalty-for-woocommerce'); ?></th>
				<td>
					<a href="<?php echo esc_url($redefine_url); ?>" id="redefine_loyalty_level_button" class="button button-secondary">
						<?php echo esc_html__('Redefine', 'loyalty-for-woocommerce'); ?>
					</a>
					<span id="loading_indicator" class="loading-indicator" style="display: none;">
						<img src="<?php echo esc_url(admin_url('images/spinner.gif')); ?>" alt="<?php esc_attr_e('Loading...', 'loyalty-for-woocommerce'); ?>">
						<?php echo esc_html__('Redefining... Please wait, DO NOT leave the page until finished.', 'loyalty-for-woocommerce'); ?>
					</span>
					<p class="description"><?php echo esc_html__('Redefine the loyalty levels based on the total earned points.', 'loyalty-for-woocommerce'); ?></p>
				</td>
			</tr>
		</table>

		<script>
			document.getElementById('import_file').addEventListener('change', function() {
				const submitButton = document.getElementById('import_csv');
				submitButton.disabled = !this.files.length;
			});

			document.getElementById('redefine_loyalty_level_button').addEventListener('click', function() {
				const loadingIndicator = document.getElementById('loading_indicator');
				if (loadingIndicator) {
					loadingIndicator.style.display = 'inline-block';
				}

				this.disabled = true;
			});
		</script>
		<?php
	}

    /** Durable UI pointer only; operation/file witnesses and row events are never removed. */
    private function import_identity() {
        global $wpdb;
        $key = 'loyf_import_pending_' . get_current_user_id();
        $read = static function () use ( $wpdb, $key ) { return YOWCL_Points_Lock::scalar( $wpdb->dbh, $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) ); };
        $id = $read();
        if ( null === $id ) { add_option( $key, wp_generate_uuid4(), '', false ); $id = $read(); }
        if ( ! YOWCL_Order_Redemption::valid_id( $id ) ) { wp_die( esc_html__( 'Import recovery identity needs review.', 'loyalty-for-woocommerce' ) ); }
        return $id;
    }

    private function import_locked( $callback ) {
        global $wpdb;
        $db = $wpdb->dbh;
        $name = 'loyf_csv_' . hash( 'sha224', DB_NAME . ':' . $wpdb->options . ':' . get_current_user_id() );
        if ( ! current_user_can( 'manage_options' ) || ! YOWCL_Free_Core::owns() ) { wp_die( esc_html__( 'You do not have permission to import points.', 'loyalty-for-woocommerce' ) ); }
        if ( ! ( $db instanceof mysqli ) || YOWCL_Points_Lock::has_transaction( $db ) || '1' !== (string) YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $name ) ) ) { wp_die( esc_html__( 'An import is running. Retry the current file later.', 'loyalty-for-woocommerce' ) ); }
        $owner = static function () use ( $db, $name ) {
            global $wpdb;
            if ( $wpdb->dbh !== $db || (string) YOWCL_Points_Lock::scalar( $db, 'SELECT CONNECTION_ID()' ) !== (string) YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT IS_USED_LOCK(%s)', $name ) ) ) { throw new RuntimeException( 'import_ownership_lost' ); }
        };
        try { $owner(); return $callback( $owner ); }
        finally { try { YOWCL_Points_Lock::scalar( $db, $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); } catch ( Throwable $ignored ) {} }
    }

    private function start_new_import() {
        if ( ! isset( $_POST['loyf_start_new_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['loyf_start_new_import_nonce'] ) ), 'loyf_start_new_import' ) ) { wp_die( esc_html__( 'Nonce verification failed. Please try again.', 'loyalty-for-woocommerce' ) ); }
        $this->import_locked( function ( $owner ) {
            global $wpdb;
            $previous = $_POST['previous_operation_id'] ?? '';
            if ( ! is_string( $previous ) || $previous !== $this->import_identity() ) { wp_die( esc_html__( 'The current import changed. Reload and review its results.', 'loyalty-for-woocommerce' ) ); }
            $owner();
            YOWCL_Points_Lock::query( $wpdb->dbh, $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND BINARY option_value = %s", wp_generate_uuid4(), 'loyf_import_pending_' . get_current_user_id(), $previous ) );
        } );
    }

    private function import_csv() {
        return $this->import_locked( function ( $owner ) { return $this->import_csv_rows( $owner ); } );
    }

    private function import_csv_rows( $owner ) {
		if (!isset($_POST['wc_loyalty_import_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['wc_loyalty_import_nonce'])), 'wc_loyalty_import_action')) {
			wp_die(esc_html__('Nonce verification failed. Please try again.', 'loyalty-for-woocommerce'));
		}

		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to import points.', 'loyalty-for-woocommerce'));
		}

		$file_tmp_path = isset($_FILES['import_file']['tmp_name']) ? sanitize_text_field(wp_unslash($_FILES['import_file']['tmp_name'])) : '';
		$file_name = isset($_FILES['import_file']['name']) ? sanitize_file_name(wp_unslash($_FILES['import_file']['name'])) : '';

		if ('' === $file_tmp_path || '' === $file_name) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('No file was uploaded.', 'loyalty-for-woocommerce') . '</p></div>';
			return;
		}

		$filetype = wp_check_filetype_and_ext($file_tmp_path, $file_name);
		if (empty($filetype['ext']) || 'csv' !== $filetype['ext']) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Invalid file type. Please upload a CSV file.', 'loyalty-for-woocommerce') . '</p></div>';
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		global $wp_filesystem;
		if (!WP_Filesystem() || !$wp_filesystem) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Could not initialize the WordPress filesystem.', 'loyalty-for-woocommerce') . '</p></div>';
			return;
		}

		$csv_contents = $wp_filesystem->get_contents($file_tmp_path);
		if (false === $csv_contents || '' === trim($csv_contents)) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__('Could not open CSV file.', 'loyalty-for-woocommerce') . '</p></div>';
			return;
		}

		$operation = $_POST['operation_id'] ?? '';
		if ( ! YOWCL_Free_Core::owns() || ! is_string( $operation ) || ! YOWCL_Order_Redemption::valid_id( $operation ) || $operation !== $this->import_identity() || strlen( $csv_contents ) > 1048576 ) { wp_die( esc_html__( 'Invalid import identity or file size.', 'loyalty-for-woocommerce' ) ); }
		$rows = preg_split('/\r\n|\r|\n/', trim($csv_contents));
		array_shift($rows);

        if ( count( $rows ) > 500 ) { wp_die( esc_html__( 'Import at most 500 rows per operation.', 'loyalty-for-woocommerce' ) ); }
        $targets = array();
        foreach ( $rows as $line ) {
            if ( '' === trim( $line ) ) { continue; }
            $row = str_getcsv( $line, ',' );
            if ( 3 !== count( $row ) || ! preg_match( '/^[1-9][0-9]{0,17}$/D', $row[0] ) || ! preg_match( '/^[0-9]{1,8}$/D', $row[1] ) || ! preg_match( '/^[0-9]{1,18}$/D', $row[2] ) || isset( $targets[$row[0]] ) || ! get_userdata( (int) $row[0] ) ) { wp_die( esc_html__( 'Invalid or duplicate CSV target. Whole non-negative balances are required.', 'loyalty-for-woocommerce' ) ); }
            $targets[$row[0]] = true;
        }
        $witness = 'loyf_import_' . get_current_user_id() . '_' . $operation;
        $hash = hash( 'sha256', $csv_contents );
        add_option( $witness, $hash, '', false );
        if ( get_option( $witness ) !== $hash ) { wp_die( esc_html__( 'This import identity belongs to different terms. Retry the original file.', 'loyalty-for-woocommerce' ) ); }
		$rows_imported = 0;
		$rows_failed = 0;

		foreach ($rows as $line) {
			if ('' === trim($line)) {
				continue;
			}

			$row = str_getcsv($line, ',');
			$user_id = isset($row[0]) ? absint($row[0]) : 0;

            $owner();
			if (get_userdata($user_id)) {
                $result = YOWCL_Points_Transaction::mutate( $user_id, (int) $row[1], (int) $row[2], 'import:' . get_current_user_id() . ':' . $operation . ':' . $user_id, array( 'action' => 'points_import', 'description' => __( 'Points imported from CSV.', 'loyalty-for-woocommerce' ) ), 'replace' );
                if ( in_array( $result['status'], array( 'applied', 'already_applied' ), true ) ) { $rows_imported++; }
                else { $rows_failed++; YOWCL_Free_Core::hold( $user_id, $result['code'] ); }

			} else {
				$rows_failed++;
			}
		}

		if ($rows_imported > 0) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html(
				sprintf(
					/* translators: %d: number of rows imported. */
					__('Successfully imported %d row(s).', 'loyalty-for-woocommerce'),
					$rows_imported
				)
			) . '</p></div>';
		}

		if ($rows_failed > 0) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(
				sprintf(
					/* translators: %d: number of rows that failed to import. */
					__('%d row(s) failed to import.', 'loyalty-for-woocommerce'),
					$rows_failed
				)
			) . '</p></div>';
		}
	}

	public function handle_redefine_loyalty_level() {
		check_admin_referer('redefine_loyalty_level', 'redefine_loyalty_level_nonce');

		if (!current_user_can('manage_options')) {
			wp_die(esc_html__('You do not have permission to perform this action.', 'loyalty-for-woocommerce'));
		}

		$redirect_url = admin_url('admin.php?page=wc-settings&tab=loyalty&section=tools');
		$loyalty_levels = get_option('loyalty_levels_rules', []);

		if (empty($loyalty_levels) || !is_array($loyalty_levels)) {
			wp_safe_redirect(add_query_arg('loyalty_redefine_error', 'invalid_rules', $redirect_url));
			exit;
		}

		$allowed_loyalty_roles = array_keys($loyalty_levels);

		uasort($loyalty_levels, function($a, $b) {
			return intval($a['from']) - intval($b['from']);
		});

		$user_query = new WP_User_Query([
			'fields' => 'ID',
			'number' => 0,
		]);
		$users = $user_query->get_results();

		foreach ($users as $user_id) {
			$wp_user_object = new WP_User($user_id);
			$current_roles = (array) $wp_user_object->roles;
			$earning_points = absint(get_user_meta($user_id, 'user_earning_points', true));
			$has_loyalty_role = false;

			foreach ($current_roles as $role) {
				if (in_array($role, $allowed_loyalty_roles, true)) {
					$has_loyalty_role = true;
					break;
				}
			}

			if (!$has_loyalty_role) {
				continue;
			}

			$new_role = 'customer';
			foreach ($loyalty_levels as $role_slug => $rule) {
				$min_points = isset($rule['from']) ? absint($rule['from']) : 0;
				if ($earning_points >= $min_points) {
					$new_role = $role_slug;
				}
			}

			if (!in_array($new_role, $current_roles, true)) {
				YOWCL_Order_Rewards::level( (int) $user_id, (array) $loyalty_levels );
			}
		}

		wp_safe_redirect(add_query_arg('loyalty_redefine_success', 'true', $redirect_url));
		exit;
	}

	public function maybe_show_admin_notices() {
		if ( null !== filter_input( INPUT_GET, 'loyalty_redefine_success', FILTER_UNSAFE_RAW ) ) {
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e('Loyalty levels have been successfully redefined.', 'loyalty-for-woocommerce'); ?></p>
			</div>
			<?php
		}

		if ( null !== filter_input( INPUT_GET, 'loyalty_redefine_error', FILTER_UNSAFE_RAW ) ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p><?php esc_html_e('An error occurred redefining loyalty levels.', 'loyalty-for-woocommerce'); ?></p>
			</div>
			<?php
		}
	}
}

new YOSWC_Loyalty_Settings_Tools();

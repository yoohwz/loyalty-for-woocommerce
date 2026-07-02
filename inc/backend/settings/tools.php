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
							<input type="submit" name="import_csv" id="import_csv" class="button-primary" value="<?php esc_attr_e('Import', 'loyalty-for-woocommerce'); ?>" disabled>
						</span>
					</form>
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

	private function import_csv() {
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

		$rows = preg_split('/\r\n|\r|\n/', trim($csv_contents));
		array_shift($rows);

		$rows_imported = 0;
		$rows_failed = 0;

		foreach ($rows as $line) {
			if ('' === trim($line)) {
				continue;
			}

			$row = str_getcsv($line, ',');
			$user_id = isset($row[0]) ? absint($row[0]) : 0;
			$user_points = isset($row[1]) ? max(0, (float) $row[1]) : 0;
			$user_earning_points = isset($row[2]) ? max(0, (float) $row[2]) : 0;

			if (get_userdata($user_id)) {
				update_user_meta($user_id, 'user_points', $user_points);
				update_user_meta($user_id, 'user_earning_points', $user_earning_points);
				$rows_imported++;
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
				$wp_user_object->set_role($new_role);
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

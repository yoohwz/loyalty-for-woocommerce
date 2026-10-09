<?php
// Capture affirmative provenance while the immutable actual old Free is still installed.
if (!defined('LOYF_RUNTIME_DISPOSABLE')) { throw new RuntimeException('Disposable required'); }
require_once getenv('LOYF_MIGRATION_CAPTURE_SOURCE');
wp_set_current_user(1);
$feature=$args[0]??'';
YOWCL_Free_Migrations::capture_legacy($feature,wp_create_nonce('loyf_capture_legacy_'.$feature));
echo "Verified old-Free source capture: $feature\n";

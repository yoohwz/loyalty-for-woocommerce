#!/usr/bin/env bash
set -euo pipefail
[[ "${LOY_RUNTIME_DISPOSABLE:-}" == 1 && -n "${LOY_DB_HOST:-}" && -n "${LOY_DB_USER:-}" && -n "${LOY_DB_PASSWORD:-}" ]] || { echo 'Disposable database required' >&2; exit 2; }
repo=$(git rev-parse --show-toplevel)
[[ "$repo" != *'/Local Sites/'* && -z $(git status --porcelain --untracked-files=all) ]] || { echo 'Clean external exact candidate required' >&2; exit 2; }
candidate=$(git rev-parse HEAD)
task_tmp=$(mktemp -d "${TMPDIR:-/tmp}/loyf-onboarding.XXXXXXXX")
databases=(); server_pid=; activation_pid=; contender_pid=
cleanup() {
  result=$?
  if [[ -n "$activation_pid" ]]; then kill "$activation_pid" 2>/dev/null || true; wait "$activation_pid" 2>/dev/null || true; fi
  if [[ -n "$contender_pid" ]]; then kill "$contender_pid" 2>/dev/null || true; wait "$contender_pid" 2>/dev/null || true; fi
  if [[ -n "$server_pid" ]]; then kill "$server_pid" 2>/dev/null || true; wait "$server_pid" 2>/dev/null || true; fi
  for database in "${databases[@]}"; do MYSQL_PWD="$LOY_DB_PASSWORD" mysql --host="$LOY_DB_HOST" --port="${LOY_DB_PORT:-3306}" --user="$LOY_DB_USER" -e "DROP DATABASE IF EXISTS \`$database\`" >/dev/null 2>&1 || result=1; done
  if [[ "$result" != 0 && -f "$task_tmp/browser.log" ]]; then tail -n 60 "$task_tmp/browser.log" >&2; fi
  python3 - "$task_tmp" <<'PY'
import shutil,sys
shutil.rmtree(sys.argv[1])
PY
  trap - EXIT; exit "$result"
}
trap cleanup EXIT
export MYSQL_PWD="$LOY_DB_PASSWORD" LOYF_WP_CLI_PHAR="$task_tmp/wp.phar"
curl -fsSL --retry 3 https://github.com/wp-cli/wp-cli/releases/download/v2.12.0/wp-cli-2.12.0.phar -o "$task_tmp/wp.phar"
mkdir -p "$task_tmp/template"
php "$task_tmp/wp.phar" --path="$task_tmp/template" core download --version=6.8.3 --skip-content --quiet
curl -fsSL --retry 3 https://downloads.wordpress.org/plugin/woocommerce.9.9.5.zip -o "$task_tmp/woo.zip"
mkdir -p "$task_tmp/template/wp-content/plugins" "$task_tmp/template/wp-content/mu-plugins"
unzip -q "$task_tmp/woo.zip" -d "$task_tmp/template/wp-content/plugins"
plugin="$task_tmp/template/wp-content/plugins/loyalty-for-woocommerce"; mkdir -p "$plugin"
git archive "$candidate" -- loyalty-for-woocommerce.php readme.txt changelog.txt license.txt css img inc js languages templates | tar -x -C "$plugin"
cp "$repo/tests/runtime/mu-isolation.php" "$task_tmp/template/wp-content/mu-plugins/loyf-runtime.php"
cat > "$task_tmp/template/wp-content/mu-plugins/loyf-onboarding-fault.php" <<'PHP'
<?php
add_filter('query',static function($query){
    if('assessment-race'===getenv('LOYF11_CASE')&&'1'===getenv('LOYF11_ACTOR_B')&&false!==strpos($query,"option_name='active_plugins'")){
        foreach(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)as$frame){if('YOWCL_Free_Onboarding'===($frame['class']??'')&&'assess'===($frame['function']??'')){
            $barrier=getenv('LOYF11_BARRIER');file_put_contents($barrier.'.b-ready','ready');
            $until=microtime(true)+30;while(!file_exists($barrier.'.b-go')&&microtime(true)<$until){usleep(20000);}
            if(!file_exists($barrier.'.b-go')){throw new RuntimeException('Assessment contender barrier timed out');}break;
        }}
    }
    $file=getenv('LOYF11_FAIL_FILE');
    if ($file && file_exists($file) && false!==strpos($query,"option_name='loyf_onboarding_v1'")) {
        unlink($file); global $wpdb; $wpdb->suppress_errors(true); return 'SELECT * FROM loyf11_deliberately_unavailable_source';
    }
    return $query;
},PHP_INT_MAX);
function loyf11_pause_bootstrap() {
    if ('1'!==getenv('LOYF11_ACTOR_A')) { return; }
    $barrier=getenv('LOYF11_BARRIER'); file_put_contents($barrier.'.ready','ready');
    $until=microtime(true)+30; while(!file_exists($barrier.'.go')&&microtime(true)<$until){usleep(20000);}
    if(!file_exists($barrier.'.go')){throw new RuntimeException('Bootstrap barrier timed out');}
}
add_action('added_option',static function($name,$value){
    if(in_array(getenv('LOYF11_CASE'),array('bootstrap-race','assessment-race'),true)&&'loyf_onboarding_v1'===$name&&is_array($value)&&'proven'===($value['status']??'')){loyf11_pause_bootstrap();}
},10,2);
add_action('activated_plugin',static function($plugin){
    if('assessment-race'!==getenv('LOYF11_CASE')||'1'!==getenv('LOYF11_ACTOR_B')||'loyalty-for-woocommerce/loyalty-for-woocommerce.php'!==$plugin){return;}
    wp_set_current_user(get_user_by('login','loyf_admin')->ID);
    $_POST=array('earning_point_rules_nonce'=>wp_create_nonce('save_earning_point_rules'),'loyalty_earning_points'=>array('customer'=>'7'),'loyalty_earning_amount'=>array('customer'=>'5'));
    (new YOSWC_Loyalty_Settings())->save_earning_point_rules();
    echo 'Native contender merchant setting:',wp_json_encode(get_option('loyalty_points_earning_rules')),PHP_EOL;
});
add_action('yowcl_reward_test_checkpoint',static function($step){
    if('seal-race'===getenv('LOYF11_CASE')&&'onboarding_baseline_snapshot'===$step){loyf11_pause_bootstrap();}
});
PHP
cases='fresh skip options premium user-marker order-marker used-marker discount-marker hpos-marker hpos-used-marker version bad-cutover migration bad-witness role orphan-role license as-group cron balance log assessment-failure late-setting late-balance failure first-failure referral-failure unknown-referral disconnect parallel merchant-race bootstrap-race seal-race assessment-race dismiss'
cases="${LOYF11_CASES:-$cases}"
for case in $cases; do
  case "$case" in fresh|skip|options|premium|user-marker|order-marker|used-marker|discount-marker|hpos-marker|hpos-used-marker|version|bad-cutover|migration|bad-witness|role|orphan-role|license|as-group|cron|balance|log|assessment-failure|late-setting|late-balance|failure|first-failure|referral-failure|unknown-referral|disconnect|parallel|merchant-race|bootstrap-race|seal-race|assessment-race|dismiss|browser|browser-exit) ;; *) echo 'Unknown onboarding fixture' >&2; exit 2 ;; esac
done
if [[ "${LOYF_SKIP_BROWSER:-}" != 1 ]]; then
  if [[ " $cases " != *' browser '* ]]; then cases="$cases browser browser-exit"; fi
  if [[ -z "${LOYF_PLAYWRIGHT_PATH:-}" ]]; then npm install --prefix "$task_tmp/browser" playwright@1.56.1 --no-audit --no-fund; export LOYF_PLAYWRIGHT_PATH="$task_tmp/browser/node_modules/playwright"; fi
  if [[ -z "${LOYF_BROWSER_EXECUTABLE:-}" ]]; then node "$LOYF_PLAYWRIGHT_PATH/cli.js" install --with-deps chromium; fi
fi
for case in $cases; do
  export LOYF11_CASE="$case" LOYF11_FIXTURE="$task_tmp/$case.json" LOYF11_FAIL_FILE="$task_tmp/$case.fail"
  database="loyf_rt_$(od -An -N8 -tx1 /dev/urandom | tr -d ' \n')"; databases+=( "$database" )
  mysql --host="$LOY_DB_HOST" --port="${LOY_DB_PORT:-3306}" --user="$LOY_DB_USER" -e "CREATE DATABASE \`$database\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
  site="$task_tmp/$case"; mkdir -p "$site"; cp -R "$task_tmp/template/." "$site/"
  wp() { php "$task_tmp/wp.phar" --path="$site" "$@"; }
  wp core config --dbname="$database" --dbuser="$LOY_DB_USER" --dbpass="$LOY_DB_PASSWORD" --dbhost="$LOY_DB_HOST:${LOY_DB_PORT:-3306}" --skip-check --quiet
  wp core install --url=http://loyf-onboarding.invalid --title=Onboarding --admin_user=loyf_admin --admin_password=disposable-only --admin_email=admin@example.invalid --skip-email --quiet
  wp config set DISABLE_WP_CRON true --raw --quiet
  wp plugin activate woocommerce --quiet
  if [[ "$case" == hpos-marker || "$case" == hpos-used-marker ]]; then wp option update woocommerce_custom_orders_table_data_sync_enabled no --quiet; wp option update woocommerce_custom_orders_table_enabled yes --quiet; fi
  LOYF11_PHASE=seed wp eval-file "$repo/tests/runtime/onboarding-fixture.php" --quiet
  if [[ "$case" == bootstrap-race || "$case" == seal-race || "$case" == assessment-race ]]; then
    export LOYF11_BARRIER="$task_tmp/$case-barrier"
    if [[ "$case" == assessment-race ]]; then
      LOYF11_ACTOR_B=1 wp plugin activate loyalty-for-woocommerce --quiet > "$task_tmp/$case-contender.log" 2>&1 & contender_pid=$!
      for attempt in {1..1000}; do [[ ! -f "$LOYF11_BARRIER.b-ready" ]] || break; sleep 0.02; done
      [[ -f "$LOYF11_BARRIER.b-ready" ]] || { cat "$task_tmp/$case-contender.log" >&2; echo 'Assessment contender did not reach barrier' >&2; exit 1; }
    fi
    LOYF11_ACTOR_A=1 wp plugin activate loyalty-for-woocommerce --quiet > "$task_tmp/$case-activation.log" 2>&1 & activation_pid=$!
    for attempt in {1..1000}; do [[ ! -f "$LOYF11_BARRIER.ready" ]] || break; sleep 0.02; done
    [[ -f "$LOYF11_BARRIER.ready" ]] || { cat "$task_tmp/$case-activation.log" >&2; echo 'Native first activation did not reach barrier' >&2; exit 1; }
    if [[ "$case" == assessment-race ]]; then
      touch "$LOYF11_BARRIER.b-go"
      wait "$contender_pid"; contender_pid=
      cat "$task_tmp/$case-contender.log"
    else
      wp plugin activate loyalty-for-woocommerce --quiet
      LOYF11_PHASE=bootstrap-merchant wp eval-file "$repo/tests/runtime/onboarding-fixture.php" --quiet
    fi
    touch "$LOYF11_BARRIER.go"
    wait "$activation_pid"; activation_pid=
  else
    wp plugin activate loyalty-for-woocommerce --quiet
  fi
  LOYF11_PHASE=verify wp eval-file "$repo/tests/runtime/onboarding-fixture.php" --quiet
  # Reactivation never turns an existing or completed installation into a writable wizard.
  if [[ "$case" == fresh || "$case" == bad-witness || "$case" == dismiss ]]; then
    wp plugin deactivate loyalty-for-woocommerce --quiet; wp plugin activate loyalty-for-woocommerce --quiet
    wp eval 'if(YOWCL_Free_Onboarding::writable()){throw new RuntimeException("Reactivation restarted wizard");}' --quiet
  fi
  if [[ "$case" == browser || "$case" == browser-exit ]]; then
    wp option update woocommerce_onboarding_profile '{"skipped":true}' --format=json --quiet
    wp transient delete _wc_activation_redirect --quiet
    export LOYF_BROWSER_URL="http://127.0.0.1:${LOYF11_BROWSER_PORT:-18089}"
    wp option update home "$LOYF_BROWSER_URL" --quiet; wp option update siteurl "$LOYF_BROWSER_URL" --quiet
    php -d opcache.enable_cli=0 -d opcache.enable=0 -d opcache.jit=0 -S "127.0.0.1:${LOYF11_BROWSER_PORT:-18089}" -t "$site" "$repo/tests/runtime/browser-router.php" > "$task_tmp/browser.log" 2>&1 & server_pid=$!
    if ! node "$repo/tests/runtime/onboarding-browser.cjs"; then LOYF11_PHASE=browser-debug wp eval-file "$repo/tests/runtime/onboarding-fixture.php" --quiet; exit 1; fi
    LOYF11_PHASE=browser-check wp eval-file "$repo/tests/runtime/onboarding-fixture.php" --quiet
    kill "$server_pid"; wait "$server_pid" 2>/dev/null || true; server_pid=
  fi
done
echo "Native onboarding activation/footprint/failure/browser PASS $candidate wp6.8.3 woo9.9.5"

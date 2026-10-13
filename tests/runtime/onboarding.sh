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
  if [[ "$result" != 0 ]]; then
    for log in "$task_tmp"/*-activation.log "$task_tmp"/*-contender.log; do [[ ! -f "$log" ]] || tail -n 60 "$log" >&2; done
  fi
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
export MYSQL_PWD="$LOY_DB_PASSWORD"
if [[ -z "${LOYF_WP_CLI_PHAR:-}" ]]; then
  export LOYF_WP_CLI_PHAR="$task_tmp/wp.phar"
curl -fsSL --retry 3 https://github.com/wp-cli/wp-cli/releases/download/v2.12.0/wp-cli-2.12.0.phar -o "$task_tmp/wp.phar"
fi
mkdir -p "$task_tmp/template"
php "$LOYF_WP_CLI_PHAR" --path="$task_tmp/template" core download --version=6.8.3 --skip-content --quiet
curl -fsSL --retry 3 https://downloads.wordpress.org/plugin/woocommerce.9.9.5.zip -o "$task_tmp/woo.zip"
mkdir -p "$task_tmp/template/wp-content/plugins" "$task_tmp/template/wp-content/mu-plugins"
unzip -q "$task_tmp/woo.zip" -d "$task_tmp/template/wp-content/plugins"
plugin="$task_tmp/template/wp-content/plugins/loyalty-for-woocommerce"; mkdir -p "$plugin"
git archive "$candidate" -- loyalty-for-woocommerce.php readme.txt changelog.txt license.txt css img inc js languages templates | tar -x -C "$plugin"
cp "$repo/tests/runtime/mu-isolation.php" "$task_tmp/template/wp-content/mu-plugins/loyf-runtime.php"
cases='fresh options premium user-marker order-marker used-marker discount-marker hpos-marker hpos-used-marker version bad-cutover migration bad-witness role orphan-role license as-group cron balance log proven started complete dismissed review'
cases="${LOYF11_CASES:-$cases}"
for case in $cases; do
  case "$case" in fresh|options|premium|user-marker|order-marker|used-marker|discount-marker|hpos-marker|hpos-used-marker|version|bad-cutover|migration|bad-witness|role|orphan-role|license|as-group|cron|balance|log|proven|started|complete|dismissed|review) ;; *) echo 'Unknown activation fixture' >&2; exit 2 ;; esac
done
if [[ "${LOYF_SKIP_BROWSER:-}" != 1 ]]; then
  if [[ -z "${LOYF_PLAYWRIGHT_PATH:-}" ]]; then npm install --prefix "$task_tmp/browser" playwright@1.56.1 --no-audit --no-fund; export LOYF_PLAYWRIGHT_PATH="$task_tmp/browser/node_modules/playwright"; fi
  if [[ -z "${LOYF_BROWSER_EXECUTABLE:-}" ]]; then node "$LOYF_PLAYWRIGHT_PATH/cli.js" install --with-deps chromium; fi
fi
for case in $cases; do
  export LOYF11_CASE="$case" LOYF11_FIXTURE="$task_tmp/$case.json" LOYF11_FAIL_FILE="$task_tmp/$case.fail"
  database="loyf_rt_$(od -An -N8 -tx1 /dev/urandom | tr -d ' \n')"; databases+=( "$database" )
  mysql --host="$LOY_DB_HOST" --port="${LOY_DB_PORT:-3306}" --user="$LOY_DB_USER" -e "CREATE DATABASE \`$database\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
  site="$task_tmp/$case"; mkdir -p "$site"; cp -R "$task_tmp/template/." "$site/"
  wp() { php "$LOYF_WP_CLI_PHAR" --path="$site" "$@"; }
  wp core config --dbname="$database" --dbuser="$LOY_DB_USER" --dbpass="$LOY_DB_PASSWORD" --dbhost="$LOY_DB_HOST:${LOY_DB_PORT:-3306}" --skip-check --quiet
  wp core install --url=http://loyf-onboarding.invalid --title=Onboarding --admin_user=loyf_admin --admin_password=disposable-only --admin_email=admin@example.invalid --skip-email --quiet
  wp config set DISABLE_WP_CRON true --raw --quiet
  wp plugin activate woocommerce --quiet
  if [[ "$case" == hpos-marker || "$case" == hpos-used-marker ]]; then wp option update woocommerce_custom_orders_table_data_sync_enabled no --quiet; wp option update woocommerce_custom_orders_table_enabled yes --quiet; fi
  LOYF11_PHASE=seed wp eval-file "$repo/tests/runtime/onboarding-fixture.php" --quiet
  wp plugin activate loyalty-for-woocommerce --quiet
  LOYF11_PHASE=verify wp eval-file "$repo/tests/runtime/onboarding-fixture.php" --quiet
  if [[ "$case" == fresh || "$case" == started || "$case" == complete || "$case" == dismissed ]]; then
    LOYF11_PHASE=reactivation-before wp eval-file "$repo/tests/runtime/onboarding-fixture.php" --quiet
    wp plugin deactivate loyalty-for-woocommerce --quiet; wp plugin activate loyalty-for-woocommerce --quiet
    LOYF11_PHASE=reactivation-after wp eval-file "$repo/tests/runtime/onboarding-fixture.php" --quiet
  fi
  if [[ "${LOYF_SKIP_BROWSER:-}" != 1 && ( "$case" == fresh || "$case" == started ) ]]; then
    wp option update woocommerce_onboarding_profile '{"skipped":true}' --format=json --quiet
    wp transient delete _wc_activation_redirect --quiet
    export LOYF_BROWSER_URL="http://127.0.0.1:${LOYF11_BROWSER_PORT:-18089}"
    wp option update home "$LOYF_BROWSER_URL" --quiet; wp option update siteurl "$LOYF_BROWSER_URL" --quiet
    php -d opcache.enable_cli=0 -d opcache.enable=0 -d opcache.jit=0 -S "127.0.0.1:${LOYF11_BROWSER_PORT:-18089}" -t "$site" "$repo/tests/runtime/browser-router.php" > "$task_tmp/browser.log" 2>&1 & server_pid=$!
    LOYF11_PHASE=browser-before wp eval-file "$repo/tests/runtime/onboarding-fixture.php" --quiet
    node "$repo/tests/runtime/onboarding-browser.cjs"
    LOYF11_PHASE=browser-after wp eval-file "$repo/tests/runtime/onboarding-fixture.php" --quiet
    kill "$server_pid"; wait "$server_pid" 2>/dev/null || true; server_pid=
  fi
done
echo "Native setup retirement/activation/footprint/browser PASS $candidate wp6.8.3 woo9.9.5"

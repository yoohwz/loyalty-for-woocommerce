#!/usr/bin/env bash
set -euo pipefail
[[ "${LOY_RUNTIME_DISPOSABLE:-}" == 1 ]] || { echo 'Disposable runtime required' >&2; exit 2; }
repo=$(git rev-parse --show-toplevel)
[[ "$repo" != *'/Local Sites/'* ]] || { echo 'Refusing installed Local site' >&2; exit 2; }
[[ -n "${LOY_DB_HOST:-}" && -n "${LOY_DB_USER:-}" && -n "${LOY_DB_PASSWORD:-}" ]] || exit 2
candidate=$(git rev-parse HEAD)
[[ -z $(git status --porcelain --untracked-files=all) ]] || { echo 'Clean committed candidate required' >&2; exit 2; }
task_tmp=$(mktemp -d "${TMPDIR:-/tmp}/loyf-modern.XXXXXXXX")
databases=()
server_pid=
cleanup() {
  result=$?
  if [[ -n "$server_pid" ]]; then kill "$server_pid" 2>/dev/null || true; wait "$server_pid" 2>/dev/null || true; fi
  for database in "${databases[@]}"; do
    MYSQL_PWD="$LOY_DB_PASSWORD" mysql --host="$LOY_DB_HOST" --port="${LOY_DB_PORT:-3306}" --user="$LOY_DB_USER" -e "DROP DATABASE IF EXISTS \`$database\`" >/dev/null 2>&1 || result=1
  done
  python3 - "$task_tmp" <<'PY'
import shutil,sys
shutil.rmtree(sys.argv[1])
PY
  trap - EXIT; exit "$result"
}
trap cleanup EXIT
export LOYF_WP_CLI_PHAR="$task_tmp/wp.phar"
export MYSQL_PWD="$LOY_DB_PASSWORD" LOYF_FIXTURE="$repo/tests/fixtures/free-1.2.2.json"
curl -fsSL --retry 3 https://github.com/wp-cli/wp-cli/releases/download/v2.12.0/wp-cli-2.12.0.phar -o "$task_tmp/wp.phar"
if [[ "${LOYF_SKIP_BROWSER:-}" != 1 ]]; then
  if [[ -z "${LOYF_PLAYWRIGHT_PATH:-}" ]]; then
    npm install --prefix "$task_tmp/browser" playwright@1.56.1 --no-audit --no-fund
    export LOYF_PLAYWRIGHT_PATH="$task_tmp/browser/node_modules/playwright"
  fi
  if [[ -z "${LOYF_BROWSER_EXECUTABLE:-}" ]]; then node "$(dirname "$LOYF_PLAYWRIGHT_PATH")/playwright/cli.js" install --with-deps chromium; fi
fi
for versions in '6.8.3:9.9.5' '7.0:11.1.2'; do
  wordpress=${versions%:*}; woocommerce=${versions#*:}
  for storage in cpt hpos; do
    database="loyf_rt_$(od -An -N8 -tx1 /dev/urandom | tr -d ' \n')"; databases+=( "$database" )
    mysql --host="$LOY_DB_HOST" --port="${LOY_DB_PORT:-3306}" --user="$LOY_DB_USER" -e "CREATE DATABASE \`$database\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    site="$task_tmp/$woocommerce-$storage"; mkdir -p "$site"
    wp() { php "$task_tmp/wp.phar" --path="$site" "$@"; }
    wp core download --version="$wordpress" --skip-content --quiet
    wp core config --dbname="$database" --dbuser="$LOY_DB_USER" --dbpass="$LOY_DB_PASSWORD" --dbhost="$LOY_DB_HOST:${LOY_DB_PORT:-3306}" --skip-check --quiet
    wp core install --url=http://loyf-modern.invalid --title='Modern Free matrix' --admin_user=loyf_admin --admin_password=disposable-only --admin_email=admin@example.invalid --skip-email --quiet
    wp plugin install woocommerce --version="$woocommerce" --quiet
    plugin="$site/wp-content/plugins/loyalty-for-woocommerce"; mkdir -p "$plugin" "$site/wp-content/mu-plugins"
    git archive "$candidate" -- loyalty-for-woocommerce.php readme.txt changelog.txt license.txt css img inc js languages templates | tar -x -C "$plugin"
    cp "$repo/tests/runtime/mu-isolation.php" "$site/wp-content/mu-plugins/loyf-runtime.php"
    wp config set DOING_AJAX true --raw --quiet; wp config set DISABLE_WP_CRON true --raw --quiet
    wp plugin activate woocommerce --quiet; wp eval-file "$repo/tests/runtime/seed.php" --quiet
    wp option update woocommerce_custom_orders_table_data_sync_enabled no --quiet
    if [[ "$storage" == hpos ]]; then wp option update woocommerce_custom_orders_table_enabled yes --quiet; fi
    wp plugin activate loyalty-for-woocommerce --quiet
    export LOYF_STORAGE="$storage"
    wp eval-file "$repo/tests/runtime/first-purchase.php" --quiet
    wp eval-file "$repo/tests/runtime/modern.php" --quiet
    if [[ "${LOYF_SKIP_BROWSER:-}" != 1 ]]; then
      curl -fsSL --retry 3 https://downloads.wordpress.org/theme/twentytwentyfive.1.3.zip -o "$task_tmp/theme.zip"
      wp theme install "$task_tmp/theme.zip" --activate --skip-plugins --skip-themes --quiet
      wp config delete DOING_AJAX --quiet
      export LOYF_BROWSER_URL="http://127.0.0.1:${LOYF_BROWSER_PORT:-18088}" LOYF_BROWSER_FIXTURE="$task_tmp/browser-fixture.json"
      wp option update siteurl "$LOYF_BROWSER_URL" --quiet; wp option update home "$LOYF_BROWSER_URL" --quiet
      wp option update loyalty_customization_cart_checkout '{"cart":0,"checkout":0}' --format=json --quiet
      wp eval-file "$repo/tests/runtime/browser-seed.php" --quiet
      php -S "127.0.0.1:${LOYF_BROWSER_PORT:-18088}" -t "$site" "$repo/tests/runtime/browser-router.php" > "$task_tmp/browser-server.log" 2>&1 & server_pid=$!
      node "$repo/tests/runtime/blocks-browser.cjs"
      wp option update loyalty_points_using_rules '[]' --format=json --quiet
      for display in '1:0' '0:1' '0:0'; do
        export LOYF_SHOW_EARNED_CART=${display%:*} LOYF_SHOW_EARNED_CHECKOUT=${display#*:}
        wp option update loyalty_customization_cart_checkout "{\"cart\":$LOYF_SHOW_EARNED_CART,\"checkout\":$LOYF_SHOW_EARNED_CHECKOUT}" --format=json --quiet
        node "$repo/tests/runtime/blocks-earning-browser.cjs"
      done
      kill "$server_pid"; wait "$server_pid" 2>/dev/null || true; server_pid=
      wp eval 'if (50 !== (int)get_user_meta(get_user_by("login","blocks_browser")->ID,"user_points",true)) { throw new RuntimeException("Browser selection mutated value"); }' --quiet
    fi
  done
done

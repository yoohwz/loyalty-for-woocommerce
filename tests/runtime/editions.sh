#!/usr/bin/env bash
set -euo pipefail
[[ "${LOY_RUNTIME_DISPOSABLE:-}" == 1 && -n "${LOY_DB_HOST:-}" && -n "${LOY_DB_USER:-}" && -n "${LOY_DB_PASSWORD:-}" ]] || exit 2
repo=$(git rev-parse --show-toplevel)
[[ "$repo" != *'/Local Sites/'* && -z $(git status --porcelain --untracked-files=all) ]] || exit 2
candidate=$(git rev-parse HEAD)
upstream=9e3d663b9828a24eacd141216d5e6f6e22da8ca4
[[ -n "${LOYF_PREMIUM_GIT_DIR:-}" && $(git --git-dir="$LOYF_PREMIUM_GIT_DIR" rev-parse "$upstream^{commit}") == "$upstream" ]] || { echo 'Pinned read-only actual Premium source required; installed edition proof UNAVAILABLE'; exit 2; }
task_tmp=$(mktemp -d "${TMPDIR:-/tmp}/loyf-editions.XXXXXXXX")
databases=()
cleanup() {
 result=$?
 for database in "${databases[@]}"; do MYSQL_PWD="$LOY_DB_PASSWORD" mysql --host="$LOY_DB_HOST" --port="${LOY_DB_PORT:-3306}" --user="$LOY_DB_USER" -e "DROP DATABASE IF EXISTS \`$database\`" >/dev/null 2>&1 || result=1; done
 python3 - "$task_tmp" <<'PY'
import shutil,sys
shutil.rmtree(sys.argv[1])
PY
 trap - EXIT; exit "$result"
}
trap cleanup EXIT
export MYSQL_PWD="$LOY_DB_PASSWORD"
curl -fsSL --retry 3 https://github.com/wp-cli/wp-cli/releases/download/v2.12.0/wp-cli-2.12.0.phar -o "$task_tmp/wp.phar"
for versions in '6.8.3:9.9.5' '7.0:11.1.2'; do
 wordpress=${versions%:*}; woocommerce=${versions#*:}
 template="$task_tmp/template-$woocommerce"; mkdir -p "$template/wp-content/plugins" "$template/wp-content/mu-plugins"
 php "$task_tmp/wp.phar" --path="$template" core download --version="$wordpress" --skip-content --quiet
 curl -fsSL --retry 3 "https://downloads.wordpress.org/plugin/woocommerce.$woocommerce.zip" -o "$task_tmp/woo.zip"
 unzip -q "$task_tmp/woo.zip" -d "$template/wp-content/plugins"
 for edition in loyalty-for-woocommerce wc-loyalty; do mkdir "$template/wp-content/plugins/$edition"; done
 git archive "$candidate" -- loyalty-for-woocommerce.php readme.txt changelog.txt license.txt css img inc js languages templates | tar -x -C "$template/wp-content/plugins/loyalty-for-woocommerce"
 git --git-dir="$LOYF_PREMIUM_GIT_DIR" archive "$upstream" | tar -x -C "$template/wp-content/plugins/wc-loyalty"
 cp "$repo/tests/runtime/edition-isolation.php" "$template/wp-content/mu-plugins/loyf-edition.php"
 for storage in cpt hpos; do
  for origin in free premium; do
   database="loyf_rt_$(od -An -N8 -tx1 /dev/urandom | tr -d ' \n')"; databases+=( "$database" )
   mysql --host="$LOY_DB_HOST" --port="${LOY_DB_PORT:-3306}" --user="$LOY_DB_USER" -e "CREATE DATABASE \`$database\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
   site="$task_tmp/$woocommerce-$storage-$origin"; mkdir -p "$site"; cp -R "$template/." "$site/"
   wp() { php "$task_tmp/wp.phar" --path="$site" "$@"; }
   wp core config --dbname="$database" --dbuser="$LOY_DB_USER" --dbpass="$LOY_DB_PASSWORD" --dbhost="$LOY_DB_HOST:${LOY_DB_PORT:-3306}" --skip-check --quiet
   wp core install --url=http://loyf-editions.invalid --title='Edition certification' --admin_user=loyf_admin --admin_password=disposable-only --admin_email=admin@example.invalid --skip-email --quiet
   wp config set DISABLE_WP_CRON true --raw --quiet; wp config set DOING_AJAX true --raw --quiet
   wp plugin activate woocommerce --quiet
   wp option update woocommerce_custom_orders_table_data_sync_enabled no --quiet
   if [[ "$storage" == hpos ]]; then wp option update woocommerce_custom_orders_table_enabled yes --quiet; fi
   export LOYF_STORAGE="$storage" LOYF13_EDITION_FIXTURE="$task_tmp/fixture.json" LOYF13_SNAPSHOT="$task_tmp/origin.json"
   if [[ "$origin" == free ]]; then
    wp plugin activate loyalty-for-woocommerce --quiet
    LOYF13_EDITION_PHASE=free-seed wp eval-file "$repo/tests/runtime/edition-events.php"
    wp plugin deactivate loyalty-for-woocommerce --quiet; wp plugin activate wc-loyalty --quiet
    LOYF13_OWNER=wc-loyalty wp eval-file "$repo/tests/runtime/edition-owner.php"
    wp plugin activate loyalty-for-woocommerce --quiet
    LOYF13_OWNER=wc-loyalty wp eval-file "$repo/tests/runtime/edition-owner.php"
    wp plugin deactivate loyalty-for-woocommerce --quiet
    LOYF13_EDITION_PHASE=premium-replay wp eval-file "$repo/tests/runtime/edition-events.php"
    wp plugin deactivate wc-loyalty --quiet; wp plugin activate loyalty-for-woocommerce --quiet
    LOYF13_OWNER=loyalty-for-woocommerce wp eval-file "$repo/tests/runtime/edition-owner.php"
    wp plugin activate wc-loyalty --quiet
    LOYF13_OWNER=wc-loyalty wp eval-file "$repo/tests/runtime/edition-owner.php"
    wp plugin deactivate wc-loyalty --quiet
    LOYF13_OWNER=loyalty-for-woocommerce wp eval-file "$repo/tests/runtime/edition-owner.php"
    LOYF13_EDITION_PHASE=free-replay wp eval-file "$repo/tests/runtime/edition-events.php"
   else
    wp plugin activate wc-loyalty --quiet; wp eval-file "$repo/tests/runtime/edition-origin-seed.php"
    wp plugin deactivate wc-loyalty --quiet; wp plugin activate loyalty-for-woocommerce --quiet
    wp eval-file "$repo/tests/runtime/edition-origin-held.php"
    wp plugin deactivate loyalty-for-woocommerce --quiet; wp plugin activate wc-loyalty --quiet
    wp eval-file "$repo/tests/runtime/edition-origin-compare.php"
   fi
   echo "Installed edition transition PASS candidate=$candidate WP=$wordpress Woo=$woocommerce storage=$storage origin=$origin Premium=$upstream entitlement=SIMULATED"
   mysql --host="$LOY_DB_HOST" --port="${LOY_DB_PORT:-3306}" --user="$LOY_DB_USER" -e "DROP DATABASE \`$database\`"
   python3 - "$site" <<'PY'
import shutil,sys
shutil.rmtree(sys.argv[1])
PY
  done
 done
 python3 - "$template" <<'PY'
import shutil,sys
shutil.rmtree(sys.argv[1])
PY
done

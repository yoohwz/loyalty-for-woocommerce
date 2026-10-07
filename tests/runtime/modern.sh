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
cleanup() {
  result=$?
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
export MYSQL_PWD="$LOY_DB_PASSWORD" LOYF_FIXTURE="$repo/tests/fixtures/free-1.2.2.json"
curl -fsSL --retry 3 https://github.com/wp-cli/wp-cli/releases/download/v2.12.0/wp-cli-2.12.0.phar -o "$task_tmp/wp.phar"
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
    wp eval-file "$repo/tests/runtime/modern.php" --quiet
  done
done

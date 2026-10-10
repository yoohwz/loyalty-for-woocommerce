#!/usr/bin/env bash
set -euo pipefail
[[ $# -eq 0 ]] || { echo 'Usage: run.sh' >&2; exit 2; }
[[ "${LOY_RUNTIME_DISPOSABLE:-}" == 1 ]] || { echo 'Set LOY_RUNTIME_DISPOSABLE=1 for an isolated test database.' >&2; exit 2; }
[[ -n "${LOY_DB_HOST:-}" && -n "${LOY_DB_USER:-}" && -n "${LOY_DB_PASSWORD:-}" ]] || { echo 'Disposable database connection is required.' >&2; exit 2; }
repo=$(git rev-parse --show-toplevel)
head=$(git -C "$repo" rev-parse HEAD)
[[ "$repo" != *'/Local Sites/'* ]] || { echo 'Refusing to run from a Local site checkout.' >&2; exit 2; }
[[ -z "$(git -C "$repo" status --porcelain --untracked-files=all)" ]] || { echo 'Exact candidate checkout must be clean.' >&2; exit 2; }
base=$(python3 -c 'import json; print(json.load(open("tests/fixtures/free-1.2.2.json"))["free_sha"])')
git cat-file -e "$base^{commit}"
tmp=$(mktemp -d "${TMPDIR:-/tmp}/loyf-runtime.XXXXXXXX")
dbs=()
server_pid=
cleanup() {
    status=$?
    if [[ -n "$server_pid" ]]; then
        if [[ "$status" != 0 ]]; then tail -n 80 "$tmp/browser.log" >&2; fi
        kill "$server_pid" 2>/dev/null || true; wait "$server_pid" 2>/dev/null || true
    fi
    for db in "${dbs[@]}"; do
        if ! MYSQL_PWD="$LOY_DB_PASSWORD" mysql --host="$LOY_DB_HOST" --port="${LOY_DB_PORT:-3306}" --user="$LOY_DB_USER" -e "DROP DATABASE IF EXISTS \`$db\`" >/dev/null 2>&1; then
            echo 'Disposable database cleanup failed.' >&2
            status=1
        fi
    done
    if ! rm -rf -- "$tmp"; then status=1; fi
    trap - EXIT
    exit "$status"
}
trap cleanup EXIT
export MYSQL_PWD="$LOY_DB_PASSWORD"
export LOYF_WP_CLI_PHAR="$tmp/wp.phar"
export LOYF_FIXTURE="$repo/tests/fixtures/free-1.2.2.json"
curl -fsSL --retry 3 https://github.com/wp-cli/wp-cli/releases/download/v2.12.0/wp-cli-2.12.0.phar -o "$tmp/wp.phar"
wp() { php "$tmp/wp.phar" --path="$site" "$@"; }
for phase in baseline uncaptured candidate; do
    sha=$head
    [[ "$phase" == candidate ]] || sha=$base
    db="loyf_rt_$(od -An -N8 -tx1 /dev/urandom | tr -d ' \n')"
    site="$tmp/$phase"
    # Track before CREATE: cleanup also covers a client failure after server creation.
    dbs+=( "$db" )
    mysql --host="$LOY_DB_HOST" --port="${LOY_DB_PORT:-3306}" --user="$LOY_DB_USER" -e "CREATE DATABASE \`$db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    mkdir -p "$site"
    wp core download --version=6.8.3 --locale=en_US --skip-content --quiet
    wp core config --dbname="$db" --dbuser="$LOY_DB_USER" --dbpass="$LOY_DB_PASSWORD" --dbhost="$LOY_DB_HOST:${LOY_DB_PORT:-3306}" --skip-check --quiet
    wp core install --url=http://loyf-runtime.invalid --title='LOYF Runtime' --admin_user=loyf_admin --admin_password='disposable-only-password' --admin_email=admin@example.invalid --skip-email --quiet
    wp plugin install woocommerce --version=9.9.5 --quiet
    plugin="$site/wp-content/plugins/loyalty-for-woocommerce"
    mkdir -p "$plugin" "$site/wp-content/mu-plugins"
    paths=(loyalty-for-woocommerce.php readme.txt changelog.txt license.txt css img inc js languages)
    if git -C "$repo" cat-file -e "$sha:templates" 2>/dev/null; then paths+=(templates); fi
    git -C "$repo" archive "$sha" -- "${paths[@]}" | tar -x -C "$plugin"
    cp "$repo/tests/runtime/mu-isolation.php" "$site/wp-content/mu-plugins/loyf-runtime.php"
    wp config set WP_HTTP_BLOCK_EXTERNAL true --raw --quiet
    wp config set DISABLE_WP_CRON true --raw --quiet
    wp config set WP_AUTO_UPDATE_CORE false --raw --quiet
    wp config set DOING_AJAX true --raw --quiet
    wp plugin activate woocommerce --quiet
    wp eval-file "$repo/tests/runtime/seed.php" --quiet
    if [[ "$phase" == candidate ]]; then wp eval-file "$repo/tests/runtime/pre-cutover.php" --quiet; fi
    wp plugin activate loyalty-for-woocommerce --quiet
    if [[ "$phase" == candidate ]]; then
      for feature in signup login review levelup redemption email_reward email_deduct email_level; do wp eval-file "$repo/tests/runtime/resolve-fixture.php" "$feature" --quiet; done
    fi
    if [[ "$phase" == uncaptured ]]; then
        # A separate actual historical tree/database: never call capture_legacy here.
        export LOYF_SNAPSHOT="$tmp/uncaptured-legacy.json" LOYF_RAW_SNAPSHOT="$tmp/uncaptured-legacy-raw.json"
        wp eval-file "$repo/tests/runtime/characterization.php" --quiet
        cmp "$repo/tests/fixtures/free-1.2.2-expected.json" "$LOYF_SNAPSHOT"
        export LOYF25_SNAPSHOT="$tmp/uncaptured-before.json"
        wp eval-file "$repo/tests/runtime/upgrade-seed.php" --quiet
        wp eval-file "$repo/tests/runtime/migration-review.php" before --quiet
        rm -f "$plugin/inc/cores/api/push-subscription.php"
        git -C "$repo" archive "$head" -- loyalty-for-woocommerce.php readme.txt changelog.txt license.txt css img inc js languages templates | tar -x -C "$plugin"
        wp eval-file "$repo/tests/runtime/migration-review.php" upgrade --quiet
        if [[ "${LOYF_SKIP_BROWSER:-}" != 1 ]]; then
            if [[ -z "${LOYF_PLAYWRIGHT_PATH:-}" ]]; then
                npm install --prefix "$tmp/browser" playwright@1.56.1 --no-audit --no-fund
                export LOYF_PLAYWRIGHT_PATH="$tmp/browser/node_modules/playwright"
                node "$LOYF_PLAYWRIGHT_PATH/cli.js" install chromium --with-deps
            fi
            port="${LOYF25_BROWSER_PORT:-18095}"
            export LOYF_BROWSER_URL="http://127.0.0.1:$port" LOYF25_SITE="$site" LOYF25_FIXTURE="$repo/tests/runtime/migration-review.php"
            wp option update home "$LOYF_BROWSER_URL" --quiet
            wp option update siteurl "$LOYF_BROWSER_URL" --quiet
            wp config delete DOING_AJAX --quiet
            wp eval-file "$LOYF25_FIXTURE" browser-seed --quiet
            php -d opcache.enable=0 -d opcache.enable_cli=0 -d opcache.jit=0 -d opcache.jit_buffer_size=0 -S "127.0.0.1:$port" -t "$site" > "$tmp/browser.log" 2>&1 & server_pid=$!
            node "$repo/tests/runtime/migration-review-browser.cjs"
            kill "$server_pid"; wait "$server_pid" 2>/dev/null || true; server_pid=
        fi
        continue
    fi
    export LOYF_SNAPSHOT="$tmp/$phase.json" LOYF_RAW_SNAPSHOT="$tmp/$phase-raw.json"
    echo "phase=$phase candidate=$sha wp=6.8.3 woo=9.9.5 php=$(php -r 'echo PHP_VERSION;')"
    scenario=characterization.php
    [[ "$phase" != candidate ]] || scenario=hardened.php
    wp eval-file "$repo/tests/runtime/$scenario" --quiet
    test -s "$LOYF_SNAPSHOT" && test -s "$LOYF_RAW_SNAPSHOT"
    if [[ "$phase" == candidate ]]; then wp eval-file "$repo/tests/runtime/economic-certification.php" --quiet; fi
    if [[ "$phase" == baseline ]]; then
        # Upgrade the actual executed 1.2.2 database/tree; do not manufacture canonical history.
        wp eval-file "$repo/tests/runtime/upgrade-seed.php" --quiet
        export LOYF_MIGRATION_CAPTURE_SOURCE="$repo/inc/cores/helper/free-migrations.php"
        for feature in signup login review levelup redemption email_reward email_deduct email_level; do wp eval-file "$repo/tests/runtime/capture-legacy.php" "$feature" --quiet; done
        rm -f "$plugin/inc/cores/api/push-subscription.php"
        git -C "$repo" archive "$head" -- loyalty-for-woocommerce.php readme.txt changelog.txt license.txt css img inc js languages templates | tar -x -C "$plugin"
        upgrade_result=$(wp eval-file "$repo/tests/runtime/upgrade.php" --quiet)
        printf '%s\n' "$upgrade_result"
        [[ "$upgrade_result" == *'Actual old-Free → refreshed-Free upgrade PASS'* ]] || { echo 'Upgrade fixture did not complete.' >&2; exit 1; }
    fi
done
cmp "$repo/tests/fixtures/free-1.2.2-expected.json" "$tmp/baseline.json"
python3 - "$tmp/candidate.json" <<'PYJSON'
import json, sys
with open(sys.argv[1]) as f: result=json.load(f)
assert result == {'hardened_core': 'PASS'}, result
PYJSON
# Optional reusable evidence: raw stable IDs/dates/order/user data for later transition tasks.
if [[ -n "${LOYF_RUNTIME_ARTIFACTS:-}" ]]; then
    [[ "$LOYF_RUNTIME_ARTIFACTS" == /* && "$LOYF_RUNTIME_ARTIFACTS" != "$repo"* ]] || { echo 'Artifacts must be outside source.' >&2; exit 2; }
    mkdir -p "$LOYF_RUNTIME_ARTIFACTS"
    cp "$tmp/"*.json "$LOYF_RUNTIME_ARTIFACTS/"
fi
echo 'FREE-1.2.2 historical baseline and hardened candidate PASS'

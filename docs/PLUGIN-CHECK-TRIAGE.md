# WordPress.org Plugin Check evidence and triage

This is an engineering assessment, not WordPress.org review approval or permission to publish. The candidate remains version1.2.2. No errors/warnings, categories or files were hidden with ignore flags, `.pcpignore`, PHPCS suppressions or a lower severity. PCP's CLI exit0 does not mean zero findings.

## Reproduction

Install the exact verified Free ZIP into a disposable WordPress/Woo site, activate official WordPress.org **Plugin Check2.1.0**, then run:

```sh
php /absolute/wp.phar --path=/absolute/disposable/site \
  --require=/absolute/disposable/site/wp-content/plugins/plugin-check/cli.php \
  plugin check loyalty-for-woocommerce --format=json
```

The runtime bootstrap must load the activated checker; merely requiring its CLI file without the active plugin is not valid runtime evidence. The official CLI documentation is at https://github.com/WordPress/plugin-check/blob/trunk/docs/CLI.md. Native transport is intercepted in the disposable site; no service-provider subscription/license endpoint is contacted. Only the exact Free plugin is scanned.

The executed scan at `2b1dcf35328db7368982a75b7db1b90aa8e78638`, source/stage/ZIP/extraction102 files and verification ZIP SHA256 `59ef51b8acaf735f322bf5e998987006ee2ada176f8d2a87b07709e1a1c64e93`, reports **95 ERROR /224 WARNING (319 findings)**. The row inventory below uses that exact source's lines. The CLI renders temporary symlink paths as `/privateinc/...` or `/privatetemplates/...`; the table normalizes only that leading `/private`, and every resulting source path was inspected. Later candidates must rerun the scan and reconcile any changes before acceptance.

## Corrections and dispositions

Thirteen missing placeholder explanations were corrected in Free overlays; the new resolution POST handler now sanitizes typed tokens and independently validates the feature/mode/fingerprint, capability, owner and feature nonce. The remaining imported translator comment is T below.

A true output-boundary issue was found while tracing exception findings: Classic cart AJAX returned arbitrary exception details into clients using an HTML sink. The scanned candidate returns an escaped fixed message instead. The native modern regression throws markup plus private text from Woo totals and requires the fixed response, no exposed detail, no funded value and safe removal/reapply of the unfunded selection. The shared imported engine remains byte-bound to upstream.

| Code | Assessment and concrete boundary |
| --- | --- |
| Q | Prepared SQL construction not fully understood by static analysis. Values use `%s/%d`, numeric LIMIT/cursors are bounded casts, table names come from validated WordPress prefixes/fixed Loyalty suffixes or Woo's native storage API, and columns/DDL/index definitions come from literal finite maps/ternaries. The two replacement-count reports pass an argument array (`array_values($data)` or the conditionally appended `$values`), with matching executed placeholders. `Free_Readers::query()` /`Free_Onboarding::exists()` accept internally composed queries, never request SQL. Reader population clauses use prepared and escaped role strings. There is no identifier/SQL text from POST/GET/cookie. Native schema, malformed-value, SQL-failure, historical preservation and consumer-limit fixtures exercise these paths. |
| D | Deliberate direct database operations for live lock/witness/schema/raw duplicate-sensitive evidence and consistent accounting snapshots. A cached options/usermeta projection cannot replace raw rows at these concurrency boundaries. Schema changes are additive literal columns/indexes in the pinned helper; no destructive repair is added. Capability scans and store/order discovery fail closed on errors, ambiguity or safe limits. Each query/write's concrete source is listed below. |
| M | The pinned transaction layer and Free migration owner operate on the exact mysqli connection holding its named/row locks and transaction, checks the original thread/table/lock holder around writes, checks affected rows and transaction ownership, inspects error numbers/results/insert IDs and frees results. Nested native services reuse that verified owner without reacquiring or releasing its lock. Actual connection-KILL and outer-transaction fixtures prove rollback/no replacement-connection witness and preservation of caller transaction ownership. Substituting WordPress reconnecting/cached convenience calls would change ownership/failure semantics. This is an accepted native MySQL protocol requirement, not permission for arbitrary raw SQL; Q applies to every supplied query. |
| E | Exceptions are protocol/control flow, not an HTML output site. `points-lock` emits a fixed database code/integer error number; `referral-rewards` combines bounded result enums/codes; `free-blocks` returns fixed RouteException text through Woo REST/React; `free-cart` throws fixed translated text. Native Woo checkout notices render through `wc_kses_notice`; Free admin diagnostics use `esc_html`. The Classic AJAX raw exception boundary is corrected as described above; its new native regression is required. Exceptions are not blanket escaped at throw time, which would corrupt machine codes or double-escape downstream output. |
| N | Actual mutation owners check capability/nonce/ownership: Tools dispatch delegates to `start_new_import()` nonce then `import_locked()` capability and exact current UUID; `import_csv()` checks the import nonce and user-owned identity. Checkout UUID reads are hints only; `prepare()` calls `request_owner()` /`assert_identity()`, binds user/session/immutable funded terms and serialized admission; `recover_checkout()` binds stored user/session and native Woo checkout validation. Public referral GET only selects a strictly validated12-character token/cookie; it awards no value. Dashboard GET only chooses the literal30-day reader view. Activation request hints can deny Free ownership, never grant a mutation. Native denied nonce/capability, forged ownership and replay tests remain required. |
| V | The flagged values are independently validated before use: digit/range grammar for whole points and user IDs; UUID grammar plus current per-user intent for import/manual actions;12-character referral token validation; finite component kind allowlist and current-user-only identity; nonce verification on typed strings; literal request-method comparison. Inputs are rejected instead of normalizing malformed economic terms into another request. Nonces are typed/sanitized or checked through the native nonce verifier; unexpected quote/backslash values cannot pass the numeric/UUID/token grammar or equal a valid nonce. The new resolution handler additionally follows standard typed sanitation. These are specific validation paths, not a global sanitizer exemption. |
| P | The `query` filter is WordPress's existing SQL-observer contract, retained around connection-bound writes; `woocommerce_*` hooks are native Woo contracts; `yowcl_*`, `yoswc_*` and `loyf_*` preserve admitted public identities. Email template variables are local to Woo `wc_get_template()` inclusion, not globals registered by the plugin; their names match the email's template arguments. Renaming imported hook/argument contracts to satisfy heuristic prefixes would break compatibility. |
| T | One real, non-security translation-style omission at pinned `referral-rewards.php:273`. The placeholders and order number are used correctly; runtime English behavior is verified. It is non-blocking P3, retained because the admitted29 imports must remain exactly bound to read-only upstream. No missing translations/POT terms or unsafe output is claimed away. |
| F | `fclose()` closes the previously opened `php://output` CSV response stream in `finally`; it does not access/delete a server file. WordPress filesystem abstraction cannot replace a response stream. Private response headers, capability/nonce and the final completion record are tested natively. |
| B | First Purchase excludes the current order through Woo's native `wc_get_orders()` API, with bounded paginated history, stable cutoff, cache disabled and failed/partial reads held. The exclusion is necessary to prove an earlier qualifying order; it is not an unbounded caller-controlled query. CPT/HPOS history/failure tests cover it. |

This table records implementation-owned source assessment. Independent Technical Review and final WordPress.org review may disagree; no marketplace acceptance is inferred from it.

## Complete finding inventory at the scan SHA

Repeated findings on the same file/line/code are counted in the last column. Counts sum to319; every returned finding is represented.

| Source at scan SHA | Line | PCP code | Disposition | Count |
| --- | ---: | --- | --- | ---: |
| `inc/backend/actions/helper/referrals.php` | 83 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/backend/actions/helper/referrals.php` | 83 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/backend/actions/helper/referrals.php` | 103 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/backend/actions/helper/referrals.php` | 103 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/backend/actions/helper/referrals.php` | 112 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/backend/actions/helper/referrals.php` | 112 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/backend/actions/helper/referrals.php` | 127 | `WordPress.Security.NonceVerification.Recommended` | N | 2 |
| `inc/backend/actions/helper/referrals.php` | 129 | `WordPress.Security.NonceVerification.Recommended` | N | 1 |
| `inc/backend/actions/helper/referrals.php` | 129 | `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` | V | 1 |
| `inc/backend/actions/helper/referrals.php` | 140 | `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` | V | 1 |
| `inc/backend/settings/extra-points.php` | 174 | `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` | V | 1 |
| `inc/backend/settings/extra-points.php` | 174 | `WordPress.Security.ValidatedSanitizedInput.MissingUnslash` | V | 1 |
| `inc/backend/settings/extra-points.php` | 179 | `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` | V | 1 |
| `inc/backend/settings/extra-points.php` | 179 | `WordPress.Security.ValidatedSanitizedInput.MissingUnslash` | V | 1 |
| `inc/backend/settings/extra-points.php` | 186 | `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` | V | 1 |
| `inc/backend/settings/extra-points.php` | 186 | `WordPress.Security.ValidatedSanitizedInput.MissingUnslash` | V | 1 |
| `inc/backend/settings/referrals.php` | 20 | `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` | V | 1 |
| `inc/backend/settings/referrals.php` | 21 | `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` | V | 1 |
| `inc/backend/settings/referrals.php` | 21 | `WordPress.Security.ValidatedSanitizedInput.MissingUnslash` | V | 1 |
| `inc/backend/settings/tools.php` | 17 | `WordPress.Security.NonceVerification.Missing` | N | 1 |
| `inc/backend/settings/tools.php` | 134 | `WordPress.Security.NonceVerification.Missing` | N | 1 |
| `inc/backend/settings/tools.php` | 134 | `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` | V | 1 |
| `inc/backend/settings/tools.php` | 134 | `WordPress.Security.ValidatedSanitizedInput.MissingUnslash` | V | 1 |
| `inc/backend/settings/tools.php` | 182 | `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` | V | 1 |
| `inc/backend/settings/tools.php` | 182 | `WordPress.Security.ValidatedSanitizedInput.MissingUnslash` | V | 1 |
| `inc/cores/database.php` | 52 | `WordPress.DB.PreparedSQL.NotPrepared` | Q | 2 |
| `inc/cores/database.php` | 54 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Q | 1 |
| `inc/cores/database.php` | 54 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/database.php` | 54 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/database.php` | 54 | `WordPress.DB.PreparedSQL.NotPrepared` | Q | 1 |
| `inc/cores/helper/advanced-rewards.php` | 101 | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | Q | 2 |
| `inc/cores/helper/advanced-rewards.php` | 105 | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | Q | 2 |
| `inc/cores/helper/database.php` | 36 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/database.php` | 36 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/helper/database.php` | 41 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/database.php` | 41 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/helper/database.php` | 41 | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | Q | 1 |
| `inc/cores/helper/database.php` | 72 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/database.php` | 72 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/helper/database.php` | 72 | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | Q | 1 |
| `inc/cores/helper/database.php` | 108 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/database.php` | 108 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/helper/database.php` | 110 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/database.php` | 110 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/helper/database.php` | 110 | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | Q | 1 |
| `inc/cores/helper/database.php` | 112 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Q | 1 |
| `inc/cores/helper/database.php` | 112 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/database.php` | 112 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/helper/database.php` | 112 | `WordPress.DB.DirectDatabaseQuery.SchemaChange` | D | 1 |
| `inc/cores/helper/database.php` | 112 | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | Q | 3 |
| `inc/cores/helper/database.php` | 114 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/database.php` | 114 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/helper/database.php` | 114 | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | Q | 1 |
| `inc/cores/helper/database.php` | 116 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Q | 1 |
| `inc/cores/helper/database.php` | 116 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/database.php` | 116 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/helper/database.php` | 116 | `WordPress.DB.DirectDatabaseQuery.SchemaChange` | D | 1 |
| `inc/cores/helper/database.php` | 116 | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | Q | 2 |
| `inc/cores/helper/free-admin.php` | 7 | `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` | V | 1 |
| `inc/cores/helper/free-admin.php` | 7 | `WordPress.Security.ValidatedSanitizedInput.MissingUnslash` | V | 1 |
| `inc/cores/helper/free-admin.php` | 10 | `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` | V | 1 |
| `inc/cores/helper/free-admin.php` | 10 | `WordPress.Security.ValidatedSanitizedInput.MissingUnslash` | V | 1 |
| `inc/cores/helper/free-admin.php` | 11 | `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` | V | 1 |
| `inc/cores/helper/free-admin.php` | 11 | `WordPress.Security.ValidatedSanitizedInput.MissingUnslash` | V | 1 |
| `inc/cores/helper/free-blocks.php` | 38 | `WordPress.Security.EscapeOutput.ExceptionNotEscaped` | E | 1 |
| `inc/cores/helper/free-blocks.php` | 47 | `WordPress.Security.EscapeOutput.ExceptionNotEscaped` | E | 1 |
| `inc/cores/helper/free-cart.php` | 9 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_fetch_row` | M | 1 |
| `inc/cores/helper/free-cart.php` | 9 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_free_result` | M | 1 |
| `inc/cores/helper/free-cart.php` | 36 | `WordPress.Security.EscapeOutput.ExceptionNotEscaped` | E | 1 |
| `inc/cores/helper/free-cart.php` | 37 | `WordPress.Security.EscapeOutput.ExceptionNotEscaped` | E | 1 |
| `inc/cores/helper/free-cart.php` | 47 | `WordPress.Security.EscapeOutput.ExceptionNotEscaped` | E | 1 |
| `inc/cores/helper/free-cart.php` | 51 | `WordPress.Security.EscapeOutput.ExceptionNotEscaped` | E | 1 |
| `inc/cores/helper/free-core.php` | 8 | `WordPress.Security.NonceVerification.Recommended` | N | 3 |
| `inc/cores/helper/free-core.php` | 8 | `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` | V | 1 |
| `inc/cores/helper/free-core.php` | 8 | `WordPress.Security.ValidatedSanitizedInput.MissingUnslash` | V | 1 |
| `inc/cores/helper/free-core.php` | 11 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/free-core.php` | 11 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/helper/free-core.php` | 17 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/free-core.php` | 17 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/helper/free-core.php` | 28 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/free-core.php` | 28 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/helper/free-core.php` | 30 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/free-core.php` | 30 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/helper/free-core.php` | 50 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound` | P | 1 |
| `inc/cores/helper/free-customer-components.php` | 34 | `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` | V | 1 |
| `inc/cores/helper/free-customer-components.php` | 34 | `WordPress.Security.ValidatedSanitizedInput.MissingUnslash` | V | 1 |
| `inc/cores/helper/free-customer-components.php` | 39 | `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` | V | 1 |
| `inc/cores/helper/free-customer-components.php` | 39 | `WordPress.Security.ValidatedSanitizedInput.MissingUnslash` | V | 1 |
| `inc/cores/helper/free-first-purchase.php` | 91 | `WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude` | B | 1 |
| `inc/cores/helper/free-migrations.php` | 11 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/free-migrations.php` | 11 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/helper/free-migrations.php` | 38 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_thread_id` | M | 1 |
| `inc/cores/helper/free-migrations.php` | 39 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_query` | M | 1 |
| `inc/cores/helper/free-migrations.php` | 41 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_fetch_row` | M | 1 |
| `inc/cores/helper/free-migrations.php` | 41 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_free_result` | M | 1 |
| `inc/cores/helper/free-migrations.php` | 48 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound` | P | 1 |
| `inc/cores/helper/free-migrations.php` | 50 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_query` | M | 1 |
| `inc/cores/helper/free-migrations.php` | 62 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_affected_rows` | M | 1 |
| `inc/cores/helper/free-migrations.php` | 76 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/free-migrations.php` | 76 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/helper/free-migrations.php` | 80 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_thread_id` | M | 1 |
| `inc/cores/helper/free-migrations.php` | 85 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_free_result` | M | 1 |
| `inc/cores/helper/free-migrations.php` | 85 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_query` | M | 1 |
| `inc/cores/helper/free-migrations.php` | 143 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/free-migrations.php` | 143 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/helper/free-migrations.php` | 208 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/free-migrations.php` | 208 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/helper/free-migrations.php` | 216 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_free_result` | M | 1 |
| `inc/cores/helper/free-migrations.php` | 222 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_query` | M | 1 |
| `inc/cores/helper/free-onboarding.php` | 13 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/free-onboarding.php` | 13 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/helper/free-onboarding.php` | 24 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Q | 1 |
| `inc/cores/helper/free-onboarding.php` | 24 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/free-onboarding.php` | 24 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/helper/free-onboarding.php` | 24 | `WordPress.DB.PreparedSQL.NotPrepared` | Q | 1 |
| `inc/cores/helper/free-onboarding.php` | 34 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/free-onboarding.php` | 34 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/helper/free-onboarding.php` | 53 | `WordPress.DB.SlowDBQuery.slow_db_query_meta_key` | D | 1 |
| `inc/cores/helper/free-onboarding.php` | 60 | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | Q | 1 |
| `inc/cores/helper/free-onboarding.php` | 61 | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | Q | 1 |
| `inc/cores/helper/free-onboarding.php` | 65 | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | Q | 1 |
| `inc/cores/helper/free-onboarding.php` | 76 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/free-onboarding.php` | 76 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/helper/free-onboarding.php` | 106 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/free-onboarding.php` | 106 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/helper/free-onboarding.php` | 106 | `WordPress.DB.PreparedSQL.NotPrepared` | Q | 4 |
| `inc/cores/helper/free-onboarding.php` | 138 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/free-onboarding.php` | 138 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/helper/free-onboarding.php` | 280 | `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` | V | 2 |
| `inc/cores/helper/free-onboarding.php` | 280 | `WordPress.Security.ValidatedSanitizedInput.MissingUnslash` | V | 1 |
| `inc/cores/helper/free-reader-admin.php` | 12 | `WordPress.Security.NonceVerification.Recommended` | N | 3 |
| `inc/cores/helper/free-reader-admin.php` | 86 | `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` | V | 2 |
| `inc/cores/helper/free-reader-admin.php` | 86 | `WordPress.Security.ValidatedSanitizedInput.MissingUnslash` | V | 1 |
| `inc/cores/helper/free-reader-admin.php` | 92 | `WordPress.WP.AlternativeFunctions.file_system_operations_fclose` | F | 1 |
| `inc/cores/helper/free-readers.php` | 31 | `PluginCheck.Security.DirectDB.UnescapedDBParameter` | Q | 1 |
| `inc/cores/helper/free-readers.php` | 31 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/free-readers.php` | 31 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/helper/free-readers.php` | 31 | `WordPress.DB.PreparedSQL.NotPrepared` | Q | 1 |
| `inc/cores/helper/free-readers.php` | 77 | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | Q | 3 |
| `inc/cores/helper/free-referral.php` | 8 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/free-referral.php` | 8 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/helper/free-referral.php` | 47 | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | Q | 2 |
| `inc/cores/helper/free-referral.php` | 98 | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | Q | 2 |
| `inc/cores/helper/ledger-v2.php` | 64 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/ledger-v2.php` | 64 | `WordPress.DB.DirectDatabaseQuery.NoCaching` | D | 1 |
| `inc/cores/helper/ledger-v2.php` | 64 | `WordPress.DB.PreparedSQL.NotPrepared` | Q | 2 |
| `inc/cores/helper/order-redemption.php` | 205 | `WordPress.Security.NonceVerification.Missing` | N | 3 |
| `inc/cores/helper/order-redemption.php` | 218 | `WordPress.Security.EscapeOutput.ExceptionNotEscaped` | E | 1 |
| `inc/cores/helper/order-redemption.php` | 223 | `WordPress.Security.EscapeOutput.ExceptionNotEscaped` | E | 1 |
| `inc/cores/helper/order-redemption.php` | 251 | `WordPress.Security.EscapeOutput.ExceptionNotEscaped` | E | 1 |
| `inc/cores/helper/order-redemption.php` | 278 | `WordPress.Security.EscapeOutput.ExceptionNotEscaped` | E | 1 |
| `inc/cores/helper/order-redemption.php` | 491 | `WordPress.DB.PreparedSQL.NotPrepared` | Q | 2 |
| `inc/cores/helper/order-redemption.php` | 492 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_fetch_assoc` | M | 1 |
| `inc/cores/helper/order-redemption.php` | 493 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_free_result` | M | 1 |
| `inc/cores/helper/order-redemption.php` | 548 | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | Q | 1 |
| `inc/cores/helper/order-redemption.php` | 589 | `WordPress.Security.NonceVerification.Missing` | N | 3 |
| `inc/cores/helper/order-redemption.php` | 593 | `WordPress.Security.EscapeOutput.ExceptionNotEscaped` | E | 1 |
| `inc/cores/helper/order-redemption.php` | 615 | `WordPress.Security.EscapeOutput.ExceptionNotEscaped` | E | 1 |
| `inc/cores/helper/order-redemption.php` | 629 | `WordPress.Security.EscapeOutput.ExceptionNotEscaped` | E | 1 |
| `inc/cores/helper/order-redemption.php` | 727 | `WordPress.Security.EscapeOutput.ExceptionNotEscaped` | E | 1 |
| `inc/cores/helper/order-redemption.php` | 761 | `WordPress.Security.EscapeOutput.ExceptionNotEscaped` | E | 1 |
| `inc/cores/helper/order-redemption.php` | 782 | `WordPress.Security.EscapeOutput.ExceptionNotEscaped` | E | 1 |
| `inc/cores/helper/order-rewards.php` | 169 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_fetch_row` | M | 1 |
| `inc/cores/helper/order-rewards.php` | 170 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_free_result` | M | 1 |
| `inc/cores/helper/order-rewards.php` | 183 | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | Q | 1 |
| `inc/cores/helper/order-rewards.php` | 183 | `WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber` | Q | 1 |
| `inc/cores/helper/order-rewards.php` | 184 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_insert_id` | M | 1 |
| `inc/cores/helper/points-events.php` | 9 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound` | P | 1 |
| `inc/cores/helper/points-events.php` | 15 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound` | P | 1 |
| `inc/cores/helper/points-events.php` | 21 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound` | P | 1 |
| `inc/cores/helper/points-events.php` | 28 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound` | P | 1 |
| `inc/cores/helper/points-lock.php` | 64 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_query` | M | 1 |
| `inc/cores/helper/points-lock.php` | 65 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_errno` | M | 1 |
| `inc/cores/helper/points-lock.php` | 91 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_query` | M | 1 |
| `inc/cores/helper/points-lock.php` | 93 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_errno` | M | 1 |
| `inc/cores/helper/points-lock.php` | 93 | `WordPress.Security.EscapeOutput.ExceptionNotEscaped` | E | 1 |
| `inc/cores/helper/points-lock.php` | 100 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_fetch_row` | M | 1 |
| `inc/cores/helper/points-lock.php` | 101 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_free_result` | M | 1 |
| `inc/cores/helper/points-log.php` | 17 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/points-log.php` | 53 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound` | P | 1 |
| `inc/cores/helper/points-transaction.php` | 205 | `WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber` | Q | 1 |
| `inc/cores/helper/points-transaction.php` | 206 | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | Q | 1 |
| `inc/cores/helper/points-transaction.php` | 206 | `WordPress.DB.PreparedSQL.NotPrepared` | Q | 10 |
| `inc/cores/helper/points-transaction.php` | 208 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_insert_id` | M | 1 |
| `inc/cores/helper/points-transaction.php` | 273 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_fetch_row` | M | 1 |
| `inc/cores/helper/points-transaction.php` | 274 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_free_result` | M | 1 |
| `inc/cores/helper/points-transaction.php` | 280 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_fetch_assoc` | M | 1 |
| `inc/cores/helper/points-transaction.php` | 281 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_num_rows` | M | 1 |
| `inc/cores/helper/points-transaction.php` | 282 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_free_result` | M | 1 |
| `inc/cores/helper/points-transaction.php` | 284 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_fetch_assoc` | M | 1 |
| `inc/cores/helper/points-transaction.php` | 285 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_num_rows` | M | 1 |
| `inc/cores/helper/points-transaction.php` | 286 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_free_result` | M | 1 |
| `inc/cores/helper/points-transaction.php` | 290 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_fetch_assoc` | M | 1 |
| `inc/cores/helper/points-transaction.php` | 294 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_free_result` | M | 1 |
| `inc/cores/helper/points-transaction.php` | 303 | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | Q | 1 |
| `inc/cores/helper/points-transaction.php` | 304 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_fetch_assoc` | M | 1 |
| `inc/cores/helper/points-transaction.php` | 305 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_free_result` | M | 1 |
| `inc/cores/helper/points-transaction.php` | 314 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_fetch_assoc` | M | 1 |
| `inc/cores/helper/points-transaction.php` | 317 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_free_result` | M | 1 |
| `inc/cores/helper/points-transaction.php` | 323 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_free_result` | M | 1 |
| `inc/cores/helper/referral-rewards.php` | 88 | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | Q | 2 |
| `inc/cores/helper/referral-rewards.php` | 88 | `WordPress.DB.PreparedSQL.NotPrepared` | Q | 3 |
| `inc/cores/helper/referral-rewards.php` | 109 | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | Q | 2 |
| `inc/cores/helper/referral-rewards.php` | 110 | `WordPress.DB.PreparedSQL.InterpolatedNotPrepared` | Q | 3 |
| `inc/cores/helper/referral-rewards.php` | 119 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_fetch_assoc` | M | 1 |
| `inc/cores/helper/referral-rewards.php` | 121 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_free_result` | M | 1 |
| `inc/cores/helper/referral-rewards.php` | 133 | `WordPress.DB.RestrictedFunctions.mysql_mysqli_free_result` | M | 1 |
| `inc/cores/helper/referral-rewards.php` | 250 | `WordPress.Security.EscapeOutput.ExceptionNotEscaped` | E | 2 |
| `inc/cores/helper/referral-rewards.php` | 273 | `WordPress.WP.I18n.MissingTranslatorsComment` | T | 1 |
| `inc/cores/helper/role-ownership.php` | 67 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/cores/helper/role-ownership.php` | 74 | `WordPress.DB.DirectDatabaseQuery.DirectQuery` | D | 1 |
| `inc/frontend/cart-checkout.php` | 213 | `WordPress.Security.ValidatedSanitizedInput.InputNotSanitized` | V | 1 |
| `inc/frontend/cart-checkout.php` | 213 | `WordPress.Security.ValidatedSanitizedInput.MissingUnslash` | V | 1 |
| `templates/emails/loyalty-level-update.php` | 12 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-level-update.php` | 13 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-level-update.php` | 14 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-level-update.php` | 15 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-level-update.php` | 16 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-level-update.php` | 17 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-level-update.php` | 18 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-level-update.php` | 19 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-level-update.php` | 20 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-level-update.php` | 21 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-level-update.php` | 22 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-level-update.php` | 23 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-level-update.php` | 24 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-level-update.php` | 25 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-level-update.php` | 27 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound` | P | 1 |
| `templates/emails/loyalty-level-update.php` | 140 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound` | P | 1 |
| `templates/emails/loyalty-points-deduct.php` | 12 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-deduct.php` | 13 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-deduct.php` | 14 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-deduct.php` | 15 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-deduct.php` | 16 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-deduct.php` | 17 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-deduct.php` | 18 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-deduct.php` | 19 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-deduct.php` | 20 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-deduct.php` | 21 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-deduct.php` | 22 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-deduct.php` | 23 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-deduct.php` | 24 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-deduct.php` | 26 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound` | P | 1 |
| `templates/emails/loyalty-points-deduct.php` | 157 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound` | P | 1 |
| `templates/emails/loyalty-points-reward.php` | 12 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-reward.php` | 13 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-reward.php` | 14 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-reward.php` | 15 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-reward.php` | 16 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-reward.php` | 17 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-reward.php` | 18 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-reward.php` | 19 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-reward.php` | 20 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-reward.php` | 21 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-reward.php` | 22 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-reward.php` | 23 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-reward.php` | 24 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/loyalty-points-reward.php` | 26 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound` | P | 1 |
| `templates/emails/loyalty-points-reward.php` | 157 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound` | P | 1 |
| `templates/emails/plain/loyalty-level-update.php` | 12 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/plain/loyalty-level-update.php` | 13 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/plain/loyalty-level-update.php` | 14 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/plain/loyalty-level-update.php` | 15 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/plain/loyalty-level-update.php` | 16 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/plain/loyalty-level-update.php` | 65 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound` | P | 1 |
| `templates/emails/plain/loyalty-points-deduct.php` | 12 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/plain/loyalty-points-deduct.php` | 13 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/plain/loyalty-points-deduct.php` | 14 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/plain/loyalty-points-deduct.php` | 15 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/plain/loyalty-points-deduct.php` | 16 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/plain/loyalty-points-deduct.php` | 70 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound` | P | 1 |
| `templates/emails/plain/loyalty-points-reward.php` | 12 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/plain/loyalty-points-reward.php` | 13 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/plain/loyalty-points-reward.php` | 14 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/plain/loyalty-points-reward.php` | 15 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/plain/loyalty-points-reward.php` | 16 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound` | P | 1 |
| `templates/emails/plain/loyalty-points-reward.php` | 70 | `WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound` | P | 1 |

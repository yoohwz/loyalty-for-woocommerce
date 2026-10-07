# Free testing evidence

Governance is inherited via [LOYF-1](https://github.com/yoohwz/loyalty-for-woocommerce/issues/1) and `AGENTS.md`; this file describes Free evidence implementation only. Fresh-read current upstream `TESTING.md` at each canonical command. Use its EXECUTED / HISTORICAL / INFERRED / UNAVAILABLE evidence vocabulary and exact-head gate semantics.

## Local commands

- `git ls-files -z '*.php' | xargs -0 -n1 php -l` (minimum PHP 7.4 in hosted assurance).
- `git ls-files -z '*.js' | xargs -0 -n1 node --check`.
- `PYTHONDONTWRITEBYTECODE=1 python3 tests/ci/policy-test.py`.
- `PYTHONDONTWRITEBYTECODE=1 python3 tests/ci/provenance-test.py`.
- `PYTHONDONTWRITEBYTECODE=1 python3 tests/ci/runtime-test.py`.
- `git diff --check <base> <head>`.

CI policy/provenance tests use real Git fixtures and synthetic Checks/Actions responses. Runtime harness tests use external-tool doubles to prove safety, dispatch and cleanup, not WP/Woo behavior.

## Native disposable characterization

From a clean checkout outside `/Local Sites/`, set `LOY_RUNTIME_DISPOSABLE=1`, `LOY_DB_HOST`, `LOY_DB_USER`, `LOY_DB_PASSWORD`, optionally `LOY_DB_PORT` (default 3306), then run `bash tests/runtime/run.sh`. Supply a private disposable MySQL server with CREATE/DROP permission; the runner does not provision or select an installed site's database. Hosted CI supplies MySQL 8.0 and PHP 8.2. WordPress 6.8.3, WooCommerce 9.9.5 and WP-CLI 2.12.0 match the referenced upstream runtime pins.

The runner archives exact committed product files twice: Free `3c1aac240df03101561b856cddb603b1501fa41b` and current candidate HEAD, into independently provisioned temporary sites/databases. Test-only MU isolation blocks outbound WordPress HTTP/mail. Both use `tests/fixtures/free-1.2.2.json`. The historical baseline executes `tests/runtime/characterization.php` and must equal the immutable `tests/fixtures/free-1.2.2-expected.json`. The candidate separately executes `pre-cutover.php` before activation and `hardened.php` after activation, with explicit new hardening expectations. Candidate and legacy results intentionally differ for duplicate rewards and debit timing. The golden JSON was captured by executing the original Free SHA; candidate code never generates its own expectation. Every database/tree is removed on success/failure, and cleanup failure fails the run.

`LOYF_RUNTIME_ARTIFACTS=/absolute/external/directory` optionally retains baseline/candidate raw and normalized JSON for later transition tests. Raw exports preserve concrete user/order/comment IDs, user meta, history IDs/actions/dates, markers and public event arguments; normalized comparison replaces only runtime IDs/date fields. No customer data is read. Artifact output is outside the checkout and never part of the distribution.

Coverage: signup/replay; daily login once/day and later-day admission; approved versus unapproved review/replay; purchase processing→completed→cancel/refund/failure and replay/recompletion; level transitions/re-entry; native session/cart fee and total; registered apply/remove AJAX nonce/amount denial; Classic checkout creation→thankyou debit/replay→return/replay; both balances; history rows/IDs and schema rerun; persisted option shapes; My Account slug/menu/history reader; legacy email settings, recipient/subject/custom URL; `yoswc_loyalty_*` actions and filter pass-through.

This freezes current behavior, including legacy replay defects, not future points invariants. No migration, Premium implementation, HPOS/Blocks certification, concurrent/partial-storage-failure proof, browser layout, external payment/provider or real mailbox delivery is claimed. Direct event dispatch uses native registered callbacks; Classic order construction uses `WC_Checkout::create_order`, not a browser POST. Review uses native `comment_post` dispatch after a real comment insert, not an HTTP comment submission.

## Hosted certification and package

`LOYF Required CI` is the Free aggregate corresponding to upstream `LOY Required CI`. Draft events skip all jobs before runner allocation; separate Draft/Ready concurrency lanes prevent stale Draft cancellation. Accepted-base classifier/provenance controls modes. First adoption is FULL. FULL requires assurance, stage/archive/extract byte-inventory verification and the complete native characterization runtime; LIGHTWEIGHT and POST_MERGE skips are permitted only by the inherited policy. Missing/failing evidence fails closed.

`bash scripts/stage-distribution.sh <exact-sha> <empty-absolute-external-dir>` stages only committed allowlisted product files. `python3 scripts/verify-distribution.py --source <checkout> --sha <sha> --version 1.2.2 --stage <dir> --zip <archive> --extracted <dir>` verifies clean exact source, required files, version and identical inventory/bytes across source, stage, archive and extraction, rejecting unsafe paths/symlinks. Temporary verification archives grant no release/publication authority.

## Exact upstream import contract

Read `docs/FREE-IMPORT-CONTRACT.md`. Run `PYTHONDONTWRITEBYTECODE=1 python3 tests/ci/import-contract-test.py` for real Git/filesystem fixtures covering exact origin/SHA/tree, drift reporting, tokenizer domain spans, forbidden/renamed/obfuscated source, overlay identity/modes, path collision/traversal, symlink refusal and deterministic external staging. Fixtures contain synthetic code; they do not publish or substitute for inspecting private upstream source.

`PYTHONDONTWRITEBYTECODE=1 python3 scripts/free-import.py verify-source --root "$PWD"` is part of assurance, staging and package verification. Current repo/package certification includes all 15 imports plus 53 overlays in `core-runtime` mode (the CLI historical `legacy` spelling cannot omit imports). Actual upstream inspection/staging requires an authenticated read-only bare snapshot at the manifest's exact SHA; CI receives no Premium credentials.

`tests/ci/extraction-test.py` uses synthetic bytes to verify source/hash binding, invalid spans, ordering, types, base64 and selected-output integrity. `node tests/js/admin-replay.cjs` executes both shipped manual-operation scripts with controlled DOM/network/storage doubles: invalid input creates no intent, a lost response retains its UUID, changed terms are denied, and a late callback cannot clear a newer operation. Browser layout and live browser AJAX delivery remain unexecuted.

Candidate native assertions cover additive legacy-row preservation, historical reward suppression, new exactly-once rewards, first legitimate level bonus, order reversal, actual Classic cart AJAX/order creation with pre-gateway debit, canonical return, legacy manual review, exact fractional preservation, rollback at three native transaction boundaries, observer failure, privileged AJAX authorization/replay/conflict, actual private CSV writer partial failure/replay through repeated native form rendering with durable operation identity, retained Action Scheduler retry and independent WP-CLI debit concurrency. Cutover boundary assertions additionally cover stale ownership cache, unrelated roles, new-role provenance/owned retirement, native duplicate fractional historical cancellation, guest/missing-user reader warnings, dormant product-redemption preservation and the unsupported Store API safety guard. CSV invokes the actual writer with native nonce/upload checks but does not emulate HTTP multipart transport. AS replay delivers a retained native action through its registered hook; cron dispatch timing is not certified.

These are scoped LOYF-5 prerequisites and native evidence, not complete old-Free migration or full gateway/Blocks/HPOS certification. LOYF-6 owns semantic compatibility migrations and fractional resolution; LOYF-7 broader economic certification; LOYF-8 Blocks/HPOS. Late asynchronous gateway callback/reconnect fault injection and browser transport remain material unexecuted coverage. FULL hosted exact-head CI and fresh independent Technical Review remain required before Acceptance.

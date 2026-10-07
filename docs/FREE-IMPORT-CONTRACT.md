# Exact-SHA Free downstream contract

Authority: LOYF-1 and the current read-only canonical LOY workflow. Scope: LOYF-4, parent LOYF-2; this is a source provenance/package contract, not another governance workflow.

Pinned upstream: `yoohwz/wc-loyalty@9e3d663b9828a24eacd141216d5e6f6e22da8ca4`.
Free baseline: `yoohwz/loyalty-for-woocommerce@bc9b63c1c2d3cf5e73b42e4bfa51d7681ecb97d9`.
The executable contract is `config/free-import-manifest.json`. It inventories every upstream tracked entry by Git blob identity and mode, with exactly one decision: `import`, `reference-only`, `forbidden`, or `excluded`. Missing/new entries never inherit approval from a directory glob.

## Current phase and import allowlist

This phase is `contract-only`. No canonical file is copied into the Free repository or activated. The five admitted whole-file shared imports are:

| Upstream and target path | Purpose |
| --- | --- |
| `inc/cores/helper/points-lock.php` | Native connection ownership and serialized user mutation primitive |
| `inc/cores/helper/points-log.php` | Canonical existing log table and post-write observations |
| `inc/cores/helper/points-log-cache.php` | Canonical per-user log cache version |
| `inc/cores/helper/role-claims.php` | Shared conservative claim protocol, original `YOSWC_Role_Claims` identity |
| `inc/backend/actions/helper/roles.php` | Canonical loyalty level getter/claim projection with companion preservation |

Each entry pins input SHA-256, exact source/target, transformation ID, replacement count and output SHA-256. These five sources need no text-domain edits at this SHA; all counts are zero and all other bytes remain canonical. They are definitions, not an operational Free reward engine or a complete dependency closure for vNext.

`stage` creates an **inert external projection**: all 50 unchanged legacy product overlay files plus these five definitions, 55 files. It does not wire a loader, replace business implementation, mutate options/schema, or activate anything. This projection is not the shipping candidate. Normal repository/package certification remains locked to the 50-file legacy overlay for LOYF-4.

This deliberately refuses a whole-folder import. `points-transaction.php` contains expiration/reset/reconcile mutations; `core-rewards.php` includes licensing/expiration; `order-redemption.php` includes product redemption; `points-events.php` includes expiration and advanced notifications. These and other mixed dependencies are recorded in `blocked_mixed_modules`, classified `reference-only`, and cannot be selected as whole-file imports. LOYF-5 must audit/extract the admitted core, expand explicit entries and hashes, and provide deterministic transformations before adding them. LOYF-9/10 similarly need bounded first-purchase/referral kernels, not whole advanced engines. This is a settled implementation boundary within #2/#4, not an authorization to delete dormant Premium data.

`forbidden` records concrete Premium-only licensing/updater, campaigns, expiration, advanced referral/rewards, coupon/free-shipping conversion, redeem products, product/category earning and level discount/reset files. Other product files are reference-only and never implicitly importable; non-product CI/docs/tests/distribution artifacts are excluded. Reconciliation repair embedded in Tools/transaction/occurrences remains blocked. New/renamed files require inspection through `drift`, even if the name looks like a shared helper.

## Overlay and compatibility policy

The 50 `overlays` enumerate current Free product files and bytes, including bootstrap/metadata, legacy runtime adapters, settings, frontend assets, locale template and WordPress.org documents. They are a frozen temporary overlay at this phase; they are not a decision to retain two reward engines indefinitely. Later admitted implementation tasks replace/shrink this list explicitly. Unknown or changed source/package files fail verification even if a Premium class has been renamed or obfuscated.

- Preserve folder/slug/main file/text domain `loyalty-for-woocommerce`, WordPress.org Plugin URI, plugin name, Requires Plugins/WordPress/PHP metadata, license and version. Hash-pinned identity files additionally protect all metadata not individually parsed. This task grants no version/release authority.
- Preserve `YOSWC_LOYALTY_VERSION`, `YOSWC_LOYALTY_PLUGIN_FILE`, `YOSWC_LOYALTY_PLUGIN_DIR` and `YOSWC_LOYALTY_PLUGIN_BASENAME` as Free identities, resolving to the Free installation. Do not replace their values with Premium paths or an upstream version.
- Keep canonical internal `YOWCL_*` symbols, stored `yowcl_*` identities and the shared `YOSWC_Role_Claims` unchanged. Namespace-prefix replacement would fork transaction keys, locks, markers and companion ownership. Free bootstrap must exclude simultaneous active Premium loading before canonical includes; `class_exists` masking alone does not prove a single economic owner. Loading/transition certification belongs to LOYF-5/13.
- Existing legacy `YOSWC_Loyalty_*` classes remain untouched now. Future adapters must preserve each exposed constructor/method signature, return/error contract and registration behavior; blind `class_alias` is forbidden for incompatible APIs. In particular, `YOSWC_Loyalty_Database::insert_points_log/get_points_log` must remain a legacy-compatible reader/writer surface, not silently convert unidentified historical writes into canonical events. Balance/order/extra-reward/cart controllers require dedicated facades over the single canonical producer once migration is admitted.
- Future bridges for `yoswc_loyalty_points_reward` `(user_id, points, new_points, order_id)`, `yoswc_loyalty_points_deduct` with the same shape, and `yoswc_loyalty_level_update` `(user_id, level, earning)` are **one-way post-commit observations** from canonical channels. Emit once per effective transition; never connect both hook families as value producers or award on a bridge. Preserve `yoswc_loyalty_is_premium`, purchase/docs URL filters and existing frontend/UI hooks. LOYF-3 provides native observable fixtures; bridge implementation/certification is LOYF-6/7.
- Preserve `user_points`, `user_earning_points`, `yo_loyalty_points_log` rows/IDs, options, markers and current session keys until LOYF-6's admitted migrations. No synthetic historical event identity or silent reinterpretation of reward option shapes. Dormant Premium settings/history are not execution permission and must not be deleted merely because their features are excluded.

## Deterministic text-domain transformation

`wp-text-domain-v1` uses PHP's tokenizer and changes only the literal domain argument of supported global WordPress translation calls (`__`, `_e`, context/plural/noop and escaped variants). It preserves quote style, whitespace and every non-domain token, including a message containing `wc-loyalty`, stored identifiers, URLs, plugin/owner strings, comments and method/static calls of the same spelling. Source must parse; unknown transformation IDs, mismatched replacement counts/input/output hashes fail closed. Escaped/dynamic/qualified forms are not silently guessed; admit only reviewed sources whose pinned output proves the supported transform. Further syntax support requires focused tests and explicit contract changes. No blanket `wc-loyalty` or `YOWCL` text substitution is permitted.

WordPress.org translation compliance for a fully generated vNext distribution is part of LOYF-5/13, including POT regeneration and scan of all final runtime strings. LOYF-4 proves the deterministic transformation and current legacy package boundary; it does not claim unpublished adapters already satisfy that later certification.

## Commands and refresh

Use an authenticated **read-only bare Git snapshot** of upstream, outside both installed plugins. `git clone --bare https://github.com/yoohwz/wc-loyalty.git /absolute/disposable/upstream.git` is acquisition of reference objects, not a Premium working checkout or any remote write. The upstream is private: CI never receives cross-repository credentials or copies private product source into fixtures. CI validates the manifest schema, source/package hashes and synthetic real-Git adversarial cases; root records actual exact-upstream verification as implementation evidence.

```sh
python3 scripts/free-import.py inspect \
  --upstream /absolute/disposable/upstream.git \
  --sha 9e3d663b9828a24eacd141216d5e6f6e22da8ca4

python3 scripts/free-import.py drift \
  --upstream /absolute/disposable/upstream.git --sha <new-exact-40-character-sha>

python3 scripts/free-import.py verify-source --root <Free-checkout>

python3 scripts/free-import.py stage \
  --upstream /absolute/disposable/upstream.git \
  --sha 9e3d663b9828a24eacd141216d5e6f6e22da8ca4 \
  --source <clean-exact-Free-checkout> --head <exact-Free-sha> \
  --output <empty-absolute-external-directory>

python3 scripts/free-import.py verify-tree \
  --root <external-output>/loyalty-for-woocommerce --mode projection
```

`inspect` verifies repository origin, exact commit/tree, the complete upstream inventory and transformation outputs before reporting selections. Branch names/abbreviations and mismatched SHA/tree/content/mode are rejected. `drift` reports added/removed/modified paths, prior decision, old/new blobs and modes without writing source or updating the manifest. A new commit requires readmission even if its tree is unchanged; drift exits nonzero. New paths are `unclassified`. Symlink/submodule changes are reportable but never importable. Review the changed files and dependencies, then update SHA/inventory/allowlist/transform hashes under the existing task/PR/review process. There is no automatic apply, wildcard adoption or governance approval parser.

`stage` requires the bound Free repository origin, an exact HEAD descended from the admitted Free baseline, clean exact Free HEAD, unchanged overlay, verified upstream and deterministic conversion. All input proofs precede writes; output must be empty, absolute and outside source/upstream/tool checkout. Only the output tree is written. Repeating the same inputs yields identical file contents and executable modes; filesystem timestamps are not source identity. An interrupted filesystem write can leave a partial external tree, which must not be used until verification passes. Normal source staging and ZIP verification both invoke the legacy boundary guard before certification. OS metadata ignored in the source checkout is never accepted into a package.

## Lifecycle readiness and adversarial evidence

| Transition | Identity/authority | Failure/recovery invariant |
| --- | --- | --- |
| Resolve upstream → inspect | Exact repository/commit/tree and complete Git inventory | Wrong origin/ref/hash/mode denies planning/import |
| Refresh → drift report | New immutable SHA, old classification | Report all changes before readmission; no automatic source writes |
| Transform → pin output | Literal domain token spans, count, input/output SHA-256 | No identifier/data/global renaming; parser/count/hash mismatch denies |
| Overlay → external projection | Exact clean Free HEAD and frozen product identity | Collision, source drift or internal output path denies before writes |
| Repo → package | Explicit legacy inventory, hashes, symbols and executable modes | Renamed/obfuscated/unknown Premium source, symlink or metadata junk cannot certify |
| Later loader/facade → runtime | Single edition owner and preserved persisted identities | No two producer engines, hook loops or guessed historical transaction keys |

The first five rows are executable tooling/package evidence in LOYF-4. The last row is a declared compatibility policy for later admitted tasks, not implemented runtime behavior here. Root pre-review adversarial tests use real Git repositories and paths to cover unknown/new upstream files, moved/identical-tree commits, symlinks, transformation drift, duplicated/colliding/traversing manifest paths, renamed licensing, changed overlay bytes/modes, internal output refusal, and deterministic external staging. Hosted FULL continues to execute the unchanged LOYF-3 native legacy characterization.

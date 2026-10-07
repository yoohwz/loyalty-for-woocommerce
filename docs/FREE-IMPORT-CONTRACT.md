# Exact-SHA Free downstream contract

Authority: LOYF-1, LOYF-2, LOYF-5 and its approved cutover boundary in Issue #5, plus the modern adapters admitted by LOYF-8 in Issue #8. Canonical workflow remains the read-only upstream workflow; this file describes source and package provenance.

Pinned upstream: `yoohwz/wc-loyalty@9e3d663b9828a24eacd141216d5e6f6e22da8ca4`.
Free baseline: `yoohwz/loyalty-for-woocommerce@3ee1ba4841615c0fc23f3e4d6680d442ea276d1b`.
The executable `config/free-import-manifest.json` inventories every upstream Git entry by blob/mode and an explicit `import`, `reference-only`, `forbidden` or `excluded` decision. Directory membership never grants import authority.

## Core runtime and extraction

The phase is `core-runtime`: 25 explicit imports plus 58 Free product overlays form the complete 83-file distribution. Source, stage, ZIP and extracted-tree checks require this same boundary. The historical CLI mode name `legacy` automatically includes imports in this phase; it cannot waive core-runtime verification.

Five imports retain whole canonical source bytes: `points-lock.php`, `points-log-cache.php`, `points-log.php`, `role-claims.php` and `inc/backend/actions/helper/roles.php`. The core mixed modules use reviewed exact-byte extraction recipes:

| Canonical source | Free purpose / excluded closure |
| --- | --- |
| `points-transaction.php` | Atomic integer balance/log writer; no expiration, reset, reconciliation, referral reversal or checkpoint creation |
| `ledger-v2.php` | Existing row classification, source proof and checkpoint reader; narrowed Free action allowlist |
| `points-allocation.php` | Compatible receipt decoding/time validation only; no allocation planner, expiry policy or writer |
| `core-rewards.php` | Exactly-once Free user rewards and retained retries; no licensing, profile or expiration producer |
| `points-events.php` | Paired reward/deduct/level observations; no advanced/expiration notifications |
| `order-rewards.php` | Serialized purchase/reversal finalization and role projection; no referral or checkpoint allocation |
| `inc/cores/database.php` → `inc/cores/helper/database.php` | Exact additive canonical schema v3 prerequisite, Free activation identity |
| `inc/backend/actions/deduct-points.php` → `inc/cores/helper/order-deduction.php` | Canonical purchase reversal with no Premium entitlement dependency |
| `order-redemption.php` | Canonical Classic/Store API cart debit/return/payment fences and native draft/POST recovery; no product or coupon conversion producer |
| `role-ownership.php` | Canonical new-role creation and owned retirement; no hard deletion, companion scan or historical ownership adoption |

Helper names above resolve under `inc/cores/helper/` unless qualified. Canonical symbols, locks, transaction keys, persisted `yowcl_*` identities and shared role protocol identities stay unchanged. Recipes preserve canonical portions and explicitly replace/remove spans needed by the admitted Free adapters. They are not blanket prefix/domain replacements or permission to import a folder.

`config/free-core-extractions.json` pins each raw input hash, ordered byte-span replacements and selected output hash. The manifest additionally binds the recipe file's SHA-256. Input origin/SHA/tree/inventory/hash are checked before extraction, then exact selected bytes, literal WordPress text-domain conversion count and final output hash. Wrong source, corrupt/overlapping/out-of-range spans, invalid base64 or output drift fails closed before staging writes. Each replacement is reviewable in the checked-in recipe and resulting source diff.

`wp-text-domain-v1` uses PHP tokenization to change only literal domain arguments of supported global WordPress translation calls. All other bytes remain unchanged. Namespaced sources are refused; dynamic/qualified domains are not guessed. Free runtime strings and the regenerated POT use `loyalty-for-woocommerce`; owner strings and persisted identities are not translation domains.

Premium licensing/updater, campaigns, expiration, advanced rewards/referrals, redeem-products/free-shipping/coupon conversion, advanced earning, level discounts/reset and reconciliation repair remain absent/unreachable. Dormant data is preserved, not deleted or treated as permission to execute excluded behavior.

LOYF-6 additionally admits only the canonical email base, Points Reward, Points Deduct and Level Update classes and their six HTML/plain templates. The base extraction substitutes the Free template root, retains Woo-version palette fallbacks, gates failed migrations and preserves unrelated native settings on authorized Woo saves. The Free registration adapter owns only those three families; retained legacy preferences never register a second sender. Other email families remain excluded.

## Overlay and persisted boundary

Overlays enumerate exact Free metadata/bootstrap, settings, compatibility facades, UI/assets/locales and Free admission adapters. They retain version `1.2.2`, slug/main file/text domain, WordPress.org URI and all header requirements. No release/version/tag/publication authority is granted.

The loader checks durable Free/Premium ownership before canonical includes; producer adapters also check ownership. Legacy public `YOSWC_*` facades preserve constructor/callback signatures rather than alias incompatible APIs. `insert_points_log` remains nullable historical append compatibility, never a second balance writer. Native legacy hooks are one-way observations from committed canonical events.

The admitted additive schema preserves existing table/IDs/rows and balances, leaves new historical transaction fields NULL, verifies exact schema v3 and never lowers a newer witness. Cutover stores only maximum existing user/comment IDs. New signup/review/per-role rewards use canonical identities; ambiguous old signup/review/role eligibility is suppressed without fabricated events. Legacy daily date is read-only suppression evidence.

Legacy redemption markers without atomic debit proof receive an idempotent manual-review hold, never automatic credit. Old cart selections cannot fund a new order; a new selection carries frozen terms and an attempt UUID. Pending funded checkout retains that selection through same-identity retry; gateway completion/empty-cart lifecycle retires it. Manual operation scope is actor/site/target/action, independent of nonce refresh; original terms remain recoverable until acknowledged. Fractional stored balances remain byte-for-byte intact and economic mutation explicitly fails closed, including duplicate meta during historical reversal. CSV retains a durable actor-scoped form identity across rendering/reloads; original file terms and row keys remain immutable. A separate import requires an explicit authorized action, serialized with the current writer; starting it never deletes prior events/witnesses. Existing allocation checkpoints/dormant unsupported redemption records are held for review rather than rewritten.

LOYF-6 retains semantic option/email/session/role compatibility migrations, fractional resolution and full old-Free upgrade certification. LOYF-7 retains broader economic certification; LOYF-8 adds native Cart/Checkout Blocks and namespaced Store API selection updates over the same canonical redemption identity/owner. CPT/HPOS sync-off and shipped Blocks browser evidence precede positive FeaturesUtil declarations. The lifecycle and certification boundary is recorded in `docs/MODERN-CHECKOUT-CONTRACT.md`; Premium-only requests remain denied without value mutation.

## Verification and refresh

Use an authenticated read-only bare upstream snapshot outside installed plugins. CI receives no private upstream credentials; it verifies checked-in exact hashes and synthetic Git/extraction adversarial tests. Root records actual raw-upstream inspection separately.

```sh
python3 scripts/free-import.py inspect --upstream /absolute/upstream.git --sha 9e3d663b9828a24eacd141216d5e6f6e22da8ca4
python3 scripts/free-import.py verify-source --root /absolute/Free-checkout
python3 scripts/free-import.py stage --upstream /absolute/upstream.git --sha 9e3d663b9828a24eacd141216d5e6f6e22da8ca4 --source /absolute/Free-checkout --head <exact-Free-sha> --output /absolute/empty-external-directory
python3 scripts/free-import.py verify-tree --root /absolute/output/loyalty-for-woocommerce --mode projection
python3 scripts/free-import.py drift --upstream /absolute/upstream.git --sha <new-exact-sha>
```

Staging requires bound origins, an exact clean Free HEAD descended from the admitted baseline, exact overlay bytes and complete upstream proofs. All targets are validated together against duplicate, NFC/case-fold and file/ancestor collisions. Output must be empty, absolute and outside source/upstream/tool checkouts including aliases. Only the external output is written. Partial interrupted output is uncertified until verification passes. ZIP checks reject extra files, symlinks, excluded paths, identity drift and payload changes.

Drift reports all added/removed/modified paths, blobs and modes without writing source. New upstream commits require explicit readmission even with an unchanged tree. There is no wildcard import, automatic apply, task-state registry or approval parser.

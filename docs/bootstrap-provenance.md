# LOYF-3 bootstrap provenance and lifecycle evidence

Free baseline: `yoohwz/loyalty-for-woocommerce@3c1aac240df03101561b856cddb603b1501fa41b`, version 1.2.2. Read-only upstream comparison/mechanical tooling source: `yoohwz/wc-loyalty@9e3d663b9828a24eacd141216d5e6f6e22da8ca4`.

`AGENTS.md` is a thin reference to LOYF-1/current canonical governance, not a copied workflow. `scripts/ci-policy.py` is unchanged upstream. `ci-provenance.py` and its test change repository/check names. `tests/ci/policy-test.py` changes package entry path. `.github/workflows/ci.yml` preserves events, Draft/Ready routing, accepted-base policy, first-adoption FULL, provenance, named package assurance and fail-closed aggregate, replacing Premium-specific suites with the complete Free harness. Package stage/verifier change slug, allowlisted directories and required files to Free. Disposable runner follows upstream isolation/clean exact archive/database cleanup/pinned WP/Woo/WP-CLI pattern, with two Free installs rather than Premium/transition suites. No license stub or Premium feature enters Free product files.

| Entry/transition | Legacy authority and persisted identity | Side effects and replay/recovery observation |
| --- | --- | --- |
| Signup/review callbacks | Direct user meta/log writes; no persisted replay key | Replay credits again; fixture freezes limitation |
| Daily login | `loyalty_last_daily_login` date before balance writes | Same date no reward; older date awards; partial failure not certified |
| Processing/completed earning | `_points_awarded` in CPT postmeta | Both balances, reward/level hooks, role/log/email; retained marker blocks re-award after deduction |
| Cancel/refund/fail earning deduction | `_points_deducted` plus `_points_awarded` | Clamp balances, role projection, deduct/level hooks/log/mail; replay no new row |
| `set_user_role` level bonus | Rule lookup, no lifetime reward marker | Both balances/log/reward hook/mail; unchanged role does not dispatch; role re-entry awards again |
| Apply/remove cart points | Authenticated nonce AJAX; two `yoswc_loyalty_*` session keys | Selection/negative fee only; no debit; invalid nonce/amount denies |
| Classic create→thankyou | `_used_points`, `_used_points_discount`, `_loyalty_points_processed` | Creation freezes selection; thankyou clamps debit, log, clears session; repeated thankyou no debit |
| Terminal redemption return | `_used_points` removed with processed marker | Credit/log once; `_used_points_discount` remains; repeated terminal callback no return |
| Schema/readers/options | Existing row IDs/actions and legacy option shapes | dbDelta rerun preserves rows; My Account/email consume custom slug; no migration |

Readiness invariants for this task: product file bytes/version remain baseline-identical; two isolated exact-source runs reproduce the same documented legacy outcomes; raw fixture IDs/rows/options/markers remain exportable; no installed-site mutation; CI modes cannot certify missing assurance/runtime/package evidence. These are characterization/bootstrap invariants, not a claim that legacy Free satisfies prospective upstream transaction guarantees.

Adversarial evidence targets duplicate callbacks, terminal/recompletion crossings, insufficient/denied AJAX, selection-versus-committed-debit boundary, return markers, role re-entry, unchanged rows after schema rerun, first-adoption and unknown routing, latest native certification provenance, package failure propagation and cleanup on partial runner failure. Native browser/network/concurrency/storage crash coverage remains explicitly unexecuted in this bootstrap.

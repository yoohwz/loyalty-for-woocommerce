# Free First Purchase delivery

LOYF-9 base `2de7ce512f61881738710bc22952ce2d841eceb8`; read-only upstream `9e3d663b9828a24eacd141216d5e6f6e22da8ca4`. Issue9 Plan Review6036108885 admits creation cutoff per effective-enable epoch.

| Boundary | Owner / durable identity | Invariant and recovery |
| --- | --- | --- |
| Authorized settings save | Existing Free options mutex; shared two rule fields + one Free epoch witness | Positive enable establishes cutoff; positive edits preserve it; disable/zero closes epoch. Atomic native options writes/readback cannot expose broader admission after partial failure; unknown fields/wrappers preserved. Boot never creates a cutoff. |
| Payment/status callback, Classic or Store API | Shared order reward mutex; Woo CRUD order date/status/customer | Recover committed event or frozen delivery first. New candidates require own eligible customer, successful status, strict post-cutoff creation, compatible skip rules, no legacy proof or prior qualifying purchase. Query errors/malformed coverage fail closed. |
| Accepted delivery | `_yowcl_advanced_terms_first_purchase`; AS order retry | Capture entire accepted event before mutation and retain retry owner before commit. Settings/cutoff changes do not rewrite terms. |
| Economic admission | Canonical user transaction; `reward:first_purchase:<user_id>` / `first_purchase_reward` | Only the existing primitive writes balances/log. Transaction guard holds authoritative CPT/HPOS status row and checks legacy evidence; terminal order cannot commit delayed value. One user-wide winner, losing receipt converges without value. |
| Projection / observer | `first_purchase_rewarded`, winning `_yowcl_first_purchase_awarded` | Proven canonical row repairs markers; markers never mint value. Committed bonus survives later terminal transitions; normal purchase reversal remains separate. Observers are first-application only, without outbox. |
| Compatibility | Same key/action/markers and admitted shared primitive | Proven Free event replays as already applied in upstream. No claim that Premium preserves Free cutoff rejection of unawarded orders. No expiration or other advanced producers. |

Prior history includes orders before cutoff; strict ties/missing creation time fail closed. Dormant enabled options without matching valid witness remain held until explicit authorized save; administrator diagnostic explains required save. No scan/backfill, accounting checkpoint, generalized migration, release or version change.

Adversarial verification targets settings partial failure, stale/cache/query failure, zero/positive epochs, pre-enable pending/draft versus later first history, user-wide simultaneous orders, accepted contention under changing settings, terminal-before-commit and missing winner projections, across CPT/HPOS and retained/current Woo.

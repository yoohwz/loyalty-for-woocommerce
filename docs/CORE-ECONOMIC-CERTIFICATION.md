# Retained Free economic certification

Authority: LOYF-7, Issue #7, base `29330f9fade5aba88b86ce51a6ca45bf0f3a7e66`. Upstream reference is read-only `yoohwz/wc-loyalty@9e3d663b9828a24eacd141216d5e6f6e22da8ca4`.

| Entry/transition | Persisted identity and owner | Failure/retry/consumer invariant |
| --- | --- | --- |
| Native order status → purchase → reversal | `reward:order:<id>` / `:reversal`; order named ownership then canonical user transaction | One row/delta, clamp available under lock, immutable terms, recover marker/role from committed row; obsolete pending reward cannot mint after cancellation. |
| Signup/login/review/level callback → canonical user reward | Signup user, site-day login, review comment, per-user role keys; user transaction + compatibility marker projection | Concurrent callbacks and retained AS intents converge; old ambiguous/legacy markers suppress admission; frozen intent never becomes value proof. |
| Classic apply/remove → order creation → pre-gateway debit → terminal return | UUID checkout attempt, frozen owner/cart/order terms; `checkout_redeem:<uuid>` / `:return` | Selection changes no balance. Same-ID pending recovery retains funded discount; usable value requires canonical debit; return requires debit row; malformed evidence fails closed. |
| Admin add/deduct / CSV form → mutation | Actor/site/target/action operation UUID, immutable CSV file identity; canonical user transaction | Independent capability/nonce, same-ID replay, changed terms denied, partial failure/retry preserves later credit; lost outcome never creates a replacement operation. |
| History/My Account/email/public observation | Canonical deltas or unchanged nullable historical evidence; one-way events | Readers do not write balances/logs; malformed accounting proof cannot be reinterpreted as canonical; one delivery owner and no duplicate normal replay observations. |
| Free committed event → upstream shared primitive replay | Exact shared keys/actions/relations and receipts | Recognized as already applied with same row ID and no further delta. No Premium activation, commercial entitlement, advanced surface or release transition certification. |

Test the intersections through actual WP hooks, Woo status transitions/Classic checkout, native AS delivery and independent PHP/WP worker processes. Existing baseline/upgrade/hardened fixtures remain mandatory. Additional faults use the existing disposable-only checkpoint mechanism. A checkpoint after the real SQL COMMIT represents a lost application response: recovery must use the same event key for both committed and rolled-back outcomes. This is not network/protocol/gateway fault timing proof.

Historical rows, balances (including fractional/duplicate storage), role usage, migration precedence and unsupported Store API refusal retain the settled LOYF-5/6 boundary. No historical backfill or value normalization. Public notification delivery retains the canonical post-commit crash/observer window; these fixtures do not add a durable notification outbox or promise external inbox delivery. Runtime producer coverage and limitations belong in TESTING.md.

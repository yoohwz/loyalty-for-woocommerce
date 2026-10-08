# Referral Lite lifecycle

Authority: Issue #10 and its fixed start handoff. Free-owned scalar settings never rewrite Premium referral options. Only link/referrer events are admitted; no coupon, referee, guest-email attribution, configurable frequency, expiration or analytics.

| Entry / transition | Ownership and persisted identity | Failure / recovery |
| --- | --- | --- |
| Native init valid `ref` capture | Unique existing `_yo_referral_token`; fixed30-day `yowcl_ref` HttpOnly/Lax/SSL cookie, WP path/domain | Malformed/numeric/ambiguous token fails closed; last valid click replaces only uncommitted attribution |
| Link rendering / copy | Current logged-in customer's token; global token admission mutex | Token uniqueness checked before persist/readback; no accounting mutation |
| Classic order-created / Store API final processed POST | Existing order mutex, `_yowcl_referral_terms` and `_yowcl_referral_customer_identity` | Freeze even empty/no-value receipt; GET/PUT draft cannot attach; frozen receipts never recalculate from cookie/settings |
| Natural processing/completed | Monotone `_yowcl_referral_qualified`, registered customer election mutex, `_yowcl_referral_first_order` | Deterministic creation-date/ID winner across attributed and non-attributed orders; retained claim survives deletion/terminal states; incomplete/ambiguous storage fails closed |
| Link referrer delivery | Order mutex then canonical recipient lock/status row lock; `referral:link:<order>:referrer`, `referral_link_referrer_reward` | Durable unique AS retry precedes value; exact canonical proof precedes markers/current eligibility; referrer must exist; terminal blocks missing positive event |
| Failed/cancelled/refunded | Monotone `_yowcl_referral_terminal`; exact original event source | `<original>:reversal` / `referral_reward_reversal`; independent available/earning clamps including terminal zero; retained AS completes unknown outcome without touching later unrelated credit |

Native-owned history enters election validation even when its stored identity differs. An orphan, duplicate or malformed identity cannot erase that history; only a coherent validated frozen receipt may retain another customer ownership after a live owner edit.

All receipt consumers use the same authoritative persisted-row reader: absence means zero rows; empty, duplicate or unsupported rows are held unchanged before mutation. Single-value Woo metadata reads cannot authorize attachment or replace a frozen receipt.

Invariants: one registered customer winner, one event per order/component, no historical positive backfill, no marker-as-value authority. Empty final receipt prevents later mutable state from changing attribution. Existing Premium receipts outside the link/referrer/no-expiration/registered/first-order shape are held rather than interpreted as Free policy. Committed component marker proof/recovery stays independent of settings/cookies; legacy awarded markers suppress fabricated value. No raw user-ID cookie selects a referrer.

Referrer and referee are separate recipients: LOYF-9 may independently award the referred customer on the same order, and its no-clawback policy stays intact when Referral Lite reverses. Producers share the established order mutex and canonical recipient locks; contention retains only application retries, never replays Woo hooks. Post-commit notifications remain observations with the existing delivery crash window, not an outbox.

Native certification covers both CPT and HPOS sync-off, Classic/Store API finality, token/cookie security, malformed storage, frozen recovery, concurrent winners, partial/zero reversals, cross-producer behavior and actual pinned upstream primitive replay. External providers, commercial WCS and production TLS/mail remain explicit limitations.

# Free transition semantics

Authority: [LOYF-6](https://github.com/yoohwz/loyalty-for-woocommerce/issues/6), specifically the approved [one-time legacy-effective precedence](https://github.com/yoohwz/loyalty-for-woocommerce/issues/6#issuecomment-6031794246). This is an implementation contract, not another workflow registry.

| Transition | Ownership / persistence | Failure and replay invariant |
| --- | --- | --- |
| Boot → signup/login/review/level/redemption/email conversion | Site option named lock, per-feature `_before` evidence and `_v1` witness; uncached storage reads and conditional narrow writes | Freeze exact legacy source and pre-target bytes before replacing shared fields. A failed read/write/read-back cannot complete a witness. Retry uses the frozen source; unknown/dormant fields survive. |
| Witness → merchant save | Canonical shared terms and Woo email settings; legacy separate sources and snapshots remain evidence | Boot never reapplies witnessed legacy values. Free extra-points/redemption saves merge owned fields under the same option lock and deny incomplete migration. Woo native saves preserve unrelated settings. |
| Canonical event → notification/public observation | Canonical points dispatcher, three Woo native email families, request-local paired-hook suppression | Legacy hooks do not enter the accounting engine or register another mail sender. Cold and already-warm mailers share one registered object per family. |
| Old cart selection → checkout | Clear uncommitted legacy session points/discount with a reapply notice | Never infer a debit from selection or order markers. Canonical attempt/return evidence is preserved and the existing atomic debit proof remains authoritative. |
| Old database → refreshed boot/natural rewards | LOYF-5 additive schema and cutover; unchanged balances/history/roles/order markers | No recomputation, synthetic identity/history, historical re-award, physical role creator adoption or fractional normalization. |

Each feature owns only its shared pair (enablement and amount/map); redemption supplies missing defaults only. Initial legacy zero/disabled terms override dormant canonical enablement, just as initial positive terms override dormant canonical disablement. Email conversion changes only `enabled` for reward/deduct/level and keeps native subject/heading/type/other fields. No persistent conflict-selection UI is introduced. Malformed/unavailable migration storage holds the affected feature and reports an administrator diagnostic.

Evidence lives in non-autoloaded option rows with no user data. Legacy signup/login share their target container, so frozen source evidence also makes interruption before witness safe. Serialized legacy option wrappers are retained on patching; unrelated nested values are compared by their serialized representation without reinterpretation or transient object-identity equality. The site option lock coordinates plugin boot and Free settings writes; binary compare-and-swap refuses an intervening target change. Native Woo email saves run after boot conversion; they cannot save through this plugin's email class before its witness exists.

Coverage and its practical limits are recorded in `TESTING.md`. LOYF-7 broader economic certification and LOYF-8 Blocks/HPOS remain separate. Version stays 1.2.2; staging verifies bytes only and grants no publication authority.

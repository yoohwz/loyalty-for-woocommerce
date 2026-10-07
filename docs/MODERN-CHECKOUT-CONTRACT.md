# Free modern checkout adapters

LOYF-8 admitted base `2eafe7fd0cd6febe2a7b524fa342153e871cffbc`; read-only upstream `9e3d663b9828a24eacd141216d5e6f6e22da8ca4`.

| Boundary | Identity/owner | Failure/recovery invariant |
| --- | --- | --- |
| Classic AJAX / Store API cart extension | Shared server selection, user/session owner and UUID; no balance writer | Native nonce/authentication, whole affordable points, current Free rules/currency/cart economics. Replay original operation; changed terms require explicit new selection. |
| Classic create order / Store API GET/PUT draft | Same selection UUID; editable draft has no debit/immutable recovery record | Draft may track explicit reselection. Never overwrite an already frozen order's identity/economics. |
| Classic order-created / Store API POST order-processed | Canonical order-redemption service and `checkout_redeem:<uuid>` | Freeze final cart/order terms; original order ownership mutex and user transaction; debit before usable payment/order value. Same UUID cannot fund another order. |
| Store API retries / cart edits / cross-adapter retry | Retained original debit, prepared/active record and frozen order | Serialize native draft before UUID admission; reject changed frozen cart before CRUD item synchronization; same-order unknown outcome remains retryable at zero balance. |
| Gateway/status/cancellation | Existing canonical payment fence, reward/reversal and source-linked return | At most one debit/return; malformed/legacy proof holds; no fulfillment after terminal return. |
| CPT / HPOS authority, sync off | Woo CRUD/query/cache APIs; storage-specific legacy transactional marker boundary | Do not rely on synchronized postmeta. User accounting/identities and unrelated roles remain unchanged. |
| Blocks UI | Official Slot/Fill context, cart extension data and extensionCartUpdate | Own-user minimal data only; server is authority. Persisted Cart/Checkout earning-message flags apply per Slot context, independently of redemption configuration. No Classic DOM/AJAX emulation or Premium controls. |

Selection lifetime follows the existing Free contract: uncommitted selection may be explicitly changed/removed; prepared/funded order terms remain immutable and independently recoverable. A new explicit selection is a new UUID, never a rewrite of the original debit/order. Cart/rule/currency/owner drift before admission requires reapply; an already committed attempt retains its original recovery terms. No migration/backfill, new ledger, Premium activation, version/release or product-redemption surface.

Certification must execute Classic/Store API × CPT/HPOS, including native REST checkout and shipped Blocks browser UI. Retain 9.9.5 historical regression; current stable 11.1.2 requires WordPress7.0. Positive Woo FeaturesUtil declarations follow native evidence, never precede it. Broader external gateway/cron/mail/network fault timing remains outside the matrix.

Official references: [HPOS CRUD recipe](https://developer.woocommerce.com/docs/features/orders/high-performance-order-storage/recipe-book/), [Blocks extensibility](https://developer.woocommerce.com/docs/block-development/extensible-blocks/cart-and-checkout-blocks/), [ExtendSchema data](https://developer.woocommerce.com/docs/apis/store-api/extending-store-api/extend-store-api-add-data/), [cart extension update](https://developer.woocommerce.com/docs/apis/store-api/extending-store-api/extend-store-api-update-cart/).

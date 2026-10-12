# LOYF-33 presentation evidence

Synthetic disposable WordPress 6.8.3 / WooCommerce 9.9.5, PHP 8.4, private MariaDB. Neither the Human installation nor customer data was used.

Before images use Free `38a68d4b848b288808afd85774ff89eae13dc4d3`. After images use the accompanying candidate; exact candidate validation and hosted certification are recorded on the PR. The Premium reference is read-only `yoohwz/wc-loyalty@7e52b85ffaac8e5d38f830ed41bb4ef572f6a5e2`, `inc/backend/actions/emails/email-notifications.php` and `inc/backend/settings/notification.php`.

- General: interleaved Premium previews moved to a keyboard-accessible disclosure after the native fields; Overview button removed.
- Extra points: oversized First purchase input replaced with a normal points field. Human-requested `0 = disabled` / positive = enabled uses the existing authorized save and activation-cutoff mechanism.
- Referrals: inline fields moved to native form-table rows.
- Customization: color labels/pickers/hex fields stay together. After images additionally expose the configured Shop/Product message controls, which were disabled in the baseline fixture.
- Coffee admin scheme: General/Extra points previews and profile/modal Premium cards inherit the actual selected menu color.
- Emails: General | Loyalty, native Woo table columns/status icons/description tips/Manage links, exactly three Free entries, held disclosure and mobile Manage controls. Loyalty → Emails redirects to this group. Native email editors and the full mailer registry remain unchanged; General lists the other registered emails and retains Woo global email options.

Images prove presentation only. Native save/reload/restore, denied/held raw-row preservation, first-purchase validation/cutoff/retry, package and exact-head CI are separate evidence. Outbound mail/HTTP was blocked by the existing test-only isolation plugin.

The pictured General/Loyalty tables use the classic email-list mode. When Woo's block email editor is enabled, General retains Woo's native listing and Edit template editor; only the three Free entries are filtered from its presentation metadata. Native browser coverage includes that editor destination separately from these images.

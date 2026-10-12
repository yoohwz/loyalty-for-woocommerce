# LOYF-35 Settings presentation follow-up

Base: `9a2e9b9ed2989e24d599b30343b74a126ef60658`.
Read-only governance: `yoohwz/wc-loyalty@65363d160b2821fdff7f727812c56b4db7fed5b7`.
Fast scope: presentation and submenu order only. Import handlers, identities, nonces, field names, retry rules and settings save owners are unchanged.

Synthetic disposable WordPress 7.0 / WooCommerce 11.1.2 site outside Local Sites, isolated database `loyf35_ui_20261012`, loopback only, external HTTP and mail disabled. Coffee administrator scheme. Chromium desktop 1440px and mobile 390px.

- [General before](before-general-1440.png): duplicate header and grouped Premium disclosure.
- [General after](after-general-1440.png), [mobile](after-general-390.png): native sections hook, no duplicate heading, previews at their original contextual positions and scheme colors retained.
- [Tools before](before-tools-1440.png): bordered file box and adjacent recovery action.
- [Tools after](after-tools-1440.png), [mobile](after-tools-390.png): compact file/Import controls, sample guidance, separate recovery action.

Native Products and Loyalty section lists measured at the same left/top coordinates: desktop `(190,149)`, mobile `(30,174)`. WooCommerce styles native `nav` elements as main tabs, so the accessible section wrapper uses `role="navigation"` on a div; the inner native `subsubsub` list retains its default styles.

Focused assertions extend the existing consumer browser fixture: Overview/Emails/Settings menu order, native sections placement, absent duplicate header, four previews following their original related controls, file selection/clear enablement and retained shared operation identity. Existing native save/reload/restore and all baseline consumer/email coverage are retained; no new matrix or harness. Desktop alignment is asserted on the existing Tools visit; mobile follows Woo's native centered layout.

Owner requested a package-only `1.3.0-dev.3` test ZIP and approval before Acceptance Review. Git source retains version 1.2.2. No installation, merge, release or publication is performed.

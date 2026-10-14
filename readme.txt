=== Loyalty for WooCommerce ===
Contributors: yoohw, baonguyen0310
Tags: woocommerce, loyalty program, reward points, points rewards, customer rewards
Requires at least: 6.3
Tested up to: 7.1.3
WC tested up to: 11.2.0
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Reward purchases and customer actions with points, offer checkout discounts, and build a loyalty program for your WooCommerce store.

== Description ==

Loyalty for WooCommerce helps you reward customers for shopping and returning to your store. Set your earning rules, choose how points convert into discounts, and let registered customers follow their balance and loyalty progress.

The free plugin includes purchase rewards, optional bonus actions, First Purchase rewards, Referral Lite, cart and checkout redemption, customer displays, and tools for managing points. Configure the features you need directly in WooCommerce settings.

[Documentation](https://docs.yoohw.com/category/woocommerce-loyalty/) | [Support](https://yoohw.com/support/) | [Premium](https://yoohw.com/product/woocommerce-loyalty-points-and-rewards/) | [Demo](https://sandbox.yoohw.com/demo/wclp_demo.html)

= Earn points =

* Award points based on eligible order totals and your configured order statuses.
* Offer optional bonuses for account registration, daily login, product reviews, and reaching a loyalty level.
* Add a First Purchase bonus for a customer's first eligible purchase after the offer is enabled.
* Enable Referral Lite to reward a registered referrer when a referred registered customer places their first qualifying order.
* Define loyalty levels from total earned points and show customers their progress.

= Redeem points at checkout =

Customers can apply available points for a discount in both Classic cart/checkout and WooCommerce Cart/Checkout Blocks. You control the point-to-discount conversion and redemption limits.

The discount stays tied to the customer's cart and order. Selecting points does not immediately spend them; points are deducted when checkout is finalized. Funded redemptions use the same accounting flow across Classic checkout and Blocks, with recovery and replay checks to help prevent duplicate point changes.

Point discounts use a cart discount fee. The plugin does not create a reusable WooCommerce coupon for each redemption. Normal WooCommerce coupons remain separate, and point discounts are limited to the remaining eligible cart value.

= Show customers their rewards =

* A floating Loyalty bubble explains enabled earning rules, bonus offers, redemption, balance, and level progress.
* The My Account points area shows the customer's points, loyalty information, and points history.
* The Referrals card lets eligible customers view and copy their Referral Lite link.
* Optional shop, product, cart, and checkout messages explain earning opportunities.
* Gutenberg blocks and shortcodes let you add loyalty information to your own pages.

The five customer components are Points balance, Points history, Loyalty level, Ways to earn, and Referral link. Their shortcodes are:

* `[loyf_points_balance]`
* `[loyf_points_history]`
* `[loyf_level_progress]`
* `[loyf_ways_to_earn]`
* `[loyf_referral_link]`

Personal information is loaded for the signed-in customer. Guest views do not expose another customer's balance, history, or referral link.

= Manage your program =

* Use Loyalty Overview to review available point stock and reward activity. Historical, held, or incomplete data carries a coverage or Unavailable indication.
* Add or deduct points from customer profiles and the Users screen, with a description for the point log.
* Import customer point balances and total earned points from CSV.
* Recalculate loyalty levels from existing earned points.
* Export a snapshot of current customer balances, earned points, levels, and balance-state indicators.
* Configure optional points-earned, points-deducted, and level-update emails through native WooCommerce Emails settings.

Snapshot exports include a final completion record. Check it before treating the report as complete. An export report is not an import template; use the sample provided by the import tool for imports.

= Updating an existing store =

Existing point balances, historical logs, and saved settings are preserved. Background migration checks whether earlier settings can be used safely. When a legacy reward, redemption, or notification configuration cannot be verified, future activity for that feature may be paused while its saved terms remain intact.

After updating, review the Loyalty notice and your earning, redemption, and notification settings. Configure future offers deliberately through the ordinary settings. Unreadable settings remain on hold; check WooCommerce Scheduled Actions and logs if processing cannot finish. Migration does not reset balances, replay historical rewards, or automatically restore old snapshots.

= Free and Premium =

First Purchase and Referral Lite are functional Free features. Customer points history is also included in Free.

For advanced earning rules, expanded referral rewards and coupons, point expiration, redeemable products, campaigns, and expanded reporting or redemption controls, see the [Premium version](https://yoohw.com/product/woocommerce-loyalty-points-and-rewards/).

Premium previews in Free explain optional features. A preview does not enable campaigns or advanced store-wide points-history tools in the free plugin.

= Privacy and external connections =

The plugin stores loyalty settings, customer point balances and history, and order-related loyalty records in your WordPress database.

Referral Lite uses a first-party referral cookie when a visitor follows a valid referral link. The cookie contains the referral token, lasts up to 30 days, and is used to identify the referrer for an eligible order. It does not award points merely for visiting a link.

Free does not send installation or subscription reports to YoOhw, collect the store administrator's email for a mailing list, or require a reporting opt-in. Historical installation-report settings are left dormant; this does not delete records previously held by a service provider.

Optional transactional notifications use your store's WordPress/WooCommerce mail configuration. You control their enablement and content; they are separate from installation reporting and marketing subscriptions.

Bundled assets are served locally. If you configure an external image URL for a loyalty display or email icon, the browser or email client requests that image from the selected host. Documentation, support, demo, and Premium links open when followed. WordPress, WooCommerce, and other store services retain their own update, mail, and privacy behavior.

== Installation ==

1. Install and activate WooCommerce.
2. Install Loyalty for WooCommerce from the WordPress plugin directory, or upload the `loyalty-for-woocommerce` folder to `/wp-content/plugins/`.
3. Activate the plugin under **Plugins > Installed Plugins**.
4. Open **WooCommerce > Settings > Loyalty** and configure earning, redemption, levels, and optional bonuses.
5. Choose the customer displays you want. Configure notifications under **WooCommerce > Settings > Emails > Loyalty**.
6. For an existing store, review any migration notice before enabling future rewards or redemption.

Activation alone does not launch a reward program. Review and save the rules appropriate for your store.

== Frequently Asked Questions ==

= Does the plugin work with Cart and Checkout Blocks? =

Yes. Point redemption supports both WooCommerce Cart/Checkout Blocks and Classic cart/checkout. Earning messages and redemption can be configured separately.

= Does it support HPOS? =

Yes. The plugin supports WooCommerce High-Performance Order Storage (HPOS) and traditional WordPress order storage.

= How do customers earn points? =

Enable purchase earning and any bonus actions you want to offer. Available actions include account registration, daily login, product reviews, level-up, First Purchase, and Referral Lite. Customers only earn from enabled rules for which they qualify.

= How does First Purchase work? =

Set a positive First Purchase bonus in Extra points settings. It applies to eligible first purchases after the offer is enabled; enabling it does not award points retroactively for earlier orders.

= What is included in Referral Lite? =

Registered customers can share a referral link. A registered referrer can earn fixed points after the referred registered customer's first qualifying order. Self-referrals do not qualify. Cancelled, failed, or refunded qualifying orders reverse the referral reward. Referral coupons and expanded referral rules are Premium features.

= Does point redemption generate a coupon code? =

No. The point discount is applied as a fee tied to the current customer's cart and order. It does not create a reusable coupon code.

= Where can I manage points and view activity? =

Use Loyalty Overview for program activity, customer profiles or the Users screen for point adjustments, and Loyalty Tools for imports, level recalculation, and snapshot exports. Customers can view their own history in My Account or the Points history block/shortcode.

= Will an update remove existing points or history? =

No. Updates preserve stored balances and historical logs. Some legacy reward, redemption, or email settings may be paused when their previous configuration cannot be verified. Review the Loyalty notice and configure those features before using them again.

= Can I import an existing points program? =

You can import customer available points and total earned points using the CSV import tool in **WooCommerce > Settings > Loyalty > Tools**. Follow the provided sample format. Snapshot exports use a reporting format and are not import templates.

= Are point expiration, campaigns, and product redemption included in Free? =

These advanced features are available in the optional Premium version. Their previews in Free do not activate them.

= Can I translate the plugin? =

Yes. The plugin uses WordPress translation functions and includes a `.pot` file in `/languages/`.

== Changelog ==

= 1.3.0 (Oct 14, 2026) =
* New: Added optional First Purchase bonuses and Referral Lite rewards for registered referrers after a qualifying first purchase.
* New: Added a Loyalty Overview, customer points/level/progress/referral/history blocks and shortcodes, and snapshot CSV export with completion and balance-state indicators.
* Improve: Added funded point redemption in WooCommerce Cart/Checkout Blocks and support for HPOS order storage alongside Classic checkout.
* Fix: Hardened rewards, redemption, returns and manual point operations against duplicate requests and retries, with safer recovery after interrupted processing.
* Improve: Preserved historical points, logs and settings during upgrades. Unverified legacy reward, redemption and email settings are paused safely until configured in Loyalty settings.
* Improve: Refreshed Loyalty settings, the My Points Referrals card and contextual Premium previews; grouped Loyalty notifications in native WooCommerce Emails settings.
* Privacy: Removed automatic installation/subscription reporting to YoOhw; optional store-managed transactional emails remain available.

== Upgrade Notice ==

= 1.3.0 =
Existing points and history are preserved. Review Loyalty settings after updating: unverified legacy rewards, redemption and notifications may be paused until configured.

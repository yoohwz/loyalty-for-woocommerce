=== Loyalty for WooCommerce ===
Contributors: yoohw, baonguyen0310
Tags: woocommerce, loyalty program, reward points, points rewards, customer rewards
Requires at least: 6.3
Tested up to: 7.1.3
WC tested up to: 11.1.2
Requires PHP: 7.4
Stable tag: 1.2.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Create a WooCommerce loyalty program with reward points, customer rewards, cart discounts, loyalty levels, and points history.

== Description ==

Loyalty for WooCommerce is a points and rewards plugin for WooCommerce stores. It helps you reward purchases and customer actions with points, then lets customers redeem those points for discounts in the standard WooCommerce cart and checkout flow.

Use it to build a simple WooCommerce loyalty program, encourage repeat purchases, show customers their reward progress, and manage point balances from the WordPress admin.

The free version focuses on the core loyalty workflow: earning points, redeeming points, showing customer reward information, and keeping a clear points log.

[Premium version](https://yoohw.com/product/woocommerce-loyalty-points-and-rewards/) | [Documentation](https://docs.yoohw.com/category/woocommerce-loyalty/) | [Support](https://yoohw.com/support/) | [Demo](https://sandbox.yoohw.com/demo/wclp_demo.html)

== Key Features ==

* Create a WooCommerce points and rewards program
* Award points based on order totals
* Reward account registration, product reviews, daily login, and level-up actions
* Let customers redeem points for cart or checkout discounts
* Show a floating Loyalty bubble with reward rules and customer point details
* Display customer points and points history in My Account
* Manually reward or deduct points from user profiles and the Users screen
* Import point balances from CSV
* Offer optional First Purchase rewards and Referral Lite for registered customers
* Set up a new program with the optional Quick Start wizard
* Review available/earned points and recent reward activity in a basic Dashboard
* Add points, level, progress, referral and history blocks or shortcodes
* Export members or history as snapshot CSV with coverage and completion indicators
* Redefine customer loyalty levels from existing earned points
* Prevent discount abuse by keeping point discounts and coupons separate
* Send optional email notifications for point and level updates
* Works with standard WooCommerce coupons, fees, cart totals, and checkout totals

== How It Works ==

1. Set the earning rule, such as how many points a customer earns per order amount.
2. Choose the order statuses that should award or deduct points.
3. Configure how many points convert into a cart or checkout discount.
4. Enable customer-facing displays such as the Loyalty bubble, cart message, checkout message, or My Account points tab.
5. Customers earn points from eligible actions and redeem available points during checkout.

== Quick Start and Existing Stores ==

A genuinely new Free installation can use Quick Start to choose earning, redemption, levels and optional rewards. Previewing or leaving the wizard does not launch a program. Existing or previously used stores receive a review path rather than an automatic reset.

When the source of a migrated feature's settings cannot be established, that feature stays on hold. An administrator can review and keep complete current terms, explicitly adopt valid legacy Free terms, or disable the feature using Free settings. This does not change balances or history, restore old snapshots automatically, or reset Premium configuration.

== Loyalty Points and Rewards ==

The plugin stores each customer's current points and total earned points. Store managers can review point activity, add or remove points manually, and see the reason for each point change.

Supported free point actions include:

* Purchase rewards
* Account sign-up bonus
* Product review bonus
* Daily login bonus
* Level-up bonus
* Optional First Purchase bonus
* Optional Referral Lite reward to a registered referrer after a qualifying first purchase
* Manual admin reward
* Manual admin deduction
* Points used for a discount
* Points returned for incomplete orders

== Cart and Checkout Redemption ==

Customers can redeem points from the cart or checkout when point redemption is enabled and their balance is high enough. The discount is applied as a cart fee instead of a generated coupon code, which keeps point usage tied to the current customer session.

The checkout messaging explains how many points are available and how the discount is calculated from your configured conversion rule.

== Loyalty Bubble and Customer Display ==

The frontend Loyalty bubble helps customers understand your rewards program without leaving the storefront. It can show earning rules, bonus rewards, redemption rules, available points, loyalty level, and progress toward the next level.

You can also show reward messages on shop pages, product pages, cart, checkout, and the customer My Account area.

== Dashboard, Blocks and CSV Export ==

The basic Dashboard distinguishes current available/earned point stock from recent reward activity. Missing, historical or held data carries a coverage or Unavailable indication; it is not converted into a misleading exact total.

Customer points, level, progress, referral and history components are available as Gutenberg blocks and shortcodes. Logged-in customers see their own information; guest views do not expose another customer's balance or referral link.

Tools can export members or history as snapshot CSV. Check the final completion record before treating an export as complete. Numeric values are exported as text, and text that could be interpreted as a spreadsheet formula is escaped. An exported report is not a CSV import template.

== Premium Features ==

An optional premium version is available for stores that need advanced loyalty workflows such as product and category earning rules, advanced referral rules and coupons, point expiration, redeemable products, automated rules, and expanded redemption controls.

Learn more: [https://yoohw.com/product/woocommerce-loyalty-points-and-rewards/](https://yoohw.com/product/woocommerce-loyalty-points-and-rewards/)

== Privacy and External Connections ==

Free does not send installation or subscription reports to YoOhw, collect the store administrator's email for a mailing list, or require a reporting opt-in. Historical installation-report options are left dormant; removing this plugin feature does not delete records previously held by a service provider.

Optional points-earned, points-deducted and level-update notifications use the store's native WooCommerce/WordPress mail configuration. Store administrators control those transactional notifications. They are separate from installation reporting or marketing subscriptions.

Assets bundled with the plugin are served locally. A store administrator can choose an external image URL for a display or email icon; that browser/email-client request is made to the configured image host. Documentation, support and Premium links open only when followed. WordPress/WooCommerce and the store's other services retain their own update, mail and privacy behavior.

== Installation ==

1. Upload the `loyalty-for-woocommerce` folder to `/wp-content/plugins/`, or install the plugin from the WordPress plugin directory.
2. Activate the plugin from **Plugins > Installed Plugins**.
3. Make sure WooCommerce is installed and active.
4. Go to **WooCommerce > Settings > Loyalty**.
5. Set loyalty levels, earning rules, redemption rules, and display options.

== Frequently Asked Questions ==

= What is Loyalty for WooCommerce? =

Loyalty for WooCommerce is a WooCommerce points and rewards plugin. It lets store owners award points to customers and let customers redeem points for cart or checkout discounts.

= How do customers earn reward points? =

Customers can earn points from eligible purchases and configured bonus actions such as account registration, product reviews, daily login, and level-up events.

= How do customers redeem points? =

When point redemption is enabled, eligible logged-in customers can redeem available points from the cart or checkout. The plugin applies the discount to the current cart session.

= Does the plugin create WooCommerce coupons for redeemed points? =

No. Point redemption is handled through the customer cart session and discount fee logic, not a predictable coupon code.

= Can I manually add or remove customer points? =

Yes. Store managers can reward or deduct points from the user profile screen or the Users screen. Each manual action can include a description for the points log.

= Can I import existing customer point balances? =

Yes. Go to **WooCommerce > Settings > Loyalty > Tools** to import user points and total earned points from a CSV file.

= Does it work with WooCommerce checkout? =

Yes. Both Classic cart/checkout and WooCommerce Cart/Checkout Blocks use the same funded redemption flow. HPOS is supported; turning off cart redemption does not turn off purchase earning messages.

= Does it support point expiration? =

Point expiration is available in the optional premium version.

= Is it translation-ready? =

Yes. The plugin includes localized strings and a `.pot` file in the `/languages/` directory.

== Compatibility ==

* Requires WooCommerce.
* Supports Classic and Cart/Checkout Blocks, and CPT or HPOS order storage.
* Works with standard WooCommerce product types.
* Uses WordPress user meta and a custom points log table for loyalty accounting.

== Developer Notes ==

The plugin includes hooks for earning, redemption, logging, and email flows. Legacy `yoswc_loyalty_` observations remain available alongside canonical reward events. Historical observations do not create a second accounting writer.

== Changelog ==

= 1.2.2 (Jun 13, 2026) =
* Fix: Reworked point redemption to use the WooCommerce session and cart discount fee instead of generated `yosl-loyalty-{user_id}` coupons.
* Fix: Corrected admin JavaScript asset paths for user point modals.
* Improve: Updated third-party service disclosure and limited the YoOhw subscription request to the first successful install connection.
* Improve: Added translation-safe fallbacks for default point labels and My Account labels.

= 1.2.1 (Jun 6, 2026) =
* Fix: Added a WooCommerce runtime guard for older WordPress versions that do not enforce plugin dependencies.
* Fix: Removed unnecessary guest AJAX handlers from loyalty points actions.
* Improve: Cleaned translation text domains, translator comments, and package metadata for WordPress.org readiness.

= 1.2.0 (Jun 1, 2026) =
* New: Added a frontend Loyalty bubble with reward levels, earning rules, bonus rewards, point redemption, and customer reward summary.
* New: Added Customization settings for Loyalty bubble visibility and bottom-left/bottom-right placement.
* New: Added premium previews for Advanced earning rules and Redeem products in the General settings.

= 1.1.5 (Apr 27, 2026) =
* Improve: Optimized and cleaned functionality.
* Improve: WooCommerce 10.7 compatibility.

= 1.1.4 and older =
* Earlier releases added member card customization, extra point actions, import tools, level recalculation, email notifications, compatibility improvements, and initial loyalty points features.

== Upgrade Notice ==

= 1.2.2 =
Recommended update for WordPress.org readiness, third-party service disclosure, translation fallbacks, and package metadata.

= 1.2.1 =
Recommended update for WordPress.org readiness, WooCommerce dependency handling, translation cleanup, and loyalty points action hardening.

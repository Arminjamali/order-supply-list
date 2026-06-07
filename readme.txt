=== Order Supply List ===
Contributors: aryabyte
Tags: woocommerce, orders, supply, inventory, production
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.6.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Displays the required production quantities grouped by parent category for WooCommerce orders in processing, on-hold, or pending status.

== Description ==

**Order Supply List** aggregates all active WooCommerce orders (processing, on-hold, pending) and shows how many units of each product need to be fulfilled — grouped by parent product category and sub-category.

**Features:**

* Groups products by parent category and sub-category
* Supports product variations with attribute labels
* Calendar-aware date filter: Jalali for Persian admin UI, Gregorian for English/LTR admin UI
* Print-friendly layout
* Lightweight — JalaliDatePicker is bundled locally; no external CDN dependencies

== Installation ==

1. Upload the `order-supply-list` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Navigate to **Order Supply** in your admin menu

== Frequently Asked Questions ==

= Does this plugin work without WooCommerce? =

No. WooCommerce must be installed and active.

= What order statuses are included? =

Processing, On-Hold, and Pending orders are included.

= Can I filter by date? =

Yes. The plugin uses Jalali (Shamsi) dates in Persian admin UI and Gregorian dates in English/LTR admin UI. Internally, WooCommerce queries always use Gregorian dates.

== Changelog ==

= 1.6.7 =
* Bundled JalaliDatePicker JS/CSS locally instead of loading from CDN
* Re-hardened print CSS so printed output contains only report tables

= 1.6.6 =
* Renamed plugin slug/text domain to order-supply-list
* Changed internal function/asset prefix from os to osl
* Added calendar-aware date handling: Jalali for Persian UI and Gregorian for English UI
* Improved LTR/RTL layout handling

= 1.6.0 =
* Added internationalisation (i18n) support
* Fixed version mismatch in plugin header
* Improved security: escaped all output consistently
* Removed external Google Fonts dependency (now locally loaded)
* Added readme.txt for WordPress.org repository

= 1.5.0 =
* Added date filter with Jalali calendar support
* Added product variation labels
* Improved print layout

= 1.4.0 =
* Initial release

== Upgrade Notice ==

= 1.6.7 =
Recommended update: removes external CDN dependency and fixes print output.

= 1.6.6 =
Recommended update: adds calendar-aware date handling and updates internal prefixes.

= 1.6.0 =
Recommended update: adds i18n support and security improvements.

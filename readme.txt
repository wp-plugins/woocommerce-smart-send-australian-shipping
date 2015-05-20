=== Smart Send Shipping for WooCommerce ===
Contributors: sp4cecat, goSmartSend
Tags: australia, calculator, carriers, cart, e-commerce, ecommerce, woocommerce, postage, shipping, shop, tax, courier
Requires at least: 3.5
Tested up to: 4.2.2
Stable tag: 2.1.5
License: GPLv2

The WooCommerce Smart Send shipping plugin integrates Australian shipping cost calculations for 'Smart Send' online shipping.

== Description ==

Seamlessly integrate shipping for your Australian business with WooCommerce and the Smart Send shipping plugin.

Allows customers to get an accurate quote for shipping before checking out, simply by entering some basic address info, as well as offering multiple shipping price point options, receipted delivery, transport assurance, tail-lift options AND the ability to set handling fees; flat rate or percentage.

Merchant can fulfill shipping directly from within the WooCommerce 'orders' section of the dashboard, specifying pickup date.

For more information visit [http://codexmedia.com.au/woocommerce-smart-send-shipping-plugin/](http://codexmedia.com.au/woocommerce-smart-send-shipping-plugin/ "http://codexmedia.com.au/woocommerce-smart-send-shipping-plugin/")

This plugin requires the WooCommerce e-commerce plugin.

== Installation ==

1. Upload the folder 'woocommerce-smartsend' to the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Under Woocommerce -> Settings -> Shipping, find the Smart Send section and fill out the required fields. You will need a Smart Send VIP account.

== Shipping Classes ==

Shipping classes (eg 'Carton', 'Tube', 'Satchel') can now be set on a per-product basis - just go to the 'shipping' tab on the 'Edit Product' page. This also includes new 'priority' and 'prepaid road' satchels.

If you choose either 'Best fixed price road' or 'Best priority', the calculator will choose the smallest-fitting satchel (comparing maximum weight and dimensions of each possible satchel with that of the product).

== Support ==

Support is available at the [Codex Media website](http://codexmedia.com.au/support "Support");

== Upgrade Notice ==

Upgrade through dashboard for added control over results users see and bug fixes.

== Changelog ==

= 2.1.6 =

 - Bug fix in HTML patterns

= 2.1.5 =

- Checkout form - postcode/phone restrictions imposed only on Australia

= 2.1.4 =

- Moved checkout fields customisation to locale settings
- WooCommerce compatibility noted
- ABSPATH checking

= 2.1.3 =

- Extra address verifications functions
- Bug fix: Array declaration fails in PHP 5.3

= 2.1.2 =

- Additions to core SDK functions (address validation)
- Tested with WordPress 4.2
- Tested with WooCommerce 2.3.8

= 2.1.1 =

- Fixed bug where address1 was being set in both address fields at bookjob time

= 2.1.0 =

 - Better quote caching system integrated
 - Errors and notices handled better on checkout
 - Suburb / postcode UI improved on cart shipping estimate
 - Tail-lift delivery must be enforce for items over 30kg for non-business deliveries
 - Added second origin street address
 - Warning on character restrictions on settings screen

= 2.0.2 =

 - Check that WooCommerce is installed before running

= 2.0.1 =

 - Added upgrade notice

= 2.0 =

Major update

New Features:

 - Shipping fulfilment from within dashboard
 - Shipment tracking from within dashboard
 - Complete restructuring of SDK classes / API interface
 - Intelligent caching of orders and quotes for use in booking orders
 - Adds 'cost' field to products, for accurate transport assurance calculations

Bug fixes:

 - Better delivery address parsing

= 1.4.9 =

 - Allow for extra delivery address post fields on quote calculations

= 1.4.8 =

 - Only show product shipping errors (relating to weight etc) to admin users
 - Changed utils class debug variable name

= 1.4.7 =

 - Finer-grained cache control to allow for variations missed by WooCommerce transient storage
 - Magic getter in utils class

= 1.4.6 =

 - Use compatible shipping tax rate ID if available

= 1.4.5 =

 - Fixed parent checking
 - Some issues with html entity encoding on password

= 1.4.4 =

 - Tax component returned separately with shipping prices
 - Product variations will now use parent dimensions and weight if any are missing from the variation itself

= 1.4.3 =

 - Boolean settings on quote request

= 1.4.2 =

 - Force dimension to that of pre-paid satchel when necessary

= 1.4.1 =

 - Shipping class issues fixed

= 1.4 =

 - Auto-complete dropdown for suburbs when postcode entered (on checkout); fixed-price and priority satchel options for shipping classes

= 1.3.5 =

 - Added per-product shipping classes; separated logic in to other files

= 1.3.1 =

 - Used init function, and better checking for missing TOWN parameter - search by postcode if missing

= 1.3 =

 - MAJOR FIX: WooCommerce class dependencies, removed checking for plugin orders, uses woocommerce init action and replaced add_error with wc_add_notice

= 1.2.6 =

 - Cleaned up code, removed 'test server' option, proper variable checking

= 1.2.5 =

 - Some minor tweaks

= 1.2.3 =

 - Fixed problem with rounding up weights, better error formatting

= 1.2.2 =

 - Round up weights and better error reporting

= 1.2.1 =

 - Returned shipping quote if error returned along with prices

= 1.2 =

 - Fixed some major issues with shipping address data

= 1.1.5 = 

 - Added transparent country allow settings

= 1.1.4 =

 - Minor fixes.

= 1.1.3 =

 - Fixed issues with multiple of same item in cart.

= 1.1.2 =

 - Fixed bug when only one results is returned by API
 - Added option for displaying cheapest, fastest or all shipping solutions returned from API.

= 1.1.1 =

 - Suppress error when only one result comes back

= 1.1 =

 - Added option for using live or test Smart Send API
 - Added option to specify if your product weights are in grams or kilograms
 - Improved error reporting
 - Minor code improvements

= 1.0.1 =

 - Fixed textual errors on admin screen
 - Removed some debugging code that was causing an error

== Frequently Asked Questions ==

= Do I need a Smart Send VIP account to use shipping calculation? =

Yes, the username and password are required to query the Smart Send SOAP API. To sign up, go to:
[https://www.smartsend.com.au/vipClientEnquiry.cfm](https://www.smartsend.com.au/vipClientEnquiry.cfm "https://www.smartsend.com.au/vipClientEnquiry.cfm").

= Why do I need to set the 'shipping origin town' - isn't the postcode enough? =

There can be multiple towns within a postcode, and this detail can make a difference to the best price.

== Screenshots ==

1. Admin settings screen
2. Checkout page with additional user options

== Upgrade Notice ==

= 2.1.0 =
Please note that with this version, due to OH&S concerns, we now require tail-lift delivery be selected if a delivery is going to a non-company AND if an item in the shipment is over 30kg. A customer is considered a non-company if the company field is not completed in billing details. This setting can be changed at the bottom of the options screen.
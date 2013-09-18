=== WooCommerce Smart Send Shipping ===
Contributors: sp4cecat
Donate link: http://codexmedia.com.au/donations
Tags: australia, calculator, carriers, cart, e-commerce, ecommerce, woocommerce, postage, shipping, shop, tax
Requires at least: 3.5
Tested up to: 3.5.2
Stable tag: 1.21
License: GPLv2

The WooCommerce Smart Send shipping plugin integrates Australian shipping cost calculations for 'Smart Send' online shipping.

== Description ==

Seamlessly integrate shipping for your Australian business with WooCommerce and the Smart Send shipping plugin.

Allows customers to get an accurate quote for shipping before checking out, simply by entering some basic address info, as well as offering multiple shipping price point options, receipted delivery, transport assurance, tail-lift options AND the ability to set handlng fees; flat rate or percentage.

For more information visit [http://codexmedia.com.au/woocommerce-smart-send-shipping-plugin/](http://codexmedia.com.au/woocommerce-smart-send-shipping-plugin/ "http://codexmedia.com.au/woocommerce-smart-send-shipping-plugin/")

This plugin requires the WooCommerce e-commerce plugin.

== Installation ==

1. Upload the folder 'woocommerce-smartsend' to the '/wp-content/plugins/' directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Under Woocommerce -> Settings -> Shipping, find the Smart Send section and fill out the required fields. You will need a Smart Send VIP account.

== Support ==

Support is available at the [Codex Media website](http://codexmedia.com.au/support "Support");

== Upgrade Notice ==

Upgrade through dashboard for added control over results users see and bug fixes.

== Changelog ==

= 1.2.1 =
Returned shipping quote if error returned along with prices

= 1.2 =
Fixed some major issues with shipping address data

= 1.1.5 = 

Added transparent country allow settings

= 1.1.4 =

Minor fixes.

= 1.1.3 =

Fixed issues with multiple of same item in cart.

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
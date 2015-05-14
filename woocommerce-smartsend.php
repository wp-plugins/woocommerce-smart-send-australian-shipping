<?php
/*
	Plugin Name: Smart Send Shipping for WooCommerce
	Plugin URI: http://digital.smartsend.com.au/plugins/woocommerce
	Description: Add Smart Send Australian shipping calculations to Woo Commerce.
	Version: 2.1.5
	Author:  Smart Send
	Author:  Paul Appleyard
	Author URI: http://digital.smartsend.com.au/plugins/woocommerce-shipping-plugin
	License: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
	WC requires at least: 2.0
	WC tested up to: 2.3.8
*/

/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) )
{

	// Paths

	define( 'SSBASE', dirname( __FILE__ ) . '/' );
	define( 'SSCACHE', SSBASE . 'cache/' );
	define( 'SSINC', SSBASE . 'includes/' );
	define( 'SSASSETS', SSBASE . 'assets/' );
	define( 'SSLOGS', SSBASE . 'logs/' );
	define( 'SSLABELS', SSBASE . 'labels/' );

	// SDK classes
	foreach (glob(dirname(__FILE__)."/sdk/*.php") as $filename)
	{
		include $filename;
	}

	require_once('smartsend-plugin.php');

	// Plugin includes
	foreach (glob(dirname(__FILE__)."/includes/*.php") as $filename)
	{
		include $filename;
	}

}
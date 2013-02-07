<?php
/*
	Plugin Name: Woo Commerce - Smart Send Shipping Plugin
	Plugin URI: http://codexmedia.com.au/woocommerce-smart-send-shipping-plugin/
	Description: Add Smart Send shipping calculations to Woo Commerce e-commerce plugin
	Version: 1.1.1
	Author:  Paul Appleyard	
	Author URI: http://codexmedia.com.au/
	License: GPL v2 - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/
if( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

	// Plugin is breaking if Woo Commerce does not get loaded first
	function woocommerce_first() {
		$wc_path = 'woocommerce/woocommerce.php';
		$active_plugins = get_option('active_plugins');
		$this_plugin_key = array_search($wc_path, $active_plugins);
		if ($this_plugin_key) { // if it's 0 it's the first plugin already, no need to continue
			array_splice($active_plugins, $this_plugin_key, 1);
			array_unshift($active_plugins, $wc_path);
			update_option('active_plugins', $active_plugins);
		}
	}
	add_action("activated_plugin", "woocommerce_first");

	if( class_exists('WC_Shipping_Method'))
	{
		require_once('smartSendUtils.php');
		require_once('smartsend-plugin.php');
	}

	// file_put_contents( 'C:\docroot\wpdev\woocommerce\wp-content\plugins\woocommerce-smartsend\pluginz', print_r( get_option( 'active_plugins' ), true ) );

}
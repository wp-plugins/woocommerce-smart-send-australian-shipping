<?php

class WC_smart_send extends WC_Shipping_Method
{

	protected $_shipToTown;
	protected $_shipToPostcode;
	protected $_shipToState;
	protected $_shipToCountry;

	// Minimum weight at which tail-lift assistance is triggered
	public static $tailMin = 30;

	function __construct()
	{
		$this->id 							= 'smart_send';
		$this->method_title 				= 'Smart Send';
		$this->title 		  				= 'Smart Send';
		$this->tax_status					= 'none';

		add_action( 'woocommerce_update_options_shipping_smart_send', array(&$this, 'process_admin_options'));

		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();		// Define user set variables

		foreach( $this->settings as $k => $v ) $this->$k = $v;
	}

	// Set the destination town and postcode to use. If not Australia, disable the plugin
	protected function _setDestinationVars()
	{
		if( is_admin() ) return;
		global $woocommerce;

		if( isset( $_POST['post_data']))
		{
			foreach( explode( '&', $_POST['post_data']) as $var )
			{
				list( $k, $v ) = explode( '=', $var );
				$postData[$k] = $v;
			}
	      if($postData['shipping-postcode'] ) $this->_shipToPostcode = $postData['shipping-postcode'];
	      else if(isset($postData['billing-postcode']) ) $this->_shipToPostcode = $postData['billing-postcode'];
	      
	      if($postData['shipping-city'] ) $this->_shipToTown = $postData['shipping-city'];
	      else if(isset($postData['billing-city']) ) $this->_shipToTown = $postData['billing-city'];
		}
		else
		{
			$this->_shipToCountry 	= $woocommerce->customer->get_shipping_country();
			$this->_shipToState 		= $woocommerce->customer->get_shipping_state();
			$this->_shipToPostcode 	= $woocommerce->customer->get_shipping_postcode();
		}
	}

	public function init_form_fields()
	{
		$this->form_fields = array(

			'enabled' => array(
				'enabled' 		=> __( 'Enable/Disable', 'woocommerce' ),
				'type' 			=> 'checkbox',
				'label' 			=> __( ' Enable Smart Send shipping', 'woocommerce' ),
				'default' 		=> 'no',
				),
			'denoted' => array(
				'title' 			=> __( 'Method Title', 'woocommerce' ),
				'type' 			=> 'text',
				'description' 	=> __( ' Displayed next to each shipping option/price point.', 'woocommerce' ),
				'default'		=> __( 'Courier', 'woocommerce' ),
				),
			'vipusername'		=> array(
				'title' 			=> __('VIP Username', 'woocommerce' ),
				'type' 			=> 'text',
				'description'	=> __( ' VIP username issued by Smart Send' )
				),
			'vippassword' => array(
				'title' 			=> __('VIP Password', 'woocommerce' ),
				'type' 			=> 'text',
				'description'	=> __( ' VIP password issued by Smart Send' )
				),
			'server' => array(
				'title' 		=> __( 'Smart Send Server', 'woocommerce' ),
				'type' 			=> 'select',
				'description' 	=> __( ' Select test server if you are using the test account.' ),
				'default' 		=> 'live',
				'options'		=> array(
					'live' 		=> __( 'Live Server', 'woocommerce' ),
					'test' 	=> __( 'Test Server', 'woocommerce' )
					)
				),
			'weights' => array(
				'title' 		=> __( 'Weight Units', 'woocommerce' ),
				'type' 			=> 'select',
				'default' 		=> 'kg',
				'description' 	=> __( ' Weight unit products are measured in.' ),
				'options'		=> array(
					'kg' 		=> __( 'Kilograms', 'woocommerce' ),
					'g' 	=> __( 'Grams', 'woocommerce' )
					)
				),
			'origintown' => array(
				'title' 			=> __('Origin Town', 'woocommerce' ),
				'type' 			=> 'text',
				'description'	=> __( ' The town goods are shipped from' )
				),
			'originpostcode' => array(
				'title' 			=> __('Origin Postcode', 'woocommerce' ),
				'type' 			=> 'text',
				'css'				=> 'width: 50px',
				'description'	=> __( ' The postcode goods are shipped from' )
				),
			'type' => array(
				'title' 		=> __( 'Package Type', 'woocommerce' ),
				'type' 			=> 'select',
				'description' 	=> '',
				'default' 		=> 'Carton'
				),
			'handling_type' => array(
				'title' 		=> __( 'Add handling fee?', 'woocommerce' ),
				'type' 			=> 'select',
				'description' 	=> __( ' Add handling fee as a percentage or flat rate' ),
				'default' 		=> 'none',
				'class'			=> 'ss-handling-type',
				'options'		=> array(
					'none' 		=> __( 'No', 'woocommerce' ),
					'percent' 	=> __( 'Percentage', 'woocommerce' ),
					'flat' 		=> __( 'Flat Rate', 'woocommerce' )
					)
				),
			'handling_amount' => array(
				'title' 			=> __('Handling Amount', 'woocommerce' ),
				'type' 			=> 'text',
				'css'				=> 'width: 50px'
				),
			'assurance' => array(
				'title' 		=> __( 'Transport Assurance', 'woocommerce' ),
				'type' 			=> 'select',
				'description' 	=> __( ' No, always or user-optional' ),
				'default' 		=> 'none',
				'class'			=> 'ss-assurance',
				'options'		=> array(
					'none' 		=> __( 'No', 'woocommerce' ),
					'forced' 	=> __( 'Always', 'woocommerce' ),
					'optional'	=> __( 'Optional', 'woocommerce' )
					)
				),
			'assurance_minimum' => array(
				'title' 			=> __('Assurance Min.', 'woocommerce' ),
				'type' 			=> 'text',
				'description' 	=> __( ' Only apply to cart totals over this amount.' ),
				'css'				=> 'width: 50px'
				),
			'receipted' => array(
				'title' 		=> __( 'Receipted Delivery', 'woocommerce' ),
				'type' 			=> 'select',
				'description' 	=> __( ' No, always or user-optional' ),
				'default' 		=> 'none',
				'options'		=> array(
					'none' 		=> __( 'No', 'woocommerce' ),
					'forced' 	=> __( 'Always', 'woocommerce' ),
					'optional'	=> __( 'Optional', 'woocommerce' )
					)
				),
			'tail_pickup' => array(
				'title' 			=> __('Tail-lift - pickup', 'woocommerce' ),
				'type' 			=> 'text',
				'default'		=> 0,
				'description' 	=> __( ' KG - set to zero for none' ),
				'css'				=> 'width: 50px'
				),
			'tail_delivery' => array(
				'title' 		=> __( 'Tail-lift - delivery', 'woocommerce' ),
				'type' 			=> 'select',
				'description' 	=> __( ' Offer tail-lift option for deliveries over 30kg' ),
				'default' 		=> 'no',
				'options'		=> array(
					'no' 			=> __( 'No', 'woocommerce' ),
					'yes' 		=> __( 'Yes', 'woocommerce' )
					)
				)
			);
foreach( smartSendUtils::$ssPackageTypes as $type )
{
	$this->form_fields['type']['options'][$type] = $type;
}
}

public function admin_options() {
	?>
	<h3>Smart Send</h3>
	<p>Smart Send offers multiple shipping options for items sent <strong>within Australia.</strong></p>
	<?php
	if( !$this->vipusername || !$this->vippassword )
		{?>
	<p style="color: red">Note: You must have a Smart Send VIP account to use this plugin. Visit <a href="https://www.smartsend.com.au/vipClientEnquiry.cfm" target="_blank">Smart Send</a> to register.</span></p>
	<?php
}
?>

<table class="form-table">
	<?php $this->generate_settings_html(); ?>
	</table> <?php
}


public function calculate_shipping( $package )
{
	global $woocommerce;

	$this->_setDestinationVars();

	$testServer = false;
	if( $this->server == 'test' ) $testServer = true;

	$smartSendQuote = new smartSendUtils( $this->vipusername, $this->vippassword, $testServer );

	if( is_null( $this->_shipToTown ) ) $this->_shipToTown = $smartSendQuote->getFirstTown( $this->_shipToPostcode );

		if( !$this->_shipToTown ) return; // Not a valid postcode

		$shipping_errors = array();
		$cartTotal = 0;
		$tailPickup = $this->tail_pickup;
		$tailDelivery = $this->tail_delivery;
		// User optin for tail delivery
		$sessionTailDelivery = ( $_SESSION['ss_option_delivery'] ) ? $_SESSION['ss_option_delivery'] : 'no';

		$cartItems = $package['contents'];

		// Loop through items in the cart, check for items 
		if( sizeof( $cartItems ) > 0 )
		{
			$pickupFlag = $deliveryFlag = false;
			$itemList = array();

			foreach( $cartItems as $item_id => $values )
			{
				$_product = $values['data'];
				$name = $_product->get_title();

				foreach( array( 'weight', 'length', 'width', 'height' ) as $prodAttr )
				{
					$$prodAttr = $_product->$prodAttr;
					if( $$prodAttr <= 0 )
					{
						$shipping_errors[] = "Product <b>$name</b> has no <em>$prodAttr</em> set.";
					}
				}
				if(count( $shipping_errors ))
				{
					$errString = '<b>Shipping calculation error';
					if( count( $shipping_errors ) > 1 ) $errString .= 's';
					$errString .= ':</b><br/><ul><li>' . implode( $shipping_errors, "</li>\n<li>" ) . '</ul>';
					$woocommerce->add_error(__($errString, 'WC_smart_send'));
					return;
				}

				if( $this->weights == 'g' ) $weight = ceil( $weight / 1000 );

				$cartTotal += $_product->price; // @NOTE We COULD call the get_cart_total method, but it's just going to repeat the above ..

				// Now we look at weights to see if tail-lift is called for
				if( $tailDelivery == 'yes' && $weight >= 30 && $sessionTailDelivery == 'yes' ) $deliveryFlag = true;
				if( $tailPickup > 0 && $weight > $tailPickup ) $pickupFlag = true;

				// Add the cart item to the list for API calculations
				$itemList[] = array(
					'Description'   => $this->type,
					'Weight'        => $weight,
					'Depth'         => $width,
					'Length'        => $length,
					'Height'        => $height
					);
			}
		}

		// Did any get through?

		if( count( $itemList ) )
		{
			// Transport assurance
			$assuranceOpt = $this->assurance;
			if( $assuranceOpt == 'forced' || ( $assuranceOpt == 'optional' && ( !empty( $_SESSION['ss_option_assurance'] ) && $_SESSION['ss_option_assurance'] == 'yes' ) ) )
			{
				// Set transport assurance option to the total of the cart
				// This is normally set to the wholesale value of the goods but that data is not available
				if( $cartTotal >= $this->assurance_minimum ) $smartSendQuote->setOptional( 'transportAssurance', $cartTotal );
			}

			// Receipted delivery
			// Either off, always on or optional with user opt-in 'yes'
			$recOpt = $this->receipted;
			if( $recOpt == 'forced' || ( $recOpt == 'optional' && ( !empty( $_SESSION['ss_option_receipted']) && $_SESSION['ss_option_receipted'] == 'yes' ) ))
			{
				$smartSendQuote->setOptional( 'receiptedDelivery', 'true' );
			}

			// Tail-lift options
			$tailLift = 'NONE'; // Default
			if( $pickupFlag ) $tailLift = 'PICKUP';
			if( $deliveryFlag )
			{
				if( $pickupFlag ) $tailLift = 'BOTH';
				else $tailLift = 'DELIVERY';
			}
			$smartSendQuote->setOptional( 'tailLift', $tailLift );

			// Handling Fee
			$feeType = $this->handling_type;
			if( $feeType == 'flat' )
				$this->fee = $this->handling_amount;
			else if( $feeType == 'percent' )
				$this->fee = sprintf( "%1.2f", $cartTotal * $this->handling_amount / 100 );

			$smartSendQuote->setFrom(
				array( $this->originpostcode, $this->origintown, $smartSendQuote->getState( $this->originpostcode) )
				);

			if( empty($this->_shipToState)) $this->_shipToState = $smartSendQuote->getState($this->_shipToPostcode );

			$smartSendQuote->setTo(
				array( $this->_shipToPostcode, $this->_shipToTown, $this->_shipToState )
				);

			foreach( $itemList as $item )
				$smartSendQuote->addItem( $item );

			$quoteResult = $smartSendQuote->getQuote();
			
			if( $quoteResult->ObtainQuoteResult->StatusCode != 0 )
			{
				$woocommerce->add_error( __('Shipping calculation error: ' . $quoteResult->ObtainQuoteResult->StatusMessages->string, 'WC_smart_send' ) );
				return;
			}
			$quotes = $quoteResult->ObtainQuoteResult->Quotes->Quote;

			if( !is_array( $quotes ) ) $quotes[0] = $quotes;

			$r = 0;
			foreach( $quotes as $quote )
			{
				if( empty($quote->TotalPrice) ) continue;
				$rateTotal = $quote->TotalPrice + $this->fee;
				$r++;
				$rate = array(
					'id'			=> 'smart_send_'.$r,
					'label'		=> $this->denoted . ' - '. $quote->TransitDescription,
					'cost'		=> $rateTotal,
					'calc_tax'	=> 'per_order'
					);
				$this->add_rate( $rate );
			}

		}
	}

	/**
	* is_available function.
	*
	* @access public
	* @param mixed $package
	* @return bool
	*/
	public function is_available( $package ) 
	{
		global $woocommerce;

		if( $this->enabled == 'no' ) return false;

		if( $package['destination']['country'] != 'AU' ) return false;

		if( empty($this->vipusername) || empty( $this->vippassword ) || empty( $this->origintown ) || empty( $this->originpostcode )  )
			return false;

		return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', true );
	}

	/**
	 * Check if cart has anything over a certain weight
	 * 
	 * @access protected
	 * @param integer $maxWeight Return true if anything this weight or over
	 * @return bool
	 */
	protected function anyOverWeight( $maxWeight )
	{
		global $woocommerce;
		$cart = $woocommerce->cart->get_cart();
		foreach ( $cart as $item_id => $values ) {
			$_product = $values['data'];
			if( $_product->weight >= $this->tailMin ) return true;
		}
		return false;
	}
}


// add_action( 'woocommerce_checkout_after_customer_details', 'addSmartSendUserShippingOptions' );

function add_smart_send_method( $methods )
{
	$methods[] = 'WC_smart_send';
	return $methods;
}
add_filter('woocommerce_shipping_methods', 'add_smart_send_method' );

function smartSendAjaxSetExtras()
{
	$settings = get_option( 'woocommerce_smart_send_settings' );
	if( !wp_verify_nonce( $_POST['ssNonce'], 'ss_noncical') ) exit( 'Illegal Ajax action' );
	$status = $_POST['ssStatus'];
	if( !in_array( $_POST['ssStatus'], array( 'yes', 'no' ) ) ) exit( 'Invalid value' );
	$option = $_POST['ssOption'];
	if( !in_array( $option, array( 'assurance', 'delivery', 'receipted' ) ) ) exit( 'Invalid option' );

	if(
		( $option == 'receipted' && $settings['receipted'] != 'optional' ) ||
		( $option == 'assurance' && $settings['assurance'] != 'optional' ) ||
		( $option == 'delivery' && $settings['tail_delivery'] != 'yes' ) )
			exit( 'You cannot set that.');

	// Enough sanity, set the session var and return the value
	$_SESSION["ss_option_$option"] = $status;
	exit( $_SESSION["ss_option_$option"] );
}

// Called by AJAX when options checkboxes checked
add_action( 'wp_ajax_smart_send_set_xtras', 'smartSendAjaxSetExtras' );
add_action( 'wp_ajax_nopriv_smart_send_set_xtras', 'smartSendAjaxSetExtras' );


// Insert extra Smart Send options on to checkout page:
// 	- Receipted delivery
// 	- Tail-lift assistance
// 	- Transport assurance
function addSmartSendUserShippingOptions()
{
	$settings = get_option( 'woocommerce_smart_send_settings' );

	if( !( $settings['receipted'] == 'optional' || $settings['assurance'] == 'optional' || $settingss['tail_delivery'] == 'yes' ) ) return;
	?>
	<div class="col2-set" id="smart_send_shipping_options">
	<hr/>
		<h4>Other shipping options</h4>
		<?php
		if( $settings['receipted'] == 'optional' )
		{
			?>
			<div class="col-1">
				<label for="smart_send_receipted">
					<input type="checkbox" class="smart_send_option" id="smart_send_receipted" data-option="receipted" <?php checked( $_SESSION['ss_option_receipted'] ); ?>/>
					I require receipted delivery<br>
         <small>A signature will be required upon delivery</small></label>
			</div>
			<?php
		}
		if( $settings['tail_delivery'] == 'yes' )
		{
			?>
			<div class="col-1">
				<label for="smart_send_lift_delivery">
					<input type="checkbox" class="smart_send_option" id="smart_send_lift_delivery" data-option="delivery" <?php checked( $_SESSION['ss_option_delivery'] ); ?>/>
					I require tail-lift delivery<br>
         <small>If any items are <?php echo WC_smart_send::$tailMin; ?>kg or over, extra assistance will be provided</small></label>
			</div>
			<?php
		}
		if( $settings['assurance'] == 'optional' )
		{
			?>
			<div class="col-1">
				<label for="smart_send_assurance">
					<input type="checkbox" class="smart_send_option" id="smart_send_assurance" data-option="assurance" <?php checked( $_SESSION['ss_option_assurance'] ); ?>/>
					I require transport assurance<br>
         <small>Insure items against damage for a small additional fee</small></label>
			</div>
			<?php
		}

		// Session variables:
		// smartSendAssurance, smartSendReceipted, SmartSendLift
		?>
	</div>
		<br clear="left"><br>
	<script type="text/javascript">
	(function($) {
	   var ssParams = {
	           action:     'smart_send_set_xtras',
	           ssNonce:    '<?php echo wp_create_nonce('ss_noncical'); ?>'
	       },
	       jqhxr;

	   $('.smart_send_option').click( function()
	   {
			console.log( $(this).data('option'));
			var $opDiv = $(this).closest('div');
			var _self = $(this);
			$opDiv.block({
				message: null,
				overlayCSS: {
					background: '#fff url(' + woocommerce_params.ajax_loader_url + ') no-repeat 250px',
					opacity: 0.6
				}
			});
			ssParams.ssOption = $(this).attr('data-option');
			ssParams.ssStatus = ( $(this).is(':checked') ) ? 'yes' : 'no';
			jqxhr = $.ajax({
				type: 'POST',
				url: 	woocommerce_params.ajax_url,
				data: ssParams,
				success: function( resp )
				{
					$opDiv.unblock();
					if( resp == 'yes' ) _self.attr('checked', 'checked');
					else _self.removeAttr('checked');
					$('body').trigger('update_checkout');
				}
			});
		});
	})(jQuery);
	</script>
	<?php
}

add_action( 'woocommerce_checkout_after_customer_details', 'addSmartSendUserShippingOptions' );
<?php

/**
 *
 */
function woocommerce_smart_send_shipping_init()
{
	class WC_Smart_Send extends WC_Shipping_Method {

		protected $_shipToTown;
		protected $_shipToPostcode;
		protected $_shipToState;
		protected $_shipToCountry;

		protected $_rememberTheError;
		protected $_rememberTheErrorType;

		// Minimum weight at which tail-lift assistance is triggered
		public static $tailMin = 30;
		private static $ssVersion = 1500;

		function __construct() {
			$this->id                 = 'smart_send';
			$this->method_title       = __( 'Smart Send', 'smart_send' );
			$this->method_description = __( 'Smart Send Australian Shipping', 'smart_send');
			$this->title              = __( 'Smart Send', 'smart_send' );
			$this->tax_status         = 'none';  // Smart Send returns tax

			add_action( 'woocommerce_update_options_shipping_smart_send', array( $this, 'process_admin_options' ) );

			$this->init();
		}

		function init()
		{
			global $wpdb;

			$this->init_form_fields();

			// Load the settings.
			$this->init_settings(); // Define user set variables
			foreach ( $this->settings as $k => $v )
				$this->$k = $v;

			$this->vipusername = html_entity_decode($this->vipusername);
			$this->vippassword = html_entity_decode($this->vippassword);

			// Check for upgrades - smart_send_updates option returns the previous version
			$SSprev = get_option('smart_send_updates');
			if ( self::$ssVersion > $SSprev || empty($SSprev))
			{
				$this->_smartSendOnInstallUpgrade();
				update_option('smart_send_updates', self::$ssVersion );
			}

			// Delete all the wc_ship transient scum, you aren't wanted around here, move along
			$transients = $wpdb->get_col(
				"SELECT option_name FROM $wpdb->options
				WHERE
					option_name LIKE '_transient_wc_ship%'"
			);

			if (count($transients))
			{
				foreach( $transients as $tr)
				{
					$hash = substr( $tr, 11);
					delete_transient($hash);
				}
			}

		}

		// Set the destination town and postcode to use. If not Australia, disable the plugin
		protected function _setDestinationVars( $postArray ) {
			smart_send_debug_log( 'setdestinationvars', $postArray );

			$this->_shipToCountry = 'AU';

			if ( !empty( $postArray['s_state'] ) ) {
				$this->_shipToState    = $postArray['s_state'];
				$this->_shipToPostcode = $postArray['s_postcode'];
				$this->_shipToTown     = $postArray['s_city'];
			}
			// If the s_state etc isn't populated, check for ship to different address flag and use shipping listed
			elseif ( !empty($postArray['ship_to_different_address']) && $postArray['ship_to_different_address'] == 1 )
			{
				$this->_shipToState    = $postArray['shipping_state'];
				$this->_shipToPostcode = $postArray['shipping_postcode'];
				$this->_shipToTown     = $postArray['shipping_city'];
			}
			elseif ( !empty( $postArray['state'] ) ) {
				$this->_shipToTown     = $postArray['city'];
				$this->_shipToState    = $postArray['state'];
				$this->_shipToPostcode = $postArray['postcode'];
			}
			// This bit is triggered if request is coming from the shipping calculator on the cart page
			elseif ( isset( $postArray['calc_shipping'] ) && $postArray['calc_shipping'] == 1 ) {
				$this->_shipToTown     = $postArray['calc_shipping_city'];
				$this->_shipToState    = $postArray['calc_shipping_state'];
				$this->_shipToPostcode = $postArray['calc_shipping_postcode'];
			}
			elseif ( isset( $_POST['post_data'] ) ) {
				$postDataString = urldecode( $postArray['post_data'] );
				foreach ( explode( '&', $postDataString ) as $var ) {
					list( $k, $v ) = explode( '=', $var );
					$postData[ $k ] = $v;
				}
				if ( isset( $postData['shiptobilling'] ) && $postData['shiptobilling'] == 1 ) {
					$postPrefix = 'billing';
				} else {
					$postPrefix = 'shipping';
				}

				$this->_shipToPostcode = $postData[ $postPrefix . '_postcode' ];
				$this->_shipToTown     = $postData[ $postPrefix . '_city' ];
				$this->_shipToState    = $postData[ $postPrefix . '_state' ];
			}
			else {
				global $woocommerce;
				$this->_shipToCountry  = $woocommerce->customer->get_shipping_country();
				$this->_shipToState    = $woocommerce->customer->get_shipping_state();
				$this->_shipToPostcode = $woocommerce->customer->get_shipping_postcode();
				$this->_shipToTown     = $woocommerce->customer->get_shipping_city();
			}
		}

		public function init_form_fields() {
			$this->form_fields = array(

				'enabled'           => array(
					'type'    => 'checkbox',
					'label'   => __( ' Enable Smart Send shipping', 'woocommerce' ),
					'default' => 'no',
				),
				'denoted'           => array(
					'title'       => __( 'Method Title', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( ' Displayed next to each shipping option/price point.', 'woocommerce' ),
					'default'     => __( 'Courier', 'woocommerce' ),
					'desc_tip'    => true
				),
				'vipusername'       => array(
					'title'       => __( 'VIP Username', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( ' VIP username issued by Smart Send' ),
					'desc_tip'    => true
				),
				'vippassword'       => array(
					'title'       => __( 'VIP Password', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( ' VIP password issued by Smart Send' ),
					'desc_tip'    => true
				),
				'show_results'      => array(
					'title'       => __( 'Display Results', 'woocommerce' ),
					'type'        => 'select',
					'description' => __( ' Select which shipping solutions to display.' ),
					'default'     => 'all',
					'options'     => array(
						'all'   => __( 'Show All Results', 'woocommerce' ),
						'cheap' => __( 'Show Only Cheapest', 'woocommerce' ),
						'fast'  => __( 'Show Only Fastest', 'woocommerce' )
					),
					'class'       => 'chosen-select',
					'desc_tip'    => true
				),
				'weights'           => array(
					'title'       => __( 'Weight Units', 'woocommerce' ),
					'type'        => 'select',
					'default'     => 'kg',
					'description' => __( ' Weight unit products are measured in.' ),
					'options'     => array(
						'kg' => __( 'Kilograms', 'woocommerce' ),
						'g'  => __( 'Grams', 'woocommerce' )
					),
					'class'       => 'chosen-select',
					'desc_tip'    => true
				),
				'usetestserver'     => array(
					'type'        => 'checkbox',
					'label'       => __( ' Force use of test API', 'woocommerce' ),
					'default'     => 'no',
					'description' => __( "Force plugin to use the test API server. Will use this anyway if the username starts with 'test@'" ),
					'desc_tip'    => true
				),
				'pickupdetails'     => array(
					'title'       => __( 'Company Details', 'woocommerce' ),
					'type'        => 'title',
					'default'     => '',
					'description' => __( 'Where shipping will be collected' ),
					'desc_tip'    => true
				),
				'companyname'       => array(
					'title'       => __( 'Company Name', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Required if the merchant is a company.' )
				),/*
				'couriermessage'        => array(
					'title'       => __( 'Message for Courier', 'woocommerce' ),
					'type'        => 'text',
					'placeholder' => __( 'Optional courier notes', 'woocommerce' ),
					'custom_attributes' => array( 'maxlength' => '24', 'title' => 'Message cannot be longer than 24 characters' ),
					'description' => __( ' Enter a standard note for pickups - eg, "Not at home, please collect package from rear door"' )
				),*/
				'merchantcontact'   => array(
					'title'       => __( 'Contact', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Full name of the person to contact' ),
					'desc_tip'    => true
				),
				'merchantphone'     => array(
					'title'             => __( 'Contact Phone', 'woocommerce' ),
					'type'              => 'text',
					'description'       => __( 'A phone number for the contact person, must be 10 digits only' ),
					'custom_attributes' => array( 'pattern' => '\d{10}', 'title' => 'Phone must be exactly 10 digits' ),
					'desc_tip'          => true
				),
				'merchantemail'     => array(
					'title'             => __( 'Contact Email', 'woocommerce' ),
					'type'              => 'email',
					'description'       => __( 'The email address for the contact' ),
					'custom_attributes' => array( 'title' => 'Phone must be exactly 10 digits' ),
					'desc_tip'          => true
				),
				'originaddress'     => array(
					'title'       => __( 'Origin Address', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Street address goods are shipped from' ),
					'desc_tip'    => true
				),
				'origintown'        => array(
					'title'       => __( 'Origin Town', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( ' The town goods are shipped from' ),
					'desc_tip'    => true
				),
				'originpostcode'    => array(
					'title'       => __( 'Origin Postcode', 'woocommerce' ),
					'type'        => 'text',
					'css'         => 'width: 50px',
					'description' => __( 'The postcode goods are shipped from' ),
					'desc_tip'    => true
				),
				'autofulfill'       => array(
					'title'       => __( 'Auto-fulfilment', 'woocommerce' ),
					'enabled'     => __( 'Enable/Disable', 'woocommerce' ),
					'type'        => 'checkbox',
					'label'       => __( ' Enable auto-fulfilment', 'woocommerce' ),
					'default'     => 'no',
					'description' => __( 'Order shipping after successful checkout by customer' ),
					'desc_tip'    => true
				),
				'pickuptime'        => array(
					'title'       => __( 'Pickup Window', 'woocommerce' ),
					'type'        => 'select',
					'default'     => 'between 12pm - 4pm',
					'description' => __( 'The time you would like the courier to pick up items' ),
					'options'     => array(
						'between 12pm - 4pm'                                 => __( 'Between 12pm and 4pm', 'woocommerce' ),
						'between 1pm - 5pm'                                  => __( 'Between 1pm and 5pm', 'woocommerce' ),
						'Within 30 minutes of Booking during business hours' => __( 'Within 30 minutes of Booking during business hours', 'woocommerce' )
					),
					'class'       => 'chosen-select',
					'desc_tip'    => true
				),
/*				'ship_delay'   => array(
					'title'    => __( 'Shipping Delay', 'woocommerce' ),
					'type'     => 'text',
					'css'      => 'width: 50px',
					'default'     => '0',
					'description' => __( "Number of days until shipping ordered. '0' for ASAP." ),
					'desc_tip' => true
				),*/
				'type'              => array(
					'title'       => __( 'Package Type', 'woocommerce' ),
					'type'        => 'select',
					'description' => 'Default shipping class, unless specified in product',
					'default'     => 'Carton',
					'class'       => 'chosen-select',
					'desc_tip'    => true
				),
				'handling_type'     => array(
					'title'       => __( 'Add handling fee?', 'woocommerce' ),
					'type'        => 'select',
					'description' => __( ' Add a handling fee as a percentage of the cart total, or a flat rate' ),
					'default'     => 'none',
					'class'       => 'ss-handling-type chosen-select',
					'options'     => array(
						'none'    => __( 'No', 'woocommerce' ),
						'percent' => __( 'Percentage', 'woocommerce' ),
						'flat'    => __( 'Flat Rate', 'woocommerce' )
					),
					'desc_tip'    => true
				),
				'handling_amount'   => array(
					'title'    => __( 'Handling Amount', 'woocommerce' ),
					'type'     => 'text',
					'css'      => 'width: 50px',
					'desc_tip' => true
				),
				'assurance'         => array(
					'title'       => __( 'Transport Assurance', 'woocommerce' ),
					'type'        => 'select',
					'description' => __( ' No, always or user-optional' ),
					'default'     => 'none',
					'class'       => 'ss-assurance chosen-select',
					'options'     => array(
						'none'     => __( 'No', 'woocommerce' ),
						'forced'   => __( 'Always', 'woocommerce' ),
						'optional' => __( 'Optional', 'woocommerce' )
					),
					'desc_tip'    => true
				),
				'assurance_minimum' => array(
					'title'       => __( 'Assurance Min.', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( ' Only apply to cart totals over this amount.' ),
					'css'         => 'width: 50px',
					'desc_tip'    => true
				),
				'tail_pickup'       => array(
					'title'       => __( 'Tail-lift - pickup', 'woocommerce' ),
					'type'        => 'text',
					'default'     => 0,
					'description' => __( ' KG - set to zero for none' ),
					'css'         => 'width: 50px',
					'desc_tip'    => true
				),
				'recipienttitle'    => array(
					'title'       => __( 'Recipient Related', 'woocommerce' ),
					'type'        => 'title',
					'default'     => '',
					'description' => __( 'Options that affect the recipient' ),
					'desc_tip'    => true
				),
				'receipted'         => array(
					'title'       => __( 'Receipted Delivery', 'woocommerce' ),
					'type'        => 'select',
					'description' => __( 'Specify whether recipient should sign for deliveries. No, always or user-optional. May increase quote prices.' ),
					'default'     => 'none',
					'options'     => array(
						'none'     => __( 'No', 'woocommerce' ),
						'forced'   => __( 'Always', 'woocommerce' ),
						'optional' => __( 'Optional', 'woocommerce' )
					),
					'class'       => 'chosen-select',
					'desc_tip'    => true
				),
				'emailrecipient'    => array(
					'title'    => __( 'Recipient notification', 'woocommerce' ),
					'type'     => 'checkbox',
					'label'    => __( 'Email tracking details to recipient', 'woocommerce' ),
					'default'  => 'no',
					'desc_tip' => true
				),
				'tail_delivery'     => array(
					'title'       => __( 'Tail-lift (delivery)', 'woocommerce' ),
					'type'        => 'checkbox',
					'default'     => 'no',
					'label'    => __( 'Give buyers the option to request a tail-lift truck deliver the items.', 'woocommerce' )
				)
			);
			foreach ( smartSendQuote::$ssPackageTypes as $type ) {
				$this->form_fields['type']['options'][ $type ] = $type;
			}
		}

		public function admin_options() {
			?>
			<h3>Smart Send</h3>
			<p>Smart Send offers multiple shipping options for items sent <strong>within Australia.</strong></p>
			<?php
			if ( empty( $this->vipusername ) || empty( $this->vippassword ) ) {
				?>
				<p style="color: red">Note: You must have a Smart Send VIP account to use this plugin. Visit <a
						href="https://www.smartsend.com.au/vipClientEnquiry.cfm" target="_blank">Smart Send</a> to
					register.</span></p>
			<?php
			}
			if ( ! extension_loaded( 'soap' ) ) {
				?>
				<p style="color: red">Note: the <b>SOAP</b> extension must be loaded for Smart Send shipping to work.
					Contact your hosting company, ISP or systems administrator and request that the soap extension be
					activated.</span></p>
			<?php
			}
			?>

			<table class="form-table">
				<?php $this->generate_settings_html(); ?>
			</table> <?php
		}


		public function calculate_shipping( $package ) {

			$this->_setDestinationVars( $_POST );

			$useTest = ( empty($this->usetestserver) || $this->usetestserver == 'no') ? false : true;

			$smartSendQuote = new smartSendQuote( $this->vipusername, $this->vippassword, $useTest );

			if ( empty( $this->_shipToTown ) )
				$this->_shipToTown = $smartSendQuote->getFirstTown( $this->_shipToPostcode );

			if ( ! $this->_shipToTown )
				return; // Not a valid postcode

			$shipping_errors = array();
			$cartTotal       = 0;
			$assuranceTotal  = 0;
			$tailPickup      = $this->tail_pickup; // Items over this weight will trigger 'tail lift' on pickup

			$cartItems = $package['contents'];

			// Loop through items in the cart, check for items
			if ( sizeof( $cartItems ) > 0 ) {
				$pickupFlag = $deliveryFlag = false;
				$itemList   = array();

				// First, we check if this is blank (based on cart items), standard, reduced-rate or zero-rate
				$shippingTaxClass = get_option('woocommerce_shipping_tax_class');

				if ($shippingTaxClass != 'none' )
				{
					$_tax = new WC_Tax();
					$_taxRates = $_tax->get_shipping_tax_rates($shippingTaxClass);
					$_taxKeys = array_keys($_taxRates);
					$useTaxId = $_taxKeys[0];
				}
				else
					$useTaxId = 0;

				foreach ( $cartItems as $item_id => $values ) {
					$_product = $values['data'];
					$name     = $_product->get_title();

					$prodClass = $_product->get_shipping_class();

					$shippingClass = empty( $prodClass ) ? $this->type : get_term_by('slug', $prodClass, 'product_shipping_class')->name;

					// Check if valid shipping class
					if (!in_array( $shippingClass, smartSendQuote::$ssPackageTypes))
						$shippingClass = $this->settings['type'];

					$weight = $length = $width = $height = 0;

					// Check if variation
					if( $_product->product_type == 'variation' )
					{
						$variationMeta = get_post_meta($_product->parent->id);
					}
					else {
						$variationMeta = false;
					}

					$costPrice = get_post_meta($_product->id,'SScostPrice');

					if ($costPrice)
						$costPrice = $costPrice[0];

					foreach ( array( 'weight', 'length', 'width', 'height' ) as $prodAttr )
					{
						// First, see if it has own settings
						$$prodAttr = $_product->$prodAttr;

						// If dimension missing, and it is a variation, see if parent product has the setting
						if ( !$$prodAttr && $variationMeta )
							$$prodAttr = $variationMeta['_'.$prodAttr][0];

                        // If it's missing this attribute, and it's not a fixed price satchel (we enforce dimensions for those)
                        // Show an error
						if ( ($$prodAttr <= 0 || !$$prodAttr) && !preg_match( '/^\d.*satchel$/', $shippingClass)) {
							$shipping_errors[] = "Product <b>$name</b> has no <em>$prodAttr</em> set.";
						}
					}

					if ( count( $shipping_errors ) ) {
						$errString = '<b>Shipping calculation error';
						if ( count( $shipping_errors ) > 1 )
							$errString .= 's';
						$errString .= ':</b><br/><ul><li>' . implode( $shipping_errors, "</li>\n<li>" ) . '</ul>';

						$this->_shippingNotice($errString);
						return;
					}

					if ( $this->weights == 'g' )
						$weight = ceil( $weight / 1000 );

					$cartTotal += $_product->price;

					// Total that transport assurance (if required) will be calculated on
					$assuranceTotal += $costPrice ? $costPrice : $_product->price;

					// Now we look at weights to see if tail-lift is called for
					if ( $tailPickup > 0 && $weight > $tailPickup )
						$pickupFlag = true;


					// Add the cart item/s to the list for API calculations
					foreach ( range( 1, $values['quantity'] ) as $blah )
						$itemList[] = array(
							'Description' => $shippingClass,
							'Weight'      => $weight,
							'Depth'       => $width,
							'Length'      => $length,
							'Height'      => $height
						);
				}
			}

			// Did any get through?

			if ( count( $itemList ) ) {
				// Transport assurance
				$assuranceOpt = $this->assurance;
				$sessionAssurance = WC()->session->get('ss_option_assurance');
				if ( $assuranceOpt == 'forced' || ( $assuranceOpt == 'optional' && ( ! empty( $sessionAssurance ) && $sessionAssurance == 'yes' ) ) ) {
					// Set transport assurance option to the total of the cart
					// This is normally set to the wholesale value of the goods but that data is not available
					if ( $assuranceTotal >= $this->assurance_minimum )
						$smartSendQuote->setOptional( 'TransportAssurance', $assuranceTotal );
				}

				// Receipted delivery
				// Either off, always on or optional with user opt-in 'yes'
				$recOpt = $this->receipted;
				$sessionRxd = WC()->session->get('ss_option_receipted');
				if ( $recOpt == 'forced' || ( $recOpt == 'optional' && ( ! empty( $sessionRxd ) && $sessionRxd == 'yes' ) ) ) {
					$smartSendQuote->setOptional( 'ReceiptedDelivery', 1 );
				}

				// User optin for tail delivery
				$sessionDelivery = WC()->session->get('ss_option_delivery');
				$sessionTailDelivery = ! empty($sessionDelivery) ? $sessionDelivery : 'no';

				// Tail-lift options
				$tailLift = 'NONE'; // Default
				if ( $pickupFlag )
					$tailLift = 'PICKUP';

				if ($sessionTailDelivery == 'yes')
				{
					if ( $pickupFlag )
						$tailLift = 'BOTH';
					else
						$tailLift = 'DELIVERY';
				}
				$smartSendQuote->setOptional( 'TailLift', $tailLift );

				// Handling Fee
				$feeType = $this->handling_type;
				if ( $feeType == 'flat' )
					$this->fee = $this->handling_amount;
				else if ( $feeType == 'percent' )
					$this->fee = sprintf( "%1.2f", $cartTotal * $this->handling_amount / 100 );

				if (empty($this->_shipToPostcode) )
				{
					$this->_shippingNotice('Please enter destination town, postcode and state for an accurate shipping quote.', 'notice');
					return;
				}

				if ( empty( $this->_shipToState ) ) {
					$this->_shipToState = $smartSendQuote->getState( $this->_shipToPostcode );
				}

				// Check destination is all hunky dory
				$toCheck = $smartSendQuote->setTo(
					array( $this->_shipToPostcode, $this->_shipToTown, $this->_shipToState )
				);

				if ($toCheck !== true)
				{
					$this->_shippingNotice($toCheck, 'error');
					return;
				}

				$smartSendQuote->setFrom(
					array(
						$this->originpostcode,
						$this->origintown,
						$smartSendQuote->getState( $this->originpostcode )
					)
				);

				foreach ( $itemList as $item )
					$smartSendQuote->addItem( $item );

				// Check/set OWN transient
				if (!$quoteResult = $this->_selfTransient($smartSendQuote))
				{
					$quoteResult = $smartSendQuote->getQuote();
					$this->_selfTransient($smartSendQuote, $quoteResult);
				}
				else
					$smartSendQuote->setLastResult($quoteResult);

				$niceResult = $smartSendQuote->niceResult();

				// Error returned from quote attempt
				if ($niceResult[0] == 'error')
				{
					$this->_shippingNotice($niceResult[1], 'error');
					return;
				}

				if (is_array($niceResult))
				{
					if ( $this->show_results == 'cheap' )
						$quotes[0] = array_shift( $niceResult );
					else if ( $this->show_results == 'fast' )
						$quotes[0] = array_pop( $niceResult );
					else
						$quotes = $niceResult;

					// We need to cache based on params, so we set rates keys with some that change
					$r = 0;
					foreach ( $quotes as $quote ) {
						if ( empty( $quote->TotalPrice ) )
							continue;
						$rateTotal = $quote->TotalPrice + $this->fee;
						$r ++;
						$rate = array(
							'id'       => 'smart_send_'.$quote->PriceID.'_'. $r,
							'label'    => $this->denoted.' - '.$quote->TransitDescription,
							'cost'     => $rateTotal,
							'calc_tax' => 'per_order'
						);

						if ($useTaxId)
						{
							$rate['taxes'] = array( $useTaxId => $quote->TotalGST);
							$rate['cost'] = $rateTotal - $quote->TotalGST;
						}
						$this->add_rate( $rate );
					}
				}

			}
		}

		/**
		 * Because WooCommerce can't cache on all the factors we use, we do our own caching.
		 *
		 * @param object $qObj  - seed genesis
		 * @param array $quote - quote data
		 *
		 * @return bool
		 */
		protected function _selfTransient($qObj, $quote = null)
		{
			// Create a hash key from unique request data
			$hashKey = 'ss_quote_'.md5(json_encode($qObj->_params['request']));

			if (!empty($quote))
			{
				set_transient($hashKey, $quote, 1200);
				// Get existing
				$transients = WC()->session->get('ssTransients') ? WC()->session->get('ssTransients') : array();
				// Add one
				if (empty($transients))
					$transients = array();

				$transients[] = $hashKey;
				WC()->session->set('ssTransients', $transients);
				return true;
			}

			if ( !( $return = get_transient($hashKey)))
				return false;
			else
				return $return;
		}

		/**
		 * is_available function. Returns true if all is good and Smart Send shipping is available
		 *
		 * @access public
		 *
		 * @param mixed $package
		 *
		 * @return bool
		 */
		public function is_available( $package ) {
			smart_send_debug_log( 'available', $package );

			if ( $this->enabled == 'no' )
				return false;

			$this->availability = true;

			if ( $package['destination']['country'] != 'AU' )
				return false;

			if ( empty( $this->vipusername ) || empty( $this->vippassword ) || empty( $this->origintown ) || empty( $this->originpostcode ) )
				return false;

			return apply_filters( 'woocommerce_shipping_' . $this->id . '_is_available', true );
		}


		/**
		 * Run on install or upgrade. Checks for and adds Smart Send shipping classes.
		 */
		private function _smartSendOnInstallUpgrade()
		{
			foreach( smartSendQuote::$ssPackageTypes as $type )
			{
				$t = get_term_by( 'name', $type, 'product_shipping_class' );

				if (!$t) // Add shipping class to taxonomies
				{
					wp_insert_term(
						$type,
						'product_shipping_class',
						array(
							'slug' => strtolower( preg_replace( '/\W/', '_', $type ) ),
							'description' => 'Delivered by '.$type
						)
					);
				}
				// TODO: Manually add new options as defaults
			}
		}

		/**
		 * CLEVER way to get notices in to the right place ..
		 *
		 * @param string $str
		 * @param string $type
		 */
		protected function _shippingNotice($str, $type='error')
		{
			if (is_page('cart'))
				return;
			// Check if this is by AJAX
			if (defined('DOING_AJAX') && DOING_AJAX)
			{
				// AJAX huh? Notices won't work, inject directly in to returned html
				add_action('woocommerce_review_order_before_shipping', array( $this,'oh_no_a_shipping_error'));
				$this->_rememberTheError = $str;
				$this->_rememberTheErrorType = $type;
				return;
			}
			if (current_user_can( 'manage_options' ))
				wc_add_notice( __( 'Shipping calculation '.$type.': ' . $str, 'WC_Smart_Send' ), $type );
		}

		/**
		 *  If we get an error in AJAX town, invade the HTML returned
		 */
		public function oh_no_a_shipping_error() {
			echo '<div class="woocommerce-'.$this->_rememberTheErrorType.'">Shipping calculation '.$this->_rememberTheErrorType.': ' . $this->_rememberTheError.'</div>';
		}
	}
}

add_action('woocommerce_shipping_init', 'woocommerce_smart_send_shipping_init');

function add_smart_send_method( $methods )
{
	$methods[] = 'WC_Smart_Send';
	return $methods;
}

// Enqueue jQuery and jQuery UI stuff
add_filter('woocommerce_shipping_methods', 'add_smart_send_method' );


add_action('wp_enqueue_scripts', 'smartsend_add_frontend_scripts');

function smartsend_add_frontend_scripts()
{
	wp_enqueue_script('jquery');
	wp_enqueue_script('jquery-ui-core');
	wp_enqueue_script('jquery-ui-autocomplete');

	wp_register_style('ss-jquery-ui-style', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.10.3/themes/ui-lightness/jquery-ui.css', false, null);
	wp_enqueue_style('ss-jquery-ui-style');
}

add_action('admin_enqueue_scripts', 'smartsend_enqueue_admin');

function smartsend_enqueue_admin()
{
	global $woocommerce;
	$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
	wp_enqueue_script( 'prettyPhoto', $woocommerce->plugin_url() . '/assets/js/prettyPhoto/jquery.prettyPhoto' . $suffix . '.js', array( 'jquery' ), $woocommerce->version, false );
	wp_enqueue_script( 'prettyPhoto-init', $woocommerce->plugin_url() . '/assets/js/prettyPhoto/jquery.prettyPhoto.init' . $suffix . '.js', array( 'jquery' ), $woocommerce->version, false );

	wp_register_style('woocommerce_prettyPhoto_css', $woocommerce->plugin_url() . '/assets/css/prettyPhoto.css');
	wp_enqueue_style( 'woocommerce_prettyPhoto_css');
}


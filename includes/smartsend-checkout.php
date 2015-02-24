<?php


/**
 *  Add extra Smart Send shipping options on the checkout page
 */

add_action( 'woocommerce_checkout_after_customer_details', 'addSmartSendUserShippingOptions' );

function addSmartSendUserShippingOptions()
{
	$settings = get_option( 'woocommerce_smart_send_settings' );

	if( !( $settings['receipted'] == 'optional' || $settings['assurance'] == 'optional' || $settings['tail_delivery'] == 'yes' ) ) return;
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
					<input type="checkbox" class="smart_send_option" id="smart_send_receipted" data-option="receipted" <?php checked(WC()->session->get('ss_option_receipted'), 'yes'); ?>/>
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
					<input type="checkbox" class="smart_send_option" id="smart_send_lift_delivery" data-option="delivery" <?php checked( WC()->session->get('ss_option_delivery'), 'yes'); ?>/>
					I require tail-lift delivery<br>
					<small>Additional assistance will be provided at delivery</small></label>
			</div>
		<?php
		}
		if( $settings['assurance'] == 'optional' )
		{
			?>
			<div class="col-1">
				<label for="smart_send_assurance">
					<input type="checkbox" class="smart_send_option" id="smart_send_assurance" data-option="assurance" <?php checked( WC()->session->get('ss_option_assurance'), 'yes' ); ?>/>
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

// Called by AJAX when options checkboxes checked
add_action( 'wp_ajax_smart_send_set_xtras', 'smartSendCheckoutAjax' );
add_action( 'wp_ajax_nopriv_smart_send_set_xtras', 'smartSendCheckoutAjax' );

function smartSendCheckoutAjax()
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
	WC()->session->set('ss_option_'.$option, $status);
	exit( $status );
}


// Postcode sets suburb dropdown on town, sets state

add_filter( 'woocommerce_billing_fields', 'wc_smartsend_checkout_fields', 10, 1 );
add_filter( 'woocommerce_shipping_fields', 'wc_smartsend_checkout_fields', 10, 1 );

function wc_smartsend_checkout_fields( $address_fields )
{
	// Put billing city after postcode
	$newFields = array();
	foreach( $address_fields as $k => $v )
	{
		if ($k == 'billing_city' || $k == 'shipping_city')
		{
			$temp1 = $k;
			$temp2 = $v;
			continue;
		}
		$newFields[$k] = $v;

		if ($k == 'billing_postcode' || $k == 'shipping_postcode')
		{
			$newFields[ $temp1 ] = $temp2;
			$newFields[$k]['custom_attributes']['pattern'] = '\d{4}';
			$newFields[$k]['custom_attributes']['title'] = 'Must be a 4 digit number';
			$newFields[$k]['maxlength'] = '4';
		}

		// Set 30 char limit on these
		preg_match( '/^(billing|shipping)_(.*)/', $k, $bits);
		if ( in_array($bits[2], array('company', 'address_1', 'address_2')))
		{
			$newFields[$k]['custom_attributes']['maxlength'] = '30';
		}

		if ($bits[2] == 'email')
		{
			$newFields[$k]['maxlength'] = '100';
		}
	}
	if ( isset($newFields['billing_phone']) )
	{
		$newFields['billing_phone']['custom_attributes']['pattern'] = '\d{10}';
		$newFields['billing_phone']['custom_attributes']['title'] = 'Must be a 10-digit phone number';
		$newFields[$k]['maxlength'] = '10';
//		$newFields[$k]['type'] = 'phone';
	}

	return $newFields;
}

add_filter('woocommerce_shipping_calculator_enable_city', 'smartSendEnableCity');

function smartSendEnableCity()
{
	return true;
}

// Checkout fields filter for phone number
//add_filter('woocommerce_form_field_phone', 'wc_smartsend_form_phone', 10, 3);

function wc_smartsend_form_phone( $value, $key, $args)
{
	$custom_attributes = $args['custom_attributes'];
	$required = $args['required'];
	$after = '';

	$field = '<p class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) .'" id="' . esc_attr( $args['id'] ) . '_field">';

	if ( $args['label'] )
		$field .= '<label for="' . esc_attr( $args['id'] ) . '" class="' . esc_attr( implode( ' ', $args['label_class'] ) ) .'">' . $args['label'] . $required . '</label>';

	$field .= '<input type="phone" class="input-text ' . esc_attr( implode( ' ', $args['input_class'] ) ) .'" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '" '.$args['maxlength'].' value="' . esc_attr( $value ) . '" ' . implode( ' ', $custom_attributes ) . ' />';

	if ( $args['description'] )
		$field .= '<span class="description">' . esc_attr( $args['description'] ) . '</span>';

	$field .= '</p>' . $after;

	return $field;
}

// Checkout fields filter for phone number
//add_filter('woocommerce_form_field_number', 'wc_smartsend_form_number', 10, 3);

function wc_smartsend_form_number( $value, $key, $args)
{
	$custom_attributes = $args['custom_attributes'];
	$required = $args['required'];
	$after = '';

	$field = '<p class="form-row ' . esc_attr( implode( ' ', $args['class'] ) ) .'" id="' . esc_attr( $args['id'] ) . '_field">';

	if ( $args['label'] )
		$field .= '<label for="' . esc_attr( $args['id'] ) . '" class="' . esc_attr( implode( ' ', $args['label_class'] ) ) .'">' . $args['label'] . $required . '</label>';

	$field .= '<input type="number" class="input-text ' . esc_attr( implode( ' ', $args['input_class'] ) ) .'" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '" '.$args['maxlength'].' value="' . esc_attr( $value ) . '" ' . implode( ' ', $custom_attributes ) . ' />';

	if ( $args['description'] )
		$field .= '<span class="description">' . esc_attr( $args['description'] ) . '</span>';

	$field .= '</p>' . $after;

	return $field;
}


add_action('woocommerce_checkout_after_customer_details', 'woocommerce_smart_send_checkout_js');

// jQuery UI JS after form
function woocommerce_smart_send_checkout_js()
{
	$settings = get_option( 'woocommerce_smart_send_settings' );
	$smartSendQuote = new smartSendQuote( $settings['vipusername'], $settings['vippassword'] );
	$locations = $smartSendQuote->getLocations();
	$locSimple = array();

	foreach( $locations as $postcode => $details)
	{
		$townList = array();
		foreach( $details as $town)
			$townList[] = ucwords(strtolower($town[0]));

		$locSimple[] = '"'.$postcode.'": ["'. implode( '", "', $townList) .'"]';
	}

?>
	<script type="text/javascript">

		// Suburbs
		var suburbs = {<?php echo implode( ', ', $locSimple); ?>};

		jQuery( document ).ready( function($){

			$("#billing_postcode, #shipping_postcode").on( "keyup", function() {
				var _self = $(this), pcode = _self.val();

				if (pcode.length==4)
				{
					if ($(this).attr("id").indexOf('billing') > -1 )
						var cartSide = 'billing';
					else
						var cartSide = 'shipping';

					$("#"+cartSide+"_city").autocomplete( {
						"source": suburbs[pcode],
						"minLength": 0,
						change: function(event, ui) {
							$("body").trigger("update_checkout");
						}
					});

					$("#"+cartSide+"_city").autocomplete( "search", "");
				}
			});
		});
	</script>
<?php
}

/**
 * Extract PriceID from shipping ID stuff
 */
add_action( 'woocommerce_checkout_update_order_meta', 'smart_send_record_shipping' );

function smart_send_record_shipping( $order_id )
{
	// Scan for Smart Send shipping methods
	if (isset($_POST['shipping_method']) && is_array($_POST['shipping_method']))
	{
		foreach($_POST['shipping_method'] as $m)
		{
			if (preg_match('/^smart_send_(\d+)_/', $m, $bits))
			{
				$PriceID = $bits[1];
				update_post_meta( $order_id, 'SSpriceID', $PriceID );
				// We need to set shipping status
				update_post_meta( $order_id, 'SSstatus', 'quoted' ); // quoted, ordered, shipped
			}
		}
	}

	// Delete Smart Send shipping quote transients (cache)
	if ($transients = WC()->session->get('ssTransients'))
	{
		foreach( $transients as $hashKey )
		{
			if ($hashKey)
			{
				delete_transient($hashKey);
				WC()->session->set($hashKey, '');
			}
		}
	}
}

/**
 * On cart shipping calculator, swap postcode and city fields around for better city dropdown
 */
add_action('woocommerce_after_shipping_calculator', 'smartSendMoveCalcFields');

function smartSendMoveCalcFields()
{
	// Prepare for the suburb autocomplete

	$settings = get_option( 'woocommerce_smart_send_settings' );
	$smartSendQuote = new smartSendQuote( $settings['vipusername'], $settings['vippassword'] );
	$locations = $smartSendQuote->getLocations();
	$locSimple = array();

	foreach( $locations as $postcode => $details)
	{
		$townList = array();

		foreach( $details as $town)
			$townList[] = ucwords(strtolower($town[0]));

		$locSimple[] = '"'.$postcode.'": ["'. implode( '", "', $townList) .'"]';
	}

	?>
<script type="text/javascript">
	// Suburbs
	var suburbs = {<?php echo implode( ', ', $locSimple); ?>};

	jQuery(document).ready(function($) {

		$("#calc_shipping_city").closest("p").hide();

		// Move these fields around
//		$("#calc_shipping_postcode").insertBefore($("#calc_shipping_city"));

		$("#calc_shipping_postcode").on("keyup", function() {

			var _self = $(this), pcode = _self.val();
			if (pcode.length==4)
			{
				$("#calc_shipping_city").autocomplete( {
					"source": suburbs[pcode],
					"minLength": 0
				});

				$("#calc_shipping_city").closest("p").show();
				$("#calc_shipping_city").autocomplete( "search", "");
			}
		});
	});
</script><?php
}
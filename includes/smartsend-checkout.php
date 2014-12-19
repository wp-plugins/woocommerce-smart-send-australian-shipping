<?php


/**
 *  Add extra Smart Send shipping options on the checkout page
 */

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
					<small>If any items are <?php echo WC_Smart_Send::$tailMin; ?>kg or over, extra assistance will be provided</small></label>
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
	$_SESSION["ss_option_$option"] = $status;
	exit( $_SESSION["ss_option_$option"] );
}

// Called by AJAX when options checkboxes checked
add_action( 'wp_ajax_smart_send_set_xtras', 'smartSendCheckoutAjax' );
add_action( 'wp_ajax_nopriv_smart_send_set_xtras', 'smartSendCheckoutAjax' );


// Postcode sets suburb dropdown on town, sets state

add_filter( 'woocommerce_billing_fields', 'wc_smartsend_checkout_postcode', 10, 1 );
add_filter( 'woocommerce_shipping_fields', 'wc_smartsend_checkout_postcode', 10, 1 );

function wc_smartsend_checkout_postcode( $address_fields )
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
			$newFields[$temp1] = $temp2;
	}
	return $newFields;
}

// jQuery UI JS after form
function woocommerce_smart_send_checkout_js()
{
	$settings = get_option( 'woocommerce_smart_send_settings' );
	$smartSendQuote = new smartSendUtils( $settings['vipusername'], $settings['vippassword'] );
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
		var suburbs = {<?=implode( ', ', $locSimple)?>};

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

add_action('woocommerce_checkout_after_customer_details', 'woocommerce_smart_send_checkout_js');
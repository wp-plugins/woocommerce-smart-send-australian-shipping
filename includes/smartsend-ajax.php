<?php

/*
 *  Order Fulfillment
 *
 *  Called by
 *   - Summary page (wp-admin/edit.php?post_type=shop_order)
 *   - Order view page
 *
 *  smartSendOrder object loads in all the order-related data we have;
 *  method 'verifyStatus' is then used if we need to book ($setStatus = 'ordered')
 *
 */

add_action( 'wp_ajax_smart_send_ship_order', 'smart_send_ship_order_callback' );

function smart_send_ship_order_callback() {

    if (empty($_GET['priceID']))
        die( 'Error: No Price ID set');

    if ($order_id = $_GET['order_id'])
    {

        // The status we wish to set to
        if ( !($setStatus = $_GET['set_status']))
            die('Error: No status specified.');

        if ( !in_array( $setStatus, array( 'ordered', 'shipped')))
            die( "Error: Can only set to 'ordered' or 'shipped' (not '$setStatus')'");

        $smartSendOrder = new smartSendOrder($order_id);

        $realStatus = $smartSendOrder->verifyStatus();

        switch( $setStatus)
        {
            // We want to summon the courier
            case 'ordered':

                if ($realStatus == 'booked')
                    smart_send_ajax_response('Shipping has already been booked');

                if ($realStatus == 'incomplete')
                    smart_send_ajax_response('Missing parameters', $smartSendOrder->getMissingParams());

                if ($realStatus == 'unpaid')
                    smart_send_ajax_response('Order has not been paid for, check order status');

                if (!empty($_GET['pickupdate']))
                {
                    $pickupDate = $_GET['pickupdate'];
                }
                else
                    $pickupDate = '';

                $smartSendOrder->pickupDate = $pickupDate;

                $resp = $smartSendOrder->fulfill();

                if ($resp[0] == 'error')
                {
                    smart_send_ajax_response('error', array($resp[1]));
                }
                elseif ($resp[0] == 'notice')
                {
                    smart_send_ajax_response('error', array($resp[1]));
                }

                smart_send_ajax_response($resp[1]);

                break;

            // Show tracking info
            case 'shipped':
                list( $type, $resp) = $smartSendOrder->getTrackingStatus();

                $html = '';

                if (preg_match('/Consignment number not found/', $resp)) {
                    $type = 'error';
                    $html = 'Could not find the consignment in tracking, please check <a href="https://www.smartsend.com.au/VIP/completedbookings.cfm">VIP console';
                }

                if (preg_match('/^Connection Failure/i', $resp))
                {
                    $type = 'error';
                    $html = 'Could not retrieve tracking data - please contact Smart Send, reference number '.$smartSendOrder->bookingReference;
                }

                if ($type == 'error') {
	                $html = '<style>body: { background: white; }</style>' . $html;
                }
				elseif (!$resp)
				{
					$html = '<h2>No tracking results available currently. Try again after pickup date/time in your booking or contact Smart Send Customer Service for assistance.</h2>h2>';
				}
				else
					$html = $resp;

                // Ajax response or not?
//                if (defined('DOING_AJAX') && DOING_AJAX)
//                    smart_send_ajax_response( $type, array($html));
//                else
                    echo $html;

                break;
        }
    }
    else
        die('Error: No order ID');
//    print_r($_REQUEST);

    die(); // this is required to terminate immediately and return a proper response
}

function smart_send_ajax_response($msg, $data = array())
{
    $resp = array_merge( array('response' => $msg ), $data);
    wp_send_json($resp);
}
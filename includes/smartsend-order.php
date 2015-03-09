<?php

/**
 * 'Order' object, contains all the info and functionality we need to do what we have to.
 * This collates the info we need from various sources, and provides methods for completing the shipping.
 *
 * Class smartsendOrder
 */
class smartSendOrder {
	public $priceId;

	// SDK object
	protected $bookJob;
	protected $tracking;

	public $shippingStatus;
	public $setStatus;
	public $trackingId;
	public $bookingReference;
	public $pickupDate;
	public $shipQuote;

	public function __construct( $orderId ) {

		$this->orderId = $orderId;
		$this->wcOrder = wc_get_order( $orderId );
		$this->wcMeta  = get_post_meta( $orderId );

		$this->priceId = $priceId = $this->wcMeta['SSpriceID'][0];

		$this->shippingStatus = $this->wcMeta['SSstatus'][0];

		$this->bookingReference = !empty($this->wcMeta['SSreferenceID'][0]) ? $this->wcMeta['SSreferenceID'][0] : 0;
		$this->trackingId = !empty($this->wcMeta['SStrackingID'][0]) ? $this->wcMeta['SStrackingID'][0] : 0;

        // The status we want to change to
		if ( !empty( $_GET['set_status'] ) ) {
			$this->setStatus = $_GET['set_status'];
		}

		// Pull in Smart Send settings
		$this->ssSettings = get_option( 'woocommerce_smart_send_settings', null );
		$this->useTest = ( empty($this->ssSettings['usetestserver']) || $this->ssSettings['usetestserver'] == 'no') ? false : true;
	}

	protected function _connectBookJob() {
		// Try to initialise bookJob order and add params
		if ( !$this->bookJob )
			$this->bookJob = new smartSendBook( $this->ssSettings['vipusername'], $this->ssSettings['vippassword'], $this->useTest );
	}

	protected function _connectTracking() {
		// Try to initialise Tracking order and add params
		if ( !$this->tracking )
			$this->tracking = new smartSendTrack( $this->ssSettings['vipusername'], $this->ssSettings['vippassword'], $this->useTest );
	}

	public function getMissingParams() {
		return $this->bookJob->missingParams;
	}

	public function getTrackingId()
	{
		if (!$this->bookingReference)
			return false;

		if ($this->trackingId)
			return $this->trackingId;

		// Set up bookJob object
		$this->_connectBookJob();

		$trackingId = $this->trackingId = $this->bookJob->getTrackingId($this->bookingReference);

		update_post_meta($this->orderId, 'SStrackingID', $trackingId);

		return $trackingId;
	}

	public function getLabel()
	{
		// Make sure we have this ..
		$this->getTrackingId();

		// Set up bookJob object
		$this->_connectBookJob();

		if ($this->trackingId)
		{
			$labelname = $this->bookJob->getLabel($this->bookingReference, SSLABELS);
			return $labelname;
		}

	}



	/**
	 *  Return what the actual status of this order is:
	 *
	 *   * bookable - Order has not been booked but all parameters are correct
	 *   * incomplete - Order has been quoted but some parameters are missing or incorrect
	 *   * unpaid - Order is bookable but it has not been paid for
	 *   * booked - Order has been booked but not collected
	 *   * collected - Order has been collected by courier
	 *
	 *   Possible Smart Send statuses are 'quoted', 'ordered', 'shipped'
	 *
	 *   WooCommerce statuses should be 'wc-processing' or 'wc-completed'
	 */

	public function verifyStatus()
	{
		$wcStatus = $this->wcOrder->post->post_status;
		$ssStatus = $this->wcMeta['SSstatus'][0];

		// If not yet ordered, we need to check that all required parameters are present
		// And that the order has been paid for
		if ( $ssStatus == 'quoted' ) {
			// Populates missingParams
			$this->_connectBookJob();
			$allPresent = $this->bookJob->verifyRequired();

			if ( !$allPresent ) // Missing parameters
			{
				return 'incomplete';
			}

			if ( in_array( $wcStatus, array( 'wc-pending', 'wc-on-hold' ) ) ) {
				return 'unpaid';
			}

			return 'bookable';
		}

		if ( $ssStatus == 'ordered' ) {
			return 'booked';
		}

		if ( $ssStatus == 'shipped' ) {
			return 'collected';
		}
	}

	public function getTrackingStatus()
	{
		$this->_connectTracking();
		$tracked = $this->tracking->getTracking($this->wcMeta['SStrackingID'][0]);

		if ($tracked->StatusCode == 0 )
		{
			return array('success', $this->tracking->getHTML());
		}
	}

	public function fulfill($pickupDate = '')
	{
		// Tell the SDK to book it, get result
		$this->_connectBookJob();

		if ( $this->verifyStatus() != 'bookable' ) {
			return 'Cannot be fulfilled';
		}

		// Set the 'details' params
        $this->_populateDetails();

		$resp = $this->bookJob->bookOrder();

		list( $status, $messages, $referenceID ) = $this->bookJob->getBookStatus();

		// Handle the results - success, pending or error
		if ( $status == 'error' ) {
			return array( 'error', 'Error code '.$status.': '.$messages );
		}

		$order_id = $this->wcOrder->id;

		if ($status == 'notice' )
		{
			// We need to set shipping status
			update_post_meta( $order_id, 'SSstatus', 'pending' ); // quoted, ordered, shipped
			return array( 'notice', 'Notice: '.$messages);
		}
		update_post_meta( $order_id, 'SSreferenceID', $referenceID );

		// We need to set shipping status
		update_post_meta( $order_id, 'SSstatus', 'ordered' ); // quoted, ordered, shipped

		// Set pickup date in order meta
		update_post_meta( $order_id, 'SSpickupDate', $this->pickupDate );

		return array( 'success', 'Shipping booked, reference ID: '.$referenceID );

	}

    protected function _populateDetails()
    {
        $this->bookJob->priceId = $this->priceId;

        // Set 'MerchantInvoiceNumber' = order ID / Post ID
	    $this->bookJob->setParam( 'MerchantInvoiceNumber', 'Order Number: '.$this->wcOrder->id );
	    $this->bookJob->setParam( 'PriceID', $this->priceId );

        // Quote details, from cache
        $this->shipQuote = $this->bookJob->getQuoteDetails();

        // Recipient email tracking?
        if ( $this->ssSettings['emailrecipient'] == 'yes' && $this->wcMeta['_billing_email'][0] ) {
            $this->bookJob->setParam( 'EmailReceiverTracking', 1 );
            $this->bookJob->setParam( 'ReceiverEmailIn', $this->wcMeta['_billing_email'][0] );
        }

        // Pickup date and time
        $PickupTime = $this->bookJob->calcPickupTime( $this->shipQuote, $this->ssSettings['pickuptime'] );
        $this->bookJob->setParam( 'PickupTime', $PickupTime );

        $PickupDate = $this->bookJob->calcPickupDate( $this->shipQuote, $this->pickupDate );
        $this->bookJob->setParam( 'PickupDate', $PickupDate );

        // Generic address details
        $companyName  = $this->ssSettings['companyname'];
        $contact      = $this->ssSettings['merchantcontact'];
        $contactphone = $this->ssSettings['merchantphone'];
        $contactemail = $this->ssSettings['merchantemail'];

        $bookParams = array();

        // Collate contact details
        $bookParams['ContactDetails'] = array(
            'CompanyName' => $companyName,
            'Name'        => $contact,
            'Phone'       => $contactphone,
            'Email'       => $contactemail
        );

        // Collate pickup details
        $bookParams['PickupDetails'] = array(
            'CompanyName'    => $companyName,
            'Name'           => $contact,
            'Phone'          => $contactphone,
            'StreetAddress1' => $this->ssSettings['originaddress']
        );

	    if ($this->ssSettings['couriermessage'])
	    {
		    $bookParams['PickupDetails']['StreetAddress2'] = 'NOTE: '.$this->ssSettings['couriermessage'];
	    }

        // Collate destination details
        $bookParams['DestinationDetails'] = array(
            'CompanyName'    => $this->wcMeta['_shipping_company'][0],
            'Name'           => substr( $this->wcMeta['_shipping_first_name'][0] . ' ' . $this->wcMeta['_shipping_last_name'][0], 0, 30),
            'Phone'          => $this->wcMeta['_billing_phone'][0],
            'StreetAddress1' => $this->wcMeta['_shipping_address_1'][0],
            'StreetAddress2' => $this->wcMeta['_shipping_address_2'][0]
        );

        $this->bookJob->setParams( $bookParams );
    }
}

/**
 *  When payment is complete, we might need to auto-fulfill
 */
add_action( 'woocommerce_order_status_completed', 'smart_send_order_paid' );

function smart_send_order_paid( $orderId ) {
	$ssSettings = get_option( 'woocommerce_smart_send_settings', null );

	// If we are set for auto-fulfil, let's try to do that
	if ($ssSettings['autofulfill'] == 'yes')
	{
		$meta = get_post_meta( $orderId );

		if ($meta['SSstatus'][0] == 'quoted' && empty($meta['SSreferenceID'][0]))
		{
			$ssOrder = new smartSendOrder($orderId);
			list($res, $msg ) = $ssOrder->fulfill();
		}
	}
}

/**
 *  Tracking button if order has been collected
 */
add_action('woocommerce_order_details_after_order_table', 'smart_send_tracking_button');

function smart_send_tracking_button($order)
{

}
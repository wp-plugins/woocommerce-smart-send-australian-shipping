<?php
/**
 * Smart Send PHP SDK 2.0
 *
 * Extend the API class to get booking quotes through the Smart Send API.
 *
 * Collates new booking details and then retrieves relevant original request to fulfil a booking.
 *
 * @package   Smart Send PHP SDK
 * @author    Paul Appleyard <paul@smartsend.com.au>
 * @link      http://digital.smartsend.com.au/sdk/php
 * @see       smartSendApi
 * @see       smartSendQuote
 * @see       smartSendTrack
 *
 * @copyright 2015 Smart Send Pty Ltd
 */

/**
 *  Class smartSendBook
 *
 *  Extend the API class to book Smart Send shipping quotes previously made
 *
 */
class smartSendBook extends smartSendAPI
{
    public $priceId;

    /**
     * @var array Another convoluted solution to checking params and returning meaningful messages
     */
    protected $_detailsFormats = array(

        'PriceID'               => array( '^\d+$', "must be a number" ),

        'MerchantInvoiceNumber' => '', // Not required

        'ContactDetails'        => array(
            'CompanyName' => '',
            'Name'        => '',
            'Phone'       => array( '^\d{10}$', "must be a 10 digit number (no spaces)" ),
            // A bunch of stuff that's NOT an '@', then an '@', then an '@', then a bunch of non-'@'
            'Email'       => array( '[^@]+@[^@]+', 'is invalid')
        ),

        'PickupDetails'         => array(
            'CompanyName'    => '', // Not required
            'Name'           => '',
            'Phone'          => array( '^\d{10}$', "must be a 10 digit number (no spaces)" ),
            'StreetAddress1' => '',
            'StreetAddress2' => ''
        ),

        'DestinationDetails'    => array(
            'CompanyName'    => '', // Not required
            'Name'           => '',
            'Phone'          => array( '^\d{10}$', "must be a 10 digit number (no spaces)" ),
            'StreetAddress1' => '',
            'StreetAddress2' => ''
        ),

        'PickupDate' => '',
        'PickupTime' => '',

        'EmailReceiverTracking' => '', // Not required
        'ReceiverEmailIn'       => ''
    );

    /**
     * @var array Required parameters when hitting BookJob
     */
    protected $_detailsRequired = array(
        'PriceID',
        'ContactDetails' => array(
            'Name',
            'Phone',
            'Email'
        ),
        'PickupDetails' => array(
            'Name',
            'Phone',
            'StreetAddress1'
        ),
        'DestinationDetails' => array(
            'Name',
            'Phone',
            'Email'
        ),
        'PickupDate',
        'PickupTime'
    );

    /**
     * @var Result of booking attempt is stored in this
     */
    protected $_bookingResults;

    /**
     * @var Reference from calling app - used for cache key
     */
    protected $_appReference;

    /**
     * @var Possibly not needed
     */
    public $quoteDetails;

    /**
     * Giddy up - Sets a default for emailReceiverTracking after calling parent constructor
     *
     * @param null $username
     * @param null $password
     * @param bool $usetest
     */
    public function __construct( $username = NULL, $password = NULL, $usetest = false )
    {
        parent::__construct( $username, $password, $usetest );

        // Defaults
        $this->_params['details'] = array(
            'EmailReceiverTracking' => 0
        );

        $this->_missingParams['details'] = array('init');
    }

    /**
     * When booking a job, we need the params from the original quote, for some stupid reason.
     * This method extracts these cached values.
     * TODO: Allow the code using this SDK to implement their own method for caching
     *
     * @return mixed Unserialised request params array/false
     */
    protected function _setRequestParams()
    {
        $cachePath = SSCACHE.'quotes/req_'.$this->getCacheKey();
        if (is_readable($cachePath))
        {
            $params = file_get_contents( $cachePath );
            $this->_params['request'] = unserialize($params);
            return true;
        }
        return false;
    }

    /**
     * Use the registered PriceID to pull the actual quote details from the cache
     *
     * @return array/boolean - Quote array or false
     */
    public function getQuoteDetails($priceId = null)
    {
        $cachePath = SSCACHE.'quotes/res_'.$this->getCacheKey();
        if (file_exists($cachePath))
        {
            $quote = file_get_contents( $cachePath );
            $this->quoteDetails = unserialize($quote);
            return $this->quoteDetails;
        }
        return false;
    }

    /**
     * Return the packing list, if set
     *
     * @return bool|mixed - Packing list
     */
    public function getPackingList()
    {
        $cachePath = SSCACHE.'quotes/packing_'.$this->getCacheKey();
        if (file_exists($cachePath))
        {
            $packing = file_get_contents( $cachePath );
            $this->packingList = unserialize($packing);
            return $this->packingList;
        }
        return false;
    }

    /**
     * Set an array of params all at once.
     *
     * @param $arr - Array of values to be set
     * @uses parent::_setParams()
     *
     * @return bool|string
     */
    public function setParams($arr)
    {
        return $this->_setParams($arr, 'details');
    }

    public function setAppReference($ref)
    {
        $this->_appReference = $ref;
    }

    protected function getCacheKey()
    {
        $key = $this->_appReference ? $this->priceId . '_' . $this->_appReference : $this->priceId;
        return $key;
    }

    /**
     * Set a job booking related parameter - calls core param setting logic
     *
     * TODO: Max char limits
     *
     * @uses parent::_setParam()
     *
     * @param $param
     * @param $value
     *
     * @return mixed|string
     */
    public function setParam( $param, $value)
    {
        // Now we check for expected params; receiver emails need special attention
        switch( $param )
        {
            case 'EmailReceiverTracking':

                if ( !in_array( (int)$value, array(0, 1)))
                {
                    return smart_send_return_error($param.' must be a 1 or a 0');
                }

                break;

            case 'ReceiverEmailIn':

                if ( !preg_match( '/[^@]+@[^@]+/', $value))
                {
                    return smart_send_return_error($param.' is invalid format');
                }
                elseif (empty($this->_params['details']['EmailReceiverTracking']))
                {
                    return smart_send_return_error("'EmailReceiverTracking' parameter must be set to '1' first");
                }

                break;
        }
        return $this->_setParam( $param, $value, 'details' );
    }

    /**
     * Set the original quote 'request' params and try to book the job
     *
     * @uses $this->_setRequestParams()
     * @return object Results from API
     */
    public function bookOrder()
    {

        $this->_setRequestParams();

        $soap = $this->_connectSoap();

        // Log the params sent ..
        $this->debugLog( 'book-results', $this->_params );

        $this->_bookingResults = $soap->BookJob( $this->_params );

        // Log SOAP data
        $this->_logSoap( $soap );

        // .. and log the response received
        $this->debugLog( 'book-results', $this->_bookingResults );

        return $this->_bookingResults;
    }

    /**
     *  Return a more legible response
     *
     * @return array $status, $message, reference ID
     */
    public function getBookStatus()
    {
        $res = $this->_bookingResults->BookJobResult;

        $refId = $res->ReferenceID;

        if ($res->StatusCode == -1 ) {
            $status = 'error';
            $refId = null;
        }
        else
            $status = 'success';

        $messages = $res->StatusMessages;

        if (is_array($messages))
            $message = implode(", ", $messages);
        elseif (!empty($messages->string))
            $message = $messages->string;
        else
            $message = '';

        // Need to book manually, no auto top-up set
        if ($status == 0 && !$message )
        {
            $status = 'notice';
            $message = "Because Smart Top-up ‘Auto-pay’ is not selected in your VIP dashboard’s “API Bookings” tab, you must book this order through the “API Bookings” tab.  To streamline bookings from your Woocommerce store in future, select the ‘Auto-pay’ option in the “API Bookings” tab of your VIP dashboard.";
            $refId = null;
        }

        return array( $status, $message, $refId);
    }

    /**
     * Loop through params; unless they have a expected value of 'false' they are needed.
     * Add each missing param to $missing array and store in $this->missingParams
     *
     * @return bool
     */
    public function verifyRequired()
    {
        // List of missing
        $missing = array();

        // Top level
        foreach( $this->_params['details'] as $p => $s )
        {
            if ($s === false) // Not required
                continue;

            // Array of required fields
            if (is_array($s) && count($s) > 2)
            {
                foreach( $s as $sp => $ss )
                {
                    if ($ss === false)
                        continue;

                    if (!isset( $this->_params['details'][$p][$sp] ))
                        $missing[$p] = $sp;
                }
            }
            elseif ($s === true || is_array($s) )
            {
                // Required, or has a pattern - check for presence
                if (!isset($this->_params['details'][$p]))
                    $missing[] = $p;
            }

        }

        if ( count($missing))
        {
            $this->missingParams = $missing;
            return false;
        }

        $this->missingParams = array();

        return true;
    }

    /**
     * Based on specified pickup window, return a pickup time
     * 11:45 every carrier except for Fastway, which is tomorrow between 9 and 5
     *
     * @param array $quote - Quote details relating to booking job
     *
     * @return string - Pickup date string
     * @deprecated - Use static function puCalc instead
     */
    public function calcPickupDate($quote, $date = '')
    {
        return self::puCalc($quote, $date);
    }

    public static function puCalc($quote, $date='')
    {
        // Set to UTC +10
        $timePlus = time() + 60 * 60 * 10;

        // Current hour in 24 hour format
        $H = gmdate("G", $timePlus);
        $m = (int)gmdate("i", $timePlus);

        $today = date("Y-m-d", $timePlus);
        $tomoz = date("Y-m-d", $timePlus+86400);
        $puDate = '';

        if (!$quote['SameDayPickupAvailable'] || !$quote['SameDayPickupCutOffTime'])
            $puDate = $tomoz;

        // Same day cut-off time - usually 11:45
        elseif (!empty($quote['SameDayPickupCutOffTime']))
        {
            list($sdH, $sdM) = explode('.', $quote['SameDayPickupCutOffTime']);

            if ($H > $sdH || ($H == $sdH && $m > $sdM ))
                $puDate = $tomoz;
            else
                $puDate = $today;
        }
        else
            $puDate = $today;

        if ($date && strtotime($date) > strtotime($puDate))
            $puDate = $date;

        // Make sure that pickup date is not a weekend day
        if ($puDate == $today) {
            $offSet = $timePlus;
        }
        else {
            $offSet = $timePlus + 86400;
        }
        $wDay = gmdate( "l", $offSet );

        if (in_array($wDay, array('Saturday', 'Sunday')))
        {
            while( in_array($wDay, array('Saturday', 'Sunday')))
            {
                $offSet = $offSet + 86400; // Next day
                $wDay = gmdate( "l", $offSet );
            }
            $puDate = date("Y-m-d", $offSet);
        }

        return $puDate;
    }

    /**
     * Return the correct pickup time - for < 4 hours delivery, this must be the 30 minute one.
     *
     * @param $quote  - Details of the quote
     * @param $puTime - Pickup time specified by merchant
     *
     * @return string - One of 4 allowed strings
     */
    public function calcPickupTime($quote, $puTime)
    {
        // Fastway are special
        if ($quote['CourierName'] == 'Fastway' )
            return 'Between 9am - 5pm';

        $serviceName = $quote['ServiceName'];

        if ( in_array($serviceName, array( 'Local 3-4 hours', 'Local 2 hours', 'Local 1 hour')) )
        {
            return 'Within 30 minutes of Booking during business hours';
        }

        return $puTime;
    }
}
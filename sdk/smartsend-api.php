<?php
/**
 * Smart Send PHP SDK 2.0
 *
 * An SDK for integrating with Smart Send via the API.
 *
 * This file contains the 'core' class that is extended by the specific functionality, which are:
 *
 *   - smartSendQuote for procuring initial quotes from the API
 *   - smartSendBook to 'fulfil' those quotes using the price ID returned by the quote
 *   - smartSendTrack to access tracking data available to Smart Send for the shipment
 *
 * It contains default functions and attributes as much as possible, keeping it DRY
 *
 * @package   Smart Send PHP SDK
 * @author    Paul Appleyard <paul@smartsend.com.au>
 * @link      http://digital.smartsend.com.au/sdk/php
 * @see       smartSendBook
 * @see       smartSendQuote
 * @see       smartSendTrack
 *
 * @copyright 2015 Smart Send Pty Ltd
 */

// Base path for standard file locations
if (!defined( 'SSBASE' )) {
    define( 'SSBASE', dirname( __FILE__ ) . '/' );
}

// Location for caching of locations and quotes
if (!defined( 'SSCACHE' )) {
    define( 'SSCACHE', SSBASE . 'cache/' );
}

if (!defined( 'SSLOGS' )) {
    define( 'SSLOGS', SSBASE . 'logs/' );
}

if (!defined( 'SSLABELS' )) {
    define( 'SSLABELS', SSBASE . 'labels/' );
}

//if (!defined( 'CALLSRC' )) {
//    define( 'CALLSRC', 'sdk' );
//}
//
//if (!defined( 'CALLVER' )) {
//    define( 'CALLVER', '2.3' );
//}


/**
 * Class smartSendAPI
 *
 * V 2.3
 *
 * Core Smart Send SDK functionality and settings
 */
class smartSendAPI
{

    /**
     * @var string - SOAP endpoint for live server
     */
    private $_liveWSDL = 'http://developer.smartsend.com.au/service.asmx?wsdl';
    /**
     * @var string - SOAP endpointfor test UAT server
     */
    private $_testWSDL = 'http://uat.smartservice.smartsend.com.au/service.asmx?wsdl';

    /**
     * @var string - The endpoint that is selected
     */
    protected $ssWSDL;

    /**
     * @var bool Flag to use UAT or live API
     */
    protected $_useTest;

    /**
     * @var Soap connection object
     */
    protected $soapClient;

    /**
     * @var string VIP username
     */
    protected $username = '';

    /**
     * @var string VIP password
     */
    protected $password = '';

    /**
     * @var Core request parameters. These are used by both ObtainQuote and BookJob
     */
    protected $_params;

    /**
     * @var int TTL in days for locations cache
     */
    private $cacheTTL = 1;

    /**
     * @var string Where we keep the locations cache file
     */
    private $locationsCacheFile;

    /**
     * @var array Load locations in to this
     */
    private $_locationsList = array();

    /**
     * @var bool Debug flag
     */
    public static $SSdebug = true;

    // Package descriptions

    /**
     * @var array - Complete list of package types
     */
    public static $ssPackageTypes = array(
        'Carton',
        'Satchel/Bag',
        'Tube',
        'Skid',
        'Pallet',
        'Crate',
        'Flat Pack',
        'Roll',
        'Length',
        'Tyre/Wheel',
        'Furniture/Bedding',
        'Envelope',
        'Best fixed price road satchel',
        'Best priority satchel',
        '500gm fixed price road satchel',
        '1kg fixed price road satchel',
        '3kg fixed price road satchel',
        '5kg fixed price road satchel',
        '3kg PRIORITY SATCHEL',
        '5kg PRIORITY SATCHEL'
    );

    /**
     * @var array Fixed-price satchel attributes - Width, length, depth and weight
     */
    public static $ssFixedSatchelParams = array(
        '500gm fixed price road satchel' => array( '2', '22', '33', '0.5' ),
        '1kg fixed price road satchel'   => array( '4', '19', '26', '1' ),
        '3kg fixed price road satchel'   => array( '7', '32', '42', '3' ),
        '5kg fixed price road satchel'   => array( '7', '43', '59', '5' )
    );

    /**
     * @var array
     */
    public static $ssPrioritySatchelParams = array(
        '3kg PRIORITY SATCHEL' => array( '5', '39', '33', '3' ),
        '5kg PRIORITY SATCHEL' => array( '9', '48', '42', '5' )
    );

    /**
     * @var array Acceptable pickup window values
     */
    public static $pickupWindows = array(
        'Between 12pm - 4pm',
        'Between 1pm - 5pm',
        'Within 30 minutes of Booking during business hours'
    );

	public static $ozStates = array(
		'ACT' => 'Australian Capital Territory',
		'NSW' => 'New South Wales',
		'VIC' => 'Victoria',
		'QLD' => 'Queensland',
		'SA'  => 'South Australia',
		'WA'  => 'Western Australia',
		'TAS' => 'Tasmania',
		'NT'  => 'Northern Territory'
    );

	public static $stdPackages = array(

        // Best Price Courier Satchels
        'Best Price 1kg Road Satchel' => array(
            'type' => '1kg fixed price road satchel',
            'dimensions' => array(19, 26, 4),
            'weight' => '1'
        ),
        'Best Price 3kg Road Satchel' => array(
            'type' => '3kg fixed price road satchel',
            'dimensions' => array(32, 42, 7),
            'weight' => '3'
        ),
        'Best Price 5kg Road Satchel' => array(
            'type' => '5kg fixed price road satchel',
            'dimensions' => array(43, 59, 7),
            'weight' => '5'
        ),

        // Fastway Courier Satchels
        'Fastway 1kg Road Satchel' => array(
            'type' => 'Satchel/Bag',
            'dimensions' => array(19, 26, 4),
            'weight' => '1'
        ),
        'Fastway 3kg Road Satchel' => array(
            'type' => 'Satchel/Bag',
            'dimensions' => array(32, 44, 7),
            'weight' => '3'
        ),
        'Fastway 5kg Road Satchel' => array(
            'type' => 'Satchel/Bag',
            'dimensions' => array(45, 61, 7),
            'weight' => '5'
        ),

        // Couriers Please Courier Satchels
        'Couriers Please 500g Road Satchel' => array(
            'type' => '500gm fixed price road satchel',
            'dimensions' => array(22, 33, 2),
            'weight' => '.5'
        ),
        'Couriers Please 1kg Road Satchel' => array(
            'type' => '1kg fixed price road satchel',
            'dimensions' => array(27, 35, 4),
            'weight' => '1'
        ),
        'Couriers Please 3kg Road Satchel' => array(
            'type' => '3kg fixed price road satchel',
            'dimensions' => array(33, 42, 7),
            'weight' => '3'
        ),
        'Couriers Please 5kg Road Satchel' => array(
            'type' => '5kg fixed price road satchel',
            'dimensions' => array(43, 59, 7),
            'weight' => '5'
        ),


		// Aust Post Mailing Boxes
		'Mailing Box Bx1 (Australia Post)' => array(
			'type' => 'Carton',
			'dimensions' => array(22, 16, 7.7),
			'weight' => '1'
		),
		'Mailing Box Bx2 (Australia Post)' => array(
			'type' => 'Carton',
			'dimensions' => array(31, 22.5, 10.2),
			'weight' => '3'
		),
		'Mailing Box Bx3 (Australia Post)' => array(
			'type' => 'Carton',
			'dimensions' => array(40, 40, 18),
			'weight' => '5'
		),
		'Mailing Box Bx4 (Australia Post)' => array(
			'type' => 'Carton',
			'dimensions' => array(43, 30.5, 14),
			'weight' => '5'
		),
		'Mailing Box Bx5 (Australia Post)' => array(
			'type' => 'Carton',
			'dimensions' => array(40.5, 30, 25.5),
			'weight' => '6'
		),
		'Mailing Box Bx18 (Australia Post)' => array(
			'type' => 'Carton',
			'dimensions' => array(18, 18, 18),
			'weight' => '4'
		),
		'Mailing Box Bx20 (Australia Post)' => array(
			'type' => 'Carton',
			'dimensions' => array(35, 50, 44),
			'weight' => ''
		),
		'Video/DVD Bx6 (Australia Post)' => array(
			'type' => 'Carton',
			'dimensions' => array(22, 14.5, 3.5),
			'weight' => ''
		),

		// Aust Post Padded Bags
		'Padded Bag Pb1 (Australia Post)' => array(
			'type' => 'Satchel/Bag',
			'dimensions' => array(12.7, 17.8, 0),
			'weight' => ''
		),
		'Padded Bag Pb2 (Australia Post)' => array(
			'type' => 'Satchel/Bag',
			'dimensions' => array(15.1, 22.9, 0),
			'weight' => ''
		),
		'Padded Bag Pb3' => array(
			'type' => 'Satchel/Bag',
			'dimensions' => array(21.5, 28, 0),
			'weight' => ''
		),
		'Padded Bag Pb4 (Australia Post)' => array(
			'type' => 'Satchel/Bag',
			'dimensions' => array(26.6, 38.1, 0),
			'weight' => ''
		),
		'Padded Bag Pb5 (Australia Post)' => array(
			'type' => 'Satchel/Bag',
			'dimensions' => array(36.1, 48.3, 0),
			'weight' => ''
		),
		'Padded Bag (Recycled) Pb8 (Australia Post)' => array(
			'type' => 'Satchel/Bag',
			'dimensions' => array(24.5, 33.5, 0),
			'weight' => ''
		),
		'Padded Bag Pb9 (Australia Post)' => array(
			'type' => 'Satchel/Bag',
			'dimensions' => array(36, 48, 0),
			'weight' => ''
		),

		// Aust Post Tough Bags
		'Tough Bag Tb1 (Australia Post)' => array(
			'type' => 'Satchel/Bag',
			'dimensions' => array(21.6, 27.5, 6.5),
			'weight' => ''
		),
		'Tough Bag Tb2 (Australia Post)' => array(
			'type' => 'Satchel/Bag',
			'dimensions' => array(24.1, 33.8, 6.5),
			'weight' => ''
		),
		'Tough Bag Tb3 (Australia Post)' => array(
			'type' => 'Satchel/Bag',
			'dimensions' => array(26.6, 37.6, 6.5),
			'weight' => ''
		),
		'Expandable Tough Bag Tb5 (Australia Post)' => array(
			'type' => 'Satchel/Bag',
			'dimensions' => array(37, 40.5, 7.5),
			'weight' => ''
		),
		'Expandable Tough Bag Tb6 (Australia Post)' => array(
			'type' => 'Satchel/Bag',
			'dimensions' => array(40.5, 67, 11.5),
			'weight' => ''
		),
		'Expandable Tough Bag Bx8 (Australia Post)' => array(
			'type' => 'Carton',
			'dimensions' => array(36.3, 21.2, 6.5),
			'weight' => ''
		),

		// Aust Post Mailing Tubes
		'Mailing Tube Tu1 (Australia Post)' => array(
			'type' => 'Tube',
			'dimensions' => array(42, 6, 6),
			'weight' => ''
		),
		'Mailing Tube Tu2 (Australia Post)' => array(
			'type' => 'Tube',
			'dimensions' => array(42, 6, 6),
			'weight' => ''
		),
		'Mailing Tube Tu3 (Australia Post)' => array(
			'type' => 'Tube',
			'dimensions' => array(66, 6, 6),
			'weight' => ''
		),

		// Aust Post Other
		'CD Mailer (Australia Post)' => array(
			'type' => 'Carton',
			'dimensions' => array(14.5, 12.7, 1),
			'weight' => ''
		),
		'Wine Box (Single) (Australia Post)' => array(
			'type' => 'Carton',
			'dimensions' => array(13.2, 10.5, 39.5),
			'weight' => ''
		),
		'Wine Box (Twin) (Australia Post)' => array(
			'type' => 'Carton',
			'dimensions' => array(13.2, 10.5, 39.5),
			'weight' => ''
		)
	);

    /**
     * @var array Possibly overly complicated way of parsing expected parameters for 'request' params
     */
    protected $_requestFormats = array(
        'VIPUsername'        => array( '.{0,250}', 'is too long' ),
        'VIPPassword'        => array( '.{0,250}', 'is too long' ),
        'PostcodeFrom'       => '\d{4}',
        'PostcodeTo'         => '\d{4}',
        'SuburbFrom'         => '\d{0,255}',
        'SuburbTo'           => '\d{0,255}',
        'StateFrom'          => '\d{3}',
        'StateTo'            => '\d{3}',
        'Items'              => array(
            'Depth'  => array( '\d+', 'must be a whole number' ),
            'Height' => array( '\d+', 'must be a whole number' ),
            'Length' => array( '\d+', 'must be a whole number' ),
            'Weight' => array( '[\d.]+', 'must be a number' )
        ),
        'DeveloperId'        => '',
        'ResellerId'         => '',
        'UserType'           => array( '(EBAY|CORPORATE|PROMOTION|CASUAL|REFERRAL)', 'value invalid' ),
        'OnlineSellerId'     => array( '.{0,50}', 'OnlineSellerId is too long' ),
        'PromotionalCode'    => array( '.{0,50}', 'PromotionalCode is too long' ),
        'ReceiptedDelivery'  => array( '(True|true|yes|Yes|False|false|0|1)', 'value invalid' ),
        'TailLift'           => array( '(NONE|PICKUP|DELIVERY|BOTH)', 'value invalid' ),
        'TransportAssurance' => array( '\d+', 'must be a whole number' )
    );

    /**
     * @var array Fields required by 'request' params
     */
    protected $_requestRequired = array(
        'PostcodeFrom',
        'SuburbFrom',
        'StateFrom',
        'PostcodeTo',
        'SuburbTo',
        'StateTo',
        'Items'
    );

    /**
     * @var array - Running tally of missing parameters
     */
    protected $_missingParams = array( 'request' => array( 'init' ) );

    // Change these to your own if needed. These facilitate shipping label delivery.
    /**
     * @var string Developer ID for test environment
     */
    protected $_testDeveloperID = '87b6c44e-4af5-485f-a033-e42a6dd981f3';

    /**
     * @var string Developer ID for live environment
     */
    protected $_liveDeveloperID = 'b9a66cbc-d785-4874-8c44-5cee2ba0d9f3';

    /**
     * @var string Place to retrieve shipping labels and tracking info from
     */
    protected $_labelServer = 'http://labels.smartsend.com.au/srv.php';

    /**
     * Initialise the Smart Send SOAP API
     *
     * If username starts with test@ will use the test server if username starts with 'test@'
     *
     * @param string $username Smart Send VIP username
     * @param string $password Smart Send VIP password
     * @param bool   $useTest  Whether to use the test API. Default is false.
     *
     * @throws Exception
     */

    public function __construct( $username = '', $password = '', $useTest = false )
    {
        $errors         = array();
        $this->_useTest = $useTest;

        if (!$username) {
            $errors[] = 'No VIP username provided';
        }

        if (!$password) {
            $errors[] = 'No VIP password provided';
        }

        if (count( $errors )) {
            return smart_send_return_error( $errors );
        }

        $this->username = $username;
        $this->password = $password;

        // Set to test server if username starts with 'test@'
        if (preg_match( '/^test@/', $username )) {
            $this->_useTest = true;
        }

        $this->ssWSDL      = $this->_useTest ? $this->_testWSDL : $this->_liveWSDL;
        $this->developerId = $this->_useTest ? $this->_testDeveloperID : $this->_liveDeveloperID;

        self::debugLog( 'utilsInit', array(
            'username: ' . $this->username,
            'password: ' . $this->password,
            'endpoint: ' . $this->ssWSDL
        ) );

        // Location of suburbs cache
        $this->locationsCacheFile = SSCACHE . 'smartsend-locations.data';
        // Location for quotes caching
        $this->quotesCacheFile = SSCACHE . 'quotes/';

        // Format for items description
        $this->_requestFormats['Items']['Description'] = array(
            preg_replace( '|/|', '\/', ( implode( "|", self::$ssPackageTypes ) ) ),
            'not a valid value'
        );
    }

    /**
     * Instantiate soap connection
     *
     * @return object SOAP object
     */
    protected function _connectSoap()
    {
        if (self::$SSdebug) {
            $options = array( 'trace' => 1 );
        } else {
            $options = array();
        }

        if (!$this->soapClient) {
            $this->soapClient = new SoapClient( $this->ssWSDL, $options );
        }

        return $this->soapClient;
    }

    /**
     *  Take an array of params and set them one by one
     *
     * @param array  $arr
     * @param string $type
     *
     * @return bool|string
     */
    protected function _setParams( $arr, $type = 'request' )
    {
        $errs = array();
        if (is_array( $arr )) {
            foreach ($arr as $p => $v) {
                $res = $this->_setParam( $p, $v, $type );
                if ($res !== true) {
                    $errs[] = $res;
                }
            }
        }

        if (count( $errs )) {
            return implode( "\n", $errs );
        }

        return true;
    }

    /**
     * Checks if it's a valid parameter. We use the $_bookParams protected array to check for expected.
     *
     * @param string $item - The parameter
     * @param mixed  $data - The setting/s; can be a string or an array
     * @param string $type - 'request' or 'details'
     *
     * @return mixed - true if successful or an error message if not
     */
    protected function _setParam( $item, $data, $type = 'request' )
    {
        $errors = array();

        // Defines array of formats associated with request type, eg '_requestFormats'
        $formatsArray = $this->{'_' . $type . 'Formats'};

        if (!in_array( $item, array_keys( $formatsArray ) )) {
            $errors[] = "'$item' is not a valid parameter";

            return
                smart_send_return_error( $errors );
        }

        // If there's no expected format ..
        if (empty( $formatsArray[$item] )) {
            $this->_params[$type][$item] = $data;
        } else {
            $checkParams = $formatsArray[$item];

            // Setting a literal value
            if (!is_array( $data )) {
                $resp = $this->_paramValidate( $data, $item, $checkParams );
                if ($resp !== true) {
                    $errors[] = $resp;
                } else {
                    $this->_params[$type][$item] = $data;
                }
            } else {
                // Checking a subset of params (eg ContactDetails, PickupDetails)
                foreach ($data as $k => $v) {
                    $resp = !empty( $checkParams[$k] ) ? $this->_paramValidate( $v, $k, $checkParams[$k] ) : true;

                    if ($resp !== true) {
                        $errors[] = $resp;
                    } else {
                        $this->_params[$type][$item][$k] = $v;
                    }
                }

            }
        }

        // Update which params are missing
        if (count( $this->_missingParams[$type] )) {
            $this->_checkRequired( $this->{'_' . $type . 'Required'}, 'request' );
        }

        if (count( $errors )) {
            return $errors;
        }

        return true;
    }

    /**
     * Retrieve official list of locations - postcode, suburb, state
     *
     * @param bool $cachedRequested
     *
     * @internal param bool $cached true (default) for returning cached data, false for fresh data
     *
     * @return array|mixed
     */
    public function getLocations( $cachedRequested = true )
    {
        if (!$cachedRequested) {
            return $this->_locationsToArray();
        } else {
            if (file_exists( $this->locationsCacheFile )) {
                // Return cached data if not expired
                $fileAge = time() - filemtime( $this->locationsCacheFile );
                if ($fileAge < $this->cacheTTL * 3600) {
                    $locationsList = unserialize( file_get_contents( $this->locationsCacheFile ) );
                }
            }
            // Either expired or doesn't exist
            if (!isset( $locationsList )) {
                $locationsList = $this->_locationsToArray();
                file_put_contents( $this->locationsCacheFile, serialize( $locationsList ) );
            }

            return $locationsList;
        }
    }

    /**
     * Get locations from API and store as an array within this object
     *
     * @return array
     */
    protected function _locationsToArray()
    {
        $this->_connectSoap();
        $locations = $this->soapClient->GetLocations();
        foreach ($locations->GetLocationsResult->Location as $location) {
            $postcode                        = sprintf( "%04d", $location->Postcode );
            $this->_locationsList[$postcode][] = array( $location->Suburb, $location->State );
        }

        return $this->_locationsList;
    }

    /**
     * Get the first town returned for this postcode.
     * Helpful if we need to rough out a quote but we don't have a town to match the postcode
     *
     * @param $postcode
     *
     * @return array|string
     */
    public function getFirstTown( $postcode )
    {
        if (!preg_match( '/^\d{4}$/', $postcode )) {
            return 'bad postcode';
        }
        $towns = $this->getPostcodeTowns( $postcode );
        if (!$towns) {
            return array();
        }

        return trim( $towns[0] );
    }

    /**
     * Returns a list of towns at this postcode, formatted with upper case first letters.
     *
     * @param $postcode
     *
     * @return array|bool|string
     */
    public function getPostcodeTowns( $postcode )
    {
        if (!preg_match( '/^\d{4}$/', $postcode )) {
            return 'bad postcode';
        }
        $locations = $this->getLocations();
        if (empty( $locations[$postcode] )) {
            return false;
        }
        $towns = $locations[$postcode];
        $list  = array();
        foreach ($towns as $t) {
            $list[] = ucwords( strtolower( $t[0] ) );
        }

        return $list;
    }

    public function checkTownInPostcode($town, $postcode)
    {
        $towns = $this->getPostcodeTowns($postcode);
        $town = self::townFilter($town);

        foreach( $towns as $t )
        {
            if ( self::townFilter($t) == $town)
            {
                return true;
            }
        }
        return $towns;
    }

    /**
     * Take a town and return a version without non-alpha, upper case first letters
     *
     * @param $town
     *
     * @return string - Cleansed town string
     */
    public static function townFilter($town)
    {
        return ucwords( preg_replace( '/[^a-z]/', '', strtolower($town)));
    }

    /**
     * Check that this is a good address
     *
     * @param $town
     * @param $postcode
     * @param $state
     *
     * @return array|bool - Return true if all good, error array if npt
     */
    public function addressVerify( $town, $postcode, $state )
    {
        $errors = array();

        $inTown = $this->checkTownInPostcode($town, $postcode);
        // First check that town is in postcode
        if (is_array($inTown))
            $errors[] = '<strong>'.self::townFilter($town)."</strong> is not at postcode <strong>$postcode</strong>. Valid suburbs are '".implode("', '", $inTown)."'";

        // Check that postcode matches state
        $state = self::checkState($state); // Clean if needed
        if ($state != self::getState($postcode))
            $errors[] = "Postcode <strong>$postcode</strong> does not belong in state <strong>$state</strong>";

        if (count($errors))
            return $errors;

        return true;
    }

    /**
     * Given a postcode, return the state.
     * Straya.
     *
     * @param $pcode
     *
     * @return string One of NSW, QLD, ACT, TAS, SA, WA, NT
     */
    public static function getState( $pcode )
    {
        $first = (int) $pcode[0];    // First number
        $pcode = (int) $pcode;        // Type to integer

        if ($first == 1) {
            return 'NSW';
        }

        if ($first == 2) // ACT or NSW
        {
            if (( $pcode >= 2600 && $pcode <= 2618 ) || ( $pcode >= 2900 && $pcode <= 2920 )) {
                return 'ACT';
            }

            return 'NSW'; // Defaults to..
        }

        if ($pcode < 300) {
            return 'ACT';
        }

        if ($first == 3 || $first == 8) {
            return 'VIC';
        }

        if ($first == 4 || $first == 9) {
            return 'QLD';
        }

        if ($first == 5) {
            return 'SA';
        }

        if ($first == 6) {
            return 'WA';
        }

        if ($first == 7) {
            return 'TAS';
        }

        // ACT's 0200-0299 already caught
        if ($first == 0) {
            return 'NT';
        }
    }

	/**
	 * Sometimes a non-abbreviated state name will come through (eg New South Wales)
	 * Convert it to the abbreviated version.
	 *
	 * @param $text
	 *
	 * @return mixed - False if not found, abbreviation if it is
	 */
	public static function abbrevState($text)
	{
        // Possible conversions
		$filter = array(
			'australian capital territory' => 'ACT',
			'australian capitol territory' => 'ACT',
			'new south wales'              => 'NSW',
			'victoria'                     => 'VIC',
			'queensland'                   => 'QLD',
			'south australia'              => 'SA',
			'western australia'            => 'WA',
			'west australia'               => 'WA',
			'northern territory'           => 'NT',
			'tasmania'                     => 'TAS'
		);

		$check = preg_replace( '/[^a-z]/', '', strtolower($text));

		if (empty($filter[$check]))
			return false;

		return $filter[$check];
	}

    /**
     *  Return state code if valid; return false if not
     *
     * @param $state - Abbreviated state
     *
     * @return mixed - false if not found at all, state code if found
     */
	public static function checkState($state)
	{
        // First check if matches normal abbrev
        if (strlen($state) < 4 )
        {
            if (isset(self::$ozStates[$state]))
                return $state;
        }
        return self::abbrevState($state);
	}


    /**
     * Verify parameter is correct format.
     *
     * @param string $val   The value to check
     * @param string $p     The name of the parameter we're checking
     * @param mixed  $check What to expect, and what to say if it's not there [regex, error message], 1 or 0
     *
     * @return mixed - true if valid, error string if not
     */
    protected function _paramValidate( $val, $p, $check )
    {
        if (is_array( $check )) {
            list( $reg, $err ) = $check;

            if (!preg_match( '/' . $reg . '/', $val )) {
                return $p . ' ' . $err;
            }
        }

        return true;
    }

    /**
     * Check in $type array for required parameters.
     * smartSendBook has a 'details' list
     *
     * @param array  $required - List of required parameters
     * @param string $type     - 'request' or 'details'
     *
     * @return array - List of missing parameters
     */
    protected function _checkRequired( $required, $type = 'request' )
    {
        $missing = array();
        foreach ($required as $p => $v) {
            if (!is_array( $v )) {
                if (!isset( $this->_params[$type][$v] )) {
                    $missing[] = $v;
                }
            } else {
                foreach ($v as $sub) {
                    if (!isset( $this->_params[$type][$p][$sub] )) {
                        $missing[$p][] = $v;
                    }
                }
            }
        }
        $this->_missingParams[$type] = $missing;

        return $missing;
    }

    /**
     * Log SOAP request and response
     *
     * @param $soap
     *
     * @return string - Dat alogged
     */
    protected function _logSoap( $soap )
    {
        if ( !self::$SSdebug)
            return '';

        if (empty($soap->__last_response))
        {
            return 'No SOAP response';
        }

        $logString = "Request Headers:\n========================\n\n" . $soap->__last_request_headers . "Request:\n========================\n\n" . $soap->__last_request .
                     "Response Headers:\n========================\n\n" . $soap->__last_response_headers . "Response:\n========================\n\n" . $soap->__last_response . "\n\n\n";
        self::debugLog( 'soap', $logString );

        return $logString;
    }

    /**
     * Given a reference ID, get the label - return the data directly or save it to the optional path specified.
     *
     * @param string $refid - Ref ID returned at booking time
     *
     * @return mixed
     */
    protected function _getLabel( $refid )
    {
	    // TODO: Suppress is bad
        $label    = @file_get_contents( $this->_labelServer . '?want=pdf&devcode=' . $this->developerId . '&refid=' . $refid );
        $head     = $http_response_header;
        $fileName = '';
        foreach ($head as $h) {
            if (preg_match( "/filename='([^']+)'/", $h, $bits )) {
                $fileName = $bits[1];
                break;
            }
        }

        return array( $fileName, $label );
    }

    /**
     *  Publically accessible label grabber
     *
     * @param string $refid
     * @param string $savePath
     *
     * @return array|bool
     */
    public function getLabel( $refid, $savePath = '' )
    {
        // Make sure trailing slash ..
        $savePath = rtrim( $savePath, '/' ) . '/';

        list( $labelname, $labeldata ) = $this->_getLabel( $refid );

        if (!$savePath) {
            return array( $labelname, $labeldata );
        }

        $labelPath = $savePath . $labelname;

        if (!file_exists( $labelPath )) {
            if (!file_put_contents( $labelPath, $labeldata )) {
                smartSendAPI::debugLog( 'labels', 'Could not write ' . $savePath . '/' . $labelname );

                return false;
            }
        }

        return $labelname;
    }

    /**
     * Given a reference ID, hit the label server up for a tracking ID related to the reference.
     *
     * Expected responses are a tracking ID (string), or a 404 error
     *
     * @param $refid - Reference ID returned by BookJob
     *
     * @return string - tracking ID
     */
    protected function _getTrackingId( $refid )
    {
        $trackingId = file_get_contents( $this->_labelServer . '?want=tracking&devcode=' . $this->developerId . '&refid=' . $refid );
        if ($trackingId == 'No such item') {
            return false;
        }

        return $trackingId;
    }

    /**
     * Public getter for tracking ID
     *
     * @param $ref
     *
     * @return string
     */
    public function getTrackingId( $ref )
    {
        return $this->_getTrackingId( $ref );
    }

    public static function debugLog( $file, $data )
    {
        if (self::$SSdebug) {
            error_log( date( "Y-m-d H:i:s" ) . " - $_SERVER[REMOTE_ADDR]\n" . print_r( $data, true ), 3,
                SSLOGS . 'log-' . $file . '.log' );
        }
    }

    /**
     * Given a start date and an end date (optional) return a list of dates with the weekends removed
     *
     * @param string $start
     * @param string $end
     *
     * @return array List of dates
     */
    public static function weekendless( $start = '', $end = '' )
    {
        $list  = array();
        $fluff = 60 * 60 * 10; // 10 hours after GMT

        if (!$start) {
            $start = gmdate( "Y-m-d", time() + $fluff );
        }

        $s = strtotime( $start );

        // Default = 14 days later
        if (!$end) {
            $end = gmdate( "Y-m-d", $s + 86400 * 14 );
        }

        $e = strtotime( $end );

        for ($d = $s; $d <= $e; $d += 86400) {
            if (!in_array( gmdate( 'N', $d ), array( 6, 7 ) )) {
                $list[] = gmdate( "Y-m-d", $d );
            }
        }

        return $list;
    }
}

/**
 * @deprecated
 */
if (!function_exists('smart_send_debug_log')) {
    function smart_send_debug_log( $file, $data )
    {
        smartSendAPI::debugLog( $file, $data );
    }
}

/**
 * Helper to format an array of errors in to a string
 *
 * @param string $errs
 *
 * @return string|void
 */
function smart_send_return_error( $errs = '' )
{
    if (!$errs) {
        return;
    }

    $str = 'Error: ';

    if (is_array( $errs )) {
        $str .= "\n" . implode( "\n", $errs );
    } else {
        $str .= $errs;
    }

    return $str;
}

/**
 * Fun little function to convert object to array. Keeps drilling down and converting to arrays.
 * This is called 'recursive' :)
 *
 * @param $object
 *
 * @return array
 */
function smart_send_objectToArray( $object )
{
    if (!is_object( $object ) && !is_array( $object )) {
        return $object;
    }

    return array_map( 'smart_send_objectToArray', (array) $object );
}
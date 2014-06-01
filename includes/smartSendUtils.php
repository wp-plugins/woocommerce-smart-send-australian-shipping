<?php

class smartSendUtils
{
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

	public static $ssFixedSatchelParams = array(
		'500gm fixed price road satchel' => array( '2', '22', '33', '0.5' ),
		'1kg fixed price road satchel'   => array( '4', '27', '34', '1' ),
		'3kg fixed price road satchel'   => array( '7', '33', '42', '3' ),
		'5kg fixed price road satchel'   => array( '7', '43', '59', '5' )
	);

	public static $ssPrioritySatchelParams = array(
		'3kg PRIORITY SATCHEL' => array( '5', '39', '33', '3'),
		'5kg PRIORITY SATCHEL' => array( '9', '48', '42', '5' )
	);
	
	private $liveWSDL = 'http://developer.smartsend.com.au/service.asmx?wsdl';
	private $testWSDL = 'http://uat.smartservice.smartsend.com.au/service.asmx?wsdl';

	// TTL in days for locations cache
	private $cacheTTL = 1;

	private $locationsCacheFile;

	// Soap connection object
	private $soapClient;

	// Auth details
	private $username = '';
	private $password = '';

	// List of locations
	private $locationList = array();

	// Coming from
	private $postcodeFrom;
	private $suburbFrom;
	private $stateFrom;

	// Going to
	private $postcodeTo;
	private $suburbTo;
	private $stateTo;

	// Arrays of params for quote
	// Each item contains:
	// 	Description: One of -
	// 		Carton, Satchel/Bag, Tube, Skid, Pallet, Crate, Flat Pack, Roll, Length, Tyre/Wheel, Furniture/Bedding, Envelope
	// 	Depth
	// 	Height
	// 	Length
	// 	Weight
	private $quoteItems;

	// Optional promo code
	private $promoCode;

	// Amount to insure
	private $transportAssurance = 0;

	// User type. Options are:
	// EBAY, PROMOTION, VIP
	private $userType = 'VIP';

	// Wether taillift required, if so what and where. Options are:
	// NONE, PICKUP, DELIVERY, BOTH
	private $tailLift = 'NONE';

	// Optional
	private $promotionalCode = '';
	private $onlineSellerId = '';

	private $receiptedDelivery = 'false';

	// Object containing the results of last quote
	private $lastQuoteResults;

	public static $_debug = true;

	/**
	 * Initialise the Smart Send SOAP API
	 *
	 * @param string $username Smart Send VIP username
	 * @param string $password Smart Send VIP password
	 * @param bool   $useTest  Whether to use the test API. Default is false.
	 *
	 * @throws Exception
	 */
	
	public function __construct( $username = NULL, $password = NULL, $useTest = false )
	{
		if( is_null($username) && is_null($password) )
		{
			throw new Exception( 'Missing username and password.');
		}
		$this->username = $username;
		$this->password = $password;

		// Set to test server if username starts with 'test@'
		if( preg_match( '/^test@/', $username ) )
			$useTest = true;

		$this->ssWSDL = $useTest ? $this->testWSDL : $this->liveWSDL;

		smart_send_debug_log( 'utilsInit', array( 'username: '.$this->username, 'password: '.$this->password, 'endpoint: '.$this->ssWSDL));

		$this->soapClient = new SoapClient( $this->ssWSDL );
		$this->locationsCacheFile = dirname(__FILE__) . '/../locations.data';
	}

	public function getQuote()
	{
		$required = array(
			'postcodeFrom',
			'suburbFrom',
			'stateFrom',
			'postcodeTo',
			'suburbTo',
			'stateTo',
			'userType',
			'tailLift'
		);

		foreach( $required as $req )
		{
			if( is_null( $this->$req ) )
				throw new Exception( "Cannot get quote without '$req' parameter" );
		}

		if( $this->userType == 'EBAY' && is_null($this->onlineSellerId ))
				throw new Exception( 'Online Seller ID required for Ebay user type.' );

		if( $this->userType == 'PROMOTION' && is_null($this->promotionalCode ))
				throw new Exception( "Promotional code required for user type 'PROMOTION'." );

		$quoteParams['request'] = array(
			'VIPUsername'        => $this->username,
			'VIPPassword'        => $this->password,
			'PostcodeFrom'       => $this->postcodeFrom,
			'SuburbFrom'         => $this->suburbFrom,
			'StateFrom'          => $this->stateFrom,
			'PostcodeTo'         => $this->postcodeTo,
			'SuburbTo'           => $this->suburbTo,
			'StateTo'            => $this->stateTo,
			'UserType'           => $this->userType,
			'OnlineSellerID'     => $this->onlineSellerId,
			'PromotionalCode'    => $this->promotionalCode,
			'ReceiptedDelivery'  => $this->receiptedDelivery,
			'TailLift'           => $this->tailLift,
			'TransportAssurance' => $this->transportAssurance,
			'DeveloperId'        => '929fc786-8199-4b81-af60-029e2bca4f39',
			'Items'              => $this->quoteItems
		);

		smart_send_debug_log( 'params', $quoteParams['request'] );

		$this->lastQuoteResults = $this->soapClient->obtainQuote( $quoteParams );

		smart_send_debug_log( 'results', $this->lastQuoteResults );

		return $this->lastQuoteResults;
	}

	/**
	 * @param array $fromDetails Array of 'from' address details: [ postcode, suburb, state, ]
	 */
	public function setFrom( $fromDetails )
	{
		list( $this->postcodeFrom, $this->suburbFrom, $this->stateFrom ) = $fromDetails;
	}

	/**
	 * @param array $toDetails Array of 'to' address details: [ postcode, suburb, state, ]
	 */
	public function setTo( $toDetails )
	{
		list( $this->postcodeTo, $this->suburbTo, $this->stateTo ) = $toDetails;
	}

	/**
	 * @param $param
	 * @param $value
	 *
	 * @internal param \Set $string optional parameters:
	 *           userType:                        EBAY, CORPORATE, PROMOTION, CASUAL, REFERRAL
	 *           onlineSellerID:            Only if userType = EBAY
	 *           promotionalCode:        Only if userType = PROMOTIONAL
	 *           receiptedDelivery:    Customer signs to indicate receipt of package
	 *           tailLift:                        For heavy items; either a tail lift truck or extra staff
	 *           transportAssurance:    If insurance is required
	 */
	
	public function setOptional( $param, $value )
	{
		$allowed = array(
			'userType'           => array( 'EBAY', 'PROMOTIONAL', 'VIP' ),
			'onlineSellerId'     => '',
			'promotionalCode'    => '',
			'receiptedDelivery'  => array( 'true', 'false' ),
			'tailLift'           => array( 'NONE', 'PICKUP', 'DELIVERY', 'BOTH' ),
			'transportAssurance' => ''
		);
		if( !in_array( $param, array_keys( $allowed ) ) )
		{
			echo 'Not a settable parameter';
			return;
		} 
		if( is_array( $allowed[$param] ) && !in_array( $value, $allowed[$param]) )
		{
			echo "'$value' is not a valid value for '$param'";
			return;
		}
		$this->$param = $value;
	}

	/**
	 * Add items to be shipped
	 *
	 * @param array $itemData [ Description, Depth, Height, Length, Weight ]
	 *
	 * @throws Exception
	 */
	public function addItem( array $itemData )
	{
		$descriptions = self::$ssPackageTypes;

		if( !in_array( $itemData['Description'], $descriptions ))
			throw new Exception( 'Item must be one of: ' . implode( ', ', $descriptions ) );

		// Use the best-fitting fixed price or priority satchel based on dimensions given
		if (in_array($itemData['Description'], array( 'Best fixed price road satchel', 'Best priority satchel')))
		{
			list( $itemDescription, $itemWeight, $itemDepth, $itemHeight, $itemLength) = array_values($itemData);

			if ($itemData['Description'] == 'Best fixed price road satchel')
				$bestfitArr = self::$ssFixedSatchelParams;
			else
				$bestfitArr = self::$ssPrioritySatchelParams;

			// Step through each option, use the first one that fits all params
			foreach( $bestfitArr as $satch => $satchParams )
			{
				list( $sDepth, $sHeight, $sLength, $sWeight ) = $satchParams;

				// Check for weight fit, continue to next if too heavy for this satchel
				if ($itemWeight > $sWeight )
					continue;

				// Compare the satchel with the item dimensions
				if (!self::dimsFit( array( $itemDepth, $itemHeight, $itemLength), array($sDepth, $sHeight, $sLength)))
					continue;

				// Passed through, we have a winner
				$newDescription = $satch;
				list($newDepth, $newHeight, $newLength, $newWeight ) = array( $sDepth, $sHeight, $sLength, $sWeight);
				break;
			}

			if (!isset($newDescription))
				throw new Exception( 'No best-fitting satchel could be found for this items dimensions and/or weight' );
			else {
				$itemData['Description'] = $newDescription;
				$itemData['Depth'] = $newDepth;
				$itemData['Height'] = $newHeight;
				$itemData['Length'] = $newLength;
				$itemData['Weight'] = $newWeight;
			}
		}
		else
			$itemData['Weight'] = ceil($itemData['Weight']); // Ensure weight is round figure

		$this->quoteItems[] = $itemData;
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
		if( !$cachedRequested )
			return $this->_locationsToArray();
		else
		{
			if( file_exists( $this->locationsCacheFile ) )
			{
				// Return cached data if not expired
				$fileAge = time() - filemtime( $this->locationsCacheFile );
				if( $fileAge < $this->cacheTTL * 3600 )
					$locationsList = unserialize(file_get_contents($this->locationsCacheFile));
			}
			// Either expired or doesn't exist
			if( !isset( $locationsList ) )
			{
				$locationsList = $this->_locationsToArray();
				file_put_contents( $this->locationsCacheFile, serialize( $locationsList ) );
			}
			return $locationsList;
		}
	}

	// Request locations from SOAP object and convert to an array
	// return: array $this->locationList
	protected function _locationsToArray()
	{
		$locations = $this->soapClient->GetLocations();
		foreach ($locations->GetLocationsResult->Location as $location)
		{
			$postcode = sprintf( "%04d", $location->Postcode );
			$this->locationList[$postcode][] = array( $location->Suburb, $location->State );
		}
		return $this->locationList;
	}

	// Get the first town in the list for a postcode
	public function getFirstTown( $postcode )
	{
		if( !preg_match( '/^\d{4}$/', $postcode ) ) return false;
		$locations = $this->getLocations();
		return trim($locations[$postcode][0][0]);
	}

	public static function getState( $pcode )
	{
		$first = (int) $pcode[0]; 	// First number
		$pcode = (int) $pcode;		// Type to integer

		if( $first == 1 ) return 'NSW';

		if( $first == 2 ) // ACT or NSW
		{
			if( ( $pcode >= 2600 && $pcode <= 2618 ) || ( $pcode >= 2900 && $pcode <= 2920 ) ) return 'ACT';
			return 'NSW'; // Defaults to..
		}

		if( $pcode < 300 ) return 'ACT';

		if( $first == 3 || $first == 8 ) return 'VIC';

		if( $first == 4 || $first == 9 ) return 'QLD';

		if( $first == 5 ) return 'SA';

		if( $first == 6 ) return 'WA';

		if( $first == 7 ) return 'TAS';

		// ACT's 0200-0299 already caught
		if( $first == 0 ) return 'NT';
	}

	/**
	 * Given two arrays, determine if $arr1 dimensions will fit within $arr2 dimensions
	 *
	 * @param $arr1 - Must have 3 fields
	 * @param $arr2 - Must have 3 fields
	 *
	 * @return bool - Whether $arr1 first in $arr2, true or false
	 */
	public static function dimsFit( $arr1, $arr2 )
	{
		if (count($arr1) != 3 || count($arr2) != 3 )
			return 'Error: Each array must have 3 dimensions';

		// First, simple volume check
		$vol1 = $arr1[0]*$arr1[1]*$arr1[2];
		$vol2 = $arr2[0]*$arr2[1]*$arr2[2];

		if ($vol1 > $vol2 )
			return false;

		// Compare all combos of $arr2 to see if any combos can contain $arr1
		$combos = array(
			array(0, 1, 2), array(0, 2, 1),
			array(1, 0, 2), array(1, 2, 0),
			array(2, 0, 1), array(2, 1, 0)
		);

		foreach( $combos as $c)
		{
			// Check for fit - if we get any combination of $arr2 containing $arr1 return true
			if ($arr1[0] <= $arr2[$c[0]] && $arr1[1] <= $arr2[$c[1]] && $arr1[2] <= $arr2[$c[2]])
				return true;
		}
		return false;
	}
}

function smart_send_debug_log( $file, $data )
{
	if (smartSendUtils::$_debug)
		error_log( date( "Y-m-d H:i:s" ) . " - $_SERVER[REMOTE_ADDR]\n" . print_r( $data, true ), 3, dirname( __FILE__ ) . '/../log-'.$file.'.log' );
}
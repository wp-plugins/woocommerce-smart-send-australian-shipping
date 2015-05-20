<?php
/**
 * Smart Send PHP SDK 2.0
 *
 * Extend the API class to get booking quotes through the Smart Send API.
 *
 * Checks that all needed parameters are present (and correct), makes the booking and then caches results
 * and original request for later use by booking functions.
 *
 * @package   Smart Send PHP SDK
 * @author    Paul Appleyard <paul@smartsend.com.au>
 * @link      http://digital.smartsend.com.au/sdk/php
 * @see       smartSendApi
 * @see       smartSendBook
 * @see       smartSendTrack
 *
 * @copyright 2015 Smart Send Pty Ltd
 */


/**
 * Class smartSendQuote
 */
class smartSendQuote extends smartSendAPI
{

	// Coming from - deprecated, now lives in _params['request']
	private $postcodeFrom;
	private $suburbFrom;
	private $stateFrom;

	// Going to - deprecated, now lives in _params['request']
	private $postcodeTo;
	private $suburbTo;
	private $stateTo;

	// User type. Options are:
	// EBAY, PROMOTION, VIP
	private $userType = 'VIP';

	// Optional
	private $promotionalCode = '';
	private $onlineSellerId = '';

    /**
     * @var Object containing the results of last quote
     */
	protected $_lastQuoteResults;

    /**
     * @var array Params that will be passed via SOAP to the API
     */
    protected $_params = array();

	/**
	 * @var string Unique reference from the calling app; eg, order ID
	 */
	protected $_appReference;

	/**
	 * @var array If packing, will record instructions for packing shipping at fulfillment
	 */
	protected $_packingInstructions;

	protected $_packages = array();

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
		parent::__construct($username, $password, $useTest);

		// Set defaults
		$this->_params['request'] = array(
			'VIPUsername'        => $username,
			'VIPPassword'        => $password,
			'TransportAssurance' => 0,
			'UserType'           => $this->userType,
			'TailLift'           => 'NONE',
			'PromotionalCode'    => $this->promotionalCode,
			'OnlineSellerId'     => $this->onlineSellerId,
			'ReceiptedDelivery'  => 0,
			'DeveloperId'        => $this->developerId/*,
			'callsrc'            => CALLSRC,
			'callver'            => CALLVER*/
		);
	}

	/**
	 *  Check vanilla required plus some specials
	 *
	 * @param array  $requiredList
	 * @param string $type
	 *
	 * @return array - Missing parameters
	 */
	protected function _checkRequired($requiredList = array(), $type = 'request')
	{
		$missing = parent::_checkRequired( $this->_requestRequired, 'request');

		if (!empty($this->_params['request']['UserType']))
		{
			if( $this->_params['request']['UserType'] == 'PROMOTION' && empty($this->_params['request']['PromotionalCode']))
				$missing[] = 'PromotionalCode';

			if ($this->_params['request']['UserType'] == 'EBAY' && empty($this->_params['request']['OnlineSellerId']))
				$missing[] = 'OnlineSellerId';
		}

		return $missing;
	}

    /**
     * Hit the SOAP function for a quote
     *
     * Checks we have all we need, then asks for a quote.
     *
     * @return object Populates the quote result
     */
	public function getQuote()
	{
		// Check for required
		$missing = $this->_checkRequired($this->_requestRequired, 'request');

		$this->_checkItemClasses();

		if( count($missing))
			return array( 'error' => "Missing parameters: \n".implode( "\n -", $missing) );

		smart_send_debug_log( 'quote', $this->_params['request'] );

		$soap = $this->_connectSoap();
		$this->_lastQuoteResults = $soap->ObtainQuote($this->_params);

		$this->_cacheParams();

		smart_send_debug_log( 'quote', $this->_lastQuoteResults );

		// Log to Soap and also log soap stuff to quote
		if (self::$SSdebug) {
			smart_send_debug_log( 'quote', $this->_logSoap($soap) );
		}

		return $this->_lastQuoteResults;
	}

	/**
     * Retrieve request and result of a quote
     *
	 * @param $priceId - Price ID of original quote
	 *
	 * @return array|bool False if not there, otherwise return $res = quote result, $req = quote request params
	 */
	public static function getCached($priceId)
	{
		$cachePath = SSCACHE.'quotes/';
		if (!file_exists($cachePath.'req_'.$priceId) || !file_exists($cachePath.'res_'.$priceId))
			return false;

		// Request params from quote
		$req = unserialize(file_get_contents($cachePath.'req_'.$priceId));

		// Result of quote
		$res = unserialize(file_get_contents($cachePath.'res_'.$priceId));

		return array( $res, $req);
	}

	/**
	 * Cache both params and quote results, index is Price ID
	 */
	protected function _cacheParams()
	{
		if( $quotes = $this->_extractQuotes())
		{
			// Serialised version of params array
			$requestParams = serialize($this->_params['request']);

			foreach( $quotes as $quote )
			{
				$priceID = $quote->PriceID;

				// Cache key should be unique to this order and this price ID
				if (!empty($this->_appReference))
					$priceID .= '_'.$this->_appReference;

				$cacheParams = SSCACHE.'quotes/req_'.$priceID;
				file_put_contents($cacheParams, $requestParams);

				// Now ze quote
				$quoteData = serialize( smart_send_objectToArray($quote) );
				$cacheQuote = SSCACHE.'quotes/res_'.$priceID;
				file_put_contents($cacheQuote, $quoteData);

				// Also, packing instructions if present
				if (count($this->_packingInstructions))
				{
					$cachePacking = SSCACHE.'quotes/packing_'.$priceID;
					file_put_contents($cachePacking, serialize($this->_packingInstructions));
				}
			}
		}
	}

	/**
	 * Given the quote object is set, extract quotes therein
	 *
	 * @return array/boolean - Array of quotes, or false
	 */
	protected function _extractQuotes()
	{
		$qr = $this->_lastQuoteResults;

		if (!empty($qr->ObtainQuoteResult->Quotes->Quote))
		{
			$quotes = $qr->ObtainQuoteResult->Quotes->Quote;

			$useQuotes = array();

			if ( !empty($quotes) && !is_array( $quotes ) )
				$useQuotes[0] = $quotes;
			else
				$useQuotes = $quotes;

			return $useQuotes;
		}

		return false;
	}


    /**
     * Return a standardised quote result array
     *
     * @return object Return last result or get a new one
     */
	public function lastResult()
	{
		if ( !($qr = $this->_lastQuoteResults))
		{
			$qr = $this->getQuote();
		}
		return $qr;
	}

    /**
     * Set the last result
     *
     * @param $obj
     */
	public function setLastResult($obj)
	{
		$this->_lastQuoteResults = $obj;
	}

    /**
     * Get a quote, and return a nicely formatted array
     *
     * @return array List of quotes to show, or error with error message
     */
	public function niceResult()
	{
		$res = $this->lastResult();

		// An error?
		if ( is_object($res) && $res->ObtainQuoteResult->StatusCode != 0 && empty( $res->ObtainQuoteResult->Quotes->Quote ) ) {
			$returnError = $res->ObtainQuoteResult->StatusMessages->string;
			if ( is_array( $returnError ) )
				$returnError = implode( ", ", $returnError );
		}
		elseif (is_array($res) && isset($res['error']))
			return array( 'error', $res['error']);

		if (isset($returnError))
			return array( 'error', $returnError);

		// We have quote-age!
		$useQuotes = $this->_extractQuotes();

		if (count($useQuotes))
		{
			foreach( range( 1, count($useQuotes)) as $q )
			{
				$i = $q-1;
				$useQuotes[$i]->Discount = sprintf("%1.2f", $useQuotes[$i]->Discount );
				$useQuotes[$i]->TotalDiscount = sprintf("%1.2f", $useQuotes[$i]->TotalDiscount );
				$useQuotes[$i]->TotalGST = sprintf("%1.2f", $useQuotes[$i]->TotalGST );
				$useQuotes[$i]->TotalPrice = sprintf("%1.2f", $useQuotes[$i]->TotalPrice );
				$useQuotes[$i]->TotalTransportAssurance = sprintf("%02f", $useQuotes[$i]->TotalTransportAssurance );
			}
		}

		return $useQuotes;
	}

	/**
     * Set the 'origin address' details of the quote
     *
	 * @param array $fromDetails Array of 'from' address details: [ postcode, suburb, state, ]
	 */
	public function setFrom( $fromDetails )
	{
		// Deprecated - we now set with $this->_setParam('postcodeFrom', 2548, 'request') etc
		list( $this->postcodeFrom, $this->suburbFrom, $this->stateFrom ) = $fromDetails;

		$this->_setParam( 'PostcodeFrom', $this->postcodeFrom );
		$this->_setParam( 'SuburbFrom', $this->suburbFrom );
		$this->_setParam( 'StateFrom', $this->stateFrom );
	}

	/**
     * Set the destination particulars
     *
	 * @param array $toDetails Array of 'to' address details: [ postcode, suburb, state, ]
	 *
	 * @return string/boolean True for success or error message
	 */
	public function setTo( $toDetails )
	{
		list( $this->postcodeTo, $this->suburbTo, $stateTo ) = $toDetails;

		$this->suburbTo = ucwords(strtolower($this->suburbTo));

		$this->stateTo = strtoupper($stateTo);

		if (!in_array($this->stateTo, array_keys(self::$ozStates)))
		{
			$cleanState = self::abbrevState($stateTo);
			if (!$cleanState)
				return "The state '$stateTo' is not a valid Australian state";
		}

		if ($this->stateTo != self::getState($this->postcodeTo))
			return "Postcode '".$this->postcodeTo."' doesn't come from ".$this->stateTo.".";

		if (strlen($this->postcodeTo) > 4 )
			return "Suburb must be 4 digits";

		// Check if postcode is in that state
		if ($this->stateTo != smartSendAPI::getState($this->postcodeTo))
			return 'Destination postcode '.$this->postcodeTo.' is not in '.$this->stateTo;

		// Check if town is at that suburb
		$goodTowns = $this->getPostcodeTowns($this->postcodeTo);
		if ($goodTowns === false)
		{
			return 'No towns are at '/$this->postcodeTo;
		}

		if (!in_array(ucwords( strtolower($this->suburbTo)), $goodTowns))
			return "Destination '".$this->suburbTo."' is not at postcode ".$this->postcodeTo.". Valid town/s are ".implode(", ", $goodTowns);

		$this->_setParam( 'PostcodeTo', $this->postcodeTo );
		$this->_setParam( 'SuburbTo', $this->suburbTo );
		$this->_setParam( 'StateTo', $this->stateTo );

		return true;
	}

	/**
	 *  Set $string optional parameters:
	 *  userType:            EBAY, CORPORATE, PROMOTION, CASUAL, REFERRAL
	 *  onlineSellerID:      Only if userType = EBAY
	 *  promotionalCode:     Only if userType = PROMOTIONAL
	 *  receiptedDelivery:   Customer signs to indicate receipt of package
	 *  tailLift:            For heavy items; either a tail lift truck or extra staff
	 *  transportAssurance:  If insurance is required
	 *
	 * @param $param
	 * @param $value
     *
     * @deprecated
	 *
	 * @return bool|mixed
	 */
	
	public function setOptional( $param, $value )
	{
		$res = $this->_setParam($param, $value, 'request' );

		if ($res !== true )
			return $res;

		$this->$param = $value;

		return true;
	}

	public function setAppReference($ref)
	{
		$this->_appReference = $ref;
	}


    /**
     * Add items to be shipped
     *
     * @param array $itemData [ Description, Depth, Height, Length, Weight ]
     *
     * @return string/boolean Error message or true if successfully set
     */
	public function addItem( array $itemData )
	{
		$descriptions = self::$ssPackageTypes;

		if( !in_array( $itemData['Description'], $descriptions ))
			return $itemData['Description'].' invalid - item must be one of: ' . implode( ', ', $descriptions );

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
				return 'No best-fitting satchel could be found for this items dimensions and/or weight';
			else {
				$itemData['Description'] = $newDescription;
				$itemData['Depth']       = $newDepth;
				$itemData['Height']      = $newHeight;
				$itemData['Length']      = $newLength;
				$itemData['Weight']      = $newWeight;
			}
		}
		elseif ( in_array( $itemData['Description'], array_keys(self::$ssFixedSatchelParams)))
		{
			list( $itemData['Depth'], $itemData['Height'], $itemData['Length'], $itemData['Weight'] ) = self::$ssFixedSatchelParams[$itemData['Description']];
		}
		else
			$itemData['Weight'] = ceil($itemData['Weight']); // Ensure weight is round figure if not pre-paid

		$this->_params['request']['Items'][] = $itemData;

        return true;
	}

	/**
	 * @return mixed - Array of items or false
	 */
	public function getItemList()
	{
		if (!empty($this->_params['request']['Items']))
			return $this->_params['request']['Items'];
		return false;
	}

	/**
	 * Given an item in the format you'd submit to addItem, and a list of packages, set items as the packages
	 *
	 * @param $qty      - Quantity of items
	 * @param $itemName - Title of product
	 * @param $itemData - Shipping-related data for product. Format [ Description, Depth, Height, Length, Weight ]
	 * @param $packages - Format Name => [ Fits, Weight ] Weight (max. weight) is optional
	 *
	 * Process and make submissions to addItem
	 *
	 * @return array - List of items/packages
	 */
	public function packageItem($qty, $itemName, $itemData, $packages)
	{
		// If no packages have been set up for this product
		if (!$packages)
		{
			$ret = array();
			// Add the cart item/s to the list for API calculations
			foreach ( range( 1, $qty ) as $blah )
			{
				$res = $this->addItem( $itemData );
				if ($res !== true )
					return $res;
				$ret[] = $itemData;
			}
			return $ret;
		}

		$this->_packOne( $qty, $itemName, $itemData, $packages );
	}

	/**
	 * Create packing list, apportion items to packages. Recurse until all items are packed.
	 *
	 * @param int $qty         - Actual number of items to pack
	 * @param string $itemName - Human-readable package description
	 * @param array $itemData  - [ Description, Depth, Height, Length, Weight ]
	 * @param array $packages  - Format [ $fits, [Name, Length, Width, ..Depth, ..Weight ]] (Depth and max weight are optional)
	 */
	protected function _packOne($qty, $itemName, $itemData, $packages )
	{
		$itemName = sanitize_title($itemName);
		if (!isset($this->_packages[$itemName]))
		{
			asort( $packages );
			$this->_packages[$itemName] = $packages;
		}

		// Move up from smallest to largest package (by indicated capacity)
		// If none are bigger than the quantity, we just use the biggest and move on
		foreach( $this->_packages[$itemName] as $pkg => $pData)
		{
			// Check if the number fits
			$thisPackage = $pkg;
			list( $thisFits, $pkgAttr) = $pData;
			$maxWeight = $pkgAttr['maxweight'];

			if ($qty < $thisFits && $qty*$itemData['weight'] < $maxWeight)
				break;
		}

		// In to this package goes either $thisFits or floor($maxweight/item weight)
		$pkgTakes = $maxWeight ? floor( $maxWeight / $itemData['Weight'] ) : $thisFits;
		$pkgWeight = $itemData['Weight']*$pkgTakes;

		// Add packing instruction
		$this->_packingInstructions[] = array( $pkg, $pkgTakes);

		// Add item to quote list: $itemData [ Description, Depth, Height, Length, Weight ]
		$this->addItem( array(
			'Description' => $pkgAttr['smartsendClass'],
			'Length' => $pkgAttr['smartsendLength'],
			'Height' => $pkgAttr['smartsendWidth'],
			'Depth' => $pkgAttr['smartsendDepth'],
			'Weight' => $pkgWeight
		));

		// More to pack? Deduct from $qty and send on
		$newQty = $qty - $pkgTakes;

		if ($newQty)
		{
			$this->_packOne($newQty, $itemName, $itemData, $packages);
		}
	}


	/**
	 * Go through registered items and check for fixed price / normal class shipping types.
	 * Convert fixed price items to 'Satchel/Bag' if they are.
	 */
	protected function _checkItemClasses()
	{
		$items = $this->_params['request']['Items'];
		if (count($items) == 1)
			return;

		$itemTypes = array();

		$normal = false;
		$fixedPrice = array();

		foreach($items as $item)
		{
			$type = $item['Description'];

			if (preg_match( '/fixed price/', $type))
			{
				if (!isset($fixedPrice[$type]))
					$fixedPrice[$type] = 0;

				$fixedPrice[$type]++;
			}
			else
				$normal = true;
		}

		// Change fixed price to 'Satchel/Bag'
		if ($normal === true && count($fixedPrice) || count(array_keys($fixedPrice)) > 1 )
		{
			foreach($items as $i => $item)
			{
				$type = $item['Description'];
				if (preg_match( '/fixed price/', $type))
				{
					$this->_params['request']['Items'][$i]['Description'] = 'Satchel/Bag';
					$this->_params['request']['Items'][$i]['Weight'] = ceil($this->_params['request']['Items'][$i]['Weight']);
				}
			}
		}
	}

	/**
	 * Given two arrays, determine if $arr1 dimensions will fit within $arr2 dimensions.
     * Used for best fit in to satchels
	 *
	 * @param $arr1 - Must have 3 fields
	 * @param $arr2 - Must have 3 fields
	 *
	 * @return bool - Whether $arr1 fits in $arr2, true or false
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

	/**
	 * Determine potential capacity of $arr2 into $arr1
	 *
	 * Will return 0 (zero) if some dimensions are missing
	 *
	 * @param $arr1 - What will fit
	 * @param $arr2 - What to fit in to
	 *
	 * @return bool|float|int - Either false (won't fit) or a number (0 or more)
	 */
	public static function volumeFit( $arr1, $arr2 )
	{
		if (empty($arr1[2]) || empty($arr2[2]) )
			return 0;

		$vol1 = $arr1[0]*$arr1[1]*$arr1[2];
		$vol2 = $arr2[0]*$arr2[1]*$arr2[2];

		if ($vol1 > $vol2 )
			return 'toobig';

		if (!self::dimsFit($arr1, $arr2))
			return 'toobig';

		$fits = floor($vol2/$vol1);

		// Weight check
		if (!empty($arr1[3]) && !empty($arr2[3]))
		{
			$w1 = $arr1[3];
			$w2 = $arr2[3];

			if ($w1 > $w2 )
				$fits = 'tooheavy';

			// Maximum is how many will fit by weight
			else if ($w1*$fits > $w2)
				$fits = floor($w2 / $w1);
		}
		return $fits;
	}

	public function __get($k)
	{
		if (isset($this->$k))
			return $this->$k;
	}
}
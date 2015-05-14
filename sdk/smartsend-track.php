<?php

/**
 * Class smartSendTrack
 *
 * Get tracking info for shipment
 */
class smartSendTrack extends smartSendAPI
{
    protected $_trackResponse;

    public function __construct( $username = NULL, $password = NULL, $usetest = false )
    {
        parent::__construct( $username, $password, $usetest );
    }

    public function getTracking($number)
    {
        $soap = $this->_connectSoap();
        $request = array( 'request' => array( 'VIPUsername' => $this->username, 'VIPPassword' => $this->password, 'TrackingNumber' => $number ) );
        self::debugLog('tracking', $request);
        $resp = $soap->TrackConsignment($request);
        $this->_trackResponse = $resp->TrackConsignmentResult;

        self::debugLog('tracking', $this->_trackResponse);

        return $this->_trackResponse;
    }

    public function getHTML()
    {
        return $this->_trackResponse->TrackingHTML;
    }

    public function getPlain()
    {
        return strip_tags($this->getHTML());
    }

    public function getMessages()
    {
        return $this->_trackResponse->StatusMessages;
    }
}
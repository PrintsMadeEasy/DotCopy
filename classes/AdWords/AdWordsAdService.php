<?php

// http://code.google.com/intl/de-CH/apis/adwords/docs/developer/AdService.html

class AdWordsAdService extends AdWordsBase {
  	
  	private $adObjArr = array();
  	private $adStatsObjArr = array();
  	
  	
  	private $currentAdObj;
  	private $currentAdStatsObj;
  	private $adCriteria_Key;
  	private $adStats_Key;

	function __construct() {
		
		$this->webServiceName = "AdService";
		parent::__construct();	
		
		$this->adStats_Key = "^SOAPENV:ENVELOPE^SOAPENV:BODY^GETADSTATSRESPONSE^GETADSTATSRETURN";

	}
	
	
	// Returns an array of AdWordsStatsRecord Objects
	function getAllAds(array $adGroupIdArr) {
		
		$this->adCriteria_Key = "^SOAPENV:ENVELOPE^SOAPENV:BODY^GETALLADSRESPONSE^GETALLADSRETURN";
 
		$this->adObjArr = array();
		
		$request_xml = "<getAllAds>\n";
		
		foreach($adGroupIdArr as $thisAdGroupId)
			$request_xml .= "<adGroupIds>$thisAdGroupId</adGroupIds>\n";
		
		$request_xml .= "</getAllAds>\n";
		
		$this->soapRequest($request_xml);	
		
		return $this->adObjArr;
	}
	
	// Returns an array of AdWordsStatsRecord Objects
	function getActiveAds(array $adGroupIdArr) {
		
		$this->adCriteria_Key = "^SOAPENV:ENVELOPE^SOAPENV:BODY^GETACTIVEADSRESPONSE^GETACTIVEADSRETURN";
 
		$this->adObjArr = array();
		
		$request_xml = "<getActiveAds>\n";
		
		foreach($adGroupIdArr as $thisAdGroupId)
			$request_xml .= "<adGroupIds>$thisAdGroupId</adGroupIds>\n";
		
		$request_xml .= "</getActiveAds>\n";
		
		$this->soapRequest($request_xml);	
		
		return $this->adObjArr;
	}

	// Returns an array AdWords Stats Objects
	function getAdStats($adGroupId, array $adIdsArr, $startDayTimeStamp, $endDayTimeStamp) {
		
		$this->adStatsObjArr = array();

		$request_xml = "<getAdStats>
							<adGroupId>" . $adGroupId . "</adGroupId>";
		
		foreach($adIdsArr as $thisAdId)
			$request_xml .= "<adIds>" . $thisAdId . "</adIds>";
			
		$request_xml .= "<startDay>" . $this->convertUnixTimeStampToGoogleTimeStamp($startDayTimeStamp) . "</startDay>
							<endDay>" . $this->convertUnixTimeStampToGoogleTimeStamp($endDayTimeStamp) . "</endDay>
						</getAdStats>"; 
		
		$this->soapRequest($request_xml);	
	
		return $this->adStatsObjArr;
	}

	// Only ths Staus Field can updated... all of the other fields will be ingored.
	// If you want to change an Ad, you have to create a new one and pause the old one.
	function updateAds(array $adObjArr){
		
		$request_xml = "<updateAds>";
		
		foreach($adObjArr as $thisAdObj){

			if(get_class($thisAdObj) != "AdWordsTextAd")
				throw new Exception("updateAds can only take an array of campaign objects.");
			
			$request_xml .= "<ads>";
			$request_xml .= $thisAdObj->getTextAdXml();
			$request_xml .= "</ads>";
		}
		$request_xml .= "</updateAds>";

		$this->soapRequest($request_xml);	
	}
	
	
	function addAds($adGroupId, array $adObjArr){
		
		$this->adCriteria_Key = "^SOAPENV:ENVELOPE^SOAPENV:BODY^ADDADSRESPONSE^ADDADSRETURN";
 
		$this->adObjArr = array();
		
		$request_xml = "<addAds>";
		
		foreach($adObjArr as $thisAdObj){

			if(get_class($thisAdObj) != "AdWordsTextAd")
				throw new Exception("updateAds can only take an array of campaign objects.");
			
			$request_xml .= "<ads>";
			$request_xml .= $thisAdObj->getTextAdXml();
			$request_xml .= "</ads>";
		}
		$request_xml .= "</addAds>";

		$this->soapRequest($request_xml);
		
		return $this->adObjArr;
	}

  
	function parseXMLStructure($_parser, $data) {

		// Define all possible tag structures with a carrot separating each tag name.
		if ($this->_curTag == ($this->adCriteria_Key . "^ADGROUPID")) {
			$this->currentAdObj->adGroupId = $data;	
		}
		else if ($this->_curTag == ($this->adCriteria_Key . "^ADTYPE")) {
			$this->currentAdObj->adType = $data;	
		}
		else if ($this->_curTag == ($this->adCriteria_Key . "^DESCRIPTION1")) {
			$this->currentAdObj->description1 .= $data;	// Use ".=" in case of an &amp; entity.
		}
		else if ($this->_curTag == ($this->adCriteria_Key . "^DESCRIPTION2")) {
			$this->currentAdObj->description2 .= $data;	// Use ".=" in case of an &amp; entity.
		}
		else if ($this->_curTag == ($this->adCriteria_Key . "^DESTINATIONURL")) {
			$this->currentAdObj->destinationUrl .= $data;	// Use ".=" in case of an &amp; entity.
		}
		else if ($this->_curTag == ($this->adCriteria_Key . "^DISAPPROVED")) {
			$this->currentAdObj->disapproved = $data;	
		}
		else if ($this->_curTag == ($this->adCriteria_Key . "^DISPLAYURL")) {
			$this->currentAdObj->displayUrl .= $data;	// Use ".=" in case of an &amp; entity.
		}
		else if ($this->_curTag == ($this->adCriteria_Key . "^EXEMPTIONREQUEST")) {
			$this->currentAdObj->exemptionRequest = $data;	
		}
		else if ($this->_curTag == ($this->adCriteria_Key . "^HEADLINE")) {
			$this->currentAdObj->headline .= $data;	// Use ".=" in case of an &amp; entity.
		}
		else if ($this->_curTag == ($this->adCriteria_Key . "^ID")) {
			$this->currentAdObj->id = $data;	
		}
		else if ($this->_curTag == ($this->adCriteria_Key . "^STATUS")) {
			$this->currentAdObj->status = $data;	
		}

		// ------  Stats on the Current Ad ----
		else if ($this->_curTag == ($this->adStats_Key . "^CLICKS")) {
			$this->currentAdStatsObj->clicks = $data;	
		}
		else if ($this->_curTag == ($this->adStats_Key . "^CONVERSIONRATE")) {
			$this->currentAdStatsObj->conversionRate = $data;	
		}
		else if ($this->_curTag == ($this->adStats_Key . "^CONVERSIONS")) {
			$this->currentAdStatsObj->conversions = $data;	
		}
		else if ($this->_curTag == ($this->adStats_Key . "^COST")) {
			$this->currentAdStatsObj->cost = AdWordsBase::convertGooglePriceToLocal($data);	
		}
		else if ($this->_curTag == ($this->adStats_Key . "^ID")) {
			$this->currentAdStatsObj->id = $data;	
		}
		else if ($this->_curTag == ($this->adStats_Key . "^IMPRESSIONS")) {
			$this->currentAdStatsObj->impressions = $data;	
		}
		else if ($this->_curTag == ($this->adStats_Key . "^AVERAGEPOSITION")) {
			$this->currentAdStatsObj->averagePosition = $data;	
		}
		
	}
	
  	function eventStartXmlElement(){

  		// Whenever we come across an opening tag for the GETADGROUPSTATSRETURN we know that we have to create a new container to hold our statistics for the AdGroup that we are looping over.
  		if ($this->_curTag == $this->adCriteria_Key) {
  			$this->currentAdObj = new AdWordsTextAd();	
  		}
  		else if ($this->_curTag == $this->adStats_Key) {
  			$this->currentAdStatsObj = new AdWordsStatsRecord();	
  		}

		
	}
	function eventEndXmlElement(){

  		// Whenever we come across the closing tag for the GETADGROUPSTATSRETURN we know that we have to add our "Current" Status Object to our array of AdGroup Status Objects..
  		// We may have called many AdGroup ID's within an "array request" from the API.
		if ($this->_curTag == $this->adCriteria_Key) {
			$this->adObjArr[] = $this->currentAdObj;	
		}
		else if ($this->_curTag == $this->adStats_Key) {
  			$this->adStatsObjArr[] = $this->currentAdStatsObj;
		}

	}
	
	
	
 } 




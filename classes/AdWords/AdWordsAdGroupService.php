<?php

// http://code.google.com/intl/de-CH/apis/adwords/docs/developer/AdService.html

class AdWordsAdGroupService extends AdWordsBase {

  	
  	private $adGroupsStatsObjArr = array();
  	private $adGroupsArr = array();
  	
  	private $currentAdGroupStatsRecordObj;
  	private $currentAdGroupObj;
  	
  	private $adGroupStats_Key;
  	private $adGroupList_Key;

	function __construct() {
		
		$this->webServiceName = "AdGroupService";
		parent::__construct();	
		
		$this->adGroupStats_Key = "^SOAPENV:ENVELOPE^SOAPENV:BODY^GETADGROUPSTATSRESPONSE^GETADGROUPSTATSRETURN";
		$this->adGroupList_Key = "^SOAPENV:ENVELOPE^SOAPENV:BODY^GETADGROUPLISTRESPONSE^GETADGROUPLISTRETURN";
	}
	

	
	// Returns an array of AdWordsStatsRecord Objects
	function getAdGroupStats($campainId, array $adGroupIdsArr, $startDayTimeStamp, $endDayTimeStamp) {
 
		$this->adGroupsStatsObjArr = array();
		
		$request_xml = "<getAdGroupStats>
							<campaignID>" . $campainId . "</campaignID>";
		
		foreach($adGroupIdsArr as $thisAdGroupID)
			$request_xml .= "<adGroupIds>" . $thisAdGroupID . "</adGroupIds>";
			
		$request_xml .= "<startDay>" . $this->convertUnixTimeStampToGoogleTimeStamp($startDayTimeStamp) . "</startDay>
							<endDay>" . $this->convertUnixTimeStampToGoogleTimeStamp($endDayTimeStamp) . "</endDay>
						</getAdGroupStats>"; 
		
		$this->soapRequest($request_xml);	
		
		return $this->adGroupsStatsObjArr;
	}
	
	function getAdGroupList(array $adGroupIdsArr) {
 
		$this->adGroupList_Key = "^SOAPENV:ENVELOPE^SOAPENV:BODY^GETADGROUPLISTRESPONSE^GETADGROUPLISTRETURN";
		
		$this->adGroupsArr = array();
		
		$request_xml = "<getAdGroupList>";
		
		foreach($adGroupIdsArr as $thisAdGroupID)
			$request_xml .= "<adgroupIDs>" . $thisAdGroupID . "</adgroupIDs>";
			
		$request_xml .= "</getAdGroupList>"; 
		
		$this->soapRequest($request_xml);	
		
		return $this->adGroupsArr;
	}
	
	function getActiveAdGroups($campainId) {
 
		$this->adGroupList_Key = "^SOAPENV:ENVELOPE^SOAPENV:BODY^GETACTIVEADGROUPSRESPONSE^GETACTIVEADGROUPSRETURN";
		
		$this->adGroupsArr = array();
		
		$request_xml = "<getActiveAdGroups><campaignID>$campainId</campaignID></getActiveAdGroups>";
		
		$this->soapRequest($request_xml);	
		
		return $this->adGroupsArr;
	}
	
  
	function parseXMLStructure($_parser, $data) {

		// Define all possible tag structures with a carrot separating each tag name.
		
		if ($this->_curTag == ($this->adGroupStats_Key . "^CLICKS")) {
			$this->currentAdGroupStatsRecordObj->clicks = $data;	
		}
		else if ($this->_curTag == ($this->adGroupStats_Key . "^CONVERSIONRATE")) {
			$this->currentAdGroupStatsRecordObj->conversionRate = $data;	
		}
		else if ($this->_curTag == ($this->adGroupStats_Key . "^CONVERSIONS")) {
			$this->currentAdGroupStatsRecordObj->conversions = $data;	
		}
		else if ($this->_curTag == ($this->adGroupStats_Key . "^COST")) {
			$this->currentAdGroupStatsRecordObj->cost = AdWordsBase::convertGooglePriceToLocal($data);	
		}
		else if ($this->_curTag == ($this->adGroupStats_Key . "^ID")) {
			$this->currentAdGroupStatsRecordObj->id = $data;	
		}
		else if ($this->_curTag == ($this->adGroupStats_Key . "^IMPRESSIONS")) {
			$this->currentAdGroupStatsRecordObj->impressions = $data;	
		}
		else if ($this->_curTag == ($this->adGroupStats_Key . "^AVERAGEPOSITION")) {
			$this->currentAdGroupStatsRecordObj->averagePosition = $data;	
		}
		
		
		else if ($this->_curTag == ($this->adGroupList_Key . "^CAMPAIGNID")) {
			$this->currentAdGroupObj->campaignId = $data;	
		}
		else if ($this->_curTag == ($this->adGroupList_Key . "^ID")) {
			$this->currentAdGroupObj->id = $data;	
		}
		else if ($this->_curTag == ($this->adGroupList_Key . "^KEYWORDCONTENTMAXCPC")) {
			$this->currentAdGroupObj->keywordContentMaxCpc = AdWordsBase::convertGooglePriceToLocal($data);	
		}
		else if ($this->_curTag == ($this->adGroupList_Key . "^KEYWORDMAXCPC")) {
			$this->currentAdGroupObj->keywordMaxCpc = AdWordsBase::convertGooglePriceToLocal($data);	
		}
		else if ($this->_curTag == ($this->adGroupList_Key . "^MAXCPA")) {
			$this->currentAdGroupObj->maxCpa = AdWordsBase::convertGooglePriceToLocal($data);	
		}
		else if ($this->_curTag == ($this->adGroupList_Key . "^NAME")) {
			$this->currentAdGroupObj->name .= $data;	// Use ".=" in case of an &amp; entity.
		}
		else if ($this->_curTag == ($this->adGroupList_Key . "^PROXYKEYWORDMAXCPC")) {
			$this->currentAdGroupObj->proxyKeywordMaxCpc = AdWordsBase::convertGooglePriceToLocal($data);	
		}
		else if ($this->_curTag == ($this->adGroupList_Key . "^SITEMAXCPC")) {
			$this->currentAdGroupObj->siteMaxCpc = AdWordsBase::convertGooglePriceToLocal($data);	
		}
		else if ($this->_curTag == ($this->adGroupList_Key . "^SITEMAXCPM")) {
			$this->currentAdGroupObj->siteMaxCpm = AdWordsBase::convertGooglePriceToLocal($data);	
		}
		else if ($this->_curTag == ($this->adGroupList_Key . "^STATUS")) {
			$this->currentAdGroupObj->status = $data;	
		}
		
		
	}
	
	
	function updateAdGroupList(array $adGroupObjArr){
		
		$request_xml = "<updateAdGroupList>";
		
		foreach($adGroupObjArr as $thisAdGroupObj){
			
			if(get_class($thisAdGroupObj) != "AdWordsAdGroup")
				throw new Exception("updateCampaignList can only take an array of campaign objects.");
				
			$request_xml .= "<changedData>";
			$request_xml .= $thisAdGroupObj->getAdGroupXml();
			$request_xml .= "</changedData>";
		}
		$request_xml .= "</updateAdGroupList>";

		$this->soapRequest($request_xml);	
	}
	
	
	// Returns an Array of AdGroups that have been added.
	function addAdGroupList($campaignId, array $adGroupObjArr){
		
		$this->adGroupsArr = array();
		
		$this->adGroupList_Key = "^SOAPENV:ENVELOPE^SOAPENV:BODY^ADDADGROUPLISTRESPONSE^ADDADGROUPLISTRETURN";
		
		$request_xml = "<addAdGroupList>";
		$request_xml .= "<campaignID>$campaignId</campaignID>";
		
		foreach($adGroupObjArr as $thisAdGroupObj){
			
			if(get_class($thisAdGroupObj) != "AdWordsAdGroup")
				throw new Exception("addAdGroupList can only take an array of campaign objects.");
				
			$request_xml .= "<newData>";
			$request_xml .= $thisAdGroupObj->getAdGroupXml();
			$request_xml .= "</newData>";
		}
		$request_xml .= "</addAdGroupList>";

		$this->soapRequest($request_xml);
		
		return $this->adGroupsArr;
	}
	
  	function eventStartXmlElement(){

  		// Whenever we come across an opening tag for the GETADGROUPSTATSRETURN we know that we have to create a new container to hold our statistics for the AdGroup that we are looping over.
  		if ($this->_curTag == $this->adGroupStats_Key) {
  			$this->currentAdGroupStatsRecordObj = new AdWordsStatsRecord();	
  		}
  		else if ($this->_curTag == $this->adGroupList_Key) {
  			$this->currentAdGroupObj = new AdWordsAdGroup();	
  		}
		
	}
	function eventEndXmlElement(){

  		// Whenever we come across the closing tag for the GETADGROUPSTATSRETURN we know that we have to add our "Current" Status Object to our array of AdGroup Status Objects..
  		// We may have called many AdGroup ID's within an "array request" from the API.
  		if ($this->_curTag == $this->adGroupStats_Key) {
  			$this->adGroupsStatsObjArr[] = $this->currentAdGroupStatsRecordObj;	
  		}
  		else if ($this->_curTag == $this->adGroupList_Key) {
  			$this->adGroupsArr[] = $this->currentAdGroupObj;	
  		}
	}
	
	
	
 } 




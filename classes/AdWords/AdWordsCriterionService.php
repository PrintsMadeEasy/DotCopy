<?php

// http://code.google.com/intl/de-CH/apis/adwords/docs/developer/CriterionService.html

class AdWordsCriterionService extends AdWordsBase {
  	
  	private $keywordCriteriaObjArr = array();
  	private $criterionStatsObjArr = array();
  	
  	private $currentStatsObj;
  	private $currentKeywordObj;
  	private $keywordCriteria_Key;
  	private $criterionStats_Key;

	function __construct() {
		
		$this->webServiceName = "CriterionService";
		parent::__construct();	

		$this->criterionStats_Key = "^SOAPENV:ENVELOPE^SOAPENV:BODY^GETCRITERIONSTATSRESPONSE^GETCRITERIONSTATSRETURN";
	}
	
	
	// Returns an array of AdWordsStatsRecord Objects
	function getAllCriteria($adGroupId) {
		
		$this->keywordCriteria_Key = "^SOAPENV:ENVELOPE^SOAPENV:BODY^GETALLCRITERIARESPONSE^GETALLCRITERIARETURN";
 
		$this->keywordCriteriaObjArr = array();
		
		$request_xml = "<getAllCriteria><adGroupId>" . $adGroupId . "</adGroupId></getAllCriteria>";
		
		$this->soapRequest($request_xml);	
		
		return $this->keywordCriteriaObjArr;
	}
	
	// Returns an array of AdWordsCriteria Objects
	function getCriteria($adGroupId, array $criterionIds) {
		
		$this->keywordCriteria_Key = "^SOAPENV:ENVELOPE^SOAPENV:BODY^GETCRITERIARESPONSE^GETCRITERIARETURN";
 
		$this->keywordCriteriaObjArr = array();
		
		$request_xml = "<getCriteria>\n";
		
		$request_xml .= "<adGroupId>$adGroupId</adGroupId>\n";
		
		foreach($criterionIds as $thisCriteriaID)
			$request_xml .= "<criterionIds>$thisCriteriaID</criterionIds>\n";
		
		$request_xml .= "</getCriteria>\n";
		
		$this->soapRequest($request_xml);
		
		return $this->keywordCriteriaObjArr;
	}
	
	
	// Returns an array of AdWords Stats Objects.
	function getCriterionStats($adGroupId, array $criterionIds, $startDayTimeStamp, $endDayTimeStamp) {
		
		if(empty($criterionIds))
			return array();
		
		$this->criterionStatsObjArr = array();

		$request_xml = "<getCriterionStats>
							<adGroupId>" . $adGroupId . "</adGroupId>";
		
		foreach($criterionIds as $thisCriteriaID)
			$request_xml .= "<criterionIds>" . $thisCriteriaID . "</criterionIds>";
			
		$request_xml .= "<startDay>" . $this->convertUnixTimeStampToGoogleTimeStamp($startDayTimeStamp) . "</startDay>
							<endDay>" . $this->convertUnixTimeStampToGoogleTimeStamp($endDayTimeStamp) . "</endDay>
						</getCriterionStats>"; 

		$this->soapRequest($request_xml);	
		
		return $this->criterionStatsObjArr;
	}
	
	function updateCriteria($criteriaObjArr){

		$request_xml = "<updateCriteria>\n";

		foreach($criteriaObjArr as $criteriaObj){
			
			if(get_class($criteriaObj) != "AdWordsCriteriaKeyword")
				throw new Exception("updateCriteria must take an aray of AdWordsCriteriaKeyword objects.");
			
			$request_xml .= "<criteria>\n";
			$request_xml .= $criteriaObj->getKeywordCriteriaXml();
			$request_xml .= "</criteria>\n";
		}
		
		//$this->showResponseText = true;
		
		$request_xml .= "</updateCriteria>\n";
		
		$this->soapRequest($request_xml);	
	}
	
	
	function removeCriteria($adGroupId, array $criterionIds){

		$request_xml = "<removeCriteria>\n";
		
		$request_xml .= "<adGroupId>$adGroupId</adGroupId>\n";
		
		foreach($criterionIds as $thisCriteriaID)
			$request_xml .= "<criterionIds>$thisCriteriaID</criterionIds>\n";
		
		$request_xml .= "</removeCriteria>\n";
		
		$this->soapRequest($request_xml);	
	}
	
	
	function addCriteria($criteriaObjArr){

		$this->keywordCriteria_Key = "^SOAPENV:ENVELOPE^SOAPENV:BODY^ADDCRITERIARESPONSE^ADDCRITERIARETURN";
		
		$this->keywordCriteriaObjArr = array();
		
		$request_xml = "<addCriteria>\n";

		foreach($criteriaObjArr as $criteriaObj){
			
			if(get_class($criteriaObj) != "AdWordsCriteriaKeyword")
				throw new Exception("addCriteria must take an aray of AdWordsCriteriaKeyword objects.");
			
			$request_xml .= "<criteria>\n";
			$request_xml .= $criteriaObj->getKeywordCriteriaXml();
			$request_xml .= "</criteria>\n";
		}
		
		$request_xml .= "</addCriteria>\n";
		
		//$this->showResponseText = true;
	
		$this->soapRequest($request_xml);	
		
		return $this->keywordCriteriaObjArr;
	}
	

  
	function parseXMLStructure($_parser, $data) {

		// Define all possible tag structures with a carrot separating each tag name.
		
		if ($this->_curTag == ($this->keywordCriteria_Key . "^ADGROUPID")) {
			$this->currentKeywordObj->adGroupId = $data;	
		}
		else if ($this->_curTag == ($this->keywordCriteria_Key . "^CRITERIONTYPE")) {
			$this->currentKeywordObj->criterionType = $data;	
		}
		else if ($this->_curTag == ($this->keywordCriteria_Key . "^DESTINATIONURL")) {
			$this->currentKeywordObj->destinationUrl .= $data;	
		}
		else if ($this->_curTag == ($this->keywordCriteria_Key . "^EXEMPTIONREQUEST")) {
			$this->currentKeywordObj->exemptionRequest = $data;	
		}
		else if ($this->_curTag == ($this->keywordCriteria_Key . "^FIRSTPAGECPC")) {
			$this->currentKeywordObj->firstPageCpc = AdWordsBase::convertGooglePriceToLocal($data);	
		}
		else if ($this->_curTag == ($this->keywordCriteria_Key . "^ID")) {
			$this->currentKeywordObj->id = $data;	
		}
		else if ($this->_curTag == ($this->keywordCriteria_Key . "^LANGUAGE")) {
			$this->currentKeywordObj->language = $data;	
		}
		else if ($this->_curTag == ($this->keywordCriteria_Key . "^MAXCPC")) {
			$this->currentKeywordObj->maxCpc = AdWordsBase::convertGooglePriceToLocal($data);	
		}
		else if ($this->_curTag == ($this->keywordCriteria_Key . "^NEGATIVE")) {
			$this->currentKeywordObj->negative = $data;	
		}
		else if ($this->_curTag == ($this->keywordCriteria_Key . "^PAUSED")) {
			$this->currentKeywordObj->paused = $data;	
		}
		else if ($this->_curTag == ($this->keywordCriteria_Key . "^PROXYMAXCPC")) {
			$this->currentKeywordObj->proxyMaxCpc = AdWordsBase::convertGooglePriceToLocal($data);	
		}
		else if ($this->_curTag == ($this->keywordCriteria_Key . "^QUALITYSCORE")) {
			$this->currentKeywordObj->qualityScore = $data;	
		}
		else if ($this->_curTag == ($this->keywordCriteria_Key . "^STATUS")) {
			$this->currentKeywordObj->status = $data;	
		}
		else if ($this->_curTag == ($this->keywordCriteria_Key . "^TEXT")) {
			$this->currentKeywordObj->text .= $data;	
		}
		else if ($this->_curTag == ($this->keywordCriteria_Key . "^TYPE")) {
			$this->currentKeywordObj->type = $data;	
		}

		// Get the statistics for the Criteria
		else if ($this->_curTag == ($this->criterionStats_Key . "^CLICKS")) {
			$this->currentStatsObj->clicks = $data;	
		}
		else if ($this->_curTag == ($this->criterionStats_Key . "^CONVERSIONRATE")) {
			$this->currentStatsObj->conversionRate = $data;	
		}
		else if ($this->_curTag == ($this->criterionStats_Key . "^CONVERSIONS")) {
			$this->currentStatsObj->conversions = $data;	
		}
		else if ($this->_curTag == ($this->criterionStats_Key . "^COST")) {
			$this->currentStatsObj->cost = AdWordsBase::convertGooglePriceToLocal($data);	
		}
		else if ($this->_curTag == ($this->criterionStats_Key . "^ID")) {
			$this->currentStatsObj->id = $data;	
		}
		else if ($this->_curTag == ($this->criterionStats_Key . "^IMPRESSIONS")) {
			$this->currentStatsObj->impressions = $data;	
		}
		else if ($this->_curTag == ($this->criterionStats_Key . "^AVERAGEPOSITION")) {
			$this->currentStatsObj->averagePosition = $data;
		}
		
	}
	
  	function eventStartXmlElement(){

  		// Whenever we come across an opening tag for the GETADGROUPSTATSRETURN we know that we have to create a new container to hold our statistics for the AdGroup that we are looping over.
  		if ($this->_curTag == $this->keywordCriteria_Key) {
  			$this->currentKeywordObj = new AdWordsCriteriaKeyword();	
  		}
  		else if ($this->_curTag == $this->criterionStats_Key) {
  			$this->currentStatsObj = new AdWordsStatsRecord();	
  		}

		
	}
	function eventEndXmlElement(){

  		// Whenever we come across the closing tag for the GETADGROUPSTATSRETURN we know that we have to add our "Current" Status Object to our array of AdGroup Status Objects..
  		// We may have called many AdGroup ID's within an "array request" from the API.
  		if ($this->_curTag == $this->keywordCriteria_Key) {
  			$this->keywordCriteriaObjArr[] = $this->currentKeywordObj;	
  		}
   		else if ($this->_curTag == $this->criterionStats_Key) {
  			$this->criterionStatsObjArr[] =  $this->currentStatsObj;	
  		}
  		

	}
	
	
	
 } 




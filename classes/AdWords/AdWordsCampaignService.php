<?php

// http://code.google.com/intl/de-CH/apis/adwords/docs/developer/CampaignService.html

 class AdWordsCampaignService extends AdWordsBase {

 	// If we have multiple campaigns returned in a call this will contain an array of campaign objects.
  	private $campaignObjArr = array();
  	private $campaignStatsObjArr = array();
  	
  	private $campaignTag;
  	private $campaignStatsTag;
  	private $budgetOptimizerTag;
  	private $conversionOptimizerTag;
  	
  	
  	private $languageTargetingTag;
  	private $networkTargetingTag;
  	private $scheduleTag;
  	private $scheduleIntervalsTag;
  	private $geoTargetingTag;

  	
  	// While we are going through a particular tag (and its subnodes)... we have to keep track of the SubNode we are currently working on with "current objects"
  	private $campaignObj;
  	private $current_ScheduleIntervalObj;
  	private $current_GeoCirclesObj;
  	private $current_CampaignStatsObj;



	function __construct() {
		
		$this->webServiceName = "CampaignService";
		parent::__construct();	
		
	}

	
	private function setSecondLevelTags(){

		$this->budgetOptimizerTag = $this->campaignTag . "^BUDGETOPTIMIZERSETTINGS";
		$this->conversionOptimizerTag = $this->campaignTag . "^CONVERSIONOPTIMIZERSETTINGS";
		$this->geoTargetingTag = $this->campaignTag . "^GEOTARGETING";
		$this->languageTargetingTag = $this->campaignTag . "^LANGUAGETARGETING";
		$this->networkTargetingTag = $this->campaignTag . "^NETWORKTARGETING";
		$this->scheduleTag = $this->campaignTag . "^SCHEDULE";
		$this->scheduleIntervalsTag = $this->campaignTag . "^SCHEDULE^INTERVALS";
		
	}
	 
	
	// Returns an array of Campaign Objects
	function getAllAdWordsCampaigns () {
 
		$this->campaignTag = "^SOAPENV:ENVELOPE^SOAPENV:BODY^GETALLADWORDSCAMPAIGNSRESPONSE^GETALLADWORDSCAMPAIGNSRETURN";
		$this->setSecondLevelTags();
		
		$this->campaignObjArr = array();
		
		$request_xml = "<getAllAdWordsCampaigns><dummy>0</dummy></getAllAdWordsCampaigns>"; 
		
		$this->soapRequest($request_xml);	
		
		return $this->campaignObjArr;
	}
	
	 // Returns an array of Campaign Objects
	function getActiveAdWordsCampaigns () {
 
		$this->campaignTag = "^SOAPENV:ENVELOPE^SOAPENV:BODY^GETACTIVEADWORDSCAMPAIGNSRESPONSE^GETACTIVEADWORDSCAMPAIGNSRETURN";
		$this->setSecondLevelTags();
		
		$this->campaignObjArr = array();
		
		$request_xml = "<getActiveAdWordsCampaigns></getActiveAdWordsCampaigns>"; 
		
		$this->soapRequest($request_xml);	
		
		return $this->campaignObjArr;
	}
	
	 // Returns an array of Campaign Objects
	function getCampaignList(array $campaignIDs) {
 
		$this->campaignTag = "^SOAPENV:ENVELOPE^SOAPENV:BODY^GETCAMPAIGNLISTRESPONSE^GETCAMPAIGNLISTRETURN";
		$this->setSecondLevelTags();
		
		$this->campaignObjArr = array();
		
		$request_xml = "<getCampaignList>";
		
		foreach($campaignIDs as $thisCampaignID)
			$request_xml .= "<ids>$thisCampaignID</ids>";
	  
		$request_xml .= "</getCampaignList>"; 
		
		$this->soapRequest($request_xml);	
		
		return $this->campaignObjArr;
	}
	

	function getCampaignStats(array $campaignIDs, $startDayTimeStamp, $endDayTimeStamp) {
 
		$this->campaignStatsTag = "^SOAPENV:ENVELOPE^SOAPENV:BODY^GETCAMPAIGNSTATSRESPONSE^GETCAMPAIGNSTATSRETURN";
		$this->setSecondLevelTags();
		
		$this->campaignStatsObjArr = array();
		
		$request_xml = "<getCampaignStats>";
		foreach($campaignIDs as $thisCampaignID)
			$request_xml .= "<ids>$thisCampaignID</ids>";
		
		$request_xml .= "<startDay>" . $this->convertUnixTimeStampToGoogleTimeStamp($startDayTimeStamp) . "</startDay>
							<endDay>" . $this->convertUnixTimeStampToGoogleTimeStamp($endDayTimeStamp) . "</endDay>
		</getCampaignStats>"; 
		
		$this->soapRequest($request_xml);	
		
		return $this->campaignStatsObjArr;
	}
  
	function parseXMLStructure($_parser, $data) {

		// Define all possible tag structures with a carrot separating each tag name.
		
		if ($this->_curTag == ($this->campaignTag . "^BUDGETAMOUNT")) {
			$this->campaignObj->budgetAmount = AdWordsBase::convertGooglePriceToLocal($data);	
		}
		else if ($this->_curTag == ($this->campaignTag . "^BUDGETPERIOD")) {
			$this->campaignObj->budgetPeriod = $data;	
		}
		else if ($this->_curTag == ($this->campaignTag . "^CONTENTTARGETING")) {
			$this->campaignObj->contentTargeting = $data;	
		}
		else if ($this->_curTag == ($this->campaignTag . "^ENDDAY")) {
			$this->campaignObj->endDay = $data;	
		}
		else if ($this->_curTag == ($this->campaignTag . "^STARTDAY")) {
			$this->campaignObj->startDay = $data;	
		}
		else if ($this->_curTag == ($this->campaignTag . "^ID")) {
			$this->campaignObj->id = $data;	
		}
		else if ($this->_curTag == ($this->campaignTag . "^NAME")) {
			$this->campaignObj->name .= $data;	// Use ".=" in case of an &amp; entity.
		}
		else if ($this->_curTag == ($this->campaignTag . "^STATUS")) {
			$this->campaignObj->status = $data;	
		}
		
		// Budget Optimizer
		else if ($this->_curTag == ($this->budgetOptimizerTag . "^ENABLED")) {
			$this->campaignObj->budgetOptimizerSettingsObj->enabled = $data;	
		}	
		else if ($this->_curTag == ($this->budgetOptimizerTag . "^BIDCEILING")) {
			$this->campaignObj->budgetOptimizerSettingsObj->bidCeiling = AdWordsBase::convertGooglePriceToLocal($data);	
		}	
		else if ($this->_curTag == ($this->budgetOptimizerTag . "^TAKEONOPTIMIZEDBIDS")) {
			$this->campaignObj->budgetOptimizerSettingsObj->takeOnOptimizedBids = $data;	
		}	
		
		// Languages
		else if ($this->_curTag == ($this->languageTargetingTag . "^LANGUAGES")) {
			$this->campaignObj->languageTargetingObj->languagesArr[] = $data;	
		}	
		
		// Network Targeting
		else if ($this->_curTag == ($this->networkTargetingTag . "^NETWORKTYPES")) {
			$this->campaignObj->networkTargetingObj->networkTypesArr[] = $data;	
		}	
		
		// Conversion Optimizer
		else if ($this->_curTag == ($this->conversionOptimizerTag . "^ENABLED")) {
			$this->campaignObj->conversionOptimizerSettingsObj->enabled = $data;	
		}	
		else if ($this->_curTag == ($this->conversionOptimizerTag . "^MAXCPABIDFORALLADGROUPS")) {
			$this->campaignObj->conversionOptimizerSettingsObj->maxCpaBidForAllAdGroups = AdWordsBase::convertGooglePriceToLocal($data);	
		}	
		
		
		// Campaign Statistics
		else if ($this->_curTag == ($this->campaignStatsTag . "^CLICKS")) {		
			$this->current_CampaignStatsObj->clicks = $data;
		}
		else if ($this->_curTag == ($this->campaignStatsTag . "^CONVERSIONRATE")) {
			$this->current_CampaignStatsObj->conversionRate = $data;	
		}
		else if ($this->_curTag == ($this->campaignStatsTag . "^CONVERSIONS")) {
			$this->current_CampaignStatsObj->conversions = $data;	
		}
		else if ($this->_curTag == ($this->campaignStatsTag . "^COST")) {
			$this->current_CampaignStatsObj->cost = AdWordsBase::convertGooglePriceToLocal($data);	
		}
		else if ($this->_curTag == ($this->campaignStatsTag . "^ID")) {
			$this->current_CampaignStatsObj->id = $data;	
		}
		else if ($this->_curTag == ($this->campaignStatsTag . "^IMPRESSIONS")) {
			$this->current_CampaignStatsObj->impressions = $data;	
		}
		else if ($this->_curTag == ($this->campaignStatsTag . "^AVERAGEPOSITION")) {
			$this->current_CampaignStatsObj->averagePosition = $data;	
		}
		
		
		// Scheduling
		else if ($this->_curTag == ($this->scheduleTag . "^STATUS")) {
			$this->campaignObj->scheduleObj->status = $data;	
		}	
		else if ($this->_curTag == ($this->scheduleTag . "^INTERVALS^DAY")) {
			$this->current_ScheduleIntervalObj->day = $data;	
		}	
		else if ($this->_curTag == ($this->scheduleTag . "^INTERVALS^ENDHOUR")) {
			$this->current_ScheduleIntervalObj->endHour = $data;	
		}	
		else if ($this->_curTag == ($this->scheduleTag . "^INTERVALS^ENDMINUTE")) {
			$this->current_ScheduleIntervalObj->endMinute = $data;	
		}	
		else if ($this->_curTag == ($this->scheduleTag . "^INTERVALS^MULTIPLIER")) {
			$this->current_ScheduleIntervalObj->multiplier = $data;	
		}	
		else if ($this->_curTag == ($this->scheduleTag . "^INTERVALS^STARTHOUR")) {
			$this->current_ScheduleIntervalObj->startHour = $data;	
		}	
		else if ($this->_curTag == ($this->scheduleTag . "^INTERVALS^STARTMINUTE")) {
			$this->current_ScheduleIntervalObj->startMinute = $data;	
		}	
		
		
		// Geo Targeting
		else if ($this->_curTag == ($this->geoTargetingTag . "^TARGETALL")) {
			$this->campaignObj->geoTargetingObj->targetAll = $data;	
		}
		
		else if ($this->_curTag == ($this->geoTargetingTag . "^CITYTARGETS^CITIES")) {
			$this->campaignObj->geoTargetingObj->cityTargetsObj->citiesArr[] = $data;	
		}
		else if ($this->_curTag == ($this->geoTargetingTag . "^CITYTARGETS^EXCLUDEDCITIES")) {
			$this->campaignObj->geoTargetingObj->cityTargetsObj->excludedCities[] = $data;	
		}
		
		
		else if ($this->_curTag == ($this->geoTargetingTag . "^COUNTRYTARGETS^COUNTRIES")) {
			$this->campaignObj->geoTargetingObj->countryTargetsObj->countriesArr[] = $data;	
		}
		else if ($this->_curTag == ($this->geoTargetingTag . "^COUNTRYTARGETS^EXCLUDEDCOUNTRIES")) {
			$this->campaignObj->geoTargetingObj->countryTargetsObj->excludedCountriesArr[] = $data;	
		}
		
		
		else if ($this->_curTag == ($this->geoTargetingTag . "^METROTARGETS^METROS")) {
			$this->campaignObj->geoTargetingObj->metroTargetsObj->metrosArr[] = $data;	
		}
		else if ($this->_curTag == ($this->geoTargetingTag . "^METROTARGETS^EXCLUDEDMETROS")) {
			$this->campaignObj->geoTargetingObj->metroTargetsObj->excludedMetrosArr[] = $data;	
		}
		
		else if ($this->_curTag == ($this->geoTargetingTag . "^REGIONTARGETS^REGIONS")) {
			$this->campaignObj->geoTargetingObj->regionTargetsObj->regionsArr[] = $data;	
		}
		else if ($this->_curTag == ($this->geoTargetingTag . "^REGIONTARGETS^EXCLUDEDREGIONS")) {
			$this->campaignObj->geoTargetingObj->regionTargetsObj->excludedRegionsArr[] = $data;	
		}
		
		else if ($this->_curTag == ($this->geoTargetingTag . "^PROXIMITYTARGETS^LATITUDEMICRODEGREES")) {
			$this->current_GeoCirclesObj->latitudeMicroDegrees = $data;	
		}
		else if ($this->_curTag == ($this->geoTargetingTag . "^PROXIMITYTARGETS^LONGITUDEMICRODEGREES")) {
			$this->current_GeoCirclesObj->longitudeMicroDegrees = $data;	
		}
		else if ($this->_curTag == ($this->geoTargetingTag . "^PROXIMITYTARGETS^RADIUSMETERS")) {
			$this->current_GeoCirclesObj->radiusMeters = $data;	
		}


	}
	
  	function eventStartXmlElement(){

  		// Whenever we come across an opening tag for a Sub Object... we know that we have to create a new container.
  		// This container object in the class has to temporarily hold details for the object that we are looping over until the closing tag can add it to the array.
  		if ($this->_curTag == $this->campaignTag) {
  			$this->campaignObj = new AdWordsCampaign();	
  		}
  	  	else if ($this->_curTag == $this->campaignStatsTag) {
  			$this->current_CampaignStatsObj = new AdWordsStatsRecord();	
  		}
  		else if ($this->_curTag == $this->budgetOptimizerTag) {
  			$this->campaignObj->budgetOptimizerSettingsObj = new AdWordsCampaignBudgetOptimizerSettings();	
  		}
  		else if ($this->_curTag == $this->conversionOptimizerTag) {
  			$this->campaignObj->conversionOptimizerSettingsObj = new AdWordsCampaignConversionOptimizerSettings();	
  		}
  		else if ($this->_curTag == $this->geoTargetingTag) {
  			$this->campaignObj->geoTargetingObj = new AdWordsGeoTarget();
  		}
  		else if ($this->_curTag == $this->languageTargetingTag) {
  			$this->campaignObj->languageTargetingObj = new AdWordsCampaignLanguageTargeting();	
  		}
  		else if ($this->_curTag == $this->networkTargetingTag) {
  			$this->campaignObj->networkTargetingObj = new AdWordsCampaignNetworkTargeting();	
  		}
  		else if ($this->_curTag == $this->scheduleTag) {
  			$this->campaignObj->scheduleObj = new AdWordsCampaignSchedule();	
  		}
  	  	else if ($this->_curTag == $this->geoTargetingTag) {
  			$this->campaignObj->geoTargetingObj = new AdWordsGeoTarget();	
  		}
  	  	else if ($this->_curTag == ($this->geoTargetingTag . "^CITYTARGETS")) {
  			$this->campaignObj->geoTargetingObj->cityTargetsObj = new AdWordsGeoTargetCities();	
  		}
  		else if ($this->_curTag == ($this->geoTargetingTag . "^PROXIMITYTARGETS")) {
  			$this->campaignObj->geoTargetingObj->proximityTargetsObj= new AdWordsGeoTargetProximities();	
  		}
  		else if ($this->_curTag == ($this->geoTargetingTag . "^REGIONTARGETS")) {
  			$this->campaignObj->geoTargetingObj->regionTargetsObj = new AdWordsGeoTargetRegions();	
  		}
  		else if ($this->_curTag == ($this->geoTargetingTag . "^METROTARGETS")) {
  			$this->campaignObj->geoTargetingObj->metroTargetsObj = new AdWordsGeoTargetMetros();	
  		}
  		else if ($this->_curTag == ($this->geoTargetingTag . "^COUNTRYTARGETS")) {
  			$this->campaignObj->geoTargetingObj->countryTargetsObj = new AdWordsGeoTargetCountries();	
  		}
  		
  		// Because there will be child nodes, hold the object in a temp variable while we parse... then add to the array on closing tags.
  		else if ($this->_curTag == $this->scheduleIntervalsTag) {
  			$this->current_ScheduleIntervalObj = new AdWordsSchedulingInterval();	
  		}
  	  	else if ($this->_curTag == ($this->geoTargetingTag . "^PROXIMITYTARGETS^CIRCLE")) {
  			$this->current_GeoCirclesObj = new AdWordsCircles();	
  		}

	}
	
	
	function updateCampaignList(array $campaingObjArr){
		
		$request_xml = "<updateCampaignList>";
		
		foreach($campaingObjArr as $thisCampainObj){
			
			if(get_class($thisCampainObj) != "AdWordsCampaign")
				throw new Exception("updateCampaignList can only take an array of campaign objects.");
				
			$request_xml .= "<campaigns>";
			$request_xml .= $thisCampainObj->getCampaignXml();
			$request_xml .= "</campaigns>";
		}
		$request_xml .= "</updateCampaignList>";
		
		$this->soapRequest($request_xml);	
	}
	
	
 	function addCampaignList(array $campaingObjArr){
		
		$request_xml = "<addCampaignList>";
		
		foreach($campaingObjArr as $thisCampainObj){
			
			if(get_class($thisCampainObj) != "AdWordsCampaign")
				throw new Exception("addCampaignList can only take an array of campaign objects.");
				
			$request_xml .= "<campaigns>";
			$request_xml .= $thisCampainObj->getCampaignXml();
			$request_xml .= "</campaigns>";
		}
		$request_xml .= "</addCampaignList>";
		
		$this->soapRequest($request_xml);	
	}
	
	function eventEndXmlElement(){

  		// Whenever we come across the closing tag for the Sub Object, we know that we have to add our "Current" Object to our array of Sub Objects..
 
		if ($this->_curTag == $this->campaignTag) {
			$this->campaignObjArr[] = $this->campaignObj;	
  		}
		else if ($this->_curTag == $this->scheduleIntervalsTag) {
			$this->campaignObj->scheduleObj->schedulingIntervalArr[] = $this->current_ScheduleIntervalObj;	
  		}
		else if ($this->_curTag == $this->campaignStatsTag) {
  			$this->campaignStatsObjArr[] = $this->current_CampaignStatsObj;	
  		}
		else if ($this->_curTag == ($this->geoTargetingTag . "^PROXIMITYTARGETS^CIRCLE")) {
			$this->campaignObj->geoTargetingObj->proximityTargetsObj->circlesArr[] = $this->current_GeoCirclesObj;	
  		}



	}
	
	
	
 } 




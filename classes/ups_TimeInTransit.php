<?

class UPS_ServiceSummary {

	public $serviceCode;
	public $serviceDescription;
	public $guaranteed;
	public $businessTransitDays;
	public $arrivalTime;
	public $arrivalDate;
	public $arrivalDay;
	public $pickupDate;

	// constructor, initalize members to blank strings --#
	function UPS_ServiceSummary(){

		$this->serviceCode = "";
		$this->serviceDescription = "";
		$this->guaranteed = "";
		$this->businessTransitDays = "";
		$this->arrivalTime = "";
		$this->arrivalDate = "";
		$this->arrivalDay = "";
		$this->pickupDate = "";
	}
}

class UPS_TimeInTransit_response {

	private $parser;  //So this object has reference to the XPAT xml proccesor
	private $curTag;
	private $attributes;

	#-- Member Variables --#
	private $_XMLfile;
	private $_xpciVersion;
	
	private $_ResponseStatusDescription;
	private $_ResponseStatusCode;
	private $_ErrorSeverity;
	private $_ErrorCode;
	private $_ErrorDescription;
	
	private $_CommunicationErrorFlag;
	
	private $_candidateListFlag;
	private $_serviceSummaryListFlag;

	//this will contain an array of hashes, all with the different shipping methods available to a destination
	private $_ServiceSummariesArr = array();



	##--------------   Constructor  ------------------##
	function UPS_TimeInTransit_response($XMLfile){

		$this->_XMLfile = $XMLfile;

		$this->_xpciVersion = "";
		$this->_ResponseStatusDescription = "";
		$this->_ResponseStatusCode = "";
		$this->_ErrorSeverity = "";
		$this->_ErrorCode = "";
		$this->_ErrorDescription = "";
		$this->_CommunicationErrorFlag = false;
		$this->_candidateListFlag = false;
		$this->_serviceSummaryListFlag = false;


		//An extra level of protection... in case the server is having an internal error and we are not getting back a well formed XML document
		if(!preg_match("/TimeInTransitResponse/i", $this->_XMLfile)){
			$this->_CommunicationErrorFlag = true;
			$this->_ErrorDescription = "An unknown error has occured.  The AddressValidationResponse tag was not found";
		}
		else
			$this->parseXMLdoc();

	}


	##-----------------   Methods  -------------------##

	// This will return an array of UPS_ServiceSummary objects

	function GetServiceSummariesArr(){
		return $this->_ServiceSummariesArr;
	}

	function GetStatusDescription() {
		return $this->_ResponseStatusDescription;
	}
	function GetStatusCode() {
		return $this->_ResponseStatusCode;
	}
	function GetErrorCode() {
		return $this->_ErrorCode;
	}
	function GetErrorDescription() {
		return $this->_ErrorDescription;
	}
	function GetErrorSeverity() {
		return $this->_ErrorSeverity;
	}
	function CheckIfCommunicationError() {
		return $this->_CommunicationErrorFlag;
	}
	
	// We know for sure that an address is OK if UPS responds with a list of service summaries.
	function CheckIfAddressIsOK(){
			
		return $this->_serviceSummaryListFlag;
	}



	####### ----- Private Methods Below ---------- ########

	#--------------------------  Methods below to parse the xml document ----------------------------------------#
	function parseXMLdoc(){

		##--- Define Event driven functions for XPAT processor. ----##
		$this->parser = xml_parser_create();
		xml_set_object($this->parser, $this);
		xml_set_element_handler($this->parser, "startElement", "endElement");
		xml_set_character_data_handler($this->parser, "characterData");

		##--- Parse the XMl document.  This will call our functions that we just set handlers for above during the processing. ----#
		if (!xml_parse($this->parser, $this->_XMLfile)) {
			die(sprintf("XML error: %s at line %d", xml_error_string(xml_get_error_code($this->parser)), xml_get_current_line_number($this->parser)));
		}

		xml_parser_free($this->parser);
	}

	function startElement($parser, $name, $attrs) {

		$this->attributes = $attrs;

		$this->curTag .= "^$name";
	  
	  	//Create a new element to the array of service summaries
		if($this->curTag == "^TIMEINTRANSITRESPONSE^TRANSITRESPONSE^SERVICESUMMARY")			
			$this->_ServiceSummariesArr[] = new UPS_ServiceSummary();
	}

	function endElement($parser, $name) {

		$caret_pos = strrpos($this->curTag,'^');

		$this->curTag = substr($this->curTag, 0, $caret_pos);
	}



	function characterData($parser, $data) {

		##-- Define  possible tag structures with a carrot separating each tag name. __##


		$CommunicationError_KEY = "^TIMEINTRANSITRESPONSE^COMMUNICATIONERROR";
		$ResponseStatusCode_KEY = "^TIMEINTRANSITRESPONSE^RESPONSE^RESPONSESTATUSCODE";
		$ResponseStatusDesc_KEY = "^TIMEINTRANSITRESPONSE^RESPONSE^RESPONSESTATUSDESCRIPTION";
		$ResponseErrorSevertity_KEY = "^TIMEINTRANSITRESPONSE^RESPONSE^ERROR^ERRORSEVERITY";
		$ResponseErrorCode_KEY = "^TIMEINTRANSITRESPONSE^RESPONSE^ERROR^ERRORCODE";
		$ResponseErrorDesc_KEY = "^TIMEINTRANSITRESPONSE^RESPONSE^ERROR^ERRORDESCRIPTION";

		$ServiceSumServiceCode_KEY = "^TIMEINTRANSITRESPONSE^TRANSITRESPONSE^SERVICESUMMARY^SERVICE^CODE";
		$ServiceSumServiceDesc_KEY = "^TIMEINTRANSITRESPONSE^TRANSITRESPONSE^SERVICESUMMARY^SERVICE^DESCRIPTION";
		$ServiceSumGuaranteedCode_KEY = "^TIMEINTRANSITRESPONSE^TRANSITRESPONSE^SERVICESUMMARY^GUARANTEED^CODE";
		$ServiceSumArrivalTransitDays_KEY = "^TIMEINTRANSITRESPONSE^TRANSITRESPONSE^SERVICESUMMARY^ESTIMATEDARRIVAL^BUSINESSTRANSITDAYS";
		$ServiceSumArrivalTime_KEY = "^TIMEINTRANSITRESPONSE^TRANSITRESPONSE^SERVICESUMMARY^ESTIMATEDARRIVAL^TIME";
		$ServiceSumArrivalPickupDate_KEY = "^TIMEINTRANSITRESPONSE^TRANSITRESPONSE^SERVICESUMMARY^ESTIMATEDARRIVAL^PICKUPDATE";
		$ServiceSumArrivalArrivalDate_KEY = "^TIMEINTRANSITRESPONSE^TRANSITRESPONSE^SERVICESUMMARY^ESTIMATEDARRIVAL^DATE";
		$ServiceSumArrivalDayOfWeek_KEY = "^TIMEINTRANSITRESPONSE^TRANSITRESPONSE^SERVICESUMMARY^ESTIMATEDARRIVAL^DAYOFWEEK";
		$TransitToCandidate_KEY = "^TIMEINTRANSITRESPONSE^TRANSITTOLIST^CANDIDATE";
		

		// If we come across an CommunicationError key... then it was put in by our own code somewhere in the form of XML.  UPS does not provide this key in any of their XML docs.
		// Just set a flag in the communication object letting us know that there was an error... and then piggy back on the regular error description member.
		if ($this->curTag == $CommunicationError_KEY){
			$this->_CommunicationErrorFlag = true;
			$this->_ErrorDescription = $data;
		}
		else if ($this->curTag == $ResponseStatusCode_KEY)
			$this->_ResponseStatusCode = $data;
		else if ($this->curTag == $ResponseStatusDesc_KEY)
			$this->_ResponseStatusDescription = $data;
		else if ($this->curTag == $ResponseErrorSevertity_KEY)
			$this->_ErrorSeverity = $data;
		else if ($this->curTag == $ResponseErrorCode_KEY)
			$this->_ErrorCode = $data;
		else if ($this->curTag == $ResponseErrorDesc_KEY) 
			$this->_ErrorDescription = $data;
		
		else if ($this->curTag == $TransitToCandidate_KEY) 
			$this->_candidateListFlag = true;
			
			

		else if ($this->curTag == $ServiceSumServiceCode_KEY){
			$this->_ServiceSummariesArr[sizeof($this->_ServiceSummariesArr) - 1]->serviceCode = $data;
			$this->_serviceSummaryListFlag = true;
		}
		else if ($this->curTag == $ServiceSumServiceDesc_KEY) 
			$this->_ServiceSummariesArr[sizeof($this->_ServiceSummariesArr) - 1]->serviceDescription = $data;
		else if ($this->curTag == $ServiceSumGuaranteedCode_KEY){
			if(strtoupper($data) == "Y")
				$this->_ServiceSummariesArr[sizeof($this->_ServiceSummariesArr) - 1]->guaranteed = true;
			else
				$this->_ServiceSummariesArr[sizeof($this->_ServiceSummariesArr) - 1]->guaranteed = false;
		}
		else if ($this->curTag == $ServiceSumArrivalTransitDays_KEY) 
			$this->_ServiceSummariesArr[sizeof($this->_ServiceSummariesArr) - 1]->businessTransitDays = $data;
		else if ($this->curTag == $ServiceSumArrivalTime_KEY) 
			$this->_ServiceSummariesArr[sizeof($this->_ServiceSummariesArr) - 1]->arrivalTime = $data;
		else if ($this->curTag == $ServiceSumArrivalArrivalDate_KEY) 
			$this->_ServiceSummariesArr[sizeof($this->_ServiceSummariesArr) - 1]->arrivalDate = $data;
		else if ($this->curTag == $ServiceSumArrivalDayOfWeek_KEY) 
			$this->_ServiceSummariesArr[sizeof($this->_ServiceSummariesArr) - 1]->arrivalDay = $data;
		else if ($this->curTag == $ServiceSumArrivalPickupDate_KEY) 
			$this->_ServiceSummariesArr[sizeof($this->_ServiceSummariesArr) - 1]->pickupDate = $data;


	}
}

class UPS_TimeInTransit_request {

	#-- Member Variables --#
	private $_shipTocity;
	private $_shipToState;
	private $_shipToPostalcode;
	private $_shipToCountryCode;
	private $_shipToResidentialFlag;
	
	private $_shipFromCity;
	private $_shipFromState;
	private $_shipFromPostalcode;
	private $_shipFromCountryCode;
	
	private $_weight;
	private $_dollarValue;
	private $_pickupDate;
	private $_XpciVersion;
	private $_AccessLicenseNumber;
	private $_UserId;
	private $_Password;




	##--------------   Constructor  ------------------##
	function UPS_TimeInTransit_request($shipFromCity, $shipFromState, $shipFromPostalCode, $shipFromCountryCode, $shipToCity, $shipToState, $shipToPostalcode, $shipToCountryCode, $shipToResidentialFlag, $pickupDate){

			$yearFromPickupDate = intval(substr($pickupDate, 0, 4));
			if(strlen($pickupDate) != 8 || $yearFromPickupDate < 2000 || $yearFromPickupDate > 2200)
				throw new Exception("Error with UPS_TimeInTransit_request. The pickup date must be in the format YYYYMMDD");
			
			
			// Just use a default of 10 pounds... We will never have really heavy packages so we don't need to see hundred weight options or anything
			$this->_weight = "10";

			// Just default to a dollar value of $50
			// It is unlikely that the value would be so hight that it would cause any change to the available shipping methods
			$this->_dollarValue = "50.00";

			$this->_shipTocity = $shipToCity;
			$this->_shipToState = $shipToState;
			$this->_shipToPostalcode = $shipToPostalcode;
			$this->_shipToCountryCode = $shipToCountryCode;
			$this->_shipToResidentialFlag = $shipToResidentialFlag;
			
			$this->_shipFromCity = $shipFromCity;
			$this->_shipFromState = $shipFromState;
			$this->_shipFromPostalcode = $shipFromPostalCode;
			$this->_shipFromCountryCode = $shipFromCountryCode;
			
			$this->_pickupDate = $pickupDate;
			
	
			//Hard code for now
			$this->_XpciVersion = "1.0001";
			$this->_AccessLicenseNumber = "7B9DE9F4A38077D8";
			$this->_UserId = "bpiere";
			$this->_Password = "SDFSF34H";
	}

	function BuildAccessRequestXMLfile(){

		$this->_xmlfile = 	'<?xml version="1.0"?>
					<AccessRequest xml:lang="en-US">
					<AccessLicenseNumber>' . $this->_AccessLicenseNumber . '</AccessLicenseNumber>
					<UserId>' . $this->_UserId . '</UserId>
					<Password>' . $this->_Password . '</Password>
					</AccessRequest>
					';

		return $this->_xmlfile;

	}
	function BuildTimeInTransitDetailsXMLfile(){

		$this->_xmlfile = 	'<?xml version="1.0"?>
		<TimeInTransitRequest xml:lang="en-US">
		<Request>
		<TransactionReference>
		<CustomerContext>Getting Time In Transit</CustomerContext>
		<XpciVersion>1.0002</XpciVersion>
		</TransactionReference>
		<RequestAction>TimeInTransit</RequestAction>
		</Request>
		<TransitFrom>
		<AddressArtifactFormat>
		<PoliticalDivision2>' . WebUtil::htmlOutput($this->_shipFromCity) . '</PoliticalDivision2>
		<PoliticalDivision1>' . WebUtil::htmlOutput($this->_shipFromState) . '</PoliticalDivision1>
		<CountryCode>' . WebUtil::htmlOutput($this->_shipFromCountryCode) . '</CountryCode>
		<PostcodePrimaryLow>' . WebUtil::htmlOutput($this->_shipFromPostalcode) . '</PostcodePrimaryLow>
		</AddressArtifactFormat>
		</TransitFrom>
		<TransitTo>
		<AddressArtifactFormat>
		<PoliticalDivision2>' . WebUtil::htmlOutput($this->_shipTocity) . '</PoliticalDivision2>
		<PoliticalDivision1>' . WebUtil::htmlOutput($this->_shipToState) . '</PoliticalDivision1>
		<CountryCode>' . $this->_shipToCountryCode . '</CountryCode>
		<PostcodePrimaryLow>' . WebUtil::htmlOutput($this->_shipToPostalcode) . '</PostcodePrimaryLow>
		<ResidentialAddressIndicator>' . ($this->_shipToResidentialFlag ? "1" : "0") . '</ResidentialAddressIndicator>
		</AddressArtifactFormat>
		</TransitTo>
		<ShipmentWeight>
		<UnitOfMeasurement>
		<Code>LBS</Code>
		<Description>Pounds</Description>
		</UnitOfMeasurement>
		<Weight>' . $this->_weight . '</Weight>
		</ShipmentWeight>
		<TotalPackagesInShipment>1</TotalPackagesInShipment>
		<InvoiceLineTotal>
		<CurrencyCode>USD</CurrencyCode>
		<MonetaryValue>' . $this->_dollarValue . '</MonetaryValue>
		</InvoiceLineTotal>
		<PickupDate>' . $this->_pickupDate . '</PickupDate>
		</TimeInTransitRequest>';

		return $this->_xmlfile;
	}
}

// These are all static Methods in this Class

class UPS_TimeInTransit {
	//This function will connect with the UPS server and return a UPS_TimeInTransit_response Object.
	//If no PickupDate is supplied then it assumes we are going to use Next Friday.
	static function Check($shipFromCity, $shipFromState, $shipFromPostalCode, $shipFromCountryCode, $shipToCity, $shipToState, $shipToPostalcode, $shipToCountryCode, $shipToResidentialFlag, $pickupDate = NULL){

		
		// Digestive hash to use as a key in our database to check if the result is cached.
		// Don't cache the Pickup Date (if it is NULL) because Transit Times are always the same.
		$upsRequestSignature = md5($shipFromCity . $shipFromState . $shipFromPostalCode . $shipFromCountryCode . $shipToCity . $shipToState . $shipToPostalcode . $shipToCountryCode . $pickupDate . ($shipToResidentialFlag ? "Y" : "N"));
		
		if(empty($pickupDate)){
			// All of UPS's "days in transit" are the same for the address (as long as you calculate weekends and holidays yourself). 
			// If using an API call... assume that the Pickup date will be on next Friday.  This is when the most amount of shipping options are available (such as Saturday)
			// ... We don't care about the "Arrival Date" in the API calls.  We just want to know how many "days in transit" each shipping method takes.  We will calculate our own ArrivalDate and filter out Saturday Shipping options if needed.
			// .... this will also give us the best chance to cache results since it doesn't matter what Friday.
			$nextFriday = EventScheduler::getNextFridayNotShippingCarrierHoliday();
			$pickupDate = $nextFriday["Year"] . $nextFriday["Month"] . $nextFriday["Day"];
		}
		
		
		// Find out if our results are cached.
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT Response, UNIX_TIMESTAMP(DateCreated) AS DateCreated FROM shippingtransitcache_ups 
							WHERE RequestSignature='". $upsRequestSignature . "'");
		if($dbCmd->GetNumRows() > 0){
			
			$row = $dbCmd->GetRow();
			
			$yearCreated = date("Y", $row["DateCreated"]);
			//$monthCreated = date("j", $row["DateCreated"]);
			
			// Stop caching the result if it was saved last year... (and at least 1 month old).  We don't want the Server to get pounded on Jan 1st all of a sudden.
			// If it is older... then let the script continue on and contact the UPS server.  It will refresh the Date and Response XML if it is successful.
			$oneMonthOfSeconds = 60*60*24*31;
			if(!($yearCreated < date("Y") && (time() - $row["DateCreated"] > $oneMonthOfSeconds)))
				return new UPS_TimeInTransit_response($row["Response"]);
		}
		
		
		$upsReqObj = new UPS_TimeInTransit_request($shipFromCity, $shipFromState, $shipFromPostalCode, $shipFromCountryCode, $shipToCity, $shipToState, $shipToPostalcode, $shipToCountryCode, $shipToResidentialFlag, $pickupDate);

		//Testing : https://wwwcie.ups.com/ups.app/xml/TimeInTransit
		//Actual : https://www.ups.com/ups.app/xml/TimeInTransit
		$PaymentGatewayHost = "https://www.ups.com/ups.app/xml/TimeInTransit";

		$POST_string = $upsReqObj->BuildAccessRequestXMLfile() .  $upsReqObj->BuildTimeInTransitDetailsXMLfile();
		$POST_string = addslashes($POST_string);
		$POST_string = preg_replace("/(\r|\n)/", "", $POST_string);

// Let our development server contact the Real UPS server.
		if(!Constants::GetDevelopmentServer()){

			$return_value = "";
			
			// -m 30 lets us have up to 30 seconds to get a response back from the server.
			// -k means the server doesn't need a valid certificate to connect in SSL mode.
			// -d means that we are posting data.
			exec(Constants::GetCurlCommand() . " -m 30 -k -d \"$POST_string\" $PaymentGatewayHost", $return_value);

			if(!is_array($return_value) || !isset($return_value[0])){
				$DisplayMsg = "Failed to make an SSL connection to UPS Time In Transit API";

				return new UPS_TimeInTransit_response('<?xml version="1.0"?><TimeInTransitResponse><CommunicationError>'. $DisplayMsg .'</CommunicationError></TimeInTransitResponse>');
			}

			$retXML = implode("", $return_value);
		}

		else{

			#-- This is just just for debuging on the development server --#

			// Successful with Saturday Delivery
			$retXML = '<?xml version="1.0"?><TimeInTransitResponse><Response><TransactionReference><CustomerContext>Testing Candidate List</CustomerContext><XpciVersion>1.0002</XpciVersion></TransactionReference><ResponseStatusCode>1</ResponseStatusCode><ResponseStatusDescription>Success</ResponseStatusDescription></Response><TransitResponse><PickupDate>2005-07-29</PickupDate><TransitFrom><AddressArtifactFormat><PoliticalDivision2>THOMASVILLE</PoliticalDivision2><PoliticalDivision1>NC</PoliticalDivision1><Country>UNITED STATES</Country><CountryCode>US</CountryCode><PostcodePrimaryLow>26360</PostcodePrimaryLow></AddressArtifactFormat></TransitFrom><TransitTo><AddressArtifactFormat><PoliticalDivision2>NEWBURY PARK</PoliticalDivision2><PoliticalDivision1>CA</PoliticalDivision1><Country>UNITED STATES</Country><CountryCode>US</CountryCode><PostcodePrimaryLow>91320</PostcodePrimaryLow></AddressArtifactFormat></TransitTo><ShipmentWeight><UnitOfMeasurement><Code>LBS</Code></UnitOfMeasurement><Weight>20.0</Weight></ShipmentWeight><InvoiceLineTotal><CurrencyCode>USD</CurrencyCode><MonetaryValue>35.00</MonetaryValue></InvoiceLineTotal><Disclaimer>Services listed as guaranteed are backed by a money-back guarantee for transportation charges only. UPS guarantees the day of delivery for every ground package you ship to any address within the 48 contiguous states, except for any ground package originating in Alaska or Hawaii. In addition, the guarantee applies to shipments from Puerto Rico to the 48 contiguous states. See Terms and Conditions in the Service Guide for details.</Disclaimer><ServiceSummary><Service><Code>1DAS</Code><Description>UPS Next Day Air (Saturday Delivery)</Description></Service><Guaranteed><Code>Y</Code></Guaranteed><EstimatedArrival><BusinessTransitDays>1</BusinessTransitDays><Time>12:00:00</Time><PickupDate>2005-07-29</PickupDate><Date>2005-07-30</Date><DayOfWeek>SAT</DayOfWeek></EstimatedArrival></ServiceSummary><ServiceSummary><Service><Code>1DA</Code><Description>UPS Next Day Air</Description></Service><Guaranteed><Code>Y</Code></Guaranteed><EstimatedArrival><BusinessTransitDays>1</BusinessTransitDays><Time>10:30:00</Time><PickupDate>2005-07-29</PickupDate><Date>2005-08-01</Date><DayOfWeek>MON</DayOfWeek></EstimatedArrival></ServiceSummary><ServiceSummary><Service><Code>1DP</Code><Description>UPS Next Day Air Saver</Description></Service><Guaranteed><Code>Y</Code></Guaranteed><EstimatedArrival><BusinessTransitDays>1</BusinessTransitDays><Time>15:00:00</Time><PickupDate>2005-07-29</PickupDate><Date>2005-08-01</Date><DayOfWeek>MON</DayOfWeek></EstimatedArrival></ServiceSummary><ServiceSummary><Service><Code>2DM</Code><Description>UPS 2nd Day Air A.M.</Description></Service><Guaranteed><Code>Y</Code></Guaranteed><EstimatedArrival><BusinessTransitDays>2</BusinessTransitDays><Time>12:00:00</Time><PickupDate>2005-07-29</PickupDate><Date>2005-08-02</Date><DayOfWeek>TUE</DayOfWeek></EstimatedArrival></ServiceSummary><ServiceSummary><Service><Code>2DA</Code><Description>UPS 2nd Day Air</Description></Service><Guaranteed><Code>Y</Code></Guaranteed><EstimatedArrival><BusinessTransitDays>2</BusinessTransitDays><Time>23:00:00</Time><PickupDate>2005-07-29</PickupDate><Date>2005-08-02</Date><DayOfWeek>TUE</DayOfWeek></EstimatedArrival></ServiceSummary><ServiceSummary><Service><Code>3DS</Code><Description>UPS 3 Day Select</Description></Service><Guaranteed><Code>Y</Code></Guaranteed><EstimatedArrival><BusinessTransitDays>3</BusinessTransitDays><Time>23:00:00</Time><PickupDate>2005-07-29</PickupDate><Date>2005-08-03</Date><DayOfWeek>WED</DayOfWeek></EstimatedArrival></ServiceSummary><ServiceSummary><Service><Code>GND</Code><Description>UPS Ground</Description></Service><Guaranteed><Code>Y</Code></Guaranteed><EstimatedArrival><BusinessTransitDays>5</BusinessTransitDays><Time>23:00:00</Time><PickupDate>2005-07-29</PickupDate><Date>2005-08-05</Date><DayOfWeek>FRI</DayOfWeek></EstimatedArrival></ServiceSummary><MaximumListSize>35</MaximumListSize></TransitResponse></TimeInTransitResponse>';

		}



		// We only want to cache the response if it is successful... 
		if(preg_match("/TimeInTransitResponse/i", $retXML) && preg_match("/BusinessTransitDays/i", $retXML)){
			
			// prevent duplicates
			$dbCmd->Query("DELETE FROM shippingtransitcache_ups WHERE RequestSignature='". $upsRequestSignature . "'");
			
			$dbCmd->InsertQuery("shippingtransitcache_ups", array("RequestSignature"=>$upsRequestSignature, "Response"=>$retXML));
		}

		return new UPS_TimeInTransit_response($retXML);
	}

	static function GetUPSerrorMessageForCustomer(){
		return "An error occured when trying to connect to the server for the United Parcel Service.  We must validate shipping addresses so that we can maintain an high level of efficiency.\n\nYou should save your project(s) if you have not done so already.  You can try again in a little while.\n\nA message has automaticaly been sent to the webmaster reporting this problem.";
	}
	
	// The UPS timestamp is in the form of HH:MM:SS

	// We want to return the components without leading zeros.
	// If it can't find a match then it will just return a blank string
	static function GetHoursFromUPStimestamp($UPStimeStampString){
		
		$matches = array();
		if(preg_match_all("/^(\d+):\d+:\d+$/", $UPStimeStampString, $matches)){
			$hoursComponent = $matches[1][0];
			// Get rid of a leading zero
			$hoursComponent = preg_replace("/^0/", "", $hoursComponent);
			return $hoursComponent;
		}
		return "";
	}
	static function GetMintuesFromUPStimestamp($UPStimeStampString){
		
		$matches = array();
		if(preg_match_all("/^\d+:(\d+):\d+$/", $UPStimeStampString, $matches)){
			$minutesComponent = $matches[1][0];
			// Get rid of a leading zero
			$minutesComponent = preg_replace("/^0/", "", $minutesComponent);
			return $minutesComponent;
		}
		return "";
	}
	
	// Converts a UPS service code into our internal code.
	// If it does not find a match... it will return null
	static function GetShippingCodeFromUPSserviceCodes($UPSserviceCode){

		if($UPSserviceCode == "1DA")
			return ShippingCarrier_UPS::NEXTDAY;
			
		else if($UPSserviceCode == "1DM") 
			return ShippingCarrier_UPS::NEXTDAY_EARLY;
			
		else if($UPSserviceCode == "1DP")
			return ShippingCarrier_UPS::NEXTDAY_SAVER;
			
		else if($UPSserviceCode == "1DAS")
			return ShippingCarrier_UPS::NEXTDAY_SATURDAY;
			
		else if($UPSserviceCode == "1DMS")
			return ShippingCarrier_UPS::NEXTDAY_SATURDAY_EARLY;
			
		else if($UPSserviceCode == "2DA")
			return ShippingCarrier_UPS::TWODAY;
			
		else if($UPSserviceCode == "2DAS")	//  I am not sure what this code is, I think it is for 2-day saturday delivery?
			return null;
			
		else if($UPSserviceCode == "2DM")
			return ShippingCarrier_UPS::TWODAY_EARLY;
			
		else if($UPSserviceCode == "3DS")
			return ShippingCarrier_UPS::THREE_DAY_SELECT;
			
		else if($UPSserviceCode == "GND" || $UPSserviceCode == "G")
			return ShippingCarrier_UPS::GROUND;
			
		else if($UPSserviceCode == "01" || $UPSserviceCode == "EXP")
			return ShippingCarrier_UPS::WORLDWIDE_EXPRESS;
			
		else if($UPSserviceCode == "28")
			return ShippingCarrier_UPS::WORLDWIDE_SAVER;
			
		else if($UPSserviceCode == "02")
			return ShippingCarrier_UPS::INTERNATIONAL_TWODAY;
			
		else if($UPSserviceCode == "21" || $UPSserviceCode == "EHL")
			return ShippingCarrier_UPS::WORLDWIDE_EXPRESS_PLUS;
			
		else if($UPSserviceCode == "05" || $UPSserviceCode == "WWS")
			return ShippingCarrier_UPS::WORLDWIDE_EXPEDITED;
			
		else if($UPSserviceCode == "03" || $UPSserviceCode == "STD")
			return ShippingCarrier_UPS::CANADA_GROUND;
		else{
			WebUtil::WebmasterError("The given UPS service code has not been defined yet: " . $UPSserviceCode);
			return null;
		}
	}
}



?>
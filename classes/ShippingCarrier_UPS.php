<?php


class ShippingCarrier_UPS implements ShippingMethodsInterface {
	
	private $shipMethodsHash = array();
	private $shippingCodesArr = array();
	private $addressNotValidFlag;
	private $addressSuggestionsArr = array();
	private $transitTimesObjArr = array();
	
	// The keep an array of Address Signatures that have been verified already during the execution of this thread/script to cut down on DB access.
	private static $addressesVerifiedAlreadyArr = array();
	
	// Shipping Codes that we will use internally.
	const WORLDWIDE_EXPRESS_PLUS = "UWP";
	const WORLDWIDE_EXPRESS = "UWE";
	const WORLDWIDE_SAVER = "UWS";
	const NEXTDAY_SATURDAY_EARLY = "USE";
	const NEXTDAY_SATURDAY = "US";
	const NEXTDAY_EARLY = "U1E";
	const NEXTDAY = "U1";
	const NEXTDAY_SAVER = "U1S";
	const INTERNATIONAL_TWODAY = "UW2";
	const TWODAY_EARLY = "U2E";
	const TWODAY = "U2";
	const WORLDWIDE_EXPEDITED = "UWD";
	const CANADA_GROUND = "UCG";
	const THREE_DAY_SELECT = "U3";
	const GROUND = "UG";
	const UNKNOWN_SHIP_METHOD = "UU";
	
	function __construct() {
	
		$this->shipMethodsHash[ShippingCarrier_UPS::WORLDWIDE_EXPRESS_PLUS] = 	"Worldwide Express Plus";
		$this->shipMethodsHash[ShippingCarrier_UPS::WORLDWIDE_EXPRESS] = 		"Worldwide Express";
		$this->shipMethodsHash[ShippingCarrier_UPS::WORLDWIDE_SAVER] = 			"Worldwide Saver";
		$this->shipMethodsHash[ShippingCarrier_UPS::NEXTDAY_SATURDAY_EARLY] = 	"Saturday Next Day Air Early AM";
		$this->shipMethodsHash[ShippingCarrier_UPS::NEXTDAY_SATURDAY] = 		"Saturday  Next Day Air";
		$this->shipMethodsHash[ShippingCarrier_UPS::NEXTDAY_EARLY] = 			"Next Day Air Early AM";
		$this->shipMethodsHash[ShippingCarrier_UPS::NEXTDAY] = 					"Next Day Air";
		$this->shipMethodsHash[ShippingCarrier_UPS::NEXTDAY_SAVER] = 			"Next Day Air Saver";
		$this->shipMethodsHash[ShippingCarrier_UPS::INTERNATIONAL_TWODAY] = 	"International 2 Day Air";
		$this->shipMethodsHash[ShippingCarrier_UPS::TWODAY_EARLY] = 			"2nd Day Air AM";
		$this->shipMethodsHash[ShippingCarrier_UPS::TWODAY] = 					"2nd Day Air";
		$this->shipMethodsHash[ShippingCarrier_UPS::WORLDWIDE_EXPEDITED] = 		"Worldwide Expedited";
		$this->shipMethodsHash[ShippingCarrier_UPS::CANADA_GROUND] = 			"Canada Ground";
		$this->shipMethodsHash[ShippingCarrier_UPS::THREE_DAY_SELECT] = 		"3 Day Select";
		$this->shipMethodsHash[ShippingCarrier_UPS::GROUND] = 					"Ground";
		$this->shipMethodsHash[ShippingCarrier_UPS::UNKNOWN_SHIP_METHOD] = 		"Unknown Method";
		
		$this->shippingCodesArr = array_keys($this->shipMethodsHash);
			
	}
	
	
	// All Possible Shipment Methods.  
	// The Descriptions are what the admin sees... not necessarily what the Customer will see.
	function getShippingMethodsHash() {
			return $this->shipMethodsHash;	
	}
	
	// This is what we export into the OCBC database for UPS Worlship
	// The Shipment Methods for UPS Woldship is very similar to what the Admins see (on our backend)
	// The only differenc is with Saturday Shipments.  Worldship still calls that "Next Day Air"... but they want a Saturday Delivery Flag checked.
	function getCarrierReference($shippignMethodCode){
		
		if(!array_key_exists($shippignMethodCode, $this->shipMethodsHash))
			throw new Exception("Error in method getCarrierReference. The Shipping Code was not found.");
		
		if($shippignMethodCode == ShippingCarrier_UPS::NEXTDAY_SATURDAY_EARLY)
			return "Next Day Air Early AM";
		else if($shippignMethodCode == ShippingCarrier_UPS::NEXTDAY_SATURDAY)
			return "Next Day Air";
		else 
			return $this->shipMethodsHash[$shippignMethodCode];
		
	}
	
	// Pass in shipping method description used by the shipping carrier, it will convert it back to our internal shipping method code.
	function getShippingMethodCodeFromCarrierReference($shippingCarrierName, $shippingMethodCarrierReference){
		
		if($shippingCarrierName != ShippingMethods::CARRIER_CODE_UPS)
			throw new Exception("Error in getShippingMethodCodeFromCarrierReference. The Shipping Carrier is invalid for this interface: $shippingCarrierName");
			
		foreach (array_keys($this->shipMethodsHash) as $thisShippingMethodCode){
			if($this->getCarrierReference($thisShippingMethodCode) == $shippingMethodCarrierReference)
				return $thisShippingMethodCode;
		}
		
		return ShippingCarrier_UPS::UNKNOWN_SHIP_METHOD;
		
	}
	

	// Returns a Transit Time Object for all of the shipping methods and arrival times that are available to the destination.
	// Does not filter out Saturday Deliveries.
	function getTransitTimes(MailingAddress $shipFromAddressObj, MailingAddress $shipToAddressObj) {
	
		if(!$this->isAddressValid($shipFromAddressObj) || !$this->isAddressValid($shipToAddressObj))
			throw new Exception("Error in method ShippingCarrier_UPS::getTransitTimes. The address must be intialized before calling this method.");
		
		$this->fillTranistTimesObjectIfNotSet($shipFromAddressObj, $shipToAddressObj);
			
		return $this->transitTimesObjArr[$this->getShipFromAndToAddressSignature($shipFromAddressObj, $shipToAddressObj)];
			
	}
	
	// Returns a unique digestive hash relating to both the ShipFrom and ShipTo Addresses.
	private function getShipFromAndToAddressSignature(MailingAddress $shipFromAddressObj, MailingAddress $shipToAddressObj){
		return md5($shipFromAddressObj->toString() . $shipToAddressObj->toString());
	}
	
	
	// Returns an array of ShippingMethod Codes.   Transit API's may omit popular shipping choices if the destination is close.
	// This is not good for Shipping Downgrades.  Fills in missing Shipping Codes... such as it may add "2 day shipping" for next door neighbors.
	public function getAllShippingMethodsPossible(MailingAddress $shipFromAddressObj, MailingAddress $shipToAddressObj){
		
		$transitTimesObj = $this->getTransitTimes($shipFromAddressObj, $shipToAddressObj);
		$retArr = $transitTimesObj->getShippingCodesSet();
		
		// Just fill 2day, and 3day if they are missing.
		if($shipToAddressObj->getCountryCode() == "US"){
			
			// Hawaii and Alaska may not have 2day or 3day shipping available.
			if(!in_array($shipToAddressObj->getState(), array("HI", "AK"))){
			
				if(!in_array(self::TWODAY, $retArr))
					$retArr[] = self::TWODAY;
				
				if(!in_array(self::THREE_DAY_SELECT, $retArr))
					$retArr[] = self::THREE_DAY_SELECT;
			}
		}

		
		return $retArr;
	}
	
	private function fillTranistTimesObjectIfNotSet(MailingAddress $shipFromAddressObj, MailingAddress $shipToAddressObj){
		
		// In case this Object is reused for muliple Shipping Addresses... we cache the Transit Times in an array
		// The key to the Array is a unique MD5 for the Address.
		if(isset($this->transitTimesObjArr[$this->getShipFromAndToAddressSignature($shipFromAddressObj, $shipToAddressObj)]))
			return;
			
		// Don't Supply a Pickup Date.  So it will try to get the Tranist Times starting from Next Friday.
		$UPStimeTransitResponseObj = UPS_TimeInTransit::Check($shipFromAddressObj->getCity(), $shipFromAddressObj->getState(), $shipFromAddressObj->getZipCode(), $shipFromAddressObj->getCountryCode(), $shipToAddressObj->getCity(), $shipToAddressObj->getState(), $shipToAddressObj->getZipCode(), $shipToAddressObj->getCountryCode(), $shipToAddressObj->isResidential());
	
		if($UPStimeTransitResponseObj->CheckIfCommunicationError()){
			WebUtil::CommunicationError("Error with UPS Time In Transit Server: " . $UPStimeTransitResponseObj->GetErrorDescription() . "\n\n" . $shipToAddressObj->toString());
			throw new ExceptionCommunicationError(UPS_TimeInTransit::GetUPSerrorMessageForCustomer());
		}

		
		if(!$UPStimeTransitResponseObj->CheckIfAddressIsOK()){
			WebUtil::WebmasterError("Error with UPS Time in Transit Server.  Address Verification failed: " . $UPStimeTransitResponseObj->GetErrorDescription() . "\n\n" . $shipToAddressObj->toString());
			throw new ExceptionInvalidShippingAddress(UPS_TimeInTransit::GetUPSerrorMessageForCustomer());
		}
			
		// Now fill up our TransistTimesObj with details on each shipping method
		$transitTimesObj = new TransitTimes();
		
		// Get an array of UPS_ServiceSummary objects that contain information about each individual shipment option
		$UPSserviceSummaryObjectsArr = $UPStimeTransitResponseObj->GetServiceSummariesArr();
	
		foreach($UPSserviceSummaryObjectsArr as $thisUPSserviceSummaryObj){
	
			// Parse out the information returned by UPS
			$shippingCode = UPS_TimeInTransit::GetShippingCodeFromUPSserviceCodes($thisUPSserviceSummaryObj->serviceCode);
			$arrivalHour = UPS_TimeInTransit::GetHoursFromUPStimestamp($thisUPSserviceSummaryObj->arrivalTime);
			$arrivalMinute = UPS_TimeInTransit::GetMintuesFromUPStimestamp($thisUPSserviceSummaryObj->arrivalTime);
	
			// In case UPS added a new shipping code that we are not aware of... just skip over it.  It would have alerted the webmaster already
			if($shippingCode)
				$transitTimesObj->addShippingMethod($shippingCode, $arrivalHour, $arrivalMinute, $thisUPSserviceSummaryObj->businessTransitDays);
		}
		
		$this->transitTimesObjArr[$this->getShipFromAndToAddressSignature($shipFromAddressObj, $shipToAddressObj)] = $transitTimesObj;
	}
	
	function getAddressSuggestions() {
		
		if(!$this->addressNotValidFlag)
			throw new Exception("Error in method getAddressSuggestions. The address has to be invalid before calling this method.");
		
		return $this->addressSuggestionsArr;
	
	}


	
	// First trys looking in our database cache to see if the Address has been verified in the past.
	// If not, it tries to contact the API.  If successful it will save to cache for future use.
	// Throws a CommunicationError exception if UPS can not be contacted.
	function isAddressValid(MailingAddress $addressObj) {
	
		if(!$addressObj->addressInitialized())
			throw new Exception("Error checking Mailing address. Not initialized.");
			
		// The Address Verification system does not work on internation addresses at this time.
		if($addressObj->getCountryCode() != "US")
			return true;
			
		$this->addressSuggestionsArr = array();
		
		// Cut down on DB usage.
		if(in_array($addressObj->getSignatureOfStateCityAndZip(), self::$addressesVerifiedAlreadyArr))
			return true;
		
		$dbCmd = new DbCmd();
		
		$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateVerified) AS DateVerified FROM shippingaddresscache_ups 
							WHERE AddressSignature='". $addressObj->getSignatureOfStateCityAndZip() . "'");
		if($dbCmd->GetNumRows() > 0){
			
			$dateVerified = $dbCmd->GetValue();
			
			// Stop caching the result if it is over 3 years old.
			$threeYearsOfSeconds = 60*60*24*31*12*3;
			if(time() - $dateVerified < $threeYearsOfSeconds){
				
				// If the address has been verified and it is not too old then we can cache the result
				// Otherwish, let our system contact UPS again and refresh the result.
				self::$addressesVerifiedAlreadyArr[] = $addressObj->getSignatureOfStateCityAndZip();
				return true;
				
			}
		}	
		
			
		// Not in the Cache, so contact Address Verification Service.
		$upsAVresponseObj = UPS_AV::ValidateShippingAddress($addressObj->getCity(), $addressObj->getState(), $addressObj->getZipCode());

		if($upsAVresponseObj->CheckIfCommunicationError() || $upsAVresponseObj->GetErrorCode() != ""){
		
			$errMsg = "Error with UPS Address Verification Server: " . $upsAVresponseObj->GetErrorDescription();
		
			if($upsAVresponseObj->CheckIfCommunicationError())
				WebUtil::CommunicationError($errMsg);
			else
				WebUtil::WebmasterError($errMsg);
	
			throw new ExceptionCommunicationError(UPS_AV::GetUPSerrorMessageForCustomer());
	
		}
		
		if($upsAVresponseObj->CheckIfAddressIsOK()){
		
			// The address is valid so update our Database Cache and return true.
			$this->addressNotValidFlag = false;
			
			// Prevent duplicates.
			$dbCmd->Query("DELETE FROM shippingaddresscache_ups WHERE AddressSignature='". $addressObj->getSignatureOfStateCityAndZip() . "'");
			
			$dbCmd->InsertQuery("shippingaddresscache_ups", array("AddressSignature"=>$addressObj->getSignatureOfStateCityAndZip()));
			
			self::$addressesVerifiedAlreadyArr[] = $addressObj->getSignatureOfStateCityAndZip();
			
			return true;
		}
		else{
			
			$this->addressNotValidFlag = true;
			
			
			// This array will contain a list of alternate suggestions from UPS
			// Port that into our general MailingAddressSuggestion object and add to the array.
			$validationResultsArr = $upsAVresponseObj->GetValidationResults();

			foreach($validationResultsArr as $upsSuggestionObj){
				$this->addressSuggestionsArr[] = new MailingAddressSuggestion($upsSuggestionObj->City, $upsSuggestionObj->State, $upsSuggestionObj->postalLow, $upsSuggestionObj->postalHigh);
			}
			
			return false;
		}
	}
	
	function isSaturdayDelivery($shippingCode) {
	
			if(in_array($shippingCode, array(ShippingCarrier_UPS::NEXTDAY_SATURDAY, ShippingCarrier_UPS::NEXTDAY_SATURDAY_EARLY)))
				return true;
			else
				return false;
	}
	
	function isDestinationExtended(MailingAddress $shipFromAddressObj, MailingAddress $shipToAddressObj) {

		if($shipFromAddressObj->getCountryCode() == "US" && $shipToAddressObj->getCountryCode() == "PR")
			return true;
		else
			return false;
	}
	
	function isDestinationRural(MailingAddress $addressObj) {
	
		// Array of all Zip codes that are NOT rural... or that UPS does not charge more money for.

		$AlaskaZips = array(
			"99501",
			"99502",
			"99503",
			"99504",
			"99505",
			"99506",
			"99507",
			"99508",
			"99509",
			"99510",
			"99511",
			"99512",
			"99513",
			"99514",
			"99515",
			"99516",
			"99517",
			"99518",
			"99519",
			"99520",
			"99521",
			"99522",
			"99523",
			"99524",

			"99540",
			"99556",
			"99567",
			"99568",
			"99572",
			"99577",
			"99587",
			"99603",

			"99605",
			"99610",
			"99611",
			"99631",
			"99635",
			"99639",
			"99645",
			"99654",
			"99664",
			"99669",

			"99672",
			"99687",
			"99701",
			"99702",
			"99703",

			"99705",
			"99706",
			"99707",
			"99708",
			"99709",
			"99710",
			"99711",
			"99712",

			"99775"

			);


		$HawaiiZips = array(

			"96701",
			"96706",
			"96707",

			"96709",
			"96712",
			"96717",
			"96730",
			"96731",
			"96734",
			"96744",
			"96759",
			"96762",
			"96782",
			"96786",
			"96789",
			"96791",
			"96792",
			"96795",
			"96797",

			"96801",
			"96802",
			"96803",
			"96804",
			"96805",
			"96806",
			"96807",
			"96808",
			"96809",
			"96810",
			"96811",
			"96812",
			"96813",
			"96814",
			"96815",
			"96816",
			"96817",
			"96818",
			"96819",
			"96820",
			"96821",
			"96822",
			"96823",
			"96824",
			"96825",
			"96826",
			"96827",
			"96828",

			"96830",
			"96831",
			"96832",
			"96833",
			"96834",

			"96835",
			"96836",
			"96837",
			"96838",
			"96839",
			"96840",
			"96841",
			"96842",
			"96843",
			"96844",
			"96845",
			"96846",
			"96847",
			"96848",
			"96849",
			"96850",

			"96853",
			"96854",

			"96857",
			"96858",
			"96859",
			"96860",
			"96861",
			"96862",
			"96863",

			"96898"

			);

		$allRuralZipsArr = array_merge($HawaiiZips, $AlaskaZips);
		
		if($addressObj->getCountryCode() == "US"){
			
			if($addressObj->getState() == "HI" || $addressObj->getState() == "AK"){
				if(in_array($addressObj->getZipCode(), $allRuralZipsArr))
					return false;
				else
					return true;
			}
			else{
				// Right now we are only charging Rural fees to Hawaii and Alaska
				return false;
			}
		}
		else{
			return false;
		}

	}
	

	
}

?>

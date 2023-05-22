<?php


class ShippingCarrier_USPS implements ShippingMethodsInterface {
	
	private $shipMethodsHash = array();
	private $shippingCodesArr = array();
	private $transitTimesObjArr = array();

	private $shippingCarrierUPS;

	
	// The keep an array of Address Signatures that have been verified already during the execution of this thread/script to cut down on DB access.
	private static $addressesVerifiedAlreadyArr = array();
	
	// Shipping Codes that we will use internally.
	const POSTAGE_FIRST_CLASS = "PF";
	const POSTAGE_PRIORITY = "PP";
	const POSTAGE_UNKNOWN = "PU";
	
	function __construct() {
	
		$this->shipMethodsHash[ShippingCarrier_USPS::POSTAGE_FIRST_CLASS] = 	"First Class Postage";
		$this->shipMethodsHash[ShippingCarrier_USPS::POSTAGE_PRIORITY] = 		"Priority Postage";
		$this->shipMethodsHash[ShippingCarrier_USPS::POSTAGE_UNKNOWN] = 		"Unknown Postage Type";
		
		$this->shippingCodesArr = array_keys($this->shipMethodsHash);
		
		
		// We are going to steal functionality from the United Parcel Service in order to help us with the Post Office tranist times, etc.
		$this->shippingCarrierUPS = new ShippingCarrier_UPS();
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
		
		if($shippignMethodCode == ShippingCarrier_USPS::POSTAGE_FIRST_CLASS)
			return "F";
		else if($shippignMethodCode == ShippingCarrier_USPS::POSTAGE_PRIORITY)
			return "O";
		else if($shippignMethodCode == ShippingCarrier_USPS::POSTAGE_UNKNOWN)
			return "R";
		else 
			return $this->shipMethodsHash[$shippignMethodCode];
		
	}
	
	// Pass in shipping method description used by the shipping carrier, it will convert it back to our internal shipping method code.
	function getShippingMethodCodeFromCarrierReference($shippingCarrierName, $shippingMethodCarrierReference){
		
		if($shippingCarrierName != ShippingMethods::CARRIER_CODE_POSTOFFICE)
			throw new Exception("Error in getShippingMethodCodeFromCarrierReference. The Shipping Carrier is invalid for this interface: $shippingCarrierName");
		
		
		if($shippingMethodCarrierReference == "F")
			return ShippingCarrier_USPS::POSTAGE_FIRST_CLASS;
		else if($shippingMethodCarrierReference == "O")
			return ShippingCarrier_USPS::POSTAGE_PRIORITY;
		else if($shippingMethodCarrierReference == "R")
			return ShippingCarrier_USPS::POSTAGE_UNKNOWN;
		else 
			return ShippingCarrier_USPS::POSTAGE_UNKNOWN;
		
	}
	

	// Returns a Transit Time Object for all of the shipping methods and arrival times that are available to the destination.
	// We are going to judge how long a post office method takes based upon how long UPS takes.  
	function getTransitTimes(MailingAddress $shipFromAddressObj, MailingAddress $shipToAddressObj) {
	
		if(!$this->isAddressValid($shipFromAddressObj) || !$this->isAddressValid($shipToAddressObj))
			throw new Exception("Error in method ShippingCarrier_USPS::getTransitTimes. The address must be intialized before calling this method.");
		
		$this->fillTranistTimesObjectIfNotSet($shipFromAddressObj, $shipToAddressObj);
			
		return $this->transitTimesObjArr[$this->getShipFromAndToAddressSignature($shipFromAddressObj, $shipToAddressObj)];
		
	}
	
	private function fillTranistTimesObjectIfNotSet(MailingAddress $shipFromAddressObj, MailingAddress $shipToAddressObj){
		
		// In case this Object is reused for muliple Shipping Addresses... we cache the Transit Times in an array
		// The key to the Array is a unique MD5 for the Address.
		if(isset($this->transitTimesObjArr[$this->getShipFromAndToAddressSignature($shipFromAddressObj, $shipToAddressObj)]))
			return;

		$transitTimesObj = new TransitTimes();
		
		// The Post office isn't available outside of the US.
		// This method will return without populating any USPS choices.
		if($shipToAddressObj->getCountryCode() != "US"){
			$this->transitTimesObjArr[$this->getShipFromAndToAddressSignature($shipFromAddressObj, $shipToAddressObj)] = $transitTimesObj;
			return;
		}
			
		// If we are shipping from our California Plant, then just esimate how long it will take to ship to Hawaii or Alaska
		// For Hawaii an Alaska, just estimate shipment times since Ground Shipping isn't available.
		if($shipFromAddressObj->getCountryCode() == "US" && $shipFromAddressObj->getState() == "CA" && in_array($shipToAddressObj->getState(), array("HI", "AK"))){
			
			$firstClassDaysInTranist = 7;
			$priorityDaysInTranist = 4;
		}
		else{
			
			// Find out how long UPS takes for Ground Shipping, that will give us an idea how far away the destination is, or how difficult it is to reach.
			$transitTimesFromUPS = $this->shippingCarrierUPS->getTransitTimes($shipFromAddressObj, $shipToAddressObj);
		
			$daysInTransitWithUPSground = $transitTimesFromUPS->getTransitDays(ShippingCarrier_UPS::GROUND);
	
			if($daysInTransitWithUPSground >= 5){
				$firstClassDaysInTranist = 8;
				$priorityDaysInTranist = 3;
			}
			else if($daysInTransitWithUPSground >= 4){
				$firstClassDaysInTranist = 7;
				$priorityDaysInTranist = 3;
			}
			else if($daysInTransitWithUPSground >= 3){
				$firstClassDaysInTranist = 6;
				$priorityDaysInTranist = 2;
			}
			else if($daysInTransitWithUPSground >= 2){
				$firstClassDaysInTranist = 4;
				$priorityDaysInTranist = 2;
			}
			else if($daysInTransitWithUPSground >= 1){
				$firstClassDaysInTranist = 3;
				$priorityDaysInTranist = 1;
			}
			else{
				$firstClassDaysInTranist = 10;
				$priorityDaysInTranist = 10;
			}
		}
			
		
		// Make it harder to downgrade First Class packages during the month of December when the post office will be very busy.
		if(date("n") == "12"){
			$firstClassDaysInTranist += 3;
			
			if($priorityDaysInTranist < 3)
				$priorityDaysInTranist++;
		}


		$transitTimesObj->addShippingMethod(self::POSTAGE_FIRST_CLASS, 0, 0, $firstClassDaysInTranist);
		$transitTimesObj->addShippingMethod(self::POSTAGE_PRIORITY, 0, 0, $priorityDaysInTranist);
		

		// Add the Transit Times to our cache.
		$this->transitTimesObjArr[$this->getShipFromAndToAddressSignature($shipFromAddressObj, $shipToAddressObj)] = $transitTimesObj;
	}
	
	// Returns a unique digestive hash relating to both the ShipFrom and ShipTo Addresses.
	private function getShipFromAndToAddressSignature(MailingAddress $shipFromAddressObj, MailingAddress $shipToAddressObj){
		return md5($shipFromAddressObj->toString() . $shipToAddressObj->toString());
	}
	
	
	// Basically First Class and Priority is available everyone in the U.S.
	public function getAllShippingMethodsPossible(MailingAddress $shipFromAddressObj, MailingAddress $shipToAddressObj){
		
		if($shipToAddressObj->getCountryCode() == "US" || $shipToAddressObj->getCountryCode() == "PR"){
			
			return array(self::POSTAGE_FIRST_CLASS, self::POSTAGE_PRIORITY);
		}

		
		return array();
	}
	
	// Piggy back on UPS adddress verification system.
	function getAddressSuggestions() {	
		return $this->shippingCarrierUPS->getAddressSuggestions();
	
	}
	
	// Piggy back on UPS adddress verification system.
	function isAddressValid(MailingAddress $addressObj) {
		return $this->shippingCarrierUPS->isAddressValid($addressObj);

	}
	
	// Even through the Post Office still delivers on Saturdays, they don't really consider it a transit day because the sorting houses aren't working.
	function isSaturdayDelivery($shippingCode) {

		return false;
	}

	// One of the Post Office ideals is to provide mail to everyone in the U.S., no matter how rurual... and to provide it for the same cost.
	function isDestinationExtended(MailingAddress $shipFromAddressObj, MailingAddress $shipToAddressObj) {

		return false;
	}
	
	function isDestinationRural(MailingAddress $addressObj) {
	
		return false;

	}
	

	
}

?>

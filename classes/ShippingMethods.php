<?php

// This class implements all of the same methods that the "ShippingMethodsInterface" classes does.
// But the ShippingMethods class aggregates multiple ShippingCarrier (based upon domain privelages) and adds some methods tying them together.
// Read comments on ShippingMethodsInterface.php

// Address Validation:
// There is alwasy at least 1 primary carrier for every domain.
// If there is an Address error on the primary carrier then the system must validate the address before continuing.
// If the primary carrier passes, but subsequent Carriers fail, then those carriers's shippiment methods will be elimiated from the possibilites.
// If multiple Shipping Carriers pass then all shipping methods will be included as a possibility to that Shipping Address.
class ShippingMethods implements ShippingMethodsInterface {
	
	private $shippingCarriersObjectsArr = array();
	private $primaryShippingCarrier;
	private $shipMethodsHash = array();
	private $addressIsValidFlag;
	
	const CARRIER_CODE_UPS = "UPS";
	const CARRIER_CODE_POSTOFFICE = "USPS";
	
	
	
	// Contructor contacts all of the Shipping Interfaces and builds a list of every single ShippingMethod in the system
	// It will complain if there are 2 identical ShippingCodes used by 2 separate Carriers.
	// Pass in a Domain name ID if you want the shipping methods to be based off of carriers for that domain..
	// By default leave blank to get "oneDomain" out of the URL or the Selected List of domains.
	// Pass in "0" zero if you want to load all Carriers.
	function __construct($domainID = NULL){
		
		$domainObj = Domain::singleton();
		
		if(!empty($domainID)){
			if(!Domain::checkIfDomainIDexists($domainID))
				throw new Exception("Error in Shipping Methods contructor with DomainID.");
			$domainID = $domainID;
			
			$carrierNames = $domainObj->getShippingCarriersForDomain($domainID);
		}
		else if($domainID === NULL){
			$domainID = Domain::oneDomain();
			
			$carrierNames = $domainObj->getShippingCarriersForDomain($domainID);
		}
		else{
			// If the Domain is "Zero" then load all carriers.
			$carrierNames = array(self::CARRIER_CODE_UPS, self::CARRIER_CODE_POSTOFFICE);
		}
	
		
		
		
		if(empty($carrierNames))
			throw new Exception("Error in ShippingMethods constructor. A shipping carrier was not found.");
		
		$this->primaryShippingCarrier = $carrierNames[0];
		
		// Use this to check that there are not overlapping methods between carriers.
		$overlappingMethodsCheckArr = array();
		
		// Create Shipping Carrier objects for each of our carrier names.
		foreach ($carrierNames as $thisCarrierName){
			
			if($thisCarrierName == self::CARRIER_CODE_UPS)
				$this->shippingCarriersObjectsArr[$thisCarrierName] = new ShippingCarrier_UPS();
			else if($thisCarrierName == self::CARRIER_CODE_POSTOFFICE)
				$this->shippingCarriersObjectsArr[$thisCarrierName] = new ShippingCarrier_USPS();
			else 
				throw new Exception("Error in ShippingMethods. The carrier name is not recognized.");
				
			$shippingCodesForCarrier = array_keys($this->shippingCarriersObjectsArr[$thisCarrierName]->getShippingMethodsHash());
			$overlappingMethodsCheckArr = array_merge($overlappingMethodsCheckArr, $shippingCodesForCarrier);
			
			$this->shipMethodsHash = array_merge($this->shipMethodsHash, $this->shippingCarriersObjectsArr[$thisCarrierName]->getShippingMethodsHash());
		}
		
		if(sizeof($overlappingMethodsCheckArr) != sizeof(array_unique($overlappingMethodsCheckArr)))
			throw new Exception("Error in ShippingMethod contructor. There are shipping codes matching across multiple carriers.");
	}
	
	// Returns true or false depending on whether the shipping code exists.
	function doesShippingCodeExist($shippignMethodCode){
		
		$carrierCode = $this->getShippingCarrierFromShippingCode($shippignMethodCode);
		
		if(empty($carrierCode))
			return false;
		else
			return true;
	}
	
	// Returns all shipping methods for all carriers... whether or not they are possible to be sent to a particular destination.
	function getShippingMethodsHash(){
		
		$returnHash = array();
		
		foreach($this->shippingCarriersObjectsArr as $thisShippingCarrierObj)
			$returnHash = array_merge($returnHash, $thisShippingCarrierObj->getShippingMethodsHash());
		
		return $returnHash;
	}
	
	// Returns the Shipping Method Name associated with the Shipping Code.  Fails with error if it does not exist.
	// Pass in the second parameter if you want to include the Carrier Code prefiexed before the Shipping Method with a colon
	function getShippingMethodName($shippingMethodCode, $prefixWithCarrier = false){
		
		$allShippingMethodsHash = $this->getShippingMethodsHash();
		
		if(!array_key_exists($shippingMethodCode, $allShippingMethodsHash))
			throw new Exception("Error in method getShippingMethodName. The Code does not exist.");
			
		if($prefixWithCarrier)
			return $this->getShippingCarrierFromShippingCode($shippingMethodCode) . ": " . $allShippingMethodsHash[$shippingMethodCode];
		else
			return $allShippingMethodsHash[$shippingMethodCode];
	}
	
	// Returns a String like UPS or USPS
	// Returns NULL if it can't find a match.
	function getShippingCarrierFromShippingCode($shippingCode){
		
		foreach($this->shippingCarriersObjectsArr as $thisCarrierCode => $shippingCarrierObj){
			
			if(in_array($shippingCode, array_keys($shippingCarrierObj->getShippingMethodsHash())))
				return $thisCarrierCode;
		}
		
		return NULL;
	}
	
	// Returns the Name (or Code) that hte Shipping Carrier uses for the ODBC Database or API call.  
	// The customer and Admins will not see this code.
	function getCarrierReference($shippignMethodCode){
		
		if(!$this->doesShippingCodeExist($shippignMethodCode))
			throw new Exception("Error in ShippingMethods->getCarrierReference. The ShippingCode was not found.");
			
		$shippingCarrierName = $this->getShippingCarrierFromShippingCode($shippignMethodCode);
		
		if(!isset($this->shippingCarriersObjectsArr[$shippingCarrierName]))
			throw new Exception("Error in method getCarrierReference");
		
		return $this->shippingCarriersObjectsArr[$shippingCarrierName]->getCarrierReference($shippignMethodCode);
	}
	
	// Pass in shipping method description used by the shipping carrier, it will convert it back to our internal shipping method code.
	function getShippingMethodCodeFromCarrierReference($shippingCarrierName, $shippingMethodCarrierReference){
		
		if(!isset($this->shippingCarriersObjectsArr[$shippingCarrierName]))
			throw new Exception("Error in method getShippingMethodCodeFromCarrierReference. The Shipping Carrier has not been defined: $shippingCarrierName");
		
		return $this->shippingCarriersObjectsArr[$shippingCarrierName]->getShippingMethodCodeFromCarrierReference($shippingCarrierName, $shippingMethodCarrierReference);	
	}
	
	// We only check if our primary carrier finds the address valid.
	function isAddressValid(MailingAddress $addressObj) {
		
		if(!$addressObj->addressInitialized())
			throw new Exception("Error in ShippingMethods->isAddressValid.  The address has not been initialized.");
			
		$this->addressIsValidFlag = $this->shippingCarriersObjectsArr[$this->primaryShippingCarrier]->isAddressValid($addressObj);
		return $this->addressIsValidFlag;
	}

	
	// Returns a Tranist Times object (Including Saturdays, if possible).
	// Uses all Shipping Methods this this domain (for all Shipping Carriers), if the address is valid for the carriers.
	// Read comments on Interface definition.
	function getTransitTimes(MailingAddress $shipFromAddressObj, MailingAddress $shipToAddressObj) {
		
		if(!$shipFromAddressObj->addressInitialized() || !$shipToAddressObj->addressInitialized())
			throw new Exception("Error in ShippingMethods->getTransitTimes. The address hasn't been initialized");
		
		if(!$this->shippingCarriersObjectsArr[$this->primaryShippingCarrier]->isAddressValid($shipFromAddressObj))
			throw new ExceptionInvalidShippingAddress("Error in getTransitTimes. The Ship From address must be valid with the primary carrier before calling this method.");
		if(!$this->shippingCarriersObjectsArr[$this->primaryShippingCarrier]->isAddressValid($shipToAddressObj))
			throw new ExceptionInvalidShippingAddress("Error in getTransitTimes. The Ship To address must be valid with the primary carrier before calling this method.");

		// Start adding to our TransitTimes blank object.
		$transiTimesObj = new TransitTimes();
		foreach($this->shippingCarriersObjectsArr as $thisShippingCarrierObj){
			
			// If the Address is not valid for a Shipping Carrier, then we can't use their shipping methods.
			if(!$thisShippingCarrierObj->isAddressValid($shipToAddressObj))
				continue;
			
			$transiTimesObj->mergeTransitTimesObject($thisShippingCarrierObj->getTransitTimes($shipFromAddressObj, $shipToAddressObj));
		}
		
		return $transiTimesObj;
	}
	
	// Returns an array of Shipping Codes
	// Read comments on Interface definition.
	function getAllShippingMethodsPossible(MailingAddress $shipFromAddressObj, MailingAddress $shipToAddressObj){

		if(!$shipFromAddressObj->addressInitialized() || !$shipToAddressObj->addressInitialized())
			throw new Exception("Error in ShippingMethods->getAllShippingMethodsPossible. The address hasn't been initialized");
		
		if(!$this->shippingCarriersObjectsArr[$this->primaryShippingCarrier]->isAddressValid($shipToAddressObj)){
			throw new ExceptionInvalidShippingAddress("Error in method getAllShippingMethodsPossible. The address must be valid with the primary shipping carrier before calling this method.");
		}
			
		$returnArr = array();
		
		foreach($this->shippingCarriersObjectsArr as $thisShippingCarrierObj){
			
			// If the Address is not valid for a Shipping Carrier, then we can't use their shipping methods.
			if(!$thisShippingCarrierObj->isAddressValid($shipToAddressObj))
				continue;
			
			$returnArr = array_merge($returnArr, $thisShippingCarrierObj->getAllShippingMethodsPossible($shipFromAddressObj, $shipToAddressObj));
		}
		
		return $returnArr;
	}
	
	function getAddressSuggestions() {
		
		if($this->addressIsValidFlag)
			throw new Exception("Error in method ShippingMethods::getAddressSuggestions. The address has to be invalid before calling this method.");
		
		return $this->shippingCarriersObjectsArr[$this->primaryShippingCarrier]->getAddressSuggestions();
	}
	
	
	// Returns true if the Primary Carrier finds the address to be in a remote area.
	function isDestinationRural(MailingAddress $addressObj) {
		
		if(!$addressObj->addressInitialized())
			throw new Exception("Error in method ShippingMethods->isDestinationRural(). The address has not been initialized.");
		
		return $this->shippingCarriersObjectsArr[$this->primaryShippingCarrier]->isDestinationRural($addressObj);
	}
	
	// Returns true if the Primary Carrier finds the address to be in an extended area, that we may want to charge more for.
	function isDestinationExtended(MailingAddress $shipFromAddressObj, MailingAddress $shipToAddressObj) {

		if(!$shipFromAddressObj->addressInitialized() || !$shipToAddressObj->addressInitialized())
			throw new Exception("Error in method ShippingMethods->isDestinationExtended(). One of the addresses have not been initialized.");
		
		return $this->shippingCarriersObjectsArr[$this->primaryShippingCarrier]->isDestinationExtended($shipFromAddressObj, $shipToAddressObj);
	}
	
	function isSaturdayDelivery($shippingCode) {

		$shippingCarrierCode = $this->getShippingCarrierFromShippingCode($shippingCode);
		
		// Just in case the class for the Shipping Carrier was changed after the database was saved.
		if(empty($shippingCarrierCode))
			return false;
			
		return $this->shippingCarriersObjectsArr[$shippingCarrierCode]->isSaturdayDelivery($shippingCode);
	
	}
	
	
	
	

}

?>

<?php

class MailingAddress {

	private $attention;
	private $companyName;
	private $addressOne;
	private $addressTwo;
	private $city;
	private $state;
	private $zipCode;
	private $countryCode;
	private $isResidentialFlag;
	private $phoneNumber;
	

	function __construct($attention, $companyName, $address1, $address2, $city, $state, $zipCode, $countryCode, $isResidentialFlag=true, $phoneNumber){
		
		$this->setCountryCode($countryCode);
		$this->setZipCode($zipCode);
		$this->setAttention($attention);
		$this->setCompanyName($companyName);
		$this->setAddressOne($address1);
		$this->setAddressTwo($address2);
		$this->setCity($city);
		$this->setState($state);
		$this->setResidentialFlag($isResidentialFlag);
		$this->setPhoneNumber($phoneNumber);
	}
	
		
	// Retursn True or False 
	// Makes sure thar the ContryCode, ZipCode, State, City, and Address are set/valid
	// Address2, and Attention are optional.
	public function addressInitialized(){
		
		if(empty($this->addressOne) || empty($this->city) || empty($this->state) || empty($this->zipCode) || empty($this->countryCode))
			return false;
		else
			return true;
	}
	

	// Returns the MD5 of the City, State, Zip, and Country
	public function getSignatureOfStateCityAndZip(){

			if(!$this->addressInitialized())
				throw new Exception("Error in method getSignatureOfCityAndZip.  The address has not been initialized yet.");
				
			return md5($this->city . $this->state. $this->zipCode . $this->countryCode);
	}
	
	public function getSignatureOfFullAddress(){

			if(!$this->addressInitialized())
				throw new Exception("Error in method getSignatureAddress.  The address has not been initialized yet.");
				
			return md5($this->city . $this->state . $this->zipCode . $this->countryCode . $this->addressOne . $this->addressTwo . $this->attention . $this->companyName . $this->phoneNumber . ($this->isResidentialFlag ? "true" : "false"));
	}
	
	
	public function getAddressOne() {
		return $this->addressOne;
	}
	
	public function getAddressTwo() {
		return $this->addressTwo;
	}
	
	public function getAttention() {
		return $this->attention;
	}
	
	public function getCompanyName(){
		return $this->companyName;
	}
	
	public function getCity() {
		return $this->city;
	}
	
	public function getCountryCode() {
		return $this->countryCode;
	}
	

	public function getState() {
		return $this->state;
	}

	public function getZipCode() {
		return $this->zipCode;
	}
	
	public function isResidential() {
		return $this->isResidentialFlag;
	}
	public function getPhoneNumber(){
		return $this->phoneNumber;
	}
	
	public function setAddressOne($addressOne) {
		$this->addressOne = trim($addressOne);
	}
	
	public function setAddressTwo($addressTwo) {
		$this->addressTwo = trim($addressTwo);
	}
	
	public function setAttention($attention) {
		$this->attention = trim($attention);
	}
	public function setCompanyName($companyName) {
		$this->companyName = trim($companyName);
	}
	public function setPhoneNumber($phoneNumber) {
		$this->phoneNumber = trim($phoneNumber);
	}
	public function setCity($city) {
		$this->city = trim($city);
	}
	
	public function setCountryCode($countryCode) {
		$countryCode = trim($countryCode);

		if(strlen($countryCode) != 2)
			$countryCode = "US";
		
		$this->countryCode = $countryCode;
	}
	
	public function setState($state) {
		$this->state = trim($state);
	}
	
	public function setZipCode($zipCode) {
		
		if(empty($this->countryCode))
			throw new Exception("Error Setting ZipCode. The Country Code must be set first.");
		
		$zipCode = trim($zipCode);
		
			
		if($this->countryCode == "US" && strlen($zipCode) > 5){
			$zipCode = preg_replace("/[^\d]/", "", $zipCode);
			$zipCode = substr($zipCode, 0, 5);
		}
		
		$this->zipCode = $zipCode;
	}
	
	public function setResidentialFlag($booleanFlag) {
		
		if(!is_bool($booleanFlag))
			throw new Exception("Error setting setResidentialFlag. It must be boolean.");
		
		$this->isResidentialFlag = $booleanFlag;
	}
	
	// Returns Address with new line characters
	public function toString() {
		
		if(!$this->addressInitialized())
			throw new Exception("Can not convert MailingAddress to a string because it hasn't been initialized yet.");
		
		$retStr = "";
		
		if(!empty($this->companyName))
			$retStr .= $this->companyName . "\n" . "Attention: " . $this->attention . "\n";
		else
			$retStr .= $this->attention . "\n";
		
		$retStr .= $this->addressOne . "\n";
		
		if(!empty($this->addressTwo))
			$retStr .= $this->addressTwo . "\n";
		
		$retStr .= $this->city . ", " . $this->state . " " . $this->zipCode . "\n" . $this->countryCode;
		
		return $retStr;
		
	}

}


// In case there is an Address Verification Error.
// This class will contain information suggesting a correct address.
class MailingAddressSuggestion {

	public $city;
	public $state;
	public $postalHigh;
	public $postalLow;

	function MailingAddressSuggestion($city, $state, $postalLow, $postalHigh){
		$this->city = $city;
		$this->state = $state;
		$this->postalHigh = $postalHigh;
		$this->postalLow = $postalLow;
	}
}

?>

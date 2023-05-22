<?php

class DomainAddresses {

	// This might not be the best place to store UPS account into... but it was too small to make another class for.
	public $upsAccountNumber;
	public $taxIDnumber;
	public $taxIDType;
		
	private $domainID;
	
	const UPSACCNO		= "UPSAccountNo";
	const TAXIDNO		= "TaxIDNo";
	const TAXIDTYPE		= "TaxIDType";
	
	const ADRTYPECUS	= "CustomerService";
	const ADRTYPESHI	= "ShippingDept";
	const ADRTYPEBIL	= "BillingDept";
	const ADRTYPEDEF	= "DefaultProduction";
		
	private static $addressTypeArr = array(self::ADRTYPECUS, self::ADRTYPESHI,self::ADRTYPEBIL,self::ADRTYPEDEF);
	
	private static $addressFieldArr = array("Attention","Company","AddressOne","AddressTwo","City","ZIP","State","CountryCode","Phone","ResidentialFlag");
		
	public function __construct($domainID){
		
		if(!Domain::checkIfDomainIDexists($domainID))
			throw new Exception("Error in Domain Address Configuration. The Domain ID does not exist.");

		$this->dbCmd = new DbCmd();
				
		$this->domainID = $domainID;	
		
		if($this->isMissing()) 
			$this->addNewDomainAddress();
		
		$this->upsAccountNumber = $this->loadNonAddress(self::UPSACCNO);
		$this->taxIDnumber 		= $this->loadNonAddress(self::TAXIDNO);
		$this->taxIDType 		= $this->loadNonAddress(self::TAXIDTYPE);
	}
	
	private function addNewDomainAddress() {
		
		$this->dbCmd->Query( "SELECT ID FROM domainaddresses WHERE Type='NonAddress' AND DomainID = $this->domainID");
		if($this->dbCmd->GetNumRows() == 0) {
				
			$insertValue["Type"] = "NonAddress";
			$insertValue["DomainID"] = $this->domainID;	
			$this->dbCmd->InsertQuery("domainaddresses", $insertValue);	
		}
				
		foreach (self::$addressTypeArr as $addressType) {
			
			$this->dbCmd->Query( "SELECT ID FROM domainaddresses WHERE AddressType='$addressType' AND Type='Address' AND DomainID = $this->domainID");
			if($this->dbCmd->GetNumRows() > 0)
				continue;
			
			$insertValue["Type"] 	  	 = "Address";
			$insertValue["DomainID"]  	 = $this->domainID;
			$insertValue["AddressType"]  = $addressType;
			
			$insertValue["CountryCode"]  = "US";
			$insertValue["ResidentialFlag"]  = 0;
			
			$this->dbCmd->InsertQuery("domainaddresses", $insertValue);
		}
	}
	
	private function loadAddress($addressType) {
		
		self::verifyType($addressType);
	
		$this->dbCmd->Query( "SELECT * FROM domainaddresses WHERE Type='Address' AND AddressType='".DbCmd::EscapeLikeQuery($addressType)."' AND DomainID = $this->domainID");
		$row = $this->dbCmd->GetRow();
		
		if(intval($row["ResidentialFlag"]) == 0)
			$residentialFlag = false;
		else 
			$residentialFlag = true;

		// Prevent a failure with the Mailing Address object if there are no values yet.
		if(empty($row["CountryCode"]))
			$countrCode = "US";
		else 
			$countrCode = $row["CountryCode"];
			
		if(empty($row["ZIP"]))
			$zipCode = "11220";
		else 
			$zipCode = $row["ZIP"];

		if(empty($row["State"]))
			$state = "NY";
		else 
			$state = $row["State"];
			
		if(empty($row["City"]))
			$city = "Brooklyn";
		else 
			$city = $row["City"];
			
		if(empty($row["AddressOne"]))
			$address1 = "Not defined";
		else 
			$address1 = $row["AddressOne"];
	
		$addressObj = new MailingAddress($row["Attention"], $row["Company"],  $address1,  $row["AddressTwo"],  $city,  $state, $zipCode,  $countrCode, $residentialFlag, $row["Phone"]);
		
		return $addressObj;
	}

	private function loadNonAddress($field) {
		
		$this->dbCmd->Query( "SELECT " . $field . " FROM domainaddresses WHERE Type='NonAddress' AND DomainID = $this->domainID");
		return $this->dbCmd->GetValue();
	}
	
	private function verifyType($type) {
		
		if(!in_array($type,self::$addressTypeArr))
			throw new Exception("Illegal type $type");
	}
	
	private function verifyAddressField($addressField) {
		
		if(!in_array($addressField,self::$addressFieldArr))
			throw new Exception("Illegal address field $addressField");
	}
	
	function updateFieldOfType($field,$value,$type) {
		
		self::verifyType($type);
		self::verifyAddressField($field);
		
		$this->dbCmd->Query( "update domainaddresses set " . DbCmd::EscapeSQL($field) . "='" . DbCmd::EscapeSQL($value) . "' WHERE DomainID=$this->domainID AND AddressType ='".$type."'");
	}
	
	function updateResidentialFlag($state,$type) {
	
		self::verifyType($type);
		if($state)
			$this->dbCmd->Query( "update domainaddresses set ResidentialFlag=1 WHERE DomainID=$this->domainID AND AddressType ='".$type."'" );
		else 
			$this->dbCmd->Query( "update domainaddresses set ResidentialFlag=0 WHERE DomainID=$this->domainID AND AddressType ='".$type."'" );
	}
	
	public function updateUPSAccountNo($value) {
		$this->dbCmd->Query( "update domainaddresses set UPSAccountNo='" . DbCmd::EscapeSQL($value) . "' WHERE DomainID=$this->domainID AND Type ='NonAddress'" );
	}

	public function updateTaxIdNo($value) {
		$this->dbCmd->Query( "update domainaddresses set TaxIDNo='" . DbCmd::EscapeSQL($value) . "' WHERE DomainID=$this->domainID AND Type ='NonAddress'" );
	}

	public function updateTaxIdType($value) {
		$this->dbCmd->Query( "update domainaddresses set TaxIDType='" . DbCmd::EscapeSQL($value) . "' WHERE DomainID=$this->domainID AND Type ='NonAddress'" );
	}
	
	public function getUPSAccountNo(){	
		return $this->upsAccountNumber;
	}
	
	public function getTaxIDType(){	
		return $this->taxIDType ;
	}
	
	public function getTaxIDNumber(){	
		return $this->taxIDnumber;
	}
	
	public function getMailingAddressOfType($addressType){
		self::verifyType($addressType);
		return $this->loadAddress($addressType);
	}
	
	/**
	 * @return MailingAddress
	 */
	public function getCustomerServiceAddressObj(){
		return $this->loadAddress(self::ADRTYPECUS);
	}
	
	/**
	 * @return MailingAddress
	 */
	public function getReturnShippingAddressObj(){
		return $this->loadAddress(self::ADRTYPESHI);
	}
	
	/**
	 * @return MailingAddress
	 */
	public function getBillingDepartmentAddressObj(){
		return $this->loadAddress(self::ADRTYPEBIL);
	}
	
	/**
	 * @return MailingAddress
	 */
	public function getDefaultProductionFacilityAddressObj(){
		return $this->loadAddress(self::ADRTYPEDEF);
	}
	
	public function getAddressTypes() {
		return self::$addressTypeArr;
	}
		
	public function isMissing() {
		
		$this->dbCmd->Query( "SELECT ID FROM domainaddresses WHERE DomainID = $this->domainID");
		
		if($this->dbCmd->GetNumRows() != (sizeof(self::$addressTypeArr)+1) )
			return true;
		else 	
			return false;
	}
	
}

?>
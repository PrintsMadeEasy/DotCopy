<?php

class OfflineMarketingUrl {
	
	private $smallestStringLength;
	private $largestStringLength;
	private $maxIncorrectAccessAttempt;
	private $dbCmd;
	
	function __construct() {
	
		$this->smallestStringLength = 6;
		$this->largestStringLength = 30;
		$this->maxIncorrectAccessAttempt = 10;
		$this->dbCmd = new DbCmd();
	}
	
	// Pass in a company name and it will return a searchable string.
	// It will replace spaces with dashes and remove any non-alpha numeric characters
	// Returns the first 30 characters
	function getCompanyNameSearch($companyName){
		
		$companyName = trim($companyName);
		
		$companyName = preg_replace('/\s+/', '_', $companyName); // Convert 1 or more spaces into a single underscore.
		$companyName = preg_replace('/[^A-Za-z0-9\_]/', '', $companyName); // non alpha-numeric characters
		$companyName = preg_replace('/\_/', '-', $companyName); // Convert underscores to dashes
		$companyName = preg_replace('/\-+/', '-', $companyName); // Convert more than 1 dash into a single dash
		$companyName = substr($companyName, 0, $this->largestStringLength);
		
		return $companyName;
	}
	
	
	// This is meant to be called before a record is inserted into a DB.
	// It will return the best unique string possible
	// It will start with the smallest string length... but in case it doesn't have a good cardinality, it will keep advancing until it is unique.
	// Eventually, it may return the whole company name (and there could be multiple matches).  If by some chance, 2 companies have the same company name within 3 months, who cares.
	function getUniqueLocatorString($companyName){
		
		$companyNameFullSearch = $this->getCompanyNameSearch($companyName);
		
		$currentStrLength = $this->smallestStringLength;
		while(true){
			
			$companyNameShortSearch = substr($companyNameFullSearch, 0, $currentStrLength);
			
			// Prevent the company search from stopping on a Dash (where there is a space)
			if(substr($companyNameShortSearch, -1, 1) == "-"){
				$currentStrLength++;
				$companyNameShortSearch = substr($companyNameFullSearch, 0, $currentStrLength);
			}
			
			$this->dbCmd->Query("SELECT COUNT(*) FROM merchantmailers WHERE CompanySearch LIKE \"" . DbCmd::EscapeLikeQuery($companyNameShortSearch) . "\"");
			$conflictCount = $this->dbCmd->GetValue();
			
			if($conflictCount == 0)
				break;
				
			if(strlen($companyNameShortSearch) == strlen($companyNameFullSearch))
				break;
			
			$currentStrLength++;
		}
		
		return $companyNameShortSearch;
	}
	
	
	function checkIfIpAddressHasPermission(){
		
		$this->dbCmd->Query("SELECT COUNT(*) FROM ipaddresswrongaccess WHERE IPaddress LIKE '" . DbCmd::EscapeLikeQuery(WebUtil::getRemoteAddressIp()) . "' 
							AND AccessType='OffMk' AND Date > DATE_ADD(NOW(), INTERVAL -3 DAY )");
		$wrongAccessCount = $this->dbCmd->GetValue();
		
		if($wrongAccessCount > $this->maxIncorrectAccessAttempt)
			return false;
			
		return true;
	}
	
	function addIncorrectIpAccess(){
		$this->dbCmd->InsertQuery("ipaddresswrongaccess", array("AccessType"=>'OffMk', "Date"=>time(), "IPaddress"=>WebUtil::getRemoteAddressIp()));
	}

	// Returns false if it doesn't have a match
	// Returns a Mailing Address object if does have a match
	/*
	 * @return MailingAddress
	 */
	function getAddressObjFromLocatorString($companySearch){
		
		if(empty($companySearch))
			return false;
		
		$this->dbCmd->Query("SELECT * FROM merchantmailers WHERE CompanySearch LIKE '" . DbCmd::EscapeLikeQuery($companySearch) . "' ORDER BY DateAdded DESC");
		if($this->dbCmd->GetNumRows() == 0)
			return false;
			
		$row = $this->dbCmd->GetRow();

		$mailingAddressObj = new MailingAddress($row["Attention"], $row["Company"], $row["Address1"], $row["Address2"], $row["City"], $row["State"], $row["Zip"], "US", true, "");
		return $mailingAddressObj;
	}
	
	// Return false if the company search doesn't have a match
	// Otherwise returns a string for the Industry Category name
	function getIndustryName($companySearch){
		
		if(empty($companySearch))
			return false;
		
		$this->dbCmd->Query("SELECT IndustryName FROM merchantmailers WHERE CompanySearch LIKE '" . DbCmd::EscapeLikeQuery($companySearch) . "' ORDER BY DateAdded DESC");
		if($this->dbCmd->GetNumRows() == 0)
			return false;
		
		return $this->dbCmd->GetValue();
	}
	

}

?>
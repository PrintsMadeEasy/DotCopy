<?php


class DomainEmails {

	public $marketingDataAttachmentPrefix;
	public $marketingDataUserIDofSavedProjects;
	public $marketingDataSavedProjectsPrefix;
	
	private $domainKey;
	private $domainID;
	
	const CUSTSERV		= "CustomerService";
	const WEBMASTER		= "Webmaster";
	const SHIPNOTI		= "ShipmentNotify";
	const EMAILNOTI		= "EmailNotify";
	const ARTWORK		= "ArtWork";
	const REMINDER		= "Reminder";
	const SALES			= "Sales";
	const CONFIRM		= "Confirmation";
	const PAYPAL		= "Paypal";
	const MARKETING		= "Marketing";
	const RETENV		= "ReturnEnvelope";
		
	private static $emailTypeArr = array(self::CUSTSERV, self::WEBMASTER, self::SHIPNOTI,self::EMAILNOTI,self::ARTWORK,self::REMINDER,self::SALES,self::CONFIRM,self::PAYPAL,self::MARKETING);
	
	public function __construct($domainID){
		
		if(!Domain::checkIfDomainIDexists($domainID))
			throw new Exception("Error in Domain Email Configuration. The Domain ID does not exist.");
			
		$this->dbCmd = new DbCmd();
		$this->domainKey = Domain::getDomainKeyFromID($domainID);
		$this->domainID  = $domainID;
	
		if(!$this->isEditable())
			$this->addNewDomain();
		
		if(in_array($this->domainKey, array("PrintsMadeEasy.com", "MarketingEngine.biz", "DotCopyShop.com", "Bang4BuckPrinting.com"))){
			
			// This part is only used in: server_getMaretingData.php
			$this->marketingDataAttachmentPrefix = "DotGraphics"; // Only imports attachments with the matching prefix.
			$this->marketingDataUserIDofSavedProjects = 6;  // When the markting data is loaded... it looks at this UserID to determine what saved projects to search
			$this->marketingDataSavedProjectsPrefix = "merchant";  // Only looks for Saved Projects with this prefix... like "merchant_HairSalon" ... If it can't find a match then the system skips and lumps into a General Category.
			
		}

	}
	
	function addNewDomain() {
		
		$this->dbCmd->Query( "SELECT ID FROM domainemailconfig WHERE DomainID = $this->domainID");
		
		if($this->dbCmd->GetNumRows() > 0)
				throw new Exception("Error in addNewDomain. The Domain ID does already exist.");
	
		foreach (self::$emailTypeArr as $emailType) {
			
			$insertValue["EmailType"] = $emailType;
			$insertValue["DomainID"]  = $this->domainID;
			$insertValue["Name"]      = $emailType;
			$insertValue["Email"]     = strtolower($emailType."@".$this->domainKey);
			
			$this->dbCmd->InsertQuery("domainemailconfig", $insertValue);
		}
	}
	
	function addNewAccont() {
		
		$this->dbCmd->Query( "SELECT EmailType FROM domainemailconfig WHERE EmailType like 'EMAIL-%' AND DomainID = $this->domainID ORDER BY ID DESC LIMIT 1");
		
		$nextEmailId = 0;
		if($this->dbCmd->GetNumRows() > 0) 
			$nextEmailId = intval(substr($this->dbCmd->getValue(),6));
		
		$nextEmailId += 1;
	
		$insertValue["EmailType"] = "Email-" . $nextEmailId;
		$insertValue["DomainID"]  = $this->domainID;
		$insertValue["Email"]     = strtolower("@".$this->domainKey);
		
		$this->dbCmd->InsertQuery("domainemailconfig", $insertValue);
	}
	
	private function verifyType($type) {
		
		$arrayOK = false;
		
		if(in_array($type,self::$emailTypeArr))
			$arrayOK = true;
		
		if(substr(strtoupper($type),0,6)=="EMAIL-")	
			$arrayOK = true;
		
		if(!$arrayOK)	
			throw new Exception("Illegal Email type: $type");
	}
	
	function getEmailTypeByEmailAddress($emailAddress){
		
		$this->dbCmd->Query("SELECT EmailType FROM domainemailconfig WHERE Email LIKE '".DbCmd::EscapeLikeQuery($emailAddress)."' AND DomainID = " . $this->domainID);
		$emailType = $this->dbCmd->GetValue();
		$this->verifyType($emailType);
		return $emailType;
	}

	function getEmailIDOfType($type) {
		self::$this->verifyType($type);
		$this->dbCmd->Query( "SELECT ID FROM domainemailconfig WHERE EmailType='".DbCmd::EscapeSQL($type)."' AND DomainID = $this->domainID");
		return $this->dbCmd->GetValue();
	}
	function getEmailNameOfType($type) {
		self::$this->verifyType($type);
		$this->dbCmd->Query( "SELECT Name FROM domainemailconfig WHERE EmailType='".DbCmd::EscapeSQL($type)."' AND DomainID = $this->domainID");
		return $this->dbCmd->GetValue();
	}
	function getEmailAddressOfType($type) {
		self::verifyType($type);
		$this->dbCmd->Query( "SELECT Email FROM domainemailconfig WHERE EmailType='".DbCmd::EscapeSQL($type)."' AND DomainID = $this->domainID");
		return $this->dbCmd->GetValue();
	}
	function getHostOfType($type) {
		self::verifyType($type);
		$this->dbCmd->Query( "SELECT MailServerHost FROM domainemailconfig WHERE EmailType='".DbCmd::EscapeSQL($type)."' AND DomainID = $this->domainID");
		return $this->dbCmd->GetValue();
	}
	function getUserOfType($type) {
		self::verifyType($type);
		$this->dbCmd->Query( "SELECT MailServerUser FROM domainemailconfig WHERE EmailType='".DbCmd::EscapeSQL($type)."' AND DomainID = $this->domainID");
		return $this->dbCmd->GetValue();
	}
	function getPassOfType($type) {
		self::verifyType($type);
		$this->dbCmd->Query( "SELECT MailServerPassword FROM domainemailconfig WHERE EmailType='".DbCmd::EscapeSQL($type)."' AND DomainID = $this->domainID");
		return $this->dbCmd->GetValue();
	}
	function updateEmailNameOfType($name,$type) {
		self::verifyType($type);
		$this->dbCmd->Query( "update domainemailconfig set Name='".DbCmd::EscapeSQL($name)."' WHERE DomainID=$this->domainID AND EmailType ='".DbCmd::EscapeSQL($type)."'");
	}
	function updateEmailAddressOfType($email,$type) {
		self::verifyType($type);
		$this->dbCmd->Query( "update domainemailconfig set Email='".DbCmd::EscapeSQL($email)."' WHERE DomainID=$this->domainID AND EmailType ='".DbCmd::EscapeSQL($type)."'");
	}
	function updateHostOfType($host,$type) {
		self::verifyType($type);
		$this->dbCmd->Query( "update domainemailconfig set MailServerHost='".DbCmd::EscapeSQL($host)."' WHERE DomainID=$this->domainID AND EmailType ='".DbCmd::EscapeSQL($type)."'");
	}
	function updateUserOfType($user,$type) {
		self::verifyType($type);
		$this->dbCmd->Query( "update domainemailconfig set MailServerUser='".DbCmd::EscapeSQL($user)."' WHERE DomainID=$this->domainID AND EmailType ='".DbCmd::EscapeSQL($type)."'");
	}
	function updatePassOfType($pass,$type) {
		self::verifyType($type);
		$this->dbCmd->Query( "update domainemailconfig set MailServerPassword='".DbCmd::EscapeSQL($pass)."' WHERE DomainID=$this->domainID AND EmailType ='".DbCmd::EscapeSQL($type)."'");
	}
	
	function deleteEmailAccount($id) {
		
		$id = intval($id);
		if(empty($id))
			throw new Exception("Error in deleteEmailAccount. Unknown email Id.");

		$this->dbCmd->Query( "SELECT ID FROM domainemailconfig WHERE ID = $id AND EmailType like 'Email-%'");
		
		if($this->dbCmd->GetNumRows() > 0)
			$this->dbCmd->Query( "DELETE from domainemailconfig where DomainID=$this->domainID AND ID=$id");
		else 	
			throw new Exception("Error in deleteEmailAccount. Pre-defined email accounts can't be deleted.");
	}
	
	function getFieldTypes() {
		
		$this->dbCmd->Query( "SELECT EmailType FROM domainemailconfig WHERE EmailType like 'Email-%' AND DomainID = $this->domainID ORDER BY ID DESC");
		return array_merge($this->dbCmd->GetValueArr(), self::$emailTypeArr);	
	}
		
	function isEditable() {
		
		$this->dbCmd->Query( "SELECT ID FROM domainemailconfig WHERE DomainID = $this->domainID");
		
		if($this->dbCmd->GetNumRows() > 0)
			return true;
		else 	
			return false;
	}	
	
	function getUserPassCheckOfType($type) {
		
		$host = $this->getHostOfType($type);
		
		$result = "NOH";
		
		if(!empty($host)) {
			
			$mailObj = new Mail();	
			
			$username = $this->getUserOfType($type); 
			$userSplit = split("@", $username);
			if(sizeof($userSplit) == 2)
				$username = $userSplit[0];
			
			$password = $this->getPassOfType($type);
		
			if($mailObj->checkPop3Account($host, $username, $password))
				$result = "YES";
			else 
				$result = "NOT";
		}
		return $result;
	}
	
	function syncEmail($emailId) {
		
		$emailId = intval($emailId);
		
		$this->dbCmd->Query( "SELECT * FROM domainemailconfig WHERE ID = $emailId");
		$row = $this->dbCmd->GetRow();
		$email = $row["Email"];
		$password = $row["MailServerPassword"];
	
		if(empty($email))
			return;
		
		$emailSplit = split("@", $email);
		if(sizeof($emailSplit) != 2) 
			throw new Exception("Error in syncEmail. Invalid Email.");
	
		$user   = $emailSplit[0];
		$domain = $emailSplit[1];
		
		if(!empty($user)) {
		
			if(strtolower($domain)!=strtolower($this->domainKey))
				throw new Exception("Error in syncEmail. Invalid Email Domain.");
		
			$mailObj = new Mail();
			$mailObj->addNewPop3Account($user,$domain,$password); // returns OK,ERROR,NOPASS
		}
	}
}


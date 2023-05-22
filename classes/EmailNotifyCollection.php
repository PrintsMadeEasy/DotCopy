<?php

class EmailNotifyCollection  {

	private $dbCmd;
	private $countTotalAdded = 0;
	private $countDuplicateInBatch = 0;
	private $countTotalSubmitted = 0;
	private $lastImportedBachId = 0;
	private $savedToDbFlag = false;
	private $sourceType = "";
	private $lastImportErrorDescription = "";
	private $statDateRangeStart = NULL;
	private $statDateRangeEnd   = NULL;
	private $statSelectedDomainIDsArr = array();
	
	const STATUS_READY		= "R";
	const STATUS_DELETED	= "D";
	const STATUS_BOUNCED	= "B";
	const STATUS_DNE		= "N";
	const STATUS_IGNORE		= "I";	// If an email matches one of our negative criteria
	const STATUS_BADSEED	= "S";	// If we detect a Bad Seed by triangulating on the address by showing up on Real-Time Block Lists
	
	const SOURCE_TYPE_BULK = "B";
	const SOURCE_TYPE_INTERNAL_DOMAIN = "D"; 
	
	private static $statusCodesArr = array(self::STATUS_READY, self::STATUS_DELETED, self::STATUS_BOUNCED, self::STATUS_DNE, self::STATUS_IGNORE, self::STATUS_BADSEED);
	private static $sourceTypeArr  = array(self::SOURCE_TYPE_BULK, self::SOURCE_TYPE_INTERNAL_DOMAIN);

	
	function __construct() {
	      
		$this->dbCmd = new DbCmd();
	}
	
	public function setSourceType($sourceType) {
	
		if(!in_array($sourceType, EmailNotifyCollection::$sourceTypeArr))
			throw new Exception("Illegal Status");
	
		$this->sourceType = $sourceType;
	}
	
	public function getCountTotalAdded() {
			
		if(!$this->savedToDbFlag)
			throw new Exception("Must call save method before calling this method.");
		
		return $this->countTotalAdded;
	}
	
	public function getCountDuplicateInBatch() {
		
		if(!$this->savedToDbFlag)
			throw new Exception("Must call save method before calling this method.");
			
		return $this->countDuplicateInBatch;
	}
	
	public function getCountTotalSubmitted() {
		
		if(!$this->savedToDbFlag)
			throw new Exception("Must call save method before calling this method.");
			
		return $this->countTotalSubmitted;
	}
	
	static function isDescriptionOK($description) {
	
		$dbCmd = new DbCmd();
		$dbCmd->Query("select ID from emailnotifybatch where Description = '" . DbCmd::EscapeSQL($description) . "'");	

		if($dbCmd->GetNumRows() > 0)
			return false;
		else 
			return true;
	}
	
	
	public function getLastImportErrorDescription() {
		
		return $this->lastImportErrorDescription;
	}
	
	public function getLastImportedBatchId() {
	
		return $this->lastImportedBachId;
	}

	
	private function validateCvsRow($row) {

		$fieldCount = sizeof($row);
		
		if($fieldCount==0) {
			$this->lastImportErrorDescription = "Empty Line";		
			return false;	
		}

		if($fieldCount>12) {
			$this->lastImportErrorDescription = "Too many fields";		
			return false;	
		}
		
		// if we have up to email(0) name(1) company(2) Title(3) -> OK
		if($fieldCount<4)
			return true;
		
		// Not all Address fields are present 	
		if( ($fieldCount > 4) && ($fieldCount < 9))
		{
			$this->lastImportErrorDescription = "Address not complete";		
			return false;
		}

		$this->lastImportErrorDescription = "Invalid country code";		
		if(strlen($row[8])!=2)  
			return false;	
		
		$this->lastImportErrorDescription = "";		
		
		// We have  Email(0), Name(1), Company(2), Title(3), Address(4), City(5), ZIP(6), State(7) and Country(8) all together -> OK	
		if($fieldCount == 9) 
			return true;	
		
		$phone = preg_replace('/[^0-9]/','',$row[9]);

		$this->lastImportErrorDescription = "Wrong phone number format";	
		if((strlen($phone)!=10) && (strlen($phone)!=0)) 		
			return false;	
				
		$this->lastImportErrorDescription = "";		
				
		// Nothing more after the phone ? OK, we're done
		if($fieldCount == 10) 
			return true;
	
		// Industry ? OK, we're done
		if($fieldCount == 11) 
			return true;
					
		if($fieldCount == 12) {

			$sicCode = preg_replace('/[^0-9]/','',$row[11]);
			$this->lastImportErrorDescription = "Wrong SIC";
			if(empty($sicCode))
				return false; 
				
			$this->lastImportErrorDescription = "";	
			return true;	
		}	
			
		// Something unexpected went wrong
		return false;
	}	
	
	
	private function validateListForDatabase($pathToCsvFile) {
		
		$csv = new ParseCSV($pathToCsvFile);
		
		$counter = 0;
		while ($row = $csv->NextLine()) {
			
			$counter++;	
			if(!self::validateCvsRow($row)) {
				
				$this->lastImportErrorDescription = "Line $counter: " . $this->lastImportErrorDescription . ".";
				return false;
			}
		}
		return true; // All went well, no errors
	}
	
	
	/*
	Format: email,   name,   companyName,   title,   address,city,zip,state,country,    phone,    industryType,   sicCode \n
	Message Template Placeholders: {PERSON_ADDRESS}  {PERSON_NAME}  {PERSON_TITLE}  {PERSON_SICCODE}  {PERSON_COMPANY}  {PERSON_INDUSTRY}  {PERSON_PHONE}  {PERSON_CITY}  {PERSON_STATE}  {PERSON_ZIP} {PERSON_COUNTRY}
	*/
	public function addBatchToDatabase($emailList, $description) {
					
		if(!self::isDescriptionOK($description) || empty($description)) 
			throw new Exception("Description already used !");	

		$pathToCsvFile = FileUtil::newtempnam(Constants::GetTempDirectory(), "CVSIMPORT", "");
		
		// Store email list into temp file
		$fp = fopen($pathToCsvFile, "w");
		fwrite($fp, $emailList);
		fclose($fp);	
		
		$emailList = "";
			
		// If the file format is not valid, we return with an error	
		if(!self::validateListForDatabase($pathToCsvFile))	{			
		//	unlink($pathToCsvFile);
			return false;
		}

		// Batches: Create space for new now on position 1
		self::increaseEachSequenceByOne();
		
		$batchArr["Date"]  	     = time(); 
		$batchArr["Description"] = $description;
		$batchArr["Sequence"]    = 1;
	
		$batchID = $this->dbCmd->InsertQuery("emailnotifybatch", $batchArr); 		
		$this->lastImportedBachId = $batchID;	
	
		$csv = new ParseCSV($pathToCsvFile);
		
		$addCounter = 0; $doubleInBatch = 0; $countTotalSubmitted = 0;
		while ($row = $csv->NextLine()) {
			
			$countTotalSubmitted++;
			
			$fieldCount = sizeof($row);
			
			if($fieldCount>0) 
				$email = trim($row[0]);
			
			$name = NULL; 	
			if($fieldCount>1) 
				$name = trim($row[1]);
	
			$companyName = NULL; 	
			if($fieldCount>2) 
				$companyName = trim($row[2]);
				
			$title = NULL; 	
			if($fieldCount>3) 
				$title = trim($row[3]);
					
			$address = NULL; $city = NULL; $zip = NULL;  $state = NULL;  $country = NULL;  
			
			if($fieldCount > 8) {
				$address = trim($row[4]);
				$city    = trim($row[5]);
				$state   = trim($row[6]);
				$zip     = trim($row[7]);
				$country = trim($row[8]);
			}	
			
			$phone = NULL; 
			if($fieldCount>9) 
				$phone = $row[9];
					
			$industryType = NULL; 	
			if($fieldCount>10) 
				$industryType = trim($row[10]);
					
			$sicCode = NULL; 	
			if($fieldCount>11) 
				$sicCode = $row[11]; 
	
			$this->dbCmd->Query( "SELECT COUNT(*) FROM emailnotifycollection WHERE BatchID = $batchID AND Email LIKE '".DbCmd::EscapeLikeQuery($email)."'");
				
			if($this->dbCmd->GetValue() > 0) {
				$doubleInBatch++;
				continue;
			}	   
				
			$addCounter++;	
					
			$insertArr["BatchID"]    = $batchID;
			$insertArr["Email"]      = $email;
			$insertArr["Name"]       = $name;				
			$insertArr["CompanyName"]= $companyName;
			$insertArr["Title"]      = $title;
			$insertArr["Address"]    = $address;
			$insertArr["City"]       = $city;
			$insertArr["State"]      = $state;
			$insertArr["Zip"]        = $zip;
			$insertArr["Country"]    = $country;
			$insertArr["Phone"]      = $phone;
			$insertArr["IndustryType"]= $industryType;
			$insertArr["SICCode"]    = $sicCode;
			$insertArr["Status"]     = self::STATUS_READY; 
			$insertArr["ImportDate"] = time();
			$insertArr["UpdateDate"] = time();
			$insertArr["SourceType"] = $this->sourceType;
			
			$this->dbCmd->InsertQuery("emailnotifycollection", $insertArr);	
		}
		
		// unlink($pathToCsvFile);
		
		$this->savedToDbFlag = true;
	
		$this->countTotalAdded = $addCounter;	
		$this->countDuplicateInBatch = $doubleInBatch;
		$this->countTotalSubmitted = $countTotalSubmitted;
	
		return true;	
	}
	
	public function updateEmailHistory($emailID, $domainID, $messageID, $jobID, $batchID){
		
		$emailID = intval($emailID);
		if(empty($emailID))
			throw new Exception("Illegal ID");

		$messageID = intval($messageID);
		if(empty($messageID))
			throw new Exception("Illegal messageID");	

		$jobID = intval($jobID);
		if(empty($jobID))
			throw new Exception("Illegal jobID");	

		$batchID = intval($batchID);
		if(empty($batchID))
			throw new Exception("Illegal batchId");		
						
		$id = $this->dbCmd->InsertQuery("emailnotifyhistory", array("EmailID"=>$emailID,"DomainID"=>$domainID,"MessageID"=>$messageID,"JobID"=>$jobID,"BatchID"=>$batchID,"Date"=>time()));
		
		return $id;
	}
	
	
	public function getBatchIdOfEmailId($emailId) {
		
		$emailId = intval($emailId);
		if(empty($emailId))
			throw new Exception("Illegal EmailID");
				
		$this->dbCmd->Query("SELECT BatchID FROM emailnotifycollection WHERE ID=$emailId");
		return $this->dbCmd->GetValue();	
	}


	public function changeStatus($email, $newStatus) {
	
		if(!in_array($newStatus, EmailNotifyCollection::$statusCodesArr))
			throw new Exception("Illegal Status");
		
		if(!WebUtil::ValidateEmail($email))
			throw new Exception("Email is invalid.");
						
		$this->dbCmd->UpdateQuery("emailnotifycollection", array("Status"=>$newStatus, "LastStatusChange"=>time()), "Email='" . DbCmd::EscapeSQL($email) . "'");
	}
	

	public function checkEmailStatus($email) {
		
		if(!WebUtil::ValidateEmail($email))
			throw new Exception("Email is not valid.");
				
		$this->dbCmd->Query("SELECT Status FROM emailnotifycollection WHERE Email='" . DbCmd::EscapeSQL($email) ."'");
		return $this->dbCmd->GetValue();	
	}
	
	public function getEmailByID($ID) {
		
		$ID = intval($ID);
		if(empty($ID))
			throw new Exception("Illegal EmailID");
				
		$this->dbCmd->Query("SELECT Email FROM emailnotifycollection WHERE ID=$ID");
		return $this->dbCmd->GetValue();	
	}

	public function getNameByID($ID) {
		
		$ID = intval($ID);
		if(empty($ID))
			throw new Exception("Illegal EmailID");
				
		$this->dbCmd->Query("SELECT Name FROM emailnotifycollection WHERE ID=$ID");
		return $this->dbCmd->GetValue();	
	}
	
	public function getCompanyNameByID($ID) {
		
		$ID = intval($ID);
		if(empty($ID))
			throw new Exception("Illegal EmailID");
				
		$this->dbCmd->Query("SELECT CompanyName FROM emailnotifycollection WHERE ID=$ID");
		return $this->dbCmd->GetValue();	
	}
	
	public function getTitleByID($ID) {
		
		$ID = intval($ID);
		if(empty($ID))
			throw new Exception("Illegal EmailID");
				
		$this->dbCmd->Query("SELECT Title FROM emailnotifycollection WHERE ID=$ID");
		return $this->dbCmd->GetValue();	
	}
		
	public function getAddressByID($ID) {
		
		$ID = intval($ID);
		if(empty($ID))
			throw new Exception("Illegal EmailID");
				
		$this->dbCmd->Query("SELECT Address FROM emailnotifycollection WHERE ID=$ID");
		return $this->dbCmd->GetValue();	
	}
		
		public function getCityByID($ID) {
		
		$ID = intval($ID);
		if(empty($ID))
			throw new Exception("Illegal EmailID");
				
		$this->dbCmd->Query("SELECT City FROM emailnotifycollection WHERE ID=$ID");
		return $this->dbCmd->GetValue();	
	}
		
		public function getZipByID($ID) {
		
		$ID = intval($ID);
		if(empty($ID))
			throw new Exception("Illegal EmailID");
				
		$this->dbCmd->Query("SELECT Zip FROM emailnotifycollection WHERE ID=$ID");
		return $this->dbCmd->GetValue();	
	}
		
		public function getStateByID($ID) {
		
		$ID = intval($ID);
		if(empty($ID))
			throw new Exception("Illegal EmailID");
				
		$this->dbCmd->Query("SELECT State FROM emailnotifycollection WHERE ID=$ID");
		return $this->dbCmd->GetValue();	
	}
		
		public function getCountryByID($ID) {
		
		$ID = intval($ID);
		if(empty($ID))
			throw new Exception("Illegal EmailID");
				
		$this->dbCmd->Query("SELECT Country FROM emailnotifycollection WHERE ID=$ID");
		return $this->dbCmd->GetValue();	
	}
		
		public function getPhoneByID($ID) {
		
		$ID = intval($ID);
		if(empty($ID))
			throw new Exception("Illegal EmailID");
				
		$this->dbCmd->Query("SELECT Phone FROM emailnotifycollection WHERE ID=$ID");
		return $this->dbCmd->GetValue();	
	}
		
		public function getIndustryByID($ID) {
		
		$ID = intval($ID);
		if(empty($ID))
			throw new Exception("Illegal EmailID");
				
		$this->dbCmd->Query("SELECT IndustryType FROM emailnotifycollection WHERE ID=$ID");
		return $this->dbCmd->GetValue();	
	}
		
		public function getSicCodeByID($ID) {
		
		$ID = intval($ID);
		if(empty($ID))
			throw new Exception("Illegal EmailID");
				
		$this->dbCmd->Query("SELECT SICCode FROM emailnotifycollection WHERE ID=$ID");
		return $this->dbCmd->GetValue();	
	}
		
		public function getByID($ID) {
		
		$ID = intval($ID);
		if(empty($ID))
			throw new Exception("Illegal EmailID");
				
		$this->dbCmd->Query("SELECT ~ FROM emailnotifycollection WHERE ID=$ID");
		return $this->dbCmd->GetValue();	
	}
		
	
	static function removeEmail($email) {
	
		if(!WebUtil::ValidateEmail($email))
			throw new Exception("Invalid Email");
		
		$dbCmd = new DbCmd();
		$dbCmd->UpdateQuery("emailnotifycollection", array("Status"=>self::STATUS_DELETED, "LastStatusChange"=>date("YmdHis", time())), "Email='" . DbCmd::EscapeSQL($email) . "'");	
	}
	
	public function updateLastDomainEmailDate($domainId, $emailID){
		
		$emailID = intval($emailID);
		
		if(empty($emailID))
			throw new Exception("Illegal emailID");
		
		$this->dbCmd->UpdateQuery("emailnotifycollection", array("LastEmailDate"=>time(),"LastDomainID"=>$domainId), "ID = $emailID");
	}
	
	static function markAllEmailsDneWithDomain($domainName){
	
		$dbCmd = new DbCmd();
		$dbCmd->UpdateQuery("emailnotifycollection", array("Status"=>self::STATUS_DNE), "SUBSTRING_INDEX(Email,'@',-1)='" . DbCmd::EscapeSQL($domainName) . "'");
	}
	
	
	function updateEmailIgnorePatternsList($domainList) {

		$domainList = $domainList . "\n";		 	
		$domainArr = explode("\n", $domainList);
		
		if(empty($domainArr))
			throw new Exception("Empty List");

		$this->dbCmd->Query("truncate table emailnotifypatternstoignore");	
				
		foreach ($domainArr AS $emailDomain) {
	
			$emailDomain = trim($emailDomain);
			$emailDomain = strtolower($emailDomain);
			$emailDomain = DbCmd::EscapeSQL($emailDomain);
			
			if(empty($emailDomain))
				continue;
										
			$insertArr["Date"]= time();
			$insertArr["EmailDomain"]  = $emailDomain;
			$this->dbCmd->InsertQuery("emailnotifypatternstoignore", $insertArr);				
		}
		
		self::updateEmailStatusWithNegativeCriteria();
	}

		
	function getEmailIgnorePatternsList() {
		
		$completeList = "";
		
		$this->dbCmd->Query("select distinct(EmailDomain) FROM emailnotifypatternstoignore order by EmailDomain ASC");	
		
		while($listItem = $this->dbCmd->GetValue())
			$completeList .= $listItem . "\n";
		
		$completeList = trim($completeList,"\n");	
			
		return $completeList;	
	}
	
	
	// This can only change a READY status to IGNORE ... or visa versa.
	// Once an email address has another status, it can not be changed by negative criteria.
	static function updateEmailStatusWithNegativeCriteria(){
		
		// First try and take any READY status, and convert it to an "IGNORE" status
		$dbCmd = new DbCmd();
		$dbCmd2 = new DbCmd();
		
		$dbCmd->Query("SELECT EmailDomain FROM emailnotifypatternstoignore");
			$ignoreEmailAddressesArr = $dbCmd->GetValueArr();
		
		// Possibly change READY status to the IGNORE status
		$dbCmd->Query("SELECT Email FROM emailnotifycollection WHERE Status='" . self::STATUS_READY . "'");
		while($thisEmail = $dbCmd->GetValue()){
			foreach($ignoreEmailAddressesArr as $thisEmailToIgnore){
				if(preg_match("/".preg_quote($thisEmailToIgnore)."/i", $thisEmail)){
					$dbCmd2->UpdateQuery("emailnotifycollection", array("Status"=>self::STATUS_IGNORE), ("Email='" . DbCmd::EscapeSQL($thisEmail)."'"));
					break;
				}
			}
		}
		
		// Possibly change IGNORE status to the READY status if a negative criteria has been removed.
		$dbCmd->Query("SELECT Email FROM emailnotifycollection WHERE Status='" . self::STATUS_IGNORE . "'");
		while($thisEmail = $dbCmd->GetValue()){
			
			$ignoreMatchFound = false;
			foreach($ignoreEmailAddressesArr as $thisEmailToIgnore){
				if(preg_match("/".preg_quote($thisEmailToIgnore)."/i", $thisEmail)){
					$ignoreMatchFound = true;
				}
			}
			
			if(!$ignoreMatchFound)
				$dbCmd2->UpdateQuery("emailnotifycollection", array("Status"=>self::STATUS_READY), ("Email='" . DbCmd::EscapeSQL($thisEmail). "'"));
		}
		
	}
	
	static function addEmailSendingError($email,$error,$emailHistoryId) {
			
		$emailHistoryId = intval($emailHistoryId);
		if(empty($emailHistoryId))
			throw new Exception("Empty emailHistoryId");
	
		if(!WebUtil::ValidateEmail($email))
			throw new Exception("Wrong Email");
		
		$dbCmd = new DbCmd();
		
		$dbCmd->Query("SELECT ID FROM emailnotifycollection WHERE Email = '$email'");
		$emailIdArr = $dbCmd->GetValueArr();	
				
		if(empty($emailIdArr))
			throw new Exception("Empty Email List");
		
		$whereClause = "";
		foreach($emailIdArr AS $emailId) 
			$whereClause .= "(EmailID=$emailId) OR";
		$whereClause = trim($whereClause," OR");

		// Since we have only one email per JobId we can find the BatchID now 
		$dbCmd->Query( "SELECT BatchID, DomainID, JobID FROM emailnotifyhistory WHERE ID = $emailHistoryId AND (" . $whereClause . ")");
		$row = $dbCmd->GetRow();
		
		$batchId  = $row["BatchID"];
		$domainId = $row["DomainID"];
		$jobId    = $row["JobID"];
		
		if(empty($batchId))
			 throw new Exception("No batch ID");
		
		$errorArr["Date"] 	   = time();
		$errorArr["Email"]     = $email; 
		$errorArr["ErrorCode"] = intval($error);
		$errorArr["DomainID"]  = $domainId;
		$errorArr["JobID"]     = $jobId;
		$errorArr["BatchID"]   = $batchId;
		
		$dbCmd->InsertQuery("emailnotifysenderror", $errorArr);	
	}
	
	public function getBatchIdArr() {
		
		$this->dbCmd->Query("select ID from emailnotifybatch order by Sequence");
		return $this->dbCmd->GetValueArr();
	}


	
	

	public function getBatchDecriptionById($id) {
		
		$id = intval($id);
		if(empty($id))
			throw new Exception("Invalid Batch ID");
	
		$this->dbCmd->Query("SELECT Description FROM emailnotifybatch WHERE ID=$id");
		return $this->dbCmd->GetValue();
	}
	
	public function getBatchImportDateById($id) {
		
		$id = intval($id);
		if(empty($id))
			throw new Exception("Invalid Batch ID");
	
		$this->dbCmd->Query("SELECT UNIX_TIMESTAMP(Date) AS ImportDate  FROM emailnotifybatch WHERE ID=$id");
		return $this->dbCmd->GetValue();
	}
	
	public function moveBatchChoicePosition($batchId, $moveForwardFlag) {

		$batchId1 = intval($batchId);
	
		$this->dbCmd->Query("SELECT Sequence FROM emailnotifybatch WHERE ID = $batchId1");
		$sequenceId1 = $this->dbCmd->GetValue();		
		
		if(empty($sequenceId1))
			throw new Exception("Invalid Batch ID");
			
		// true: moves UP, false: DOWN
		if($moveForwardFlag)	
			$sequenceId2 = $sequenceId1 - 1;		
		else 
			$sequenceId2 = $sequenceId1 + 1;		
			
		$this->dbCmd->Query("SELECT ID FROM emailnotifybatch WHERE Sequence = $sequenceId2");
		$batchId2 = $this->dbCmd->GetValue();		
	
		// Only move (switch) if in range
		if(!empty($batchId2)) {
		
			$this->dbCmd->UpdateQuery("emailnotifybatch", array("Sequence"=>$sequenceId1), "ID=" . $batchId2);
			$this->dbCmd->UpdateQuery("emailnotifybatch", array("Sequence"=>$sequenceId2), "ID=" . $batchId1);
		}	
	}
	
	
	private function increaseEachSequenceByOne() {

		$valueArr = array();
		$this->dbCmd->Query("SELECT ID,Sequence FROM emailnotifybatch Order By Sequence"); 
		while($row = $this->dbCmd->GetRow()) {
			$value["ID"]       = $row["ID"];
			$value["Sequence"] = $row["Sequence"] + 1;
			$valueArr[]        = $value;
		}
	
		foreach($valueArr as $value) 			
			$this->dbCmd->UpdateQuery("emailnotifybatch", array("Sequence"=>$value["Sequence"]), "ID=" . $value["ID"]);		
	}
	
	public function createBatchDomainCheckBoxes($batchId, $allowedDomainIdArr) {

		$batchId = intval($batchId);
		if(empty($batchId))
			throw new Exception("Invalid Batch ID");
		
		$this->dbCmd->Query("SELECT DomainID FROM emailnotifybatchdomain WHERE BatchID=$batchId"); 
		$domainIdsInBatch = $this->dbCmd->GetValueArr();
	
		$checkBoxList = "";
		foreach ($allowedDomainIdArr as $domainId) {
				
			$checked = "";
			if(in_array($domainId,$domainIdsInBatch))
				$checked = " CHECKED";
						
			$domainLogoObj = new DomainLogos($domainId);
			$domainLogoImg = "<img src='./domain_logos/$domainLogoObj->verySmallIcon' border='0'>";
			
			$checkBoxList .=  $domainLogoImg . "<INPUT TYPE=CHECKBOX NAME='DomainID$domainId' VALUE='1'$checked>". Domain::getDomainKeyFromID($domainId) . "<br>\n";
		}
		return $checkBoxList;
	}

	

	public function generateBatchDomainText($batchId, $allowedDomainIdArr) {
	
		$batchId = intval($batchId);
		if(empty($batchId))
			throw new Exception("Invalid Batch ID");
		
		$this->dbCmd->Query("SELECT DomainID FROM emailnotifybatchdomain WHERE BatchID=$batchId ORDER BY DomainID"); 
		$domainIdsInBatch = $this->dbCmd->GetValueArr();
		
		$text = "";
		foreach ($domainIdsInBatch AS $domainId) { 	
			
			$domainLogoObj = new DomainLogos($domainId);
			
			if(in_array($domainId, $allowedDomainIdArr))
				$text .= "<img src='./domain_logos/$domainLogoObj->verySmallIcon' border='0'> " . Domain::getDomainKeyFromID($domainId) ."<br>\n";
		}
			
		trim($text,"\n");
		
		return $text;
	}

	public function generateJobDomainText($jobId) {
	
		$jobId = intval($jobId);
		if(empty($jobId))
			throw new Exception("Invalid Job ID");
		
		$this->dbCmd->Query("SELECT DomainID FROM emailnotifyjob WHERE ID=$jobId ORDER BY DomainID"); 
		$domainId = $this->dbCmd->GetValue();
		
		$domainLogoObj = new DomainLogos($domainId);
			
		$domainImageText = "<img src='./domain_logos/$domainLogoObj->verySmallIcon' border='0'> " . Domain::getDomainKeyFromID($domainId) ."<br>\n";
			
		trim($domainImageText,"\n");
		
		return $domainImageText;
	}

	
	public function getJobDateById($jobId) {
		
		$jobId = intval($jobId);
		if(empty($jobId))
			throw new Exception("Invalid Job ID");
		
		$this->dbCmd->Query("SELECT UNIX_TIMESTAMP(DeliveryDate) AS JobDate FROM emailnotifyjob WHERE ID=$jobId ORDER BY DomainID"); 
		return  $this->dbCmd->GetValue();
	}
	
	
	
	public function updateBatchDomainRelation($batchId, $domainId, $onOff = true) {
		
		$batchId = intval($batchId);
		if(empty($batchId))
			throw new Exception("Invalid Batch ID");
			
		$domainId = intval($domainId);
		if(empty($domainId))
			throw new Exception("Invalid Domain ID");
		
		$this->dbCmd->Query("SELECT ID FROM emailnotifybatchdomain WHERE BatchID=$batchId AND DomainID=$domainId"); 
		$existingID = $this->dbCmd->GetValue();
		
		// On=True, Off=False
		if($onOff && empty($existingID))
			$this->dbCmd->InsertQuery("emailnotifybatchdomain", array("DomainID"=>$domainId,"BatchID"=>$batchId));	
				
		if(!$onOff && !empty($existingID))
			$this->dbCmd->Query("DELETE FROM emailnotifybatchdomain WHERE BatchID=$batchId AND DomainID=$domainId");		
	}

	
	public function setInitialBatchDomain($batchId, $domainId) {
		
		$batchId = intval($batchId);
		if(empty($batchId))
			throw new Exception("Invalid Batch ID");
			
		$domainId = intval($domainId);
		if(empty($domainId))
			throw new Exception("Invalid Domain ID");
		
		$this->dbCmd->Query("SELECT ID FROM emailnotifybatchdomain WHERE BatchID=$batchId AND DomainID=$domainId"); 
		$existingID = $this->dbCmd->GetValue();
		
		if(empty($existingID))
			$this->dbCmd->InsertQuery("emailnotifybatchdomain", array("DomainID"=>$domainId,"BatchID"=>$batchId));				
	}
	
	
	public function setStatisticsDateRange($statDateRangeStart, $statDateRangeEnd) {
		
		$this->statDateRangeStart = $statDateRangeStart;
		$this->statDateRangeEnd   = $statDateRangeEnd;
	}

	public function setStatisticsAllowedDomainIds($selectedDomainIDsArr) {
		
		$this->statSelectedDomainIDsArr = $selectedDomainIDsArr;
	}
	
	private function statisticDateRangeDomainClause($dateField = "Date", $domainIdField = "DomainID") {
	
		if(empty($this->statDateRangeStart) || empty($this->statDateRangeEnd))
			$dateSelected = " ";
		else 	
			$dateSelected = " AND ($dateField>='".date("YmdHis", $this->statDateRangeStart)."' AND $dateField<'".date("YmdHis", ($this->statDateRangeEnd) + 86400) ."') ";
			
		$domainClause = "";	
		if(!empty($this->statSelectedDomainIDsArr)) {	
			
			$domainClause = " AND (";
			foreach($this->statSelectedDomainIDsArr AS $domainId) 
				$domainClause .= " ($domainIdField=$domainId) OR";
			$domainClause = trim($domainClause," OR");
			$domainClause .= ")";	
		}
			
		return $dateSelected . $domainClause;
	}

	public function statisticSendErrorsByBatchId($batchId) {

		$batchId = intval($batchId);
		if(empty($batchId))
			throw new Exception("Invalid Batch ID");

		$this->dbCmd->Query("SELECT Count(*) AS ErrorCount FROM emailnotifysenderror WHERE BatchID=$batchId" . $this->statisticDateRangeDomainClause()); 	
		return $this->dbCmd->GetValue();		
	}
	
	public function statisticTrackingByBatchId($batchId) {

		$batchId = intval($batchId);
		if(empty($batchId))
			throw new Exception("Invalid Batch ID");
			
		$this->dbCmd->Query("SELECT Count(*) AS TrackingCount FROM emailnotifytracking WHERE BatchID=$batchId" . $this->statisticDateRangeDomainClause()); 	
		return $this->dbCmd->GetValue();
	}
	
	public function statisticSentOutByBatchId($batchId) {

		$batchId = intval($batchId);
		if(empty($batchId))
			throw new Exception("Invalid Batch ID");
			
		$this->dbCmd->Query("SELECT Count(*) AS SendCount FROM emailnotifyhistory WHERE BatchID=$batchId". $this->statisticDateRangeDomainClause()); 	
		return $this->dbCmd->GetValue();
	}
	
	public function statisticClicksByBatchId($batchId) {

		$batchId = intval($batchId);
		if(empty($batchId))
			throw new Exception("Invalid Batch ID");

		$this->dbCmd->Query("SELECT Count(*) AS OrderCount FROM emailnotifyorders WHERE BatchID=$batchId". $this->statisticDateRangeDomainClause()); 	
		return $this->dbCmd->GetValue();
	}
	
	public function statisticOrdersByBatchId($batchId) {

		$batchId = intval($batchId);
		if(empty($batchId))
			throw new Exception("Invalid Batch ID");

		$this->dbCmd->Query("SELECT COUNT(*) As OrderCount from emailnotifyhistory, orders WHERE (emailnotifyhistory.ID = orders.EmailNotifyJobHistoryID) AND emailnotifyhistory.BatchID=$batchId" . $this->statisticDateRangeDomainClause("orders.DateOrdered", "emailnotifyhistory.DomainID")); 	
		return $this->dbCmd->GetValue();
	}	
	
	public function getStatisticsJobIdArr() {
		
		$this->dbCmd->Query("select ID from emailnotifyjob WHERE DomainID > 0 " . $this->statisticDateRangeDomainClause("DeliveryDate","DomainID") . " order by DeliveryDate DESC");
		return $this->dbCmd->GetValueArr();
	}

	public function statisticSendErrorsByJobId($jobId) {

		$jobId = intval($jobId);
		if(empty($jobId))
			throw new Exception("Invalid Job ID");

		$this->dbCmd->Query("SELECT Count(*) AS ErrorCount FROM emailnotifysenderror WHERE JobID=$jobId" . $this->statisticDateRangeDomainClause()); 	
		return $this->dbCmd->GetValue();		
	}
	
	public function statisticTrackingByJobId($jobId) {

		$jobId = intval($jobId);
		if(empty($jobId))
			throw new Exception("Invalid Job ID");
			
		$this->dbCmd->Query("SELECT Count(*) AS TrackingCount FROM emailnotifytracking WHERE JobID=$jobId" . $this->statisticDateRangeDomainClause()); 	
		return $this->dbCmd->GetValue();
	}
	
	public function statisticSentOutByJobId($jobId) {

		$jobId = intval($jobId);
		if(empty($jobId))
			throw new Exception("Invalid Job ID");
			
		$this->dbCmd->Query("SELECT Count(*) AS SendCount FROM emailnotifyhistory WHERE JobID=$jobId". $this->statisticDateRangeDomainClause()); 	
		return $this->dbCmd->GetValue();
	}
	
	public function statisticClicksByJobId($jobId) {

		$jobId = intval($jobId);
		if(empty($jobId))
			throw new Exception("Invalid Job ID");

		$this->dbCmd->Query("SELECT Count(*) AS OrderCount FROM emailnotifyorders WHERE JobID=$jobId". $this->statisticDateRangeDomainClause()); 	
		return $this->dbCmd->GetValue();
	}
	
	public function statisticOrdersByJobId($jobId) {

		$jobId = intval($jobId);
		if(empty($jobId))
			throw new Exception("Invalid Job ID");

		$this->dbCmd->Query("SELECT COUNT(*) As OrderCount from emailnotifyhistory, orders WHERE (emailnotifyhistory.ID = orders.EmailNotifyJobHistoryID) AND emailnotifyhistory.JobID=$jobId" . $this->statisticDateRangeDomainClause("orders.DateOrdered", "emailnotifyhistory.DomainID")); 	
		return $this->dbCmd->GetValue();
	}	
	
	
	
	
}


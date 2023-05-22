<?php


class EmailNotifyJob {
	
	private $dbCmd;
	private $domainID;
		
	private $emailIdsArr = array();
	private $minEmailQuantity;
	private $maxEmailQuantity;
	private $minDaysSinceLastEmail;
	private $maxDaysSinceLastEmail;
	
	private $minDaysBetweenDomainEmails;
	private $minDaysBetweenDomainEmailsVariance;
	
	private $amountToSendEachCronIteration;
	private $cronPeriodMinutes;
	
	function __construct($domainID){
			
		$domainID = intval($domainID);
		if(empty($domainID))
			throw new Exception("ID is invalid.");
			
		$this->domainID = $domainID;
			
		$this->dbCmd = new DbCmd();
			
	/*	$this->minEmailQuantity      = 100;
		$this->maxEmailQuantity      = 500;
		$this->minDaysSinceLastEmail = 4;
		$this->maxDaysSinceLastEmail = 8;
		$this->minDaysBetweenDomainEmails = 10;
	*/		

		$this->minEmailQuantity      = 900;
		$this->maxEmailQuantity      = 1600;
		$this->minDaysSinceLastEmail = 4;
		$this->maxDaysSinceLastEmail = 3650; // No imit how old the emails are. The 387k batch took 4 Mts to be used up.
		$this->minDaysBetweenDomainEmails = 18;
	
		

		
		$this->amountToSendEachCronIteration = 35;
		$this->cronPeriodMinutes = 30;
		
		// A randomization setup variable to prevent minDaysBetweenDomainEmails from becoming consistent.
		$this->minDaysBetweenDomainEmailsVariance = 5;
		
		 srand(microtime()*1000000);
	}

	private function getNextJobID($messageID) {
		
		$messageID = intval($messageID);
		$jobInsert["DomainID"]     = $this->domainID;
		$jobInsert["DeliveryDate"] = time();
		$jobInsert["MessageID"]    = $messageID;
		$jobID = $this->dbCmd->InsertQuery("emailnotifyjob", $jobInsert);
		
		return $jobID;
	}

	static function registerTrackIdClick($emailTrackHistoryId)
	{	
		$emailTrackHistoryId = intval($emailTrackHistoryId);
		
		if(empty($emailTrackHistoryId))
			throw new Exception("Empty ID");
			
		$dbCmd = new DbCmd();
		
		$dbCmd->Query("SELECT * FROM emailnotifyhistory WHERE ID = $emailTrackHistoryId");
		$row = $dbCmd->GetRow();
		
		$insertArr["Date"]    = time();
		$insertArr["JobID"]   = $row["JobID"];
		$insertArr["BatchID"] = $row["BatchID"];
		$insertArr["EmailID"] = $row["EmailID"];
		$insertArr["MessageID"] = $row["MessageID"];
		$insertArr["DomainID"] = $row["DomainID"];
		
		$dbCmd->InsertQuery("emailnotifytracking", $insertArr);
	}

	static function getDomainIdOfHistoryId($historyId)
	{	
		$historyId = intval($historyId);	
		if(empty($historyId))
			throw new Exception("Empty ID");
			
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT DomainID FROM emailnotifyhistory WHERE ID = $historyId");
		return $dbCmd->GetValue();
	}

	static function getEmailOfHistoryId($historyId)
	{		
		$historyId = intval($historyId);
		if(empty($historyId))
			throw new Exception("Empty ID");
	
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT Email FROM emailnotifycollection, emailnotifyhistory WHERE (emailnotifycollection.ID = emailnotifyhistory.EmailID) AND emailnotifyhistory.ID = $historyId");
		return $dbCmd->GetValue();	
	}

	static function recordClick($email, $emailHistoryId)
	{		
		$emailHistoryId = intval($emailHistoryId);
		if(empty($emailHistoryId))
			throw new Exception("Invalid ID");
			
		if(!WebUtil::ValidateEmail($email))
			throw new Exception("Email wrong");
			
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT * FROM emailnotifyhistory WHERE ID = $emailHistoryId");
		$row = $dbCmd->GetRow();

		$insertArr["Date"]    = time();
		$insertArr["JobID"]   = $row["JobID"];
		$insertArr["BatchID"] = $row["BatchID"];
		$insertArr["MessageID"] = $row["MessageID"];
		$insertArr["Email"]   = $email;
		$insertArr["DomainID"] = $row["DomainID"];
		
		$dbCmd->InsertQuery("emailnotifyorders", $insertArr);
	}

	
	function getJobXML(){
	
		if(empty($this->domainID))
			throw new Exception("ID is not set.");
			
		$emailMessageObj = new EmailNotifyMessages($this->domainID);
		
		try {
			$emailMessageObj->loadMessageForToday();
		}
		catch (ExceptionCommunicationError $e) {
			return $e->getMessage();
		}
	
		$messageID = $emailMessageObj->getMessageID();
			
		if(empty($messageID)) 
			return "<xml><error>No Message for this domain</error></xml>";
			
		$randomDaysBetweenDomainEmails = $this->minDaysBetweenDomainEmails + rand(0, $this->minDaysBetweenDomainEmailsVariance);	
		
		$emailQueryObj = new EmailNotifyCollectionQuery();
		
		$emailQueryObj->setDomainID($this->domainID);
		$emailQueryObj->setEmailMessageID($messageID);
		
		$emailQueryObj->limitDaysBetweenDomainEmails($randomDaysBetweenDomainEmails);
		$emailQueryObj->limitMinQuantity($this->minEmailQuantity);
		$emailQueryObj->limitMaxQuantity($this->maxEmailQuantity);
		$emailQueryObj->limitMinDaysSinceLastEmail($this->minDaysSinceLastEmail);
		$emailQueryObj->limitMaxDaysSinceLastEmail($this->maxDaysSinceLastEmail);

		$emailCollectionObj = new EmailNotifyCollection();
		
		// We are going to decluster the email IDs by domains.
		// For example, we want to avoid sending Yahoo, or Hotmail users back to back.
		$emailIdsToDeCluster = $emailQueryObj->getEmailIdsArr();
	
		// Make a parallel array (to the Email Id's) with the domain of every email address
		$emailDomainNamesArr = array();
		foreach($emailIdsToDeCluster as $emailID){
			$emailAddress = $emailCollectionObj->getEmailByID($emailID);
			
			// Strip off the @ symbol, and everything that comes before it.
			$emailDomainNamesArr[] = strtolower(preg_replace("/^[^@]+@/", "", $emailAddress));
		}
		
		$this->emailIdsArr = WebUtil::arrayDecluster($emailDomainNamesArr, $emailIdsToDeCluster);
		
		foreach($this->emailIdsArr AS $emailId) {	
			if(empty($emailId))
				return "<xml><error>Empty EmailId</error></xml>"; 
		}	

		//throw new Exception("No Emails to send");	
		if(empty($this->emailIdsArr)) 
			return "<xml><error>No Emails to send</error></xml>";
		
		// Update the last Email date and DomainID to prevent other Domains from getting duplicates.
		foreach($this->emailIdsArr AS $emailID)	
			$emailCollectionObj->updateLastDomainEmailDate($this->domainID, $emailID);
			
		$jobId = $this->getNextJobID($messageID); 
	
		$xml = "<xml>";	 
		$xml .="<emailJobs>";	
			
		$xml .="<amountToSendEachCronIteration>$this->amountToSendEachCronIteration</amountToSendEachCronIteration>";
		$xml .="<cronPeriodMinutes>$this->cronPeriodMinutes</cronPeriodMinutes>";
		
		$xml .="<Job>";
		
		$body = $emailMessageObj->getEmailSourceWithInline($this->domainID);
		$messageBase64 = base64_encode($body); 
		
		$subjectBase64 = base64_encode($emailMessageObj->getSubject());
		$htmlOrText    = $emailMessageObj->getIsHTML() ? "HTML" : "TEXT";
		
		$xml .="<message>
			<messageFormat>$htmlOrText</messageFormat>
			<messageSubject>$subjectBase64</messageSubject>
			<messageContents>$messageBase64</messageContents>
		</message>";
		
		$fromName  = $emailMessageObj->getFromName();
		$fromEmail = $emailMessageObj->getFromEmail();
		
		if( (empty($fromName)) || (empty($fromEmail)) ) {
			$domainEmailObj = new DomainEmails($this->domainID);
			$fromName  = $domainEmailObj->getEmailNameOfType("Marketing");	
			$fromEmail = $domainEmailObj->getEmailAddressOfType("Marketing");		
		}
		
		// It's impossible to send an email without From: , in case we don't have one specified here we have to abord.
		if(empty($fromEmail)) {
			
			$error = "EmailNotifyJob.getJobXML: No FROM: email defined for ". Domain::getDomainKeyFromID($this->domainID);
			
			WebUtil::WebmasterError($error);
			throw new Exception($error);	
		}
		
		$xml .="<fromName>" .  $fromName . "</fromName>";
		$xml .="<fromEmail>" . $fromEmail . "</fromEmail>";
		
		$xml .="<jobID>$jobId</jobID>";
		
		$xml .="<emailList>";
		
		
		$emailCount = sizeof($this->emailIdsArr) + 1; // One Added Email
		
		
		$xml .="<emailCount>" . $emailCount . "</emailCount>";
		
		$counter = 0;
		
		// First Email goes to Christian during initial phase
		$counter++;
		$xml .= "<person>
			<Position>$counter</Position>
			<ID>0</ID>
			<Name>Christian Nuesch</Name>
			<Title>Programmer</Title>
			<Company>ACME Company</Company>
			<Industry>Software</Industry>
			<Email>christian@asynx.com</Email>
			<Phone>8184937200</Phone>
			<Address>First Street</Address>
			<City>Simi Valley</City>
			<State>CA</State>
			<Zip>93065</Zip>
			<Country>US</Country>
			<SICCode>0123456789</SICCode>
		</person>";	

		// To check DKIM sent emails
		$counter++;
		$xml .= "<person>
			<Position>$counter</Position>
			<ID>0</ID>
			<Name>Christian Nuesch</Name>
			<Title>Programmer</Title>
			<Company>ACME Software</Company>
			<Industry>Software</Industry>
			<Email>christian_nuesch@yahoo.com</Email>
			<Phone>8184937200</Phone>
			<Address>First Street</Address>
			<City>Simi Valley</City>
			<State>CA</State>
			<Zip>93065</Zip>
			<Country>US</Country>
			<SICCode>0123456789</SICCode>
		</person>";	
		
		foreach($this->emailIdsArr AS $emailID) {

			$counter++;
			
			$email     = $emailCollectionObj->getEmailByID($emailID);
			$name 	   = $emailCollectionObj->getNameByID($emailID);
			$company   = $emailCollectionObj->getCompanyNameByID($emailID);
			$title     = $emailCollectionObj->getTitleByID($emailID);
			$address   = $emailCollectionObj->getAddressByID($emailID);
			$city      = $emailCollectionObj->getCityByID($emailID);
			$state     = $emailCollectionObj->getStateByID($emailID);
			$zip       = $emailCollectionObj->getZipByID($emailID);
			$country   = $emailCollectionObj->getCountryByID($emailID);
			$phone     = $emailCollectionObj->getPhoneByID($emailID);
			$industry  = $emailCollectionObj->getIndustryByID($emailID);
			$sicCode   = $emailCollectionObj->getSicCodeByID($emailID);
						
			$batchId = $emailCollectionObj->getBatchIdOfEmailId($emailID); 
			
			$historyId = $emailCollectionObj->updateEmailHistory($emailID, $this->domainID, $messageID, $jobId, $batchId);
	
			$xml .= "<person>
						<Position>$counter</Position>
						<ID>$historyId</ID>
						<Name>$name</Name>
						<Title>$title</Title>
						<Company>$company</Company>
						<Industry>$industry</Industry>
						<Email>$email</Email>
						<Phone>$phone</Phone>
						<Address>$address</Address>
						<City>$city</City>
						<State>$state</State>
						<Zip>$zip</Zip>
						<Country>$country</Country>
						<SICCode>$sicCode</SICCode>
					</person>";	
								
		}	
		
		$xml .="</emailList>\n";
		$xml .="</Job>\n";
		$xml .="</emailJobs>\n";
		$xml .= "</xml>";	

		return base64_encode(gzencode($xml,9));
	}
	
	
	function notifyAdminsOfEmailJob(){
		
		$subject = "Emails By {DomainName} (count)";
		$messageBody = $subject;
		
		$totalSentEmails = 0;
				
		$messageBody .= "Sent Emails with Emailnotification: \n";
		
		$todayStart = date("Y-m-d") . "00:00:00";
		$todayEnd   = date("Y-m-d") . "23:59:59";
		
		$this->dbCmd->Query("SELECT distinct(DomainID) AS DomainIDs FROM emailnotifyhistory WHERE (Date>$todayStart AND date<$todayEnd) ORDER BY DomainID DESC");
		$domainIds = $this->dbCmd->GetValueArr();
	
		foreach($domainIds AS $domainID) {
			
			$this->dbCmd->Query("SELECT count(*) AS CountEmails FROM emailnotifyhistory WHERE (Date>$todayStart AND date<$todayEnd) AND DomainID = $domainID");
			$countEmails = $this->dbCmd->GetValue();
			
			$messageBody .= Domain::getDomainKeyFromID($domainID) . ": $countEmails\n";
			
			$totalSentEmails += $countEmails;
		}
		
		$subject = "EmailNotify: Sent $totalSentEmails to " . sizeof($domainIds) ." Domains";
		
		$emailContactsForReportsArr = Constants::getEmailContactsForServerReports();
		foreach($emailContactsForReportsArr as $thisEmailContact)
			WebUtil::SendEmail("Email Notification Report", Constants::GetMasterServerEmailAddress(), "", $thisEmailContact, $subject, $messageBody, true);			
	}
		
}

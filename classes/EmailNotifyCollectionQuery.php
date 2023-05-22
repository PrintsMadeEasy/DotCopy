<?php

class EmailNotifyCollectionQuery {
	
	private $dbCmd;
	private $domainID;
	
	private $maxQuantity;
	private $minQuantity;
	private $minDaysSinceLastEmail;
	private $maxDaysSinceLastEmail;
	private $minDaysBetweenDomainEmails;
	private $messageID;
	
	function __construct() {
	      
		$this->dbCmd = new DbCmd();
		
		 srand(microtime()*1000000);
	}
	
	function setDomainID($domainID){
		
		$domainID = intval($domainID);
		
		if(empty($domainID))
			throw new Exception("ID is invalid.");
		
		$this->domainID = $domainID;
	}
	
	function limitMaxQuantity($maxQ){
		
		$maxQ = intval($maxQ);
		$this->maxQuantity = $maxQ;
	}
	
	// Something between Max and Min quantity means ranomization in this class.
	function limitMinQuantity($minQ){
		
		$minQ = intval($minQ);
		$this->minQuantity = $minQ;
	}
	
	function limitMinDaysSinceLastEmail($numDays){

		$numDays = intval($numDays);
		$this->minDaysSinceLastEmail = $numDays;
	}

	function limitMaxDaysSinceLastEmail($numDays){
		
		$numDays = intval($numDays);
		$this->maxDaysSinceLastEmail = $numDays;
	}	
	
	function limitDaysBetweenDomainEmails ($numDays){
		
		$numDays = intval($numDays);
		$this->minDaysBetweenDomainEmails = $numDays;
	}	
	
	// We need to now which messageID is sent to prevent sending the same emailMessage twice
	function setEmailMessageID($messageID){
		
		$messageID = intval($messageID);
		$this->messageID = $messageID;
	}	
	
	function getEmailIdsArr(){
			
		if(empty($this->domainID))
			throw new Exception("ID is not set.");
		
		if(empty($this->messageID))
			throw new Exception("MessageID is not set.");	
			
		$this->dbCmd->Query( "SELECT BatchID FROM emailnotifybatch, emailnotifybatchdomain WHERE emailnotifybatch.ID = emailnotifybatchdomain.BatchId AND emailnotifybatchdomain.DomainID = $this->domainID ORDER BY emailnotifybatch.Sequence" );
		
		$batchIdArr = $this->dbCmd->GetValueArr();
//WebUtil::SendEmail("Server Debug", "debug@printsmadeeasy.com", "Christian Nuesch", "christian@printsmadeeasy.com", "Email Notify: Batches", "Domain: " . Domain::getDomainKeyFromID($this->domainID) . "\Batch IDs: " . var_export($batchIdArr));
		// No data at all for this DomainId
		if(empty($batchIdArr))
			return array();

		$emailColectionObj = new EmailNotifyCollection();		
			
		$desiredQty = rand($this->minQuantity, $this->maxQuantity); 
		
		$minDomainDate = DbCmd::convertUnixTimeStmpToMysql(time() - ($this->minDaysBetweenDomainEmails*24*3600)); 
		$minEmailDate  = DbCmd::convertUnixTimeStmpToMysql(time() - ($this->minDaysSinceLastEmail*24*3600)); 
		$maxEmailDate  = DbCmd::convertUnixTimeStmpToMysql(time() - ($this->maxDaysSinceLastEmail*24*3600)); 			

		$emailIdArr    = array();		
		$emailRecyclePercentage = 20;
		
		foreach ($batchIdArr AS $batchId) {
			
			if(sizeof($emailIdArr) < $desiredQty) {
			
				$limitRecycledSelectionQty = 0;
						
				$this->dbCmd->Query( "SELECT COUNT(*) FROM emailnotifycollection WHERE BatchID = $batchId AND  Status='" . EmailNotifyCollection::STATUS_READY . "' AND Status <> '" .  EmailNotifyCollection::STATUS_IGNORE . "' AND LastEmailDate IS NULL" );
				$availNewEmailQty = $this->dbCmd->GetValue();
			
				$limitNewSelectionQty = $desiredQty - sizeof($emailIdArr);
				
				if($availNewEmailQty < ($desiredQty - sizeof($emailIdArr)))
					$limitNewSelectionQty = $availNewEmailQty;
		
				$newEmailIdArr      = array();
				$recycledEmailIdArr = array();
				
				// Get all New Emails
				if($limitNewSelectionQty > 0) {	
					
					$this->dbCmd->Query( "SELECT ID FROM emailnotifycollection WHERE BatchID = $batchId AND  Status='" . EmailNotifyCollection::STATUS_READY . "' AND  Status <> '" .  EmailNotifyCollection::STATUS_IGNORE . "' AND LastEmailDate IS NULL LIMIT $limitNewSelectionQty");
					$newEmailIdArr = $this->dbCmd->GetValueArr();
					$emailIdArr  = array_merge($emailIdArr, $newEmailIdArr);
				} 
						
				// Get Receycled Emails qty 
				if(($desiredQty - sizeof($newEmailIdArr)) > 0)
				{
					$this->dbCmd->Query( "SELECT COUNT(*) FROM emailnotifycollection WHERE BatchID = $batchId AND  Status='" . EmailNotifyCollection::STATUS_READY . "' AND (LastEmailDate < '$minEmailDate' AND LastEmailDate > '$maxEmailDate') AND (LastDomainID <> " . $this->domainID . " OR LastEmailDate < '$minDomainDate') AND Status <> '" .  EmailNotifyCollection::STATUS_IGNORE . "'");
					$availNewEmailQty = $this->dbCmd->GetValue();
					$limitRecycledSelectionQty = intval($availNewEmailQty * $emailRecyclePercentage / 100); 

					if(($limitRecycledSelectionQty + sizeof($newEmailIdArr)) > $desiredQty)
						$limitRecycledSelectionQty = $desiredQty - sizeof($newEmailIdArr);				
				}
		
				// Get Receycled Emails
				if($limitRecycledSelectionQty > 0) {
					
					$this->dbCmd->Query( "SELECT ID FROM emailnotifycollection WHERE BatchID = $batchId AND  Status='" . EmailNotifyCollection::STATUS_READY . "' AND (LastEmailDate < '$minEmailDate' AND LastEmailDate > '$maxEmailDate') AND (LastDomainID <> " . $this->domainID . " OR LastEmailDate < '$minDomainDate') AND Status <> '" .  EmailNotifyCollection::STATUS_IGNORE . "' LIMIT $limitRecycledSelectionQty");
					$recycledEmailIdArr = $this->dbCmd->GetValueArr();
					
					/*
					// Turned off for now 9/8/09
					// Never send the same message to the same email ! Doesn't remove double/same email in a second Batch
					$this->dbCmd->Query( "SELECT EmailID FROM emailnotifyhistory WHERE MessageID = " .$this->messageID);
					$messageEmailIdArr = $this->dbCmd->GetValueArr();
					$recycledEmailIdArr =  array_diff($recycledEmailIdArr, $messageEmailIdArr);
					*/

				}		
			
				$emailIdArr  = array_merge($emailIdArr, $recycledEmailIdArr);
		
				
				// Eliminate any Emails from the batch which the user has unsubscribed from (for the respective domain).
				$tempArrary = array();
				foreach($emailIdArr as $thisEmailID){
				
					$this->dbCmd->Query( "SELECT COUNT(*) FROM emailunsubscriptions WHERE DomainID = $this->domainID AND Email LIKE '".DbCmd::EscapeLikeQuery($emailColectionObj->getEmailByID($thisEmailID))."'");
				
					if($this->dbCmd->GetValue()==0)
				    	$tempArrary[] = $thisEmailID;
				}
				$emailIdArr = $tempArrary;
								
				
				
				
								
				// Remove bounced Emails
				$bouncedEmailArr = array();
				$smtpErrorBlockArr = array(451,500,501,502,503,504,551,552,553,554); 
				
				foreach ($emailIdArr AS $emailId) {
				
					$this->dbCmd->Query( "SELECT ErrorCode FROM emailnotifycollection,emailnotifysenderror WHERE (emailnotifycollection.Email = emailnotifysenderror.Email) AND emailnotifycollection.ID=$emailId");
						$errorCode = $this->dbCmd->GetValue();
						
					if(in_array($errorCode, $smtpErrorBlockArr))
						$bouncedEmailArr[] = $emailId;
				}
				
				$emailIdArr = array_diff($emailIdArr, $bouncedEmailArr);	
			}
		}

		// Rebuild array, skip "null" fields.
		$emailIdArrResult = array();
		foreach($emailIdArr AS $emailId) 	
			$emailIdArrResult[] = $emailId;
		
			
		foreach($emailIdArrResult AS $emailId) {	
			if(empty($emailId))
				throw new Exception("Illegal emailID");
		}	
				
		return $emailIdArrResult;	
	}
}
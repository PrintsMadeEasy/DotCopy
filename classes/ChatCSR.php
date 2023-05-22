<?php

class ChatCSR {
	
	private $dbCmd;
	private $csrUser;
	private static $singletonInstances = array();
	private $status;
	private $openThreadsLimit;
	private $openChatThreads;
	
	const STATUS_Available = "A";
	const STATUS_Full = "F";
	const STATUS_Offline = "O";
	
	private function __construct($csrUserID){
		$this->dbCmd = new DbCmd();

		if(!UserControl::CheckIfUserIDexists($this->dbCmd, $csrUserID))
			throw new Exception("The CSR user ID does not exist.");
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		if(!$passiveAuthObj->CheckIfUserIDisMember($csrUserID))
			throw new Exception("The CSR must be a user in the system.");
			
		$this->csrUser = intval($csrUserID);
		
		$this->dbCmd->Query("SELECT * FROM chatcsrstatus WHERE UserID=" . $this->csrUser);
		if($this->dbCmd->GetNumRows() == 0){
			
			// Create a default entry for the CSR user.
			
			$this->openChatThreads = 0;
			$this->openThreadsLimit = 1;
			$this->status = self::STATUS_Offline;
			
			// Create default setup parameters upon the first access.
			$insertArr["UserID"] = $this->csrUser;
			$insertArr["Status"] = $this->status;
			$insertArr["OpenChatThreads"] = $this->openChatThreads;
			$insertArr["OpenThreadsLimit"] = $this->openThreadsLimit;
			$this->dbCmd->InsertQuery("chatcsrstatus", $insertArr);
		}
		else{
			$row = $this->dbCmd->GetRow();
			$this->openChatThreads = $row["OpenChatThreads"];
			$this->openThreadsLimit = $row["OpenThreadsLimit"];
			$this->status = $row["Status"];
		}
	}
	

	
	// The Singleton method will cut down on object creation. A private array keeps objects for each CSR.
	/*
	 * @return ChatCSR
	 */
	static function singleton($csrUserID){
		
		if(!array_key_exists($csrUserID, self::$singletonInstances))
			self::$singletonInstances[$csrUserID] = new ChatCSR($csrUserID);
			
		return self::$singletonInstances[$csrUserID];
	}
	
	// Pass in a ChatID and it will let you know if the
	static function checkIfChatThreadWasOpenedByCsrRecently($chatId){
		
		$secondsThreadHold = 9;
		
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT UNIX_TIMESTAMP(LastOpenedByCsr) AS LastOpenedByCsr FROM chatopeninstances WHERE ChatThreadID=" . intval($chatId));
		if($dbCmd->GetNumRows() == 0)
			return false;
			
		$lastOpenedTimeStamp = $dbCmd->GetValue();
		
		if(time() - $lastOpenedTimeStamp < $secondsThreadHold)
			return true;
		else 
			return false;
	}
	
	static function markChatThreadOpenedRecently($chatId){
		
		$dbCmd = new DbCmd();
		$dbCmd->UpdateQuery("chatopeninstances", array("LastOpenedByCsr"=>time()), ("ChatThreadID=".intval($chatId)));
		
	}
	
	// Returns an array of CSR's who are online for the given domain.
	// They can be busy or waiting, just not offline.
	// Pass in an array of Chat Types if you want to see what CSR's are available with a certain "chat type" selected.
	// The use must have ALL of the chat types selected in order to find a match.
	// If you leave the "ChatTypes" an empty array... it will show all CSR's online, regardless.
	static function getCSRsOnline($domainId, array $chatTypesArr = array()){

		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT UserID FROM chatcsrstatus WHERE ( 
						Status ='".DbCmd::EscapeSQL(ChatCSR::STATUS_Available)."' OR 
						Status='".DbCmd::EscapeSQL(ChatCSR::STATUS_Full)."')");
		
		$userIdArr = self::filterCsrUserIdsByDomainPool($dbCmd->GetValueArr(), array($domainId));
		
		return self::filterUserIdsByChatTypes($domainId, $userIdArr, $chatTypesArr);
	}
	
	// Returns an array of CSR's who are available to take more chat threads.
	// Pass in an array of Chat Types if you want to see what CSR's are available with a certain "chat type" selected.
	// The use must have ALL of the chat types selected in order to find a match.
	static function getCSRsAvailable($domainId, array $chatTypesArr = array()){
				
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT UserID FROM chatcsrstatus WHERE 
						Status ='".DbCmd::EscapeSQL(ChatCSR::STATUS_Available)."'");
		
		$userIdArr = self::filterCsrUserIdsByDomainPool($dbCmd->GetValueArr(), array($domainId));
		
		return self::filterUserIdsByChatTypes($domainId, $userIdArr, $chatTypesArr);
	}
	
	// Pass in an array of UserID's.  
	// This method will return an array of UserIDs that have 
	private static function filterUserIdsByChatTypes($domainId, array $userIdArr, array $chatTypesArr){
		
		if(empty($chatTypesArr))
			return $userIdArr;
			
		if(empty($userIdArr))
			return $userIdArr;
			
		$returnArr = array();
		
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT UserID, ChatTypes FROM chatcsrsetup WHERE " . DbHelper::getOrClauseFromArray("UserID", $userIdArr) . " AND DomainID=" . intval($domainId));
		while($row = $dbCmd->GetRow()){
			$thisUserId = $row["UserID"];
			$csrTypesSavedArr = preg_split('//', $row["ChatTypes"], -1, PREG_SPLIT_NO_EMPTY);
			
			$allChatTypesFound = true;
			foreach($chatTypesArr as $thisCheckChatTypeChar){
				if(!in_array($thisCheckChatTypeChar, $csrTypesSavedArr)){
					$allChatTypesFound = false;
					break;
				}
			}
			
			if(!$allChatTypesFound)
				continue;
				
			$returnArr[] = $thisUserId;
		}
		
		return $returnArr;
	}
	
	// Returns an array of CSR's who are offline.  They may actually be chatting at the moment, but they are marked as offline.
	// Sometimes a CSR may go offline shortly before going to lunch.
	static function getCSRsOffline(array $domainIdArr){
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT UserID FROM chatcsrstatus WHERE Status ='".DbCmd::EscapeSQL(ChatCSR::STATUS_Offline)."'");
		return self::filterCsrUserIdsByDomainPool($dbCmd->GetValueArr(), $domainIdArr);
	}
	
	
	// Returns an array of CSR's who are online, but are occupied with their Max chat count.
	static function getCSRsFull(array $domainIdArr){
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT UserID FROM chatcsrstatus WHERE Status ='".DbCmd::EscapeSQL(ChatCSR::STATUS_Full)."'");
		return self::filterCsrUserIdsByDomainPool($dbCmd->GetValueArr(), $domainIdArr);
	}
	
	
	
	// Pass in an array of CSR User ID's.  It will return an array of CSR User ID's that have pemissions to the domain ID pool.  It will bassically be filtering the UserID array.
	private static function filterCsrUserIdsByDomainPool(array $csrUserIDs, array $domainIdArr){

		if(empty($domainIdArr))
			throw new Exception("You must provide at least one domain to check.");
		
		$retArr = array();
		foreach($csrUserIDs as $thisCsrId){
			
			$passiveAuthObj = Authenticate::getPassiveAuthObject();
			$csrDomainsArr = $passiveAuthObj->getUserDomainsIDs($thisCsrId);
			
			foreach($domainIdArr as $thisDomainId){
				if(!empty($thisDomainId) && in_array($thisDomainId, $csrDomainsArr)){
					$retArr[] = $thisCsrId;
					break;
				}
			}
		}
		
		return $retArr;

	}
	
	private function authenticateDomainIdForCsr($domainId){
		
		if(!preg_match("/^\d+$/", $domainId))
			throw new Exception("Domain ID is not a digit");
			
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		if(!in_array($domainId, $passiveAuthObj->getUserDomainsIDs($this->csrUser)))
			throw new Exception("The CSR can't use this domain.");
	}
	
	// Anytime a chat thread is assigned to a CSR (or a chat thread is closed), call this method to update the status.
	// If a CSR is marked as "offline"... it is sticky... until they take themselves off.
	function updateStatusOfCsr(){
		
		
		$this->dbCmd->Query("SELECT COUNT(*) FROM chatthread USE INDEX(chatthread_Status) 
							WHERE (Status='".DbCmd::EscapeSQL(ChatThread::STATUS_Active)."' OR Status='".DbCmd::EscapeSQL(ChatThread::STATUS_Waiting)."') AND CsrUserID=".intval($this->csrUser));
		
		$this->openChatThreads = $this->dbCmd->GetValue();
		
		// Don't change the status from offline unless a CSR does it by themselves, exclusively.
		if($this->status != self::STATUS_Offline){
			if($this->openChatThreads >= $this->openThreadsLimit)
				$this->status = self::STATUS_Full;
			else 
				$this->status = self::STATUS_Available;
		}

		
		// Save the status to the DB
		$updateArr["Status"] = $this->status;
		$updateArr["OpenChatThreads"] = $this->openChatThreads;
		$this->dbCmd->UpdateQuery("chatcsrstatus", $updateArr, ("UserID=" . intval($this->csrUser)));
		
	}
	
	
	
	// Supply 1 or more Domain ID's in the array.  Make sure that the CSR User loaded has permissions to all of the domains.
	// This is slightly DIFFERENT than the method ChatThread->getNumberOfCustomersWaitingAhead(). 
	// ... That method is meant to work from the customer's perspective... on a single domain (waiting in front of them) for a particular Chat Thread ID.
	function getNumberOfCustomersWaiting(array $domainIdArr){
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		$csrDomainIDsArr = $passiveAuthObj->getUserDomainsIDs($this->csrUser);
		
		foreach($domainIdArr as $thisDomainID){
			if(!in_array($thisDomainID, $csrDomainIDsArr))
				throw new Exception("CSR doesn't have permission to this domain.");
		}
		
		$this->dbCmd->Query("SELECT COUNT(*) FROM chatthread WHERE Status='" . ChatThread::STATUS_Waiting . "' AND " . DbHelper::getOrClauseFromArray("DomainID", $domainIdArr));
		return $this->dbCmd->GetValue();
	}
	
	// A CSR should mark themselves as offline when they don't want to receive any more chat thread assignments.
	// They can still continue working with any open chat threads.
	function changeStatusToOffline(){
		
		$this->status = self::STATUS_Offline;
		
		$this->dbCmd->UpdateQuery("chatcsrstatus", array("Status"=>$this->status), ("UserID=" . intval($this->csrUser)));
		
	}
	// As soon as a CSR says they are "NOT OFFLINE"... we will immediately update their status
	// They may still have active threads going on... in which case their status could snap to "full".
	// If they become available... we may want to assign them some chat threads (if there are customers currently waiting).
	function changeStatusToOnline(){
		
		// Set the status to FULL, temporarily before re-assigning the status.  We don't want a user to get assigned a chat thread within a microsecond
		$this->status = self::STATUS_Full;
		$this->dbCmd->UpdateQuery("chatcsrstatus", array("Status"=>$this->status), ("UserID=" . intval($this->csrUser)));
		$this->updateStatusOfCsr();
		
		// In case the CSR just became available... possibly assign them a customer in the queue.
		$chatThreadObj = new ChatThread();
		$chatThreadObj->assignChatThreadsToAvailableCSRs();
		
	}
	
	
	function addNewPleaseWaitMessage($domainId, $message){
		
		$domainId = intval($domainId);
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		if(!in_array($domainId, $passiveAuthObj->getUserDomainsIDs($this->csrUser)))
			throw new Exception("The CSR can't use this domain.");
			
		
		$insert["UserID"] = $this->csrUser;
		$insert["DomainID"] = $domainId;
		$insert["Message"] = $message;
		$this->dbCmd->InsertQuery("chatcsrpleasewait", $insert);	
	}
	
	function createDefaultPleaseWaitMessages($domainId){
	
		// Only do this if the user doesn't have any yet.
		$this->dbCmd->Query("SELECT COUNT(*) FROM chatcsrpleasewait WHERE UserID=" . intval($this->csrUser) . " AND DomainID=" . intval($domainId));
		if($this->dbCmd->GetValue() != 0)
			return;	
		
		$this->addNewPleaseWaitMessage($domainId, "Thank you for being patient.");
		$this->addNewPleaseWaitMessage($domainId, "Don't worry, I haven't gone anywhere.  Please give me a little more time.");
		$this->addNewPleaseWaitMessage($domainId, "I'm still working on this, thank you for being patient.");
		$this->addNewPleaseWaitMessage($domainId, "Give me a moment please.  I appreciate your understanding.");
		$this->addNewPleaseWaitMessage($domainId, "Please give me a little more time. I'm still working on this.");
		$this->addNewPleaseWaitMessage($domainId, "Please stand by.");
	}
	
	// Returns an array of messages.
	// The key to the array is the Message ID.  The value is the message.
	function getPleaseWaitMessages($domainId){
		
		$retArr = array();
		$this->dbCmd->Query("SELECT * FROM chatcsrpleasewait WHERE UserID=" . intval($this->csrUser) . " AND DomainID=" . intval($domainId));
		while($row = $this->dbCmd->GetRow())
			$retArr[$row["ID"]] = $row["Message"];
		
		return $retArr;
	}
	
	function getChatTypes($domainId){
		$allChatTypesArr = ChatThread::getChatTypesArr();
		
		$this->dbCmd->Query("SELECT ChatTypes FROM chatcsrsetup WHERE UserID=" . intval($this->csrUser) . " AND DomainID=" . intval($domainId));
		$chatTypesStr = $this->dbCmd->GetValue();
		
		$chatTypesArr = preg_split('//', $chatTypesStr, -1, PREG_SPLIT_NO_EMPTY);
		$retArr = array();
		
		foreach($chatTypesArr as $thisChatType){
			if(in_array($thisChatType, $allChatTypesArr))
				$retArr[] = $thisChatType;
		}
		return $retArr;
	}
	
	function setChatTypes($domainId, array $chatTypesArr){
		
		$this->authenticateDomainIdForCsr($domainId);
		
		$allChatTypesArr = ChatThread::getChatTypesArr();
		$filteredArr = array();
		
		foreach($chatTypesArr as $thisChatType){
			if(in_array($thisChatType, $allChatTypesArr))
				$filteredArr[] = $thisChatType;
		}
		
		$chatTypesStr = implode("", $filteredArr);
		
		if($this->checkIfCsrHasSetupDomain($domainId)){
			$this->dbCmd->UpdateQuery("chatcsrsetup", array("ChatTypes"=>$chatTypesStr), ("DomainID=" . intval($domainId) . " AND UserID=" . intval($this->csrUser)));
		}
		else{
			$this->dbCmd->InsertQuery("chatcsrsetup", array("ChatTypes"=>$chatTypesStr, "DomainID"=>$domainId, "UserID"=>$this->csrUser));
		}

		// In case the CSR can handle new types of threads that are "currently" waiting.
		$chatThreadObj = new ChatThread();
		$chatThreadObj->assignChatThreadsToAvailableCSRs();
	}
	
	function deletePleaseWaitMessage($messageID){
		
		// Query upon the CSR User ID for security.  It would prevent a CSR from deleting other's messages.
		$this->dbCmd->Query("DELETE FROM chatcsrpleasewait WHERE UserID=" . intval($this->csrUser) . " AND ID=" . intval($messageID));
	}

	
	// This let's us know if the CSR can take an additional Chat Thread.
	function isCsrAvailable(){
		if($this->status == self::STATUS_Available)
			return true;
		else
			return false;
	}
	
	
	// This let's us know if the CSR the total amount of threads assigned to them (or more) that they can handle.
	function isCsrFull(){
		if($this->openChatThreads >= $this->openThreadsLimit)
			return true;
		else
			return false;
	}
	
	// This let's us know that the CSR has marked themselves as "Offline".  That means they can't get chat threads transfered to them, no matter what.
	// CSR's can continue existing converstations while they are offline.  For example, they mark themselves as "offline" 15 mintues before lunch so that they can clear out their conversations.
	function isCsrOffline(){
		if($this->status == self::STATUS_Offline)
			return true;
		else
			return false;
	}
	
	// If this method return Zero... the CSR is booked up.
	function additionalChatThreadsCsrCanHandle(){
		
		if(!$this->isCsrAvailable())
			return 0;
			
		$diff = $this->openThreadsLimit - $this->openChatThreads;
		
		// It is possible to transfer chat threads to somone who is already full (but not if they are offline).
		// In that case, they will have more open chat threads than the limit.
		if($diff < 0)
			return 0;
		else
			return $diff;
			
	}
	
	function getChatThreadIdsOpenByCsr(){
		
		$this->dbCmd->Query("SELECT ID FROM chatthread USE INDEX(chatthread_Status) 
							WHERE (Status='".DbCmd::EscapeSQL(ChatThread::STATUS_Active)."' OR Status='".DbCmd::EscapeSQL(ChatThread::STATUS_Waiting)."') 
							AND CsrUserID=".intval($this->csrUser));
		
		$openChatThreadsArr = $this->dbCmd->GetValueArr();
		return $openChatThreadsArr;
	}
	
	// This will return an array of ThreadIDs open by the CSR which haven't received a PING yet... or the Ping is more than 25 seconds old
	// That could indicate that a chat pop-up has never been opened, or an chat thread was closed by mistake.
	// There is not a great way for browser to detect if pop-up windows are open (especially if there are multiple parent browser windows).
	function getDelinquentThreadIdsByCsr(){
		
		$delinquentSeconds = 12;
		
		// Get all delignquent Chat threads in the system to avoid doing any joins.  There shouldn't be very many open chat threads at any given time.
		$this->dbCmd->Query("SELECT ChatThreadID FROM chatopeninstances 
							WHERE (UNIX_TIMESTAMP(NOW()) - UNIX_TIMESTAMP(LastCsrPing) > $delinquentSeconds)");
		
		$delinquentChatThreadsArr = $this->dbCmd->GetValueArr();
		
		$retArr = array();
		
		// Find out which delinquent chat threads belong to the CSR.
		foreach($delinquentChatThreadsArr as $thisChatThreadID){
			$this->dbCmd->Query("SELECT ID, CsrUserID FROM chatthread WHERE ID=$thisChatThreadID");
			$row = $this->dbCmd->GetRow();
			if($row["CsrUserID"] == $this->csrUser)
				$retArr[] = $row["ID"];
		}
		
		return $retArr;	
	}
	
	// Returns a list of Chat thread IDs (with are active or waiting) that don't have an API acknowledgment from the CSR yet.
	// That is a good indication that a pop-up window for the chat thread needs to be launched as quick as possible.
	function getOpenChatThreadsByCsrWithoutAcknowledgement(){
		
		$this->dbCmd->Query("SELECT ID FROM chatthread USE INDEX(chatthread_Status) 
							WHERE (Status='".DbCmd::EscapeSQL(ChatThread::STATUS_Active)."' OR Status='".DbCmd::EscapeSQL(ChatThread::STATUS_Waiting)."') 
							AND CsrUserID=".intval($this->csrUser));
		$newChatThreadsArr = $this->dbCmd->GetValueArr();
		
		$filterArr = array();
		foreach($newChatThreadsArr as $thisChatThread){

			$this->dbCmd->Query("SELECT COUNT(*)
								FROM chatopeninstances  
								WHERE ChatThreadID=" . intval($thisChatThread) . " 
								AND FirstPingByCsrFlag=0");
			if($this->dbCmd->GetValue() == "1")
				$filterArr[] = $thisChatThread;
		}
		
		return $filterArr;
	}
	
	function getChatThreadsOpen(){
		return $this->openChatThreads;
	}
	
	function getChatThreadLimit(){
		
		return $this->openThreadsLimit;
	}
	function setChatThreadLimit($newLimit){
		
		$this->openThreadsLimit = intval($newLimit);
		
		$this->dbCmd->UpdateQuery("chatcsrstatus", array("OpenThreadsLimit"=>$this->openThreadsLimit), ("UserID=" . intval($this->csrUser)));
		$this->updateStatusOfCsr();
		
		// In case the CSR can handle more threads now... try to assign them.
		$chatThreadObj = new ChatThread();
		$chatThreadObj->assignChatThreadsToAvailableCSRs();
	}
	
	// Every CSR can define and alias name for a particular domain ID.
	// If not alias has been setup for the CSR on the domain, it will return the real CSR Name.
	function getPenName($domainId){
		
		$this->authenticateDomainIdForCsr($domainId);
			
		$this->dbCmd->Query("SELECT CsrPenName FROM chatcsrsetup USE INDEX(chatcsrsetup_UserID) 
							WHERE DomainID='".intval($domainId)."' AND UserID=".intval($this->csrUser));
		if($this->dbCmd->GetNumRows() == 0)
			return UserControl::GetNameByUserID($this->dbCmd, $this->csrUser);
			
		return $this->dbCmd->GetValue();
		
	}
	
	// Returns 0 if the CSR does not have a setup saved... or a Photo uploaded.  It is efficient to call this method.
	// Otherwise, returns the ID of the csrsetup record.  That can be used to download an image through a PHP URL.
	function getPhotoId($domainId){
		$this->authenticateDomainIdForCsr($domainId);
		
		$this->dbCmd->Query("SELECT ID, IsPhotoUploaded FROM chatcsrsetup USE INDEX(chatcsrsetup_UserID) 
							WHERE DomainID='".intval($domainId)."' AND UserID=".intval($this->csrUser));
		if($this->dbCmd->GetNumRows() == 0)
			return 0;
			
		$row = $this->dbCmd->GetRow();
		
		if($row["IsPhotoUploaded"] == "Y")
			return $row["ID"];
		else
			return 0;
	}
	
	function getGreetingMessage($domainId){
		
		$this->authenticateDomainIdForCsr($domainId);
			
		$this->dbCmd->Query("SELECT GreetingMessage FROM chatcsrsetup USE INDEX(chatcsrsetup_UserID) 
							WHERE DomainID='".intval($domainId)."' AND UserID=".intval($this->csrUser));
		return $this->dbCmd->GetValue();
	}
	
	function getSignOffMessage($domainId){
		
		$this->authenticateDomainIdForCsr($domainId);
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		if(!in_array($domainId, $passiveAuthObj->getUserDomainsIDs($this->csrUser)))
			throw new Exception("The CSR can't use this domain.");
			
		$this->dbCmd->Query("SELECT SignOffMessage FROM chatcsrsetup USE INDEX(chatcsrsetup_UserID) 
							WHERE DomainID='".intval($domainId)."' AND UserID=".intval($this->csrUser));
		return $this->dbCmd->GetValue();
	}
	
	function checkIfCsrHasSetupDomain($domainId){
		
		$this->authenticateDomainIdForCsr($domainId);
		
		$this->dbCmd->Query("SELECT COUNT(*) FROM chatcsrsetup USE INDEX(chatcsrsetup_UserID) 
							WHERE DomainID='".intval($domainId)."' AND UserID=".intval($this->csrUser));
		if($this->dbCmd->GetValue() == 0)
			return false;
		else
			return true;
	}
	
	
	function setPenName($domainId, $penName){
		
		$penName = trim($penName);
		
		if(empty($penName))
			throw new Exception("The Pen Name can not be empty.");
		
		$this->authenticateDomainIdForCsr($domainId);
		
		if($this->checkIfCsrHasSetupDomain($domainId)){
			$this->dbCmd->UpdateQuery("chatcsrsetup", array("CsrPenName"=>$penName), ("DomainID=" . intval($domainId) . " AND UserID=" . intval($this->csrUser)));
		}
		else{
			$this->dbCmd->InsertQuery("chatcsrsetup", array("CsrPenName"=>$penName, "DomainID"=>$domainId, "UserID"=>$this->csrUser));
		}		
	}
	function setGreetingMessage($domainId, $greetingMessage){
		
		$greetingMessage = trim($greetingMessage);
		
		$this->authenticateDomainIdForCsr($domainId);
		
		if($this->checkIfCsrHasSetupDomain($domainId)){
			$this->dbCmd->UpdateQuery("chatcsrsetup", array("GreetingMessage"=>$greetingMessage), ("DomainID=" . intval($domainId) . " AND UserID=" . intval($this->csrUser)));
		}
		else{
			$this->dbCmd->InsertQuery("chatcsrsetup", array("GreetingMessage"=>$greetingMessage, "DomainID"=>$domainId, "UserID"=>$this->csrUser));
		}		
	}
	function setSignOffMessage($domainId, $signOffMessage){
		
		$signOffMessage = trim($signOffMessage);
		
		$this->authenticateDomainIdForCsr($domainId);
		
		if($this->checkIfCsrHasSetupDomain($domainId)){
			$this->dbCmd->UpdateQuery("chatcsrsetup", array("SignOffMessage"=>$signOffMessage), ("DomainID=" . intval($domainId) . " AND UserID=" . intval($this->csrUser)));
		}
		else{
			$this->dbCmd->InsertQuery("chatcsrsetup", array("SignOffMessage"=>$signOffMessage, "DomainID"=>$domainId, "UserID"=>$this->csrUser));
		}		
	}
	
	// Returns null string if there is no error.  Otherwise, it returns a blank string.
	function setCsrPhoto($domainId, &$jpegBinData){
	
		if(strlen($jpegBinData) > (100 * 1024))
			return "Error uploading the Thumbnail Background Image. The file size can not be greater than 100K.";
		
		if(!empty($jpegBinData)){
			$imageFormatCheck = ImageLib::GetImageFormatFromBinData($jpegBinData);
			if($imageFormatCheck != "JPEG")
				return "Error uploading the Thumbnail Background Image. The image does not appear to be in JPEG format.";
				
			$imageDimHash = ImageLib::GetDimensionsFromImageBinaryData($jpegBinData);
			if($imageDimHash["Width"] > 200)
				return "The thumbnail width can not exceed 200 pixels.";
			if($imageDimHash["Height"] > 200)
				return "The thumbnail width can not exceed 200 pixels.";

			$this->dbCmd->UpdateQuery("chatcsrsetup", array("IsPhotoUploaded"=>"Y"), ("DomainID=" . intval($domainId) . " AND UserID=" . intval($this->csrUser)));
			$this->dbCmd->UpdateQuery("chatcsrsetup", array("CsrPhoto"=>$jpegBinData), ("DomainID=" . intval($domainId) . " AND UserID=" . intval($this->csrUser)));
		}
		else{
			$this->dbCmd->UpdateQuery("chatcsrsetup", array("IsPhotoUploaded"=>"N"), ("DomainID=" . intval($domainId) . " AND UserID=" . intval($this->csrUser)));
		}
	
		
		return null;
	}
	
}


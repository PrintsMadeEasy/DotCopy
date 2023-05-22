<?
class MemberAttendance {


	
	private $_dbCmd;
	private $_numberOfMintuesBeforeAWOL;

	private $userIDdomainCheckCache = array();
	

	private $_statusArr = array();
	

	###-- Constructor --###
	function MemberAttendance(DbCmd $dbCmd){

		$this->_dbCmd = $dbCmd;
		
		$this->_statusArr["K"] = "Working";
		$this->_statusArr["A"] = "Away";
		$this->_statusArr["L"] = "At Lunch";
		$this->_statusArr["V"] = "On Vacation";
		$this->_statusArr["F"] = "Offline";
		$this->_statusArr["W"] = "AWOL";	// Absent Without Leave
		$this->_statusArr["R"] = "AWOL Explanation";
		
		// If they have a working status and have not used the website in this amount of time... then consider them AWOL.
		$this->_numberOfMintuesBeforeAWOL = 25;
		
		$this->userIDdomainCheckCache = array();
	}
	
	// Returns False if the the person running the script doesn't have permission to look at the user.
	private function checkDomainPermissionForUserID($userID){
		
		if(isset($this->userIDdomainCheckCache[$userID]))
			return $this->userIDdomainCheckCache[$userID];
			
		$domainIDofUser = UserControl::getDomainIDofUser($userID);

		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofUser))
			$this->userIDdomainCheckCache[$userID] = false;
		else
			$this->userIDdomainCheckCache[$userID] = true;
			
		return $this->userIDdomainCheckCache[$userID];
	}
	
	

	// Pass in a start and end unix timestamp

	// It will return how many seconds that use worked within the period.
	function checkHowManySecondsUserWorked($userID, $startTime, $endTime){

		if(!$this->_checkIfDigit($userID))
			throw new Exception("Error in method checkHowManyHoursUserWorked.  The user ID is invalid.");
			
		if(!$this->_checkIfDigit($startTime) || !$this->_checkIfDigit($endTime))
			throw new Exception("Error in method checkHowManyHoursUserWorked.  One of the Timestamps is invalid, they must be numeric.");
		
		if($startTime > $endTime)
			throw new Exception("Error in method checkHowManyHoursUserWorked.  The start time stamp is greater than the ending.");
		
		if(!$this->checkDomainPermissionForUserID($userID))
			return 0;
			
		$startTimeSQL = date("YmdHis", $startTime);
		$endTimeSQL = date("YmdHis", $endTime);


		$this->_dbCmd->Query("SELECT Status, UNIX_TIMESTAMP(StatusDate) AS StatusDate FROM memberattendance 
					WHERE UserID=$userID AND (StatusDate BETWEEN $startTimeSQL AND $endTimeSQL) ORDER BY StatusDate ASC");
		
		
		// Store the results into memory because we need to use the DB handle again while looping
		$rowArr = array();
		while($row = $this->_dbCmd->GetRow())
			$rowArr[] = $row;
		
		
		$totalSecondsWorked = 0;
		$totalRecords = sizeof($rowArr);

		$timestampDifferenceStarted = null;
		$lastStatusWorkingFlag = false;
		
		$counter = 1;
		foreach($rowArr as $thisRow){
		
			$statusDate = $thisRow["StatusDate"];
			$statusChar = $thisRow["Status"];
			

			// If the status changed from Not Working to Working... then reset the Beginning Time Stamp
			// This will also happen when the first "Working" status is come across within the report period.
			if(!$lastStatusWorkingFlag && $this->checkIfStatusMeansWorking($statusChar))
				$timestampDifferenceStarted = $statusDate;


			// If the Status changed from working to NOT working... then we want to add up the time
			if($lastStatusWorkingFlag && !$this->checkIfStatusMeansWorking($statusChar))
				$totalSecondsWorked += ($statusDate - $timestampDifferenceStarted);

			// This is the last Status for the user within the period.
			if($counter == $totalRecords){
				
				// On the Last record we want to know if the User was still working. 
				// ... if so then include their time up until the end of the report period.
				if($this->checkIfStatusMeansWorking($statusChar)){

					// If the Report End date is in the future... then don't go past the present time
					if($endTime > time())
						$totalSecondsWorked += (time() - $statusDate);
					else
						$totalSecondsWorked += ($endTime - $statusDate);
				}

				// In this case we were adding up times between working statuses all the way to the end and never switched to a non-working status
				// That means we have to add up what we have so far.
				if($lastStatusWorkingFlag && $this->checkIfStatusMeansWorking($statusChar))
					$totalSecondsWorked += ($statusDate - $timestampDifferenceStarted);
			}


			
			$lastStatusWorkingFlag = $this->checkIfStatusMeansWorking($statusChar);
		
			$counter++;
		}
		
		
		// This will figure out if they are owed any time (because they were already working before the Start Period.
		$totalSecondsWorked += $this->getSecondsWorkedAtStartPeriod($userID, $startTime, $endTime);
		

		return $totalSecondsWorked;
	}
	
	
	
	// Useful for building a report Page in HTML
	// Pass in an Entry ID (unique ID to the memberattendance table).
	// It will tell you how many consecutive entry ID's come after this entry ID.  
	// If the Given Entry ID is a "Working" status it will tell you how many more "Working" statuses come after
	// ... likewise if the given Entry ID is a "non-working" status then it will tell you how many more "non-working" statuses come after.
	// If the given entry ID has a 'working' status and the following entry ID (for the same UserID) is 'not-working' then this method will return 0.
	// If there are no more consecutive entry ID's by then end of the report period then the method will return 0. 

	// (This can be useful for putting in "rowspans" for HTML)

	// *** This method counts Ascending... with the oldest status on bottom

	function getNumberOfConsecutiveSimilarStatusesAfterID($entryID, $startTime, $endTime){
	
		if(!$this->_checkIfDigit($entryID))
			throw new Exception("Error in method getNumberOfConsecutiveSimilarStatusesAfterID.  The entry ID is invalid.");
			
		if(!$this->_checkIfDigit($startTime) || !$this->_checkIfDigit($endTime))
			throw new Exception("Error in method getNumberOfConsecutiveSimilarStatusesAfterID.  One of the Timestamps is invalid, they must be numeric.");
		
		if($startTime > $endTime)
			throw new Exception("Error in method getNumberOfConsecutiveSimilarStatusesAfterID.  The start time stamp is greater than the ending.");
		
		$startTimeSQL = date("YmdHis", $startTime);
		$endTimeSQL = date("YmdHis", $endTime);

		$this->_dbCmd->Query("SELECT UserID, Status FROM memberattendance WHERE ID=$entryID AND (StatusDate BETWEEN $startTimeSQL AND $endTimeSQL)");
		
		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method getNumberOfConsecutiveSimilarStatusesAfterID.  The Entry ID does not exist or it is out of the time range.");
		
		$row = $this->_dbCmd->GetRow();
		$thisUserID = $row["UserID"];
		$thisStatus = $row["Status"];

		$statusCounter = 0;

		$this->_dbCmd->Query("SELECT Status FROM memberattendance WHERE ID > $entryID AND UserID = $thisUserID 
					AND (StatusDate BETWEEN $startTimeSQL AND $endTimeSQL) ORDER BY ID ASC");
		
		while($nextStatus = $this->_dbCmd->GetValue()){
			
			if($this->checkIfStatusMeansWorking($thisStatus)){
				if($this->checkIfStatusMeansWorking($nextStatus))
					$statusCounter++;
				else
					break;
			}
			else{
				if(!$this->checkIfStatusMeansWorking($nextStatus))
					$statusCounter++;
				else
					break;
			
			}
		}
		
		return $statusCounter;
	}
	
	

	// Works fairly similar to getNumberOfConsecutiveSimilarStatusesAfterID.
	// Pass in an Entry ID (unique ID to the memberattendance table).  This Entry ID must correspond to a "working" status or it will fail with error
	// Will return the number of seconds worked for the given User until the next "non-working" status or the end of the report period (which ever comes first)
	function getSecondsWorkedAfterWorkingID($entryID, $startTime, $endTime){

		// Let the following method do all of the error checking for us on the given parameters.
		$statusesInArow = $this->getNumberOfConsecutiveSimilarStatusesAfterID($entryID, $startTime, $endTime);

		$startTimeSQL = date("YmdHis", $startTime);
		$endTimeSQL = date("YmdHis", $endTime);


		$this->_dbCmd->Query("SELECT UserID FROM memberattendance WHERE ID=$entryID");
		$thisUserID = $this->_dbCmd->GetValue();
		

		// Add one, since the LIMIT clause in SQL should include the given entry ID.
		$statusesInArow++;
		
		// Add another one because we want to include the timestamp up until the next Status Change (when it switches from a working to a non-working status).
		$statusesInArow++;
		
		$timeSecondsCounter = 0;

		$this->_dbCmd->Query("SELECT UNIX_TIMESTAMP(StatusDate) AS StatusDate FROM memberattendance WHERE ID >= $entryID AND UserID = $thisUserID 
					AND (StatusDate BETWEEN $startTimeSQL AND $endTimeSQL) ORDER BY ID ASC LIMIT $statusesInArow");
		
		$totalNumResults = $this->_dbCmd->GetNumRows();
		
		$lastTimeStamp = null;
		while($nextStatusDate = $this->_dbCmd->GetValue()){
			
			if($lastTimeStamp)
				$timeSecondsCounter += $nextStatusDate - $lastTimeStamp;
			
			$lastTimeStamp = $nextStatusDate;
		}
		
		
		// In this case there was no "non-working" status after the final working status within the period.  
		// In this case we should include the time up until the end of the report period.
		if($totalNumResults == ($statusesInArow - 1)){
			
			// Make sure that the end of the report period doesn't go past the the present time
			if($endTime > time())
				$timeSecondsCounter += time() - $lastTimeStamp;
			else
				$timeSecondsCounter += $endTime - $lastTimeStamp;
		}
		else if($totalNumResults != $statusesInArow){
			throw new Exception("There is some type of an error in the method getSecondsWorkedAfterWorkingID.  The Number of Row Results should always be equal or 1 less than the Number or Consecutive Statuses.");
		}
			
		
	
		return $timeSecondsCounter;
	}
	
	
	// When the Report period begins, the user may have already been working
	// ... There may be a "working" status directly before the start Time
	// If so then this method will return the number of seconds the user worked between the Start of the TimeStamp until the 
	// ... first Status entry (regardless if the status entry is "working" or "not working".
	// If there was a working status before the start of the period and there are no status entries within the period... 
	// ... this method will return the number of seconds between the start and end time of the report.
	// Will return 0 seconds in any other case.
	function getSecondsWorkedAtStartPeriod($userID, $startTime, $endTime){
		
		if(!$this->checkDomainPermissionForUserID($userID))
			return 0;

		// Let the following method do all of the error checking for us on the given parameters.
		$firstEntryID = $this->getfirstEntryID($userID, $startTime, $endTime);

		$startTimeSQL = date("YmdHis", $startTime);


		// Get the last Status of the member right before the start of the report period.
		$this->_dbCmd->Query("SELECT Status FROM memberattendance 
					WHERE UserID=$userID AND StatusDate < $startTimeSQL ORDER BY ID DESC LIMIT 1");

		// In case there are no Statuses before the start of the report period then set the Status = NULL
		if($this->_dbCmd->GetNumRows() == 0)
			$previousStatusChar = null;
		else
			$previousStatusChar = $this->_dbCmd->GetValue();


		$returnSeconds = 0;

		// Get the First Entry ID within the period (regardless of what type of status that it is.
		$firstEntryID = $this->getfirstEntryID($userID, $startTime, $endTime);
		
		// If there are no entries within the period... that doesn't mean that they weren't working.
		// If they had a working status right before the start date (with no statuses during the period) then they were working for the entire report period.
		if($firstEntryID == 0){
		
			if($previousStatusChar && $this->checkIfStatusMeansWorking($previousStatusChar)){
				
				// Don't let the report period go past the present time.
				if($endTime > time())
					$returnSeconds = (time() - $startTime);
				else
					$returnSeconds = ($endTime - $startTime);
			}
		}
		else{
		
			// In this case they were working before the start of the Report period.
			// We want to include their seconds Worked as the difference between the StartTime Stamp until the Status Date of the first entry ID.
			if($previousStatusChar && $this->checkIfStatusMeansWorking($previousStatusChar)){
			
				// Get the Status Date of the First Entry ID
				$this->_dbCmd->Query("SELECT UNIX_TIMESTAMP(StatusDate) AS StatusDate 
							FROM memberattendance WHERE ID=$firstEntryID");
				$firstEntryStatusDate = $this->_dbCmd->GetValue();
				
				$returnSeconds = $firstEntryStatusDate - $startTime;
			}
		}
		
		return $returnSeconds;
	}
	

	
	// Will the first entryID within the Time Period regardless of the Status
	// If no entries exist within the period this method will return 0
	function getfirstEntryID($userID, $startTime, $endTime){
	
		if(!$this->_checkIfDigit($userID))
			throw new Exception("Error in method getfirstEntryID.  The User ID is invalid.");
			
		if(!$this->_checkIfDigit($startTime) || !$this->_checkIfDigit($endTime))
			throw new Exception("Error in method getfirstEntryID.  One of the Timestamps is invalid, they must be numeric.");
		
		if($startTime > $endTime)
			throw new Exception("Error in method getfirstEntryID.  The start time stamp is greater than the ending.");

		if(!$this->checkDomainPermissionForUserID($userID))
			return 0;

		$startTimeSQL = date("YmdHis", $startTime);
		$endTimeSQL = date("YmdHis", $endTime);


		$this->_dbCmd->Query("SELECT ID FROM memberattendance WHERE UserID=$userID AND 
					(StatusDate BETWEEN $startTimeSQL AND $endTimeSQL) ORDER BY ID ASC LIMIT 1");
		
		if($this->_dbCmd->GetNumRows() == 0)
			return 0;
		else
			return $this->_dbCmd->GetValue();

	}
	
	// Returns the next Entry ID for the corresponding UserID within the period regardless of the status
	// If no more entries exist within the period this method will return 0
	// Will fail if the entryID does not fall within the given Time Period
	function getNextEntryIDforUser($entryID, $startTime, $endTime){

		if(!$this->_checkIfDigit($entryID))
			throw new Exception("Error in method getNextEntryIDforUser.  The entry ID is invalid.");
			
		if(!$this->_checkIfDigit($startTime) || !$this->_checkIfDigit($endTime))
			throw new Exception("Error in method getNextEntryIDforUser.  One of the Timestamps is invalid, they must be numeric.");
		
		if($startTime > $endTime)
			throw new Exception("Error in method getNextEntryIDforUser.  The start time stamp is greater than the ending.");

		$startTimeSQL = date("YmdHis", $startTime);
		$endTimeSQL = date("YmdHis", $endTime);

		$this->_dbCmd->Query("SELECT UserID FROM memberattendance WHERE ID = $entryID 
					AND (StatusDate BETWEEN $startTimeSQL AND $endTimeSQL)");
		
		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method getNextEntryIDforUser.  The given entry ID does not exist.");
			
		$thisUserID = $this->_dbCmd->GetValue();
		
		$this->_dbCmd->Query("SELECT ID FROM memberattendance WHERE ID > $entryID AND UserID = $thisUserID
					AND (StatusDate BETWEEN $startTimeSQL AND $endTimeSQL) ORDER BY ID ASC LIMIT 1");
		
		if($this->_dbCmd->GetNumRows() == 0)
			return 0;
		else
			return $this->_dbCmd->GetValue();

	}
	
	
	// Pass in an Entry ID.  It will return an array of details for that row
	// Ex: array("UserID"=>"1234", "Status"=>"A", "AutoReply"=>"Y", "Message"=>"Was on the Phone", "ReturnDate"=>"UnixTimeStamp", "StatusDate"=>"UnixTimeStamp")
	function getEntryDetails($entryID){
		
		if(!$this->_checkIfDigit($entryID))
			throw new Exception("Error in method getEntryDetails.  The entry ID is invalid.");
			
		$this->_dbCmd->Query("SELECT UserID, Status, AutoReply, Message, UNIX_TIMESTAMP(StatusDate) AS StatusDate, UNIX_TIMESTAMP(ReturnDate) AS ReturnDate 
					FROM memberattendance WHERE ID = $entryID");
		
		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method getEntryDetails.  The given entry ID does not exist.");
		
		return $this->_dbCmd->GetRow();
	}
	
	

	// Returns true or false on whether the status means they are working or not working
	// There could be multiple statuses that mean someone is working... or multiple statuses that mean they are not working
	function checkIfStatusMeansWorking($statusChar){
		
		if(!isset($this->_statusArr[$statusChar]))
			throw new Exception("Error in method CheckIfStatusMeansWorking.  The status Character is not defined: " . $statusChar);
		
		$workingStatusesArr = $this->_getArrayOfWorkingStatusChars();
		
		if(in_array($statusChar, $workingStatusesArr))
			return true;
		else
			return false;

	}
	

	// Find out if the user currently has a "working" status but has not had any activity on the website for a while
	// Then we can assume that they forgot to change their status before leaving their post.
	function checkIfUserIsCurrentlyAWOL($userID){
	
		if(!$this->_checkIfDigit($userID))
			throw new Exception("Error in method checkIfUserIsCurrentlyAWOL.  The user ID is invalid.");
			
		if(!$this->checkDomainPermissionForUserID($userID))
			return false;
		
		// The Navigation History Object keeps track of what pages a user has been to.
		$navHistoryObj = new NavigationHistory($this->_dbCmd);
		
		$lastActivityTimeStamp = $navHistoryObj->getTimeStampOfLastActivity($userID);
		
		// If they don't have any activity yet then they are not AWOL
		if(empty($lastActivityTimeStamp))
			return false;
			
		$currentStatusOfUser = $this->getCurrentStatusOfUser($userID);
		
		// If they don't have a Status yet then they are not AWOL
		if(empty($currentStatusOfUser))
			return false;
			
		
		if($this->checkIfStatusMeansWorking($currentStatusOfUser)){
		
			$secondsSinceLastActivity = time() - $lastActivityTimeStamp;
			$mintuesSinceLastActivity = round($secondsSinceLastActivity / 60);
			
			if($mintuesSinceLastActivity > $this->_numberOfMintuesBeforeAWOL)
				return true;
		}
		
		return false;
			
	}
	
	
	// First check if the user with the method checkIfUserIsCurrentlyAWOL ... then call this to write to the DB
	// It will mark them as AWOL at the time the system sensed any activity from them.
	// This method will fail if the user does not currently have a "Working Status";
	function markUserAsAWOL($userID){
	
		if(!$this->_checkIfDigit($userID))
			throw new Exception("Error in method markUserAsAWOL.  The user ID is invalid.");
			
		if(!$this->checkDomainPermissionForUserID($userID))
			return;
		
		// The Navigation History Object keeps track of what pages a user has been to.
		$navHistoryObj = new NavigationHistory($this->_dbCmd);
		
		$lastActivityTimeStamp = $navHistoryObj->getTimeStampOfLastActivity($userID);
		
		$currentStatusOfUser = $this->getCurrentStatusOfUser($userID);
		
		// If they don't have a Status yet then there is no business calling them AWOL
		if(empty($currentStatusOfUser))
			throw new Exception("Error in method markUserAsAWOL.  The user does not have any Status yet.");
			
		if(!$this->checkIfStatusMeansWorking($currentStatusOfUser))
			throw new Exception("Error in method markUserAsAWOL.  If the user is not currently working then they can not be AWOL.");
		
		
		$lastActivityTimeSQL = date("YmdHis", $lastActivityTimeStamp);
		
		
		// If we are marking a user as AWOL then we will be adding a Status Date in the Past (whatever their last activity date is).
		// We need to get rid of any Statuses that may come after the AWOL Status Date (sorting by Status Date)
		// It is possible that someone could have changed their status back and forth between 2 Status Times without actually logging any activity
		// This will ensure that when someone is marked as AWOL... there will be no status's coming after it.
		$this->_dbCmd->Query("DELETE FROM memberattendance WHERE UserID=$userID AND StatusDate >= $lastActivityTimeSQL");
		
		// Keep the auto-reply sticky
		$autoReplyChar = $this->getLastAutoReplySetOnUser($userID);
		
		// If a user is "Offline" then the Autoreply should not be turned on.
		if($currentStatusOfUser == "F")
			$autoReplyChar = "N";
		
		$insertArr["Status"] = "W";
		$insertArr["AutoReply"] = $autoReplyChar;
		$insertArr["UserID"] = $userID;
		$insertArr["StatusDate"] = $lastActivityTimeSQL;
		$insertArr["ReturnDate"] = NULL;
		
		$this->_dbCmd->InsertQuery("memberattendance", $insertArr);
		
		// If they went AWOL, then they may have fogotten to set their Chat status to "offline".
		$chatCsrObj = ChatCSR::singleton($userID);
		$chatCsrObj->changeStatusToOffline();

	}
	
	function getStatusDescriptionFromChar($statusChar){
		
		if(!isset($this->_statusArr[$statusChar]))
			throw new Exception("Error in method getStatusDescriptionFromChar.  The status Character is not defined.");
		
		return $this->_statusArr[$statusChar];
	
	}
	
	// Returns the Current Status for the User or returns NULL if the Status does not exist for the user yet.
	function getCurrentStatusOfUser($userID){

		if(!$this->_checkIfDigit($userID))
			throw new Exception("Error in method getCurrentStatusOfUser.  The user ID is invalid.");
		
		if(!$this->checkDomainPermissionForUserID($userID))
			return NULL;
			
		$this->_dbCmd->Query("SELECT Status FROM memberattendance WHERE UserID=$userID ORDER BY ID DESC LIMIT 1");
		
		if($this->_dbCmd->GetNumRows() == 0)
			return NULL;
		else
			return $this->_dbCmd->GetValue();

	}
	
	
	// Works like getCurrentStatusOfUser, but returns an english description of the Status vs. the Status Characters

	function getCurrentStatusDescriptionOfUser($userID){
		
		if(!$this->checkDomainPermissionForUserID($userID))
			return "User not available";
		
		$currentStatusChar = $this->getCurrentStatusOfUser($userID);
		
		if($currentStatusChar)
			return $this->getStatusDescriptionFromChar($currentStatusChar);
		else
			return "Not Defined Yet";
	}
	
	
	// Return the most recent status date for the user.
	// return NULL if the user does not have a status yet
	function getCurrentStatusDate($userID){

		if(!$this->_checkIfDigit($userID))
			throw new Exception("Error in method getCurrentStatusDate.  The user ID is invalid.");
			
		if(!$this->checkDomainPermissionForUserID($userID))
			return NULL;
		
		$this->_dbCmd->Query("SELECT UNIX_TIMESTAMP(StatusDate) AS StatusDate FROM memberattendance 
					WHERE UserID=$userID ORDER BY ID DESC LIMIT 1");
		
		if($this->_dbCmd->GetNumRows() == 0)
			return NULL;
		else
			return $this->_dbCmd->GetValue();

	}
	


	// If the user has a return date saved it will return a Unix Timestamp... otherwise it will return NULL.
	// Also return false if their status is currently 'working'.
	function getUnixTimeStampOfReturn($userID){

		if(!$this->_checkIfDigit($userID))
			throw new Exception("Error in method getUnixTimeStampOfReturn.  The user ID is invalid.");
		
		if(!$this->checkDomainPermissionForUserID($userID))
			return NULL;
			
		$currentStatus = $this->getCurrentStatusOfUser($userID);
		if(!$currentStatus || $this->checkIfStatusMeansWorking($currentStatus))
			return NULL;
		
		$this->_dbCmd->Query("SELECT UNIX_TIMESTAMP(ReturnDate) AS ReturnDate 
					FROM memberattendance WHERE UserID=$userID ORDER BY ID DESC LIMIT 1");
		$returnDate = $this->_dbCmd->GetValue();
		
		return $returnDate;
	}

	
	// Returns true if the user is away and the AutoReply flag is turned on.
	// If the user is currently working or they don't have a status yet... then it will return false.
	function checkForAutoReplyOnUser($userID){
	
		if(!$this->_checkIfDigit($userID))
			throw new Exception("Error in method checkForAutoReplyOnUser.  The user ID is invalid.");
			
		if(!$this->checkDomainPermissionForUserID($userID))
			return false;

		$this->_dbCmd->Query("SELECT AutoReply FROM memberattendance WHERE UserID=$userID ORDER BY ID DESC LIMIT 1");
		
		if($this->_dbCmd->GetNumRows() == 0)
			return false;
	
		$autoReply = $this->_dbCmd->GetValue();
		
		if($autoReply == "Y")
			return true;
		else
			return false;
	
	}
	
	
	// Only returns TRUE if the user as a ReturnTime saved and they are late more than 15 minutes.
	function checkIfUserIsTardy($userID){
		
		if(!$this->checkDomainPermissionForUserID($userID))
			return false;
	
		$returnTime = $this->getUnixTimeStampOfReturn($userID);
		
		$bufferMintues = 15;
		
		if($returnTime){
			if(($returnTime + ($bufferMintues * 60)) < time())
				 return true;
		}
		
		return false;
	}
	
	
	function checkIfUserIsWorking($userID){
		
		if(!$this->checkDomainPermissionForUserID($userID))
			return false;
	
		$currentStatusOfUser = $this->getCurrentStatusOfUser($userID);
		
		if(!isset($this->_statusArr[$currentStatusOfUser]))
			return false;
		
		return $this->checkIfStatusMeansWorking($currentStatusOfUser);
	
	}
	
	
	// Will return a message that can be sent back to a Customer when a CSR (Customer Sales Representative) is not available.
	// If the CSR has set a return time, that will be included within the message.
	function getAutoReplyMessage($userID, $customerServiceMessageID){
	
		if(!$this->checkDomainPermissionForUserID($userID))
			return "";
		
		$currentUserStatus = $this->getCurrentStatusOfUser($userID);
		
		$csrName = UserControl::GetNameByUserID($this->_dbCmd, $userID);
		
		$domainIDofCSitem = CustService::getDomainIDfromCSitem($this->_dbCmd, $customerServiceMessageID);
		$domainWebsiteURL = Domain::getWebsiteURLforDomainID($domainIDofCSitem);
		$domainEmailConfig = new DomainEmails($domainIDofCSitem);
		
		$msg = "This is an automatic reply on behalf of " . $csrName . ".\n--------------------------------------------------------\n\n";
		$msg .= "I wanted to let you know that our system has received your message ";
		
		if($currentUserStatus == "L")
			$msg .= "but I am currently at Lunch.  ";
		else if($currentUserStatus == "A")
			$msg .= "but I am currently away from my desk.  ";
		else if($currentUserStatus == "V")
			$msg .= "but I am currently on vacation.  ";
		else
			$msg .= "but I am currently away from my desk.  ";

		
		$returnTime = $this->getUnixTimeStampOfReturn($userID);
		
		if($returnTime){
		
			$tardyFlag = $this->checkIfUserIsTardy($userID);
			
			if($tardyFlag)
				$msg .= "I had planed on returning ";
			else
				$msg .= "I plan on returning ";
			
			
			// Get a Description of the Timestamp relative to the current time.  May include Today, Tommorow, Yesterday, etc.			
			$msg .= LanguageBase::getRelativeTimeStampDesc($returnTime);
			
			if($tardyFlag)
				$msg .= "  I am still not back yet so something unexpected may have come up.";
		}
		
		$msg .= "\n\nIn order to provide you with the best possible customer service, I would like to continue working with you on this matter. ";
		$msg .= "I will get back to you soon after I return (in the order that your message was received).  ";
		$msg .= "\n\nHowever, if you require immediate attention you may click on the following link at any time.  ";
		$msg .= "This will un-assign the message from myself so that it is picked up by the next available Customer Service Representative.\n\n"; 
		$msg .= "http://".$domainWebsiteURL."/customer_service_hurry.php?id=" . $customerServiceMessageID;
		$msg .= "\n\n\nImportant:\nIf this matter is related to an order that has already been placed (not printed yet), I recommend that you click on the link above so that the issue is resolved quickly.  You may also ask to have your order placed on hold.  ";
		$msg .= "\n\nThank you,\n" . $csrName . "\n".$domainEmailConfig->getEmailNameOfType(DomainEmails::CUSTSERV)."\n";
	
		return $msg;
	}


	// Pass in a Flag of TRUE if you want to turn on the auto reply for user... false otherwise.
	// If the user is currently 'Working' then it will not update the AutoReply flag (it should always be null if they are working)
	function setAutoReplyOnUser($userID, $AutoReplyFlag){
	
		if(!$this->_checkIfDigit($userID))
			throw new Exception("Error in method setAutoReplyOnUser.  The user ID is invalid.");
		
		if(!is_bool($AutoReplyFlag))
			throw new Exception("Error in method setAutoReplyOnUser.  The Auto Reply flag must be boolean.");
		
		if(!$this->checkDomainPermissionForUserID($userID))
			return;
			
		$currentStatus = $this->getCurrentStatusOfUser($userID);
		
		if(!$currentStatus || $this->checkIfStatusMeansWorking($currentStatus))
			return;
		
		
		$this->_dbCmd->Query("SELECT MAX(ID) FROM memberattendance WHERE UserID=$userID");
		$entryID = $this->_dbCmd->GetValue();
		
		$autoReplyChar = $AutoReplyFlag ? "Y" : "N";
		$this->_dbCmd->UpdateQuery("memberattendance", array("AutoReply"=>$autoReplyChar), "ID=$entryID");
	}


	// Pass in a Message (optional) if you want it to accompany the status change.
	function changeStatusForUser($userID, $statusChar, $message = null){

		if(!isset($this->_statusArr[$statusChar]))
			throw new Exception("Error in method changeStatusForUser.  The status Character is not defined.");
		
		if(!$this->_checkIfDigit($userID))
			throw new Exception("Error in method changeStatusForUser.  The user ID is invalid.");
			
		if(!$this->checkDomainPermissionForUserID($userID))
			return;
		
		// In case they have 2 browser windows open or something... don't let the same status go in back to back.
		$currentStatus = $this->getCurrentStatusOfUser($userID);
		if($currentStatus == $statusChar)
			return;

		// Add some recent activity to their account to keep the Cron from finding them as AWOL before they access another order page or something.
		NavigationHistory::recordPageVisit($this->_dbCmd, $userID, "TimeSheet", "");

		// By default the Auto Reply should be set to whatever the Last Auto Reply was set to (sticky).
		if(!$this->checkIfStatusMeansWorking($statusChar))
			$autoReplyChar = $this->getLastAutoReplySetOnUser($userID);
		else
			$autoReplyChar = null;
		
		$insertArr["Status"] = $statusChar;
		$insertArr["AutoReply"] = $autoReplyChar;
		$insertArr["UserID"] = $userID;
		$insertArr["Message"] = $message;
		$insertArr["StatusDate"] = date("YmdHis");
		$insertArr["ReturnDate"] = NULL;
		
		$this->_dbCmd->InsertQuery("memberattendance", $insertArr);
		
		// If the user goes offline or AWOL, then we should try to change their "chat status" at the same time.
		if(!$this->checkIfStatusMeansWorking($statusChar)){
			$chatCsrObj = ChatCSR::singleton($userID);
			$chatCsrObj->changeStatusToOffline();
		}
	}
	
	
	// This method allows the AutoReply flag to remain Sticky.  This will always use what the last status was set to
	// If the status was never set in the past then it will default to "yes"
	function getLastAutoReplySetOnUser($userID){

		if(!$this->_checkIfDigit($userID))
			throw new Exception("Error in method getLastAutoReplySetOnUser.  The user ID is invalid.");
			
		$this->_dbCmd->Query("SELECT AutoReply FROM memberattendance 
					WHERE UserID=$userID AND (AutoReply='Y' OR AutoReply='N') 
					ORDER BY ID DESC LIMIT 1");
		
		if($this->_dbCmd->GetNumRows() == 0)
			return "Y";
		else
			return $this->_dbCmd->GetValue();
	
	}
	
	// Based upon the last Entry for the user... it will update the Return Date (only if they are set to Away).
	function setReturnDate($userID, $returnDateTimeStamp){
	
		if(!$this->_checkIfDigit($userID))
			throw new Exception("Error in method setReturnDate.  The User ID is invalid.");
			
		if(!$this->_checkIfDigit($returnDateTimeStamp))
			throw new Exception("Error in method setReturnDate.  The return Timestamp is invalid.");

		if(!$this->checkDomainPermissionForUserID($userID))
			return;

		$this->_dbCmd->Query("SELECT ID, Status FROM memberattendance WHERE UserID=$userID ORDER BY ID DESC LIMIT 1");
		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method setReturnDate.  An Entry does not exist for the given user.");
		$row = $this->_dbCmd->GetRow();
		
		$entryID = $row["ID"];
		$statusChar = $row["Status"];


		// Maybe the user has 2 browser windows open or something???
		// We don't want to set the "Return Date" if the status is currently "working" or some other equivalent
		if($this->checkIfStatusMeansWorking($statusChar))
			return;

		$returnMysqlStamp = date("YmdHis", $returnDateTimeStamp);
	
		$this->_dbCmd->UpdateQuery("memberattendance", array("ReturnDate" => $returnMysqlStamp), "ID=$entryID");
	}
	
	// If the user was really working... they have a chance to pass in a Message of what they were up to in order to override it
	// Override message can not be empty
	function overrideAWOLstatus($userID, $overrideMessage){

		$HoursLimit = "4";

		if(empty($overrideMessage))
			throw new Exception("Error in the method overrideAWOLstatus.  The message can not be empty.");
			
		if(!$this->checkDomainPermissionForUserID($userID))
			return;
			
		// There is a limit on the DB field
		if(strlen($overrideMessage) > 250)
			$overrideMessage = substr($overrideMessage, 0, 250);

		$currentStatus = $this->getCurrentStatusOfUser($userID);

		// Maybe they have 2 browser windows open or something?
		if($currentStatus != "W")
			return;

		// Someone may have let their browser sit overnight
		// Don't let them make a mistake and possibly add 10 hours to their Schedule
		// We won't let someone override AWOL status if it has been more than $HoursLimit hours away from their computer.
		$AWOLstatusDate = $this->getCurrentStatusDate($userID);

		if((time() - $AWOLstatusDate) > (60 * 60 * $HoursLimit)){
		
			$numberOfHours = round((time() - $AWOLstatusDate) / (60 * 60), 1);
			
			WebUtil::PrintError("It has been $numberOfHours hours since the system has detected you leaving your computer.
					Unfortunately it is not possible to override this status after the number of hours has exceeded " . $HoursLimit . ". If you 
					are paid hourly and were working this entire time... then keep track of your hours manually and forward them to your supervisor for payroll entry.
					Clicking on the 'I Forgot' link is the only way to get back to a working status again.");
		}
		
		// We are going to Change the A'W'OL status to the special status of Over'R'ided
		$this->_dbCmd->Query("SELECT ID FROM memberattendance WHERE UserID=$userID ORDER BY ID DESC LIMIT 1");
		$entryID = $this->_dbCmd->GetValue();
		
		$this->_dbCmd->UpdateQuery("memberattendance", array("Status"=>"R", "Message"=>$overrideMessage), "ID=$entryID");

	
		// Add some recent activity to their account to keep the Cron from finding them as AWOL before they access another order page or something.
		NavigationHistory::recordPageVisit($this->_dbCmd, $userID, "TimeSheet", "");
		
		// Set their status back to Wor'K'ing again.
		$this->changeStatusForUser($userID, "K");
	}
	

	
	// There could be multiple statuses that mean someone is working... or multiple statuses that mean they are not working
	function _getArrayOfWorkingStatusChars(){
		
		// A status of 'R' means that we detected the user AWOL... but then they later overrode that call and said they were working.
		// So it is not a 'real' type of status... but if you come across it you can consider that the user was working then.
		
		return array("K", "R");
	}



	// Will return true or false if digits only
	function _checkIfDigit($str){
		if(!preg_match("/^\d+$/", $str))
			return false;
		else
			return true;
			
	}

}









?>

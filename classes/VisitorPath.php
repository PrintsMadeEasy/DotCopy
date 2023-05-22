<?php

class VisitorPath {
	
	private $_dbCmd;
	
	// Holds a record of every Session ID that has been authenticated for the user.
	private $sessionPermissionCache = array();
	
	// The key is the Log ID, the value is the UnixTimestamp
	private $timeStampCacheForLogIDs = array();
	
	private $labelNamesCacheForLogIDs = array();
	private $labelDetailCacheForLogIDs = array();
	

	function __construct() {
	
		$this->_dbCmd = new DbCmd();
	}
	
	// We could do this automatically... but there might be too much overhead to crunch the database just to find out which Log entries have Sub-Labels.
	static function getExpandableLabelNames(){
		
		return array("Bandwidth", "Banner Click", "Template StartPage", "Template Category", "Template SearchEngine");
	}
	
	
	// Returns TRUE if the user has gone through the label within their session.
	// If you don't pass in a SubLabel... then the system will not care if
	static function checkIfVisitorHasGoneThroughLabel($visitLabel, $subLabel = null){
		
		$dbCmd = new DbCmd();
		
		$userSessionID = WebUtil::GetSessionID();
		
		$qry = "SELECT COUNT(*) FROM visitorlog WHERE SessionID='" . DbCmd::EscapeSQL($userSessionID) . "' 
					AND VisitLabel = '".DbCmd::EscapeSQL($visitLabel)."'";
		
		if(!empty($subLabel))
			$qry .= " AND VisitLabelDetail ='".DbCmd::EscapeSQL($subLabel)."'";
		
		$dbCmd->Query($qry);
		
		$countOfUserThroughLabel = $dbCmd->GetValue();
		
		if($countOfUserThroughLabel == 0)
			return false;
		else 
			return true;
		
	}
	
	// Returns an array of Sub Labels that go through Main Visit Label.
	// Returns an empty array if the user has traveled through the given main label.
	static function getSubLabelsInSessionGoingThroughMainLabel($visitLabel){
		
		$dbCmd = new DbCmd();
		
		$userSessionID = WebUtil::GetSessionID();
		
		$dbCmd->Query("SELECT VisitLabelDetail FROM visitorlog WHERE SessionID='" . DbCmd::EscapeSQL($userSessionID) . "' 
					AND VisitLabel = '".DbCmd::EscapeSQL($visitLabel)."'");
		
		return $dbCmd->GetValueArr();
	}
	
	// Call this static method with a description that you want to show up on the Log Graph for the page visit.
	// Sometimes you may want to include a Sub-Label you don't want to effect a global path... but may want to drill down deeper for individual sessions.
	// For example, you may want a lable to say "Template Search Engine" ... and then the Sub-Label could be the keyword that the user is searching for.
	// Pass in a HTTP Referrer Override if you want to record that for new sessions.  This can be useful if the tracking label is sent through a 1x1 pixel image (where the refering URL is the HTML page containing the image).
	// ... To get around this issue, you can use the File Proxy to embed the Referrer URL in the HTML page dynamically... and pass that through the image.
	static function addRecord($visitLabel, $subLabel = null, $overrideHttpReferrer = "auto"){
		
		$visitLabel = trim($visitLabel);
		if(empty($visitLabel))
			throw new Exception("Error adding a Vistor Path record. The visit label can not be null.");
		
		
		$userAgent = WebUtil::GetServerVar("HTTP_USER_AGENT");
		
		
		$dropIpAddresses = array("69.84.207.147");
		if(in_array(WebUtil::getRemoteAddressIp(), $dropIpAddresses)){
			if(Domain::getDomainKeyFromURL() == "PrintsMadeEasy.com")
				exit();
			else if(Domain::getDomainKeyFromURL() == "BusinessCards24.com")
				WebUtil::print404Error();
			else if(Domain::getDomainKeyFromURL() == "PostcardPrinting.com")
				WebUtil::print410Error();
			else if(Domain::getDomainKeyFromURL() == "Postcards.com")
				exit("Bad Request");
			else 
				exit("<html>\nError</html>");
		}
		
		
		// Send an alert if the Googlebot is coming from a non-google IP address
		if(strripos($userAgent, "Googlebot") !== false && strripos(WebUtil::getRemoteAddressIp(), "66.249") === false){
			
			// There are so many robots pretending to be the Googlebot that we are just going to drop them for now.
			// For now we are just dropping PME
			if(in_array(Domain::getDomainIDfromURL(), array(1)))
				exit();

			// Keep track of a timestamp on a temp file.  This will keep us from overloading the webmaster email box.
			$googleBotTimeNotifier = Constants::GetTempDirectory() . "/GoogleBotTracker";
			$readyToNotifiyFlag = true;
			
			if(!file_exists($googleBotTimeNotifier)){
				$fp = fopen($googleBotTimeNotifier, "w"); fwrite($fp, "0"); fclose($fp);
			}
			else{
				$lastNotifyDate = filemtime($googleBotTimeNotifier);
				
				// Don't send more than 1 email per hour
				if((time() - $lastNotifyDate) < 3600){
					$readyToNotifiyFlag = false;
				}
				else{
					$fp = fopen($googleBotTimeNotifier, "w"); fwrite($fp, "0"); fclose($fp);
				}
			}
				
			//if($readyToNotifiyFlag)
			//	WebUtil::WebmasterError("Possible Proxy Website. Check out the IP Address " . WebUtil::getRemoteAddressIp() . ". Affiliates can proxy our websites and cause rankings to drop in the search engines because of duplicate content.");
		}
	
		$userAgentFiltersArr = WebUtil::getSpiderUserAgentsArr();
		// Don't log requests from certain user agents.
		foreach($userAgentFiltersArr as $thisFilterCheck){
			if(strripos($userAgent, $thisFilterCheck) !== false)
				return;
		}

		$userSessionID = WebUtil::GetSessionID();

		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		// Don't log entries from Admins
		if(!Constants::GetDevelopmentServer() && $passiveAuthObj->CheckIfBelongsToGroup("MEMBER"))
			return;
		
		
		// Convert Double Quotes to Single Quotes for the Dot Language.
		if(!empty($subLabel)){
			$subLabel = preg_replace('/"/', "'", $subLabel);
			$subLabel = preg_replace('/|/', "", $subLabel);
		}
		
			
		// Find out if we have already established the session details.
		// There is no reason in wasting space and storing the User Agent, etc. with every single log request.
		// The Session IDs are unique enough to give us a good correlation.
		$dbCmd = new DbCmd();
		
		$dbCmd->Query("SELECT UserID FROM visitorsessiondetails WHERE SessionID='" . DbCmd::EscapeSQL($userSessionID) . "'" );
		$recordAlreadyExists = $dbCmd->GetNumRows();
		$userIDfromSessionRecord = $dbCmd->GetValue();
		
		if(!$recordAlreadyExists){
			
			// See if we can figure out a UserID (if they are logged in)
			$userID = 0;
			if($passiveAuthObj->CheckIfLoggedIn())
				$userID = $passiveAuthObj->GetUserID();
				
			// See if we can figure out where the user came from
			$httpReferer = "";
			
			if($overrideHttpReferrer == "auto")
				$httpReferer = WebUtil::FilterData(WebUtil::GetServerVar('HTTP_REFERER'), FILTER_SANITIZE_STRING_ONE_LINE);
			else
				$httpReferer = $overrideHttpReferrer;
				
				
			// We want to determine what type of a referal it is.
			// O = Organic Search Engine with keywords we can infer.
			// B = Banner Click referal... such as Google Adwords
			// L = Linked from somewhere, but we can't categorize it.
			// U = Unknown... because it was not a banner click, and there was no referrer present.
			// S = Sales Rep Link
			
			// If we can gather keywords out of a URL... then we can assume it came from a major search engine.
			$referrerKeywords = WebUtil::getKeywordsFromSearchEngineURL($httpReferer);
			
			if($visitLabel == "Banner Click")
				$referralCode = "B";
			else if($visitLabel == "Sales Rep Link")
				$referralCode = "S";
			else if(!empty($referrerKeywords))
				$referralCode = "O";
			else if(!empty($httpReferer))
				$referralCode = "L";
			else 
				$referralCode = "U";

			
			
			// See the the User has a Previous Session ID.  We try to store that inside a cookie on the user's machine to figure out how many times they return to the website.
			$previousSessionID = WebUtil::GetCookie("PreviousSession");
			
			// Now set the Previous Session Cookie to our current Sessions ID for the next possible visit.
			WebUtil::SetCookie("PreviousSession", $userSessionID, 360);
				
			$userAgent = WebUtil::GetServerVar("HTTP_USER_AGENT");
				
			$insertArr = array();
			$insertArr["UserID"] = $userID;
			$insertArr["SessionID"] = $userSessionID;
			$insertArr["PreviousSessionID"] = $previousSessionID;
			$insertArr["IPaddress"] = WebUtil::getRemoteAddressIp();
			$insertArr["UserAgent"] = $userAgent;
			$insertArr["ReferralType"] = $referralCode;
			$insertArr["RefURL"] = $httpReferer;  	// We only care about the Reffer URL when the session starts... after that they are already in the site.
			$insertArr["DomainID"] = Domain::getDomainIDfromURL();
			$insertArr["DateStarted"] = time();
			$insertArr["LocationID"] = MaxMind::getLocationIdFromIPaddress(WebUtil::getRemoteAddressIp());
			$insertArr["ISPname"] = MaxMind::getIspFromIPaddress(WebUtil::getRemoteAddressIp());
		
			$dbCmd->InsertQuery("visitorsessiondetails", $insertArr);
			
			
			// If we discovered that we arrived from an organic search engine... the we want to list this as the first Node in the users session path.
			if($referralCode == "O"){
				
				$searchEngineDomain = WebUtil::getDomainFromURL($httpReferer);
				
				$insertArr = array();
				$insertArr["SessionID"] = $userSessionID;
				$insertArr["VisitLabel"] = "Organic Link";
				$insertArr["VisitLabelDetail"] = $searchEngineDomain . ": " . $referrerKeywords;
				$insertArr["Date"] = time();
			
				$dbCmd = new DbCmd();
				$dbCmd->InsertQuery("visitorlog", $insertArr);
			}
			
			
		}
		else{
			
			// Maybe the user has created an account already.
			// If they are logged in, but we don't have a user ID associated with the session, then update the DB.
			if(empty($userIDfromSessionRecord) && $passiveAuthObj->CheckIfLoggedIn()){
				$dbCmd->UpdateQuery("visitorsessiondetails", array("UserID"=>$passiveAuthObj->GetUserID()), "SessionID='".DbCmd::EscapeSQL($userSessionID)."'");
			}
		}
		
		// Keep track of the last page visit, so that we can analyze Session Duration.
		$dbCmd->UpdateQuery("visitorsessiondetails", array("DateLastAccess"=>time()), "SessionID='".DbCmd::EscapeSQL($userSessionID)."'");
		
		// If the user hits the "Order Placed" node... then update the Session details so that we know the user converted.
		if($visitLabel == "Order Placed")
			$dbCmd->UpdateQuery("visitorsessiondetails", array("OrderPlaced"=>"Y"), "SessionID='".DbCmd::EscapeSQL($userSessionID)."'");
		
		
		
		$insertArr = array();
		$insertArr["SessionID"] = $userSessionID;
		$insertArr["VisitLabel"] = $visitLabel;
		$insertArr["VisitLabelDetail"] = $subLabel;
		$insertArr["Date"] = time();
	
		$dbCmd = new DbCmd();
		$dbCmd->InsertQuery("visitorlog", $insertArr);
	}
	
	// Returns an array of previous session ID's that are daisy chained to this one (with cookies)
	// Returns an empty array if there are no previous sessions linked to this one.
	// The first array element is closest (in time) to the SessionID passed into this method.  The larger elements in the array go farther back in time.
	// The Key to the Array is the "Session ID"
	// The Value to the array is the UnixTimestamp when it was started.
	function getPreviousSessionIDsWithDates($sessionID){
		
		$this->ensureSessionPermission($sessionID);
		
		$previousSessionIDarr = array();
		
		$nextSessionIDtoGet = $sessionID;
		
		$infinteLoopPrevention = 0;
		
		while($nextSessionIDtoGet){
			$this->_dbCmd->Query("SELECT PreviousSessionID, UNIX_TIMESTAMP(DateStarted) AS SessionDate FROM visitorsessiondetails WHERE 
								SessionID='" . DbCmd::EscapeSQL($nextSessionIDtoGet) . "'");
			
			if($this->_dbCmd->GetNumRows() == 0)
				break;
				
			$row = $this->_dbCmd->GetRow();
			
			$previousSessionID = $row["PreviousSessionID"];
			$previousSessionDate = $row["SessionDate"];
			
			if(empty($previousSessionID))
				break;
				
			if($previousSessionID == $nextSessionIDtoGet){
				WebUtil::WebmasterError("Infinite Loop detected on SessionID: $previousSessionID");
				$nextSessionIDtoGet = null;
				break;
			}
				
			$previousSessionIDarr[$previousSessionID] = $previousSessionDate;
			$nextSessionIDtoGet = $previousSessionID;
			
			$infinteLoopPrevention++;
			if($infinteLoopPrevention > 50000)
				throw new Exception("Infinite Loop Detected in getPreviousSessionIDs() after 50000 iterations.");
		}
		
		return $previousSessionIDarr;
	}
	
	function getDomainID($sessionID){

		$this->ensureSessionPermission($sessionID);
		
		$this->_dbCmd->Query("SELECT DomainID FROM visitorsessiondetails WHERE 
							SessionID='" . DbCmd::EscapeSQL($sessionID) . "'");
		
		return $this->_dbCmd->GetValue();
	}
	
	function getUserAgent($sessionID){

		$this->ensureSessionPermission($sessionID);
		
		$this->_dbCmd->Query("SELECT UserAgent FROM visitorsessiondetails WHERE 
							SessionID='" . DbCmd::EscapeSQL($sessionID) . "'");
		
		return $this->_dbCmd->GetValue();
	}
	
	// Returns 0 if the users did not resister
	function getUserIDofSession($sessionID){

		$this->ensureSessionPermission($sessionID);
		
		$this->_dbCmd->Query("SELECT UserID FROM visitorsessiondetails WHERE 
							SessionID='" . DbCmd::EscapeSQL($sessionID) . "'");
		
		return $this->_dbCmd->GetValue();
	}
	
	// Returns IP Address matching when the session was started.
	function getIPaddressOfSession($sessionID){

		$this->ensureSessionPermission($sessionID);
		
		$this->_dbCmd->Query("SELECT IPaddress FROM visitorsessiondetails WHERE 
							SessionID='" . DbCmd::EscapeSQL($sessionID) . "'");
		
		return $this->_dbCmd->GetValue();
	}
	
	// Returns TRUE if the user placed an order during the session.
	function checkIfOrderPlaced($sessionID){

		$this->ensureSessionPermission($sessionID);
		
		$this->_dbCmd->Query("SELECT OrderPlaced FROM visitorsessiondetails WHERE 
							SessionID='" . DbCmd::EscapeSQL($sessionID) . "'");
		
		if($this->_dbCmd->GetValue() == "Y")
			return true;
		else
			return false;
	}
	

	// Returns one of the following codes.
	// O = Organic Search Engine with keywords we can infer.
	// B = Banner Click referal... such as Google Adwords
	// L = Linked from somewhere, but we can't categorize it.
	// U = Unknown... because it was not a banner click, and there was no referrer present.
	// S = Sales Rep Link
	function getReferrer($sessionID){

		$this->ensureSessionPermission($sessionID);
		
		$this->_dbCmd->Query("SELECT RefURL FROM visitorsessiondetails WHERE 
							SessionID='" . DbCmd::EscapeSQL($sessionID) . "'");
		
		return $this->_dbCmd->GetValue();
	}
	
	function getReferralType($sessionID){

		$this->ensureSessionPermission($sessionID);
		
		$this->_dbCmd->Query("SELECT ReferralType FROM visitorsessiondetails WHERE 
							SessionID='" . DbCmd::EscapeSQL($sessionID) . "'");
		
		return $this->_dbCmd->GetValue();
	}

	
	function getSessionStartTime($sessionID){
		
		$this->ensureSessionPermission($sessionID);
		
		$this->_dbCmd->Query("SELECT UNIX_TIMESTAMP(DateStarted) As Date FROM visitorsessiondetails WHERE 
							SessionID='" . DbCmd::EscapeSQL($sessionID) . "'");
		
		return $this->_dbCmd->GetValue();
	}
	
	function getSessionEndTime($sessionID){
		
		$this->ensureSessionPermission($sessionID);
		
		$this->_dbCmd->Query("SELECT UNIX_TIMESTAMP(DateLastAccess) As Date FROM visitorsessiondetails WHERE 
							SessionID='" . DbCmd::EscapeSQL($sessionID) . "'");
		
		return $this->_dbCmd->GetValue();
	}
	
	// Returns the number of seconds that the session lasted for.
	function getSessionDuration($sessionID){
		return $this->getSessionEndTime($sessionID) - $this->getSessionStartTime($sessionID);
	}
	
	
	// Returns the first "Label Description" of the first banner click in the User's session.
	// Returns NULL if there is no banner click.
	function getFirstBannerClickCode($sessionID){

		$this->ensureSessionPermission($sessionID);
		
		$this->_dbCmd->Query("SELECT VisitLabelDetail FROM visitorlog WHERE 
							SessionID='" . DbCmd::EscapeSQL($sessionID) . "' AND VisitLabel=\"Banner Click\" 
							ORDER BY ID ASC LIMIT 1");
		
		return $this->_dbCmd->GetValue();
	}
	
	
	// Returns a list of Visit Labels for the given session (in order from beggining to end).
	// The key is the Visit ID (from the DB) ... and the value is the Page Vist label.
	// Pass in the second parameter FALSE if you want the Label Details concatenated after the Main Label Name with a dash and 2 spaces (" - ");
	function getVisitLabels($sessionID, $conflateVisitLabel = true){
		
		$this->ensureSessionPermission($sessionID);
		
		$this->_dbCmd->Query("SELECT ID, VisitLabel, VisitLabelDetail FROM visitorlog WHERE 
							SessionID='" . DbCmd::EscapeSQL($sessionID) . "'
							ORDER BY ID ASC");
		$retArr = array();
		
		while($row = $this->_dbCmd->GetRow()){
			$labelDesc = $row["VisitLabel"];
			
			if(!$conflateVisitLabel && !empty($row["VisitLabelDetail"]))
				$labelDesc .= " - " . $row["VisitLabelDetail"];
				
			$retArr[$row["ID"]] = $labelDesc;
		}
			
		return $retArr;
	}
	
	function getVisitLabelDesc($visitLogID, $conflateVisitLabel = true){
		
		$this->cacheVistLabelNamesInSession($visitLogID);
		
		if(!$conflateVisitLabel)
			$labelDetail = $this->labelDetailCacheForLogIDs[$visitLogID];
		else
			$labelDetail = null;
		
		if(!empty($labelDetail))
			return $this->labelNamesCacheForLogIDs[$visitLogID] . " - " . $labelDetail;
		else
			return $this->labelNamesCacheForLogIDs[$visitLogID];
	}
	
	
	// Returns the Unix Timestamp of the visit ID.
	// This will cache the timestamps of all Page Visit IDs belonging to the current session (upon first access) because it assumes you will to cycle through all of the times (which would access the DB a lot).
	function getVisitTimestamp($visitLogID){
		
		$visitLogID = intval($visitLogID);
		
		if(array_key_exists($visitLogID, $this->timeStampCacheForLogIDs))
			return $this->timeStampCacheForLogIDs[$visitLogID];
			
		// Since we don't have the record cached, wipe out our array to keep the objects memory lower.
		// The log ID must belong to another session.
		$this->timeStampCacheForLogIDs = array();
		
		// Get the Session ID that belongs to the Log ID.
		$this->_dbCmd->Query("SELECT SessionID FROM visitorlog WHERE ID=$visitLogID");
		$visitSessionID = $this->_dbCmd->GetValue();
		
		$this->ensureSessionPermission($visitSessionID);

		$this->_dbCmd->Query("SELECT ID, UNIX_TIMESTAMP(Date) AS Date FROM visitorlog WHERE 
							SessionID='" . DbCmd::EscapeSQL($visitSessionID) . "' ORDER BY ID ASC");
		
		while($row = $this->_dbCmd->GetRow())
			$this->timeStampCacheForLogIDs[$row["ID"]] = $row["Date"];
			
		if(!array_key_exists($visitLogID, $this->timeStampCacheForLogIDs))
			throw new Exception("Error in method getVisitTimestamp with LogID: $visitLogID");
		
		return $this->timeStampCacheForLogIDs[$visitLogID];
	}
	
	
	// Pass in a visitorlog ID... and it will tell you how long the user waited at that page before going to another page.
	// Returns 0 if the page visit is the last entry within the user's session.
	function getVisitIdDuration($visitLogID, $snapToNextLabelFlag = true){

		$visitLogID = intval($visitLogID);
		
		// Get our start timestamp of the given visitLogID
		// This will also cache the results of all Lod IDs for the corresponding Session (in $this->timeStampCacheForLogIDs).
		$startTimeStamp = $this->getVisitTimestamp($visitLogID);
		
		// Extract the Log ID's out of the Hash Keys. This will give us a the ability step ahead to the next entry by advancing the "Keys Index".
		$logIDsArr = array_keys($this->timeStampCacheForLogIDs);
		
		$lastVisitLogID = end($logIDsArr);
		
		// Find out if this is the last log entry in the session... if so, it is impossible to know how long they stayed on the page.
		if($lastVisitLogID == $visitLogID)
			return 0;
		
		// If we are not snapping to the next non-matching label, then let's return the difference of the very next log ID.
		if(!$snapToNextLabelFlag){
			
			// We know there are other log ID's to follow.  Otherwise we would have already returned Zero (above).
			$indexOfLogID = array_search($visitLogID, $logIDsArr);
			$nextLogId = $logIDsArr[$indexOfLogID + 1];
			
			return $this->getVisitTimestamp($nextLogId) - $startTimeStamp;
		}

			
		// Find out the next Log ID 
		$nextLogIdWithoutMatchingLabel = $this->getNextVisitIdWithoutMatchingLabel($visitLogID);

		// If we can't find a Label Change (in the session chain) going forward... then it means the last Visit Log ID in the session must have a matching label to our given VisitLogID from the method call.
		if($nextLogIdWithoutMatchingLabel == 0)
			return $this->getVisitTimestamp($lastVisitLogID) - $startTimeStamp;
		else
			return $this->getVisitTimestamp($nextLogIdWithoutMatchingLabel) - $startTimeStamp;
		
	}
	
	// Returns 1 if this this is the last log ID in the chain with back-to-back label names.
	function getCountOfMatchingVisitLabelsAhead($visitLogID){
		
		$this->cacheVistLabelNamesInSession($visitLogID);
		$returnCount = 1;
		$logIDfound = false;
		
		foreach($this->labelNamesCacheForLogIDs as $thisLogID => $thisLabelName){
			
			if($thisLogID == $visitLogID){
				$logIDfound = true;
				continue;
			}
				
			if($logIDfound){
				if($thisLabelName != $this->labelNamesCacheForLogIDs[$visitLogID])
					return $returnCount;
				else
					$returnCount++;
			}
		}
		
		return $returnCount;
	}
	
	// For example, if someone is browsing templates... and they keep going to "next page", "next page", etc.
	// This will give you the Log ID (in the same session) that comes next in the chain without a matching Label Name. (e.g. Edit Arwork). 
	// Will return Zero if this is the last visitLogID in the session.
	// Also will returns Zero if there is not a different "label visit" following the matching label chain.
	function getNextVisitIdWithoutMatchingLabel($visitLogID){
	
		$this->cacheVistLabelNamesInSession($visitLogID);
		$logIDfound = false;
		
		foreach($this->labelNamesCacheForLogIDs as $thisLogID => $thisLabelName){
			
			if($thisLogID == $visitLogID){
				$logIDfound = true;
				continue;
			}
				
			if($logIDfound){
				if($thisLabelName != $this->labelNamesCacheForLogIDs[$visitLogID])
					return $thisLogID;
			}
		}
		
		return 0;
	}
	
	// Returns the next Visit ID in the session.
	// Returns Zero if it is the last one.
	function getNextVisitLogIDInSession($visitLogID){
		
		$this->cacheVistLabelNamesInSession($visitLogID);
		
		$visitLogIDsInSession = array_keys($this->labelNamesCacheForLogIDs);

		$indexOfVisitID = array_search($visitLogID, $visitLogIDsInSession);
			
		if($indexOfVisitID == (sizeof($visitLogIDsInSession) - 1))
			return 0;
		else 
			return $visitLogIDsInSession[$indexOfVisitID + 1];
	}
	
	
	
	// Pass in a Visit Log ID.
	// It will find out the session belonging to it... and cache all visit label names within the session.
	private function cacheVistLabelNamesInSession($visitLogID){
		
		// Find out if it is already cached.
		if(in_array($visitLogID, array_keys($this->labelNamesCacheForLogIDs)))
			return;
		
		$this->labelNamesCacheForLogIDs = array();
		$this->labelDetailCacheForLogIDs = array();
		
		$this->_dbCmd->Query("SELECT SessionID FROM visitorlog WHERE ID=" . intval($visitLogID));
		$sessionIDfromLogEntry = $this->_dbCmd->GetValue();
		
		if(empty($sessionIDfromLogEntry))
			throw new Exception("Error in cacheVistLabelNamesInSession. The Visit Log ID.");
		
		$this->_dbCmd->Query("SELECT ID, VisitLabel, VisitLabelDetail FROM visitorlog WHERE SessionID='".DbCmd::EscapeSQL($sessionIDfromLogEntry)."' ORDER BY ID ASC");
		while($row = $this->_dbCmd->GetRow()){
			$this->labelNamesCacheForLogIDs[$row["ID"]] = $row["VisitLabel"];
			$this->labelDetailCacheForLogIDs[$row["ID"]] = $row["VisitLabelDetail"];
		}
	}
	
	
	// Make sure that the Domain ID is in the User's avaialable Domain IDs
	private function ensureSessionPermission($sessionID){
		
		// Disable for now... need to speed things up a bit.
		// I don't think that I am using this methods unless they are found within the Users "Selected Domain ID's"
		// Please have a look at this if we ever open the codebase up to resellers or 3rd parties.
		
		return;
	/*	
		if(in_array($sessionID, $this->sessionPermissionCache))
			return;
			
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		$this->_dbCmd->Query("SELECT COUNT(*) FROM visitorsessiondetails WHERE 
							SessionID='" . DbCmd::EscapeSQL($sessionID) . "'
							AND " . DbHelper::getOrClauseFromArray("DomainID", $passiveAuthObj->getUserDomainsIDs()));
		
		$validCount = $this->_dbCmd->GetValue();
		
		if($validCount)
			$this->sessionPermissionCache[] = $sessionID;
		else
			throw new Exception("Error in ensureSessionPermission. The Session ID is not valid for the domain, or the session ID does not exist.");
	*/
	}
	
	
}

?>
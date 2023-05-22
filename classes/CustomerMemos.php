<?


class CustomerMemos {

	private $_dbCmd;
	private $_customerID;


	// Match our fields in the DB
	private $_CreatedByUserID;
	private $_Memo;
	private $_Date;
	private $_CustomerReaction;


	

	
	function __construct(DbCmd $dbCmd){

		$this->_dbCmd = $dbCmd;
	}
	
	
	#-----   BEGIN Static Functions -----------#
	
	// returns an HTML description for the Link to the memo
	// It does not provide a hyper link.  It will be bold if there are memos for the customer.
	static function getLinkDescription(DbCmd $dbCmd, $customerID, $smallLinkFlag){
	
		$custMemosObj = new CustomerMemos($dbCmd);
		$custMemosObj->setCustomerID($customerID);
		
		$memoCount = $custMemosObj->getCountOfMemosForCustomer();
		
		if($memoCount > 0)
			$recentMemoCount = $custMemosObj->getCountOfMemosWithinXdays(30);
		else
			$recentMemoCount = 0;
		
		if($memoCount > 0){
			
			if($recentMemoCount > 0){
				if($smallLinkFlag)
					return "Mem(<font style='font-size:12px;'>$recentMemoCount</font> /$memoCount)";
				else
					return "Memos (<font style='font-size:15px;'>$recentMemoCount</font> /$memoCount)";
			}
			else{
				if($smallLinkFlag)
					return "Mem($memoCount)";
				else
					return "Memos ($memoCount)";
			}
		}
		else{
			return "Memos";
		}
	
	}
	
	// When someone opens up a pop-up to enter a Customer memo it should aquire a lock.
	static function lockMemoForViewing(DbCmd $dbCmd, $customerID, $userID){
		
		WebUtil::EnsureDigit($customerID);
		WebUtil::EnsureDigit($userID);
		
		$domainIDofCustomer = UserControl::getDomainIDofUser($customerID);
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofCustomer))
			throw new Exception("Error Locking the Customer ID for memos.");
		
		$dbCmd->InsertQuery("customermemoslocker", array("UserID"=>$userID, "CustomerID"=>$customerID, "LockTime"=>time()));

	}
	
	// When someone closes a pop-up window for a memo or saves a message then it should clear the lock.
	// It will only clear the oldes lock... in case the user has opened 2 memo windows for the same user.
	static function clearLockOnMemo(DbCmd $dbCmd, $customerID, $userID){
		
		WebUtil::EnsureDigit($customerID);
		WebUtil::EnsureDigit($userID);
		
		$domainIDofCustomer = UserControl::getDomainIDofUser($customerID);
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofCustomer))
			throw new Exception("Error clearing the Lock for the Customer ID for memos.");
		
		$dbCmd->Query("SELECT MAX(ID) FROM customermemoslocker WHERE UserID=$userID AND CustomerID=$customerID");
		$maxIDfromUser = $dbCmd->GetValue();
		
		if($maxIDfromUser)
			$dbCmd->Query("DELETE FROM customermemoslocker WHERE ID=$maxIDfromUser");
	}
	
	
	// Will tell you if someone else has a viewing lock on the memo by returning the UserID owning the lock... will not return your own ID.
	// Returns an Array of UserID's becaue there can be multiple users with a lock.
	// Locks expire after 1 hour... in case someone leaves their browser window open for a long time.
	static function getUserLocks(DbCmd $dbCmd, $customerID, $userID){
		
		WebUtil::EnsureDigit($customerID);
		WebUtil::EnsureDigit($userID);
		
		$domainIDofCustomer = UserControl::getDomainIDofUser($customerID);
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofCustomer))
			throw new Exception("Error getting User locks for the memos.");
		
		$oneHourAgoMysql = date("YmdHis", mktime ((date("G") - 1), date("i"), 1, date("n"),(date("j")),date("Y"))); 

		$retArr = array();
		$dbCmd->Query("SELECT DISTINCT (UserID) FROM customermemoslocker WHERE UserID != $userID AND CustomerID=$customerID AND LockTime > $oneHourAgoMysql");
		
		while($userLocked = $dbCmd->GetValue())
			$retArr[] = $userLocked;
		
		return $retArr;
	}
	
	
	
	// Pass in an array of codes and it will return the count for each array key
	// Regardless of who created the memo... get a count between dates.
	// In case the Reaction Type is a "SalesForceMemo" type... then it will only show time that the last SalesForceMemo was added...
	// ... and in case there is a SalesForceMemo status added more recently than the "$endTime" of this report period.  This method will return a Zero count.
	// For example... if a Sales Person "closed a deal" on a customer... it would be missleading to have that customer showing up as "Still interested" within any report or (time range).
	static function getMemoCountArrByTimeRange(DbCmd $dbCmd, $custReactionArr, $startTime, $endTime) {
		
		if(sizeof($custReactionArr) == 0)
			throw new Exception("Error with Customer reaction in method getMemoCountArrByTimeRange");

		$startTimeMysql = date("YmdHis", $startTime); 
		$endTimeMysql   = date("YmdHis", $endTime); 

		$retArr = array();

		// Select all codes and take care of the "lastSalesForceReaction" rule
		$OrClause = "(";
		foreach($custReactionArr as $thisCode) {

			$lastSalesForceReactionFilter = "";  // default must be empty
			if(in_array($thisCode, CustomerMemos::getSalesForceReactionTypes()))	
				$lastSalesForceReactionFilter = "LastSalesForceReaction = 'Y' AND ";

			$OrClause .= "(" . $lastSalesForceReactionFilter . "CustomerReaction = '" . $thisCode . "') OR ";
			
			// Default to a Zero count.
			$retArr[$thisCode] = 0;
		}
		


		// Get rid of the last OR
		$OrClause  = substr($OrClause, 0, -4) . ")";

		$domainObj = Domain::singleton();

		$dbCmd->Query("SELECT DISTINCT(CustomerReaction) AS SMCode 
					FROM customermemos INNER JOIN users ON users.ID = customermemos.CustomerUserID 
					WHERE ". $OrClause . " AND ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
					AND customermemos.Date BETWEEN '$startTimeMysql' AND '$endTimeMysql'");

		$customerReactionCodes = array();
		while($thisReaction = $dbCmd->GetValue()){
			$customerReactionCodes[] = $thisReaction;
		}
		

		foreach($customerReactionCodes as $thisReactionCode){
		
			$lastSalesForceReactionFilter = ""; 
			if(in_array($thisReactionCode, CustomerMemos::getSalesForceReactionTypes()))	
				$lastSalesForceReactionFilter = " AND LastSalesForceReaction = 'Y' ";
			
			$dbCmd->Query("SELECT COUNT(*) 
						FROM customermemos INNER JOIN users ON users.ID = customermemos.CustomerUserID 
						WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
						$lastSalesForceReactionFilter AND CustomerReaction='$thisReactionCode'
						AND customermemos.Date BETWEEN '$startTimeMysql' AND '$endTimeMysql'");
						
			$retArr[$thisReactionCode] = $dbCmd->GetValue();
		}

		return $retArr;
	}
	
	
	
	
	static function getReactionDescription($reactionChar){
	
		if($reactionChar == "N")
			return "No Reaction";
		else if($reactionChar == "H")
			return "Happy Customer";
		else if($reactionChar == "U")
			return "Upset";
		else if($reactionChar == "M")
			return "Mood Changed";
		else if($reactionChar == "A")
			return "Angry";
		else if($reactionChar == "C")
			return "Never Phone Customer";
		else if($reactionChar == "T")
			return "Not Interested";
		else if($reactionChar == "S")
			return "Left Message";
		else if($reactionChar == "W")
			return "Warm";
		else if($reactionChar == "P")
			return "Looks Good";
		else if($reactionChar == "V")
			return "Very Interested";
		else if($reactionChar == "D")
			return "Deal Closed";
		else if($reactionChar == "W")
			return "Warm";
		else
			throw new Exception("Error in method getReactionDescription... The Reaction Char has not been defined yet.");
	
	}
	
	static function getCustomerServiceReactionTypes(){
		return array("N", "H", "U", "M", "A");
	}
	static function getSalesForceReactionTypes(){
		return array("C", "T", "S", "W", "P", "V", "D");
	}
	
	
	#-----   END Static Functions -----------#
	
	
	
	
	
	
	
	
	// Returns the User ID who entered the memo (administrator)
	function getCreatedByUserID(){
		$this->_ensureMemoIDisLoaded();
		return $this->_CreatedByUserID;
	}
	function getMemo(){
		$this->_ensureMemoIDisLoaded();
		return $this->_Memo;
	}
	// return date of memo in Unix Timestamp
	function getDate(){
		$this->_ensureMemoIDisLoaded();
		return $this->_Date;
	}


	// Will return a string like "Today", "Yesterday", "This Week", etc.
	// Pass in HTML = true if you want it to be color coded
	function getDescriptOfTime($html = true){
		
		$retStr = "";
	
		$secondsInDay = 60*60*24;
		$secondsAgoMemoEntered = time() - $this->_Date;
		$secondsSinceMidnight = time() - mktime (0,0,1,date("n"),(date("j")),date("Y"));

		$daysAgoMemoEntered = ($secondsAgoMemoEntered -  $secondsSinceMidnight) / $secondsInDay;
		
		if($daysAgoMemoEntered <= 0)
			$retStr = "Today";
		else if($daysAgoMemoEntered <= 1)
			$retStr = "Yesterday";
		else if($daysAgoMemoEntered <= 8)
			$retStr = "Within a Week";
		else if($daysAgoMemoEntered <= 32)
			$retStr = "Within a Month";
		else if($daysAgoMemoEntered <= 361)
			$retStr = "Within a Year";
		else
			$retStr = "Greater Than 1 Year";

		if(!$html)
			return $retStr;
		
		if($retStr == "Today" || $retStr == "Yesterday")
			return "<font color='#bb0000' style='font-size:15px;'><b>$retStr</b></font>";
		else if($retStr == "This Week")
			return "<font color='#990000' style='font-size:14px;'><b>$retStr</b></font>";
		else if($retStr == "This Month")
			return "<font color='#660000' style='font-size:13px;'><b>$retStr</b></font>";
		else
			return "<font color='#333333'><b>$retStr</b></font>";
	
	}


	
	function setCustomerID($customerID){
		WebUtil::EnsureDigit($customerID);
		
		$domainIDofCustomer = UserControl::getDomainIDofUser($customerID);
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofCustomer))
			throw new Exception("Error setting the Customer ID in memos.");
		
		$this->_dbCmd->Query("SELECT COUNT(*) FROM users WHERE ID=$customerID");
		if($this->_dbCmd->GetValue() == 0)
			throw new Exception("The Customer ID does not exist within CustomerMemos");
		
		$this->_customerID = $customerID;
	}
	
	
	
	function enterCustomerMemo($memoText, $userIDwhoIsInserting, $customerReaction){
	
		WebUtil::EnsureDigit($userIDwhoIsInserting);
		
		if(strlen($customerReaction) != 1)
			throw new Exception("Error entering a Customer Memo... The customer Reaction must be exactly 1 character.");
		
		$this->_ensureCustomerIDisSet();
		
		
		// If we are adding an new SalesForceMemo... then we want set any old entries to "N"ot the latest (for that customer)
		// The new entry will be the latest one.
		if(in_array($customerReaction, CustomerMemos::getSalesForceReactionTypes())){
			
			$this->_dbCmd->UpdateQuery("customermemos", array("LastSalesForceReaction"=>"N"), "CustomerUserID ='$this->_customerID'");
		
			$lastSalesForceReaction = "Y";
		}
		else{
			$lastSalesForceReaction = "N";
		}

		
		$insertArr["Memo"] = $memoText;
		$insertArr["Date"] = date("YmdHis");
		$insertArr["CreatedByUserID"] = $userIDwhoIsInserting;
		$insertArr["CustomerReaction"] = $customerReaction;
		$insertArr["CustomerUserID"] = $this->_customerID;
		$insertArr["LastSalesForceReaction"] = $lastSalesForceReaction; 
		
		$this->_dbCmd->InsertQuery("customermemos", $insertArr);
	}



	function getCountOfMemosForCustomer(){
	
		$this->_ensureCustomerIDisSet();
	
		$this->_dbCmd->Query("SELECT COUNT(*) FROM customermemos WHERE CustomerUserID=" . $this->_customerID);
		return $this->_dbCmd->GetValue();
	}
	
	
	function getCountOfMemosWithinXdays($days){
	
		$this->_ensureCustomerIDisSet();
		
		$days = intval($days);
		
		$daysAgoMYSQL = date("YmdHis", mktime (0,0,1,date("n"),(date("j") - $days),date("Y"))); 
	
		$this->_dbCmd->Query("SELECT COUNT(*) FROM customermemos WHERE CustomerUserID=" . $this->_customerID . " AND Date > '" . $daysAgoMYSQL . "'");
		return $this->_dbCmd->GetValue();
	}
	
	
	// Returns an array of CustomerMemo ID's with the most recent being at the top
	function getMemosIDs(){
	
		$this->_ensureCustomerIDisSet();
		
		$retArr = array();
	
		$this->_dbCmd->Query("SELECT ID FROM customermemos WHERE CustomerUserID=" . $this->_customerID . " ORDER BY DATE DESC");
		while($x = $this->_dbCmd->GetValue())
			$retArr[] = $x;
		
		return $retArr;
	}
	
	// returns a Hash of details for the given CustomerMemo ID
	function loadMemoByID($memoID){
	
		WebUtil::EnsureDigit($memoID);
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
	
		$this->_dbCmd->Query("SELECT CreatedByUserID, Memo, CustomerReaction, UNIX_TIMESTAMP(Date) AS UnixDate FROM customermemos 
						INNER JOIN users ON users.ID = customermemos.CustomerUserID WHERE customermemos.ID=$memoID 
						AND " . DbHelper::getOrClauseFromArray("DomainID", $passiveAuthObj->getUserDomainsIDs()));
		
		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Error loading Memo by ID, it does not exist.");
			
		$row = $this->_dbCmd->GetRow();
		

		$this->_CreatedByUserID = $row["CreatedByUserID"];
		$this->_Memo = $row["Memo"];
		$this->_Date = $row["UnixDate"];
		$this->_CustomerReaction = $row["CustomerReaction"];
	
	}
	
	
	// Will return a block of HTML of memos for the Customer ID that has already been set
	// If no memos exist yet, then it will return a Description "no memos entered"
	function getHTMLblockOfMemosForCustomer(){
	
		$this->_ensureCustomerIDisSet();

		$memoIDarr = $this->getMemosIDs();

		$retHTML = '
			<table width="100%" border="0" cellspacing="0" cellpadding="0">
			<tr><td bgcolor="#CCCCCC">
			<table width="100%" border="0" cellspacing="1" cellpadding="4">
			';
			
		foreach($memoIDarr as $memoID){
			
			$this->loadMemoByID($memoID);
			
			$retHTML .= '
			  <tr>
			    <td bgcolor="#DDDDEE">
			    
			    <table width="100%" border="0" cellspacing="0" cellpadding="0">
			    <tr>
			    <td  width="52%" class="SmallBody">
			    By: <b>' . WebUtil::htmlOutput(UserControl::GetNameByUserID($this->_dbCmd, $this->_CreatedByUserID)) . '</b> on ' . date("D, M j Y, g:i A", $this->_Date) . '&nbsp;&nbsp;&nbsp;&nbsp;
			    </td>
			    <td width="24%" align="right" class="SmallBody" nowrap>' . $this->getCustomerReactionHTML($this->_CustomerReaction) . '</td>
			    <td width="24%" align="right" class="SmallBody" nowrap><nobr>' . $this->getDescriptOfTime() . '</nobr></td>
			    </tr>
			    </table>
			    
			    </td>
			  </tr>
			   <tr>
			    <td class="Body" bgcolor="#DDDDDD">' . preg_replace("/\n/", "<br>", WebUtil::htmlOutput($this->_Memo)) . '</td>
			  </tr>
			   <tr>
			    <td class="SmallBody" bgcolor="#EEEEEE">&nbsp;</td>
			  </tr>
			 ';
		}
			  
		$retHTML .= '
			</table>
			</td></tr>
			</table>
			';
		
		if(empty($memoIDarr))
			$retHTML = '<table width="100%" border="0" cellspacing="0" cellpadding="4"><tr><td class="Body"><b>No Customer Memos Yet.</b></td></tr></table>';
		
		return $retHTML;
	}
	
	
	function getCustomerReactionHTML($reactionChar){
		
		if($reactionChar == "N")
			return "&nbsp;";
		else if($reactionChar == "C")
			return "<img src='./images/memos_NoPhone.gif' align='absmiddle' alt='Do Not Call'> <b>Don't Call</b>";
		else if($reactionChar == "H")
			return "<img src='./images/memos_Happy.gif' align='absmiddle' alt='Happy Customer'>";
		else if($reactionChar == "U")
			return "<img src='./images/memos_Upset.gif' align='absmiddle' alt='Upset'>";
		else if($reactionChar == "A")
			return "<img src='./images/memos_Angry.gif' align='absmiddle' alt='Angry'>";
		else if($reactionChar == "M")
			return "<img src='./images/memos_MoodChanged.gif' align='absmiddle' alt='Mood Changed From Bad to Good'>";
		else if($reactionChar == "T")
			return "<img src='./images/memos_NotInterested.gif' align='absmiddle' alt='Not Interested'>";
		else if($reactionChar == "P")
			return "<img src='./images/memos_SoundsGood.gif' align='absmiddle' alt='Sale Looks Good'>";
		else if($reactionChar == "V")
			return "<img src='./images/memos_VeryInterested.gif' align='absmiddle' alt='Very Interested'>";
		else if($reactionChar == "D")
			return "<img src='./images/memos_DealClosed.gif' align='absmiddle' alt='Sales: Deal Complete'>";
		else if($reactionChar == "W")
			return "<img src='./images/memos_Warm.gif' align='absmiddle' alt='Sales: Warm'>";
		else if($reactionChar == "S")
			return "<img src='./images/memos_LeftMessage.gif' align='absmiddle' alt='Sales: Left a Message'>";
		else
			return "&nbsp;";
	
	}



	
	#-- BEGIN  Public Methods -------------#
	
	
	function _ensureCustomerIDisSet(){
		if(!$this->_customerID)
			throw new Exception("The customer ID must be set first within CustomerMemos");
	}

	function _ensureMemoIDisLoaded(){
		if(!$this->_CreatedByUserID)
			throw new Exception("The Memo ID must be set first within CustomerMemos");
	}


}



?>

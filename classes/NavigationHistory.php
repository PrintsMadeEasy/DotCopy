<?

// This class is meant to keep track of certain visits to varias pages.
// It is not meant to keep a log of every URL that a person visits.  That would be too much memory for the database over time and uneccessary
// It is not meant to record critical items like "refunds", or changes to orders.  That type of information should be logged in its own user interface and DB if it is really important anyway
// This is meant to keep track of important pages that people visit along with a Time Stamp.  We can get them back to a recently viewed order page... and roughly calculate how much time they have been spending on the website.
class NavigationHistory {
	
	private $_dbCmd;
	

	private $_userID;
	private $_pageName;
	private $_identifier;
	private $_date;
	

	// contains an array of Page Descriptions that are valid for recording.
	private $_pageNamesArr = array();
	

	// Constructor
	function NavigationHistory(DbCmd $dbCmd){

		$this->_dbCmd = $dbCmd;
		
		
		// Setup the Page Names and their descriptions.
		$this->_pageNamesArr = array(	"Order"=>"Order Pages", 
						"Project"=>"Project Pages", 
						"TempTxtFld"=>"Saved Template Text Fields", 
						"CSreply"=>"CS Replies", 
						"ProofPage"=>"Proofing Pages", 
						"ArtPreview"=>"Artwork Previews", 
						"UserAccnt"=>"User Accounts",
						"UserLylty"=>"User Loyalty",
						"UserID"=>"User ID",
						"UserEmail"=>"User Search (Email)",
						"UserPhone"=>"User Search (Phone)",
						"UserName"=>"User Search (Name)",
						"Memo"=>"Customer Memos",
						"MsgView"=>"Messages Viewed",
						"MsgSent"=>"Messages Sent",
						"Template"=>"Artwork Templates",
						"TimeSheet"=>"Status Change",
						"ChatView"=>"Chat View",
						"ChatNew"=>"Chat New",
						"ChatEnd"=>"Chat End",
						"ChatTrans"=>"Chat Transfered Away",
						"ChatAssign"=>"Chat Assigned",
						"CntTemp"=>"Content Template",
						"CntItem"=>"Content Item",
						"CntCat"=>"Content Category"
						);
	}
	



	
	// A static method that records the visit to the given "Page Name".  That page name must be defined within the array _pageNamesArr
	// The Unique ID could be anything that identifies the particular page visit... Most commonly it would be an ID number.
	// However, the Identifier could also be null... like the "Home Page" or something that doesn't have an ID associated with its view.
	// Will not record duplicates if they immediately follow each other.   

	// If a duplicate is ignored... it will not update the Timestamp of the Last Visit either since it may be far in the past.  That could throw off previous time-clock reports which have been completed, or verified, etc.

	static function recordPageVisit(DbCmd $dbCmd, $userID, $pageName, $pageIdentifier){
		
		$navHistoryObj = new NavigationHistory($dbCmd);

		if(!$navHistoryObj->_checkIfDigit($userID))
			throw new Exception("Error in method recordPageVisit, UserID is Invalid");

		if(!isset($navHistoryObj->_pageNamesArr[$pageName]))
			throw new Exception("Error in method recordPagevisit, The given Page Name has not been defined yet.");
			
		if(strlen($pageName) > 10)
			throw new Exception("Error in function recordPagevisit.  The size of the Page Name can not exceed 10 characters.");

		if(strlen($pageIdentifier) > 50)
			throw new Exception("Error in function recordPagevisit.  The size of the Identifier can not exceed 10 characters.");

		
		$navHistoryObj->_dbCmd->Query("SELECT PageName, Identifier FROM navigationhistory WHERE UserID=$userID ORDER BY ID DESC LIMIT 1");
		if($navHistoryObj->_dbCmd->GetNumRows() != 0){
			
			$row = $navHistoryObj->_dbCmd->GetRow();
			
			$lastPageName = $row["PageName"];
			$lastPageIdentify = $row["Identifier"];
			
			
			// The only time that duplicates should be allowed to be filed is when it is a time sheet adjustment.
			// They may have gone AWOL and then overridden 2 times in a row.
			if($pageName != "TimeSheet"){
				if($lastPageName == $pageName && $lastPageIdentify == $pageIdentifier)
					return;
			}
		}
		
		$insertArr["PageName"] =  $pageName;
		$insertArr["Identifier"] =  $pageIdentifier;
		$insertArr["UserID"] =  $userID;
		$insertArr["Date"] =  date("YmdHis");
		
		$navHistoryObj->_dbCmd->InsertQuery("navigationhistory", $insertArr);   
	
	}
	
	
	// returns an array of ID numbers that can be passed into the method getDescriptionOfPageVisit or whatever.
	// $maxResults must be a number, but it can not be greater than 3000.
	// pass in $onlyPageName if you want to restrict the results to only that page type.
	function getLastPageVisitsIDs($userID, $maxResults, $onlyPageName = ""){

		if(!$this->_checkIfDigit($userID))
			throw new Exception("Error in method getLastPageVisitsIDs, UserID is Invalid");

		if(!$this->_checkIfDigit($maxResults))
			throw new Exception("Error in method getLastPageVisitsIDs, the Max ID must be a number");
		
		if($maxResults > 3000)
			throw new Exception("Error in method getLastPageVisitsIDs, the Max ID can not be greater than 3000");
	
		// Make sure an Admin can't view the Domain of another employee.
		$domainObj = Domain::singleton();
		$query = "SELECT navigationhistory.ID FROM navigationhistory INNER JOIN users on users.ID = navigationhistory.UserID 
				WHERE users.ID=$userID AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs());
		
		// "All" means don't restrict by page name.
		if($onlyPageName == "all")
			$onlyPageName = "";
		
		if(!empty($onlyPageName)){
			if(!isset($this->_pageNamesArr[$onlyPageName]))
				throw new Exception("Error in method getLastPageVisitsIDs, The given Page Name has not been defined yet.");
			
			$query .= " AND PageName='$onlyPageName' ";
		}
		
		$query .= " ORDER BY ID DESC LIMIT $maxResults";
		
		$retArr = array();
		
		$this->_dbCmd->Query($query);
		
		while($thisID = $this->_dbCmd->GetValue())
			$retArr[] = $thisID;
		
		return $retArr;
	}
	
	
	// Returns a Unix Timestamp of the last activity for this user.
	// If the user has no activity yet then it returns NULL
	function getTimeStampOfLastActivity($userID){
	
		if(!$this->_checkIfDigit($userID))
			throw new Exception("Error in method getTimeStampOfLastActivity, UserID is Invalid");
		
		$this->_dbCmd->Query("SELECT UNIX_TIMESTAMP(Date) AS Date FROM navigationhistory WHERE UserID=$userID ORDER BY ID DESC LIMIT 1");
		
		if($this->_dbCmd->GetNumRows() == 0)
			return NULL;
		else
			return $this->_dbCmd->GetValue();
	}
	
	function loadNavHistoryID($navHistoryID){
	
		if(!preg_match("/^\d+$/", $navHistoryID))
			throw new Exception("Error in function loadNavHistoryID.  Illegal Nav History ID");
		
		$domainObj = Domain::singleton();
		$this->_dbCmd->Query("SELECT NH.PageName, NH.Identifier, NH.UserID, UNIX_TIMESTAMP(NH.Date) AS Date FROM navigationhistory AS NH
						INNER JOIN users on users.ID = NH.UserID 
						WHERE NH.ID=$navHistoryID AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));

		
		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method loadNavHistoryID... The NavHistoryID does not exist.");
		
		$row = $this->_dbCmd->GetRow();
		
		$this->_pageName = $row["PageName"];
		$this->_identifier = $row["Identifier"];
		$this->_userID = $row["UserID"];
		$this->_date = $row["Date"];
	}
	
	// Must call the method loadNavHistoryID before calling this method.
	function getTimeStampOfVisit(){
		if(empty($this->_pageName))
			throw new Exception("Error in Method getTimeStampOfVisit.  The Nav History ID must be loaded before calling this method.");
		
		return $this->_date;
	}
	
	
	

	// Returns a UnixTimeStamp of the last visit by a user to a certain page.
	// Return Null if the user has never visted the page.
	function getDateOfLastVisit($userID, $pageName, $identifier){
	
		$userID = intval($userID);
		$identifier = intval($identifier);

		if(!in_array($pageName, array_keys($this->_pageNamesArr)))
			throw new Exception("Error in function getLastDateOfVisit. Illegal page name");
			
		$this->_dbCmd->Query("SELECT UNIX_TIMESTAMP(Date) AS Date FROM navigationhistory WHERE PageName=\"" . DbCmd::EscapeSQL($pageName) . "\" AND Identifier=" . $identifier . " AND UserID=" . $userID . " ORDER BY ID DESC LIMIT 1");
		if($this->_dbCmd->GetNumRows() == 0)
			return null;
		else
			return $this->_dbCmd->GetValue();
	}
	
	
	// Must call the method loadNavHistoryID before calling this method.
	// Returns string describing the Page visit by the NavigationHistory unique ID number
	function getDescriptionOfPageVisit(){
		
		$domainObj = Domain::singleton();
		
		if(empty($this->_pageName))
			throw new Exception("Error in Method getDescriptionOfPageVisit.  The Nav History ID must be loaded before calling this method.");
		
		// Build a Description for the given entry.  You may want to look up company Names belonging to an order screen or whatever.
		if($this->_pageName == "Order"){
			
			if(!$this->_checkIfDigit($this->_identifier))
				return "Bad Order ID";
			
			$this->_dbCmd->Query("SELECT UserID FROM orders WHERE ID=" . $this->_identifier . " 
						AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));
			
			if($this->_dbCmd->GetNumRows() == 0)
				return "Order Identifier Does not exist.";
			
			$customerID = $this->_dbCmd->GetValue();

			$customerName = $this->getNameDescriptionFromUserID($customerID);
		
			return "Order #" . Order::GetHashedOrderNo($this->_identifier) . " ........... " . $customerName;
		}
		else if($this->_pageName == "Project" || $this->_pageName == "ProofPage" || $this->_pageName == "ArtPreview"){
			
			if(!$this->_checkIfDigit($this->_identifier))
				return "Bad Project ID";
			
			$this->_dbCmd->Query("SELECT UserID FROM orders INNER JOIN projectsordered AS PO 
						ON PO.OrderID = orders.ID WHERE PO.ID=" . $this->_identifier . " 
						AND " . DbHelper::getOrClauseFromArray("PO.DomainID", $domainObj->getSelectedDomainIDs()));
			
			if($this->_dbCmd->GetNumRows() == 0)
				return "Project Identifier Does not exist.";
				
			$customerID = $this->_dbCmd->GetValue();
			
			$customerName = $this->getNameDescriptionFromUserID($customerID);
		
			if($this->_pageName == "Project")
				return "Project Page P" . $this->_identifier . " ........... " . $customerName;
			else if($this->_pageName == "ProofPage")
				return "Proofing Page A" . $this->_identifier . " ........... " . $customerName;
			else if($this->_pageName == "ArtPreview")
				return "Artwork Preview A" . $this->_identifier . " ........... " . $customerName;
			else
				throw new Exception("Undefined page name for Project Number in method getDescriptionOfPageVisit.");
		}
		else if($this->_pageName == "CSreply"){
		
			if(!$this->_checkIfDigit($this->_identifier))
				return "Bad Customer Service ID";
			
			$this->_dbCmd->Query("SELECT CustomerName FROM csitems WHERE ID=" . $this->_identifier . "
									AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));
						
			if($this->_dbCmd->GetNumRows() == 0)
				return "CS Item Identifier Does not exist.";
				
			$customerName = $this->_dbCmd->GetValue();
			
			if(empty($customerName))
				$customerName = "(Customer Name not Defined)";
			
			return "Replied to $customerName on CS Item #" . $this->_identifier;
		}
		else if($this->_pageName == "UserAccnt"){
		
			if(!$this->_checkIfDigit($this->_identifier))
				return "Bad User Account ID";
			
			$customerName = $this->getNameDescriptionFromUserID($this->_identifier);
			
			return "User Account Screen ...........  $customerName";
		}
		else if($this->_pageName == "UserLylty"){
		
			if(!$this->_checkIfDigit($this->_identifier))
				return "Bad User Account ID";
			
			$customerName = $this->getNameDescriptionFromUserID($this->_identifier);
			
			return "User Loyalty Program ...........  $customerName";
		}
		else if($this->_pageName == "UserID"){
		
			if(!$this->_checkIfDigit($this->_identifier))
				return "Bad User Account ID";
			
			$customerName = $this->getNameDescriptionFromUserID($this->_identifier);
			
			return "User Details ...........  $customerName";
		}
		else if($this->_pageName == "UserEmail"){
			
			return "User Search (Email) ...........  " . $this->_identifier;
		}
		else if($this->_pageName == "UserPhone"){
			
			return "User Search (Phone) ...........  " . $this->_identifier;
		}
		else if($this->_pageName == "UserName"){
			
			return "User Search (Name) ...........  " . $this->_identifier;
		}
		else if($this->_pageName == "TempTxtFld"){
			
			return "Saved Text Fields on Template";
		}
		else if($this->_pageName == "Memo"){
		
			if(!$this->_checkIfDigit($this->_identifier))
				return "Bad Memo ID";
			
			$customerName = $this->getNameDescriptionFromUserID($this->_identifier);
			
			return "Customer Memo ........... $customerName";
		}
		else if($this->_pageName == "MsgView" || $this->_pageName == "MsgSent"){
		
			if(!$this->_checkIfDigit($this->_identifier))
				return "Bad Msg View ID";
			
			$this->_dbCmd->Query("SELECT Subject FROM msgsthreads WHERE ThreadID=" . $this->_identifier);
						
			if($this->_dbCmd->GetNumRows() == 0)
				return "Msg Thread Identifier Does not exist.";
			
			$msgSubject = $this->_dbCmd->GetValue();
			
			if($this->_pageName == "MsgSent")
				return "Message Sent ........... Subject: $msgSubject";
			else
				return "Message Viewed ........... Subject: $msgSubject";
				
		}
		else if($this->_pageName == "Template"){
			// template ID corresponds to a Search Engine or a Category ID
			// The identifier has a color separating the view type from the category ID
			$TemplatePartsArr = split(":", $this->_identifier);
		
			if(!is_array($TemplatePartsArr) || sizeof($TemplatePartsArr) != 2 || !$this->_checkIfDigit($TemplatePartsArr[1]))
				return "Bad Template ID";

			return "Template ID #" . $TemplatePartsArr[1];
		}
		else if($this->_pageName == "TimeSheet"){
			return "Status Change";
		}
		else if($this->_pageName == "CntTemp"){
	
			return "Content Template ...........  ID:" . $this->_identifier;
		}
		else if($this->_pageName == "ChatNew"){
	
			return "Chat New Session ...........  ID:" . $this->_identifier;
		}
		else if($this->_pageName == "ChatView"){
	
			return "Viewed Chat Thread ...........  ID:" . $this->_identifier;
		}
		else if($this->_pageName == "ChatEnd"){
	
			return "Chat End Session ...........  ID:" . $this->_identifier;
		}
		else if($this->_pageName == "ChatTrans"){
	
			return "Transfered Chat To Another CSR ...........  ID:" . $this->_identifier;
		}
		else if($this->_pageName == "ChatAssign"){
	
			return "Chat Assigned by Another CSR ...........  ID:" . $this->_identifier;
		}
		else if($this->_pageName == "CntItem"){
		
			return "Content Item ID: " . $this->_identifier;
		}
		else if($this->_pageName == "CntCat"){
			
			return "Content Category ID: \"" . $this->_identifier . "\"";
		}
		else{
			throw new Exception("Error in Method getDescriptionOfPageVisit for ID: " . $this->_identifier . " The Page Name has not been defined: " . $this->_pageName);
		}
	}



	// Must call the method loadNavHistoryID before calling this method.
	// Returns string that is the URL to get back to the given Page Record
	// May return a blank string if it is not meant to be linked to.
	function getHyperLinkOfPageVisit(){
		
		if(empty($this->_pageName))
			throw new Exception("Error in Method getHyperLinkOfPageVisit.  The Nav History ID must be loaded before calling this method.");
		
		// Build a Description for the given entry.  You may want to look up company Names belonging to an order screen or whatever.
		// Add a little bit of jibberish to the front of the URL to make sure their browser thinks it is a unique URL... so they can see what link they clicked on.
		if($this->_pageName == "Order"){
			return "ad_order.php?pv=1&orderno=" . $this->_identifier;
		}
		else if($this->_pageName == "Project"){
			return "ad_project.php?pv=1&projectorderid=" . $this->_identifier;
		}
		else if($this->_pageName == "ProofPage"){
			return "ad_proof_artwork.php?pv=1&projectid=" . $this->_identifier;
		}
		else if($this->_pageName == "ArtPreview"){
			return "ad_proof_artwork.php?pv=1&projectid=" . $this->_identifier;
		}
		else if($this->_pageName == "CSreply"){
			return "ad_cs_home.php?pv=1&view=search&csitemid=" . $this->_identifier;
		}
		else if($this->_pageName == "UserAccnt"){
			return "ad_users_account.php?pv=1&returl=ad_pagevisits.php&retdesc=Page+Visits+History+Page&view=general&customerid=" . $this->_identifier;
		}
		else if($this->_pageName == "UserLylty"){
			return "ad_loyalty_program.php?pv=1&returl=ad_pagevisits.php&retdesc=Page+Visits+History+Page&view=general&customerid=" . $this->_identifier;
		}
		else if($this->_pageName == "UserID"){
			return "ad_users_search.php?pv=1&customerid=" . $this->_identifier;
		}
		else if($this->_pageName == "UserEmail"){
			return "ad_users_search.php?pv=1&email=" . $this->_identifier;
		}
		else if($this->_pageName == "UserPhone"){
			return "ad_users_search.php?pv=1&phone=" . $this->_identifier;
		}
		else if($this->_pageName == "UserName"){
			return "ad_users_search.php?pv=1&username=" . $this->_identifier;
		}
		else if($this->_pageName == "Memo"){
			return "ad_users_search.php?pv=1&customerid=" . $this->_identifier;
		}
		else if($this->_pageName == "MsgView" || $this->_pageName == "MsgSent"){
			return "ad_message_display.php?thread=" . $this->_identifier;
		}
		else if($this->_pageName == "CntTemp"){
			return "ad_contentTemplate.php?pv=1&viewType=edit&editContentTemplateID=" . $this->_identifier;
		}
		else if($this->_pageName == "CntItem"){
			return "ad_contentItem.php?pv=1&viewType=edit&editContentItemID=" . $this->_identifier;
		}
		else if($this->_pageName == "CntCat"){
			return "ad_contentCategory.php?pv=1&viewType=edit&editCategoryID=" . $this->_identifier;
		}
		else if($this->_pageName == "ChatNew"){
			return "javascript:Chat(\"" . $this->_identifier . "\");";
		}
		else if($this->_pageName == "ChatView"){
			return "javascript:Chat(\"" . $this->_identifier . "\");";
		}
		else if($this->_pageName == "ChatEnd"){
			return "javascript:Chat(\"" . $this->_identifier . "\");";
		}
		else if($this->_pageName == "ChatTrans"){
			return "javascript:Chat(\"" . $this->_identifier . "\");";
		}
		else if($this->_pageName == "ChatAssign"){
			return "javascript:Chat(\"" . $this->_identifier . "\");";
		}
		else if($this->_pageName == "Template"){
		
			// template ID corresponds to a Search Engine or a Category ID
			// The identifier has a color separating the view type from the category ID
			$TemplatePartsArr = split(":", $this->_identifier);
		
			if(!is_array($TemplatePartsArr) || sizeof($TemplatePartsArr) != 2 || !$this->_checkIfDigit($TemplatePartsArr[1]))
				return "";
			
			// The URL for Tempalates needs to be improved a bit.  Don't worry about linking yet.
			return "";
			
		}
		else if($this->_pageName == "TimeSheet"){
			return "";
		}
		else if($this->_pageName == "TempTxtFld"){
			return "";
		}
		else{
			throw new Exception("Error in Method getHyperLinkOfPageVisit for ID: " . $this->_identifier . " The Page Name has not been defined: " . $this->_pageName);
		}
	}


	
	// The Key is the Unique Page Identifier and the value is a description.
	function getArrayNavPages(){
		return $this->_pageNamesArr;
	}


	// Will return true or false if the ID is digits only
	function _checkIfDigit($identifier){
		if(!preg_match("/^\d+$/", $identifier))
			return false;
		else
			return true;
			
	}
	
	function getNameDescriptionFromUserID($customerID){
	
		if(!$this->_checkIfDigit($customerID))
			throw new Exception("Error in Method getNameDescriptionFromUserID.  The User ID is invalid.");
	
		$this->_dbCmd->Query("SELECT Name, Company FROM users WHERE ID=$customerID");

		if($this->_dbCmd->GetNumRows() == 0)
			return "User ID Does not exist.";

		$row = $this->_dbCmd->GetRow();
		
		if($row["Company"] != "")
			return $row["Company"];
		else
			return $row["Name"];
	
	}


}






?>

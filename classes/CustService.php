<?php


class CustService {
	
	// Returns true if nobody owns the customer service item yet... or if the item is already owned by the user
	static function CheckIfCSitemIsFree(DbCmd $dbCmd, $csItemID, $userID){
	
		if(!preg_match("/^\d+$/", $csItemID))
			throw new Exception("Error with csitemID in function CustService::CheckIfCSitemIsFree");
	
		if(!preg_match("/^\d+$/", $userID))
			throw new Exception("Error with userID in function CustService::CheckIfCSitemIsFree");
	
		$dbCmd->Query("SELECT Ownership FROM csitems WHERE ID=$csItemID");
		$OwnershipID = $dbCmd->GetValue();
	
		if($OwnershipID == 0 || $OwnershipID == $userID)
			return true;
		else
			return false;
	}
	
	
	// Returns true or false 
	static function CheckIfCSitemExists(DbCmd $dbCmd, $csItemID){
	
		if(!preg_match("/^\d+$/", $csItemID))
			throw new Exception("Error with csitemID in function CustService::CheckIfCSitemExists");
			
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		$allUserDomains = $passiveAuthObj->getUserDomainsIDs();		
	
		$dbCmd->Query("SELECT COUNT(*) FROM csitems where ID=$csItemID 
						AND " . DbHelper::getOrClauseFromArray("DomainID", $allUserDomains));
	
		if($dbCmd->GetValue() == 1)
			return true;
		else
			return false;
	}
	
	
	// Returns Zero if the CS Item ID does not exist.
	static function getDomainIDfromCSitem(DbCmd $dbCmd, $csItemID){
	
		$csItemID = intval($csItemID);
			
		$dbCmd->Query("SELECT DomainID FROM csitems WHERE ID=$csItemID");
		$domainID = $dbCmd->GetValue();
			
		if(empty($domainID))
			$domainID = 0;
			
		return $domainID;
	}
	
	// Returns a Hash containing all of the messages for a particular csThreadID 
	static function GetMessagesFromCSitem(DbCmd $dbCmd, $csThreadID){
	
		if(!preg_match("/^\d+$/", $csThreadID))
			throw new Exception("Error with csitemcsThreadID in function CustService::GetMessagesFromCSitem");
	
		$retArr = array();
	
		$counter = 0;
		$domainObj = Domain::singleton();
		$dbCmd->Query("Select CustomerFlag, FromName, FromEmail, ToName, ToEmail, Message, UNIX_TIMESTAMP(DateSent) AS DateSent 
					FROM csmessages INNER JOIN csitems ON csitems.ID = csmessages.csThreadID WHERE csitems.ID=$csThreadID 
					AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()) . " ORDER BY csmessages.ID DESC");
	
		while($row = $dbCmd->GetRow()){
	
			$retArr[$counter] = array();
	
			$retArr[$counter] = $row;
	
			$counter++;
		}
	
		return $retArr;
	}
	
	// Returns a Hash containing all of the attachments for a particular csThreadID
	static function GetAttachmentsFromCSitem(DbCmd $dbCmd, $csThreadID){
	
		if(!preg_match("/^\d+$/", $csThreadID))
			throw new Exception("Error with csitemcsThreadID in function CustService::GetMessagesFromCSitem");
	
			
		if(!self::CheckIfCSitemExists($dbCmd, $csThreadID))
			throw new Exception("Can not fetch Attatchments from the CS item because it doesn't exist.");
	
		$retArr = array();
	
		$counter = 0;
	
		$dbCmd->Query("SELECT ID, filename, filesize, UNIX_TIMESTAMP(DateAttached) as DateAttached 
				FROM csattachments WHERE csThreadID=$csThreadID ORDER BY DateAttached DESC");
	
		while($row = $dbCmd->GetRow()){
			
			// Skip empty file names... which are usually HTML attachments used for rich text.
			if(empty($row["filename"]))
				continue;
	
			$retArr[$counter] = array();
	
			$retArr[$counter] = $row;
	
			$counter++;
		}
	
	
		return $retArr;
	
	}
	
	
	// Make hyperlink for customer service.
	static function GetCustServiceLinkForUser(DbCmd $dbCmd, $CS_userID, $smallLinkFlag){
	
		$custServiceCnt = CustService::GetCustServCountFromUser($dbCmd, $CS_userID, 0);
		
		if($custServiceCnt > 0)
			$recentCustServ = CustService::GetCustServCountFromUser($dbCmd, $CS_userID, 30);
		else
			$recentCustServ = 0;
		
		
		
		if($custServiceCnt == 0){
			if($smallLinkFlag)
				return "No CS";
			else
				return "<a class='BlueRedLink' href='javascript:NewCSfromUser($CS_userID)'>New Cust. Srvc.</a>";
		}
		
		if(empty($recentCustServ)){
			if($smallLinkFlag)
				return "<a class='BlueRedLinkRecSM' href='javascript:ShowCSfromUser($CS_userID, 0)'>CS ($custServiceCnt)</a>";
			else
				return "<a class='BlueRedLink' href='javascript:ShowCSfromUser($CS_userID, 0)'>Cust Srvc. ($custServiceCnt)</a> <a class='BlueRedLink' href='javascript:NewCSfromUser($CS_userID)'>New</a>";
		}
		else{
			if($smallLinkFlag)
				return "CS(<a class='BlueRedLinkRecSM' href='javascript:ShowCSfromUser($CS_userID, 30)'><font style='font-size:13px;'>$recentCustServ</font></a> /<a class='BlueRedLinkRecSM' href='javascript:ShowCSfromUser($CS_userID, 0)'>$custServiceCnt</a>)";
			else
				return "Cust Srvc. (<a class='BlueRedLink' href='javascript:ShowCSfromUser($CS_userID, 30)'><font style='font-size:14px;'>$recentCustServ</font></a> / <a class='BlueRedLink' href='javascript:ShowCSfromUser($CS_userID, 0)'>$custServiceCnt</a>) <a class='BlueRedLink' href='javascript:NewCSfromUser($CS_userID)'>New</a>";
		}
	
		
	}
	
	
	// Get the count of CS items belonging to a user with X number of days.  Pass in Zero to get a count of all.
	static function GetCustServCountFromUser(DbCmd $dbCmd, $CS_userID, $xDays){
	
		$xDays = intval($xDays);
		
		$domainObj = Domain::singleton();
		$domainClause = " AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs());
		
		if(empty($xDays)){
			$dbCmd->Query("SELECT COUNT(DISTINCT CS.ID) FROM csitems AS CS INNER JOIN csmessages AS CSM on CSM.csThreadID = CS.ID 
					WHERE CS.UserID=" . $CS_userID . $domainClause);
		}
		else{
			$daysAgoMYSQL = date("YmdHis", mktime (0,0,1,date("n"),(date("j") - $xDays),date("Y"))); 
		
			$dbCmd->Query("SELECT COUNT(DISTINCT CS.ID) FROM csitems AS CS INNER JOIN csmessages AS CSM on CSM.csThreadID = CS.ID 
					WHERE CS.UserID=" . $CS_userID . " AND LastActivity > '" . $daysAgoMYSQL . "'" . $domainClause);
		}
	
		return $dbCmd->GetValue();
	}
	
	
	
	// Set $CountOnly = false when you want to get back an array of IDs.... otherwise returns just a count.
	static function GetCustomerServiceCount(DbCmd $dbCmd, $view, $CS_userID, $CountOnly=true){
	
		if($CountOnly)
			$Column = "COUNT(*)";
		else
			$Column = "ID";
			
		if(strtoupper($view) == "ALL")
			$query = "SELECT $Column FROM csitems WHERE (Ownership=$CS_userID OR Ownership=0) AND (Status='O' OR Status='H') ";	
		else if(strtoupper($view) == "MY")
			$query = "SELECT $Column FROM csitems WHERE Ownership=$CS_userID AND (Status='O' OR Status='H' OR Status='P')";
		else if(strtoupper($view) == "UNASSIGNED")
			$query = "SELECT $Column FROM csitems WHERE Ownership=0 AND Status='O' ";
		else if(strtoupper($view) == "OTHERS")
			$query = "SELECT $Column FROM csitems WHERE (Ownership!=$CS_userID AND Ownership!=0) AND (Status='O' OR Status='H') ";
		else if(strtoupper($view) == "HELP")
			$query = "SELECT $Column FROM csitems WHERE Status='H' ";
		else if(strtoupper($view) == "PHONE")
			$query = "SELECT $Column FROM csitems WHERE Status='P' ";
		else{
			print "Error with View in CustService::GetCustomerServiceCount";
			exit;
		}
		
		$domainObj = Domain::singleton();
		
		$query .= " AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs());
	
		
		$dbCmd->Query($query);
	
		if($CountOnly)	
			return $dbCmd->GetValue();
		else{
			$RetArr = array();
			while($ThisID = $dbCmd->GetValue())
				$RetArr[] = $ThisID;
			return $RetArr;
		}
	}
	
	
	// Returns true or false depending on whether this project has any customer service messages with it.
	static function CheckIfOrderHasAnyCSitems(DbCmd $dbCmd, $OrderID){
	
		if(!preg_match("/^\d+$/", $OrderID))
			throw new Exception("Error with OrderID in function CustService::CheckIfOrderHasAnyCSitems");
		$OrderID = intval($OrderID);
	
		$dbCmd->Query("SELECT count(*) from csmessages INNER JOIN csitems 
				ON csmessages.csThreadID = csitems.ID  WHERE csitems.OrderRef=$OrderID");
	
	
		if($dbCmd->GetValue() == 0)
			return false;
		else
			return true;
	
	}
	
	
	
	
	// Returns a description and Link for all of the attachments
	static function FormatAttachmentsForCSItem(&$AttachmentHash){
	
		$retStr = "";
	
		if(sizeof($AttachmentHash) > 0){
	
			foreach($AttachmentHash as $AttachmentDetail){
			
				//We want to find out if the file attachment is on disk
				//If it is... then show them a link... If not.  Then just show the file name with no link on it
				$AttachmentFileName = $AttachmentDetail["ID"] . "_" . $AttachmentDetail["filename"];
				$AttachmentFileName = WebUtil::FilterData($AttachmentFileName, FILTER_SANITIZE_STRING_ONE_LINE);
				
				$fileNameDesc = WebUtil::FilterData($AttachmentDetail["filename"], FILTER_SANITIZE_STRING_ONE_LINE);
				
				$FileNameForDisk = Constants::GetFileAttachDirectory() . "/" . $AttachmentFileName;
				
				$retStr .= "<img src='./images/paperclip.gif' align='absmiddle'> ";
	
				if(file_exists($FileNameForDisk))
					$retStr .= "<a href='./customer_attachments/" . urlencode($AttachmentFileName) . "' target='top'>" . WebUtil::htmlOutput($fileNameDesc) . "</a>  &nbsp;&nbsp;&nbsp;";
				else
					$retStr .= WebUtil::htmlOutput($AttachmentDetail["filename"]) . "  &nbsp;&nbsp;&nbsp;";
			
	
				$retStr .= round($AttachmentDetail["filesize"]/1024,2) . "KB" ;
				$retStr .= " &nbsp;&nbsp;&nbsp;";
				$retStr .= date("m-d-Y H:i", $AttachmentDetail["DateAttached"]);
				$retStr .= "<br><img src='./images/transparent.gif' height='5' width='1'><br>";
	
			}
		}
	
		return $retStr;
	
	}
	
	
	// Returns a table expanding to 100% of the width... Should pass in the hash obtained from calling the function CustService::GetMessagesFromCSitem
	// $AdminView is boolean... it tells us if we should look for order links within the message and convert into admin hyperlinks
	static function FormatMessagesForCSItem($MessageHash, $CustomerColor, $AgentColor, $AdminView){

		if(sizeof($MessageHash) > 0){
	
			$retHTML = "<table width='100%' cellpadding='2' cellspacing='0' border='1'>";
	
			foreach($MessageHash as $MessageDetail){
	
				if($MessageDetail["Message"] == ""){
					$MessageDetail["Message"] = "empty";
				}
	
				// If it came from the customer then show it in a different color
				if($MessageDetail["CustomerFlag"] == "N"){
					$rowBGcolor = $AgentColor;
					$startItalics = "";   //Add font color, italics etc...
					$endItalics = "";
				}
				else{
					$rowBGcolor = $CustomerColor;
					$startItalics = "";
					$endItalics = "";
				}
	
	
	
	
	
				if(strlen($MessageDetail["Message"]) < 20000){
					#-- Format the message for HTML --#
					$MessageDetail["Message"] = WebUtil::htmlOutput($MessageDetail["Message"]);
					$MessageDetail["Message"] = preg_replace("/\n/", "<br>", $MessageDetail["Message"]);
				}
				else{
					$MessageDetail["Message"] = "The message body is extremely large.  There could a problem with this message.";
				}
				
	
				$retHTML .= "<tr bgcolor='$rowBGcolor'>";
	
				$retHTML .= "<td class='ReallySmallBody'><b>Date:</b> " . date("m/d/y g:i a", $MessageDetail["DateSent"]) . "</td>";
				$retHTML .= "<td class='ReallySmallBody'><b>F:</b> " . WebUtil::htmlOutput($MessageDetail["FromName"]) . "</td>";
				$retHTML .= "<td class='ReallySmallBody'><b>T:</b> " . WebUtil::htmlOutput($MessageDetail["ToName"]) . "</td>";
	
				$retHTML .= "</tr>";
	
				$retHTML .= "<tr>";
	
				$retHTML .= "<td class='SemiSmallBody' colspan='3'>$startItalics" . $MessageDetail["Message"] . "$endItalics</td>";
	
				$retHTML .= "</tr>";
			}
	
			$retHTML .= "</table>";
	
			// We don't want to do this for customers
			if($AdminView){
				$retHTML = CustService::ConvertOrderNumberToLinks($retHTML);
			}
	
			return $retHTML;
	
	
		}
		else{
			return "";
		}
	
	}
	
	// Will search through section of text and convert any order numbers into administrator hyper links
	static function ConvertOrderNumberToLinks($Message){
	
		$OrderNumbersArr = CustService::GetOrderNumbersFromMessage($Message);
		
		// The order Numbers look like O61-87  .. The hashed part before the dash
		
		foreach($OrderNumbersArr as $ThisOrderNo){
		
			$SplitArr = split("-", $ThisOrderNo);
			$MainOrderNo = $SplitArr[1];
	
			$HyperLink = "<a class='BlueRedLink' href='javascript:window.top.goToOrderNumber(" . $MainOrderNo . ")'>$ThisOrderNo</a>";
	
			$Message = preg_replace("/$ThisOrderNo/", $HyperLink, $Message);
		
		}
		
		
		return $Message;
	}
	
	//Returns a unique array... containing all order numbers found in the message
	static function GetOrderNumbersFromMessage(&$Message){
	
		$RetArr = array();
	
		// There was a bug... where an Attached image was returned by a mail server ... 
		// the binary stuff came back in the body of the message This criples the CPU
		if(strlen($Message) < 6000){
	
			$matches = array();
			
			// Make sure that the order number is at least 2 digits.
			if(preg_match_all("/((\w|\d)+)-(\d{2,12})/", $Message, $matches)){
	
				for($i=0; $i<sizeof($matches[0]); $i++){
	
					$PossibleHashCode = $matches[1][$i];
					$PossibleOrderID = $matches[3][$i];
	
					$CalculatedHashOrderNo = Order::CalculateHashedOrderNo($PossibleOrderID);
	
					if($CalculatedHashOrderNo == $PossibleHashCode){
						$RetArr[] = $CalculatedHashOrderNo . "-" . $PossibleOrderID;
					}
				}
			}
		}
		
		return array_unique($RetArr);
	}
	
	//Returns a hash... very similar to what is stored in the DB for a "csitem"... except the date is a unix timestamp.
	static function GetCSitem(DbCmd $dbCmd, $csthreadid){
	
		$csthreadid = intval($csthreadid);
		$domainObj = Domain::singleton();
		
		$dbCmd->Query("SELECT *, UNIX_TIMESTAMP(DateCreated) AS StartDate, UNIX_TIMESTAMP(LastActivity) 
						AS EndDate FROM csitems WHERE ID=$csthreadid 
						AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));
						
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Error with method GetCSitem. It does not exist.");
			
		return $dbCmd->GetRow();
	}
	
	//Returns an array of CS items belonging to an order
	static function GetCSitemIDsInOrder(DbCmd $dbCmd, $OrderID){
	
		if(!preg_match("/^\d+$/", $OrderID))
			throw new Exception("Error with csitemID in function CustService::GetCSitem");
	
		$OrderID = intval($OrderID);
	
		//Store CS Item ID's in an array to avoid doing a nested query or a Join
		$CSidArr = array();
		$dbCmd->Query("SELECT ID FROM csitems WHERE OrderRef=$OrderID AND Status != 'J' ORDER BY DateCreated DESC");
		while($ThisID = $dbCmd->GetValue())
			$CSidArr[] = $ThisID;
		
		return $CSidArr;
	}
	
	
	//Returns the HTML for what goes in the Date column for the CS item. 
	static function GetCSItemDate(DbCmd $dbCmd, $csthreadid){
	
		$CSItemHash_5 = CustService::GetCSitem($dbCmd, $csthreadid);
	
		$retHTML = "Last Activity<br><img src='./images/transparent.gif' height='5' width='5'><br>";
		$retHTML .= date("m/d/y", $CSItemHash_5["EndDate"]);
		$retHTML .= "<br><nobr>" . date("g:i a", $CSItemHash_5["EndDate"]) . "</nobr>";
	
		//If the CS item is still open... then show them how long they have been waiting.
		if($CSItemHash_5["Status"] == "O" || $CSItemHash_5["Status"] == "H" || $CSItemHash_5["Status"] == "P"){
			
			$SecondsDiff_Total = time() - $CSItemHash_5["EndDate"];
			$HoursDiff = floor($SecondsDiff_Total / 3600);
			$MinutesDiff = floor(($SecondsDiff_Total - $HoursDiff * 3600) / 60);
			
			$retHTML .= "<br><br><font class='reallsmallbody'>Waiting...</font><br><img src='./images/transparent.gif' height='2' width='2'><br>";
			if($HoursDiff != 0)
				$retHTML .= "<nobr><b>" . $HoursDiff . " hour" . LanguageBase::GetPluralSuffix($HoursDiff, "", "s") . "</b></nobr><br>";
			$retHTML .= $MinutesDiff . " min.";
		}
		
		
		if($CSItemHash_5["RawTextDumpFileName"] == "")
			return $retHTML;
			
		
		$RawTextFileName = Constants::GetFileAttachDirectory() . "/" . $CSItemHash_5["RawTextDumpFileName"];
		if(file_exists($RawTextFileName)){
			$retHTML .= "<br><br><a href='javascript:DisplayAttachment(\"" . $CSItemHash_5["RawTextDumpFileName"] . "\")'><font class='ReallySmallBody'>Raw Msg.</font></a>";
		}
		return $retHTML;
	
		
	}
	
	
	// Parse All of the CS Items...  Pass in a reference to the template object.
	// $CSIDarr is a list of all CS ID's that we want parsed
	// $BlockName is the name of the HTML block
	// $RowBackground is the alternating row color.  The primary color is always white.
	static function ParseCSBlock(DbCmd $dbCmd, &$t, $CSIDarr, $BlockName, $RowBackground, $CS_UserID, $CurrentURL){
	
		$BlockNameOut = $BlockName . "out";
		
		$t->set_block("origPage", $BlockName, $BlockNameOut);
	
		$rowBackgroundColor = true;
	
		foreach($CSIDarr as $ThisCSid){
	
			$ThisCSitemHash = CustService::GetCSitem($dbCmd, $ThisCSid);
	
			// Get a hash with all of the attachments
			$attachmentHash = CustService::GetAttachmentsFromCSitem($dbCmd, $ThisCSitemHash["ID"]);
	
			// Set the Row Color
			if($rowBackgroundColor){
				$rowcolor = "#FFFFFF";
				$rowBackgroundColor = false;
			}
			else{
				$rowcolor = $RowBackground;
				$rowBackgroundColor = true;
			}
	
			
			// Find out how many messages are in a CS Item.
			// We can use this as a "cache breaker".  Because we set a long "expires" on the CS Message Thread
			// So when a new message comes in, then it will break the cache by changing the source to the Iframe.
			$dbCmd->Query("SELECT COUNT(*) FROM csmessages WHERE csThreadID=$ThisCSid"); 
			$messageCount = $dbCmd->GetValue();
			
			$t->set_var("CS_SNDMSG", CustService::GetCSItemCommands($dbCmd, $ThisCSitemHash["ID"], $CS_UserID, $CurrentURL));
			$t->set_var("CS_SUBJECT", CustService::GetCSItemSubject($dbCmd, $ThisCSitemHash["ID"]));
			$t->set_var("CS_DATE", CustService::GetCSItemDate($dbCmd, $ThisCSitemHash["ID"]));
			$t->set_var("CS_MESSAGE", "<iframe src='./ad_cs_message_thread.php?csitemid=".$ThisCSitemHash["ID"]."&messageCount=$messageCount' height='300' width='100%'>Iframes are not enabled.</iframe>");
			$t->set_var("CS_ATTACHMENTS", CustService::FormatAttachmentsForCSItem($attachmentHash));
			$t->set_var("CSITEM_ID", $ThisCSitemHash["ID"]);
			$t->set_var("ROWCOLOR", $rowcolor);
			
			$t->allowVariableToContainBrackets("CS_SNDMSG");
			$t->allowVariableToContainBrackets("CS_SUBJECT");
			$t->allowVariableToContainBrackets("CS_DATE");
			$t->allowVariableToContainBrackets("CS_MESSAGE");
			$t->allowVariableToContainBrackets("CS_ATTACHMENTS");
	
			$t->parse($BlockNameOut, $BlockName,true);
		}
	
	
	}
	
	
	// Returns the HTML for what goes in the Subject column for the CS item. 
	// May show a link to other customer services by the person.  Show you who has ownership... display the subject of the message... May have a link to an order.
	static function GetCSItemSubject(DbCmd $dbCmd, $csthreadid){
	
		$CSItemHash_1 = CustService::GetCSitem($dbCmd, $csthreadid);
	
	
		if($CSItemHash_1["Status"] == "C"){
			$retHTML = '<font color="#333333" size="2"><b>Closed</b></font>';
		}
		else if($CSItemHash_1["Status"] == "O"){
			$retHTML = '<font color="#336699" size="4"><b>Open</b></font>';
		}
		else if($CSItemHash_1["Status"] == "H"){
			$retHTML = '<font color="#996666" size="3"><b>Needs Assistance</b></font>';
		}
		else if($CSItemHash_1["Status"] == "J"){
			$retHTML = 'Junk Email';
		}
		else if($CSItemHash_1["Status"] == "S"){
			$retHTML = 'Spam';
		}
		else if($CSItemHash_1["Status"] == "P"){
			$retHTML = '<font color="#666699" size="3"><b>Phone Message</b></font>';
		}
		else{
			print "Error with Status Type for CS item";
			exit;
		}
	
		//Spacer
		$retHTML .= '<br><img src="./images/transparent.gif" border="0" width="1" height="7"><br>';
	
		if($CSItemHash_1["Ownership"] == "0"){
			$retHTML .= "Unassigned";
		}
		else{
			$retHTML .= "<font class='SmallBody'>Owned by " . WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, $CSItemHash_1["Ownership"])) . "</font>";
			
			$memberAttendanceObj = new MemberAttendance($dbCmd);
			
			$currentUserStatus = $memberAttendanceObj->getCurrentStatusOfUser($CSItemHash_1["Ownership"]);
			$returnDate = $memberAttendanceObj->getUnixTimeStampOfReturn($CSItemHash_1["Ownership"]);
			
			// If the CSR is late from lunch or didn't show up to work, then show a Red font color.
			if($returnDate && $returnDate < time())
				$thisFontColor = "#CC0000";
			else
				$thisFontColor = "#000000";
			
			// Also, Offline Users should be shown in Red since they might not be online to check their messages
			if($currentUserStatus == "F")
				$thisFontColor = "#CC0000";
			
			$retHTML .= "<br><font class='ReallySmallBody' style='color:" . $thisFontColor . "'>(" . $memberAttendanceObj->getCurrentStatusDescriptionOfUser($CSItemHash_1["Ownership"]) . ")";
		
			
			if($returnDate){
				if($returnDate < time())
					$retHTML .= "<br>Late: " . LanguageBase::getTimeDiffDesc($returnDate);
				else
					$retHTML .= "<br>Ret: " . LanguageBase::getTimeDiffDesc($returnDate);
			}
			
			$retHTML .= "</font>";
		
		}
	
		//Spacer
		$retHTML .= '<br><img src="./images/transparent.gif" border="0" width="1" height="7"><br>';
		$retHTML .= WebUtil::htmlOutput($CSItemHash_1["Subject"]);
	
		//See if this is related to a specific order
		if($CSItemHash_1["OrderRef"] != "0"){
			$retHTML .= "<br><img src='./images/transparent.gif' border='0' width='1' height='7'><br><a href='./ad_order.php?orderno=" . $CSItemHash_1["OrderRef"] . "' class='BlueRedLink'>Order # " . $CSItemHash_1["OrderRef"] . '</a>';
		
			// If we have an order number... but not a user ID associated with this person...  Then we want to temporarily give them the corresponding user ID
			if($CSItemHash_1["UserID"] == "0"){
				$CSItemHash_1["UserID"] = Order::GetUserIDFromOrder($dbCmd, $CSItemHash_1["OrderRef"]);
			}
		}
	
		// Show a link to see all CS items from this person.
		// When you move your mouse over the Total CS items, it should show an DHTML menu pop-up that you can see the CS items by activity period
		$TotalCSitemsFromEmail = CustService::GetNumberOfCSitemsFromEmail($dbCmd, $CSItemHash_1["CustomerEmail"]);
		$DHTML_CSbyActivityPeriod = CustService::GetHTMLforCSitemsByActivityPeriod($dbCmd, $CSItemHash_1["CustomerEmail"]);
	
		$csItemID = $CSItemHash_1["ID"];
	
		$retHTML .= "<br><br><div id='csActivityDiv" . $csItemID . "' class='hiddenDHTMLwindow' onMouseOver='showCSactivityPeriodsDHTML($csItemID, true);' onMouseOut='showCSactivityPeriodsDHTML($csItemID, false);'> 
			<a class='blueredlink' href='./ad_cs_home.php?view=search&bydaysold=&bykeywords=" . urlencode($CSItemHash_1["CustomerEmail"]) . "'>All CS Items ($TotalCSitemsFromEmail)</a>
			<span style='visibility:hidden; left:90px; top:-55' id='csActivityLayer" . $csItemID . "'>" . $DHTML_CSbyActivityPeriod . "</span></div>";
		
	
		$domainObj = Domain::singleton();
		if(sizeof($domainObj->getSelectedDomainIDs()) > 1){
			$domainLogoObj = new DomainLogos($CSItemHash_1["DomainID"]);
			$domainLogoImg = "<img alt='".Domain::getDomainKeyFromID($CSItemHash_1["DomainID"])."' src='./domain_logos/$domainLogoObj->verySmallIcon' border='0'>";
		}
		else{
			$domainLogoImg = "";
		}
			
		//If we have the user information.. then show them a link to "user info"... If they have placed an orders.. show them a link to that too.
		if($CSItemHash_1["UserID"] != "0"){
			$retHTML .= "<img src='./images/transparent.gif' border='0' width='1' height='7'><br><a class='blueredlink' href='javascript:UserInfo(". $CSItemHash_1["UserID"] . ");'>User Info</a> &nbsp;&nbsp;";
			
			$ProjectCount = Order::GetProjectCountByUser($dbCmd, $CSItemHash_1["UserID"], "");
			if($ProjectCount > 0)
				$retHTML .= "<br><img src='./images/transparent.gif' border='0' width='1' height='5'><br><nobr><a class='blueredlink' href='javascript:Cust(". $CSItemHash_1["UserID"] . ")'>" . $ProjectCount . " project" . LanguageBase::GetPluralSuffix($ProjectCount, "", "s") .  "</a></nobr>";
		
			// Show them a link into the users Saved Projets
			$TotalSavedProjects = ProjectSaved::GetCountOfSavedProjects($dbCmd, $CSItemHash_1["UserID"]);
			$retHTML .= "<br><img src='./images/transparent.gif' border='0' width='1' height='7'><br><a class='BlueRedLink' href='javascript:SaveProj(\"".Domain::getWebsiteURLforDomainID(Domain::getDomainIDfromURL())."\","  . $CSItemHash_1["UserID"] . ");'>Saved Projects ($TotalSavedProjects)</a>";
	
			// Link to the memos
			$retHTML .= "<br><img src='./images/transparent.gif' border='0' width='1' height='7'><br><a class='blueredlink' href='javascript:CustomerMemo(". $CSItemHash_1["UserID"] . ", true)'>" . CustomerMemos::getLinkDescription($dbCmd, $CSItemHash_1["UserID"], false) . "</a>";
	
	
		
			$custUserControlObj = new UserControl($dbCmd);
			$custUserControlObj->LoadUserByID($CSItemHash_1["UserID"]);
	
			$customerRating = $custUserControlObj->getUserRating();
	
			$custRatingImgHTML = "<img src='./images/star-rating-" . $customerRating . ".gif' width='74' height='13'>";
	
			if($customerRating > 0)
				$retHTML .= "<br><div id='Dcust" . $csthreadid . "' style='display:inline; cursor: hand; width:74px;' class='hiddenDHTMLwindow' onMouseOver='CustInf($csthreadid, ". $CSItemHash_1["UserID"] .", true)' onMouseOut='CustInf($csthreadid, ". $CSItemHash_1["UserID"] .", false)'>$custRatingImgHTML<span class='hiddenDHTMLwindow' style='visibility:hidden; left:75px; top:-15' id='Scust" . $csthreadid . "'></span></div>$domainLogoImg";
			else
				$retHTML .= "<br><img src='./images/transparent.gif' width='7' height='7'><br>" . $custRatingImgHTML . $domainLogoImg;
	
			// Give the customer Service person a clue if the customer may have registered with multiple accounts.
			$similarAccounts = UserControl::GetCountOfSimlarAccountsToUserID($dbCmd, $CSItemHash_1["UserID"]);
			if($similarAccounts)
				$retHTML .= "<br><img src='./images/transparent.gif' border='0' width='1' height='7'><br><a href='./ad_users_search.php?similarToCustomerID=" . $CSItemHash_1["UserID"] . "'><font style='font-size:16px; font-weight:bold;' color='#cc0000'>" . ($similarAccounts + 1) . " Similar Accounts</font></a>";
		
		}
		else{
			$retHTML .= "<img src='./images/transparent.gif' border='0' width='1' height='7'><br>$domainLogoImg";
		}
	
	
		$retHTML .= "<br><img src='./images/transparent.gif' border='0' width='1' height='7'><br><font class='ReallySmallBody'>" . WebUtil::htmlOutput($CSItemHash_1["CustomerEmail"]) . "</font>";
	
		return $retHTML;
	}
	
	
	// Returns HTML that may allow them to "Send a Message", "Reply to Customer", "Task", "Take Ownership", "Override Ownership", etc.
	static function GetCSItemCommands(DbCmd $dbCmd, $csthreadid, $customerServiceUserID, $ReturnURL){
	
		$ReturnURL = WebUtil::FilterURL($ReturnURL);
		
		$CSItemHash_2 = CustService::GetCSitem($dbCmd, $csthreadid);
	
		$CommandHTML = "";
	
		$ReturnURL = urlencode($ReturnURL);
		
		//We need to double encode in the Return URL.  The first time for entering the Javascript function through href="javascript:JunkEmail()
		//The second time for going to the server.
		$DoubleEncodedURL = urlencode($ReturnURL);
	
		// If the administrator owns this project  
		if($CSItemHash_2["Ownership"] == $customerServiceUserID){
			
			$CommandHTML .= "<a href=\"javascript:SendCustomerWebMail(" . $CSItemHash_2["ID"] . ");\" class=\"BlueRedLink\">Reply To<br>Customer</a>";
	
			//spacer
			$CommandHTML .= "<br><img src='./images/transparent.gif' border='0' width='1' height='7'><br>";
			
			$CommandHTML .= "<a href=\"javascript:ReAssignCSitem(" . $CSItemHash_2["ID"] . ");\" class=\"BlueRedLink\">Reassign</a>";
	
			$CommandHTML .= "<br><img src='./images/transparent.gif' border='0' width='1' height='7'><br>";
	
	
		}
		else{
			
			// The Admin doesn't own it.  Find out if it is owned by someone else... or if it is undefined
			if($CSItemHash_2["Ownership"] == 0)
				$CommandHTML .= "<a class='BlueRedLink' href='./ad_actions.php?form_sc=".WebUtil::getFormSecurityCode()."&action=csitem_assign&assign[]=" . $CSItemHash_2["ID"] . "&returl="  . $ReturnURL . "'>Take Control</a>";
			else
				$CommandHTML .= "<a class='BlueRedLink' href='./ad_actions.php?form_sc=".WebUtil::getFormSecurityCode()."&action=csitem_assign&assign[]=" . $CSItemHash_2["ID"] . "&returl="  . $ReturnURL . "&override=yes'>Override Control</a>";
	
			//spacer
			$CommandHTML .= "<br><img src='./images/transparent.gif' border='0' width='1' height='7'><br>";
		}
	
	
		$CommandHTML .= "<a href=\"javascript:MakeNewTask('csitem', " . $CSItemHash_2["ID"] . ", false);\" class=\"BlueRedLink\">Task</a>";
	
		//spacer
		$CommandHTML .= "<br><img src='./images/transparent.gif' border='0' width='1' height='7'><br>";
	
	
		$CommandHTML .= "<a href=\"javascript:SendInternalMessage('csitem', {CSITEM_ID}, true);\" class=\"BlueRedLink\">Message</a>";
	
	
		//Allow anyone to move the CS item to "Needs Assistance" if the CS item is still open
		//If it is owned by somebody else then don't give them permission to see the link
		if($CSItemHash_2["Status"] == "O" && ($CSItemHash_2["Ownership"] == $customerServiceUserID || $CSItemHash_2["Ownership"] == 0 )){
			//spacer
			$CommandHTML .= "<br><img src='./images/transparent.gif' border='0' width='1' height='7'><br>";
	
	
			$CommandHTML .= "<a href='javascript:CSitemNeedsAssistance({CSITEM_ID}, \"$DoubleEncodedURL\", \"".WebUtil::getFormSecurityCode()."\");' class=\"BlueRedLink\">Needs Help</a>";
		}
	
		//spacer
		$CommandHTML .= "<br><img src='./images/transparent.gif' border='0' width='1' height='7'><br>";
	
	
		// Also allow them to close the item (if it isn't already closed.)... but only if they own it... or nobody else does yet
		if(($CSItemHash_2["Status"] == "O" || $CSItemHash_2["Status"] == "H" || $CSItemHash_2["Status"] == "P")  && ($CSItemHash_2["Ownership"] == 0 || $CSItemHash_2["Ownership"] == $customerServiceUserID)){
	
			#-- If nobody owns it... then it can be closed as junk (possibly) --#
			if($CSItemHash_2["Ownership"] == 0){	
			
				$CommandHTML .= "<a href='javascript:CloseAsJunk({CSITEM_ID}, \"$DoubleEncodedURL\", \"".WebUtil::getFormSecurityCode()."\");' class='BlueRedLink'><nobr>Junk Email</nobr></a>";
	
				//spacer
				$CommandHTML .= "<br><img src='./images/transparent.gif' border='0' width='1' height='7'><br>";
			}
	
			$CommandHTML .= "<a href='javascript:CloseCSitem({CSITEM_ID}, \"$DoubleEncodedURL\", \"".WebUtil::getFormSecurityCode()."\")' class='BlueRedLink'>Close</a>";
	
			//spacer
			$CommandHTML .= "<br><img src='./images/transparent.gif' border='0' width='1' height='7'><br>";
	
		}
	
		return $CommandHTML;
	
	}
	
	// Get rid of the raw text dump for the message... if there is one.
	static function RemoveRawTextFile(DbCmd $dbCmd, $CSitemID){
	
		$dbCmd->Query("SELECT RawTextDumpFileName FROM csitems WHERE ID=$CSitemID");
	
		$OldRawTextDumpName = $dbCmd->GetValue();
		
		if(!empty($OldRawTextDumpName)){
			$OldRawTextDumpName = Constants::GetFileAttachDirectory() . "/" . $OldRawTextDumpName;
			if(file_exists($OldRawTextDumpName))
				unlink($OldRawTextDumpName);
		}
	
	}
	
	
	// Pass in a Database object and a hash with all of the name/values to be inserted into the DB.
	// Returns the ID 
	static function NewCSitem(DbCmd $dbCmd, $infoHash){
	
	
		// Verify that everything that is supposed to be in the hash is there.
		$Error = "";
		if(!isset($infoHash["Subject"]))
			$Error = "no subject in CustService::NewCSitem";
		if(!isset($infoHash["Status"]))
			$Error = "no Status in CustService::NewCSitem";
		if(!isset($infoHash["UserID"]))
			$Error = "no UserID in CustService::NewCSitem";
		if(!isset($infoHash["Ownership"]))
			$Error = "no Ownership in CustService::NewCSitem";
		if(!isset($infoHash["DateCreated"]))
			$Error = "no DateCreated in CustService::NewCSitem";
		if(!isset($infoHash["LastActivity"]))
			$Error = "no LastActivity in CustService::NewCSitem";
		if(!isset($infoHash["CustomerName"]))
			$Error = "no CustomerName in CustService::NewCSitem";
		if(!isset($infoHash["CustomerEmail"]))
			$Error = "no CustomerEmail in CustService::NewCSitem";
	
		if(!empty($Error)){
			print $Error;
			exit;
		}
	
		// Default Values
		if(!isset($infoHash["OrderRef"])){
			$infoHash["OrderRef"] = 0;
		}
		if(!isset($infoHash["RawTextDumpFileName"])){
			$infoHash["RawTextDumpFileName"] = "";
		}
	
		$arrStruct2 = array();
		$arrStruct2[ "Subject"] = $infoHash["Subject"];
		$arrStruct2[ "Status"] = $infoHash["Status"];
		$arrStruct2[ "UserID"] = $infoHash["UserID"];
		$arrStruct2[ "Ownership"] = $infoHash["Ownership"];
		$arrStruct2[ "OrderRef"] = $infoHash["OrderRef"];
		$arrStruct2[ "DateCreated"] = $infoHash["DateCreated"];
		$arrStruct2[ "LastActivity"] = $infoHash["LastActivity"];
		$arrStruct2[ "CustomerName"] = $infoHash["CustomerName"];
		$arrStruct2[ "CustomerEmail"] = $infoHash["CustomerEmail"];
		$arrStruct2[ "RawTextDumpFileName"] = $infoHash["RawTextDumpFileName"];
		$arrStruct2[ "DomainID"] = $infoHash["DomainID"];
	
		return $dbCmd->InsertQuery("csitems", $arrStruct2);
	
	}
	
	
	// When someone replies to an email the characters -----Original Message----- separates each messsage
	// We just want the top message
	static function GetOriginalMessage($msg){
	
		// If the message is over 200K... then something is wrong..  Maybe the attachment is embedded in the message
		if(strlen($msg) > 200000)
			return "There was a problem with this message.  The body appears to be extremely large.  Check the Raw Message log for more details.";
	
		$msgArr = split("\n", $msg);
		$newMessage = "";
	
	
		foreach($msgArr as $MessageLine){
	
			if(preg_match("/---\s*Original\s*Message\s*---/i", $MessageLine))
				break;
	
	
			// Look for a line begining with a greater than operator... This is typical of what many email clients do when they reply to an email
			// If we find one, then skip the line.
			// This is only in case if the "Original Message" delinator doesn't work.
			if(preg_match("/^>/", $MessageLine) || preg_match("/^&gt/", $MessageLine)){
				continue;
			}
	
	
			$newMessage .= $MessageLine . "\n";
	
		}
	
		// Clean up white spaces and new lines
		$newMessage = ltrim($newMessage);
		$newMessage = rtrim($newMessage);
	
		return $newMessage;
	}
	
	
	
	
	
	// Find out if this cs item is not attached to a project or an order.
	// If not, then lets look through the message and see if we can find any order number
	// If we do find 1.. then we can be sure it is valid (due to the digestive hash)..
	// So then update the Database with a link from the CS item  
	// Pass in $FromEmail if you want to rescan the DB looking for a matching user... otherwise just leave it as ""
	static function PossiblyAssociateReferenceWithCSitem(DbCmd $dbCmd, $CS_ThreadID, &$Message, $FromEmail){
		
		// First we want to check that the cs item is not already associated --#
		$dbCmd->Query("SELECT OrderRef, UserID, Subject, DomainID FROM csitems WHERE ID=$CS_ThreadID");
		$row = $dbCmd->GetRow();
		$OrderRef = $row["OrderRef"];
		$CSuserID = $row["UserID"];
		$Subject = $row["Subject"];
		$domainIDofCSitem = $row["DomainID"];
	
		//  If the csitem is not attached to an order... see if we can attach it.
		if($OrderRef == "0"){
	
			//Scan through the message looking for our hashed order number
			$OrderNumberArr = CustService::GetOrderNumbersFromMessage($Message);
			$OrderNumberArr = array_merge($OrderNumberArr, CustService::GetOrderNumbersFromMessage($Subject));
	
			if(sizeof($OrderNumberArr) > 0){
	
				//We are going to associate the CS item with the first order number found.
				$SplitArr = split("-", $OrderNumberArr[0]);
				$MainOrderNo = $SplitArr[1];

				// First Make sure the order number exists... or the getDomainIDFromOrder() method could fail.
				if(Order::checkIfOrderIDexists($MainOrderNo)){

					// If someone forwared a quote or something from a sister domain... it could get linked to an order which a CSR does not have permission to see.
					$domainIDofOrder = Order::getDomainIDFromOrder($MainOrderNo);
					
					if($domainIDofOrder == $domainIDofCSitem)
						$dbCmd->Query("UPDATE csitems SET OrderRef=$MainOrderNo WHERE ID=$CS_ThreadID");
				}
			}
		}
		if($CSuserID == "0" && !empty($FromEmail)){
	
			//Prevent someone from querying on unchecked data
			if(WebUtil::ValidateEmail($FromEmail)){
		
				//Now look for the email address within our system
				$dbCmd->Query("SELECT ID FROM users WHERE Email LIKE '" . DbCmd::EscapeLikeQuery($FromEmail) . "' AND DomainID=$domainIDofCSitem");
				
				if($dbCmd->GetNumRows() == 1){
					$customerUserID = $dbCmd->GetValue();
					
					$dbCmd->Query("UPDATE csitems SET UserID=$customerUserID WHERE ID=$CS_ThreadID");
				}
		
			}
		}
	
	}
	
	
	static function GetHTMLforCSitemsByActivityPeriod(DbCmd $dbCmd, $CustomerEmail){
	
		$startTime = time() - (1*60*60*24);
		$endTime = 0;
		$csCount1Day = CustService::GetNumberOfCSitemsFromEmail($dbCmd, $CustomerEmail, $startTime, $endTime);
		if($csCount1Day)
			$csHTML1Day = "<a class='blueredlink' href='./ad_cs_home.php?view=search&starttime=$startTime&endtime=$endTime&bykeywords=" . urlencode($CustomerEmail) . "'>Within 1 Day (<b>$csCount1Day</b>)</a>";
		else
			$csHTML1Day = "Within 1 Day (0)";
		
		$startTime = time() - (7*60*60*24);
		$endTime = time() - (1*60*60*24);
		$csCount7Day = CustService::GetNumberOfCSitemsFromEmail($dbCmd, $CustomerEmail, $startTime, $endTime);
		if($csCount7Day)
			$csHTML7Day = "<a class='blueredlink' href='./ad_cs_home.php?view=search&starttime=$startTime&endtime=$endTime&bykeywords=" . urlencode($CustomerEmail) . "'>Between 2 and 7 Days (<b>$csCount7Day</b>)</a>";
		else
			$csHTML7Day = "Between 2 and 7 Days (0)";
		
		$startTime = time() - (30*60*60*24);
		$endTime = time() - (7*60*60*24);
		$csCount30Day = CustService::GetNumberOfCSitemsFromEmail($dbCmd, $CustomerEmail, $startTime, $endTime);
		if($csCount30Day)
			$csHTML30Day = "<a class='blueredlink' href='./ad_cs_home.php?view=search&starttime=$startTime&endtime=$endTime&bykeywords=" . urlencode($CustomerEmail) . "'>Between 7 and 30 Days (<b>$csCount30Day</b>)</a>";
		else
			$csHTML30Day = "Between 7 and 30 Days (0)";
		
		$startTime = 0;
		$endTime = time() - (30*60*60*24);
		$csCountOld = CustService::GetNumberOfCSitemsFromEmail($dbCmd, $CustomerEmail, $startTime, $endTime);
		if($csCountOld)
			$csHTMLOld = "<a class='blueredlink' href='./ad_cs_home.php?view=search&starttime=$startTime&endtime=$endTime&bykeywords=" . urlencode($CustomerEmail) . "'>Greater than 30 Days (<b>$csCountOld</b>)</a>";
		else
			$csHTMLOld = "Greater than 30 Days (0)";
	
	
		$retHTML = '
		  <table width="176" border="0" cellspacing="0" cellpadding="2" bgcolor="#CC6600" style="-moz-opacity:60%;filter:progid:DXImageTransform.Microsoft.dropShadow(Color=#999999,offX=3,offY=3)">
		    <tr> 
		      <td> 
			<table width="100%" border="0" cellspacing="0" cellpadding="4" bgcolor="#FFFFFF">
			  <tr> 
			    <td class="SmallBody" align="center" style="background-image: url(./images/admin/arrival_time_back.png); background-repeat: no-repeat;"><b><u>By Activity Period</u></b><br>
			      <img src="./images/transparent.gif" height="6" width="1"><br>
			      '. $csHTML1Day . '
			      <br>
			      <img src="./images/transparent.gif" height="4" width="1"><br>
			      '. $csHTML7Day . '
			      <br>
			      <img src="./images/transparent.gif" height="4" width="1"><br>
			      '. $csHTML30Day . '
			      <br>
			      <img src="./images/transparent.gif" height="4" width="1"><br>
			      '. $csHTMLOld . '
			      <br>
			      <img src="./images/transparent.gif" height="4" width="1"></td>
			  </tr>
			</table>
		      </td>
		    </tr>
		  </table>
		';
	
		return $retHTML;
	
	}

	// Pass in a Customer service Thread ID... along with a file name and binary data
	// It will insert a record into the DB no matter what... It might not write the file to disk if the file type is illegal
	// Returns the Attachment ID
	static function InsertCSattachment(DbCmd $dbCmd, $CSThreadID, $fileName, &$fileData){
		
		// Don't try to insert attachments without a filename (yes it happened once).
		if(empty($fileName))
			return;

		$mysql_timestamp = date("YmdHis", time());

		//Make sure there are no bad characters in the filename
		$fileName = FileUtil::CleanFileName($fileName);

		$insertArr["csThreadID"] = $CSThreadID;
		$insertArr["filename"] = $fileName;
		$insertArr["filesize"] = strlen($fileData);
		$insertArr["DateAttached"] = $mysql_timestamp;

		$attachmentID = $dbCmd->InsertQuery("csattachments", $insertArr);

		//Only if the attachment is legal and under 8 megs... will we write it to disk
		if(FileUtil::CheckIfFileNameIsLegal($fileName) && strlen($fileData) < 26214400){
			//The file name is based off of the Attachment ID... plus whatever file name the customer attached.
			//That will ensrue that it is always unique on the disk and make it harder for others to guess file names
			$FileNameForDisk = Constants::GetFileAttachDirectory() . "/" . $attachmentID . "_" . $fileName;

			// Put image data into the temp file
			$fp = fopen($FileNameForDisk, "w");
			fwrite($fp, $fileData);
			fclose($fp);

		}

		return $attachmentID;
	}

	//When we are attaching files through the web mail system... we put a prefix on the file to keep in unique on disk.  We strip that off when sending the Mime data
	static function StripPrefixFromCustomerServiceAttachment($fname){
	
		return preg_replace("/^CA_\d+_/", "", $fname);
	}
		
	//Returns an array of file names that have been uploaded in the current sesssion.
	static function GetAttachmentsInSession($cs_message_unique){
	
		
		//The variable $cs_message_unique is a unqiue number, everytime somebody clicks on "Reply to customer".
		//We keep a session variable (array) for keeping track of which attachments have been written to disk ... that applies to the "reply window
		//That way when we are sending the message we can figure out what attachments should be sent with it.    It is a 2D array... the bottom dimension is the name of the file.
		
		$attachmentLisArr = WebUtil::GetSessionVar('CSattachmentListArr');
		if(empty($attachmentLisArr))
			$attachmentLisArr = array();
		
		if (!isset($attachmentLisArr[$cs_message_unique]))
			$attachmentLisArr[$cs_message_unique] = array();
	
			
		//Sometimes this variable has been set... but it was not an array.... Maybe it would be more reliable to use the PHP function searialize.. that might handle empy arrays better stored in a session... Anyway, this should fix the problem for now.
		if(is_array($attachmentLisArr[$cs_message_unique]))
			$AttachmentListArray = $attachmentLisArr[$cs_message_unique];
		else
			$AttachmentListArray = array();
			
		return $AttachmentListArray;
	}

	// Tells us how many projects a user has ordered.  
	// Pass in Timestamps if you want to restrict since those dates of activity  
	static function GetNumberOfCSitemsFromEmail(DbCmd $dbCmd, $CustomerEmail, $startTimeStamp=0, $endTimeStamp=0){
		
		$domainObj = Domain::singleton();
		
		$query = "SELECT COUNT(*) FROM csitems USE INDEX (csitems_CustomerEmail) WHERE CustomerEmail like \"" . DbCmd::EscapeLikeQuery($CustomerEmail) . "\" AND Status != 'J' 
					AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs());
		
		if($startTimeStamp){
			WebUtil::EnsureDigit($startTimeStamp);
			$startTimeStamp = date("YmdHis", $startTimeStamp);
		}
		if($endTimeStamp){
			WebUtil::EnsureDigit($endTimeStamp);
			$endTimeStamp = date("YmdHis", $endTimeStamp);
		}
		
		if($startTimeStamp && $endTimeStamp)
			$query .= " AND (LastActivity BETWEEN $startTimeStamp AND $endTimeStamp)";
		else if($startTimeStamp)
			$query .= " AND LastActivity > $startTimeStamp";
		else if($endTimeStamp)
			$query .= " AND LastActivity <= $endTimeStamp";
		
		
		$dbCmd->Query($query);
		
		return $dbCmd->GetValue();
	}
	
	

	// Will look for scan message through all filters... and take the appropriate action upon match
	static function FilterEmail(DbCmd $dbCmd, $CSThreadID){
	
		// This will scrape the "last inserted" from the customer service thread --#
		$dbCmd->Query("SELECT csmessages.FromName, csmessages.FromEmail, csmessages.ToName, 
				csmessages.ToEmail, csmessages.Message, csitems.Subject, csitems.DomainID FROM csitems 
				INNER JOIN csmessages ON csitems.ID = csmessages.csThreadID 
				WHERE csitems.ID=$CSThreadID ORDER BY csmessages.ID DESC LIMIT 1");
	
		if($dbCmd->GetNumRows() == 0)
			return;
				
		$row = $dbCmd->GetRow();
	
		$FromName = $row["FromName"];
		$FromEmail = $row["FromEmail"];
		$ToName = $row["ToName"];
		$ToEmail = $row["ToEmail"];
		$MessageBody = $row["Message"];
		$MsgSubject = $row["Subject"];
		$domainIDofCsItem = $row["DomainID"];
		
		// For the person filters... we are looking for a match in any of these strings... just concatenate to make it easier.
		$PersonStr = $FromName . " " . $FromEmail . " " . $ToName . " " . $ToEmail;
		
		// Now we want to collect a list of filters installed
		$dbCmd->Query("SELECT FilterFrom, FilterSubject, FilterBodyText, Action, ActionValue FROM csemailfilters WHERE DomainID=$domainIDofCsItem");
		
		$FoundMatch = false;
		
		while($row = $dbCmd->GetRow()){
	
			$FilterFrom = $row["FilterFrom"];
			$FilterSubject = $row["FilterSubject"];
			$FilterBody = $row["FilterBodyText"];
			$Action = $row["Action"];
			$ActionValue = $row["ActionValue"];
			
			
			// The following sequence will ensure that the more filters used... the more narrow the search will become.
			if(!empty($FilterFrom)){
	
				if(self::CheckForFilterMatch($FilterFrom, $PersonStr))
					$FoundMatch = true;
				
				if(!empty($FilterSubject) && $FoundMatch){
					if(self::CheckForFilterMatch($FilterSubject, $MsgSubject))
						$FoundMatch = true;
					else
						$FoundMatch = false;
				}
	
				if(!empty($FilterBody) && $FoundMatch){
					if(self::CheckForFilterMatch($FilterBody, $MessageBody))
						$FoundMatch = true;
					else
						$FoundMatch = false;
				}
			}
			else if(!empty($FilterSubject)){
			
				if(self::CheckForFilterMatch($FilterSubject, $MsgSubject))
					$FoundMatch = true;
	
				if(!empty($FilterBody) && $FoundMatch){
					if(self::CheckForFilterMatch($FilterBody, $MessageBody))
						$FoundMatch = true;
					else
						$FoundMatch = false;
				}
			}
			else if(!empty($FilterBody)){
			
				if(self::CheckForFilterMatch($FilterBody, $MessageBody))
					$FoundMatch = true;
			}
			
			if($FoundMatch){
				//Always break out upon the first match.
				break;
			}
		}
		
		
		// Well, the filter hit a match within the message... let's peform an action on it
		if($FoundMatch){
	
			if($Action == "J"){
			
				// It is junk email
				$dbCmd->Query("UPDATE csitems SET Status='J' WHERE ID=$CSThreadID");
				
			}
			else if($Action == "S"){
			
				// It is Spam Email.   Generally we separate Spam and Junk so that we can determine which one is caught by Spam Assassin, vs. our Own Email filters.
				$dbCmd->Query("UPDATE csitems SET Status='S' WHERE ID=$CSThreadID");
				
			}
			else if($Action == "P"){
			
				// It is a Phone Message email
				$dbCmd->Query("UPDATE csitems SET Status='P' WHERE ID=$CSThreadID");
				
				// We want to get the subject of the email 
				// The callerID in a phone message may match up to a phone number in the User database
				// In which case we can automatically assign it to their account.
				$dbCmd->Query("SELECT Subject FROM csitems WHERE ID=$CSThreadID");
				$CSsubject = $dbCmd->GetValue();
				
				$phoneMatch = null;
				$matches = array();
				if(preg_match_all("/from:\s+(\d+)/", $CSsubject, $matches))
					$phoneMatch = $matches[1][0];
			
				if($phoneMatch){
					$dbCmd->Query("SELECT ID FROM users WHERE PhoneSearch='$phoneMatch' AND DomainID=$domainIDofCsItem");
					$UserIDofPhoneMessage = $dbCmd->GetValue();
					
					if($UserIDofPhoneMessage)
						$dbCmd->Query("UPDATE csitems SET UserID=$UserIDofPhoneMessage WHERE ID=$CSThreadID");
				}
			}
			else if($Action == "A"){
			
				// It should be assigned to someone
				$dbCmd->Query("UPDATE csitems SET Ownership=$ActionValue WHERE ID=$CSThreadID");
			}
		}
	}
	
	
	
	// $SrchString may have multiple phrases separated by pipe symbols
	static function CheckForFilterMatch($SrchString, &$Haystack){
	
	
		$FilterArr = split("\|", $SrchString);
	
		foreach($FilterArr as $ThisFilterPhrase){
		
			if(preg_match("/^\s*$/", $ThisFilterPhrase))  //skip blank spaces
				continue;
		
			// Regex needs quotes and forward slashes escaped
			$ThisFilterPhrase = addslashes($ThisFilterPhrase);
		
			// Convert spaces into regex equivalants
			$ThisFilterPhrase = preg_replace("/\s/", "\\s", $ThisFilterPhrase);
			
			// Escape Wild cards.
			$ThisFilterPhrase = preg_replace("/\*/", "\\*", $ThisFilterPhrase);
	
			if(preg_match("/". $ThisFilterPhrase . "/i", $Haystack))
				return true;
	
		}
		return false;
	}
}

<?
require_once("library/Boot_Session.php");

// ----------  Normaly actions going insde of the script that is displaying the page 				-----------
// ----------  However if the action is shared across many different pages then the action should go in here 	-----------

// Make this script be able to run for a while
set_time_limit(900000);
ini_set("memory_limit", "512M");


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


$domainObj = Domain::singleton();

//Make sure they are logged in.



$mysql_timestamp = date("YmdHis");


$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$command = WebUtil::GetInput("command", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$returl = WebUtil::GetInput("returl", FILTER_SANITIZE_URL);


// Savign the Domains shouldn't require IP Security... since an Admin may be visiting the IP Security Page and need to change domains.
if(!empty($action) && $action == "saveDomains"){
	$AuthObj = new Authenticate(Authenticate::login_general);
	$AuthObj->EnsureGroup("MEMBER");
}
else{
	$AuthObj = new Authenticate(Authenticate::login_ADMIN);
}

$UserID = $AuthObj->GetUserID();


// Sometimes there could be a conflict in HTML if you try to set the value of Form.action .... if "Action" is a hidden input and not a form property... so Make "command" synonyms with "action".
if(empty($action))
	$action = $command;

// Specify which Actions do not require a Security Code for Cross-site Forgery Requests
if(!in_array($action, array("GetArtworkPreviewImageNames", "GetCustomerData", "GetProjectHistoryXML", "TasksShowBeforeReminder", "newcsitem", "saveDomains")))
	WebUtil::checkFormSecurityCode();

$messageThreadObj = new MessageThread();	
	

if($action == "markmessageread"){

	$messagelist = WebUtil::GetInput("messagelist", FILTER_SANITIZE_STRING_ONE_LINE);

	//this var is a pipe separated string of message ids.. split it into pieces
	$MsgIDArr = split("\|", $messagelist);
	
	foreach($MsgIDArr as $ThisMsg){
		if(preg_match("/^\d+$/", $ThisMsg))
			$messageThreadObj->markMessageAsRead($ThisMsg, $UserID);
	}

	header("Location: ". WebUtil::FilterURL($returl));
	exit;
}
else if($action == "runEmailFilters"){

	$domainObj = Domain::singleton();
	
	// First build a list of open CS Thread IDs within the selected domain pool.
	$query = "SELECT ID FROM csitems WHERE (Status!='C' AND Status!='J' AND Status!='S') ";	
	$query .= " AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs());
	
	$dbCmd->Query($query);
	$csItemsArr = $dbCmd->GetValueArr();
	
	foreach($csItemsArr as $csitem_id){
		CustService::FilterEmail($dbCmd, $csitem_id);
	}

	header("Location: ". WebUtil::FilterURL($returl));
	exit;
}
else if($action == "StatusChange"){

	$projectid = WebUtil::GetInput("projectid", FILTER_SANITIZE_INT);
	$newstatus = WebUtil::GetInput("newstatus", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);


	// Find out from the URL, if we are supposed to apply this status change to the entire order, or just the project
	if(WebUtil::GetInput("statusrange", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "order")
		$Qualifier = " OrderID=" . Order::GetOrderIDfromProjectID($dbCmd, $projectid);
	else
		$Qualifier = " ID=" . $projectid;
	
	
	$oldStatusOnMasterProject = ProjectOrdered::GetProjectStatus($dbCmd, $projectid);
	$productIDOnMasterProject = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $projectid, "ordered");
	
	
	// Ensure Domain Permissions.
	$orderID = Order::GetOrderIDfromProjectID($dbCmd, $projectid);
	if(!Order::CheckIfUserHasPermissionToSeeOrder($orderID))
		throw new Exception("Error, the Project ID doesn't exist for status change.");
		
		
	// Make sure the status can not change on any project which has been completed or canceled.
	$statusRestrictionSQL = "(Status='F' OR Status='C')";
	
	// Normally a bulk status change for an order will work for only Project IDs that have the same status
	// However... if the status is either 'N'ew or 'P'roofed then we allow the status to change for any of them.
	// .... For example.. someone may have proofed an artwork for business cards and then realized that there is a problem... so they place the order on hold.  If there are other Projects that are still 'N'ew then they should also go to Hold with it.
	if($oldStatusOnMasterProject == "N" || $oldStatusOnMasterProject == "P")
		$statusRequireSQL = "(Status='N' OR Status='P')";
	else
		$statusRequireSQL = "(Status='$oldStatusOnMasterProject')";

	// Loop through 1 or more Projects and appy the Status Change.
	// Also if you are changing the status for an entire order... anything that it modifies must have a matching Product ID
	// ... for example placing all of the business cards on hold does not change any of the Envelopes Status... they get printed at different times and usually have compeltely separate artwork so they should be independent.
	$dbCmd->Query("SELECT ID FROM projectsordered WHERE $Qualifier AND !" . $statusRestrictionSQL . " AND " . $statusRequireSQL . " AND ProductID='$productIDOnMasterProject'");
	while($ProjID = $dbCmd->GetValue()){
	
		// Don't let them changed to a Proofed status unless all of the necessary PDF documents have been pre-generated 
		if($newstatus == "P"){
			if(!ProjectOrdered::CheckIfBleedSettingsAreSaved($dbCmd2, $ProjID))
				WebUtil::PrintAdminError("You can not change the status of an Artwork to proofed unless all of the necessary PDF settings have been recorded on the project.");
		}
		
		// We don't want people to change the status on a mailing batch because there could be a lot of money associated with Postage, etc.  There is not an easy way to issolate a Project once it has been grouped with others, printed, etc.
		if(MailingBatch::checkIfProjectBatched($dbCmd2, $ProjID))
			WebUtil::PrintAdminError("You can not change the status on a Project which has been already included within a Mailing Batch.  If you really need to change the status then cancel the Mailing Batch first.");

		ProjectOrdered::ChangeProjectStatus($dbCmd2, $newstatus, $ProjID);

		ProjectHistory::RecordProjectHistory($dbCmd2, $ProjID, $newstatus, $UserID);
	
	}


	header("Location: " . WebUtil::FilterURL(WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL)) . "&nocache=". time());
	exit;
}


else if($action == "MessageAdmin"){

	$projectid = WebUtil::GetInput("projectid", FILTER_SANITIZE_INT);
	
	
	$orderID = Order::GetOrderIDfromProjectID($dbCmd, $projectid);
	if(!Order::CheckIfUserHasPermissionToSeeOrder($orderID))
		throw new Exception("Error, the Project ID doesn't exist for admin message.");
	

	$adminmessage = WebUtil::GetInput("adminmessage", FILTER_SANITIZE_STRING_ONE_LINE);


	// Find out from the URL, if we are supposed to apply this note to the entire order, or just the project
	if(WebUtil::GetInput("notetype", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "order")
		$Qualifier = " OrderID=" . Order::GetOrderIDfromProjectID($dbCmd, $projectid);
	else
		$Qualifier = " ID=" . $projectid;


	$dbCmd->Query("UPDATE projectsordered SET NotesAdmin=\"".  DbCmd::EscapeSQL($adminmessage) . "\" WHERE " . $Qualifier);

	// Redirect to the order page that they just came from
	header("Location: " . WebUtil::FilterURL($returl) . "&nocache=". time());
	exit;
}
else if($action == "MessageProduction"){

	$projectid = WebUtil::GetInput("projectid", FILTER_SANITIZE_INT);
	
	
	// Ensure Domain Permissions.
	$orderID = Order::GetOrderIDfromProjectID($dbCmd, $projectid);
	if(!Order::CheckIfUserHasPermissionToSeeOrder($orderID))
		throw new Exception("Error, the Project ID doesn't exist for production message.");
		

	$productionmessage = WebUtil::GetInput("productionmessage", FILTER_SANITIZE_STRING_ONE_LINE);

	// Find out from the URL, if we are supposed to apply this note to the entire order, or just the project
	if(WebUtil::GetInput("notetype", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "order")
		$Qualifier = " OrderID=" . Order::GetOrderIDfromProjectID($dbCmd, $projectid);
	else
		$Qualifier = " ID=" . $projectid;
	

	$dbCmd->Query("UPDATE projectsordered SET NotesProduction=\"".  DbCmd::EscapeSQL($productionmessage) . "\" WHERE " . $Qualifier);

	// Redirect to the order page that they just came from
	header("Location: " . WebUtil::FilterURL($returl) . "&nocache=". time());
	exit;
}
else if($action == "completetask"){

	$taskid = WebUtil::GetInput("taskid", FILTER_SANITIZE_INT);
	
	$taskObj = new Task($dbCmd);	
	$taskObj->loadTaskByID($taskid);
	$taskObj->setStatusCompleted(true);
	$taskObj->setDateCompleted();	

	$taskObj->updateTask();

	header("Location: ./" . WebUtil::FilterURL($returl));
	exit;
}


else if($action == "csitem_close"){
	
	$csitemid = WebUtil::GetInput("csitemid", FILTER_SANITIZE_INT);
	
	if(!CustService::CheckIfCSitemExists($dbCmd, $csitemid))
		throw new Exception("Customer Service Item does not exist.");

	// If someone else hasnt already taken ownership 
	if(CustService::CheckIfCSitemIsFree($dbCmd, $csitemid, $UserID))
		$dbCmd->UpdateQuery("csitems", array("Status"=>"C", "Ownership"=>$UserID, "LastActivity"=>$mysql_timestamp), "ID=$csitemid");

	header("Location: ./" . WebUtil::FilterURL($returl));
	exit;
}
else if($action == "csitem_needsassistance"){

	$csitemid = WebUtil::GetInput("csitemid", FILTER_SANITIZE_INT);
	
	if(!CustService::CheckIfCSitemExists($dbCmd, $csitemid))
		throw new Exception("Customer Service Item does not exist.");

	// If someone else hasnt already taken ownership
	if(CustService::CheckIfCSitemIsFree($dbCmd, $csitemid, $UserID))
		$dbCmd->UpdateQuery("csitems", array("Status"=>"H", "Ownership"=>$UserID, "LastActivity"=>$mysql_timestamp), "ID=$csitemid");

	header("Location: ./" . WebUtil::FilterURL($returl));
	exit;
}


else if($action == "closeasjunk"){

	$csitemid = WebUtil::GetInput("csitemid", FILTER_SANITIZE_INT);
	
	if(!CustService::CheckIfCSitemExists($dbCmd, $csitemid))
		throw new Exception("Customer Service Item does not exist.");

	//Set Status to "J" for 'Junk'
	$dbCmd->UpdateQuery("csitems", array("Status"=>"J", "Ownership"=>$UserID, "LastActivity"=>$mysql_timestamp), "ID=$csitemid");

	CustService::RemoveRawTextFile($dbCmd, $csitemid);

	header("Location: ./" . WebUtil::FilterURL($returl));
	exit;
}
else if($action == "csitem_assign"){


	// Multi-select HTML list menu creates an array.
	$assign = WebUtil::GetInputArr("assign", FILTER_SANITIZE_INT);		
		
	// If someone another administrator owns this Csitem, then passing this parameter through the URL will allow another admin to override
	$override = WebUtil::GetInput("override", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

	// $assign is a list of checkboxes that comes in an array from the HTML form elements --#
	foreach($assign as $csitem_id){
	
		$csitem_id = intval($csitem_id);
		
		if(!CustService::CheckIfCSitemExists($dbCmd, $csitem_id))
			throw new Exception("Customer Service Item does not exist.");

		if(CustService::CheckIfCSitemIsFree($dbCmd, $csitem_id, $UserID) || !empty($override))
			$dbCmd->UpdateQuery("csitems", array("Ownership"=>$UserID), "ID=$csitem_id");
		
		// If the status is set to a "Phone Message"... we want to change the status to Open if someone takes ownership of it.
		$dbCmd->Query("SELECT Status FROM csitems WHERE ID=$csitem_id AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));
		if($dbCmd->GetValue() == "P")
			$dbCmd->UpdateQuery("csitems", array("Status"=>"O"), "ID=$csitem_id");
	}
	
	

	header("Location: ./" . WebUtil::FilterURL($returl));
	exit;
}

else if($action == "newcsitem"){
	
	$OrderID = WebUtil::GetInput("orderno", FILTER_SANITIZE_INT);
	$customerID = WebUtil::GetInput("customerID", FILTER_SANITIZE_INT);
	$subject = WebUtil::GetInput("subject", FILTER_SANITIZE_STRING_ONE_LINE, "No Subject");
	

	if(empty($OrderID) && empty($customerID))
		throw new Exception("Error creating a new CS Item.  The OrderID and the CustomerID can not both be left blank.");
	
	if(!empty($OrderID)){
		$OrderID = intval($OrderID);
		$customerID = Order::GetUserIDFromOrder($dbCmd, $OrderID);
		$subject = "Order #" . Order::GetHashedOrderNo($OrderID);
		
		if(!Order::CheckIfUserHasPermissionToSeeOrder($OrderID))
			throw new Exception("Can't create a new order number.  The order does not exist.");
			
		$domainID = Order::getDomainIDFromOrder($OrderID);
	}
	else{
		$OrderID = 0;
		$customerID = intval($customerID);
		
		$domainID = UserControl::getDomainIDofUser($customerID);
	}
	

	$userControlObj = new UserControl($dbCmd);
	$userControlObj->LoadUserByID($customerID);
	
	$customername = $userControlObj->getName();
	$customeremail = $userControlObj->getEmail();

	$infoHash["Subject"] = $subject;
	$infoHash["Status"] = "O";
	$infoHash["UserID"] = $customerID;
	$infoHash["Ownership"] = $UserID;
	$infoHash["OrderRef"] = $OrderID;
	$infoHash["DateCreated"] = $mysql_timestamp;
	$infoHash["LastActivity"] = $mysql_timestamp;
	$infoHash["CustomerName"] = $customername;
	$infoHash["CustomerEmail"] = $customeremail;
	$infoHash["DomainID"] = $domainID;

	// Insert into the Database and the function will return what the new csThreadID is
	$CS_ThreadID = CustService::NewCSitem($dbCmd, $infoHash);

	header("Location: ./" . WebUtil::FilterURL($returl));
	exit;
}
else if($action == "ChangeAttendanceStatus"){
	
	$memberAttendanceObj = new MemberAttendance($dbCmd);
	
	
	// An administrator can override the status for another user.
	if(WebUtil::GetInput("overrideuser", FILTER_SANITIZE_INT) && !$AuthObj->CheckForPermission("CHANGE_OTHER_WORKERS_STATUS"))
		throw new Exception("Permission Denied");
	
		
	$newAttendanceStatus = WebUtil::GetInput("StatusChar", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
	
	
	// One of the choices in the Drop Down menu for Status is to be able to logut.
	if($newAttendanceStatus == "logout"){
		header("Location: ./logout.php?redirect=ad_home.php");
		exit;
	}

	// If a user has gone AWOL they can not just change the status within the Drop down menu (or they might not see the AWOL message)
	// They must pass in an override flag to get out of this status.
	$awolAcknowlegement = WebUtil::GetInput("awolAck", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);	


	if(WebUtil::GetInput("overrideuser", FILTER_SANITIZE_INT)){
		$statusMessage = "Changed by " . UserControl::GetNameByUserID($dbCmd, $UserID);
		$userIDtoChange = WebUtil::GetInput("overrideuser", FILTER_SANITIZE_INT);
		
		$domainIDofUser = UserControl::getDomainIDofUser($UserID);
		if(!$AuthObj->CheckIfUserCanViewDomainID($domainIDofUser))
			throw new Exception("Problem with User Domain on override.");
		
		// Administrators can switch a user from AWOL if they want to.
		$awolAcknowlegement = true;
	}
	else{
		$statusMessage = null;
		$userIDtoChange = $UserID;
	}
	
	
	$currentStatus = $memberAttendanceObj->getCurrentStatusOfUser($userIDtoChange);
	
	// Add some recent activity to their account to keep the Cron from finding them as AWOL before they access another order page (or commit some other activity to the website).
	if($awolAcknowlegement && $currentStatus == "W")
		NavigationHistory::recordPageVisit($dbCmd, $userIDtoChange, "TimeSheet", $statusMessage);
	
	
	// When overriding another User's status... it is possible to change them without an AWOL acknowledgement.
	if($currentStatus != "W" || ($currentStatus == "W" && $awolAcknowlegement))
		$memberAttendanceObj->changeStatusForUser($userIDtoChange, $newAttendanceStatus, $statusMessage);

	header("Location: " . WebUtil::FilterURL(WebUtil::GetInput("returl", FILTER_SANITIZE_URL)));
	exit;
}
else if($action == "OverrideAWOLstatus"){
	
	$memberAttendanceObj = new MemberAttendance($dbCmd);

	// If a user is overriding their AWOL Status then they must include short message on what they were up to.
	$overrideMessage = WebUtil::GetInput("message", FILTER_SANITIZE_STRING_ONE_LINE);

	$memberAttendanceObj->overrideAWOLstatus($UserID, $overrideMessage);
	
	header("Location: " . WebUtil::FilterURL(WebUtil::GetInput("returl", FILTER_SANITIZE_URL)));
	exit;
}
else if($action == "ChangeMemberReturnDate"){
	
	$elapsedSeconds = WebUtil::GetInput("secondsElapsed", FILTER_SANITIZE_INT);
	$timeStampWhenLoaded = WebUtil::GetInput("TimeStampLoaded", FILTER_SANITIZE_INT);
	
	$days = WebUtil::GetInput("days", FILTER_SANITIZE_INT);
	$hours = WebUtil::GetInput("hours", FILTER_SANITIZE_INT);
	$minutes = WebUtil::GetInput("minutes", FILTER_SANITIZE_INT);
	

	$memberAttendanceObj = new MemberAttendance($dbCmd);

	// Build a new Timestamp from the number of Seconds that elapsed on the HTML form... along with the Unixtimestamp when the page loaded.
	$newTimeStamp = $timeStampWhenLoaded + $elapsedSeconds + $minutes * 60 + $hours * 60 * 60 + $days * 60 * 60 * 24;
	
	if($newTimeStamp < mktime(1, 1, 1, 1, 1, 2006) || $newTimeStamp > mktime(1, 1, 1, 1, 1, 2037))
		throw new Exception("The new timestamp is out of range.");
		
	$currentStatusOfUser = $memberAttendanceObj->getCurrentStatusOfUser($UserID);
	
	if($currentStatusOfUser == "L" && ($newTimeStamp - time() > (60 * 60 * 4)))
		WebUtil::PrintAdminError("It looks like you made a mistake with setting your 'Return Time' from Lunch. This value can not be greater than 4 hours.");
	
	
	$memberAttendanceObj->setReturnDate($UserID, $newTimeStamp);

	header("Location: " . WebUtil::FilterURL(WebUtil::GetInput("returl", FILTER_SANITIZE_URL)));
	exit;
}
else if($action == "ChangeAutoReply"){
	
	$memberAttendanceObj = new MemberAttendance($dbCmd);
	
	$autoReply = WebUtil::GetInput("AutoReplyFlag", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "Y" ? true : false;
	
	$memberAttendanceObj->setAutoReplyOnUser($UserID, $autoReply);

	header("Location: " . WebUtil::FilterURL(WebUtil::GetInput("returl", FILTER_SANITIZE_URL)));
	exit;
}
else if($action == "CancelMailingBatch"){

	$batchID = WebUtil::GetInput("batchID", FILTER_SANITIZE_INT);
	
	$mailBatchObj = new MailingBatch($dbCmd, $UserID);
	
	$mailBatchObj->loadBatchByID($batchID);
	
	$mailBatchObj->cancelBatch();
	
	header("Location: " . WebUtil::FilterURL(WebUtil::GetInput("returl", FILTER_SANITIZE_URL)));
	exit;
	
}
else if($action == "DownloadOriginalCSVmailing"){

	$batchID = WebUtil::GetInput("batchID", FILTER_SANITIZE_INT);
	
	$mailBatchObj = new MailingBatch($dbCmd, $UserID);
	
	$mailBatchObj->loadBatchByID($batchID);
	
	$csvData = $mailBatchObj->getOriginalDataFile();
	
	$downloadfileName = "OriginalMailingData_BatchID_" . $batchID . "_" . substr(md5($batchID . "extraSequrE"), 10, 20) . ".csv";

	// Put on disk
	$fp = fopen(DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $downloadfileName, "w");
	fwrite($fp, $csvData);
	fclose($fp);

	$fileDownloadLocation = WebUtil::FilterURL(DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $downloadfileName);

	header("Location: " . $fileDownloadLocation);
	exit;
	
}
else if($action == "DownloadImportedCSVmailing"){

	$batchID = WebUtil::GetInput("batchID", FILTER_SANITIZE_INT);
	
	$mailBatchObj = new MailingBatch($dbCmd, $UserID);
	
	$mailBatchObj->loadBatchByID($batchID);
	
	$csvData = $mailBatchObj->getImportedDataFile();
	
	$downloadfileName = "AlreadyImportedMailingData_BatchID_" . $batchID . "_" . substr(md5($batchID . "extraSequrE"), 10, 20) . ".csv";

	// Put on disk
	$fp = fopen(DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $downloadfileName, "w");
	fwrite($fp, $csvData);
	fclose($fp);

	$fileDownloadLocation = WebUtil::FilterURL(DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $downloadfileName . "?nocache=" . time());

	header("Location: " . $fileDownloadLocation);
	exit;
	
}

else if($action == "CompleteMailingBatch"){

	$batchID = WebUtil::GetInput("batchID", FILTER_SANITIZE_INT);
	
	$mailBatchObj = new MailingBatch($dbCmd, $UserID);
	
	$mailBatchObj->loadBatchByID($batchID);
	
	$projectIDlistArr = $mailBatchObj->getUniqueListOfProjectsWithinBatch();
	
	
	print "<html><u>Completing Batch # ". $batchID . "...</u><br>\n                                                                                                                                                                     
	

	                                                                                                                                                                                                                                                                                                              

	                                                                                                                                                                                                                                                                                                                                                                                                                                                                     
	\n

	";
	Constants::FlushBufferOutput();
	
	print "<html>\n";
	
	$mailBatchObj->batchComplete($batchID, "html");
	
	
	foreach($projectIDlistArr as $thisProjectID){
		
		$domainIDofProject = ProjectBase::getDomainIDofProjectRecord($dbCmd2, "ordered", $thisProjectID);
		

		$PaymentsObj = new Payments($domainIDofProject);
		
		ProjectOrdered::ChangeProjectStatus($dbCmd, "F", $thisProjectID);

		//Keep a record of the change
		ProjectHistory::RecordProjectHistory($dbCmd, $thisProjectID, "Project Finished with Mailing Batch # $batchID", $UserID);
		
		$orderNum = Order::GetOrderIDfromProjectID($dbCmd, $thisProjectID);
	
		Order::OrderChangedCheckForPaymentCapture($dbCmd, $orderNum, $PaymentsObj);
	}
	
	
	print "</html>\n<script>document.location='" . WebUtil::FilterURL(WebUtil::GetInput("returl", FILTER_SANITIZE_URL))  . "';\n</script>";
	
	
}

else if($action == "NewMailingBatch"){

	$projectList = WebUtil::GetInput("projectlist", FILTER_SANITIZE_STRING_ONE_LINE);
	
	
	$projectArr = array();
	
	// convert Piped List into an Array
	$listSplitArr = split("\|", $projectList);
	
	foreach($listSplitArr as $thisID){
		$thisID = trim($thisID);
		
		if(preg_match("/^\d+$/", $thisID))
			$projectArr[] = $thisID;
	
	}
	
	
	$mailBatchObj = new MailingBatch($dbCmd, $UserID);

	$resultsWindow = '<html>

		<body bgcolor="#3366CC">
		<form name="batchwindow" method="post" action="./ad_batchwindow_new.php">
		<input type="hidden" name="projectlist" value="' . $projectList . '">
		<input type="hidden" name="command" value="NewMailingBatch">
		<input type="hidden" name="form_sc" value="'.WebUtil::getFormSecurityCode().'">
		</form>
		<br><br>
		<font style="font-size:14px;">{POP_UP_CONTENT}</font><br><br>
		<a href="javascript:document.forms[\'batchwindow\'].submit()"><font face="arial" color="#FFFFFF">Back to Batch Window</font></a>
		<br><br>
		<a href="javascript:self.close();"><font face="arial" color="#FFFFFF">Close Window</font></a>
		<br><br></body><script>{REDIRECT}</script></html>';
	

	
	// If successful... the string will be empty.
	if(!$mailBatchObj->createNewBatchInDB($projectArr)){
		$resultsWindow = preg_replace("/\{POP_UP_CONTENT\}/", '<font face="arial" color="#FFCCCC">' . WebUtil::htmlOutput($mailBatchObj->getErrorMessage()) . '</font>', $resultsWindow);
		$resultsWindow = preg_replace("/\{REDIRECT\}/", "", $resultsWindow);
		print $resultsWindow;
		exit;
	}
	else{
		$resultsWindow = preg_replace("/\{POP_UP_CONTENT\}/", '<font face="arial" color="#FFFFFF"><font style="font-size:24px;"><b>Mailing Batch was Created.</b></font></font>', $resultsWindow);
		$resultsWindow = preg_replace("/\{REDIRECT\}/", 'window.opener.location = "./ad_home.php?";', $resultsWindow);
		print $resultsWindow;
		exit;
	}
	
}

else if($action == "ShowMailingBatches"){
	

	$DaysToRemember = 300;
	$cookieTime = time()+60*60*24 * $DaysToRemember;
	setcookie ("HideMailingBatches", "no", $cookieTime);

	header("Location: " . WebUtil::FilterURL(WebUtil::GetInput("returl", FILTER_SANITIZE_URL)));
	exit;
	
}
else if($action == "HideMailingBatches"){
	
	$DaysToRemember = 300;
	$cookieTime = time()+60*60*24 * $DaysToRemember;
	setcookie ("HideMailingBatches", "yes", $cookieTime);

	header("Location: " . WebUtil::FilterURL(WebUtil::GetInput("returl", FILTER_SANITIZE_URL)));
	exit;
	
}
else if($action == "GetProjectHistoryXML"){

	$errorFlag = false;
	$ErrorDescription = "";


	$projectOrderedID = WebUtil::GetInput("projectOrderedID", FILTER_SANITIZE_INT);
	
	if(!preg_match("/^\d+/", $projectOrderedID)){
		$errorFlag = true;
		$ErrorDescription = "Can not fetch Artwork Project History because the Project ID is incorrect.";
	}
	
	
	$dbCmd->Query("SELECT Note, UNIX_TIMESTAMP(Date) AS Date, UserID FROM projecthistory
					INNER JOIN projectsordered ON projecthistory.ProjectID = projectsordered.ID
					WHERE ProjectID=$projectOrderedID AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));

	if($dbCmd->GetNumRows() == 0){
		$errorFlag = true;
		$ErrorDescription = "There is no project history on P" . $projectOrderedID . " yet.";
	
	}



	header ("Content-Type: text/xml");
	// It seems that when you hit session_start it will send a Pragma: NoCache in the header
	// When comminicating over HTTPS there is a bug with IE.  It can not get the documents after they have finished downloading because they have already expired
	// We can allow the file to be cached be sending this header.  When requesting the document you can put a ?noache=timestamp
	header("Pragma: public");


	if($errorFlag){
		print "<?xml version=\"1.0\" ?>
			<response>
			<success>bad</success>
			<errormessage>". $ErrorDescription ."</errormessage>
			</response>"; 
		exit;
	}
	

	

	print "<?xml version=\"1.0\" ?>
		<response>
		<success>good</success>\n";





	while($row = $dbCmd->GetRow()){
	
		print "<event>\n";
		print "<desc>" . WebUtil::htmlOutput($row["Note"]) . "</desc>\n";
		print "<date>" . date("D, M j, Y g:i a", $row["Date"]) . "</date>\n";
		print "<person>" . WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd2, $row["UserID"])) . "</person>\n";
		print "</event>\n";
	}

	print "</response>";

	exit;
}
else if($action == "GetArtworkPreviewImageNames"){

	$errorFlag = false;
	$ErrorDescription = "";


	$projectOrderedID = WebUtil::GetInput("projectOrderedID", FILTER_SANITIZE_INT);
	$forceUpdate = WebUtil::GetInput("forceUpdate", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
	
	if(!preg_match("/^\d+/", $projectOrderedID)){
		$errorFlag = true;
		$ErrorDescription = "Can not fetch Artwork Preview Names because the Project ID is incorrect.";
	}
	
	
	$orderID = Order::GetOrderIDfromProjectID($dbCmd, $projectOrderedID);
	if(!Order::CheckIfUserHasPermissionToSeeOrder($orderID)){
		$errorFlag = true;
		$ErrorDescription = "Can not fetch Artwork Preview Names because the Order does not exist.";
	}

	
	
	$artworkPreviewObj = new ArtworkPreview($dbCmd);
	$fileNamesArr = $artworkPreviewObj->getArrayOfFileNames($projectOrderedID, "ordered", "relative");
	
	// In case there is no artwork image.. Generate it in real time if the "forceUpdate" parameter was sent in the URL..
	if(empty($fileNamesArr) && $forceUpdate){
	
		// Record the last "forced update"... just in case the server is loaded up and the Javascript starts polling too quick on a big background.
		// This will keep only 1 forced update per project (in a row).
		$lastUpdatedProjectID = WebUtil::GetSessionVar("LastProjectPreviewUpdate");
		
		if($lastUpdatedProjectID != $projectOrderedID){
		
			WebUtil::SetSessionVar( "LastProjectPreviewUpdate", $lastUpdatedProjectID);
	
			// Delete any entry in the "needs update" table... so that a cron doesn't compete for generating this image..
			$dbCmd->Query("DELETE FROM projectorderedartworkupdate WHERE ProjectOrderedID=" . $projectOrderedID);

			$artworkPreviewObj->updateArtworkPreview($projectOrderedID, "ordered");

			$fileNamesArr = $artworkPreviewObj->getArrayOfFileNames($projectOrderedID, "ordered", "relative");
		}
	}
		
	// Keep a record of the visit to this page by the user.	
	NavigationHistory::recordPageVisit($dbCmd, $UserID, "ArtPreview", $projectOrderedID);



	header ("Content-Type: text/xml");
	// It seems that when you hit session_start it will send a Pragma: NoCache in the header
	// When comminicating over HTTPS there is a bug with IE.  It can not get the documents after they have finished downloading because they have already expired
	// We can allow the file to be cached be sending this header.  When requesting the document you can put a ?noache=timestamp
	header("Pragma: public");


	if(!$errorFlag){
		print "<?xml version=\"1.0\" ?>
			<response>
			<success>good</success>
			<description>" . WebUtil::getPipeDelimetedStringFromArr($fileNamesArr) . "</description>
			</response>"; 
	}
	else{
		print "<?xml version=\"1.0\" ?>
			<response>
			<success>bad</success>
			<description>". $ErrorDescription ."</description>
			</response>"; 
	}
	exit;
}
else if($action == "GetCustomerData"){

	$errorFlag = false;
	$ErrorDescription = "";

	$customerUserID = WebUtil::GetInput("customerUserID", FILTER_SANITIZE_INT);	

	$userControlObj = new UserControl($dbCmd);
	$userControlObj->LoadUserByID($customerUserID);
	
	$newCustomerDate = " ... <font class='ReallySmallBody'>since " . date("M jS Y", $userControlObj->getDateCreated()) . "</font>";

	//header ("Content-Type: text/xml");
	// It seems that when you hit session_start it will send a Pragma: NoCache in the header
	// When comminicating over HTTPS there is a bug with IE.  It can not get the documents after they have finished downloading because they have already expired
	// We can allow the file to be cached be sending this header.  When requesting the document you can put a ?noache=timestamp
	header("Pragma: public");

	$statisticsHTML = "";
	$statisticsHTML .= WebUtil::htmlOutput("Total Spent: \$" . number_format($userControlObj->getTotalCustomerSpend(), 2)) . "<br>";
	$statisticsHTML .= "Total Orders: <b>" . $userControlObj->getTotalOrders() . "</b><br>";
	$statisticsHTML .= WebUtil::htmlOutput("Average Total: \$" . number_format($userControlObj->getAverageOrderTotal(), 2)) . "<br>";
	$statisticsHTML .= "Orders / Month: <b>" . round($userControlObj->getAverageOrdersPerMonth(), 1) . "</b>" . $newCustomerDate . "<br>";
	$statisticsHTML .= WebUtil::htmlOutput("Avg. Msg. / Order: " . $userControlObj->getAverageCommunicationsPerOrder()) . "<br><img src='./images/transparent.gif' width='5' height='5'><br>";

	$statisticsHTML .= "<u>Product(s)</u><font class='ReallySmallBody'>";

	$productIDsOrderedArr = $userControlObj->getProductsOrderedByCustomer();	
	
	
	foreach($productIDsOrderedArr as $thisProductID){
		$statisticsHTML .= "<br>" . WebUtil::htmlOutput(Product::getFullProductName($dbCmd, $thisProductID));
		
		$ordersCount = $userControlObj->getProductCountFromCustomer($thisProductID);
		
		$statisticsHTML .= " : <b>" . number_format($ordersCount) . LanguageBase::GetPluralSuffix($ordersCount, " set", " sets") . "</b>";
		
		$productUnitCount = $userControlObj->getProductUnitCountFromCustomer($thisProductID);
		
		// If the Unit count equals the product count then no sense of showing the extra information.
		if($ordersCount != $productUnitCount)
			$statisticsHTML .= " (" . number_format($productUnitCount) . " pieces)";
	}
	
	$statisticsHTML .= "</font>";

	print "<?xml version=\"1.0\" ?>
		<response>
		<success>good</success>\n";

		print "<statistics>" . WebUtil::htmlOutput($statisticsHTML) . "</statistics>\n";
	
	print "</response>";

	exit;
}
else if($action == "TasksShowBeforeReminder"){
	
	$DaysToRemember = 360;
	$cookieTime = time()+60*60*24 * $DaysToRemember;
	setcookie ("TasksShowBeforeReminder", WebUtil::GetInput("showFlag", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES), $cookieTime);

	header("Location: " . WebUtil::FilterURL(WebUtil::GetInput("returl", FILTER_SANITIZE_URL)));
	exit;
	
}
else if($action == "saveDomains"){
	
	$domainObj = Domain::singleton();
	
	$domainIDsArr = array();
	$tempArr = explode("|", WebUtil::GetInput("domainList", FILTER_SANITIZE_STRING_ONE_LINE));
	
	foreach($tempArr as $thisDomainID){
		
		$thisDomainID = trim($thisDomainID);
		
		if(empty($thisDomainID))
			continue;
			
		// Do some checking before trying to save the Domain IDs.
		// For added security there is also a authentication done inside of the Domain Class when trying to fetch the selected domain IDs
		if(!$AuthObj->CheckIfUserCanViewDomainID($thisDomainID))
			throw new Exception("An invalid Domain ID was passed.");
			
		$domainIDsArr[] = $thisDomainID;
	}
	
	$domainObj->setDomains($domainIDsArr);

	print "OK";
	exit;
}

else{

	throw new Exception("No actions were passed.");
}


?>
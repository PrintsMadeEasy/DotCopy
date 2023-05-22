<?

require_once("library/Boot_Session.php");

// Don't make it look like we are favoring a particular Domain 
Domain::removeTopDomainID();

$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


$showGoofs = WebUtil::GetInput("showGoofs", FILTER_SANITIZE_STRING_ONE_LINE);

set_time_limit(60);


// Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

$domainObj = Domain::singleton();

$memberAttendanceObj = new MemberAttendance($dbCmd);

$message = null;


$t = new Templatex(".");

$t->set_file("origPage", "ad_cs_report-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

// Process Passed Report Parameters
$ReportPeriodIsDateRange = WebUtil::GetInput( "PeriodType", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES ) == "DATERANGE";
$TimeFrameSel = WebUtil::GetInput( "TimeFrame", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "TODAY"  );

$date = getdate();
$startday= WebUtil::GetInput( "DateRangeStartDay", FILTER_SANITIZE_INT, "1" );
$startmonth= WebUtil::GetInput( "DateRangeStartMonth", FILTER_SANITIZE_INT, $date["mon"] );
$startyear= WebUtil::GetInput( "DateRangeStartYear", FILTER_SANITIZE_INT, $date["year"] );
$endday= WebUtil::GetInput( "DateRangeEndDay", FILTER_SANITIZE_INT, $date["mday"] );
$endmonth= WebUtil::GetInput( "DateRangeEndMonth", FILTER_SANITIZE_INT, $date["mon"] );
$endyear= WebUtil::GetInput( "DateRangeEndYear", FILTER_SANITIZE_INT, $date["year"] );

$startday= intval($startday);
$startmonth= intval($startmonth);
$startyear= intval($startyear);
$endday= intval($endday);
$endmonth= intval($endmonth);
$endyear= intval($endyear);



// Format the dates that we want for MySQL for the date range
if( $ReportPeriodIsDateRange )
{
	$start_timestamp = mktime (0,0,0,$startmonth,$startday,$startyear);
	$end_timestamp = mktime (23,59,59,$endmonth,$endday,$endyear);
	
	if(  $start_timestamp >  $end_timestamp  )	
		WebUtil::PrintAdminError("The Date Range is invalid.");
}
else
{
	$ReportPeriod = Widgets::GetTimeFrame( $TimeFrameSel );
	$start_timestamp = $ReportPeriod[ "STARTDATE" ];
	$end_timestamp = $ReportPeriod[ "ENDDATE" ];
}



$start_mysql_timestamp = date("YmdHis", $start_timestamp);
$end_mysql_timestamp  = date("YmdHis", $end_timestamp);

#---- Setup date range selections and and type ---#
$t->set_var( "PERIODTYPETIMEFRAME", $ReportPeriodIsDateRange ? null : "CHECKED" );
$t->set_var( "PERIODISTIMEFRAME", $ReportPeriodIsDateRange ? "false" : "true" );
$t->set_var( "TIMEFRAMESELS", Widgets::BuildTimeFrameSelect( $TimeFrameSel ));
$t->set_var( "PERIODTYPEDATERANGE", $ReportPeriodIsDateRange ? "CHECKED" : null );
$t->set_var( "DATERANGESELS", Widgets::BuildDateRangeSelect( $start_timestamp, $end_timestamp, "D" ));
$t->set_var( "MESSAGE", $message );


$t->allowVariableToContainBrackets("TIMEFRAMESELS");
$t->allowVariableToContainBrackets("DATERANGESELS");
$t->allowVariableToContainBrackets("PERIODTYPEDATERANGE");

$t->set_var( "START_REPORT_DATE_STRING", date("m/d/Y", $start_timestamp));

if( $message )
{
	//Error occurred - discontinue report generation
	$t->discard_block("origPage","ReportBody");
	
	##--- Print Template 
	$t->pparse("OUT","origPage");
	exit;
}



$t->set_var("RETURN_URL", ($_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']) );
$t->set_var("RETURN_URL_ENCODED", urlencode($_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']) );

$t->set_var("STARTTIMESTAMP",  $start_timestamp);
$t->set_var("ENDTIMESTAMP",  $end_timestamp);



// Will hold all of the information for each rep. 
$CustomerRepHash = array();

// Get the total amount of cs items that have have more than 1 message in it.  We don't want to count junk mails and short messages.
$dbCmd->Query( "SELECT * FROM csitems USE INDEX (csitems_LastActivity) WHERE LastActivity BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . "
				AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));
while($row = $dbCmd->GetRow()){
	
	//The key to the hash is the user ID.  We want it to be string value... numbers start from 0 in an array
	$OwnerShipIDstr = strval($row["Ownership"]);
	
	if(!isset($CustomerRepHash[$OwnerShipIDstr])){
		$CustomerRepHash[$OwnerShipIDstr] = array();
		
		//Initialize some values for this user
		$CustomerRepHash[$OwnerShipIDstr]["TotalCSitems"] = 0;
		$CustomerRepHash[$OwnerShipIDstr]["TotalSentMessages"] = 0;
		$CustomerRepHash[$OwnerShipIDstr]["TotalReceivedMessages"] = 0;
		$CustomerRepHash[$OwnerShipIDstr]["TotalReplyTimeSeconds"] = 0;
	}




	$dbCmd2->Query( "SELECT UNIX_TIMESTAMP(DateSent) AS TheDate, CustomerFlag FROM csmessages WHERE csThreadID=" . $row["ID"] . " ORDER BY ID ASC");
	$messageCount = 0;

	while($row2 = $dbCmd2->GetRow()){
		$messageCount++;
		
		//Increment the total cs items if there are 2 or more messages


		if($messageCount == 1){
			$LastTimeStamp = $row2["TheDate"];
		}
		else if($messageCount == 2){
			$CustomerRepHash[$OwnerShipIDstr]["TotalCSitems"]++;
		}
		
		// Calculate the response time from the last message if this message was sent by a customer service rep 
		if($messageCount > 1 && $row2["CustomerFlag"] == "N"){
			$CustomerRepHash[$OwnerShipIDstr]["TotalReplyTimeSeconds"] += intval($row2["TheDate"] - $LastTimeStamp);
			$CustomerRepHash[$OwnerShipIDstr]["TotalSentMessages"]++;
		}
		
		if($row2["CustomerFlag"] == "Y"){
			$CustomerRepHash[$OwnerShipIDstr]["TotalReceivedMessages"]++;
		}
		
		$LastTimeStamp = intval($row2["TheDate"]);
	}	
	
	
}



// Make sure that we have an entry in the array for Each person if they have worked hours on the site but have not answered any customer service
$dbCmd->Query("SELECT DISTINCT(users.ID) FROM memberattendance USE INDEX (memberattendance_StatusDate) INNER JOIN users ON memberattendance.UserID=users.ID 
				WHERE StatusDate BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . "
				AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));
while($thisUserID = $dbCmd->GetValue()){
	if(!isset($CustomerRepHash[$thisUserID])){
		
		$CustomerRepHash[$thisUserID] = array();
		
		$CustomerRepHash[$thisUserID]["TotalCSitems"] = 0;
		$CustomerRepHash[$thisUserID]["TotalSentMessages"] = 0;
		$CustomerRepHash[$thisUserID]["TotalReceivedMessages"] = 0;
		$CustomerRepHash[$thisUserID]["TotalReplyTimeSeconds"] = 0;
	}
}



// Make sure that we have an entry in the array for Each person if they show any activity within the period
$dbCmd->Query("SELECT DISTINCT(users.ID) FROM navigationhistory USE INDEX (navigationhistory_Date) INNER JOIN users ON navigationhistory.UserID=users.ID 
				WHERE Date BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . "
				AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));
while($thisUserID = $dbCmd->GetValue()){
	if(!isset($CustomerRepHash[$thisUserID])){
		
		$CustomerRepHash[$thisUserID] = array();
		
		$CustomerRepHash[$thisUserID]["TotalCSitems"] = 0;
		$CustomerRepHash[$thisUserID]["TotalSentMessages"] = 0;
		$CustomerRepHash[$thisUserID]["TotalReceivedMessages"] = 0;
		$CustomerRepHash[$thisUserID]["TotalReplyTimeSeconds"] = 0;
	}
}


$totalNumberOfCsCorrespondences = 0;
$totalNumberOfChats = 0;
$totalNumberOfProofs = 0;
$totalNumberOfGoofs = 0;
$totalNumberOfMemos = 0;
$totalNumberOfSecondsWorked = 0;

	
// Now build the HTML table.
if(sizeof($CustomerRepHash) == 0){
	$t->set_block("origPage", "EmptyResults","EmptyResultsout");
	$t->set_var("EmptyResultsout", "No Results Found");
}
else{
	
	
	$t->set_var("START_REPORT_DESCRIPTION", date("D, M j, Y, ", $start_timestamp));
	$t->set_var("END_REPORT_DESCRIPTION", date("D, M j, Y, ", $end_timestamp));


	$t->set_block("origPage","performanceBL","performanceBLout");

	foreach($CustomerRepHash as $thisUserID => $CSRepHash){
		
		$csAgentName = UserControl::GetNameByUserID($dbCmd, $thisUserID);
		
		if(strtoupper($csAgentName) == "SYSTEM")
			continue;
	
		$csAgentName = WebUtil::htmlOutput($csAgentName);
		
		// If they are a Content Editor... then show the number of New Entries and Edits underneath their name.
		$newTemplateCount = ContentTemplate::GetNewContentTemplatesCountByUser($dbCmd, $thisUserID, $start_timestamp, $end_timestamp);
		$editTemplateCount = ContentTemplate::GetEditContentTemplatesCountByUser($dbCmd, $thisUserID, $start_timestamp, $end_timestamp);
		
		$newItemCount = ContentItem::GetNewContentItemsCountByUser($dbCmd, $thisUserID, $start_timestamp, $end_timestamp);
		$editItemCount = ContentItem::GetEditContentItemsCountByUser($dbCmd, $thisUserID, $start_timestamp, $end_timestamp);

	
		if(!empty($newTemplateCount))
			$csAgentName .= "<br><font class='ReallySmallBody' style='color:#000066'>New Cnt. Templates - " . $newTemplateCount . "</font>";
		if(!empty($editTemplateCount))
			$csAgentName .= "<br><font class='ReallySmallBody' style='color:#000066'>Cnt. Templates Edits - " . $editTemplateCount . "</font>";
		if(!empty($newItemCount))
			$csAgentName .= "<br><font class='ReallySmallBody' style='color:#007700'>New Cnt. Items - " . $newItemCount . "</font>";
		if(!empty($editItemCount))
			$csAgentName .= "<br><font class='ReallySmallBody' style='color:#007700'>Cnt. Items Edits - " . $editItemCount . "</font>";
			
	
		$t->set_var("NAME", $csAgentName);
		$t->allowVariableToContainBrackets("NAME");


		// Create a hyperlink over the CS items count
		if($CSRepHash["TotalCSitems"] > 0)
			$totalCSitemsCount = "<a target='CsByUser' href=\"./ad_cs_home.php?view=search&byowner=$thisUserID&bydaysold=7\" class='blueredlink'>" .  $CSRepHash["TotalCSitems"] . "</a>";
		else
			$totalCSitemsCount = $CSRepHash["TotalCSitems"];
		
		
		
		$totalNumberOfCsCorrespondences += $CSRepHash["TotalCSitems"];
		

		// Show the average number of Customer Service Messages per CS Item... as well as the Average data size they are writing.		
		if($AuthObj->CheckForPermission("VIEW_WORKERS_SCHEDULES") && $CSRepHash["TotalCSitems"] > 0){
			
			$dbCmd->Query("SELECT CHAR_LENGTH(Message) AS MessageLength FROM csitems USE INDEX(csitems_LastActivity) INNER JOIN csmessages on csmessages.csThreadID = csitems.ID 
					WHERE csmessages.FromUserID=" . $thisUserID  . " AND csitems.LastActivity BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . " 
					AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));

			$messagesFromCS = $dbCmd->GetNumRows();

			$bytesWritten = 0;
			while($row = $dbCmd->GetRow())
				$bytesWritten += $row["MessageLength"];
				
			if($CSRepHash["TotalCSitems"] > 0)
				$avgMessagesPerCS = round($CSRepHash["TotalSentMessages"] / $CSRepHash["TotalCSitems"], 1);
			else
				$avgMessagesPerCS = 0;
			
			if($messagesFromCS > 0)
				$avgMessageSize = round($bytesWritten / $messagesFromCS) . " B";
			else
				$avgMessageSize = "0 Kb";
				
			$extraCSmessageStr = " - <font class='ReallySmallBody'>Avg. Msg/CS</font> <b>" .  $avgMessagesPerCS . "</b><font class='ReallySmallBody'> (" . $avgMessageSize . ")</font>";

		}
		else{
			$extraCSmessageStr = "";
		}
		
		
		$t->set_var("NO_RESPONSES", "Emails: " . $totalCSitemsCount . $extraCSmessageStr);
		$t->allowVariableToContainBrackets("NO_RESPONSES");

		
		$dbCmd->Query("SELECT COUNT(*) FROM chatthread USE INDEX(chatthread_StartDate) 
				WHERE StartDate BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . " 
				AND CsrUserID=".$thisUserID."
				AND TotalCsrMessages > 0
				AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));
		$thisChatCount = $dbCmd->GetValue();
		$totalNumberOfChats += $thisChatCount;
		
		$t->set_var("CHATS", "<br>Chats: "  . $thisChatCount);



		$dbCmd->Query("SELECT COUNT(*) FROM customermemos USE INDEX(customermemos_Date) INNER JOIN users AS U on customermemos.CustomerUserID = U.ID 
					WHERE CreatedByUserID=$thisUserID AND customermemos.Date BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . "
					AND " . DbHelper::getOrClauseFromArray("U.DomainID", $domainObj->getSelectedDomainIDs()));
		$memoCount = $dbCmd->GetValue();
		$totalNumberOfMemos += $memoCount;
		$t->set_var("NUM_MEMOS", $memoCount);
		
		
		

		
		// Find out how many times the user has a proofed, followed immediately by an "Artwork Problem".
		$artworkGoofsFromUser = 0;

		$dbCmd->Query( "SELECT projecthistory.ID, ProjectID FROM projecthistory USE INDEX(projecthistory_Date) INNER JOIN projectsordered AS PO ON projecthistory.ProjectID = PO.ID 
					WHERE projecthistory.UserID=$thisUserID AND projecthistory.Note='Proofed' AND projecthistory.Date BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . " 
					AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));

		
		// Find out how many proofs were done 
		$totalProofs = $dbCmd->GetNumRows();
		
		$totalNumberOfProofs += $totalProofs;
			
		// This is Processor intensive. So don't do this unless the user really wants to see the Goofs.
		if(!empty($showGoofs)){
			while($projHistoryRow = $dbCmd->GetRow()){
			
				$projectIDfromProofed = $projHistoryRow["ProjectID"];
				$projectHistoryID = $projHistoryRow["ID"];
				
				// We know that the particular ProjectID has been proofed by the user (at some point in the history)
				// Now we want to find out if there is ever an "Artwork Problem" status that follows this (Auto Increment ID greater).
				
				$dbCmd2->Query("SELECT COUNT(*) FROM projecthistory USE INDEX(projecthistory_Date) INNER JOIN projectsordered AS PO ON projecthistory.ProjectID = PO.ID  
								WHERE ProjectID=$projectIDfromProofed AND Note='Artwork Problem' AND projecthistory.ID > $projectHistoryID AND 
								projecthistory.Date BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . "
								AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));
				$countOfArtworkProblem = $dbCmd2->GetValue();
				
				if($countOfArtworkProblem > 0)
					$artworkGoofsFromUser++;
			}
			
			$totalNumberOfGoofs += $artworkGoofsFromUser;
		}
		
		
		// Create a hyperlink over the Proofs count (if there are any)
		if($totalProofs > 0)
			$totalProofsCountLink = "<a href=\"javascript:ShowProofsFromUser($thisUserID);\" class='blueredlink'>" .  $totalProofs . "</a>";
		else
			$totalProofsCountLink = $totalProofs;
		
		
		$t->set_var("PROOFS", $totalProofsCountLink);
		
		
		if(empty($showGoofs)){
			$goofsDescription = "-";
		}
		else{
			if($totalProofs > 0)
				$goofsDescription = $artworkGoofsFromUser . " (" . round(($artworkGoofsFromUser / $totalProofs * 100), 2) . "%)";
			else
				$goofsDescription = "";
		}
		
		$t->set_var("GOOFS", $goofsDescription);
		
		
		
		
		// Found out how many hours the person worked
		$secondsWorked = $memberAttendanceObj->checkHowManySecondsUserWorked($thisUserID, $start_timestamp, $end_timestamp);
	
		$totalNumberOfSecondsWorked += $secondsWorked;
	
		$hoursWorked = round(($secondsWorked / (60 * 60)), 1);
		$minutes = round(($secondsWorked / 60));
		$t->set_var("HOURS_WORKED", $hoursWorked);
		$t->set_var("MINUTES_WORKED", $minutes);
		
		
		usleep(10000);
		
		$t->parse("performanceBLout","performanceBL",true);
	}
	
	$t->set_var("CHATS", "<br><b>Chats: " . $totalNumberOfChats . "</b>");
	$t->set_var("NO_RESPONSES", "<b>Emails: " . $totalNumberOfCsCorrespondences . "</b>");
	$t->set_var("NUM_MEMOS", "<b>" . $totalNumberOfMemos . "</b>");
	$t->set_var("PROOFS", "<b>" . $totalNumberOfProofs . "</b>");
	
	$t->allowVariableToContainBrackets("PROOFS");
	$t->allowVariableToContainBrackets("NUM_MEMOS");
	$t->allowVariableToContainBrackets("NO_RESPONSES");
	$t->allowVariableToContainBrackets("CHATS");
	$t->allowVariableToContainBrackets("GOOFS");
	
	if(!empty($showGoofs))
		$t->set_var("GOOFS", "<b>" . $totalNumberOfGoofs . "</b>");
	else 
		$t->set_var("GOOFS", "<b>-</b>");
	
	$t->set_var("NAME", "<b>Totals</b>");
	$t->allowVariableToContainBrackets("NAME");
	
	$totalHoursWorked = round(($totalNumberOfSecondsWorked / (60 * 60)), 1);
	$totalMinutes = round(($totalNumberOfSecondsWorked / 60));
	$t->set_var("HOURS_WORKED", $totalHoursWorked);
	$t->set_var("MINUTES_WORKED", $totalMinutes);
	$t->parse("performanceBLout","performanceBL",true);

}





// Find out how many proofs were Automatically approved
$dbCmd->Query( "SELECT PO.ID FROM projecthistory USE INDEX(projecthistory_Date) INNER JOIN projectsordered AS PO ON projecthistory.ProjectID = PO.ID 
			WHERE Note LIKE 'Auto Proofed%' AND projecthistory.Date BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . " 
			AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));
$autoProofProjectIdArr = $dbCmd->GetValueArr();
$autoProofProjectIdArr = array_unique($autoProofProjectIdArr);
$t->set_var("AUTO_PROOFS", sizeof($autoProofProjectIdArr));

$orderIdAutoProofArr = array();
foreach($autoProofProjectIdArr as $thisPID){
	$orderIdAutoProofArr[] = Order::GetOrderIDfromProjectID($dbCmd, $thisPID);
}
$orderIdAutoProofArr = array_unique($orderIdAutoProofArr);
$t->set_var("AUTO_PROOFS_ORDERS", sizeof($orderIdAutoProofArr));






$userIDforTimeSheetReport = WebUtil::GetInput("UserIdForSchedule", FILTER_SANITIZE_INT, $UserID);

// Workers can always view their own schedule... but maybe not the schedules of others.
if($UserID != $userIDforTimeSheetReport && !$AuthObj->CheckForPermission("VIEW_WORKERS_SCHEDULES"))
	WebUtil::PrintAdminError("Not Available");
	
	
$domainIDofUserSchedule = UserControl::getDomainIDofUser($userIDforTimeSheetReport);
if(!in_array($domainIDofUserSchedule, $AuthObj->getUserDomainsIDs()))
	WebUtil::PrintAdminError("UserID is not valid.");
	

$t->set_var("USER_ID_SCHEDULE", $userIDforTimeSheetReport);



// If the UserID looking at the Screen is different than the person the Schedule is being displayed for...
// Then give the Administrator a drop down menu so they can change the Status on behalf of another user
// This is useful in case they go AWOL permanently, or go on vacation without notice.  We need a way to change their status.
if($UserID != $userIDforTimeSheetReport && $AuthObj->CheckForPermission("CHANGE_OTHER_WORKERS_STATUS")){

	

	$currentUserStatus = $memberAttendanceObj->getCurrentStatusOfUser($userIDforTimeSheetReport);
	

	$statusArr = array();
	$statusArr["K"] = "Working";
	$statusArr["A"] = "Away";
	$statusArr["L"] = "At Lunch";
	$statusArr["V"] = "On Vacation";
	$statusArr["F"] = "Offline";
	$statusArr["W"] = "AWOL";

	
	if(!in_array($currentUserStatus, array_keys($statusArr))){
		$statusArr["Z"] = "Undefined";
		$currentUserStatus = "Z";
	}


	$t->set_var("USER_STATUS_CHOICES", Widgets::buildSelect($statusArr, $currentUserStatus));
	$t->allowVariableToContainBrackets("USER_STATUS_CHOICES");
	
	$t->set_var("USERID_FOR_SCHEDULE_NAME", WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, $userIDforTimeSheetReport)));
}
else{
	$t->discard_block("origPage","ChangeStatusForAnotherUserBL");
}






// If the user has the choice to view other people's schedules then give them a drop down list.
if($AuthObj->CheckForPermission("VIEW_WORKERS_SCHEDULES")){

	$t->discard_block("origPage","MyScheduleHeaderBasicBL");
	
	$customerServiceIDArr = $AuthObj->GetUserIDsInGroup(array("MEMBER", "CS"));
	$editorIDArr = $AuthObj->GetUserIDsInGroup(array("EDITOR", "CS"));
	
	
	
	
	
	
	$workerUserIDsArr = array_unique(array_merge($customerServiceIDArr, $editorIDArr));
	
	// Filter out the people who are not in the user's selected Domains.
	$tempArr = array();
	foreach($workerUserIDsArr as $thisWorkerID){
		
		$domainIDofUser = UserControl::getDomainIDofUser($thisWorkerID);
		
		if(in_array($domainIDofUser, $domainObj->getSelectedDomainIDs()))
			$tempArr[] = $thisWorkerID;
	}
	
	$workerUserIDsArr = $tempArr;
	

	
	$CSoptionList = array();
	
	foreach($workerUserIDsArr as $thisID)
		$CSoptionList[$thisID] = WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, $thisID));
	
	$t->set_var("EMPLOYEE_LIST", Widgets::buildSelect($CSoptionList, $userIDforTimeSheetReport));
	
	$t->allowVariableToContainBrackets("EMPLOYEE_LIST");
}
else{

	$t->discard_block("origPage","MyScheduleHeaderAdminBL");
}



$workingColor = "EEFFEE";
$notworkingColor = "f9EEEE";
$neutralColor = "f3f3ff";

// Display the Itemized shedule for the user only if they have worked more than 0 seconds.
$secondsWorked = $memberAttendanceObj->checkHowManySecondsUserWorked($userIDforTimeSheetReport, $start_timestamp, $end_timestamp);
if($secondsWorked == 0 || sizeof($CustomerRepHash) == 0){

	// You can't delete the following block if its parent block was already deleted.
	if(sizeof($CustomerRepHash) > 0){
		$t->set_block("origPage", "ScheduleBL","ScheduleBLout");
		$t->set_var("ScheduleBLout", "<br><br>No schedule to display.");
	}
}
else{

	$t->set_block("origPage","StatusDescBL","StatusDescBLout");


	$firstEntryID = $memberAttendanceObj->getfirstEntryID($userIDforTimeSheetReport, $start_timestamp, $end_timestamp);

	// If there are no entries for this user (but they have worked some seconds).  Then they must have had a working status before the start of the report period
	if($firstEntryID == 0){
	
		if($end_timestamp > time())
			$t->set_var("STATUS_TIME", "Between <br>" . date("D, M j, g:i a", $start_timestamp) . "<br>And<br>" . date("D, M j, h:i a", time()));
		else
			$t->set_var("STATUS_TIME", "Entire Report Period");
			
	
		$t->set_var("STATUS_DESC", "There are no status entries within the period.  
						However there was a 'Working' Status in effect before the start of the report. 
						Minutes are being credited for the entire report period.");
		$t->set_var("MINUTES_ACCUM", round(($memberAttendanceObj->getSecondsWorkedAtStartPeriod($userIDforTimeSheetReport, $start_timestamp, $end_timestamp)) / 60));
		$t->set_var("STATUS_COLOR", $neutralColor);
		$t->set_var("ROWSPAN", "1");
		$t->set_var(array("HIDE_ROW_START"=>"", "HIDE_ROW_END"=>""));
		
		
		$t->parse("StatusDescBLout","StatusDescBL",true);
	}
	else{
	
		$startEntryRow = $memberAttendanceObj->getEntryDetails($firstEntryID);
	
		$startingSeconds = $memberAttendanceObj->getSecondsWorkedAtStartPeriod($userIDforTimeSheetReport, $start_timestamp, $end_timestamp);
	
		
		// If they had a working status before the start of the report, then show how much time was accumulated up until the first Status entry
		if($startingSeconds != 0){
			$t->set_var("STATUS_TIME", date("D, M j, g:i a", $start_timestamp));
			$t->set_var("STATUS_DESC", "There was a 'Working' Status in effect before the start of the report. 
							Minutes are being credited from the start of the report until the first Status Timestamp.");
			$t->set_var("MINUTES_ACCUM", round($startingSeconds / 60));
		}
		else{
			$t->set_var("STATUS_TIME", date("D, M j, g:i a", $start_timestamp));
			$t->set_var("STATUS_DESC", "Start of the Report");
			$t->set_var("MINUTES_ACCUM", "");
		}
		
		$t->set_var("STATUS_COLOR", $neutralColor);
		$t->set_var("ROWSPAN", "1");
		$t->set_var(array("HIDE_ROW_START"=>"", "HIDE_ROW_END"=>""));
		
		
		$t->parse("StatusDescBLout","StatusDescBL",true);
		
		
		$rowSpanCounter = 0;
		
		// Loop through all Status Entries within the Period
		$nextEntryID = $firstEntryID;
		while($nextEntryID){
		
			$nextEntryRow = $memberAttendanceObj->getEntryDetails($nextEntryID);
			
			$minutesAccumulated = "";
			
			// If the entry before this had a Row that was still spaning, then Hide the HTML cell with HTML comment tags
			if($rowSpanCounter != 0)
				$t->set_var(array("HIDE_ROW_START"=>" <!-- ", "HIDE_ROW_END"=>" --> "));
			else
				$t->set_var(array("HIDE_ROW_START"=>"", "HIDE_ROW_END"=>""));
				
				
			$t->allowVariableToContainBrackets("HIDE_ROW_START");
			$t->allowVariableToContainBrackets("HIDE_ROW_END");
				
			
			// If this is the start of a new Working Status then find out how much time is accumulated looking forward (aggregating all of the consecutive working statuses).
			// Also Figure out how to show the row Spans in HTML to group Time entries between multiple slots.
			if($memberAttendanceObj->checkIfStatusMeansWorking($nextEntryRow["Status"])){
				
				if($rowSpanCounter == 0){
					$rowSpanCounter = 1 + $memberAttendanceObj->getNumberOfConsecutiveSimilarStatusesAfterID($nextEntryID, $start_timestamp, $end_timestamp);
					$minutesAccumulated = round($memberAttendanceObj->getSecondsWorkedAfterWorkingID($nextEntryID, $start_timestamp, $end_timestamp) / 60);
				}
				else{
					$rowSpanCounter--;
				}
				
				$t->set_var("STATUS_COLOR", $workingColor);
			}
			else{
				$rowSpanCounter = 0;
				
				$t->set_var("STATUS_COLOR", $notworkingColor);
			}
			
			
			$statusDesc = $memberAttendanceObj->getStatusDescriptionFromChar($nextEntryRow["Status"]);
		
			if(!empty($nextEntryRow["Message"]))
				$statusDesc = $statusDesc . ": <font color='#990000'>" . $nextEntryRow["Message"] . "</font></em>";
			
			
			$t->set_var("STATUS_TIME", date("D, M j, g:i a", $nextEntryRow["StatusDate"]));
			$t->set_var("STATUS_DESC", $statusDesc);
			$t->set_var("MINUTES_ACCUM", $minutesAccumulated);
			$t->set_var("ROWSPAN", ($rowSpanCounter + 1));
			$t->parse("StatusDescBLout","StatusDescBL",true);

			$nextEntryID = $memberAttendanceObj->getNextEntryIDforUser($nextEntryID, $start_timestamp, $end_timestamp);
		}
		
		
		// If the report date goes past the present time.... then show them that the report ends at the present.
		if($end_timestamp > time())
			$end_report_timestamp = time();
		else
			$end_report_timestamp = $end_timestamp;
		

		// If the entry before this had a Row that was still spaning, then Hide the HTML cell with HTML comment tags
		if($rowSpanCounter != 0)
			$t->set_var(array("HIDE_ROW_START"=>" <!-- ", "HIDE_ROW_END"=>" --> "));
		else
			$t->set_var(array("HIDE_ROW_START"=>"", "HIDE_ROW_END"=>""));
			
		$t->allowVariableToContainBrackets("HIDE_ROW_START");
		$t->allowVariableToContainBrackets("HIDE_ROW_END");
			
		
		$t->set_var("STATUS_COLOR", $neutralColor);
		$t->set_var("STATUS_TIME", date("D, M j, g:i a", $end_report_timestamp));
		$t->set_var("STATUS_DESC", WebUtil::htmlOutput("End of the Report"));
		$t->set_var("MINUTES_ACCUM", "");
		$t->set_var("ROWSPAN", "1");
		$t->parse("StatusDescBLout","StatusDescBL",true);
		
	}
	
	
	$t->set_var("TOTAL_HOURS_WORKED", round(($secondsWorked / (60 * 60)), 1));
	
	$t->set_var("TOTAL_MINUTES_WORKED", round($secondsWorked / 60));
	
	
	

}

if(!empty($showGoofs))
	$t->discard_block("origPage","ShowGoofsBL");


$t->set_var("CURRENT_URL", WebUtil::FilterURL($_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']));

$t->allowVariableToContainBrackets("STATUS_TIME");

$t->pparse("OUT","origPage");


?>
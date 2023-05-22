<?

require_once("library/Boot_Session.php");

// Don't make it look like we are favoring a particular Domain 
Domain::removeTopDomainID();

$offset = WebUtil::GetInput( "offset", FILTER_SANITIZE_INT);
$bykeywords = trim(WebUtil::GetInput( "bykeywords", FILTER_SANITIZE_STRING_ONE_LINE ));
$bydaysold = WebUtil::GetInput( "bydaysold", FILTER_SANITIZE_INT);
$csitemid = WebUtil::GetInput( "csitemid", FILTER_SANITIZE_INT );
$byowner = WebUtil::GetInput( "byowner", FILTER_SANITIZE_INT);
$view = strtoupper(WebUtil::GetInput( "view", FILTER_SANITIZE_STRING_ONE_LINE, "MY" ));
$starttime = WebUtil::GetInput( "starttime", FILTER_SANITIZE_INT);
$endtime = WebUtil::GetInput( "endtime", FILTER_SANITIZE_INT);

$offset = intval($offset);

$dbCmd = new DbCmd();

$domainObj = Domain::singleton();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("VIEW_CUSTOMER_SERVICE"))
	WebUtil::PrintAdminError("Not Available");

$memberAttendanceObj = new MemberAttendance($dbCmd);

$CustomerServiceName = UserControl::GetNameByUserID($dbCmd, $UserID);


$CurrentURL = "ad_cs_home.php?offset=" . $offset . "&view=" . $view . "&bykeywords=" . urlencode($bykeywords) . "&bydaysold=" . $bydaysold . "&csitemid=" . $csitemid . "&byowner=" . $byowner . "&starttime=" . $starttime . "&endtime=" . $endtime;



$t = new Templatex(".");

$t->set_file("origPage", "ad_cs_home-template.html");


$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());



// Hide the button for viewing email filters unless they have permission to do so.
if(!$AuthObj->CheckForPermission("EMAIL_FILTER")){
	$t->set_block("origPage","EmailFilterBL","EmailFilterBLout");
	$t->set_var(array("EmailFilterBLout"=>""));
}




//  To find out how many There are with each Area   --####
$Count_CS_my = CustService::GetCustomerServiceCount($dbCmd, "MY", $UserID, true);
$Count_CS_unassigned = CustService::GetCustomerServiceCount($dbCmd, "UNASSIGNED", $UserID, true);
$Count_CS_others = CustService::GetCustomerServiceCount($dbCmd, "OTHERS", $UserID, true);
$Count_CS_phone = CustService::GetCustomerServiceCount($dbCmd, "PHONE", $UserID, true);
$Count_CS_help = CustService::GetCustomerServiceCount($dbCmd, "HELP", $UserID, true);




$empty_search_results = true;




// Build the tabs 
$baseTabUrl = "./ad_cs_home.php?view=";
$TabsObj = new Navigation();
$TabsObj->AddTab("MY", "Mine - $Count_CS_my", ($baseTabUrl . "my"));
$TabsObj->AddTab("UNASSIGNED", "Unassigned - $Count_CS_unassigned", ($baseTabUrl . "unassigned"));
$TabsObj->AddTab("OTHERS", "Owned by Others - $Count_CS_others", ($baseTabUrl . "others"));
$TabsObj->AddTab("HELP", "Needs Assistance - $Count_CS_help", ($baseTabUrl . "help"));
$TabsObj->AddTab("PHONE", "Phone Messages - $Count_CS_phone", ($baseTabUrl . "phone"));
$TabsObj->AddTab("SEARCH", "Search", ($baseTabUrl . "search"));
$t->set_var("NAV_BAR_HTML", $TabsObj->GetTabsHTML($view));

$t->allowVariableToContainBrackets("NAV_BAR_HTML");




// This will contain the UserID of a name that we want pre-selected within the drop down menu
$CS_Person_selected = 0;

if($view == "MY"){

	#-- Get a List of CS ID's --#
	$CSIDarr = CustService::GetCustomerServiceCount($dbCmd, "MY", $UserID, false);
	
	CustService::ParseCSBlock($dbCmd, $t, $CSIDarr, "ownedInquiriesBL", "#F3F3FF", $UserID, $CurrentURL);

	$CS_Person_selected = $UserID;

}
if($view == "UNASSIGNED"){

	#-- Get a List of CS ID's --#
	$CSIDarr = CustService::GetCustomerServiceCount($dbCmd, "UNASSIGNED", $UserID, false);


	CustService::ParseCSBlock($dbCmd, $t, $CSIDarr, "openInquiriesBL", "#F3FFF3", $UserID, $CurrentURL);
	
	$CS_Person_selected = $UserID;

}
if($view == "PHONE" ){

	#-- Get a List of CS ID's --#
	$CSIDarr = CustService::GetCustomerServiceCount($dbCmd, "PHONE", $UserID, false);


	CustService::ParseCSBlock($dbCmd, $t, $CSIDarr, "phoneInquiriesBL", "#F3FFF3", $UserID, $CurrentURL);
	
	$CS_Person_selected = $UserID;

}
if($view == "OTHERS" ){

	//We may be limiting to a certain person...
	if($byowner <> "0")
		$CSIDarr = CustService::GetCustomerServiceCount($dbCmd, "MY", $byowner, false);
	else
		$CSIDarr = CustService::GetCustomerServiceCount($dbCmd, "OTHERS", $UserID, false);
	

	CustService::ParseCSBlock($dbCmd, $t, $CSIDarr, "othersInquiriesBL", "#FFFFEE", $UserID, $CurrentURL);

	$CS_Person_selected = $byowner;
	
	// To let us know below if we got any results
	$OthersResultCount = sizeof($CSIDarr);

}
if($view == "HELP" ){

	// Get a List of CS ID's 
	$CSIDarr = CustService::GetCustomerServiceCount($dbCmd, "HELP", $UserID, false);

	CustService::ParseCSBlock($dbCmd, $t, $CSIDarr, "helpInquiriesBL", "#FFF8F8", $UserID, $CurrentURL);

}

if($view == "SEARCH" ){

	$CS_Person_selected = $byowner;


	$t->set_var(array("SEARCH_DAYSOLD"=>$bydaysold));
	$t->set_var(array("SEARCH_STARTTIME"=>$starttime));
	$t->set_var(array("SEARCH_ENDTIME"=>$endtime));
	$t->set_var(array("SEARCH_KEYWORDS"=>WebUtil::htmlOutput(stripslashes($bykeywords))));

	

	// Timestamps can't go too low, before 1970
	if($bydaysold && $bydaysold > 3000)
		$bydaysold = 3000;

	if($starttime)
		$starttimeMysql = date("YmdHis", $starttime);
	if($endtime)
		$endtimeMysql = date("YmdHis", $endtime);
		
	
	// Don't let the query grow too enormous
	if(!$bydaysold && !$starttime && !$endtime && empty($bykeywords) && !$byowner && !$csitemid)
		$bydaysold = 3;


	// This query will get a unique list of csitemids ... using a WHERE clause 
	// When we loop through the result set we can do a nested query to get more info
	$query = "SELECT DISTINCT csitems.ID FROM csitems ";
	

	// We don't want JOIN on the messages table if there is no keyword to search by.
	// This is because there could be an empty CS item.   CS items can be created before any message is written
	if(!WebUtil::ValidateEmail($bykeywords) && !empty($bykeywords))
		$query .= " INNER JOIN csmessages on csitems.ID = csmessages.csThreadID ";
	

	$query .= " WHERE csitems.Status != 'J' AND csitems.Status != 'S' ";  // J means no Junk and no 'S'pam
	
	$query .= " AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs());

	if($bydaysold){
		$dayOldTimeStamp = date("YmdHis", (time() - ($bydaysold * 60*60*24)));
		$query .= " AND csitems.LastActivity > $dayOldTimeStamp ";
	}
	
	if($starttime && $endtime)
		$query .= " AND (csitems.LastActivity BETWEEN $starttimeMysql AND $endtimeMysql) ";
	else if($starttime)
		$query .= " AND csitems.LastActivity > $starttimeMysql ";
	else if($endtime)
		$query .= " AND csitems.LastActivity <= $endtimeMysql ";
	


	if(!empty($bykeywords)){
	
		$matches = array();
		
		//If the keyword is an email address then it is a different kind of search
		if(WebUtil::ValidateEmail($bykeywords)){
			$query .= " AND csitems.CustomerEmail like '" . $bykeywords . "'";
		}
		else if(preg_match("/^U(\d+)$/", $bykeywords, $matches)){
			$query .= " AND UserID = '" . $matches[1] . "'";
		}
		else{

			// Clean up the Keywords
			$bykeywords = trim($bykeywords);
			$bykeywords = preg_replace("/\'/", "", $bykeywords);  //Get rid of single quotes
			$bykeywords = preg_replace('/"/', "", $bykeywords);  //Get rid of double quotes
			$bykeywords = preg_replace('/\\\/', "", $bykeywords);  //Get rid of escape characters

			// Make the keyword list into an array
			$keywordList = split(" ", $bykeywords);

			// Loop through the Keyword List continue building the WHERE clause ---#
			for($i = 0; $i<sizeof($keywordList); $i++){

				$query .= " AND csmessages.Message like '%" . $keywordList[$i] . "%'";
			}
		}
	}
	if(!empty($csitemid))
		$query .= " AND csitems.ID='$csitemid'";
	if($byowner <> "0")
		$query .= " AND csitems.Ownership = $byowner";
	
	$query .= " ORDER BY csitems.LastActivity DESC LIMIT 3000 "; // There is no reason for us to get more than 3000 results or it could load up the DB



	// How many orders to be displayed to the user 
	$NumberOfResultsToDisplay = 5;

	$resultCounter = 0;

	// Get a List of CS ID's
	$CSIDarr = array();
	$dbCmd->Query($query);
	while ($ThisID = $dbCmd->GetValue())
		$CSIDarr[] = $ThisID;



	$SearchResultsDisplayArr = array();

	foreach($CSIDarr as $ThisCSid){

		##-- If we have many matches we only want to display those which are on the current page.  This is figured out by the offset parameter passed in the URL.
		if(($resultCounter >= $offset) && ($resultCounter < ($NumberOfResultsToDisplay + $offset))){

			$empty_search_results = false;

			$SearchResultsDisplayArr[] = $ThisCSid;
		}

		$resultCounter++;
	}

	CustService::ParseCSBlock($dbCmd, $t, $SearchResultsDisplayArr, "searchresultBL", "#FFF3F3", $UserID, $CurrentURL);



	// Set this block so that we can erase the multi-page navigation bar if there aren't multiple pages --#
	$t->set_block("origPage","MultiPageBL","MultiPageBLout");
	$t->set_block("origPage","SecondMultiPageBL","SecondMultiPageBLout");

	#-- This means that we have multiple pages of search results --##
	if($resultCounter > $NumberOfResultsToDisplay){

		#-- What are the name/value pairs AND URL  for all of the subsequent pages --#
		$NV_pairs_URL = "bykeywords=" . urlencode($bykeywords) . "&bydaysold=$bydaysold&byowner=$byowner&view=$view&starttime=$starttime&endtime=$endtime&";
		$BaseURL = "./ad_cs_home.php";

		#-- Get a the navigation of hyperlinks to all of the multiple pages --#
		$NavigateHTML = Navigation::GetNavigationForSearchResults($BaseURL, $resultCounter, $NV_pairs_URL, $NumberOfResultsToDisplay, $offset);


		$t->allowVariableToContainBrackets("NAVIGATE");
		$t->set_var(array("NAVIGATE"=>$NavigateHTML, "RESULT_DESC"=>$resultCounter, "OFFSET"=>$offset));
		$t->parse("MultiPageBLout","MultiPageBL",true);
		$t->parse("SecondMultiPageBLout","SecondMultiPageBL",true);
	}
	else{
		$t->set_var(array("NAVIGATE"=>""));
		$t->set_var(array("MultiPageBLout"=>""));
		$t->set_var(array("SecondMultiPageBLout"=>""));
	}

	

	// If we are searching for just 1 single Customer service item, then we can show any messages associated with it. --#
	if(!empty($csitemid)){

		$CurrentURL = "./ad_cs_home.php?csitemid=" . $csitemid;
		$t->set_var(array("CURRENTURL"=>$CurrentURL));

		$t->set_block("origPage","MessageThreadBL","MessageThreadBLout");
		
		// Extract Inner HTML blocks out of the Block we just extracted.
		$t->set_block( "MessageThreadBL", "CloseMessageLinkBL", "CloseMessageLinkBLout" );

		$messageThreadCollectionObj = new MessageThreadCollection();	
		
		$messageThreadCollectionObj->setUserID($UserID);
		$messageThreadCollectionObj->setRefID($csitemid);
		$messageThreadCollectionObj->setAttachedTo("csitem");
		
		$messageThreadCollectionObj->loadThreadCollection();
		
		$messageThreadCollection = $messageThreadCollectionObj->getThreadCollection();
		
		foreach ($messageThreadCollection as $messageThreadObj) {
			
			$messageThreadHTML = MessageDisplay::generateMessageThreadHTML($messageThreadObj);
					
			$t->set_var(array(
				"MESSAGE_BLOCK"=>$messageThreadHTML,
				"THREAD_SUB"=>WebUtil::htmlOutput($messageThreadObj->getSubject()),
				"THREADID"=>$messageThreadObj->getThreadID()
				));
				
			$t->allowVariableToContainBrackets("MESSAGE_BLOCK");
			
			// Discard the inner blocks (for the Close Message Link) if there are no unread messages.
			$unreadMessageIDs = $messageThreadObj->getUnReadMessageIDs();
			$t->set_var("UNREAD_MESSAGE_IDS", implode("|", $unreadMessageIDs));
			
			if(!empty($unreadMessageIDs))
				$t->parse("CloseMessageLinkBLout", "CloseMessageLinkBL", false );
			else
				$t->set_var("CloseMessageLinkBLout", "");				
			
			$t->parse("MessageThreadBLout","MessageThreadBL", true);	
		}	
		
		if(sizeof($messageThreadCollection) == 0)
			$t->set_var(array("MessageThreadBLout"=>"No messages with this CS item."));
		
			
		// Task Collection --#
			
		$taskCollectionObj = new TaskCollection();
		$taskCollectionObj->limitShowTasksBeforeReminder(true);
		$taskCollectionObj->limitUserID($UserID);

		$taskCollectionObj->limitAttachedToName("csitem");
		$taskCollectionObj->limitAttachedToID($csitemid);
		$taskCollectionObj->limitUncompletedTasksOnly(true);

	
		$taskObjectsArr = $taskCollectionObj->getTaskObjectsArr();

		$tasksDisplayObj = new TasksDisplay($taskObjectsArr);
		$tasksDisplayObj->setTemplateFileName("tasks-template.html");
		$tasksDisplayObj->setReturnURL("./ad_cs_home.php?view=search&csitemid=" . $csitemid);
		$tasksDisplayObj->displayAsHTML($t);		


	}
	else{
		#-- Erase the messaging block --#
		$t->set_block("origPage","EmptyMessageBL","EmptyMessageBLout");
		$t->set_var(array("EmptyMessageBLout"=>""));
		
		$t->set_var("TASKS", "");
	}
}
else{
	$t->set_var("TASKS", "");
}


// Flags we set to true when we know that we want to delete the block of HTML for a particular section
$Delete_my_CS = false;
$Delete_unassigned_CS = false;
$Delete_phone_CS = false;
$Delete_others_CS = false;
$Delete_help_CS = false;
$Delete_search_CS = false;



// This message will go on the center of the screen
$DisplayMessage = "";


if($view == "MY" ){
	$Delete_my_CS = false;
	$Delete_unassigned_CS = true;
	$Delete_phone_CS = true;
	$Delete_others_CS = true;
	$Delete_help_CS = true;
	$Delete_search_CS = true;

	if($Count_CS_my == 0)
		$DisplayMessage = "<br><font size='3' color='#336699'>No Messages</font><br><br><br>";
}
else if($view == "UNASSIGNED" ){
	$Delete_my_CS = true;
	$Delete_unassigned_CS = false;
	$Delete_phone_CS = true;
	$Delete_others_CS = true;
	$Delete_help_CS = true;
	$Delete_search_CS = true;

	if($Count_CS_unassigned == 0)
		$DisplayMessage = "<br><font size='3' color='#336699'>No Messages</font><br><br><br>";
}
else if($view == "OTHERS" ){
	$Delete_my_CS = true;
	$Delete_unassigned_CS = true;
	$Delete_phone_CS = true;
	$Delete_others_CS = false;
	$Delete_help_CS = true;
	$Delete_search_CS = true;

	if($OthersResultCount == 0){
		$Delete_others_CS = true;
		$DisplayMessage = "<br><font size='3' color='#336699'>No Messages</font><br><br><br>";
	}
}
else if($view == "HELP" ){
	$Delete_my_CS = true;
	$Delete_unassigned_CS = true;
	$Delete_phone_CS = true;
	$Delete_others_CS = true;
	$Delete_help_CS = false;
	$Delete_search_CS = true;
	
	if($Count_CS_help == 0)
		$DisplayMessage = "<br><font size='3' color='#336699'>No Messages</font><br><br><br>";
}
else if($view == "SEARCH" ){
	$Delete_my_CS = true;
	$Delete_unassigned_CS = true;
	$Delete_phone_CS = true;
	$Delete_others_CS = true;
	$Delete_help_CS = true;
	$Delete_search_CS = false;

	if($empty_search_results)
		$DisplayMessage = "<br><font size='3' color='#333333'>No Results Found.</font><br><br><br>";

}
else if($view == "PHONE" ){
	$Delete_my_CS = true;
	$Delete_unassigned_CS = true;
	$Delete_phone_CS = false;
	$Delete_others_CS = true;
	$Delete_help_CS = true;
	$Delete_search_CS = true;

	if($empty_search_results)
		$DisplayMessage = "<br><font size='3' color='#333333'>No Results Found.</font><br><br><br>";

}
else{
	print "Error with View Type";
	exit;
}


$t->set_var("DISPLAY_MESSAGE", $DisplayMessage);
$t->allowVariableToContainBrackets("DISPLAY_MESSAGE");


// If any of the counts are 0... then we want to erase the block of HTML for that section.
if($Count_CS_my == 0)
	$Delete_my_CS = true;
if($Count_CS_unassigned == 0)
	$Delete_unassigned_CS = true;
if($Count_CS_phone == 0)
	$Delete_phone_CS = true;
if($Count_CS_others == 0)
	$Delete_others_CS = true;
if($Count_CS_help == 0)
	$Delete_help_CS = true;
if($empty_search_results)
	$Delete_search_CS = true;


if($Delete_my_CS)
	$t->discard_block( "origPage", "EmptyOwnedInquiriesBL");
if($Delete_unassigned_CS)
	$t->discard_block( "origPage", "EmptyOpenInquiriesBL");
if($Delete_phone_CS)
	$t->discard_block( "origPage", "EmptyPhoneInquiriesBL");
if($Delete_others_CS)
	$t->discard_block( "origPage", "EmptyOthersInquiriesBL");
if($Delete_help_CS)
	$t->discard_block( "origPage", "EmptyHelpInquiriesBL");
if($Delete_search_CS)
	$t->discard_block( "origPage", "EmptySearchResultsBL");




// Make a drop down list of customer service agents
$CSuserIDArr = $AuthObj->GetUserIDsInGroup(array("MEMBER", "CS"));

// Filter out only users that this user has permission to see for his domains.
$CSuserIDArr = UserControl::filterUserIDsNotInUserDomainPool($dbCmd, $UserID, $CSuserIDArr);

$NamesCSarr = array();

//On the page owned by others... we want the drop down menu to act a little differently
if($view == "OTHERS"){
	$JavascriptForDropDown = "onChange='DisplayOtherCSitems(this.value)'";

	//Erase the "search for messages" block
	$t->set_block("origPage","SearchKeywordBL","SearchKeywordBLout");
	$t->set_var("SearchKeywordBLout", "");
	
	$NamesCSarr["0"] = "Anybody but me";
	
	//We don't want to include ourselves in the list for "others".
	foreach($CSuserIDArr as $ThisUserID){
		if($UserID != $ThisUserID)
			$NamesCSarr["$ThisUserID"] = UserControl::GetNameByUserID($dbCmd, $ThisUserID) . " - " . CustService::GetCustomerServiceCount($dbCmd, "MY", $ThisUserID, true) . " - (" . $memberAttendanceObj->getCurrentStatusDescriptionOfUser($ThisUserID) . ")";
	}
	

}
else{
	$JavascriptForDropDown = "";
	$NamesCSarr["0"] = "Anybody";

	foreach($CSuserIDArr as $ThisUserID)
		$NamesCSarr["$ThisUserID"] = UserControl::GetNameByUserID($dbCmd, $ThisUserID) . " - (" . $memberAttendanceObj->getCurrentStatusDescriptionOfUser($ThisUserID) . ")";
}
	
	



$OwnerDropDown = Widgets::buildSelect($NamesCSarr, array($CS_Person_selected));
$OwnerDropDown = "<select name='byowner' class='AdminDropDown' $JavascriptForDropDown>" . $OwnerDropDown . "</select>";

	
$t->set_var("CS_DROPDOWN", $OwnerDropDown);

$t->allowVariableToContainBrackets("CS_DROPDOWN");
	

// Set up some the default field values for the search box 
if($view != "SEARCH"){
	$t->set_var(array("SEARCH_DAYSOLD"=>"3"));
	$t->set_var(array("SEARCH_KEYWORDS"=>""));
}





// Show the Customer Service Schedule inside of a DHTML fly-open menu.
$notFoundFlag = true;
$t->set_block("origPage","CsrWorkBL","CsrWorkBLout");
foreach($CSuserIDArr as $ThisUserID){
	
	$csrStatus = $memberAttendanceObj->getCurrentStatusDescriptionOfUser($ThisUserID);
	
	// Don't show people who are Offline.
	if(strtoupper($csrStatus) == "OFFLINE")
		continue;
	if(strtoupper($csrStatus) == "NOT DEFINED YET")
		continue;
		
	$userStatus = $memberAttendanceObj->getCurrentStatusDescriptionOfUser($ThisUserID);
	
	$notFoundFlag = false;
	
	// If the user is Late.. then show their status in red.
	if($memberAttendanceObj->checkIfUserIsTardy($ThisUserID))
		$t->set_var("CSR_STATUS", "<font color='#CC0000'>" . WebUtil::htmlOutput($userStatus) . "</font>");
	else
		$t->set_var("CSR_STATUS", WebUtil::htmlOutput($userStatus));
		
	$t->allowVariableToContainBrackets("CSR_STATUS");
	
	
	// If the user is not working... then try to show a Return Time
	if($memberAttendanceObj->checkIfUserIsWorking($ThisUserID)){
		$t->set_var("CSR_RETURN_TIME", "");
	}
	else{
	
		$returnTime = $memberAttendanceObj->getUnixTimeStampOfReturn($ThisUserID);
		
		// Tells how many minues, days or hours they are away from returning.
		$returnTimeStampDesc = LanguageBase::getTimeDiffDesc($returnTime);
		
		if(empty($returnTime)){
			$t->set_var("CSR_RETURN_TIME", "Not specified.");
		}
		else{
			if($returnTime < time())
				$returnTimeStampDesc = "<font color='#993333'>- " . WebUtil::htmlOutput($returnTimeStampDesc) . "</font>";
			else
				$returnTimeStampDesc = WebUtil::htmlOutput($returnTimeStampDesc);
				
			$t->set_var("CSR_RETURN_TIME", $returnTimeStampDesc);
			$t->allowVariableToContainBrackets("CSR_RETURN_TIME");
		}
	}
	
	
	$t->set_var("CSR_NAME", WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, $ThisUserID)));
	



	$t->parse("CsrWorkBLout","CsrWorkBL", true);
}

if($notFoundFlag)
	$t->set_var("CsrWorkBLout", "");








$t->set_var(array("CURRENT_URL"=>urldecode($CurrentURL)));


$t->pparse("OUT","origPage");



?>
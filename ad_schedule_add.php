<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();

$domainObj = Domain::singleton();


// Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("SCHEDULE_VIEW"))
	WebUtil::PrintAdminError("Not Available");


$t = new Templatex(".");

$t->set_file("origPage", "ad_schedule_add-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

// These date values only come in for "new" events.
$year = WebUtil::GetInput("year", FILTER_SANITIZE_INT);
$month = WebUtil::GetInput("month", FILTER_SANITIZE_INT);
$day = WebUtil::GetInput("day", FILTER_SANITIZE_INT);


$eventSignature = WebUtil::GetInput("eventSignature", FILTER_SANITIZE_STRING_ONE_LINE);
$eventTitleSignature = WebUtil::GetInput("eventTitleSignature", FILTER_SANITIZE_STRING_ONE_LINE);





$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);


$eventObj = new Event();



// If we are trying to edit an "Event Title Signature"
// ... there could be many different "Events Signatures" listed under that.
// If there are no other Event listed under (only 1 Event Signature under the title) ... then just pretend we are editing the event directly.
if(!empty($eventTitleSignature)){
	$eventSignaturesUnderTitleSignature = $eventObj->getEventSignaturesFromTitleSignature($eventTitleSignature);
	
	if(sizeof($eventSignaturesUnderTitleSignature) == 1){
		$eventSignature = current($eventSignaturesUnderTitleSignature);
		$eventTitleSignature = "";
	}
}
	




if(!empty($eventSignature)){
	$eventObj->loadEventBySignature($eventSignature);

	// Since we are editing, get the Date values from the event object instead of from the URL.
	$year = date("Y",$eventObj->getEventDate());
	$month = date("n",$eventObj->getEventDate());
	$day = date("j",$eventObj->getEventDate());
}
	
	
$t->set_var("YEAR", $year);
$t->set_var("MONTH", $month);
$t->set_var("DAY", $day);

$t->set_var("DATE_DESC", date("M jS Y", mktime(0, 0, 0, $month, $day, $year)) );


	
//Find out if we are saving the event to the database
if(!empty($action)){
	
	WebUtil::checkFormSecurityCode();
	
	if($action == "savedata"){
	
		$eventObj->setTitle(WebUtil::GetInput("title", FILTER_SANITIZE_STRING_ONE_LINE));
		$eventObj->setDescription(WebUtil::GetInput("description", FILTER_SANITIZE_STRING_ONE_LINE));
		$eventObj->setDelaysProduction(WebUtil::GetInput("delaysproduction", FILTER_SANITIZE_STRING_ONE_LINE) == "yes" ? TRUE : FALSE);
		$eventObj->setDelaysTransit(WebUtil::GetInput("delaystransit", FILTER_SANITIZE_STRING_ONE_LINE) == "yes" ? TRUE : FALSE);
		$eventObj->setProductID(WebUtil::GetInput("productid", FILTER_SANITIZE_INT));
		$eventObj->setStartMinute(WebUtil::GetInput("startMinute", FILTER_SANITIZE_INT));
		$eventObj->setEndMinute(WebUtil::GetInput("endMinute", FILTER_SANITIZE_INT));
		$eventObj->setEventDate(mktime(3, 3, 3, $month, $day, $year));
		
		//Find out if we are updating an existing Event or adding a new one
		if(empty($eventSignature))
			$eventObj->createEvent();
		else
			$eventObj->updateEvent();
	
			
		$t->set_var("DEFAULT_TITLE", urlencode($eventObj->getTitle()));	
		$t->set_var("HEADER_DESC", "Event Updated");
		$t->set_var("TITLE", WebUtil::htmlOutput($eventObj->getTitle()));
		
			
		$t->discard_block("origPage","EventSignaturesBl");
		$t->discard_block("origPage","AddEditBl");
		
		// Build a Drop Down list allowing them to choose/view an event with matching titles.
		$eventTitlesListMenu = array("0"=>"Edit an Event with matching Title on this date.", "1"=>"---------------------------------------");
		$eventSignaturesUnderTitleSignature = $eventObj->getEventSignaturesFromTitleSignature($eventObj->getTitleSignature());
		
		foreach($eventSignaturesUnderTitleSignature as $thisEventSignature){
			
			$eventObj->loadEventBySignature($thisEventSignature);
			
			$eventDescription = $eventObj->getDescription();
			if(strlen($eventDescription) > 30)
				$eventDescription = substr($eventDescription, 0, 30) . " ... ";
			
			if(empty($eventDescription))
				$eventDescription = "<< Empty >>";
				
			
			$startMinute = $eventObj->getStartMinute();
			
			if(!empty($startMinute))
				$eventDescription = $eventObj->getStartMinuteDisplay() . " - " . $eventObj->getEndMinuteDisplay() . " ::: $eventDescription";
			else
				$eventDescription = "Day Event ::: " . $eventDescription;

			$eventTitlesListMenu[$thisEventSignature] = $eventDescription;
		}
		
		$t->set_var("CHOOSE_ANOTHER_TITLE_LIST", Widgets::buildSelect($eventTitlesListMenu, 0));
		$t->allowVariableToContainBrackets("CHOOSE_ANOTHER_TITLE_LIST");
		
		$t->set_var("EDIT_EVENT_AGAIN_FOCUS_COMMAND", "EventWasUpdated");
		
		
		$t->pparse("OUT","origPage");
		exit;
	}
	else if($action == "deleteEvent"){
	
		$eventObj->deleteEvent();
	
		print "<html><script>window.opener.location = window.opener.location;  self.close();</script></html>";
		exit;
	}
	else{
		throw new Exception("Error, undefined Action.");
	}
}




// -----  Drop down list for Product IDs ---

$allProductionProductIDs = Product::getAllProductionProductIDsInUsersSelectedDomains();

$productIDdropDown = array("0" => "Not Product Related");

$userDomainIDsArr = $domainObj->getSelectedDomainIDs();

foreach($allProductionProductIDs as $thisProductionProductID){
	
	$domainIDofProduct = Product::getDomainIDfromProductID($dbCmd, $thisProductionProductID);
	
	// If we have more than one Domain, then list the domain in front of the Product.
	if(sizeof($userDomainIDsArr) > 1)
		$domainPrefix = $domainObj->getDomainKeyFromID($domainIDofProduct) . "> ";
	else
		$domainPrefix = "";
	
	$productIDdropDown[$thisProductionProductID] = $domainPrefix . Product::getRootProductName($dbCmd, $thisProductionProductID);
}





// We are either
// 1) Looking at an event title which was many Event Signatures listed within
// 2) Editing a specific event signature
// 3) Creating a new Event.
if(!empty($eventTitleSignature)){
	
	$eventSignaturesUnderTitleSignature = $eventObj->getEventSignaturesFromTitleSignature($eventTitleSignature);
	
	$t->discard_block("origPage","AddEditBl");
	$t->discard_block("origPage","EventUpdatedBL");
	
	$t->set_var("HEADER_DESC", "Events with &quot;<font class='SmallBody'><font color='#990000'>".WebUtil::htmlOutput($eventObj->getEventTitleFromTitleSignature($eventTitleSignature))."</font></font>&quot;");
	$t->allowVariableToContainBrackets("HEADER_DESC");
	
	$t->set_var("TITLE_ENCODED", urlencode($eventObj->getEventTitleFromTitleSignature($eventTitleSignature)));	
	

	
	
	$t->set_block("origPage","EventSigBL","EventSigBLout");
	
	foreach($eventSignaturesUnderTitleSignature as $thisEventSignature){
		
		$eventObj->loadEventBySignature($thisEventSignature);
		
		
		$eventDateTimeStamp = $eventObj->getEventDate();
		$t->set_var("YEAR", date("Y",$eventDateTimeStamp));
		$t->set_var("MONTH", date("n",$eventDateTimeStamp));
		$t->set_var("DAY", date("j",$eventDateTimeStamp));
		
		$eventDescription = WebUtil::htmlOutput($eventObj->getDescription());
		if(empty($eventDescription))
			$eventDescription = "<i>Empty</i>";
			
		$t->set_var("EVENT_DESC", $eventDescription);
		$t->set_var("EVENT_SIGNATURE", $thisEventSignature);
		
		$startMinute = $eventObj->getStartMinute();
		$endMinute = $eventObj->getEndMinute();
		
		$newTimeStamp = mktime(0,0,0);
		$startTimeMinuteTimeStamp = $newTimeStamp + 60 * $startMinute;
		$endTimeMinuteTimeStamp = $newTimeStamp + 60 * $endMinute;
		
		if(empty($startMinute) && empty($endMinute)){
			$t->set_var("EVENT_TIME", "");
		}
		else{

			$timeDesc = $eventObj->getStartMinuteDisplay() . " - " . $eventObj->getEndMinuteDisplay();
			
			$durationDesc = LanguageBase::getTimeDiffDesc($startTimeMinuteTimeStamp, $endTimeMinuteTimeStamp);
			
			$t->set_var("EVENT_TIME", WebUtil::htmlOutput($timeDesc) . "<font class='ReallySmallBody'><br>$durationDesc</font>");
			$t->allowVariableToContainBrackets("EVENT_TIME");
		}
		
		$t->parse("EventSigBLout","EventSigBL",true);
	}
	

	$t->set_var("EDIT_EVENT_AGAIN_FOCUS_COMMAND", "EditEventAgain");

	
}
else if(!empty($eventSignature)){
	
	
	$t->discard_block("origPage","EventSignaturesBl");
	$t->discard_block("origPage","EventUpdatedBL");



	// Find out if there are multiple Events linked to this title.
	// If not, get rid of the link allowing them to edit those.
	$eventSignaturesUnderTitleSignature = $eventObj->getEventSignaturesFromTitleSignature($eventObj->getTitleSignature());
	if(sizeof($eventSignaturesUnderTitleSignature) == 1)
		$t->discard_block("origPage","ViewEventGroupLink");	

		
	$t->set_var("HEADER_DESC", "Edit Event for ");
	
	$t->set_var("EVENT_TITLE", WebUtil::htmlOutput($eventObj->getTitle()));
	$t->set_var("EVENT_DESC", WebUtil::htmlOutput($eventObj->getDescription()));
	$t->set_var("EVENT_SIG", $eventSignature);
	$t->set_var("EVENT_TITLE_SIG", $eventObj->getTitleSignature());
	
	$t->set_var("START_MINUTE", $eventObj->getStartMinute());
	$t->set_var("END_MINUTE", $eventObj->getEndMinute());
	
	$t->set_var("START_TIME_DISPLAY", $eventObj->getStartMinuteDisplay());
	$t->set_var("END_TIME_DISPLAY", $eventObj->getEndMinuteDisplay());
	
	
	// Set up the checkboxes 
	$eventObj->getDelaysProduction() ? $DP_check = "checked" : $DP_check = "";
	$eventObj->getDelaysTransit() ? $DT_check = "checked" : $DT_check = "";
			
	$t->set_var("DELAY_PRODUCTION", $DP_check);
	$t->set_var("DELAY_TRANSIT", $DT_check);
	
	$t->set_var("PRODUCT_DROP_DOWN", Widgets::buildSelect($productIDdropDown, $eventObj->getProductID()));
	$t->allowVariableToContainBrackets("PRODUCT_DROP_DOWN");
	
	// Set Focus on the Event Description
	$t->set_var("EDIT_EVENT_AGAIN_FOCUS_COMMAND", "EditEventAgain");
}
else{
	
	$t->discard_block("origPage","EventSignaturesBl");
	$t->discard_block("origPage","EventUpdatedBL");
	$t->discard_block("origPage","ViewEventGroupLink");
	
	$defaultTitle = WebUtil::GetInput("defaultTitle", FILTER_SANITIZE_STRING_ONE_LINE);
	
	$t->set_var("HEADER_DESC", "Add Event on");
	
	$t->set_var("EVENT_TITLE", WebUtil::htmlOutput($defaultTitle));
	$t->set_var("EVENT_DESC", "");
	$t->set_var("EVENT_SIG", "");

	$t->set_var("DELAY_PRODUCTION", "");
	$t->set_var("DELAY_TRANSIT", "");
	
	$t->set_var("START_MINUTE", "");
	$t->set_var("END_MINUTE", "");
	
	$t->set_var("START_TIME_DISPLAY", "");
	$t->set_var("END_TIME_DISPLAY", "");	

	$t->set_var("PRODUCT_DROP_DOWN", Widgets::buildSelect($productIDdropDown, 0));
	$t->allowVariableToContainBrackets("PRODUCT_DROP_DOWN");
	
	// Focus the mouse on the Event Description if we already know what the title is.
	if(empty($defaultTitle))
		$t->set_var("EDIT_EVENT_AGAIN_FOCUS_COMMAND", "new");
	else
		$t->set_var("EDIT_EVENT_AGAIN_FOCUS_COMMAND", "EditEventAgain");
		
	
	//Since we are not editing... then don't allow them to delete the event
	$t->discard_block("origPage", "DeleteBL");
}


if(!$AuthObj->CheckForPermission("ADD_SCHEDULER_DELAYS"))
	$t->discard_block("origPage","HideDelaysBL");




	

$t->pparse("OUT","origPage");



?>
<?

require_once("library/Boot_Session.php");



// Don't make it look like we are favoring a particular Domain 
Domain::removeTopDomainID();


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();




//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("SCHEDULE_VIEW"))
	WebUtil::PrintAdminError("Not Available");


$t = new Templatex(".");

$t->set_file("origPage", "ad_schedule-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$yearDigits = WebUtil::GetInput("year", FILTER_SANITIZE_INT, date('Y'));
$monthDigits = WebUtil::GetInput("month", FILTER_SANITIZE_INT, date('m'));
$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);





// Get a list of days that have Events during this month
$eventSchedulerObj = new EventScheduler();
$eventObj = new Event();


// Build the month
$Month = new Calendar_Month_Weekdays($yearDigits, $monthDigits);

// Construct strings for next/previous links
$PMonth = $Month->prevMonth('object'); // Get previous month as object
$prevLink = $_SERVER['PHP_SELF'].'?view='.$view.'&year='.$PMonth->thisYear().'&month='.$PMonth->thisMonth();
$NMonth = $Month->nextMonth('object');
$nextLink = $_SERVER['PHP_SELF'].'?view='.$view.'&year='.$NMonth->thisYear().'&month='.$NMonth->thisMonth();


$t->set_var("PREVIOUS_MONTH_LINK", $prevLink);
$t->set_var("NEXT_MONTH_LINK", $nextLink);

$t->set_var("CALENDAR_DATE", date("F Y", mktime(0, 0, 0, ($monthDigits + 1), 0, $yearDigits)  ));

// Build the days in the month
$Month->build();


$t->set_block("origPage","DayBL","DayBLout");
$t->set_block("origPage","WeekBL","WeekBLout");



// This array will contain an array entry for every day number having an event
// We want to include all Production Product ID's for which the user has selected in their domain list.
$allProductionProductIDs = Product::getAllProductionProductIDsInUsersSelectedDomains();

$eventSignaturesTitlesInMonthArr = $eventSchedulerObj->getEventsInMonth($yearDigits, $monthDigits, $allProductionProductIDs, EventScheduler::DELAYS_DONT_CARE, EventScheduler::EVENT_SIGNATURE_TITLE);


while ( $Day = $Month->fetch() ) {

	//Clear out the Nested HTML block on the beggining of each week
	if($Day->isFirst()){
		$t->set_var("DayBLout", "");
		$WeekDayNumber = 0;
	}
	else{
		$WeekDayNumber++;
	}


	if($Day->isEmpty()){
		$t->set_var("DAY_NUMBER", "&nbsp;");
		$t->set_var("BACK_IMAGE", "calendar_back_empty.png");
		$t->set_var("EVENT_LIST", "");
		
		$t->parse("DayBLout","DayBL",true);
		
		if($Day->isLast())
			$t->parse("WeekBLout","WeekBL",true);
			
		continue;
	}

	
	
	if($WeekDayNumber == 5 || $WeekDayNumber == 6)
		$t->set_var("BACK_IMAGE", "calendar_back_weekend.png");
	else
		$t->set_var("BACK_IMAGE", "calendar_back_weekday.png");



	// Just The hours, mintues, seconds, don't matter.  Just make the timestamp the middle of the day.
	$unixTimeStampOfThisDay = mktime(12, 0, 0, $monthDigits, $Day->thisDay(), $yearDigits);
	
	
	//Find out if this day has an event
	if($eventSchedulerObj->checkForEventSignatureCachedForDate($unixTimeStampOfThisDay, EventScheduler::EVENT_SIGNATURE_TITLE)){


		// Build a list of events
		$eventHTML = "";
		
		
		foreach($eventSignaturesTitlesInMonthArr as $thisTitleSignature){
			
			
			// Skip event signatures that don't fall on today.
			if(!$eventSchedulerObj->checkIfEventSignatureHasMatchingDateInCache($thisTitleSignature, $unixTimeStampOfThisDay, EventScheduler::EVENT_SIGNATURE_TITLE))
				continue;
			
			$eventTitle = $eventObj->getEventTitleFromTitleSignature($thisTitleSignature);
			
			$productIDofEventTitle = $eventObj->getProductIdFromTitleSignature($thisTitleSignature);
	
			$eventTitle = WebUtil::htmlOutput($eventTitle);
			
			// Show Delay Icons.
			$hasDelays = false;
			if($eventObj->checkIfTitleSignatureHasTransitDelay($thisTitleSignature)){
				$eventHTML .= '<img src="./images/icon-truck.png">&nbsp;';
				$hasDelays = true;
			}
			if($eventObj->checkIfTitleSignatureHasProductionDelay($thisTitleSignature)){
				$eventHTML .= '<img src="./images/icon-proudctionDelay.png">&nbsp;';
				$hasDelays = true;
			}
			
			// Don't let people edit the event if the Event has a Product/Shipment delay and they don't have permission
			if(!$hasDelays || $AuthObj->CheckForPermission("ADD_SCHEDULER_DELAYS"))
				$eventTitle = "<a href='javascript:EditEvent(\"" . $thisTitleSignature . "\")' class='DateNumber'>" . $eventTitle . "</a>";
			
				
			// If this Event is attached to a certain Product. Then Prefix the product name in front.
			if(!empty($productIDofEventTitle))
				$eventTitle = "<br><font class='ReallySmallBody'><u>" . WebUtil::htmlOutput(Product::getRootProductName($dbCmd, $productIDofEventTitle)) . "<u></font><br>" . $eventTitle;
			
			$eventHTML .= $eventTitle;
			
			// Add a Line separator
			$eventHTML .= "<br><img src='./images/admin/calendar_event_line.png'><br><img src='./images/transparent.gif' width='1' height='4'><br>";
				
		}

		
		$t->set_var("EVENT_LIST", $eventHTML);
		$t->allowVariableToContainBrackets("EVENT_LIST");
	}
	else{
		$t->set_var("EVENT_LIST", "");
	}
	

	// Show the Current Day Bold
	$thisD = $unixTimeStampOfThisDay;
	$todaD = time();
	if(date("n", $thisD) == date("n", $todaD) && date("j", $thisD) == date("j", $todaD) && date("y", $thisD) == date("y", $todaD)){
		$todayDisplay = "<font style='font-size:14px' color='#883333'><b>" . WebUtil::htmlOutput($Day->thisDay()) . "</font></b>";
		$t->set_var("BACK_IMAGE", "calendar_back_event.png");
	}
	else{
		$todayDisplay = WebUtil::htmlOutput($Day->thisDay());
	}
	
		

	$t->set_var("DAY_NUMBER", "<a href='javascript:AddEvent(" . WebUtil::htmlOutput($Day->thisDay()) . ")' class='DateNumber'>" . $todayDisplay . "</a>");
	$t->allowVariableToContainBrackets("DAY_NUMBER");
	

	$t->parse("DayBLout","DayBL",true);
	
	if($Day->isLast())
		$t->parse("WeekBLout","WeekBL",true);

}



// If the view is small then just hide the header
if($view == "small")
	$t->set_var("HEADER", "");


$t->set_var("YEAR", $yearDigits);
$t->set_var("VIEW", $view);
$t->set_var("MONTH", $monthDigits);



$t->pparse("OUT","origPage");




?>
<?

require_once("library/Boot_Session.php");

$dbCmd = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("SCHEDULE_VIEW"))
	WebUtil::PrintAdminError("Not Available");


$t = new Templatex(".");

$t->set_file("origPage", "ad_schedule_display-template.html");


$eventSchedulerObj = new EventScheduler();
$eventObj = new Event();


$eventTitleSignature = WebUtil::GetInput("eventTitleSignature", FILTER_SANITIZE_STRING_ONE_LINE);


$eventSignaturesArr = $eventObj->getEventSignaturesFromTitleSignature($eventTitleSignature);


$t->set_block("origPage","ScheduleBl","ScheduleBlout");

foreach($eventSignaturesArr as $thisEventSignature){

	$eventObj->loadEventBySignature($thisEventSignature);
	
	$startMinute = $eventObj->getStartMinute();
	$endMinute = $eventObj->getEndMinute();

	// Figure how wide to make the tables depending on how much of the day the even occupies.
	$totalMintuesInDay = 60 * 24;

	if(empty($startMinute)){
		
		$t->set_var("LEFT_WIDTH", "1%");
		$t->set_var("CENTER_WIDTH", "98%");
		$t->set_var("RIGHT_WIDTH", "1%");
		
		$t->set_var("BAR_BACKGROUND", "#CCCCCC");
		$t->set_var("TIME_DESC", "");
		
	}
	else{
		
		$leftColumnWidth = $startMinute / $totalMintuesInDay;
		$centerColumnWidth = ($endMinute - $startMinute) / $totalMintuesInDay;
		$rightColumnWidth = ($totalMintuesInDay - $endMinute) / $totalMintuesInDay;
		
		$t->set_var("LEFT_WIDTH", round($leftColumnWidth * 100) . "%");
		$t->set_var("CENTER_WIDTH", round($centerColumnWidth * 100) . "%");
		$t->set_var("RIGHT_WIDTH", round($rightColumnWidth * 100) . "%");
		
		$t->set_var("BAR_BACKGROUND", "#6633CC");
		
		
		$newTimeStamp = mktime(0,0,0);
		$startTimeMinuteTimeStamp = $newTimeStamp + 60 * $startMinute;
		$endTimeMinuteTimeStamp = $newTimeStamp + 60 * $endMinute;
		
		$timeDesc = $eventObj->getStartMinuteDisplay() . " - " . $eventObj->getEndMinuteDisplay();
		$durationDesc = LanguageBase::getTimeDiffDesc($startTimeMinuteTimeStamp, $endTimeMinuteTimeStamp);
		$t->set_var("TIME_DESC", "<b>" . WebUtil::htmlOutput($timeDesc) . "</b> <font class='ReallySmallBody'>(".WebUtil::htmlOutput($durationDesc).")</font>&nbsp;&nbsp;&gt;&nbsp;&nbsp;");
		$t->allowVariableToContainBrackets("TIME_DESC");

		
	}
	
	
	$eventDescription = WebUtil::htmlOutput($eventObj->getDescription());
	if(empty($eventDescription))
		$eventDescription = "<i>Empty</i>";
		
	if($eventObj->getDelaysTransit())
		$eventDescription .= '&nbsp;<img src="./images/icon-truck.png">&nbsp;';

	if($eventObj->getDelaysProduction())
		$eventDescription .= '&nbsp;<img src="./images/icon-proudctionDelay.png">&nbsp;';

	$t->set_var("EVENT_DESC", $eventDescription);
	$t->allowVariableToContainBrackets("EVENT_DESC");

	$dateDesc = date("D, M j, Y", $eventObj->getEventDate());
	$dateDesc = "<font class='SmallBody'>$dateDesc</font>";
	
	$t->set_var("SCHEDULE_TITLE", $dateDesc . " - " . WebUtil::htmlOutput($eventObj->getTitle()));
	$t->allowVariableToContainBrackets("SCHEDULE_TITLE");
	
	$t->parse("ScheduleBlout","ScheduleBl",true);
			
}




$t->pparse("OUT","origPage");




?>
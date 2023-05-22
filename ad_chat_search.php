<?

require_once("library/Boot_Session.php");


$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$timeFrame = WebUtil::GetInput("TimeFrame", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "TODAY");
$ReportPeriodIsDateRange = WebUtil::GetInput( "PeriodType", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "TIMEFRAME" ) == "DATERANGE";
$chat_id = WebUtil::GetInput("chat_id", FILTER_SANITIZE_INT);
$chat_user_id = WebUtil::GetInput("user_id", FILTER_SANITIZE_INT);

#-- Format the dates that we want for MySQL for the date range ----#
if( $ReportPeriodIsDateRange )
{
	$date = getdate();
	
	$startday= WebUtil::GetInput( "DateRangeStartDay", FILTER_SANITIZE_INT, "1" );
	$startmonth= WebUtil::GetInput( "DateRangeStartMonth", FILTER_SANITIZE_INT, $date["mon"] );
	$startyear= WebUtil::GetInput( "DateRangeStartYear", FILTER_SANITIZE_INT, $date["year"] );
	
	$endday= WebUtil::GetInput( "DateRangeEndDay", FILTER_SANITIZE_INT, $date["mday"] );
	$endmonth= WebUtil::GetInput( "DateRangeEndMonth", FILTER_SANITIZE_INT, $date["mon"] );
	$endyear= WebUtil::GetInput( "DateRangeEndYear", FILTER_SANITIZE_INT, $date["year"] );
	
	$StartTimeStamp = mktime (0,0,0,$startmonth,$startday,$startyear);
	$EndTimeStamp = mktime (23,59,59,$endmonth,$endday,$endyear);
	
}
else
{
	$ReportPeriod = Widgets::GetTimeFrame( $timeFrame );
	$StartTimeStamp = $ReportPeriod["STARTDATE"];
	$EndTimeStamp = $ReportPeriod["ENDDATE"];
}


if($EndTimeStamp - $StartTimeStamp > (60 * 60 * 24 * 15))
	WebUtil::PrintAdminError("The Date Range can not exceed 2 weeks.");

// Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


$domainObj = Domain::singleton();

if(!$AuthObj->CheckForPermission("CHAT_SEARCH"))
	WebUtil::PrintAdminError("This URL is not available.");

$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


$chatTypes = WebUtil::GetInput("chat_types", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$chatID = WebUtil::GetInput("chat_id", FILTER_SANITIZE_INT);

// Split the characters into arrays 
$chatTypesArr = preg_split('//', $chatTypes, -1, PREG_SPLIT_NO_EMPTY);

$chatOjb = new ChatThread();

$t = new Templatex(".");

$t->set_file("origPage", "ad_chat_search-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");



$t->set_block("origPage","ChatBL","ChatBLout");


if(!empty($chat_id)){	
	$query = "SELECT *, UNIX_TIMESTAMP(StartDate) AS StartDate, UNIX_TIMESTAMP(ClosedDate) AS ClosedDate FROM chatthread WHERE 
				ID=".intval($chat_id)." AND " . DbHelper::getOrClauseFromArray("DomainID", $AuthObj->getUserDomainsIDs()) . " 
			ORDER BY ID DESC";
}
else if(!empty($chat_user_id)){	
	$query = "SELECT *, UNIX_TIMESTAMP(StartDate) AS StartDate, UNIX_TIMESTAMP(ClosedDate) AS ClosedDate FROM chatthread WHERE 
				CustomerUserID=".intval($chat_user_id)." AND " . DbHelper::getOrClauseFromArray("DomainID", $AuthObj->getUserDomainsIDs()) . " 
			ORDER BY ID DESC";
}
else{
	// If we haven't specified what chat types to query upon, then just query on all of them.
	if(empty($chatTypesArr))
		$chatTypeQuery = "";
	else
		$chatTypeQuery = " AND " . DbHelper::getOrClauseFromArray("ChatType", $chatTypesArr);
	
	$query = "SELECT *, UNIX_TIMESTAMP(StartDate) AS StartDate, UNIX_TIMESTAMP(ClosedDate) AS ClosedDate FROM chatthread WHERE 
				(StartDate BETWEEN '" . DbCmd::convertUnixTimeStmpToMysql($StartTimeStamp) . "' AND '" . DbCmd::convertUnixTimeStmpToMysql($EndTimeStamp) ."') 
				AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()) . " 
				$chatTypeQuery
				ORDER BY ID DESC";
}

$dbCmd->Query($query);



$foundResults = false;
while($row = $dbCmd->GetRow()){
	
	$foundResults = true;
	
	$t->set_var("CHAT_ID", $row["ID"]);
	$t->set_var("CHAT_DATE", date("g:i a", $row["StartDate"]));
	$t->set_var("CHAT_TYPE", ChatThread::getChatTypeDesc($row["ChatType"]));
	$t->set_var("SUBJECT", ChatThread::getSubjectDesc($row["Subject"]));
	
	if($row["Status"] == ChatThread::STATUS_Closed)
		$t->set_var("STATUS", "<font color='#000000'>Closed</font>");
	else if($row["Status"] == ChatThread::STATUS_New)
		$t->set_var("STATUS", "<font color='#cc0000'>New</font>");
	else if($row["Status"] == ChatThread::STATUS_Waiting)
		$t->set_var("STATUS", "<font color='#00cc00'><b>Waiting</b></font>");
	else if($row["Status"] == ChatThread::STATUS_Active)
		$t->set_var("STATUS", "<font color='#cc00cc'>Active</font>");
	else 	
		throw new Exception("Undefined Chat thread status.");
		
	$t->allowVariableToContainBrackets("STATUS");
	
	
	// Only show the Domain Logo if the user has more than 1 domain selected.
	if(sizeof($domainObj->getSelectedDomainIDs()) > 1){
		$domainLogoObj = new DomainLogos($row["DomainID"]);
		$domainLogoImg = "<img alt='".Domain::getDomainKeyFromID($row["DomainID"])."' align='absmiddle'  src='./domain_logos/$domainLogoObj->verySmallIcon' border='0'>";
	}
	else{
		$domainLogoImg = "";
	}
	$t->set_var("DOMAIN_LOGO", $domainLogoImg);
	$t->allowVariableToContainBrackets("DOMAIN_LOGO");
	
	
	if(empty($row["CsrUserID"])){
		$t->set_var("CSR_DESC", "*** NOT ASSIGNED ***");
	}
	else{
		$chatCsrObj = ChatCSR::singleton($row["CsrUserID"]);
		$penName = $chatCsrObj->getPenName($row["DomainID"]);
		$realName = UserControl::GetNameByUserID($dbCmd2, $row["CsrUserID"]);
		
		if($penName != $realName)
			$t->set_var("CSR_DESC", WebUtil::htmlOutput($realName . " / " . $penName));
		else 
			$t->set_var("CSR_DESC", WebUtil::htmlOutput($realName));
		
	}
	
	
	if(empty($row["CustomerUserID"])){
		$maxMindObj = new MaxMind();
		
		$locationDetails = "";
		if($maxMindObj->loadIPaddressForLocationDetails($row["CustomerIpAddress"]) && $maxMindObj->getCity() != null){
			$locationDetails .= "<br>" . WebUtil::htmlOutput($maxMindObj->getCity() . ", " . $maxMindObj->getRegion() . " - " . $maxMindObj->getCountry());
		}
			
		$t->set_var("CUSTOMER_DESC", "Not Registered Yet" . $locationDetails);
		$t->allowVariableToContainBrackets("CUSTOMER_DESC");
	}
	else{

		$custRatingDivHTML = "";
			
		// Sho the customer rating (how many stars)
		if($AuthObj->CheckForPermission("CUSTOMER_RATING_OPENORDERS")){
			
			$custUserControlObj = new UserControl($dbCmd2);
			$custUserControlObj->LoadUserByID($row["CustomerUserID"]);

			$customerRating = $custUserControlObj->getUserRating();
			
			$custRatingImgHTML = "<img src='./images/star-rating-" . $customerRating . ".gif' width='74' height='13'>";

			if($customerRating > 0)
				$custRatingDivHTML = "<br><div id='Dcust" . $row["ID"] . "' style='display:inline; cursor: hand; width:74px;' class='hiddenDHTMLwindow' onMouseOver='CustInf(".$row["ID"].", ".$row["CustomerUserID"].", true, \"".WebUtil::getFormSecurityCode()."\")' onMouseOut='CustInf(".$row["ID"].", ".$row["CustomerUserID"].", false, \"".WebUtil::getFormSecurityCode()."\")'>$custRatingImgHTML<span class='hiddenDHTMLwindow' style='visibility:hidden; left:75px; top:-15' id='Scust" . $row["ID"] . "'></span></div><img src='./images/transparent.gif' width='10' height='1'>$domainLogoImg";
			else
				$custRatingDivHTML = "<br>" . $custRatingImgHTML . "<img src='./images/transparent.gif' width='10' height='1'>$domainLogoImg";
		}
		
		
		$t->set_var("CUSTOMER_DESC", "<a href='javascript:Cust(".$row["CustomerUserID"].")'>". WebUtil::htmlOutput(UserControl::GetCompanyOrNameByUserID($dbCmd2, $row["CustomerUserID"]))."</a>" . $custRatingDivHTML);
		$t->allowVariableToContainBrackets("CUSTOMER_DESC");
	}
	
	if(empty($row["OrderID"])){
		$t->set_var("ORDER_LINK", "");
	}
	else{
		$t->set_var("ORDER_LINK", "<br><a href='javascript:Order(".$row["OrderID"].")'>O". $row["OrderID"]."</a>");
		$t->allowVariableToContainBrackets("ORDER_LINK");
	}
	
	
	
	// Show if there was a "CHAT Transfer"...  meaning that more than 1 CSR dealt with the Customer.
	$dbCmd2->Query("SELECT DISTINCT CsrUserID FROM chatmessages WHERE ChatThreadID=" . intval($row["ID"]) . " AND CsrUserID != 0");
	$chatTransfersArr = $dbCmd2->GetValueArr();

	$numberOfCsrs = sizeof($chatTransfersArr);
	if($numberOfCsrs <= 1)
		$t->set_var("TRANSFERS_DESC", "");
	else if($numberOfCsrs == 2)
		$t->set_var("TRANSFERS_DESC", "<br>1 CSR Transfer");
	else
		$t->set_var("TRANSFERS_DESC", "<br>".($numberOfCsrs - 1)." CSR Transfers");
		
	$t->allowVariableToContainBrackets("TRANSFERS_DESC");

	
	
	
	
	
	$t->set_var("DURATION", LanguageBase::getTimeDiffDesc($row["StartDate"], $row["ClosedDate"], true));
	
	$t->set_var("CSR_COUNT", $row["TotalCsrMessages"]);
	$t->set_var("CUSTOMER_COUNT", $row["TotalCustomerMessages"]);
	
	if($row["Status"] == ChatThread::STATUS_Closed)
		$t->set_var("CLOSED_REASON", ChatThread::getClosureDesc($row["ClosedReason"]));
	else 
		$t->set_var("CLOSED_REASON", "");
	

	
	
	$t->parse("ChatBLout","ChatBL",true);
}


if($foundResults){
	$t->discard_block("origPage", "EmptyDateMessageBL");
}
else{ 
	if(!empty($chat_id))
		$t->set_var("EMPTY_MESSAGE", "The chat ID was not found: C".$chat_id);
	else 
		$t->set_var("EMPTY_MESSAGE", "There are no chat threads within the date range you specified.");
		
	$t->discard_block("origPage", "EmptyChatBL");
}











// Build the Drop down to select the Time Frame.
$timeFrameValues = array("TODAY"=>"Today", "YESTERDAY"=>"Yesterday");

$timeFrameValues["2DAYSAGO"] = Widgets::GetTimeFrameText("2DAYSAGO");
$timeFrameValues["3DAYSAGO"] = Widgets::GetTimeFrameText("3DAYSAGO");
$timeFrameValues["4DAYSAGO"] = Widgets::GetTimeFrameText("4DAYSAGO");
$timeFrameValues["5DAYSAGO"] = Widgets::GetTimeFrameText("5DAYSAGO");
$timeFrameValues["6DAYSAGO"] = Widgets::GetTimeFrameText("6DAYSAGO");
$timeFrameValues["7DAYSAGO"] = Widgets::GetTimeFrameText("7DAYSAGO");
$timeFrameValues["8DAYSAGO"] = Widgets::GetTimeFrameText("8DAYSAGO");
$timeFrameValues["9DAYSAGO"] = Widgets::GetTimeFrameText("9DAYSAGO");
$timeFrameValues["10DAYSAGO"] = Widgets::GetTimeFrameText("10DAYSAGO");
$timeFrameValues["11DAYSAGO"] = Widgets::GetTimeFrameText("11DAYSAGO");
$timeFrameValues["12DAYSAGO"] = Widgets::GetTimeFrameText("12DAYSAGO");
$timeFrameValues["13DAYSAGO"] = Widgets::GetTimeFrameText("13DAYSAGO");

$timeFrameValues["THISWEEK"] = "This Week";
$timeFrameValues["LASTWEEK"] ="Last Week";

$t->set_var("TIMEFRAME_SEL",  Widgets::buildSelect($timeFrameValues, $timeFrame));
$t->allowVariableToContainBrackets("TIMEFRAME_SEL");


$t->set_var("PERIODTYPETIMEFRAME", $ReportPeriodIsDateRange ? null : "CHECKED" );
$t->set_var("PERIODTYPEDATERANGE", $ReportPeriodIsDateRange ? "CHECKED" : null );
$t->set_var("PERIODISTIMEFRAME", $ReportPeriodIsDateRange ? "false" : "true" );
$t->set_var("DATERANGESELS", Widgets::BuildDateRangeSelect( $StartTimeStamp, $EndTimeStamp, "D", "DateRange", ""));
$t->allowVariableToContainBrackets("DATERANGESELS");

$t->set_var( "START_REPORT_DATE_STRING", date("m/d/Y", $StartTimeStamp));

$t->set_var("START_REPORT_DAY",  date("j", $StartTimeStamp));
$t->set_var("START_REPORT_MONTH",  date("m", $StartTimeStamp));
$t->set_var("START_REPORT_YEAR",  date("Y", $StartTimeStamp));
$t->set_var("END_REPORT_DAY",  date("j", $EndTimeStamp));
$t->set_var("END_REPORT_MONTH",  date("m", $EndTimeStamp));
$t->set_var("END_REPORT_YEAR",  date("Y", $EndTimeStamp));




$t->pparse("OUT","origPage");





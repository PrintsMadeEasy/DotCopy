<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


// Dead accounts should not be favoring 1 particular domain (if there are multiple selected). 
Domain::removeTopDomainID();


if(!$AuthObj->CheckForPermission("DEAD_ACCOUNTS"))
	throw new Exception("Permission Denied");

$domainObj = Domain::singleton();
	
$t = new Templatex(".");


$t->set_file("origPage", "ad_dead_accounts-template.html");


$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");


$displayCustomerService = WebUtil::GetInput("displayCustomerService", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "Y");
$displayCustomerMemos = WebUtil::GetInput("displayCustomerMemos", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "Y");


// Customer Service Show Radio Buttons
if($displayCustomerService == "N"){
	$t->set_var("CUST_SERV_SHOW_CHECKED",  "");
	$t->set_var("CUST_SERV_HIDE_CHECKED",  "checked");
}
else{
	$t->set_var("CUST_SERV_SHOW_CHECKED",  "checked");
	$t->set_var("CUST_SERV_HIDE_CHECKED",  "");
}

// Customer Memos Show Radio Button
if($displayCustomerMemos == "N"){
	$t->set_var("MEMOS_SHOW_CHECKED",  "");
	$t->set_var("MEMOS_HIDE_CHECKED",  "checked");
}
else{
	$t->set_var("MEMOS_SHOW_CHECKED",  "checked");
	$t->set_var("MEMOS_HIDE_CHECKED",  "");
}






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



$t->allowVariableToContainBrackets("TIMEFRAMESELS");
$t->allowVariableToContainBrackets("DATERANGESELS");
$t->allowVariableToContainBrackets("PERIODTYPEDATERANGE");



$t->set_var( "START_REPORT_DATE_STRING", date("m/d/Y", $start_timestamp));




$t->set_var("RETURN_URL", ($_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']) );
$t->set_var("RETURN_URL_ENCODED", urlencode($_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']) );

$t->set_var("STARTTIMESTAMP",  $start_timestamp);
$t->set_var("ENDTIMESTAMP",  $end_timestamp);






$t->set_var("START_DATE", date("F j, Y", $start_timestamp));
$t->set_var("END_DATE", date("F j, Y", $end_timestamp));


$EmptyList = true;


$t->set_block("origPage","UsersBL","UsersBLout");


$start_mysql_timestamp = date("YmdHis", $start_timestamp);
$end_mysql_timestamp  = date("YmdHis", $end_timestamp);




$dbCmd->Query("SELECT ID FROM users WHERE DateCreated BETWEEN $start_mysql_timestamp AND $end_mysql_timestamp 
				AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()) . "
				ORDER BY ID DESC");

$userCounts = 0;
$userCountsWithSavedProjects = 0;
while($thisUserID = $dbCmd->GetValue()){

	$dbCmd2->Query("SELECT COUNT(*) FROM orders WHERE UserID=" . $thisUserID );
	$orderCount = $dbCmd2->GetValue();
	
	if($orderCount == 0){
		
		$userCounts++;
		
		$domainIDofUser = UserControl::getDomainIDofUser($thisUserID);
		
		// Possibly skip over users that have written into customer service.
		if($displayCustomerService == "N" && CustService::GetCustServCountFromUser($dbCmd2, $thisUserID, 0) != 0)
			continue;
			
		// Possibly skip over rows that have customer memos on them
		if($displayCustomerMemos == "N"){
			$dbCmd2->Query("SELECT COUNT(*) FROM customermemos WHERE CustomerUserID=$thisUserID");
			$customerMemoCount = $dbCmd2->GetValue();
			
			if($customerMemoCount > 0)
				continue;
		}
		
		
		$dbCmd2->Query("SELECT COUNT(*) FROM projectssaved WHERE UserID=" . $thisUserID );
		$savedProjectCount = $dbCmd2->GetValue();
		
		if($savedProjectCount != 0){
			$savedProjectCount = "<a href='javascript:SaveProj(\"".Domain::getWebsiteURLforDomainID(Domain::getDomainIDfromURL())."\", $thisUserID);'>" . $savedProjectCount . "</a>";
			$userCountsWithSavedProjects++;
		}
		
		$t->set_var("SAVED", $savedProjectCount);
		$t->allowVariableToContainBrackets("SAVED");
		
		$t->set_var("MEMOS", CustomerMemos::getLinkDescription($dbCmd2, $thisUserID, true));


		$t->set_var("CS", CustService::GetCustServiceLinkForUser($dbCmd2, $thisUserID, true));
		
		$t->allowVariableToContainBrackets("MEMOS");
		$t->allowVariableToContainBrackets("CS");
		
		$UserControlObj = new UserControl($dbCmd2);
		$UserControlObj->LoadUserByID($thisUserID, false);
		
		
		$t->set_var("USER_NAME", WebUtil::htmlOutput($UserControlObj->getName()));
		$t->set_var("USER_COMPANY", WebUtil::htmlOutput($UserControlObj->getCompany()));
		$t->set_var("USER_STATE", WebUtil::htmlOutput($UserControlObj->getState()));
		
		$UserControlObj->getDateCreated();
		
		
		// Change colors depending on the last activity time... to indicate if the user may still be working, or if they are a good lead.
		$dateLastUsedTimeDiffMinutes = (time() - $UserControlObj->getDateLastUsed()) / 60;
		if($dateLastUsedTimeDiffMinutes < 10)
			$t->set_var("LAST_USED_COLOR", "#cc3300");
		else if($dateLastUsedTimeDiffMinutes < 20)
			$t->set_var("LAST_USED_COLOR", "#FF9900");
		else if($dateLastUsedTimeDiffMinutes < 480)
			$t->set_var("LAST_USED_COLOR", "#009900");
		else if($dateLastUsedTimeDiffMinutes < 2000)
			$t->set_var("LAST_USED_COLOR", "#333377");
		else
			$t->set_var("LAST_USED_COLOR", "#999999");
		
		

		$t->set_var("CREATED", WebUtil::htmlOutput(LanguageBase::getRelativeTimeStampDesc($UserControlObj->getDateCreated())));
		$t->set_var("LAST_USED", WebUtil::htmlOutput(LanguageBase::getTimeDiffDesc(time(), $UserControlObj->getDateLastUsed())));
		
		$t->set_var("CUSTID", $thisUserID);
		
		
		
		// Find out the last Sales Force reaction from the customer.
		$customerMemoObj = new CustomerMemos($dbCmd2);
		$dbCmd2->Query("SELECT CustomerReaction FROM customermemos WHERE LastSalesForceReaction = 'Y' AND CustomerUserID=$thisUserID ORDER BY ID DESC");
		$lastSalesReaction = $dbCmd2->GetValue();
		
		if(empty($lastSalesReaction))
			$t->set_var("LAST_REACTION", "");
		else
			$t->set_var("LAST_REACTION", $customerMemoObj->getCustomerReactionHTML($lastSalesReaction));
		
			$t->allowVariableToContainBrackets("LAST_REACTION");

			
			
		// Only show the Domain Logo if the user has more than 1 domain selected.
		if(sizeof($domainObj->getSelectedDomainIDs()) > 1){
			$userDomainID = UserControl::getDomainIDofUser($thisUserID);
			$domainLogoObj = new DomainLogos($userDomainID);
			$domainLogoImg = "<img alt='".Domain::getDomainKeyFromID($userDomainID)."' align='absmiddle'  src='./domain_logos/$domainLogoObj->verySmallIcon' border='0'>&nbsp;";
		}
		else{
			$domainLogoImg = "";
		}
		
		$t->set_var("DOMLOGO", $domainLogoImg);
		$t->allowVariableToContainBrackets("DOMLOGO");
		
			
		$EmptyList = false;

		$t->parse("UsersBLout","UsersBL",true);
	}
}


if($EmptyList){
	$t->set_block("origPage","NoResultsBL","NoResultsBLout");
	$t->set_var("NoResultsBLout", "<br><br>No Dead Accounts found within this period.");
}


$t->set_var("ACCOUNTS_NUM", $userCounts);



$t->set_var("CUSTOM_MESSAGE", "Number of Accounts with at least 1 Saved Project: <b> $userCountsWithSavedProjects </b> <br><br>");
$t->allowVariableToContainBrackets("CUSTOM_MESSAGE");


$t->set_var("COLUMN2", "Saved");




$t->pparse("OUT","origPage");




?>
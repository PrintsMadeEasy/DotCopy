<?

require_once("library/Boot_Session.php");

// Don't make it look like we are favoring a particular Domain 
Domain::removeTopDomainID();

$dbCmd = new DbCmd();

set_time_limit(100);
ini_set("memory_limit", "512M");


// Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("VISITOR_PATHS_REPORT"))
	throw new Exception("Permission Denied");

$domainObj = Domain::singleton();



$combinedChartParams = WebUtil::GetInput("combinedChartParams", FILTER_SANITIZE_STRING_ONE_LINE);

// If the combined report parameters has been sent... then we want to uncomporess the contents, and redirect the server to the "exploded URL parameters".
// This saves on IE GET URL lengh maximums... and prevents bugs in GraphViz using special characters in a "node url". 
if(!empty($combinedChartParams)){
	header("Location: ./ad_visitorPaths.php?generateReport=yes&redirectParams=yes&" . gzinflate(base64_decode($combinedChartParams)));
	exit;
}



$t = new Templatex(".");

$t->set_file("origPage", "ad_visitorPaths-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");





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


// Format the dates that we want for MySQL for the date range
if( $ReportPeriodIsDateRange )
{
	$start_timestamp = mktime (0,0,0,$startmonth,$startday,$startyear);
	$end_timestamp = mktime (23,59,59,$endmonth,$endday,$endyear);
	
	// Find out if we have explicity set the Start and End unix timestamps instead of individual data components.
	if( array_key_exists( "startTime", $_REQUEST )){
		$start_timestamp  = WebUtil::GetInput("startTime", FILTER_SANITIZE_INT);
		$end_timestamp  = WebUtil::GetInput("endTime", FILTER_SANITIZE_INT);
	}
	
	if(  $start_timestamp >  $end_timestamp  )	
		WebUtil::PrintAdminError("The Date Range is invalid.");
}
else
{
	$ReportPeriod = Widgets::GetTimeFrame( $TimeFrameSel );
	$start_timestamp = $ReportPeriod[ "STARTDATE" ];
	$end_timestamp = $ReportPeriod[ "ENDDATE" ];
}


// If we have redirected the parameters to this Script... then it means there should be a start and stop timestamp.
// We want to make sure that our Date Rang selector remmebers those values instead.
$redirectParams = WebUtil::GetInput("redirectParams", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES );
if(!empty($redirectParams)){
	$ReportPeriodIsDateRange = true;
	$start_timestamp  = WebUtil::GetInput("startTime", FILTER_SANITIZE_INT);
	$end_timestamp  = WebUtil::GetInput("endTime", FILTER_SANITIZE_INT);
}


#---- Setup date range selections and and type ---#
$t->set_var( "PERIODTYPETIMEFRAME", $ReportPeriodIsDateRange ? null : "CHECKED" );
$t->set_var( "PERIODISTIMEFRAME", $ReportPeriodIsDateRange ? "false" : "true" );
$t->set_var( "TIMEFRAMESELS", Widgets::BuildTimeFrameSelect( $TimeFrameSel ));
$t->set_var( "PERIODTYPEDATERANGE", $ReportPeriodIsDateRange ? "CHECKED" : null );
$t->set_var( "DATERANGESELS", Widgets::BuildDateRangeSelect( $start_timestamp, $end_timestamp, "D" ));


$t->allowVariableToContainBrackets("TIMEFRAMESELS");
$t->allowVariableToContainBrackets("DATERANGESELS");
$t->allowVariableToContainBrackets("PERIODTYPEDATERANGE");

$t->set_var("START_REPORT_DATE_STRING", date("m/d/Y", $start_timestamp));
$t->set_var("STARTTIMESTAMP",  $start_timestamp);
$t->set_var("ENDTIMESTAMP",  $end_timestamp);



$generateReport = WebUtil::GetInput("generateReport", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);


// Get query parameters from the URL and set it within the Query Object.  Then extract them so we can build the Form page.
$visitorQueryObj = new VisitorPathQuery();
$visitorQueryObj->setQueryParametersFromURL();
$visitorQueryObj->limitDateRange($start_timestamp, $end_timestamp);

// Extract query parameters from URL that was set in the Object automatically.
$minSessionMinutes = $visitorQueryObj->getMinSessionDurationSeconds() / 60;
$maxSessionMinutes = $visitorQueryObj->getMaxSessionDurationSeconds() / 60;

$minNodes = $visitorQueryObj->getMinimumNodes();
$maxNodes = $visitorQueryObj->getMaximumNodes();

// Parralell arrays.
$mainLabelFilters = $visitorQueryObj->getMainLabelLimiters();
$detailLabelFilters = $visitorQueryObj->getDetailLabelLimiters();

// Parralell arrays.
$mainLabelInvalidators = $visitorQueryObj->getMainLabelInvalidators();
$detailLabelInvalidators = $visitorQueryObj->getDetailLabelInvalidators();


// Paralell Arrays
$pathLimitersSourceMainArr = $visitorQueryObj->getPathLimitersSourceMain();
$pathLimitersSourceDetailArr = $visitorQueryObj->getPathLimitersSourceDetail();
$pathLimitersTargetMainArr = $visitorQueryObj->getPathLimitersTargetMain();
$pathLimitersTargetDetailArr = $visitorQueryObj->getPathLimitersTargetDetail();


// Paralell Arrays
$pathInvalidatorsSourceMainArr = $visitorQueryObj->getPathInvalidatorsSourceMain();
$pathInvalidatorsSourceDetailArr = $visitorQueryObj->getPathInvalidatorsSourceDetail();
$pathInvalidatorsTargetMainArr = $visitorQueryObj->getPathInvalidatorsTargetMain();
$pathInvalidatorsTargetDetailArr = $visitorQueryObj->getPathInvalidatorsTargetDetail();



$userAgentFilters = $visitorQueryObj->getUserAgentLimiters();
$referrerKeywordFilters = $visitorQueryObj->getReferrerKewordsLimiters();
$expandLabelsArr = $visitorQueryObj->getExpandedLabelNames();
$userIDsArr = $visitorQueryObj->getUserIDlimiters();
$sessionIDsArr = $visitorQueryObj->getSessionIdLimiters();
$showNodexArr = $visitorQueryObj->getNodesShown();



	
// Make the filters 3 elements larger than their current length. So that a user can keep adding.
$labelFiltersLength = sizeof($mainLabelFilters) + 2;
$labelInvalidatorsLength = sizeof($mainLabelInvalidators) + 2;
$userAgentsLength = sizeof($userAgentFilters) + 2;
$refKeywordFilterLength = sizeof($referrerKeywordFilters) + 2;
$userIdFilterLength = sizeof($userIDsArr) + 2;
$pathLimitersLength = sizeof($pathLimitersSourceMainArr) + 2;
$pathInvalidatorsLength = sizeof($pathInvalidatorsSourceMainArr) + 2;
$showNodexLength = sizeof($showNodexArr) + 4;



$t->set_block("origPage","userAgentBL","userAgentBLout");
$t->set_block("origPage","referrerLimitBL","referrerLimitBLout");
$t->set_block("origPage","invalidatorsBL","invalidatorsBLout");
$t->set_block("origPage","filtersBL","filtersBLout");
$t->set_block("origPage","userIDlimiters","userIDlimitersout");
$t->set_block("origPage","pathFiltersBL","pathFiltersBLout");
$t->set_block("origPage","pathInvalidatorsBL","pathInvalidatorsBLout");








for($i=0; $i<$pathInvalidatorsLength; $i++){
	
	if(!isset($pathInvalidatorsSourceMainArr[$i])){
		$t->set_var("INVALIDATOR_MAIN_PATH_SRC",  "");
		$t->set_var("INVALIDATOR_DETAIL_PATH_SRC",  "");
		$t->set_var("INVALIDATOR_MAIN_PATH_TRGT",  "");
		$t->set_var("INVALIDATOR_DETAIL_PATH_TRGT",  "");
	}
	else{
		$t->set_var("INVALIDATOR_MAIN_PATH_SRC",  WebUtil::htmlOutput($pathInvalidatorsSourceMainArr[$i]));
		$t->set_var("INVALIDATOR_DETAIL_PATH_SRC",  WebUtil::htmlOutput($pathInvalidatorsSourceDetailArr[$i]));
		$t->set_var("INVALIDATOR_MAIN_PATH_TRGT",  WebUtil::htmlOutput($pathInvalidatorsTargetMainArr[$i]));
		$t->set_var("INVALIDATOR_DETAIL_PATH_TRGT",  WebUtil::htmlOutput($pathInvalidatorsTargetDetailArr[$i]));
	}

	$t->set_var("COUNTER",  $i);
		
	$t->parse("pathInvalidatorsBLout","pathInvalidatorsBL",true);
}

$t->set_var("PATH_INVALIDATORS_ROWS",  $pathInvalidatorsLength);







for($i=0; $i<$pathLimitersLength; $i++){
	
	if(!isset($pathLimitersSourceMainArr[$i])){
		$t->set_var("FILTER_MAIN_PATH_SRC",  "");
		$t->set_var("FILTER_DETAIL_PATH_SRC",  "");
		$t->set_var("FILTER_MAIN_PATH_TRGT",  "");
		$t->set_var("FILTER_DETAIL_PATH_TRGT",  "");
	}
	else{
		$t->set_var("FILTER_MAIN_PATH_SRC",  WebUtil::htmlOutput($pathLimitersSourceMainArr[$i]));
		$t->set_var("FILTER_DETAIL_PATH_SRC",  WebUtil::htmlOutput($pathLimitersSourceDetailArr[$i]));
		$t->set_var("FILTER_MAIN_PATH_TRGT",  WebUtil::htmlOutput($pathLimitersTargetMainArr[$i]));
		$t->set_var("FILTER_DETAIL_PATH_TRGT",  WebUtil::htmlOutput($pathLimitersTargetDetailArr[$i]));
	}

	$t->set_var("COUNTER",  $i);
		
	$t->parse("pathFiltersBLout","pathFiltersBL",true);
}

$t->set_var("PATH_FILTERS_ROWS",  $pathLimitersLength);







for($i=0; $i<$userIdFilterLength; $i++){
	
	if(!isset($userIDsArr[$i]))
		$t->set_var("USER_ID",  "");
	else
		$t->set_var("USER_ID",  "U" . WebUtil::htmlOutput($userIDsArr[$i]));

	$t->set_var("COUNTER",  $i);
		
	$t->parse("userIDlimitersout","userIDlimiters",true);
}

$t->set_var("USER_ID_ROWS",  $userIdFilterLength);









for($i=0; $i<$userAgentsLength; $i++){
	
	if(!isset($userAgentFilters[$i]))
		$t->set_var("USER_AGENT",  "");
	else
		$t->set_var("USER_AGENT",  WebUtil::htmlOutput($userAgentFilters[$i]));

	$t->set_var("COUNTER",  $i);
		
	$t->parse("userAgentBLout","userAgentBL",true);
}

$t->set_var("USER_AGENT_ROWS",  $userAgentsLength);




for($i=0; $i<$refKeywordFilterLength; $i++){
	
	if(!isset($referrerKeywordFilters[$i]))
		$t->set_var("REF_KEYWORDS",  "");
	else
		$t->set_var("REF_KEYWORDS",  WebUtil::htmlOutput($referrerKeywordFilters[$i]));

	$t->set_var("COUNTER",  $i);
		
	$t->parse("referrerLimitBLout","referrerLimitBL",true);
}

$t->set_var("REFERRER_KEYWORDS_ROWS",  $refKeywordFilterLength);





for($i=0; $i<$labelFiltersLength; $i++){
	
	if(!isset($mainLabelFilters[$i])){
		$t->set_var("FILTER_MAIN_LABEL",  "");
		$t->set_var("FILTER_DETAIL_LABEL",  "");
	}
	else{
		$t->set_var("FILTER_MAIN_LABEL",  WebUtil::htmlOutput($mainLabelFilters[$i]));
		$t->set_var("FILTER_DETAIL_LABEL",  WebUtil::htmlOutput($detailLabelFilters[$i]));
	}

	$t->set_var("COUNTER",  $i);
		
	$t->parse("filtersBLout","filtersBL",true);
}

$t->set_var("LABEL_LIMITER_ROWS",  $labelFiltersLength);


for($i=0; $i<$labelInvalidatorsLength; $i++){
	
	if(!isset($mainLabelInvalidators[$i])){
		$t->set_var("INVALIDATE_MAIN_LABEL",  "");
		$t->set_var("INVALIDATE_DETAIL_LABEL",  "");
	}
	else{
		$t->set_var("INVALIDATE_MAIN_LABEL",  WebUtil::htmlOutput($mainLabelInvalidators[$i]));
		$t->set_var("INVALIDATE_DETAIL_LABEL",  WebUtil::htmlOutput($detailLabelInvalidators[$i]));
	}

	$t->set_var("COUNTER",  $i);
		
	$t->parse("invalidatorsBLout","invalidatorsBL",true);
}

$t->set_var("LABEL_INVALIDATOR_ROWS",  $labelInvalidatorsLength);



	


// Don't let Zero show up on the form.
$t->set_var("MINIMUM_SESSION_DURATION",  empty($minSessionMinutes) ? "" : $minSessionMinutes);
$t->set_var("MAXIMUM_SESSION_DURATION",  empty($maxSessionMinutes) ? "" : $maxSessionMinutes);

// Don't let Zero show up on the form.
$t->set_var("MINIMUM_NODES",  empty($minNodes) ? "" : $minNodes);
$t->set_var("MAXIMUM_NODES",  empty($maxNodes) ? "" : $maxNodes);





$t->set_var("CHART_QUERY_STRING",  urlencode($visitorQueryObj->getUrlQueryEncoded()));

$t->set_var("DOMAIN_IDS",  implode('|', $visitorQueryObj->getDomainIDs()));



$expandLabelsChoicesArr = VisitorPath::getExpandableLabelNames();
$uniqueMainLabelsDropDown = array();

foreach($expandLabelsChoicesArr as $thisLabelName)
	$uniqueMainLabelsDropDown[$thisLabelName] = $thisLabelName;
	
	

$t->set_var("LABEL_EXPANSION_LIST",  Widgets::buildSelect($uniqueMainLabelsDropDown, $visitorQueryObj->getExpandedLabelNames()));
$t->allowVariableToContainBrackets("LABEL_EXPANSION_LIST");




// Let's find out if the Domain IDs stored in the Visiter Query Object match what the user has selected.
// If they do not match... then show them a link to make them match.
$selectedDomainIDs = $domainObj->getSelectedDomainIDs();
$queryDomainIDs = $visitorQueryObj->getDomainIDs();
$domainIDsDiff = (count(array_intersect($selectedDomainIDs,$queryDomainIDs)) == count(array_unique(array_merge($selectedDomainIDs,$queryDomainIDs)))) ? false : true; 
if(!$domainIDsDiff)
	$t->discard_block("origPage", "ClearDomainsLink");


	
	
// Set the Checkboxes depending on the type of referral types.
$referalTypesArr = $visitorQueryObj->getReferralTypes();

if(in_array("B", $referalTypesArr))
	$t->set_var("REF_B", "checked");
else 
	$t->set_var("REF_B", "");
	
if(in_array("O", $referalTypesArr))
	$t->set_var("REF_O", "checked");
else 
	$t->set_var("REF_O", "");
	
if(in_array("L", $referalTypesArr))
	$t->set_var("REF_L", "checked");
else 
	$t->set_var("REF_L", "");
	
if(in_array("S", $referalTypesArr))
	$t->set_var("REF_S", "checked");
else 
	$t->set_var("REF_S", "");
	
if(in_array("U", $referalTypesArr))
	$t->set_var("REF_U", "checked");
else 
	$t->set_var("REF_U", "");
	
	
	
	
	
// Don't generate the report until we say... otherwise it would show results for "today", etc.
if($generateReport != "yes"){
	
	// In case the Initial Setup has does to be displayed... show all of them... and keep them all initially selected.
	$nodesShownArr = $visitorQueryObj->getNodesShown();
	if(!empty($nodesShownArr))
		$nodesShownArr = array_combine($visitorQueryObj->getNodesShown(), $visitorQueryObj->getNodesShown());
		
	$t->set_var("DISPLAYED_NODES_LIST",  Widgets::buildSelect($nodesShownArr, $visitorQueryObj->getNodesShown()));
	$t->allowVariableToContainBrackets("DISPLAYED_NODES_LIST");
		
	$t->set_var("SESSION_COUNT", "");
	$t->set_var("USER_COUNT", "");
	$t->set_var("ORDER_COUNT", "");
	
	$t->discard_block("origPage", "ReportBody");
	$t->discard_block("origPage", "ShowChartLink");
	$t->discard_block("origPage", "hiddenNodesBL");
	
	$t->pparse("OUT","origPage");
	exit;
}



$sessionIDsArr = $visitorQueryObj->getSessionIDs();
$ipAddressArr = $visitorQueryObj->getIpAddressesFromSessionIDs($sessionIDsArr);
$ipAddressArr = array_unique($ipAddressArr);

$t->set_var("SESSION_COUNT", sizeof($sessionIDsArr) . " Session(s)");
$t->set_var("UNIQUE_IPS", sizeof($ipAddressArr) . " Unique IP(s)");




$hiddenNodesArr = $visitorQueryObj->getHiddenNodes();
$allMainLabelNodesArr = array_keys($visitorQueryObj->getMainLabelCounts());

$allMainLabelsDropDownArr = array();
foreach($allMainLabelNodesArr as $thisMainLabelValue)
	$allMainLabelsDropDownArr[$thisMainLabelValue] = $thisMainLabelValue;

// Figure out which labels are displayed.
$displayedNodesArr = array_diff($allMainLabelNodesArr, $hiddenNodesArr);

$t->set_var("DISPLAYED_NODES_LIST",  Widgets::buildSelect($allMainLabelsDropDownArr, $displayedNodesArr));
$t->allowVariableToContainBrackets("DISPLAYED_NODES_LIST");



$NumberOfResultsToDisplay = 1000;

$offset = WebUtil::GetInput("offset", FILTER_SANITIZE_INT);




$visitorPathObj = new VisitorPath();

$t->set_block("origPage","SessionListBL","SessionListBLout");

$resultCounter = 0;
$resultsDisplayed = 0;

$userIDArr = array();
$orderCount = 0;

foreach($sessionIDsArr as $thisSessionID){
	
	$resultCounter++;
	
	// Find out if the user placed an order during the Session.
	$userIDofSession = $visitorPathObj->getUserIDofSession($thisSessionID);
	
	if(!empty($userIDofSession))
		$userIDArr[] = $userIDofSession;
		
	$orderPlacedFlag = false;
	if($visitorPathObj->checkIfOrderPlaced($thisSessionID)){
		$orderCount++;
		$orderPlacedFlag = true;
	}
	
	// Add a hack in to look for customers who place their order within 10-20 minutes after account registration.
	if(!empty($userIDofSession) && $orderPlacedFlag){
		if(in_array("User Account Created", $mainLabelFilters) && in_array("Order Placed", $mainLabelFilters)){
			print "Trying to find people that placed an order within 10-20 mintues after account creation.";
			
			$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateCreated) FROM users WHERE ID=" . $userIDofSession);
			$dateUserCreated = $dbCmd->GetValue();
			
			$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateOrdered) FROM orders WHERE UserID=" . $userIDofSession);
			$dateUserOrdered = $dbCmd->GetValue();
			
			$timeDiff = $dateUserOrdered - $dateUserCreated;
			if($timeDiff < 60 * 10 || $timeDiff > 60 * 20)
				continue;
		}
	}
	
	if(($resultCounter < ($offset + 1)) || ($resultCounter > ($NumberOfResultsToDisplay + $offset)))
		continue;
		
	$resultsDisplayed++;
	
	
	$domainID = $visitorPathObj->getDomainID($thisSessionID);
	$sessionStartTimestamp = $visitorPathObj->getSessionStartTime($thisSessionID);
	
	$domainLogoObj = new DomainLogos($domainID);
	$domainLogoImg = "<img alt='".Domain::getDomainKeyFromID($domainID)."' src='./domain_logos/$domainLogoObj->verySmallIcon' border='0' align='absmiddle'>";
	$t->set_var("DOMAIN_LOGO", $domainLogoImg);
	$t->allowVariableToContainBrackets("DOMAIN_LOGO");

/*
	$maxMindObj = new MaxMind();
	$maxMindObj->loadIPaddressForLocationDetails($visitorPathObj->getIPaddressOfSession($thisSessionID));
	$locationID = $maxMindObj->getLocationIdFromIPaddress($visitorPathObj->getIPaddressOfSession($thisSessionID));
	if($locationID)
		$locationStr = $maxMindObj->getCity() . ", " . $maxMindObj->getCountry();
	else
		$locationStr = "";

	$t->set_var("IPADDRESS", $visitorPathObj->getIPaddressOfSession($thisSessionID) . "&nbsp;&nbsp;&nbsp; " . $locationStr);
*/

	$t->set_var("SESSION_ID", $thisSessionID);
	$t->set_var("IPADDRESS", $visitorPathObj->getIPaddressOfSession($thisSessionID));
	
	if(time() - $sessionStartTimestamp < 60 * 60 * 24)
		$startTimeDesc = date("g:i a", $sessionStartTimestamp);
	else 
		$startTimeDesc = date("D, M jS g:i a", $sessionStartTimestamp);
		
	$t->set_var("SESSION_STARTDATE", $startTimeDesc);
	
	$secondsDuration = $visitorPathObj->getSessionDuration($thisSessionID);
	$minutesDuration = round($secondsDuration / 60);
	
	if($secondsDuration == 0)
		$durationDesc = "0";
	else if($secondsDuration < 60)
		$durationDesc = $secondsDuration . " second" . LanguageBase::GetPluralSuffix($secondsDuration, "", "s");
	else 
		$durationDesc = $minutesDuration . " minute" . LanguageBase::GetPluralSuffix($minutesDuration, "", "s");
	
	$t->set_var("SESSION_DURATION", $durationDesc);
	
	$t->set_var("WEB_BROWSER_AGENT", WebUtil::htmlOutput($visitorPathObj->getUserAgent($thisSessionID)));
	
	$sessionReferrer = $visitorPathObj->getReferrer($thisSessionID);
	
	// If we have a referrer... then we need to condense it.
	if(!empty($sessionReferrer)){
		
		// Let's first see if we can figure out a domain and Keyword Search parameter from a major search engine.
		$domainReferrer = WebUtil::getDomainFromURL($sessionReferrer);
		$referrerKeywords = WebUtil::getKeywordsFromSearchEngineURL($sessionReferrer);
		
		// If empty... then it didn't come from a major search engine... to show the URL unless it is over 100 characters, then chop it down.
		if(empty($referrerKeywords)){
			if(strlen($sessionReferrer) > 40)
				$refererDescription = "<a href='".WebUtil::htmlOutput($sessionReferrer)."' target='topOrganic' style='color:#006666;'>" . WebUtil::htmlOutput(substr($sessionReferrer, 0, 40) . ".......") . "</a>";
			else 
				$refererDescription = "<a href='".WebUtil::htmlOutput($sessionReferrer)."' target='topOrganic' style='color:#006666;'>" . WebUtil::htmlOutput($sessionReferrer) . "</a>";
		}
		else{
			$refererDescription = $domainReferrer . " <a href='".WebUtil::htmlOutput($sessionReferrer)."' target='topOrganic' style='color:#337766;'>" . WebUtil::htmlOutput($referrerKeywords) . "</a>";
		}
	}
	else{
		$refererDescription = "";
	}
	



	if(empty($userIDofSession)){
		$t->set_var("DETAILS", "");
	}
	else{
		
		
		
		if($orderPlacedFlag){
			$orderPlacedHTML = "&nbsp;&nbsp;<font color='#006600'><b>Ordered</b></font>";
		}
		else {
			$orderPlacedHTML = "";
		}
			
		$t->set_var("DETAILS", "<a href=\"javascript:Cust($userIDofSession);\" class=\"BlueRedLinkRecSM\">" . "U" . $userIDofSession . "</a>" . $orderPlacedHTML);
		$t->allowVariableToContainBrackets("DETAILS");
	}
	
	
	
	$referralType = $visitorPathObj->getReferralType($thisSessionID);

	// O = Organic Search Engine with keywords we can infer.
	// B = Banner Click referal... such as Google Adwords
	// L = Linked from somewhere, but we can't categorize it.
	// U = Unknown... because it was not a banner click, and there was no referrer present.
	// S = Sales Rep Link
	if($referralType == "O")
		$t->set_var("REFERRAL_TYPE", "<img src='images/icon-leaf.png' width='19' height='16' align='absmiddle'>");
	else if($referralType == "B")
		$t->set_var("REFERRAL_TYPE", "<img src='images/icon-dollars.png' width='19' height='16' align='absmiddle'>");
	else if($referralType == "L")
		$t->set_var("REFERRAL_TYPE", "<img src='images/icon-linked.png' width='19' height='16' align='absmiddle'>");
	else if($referralType == "U")
		$t->set_var("REFERRAL_TYPE", "<img src='images/icon-unknown.png' width='19' height='16' align='absmiddle'>");
	else if($referralType == "S")
		$t->set_var("REFERRAL_TYPE", "<img src='images/icon-linkSalesRep.png' width='19' height='16' align='absmiddle'>");
	else
		$t->set_var("REFERRAL_TYPE", "Error");
	

	
	$t->set_var("REFERER", $refererDescription);
	
	if($referralType == "B")
		$t->set_var("REF_DETAIL", WebUtil::htmlOutput($visitorPathObj->getFirstBannerClickCode($thisSessionID)));
	else 	
		$t->set_var("REF_DETAIL", "");
	
	$t->allowVariableToContainBrackets("REFERER");
	$t->allowVariableToContainBrackets("REFERRAL_TYPE");
	
	$t->parse("SessionListBLout","SessionListBL",true);
}

if(empty($sessionIDsArr)){
	
	$t->discard_block("origPage","ShowChartLink");
	
	$t->set_block("origPage","ReportBody","ReportBodyOut");
	$t->set_var("ReportBodyOut",  "No Results Found");
}
else{
	
	$sessionCount = sizeof($sessionIDsArr);
	$uniqueUserIDarr = array_unique($userIDArr);
	
	$t->set_var("USER_COUNT", sizeof($userIDArr) . " User(s) " . round(sizeof($userIDArr) / $sessionCount * 100, 1) . "% &nbsp;&nbsp;&nbsp;" . sizeof($uniqueUserIDarr) . " Unique User(s) " . round(sizeof($uniqueUserIDarr) / $sessionCount * 100, 1) . "%");
	$t->set_var("ORDER_COUNT", $orderCount . " Order(s) " . round($orderCount / $sessionCount * 100, 1) . "%");


	// Handle Multi-Pages (possibly).
	
	// Set this block so that we can erase the multi-page navigation bar if there aren't multiple pages 
	$t->set_block("origPage","MultiPageBL","MultiPageBLout");
	$t->set_block("origPage","SecondMultiPageBL","SecondMultiPageBLout");
	
	
	$resultCounter = sizeof($sessionIDsArr);
	
	// This means that we have multiple pages of search results
	if($resultCounter > $NumberOfResultsToDisplay){
	
	
		// What are the name/value pairs AND URL  for all of the subsequent pages 
		$NV_pairs_URL = $visitorQueryObj->getUrlQueryString() . "&generateReport=yes&PeriodType=DATERANGE";
		$BaseURL = "./ad_visitorPaths.php";
	
		// Get a the navigation of hyperlinks to all of the multiple pages 
		$NavigateHTML = Navigation::GetNavigationForSearchResults($BaseURL, $resultCounter, $NV_pairs_URL, $NumberOfResultsToDisplay, $offset);
	
		$t->set_var(array("RESULT_DESC"=>$resultCounter, "OFFSET"=>$offset, "START_RESULTS_DISP"=>($offset+1), "END_RESULTS_DISP"=>($offset+$resultsDisplayed)));
		$t->set_var("NAVIGATE", $NavigateHTML);
		$t->allowVariableToContainBrackets("NAVIGATE");
	
		
		$t->parse("MultiPageBLout","MultiPageBL",true);
		$t->parse("SecondMultiPageBLout","SecondMultiPageBL",true);
	}
	else{
		$t->set_var("NAVIGATE", "");
		$t->set_var("MultiPageBLout", "");
		$t->set_var("SecondMultiPageBLout", "");
	}
}






$t->pparse("OUT","origPage");



<?

require_once("library/Boot_Session.php");

// Don't make it look like we are favoring a particular Domain 
Domain::removeTopDomainID();

$keywords = WebUtil::GetInput("keywords", FILTER_SANITIZE_STRING_ONE_LINE);
$startmonth = WebUtil::GetInput("startmonth", FILTER_SANITIZE_INT, date("n"));
$endmonth = WebUtil::GetInput("endmonth", FILTER_SANITIZE_INT, date("n"));
$startyear = WebUtil::GetInput("startyear", FILTER_SANITIZE_INT, date("Y"));
$endyear = WebUtil::GetInput("endyear", FILTER_SANITIZE_INT, date("Y"));

$dbCmd = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

if($action == "changeThreadPageResults"){
		
		$resultPerPage = WebUtil::GetInput("threadCounts", FILTER_SANITIZE_INT);

		// Remember the setting
		$DaysToRemember = 300;
		$cookieTime = time()+60*60*24 * $DaysToRemember;
		setcookie ("ThreadPageCountResults", $resultPerPage, $cookieTime);
		
		header("Location: ./ad_messages.php?");
		exit;
}

$t = new Templatex(".");

$t->set_file("origPage", "ad_messages-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$start_timestamp = mktime (0,0,0,$startmonth,1,$startyear);
$end_timestamp   = mktime (0,0,0,$endmonth +1,1,$endyear);

// Set up the drop down boxes for the date select
$start_month_selectHTML = Widgets::BuildMonthSelect($startmonth, "startmonth");
$end_month_selectHTML = Widgets::BuildMonthSelect($endmonth, "endmonth");

$start_year_selectHTML = Widgets::BuildYearSelect($startyear, "startyear");
$end_year_selectHTML = Widgets::BuildYearSelect($endyear, "endyear");

$t->set_var(array("START_MONTH"=>$start_month_selectHTML, "START_YEAR"=>$start_year_selectHTML, "END_MONTH"=>$end_month_selectHTML, "END_YEAR"=>$end_year_selectHTML));
$t->allowVariableToContainBrackets("START_MONTH");
$t->allowVariableToContainBrackets("START_YEAR");
$t->allowVariableToContainBrackets("END_MONTH");
$t->allowVariableToContainBrackets("END_YEAR");


$threadOffset = WebUtil::GetInput("threadOffset", FILTER_SANITIZE_INT);
$numberOfThreadsToDisplay  = WebUtil::GetCookie("ThreadPageCountResults",5);

$t->set_var("THREADS_PER_PAGE_DROPDOWN", Widgets::buildSelect(array("5"=>5, "10"=>10, "20"=>20, "50"=>50, "100"=>100, "500"=>500, "10000"=>"All"), $numberOfThreadsToDisplay));
$t->allowVariableToContainBrackets("THREADS_PER_PAGE_DROPDOWN");


$t->set_block("origPage","messagesBL","messagesBLout");


$messageThreadCollectionObj = new MessageThreadCollection();

$messageThreadCollectionObj->setUserID($UserID);
$messageThreadCollectionObj->setLimitStartTime($start_timestamp);
$messageThreadCollectionObj->setLimitEndTime($end_timestamp);
$messageThreadCollectionObj->setKeywords($keywords);
$messageThreadCollectionObj->setStartRecord($threadOffset);
$messageThreadCollectionObj->setRecordsToGo($numberOfThreadsToDisplay);

$messageThreadCollectionObj->loadThreadCollection();

$threadResultsDisplayed   = $messageThreadCollectionObj->getThreadCountsDisplayed();
$threadResultCounter      = $messageThreadCollectionObj->getTotalThreadCount();


$t->set_block("origPage","MultiPageBL","MultiPageBLout");

// This means that we have multiple pages of search results
if($threadResultCounter > $numberOfThreadsToDisplay){
	
	// What are the name/value pairs AND URL  for all of the subsequent pages 
	$threadNV_pairs_URL = "&startmonth=$startmonth&startyear=$startyear&endmonth=$endmonth&endyear=$endyear&";
	$threadBaseURL = "./ad_messages.php";

	// Get a the navigation of hyperlinks to all of the multiple pages 
	$NavigateHTML = Navigation::GetNavigationForSearchResults($threadBaseURL, $threadResultCounter, $threadNV_pairs_URL, $numberOfThreadsToDisplay, $threadOffset, "threadOffset");

	$t->set_var(array("THREAD_NAVIGATE"=>$NavigateHTML, "THREAD_RESULT_DESC"=>$threadResultCounter, "THREAD_START_RESULTS_DISP"=>($threadOffset+1), "THREAD_END_RESULTS_DISP"=>($threadOffset+$threadResultsDisplayed)));
	$t->allowVariableToContainBrackets("THREAD_NAVIGATE");
	
	$t->parse("MultiPageBLout","MultiPageBL",true);
}
else {
	$t->set_var(array("THREAD_NAVIGATE"=>""));
	$t->set_var(array("MultiPageBLout"=>""));	
}

$messageDisplay = new MessageDisplay();
$empty_message  = $messageDisplay->displayAsOverviewHTML($t, $messageThreadCollectionObj);




$t->set_var(array("KEYWORDS"=>$keywords));

if($empty_message) {
	$t->set_block("origPage","EmptyMessageBL","EmptyMessageBLout");
	$t->set_var(array("EmptyMessageBLout"=>"<font class='LargeBody'>No Messages Found<br></font>"));
}

$t->pparse("OUT","origPage");

?>
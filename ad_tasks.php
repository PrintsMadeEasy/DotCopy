<?

require_once("library/Boot_Session.php");

// Don't make it look like we are favoring a particular Domain 
Domain::removeTopDomainID();

$keywords = WebUtil::GetInput("keywords", FILTER_SANITIZE_STRING_ONE_LINE);
$startmonth = WebUtil::GetInput("startmonth", FILTER_SANITIZE_INT, (date("n")));
$endmonth = WebUtil::GetInput("endmonth", FILTER_SANITIZE_INT, date("n"));
$startyear = WebUtil::GetInput("startyear", FILTER_SANITIZE_INT, date("Y"));
$endyear = WebUtil::GetInput("endyear", FILTER_SANITIZE_INT, date("Y"));


$dbCmd = new DbCmd();




//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


$t = new Templatex(".");


$t->set_file("origPage", "ad_tasks-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


$start_timestamp = mktime (0,0,0,$startmonth,1,$startyear);
$end_timestamp   = mktime (0,0,0,$endmonth +1,1,$endyear);

$tasksShowBeforeReminder = WebUtil::GetCookie("TasksShowBeforeReminder", "true") == "false" ? false : true;

if($tasksShowBeforeReminder)
	$t->set_var("SHOW_FUTURE_TASKS", "checked");
else
	$t->set_var("SHOW_FUTURE_TASKS", "");

$taskCollectionObj = new TaskCollection();
$taskCollectionObj->limitShowTasksBeforeReminder($tasksShowBeforeReminder);
$taskCollectionObj->limitUserID($UserID);
$taskCollectionObj->limitEndTime($end_timestamp);
$taskCollectionObj->limitStartTime($start_timestamp);
$taskCollectionObj->limitUncompletedTasksOnly(true);
$taskCollectionObj->limitKeywords($keywords);

$taskObjectsArr = $taskCollectionObj->getTaskObjectsArr();
$MatchesCount = $taskCollectionObj->getResultsDisplayedCount();

$tasksDisplayObj = new TasksDisplay($taskObjectsArr);
$tasksDisplayObj->setTemplateFileName("tasks-template.html");
$tasksDisplayObj->setReturnURL("ad_tasks.php");

$tasksDisplayObj->displayAsHTML($t);




$SearchResult_Desc = "";

if(($MatchesCount > 1) && (!empty($keywords)))
	$SearchResult_Desc .= "$MatchesCount Matches found in ";
else if(($MatchesCount == 1) && (!empty($keywords)))
	$SearchResult_Desc .= "$MatchesCount Match found in ";

if($MatchesCount > 1)
	$SearchResult_Desc .= "$MatchesCount Tasks";
else
	$SearchResult_Desc .= "$MatchesCount Task";



// Set up the drop down boxes for the date select
$start_month_selectHTML = Widgets::BuildMonthSelect($startmonth, "startmonth");
$end_month_selectHTML = Widgets::BuildMonthSelect($endmonth, "endmonth");

$start_year_selectHTML = Widgets::BuildYearSelect($startyear, "startyear");
$end_year_selectHTML = Widgets::BuildYearSelect($endyear, "endyear");

$t->set_var(array(
		"START_MONTH"=>$start_month_selectHTML, 
		"START_YEAR"=>$start_year_selectHTML, 
		"END_MONTH"=>$end_month_selectHTML, 
		"END_YEAR"=>$end_year_selectHTML));

$t->set_var(array(
		"KEYWORDS"=>$keywords, 
		"RESULT_DESC"=>$SearchResult_Desc));

$t->allowVariableToContainBrackets("START_MONTH");
$t->allowVariableToContainBrackets("START_YEAR");
$t->allowVariableToContainBrackets("END_MONTH");
$t->allowVariableToContainBrackets("END_YEAR");



$t->pparse("OUT","origPage");


?>
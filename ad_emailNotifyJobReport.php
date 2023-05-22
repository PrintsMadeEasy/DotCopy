<?

require_once("library/Boot_Session.php");

$domainObj = Domain::singleton();

$dbCmd = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("EMAIL_NOTIFY_EMAIL_ADDRESS_BATCHES"))
	throw new Exception("Permission Denied");

$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

$emailObj = new EmailNotifyCollection();

if(empty($view))
	$view = "start";


$t = new Templatex(".");

$t->set_file("origPage", "ad_emailNotifyJobReport-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

if($view == "start"){

	$t->set_block("origPage","JobListBL","JobListBLout"); 

	$t->allowVariableToContainBrackets("START_MONTH_SELECT");
	$t->allowVariableToContainBrackets("START_YEAR_SELECT");
	$t->allowVariableToContainBrackets("END_MONTH_SELECT");
	$t->allowVariableToContainBrackets("END_YEAR_SELECT");
	$t->allowVariableToContainBrackets("DOMAIN_NAME");
	
	$startday   = WebUtil::GetInput("startday", FILTER_SANITIZE_INT);
	$startmonth = WebUtil::GetInput("startmonth", FILTER_SANITIZE_INT);
	$startyear  = WebUtil::GetInput("startyear", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
	$endday     = WebUtil::GetInput("endday",   FILTER_SANITIZE_INT);
	$endmonth   = WebUtil::GetInput("endmonth", FILTER_SANITIZE_INT);
	$endyear    = WebUtil::GetInput("endyear",  FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
	
	if(empty($endyear) || empty($startyear)) {		
		
		$currentDate = getdate(time());
			
		$startday    = $currentDate["mday"];
		$startmonth  = $currentDate["mon"];  
		$startyear   = $currentDate["year"]; 
		$endday      = $currentDate["mday"]; 
		$endmonth    = $currentDate["mon"];  
		$endyear    =  $currentDate["year"]; 
	}

	$t->set_var("START_DAY", $startday);	
	$t->set_var("START_MONTH_SELECT",Widgets::BuildMonthSelect( $startmonth, "startmonth", "" ));				
	$t->set_var("START_YEAR_SELECT",Widgets::BuildFutureYearSelectWithAllChoice( $startyear,  "startyear", "" ));		
	$t->set_var("END_DAY", $endday);	
	$t->set_var("END_MONTH_SELECT",Widgets::BuildMonthSelect( $endmonth,"endmonth", "" ));	
	$t->set_var("END_YEAR_SELECT",Widgets::BuildFutureYearSelectWithAllChoice($endyear,  "endyear", "" ));	
	
	$dateRangeStart = NULL; 
	if($startyear!="ALL") 
		$dateRangeStart = mktime(0,0,0,$startmonth,$startday,$startyear);
		
	$dateRangeEnd = NULL;		
	if($endyear!="ALL") 	
		$dateRangeEnd = mktime(0,0,0,$endmonth,$endday,$endyear);	
	
	if($dateRangeStart > $dateRangeEnd)
		$dateRangeEnd = $dateRangeStart;
	
	$emailObj->setStatisticsDateRange($dateRangeStart,$dateRangeEnd);
	$emailObj->setStatisticsAllowedDomainIds($domainObj->getSelectedDomainIDs());	
		
	$jobIdArr = $emailObj->getStatisticsJobIdArr();
	
	$counter = 0;
	foreach($jobIdArr as $jobId) {
		
		$t->set_var("JOB_ID",    $jobId);
		$t->set_var("JOB_DATE", date("M j, Y",$emailObj->getJobDateById($jobId)));	
		$t->set_var("DOMAIN_NAME", $emailObj->generateJobDomainText($jobId));
			
		$t->set_var("STATISTIC_SENDERROR", $emailObj->statisticSendErrorsByJobId($jobId));
		$t->set_var("STATISTIC_TRACKING",  $emailObj->statisticTrackingByJobId($jobId));
		$t->set_var("STATISTIC_SENTEMAILS",$emailObj->statisticSentOutByJobId($jobId));
		
		$statClicks = $emailObj->statisticClicksByJobId($jobId);
		$statOrders = $emailObj->statisticOrdersByJobId($jobId);
		
		$statConversionRate = "0.00%";
		if($statClicks>0)
			$statConversionRate = number_format($statClicks /$statOrders / 100 , 2)." %";  
					
		$t->set_var("STATISTIC_CLICKS",    $statClicks);
		$t->set_var("STATISTIC_ORDERS",    $statOrders);
		$t->set_var("STATISTIC_CONVRATE",  $statConversionRate);

		$t->parse("JobListBLout","JobListBL",true); 	
	
		$counter++;
	}
	
	if(empty($jobIdArr))
		$t->set_var("JobListBLout",  "");
		
	
	$t->discard_block("origPage", "JobListBL");
}
 else {
	throw new Exception("Illegal View Type");
}

Widgets::buildTabsForEmailNotify($t, "jobreports");

$t->pparse("OUT","origPage");

?>
<?

require_once("library/Boot_Session.php");


// Don't make it look like we are favoring a particular Domain 
Domain::removeTopDomainID();


$dbCmd = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$UserID = $AuthObj->GetUserID();


Domain::removeTopDomainID();
$domainObj = Domain::singleton();


if(!$AuthObj->CheckForPermission("ARTWORK_SEARCH"))
	throw new Exception("Permission Denied");


$t = new Templatex(".");

$t->set_file("origPage", "ad_artwork_search-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$keywords = WebUtil::GetInput("keywords", FILTER_SANITIZE_STRING_ONE_LINE);


$t->set_var("KEYWORDS", WebUtil::htmlOutput($keywords));



#-- Start Report Date Parameters--#
$message = null;
$ReportPeriodIsDateRange = WebUtil::GetInput( "PeriodType", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "DATERANGE";
$TimeFrameSel = WebUtil::GetInput( "TimeFrame", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "TODAY"  );

$date = getdate();
$startday= WebUtil::GetInput( "DateRangeStartDay", FILTER_SANITIZE_INT, "1" );
$startmonth= WebUtil::GetInput( "DateRangeStartMonth", FILTER_SANITIZE_INT, $date["mon"] );
$startyear= WebUtil::GetInput( "DateRangeStartYear", FILTER_SANITIZE_INT, $date["year"] );
$endday= WebUtil::GetInput( "DateRangeEndDay", FILTER_SANITIZE_INT, $date["mday"] );
$endmonth= WebUtil::GetInput( "DateRangeEndMonth", FILTER_SANITIZE_INT, $date["mon"] );
$endyear= WebUtil::GetInput( "DateRangeEndYear", FILTER_SANITIZE_INT, $date["year"] );
$limitToProductID = WebUtil::GetInput("productlimit", FILTER_SANITIZE_INT);

// Format the dates that we want for MySQL for the date range
if( $ReportPeriodIsDateRange )
{
	$start_timestamp = mktime (0,0,0,$startmonth,$startday,$startyear);
	$end_timestamp = mktime (23,59,59,$endmonth,$endday,$endyear);
	
	if(  $start_timestamp >  $end_timestamp  )	
		$message = "Invalid Date Range Specified - Unable to Generate Report";
}
else
{
	$ReportPeriod = Widgets::GetTimeFrame( $TimeFrameSel );
	$start_timestamp = $ReportPeriod[ "STARTDATE" ];
	$end_timestamp = $ReportPeriod[ "ENDDATE" ];
}

$start_mysql_timestamp = date("YmdHis", $start_timestamp);
$end_mysql_timestamp  = date("YmdHis", $end_timestamp);

// Setup date range selections and and type
$t->set_var( "PERIODTYPETIMEFRAME", $ReportPeriodIsDateRange ? null : "CHECKED" );
$t->set_var( "PERIODISTIMEFRAME", $ReportPeriodIsDateRange ? "false" : "true" );
$t->set_var( "TIMEFRAMESELS", Widgets::BuildTimeFrameSelect( $TimeFrameSel ));
$t->set_var( "PERIODTYPEDATERANGE", $ReportPeriodIsDateRange ? "CHECKED" : null );
$t->set_var( "DATERANGESELS", Widgets::BuildDateRangeSelect( $start_timestamp, $end_timestamp, "D" ));
$t->set_var( "MESSAGE", $message );
$t->set_var( "STARTTIMESTAMP", $start_timestamp );
$t->set_var( "ENDTIMESTAMP", $end_timestamp );


$t->allowVariableToContainBrackets("TIMEFRAMESELS");
$t->allowVariableToContainBrackets("DATERANGESELS");
$t->allowVariableToContainBrackets("PERIODTYPEDATERANGE");

if( $message )
{
	//Error occurred - discontinue report generation
	$t->discard_block("origPage","NoResultsBL");
	
	$t->pparse("OUT","origPage");
	exit;
}


#-- End Report Date Parameters--#





$EmptyList = true;


$dbCmd->Query("SELECT projectsordered.ID AS ProjectID, OrderID, BillingName, BillingCompany, UNIX_TIMESTAMP(DateOrdered) AS DateOrderedUNIX FROM projectsordered
		INNER JOIN orders ON orders.ID = projectsordered.OrderID
		WHERE (orders.DateOrdered BETWEEN $start_mysql_timestamp AND $end_mysql_timestamp) 
		AND ".DbHelper::getOrClauseFromArray("orders.DomainID", $domainObj->getSelectedDomainIDs())."
		AND (ArtworkFile like \"%". DbCmd::EscapeSQL($keywords) . "%\" OR ArtworkFileModified like \"%". DbCmd::EscapeSQL($keywords) . "%\")
		ORDER BY orders.ID DESC LIMIT 300");


$t->set_block("origPage","SearchResultBL","SearchResultBLout");
$totalResults = $dbCmd->GetNumRows();
while($row = $dbCmd->GetRow()){

	$EmptyList = false;

	$customerName = "";
	
	if(!empty($row["BillingCompany"]))
		$customerName .= WebUtil::htmlOutput($row["BillingCompany"]) . " <b>-</b> ";
	
	$customerName .= WebUtil::htmlOutput($row["BillingName"]);
	
	$t->set_var("CUSTOMER_NAME", $customerName);
	$t->allowVariableToContainBrackets("CUSTOMER_NAME");
	
	$t->set_var("PROJECTNO", $row["ProjectID"]);
	$t->set_var("ORDERNO", $row["OrderID"]);
	$t->set_var("ORDER_DATE", date("M j, Y", $row["DateOrderedUNIX"]));
	$t->set_var("TOTAL_RESULTS", $totalResults);
	
	
	
	if($totalResults >= 300)
		$t->set_var( "MESSAGE", "Your list has been truncated to the first 300 results." );
	
	
	$t->parse("SearchResultBLout","SearchResultBL",true);

}

if($EmptyList){
	$t->set_block("origPage","NoResultsBL","NoResultsBLout");
	$t->set_var("NoResultsBLout", "No artworks were found containing your search phrase.");
}


$t->pparse("OUT","origPage");



?>

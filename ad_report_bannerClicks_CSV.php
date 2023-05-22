<?

require_once("library/Boot_Session.php");


$reportSpanDays = WebUtil::GetInput("reportSpanDays", FILTER_SANITIZE_INT);
$reportSpacingIncrementDays = WebUtil::GetInput("reportSpacingIncrementDays", FILTER_SANITIZE_INT);
$startMonth = WebUtil::GetInput("startMonth", FILTER_SANITIZE_INT);
$startDay = WebUtil::GetInput("startDay", FILTER_SANITIZE_INT);
$startYear = WebUtil::GetInput("startYear", FILTER_SANITIZE_INT);
$trackingCodeSearch = WebUtil::GetInput("trackingCodeSearch", FILTER_SANITIZE_STRING_ONE_LINE);


$start_timestamp = mktime (0,0,0, $startMonth, $startDay, $startYear);
$start_mysql_timestamp = date("YmdHis", $start_timestamp);




$dbCmd = new DbCmd();

// Make this script be able to run for a while
set_time_limit(40000);


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("MARKETING_REPORT"))
	WebUtil::PrintAdminError("Not Available");
	

if($reportSpanDays <= 0)
	WebUtil::PrintAdminError("Report Span Days must be a postitive number.");
if($reportSpacingIncrementDays <= 0)
	WebUtil::PrintAdminError("Report Spacing Increments must be a postitive number.");
	

// In case you want to make increments spaced upon minutes or hours (rather than days)
//$reportSpanDays = 0.02;
//$reportSpacingIncrementDays = 0.02;
	

$reportSpanSeconds = 60 * 60 * 24 * $reportSpanDays;
$reportSpacingIncrementSeconds = 60 * 60 * 24 * $reportSpacingIncrementDays;

$bannerSearchArr = split("\|", $trackingCodeSearch);

$bannerClicksSearchArrCleaned = array();
$bannerClicksDontSearchArrCleaned = array();

foreach($bannerSearchArr as $thisTrackingCodeSearch){

	$thisTrackingCodeSearch = trim($thisTrackingCodeSearch);

	if(empty($thisTrackingCodeSearch))
		continue;

	// If there are any explantion points in the expression... then those values should always come after the first element in the Array.
	$possibleExplantionPointArr = split("\#", $thisTrackingCodeSearch);

	$possibleExplantionPointArr[0] = trim($possibleExplantionPointArr[0]);

	if(!empty($possibleExplantionPointArr[0]))
		$bannerClicksSearchArrCleaned[] = $possibleExplantionPointArr[0];

	for($i=1; $i<sizeof($possibleExplantionPointArr); $i++){

		$dontSearchTerm = trim($possibleExplantionPointArr[$i]);
		if(empty($dontSearchTerm))
			continue;

		$bannerClicksDontSearchArrCleaned[] = $dontSearchTerm;
	}
}



$bannerTrackingSearchSQL = DbHelper::getSQLfromSearchArr($bannerClicksSearchArrCleaned, "Name", true);
$bannerTrackingSearch_ForOrders_SQL = DbHelper::getSQLfromSearchArr($bannerClicksSearchArrCleaned, "Referral", true);

$bannerTrackingDontSearchSQL = DbHelper::getSQLfromSearchArr($bannerClicksDontSearchArrCleaned, "Name", false);
$bannerTrackingDontSearch_ForOrders_SQL = DbHelper::getSQLfromSearchArr($bannerClicksDontSearchArrCleaned, "Referral", false);


// prevent XSS
$trackingCodeSearch = preg_replace("/[<>]/", "", $trackingCodeSearch);

print "Tracking Search: $trackingCodeSearch" . "\n";
print "Date," . "Clicks," . "Visitors," . "Avg.Clicks/Person," . "Purcahses," . "Conversions," . "New Customers," . "New Cust. Conversions" ."\n";
flush();

$domainObj = Domain::singleton();

// Get a unique list of names from our BannerLog
$uniqeBannerListQry = "SELECT DISTINCT Name FROM bannerlog USE INDEX (bannerlog_Date)  WHERE " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs());

// If we are trying to limit our Customer Search by tracking codes from Banner Clicks.
if(!empty($bannerClicksSearchArrCleaned))
	$uniqeBannerListQry .= " AND $bannerTrackingSearchSQL";

// We may want to include terms that we don't want to search for.
if(!empty($bannerClicksDontSearchArrCleaned))
	$uniqeBannerListQry .= " AND $bannerTrackingDontSearchSQL";

$uniqeBannerListQry .= " AND Date > '" .$start_mysql_timestamp . "'   ";
$uniqeBannerListQry .= "  AND UserAgent NOT LIKE '%Google%' AND UserAgent NOT LIKE '%Slurp%' AND UserAgent NOT LIKE '%FunXXXXWeb%'";

$dbCmd->Query($uniqeBannerListQry);


$dbCmd->Query($uniqeBannerListQry);
$uniqueBannerNameArr_fromBannerClicks = $dbCmd->GetValueArr();




// Get a unique list of Tracking codes from our the "orders" table.... "The Lag Event" -------------------------------------
$uniqeBannerListQry = "SELECT DISTINCT Referral FROM orders USE INDEX (orders_DateOrdered)  WHERE " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs());

// If we are trying to limit our Customer Search by tracking codes from Banner Clicks.
if(!empty($bannerClicksSearchArrCleaned))
	$uniqeBannerListQry .= " AND $bannerTrackingSearch_ForOrders_SQL";

// We may want to include terms that we don't want to search for.
if(!empty($bannerClicksDontSearchArrCleaned))
	$uniqeBannerListQry .= " AND $bannerTrackingDontSearch_ForOrders_SQL";

$uniqeBannerListQry .= " AND DateOrdered > '" . $start_mysql_timestamp . "'";


$dbCmd->Query($uniqeBannerListQry);
$uniqueBannerNameArr_fromOrders = $dbCmd->GetValueArr();





// Merge the 2 arrays together
$trackingUniqueNamesArr = array_merge($uniqueBannerNameArr_fromBannerClicks, $uniqueBannerNameArr_fromOrders);
$trackingUniqueNamesArr = array_unique($trackingUniqueNamesArr);




$trackingCodeSearchSQL_OrClause_Name = DbHelper::getOrClauseFromArray("bannerlog.Name", $trackingUniqueNamesArr);
$trackingCodeSearchSQL_OrClause_Referral = DbHelper::getOrClauseFromArray("orders.Referral", $trackingUniqueNamesArr);

$newStartTimestamp = $start_timestamp;
$newEndTimeStamp = $newStartTimestamp + $reportSpanSeconds;



while(true){
	

	$start_mysql_timestamp = date("YmdHis", $newStartTimestamp);
	$end_mysql_timestamp = date("YmdHis", $newEndTimeStamp);
	
	if($reportSpanDays < 1)
		print date("M j Y h:i:s", $newStartTimestamp) . ",";
	else if($reportSpanDays == 1)
		print date("M j Y", $newStartTimestamp) . ",";
	else
		print date("M j Y", $newStartTimestamp) . " (" . $reportSpanDays . " days),";


		
	// Do a nested query to figure out how many clicks each name received 
	$bannerQry = "SELECT count(*) from bannerlog USE INDEX (bannerlog_Date) WHERE " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()) ."
			 AND $trackingCodeSearchSQL_OrClause_Name 
			AND (Date BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") AND  UserAgent NOT LIKE '%Google%' AND UserAgent NOT LIKE '%Slurp%' AND UserAgent NOT LIKE '%FunXXXXWeb%'";

	$dbCmd->Query($bannerQry);
	$BannerClicks = $dbCmd->GetValue();
	
	print $BannerClicks . ",";

		
	// Find out the unique visitors from this banner
	$dbCmd->Query("Select COUNT(DISTINCT IPaddress) FROM bannerlog USE INDEX (bannerlog_Date) WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
			AND $trackingCodeSearchSQL_OrClause_Name 
			AND (Date BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") AND UserAgent NOT LIKE '%Google%' AND UserAgent NOT LIKE '%Slurp%' AND UserAgent NOT LIKE '%FunXXXXWeb%'");
	$BannerVisitors = $dbCmd->GetValue();

	print $BannerVisitors . ",";


	
	if($BannerVisitors > 0)
		print "" . round($BannerClicks/$BannerVisitors,2)  . ",";
	else 
		print "0,";	
	
	
	// Do a nested query to figure out how many purchases each Banner Name received
	$dbCmd->Query("SELECT COUNT(*) from orders USE INDEX (orders_DateOrdered) WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
				AND $trackingCodeSearchSQL_OrClause_Referral 
				AND (DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") ");
	$BannerPurchases = $dbCmd->GetValue();
	
	print $BannerPurchases . ",";
	
	
	
	if($BannerClicks > 0)
		print round($BannerPurchases/$BannerClicks*100,1)  . ",";
	else 
		print "0,";
	
	
	$dbCmd->Query("SELECT COUNT(*) from orders USE INDEX (orders_DateOrdered) WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
				AND $trackingCodeSearchSQL_OrClause_Referral 
				AND (DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") 
				AND FirstTimeCustomer='Y'");
	$BannerPurchasesNewCustomers = $dbCmd->GetValue();

	
	print $BannerPurchasesNewCustomers . ",";
	
	if($BannerClicks > 0)
		print round($BannerPurchasesNewCustomers/$BannerClicks*100,1)  . "";
	else 
		print "0";
		
	print "\n";

	flush();
	
	//sleep(1);
	
	$newStartTimestamp += $reportSpacingIncrementSeconds;
	$newEndTimeStamp = $newStartTimestamp + $reportSpanSeconds;
	
	// Stop Looping when The report increment starts going into the future.
	if($newEndTimeStamp > time())
		break;
}







<?

require_once("library/Boot_Session.php");

// Don't make it look like we are favoring a particular Domain 
Domain::removeTopDomainID();

$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();
$dbCmd3 = new DbCmd();




$domainObj = Domain::singleton();



// Find out if the user has changed categories before in the coupon report... if so use that value ... otherwise default to the "General" category
$rememberMarketingReport = WebUtil::GetCookie("MarketingReportLastView");

$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, $rememberMarketingReport);

if(empty($view))
	$view =  "banners";

// If the current category that we are viewing is different than the "Default"...
// Then set a cookie to remember this preference.
if($view != $rememberMarketingReport){
	$cookieTime = time()+60*60*24*90; // 3 months
	setcookie ("MarketingReportLastView", $view, $cookieTime);
}



// Make this script be able to run for a while
set_time_limit(4000);






$cacheReportForUser = WebUtil::GetInput("CacheReportOnBehalfOfUserID", FILTER_SANITIZE_INT);

// A cron can run reports on behalf of a User (without being logged in).
if(!empty($cacheReportForUser)){
	$cacheReportForUser = intval($cacheReportForUser);
	$AuthObj = new Authenticate(Authenticate::login_OVERRIDE, $cacheReportForUser);
	
	// A pipe delimeted list that we want to use for caching the report
	$domainIDsForReportCache = WebUtil::GetInput("domainIDsForReportCache", FILTER_SANITIZE_STRING_ONE_LINE);
	$domainIDsForReportCacheArr = explode("\|", $domainIDsForReportCache);
	
	foreach($domainIDsForReportCacheArr as $thisDomainIDtoCache){
		if(!Domain::checkIfDomainIDexists($thisDomainIDtoCache) || !$AuthObj->CheckIfUserCanViewDomainID($thisDomainIDtoCache)){
			WebUtil::WebmasterError("Error caching the Marketing Report the domain ID is not available for the user: $cacheReportForUser DomainID: $thisDomainIDtoCache");
			throw new Exception("DomainID is wrong.");
		}
	}
	
	if(empty($domainIDsForReportCacheArr)){
		WebUtil::WebmasterError("Error caching the Marketing Report because there were no domain ID's specified.");
		throw new Exception("Domain missing");
	}
	
	$domainObj->setDomains($domainIDsForReportCacheArr);
}
else{
	//Make sure they are logged in.
	$AuthObj = new Authenticate(Authenticate::login_ADMIN);
}


$AuthObj->EnsureGroup("MEMBER");
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("MARKETING_REPORT"))
	WebUtil::PrintAdminError("Not Available");

	
$message = null;


$t = new Templatex(".");

$t->set_file("origPage", "ad_marketing_report-template.html");
$t->allowVariableToContainBrackets("OUT");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

#-- Process Passed Report Parameters--#
$ReportPeriodIsDateRange = WebUtil::GetInput( "PeriodType", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "TIMEFRAME" ) == "DATERANGE";
$TimeFrameSel = WebUtil::GetInput( "TimeFrame", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "TODAY"  );

$date = getdate();

$startday= WebUtil::GetInput( "DateRangeStartDay", FILTER_SANITIZE_INT, "1" );
$startmonth= WebUtil::GetInput( "DateRangeStartMonth", FILTER_SANITIZE_INT, $date["mon"] );
$startyear= WebUtil::GetInput( "DateRangeStartYear", FILTER_SANITIZE_INT, $date["year"] );

$endday= WebUtil::GetInput( "DateRangeEndDay", FILTER_SANITIZE_INT, $date["mday"] );
$endmonth= WebUtil::GetInput( "DateRangeEndMonth", FILTER_SANITIZE_INT, $date["mon"] );
$endyear= WebUtil::GetInput( "DateRangeEndYear", FILTER_SANITIZE_INT, $date["year"] );


$endPeriodDay = WebUtil::GetInput( "DatePeriodEndDay", FILTER_SANITIZE_INT, $date["mday"] );
$endPeriodMonth = WebUtil::GetInput( "DatePeriodEndMonth", FILTER_SANITIZE_INT, $date["mon"] );
$endPeriodYear = WebUtil::GetInput( "DatePeriodEndYear", FILTER_SANITIZE_INT, $date["year"] );


$endPeriod_timestamp = mktime (23,59,59,$endPeriodMonth,$endPeriodDay,$endPeriodYear);


$bannerClicksTrackingCodeSearch = WebUtil::GetInput( "bannerClicksTrackingCodeSearch", FILTER_SANITIZE_STRING_ONE_LINE);
$displayUnknownOrigins = WebUtil::GetInput( "displayUnknownOrigins", FILTER_SANITIZE_STRING_ONE_LINE);



// Record the last Banner Click tracking code into a Cookie. 
// If we ever get a null value within the input field... then we can use the last value searched for.
$DaysToRemember = 300;
$cookieTime = time()+60*60*24 * $DaysToRemember;

if(!empty($bannerClicksTrackingCodeSearch)){
	if(WebUtil::GetCookie("BannerTrackingSearchCookie") != $bannerClicksTrackingCodeSearch)
		setcookie("BannerTrackingSearchCookie" , $bannerClicksTrackingCodeSearch, $cookieTime);
}
else{
	$bannerClicksTrackingCodeSearch = WebUtil::GetCookie("BannerTrackingSearchCookie", "y-*");
}




// Record in the session what ever the Value of the radio button is for "Display Unkown Origns"
if(!empty($displayUnknownOrigins)){
	if(WebUtil::GetSessionVar("displayUnknownOriginsSess") != $displayUnknownOrigins)
		WebUtil::SetSessionVar("displayUnknownOriginsSess", $displayUnknownOrigins);
}
else{
	$displayUnknownOrigins = WebUtil::GetSessionVar("displayUnknownOriginsSess", "no");
}


if($displayUnknownOrigins == "yes")
	$t->set_var(array("UNKOWN_ORIGINS_CHECKED_YES"=>"checked", "UNKOWN_ORIGINS_CHECKED_NO"=>""));
else if($displayUnknownOrigins == "no")
	$t->set_var(array("UNKOWN_ORIGINS_CHECKED_YES"=>"", "UNKOWN_ORIGINS_CHECKED_NO"=>"checked"));
else
	throw new Exception("Illegal Value with displayUnknownOrigins: " . $displayUnknownOrigins);

$t->set_var( "DISPLAY_UNKNOWN_ORIGNS", $displayUnknownOrigins );



$customerWorthCouponCode = WebUtil::GetInput( "custWorthCouponCode", FILTER_SANITIZE_STRING_ONE_LINE);
$customerWorthArtworkSearch = WebUtil::GetInput( "custWorthArtworkSearch", FILTER_SANITIZE_STRING_ONE_LINE);
$custWorthCustomerName = WebUtil::GetInput( "custWorthCustomerName", FILTER_SANITIZE_STRING_ONE_LINE);
$IPindicator = WebUtil::GetInput("ipindicator", FILTER_SANITIZE_STRING_ONE_LINE);



$limitToProductID = WebUtil::GetInput("productlimit", FILTER_SANITIZE_INT);

#-- Format the dates that we want for MySQL for the date range ----#
if( $ReportPeriodIsDateRange )
{
	$start_timestamp = mktime (0,0,0,$startmonth,$startday,$startyear);
	$end_timestamp = mktime (23,59,59,$endmonth,$endday,$endyear);
	
	if(  $start_timestamp >  $end_timestamp  )	
		$message = "<br><br><br>Invalid Date Range Specified - Unable to Generate Report<br><br><br>";
}
else
{
	$ReportPeriod = Widgets::GetTimeFrame( $TimeFrameSel );
	$start_timestamp = $ReportPeriod[ "STARTDATE" ];
	$end_timestamp = $ReportPeriod[ "ENDDATE" ];
}



$start_mysql_timestamp = date("YmdHis", $start_timestamp);
$end_mysql_timestamp  = date("YmdHis", $end_timestamp);
$endPeriod_mysql_timestamp  = date("YmdHis", $endPeriod_timestamp);

#---- Setup date range selections and and type -----##
$t->set_var( "PERIODTYPETIMEFRAME", $ReportPeriodIsDateRange ? null : "CHECKED" );
$t->set_var( "PERIODISTIMEFRAME", $ReportPeriodIsDateRange ? "false" : "true" );
$t->set_var( "TIMEFRAMESELS", Widgets::BuildTimeFrameSelect( $TimeFrameSel ));
$t->set_var( "PERIODTYPEDATERANGE", $ReportPeriodIsDateRange ? "CHECKED" : null );
$t->set_var( "DATERANGESELS", Widgets::BuildDateRangeSelect( $start_timestamp, $end_timestamp, "D" ));
$t->set_var( "MESSAGE", $message );
$t->set_var( "STARTTIMESTAMP", $start_timestamp );
$t->set_var( "ENDTIMESTAMP", $end_timestamp );
$t->set_var( "VIEW_TYPE", $view );


$t->allowVariableToContainBrackets("MESSAGE");
$t->allowVariableToContainBrackets("TIMEFRAMESELS");
$t->allowVariableToContainBrackets("DATERANGESELS");
$t->allowVariableToContainBrackets("PERIODTYPEDATERANGE");




$totalsDaysInReportPeriod = round(($end_timestamp - $start_timestamp) / (60 * 60 * 24));

// Make parallel arrays of Start & End timestamps for each day (spaced by 24 hours) between Start and End days in the report period.
$singleDaysTimeStampsArr_Start = array();
$singleDaysTimeStampsArr_End = array();

for($i=0; $i < $totalsDaysInReportPeriod; $i++){
	$singleDaysTimeStampsArr_Start[] = $start_timestamp + ($i * 60 * 60 * 24);
	$singleDaysTimeStampsArr_End[] = $start_timestamp + ($i * 60 * 60 * 24) + (60 * 60 * 24);
}




// Set the ending report period for the Customer Worth
$t->set_var("REPORTPERIOD_END_MONTH", $endPeriodMonth);
$t->set_var("REPORTPERIOD_END_DAY", $endPeriodDay);
$t->set_var("REPORTPERIOD_END_YEAR", $endPeriodYear);

$t->set_var("CUSTOMERWORTH_COUPON", WebUtil::htmlOutput($customerWorthCouponCode));
$t->set_var("CUSTOMERWORTH_ARTWORK", WebUtil::htmlOutput($customerWorthArtworkSearch));
$t->set_var("CUSTOMERWORTH_NAMESEARCH", WebUtil::htmlOutput($custWorthCustomerName) );
$t->set_var("IP_INDICATOR", addslashes(WebUtil::htmlOutput($IPindicator)));


$t->set_var( "START_REPORT_DATE_STRING", date("m/d/Y", $start_timestamp));



// Build Drop down for Products.  If the user has more than one domain selected then show the abrieviated Domain Name in front of the ProductName.
$productIDdropDown = array("0"=>"All Products");
if(sizeof($domainObj->getSelectedDomainIDs()) > 1){
	
	foreach($domainObj->getSelectedDomainIDs() as $thisDomainID){
		
		$abrevieatedDomain = Domain::getAbreviatedNameForDomainID($thisDomainID);
		
		$productsForDomainHash = Product::getFullProductNamesHash($dbCmd, $thisDomainID, false);
		
		foreach($productsForDomainHash as $thisProductID => $productDesc)
			$productIDdropDown[$thisProductID] = $abrevieatedDomain . "> " . $productDesc;
	}
}
else{
	$productHash = Product::getFullProductNamesHash($dbCmd, Domain::oneDomain(), false);
	foreach($productHash as $thisProductID => $productDesc)
		$productIDdropDown[$thisProductID] = $productDesc;
}

$t->set_var("PRODUCT_DROPDOWN", Widgets::buildSelect($productIDdropDown, array($limitToProductID)));
$t->allowVariableToContainBrackets("PRODUCT_DROPDOWN");


if( $message )
{
	//Error occurred - discontinue report generation
	$t->discard_block("origPage","ReportBody");
		

	##--- Print Template --#
	$t->pparse("OUT","origPage");
	exit;
}






// ---------  Report Caching ----------------
// Find out if report has already been cached for "Order Statistic Reports"
if($view == "orders" || $view == "banners"){

	if(!file_exists(Constants::GetReportCacheDirectory()))
		throw new Exception("Report Cache Directory Does not exist.");

	// Build a unique String that will determine if the report is unique
	$uniqueReportString = strval($start_timestamp) . strval($end_timestamp) . $limitToProductID;
	
	if($view == "banners")
		$uniqueReportString .= "bannerCodes:" . $bannerClicksTrackingCodeSearch . $displayUnknownOrigins;
	
	// The Report should also contain all of the groups that the user belongs to... This will keep someone from viewing a cached report belonging to someone else.
	$uniqueReportString .= implode(",", $AuthObj->GetGroupsThatUserBelongsTo());
	
	
	// The Report is cached based upon the Domain IDs that were selected.
	$uniqueReportString .= implode(",", $domainObj->getSelectedDomainIDs());
		
	// Take the MD5 of the report string so the unique list won't make the file name too long.
	$reportCachedFileName = Constants::GetReportCacheDirectory() . "/MarketingOrders_" . md5($uniqueReportString);

	// Don't cache report if a parameter in the URL instructs us not to.
	if(file_exists($reportCachedFileName) && WebUtil::GetInput("doNotCacheReport", FILTER_SANITIZE_STRING_ONE_LINE) == null){
		
		// Before we are willing to print out a Cached Report... we have to make sure that the "End Date" of the Report is Less than the date that the report was generated. Otherwise it is an old Report and deserves to be regenerated.
		if(filemtime($reportCachedFileName) > $end_timestamp){
		
			$fd = fopen ($reportCachedFileName, "r");
			$reportCacheHTML = fread ($fd, filesize ($reportCachedFileName));
			fclose ($fd);
			
			$reportCacheHTML = gzuncompress($reportCacheHTML);
			
			
			$reportGeneratedTimeStamp = filemtime($reportCachedFileName);
			
			
			// Look for a place holder within the HTML that we can show a notice of this report being cached.
			$cachedReportMessage = "<font class='SmallBody'>This report was saved on " . date("F j, Y, g:i a", $reportGeneratedTimeStamp ) . ".  <a href='javascript:document.forms[\"ReportOptions\"].doNotCacheReport.value=\"true\"; document.forms[\"ReportOptions\"].submit();' class='BlueRedLink'>Click here</a> to regenerate.</font>";
			$reportCacheHTML = preg_replace("/<!--\sReportCacheNoticePlaceHolder\s-->/", $cachedReportMessage, $reportCacheHTML);
			
			if(!empty($cacheReportForUser))
				print "Can not print a Cached Page with UserID override";
			else
				print $reportCacheHTML;
			
			exit;
		}
	}
	
}





$t->set_block("origPage","CTbl","CTblout");

$BannerClicksArr = array();
$BannerPurchasesNewCustArr = array();
$BannerClicksFromIPsArr = array();
$BannerPurchasesArr = array();
$BannerPurchasesFromIPsArr = array();
$BannerDelayedPurchasesArr = array();
$BannerSameDayNewCustPurchasesArr = array();
$BannerVistorsArr = array();
$BannerClicksFromPurchases = array();
$TotalBannerPurchases = 0;
$TotalBannerPurchasesNewCust = 0;
$TotalBannerPurchasesDelayed = 0;
$TotalBannerPurchasesNewCustSameDay = 0;





// Make an SQL query out of the Banner Click Tracking Code Searches.
// Pipe Symbols separate Multiple tracking codes terms... and * represent Wildcard characters.
// Phrases separated by ! Exclamation points means that it will not show anything that follows after the explanation point.

$bannerClicksSearchArrCleaned = array();
$bannerClicksDontSearchArrCleaned = array();



// The Order Statistics Page should always look at "all banners" ... instead of the search codes 
if($view == "orders")
	$bannerClicksSearchArr = array("*");
else
	$bannerClicksSearchArr = split("\|", $bannerClicksTrackingCodeSearch);




foreach($bannerClicksSearchArr as $thisTrackingCodeSearch){

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








// Get a unique list of Tracking codes from our BannerLog  -------------------------------------
$uniqeBannerListQry = "SELECT DISTINCT Name FROM bannerlog USE INDEX (bannerlog_Date)  WHERE " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs());

// If we are trying to limit our Customer Search by tracking codes from Banner Clicks.
if(!empty($bannerClicksSearchArrCleaned))
	$uniqeBannerListQry .= " AND $bannerTrackingSearchSQL";

// We may want to include terms that we don't want to search for.
if(!empty($bannerClicksDontSearchArrCleaned))
	$uniqeBannerListQry .= " AND $bannerTrackingDontSearchSQL";

$uniqeBannerListQry .= " AND (Date BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") ";
$uniqeBannerListQry .= " AND UserAgent NOT LIKE '%Google%' AND UserAgent NOT LIKE '%Slurp%' ";

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

$uniqeBannerListQry .= " AND (DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") ";

$dbCmd->Query($uniqeBannerListQry);
$uniqueBannerNameArr_fromOrders = $dbCmd->GetValueArr();




// Merge the 2 arrays together
$uniqueBannerNameArr = array_merge($uniqueBannerNameArr_fromBannerClicks, $uniqueBannerNameArr_fromOrders);
$uniqueBannerNameArr = array_unique($uniqueBannerNameArr);





$timeOutCounter = 0;

foreach($uniqueBannerNameArr as $BannerName){

	if($view == "banners"){


		// Prevent Browser Time out.
		if($timeOutCounter > 100){
			print "                                        -                                       ";
			Constants::FlushBufferOutput();
			$timeOutCounter = 0;
		}
		else{
			$timeOutCounter++;
		}

		// Do a nested query to figure out how many clicks each name received 
		$bannerQry = "SELECT count(*) from bannerlog USE INDEX (bannerlog_Date) WHERE " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()) ."
				 AND Name=\"" . DbCmd::EscapeSQL($BannerName)  . "\" 
				AND (Date BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") 
				AND UserAgent NOT LIKE '%Google%' AND UserAgent NOT LIKE '%Slurp%' ";

		// We are disabling this for now. I am able to filter out the Google AdsBots by looking at the user agent.
		//if($displayUnknownOrigins == "no")
		//	$bannerQry .= " AND RefererBlank=0";
		
		$dbCmd2->Query($bannerQry);
		$BannerClicks = $dbCmd2->GetValue();
		
				
		
		
/*
		if($UserID == 2){

							
//				$dbCmd2->Query("SELECT DISTINCT IPaddress FROM orders WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
//							AND Referral=\"" . DbCmd::EscapeSQL($BannerName)  . "\" 
//							AND (DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") 
//							");
//				$bannerOrdersIPs = $dbCmd2->GetValueArr();


				$dbCmd2->Query("SELECT DISTINCT IPaddress FROM bannerlog WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
							AND Name=\"" . DbCmd::EscapeSQL($BannerName)  . "\" 
							AND (Date BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") 
							AND UserAgent NOT LIKE '%Google%' AND UserAgent NOT LIKE '%Slurp%'");
				$bannerOrdersIPs = $dbCmd2->GetValueArr();
				
				
				foreach($bannerOrdersIPs as $thisIPAddress){
					
					
					$dbCmd2->Query("SELECT Name, UNIX_TIMESTAMP(Date) AS Date, UserAgent FROM bannerlog WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
								AND IPaddress=\"" . DbCmd::EscapeSQL($thisIPAddress)  . "\" 
								AND (Date BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . " ) 
								AND UserAgent NOT LIKE '%Google%' AND UserAgent NOT LIKE '%Slurp%' ");
					
					print "<u>" . $thisIPAddress . "</u><br>";
					
					while($bannerRow = $dbCmd2->GetRow()){
						print $bannerRow["Name"] . " - " . date("M, j, g:i A", $bannerRow["Date"]) . " - " . $bannerRow["UserAgent"] . "<br>";
					}
					print "<br><br>";
				}
		}
				
*/				
				
		
		// If we are limiting by IP address then get the counts of banner clicks from them.
		$searchingBannersWithIPflag = false;
		$BannerClicksFromIPs = 0;
		$BannerOrdersFromIPs = 0;
		if(!empty($IPindicator)){
			
			$ipCleanedArr = array();
			$ipListArr = split("\|", $IPindicator);

			foreach($ipListArr as $thisIPadd){
				if(preg_match("/^(\d+(\.)?)+$/", $thisIPadd))
					$ipCleanedArr[] = $thisIPadd;
			}
			
			$IPwhereClause = "";
			
			if(!empty($ipCleanedArr)){
			
				$searchingBannersWithIPflag = true;
				
				for($i=0; $i<sizeof($ipCleanedArr); $i++){
				
					if($i > 0)
						$IPwhereClause .= " OR ";
					
					// Always use a wildcard at the end of the IP address search.  That will allow us to search for subnets, etc.
					$IPwhereClause .= " IPaddress LIKE \"" . DbCmd::EscapeLikeQuery($ipCleanedArr[$i]) . "%\" ";
				}
				
				// Do a nested query to figure out how many clicks each name received 
				$dbCmd2->Query("SELECT count(*) from bannerlog USE INDEX (bannerlog_Date) WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
						AND Name=\"" . DbCmd::EscapeSQL($BannerName)  . "\" 
						AND (Date BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") 
						AND ($IPwhereClause)
						AND UserAgent NOT LIKE '%Google%' AND UserAgent NOT LIKE '%Slurp%' ");
				$BannerClicksFromIPs = $dbCmd2->GetValue();
				
				
				// Figure out how many purchases came from each Banner Name and IP Address list
				$dbCmd2->Query("SELECT COUNT(*) FROM orders WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
							AND Referral=\"" . DbCmd::EscapeSQL($BannerName)  . "\" 
							AND (DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") 
							AND ($IPwhereClause)");
				$BannerOrdersFromIPs = $dbCmd2->GetValue();
				
				
			}
			
			
			// In case the IP addresses were not well formed... then clear out the Variable so that the form isn't defaulted with bad data.
			$newIPList = "";
			for($i=0; $i<sizeof($ipCleanedArr); $i++){
				
				$newIPList .= $ipCleanedArr[$i];
				
				if($i != (sizeof($ipCleanedArr) - 1))
					$newIPList .= "|";
			}
			$t->set_var("IP_INDICATOR", $newIPList);
			

		}



		// Find out the unique visitors from this banner
		$dbCmd2->Query("Select DISTINCT IPaddress FROM bannerlog USE INDEX (bannerlog_Date) WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
				AND Name=\"" . DbCmd::EscapeSQL($BannerName)  . "\" 
				AND (Date BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") 
				AND UserAgent NOT LIKE '%Google%' AND UserAgent NOT LIKE '%Slurp%' ");
		$BannerVisitors = $dbCmd2->GetNumRows();


		// Find out how many clicks where used to generate the orders for this banner name
		// This will not count any clicks that did not result in a purchase
		$dbCmd2->Query("SELECT COUNT(*) FROM bannerlog INNER JOIN orders ON bannerlog.IPaddress = orders.IPaddress WHERE orders.Referral=\"" . DbCmd::EscapeSQL($BannerName)  . "\" AND bannerlog.Name=\"" . DbCmd::EscapeSQL($BannerName)  . "\"
				AND (orders.DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") AND
				" . DbHelper::getOrClauseFromArray("orders.DomainID", $domainObj->getSelectedDomainIDs()) . " 
					AND " . DbHelper::getOrClauseFromArray("bannerlog.DomainID", $domainObj->getSelectedDomainIDs()));
		$BannerClicksForOrders = $dbCmd2->GetValue();
	
	}


	if($view == "banners" || $view == "orders"){

		$BannerPurchases = 0;
		$BannerPurchasesNewCustomers = 0;
		
		// Regeneration Tracking Scripts are stored in the orders table in parallell... we need to to override the orders value if it is a regneration code.
		if(preg_match("/\-regen\-/", $BannerName)){

			// Do a nested query to figure out how many purchases each Banner Name received
			$dbCmd2->Query("SELECT COUNT(*) from orders USE INDEX (orders_DateOrdered) WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
						AND RegenTrackingCode=\"" . DbCmd::EscapeSQL($BannerName)  . "\" 
						AND (DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") ");
			$BannerPurchases = $dbCmd2->GetValue();
	
			$dbCmd2->Query("SELECT COUNT(*) from orders USE INDEX (orders_DateOrdered) WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
						AND RegenTrackingCode=\"" . DbCmd::EscapeSQL($BannerName)  . "\" 
						AND (DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") 
						AND FirstTimeCustomer='Y'");
			$BannerPurchasesNewCustomers = $dbCmd2->GetValue();
			
			// We are not storing the Referral Date on Regeneration Tracking codes.
			$DelayedBannerPurchases = 0;
			$sameDayPurchasesNewCustomers = 0;
		}
		else{
			
			// Do a nested query to figure out how many purchases each Banner Name received
			$dbCmd2->Query("SELECT COUNT(*) from orders USE INDEX (orders_DateOrdered) WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
						AND Referral=\"" . DbCmd::EscapeSQL($BannerName)  . "\" 
						AND (DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") ");
			$BannerPurchases = $dbCmd2->GetValue();
			
			$dbCmd2->Query("SELECT COUNT(*) from orders USE INDEX (orders_DateOrdered) WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
						AND Referral=\"" . DbCmd::EscapeSQL($BannerName)  . "\" 
						AND (DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") 
						AND FirstTimeCustomer='Y'");
			$BannerPurchasesNewCustomers = $dbCmd2->GetValue();
	
			
			$dbCmd2->Query("SELECT COUNT(*) from orders USE INDEX (orders_ReferralDate) WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
						AND Referral=\"" . DbCmd::EscapeSQL($BannerName)  . "\" 
						AND (ReferralDate BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") ");
			$DelayedBannerPurchases = $dbCmd2->GetValue();
	

			$sameDayPurchasesNewCustomers = 0;
			
			// Between the Start and End Dates... Cycle through each day (incrementing by 24 hours) looking for corresponding click & purchase dates.
			for($i=0; $i<sizeof($singleDaysTimeStampsArr_Start); $i++){
				
				$thisStartTimeStampForSingleDay = DbCmd::FormatDBDateTime($singleDaysTimeStampsArr_Start[$i]);
				$thisEndTimeStampForSingleDay = DbCmd::FormatDBDateTime($singleDaysTimeStampsArr_End[$i]);
		
				$dbCmd2->Query("SELECT COUNT(*) from orders USE INDEX (orders_ReferralDate) WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
							AND Referral=\"" . DbCmd::EscapeSQL($BannerName)  . "\" 
							AND (DateOrdered BETWEEN '" . $thisStartTimeStampForSingleDay . "' AND '" . $thisEndTimeStampForSingleDay . "') 
							AND (ReferralDate BETWEEN '" . $thisStartTimeStampForSingleDay . "' AND '" . $thisEndTimeStampForSingleDay . "')
							AND FirstTimeCustomer='Y' ");
				$sameDayPurchasesNewCustomers += $dbCmd2->GetValue();
			}

			

		}

		$TotalBannerPurchases += $BannerPurchases;
		$TotalBannerPurchasesNewCust += $BannerPurchasesNewCustomers;
		$TotalBannerPurchasesDelayed += $DelayedBannerPurchases;
		$TotalBannerPurchasesNewCustSameDay += $sameDayPurchasesNewCustomers;
	}


	if($view == "banners"){
		// Fill up a hash that we will loop through later.
		$BannerClicksArr[$BannerName] = $BannerClicks;
		$BannerClicksFromIPsArr[$BannerName] = $BannerClicksFromIPs;
		$BannerPurchasesFromIPsArr[$BannerName] = $BannerOrdersFromIPs;
		$BannerPurchasesArr[$BannerName] = $BannerPurchases;
		$BannerDelayedPurchasesArr[$BannerName] = $DelayedBannerPurchases;
		$BannerSameDayNewCustPurchasesArr[$BannerName] = $sameDayPurchasesNewCustomers;
		$BannerPurchasesNewCustArr[$BannerName] = $BannerPurchasesNewCustomers;
		$BannerVistorsArr[$BannerName] = $BannerVisitors;
		$BannerClicksFromPurchases[$BannerName] = $BannerClicksForOrders;
	}
}



if($view == "banners"){

	$totalClicks = 0;
	$totalVisitors = 0;
	$totalPurchases = 0;
	$totalPurchasesNewCustomers = 0;
	$totalPurchasesDelayed = 0;
	$totalSameDayNewCustPurchases = 0;
	$timeOutCounter = 0;

	foreach($BannerClicksArr as $BnrNme => $BnrClks)
	{
	
		$bannerPurchaches = $BannerPurchasesArr[$BnrNme];
	
		// If this banner received no clicks and no puraches, then don't bother showing it.
		if($BnrClks == 0 && $bannerPurchaches == 0)
			continue;
		


		// Prevent Browser Time out.
		if($timeOutCounter > 30){
			print " . ";
			Constants::FlushBufferOutput();
			$timeOutCounter = 0;
		}
		else{
			$timeOutCounter++;
		}

		
		// Prevent a divide by Zero
		if($BnrClks != 0){
			$retentionPercent = round($BannerPurchasesArr[$BnrNme] / $BnrClks * 100, 1);
			$retentionPercentNewCustomers = round($BannerPurchasesNewCustArr[$BnrNme] / $BnrClks * 100, 1);
			$delayedPurchasesPercent = round($BannerDelayedPurchasesArr[$BnrNme] / $BnrClks * 100, 1);
			$sameDayNewCustConvPercent = round($BannerSameDayNewCustPurchasesArr[$BnrNme] / $BnrClks * 100, 1);
			
			if($BannerVistorsArr[$BnrNme] != 0)
				$AverageClicksPerPerson = round($BannerClicksArr[$BnrNme]/$BannerVistorsArr[$BnrNme],2);
			else
				$AverageClicksPerPerson = 0;
		}
		else if($bannerPurchaches != 0){
			$retentionPercent = "N/A";
			$retentionPercentNewCustomers = "N/A";
			$AverageClicksPerPerson = "N/A";
			$delayedPurchasesPercent = 0;
			$sameDayNewCustConvPercent = 0;
		}
		


		//prevent a division by 0
		if($BannerPurchasesArr[$BnrNme] == 0){
			$AverageClicksPerPurchase = 0;
		}
		else{
			$AverageClicksPerPurchase = round($BannerClicksFromPurchases[$BnrNme]/$BannerPurchasesArr[$BnrNme],1);
		}
		

		
		$totalClicks += $BnrClks;
		$totalVisitors += $BannerVistorsArr[$BnrNme];
		$totalPurchases += $BannerPurchasesArr[$BnrNme];
		$totalPurchasesDelayed += $BannerDelayedPurchasesArr[$BnrNme];
		$totalSameDayNewCustPurchases += $BannerSameDayNewCustPurchasesArr[$BnrNme];
		$totalPurchasesNewCustomers += $BannerPurchasesNewCustArr[$BnrNme];
		
		
		$linkToOrdersFromBannerStart = "<a href='javascript:ShowOrdersFromBanners(\"" . DbCmd::EscapeSQL($BnrNme) . "\");' class='blueredlink'>";
		$linkToOrdersFromBannerEnd = "</a>";
		
		if($BannerPurchasesArr[$BnrNme] == 0){
			$linkToOrdersFromBannerStart = "";
			$linkToOrdersFromBannerEnd = "";
		}


		$linkToOrdersNewCustFromBannerStart = "<a href='javascript:ShowOrdersWorthFromBanners(\"" . DbCmd::EscapeSQL($BnrNme) . "\");' class='blueredlink'>";
		$linkToOrdersNewCustFromBannerEnd = "</a>";
		
		if($BannerPurchasesNewCustArr[$BnrNme] == 0){
			$linkToOrdersNewCustFromBannerStart = "";
			$linkToOrdersNewCustFromBannerEnd = "";
		}
		
		
		// If we are limiting by IP Addresses then we want to show a new Line underneath the banner clicks showing what percentage of the clicks came from the given IP address.
		// We also want to see how many orders corresponded to it.
		if($searchingBannersWithIPflag){
			
			// Only show the percentage of Clicks if it is more than Zero.
			if($BannerClicksFromIPsArr[$BnrNme] > 0){
				$bannerClicksDisp = $BnrClks . "<br><nobr><font class='reallysmallbody'><font color='#660000'>(" . $BannerClicksFromIPsArr[$BnrNme] . ") - " . round((100 * $BannerClicksFromIPsArr[$BnrNme] / $BnrClks), 1) .  "%</font></font></nobr>";
				
				if($BannerPurchasesArr[$BnrNme] != 0){
					$bannerOrdersFromIPdisp = $BannerPurchasesArr[$BnrNme] . "<br><nobr><font class='reallysmallbody'><font color='#660000'>(" . $BannerPurchasesFromIPsArr[$BnrNme] . ") - " . round((100 * $BannerPurchasesFromIPsArr[$BnrNme] / $BannerPurchasesArr[$BnrNme]), 1) .  "%</font></font></nobr>";
				}
				else{
					$bannerOrdersFromIPdisp = $BannerPurchasesArr[$BnrNme];
				}
					
			}
			else{
				$bannerClicksDisp = $BnrClks;
				$bannerOrdersFromIPdisp = $BannerPurchasesArr[$BnrNme];
			}
		}
		else{
			$bannerClicksDisp = $BnrClks;
			$bannerOrdersFromIPdisp = $BannerPurchasesArr[$BnrNme];
		}
		
		// Regeneration Tracking Scripts should be shown in a different color.
		if(preg_match("/\-regen\-/", $BnrNme)){
			$bannerNameHTML = "<font color='#883300'>$BnrNme</font>";
		}
		else{
			$bannerNameHTML = $BnrNme;
		}

		$t->allowVariableToContainBrackets("CT_NAME");

		if($BnrClks == 0 && ($BannerPurchasesNewCustArr[$BnrNme] != 0 || $BannerPurchasesArr[$BnrNme] != 0))
			$bannerClicksDisp = "<font color='#009900'>LAG</font>";
		
		$t->set_var(array(
			"CT_NAME"=>$bannerNameHTML, 
			"CT_CLICK"=>$bannerClicksDisp, 
			"CT_PURCHASE"=>$bannerOrdersFromIPdisp, 
			"CT_PERCENT"=>$retentionPercent, 
			"CT_DELAY_PURCHASE_PERCENT"=>$delayedPurchasesPercent, 
			"CT_SAMEDAY_NEW_CUST_PERCENT"=>$sameDayNewCustConvPercent, 
			"CT_PERCENT_NEW"=>$retentionPercentNewCustomers, 
			"CT_VISITORS"=>$BannerVistorsArr[$BnrNme], 
			"CT_CLICKSPERSON"=>$AverageClicksPerPerson, 
			"CT_CLICKSPURCHASE"=>$AverageClicksPerPurchase,
			"CT_NEW_PURCHASE"=>$BannerPurchasesNewCustArr[$BnrNme],
			"CT_DELAY_PURCHASE"=>$BannerDelayedPurchasesArr[$BnrNme],
			"CT_SAMEDAY_NEW_CUST_PURCHASE"=>$BannerSameDayNewCustPurchasesArr[$BnrNme],
			"CT_ORDERS_LINK_START"=>$linkToOrdersFromBannerStart,
			"CT_ORDERS_LINK_END"=>$linkToOrdersFromBannerEnd,
			"CT_NEW_ORDERS_LINK_START"=>$linkToOrdersNewCustFromBannerStart,
			"CT_NEW_ORDERS_LINK_END"=>$linkToOrdersNewCustFromBannerEnd
			));

		$t->parse("CTblout","CTbl",true);
	}
	
	// Set Default Dates for the CSV Report parameters.
	$t->set_var("START_CSV_MONTH_OPTIONS", Widgets::BuildMonthSelect($startmonth, "startMonth"));
	$t->set_var("START_CSV_DAY_OPTIONS", Widgets::BuildDaySelect($startday, "startDay"));
	$t->set_var("START_CSV_YEAR_OPTIONS", Widgets::BuildYearSelect($startyear, "startYear"));

	$t->allowVariableToContainBrackets("CT_CLICK");
	$t->allowVariableToContainBrackets("START_CSV_MONTH_OPTIONS");
	$t->allowVariableToContainBrackets("START_CSV_DAY_OPTIONS");
	$t->allowVariableToContainBrackets("START_CSV_YEAR_OPTIONS");
	
	$daysOfReportSpan = round(($end_timestamp - $start_timestamp) / (60*60*24));
	
	$t->set_var("REPORT_DAYS_SPAN", $daysOfReportSpan);
	$t->set_var("REPORT_DAYS_SPACING", $daysOfReportSpan);

	
	
	$totalClicksApersonRatio = 0;
	if($totalVisitors != 0)
		$totalClicksApersonRatio = round($totalClicks / $totalVisitors, 2);
	
	if(!empty($BannerClicksArr)){
		$t->set_var(array(
			"CT_NAME"=>WebUtil::htmlOutput($bannerClicksTrackingCodeSearch), 
			"CT_CLICK"=>"<b>" . $totalClicks . "</b>", 
			"CT_PURCHASE"=>"<b>" . $totalPurchases . "</b>", 
			"CT_PERCENT"=>"<b>" . round($totalPurchases / $totalClicks * 100, 1) . "</b>", 
			"CT_DELAY_PURCHASE_PERCENT"=>"<b>" . round($totalPurchasesDelayed / $totalClicks * 100, 1) . "</b>", 
			"CT_SAMEDAY_NEW_CUST_PERCENT"=>"<b>" . round($totalSameDayNewCustPurchases / $totalClicks * 100, 1) . "</b>", 
			"CT_PERCENT_NEW"=>"<b>" . round($totalPurchasesNewCustomers / $totalClicks * 100, 1) . "</b>", 
			"CT_VISITORS"=>"<b>" . $totalVisitors . "</b>", 
			"CT_CLICKSPERSON"=>"$totalClicksApersonRatio", 
			"CT_DELAY_PURCHASE"=>"<b>" . $totalPurchasesDelayed . "</b>",
			"CT_SAMEDAY_NEW_CUST_PURCHASE"=>"<b>" . $totalSameDayNewCustPurchases . "</b>",
			"CT_NEW_PURCHASE"=>"<b>" . $totalPurchasesNewCustomers . "</b>",
			"CT_ORDERS_LINK_START"=>"",
			"CT_ORDERS_LINK_END"=>"",
			"CT_NEW_ORDERS_LINK_START"=>"",
			"CT_NEW_ORDERS_LINK_END"=>""
			));
			
		$t->allowVariableToContainBrackets("CT_NAME");
		$t->allowVariableToContainBrackets("CT_CLICK");
		$t->allowVariableToContainBrackets("CT_PURCHASE");
		$t->allowVariableToContainBrackets("CT_PERCENT");
		$t->allowVariableToContainBrackets("CT_VISITORS");
		$t->allowVariableToContainBrackets("CT_NEW_PURCHASE");
		$t->allowVariableToContainBrackets("CT_DELAY_PURCHASE");
		$t->allowVariableToContainBrackets("CT_DELAY_PURCHASE_PERCENT");
		$t->allowVariableToContainBrackets("CT_SAMEDAY_NEW_CUST_PERCENT");
		$t->allowVariableToContainBrackets("CT_SAMEDAY_NEW_CUST_PURCHASE");
		$t->allowVariableToContainBrackets("CT_PERCENT_NEW");

		$t->parse("CTblout","CTbl",true);
	
	}
	else{
	
		$t->set_block("origPage","EmptyBannerClicks","EmptyBannerClicksOut");
		$t->set_var("EmptyBannerClicksOut", "<br><br>No banner clicks within this period.");		
	}
}




if($view == "questions"){

	$t->set_block("origPage","HAbl","HAblout");

	// Get a unique list of names from our BannerLog --#
	$dbCmd->Query("SELECT DISTINCT HearAbout FROM users 
					WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
					AND HearAbout IS NOT NULL");

	$haCounter = 0;
	while($HearAbout = $dbCmd->GetValue())
	{
		// Do a nested query to figure out how many matches the names received --#
		$dbCmd2->Query("Select COUNT(*) from users WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
				AND Hearabout=\"" . DbCmd::EscapeSQL($HearAbout)  . "\" 
				AND (DateCreated BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") ");
		$HearAboutTotal = $dbCmd2->GetValue();

		if(empty($HearAbout))
			$HearAbout = "Not Disclosed";
			
		$haCounter++;
		
		$t->set_var(array("HA_NAME"=>$HearAbout, "HA_TOTAL"=>$HearAboutTotal));
		$t->parse("HAblout","HAbl",true);
	}
	
	if($haCounter == 0)
		$t->set_var("HAblout", "");
	
	
	
	$dbCmd->Query("SELECT COUNT(*) FROM users WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
					AND CopyrightTemplates=\"Y\" AND (DateCreated BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") ");
	$copyrightYes = $dbCmd->GetValue();
	
	$dbCmd->Query("SELECT COUNT(*) FROM users WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
				AND CopyrightTemplates=\"N\" AND CopyrightHiddenAtReg='N' AND (DateCreated BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") ");
	$copyrightNo = $dbCmd->GetValue();
	
	$dbCmd->Query("SELECT COUNT(*) FROM users WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
				AND CopyrightHiddenAtReg='Y' AND (DateCreated BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") ");
	$copyrightHidden = $dbCmd->GetValue();
	
	$totalUsersNotCopyrightHidden = $copyrightYes + $copyrightNo;
	

	$t->set_var("COPYRIGHT_TEMPLATE_YES", $copyrightYes);
	$t->set_var("COPYRIGHT_TEMPLATE_NO", $copyrightNo);
	$t->set_var("COPYRIGHT_TEMPLATE_HIDDEN", $copyrightHidden);
	
	
	if($totalUsersNotCopyrightHidden == 0)
		$t->set_var("COPYRIGHT_TEMPLATE_YES_PERCENT", "");
	else
		$t->set_var("COPYRIGHT_TEMPLATE_YES_PERCENT", round($copyrightYes / $totalUsersNotCopyrightHidden * 100, 1) . "%");



	$dbCmd->Query("SELECT COUNT(*) FROM users WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
						AND Newsletter=\"Y\" AND (DateCreated BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") ");
	$newsletterYes = $dbCmd->GetValue();
	
	$dbCmd->Query("SELECT COUNT(*) FROM users WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
					AND Newsletter=\"N\" AND (DateCreated BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") ");
	$newsletterNo = $dbCmd->GetValue();
	
	$t->set_var("NEWSLETTERS_YES", $newsletterYes);
	$t->set_var("NEWSLETTERS_NO", $newsletterNo);
	
	
	
	$totalUsers = $copyrightYes + $copyrightNo;
	

	
	
	$dbCmd->Query("SELECT COUNT(*) FROM users WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
						AND LoyaltyProgram=\"Y\" AND (DateCreated BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") ");
	$loyaltyYes = $dbCmd->GetValue();
	
	$dbCmd->Query("SELECT COUNT(*) FROM users WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
						AND LoyaltyProgram=\"N\" AND LoyaltyHiddenAtReg=\"N\" AND (DateCreated BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") ");
	$loyaltyNo = $dbCmd->GetValue();

	$dbCmd->Query("SELECT COUNT(*) FROM users WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
						AND LoyaltyHiddenAtReg=\"Y\" AND (DateCreated BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") ");
	$loyaltyHidden = $dbCmd->GetValue();
	
	
	$totalUsersNotLoyaltyHidden = $loyaltyYes + $loyaltyNo;
	
	if($totalUsersNotLoyaltyHidden == 0)
		$t->set_var("LOYALTY_YES_PERCENT", "");
	else
		$t->set_var("LOYALTY_YES_PERCENT", round($loyaltyYes / $totalUsersNotLoyaltyHidden * 100, 1) . "%");
	
	$t->set_var("LOYALTY_YES", $loyaltyYes);
	$t->set_var("LOYALTY_NO", $loyaltyNo);
	$t->set_var("LOYALTY_HIDDEN", $loyaltyHidden);
	
	
	
	if($totalUsers == 0){
		$t->set_var("COPYRIGHT_TEMPLATE_YES_PERCENT", "");
		$t->set_var("NEWSLETTERS_YES_PERCENT", "");
		$t->set_var("LOYALTY_HIDDEN_PERCENT", "");
		$t->set_var("COPYRIGHT_HIDDEN_PERCENT", "");
	}
	else{
		$t->set_var("COPYRIGHT_TEMPLATE_YES_PERCENT", round($copyrightYes/$totalUsers * 100, 1) . "%");
		$t->set_var("NEWSLETTERS_YES_PERCENT", round($newsletterYes/$totalUsers * 100, 1) . "%");
		$t->set_var("LOYALTY_HIDDEN_PERCENT", round($loyaltyHidden/$totalUsers * 100, 1) . "%");
		$t->set_var("COPYRIGHT_HIDDEN_PERCENT", round($copyrightHidden/$totalUsers * 100, 1) . "%");
	}
	
	
	
	$t->set_block("origPage","NewsLetterSignUpBL","NewsLetterSignUpBLout");
	
	$dbCmd->Query("SELECT DISTINCT BannerTrackingCode FROM emailnewsletterrequest 
				WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
				AND (DateSubmitted BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") ");
	$bannerCodesFromNewsLetterArr = $dbCmd->GetValueArr();

	$totalSignUps = 0;
	foreach($bannerCodesFromNewsLetterArr as $thisBannerCode){
		
		if(empty($thisBannerCode))
			continue;
		
		$dbCmd->Query("SELECT COUNT(DISTINCT Email) FROM emailnewsletterrequest 
					WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
					AND (DateSubmitted BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ")
					AND BannerTrackingCode='".DbCmd::EscapeSQL($thisBannerCode)."' ");
		
		$thisBannerCodeSignUpCount = $dbCmd->GetValue();
		
		$totalSignUps += $thisBannerCodeSignUpCount;
		
		$t->set_var(array("NL_BANNER_CODE"=>WebUtil::htmlOutput($thisBannerCode), "NL_COUNT"=>$thisBannerCodeSignUpCount));
		
		$t->parse("NewsLetterSignUpBLout","NewsLetterSignUpBL",true);
	}
	
	// Find out how manyt sign-up without a Tracking Code
	$dbCmd->Query("SELECT COUNT(DISTINCT Email) FROM emailnewsletterrequest 
				WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
				AND (DateSubmitted BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ")
				AND BannerTrackingCode IS NULL");
	$nullBannerCodeSignUpCount = $dbCmd->GetValue();
	$totalSignUps += $nullBannerCodeSignUpCount;
	
	if($nullBannerCodeSignUpCount > 0){
		// Set the Total in the Last Row
		$t->set_var(array("NL_BANNER_CODE"=>"No Tracking Code", "NL_COUNT"=>$nullBannerCodeSignUpCount));
		$t->parse("NewsLetterSignUpBLout","NewsLetterSignUpBL",true);
	}
	
	$t->allowVariableToContainBrackets("NL_BANNER_CODE");
	$t->allowVariableToContainBrackets("NL_COUNT");
	
	// Set the Total in the Last Row
	$t->set_var(array("NL_BANNER_CODE"=>"<b>Total</b>", "NL_COUNT"=>"<b>". $totalSignUps . "</b>"));
	$t->parse("NewsLetterSignUpBLout","NewsLetterSignUpBL",true);
	
	
	
}



if($view == "customerworth"){

	// Store the customer ID's if they fall within the ranges of Total (profit)
	$customerWorthRangeArr_0to20 = array();
	$customerWorthRangeArr_20to50 = array();
	$customerWorthRangeArr_50to100 = array();
	$customerWorthRangeArr_100to200 = array();
	$customerWorthRangeArr_200to500 = array();
	$customerWorthRangeArr_500to1000 = array();
	$customerWorthRangeArr_1000to3000 = array();
	$customerWorthRangeArr_greater3000 = array();
	
	
	// Store the customer ID's if they fall within the ranges of number of orders placed by a single customer.
	$customerOrdersPlaced_1 = array();
	$customerOrdersPlaced_2to2 = array();
	$customerOrdersPlaced_3to3 = array();
	$customerOrdersPlaced_4to5 = array();
	$customerOrdersPlaced_6to10 = array();
	$customerOrdersPlaced_11to20 = array();
	$customerOrdersPlaced_21to50 = array();
	$customerOrdersPlaced_greater50 = array();


	// Create the Ending Period Drop Downs that measures the end of the Customer Worth
	$t->set_var("MONTH_PERIOD_END", Widgets::BuildMonthSelect( $endPeriodMonth, "DatePeriodEndMonth", "AdminDropDown", "EndPeriodMonthChange(this.value)"));
	$t->set_var("DAY_PERIOD_END", Widgets::BuildDaySelect( $endPeriodDay, "DatePeriodEndDay", "AdminDropDown", "EndPeriodDayChange(this.value)"));
	$t->set_var("YEAR_PERIOD_END", Widgets::BuildYearSelect( $endPeriodYear, "DatePeriodEndYear", "AdminDropDown", "EndPeriodYearChange(this.value)"));
	
	$t->allowVariableToContainBrackets("YEAR_PERIOD_END");
	$t->allowVariableToContainBrackets("DAY_PERIOD_END");
	$t->allowVariableToContainBrackets("MONTH_PERIOD_END");
	
	// If the user has not searched by a banner name then we need to get rid of the Block of HTML that will let us estimate the CPC charges.
	// Otherwise show how many times that banner received clicks within the period.
	if(empty($bannerClicksSearchArrCleaned) && empty($bannerClicksDontSearchArrCleaned)){
		$t->discard_block("origPage","BannerAnalysisBL");
	}
	else{
	
	
		$custWorthBannerCountSQL = "SELECT COUNT(*) FROM bannerlog USE INDEX (bannerlog_Date) WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
								AND Date BETWEEN $start_mysql_timestamp AND $end_mysql_timestamp";

		// If we are trying to limit our Customer Search by tracking codes from Banner Clicks.
		if(!empty($bannerClicksSearchArrCleaned))
			$custWorthBannerCountSQL .= " AND $bannerTrackingSearchSQL";

		// We may want to include terms that we don't want to search for.
		if(!empty($bannerClicksDontSearchArrCleaned))
			$custWorthBannerCountSQL .= " AND $bannerTrackingDontSearchSQL";



		// We are disabling this for now. I am able to filter out the Google AdsBots by looking at the user agent.
		//if($displayUnknownOrigins == "no")
		//	$custWorthBannerCountSQL .= " AND RefererBlank=0";
		
		$dbCmd->Query($custWorthBannerCountSQL);
		$t->set_var("CUST_WORTH_BANNER_TRACKING_COUNT", $dbCmd->GetValue());
	}



	if($endPeriod_timestamp < $end_timestamp){
		$t->set_block("origPage","CustomerWorthHideBL","CustomerWorthHideBLout");
		$t->set_var("CustomerWorthHideBLout", "<br><br><font class='error'>The Ending Period must be greater than or equal to the Time Range.</font>");
	}
	else{
	

		$t->set_var("START_TIMERANGE_DESC", date("F j, Y", $start_timestamp));
		$t->set_var("END_TIMERANGE_DESC", date("F j, Y", $end_timestamp));
		$t->set_var("ENDING_PERIOD_DESC", date("F j, Y", $endPeriod_timestamp));
	
		
		// Arrays to hold all of the First-Time Customer ID's within the time range.
		$firstTimeCustomerIDarr = array();
		$firstTimeCustIDproductSoloArr = array();
		$firstTimeCustIDproductMixedArr = array();
		
		
		// Get a unique list of Customer ID's within the Time Range
		$userIDQuery = "SELECT DISTINCT UserID FROM orders WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
						AND DateOrdered BETWEEN $start_mysql_timestamp AND $end_mysql_timestamp";
		
		
		// If the user is trying to limit by coupon code then refine the query even more.
		if(!empty($customerWorthCouponCode)){
			
			// Get the coupon ID from the coupon Name.
			$dbCmd->Query("SELECT ID from coupons WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
							AND Code LIKE '" . DbCmd::EscapeLikeQuery($customerWorthCouponCode) . "'");
			$couponID = $dbCmd->GetValue();
			
			// Just in case the coupon Name does not exist.
			if($couponID)
				$userIDQuery .= " AND CouponID=$couponID";
			else
				$userIDQuery .= " AND CouponID=99999999";
		}
		
		// If we are trying to limit our Customer Search by tracking codes from Banner Clicks.
		if(!empty($bannerClicksSearchArrCleaned))
			$userIDQuery .= " AND $bannerTrackingSearch_ForOrders_SQL";
		
		// We may want to include terms that we don't want to search for.
		if(!empty($bannerClicksDontSearchArrCleaned))
			$userIDQuery .= " AND $bannerTrackingDontSearch_ForOrders_SQL";
		

		

		$dbCmd->Query($userIDQuery);

		while($thisUserID = $dbCmd->GetValue()){
		
			// If we are trying to limit our Customer Search by Customer or Company Name.
			if(!empty($custWorthCustomerName)){
			
				// Concatenate the Customer Name and user within a string and to a patern match within both.
				// If we can't find a match then 
				$dbCmd2->Query("SELECT Name, Company FROM users WHERE ID=$thisUserID");
				$customerRow = $dbCmd2->GetRow();
				
				$customerNameSearch = $customerRow["Name"] . " " . $customerRow["Company"];
				if(!preg_match("/" . addslashes($custWorthCustomerName) . "/i", ($customerRow["Name"] . " " . $customerRow["Company"])))
					continue;
			
			}
			

			// Find out if the user has placed any orders before the start time ... which will tell us that the User became a new customer within the Period.
			$dbCmd2->Query("SELECT COUNT(*) FROM orders WHERE UserID=$thisUserID AND DateOrdered < $start_mysql_timestamp");
			if($dbCmd2->GetValue() == 0){
			
			
				// Since we know that they are a first-time customer within the period now... Find out the first order number. 
				$dbCmd2->Query("SELECT ID FROM orders WHERE UserID=$thisUserID AND DateOrdered >= $start_mysql_timestamp ORDER BY ID ASC LIMIT 1");
				$firstOrderID = $dbCmd2->GetValue();
			
			
				// If we are limiting this report by Artwork Keywords... then find out if we can find a match within any of the artworks for this first order.
				// If we don't find an artwork match then skip this user Loop.
				if(!empty($customerWorthArtworkSearch)){
					$dbCmd2->Query("SELECT COUNT(*) FROM projectsordered
							INNER JOIN orders ON orders.ID = projectsordered.OrderID
							WHERE orders.ID =  $firstOrderID 
							AND ArtworkFile like \"%". DbCmd::EscapeSQL($customerWorthArtworkSearch) . "%\"");
				
					$projectCountFromArtworkSearch = $dbCmd2->GetValue();
					
					if($projectCountFromArtworkSearch == 0)
						continue;
				}
			
			
				
				$firstTimeCustomerIDarr[] = $thisUserID;
				
				// If we are limiting by Product ID.... let us make a separate list of new customers that bought only that product on their order.
				if($limitToProductID){
				
					// Get a unique list of ProductID's in their first order.

					
					$dbCmd2->Query("SELECT DISTINCT ProductID FROM projectsordered WHERE OrderID=$firstOrderID");
					$productsInFirstOrderArr = array();
					while($thisProductID = $dbCmd2->GetValue())
						$productsInFirstOrderArr[] = $thisProductID;
					
					if(sizeof($productsInFirstOrderArr) == 1 && $productsInFirstOrderArr[0] == $limitToProductID)
						$firstTimeCustIDproductSoloArr[] = $thisUserID;
						
					if(sizeof($productsInFirstOrderArr) > 1 && in_array($limitToProductID, $productsInFirstOrderArr))
						$firstTimeCustIDproductMixedArr[] = $thisUserID;
					
				}
			}
		}
		
		
		
		
		
		// If we are not limiting the Customer Worth Report by Product ID.
		if(!$limitToProductID){
			
			$totalCustomerSpend = 0;
			$totalCustomerProfit = 0;
			
			$firstTimeCustomerSpend = 0;
			$firstTimeCustomerProfit = 0;
			
			$totalRepeatCustomers = 0;

			foreach($firstTimeCustomerIDarr as $thisCustID){
			
				$firstOrderFlag = true;
			
				$thisCustomerTotalProfit = 0;

				$dbCmd->Query("SELECT ID FROM orders WHERE UserID=$thisCustID AND DateOrdered BETWEEN $start_mysql_timestamp AND $endPeriod_mysql_timestamp ORDER BY ID ASC");
				
				if($dbCmd->GetNumRows() > 1)
					$totalRepeatCustomers++;
				
				while($orderID = $dbCmd->GetValue()){

					$customerSubtotal = Order::GetTotalFromOrder($dbCmd2, $orderID, "customersubtotal"); 
					$customerDiscount = Order::GetTotalFromOrder($dbCmd2, $orderID, "customerdiscount"); 
					$vendorSubtotal = Order::GetTotalFromOrder($dbCmd2, $orderID, "vendorsubtotal"); 


					$totalCustomerSpend += $customerSubtotal - $customerDiscount;
					$totalCustomerProfit += $customerSubtotal - $customerDiscount - $vendorSubtotal;
					$thisCustomerTotalProfit += $customerSubtotal - $customerDiscount - $vendorSubtotal;
					
					if($firstOrderFlag){
						$firstTimeCustomerSpend += $customerSubtotal - $customerDiscount;
						$firstTimeCustomerProfit += $customerSubtotal - $customerDiscount - $vendorSubtotal;
					}
					
					$firstOrderFlag = false;

				}
				
				// Decide what category of customer worth that they fall in.
				if($thisCustomerTotalProfit < 20)
					$customerWorthRangeArr_0to20[] = $thisCustID;
				else if($thisCustomerTotalProfit >= 20 && $thisCustomerTotalProfit < 50)
					$customerWorthRangeArr_20to50[] = $thisCustID;
				else if($thisCustomerTotalProfit >= 50 && $thisCustomerTotalProfit < 100)
					$customerWorthRangeArr_50to100[] = $thisCustID;
				else if($thisCustomerTotalProfit >= 100 && $thisCustomerTotalProfit < 200)
					$customerWorthRangeArr_100to200[] = $thisCustID;
				else if($thisCustomerTotalProfit >= 200 && $thisCustomerTotalProfit < 500)
					$customerWorthRangeArr_200to500[] = $thisCustID;
				else if($thisCustomerTotalProfit >= 500 && $thisCustomerTotalProfit < 1000)
					$customerWorthRangeArr_500to1000[] = $thisCustID;
				else if($thisCustomerTotalProfit >= 1000 && $thisCustomerTotalProfit < 3000)
					$customerWorthRangeArr_1000to3000[] = $thisCustID;
				else if($thisCustomerTotalProfit >= 3000)
					$customerWorthRangeArr_greater3000[] = $thisCustID;




				$dbCmd->Query("SELECT COUNT(*) FROM orders WHERE UserID=$thisCustID AND DateOrdered BETWEEN $start_mysql_timestamp AND $endPeriod_mysql_timestamp");
				$orderCountFromCustomer = $dbCmd->GetValue();


				// Decide what Number of Orders that they fall in.
				if($orderCountFromCustomer == 1)
					$customerOrdersPlaced_1[] = $thisCustID;
				else if($orderCountFromCustomer == 2)
					$customerOrdersPlaced_2to2[] = $thisCustID;
				else if($orderCountFromCustomer == 3)
					$customerOrdersPlaced_3to3[] = $thisCustID;
				else if($orderCountFromCustomer >= 4 && $orderCountFromCustomer <= 5)
					$customerOrdersPlaced_4to5[] = $thisCustID;
				else if($orderCountFromCustomer >= 6 && $orderCountFromCustomer <= 10)
					$customerOrdersPlaced_6to10[] = $thisCustID;
				else if($orderCountFromCustomer >= 11 && $orderCountFromCustomer <= 20)
					$customerOrdersPlaced_11to20[] = $thisCustID;
				else if($orderCountFromCustomer >= 21 && $orderCountFromCustomer <= 50)
					$customerOrdersPlaced_21to50[] = $thisCustID;
				else if($orderCountFromCustomer >= 50)
					$customerOrdersPlaced_greater50[] = $thisCustID;


			}
			
			// Get Rid of the HTML block for Product Specific stuff.
			$t->discard_block("origPage","CustomerSingleProduct");
			
			
			$t->set_var("NEW_CUSTOMER_COUNT", sizeof($firstTimeCustomerIDarr));
			
			$t->set_var("TOTAL_CUSTOMER_SPEND", '$' .  number_format($totalCustomerSpend, 2));
			$t->set_var("TOTAL_CUSTOMER_SPEND_NO_COMMA", $totalCustomerSpend);
			$t->set_var("ESTIMATED_PROFIT", '$' . number_format($totalCustomerProfit, 2));
			
			if(sizeof($firstTimeCustomerIDarr) == 0){
				$t->set_var("APPROX_CUSTOMER_WORTH", '$0.00');
				$t->set_var("AVERAGE_CUSTOMER_REVENUE", '$0.00');
				$t->set_var("AVERAGE_FIRST_CUSTOMER_REVENUE", '$0.00');
				$t->set_var("APPROX_FIRST_CUSTOMER_WORTH", '$0.00');
				$t->set_var("REPEAT_CUSTOMERS_FROM_CUST_WORTH", '0');

				
			}
			else{
				$t->set_var("APPROX_CUSTOMER_WORTH", '$' . number_format(round($totalCustomerProfit/sizeof($firstTimeCustomerIDarr), 2), 2));
				$t->set_var("AVERAGE_CUSTOMER_REVENUE", '$' . number_format(round($totalCustomerSpend/sizeof($firstTimeCustomerIDarr), 2), 2));
				$t->set_var("APPROX_FIRST_CUSTOMER_WORTH", '$' . number_format(round($firstTimeCustomerProfit/sizeof($firstTimeCustomerIDarr), 2), 2));
				$t->set_var("AVERAGE_FIRST_CUSTOMER_REVENUE", '$' . number_format(round($firstTimeCustomerSpend/sizeof($firstTimeCustomerIDarr), 2), 2));
			
				$t->set_var("REPEAT_CUSTOMERS_FROM_CUST_WORTH", $totalRepeatCustomers . " (" . round(100 * $totalRepeatCustomers / sizeof($firstTimeCustomerIDarr), 1) . "%)");

			}



			// In case we have any banner clicks... set these variables for Javascript.
			$t->set_var("CUST_WORTH_NEW_CUSTOMER_COUNT_FROM_BANNER", sizeof($firstTimeCustomerIDarr));
			$t->set_var("CUST_WORTH_PROFIT_FROM_BANNER", $totalCustomerProfit);
			
			
			// Set the Customer Worth By Range Table
			$t->set_var("CUST_W_0_20", sizeof($customerWorthRangeArr_0to20));
			$t->set_var("CUST_W_20_50", sizeof($customerWorthRangeArr_20to50));
			$t->set_var("CUST_W_50_100", sizeof($customerWorthRangeArr_50to100));
			$t->set_var("CUST_W_100_200", sizeof($customerWorthRangeArr_100to200));
			$t->set_var("CUST_W_200_500", sizeof($customerWorthRangeArr_200to500));
			$t->set_var("CUST_W_500_1000", sizeof($customerWorthRangeArr_500to1000));
			$t->set_var("CUST_W_1000_3000", sizeof($customerWorthRangeArr_1000to3000));
			$t->set_var("CUST_W_3000_UP", sizeof($customerWorthRangeArr_greater3000));
			
			
			

			// Set the Customer Order Numbers By Range Table
			$t->set_var("CUST_O_1", sizeof($customerOrdersPlaced_1));
			$t->set_var("CUST_O_2", sizeof($customerOrdersPlaced_2to2));
			$t->set_var("CUST_O_3", sizeof($customerOrdersPlaced_3to3));
			$t->set_var("CUST_O_4_5", sizeof($customerOrdersPlaced_4to5));
			$t->set_var("CUST_O_6_10", sizeof($customerOrdersPlaced_6to10));
			$t->set_var("CUST_O_11_20", sizeof($customerOrdersPlaced_11to20));
			$t->set_var("CUST_O_21_50", sizeof($customerOrdersPlaced_21to50));
			$t->set_var("CUST_O_50_UP", sizeof($customerOrdersPlaced_greater50));



			
			
			
			if(sizeof($firstTimeCustomerIDarr) == 0){
				$t->set_block("origPage","CustomerWorthHideBL","CustomerWorthHideBLout");
				$t->set_var("CustomerWorthHideBLout", "<br><br><font class='error'>There were no orders matching your search criteria..</font>");
			}
		}

		




		
		// If we are reporting Customer Worth (Product ID) it will be a little more complicated.
		if($limitToProductID){
		
			$mixedProductCustomerSpend = 0;
			$mixedProductCustomerProfit = 0;
			$mixedProductExclusiveCustomerSpend = 0;
			$mixedProductExclusiveCustomerProfit = 0;
		
			foreach($firstTimeCustIDproductMixedArr as $thisCustID){

				$dbCmd->Query("SELECT ID FROM orders WHERE UserID=$thisCustID AND DateOrdered BETWEEN $start_mysql_timestamp AND $endPeriod_mysql_timestamp");
				while($orderID = $dbCmd->GetValue()){

					// Get the Order totals for all products placed by this customer.
					$customerSubtotal = Order::GetTotalFromOrder($dbCmd2, $orderID, "customersubtotal"); 
					$customerDiscount = Order::GetTotalFromOrder($dbCmd2, $orderID, "customerdiscount"); 
					$vendorSubtotal = Order::GetTotalFromOrder($dbCmd2, $orderID, "vendorsubtotal"); 
					
					$mixedProductCustomerSpend += $customerSubtotal - $customerDiscount;
					$mixedProductCustomerProfit += $customerSubtotal - $customerDiscount - $vendorSubtotal;



					// Get the Order totals ... limiting to the Product Only.
					$customerSubtotal = Order::GetTotalFromOrder($dbCmd2, $orderID, "customersubtotal", null, $limitToProductID); 
					$customerDiscount = Order::GetTotalFromOrder($dbCmd2, $orderID, "customerdiscount", null, $limitToProductID); 
					$vendorSubtotal = Order::GetTotalFromOrder($dbCmd2, $orderID, "vendorsubtotal", null, $limitToProductID); 

					$mixedProductExclusiveCustomerSpend += $customerSubtotal - $customerDiscount;
					$mixedProductExclusiveCustomerProfit += $customerSubtotal - $customerDiscount - $vendorSubtotal;
				}
			}
			
			
			
			$soloProductCustomerSpend = 0;
			$soloProductCustomerProfit = 0;
			$soloProductExclusiveCustomerSpend = 0;
			$soloProductExclusiveCustomerProfit = 0;
		
			foreach($firstTimeCustIDproductSoloArr as $thisCustID){

				$dbCmd->Query("SELECT ID FROM orders WHERE UserID=$thisCustID AND DateOrdered BETWEEN $start_mysql_timestamp AND $endPeriod_mysql_timestamp");
				while($orderID = $dbCmd->GetValue()){


					// Get the Order totals for all products placed by this customer.
					$customerSubtotal = Order::GetTotalFromOrder($dbCmd2, $orderID, "customersubtotal"); 
					$customerDiscount = Order::GetTotalFromOrder($dbCmd2, $orderID, "customerdiscount"); 
					$vendorSubtotal = Order::GetTotalFromOrder($dbCmd2, $orderID, "vendorsubtotal"); 

					$soloProductCustomerSpend += $customerSubtotal - $customerDiscount;
					$soloProductCustomerProfit += $customerSubtotal - $customerDiscount - $vendorSubtotal;



					// Get the Order totals ... limiting to the Product Only.
					$customerSubtotal = Order::GetTotalFromOrder($dbCmd2, $orderID, "customersubtotal", null, $limitToProductID); 
					$customerDiscount = Order::GetTotalFromOrder($dbCmd2, $orderID, "customerdiscount", null, $limitToProductID); 
					$vendorSubtotal = Order::GetTotalFromOrder($dbCmd2, $orderID, "vendorsubtotal", null, $limitToProductID); 

					$soloProductExclusiveCustomerSpend += $customerSubtotal - $customerDiscount;
					$soloProductExclusiveCustomerProfit += $customerSubtotal - $customerDiscount - $vendorSubtotal;

				}
			}
		
		
		
			// Get Rid of the HTML block for Not Product Specific stuff.
			$t->discard_block("origPage","CustomerWorthAllProducts");
			
			
			$t->set_var("PRODUCT_NAME", WebUtil::htmlOutput(Product::getFullProductName($dbCmd2, $limitToProductID)));
			
			
			// Show totals of customers who bought a certain product on their first order... but mixed with other products.
			$t->set_var("TOTAL_CUSTOMER_SPEND_MIXED", '$' .  number_format($mixedProductCustomerSpend, 2));
			$t->set_var("ESTIMATED_PROFIT_MIXED", '$' . number_format($mixedProductCustomerProfit, 2));

			$t->set_var("TOTAL_CUSTOMER_SPEND_MIXED_PRODUCT_SPECIFIC", '$' .  number_format($mixedProductExclusiveCustomerSpend, 2));
			$t->set_var("ESTIMATED_PROFIT_MIXED_PRODUCT_SPECIFIC", '$' . number_format($mixedProductExclusiveCustomerProfit, 2));
			
			
			// Show totals of customers who bought a certain product exclusively on their first order.
			$t->set_var("TOTAL_CUSTOMER_SPEND_SOLO", '$' .  number_format($soloProductCustomerSpend, 2));
			$t->set_var("ESTIMATED_PROFIT_SOLO", '$' . number_format($soloProductCustomerProfit, 2));

			$t->set_var("TOTAL_CUSTOMER_SPEND_SOLO_PRODUCT_SPECIFIC", '$' .  number_format($soloProductExclusiveCustomerSpend, 2));
			$t->set_var("ESTIMATED_PROFIT_SOLO_PRODUCT_SPECIFIC", '$' . number_format($soloProductExclusiveCustomerProfit, 2));
			
			
			// Show combined totals of Mixed and Solo purchased on the first order.
			$t->set_var("TOTAL_CUSTOMER_SPEND_COMBINED", '$' .  number_format($soloProductCustomerSpend + $mixedProductCustomerSpend, 2));
			$t->set_var("ESTIMATED_PROFIT_COMBINED", '$' . number_format($soloProductCustomerProfit + $mixedProductCustomerProfit, 2));

			$t->set_var("TOTAL_CUSTOMER_SPEND_COMBINED_PRODUCT_SPECIFIC", '$' .  number_format($soloProductExclusiveCustomerSpend + $mixedProductExclusiveCustomerSpend, 2));
			$t->set_var("ESTIMATED_PROFIT_COMBINED_PRODUCT_SPECIFIC", '$' . number_format($soloProductExclusiveCustomerProfit + $mixedProductExclusiveCustomerProfit, 2));
			
			
			// Show Customer Counts
			$t->set_var("NEW_CUSTOMER_COUNT_SOLO_PRODUCT", sizeof($firstTimeCustIDproductSoloArr));
			$t->set_var("NEW_CUSTOMER_COUNT_MIXED_PRODUCT", sizeof($firstTimeCustIDproductMixedArr));
			$t->set_var("NEW_CUSTOMER_COUNT_COMBINED", sizeof($firstTimeCustIDproductSoloArr) + sizeof($firstTimeCustIDproductMixedArr));
			

			// In case we have any banner clicks... set these variables for Javascript.
			$t->set_var("CUST_WORTH_NEW_CUSTOMER_COUNT_FROM_BANNER", sizeof($firstTimeCustIDproductSoloArr) + sizeof($firstTimeCustIDproductMixedArr));
			$t->set_var("CUST_WORTH_PROFIT_FROM_BANNER", $soloProductExclusiveCustomerProfit + $mixedProductExclusiveCustomerProfit);


			$profitCombined = $soloProductCustomerProfit + $mixedProductCustomerProfit;
			$revenuesCombined = $soloProductCustomerSpend + $mixedProductCustomerSpend;
			$newCustomersCombined = sizeof($firstTimeCustIDproductSoloArr) + sizeof($firstTimeCustIDproductMixedArr);
			
			// Prevent Division by Zeros.
			
			// For the Avereage Customer Worth
			if(sizeof($firstTimeCustIDproductMixedArr) == 0)
				$t->set_var("APPROX_CUSTOMER_WORTH_MIXED_PRODUCT", '$0.00');
			else
				$t->set_var("APPROX_CUSTOMER_WORTH_MIXED_PRODUCT", '$' . number_format(round($mixedProductCustomerProfit/sizeof($firstTimeCustIDproductMixedArr), 2), 2));

			if(sizeof($firstTimeCustIDproductSoloArr) == 0){
				$t->set_var("APPROX_CUSTOMER_WORTH_SOLO_PRODUCT", '$0.00');
				$t->set_var("APPROX_CUSTOMER_WORTH_SOLO_PRODUCT_SPECIFIC", '$0.00');
			}
			else{
				$t->set_var("APPROX_CUSTOMER_WORTH_SOLO_PRODUCT", '$' . number_format(round($soloProductCustomerProfit/sizeof($firstTimeCustIDproductSoloArr), 2), 2));
				$t->set_var("APPROX_CUSTOMER_WORTH_SOLO_PRODUCT_SPECIFIC", '$' . number_format(round($soloProductExclusiveCustomerProfit/sizeof($firstTimeCustIDproductSoloArr), 2), 2));

			}
			
			if($newCustomersCombined == 0)
				$t->set_var("APPROX_CUSTOMER_WORTH_COMBINED_PRODUCT", '$0.00');
			else
				$t->set_var("APPROX_CUSTOMER_WORTH_COMBINED_PRODUCT", '$' . number_format(round($profitCombined/$newCustomersCombined, 2), 2));


			// For the average Customer Revenue
			if(sizeof($firstTimeCustIDproductMixedArr) == 0)
				$t->set_var("AVERAGE_CUSTOMER_REVENUE_MIXED_PRODUCT", '$0.00');
			else
				$t->set_var("AVERAGE_CUSTOMER_REVENUE_MIXED_PRODUCT", '$' . number_format(round($mixedProductCustomerSpend/sizeof($firstTimeCustIDproductMixedArr), 2), 2));


			if(sizeof($firstTimeCustIDproductSoloArr) == 0){
				$t->set_var("AVERAGE_CUSTOMER_REVENUE_SOLO_PRODUCT", '$0.00');
				$t->set_var("AVERAGE_CUSTOMER_REVENUE_SOLO_PRODUCT_SPECIFIC", '$0.00');
			}
			else{
				$t->set_var("AVERAGE_CUSTOMER_REVENUE_SOLO_PRODUCT", '$' . number_format(round($soloProductCustomerSpend/sizeof($firstTimeCustIDproductSoloArr), 2), 2));
				$t->set_var("AVERAGE_CUSTOMER_REVENUE_SOLO_PRODUCT_SPECIFIC", '$' . number_format(round($soloProductExclusiveCustomerSpend/sizeof($firstTimeCustIDproductSoloArr), 2), 2));

			}
			
			if($newCustomersCombined == 0)
				$t->set_var("AVERAGE_CUSTOMER_REVENUE_COMBINED_PRODUCT", '$0.00');
			else
				$t->set_var("AVERAGE_CUSTOMER_REVENUE_COMBINED_PRODUCT", '$' . number_format(round($revenuesCombined/$newCustomersCombined, 2), 2));


			
			// If there are Zero counts then hide the blocks for each group
			if(sizeof($firstTimeCustIDproductSoloArr) == 0){
				$t->set_block("origPage","ExclusiveProductBL","ExclusiveProductBLout");
				$t->set_var("ExclusiveProductBLout", "No customers during this period with matching search criteria.");
			}
			if(sizeof($firstTimeCustIDproductMixedArr) == 0){
				$t->set_block("origPage","MixedProductBL","MixedProductBLout");
				$t->set_var("MixedProductBLout", "No customers during this period with matching search criteria.");
			}

			
			if(sizeof($firstTimeCustIDproductMixedArr) == 0 && sizeof($firstTimeCustIDproductSoloArr) == 0){
				$t->set_block("origPage","CustomerWorthHideBL","CustomerWorthHideBLout");
				$t->set_var("CustomerWorthHideBLout", "<br><br><font class='error'>There were no orders matching your search criteria..</font>");
			}
			

		}
	}
}




$productIDQuery = "";
if($limitToProductID != 0)
	$productIDQuery = " AND ProductID=$limitToProductID ";


// Find out the total number of Projects that were ordered.. reprints don't count 
$dbCmd->Query("SELECT COUNT(*) FROM projectsordered INNER JOIN orders on projectsordered.OrderID = orders.ID WHERE 
		".DbHelper::getOrClauseFromArray("orders.DomainID", $domainObj->getSelectedDomainIDs())." AND 
		(orders.DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ")
			AND projectsordered.RePrintLink=0 AND Status !='C' " . $productIDQuery);
$TotalProjects = $dbCmd->GetValue();



$AverageSubtotal = 0;
$AverageProfit = 0;
$TotalBannerClicks = 0;
$FirstTimeCustomerCount = 0;
$repeat_order_percent = 0;
$nomadCustomers = 0;
$NewAccountsTotal = 0;
$DeadAccountsTotal = 0;
$SameDateAccountOrders = 0;
$DelayedAccountPurcahses = 0;
$RepeatCustomerCount = 0;
$RepeatCustomerPercentage = 0;
$FirstTimeOrderCounter = 0;
$TotalCustomerCount = 0;
$RepeatOrderCounter = 0;
$ProjectDiscounts = 0;
$TotalOrders = 0;
$RePrintOrderCounter = 0;
$RePrintProjectCounter = 0;
$TotalProjectsSubtotals = 0;
$TotalProjectsSubtotalNoComma = 0;
$TotalProfits = 0;
$TotalProfitsNoComma = 0;
$TotalSalesCommissions = 0;
$FirstTimeCustomerBannerCount = 0;


$totalVendorAdjustments = 0;
$totalCustomerAdjustments = 0;
$totalAdjustmentsProfit = 0;


$ReOrderPeriodHash = array();
$ReOrderPeriodHash["1 Month"] = 0;
$ReOrderPeriodHash["2 Months"] = 0;
$ReOrderPeriodHash["3 Months"] = 0;
$ReOrderPeriodHash["4 Months"] = 0;
$ReOrderPeriodHash["5 Months"] = 0;
$ReOrderPeriodHash["6 Months"] = 0;
$ReOrderPeriodHash["6 Months - 1 Year"] = 0;
$ReOrderPeriodHash["Greather than 1 Year"] = 0;
$numberOfSecondsInMonth = 2592000;  //For calculating differences in unix timesamps

$FirstTimeCustomerTotals = 0;
$FirstTimeCustomerProfits = 0;
$RepeatCustomerTotals = 0;
$RepeatCustomerProfits = 0;

$FirstTimeSalesRepCustomersCount = 0;
$TotalSalesRepCustomersCount = 0;
$FirstTimeCouponCustomersCount = 0;

$Demog_States_table = "No orders yet.";
$Shipping_methods_table = "No orders yet.";
$reorder_reason_table = "No orders yet.";
$customer_assistance_table = "No orders with Customer Assistance during this period.";
$ReOrderPeriod_table = "No Re-Orders";
$TimeUntilFirstOrder_table = "";




// For the Order Report
if($TotalProjects <> 0 && $view == "orders"){

	// Get total orders this period Be sure to skip over any orders which are reprints... or that are canceled

	$dbCmd->Query("SELECT projectsordered.OrderID FROM projectsordered INNER JOIN orders on projectsordered.OrderID = orders.ID  WHERE 
			".DbHelper::getOrClauseFromArray("orders.DomainID", $domainObj->getSelectedDomainIDs())." AND 
			(orders.DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ")
			AND projectsordered.RePrintLink=0 AND Status !='C' ". $productIDQuery . " GROUP BY projectsordered.OrderID ");
	$TotalOrders = 0;
	$LastOrderID = 0;
	while($ThisOrderID = $dbCmd->GetValue()){
		if($ThisOrderID <> $LastOrderID){
			$LastOrderID = $LastOrderID;
			$TotalOrders++;
		}
	}


	// Get the sum of all of the subtotals for all of the projects 
	$dbCmd->Query("SELECT SUM(projectsordered.CustomerSubtotal) AS SUMCUSTOMER, 
			SUM(VendorSubtotal1) AS V1, SUM(VendorSubtotal2) AS V2, SUM(VendorSubtotal3) AS V3, SUM(VendorSubtotal4) AS V4, SUM(VendorSubtotal5) AS V5, SUM(VendorSubtotal6) AS V6, 
			SUM(ROUND(projectsordered.CustomerSubtotal * projectsordered.CustomerDiscount, 2)) AS SUMCUSTDISC 
			FROM projectsordered INNER JOIN orders on projectsordered.OrderID = orders.ID WHERE 
			".DbHelper::getOrClauseFromArray("orders.DomainID", $domainObj->getSelectedDomainIDs())." AND 
			(orders.DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ")
			AND projectsordered.RePrintLink=0 AND Status !='C' " . $productIDQuery);
	$row=$dbCmd->GetRow();
	
	$ProjectsSubtotal = $row["SUMCUSTOMER"];
	$VendorSubtotal = $row["V1"] + $row["V2"] + $row["V3"] + $row["V4"] + $row["V5"] + $row["V6"];
	$ProjectDiscounts = $row["SUMCUSTDISC"];

	$ProjectsSubtotal -= $ProjectDiscounts;

	$AverageSubtotal = '$' . number_format(($ProjectsSubtotal/$TotalProjects), 2);
	

	$TotalProjectsSubtotals = '$' . number_format($ProjectsSubtotal, 2);
	$TotalProjectsSubtotalNoComma = $ProjectsSubtotal;
	
	$TotalProfitsNoComma = number_format(($ProjectsSubtotal - $VendorSubtotal), 2, '.', '');
	$TotalProfits = number_format($TotalProfitsNoComma, 2);

	$AverageProfit = '$' . number_format((($ProjectsSubtotal - $VendorSubtotal)/$TotalProjects), 2);


	// Find out the total number of Banner Clicks
	$dbCmd->Query("SELECT COUNT(*) from bannerlog USE INDEX (bannerlog_Date) WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." AND 
				Date BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp);
	$TotalBannerClicks = $dbCmd->GetValue();
	

	// Find out how much sales commissions are costing us.
	// Sales commissions are Domain-Specific, so we have to get the total in a loop based upon our selected Domain IDs.
	$TotalSalesCommissions = 0;
	
	foreach($domainObj->getSelectedDomainIDs() as $thisDomainID){
		$salesCommissionsObj = new SalesCommissions($dbCmd, $thisDomainID);
		$salesCommissionsObj->SetUser(0);
		$salesCommissionsObj->SetDateRangeByTimeStamp($start_timestamp, $end_timestamp);
		$TotalSalesCommissions += $salesCommissionsObj->GetTotalCommissionsWithinPeriodForUser(0, "All", "GoodAndSuspended");
	}
	
	
	// Prevent Browser Time out.
	print " ";
	Constants::FlushBufferOutput();

	// We want to find out how many repeat customers we have this month
	// MYSQL doesn't support the command INTERSECT, so we have to do the interesection within PHP
	// Get a unique list a User ID's from the orders table before the start date
	$dbCmd->Query("SELECT DISTINCT UserID FROM orders 
			WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." AND
		DateOrdered < " . $start_mysql_timestamp);

	$i=0;
	$AllUserIDsBeforeStartDate = array();
	while($thisUserID = $dbCmd->GetValue()){
		$AllUserIDsBeforeStartDate[$i] = $thisUserID;
		$i++;
	}

	// Get a unique list a User ID's during this period
	$dbCmd->Query("Select DISTINCT UserID FROM orders WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." AND 
			DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp);

	$i=0;
	$AllUserIDsDuringThisPeriod = array();
	while($thisUserID = $dbCmd->GetValue()){
		$AllUserIDsDuringThisPeriod[$i] = $thisUserID;
		$i++;
	}


	// Get the total cost/profits of doing reprints
	$ReprintTotals = 0;
	$ReprintProffit = 0;
	$RePrintOrderCounter = 0;  // We contain the number or orders...not projects
	

	$dbCmd->Query("SELECT projectsordered.CustomerSubtotal, projectsordered.CustomerDiscount, 
			VendorSubtotal1 AS V1, VendorSubtotal2 AS V2, VendorSubtotal3 AS V3, VendorSubtotal4 AS V4, VendorSubtotal5 AS V5, VendorSubtotal6 AS V6, 
			orders.ID FROM projectsordered INNER JOIN orders ON projectsordered.OrderID = orders.ID WHERE 
			".DbHelper::getOrClauseFromArray("orders.DomainID", $domainObj->getSelectedDomainIDs())." AND
			projectsordered.RePrintLink != 0  AND Status !='C' " . $productIDQuery . " AND
			orders.DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . " ORDER BY orders.ID ASC");



	$LastRePrintOrderNumber = 0;
	while($row = $dbCmd->GetRow()){
		$custTot = $row["CustomerSubtotal"];
		$custDisc = $row["CustomerDiscount"];
		$vendTot = $row["V1"] + $row["V2"] + $row["V3"] + $row["V4"] + $row["V5"] + $row["V6"];
		$thisOrderID = $row["ID"];
		
		$RePrintProjectCounter++;
		
		//make sure that each order is counted only once... regardless of how many projects there are
		if($thisOrderID <> $LastRePrintOrderNumber){
			$RePrintOrderCounter++;
			$LastRePrintOrderNumber = $thisOrderID;
		}

		$ReprintCustomerTotal = $custTot - round($custTot * $custDisc, 2);
		$ReprintTotals += $ReprintCustomerTotal;
		$ReprintProffit += $ReprintCustomerTotal - $vendTot;
	}



	// Get a list a unique list User ID's from the orders table that also have a record in the banner log beteween the start and end date 
	$dbCmd->Query("SELECT DISTINCT UserID FROM orders WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
					AND Referral !='' AND DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp);
	$i=0;
	$BannerLogUserIDArr = array();
	while($thisUserID = $dbCmd->GetValue()){
		$BannerLogUserIDArr[$i] = $thisUserID;
		$i++;
	}
	


	
	
	// Get a unique list of User ID's who belong to sales reps during this period.
	$dbCmd->Query("SELECT DISTINCT orders.UserID FROM orders INNER JOIN users on orders.UserID = users.ID 
			WHERE ".DbHelper::getOrClauseFromArray("users.DomainID", $domainObj->getSelectedDomainIDs())." 
			AND users.SalesRep != 0 AND orders.DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp);
	$i=0;
	$SalesRepUserIDArr = array();
	while($thisUserID = $dbCmd->GetValue()){
		$SalesRepUserIDArr[$i] = $thisUserID;
		$i++;
	}




	// Get a unique list of User ID's who used a coupon during this period.
	$dbCmd->Query("SELECT DISTINCT UserID FROM orders WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
			AND CouponID !=0 AND DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp);
	$i=0;
	$UserIDwithCouponsArr = array();
	while($thisUserID = $dbCmd->GetValue()){
		$UserIDwithCouponsArr[$i] = $thisUserID;
		$i++;
	}
	
	// Filter the list of users ID's who have used coupons during the period.  
	// We want a list of users in this period who used become first-time customers with their coupon usage.
	// Also, the coupon can not belong to a Sales Rep.
	$UserIDwithCouponsFirstOrderArr = array();
	foreach($UserIDwithCouponsArr as $thisUserID){
		
		
		// Skip Customer IDs who have placed orders before this report date.
		$dbCmd->Query("SELECT COUNT(*) FROM orders WHERE UserID=$thisUserID AND DateOrdered < $start_mysql_timestamp");
		if($dbCmd->GetValue()!= 0)
			continue;
		
		// Grab the first Coupon ID from the first order in this period.
		$dbCmd->Query("SELECT CouponID FROM orders WHERE UserID=$thisUserID AND 
						(DateOrdered BETWEEN " . $start_mysql_timestamp . " 
						AND " . $end_mysql_timestamp . ")  ORDER BY ID ASC LIMIT 1");
		
		$couponIDofFirstOrder = $dbCmd->GetValue(); 	
		
		// Will be zero if they didn't use a coupon on their first order.
		if(empty($couponIDofFirstOrder))
			continue;
			
		// Make sure that the Coupon doesn't belong to a sales rep.
		$dbCmd->Query("SELECT SalesLink FROM coupons WHERE ID=$couponIDofFirstOrder");
		$salesLinkOfCoupon = $dbCmd->GetValue();
		
		// If if first coupon code that the customer used has a Sales Link on it... then we should skip.
		if(!empty($salesLinkOfCoupon))
			continue;

		// Add to our list of peole who used a coupon on their first order.
		$UserIDwithCouponsFirstOrderArr[] = $thisUserID;
	}
	

	// Get a total of new accounts during this period
	$dbCmd->Query("SELECT ID FROM users WHERE 
			".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." AND 
			DateCreated BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp);
	$userIDsCreatedInPeriod = $dbCmd->GetValueArr();
	$NewAccountsTotal = sizeof($userIDsCreatedInPeriod);
	
	
	foreach($userIDsCreatedInPeriod as $thisNewUserInPeriod){
		
		$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateOrdered) FROM orders WHERE UserID=".intval($thisNewUserInPeriod) . " ORDER BY ID ASC LIMIT 1");	
		if($dbCmd->GetNumRows() == 0){
			$DeadAccountsTotal++;
		}
		else{
			$dateFirstOrdered = $dbCmd->GetValue();
			
			$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateCreated) FROM users WHERE ID=".intval($thisNewUserInPeriod));	
			$dateCreatedForUser = $dbCmd->GetValue();
			
			$firstOrderDiff = $dateFirstOrdered - $dateCreatedForUser;
			if($firstOrderDiff < (60*60*24)){
				// Just because the order was placed within 24 hours doesn't mean that they ordered it the same day.
				// Check if the week day names match
				if(date("D", $dateCreatedForUser) == date("D", $dateFirstOrdered))
					$SameDateAccountOrders++;
			}
			
			// Find out the timestamp at midnight.
			$timeStampOfMidnightAfterAccountCreation = mktime(0,0,0,date("n", $dateCreatedForUser), (date("j", $dateCreatedForUser) + 1), date("Y", $dateCreatedForUser));
			if($dateFirstOrdered > $timeStampOfMidnightAfterAccountCreation)
				$DelayedAccountPurcahses++;
		}
	}
	

	
	
	// Now we want to get all Order totals from repeat customers and new customers
	$FirstTimeOrderCounter = 0;
	$RepeatOrderCounter = 0;
	$preventTimeoutCounter = 0;
	foreach($AllUserIDsDuringThisPeriod as $ThisUserID){

		// Now loop through all orders from the user.. regardless of when the date the order was placed.
		$dbCmd->Query("SELECT ID, UNIX_TIMESTAMP(DateOrdered) AS DateOrdered FROM orders WHERE UserID=$ThisUserID 
				ORDER BY DateOrdered ASC");
		
		// Since we are looping through all orders from this user, we want count each order... anything over 1 is considered a repeat customer
		// Additionally, we are going to check whether the "DateOrdered" is within range of our report. 
		$CustomerOrderCounter = 0;
		$RepeatCustomerHasBeenCounted = false;
		while($row = $dbCmd->GetRow()){

			$ThisOrderID = $row["ID"];
			$ThisDateOrdered = $row["DateOrdered"];
			
			$CustomerOrderCounter++;
			
			$preventTimeoutCounter++;
			
			
			if($preventTimeoutCounter > 1000){
				print " ";
				Constants::FlushBufferOutput();
				$preventTimeoutCounter = 0;
			}

			// Make sure the order date is within range of the report
			// Also make sure that the order has not been canceled
			if($ThisDateOrdered >= $start_timestamp && $ThisDateOrdered <= $end_timestamp && Order::CheckForActiveProjectWithinOrder($dbCmd2, $ThisOrderID)){

				// We want to count every repeat customer within the period... but only once
				if($CustomerOrderCounter >= 2 && !$RepeatCustomerHasBeenCounted){
	
					// Also, we don't consider a customer to be a repeat if the order has any reprints inside 
					if(Order::GetCountOfReprintsInOrder($dbCmd2, $ThisOrderID) == 0){
						$RepeatCustomerCount++;
						$RepeatCustomerHasBeenCounted = true;
					}
				}

				#-- This will part might take a little while --#
				$thisCustomerTotal = Order::GetTotalFromOrder($dbCmd2, $ThisOrderID, "customersubtotal");
				$thisCustomerDiscount = Order::GetTotalFromOrder($dbCmd2, $ThisOrderID, "customerdiscount");
				$thisVendorTotal = Order::GetTotalFromOrder($dbCmd2, $ThisOrderID, "vendorsubtotal");
			
				if($CustomerOrderCounter == 1){
					$FirstTimeCustomerTotals += $thisCustomerTotal;
					$FirstTimeCustomerProfits += $thisCustomerTotal - $thisCustomerDiscount - $thisVendorTotal;
					$FirstTimeOrderCounter++;
					
					$LastDateOrdered = $ThisDateOrdered;
				}

				else{
					// These orders will contain Re-Prints... we need to filter out the figures below
					$RepeatCustomerTotals += $thisCustomerTotal - $thisCustomerDiscount;
					$RepeatCustomerProfits += $thisCustomerTotal - $thisCustomerDiscount - $thisVendorTotal;
					$RepeatOrderCounter++;
					
					// Since it is a repeat order... we want to figure out the frequency between each reorder 
					// Make sure not to count orders with reprints
					if(Order::GetCountOfReprintsInOrder($dbCmd2, $ThisOrderID) == 0){
					
						if($ThisDateOrdered - $LastDateOrdered < $numberOfSecondsInMonth){
							$ReOrderPeriodHash["1 Month"]++;
						}
						else if($ThisDateOrdered - $LastDateOrdered < $numberOfSecondsInMonth * 2){
							$ReOrderPeriodHash["2 Months"]++;
						}
						else if($ThisDateOrdered - $LastDateOrdered < $numberOfSecondsInMonth * 3){
							$ReOrderPeriodHash["3 Months"]++;
						}
						else if($ThisDateOrdered - $LastDateOrdered < $numberOfSecondsInMonth * 4){
							$ReOrderPeriodHash["4 Months"]++;
						}
						else if($ThisDateOrdered - $LastDateOrdered < $numberOfSecondsInMonth * 5){
							$ReOrderPeriodHash["5 Months"]++;
						}
						else if($ThisDateOrdered - $LastDateOrdered < $numberOfSecondsInMonth * 6){
							$ReOrderPeriodHash["6 Months"]++;
						}
						else if($ThisDateOrdered - $LastDateOrdered < $numberOfSecondsInMonth * 12){
							$ReOrderPeriodHash["6 Months - 1 Year"]++;
						}
						else if($ThisDateOrdered - $LastDateOrdered >= $numberOfSecondsInMonth * 12){
							$ReOrderPeriodHash["Greather than 1 Year"]++;
						}
					}
				}
			}
			
			$LastDateOrdered = $ThisDateOrdered;

		}
	}


	// No we can take away the Total customer reprints and profits from our "RepeatCustomerTotals"
	// The reason is that the "RepeatCustomerTotals" is poluted with reprints
	$RepeatCustomerTotals -= $ReprintTotals;
	$RepeatCustomerProfits -= $ReprintProffit;
	$RepeatOrderCounter -= $RePrintOrderCounter;


	// This will return us an array with a unique list of User ID's that placed orders this period for the first time.
	$FirstTimeCustomerIDs = array_diff($AllUserIDsDuringThisPeriod, $AllUserIDsBeforeStartDate);

	$FirstTimeCustomerCount = count($FirstTimeCustomerIDs);
	
	$TotalCustomerCount = count($AllUserIDsDuringThisPeriod);
	
	$TotalSalesRepCustomersCount = count($SalesRepUserIDArr);
	
	$FirstTimeCouponCustomersCount = count($UserIDwithCouponsFirstOrderArr);
	
	$FirstTimeSalesRepCustomersIDs = array_intersect($FirstTimeCustomerIDs, $SalesRepUserIDArr);
	
	$FirstTimeSalesRepCustomersCount = count($FirstTimeSalesRepCustomersIDs);
	
	$FirstTimeCustomerWithBannerIDs = array_intersect($FirstTimeCustomerIDs, $BannerLogUserIDArr);
	
	$FirstTimeCustomerBannerCount = sizeof(array_diff($FirstTimeCustomerWithBannerIDs, $FirstTimeSalesRepCustomersIDs));
	
	
	if($TotalCustomerCount > 0)
		$RepeatCustomerPercentage = round($RepeatCustomerCount / $TotalCustomerCount * 100);
	else
		$RepeatCustomerPercentage = 0;
	
	

	// percentage of orders coming from repeat customers
	$repeat_order_percent = (number_format($RepeatOrderCounter/$TotalOrders,2)) * 100;

	// Find out the number of nomad customers... Users which have placed order that don't have a record in the banner log, and did not use a coupon, and do not belong to a Sales Rep.
	$nomadCustomers = sizeof(array_diff($FirstTimeCustomerIDs, $BannerLogUserIDArr, $UserIDwithCouponsFirstOrderArr, $FirstTimeSalesRepCustomersIDs));


	// Fill up the re-order period hash
	$ReOrderPeriod_table = "<table cellpadding='2' cellspacing='0' border='1' width='220'><tr><td class='body' colspan='2' bgcolor='#EEEEEE'>Reorders Within Each Period</td></tr>";
	foreach($ReOrderPeriodHash as $PeriodDesc => $PeriodAmount){	
		$ReOrderPeriod_table .= "<tr><td class='SmallBody'>" . WebUtil::htmlOutput($PeriodDesc) . "</td><td class='SmallBody'>".WebUtil::htmlOutput($PeriodAmount)."</td></tr>";
	}
	$ReOrderPeriod_table .= "</table>";
	
	
	




	// Don't run this report unless the end date is greater than 20 days in the past.  So it will show up when you run a report for "last month"
	$runfirstTimeAndRepeatCustomersFlag = false;
	if($end_timestamp < time() - 60 * 60 * 24 * 20){
		
		$runfirstTimeAndRepeatCustomersFlag = true;
			
		// Will hold a record of First-Time Customers (during this period) who became repeat customers
		$firstTimeRepeatCustomersArr = array();
		$firstTimeRpCustOrderCountArr = array();
		$firstTimeRpUserIDArr = array();

		$firstTimeRepeatPeriods = array("3 days", "1 Week", "2 Weeks", "1 Month", "2 Months", "3 Months", "4 Months", "5 Months", "6 Months", "6 Months - 1 Year", "Greather than 1 Year");
	
		
		foreach($firstTimeRepeatPeriods as $thisPeriod){
			$firstTimeRpUserIDArr[$thisPeriod] = array();
			$firstTimeRpCustOrderCountArr[$thisPeriod] = 0;
			$firstTimeRepeatCustomersArr[$thisPeriod] = 0;
		}

	}
	
	
	foreach($FirstTimeCustomerIDs as $thisUserID){
	
		if(!$runfirstTimeAndRepeatCustomersFlag)
			break;
	

		// Find out if any of the First Time Customers placed re-orders.	
		$dbCmd->Query("SELECT orders.ID FROM orders INNER JOIN projectsordered ON projectsordered.OrderID = orders.ID 
				WHERE orders.UserID = $thisUserID AND projectsordered.RePrintLink = 0 AND projectsordered.Status !='C' 
				AND orders.DateOrdered > " . $start_mysql_timestamp . " ORDER BY orders.ID ASC");

		$uniqueOrderIDs = array();
		while($o = $dbCmd->GetValue())
			$uniqueOrderIDs[] = $o;


		$uniqueOrderIDs = array_unique($uniqueOrderIDs);

		$numberOfReOrders = sizeof($uniqueOrderIDs) - 1;


		if($numberOfReOrders >= 1){

			// array_unique does not collapse the index numbers... so it is not possible to get the first repeat order number by simply doing $uniqueOrderIDs[1];
			$tmpArr = $uniqueOrderIDs;
			$uniqueOrderIDs = array();
			foreach($tmpArr as $x)
				$uniqueOrderIDs[] = $x;


			// Now find out how long it took them to place their first re-order... and subsequently how many orders they placed.
			$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateOrdered) FROM orders WHERE ID = " . $uniqueOrderIDs[1]);
			$firstReOrderTimeStamp = $dbCmd->GetValue();

			// Now increment the arrays based upon how long it the repeat customers to orders (From Day 1) to the end... and how many re-orders did they place totally within that period.
			if($firstReOrderTimeStamp - $start_timestamp < 60 * 60 * 24 * 3){
				$firstTimeRepeatCustomersArr["3 days"]++;
				$firstTimeRpCustOrderCountArr["3 days"] += $numberOfReOrders;
				$firstTimeRpUserIDArr["3 days"][] = $thisUserID;
			}
			else if($firstReOrderTimeStamp - $start_timestamp < 60 * 60 * 24 * 7){
				$firstTimeRepeatCustomersArr["1 Week"]++;
				$firstTimeRpCustOrderCountArr["1 Week"] += $numberOfReOrders;
				$firstTimeRpUserIDArr["1 Week"][] = $thisUserID;
			}
			else if($firstReOrderTimeStamp - $start_timestamp < $numberOfSecondsInMonth/2){
				$firstTimeRepeatCustomersArr["2 Weeks"]++;
				$firstTimeRpCustOrderCountArr["2 Weeks"] += $numberOfReOrders;
				$firstTimeRpUserIDArr["2 Weeks"][] = $thisUserID;
			}
			else if($firstReOrderTimeStamp - $start_timestamp < $numberOfSecondsInMonth){
				$firstTimeRepeatCustomersArr["1 Month"]++;
				$firstTimeRpCustOrderCountArr["1 Month"] += $numberOfReOrders;
				$firstTimeRpUserIDArr["1 Month"][] = $thisUserID;
			}
			else if($firstReOrderTimeStamp - $start_timestamp < $numberOfSecondsInMonth * 2){
				$firstTimeRepeatCustomersArr["2 Months"]++;
				$firstTimeRpCustOrderCountArr["2 Months"] += $numberOfReOrders;
				$firstTimeRpUserIDArr["2 Months"][] = $thisUserID;
			}
			else if($firstReOrderTimeStamp - $start_timestamp < $numberOfSecondsInMonth * 3){
				$firstTimeRepeatCustomersArr["3 Months"]++;
				$firstTimeRpCustOrderCountArr["3 Months"] += $numberOfReOrders;
				$firstTimeRpUserIDArr["3 Months"][] = $thisUserID;
			}
			else if($firstReOrderTimeStamp - $start_timestamp < $numberOfSecondsInMonth * 4){
				$firstTimeRepeatCustomersArr["4 Months"]++;
				$firstTimeRpCustOrderCountArr["4 Months"] += $numberOfReOrders;
				$firstTimeRpUserIDArr["4 Months"][] = $thisUserID;
			}
			else if($firstReOrderTimeStamp - $start_timestamp < $numberOfSecondsInMonth * 5){
				$firstTimeRepeatCustomersArr["5 Months"]++;
				$firstTimeRpCustOrderCountArr["5 Months"] += $numberOfReOrders;
				$firstTimeRpUserIDArr["5 Months"][] = $thisUserID;
			}
			else if($firstReOrderTimeStamp - $start_timestamp < $numberOfSecondsInMonth * 6){
				$firstTimeRepeatCustomersArr["6 Months"]++;
				$firstTimeRpCustOrderCountArr["6 Months"] += $numberOfReOrders;
				$firstTimeRpUserIDArr["6 Months"][] = $thisUserID;
			}
			else if($firstReOrderTimeStamp - $start_timestamp < $numberOfSecondsInMonth * 12){
				$firstTimeRepeatCustomersArr["6 Months - 1 Year"]++;
				$firstTimeRpCustOrderCountArr["6 Months - 1 Year"] += $numberOfReOrders;
				$firstTimeRpUserIDArr["6 Months - 1 Year"][] = $thisUserID;
			}
			else if($firstReOrderTimeStamp - $start_timestamp >= $numberOfSecondsInMonth * 12){
				$firstTimeRepeatCustomersArr["Greather than 1 Year"]++;
				$firstTimeRpCustOrderCountArr["Greather than 1 Year"] += $numberOfReOrders;
				$firstTimeRpUserIDArr["Greather than 1 Year"][] = $thisUserID;
			}

		}


	}
	



	
	
	
	// Fill up the table that shows how long it takes for New Customers to re-order.
	if(!empty($firstTimeRepeatCustomersArr)){

	

		$TimeUntilFirstOrder_table = "<font class='ReallySmallBody'>IMPORTANT: Caching can affect the results of this table. (looks into the future)<br>'Regenerate' the report to get the most accurate numbers.</font><table cellpadding='3' cellspacing='0' border='1' width='80%'><tr><td class='SmallBody' colspan='3' bgcolor='#EEEEEE'>New Customers During this Period That Became Repeats</td></tr>";

		$TimeUntilFirstOrder_table .= "<tr>";
		$TimeUntilFirstOrder_table .= "<td class='SmallBody'>Time To Repeat</td>";
		$TimeUntilFirstOrder_table .= "<td class='SmallBody'>Customer Count</td>";
		$TimeUntilFirstOrder_table .= "<td class='SmallBody'>Total # Repeat Orders</td>";
		$TimeUntilFirstOrder_table .= "</tr>";

		
		$totalFirstTimeRepeatCustomers = 0;
		foreach($firstTimeRepeatPeriods as $PeriodDesc)
			$totalFirstTimeRepeatCustomers += $firstTimeRepeatCustomersArr[$PeriodDesc];
		
		
		
		foreach($firstTimeRepeatPeriods as $PeriodDesc){
			
			// Prevent division by zero
			if(empty($totalFirstTimeRepeatCustomers))
				$custPercentage = 0;
			else
				$custPercentage = round(($firstTimeRepeatCustomersArr[$PeriodDesc] / $totalFirstTimeRepeatCustomers * 100) ,1);
			
			if($firstTimeRepeatCustomersArr[$PeriodDesc] != 0)
				$repeatOrdersToCustRatio = round($firstTimeRpCustOrderCountArr[$PeriodDesc] / $firstTimeRepeatCustomersArr[$PeriodDesc], 1);
			else
				$repeatOrdersToCustRatio = 0;
			
			$userIDpipedList = "";
			$userPeriodListArr = array_unique($firstTimeRpUserIDArr[$PeriodDesc]);
			foreach($userPeriodListArr as $thisCust)
				$userIDpipedList .= $thisCust . "|";
			
			// Build a Link that will submit the UserID Piped list into a Form... which will in turn make a pop-up window showing the user ID's.
			if($firstTimeRepeatCustomersArr[$PeriodDesc] != 0)
				$userIDCountDesc = "<a href='#' onClick='javascript:document.forms[\"NewAndRepeatCustomerForm\"].userlist.value = \"" . $userIDpipedList ."\";  document.forms[\"NewAndRepeatCustomerForm\"].CustomMessage.value = \"For users who placed their first repeat order within " . $PeriodDesc . "\"; document.forms[\"NewAndRepeatCustomerForm\"].submit();'>" . $firstTimeRepeatCustomersArr[$PeriodDesc] . "</a>";
			else
				$userIDCountDesc = "0";
		
			$TimeUntilFirstOrder_table .= "<tr>";
			$TimeUntilFirstOrder_table .= "<td class='SmallBody'>" . $PeriodDesc . "</td>";
			$TimeUntilFirstOrder_table .= "<td class='SmallBody'>" . $userIDCountDesc . "&nbsp;&nbsp; - &nbsp;(" . $custPercentage . "%)</td>";
			$TimeUntilFirstOrder_table .= "<td class='SmallBody'>" . $firstTimeRpCustOrderCountArr[$PeriodDesc] . "&nbsp;&nbsp; - &nbsp;(<font class='ReallySmallBody'>Strength:</font> " . $repeatOrdersToCustRatio . ")</td>";
			$TimeUntilFirstOrder_table .= "</tr>";
		}
		$TimeUntilFirstOrder_table .= "</table><font color='#cc0000'>* </font><font class='SmallBody'>Column #1 shows how long it took before the first repeat order was placed.</font><br><font color='#cc0000'>* </font><font class='SmallBody'>Column #2 measures total repeat orders from them until present time.</font>";
	}
	



	// Get a list of reprints... and the reasons for it
	$reorder_reason_table = "<table cellpadding='2' cellspacing='0' border='1' width='200'><tr><td class='body' colspan='2'bgcolor='#EEEEEE'>Reprint Reasons</td></tr>";

	
	$dbCmd->Query("SELECT COUNT(RePrintReason) AS REPRINTREASONCOUNT, RePrintReason FROM projectsordered INNER JOIN orders ON projectsordered.OrderID = orders.ID 
			WHERE ".DbHelper::getOrClauseFromArray("orders.DomainID", $domainObj->getSelectedDomainIDs())." 
			AND projectsordered.RePrintLink!=0 AND projectsordered.Status != 'C' " . $productIDQuery . " AND 
			orders.DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . " GROUP BY RePrintReason");

	while($row = $dbCmd->GetRow()){
		$ReprintReasonCount = $row["REPRINTREASONCOUNT"];
		$ReprintReasonChar = $row["RePrintReason"];

		$reorder_reason_table .= "<tr><td class='SmallBody'>" . WebUtil::htmlOutput(Status::GetReprintReasonString($ReprintReasonChar)) . "</td><td class='SmallBody'>$ReprintReasonCount</td></tr>";
	}
	$reorder_reason_table .= "</table>";




	// Get a list of all Shipping Method Types 
	$Shipping_methods_table = "<table cellpadding='2' cellspacing='0' border='1' width='200'><tr><td class='body' colspan='2'bgcolor='#EEEEEE'>Orders by Shipping Method</td></tr>";
	

	$dbCmd->Query("SELECT count(ShippingChoiceID) AS COUNTSHIPPING, ShippingChoiceID FROM orders WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())."
			AND DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . " GROUP BY ShippingChoiceID");

	while($row = $dbCmd->GetRow()){
		$ShippingMethodCount = $row["COUNTSHIPPING"];
		$shippingChoiceID = $row["ShippingChoiceID"];

		$Shipping_methods_table .= "<tr><td class='SmallBody'>" . WebUtil::htmlOutput(ShippingChoices::getChoiceName($shippingChoiceID)) . "</td><td class='SmallBody'>$ShippingMethodCount</td></tr>";
	}
	$Shipping_methods_table .= "</table>";
	
	
	
	// Find how many projects have Had Customer Assistance Charges.	 If you want to find out totals you must set the Option Alias to "Assistance"
	$dbCmd->Query("SELECT OptionsAlias FROM projectsordered INNER JOIN orders on projectsordered.OrderID = orders.ID WHERE 
			".DbHelper::getOrClauseFromArray("orders.DomainID", $domainObj->getSelectedDomainIDs())." AND 
			DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . " AND projectsordered.OptionsAlias LIKE \"%Assistance - %\" AND projectsordered.OptionsAlias NOT LIKE \"%Assistance - HIDE%\" ");
	
	if($dbCmd->GetNumRows() > 0){
	
		// The Key is the Assistance Name... the value is the count.
		$CustomerAssistArr = array();
		
		
		// We are just going to do pattern matching to extract the option names directly from the DB.  It would be too much work to build Product Objects on everything.
		while($thisOptionsDesc = $dbCmd->GetValue()){

			$matches = array();
			if(preg_match("/Assistance \- ((\w|\s)+)/", $thisOptionsDesc, $matches)){
	
				$assistanceName = $matches[1];

				if(!array_key_exists($assistanceName, $CustomerAssistArr))
					$CustomerAssistArr[$assistanceName] = 1;
				else
					$CustomerAssistArr[$assistanceName]++;
			}
		
		}
		
		$customer_assistance_table = "<table cellpadding='2' cellspacing='0' border='1' width='400'><tr><td class='body' colspan='3'bgcolor='#EEEEEE'>Customer Assistance Upgrades</td></tr>";
		$customer_assistance_table .= "<tr><td class='SmallBody'><b>Option Name</b></td><td class='SmallBody'><b>Count</b></td></tr>";

		$totalCustAssistanceOptions = 0;
		
		foreach($CustomerAssistArr as $custAssistOptionName => $custAssistOptionValue){
			
			$totalCustAssistanceOptions += $custAssistOptionValue;
				
			$customer_assistance_table .= "<tr><td class='SmallBody'>" . WebUtil::htmlOutput($custAssistOptionName) . "</td><td class='SmallBody'>" . WebUtil::htmlOutput($custAssistOptionValue) . "</td></tr>";
		}
		
		$customer_assistance_table .= "<tr><td class='SmallBody'><b>Total</b></td><td class='SmallBody'><b>" . $totalCustAssistanceOptions . "</b></td></tr>";
		
		$customer_assistance_table .= "</table>";
	}




	// Figure out a Total of Customer and Vendor Balance Adjustments within the perdiod.
	$dbCmd->Query("SELECT SUM(CustomerAdjustment) as CustSum, SUM(VendorAdjustment) as VendSum 
			FROM balanceadjustments INNER JOIN orders ON orders.ID = balanceadjustments.OrderID WHERE 
			".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." AND (DateCreated BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") ");
	$adjustRow = $dbCmd->GetRow();
	
	$totalCustomerAdjustments = $adjustRow["CustSum"];
	$totalVendorAdjustments = $adjustRow["VendSum"];


	if($totalCustomerAdjustments < 0)
		$totalAdjustmentsProfit = '-$' . number_format(abs(($totalCustomerAdjustments - $totalVendorAdjustments)), 2);
	else
		$totalAdjustmentsProfit = '$' . number_format(abs(($totalCustomerAdjustments - $totalVendorAdjustments)), 2);

	if($totalVendorAdjustments < 0)
		$totalVendorAdjustments = '-$' . number_format(abs($totalVendorAdjustments), 2);
	else
		$totalVendorAdjustments = '$' . number_format(abs($totalVendorAdjustments), 2);

	if($totalCustomerAdjustments < 0)
		$totalCustomerAdjustments = '-$' . number_format(abs($totalCustomerAdjustments), 2);
	else
		$totalCustomerAdjustments = '$' . number_format(abs($totalCustomerAdjustments), 2);


}





// For Demographics
if($TotalProjects <> 0 && $view == "demographics"){

	$StateCountArr = array();
	$TotalStates = 0;

	// Get a list of all States and the amount of orders in each --#

	$dbCmd->Query("SELECT count(ShippingState) AS STATECOUNT, ShippingState FROM orders WHERE 
			".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." AND 
			DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . " GROUP BY ShippingState ORDER BY ShippingState");
	while($row = $dbCmd->GetRow()){
		$StateCount = $row["STATECOUNT"];
		$TheState = $row["ShippingState"];
		
		$TotalStates += $StateCount;
		
		$StateCountArr[$TheState] = intval($StateCount);
	}
	
	
	
	// Sort by the most popular
	asort($StateCountArr);
	$StateCountArr = array_reverse($StateCountArr, true);
	reset($StateCountArr);

	
	$Demog_States_table = "<table cellpadding='2' cellspacing='0' border='1' width='150'><tr><td class='body' colspan='3'bgcolor='#EEEEEE'>Orders by State</td></tr>";
	

	foreach($StateCountArr as $StateName => $ThisStateCount){
	
		if($TotalStates == 0)
			$statePercentage = 0;
		else
			$statePercentage = round($ThisStateCount / $TotalStates, 3) * 100;
		
		$Demog_States_table .= "<tr><td class='SmallBody'>".WebUtil::htmlOutput($StateName)."</td><td class='SmallBody'>$ThisStateCount</td><td class='SmallBody'>".  $statePercentage .  "%</td></tr>";
	}

	$Demog_States_table .= "</table>";
}


if($view == "cancelations"){
	
	$dbCmd->Query("SELECT DISTINCT orders.ID FROM orders INNER JOIN projectsordered on orders.ID = projectsordered.OrderID WHERE 
			projectsordered.Status = 'C' AND 
			".DbHelper::getOrClauseFromArray("orders.DomainID", $domainObj->getSelectedDomainIDs())." AND 
			orders.DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . " ORDER BY ID ASC");
	
	$orderIDcancelationsArr = $dbCmd->GetValueArr();
	
	$t->set_block("origPage","OrdersRowBL","OrdersRowBLout");
	
	$emptyOrders = true;
	
	foreach($orderIDcancelationsArr as $thisOrderID){
		
		// Don't list orders that have an active project inside.  Our list of order ID's just says if there is at least 1 canceled project.  This will make it unanimous.
		$dbCmd->Query("SELECT COUNT(*) FROM projectsordered WHERE OrderID=$thisOrderID AND Status != 'C'");
		$nonCanceledProjects = $dbCmd->GetValue();

		if($nonCanceledProjects > 0)
			continue;
		
		$dbCmd->Query("SELECT ID, UserID, UNIX_TIMESTAMP(DateOrdered) as DateOrdered, FirstTimeCustomer FROM orders WHERE ID = $thisOrderID");
		$row = $dbCmd->GetRow();
		
		$emptyOrders = false;
		
		$orderID = $row["ID"];
		$orderUserID = $row["UserID"];
		$dateOrdered = $row["DateOrdered"];
		$firstTimeCustomer = $row["FirstTimeCustomer"];
		
		// We don't have a method for extracting the order value when status is set to Canceled.
		$dbCmd->Query("SELECT SUM(CustomerSubtotal) FROM projectsordered WHERE OrderID=$thisOrderID");
		$orderValueIfNotCanceled = $dbCmd->GetValue();
		$dbCmd->Query("SELECT ShippingQuote FROM orders WHERE ID=$thisOrderID");
		$orderValueIfNotCanceled += $dbCmd->GetValue();
		
		$t->set_var("ORDER_ID", $orderID);
		$t->set_var("ORDER_HASH", Order::GetHashedOrderNo($orderID));
		$t->set_var("ORDER_VALUE", '$' . number_format($orderValueIfNotCanceled, 2));
		$t->set_var("ORDER_DATE", date("M j, Y - g:i a", $dateOrdered));
		$t->set_var("COMPANY_OR_NAME", WebUtil::htmlOutput(UserControl::GetCompanyOrNameByUserID($dbCmd2, $orderUserID)));
		
		if($firstTimeCustomer == "Y"){
			$t->set_var("ROW_BOLD_START", "<b>");
			$t->set_var("ROW_BOLD_END", "</b>");
		}
		else{
			$t->set_var("ROW_BOLD_START", "");
			$t->set_var("ROW_BOLD_END", "");
		}
		
		$t->allowVariableToContainBrackets("ROW_BOLD_START");
		$t->allowVariableToContainBrackets("ROW_BOLD_END");
		
		$t->parse("OrdersRowBLout","OrdersRowBL",true);
	}
	
	if($emptyOrders){
		$t->set_block("origPage","EmptyOrderCancelationsBL","EmptyOrderCancelationsBLout");
		$t->set_var("EmptyOrderCancelationsBLout", "No cancelations within this period.");
	
	}
}








if($view == "orders"){
	
	$loyaltyCharges = 0;
	$loyaltyRefunds = 0;
	$loyaltyBalance = 0;
	
	if($AuthObj->CheckForPermission("LOYALTY_REVENUES")){
		$dbCmd->Query("SELECT SUM(ChargeAmount) AS LoyaltyCharges, SUM(RefundAmount) AS LoyaltyRefunds FROM loyaltycharges WHERE 
						" . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()) . "  AND 
						Date BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp);
		$loyaltyRow = $dbCmd->GetRow();
		$loyaltyCharges = $loyaltyRow["LoyaltyCharges"];
		$loyaltyRefunds = $loyaltyRow["LoyaltyRefunds"];
		$loyaltyBalance = $loyaltyCharges - $loyaltyRefunds;
	}
	else{
		$t->discard_block("origPage", "LoyaltyChargesBL");
	}
		
	
	// Don't show the loyalty charges if there aren't any charges.
	// We don't want to show some domain owners the revenue fees if they aren't enrolled in the program.
	if(empty($loyaltyCharges)){
		$t->discard_block("origPage", "LoyaltyChargesBL");
	}
	
	
	$t->set_var("LOYALTY_CHARGES_TOTAL", '$' . number_format($loyaltyCharges, 2));
	$t->set_var("LOYALTY_REFUND_TOTALS", '$' . number_format($loyaltyRefunds, 2));
	$t->set_var("LOYALTY_BALANCE_TOTALS", '$' . number_format($loyaltyBalance, 2));
	
	
	
	$totalLoyaltySavings = LoyaltyProgram::getTotalSavingsWithinDateRange($domainObj->getSelectedDomainIDs(), $start_timestamp, $end_timestamp);
	$totalLoyaltyShippingSavings = LoyaltyProgram::getShippingSavingsWithinDateRange($domainObj->getSelectedDomainIDs(), $start_timestamp, $end_timestamp);
	$totalLoyaltySubtotalSavings = LoyaltyProgram::getSubtotalSavingsWithinDateRange($domainObj->getSelectedDomainIDs(), $start_timestamp, $end_timestamp);
	
	$t->set_var("LOYALTY_DISCOUNT_TOTALS", '$' . number_format($totalLoyaltySavings, 2));
	$t->set_var("LOYALTY_DISCOUNTS_SHIPPING", '$' . number_format($totalLoyaltyShippingSavings, 2));
	$t->set_var("LOYALTY_DISCOUNTS_SUBTOTAL", '$' . number_format($totalLoyaltySubtotalSavings, 2));


	$dbCmd->Query("SELECT DISTINCT UserID FROM loyaltycharges WHERE 
					" . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()) . "  AND 
					ChargeAmount > 0 AND
					Date BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp);
	$loyaltyUserIDsCharged = $dbCmd->GetValueArr();

	$dbCmd->Query("SELECT DISTINCT UserID FROM loyaltycharges WHERE 
					" . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()) . "  AND 
					RefundAmount > 0 AND
					Date BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp);
	$loyaltyUserIDsRefunded = $dbCmd->GetValueArr();
	
	$newLoyaltyMembers = 0;
	$repeatLoyaltyMembers = 0;
	
	// Find out the first charge date of the user to know if they are new or repeat.
	foreach($loyaltyUserIDsCharged as $thisUserID){
		
		$dbCmd->Query("SELECT COUNT(*) FROM loyaltycharges WHERE UserID=$thisUserID AND ChargeAmount > 0 AND Date < " . $start_mysql_timestamp);
		if($dbCmd->GetValue() == 0)
			$newLoyaltyMembers++;
		
		$dbCmd->Query("SELECT COUNT(*) FROM loyaltycharges WHERE UserID=$thisUserID AND ChargeAmount > 0 AND Date < " . $end_mysql_timestamp);
		if($dbCmd->GetValue() > 1)
			$repeatLoyaltyMembers++;
	}
	
	$loyaltyCancel_1Day = 0;
	$loyaltyCancel_1Week = 0;
	$loyaltyCancel_1Month = 0;
	$loyaltyCancel_6Month = 0;
	$loyaltyCancel_G6month = 0;
	
	foreach($loyaltyUserIDsRefunded as $thisUserID){
		
		$dbCmd->Query("SELECT UNIX_TIMESTAMP(Date) FROM loyaltycharges WHERE UserID=$thisUserID AND ChargeAmount > 0 ORDER BY ID ASC LIMIT 1");
		$firstChargeDate = $dbCmd->GetValue();
		$dbCmd->Query("SELECT UNIX_TIMESTAMP(Date) FROM loyaltycharges WHERE UserID=$thisUserID AND RefundAmount > 0 ORDER BY ID ASC LIMIT 1");
		$firstRefundDate = $dbCmd->GetValue();	
			
		$timeDiff = $firstRefundDate - $firstChargeDate;
		$dayDiff = $timeDiff / (60 * 60 * 24);
	
		if($dayDiff < 1)
			$loyaltyCancel_1Day++;
		else if($dayDiff < 7)
			$loyaltyCancel_1Week++;
		else if($dayDiff < 31)
			$loyaltyCancel_1Month++;
		else if($dayDiff < 186)
			$loyaltyCancel_6Month++;
		else
			$loyaltyCancel_G6month++;
	}
	
	if($FirstTimeCustomerCount > 0)
		$newLoyaltyMembersDesc = "$newLoyaltyMembers &nbsp;&nbsp;&nbsp;<font class='SmallBody'>(" . round($newLoyaltyMembers / $FirstTimeCustomerCount * 100, 1) . "% of new)</font>";
	else 
		$newLoyaltyMembersDesc = $newLoyaltyMembers;
	
	if($RepeatCustomerCount > 0)
		$repeatLoyaltyMembersDesc = "$repeatLoyaltyMembers &nbsp;&nbsp;&nbsp;<font class='SmallBody'>(" . round($repeatLoyaltyMembers / $RepeatCustomerCount * 100, 1) . "% of repeat)</font>";
	else 
		$repeatLoyaltyMembersDesc = $repeatLoyaltyMembers;
		
	$t->set_var("NEW_LOYALTY_MEMBERS", $newLoyaltyMembersDesc);
	$t->set_var("REPEAT_LOYALTY_MEMBERS", $repeatLoyaltyMembersDesc);
	$t->set_var("CANCELED_LOYALTY_MEMBERS", sizeof($loyaltyUserIDsRefunded));
	$t->set_var("CANCELED_LOYALTY_1DAY", $loyaltyCancel_1Day);
	$t->set_var("CANCELED_LOYALTY_1WEEK", $loyaltyCancel_1Week);
	$t->set_var("CANCELED_LOYALTY_1MONTH", $loyaltyCancel_1Month);
	$t->set_var("CANCELED_LOYALTY_6MONTH", $loyaltyCancel_6Month);
	$t->set_var("CANCELED_LOYALTY_G6MONTH", $loyaltyCancel_G6month);
	
	$t->allowVariableToContainBrackets("NEW_LOYALTY_MEMBERS");
	$t->allowVariableToContainBrackets("REPEAT_LOYALTY_MEMBERS");
	
	
	
	
	
	
	/* ---------------------------------------------------------------------------------------------------------------------------------------------------------- */
	
	$copyrightCharges = 0;
	$copyrightRefunds = 0;
	$copyrightBalance = 0;
	
	$distinctChargeAmounts = array();
	$copyrightUserIDsCharged = array();
	
	if($AuthObj->CheckForPermission("COPYRIGHT_REVENUES")){

		$dbCmd->Query("SELECT CustomerAdjustment, orders.UserID AS UserID FROM balanceadjustments USE INDEX(balanceadjustments_DateCreated) 
					INNER JOIN orders ON balanceadjustments.OrderID = orders.ID 
					WHERE (CustomerAdjustmentType = 'C' OR CustomerAdjustmentType = 'A')
					AND Description LIKE 'Copyright Permissions%'
					AND	" . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()) . "   
					AND	DateCreated BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp);

		while($loyaltyRow = $dbCmd->GetRow()){
			$distinctChargeAmounts[] = $loyaltyRow["CustomerAdjustment"];
			$copyrightUserIDsCharged[] = $loyaltyRow["UserID"];
			$copyrightCharges += $loyaltyRow["CustomerAdjustment"];
		}
		
		$distinctChargeAmounts = array_unique($distinctChargeAmounts);
		$copyrightUserIDsCharged = array_unique($copyrightUserIDsCharged);

	}
	else{
		$t->discard_block("origPage", "CopyrightChargesBL");
	}
	
	// Add to it charges that we have charged in the past.  They may be requesting a refund from something a lont time in the past (before the prices changed).
	$distinctChargeAmounts[] = "19.95";
	$distinctChargeAmounts[] = "39.95";
	$distinctChargeAmounts[] = "25.00";
	$distinctChargeAmounts[] = "25";
	
	// Now flip the numbers around so they are negative.
	$filterArr = array();
	foreach($distinctChargeAmounts as $thisCharge){
		$filterArr[] = "-" . $thisCharge;
	}
	$distinctChargeAmounts = $filterArr;
		
	
	// Don't show the loyalty charges if there aren't any charges.
	// We don't want to show some domain owners the revenue fees if they aren't enrolled in the program.
	if(empty($copyrightCharges)){
		$t->discard_block("origPage", "CopyrightChargesBL");
	}
	

	$dbCmd->Query("SELECT orders.UserID AS UserID, CustomerAdjustment FROM balanceadjustments USE INDEX(balanceadjustments_DateCreated) 
				INNER JOIN orders ON balanceadjustments.OrderID = orders.ID 
				WHERE (CustomerAdjustmentType = 'R' OR CustomerAdjustmentType = 'F') 
				AND " . DbHelper::getOrClauseFromArray("CustomerAdjustment", $distinctChargeAmounts) . "
				AND	" . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()) . "   
				AND	DateCreated BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp);
	$copyrightUserIDsRefunded = array();
	$copyrightRefunds = 0;
	while($loyaltyRow = $dbCmd->GetRow()){
		$copyrightUserIDsRefunded[] = $loyaltyRow["UserID"];
		$copyrightRefunds += abs($loyaltyRow["CustomerAdjustment"]);
	}
	$copyrightUserIDsRefunded = array_unique($copyrightUserIDsRefunded);
	
	$copyrightBalance = $copyrightCharges - $copyrightRefunds;
	
	$t->set_var("COPYRIGHT_CHARGES_TOTAL", '$' . number_format($copyrightCharges, 2));
	$t->set_var("COPYRIGHT_REFUND_TOTALS", '$' . number_format($copyrightRefunds, 2));
	$t->set_var("COPYRIGHT_BALANCE_TOTALS", '$' . number_format($copyrightBalance, 2));
	
	


	
	$copyrightCancel_1Day = 0;
	$copyrightCancel_1Week = 0;
	$copyrightCancel_1Month = 0;
	$copyrightCancel_6Month = 0;
	$copyrightCancel_G6month = 0;
	
	foreach($copyrightUserIDsRefunded as $thisUserID){
		
		$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateCreated) FROM orders USE INDEX(orders_UserID) 
				INNER JOIN balanceadjustments ON balanceadjustments.OrderID = orders.ID 
				WHERE (CustomerAdjustmentType = 'C' OR CustomerAdjustmentType = 'A')
				AND Description LIKE 'Copyright Permissions%'
				AND orders.UserID=$thisUserID
				ORDER BY balanceadjustments.ID ASC LIMIT 1");
		$firstChargeDate = $dbCmd->GetValue();
		
		$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateCreated) FROM orders USE INDEX(orders_UserID) 
				INNER JOIN balanceadjustments ON balanceadjustments.OrderID = orders.ID 
				WHERE (CustomerAdjustmentType = 'R' OR CustomerAdjustmentType = 'F') 
				AND orders.UserID=$thisUserID
				AND " . DbHelper::getOrClauseFromArray("CustomerAdjustment", $distinctChargeAmounts) . " ORDER BY balanceadjustments.ID ASC LIMIT 1");
		$firstRefundDate = $dbCmd->GetValue();

		
		$timeDiff = $firstRefundDate - $firstChargeDate;
		$dayDiff = $timeDiff / (60 * 60 * 24);
		
		if($dayDiff < 1)
			$copyrightCancel_1Day++;
		else if($dayDiff < 7)
			$copyrightCancel_1Week++;
		else if($dayDiff < 31)
			$copyrightCancel_1Month++;
		else if($dayDiff < 186)
			$copyrightCancel_6Month++;
		else
			$copyrightCancel_G6month++;
	}

	if($FirstTimeCustomerCount > 0)
		$newCopyrightMembersDesc = sizeof($copyrightUserIDsCharged) . "&nbsp;&nbsp;&nbsp;<font class='SmallBody'>(" . round(sizeof($copyrightUserIDsCharged) / $FirstTimeCustomerCount * 100, 1) . "% of new)</font>";
	else 
		$newCopyrightMembersDesc = sizeof($copyrightUserIDsCharged);
	

		
	$t->set_var("NEW_COPYRIGHT_MEMBERS", $newCopyrightMembersDesc);
	$t->set_var("CANCELED_COPYRIGHT_MEMBERS", sizeof($copyrightUserIDsRefunded));
	$t->set_var("CANCELED_COPYRIGHT_1DAY", $copyrightCancel_1Day);
	$t->set_var("CANCELED_COPYRIGHT_1WEEK", $copyrightCancel_1Week);
	$t->set_var("CANCELED_COPYRIGHT_1MONTH", $copyrightCancel_1Month);
	$t->set_var("CANCELED_COPYRIGHT_6MONTH", $copyrightCancel_6Month);
	$t->set_var("CANCELED_COPYRIGHT_G6MONTH", $copyrightCancel_G6month);
	
	$t->allowVariableToContainBrackets("NEW_COPYRIGHT_MEMBERS");
	
	/* ---------------------------------------------------------------------------------------------------------------------------------------------------------- */
}





// Build the tabs
$TabsObj = new Navigation();
$TabsObj->AddTab("banners", "Banner Clicks", 'javascript:ChangeMarketingView("banners")', false);
$TabsObj->AddTab("orders", "Order Statistics", 'javascript:ChangeMarketingView("orders")', false);
$TabsObj->AddTab("demographics", "Demographics", 'javascript:ChangeMarketingView("demographics")', false);
$TabsObj->AddTab("questions", "Questionaires", 'javascript:ChangeMarketingView("questions")', false);
$TabsObj->AddTab("cancelations", "Cancelations", 'javascript:ChangeMarketingView("cancelations")', false);
$TabsObj->AddTab("customerworth", "Customer Worth", 'javascript:ChangeMarketingView("customerworth")', false);
$t->set_var("NAV_BAR_HTML", $TabsObj->GetTabsHTML($view));

$t->allowVariableToContainBrackets("NAV_BAR_HTML");



// Set the selected tab to blue and erase the views of HTML that we don't want
if($view == "banners"){
	$t->discard_block("origPage","VIEW_OrdersBL");
	$t->discard_block("origPage","VIEW_DemogBL");
	$t->discard_block("origPage","VIEW_QuestionsBL");
	$t->discard_block("origPage","VIEW_CustomerWorthBL");
	$t->discard_block("origPage","VIEW_Cancelations");
}
else if($view == "orders"){
	$t->discard_block("origPage","VIEW_BannersBL");
	$t->discard_block("origPage","VIEW_DemogBL");
	$t->discard_block("origPage","VIEW_QuestionsBL");
	$t->discard_block("origPage","VIEW_CustomerWorthBL");
	$t->discard_block("origPage","VIEW_Cancelations");
}
else if($view == "demographics"){
	$t->discard_block("origPage","VIEW_BannersBL");
	$t->discard_block("origPage","VIEW_OrdersBL");
	$t->discard_block("origPage","VIEW_QuestionsBL");
	$t->discard_block("origPage","VIEW_CustomerWorthBL");
	$t->discard_block("origPage","VIEW_Cancelations");
}
else if($view == "questions"){
	$t->discard_block("origPage","VIEW_BannersBL");
	$t->discard_block("origPage","VIEW_OrdersBL");
	$t->discard_block("origPage","VIEW_DemogBL");
	$t->discard_block("origPage","VIEW_CustomerWorthBL");
	$t->discard_block("origPage","VIEW_Cancelations");
}
else if($view == "customerworth"){
	$t->discard_block("origPage","VIEW_BannersBL");
	$t->discard_block("origPage","VIEW_OrdersBL");
	$t->discard_block("origPage","VIEW_DemogBL");
	$t->discard_block("origPage","VIEW_QuestionsBL");
	$t->discard_block("origPage","VIEW_Cancelations");
}
else if($view == "cancelations"){
	$t->discard_block("origPage","VIEW_BannersBL");
	$t->discard_block("origPage","VIEW_OrdersBL");
	$t->discard_block("origPage","VIEW_DemogBL");
	$t->discard_block("origPage","VIEW_QuestionsBL");
	$t->discard_block("origPage","VIEW_CustomerWorthBL");
}
else{
	print "Error with View Type:" . $view;
	exit;
}
//Set hidden input in form
$t->set_var( "VIEW_TYPE", $view);







// Find out how many purchases where made without banner clicks
$Total_no_Banners = $TotalOrders - $TotalBannerPurchases;

if($limitToProductID){
	$t->discard_block("origPage","ProductNotSensitiveBL1");
	$t->discard_block("origPage","CustomerInfoBL");
	$t->discard_block("origPage","ProductNotSensitiveBL2");
}
	
	
	

$t->set_var(array("TOTAL_PROJECTS"=>$TotalProjects));
$t->set_var(array("TOTAL_DISCOUNTS"=>$ProjectDiscounts));
$t->set_var(array("AVERAGE_SUBTOTAL"=>$AverageSubtotal));
$t->set_var(array("AVERAGE_PROFIT"=>$AverageProfit));
$t->set_var(array("TOTAL_SUBTOTALS"=>$TotalProjectsSubtotals));
$t->set_var(array("TOTAL_SUBTOTALS_NO_COMMA"=>$TotalProjectsSubtotalNoComma));
$t->set_var(array("TOTAL_PROFITS"=>$TotalProfits));
$t->set_var(array("TOTAL_PROFITS_NO_COMMA"=>$TotalProfitsNoComma));
$t->set_var(array("TOTAL_BANNER_CLICKS"=>$TotalBannerClicks));
$t->set_var(array("SALES_COMMISSIONS"=>number_format($TotalSalesCommissions, 2)));
$t->set_var(array("SALES_COMMISSIONS_NO_COMMA"=>$TotalSalesCommissions));





$t->set_var(array("REPRINT_ORDER_TOTAL"=>$RePrintOrderCounter));
$t->set_var(array("REPRINT_PROJECT_TOTAL"=>$RePrintProjectCounter));
$t->set_var(array("NEW_CUSTOMER_ORDERS"=>$FirstTimeOrderCounter));
$t->set_var(array("REPEAT_CUSTOMER_ORDERS"=>$RepeatOrderCounter));
$t->set_var(array("REPEAT_CUSTOMERS"=>$RepeatCustomerCount));
$t->set_var(array("TOTAL_CUSTOMERS"=>$TotalCustomerCount));
$t->set_var(array("FIRSTTIME_CUSTOMERS"=>$FirstTimeCustomerCount));
$t->set_var(array("REPEAT_ORDER_PERCENT"=>$repeat_order_percent));
$t->set_var(array("NOMAD_CUSTOMERS"=>$nomadCustomers));

$t->set_var(array("FIRSTTIME_COUPON_CUSTOMERS"=>$FirstTimeCouponCustomersCount));
$t->set_var(array("FIRSTTIME_SALESREP_CUSTOMERS"=>$FirstTimeSalesRepCustomersCount));
$t->set_var(array("SALESREP_CUSTOMERS"=>$TotalSalesRepCustomersCount));


$t->set_var(array("REPEAT_CUSTOMER_PERCENT"=>$RepeatCustomerPercentage));
$t->set_var(array("FIRSTTIME_CUSTOMERS_BANNERS"=>$FirstTimeCustomerBannerCount));



$t->set_var(array("DEMOG_STATES_TABLE"=>$Demog_States_table));
$t->allowVariableToContainBrackets("DEMOG_STATES_TABLE");

$t->set_var(array("SHIPPING_METHODS_TABLE"=>$Shipping_methods_table));
$t->allowVariableToContainBrackets("SHIPPING_METHODS_TABLE");

$t->set_var(array("CUSTOMER_ASSISTANCE_CHARGES"=>$customer_assistance_table));
$t->allowVariableToContainBrackets("CUSTOMER_ASSISTANCE_CHARGES");

$t->set_var(array("REORDER_PERIOD_TABLE"=>$ReOrderPeriod_table));
$t->allowVariableToContainBrackets("REORDER_PERIOD_TABLE");

$t->set_var(array("TIME_UNTIL_FIRST_REORDER_TABLE"=>$TimeUntilFirstOrder_table));
$t->allowVariableToContainBrackets("TIME_UNTIL_FIRST_REORDER_TABLE");

$t->set_var(array("REPRINT_REASONS_TABLE"=>$reorder_reason_table));
$t->allowVariableToContainBrackets("REPRINT_REASONS_TABLE");

$t->set_var(array("TOTAL_BANNERS"=>$TotalBannerPurchases));
$t->set_var(array("TOTAL_NEW_CUSTOMER_BANNERS"=>$TotalBannerPurchasesNewCust));
$t->set_var(array("SAME_DAY_FIRST_TIME_NEW_CUSTOMER_BANNERS"=>$TotalBannerPurchasesNewCustSameDay));
$t->set_var(array("TOTAL_REPEAT_CUSTOMER_BANNERS"=>($TotalBannerPurchases - $TotalBannerPurchasesNewCust)));




$t->set_var("TOTAL_NO_BANNERS", $Total_no_Banners);
$t->set_var("TOTAL_ORDERS", $TotalOrders);
$t->set_var("NEW_ACCOUNTS", $NewAccountsTotal);
$t->set_var("DEAD_ACCOUNTS", $DeadAccountsTotal);
$t->set_var("SAME_DAY_ACCOUNT_PURCAHSES", $SameDateAccountOrders);
$t->set_var("DELAYED_ACCOUNT_PURCAHSES", $DelayedAccountPurcahses);

if($NewAccountsTotal > 0){
	$t->set_var("DEAD_ACCOUNT_RATIO", round(($DeadAccountsTotal/$NewAccountsTotal * 100), 1));
	$t->set_var("SAME_DAY_ACCOUNT_PURCAHSE_RATIO", round(($SameDateAccountOrders/$NewAccountsTotal * 100), 1));
	$t->set_var("DELAYED_ACCOUNT_PURCAHSES_RATIO", round(($DelayedAccountPurcahses/$NewAccountsTotal * 100), 1));
}
else {
	$t->set_var("DEAD_ACCOUNT_RATIO", "0");
	$t->set_var("SAME_DAY_ACCOUNT_PURCAHSE_RATIO", "0");
	$t->set_var("DELAYED_ACCOUNT_PURCAHSES_RATIO", "0");
}
	




$t->set_var("TOTAL_VENDOR_ADJUSTMENTS", $totalVendorAdjustments);
$t->set_var("TOTAL_CUSTOMER_ADJUSTMENTS", $totalCustomerAdjustments);
$t->set_var("ORDER_ADJUSTMENTS_INCOME", $totalAdjustmentsProfit);



$t->set_var(array("BANNER_CLICK_BANNER_SEARCH"=>addslashes(WebUtil::htmlOutput($bannerClicksTrackingCodeSearch))));



// Find the average costs of first time customers and repeats.. be careful not to divide by zero
if($FirstTimeOrderCounter == 0){
	$t->set_var(array(
		"FIRST_TIME_ORDER_TOTAL"=>'N/A',
		"FIRST_TIME_ORDER_PROFIT"=>'N/A'
		));
}
else{
	$t->set_var(array(
		"FIRST_TIME_ORDER_TOTAL"=>'$' . round($FirstTimeCustomerTotals / $FirstTimeOrderCounter, 2),
		"FIRST_TIME_ORDER_PROFIT"=>'$' . round($FirstTimeCustomerProfits / $FirstTimeOrderCounter, 2)
		));
}
if($RepeatOrderCounter == 0){
	$t->set_var(array(
		"REPEAT_ORDER_TOTAL"=>'N/A',
		"REPEAT_ORDER_PROFIT"=>'N/A'
		));
}
else{
	$t->set_var(array(
		"REPEAT_ORDER_TOTAL"=>'$' . round($RepeatCustomerTotals / $RepeatOrderCounter, 2),
		"REPEAT_ORDER_PROFIT"=>'$' . round($RepeatCustomerProfits / $RepeatOrderCounter, 2)
		));
}




// ---------  Report Caching ----------------
// Cache the Results of the Orders Report.
if($view == "orders" || $view == "banners"){

	
	$reportHTML = $t->finish($t->parse("OUT","origPage"));

	$reportHTML = gzcompress($reportHTML);

	$fp = fopen($reportCachedFileName, "w");
	fwrite($fp, $reportHTML);
	fclose($fp);
	
}









if(!empty($cacheReportForUser)){
	print "The Report has been successfully cached on behalf of UserID: " . $cacheReportForUser;
}
else{
	// Output compressed HTML
	WebUtil::print_gzipped_page_start();
	$t->pparse("OUT","origPage");
	WebUtil::print_gzipped_page_end();
}








?>
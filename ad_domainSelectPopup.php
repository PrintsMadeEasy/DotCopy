<?

require_once("library/Boot_Session.php");



$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$timeFrame = WebUtil::GetInput("TimeFrame", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "TODAY");
$ReportPeriodIsDateRange = WebUtil::GetInput( "PeriodType", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "TIMEFRAME" ) == "DATERANGE";

// This is the only page that we don't want to ensure Member security on.  So do a "General Login"
// We don't want to authenticate for an Admin LOGIN because it checks if the IP address has been enabled.  The problem is that an admin visiting the IP Access page might not be able to switch domains (in order to enable themselves).
$AuthObj = new Authenticate(Authenticate::login_general);
$AuthObj->EnsureGroup("MEMBER");

$domainObj = Domain::singleton();

$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

$availableDomainIDsArr = $AuthObj->getUserDomainsIDs();
$selectedDomainIDs = $domainObj->getSelectedDomainIDs();

$t = new Templatex(".");

$domainProjectCountsArr = array();
$domainOrderCountsArr = array();
$domainShippingHandelingArr = array();
$domainVendorCostsArr = array();
$domainProfitsArr = array();




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




$t->set_file("origPage", "ad_domainSelectPopup-template.html");
$t->set_block("origPage","DomainLogosBL","DomainLogosBLout");

foreach($availableDomainIDsArr as $thisDomainID){
	
	$domainLogoObj = new DomainLogos($thisDomainID);

	$t->set_var("DOMAIN_LOGO",  $domainLogoObj->navBarIcon);
	$t->set_var("DOMAIN_KEY",  Domain::getDomainKeyFromID($thisDomainID));
	$t->set_var("DOMAIN_URL",  Domain::getWebsiteURLforDomainID($thisDomainID));
	$t->set_var("DOMAIN_ID",  $thisDomainID);
	
	if($domainObj->getTopDomainID() == $thisDomainID)
		$t->set_var("TOP_DOMAIN_NOTIFY",  "<font class='ReallySmallBody'><font color='#990000'>Currently Top Domain</font><br><img src='./images/transparent.gif' width='5' height='5'><br></font>");
	else
		$t->set_var("TOP_DOMAIN_NOTIFY",  "");
	
	$t->allowVariableToContainBrackets("TOP_DOMAIN_NOTIFY");
		
	if($view == "extended"){
		
		// This will make sure that their IP address has been enabled for backend access.  We don't enable it for just the Domain List.
		$AuthObj->EnsureMemberSecurity();
		
		if(!$AuthObj->CheckForPermission("VIEW_DOMAIN_TOTALS"))
			throw new Exception("Permission Denied");
		
		$dbCmd->Query("SELECT COUNT(projectsordered.ID) AS ProjectCount, COUNT(DISTINCT orders.ID) AS OrderCount, SUM(CustomerSubtotal) SubtotalNoDisc, 
						SUM(VendorSubtotal1) AS VendorTotal1,
						SUM(VendorSubtotal2) AS VendorTotal2,
						SUM(VendorSubtotal3) AS VendorTotal3,
						SUM(VendorSubtotal4) AS VendorTotal4,
						SUM(VendorSubtotal5) AS VendorTotal5,
						SUM(VendorSubtotal6) AS VendorTotal6,
						SUM(projectsordered.CustomerTax) AS ProjectTax,
						SUM(ROUND(projectsordered.CustomerSubtotal * projectsordered.CustomerDiscount, 2)) AS TotlalDiscounts
						FROM projectsordered INNER JOIN orders ON orders.ID = projectsordered.OrderID 
						WHERE orders.DomainID=$thisDomainID AND Status != 'C' AND 
						orders.DateOrdered BETWEEN " . DbCmd::convertUnixTimeStmpToMysql($StartTimeStamp) . " AND " . DbCmd::convertUnixTimeStmpToMysql($EndTimeStamp));
		
		$domProjTot = $dbCmd->GetRow();
		
		// Get the number of new customers within this period.
		$dbCmd->Query("SELECT COUNT(*) FROM orders 
						WHERE DomainID=$thisDomainID AND orders.FirstTimeCustomer = 'Y'
						AND orders.DateOrdered BETWEEN " . DbCmd::convertUnixTimeStmpToMysql($StartTimeStamp) . " AND " . DbCmd::convertUnixTimeStmpToMysql($EndTimeStamp));
		$newCustomerCount = $dbCmd->GetValue();
		
		// With this version of SQL there is not sub-queries available.  So we have to add everything programatically.
		// ... and check that the order hasn't been canceled.
		$shippingTotal = 0;
		$shippingTax = 0;
		$dbCmd->Query("SELECT ID, ShippingQuote, ShippingTax FROM orders WHERE orders.DomainID=$thisDomainID  AND 
						DateOrdered BETWEEN " . DbCmd::convertUnixTimeStmpToMysql($StartTimeStamp) . " AND " . DbCmd::convertUnixTimeStmpToMysql($EndTimeStamp));
		while($orderDetail = $dbCmd->GetRow()){
			
			if(!Order::CheckForActiveProjectWithinOrder($dbCmd2, $orderDetail["ID"]))
				continue;
				
			$shippingTotal += $orderDetail["ShippingQuote"];
			$shippingTax += $orderDetail["ShippingTax"];
		}


		$dbCmd->Query("SELECT SUM(ChargeAmount) AS LoyaltyCharges, SUM(RefundAmount) AS LoyaltyRefunds FROM loyaltycharges WHERE 
						DomainID=$thisDomainID  AND 
						Date BETWEEN " . DbCmd::convertUnixTimeStmpToMysql($StartTimeStamp) . " AND " . DbCmd::convertUnixTimeStmpToMysql($EndTimeStamp));
		$loyaltyRow = $dbCmd->GetRow();
		$loyaltyCharges = $loyaltyRow["LoyaltyCharges"];
		$loyaltyRefunds = $loyaltyRow["LoyaltyRefunds"];

		
		$dbCmd->Query("SELECT SUM(CustomerAdjustment) AS CustAdjust, SUM(VendorAdjustment) AS VendAdjust FROM 
						orders INNER JOIN balanceadjustments ON balanceadjustments.OrderID = orders.ID  
						WHERE orders.DomainID=$thisDomainID  AND 
						DateCreated BETWEEN " . DbCmd::convertUnixTimeStmpToMysql($StartTimeStamp) . " AND " . DbCmd::convertUnixTimeStmpToMysql($EndTimeStamp));
		$adjustRow = $dbCmd->GetRow();
		$custAdjustments = $adjustRow["CustAdjust"];
		$vendAdjustments = $adjustRow["VendAdjust"];
		
		
		// We are going to combine the loyalty charges with the balance adjustments.
		// ... but only if the user has permission to see them.
		if($AuthObj->CheckForPermission("LOYALTY_REVENUES"))
			$custAdjustments += ($loyaltyCharges - $loyaltyRefunds);
		
						
		$projectSubtotal = $domProjTot["SubtotalNoDisc"] - $domProjTot["TotlalDiscounts"];
		$totalRevenues = $shippingTotal + $projectSubtotal + $shippingTax + $domProjTot["ProjectTax"] + $custAdjustments;
		$subTotalProfit = $projectSubtotal - ($domProjTot["VendorTotal1"] + $domProjTot["VendorTotal2"] + $domProjTot["VendorTotal3"] + $domProjTot["VendorTotal4"] + $domProjTot["VendorTotal5"] + $domProjTot["VendorTotal6"]);
		
		// Estimate that our merchant processing fees are 3%
		$profitEstimate = $subTotalProfit - ($totalRevenues * 0.03);
		$profitEstimate += ($custAdjustments - $vendAdjustments);
		
		$subTotalProfit -= ($totalRevenues * 0.03);

		
		$totalsHtmlTable = "<table width='100%' cellpadding='0' cellspacing='0' border='0'>";
		$totalsHtmlTable .= "<tr>	<td class='ReallySmallBody' style='color:#000000'><b>Projects:</b></td><td class='SmallBody' style='color:#000000'>". $domProjTot["ProjectCount"] ."</td>
									<td class='ReallySmallBody' style='color:#000000'><b>Orders:</b></td><td class='SmallBody' style='color:#000000'>". $domProjTot["OrderCount"] ."</td>
							</tr>";
		$totalsHtmlTable .= "<tr>
									<td class='ReallySmallBody' style='color:#000000'><b>Subtotals:</b></td><td class='SmallBody' style='color:#000000'>\$".number_format(round($projectSubtotal)) ."</td>
									<td class='ReallySmallBody' style='color:#000000'><b>Shipping:</b></td><td class='SmallBody' style='color:#000000'>\$".number_format(round($shippingTotal))."</td>
							</tr>";
		$totalsHtmlTable .= "<tr>
									<td class='ReallySmallBody' style='color:#000000'><b>Cust Adjst:</b></td><td class='SmallBody' style='color:#0000aa'>\$".number_format(round($custAdjustments))."</td>
									<td class='ReallySmallBody' style='color:#000000'><b>Vend Adjst:</b></td><td class='SmallBody' style='color:#009900'>\$".number_format(round($vendAdjustments))."</td>
							</tr>";
		$totalsHtmlTable .= "<tr>
									<td class='ReallySmallBody' style='color:#000000'><b>Revenue:</b></td><td class='SmallBody' style='color:#0000aa'>\$".number_format(round($totalRevenues))."</td>
									<td class='ReallySmallBody' style='color:#000000'><b>SubT. Profit:</b></td><td class='SmallBody' style='color:#009900'>\$".number_format(round($subTotalProfit))."</td>
							</tr>";
		$totalsHtmlTable .= "</table>";

		$totalsHtmlTable .= "<input type='hidden' id='totals_".$thisDomainID."_projectCount' name='totals_".$thisDomainID."_projectCount' value='" . $domProjTot["ProjectCount"] . "'>";
		$totalsHtmlTable .= "<input type='hidden' id='totals_".$thisDomainID."_orderCount' name='totals_".$thisDomainID."_orderCount' value='" . $domProjTot["OrderCount"] . "'>";
		$totalsHtmlTable .= "<input type='hidden' id='totals_".$thisDomainID."_revenue' name='totals_".$thisDomainID."_revenue' value='" . round($totalRevenues) . "'>";
		$totalsHtmlTable .= "<input type='hidden' id='totals_".$thisDomainID."_subTprofit' name='totals_".$thisDomainID."_subTprofit' value='" . round($profitEstimate) . "'>";
		$totalsHtmlTable .= "<input type='hidden' id='totals_".$thisDomainID."_newCustomers' name='totals_".$thisDomainID."_newCustomers' value='" . round($newCustomerCount) . "'>";
		$totalsHtmlTable .= "<input type='hidden' id='totals_".$thisDomainID."_adjustBalance' name='totals_".$thisDomainID."_adjustBalance' value='" . round($custAdjustments - $vendAdjustments) . "'>";
	
		$t->set_var("EXTRA_COLUMN",  "<td class='SmallBody' width='43%' bgcolor='#D2D8DF'>$totalsHtmlTable</td>");
		$t->allowVariableToContainBrackets("EXTRA_COLUMN");
		
		$t->set_var("NEW_CUST_COUNT", "<br>New Customers: $newCustomerCount");
		$t->allowVariableToContainBrackets("NEW_CUST_COUNT");
		
		$t->set_var("VIEW_EXTENDED_JS_FLAG", "true");
	}
	else{
		$t->set_var("EXTRA_COLUMN",  "");
		$t->set_var("VIEW_EXTENDED_JS_FLAG", "false");
		$t->set_var("NEW_CUST_COUNT", "");
		
	}
	
	if(in_array($thisDomainID, $selectedDomainIDs))
		$t->set_var("DOMAIN_CHECKED", "checked");
	else
		$t->set_var("DOMAIN_CHECKED", "");
	
	$t->parse("DomainLogosBLout","DomainLogosBL",true);

	
}

if($view == "extended"){
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
	$timeFrameValues["THISMONTH"] = "This Month";
	$timeFrameValues["LASTMONTH"] = "Last Month";
	
	$t->set_var("TIMEFRAME_SEL",  Widgets::buildSelect($timeFrameValues, $timeFrame));
	$t->allowVariableToContainBrackets("TIMEFRAME_SEL");
	
	// A javascript command to display the totals when the page loads... but we only do this with the Extended View.
	$t->set_var("DISPLAY_TOTALS_EXTENDED_VIEW",  "top.displayDomainTotals(); showDomainComparisons();");

	$t->set_var("PERIODTYPETIMEFRAME", $ReportPeriodIsDateRange ? null : "CHECKED" );
	$t->set_var("PERIODTYPEDATERANGE", $ReportPeriodIsDateRange ? "CHECKED" : null );
	$t->set_var("PERIODISTIMEFRAME", $ReportPeriodIsDateRange ? "false" : "true" );
	$t->set_var("DATERANGESELS", Widgets::BuildDateRangeSelect( $StartTimeStamp, $EndTimeStamp, "D", "DateRange", "dashboardInput"));
	$t->allowVariableToContainBrackets("DATERANGESELS");
	
	$t->set_var( "START_REPORT_DATE_STRING", date("m/d/Y", $StartTimeStamp));
	
	$t->set_var("START_REPORT_DAY",  date("j", $StartTimeStamp));
	$t->set_var("START_REPORT_MONTH",  date("m", $StartTimeStamp));
	$t->set_var("START_REPORT_YEAR",  date("Y", $StartTimeStamp));
	$t->set_var("END_REPORT_DAY",  date("j", $EndTimeStamp));
	$t->set_var("END_REPORT_MONTH",  date("m", $EndTimeStamp));
	$t->set_var("END_REPORT_YEAR",  date("Y", $EndTimeStamp));

	
	
}
else{
	
	$t->set_var("START_REPORT_DAY",  0);
	$t->set_var("START_REPORT_MONTH",  0);
	$t->set_var("START_REPORT_YEAR",  0);
	$t->set_var("END_REPORT_DAY",  0);
	$t->set_var("END_REPORT_MONTH",  0);
	$t->set_var("END_REPORT_YEAR",  0);
	
	$t->set_var("PERIODISTIMEFRAME",  true);
	
	$t->set_var("START_REPORT_DATE_STRING",  date("m/d/Y", $StartTimeStamp));
	
	$t->set_var("DISPLAY_TOTALS_EXTENDED_VIEW",  "");
	$t->discard_block("origPage", "TimeFrameBL");
}


$t->pparse("OUT","origPage");



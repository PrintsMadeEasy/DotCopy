<?

require_once("library/Boot_Session.php");

// Don't make it look like we are favoring a particular Domain 
Domain::removeTopDomainID();

$showadjustments = WebUtil::GetInput("showadjustments", FILTER_SANITIZE_STRING_ONE_LINE);


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();



$domainObj = Domain::singleton();


// Make this script be able to run for a while
set_time_limit(3000);

$cacheReportForUser = WebUtil::GetInput("CacheReportOnBehalfOfUserID", FILTER_SANITIZE_INT);


// A cron can run reports on behalf of a User (without being logged in).
if(!empty($cacheReportForUser)){
	$cacheReportForUser = intval($cacheReportForUser);
	$AuthObj = new Authenticate(Authenticate::login_OVERRIDE, $cacheReportForUser);
	
	// A pipe delimeted list that we want to use for caching the report
	$domainIDsForReportCache = WebUtil::GetInput("domainIDsForReportCache", FILTER_SANITIZE_INT);
	$domainIDsForReportCacheArr = explode("\|", $domainIDsForReportCache);
	
	foreach($domainIDsForReportCacheArr as $thisDomainIDtoCache){
		if(!Domain::checkIfDomainIDexists($thisDomainIDtoCache) || !$AuthObj->CheckIfUserCanViewDomainID($thisDomainIDtoCache)){
			WebUtil::WebmasterError("Error caching the Month Report the domain ID is not available for the user: $cacheReportForUser DomainID: $thisDomainIDtoCache");
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


if(!$AuthObj->CheckForPermission("REPORT_MONTH"))
	WebUtil::PrintAdminError("Not Available");



$t = new Templatex(".");

$t->set_file("origPage", "ad_report_month-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


$theTime = time();
$current_month = date("n", $theTime);
$current_year = date("Y", $theTime);

$MonthStatus = "";



// The user may select a report by month... or between a date range

if(WebUtil::GetInput("datetype", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "month") == "month"){


	$month = WebUtil::GetInput("month", FILTER_SANITIZE_INT, date("n"));
	$year = WebUtil::GetInput("year", FILTER_SANITIZE_INT, date("Y"));


	if($month < $current_month || $year < $current_year)
		$MonthStatus = "This month is closed. ";
	if($month == $current_month && $year == $current_year)
		$MonthStatus = "This month is not closed yet.";


	
	$start_timestamp = mktime (0,0,0,$month,1,$year);
	$end_timestamp = mktime (23,59,59,$month +1,0,$year);
	
	// Format the dates that we want for MySQL
	$start_mysql_timestamp = date("YmdHis", $start_timestamp);
	$end_mysql_timestamp  = date("YmdHis", $end_timestamp);
	
}
else if(WebUtil::GetInput("datetype", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "range"){

	$MonthStatus = "";

	$startday= WebUtil::GetInput( "DateRangeStartDay", FILTER_SANITIZE_INT);
	$startmonth= WebUtil::GetInput( "DateRangeStartMonth", FILTER_SANITIZE_INT);
	$startyear= WebUtil::GetInput( "DateRangeStartYear", FILTER_SANITIZE_INT);
	$endday= WebUtil::GetInput( "DateRangeEndDay", FILTER_SANITIZE_INT);
	$endmonth= WebUtil::GetInput( "DateRangeEndMonth", FILTER_SANITIZE_INT);
	$endyear= WebUtil::GetInput( "DateRangeEndYear", FILTER_SANITIZE_INT);


	$start_timestamp = mktime (0,0,0,$startmonth,$startday,$startyear);
	$end_timestamp = mktime (23,59,59,$endmonth,$endday,$endyear);
	
	if(  $start_timestamp >  $end_timestamp  )
		WebUtil::PrintAdminError("Invalid Date Range Specified.");


	// Format the dates that we want for MySQL
	$start_mysql_timestamp = date("YmdHis", $start_timestamp);
	$end_mysql_timestamp  = date("YmdHis", $end_timestamp);
	
	// Just set the Month Drop down to whatever the start month of the date range is.
	$month = $startmonth;
	$year = $startyear;

}
else{
	throw new Exception("Illegal Date Type");
}







// ---------  Report Caching ----------------
// Find out if report has already been cached for "Order Statistic Reports"

if(!file_exists(Constants::GetReportCacheDirectory()))
	throw new Exception("Report Cache Directory Does not exist.");

// Build a unique String that will determine if the report is unique
$uniqueReportString = $start_timestamp . $end_timestamp . $showadjustments;

// The Report should also contain all of the groups that the user belongs to... This will keep someone from viewing a cached report belonging to someone else.
$uniqueReportString .= implode(",", $AuthObj->GetGroupsThatUserBelongsTo());

// The Report is cached based upon the Domain IDs that were selected.
$uniqueReportString .= implode(",", $domainObj->getSelectedDomainIDs());

// Take the MD5 of the report string so the unique list won't make the file name too long.
$reportCachedFileName = Constants::GetReportCacheDirectory() . "/MonthReport_" . md5($uniqueReportString);

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
		if(!empty($showadjustments))
			$showAdjustmensParameter = " document.forms[\"ordersbyrange\"].showadjustments.value=\"true\"; ";
		else
			$showAdjustmensParameter = "";
		
		$cachedReportMessage = "<font class='SmallBody'>This report was saved on " . date("F j, Y, g:i a", $reportGeneratedTimeStamp ) . ".  <a href='javascript:document.forms[\"ordersbyrange\"].doNotCacheReport.value=\"true\"; " . $showAdjustmensParameter . " document.forms[\"ordersbyrange\"].submit();' class='BlueRedLink'>Click here</a> to regenerate.</font>";
		$reportCacheHTML = preg_replace("/<!--\sReportCacheNoticePlaceHolder\s-->/", $cachedReportMessage, $reportCacheHTML);
	
		if(!empty($cacheReportForUser))
			print "Can not print a Cached Page with UserID override";
		else
			print $reportCacheHTML;
		exit;
	}
}







$t->set_var("PERIOD_DESC", "<b>Period Between:</b> " . date("F j, Y, g:i A", $start_timestamp) . " and " . date("F j, Y, g:i:s A", $end_timestamp));

$t->set_var(array("START_TIMESTAMP"=>$start_timestamp, "END_TIMESTAMP"=>$end_timestamp));


// Keep adding to these vars as we loop through the query
$order_income_total = 0;
$totalRetailCharges = 0;
$totalRetailChargesNonTaxable = 0;
$totalRetailChargesTaxable = 0;
$total_orders = 0;
$total_projects = 0;
$total_reprints = 0;
$tax_total_for_subtotals = 0;
$shipping_tax_total = 0;
$sum_project_vendor_subtotals = 0;
$sum_reprint_vendor_subtotals = 0;
$sum_reprint_customer_subtotals  = 0;
$project_customer_subtotal_total = 0;
$reprint_income_total = 0;
$project_income_total = 0;
$shipping_quotes_total = 0;
$shipping_quotes_total_nontaxable = 0;
$shipping_quotes_total_taxable = 0;
$order_totals_invoiced = 0;
$project_income_total_invoiced = 0;

$OrderIDtrackerHash = array();  //Use the order id as the key in the hash.. if the key isn't set then it means we have not seen the order yet.


$totalVendorIDsArr = array();

// There are currently 6 Vendor IDs associated with a single project
for($i=1; $i<=6; $i++){

	$dbCmd->Query("SELECT DISTINCT VendorID". $i . "  
		FROM orders USE INDEX (orders_DateOrdered) INNER JOIN projectsordered ON orders.ID = projectsordered.OrderID
		WHERE VendorID". $i . " >0 AND " . DbHelper::getOrClauseFromArray("orders.DomainID", $domainObj->getSelectedDomainIDs()) . " AND
		DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp);

	while($thisVendorID = $dbCmd->GetValue()){
		if(!in_array($thisVendorID, $totalVendorIDsArr))
			$totalVendorIDsArr[] = $thisVendorID;
	}
}


// The list of all Vendor ID's during the period includes any vendors that may have adjustements.
// There is a rare chance that a vendor could have an adjustment, even though they haven't had any orders.
$dbCmd->Query("SELECT DISTINCT VendorID FROM balanceadjustments USE INDEX (balanceadjustments_DateCreated) INNER JOIN orders ON balanceadjustments.OrderID = orders.ID
		WHERE " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()) . " AND 
		(balanceadjustments.DateCreated BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") ");
while($thisVendorID = $dbCmd->GetValue()){
	if(!in_array($thisVendorID, $totalVendorIDsArr))
		$totalVendorIDsArr[] = $thisVendorID;
}



// If the person viewing this page is a vendor... then they shouldn't be able to see other vendors.
if($AuthObj->CheckIfBelongsToGroup("VENDOR")){
	$totalVendorIDsArr = array($UserID);
}



// We need to get a list of all Vendors ID's during this period with their Company Names
// We are also going to be collecting some totals for each one.
$VendorTotalsArray = array();
foreach($totalVendorIDsArr as $thisVendorID){
	
	$vendorName = Product::GetVendorNameByUserID($dbCmd, $thisVendorID);
	
	$VendorTotalsArray[$thisVendorID] = array();
	$VendorTotalsArray[$thisVendorID]["VendorName"] = $vendorName;
	$VendorTotalsArray[$thisVendorID]["Totals"] = 0;
	$VendorTotalsArray[$thisVendorID]["ReprintTotals"] = 0;
	$VendorTotalsArray[$thisVendorID]["Adjustments"] = 0;
}




if($AuthObj->CheckIfBelongsToGroup("VENDOR"))
	$vendorRestriction = $UserID;
else
	$vendorRestriction = null;




$query = "SELECT orders.ID AS OrderID, users.Name, users.Company, projectsordered.OrderDescription, 
	UNIX_TIMESTAMP(orders.DateOrdered) AS DateOrdered, projectsordered.ID AS ProjectID,  
	projectsordered.CustomerSubtotal, projectsordered.CustomerTax, 
	projectsordered.CustomerDiscount, projectsordered.OptionsDescription, projectsordered.Status, 
	projectsordered.RePrintLink, orders.ShippingQuote, orders.BillingType, orders.ShippingTax 
	FROM (orders USE INDEX (orders_DateOrdered) INNER JOIN projectsordered ON orders.ID = projectsordered.OrderID) 
	INNER JOIN users ON orders.UserID = users.ID WHERE 
	" . DbHelper::getOrClauseFromArray("orders.DomainID", $domainObj->getSelectedDomainIDs()) . " AND 
	(orders.DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") ";

if($AuthObj->CheckIfBelongsToGroup("VENDOR"))
	$query .= " AND (" . Product::GetVendorIDLimiterSQL($UserID) . ") ";

$query .= " ORDER BY orders.DateOrdered DESC";


$dbCmd->Query($query);

$empty_projects=true;
$empty_reprints = true;
$empty_adjustments = true;
$preventTimeoutCounter = 0;

while ($row = $dbCmd->GetRow()){


	$order_number = $row["OrderID"];
	$user_name = $row["Name"];
	$user_company = $row["Company"];
	$order_summary = $row["OrderDescription"];
	$date_ordered = $row["DateOrdered"];
	$project_id = $row["ProjectID"];
	$customer_subtotal = $row["CustomerSubtotal"];
	$Project_tax = $row["CustomerTax"];
	$CustDisc = $row["CustomerDiscount"];
	$OptionsDescription = $row["OptionsDescription"];
	$ProjectStatus = $row["Status"];
	$RePrintLink = $row["RePrintLink"];
	$CustomerShippingQuote = $row["ShippingQuote"];
	$BillingType = $row["BillingType"];
	$ShippingTax = $row["ShippingTax"];

	if($CustDisc == "")
		$CustDisc = 0;



	// Skip this project if it has been canceled
	if($ProjectStatus == "C")
		continue;


	$preventTimeoutCounter++;


	if($preventTimeoutCounter > 500){
		print " ";
		Constants::FlushBufferOutput();
		$preventTimeoutCounter = 0;
	}


	// This will get the subtotal for all vendors associates with this project...
	// ... unless the person viewing this page is a vendor.   Then it will only get there total.
	$vendor_subtotal_all = ProjectOrdered::GetVendorSubtotalFromProject($dbCmd2, $project_id, $vendorRestriction);

	$CustomerDiscount = round($customer_subtotal * $CustDisc, 2);

	// This is a way to add up results from join... for each unique order (not project)
	if(!isset($OrderIDtrackerHash[$order_number])){
		$OrderIDtrackerHash[$order_number] = true;
		$total_orders++;
		$shipping_quotes_total += $CustomerShippingQuote;

		$shipping_tax_total += $ShippingTax;

		// For corporate billing, add the Shipping Quote to the total
		if($BillingType == "C")
			$order_totals_invoiced += $CustomerShippingQuote;
		if($Project_tax == 0)
			$shipping_quotes_total_nontaxable += $CustomerShippingQuote;
		else
			$shipping_quotes_total_taxable += $CustomerShippingQuote;

	}

	// Add up main totals
	$order_income = $customer_subtotal - $CustomerDiscount - $vendor_subtotal_all;
	$order_income_total += $order_income;
	$totalRetailCharges += $customer_subtotal - $CustomerDiscount;
	$tax_total_for_subtotals += $Project_tax;

	// If the order is corporate billed
	if($BillingType == "C"){
		$order_totals_invoiced += $customer_subtotal - $CustomerDiscount + $Project_tax;
		$project_income_total_invoiced += $order_income;
	}

	if($Project_tax == 0)
		$totalRetailChargesNonTaxable += $customer_subtotal - $CustomerDiscount;
	else
		$totalRetailChargesTaxable += $customer_subtotal - $CustomerDiscount;


	// Add the totals into separate hashes, depending on whether this is a re-print or not.
	if($RePrintLink <> "0"){

		$empty_reprints = false;

		$sum_reprint_customer_subtotals += $customer_subtotal - $CustomerDiscount;
		$sum_reprint_vendor_subtotals += $vendor_subtotal_all;
		$reprint_income_total += $order_income;
		$total_reprints++;

		foreach($VendorTotalsArray as $thisVendorID => $vendorHash)
			$VendorTotalsArray[$thisVendorID]["ReprintTotals"] += ProjectOrdered::GetVendorSubtotalFromProject($dbCmd2, $project_id, $thisVendorID);

	}
	else{
		$empty_projects = false;


		$project_customer_subtotal_total += $customer_subtotal - $CustomerDiscount;
		$sum_project_vendor_subtotals += $vendor_subtotal_all;
		$project_income_total += $order_income;
		$total_projects++;

		foreach($VendorTotalsArray as $thisVendorID => $vendorHash)
			$VendorTotalsArray[$thisVendorID]["Totals"] += ProjectOrdered::GetVendorSubtotalFromProject($dbCmd2, $project_id, $thisVendorID);
	}
}



//  Now we are going to fill up the adjustments for the period


if($AuthObj->CheckIfBelongsToGroup("VENDOR"))
	$t->set_block("origPage","VendorAdjustmentBL","VendorAdjustmentBLout");
else
	$t->set_block("origPage","AdminAdjustmentBL","AdminAdjustmentBLout");

$VendorAdjustmentTotal = 0;
$CustomerAdjusmentTotal = 0;


// Get list adjustments
$query = "SELECT OrderID, CustomerAdjustment, VendorAdjustment, Description, FromUserID, 
		UNIX_TIMESTAMP(balanceadjustments.DateCreated) AS DateCreated, balanceadjustments.VendorID FROM balanceadjustments USE INDEX (balanceadjustments_DateCreated) 
		INNER JOIN orders ON balanceadjustments.OrderID = orders.ID
		WHERE " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()) . " AND 
		(balanceadjustments.DateCreated BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") ";

// Don't let Vendors see adjusments that don't belong to them.
if($AuthObj->CheckIfBelongsToGroup("VENDOR"))
	$query .= " AND VendorID=" . $UserID;

$dbCmd->Query($query);

$VendorAdjusmentTotal = 0;
$CustomerAdjusmentTotal = 0;
$counter = 0;

while($row = $dbCmd->GetRow()){

	$thisOrderID = $row["OrderID"];
	$customerAdjust = $row["CustomerAdjustment"];
	$vendorAdjust = $row["VendorAdjustment"];
	$adjustedByUserID = $row["FromUserID"];
	$Adjst_desc = $row["Description"];
	$Adjustcreated = $row["DateCreated"];
	$ThisVendorID = $row["VendorID"];
	
	$CustomerAdjusmentTotal += $customerAdjust;
	$VendorAdjusmentTotal += $vendorAdjust;
	
	if(!empty($ThisVendorID) && array_key_exists($ThisVendorID, $VendorTotalsArray))
		$VendorTotalsArray[$ThisVendorID]["Adjustments"] += $vendorAdjust;
	
	$counter++;
	
	$empty_adjustments = false;
	
	// Don't go through all of parsing the HTML if the user hasn't chosen to look at the details.
	if(!empty($showadjustments)){
	
		// Get the Vendor name of who the adjustment belongs to
		$adjst_VendorName = "&nbsp;";

		if(!empty($vendorAdjust)){
			$dbCmd2->Query("SELECT Company FROM users WHERE ID=" . $ThisVendorID);
			$adjst_VendorName = $dbCmd2->GetValue();
		}
		
		if(empty($adjustedByUserID))
			$adjustedByUserName = "System";
		else
			$adjustedByUserName = UserControl::GetNameByUserID($dbCmd2, $adjustedByUserID);
			


		// Color red or green for pos or neg
		if(!empty($customerAdjust))
			$customerAdjust = number_format($customerAdjust, 2);
		if(!empty($vendorAdjust))
			$vendorAdjust = number_format($vendorAdjust, 2);

		$Adjustcreated = date ("M. d", $Adjustcreated);

		$t->set_var(array(	"VENDORADUST"=>$vendorAdjust, 
					"CUSTOMERADJUST"=>$customerAdjust, 
					"ADJUSTDATE"=>$Adjustcreated, 
					"ADJUSTBY"=>WebUtil::htmlOutput($adjustedByUserName), 
					"ADJUSTSUMMARY"=>$Adjst_desc, 
					"ADJ_ORDERID"=>$thisOrderID, 
					"V_NAME"=>$adjst_VendorName
				));
		
		if($AuthObj->CheckIfBelongsToGroup("VENDOR")){
			$t->parse("VendorAdjustmentBLout","VendorAdjustmentBL",true);
		}
		else{
			$t->parse("AdminAdjustmentBLout","AdminAdjustmentBL",true);
		}
	
	}
}

// Erase the block if we got no results
if($counter == 0){
	if($AuthObj->CheckIfBelongsToGroup("VENDOR"))
		$t->set_var(array("VendorAdjustmentBLout"=>""));
	else
		$t->set_var(array("AdminAdjustmentBLout"=>""));
}


// Find out if the user has clicked the button to display all Price Adjustments
if(!empty($showadjustments)){

	// Hide the button to display the details, since they have already been displayed
	$t->set_block("origPage","ShowPriceAdjustButtonBL","ShowPriceAdjustButtonBLout");
	$t->set_var(array("ShowPriceAdjustButtonBLout"=>""));
	
	// If they are a vendor, then hide the admin adjustment block and visa versa
	if($AuthObj->CheckIfBelongsToGroup("VENDOR")){
		$t->set_block("origPage","AdminPriceAdjustTableBL","AdminPriceAdjustTableBLout");
		$t->set_var(array("AdminPriceAdjustTableBLout"=>""));
	}
	else{
		$t->set_block("origPage","VendorPriceAdjustTableBL","VendorPriceAdjustTableBLout");
		$t->set_var(array("VendorPriceAdjustTableBLout"=>""));
	}
}
else{

	// Hide the button to hide the Adjustment details
	$t->set_block("origPage","HidePriceAdjustButtonBL","HidePriceAdjustButtonBLout");
	$t->set_var(array("HidePriceAdjustButtonBLout"=>""));

	// Hide the block of HTML for the price adjustments
	$t->set_block("origPage","AdminPriceAdjustTableBL","AdminPriceAdjustTableBLout");
	$t->set_var(array("AdminPriceAdjustTableBLout"=>""));
	
	$t->set_block("origPage","VendorPriceAdjustTableBL","VendorPriceAdjustTableBLout");
	$t->set_var(array("VendorPriceAdjustTableBLout"=>""));
}



$adjustments_profit =  $CustomerAdjusmentTotal - $VendorAdjusmentTotal;


$t->set_var(array(	"TOTALVENDORADJUST"=>Widgets::GetColoredPrice($VendorAdjusmentTotal), 
			"TOTALCUSTOMERADJUST"=>Widgets::GetColoredPrice($CustomerAdjusmentTotal), 
			"ADJUSTMENTS_PROFIT"=>Widgets::GetColoredPrice($adjustments_profit)
		));




// Get the total merchant fees this period
// Get the shipping totals for all shipments completed within this period

$dbCmd->Query("SELECT SUM(CommissionFee) FROM charges INNER JOIN orders ON charges.OrderID = orders.ID 
		WHERE " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()) . " AND 
		(StatusDate BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") ");
$MerchantFees = number_format($dbCmd->GetValue(), 2, '.', '');







// Get the shipping totals for all shipments completed within this period
// We can not get the Value with a JOIN, because there may be many Projects associated with a shipment.
$dbCmd->Query("SELECT DISTINCT shipments.ID FROM 
				(shipments USE INDEX (shipments_DateShipped) INNER JOIN shipmentlink ON shipmentlink.ShipmentID = shipments.ID)
				INNER JOIN projectsordered ON shipmentlink.ProjectID = projectsordered.ID
				WHERE " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()) . " AND 
				(shipments.DateShipped BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") ");
$allShipmentIDs = $dbCmd->GetValueArr();

$FreightCharges = 0;
$PackageWeights = 0;
foreach ($allShipmentIDs as $thisShipID){
	
	$dbCmd->Query("SELECT ShippingCost, PackageWeight FROM shipments WHERE ID=$thisShipID");
	$row = $dbCmd->GetRow();
	$FreightCharges += $row["ShippingCost"];
	$PackageWeights += $row["PackageWeight"];
}




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


$t->set_var("LOYALTY_CHARGES_TOTAL", Widgets::GetColoredPrice($loyaltyCharges));
$t->set_var("LOYALTY_REFUND_TOTALS", Widgets::GetColoredPrice($loyaltyRefunds * -1));
$t->set_var("LOYALTY_BALANCE_TOTALS", Widgets::GetColoredPrice($loyaltyBalance));



	




// Get the Total Amount of payments received within this period
$dbCmd->Query("SELECT SUM(Amount) FROM paymentsreceived INNER JOIN users ON paymentsreceived.CustomerID = users.ID
			WHERE " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()) . " AND 
			(paymentsreceived.Date BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") ");
$TotalPaymentReceived = $dbCmd->GetValue();

$total_shipping_income = $shipping_quotes_total - $FreightCharges;
$gross_sales_total = $tax_total_for_subtotals + $shipping_tax_total + $shipping_quotes_total + $adjustments_profit + $totalRetailCharges + $loyaltyBalance;


$Total_income = $total_shipping_income + $adjustments_profit + $order_income_total - $MerchantFees + $loyaltyBalance;

// prevent division by 0
if($PackageWeights > 0)
	$proffit_per_pound = round($Total_income / $PackageWeights, 2);
else
	$proffit_per_pound = 0;






// Put in the Date Selection Control
$DateSelectObj = new Navigation();
$DateSelectObj->SetBaseURL("./ad_report_month.php");
$t->set_var("DATE_SELECT", $DateSelectObj->GetYearMonthSelectorHTML($month, $year));
$t->allowVariableToContainBrackets("DATE_SELECT");

$t->set_var("DATERANGESELS", Widgets::BuildDateRangeSelect( $start_timestamp, $end_timestamp, "D" ));
$t->allowVariableToContainBrackets("DATERANGESELS");

$t->set_var(array("MESSAGE"=>$MonthStatus, "NUMBER_REPRINTS"=>$total_reprints, "NUMBER_ORDERS"=>$total_orders, "NUMBER_PROJECTS"=>$total_projects));

$t->set_var(array("SUM_PROJECT_INCOME"=>Widgets::GetColoredPrice($project_income_total)));
$t->set_var(array("SUM_P_V_SUBTOTALS"=>(Widgets::GetColoredPrice($sum_project_vendor_subtotals))));
$t->set_var(array("SUM_CUS_SUBTOTALS"=>(Widgets::GetColoredPrice($project_customer_subtotal_total))));


$t->set_var(array("ORDER_TOTALS_INVOICED"=>Widgets::GetColoredPrice($order_totals_invoiced)));
$t->set_var(array("PROJECT_INCOME_INVOICED"=>(Widgets::GetColoredPrice($project_income_total_invoiced))));
$t->set_var(array("OFFLINE_PAYMENTS"=>(Widgets::GetColoredPrice($TotalPaymentReceived))));




$t->set_var(array("RP_SUM_ORDERINCOME"=>(Widgets::GetColoredPrice($reprint_income_total))));
$t->set_var(array("RP_SUM_CUS_SUBTOTALS"=>(Widgets::GetColoredPrice($sum_reprint_customer_subtotals))));
$t->set_var(array("RP_SUM_V_SUBTOTALS"=>(Widgets::GetColoredPrice($sum_reprint_vendor_subtotals))));




$t->set_var(array("MERCHANT_FEES"=>(Widgets::GetColoredPrice($MerchantFees))));

$t->set_var(array("GROSS_SALES"=>Widgets::GetColoredPrice($gross_sales_total)));

$t->set_var(array(
		"TOTAL_RETAIL_SALES_NONTAXABLE"=>Widgets::GetColoredPrice($totalRetailChargesNonTaxable + $shipping_quotes_total_nontaxable),
		"TOTAL_RETAIL_SALES_TAXABLE"=>Widgets::GetColoredPrice($totalRetailChargesTaxable + $shipping_quotes_total_taxable)
		));

$t->set_var(array(
	"TOTAL_SHIPPING_CHARGES"=>Widgets::GetColoredPrice($shipping_quotes_total),
	"FREIGHT_CHARGES"=>Widgets::GetColoredPrice($FreightCharges),
	"SHIPPING_INCOME"=>Widgets::GetColoredPrice($total_shipping_income),

	"PROFFIT_PER_POUND"=>Widgets::GetColoredPrice($proffit_per_pound)
	));


$All_Vendor_Totals = 0;

// We are going to create the HTML block for displaying the sum of all charges for each Vendor individually
if(!$empty_projects || !$empty_reprints || !$empty_adjustments){

	// We created a 2 dimmensional array above.  The first key is unique for every different vendor on this summary page.  The 2nd dimession is totals of each of the vendor components.
	$t->set_block("origPage","vendorSummaryBL","vendorSummaryBLout");

	foreach($VendorTotalsArray as $VendorID => $VendorDetailsArr){
		$t->set_var("VENDOR_NAME", $VendorDetailsArr["VendorName"]);
		$t->set_var("SUM_VENDOR_ADJMTS", Widgets::GetColoredPrice($VendorDetailsArr["Adjustments"]));
		$t->set_var("SUM_VENDOR_SUBTOTALS", (Widgets::GetColoredPrice($VendorDetailsArr["Totals"])));
		$t->set_var("SUM_VENDOR_REPRINTS", (Widgets::GetColoredPrice($VendorDetailsArr["ReprintTotals"])));
		$t->set_var("SUM_VENDOR_PRICES", (Widgets::GetColoredPrice($VendorDetailsArr["ReprintTotals"] + $VendorDetailsArr["Totals"] + $VendorDetailsArr["Adjustments"])));

		$All_Vendor_Totals += $VendorDetailsArr["ReprintTotals"] + $VendorDetailsArr["Totals"] + $VendorDetailsArr["Adjustments"];

		$t->parse("vendorSummaryBLout","vendorSummaryBL",true);
	}
	
	if(empty($VendorTotalsArray))
		$t->set_var("vendorSummaryBLout", "");
}

$t->set_var(array("ALL_VENDOR_TOTALS"=>(Widgets::GetColoredPrice($All_Vendor_Totals))));



// Find out what our Sales Commission Totals are
if(!$AuthObj->CheckIfBelongsToGroup("VENDOR")){
	
	// Set the User to Sales Master
	// Sales commissions are Domain-Specific, so we have to get the total in a loop based upon our selected Domain IDs.
	$SalesRepsCustomerSubtotals = 0;
	$SalesRepsVendorSubtotals = 0;
	$TotalSalesCommissionsEarned = 0;
	
	foreach($domainObj->getSelectedDomainIDs() as $thisDomainID){
	
		$SalesCommissionsObj = new SalesCommissions($dbCmd, $thisDomainID);
		$SalesCommissionsObj->SetUser(0);
		$SalesCommissionsObj->SetDateRangeByTimeStamp($start_timestamp, $end_timestamp);
	
		$SalesRepsCustomerSubtotals += $SalesCommissionsObj->GetOrderTotalsWithinPeriod(0, true, "CustomerSubotals");
		$SalesRepsVendorSubtotals += $SalesCommissionsObj->GetOrderTotalsWithinPeriod(0, true, "VendorSubtotals");
		$TotalSalesCommissionsEarned += $SalesCommissionsObj->GetTotalCommissionsWithinPeriodForUser(0, "All", "GoodAndSuspended");
	}
	
	$t->set_var("SALESREPS_TOTAL_COMMISSIONS", Widgets::GetColoredPrice($TotalSalesCommissionsEarned));
	$t->set_var("SALESREPS_CUSTOMER_SUBTOTALS", Widgets::GetColoredPrice($SalesRepsCustomerSubtotals));
	$t->set_var("SALESREPS_VENDOR_SUBTOTALS", Widgets::GetColoredPrice($SalesRepsVendorSubtotals));
	$t->set_var("SALESREPS_INCOME", Widgets::GetColoredPrice($SalesRepsCustomerSubtotals - $SalesRepsVendorSubtotals - $TotalSalesCommissionsEarned));

	$Total_income -= $TotalSalesCommissionsEarned;
}


$t->set_var("TOT_VENDORADJMTS", Widgets::GetColoredPrice($VendorAdjusmentTotal));
$t->set_var("TOTAL_SHIPPING_INCOME", $total_shipping_income);
$t->set_var("TOTAL_TAXES", Widgets::GetColoredPrice($tax_total_for_subtotals + $shipping_tax_total));
$t->set_var("TOTAL_INCOME", Widgets::GetColoredPrice($Total_income));




// Find out if they are trying to view a month in the future.. If not then check if there are empty orders.
if ($month > $current_month && $year == $current_year){
	$t->set_block("origPage","EmptyOrdersBL","EmptyOrdersBLout");
	$t->set_var(array("EmptyOrdersBLout"=>"<font class='LargeBody'>How can you view orders in the future?  This website is not a fortune teller.</font>"));

}
else{

	// If they are a vendor, then take away the ability to see Vendor summars and company reports
	if($AuthObj->CheckIfBelongsToGroup("VENDOR")){
		$t->set_block("origPage","HideVendorBlocksBL","HideVendorBlocksBLout");
		$t->set_var("HideVendorBlocksBLout", "");

		$t->set_block("origPage","HideAdminReportsBL","HideAdminReportsBLout");
		$t->set_var("HideAdminReportsBLout", "");
		
		$t->set_block("origPage","HideAdminReportsBL2","HideAdminReportsBL2out");
		$t->set_var("HideAdminReportsBL2out", "");
		
		
		
		
		// Put the total revenue for the period for this vendor
		$TotalVendorRevenue = $sum_project_vendor_subtotals + $sum_reprint_vendor_subtotals + $VendorAdjusmentTotal;
		$t->set_var("VENDOR_REVENUE", Widgets::GetColoredPrice($TotalVendorRevenue));
		$t->set_var("VENDOR_REPRINT_REVENUE", Widgets::GetColoredPrice($sum_reprint_vendor_subtotals));
		$t->set_var("VENDOR_PRICE_ADJUSTMENTS", Widgets::GetColoredPrice($VendorAdjusmentTotal));
		$t->set_var("VENDOR_PROJECT_REVENUE", Widgets::GetColoredPrice($sum_project_vendor_subtotals));
		
	}
	else{

		// Erase Vendor Specific Information... the administrator has more details anyway.
		$t->set_block("origPage","HideVendorReportsBL","HideVendorReportsBLout");
		$t->set_var("HideVendorReportsBLout", "");

	}

	if($empty_projects && $empty_reprints && $empty_adjustments){
		$t->set_block("origPage","EmptyOrdersBL","EmptyOrdersBLout");
		$t->set_var(array("EmptyOrdersBLout"=>"<font class='LargeBody'><br><br>There were no orders in this period.<br><br></font>"));
	}
}




// Now look for any charges that have been canceled this period.  We really should never see a canceled charge
// One possibility is that the customer cancels his credit card after the order is placed... and then we later try to issue a refund --#
//  If the charge has errors and we can't get it to go through.. then we may need to cancel the charge... and therefore we need see a record --#


$t->set_block("origPage","chargesBL","chargesBLout");

$foundCharge = false;

if($AuthObj->CheckForPermission("CREDIT_CANCELATIONS")){

	//All charges with a canceled status	
	$dbCmd->Query("SELECT charges.ID AS ChargeID, OrderID, ChargeType, Status, ResponseReasonText, 
			UNIX_TIMESTAMP(StatusDate) AS StatusDate, ChargeRetries 
			FROM charges USE INDEX (charges_StatusDate) INNER JOIN orders ON charges.OrderID = orders.ID WHERE 
			" . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()) . " AND 
			Status =\"X\" AND charges.StatusDate 
			BETWEEN  " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp);


	while ($row = $dbCmd->GetRow()){

		$ChargeID = $row["ChargeID"];
		$OrderID = $row["OrderID"];
		$ChargeType = $row["ChargeType"];
		$Status = $row["Status"];
		$ResponseReason = $row["ResponseReasonText"];
		$StatusDate = $row["StatusDate"];
		$Retries = $row["ChargeRetries"];

		$foundCharge = true;

		// Convert the chargeType character into english
		if($ChargeType == "R")
			$t->set_var("CHARGE_TYPE", "Refund");
		else if($ChargeType == "C")
			$t->set_var("CHARGE_TYPE", "Capture");
		else
			$t->set_var("CHARGE_TYPE", "Unknown");
	

		$StatusDesc = "<b>Last Error:</b> " .  WebUtil::htmlOutput($ResponseReason);

		$t->set_var(array(
			"ERROR_DATE"=>date("m-j H:i", $StatusDate), 
			"ORDERNO"=>$OrderID,
			"ERROR_DESC"=>$StatusDesc,
			"RETRIES"=>$Retries, 
			"CHARGE_ID"=>$ChargeID 
			));

		$t->parse("chargesBLout","chargesBL",true);
	}
}

if(!$foundCharge){
	$t->set_block("origPage","HideChargesBL","HideChargesBLout");
	$t->set_var(array("HideChargesBLout"=>""));
}




// Cache the Report For later use.
$reportHTML = $t->finish($t->parse("OUT","origPage"));

$reportHTMLcompressed = gzcompress($reportHTML);

$fp = fopen($reportCachedFileName, "w");
fwrite($fp, $reportHTMLcompressed);
fclose($fp);



if(!empty($cacheReportForUser)){
	print "The Report has been successfully cached on behalf of UserID: " . $cacheReportForUser;
}
else{
	print $reportHTML;
}








?>
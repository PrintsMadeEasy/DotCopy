<?

require_once("library/Boot_Session.php");


// Don't make it look like we are favoring a particular Domain 
Domain::removeTopDomainID();


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

set_time_limit(3000);


$domainObj = Domain::singleton();

// The marketing report should not favor any particular domain.
Domain::removeTopDomainID();


$start_timestamp = WebUtil::GetInput("starttimestamp", FILTER_SANITIZE_INT);
$end_timestamp = WebUtil::GetInput("endtimestamp", FILTER_SANITIZE_INT);


$start_timestamp_mysql = date("YmdHis", $start_timestamp);
$end_timestamp_mysql = date("YmdHis", $end_timestamp);


$t = new Templatex(".");

$t->set_file("origPage", "ad_report_production-template.html");

$t->set_block("origPage","ProductBL","ProductBLout");


$t->set_var("DATE_RANGE",  date("F j, Y, g:i a", $start_timestamp) . " - " . date("F j, Y, g:i a", $end_timestamp));



// Get all Products in users Selected Domains.
$productIDarr = Product::getActiveProductIDsInUsersSelectedDomains();

	
$productIDarr = array_unique($productIDarr);
	
$grandTotalPageCounts = 0;
$grandTotalUnitQuantityCount = 0;
$grandTotalPageImpressions = 0;
$grandTotalProjectCount = 0;


foreach($productIDarr as $thisProductID){

	$thisProductName = Product::getFullProductName($dbCmd, $thisProductID);
	

	$totalProjectCountForProduct = 0;
	$totalUnitQuantityForProduct = 0;
	$totalPageCountsForProduct = 0;
	$totalPageImpressionsForProduct = 0;


	// Only get Projects which have been Finished... so we know that a PDF profile has been generated.
	$query = "SELECT PO.ID AS ProjRecord, PO.Quantity AS Quantity FROM projectsordered AS PO INNER JOIN orders on orders.ID = PO.OrderID 
			WHERE " . DbHelper::getOrClauseFromArray("orders.DomainID", $domainObj->getSelectedDomainIDs()) . " AND 
			PO.ProductID = $thisProductID AND PO.Status != 'C' AND ( orders.DateOrdered BETWEEN $start_timestamp_mysql AND $end_timestamp_mysql ) ";
	if($AuthObj->CheckIfBelongsToGroup("VENDOR"))
		$query .= " AND (" . Product::GetVendorIDLimiterSQL($UserID) . ") ";

	$dbCmd->Query($query);
			

	// Skip Products that do not have any projects ordered.
	if($dbCmd->GetNumRows() == 0)
		continue;
		
	while($row = $dbCmd->GetRow()){

		$projectRecord = $row["ProjRecord"];
		$projectQuantity = $row["Quantity"];
	
		$totalProjectCountForProduct++;
		
		$totalUnitQuantityForProduct += $projectQuantity;

		// Keep the PDF Profile blank so that it will use whatever PDF profile was saved for the Project.
		$defaultPDFprofileName = null;

		$projectSheetCount = PdfDoc::getPageCountsByProjectList($dbCmd2, array($projectRecord), $defaultPDFprofileName, "SheetCount");
		$totalPageCountsForProduct += $projectSheetCount;
		
		// That is used to tell us how many page impressions there are... for example... double-sided business cards get 2 impressions for each sheet.
		$projectImpressionCount = PdfDoc::getPageCountsByProjectList($dbCmd2, array($projectRecord), $defaultPDFprofileName, "ImpressionCount");
		$totalPageImpressionsForProduct += $projectImpressionCount;
	}
	
	
	// Add to the Grand totals
	$grandTotalPageCounts += $totalPageCountsForProduct;
	$grandTotalUnitQuantityCount += $totalUnitQuantityForProduct;
	$grandTotalProjectCount += $totalProjectCountForProduct;
	$grandTotalPageImpressions += $totalPageImpressionsForProduct;
	
	$t->set_var("PRODUCT_NAME",  WebUtil::htmlOutput($thisProductName));
	$t->set_var("PROJ_COUNT",  number_format($totalProjectCountForProduct, 0));
	$t->set_var("UNIT_COUNT",  number_format($totalUnitQuantityForProduct, 0));
	$t->set_var("PRODUCT_SHEETCOUNTS",  number_format($totalPageCountsForProduct, 0));
	$t->set_var("PRODUCT_IMPRESSIONS",  number_format($totalPageImpressionsForProduct, 0));

	$t->parse("ProductBLout","ProductBL",true);
}


// If there are not projects for any products then show an "empty" message.... otherwise show the grand totals.
if($grandTotalPageCounts == 0){
	$t->set_var("ProductBLout", "<tr><td class='body'>No orders during this period.</td></tr>");
}
else{

	$t->set_var("PRODUCT_NAME",  "<b>Totals</b>");
	$t->set_var("PROJ_COUNT",  "<b>" . number_format($grandTotalProjectCount, 0) . "</b>");
	$t->set_var("UNIT_COUNT",  "<b>" . number_format($grandTotalUnitQuantityCount, 0) . "</b>");
	$t->set_var("PRODUCT_SHEETCOUNTS",  "<b>" . number_format($grandTotalPageCounts, 0) . "</b>");
	$t->set_var("PRODUCT_IMPRESSIONS",  "<b>" . number_format($grandTotalPageImpressions, 0) . "</b>");
	
	$t->allowVariableToContainBrackets("PRODUCT_NAME");
	$t->allowVariableToContainBrackets("PROJ_COUNT");
	$t->allowVariableToContainBrackets("UNIT_COUNT");
	$t->allowVariableToContainBrackets("PRODUCT_SHEETCOUNTS");
	$t->allowVariableToContainBrackets("PRODUCT_IMPRESSIONS");
	

	$t->parse("ProductBLout","ProductBL",true);

}


$t->pparse("OUT","origPage");


?>
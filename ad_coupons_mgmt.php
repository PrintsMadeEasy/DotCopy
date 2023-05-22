<?

require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();



if(!$AuthObj->CheckForPermission("COUPONS_VIEW"))
	WebUtil::PrintAdminError("Not Available");



	
// Multi-select HTML list menu creates an array.
$productIDsSearchArr = WebUtil::GetInputArr("productIDsSearch", FILTER_SANITIZE_INT);

$StartMo = WebUtil::GetInput("StartMo", FILTER_SANITIZE_INT);
$EndMo = WebUtil::GetInput("EndMo", FILTER_SANITIZE_INT);
$StartYr = WebUtil::GetInput("StartYr", FILTER_SANITIZE_INT);
$EndYr = WebUtil::GetInput("EndYr", FILTER_SANITIZE_INT);


// If they have selected "All" as a choice... then just pretend that there is no product ID's selected
if(sizeof($productIDsSearchArr) == 1 && $productIDsSearchArr[0] == "all")
	$productIDsSearchArr = array();


$CouponObj = new Coupons($dbCmd);


//Load template and add set header var
$t = new Templatex(".");
$t->set_file("origPage", "ad_coupons_mgmt-template.html");
$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$ErrorMessage = null;

//Establish Report Parameters


if(!$AuthObj->CheckForPermission("COUPONS_EDIT")){
	$t->discard_block( "origPage", "HideAddCouponBtnBL");
	$t->discard_block( "origPage", "ManageCategoriesButtonBL");
}



//Establish Reporting Period
$ReportPeriodIsDateRange = WebUtil::GetInput( "PeriodType", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES ) == "DATERANGE";
if( $ReportPeriodIsDateRange )
{
	
	$ReportStartDate = mktime(0, 0, 0, $StartMo, 1, $StartYr);
	$ReportEndDate = mktime(23, 59, 59, $EndMo+1, 0, $EndYr);
	
	if( $ReportEndDate < $ReportStartDate )
		$ErrorMessage = "Unclear Reporting Period Selected";
	else if( $ReportStartDate > time() )
		$ErrorMessage = "Predicting the future is not possible!";

	
	//Default/Reset date frame selection
	$TimeFrameSel = "ALLTIME";
}
else //Period is TIMEFRAME
{
	//If timeframe is not specified, assume "ALLTIME"
	$TimeFrameSel = WebUtil::GetInput( "TimeFrame", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES );
	if( $TimeFrameSel == null )
		$TimeFrameSel = "ALLTIME";
	
	$ReportPeriod = Widgets::GetTimeFrame( $TimeFrameSel );
	$ReportStartDate = $ReportPeriod[ "STARTDATE" ];
	$ReportEndDate = $ReportPeriod[ "ENDDATE" ];	
	
	//Default/Reset date selection range to current month, year
	$StartMo = $EndMo = date("n");
	$StartYr = $EndYr = date("Y");	
}


// So that the user can maintain their category selection
$defaultCouponCategoryID = WebUtil::GetCookie("DefaultCouponCategory");

$CategorySel = WebUtil::GetInput( "CategorySel", FILTER_SANITIZE_INT,  $defaultCouponCategoryID); 
$ShowExpiredCoupons = WebUtil::GetInput( "ShowExpired", FILTER_SANITIZE_STRING_ONE_LINE );
$SortColumn = WebUtil::GetInput( "SortColumn", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "DiscountPercent" );


// If the current category that we are viewing is different than the "Default"...
// Then set a cookie to remember this preference.
if($CategorySel != $defaultCouponCategoryID){
	$cookieTime = time()+60*60*24*90; // 3 months
	setcookie ("DefaultCouponCategory", $CategorySel, $cookieTime);
}


//Setup reporting criteria vars in form
$t->set_var( "MESSAGE", $ErrorMessage ? "Error: " . $ErrorMessage : null );	

$t->set_var( "PERIODTYPETIMEFRAME", $ReportPeriodIsDateRange ? null : "CHECKED" );
$t->set_var( "TIMEFRAMESELS", Widgets::BuildTimeFrameSelect( $TimeFrameSel ));
$t->set_var( "PERIODISTIMEFRAME", $ReportPeriodIsDateRange ? "false" : "true" );

$t->set_var( "PERIODTYPEDATERANGE", $ReportPeriodIsDateRange ? "CHECKED" : null );
$t->set_var( "STARTMOSELS", Widgets::BuildMonthSelect( $StartMo, "StartMo" ));
$t->set_var( "STARTYRSELS", Widgets::BuildYearSelect( $StartYr, "StartYr" ));
$t->set_var( "ENDMOSELS", Widgets::BuildMonthSelect( $EndMo, "EndMo" ));
$t->set_var( "ENDYRSELS", Widgets::BuildYearSelect( $EndYr, "EndYr" ));

$t->allowVariableToContainBrackets("STARTMOSELS");
$t->allowVariableToContainBrackets("STARTYRSELS");
$t->allowVariableToContainBrackets("ENDMOSELS");
$t->allowVariableToContainBrackets("ENDYRSELS");
$t->allowVariableToContainBrackets("TIMEFRAMESELS");

$Categories = Coupons::getCouponCategoriesList($dbCmd);
$Categories = array("0"=>"All Categories") + $Categories;
$t->set_var( "CATEGORYSELS", Widgets::buildSelect($Categories, array( $CategorySel ), "CategorySel" ));

$t->allowVariableToContainBrackets("CATEGORYSELS");

// Get a list of all products in the system
$productIDselection = array("all"=>"Any/All Coupons");
$allProductIDarr = Product::getActiveProductIDsArr($dbCmd, Domain::oneDomain());
foreach($allProductIDarr as $thisProdID){
	$productObj = Product::getProductObj($dbCmd, $thisProdID);
	$productIDselection["$thisProdID"] = $productObj->getProductTitleWithExtention();
}

// If we don't have any product ID's for this coupon, then it means it works on all products.
if(empty($productIDsSearchArr))
	$ProductsSelections = Widgets::buildSelect( $productIDselection, array("all") );
else
	$ProductsSelections = Widgets::buildSelect( $productIDselection, $productIDsSearchArr );

$t->set_var( "PRODUCTS_SELECT",  $ProductsSelections);
$t->allowVariableToContainBrackets("PRODUCTS_SELECT");


$SortChar = "<font class='largebody'><b>*</b></font>";
$t->set_var( "SORT_CODE", $SortColumn == "Code" ? $SortChar : null );
$t->set_var( "SORT_NAME", $SortColumn == "Name" ? $SortChar : null );
$t->set_var( "SORT_ACTREQ", $SortColumn == "ActReq" ? $SortChar : null );
$t->set_var( "SORT_DISCOUNT", $SortColumn == "DiscountPercent" ? $SortChar : null );
$t->set_var( "SORT_EXPIRE", $SortColumn == "ExpireDate" ? $SortChar : null );
$t->set_var( "SORT_USAGELIMIT", $SortColumn == "UsageLimit" ? $SortChar : null );
$t->set_var( "SORT_USAGE", $SortColumn == "Uses" ? $SortChar : null );
$t->set_var( "SORTCOLUMN", $SortColumn );

$t->allowVariableToContainBrackets("SORT_CODE");
$t->allowVariableToContainBrackets("SORT_NAME");
$t->allowVariableToContainBrackets("SORT_ACTREQ");
$t->allowVariableToContainBrackets("SORT_DISCOUNT");
$t->allowVariableToContainBrackets("SORT_EXPIRE");
$t->allowVariableToContainBrackets("SORT_USAGELIMIT");
$t->allowVariableToContainBrackets("SORT_USAGE");
$t->allowVariableToContainBrackets("SORTCOLUMN");


$t->set_block( "origPage", "ListCouponLineBL", "_ListCouponLineBL" );	
$t->set_block( "ListCouponLineBL", "ListCategoryLineBL", "_ListCategoryLineBL" );
$t->set_block( "ListCouponLineBL", "EditCouponButtonBL1", "_EditCouponButtonBL1" );
$t->set_block( "ListCouponLineBL", "EditCouponButtonBL2", "_EditCouponButtonBL2" );
$t->set_block( "ListCouponLineBL", "OptionNamesBl", "_OptionNamesBl");
$t->set_block( "ListCouponLineBL", "EmptyProductOptionsBL", "_EmptyProductOptionsBL" );
$t->set_block( "ListCouponLineBL", "NoDiscountOptionNamesBl", "_NoDiscountOptionNamesBl");
$t->set_block( "ListCouponLineBL", "EmptyNoDiscountProductOptionsBL", "_EmptyNoDiscountProductOptionsBL" );



$DBStartDate = DbCmd::FormatDBDateTime($ReportStartDate);
$DBEndDate = DbCmd::FormatDBDateTime($ReportEndDate);



$QCatClause = ( $CategorySel ) ? " WHERE cp.CategoryID = $CategorySel" : null;	
$QSelect = "SELECT cp.ID, Code, IsActive, cp.Name AS Name, cp.ActReq, cp.CategoryID, cp.UsageLimit, cp.WithinFirstOrders, cp.ExpireDate, Comments, MinimumSubtotal, MaximumSubtotal, ProjectMinQuantity, ProjectMaxQuantity,  
			cp.DiscountPercent, cc.Name AS CatName, cp.MaxAmount, cp.ShippingDiscount, cp.SalesLink, cp.MaxAmountType 
		FROM coupons AS cp
		JOIN couponcategories as cc ON cp.CategoryID = cc.ID
		$QCatClause AND cp.DomainID=".Domain::oneDomain()."
		ORDER BY cp.CategoryID, cp.$SortColumn";

$CategoryID = 0;	
$dbCmd->Query( $QSelect );
$dbCmd1 = new DbCmd();

$couponResultFoundFlag = false;

while( $row = $dbCmd->GetRow()  )
{
	$CouponID = $row[ "ID" ];


	// If we are limiting the results by Product
	$couponObj = new Coupons($dbCmd2);
	$couponObj->LoadCouponByID($CouponID);
	$productIDforThisCouponArr = $couponObj->GetProductIDs();
	$productIDbundleArr = $couponObj->GetBundeledProductIDs();
	
	if(!empty($productIDsSearchArr)){
	
		// If we are searching by Products for the coupon... and the coupon is not limited to a product
		// then there is no reason to do any checking, it is obvious nothing will be found
		if(empty($productIDforThisCouponArr))
			continue;
		
		$productIDmatchFoundFlag = false;
		foreach($productIDsSearchArr as $thisProductIDtoSearch){
		
			if(in_array($thisProductIDtoSearch, $productIDforThisCouponArr)){
				$productIDmatchFoundFlag = true;
				break;
			}	
		}
		
		// Skip this coupon if there is no matches from the search criteria with the products required in the coupon data
		if(!$productIDmatchFoundFlag)
			continue;
	}


	$QSelect = "SELECT COUNT(DISTINCT os.ID)
			FROM orders AS os
			WHERE os.CouponID = $CouponID 
				AND os.DateOrdered BETWEEN '$DBStartDate' AND '$DBEndDate'";
	$dbCmd1->Query( $QSelect );
	$Uses = $dbCmd1->GetValue();
	
	//Build category line if new category
	if( $row[ "CategoryID" ] !=  $CategoryID )
	{
		$t->set_var( "CATEGORY", $row[ "CatName" ] );
		$t->parse( "_ListCategoryLineBL",  "ListCategoryLineBL" );
		$CategoryID = $row[ "CategoryID" ];
	}
	else
		$t->set_var( "_ListCategoryLineBL", null );	


	$ExpireDate = $row[ "ExpireDate" ] ? strtotime( $row[ "ExpireDate" ] ) : 0;

	// If the Coupon is associated with a Sales Rep... then make it appear bold
	if($row[ "SalesLink" ])

		$t->set_var( "CODE_DISP", "<b>" . WebUtil::htmlOutput($row[ "Code" ]) . "</b>");
	else
		$t->set_var( "CODE_DISP", WebUtil::htmlOutput($row[ "Code" ]) );
		
	$t->allowVariableToContainBrackets("CODE_DISP");
	
	$CodeColor = "black";
	if( $ExpireDate  &&  $ExpireDate < time() )
		$CodeColor = "#CC0000";
	elseif( !$row[ "IsActive" ] )
		$CodeColor = "#999999";
	$t->set_var( "CODECOLOR", $CodeColor );		
	

	
	$t->set_var( "CODE", $row[ "Code" ] );
	$t->set_var( "NAME", $row[ "Name" ] ? $row[ "Name" ] : $row[ "CatName" ]  );
	
	
	if($row["Comments"] != "")
		$t->set_var( "COMMENTS", '<br><img src="./images/transparent.gif" width="7" height="7"><br><font class="ReallySmallBody">' . WebUtil::htmlOutput($row["Comments"]) . '</font>' );
	else
		$t->set_var( "COMMENTS", "");
	

	$t->allowVariableToContainBrackets("COMMENTS");
	
	if($row[ "DiscountPercent"] == 0)

		$t->set_var( "DISC", "No subtotal discount");
	else
		$t->set_var( "DISC", $row[ "DiscountPercent" ] . "% discount");
	

	
	// Maximum Discount (only display if not Zero)
	if($row["MaxAmount"] != 0){
		
		$maxAmountDesc = '<br>Max: $' . number_format($row[ "MaxAmount" ], 2, '.', '');
		
		if($row["MaxAmountType"] == "O")
			$maxAmountDesc .= " (per order)";
		else if($row["MaxAmountType"] == "P")
			$maxAmountDesc .= " (per project)";
		else if($row["MaxAmountType"] == "T")
			$maxAmountDesc .= " (per quantity)";
		else
			throw new Exception("Illegal Max Amount Type");
		
		$t->set_var( "MAX_AMOUNT",  $maxAmountDesc);
		$t->allowVariableToContainBrackets("MAX_AMOUNT");
	}
	else{
		$t->set_var( "MAX_AMOUNT", "");
	}
	
	
	// Shipping Discount (only display if not Zero)
	if($row["ShippingDiscount"] != 0){
		$SHDesc = '<br>S&amp;H: $' . number_format($row[ "ShippingDiscount" ], 2, '.', '');
		$t->set_var( "SH_DISCOUNT", $SHDesc );
		$t->allowVariableToContainBrackets("SH_DISCOUNT");
	}
	else{
		$t->set_var( "SH_DISCOUNT", "" );
	}
	
	
	$t->set_var( "USAGELIMIT", WebUtil::htmlOutput($couponObj->GetUsageDescription()) );
	

	if(empty($row["ExpireDate"]))
		$t->set_var( "EXPIRE", "" );
	else
		$t->set_var( "EXPIRE", "<br>Expires: " . date( "n/j/y", $ExpireDate ));

	$t->set_var( "ACTREQ", $row[ "ActReq" ] ? "<b>Yes</b>" : "No" );	

	$t->allowVariableToContainBrackets("EXPIRE");
	$t->allowVariableToContainBrackets("ACTREQ");
	

	if(empty($row["MinimumSubtotal"]))
		$t->set_var( "MIN_SUBTOTAL", "" );
	else
		$t->set_var( "MIN_SUBTOTAL", '<br><img src="./images/transparent.gif" width="7" height="7"><br><font class="ReallySmallBody"><font color="#000000">Minimum Subtotal Required: </font></font> $' . $row["MinimumSubtotal"] );
	

	if(empty($row["MaximumSubtotal"]))
		$t->set_var( "MAX_SUBTOTAL", "" );
	else
		$t->set_var( "MAX_SUBTOTAL", '<br><img src="./images/transparent.gif" width="7" height="7"><br><font class="ReallySmallBody"><font color="#000000">Maximum Subtotal Allowed: </font></font> $' . $row["MaximumSubtotal"] );
	

	if(empty($row["ProjectMinQuantity"]))
		$t->set_var( "MIN_QUANTITY", "" );
	else
		$t->set_var( "MIN_QUANTITY", '<br><img src="./images/transparent.gif" width="7" height="7"><br><font class="ReallySmallBody"><font color="#000000">Minimum Project Quantity: </font></font>' . $row["ProjectMinQuantity"] );
	
	
	if(empty($row["ProjectMaxQuantity"]))
		$t->set_var( "MAX_QUANTITY", "" );
	else
		$t->set_var( "MAX_QUANTITY", '<br><img src="./images/transparent.gif" width="7" height="7"><br><font class="ReallySmallBody"><font color="#000000">Maximum Project Quantity: </font></font>' . $row["ProjectMaxQuantity"] );
	
	
	if($Uses > 0)
		$t->set_var( "USAGE", "<a href='javascript:ShowCouponUsage(\"". $row[ "Code" ] . "\")'>$Uses</a>" );
	else
		$t->set_var( "USAGE", 0 );

		
	$t->allowVariableToContainBrackets("MIN_SUBTOTAL");
	$t->allowVariableToContainBrackets("MAX_SUBTOTAL");
	$t->allowVariableToContainBrackets("MIN_QUANTITY");
	$t->allowVariableToContainBrackets("MAX_QUANTITY");
	$t->allowVariableToContainBrackets("USAGE");
	
	
	if($AuthObj->CheckForPermission("COUPONS_EDIT")){
		$t->parse( "_EditCouponButtonBL1",  "EditCouponButtonBL1" );
		$t->parse( "_EditCouponButtonBL2",  "EditCouponButtonBL2" );
	}
	else{
		$t->set_var( "_EditCouponButtonBL1", "&nbsp;" );
		$t->set_var( "_EditCouponButtonBL2", "&nbsp;" );
	}
	
	
	// Find out if the coupon is restricted to only work on certain products.
	if(empty($productIDforThisCouponArr)){
		$t->set_var( "PRODUCT_DETAILS", "Works with all products" );
	}
	else{
		
		$productNamesHash = Product::getFullProductNamesHash($dbCmd1, Domain::oneDomain(), true);
		
		$productDesc = "<b><font color='#666666'>Only works with...</font></b>";
		
		foreach($productIDforThisCouponArr as $thisProductID){
			if(!isset($productNamesHash[$thisProductID]))
				$productDesc = "<font color='#FF0000'>Inactive Product ID:</font> " . $thisProductID;
			else	
				$productDesc .= "<br>" . WebUtil::htmlOutput($productNamesHash[$thisProductID]);
		}
	
		$t->set_var( "PRODUCT_DETAILS", $productDesc );
		$t->allowVariableToContainBrackets("PRODUCT_DETAILS");
	}
	

	// Find out if a bundle of products is needed in the shopping cart before the coupon will work
	if(empty($productIDbundleArr)){
		$t->set_var( "PRODUCT_BUNDLE", "" );
	}
	else{
		
		$rootProductNamesHash = Product::getMainProductNamesHash($dbCmd1, Domain::oneDomain());
		
		$productDesc = '<br><img src="./images/transparent.gif" width="7" height="7"><br><b><font color="#666666">Bundle Required...</font></b>';
		
		foreach($productIDbundleArr as $thisProductID){
			if(!Product::checkIfProductIDisActive($dbCmd1, $thisProductID))
				$productDesc .= "<br><font color='#FF0000'>InActive: " . WebUtil::htmlOutput(Product::getFullProductName($dbCmd1, $thisProductID)); 
			else
				$productDesc .= "<br>" . WebUtil::htmlOutput(Product::getRootProductName($dbCmd1, $thisProductID)); 
		}
	
		$t->set_var( "PRODUCT_BUNDLE", $productDesc );
		$t->allowVariableToContainBrackets("PRODUCT_BUNDLE");
	}

	
	// If the Coupon has any Product Options associated with it then list them in a table.
	$productOptionsArr = $couponObj->GetProductOptionsArr();
	if(!empty($productOptionsArr)){	
		
		// Clear the slate for the inner block.
		$t->set_var( "_OptionNamesBl", "");

		foreach($productOptionsArr as $thisOptionName => $thisChoicesArr){

			$choicesListHTML = "";
			foreach($thisChoicesArr as $thisChoice)
				$choicesListHTML .= "� " . WebUtil::htmlOutput($thisChoice) . "<br>";

			// Get rid of the last line break.
			if(!empty($choicesListHTML))
				$choicesListHTML = substr($choicesListHTML, 0, -4);

			$t->set_var( "OPTION_NAME", WebUtil::htmlOutput($thisOptionName));
			$t->set_var( "OPTION_CHOICES", $choicesListHTML);
			
			$t->allowVariableToContainBrackets("OPTION_CHOICES");

			$t->parse("_OptionNamesBl","OptionNamesBl", true);
		}
		
		$t->parse("_EmptyProductOptionsBL", "EmptyProductOptionsBL", false);

	}
	else{
		$t->set_var( "_EmptyProductOptionsBL", "");
	}





	$noDiscountsOptionsArr = $couponObj->GetNoDiscountOnProductOptionsArr(false);
	if(!empty($noDiscountsOptionsArr)){	
		
		// Clear the slate for the inner block.
		$t->set_var( "_NoDiscountOptionNamesBl", "");

		foreach($noDiscountsOptionsArr as $thisOptionName => $thisChoicesArr){

			$choicesListHTML = "";
			foreach($thisChoicesArr as $thisChoice)
				$choicesListHTML .= "� " . WebUtil::htmlOutput($thisChoice) . "<br>";

			// Get rid of the last line break.
			if(!empty($choicesListHTML))
				$choicesListHTML = substr($choicesListHTML, 0, -4);

			$t->set_var( "NODISC_OPTION_NAME", WebUtil::htmlOutput($thisOptionName));
			$t->set_var( "NODISC_OPTION_CHOICES", $choicesListHTML);
			
			$t->allowVariableToContainBrackets("NODISC_OPTION_CHOICES");

			$t->parse("_NoDiscountOptionNamesBl","NoDiscountOptionNamesBl", true);
		}
		
		$t->parse("_EmptyNoDiscountProductOptionsBL", "EmptyNoDiscountProductOptionsBL", false);

	}
	else{
		$t->set_var( "_EmptyNoDiscountProductOptionsBL", "");
	}





		
	$couponResultFoundFlag = true;

	$t->parse( "_ListCouponLineBL", "ListCouponLineBL", true );
}

//Remove report block if no report generated
$Rows = $dbCmd->GetNumRows();
$t->parse_block( "origPage", "ReportBL", $couponResultFoundFlag );
$t->parse_block( "origPage", "NoReportBL", !$couponResultFoundFlag );

//Parse fullpage and output
$t->pparse("OUT","origPage");

?>

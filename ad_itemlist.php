<?

require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


$orderno = WebUtil::GetInput("orderno", FILTER_SANITIZE_INT);


$t = new Templatex(".");

$t->set_file("origPage", "ad_itemlist-template.html");


$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$t->set_var("ORDERNO", $orderno);


if($AuthObj->CheckIfBelongsToGroup("VENDOR"))
	$vendorRestriction = $UserID;
else
	$vendorRestriction = null;


if(!Order::CheckIfUserHasPermissionToSeeOrder($orderno))
	throw new Exception("Can not display the item list because the order number does no exist.");

$orderCompletedFlag = Order::CheckIfOrderComplete($dbCmd, $orderno);



// Intiate a string that will contain the javascript commands for making an array
$ProjectListArr_JS = "Array(";
$DiscountsArrTotal_JS = "Array(";
$DiscountsArrPercent_JS = "Array(";

$t->set_block("origPage","VendorSubBL","VendorSubBLout");
$t->set_block("origPage","HideProjectDetailsBL","HideProjectDetailsBLout");
$t->set_block("origPage","ProjectBL","ProjectBLout");

$query = "SELECT ID FROM projectsordered WHERE OrderID=$orderno AND Status !='C'";

if($AuthObj->CheckIfBelongsToGroup("VENDOR"))
	$query .= " AND (" . Product::GetVendorIDLimiterSQL($UserID) . ")";

$dbCmd->Query($query);

$TotalProjects = $dbCmd->GetNumRows();

while($ProjectID = $dbCmd->GetValue()){

	$projectOrderedObj = ProjectOrdered::getObjectByProjectID($dbCmd2, $ProjectID);
	
	$t->set_var("PRODUCT_NAME", WebUtil::htmlOutput($projectOrderedObj->getProductTitleWithExt()));
	
	
	//Don't parse our project info object if there are more than 10 projects in the order
	if($TotalProjects < 11)
		$t->set_var("PROJECT_TABLE", $projectOrderedObj->getProjectDescriptionTable("reallysmallbody", "", true));
	else
		$t->set_var("PROJECT_TABLE", WebUtil::htmlOutput($projectOrderedObj->getOrderDescription()) . "<br>" . ($projectOrderedObj->getOptionsDescription() != "" ? "(" : "") . WebUtil::htmlOutput($projectOrderedObj->getOptionsDescription() . ($projectOrderedObj->getOptionsDescription()) != "" ? ")" : ""));

	$t->allowVariableToContainBrackets("PROJECT_TABLE");
		
	$customerSubtotal = $projectOrderedObj->getCustomerSubTotal();
	$customerDiscount = $projectOrderedObj->getCustomerDiscount();

	$t->set_var("PROJECTID", $ProjectID);

	// clean the slate for inner block.
	$t->set_var("VendorSubBLout", "");
	
	$vendorIDArr = $projectOrderedObj->getVendorID_DB_Arr();
	$vendorSubtotalArr = $projectOrderedObj->getVendorSubtotals_DB_Arr();
	
	$vendorCounter = 1;
	foreach($vendorIDArr as $thisVendorID){
	
		if(empty($thisVendorID))
			continue;
	
		$t->set_var("VENDOR_NAME", WebUtil::htmlOutput(Product::GetVendorNameByUserID($dbCmd2, $thisVendorID)));
		$t->set_var("V_SUB", $vendorSubtotalArr[($vendorCounter - 1)]);
		$t->set_var("VENDORNUM", $vendorCounter);
		
		$vendorCounter++;
		
		$t->parse("VendorSubBLout","VendorSubBL",true);
	}



	$t->set_var("PROJECT_STATUS", WebUtil::htmlOutput(ProjectStatus::GetProjectStatusDescription($projectOrderedObj->getStatusChar(), false)));
	$t->set_var("C_SUB", $customerSubtotal);
	$t->set_var("D_DOL", number_format(round($customerDiscount * $customerSubtotal, 2), 2, '.', ''));
	
	// so that ther are not an unessary number of digits (if they are not significant)
	$discountFormatted = Widgets::GetDiscountPercentFormated($customerSubtotal, ($customerSubtotal *$customerDiscount) );
	$t->set_var("D_PER", $discountFormatted);
	
	// If the order has been completed then we need to lock the HTML Input fields with the keyword READONLY
	if($orderCompletedFlag)
		$t->set_var("INPUT_LOCKED", "READONLY");
	else
		$t->set_var("INPUT_LOCKED", "");

		

	$ProjectListArr_JS .= "'$ProjectID',";
	$DiscountsArrTotal_JS .= "'" . number_format(round($customerDiscount * $customerSubtotal, 2), 2, '.', '') . "',";
	$DiscountsArrPercent_JS .= "'" . $customerDiscount . "',";
	



	// See if they have permissions to see the Project totals..  
	// If not erase the block of HTML containing the prices --##
	if(!$AuthObj->CheckForPermission("PROJECT_TOTALS"))
		$t->set_var("HideProjectDetailsBLout", "<tr><td class='SmallBody' align='right'>\$" . ProjectOrdered::GetVendorSubtotalFromProject($dbCmd2, $ProjectID, $vendorRestriction) . "</td></tr>");
	else
		$t->parse("HideProjectDetailsBLout","HideProjectDetailsBL",false);


	$t->parse("ProjectBLout","ProjectBL",true);
}



// If the order has been completed take out different parts of HTML
if($orderCompletedFlag){
	$t->set_var("LOCKED_MESSAGE", "This order is completed, so you can not modify prices at this point.  However, you may add balance adjustments.");

	$t->set_block("origPage","HideUpdateCancelButtonsBL","HideUpdateCancelButtonsBLout");
	$t->set_var("HideUpdateCancelButtonsBLout", "");
	
	$t->set_block("origPage","HideGlobalDiscountLinkBL","HideGlobalDiscountLinkBLout");
	$t->set_var("HideGlobalDiscountLinkBLout", "");
}
else{
	$t->set_var("LOCKED_MESSAGE", "");
}



// Get rid of the last character which is a comma
$ProjectListArr_JS = substr($ProjectListArr_JS, 0, -1);
$DiscountsArrTotal_JS = substr($DiscountsArrTotal_JS, 0, -1);
$DiscountsArrPercent_JS = substr($DiscountsArrPercent_JS, 0, -1);




// Finish off the javascrip array
$ProjectListArr_JS .= ")";
$DiscountsArrTotal_JS .= ")";
$DiscountsArrPercent_JS .= ")";



$t->set_var("PROJECT_LIST_JS_ARR", $ProjectListArr_JS);
$t->set_var("DISCOUNTS_TOTALS_ARR", $DiscountsArrTotal_JS);
$t->set_var("DISCOUNTS_PERCENT_ARR", $DiscountsArrPercent_JS);




// See if they have permissions to see the Project totals..  If not then erase the top block HTML with all of the totals.
if(!$AuthObj->CheckForPermission("PROJECT_TOTALS")){
	$t->set_block("origPage","HideOrderTotalsBL","HideOrderTotalsBLout");
	$t->set_var("HideOrderTotalsBLout", "&nbsp;");
}



$t->pparse("OUT","origPage");


?>
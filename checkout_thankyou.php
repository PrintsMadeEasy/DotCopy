<?

require_once("library/Boot_Session.php");

$ordernumber = WebUtil::GetInput("ordernumber", FILTER_SANITIZE_STRING_ONE_LINE);

$t = new Templatex();
$dbCmd = new DbCmd();

$AuthObj = new Authenticate(Authenticate::login_general);
$UserID = $AuthObj->GetUserID();

$t->set_file("origPage", "checkout_thankyou-template.html");

$orderNumberOnly = 0;

try{
	$orderNumberOnly = Order::getOrderIDfromOrderHash($ordernumber);
}
catch (Exception $e){
	
	WebUtil::PrintError("An error occured. Please contact Customer Service.");
}


$t->set_var("ORDERNO", $ordernumber);


// Create an order ID with a zero replacing the dash.
$orderNumberWithZeroInsteadOfDash = preg_replace("/-/", "X", $ordernumber);
$t->set_var("ORDERNO_WITH_X_DELIMETER", $orderNumberWithZeroInsteadOfDash);



// Get the subtotal of the order (without options like Postage).  This is going to be used for Affiliate subtotals.
$customerSubtotal = Order::GetTotalFromOrder($dbCmd, $orderNumberOnly, "customersubtotal");
$customerDiscount = Order::GetTotalFromOrder($dbCmd, $orderNumberOnly, "customerdiscount");
$discDoesntApplyToThisAmnt = ProjectGroup::GetTotalFrmGrpPermDiscDoesntApply($dbCmd, $orderNumberOnly, "ordered");

$orderTotalForAffiliate = $customerSubtotal - $customerDiscount - $discDoesntApplyToThisAmnt;

$t->set_var("ORDER_SUBTOTAL_FOR_AFFILIATES", WebUtil::htmlOutput($orderTotalForAffiliate));




$projectIDsArr = Order::getProjectIDsInOrder($dbCmd, $orderNumberOnly);
$t->set_var("NUMBER_OF_PROJECTS", sizeof($projectIDsArr));




$dbCmd->Query("SELECT FirstTimeCustomer FROM orders WHERE ID=" . intval($orderNumberOnly));
$firstTimeCustomer = $dbCmd->GetValue();

if($firstTimeCustomer == "Y"){
	$t->set_var("FIRST_TIME_CUSTOMER_1_OR_0", "1");
}
else if($firstTimeCustomer == "N"){
	$t->set_var("FIRST_TIME_CUSTOMER_1_OR_0", "0");
}
else{
	throw new Exception("The value for first time customer is unknown in the orders table.");
}




$shippingChoicesObj = new ShippingChoices();

if(!Order::checkIfProductInOrderWillBeShipped($orderNumberOnly))
	$t->discard_block("origPage", "ShippingMethodBL");
else 
	$t->set_var("SHIPPING_METHOD", WebUtil::htmlOutput($shippingChoicesObj->getShippingChoiceName(Order::getShippingChoiceIDfromOrder($dbCmd, $orderNumberOnly))));

	
	
$order_summary = "";

// The Marketing company Dotomi wants a list of Product ID's (and their order values) separated by pipe symbols and semicolons.
$dotomi_productID_Hash = "";
$commissionJunctionNameValuePairs = "";

$loopNumber = 1;

$dbCmd->Query("SELECT CustomerSubtotal, CustomerDiscount, ProductID, Quantity FROM projectsordered WHERE OrderID=" .intval($orderNumberOnly));
while($projectHash = $dbCmd->GetRow()){
	$itemSubtotalWithDiscountRemoved = round($projectHash["CustomerSubtotal"] - ($projectHash["CustomerSubtotal"] * $projectHash["CustomerDiscount"]), 2);
	$itemSubtotalWithDiscountRemoved = number_format($itemSubtotalWithDiscountRemoved, 2, ".", "");

	$itemSubtotalBeforeDiscount = number_format($projectHash["CustomerSubtotal"], 2, ".", "");
	$itemDiscountAmount = round(($projectHash["CustomerSubtotal"] * $projectHash["CustomerDiscount"]), 2);
	$itemDiscountAmount = number_format($itemDiscountAmount, 2, ".", "");
	
	if(!empty($dotomi_productID_Hash))
		$dotomi_productID_Hash .= "|";
		
	$dotomi_productID_Hash .= "PR" . $projectHash["ProductID"] . ";" . $itemSubtotalWithDiscountRemoved;

	$commissionJunctionNameValuePairs .= "ITEM".$loopNumber."=sku".$projectHash["ProductID"] . "X" . $projectHash["Quantity"] ."&AMT".$loopNumber."=".$itemSubtotalBeforeDiscount . "&DCNT".$loopNumber."=".$itemDiscountAmount."&QTY".$loopNumber."=1&";
	
	$loopNumber++;
	
}
$t->set_var("DOTOMI_PRODUCT_HASH", $dotomi_productID_Hash);
$t->set_var("COMMISSION_JUNCTION_PRODUCTS", $commissionJunctionNameValuePairs);


//$t->set_block("origPage","BingCashBackItem","BingCashBackItemout");

$productIDsinOrderArr = ProjectGroup::GetProductIDlistFromGroup($dbCmd, $orderNumberOnly, "order");

foreach($productIDsinOrderArr as $thisProductID){
	
	$productObj = new Product($dbCmd, $thisProductID);

	// Get a new project info Object based on a product ID
	// We need this to give us a description of the product being shipped
	$projectInfoObj = new ProjectInfo($dbCmd, $thisProductID);
	
	$quantityOfProduct = ProjectGroup::GetDetailsOfProductInGroup($dbCmd, "quantity", $orderNumberOnly, $thisProductID, "order");

	$order_summary .= WebUtil::htmlOutput($projectInfoObj->getOrderDescription($quantityOfProduct)) . "<br>";
	
	
	$subTotalOfProduct_inOrder = Order::GetTotalFromOrder($dbCmd, $orderNumberOnly, "customersubtotal", null, $thisProductID);
	$discountOfProduct_inOrder = Order::GetTotalFromOrder($dbCmd, $orderNumberOnly, "customerdiscount", null, $thisProductID);
	$discountDoesNotApplyToProduct_inOrder = ProjectGroup::GetTotalFrmGrpPermDiscDoesntApply($dbCmd, $orderNumberOnly, "ordered", $thisProductID);

	$subtotalOfProductGroupAfterDiscountsForAffiliate = $subTotalOfProduct_inOrder - $discountOfProduct_inOrder - $discountDoesNotApplyToProduct_inOrder;

	if($subtotalOfProductGroupAfterDiscountsForAffiliate < 0)
		$subtotalOfProductGroupAfterDiscountsForAffiliate = 0;
	
	$t->set_var("PRODUCT_ID", $thisProductID);
	$t->set_var("PRODUCT_QUANTITY_IN_ORDER", $quantityOfProduct);
	$t->set_var("PRODUCT_UNIT_PRICE_OF_ORDER_FOR_AFFILIATE", round($subtotalOfProductGroupAfterDiscountsForAffiliate / $quantityOfProduct, 2) );
	
	//$t->parse("BingCashBackItemout","BingCashBackItem",true);
}
	
	
$t->set_var("ORDER_DESC", $order_summary);
$t->allowVariableToContainBrackets("ORDER_DESC");	


$shippingAddressObj = Order::getShippingAddressObject($dbCmd, $orderNumberOnly);
$htmlShippingAddress = WebUtil::htmlOutput($shippingAddressObj->toString());
$htmlShippingAddress = preg_replace("/\n/", "<br>", $htmlShippingAddress);
$htmlShippingAddress = preg_replace("/<br>US/", "", $htmlShippingAddress); // Don't need to show the country code in the US.
$t->set_var("SHIPPINGADDRESS", $htmlShippingAddress);
$t->allowVariableToContainBrackets("SHIPPINGADDRESS");

$billingAddressObj = Order::getBillingAddressObject($dbCmd, $orderNumberOnly);
$htmlBillingAddress = WebUtil::htmlOutput($billingAddressObj->toString());
$htmlBillingAddress = preg_replace("/\n/", "<br>", $htmlBillingAddress);
$htmlBillingAddress = preg_replace("/<br>US/", "", $htmlBillingAddress); // Don't need to show the country code in the US.
$t->set_var("BILLINGADDRESS", $htmlBillingAddress);
$t->allowVariableToContainBrackets("BILLINGADDRESS");


$shippingInstructions = Order::getShippingInstructions($dbCmd, $orderNumberOnly);
$shipInstrMultiLine = "";
$shippingInstArr = preg_split("/\n/", $shippingInstructions);
foreach($shippingInstArr as $thisLine){
	if(!empty($shipInstrMultiLine))
		$shipInstrMultiLine .= "<br>";
	$shipInstrMultiLine .= WebUtil::htmlOutput($thisLine);
}

$t->set_var("SHIPPING_INSTRUCTIONS", $shipInstrMultiLine);
$t->allowVariableToContainBrackets("SHIPPING_INSTRUCTIONS");

if(empty($shipInstrMultiLine))
	$t->discard_block("origPage", "ShippingInstructionsBL");



$grandTotal = Order::GetGrandTotalOfOrder($dbCmd, $orderNumberOnly);
$customerShipping = Order::GetCustomerShippingQuote($dbCmd, $orderNumberOnly);
$customerSubtotal = Order::GetTotalFromOrder($dbCmd, $orderNumberOnly, "customersubtotal");
$customerDiscount = Order::GetTotalFromOrder($dbCmd, $orderNumberOnly, "customerdiscount");
$customerTax = Order::GetTotalFromOrder($dbCmd, $orderNumberOnly, "customertax");


if($customerDiscount <= 0.1)
	$t->discard_block("origPage", "DiscountBL");

	
$t->set_var("SUBTOTAL", number_format($customerSubtotal, 2));
$t->set_var("DISCOUNT", number_format($customerDiscount, 2));
$t->set_var("TAX", number_format($customerTax, 2));
$t->set_var("S_AND_H", number_format($customerShipping, 2));
$t->set_var("GRANDTOTAL", number_format($grandTotal, 2));
$t->set_var("GRANDTOTAL_NO_COMMAS", number_format($grandTotal, 2, ".", ""));




$userName = UserControl::GetNameByUserID($dbCmd, $UserID);
$FullNamePartsHash = UserControl::GetPartsFromFullName($userName);
$firstname = $FullNamePartsHash["First"];

$t->set_var("NAME", WebUtil::htmlOutput($firstname));
$t->set_var("USER_ID", WebUtil::htmlOutput($UserID));


$trackingCodeReferral = Order::GetTrackingCodeReferral($dbCmd, $orderNumberOnly);

// Find out if this is a new user (who also has a Google Tracking Code) on the order
// We are only tracking Google aquisitions on brand new customers.
// If the customer is not new (or doesn't have a google tracking code) then remove the tracking script.
//if(Order::CheckIfOrderIsRepeat($dbCmd, $orderNumberOnly) || !preg_match("/^g.*/", Order::GetTrackingCodeReferral($dbCmd, $orderNumberOnly)))
//	$t->discard_block("origPage", "GoogleTrackingCodeBL");


// If peole have a Google/Yahoo/Msn tracking code set... then they must have found us through Google and not an affilate.
// The only exception is that if they have a "prints made easy" google code set... then they may have come back to complete their order the following day.
//if(preg_match("/^g-.*/", $trackingCodeReferral) || preg_match("/^gcn-.*/", $trackingCodeReferral) || preg_match("/^y-.*/", $trackingCodeReferral) || preg_match("/^msn-.*/", $trackingCodeReferral) || preg_match("/^yahoo-.*/", $trackingCodeReferral) || preg_match("/^overture-.*/", $trackingCodeReferral) || preg_match("/^bing-.*/", $trackingCodeReferral)){
//	if(!preg_match("/pme/", $trackingCodeReferral) && !preg_match("/printsmade/", $trackingCodeReferral)){
//		$t->discard_block("origPage", "AffiliateTractionBL");
//	}
//}


// PME has a tiered commission.  There are different blocks for repeat customers.
if(Order::CheckIfOrderIsRepeat($dbCmd, $orderNumberOnly)){
	$t->discard_block("origPage", "AffiliateTraction_NewCustomerBL");
}
else{
	$t->discard_block("origPage", "AffiliateTraction_RepeatCustomerBL");
}




// Find out what tracking code was given credit for the order.
$dbCmd->Query("SELECT Referral FROM orders WHERE ID=" . intval($orderNumberOnly));
$bannerTrackingCodeFromOrder = $dbCmd->GetValue();



// On the live site the Navigation bar needs to have hard-coded links to jump out of SLL mode... like http://www.example.com
// Also flash plugin can not have any non-secure source or browser will complain.. nead to change plugin to https:/
if(Constants::GetDevelopmentServer()){
	$t->set_var("SECURE_FLASH","");
	$t->set_var("HTTPS_FLASH","");
}
else{
	$t->set_var("SECURE_FLASH","TRUE");
	$t->set_var("HTTPS_FLASH","s");
}

// Don't let an Admin give affiliate commissions.
/*  ------------------------------  TEMPORARILY DISABLE THIS FOR TESTING --------------------
if(Constants::GetDevelopmentServer() || IPaccess::checkIfUserIpHasSomeAccess())
	$t->discard_block("origPage", "AfilliateTrackingCodes");
*/

// If we don't have CashBack shopping cookie set, then don't let the Bing Pixel fire.
// Bing Cashback shopping expects to have a URL with name value pairs like "source=cashbackShopping"
/*
$affiliateSource = WebUtil::GetSessionVar("AffiliateSource", WebUtil::GetCookie("AffiliateSource"));
if($affiliateSource != "cashbackShopping"){
	$t->discard_block("origPage", "BingCashBackBL");
}
*/


	
VisitorPath::addRecord("Order Placed", $orderNumberOnly);

$t->pparse("OUT","origPage");





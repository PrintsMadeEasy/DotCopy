<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();
$dbCmd3 = new DbCmd();

$UserControlObj = new UserControl($dbCmd);


$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("CUSTOMER_ACCOUNT"))
	WebUtil::PrintAdminError("Not Available");


$customerid = WebUtil::GetInput("customerid", FILTER_SANITIZE_INT);

// Load customer info from Database
if(!$UserControlObj->LoadUserByID($customerid, TRUE))
	throw new Exception("Can not load user data because the user does not exist.");
	
$loyaltyObj = new LoyaltyProgram(UserControl::getDomainIDofUser($customerid));

Domain::enforceTopDomainID(UserControl::getDomainIDofUser($customerid));

$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$returl = WebUtil::GetInput("returl", FILTER_SANITIZE_URL);
$retdesc = WebUtil::GetInput("retdesc", FILTER_SANITIZE_STRING_ONE_LINE);
$refundAmnt = WebUtil::GetInput("refundAmnt", FILTER_SANITIZE_STRING_ONE_LINE);
$loyaltyRowId = WebUtil::GetInput("loyaltyRowId", FILTER_SANITIZE_NUMBER_INT);




if(!empty($action)){
	
	WebUtil::checkFormSecurityCode();

	if($action == "refund"){
		
		$loyaltyObj->refundMoneyToUser($customerid, $refundAmnt);
	
		header("Location: " . WebUtil::FilterURL("./ad_loyalty_program.php?customerid=$customerid&returl=".urlencode($returl)."&retdesc=" . urlencode($retdesc)));
		exit;
	}
	else if($action == "retrycharge"){
		
		$loyaltyObj->retryRefund($loyaltyRowId);
	
		header("Location: " . WebUtil::FilterURL("./ad_loyalty_program.php?customerid=$customerid&returl=".urlencode($returl)."&retdesc=" . urlencode($retdesc)));
		exit;
	}
	else if($action == "enrollCustomer"){
		
		// Log any Changes made to the Account Information (before updating)
		$userChangeLogFrom = $UserControlObj->getAccountDescriptionText(true);
		
		$UserControlObj->setLoyaltyProgram("Y");
		$UserControlObj->UpdateUser();
		
		// Log any Changes made to the Account Information (after updating)
		$userChangeLogTo = $UserControlObj->getAccountDescriptionText(true);
		
		if(md5($userChangeLogFrom) != md5($userChangeLogTo))
			UserControl::RecordChangesToUser($dbCmd, $UserControlObj->getUserID(), $UserID, $userChangeLogFrom, $userChangeLogTo);
		
		header("Location: " . WebUtil::FilterURL("./ad_loyalty_program.php?customerid=$customerid&returl=".urlencode($returl)."&retdesc=" . urlencode($retdesc)));
		exit;
	}
	else if($action == "optOutCustomer"){
		
		// Log any Changes made to the Account Information (before updating)
		$userChangeLogFrom = $UserControlObj->getAccountDescriptionText(true);
		
		$UserControlObj->setLoyaltyProgram("N");
		$UserControlObj->UpdateUser();
		
		// Log any Changes made to the Account Information (after updating)
		$userChangeLogTo = $UserControlObj->getAccountDescriptionText(true);
		
		if(md5($userChangeLogFrom) != md5($userChangeLogTo))
			UserControl::RecordChangesToUser($dbCmd, $UserControlObj->getUserID(), $UserID, $userChangeLogFrom, $userChangeLogTo);
		
		
		header("Location: " . WebUtil::FilterURL("./ad_loyalty_program.php?customerid=$customerid&returl=".urlencode($returl)."&retdesc=" . urlencode($retdesc)));
		exit;
	}
	else{
		throw new Exception("Illegal action was passed.");
	}
}



// Enforce the Top Domain ID so that we can see a logo in the Nav Bar for the user's domain.
Domain::enforceTopDomainID($UserControlObj->getDomainID());



$t = new Templatex(".");

$t->set_file("origPage", "ad_loyalty_program-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


// Keep a record of the visit to this page by the user.
NavigationHistory::recordPageVisit($dbCmd, $UserID, "UserLylty", $customerid);




$totalCustomerLoyaltyCharges = $loyaltyObj->getLoyaltyChargeTotalsFromUser($customerid);
$totalCustomerLoyaltyChargeCount = $loyaltyObj->getLoyaltyChargeCountsFromUser($customerid);
$totalCustomerLoyaltyRefunds = $loyaltyObj->getRefundsTotalsToUser($customerid);
$totalCustomerLoyaltySavings = $loyaltyObj->getTotalSavingsForUser($customerid);
$totalCustomerRefundErrors = $loyaltyObj->getRefundsErrorTotal($customerid);

$montlyFeeForUser = $loyaltyObj->getMontlyFee($customerid);
if(empty($montlyFeeForUser))
	$montlyFeeForUser = $loyaltyObj->getMontlyFee();

$t->set_var("TOTAL_MONTLY_FEES", number_format($totalCustomerLoyaltyCharges, 2));
$t->set_var("SUBSCRIPTION_MONTH_COUNT", $totalCustomerLoyaltyChargeCount);
$t->set_var("MONTHLY_FEE", number_format($montlyFeeForUser, 2));
$t->set_var("CUSTOMER_SAVINGS", number_format($totalCustomerLoyaltySavings, 2));
$t->set_var("CUSTOMER_SHIPPING_SAVINGS", number_format($loyaltyObj->getShippingSavingsForUser($customerid), 2));
$t->set_var("CUSTOMER_SUBTOTAL_SAVINGS", number_format($loyaltyObj->getSubtotalSavingsForUser($customerid), 2));
$t->set_var("LOYALTY_REFUNDS", number_format($totalCustomerLoyaltyRefunds, 2));
$t->set_var("CUSTOMER_ABSOLUTE_BALANCE_NO_COMMAS", number_format(($totalCustomerLoyaltyCharges - $totalCustomerLoyaltyRefunds), 2, ".", ""));
$t->set_var("CUSTOMER_ABSOLUTE_BALANCE", Widgets::GetColoredPrice(($totalCustomerLoyaltyCharges - $totalCustomerLoyaltyRefunds)));
$t->set_var("CUSTOMER_RELATIVE_BALANCE_NO_COMMAS", number_format(($totalCustomerLoyaltyCharges - $totalCustomerLoyaltySavings - $totalCustomerLoyaltyRefunds), 2, ".", ""));
$t->set_var("CUSTOMER_RELATIVE_BALANCE", Widgets::GetColoredPrice($totalCustomerLoyaltyCharges - $totalCustomerLoyaltySavings - $totalCustomerLoyaltyRefunds));


$t->allowVariableToContainBrackets("CUSTOMER_RELATIVE_BALANCE");
$t->allowVariableToContainBrackets("CUSTOMER_ABSOLUTE_BALANCE");

$t->set_var("PERSONS_NAME", WebUtil::htmlOutput($UserControlObj->getName()));
$t->set_var("CUSTOMERID", $customerid);

$t->set_var("RETURL", WebUtil::FilterURL(($returl)));
$t->set_var("RETDESC", WebUtil::htmlOutput($retdesc));



if($totalCustomerRefundErrors > 0){
	$t->set_var("LOYALTY_REFUND_ERRORS", "<font color='#990000'>Refund Errors: <b>\$" . number_format($totalCustomerRefundErrors, 2) . "</b></font>");
	$t->allowVariableToContainBrackets("LOYALTY_REFUND_ERRORS");
}
else{
	$t->set_var("LOYALTY_REFUND_ERRORS", "");
}




// If the user is still enrolled in the program... but they have refunds already issued, then we want to show a warning message.
if(!($UserControlObj->getLoyaltyProgram() == "Y" && $totalCustomerLoyaltyRefunds > 0))
	$t->discard_block("origPage", "WarningMessageSubscriptionBL");


	
if($UserControlObj->getLoyaltyProgram() == "Y"){
	$t->set_var("ENROLLMENT_YES", "checked");
	$t->set_var("ENROLLMENT_NO", "");
}
else{
	$t->set_var("ENROLLMENT_YES", "");
	$t->set_var("ENROLLMENT_NO", "checked");
}



// Show all of the charge records for the user.
$t->set_block("origPage","ChargeBL","ChargeBLout");
$dbCmd->Query("SELECT ID, UNIX_TIMESTAMP(Date) AS Date, ChargeAmount FROM loyaltycharges WHERE UserID=$customerid AND ChargeAmount > 0 ORDER BY ID DESC");

if($dbCmd->GetNumRows() == 0){
	$t->discard_block("origPage", "EmptyLoyaltyChargesBL");
}
while($row = $dbCmd->GetRow()){
	
	$t->set_var("DATE", date("n/j/Y", $row["Date"]));
	$t->set_var("AMOUNT", number_format($row["ChargeAmount"], 2));
	
	$t->set_var("CHARGE_STATUS", "Captured");
	
	
	$refundRecordsHTML = "";
	$dbCmd2->Query("SELECT ID, UNIX_TIMESTAMP(Date) AS Date, RefundAmount FROM loyaltycharges WHERE RefundChargeLink=" . $row["ID"]);
	while($refundRow = $dbCmd2->GetRow()){
		
		if(!empty($refundRecordsHTML))
			$refundRecordsHTML .= "<br><br>";
		
		$refundRecordsHTML .= "Refund: \$" . number_format($refundRow["RefundAmount"], 2) . "&nbsp;&nbsp;&nbsp;<font class='ReallySmallBody'>" . date("n/j/Y - g:i:a", $refundRow["Date"]) . "</font>";
	
		// Find out if there is an error on the refund.
		$dbCmd3->Query("SELECT ID, ResponseReasonText, ChargeRetries, UNIX_TIMESTAMP(StatusDate) AS StatusDate FROM charges USE INDEX(charges_LoyaltyChargeID) WHERE Status != 'A' AND LoyaltyChargeID=" . $refundRow["ID"]);
		if($dbCmd3->GetNumRows() != 0){
			
			$chargeRow = $dbCmd3->GetRow();
			
			$refundRecordsHTML .= "<br><font color='#990000'><a href='javascript:RetryRefund(".$refundRow["ID"].");'>Retry (".$chargeRow["ChargeRetries"].")</a>: ".WebUtil::htmlOutput($chargeRow["ResponseReasonText"])." </font> ";
			if($chargeRow["ChargeRetries"] > 0)
				$refundRecordsHTML .= " Last Tried: " . date("n/j/Y - g:i:a", $chargeRow["StatusDate"]);
		}
	}
	
	if(!empty($refundRecordsHTML))
		$t->set_var("CHARGE_STATUS", $refundRecordsHTML);
	else 
		$t->set_var("CHARGE_STATUS", "Captured");
	
	$t->allowVariableToContainBrackets("CHARGE_STATUS");
	
	$t->parse("ChargeBLout","ChargeBL",true);
}





// Show all of the charge records for the user.
$t->set_block("origPage","OrderBL","OrderBLout");
$dbCmd->Query("SELECT UNIX_TIMESTAMP(Date) AS Date, ShippingDiscount, SubtotalDiscount, OrderID FROM loyaltysavings
				INNER JOIN orders ON orders.ID = loyaltysavings.OrderID WHERE orders.UserID=$customerid
				ORDER BY loyaltysavings.ID DESC");

if($dbCmd->GetNumRows() == 0){
	$t->discard_block("origPage", "EmptyLoyaltySavingsBL");
}
while($row = $dbCmd->GetRow()){
	
	$t->set_var("DATE", date("n/j/Y", $row["Date"]));
	$t->set_var("ORDER_NO", $row["OrderID"]);
	$t->set_var("ORDER_HASH", ("#" . Order::GetHashedOrderNo($row["OrderID"])));
	$t->set_var("SHIPPING_SAVED", number_format($row["ShippingDiscount"], 2));
	$t->set_var("SUBTOTAL_SAVED", number_format($row["SubtotalDiscount"], 2));
	$t->set_var("TOTAL_SAVED", number_format(($row["SubtotalDiscount"] + $row["ShippingDiscount"]), 2));
	
	
	$t->parse("OrderBLout","OrderBL",true);
}





$t->pparse("OUT","origPage");




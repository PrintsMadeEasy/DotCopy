<?

require_once("library/Boot_Session.php");

// Don't make it look like we are favoring a particular Domain 
Domain::removeTopDomainID();

$searcherrors = WebUtil::GetInput("searcherrors", FILTER_SANITIZE_STRING_ONE_LINE);


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();





$domainObj = Domain::singleton();


// Make this script be able to run for a while 
set_time_limit(2000);

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("CREDIT_CARD_ERRORS"))
	throw new Exception("Permission Denied");

	
	
	
$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if($action){
	
	WebUtil::checkFormSecurityCode();

	if($action == "retrycharge"){
		
		$chargeid = WebUtil::GetInput("chargeid", FILTER_SANITIZE_INT);
		$returl = WebUtil::GetInput("returl", FILTER_SANITIZE_URL);
		
		$dbCmd->Query("SELECT OrderID FROM charges WHERE ID=" . intval($chargeid));
		$orderIDofCharge = $dbCmd->GetValue();
		
		if(!Order::CheckIfUserHasPermissionToSeeOrder($orderIDofCharge))
			throw new Exception("The Charge ID doesn't exist, or it doesn't belong to the correct order while retrying.");
		
		$domainIDofOrder = Order::getDomainIDFromOrder($orderIDofCharge);
			

		$PaymentsObj = new Payments($domainIDofOrder);
		
		$PaymentsObj->LoadBillingInfoByOrderID($orderIDofCharge);
			
		if($PaymentsObj->RetryChargingCard($chargeid)){
			header("Location: ". WebUtil::FilterURL($returl));
			exit;
		}
		else{
			//The new charges were not approved, so display an error page
			$ErrorMessage = "The transaction was still not authorized.  The reason is... " . $PaymentsObj->GetErrorReason();
			WebUtil::PrintAdminError($ErrorMessage);
		}

	
	}
	else if($action == "cancelcharge"){

		$chargeid = WebUtil::GetInput("chargeid", FILTER_SANITIZE_INT);
		$returl = WebUtil::GetInput("returl", FILTER_SANITIZE_URL);
		
		$dbCmd->Query("SELECT OrderID FROM charges WHERE ID=" . intval($chargeid));
		$orderIDofCharge = $dbCmd->GetValue();
		
		if(!Order::CheckIfUserHasPermissionToSeeOrder($orderIDofCharge))
			throw new Exception("The Charge ID doesn't exist, or it doesn't belong to the correct order while canceling.");
	
		// Set the status of this charge to X... meaning that we canceled the charge --#
		$dbCmd->Query("UPDATE charges set Status=\"X\" WHERE ID=$chargeid");

		header("Location: " . WebUtil::FilterURL($returl));
	}
	else
		throw new Exception("Action undefined");

}


$t = new Templatex(".");

$t->set_file("origPage", "ad_creditcard_errors-template.html");


$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


$t->set_block("origPage","chargesBL","chargesBLout");

//All charges with status of Declined or Error
$dbCmd->Query("SELECT charges.ID, OrderID, ChargeType, Status, ResponseReasonText, UNIX_TIMESTAMP(StatusDate) AS StatusDate, ChargeRetries
		FROM charges INNER JOIN orders ON orders.ID = charges.OrderID WHERE (Status =\"D\" OR Status =\"E\") AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));

$found = false;
while ($row = $dbCmd->GetRow()){

	$ChargeID = $row["ID"];
	$OrderID = $row["OrderID"];
	$ChargeType = $row["ChargeType"];
	$Status = $row["Status"];
	$ResponseReason = $row["ResponseReasonText"];
	$StatusDate = $row["StatusDate"];
	$Retries = $row["ChargeRetries"];

	$found = true;

	// Convert the chargeType character into english --#
	if($ChargeType == "R")
		$t->set_var("CHARGE_TYPE", "Refund");
	else if($ChargeType == "C")
		$t->set_var("CHARGE_TYPE", "Capture");
	else
		WebUtil::WebmasterError("Order: $OrderID has an illegal charge type.");
	
	
	if($Status == "D")
		$StatusDesc = "<b>Declined:</b><br> ";
	else
		$StatusDesc = "<b>Error:</b><br> ";	

	
	$StatusDesc .= WebUtil::htmlOutput($ResponseReason);
	
	$dominIdOfOrder = Order::getDomainIDFromOrder($OrderID);
	
	// Only show the Domain Logo if the user has more than 1 domain selected.
	$domainLogoImg = "";
	if(sizeof($domainObj->getSelectedDomainIDs()) > 1){
		$domainLogoObj = new DomainLogos($dominIdOfOrder);
		$domainLogoImg = "<img title='".Domain::getDomainKeyFromID($dominIdOfOrder)."' alt='".Domain::getDomainKeyFromID($dominIdOfOrder)."'   src='./domain_logos/$domainLogoObj->verySmallIcon' border='0'>";
	}
	
	
	

	$t->set_var(array(
		"ERROR_DATE"=>date("m-j H:i", $StatusDate), 
		"ORDERNO"=>$OrderID,
		"ERROR_DESC"=>$StatusDesc,
		"RETRIES"=>$Retries, 
		"DOMAIN_LOGO"=>$domainLogoImg, 
		"CHARGE_ID"=>$ChargeID 
		));
		
	$t->allowVariableToContainBrackets("ERROR_DESC");
	$t->allowVariableToContainBrackets("DOMAIN_LOGO");

	$t->parse("chargesBLout","chargesBL",true);
}

if(!$found){
	$t->set_block("origPage","HideChargesBL","HideChargesBLout");
	$t->set_var(array("HideChargesBLout"=>"No credit card errors at this moment."));
}



//All charges with an N status... meaning... Needs Capture
$dbCmd->Query("SELECT DISTINCT OrderID FROM charges INNER JOIN orders ON orders.ID = charges.OrderID 
					WHERE " . DbHelper::getOrClauseFromArray("orders.DomainID", $domainObj->getSelectedDomainIDs()) . " 
					AND ChargeType='N' ORDER BY OrderID ASC");
$OrderStr = "";
while ($thisOrderID = $dbCmd->GetValue())
	$OrderStr .= $thisOrderID . " : ";

if($OrderStr == "")
	$t->set_var("WAITING_CAPTURES", "No orders pending captures.");
else
	$t->set_var("WAITING_CAPTURES", $OrderStr);






// Now see if this variable was passed in the URL.. If it is then we want to look for anomolies in the Database --#
// We are going to look for orders which are completed that do not have a matching capture  --#
// This make take a long time if there are a lot of orders --#
if(!empty($searcherrors)){

	// No need to check previous orders if we are already sure we got our money.
	$startScanningFromOrder = 365539;


	$dbCmd->Query("SELECT ID FROM orders WHERE ID > $startScanningFromOrder AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));

	$OrderIDerrorStr = "";
	
	
	print "Starting from order Number: $startScanningFromOrder   ..... Number of Order to Scan: " . $dbCmd->GetNumRows() . "<br><br>";
	
	while ($OrderID = $dbCmd->GetValue()){
	
	
		print " . ";
		flush();
		
		if(Order::CheckIfOrderComplete($dbCmd2, $OrderID) && Order::CheckForActiveProjectWithinOrder($dbCmd2, $OrderID) && !Order::CheckIfOrderIsWaitingForCapture($dbCmd2, $OrderID)){
		
			$GrandTotal = Order::GetGrandTotalOfOrder($dbCmd2, $OrderID);

			//If we start off with an authorization.. but then give the customer a discount... there is no point in doing a capture
			if($GrandTotal > 0){

				$ChargeAmount = 0;

				// Now look for a Capture or Auth/Capture within the charges table matching the grand total and OrderID
	
				$dbCmd2->Query("SELECT ChargeAmount FROM charges WHERE OrderID=$OrderID AND (ChargeType=\"C\" OR ChargeType=\"B\")");
				while($row = $dbCmd2->GetRow())
					$ChargeAmount += $row["ChargeAmount"];

				// There are some rounding errors on certain coupons discounts I think. So, don't consider a problem if it is close by a penny.
				if(round($ChargeAmount, 2) < ($GrandTotal - 0.01)){
					
					// It is likely that the Capture amount may be less than the Order Total on Mailing Batches since the quantity can change.
					$dbCmd2->Query("SELECT DISTINCT ProductID FROM projectsordered WHERE OrderID=$OrderID");
					$productIDarr = $dbCmd2->GetValueArr();
					
					if(sizeof($productIDarr) > 1){
						$OrderIDerrorStr .= $OrderID . "<br>";
					}
					else{
						if(!Product::checkIfProductHasMailingServices($dbCmd2, $productIDarr[0]))
							$OrderIDerrorStr .= $OrderID . "<br>";
					}
				}
			}
		
		}
	
		
	
	}
	
	if(!empty($OrderIDerrorStr))
		$OrderIDerrorStr = "<u>The following orders do not have captures:</u> <br>" . $OrderIDerrorStr;
	else
		$OrderIDerrorStr = "No Errors Found";
	
	$t->set_var("ORDER_ERRORS", $OrderIDerrorStr);
}
else{
	$t->set_var("ORDER_ERRORS", "");
}

$t->allowVariableToContainBrackets("ORDER_ERRORS");
 

$t->pparse("OUT","origPage");




?>
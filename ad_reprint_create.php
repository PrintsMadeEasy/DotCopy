<?

require_once("library/Boot_Session.php");

$projectrecord = WebUtil::GetInput("projectrecord", FILTER_SANITIZE_INT);
$projectids = WebUtil::GetInput("projectids", FILTER_SANITIZE_STRING_ONE_LINE);
$reprinttype = WebUtil::GetInput("reprinttype", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$reprintreason = WebUtil::GetInput("reprintreason", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$ignoreLargeSubtotalWarning = WebUtil::GetInput("ignorelargesubtotal", FILTER_SANITIZE_STRING_ONE_LINE);


if(!preg_match("/^\w$/", $reprintreason))
	throw new Exception("There was an error with the reprint reason.");

WebUtil::checkFormSecurityCode();


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();



//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("CREATE_REPRINT"))
	throw new Exception("permission error with reprint creation");


// Depending on the type of reprint this is... we are going to make some values default.
// Leave them as an empty string if we do not know the value yet... the database will pick them up on the way down
if($reprinttype == "P"){
	$customershipping = "0.00";
	$vendorsubtotal = "";
	$customerdiscount = "1.0";  //Give them 100% discount
	$reprintDesc = "Company Reprint: 100% Discount and Free Shipping";
}
else if($reprinttype == "H"){
	$customershipping = "";
	$vendorsubtotal = "";
	$customerdiscount = "1.0";  //Give them 100% discount
	$reprintDesc = "Company Reprint: 100% Discount but Customer Pays Shipping";
}
else if($reprinttype == "V"){
	$customershipping = "0.00";
	$vendorsubtotal = "0.00";
	$customerdiscount = "1.0";  //Give them 100% discount
	$reprintDesc = "Vendor Reprint: No charge for Customer";
}
else if($reprinttype == "C"){
	$customershipping = "";
	$vendorsubtotal = "";
	$customerdiscount = "0.5";  //Give them 50% discount
	$reprintDesc = "Customer Reprint: 50% Discount plus Shipping Charges";
}
else if($reprinttype == "F"){
	$customershipping = "";
	$vendorsubtotal = "";
	$customerdiscount = "0";  // No Discount
	$reprintDesc = "Customer Reprint: No Discounts";
}
else{
	throw new Exception("Problem, wrong reprint type was sent in the URL");
}


// This will be used to hold the ID's of the reprint ProjectID's when they are created
// We need this info in case we need to back out, in case a charge doesn't go through
$NewReprintIDsArr = array();

// Put all of the project ID's into this array that we are goint to reprint.
$ProjectIDtoReprint = array();


if(!empty($projectrecord)){
	$projectrecord = intval($projectrecord);
	ProjectBase::EnsurePrivilagesForProject($dbCmd, "ordered", $projectrecord);
	
	$ProjectIDtoReprint[] = $projectrecord;
}
else if(!empty($projectids)){

	// Get the Project IDs out of the URL separated by a pipe symbol and put into an array
	$ProjectIDArr = split("\|", $projectids);
	foreach($ProjectIDArr as $ThisPID){
		if(!preg_match("/^\d+$/", $ThisPID))
			continue;
		
		ProjectBase::EnsurePrivilagesForProject($dbCmd, "ordered", $ThisPID);
			
		$ProjectIDtoReprint[] = $ThisPID;
	}
}
else{
	throw new Exception("Error with the URL, No projects were selected for reprinting.");
}


// We want to run through the list of all Project ID's to be printed.
// If we find a subtotal over $200 and it is a Customer 50% reprint then we need to display a warning to the user that we may lose money
if(!$ignoreLargeSubtotalWarning && $reprinttype == "C"){

	$largeSubtotalProjectsArr = array();
	
	foreach($ProjectIDtoReprint as $thisPID){
	
		$dbCmd->Query("SELECT CustomerSubtotal FROM projectsordered WHERE ID =" . $thisPID);
		$custSub = $dbCmd->GetValue();
		
		if($custSub > 200.00)
			$largeSubtotalProjectsArr[$thisPID] = $custSub;
	}
	
	if(!empty($largeSubtotalProjectsArr)){
	
		$warningStr = "<font class='body'>The following Project Number" . LanguageBase::GetPluralSuffix(sizeof($largeSubtotalProjectsArr), " has", "s have") . " a 
				subtotal greater than $200.<br><br>";
		
		foreach($largeSubtotalProjectsArr as $thisProjectID => $thisSub){
			$warningStr .= "<a href='./ad_project.php?projectorderid=$thisProjectID' class='blueredlink'>P$thisProjectID</a> - Subtotal: \$" . number_format($thisSub, 2) . "<br>"; 
		}
		
		$warningStr .= "<br>You may need to check with your supervisor before creating this reprint.<br><br> 
				Because of the volume discounts we give on large orders it could cost the company money.<br>The goal of &quot;Customer
				Reprints&quot; is to prevent us from profiting at a customer's misfortune (without losing ourselves).<br><br><br>";
				
		// Form a link to override this message if they choose to reprint anyway
		$overrideLink = $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING'] . "&ignorelargesubtotal=true";
		
		$warningStr .= "<a href='javascript:history.back();' class='blueredlink'><b>Go Back</b></a> &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;or&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; <a href='$overrideLink' class='blueredlink'><b>Continue Anyways</b></a>";
		$warningStr .= "</font>";
	
		WebUtil::PrintAdminError($warningStr, TRUE);
	}


}


// Sort in reverse order... This way we will always do a reprint of the most recent "order" with the latest billing information
arsort($ProjectIDtoReprint);



$NewOrderCreated = false; 
$NewOrderID = 0;
$ShippingChoiceID = "";


foreach($ProjectIDtoReprint as $ThisProjectIDtoReprint){
	
	// Copy the order information from the project that we are reprinting
	// We only want to create 1 order if we are reprinting multiple projects
	if(!$NewOrderCreated){
	
		// Get the order ID that belongs to the Order we are reprinting
		$ThisOrderID = Order::GetOrderIDfromProjectID($dbCmd, $ThisProjectIDtoReprint);

		$dbCmd->Query("SELECT * FROM orders WHERE ID=$ThisOrderID");
		$OrderRow = $dbCmd->GetRow();

		$ShippingChoiceID = $OrderRow["ShippingChoiceID"];

		$mysql_timestamp = date("YmdHis", time());

		//Get Rid of the ID column since it is an autoincrement.  We want be able to put the row back in the DB
		unset($OrderRow["ID"]);
		
		//Update columns for the new order
		$OrderRow["DateOrdered"] = $mysql_timestamp;
		$OrderRow["InvoiceNote"] = "This order is a re-print.";
		$OrderRow["CouponID"] = 0;
		$OrderRow["Referral"] = "";
		$OrderRow["ReferralDate"] = null;
		$OrderRow["FirstTimeCustomer"] = "N";

		$NewOrderID = $dbCmd->InsertQuery("orders", $OrderRow );
		
		$NewOrderCreated = true;
	}



	$OldProjectObj = ProjectOrdered::getObjectByProjectID($dbCmd, $ThisProjectIDtoReprint);
	

	$reprintProjectObj = new ProjectOrdered($dbCmd);
	

	$reprintProjectObj->copyProject($OldProjectObj);
	
	$reprintProjectObj->setOrderID($NewOrderID);
	$reprintProjectObj->setRePrintLink($ThisProjectIDtoReprint);
	$reprintProjectObj->setRePrintReason($reprintreason);
	$reprintProjectObj->setCustomerDiscount($customerdiscount);
	$reprintProjectObj->setStatusChar("N");
	$reprintProjectObj->setPriority("N");
	$reprintProjectObj->setNotesProduction("");
	$reprintProjectObj->setNotesAdmin("");
	
	// For Mailing Jobs we want to erase the modified data file
	// That is because it could have been certified and had quantities removed, or other data.
	// If there is an error of some kind... then we want the data to be the same as when the customer placed the original order.
	if(Product::checkIfProductHasMailingServices($dbCmd, $reprintProjectObj->getProductID())){
		$reprintProjectObj->setVariableDataArtworkConfig(null);
		$reprintProjectObj->setVariableDataFile(null);
	}
	
	
	// If artwork Assistance was selected before... make sure that it is not used on the reprint.
	$reprintProjectObj->clearAdminOptionIfSet();
	
	

	$NewProjectID = $reprintProjectObj->createNewProject();
	
	// For mailing services Projects... reverting the data file (and config) back... could have changed the quantity and other config errors.
	if(Product::checkIfProductHasMailingServices($dbCmd, $reprintProjectObj->getProductID())){
		
		$reprintProjectObj->loadByProjectID($NewProjectID);
		
		$dataFileArr = split("\n", $reprintProjectObj->getVariableDataFile());
		$varDataLineCount = sizeof($dataFileArr);
		
		$reprintProjectObj->setQuantity($varDataLineCount);
		$reprintProjectObj->updateDatabase();
		
		VariableDataProcess::SetVariableDataStatusForProject($dbCmd, $NewProjectID, "ordered");
	}
	

	// Create a Project Object and update the database
	// This will refresh the prices to be the Most Current.
	// Also if any Vendor ID's have changed, those will be updated as well.
	$projectObj = ProjectOrdered::getObjectByProjectID($dbCmd, $NewProjectID);
	$projectObj->updateDatabase();
	
	// We may override the vendor subtotal (to give them nothing) in the event of a Vendor Reprint
	// We are only going to penalize Vendor #1, because they have the most reponsibility for the order. 
	if($vendorsubtotal != ""){
	
		$projectObj->loadByProjectID($NewProjectID);
		
		$vendorSubtotalsArr = $projectObj->getVendorSubtotals_DB_Arr();
		$vendorSubtotalsArr[0] = $vendorsubtotal;
		$projectObj->setVendorSubtotalsByArr($vendorSubtotalsArr);
		
		$projectObj->updateDatabaseWithRawData();
	}
	
	$NewReprintIDsArr[] = $NewProjectID;
}

if($NewOrderID == 0)
	throw new Exception("Error with creating reprint.");


// Create shipment(s)  and shipment links for this order
Shipment::ShipmentCreateForOrder($dbCmd, $dbCmd2, $NewOrderID, $ShippingChoiceID);

Order::RecordShippingAddressHistory($dbCmd, $NewOrderID, $UserID);
Order::RecordShippingChoiceHistory($dbCmd, $NewOrderID, $UserID);


// The function call above Shipment::ShipmentCreateForOrder would have automatically created our shipping quote... so lets overide it
if(!empty($customershipping)){
	Order::UpdateShippingQuote($dbCmd, $NewOrderID, $customershipping);
}
else{

	// Will calculate the amount the customer is supposed to pay for shipping and handeling and store it in the DB
	Order::UpdateShippingQuote($dbCmd, $NewOrderID, "RECALCULATE");

	// Let's find out what the shipping charges are for this new order (we just calculated it above)
	$dbCmd->Query("SELECT ShippingQuote FROM orders WHERE ID=$NewOrderID");
	$customershipping = $dbCmd->GetValue();
}


Order::UpdateSalesTaxForOrder($dbCmd, $NewOrderID);


// Now if the Customer Subtotal or the shipping charges are greater than 0 we need to authorize their credit card
$CustomerGrandTotal = Order::GetGrandTotalOfOrder($dbCmd, $NewOrderID);
$CustomerTax = Order::GetTotalFromOrder($dbCmd, $NewOrderID, "customertax");




$PaymentsObj = new Payments(Order::getDomainIDFromOrder($NewOrderID));



$PaymentsObj->LoadBillingInfoByOrderID($NewOrderID);
$PaymentsObj->SetTransactionAmounts($CustomerGrandTotal, $CustomerTax, $customershipping);



// Execute the transaction
$tranasctionSuccess = $PaymentsObj->AuthorizeFunds();

if(!$tranasctionSuccess){

	//The new charges were not approved, so roll back the transaction and display an error page.
	foreach($NewReprintIDsArr as $ThisProjectIDtoRemove){
	
		// Get Rid of the shipment
		Shipment::ShipmentRemoveProject($dbCmd, $ThisProjectIDtoRemove);

		// Delete the reprint 
		$dbCmd->Query("DELETE FROM projectsordered WHERE ID=$ThisProjectIDtoRemove");
	}

	// Delete the order
	$dbCmd->Query("DELETE FROM orders WHERE ID=$NewOrderID");

	WebUtil::PrintAdminError("The new charges for the reprint were not authorized on the customer's credit card. The reason is... " . $PaymentsObj->GetErrorReason());
}
else{
	//The transaction was approved... so make a record of it within the database
	$PaymentsObj->setOrderIdLink($NewOrderID);
	$PaymentsObj->CreateNewTransactionRecord(0);
	
	foreach($NewReprintIDsArr as $ThisReprintID)
		ProjectHistory::RecordProjectHistory($dbCmd, $ThisReprintID, $reprintDesc, $UserID);

}



// Set the Estimated Ship & Arrival Dates
Order::ResetProductionAndShipmentDates($dbCmd, $NewOrderID, "order", time());


// Copy any PDF settings from the project into the new reprint
foreach($NewReprintIDsArr as $ThisReprintID){

	$dbCmd->Query( "SELECT RePrintLink FROM projectsordered WHERE ID=$ThisReprintID" );
	$OriginalProjectID = $dbCmd->GetValue();

	PDFparameters::CopyPDFsettingsToNewProject($dbCmd, $dbCmd2, $OriginalProjectID, $ThisReprintID);	

}


if(!empty($projectids)){
	$returl = WebUtil::GetInput("returl", FILTER_SANITIZE_URL);
	header("Location: " . WebUtil::FilterURL($returl));
}
else{
	header("Location: ./ad_project.php?projectorderid=" . $NewProjectID);
}

?>
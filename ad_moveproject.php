<?

require_once("library/Boot_Session.php");


// It could take a while to communicate with the bank... just give the script at least 5 minutes to run.
set_time_limit(300);

$projectid = WebUtil::GetInput("projectid", FILTER_SANITIZE_INT);
$neworder = WebUtil::GetInput("neworder", FILTER_SANITIZE_STRING_ONE_LINE);
$useroverride = WebUtil::GetInput("useroverride", FILTER_SANITIZE_STRING_ONE_LINE);


WebUtil::checkFormSecurityCode();


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();



//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

$projectid = intval($projectid);


// Will Ensure Domain Permission on this Project.
ProjectBase::EnsurePrivilagesForProject($dbCmd,"admin", $projectid);


// We don't want people to change the status on a mailing batch because there could be a lot of money associated with Postage, etc.  There is not an easy way to issolate a Project once it has been grouped with others, printed, etc.
if(MailingBatch::checkIfProjectBatched($dbCmd, $projectid))
	WebUtil::PrintAdminError("You can not move a Project which has been already included within a Mailing Batch. If you really need to move this project then cancel the Mailing Batch first.");



if(preg_match("/^\d+-$/", $neworder)){

	// Take off the dash 
	$NewMainOrderID = substr($neworder, 0, -1);
	
	if(!Order::CheckIfUserHasPermissionToSeeOrder($NewMainOrderID)){
		WebUtil::PrintAdminError("The order number that you are trying to associate this project with is invalid.");
	}
	

	$PaymentsObj = new Payments(Order::getDomainIDFromOrder($NewMainOrderID));
	
		

	// Find out the user ID and order ID from the old project that we are moving from 
	$dbCmd->Query("SELECT orders.UserID, orders.ID AS OrderID, projectsordered.Status, orders.ShippingQuote 
			FROM orders INNER JOIN projectsordered ON projectsordered.OrderID = orders.ID 
			WHERE projectsordered.ID=$projectid");

	$row = $dbCmd->GetRow();
	$projectidUserID = $row["UserID"];
	$OldOrderID = $row["OrderID"];
	$ProjectStatus = $row["Status"];
	$OldShippingQuote = $row["ShippingQuote"];
	
	

	if(in_array($ProjectStatus, array("Q", "T", "B", "F", "C")))
		WebUtil::PrintAdminError("Projects may not be moved once the status becomes Queued, Printed, Boxed, Finished, or Canceled.");

	// Now get the user ID of the order that we are moving to.
	$dbCmd->Query("SELECT UserID, ShippingChoiceID, ShippingQuote FROM orders WHERE ID=$NewMainOrderID");
	$row = $dbCmd->GetRow();

	$NewOrderUserID = $row["UserID"];
	$ShippingChoiceID = $row["ShippingChoiceID"];
	$NewShippingQuote = $row["ShippingQuote"];

	if(empty($NewOrderUserID)){
		WebUtil::PrintAdminError("The order number that you are trying to associate this project with was not found.");
	}

	
	if(!Order::CheckIfUserHasPermissionToSeeOrder($OldOrderID) || !Order::CheckIfUserHasPermissionToSeeOrder($NewMainOrderID)){
		WebUtil::PrintAdminError("The order number that you are trying to associate this is not available.");
	}
		
	$domainIDofOldOrder = Order::getDomainIDFromOrder($OldOrderID);
	$domainIDofNewOrder = Order::getDomainIDFromOrder($NewMainOrderID);
	
	if($domainIDofOldOrder != $domainIDofNewOrder){
		WebUtil::PrintAdminError("You are trying to move the Project into an Order which belongs to another Domain.");
	}
	
	
	if($OldOrderID == $NewMainOrderID){
		WebUtil::PrintAdminError("You are trying to move a Project into its own Order. Maybe you made a mistake?");
	}
	

	// See if the order is closed...
	// We don't consider an order closed if it is empty
	if(!Order::CheckForEmptyOrder($dbCmd, $NewMainOrderID)){
	
		if(Order::CheckIfOrderComplete($dbCmd, $NewMainOrderID))
			WebUtil::PrintAdminError("Order# $NewMainOrderID is closed.  You can only move this project into an order which has other open projects.");
	}



	// If they just tried to associate the project with another order not having a matchin user ID.... then thy will be presented with the following error message.  The message contains a link with a parameter in the URL that bybasses this check.
	if($useroverride == ""){
		if($NewOrderUserID <> $projectidUserID){
			WebUtil::PrintAdminError("The order that you are trying to 
					associate this project with does not belong to the same user. 
					<br><br>Are you sure that you want to do this?.<br><br>
					<a href='./ad_moveproject.php?useroverride=yes&projectid=$projectid&neworder=$neworder&form_sc=".WebUtil::getFormSecurityCode()."'>
					Yes</a><br><br><br><a href='javascript:history.back();'>No</a>
					<br><br><br>", TRUE);
		}
	}



	// Get rid of the shipment links for this project
	Shipment::ShipmentRemoveProject($dbCmd, $projectid);
	
	// Now Switch around the info for the order ID
	$dbCmd->Query("UPDATE projectsordered set OrderID=$NewMainOrderID WHERE ID=$projectid");
	
	// Now that the project is removed from the old order, we need to recalculate the shipment weights
	Shipment::UpdatePackageParametersForOrder($dbCmd, $OldOrderID);

	// Now save the new quote since weights have changed
	Order::UpdateShippingQuote($dbCmd, $OldOrderID);
	Order::UpdateSalesTaxForOrder($dbCmd, $OldOrderID);


	// Lets set the shipment to a default state for the order we just moved the project into
	// Keep the same shipping method
	Shipment::ShipmentCreateForOrder($dbCmd, $dbCmd2, $NewMainOrderID, $ShippingChoiceID);

	// Now save the new quote since weights have changed
	Order::UpdateShippingQuote($dbCmd, $NewMainOrderID);


	// If the shipping quote on the old order is $0 and the new order is $0 then the combined shipping quote should be $0
	// This will prevent authorizations from occuring when combining 2 or more "free" reprints
	if($NewShippingQuote == 0 && $OldShippingQuote == 0)
		Order::UpdateShippingQuote($dbCmd, $NewMainOrderID, "0.00");
	
	// An increase in S&H charges could cause the ShippingTax to increase
	Order::UpdateSalesTaxForOrder($dbCmd, $NewMainOrderID);

	// Get the totals of the order to see if we need to re-authorize a credit card from the customer
	$GrandTotal = Order::GetGrandTotalOfOrder($dbCmd, $NewMainOrderID);
	$TaxTotal = Order::GetTotalFromOrder($dbCmd, $NewMainOrderID, "customertax");
	$ShippingQuote = Order::GetCustomerShippingQuote($dbCmd, $NewMainOrderID);

	// If a soft balance adjustment (with an Auth Only) is put on an order... it will not show up on the Invoice Grand Total.
	// Soft Balance adjustments can only be put on an order before the order is complete.  The pupose is to hide a "line item" on the invoice, but still charge the customer.
	$softBalanceAdjustments = BalanceAdjustment::getCustomerAdjustmentsTotalFromOrder_AuthOnly($dbCmd, $NewMainOrderID);
	
	// now we may have to reauthorize a larger charge on the person's credit card
	// Adding a project into another order will almost certainly increase the cost
	if($PaymentsObj->AuthorizeNewAmountForOrder($NewMainOrderID, ($GrandTotal + $softBalanceAdjustments), $TaxTotal, $ShippingQuote)){
	
		#-- Keep a record of the change --#
		ProjectHistory::RecordProjectHistory($dbCmd, $projectid, ("Project was moved from Order #" . $OldOrderID), $UserID);

		#-- Delete any PDF settings... this will force the Order Numbers to be re-generated when PDF settins are saved again.  --#
		if($ProjectStatus == "P" || $ProjectStatus == "N")
			$dbCmd->Query("DELETE FROM pdfgenerated WHERE ProjectRef=$projectid");


		//If it is proofed... set the status back to new.  This will force the admin to regenerate the PDF settings
		if($ProjectStatus == "P")
			$dbCmd->Query("UPDATE projectsordered set Status='N' WHERE ID=$projectid");

		//Since the project was moved out from the old order... it may have completed it. We may need charge the customer for that order now.
		Order::OrderChangedCheckForPaymentCapture($dbCmd, $OldOrderID, $PaymentsObj);


		#-- Redirect to the same project ID... This time it will belong to a different Order number ---##
		header("Location: ./ad_project.php?projectorderid=$projectid");
	}
	else{
	
		// Well the card was not authorized... we need to roll back the changes --


		// Get rid of the shipment links for this project
		Shipment::ShipmentRemoveProject($dbCmd, $projectid);

		// Now Switch around the info for the order ID 
		$dbCmd->Query("UPDATE projectsordered set OrderID=$OldOrderID WHERE ID=$projectid");

		// Now that the project is removed from the old order, we need to recalculate the shipment weights
		Shipment::UpdatePackageParametersForOrder($dbCmd, $NewMainOrderID);

		// Put the shipping Quote back to what it was before we tried moving anything
		Order::UpdateShippingQuote($dbCmd, $NewMainOrderID, $NewShippingQuote);
		Order::UpdateSalesTaxForOrder($dbCmd, $NewMainOrderID);

		// Lets set the shipment to a default state for the order we just moved the project into
		// Keep the same shipping method
		Shipment::ShipmentCreateForOrder($dbCmd, $dbCmd2, $OldOrderID, $ShippingChoiceID);

		// Put the shipping Charges back to what they were before we tried moving anything
		Order::UpdateShippingQuote($dbCmd, $OldOrderID, $OldShippingQuote);
		Order::UpdateSalesTaxForOrder($dbCmd, $OldOrderID);
		

		// The new charges were not approved, so display an error page
		$ErrorMessage = "Moving this project caused the total price of the order to increase.  The new charges were not authorized on the customer's credit card.  The reason is... ";
		$ErrorMessage .= WebUtil::htmlOutput($PaymentsObj->GetErrorReason());

		WebUtil::PrintAdminError($ErrorMessage);
	}


}
else{
	WebUtil::PrintAdminError("The Order Number was not received in the URL properly.");
}




?>
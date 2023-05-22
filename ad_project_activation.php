<?

require_once("library/Boot_Session.php");

// It could take a while to communicate with the bank... just give the script at least 5 minutes to run.
set_time_limit(300);


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


WebUtil::checkFormSecurityCode();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


$projectid = WebUtil::GetInput("projectid", FILTER_SANITIZE_INT);
$orderactive = WebUtil::GetInput("orderactive", FILTER_SANITIZE_STRING_ONE_LINE);


ProjectBase::EnsurePrivilagesForProject($dbCmd, "ordered", $projectid);

$orderno = Order::GetOrderIDfromProjectID($dbCmd, $projectid);


$UserControlObj = new UserControl($dbCmd2);

$PaymentsObj = new Payments(Order::getDomainIDFromOrder($orderno));

$dbCmd->Query("SELECT orders.ShippingChoiceID, orders.UserID FROM orders 
		INNER JOIN projectsordered ON orders.ID = projectsordered.OrderID
		WHERE projectsordered.ID=$projectid");
$row = $dbCmd->GetRow();

$shippingChoiceID = $row["ShippingChoiceID"];
$customerUserID = $row["UserID"];

$UserControlObj->LoadUserByID($customerUserID);


// We don't want people to change the status on a mailing batch because there could be a lot of money associated with Postage, etc.  There is not an easy way to issolate a Project once it has been grouped with others, printed, etc.
if(MailingBatch::checkIfProjectBatched($dbCmd, $projectid))
	WebUtil::PrintAdminError("You can not change the status on a Project which has been already included within a Mailing Batch.  If you really need to cancel this project then cancel the Mailing Batch first.");




// Find out if the are trying to Cancel or reactivate the order
if($orderactive == "Re-Activate" || $orderactive == "Re-Activate Re-print"){

	// We are reactivating the project
	// Update database, set the status to NEW.
	ProjectOrdered::ChangeProjectStatus($dbCmd, "N", $projectid);

	// Set the shipment to a default state for the order we just reactivated
	Shipment::ShipmentCreateForOrder($dbCmd, $dbCmd2, $orderno, $shippingChoiceID);

	// Now save the new quote for the customer shipping charges
	Order::UpdateShippingQuote($dbCmd, $orderno);
	Order::UpdateSalesTaxForOrder($dbCmd, $orderno);

	//- Now the problem is that there is a possiblity that re-activating a project could push the total of the order over the authorized limit
	// We need to check the totals of the order after reactiving
	$GrandTotal = Order::GetGrandTotalOfOrder($dbCmd, $orderno);
	$TaxTotal = Order::GetTotalFromOrder($dbCmd, $orderno, "customertax");
	$FreightTotal = Order::GetCustomerShippingQuote($dbCmd, $orderno);


	// If a soft balance adjustment (with an Auth Only) is put on an order... it will not show up on the Invoice Grand Total.
	// Soft Balance adjustments can only be put on an order before the order is complete.  The pupose is to hide a "line item" on the invoice, but still charge the customer.
	$softBalanceAdjustments = BalanceAdjustment::getCustomerAdjustmentsTotalFromOrder_AuthOnly($dbCmd, $orderno);
	
	//now we may have to reauthorize a larger charge on the person's credit card
	if(!$PaymentsObj->AuthorizeNewAmountForOrder($orderno, ($GrandTotal + $softBalanceAdjustments), $TaxTotal, $FreightTotal)){

		//  Well the authorization faild... so let us set the project back to a canceled state
		ProjectOrdered::ChangeProjectStatus($dbCmd, "C", $projectid);

		// Get rid of the shipment links for this project 
		Shipment::ShipmentRemoveProject($dbCmd, $projectid);

		// Now that the project is removed from the old order, we need to recalculate the shipment weights 
		Shipment::UpdatePackageParametersForOrder($dbCmd, $orderno);

		// Now save the new quote for the customer shipping charges
		Order::UpdateShippingQuote($dbCmd, $orderno);
		Order::UpdateSalesTaxForOrder($dbCmd, $orderno);

		//The new charges were not approved, so display an error page
		$ErrorMessage = "Re-activating this project caused the total price of the order to increase. The new charges were not authorized on the customer's credit card.  The reason is... " . $PaymentsObj->GetErrorReason();
		WebUtil::PrintAdminError($ErrorMessage);
	}
	else{
		ProjectHistory::RecordProjectHistory($dbCmd, $projectid, "N", $UserID);

		// Reset the Estimated Ship & Arrival Dates
		Order::ResetProductionAndShipmentDates($dbCmd, $projectid, "project", time());
	}

}
else if($orderactive == "Cancel Order" || $orderactive == "Cancel Re-print"){

	ProjectOrdered::ChangeProjectStatus($dbCmd, "C", $projectid);

	// Get rid of the shipment links for this project
	Shipment::ShipmentRemoveProject($dbCmd, $projectid);

	// Now that the project is removed from the old order, we need to recalculate the shipment weights
	Shipment::UpdatePackageParametersForOrder($dbCmd, $orderno);

	// Now save the new quote for the customer shipping charges
	Order::UpdateShippingQuote($dbCmd, $orderno);
	Order::UpdateSalesTaxForOrder($dbCmd, $orderno);

	// Since the project was just canceled, we may need charge the customer now.
	Order::OrderChangedCheckForPaymentCapture($dbCmd, $orderno, $PaymentsObj);

	ProjectHistory::RecordProjectHistory($dbCmd, $projectid, "C", $UserID);
	
	$domainIDofOrder = Order::getDomainIDFromOrder($orderno);
	
	$fullCompanyNameOfDomain = Domain::getFullCompanyNameForDomainID($domainIDofOrder);
	
	// Send a notification email to the customer when the last project within an order has been canceled.
	if(!Order::CheckForActiveProjectWithinOrder($dbCmd, $orderno)){
		
		$emailMessage = "Dear " . $UserControlObj->getNameFirst() . ",\n\n";
		$emailMessage .= "This email confirms that Order #" . Order::GetHashedOrderNo($orderno) . " has been canceled.\n\n";
		$emailMessage .= "Our system will never capture funds from a customer's credit card before the product has been shipped.  ";
		$emailMessage .= "You may still have a \"pending\" authorization sitting on your card but it will expire within about 2 weeks.  ";
		$emailMessage .= "If you need further assistance please don't hesitate to ask us.\n\nThank You,\n$fullCompanyNameOfDomain\n\n";
		
		$emailSubject = "Confirmation of Order Cancellation";
	
		$domainEmailConfigObj = new DomainEmails($domainIDofOrder);
		
		WebUtil::SendEmail($domainEmailConfigObj->getEmailNameOfType(DomainEmails::CUSTSERV), $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::CUSTSERV), $UserControlObj->getName(), $UserControlObj->getEmail(), $emailSubject, $emailMessage);
	}
}
else{
	throw new Exception("No Action was passed for 'order active'");
}

// Canceling a big order could have an affect the user User Rating.
UserControl::updateUserRating($customerUserID);


$returnurl = WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL);
if(!$returnurl)
	throw new Exception("No Return URL has been submitted.");

header("Location: " . WebUtil::FilterURL($returnurl));



?>
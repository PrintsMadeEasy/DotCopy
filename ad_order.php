<?

require_once("library/Boot_Session.php");

// It could take a while to communicate with the bank... just give the script at least 5 minutes to run.
set_time_limit(300);

$orderno = WebUtil::GetInput("orderno", FILTER_SANITIZE_INT);


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!Order::CheckIfUserHasPermissionToSeeOrder($orderno)){
	WebUtil::PrintAdminError("Order number does not exist.");
}


// Make sure that all Objects on this script are using the same Domain ID.
// This will also show the Logo of the Domain that was ordered through in the Nav Bar.
$domainID = Order::getDomainIDFromOrder($orderno);
Domain::enforceTopDomainID($domainID);

$domainObj = Domain::singleton();




$UserControlObj = new UserControl($dbCmd2);

$PaymentsObj = new Payments($domainID);


$navHistoryObj = new NavigationHistory($dbCmd2);

$shippingChoiceObj = new ShippingChoices();
$shippingMethodObj = new ShippingMethods();



if(!$AuthObj->CheckForPermission("ORDER_SCREEN"))
	WebUtil::PrintAdminError("Not Available");



// If this person is a vendor then we want to restrict the projects to them. --#
if($AuthObj->CheckIfBelongsToGroup("VENDOR"))
	$VendorRestriction = $UserID;
else
	$VendorRestriction = "";




$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if(!empty($action)){
	
	// Specify which Actions do not require a Security Code for Cross-site Forgery Requests
	if(!in_array($action, array("GetOrderSummaryHTML")))
		WebUtil::checkFormSecurityCode();
		
		

	if($action == "updateinvoicemessage"){

		$invoicemessage = WebUtil::GetInput("invoicemessage", FILTER_SANITIZE_STRING_ONE_LINE);

		$dbCmd->UpdateQuery("orders", array("InvoiceNote" => $invoicemessage), "ID=$orderno");

		header("Location: " . WebUtil::FilterURL(WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL)) . "&nocache=". time());
		exit;
	}
	else if($action == "updateshippinginstructions"){

		$shippingInstructions = WebUtil::GetInput("shippingInstructions", FILTER_SANITIZE_STRING_MULTI_LINE);

		$dbCmd->UpdateQuery("orders", array("ShippingInstructions" => $shippingInstructions), "ID=$orderno");

		header("Location: " . WebUtil::FilterURL(WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL)) . "&nocache=". time());
		exit;
	}
	else if($action == "RecalculateShipDate"){
	
	
		$sMonth = WebUtil::GetInput("month", FILTER_SANITIZE_INT);
		$sDay = WebUtil::GetInput("day", FILTER_SANITIZE_INT);
		$sYear = WebUtil::GetInput("year", FILTER_SANITIZE_INT);
		$sHour = WebUtil::GetInput("hour", FILTER_SANITIZE_INT);
		$sMinute = WebUtil::GetInput("minute", FILTER_SANITIZE_INT);
		$sAMPM = WebUtil::GetInput("ampm", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
		
		if($sAMPM == "pm")
			$sHour += 12;
		
		$newDateOrderedTimeStamp = mktime($sHour, $sMinute, 1, $sMonth, $sDay, $sYear);

		Order::ResetProductionAndShipmentDates($dbCmd, $orderno, "order", $newDateOrderedTimeStamp );
		
		header("Location: " . WebUtil::FilterURL(WebUtil::GetInput("returl", FILTER_SANITIZE_URL)) . "&neworderfromdate=$newDateOrderedTimeStamp&nocache=". time());
		exit;
	}
	else if($action == "changepackageweight"){

		$weight = WebUtil::GetInput("weight", FILTER_SANITIZE_INT);
		$shipmentid = WebUtil::GetInput("shipmentid", FILTER_SANITIZE_INT);
		
		$orderIDofShipment = Shipment::GetOrderIDfromShipmentID($dbCmd, $shipmentid);
		
		if(!Order::CheckIfUserHasPermissionToSeeOrder($orderIDofShipment))
			throw new Exception("Can't chagne the package weight because the order number does not exist.");
		
		Shipment::ShipmentSetPackageWeight($dbCmd, $shipmentid, $weight);


		header("Location: ./" . WebUtil::FilterURL(WebUtil::GetInput("returl", FILTER_SANITIZE_URL)));
		exit;
	}
	else if($action == "movetonewshipment"){
	
		$quantity = WebUtil::GetInput("quantity", FILTER_SANITIZE_INT);
		$shipmentlink = WebUtil::GetInput("shipmentlink", FILTER_SANITIZE_INT);
		
		$shipmentID = Shipment::getShipmentIDfromShippingLinkID($dbCmd, $shipmentlink);
		
		if(empty($shipmentID))
			throw new Exception("Can't move to a new shipment because the Shipping Link doesn't exist.");
			
		$orderIDofShipment = Shipment::GetOrderIDfromShipmentID($dbCmd, $shipmentID);
		
		if(!Order::CheckIfUserHasPermissionToSeeOrder($orderIDofShipment))
			throw new Exception("Can't move to a new shipment because the Order Number doesn't exist.");
	
		Shipment::ShipmentMoveQuantityToNewShipment($dbCmd, $quantity, $shipmentlink);

		header("Location: ./" . WebUtil::FilterURL(WebUtil::GetInput("returl", FILTER_SANITIZE_URL)));
		exit;
	}
	else if($action == "urgent"){
	
		$dbCmd->Query("UPDATE projectsordered SET Priority='U' WHERE OrderID=$orderno");

		header("Location: ./" . WebUtil::FilterURL(WebUtil::GetInput("returl", FILTER_SANITIZE_URL)));
		exit;
	}
	else if($action == "resetShipment"){
	
		$shippingChoiceID = Order::getShippingChoiceIDfromOrder($dbCmd, $orderno);
		Shipment::ShipmentCreateForOrder($dbCmd, $dbCmd2, $orderno, $shippingChoiceID);

		header("Location: ./" . WebUtil::FilterURL(WebUtil::GetInput("returl", FILTER_SANITIZE_URL)));
		exit;
	}
	else if($action == "noturgent"){
	
		$dbCmd->Query("UPDATE projectsordered SET Priority='N' WHERE OrderID=$orderno");

		header("Location: ./" . WebUtil::FilterURL(WebUtil::GetInput("returl", FILTER_SANITIZE_URL)));
		exit;
	}
	else if($action == "combineshipments"){
	
		$shipmentidlist = WebUtil::GetInput("shipmentidlist", FILTER_SANITIZE_STRING_ONE_LINE);
		$ShipmentIDArr = split("\|", $shipmentidlist);

		//The master ShipID is the one for which all other shipments will be combined with
		//The master is always the first shipment we come across
		$MasterID = 0;

		for($i=0; $i<sizeof($ShipmentIDArr); $i++){
			//Make sure that the ID is a valid number
			if(preg_match("/^\d+$/", $ShipmentIDArr[$i])){
				
				$orderIDofShipment = Shipment::GetOrderIDfromShipmentID($dbCmd, $ShipmentIDArr[$i]);
				
				if(!Order::CheckIfUserHasPermissionToSeeOrder($orderIDofShipment))
					throw new Exception("Can't compbine shipments because the order number does not exist.");
				
				if($i == 0 )
					$MasterID = $ShipmentIDArr[$i];
				else
					Shipment::ShipmentCombine($dbCmd, $MasterID, $ShipmentIDArr[$i]);
			}
		}

		header("Location: ./" . WebUtil::FilterURL(WebUtil::GetInput("returl", FILTER_SANITIZE_URL)));
		exit;
	}
	else if($action == "changeshippingtotal"){

		$shippingtotal = WebUtil::GetInput("shippingtotal", FILTER_SANITIZE_FLOAT);

		// We need the shipping state to calculate new Taxes for the shipping charges
		$dbCmd->Query("SELECT ShippingState FROM orders WHERE ID=" .$orderno);
		$ShippingState = $dbCmd->GetValue();

		$shippingtotal = number_format($shippingtotal, 2);

		//Find out the current charges of this order
		$GrandTotal = Order::GetGrandTotalOfOrder($dbCmd, $orderno);
		$SubtotalTax = Order::GetTotalFromOrder($dbCmd, $orderno, "subtotaltax");
		$ShippingTax = Order::GetTotalFromOrder($dbCmd, $orderno, "shippingtax");
		$OldShippingQuote = Order::GetCustomerShippingQuote($dbCmd, $orderno);

		$GrandTotalWithoutShippingAmount = $GrandTotal - $OldShippingQuote - $ShippingTax;

		// Find out how much tax will be charged on the new shipping total
		$NewShippingTax = number_format(round((Constants::GetSalesTaxConstant($ShippingState) * $shippingtotal), 2), 2);

		// Let's find out what the grand total would be with the new shipping total
		$NewGrandTotal = $GrandTotalWithoutShippingAmount + $shippingtotal + $NewShippingTax;

		// If a soft balance adjustment (with an Auth Only) is put on an order... it will not show up on the Invoice Grand Total.
		// Soft Balance adjustments can only be put on an order before the order is complete.  The pupose is to hide a "line item" on the invoice, but still charge the customer.
		$softBalanceAdjustments = BalanceAdjustment::getCustomerAdjustmentsTotalFromOrder_AuthOnly($dbCmd, $orderno);
		
		// Now we may have to reauthorize a larger charge on the person's credit card
		if($PaymentsObj->AuthorizeNewAmountForOrder($orderno, ($NewGrandTotal + $softBalanceAdjustments), ($SubtotalTax + $NewShippingTax), $shippingtotal)){

			// The new carges were approved... we can update the database
			Order::UpdateShippingQuote($dbCmd, $orderno, $shippingtotal);
			Order::UpdateSalesTaxForOrder($dbCmd, $orderno);
			

			// Record the Shipping Total change in each Project History
			$dbCmd->Query("SELECT ID FROM projectsordered WHERE OrderID=$orderno AND Status!='C'");	
			while($pid = $dbCmd->GetValue())
				$projectIDArr[] = $pid;
			foreach($projectIDArr as $pid)
				ProjectHistory::RecordProjectHistory($dbCmd, $pid, "Shipping charges manually changed on Order # " . $orderno . " from \$" . $OldShippingQuote . " to \$" . $shippingtotal, $UserID);	
		

			header("Location: " . WebUtil::FilterURL(WebUtil::GetInput("returl", FILTER_SANITIZE_URL)) . "&nocache=". time());
			exit;
		}
		else{
			// The new charges were not approved, so display an error page
			$ErrorMessage = "The new shipping charges were not authorized on the customer's credit card.  The reason is... " . $PaymentsObj->GetErrorReason();
			WebUtil::PrintAdminError($ErrorMessage);
		}
	
	}
	else if($action == "customershippingmethod"){
	

		$shippingChoiceID = WebUtil::GetInput("shippingChoiceID", FILTER_SANITIZE_INT);
		$returnurl = WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL);
		
		
		$domainAddressObj = new DomainAddresses(Domain::oneDomain());
		$shipFromAddressObj = $domainAddressObj->getDefaultProductionFacilityAddressObj();
		$shipToAddressObj = Order::getShippingAddressObject($dbCmd, $orderno);
		
		
		$availableShippingChoicesArr = $shippingChoiceObj->getAvailableShippingChoices($shipFromAddressObj, $shipToAddressObj);
		
		
		// It is possible that our Customer Shipping Method drop down could have "unknown" in it for various reasons.
		if(!in_array($shippingChoiceID, array_keys($availableShippingChoicesArr)))
			WebUtil::PrintAdminError("The shipping method is not valid. Please go back and choose another one.");


		$dbCmd->Query("SELECT ShippingCity, ShippingState, ShippingZip, ShippingCountry, ShippingQuote, ShippingResidentialFlag, UNIX_TIMESTAMP(DateOrdered) as DateOrdered FROM orders WHERE ID=$orderno");
		$row = $dbCmd->GetRow();
		$ShippingCity = $row["ShippingCity"];
		$ShipState = $row["ShippingState"];
		$ShipZip = $row["ShippingZip"];
		$ShipCountry = $row["ShippingCountry"];
		$ShippingResidentialFlag = $row["ShippingResidentialFlag"];
		$OldShippingQuote = $row["ShippingQuote"];
		$DateOrderedTimeStamp = $row["DateOrdered"];


		// Find out the current charges of this order
		$GrandTotal = Order::GetGrandTotalOfOrder($dbCmd, $orderno);
		$SubtotalTax = Order::GetTotalFromOrder($dbCmd, $orderno, "subtotaltax");
		$ShippingTax = Order::GetTotalFromOrder($dbCmd, $orderno, "shippingtax");

		$GrandTotalWithoutShippingAmount = $GrandTotal - $OldShippingQuote - $ShippingTax;

		$shippingAddressObj = new MailingAddress("person", "company name", "Address1", "", $ShippingCity, $ShipState, $ShipZip, $ShipCountry, ($ShippingResidentialFlag == "Y" ? true : false), "");
		
		// If we are not charging the customer for shipping before the change, then we shouldn't charge them after the change.
		// Otherwise let's find out what the grand total would be with the new shipping method
		if($OldShippingQuote == "0.00")
			$NewShippingQuote = "0.00";
		else
			$NewShippingQuote = ShippingPrices::getTotalShippingPriceForGroup($orderno, "order", $shippingChoiceID, $shippingAddressObj);			
			
		// Find out how much tax will be charged on the new shipping total
		$NewShippingTax = number_format(round((Constants::GetSalesTaxConstant($ShipState) * $NewShippingQuote), 2), 2);

		// This is what the new grand total would be if we were to change the shipping method 
		$NewGrandTotal = $GrandTotalWithoutShippingAmount + $NewShippingQuote + $NewShippingTax;

		// If a soft balance adjustment (with an Auth Only) is put on an order... it will not show up on the Invoice Grand Total.
		// Soft Balance adjustments can only be put on an order before the order is complete.  The pupose is to hide a "line item" on the invoice, but still charge the customer.
		$softBalanceAdjustments = BalanceAdjustment::getCustomerAdjustmentsTotalFromOrder_AuthOnly($dbCmd, $orderno);
		
		// now we may have to reauthorize a larger charge on the person's credit card
		if($PaymentsObj->AuthorizeNewAmountForOrder($orderno, ($NewGrandTotal + $softBalanceAdjustments), ($SubtotalTax + $NewShippingTax), $NewShippingQuote)){

			// Change all of the shipping methods for all shipments associated with this order.
			$shipIDarr = Shipment::GetShipmentsIDsWithinOrder($dbCmd, $orderno, 0, "");

			// Set the Shipment Method to be the default Shipping Mehod for the Shipping Choice
			// It could get Downgraded at the time the Package is ready for shipment.
			$shippingMethodCode = $shippingChoiceObj->getDefaultShippingMethodCode($shippingChoiceID);
			
			foreach($shipIDarr as $ThisShipID){

				// Can't change the shipping method on a finished package
				if(!Shipment::CheckIfShipmentHasGone($dbCmd, $ThisShipID))
					Shipment::ShipmentSetShippingMethod($dbCmd, $ThisShipID, $shippingMethodCode);
			}

			// Update the customer shipping method
			$dbCmd->Query("UPDATE orders set ShippingChoiceID=\"". $shippingChoiceID .  "\" WHERE ID=$orderno");
			
			Order::ChangeShippingPriorityOnProjects($dbCmd, $orderno);

			// Now save the new quote for the customer shipping charges --#
			Order::UpdateShippingQuote($dbCmd, $orderno, $NewShippingQuote);
			Order::UpdateSalesTaxForOrder($dbCmd, $orderno);

			// Reset the Arrival date since the Shipping method was changed.
			// Set the last flag as TRUE so that the new Arrival Date will be calculated off of the current Ship Date.
			Order::ResetProductionAndShipmentDates($dbCmd, $orderno, "order", $DateOrderedTimeStamp, true);

			Order::RecordShippingChoiceHistory($dbCmd, $orderno, $UserID);

			header("Location: " . WebUtil::FilterURL($returnurl) . "&nocache=". time());
			exit;
		}
		else{
			//The new charges were not approved, so display an error page
			$ErrorMessage = "The new shipping charges were not authorized on the customer's credit card.  The reason is... " . $PaymentsObj->GetErrorReason();
			WebUtil::PrintAdminError($ErrorMessage);
		}

	}
	else if($action == "changeshippingaddress"){
		
		$shippingname = WebUtil::GetInput("shippingname", FILTER_SANITIZE_STRING_ONE_LINE);
		$shippingcompany = WebUtil::GetInput("shippingcompany", FILTER_SANITIZE_STRING_ONE_LINE);
		$shippingaddress = WebUtil::GetInput("shippingaddress", FILTER_SANITIZE_STRING_ONE_LINE);
		$shippingaddress2 = WebUtil::GetInput("shippingaddress2", FILTER_SANITIZE_STRING_ONE_LINE);
		$shippingcity = WebUtil::GetInput("shippingcity", FILTER_SANITIZE_STRING_ONE_LINE);
		$shippingzip = WebUtil::GetInput("shippingzip", FILTER_SANITIZE_STRING_ONE_LINE);
		$shippingstate = WebUtil::GetInput("shippingstate", FILTER_SANITIZE_STRING_ONE_LINE);
		$shippingcountry = WebUtil::GetInput("shippingcountry", FILTER_SANITIZE_STRING_ONE_LINE);
		$shippingresidentialflag = WebUtil::GetInput("residentialflag", FILTER_SANITIZE_STRING_ONE_LINE);
		$returnurl = WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL);
	
		if(empty($shippingname) || empty($shippingaddress) || empty($shippingcity) || empty($shippingzip) || empty($shippingstate) || empty($shippingcountry))
			WebUtil::PrintAdminError("One of the shipping details was left blank.");



			
		// Check and see if the address is valid with UPS
		// The address validation only works when shipping within the United States
		if($shippingcountry == "US"){
			$upsResponseObj = UPS_AV::ValidateShippingAddress($shippingcity, $shippingstate, $shippingzip);

			if($upsResponseObj->CheckIfCommunicationError() || $upsResponseObj->GetErrorCode() != ""){
				WebUtil::PrintAdminError(UPS_AV::GetUPSerrorMessageForCustomer());
			}


			// Find out if the Address is verfied by UPS
			if(!$upsResponseObj->CheckIfAddressIsOK()){

				// This array will contain a list of alternate suggestions... or it may return with 1 single exact match --#
				$ValidationResultsArr = $upsResponseObj->GetValidationResults();

				// Make a string out of the results
				$suggestionsStr = "";
				foreach($ValidationResultsArr as $suggestionResult){
					$suggestionsStr .= WebUtil::htmlOutput($suggestionResult->City) . ", " . WebUtil::htmlOutput($suggestionResult->State) . "&nbsp;&nbsp;" . WebUtil::htmlOutput($suggestionResult->postalLow) . " - " . WebUtil::htmlOutput($suggestionResult->postalHigh) . "<br><br>";
				}

				if($upsResponseObj->EmptySuggestions())
					$suggestionsStr = "No Suggestions were found for the address.  Double check the City/State/Postal code to make sure that they are correct.";

				$ErrorMessage = "<br><br><br><div align=center>The new shipping address does not seem to be valid. <br><br><a href='javascript:history.back();'>Go Back</a> to fix the problem.<br><br><br>Here are some other suggestions ....</div><br><br>";
				$ErrorMessage .= "<font color='#000000'>$suggestionsStr</font>";
				WebUtil::PrintAdminError($ErrorMessage, TRUE);
			}
		}
		
		
		$domainAddressObj = new DomainAddresses(Domain::oneDomain());
		$shipFromAddressObj = $domainAddressObj->getDefaultProductionFacilityAddressObj();
		$newShipToAddressObj = new MailingAddress($shippingname, $shippingcompany, $shippingaddress, $shippingaddress2, $shippingcity, $shippingstate, $shippingzip, $shippingcountry, ($shippingresidentialflag == "Y" ? true : false), "111-111-1111" );
		
		
		$availableShippingChoicesArr = $shippingChoiceObj->getAvailableShippingChoices($shipFromAddressObj, $newShipToAddressObj);
		
		$shippingChoiceID = Order::getShippingChoiceIDfromOrder($dbCmd, $orderno);
		
		// It is possible that our Customer Shipping Method drop down could have "unknown" in it for various reasons.
		if(!in_array($shippingChoiceID, array_keys($availableShippingChoicesArr)))
			WebUtil::PrintAdminError("The currently selected Shipping Choice must be available to the new address in order to change.  Try changing the Shipping Choice to a more common method before making the address change.");
			
			

		// Changing this shipping address could cause an increase in Sales Tax... if it does we need to re-authorize charges on the customer credit card --#
		$OldSalesTax = Order::GetTotalFromOrder($dbCmd, $orderno, "customertax");
		$OrderSubtotal = Order::GetTotalFromOrder($dbCmd, $orderno, "customersubtotal");
		$OrderDiscount = Order::GetTotalFromOrder($dbCmd, $orderno, "customerdiscount");
		$CustomerShippingQuote = Order::GetCustomerShippingQuote($dbCmd, $orderno);
		$NewSalesTax = round((Constants::GetSalesTaxConstant($shippingstate) * ($OrderSubtotal  -  $OrderDiscount + $CustomerShippingQuote)),2);
		$NewGrandTotal = $OrderSubtotal - $OrderDiscount + $CustomerShippingQuote + $NewSalesTax;

		if($NewSalesTax > $OldSalesTax){

			// If a soft balance adjustment (with an Auth Only) is put on an order... it will not show up on the Invoice Grand Total.
			// Soft Balance adjustments can only be put on an order before the order is complete.  The pupose is to hide a "line item" on the invoice, but still charge the customer.
			$softBalanceAdjustments = BalanceAdjustment::getCustomerAdjustmentsTotalFromOrder_AuthOnly($dbCmd, $orderno);
			
			if(!$PaymentsObj->AuthorizeNewAmountForOrder($orderno, ($NewGrandTotal + $softBalanceAdjustments), $NewSalesTax, $CustomerShippingQuote)){

				//The new charges were not approved, so display an error page
				$ErrorMessage = "Changing the shipping address caused an increase in Sales Tax.  The new charges were not authorized on the customer's credit card.  The reason is... " . $PaymentsObj->GetErrorReason();
				WebUtil::PrintAdminError($ErrorMessage);
			}
		}

		// Make sure that U.S. state codes are 2 letters and upper case
		if($shippingcountry == "US")
			$shippingstate = WebUtil::CapitilizeUnitedState($shippingstate);


		// Everything looks good so far, so update the Database
		$updateArr["ShippingName"] = $shippingname;
		$updateArr["ShippingCompany"] = $shippingcompany;
		$updateArr["ShippingAddress"] = $shippingaddress;
		$updateArr["ShippingAddressTwo"] = $shippingaddress2;
		$updateArr["ShippingCity"] = $shippingcity;
		$updateArr["ShippingState"] = $shippingstate;
		$updateArr["ShippingZip"] = $shippingzip;
		$updateArr["ShippingCountry"] = $shippingcountry;
		$updateArr["ShippingResidentialFlag"] = $shippingresidentialflag;
		$dbCmd->UpdateQuery("orders", $updateArr, "ID=$orderno");


		// The sales tax could have increased... but we have already checked for an authorization above
		Order::UpdateSalesTaxForOrder($dbCmd, $orderno);

		Order::RecordShippingAddressHistory($dbCmd, $orderno, $UserID);


		header("Location: ". WebUtil::FilterURL($returnurl));
		exit;
	}
	else if($action == "GetOrderSummaryHTML"){
	
		$errorFlag = false;
		$ErrorDescription = "";
		
		
		if(!$AuthObj->CheckForPermission("ORDER_SUMMARY_POPUPS")){
			$errorFlag = true;
			$ErrorDescription = "Order Summary Pop-ups permission denied.";
		}
			
		
		// Get the shipping address
		$dbCmd->Query("SELECT ShippingName, ShippingAddress, ShippingAddressTwo, ShippingState, ShippingZip, ShippingCity, ShippingCountry, BannerReferer, Referral, SessionID FROM orders WHERE ID=" .$orderno);
		$row = $dbCmd->GetRow();
		

		$retHTML = "<u><b>Ship To:</b></u> " . WebUtil::htmlOutput($row["ShippingName"]) . "<br>" . WebUtil::htmlOutput($row["ShippingAddress"] . " " . $row["ShippingAddressTwo"]) . "<br>" . WebUtil::htmlOutput($row["ShippingCity"]) . ", <b>" . WebUtil::htmlOutput($row["ShippingState"]) . "</b> " . WebUtil::htmlOutput($row["ShippingZip"]) . " " . WebUtil::htmlOutput($row["ShippingCountry"]) . "<br>&nbsp;<br>";

		$BannerReferer = $row["BannerReferer"];
		$Referral = $row["Referral"];
		$orderSessionID = $row["SessionID"];


		$customerShipping = Order::GetCustomerShippingQuote($dbCmd, $orderno);
		$customerSubtotal = Order::GetTotalFromOrder($dbCmd, $orderno, "customersubtotal");
		$customerDiscount = Order::GetTotalFromOrder($dbCmd, $orderno, "customerdiscount");
		$customerTax = Order::GetTotalFromOrder($dbCmd, $orderno, "customertax");
		$vendorSubtotal = Order::GetTotalFromOrder($dbCmd, $orderno, "vendorsubtotal", $VendorRestriction);


		$retHTML .= "Subtotal: \$" . number_format($customerSubtotal, 2) . "<br>";
		$retHTML .= "Shipping: \$" . number_format($customerShipping, 2) . "<br>";
		$retHTML .= "Discount: \$" . number_format($customerDiscount, 2) . "<br>";
		$retHTML .= "Sales Tax: \$" . number_format($customerTax, 2). "<br>";
		$retHTML .= "Vendor: \$" . number_format($vendorSubtotal, 2) . "<br>";
		$retHTML .= "Grand T: <b>\$" . number_format($customerSubtotal - $customerDiscount + $customerTax + $customerShipping, 2) . "</b><br>";
		$retHTML .= "Income: <b>\$" . number_format($customerSubtotal - $vendorSubtotal - $customerDiscount, 2) . "</b><br>";
		
		
		
		$retHTML .= "<br><u>Order Summary</u>";
		
		$dbCmd->Query("SELECT DISTINCT(ProductID) FROM projectsordered WHERE OrderID=" .$orderno);
		while($productID = $dbCmd->GetValue()){
		
			$retHTML .= "<br>" . WebUtil::htmlOutput(Product::getFullProductName($dbCmd2, $productID));

			
			$dbCmd2->Query("SELECT COUNT(*) FROM projectsordered WHERE OrderID=" .$orderno . " AND ProductID=" . $productID);
			$setCount = $dbCmd2->GetValue();

			$retHTML .= " : <b>" . $setCount . LanguageBase::GetPluralSuffix($setCount, " set", " sets") . "</b>";
			
			
			$dbCmd2->Query("SELECT SUM(Quantity) FROM projectsordered WHERE OrderID=" .$orderno . " AND ProductID=" . $productID);
			$unitCount = $dbCmd2->GetValue();
			
			// If the Unit count equals the product count then no sense of showing the extra information.
			if($setCount != $unitCount)
				$retHTML .= " (" . number_format($unitCount) . " pieces)";
			
		}
		$retHTML .= "<br>";
		
		
		
		if($AuthObj->CheckForPermission("EXTRA_MARKETING_DETAILS_ORDER")){

			if(!empty($Referral))
				$retHTML .= "<img src='./images/transparent.gif' width='5' height='5'><br><b>Banner Click: </b> <font color='#000099'>" . WebUtil::htmlOutput($Referral) . "</font>";


			if(!empty($BannerReferer)){

				$bannerDomain = WebUtil::getDomainFromURL($BannerReferer);
				$bannerKeywords = WebUtil::getKeywordsFromSearchEngineURL($BannerReferer);

				$retHTML .= "<br><b>Domain: </b> " . WebUtil::htmlOutput($bannerDomain);
				$retHTML .= "<br><b>Keywords: </b> " .  WebUtil::htmlOutput($bannerKeywords);
			}
			
			if(!empty($Referral))
				$retHTML .= "<br>";
				
			// Try to find out the last organic click recorded in the Visitor Session details.
			$visitorPathObj = new VisitorPath();
			$sessionIdLinkedArr = array();
			
			// We didn't start adding the Session ID's to the order table until Sept 2010
			if(!empty($orderSessionID))
				$sessionIdLinkedArr = $visitorPathObj->getPreviousSessionIDsWithDates($orderSessionID);
				
			// Go back through each session ID backwards looking for the first Organic click.
			$organicClickDesc = "";
			foreach($sessionIdLinkedArr as $thisSessionId => $thisSessionDate){
				
				$dbCmd2->Query("SELECT ReferralType FROM visitorsessiondetails WHERE SessionID='".DbCmd::EscapeSQL($thisSessionId)."'");
				$sessionReferralType = $dbCmd2->GetValue();
				
				if($sessionReferralType == "O"){
					$sessionReferrer = $visitorPathObj->getReferrer($thisSessionId);
					$domainReferrer = WebUtil::getDomainFromURL($sessionReferrer);
					$referrerKeywords = WebUtil::getKeywordsFromSearchEngineURL($sessionReferrer);
					$organicClickDesc = "<br><img src='images/icon-leaf.png' width='19' height='16' align='absmiddle'> " . WebUtil::htmlOutput($domainReferrer) . ": " . WebUtil::htmlOutput($referrerKeywords);
					break;
				}
			}
			
			$retHTML .= $organicClickDesc;
		}

		if($AuthObj->CheckForPermission("LOYALTY_REVENUES")){
			
			$userIdofOrder = Order::GetUserIDFromOrder($dbCmd2, $orderno);
			$domainIDofUser = UserControl::getDomainIDofUser($userIdofOrder);
			
			if(Domain::getLoyaltyDiscountFlag($domainIDofUser)){
				$UserControlObj->LoadUserByID($userIdofOrder, false);
				
				$retHTML .= "<br>";
			
				if($UserControlObj->getLoyaltyProgram() == "Y")
					$retHTML .= "Loyalty Program: <b>YES</b> ";
				else 
					$retHTML .= "Loyalty Program: No ";
		
				// Find out if the user has a loyalty setting as NO... but they have a Loyalty Program Discount
				$loyaltyObj = new LoyaltyProgram($domainIDofUser);
				if($loyaltyObj->getTotalSavingsForUser($userIdofOrder) > 0 && $UserControlObj->getLoyaltyProgram() == "N")
					$retHTML .= " <font color='#cc0000'>Possible Scam</font>";

			}
		}



		header ("Content-Type: text/xml");
		// It seems that when you hit session_start it will send a Pragma: NoCache in the header
		// When comminicating over HTTPS there is a bug with IE.  It can not get the documents after they have finished downloading because they have already expired
		// We can allow the file to be cached be sending this header.  When requesting the document you can put a ?noache=timestamp
		header("Pragma: public");



		if(!$errorFlag){
			print "<?xml version=\"1.0\" ?>
				<response>
				<success>good</success>
				<details>" . WebUtil::htmlOutput($retHTML)  . "</details>
				</response>"; 
		}
		else{
			print "<?xml version=\"1.0\" ?>
				<response>
				<success>bad</success>
				<error_description>". $ErrorDescription ."</error_description>
				</response>"; 
		}
		exit;
	
	}
	else{
		throw new Exception("undefined action");
	}

}




$t = new Templatex(".");


$t->set_file("origPage", "ad_order-template.html");


$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


$t->set_var("DOMAIN_FROM_ORDER", Domain::oneDomain());


// If a vendor is viewing this order... they should not have permissions to see it unless there is at least 1 project that belongs to them
if(!empty($VendorRestriction)){	
	if(Order::GetProjectCountByVendor($dbCmd, $VendorRestriction, $orderno) == 0)
		WebUtil::PrintAdminError("Order #$orderno is not available.");	
}


// Keep a record of the visit to this page by the user.
NavigationHistory::recordPageVisit($dbCmd, $UserID, "Order", $orderno);


$dbCmd->Query("SELECT users.Name, users.Email, users.Company, users.Address,
		users.AddressTwo, users.City, users.State, users.Zip,
		UNIX_TIMESTAMP(orders.DateOrdered) AS DateOrdered, orders.CardType, 
		orders.CardNumber, orders.MonthExpiration, orders.YearExpiration, 
		users.Email, orders.ShippingChoiceID, orders.BillingName, orders.BillingType, 
		orders.BillingCompany, orders.BillingAddress, orders.BillingAddressTwo, 
		orders.BillingCity, orders.BillingState, orders.BillingZip, 
		orders.BillingCountry, orders.ShippingName, orders.ShippingCompany, 
		orders.ShippingAddress, orders.ShippingAddressTwo, orders.ShippingCity, 
		orders.ShippingState, orders.ShippingZip, orders.ShippingCountry, orders.ShippingResidentialFlag, users.Phone, 
		users.ID AS CustomerID, users.Password, orders.InvoiceNote, orders.CouponID, orders.BannerReferer, orders.Referral, 
		orders.IPaddress FROM users 
		INNER JOIN orders on orders.UserID = users.ID where orders.ID=\"$orderno\"");
$row = $dbCmd->GetRow();

$personsName = $row["Name"];
$personsEmail = $row["Email"];
$companyName = $row["Company"];
$theirAddress = $row["Address"];
$theirAddress2 = $row["AddressTwo"];
$City = $row["City"];
$State = $row["State"];
$Zip = $row["Zip"];
$DateOrderedTimeStamp = $row["DateOrdered"];
$CardType = $row["CardType"];
$CardNumber = $row["CardNumber"];
$MonthExp = $row["MonthExpiration"];
$YearExp = $row["YearExpiration"];
$customer_email = $row["Email"];
$shippingChoiceID = $row["ShippingChoiceID"];
$BillingType = $row["BillingType"];
$billing_name = $row["BillingName"];
$billing_company = $row["BillingCompany"];
$billing_address = $row["BillingAddress"];
$billing_address2 = $row["BillingAddressTwo"];
$billing_city = $row["BillingCity"];
$billing_state = $row["BillingState"];
$billing_zip = $row["BillingZip"];
$billing_country = $row["BillingCountry"];
$ShippingName = $row["ShippingName"];
$ShippingCompany = $row["ShippingCompany"];
$ShippingAddress = $row["ShippingAddress"];
$ShippingAddress2 = $row["ShippingAddressTwo"];
$ShippingCity = $row["ShippingCity"];
$ShippingState = $row["ShippingState"];
$ShippingZip = $row["ShippingZip"];
$ShippingCountry = $row["ShippingCountry"];
$ShippingResidentialFlag = $row["ShippingResidentialFlag"];
$UsersPhone = $row["Phone"];
$CustomerID = $row["CustomerID"];
$Password = $row["Password"];
$InvoiceNote = $row["InvoiceNote"];
$CouponID = $row["CouponID"];
$BannerReferer = $row["BannerReferer"];
$Referral = $row["Referral"];
$userIPaddress = $row["IPaddress"];


if($CustomerID == "")
	WebUtil::PrintAdminError("Order was not found within the system.");

$UserControlObj->LoadUserByID($CustomerID);


$custUserControlObj = new UserControl($dbCmd);
$custUserControlObj->LoadUserByID($CustomerID);


$DateOrdered = date("D g:i a", $DateOrderedTimeStamp) . "<br>" . date("M j, Y", $DateOrderedTimeStamp);
$t->set_var(array("DATEORDERED"=>$DateOrdered));
$t->allowVariableToContainBrackets("DATEORDERED");



$billingInfo = "";
if(!empty($billing_company)){
	$billingInfo .= WebUtil::htmlOutput($billing_company) . "<br>";
}
$billingInfo .= WebUtil::htmlOutput($billing_name) . "<br>";
$billingInfo .= WebUtil::htmlOutput($billing_address) . "<br>";
if(!empty($billing_address2))
	$billingInfo .= WebUtil::htmlOutput($billing_address2) . "<br>";

$billingInfo .= WebUtil::htmlOutput($billing_city) . ", " . WebUtil::htmlOutput($billing_state) . " " . WebUtil::htmlOutput($billing_zip) . "<br>";
$billingInfo .= WebUtil::htmlOutput(Status::GetCountryByCode($billing_country)) . "<br>" . WebUtil::htmlOutput($UsersPhone);

$t->set_var(array("BILLING_INFO"=>$billingInfo));
$t->set_var(array("INVOICE_MESSAGE"=>$InvoiceNote));

$t->allowVariableToContainBrackets("BILLING_INFO");





// If they are corporate billing then show them the description.
// Otherwise show them the Credit Card type and last 4 digits.
if($BillingType == "C"){
	$t->set_var("PAYMENT_DESC", "Corporate Billing");
}
else if($BillingType == "P"){
	$t->set_var("PAYMENT_DESC", "PAYPAL");
}
else if($BillingType == "N" && strlen($CardNumber >= 4)){
	$t->set_var("PAYMENT_DESC", $CardType . ": ***** " . substr($CardNumber, -4));
}
else{
	$t->set_var("PAYMENT_DESC", "Unknown");
}



if(empty($CouponID) || !$AuthObj->CheckForPermission("COUPONS_VIEW"))
	$t->discard_block("origPage", "CouponBL");
else{
	$couponObj = new Coupons($dbCmd);
	$couponObj->LoadCouponByID($CouponID);

	$t->set_var("COUPON_CODE", $couponObj->GetCouponCode());
	$t->set_var("COUPON_DESC", WebUtil::htmlOutput($couponObj->GetSummaryOfCoupon()));
	
}



// If they have permission to see extra marketing details... show the Banner Click stored in the order table.
if(!$AuthObj->CheckForPermission("EXTRA_MARKETING_DETAILS_ORDER")){
	$t->discard_block("origPage", "BannerClickBl");
}
else{
	if(empty($BannerReferer)){
		$t->set_var("BANNER_FROM_DOMAIN", "(empty)");
		$t->set_var("BANNER_FROM_KW", "(empty)");
	}
	else{
		$bannerKeywords = WebUtil::getKeywordsFromSearchEngineURL($BannerReferer);
		$bannerDomain = WebUtil::getDomainFromURL($BannerReferer);

		
		$t->set_var("BANNER_FROM_KW", WebUtil::htmlOutput($bannerKeywords));
		$t->set_var("BANNER_FROM_DOMAIN", WebUtil::htmlOutput($bannerDomain));
	}
		

	$t->set_var("IP_ADDRESS", WebUtil::htmlOutput($userIPaddress));


	$t->allowVariableToContainBrackets("BANNER_CLICK");

	$bannerHistory = "";

	
	$lastTimeStamp = null;
	
	// If We have the banner name in our history (based upon IP addresses)... then don't show the banner click stored in the cookie because it would be redundant.	
	$bannerClickHistoryArr = array();
	$dbCmd->Query("SELECT Name, UNIX_TIMESTAMP(Date) AS BannerClickDate, UserAgent FROM 
				bannerlog WHERE IPaddress='$userIPaddress' AND DomainID=".Domain::oneDomain()." ORDER BY Date ASC LIMIT 50");
	if($dbCmd->GetNumRows() > 0){

		while($bannerRow = $dbCmd->GetRow()){
			
			$timeSpanDifference = "";
			if($lastTimeStamp){
				$timeSpanDifference = "&nbsp;&nbsp;&nbsp;<font color='#6699cc'>" . LanguageBase::getTimeDiffDesc($lastTimeStamp, $bannerRow["BannerClickDate"], true) . "</font> <font color='#999999'>later</font>";
			}
			
			$lastTimeStamp = $bannerRow["BannerClickDate"];
			
			if(preg_match("/Fun/i", $bannerRow["UserAgent"]))
				$userAgent = "&nbsp;&nbsp;&nbsp;&nbsp;<font color='#660000' style='font-size:8px;'>Fun Web Prod.</font>";
			else 
				$userAgent = "";
			
			$bannerHistory .= "<br><img src='./images/transparent.gif' width='5' height='10'><br>" . date("M jS y g:i:s a", $bannerRow["BannerClickDate"]) . "$timeSpanDifference<br><b>" . WebUtil::htmlOutput($bannerRow["Name"]) . "</b>$userAgent";
			$bannerClickHistoryArr[] = $bannerRow["Name"];
			

		}

	}
	
	$salesLogHTML = "";

	if($UserControlObj->getSalesRepID() != 0){
		
		$dbCmd->Query("SELECT Name, UNIX_TIMESTAMP(Date) AS BannerClickDate FROM 
					salesbannerlog WHERE IPaddress='$userIPaddress' AND SalesUserID=".$UserControlObj->getSalesRepID()." ORDER BY ID ASC LIMIT 10");

		while($bannerRow = $dbCmd->GetRow())
			$salesLogHTML .= "<br><img src='./images/transparent.gif' width='5' height='5'><br><u>Sales Rep Log:</u> " . date("M jS y g:i:s a", $bannerRow["BannerClickDate"]) . "<br><b>" . WebUtil::htmlOutput($bannerRow["Name"]) . "</b>";
	}
	

	if(empty($bannerHistory) || !in_array($Referral, $bannerClickHistoryArr)){
		
		$bannerHistory = "<br><img src='./images/transparent.gif' width='5' height='5'><br>
			<b>" . WebUtil::htmlOutput($Referral) . "</b>" . $bannerHistory;
	}
	
	$userDomainIDsArr = $AuthObj->getUserDomainsIDs();
	
	$bannerHistory = "<font class='ReallySmallBody'>" . $bannerHistory . $salesLogHTML . "</font>";
	
	if($AuthObj->CheckForPermission("VISITOR_PATHS_REPORT")){
		$dbCmd->Query("SELECT SessionID, DomainID, UNIX_TIMESTAMP(DateStarted) AS DateStarted, UNIX_TIMESTAMP(DateLastAccess) AS DateLastAccess FROM visitorsessiondetails WHERE IPaddress='$userIPaddress' ORDER BY ID DESC LIMIT 30");
		if($dbCmd->GetNumRows() > 0)
			$bannerHistory .= "<br><br><u>Visitor Charts</u>";
		
		// Reverse the Rows so that the oldest ones show up first.
		$sessionsRows = array();
		while($row = $dbCmd->GetRow())
			array_push($sessionsRows, $row);

		$sessionsRows = array_reverse($sessionsRows);
		foreach($sessionsRows as $thisSessionsRow){
			
			if(sizeof($userDomainIDsArr) > 1){
				$domainLogoObj = new DomainLogos($thisSessionsRow["DomainID"]);
				$domainLogoImg = "&nbsp;<br><img alt='".Domain::getDomainKeyFromID($thisSessionsRow["DomainID"])."' align='absmiddle'  src='./domain_logos/$domainLogoObj->verySmallIcon' border='0'>";
			}
			else{
				$domainLogoImg = "";
			}
			
			$sessionDuration = LanguageBase::getTimeDiffDesc($thisSessionsRow["DateStarted"], $thisSessionsRow["DateLastAccess"]);
			$bannerHistory .= "<br>$domainLogoImg <font class='ReallySmallBody'><a href='javascript:visitorChartSession(\"" . $thisSessionsRow["SessionID"] . "\", false);' class=\"BlueRedLinkRecSM\">".date("M jS y g:i:s a", $thisSessionsRow["DateStarted"])."</a><br>Duration: $sessionDuration</font>";
		}
	}
	
	$t->set_var("BANNER_CLICK_HISTORY", $bannerHistory);
	$t->allowVariableToContainBrackets("BANNER_CLICK_HISTORY");
}





// The Customer Shipping Method Selected
$t->set_var("CUSTOMER_SHIPPING_CHOICEID", $shippingChoiceID);


// Just pick the first Product ID that we find in the order and use that for the default Product in our Shipping Calculator Pop-up.
$dbCmd->Query("SELECT ProductID,Quantity FROM projectsordered INNER JOIN orders ON orders.ID = projectsordered.OrderID WHERE orders.ID=$orderno AND projectsordered.DomainID=" . Domain::oneDomain() . " LIMIT 1");
$row = $dbCmd->GetRow();
$t->set_var("DEFAULT_PRODUCTID_SHIPPING_CALCULATOR", $row["ProductID"]);
$t->set_var("DEFAULT_QUANTITY_SHIPPING_CALCULATOR", $row["Quantity"]);




// Find out if there is an invoice note, if so then we want to change the background color to alert the administrator 
if(!empty($InvoiceNote))
	$t->set_var(array("MESSAGECOLOR_INVOICE"=>"#FFDDDD"));
else
	$t->set_var(array("MESSAGECOLOR_INVOICE"=>"#EEEEFF"));


// An extra urlencode is needed for the login link since it will be traveling though the <a href="javascript
$domainWebsiteURL = Domain::getWebsiteURLforDomainID(Domain::oneDomain());

if($AuthObj->CheckForPermission("VIEW_LOGIN_LINK") && !$AuthObj->CheckIfUserIDisMember($CustomerID))
	$t->set_var(array("LOGIN_LINK"=>urlencode("https://$domainWebsiteURL/signin_xml.php?redirect=home&email=$customer_email&pwe=" . md5($Password . Authenticate::getSecuritySaltBasic()))));
else
	$t->set_var("LOGIN_LINK", "");
	


// If we are switchin tabs from International to United States... we may need to truncate the State down to 2 characters.
if(WebUtil::GetInput("showshippingcountry", FILTER_SANITIZE_STRING_ONE_LINE) == "US"){
	if(strlen($ShippingState) > 2)
		$ShippingState = substr($ShippingState, 0, 2);
}
$t->set_var(array(
	"SHIPPING_COMPANY"=>WebUtil::htmlOutput($ShippingCompany), 
	"SHIPPING_NAME"=>WebUtil::htmlOutput($ShippingName),
	"SHIPPING_ADDRESS"=>WebUtil::htmlOutput($ShippingAddress), 
	"SHIPPING_ADDRESS_TWO"=>WebUtil::htmlOutput($ShippingAddress2),
	"SHIPPING_CITY"=>WebUtil::htmlOutput($ShippingCity),
	"SHIPPING_CITY_ENCODED"=>urlencode($ShippingCity),
	"SHIPPING_STATE"=>WebUtil::htmlOutput($ShippingState),
	"SHIPPING_STATE_ENCODED"=>urlencode($ShippingState),
	"SHIPPING_COUNTRY"=>$ShippingCountry,
	"SHIPPING_ZIP"=>$ShippingZip
	));



if($ShippingResidentialFlag == "Y"){
	$t->set_var("SHIPPING_RESIDENTIAL_CHECKED", "checked");
	$t->set_var("SHIPPING_COMMERCIAL_CHECKED", "");
	$t->set_var("IS_RESIDENTIAL_SHIPPING", "yes");
	
}
else{
	$t->set_var("SHIPPING_RESIDENTIAL_CHECKED", "");
	$t->set_var("SHIPPING_COMMERCIAL_CHECKED", "checked");
	$t->set_var("IS_RESIDENTIAL_SHIPPING", "no");
}


$CurrentURL = "./ad_order.php?orderno=" . $orderno;

$CurrentURLencoded = urlencode($CurrentURL);

$t->set_var(array(
	"CURRENTURL"=>$CurrentURL, 
	"CURRENTURL_ENCODED"=>$CurrentURLencoded
	));

$t->set_var("CUSTOMER_ID", $CustomerID);
$t->set_var(array("CUSTOMER_EMAIL"=>$personsEmail, "PHONE"=>$UsersPhone));

$t->set_var("MAIN_ORDERID", $orderno);

$t->set_var(array(
	"EMAIL_ENCODED"=>urlencode($personsEmail),
	"SHIPPINGNAME_ENCODED"=>urlencode($ShippingName)
	));

$t->set_var("ORDERNO_HASHED", Order::GetHashedOrderNo($orderno));


$t->set_var("CUSTOMER_PROJECTS", Order::GetProjectCountByUser($dbCmd, $CustomerID, $VendorRestriction));

$customerShipping = Order::GetCustomerShippingQuote($dbCmd, $orderno);

$customerSubtotal = Order::GetTotalFromOrder($dbCmd, $orderno, "customersubtotal");
$customerDiscount = Order::GetTotalFromOrder($dbCmd, $orderno, "customerdiscount");
$customerTax = Order::GetTotalFromOrder($dbCmd, $orderno, "customertax");



$vendorSubtotal = Order::GetTotalFromOrder($dbCmd, $orderno, "vendorsubtotal", $VendorRestriction);


$t->set_var("C_SUB", number_format($customerSubtotal, 2));
$t->set_var("C_DISC", $customerDiscount);
$t->set_var("C_TAX", number_format($customerTax, 2));
$t->set_var("V_SUB", number_format($vendorSubtotal, 2));
$t->set_var("C_GRAND", number_format($customerSubtotal - $customerDiscount + $customerTax + $customerShipping, 2));
$t->set_var("INCOME", number_format($customerSubtotal - $vendorSubtotal - $customerDiscount, 2));


$t->set_var("C_SHIP", $customerShipping);


// For the pricing block, we should change the column description if it is a vendor
if($AuthObj->CheckIfBelongsToGroup("VENDOR"))
	$t->set_var("VENDOR_SUB_TITLE", "Total");
else
	$t->set_var("VENDOR_SUB_TITLE", "Vendor Subtotal");



// Create an array of selections for package weight
$PackageWeightsArr = array();
for($i=1; $i<=200; $i++){
	if($i==1)
		$PackageWeightsArr[($i)] = $i . " lb.";
	else
		$PackageWeightsArr[($i)] = $i . " lbs.";
}





// Get a List of Shipping Choices to the customers address and put them in a Drop down menu (with the Customer's choice selected).
$domainAddressObj = new DomainAddresses(Domain::oneDomain());
$shipFromAddressObj = $domainAddressObj->getDefaultProductionFacilityAddressObj();
$shipToAddressObj = Order::getShippingAddressObject($dbCmd, $orderno);


try{
	$shippingChoicesDropDown = $shippingChoiceObj->getAvailableShippingChoices($shipFromAddressObj, $shipToAddressObj);
}
catch(ExceptionInvalidShippingAddress $e){
	$shippingChoicesDropDown["InvalidAddress"] = "Invalid Shipping Address";
	print "<br><font color='#990000'><b>The Shipping Address appears to be invalid. Try changing the shipping address.  If the problem persists contact Webmaster</b></font><br>";
	
}


// If we can't find the currently selected shipping method within the drop down menu choices, then we need to show an error on the screen
// Also Set the Shipping Method drop down to unknown
if(array_key_exists($shippingChoiceID, $shippingChoicesDropDown)){
	$t->discard_block("origPage","ShippingMethodNotFoundBL");
	$selctedShippingChoiceValue = $shippingChoiceID;
}
else{
	// Add an extra choice to the arrray of Shipping Choices saying the method is now unkown
	$shippingChoicesDropDown["UnknownShipping"] = "Not Available";
	$selctedShippingChoiceValue = "UnknownShipping";
}



// Get the shipment info 
$shipmentIDArr = Shipment::GetShipmentsIDsWithinOrder($dbCmd, $orderno, 0, $VendorRestriction);


// Find out how many shipments have not been completed within this order... that way we know whether to show the checkboxes for combining shipments together
$UnCmpltShipmnts = Shipment::GetNumberOfUncompletedShipments($dbCmd, $orderno, $AuthObj);

$t->set_block("origPage","ShipmentBreakBL","ShipmentBreakBLout");
$t->set_block("origPage","PackageWeightBL","PackageWeightBLout");
$t->set_block("origPage","ProjectShipmentsBL","ProjectShipmentsBLout");
$t->set_block("origPage","ShipmentsBL","ShipmentsBLout");

foreach($shipmentIDArr as $ThisShipID){
	
	$ProjectsInShipmentArr = Shipment::ShipmentGetProjectInfo($dbCmd, $ThisShipID);
	$shipmentInfoHash = Shipment::GetInfoByShipmentID($dbCmd, $ThisShipID);
	
	// When a shipment has a large number of projects we do not want to show all of them
	// If we limit the amount of projects shown.. then we need to give them a message.
	$LargeShipmentBreak = 15;
	$LargeShipmentMessage = "";
	
	if(sizeof($ProjectsInShipmentArr) > $LargeShipmentBreak){
		$LargeShipmentMessage = "<font class='ReallySmallBody'>This shipment contains " . sizeof($ProjectsInShipmentArr) . " projects.<br>";
		$LargeShipmentMessage .= "Not all of them are displayed here because we do not bother associating projects within shipments (in detail) at this volume.  Adjust package weights manually when adding/removing shipments.<br></font>";
	}

	// clean the slate for inner block.
	$t->set_var("ProjectShipmentsBLout", "");
	
	$counter = 0;
	foreach($ProjectsInShipmentArr as $ProjectQuanString){
	
		$counter++;
		if($counter > 1 && !empty($LargeShipmentMessage))
			continue;
	
		// split the string at the pipe character.  The ProjectID is on the left, quantity in the middle, ShipmentLinkID on the right
		$SplitInfo = split("\|", $ProjectQuanString);
		
		$thisProjectID = $SplitInfo[0];
	
		
		// Project Desc
		$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "ordered", $thisProjectID);
		$shipmentProjectDesc = $SplitInfo[1] . " <nobr>" . WebUtil::htmlOutput($projectObj->getProductTitle()) . "</nobr>";
		
		$t->set_var(array(
			"SHP_PID"=>$thisProjectID,
			"P_Q"=>$shipmentProjectDesc,
			"SHP_LINK_ID"=>$SplitInfo[2]
			));
			
		$t->allowVariableToContainBrackets("P_Q");
		
		$ProjectStatus = ProjectOrdered::GetProjectStatus($dbCmd, $thisProjectID);
		
		// Get the Status History
		$StatusHistory = "";
		$StatusTimeStamp = 0;
		$dbCmd->Query("SELECT *, UNIX_TIMESTAMP(Date) AS StatusDate FROM projecthistory WHERE ProjectID=$thisProjectID ORDER BY ID ASC");
		while($PHistRow = $dbCmd->GetRow()){
			$StatusTimeStamp = $PHistRow["StatusDate"];
			$status_history_date = date("D, M j, D g:i a", $StatusTimeStamp);
			$StatusHistory .= $PHistRow["Note"] . "  --  " .  WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd2, $PHistRow["UserID"])) . "  --  $status_history_date " . "<br>- - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - -<br>";
		}
		$t->set_var("PROJECT_HISTORY", $StatusHistory);
		$t->allowVariableToContainBrackets("PROJECT_HISTORY");
		

		if(Order::CheckIfProjectShouldShipToday($dbCmd, $thisProjectID))
			$dueTodayFlag = "DueToday";
		else
			$dueTodayFlag = "";

		$t->set_var(array(
			"DUETODAY"=>"$dueTodayFlag",
			"EST_SHIP_DATE"=>"<a href='javascript:ShowShedule()' class='BlueRedLink'>" . TimeEstimate::formatTimeStamp($projectObj->getEstShipDate(), "D, M jS", false) . "</a>",
			"EST_ARRIV_DATE"=>"<a href='javascript:ShowShedule()' class='BlueRedLink'>" . TimeEstimate::formatTimeStamp($projectObj->getEstArrivalDate()) . "</a>"
			));
			
		$t->allowVariableToContainBrackets("EST_SHIP_DATE");
		$t->allowVariableToContainBrackets("EST_ARRIV_DATE");
		
		

		// If the project has shipped and the Date of the shipment is greater than when we estimated... then show a message that we were late
		if(!empty($shipmentInfoHash["DateShipped"]) && $shipmentInfoHash["DateShipped"] > $projectObj->getEstShipDate())
			$t->set_var("LATE_MESSAGE", "<br>We may have missed our deadline for shipping.");
		else if($shipmentInfoHash["DateShipped"] == "" && time() > $projectObj->getEstShipDate())
			$t->set_var("LATE_MESSAGE", "<br>This item hasn't shipped yet. We may have missed our deadline.");
		else
			$t->set_var("LATE_MESSAGE", "");
			
		$t->allowVariableToContainBrackets("LATE_MESSAGE");
		
		// ------------  Disabling the function to break apart shipments for now ..........
		$t->set_var("ShipmentBreakBLout", "");
		/*
		// Find out if the shipment has already gone out
		if($shipmentInfoHash["DateShipped"] == ""){
		
			//Since it hasn't been shipped yet, this gives them the ability to break the shipment into a new one
			$t->parse("ShipmentBreakBLout","ShipmentBreakBL", false);
		}
		else{
			//Don't allow them to break the shipment up, since it has already gone out the door
			$t->set_var("ShipmentBreakBLout", " qty.");
		}
		*/
		
		if($AuthObj->CheckForPermission("PROOF_ARTWORK"))
			$t->set_var("ARTWORKLINK", "- <a class='BlueRedLinkRecord' href='./ad_proof_artwork.php?projectid=$thisProjectID'>A</a>");
		else
			$t->set_var("ARTWORKLINK", "");
			
		$t->allowVariableToContainBrackets("ARTWORKLINK");
	
	
	
	
		// Show Artwork Preview links.
		if($AuthObj->CheckForPermission("PROOF_ARTWORK")){

			if($AuthObj->CheckForPermission("ARTWORK_PREVIEW_POPUPS_USER_ORDERS")){
				
				$previewSpanHTML = "<span style='visibility:hidden; position:absolute; left:170px; top:60' id='artwPreviewSpan" . $thisProjectID . "' onmouseover=\"this.style.cursor='pointer'\" onMouseOut=\"this.style.cursor='default'\"></span>";
				$hrefMouseOver = " onMouseOver='showArtPrev(" . $thisProjectID . ", true);' onMouseOut='showArtPrev(" . $thisProjectID . ", false);'";


				// Find out if we have previewed this email recently
				$lastPreviewedDate = $navHistoryObj->getDateOfLastVisit($UserID, "ArtPreview", $thisProjectID);

				if($lastPreviewedDate){
					$artworkBoldStart = "";
					$artworkBoldEnd = "";
				}
				else{
					$artworkBoldStart = "<b><font style='font-size:14px;'>";
					$artworkBoldEnd = "</font></b>";
				}

				$artworkLink = " <a href='./ad_proof_artwork.php?projectid=$thisProjectID' class='BlueRedLinkRecord' $hrefMouseOver >" . $artworkBoldStart . "*" . $artworkBoldEnd  . "</a>" . $previewSpanHTML;
				
				$t->set_var("ART", $artworkLink);

			}
			else{

				$t->set_var("ART", "");

			}
		}
		else{
			$t->set_var("ART", "");
		}
		
		$t->allowVariableToContainBrackets("ART");

	
	
	
		
		$t->parse("ProjectShipmentsBLout","ProjectShipmentsBL",true);
	}

	$t->set_var("SHIPMENT_ID",$ThisShipID);
	
	$t->set_var("LARGE_SHIPMENT_MESSAGE", $LargeShipmentMessage);
	$t->allowVariableToContainBrackets("LARGE_SHIPMENT_MESSAGE");

	// If this Domain does "Shipping Downgrading" ... and it has not been shipped yet.  Then we don't know what Carrier Method will be used yet.
	// Or if the user settings don't allow shipping downgrading.
	// In that case... what until the Package actually leaves before we show the CSR what Carrier method was used.
	if(!empty($shipmentInfoHash["DateShipped"]) || !$domainObj->doesDomainDowngradeShipping() || $UserControlObj->getDowngradeShipping() == "N")
		$t->set_var("SHIPPING_METHOD", $shippingMethodObj->getShippingMethodName($shipmentInfoHash["ShippingMethod"], true));
	else
		$t->set_var("SHIPPING_METHOD", "Still Don't Know");


	if(!$AuthObj->CheckForPermission("CHANGE_PACKAGE_WEIGHT") || !empty($shipmentInfoHash["DateShipped"])){
		if($shipmentInfoHash["PackageWeight"] == 1){
			$WeightSuffix = " lb.";
		}
		else{
			$WeightSuffix = " lbs.";
		}

		$t->set_var(array(
			"PackageWeightBLout"=>$shipmentInfoHash["PackageWeight"] . $WeightSuffix
			));
	}
	else{
		// Give them the ablility to change the package weight
		$t->set_var(array(
			"PACKAGE_WEIGHTS"=>Widgets::buildSelect($PackageWeightsArr, array($shipmentInfoHash["PackageWeight"]))
			));
			
		$t->allowVariableToContainBrackets("PACKAGE_WEIGHTS");

		$t->parse("PackageWeightBLout","PackageWeightBL", false);
	}


	
	
	// Don't show the checkbox for combining shipments if there is only shipment left (unshipped)
	if($UnCmpltShipmnts >= 2 && $shipmentInfoHash["DateShipped"] == ""){
		$t->set_var("CX", "<input type='checkbox' name='sid' value='$ThisShipID'>");
	}
	else{
		$t->set_var("CX", "<img src='./images/transparent.gif' width='1' height='1'>");
	}
	
	$t->allowVariableToContainBrackets("CX");
	
	

	
	// Decide what we are going to show them within the Tracking column
	if($shipmentInfoHash["DateShipped"] == ""){
		$t->set_var("TRACKING", "Not shipped yet.");
	}
	else{
		// The tracking variable should contain the date the package was shipped as well as a link to the tracking number
		$TrackingDisplay = "Shipped: <b>" . date("D, jS", $shipmentInfoHash["DateShipped"]) . "</b>";
		$TrackingDisplay .= "<br>" . Shipment::GetTrackingLink($shipmentInfoHash["TrackingNumber"], $shipmentInfoHash["Carrier"]);
		
		$t->set_var("TRACKING", $TrackingDisplay);
	}
	
	$t->allowVariableToContainBrackets("TRACKING");
	
	
	$t->parse("ShipmentsBLout","ShipmentsBL",true);
}

// I am not sure... but lately there have been some orders with empty shipments.
if(empty($shipmentIDArr)){
	$t->set_var("ShipmentsBLout", "AN ERROR OCCURED<br><a href='javascript:ResetShipments();'>Click Here</a> to reset shipments on this order.");
}

//If there is only 1 shipment then don't show the combine button
if($UnCmpltShipmnts < 2){
	
	$t->set_block("origPage","HideCombineButtonBL","HideCombineButtonBLout");
	$t->set_var("HideCombineButtonBLout", "");
}



// First we see if the shipping address is in the US, or international... there are two different blocks of HTML for changing the shipping address
// However, if we get the parameter "showshippingcountry" in the URL, then that takes a higher priority over the shipping country, and we will show the HTML off of that
$HideUnitedStatesShippingBlockFlag = true;
if($ShippingCountry == "US")
	$HideUnitedStatesShippingBlockFlag = false;
if(WebUtil::GetInput("showshippingcountry", FILTER_SANITIZE_STRING_ONE_LINE)){
	if(WebUtil::GetInput("showshippingcountry", FILTER_SANITIZE_STRING_ONE_LINE) == "US")
		$HideUnitedStatesShippingBlockFlag = false;
	else
		$HideUnitedStatesShippingBlockFlag = true;
}
if($HideUnitedStatesShippingBlockFlag)
	$t->discard_block("origPage","US_ShippingBL");
else
	$t->discard_block("origPage","INT_ShippingBL");



// Set the drop down list of countries
$t->set_var("COUNTRIES_DROPDOWN", Widgets::buildSelect(Status::GetUPScountryCodesArr(), array($ShippingCountry)));
$t->allowVariableToContainBrackets("COUNTRIES_DROPDOWN");


// If the order is completed (of if they don't have permissions) then we should not give them the ability to change the customer shipping method
if(Order::CheckIfOrderComplete($dbCmd, $orderno) || !$AuthObj->CheckForPermission("CHANGE_CUSTOMER_SHIPPING_METHOD")){

	// Instead of a drop down menu... show them a description of what the Customer Shipping method was
	$t->set_block("origPage","CustomerShippingChangeBL","CustomerShippingChangeBLout");
	$t->set_var("CustomerShippingChangeBLout", ShippingChoices::getHtmlChoiceName($shippingChoiceID));
	
}
else{
	// Create the drop down list for the customer shipping method
	$t->set_var("CUSTOMER_SHIPPING_OPTIONS", Widgets::buildSelect($shippingChoicesDropDown, array($selctedShippingChoiceValue)));
}
$t->allowVariableToContainBrackets("CUSTOMER_SHIPPING_OPTIONS");

// See if they have permissions to change the customer shipping address
if(Order::CheckIfOrderComplete($dbCmd, $orderno) || !$AuthObj->CheckForPermission("CHANGE_CUSTOMER_SHIPPING_METHOD")){
	// Erase the button for changing the customer shipping address
	$t->set_block("origPage","ShippingChangeButtonBL","ShippingChangeButtonBLout");
	$t->set_var("ShippingChangeButtonBLout", "");
	
	$t->set_var("ADDRESS_READONLY", "READONLY");
	
}
else{
	//Sets a variable within a text input field....  either READONLY or nothing
	$t->set_var("ADDRESS_READONLY", "");
}




// See if they have permissions to see the customer billing information
if(!$AuthObj->CheckForPermission("CUSTOMER_BILLING_INFO")){
	$t->set_block("origPage","BillingButtonBL","BillingButtonBLout");
	$t->set_var("BillingButtonBLout", "");
}


// See if they have permissions gain access to the Merchant Bank Interface
if(!$AuthObj->CheckForPermission("MERCHANT_LINK")){
	$t->discard_block("origPage","MerchantButtonBL");
	$t->discard_block("origPage","PaypalButtonBL");
} 
else {
	if($BillingType == "P")
		$t->discard_block("origPage","MerchantButtonBL");
	else
		$t->discard_block("origPage","PaypalButtonBL");
}


// See if they have permissions to see a login link for the customer
if(!$AuthObj->CheckForPermission("VIEW_LOGIN_LINK")){
	$t->set_block("origPage","HideLoginLink","HideLoginLinkout");
	$t->set_var("HideLoginLinkout", "");
}

// See if they have permissions to change the customer shipping price
if(Order::CheckIfOrderComplete($dbCmd, $orderno) || !$AuthObj->CheckForPermission("CHANGE_CUSTOMER_SHIPPING_PRICE")){
	$t->set_block("origPage","HideShippingChangeButton","HideShippingChangeButtonout");
	$t->set_var("HideShippingChangeButtonout", "");
	
	$t->set_var("SHIPPING_QUOTE_READONLY", "READONLY");	
}
else{
	//Sets a variable within a text input field....  either READONLY or nothing
	$t->set_var("SHIPPING_QUOTE_READONLY", "");
}


// See if they have permissions to change the invoice message for the customer
if(!$AuthObj->CheckForPermission("CUSTOMER_INVOICE_MESSAGE")){
	$t->discard_block("origPage", "HideUpdateInvoiceMessageBL");
	
	$t->set_var("CUSTOMER_INVOICE_READONLY", "READONLY");	
}
else{
	//Sets a variable within a text input field....  either READONLY or nothing
	$t->set_var("CUSTOMER_INVOICE_READONLY", "");
}





$shippingInstructions = Order::getShippingInstructions($dbCmd, $orderno);
$t->set_var("SHIPPING_INSTRUCTIONS", WebUtil::htmlOutput($shippingInstructions));

if(!$AuthObj->CheckForPermission("MODIFY_SHIPPING_INSTRUCTIONS")){

	$t->discard_block("origPage", "HideShippingInstructionsBL");
	
	$t->set_var("SHIPPING_INSTRUCTIONS_READONLY", "READONLY");	
}
else{
	$t->set_var("SHIPPING_INSTRUCTIONS_READONLY", "");
}

// Find out if there is an invoice note, if so then we want to change the background color to alert the administrator 
if(!empty($shippingInstructions))
	$t->set_var("MESSAGECOLOR_SHIPPING", "#FFDDDD");
else
	$t->set_var("MESSAGECOLOR_SHIPPING", "#EEEEFF");






if(!$AuthObj->CheckForPermission("VIEW_CUSTOMER_ORDER_TOTALS")){
	$t->set_block("origPage","CustomerTotalsBL","CustomerTotalsBLout");
	$t->set_var("CustomerTotalsBLout", "");
}

if(!$AuthObj->CheckForPermission("VIEW_ORDER_INCOME")){
	$t->set_block("origPage","OrderIncomeBL","OrderIncomeBLout");
	$t->set_var("OrderIncomeBLout", "");
}

if(!$AuthObj->CheckForPermission("VIEW_VENDOR_TOTAL")){
	$t->set_block("origPage","VendorTotalBL","VendorTotalBLout");
	$t->set_var("VendorTotalBLout", "");
}



// See if they have permissions to view the link to the Customer's account
if($AuthObj->CheckForPermission("CUSTOMER_ACCOUNT")){
	$t->set_var(array("ACCOUNT_LINK"=>"<br><img src='./images/transparent.gif' width='5' height='5'><br><a href=\"./ad_users_account.php?returl=" . urlencode(WebUtil::FilterURL($_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING'])) . "&retdesc=Order+Page&view=general&customerid=$CustomerID\" class='BlueRedLink'>Account Details</a>"));
}
else{
	$t->set_var("ACCOUNT_LINK", "");
}

$t->allowVariableToContainBrackets("ACCOUNT_LINK");




$customerRating = $custUserControlObj->getUserRating();

$custRatingImgHTML = "<img src='./images/star-rating-" . $customerRating . ".gif' width='74' height='13'>";

if($customerRating > 0){

	$custRatingDivHTML = "<br>&nbsp;<div id='Dcust" . (strval($orderno) . strval($CustomerID)) . "' style='cursor: hand; width:74px;' class='hiddenDHTMLwindow' onMouseOver='CustInf(\"" . (strval($orderno) . strval($CustomerID)) . "\", $CustomerID, true, \"".WebUtil::getFormSecurityCode()."\")' onMouseOut='CustInf(\"" . (strval($orderno) . strval($CustomerID)) . "\", $CustomerID, false, \"".WebUtil::getFormSecurityCode()."\")'>$custRatingImgHTML<span class='hiddenDHTMLwindow' style='visibility:hidden; left:75px; top:-15' id='Scust" . (strval($orderno) . strval($CustomerID)) . "'></span></div>";

	$t->set_var("CUST_RATING", $custRatingDivHTML );
}
else{
	$t->set_var("CUST_RATING", "<br><br>" . $custRatingImgHTML);
}


$t->allowVariableToContainBrackets("CUST_RATING");



// Check if this order has any project in it.
if(Order::CheckForEmptyOrder($dbCmd, $orderno)){
	if(round((time() - $DateOrderedTimeStamp) / (60*60*24)) > 2){
		$ErrorMsg = "Severe issue with order ($orderno) : -- " . WebUtil::getRemoteAddressIp() . " -- " . date("l dS of F Y g:i:s A") . " -- UID: $UserID -- " . UserControl::GetNameByUserID($dbCmd, $UserID);
		WebUtil::WebmasterError($ErrorMsg);
	}
	
	// Erase the Shipment block
	$t->set_block("origPage","EmptyShipmentMessageBL","EmptyShipmentMessageBLout");
	$t->set_var("EmptyShipmentMessageBLout", "<div align=center><br><br><br><b>This Order is Empty.</b></div>");
	
}
else if(!Order::CheckForActiveProjectWithinOrder($dbCmd, $orderno)) {

	$t->set_block("origPage","EmptyShipmentMessageBL","EmptyShipmentMessageBLout");
	$t->set_var("EmptyShipmentMessageBLout", "<div align=center><br><br><br><b>All projects have been canceled within this order.</b></div>");
}


$inactiveOrderFlag = false;

if(Order::CheckForEmptyOrder($dbCmd, $orderno) || !Order::CheckForActiveProjectWithinOrder($dbCmd, $orderno)){

	// Erase the Itemized Button
	$t->discard_block("origPage", "ItemizedButtonBL");

	// Erase the Invoice Button
	$t->discard_block("origPage", "InvoiceButtonBL");

	$inactiveOrderFlag = true;
}



if($UnCmpltShipmnts == 0 || Order::CheckForEmptyOrder($dbCmd, $orderno) || !Order::CheckForActiveProjectWithinOrder($dbCmd, $orderno)){
	

	// Don't let anyone change the guaranteed Ship or Arrival dates
	$t->discard_block("origPage","HideSpeedUpOrderBL");
}
else{


	// For changing the guaranteed ship date
	$t->set_var("SHIPDATE_MONTH_SEL", Widgets::BuildMonthSelect(date("n", $DateOrderedTimeStamp)));
	$t->set_var("SHIPDATE_DAY_SEL", Widgets::BuildDaySelect(date("j", $DateOrderedTimeStamp)));
	$t->set_var("SHIPDATE_YEAR_SEL", Widgets::BuildYearSelect(date("Y", $DateOrderedTimeStamp)));
	
	$t->allowVariableToContainBrackets("SHIPDATE_MONTH_SEL");
	$t->allowVariableToContainBrackets("SHIPDATE_DAY_SEL");
	$t->allowVariableToContainBrackets("SHIPDATE_YEAR_SEL");
	$t->allowVariableToContainBrackets("SHIPDATE_HOUR_SEL");
	$t->allowVariableToContainBrackets("SHIPDATE_MIN_SEL");
	$t->allowVariableToContainBrackets("SHIPDATE_AMPM_SEL");
	
	$hoursArr = array("1"=>"1", "2"=>"2", "3"=>"3", "4"=>"4", "5"=>"5", "6"=>"6", "7"=>"7", "8"=>"8", "9"=>"9", "10"=>"10" ,"11"=>"11" ,"12"=>"12");
	$t->set_var("SHIPDATE_HOUR_SEL", Widgets::buildSelect($hoursArr, array(date("g", $DateOrderedTimeStamp)), "hour"));
	
	
	
	$minutesArr = array();
	for($i=0; $i<60; $i++){
		$minuteVal = $i;
		if(strlen($minuteVal) == 1)
			$minuteVal = "0" . $minuteVal;
		$minutesArr[$minuteVal] = $minuteVal;
	}
	
	$t->set_var("SHIPDATE_MIN_SEL", Widgets::buildSelect($minutesArr, array(date("i", $DateOrderedTimeStamp)), "minute"));
	
	$t->set_var("SHIPDATE_AMPM_SEL", Widgets::buildSelect(array("am"=>"am", "pm"=>"pm"), array(date("a", $DateOrderedTimeStamp)), "ampm"));


	$t->set_var("TODAY_MONTH", date("n"));
	$t->set_var("TODAY_DAY", date("j"));
	$t->set_var("TODAY_YEAR", date("Y"));
	$t->set_var("YESTERDAY_MONTH", date("n", (time() - 86400)));
	$t->set_var("YESTERDAY_DAY", date("j", (time() - 86400)));
	$t->set_var("YESTERDAY_YEAR", date("Y", (time() - 86400)));
}





// Create the drop down list for the projects
$ProjectListDropDownHTML = Order::BuildDropDownForProjectLink($dbCmd, $orderno, $VendorRestriction, "");
$t->set_var("PROJECT_LIST", $ProjectListDropDownHTML);

$t->allowVariableToContainBrackets("PROJECT_LIST");


// Get the Status History for the Shipping Method Changes
$shippingChoiceHistoryHTML = "";
$dbCmd->Query("SELECT *, UNIX_TIMESTAMP(Date) AS UnixDate FROM shippingchoicehistory WHERE OrderID=$orderno ORDER BY ID ASC");
$i=0;
while($row = $dbCmd->GetRow()){

	if($CustomerID == $row["UserID"])
		$MethodChangedBy = "the Customer";
	else
		$MethodChangedBy = UserControl::GetNameByUserID($dbCmd2, $row["UserID"]);
		
	
	$shippingChoiceHistoryHTML .= ShippingChoices::getHtmlChoiceName($row["ShippingChoiceID"]);
	$shippingChoiceHistoryHTML .= " -- " . date("D, M j, Y g:i a", $row["UnixDate"]) . " by " . $MethodChangedBy;
	
	$i++;
	if($i < $dbCmd->GetNumRows())
		$shippingChoiceHistoryHTML .= "<hr>";
}

// If this domain does shipping downgrading... then we also want to show Shipping Method history to indicate how downgrading works.
if($domainObj->doesDomainDowngradeShipping($domainID)){
	
	$dbCmd->Query("SELECT ShippingMethodCode, UNIX_TIMESTAMP(Date) AS UnixDate FROM shippingmethodhistory WHERE OrderID=$orderno ORDER BY ID ASC");
	
	if($dbCmd->GetNumRows() != 0)
		$shippingChoiceHistoryHTML .= "<br><br><u>Shipping Method Export History</u><br>";
	
	$i=0;
	while($row = $dbCmd->GetRow()){
			$shippingChoiceHistoryHTML .= WebUtil::htmlOutput($shippingMethodObj->getShippingMethodName($row["ShippingMethodCode"]));
			$shippingChoiceHistoryHTML .= " : " . date("D, M j, Y", $row["UnixDate"]);

			$i++;
			if($i < $dbCmd->GetNumRows())
				$shippingChoiceHistoryHTML .= "<br>";
	}
}



if(empty($shippingChoiceHistoryHTML))
	$shippingChoiceHistoryHTML = "No Shipping Method History";
	
$t->set_var("SHIPPING_METHOD_HISTORY", $shippingChoiceHistoryHTML);
$t->allowVariableToContainBrackets("SHIPPING_METHOD_HISTORY");






//  Find out if this order has any returns
$ReturnedPackagesObj = new ReturnedPackages($dbCmd);
$NotificationIDarr = $ReturnedPackagesObj->GetReturnedPackagesByOrderID($orderno);

$t->set_block("origPage","returnedItemsBL","returnedItemsBLout");
foreach($NotificationIDarr as $ThisNotificationID){

	$t->set_var("MESSAGES", $ReturnedPackagesObj->GetMessagesFromNotificationID($ThisNotificationID));
	$t->set_var("RETURNED_PACKAGE_COMM", $ReturnedPackagesObj->GetCommandLinksFromNotificationID($ThisNotificationID));

	$t->allowVariableToContainBrackets("MESSAGES");
	$t->allowVariableToContainBrackets("RETURNED_PACKAGE_COMM");
	
	$t->parse("returnedItemsBLout","returnedItemsBL",true);
}
if(sizeof($NotificationIDarr) == 0)
	$t->discard_block("origPage","EmptyReturnedItemsBL");




$taskCollectionObj = new TaskCollection();
$taskCollectionObj->limitShowTasksBeforeReminder(true);
$taskCollectionObj->limitUserID($UserID);
$taskCollectionObj->limitAttachedToName("order");
$taskCollectionObj->limitAttachedToID($orderno);
$taskCollectionObj->limitUncompletedTasksOnly(true);
$taskObjectsArr = $taskCollectionObj->getTaskObjectsArr();

$tasksDisplayObj = new TasksDisplay($taskObjectsArr);
$tasksDisplayObj->setTemplateFileName("tasks-template.html");
$tasksDisplayObj->setReturnURL($CurrentURL);
$tasksDisplayObj->displayAsHTML($t);		





// See if they can see customer service items 
if(!$AuthObj->CheckForPermission("VIEW_CUSTOMER_SERVICE")){
	$t->set_block("origPage","HideCustomerServiceBL","HideCustomerServiceBLout");
	$t->set_var("HideCustomerServiceBLout", "");
}
else{

	//Get a List of CSitems associated with this order
	$CSItemsArr = CustService::GetCSitemIDsInOrder($dbCmd, $orderno);

	CustService::ParseCSBlock($dbCmd, $t, $CSItemsArr, "csInquiriesBL", "#EEEEFF", $UserID, $CurrentURL);

	if(sizeof($CSItemsArr) == 0){
		$t->set_block("origPage","EmptyCSInquiriesBL","EmptyCSInquiriesBLout");
		$t->set_var(array("EmptyCSInquiriesBLout"=>""));
	}
}






$t->set_block("origPage","MessageThreadBL","MessageThreadBLout");

// Extract Inner HTML blocks out of the Block we just extracted.
$t->set_block ( "MessageThreadBL", "CloseMessageLinkBL", "CloseMessageLinkBLout" );

$messageThreadCollectionObj = new MessageThreadCollection();	

$messageThreadCollectionObj->setUserID($UserID);
$messageThreadCollectionObj->setRefID($orderno);
$messageThreadCollectionObj->setAttachedTo("order");

$messageThreadCollectionObj->loadThreadCollection();

$messageThreadCollection = $messageThreadCollectionObj->getThreadCollection();

foreach ($messageThreadCollection as $messageThreadObj) {
	
	$messageThreadHTML = MessageDisplay::generateMessageThreadHTML($messageThreadObj);
			
	$t->set_var(array(
		"MESSAGE_BLOCK"=>$messageThreadHTML,
		"THREAD_SUB"=>WebUtil::htmlOutput($messageThreadObj->getSubject()),
		"THREADID"=>$messageThreadObj->getThreadID()
		));

	$t->allowVariableToContainBrackets("MESSAGE_BLOCK");
		
	// Discard the inner blocks (for the Close Message Link) if there are no unread messages.
	$unreadMessageIDs = $messageThreadObj->getUnReadMessageIDs();
	$t->set_var("UNREAD_MESSAGE_IDS", implode("|", $unreadMessageIDs));
	
	if(!empty($unreadMessageIDs))
		$t->parse("CloseMessageLinkBLout", "CloseMessageLinkBL", false );
	else
		$t->set_var("CloseMessageLinkBLout", "");		
		

	$t->parse("MessageThreadBLout","MessageThreadBL", true);	
}	

if(sizeof($messageThreadCollection) == 0)
	$t->set_var(array("MessageThreadBLout"=>"No messages with this Order item."));
	




	
	

$t->set_block("origPage","AdjustmentBL","AdjustmentBLout");
$VendorAdjustmentTotal = 0;
$CustomerAdjustmentTotal = 0;




// Find out if there are any price adjustments with this order.
$query = "SELECT CustomerAdjustment, VendorAdjustment, CustomerAdjustmentType, 
		UNIX_TIMESTAMP(DateCreated) AS DateCreated, Description, VendorID, FromUserID 
		FROM balanceadjustments WHERE OrderID='$orderno'";

if($AuthObj->CheckIfBelongsToGroup("VENDOR"))
	$query .= " AND VendorID= $UserID";

	
$dbCmd->Query($query);

$blanceAdjustementExistsFlag = false;

while($row = $dbCmd->GetRow()){

	$CustomerAdjustment = $row["CustomerAdjustment"];
	$vendorAdjust = $row["VendorAdjustment"];
	$Adjustcreated = $row["DateCreated"];
	$AdjustDesc = $row["Description"];
	$thisVendorID = $row["VendorID"];
	$fromUserID = $row["FromUserID"];
	$custAdjustmentType = $row["CustomerAdjustmentType"];

	// incrment these variables so we can calculate total order income
	$VendorAdjustmentTotal += $vendorAdjust;
	$CustomerAdjustmentTotal += $CustomerAdjustment;

	// Color red or green for pos or neg
	$CustomerAdjustment = Widgets::GetColoredPrice($CustomerAdjustment);
	$vendorAdjust = Widgets::GetColoredPrice($vendorAdjust);

	$Adjustcreated = date ("M-d-y", $Adjustcreated);
	
	
	
	
	$AdjustDesc = WebUtil::htmlOutput($AdjustDesc);
	
	// Find out if there are any pending balance adjustments (without a capture).  This can only happen when the order is not complete.
	if($custAdjustmentType == BalanceAdjustment::CUSTOMER_ADJST_AUTH_ONLY || $custAdjustmentType == BalanceAdjustment::CUSTOMER_ADJST_REFUND_AUTH){
		if(!Order::CheckIfOrderComplete($dbCmd2, $orderno))
			$AdjustDesc = "<font class='ReallySmallBody' style='color:990066;'>(Pending) </font>" . $AdjustDesc;
	}
		
	// If we have put an adjustment on their account... provide a way to resend the email to the user.
	$AdjustDesc = preg_replace("/(Copyright Permissions on Template)/", "$1 <a href='javascript:resendCopyrightTemplateEmail()' class='BlueRedLinkRecSM'>Resend Email to Customer</a>", $AdjustDesc);
	

	$AdjustDesc = $AdjustDesc . "<br>&nbsp;&nbsp;<font class='ReallySmallBody'><font color='#aaaaaa'>By: " . WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd2, $fromUserID)) .  "</font></font>";
	
	
	// If the person looking at this page is not a vendor... then show them the name of the vendor the adjustment was applied to.  There could be multiple vendors on the order.
	if(!$AuthObj->CheckIfBelongsToGroup("VENDOR")){
		
		if(!empty($thisVendorID)){
			$VendorName = Product::GetVendorNameByUserID($dbCmd2, $thisVendorID);
		
			$vendorAdjust = "<nobr>" . WebUtil::htmlOutput($VendorName) . "</nobr><br>" . $vendorAdjust;
			
			$CustomerAdjustment = "";
		}
		else{
			$vendorAdjust = "";
		}
	}

	$t->set_var(array("V_PRICE"=>$vendorAdjust, "C_PRICE"=>$CustomerAdjustment, "DATE"=>$Adjustcreated, "ADJ_DESC"=>$AdjustDesc));
	$t->allowVariableToContainBrackets("V_PRICE");
	$t->allowVariableToContainBrackets("C_PRICE");
	$t->allowVariableToContainBrackets("ADJ_DESC");
	
	$t->parse("AdjustmentBLout","AdjustmentBL",true);

	$blanceAdjustementExistsFlag = true;
}

// Erase the block if we got no balance adjustments to display.
if(!$blanceAdjustementExistsFlag){
	$t->set_block("origPage","HideAdjustmentTableBL","HideAdjustmentTableBLout");
	$t->set_var(array("HideAdjustmentTableBLout"=>"No adjustments yet.<br>"));
}




// Some people may not be able to Add Balance adjustments... even if they can see the Balance Adjustment list.
if(!$AuthObj->CheckForPermission("ADD_BALANCE_ADJUSTMENTS"))
	$t->discard_block("origPage", "AdjustmentButtonBL");



	
// If the Order is active (and not completed) and there are no balance adjustments... then hide the block completely (including button to add a new adjustment)
// Completed orders 
if(!Order::CheckIfOrderComplete($dbCmd, $orderno) && !$inactiveOrderFlag && !$blanceAdjustementExistsFlag)
	$t->discard_block("origPage", "HideAdjusmentSectionBL");

// Do not just remove the copyright block because the Order is empty.  
// It is possible that a copyright charge was put on the order before a project got canceled... adn therefore they still need to be able to do a refund.
if($inactiveOrderFlag && !$blanceAdjustementExistsFlag)
	$t->discard_block("origPage", "HideAdjusmentSectionBL");

	
	
// Find out if there are any projects in the Order that have a 'N'ormal priority
// If so, provide a button to mark the order as 'U'rgent
$dbCmd->Query("SELECT COUNT(*) FROM projectsordered WHERE Priority='N' AND OrderID=$orderno");
if($dbCmd->GetValue() == 0){
	$t->set_var("PRIORITY_NORMAL", "");
	$t->set_var("PRIORITY_URGENT", "checked");
}
else{
	$t->set_var("PRIORITY_NORMAL", "checked");
	$t->set_var("PRIORITY_URGENT", "");
}






// If there is more than 1 CSitem from this email, then show them a link to view all
$custServiceCnt = CustService::GetCustServCountFromUser($dbCmd, $CustomerID, 0);
if($custServiceCnt > 0 && $AuthObj->CheckForPermission("VIEW_CUSTOMER_SERVICE")){
	$CustomerServiceLink = "<br><img src='./images/transparent.gif' width='5' height='5'><br><a href='./ad_cs_home.php?view=search&bydaysold=&bykeywords=U".$CustomerID."' class='BlueRedLink'>Cust. Srvc. ($custServiceCnt)</a>";
}
else{
	if($AuthObj->CheckForPermission("VIEW_CUSTOMER_SERVICE"))
		$CustomerServiceLink = "<br><img src='./images/transparent.gif' width='5' height='5'><br><font class='ReallySmallBody'>No Cust. Srvc.</font>";
	else
		$CustomerServiceLink = "";
}
$t->set_var("CUSTOMER_SERVICE_LINK", $CustomerServiceLink);
$t->allowVariableToContainBrackets("CUSTOMER_SERVICE_LINK");


$t->set_var("MEMOS", CustomerMemos::getLinkDescription($dbCmd, $CustomerID, false));
$t->allowVariableToContainBrackets("MEMOS");


if(WebUtil::GetInput("neworderfromdate", FILTER_SANITIZE_INT))
	$t->set_var("ORDERFROMDATE", "<font color='#CC0000'>The Ship &amp; Arrival dates were just changed, as if the order was placed on <br><b>" . date('l jS \of F Y g:i A', WebUtil::GetInput("neworderfromdate", FILTER_SANITIZE_INT)) . "</b><br><br></font>");
else
	$t->set_var("ORDERFROMDATE", "");

$t->allowVariableToContainBrackets("ORDERFROMDATE");
	



if($AuthObj->CheckForPermission("CHAT_SEARCH")){
	
	$userChatCount = ChatThread::getChatCountByUser($dbCmd, $CustomerID);
	
	if($userChatCount > 0)
		$t->set_var("USER_CHATS", "&nbsp;&nbsp;&nbsp;&nbsp;<a href='ad_chat_search.php?user_id=$CustomerID' class='BlueRedLinkRecSM'>Chats: $userChatCount</a>");
	else
		$t->set_var("USER_CHATS", "");
	
	$t->allowVariableToContainBrackets("USER_CHATS");
}
else{
	$t->set_var("USER_CHATS", "");
}



// Output compressed HTML
WebUtil::print_gzipped_page_start();

$t->pparse("OUT","origPage");

WebUtil::print_gzipped_page_end();





?>
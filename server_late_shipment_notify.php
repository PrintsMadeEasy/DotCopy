<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

$UserControlObj = new UserControl($dbCmd2);




// --- Basically we want to notify people at the end of the day if there order was supposed to ship and it did not.
// --- We are only looking at orders that have a guaranteed Ship Date as of "Today" that didn't go.  That way we don't
// --- have to worry about the same person getting 3 emails if a set of Business Cards is late shipping 3 days.
// --- In this case they would only get 1 email notification the first day that it is detected as being late.

print "Starting Late Shipments Notifications<br><br>";
flush();



set_time_limit(3600);




// Build an array of all of the statuses that consitute us to notify someone of their order being late.
// We don't want to send them an email if the order is "Finished", "Canceled" or "On Hold".
$doNotIncludeTheseStatuses = array("H", "F", "C", "W");

$temp_statusDescriptionCharsArr = array_keys(ProjectStatus::GetStatusDescriptionsHash());

$statusDescriptionCharsArr = array();

foreach($temp_statusDescriptionCharsArr as $thisStatusChar){

	if(!in_array($thisStatusChar, $doNotIncludeTheseStatuses))
		$statusDescriptionCharsArr[] = $thisStatusChar;
}


$status_WHERE_Clause = "";

// Build a Nested Where clause for the Status Characters
for($i=0; $i<sizeof($statusDescriptionCharsArr); $i++){
	if($i==0)
		$status_WHERE_Clause .= " projectsordered.Status = '" . DbCmd::EscapeSQL($statusDescriptionCharsArr[$i]) . "' ";
	else
		$status_WHERE_Clause .= " OR projectsordered.Status = '" . DbCmd::EscapeSQL($statusDescriptionCharsArr[$i]) . "' ";
}





// Only include "Urgent" and "High" Shipipng Choices.  Normally doesn't include "2 day shipping".
$shipping_WHERE_clause = " projectsordered.ShippingPriority='".ShippingChoices::URGENT_PRIORITY."' 
								OR projectsordered.ShippingPriority='".ShippingChoices::HIGH_PRIORITY."' ";





$BegginingOfTodayTimeStamp = mktime (0,1,1,date("n"),date("j"),date("Y"));
$EndingOfTodayTimeStamp = mktime (23,59,59,date("n"),date("j"),date("Y")); 

// convert to Mysql Format.
$BegginingOfTodayTimeStamp = date("YmdHis", $BegginingOfTodayTimeStamp);
$EndingOfTodayTimeStamp = date("YmdHis", $EndingOfTodayTimeStamp);


// Get a list of all Order Numbers that have at least one project inside that was supposed to ship today that didn't (with expedited shipping).
$dbCmd->Query("SELECT DISTINCT orders.ID FROM orders INNER JOIN projectsordered ON projectsordered.OrderID = orders.ID 
		WHERE ($shipping_WHERE_clause) AND ($status_WHERE_Clause) AND (projectsordered.EstShipDate BETWEEN $BegginingOfTodayTimeStamp AND $EndingOfTodayTimeStamp)");

// Store Order ID's in a temporary Array first...
$OrderIDArray = array();

while ($OrdID = $dbCmd->GetValue())
	$OrderIDArray[] = $OrdID;


foreach($OrderIDArray as $ThisOrderID){

	print $ThisOrderID . "<br>";
	flush();

	// Get the UserID of this order.
	$dbCmd->Query("SELECT UserID FROM orders WHERE ID=$ThisOrderID");
	$thisUserID = $dbCmd->GetValue();
	
	$UserControlObj->LoadUserByID($thisUserID);
	
	
	$domainIDofOrder = Order::getDomainIDFromOrder($ThisOrderID);
	
	// Get a unique list of all Product ID's that where supposed to ship today within this order.
	$productIDarr = array();
	$dbCmd->Query("SELECT DISTINCT ProductID FROM projectsordered WHERE OrderID = $ThisOrderID AND
			($status_WHERE_Clause) AND (EstShipDate BETWEEN $BegginingOfTodayTimeStamp AND $EndingOfTodayTimeStamp)"); 
	while($thisProductID = $dbCmd->GetValue())
		$productIDarr[] = $thisProductID;
		
	 
	// Now we want to get a count of each one of these products
	$projectCountsPerProductArr = array();
	foreach($productIDarr as $thisProductID){
	
		if(Product::checkIfProductHasMailingServices($dbCmd, $thisProductID))
			continue;
	
		$dbCmd->Query("SELECT COUNT(*) FROM projectsordered WHERE OrderID = $ThisOrderID AND ProductID = $thisProductID AND 
					($status_WHERE_Clause) AND (EstShipDate BETWEEN $BegginingOfTodayTimeStamp AND $EndingOfTodayTimeStamp)"); 
	
		$projectCountsPerProductArr["$thisProductID"] = $dbCmd->GetValue();
	}
	
	$pluralAmount = false;
	
	// Build a product description... like "2 sets of business cards, 1 set of letterhead, and 1 set of Postcards".
	$productDescriptionStr = "";
	$counter = 0;
	foreach($projectCountsPerProductArr as $thisProductID => $thisProjectCount){
	
		$productObj = Product::getProductObj($dbCmd, $thisProductID);

		// Only show them the detailed Product Description is there is more than one product in the Order.
		if(sizeof($projectCountsPerProductArr) > 1)
			$productName = $productObj->getProductTitleWithExtention();
		else
			$productName = $productObj->getProductTitle();
	
		$productDescriptionStr .= $thisProjectCount . " set" . LanguageBase::GetPluralSuffix($thisProjectCount, "", "s") . " of " . $productName;
		
		$counter++;
		
		if($counter < sizeof($projectCountsPerProductArr)){
		
			if($counter == sizeof($projectCountsPerProductArr) - 1)
				$productDescriptionStr .= " and ";
			else
				$productDescriptionStr .= ", ";
		}
		
		if($counter > 1 || $thisProjectCount > 1)
			$pluralAmount = true;
	}
	
	
	if(!empty($projectCountsPerProductArr)){
		
		$domainEmailConfigObj = new DomainEmails($domainIDofOrder);
	
		// Now build the Message.
		$emailSubject = "Late Shipment Notification, Order #" . Order::GetHashedOrderNo($ThisOrderID);

		$emailMessage = "Dear " . $UserControlObj->getNameFirst() . ",\n\n";
		$emailMessage .= "We would like to apologize because " .  $productDescriptionStr . " " . ($pluralAmount ? "were" : "was") . " supposed to ship today with Order # " . Order::GetHashedOrderNo($ThisOrderID) . " but an unexpected problem has caused a delay.\n\n";
		$emailMessage .= "Meeting customer deadlines is a keystone of this company.  Since you paid extra for quicker shipping we understand that the delivery date is important to you.  Let us know if one of these alternate solutions will work for you.\n\n";
		$emailMessage .= "1) If you needed the product before leaving on a trip we may be able to change the Shipping Address so that your order will arrive wherever you do.\n\n";
		$emailMessage .= "2) We may be able to upgrade your shipping method to a quicker service.  UPS has many options like 1 Day shipping (early), Saturday delivery, etc.\n\n";
		$emailMessage .= "3) You can ignore this email and wait for your product to arrive behind schedule.  Email customer service after receiving your product and we will refund your S&H fees (\$50 max).  Our system needs to see the final delivery date within the tracking number before refunding.\n\n";
		$emailMessage .= "\nYou can simply reply to this email and let us know what you want to do.  A Customer Service Representative will also be able to give you an explanation on what caused the delay.\n";
		$emailMessage .= "\n\n\nThe Automation System\n".Domain::getDomainKeyFromID($domainIDofOrder)."\n\n";


		WebUtil::SendEmail($domainEmailConfigObj->getEmailNameOfType(DomainEmails::CUSTSERV), $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::CUSTSERV), $UserControlObj->getName(), $UserControlObj->getEmail(), $emailSubject, $emailMessage);
		
		$emailContactsForLateShipmentsArr = Constants::getEmailContactsForLateShipments();
		foreach($emailContactsForLateShipmentsArr as $thisEmailContact)
			WebUtil::SendEmail($domainEmailConfigObj->getEmailNameOfType(DomainEmails::CUSTSERV), $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::CUSTSERV), $UserControlObj->getName(), $thisEmailContact, $emailSubject, $emailMessage);

	}
	
	sleep(1);
}

print "<br><br>done";



?>
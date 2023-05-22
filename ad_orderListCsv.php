<?

require_once("library/Boot_Session.php");

// PDFs may take a long time to generate on very large artworks, especially with Variable Images. .
set_time_limit(3600);
ini_set("memory_limit", "512M");

$dbCmd = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("ORDER_LIST_CSV"))
	throw new Exception("Permission Denied");


$projectlist = WebUtil::GetInput("projectlist", FILTER_SANITIZE_STRING_ONE_LINE);
$masterProjectListArr = WebUtil::getArrayFromPipeDelimetedString($projectlist);
	
$userDomainIDsArr = $AuthObj->getUserDomainsIDs();

$projectIDarr = array();
$uniqueProductIDsArr = array();

foreach($masterProjectListArr as $projectID){

	if(!preg_match("/^\d+$/", $projectID))
		continue;
		
	// Skip over projects which are not in their selected domain pool... To make sure they possibly figure out other Profile Names from another domain.
	// It is also a Security check.
	$domainIDofOrder = Order::getDomainIDFromOrder(Order::GetOrderIDfromProjectID($dbCmd, $projectID));
	if(!in_array($domainIDofOrder, $userDomainIDsArr))
		continue;
		
	$statusOfProject = ProjectOrdered::GetProjectStatus($dbCmd, $projectID);
	if(in_array($statusOfProject, array("C", "F")))
		continue;
	
	$projectIDarr[] = $projectID;
}



$csvFile = "";


$csvFile .= "OrderID,ProjectID,UserID,New Customer,Date Ordered,Shipping Name,Shipping Company,Shipping Address,Shipping City,Shipping State,Shipping Zip,Shipping Country,Status,Shipment Date,Arrival Date,Shipping Choice,Default Shipping Method,Shipping Method Downgrade,Default Method Tranist Days,Downgrade Method Transit Days,Order Summary,Multiple Products in This Order (still open),Multiple Open Orders with Same Ship Add.,Past Shipping Date,List Generated On \n";

foreach($projectIDarr as $thisProjectId){
	
	$orderId = Order::GetOrderIDfromProjectID($dbCmd, $thisProjectId);
	
	$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateOrdered) as DateOrdered,UserID, FirstTimeCustomer, 
					ShippingName, ShippingCompany, ShippingAddress, ShippingAddressTwo, ShippingCity, ShippingState, ShippingZip, ShippingCountry  
					FROM orders WHERE ID=" . intval($orderId));
	$orderRow = $dbCmd->GetRow();
	
	$csvFile .= "O" . $orderId . "," . "P" . $thisProjectId . ",U" . $orderRow["UserID"] . "," . $orderRow["FirstTimeCustomer"] . ",";
	$csvFile .= FileUtil::csvEncodeLineFromArr(array(date("D, M jS", $orderRow["DateOrdered"]))) . ",";
	$csvFile .= FileUtil::csvEncodeLineFromArr(array($orderRow["ShippingName"], $orderRow["ShippingCompany"], ($orderRow["ShippingAddress"] . " " . $orderRow["ShippingAddressTwo"]), $orderRow["ShippingCity"], $orderRow["ShippingState"], $orderRow["ShippingZip"], $orderRow["ShippingCountry"]));
	
	$csvFile .= ",";
	
	$dbCmd->Query("SELECT Status,UNIX_TIMESTAMP(EstArrivalDate) as EstArrivalDate, UNIX_TIMESTAMP(EstShipDate) as EstShipDate, 
					Status, OrderDescription  
					FROM projectsordered WHERE ID=" . intval($thisProjectId));
	$projectRow = $dbCmd->GetRow();
	
	$shippingChoiceID = Order::GetShippingChoiceIDFromProjectID($dbCmd, $thisProjectId);
	
	$shippingChoiceObj = new ShippingChoices(Order::getDomainIDFromOrder($orderId));
	$shippingMethodObj = new ShippingMethods(Order::getDomainIDFromOrder($orderId));
	
	$shippingChoiceName = $shippingChoiceObj->getShippingChoiceName($shippingChoiceID);
	$defaultShippingMethodCode = $shippingChoiceObj->getDefaultShippingMethodCode($shippingChoiceID);
	
	$defaultShippingMethodName = $shippingMethodObj->getShippingMethodName($defaultShippingMethodCode, true);
	
	// A project can be split amount multiple shipments.  However, we are only interested in the first one.
	$shipmentIdsOfProjectArr = Shipment::GetShipmentsIDsWithinProject($dbCmd, $thisProjectId);
	$shipmentIdOfProject = array_pop($shipmentIdsOfProjectArr);
	
	$domainAddressObj = new DomainAddresses(Order::getDomainIDFromOrder($orderId));
	$shipFromAddressObj = $domainAddressObj->getDefaultProductionFacilityAddressObj();
		
	$transitTimesObj = $shippingMethodObj->getTransitTimes($shipFromAddressObj, Order::getShippingAddressObject($dbCmd, $orderId));
		
	$shippingDowngradeMethodCode = $defaultShippingMethodCode;

	
	// In case a Shipipng Carrier changed their "Available Destinations" after the order was placed... or the order is a Reprint with an invalid address we could get an InvalidShippingAddress Exception
	try{
		// If we are ahead of schedule with production (or the person is close by) then we may downgrade the shipping method to save money.
		// We can be sure that the shipment is leaving today if we are exporting the shipping methods.
		$adjMethodsList = ShippingTransfers::getAdjustedShippingMethods($dbCmd, $shipmentIdOfProject);

		$shippingDowngradeMethodCode = array_pop($adjMethodsList);
		
	}
	catch (ExceptionInvalidShippingAddress $e){
		WebUtil::WebmasterError("Could Not Determine Shipments on Order: " . $orderId . " because the Address is Invalid. \n\nException: " . $e->getMessage());
	}
	
	$downgradedShippingMethodName = $shippingMethodObj->getShippingMethodName($shippingDowngradeMethodCode, true);
	$transitDaysOnDefaultShippingMethod = $transitTimesObj->getTransitDays($defaultShippingMethodCode);
	$transitDaysOnShippingMethodDowngrade = $transitTimesObj->getTransitDays($shippingDowngradeMethodCode);
	
	$csvFile .= ProjectStatus::GetProjectStatusDescription($projectRow["Status"]) . ",";
	
	$csvFile .= FileUtil::csvEncodeLineFromArr(array(TimeEstimate::formatTimeStamp($projectRow["EstShipDate"]))) . ",";
	$csvFile .= FileUtil::csvEncodeLineFromArr(array(TimeEstimate::formatTimeStamp($projectRow["EstArrivalDate"]))) . ",";
	$csvFile .= FileUtil::csvEncodeLineFromArr(array($shippingChoiceName)) . ",";
	$csvFile .= FileUtil::csvEncodeLineFromArr(array($defaultShippingMethodName)) . ",";
	$csvFile .= FileUtil::csvEncodeLineFromArr(array($downgradedShippingMethodName)) . ",";
	$csvFile .= $transitDaysOnDefaultShippingMethod . ",";
	$csvFile .= $transitDaysOnShippingMethodDowngrade . ",";
	$csvFile .= FileUtil::csvEncodeLineFromArr(array($projectRow["OrderDescription"])) . ",";
	
	// Find out if the customers has multiple products in "Open Order" status.
	$customerId = Order::GetUserIDFromOrder($dbCmd, $orderId);
	
	// Find out if there are multiple products in the same order that are not finished or canceled yet.
	$dbCmd->Query("SELECT COUNT(*) FROM projectsordered WHERE OrderID=" . intval($orderId) . " AND Status != 'F' AND Status != 'C' AND ProductID != " . ProjectOrdered::getProductIDquick($dbCmd, "ordered", $thisProjectId) );
	$additionalProductCountInOrder = $dbCmd->GetValue();
	
	if($additionalProductCountInOrder > 0)
		$csvFile .= "YES,";
	else
		$csvFile .= ",";
	
		
	// Now find out if the customer has multiple open orders with the shipping address matching this one.
	$dbCmd->Query("SELECT orders.ID FROM orders USE INDEX(orders_UserID) 
					INNER JOIN projectsordered ON projectsordered.OrderID = orders.ID 
					WHERE orders.UserID=". intval($customerId) . " 
					AND Status != 'F' AND Status != 'C'
					AND orders.ID != " . intval($orderId));
	$orderIDArr = $dbCmd->GetValueArr();
	$orderIDArr = array_unique($orderIDArr);
	
	// Get a Shipment String that we can use for comparing.
	// Just because the shipping address matches, doesn't mean it is addressed to the same person in the company.
	$thisShippingAddress = $orderRow["ShippingName"] . $orderRow["ShippingCompany"] . $orderRow["ShippingAddress"] . $orderRow["ShippingAddressTwo"] . $orderRow["ShippingZip"];
	$thisShippingAddress = strtoupper($thisShippingAddress);
	$thisShippingAddress = md5($thisShippingAddress);
	
	$otherOrdersWithSameShipAddressCount = 0;
	foreach($orderIDArr as $thisOrderID){
		$dbCmd->Query("SELECT ShippingName, ShippingCompany, ShippingAddress, ShippingAddressTwo, ShippingZip  
						FROM orders WHERE ID=" . intval($thisOrderID));
		$anotherOrderRow = $dbCmd->GetRow();
		
		$anotherShippingAddress = $anotherOrderRow["ShippingName"] . $anotherOrderRow["ShippingCompany"] . $anotherOrderRow["ShippingAddress"] . $anotherOrderRow["ShippingAddressTwo"] . $anotherOrderRow["ShippingZip"];
		$anotherShippingAddress = md5(strtoupper($anotherShippingAddress));
		
		if($thisShippingAddress == $anotherShippingAddress)
			$otherOrdersWithSameShipAddressCount++;
	}
	
	if($otherOrdersWithSameShipAddressCount > 0)
		$csvFile .= "Duplicate Orders: " . $otherOrdersWithSameShipAddressCount . ",";
	else 
		$csvFile .= ",";
		
	
	// Find out if today is past the Shipping Estimated Date
	$estimatedShippingDate = mktime(2,2,2,date("j", $projectRow["EstShipDate"]),date("n", $projectRow["EstShipDate"]),date("Y", $projectRow["EstShipDate"]));
	$todayDate = mktime(1,1,1,date("j"),date("n"),date("Y"));
		
	if($estimatedShippingDate < $todayDate)
		$csvFile .= "LATE,";
	else
		$csvFile .= ",";
		
	$csvFile .= FileUtil::csvEncodeLineFromArr(array(date("D, M jS")));

	
	
	$csvFile .= "\n";
	

}


$csvFileName = "OpenOrdersCSV_" . date("D_jS_h-i") . ".csv";



// fix for IE catching or PHP bug issue
header("Pragma: public");
header("Expires: 0"); // set expiration time
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
// browser must download file from server instead of cache

// force download dialog
header("Content-Type: application/force-download");
header("Content-Type: application/octet-stream");
header("Content-Type: application/download");

// use the Content-Disposition header to supply a recommended filename and
// force the browser to display the save dialog.
header("Content-Disposition: attachment; filename=".$csvFileName.";");

/*
The Content-transfer-encoding header should be binary, since the file will be read
directly from the disk and the raw bytes passed to the downloading computer.
The Content-length header is useful to set for downloads. The browser will be able to
show a progress meter as a file downloads. The content-lenght can be determines by
filesize function returns the size of a file.
*/
header("Content-Transfer-Encoding: binary");
header("Content-Length: ".strlen($csvFile));


print $csvFile;




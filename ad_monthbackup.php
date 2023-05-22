<?

require_once("library/Boot_Session.php");


// Don't make it look like we are favoring a particular Domain 
Domain::removeTopDomainID();

$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();




//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

$domainObj = Domain::singleton();

if(!$AuthObj->CheckForPermission("REPORT_MONTH_BACKUP"))
	throw new Exception("The month report is invalid.");


// Format the dates that we want for MySQL
$start_timestamp = WebUtil::GetInput("starttimestamp", FILTER_SANITIZE_INT);
$end_timestamp = WebUtil::GetInput("endtimestamp", FILTER_SANITIZE_INT);
$start_mysql_timestamp = date("YmdHis", $start_timestamp);
$end_mysql_timestamp  = date("YmdHis", $end_timestamp);


if($start_timestamp > $end_timestamp)
	throw new Exception("Illegal Date Range");




$query = "SELECT orders.ID AS OrderID, users.Name, users.Company, projectsordered.OrderDescription, UNIX_TIMESTAMP(orders.DateOrdered) AS DateOrdered, 
		projectsordered.ID AS ProjectID, projectsordered.CustomerSubtotal,  
		projectsordered.CustomerTax, projectsordered.CustomerDiscount, projectsordered.OptionsDescription, 
		projectsordered.Status, projectsordered.RePrintLink, users.Email, projectsordered.Quantity, UNIX_TIMESTAMP(projectsordered.EstShipDate) AS ShipDate, UNIX_TIMESTAMP(projectsordered.EstArrivalDate) AS ArrivalDate, orders.ShippingChoiceID, orders.ShippingQuote,
		orders.ShippingState, orders.ShippingCity, orders.ShippingZip, orders.ShippingCountry  
		FROM (orders INNER JOIN projectsordered ON orders.ID = projectsordered.OrderID) 
		INNER JOIN users ON orders.UserID = users.ID 
		WHERE " . DbHelper::getOrClauseFromArray("orders.DomainID", $domainObj->getSelectedDomainIDs()) . " AND 
		(orders.DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") ";

if($AuthObj->CheckIfBelongsToGroup("VENDOR"))
	$query .= " AND (" . Product::GetVendorIDLimiterSQL($UserID) . ")";

$query .= " ORDER BY orders.DateOrdered DESC";


$dbCmd->Query($query);


print "------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------\n";
print AddWhite("Order", 20);
print AddWhite("Date", 14);
print AddWhite("User", 60);
print AddWhite("Company", 80);
print AddWhite("Quantity", 9);
print AddWhite("Product Name", 80);
print AddWhite("Options Description", 255);
print AddWhite("Reprint", 8);
print AddWhite("Vendor Amt.", 15);
print AddWhite("Guaranteed Ship", 20);
print AddWhite("Guaranteed Arrival", 20);
print AddWhite("Ship Method", 35);
print AddWhite("Shipping State", 25);
print AddWhite("Shipping City", 80);
print AddWhite("Shipping Zip", 20);
print AddWhite("Freight Charges", 17);
print AddWhite("Package Weight", 15);

if(!$AuthObj->CheckIfBelongsToGroup("VENDOR")){
	print AddWhite("Customer Amt.", 15);
	print AddWhite("Customer Disc.", 15);
	print AddWhite("S & H", 15);
	print AddWhite("Repeat", 8);
	print AddWhite("Email", 50);
}
print "\n";
print "------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------\n";

while ($row = $dbCmd->GetRow()){

	if($row["CustomerDiscount"] == "")
		$row["CustomerDiscount"] = 0;


	$CustomerDiscount = round($row["CustomerSubtotal"] * $row["CustomerDiscount"], 2);

	// Skip this project if it has been canceled 
	if($row["Status"] == "C")
		continue;

	print AddWhite($row["OrderID"] . "-P" . $row["ProjectID"], 20);
	print AddWhite(date("m-j-Y", $row["DateOrdered"]), 14);
	print AddWhite($row["Name"], 60);
	print AddWhite($row["Company"], 80);
	print AddWhite($row["Quantity"], 9);
	print AddWhite(preg_replace("/^\d+\s+/", "", $row["OrderDescription"]), 80); //Strip out the quantity from the order summary to leave only the product name
	print AddWhite($row["OptionsDescription"], 255);

	if($row["RePrintLink"] <> "0")
		print AddWhite("yes", 8);
	else
		print AddWhite("no", 8);


	if($AuthObj->CheckIfBelongsToGroup("VENDOR"))
		$vendorIDLimiter = $UserID;
	else
		$vendorIDLimiter = null;
	$vendorSubTotals = ProjectOrdered::GetVendorSubtotalFromProject($dbCmd2, $row["ProjectID"], $vendorIDLimiter);


	print AddWhite('$' . number_format($vendorSubTotals, 2), 15);
	print AddWhite(date("n/j/y", $row["ShipDate"]), 20);
	print AddWhite(date("n/j/y", $row["ArrivalDate"]), 20);
	print AddWhite(ShippingChoices::getChoiceName($row["ShippingChoiceID"]), 35);
	
	print AddWhite($row["ShippingState"], 25);
	print AddWhite($row["ShippingCity"], 80);
	print AddWhite($row["ShippingZip"], 20);
	
	
	print AddWhite('$' . number_format(Shipment::GetFreightChargesFromOrder($dbCmd2, $row["OrderID"], $vendorIDLimiter), 2), 17);
	print AddWhite(Shipment::GetPackageWeightsFromOrder($dbCmd2, $row["OrderID"], $vendorIDLimiter) . " lbs.", 15);
	

	if(!$AuthObj->CheckIfBelongsToGroup("VENDOR")){
		print AddWhite('$' . number_format($row["CustomerSubtotal"], 2), 15);
		print AddWhite('$' . number_format($CustomerDiscount,2), 15);
		print AddWhite('$' . number_format($row["ShippingQuote"],2), 15);

		if(Order::CheckIfOrderIsRepeat($dbCmd2, $row["OrderID"]))
			print AddWhite("yes", 8);
		else
			print AddWhite("no", 8);

		print AddWhite($row["Email"], 50);
	}


	print "\n";
}


// Will take a string... if it does not meet the minimum characters then it will add additional white spaces
// It will also add 1 tab character
function AddWhite($str, $min_chars){

	if(strlen($str) < $min_chars){
		$str .= GetWhiteSpaces($min_chars - strlen($str)) . "\t";
	}
	return $str;

}
function GetWhiteSpaces($amount){
	$retStr = "";
	for($i=0; $i<$amount; $i++){
		$retStr .= " ";
	}
	return $retStr;
}





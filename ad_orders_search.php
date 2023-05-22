<?

require_once("library/Boot_Session.php");




$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

$ReturnedPackagesObj = new ReturnedPackages($dbCmd);



//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


$domainObj = Domain::singleton();

$t = new Templatex(".");


$t->set_file("origPage", "ad_orders_search-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

// Setup the Search Form with previous values (if any)
$Year = WebUtil::GetInput("year", FILTER_SANITIZE_INT);
if(!$Year)
	$Year = date("Y");
$t->set_var("DATE_SELECT", Widgets::BuildYearSelectWithAllChoice($Year));
$t->set_var("CC_NUM", WebUtil::htmlOutput(WebUtil::GetInput("ccnum", FILTER_SANITIZE_STRING_ONE_LINE)));
$t->set_var("TRACK_NUM", WebUtil::htmlOutput(WebUtil::GetInput("trackingnum", FILTER_SANITIZE_STRING_ONE_LINE)));
$t->set_var("BILLING_NAME_SEARCH", WebUtil::htmlOutput(WebUtil::GetInput("billingname", FILTER_SANITIZE_STRING_ONE_LINE)));

$t->allowVariableToContainBrackets("DATE_SELECT");


if(WebUtil::GetInput("dosearch", FILTER_SANITIZE_STRING_ONE_LINE)){


	if($Year == "ALL"){
		$StartTimeStamp = date("YmdHis", mktime(0, 0, 0, 1, 1, 2002));	
		$EndTimeStamp = date("YmdHis", mktime(0, 0, 0, 1, 1, 2020));
	}
	else{
		$StartTimeStamp = date("YmdHis", mktime(1, 1, 1, 1, 1, $Year));	
		$EndTimeStamp = date("YmdHis", mktime(1, 1, 1, 1, 1, ($Year + 1)));
	}
	
	

	// Create a (possibly Giant) OR clause for the SQL query that limits our Order Query (by Order ID's) to ones having the matching tracking number
	// It would seem more efficient just to do a JOIN on the tables... but in fact there is a bug with MySQL.  If you are joining tables and a fields is being searched on with Wildcards "%bla%";

	// ... then it will join ever single row in the table... regardless if the column is indexed. 
	// This solution using Temp Arrays and secondary queries is the only way to get the task done quickly for right now.
	$TrackingOrClause = "";
	
	if(WebUtil::GetInput("trackingnum", FILTER_SANITIZE_STRING_ONE_LINE)){
		
		
		$dbCmd->Query("SELECT DISTINCT ID FROM shipments WHERE TrackingNumber LIKE \"" . DbCmd::EscapeLikeQuery(WebUtil::GetInput("trackingnum", FILTER_SANITIZE_STRING_ONE_LINE)) . "\"");
		$shipmentIDsNotFilteredForDomainArr = $dbCmd->GetValueArr();
		
		$shipmentIDArr = array();
		foreach($shipmentIDsNotFilteredForDomainArr as $thisShipmentID){
			
			// Make sure that the user has permission to 
			$dbCmd->Query("SELECT COUNT(*) FROM shipmentlink USE INDEX(shipmentlink_ShipmentID) INNER JOIN projectsordered ON shipmentlink.ProjectID = projectsordered.ID
							WHERE shipmentlink.ShipmentID = $thisShipmentID AND " . DbHelper::getOrClauseFromArray("projectsordered.DomainID", $domainObj->getSelectedDomainIDs()));
			if($dbCmd->GetValue() > 0)
				$shipmentIDArr[] = $thisShipmentID;
		}
		
		if(sizeof($shipmentIDArr) > 500)
			WebUtil::PrintAdminError("Your search has returned more than 500 results.  Try to narrow your search criteria.");
		
		$OrderIDLimitTrackingArr = array();
		foreach($shipmentIDArr as $thisShipID){
			$dbCmd->Query("SELECT DISTINCT OrderID FROM projectsordered AS PO INNER JOIN shipmentlink AS SL ON SL.ProjectID = PO.ID WHERE SL.ShipmentID=$thisShipID 
								AND " . DbHelper::getOrClauseFromArray("PO.DomainID", $domainObj->getSelectedDomainIDs()));
			$OrderIDLimitTrackingArr[] = $dbCmd->GetValue();
		}
		
		foreach($OrderIDLimitTrackingArr as $thisOrderID)
			$TrackingOrClause .= "ID=" . $thisOrderID . " OR ";

		// If we don't have any matching tracking numbers... then make the Order ID something ridiculous so we won't find any matches
		if(empty($TrackingOrClause))
			$TrackingOrClause = "ID=9999999999";	
		else
			$TrackingOrClause = substr($TrackingOrClause, 0, -3); // Get rid of the Last OR
	}
			
	$query = "SELECT ID FROM orders 
				WHERE (DateOrdered BETWEEN $StartTimeStamp AND $EndTimeStamp) 
				AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs());
	
	if(WebUtil::GetInput("ccnum", FILTER_SANITIZE_STRING_ONE_LINE))
		$query .= " AND CardNumber LIKE \"%" . DbCmd::EscapeLikeQuery(WebUtil::GetInput("ccnum", FILTER_SANITIZE_STRING_ONE_LINE)) . "\" ";
	if(WebUtil::GetInput("billingname", FILTER_SANITIZE_STRING_ONE_LINE))
		$query .= " AND BillingName LIKE \"%" . DbCmd::EscapeLikeQuery(WebUtil::GetInput("billingname", FILTER_SANITIZE_STRING_ONE_LINE)) . "%\" ";
	if(WebUtil::GetInput("trackingnum", FILTER_SANITIZE_STRING_ONE_LINE))
		$query .= " AND ($TrackingOrClause)";
	
	$query .= " ORDER BY DateOrdered DESC";


	$OrderIDArr = array();
	$dbCmd->Query($query);
	
	if($dbCmd->GetNumRows() > 500)
		WebUtil::PrintAdminError("Your search has returned more than 500 results. Try to narrow your search criteria.");


	while($ThisOrderID = $dbCmd->GetValue())
		$OrderIDArr[] = $ThisOrderID;


	$t->set_block("origPage","itemsBL","itemsBLout");
	foreach($OrderIDArr as $ThisOrderID){
	
		if(Order::CheckForEmptyOrder($dbCmd, $ThisOrderID))
			continue;
			
		// Don't show a vendor an order with matching criteria, unless they own at least 1 project in the order
		if($AuthObj->CheckIfBelongsToGroup("VENDOR")){
		
			$VendorRestriction = $UserID;
			
			$VendorProjectCount = Order::GetProjectCountByVendor($dbCmd, $VendorRestriction, $ThisOrderID);
			if($VendorProjectCount == 0)
				continue;
		}
		else
			$VendorRestriction = "";
			

		$dbCmd->Query("SELECT *, UNIX_TIMESTAMP(DateOrdered) AS UnixDate FROM orders WHERE ID=$ThisOrderID");
		$row = $dbCmd->GetRow();

		$t->set_var("ORDERNO", $ThisOrderID);
		$t->set_var("BILLING_NAME", WebUtil::htmlOutput($row["BillingName"]));
		$t->set_var("GRAND_TOTAL", Order::GetGrandTotalOfOrder($dbCmd, $ThisOrderID));
		$t->set_var("ORDER_DATE", date("M j, Y g:i a", $row["UnixDate"]));
		
		if($row["BillingType"] == "N"){
			
			$row["CardNumber"] = substr($row["CardNumber"], -4);
				
			$t->set_var("PAYMENT_TYPE", "Card # ***** " . $row["CardNumber"]);
		}
		else if($row["BillingType"] == "C"){
			$t->set_var("PAYMENT_TYPE", "Corporate Billing");
		}
		else
			throw new Exception("Illegal Payment Type for order #" . $ThisOrderID);
			
		
		$t->set_var("SHIP_METHOD", ShippingChoices::getHtmlChoiceName($row["ShippingChoiceID"]));
		$t->allowVariableToContainBrackets("SHIP_METHOD");
		
		// Format the Shipping Address
		if(!empty($row["ShippingCompany"]))
			$Attention =  WebUtil::htmlOutput($row["ShippingCompany"]) . "<br>Attn: " . WebUtil::htmlOutput($row["ShippingName"]) . "<br>";
		else
			$Attention = WebUtil::htmlOutput($row["ShippingName"]) . "<br>";
		
		$ShippingAddress = $Attention . " " . WebUtil::htmlOutput($row["ShippingAddress"]) . " " . WebUtil::htmlOutput($row["ShippingAddressTwo"]) . "<br>" . WebUtil::htmlOutput($row["ShippingCity"]) . ", " . WebUtil::htmlOutput($row["ShippingState"]) . " " . WebUtil::htmlOutput($row["ShippingZip"]);
		$t->set_var("SHIPPING_ADDRESS", $ShippingAddress);
		
		$t->allowVariableToContainBrackets("SHIPPING_ADDRESS");
		

		// Build the tracking number list for this Order.
		$ShipmentIDArr = Shipment::GetShipmentsIDsWithinOrder($dbCmd, $ThisOrderID, 0, $VendorRestriction);
		$TrackingNumberList = "";
		$counter = 0;
		foreach($ShipmentIDArr as $ThisShipmentID){
			$shipmentInfo = Shipment::GetInfoByShipmentID($dbCmd, $ThisShipmentID);
			
			if(empty($shipmentInfo["TrackingNumber"]))
				continue;
			
			$TrackingNumberList .= Shipment::GetTrackingLink($shipmentInfo["TrackingNumber"], $shipmentInfo["Carrier"]);
			
			$counter++;
			if($counter < sizeof($ShipmentIDArr))
				$TrackingNumberList .= "<br>";
		}
		$t->set_var("TRACKING", $TrackingNumberList);
		$t->allowVariableToContainBrackets("TRACKING");

		$t->parse("itemsBLout","itemsBL",true);
	}
	
	
	if(sizeof($OrderIDArr) == 0){
		$t->set_block("origPage","EmptyItemsBL","EmptyItemsBLout");
		$t->set_var("EmptyItemsBLout", "No orders were found.");
	}
	

}
else{

	$t->discard_block("origPage", "NoSearchBL");
}



$t->pparse("OUT","origPage");








?>
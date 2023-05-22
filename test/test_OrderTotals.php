<?

require_once("library/Boot_Session.php");



$start_timestamp = mktime (0,0,0, 10, 1, 2008);
$end_timestamp = mktime (0,0,0, 10, 2, 2008);




$dbCmd = new DbCmd();

// Make this script be able to run for a while
set_time_limit(4000);


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("MARKETING_REPORT"))
	WebUtil::PrintAdminError("Not Available");
	

$domainObj = Domain::singleton();


print "Date,Avg. Subtotal New Cust.,Avg. Subtotal Repeat Cust.,Avg. Order Total,New Customers,Repeat Customers,Total Revenue,Visitors,Google Clicks\n";

for($i=0; $i<1180; $i++){

	$start_timestamp += (60 * 60 * 24);
	$end_timestamp += (60 * 60 * 24);

	// Do a nested query to figure out how many clicks each name received 
	$dbCmd->Query("SELECT ID FROM orders WHERE FirstTimeCustomer='Y'
			AND (DateOrdered BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "')
			AND DomainID=2");
	$orderIDsInPeriodNew = $dbCmd->GetValueArr();
	$newCustomerCount = $dbCmd->GetNumRows();
	
	$dbCmd->Query("SELECT ID FROM orders WHERE FirstTimeCustomer='N' 
			AND (DateOrdered BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "')
			AND DomainID=2");
	$orderIDsInPeriodRepeat = $dbCmd->GetValueArr();
	$repeatCustomerCount = $dbCmd->GetNumRows();
	
	$totalNewCustomerOrderTotal = 0;
	$totalRepeatCustomerOrderTotal = 0;
	$shippingFromNewCustomers = 0;
	$shippingFromRepeatCustomers = 0;
	
	foreach($orderIDsInPeriodNew as $thisOrderID){
		$totalNewCustomerOrderTotal += Order::GetTotalFromOrder($dbCmd, $thisOrderID, "customersubtotal") - Order::GetTotalFromOrder($dbCmd, $thisOrderID, "customerdiscount");
		$shippingFromNewCustomers += Order::GetCustomerShippingQuote($dbCmd, $thisOrderID);
	}

	foreach($orderIDsInPeriodRepeat as $thisOrderID){
		$totalRepeatCustomerOrderTotal += Order::GetTotalFromOrder($dbCmd, $thisOrderID, "customersubtotal") - Order::GetTotalFromOrder($dbCmd, $thisOrderID, "customerdiscount");
		$shippingFromRepeatCustomers += Order::GetCustomerShippingQuote($dbCmd, $thisOrderID);
	}
	
	
	$dbCmd->Query("SELECT COUNT(*) FROM visitorsessiondetails WHERE (UserAgent LIKE '%MSIE%' OR UserAgent LIKE '%Firefox%' OR UserAgent LIKE '%Chrome%') 
			AND (DateStarted BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "')
			AND DomainID=2");
	$visitorCount = $dbCmd->GetValue();
	
	$dbCmd->Query("SELECT COUNT(*) FROM bannerlog WHERE (UserAgent LIKE '%MSIE%' OR UserAgent LIKE '%Firefox%' OR UserAgent LIKE '%Chrome%')
			AND Name LIKE 'g%' 
			AND (Date BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "')
			AND DomainID=2");
	$googleClicks = $dbCmd->GetValue();
	
	
	
	print date("n-j-Y", $start_timestamp) . "," . round(($totalNewCustomerOrderTotal/$newCustomerCount),2) . "," . round(($totalRepeatCustomerOrderTotal/$repeatCustomerCount),2) . "," . round((($totalRepeatCustomerOrderTotal+$totalNewCustomerOrderTotal)/($repeatCustomerCount+$newCustomerCount)),2) . ",$newCustomerCount,$repeatCustomerCount," . ($totalNewCustomerOrderTotal + $totalRepeatCustomerOrderTotal + $shippingFromNewCustomers + $shippingFromRepeatCustomers) . ",". $visitorCount . "," . $googleClicks . "\n";


	flush();
}







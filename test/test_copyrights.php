<?

require_once("library/Boot_Session.php");



$start_timestamp = mktime (0,0,0, 10, 1, 2007);
$end_timestamp = mktime (0,0,0, 10, 2, 2007);




$dbCmd = new DbCmd();

// Make this script be able to run for a while
set_time_limit(4000);


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("MARKETING_REPORT"))
	WebUtil::PrintAdminError("Not Available");
	



print "Date,Copyright Charges,Copyright Refunds\n";


while(true){
	
	$dbCmd->Query("SELECT COUNT(DISTINCT UserID) FROM orders INNER JOIN projectsordered ON projectsordered.ID = orders.ID WHERE 
						DateOrdered BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
						AND RePrintLink = 0
						AND FirstTimeCustomer='N'
						AND (orders.DomainID=1 OR orders.DomainID=2)");
	$repeatCustomerCount = $dbCmd->GetValue();

	$dbCmd->Query("SELECT COUNT(DISTINCT UserID) FROM orders WHERE 
						DateOrdered BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
						AND FirstTimeCustomer='Y'
						AND (orders.DomainID=1 OR orders.DomainID=2)");
	$newCustomerCount = $dbCmd->GetValue();
	
	
	$dbCmd->Query("SELECT DISTINCT orders.ID FROM orders INNER JOIN projectsordered ON projectsordered.ID = orders.ID WHERE 
						DateOrdered BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
						AND RePrintLink = 0
						AND FirstTimeCustomer='N'
						AND (orders.DomainID=1 OR orders.DomainID=2)");
	$orderIDsInPeriodRepeat = $dbCmd->GetValueArr();
	
	$allSubtotals = 0;
	$subTotalsWithoutPostage = 0;
	$allDiscounts = 0;
	$subTotalProfit = 0;
	$shippingTotalsFromRepeat = 0;
	
	foreach($orderIDsInPeriodRepeat as $thisOrderID){
		$customerSubtotal = Order::GetTotalFromOrder($dbCmd, $thisOrderID, "customersubtotal"); 
		$customerDiscount = Order::GetTotalFromOrder($dbCmd, $thisOrderID, "customerdiscount"); 
		$vendorSubtotal = Order::GetTotalFromOrder($dbCmd, $thisOrderID, "vendorsubtotal"); 
		$discountDoesNotApplyThisAmount = ProjectGroup::GetTotalFrmGrpPermDiscDoesntApply($dbCmd, $thisOrderID, "ordered");
		
		$allSubtotals += ($customerSubtotal - $customerDiscount);
		$allDiscounts += $customerDiscount;
		$subTotalsWithoutPostage += ($customerSubtotal - $customerDiscount - $discountDoesNotApplyThisAmount);
		$subTotalProfit += ($customerSubtotal - $customerDiscount - $vendorSubtotal);
		$shippingTotalsFromRepeat += Order::GetCustomerShippingQuote($dbCmd, $thisOrderID);
	}
	


	$totalCustomers = $repeatCustomerCount + $newCustomerCount;
	
	print date("n/j/y", $start_timestamp) . ",";
	print $repeatCustomerCount . ",";
	
	if($totalCustomers == 0)
		print "0,";
	else
		print round(($repeatCustomerCount/$totalCustomers),3) . ",";
	
	print $allSubtotals . ",";
	print $subTotalsWithoutPostage . ",";
	print $subTotalProfit . ",";
	print $allDiscounts . ",";
	print $shippingTotalsFromRepeat;
	
	print "\n";
	

	// Advance by 1 week.
	$start_timestamp += 60*60*24*7; 
	$end_timestamp += 60*60*24*7; 

	sleep(1);
	flush();
	
	if($start_timestamp > time())
		break;
}





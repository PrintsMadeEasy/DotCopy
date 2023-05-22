<?

require_once("library/Boot_Session.php");



$start_timestamp = mktime (0,0,0, 10, 1, 2007);
$end_timestamp = mktime (0,0,0, 10, 7, 2007);




$dbCmd = new DbCmd();

// Make this script be able to run for a while
set_time_limit(4000);


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("MARKETING_REPORT"))
	WebUtil::PrintAdminError("Not Available");
	

$domainIDForReport = 2;

print "Date,New Customers,Copyright Charges Total,Copyright Refunds Totals (no lag),Copyright Count, Refund Count (no lag),Refund Totals (with lag), Refund Count (with lag)\n";


while(true){
	

	$dbCmd->Query("SELECT COUNT(DISTINCT UserID) FROM orders WHERE 
						DateOrdered BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
						AND FirstTimeCustomer='Y'
						AND (orders.DomainID=$domainIDForReport)");
	$newCustomerCount = $dbCmd->GetValue();
	


	

	$dbCmd->Query("SELECT SUM(CustomerAdjustment) FROM balanceadjustments INNER JOIN orders ON orders.ID = balanceadjustments.OrderID WHERE 
						DateOrdered BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
						AND (CustomerAdjustment='19.95' OR CustomerAdjustment='39.95' OR CustomerAdjustment='49.95' OR CustomerAdjustment='25.00' OR CustomerAdjustment='25')
						AND (Description LIKE '%copy%' OR Description LIKE '%cr%')
						AND (orders.DomainID=$domainIDForReport)");
	
	$totalCharges = $dbCmd->GetValue();
	
	$dbCmd->Query("SELECT COUNT(*) FROM balanceadjustments INNER JOIN orders ON orders.ID = balanceadjustments.OrderID WHERE 
						DateOrdered BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
						AND (CustomerAdjustment='19.95' OR CustomerAdjustment='39.95' OR CustomerAdjustment='49.95' OR CustomerAdjustment='25.00' OR CustomerAdjustment='25')
						AND (Description LIKE '%copy%' OR Description LIKE '%cr%')
						AND (orders.DomainID=$domainIDForReport)");
	$chargeCount = $dbCmd->GetValue();
	

	$dbCmd->Query("SELECT SUM(CustomerAdjustment) FROM balanceadjustments INNER JOIN orders ON orders.ID = balanceadjustments.OrderID WHERE 
						DateOrdered BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
						AND (CustomerAdjustment='-19.95' OR CustomerAdjustment='-39.95' OR CustomerAdjustment='-49.95' OR CustomerAdjustment='-25.00' OR CustomerAdjustment='-25')
						AND (Description LIKE '%copy%' OR Description LIKE '%cr%')
						AND (orders.DomainID=$domainIDForReport)");
	$totalRefundsNoLag = $dbCmd->GetValue();
	
	$dbCmd->Query("SELECT COUNT(*) FROM balanceadjustments INNER JOIN orders ON orders.ID = balanceadjustments.OrderID WHERE 
						DateOrdered BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
						AND (CustomerAdjustment='-19.95' OR CustomerAdjustment='-39.95' OR CustomerAdjustment='-49.95' OR CustomerAdjustment='-25.00' OR CustomerAdjustment='-25')
						AND (Description LIKE '%copy%' OR Description LIKE '%cr%')
						AND (orders.DomainID=$domainIDForReport)");
	$refundCountNoLag = $dbCmd->GetValue();

	
	$dbCmd->Query("SELECT SUM(CustomerAdjustment) FROM balanceadjustments INNER JOIN orders ON orders.ID = balanceadjustments.OrderID WHERE 
						DateCreated BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
						AND (CustomerAdjustment='-19.95' OR CustomerAdjustment='-39.95' OR CustomerAdjustment='-49.95' OR CustomerAdjustment='-25.00' OR CustomerAdjustment='-25')
						AND (Description LIKE '%copy%' OR Description LIKE '%cr%')
						AND (orders.DomainID=$domainIDForReport)");
	$totalRefundsWithLag = $dbCmd->GetValue();
	
	$dbCmd->Query("SELECT COUNT(*) FROM balanceadjustments INNER JOIN orders ON orders.ID = balanceadjustments.OrderID WHERE 
						DateCreated BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
						AND (CustomerAdjustment='-19.95' OR CustomerAdjustment='-39.95' OR CustomerAdjustment='-49.95' OR CustomerAdjustment='-25.00' OR CustomerAdjustment='-25')
						AND (Description LIKE '%copy%' OR Description LIKE '%cr%')
						AND (orders.DomainID=$domainIDForReport)");
	$refundCountWithLag = $dbCmd->GetValue();
	
	
	print date("n/j/y", $start_timestamp) . ",";
	print $newCustomerCount . ",";
	print '$' . number_format($totalCharges, 2, ".", "") . ",";
	print '-$' . number_format(abs($totalRefundsNoLag), 2, ".", "") . ",";
	print intval($chargeCount) . ",";
	print intval($refundCountNoLag) . ",";
	print '-$' . number_format(abs($totalRefundsWithLag), 2, ".", "") . ",";
	print intval($refundCountWithLag) . ",";
	
	print "\n";
	
	
	// Advance by 1 week.
	$start_timestamp += 60*60*24*7; 
	$end_timestamp += 60*60*24*7; 

	//sleep(1);
	flush();
	
	if($start_timestamp > time())
		break;
}





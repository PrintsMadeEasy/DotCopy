<?

require_once("library/Boot_Session.php");



$start_timestamp = mktime (0,0,0, 1, 5, 2003);
$end_timestamp = mktime (0,0,0, 1, 12, 2003);




$dbCmd = new DbCmd();

// Make this script be able to run for a while
set_time_limit(4000);


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("MARKETING_REPORT"))
	WebUtil::PrintAdminError("Not Available");
	



print "Date,Repeat Customers,New Customers\n";


while(true){
	
	$dbCmd->Query("SELECT COUNT(DISTINCT UserID) FROM orders INNER JOIN projectsordered ON projectsordered.OrderID = orders.ID WHERE 
						DateOrdered BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
						AND RePrintLink = 0
						AND FirstTimeCustomer='N'
						AND (orders.DomainID=1 OR orders.DomainID=1)");
	$repeatCustomerCount = $dbCmd->GetValue();

	$dbCmd->Query("SELECT COUNT(DISTINCT UserID) FROM orders INNER JOIN projectsordered ON projectsordered.OrderID = orders.ID WHERE 
						DateOrdered BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
						AND FirstTimeCustomer='Y'
						AND (orders.DomainID=1 OR orders.DomainID=1)");
	$newCustomerCount = $dbCmd->GetValue();
	
	


	$totalCustomers = $repeatCustomerCount + $newCustomerCount;
	
	print date("n/j/y", $start_timestamp) . ",";
	print $repeatCustomerCount . ",";
	print $newCustomerCount;

	
	print "\n";
	

	// Advance by 1 week.
	$start_timestamp += 60*60*24*7; 
	$end_timestamp += 60*60*24*7; 

	usleep(500000);
	flush();
	
	if($start_timestamp > time())
		break;
}





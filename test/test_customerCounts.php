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
	


$lastNewCount = 0;
$lastRepeatCount = 0;

print "Date,Repeat Customers,New Customers,Repeat Cust Tuesday Rise,New Cust Tuesday Rise Up\n";


while(true){
	
	$dbCmd->Query("SELECT COUNT(DISTINCT UserID) FROM orders INNER JOIN projectsordered ON projectsordered.ID = orders.ID WHERE 
						DateOrdered BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
						AND RePrintLink = 0
						AND FirstTimeCustomer='N'
						AND (orders.DomainID=2 OR orders.DomainID=2)");
	$repeatCustomerCount = $dbCmd->GetValue();

	$dbCmd->Query("SELECT COUNT(DISTINCT UserID) FROM orders INNER JOIN projectsordered ON projectsordered.ID = orders.ID WHERE 
						DateOrdered BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
						AND FirstTimeCustomer='Y'
						AND (orders.DomainID=2 OR orders.DomainID=2)");
	$newCustomerCount = $dbCmd->GetValue();
	
	


	$totalCustomers = $repeatCustomerCount + $newCustomerCount;
	
	
	print date("n/j/y D", $start_timestamp) . ",";
/*		
	if(date("D", $start_timestamp) == "Wed")
		print ",";
	else if(date("D", $start_timestamp) == "Thu")
		print ",";
	else if(date("D", $start_timestamp) == "Fri")
		print ",";
	else if(date("D", $start_timestamp) == "Sat")
		print ",";
	else if(date("D", $start_timestamp) == "Sun")
		print ",";
	else{
*/
		print $repeatCustomerCount . ",";
		print $newCustomerCount . ",";
//	}
	/*
	if(date("D", $start_timestamp) == "Tue"){
		if($repeatCustomerCount > $lastRepeatCount)
			print "1,";
		else
			print "0,";
			
		if($newCustomerCount > $lastNewCount)
			print "1";
		else
			print "0";
	}
*/

	
	print "\n";
	

	$lastNewCount = $newCustomerCount;
	$lastRepeatCount = $repeatCustomerCount;
		
	
	// Advance by 1 day.
	$start_timestamp += 60*60*24*1; 
	$end_timestamp += 60*60*24*1; 

	usleep(500000);
	flush();
	
	if($start_timestamp > time())
		break;
		

}





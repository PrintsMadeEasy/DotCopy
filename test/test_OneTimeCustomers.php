<?

require_once("library/Boot_Session.php");



$start_timestamp = mktime (0,0,0, 10, 1, 2004);
$end_timestamp = mktime (0,0,0, 10, 8, 2004);




$dbCmd = new DbCmd();

// Make this script be able to run for a while
set_time_limit(14000);


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("MARKETING_REPORT"))
	WebUtil::PrintAdminError("Not Available");
	

$domainObj = Domain::singleton();


print "Date,One Time New Customers,Eventual Repeat New Customers\n";

for($i=0; $i<1180; $i++){

	
	$end_timestamp_6months = $end_timestamp + (60 * 60 * 24 * 30 * 6);
	
	if($end_timestamp_6months > time()){
		break;
	}
		
	
	$firstTimeCustomer_OneTime = 0;
	$firstTimeCustomer_Repeat = 0;

	// Do a nested query to figure out how many clicks each name received 
	$dbCmd->Query("SELECT DISTINCT UserID FROM orders USE INDEX(orders_DateOrdered) INNER JOIN projectsordered ON projectsordered.OrderID = orders.ID WHERE Status != 'C' 
			AND FirstTimeCustomer='Y' AND Status != 'C' AND ProductID=73
			AND (DateOrdered BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "')
			AND orders.DomainID=1");
	$firstTimeCustomersInPeriod = $dbCmd->GetValueArr();
	
	
	foreach($firstTimeCustomersInPeriod as $thisUserID){
		$dbCmd->Query("SELECT DISTINCT OrderID FROM orders USE INDEX(orders_UserID) INNER JOIN projectsordered ON projectsordered.OrderID = orders.ID WHERE Status != 'C'
				AND UserID=$thisUserID AND orders.DomainID=1 AND DateOrdered < '" . DbCmd::FormatDBDateTime($end_timestamp_6months) . "'");
		$totalOrderIDsArr = $dbCmd->GetValueArr();
		
		if(sizeof($totalOrderIDsArr) > 1){
			$firstTimeCustomer_Repeat++;
		}
		else{
			$firstTimeCustomer_OneTime++;
		}
	}
	
	print date("n-j-Y", $start_timestamp) . "," . $firstTimeCustomer_OneTime . "," . $firstTimeCustomer_Repeat . "\n";


	
	
	// Advance by 1 week.
	$start_timestamp += 60*60*24*7; 
	$end_timestamp += 60*60*24*7; 
	
	flush();
}







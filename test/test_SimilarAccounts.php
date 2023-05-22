<?

require_once("library/Boot_Session.php");




$start_timestamp1 = mktime (0,0,0, 6, 1, 2008);
$end_timestamp1 = mktime (0,0,0, 9, 30, 2008);




$dbCmd = new DbCmd();

// Make this script be able to run for a while
set_time_limit(4000);


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("MARKETING_REPORT"))
	WebUtil::PrintAdminError("Not Available");
	

$domainObj = Domain::singleton();


print "Date,New Users,New Customers With Coupon Use,New Customers SalesRep,New Customers,Users With Saved Projects,Similar User Acounts\n";

$startMonth = 1;
$startDay = 1;
$spacingDays = 7;

for($i=1; $i<2000; $i++){
	
	if($end_timestamp > time())
		break;
	
	$start_timestamp = mktime (0,0,0, $startMonth, ($startDay + $spacingDays * $i), 2005);
	$end_timestamp = mktime (0,0,0, $startMonth, ($startDay + $spacingDays + $spacingDays * $i), 2005);
	
	print date("n/j/Y", $start_timestamp) . ",";

	// Do a nested query to figure out how many clicks each name received 
	$dbCmd->Query("SELECT ID FROM users WHERE DomainID = 1
			AND (DateCreated BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "') ");

	$userIDarr = $dbCmd->GetValueArr();
	
	print sizeof($userIDarr) . ",";
	
	$dbCmd->Query("SELECT COUNT(*) FROM orders USE INDEX(orders_DateOrdered) WHERE DomainID = 1
			AND (DateOrdered BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "') 
			AND FirstTimeCustomer='Y' AND (CouponID IS NOT NULL AND CouponID !=0)");

	$newCustomerCouponUse = $dbCmd->GetValue();
	
	print $newCustomerCouponUse . ",";	
	
	
	

	$dbCmd->Query("SELECT COUNT(*) FROM users USE INDEX(users_DateCreated) WHERE DomainID = 1
			AND (DateCreated BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "') 
			AND SalesRep != 0 AND SalesRep IS NOT NULL");
	$salesRepUsers = $dbCmd->GetValue();
	print $salesRepUsers . ",";	
	
	
	$dbCmd->Query("SELECT COUNT(*) FROM orders USE INDEX(orders_DateOrdered) WHERE DomainID = 1
			AND (DateOrdered BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "') 
			AND FirstTimeCustomer='Y'");
	$firstTimeCustomers = $dbCmd->GetValue();
	print $firstTimeCustomers . ",";	
	
	// Get the Number of Similar Accounts this persiod.
	$similarAccountsInPeriod = 0;
	$usersWithSavedProjects = 0;
	
	foreach($userIDarr as $thisUserID){

		$similarUserIDs = UserControl::GetSimilarCustomerIDsByUserID($dbCmd, $thisUserID);
		
		$dbCmd->Query("SELECT count(*) FROM projectssaved WHERE UserID=$thisUserID");
		if($dbCmd->GetValue() > 0)
			$usersWithSavedProjects++;

		if(sizeof($similarUserIDs) > 1)
			$similarAccountsInPeriod++;
	}
	
	print $usersWithSavedProjects. "," . $similarAccountsInPeriod . "\n";
	

	flush();
}







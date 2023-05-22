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


print "Date,New Users, Similar Users, Similar Ratio\n";

$startMonth = 7;
$startDay = 15;
$spacingDays = 1;

for($i=1; $i<64; $i++){
	
	$start_timestamp = mktime (0,0,0, $startMonth, ($startDay + $spacingDays * $i), 2009);
	$end_timestamp = mktime (0,0,0, $startMonth, ($startDay + $spacingDays + $spacingDays * $i), 2009);
	
	print date("n/j/Y", $start_timestamp) . ",";

	// Do a nested query to figure out how many clicks each name received 
	$dbCmd->Query("SELECT ID FROM users WHERE DomainID = 1
			AND (DateCreated BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "') ");

	$userIDarr = $dbCmd->GetValueArr();
	
	print sizeof($userIDarr) . ",";
	
	// Get the Number of Similar Accounts this persiod.
	$similarAccountsInPeriod = 0;
	
	foreach($userIDarr as $thisUserID){

		$similarUserIDs = UserControl::GetSimilarCustomerIDsByUserID($dbCmd, $thisUserID);
		

		if(sizeof($similarUserIDs) > 1)
			$similarAccountsInPeriod++;
	}
	
	print $similarAccountsInPeriod . "," . round($similarAccountsInPeriod/sizeof($userIDarr)*100, 1) . "%\n";
	

	flush();
}







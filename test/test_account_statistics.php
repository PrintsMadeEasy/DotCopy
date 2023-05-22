<?

require_once("library/Boot_Session.php");






$dbCmd = new DbCmd();

// Make this script be able to run for a while
set_time_limit(4000);


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("MARKETING_REPORT"))
	WebUtil::PrintAdminError("Not Available");
	

$domainObj = Domain::singleton();


$domainsForReport = array(1,2,6,7,8,9,10,12,13,14);


print "Date,";

foreach($domainsForReport as $thisDomainID){
	print Domain::getDomainKeyFromID($thisDomainID) . ",";
}
print "\n";

$startMonth = 1;
$startDay = 1;
$spacingDays = 7;

$domainTotalsArr = array();

for($i=1; $i<4000; $i++){
	
	
	

	$start_timestamp = mktime (0,0,0, $startMonth, ($startDay + $spacingDays * $i), 2002);
	$end_timestamp = mktime (0,0,0, $startMonth, ($startDay + $spacingDays + $spacingDays * $i), 2002);

	if($end_timestamp > time())
		break;
	
		
	print date("n/j/Y", $start_timestamp) . ",";

	foreach($domainsForReport as $thisDomainID){
		
		if(!isset($domainTotalsArr[$thisDomainID]))
			$domainTotalsArr[$thisDomainID] = 0;
		
		/*
		$dbCmd->Query("SELECT COUNT(*) FROM users WHERE DomainID = $thisDomainID
				AND (DateCreated BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "') ");
		$userAccounts = $dbCmd->GetValue();
*/
			

		$dbCmd->Query("SELECT COUNT(DISTINCT UserID) FROM orders WHERE DomainID = $thisDomainID
				AND (DateOrdered BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "') 
				AND FirstTimeCustomer = 'Y'");
		$userAccounts = $dbCmd->GetValue();
		
		
		$domainTotalsArr[$thisDomainID] += $userAccounts;
		
		print $domainTotalsArr[$thisDomainID] . ",";
	}
	print "\n";

	flush();
}







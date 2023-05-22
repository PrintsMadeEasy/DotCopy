<?

require_once("library/Boot_Session.php");



$start_timestamp = mktime (0,0,0, 1, 1, 2007);
$end_timestamp = mktime (0,0,0, 1, 2, 2007);




$dbCmd = new DbCmd();

// Make this script be able to run for a while
set_time_limit(4000);


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("MARKETING_REPORT"))
	WebUtil::PrintAdminError("Not Available");
	

$thisDomainId = 2;


for($i=0; $i<99999; $i++){

	$start_timestamp += (60 * 60 * 24);
	$end_timestamp += (60 * 60 * 24);

	// Do a nested query to figure out how many clicks each name received 
	$dbCmd->Query("SELECT COUNT(*) FROM orders WHERE FirstTimeCustomer='Y' AND (Referral ='' OR Referral IS NULL) AND DomainID=$thisDomainId
			AND (DateOrdered BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "')");

	$nomadCount = $dbCmd->GetValue();
	
	print date("n-j-Y", $start_timestamp) . "," . $nomadCount . "\n";

	if($end_timestamp > time())
		break;
	
	flush();
}







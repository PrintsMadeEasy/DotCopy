<?

require_once("library/Boot_Session.php");


$advanceByDays = 1;
$start_timestamp = mktime (0,1,1, 9, 1, 2010);
$end_timestamp = mktime (0,1,1, 9, (1+$advanceByDays), 2010);


exit("override needed");

$dbCmd = new DbCmd();

// Make this script be able to run for a while
set_time_limit(4000);


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("MARKETING_REPORT"))
	WebUtil::PrintAdminError("Not Available");
	

print "Date,New Customers,New Charges,Repeat Charges,Refunds,Missed Charges\n";




$dbCmd->Query("SELECT DISTINCT UserID FROM orders WHERE 
						DateOrdered BETWEEN '".DbCmd::FormatDBDateTime(mktime(0,0,0,9,1,2010))."' AND '".DbCmd::FormatDBDateTime(mktime(0,0,0,10,1,2010))."'
						AND FirstTimeCustomer='Y' AND DomainID=10");
$userIDarr = $dbCmd->GetValueArr();

print "Total New Customers in Period: " . sizeof($userIDarr) . "\n";

while(true){
	
	// Build a list of UserIDs that are new / repeat in this period
	$newUserIds = array();
	$repeatUserIds = array();
	
	foreach($userIDarr as $thisUser){
		
		$dbCmd->Query("SELECT COUNT(*) FROM loyaltycharges WHERE UserID=$thisUser AND Date BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'");
		if($dbCmd->GetValue() == 0)
			continue;
		
		$dbCmd->Query("SELECT COUNT(*) FROM orders WHERE UserID=$thisUser AND DateOrdered < '".DbCmd::FormatDBDateTime($start_timestamp)."'");
		$previousOrders = $dbCmd->GetValue();
		
		if($previousOrders == 0)
			$newUserIds[] = $thisUser;
		else 
			$repeatUserIds[] = $thisUser;
	}
	
	
	$totalMissedCharges = 0;
	
	if(!empty($newUserIds)){
		$dbCmd->Query("SELECT SUM(ChargeAmount) FROM loyaltycharges WHERE 
							Date BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
							AND (".DbHelper::getOrClauseFromArray("UserID", $newUserIds).")");
		$totalChargesNew = $dbCmd->GetValue();
		

	}
	else{
		$totalChargesNew = 0;
	}
	
	/*
	$dbCmd->Query("SELECT SUM(ChargeAmount) FROM loyaltymissedcharges WHERE 
						Date BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
						AND (".DbHelper::getOrClauseFromArray("UserID", $repeatUserIds).")");
	$totalMissedCharges = $dbCmd->GetValue();
	*/
	

	
	/*
	
	$dbCmd->Query("SELECT SUM(ChargeAmount) FROM loyaltymissedcharges WHERE 
						Date BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'");
	$totalMissedCharges = $dbCmd->GetValue();
*/

	if(!empty($repeatUserIds)){
		$dbCmd->Query("SELECT SUM(ChargeAmount) FROM loyaltycharges WHERE 
							Date BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
							AND (".DbHelper::getOrClauseFromArray("UserID", $repeatUserIds).")");
		$totalChargesRepeat = $dbCmd->GetValue();
	}
	else{
		$totalChargesRepeat = 0;
	}
	

	
	if(!empty($userIDarr)){
		$dbCmd->Query("SELECT SUM(RefundAmount) FROM loyaltycharges WHERE 
							Date BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
							AND (".DbHelper::getOrClauseFromArray("UserID", $userIDarr).")");
		$totalRefunds = $dbCmd->GetValue();
		
		$dbCmd->Query("SELECT SUM(ChargeAmount) FROM loyaltymissedcharges WHERE 
							Date BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
							AND (".DbHelper::getOrClauseFromArray("UserID", $userIDarr).")");
		$totalMissedCharges = $dbCmd->GetValue();
	}
	else{
		$totalRefunds = 0;
	}

	
	print date("n/j/y", $start_timestamp) . ",";

	print sizeof($newUserIds) . ",";
	print '$' . number_format($totalChargesNew, 2, '.', '') . ",";
	print '$' . number_format($totalChargesRepeat, 2, '.', '')  . ",";
	print '$' . number_format($totalRefunds, 2, '.', '')  . ",";
	print '$' . number_format($totalMissedCharges, 2, '.', '')  . ",";

	print "\n";

	
	// Advance by 1 day.
	$start_timestamp += (60*60*24*$advanceByDays); 
	$end_timestamp += (60*60*24*$advanceByDays); 

	usleep(100000);
	flush();
	
	
	if($start_timestamp > mktime(0,0,0,1,1,2011))
		break;
		

}





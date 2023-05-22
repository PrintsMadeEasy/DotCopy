<?

require_once("library/Boot_Session.php");


$advanceByDays = 1;
$start_timestamp = mktime (0,1,1, 4, 1, 2010);
$end_timestamp = mktime (0,1,1, 4, (1+$advanceByDays), 2010);




$dbCmd = new DbCmd();

// Make this script be able to run for a while
set_time_limit(4000);


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("MARKETING_REPORT"))
	WebUtil::PrintAdminError("Not Available");
	

print "Date,User Accounts Created,Eventual Live Accounts,Account Orders Within 24 Hours\n";


while(true){
	
	$dbCmd->Query("SELECT ID FROM users WHERE 
						DateCreated BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
						AND DomainID=10");
	$userIDarr = $dbCmd->GetValueArr();

	
	$newCustomerAccounts = 0;
	$sameDayPurchasesNewCustomers = 0;


	
	foreach($userIDarr as $thisUser){

		
		$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateOrdered) FROM orders WHERE UserID=" . $thisUser . " ORDER BY ID ASC LIMIT 1");
		$dateFirstOrdered = $dbCmd->GetValue();
		
		if(empty($dateFirstOrdered))
			continue;
			
		$newCustomerAccounts++;
			
		$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateCreated) FROM users WHERE ID=" . $thisUser);
		$dateCreatedForUser = $dbCmd->GetValue();
		
		$firstOrderDiff = $dateFirstOrdered - $dateCreatedForUser;
		if($firstOrderDiff < (60*60*24)){
			$sameDayPurchasesNewCustomers++;
		}

	}


	
	
	print date("n/j/y", $start_timestamp) . ",";

	print sizeof($userIDarr) . ",";
	print $newCustomerAccounts . ",";
	print $sameDayPurchasesNewCustomers . "";



	
	print "\n";

	
	// Advance by 1 day.
	$start_timestamp += (60*60*24*$advanceByDays); 
	$end_timestamp += (60*60*24*$advanceByDays); 

	usleep(100000);
	flush();
	
	
	if($start_timestamp > time())
		break;
		

}





<?

require_once("library/Boot_Session.php");


$advanceByDays = 7;
$start_timestamp = mktime (0,1,1, 1, 1, 2010);
$end_timestamp = mktime (0,1,1, 1, (1+$advanceByDays), 2010);




$dbCmd = new DbCmd();

// Make this script be able to run for a while
set_time_limit(4000);


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("MARKETING_REPORT"))
	WebUtil::PrintAdminError("Not Available");
	

print "Date,Same Day Account Orders,Order Placed within 10 Minutes,Order Placed within 20 mintues,Order Placed within 30 mintues,Order Placed within 45 mintues,Order Placed within 1 hour,Order Placed within 2 hours,Order Placed after 2 hours\n";


while(true){
	
	$dbCmd->Query("SELECT ID FROM users WHERE 
						DateCreated BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
						AND DomainID=10");
	$userIDarr = $dbCmd->GetValueArr();
/*
	// Try filtering out users that have emails which have written into customer service.
	$tempArr = array();
	foreach($userIDarr as $thisUserId){
		
		// Skip over accounts that were not registered between 8am and 3pm PST
		$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateCreated) FROM users WHERE ID=" . $thisUserId);
		$dateCreatedTimeStamp = $dbCmd->GetValue();
		
		$timeOfDay24Hours = intval(date("H", $dateCreatedTimeStamp));
		if($timeOfDay24Hours < 4 || $timeOfDay24Hours > 14)
			continue;
		
		
		$userEmail = UserControl::GetEmailByUserID($dbCmd, $thisUserId);
		
		$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateCreated) FROM csitems WHERE CustomerEmail LIKE '".DbCmd::EscapeSQL($userEmail)."' ORDER BY ID LIMIT 1");
		$firstCsEmailName =  $dbCmd->GetValue();
		
		$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateCreated) FROM csitems WHERE UserID = $thisUserId ORDER BY ID LIMIT 1");
		$firstCsEmailID =  $dbCmd->GetValue();
		
		// If there is no customer service from the user, then don't include the user.
		if(empty($firstCsEmailName) && empty($firstCsEmailID))
			continue;
		
		// Only include the user if there are no orders ... or if the first CS happens before the order was placed.
		$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateOrdered) FROM orders WHERE UserID = $thisUserId ORDER BY ID LIMIT 1");
		$dateFirstOrdered =  $dbCmd->GetValue();
		
		if(!empty($dateFirstOrdered)){
			if(!empty($firstCsEmailName) && !empty($firstCsEmailID)){
				if($firstCsEmailName > $dateFirstOrdered && $firstCsEmailID > $dateFirstOrdered)
					continue;
					
				
			}
			else if(!empty($firstCsEmailName)){
				if($firstCsEmailName > $dateFirstOrdered)
					continue;
			}
			else if(!empty($firstCsEmailID)){
				if($firstCsEmailID > $dateFirstOrdered)
					continue;
			}
		}

		
		//$dbCmd->Query("SELECT COUNT(*) FROM customermemos WHERE CustomerUserID = $thisUserId");
		//$memorUserCount =  $dbCmd->GetValue();
		
		// Make sure that no customer service exists.
		//if($csCountFromEmail > 0 || $csUserIDcount > 0 || $memorUserCount > 0)
		//	continue;
		
		// Make sure that at least some customer service or memo.
		//if($csCountFromEmail == 0 && $csUserIDcount == 0 && $memorUserCount == 0)
		//	continue;
		//if($memorUserCount == 0)
		//	continue;

		

		$tempArr[] = $thisUserId;
	}
	
	$userIDarr = $tempArr;
			*/	
	
	$newCustomerAccounts = 0;
	$within10minutes = 0;
	$within20minutes = 0;
	$within30minutes = 0;
	$within45minutes = 0;
	$within1hour = 0;
	$within2hours = 0;
	$within4hours = 0;
	$within8hours = 0;
	$within2days = 0;
	$within7days = 0;
	$greaterThan1week = 0;


	
	foreach($userIDarr as $thisUser){

		
		$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateOrdered) FROM orders WHERE UserID=" . $thisUser . " ORDER BY ID ASC LIMIT 1");
		$dateFirstOrdered = $dbCmd->GetValue();
		
		if(empty($dateFirstOrdered))
			continue;
			
		$newCustomerAccounts++;
			
		$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateCreated) FROM users WHERE ID=" . $thisUser);
		$dateCreatedForUser = $dbCmd->GetValue();
		
		$firstOrderDiff = $dateFirstOrdered - $dateCreatedForUser;
		if($firstOrderDiff < (60*10)){
			$within10minutes++;
		}
		else if($firstOrderDiff < (60*20)){
			$within20minutes++;
		}
		else if($firstOrderDiff < (60*30)){
			$within30minutes++;
		}
		else if($firstOrderDiff < (60*45)){
			$within45minutes++;
		}
		else if($firstOrderDiff < (60*60)){
			$within1hour++;
		}
		else if($firstOrderDiff < (60*60*2)){
			$within2hours++;
		}
		else if($firstOrderDiff < (60*60*4)){
			$within4hours++;
		}
		else if($firstOrderDiff < (60*60*8)){
			$within8hours++;
		}
		else if($firstOrderDiff < (60*60*24*2)){
			$within2days++;
		}
		else if($firstOrderDiff < (60*60*24*7)){
			$within7days++;
		}
		else{
			$greaterThan1week++;
		}
	}


	
	
	print date("n/j/y", $start_timestamp) . ",";

	print $newCustomerAccounts . ",";
	print $within10minutes . ",";
	print $within20minutes . ",";
	print $within30minutes . ",";
	print $within45minutes . ",";
	print $within1hour . ",";
	print $within2hours . ",";
	print $within4hours . ",";
	print $within8hours . ",";
	print $within2days . ",";
	print $within7days . ",";
	print $greaterThan1week;


	
	print "\n";

	
	// Advance by 1 day.
	$start_timestamp += (60*60*24*$advanceByDays); 
	$end_timestamp += (60*60*24*$advanceByDays); 

	usleep(100000);
	flush();
	
	
	if($start_timestamp > time())
		break;
		

}





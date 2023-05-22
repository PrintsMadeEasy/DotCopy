<?

require_once("library/Boot_Session.php");


$advanceByDays = 7;





$dbCmd = new DbCmd();

// Make this script be able to run for a while
set_time_limit(99000);


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("MARKETING_REPORT"))
	WebUtil::PrintAdminError("Not Available");
	

//$moocherArr = array();
	


print "Date,Dead Accounts,Immediate Save,Saved Within 10 Minutes,Saved By End of Day,Saved at Least 1 Day later\n";

$montCounter = 1;

while(true){
	
	$start_timestamp = mktime (0,1,1, $montCounter, 1, 2003);
	$end_timestamp = mktime (0,1,1, ($montCounter+1), 1, 2003);
	
	$montCounter++;
	
	$dbCmd->Query("SELECT ID FROM users WHERE 
						DateCreated BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
						AND DomainID=1");
	$userIDarr = $dbCmd->GetValueArr();

	// Try filtering out users that have emails which have written into customer service.
	$tempArr = array();
	foreach($userIDarr as $thisUserId){
		/*
		// The user must have at least 1 saved project.
		$dbCmd->Query("SELECT COUNT(*) FROM orders WHERE UserID=" . $thisUserId);
		$ordersCount = $dbCmd->GetValue();
		
		if($ordersCount > 0)
			continue;
*/
		/*

		// To to issolate to just postcards.		
		$dbCmd->Query("SELECT COUNT(*) FROM projectssaved WHERE UserID=" . $thisUserId . " AND
			(ProductID=73 OR ProductID=79 OR ProductID=89 OR ProductID=86 OR ProductID=90 OR ProductID=87 OR ProductID=82 OR ProductID=80 OR ProductID=84 OR ProductID=81  OR ProductID=83  OR ProductID=85 OR ProductID=88 OR ProductID=91 )");
		$savedProjectCount = $dbCmd->GetValue();
		
		if($savedProjectCount > 1)
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

			*/		

		$tempArr[] = $thisUserId;
	}
	
	$userIDarr = $tempArr;
	
	
	$usersWithSavedProjects = 0;
	
	$deadAccounts = 0;
	$deadAccountMoocherCountInPeriod = 0;
	
	$immediateSaveDate = 0;
	$savedIn10minutes = 0;
	$savedAfter10mintuesBeforeEndOfDay = 0;
	$savedAfterTheNextDay = 0;

	
	foreach($userIDarr as $thisUser){

		$dbCmd->Query("SELECT COUNT(*) FROM orders WHERE UserID=" . $thisUser);
		if($dbCmd->GetValue() == 0){
			$deadAccounts++;
		}
		/*
		$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateLastModified) FROM projectssaved_trash USE INDEX(projectssaved_UserID) WHERE UserID='$thisUser' ORDER BY ID DESC LIMIT 1");
		$lastModifiedDate = $dbCmd->GetValue();
		
		$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateCreated) FROM users WHERE ID=" . $thisUser);
		$dateUserCreated = $dbCmd->GetValue();
		
		$dateDiff = $lastModifiedDate - $dateUserCreated;
		
		
		$endOfDayTimeStamp = mktime(0,0,0,date("n", $dateUserCreated),(date("j", $dateUserCreated) + 1), date("Y", $dateUserCreated));
		
		if($dateDiff < 10){
			$immediateSaveDate++;
		}
		else if($dateDiff < (60 * 10)){
			$savedIn10minutes++;
		}
		else if($lastModifiedDate < $endOfDayTimeStamp){
			$savedAfter10mintuesBeforeEndOfDay++;
		}
		else{
			$savedAfterTheNextDay++;
		}
*/

	}
	
	
	/*
	// Get a list of all users who have saved projects in the period.... even if they are not dead.
	$dbCmd->Query("SELECT UserID FROM projectssaved_trash USE INDEX(projectssaved_trash_DateLastModified) WHERE DateLastModified 
					BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."' AND DomainID=1");
	$usersWithSavedProjectModifications = $dbCmd->GetValueArr();
	
	// A 2-d array... the Key is the UserID... the value is the number of Saved Projects.
	$userIdSavedCounts = array_count_values($usersWithSavedProjectModifications);
	
	// A unique list of users who have modified saved projects within the report period... even if they are not dead.
	$uniqueUsersWithSavedProjectsMod = array_unique(array_keys($userIdSavedCounts));
	
	$deadSavedProjectUsers = array();
	foreach($uniqueUsersWithSavedProjectsMod as $savedProjectUser){

		// Skip users that aren't dead.
		$dbCmd->Query("SELECT COUNT(*) FROM orders WHERE UserID=" . $savedProjectUser);
		$ordersCount = $dbCmd->GetValue();
		if($ordersCount > 0)
			continue;
			
		$deadSavedProjectUsers[] = $savedProjectUser;
		
		$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateCreated) FROM users WHERE ID=" . $savedProjectUser);
		$dateUserCreated = $dbCmd->GetValue();
		
		
		$dbCmd->Query("SELECT COUNT(*) FROM projectssaved_trash USE INDEX(projectssaved_UserID) WHERE UserID=$savedProjectUser AND DateLastModified > '".DbCmd::FormatDBDateTime($dateUserCreated + (60*60*24*25))."'");
		$moochDetect = $dbCmd->GetValue();
		
		if($moochDetect > 0){
		
			$deadAccountMoocherCountInPeriod++;
			$moocherArr[] = $savedProjectUser;
		}
			
	}
	
	// Total up all of the project counts
	$totalProjectsModified = 0;
	
	foreach($deadSavedProjectUsers as $thisDeadUser){
		$totalProjectsModified += $userIdSavedCounts[$thisDeadUser];
	}

*/
	

	
	print date("n/j/y", $start_timestamp) . ",";

	print sizeof($userIDarr) . ",";
	print $deadAccounts;
	
	/*
	print $immediateSaveDate . ",";
	print $savedIn10minutes . ",";
	print $savedAfter10mintuesBeforeEndOfDay . ",";
	print $savedAfterTheNextDay . ",";
*/
	//print sizeof($deadSavedProjectUsers) . ",";
	//print $totalProjectsModified . ",";
	//print $deadAccountMoocherCountInPeriod;


	
	print "\n";

	
	// Advance by 1 day.

	usleep(100000);
	flush();
	
	//if($start_timestamp > mktime(0,0,0,8,16,2004))
	//	break;
	
	if($start_timestamp > time())
		break;
	

}
/*
print "\n\n\n\n\n------------------------\n\n\n";
print "Date,User Created,Last Saved Project Activity,Max Days Between Activity,Saved Project Total\n";
$moocherArr = array_unique($moocherArr);

sort($moocherArr);

foreach($moocherArr as $thisMoochy){
	
	print $thisMoochy . ",";
	
	$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateCreated) FROM users WHERE ID=" . $thisMoochy);
	$dateUserCreated = $dbCmd->GetValue();
	
	print date("n/j/y", $dateUserCreated) . ",";
	
	$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateLastModified) FROM projectssaved_trash USE INDEX(projectssaved_UserID) WHERE UserID='$thisMoochy' ORDER BY ID DESC LIMIT 1");
	$lastModifiedDate = $dbCmd->GetValue();
	
	print date("n/j/y", $lastModifiedDate) . ",";
	
	$maxDaysBetweenActivity = ceil(($lastModifiedDate - $dateUserCreated) / (60*60*24));
	
	print $maxDaysBetweenActivity . ",";
	
	$dbCmd->Query("SELECT COUNT(*) FROM projectssaved_trash USE INDEX(projectssaved_UserID) WHERE UserID='$thisMoochy'");
	print $dbCmd->GetValue() . "\n";
	
	flush();
}
*/






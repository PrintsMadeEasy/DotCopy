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

$visitorPathObj = new VisitorPath();

//print "Date,Unique IPs,IPs That Double Click,Number Of Duplicate Clicks,Average Duplicate Clicks a Person,Orders From DoubleClickers,Conversion Rate of DoubleClickers\n";

$startMonth = 8;
$startDay = 1;
$spacingDays = 1;
//$googleTrackingCode = " (Name LIKE 'g-%' OR Name LIKE 'y-%' OR Name LIKE 'msn%' OR Name LIKE 'over%') ";
$googleTrackingCode = " (Name LIKE 'g-%bc_%') ";
$startYear = 2009;
$amountOfTimeSlices = 900;

for($i=1; $i<20; $i++){
	
	$start_timestamp = mktime (0,0,0, $startMonth, ($startDay + $spacingDays * $i), $startYear);
	$end_timestamp = mktime (0,0,0, $startMonth, ($startDay + $spacingDays + $spacingDays * $i), $startYear);
	
	print "<br><b>" . date("n/j/Y", $start_timestamp) . "</b><br>";

	// Do a nested query to figure out how many clicks each name received 
	$dbCmd->Query("SELECT DISTINCT IPaddress FROM bannerlog USE INDEX (bannerlog_Date)
					WHERE DomainID = 1 AND  ".$googleTrackingCode." 
					AND (Date BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "') 
					AND UserAgent NOT LIKE '%Google%' AND UserAgent NOT LIKE '%Slurp%' AND UserAgent NOT LIKE '%AOL%'
					ORDER BY ID ASC ");
	$ipAddressArr = $dbCmd->GetValueArr();
	
	//print sizeof($ipAddressArr) . ",";
	
	$ipAddressWithMultiples = array();
	$totalDuplicateClicks = 0;
	$doubleIPwithFunWebHistory = 0;
	
	// Locate ones that have clicked on the banner more than once.
	foreach($ipAddressArr as $thisIPaddress){
		$dbCmd->Query("SELECT COUNT(*) FROM bannerlog USE INDEX (bannerlog_Date) WHERE  DomainID = 1 AND IPaddress='".$thisIPaddress."'
						AND (Date BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp - (60*60*24*1)) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp + (60*60*24*1)) . "') ");
		$ipClickCount = $dbCmd->GetValue();
		
		if($ipClickCount > 2){
		
			$ipAddressWithMultiples[$thisIPaddress] = $ipClickCount;
			$totalDuplicateClicks += ($ipClickCount - 1);
			
		
			print $thisIPaddress . "<br>";
			$dbCmd->Query("SELECT UserAgent FROM bannerlog USE INDEX (bannerlog_Date) WHERE  DomainID = 1 AND ".$googleTrackingCode." AND IPaddress='".$thisIPaddress."'
							AND (Date BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "') ");
			$userAgentsArr = $dbCmd->GetValueArr();
			
			foreach($userAgentsArr as $thisUA){
				print $thisUA . "<br>";
			}
		
			
			$dbCmd->Query("SELECT COUNT(*) FROM bannerlog USE INDEX (bannerlog_Date) WHERE  DomainID = 1 AND UserAgent LIKE '%Fun%' AND IPaddress='".$thisIPaddress."'
							AND (Date BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp - (60*60*24*7)) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp + (60*60*24*7)) . "') ");
			if($dbCmd->GetValue() > 0)
				$doubleIPwithFunWebHistory++;
		}
	}
	
	$numberOfPeopleMultiClicking = sizeof($ipAddressWithMultiples);
/*	
	print $numberOfPeopleMultiClicking . ",";
	print $totalDuplicateClicks . ",";


	if($numberOfPeopleMultiClicking > 0){
		$averageDuplicateClicks = round($totalDuplicateClicks / $numberOfPeopleMultiClicking, 2);
		print $averageDuplicateClicks;
	}
	else {
		print "0";
	}
	print ",";
	*/
	

	
	// Figure out the Conversion Rate of people that have clicked multiple times.
	$ordersFromMultipleIPclicks = 0;
	foreach(array_keys($ipAddressWithMultiples) as $thisIPaddressMultiplied){
		$dbCmd->Query("SELECT ID FROM orders  USE INDEX (orders_DateOrdered) 
						WHERE  DomainID = 1 
						AND IPaddress='".$thisIPaddressMultiplied."'
						AND (DateOrdered BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp - 60*60*24*3) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp + 60*60*24*2) . "') ");
		
	//	print "<br>SELECT ID FROM orders  USE INDEX (orders_DateOrdered) WHERE  DomainID = 1 AND IPaddress='".$thisIPaddressMultiplied."' AND (DateOrdered BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp - 60*60*24*3) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp + 60*60*24*2) . "'); <br>";
		$orderIDarr = $dbCmd->GetValueArr();
		
		foreach($orderIDarr as $thisOrderID){
			print "<a href='ad_order.php?orderno=$thisOrderID' target='orders'>" . $thisOrderID . "</a><br>";
		}
		
		$conversionsCount = sizeof($orderIDarr);
		
		$ordersFromMultipleIPclicks += $conversionsCount;
		
	}

	
print "<br>Total Multi-click IPs: $numberOfPeopleMultiClicking<br>";
print "<br>Total Multi-click IPs with FunWebHistory: $doubleIPwithFunWebHistory<br>";
	/*
	print $ordersFromMultipleIPclicks . ",";
	
	if($numberOfPeopleMultiClicking > 0){
		$averageConversionRate = round($ordersFromMultipleIPclicks / $numberOfPeopleMultiClicking * 100, 2);
		print $averageConversionRate;
	}
	else {
		print "0";
	}
	print "%";
*/
	print "\n";
	/*
	// Find out the the First Session ID in the day matching the IP Address.
	foreach(array_keys($ipAddressWithMultiples) as $thisIPaddressMultiplied){
		// Now Find out the earliest Sessions in the Daisy chain.
		$dbCmd->Query("SELECT SessionID FROM visitorsessiondetails USE INDEX (visitorsessiondetails_DateStarted) 
						WHERE  DomainID = 1 AND IPaddress = '".$thisIPaddressMultiplied."' 
						AND (DateStarted BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "') 
						ORDER BY ID ASC LIMIT 1");
		$matchingSessionID = $dbCmd->GetValue();
		
	//	print $thisIPaddressMultiplied . "\n---------------------\n";
		
		if(!$matchingSessionID){
			print "No Matching Session\n";
		}
	//	print $matchingSessionID . "\n";
		
		$previousSessionIDs = $visitorPathObj->getPreviousSessionIDsWithDates($matchingSessionID);

		foreach($previousSessionIDs as $thisSessionTimeStamp => $thisDateOfSession){
			
			print date("m j, Y h:i", $thisDateOfSession) . "    =    ";
		}
		
		if(!empty($previousSessionIDs))
			print "\n";
		

	}
*/
	

	
	
	
	
	
	

	flush();
}







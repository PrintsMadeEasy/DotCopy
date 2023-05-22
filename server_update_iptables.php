<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();


set_time_limit(36000);
ini_set("memory_limit", "512M");

$ipTablesFile = file_get_contents("./ip_template.txt");


// Begin looking for Bad IP's created in the last day.
$beginSearchTimeStamp = time() - 60 * 60 * 24;


$ipAddressArr = array();
$outputMatches = "";

/* Disable BlackListing for now 

// Build a list of negative matches.
$dbCmd->Query("SELECT IPaddress FROM dosattack WHERE DateCreated > '".DbCmd::EscapeSQL(DbCmd::FormatDBDateTime($beginSearchTimeStamp))."'");
$ipAddressArr = $dbCmd->GetValueArr();

// It is probably safer to filter duplicates in PHP.  SQL can have odd behavior dealing with DISTINCT limiters on an Indexed column.
$ipAddressArr = array_unique($ipAddressArr);

$outputMatches = "";
foreach ($ipAddressArr as $thisIpAddress){

	print ".";
	flush();
	
	// Dont' Blacklist CSR's (or anyone with IP Access to use the backend).
	$dbCmd->Query("SELECT COUNT(*) FROM ipaccess WHERE IPaddress='".DbCmd::EscapeLikeQuery($thisIpAddress)."'");
	if($dbCmd->GetValue() > 0){
		print "Skipping IP $thisIpAddress <br>\n";
		continue;
	}
	
	//  --- To be more Aggressive, You can Drop any Out-of-Country addresses
	//$maxMindObj = new MaxMind();
	//if($maxMindObj->loadIPaddressForLocationDetails($_SERVER['REMOTE_ADDR']) && $maxMindObj->getCountry() != "US"){
	//	$outputMatches .= "iptables -A INPUT -p all -s $thisIpAddress -j DROP \n";
	//	continue;
	//}
	
	
	$dbCmd->Query("SELECT AccessCount FROM dosattack WHERE IPaddress='".$thisIpAddress."' AND  DateCreated > '".DbCmd::EscapeSQL(DbCmd::FormatDBDateTime($beginSearchTimeStamp))."'");
	if($dbCmd->GetValue() > 2){
		
		// We don't want to block any legitimate customers.
		// Let's get all of the SessionID's associated with this IP Address.		
		$visitorQueryObj = new VisitorPathQuery();
		$visitorQueryObj->limitIpAddresses(array($thisIpAddress));
		
		// The IP Address may have multiple Sessions associated with them.
		$totalSessionIDsFromIp = $visitorQueryObj->getSessionIDs();
		
		// Some legitimate customers may have a session which has no duration.
		// If any of their sessions have a duration above Zero, then it is unlikely to be a Bot.
		// So we loop through all Sessions and set this flag appropriately.
		$ipHasSessionWithDurationAboveZero = false;
		$ipHasPageViews = false;
		
		$sessionDetailObj = new VisitorPath();
		
		foreach($totalSessionIDsFromIp as $thisSessionID){
			
			if($sessionDetailObj->getSessionDuration($thisSessionID) > 0){
				$ipHasSessionWithDurationAboveZero = true;
				break;
			}
			
			$visitLablesArr = $sessionDetailObj->getVisitLabels($thisSessionID);
			$visitLablesArr = array_unique($visitLablesArr);
			
			// If there are more than 1 Page Label names, then it is unikely to be a bot.
			if(sizeof($visitLablesArr) > 1){
				$ipHasPageViews = true;
			}
			
			// If there is only 1 Page Label name, make sure that the One-&-Only is not named "Error Screen" in case they are probing a bad URL.
			if(sizeof($visitLablesArr) == 1){
				if(current($visitLablesArr) != "Error Screen")
					$ipHasPageViews = true;
			}
		}

		// If any of the Sessions have a Duration, then no sense in looking for others.  
		// Skip to the next IP which we may ban.
		if(!$ipHasSessionWithDurationAboveZero && !$ipHasPageViews){
			
			// If we have gotten this far, then it must be an IP Address which is not behaving like a web-surfer-dude.
			// Add to the "BlackList Buffer".
			$outputMatches .= "iptables -A INPUT -p all -s $thisIpAddress -j DROP \n";
			
			//print "Blacklisting IP: $thisIpAddress <br>\n";
		}
		else{
			if($ipHasSessionWithDurationAboveZero){
				print "Skipping IP Zero<br>\n";
			}
			if($ipHasPageViews){
				print "Skipping IP Names<br>\n";
			}
		}
	}
	
	
	print ".            ";
	flush();
}

*/

$negativeMatchBuffer = "";

/*
// Add Percentages.  I just hacked it.  
// This should be put into WebUtil.  Pass in a chunk of text and have it percentify it.
$outputLinesArr = split("\n", $outputMatches);

$negativeMatchBuffer .= "echo Total of " . sizeof($outputLinesArr) . " Negative Matches\n";

$percentageBreaks = array("Ten"=>10, "Twenty"=>20, "Thirty"=>30, "Forty"=>40, "Fifty"=>50, "Sixty"=>60, "Seventy"=>70, "Eighty"=>80, "Ninety"=>90);
$percentageSetArr = array();


$blackListCounter = 0;
foreach($outputLinesArr as $thisBlackListLine){

	$blackListCounter++;


	
	foreach($percentageBreaks as $thisBreakDes => $thisBreakVal){
	
		$percentageInt = intval($blackListCounter /  sizeof($outputLinesArr) * 100);	

		if($percentageInt > $percentageBreaks[$thisBreakDes] && (intval($percentageInt - $percentageBreaks[$thisBreakDes]) < 2) ){
			
			if(!isset($percentageSetArr[$thisBreakDes])){
				
				$negativeMatchBuffer .= "echo " . $thisBreakDes . " %\n";
				
				// Now the key does exist... so the echo won't have an echo.
				$percentageSetArr[$thisBreakDes] = true;
			}

			
			// Let us know that we have already output that slot.
			$percentageBreaks[$percentageBreaks] = 0;
		
		}
	
	}
	

	// Glue the Lines back together after splitting the array.
	$negativeMatchBuffer .= $thisBlackListLine . "\n";
}
*/

$ipTablesFile = preg_replace("/\{NEGATIVE_MATCHES\}/", $negativeMatchBuffer, $ipTablesFile);









$positiveMatches = "";

$counter = 0;

$ipCollection = array();


$dbCmd->Query("SELECT ID FROM users WHERE DomainID=1 AND Rating > 7 AND DateLastUsed > '2012-3-1 14:57:30'");
$userIdsWithRating = $dbCmd->GetValueArr();
foreach($userIdsWithRating as $thisUserID){
	$dbCmd->Query("SELECT DISTINCT IPaddress FROM orders WHERE UserID=$thisUserID ORDER BY IPaddress ASC");
	$ipsFromUser = $dbCmd->GetValueArr();
	
	foreach($ipsFromUser as $thisIPadd){
		$ipCollection[] = $thisIPadd;
	}
}

$dbCmd->Query("SELECT ID FROM users WHERE DomainID=1 AND Rating > 4 AND DateLastUsed > '2012-3-25 14:57:30'");
$userIdsWithRating = $dbCmd->GetValueArr();
foreach($userIdsWithRating as $thisUserID){
	$dbCmd->Query("SELECT DISTINCT IPaddress FROM orders WHERE UserID=$thisUserID ORDER BY IPaddress ASC");
	$ipsFromUser = $dbCmd->GetValueArr();
	
	foreach($ipsFromUser as $thisIPadd){
		$ipCollection[] = $thisIPadd;
	}
}



$dbCmd->Query("SELECT ID FROM users WHERE DomainID=1 AND DateLastUsed > '2012-3-30 14:57:30'");
$userIdsWithRating = $dbCmd->GetValueArr();

foreach($userIdsWithRating as $thisUserID){
	$dbCmd->Query("SELECT DISTINCT IPaddress FROM orders WHERE UserID=$thisUserID ORDER BY IPaddress ASC");
	$ipsFromUser = $dbCmd->GetValueArr();
	
	foreach($ipsFromUser as $thisIPadd){
		$ipCollection[] = $thisIPadd;
	}
}



$dbCmd->Query("SELECT ID,IPaddress, UNIX_TIMESTAMP(DateStarted) AS DateStarted, UNIX_TIMESTAMP(DateLastAccess) AS DateLastAccess FROM visitorsessiondetails WHERE DomainID=1 AND DateStarted > '2012-3-25 14:57:30'");
while($row = $dbCmd->GetRow()){
	$sessionIP = $row["IPaddress"];
	$dateStarted = $row["DateStarted"];
	$dateLastAccess = $row["DateLastAccess"];
	
	if($dateLastAccess - $dateStarted > 10){
		$ipCollection[] = $sessionIP;
	}
}





$ipCollection = array_unique($ipCollection);



$dbCmd->Query("SELECT DISTINCT IPaddress FROM orders WHERE DomainID=1 AND DateOrdered > '2012-3-30 14:57:30' ORDER BY IPaddress ASC");
while($thisIpAddress = $dbCmd->GetValue()){
	$positiveMatches .= "iptables -A INPUT -p tcp -s $thisIpAddress -j ACCEPT \n";
	//print "iptables -A INPUT -p tcp -s $thisIpAddress -j ACCEPT \n";
	flush();
	$counter++;
	
	if($counter > 1000){
		print ".                         ";
		flush();
	}
}


foreach($ipCollection as $thisIPadd){
	if(empty($thisIPadd))
		continue;
		
	$positiveMatches .= "iptables -A INPUT -p tcp -s $thisIPadd -j ACCEPT \n";
}






// Add Percentages.  I just hacked it.  
// This should be put into WebUtil.  Pass in a chunk of text and have it percentify it.
$outputLinesArr = split("\n", $positiveMatches);

$positiveMatchBuffer .= "echo Total of " . sizeof($outputLinesArr) . " White List Matches\n";

$percentageBreaks = array("Ten"=>10, "Twenty"=>20, "Thirty"=>30, "Forty"=>40, "Fifty"=>50, "Sixty"=>60, "Seventy"=>70, "Eighty"=>80, "Ninety"=>90);
$percentageSetArr = array();


$whiteListCounter = 0;
foreach($outputLinesArr as $thisWhiteListLine){

	$whiteListCounter++;


	
	foreach($percentageBreaks as $thisBreakDes => $thisBreakVal){
	
		$percentageInt = intval($whiteListCounter /  sizeof($outputLinesArr) * 100);	

		if($percentageInt > $percentageBreaks[$thisBreakDes] && (intval($percentageInt - $percentageBreaks[$thisBreakDes]) < 2) ){
			
			if(!isset($percentageSetArr[$thisBreakDes])){
				
				$positiveMatchBuffer .= "echo " . $thisBreakDes . " %\n";
				
				// Now the key does exist... so the echo won't have an echo.
				$percentageSetArr[$thisBreakDes] = true;
			}

			
			// Let us know that we have already output that slot.
			$percentageBreaks[$percentageBreaks] = 0;
		
		}
	
	}
	

	// Glue the Lines back together after splitting the array.
	$positiveMatchBuffer .= $thisWhiteListLine . "\n";
}




$ipTablesFile = preg_replace("/\{POSITIVE_MATCHES\}/", $positiveMatchBuffer, $ipTablesFile);
print $ipTablesFile;


file_put_contents("ip_tables_output.txt", $ipTablesFile);



print "<hr><hr>Done.";
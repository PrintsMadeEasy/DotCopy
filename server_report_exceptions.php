<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();


print "Checking for Exceptions:<br><br>\n\n";




// In case someone is doing a Brute Force attack on the server, we don't want to to email every exception or it could flood our inbox.
// We are looking for Exceptions that have happened in the last hour, so make sure to call this script by the cron every hour to get reports on all exceptions.
$dbCmd->Query("SELECT DISTINCT EventSignature FROM exceptionlog WHERE Date > DATE_ADD(NOW(), INTERVAL -1 HOUR)");
$eventSignaturesArr = $dbCmd->GetValueArr();

$reportTxt = "";

$allExceptionsCount = 0;
foreach($eventSignaturesArr as $thisEventSignature){
	
	$dbCmd->Query("SELECT COUNT(*) FROM exceptionlog WHERE Date > DATE_ADD(NOW(), INTERVAL -1 HOUR) AND EventSignature='$thisEventSignature'");
	$totalExceptions = $dbCmd->GetValue();
	
	$allExceptionsCount += $totalExceptions;
	
	$dbCmd->Query("SELECT DISTINCT(IPaddress) FROM exceptionlog WHERE Date > DATE_ADD(NOW(), INTERVAL -1 HOUR) AND EventSignature='$thisEventSignature'");
	$allIPaddressesArr = $dbCmd->GetValueArr();
	$ipAddressCount = sizeof($allIPaddressesArr);
	
	
	$dbCmd->Query("SELECT URL FROM exceptionlog WHERE Date > DATE_ADD(NOW(), INTERVAL -1 HOUR) AND EventSignature='$thisEventSignature' LIMIT 1");
	$exceptionURL = $dbCmd->GetValue();
	
	
	// Skip over Alerts that are very common (and we are sure are safe). 
	// A lot of people are trying to mess around with thumbnail images.
	if(preg_match("/".preg_quote("thumbnail_download.php")."/", $exceptionURL))
		continue;
		
	// People have been trying to abuse this URL for a while.  They are hopeing to find a weakness that does a "dynamic" PHP include statement with an fopen across a URL.
	if(preg_match("/".preg_quote("templates.php?categorytemplate=http")."/", $exceptionURL))
		continue;
	
	// Get the most recent exception signature within the period.  That will give us the best chance of searching through Log files, and also tracking down Projects which are still active in a session.
	$dbCmd->Query("SELECT EventObjTrace, IPaddress, UNIX_TIMESTAMP(Date) AS Date, UserID FROM exceptionlog WHERE Date > DATE_ADD(NOW(), INTERVAL -1 HOUR) AND EventSignature='$thisEventSignature' ORDER BY ID DESC LIMIT 1");
	$row = $dbCmd->GetRow();
	$exceptionDump = $row["EventObjTrace"];
	$ipAddress = $row["IPaddress"];
	$dateStamp = $row["Date"];
	$userIDofException = $row["UserID"];
	
	if(empty($userIDofException))
		$userDescriptionOfException = "Not Logged In";
	else
		$userDescriptionOfException = "ID: " . $userIDofException . " Name: " . UserControl::GetNameByUserID($dbCmd, $userIDofException);
	
	// Format the Date to match our Apache Server Logs
	$dateStampFormated = date("d/M/Y:H:i:s", $dateStamp);
	
	$reportTxt = "Exception Signature: $thisEventSignature happened $totalExceptions time" . LanguageBase::GetPluralSuffix($totalExceptions, "", "s") . " within the past hour from $ipAddressCount ". LanguageBase::GetPluralSuffix($ipAddressCount, "IP Address", "different IP Addresses"). ".\n\n";
	$reportTxt .= "URL: $exceptionURL \n\nFirst IP Address: $ipAddress First Timestamp (format matches Apache Logs): $dateStampFormated User: $userDescriptionOfException \n\nFirst Exception Dump:\n" . $exceptionDump . "\n\n\n-------------------------------------------------\n\n\n";

	WebUtil::SendEmail(Constants::GetMasterServerEmailName(), Constants::GetMasterServerEmailAddress(), "Webmaster", Constants::GetAdminEmail(), 
					("ExceptionReport: $totalExceptions time" . LanguageBase::GetPluralSuffix($totalExceptions, "", "s") . " URL: " . $exceptionURL), 
					$reportTxt);
}




print "\n\n\n<br>Finished checking for exceptions.";

?>
<?

require_once("library/Boot_Session.php");

set_time_limit(28800);

$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


$dbCmd->Query("SELECT EmailAddress, DomainID FROM emailunsubscribe WHERE AffilliatUnsubscibeStatus='W'");
while($row = $dbCmd->GetRow()){
	
	$thisEmailContact = $row["EmailAddress"];
	$thisDomainID = $row["DomainID"];
	
	if($thisDomainID == Domain::getDomainID("PrintsMadeEasy.com"))
		$unsubscribeURL = "http://dnelist.com/u/8d0cb0057282c9bbcf8f41f7c66092282";
	else if($thisDomainID == Domain::getDomainID("Postcards.com"))
		$unsubscribeURL = "http://dnelist.com/u/8d0cb0057282c9bbcf8f41f7c66092283";
	else 
		throw new Exception("The Domain ID has not been defined for affiliate email unsubscriptions.");
	
	$nameValuePairs = "email=" . urlencode($thisEmailContact);
	
	$return_value = null;
	
	// -m 60 lets us have up to 1 mintues to get a response back from the server.
	// -k means the server doesn't need a valid certificate to connect in SSL mode.
	// -d means that we are posting data.
	exec(Constants::GetCurlCommand() . " -m 60 -k -d \"$nameValuePairs\" $unsubscribeURL", $return_value);

	if(!is_array($return_value) || !isset($return_value[0])){
		print "An error occured posting an email address (".Domain::getDomainKeyFromID($thisDomainID).").\n";
		continue;
	}
	
	$returnString = implode("\n", $return_value);
	
	if(!preg_match("/has been received/i", $returnString)){
		print "An error occured with the server response while posting an email address (".Domain::getDomainKeyFromID($thisDomainID).").\n";
		continue;
	}
	
	print "OK: " . Domain::getDomainKeyFromID($thisDomainID) . ": " . $thisEmailContact . "\n<br>\n";
	
	
	$dbCmd2->UpdateQuery("emailunsubscribe", array("AffilliatUnsubscibeStatus"=>"G"), ("EmailAddress LIKE '".DbCmd::EscapeLikeQuery($thisEmailContact)."' AND DomainID=$thisDomainID") );
	
	
	flush();
	
	usleep(300000);
	
}

$dbCmd->Query("SELECT COUNT(*) FROM emailunsubscribe WHERE AffilliatUnsubscibeStatus='W'");
$stillUnSubscribedCount = $dbCmd->GetValue();
if($stillUnSubscribedCount > 20){
	WebUtil::WebmasterError("Problem with Affilliate Email Unsubscriptions. There are currently $stillUnSubscribedCount emails in 'W'aiting status after running this script.");
}



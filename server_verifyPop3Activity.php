<?php

// Run with Cron, about all 10 Minutes

require_once("library/Boot_Session.php");

$secondsAllowed = 1920; // max. 32 Minutes

$dbCmd = new DbCmd();

$breakDate = DbCmd::FormatDBDateTime(time() - $secondsAllowed); 
$dbCmd->Query("SELECT DomainID FROM pop3getemails WHERE LastConnection < '$breakDate'");
	
$problemDomainIdArr = $dbCmd->GetValueArr();
	
if(empty($problemDomainIdArr)){
	print "no problems found";
	exit;
}

$text = "server_verifyPop3Activity.php found a problem with CS Pop3 get_emails: ";	
	
foreach($problemDomainIdArr AS $domainID) 
	$text .= Domain::getDomainKeyFromID($domainID) . " ";

WebUtil::WebmasterError($text, "Pop3 CS Problem");
WebUtil::SendEmail("VerifyPop3Activity", "Server@PrintsMadeEasy.com", "Christian Nuesch", "Christian@PrintsMadeEasy.com", "Pop3 CS Problem", $text);

print "Found Problem, sent warning emails.";

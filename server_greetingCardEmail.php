<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


exit("Need an override");

set_time_limit(10000000);

// hard code for PC.com
$domainKeyforReminders = "PrintsMadeEasy.com";
$domainIDforEmail = Domain::getDomainID($domainKeyforReminders);

if(!Domain::checkIfDomainIDexists($domainIDforEmail)){
	WebUtil::WebmasterError("Error with Reminders Emails. The DomainID doesn't exist: " . $domainIDforEmail);
	throw new Exception("Error with Domain ID.");
}

print "Starting Emails<br>";
flush();

$ignoreEmailAddressesArr = array("prints.com", "print.com", "prints.net", "print.net", "printing.com", "printers.com", "printer.com", 
													"cards.com", "card.com", "cards.net", "card.net", "card.com", "zazzle.com", "businesscard", "postcard",
													".co.uk", "fedex.com", "ups.com", ".gov", "printingforless.com", "bizcard",
													"4over.com", "4over.net", "printplace", "flyers.com", "letterhead", "digitalroom.com",
													"ipccsite.com", "carddobserver", "inkd.com", "cardcreator.com", "cardconnection", "moo.com", 
													"hp.com", "degraeve.com", "epson", "microsoft", "copycenter.com", "nebs.com",
													"papercard", "metalcards.com", "flyers.com", "printvisions", "spamhaus.org", "abuseat.org", "robtex.com",
													"antisource.com", ".jp", ".cn", ".in", "rbl.org", "rbl.com", "dnswatch", "simpledns", "curce.ca", "emailtalk", "spamwall",
													"sonicwall", "xerox", "dnsbl", "dnsstuff", "theplanet", "yaritz.net", "norton", "mcafee", "dns.com",
													"mozilla", "microsoft", "apple.com", "declude.com", "sun.com", "spamassasin", "dmoz.org", "rfc-ignorant.org",
													"verio", "opendns", "webmaster", "admin@", "support@", "service@", "webmaster@", "dan.me.uk", "blacklist",
													"blocklist", "emailtalk.org", "wikipedia", "rblcheck", "port25", "spam-block", "spamblock", "inboxer", "cpanel",
													"crystalgraphics.com", "digitalroom", "printing", "posters", "river.com", "ietf.org", "mxtoolbox.com", "farheap",
													"syntegra.com", "barracuda", "swik.net", "dslreports", "wapedia.mobi", "spam", "exim", "filesland", "zdnet", 
													"dnswl.org", "w3c", "google", "irbs.net", "osdir.com", "vamsoft", "hmailserver", "apache", "isc.org", "dns"
													);

$usersToEmail = array();
/*
$usersToEmail["Brian@PrintsMadeEasy.com"] = "Brian Piere";
$usersToEmail["Brian2@PrintsMadeEasy.com"] = "Brian Piere";
$usersToEmail["Brian3@PrintsMadeEasy.com"] = "Brian Piere";
//$usersToEmail["laurie@printsmadeeasy.com"] = "Laurie Champagne";
$usersToEmail["Kelvin@printsmadeeasy.com"] = "Kelvin Angulo";
//$usersToEmail["Brian@DotGraphics.net"] = "Brian Whiteman";
$usersToEmail["billg@printsmadeeasy.com"] = "Bill Giamela";
//$usersToEmail["billgiamela@gmail.com"] = "Bill Giamela";
//$usersToEmail["billbench8@yahoo.com"] = "Bill Giamela";
$usersToEmail["billbench@msn.com"] = "Bill Giamela";
$usersToEmail["laurel@dotgraphics.net"] = "Laurel Altman";
$usersToEmail["DuPaula@PrintsMadeEasy.com"] = "Corazon Amor";
*/

$domainID=1;


$dbCmd->Query("SELECT ID,Name,Email FROM users ORDER BY ID DESC");
//$usersToEmail = array();
$emCounter = 0;
while($row = $dbCmd->GetRow()){
	
	//continue;
	//if($row["ID"] % 2 == 0)
	//	continue;
	
		
	$dbCmd2->Query("SELECT COUNT(*) FROM greetingcardemail WHERE (DomainID=1 OR DomainID=1)  AND EmailAddress LIKE '".DbCmd::EscapeLikeQuery($row["Email"])."' AND Date > DATE_ADD(NOW(), INTERVAL -10 DAY)");
	if($dbCmd2->GetValue() != 0)
		continue;
		
	$dbCmd2->Query("SELECT COUNT(*) FROM emailunsubscribe WHERE DomainID=$domainID AND EmailAddress LIKE '".DbCmd::EscapeLikeQuery($row["Email"])."'");
	if($dbCmd2->GetValue() != 0)
		continue;
/*		
	// Don't email to users who have already ordered the product.
	$dbCmd2->Query("SELECT COUNT(*) FROM projectsordered INNER JOIN orders ON orders.ID = projectsordered.OrderID WHERE UserID=".$row["ID"]." AND (ProductID = 178 OR ProductID = 179)");
	if($dbCmd2->GetValue() != 0)
		continue;
*/		
	if($emCounter > 100000)
		print "<br>";
		
	if($emCounter > 1000){
		print ".";
		flush();
		$emCounter = 0;
	}
		
	$emCounter++;
		
	$usersToEmail[$row["Email"]] = $row["Name"];

}


//$usersToEmail = array();
//$usersToEmail["Brian@PrintsMadeEasy.com"] = "Brian Piere";
//$usersToEmail["Brian2@PrintsMadeEasy.com"] = "Brian Piere";
//$usersToEmail["bpiere21@hotmail.com"] = "Brian Piere";
//$usersToEmail["printsmadeeasy@gmail.com"] = "Brian Piere";



//var_dump($usersToEmail);
//exit();

//$usersToEmail["Brian@PrintsMadeEasy.com"] = "Brian Piere";


$emailCount = 0;

foreach($usersToEmail as $userEmail => $userName){

	$emailCount++;
	
	
	foreach($ignoreEmailAddressesArr as $thisEmailCheck){
		if(preg_match("/".preg_quote($thisEmailCheck)."/i", $userEmail)){
			print "<u>Skipping Email Address: " . $userEmail . "</u><br>";
			continue;
		}
	}

	
	//if($emailCount > 100000)
	//	exit("Finished");
		
	
	$dbCmd2->InsertQuery("greetingcardemail", array("EmailAddress"=>$userEmail, "DomainID"=>$domainID, "Date"=>time()));

	$pathOfEmailAssets = Domain::getDomainSandboxPath($domainKeyforReminders). "/email/Greetings7864/";

	// Get a new message with all of the variables in place.
	$htmlMessage = file_get_contents($pathOfEmailAssets . "/web.html");
	$textMessage = strip_tags($htmlMessage);


	$textMessage = preg_replace("/".preg_quote("&nbsp;")."/", " ", $textMessage);

	$MimeObj = new Mail_mime();

	
	$imageNamesArr = array();
	preg_match_all("/((\w|\d|\\-)+\\.(jpg|gif))/", $htmlMessage, $imageNamesArr);
	$imageNamesArr = $imageNamesArr[0];
	
	if(empty($imageNamesArr) || !is_array($imageNamesArr))
		$imageNamesArr = array();
	

	foreach($imageNamesArr as $fileNameOfImage){

		$inlineImageName = $pathOfEmailAssets . $fileNameOfImage;
			
		if(!file_exists($inlineImageName)){
			WebUtil::WebmasterError("The email image could not be found: $inlineImageName");
			throw new Exception("The email image could not be found: $inlineImageName");
		}
	
		// Get the dimensions of the Company Image.
		$mimeTypeOfFile = ImageLib::GetMimeCodeForImage($inlineImageName);
		
		$MimeObj->addHTMLImage($inlineImageName, $mimeTypeOfFile);
		$inlineImage = "cid:" . $MimeObj->getLastHTMLImageCid();
		
		$htmlMessage = preg_replace("/".preg_quote($fileNameOfImage)."/", $inlineImage, $htmlMessage);
	}

	$htmlMessage = preg_replace("/{NAME}/", WebUtil::htmlOutput($userName), $htmlMessage);
	$htmlMessage = preg_replace("/{EMAIL}/", WebUtil::htmlOutput($userEmail), $htmlMessage);
	
	$textMessage = preg_replace("/{NAME}/", WebUtil::htmlOutput($userName), $textMessage);
	$textMessage = preg_replace("/{EMAIL}/", WebUtil::htmlOutput($userEmail), $textMessage);
	

	$domainEmailConfigObj = new DomainEmails(1);
		
	// It is better to have the Text/Plain part come first in case the client can't understand multi-part messages.
	$MimeObj->setTXTBody($textMessage);
	$MimeObj->setHTMLBody($htmlMessage);
//exit($htmlMessage);	
	$MimeObj->setSubject("Holiday Greeting Cards, Hurry to get 40% Off");
	$MimeObj->setFrom($domainEmailConfigObj->getEmailNameOfType(DomainEmails::REMINDER) . " <" . $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::REMINDER) . ">");

	$body = $MimeObj->get();
	$hdrs = $MimeObj->headers();
	
	// Change the headers and return envelope information for the SendMail command.
	// We don't want emails from different domains to look like they are coming from the same mail server.
	$hdrs["Message-Id"] =   "<" . substr(md5(uniqid(microtime())), 0, 15) . "@" . Domain::getDomainKeyFromID($domainIDforEmail) . ">";
	$additionalSendMailParameters = "-r " . $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::REMINDER);

	// Outlook doesn't recognize it as an inline (but as attachemnt) if we have an @domain.com in the Content-ID: !!! mine.php generates cid@domian.com other mailers just cid
	$body = preg_replace("/%40" . preg_quote(Domain::getDomainKeyFromID($domainIDforEmail)) . "/i","",$body);
	
	
	$userName = preg_replace("/</", "", $userName);
	$userName = preg_replace("/>/", "", $userName);
//var_dump($body);
	$mailObj = new Mail();
	
	$mailObj->send(($userName . " <$userEmail>"), $hdrs, $body, $additionalSendMailParameters);
	//$mailObj->send(($userName . " <".Constants::GetAdminEmail().">"), $hdrs, $body, $additionalSendMailParameters);


	print "$emailCount : $userEmail <br>\n";
	flush();
	
	unset($MimeObj);
	unset($mailObj);
	
	Constants::FlushBufferOutput();

	sleep(2);


}


$AdminSubject = "$domainKeyforReminders Email: Greeting Cards. $emailCount emails were sent out.";
$AdminBody = "Keep your fingers crossed.";

//$emailContactsForReportsArr = Constants::getEmailContactsForServerReports();
//$emailContactsForReportsArr[] = "laurie@printsmadeeasy.com";
$emailContactsForReportsArr = array("Brian@PrintsMadeEasy.com");
foreach($emailContactsForReportsArr as $thisEmailContact)
	WebUtil::SendEmail("E-Mail Notify", Constants::GetMasterServerEmailAddress(), "", $thisEmailContact, $AdminSubject, $AdminBody, true);

print "<hr>Done Sending Dead Account Emails.";







?>
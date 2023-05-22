<?

require_once("library/Boot_Session.php");

// If this script terminates... it should sent a "stop signal" to the mailer industry thread and abort the process.
set_time_limit(4000);
ini_set("memory_limit", "512M");

$dbCmd = new DbCmd();



// ====================  This script is meant to be called by a cron job every so often... listening for attachments coming on in a designated email address.
// ====================  Right now we are only doing a "Merchant Mailer"... but in the future we abstract this script to handle many different types of Marketing programs.


// Make this script be able to run for a while 
set_time_limit(9000);
ini_set("memory_limit", "512M");

// Originally this function was used for language translations..... since it is not needed in this application... just return what is passed in
// I am not sure exactly why... but for some reason on the live server it is complaining that the function is declared twice and has a fatal error
// on the development server is ok.... maybe some PEAR modules are build directly into PHP on the live server???
// Anyway... because this is in an IF block... the function declaration has to come before anytime that it gets used within a subseqeuent function call.... that is why we place it near the top of the script.
if(!function_exists('_') ){
	function _($str) {
		return $str;
	}
}


$domainObj = Domain::singleton();




// For when people reply to the auto-confirm messages about shipment notifications, etc.
$pop3 = new POP3("", 60);

$domainEmailConfigObj = new DomainEmails(Domain::getDomainIDfromURL());

$mailfetch_user = $domainEmailConfigObj->getUserOfType(DomainEmails::MARKETING);
$mailfetch_pass = $domainEmailConfigObj->getPassOfType(DomainEmails::MARKETING);
$mailfetch_server = $domainEmailConfigObj->getHostOfType(DomainEmails::MARKETING);

// In none of the attachments begin with this prefix... they won't be used.
$prefixOfAttachmentToLookFor = $domainEmailConfigObj->marketingDataAttachmentPrefix;


if(empty($mailfetch_user) || empty($mailfetch_pass) || empty($mailfetch_server) || empty($prefixOfAttachmentToLookFor)){
	WebUtil::WebmasterError("Error in server_getMarketingData.php ... the Email has not been configured for the domain: " . Domain::getDomainKeyFromURL());
	exit("Domain Error with email.");
}




// This script could download many different emails with attachments at once.
// This will let our email to finish downloading and deleting the messages before we try and run any import scripts.
$attachmentsOnDiskToLoadArr = array();

// Parallel array to $attachmentsOnDiskToLoadArr for the person's email who sent this message.
$fromAddressLinkedToAttachmentsArr = array();


// Parallel array to $attachmentsOnDiskToLoadArr for the subject that was in each message.
$emailSubjectLinkedToAttachmentsArr = array();

// Parallel array to $attachmentsOnDiskToLoadArr defining what Product to load.
$productIDsToAttachmentsArr = array();


if (!$pop3->connect($mailfetch_server)) {
	Mail_Fetch_Status(("Oops, ... ") . $pop3->ERROR );
	exit;
}

$Count = $pop3->login($mailfetch_user, $mailfetch_pass);

if (($Count == false || $Count == -1) && $pop3->ERROR != '') {
	Mail_Fetch_Status(("Login Failed: ...") . ' ' . $pop3->ERROR );
	exit;
}

if ($Count == 0) {
	Mail_Fetch_Status(("Login OK ...: Inbox EMPTY"));
	$pop3->quit();
}
else {
	Mail_Fetch_Status(("Login OK ...: Inbox contains [") . $Count . ("] messages"));
}

$retryCount = 0;
$max_retries = 20;

$FromEmail = "";

for ($i=1; $i <= $Count; $i++) {
	

	Mail_Fetch_Status(("Fetching message ") . "$i" );
	set_time_limit(20); // 20 seconds per message max

	$Message = "";
	$MessArray = $pop3->get($i);

	while ( (!$MessArray) or (gettype($MessArray) != "array")) {

			if($retryCount > $max_retries){
				Mail_Fetch_Status(("Timeout"));
				exit;
			}

			$retryCount++;

			Mail_Fetch_Status(("Oops, ") . $pop3->ERROR);
			Mail_Fetch_Status(("Server error...Disconnect"));

			$pop3->quit();

			Mail_Fetch_Status(("Reconnect from dead connection"));

			if (!$pop3->connect($mailfetch_server)) {
				Mail_Fetch_Status(("Oops, ") . $pop3->ERROR );
				Mail_Fetch_Status(("Saving UIDL"));

				continue;
			}

			$Count = $pop3->login($mailfetch_user, $mailfetch_pass);

			if (($Count == false || $Count == -1) && $pop3->ERROR != '') {
				Mail_Fetch_Status(("Login Failed:") . ' ' . $pop3->ERROR );
				Mail_Fetch_Status(("Saving UIDL"));
				continue;
			}

			Mail_Fetch_Status(("Refetching message ") . "$i" );

			$MessArray = $pop3->get($i);

	} // end while

	while (list($lineNum, $line) = each ($MessArray)) {
		 $Message .= $line;
	}

	// Create a temporary file on disk... like `msg_LKLKJ093SDF.txt' and dump the raw message data into it... (for backup)
	$RawCStextFile = FileUtil::newtempnam(Constants::GetFileAttachDirectory(), "msg_", ".txt");
	if($RawCStextFile){
		$fp = fopen($RawCStextFile, "w");
		fwrite($fp, $Message);
		fclose($fp);
	}

	// Put the EMAIL message into a stucture so that we can get all of the parts
	// We are using the MIME decode function from Pear
	$params['include_bodies'] = TRUE;
	$params['decode_bodies']  = TRUE;
	$params['decode_headers'] = TRUE;
	$decoder = new Mail_mimeDecode($Message);
	$MessageStructure = $decoder->decode($params);


	// In case they forgot to put in a subject we don't want our program to crash
	if(!isset($MessageStructure->headers["subject"]))
		$subject = "No Subject";
	else
		$subject = $MessageStructure->headers["subject"];
		
	$FromEmail = GetValueFromEmailHeader($MessageStructure->headers["from"], "email");
		
	

	// Loop through all "parts" of the message... one of the parts could be an attachment.
	if(strtoupper($MessageStructure->ctype_primary) == "MULTIPART"){

		Mail_Fetch_Status("MULTIPART - Looking for attachments.");

		foreach($MessageStructure->parts as $MessagePart){
			
			// The dispostion isn't set on every MessagePart
			if(!isset($MessagePart->disposition))
				continue;
				
			if(strtoupper($MessagePart->disposition) == "ATTACHMENT" || strtoupper($MessagePart->disposition) == "INLINE"){

				$fileAttachName = "NoAttachmentName";
				
				if(isset($MessagePart->content_parameters) && is_array($MessagePart->content_parameters) && (array_key_exists('name', $MessagePart->content_parameters) || array_key_exists('filename', $MessagePart->content_parameters))){
					if(array_key_exists('name', $MessagePart->content_parameters))
						$fileAttachName = $MessagePart->content_parameters['name'];
					else
						$fileAttachName = $MessagePart->content_parameters['filename'];
				}

				
				Mail_Fetch_Status("Getting attachment - " . $fileAttachName);
				
				// Only extract the Attatchment that matches the prefix we are expecting... folowed by a Product ID.
				$matches = array();
				if(!preg_match("/^" . $prefixOfAttachmentToLookFor . "_(\d+)_/i", $fileAttachName, $matches)){
					Mail_Fetch_Status(("Skipping Attachment because it does not match a prefix: " . $fileAttachName));
					continue;
				}
				else{
					$productIDsToAttachmentsArr[] = $matches[1];
				}
				
				$fileName = $fileAttachName; 
				$fileBinaryData = $MessagePart->body;
				
				
				$fullPathToFile = Constants::GetTempDirectory() . "/" . $fileName;
				
				// First find out if the file has already been written to disk.  
				// That will keep us from creating multiple import scripts (in case the sender computer has a run away process or something).
				// The data company should be chaning their file names every day.
				if(file_exists($fullPathToFile)){
					Mail_Fetch_Status(("Skipping Attachment because the file already exisit on Disk: " . $fullPathToFile));
					continue;
				}
				
				
				$attachmentsOnDiskToLoadArr[] = $fileName;
				$emailSubjectLinkedToAttachmentsArr[] = $subject;
				$fromAddressLinkedToAttachmentsArr[] = $FromEmail;
				
				// Put image data into the temp file 
				$fp = fopen($fullPathToFile, "w");
				fwrite($fp, $fileBinaryData);
				fclose($fp);

			}
		}
	}

}




// Now that all of the messages have been downloaded... we can delete them from the server...
for ($i=1; $i <= $Count; $i++) 
	$pop3->delete($i);

Mail_Fetch_Status(("Closing POP ... "));

$pop3->quit();

Mail_Fetch_Status(("Done downloading Messages.... "));
print "<br><br>\n\n";



if(empty($attachmentsOnDiskToLoadArr)){

	// Make sure we have at least 1 message.
	if($Count > 0){
		$emailMessage = "Could not import the marketing data because the file Attachments did not have the appropriate prefix.";
		$emailMessage .= "\n\nThe attachment must be in the following format. The attachment prefix, followed by an underscore, then the product ID number, followed by another underscore (with no spaces). Anything may be put after that, but the attachment should be unique everyday, so the date is a good choice.\n\n";
		$emailMessage .= $domainEmailConfigObj->marketingDataAttachmentPrefix . "_PRODUCTID_Date-Bla-Bla-Bla.xls";
		
		print "<br><br>" . $emailMessage . "<br><br>";
		// In case we didn't find a From email... bounce back to the Webmaster
		if(empty($FromEmail))
			$FromEmail = $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::WEBMASTER);
		
	
		WebUtil::SendEmail($domainEmailConfigObj->getEmailNameOfType(DomainEmails::MARKETING), $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::MARKETING), "", $FromEmail, "Postage Type Failure", $emailMessage);
		
		Mail_Fetch_Status(("No Attachments were found..... "));
	}
	
}
else{
	
	$attachmentCounter = 0;
	foreach($attachmentsOnDiskToLoadArr as $thisAttachmentName){
	
		Mail_Fetch_Status(("Running Import Script on File: " . $thisAttachmentName));
		
		
		
		$postageType = null;
		
		// Extract the Postage Type out of the email subject.
		if(preg_match("/postage\s*=\s*first/i", $emailSubjectLinkedToAttachmentsArr[$attachmentCounter])){
			$postageType = "First Class";
			Mail_Fetch_Status("Creating a First Class Mailing Batch.");
		}
		else if(preg_match("/postage\s*=\s*bulk/i", $emailSubjectLinkedToAttachmentsArr[$attachmentCounter])){
			$postageType = "Standard";
			Mail_Fetch_Status("Creating a Standard Postage Mailing Batch.");
		}
		else{
			
			Mail_Fetch_Status("Failed to find a postage match.");
		
			if(file_exists(Constants::GetTempDirectory() . "/" . $thisAttachmentName))
				unlink(Constants::GetTempDirectory() . "/" . $thisAttachmentName);
				
			$emailMessage = "Could not import the marketing data because you forget to specifiy a postage type in the Message Subject.\n\nTry... Postage=Bulk or Postage=First on your next attempt.";
			
			print "<br><br>" . $emailMessage . "<br><br>";
			WebUtil::SendEmail($domainEmailConfigObj->getEmailNameOfType(DomainEmails::MARKETING), $domainEmailConfigObj->marketingDataEmailAddress, "", $fromAddressLinkedToAttachmentsArr[$attachmentCounter], "Postage Type Failure", $emailMessage);
		
			continue;
		}
		
		
		// Make sure that the Product ID exists for the attachment
		$domainIDofUserIDSavedProject = UserControl::getDomainIDofUser($domainEmailConfigObj->marketingDataUserIDofSavedProjects);
		$productIDAttchmnt = $productIDsToAttachmentsArr[$attachmentCounter];
		if(!Product::checkIfProductIDisActive($dbCmd, $productIDAttchmnt) || Product::getDomainIDfromProductID($dbCmd, $productIDAttchmnt) != $domainIDofUserIDSavedProject){
			
			Mail_Fetch_Status("Product ID doesn't exist.");
		
			if(file_exists(Constants::GetTempDirectory() . "/" . $thisAttachmentName))
				unlink(Constants::GetTempDirectory() . "/" . $thisAttachmentName);
				
			$emailMessage = "Could not import the marketing data the attachment name contained a Product ID inside which doesn't exist.\n\nProductID: $productIDAttchmnt \nAttachment Name: $thisAttachmentName";
			
			print "<br><br>" . $emailMessage . "<br><br>";
			WebUtil::SendEmail($domainEmailConfigObj->getEmailNameOfType(DomainEmails::MARKETING), $domainEmailConfigObj->marketingDataEmailAddress, "", $fromAddressLinkedToAttachmentsArr[$attachmentCounter], "Postage Type Failure", $emailMessage);
		
			continue;
			
		}
		
		
		$importDomain = $domainObj->getWebsiteURLforDomainID(Domain::getDomainIDfromURL());
		$importScriptURL = "mailers_industries.php?savedProjectsUserID=".$domainEmailConfigObj->marketingDataUserIDofSavedProjects."&marketingName=".urlencode($domainEmailConfigObj->marketingDataSavedProjectsPrefix)."&mailingListFileName=$thisAttachmentName&postageType=" . urlencode($postageType) . "&productID=" . $productIDAttchmnt;
		
		$timeout = 30; 

		
		Mail_Fetch_Status("About to open a connection to the Mailer Industries Script");
		
		// Open a connection to the PHP file that will run the actual import script.  This code will keep system from timing out.
		$nullVar = null;
		$fp = fsockopen("s10.printsmadeeasy.com", 80, $nullVar, $nullVar, $timeout); 
		if ($fp) { 
			fwrite($fp, "GET /$importScriptURL HTTP/1.0\r\n"); 
			fwrite($fp, "Host: $importDomain\r\n"); 
			fwrite($fp, "Connection: Close\r\n\r\n"); 

			stream_set_blocking($fp, TRUE); 
			stream_set_timeout($fp,$timeout); 
			$info = stream_get_meta_data($fp);

			while ((!feof($fp)) && (!$info['timed_out'])) { 
				$data = fgets($fp, 1024); 
				$info = stream_get_meta_data($fp); 
				print $data;
				flush(); 
			} 

			if ($info['timed_out'])
				echo "<b>Connection Timed Out!</b>"; 

			print "<hr>Done With Socket Connection to Mailer Industries URL.";
		}
		else{
			print "Connection could not be opened";
		}
		
		$attachmentCounter++;
	}

}


function Mail_Fetch_Status($msg) {
	echo  $msg . "<br>\n";
	flush();
}





// From  the header the address somes like....    "Brian Piere" <Brian@Example.com>
// We want a way to grab either the name, or the email address out with pattern matching.
function GetValueFromEmailHeader($str, $valueType){


	if($valueType == "email"){
			$matches = array();
		if(preg_match_all("/(\w+([-.]\w+)*\@\w+([-.]\w+)*\.\w+)/", $str, $matches)){
			return $matches[1][0];
		}
		else{
			// Othewise it that there was an error..  We really need to get to the bottom of this.
			WebmasterError("Can not find a matching email address in the function GetValueFromEmailHeader");
			return "error";
		}
	}
	else if($valueType == "name"){
		$matches = array();
		if(preg_match_all("/^\"((\w|\s)+)\"/", $str, $matches))
			return $matches[1][0];
	}
	else{
		exit("Invalid valueType argument in the function call .. GetValueFromEmailHeader");
	}

	return "";

}










?>
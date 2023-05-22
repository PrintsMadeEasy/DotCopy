<?

require_once("library/Boot_Session.php");

ini_set("memory_limit", "512M");


$dbCmd = new DbCmd();



$passiveAuthObj = Authenticate::getPassiveAuthObject();
if($passiveAuthObj->CheckIfLoggedIn())
	$userID = $passiveAuthObj->GetUserID();
else
	$userID = 0;
	
$printDebugFlag = false;

if(in_array($userID, array(2,52204)))
	$printDebugFlag = true;
	

// Make this script be able to run for a while 
set_time_limit(1000);


// Originally this function was used for language translations..... since it is not needed in this application... just return what is passed in
// I am not sure exactly why... but for some reason on the live server it is complaining that the function is declared twice and has a fatal error
// on the development server is ok.... maybe some PEAR modules are build directly into PHP on the live server???
// Anyway... because this is in an IF block... the function declaration has to come before anytime that it gets used within a subseqeuent function call.... that is why we place it near the top of the script.
if(!function_exists('_') ){
	function _($str) {
		return $str;
	}
}


$allDomainIDsArr = Domain::getAllDomainIDs();

foreach($allDomainIDsArr as $thisDomainID){

	// Get emails from customer service
	$pop3 = new POP3("", 60);
	
	$domainEmailConfigObj = new DomainEmails($thisDomainID);
	
	DownloadFromServer($dbCmd, $pop3, $domainEmailConfigObj->getUserOfType(DomainEmails::CUSTSERV), $domainEmailConfigObj->getPassOfType(DomainEmails::CUSTSERV), $domainEmailConfigObj->getHostOfType(DomainEmails::CUSTSERV), ("Customer Service For: " . Domain::getDomainKeyFromID($thisDomainID)), $thisDomainID);
}


// Data for server_verifyPop3Activity.php
function RecordLastConnection(DbCmd $dbCmd, $domainID) {
	
	$domainID = intval($domainID);
		
	// Make sure there is always 1 record per domain.
	$dbCmd->Query("SELECT count(*) FROM pop3getemails WHERE DomainID = $domainID");
	if($dbCmd->GetValue() == 0)
		$dbCmd->InsertQuery("pop3getemails",  array("DomainID"=>$domainID));
	
	$dbCmd->Query("UPDATE pop3getemails SET LastConnection='".DbCmd::FormatDBDateTime(time())."' WHERE DomainID = $domainID");
}


function DownloadFromServer(DbCmd $dbCmd, &$pop3, $mailfetch_user, $mailfetch_pass, $mailfetch_server, $DebugLabel, $domainID){

	// In case a Domain does not have its Customer service email setup yet... then just set the Server Name to Null.
	if(empty($mailfetch_server))
		return;


	if (!$pop3->connect($mailfetch_server)) {
		Mail_Fetch_Status(_("Oops, $DebugLabel ... ") . $pop3->ERROR );
		exit;
	}

	$Count = $pop3->login($mailfetch_user, $mailfetch_pass);

	if (($Count == false || $Count == -1) && $pop3->ERROR != '') {
		Mail_Fetch_Status(_("Login Failed: $DebugLabel ...") . ' ' . $pop3->ERROR );
		exit;
	}

	if ($Count == 0) {
		Mail_Fetch_Status(_("Login OK $DebugLabel ...: Inbox EMPTY"));
		$pop3->quit();
	}
	else {
		Mail_Fetch_Status(_("Login OK $DebugLabel ...: Inbox contains [") . $Count . _("] messages"));
	}

	#-- Retrieve the messages from the server and process
	GetEmailMessages($Count, $pop3, $dbCmd, $mailfetch_server, $mailfetch_user, $mailfetch_pass, $domainID);

	Mail_Fetch_Status(_("Closing POP ...  $DebugLabel"));

	$pop3->quit();

	Mail_Fetch_Status(_("Done .... $DebugLabel "));
	
	RecordLastConnection($dbCmd,$domainID);
}


// Pass in a count of how many messages there are..and it will try and retrieve all of the messages and insert them into the database
function GetEmailMessages($Count, &$pop3, &$dbCmd, $mailfetch_server, $mailfetch_user, $mailfetch_pass, $domainID){

	$retryCount = 0;
	$max_retries = 20;

	for ($i=1; $i <= $Count; $i++) {
		Mail_Fetch_Status(_("Fetching message ") . "$i" );
		set_time_limit(20); // 20 seconds per message max

		$Message = "";
		$MessArray = $pop3->get($i);

		while ( (!$MessArray) or (gettype($MessArray) != "array")) {

				if($retryCount > $max_retries){
					Mail_Fetch_Status(_("Timeout"));
					exit;
				}

				$retryCount++;

				Mail_Fetch_Status(_("Oops, ") . $pop3->ERROR);
				Mail_Fetch_Status(_("Server error...Disconnect"));

				$pop3->quit();

				Mail_Fetch_Status(_("Reconnect from dead connection"));

				if (!$pop3->connect($mailfetch_server)) {
					Mail_Fetch_Status(_("Oops, ") . $pop3->ERROR );
					Mail_Fetch_Status(_("Saving UIDL"));

					continue;
				}

				$Count = $pop3->login($mailfetch_user, $mailfetch_pass);

				if (($Count == false || $Count == -1) && $pop3->ERROR != '') {
					Mail_Fetch_Status(_("Login Failed:") . ' ' . $pop3->ERROR );
					Mail_Fetch_Status(_("Saving UIDL"));
					continue;
				}

				Mail_Fetch_Status(_("Refetching message ") . "$i" );

				$MessArray = $pop3->get($i);

		}

		foreach($MessArray as $line )
			 $Message .= $line;


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
			$MessageStructure->headers["subject"] = "No Subject";

		InsertEmailIntoDatabase($dbCmd, $MessageStructure, basename($RawCStextFile), $domainID);


	}

	// Now all of the messages should have been inserted into the database...
	// We can cycle through and delete all of them.  It is safe to do this because messages are not actually deleted until the "quit" command is issued.
	for ($i=1; $i <= $Count; $i++) 
		$pop3->delete($i);

}


function Mail_Fetch_Status($msg) {
	
	global $printDebugFlag;
	
	if($printDebugFlag){
		echo  $msg . "<br>\n";
	}
	else{
		
		echo "       ^^^need admin override^^^           <br><br>";
	}
	flush();
}


function InsertEmailIntoDatabase(DbCmd $dbCmd, &$MessageStructure, $RawCStextFile, $domainID){

	$mysql_timestamp = date("YmdHis", time());

	$FromName = GetValueFromEmailHeader($MessageStructure->headers["from"], "name", $RawCStextFile, $domainID);
	$FromEmail = GetValueFromEmailHeader($MessageStructure->headers["from"], "email", $RawCStextFile, $domainID);


	// Now we should peer into the subject of the message.. We are looking for a Message ID.  This is how we associate a message with an existing thread
	$matches = array();
	if(preg_match_all("/msg\[(\d+)\]/", $MessageStructure->headers["subject"], $matches)){

		$CS_ThreadID = $matches[1][0];

		// To keep the disk from filling up quicker.... with each reply see if there is an old Raw text dump file that we can get rid of.  We will be putting in a new one.
		CustService::RemoveRawTextFile($dbCmd, $CS_ThreadID);

		// Since there is already a Customer Service Entry for this message... It means that we need to re-activate the csitem
		$dbCmd->Query("UPDATE csitems SET Status='O', RawTextDumpFileName='$RawCStextFile' WHERE ID=$CS_ThreadID");
	
	}
	else{

		// Remember that all Customer Service Responses have a msg ID in the subject
		// Since this message is not about a previous Customer Service inquiry... then it means that we have to create a new one

		$infoHash["Subject"] = $MessageStructure->headers["subject"];
		$infoHash["Status"] = "O";
		$infoHash["UserID"] = 0;
		$infoHash["Ownership"] = 0;
		$infoHash["OrderRef"] = 0;
		$infoHash["DateCreated"] = $mysql_timestamp;
		$infoHash["LastActivity"] = $mysql_timestamp;
		$infoHash["CustomerName"] = $FromName;
		$infoHash["CustomerEmail"] = $FromEmail;
		$infoHash["RawTextDumpFileName"] = $RawCStextFile;
		$infoHash["DomainID"] = $domainID;
		
		

		// Insert into the Database and the function will return what the new csThreadID is
		$CS_ThreadID = CustService::NewCSitem($dbCmd, $infoHash);

	}

	$MessageToBeInserted = GetTopMessage($MessageStructure);


	// Get the date the the message was received --#
	// The date is in the format "Thu, 17 Jul 2003 20:30:29 -0500"  ... We are only interested in parsing out the hours:minutes:seconds   ... we can use PHP's date function to make up the rest.
	if(preg_match_all("/(\d+):(\d+):(\d+)/", $MessageStructure->headers["delivery-date"], $matches)){

		$email_hour = $matches[1][0];
		$email_minutes = $matches[2][0];
		$email_seconds = $matches[3][0];

		// Make sure that the hours / minutes seconds are valid.
		if(strlen($email_hour) != 2 || strlen($email_minutes) != 2 || strlen($email_minutes) != 2){
			$mysql_timestamp_2 = date("YmdHis", time());
		}
		else{
			$mysql_timestamp_2 = date("Ymd", time()) . $email_hour . $email_minutes . $email_seconds;
		}
		

	}
	else{
		//WebUtil::WebmasterError("Error extracting the date for CS_ThreadID $CS_ThreadID : " . Domain::getDomainKeyFromID($domainID) . " Date: " . $MessageStructure->headers["delivery-date"]);
		$mysql_timestamp_2 = date("YmdHis", time());

	}
	

	// This will check and see if the CS item is NOT associated with anyting
	// If it is not then we may find an order number in the Message and be able to associate by that.
	// Or by the person's email address
	CustService::PossiblyAssociateReferenceWithCSitem($dbCmd, $CS_ThreadID, $MessageToBeInserted, $FromEmail);


	// Now put the message into the DB.... associated with the CSItemID
	$insertArr["FromUserID"] = 0;
	$insertArr["ToUserID"] = 0;
	$insertArr["csThreadID"] = $CS_ThreadID;
	$insertArr["CustomerFlag"] = "Y";
	$insertArr["FromName"] = $FromName;
	$insertArr["FromEmail"] = $FromEmail;
	$insertArr["ToName"] = "Customer Service";
	$insertArr["ToEmail"] = "";
	$insertArr["Message"] = $MessageToBeInserted;
	$insertArr["DateSent"] = $mysql_timestamp_2;

	$dbCmd->InsertQuery("csmessages",  $insertArr);


	$dbCmd->Query("UPDATE csitems SET LastActivity='$mysql_timestamp' WHERE ID=$CS_ThreadID");

	InsertAttachments($dbCmd, $MessageStructure, $CS_ThreadID);

	//Scan for junk mail and automatic ownership assignments/ etc.
	CustService::FilterEmail($dbCmd, $CS_ThreadID);


	$memberAttendanceObj = new MemberAttendance($dbCmd);

	
	// We don't want to get into a case where we send out an "Auto Reply" and then their server does another auto reply and so on.
	// Find out if this message thread has already sent an "auto reply" within the past 10 mintues.
	$dbCmd->Query("SELECT COUNT(*) FROM csmessages WHERE csThreadID=$CS_ThreadID AND DateSent > DATE_ADD(NOW(), INTERVAL -10 MINUTE) AND Message='Automatic Reply Sent'");
	$recentAutoReplyCount = $dbCmd->GetValue();
	
	
	// Find out who ownes the CS item (if Anyone).
	// We want to find out if they are away on vacation or at lunch so that we can send the Customer an "Auto Reply".
	$dbCmd->Query("SELECT Ownership FROM csitems WHERE ID=$CS_ThreadID");
	$ownerShipID = $dbCmd->GetValue();

	if($ownerShipID){

		// Because this is the server script run by the Cron... we don't need to be logged in.
		// Force the login ID of the CSR UserID to make sure that we have permission on the Member Attendance.
		$forceAuthObj = new Authenticate(Authenticate::login_OVERRIDE, $ownerShipID);
		if(!$forceAuthObj->CheckForPermission("VIEW_CUSTOMER_SERVICE"))
			$employeeWasMaybeFiredFlag = true;
		else
			$employeeWasMaybeFiredFlag = false;

		
		if($memberAttendanceObj->checkForAutoReplyOnUser($ownerShipID) && $recentAutoReplyCount == 0 && !$employeeWasMaybeFiredFlag){

			$autoReplyMessage = $memberAttendanceObj->getAutoReplyMessage($ownerShipID, $CS_ThreadID);
			
			$domainOfCsItem = CustService::getDomainIDfromCSitem($dbCmd, $CS_ThreadID);
			$domainEmailConfigObj = new DomainEmails($domainOfCsItem);
			
			$our_email = $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::CUSTSERV);
			$our_name = $domainEmailConfigObj->getEmailNameOfType(DomainEmails::CUSTSERV);

			// Now put the message into the DB.... associated with the CSItemID
			$insertArr = array();
			$insertArr["FromUserID"] = $ownerShipID;
			$insertArr["ToUserID"] = 0;
			$insertArr["csThreadID"] = $CS_ThreadID;
			$insertArr["CustomerFlag"] = "N";
			$insertArr["FromName"] = $our_name;
			$insertArr["FromEmail"] = $our_email;
			$insertArr["ToName"] = $FromName;
			$insertArr["ToEmail"] = $FromEmail;
			$insertArr["Message"] = "Automatic Reply Sent";
			$insertArr["DateSent"] = date("YmdHis");
			$dbCmd->InsertQuery("csmessages",  $insertArr);

			
			// Get the Subject of the CS Item
			$dbCmd->Query("SELECT Subject FROM csitems WHERE ID=$CS_ThreadID");
			$thisSubject = $dbCmd->GetValue();

			// The subject of the message should always have the CS thread ID in it
			// The subject is only updated on the csitem table when the csitem entry is first created... We need to always append the message ID in the subject when sending out.
			$thisSubject = $thisSubject . "  -- msg[$CS_ThreadID]";
			
			if(!Constants::GetDevelopmentServer() && !empty($autoReplyMessage)){
			
				// Return an email back to the customer.
				$MimeObj = new Mail_mime();
				$MimeObj->setTXTBody($autoReplyMessage);
				$MimeObj->setSubject($thisSubject);
				$MimeObj->setFrom("$our_name <$our_email>");

				$hdrsArr = array(
					      'From'    => "$our_name <$our_email>",
					      'Subject' => $thisSubject
					      );

				$body = $MimeObj->get();
				$hdrs = $MimeObj->headers($hdrsArr);

				
				// Change the headers and return envelope information for the SendMail command.
				// We don't want emails from different domains to look like they are coming from the same mail server.
				$hdrs["Message-Id"] =  "<" . substr(md5(uniqid(microtime())), 0, 15) . "@" . Domain::getDomainKeyFromID($domainOfCsItem) . ">";
				$additionalSendMailParameters = "-r " . $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::CUSTSERV);
				
				$mail = new Mail();
				$mail->send("$FromName <$FromEmail>", $hdrs, $body, $additionalSendMailParameters);
			}

		}

	}



}


// Will go through the message structure and look for multi part attachments.  AS long as the attachment isn't text then we will insert into the DB
function InsertAttachments(DbCmd $dbCmd, &$MessageStructure, $CSThreadID){

	if(strtoupper($MessageStructure->ctype_primary) == "MULTIPART"){

		Mail_Fetch_Status("MULTIPART - Looking for attachments.");

		foreach($MessageStructure->parts as $MessagePart){
			
			// This is not set on all messages.
			if(!isset($MessagePart->disposition))
				continue;
				
			if(strtoupper($MessagePart->disposition) == "ATTACHMENT" || strtoupper($MessagePart->disposition) == "INLINE"){
				
				if(isset($MessagePart->content_parameters) && is_array($MessagePart->content_parameters) && (array_key_exists('name', $MessagePart->content_parameters) || array_key_exists('filename', $MessagePart->content_parameters))){
					if(array_key_exists('name', $MessagePart->content_parameters))
						$fileAttachName = $MessagePart->content_parameters['name'];
					else
						$fileAttachName = $MessagePart->content_parameters['filename'];
				}
				else{
					$fileAttachName = "NoAttachmentName";
				}
				
				Mail_Fetch_Status("Inserting attachment - " . $fileAttachName);
				
				// Winmail.dat is a common attachment for Outlook.  don't bother trying to insert that one.  (even if it is greyed out) we don't want to see it.
				if(!preg_match("/\.dat$/", $fileAttachName)){
					CustService::InsertCSattachment($dbCmd, $CSThreadID, $fileAttachName, $MessagePart->body);
				}
			}
		}
	}
}


// From  the header the address somes like....    "Brian Piere" <Brian@Example.com>
// We want a way to grab either the name, or the email address out with pattern matching.
function GetValueFromEmailHeader($str, $valueType, $RawCStextFile, $domainID){

	$matches = array();
	
	if($valueType == "email"){
		if(preg_match_all("/(\w+([-.]\w+)*\@\w+([-.]\w+)*\.\w+)/", $str, $matches)){
			return $matches[1][0];
		}
		else{

			$emailSnippet = substr($RawCStextFile, 0, 2000);
			
			// Othewise it that there was an error..  We really need to get to the bottom of this.
			//WebUtil::WebmasterError(("Can not find a matching email address in the function GetValueFromEmailHeader for Domain: " . Domain::getDomainKeyFromID($domainID) . "\n\n\nSnippet:\n" . $emailSnippet), "Email Header Not Found");
			return "error";
		}
	}
	else if($valueType == "name"){
		if(preg_match_all("/^\"((\w|\s)+)\"/", $str, $matches))
			return $matches[1][0];
	}
	else{
		throw new Exception("Invalid valueType argument in the function call .. GetValueFromEmailHeader");
	}

	return "";

}




function StripHTMLtags($Message){

	// look for line breaks
	$Message = preg_replace("/(<br>|<BR>|<hr>|<HR>)/", "\n", $Message);

	// look for spaces
	$Message = preg_replace("/&nbsp;/", " ", $Message);

	// Get rid of the rest of the tags
	$Message = strip_tags($Message);


	return $Message;
}


// When someone replies to an email the characters -----Original Message----- separates each messsage
// We just want the top message
function GetTopMessage(&$MessageStructure){


	// Search for the world "multipart"
	if(preg_match("/multipart/i", $MessageStructure->ctype_primary)){

		Mail_Fetch_Status("MULTIPART");

		// Check if If there is another level of multi-part messages
		if(preg_match("/multipart/i", $MessageStructure->parts[0]->ctype_primary)){

			$msg = GetHTML_or_TextMessageFromMessageStructure($MessageStructure->parts[0]->parts[0]);

			if($msg == "")
				$msg = "In 2nd level of multipart message, nothing found";	
		}
		else{
			$msg = GetHTML_or_TextMessageFromMessageStructure($MessageStructure->parts[0]);

			if($msg == "")
				$msg = "In 1st level of multipart message, nothing found";
		}

	}
	else{

		// Otherwise this is just a regular old email..  Get the body parts from the root.

		$msg = GetHTML_or_TextMessageFromMessageStructure($MessageStructure);

		if($msg == "")
			$msg = "The body seems to be empty with this message.";

	}


	// Do pattern matching to scape off the original message --#
	$msg = CustService::GetOriginalMessage($msg);

	return $msg;
}

function GetHTML_or_TextMessageFromMessageStructure(&$MsgStruct){

		if(strtoupper($MsgStruct->ctype_secondary) == "HTML")
			$msg = StripHTMLtags($MsgStruct->body);
		else if(strtoupper($MsgStruct->ctype_secondary) == "PLAIN")
			$msg = $MsgStruct->body;
		else
			$msg = "";

		return $msg;
}



?>
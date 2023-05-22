<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();




//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("VIEW_CUSTOMER_SERVICE"))
	throw new Exception("Permission Denied");



$save = WebUtil::GetInput("save", FILTER_SANITIZE_STRING_ONE_LINE);
$body = WebUtil::GetInput("body", FILTER_UNSAFE_RAW);
$customername = WebUtil::GetInput("customername", FILTER_SANITIZE_STRING_ONE_LINE);
$csthreadid = WebUtil::GetInput("csthreadid", FILTER_SANITIZE_INT);
$emailaddr = WebUtil::GetInput("emailaddr", FILTER_SANITIZE_EMAIL);
$cs_messageid = WebUtil::GetInput("cs_messageid", FILTER_SANITIZE_INT);
$subject = WebUtil::GetInput("subject", FILTER_SANITIZE_STRING_ONE_LINE);



if(!CustService::CheckIfCSitemExists($dbCmd, $csthreadid))
	throw new Exception("The Customer Service item does not exist: $csthreadid");

$domainIDofCustServiceID = CustService::getDomainIDfromCSitem($dbCmd, $csthreadid);
Domain::enforceTopDomainID($domainIDofCustServiceID);
	
// Keep a record of the visit to this page by the user.
NavigationHistory::recordPageVisit($dbCmd, $UserID, "CSreply", $csthreadid);

$t = new Templatex(".");


$t->set_file("origPage", "ad_cs_email-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


$domainIDofCsItem = CustService::getDomainIDfromCSitem($dbCmd, $csthreadid);
	



$domainEmailConfigObj = new DomainEmails($domainIDofCsItem);
$from_email = $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::CUSTSERV);
$from_name = $domainEmailConfigObj->getEmailNameOfType(DomainEmails::CUSTSERV);


// If this variable comes in the URL then it means that the page is reloading itself... so insert into the DB.
if(!empty($save)){

	if(!WebUtil::ValidateEmail($emailaddr))
		throw new Exception("Error with Email");


	WebUtil::checkFormSecurityCode();


	$mysql_timestamp = date("YmdHis", time());


	// Scape off the orginal message using patter matching... looking for ------ Original Message -----
	$body_for_DB = CustService::GetOriginalMessage($body);
	
	
	// Look for the special Variable within the Body called {DOMAIN_URL}
	// In our Canned messages we don't restrict messages by domains.
	$body_for_DB = preg_replace("/\{DOMAIN_URL\}/", WebUtil::htmlOutput(Domain::getWebsiteURLforDomainID($domainIDofCsItem)), $body_for_DB);
	$body_for_DB = preg_replace("/\{DOMAIN\}/", WebUtil::htmlOutput(Domain::getDomainKeyFromID($domainIDofCsItem)), $body_for_DB);


	// Now put the message into the DB.... associated with the CSItemID
	$insertArr["FromUserID"] = $UserID;
	$insertArr["ToUserID"] = 0;
	$insertArr["csThreadID"] = $csthreadid;
	$insertArr["CustomerFlag"] = "N";
	$insertArr["FromName"] = $from_name;
	$insertArr["FromEmail"] = $from_email;
	$insertArr["ToName"] = $customername;
	$insertArr["ToEmail"] = $emailaddr;
	$insertArr["Message"] = $body_for_DB;
	$insertArr["DateSent"] = $mysql_timestamp;
	$dbCmd->InsertQuery("csmessages",  $insertArr);



	$updateArr["Status"] = "C";

	$updateArr["CustomerEmail"] = $emailaddr;
	$updateArr["LastActivity"] = $mysql_timestamp;
	$updateArr["Subject"] = $subject;
	$dbCmd->UpdateQuery("csitems", $updateArr, "ID=$csthreadid");



	if(!Constants::GetDevelopmentServer()){

		//This is a list of file names that have been uploaded in the same session (for the curent CS web mail reply)
		$AttachmentListArray = CustService::GetAttachmentsInSession($cs_messageid);


		//Create a Mime message... it maybe a multi part if there are attachments
		$MimeObj = new Mail_mime();
		$MimeObj->setTXTBody($body);
		
		foreach($AttachmentListArray as $ThisFileName){

				$FileAttachmentOnDisk = Constants::GetFileAttachDirectory() . "/" . $ThisFileName;
				if(file_exists($FileAttachmentOnDisk)){
				
					if(!$MimeObj->addAttachment($FileAttachmentOnDisk, 'application/octet-stream', CustService::StripPrefixFromCustomerServiceAttachment($ThisFileName))){
						print "The message was not sent.  There was a problem adding the attachment: " . $FileAttachmentOnDisk;
						exit;
					}
					
					#-- Now load the attachment off of disk and try to record it for our records as an attacment for Customer service --#
					$fd = fopen ($FileAttachmentOnDisk, "r");
					$AttachmentBinData = fread ($fd, filesize ($FileAttachmentOnDisk));
					fclose ($fd);

					$AttachID = CustService::InsertCSattachment($dbCmd, $csthreadid, CustService::StripPrefixFromCustomerServiceAttachment($ThisFileName), $AttachmentBinData);
				}
		}


		$customername = preg_replace("/,/", "", $customername);
		$customername = preg_replace("/;/", "", $customername);
		$customername = preg_replace("/>/", "", $customername);
		$customername = preg_replace("/</", "", $customername);


		// The subject of the message should always have the CS thread ID in it --#
		$subjectForEmail = $subject . "  -- msg[$csthreadid]";

		
		$MimeObj->setSubject($subjectForEmail);
		$MimeObj->setFrom("$from_name <$from_email>");
		
		$hdrs = array(
			      'From'    => "$from_name <$from_email>",
			      'Subject' => $subjectForEmail
			      );

		$body = $MimeObj->get();
		$hdrs = $MimeObj->headers($hdrs);
		
		
		// Change the headers and return envelope information for the SendMail command.
		// We don't want emails from different domains to look like they are coming from the same mail server.
		$hdrs["Message-Id"] =  "<" . substr(md5(uniqid(microtime())), 0, 15) . "@" . Domain::getDomainKeyFromID($domainIDofCsItem) . ">";
		$additionalSendMailParameters = "-r " . $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::CUSTSERV);

		$mail = new Mail();
		$mail->send("$customername <$emailaddr>", $hdrs, $body, $additionalSendMailParameters);


	}
	
	// This will check and see if the CS item is NOT associated with anyting
	// If it is not then we may find an order number in the Message and be able to associate by that
	CustService::PossiblyAssociateReferenceWithCSitem($dbCmd, $csthreadid, $body_for_DB, "");


	#-- Erase the block of HTML for writing the email  ---##
	$t->set_block("origPage","createBL","createBLout");
	$t->set_var(array("createBLout"=>"<script>window.opener.document.location = window.opener.document.location; self.close(); </script>"));  //Make the windo close itself



}
else{

	$CSitemInfoHash = CustService::GetCSitem($dbCmd, $csthreadid);

	// Get a hash containing the information of the message thread --#
	$MessageHash = CustService::GetMessagesFromCSitem($dbCmd, $csthreadid);


	$subject = $CSitemInfoHash["Subject"];
	
	$GreetingName = $CSitemInfoHash["CustomerName"];

	// Try to get just the first name of the person --#
	$matches = array();
	if(preg_match_all("/^(\w+)\s/", $GreetingName, $matches))
		$GreetingName = $matches[1][0];
		
		

	$domainLogoObj = new DomainLogos($CSitemInfoHash["DomainID"]);
	$domainLogoImg = "<img alt='".Domain::getDomainKeyFromID($CSitemInfoHash["DomainID"])."'  align='absmiddle' src='./domain_logos/$domainLogoObj->verySmallIcon' border='0'>";
	$t->set_var("LOGO_SMALL", $domainLogoImg);
	$t->allowVariableToContainBrackets("LOGO_SMALL");

	// Make sure that the first letter is the only one capitalized --#
	$GreetingName = strtolower($GreetingName);
	$FirstLetterofName = substr($GreetingName, 0, 1);
	$RestOfName = substr($GreetingName, 1);
	$GreetingName = strtoupper($FirstLetterofName) . $RestOfName;

	$EmailMessage = "Hi " . $GreetingName . ",\n\n\n\n";


	$EmailMessage .= "Thank You,\n" . UserControl::GetNameByUserID($dbCmd, $UserID) . "\n" . $domainEmailConfigObj->getEmailNameOfType(DomainEmails::CUSTSERV);

	foreach($MessageHash as $MessageDetail){

		// If it is a customer that sent the message then we can show their email address in the header --#
		if($MessageDetail["CustomerFlag"] == "Y"){
			$To_desc = "Customer Service";
			$From_desc = $MessageDetail["FromEmail"];
		}
		else{
			$To_desc = $MessageDetail["ToEmail"];
			$From_desc = $domainEmailConfigObj->getEmailNameOfType(DomainEmails::CUSTSERV);
		}

		$EmailMessage .= "\n\n\n-----Original Message-----\n";
		$EmailMessage .= "From: " .  $From_desc . "\n";
		$EmailMessage .= "Sent: " .  date("l, M d Y g:i A", $MessageDetail["DateSent"]) . "\n";
		$EmailMessage .= "To: " .  $To_desc . "\n\n\n";


		$EmailMessage .= $MessageDetail["Message"];
	}




	$t->set_var(array("CUSTOMER_MESSAGE"=>WebUtil::htmlOutput($EmailMessage)));
	$t->set_var(array("NAME"=>WebUtil::htmlOutput($CSitemInfoHash["CustomerName"])));
	$t->set_var(array("EMAIL"=>WebUtil::htmlOutput($CSitemInfoHash["CustomerEmail"])));
	$t->set_var(array("SUBJECT"=>WebUtil::htmlOutput($subject)));
	$t->set_var(array("CSTHREADID"=>$csthreadid));
	$t->set_var(array("CS_MESSAGE_UNIQUE"=>$csthreadid . time()));  //used for tracking attachments
	

}


$t->pparse("OUT","origPage");



?>
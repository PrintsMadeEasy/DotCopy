<?php

require_once("library/Boot_Session.php");

$dbCmd = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$userID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("VIEW_CUSTOMER_SERVICE"))
	throw new Exception("Permission Denied");

$messageID = WebUtil::GetInput("msgid", FILTER_SANITIZE_INT);
$attachmentID = WebUtil::GetInput("fileid",  FILTER_SANITIZE_INT);
$command = WebUtil::GetInput("command", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

$t = new Templatex(".");
$t->set_file("origPage", "ad_message_attachments-template.html");	

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$message = new Message($dbCmd);	
$message->loadMessageByID($messageID); 

$t->set_var("MESSAGEID", $messageID);

if($command=="list")
{
	$t->set_block("origPage","attachmentBL","attachmentBLout");
	
    if($message->userIsMessageOwner($userID))
		$fileAttachPermissions = true; 
	else
		$fileAttachPermissions = false;
		
	$attachmentIDArr = $message->getAttachmentIDs();
	
	foreach($attachmentIDArr as $attachmentID) {
		
		$t->set_var("MESSAGEID", $messageID);
		$t->set_var("FILEID", $attachmentID);
		$t->set_var("FILENAME", WebUtil::htmlOutput($message->getAttatchmentFileName($attachmentID)));
		$t->set_var("ADDDATE", date("M j, Y g:i a",$message->getAttachmentDate($attachmentID)));
		
		$filesize = $message->getAttatchmentFileSize($attachmentID);
		
		$kbFileSize = round(($filesize / 1024), 1);
		$mbFileSize = round(($filesize / 1024 / 1024), 1);

		if($mbFileSize > 0.4)
			$t->set_var("FILESIZE", $mbFileSize . " MB");
		else
			$t->set_var("FILESIZE", $kbFileSize . " KB");
		
			
		if(!$fileAttachPermissions)
			$t->discard_block("attachmentBL", "deleteBL");
			
		$t->parse("attachmentBLout","attachmentBL",true);
	}	


	if(empty($attachmentIDArr)) {
		$t->set_block("origPage","EmptyAttachmentBL","EmptyAttachmentBLout");
		$t->set_var(array("EmptyAttachmentBLout"=>"<font class='LargeBody'>No Attachments Yet<br></font>"));
	}
	
	if(!$fileAttachPermissions) 
		$t->discard_block("origPage", "UploadBL");
		
	$t->discard_block("origPage", "confirmUploadBL");
	$t->discard_block("origPage", "errorUploadBL");
	$t->discard_block("origPage", "deleteUploadBL");

}
else if($command=="upload") {
	
	WebUtil::checkFormSecurityCode();
	
	if(!isset($_FILES["attachedfile"]))
		throw new Exception("The attachment name did not come in.");
	
	if(!isset($_FILES["attachedfile"]["name"]) || empty($_FILES["attachedfile"]["name"])){
		$t->set_var("ERROR_MSG", "You forgot to chose a file for uploading.");
		$t->discard_block("origPage", "confirmUploadBL");
		
	}
	else if(!FileUtil::CheckIfFileNameIsLegal($_FILES["attachedfile"]["name"])) {
		$t->set_var("ERROR_MSG", "This type of file may not be uploaded for security reasons.");
		$t->discard_block("origPage", "confirmUploadBL");
	}
	else 
	{		
		$fileBinaryData = file_get_contents($_FILES["attachedfile"]['tmp_name']);
		if(empty($fileBinaryData))
			throw new Exception("Error fetching the Message Attachment from disk.");

		$newAttachmentID = $message->addAttachment($fileBinaryData, $_FILES["attachedfile"]["name"]);	

		$t->discard_block("origPage", "errorUploadBL");		
	}			
	
	$t->discard_block("origPage", "EmptyAttachmentBL");
	$t->discard_block("origPage", "UploadBL");
	$t->discard_block("origPage", "deleteUploadBL");
	
}
else if($command=="dnload") {
  
	$secureFileNameForAttachment = $message->getSecuredAttachmentFileName($attachmentID);
	$pathToFileOnDisk = Constants::GetFileAttachDirectory() . "/" . $secureFileNameForAttachment;
	
	// Don't write the binary data to disk, unless it is necessary. A background script cleans out old temp file attachments periodically.
	if(!file_exists($pathToFileOnDisk))
		file_put_contents($pathToFileOnDisk, $message->getAttachmentBinaryData($attachmentID));
	
	header('Location: ' . WebUtil::FilterURL("./customer_attachments/$secureFileNameForAttachment"));
}
else if($command=="delete") {

	WebUtil::checkFormSecurityCode();
	
	$message->removeAttachment($attachmentID);

	$t->discard_block("origPage", "EmptyAttachmentBL");
	$t->discard_block("origPage", "UploadBL");
	$t->discard_block("origPage", "confirmUploadBL");
	$t->discard_block("origPage", "errorUploadBL");
}
else{
	
	throw new Exception("No command sent to message attachments.");
}

$t->pparse("OUT","origPage");


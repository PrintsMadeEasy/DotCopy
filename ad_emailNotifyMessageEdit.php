<?php

require_once("library/Boot_Session.php");


$action    = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$messageId = WebUtil::GetInput("messageid", FILTER_SANITIZE_INT);

$dbCmd = new DbCmd();

$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$userID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("EMAIL_NOTIFY_MESSAGE_EDIT"))
		throw new Exception("Permission denied ");

if(!empty($action)){
	
	if($action == "save") {
				
		WebUtil::checkFormSecurityCode();
		
		$body = WebUtil::GetInput("body",  FILTER_UNSAFE_RAW);
		$subject = WebUtil::GetInput("subject",  FILTER_SANITIZE_STRING);
		$active = WebUtil::GetInput("active",  FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
		$ishtml = WebUtil::GetInput("ishtml",  FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
		$updateType = WebUtil::GetInput("update_type", FILTER_SANITIZE_INT);
		$fromname   = trim(WebUtil::GetInput("fromname", FILTER_SANITIZE_STRING));
		$fromemail  = trim(WebUtil::GetInput("fromemail", FILTER_SANITIZE_STRING));
		
		
		$startday   = WebUtil::GetInput("startday", FILTER_SANITIZE_INT);
		$startmonth = WebUtil::GetInput("startmonth", FILTER_SANITIZE_INT);
		$startyear  = WebUtil::GetInput("startyear", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
		
		$endday     = WebUtil::GetInput("endday", FILTER_SANITIZE_INT);
		$endmonth   = WebUtil::GetInput("endmonth", FILTER_SANITIZE_INT);
		$endyear    = WebUtil::GetInput("endyear", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
	
		$substituteYearALL = EmailNotifyMessages::getSubstituteYearALL();
		
		if($endyear=="ALL")
			$endyear = $substituteYearALL;
		
		if($startyear=="ALL")
			$startyear = $substituteYearALL;
			
		$datefrom   = mktime(0,0,0,$startmonth,$startday,$startyear);
		$dateto     = mktime(0,0,0,$endmonth,$endday,$endyear);
		
		if($datefrom > $dateto)
			$dateto = $datefrom;
			 			
		if(empty($updateType))
			exit;
		
		$emailNotifyMessageObj = new EmailNotifyMessages();
				
		if($updateType == 1)
			$emailNotifyMessageObj->loadMessageByID($messageId);
	
		$emailNotifyMessageObj->setActive($active == "active" ? true : false);
		$emailNotifyMessageObj->setTypeHTML($ishtml == "html"   ? true : false);  // we only send HTML pages, we can't track etc. with text/plain

		$emailNotifyMessageObj->setMessage($body);
		$emailNotifyMessageObj->setSubject($subject);
		$emailNotifyMessageObj->setUserID($userID);
		
		$emailNotifyMessageObj->setFromName($fromname);
		$emailNotifyMessageObj->setFromEmail($fromemail);
		
		$emailNotifyMessageObj->setDateRange($datefrom,$dateto);
		
		if($updateType == 2) 
			$messageId = $emailNotifyMessageObj->saveNewMessage();
		else 
			$emailNotifyMessageObj->updateMessage();
					
		header("Location: ./ad_emailNotifyMessageEdit.php?view=edit&messageid=$messageId");
	}
	else if($action == "upload"){	
		
		WebUtil::checkFormSecurityCode();
		
		$emailNotifyMessageObj = new EmailNotifyMessages();
		
		if(!isset($_FILES["attachedfile"]))
			throw new Exception("The attachment name did not come in.");
		
		if(!isset($_FILES["attachedfile"]["name"]) || empty($_FILES["attachedfile"]["name"])){
		
		}
		else if(!FileUtil::CheckIfFileNameIsLegal($_FILES["attachedfile"]["name"])) {

		}
		else 
		{		
			$fileBinaryData = file_get_contents($_FILES["attachedfile"]['tmp_name']);
			if(empty($fileBinaryData))
				throw new Exception("Error fetching the Message Attachment from disk.");
	
			$ImageFormat = ImageLib::GetImageFormatFromFile($_FILES["attachedfile"]['tmp_name']);

			if(!in_array($ImageFormat, array("JPEG", "PNG", "GIF")))
				throw new Exception("Illegal File Type for image upload.");
				
			EmailNotifyMessages::uploadPicture($messageId, $fileBinaryData, $_FILES["attachedfile"]["name"]);	
		}		
		
		header('Location: ' . WebUtil::FilterURL("./ad_emailNotifyMessageEdit.php?view=edit&messageid=$messageId"));
	}
	else if($action == "deletepic") {	
		
		$pictureId = WebUtil::GetInput("picid", FILTER_SANITIZE_INT);
		
		EmailNotifyMessages::deletePicture($pictureId);
		
		header('Location: ' . WebUtil::FilterURL("./ad_emailNotifyMessageEdit.php?view=edit&messageid=$messageId"));
	}
	else if($action == "sendemail") {	
		
		WebUtil::checkFormSecurityCode();

		$domainIDofUser = UserControl::getDomainIDofUser($userID);
		$domainEmailConfigObj = new DomainEmails($domainIDofUser);
		
		$emailFrom = $domainEmailConfigObj->getEmailNameOfType(DomainEmails::REMINDER);
		$headers = "";
		
		$emailNotifyMessageObj = new EmailNotifyMessages();
		$emailNotifyMessageObj->loadMessageByID($messageId);
	
		$body    = $emailNotifyMessageObj->getEmailSourceWithInline($domainIDofUser);
		$headers = $emailNotifyMessageObj->getEmailHeaders();
		
		$userEmail = UserControl::GetEmailByUserID($dbCmd, $userID);
		if(Constants::GetDevelopmentServer())
			$userEmail = "christian@asynx.com";
							
		$mailObj = new Mail();
		$test = $mailObj->send($userEmail, $headers, $body); 
		unset($mailObj);

		header('Location: ' . WebUtil::FilterURL("./ad_emailNotifyMessageEdit.php?view=edit&messageid=$messageId"));
	}
	else{
		throw new Exception("Illegal Action");		
	}
}

$t = new Templatex(".");
$t->set_file("origPage", "ad_emailNotifyMessageEdit-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());





$view = WebUtil::GetInput("view", FILTER_SANITIZE_STRING_ONE_LINE);

if(empty($messageId))
	$view = "edit";	

if($view == "edit"){
	
	$t->allowVariableToContainBrackets("START_MONTH_SELECT");
	$t->allowVariableToContainBrackets("START_YEAR_SELECT");
	$t->allowVariableToContainBrackets("END_MONTH_SELECT");
	$t->allowVariableToContainBrackets("END_YEAR_SELECT");
	$t->allowVariableToContainBrackets("BODY");
	
	$t->set_var("UPDATE_TYPE","2"); // New Message by default
	$t->set_var("SUBJECT", "");
	$t->set_var("BODY", "");
	$t->set_var("ACTIVE","");	
	$t->set_var("ISHTML","checked");
	$t->set_var("LAST_EDITED", "NEW");
	$t->set_var("FROMNAME", "");
	$t->set_var("FROMEMAIL", "");
	
	$t->set_var("DATEFROM", "");
	$t->set_var("DATETO", "");
		
	// Default Values: Jan 1. ALL - Dec 31. ALL
	
	$startdate["mday"] = 1;
	$startdate["mon"]  = 1;
	$startdate["year"] = "ALL";
	$enddate["mday"]   = 31;
	$enddate["mon"]    = 12;
	$enddate["year"]   = "ALL";
	
	
	if(empty($messageId))
		$domainIDfromMessage = Domain::oneDomain();
	else
		$domainIDfromMessage = EmailNotifyMessages::getDomainIdFromMessageID($messageId);
		
	$emailNotifyMessageObj = new EmailNotifyMessages($domainIDfromMessage);


	Domain::enforceTopDomainID($domainIDfromMessage);
			
	$domainId = $emailNotifyMessageObj->getDomainId();	
	$domainLogoObj = new DomainLogos($domainId);
	$domainLogo =  $domainLogoObj->verySmallIcon;	
	$t->set_var("DOMAIN_LOGO", $domainLogo);
				


	if(!empty($messageId)) {
		
		$emailNotifyMessageObj->loadMessageByID($messageId);
		
		$t->set_var("UPDATE_TYPE","1"); // Update Message
		$t->set_var("MESSAGEID", $messageId);
		$t->set_var("SUBJECT", $emailNotifyMessageObj->getSubject());
		$t->set_var("BODY", $emailNotifyMessageObj->getMessage());
		
		
		$t->set_var("FROMNAME", $emailNotifyMessageObj->getFromName());
		$t->set_var("FROMEMAIL", $emailNotifyMessageObj->getFromEmail());
		
		
		$t->set_var("LAST_EDITED", "Last Edited on " . date("M j, Y g:i a",$emailNotifyMessageObj->getLastEditedDate()));
		
		$startdate = getdate( $emailNotifyMessageObj->getDateRangeStart()); // + 86400);	
		$enddate   = getdate( $emailNotifyMessageObj->getDateRangeEnd());   // + 86400);
		
		$substituteYearALL = EmailNotifyMessages::getSubstituteYearALL();
		
		if($startdate["year"]==$substituteYearALL)
			$startdate["year"] = "ALL";
		
		if($enddate["year"]==$substituteYearALL)
			$enddate["year"] = "ALL";	
			
		if($emailNotifyMessageObj->getIsActive())
			$t->set_var("ACTIVE","checked");
			
		if($emailNotifyMessageObj->getIsHTML())
			$t->set_var("ISHTML","checked");
							
		// ad_emailNotifyMessageEdit.php show a Red Alert if there are any src="http:{\w+}" or href="http:{\w+}" that does not match the Domain URL ID 
		if(!(preg_match("/(http|ftp|https):\/\/[\w\-_]+(\.[\w\-_]+)?/", $emailNotifyMessageObj->getMessage())))				
			$t->discard_block("origPage", "HttpLinkAlertBL");
						
		$pictureIdArr = $emailNotifyMessageObj->getMessagePictureIds();

		if(empty($pictureIdArr))
			$t->discard_block("origPage", "showPicturesBL");
		else	
			$t->set_block("origPage","showPicturesBL","showPicturesBLout");	
		
		foreach($pictureIdArr as $pictureId) {
			
			$t->set_var("WIDTH",  EmailNotifyMessages::getPictureWidth($pictureId));
			$t->set_var("HEIGHT", EmailNotifyMessages::getPictureHeight($pictureId));
			$t->set_var("PICTUREID", $pictureId);
			$t->set_var("PICTURELINK", EmailNotifyMessages::getSecuredPictureLink($pictureId,true));
			
			if(preg_match("/{Picture" . $pictureId . "}/", $emailNotifyMessageObj->getMessage())) 
				$t->set_var("PICTUREINTEXT","OK");
			else	
				$t->set_var("PICTUREINTEXT","Missing");
			
			$t->parse("showPicturesBLout","showPicturesBL",true);
		}	
		
		if(!preg_match("/{TRACK}/", $emailNotifyMessageObj->getMessage()))	
			$t->discard_block("origPage", "TrackImageOKBL");
		 else	
			$t->discard_block("origPage", "TrackImageMissingBL");
			
		if(!preg_match("/{UNSUBSCRIBE}/", $emailNotifyMessageObj->getMessage()))	
			$t->discard_block("origPage", "UnsubscribeOKBL");
		 else	
			$t->discard_block("origPage", "UnsubscribeMissingBL");	
			
		if(!preg_match("/{ORDERLINK}/", $emailNotifyMessageObj->getMessage()))	
			$t->discard_block("origPage", "OrderLinkOKBL");
		 else	
			$t->discard_block("origPage", "OrderLinkMissingBL");	
			
	}
	else {
		$t->discard_block("origPage", "UploadBL");
		$t->discard_block("origPage", "showPicturesBL");
		$t->discard_block("origPage", "TrackImageOKBL");
		$t->discard_block("origPage", "TrackImageMissingBL");
		$t->discard_block("origPage", "UnsubscribeOKBL");
		$t->discard_block("origPage", "UnsubscribeMissingBL");		
		$t->discard_block("origPage", "OrderLinkOKBL");
		$t->discard_block("origPage", "OrderLinkMissingBL");	
		$t->discard_block("origPage", "HttpLinkAlertBL");	
	}
	
	$t->set_var("START_DAY", $startdate["mday"]);	
	$t->set_var("START_MONTH_SELECT",Widgets::BuildMonthSelect( $startdate["mon"], "startmonth", "" ));				
	$t->set_var("START_YEAR_SELECT",Widgets::BuildFutureYearSelectWithAllChoice( $startdate["year"],  "startyear", "" ));	
	
	$t->set_var("END_DAY", $enddate["mday"]);	
	$t->set_var("END_MONTH_SELECT",Widgets::BuildMonthSelect( $enddate["mon"],"endmonth", "" ));	
	$t->set_var("END_YEAR_SELECT",Widgets::BuildFutureYearSelectWithAllChoice( $enddate["year"],  "endyear", "" ));	

}
else{
	
	
	
	$t->discard_block("origPage", "SavedMessageBL");
	
	$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());
}

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->pparse("OUT","origPage");

<?php

require_once("library/Boot_Session.php");

$dbCmd = new DbCmd();

$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$userID = $AuthObj->GetUserID();

$domainObj = Domain::singleton();

// Don't make it look like we are favoring a particular Domain 
Domain::removeTopDomainID();

if(!$AuthObj->CheckForPermission("EMAIL_NOTIFY_MESSAGE_VIEW"))
	throw new Exception("Permission denied.");

$t = new Templatex(".");
$t->set_file("origPage", "ad_emailNotifyMessageList-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->allowVariableToContainBrackets("PICTURELINK_0");
$t->allowVariableToContainBrackets("PICTURELINK_1");
$t->allowVariableToContainBrackets("PICTURELINK_2");


$t->set_block("origPage","EmailLinkBL","EmailLinkBLout");

$radioButtonActive = WebUtil::GetInput("active", FILTER_SANITIZE_INT);

if(empty($radioButtonActive))
	$radioButtonActive = WebUtil::GetCookie("EmailNotifyListActive");

// No cookie yet
if(empty($radioButtonActive))
	$radioButtonActive = 1;
	
WebUtil::SetCookie("EmailNotifyListActive", $radioButtonActive);
	
if($radioButtonActive==1) 
	$t->set_var("RADIOACTIVE_1", "checked"); 
else 
	$t->set_var("RADIOACTIVE_1", "");

if($radioButtonActive==2) 
	$t->set_var("RADIOACTIVE_2", "checked"); 
else 
	$t->set_var("RADIOACTIVE_2", "");
	
if($radioButtonActive==3) 
	$t->set_var("RADIOACTIVE_3", "checked"); 
else 
	$t->set_var("RADIOACTIVE_3", "");

$hasMessages = false;		

$t->allowVariableToContainBrackets("START_MONTH_SELECT");
$t->allowVariableToContainBrackets("START_YEAR_SELECT");
$t->allowVariableToContainBrackets("END_MONTH_SELECT");
$t->allowVariableToContainBrackets("END_YEAR_SELECT");

$startday   = WebUtil::GetInput("startday", FILTER_SANITIZE_INT);
$startmonth = WebUtil::GetInput("startmonth", FILTER_SANITIZE_INT);
$startyear  = WebUtil::GetInput("startyear", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$endday     = WebUtil::GetInput("endday",   FILTER_SANITIZE_INT);
$endmonth   = WebUtil::GetInput("endmonth", FILTER_SANITIZE_INT);
$endyear    = WebUtil::GetInput("endyear",  FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

if(empty($startyear) || empty($startmonth) || empty($startday)) {		
	
	$currentStartDate = getdate(WebUtil::GetCookie("EmailNotifyListStartDate"));
	$currentEndDate   = getdate(WebUtil::GetCookie("EmailNotifyListEndDate"));

	if($currentStartDate["year"]<2000)
	{
		$currentStartDate = getdate(time());
		$currentEndDate   = getdate(time());	
	}

	$startday    = $currentStartDate["mday"]; 
	$startmonth  = $currentStartDate["mon"];  
	$startyear   = $currentStartDate["year"]; 
	$endday      = $currentEndDate["mday"]; 
	$endmonth    = $currentEndDate["mon"];  
	$endyear     = $currentEndDate["year"]; 	
}

$t->set_var("START_DAY", $startday);	
$t->set_var("START_MONTH_SELECT",Widgets::BuildMonthSelect( $startmonth, "startmonth", "" ));				
$t->set_var("START_YEAR_SELECT",Widgets::BuildFutureYearSelectWithAllChoice( $startyear,  "startyear", "" ));		
$t->set_var("END_DAY", $endday);	
$t->set_var("END_MONTH_SELECT",Widgets::BuildMonthSelect( $endmonth,"endmonth", "" ));	
$t->set_var("END_YEAR_SELECT",Widgets::BuildFutureYearSelectWithAllChoice($endyear,  "endyear", "" ));	

$dateRangeStart = NULL; 
if($startyear!="ALL") 
	$dateRangeStart = mktime(0,0,0,$startmonth,$startday,$startyear);
	
$dateRangeEnd = NULL;		
if($endyear!="ALL") 	
	$dateRangeEnd = mktime(0,0,0,$endmonth,$endday,$endyear);	

if($dateRangeStart > $dateRangeEnd)
	$dateRangeEnd = $dateRangeStart;
	
WebUtil::SetCookie("EmailNotifyListStartDate", $dateRangeStart);	
WebUtil::SetCookie("EmailNotifyListEndDate",   $dateRangeEnd);	
	
$messageIdArr = EmailNotifyMessages::getMessageListIds($domainObj->getSelectedDomainIDs(), $dateRangeStart, $dateRangeEnd, $radioButtonActive);	
	
foreach($messageIdArr AS $messageID) {

	$emailNotifyMessageObj = new EmailNotifyMessages(EmailNotifyMessages::getDomainIdFromMessageID($messageID));
	$emailNotifyMessageObj->loadMessageByID($messageID);
				
	$hasMessages = true;	
		
	$domainId = $emailNotifyMessageObj->getDomainId();	
	$domainLogoObj = new DomainLogos($domainId);
	$domainLogo =  $domainLogoObj->verySmallIcon;	
		
	$t->set_var("MESSAGE_ID", $messageID);
	$t->set_var("DOMAIN_LOGO", $domainLogo);
	$t->set_var("MESSAGE_SUBJECT", substr($emailNotifyMessageObj->getSubject(),0,60));
	$t->set_var("MESSAGE_CREADATE", date("M j, Y g:i a",$emailNotifyMessageObj->getCreationDate())); 
	
	$lastUsedDate = $emailNotifyMessageObj->getLastUsedDate();
	if(!empty($lastUsedDate))
		$t->set_var("MESSAGE_LASTUSEDDATE", date("M j, Y g:i a", $lastUsedDate)); 
	else 	
		$t->set_var("MESSAGE_LASTUSEDDATE", "Not used"); 
		
	$clickCount = intval($emailNotifyMessageObj->getClickCountOfMessage());
	$orderCount = intval($emailNotifyMessageObj->getOrderCountOfMessage());
	
	$conversionRate = "0.00%";
	if($clickCount>0)
		$conversionRate = number_format($clickCount / $orderCount / 100 , 2)." %";  
		
	$t->set_var("JOB_COUNT",   intval($emailNotifyMessageObj->getJobCountOfMessage()));
	$t->set_var("CLICK_COUNT", $clickCount);
	$t->set_var("ORDER_COUNT", $orderCount);
	$t->set_var("CONV_RATE",   $conversionRate);
	
	
	$t->set_var("EMAIL_COUNT", 	 intval($emailNotifyMessageObj->getEmailCountOfMessage()));
	$t->set_var("TRACKING_COUNT",intval($emailNotifyMessageObj->getTrackingCountOfMessage()));
	
	$pictureIdArr = $emailNotifyMessageObj->getMessagePictureIds();
	
	$pictureCount = 0;
	foreach($pictureIdArr as $pictureId) {
	
		if($pictureCount<3)	
			$t->set_var(("PICTURELINK_" . $pictureCount ), "<img src=./customer_attachments/" . EmailNotifyMessages::getSecuredPictureLink($pictureId,true) . "> ");
	
		$pictureCount++;	
	}

	if($pictureCount<3) 	
		$t->set_var("PICTURELINK_2","");
	
	if($pictureCount<2) 		
		$t->set_var("PICTURELINK_1","");
	
	if($pictureCount<1)
		$t->set_var("PICTURELINK_0","");
		
	$t->parse("EmailLinkBLout","EmailLinkBL",true);	
}

if(!$hasMessages)
	$t->set_var("EmailLinkBLout","");

if(empty($messageIdArr))
	$t->set_var("EmailLinkBLout", "No Messages");

Widgets::buildTabsForEmailNotify($t, "messages");	
	
$t->pparse("OUT","origPage");

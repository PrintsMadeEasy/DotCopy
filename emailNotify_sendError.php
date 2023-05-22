<?php
	
require_once("library/Boot_Session.php");

$emailHistoryId  = WebUtil::GetInput("id", FILTER_SANITIZE_INT);

if(Domain::getDomainIDfromURL() != EmailNotifyJob::getDomainIdOfHistoryId($emailHistoryId))
	throw new Exception("DomainID doesn't match");

$email  = WebUtil::GetInput("email", FILTER_SANITIZE_EMAIL);

if($email != EmailNotifyJob::getEmailOfHistoryId($emailHistoryId))
	throw new Exception("Email doesn't match");	

$error  = WebUtil::GetInput("error", FILTER_SANITIZE_INT);
	
EmailNotifyCollection::addEmailSendingError($email,$error,$emailHistoryId);
	
?>
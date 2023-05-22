<?php

// http://localhost/dot/ad_shippingChoices.php

require_once("library/Boot_Session.php");

// Don't make it look like we are favoring a particular Domain 
Domain::removeTopDomainID();

set_time_limit(9000);
ini_set("memory_limit", "512M");

$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

$dbCmd = new DbCmd();

$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("EMAIL_NOTIFY_EMAIL_ADDRESS_BATCHES"))
	throw new Exception("Permission Denied");


if(!empty($action)){
	if($action == "addemails") {
		
		$emailObj = new EmailNotifyCollection();

		WebUtil::checkFormSecurityCode();
		
		$emaillist = WebUtil::GetInput("emaillist", FILTER_UNSAFE_RAW);
		$description = WebUtil::GetInput("description", FILTER_SANITIZE_STRING_ONE_LINE);
		
		$emailObj = new EmailNotifyCollection();
		$emailObj->setSourceType(WebUtil::GetInput("sourcetype", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES));
		
		if($emailObj->addBatchToDatabase($emaillist, $description)) {
				
			$batchId     = $emailObj->getLastImportedBatchId();
			$domainObj   = Domain::singleton();	
			$domainIdArr = $domainObj->getSelectedDomainIDs();
			
			foreach($domainIdArr AS $domainId)
				$emailObj->setInitialBatchDomain($batchId, $domainId);

			header("Location: ./ad_emailCollection.php?view=emailsSavedNotice&countTotalSubmitted=" . $emailObj->getCountTotalSubmitted() . "&countTotalAdded=" . $emailObj->getCountTotalAdded() . "&countDuplicateInBatch=" . $emailObj->getCountDuplicateInBatch());
		
		} else {

			$errorDescription = $emailObj->getLastImportErrorDescription();
			preg_replace("/ /",$errorDescription,"%20");
			header("Location: ./ad_emailCollection.php?view=importError&errorDesc=" . $errorDescription);
		}
			
		exit;
	}
	else {	
		throw new Exception("Illegal Action");		
	}
}


$t = new Templatex(".");
$t->set_file("origPage", "ad_emailCollection-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$view = WebUtil::GetInput("view", FILTER_SANITIZE_STRING_ONE_LINE);

$t->set_var("SOURCE_TYPE", Widgets::buildSelect(array("B"=>"Bulk", "D"=>"Internal Domain"),"B"));
$t->allowVariableToContainBrackets("SOURCE_TYPE");


if($view == "emailsSavedNotice"){
	
	$t->set_var("COUNT_SUMBITTED",  WebUtil::GetInput("countTotalSubmitted", FILTER_SANITIZE_INT));
	$t->set_var("COUNT_ADDED",WebUtil::GetInput("countTotalAdded", FILTER_SANITIZE_INT));
	$t->set_var("COUNT_DOUBLE", WebUtil::GetInput("countDuplicateInBatch", FILTER_SANITIZE_INT));
	
	$t->discard_block("origPage", "ErrorMessageBL");
}
else if ($view == "importError") {

	$t->set_var("ERROR_DESCRIPTION",  WebUtil::GetInput("errorDesc", FILTER_SANITIZE_STRING_ONE_LINE));
	
	$t->discard_block("origPage", "SavedMessageBL");
}
else{
	
	$t->discard_block("origPage", "SavedMessageBL");
	$t->discard_block("origPage", "ErrorMessageBL");
	
	$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());
}

Widgets::buildTabsForEmailNotify($t, "importemail");

$t->pparse("OUT","origPage");



<?php

// http://localhost/dot/ad_shippingChoices.php

require_once("library/Boot_Session.php");

// Don't make it look like we are favoring a particular Domain 
Domain::removeTopDomainID();

$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

$dbCmd = new DbCmd();

$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("EMAIL_NOTIFY_EMAIL_ADDRESS_BATCHES"))
	throw new Exception("Permission Denied");


if(!empty($action)){
	
	if($action == "update") {
		
		WebUtil::checkFormSecurityCode();
		
		$domainList = WebUtil::GetInput("updatelist", FILTER_UNSAFE_RAW);
		trim($domainList);
		
		$emailObj = new EmailNotifyCollection();

		$emailObj->updateEmailIgnorePatternsList($domainList);
					
		header("Location: ./ad_emailCollectionPatternsToIgnore.php");
	}
	else {	
		throw new Exception("Illegal Action");		
	}
}


$t = new Templatex(".");
$t->set_file("origPage", "ad_emailCollectionPatternsToIgnore-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$emailObj = new EmailNotifyCollection();
$t->set_var("CURRENT_LIST",$emailObj->getEmailIgnorePatternsList());

// Set the Tabs on Email HTML
Widgets::buildTabsForEmailNotify($t, "emailfilters");
	
$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$t->pparse("OUT","origPage");

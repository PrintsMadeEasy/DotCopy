<?php

require_once("library/Boot_Session.php");

$domainID  = Domain::oneDomain();

$action    = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

$dbCmd = new DbCmd();

$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$userID = $AuthObj->GetUserID();

$allowedDomainsArr = $AuthObj->getUserDomainsIDs();
if(!in_array($domainID, $allowedDomainsArr))
	throw new Exception("DomainID not allowed");	

$isProxy = Domain::isDomainRunThroughProxy($domainID);	
	
if(empty($domainID))
	throw new Exception("Illegal DomainID");
			
if(!empty($action)){
	
	if($action == "update") {
	
		WebUtil::checkFormSecurityCode();
	
		$domainEmailsObj = new DomainEmails($domainID);
	
		$fieldArr = $domainEmailsObj->getFieldTypes();

		foreach($fieldArr as $field) {
	
			$domainEmailsObj->updateEmailNameOfType(WebUtil::GetInput(("name_"  . strtolower($field)), FILTER_SANITIZE_STRING_ONE_LINE),$field);
			$domainEmailsObj->updateEmailAddressOfType(WebUtil::GetInput(("email_" . strtolower($field)), FILTER_SANITIZE_EMAIL),$field);
			$domainEmailsObj->updateUserOfType(WebUtil::GetInput(("user_" . strtolower($field)), FILTER_SANITIZE_STRING_ONE_LINE),$field);
			$domainEmailsObj->updateHostOfType(WebUtil::GetInput(("host_" . strtolower($field)), FILTER_SANITIZE_STRING_ONE_LINE),$field);
			$domainEmailsObj->updatePassOfType(WebUtil::GetInput(("pass_" . strtolower($field)), FILTER_SANITIZE_STRING_ONE_LINE),$field);
		}			
			
		header('Location: ' . WebUtil::FilterURL("./ad_domainEmailEdit.php?view=edit"));
	}
	else if($action == "new"){	 // http://localhost/dot/ad_domainEmailEdit.php?view=edit&action=new&domainid=1
		
		$domainEmailsObj = new DomainEmails($domainID);
		$domainEmailsObj->addNewDomain();
		
		header('Location: ' . WebUtil::FilterURL("./ad_domainEmailEdit.php?view=edit"));
	}
	else if($action == "sync"){	 

		$emailId    = WebUtil::GetInput("id", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);	

		$domainEmailsObj = new DomainEmails($domainID);		
		$domainEmailsObj->syncEmail($emailId);

		header('Location: ' . WebUtil::FilterURL("./ad_domainEmailEdit.php?view=edit"));
	}
	else if($action == "add"){	 

		$domainEmailsObj = new DomainEmails($domainID);		
		$domainEmailsObj->addNewAccont();

		header('Location: ' . WebUtil::FilterURL("./ad_domainEmailEdit.php?view=edit"));
	}
	else if($action == "del"){	 
		
		$emailId    = WebUtil::GetInput("id", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);	
	
		$domainEmailsObj = new DomainEmails($domainID);		
		$domainEmailsObj->deleteEmailAccount($emailId);

		header('Location: ' . WebUtil::FilterURL("./ad_domainEmailEdit.php?view=edit"));
	}
	else{
		throw new Exception("Illegal Action");		
	}
}

$t = new Templatex(".");
$t->set_file("origPage", "ad_domainEmailEdit-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");
$t->allowVariableToContainBrackets("SYNCLINK");	
$t->allowVariableToContainBrackets("DELETELINK");	

if(!$isProxy) {
	$t->set_block("origPage","editFieldBL","editFieldBLout");	
	$t->discard_block("origPage","editFieldProxyBL");
	$t->set_var("ADDNEWACCOUNTLINK", "");
} else {
	$t->discard_block("origPage","editFieldBL");
	$t->set_block("origPage","editFieldProxyBL","editFieldProxyBLout");	
	$t->allowVariableToContainBrackets("ADDNEWACCOUNTLINK");	
	$t->set_var("ADDNEWACCOUNTLINK", "<a href='ad_domainEmailEdit.php?action=add'>Add New Account</a>");
}

$domainEmailsObj = new DomainEmails($domainID);

$fieldArr = $domainEmailsObj->getFieldTypes();

$t->set_var("DOMAINID", $domainID);
$t->set_var("DOMAINKEY", $domainKey = Domain::getDomainKeyFromID($domainID));

foreach($fieldArr as $field) {

	$t->set_var("NAMEVALUE", $domainEmailsObj->getEmailNameOfType($field));
	$t->set_var("EMAILVALUE", $domainEmailsObj->getEmailAddressOfType($field));
	$t->set_var("HOSTVALUE", $domainEmailsObj->getHostOfType($field));
	$t->set_var("USERVALUE", $domainEmailsObj->getUserOfType($field));
	$t->set_var("FIELDLABEL", $field);
	$t->set_var("FIELDNAME",  strtolower($field));
	$t->set_var("DELETELINK", "");

	if(!$isProxy) {
		
		$t->set_var("USERVALUE", $domainEmailsObj->getUserOfType($field));
		$t->set_var("PASSVALUE", $domainEmailsObj->getPassOfType($field));	
		$t->parse("editFieldBLout","editFieldBL",true);
	
	} else {
		
		$result = $domainEmailsObj->getUserPassCheckOfType($field);

		$t->set_var("PASSVALUE", $domainEmailsObj->getPassOfType($field));	
		
		if($result=="YES") {
			$t->set_var("SYNCTEXT",  "Synced");
			$t->set_var("SYNCLINK",  "");
		}

		if($result=="NOT") {
			$t->set_var("SYNCTEXT",  "Not synced");
			if($domainEmailsObj->getPassOfType($field)!="")
				$t->set_var("SYNCLINK",  "<a href='ad_domainEmailEdit.php?action=sync&id=" . $domainEmailsObj->getEmailIDOfType($field). "'>Sync</a>");
			else 
				$t->set_var("SYNCLINK",  "");	
		}

		if($result=="NOH") {
			$t->set_var("SYNCTEXT",  "");
			$t->set_var("SYNCLINK",  "");
		}
		
		if(substr(strtoupper($field),0,6)=="EMAIL-")
			$t->set_var("DELETELINK",  "<a href='ad_domainEmailEdit.php?action=del&id=" . $domainEmailsObj->getEmailIDOfType($field). "'>Delete</a>");
	
		$t->parse("editFieldProxyBLout","editFieldProxyBL",true);
	}
}	


$t->pparse("OUT","origPage");

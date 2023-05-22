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
	

if(!$AuthObj->CheckForPermission("EDIT_PRODUCT"))
		throw new Exception("You don't have permission to domain addresses.");
	
if(!empty($action)){
	
	if($action == "update") {
	
		WebUtil::checkFormSecurityCode();
	
		if(empty($domainID))
			throw new Exception("Illegal DomainID");

		$domainAddressObj = new DomainAddresses($domainID);
	
		$addressTypesArr = $domainAddressObj->getAddressTypes();

		foreach($addressTypesArr as $addressType) {
	
			$domainAddressObj->updateFieldOfType("Attention",WebUtil::GetInput(("attention_"  . strtolower($addressType)), FILTER_SANITIZE_STRING_ONE_LINE),$addressType);
			$domainAddressObj->updateFieldOfType("Company",WebUtil::GetInput(("company_"  . strtolower($addressType)), FILTER_SANITIZE_STRING_ONE_LINE),$addressType);
			$domainAddressObj->updateFieldOfType("AddressOne",WebUtil::GetInput(("addressone_"  . strtolower($addressType)), FILTER_SANITIZE_STRING_ONE_LINE),$addressType);
			$domainAddressObj->updateFieldOfType("AddressTwo",WebUtil::GetInput(("addresstwo_"  . strtolower($addressType)), FILTER_SANITIZE_STRING_ONE_LINE),$addressType);
			$domainAddressObj->updateFieldOfType("City",WebUtil::GetInput(("city_"  . strtolower($addressType)), FILTER_SANITIZE_STRING_ONE_LINE),$addressType);
			$domainAddressObj->updateFieldOfType("ZIP",WebUtil::GetInput(("zip_"  . strtolower($addressType)), FILTER_SANITIZE_STRING_ONE_LINE),$addressType);
			$domainAddressObj->updateFieldOfType("State",WebUtil::GetInput(("state_"  . strtolower($addressType)), FILTER_SANITIZE_STRING_ONE_LINE),$addressType);
			$domainAddressObj->updateFieldOfType("CountryCode",WebUtil::GetInput(("cc_"  . strtolower($addressType)), FILTER_SANITIZE_STRING_ONE_LINE),$addressType);
			$domainAddressObj->updateFieldOfType("Phone",WebUtil::GetInput(("phone_"  . strtolower($addressType)), FILTER_SANITIZE_STRING_ONE_LINE),$addressType);
			
			$residentialFlag = WebUtil::GetInput(("resident_"  . strtolower($addressType)),  FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
			$domainAddressObj->updateResidentialFlag($residentialFlag == "active" ? true : false, $addressType);
		}			
		
		$domainAddressObj->updateUPSAccountNo(WebUtil::GetInput("upsaccountno", FILTER_SANITIZE_STRING_ONE_LINE));
		$domainAddressObj->updateTaxIdNo(WebUtil::GetInput("taxidno", FILTER_SANITIZE_STRING_ONE_LINE));
		$domainAddressObj->updateTaxIdType(WebUtil::GetInput("taxidtype", FILTER_SANITIZE_STRING_ONE_LINE));		
		
		header('Location: ' . WebUtil::FilterURL("./ad_domainAddressEdit.php?view=edit"));
		exit;
	}
	else{
		throw new Exception("Illegal Action");		
	}
}

$t = new Templatex(".");
$t->set_file("origPage", "ad_domainAddressEdit-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$view = WebUtil::GetInput("view", FILTER_SANITIZE_STRING_ONE_LINE);


$domainAddressObj = new DomainAddresses($domainID);

$addressTypesArr = $domainAddressObj->getAddressTypes();

$t->set_block("origPage","editFieldBL","editFieldBLout");	

$t->set_var("DOMAINID", $domainID);

$t->set_var("DOMAINKEY", $domainKey = Domain::getDomainKeyFromID($domainID));

foreach($addressTypesArr as $addressType) {
	
	$mailingAddressObj = $domainAddressObj->getMailingAddressOfType($addressType);
	
	$t->set_var("ATTENTIONVALUE", $mailingAddressObj->getAttention());
	$t->set_var("COMPANYVALUE", $mailingAddressObj->getCompanyName());
	$t->set_var("ADDRESSONEVALUE", $mailingAddressObj->getAddressOne());
	$t->set_var("ADDRESSTWOVALUE", $mailingAddressObj->getAddressTwo());
	$t->set_var("CITYVALUE", $mailingAddressObj->getCity());
	$t->set_var("STATEVALUE", $mailingAddressObj->getState());
	$t->set_var("ZIPVALUE", $mailingAddressObj->getZipCode());
	$t->set_var("CCVALUE", $mailingAddressObj->getCountryCode());
	$t->set_var("PHONEVALUE", $mailingAddressObj->getPhoneNumber());

	$t->set_var("FIELDLABEL", $addressType);
	$t->set_var("FIELDNAME",  strtolower($addressType));
		
	$t->set_var("RESIDENTIALCHECKED", "");
	if($mailingAddressObj->isResidential())
		$t->set_var("RESIDENTIALCHECKED", "checked");
	
	$t->parse("editFieldBLout","editFieldBL",true);
}	

$t->set_var("UPSACCOUNTNOVALUE",$domainAddressObj->getUPSAccountNo());
$t->set_var("TAXIDTYPEVALUE",$domainAddressObj->getTaxIDType());
$t->set_var("TAXIDNOVALUE",$domainAddressObj->getTaxIDNumber());	


$t->pparse("OUT","origPage");

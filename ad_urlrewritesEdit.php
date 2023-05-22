<?php

require_once("library/Boot_Session.php");

$domainID  = Domain::oneDomain();

$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$addNewFields = WebUtil::GetInput("addnew", FILTER_SANITIZE_INT);

$dbCmd = new DbCmd();

$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$userID = $AuthObj->GetUserID();

$allowedDomainsArr = $AuthObj->getUserDomainsIDs();
if(!in_array($domainID, $allowedDomainsArr))
	throw new Exception("DomainID not allowed");	

if(!empty($action)){
	
	if($action == "update") {
	
		WebUtil::checkFormSecurityCode();
	
		if(empty($domainID))
			throw new Exception("Illegal DomainID");
					
		$urlRewriteObj = new UrlRewrite($domainID);
		$urlRewriteArr = $urlRewriteObj->loadData();
	
		foreach($urlRewriteArr as $urlRewrite) {
		
			$id = $urlRewrite["ID"];
			
			$request = WebUtil::GetInput(("key_" . $id), FILTER_SANITIZE_STRING_ONE_LINE);
			$backgroundUrl = WebUtil::GetInput(("value_" . $id), FILTER_SANITIZE_STRING_ONE_LINE);
			$delete = WebUtil::GetInput(("delete_" . $id), FILTER_SANITIZE_STRING_ONE_LINE);
			
			if(empty($backgroundUrl) && empty($request))
				$delete="delete";
			
			if($delete=="delete") {

				$urlRewriteObj->deleteRecord($id);
			
			} else {
				
				if($request!=$urlRewrite["Request"])
					$urlRewriteObj->updateRequest($request,$id);
	
				if($request!=$urlRewrite["BackgroundURL"])
					$urlRewriteObj->updateBackgroundUrl($backgroundUrl,$id);					
			}	
		}	
		
		$addNewFields = WebUtil::GetInput("noofnew", FILTER_SANITIZE_INT);
		
		for($new=0; $new<$addNewFields; $new++) {
	
			$request = WebUtil::GetInput(("newkey_" . $new), FILTER_SANITIZE_STRING_ONE_LINE);
			$backgroundUrl = WebUtil::GetInput(("newvalue_" . $new), FILTER_SANITIZE_STRING_ONE_LINE);
			
			if(!empty($request) && !empty($backgroundUrl))
				$urlRewriteObj->addNewRewrite($request,$backgroundUrl);
		}		
		
		header('Location: ' . WebUtil::FilterURL("./ad_urlrewritesEdit.php"));
		exit;
	}
	else{
		throw new Exception("Illegal Action");		
	}
}

$t = new Templatex(".");
$t->set_file("origPage", "ad_urlrewritesEdit-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$urlRewriteObj = new UrlRewrite($domainID);

$urlRewriteArr = $urlRewriteObj->loadData();

if(empty($urlRewriteArr) && (empty($addNewFields)) )
	$addNewFields = 3;

$t->set_block("origPage","editFieldBL","editFieldBLout");	
$t->set_block("origPage","newFieldBL","newFieldBLout");	

$t->set_var("DOMAINID", $domainID);
$t->set_var("DOMAINKEY", $domainKey = Domain::getDomainKeyFromID($domainID));
$t->set_var("NOOFNEW", $addNewFields);

$t->set_var("ADD_NEW_FIELDS_DROPDOWN", Widgets::buildSelect(array("3"=>3, "10"=>10, "20"=>20), $addNewFields));
$t->allowVariableToContainBrackets("ADD_NEW_FIELDS_DROPDOWN");

for($new=0; $new<$addNewFields; $new++) {
	
	$t->set_var("KEYVALUE", "");
	$t->set_var("REPLACEURLVALUE", "");
	$t->set_var("RECORDID", $new); 
	
	$t->parse("newFieldBLout","newFieldBL",true);
}

foreach($urlRewriteArr as $urlRewrite) {
	
	$t->set_var("KEYVALUE", $urlRewrite["Request"]);
	$t->set_var("REPLACEURLVALUE", $urlRewrite["BackgroundURL"]);
	$t->set_var("RECORDID", $urlRewrite["ID"]); 
	
	$t->parse("editFieldBLout","editFieldBL",true);
}	

if(empty($urlRewriteArr))
	$t->set_var("editFieldBLout", "");

if(empty($addNewFields))
	$t->set_var("newFieldBLout", "");

$t->pparse("OUT","origPage");

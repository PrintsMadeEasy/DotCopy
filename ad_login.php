<?php


require_once("library/Boot_Session.php");


$email = WebUtil::GetInput("email", FILTER_SANITIZE_EMAIL);
$pw = WebUtil::GetInput("pw", FILTER_SANITIZE_STRING_ONE_LINE);
$transferaddress = WebUtil::GetInput("transferaddress", FILTER_SANITIZE_URL);
$siteKeyID = WebUtil::GetInput("siteKeyID", FILTER_SANITIZE_INT);
$siteKeyDesc = WebUtil::GetInput("siteKeyDesc", FILTER_SANITIZE_STRING_ONE_LINE);
$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$forceNewEmailAddress = WebUtil::GetInput("forceNewEmailAddress", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);


if(empty($transferaddress))
	$transferaddress = "./";

$passiveAuthObj = Authenticate::getPassiveAuthObject();

WebUtil::RunInSecureModeHTTPS();


$maxSiteKeyID = 120;


$dbCmd = new DbCmd();

$t = new Templatex(".");

$t->set_file("origPage", "ad_login-template.html");

$t->set_var("TRANSFER_ADDRESS", WebUtil::htmlOutput($transferaddress));
$t->set_var("TRANSFER_ADDRESS_ENCODED", urlencode($transferaddress));
$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


if(!empty($action)){
	
	if(!in_array($action, array("ChangeSiteKey")))
		WebUtil::checkFormSecurityCode();
	
	
	if($action == "ShowSiteKeySelections"){
		
		$t->set_var("PAGE_TITLE", "Choose a Site Key");
		
		if(!$passiveAuthObj->CheckIfLoggedIn()){
			$t->discard_block("origPage", "SiteKeyImagesBL");
			$t->discard_block("origPage", "ChangeSiteKeyBL");
			$t->discard_block("origPage", "PasswordBL");
			$t->discard_block("origPage", "ChangeSiteKeyBL");
			$t->discard_block("origPage", "EmailBL");
			
			$t->set_var("ERROR_MESSAGE", WebUtil::htmlOutput("You must be logged in to select a new site key."));
			$t->pparse("OUT","origPage");
			exit;
		}
		
		$t->discard_block("origPage", "ChangeSiteKeyBL");
		$t->discard_block("origPage", "PasswordBL");
		$t->discard_block("origPage", "EmailBL");
		$t->discard_block("origPage", "ChangeSiteKeyBL");
		$t->discard_block("origPage", "ErrorBL");
		
		$passiveAuthObj->EnsureLoggedIn();
		$passiveAuthObj->EnsureMemberSecurity();
		
		$numberOfColumns = 6;
		
		$numberOfRows = $maxSiteKeyID / $numberOfColumns;
		
		$t->set_block("origPage","SiteKeyRowBL","SiteKeyRowBLout");
		
		for($i=0; $i<$numberOfRows; $i++){
			
			$t->set_var("SITE_KEY_COL1", $i*$numberOfColumns+1);
			$t->set_var("SITE_KEY_COL2", $i*$numberOfColumns+2);
			$t->set_var("SITE_KEY_COL3", $i*$numberOfColumns+3);
			$t->set_var("SITE_KEY_COL4", $i*$numberOfColumns+4);
			$t->set_var("SITE_KEY_COL5", $i*$numberOfColumns+5);
			$t->set_var("SITE_KEY_COL6", $i*$numberOfColumns+6);
			
			$t->parse("SiteKeyRowBLout","SiteKeyRowBL",true);
		}
		
		$t->pparse("OUT","origPage");
		exit;
		
	}
	else if($action == "SaveSiteKey"){
		
		$AuthObj = new Authenticate(Authenticate::login_ADMIN);
		$UserID = $AuthObj->GetUserID();
		
		$siteKeyDescNoSpaces = preg_replace("/\s/", "", $siteKeyDesc);
		
		if(strlen($siteKeyDescNoSpaces) < 4)
			throw new Exception("Can not save the SiteKey because the Description less than 4 characters.");
			
		if($siteKeyID < 1 || $siteKeyID > $maxSiteKeyID)
			throw new Exception("Can not save the SiteKey because the ID is invalid.");
			
		$dbCmd->UpdateQuery("users", array("SiteKeyDesc"=>$siteKeyDesc, "SiteKeyImageID"=>$siteKeyID), "ID=$UserID");
		
		header("Location: " . WebUtil::FilterURL($transferaddress));
		exit;
		
	}
	else if($action == "ChangeSiteKey"){
		
		$t->set_var("PAGE_TITLE", "Choose a New Site Key");
		
		// Show them a button to select a new site Key.  We want them to acknowlege they are trying to do this with the form security code.
		$t->discard_block("origPage", "SiteKeyImagesBL");
		$t->discard_block("origPage", "PasswordBL");
		$t->discard_block("origPage", "ErrorBL");
		$t->discard_block("origPage", "EmailBL");

		$t->pparse("OUT","origPage");
		exit;	
	}
	else if($action == "CheckUserNamePassword"){
		
		$t->set_var("PAGE_TITLE", "Please Sign In");
		
		$t->discard_block("origPage", "SiteKeyImagesBL");
		$t->discard_block("origPage", "PasswordBL");
		$t->discard_block("origPage", "EmailBL");
		$t->discard_block("origPage", "ChangeSiteKeyBL");
		
		sleep(2);
		
		// Now find all of the User IDs in the system (for each domain) belonging to the Email address.
		// If one of the UserIDs has member access then they can't use the lost password finder.
		$dbCmd->Query("SELECT ID FROM users WHERE Email LIKE '".DbCmd::EscapeLikeQuery($email)."'");
		$userIDsArr = $dbCmd->GetValueArr();
		
		$memberIDs = array();
		foreach($userIDsArr as $thisUserID){
			if(!$passiveAuthObj->CheckIfUserIDisMember($thisUserID))
				continue;
				
			$memberIDs[] = $thisUserID;
		}
		
		if(sizeof($memberIDs) == 0){
			$t->set_var("ERROR_MESSAGE", WebUtil::htmlOutput("The email address does not belong to an account with administrative privelages"));
			$t->pparse("OUT","origPage");
			exit;
		}
		else if(sizeof($memberIDs) > 1){
			$t->set_var("ERROR_MESSAGE", WebUtil::htmlOutput("An error occured, there is more than 1 account belonging to this email address with admin privelages."));
			$t->pparse("OUT","origPage");
			exit;
		}
		
		$UserID = $memberIDs[0];
		
		$domainIDofUser = UserControl::getDomainIDofUser($memberIDs[0]);
		
		$loginResult = Authenticate::CheckUserNamePass($dbCmd, $email, $pw, $domainIDofUser);
		if($loginResult == "OK"){
			
			// Log them in through the session
			Authenticate::SetUserIDLoggedIn($UserID);

			$cookieTime = time()+60*60*24 * 60;
			
			$securitySalt = Authenticate::getSecuritySaltADMIN();
	
			if(Constants::GetDevelopmentServer()){
				$secureFlag = false;
				setcookie ("PreAuthUserID", $UserID, $cookieTime, null, null, $secureFlag, true);
				setcookie ("PreAuthUserPW", md5(($pw . $securitySalt)), $cookieTime, null, null, $secureFlag, true);
			}
			else{
				$secureFlag = true;
				setcookie ("PreAuthUserID", $UserID, $cookieTime, null, null, $secureFlag, true);
				setcookie ("PreAuthUserPW", md5(($pw . $securitySalt)), $cookieTime, null, null, $secureFlag, true);
			}
			
			
		

			
			WebUtil::SetCookie("LastAdminEmailLogin", $email, 300);
			
			// If the user doesn't have a Site Key Saved yet, then redirect them to the URL for selecting one.
			// Don't redirect, otherwise the Cookies might not get sent the user's browser.

			$dbCmd->Query("SELECT SiteKeyDesc FROM users WHERE ID =" . intval($UserID));
			$siteKeyDesc = $dbCmd->GetValue();
			if(empty($siteKeyDesc)){
				print "<html><script>document.location='".WebUtil::FilterURL("ad_login.php?transferaddress=" . urlencode($transferaddress) . "&action=ShowSiteKeySelections&form_sc=" . WebUtil::getFormSecurityCode())."';</script></html>";
			}
			else{
				print "<html><script>document.location='".WebUtil::FilterURL($transferaddress)."';</script></html>";
			}
			
			exit;
		}
		else{
			$t->set_var("ERROR_MESSAGE", WebUtil::htmlOutput($loginResult) . "<br><br><br><a href='javascript:history.back()' class='BlueRedLink'>Try Again?</a>");
			$t->allowVariableToContainBrackets("ERROR_MESSAGE");
			$t->pparse("OUT","origPage");
			exit;
		}
		
	}
	else{
		throw new Exception("Illegal Action sent.");
	}
	
}


// If they are already logged in, then they don't need to be at this page.
if($passiveAuthObj->CheckIfLoggedIn()){
	header("Location: " . WebUtil::FilterURL($transferaddress));
	exit;
}

$t->set_var("PAGE_TITLE", "Please Sign In");

// Find out if we have the users email address stored in a cookie.
if(empty($email))
	$email = WebUtil::GetCookie("LastAdminEmailLogin");


$t->set_var("EMAIL", WebUtil::htmlOutput($email));
	
	
// If we still don't have an email address, then we need to show them a form asking them to enter the email.
if(empty($email) || !empty($forceNewEmailAddress)){
	
	$t->discard_block("origPage", "SiteKeyImagesBL");
	$t->discard_block("origPage", "PasswordBL");
	$t->discard_block("origPage", "ErrorBL");
	$t->discard_block("origPage", "ChangeSiteKeyBL");
	$t->discard_block("origPage", "ChangeSiteKeyBL");
	
}
else{
	
	$t->discard_block("origPage", "SiteKeyImagesBL");
	$t->discard_block("origPage", "EmailBL");
	$t->discard_block("origPage", "ChangeSiteKeyBL");
	$t->discard_block("origPage", "ChangeSiteKeyBL");
	
	// Now find all of the User IDs in the system (for each domain) belonging to the Email address.
	// If one of the UserIDs has member access then they can't use the lost password finder.
	$dbCmd->Query("SELECT ID FROM users WHERE Email LIKE '".DbCmd::EscapeLikeQuery($email)."'");
	$userIDsArr = $dbCmd->GetValueArr();
	
	$memberIDs = array();
	foreach($userIDsArr as $thisUserID){
		if(!$passiveAuthObj->CheckIfUserIDisMember($thisUserID))
			continue;
			
		$memberIDs[] = $thisUserID;
	}
	
	if(sizeof($memberIDs) == 0){
		$t->discard_block("origPage", "ExistingSiteKeyBL");
		$t->discard_block("origPage", "PasswordInputFieldBL");
		
		$t->set_var("ERROR_MESSAGE", WebUtil::htmlOutput("The email address does not belong to an account with administrative privelages"));
		$t->pparse("OUT","origPage");
		exit;
	}
	else if(sizeof($memberIDs) > 1){
		$t->discard_block("origPage", "ExistingSiteKeyBL");
		$t->discard_block("origPage", "PasswordInputFieldBL");
		
		$t->set_var("ERROR_MESSAGE", WebUtil::htmlOutput("An error occured, there is more than 1 account belonging to this email address with admin privelages."));
		$t->pparse("OUT","origPage");
		exit;
	}
	else{
		$t->discard_block("origPage", "ErrorBL");
	}
	
	
	// Find out if the user has saved their Site Key yet.
	$dbCmd->Query("SELECT SiteKeyImageID, SiteKeyDesc FROM users WHERE ID =" . intval($memberIDs[0]));
	$row = $dbCmd->GetRow();

	if(empty($row["SiteKeyDesc"])){
		$t->discard_block("origPage", "ExistingSiteKeyBL");
	}
	else{
		
		$t->discard_block("origPage", "VerifyAddressInBrowserBL");
		
		$t->set_var("SITE_KEY_ID", WebUtil::htmlOutput($row["SiteKeyImageID"]));
		$t->set_var("SITE_KEY_DESC", WebUtil::htmlOutput($row["SiteKeyDesc"]));
	}
}
	






$t->pparse("OUT","origPage");



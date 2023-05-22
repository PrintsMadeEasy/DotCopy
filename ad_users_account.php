<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

$UserControlObj = new UserControl($dbCmd);
$ResellerObj = new Reseller($dbCmd);






$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("CUSTOMER_ACCOUNT"))
	WebUtil::PrintAdminError("Not Available");


$customerid = WebUtil::GetInput("customerid", FILTER_SANITIZE_INT);

// Load customer info from Database
if(!$UserControlObj->LoadUserByID($customerid, TRUE))
	throw new Exception("Can not load user data because the user does not exist.");
	
$ResellerObj->LoadReseller($customerid);

$PaymentInvoiceObj = new PaymentInvoice();
$PaymentInvoiceObj->LoadCustomerByID($customerid);


$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$returl = WebUtil::GetInput("returl", FILTER_SANITIZE_URL);
$retdesc = WebUtil::GetInput("retdesc", FILTER_SANITIZE_STRING_ONE_LINE);



// If the Account ID that we are looking at is another member... then we should not let that happen or they could gain administrative access.
// The only exception is if the person that is logged in matches the Customer ID who is also a member
if($AuthObj->CheckIfUserIDisMember($customerid) && ($customerid != $UserID))
	$viewingAnotherMemberFlag = true;
else
	$viewingAnotherMemberFlag = false;
	


// Only let Admins see other Members Accounts.... Just not the Passwords.
if($viewingAnotherMemberFlag && !$AuthObj->CheckIfBelongsToGroup("ADMIN"))
	WebUtil::PrintAdminError("You can not view the account of another member.");


if(!empty($action)){
	
	WebUtil::checkFormSecurityCode();

	if($action == "savegeneral"){
	
		$SalesCommissionsObj = new SalesCommissions($dbCmd, UserControl::getDomainIDofUser($customerid));
		if($SalesCommissionsObj->CheckIfSalesRep($customerid))
			$UserIsSalesRep = true;
		else
			$UserIsSalesRep = false;
		
		
		// Log any Changes made to the Account Information (before updating)
		$userChangeLogFrom = $UserControlObj->getAccountDescriptionText(true);
	
	
		// If the country is not in the U.S. then use the Internation State input box
		if(WebUtil::GetInput("country", FILTER_SANITIZE_STRING_ONE_LINE) == "US")
			$UserState = WebUtil::GetInput("state", FILTER_SANITIZE_STRING_ONE_LINE);
		else
			$UserState = WebUtil::GetInput("intl_state", FILTER_SANITIZE_STRING_ONE_LINE);
	
		$UserControlObj->setEmail(WebUtil::GetInput("email", FILTER_SANITIZE_STRING_ONE_LINE));
		$UserControlObj->setName(WebUtil::GetInput("fullname", FILTER_SANITIZE_STRING_ONE_LINE));
		$UserControlObj->setCompany(WebUtil::GetInput("company", FILTER_SANITIZE_STRING_ONE_LINE));
		$UserControlObj->setAddress(WebUtil::GetInput("address", FILTER_SANITIZE_STRING_ONE_LINE));
		$UserControlObj->setAddressTwo(WebUtil::GetInput("address2", FILTER_SANITIZE_STRING_ONE_LINE));
		$UserControlObj->setCity(WebUtil::GetInput("city", FILTER_SANITIZE_STRING_ONE_LINE));
		$UserControlObj->setCountry(WebUtil::GetInput("country", FILTER_SANITIZE_STRING_ONE_LINE));
		$UserControlObj->setState($UserState);
		$UserControlObj->setZip(WebUtil::GetInput("zip", FILTER_SANITIZE_STRING_ONE_LINE));
		$UserControlObj->setPhone(WebUtil::GetInput("phone", FILTER_SANITIZE_STRING_ONE_LINE));
		$UserControlObj->setNewsletter(WebUtil::GetInput("specialoffers", FILTER_SANITIZE_STRING_ONE_LINE));
		$UserControlObj->setHint(WebUtil::GetInput("hint", FILTER_SANITIZE_STRING_ONE_LINE));
		$UserControlObj->setBillingType(WebUtil::GetInput("billingtype", FILTER_SANITIZE_STRING_ONE_LINE));
		$UserControlObj->setBillingStatus(WebUtil::GetInput("billingstatus", FILTER_SANITIZE_STRING_ONE_LINE));
		$UserControlObj->setAccountStatus(WebUtil::GetInput("accountstatus", FILTER_SANITIZE_STRING_ONE_LINE));
		$UserControlObj->setCreditLimit(WebUtil::GetInput("creditlimit", FILTER_SANITIZE_FLOAT));
		$UserControlObj->setSingleOrderLimit(WebUtil::GetInput("singleorderlimit", FILTER_SANITIZE_FLOAT));
		$UserControlObj->setMonthsChargesLimit(WebUtil::GetInput("monthschargeslimit", FILTER_SANITIZE_FLOAT));
		$UserControlObj->setAddressLocked(WebUtil::GetInput("addresslocked", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES));
		$UserControlObj->setDowngradeShipping(WebUtil::GetInput("downgradeshipping", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES));
		$UserControlObj->setCopyrightTemplates(WebUtil::GetInput("copyright", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "N"));
		$UserControlObj->setLoyaltyProgram(WebUtil::GetInput("loyalty", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "N"));
		
		$passwd = WebUtil::GetInput("password1", FILTER_SANITIZE_STRING_ONE_LINE);
		
		// Don't let a Member update another member's password.
		// Also If we removed the Password from view, don't let the database update the PW with a null value.
		if(!$viewingAnotherMemberFlag && strlen($passwd) >= 5 && $AuthObj->CheckForPermission("VIEW_PASSWORDS"))
			$UserControlObj->setPassword(WebUtil::GetInput("password1", FILTER_SANITIZE_STRING_ONE_LINE));
		
		$UserControlObj->UpdateUser();
		
	
		// Log any Changes made to the Account Information (after updating)
		$userChangeLogTo = $UserControlObj->getAccountDescriptionText(true);
		
		// Find out if any changes were made to the account info... if so then record the changes
		if(md5($userChangeLogFrom) != md5($userChangeLogTo)){
		
			UserControl::RecordChangesToUser($dbCmd, $UserControlObj->getUserID(), $UserID, $userChangeLogFrom, $userChangeLogTo);
			
			if($UserIsSalesRep){
				$SalesRepChangeLog = "Changed Account Info From:\n" . $userChangeLogFrom . "\nTo:\n" . $userChangeLogTo;
				SalesRep::RecordChangesToSalesRep($dbCmd, $UserControlObj->getUserID(), $UserID, $SalesRepChangeLog);
			}
		}
	
		
	
	
	
		header("Location: " . WebUtil::FilterURL("./ad_users_account.php?view=$view&customerid=$customerid&returl=".urlencode($returl)."&retdesc=" . urlencode($retdesc)));
		exit;
	}
	else if($action == "savereseller"){
	
		// If the country is not in the U.S. then use the Internation State input box
		if(WebUtil::GetInput("r_country", FILTER_SANITIZE_STRING_ONE_LINE) == "US")
			$ResellerState = WebUtil::GetInput("r_state", FILTER_SANITIZE_STRING_ONE_LINE);
		else
			$ResellerState = WebUtil::GetInput("intl_r_state", FILTER_SANITIZE_STRING_ONE_LINE);
	
		$ResellerObj->setAccountType(WebUtil::GetInput("accounttype", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES));
		$ResellerObj->setResellerAttention(WebUtil::GetInput("r_attention", FILTER_SANITIZE_STRING_ONE_LINE));
		$ResellerObj->setResellerCompany(WebUtil::GetInput("r_company", FILTER_SANITIZE_STRING_ONE_LINE));
		$ResellerObj->setResellerAddress(WebUtil::GetInput("r_address", FILTER_SANITIZE_STRING_ONE_LINE));
		$ResellerObj->setResellerAddressTwo(WebUtil::GetInput("r_address2", FILTER_SANITIZE_STRING_ONE_LINE));
		$ResellerObj->setResellerCity(WebUtil::GetInput("r_city", FILTER_SANITIZE_STRING_ONE_LINE));
		$ResellerObj->setResellerZip(WebUtil::GetInput("r_zip", FILTER_SANITIZE_STRING_ONE_LINE));
		$ResellerObj->setResellerCountry(WebUtil::GetInput("r_country", FILTER_SANITIZE_STRING_ONE_LINE));
		$ResellerObj->setResellerState($ResellerState);
		$ResellerObj->setResellerPhone(WebUtil::GetInput("r_phone", FILTER_SANITIZE_STRING_ONE_LINE));
		$ResellerObj->setInvoiceMessage1(WebUtil::GetInput("inv_message1", FILTER_SANITIZE_STRING_ONE_LINE));
		$ResellerObj->setInvoiceMessage2(WebUtil::GetInput("inv_message2", FILTER_SANITIZE_STRING_ONE_LINE));
		$ResellerObj->setInvoiceMessage3(WebUtil::GetInput("inv_message3", FILTER_SANITIZE_STRING_ONE_LINE));
		
	
		$R_Logo = $ResellerObj->getLogoImage();
		
		$logoUploadingFlag = array_key_exists("logo", $_FILES) && !empty($_FILES["logo"]);
		
		// If they are not uploading a logo... and the reseller doesn't already have one, then show an error.
		if(!$logoUploadingFlag && empty($R_Logo) && $ResellerObj->getAccountType() == "R"){
			
			// Make sure to save whatever data was entered so far... to keep customer service from retyping
			// But make sure not to change their account type to Reseller yet.
			$ResellerObj->setAccountType("N");
			$ResellerObj->UpdateReseller();
			
			WebUtil::PrintAdminError("You forgot to select a logo for uploading. This user can not be made into a reseller without a logo. The radio button for turning the Resller On/Off may need to be reset again.");
		}
	
	
		// See if they are tying to upload a logo
		if($logoUploadingFlag){
			
			$tmpfname = $_FILES["logo"]["tmp_name"];
	
			if(filesize($tmpfname) > 100000)
				WebUtil::PrintAdminError("The maximum file size you are permitted to upload is 100K. You attempted to upload an image that was " . round(filesize($tmpfname)/1024,0) . "K.");
			
			
			// Open the logo from POST data
			$filedata = fread(fopen($tmpfname, "r"), filesize($tmpfname));
	
			$ImageType = ImageLib::GetImageFormatFromFile($tmpfname);
	
			unlink($tmpfname);
	
			if($ImageType != "JPEG")
				WebUtil::PrintAdminError("Sorry, the logo must be in JPEG format.");
	
	
			$ResellerObj->setLogoImage($filedata);
		}
		
		
		$ResellerObj->UpdateReseller();
	
		header("Location: " . WebUtil::FilterURL("./ad_users_account.php?view=$view&customerid=$customerid&returl=".urlencode($returl)."&retdesc=" . urlencode($retdesc)));
		exit;
	}
	else{
		throw new Exception("Illegal action was passed.");
	}
}




// Enforce the Top Domain ID so that we can see a logo in the Nav Bar for the user's domain.
Domain::enforceTopDomainID($UserControlObj->getDomainID());





$t = new Templatex(".");

$t->set_file("origPage", "ad_users_account-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


// Build the tabs
$baseTabUrl = "./ad_users_account.php?customerid=$customerid&returl=".urlencode($returl)."&retdesc=" . urlencode($retdesc);
$TabsObj = new Navigation();
$TabsObj->AddTab("general", "General", $baseTabUrl . "&view=general");
$TabsObj->AddTab("reseller", "Reseller", $baseTabUrl . "&view=reseller");


// Find out if the user has any corporate billing history. 
// Is so then we need to show them a tab to view the Corporate billing history
$dbCmd->Query("SELECT COUNT(*) FROM orders WHERE UserID=$customerid AND BillingType='C'");
$orderAccountWithCorporateBilling = $dbCmd->GetValue();

$CreditUsageByCustomer = $PaymentInvoiceObj->GetCurrentCreditUsage();

if($CreditUsageByCustomer != 0 || !empty($orderAccountWithCorporateBilling))
	$TabsObj->AddTab("billinghistory", "Corporate Billing History", $baseTabUrl . "&view=billinghistory");


$t->set_var("NAV_BAR_HTML", $TabsObj->GetTabsHTML($view));
$t->allowVariableToContainBrackets("NAV_BAR_HTML");



// Keep a record of the visit to this page by the user.
NavigationHistory::recordPageVisit($dbCmd, $UserID, "UserAccnt", $customerid);


if($view == "general"){
	$t->discard_block("origPage", "ResellerFormBL");
	$t->discard_block("origPage", "CorporateBillingHistoryBL");
	
	$t->set_var("COUNTRIES_DROPDOWN", Widgets::buildSelect(Status::GetUPScountryCodesArr(), array($UserControlObj->GetCountry())));
	$t->allowVariableToContainBrackets("COUNTRIES_DROPDOWN");
	
	// Do not let members view the password of another member
	if($viewingAnotherMemberFlag || !$AuthObj->CheckForPermission("VIEW_PASSWORDS")){
		$t->discard_block("origPage", "HidePasswordBL");
		$t->discard_block("origPage", "HidePasswordCheckBL");	
	}
	
	
	// Set variables in the form with stuff from DB
	$UserControlObj->SetUserTemplateVariables($t);
	
	// If it is in the United States, then fill up the 2 letter abrieviation, otherwise the full state description
	if($UserControlObj->getCountry() == "US")
		$t->set_var("INTL_STATE", "");
	else{
		$t->set_var("INTL_STATE", WebUtil::htmlOutput($UserControlObj->getState()));
		$t->set_var("STATE", "");
	}
	
	

	
	
	// Find out if there is any history for changes to the User account info. 
	$dbCmd->Query("SELECT *, UNIX_TIMESTAMP(Date) as Date FROM userchangelog WHERE UserID=$customerid ORDER BY Date DESC");
	if($dbCmd->GetNumRows() == 0){
		$t->discard_block("origPage", "HideChangeLog");
	}
	else{
	
		$t->set_block("origPage","ChangesBL","ChangesBLout");
		
		while($row = $dbCmd->GetRow()){
		
			$changeDate = date("M j, Y - D g:i a", $row["Date"]);
			
			if($row["UserID"] == $row["WhoChangedUserID"])
				$userWhoChanged = "By: The Customer";
			else
				$userWhoChanged = "By: " . UserControl::GetNameByUserID($dbCmd2, $row["WhoChangedUserID"]);
			
			// Now find out what has changed between the Two.
			// If we detect a Change then make the difference show in bold.
			// We are splitting on the line breaks
			$changedFromArr = split("\n", $row["DescriptionFrom"]);
			$changedToArr = split("\n", $row["DescriptionTo"]);
			
			// Now paste the arrays back into a string
			$changedFromDisplayStr = "";
			foreach($changedFromArr as $thisFromLine){
				if(!in_array($thisFromLine, $changedToArr))
					$changedFromDisplayStr .= "<b><font color='#333333'>" . WebUtil::htmlOutput($thisFromLine) . "</font></b><br>";
				else
					$changedFromDisplayStr .= WebUtil::htmlOutput($thisFromLine) . "<br>";
			}
			
			$changedToDisplayStr = "";
			foreach($changedToArr as $changedToStr){
				if(!in_array($changedToStr, $changedFromArr))
					$changedToDisplayStr .= "<b><font color='#333333'>" . WebUtil::htmlOutput($changedToStr) . "</font></b><br>";
				else
					$changedToDisplayStr .= WebUtil::htmlOutput($changedToStr) . "<br>";
			}
			
			
			$t->set_var("CHANGED_FROM", $changedFromDisplayStr);
			$t->set_var("CHANGED_TO", $changedToDisplayStr);
			$t->set_var("CHANGE_RECORD", "Changed: $changeDate <br>" . WebUtil::htmlOutput($userWhoChanged));
			$t->allowVariableToContainBrackets("CHANGED_TO");
			$t->allowVariableToContainBrackets("CHANGED_FROM");
			$t->allowVariableToContainBrackets("CHANGE_RECORD");
			
			$t->parse("ChangesBLout","ChangesBL",true);
		}
		
		
	}
		
}
else if($view == "reseller"){
	$t->discard_block("origPage", "GeneralFormBL");
	$t->discard_block("origPage", "CorporateBillingHistoryBL");
	
	
	// Set variables in the form with stuff from DB
	$ResellerObj->SetResellerTemplateVariables($t);
	

	// Default to the U.S. if case no data has been saved yet
	if($ResellerObj->getResellerCountry() == "")
		$ResellerCountry = "US";
	else
		$ResellerCountry = $ResellerObj->getResellerCountry();


	// If it is in the United States, then fill up the 2 letter abrieviation, otherwise the full state description
	if($ResellerCountry != "US"){
		$t->set_var("INTL_R_STATE", WebUtil::htmlOutput($ResellerObj->getResellerState()));
		$t->set_var("R_STATE", "");
	}
	else
		$t->set_var("INTL_R_STATE", "");
	

	$t->set_var("COUNTRIES_DROPDOWN", Widgets::buildSelect(Status::GetUPScountryCodesArr(), array($ResellerCountry)));
	$t->allowVariableToContainBrackets("COUNTRIES_DROPDOWN");
	
	$R_Logo = $ResellerObj->getLogoImage();
	
	if(!empty($R_Logo)){
		$ResellerLogoDownload = "<font class='body'><b>Current Logo</b></font><br><img style='border-color:#CCCCCC' border='3' src='./draw_download_image.php?view=reseller_logo&imageid=". $ResellerObj->getUserID() . "'>";
		$t->set_var("RESELLER_LOGO", $ResellerLogoDownload);
	}
	else
		$t->set_var("RESELLER_LOGO", "");
		
	$t->allowVariableToContainBrackets("RESELLER_LOGO");
	
	
	
}
else if($view == "billinghistory"){
	$t->discard_block("origPage", "GeneralFormBL");
	$t->discard_block("origPage", "ResellerFormBL");


	// If we don't get a month or year parameter in the URL... then just default to whatever the month/year report that is available
	$month = WebUtil::GetInput("month", FILTER_SANITIZE_INT, $PaymentInvoiceObj->GetStopMonth());
	$year = WebUtil::GetInput("year", FILTER_SANITIZE_INT, $PaymentInvoiceObj->GetStopYear());
	$billingview = WebUtil::GetInput("billingview", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "unbilled");

	
	$ExtraURLparameters = array("view"=>$view, "customerid"=>$customerid, "returl"=>WebUtil::FilterURL($returl), "retdesc"=>WebUtil::FilterURL($retdesc));

	$t->set_var("BILLING_HISTORY", Billing::GetBillingHistory($dbCmd, $PaymentInvoiceObj, "ad_users_account.php", $ExtraURLparameters, $billingview, $month, $year, "orderpage"));
	$t->allowVariableToContainBrackets("BILLING_HISTORY");
}
else
	throw new Exception("Problem with the view type in the URL");




$salesRepObj = new SalesRep($dbCmd);
if($salesRepObj->LoadSalesRep($customerid)){
	$salesRepParentName = ($salesRepObj->getParentSalesRep() == 0) ? "Root" : (WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, $salesRepObj->getParentSalesRep())));
	$t->set_var("SALESREP", "<font color='#cc3366'>User is a Sales Rep</font> <font class='reallysmallbody'>Parent Rep:</font> $salesRepParentName</font>");
}
else{
	$t->set_var("SALESREP", "");
}

$t->allowVariableToContainBrackets("SALESREP");


// Don't show users the button for Payment entry if they are not allowed to use it.
if(!$AuthObj->CheckForPermission("CORPORATE_BILLING"))
	$t->discard_block("origPage", "InputPaymentButton");


if(!Domain::getLoyaltyDiscountFlag(UserControl::getDomainIDofUser($customerid)))
	$t->discard_block("origPage", "LoyaltyProgram");

if(!Domain::getCopywriteChargeFlagForDomain(UserControl::getDomainIDofUser($customerid)))
	$t->discard_block("origPage", "CopyrightBL");
	
	
	
$loyaltyObj = new LoyaltyProgram(UserControl::getDomainIDofUser($customerid));
$totalLoyaltySavings = $loyaltyObj->getTotalSavingsForUser($customerid);
$totalLoyaltyFees = $loyaltyObj->getLoyaltyChargeTotalsFromUser($customerid);
$totalReunds = $loyaltyObj->getRefundsTotalsToUser($customerid);

$t->set_var("LOYALTY_SAVINGS", number_format($totalLoyaltySavings, 2));
$t->set_var("LOYALTY_BALANCE", number_format(($totalLoyaltyFees - $totalReunds), 2));

$t->set_var("PERSONS_NAME", WebUtil::htmlOutput($UserControlObj->getName()));
$t->set_var("CUSTOMERID", $customerid);
$t->set_var("VIEW", $view);
$t->set_var("RETURL", WebUtil::FilterURL(($returl)));
$t->set_var("RETDESC", WebUtil::htmlOutput($retdesc));
$t->set_var("CURRENT_URL_ENCODED", WebUtil::htmlOutput(urlencode($_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING'])));


$t->pparse("OUT","origPage");



?>
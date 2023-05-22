<?

require_once("library/Boot_Session.php");


$transferaddress = WebUtil::GetInput("transferaddress", FILTER_SANITIZE_URL);
$registrationtype = WebUtil::GetInput("registrationtype", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);


WebUtil::RunInSecureModeHTTPS();

// Make sure that Admin can't place an order from another domain.
Domain::enforceTopDomainID(Domain::getDomainIDfromURL());

$dbCmd = new DbCmd();

$t = new Templatex();


$UserControlObj = new UserControl($dbCmd);




$t->set_file("origPage", "register-template.html");


$showLoyaltyInput = false;
$showCopyrightInput = false;


// If the user is editing then get all of the stuff from the DB
if($registrationtype == "edit"){

	//Make sure they are logged in.
	$AuthObj = new Authenticate(Authenticate::login_general);
	$UserID = $AuthObj->GetUserID();
	
	if(UserControl::getDomainIDofUser($UserID) != Domain::getDomainIDfromURL())
		WebUtil::PrintError("You must edit your account information from the domain in which you signed up.");
	
	// Load user info from Database
	$UserControlObj->LoadUserByID($UserID);
	
	// Set variables in the form with stuff from DB
	$UserControlObj->SetUserTemplateVariables($t);
	
	// Delete the Register header and button (which is used for new registrations)
	$t->discard_block("origPage", "RegisterButtonBL");
	$t->discard_block("origPage", "RegisterHeaderBL");
	$t->discard_block("origPage", "CheckoutHeaderBL");
	
	
	// Discard the radio buttons for "How did you hear about us?".  This should only be used for new account sign up
	$t->discard_block("origPage", "hearaboutBL");
	
	// The password regular expression is different for editing vs new accounts
	// They don't need to enter a new password if they are editing unless they want to change it.
	$t->set_var("PASSWORD_REGEX", "(^$|^[^\s]{5,25}$)"); 
	
	$t->set_var("PASSWORD_MESSAGE", "Change Your Password");
	
	$t->set_var("REGISTRATION_TYPE", "edit");
	
	// For the web software tracking
	$t->set_var("REGISTER_TYPE_TRACKING", "Edit"); 
	
	// Show there name in the header
	$t->set_var("PERSONS_NAME", (" - " . WebUtil::htmlOutput($UserControlObj->getName())));

	// Default to the U.S. for new accounts
	$t->set_var("COUNTRIES_DROPDOWN", Widgets::buildSelect(Status::GetUPScountryCodesArr(), array($UserControlObj->getCountry())));
	$t->allowVariableToContainBrackets("COUNTRIES_DROPDOWN");
	
	// If they are outside of the U.S. then put the State into the international province field instead.
	if($UserControlObj->getCountry() == "US")
		$t->set_var("INTL_STATE", ""); 
	else{
		$t->set_var("INTL_STATE", $UserControlObj->getState());
		$t->set_var("STATE", "");
	}
	
	if(LoyaltyProgram::displayLoyalityOptionForVisitor($UserID))
		$showLoyaltyInput = true;

	if(CopyrightCharges::displayCopyrightOptionForVisitor($UserID))
		$showCopyrightInput = true;
	
	// Don't allow corporate billing customers to enroll within the program.
	if($UserControlObj->getBillingType() != "N"){
		$showLoyaltyInput = false;
		$showCopyrightInput = false;
	}
		
	VisitorPath::addRecord("Registration Screen (Edit)");

}
else if($registrationtype == "new" || $registrationtype == "checkout" || $registrationtype == "paypal"){

	// "New" and "Checkout" are basically the same thing... 
	// It just means that they were trying to checkout when the register screen popped up... so we display a differen header for them

	// Delete the edit header and update button (which is used for editing user data)
	$t->discard_block("origPage", "UpdateButtonBL");
	$t->discard_block("origPage", "EditHeaderBL");
	


	if($registrationtype == "new")
		$t->discard_block("origPage", "CheckoutHeaderBL");
	else
		$t->discard_block("origPage", "RegisterHeaderBL");
		

	// For new Registrations... figure out the default setting for the Domain.
	if(Domain::getDefaultCopyrightFlagForRegistration(Domain::getDomainIDfromURL()))
		$UserControlObj->setCopyrightTemplates("Y");
	else
		$UserControlObj->setCopyrightTemplates("N");
		
		
	// For new Registrations... figure out the default setting for the Domain.
	if(Domain::getDefaultNewsletterFlagForRegistration(Domain::getDomainIDfromURL()))
		$UserControlObj->setNewsletter("Y");
	else
		$UserControlObj->setNewsletter("N");
	
	// For new Registrations... figure out the default setting for the Domain.
	if(Domain::getDefaultLoyaltyFlagForRegistration(Domain::getDomainIDfromURL()))
		$UserControlObj->setLoyaltyProgram("Y");
	else
		$UserControlObj->setLoyaltyProgram("N");

			
	if($registrationtype == "paypal") {
		
		$paypalToken   = WebUtil::GetSessionVar("PaypalToken");
		$paypalPayerId = WebUtil::GetSessionVar("PaypalPayerID");
		
		if(empty($paypalToken) || empty($paypalPayerId)){
			throw new Exception("Empty Paypal Token provided on the registration screen.");
		}
		
		$payPalObj = new PayPalApiPro();
			
		$customerData = $payPalObj->getExpressCheckoutDetails($paypalToken);
		
		$payerEmail = $customerData->payerEmail;
		

		if($UserControlObj->CheckIfEmailExistsInDB($payerEmail)){
			WebUtil::WebmasterError("An error occured with a duplicate email after Paypal Sign-in");
			WebUtil::PrintError("An error occured after signing in through Paypal. You already have an account with us.");
		}
		
		$UserControlObj->setEmail($payerEmail);
		$UserControlObj->setName($customerData->payerFirstName . " " . $customerData->payerMiddleName . " " . $customerData->payerLastName);
		$UserControlObj->setCompany($customerData->businessName);
		$UserControlObj->setAddress($customerData->businessStreet1);
		$UserControlObj->setAddressTwo($customerData->businessStreet2);
		$UserControlObj->setCity($customerData->businessCityName);
		$UserControlObj->setState($customerData->businessStateOrProvince);
		$UserControlObj->setZip($customerData->businessZIP);
		$UserControlObj->setCountry($customerData->payerCountryCode);
		$UserControlObj->setPhone("Paypal Auto-Account");
		$UserControlObj->setHint("Account created with Paypal");
		$UserControlObj->setPassword($payPalObj->randomPassword()); 
		$UserControlObj->setHearAbout("Paypal");
		$UserControlObj->setAffiliateName(WebUtil::GetSessionVar('AffiliateName'));
		$UserControlObj->setAffiliateDiscount(WebUtil::GetSessionVar('AffiliateDiscount'));
		$UserControlObj->setAffiliateExpires(WebUtil::GetSessionVar('AffiliateExpires'));
		$UserControlObj->setNewsletter("Y");
		$UserControlObj->setCopyrightTemplates("N");
	
		// Set a cookie on his machine so we can remember him the next time he returns... Save for 360 days
		WebUtil::OutputCompactPrivacyPolicyHeader();
		WebUtil::SetCookie("UserEmailAddress", $payerEmail, 360);
		$newUserID = $UserControlObj->AddNewUser();
	
		Authenticate::SetUserIDLoggedIn($newUserID);
		
		header("Location: ". WebUtil::FilterURL($transferaddress));
		exit;
	}

		
	// Set variables in the form
	// There is not information yet.  The $UserControlObj should be initialized with blank fields (and some defaults)
	$UserControlObj->SetUserTemplateVariables($t);

	// The password regular expression is different for editing vs new accounts
	$t->set_var("PASSWORD_REGEX", "[^\s]{5,25}$"); 
	
	$t->set_var("PASSWORD_MESSAGE", "Choose a Password");
	
	$t->set_var("REGISTRATION_TYPE", "new");
	

	// For the web software tracking
	$t->set_var("REGISTER_TYPE_TRACKING", "New"); 
	
	$t->set_var("PERSONS_NAME", ""); 
	
	
	// Default to the U.S. for new accounts
	$t->set_var("COUNTRIES_DROPDOWN", Widgets::buildSelect(Status::GetUPScountryCodesArr(), array("US")));
	$t->allowVariableToContainBrackets("COUNTRIES_DROPDOWN");
	
	$t->set_var("INTL_STATE", ""); 
	
	
	if(LoyaltyProgram::displayLoyalityOptionForVisitor())
		$showLoyaltyInput = true;
	if(CopyrightCharges::displayCopyrightOptionForVisitor())
		$showCopyrightInput = true;
	
	VisitorPath::addRecord("Registration Screen (New)");

}
else{
	WebUtil::PrintError("There is a problem with the URL.  The registration type is invalid.  Please go back and try again.");
}



// If the address is locked for this user, then we want to disable the address input fields and show them a warning
// New users (or UserControl Objects) by default are NOT locked when they are created
if($UserControlObj->getAddressLocked() == "Y"){
	$t->set_var("DISABLED_INPUT", "disabled"); 
}
else{
	$t->set_var("DISABLED_INPUT", ""); 
	$t->discard_block("origPage", "AddressLockedBL");
}


if(!$showLoyaltyInput){
	$t->discard_block("origPage", "LoyaltyProgramBL");
}
if(!$showCopyrightInput){
	$t->discard_block("origPage", "CopyrightInfoBL");
}






$t->set_var("TRANSFER_ADDRESS", WebUtil::htmlOutput($transferaddress));


// On the live site the Navigation bar needs to have hard-coded links to jump out of SLL mode... like http://www.example.com
// Also flash plugin can not have any non-secure source or browser will complain.. nead to change plugin to https:/
if(Constants::GetDevelopmentServer()){
	$t->set_var("SECURE_FLASH","");
	$t->set_var("HTTPS_FLASH","");
}
else{
	$t->set_var("SECURE_FLASH","TRUE");
	$t->set_var("HTTPS_FLASH","s");
}


$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


$t->pparse("OUT","origPage");


?>
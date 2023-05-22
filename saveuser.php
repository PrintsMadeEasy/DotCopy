<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();

// Make sure that Admin can't place an order from another domain.
Domain::enforceTopDomainID(Domain::getDomainIDfromURL());

// Prevent Cross-site Request Forgeries
WebUtil::checkFormSecurityCode();

// Get parameters from the URL
$registrationtype = WebUtil::GetInput("registrationtype", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$transferaddress = WebUtil::GetInput("transferaddress", FILTER_SANITIZE_STRING_ONE_LINE);
$fullname = WebUtil::GetInput("fullname", FILTER_SANITIZE_STRING_ONE_LINE);
$password1 = WebUtil::GetInput("password1", FILTER_SANITIZE_STRING_ONE_LINE);
$password2 = WebUtil::GetInput("password2", FILTER_SANITIZE_STRING_ONE_LINE);
$company = WebUtil::GetInput("company", FILTER_SANITIZE_STRING_ONE_LINE);
$email = WebUtil::GetInput("email", FILTER_SANITIZE_EMAIL);
$address = WebUtil::GetInput("address", FILTER_SANITIZE_STRING_ONE_LINE);
$address2 = WebUtil::GetInput("address2", FILTER_SANITIZE_STRING_ONE_LINE);
$city = WebUtil::GetInput("city", FILTER_SANITIZE_STRING_ONE_LINE);
$state = WebUtil::GetInput("state", FILTER_SANITIZE_STRING_ONE_LINE);
$zip = WebUtil::GetInput("zip", FILTER_SANITIZE_STRING_ONE_LINE);
$country = WebUtil::GetInput("country", FILTER_SANITIZE_STRING_ONE_LINE);
$phone = WebUtil::GetInput("phone", FILTER_SANITIZE_STRING_ONE_LINE);
$specialoffers = WebUtil::GetInput("specialoffers", FILTER_SANITIZE_STRING_ONE_LINE);
$hint = WebUtil::GetInput("hint", FILTER_SANITIZE_STRING_ONE_LINE);
$hearabout = WebUtil::GetInput("hearabout", FILTER_SANITIZE_STRING_ONE_LINE);
$cancelflag = WebUtil::GetInput("cancelflag", FILTER_SANITIZE_STRING_ONE_LINE);
$residential = WebUtil::GetInput("residential", FILTER_SANITIZE_STRING_ONE_LINE);
$copyright = WebUtil::GetInput("copyright", FILTER_SANITIZE_STRING_ONE_LINE, "N");
$loyalty = WebUtil::GetInput("loyalty", FILTER_SANITIZE_STRING_ONE_LINE, "N");


// If they hit the cancel button then just transfer them back to where ever they came from without modifying the DB
if(!empty($cancelflag)){
	TransferUser($transferaddress, $cancelflag);
	exit;
}



// Create a User Object to writing to the Database
$UserControlObj = new UserControl($dbCmd);

// We want to lock their address if they are a Sales Rep
$UserIsSalesRep = false;


if($registrationtype != "new" && $registrationtype != "checkout" && $registrationtype != "edit")
	throw new Exception("Illegal Registration type.");



// If we are editing, then fetch the previous info from the DB
// We need to overwrite the properties of the user Object with new data from the URL
if($registrationtype == "edit"){

	//Make sure they are logged in.
	$AuthObj = new Authenticate(Authenticate::login_general);
	$UserID = $AuthObj->GetUserID();
	
	$UserControlObj->LoadUserByID($UserID);
	
	$SalesCommissionsObj = new SalesCommissions($dbCmd, Domain::oneDomain());
	if($SalesCommissionsObj->CheckIfSalesRep($UserID))
		$UserIsSalesRep = true;
		
		
}


// Set variables from the URL that are common to both new and editing registration types
// The address will never be locked if they are trying to sign up for a new account... but it could be if they are editing.
if($UserControlObj->getAddressLocked() == "N"){

	// If the country is not in the U.S. then use the Internation State input box
	if($country == "US")
		$UserState = substr($state, 0, 2);
	else
		$UserState = WebUtil::GetInput("intl_state", FILTER_SANITIZE_STRING_ONE_LINE);

	// Log any Changes made to the Account Information (before updating)
	if($registrationtype == "edit")
		$userChangeLogFrom = $UserControlObj->getAccountDescriptionText(true);
		
	if(strlen($zip) < 5)
		WebUtil::PrintError("You must have at least 5 digits in your zip code.");

	$UserControlObj->setEmail($email);
	$UserControlObj->setName($fullname);
	$UserControlObj->setCompany($company);
	$UserControlObj->setAddress($address);
	$UserControlObj->setAddressTwo($address2);
	$UserControlObj->setCity($city);
	$UserControlObj->setCountry($country);
	$UserControlObj->setState($UserState);
	$UserControlObj->setZip($zip);
	$UserControlObj->setResidentialFlag($residential);
	$UserControlObj->setPhone($phone);
	
	// It is not used on all domains.
	if(!empty($specialoffers))
		$UserControlObj->setNewsletter($specialoffers);
	
	
	
	// Log any Changes made to the Account Information (after updating)
	if($registrationtype == "edit")
		$userChangeLogTo = $UserControlObj->getAccountDescriptionText(true);

}

$UserControlObj->setHint($hint);





if($registrationtype == "new" || $registrationtype == "checkout"){
	
	ValidateThisEmail($email); // Will exit if there is an error

	if ($UserControlObj->CheckIfEmailExistsInDB($email)){
		WebUtil::PrintError("It seems that you have already created an account with us. Try going to the Sign In page and logging in. Sorry for the inconvenience." );
	}
	
	VisitorPath::addRecord("User Account Created");

	// Session variables for giving a discount to an affiliate
	// This may have been set through a link on another website
	if(!isset($HTTP_SESSION_VARS['AffiliateName']))
		$HTTP_SESSION_VARS['AffiliateName'] = "";
	if(!isset($HTTP_SESSION_VARS['AffiliateDiscount']))
		$HTTP_SESSION_VARS['AffiliateDiscount'] = "";
	if(!isset($HTTP_SESSION_VARS['AffiliateExpires']))
		$HTTP_SESSION_VARS['AffiliateExpires'] = "";


	$UserControlObj->setAffiliateName($HTTP_SESSION_VARS['AffiliateName']);
	$UserControlObj->setAffiliateDiscount($HTTP_SESSION_VARS['AffiliateDiscount']);
	$UserControlObj->setAffiliateExpires($HTTP_SESSION_VARS['AffiliateExpires']);
	
	
	// Passwords are always set with new accounts, not necessarily for editing
	$UserControlObj->setPassword($password1);
	
	// How they heard about us is only done for new accounts
	$UserControlObj->setHearAbout($hearabout);
	
	// All new users must subscribe to newsletters... and then unsubscribe.
	$UserControlObj->setNewsletter("Y");
	
	// Record if the Loyalty/Copyright program was displayed to the user for this domain.
	$UserControlObj->setLoyaltyHiddenAtReg(LoyaltyProgram::displayLoyalityOptionForVisitor() ? "N" : "Y");
	$UserControlObj->setCopyrightHiddenAtReg(CopyrightCharges::displayCopyrightOptionForVisitor() ? "N" : "Y");
	
	// Record if the user selected to enroll in the loyalty program.  But only if it is available for this domain.
	if(!LoyaltyProgram::displayLoyalityOptionForVisitor())
		$UserControlObj->setLoyaltyProgram("N");
	else
		$UserControlObj->setLoyaltyProgram($loyalty);

	if(!CopyrightCharges::displayCopyrightOptionForVisitor())
		$UserControlObj->setCopyrightTemplates("N");
	else
		$UserControlObj->setCopyrightTemplates($copyright);

	// Insert the new user into the Database
	$UserID = $UserControlObj->AddNewUser();

	// Log them in through the session
	Authenticate::SetUserIDLoggedIn($UserID);

	// Set a cookie on his machine so we can remember him the next time he returns... Save for 360 days
	WebUtil::OutputCompactPrivacyPolicyHeader();
	setcookie ("UserEmailAddress", $email, time()+60*60*24*2000);
	
	// If the user just created an account, find out if they have any chat threads in this session.
	$dbCmd->UpdateQuery("chatthread", array("CustomerUserID"=>$UserID), "SessionID='".DbCmd::EscapeLikeQuery(WebUtil::GetSessionID())."'");

	TransferUser($transferaddress, $cancelflag);

	exit;

}
else if($registrationtype == "edit"){

	if ($UserControlObj->CheckIfEmailIsOwnedByAnother())
		WebUtil::PrintError("You can not change your email because the new address has already been registered in the system.");

	VisitorPath::addRecord("User Account Updated");
		
	// Only update the password if it is not blank
	// If they don't change the password, then they may leave it blank on the registration page
	if(!empty($password1)){
		
		// If the password has been changed... then reset the flag letting them know to update their password.
		if($password1 != $UserControlObj->getPassword())
			$UserControlObj->setPasswordUpdateRequired("N");
		
		$UserControlObj->setPassword($password1);
	}

	if(LoyaltyProgram::displayLoyalityOptionForVisitor($UserID))
		$UserControlObj->setLoyaltyProgram($loyalty);
		
	if(CopyrightCharges::displayCopyrightOptionForVisitor($UserID))
		$UserControlObj->setCopyrightTemplates($copyright);

	// Update the database
	$UserControlObj->UpdateUser();
	
	
	// Find out if any changes were made to the account info... if so then record the changes
	if(md5($userChangeLogFrom) != md5($userChangeLogTo)){
	
		UserControl::RecordChangesToUser($dbCmd, $UserControlObj->getUserID(), $UserID, $userChangeLogFrom, $userChangeLogTo);

		if($UserIsSalesRep){
			$SalesRepChangeLog = "Changed Account Info From:\n" . $userChangeLogFrom . "\nTo:\n" . $userChangeLogTo;
			SalesRep::RecordChangesToSalesRep($dbCmd, $UserControlObj->getUserID(), $UserID, $SalesRepChangeLog);
		}
	}

	TransferUser($transferaddress, $cancelflag);

}
else{
	WebUtil::PrintError("There was an error with the URL.");
}



function ValidateThisEmail($emailAddress){
	if(!WebUtil::ValidateEmail($emailAddress))
		WebUtil::PrintError("Your email address is invalid.");
}


function TransferUser($transferaddress, $cancelflag){
	
	session_write_close();

	// If we passed a $transferaddress in the URL then use that address.  Otherwise redirect them back to the my-account page.
	if(!empty($transferaddress)){

		header("Location: " . WebUtil::getFullyQualifiedDestinationURL($transferaddress, true), true, 302);

	}
	else{
		
		$transferURL = WebUtil::FilterURL(Domain::getWebsiteURLforDomainID(Domain::oneDomain()));
		
		if(empty($_SERVER['HTTPS']))
			$transferURL = "http://" . $transferURL;
		else
			$transferURL = "https://" . $transferURL;
			
		header("Location: " . $transferURL, true, 302);
	}
	
	exit;
}


?>
<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();




$user_sessionID =  WebUtil::GetSessionID();

//WebUtil::BreakOutOfSecureMode();


$productID = WebUtil::GetInput("pr", FILTER_SANITIZE_INT);
$phoneNumber = WebUtil::GetInput("ph", FILTER_SANITIZE_STRING_ONE_LINE);

$phoneNumber = WebUtil::FilterPhoneNumber($phoneNumber);


$domainIDfromURL = Domain::getDomainIDfromURL();

if(!preg_match("/^\d+$/", $productID) || !preg_match("/^\d+$/", $phoneNumber))
	WebUtil::PrintError("There is an error with this URL.   If the problem persists, please contact the webmaster");
	
	
$domainIDofProduct = Product::getDomainIDfromProductID($dbCmd, $productID);
if($domainIDofProduct != $domainIDfromURL){
	WebUtil::WebmasterError("Product/Domain Mismatch on Promo.php with ProductID: $productID");
	WebUtil::PrintError("The Product ID was not found. If the problem persists, please contact the webmaster");
}

$userIPaddress = WebUtil::getRemoteAddressIp();


// If someon has made more than 2 incorrect attempts at entering their phone number... they we must make them login.
$dbCmd->Query("SELECT COUNT(*) FROM wrongphoneaccess WHERE IPaddress='$userIPaddress'");
$inCorrectAttempts = $dbCmd->GetValue();


$UserIDforArtwork = 0;

if($inCorrectAttempts > 2){
	$AuthObj = new Authenticate(Authenticate::login_general);
	$UserIDforArtwork = $AuthObj->GetUserID();
}


if(empty($UserIDforArtwork)){

	// Find out if the phone number matches any of the users.
	// If the phone number matches more than 1 user, then make them login.
	$dbCmd->Query("SELECT ID FROM users WHERE PhoneSearch='" . DbCmd::EscapeSQL($phoneNumber) . "' AND DomainID=$domainIDfromURL");
	$numberOfAccounts = $dbCmd->GetNumRows();

	if($numberOfAccounts == 0){
		// This was an incorrect login attempt... so record their IP address to the DB and show an error message.
		$dbCmd->InsertQuery("wrongphoneaccess", array("IPaddress"=>$userIPaddress, "PhoneNumber"=>$phoneNumber));
		WebUtil::PrintError("You seem to have typed in the URL incorrectly.  Please try again.");
	}
	else if($numberOfAccounts > 1){
	
		// Since there is more than 1 account ask them to login so we can be more specifid.
		$AuthObj = new Authenticate(Authenticate::login_general);
		$UserIDforArtwork = $AuthObj->GetUserID();
	}
	else if($numberOfAccounts == 1){
		$UserIDforArtwork = $dbCmd->GetValue();
	}
	else{
		throw new Exception("An unknown error occured.");
	}
}



if($productID == "91"){

	$t = new Templatex();
	$t->set_file("origPage", "pen-jumbo-template.html");


	// This promo is for a Custom pen.
	// We want to find an artwork that goes well with that in the users account
	// Business cards, postcards, etc...  Letterhead,envelopes don't work well.
	$dbCmd->Query("SELECT MAX(po.ID) FROM projectsordered AS po INNER JOIN orders ON orders.ID = po.OrderID WHERE orders.UserID=" . intval($UserIDforArtwork). "
			AND (po.ProductID != 80 AND po.ProductID != 81 AND po.ProductID != 82 AND po.ProductID != 83 AND po.ProductID != 84 AND po.ProductID != 85)");
	$projectOrderedIDforPen = $dbCmd->GetValue();

	
	// Create a default Pen Project for the Session.
	$newProjectSessionID = ProjectSession::CreateNewDefaultProjectSession($dbCmd, $productID, $user_sessionID);
	
	$projectSessionObj = ProjectBase::getProjectObjectByViewType($dbCmd, "session", $newProjectSessionID);
	
	// If we can not find a match project to make artwork for... then we will start off with just a blank design.
	// Otherise get the artwork from the ProjectOrdered, convert it to a pen... and replace it in the Project Session.
	if(!empty($projectOrderedIDforPen)){
		
		$projectOrderedObj = ProjectBase::getProjectObjectByViewType($dbCmd, "ordered", $projectOrderedIDforPen);
		
		$convertedProjectObj = $projectOrderedObj->convertToProductID($productID);
		
		// Copy the Artwork from the "Converted Project Ordered" into the Session.
		$projectSessionObj->setArtworkFile($convertedProjectObj->getArtworkFile());
		$projectSessionObj->updateDatabase();
		
		// Just in case it is a double-sided artwork that needs to get changed.
		ProjectBase::ChangeArtworkBasedOnProjectOptions($dbCmd, $newProjectSessionID, "projectssession");
	}
	
	// Create the URL for the GetStarted button... we are just linking to the Editor using the new Project Session ID>
	$t->set_var("PROJECTRECORD", $newProjectSessionID);
	
	
	// Set this variable.  We will check to make sure it gets set on the following page.  If we cant find it then the person might not have cookies enabled.
	$HTTP_SESSION_VARS['initialized'] = 1;
	
	
	
	// Send email out to an administrator that this promotional page was accessed... but don't send it more than once for the same user... to filter out duplicates.
	$sessionPromoTrack = WebUtil::GetSessionVar("PromoNotify");
	$cookiePromoTrack = WebUtil::GetCookie("PromoNotify");
	if(empty($sessionPromoTrack) && empty($cookiePromoTrack)){
	
		$userObj = new UserControl($dbCmd);
		$userObj->LoadUserByID($UserIDforArtwork);
		$accountDetails = $userObj->getAccountDescriptionText(true);
	
	
		$AdminSubject = "Promotional URL Accessed: Jumbo Pens.";
		$AdminBody = WebUtil::htmlOutput($userObj->getName()) . " accessed the Jubmo Pen page.\n\n" . $accountDetails;
		
		$emailContactsForReportsArr = Constants::getEmailContactsForServerReports();
		foreach($emailContactsForReportsArr as $thisEmailContact)
			WebUtil::SendEmail("Promotional URL Notify", Constants::GetMasterServerEmailAddress(), "", $thisEmailContact, $AdminSubject, $AdminBody);

	
		// Set the variables so we don't repeat the notification.
		WebUtil::SetSessionVar("PromoNotify", "yes");
		$cookieTime = time()+60*60*24*90; // 3 months
		setcookie ("PromoNotify", "yes", $cookieTime);
	}
	
	$t->pparse("OUT","origPage");

}
else{
	WebUtil::PrintError("An error occured, the product was not found.");

}




?>
<?

require_once("library/Boot_Session.php");

$email = WebUtil::GetInput("email", FILTER_SANITIZE_EMAIL);


$dbCmd = new DbCmd();


if(!WebUtil::ValidateEmail($email))
	WebUtil::PrintError("The email address is not in a proper format.");

	
	
$t = new Templatex();

$t->set_file("origPage", "signin_getpassword-template.html");

	
	
$passiveAuthObj = Authenticate::getPassiveAuthObject();
if($passiveAuthObj->CheckIfLoggedIn()){
	
	$t->discard_block("origPage", "emailHasBeenSentBL");
	//$t->discard_block("origPage", "alreadyLoggedInBL");
	$t->discard_block("origPage", "userIsAnAdminBL");
	$t->discard_block("origPage", "notFoundBL");
	
	
	$t->pparse("OUT","origPage");
	exit;
}


	
// Make sure someone can't force a password to come through email (when they are listening to traffic) ... or flood a user's inbox.
WebUtil::checkFormSecurityCode();



// Now find all of the User IDs in the system (for each domain) belonging to the Email address.
// If one of the UserIDs has member access then they can't use the lost password finder.
$dbCmd->Query("SELECT ID FROM users WHERE Email LIKE '".DbCmd::EscapeLikeQuery($email)."'");
$userIDsArr = $dbCmd->GetValueArr();

foreach($userIDsArr as $thisUserID){
	if($passiveAuthObj->CheckIfUserIDisMember($thisUserID)){
		
		$t->discard_block("origPage", "emailHasBeenSentBL");
		$t->discard_block("origPage", "alreadyLoggedInBL");
		//$t->discard_block("origPage", "userIsAnAdminBL");
		$t->discard_block("origPage", "notFoundBL");
		
		$t->pparse("OUT","origPage");
		exit;
	}
}

	
$domainIDfromURL = Domain::oneDomain();
$domainKeyFromURL = Domain::getDomainKeyFromID($domainIDfromURL);
$domainEmailConfigObj = new DomainEmails($domainIDfromURL);


$dbCmd->Query("SELECT COUNT(*) FROM lostpasswordattempts WHERE DomainID=$domainIDfromURL AND Date > DATE_ADD(NOW(), INTERVAL -24 HOUR ) AND GoodOrBad='B'");
$invalidAttemptsOnDomainIn24hours = $dbCmd->GetValue();

if($invalidAttemptsOnDomainIn24hours > 200){
	
	WebUtil::WebmasterError("The lost password finder appears to have a brute force attack going on.");
	
	session_write_close();
	
	sleep(5);
	
	WebUtil::PrintError("The lost password tool is currently broken. Check back later or ask Customer Service for assistance.");
}

	
// This tries to keep someone from doing some kind of brute force to find out if Email addresses are valid or something.
// This session variable should be set to "1" on the sign-in page.  That is the only place that you see a link 
// ... to the lost password finder.  This is minor attempt to make sure that a browser is making this request and not an automated bot. 
$numberOfAttempts = WebUtil::GetSessionVar("PasswordRetrievalAttempts");
if(empty($numberOfAttempts) || $numberOfAttempts > 10){
	session_write_close();
	sleep(5);
	WebUtil::PrintError("You have exceeded the number of times that you may use this password tool for today.  Please ask Customer Service for assistance.");
}
WebUtil::SetSessionVar("PasswordRetrievalAttempts", ($numberOfAttempts + 1));
	

$userControlObj = new UserControl($dbCmd);

if(!$userControlObj->LoadUserByEmail($email, $domainIDfromURL)){

	$dbCmd->InsertQuery("lostpasswordattempts", array("DomainID"=>$domainIDfromURL, "IPaddress"=>WebUtil::getRemoteAddressIp(), "Email"=>$email, "Date"=>time(), "GoodOrBad"=>"B"));
	
	$t->discard_block("origPage", "emailHasBeenSentBL");
	$t->discard_block("origPage", "alreadyLoggedInBL");
	$t->discard_block("origPage", "userIsAnAdminBL");
	//$t->discard_block("origPage", "notFoundBL");

	session_write_close();
	sleep(5);
		
	$t->pparse("OUT","origPage");
	exit;
}

$dbCmd->InsertQuery("lostpasswordattempts", array("DomainID"=>$domainIDfromURL, "IPaddress"=>WebUtil::getRemoteAddressIp(), "Email"=>$email, "Date"=>time(), "GoodOrBad"=>"G"));


$message = $userControlObj->getName() . ",\n\nWe understand that you forgot your password to $domainKeyFromURL .  Try logging in with...\n\nEmail: $email \nPassword: " . $userControlObj->getPassword() . "\n\n";

WebUtil::SendEmail($domainEmailConfigObj->getEmailNameOfType(DomainEmails::CUSTSERV), $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::CUSTSERV), $userControlObj->getName(), $email, "Lost Password Retrieval", $message);


$t->set_var("HINT", WebUtil::htmlOutput($userControlObj->getHint()));

$t->set_var("EMAIL_ADDRESS", WebUtil::htmlOutput($email));


VisitorPath::addRecord("Lost Password Retrieval");

//$t->discard_block("origPage", "emailHasBeenSentBL");
$t->discard_block("origPage", "alreadyLoggedInBL");
$t->discard_block("origPage", "userIsAnAdminBL");
$t->discard_block("origPage", "notFoundBL");

session_write_close();
sleep(5);

$t->pparse("OUT","origPage");


?>
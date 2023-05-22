<?

require_once("library/Boot_Session.php");

$email = WebUtil::GetInput("email", FILTER_SANITIZE_EMAIL);
$pw = WebUtil::GetInput("pw", FILTER_SANITIZE_STRING_ONE_LINE);	// password
$pwe = WebUtil::GetInput("pwe", FILTER_SANITIZE_STRING_ONE_LINE);	// password encrypted
$rememberpassword = WebUtil::GetInput("rememberpassword", FILTER_SANITIZE_STRING_ONE_LINE);
$redirect = WebUtil::GetInput("redirect", FILTER_SANITIZE_URL);  

// This data should alwasy been encrypted.
WebUtil::RunInSecureModeHTTPS();

$dbCmd = new DbCmd();

$domainObj = Domain::singleton();

$domainIDfromURL = Domain::getDomainIDfromURL();

$domainWebsiteURL = Domain::getWebsiteURLforDomainID($domainIDfromURL);

// Make sure that Admin can't place an order from another domain.
Domain::enforceTopDomainID($domainIDfromURL);

/*
// For Loing Links... we can't generate the Security Code for the users's session
// That is because it will be used in a new browser window.
// A malicious person can't guess the security salt in the MD5 ... but they might be able to ask customer service for a login link and use that for some type of redirection.  Not a huge threat I don't think.
if(empty($pwe)){

	try{
		WebUtil::checkFormSecurityCode();
	
	}
	catch (ExceptionPermissionDenied $e){
		header("Content-Type: text/xml");
		header("Pragma: public");
		session_write_close();
	
		print "<?xml version=\"1.0\" ?>\n";
		print "<response>"; 
		print "<success>bad</success>";
		print "<description>". htmlspecialchars("Security Code: " . $e->getMessage()) ."</description>";
		print "</response>"; 
		exit;
	}
}
*/



// Normally there should only be a clear Text password or an Encrypted form
// If we have an encrypted version (not null)... then lets prefer to use that one.
if(!empty($pwe))
	$pw = $pwe;

// Method returns a string with an error message or with the letters "OK".
$LoginResult = Authenticate::CheckUserNamePass($dbCmd, $email, $pw, $domainIDfromURL);

if($LoginResult == "OK"){
	
	VisitorPath::addRecord("Login Accepted");

	// Set a cookie on his machine so we can remember him the next time he returns... Save for 360 days --#
	WebUtil::OutputCompactPrivacyPolicyHeader();
	setcookie ("UserEmailAddress", $email, time()+60*60*24*360, NULL, NULL, FALSE, TRUE);


	// Get the User ID from the email address
	$UserControlObj = new UserControl($dbCmd);
	$UserControlObj->LoadUserByEmail($email, $domainIDfromURL);

	// Record in the DB the last time that this user has logged in
	$UserControlObj->setDateLastUsed(time());
	$UserControlObj->UpdateUser();

	$UserID = $UserControlObj->getUserID();

	// Log them in through the session
	Authenticate::SetUserIDLoggedIn($UserID);

	// If we are using a "login link" ... erase the hash for the user selected domains. Otherwise an Admins computer will be logged in under a different account... 
	// ... and the webmaster will get error reports about a user having selected domains that they don't have permission too.
	if(!empty($pwe)){
		setcookie("SelectedDomains", "");
		$domainObj->setDomains(array());
	}

	// If they are an administrator we MUST set a permanent cookie on their machine to remember them
	// The reason is we test the UserName and Password on every authentication to make sure someone is not spoofing the session somehow
	$AuthObj = new Authenticate(Authenticate::login_general);
	if($rememberpassword == "yes" || $AuthObj->CheckIfBelongsToGroup("MEMBER")){

		// A remember should have their permanent cookies expire sooner.
		if($AuthObj->CheckIfBelongsToGroup("MEMBER"))
			$DaysToRemember = 60;
		else
			$DaysToRemember = 300;

		$cookieTime = time()+60*60*24 * $DaysToRemember;

		if(Constants::GetDevelopmentServer())
			$secureFlag = false;
		else
			$secureFlag = true;
		
		// If the user is a Member... then we should tell the browser only to transmit the cookie when communication is encrypted over HTTPS.
		// This will keep someone from breaking into our system by network sniffing
		if($AuthObj->CheckIfBelongsToGroup("MEMBER")){
			
			$securitySalt = Authenticate::getSecuritySaltADMIN();
			
			setcookie ("PreAuthUserID", $UserID, $cookieTime, NULL, NULL, $secureFlag, TRUE);
			setcookie ("PreAuthUserPW", md5(($pw . $securitySalt)), $cookieTime, NULL, NULL, $secureFlag, TRUE);
		}
		else{
			$securitySalt = Authenticate::getSecuritySaltBasic();
			setcookie ("PreAuthUserID", $UserID, $cookieTime, NULL, NULL, false, false);
			setcookie ("PreAuthUserPW", md5(($pw . $securitySalt)), $cookieTime, NULL, NULL, false, false);
		}
	}
	
	
	// If the user just created an account, find out if they have any chat threads in this session.
	$dbCmd->UpdateQuery("chatthread", array("CustomerUserID"=>$UserID), "SessionID='".DbCmd::EscapeLikeQuery(WebUtil::GetSessionID())."'");
	


	// If this parameter is passed in, then the script will redirect instead of printing XML
	if($redirect){

		// Use the keyword home to go to the home page and break out of secure mode.
		if($redirect == "home")
			$redirect = "http://$domainWebsiteURL";

		// For Login Links... we may be setting cookies... so we need to print a redirect statement or the browser won't receive the cookie headers.
		if(!empty($pwe)){
			print "<html>\n<script>document.location = '$redirect';</script>\n</html>";
			exit;
		}
		else{
			session_write_close();
			header("Location: ". WebUtil::FilterURL($redirect), true, 302);
			exit;
		}
	}
	

	// It seems that when you hit session_start it will send a Pragma: NoCache in the header
	// When comminicating over HTTPS there is a bug with IE.  It can not get the flash documents after they have finished downloading because they have already expired
	// We can allow the file to be cached be sending this header.  When requesting the document you can put a ?noache=timestamp
	// This is the only way to get flash communication to work over HTTPS with session variables
	header("Content-Type: text/xml");
	header("Pragma: public");
	session_write_close();
	
	sleep(2);

	print "<?xml version=\"1.0\" ?>\n";
	print "<response>"; 
	print "<success>good</success>";
	print "<description></description>";
	print "</response>"; 


	
}
else {

	VisitorPath::addRecord("Login Rejected");
	
	// Well, then there is an error of some kind
	header("Content-Type: text/xml");
	header("Pragma: public");
	session_write_close();

	// Keep a hacker from doing a brute force entry.
	// If a bad guy ever takes advantage of this sleep() command to use a DOS attack there are some other clever ways to thwart them.
	// A good idea could be writing every UserID (attempting to login) to a DB table (that is cleaned out every 2 days by the cron.
	// .... if there are ever more than 100 login attempts by any single UserID within the table then return "password invalid" even if the password is good.
	// But I can think of a lot more ways to launch a DOS attack on this server by probing URLS that cause image manipulations.
	sleep(5);

	print "<?xml version=\"1.0\" ?>\n";
	print "<response>"; 
	print "<success>bad</success>";
	print "<description>". WebUtil::htmlOutput($LoginResult) ."</description>";
	print "</response>"; 
}




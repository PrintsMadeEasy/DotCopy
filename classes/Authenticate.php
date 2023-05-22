<?

class Authenticate {


	//Member variables that will hold hashes of all the group/permission information
	//We will set them up manually within the contructor for now... but maybe in the future hook up to the DB
	private $_GroupUserHash = array();
	private $_GroupPermissionHash = array();

	//Member variable that will be filled up after the contructor is run.
	//Each element in the array is a group that the user belongs to.
	private $_GroupsThatUserBelongsTo = array();
	
	// Speed up performance if we are checking what domains a user has permission to view.
	private $_CacheUserDomainIDs = array();

	private $_RedirectionURL;
	
	private $_afterLoginSecureFlag;

	// If this is empty then we will look inside the Session Variables to locate our User ID.  
	// Otherwise use the ID that is passed Otherwise, all instances will of Authentication objects during the Script/Thread will share the same Override (because it is a static variable).
	private static $_UserIDoverride;

	private $_LoginType;
	
	// to cache the results of a DB query once we know the account status is good.
	private $_accountStatusGoodFlag;
	
	// To cache the results of who is a member, in case we are checking many users in a loop (like a user search page).
	static private $allUserIDsWhoAreMembersArr;
	
	// This is like a singleton for a "passive Auth object".  
	// We can't use singletons on "Admin" and "General" logins because the constructor performs authentication procedures
	private static $passiveAuthObj;
	
	const login_general = "login_general";
	const login_ADMIN = "login_ADMIN";
	const login_secure = "login_secure";
	const login_OVERRIDE = "override";
	const login_passive = "passive";


	private $_dbCmd;

	###-- Constructor --###
	#-- $AuthType can be 1 of 3 values --#
	// login_general - After a successful signin... it will take the user to a page without HTTPS
	// login_secure - After a successful login ... it will redirect them to secure page that they were trying to reach... such as the checkout page.
	// login_ADMIN is similar to "login_secure" ... except that that it will ensure the user is a belongs to group "MEMBER" and has a permaenent cookie set.
	// If you "Force" a user ID then you must use an "override" login type.  And likewise... if you have an "override" login type then you must specify an ForcedUserID
	// a "passive" login type will not authenticate the user... or redirect to sign-in page.  Calling GetUserID will fail subsequently if the user is not logged in with a "passive" login.
	// "passive" login will not Set the groups/permissions unless the person is logged in.
	function __construct($AuthType, $ForceUserID=0){

		if(!in_array($AuthType, array(self::login_general, self::login_ADMIN, self::login_secure, self::login_OVERRIDE, self::login_passive)))
			throw new Exception("Invalid AuthType...");

		if(!empty($ForceUserID) && $AuthType != self::login_OVERRIDE)
			throw new Exception("Forcing a User ID must have an overridden Login Type");
		if($AuthType == self::login_OVERRIDE && empty($ForceUserID))
			throw new Exception("If you are overriding a User you must supply a User ID");

		$this->_LoginType = $AuthType;

		$this->_dbCmd = new DbCmd();

		$this->_RedirectionURL = "";

		if($ForceUserID != 0){
			if(!UserControl::CheckIfUserIDexists($this->_dbCmd, $ForceUserID))
				throw new Exception("Error in Authentication constructor with UserID");
				
			if(!$this->AccountStatusIsGood($ForceUserID))
				throw new Exception("Error with Account status on override.");
				
			// In case the classes use the Passive Object in subsequent Requests... it will use the Overriden Authentication Object
			self::$passiveAuthObj = $this;
		}
		
		self::$_UserIDoverride = $ForceUserID;

		
		if($AuthType == self::login_secure || $AuthType == self::login_ADMIN)
			$this->_afterLoginSecureFlag = true;
		else
			$this->_afterLoginSecureFlag = false;

		// Ensure that they are logged in... Otherwise they get redirected to the sign in page and the script will not proceed any further.
		if($AuthType == self::login_general || $AuthType == self::login_secure || $AuthType == self::login_ADMIN)
			$this->EnsureLoggedIn();

		
		$this->InitializeGroupsAndPermission();

		// Run the following function to populate this object with the groups that the user belongs to
		if($AuthType != self::login_passive || $this->CheckIfLoggedIn()){
		
			$this->SetGroupsThatUserBelongsTo($this->GetUserID());
			
			// Make sure that they have permission to view
			$this->EnsureDomainPrivelages();
		}
		
	
		if($AuthType == self::login_ADMIN)
			$this->EnsureMemberSecurity();
	}
	

	#--- BEGIN Static functions ---#
	
	// If you just want to test a users authentication privelages.
	// Make sure to clear the override as soon as possible to keep the rest of the script execution using your own UserID
	static function clearUserOverride(){
		self::$_UserIDoverride = 0;
	}
	
	// static method to retrieve a singleton of a passive authenication object.
	/**
	 * @return Authenticate
	 */
	static function getPassiveAuthObject(){
		
		if(!empty(self::$passiveAuthObj))
			return self::$passiveAuthObj;
	
		self::$passiveAuthObj = new Authenticate(Authenticate::login_passive);
	
		return self::$passiveAuthObj;	
	}
	
	

	// Pass in a reference to a database object
	// Will check the username and password and see if they are good --#
	// Will also make sure that the account has't been disabled
	// If good, the function will return the string "OK".  It not the string will contain the error message such as....  "Invalid Password", "Account Not Found", "Disabled", etc.
	// The password can be passed into this function in clear text format or in encrypted form.  The encrypted form is the MD5 of the password with some salt.
	static function CheckUserNamePass(DbCmd $dbCmd, $Email, $Password, $domainID){

		if(!WebUtil::ValidateEmail($Email))
			return "The email address is not in proper format.";
			
		if(!Domain::checkIfDomainIDexists($domainID))
			throw new Exception("Error in method CheckUserNamePass. The Domain ID is invalid.");

		$retMsg = "An unknown error occured";

		$dbCmd->Query( "SELECT ID, Password, AccountStatus FROM users WHERE Email LIKE \"" . DbCmd::EscapeLikeQuery($Email) . "\" AND DomainID='".intval($domainID)."'" );

		if( $dbCmd->GetNumRows() != 1 ){
			$retMsg = "Your account was not found.";
		}
		else{
			$dbRow = $dbCmd->GetRow();
			$userID = $dbRow["ID"];
			$pwd = $dbRow["Password"];
			$AcntStatus = $dbRow["AccountStatus"];
			
			$passiveAuthObj = Authenticate::getPassiveAuthObject();
			if($passiveAuthObj->CheckIfUserIDisMember($userID))
				$securitySalt = self::getSecuritySaltADMIN();
			else
				$securitySalt = self::getSecuritySaltBasic();

			if(($pwd == $Password) || (md5($pwd . $securitySalt) == $Password))
				$retMsg = "OK";
			else
				$retMsg = "Your password is invalid.";

			// Check if the Account Status is not good.
			if($AcntStatus != "G")
				$retMsg = "Your account has expired.  Please contact customer service.";
		}

		return $retMsg;

	}
	
	
	// This is meant to be a rotating Security Salt for MD5 algorythms.
	// It works off of the date... so that even if a hacker manages to steal cookies off of the Administrator's machine
	// Then that login cookie will only stay good for a little while.  They would have to steal the cookie again or learn the user's password.
	static function getSecuritySaltADMIN(){
		
		$numberOfDaysToExpirePassword = 4;
		
		// If you want the password to expire every $X mintues, simiply change this variable to ... $numberOfYear_0_360 = date("i");
		$numberOfYear_0_360 = date("z");
		
		// Start out with the day that we are on... a number of the year between 0 and 359
		// If we are on a day that has an empty Modulo, then let's use that number for our security salt.
		// Otherwise, start counting backwards from the day num (0-359) until we arrive at the last date that has a Modulo of zero.
		// ... being careful to avoid float comparison issues by rounding.
		$rotationIndicator = 0;
		if(round($numberOfYear_0_360 % $numberOfDaysToExpirePassword, 1) == 0){
			$rotationIndicator = $numberOfYear_0_360;
		}
		else{
			for($i=$numberOfYear_0_360; $i>0; $i--){
				if(round($i % $numberOfDaysToExpirePassword, 1) == 0){
					$rotationIndicator = $i;
					break;
				}
			}
		}
		
		// Start off with the year... so if it rotates to another year we will never have matching signatures, even if the password never changes.
		// Although, around January 1st the user may have to login 2 days back to back.
		return Constants::getAdminSecuritySalt() . date("Y") . $rotationIndicator;
	}
	static function getSecuritySaltBasic(){
		
		return Constants::getGeneralSecuritySalt();
	}
	

	// Call this function to login a person by their UserID
	static function SetUserIDLoggedIn($uID){

		WebUtil::SetSessionVar('UserIDloggedIN', intval($uID));

	}
	
	static function ClearPermanentCookie(){
	
		$cookieTime = time() - 360;
		setcookie ("PreAuthUserID", "", $cookieTime);
		setcookie ("PreAuthUserPW", "", $cookieTime);

	}

	// Returns True or false depending on whether the User is Logged in or not.
	static function CheckIfVisitorLoggedIn(DbCmd $dbCmd){
		$passvAuthObj = Authenticate::getPassiveAuthObject();
		return $passvAuthObj->CheckIfLoggedIn();
	}
	
	
	// Returns true or false depending on whether the user has permission to view the domain.
	// If they are not logged in, then they can only view the domain that is in their browser.
	function CheckIfUserCanViewDomainID($domainID){

		$allUserDomainsArr = $this->getUserDomainsIDs();
			
		if(in_array($domainID, $allUserDomainsArr))
			return true;
		else
			return false;

	}

	
	// If this function is called, then it expects the page being viewed require MEMBER authentication from the Auth object 
	// Makes sure that we are in SSL https mode.
	// When a MEMBER logs into the site, a permanent cookie should be set on their PC containing an MD5 hash of their password.
	// We are going to look for this cookie and compare the Hashed password to the password on our server ... redirect them to the sign in page if we can't find it  -#
	// We need to test the MemberSecurity on all important pages to make sure that a hacker is not somehow spoofing a session variable on the server somehow... it is always testing the name and password
	// Returns nothing 
	function EnsureMemberSecurity(){
	
		$this->EnsureGroup("MEMBER");

		// Record their IP address
		$userIDtoRecord = $this->GetUserID();
	
		$ipAccessObj = new IPaccess($this->_dbCmd);
		$ipAccessObj->addLogEntry($userIDtoRecord);
		
		if(!$this->CheckIfBelongsToGroup("FRANCHISE_OWNER") && !$ipAccessObj->isPermitted($userIDtoRecord)){
			print "Please ask an administrator to enable your account from this IP Address. <br><b>" . WebUtil::getRemoteAddressIp() . "</b>";
			exit;
		}

		//First check the constants file and see if we should go to this level of security... it may not work on development servers with an IP address
		if(!Constants::AuthenticateMemberSecurity())
			return;

			
		// Make sure we have https://bla in our browser
		WebUtil::RunInSecureModeHTTPS();


		$userIDloggedIn = WebUtil::GetSessionVar('UserIDloggedIN', "");
		$PreAuthUserID = WebUtil::GetCookie("PreAuthUserID");
		$PreAuthUserPW = WebUtil::GetCookie("PreAuthUserPW");
		

		// The cookie should match the session variable
		if($userIDloggedIn != $PreAuthUserID)
			$this->RedirectUserToSignInPage();

		
		// Admins are required to have a permanent cookie on their machine.
		if(!preg_match("/^\d{1,11}$/", $userIDloggedIn) || empty($PreAuthUserPW))
			$this->RedirectUserToSignInPage();
	
			
		// Now get the User ID from the Session ID and get the corresponding Password from the database 
		$this->_dbCmd->Query("SELECT Password FROM users WHERE ID=" . intval($userIDloggedIn));
		if($this->_dbCmd->GetNumRows() != 1)
			$this->RedirectUserToSignInPage();
			
		$Password = $this->_dbCmd->GetValue();	

		// This will make sure that the password in the user's cookie matches the password on the server 
		if(md5(($Password . self::getSecuritySaltADMIN())) <> $PreAuthUserPW){
			WebUtil::SetSessionVar("UserIDloggedIN", "");
			$this->RedirectUserToSignInPage();
		}
	
			
		// Every time this method runs, it records an entry into the IPlogger table.  
		// Make sure they don't have too many accesses within 5 hours or it could be someone harvesting the database.
		$this->_dbCmd->Query("SELECT COUNT(*) FROM iplogger WHERE AccessDate > DATE_ADD(NOW(), INTERVAL -5 HOUR ) AND UserID=$userIDtoRecord");
		if($this->_dbCmd->GetValue() > 3000){
			WebUtil::WebmasterError("The user ID: $userIDloggedIn has had too much IP Access within a short amount of time. Name: " . UserControl::GetNameByUserID($this->_dbCmd, $userIDtoRecord));
			print "Your IP Address has had too much activity within a short amount of time. The system will let you back in shortly, or ask your manager to release the hold.";
			exit;
		}
	
		// See if there have been 2 IP addresses by the same user within 10 minutes of each other.
		$this->_dbCmd->Query("SELECT DISTINCT(IPaddress) FROM iplogger WHERE AccessDate > DATE_ADD(NOW(), INTERVAL -15 Minute ) AND UserID=$userIDtoRecord");
		$ipAddressesIn10Min = $this->_dbCmd->GetValueArr();
		if(sizeof($ipAddressesIn10Min) > 1){
			
			if(sizeof($ipAddressesIn10Min) == 2 && in_array("74.62.46.165", $ipAddressesIn10Min) && in_array("205.214.232.18", $ipAddressesIn10Min)){
				// Don't send alerts from the Dot Graphics load balancer.
			}
			else if($this->CheckIfBelongsToGroup("FRANCHISE_OWNER")){
				// Don't send alerts for franchise owners
			}
			else{

				$tmpNameIpCheck = Constants::GetTempDirectory() . "/" . "IPADD_User_" . $userIDtoRecord . ".txt";
	
				$lastChecked = 0;
				if(file_exists($tmpNameIpCheck)){
					$lastChecked = filemtime($tmpNameIpCheck);
				}
		
				$secondsSinceLastCheck = time() - $lastChecked;
	
				// The alarms will fire off no more than once every 20 minutes.
				if($secondsSinceLastCheck > (20 * 60)){
					WebUtil::WebmasterError("The user ID: $userIDloggedIn has add more than 2 IP addresses used within 15 mintues. Name: " . UserControl::GetNameByUserID($this->_dbCmd, $userIDtoRecord) . " IPs: " . serialize($ipAddressesIn10Min), "Multiple IP Addresses");
				
					// Write to the temp file so we update the "modified time"
					$fp = fopen($tmpNameIpCheck, "w");
					fwrite($fp, " ");
					fclose($fp);				
				}
			}
		}
		
	}





	//In the future we may extract from the DB or something
	function InitializeGroupsAndPermission(){


		$this->_GroupUserHash["SUPERADMIN"] = array();
		$this->_GroupUserHash["ADMIN"] = array();
		$this->_GroupUserHash["SUPERCS"] = array();
		$this->_GroupUserHash["CS"] = array();
		$this->_GroupUserHash["VENDOR"] = array();
		$this->_GroupUserHash["MEMBER"] = array();
		$this->_GroupUserHash["ARTIST"] = array();
		$this->_GroupUserHash["ACCOUNTANT"] = array();
		$this->_GroupUserHash["SALESMASTER"] = array();
		$this->_GroupUserHash["MARKETING"] = array();
		$this->_GroupUserHash["PRODUCTION"] = array();
		$this->_GroupUserHash["EDITOR"] = array();
		$this->_GroupUserHash["MAIL_ADMIN"] = array();
		$this->_GroupUserHash["MAIL_ASSISTANT"] = array();
		$this->_GroupUserHash["MAIL_PRODUCTION"] = array();
		$this->_GroupUserHash["SALES_FORCE"] = array();
		$this->_GroupUserHash["IPSECURITY"] = array();
		$this->_GroupUserHash["EMAIL_MESSAGE_CONTENT"] = array();
		$this->_GroupUserHash["PRODUCT_MANAGER"] = array();
		$this->_GroupUserHash["FRANCHISE_OWNER"] = array();
		

		#-- Deffine who are the Super Administrators
		array_push($this->_GroupUserHash["SUPERADMIN"], 2);
		array_push($this->_GroupUserHash["SUPERADMIN"], 97829);
		array_push($this->_GroupUserHash["SUPERADMIN"], 117246);
		array_push($this->_GroupUserHash["SUPERADMIN"], 52204);
		array_push($this->_GroupUserHash["SUPERADMIN"], 435466);
		


		#-- Deffine the Admin Group and all user IDs in that group
		array_push($this->_GroupUserHash["ADMIN"], 2);
		array_push($this->_GroupUserHash["ADMIN"], 97829);
		array_push($this->_GroupUserHash["ADMIN"], 117246);
		array_push($this->_GroupUserHash["ADMIN"], 52204);
		array_push($this->_GroupUserHash["ADMIN"], 435466);
		array_push($this->_GroupUserHash["ADMIN"], 6940);
		array_push($this->_GroupUserHash["ADMIN"], 8269);
		array_push($this->_GroupUserHash["ADMIN"], 329);

		
		#-- Deffine the Accountant Group and all user IDs in that group
		//array_push($this->_GroupUserHash["ACCOUNTANT"], 23804);
		
		#-- Deffine the Franchise Owner Group and all user IDs in that group
		array_push($this->_GroupUserHash["FRANCHISE_OWNER"], 407015);
		array_push($this->_GroupUserHash["FRANCHISE_OWNER"], 430626);

		#-- Deffine the Sales Master Group and all user IDs in that group
		array_push($this->_GroupUserHash["SALESMASTER"], 2);
		array_push($this->_GroupUserHash["SALESMASTER"], 97829);
		array_push($this->_GroupUserHash["SALESMASTER"], 117246);
		array_push($this->_GroupUserHash["SALESMASTER"], 52204);
		array_push($this->_GroupUserHash["SALESMASTER"], 435466);
		
				
		#-- Deffine the Email Notify Group and all user IDs in that group
		array_push($this->_GroupUserHash["EMAIL_MESSAGE_CONTENT"], 2);
		array_push($this->_GroupUserHash["EMAIL_MESSAGE_CONTENT"], 97829);
		array_push($this->_GroupUserHash["EMAIL_MESSAGE_CONTENT"], 117246);
		array_push($this->_GroupUserHash["EMAIL_MESSAGE_CONTENT"], 52204);
		array_push($this->_GroupUserHash["EMAIL_MESSAGE_CONTENT"], 435466);
		
		

		#-- Deffine the Customer Service Group and all user IDs
		array_push($this->_GroupUserHash["CS"], 8269);
		array_push($this->_GroupUserHash["CS"], 97829);
		//array_push($this->_GroupUserHash["CS"], 18562);
		array_push($this->_GroupUserHash["CS"], 2);
		array_push($this->_GroupUserHash["CS"], 6940);
		array_push($this->_GroupUserHash["CS"], 24245);
		//array_push($this->_GroupUserHash["CS"], 22885);
		//array_push($this->_GroupUserHash["CS"], 376257);
		array_push($this->_GroupUserHash["CS"], 41881);
		array_push($this->_GroupUserHash["CS"], 44948);
		//array_push($this->_GroupUserHash["CS"], 363168);
		array_push($this->_GroupUserHash["CS"], 54644);
		//array_push($this->_GroupUserHash["CS"], 204742);
		array_push($this->_GroupUserHash["CS"], 388797);
		//array_push($this->_GroupUserHash["CS"], 325380);
		array_push($this->_GroupUserHash["CS"], 203413);
		array_push($this->_GroupUserHash["CS"], 205054);
		array_push($this->_GroupUserHash["CS"], 363376);
		array_push($this->_GroupUserHash["CS"], 297241);
		array_push($this->_GroupUserHash["CS"], 400314);
		array_push($this->_GroupUserHash["CS"], 403110);
		array_push($this->_GroupUserHash["CS"], 388813);
		//array_push($this->_GroupUserHash["CS"], 406089);
		array_push($this->_GroupUserHash["CS"], 371989);
		array_push($this->_GroupUserHash["CS"], 453057);
		array_push($this->_GroupUserHash["CS"], 458268);
		array_push($this->_GroupUserHash["CS"], 430626);
		array_push($this->_GroupUserHash["CS"], 407015);
		//array_push($this->_GroupUserHash["CS"], 400308);
		array_push($this->_GroupUserHash["CS"], 400303);
		array_push($this->_GroupUserHash["CS"], 400206);
		array_push($this->_GroupUserHash["CS"], 418862);
		array_push($this->_GroupUserHash["CS"], 401315);
		array_push($this->_GroupUserHash["CS"], 401149);
		array_push($this->_GroupUserHash["CS"], 63402);
		array_push($this->_GroupUserHash["CS"], 45223);
		//array_push($this->_GroupUserHash["CS"], 90348);
		//array_push($this->_GroupUserHash["CS"], 102774);
		array_push($this->_GroupUserHash["CS"], 89676);
		array_push($this->_GroupUserHash["CS"], 252972);
		//array_push($this->_GroupUserHash["CS"], 288434);
		array_push($this->_GroupUserHash["CS"], 109779);
		array_push($this->_GroupUserHash["CS"], 240890);
		array_push($this->_GroupUserHash["CS"], 90085);
		array_push($this->_GroupUserHash["CS"], 92046);
		array_push($this->_GroupUserHash["CS"], 110300);
		//array_push($this->_GroupUserHash["CS"], 108266);
		array_push($this->_GroupUserHash["CS"], 76638);
		array_push($this->_GroupUserHash["CS"], 117246);
		array_push($this->_GroupUserHash["CS"], 52204);
		array_push($this->_GroupUserHash["CS"], 435466);
		array_push($this->_GroupUserHash["CS"], 329);
		//array_push($this->_GroupUserHash["CS"], 187121);
		//array_push($this->_GroupUserHash["CS"], 192860);
		array_push($this->_GroupUserHash["CS"], 196562);
		array_push($this->_GroupUserHash["CS"], 411174);
		array_push($this->_GroupUserHash["CS"], 297252);
		array_push($this->_GroupUserHash["CS"], 329109);
		array_push($this->_GroupUserHash["CS"], 367182);
		array_push($this->_GroupUserHash["CS"], 400862);
		array_push($this->_GroupUserHash["CS"], 400863);
		array_push($this->_GroupUserHash["CS"], 374130);
		array_push($this->_GroupUserHash["CS"], 374131);
		array_push($this->_GroupUserHash["CS"], 372982);
		array_push($this->_GroupUserHash["CS"], 366641);
		array_push($this->_GroupUserHash["CS"], 297519);
		array_push($this->_GroupUserHash["CS"], 209133);
		array_push($this->_GroupUserHash["CS"], 214147);
		array_push($this->_GroupUserHash["CS"], 358364);
		array_push($this->_GroupUserHash["CS"], 214568);
		array_push($this->_GroupUserHash["CS"], 218151);
		array_push($this->_GroupUserHash["CS"], 219316);
		array_push($this->_GroupUserHash["CS"], 221912);
		array_push($this->_GroupUserHash["CS"], 237969);
		array_push($this->_GroupUserHash["CS"], 242476);
		array_push($this->_GroupUserHash["CS"], 250137);
		array_push($this->_GroupUserHash["CS"], 438191);
		array_push($this->_GroupUserHash["CS"], 353856);
		array_push($this->_GroupUserHash["CS"], 64559);
		array_push($this->_GroupUserHash["CS"], 383620);
		
		
		
		
		
		
		
		
		
		
		

		
		
		#-- Deffine the Production Group and all user IDs
		array_push($this->_GroupUserHash["PRODUCTION"], 109779);
		array_push($this->_GroupUserHash["PRODUCTION"], 240890);
		array_push($this->_GroupUserHash["PRODUCTION"], 91256);
		
		
		
		
		#-- Deffine the Sales Group and all user IDs.  These are people that are trying to cross sell things to our existing customer base.
		//array_push($this->_GroupUserHash["SALES_FORCE"], 187121);
		//array_push($this->_GroupUserHash["SALES_FORCE"], 192860);
		array_push($this->_GroupUserHash["SALES_FORCE"], 196562);
		array_push($this->_GroupUserHash["SALES_FORCE"], 411174);
		array_push($this->_GroupUserHash["SALES_FORCE"], 297252);
		array_push($this->_GroupUserHash["SALES_FORCE"], 329109);
		array_push($this->_GroupUserHash["SALES_FORCE"], 367182);
		array_push($this->_GroupUserHash["SALES_FORCE"], 400862);
		array_push($this->_GroupUserHash["SALES_FORCE"], 400863);
		array_push($this->_GroupUserHash["SALES_FORCE"], 374130);
		array_push($this->_GroupUserHash["SALES_FORCE"], 374131);
		array_push($this->_GroupUserHash["SALES_FORCE"], 372982);
		array_push($this->_GroupUserHash["SALES_FORCE"], 366641);
		array_push($this->_GroupUserHash["SALES_FORCE"], 297519);
		array_push($this->_GroupUserHash["SALES_FORCE"], 209133);
		array_push($this->_GroupUserHash["SALES_FORCE"], 214147);
		array_push($this->_GroupUserHash["SALES_FORCE"], 358364);
		array_push($this->_GroupUserHash["SALES_FORCE"], 214568);
		array_push($this->_GroupUserHash["SALES_FORCE"], 218151);
		array_push($this->_GroupUserHash["SALES_FORCE"], 219316);
		array_push($this->_GroupUserHash["SALES_FORCE"], 221912);
		array_push($this->_GroupUserHash["SALES_FORCE"], 237969);
		array_push($this->_GroupUserHash["SALES_FORCE"], 242476);
		array_push($this->_GroupUserHash["SALES_FORCE"], 250137);
		array_push($this->_GroupUserHash["SALES_FORCE"], 438191);
		array_push($this->_GroupUserHash["SALES_FORCE"], 458268);
		
		
		
		
		
		
		
		
		
		
		

		#-- Deffine the Mailings Group and all user IDs
		array_push($this->_GroupUserHash["MAIL_ADMIN"], 2);
		array_push($this->_GroupUserHash["MAIL_ADMIN"], 97829);
		array_push($this->_GroupUserHash["MAIL_ADMIN"], 117246);
		array_push($this->_GroupUserHash["MAIL_ADMIN"], 52204);
		array_push($this->_GroupUserHash["MAIL_ADMIN"], 435466);
		array_push($this->_GroupUserHash["MAIL_ADMIN"], 110300);
		array_push($this->_GroupUserHash["MAIL_ADMIN"], 329);
		array_push($this->_GroupUserHash["MAIL_ADMIN"], 240890);
		
		array_push($this->_GroupUserHash["MAIL_PRODUCTION"], 91256);
		array_push($this->_GroupUserHash["MAIL_PRODUCTION"], 109779);
		array_push($this->_GroupUserHash["MAIL_PRODUCTION"], 240890);
		
		
		
		
		array_push($this->_GroupUserHash["MAIL_ASSISTANT"], 8269);
		array_push($this->_GroupUserHash["MAIL_ASSISTANT"], 63402);
		array_push($this->_GroupUserHash["MAIL_ASSISTANT"], 453057);
		array_push($this->_GroupUserHash["MAIL_ASSISTANT"], 403110);
		array_push($this->_GroupUserHash["MAIL_ASSISTANT"], 205054);
		array_push($this->_GroupUserHash["MAIL_ASSISTANT"], 24245);
		array_push($this->_GroupUserHash["MAIL_ASSISTANT"], 41881);
		array_push($this->_GroupUserHash["MAIL_ASSISTANT"], 54644);
		array_push($this->_GroupUserHash["MAIL_ASSISTANT"], 92046);
		//array_push($this->_GroupUserHash["MAIL_ASSISTANT"], 400308);
		
		

		



		#-- Super CS has a bit more control than a regular CS person.
		array_push($this->_GroupUserHash["SUPERCS"], 63402);
		array_push($this->_GroupUserHash["SUPERCS"], 24245);
		//array_push($this->_GroupUserHash["SUPERCS"], 406089);


		#-- Deffine the Vendor Group and all user IDs
		//array_push($this->_GroupUserHash["VENDOR"], 16039);
		//array_push($this->_GroupUserHash["VENDOR"], 91256);
		//array_push($this->_GroupUserHash["VENDOR"], 2);


		#-- Deffine the Graphic Artist Group and all user IDs
		array_push($this->_GroupUserHash["ARTIST"], 41215);
		
		//array_push($this->_GroupUserHash["ARTIST"], 105929);
		
		#-- Deffine the Marketing Group and all user IDs
		array_push($this->_GroupUserHash["MARKETING"], 87751);
		array_push($this->_GroupUserHash["MARKETING"], 363376);
		
		
		#-- Deffine the Content Editing Group and all user IDs
		array_push($this->_GroupUserHash["EDITOR"], 106346);
		array_push($this->_GroupUserHash["EDITOR"], 149494);
		array_push($this->_GroupUserHash["EDITOR"], 156809);
		array_push($this->_GroupUserHash["EDITOR"], 161120);
		//array_push($this->_GroupUserHash["EDITOR"], 177734);
		array_push($this->_GroupUserHash["EDITOR"], 178633);
		array_push($this->_GroupUserHash["EDITOR"], 195803);
		array_push($this->_GroupUserHash["EDITOR"], 90085);
		//array_push($this->_GroupUserHash["EDITOR"], 363376);
		
		
		
		#-- Allow certain people to authentication other's IP addresses
		array_push($this->_GroupUserHash["IPSECURITY"], 8269);
		array_push($this->_GroupUserHash["IPSECURITY"], 24245);
		array_push($this->_GroupUserHash["IPSECURITY"], 110300);
		
		
		
		#--- Define people who can edit Products
		array_push($this->_GroupUserHash["PRODUCT_MANAGER"], 24245);
		array_push($this->_GroupUserHash["PRODUCT_MANAGER"], 63402);
		
		
		

		#-- Deffine the Member Group ... this should be pretty much every UserID of people using the backend system
		array_push($this->_GroupUserHash["MEMBER"], 2);		//Brian Piere
		array_push($this->_GroupUserHash["MEMBER"], 91256);	//Dot Graphics main vendor
		array_push($this->_GroupUserHash["MEMBER"], 97829);	//Brian Whiteman (admin Account)
		//array_push($this->_GroupUserHash["MEMBER"], 16039);	//MailMark
		array_push($this->_GroupUserHash["MEMBER"], 6940);	//Ducirlene
		array_push($this->_GroupUserHash["MEMBER"], 8269);	//Susie Mathews
		//array_push($this->_GroupUserHash["MEMBER"], 18562);	//Heather Shinbarger
		//array_push($this->_GroupUserHash["MEMBER"], 23804);	//Michael Hodges
		array_push($this->_GroupUserHash["MEMBER"], 24245);	//Tammy
		//array_push($this->_GroupUserHash["MEMBER"], 22885);	//Karen Buckman
		//array_push($this->_GroupUserHash["MEMBER"], 376257); //Danielle Gage (customer service)
		array_push($this->_GroupUserHash["MEMBER"], 41881);	//Rachel
		array_push($this->_GroupUserHash["MEMBER"], 41215);	//Ginger McConnell
		array_push($this->_GroupUserHash["MEMBER"], 44948);	//Jeffery Biston
		//array_push($this->_GroupUserHash["MEMBER"], 363168);	//Rebecca Brewington
		array_push($this->_GroupUserHash["MEMBER"], 45223);	//Chris Price
		array_push($this->_GroupUserHash["MEMBER"], 54644);	//Jami Schafer
		array_push($this->_GroupUserHash["MEMBER"], 63402);	//Heather Hendrix
		//array_push($this->_GroupUserHash["MEMBER"], 90085);	//Craig Newby
		//array_push($this->_GroupUserHash["MEMBER"], 90348);	//Gina Adlers
		array_push($this->_GroupUserHash["MEMBER"], 87751);	//Justin Marshall - Gazelle Interactive
		//array_push($this->_GroupUserHash["MEMBER"], 102774);	//Michael Wissot - Public Relations
		//array_push($this->_GroupUserHash["MEMBER"], 105929);	//Bruce Graphic Artist at DOT
		array_push($this->_GroupUserHash["MEMBER"], 89676);	//Christy Boyce Goodson
		array_push($this->_GroupUserHash["MEMBER"], 109779);	//Andy (dot graphics)
		array_push($this->_GroupUserHash["MEMBER"], 240890);	//Chuck (Andy's Brother) (dot graphics)
		array_push($this->_GroupUserHash["MEMBER"], 92046);	//Amanda Reagan
		array_push($this->_GroupUserHash["MEMBER"], 110300);	//Laurel Altman
		array_push($this->_GroupUserHash["MEMBER"], 106346);	//Michael David Brennan
		//array_push($this->_GroupUserHash["MEMBER"], 108266);	//Angel J. Stokes
		//array_push($this->_GroupUserHash["MEMBER"], 134651);	//Donovan Leonard Saddler
		array_push($this->_GroupUserHash["MEMBER"], 76638);	//Brenda Moreno
		array_push($this->_GroupUserHash["MEMBER"], 117246);	//Bill giamella
		array_push($this->_GroupUserHash["MEMBER"], 329);	//Mike Hughes
		//array_push($this->_GroupUserHash["MEMBER"], 149494);	//Priscilla Bennett
		array_push($this->_GroupUserHash["MEMBER"], 156809);	//Alexander James Pearsall
		//array_push($this->_GroupUserHash["MEMBER"], 177734);	//Jeanie Erwin
		array_push($this->_GroupUserHash["MEMBER"], 161120);	//Jose E. Perez
		//array_push($this->_GroupUserHash["MEMBER"], 161772);	//Northridge Kiosk
		//array_push($this->_GroupUserHash["MEMBER"], 166294);	//Denise Henley
		array_push($this->_GroupUserHash["MEMBER"], 178633);	//Jennifer Bockoff  
		//array_push($this->_GroupUserHash["MEMBER"], 187121);	//Lance e Lorback (merchant sales) 
		array_push($this->_GroupUserHash["MEMBER"], 52204);	//Christian Nuesch
		array_push($this->_GroupUserHash["MEMBER"], 435466);	//Neel Master
		array_push($this->_GroupUserHash["MEMBER"], 195803);	//Jacob Ratliff
		array_push($this->_GroupUserHash["MEMBER"], 363376);	//Jack Lewis
		//array_push($this->_GroupUserHash["MEMBER"], 192860);	//Kristina M Galaviz (merchant sales) 
		array_push($this->_GroupUserHash["MEMBER"], 196562);	//Robert Alan Scheff (merchant sales)
		array_push($this->_GroupUserHash["MEMBER"], 411174);	//Kirk Yanish (merchant sales)
		//array_push($this->_GroupUserHash["MEMBER"], 204742);	//Kamini
		array_push($this->_GroupUserHash["MEMBER"], 388797);	//Stephen Jacob Biston
		//array_push($this->_GroupUserHash["MEMBER"], 325380);	//David McDermott
		array_push($this->_GroupUserHash["MEMBER"], 203413);	//Kristy Leppert
		array_push($this->_GroupUserHash["MEMBER"], 205054);	//Micheala Piatt
		array_push($this->_GroupUserHash["MEMBER"], 297241);	//Robbie Wigginton
		array_push($this->_GroupUserHash["MEMBER"], 209133);	//matthew deneau (merchant sales)
		array_push($this->_GroupUserHash["MEMBER"], 214147);	//Gregory Ryan Jones (merchant sales)
		//array_push($this->_GroupUserHash["MEMBER"], 214568);	//Sandy Kellogg (merchant sales)
		//array_push($this->_GroupUserHash["MEMBER"], 218151);	//Allen A. Cummins (merchant sales)
		array_push($this->_GroupUserHash["MEMBER"], 219316);	//Fred Cox (merchant sales)
		//array_push($this->_GroupUserHash["MEMBER"], 221912);	//Warren Ricketts (merchant sales)
		//array_push($this->_GroupUserHash["MEMBER"], 237969);	//Pat Loney (merchant sales)
		//array_push($this->_GroupUserHash["MEMBER"], 242476);	//Daniel Qare
		//array_push($this->_GroupUserHash["MEMBER"], 250137);	//Jason Dennis (Merchant Sales)
		array_push($this->_GroupUserHash["MEMBER"], 438191);	//Sandy Ackerman (Merchant Sales)
		array_push($this->_GroupUserHash["MEMBER"], 252972);	//Amy Marie Biggerstaff (helping with templates)
		//array_push($this->_GroupUserHash["MEMBER"], 288434);	//Pablo (business card graphic designer)
		array_push($this->_GroupUserHash["MEMBER"], 297252);	//Randy (merchant sales)
		array_push($this->_GroupUserHash["MEMBER"], 329109);	//Carl Davenport (merchant sales)
		array_push($this->_GroupUserHash["MEMBER"], 367182);	//Denis Whiteman (merchant sales)
		array_push($this->_GroupUserHash["MEMBER"], 400862);	//Julie Cathers (merchant sales)
		array_push($this->_GroupUserHash["MEMBER"], 400863);	//Krystle Sciarra (merchant sales)
		array_push($this->_GroupUserHash["MEMBER"], 366641);	//Elvia Pena (merchant sales)
		//array_push($this->_GroupUserHash["MEMBER"], 335934);	//Laurie Champagne (marketing director)
		array_push($this->_GroupUserHash["MEMBER"], 353856);	//Kelvin Angulo (web producer)
		array_push($this->_GroupUserHash["MEMBER"], 358364);	//Lynelle Furbush (Offline Print Sales)
		array_push($this->_GroupUserHash["MEMBER"], 64559);	//Sanjay Tandon
		//array_push($this->_GroupUserHash["MEMBER"], 297519);	//Ray (merchant sales)
		array_push($this->_GroupUserHash["MEMBER"], 372982);	//Zachariah Harrington (merchant sales)
		array_push($this->_GroupUserHash["MEMBER"], 374131);	//Shannonb K. miller (merchant sales)
		array_push($this->_GroupUserHash["MEMBER"], 374130);	//nick perry (merchant sales)
		array_push($this->_GroupUserHash["MEMBER"], 383620);	//Lyndsi Molz (templates... for Tammy's daughter)
		array_push($this->_GroupUserHash["MEMBER"], 400314);	//Nathan Tuck (customer service)
		//array_push($this->_GroupUserHash["MEMBER"], 400308);	//Marianne Gretchen Bull (customer service)
		array_push($this->_GroupUserHash["MEMBER"], 400303);	//Ryan Pinkston (customer service)
		array_push($this->_GroupUserHash["MEMBER"], 400206);	//Kathleen Davenport (customer service)
		array_push($this->_GroupUserHash["MEMBER"], 418862);	//Jennifer Shotwell (customer service)
		array_push($this->_GroupUserHash["MEMBER"], 401315);	//Amber Carnahan (template linking)
		array_push($this->_GroupUserHash["MEMBER"], 401149);	//Alisha Joi Eidson (template linking)
		array_push($this->_GroupUserHash["MEMBER"], 403110);	//Megan Kirsop (csr)
		array_push($this->_GroupUserHash["MEMBER"], 388813);	//Drew Gipson(csr)
		//array_push($this->_GroupUserHash["MEMBER"], 406089);	//Hope Szlemko (csr)
		array_push($this->_GroupUserHash["MEMBER"], 371989);	//Crystal Sessions (csr)
		array_push($this->_GroupUserHash["MEMBER"], 453057);	//Aaron Shaw (csr)
		array_push($this->_GroupUserHash["MEMBER"], 458268);	//Savannah Wells(csr)
		array_push($this->_GroupUserHash["MEMBER"], 430626);	//Peter Varady (HouseOfBlues)
		array_push($this->_GroupUserHash["MEMBER"], 407015);	//Brian Piere Admin Test account for Amazing Grass
		
		
		

	

		
		
		






		//----------------------------------------------

		//---------- Customize -------------------------

		$this->_GroupPermissionHash["SUPERADMIN"] = array();
		$this->_GroupPermissionHash["ADMIN"] = array();
		$this->_GroupPermissionHash["ACCOUNTANT"] = array();
		$this->_GroupPermissionHash["SUPERCS"] = array();
		$this->_GroupPermissionHash["CS"] = array();
		$this->_GroupPermissionHash["VENDOR"] = array();
		$this->_GroupPermissionHash["ARTIST"] = array();
		$this->_GroupPermissionHash["SALESMASTER"] = array();
		$this->_GroupPermissionHash["MARKETING"] = array();
		$this->_GroupPermissionHash["PRODUCTION"] = array();
		$this->_GroupPermissionHash["MAIL_ADMIN"] = array();
		$this->_GroupPermissionHash["MAIL_ASSISTANT"] = array();
		$this->_GroupPermissionHash["MAIL_PRODUCTION"] = array();
		$this->_GroupPermissionHash["SALES_FORCE"] = array();
		$this->_GroupPermissionHash["EDITOR"] = array();
		$this->_GroupPermissionHash["IPSECURITY"] = array();
		$this->_GroupPermissionHash["PRODUCT_MANAGER"] = array();
		$this->_GroupPermissionHash["EMAIL_MESSAGE_CONTENT"] = array();
		$this->_GroupPermissionHash["FRANCHISE_OWNER"] = array();
		
		



		#-- Deffine which permissions belong to the various Groups --#


		array_push($this->_GroupPermissionHash["ADMIN"], "COMPANY_ORDER_HISTORY");
		array_push($this->_GroupPermissionHash["ADMIN"], "MANAGE_TEMPLATES");
		array_push($this->_GroupPermissionHash["ADMIN"], "NEWS_LETTERS");
		array_push($this->_GroupPermissionHash["ADMIN"], "VIEW_ORDER_INCOME");
		array_push($this->_GroupPermissionHash["ADMIN"], "VIEW_VENDOR_TOTAL");
		array_push($this->_GroupPermissionHash["ADMIN"], "MENU_ADMIN");
		array_push($this->_GroupPermissionHash["ADMIN"], "CREDIT_CANCELATIONS");
		array_push($this->_GroupPermissionHash["ADMIN"], "EMAIL_FILTER");
		array_push($this->_GroupPermissionHash["ADMIN"], "PRODUCTION_API");
		array_push($this->_GroupPermissionHash["ADMIN"], "COUPONS_EDIT");
		array_push($this->_GroupPermissionHash["ADMIN"], "COUPONS_SALESLINK");
		array_push($this->_GroupPermissionHash["ADMIN"], "CREDIT_CARD_ERRORS");
		array_push($this->_GroupPermissionHash["ADMIN"], "CORPORATE_BILLING");
		array_push($this->_GroupPermissionHash["ADMIN"], "PDF_BATCH_GENERATION");
		array_push($this->_GroupPermissionHash["ADMIN"], "SALES_CONTROL");
		array_push($this->_GroupPermissionHash["ADMIN"], "MANUAL_EDIT_ARTWORK");
		array_push($this->_GroupPermissionHash["ADMIN"], "ADD_SCHEDULER_DELAYS");
		array_push($this->_GroupPermissionHash["ADMIN"], "VIEW_WORKERS_SCHEDULES");
		array_push($this->_GroupPermissionHash["ADMIN"], "CHANGE_OTHER_WORKERS_STATUS");
		array_push($this->_GroupPermissionHash["ADMIN"], "IMPORT_SHIPMENTS");
		array_push($this->_GroupPermissionHash["ADMIN"], "VIEW_SALESREP_PERCENTAGES");
		array_push($this->_GroupPermissionHash["ADMIN"], "CUSTOMER_BILLING_INFO");
		array_push($this->_GroupPermissionHash["ADMIN"], "VIEW_PASSWORDS");
		array_push($this->_GroupPermissionHash["ADMIN"], "DISCOUNT_DETAIL_OPENORDERS");
		array_push($this->_GroupPermissionHash["ADMIN"], "EDIT_CONTENT");
		array_push($this->_GroupPermissionHash["ADMIN"], "PAGE_VISITS_USER_OVERRIDE");
		array_push($this->_GroupPermissionHash["ADMIN"], "ORDER_LIST_ARRIVAL_DATES");
		array_push($this->_GroupPermissionHash["ADMIN"], "TASKS_MEMOS_OPENORDERS");
		array_push($this->_GroupPermissionHash["ADMIN"], "CUSTOMER_RATING_OPENORDERS");
		array_push($this->_GroupPermissionHash["ADMIN"], "TEMPLATE_CREATE_CATEGORIES");
		array_push($this->_GroupPermissionHash["ADMIN"], "MARKETING_USER_LIST");
		array_push($this->_GroupPermissionHash["ADMIN"], "DELETE_SUPER_PDF_PROFILE");
		array_push($this->_GroupPermissionHash["ADMIN"], "EDIT_SUPER_PDF_PROFILE");
		array_push($this->_GroupPermissionHash["ADMIN"], "EDIT_PRODUCT");
		array_push($this->_GroupPermissionHash["ADMIN"], "EDIT_PDF_PROFILES");
		array_push($this->_GroupPermissionHash["ADMIN"], "RACK_CONTROL");
		array_push($this->_GroupPermissionHash["ADMIN"], "VIEW_DOMAIN_TOTALS");
		array_push($this->_GroupPermissionHash["ADMIN"], "GANG_RUNS");
		array_push($this->_GroupPermissionHash["ADMIN"], "CHANGE_PRODUCT_OPTION_NAMES");
		array_push($this->_GroupPermissionHash["ADMIN"], "DEAD_ACCOUNTS");
		array_push($this->_GroupPermissionHash["ADMIN"], "MARKETING_REPORT");
		array_push($this->_GroupPermissionHash["ADMIN"], "REPORT_MONTH_BACKUP");
		array_push($this->_GroupPermissionHash["ADMIN"], "REPORT_MONTH");
		array_push($this->_GroupPermissionHash["ADMIN"], "CHAT_SEARCH");
		array_push($this->_GroupPermissionHash["ADMIN"], "SEARCH_REPLACE_ARTWORK_TEMP");
		array_push($this->_GroupPermissionHash["ADMIN"], "ORDER_LIST_CSV");
		array_push($this->_GroupPermissionHash["ADMIN"], "ARTWORK_SEARCH");
		
		
		
		#-- Set Permissions for a Product Manager
		array_push($this->_GroupPermissionHash["PRODUCT_MANAGER"], "DELETE_SUPER_PDF_PROFILE");
		array_push($this->_GroupPermissionHash["PRODUCT_MANAGER"], "EDIT_SUPER_PDF_PROFILE");
		array_push($this->_GroupPermissionHash["PRODUCT_MANAGER"], "EDIT_PRODUCT");
		array_push($this->_GroupPermissionHash["PRODUCT_MANAGER"], "EDIT_PDF_PROFILES");
		array_push($this->_GroupPermissionHash["PRODUCT_MANAGER"], "RACK_CONTROL");
		array_push($this->_GroupPermissionHash["PRODUCT_MANAGER"], "GANG_RUNS");
		array_push($this->_GroupPermissionHash["PRODUCT_MANAGER"], "CHANGE_PRODUCT_OPTION_NAMES");
		array_push($this->_GroupPermissionHash["PRODUCT_MANAGER"], "TEMPLATE_CREATE_CATEGORIES");
		array_push($this->_GroupPermissionHash["PRODUCT_MANAGER"], "EDIT_SHIPPING_CHOICES");
		array_push($this->_GroupPermissionHash["PRODUCT_MANAGER"], "PDF_BATCH_GENERATION");
		array_push($this->_GroupPermissionHash["PRODUCT_MANAGER"], "MANUAL_EDIT_ARTWORK");
		array_push($this->_GroupPermissionHash["PRODUCT_MANAGER"], "SEARCH_REPLACE_ARTWORK_TEMP");
		
		
		
		#---

		array_push($this->_GroupPermissionHash["IPSECURITY"], "IP_ADDRESS_SECURITY");
		

		#---
		

		array_push($this->_GroupPermissionHash["FRANCHISE_OWNER"], "ARTWORK_PREVIEW_POPUPS_USER_ORDERS");
		array_push($this->_GroupPermissionHash["FRANCHISE_OWNER"], "ARTWORK_PREVIEW_POPUPS");
		array_push($this->_GroupPermissionHash["FRANCHISE_OWNER"], "ORDER_SUMMARY_POPUPS");
		array_push($this->_GroupPermissionHash["FRANCHISE_OWNER"], "EXTENDED_HOMEPAGE_HISTORY");
		array_push($this->_GroupPermissionHash["FRANCHISE_OWNER"], "REPORT_MONTH");
		array_push($this->_GroupPermissionHash["FRANCHISE_OWNER"], "REPORT_MONTH_BACKUP");
		array_push($this->_GroupPermissionHash["FRANCHISE_OWNER"], "VIEW_ORDER_INCOME");
		array_push($this->_GroupPermissionHash["FRANCHISE_OWNER"], "VIEW_VENDOR_TOTAL");
		array_push($this->_GroupPermissionHash["FRANCHISE_OWNER"], "CUSTOMER_RATING_OPENORDERS");
		array_push($this->_GroupPermissionHash["FRANCHISE_OWNER"], "COUPONS_EDIT");
		array_push($this->_GroupPermissionHash["FRANCHISE_OWNER"], "CORPORATE_BILLING");
		

		#---


		array_push($this->_GroupPermissionHash["ACCOUNTANT"], "COMPANY_ORDER_HISTORY");
		array_push($this->_GroupPermissionHash["ACCOUNTANT"], "MARKETING_REPORT");
		array_push($this->_GroupPermissionHash["ACCOUNTANT"], "VIEW_ORDER_INCOME");
		array_push($this->_GroupPermissionHash["ACCOUNTANT"], "VIEW_VENDOR_TOTAL");
		array_push($this->_GroupPermissionHash["ACCOUNTANT"], "MENU_ACCOUNTANT");
		array_push($this->_GroupPermissionHash["ACCOUNTANT"], "CREDIT_CANCELATIONS");
		array_push($this->_GroupPermissionHash["ACCOUNTANT"], "OPEN_ORDERS");
		array_push($this->_GroupPermissionHash["ACCOUNTANT"], "ORDER_SCREEN");
		array_push($this->_GroupPermissionHash["ACCOUNTANT"], "PROJECT_SCREEN");
		array_push($this->_GroupPermissionHash["ACCOUNTANT"], "USER_SEARCH");
		array_push($this->_GroupPermissionHash["ACCOUNTANT"], "USER_ORDERS");
		array_push($this->_GroupPermissionHash["ACCOUNTANT"], "REPORT_MONTH");
		array_push($this->_GroupPermissionHash["ACCOUNTANT"], "HOME_SCREEN");


		#---
		


		array_push($this->_GroupPermissionHash["MARKETING"], "MARKETING_REPORT");
		array_push($this->_GroupPermissionHash["MARKETING"], "VIEW_ORDER_INCOME");
		array_push($this->_GroupPermissionHash["MARKETING"], "VIEW_VENDOR_TOTAL");
		array_push($this->_GroupPermissionHash["MARKETING"], "REPORT_MONTH");
		array_push($this->_GroupPermissionHash["MARKETING"], "MENU_ACCOUNTANT");
		array_push($this->_GroupPermissionHash["MARKETING"], "HOME_SCREEN");
		array_push($this->_GroupPermissionHash["MARKETING"], "MARKETING_USER_LIST");
		array_push($this->_GroupPermissionHash["MARKETING"], "VISITOR_PATHS_REPORT");
		array_push($this->_GroupPermissionHash["MARKETING"], "EXTENDED_HOMEPAGE_HISTORY");
		array_push($this->_GroupPermissionHash["MARKETING"], "ARTWORK_PREVIEW_POPUPS_USER_ORDERS");
		array_push($this->_GroupPermissionHash["MARKETING"], "ARTWORK_PREVIEW_POPUPS");
		array_push($this->_GroupPermissionHash["MARKETING"], "ORDER_SUMMARY_POPUPS");
		array_push($this->_GroupPermissionHash["MARKETING"], "VIEW_DOMAIN_TOTALS");
		
		


		#---


		array_push($this->_GroupPermissionHash["SUPERADMIN"], "PURCHASE_ORDER_VENDOR");
		array_push($this->_GroupPermissionHash["SUPERADMIN"], "EXTENDED_HOMEPAGE_HISTORY");
		array_push($this->_GroupPermissionHash["SUPERADMIN"], "EXTRA_MARKETING_DETAILS_ORDER");
		array_push($this->_GroupPermissionHash["SUPERADMIN"], "ARTWORK_PREVIEW_POPUPS");
		array_push($this->_GroupPermissionHash["SUPERADMIN"], "ORDER_SUMMARY_POPUPS");
		array_push($this->_GroupPermissionHash["SUPERADMIN"], "MEMOS_SALES_FORCE_REPORTS");
		array_push($this->_GroupPermissionHash["SUPERADMIN"], "MEMOS_SALES_FORCE_ADD");
		array_push($this->_GroupPermissionHash["SUPERADMIN"], "EDIT_SHIPPING_CHOICES");
		array_push($this->_GroupPermissionHash["SUPERADMIN"], "MENU_SUPERADMIN");
		array_push($this->_GroupPermissionHash["SUPERADMIN"], "MARKETING_REPORT");
		array_push($this->_GroupPermissionHash["SUPERADMIN"], "REPORT_MONTH");
		array_push($this->_GroupPermissionHash["SUPERADMIN"], "IP_ADDRESS_SECURITY");
		array_push($this->_GroupPermissionHash["SUPERADMIN"], "VISITOR_PATHS_REPORT");
		array_push($this->_GroupPermissionHash["SUPERADMIN"], "LOYALTY_REVENUES");
		array_push($this->_GroupPermissionHash["SUPERADMIN"], "COPYRIGHT_REVENUES");

		
		
		#-
		
		
		array_push($this->_GroupPermissionHash["EMAIL_MESSAGE_CONTENT"], "EMAIL_NOTIFY_MESSAGE_EDIT");
		array_push($this->_GroupPermissionHash["EMAIL_MESSAGE_CONTENT"], "EMAIL_NOTIFY_MESSAGE_VIEW");
		array_push($this->_GroupPermissionHash["EMAIL_MESSAGE_CONTENT"], "EMAIL_NOTIFY_EMAIL_ADDRESS_BATCHES");


		



		#---




		array_push($this->_GroupPermissionHash["SALES_FORCE"], "ARTWORK_PREVIEW_POPUPS_USER_ORDERS");
		array_push($this->_GroupPermissionHash["SALES_FORCE"], "ARTWORK_PREVIEW_POPUPS");
		array_push($this->_GroupPermissionHash["SALES_FORCE"], "COUPONS_EDIT");
		array_push($this->_GroupPermissionHash["SALES_FORCE"], "COUPONS_SALESLINK");
		array_push($this->_GroupPermissionHash["SALES_FORCE"], "CORPORATE_BILLING");
		array_push($this->_GroupPermissionHash["SALES_FORCE"], "DISCOUNT_DETAIL_OPENORDERS");
		array_push($this->_GroupPermissionHash["SALES_FORCE"], "TASKS_MEMOS_OPENORDERS");
		array_push($this->_GroupPermissionHash["SALES_FORCE"], "ORDER_LIST_ARRIVAL_DATES");
		array_push($this->_GroupPermissionHash["SALES_FORCE"], "MEMOS_SALES_FORCE_REPORTS");
		array_push($this->_GroupPermissionHash["SALES_FORCE"], "MEMOS_SALES_FORCE_ADD");
		array_push($this->_GroupPermissionHash["SALES_FORCE"], "MARKETING_USER_LIST");
		array_push($this->_GroupPermissionHash["SALES_FORCE"], "CUSTOMER_RATING_OPENORDERS");
		array_push($this->_GroupPermissionHash["SALES_FORCE"], "ORDER_SUMMARY_POPUPS");
		
		
		
	


		#---



		array_push($this->_GroupPermissionHash["PRODUCTION"], "EDIT_SUPER_PDF_PROFILE");
		array_push($this->_GroupPermissionHash["PRODUCTION"], "EDIT_PDF_PROFILES");
		array_push($this->_GroupPermissionHash["PRODUCTION"], "OPEN_ORDERS");
		array_push($this->_GroupPermissionHash["PRODUCTION"], "ORDER_SCREEN");
		array_push($this->_GroupPermissionHash["PRODUCTION"], "PROJECT_SCREEN");
		array_push($this->_GroupPermissionHash["PRODUCTION"], "USER_SEARCH");
		array_push($this->_GroupPermissionHash["PRODUCTION"], "USER_ORDERS");
		array_push($this->_GroupPermissionHash["PRODUCTION"], "COMPLETE_ORDER");
		array_push($this->_GroupPermissionHash["PRODUCTION"], "VIEW_VENDOR_TOTAL");
		array_push($this->_GroupPermissionHash["PRODUCTION"], "MENU_VENDOR");
		array_push($this->_GroupPermissionHash["PRODUCTION"], "CHANGE_PACKAGE_WEIGHT");
		array_push($this->_GroupPermissionHash["PRODUCTION"], "PRODUCTION_API");
		array_push($this->_GroupPermissionHash["PRODUCTION"], "HOME_SCREEN");
		array_push($this->_GroupPermissionHash["PRODUCTION"], "SCHEDULE_VIEW");
		array_push($this->_GroupPermissionHash["PRODUCTION"], "PDF_BATCH_GENERATION");
		array_push($this->_GroupPermissionHash["PRODUCTION"], "PURCHASE_ORDER_VENDOR");
		array_push($this->_GroupPermissionHash["PRODUCTION"], "IMPORT_SHIPMENTS");
		array_push($this->_GroupPermissionHash["PRODUCTION"], "CHANGE_PACKAGE_SHIPPING_METHOD");
		array_push($this->_GroupPermissionHash["PRODUCTION"], "ADD_SCHEDULER_DELAYS");
		array_push($this->_GroupPermissionHash["PRODUCTION"], "ARTWORK_PREVIEW_POPUPS");
		array_push($this->_GroupPermissionHash["PRODUCTION"], "ARTWORK_PREVIEW_POPUPS_USER_ORDERS");
		array_push($this->_GroupPermissionHash["PRODUCTION"], "RACK_CONTROL");
		array_push($this->_GroupPermissionHash["PRODUCTION"], "GANG_RUNS");
		


		#---




		array_push($this->_GroupPermissionHash["MAIL_ADMIN"], "MAILING_BATCHES_VIEW");
		array_push($this->_GroupPermissionHash["MAIL_ADMIN"], "MAILING_ARTWORK_PRODUCTION");
		array_push($this->_GroupPermissionHash["MAIL_ADMIN"], "MAILING_BATCHES_CANCEL");
		array_push($this->_GroupPermissionHash["MAIL_ADMIN"], "MAILING_BATCHES_CREATE");
		array_push($this->_GroupPermissionHash["MAIL_ADMIN"], "MAILING_BATCHES_PROOF");
		array_push($this->_GroupPermissionHash["MAIL_ADMIN"], "MAILING_LIST_IMPORT");
		array_push($this->_GroupPermissionHash["MAIL_ADMIN"], "MAILING_BATCHES_COMPLETE");
		
		
		
		#---
		
		
		array_push($this->_GroupPermissionHash["MAIL_PRODUCTION"], "MAILING_ARTWORK_PRODUCTION");
		array_push($this->_GroupPermissionHash["MAIL_PRODUCTION"], "MAILING_BATCHES_VIEW");
		


		#---
		
		
		array_push($this->_GroupPermissionHash["MAIL_ASSISTANT"], "MAILING_ARTWORK_PRODUCTION");
		array_push($this->_GroupPermissionHash["MAIL_ASSISTANT"], "MAILING_BATCHES_VIEW");
		array_push($this->_GroupPermissionHash["MAIL_ASSISTANT"], "MAILING_BATCHES_PROOF");
		
		


		#---

		array_push($this->_GroupPermissionHash["SALESMASTER"], "SALES_MASTER");


		#---


		array_push($this->_GroupPermissionHash["CS"], "EDIT_ARTWORK");
		array_push($this->_GroupPermissionHash["CS"], "COMPLETE_ORDER");
		array_push($this->_GroupPermissionHash["CS"], "OPEN_ORDERS");
		array_push($this->_GroupPermissionHash["CS"], "ORDER_SCREEN");
		array_push($this->_GroupPermissionHash["CS"], "PROJECT_SCREEN");
		array_push($this->_GroupPermissionHash["CS"], "USER_SEARCH");
		array_push($this->_GroupPermissionHash["CS"], "USER_ORDERS");
		array_push($this->_GroupPermissionHash["CS"], "CHANGE_CUSTOMER_SHIPPING_METHOD");
		array_push($this->_GroupPermissionHash["CS"], "CHANGE_PACKAGE_WEIGHT");
		array_push($this->_GroupPermissionHash["CS"], "CHANGE_CUSTOMER_SHIPPING_ADDRESS");
		array_push($this->_GroupPermissionHash["CS"], "VIEW_CUSTOMER_SERVICE");
		array_push($this->_GroupPermissionHash["CS"], "VIEW_LOGIN_LINK");
		array_push($this->_GroupPermissionHash["CS"], "CHANGE_CUSTOMER_SHIPPING_PRICE");
		array_push($this->_GroupPermissionHash["CS"], "CUSTOMER_INVOICE_MESSAGE");
		array_push($this->_GroupPermissionHash["CS"], "MODIFY_SHIPPING_INSTRUCTIONS");
		array_push($this->_GroupPermissionHash["CS"], "ADD_BALANCE_ADJUSTMENTS");
		array_push($this->_GroupPermissionHash["CS"], "VIEW_ADMIN_MESSAGES");
		array_push($this->_GroupPermissionHash["CS"], "CREATE_REPRINT");
		array_push($this->_GroupPermissionHash["CS"], "UPDATE_REPRINT_NOTE");
		array_push($this->_GroupPermissionHash["CS"], "CANCEL_PROJECT");
		array_push($this->_GroupPermissionHash["CS"], "EDIT_PRODUCT_OPTIONS");
		array_push($this->_GroupPermissionHash["CS"], "PROOF_ARTWORK");
		array_push($this->_GroupPermissionHash["CS"], "PROJECT_TOTALS");
		array_push($this->_GroupPermissionHash["CS"], "VIEW_CUSTOMER_ORDER_TOTALS");
		array_push($this->_GroupPermissionHash["CS"], "MENU_CS");
		array_push($this->_GroupPermissionHash["CS"], "COUPONS_VIEW");
		array_push($this->_GroupPermissionHash["CS"], "HOME_SCREEN");
		array_push($this->_GroupPermissionHash["CS"], "SAVED_PROJECT_OVERRIDE");
		array_push($this->_GroupPermissionHash["CS"], "SCHEDULE_VIEW");
		array_push($this->_GroupPermissionHash["CS"], "MERCHANT_LINK");
		array_push($this->_GroupPermissionHash["CS"], "CUSTOMER_DISCOUNT");
		array_push($this->_GroupPermissionHash["CS"], "CUSTOMER_ACCOUNT");
		array_push($this->_GroupPermissionHash["CS"], "MANAGE_TEMPLATES");
		array_push($this->_GroupPermissionHash["CS"], "PACKAGE_EXCEPTIONS");
		array_push($this->_GroupPermissionHash["CS"], "ADMIN_OPTION_CHANGING");
		array_push($this->_GroupPermissionHash["CS"], "SALES_PROXY_USER");
		array_push($this->_GroupPermissionHash["CS"], "EDIT_CONTENT");
		array_push($this->_GroupPermissionHash["CS"], "ARTWORK_PREVIEW_POPUPS_USER_ORDERS");
		array_push($this->_GroupPermissionHash["CS"], "DEAD_ACCOUNTS");
		array_push($this->_GroupPermissionHash["CS"], "MEMOS_SALES_FORCE_ADD");
		array_push($this->_GroupPermissionHash["CS"], "TESTIMONIALS");
		array_push($this->_GroupPermissionHash["CS"], "CHAT_SYSTEM");
		array_push($this->_GroupPermissionHash["CS"], "CHAT_SEARCH");


		#---


		array_push($this->_GroupPermissionHash["SUPERCS"], "COUPONS_EDIT");
		array_push($this->_GroupPermissionHash["SUPERCS"], "COUPONS_SALESLINK");
		array_push($this->_GroupPermissionHash["SUPERCS"], "CORPORATE_BILLING");
		array_push($this->_GroupPermissionHash["SUPERCS"], "DISCOUNT_DETAIL_OPENORDERS");
		array_push($this->_GroupPermissionHash["SUPERCS"], "TASKS_MEMOS_OPENORDERS");
		array_push($this->_GroupPermissionHash["SUPERCS"], "MANUAL_EDIT_ARTWORK");
		array_push($this->_GroupPermissionHash["SUPERCS"], "SEARCH_REPLACE_ARTWORK_TEMP");
		array_push($this->_GroupPermissionHash["SUPERCS"], "PAGE_VISITS_USER_OVERRIDE");
		array_push($this->_GroupPermissionHash["SUPERCS"], "CUSTOMER_RATING_OPENORDERS");
		array_push($this->_GroupPermissionHash["SUPERCS"], "ORDER_SUMMARY_POPUPS");
		array_push($this->_GroupPermissionHash["SUPERCS"], "ARTWORK_PREVIEW_POPUPS_USER_ORDERS");
		array_push($this->_GroupPermissionHash["SUPERCS"], "ARTWORK_PREVIEW_POPUPS");
		array_push($this->_GroupPermissionHash["SUPERCS"], "ORDER_LIST_CSV");
		array_push($this->_GroupPermissionHash["SUPERCS"], "ARTWORK_SEARCH");
		array_push($this->_GroupPermissionHash["SUPERCS"], "REPORT_MONTH_BACKUP");
		array_push($this->_GroupPermissionHash["SUPERCS"], "REPORT_MONTH");
		array_push($this->_GroupPermissionHash["SUPERCS"], "VIEW_WORKERS_SCHEDULES");



		#---

		array_push($this->_GroupPermissionHash["VENDOR"], "OPEN_ORDERS");
		array_push($this->_GroupPermissionHash["VENDOR"], "ORDER_SCREEN");
		array_push($this->_GroupPermissionHash["VENDOR"], "PROJECT_SCREEN");
		array_push($this->_GroupPermissionHash["VENDOR"], "USER_SEARCH");
		array_push($this->_GroupPermissionHash["VENDOR"], "USER_ORDERS");
		//array_push($this->_GroupPermissionHash["VENDOR"], "REPORT_MONTH");
		array_push($this->_GroupPermissionHash["VENDOR"], "COMPLETE_ORDER");
		array_push($this->_GroupPermissionHash["VENDOR"], "VIEW_VENDOR_TOTAL");
		array_push($this->_GroupPermissionHash["VENDOR"], "MENU_VENDOR");
		array_push($this->_GroupPermissionHash["VENDOR"], "CHANGE_PACKAGE_WEIGHT");
		array_push($this->_GroupPermissionHash["VENDOR"], "PRODUCTION_API");
		array_push($this->_GroupPermissionHash["VENDOR"], "HOME_SCREEN");
		array_push($this->_GroupPermissionHash["VENDOR"], "SCHEDULE_VIEW");
		array_push($this->_GroupPermissionHash["VENDOR"], "PDF_BATCH_GENERATION");
		array_push($this->_GroupPermissionHash["VENDOR"], "PURCHASE_ORDER_VENDOR");
		array_push($this->_GroupPermissionHash["VENDOR"], "IMPORT_SHIPMENTS");
		array_push($this->_GroupPermissionHash["VENDOR"], "CHANGE_PACKAGE_SHIPPING_METHOD");
		array_push($this->_GroupPermissionHash["VENDOR"], "ADD_SCHEDULER_DELAYS");
		array_push($this->_GroupPermissionHash["VENDOR"], "ARTWORK_PREVIEW_POPUPS");
		array_push($this->_GroupPermissionHash["VENDOR"], "ARTWORK_PREVIEW_POPUPS_USER_ORDERS");
		array_push($this->_GroupPermissionHash["VENDOR"], "RACK_CONTROL");
		array_push($this->_GroupPermissionHash["VENDOR"], "GANG_RUNS");
		


		#---

		array_push($this->_GroupPermissionHash["ARTIST"], "MANAGE_TEMPLATES");
		array_push($this->_GroupPermissionHash["ARTIST"], "MENU_GUEST");


		#---



		#---

		array_push($this->_GroupPermissionHash["EDITOR"], "EDIT_CONTENT");
		array_push($this->_GroupPermissionHash["EDITOR"], "MENU_GUEST");
		array_push($this->_GroupPermissionHash["EDITOR"], "VIEW_WORKERS_SCHEDULES");
		

		#---




	}



	#########   Public Functions - below #################

	// Will return the User ID that is Logged in.... or the UserID that we are overriding
	// If we are not overriding a User ID... and we are not logged in... calling this method results in a fatal error
	function GetUserID(){

		if(self::$_UserIDoverride == 0){

			$userID = WebUtil::GetSessionVar('UserIDloggedIN', "");
			
			if (!preg_match("/^\d{1,11}$/", $userID))
				throw new Exception("User must be logged in before calling the method GetUserID");

			return $userID;
		}
		else{
			return self::$_UserIDoverride;
		}
	}
	
	
	// Return an array containing all of the Domains that a user has permission to see in the backend.
	// For Customers (or admins within only access to 1 domain), this will return an array with 1 element.
	// If the User Is Not logged in... then it must default to whatever domain is in their browser.
	// If they are logged in though.... It will not allow the Browser URL to be added to the user's domain permission.  
	// .... That would be a security risk because someone how is logged in could just change the browser URL and stay logged in with a UserID belongin to another Domain.
	function getUserDomainsKeys($overrideUserId = null){
		
		if(empty($overrideUserId) && (!$this->CheckIfLoggedIn() || !$this->CheckIfBelongsToGroup("MEMBER"))){
			$domainNameKeyFromURL = Domain::getDomainKeyFromURL();
			return array($domainNameKeyFromURL);
		}
		else{
			
			if(empty($overrideUserId))
				$userID = $this->GetUserID();
			else 
				$userID = intval($overrideUserId);
				
			$domainIdOfUser = UserControl::getDomainIDofUser($userID);
				
			// DO NOT include the Domain of the URL for members.  Otherwise they could just change the Domain in their browser to get access to whatever they wanted.
			// Eventually... as we grow... domains need to be added into a database configuring the permimssions.
			// Domains are case sensitive... make sure they match one of the keys in the Domain class.
			
			// Susie: 8269
			// Mike Hughes: 329
			// Me: 2
			// Bill: 117246
			// Brian W: 97829
			// Chuck: 240890
			// Andy: 109779
			// Laurel: 110300
			// Heather Hendrix: 63402
			// Tammy: 24245
			// Christian: 52204
			// Neel: 435466
			// DotGraphics Production: 91256
			// Jami Schafer: 54644
			// Micheala Piatt: 205054
			// Kamini: 204742
			// Gazelle: 87751

			if($domainIdOfUser == 1){
				if(in_array($userID, $this->GetUserIDsInGroup("SUPERADMIN")))
					return array("PrintsMadeEasy.com", "Postcards.com", "Letterhead.com", "VinylBanners.com", "RefrigeratorMagnets.com", "BusinessCards24.com", "PostcardPrinting.com", "ThankYouCards.com", "LuauInvitations.com", "Bang4BuckPrinting.com", "MarketingEngine.biz", "DotCopyShop.com", "CarMagnets.com", "ZEN_Development", "BusinessHolidayCards.com", "DotGraphics.net", "SunAmerica.com", "MyGymPrinting.com", "CalsonWagonlitPrinting.com", "BusinessCards.co.uk", "PowerBalancePrinting.com", "FlyerPrinting.com", "HolidayGreetingCards.com", "ChristmasPhotoCards.com", "AmazingGrass.com", "PosterPrinting.com", "HotLaptopSkin.com", "BridalShowerInvitations.com", "HouseOfBluesPrinting.com", "NNAServices.org", "ShakeysServices.com", "YourOnlinePrintingCompany.com");
				else if(in_array($userID, array("24245", "64559", "383620", "358364", "363376")) || in_array($userID, $this->GetUserIDsInGroup("PRODUCT_MANAGER")))
					return array("PrintsMadeEasy.com", "Postcards.com", "MarketingEngine.biz", "DotCopyShop.com", "Bang4BuckPrinting.com", "Letterhead.com", "VinylBanners.com", "RefrigeratorMagnets.com", "CarMagnets.com", "PostcardPrinting.com", "BusinessCards24.com", "ThankYouCards.com", "LuauInvitations.com", "ZEN_Development", "BusinessHolidayCards.com", "DotGraphics.net", "MyGymPrinting.com", "CalsonWagonlitPrinting.com", "BusinessCards.co.uk", "PowerBalancePrinting.com", "FlyerPrinting.com", "HolidayGreetingCards.com", "ChristmasPhotoCards.com", "AmazingGrass.com", "PosterPrinting.com", "HotLaptopSkin.com", "BridalShowerInvitations.com", "HouseOfBluesPrinting.com", "NNAServices.org", "ShakeysServices.com", "YourOnlinePrintingCompany.com");
				else if(in_array($userID, $this->GetUserIDsInGroup("MAIL_ADMIN")) || in_array($userID, $this->GetUserIDsInGroup("MAIL_PRODUCTION")) || in_array($userID, $this->GetUserIDsInGroup("MAIL_ASSISTANT")))
					return array("PrintsMadeEasy.com", "Postcards.com", "MarketingEngine.biz", "DotCopyShop.com", "Letterhead.com", "VinylBanners.com", "RefrigeratorMagnets.com", "CarMagnets.com", "PostcardPrinting.com", "BusinessCards24.com", "ThankYouCards.com", "LuauInvitations.com", "BusinessHolidayCards.com", "FlyerPrinting.com", "HolidayGreetingCards.com", "ChristmasPhotoCards.com", "BusinessCards.co.uk", "AmazingGrass.com", "PosterPrinting.com", "HotLaptopSkin.com", "BridalShowerInvitations.com", "HouseOfBluesPrinting.com", "NNAServices.org", "ShakeysServices.com", "YourOnlinePrintingCompany.com");
				else if((in_array($userID, $this->GetUserIDsInGroup("CS"))) || $userID == "87751")
					return array("PrintsMadeEasy.com", "Postcards.com", "Letterhead.com", "VinylBanners.com", "RefrigeratorMagnets.com", "CarMagnets.com", "PostcardPrinting.com", "BusinessCards24.com", "ThankYouCards.com", "LuauInvitations.com", "BusinessHolidayCards.com", "FlyerPrinting.com", "HolidayGreetingCards.com", "ChristmasPhotoCards.com", "BusinessCards.co.uk", "AmazingGrass.com", "PosterPrinting.com", "HotLaptopSkin.com", "BridalShowerInvitations.com", "YourOnlinePrintingCompany.com");
				else
					return array(Domain::getDomainKeyFromID($domainIdOfUser));
			}
			else{
				return array(Domain::getDomainKeyFromID($domainIdOfUser));
			}
				
			//return array("PrintsMadeEasy.com");
		}
	}
	function getUserDomainsIDs($overrideUserId = null){
		
		// Cache results if we are calling this method a lot within a thread, like going down a list.
		if(!empty($overrideUserId)){
			WebUtil::EnsureDigit($overrideUserId);
			
			if(array_key_exists($overrideUserId, $this->_CacheUserDomainIDs))
				return $this->_CacheUserDomainIDs[$overrideUserId];
		}
		else{
			// By using a 2-D array to cache the results... we can simultaneously cache Domain ID's for both overriden UserID's, as well as the default user.
			if(array_key_exists("DEFAULT_USER", $this->_CacheUserDomainIDs))
				return $this->_CacheUserDomainIDs["DEFAULT_USER"];
		}
		
		$retArr = array();
		foreach($this->getUserDomainsKeys($overrideUserId) as $thisDomainKey)
			$retArr[] = Domain::getDomainID($thisDomainKey);

		
		if(!empty($overrideUserId)){
			// Cache the results if the User ID Override is provided.
			$this->_CacheUserDomainIDs[$overrideUserId] = $retArr;
		}
		else{
			// Cache the results for the "Default User"... who may or may not be logged in.
			// They may or may not be an admin.  We just make up an index to store this person 'DEFAULT_USER' which is local to this method. 
			$this->_CacheUserDomainIDs["DEFAULT_USER"] = $retArr;
		}
		
		return $retArr;
	}


	#--- Pass in a string representing a permission --#
	#--- If one of the groups has that permission defined then the function returns TRUE, otherwise FALSE
	#--- Therefore a user belonging to many groups widens the chance of success.
	function CheckForPermission($PermissionParam){

			$found = false;
			foreach($this->_GroupPermissionHash as $GroupName => $GroupChunk){
				if($this->CheckIfBelongsToGroup($GroupName)){
					foreach($GroupChunk as $PermissionName){
						if($PermissionName == $PermissionParam){
							$found = true;
						}
					}
				}
			}
			return $found;
	}


	#-- If this function is called then it means that the user must belong to the group named within the parameter
	#-- Otherwise it will redirect them to the sign in page
	function EnsureGroup($GroupName){

		$found = false;
		foreach($this->_GroupsThatUserBelongsTo as $GrpWithUser){
			if($GrpWithUser == $GroupName){
				$found = true;
			}
		}

		#-- If we didnt find a matching group for the user then take them to the sign in page --#
		if(!$found){
			$this->RedirectUserToSignInPage();
		}
	}


	#-- Will return true or false depending on whether the user belongs to the group --#
	function CheckIfBelongsToGroup($GroupName){

		$found = false;
		foreach($this->_GroupsThatUserBelongsTo as $GrpWithUser){
			if($GrpWithUser == $GroupName){
				$found = true;
			}
		}

		return $found;
	}
	
	function GetGroupsThatUserBelongsTo(){

		return $this->_GroupsThatUserBelongsTo;
	}


	#-- Will return an array of UserID's in within the specified group --#
	// Pass in array of 1 or more groups.  It will do an intersection of groups.
	// So if you only want Users who are both in "CS" and "MEMBER", pass in an array of both.
	function GetUserIDsInGroup($groupNamesArr){
		
		if(!is_array($groupNamesArr))
			$groupNamesArr = array($groupNamesArr);

		if(empty($this->_GroupUserHash))
			$this->InitializeGroupsAndPermission();
		
		foreach($groupNamesArr as $thisGroupName){
			if(!array_key_exists($thisGroupName, $this->_GroupUserHash))
				throw new Exception("Error in method GetUserIDsInGroup. The Group Name does not exist: $thisGroupName");
		}
		
		
		// Add all User ID's into the same array.
		$allUserIDs = array();
		foreach($groupNamesArr as $thisGroupName)
			$allUserIDs = array_merge($allUserIDs, $this->_GroupUserHash[$thisGroupName]);
		
		// Now intersect the userIDs from all groups.
		$returnUserIDs = $allUserIDs;
		foreach($groupNamesArr as $thisGroupName)
			$returnUserIDs = array_intersect($returnUserIDs, $this->_GroupUserHash[$thisGroupName]);
			
		return array_unique($returnUserIDs);
	}


	#########   Public Functions - above #################







	#-- This function builds a list of groups that user belongs to.  -#
	#-- Stores the result within this object so that other functions have access --#
	function SetGroupsThatUserBelongsTo($UserID){

		foreach($this->_GroupUserHash as $GrpName => $GroupList){
			foreach($GroupList as $GrpUID){
				if($UserID == $GrpUID){

					//Well is seems that this user is in this group.  So add the group name to our list
					array_push($this->_GroupsThatUserBelongsTo, $GrpName);
				}
			}
		}
	}
	
	
	// Returns an array showing what groups that the user belongs to.
	// This doesn't do any kind of Domain validation for the userID.
	function GetGroupsThatUserBelongsToByUserID($UserID){
		
		if(!preg_match("/^\d{1,11}$/", $UserID))
			throw new Exception("Error in method GetGroupsThatUserBelongsToByUserID");
		
		$returnArr = array();
		
		foreach($this->_GroupUserHash as $GrpName => $GroupList){
			foreach($GroupList as $GrpUID){
				if($UserID == $GrpUID){
					$returnArr[] = $GrpName;
				}
			}
		}
		
		return $returnArr;
	}
	
	function CheckIfUserIDisMember($UserID){
	
		// Cache the results
		if(empty(self::$allUserIDsWhoAreMembersArr))
			self::$allUserIDsWhoAreMembersArr = $this->GetUserIDsInGroup("MEMBER");

		if(in_array($UserID, self::$allUserIDsWhoAreMembersArr))
			return true;
		else
			return false;
	
	}


	#-- Looks for a UserID in the session to see if a person if he is logged in.  Rerturns true or false ----##
	function CheckIfLoggedIn(){

		// If we overrided the Login... then they are logged in without question.
		if(self::$_UserIDoverride != 0)
			return true;

		// Contains the session variable for the User ID of the person who is logged in.
		$userIDloggedIn = WebUtil::GetSessionVar('UserIDloggedIN', "");
		
		//This session var is set when we do not want to automatically log back in
		$sessionOnlyFlag = WebUtil::GetSessionVar('SessionOnly', "");


		##--- $HTTP_SESSION_VARS['UserIDloggedIN'] is a session variable that will tell us if they are logged in (it will have their UserID).  ---#
		##--- If we are remembering their password... then there will be a permanent cookie on their machine containing their User ID and also an MD5 hash of their password.
		##--- If we find a permanent cookie then automatically log them in, but redirect them to the place that they came from --##
		if (empty($userIDloggedIn)) {
		
			$PreAuthUserID = WebUtil::GetCookie("PreAuthUserID");
			$PreAuthUserPW = WebUtil::GetCookie("PreAuthUserPW");

			if(!empty($PreAuthUserID) && empty($sessionOnlyFlag)){

				if (!preg_match("/^\d+$/", $PreAuthUserID))
					return false;

				$this->_dbCmd->Query("SELECT Password FROM users WHERE ID=" . intval($PreAuthUserID));
				if($this->_dbCmd->GetNumRows() == 0)
					return false;
				$ActualPassword = $this->_dbCmd->GetValue();
				
				if($this->CheckIfUserIDisMember($PreAuthUserID))
					$securitySalt = self::getSecuritySaltADMIN();
				else
					$securitySalt = self::getSecuritySaltBasic();

				// If it matches, this will keep them logged in for future requests
				if(md5($ActualPassword . $securitySalt) == $PreAuthUserPW){		
					self::SetUserIDLoggedIn($PreAuthUserID);
					$userIDloggedIn = $PreAuthUserID;
				}
			}

		}
	
		// One more level of protection... make sure it isn't set with garbage or something.
		if (!preg_match("/^\d{1,11}$/", $userIDloggedIn))
			return false;
			
		if(!$this->AccountStatusIsGood($userIDloggedIn))
			return false;
		
		return true;

	}

	#-- Looks for a UserID in the session to see if a person if he is logged in.  Otherwise it redirects to the Login Page ----##
	function EnsureLoggedIn(){
		if(!$this->CheckIfLoggedIn()){
			$this->RedirectUserToSignInPage();
		}
	}
	
	
	function AccountStatusIsGood($userID){
		
		// cache result of DB query.
		if($this->_accountStatusGoodFlag)
			return true;
		
		// Make sure that their account is "enabled"... or has a "Good" Status;
		$this->_dbCmd->Query("SELECT AccountStatus FROM users WHERE ID=" . intval($userID));
		
		if($this->_dbCmd->GetNumRows() == 0)
			return false;
		
		if($this->_dbCmd->GetValue() != "G")
			return false;
		
		$this->_accountStatusGoodFlag = true;	
		
		return true;
	}
	
	
	
	
	
	function EnsureDomainPrivelages(){
		
		$domainNameKeyFromURL = Domain::getDomainKeyFromURL();

		// This check will make sure that the user has permission to view the Domain currently seen in the URL.
		// This is mainly useful if the user is logged in.  They can't trick other parts of our authentication by substituting the Domain name in their browser.
		// The UserID always belongs to a domain, and it has permissions, among other things, says what domains they are allowed to see.
		// ... If a user logs in to Postcards.com with their UserID (with admin privelages).... and subsequently they go to another domain that they haven't been given permissions to view
		// ... although they are still considered logged in to the system (Postcards.com)
		// ... this will prevent them from using any "elevated permissions" on the other domain... even though have have permissions set on postcards.com.  It will force them to log out.
		$userDomainsArr = $this->getUserDomainsKeys();

		if(!in_array($domainNameKeyFromURL, $userDomainsArr)){
			self::SetUserIDLoggedIn(null);
			self::ClearPermanentCookie();
			$this->RedirectUserToSignInPage();
		}
			
		// The User always has permission to view the Domain of the URL that is in their browser.
		// But that doesn't mean that UserID that they are logged in with is allowed to see it.
		if($this->CheckIfLoggedIn()){
			
			$this->_dbCmd->Query("SELECT DomainID FROM users WHERE ID=" . intval($this->GetUserID()));
			$domainID = $this->_dbCmd->GetValue();

			
			if(!Domain::checkIfDomainIDexists($domainID)){
				WebUtil::WebmasterError("Problem with EnsureDomainPrivelages.  The Domain ID doesn't exist: $domainID : for the User ID: " . $this->GetUserID());
				throw new Exception("Problem loading User Info in Authentication module.");
			}
			
			$domainKeyFromUserDatabase = Domain::getDomainKeyFromID($domainID);
			
			if(!in_array($domainKeyFromUserDatabase, $userDomainsArr)){
				self::SetUserIDLoggedIn(null);
				self::ClearPermanentCookie();
				$this->RedirectUserToSignInPage();
			}
		}

	}
	
	// This is the place that people would be going after loggin in.
	// By default, take them to the place they were trying to get when they were first sent to the "sign-in" page.
	function getDefaultRedirectionURL(){
		
		$redirectURL = "";
		
		$websiteUrlForDomain = Domain::getWebsiteURLforDomainID(Domain::getDomainIDfromURL());
		
		if(empty($_SERVER['HTTPS']))
			$redirectURL = "http://$websiteUrlForDomain/";
		else
			$redirectURL = "https://$websiteUrlForDomain/";

		$redirectURL .= basename($_SERVER['PHP_SELF']) . "?" . $_SERVER['QUERY_STRING'];
	
		return $redirectURL;
	}


	// You can override the place that the user will be redireted to after they successfully login.
	// Otherwise they will be redirected back to the place they were trying to go.
	function SetUpRedirectionURL($destinationURL){

		$this->_RedirectionURL = $destinationURL;
	}
	
	
	// Pass in True if you want to ensure that the user gets redirected to the "https" version of the page after successfully logging in.
	function RedirectToSecurePageAfterLogin($secureFlag){
	
		if(!is_bool($secureFlag))
			throw new Exception("Error in method RedirectToSecurePageAfterLogin. The flag must be boolean.");
	
		$this->_afterLoginSecureFlag = $secureFlag;
	}


	function RedirectUserToSignInPage(){
		
		if(empty($this->_RedirectionURL))
			$signInRedirectAddress = $this->getDefaultRedirectionURL();
		else
			$signInRedirectAddress = $this->_RedirectionURL;
		
		// If we want a secure page (after logging in)... then we just search replace the protocol part from the URI.
		// Before we redirect, lets make sure that this server can support https
		if(preg_match("/https/i", Constants::GetServerSSL())){
			if($this->_afterLoginSecureFlag)
				$signInRedirectAddress = preg_replace("/^http:/", "https:", $signInRedirectAddress);
		}

		$websiteUrlForDomain = Domain::getWebsiteURLforDomainID(Domain::getDomainIDfromURL());
		
		
		if(Domain::isUrlForTheFrontEnd())
			$redirectURL = WebUtil::FilterURL(Constants::GetServerSSL() . "://$websiteUrlForDomain/signin.php?transferaddress=" . urlencode($signInRedirectAddress));
		else
			$redirectURL = WebUtil::FilterURL(Constants::GetServerSSL() . "://$websiteUrlForDomain/ad_login.php?transferaddress=" . urlencode($signInRedirectAddress));
		
			
		// Close Session lock as soon as possible.
		session_write_close();
		
		header("Location: " . $redirectURL);
		
		exit;
	}
	


}






?>
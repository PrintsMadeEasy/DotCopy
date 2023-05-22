<?
class UserControl {

	protected $_dbCmd;
	
	protected $_UserLoadedOK;

	// These protected fields should match the columns in the DB
	protected $_ID;
	protected $_Email;
	protected $_Password;
	protected $_Name;
	protected $_Company;
	protected $_Address;
	protected $_AddressTwo;
	protected $_City;
	protected $_State;
	protected $_Zip;
	protected $_Country;
	protected $_ResidentialFlag;
	protected $_Phone;
	protected $_PhoneSearch;
	protected $_Newsletter;
	protected $_Hint;
	protected $_CustomerNotes;
	protected $_HearAbout;
	protected $_CopyrightTemplates;
	protected $_CopyrightHiddenAtReg;
	protected $_LoyaltyProgram;
	protected $_LoyaltyHiddenAtReg;
	protected $_AffiliateName;
	protected $_AffiliateDiscount;
	protected $_AffiliateExpires;
	protected $_BillingType;
	protected $_BillingStatus;
	protected $_AccountType;
	protected $_AccountStatus;
	protected $_AddressLocked;
	protected $_DowngradeShipping;
	protected $_SingleOrderLimit;
	protected $_MonthsChargesLimit;
	protected $_CreditLimit;
	protected $_DateCreated; // Dates are stored in Unix Timestamps
	protected $_DateLastUsed;
	protected $_SalesRep;
	protected $_DomainID;
	protected $_Rating;
	
	// For caching the results of certain queries
	protected $_cacheTotalCustomerSpend;
	protected $_cacheTotalOrders;
	protected $_cacheCustomerCommunications;
	
	protected $_dbUserArr = array();
	
	static protected $_cacheCustomerDomainIDsArr;



	###-- Constructor --###
	function UserControl(DbCmd $dbCmd){
		$this->_dbCmd = $dbCmd;
		
		$this->_InitUserData();
	}
	
	
	function _InitUserData(){
	
		$this->_UserLoadedOK = false;
		
		// Set defaults.  
		// This may be useful if we are adding a new user and didn't set all of the properties
		// It also allows us to check if data has been set before adding a new user
		$this->_Email = "";
		$this->_Password = "";
		$this->_Name = "";
		$this->_Company = "";
		$this->_Address = "";
		$this->_AddressTwo = "";
		$this->_City = "";
		$this->_State = "";
		$this->_Zip = "";
		$this->_Country = "";
		$this->_Phone = "";
		$this->_PhoneSearch = "";
		$this->_Hint = "";
		$this->_CustomerNotes = "";
		$this->_HearAbout = "";
		$this->_AffiliateName = "";
		$this->_AffiliateDiscount = "";
		$this->_AffiliateExpires = "";
		$this->_CreditLimit = "500";
		$this->_SingleOrderLimit = "2000";
		$this->_MonthsChargesLimit = "5000";
		$this->_DateCreated = "";
		$this->_DateLastUsed = "";
		$this->_SalesRep = "0";
		$this->_Rating = "0";
		$this->_PasswordUpdateRequired = "N";
		
		
		// Start them out as a 'N'ormal user instead of a 'R'eseller
		$this->_AccountType = "N";

		// Set the remaining defaults using our methods
		$this->setBillingType("N");
		$this->setBillingStatus("G");
		$this->setAccountStatus("G");
		$this->setAddressLocked("N");
		$this->setDowngradeShipping("Y");
		$this->setResidentialFlag("Y");
		$this->setNewsletter("Y");
		$this->setCopyrightTemplates("N");
		$this->setLoyaltyProgram("N");
		$this->setLoyaltyHiddenAtReg("Y");
		$this->setCopyrightHiddenAtReg("Y");
		
		$this->_cacheTotalCustomerSpend = null;
		$this->_cacheTotalOrders = null;
		$this->_cacheCustomerCommunications = null;
	
	}
	
	#-- BEGIN   Static Methods  ---------- #
	
	
	static function GetEmailByUserID(DbCmd $dbCmd, $UserID){
	
		$uControlObj = new UserControl($dbCmd);
		$uControlObj->LoadUserByID($UserID);
		return $uControlObj->getEmail();
		
	}
	static function GetUserIDByEmail(DbCmd $dbCmd, $email, $domainID){
	
		$uControlObj = new UserControl($dbCmd);
		$uControlObj->LoadUserByEmail($email, $domainID);
		return $uControlObj->getUserID();

	}
	static function GetNameByUserID(DbCmd $dbCmd, $UserID){
		
		$dbCmd->Query("SELECT Name FROM users WHERE ID=" . intval($UserID));
		
		if($dbCmd->GetNumRows() == 0)
			return "System";
		
		return $dbCmd->GetValue();
	}
	static function GetCompanyByUserID(DbCmd $dbCmd, $UserID){

		$dbCmd->Query("SELECT Company FROM users WHERE ID=" . intval($UserID));
		
		if($dbCmd->GetNumRows() == 0)
			return "System";
		
		return $dbCmd->GetValue();

	}
	static function GetCompanyOrNameByUserID(DbCmd $dbCmd, $UserID){

		if(!self::CheckIfUserIDexists($dbCmd, $UserID))
			return system;
		
		$companyName = self::GetCompanyByUserID($dbCmd, $UserID);
		$personName = self::GetNameByUserID($dbCmd, $UserID);
		
		if(empty($companyName))
			return $personName;
		else
			return $companyName;
	}
	
	static function CheckIfUserIsReseller(DbCmd $dbCmd, $UserID){
		
		$UserID = intval($UserID);
		
		$dbCmd->Query("SELECT COUNT(*) FROM resellers WHERE UserID=$UserID");
		
		if($dbCmd->GetValue() == 0)
			return false;
		else
			return true;
	}
	
	static function CheckIfUserIDexists(DbCmd $dbCmd, $UserID){
		
		if(!preg_match("/^\d{1,11}$/", $UserID))
			throw new Exception("Error with User ID in method CheckIfUserIDexists");
		
		$dbCmd->Query("SELECT COUNT(*) FROM users WHERE ID=" . intval($UserID));
		
		if($dbCmd->GetValue() == 0)
			return false;
		else
			return true;
	}

	// The User ID must exist in the System.  Will return a count of similar accounts it finds in the system
	// The number returned does not include the count of the UserID passed into the method... so most of the time it should return 0
	static function GetCountOfSimlarAccountsToUserID(DbCmd $dbCmd, $customerID){
	
		$similarAccountsArr = UserControl::GetSimilarCustomerIDsByUserID($dbCmd, $customerID);
		
		return sizeof($similarAccountsArr) - 1;
	}
	
	// User ID must already exist before calling this function.
	// Function will include the User ID that is passed into this function.
	static function GetSimilarCustomerIDsByUserID(DbCmd $dbCmd, $customerID){
		
	
		WebUtil::EnsureDigit($customerID);
		$dbCmd->Query("SELECT PhoneSearch, Address, Name FROM users WHERE ID=$customerID");
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Error in function UserControl::GetSimilarCustomerIDsByUserID, UserID does not exist.");
		$row = $dbCmd->GetRow();
		
		$returnArr = UserControl::GetSimilarCustomerIDsByDetails($dbCmd, $row["PhoneSearch"], $row["Name"], $row["Address"]);
		
	
		return $returnArr;
	}
	
	
	// Pass in a Phone Number, a FullName, and a StreetAddress
	// This method will return an array of UserID's that appear to belong to the same person. 
	// Normally just returns 1 element (if the user is already registered).  Can be useful to detect if someone has registered with multiple accounts.
	static function GetSimilarCustomerIDsByDetails(DbCmd $dbCmd, $phoneNumber, $fullName, $streetAddress){
		
	
		$phoneNumber = WebUtil::FilterPhoneNumber($phoneNumber);
		$fullName = trim($fullName);
		$streetAddress = trim($streetAddress);
		
		$passivAuthObj = Authenticate::getPassiveAuthObject();
		$domainIDarr = $passivAuthObj->getUserDomainsIDs();
		$domainWhereClause = DbHelper::getOrClauseFromArray("DomainID", $domainIDarr);
		
		$retArr = array();
		
		$dbCmd->Query("SELECT ID FROM users WHERE PhoneSearch='$phoneNumber' AND $domainWhereClause");
		while($thisID = $dbCmd->GetValue())
			$retArr[] = $thisID;
	
		// Disable the extra check against the street address and the name for now. It was taking up too much server power because the columns are not indexed
		// The phone number search should be good enough.
		/*		
		$secondTryArr = array();
		
		$dbCmd->Query("SELECT ID FROM users WHERE Name LIKE'". DbCmd::EscapeLikeQuery($fullName) . "' AND Address LIKE '". DbCmd::EscapeLikeQuery($streetAddress) . "'");
		while($thisID = $dbCmd->GetValue())
			$secondTryArr[] = $thisID;
		
		$retArr = array_merge($retArr, $secondTryArr);
		$retArr = array_unique($retArr);
		*/
		
		// If there are greater than 15 results then they may have just entered a junk phone number like 111-111-1111  ... in which case dont show an alarm for a similar account
		if(sizeof($retArr) > 15)
			$retArr = array($retArr[0]);
		
		return $retArr;
	}
		
	
	

	// Does pattern matchin on a full name and returns a hash with 2 keys... "First" and "Last";
	static function GetPartsFromFullName($FullName){
	
		$retHash = array();
		$retHash["First"] = "";
		$retHash["Last"] = "";
	
		// Get the first name out of the FullName 
		$matches = array();
		if(preg_match_all("/^((\w|\.)+)\s/", $FullName, $matches))
			$retHash["First"] = ucfirst($matches[1][0]);
	
		// Get the last name out of the FullName
		if(preg_match_all("/\s((\w|\.|'|-)+)$/", stripslashes($FullName), $matches))
			$retHash["Last"] = ucfirst($matches[1][0]);
			
			
		// If it didn't find a separate first name ... then just return the entire name as the First Name.
		// Somtimes people just type in "Brian" for the full name.
		if(empty($retHash["First"]))
			$retHash["First"] = ucfirst($FullName);
		
		return $retHash;
	
	}
	
	


	// Any time a user changes their address or whatever we want to record the changes
	// Just pass in a text file... use line breaks if you want with "\n" to separate multiple changes
	// This is just a notepad type of thing
	static function RecordChangesToUser(DbCmd $dbCmd, $UserID, $UserIDWhoChanged, $changeLogFrom, $changeLogTo){
	
		WebUtil::EnsureDigit($UserID);
		WebUtil::EnsureDigit($UserIDWhoChanged);
	
		$dbCmd->Query("SELECT COUNT(*) FROM users WHERE ID=$UserID AND DomainID=" . Domain::oneDomain());
		if($dbCmd->GetValue() == 0)
			throw new Exception("The UserID does not exist in the function RecordChangesToUser.");
		
		$InsertArr["UserID"] = $UserID;
		$InsertArr["WhoChangedUserID"] = $UserIDWhoChanged;
		$InsertArr["DescriptionFrom"] = $changeLogFrom;
		$InsertArr["DescriptionTo"] = $changeLogTo;
		$InsertArr["Date"] = date("YmdHis");
		$dbCmd->InsertQuery("userchangelog", $InsertArr);
	}
	
	// Returns Zero is the Customer doesn't exist.
	static function getDomainIDofUser($UserID){
		
		$UserID = intval($UserID);
		
		if(isset(self::$_cacheCustomerDomainIDsArr[$UserID]))
			return self::$_cacheCustomerDomainIDsArr[$UserID];
		
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT DomainID FROM users WHERE ID=" . $UserID);
		$domainID = $dbCmd->GetValue();
		
		if(empty($domainID))
			return 0;
			
		self::$_cacheCustomerDomainIDsArr[$UserID] = $domainID;
			
		return $domainID;
	}
	
	
	// Pass in an Main User ID... and then an array of other UserIDs
	// It will figure out which domains the "Main" userID belongs to... and then make sure to filter the list of Users who are not in that user's domain pool
	static function filterUserIDsNotInUserDomainPool(DbCmd $dbCmd, $userIDwithPermission, array $userIDsToFilterarr){
		
		if(!UserControl::CheckIfUserIDexists($dbCmd, $userIDwithPermission))
			throw new Exception("Error in method filterUserIDsNotInUserDomainPool. The User does not exist.");
			
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		$mainUserDomainIDs = $passiveAuthObj->getUserDomainsIDs();
		
		$retArr = array();
		foreach($userIDsToFilterarr as $thisUserID){
			
			$domainOfUser = UserControl::getDomainIDofUser($thisUserID);
			
			if(in_array($domainOfUser, $mainUserDomainIDs))
				$retArr[] = $thisUserID;
		}
		
		return $retArr;
	}

	// Update the timestamp that the user last used the website.
	static function updateDateLastUsed($userID){
		$userID = intval($userID);
		$dbCmd = new DbCmd();
		$dbCmd->UpdateQuery("users", array("DateLastUsed"=>time()), "ID=$userID");
	}
	
	
	#-- BEGIN  Public Methods -------------#
	
	
	// Returns false if the user does not exist, true other wise.
	// Populates protected variables in the class with the information from the DB
	function LoadUserByEmail($EmailAddress, $domainID){
	
		$this->_ValidateEmail($EmailAddress);
		
		if(!Domain::checkIfDomainIDexists($domainID))
			throw new Exception("Error in method LoadUserByEmail. The Domain ID does not exist.");

		$this->_dbCmd->Query( "SELECT ID FROM users WHERE Email LIKE \"" . DbCmd::EscapeLikeQuery($EmailAddress) . "\" AND DomainID='".intval($domainID)."'" );
		
		if( $this->_dbCmd->GetNumRows() == 0 )
			return false;
			
		$uID = $this->_dbCmd->GetValue();
			
		if(!$this->LoadUserByID($uID))
			return false;
			
		return true;
	}

	

	// Returns false if the user does not exist, (or if they don't have permission to view the domain), true otherwise.

	function LoadUserByID($UserID, $authenticateUserforDomain = true){
	
		$this->_UserLoadedOK = false;
	
		$this->_ValidateDigit($UserID);
		
		$this->_dbCmd->Query( "SELECT *, UNIX_TIMESTAMP(DateCreated) AS DateCreatedUNIX, UNIX_TIMESTAMP(DateLastUsed) AS DateLastUsedUNIX , UNIX_TIMESTAMP(AffiliateExpires) AS AffiliateExpiresUNIX 
					FROM users WHERE ID=" . intval($UserID) );

		if( $this->_dbCmd->GetNumRows() == 0 )
			return false;

			
		$ThisRow = $this->_dbCmd->GetRow(); 

		$this->_ID = $ThisRow["ID"];
		$this->_Email = $ThisRow["Email"];
		$this->_Password = $ThisRow["Password"];
		$this->_Name = $ThisRow["Name"];
		$this->_Company = $ThisRow["Company"];
		$this->_Address = $ThisRow["Address"];
		$this->_AddressTwo = $ThisRow["AddressTwo"];
		$this->_City = $ThisRow["City"];
		$this->_State = $ThisRow["State"];
		$this->_Zip = $ThisRow["Zip"];
		$this->_Country = $ThisRow["Country"];
		$this->_ResidentialFlag = $ThisRow["ResidentialFlag"];
		$this->_Phone = $ThisRow["Phone"];
		$this->_PhoneSearch = $ThisRow["PhoneSearch"];
		$this->_Newsletter = $ThisRow["Newsletter"];
		$this->_Hint = $ThisRow["Hint"];
		$this->_CustomerNotes = $ThisRow["CustomerNotes"];
		$this->_HearAbout = $ThisRow["HearAbout"];
		$this->_AffiliateName = $ThisRow["AffiliateName"];
		$this->_AffiliateDiscount = $ThisRow["AffiliateDiscount"];
		$this->_BillingType = $ThisRow["BillingType"];
		$this->_BillingStatus = $ThisRow["BillingStatus"];
		$this->_AccountType = $ThisRow["AccountType"];
		$this->_AccountStatus = $ThisRow["AccountStatus"];
		$this->_AddressLocked = $ThisRow["AddressLocked"];
		$this->_DowngradeShipping = $ThisRow["DowngradeShipping"];
		$this->_CreditLimit = $ThisRow["CreditLimit"];
		$this->_SingleOrderLimit = $ThisRow["SingleOrderLimit"];
		$this->_MonthsChargesLimit = $ThisRow["MonthsChargesLimit"];
		$this->_SalesRep = $ThisRow["SalesRep"];
		$this->_Rating = $ThisRow["Rating"];
		$this->_PasswordUpdateRequired = $ThisRow["PasswordUpdateRequired"];
		$this->_CopyrightTemplates = $ThisRow["CopyrightTemplates"];
		$this->_LoyaltyProgram = $ThisRow["LoyaltyProgram"];
		$this->_LoyaltyHiddenAtReg = $ThisRow["LoyaltyHiddenAtReg"];
		$this->_CopyrightHiddenAtReg = $ThisRow["CopyrightHiddenAtReg"];
		$this->_DomainID = $ThisRow["DomainID"];
		

		// Use Unix TimeStamps
		$this->_DateCreated = $ThisRow["DateCreatedUNIX"];
		$this->_DateLastUsed = $ThisRow["DateLastUsedUNIX"];
		$this->_AffiliateExpires = $ThisRow["AffiliateExpiresUNIX"];
		
		
				
		if($authenticateUserforDomain){
			$passiveAuthObj = Authenticate::getPassiveAuthObject();
			
			if(!$passiveAuthObj->CheckIfUserCanViewDomainID($this->_DomainID))
				return false;
		}
		
		
		$this->_UserLoadedOK = true;

		return true;
	}
	
	// Returns true or false depending on whether User Data has been loaded succesfully
	// It is possible that it could be an empty object.
	function CheckIfUserDataLoaded(){
		return $this->_UserLoadedOK;
	}
	
	// Returns true or false depending on whether the email address already exists in the Database
	function CheckIfEmailExistsInDB($EmailAddress){
		$this->_ValidateEmail($EmailAddress);

		$this->_dbCmd->Query( "SELECT COUNT(*) FROM users WHERE Email LIKE \"" . DbCmd::EscapeLikeQuery($EmailAddress) . "\" AND DomainID=" . Domain::oneDomain());
		
		if( $this->_dbCmd->GetValue() > 0 )
			return true;
		else
			return false;
	}
	
	
	// Returns true if someone else on the website owns the email address
	// This should be called before updating a user's account
	function CheckIfEmailIsOwnedByAnother(){
	
		$this->_EnsureUserDataIsLoaded();

		$this->_dbCmd->Query( "SELECT COUNT(*) FROM users WHERE ID != " . $this->_ID . " AND Email LIKE \"" . DbCmd::EscapeLikeQuery($this->_Email) . "\" AND DomainID=" . Domain::oneDomain());
		
		if( $this->_dbCmd->GetValue() > 0 )
			return true;
		else
			return false;
	}

	
	// Checks if the given password matches what is in the user's account
	function ValidatePassword($pwd){
	
		$this->_EnsureUserDataIsLoaded();
		
		return $this->_Password == $pwd;
	}
	
	
	// Will insert the user into the database and return the new UserID
	function AddNewUser(){
	
		$this->_CheckIfUserCanBeSavedToDB();
		
		if($this->CheckIfEmailExistsInDB($this->_Email))
			$this->_ErrorMessage("You can't add this user because the email address already exists in the DB.");
			
		// If the calling code has not set the dates, then just default times to right now
		if($this->_DateCreated == "")
			$this->_DateCreated = time();
		if($this->_DateLastUsed == "")
			$this->_DateLastUsed = time();
		
		$this->_PopulateUserHashForDB();
		
		$insertArr = $this->_dbUserArr;
		
		// The Domain ID is only set when a new user is created.  You can't change this value when you update the user.
		$insertArr["DomainID"] = Domain::oneDomain();
		
		return $this->_dbCmd->InsertQuery( "users", $insertArr );
	}
	
	
	// Will save the user data back to the DB
	// Data must have been loaded prior to calling this function
	function UpdateUser(){
	
		$this->_EnsureUserDataIsLoaded();
		$this->_CheckIfUserCanBeSavedToDB();
		
		if($this->CheckIfEmailIsOwnedByAnother())
			$this->_ErrorMessage("The email address can not be updated because it is owned by somebody else in the system.");
		
		$this->_PopulateUserHashForDB();
		
		$this->_dbCmd->UpdateQuery( "users", $this->_dbUserArr, ("ID=" . $this->_ID) );
	}
	
	
	// Pass in a reference to a template object.  It will set all of the variables on the HTML template with User Data
	function SetUserTemplateVariables( &$TemplateObj ){
	
	
		$TemplateObj->set_var(array(
			"FULLNAME"=>WebUtil::htmlOutput($this->_Name),
			"COMPANY"=>WebUtil::htmlOutput($this->_Company),
			"EMAIL"=>WebUtil::htmlOutput($this->_Email),
			"ADDRESS"=>WebUtil::htmlOutput($this->_Address),
			"ADDRESS_TWO"=>WebUtil::htmlOutput($this->_AddressTwo),
			"CITY"=>WebUtil::htmlOutput($this->_City),
			"STATE"=>WebUtil::htmlOutput($this->_State),
			"ZIP"=>WebUtil::htmlOutput($this->_Zip),
			"PHONE"=>WebUtil::htmlOutput($this->_Phone),
			"HINT"=>WebUtil::htmlOutput($this->_Hint),
			"PASSWORD"=>WebUtil::htmlOutput($this->_Password),
			"CREDITLIMIT"=>WebUtil::htmlOutput($this->_CreditLimit),
			"SINGLEORDERLIMIT"=>WebUtil::htmlOutput($this->_SingleOrderLimit),
			"MONTHSCHARGESLIMIT"=>WebUtil::htmlOutput($this->_MonthsChargesLimit)
			));

		if($this->_ResidentialFlag == "Y")
			$TemplateObj->set_var(array("RESIDENTIAL_YES"=>"checked","RESIDENTIAL_NO"=>""));
		else
			$TemplateObj->set_var(array("RESIDENTIAL_YES"=>"","RESIDENTIAL_NO"=>"checked"));

		if($this->_Newsletter == "Y")
			$TemplateObj->set_var(array("SPECIALOFFERS_YES"=>"checked","SPECIALOFFERS_NO"=>""));
		else
			$TemplateObj->set_var(array("SPECIALOFFERS_YES"=>"","SPECIALOFFERS_NO"=>"checked"));

		if($this->_AccountStatus == "G")
			$TemplateObj->set_var(array("ACCOUNTSTATUS_GOOD"=>"checked","ACCOUNTSTATUS_BAD"=>""));
		else
			$TemplateObj->set_var(array("ACCOUNTSTATUS_GOOD"=>"","ACCOUNTSTATUS_BAD"=>"checked"));

		if($this->_BillingStatus == "G")
			$TemplateObj->set_var(array("BILLINGSTATUS_GOOD"=>"checked","BILLINGSTATUS_BAD"=>""));
		else
			$TemplateObj->set_var(array("BILLINGSTATUS_GOOD"=>"","BILLINGSTATUS_BAD"=>"checked"));

		if($this->_BillingType == "N")
			$TemplateObj->set_var(array("BILLINGTYPE_NORMAL"=>"checked","BILLINGTYPE_CORPORATE"=>""));
		else
			$TemplateObj->set_var(array("BILLINGTYPE_NORMAL"=>"","BILLINGTYPE_CORPORATE"=>"checked"));
			
		if($this->_AddressLocked == "N")
			$TemplateObj->set_var(array("ADDRESSLOCKED_NO"=>"checked","ADDRESSLOCKED_YES"=>""));
		else
			$TemplateObj->set_var(array("ADDRESSLOCKED_NO"=>"","ADDRESSLOCKED_YES"=>"checked"));
			
		if($this->_DowngradeShipping == "N")
			$TemplateObj->set_var(array("DOWNGRADESHIPPING_NO"=>"checked","DOWNGRADESHIPPING_YES"=>""));
		else
			$TemplateObj->set_var(array("DOWNGRADESHIPPING_NO"=>"","DOWNGRADESHIPPING_YES"=>"checked"));
			
		if($this->_CopyrightTemplates == "Y")
			$TemplateObj->set_var(array("COPYRIGHT_YES"=>"checked","COPYRIGHT_NO"=>""));
		else
			$TemplateObj->set_var(array("COPYRIGHT_YES"=>"","COPYRIGHT_NO"=>"checked"));
			
		if($this->_LoyaltyProgram == "Y")
			$TemplateObj->set_var(array("LOYALTY_YES"=>"checked","LOYALTY_NO"=>""));
		else
			$TemplateObj->set_var(array("LOYALTY_YES"=>"","LOYALTY_NO"=>"checked"));
			
	
	}
	
	// Returns the User's Address with <br> tags after each line.
	// HTML Special characters are already substituted.
	function getAddressInHTML(){
	
		$retHTML = "";
		if(empty($this->_Company))
			$retHTML .= WebUtil::htmlOutput($this->_Name) . "<br>";
		else 
			$retHTML .= WebUtil::htmlOutput($this->_Company) . "<br>Attn:" . WebUtil::htmlOutput($this->_Name) . "<br>";
		
		$retHTML .= WebUtil::htmlOutput($this->getBothAddresses()) . "<br>";
	
		$retHTML .= $this->getCity() . ", " . $this->getState() . "<br>" . $this->getZip();
		
		return $retHTML;
	}
	
	#-- END  Public Methods -------------#
	
	
	
	// GET Properties
	function getUserID(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_ID;
	}
	function getEmail(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_Email;
	}
	function getPassword(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_Password;
	}	
	function getName(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_Name;
	}
	function getNameFirst(){
		$this->_EnsureUserDataIsLoaded();
		$namePartsHash = UserControl::GetPartsFromFullName($this->_Name);
		return $namePartsHash["First"];
	}
	function getNameLast(){
		$this->_EnsureUserDataIsLoaded();
		$namePartsHash = UserControl::GetPartsFromFullName($this->_Name);
		return $namePartsHash["Last"];
	}
	function getCompany(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_Company;
	}
	
	// If the company name is not entered (which is optional)
	// Then it will return the customers name (which is mandatory)
	// Use this if you prefer working with company names for the application
	function getCompanyOrName(){
		$this->_EnsureUserDataIsLoaded();
		
		if(empty($this->_Company))
			return $this->_Name;
		else
			return $this->_Company;
	}	
	function getAddress(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_Address;
	}
	function getAddressTwo(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_AddressTwo;
	}
	// Will add a comma along with the Address 2 ... If there is an Address 2
	function getBothAddresses(){
		$this->_EnsureUserDataIsLoaded();
		
		if(empty($this->_AddressTwo))
			return $this->_Address;
			
		else
			return $this->_Address . ", " . $this->_AddressTwo;
		
	}
	function getCity(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_City;
	}
	function getState(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_State;
	}
	function getZip(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_Zip;
	}
	function getCountry(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_Country;
	}
	function getResidentialFlag(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_ResidentialFlag;
	}
	function getPhone(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_Phone;
	}
	function getNewsletter(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_Newsletter;
	}
	function getHint(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_Hint;
	}
	function getCustomerNotes(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_CustomerNotes;
	}
	function getHearAbout(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_HearAbout;
	}
	function getCopyrightTemplates(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_CopyrightTemplates;
	}
	function getLoyaltyProgram(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_LoyaltyProgram;
	}
	function getLoyaltyHiddenAtReg(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_LoyaltyHiddenAtReg;
	}
	function getCopyrightHiddenAtReg(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_CopyrightHiddenAtReg;
	}
	function getAffiliateName(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_AffiliateName;
	}
	function getAffiliateDiscount(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_AffiliateDiscount;
	}
	function getAffiliateExpires(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_AffiliateExpires;
	}
	
	// If there is no Discount or it has expired then it will return false... otherwise true.
	function checkForActiveDiscount(){
		if(empty($this->_AffiliateDiscount) || empty($this->_AffiliateName) || ($this->_AffiliateExpires < time()))
			return false;
		else
			return true;
	}
	
	// The Credit limit is always $0.00 for customers with "Normal" Accounts
	function getCreditLimit(){
		if($this->_CreditLimit == "" || $this->_BillingType == "N")
			return 0;
		else
			return $this->_CreditLimit;
	}
	

	// The MomnthsCharges limit is always $0.00 for customers with "Corporate" Accounts
	function getMonthsChargesLimit(){
		if($this->_MonthsChargesLimit == "" || $this->_BillingType == "C")
			return 0;
		else
			return $this->_MonthsChargesLimit;
	}

	// The MomnthsCharges limit is always $0.00 for customers with "Corporate" Accounts
	function getSingleOrderLimit(){
		if($this->_SingleOrderLimit == "" || $this->_BillingType == "C")
			return 0;
		else
			return $this->_SingleOrderLimit;
	}
	
	function getDateCreated(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_DateCreated;
	}
	function getDateLastUsed(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_DateLastUsed;
	}
	
	// Returns 'C'orporate   or  'N'normal
	function getBillingType(){
		return $this->_BillingType;
	}
	// Returns 'G'ood   or  'B'ad
	function getBillingStatus(){
		return $this->_BillingStatus;
	}
	// Returns 'R'eseller   or  'N'ormal
	function getAccountType(){
		return $this->_AccountType;
	}
	// Returns 'G'ood   or  'B'ad
	function getAccountStatus(){
		return $this->_AccountStatus;
	}
	// Returns 'Y'es   or  'N'o
	function getAddressLocked(){
		return $this->_AddressLocked;
	}
	
	// Returns 'Y'es   or  'N'o
	function getDowngradeShipping(){
		return $this->_DowngradeShipping;
	}
	
	// Returns '0' if the user does not belong to a sales rep... otherwise returns the UserID that the sales rep belongs to.
	function getSalesRepID(){
		return $this->_SalesRep;
	}
	
	function getUserRating(){
		
		// I just added this field to the Database recently.  And rather than run a script on all old customers...
		// ... let's just let it calculate the missing values when it comes across them.
		if($this->_Rating === NULL){
			UserControl::updateUserRating($this->_ID);
			return $this->calculateCustomerRating();
		}
		
		return $this->_Rating;
	}
	
	// Returns 'Y'es   or  'N'o
	function getPasswordUpdateRequired(){
		return $this->_PasswordUpdateRequired;
	}
	
	function getDomainID(){
		return $this->_DomainID;
	}
	
	
	
	// Returns a text document describing the info in the users account

	// Line breaks separate items.  Can be useful for storing changes to a user's account
	// Pass in a Detailed Flag of true if you want extra information
	function getAccountDescriptionText($detailedFlag = false){
	
		$returnStr = "";
		if($this->getCompany() != "")
			$returnStr .= $this->getCompany() . "\n";
		$returnStr .= $this->getName() . "\n";
		$returnStr .= $this->getBothAddresses() . "\n";
		$returnStr .= $this->getCity() . ", " . $this->getState() . "\n";
		$returnStr .= $this->getZip() . ", " . $this->getCountry() . "\n";
		$returnStr .= $this->getPhone() . "\n";
		$returnStr .= $this->getEmail();
		
		if($detailedFlag){
			$returnStr .= "\n";
			$returnStr .= "Billing Type: " . ($this->getBillingType() == "C" ? "Corporate" : "Credit Card") . "\n";
			if($this->getBillingType() == "C")
				$returnStr .= "Credit Limit: " . $this->getCreditLimit() . "\n";
			if($this->getBillingType() == "N")
				$returnStr .= "Single Order Limit: " . $this->getSingleOrderLimit() . "\n";
			if($this->getBillingType() == "N")
				$returnStr .= "Monthly Limit: " . $this->getMonthsChargesLimit() . "\n";
			$returnStr .= "Billing Status: " . ($this->getBillingStatus() == "G" ? "Good" : "Bad") . "\n";
			$returnStr .= "Discount: " . ($this->getAffiliateDiscount() * 100) . "\n";
			$returnStr .= "Reseller: " . ($this->getAccountType() == "N" ? "No" : "Yes") . "\n";
			$returnStr .= "Address Locked: " . ($this->getAddressLocked() == "N" ? "No" : "Yes") . "\n";
			$returnStr .= "Shipping can Downgrade: " . ($this->getDowngradeShipping() == "N" ? "No" : "Yes") . "\n";
			
			if($this->_CopyrightHiddenAtReg != "Y")
				$returnStr .= "Copyright Templates: " . ($this->getCopyrightTemplates() == "N" ? "No" : "Yes");
			
			if($this->_LoyaltyHiddenAtReg != "Y")
				$returnStr .= "\nLoyalty Program: " . ($this->getLoyaltyProgram() == "N" ? "No" : "Yes");
			
			
		}
		
		return $returnStr;
	}



	// SET Properties
	function setEmail($x){
		$this->_ValidateEmail($x);
		$this->_ValidateStringSize($x, 60);
		$this->_Email = $x;
	}
	function setPassword($x){
		$this->_ValidateStringSize($x, 30);
		if(strlen($x) < 5)
			throw new Exception("The password must be at least 5 characters long.");
		$this->_Password = $x;
	}	
	function setName($x){
		$this->_ValidateStringSize($x, 30);
		$this->_Name = $x;
	}	
	function setCompany($x){
		$this->_ValidateStringSize($x, 60);
		$this->_Company = $x;
	}	
	function setAddress($x){
		$this->_ValidateStringSize($x, 70);
		$this->_Address = $x;
	}
	function setAddressTwo($x){
		$this->_ValidateStringSize($x, 30);
		$this->_AddressTwo = $x;
	}
	function setCity($x){
		$this->_ValidateStringSize($x, 30);
		$this->_City = $x;
	}
	function setState($x){
		if($this->_Country == "US"){
			$this->_ValidateStringSize($x, 2);
			$x = strtoupper($x);
		}
		else
			$this->_ValidateStringSize($x, 30);
		
		$this->_State = $x;
	}
	function setZip($x){
		$this->_ValidateStringSize($x, 30);
		$this->_Zip = $x;
	}
	function setCountry($x){
		$this->_ValidateStringSize($x, 30);
		$this->_Country = $x;
	}
	function setResidentialFlag($x){
		if($x <> "Y" && $x <> "N")
			$this->_ErrorMessage("ResidentialFlag must be a character, Y or N");
		$this->_ResidentialFlag = $x;
	}
	function setPhone($x){
		$this->_ValidateStringSize($x, 30);
		$this->_Phone = $x;
		
		// This will strip out slashes, parenthesis etc... in case we want to search based on phone number in the DB
		$this->_PhoneSearch = WebUtil::FilterPhoneNumber($x);
	}
	function setNewsletter($x){
		if($x <> "Y" && $x <> "N")
			$this->_ErrorMessage("Newsletter must be a character, Y or N");
		$this->_Newsletter = $x;
	}
	function setHint($x){
		$this->_ValidateStringSize($x, 30);
		$this->_Hint = $x;
	}
	function setCustomerNotes($x){
		$this->_ValidateStringSize($x, 250);
		$this->_CustomerNotes = $x;
	}
	function setHearAbout($x){
		$this->_ValidateStringSize($x, 30);
		$this->_HearAbout = $x;
	}
	function setCopyrightTemplates($x){
		if($x <> "Y" && $x <> "N")
			$this->_ErrorMessage("Copyright Templates must be a character, Y or N");
		$this->_CopyrightTemplates = $x;
	}
	function setLoyaltyProgram($x){
		if($x <> "Y" && $x <> "N")
			$this->_ErrorMessage("Loyalty Program must be a character, Y or N");
		$this->_LoyaltyProgram = $x;
	}
	function setLoyaltyHiddenAtReg($x){
		if($x <> "Y" && $x <> "N")
			$this->_ErrorMessage("Loyalty Hidden At Registration Program must be a character, Y or N");
		$this->_LoyaltyHiddenAtReg = $x;
	}
	function setCopyrightHiddenAtReg($x){
		if($x <> "Y" && $x <> "N")
			$this->_ErrorMessage("Copyright Hidden At Registration Program must be a character, Y or N");
		$this->_CopyrightHiddenAtReg = $x;
	}
	function setAffiliateName($x){
		$this->_ValidateStringSize($x, 50);
		$this->_AffiliateName = $x;
	}
	function setAffiliateDiscount($x){
		$this->_ValidateStringSize($x, 3);
		$this->_AffiliateDiscount = $x;
	}
	function setAffiliateExpires($x){
		$this->_AffiliateExpires = $x;
	}
	// set to 'C'orporate   or  'N'normal
	function setBillingType($x){
		if($x <> "C" && $x <> "N")
			$this->_ErrorMessage("Billing Type must be a character, C or N");
		$this->_BillingType = $x;
	}
	// set to 'G'ood   or  'B'ad
	function setBillingStatus($x){
		if($x <> "G" && $x <> "B")
			$this->_ErrorMessage("Billing Status must be a character, G or B");
		$this->_BillingStatus = $x;
	}
	// set to 'G'ood   or  'B'ad
	function setAccountStatus($x){
		if($x <> "G" && $x <> "B")
			$this->_ErrorMessage("Account Status must be a character, G or B");
		$this->_AccountStatus = $x;
	}
	function setAddressLocked($x){
		if($x <> "Y" && $x <> "N")
			$this->_ErrorMessage("Address Locked must be a character, Y or N");
		$this->_AddressLocked = $x;
	}
	function setDowngradeShipping($x){
		if($x <> "Y" && $x <> "N")
			$this->_ErrorMessage("Downgrade Shipping must be a character, Y or N");
		$this->_DowngradeShipping = $x;
	}
	
	function setPasswordUpdateRequired($x){
		if($x <> "Y" && $x <> "N")
			$this->_ErrorMessage("Password Update Required must be a character, Y or N");
		$this->_PasswordUpdateRequired = $x;
	}
	
	
	
	// can be a blank string... or a number
	function setCreditLimit($x){
		if(!empty($x))
			$this->_ValidateDigit($x);
		$this->_ValidateStringSize($x, 10);
		$this->_CreditLimit = $x;
	}
	
	// can be a blank string... or a number
	function setMonthsChargesLimit($x){
		if(!empty($x))
			$this->_ValidateDigit($x);
		$this->_ValidateStringSize($x, 10);
		$this->_MonthsChargesLimit = $x;
	}
	// can be a blank string... or a number
	function setSingleOrderLimit($x){
		if(!empty($x))
			$this->_ValidateDigit($x);
		$this->_ValidateStringSize($x, 10);
		$this->_SingleOrderLimit = $x;
	}
	
	
	function setDateCreated($x){
		$this->_DateCreated = $x;
	}
	function setDateLastUsed($x){
		$this->_DateLastUsed = $x;
	}
	
	
	
	// Set '0' if the user does not belong to a sales rep... set the UserID a Sales Rep... Make sure the UserID is a SalesRep because we won't check it here.
	function setSalesRepID($x){
		
		$this->_ValidateDigit($x);
		
		$this->_SalesRep = $x;
	}
	
	
	function getTotalCustomerSpend(){
	
		$this->_EnsureUserDataIsLoaded();
	
		if($this->_cacheTotalCustomerSpend !== null)
			return $this->_cacheTotalCustomerSpend;
				
		$this->_dbCmd->Query("SELECT SUM(ChargeAmount) FROM charges INNER JOIN orders ON charges.OrderID = orders.ID 
					WHERE (charges.ChargeType='C' || charges.ChargeType='B') AND orders.UserID=" . $this->_ID);
		
		$this->_cacheTotalCustomerSpend = $this->_dbCmd->GetValue();
		
		return $this->_cacheTotalCustomerSpend;
	}
	
	function getTotalOrders(){
		
		$this->_EnsureUserDataIsLoaded();
		
		if($this->_cacheTotalOrders !== null)
			return $this->_cacheTotalOrders;

		// The marketing engine has so many projets that it takes forever to calculate.
		// Eventually this number has to have a auto-increment in the user-row instead.
		if($this->_ID == 6)
			return "99999";
				
		$this->_dbCmd->Query("SELECT orders.ID FROM orders, projectsordered WHERE 
					orders.ID = projectsordered.OrderID 
					AND projectsordered.Status != 'C' 
					AND orders.UserID=" . $this->_ID);
		
		$this->_cacheTotalOrders = $this->_dbCmd->GetValueArr();
		
		$this->_cacheTotalOrders = sizeof(array_unique($this->_cacheTotalOrders));
		
		return $this->_cacheTotalOrders;
	}
	
	function getAverageOrderTotal(){
	
		$this->_EnsureUserDataIsLoaded();
		
		$totalOrders = $this->getTotalOrders();
		$totalSpend = $this->getTotalCustomerSpend();
		
		if($totalOrders == 0)
			return 0;
		else
			return round($totalSpend / $totalOrders, 2); 
	}
	
	// Returns the sum of messages and memos from a customer.
	// One cs item can have many messages from a customer.  This can indicate how big of a nuisance the customer is.
	function getTotalMessagesAndMemos(){
		
		$this->_EnsureUserDataIsLoaded();
		
		if($this->_cacheCustomerCommunications !== null)
			return $this->_cacheCustomerCommunications;
		
		$this->_dbCmd->Query("SELECT COUNT(*) FROM csmessages WHERE FromUserID=" . $this->_ID);
		$totalMessages = $this->_dbCmd->GetValue();
		
		$this->_dbCmd->Query("SELECT COUNT(*) FROM customermemos WHERE CustomerUserID=" . $this->_ID);
		$totalMemos = $this->_dbCmd->GetValue();
		
		$this->_cacheCustomerCommunications = $totalMessages + $totalMemos;
		
		return $this->_cacheCustomerCommunications;
	}
	
	
	// returns zero if no order has been placed.

	function getAverageCommunicationsPerOrder(){
	
		$orderCountTotal = $this->getTotalOrders();
		$totalMessages = $this->getTotalMessagesAndMemos();
		
		if($orderCountTotal == 0)
			return 0;
		
		return round($totalMessages / $orderCountTotal, 3); 
	}
	
	function getAverageOrdersPerMonth(){
	
		$totalDuration = time() - $this->getDateCreated();
		
		$monthsAsCustomer = ceil($totalDuration / (60 * 60 * 24 * 30));
	
		if($monthsAsCustomer == 0)
			return 0;
		
		return round(($this->getTotalOrders() / $monthsAsCustomer), 3);
	}
	
	
	// Returns a Set of ProductID's ordered by the customer since the beggining
	function getProductsOrderedByCustomer(){
		
		$this->_dbCmd->Query("SELECT DISTINCT projectsordered.ProductID FROM projectsordered, orders WHERE 
					orders.ID = projectsordered.OrderID 
					AND projectsordered.Status != 'C' 
					AND orders.UserID=" . $this->_ID);
		$retArr = array();
		
		while($prodID = $this->_dbCmd->GetValue())
			$retArr[] = $prodID;
		
		return $retArr;
	}
	
	// Returns the number of times they have ordered a certain type of product.
	function getProductCountFromCustomer($productID){
		
		$productID = intval($productID);
	
		$this->_dbCmd->Query("SELECT COUNT(*) FROM projectsordered, orders WHERE 
					orders.ID = projectsordered.OrderID 
					AND projectsordered.Status != 'C'
					AND projectsordered.ProductID = '$productID'
					AND orders.UserID=" . $this->_ID);
		
		return $this->_dbCmd->GetValue();
	}
	
	// Returns the total unit count of the Product that they ordered... like if they ordered 2 sets of 1,000 business cards... this method would return 2,000
	function getProductUnitCountFromCustomer($productID){
		
		$productID = intval($productID);
	
		$this->_dbCmd->Query("SELECT SUM(Quantity) FROM projectsordered, orders WHERE 
					orders.ID = projectsordered.OrderID 
					AND projectsordered.Status != 'C'
					AND projectsordered.ProductID = '$productID'
					AND orders.UserID=" . $this->_ID);
		
		return $this->_dbCmd->GetValue();
	}
	
	
	// Returns a number 0 through 10.
	// 0 means it is a new customer or we haven't captured any money from them yet.
	function calculateCustomerRating(){
	
		
		if($this->getTotalOrders() == 1 || $this->getTotalCustomerSpend() == 0)
			return 0;
			
	
		// We have 5 categories each worth 20 points... we will add them up to 100 to get a ratio for the Customer Rating.
		
		// At $3,000 they get a full rating for customer spend.
		$totalSpendCategory = ceil($this->getTotalCustomerSpend() / 3000 * 20);
		$totalSpendCategory = $totalSpendCategory > 20 ? 20 : $totalSpendCategory;
		
		// At $200 per order they get a full rating.
		$avgOrderTotalCategory = ceil($this->getAverageOrderTotal() / 200 * 20);
		$avgOrderTotalCategory = $avgOrderTotalCategory > 20 ? 20 : $avgOrderTotalCategory;
	
		// At 19 orders the customer gets a full rating.
		$orderCountCategory = ceil($this->getTotalOrders() / 19 * 20);
		$orderCountCategory = $orderCountCategory > 20 ? 20 : $orderCountCategory;
	
		// At 1 order per month they get get a full rating.
		$frequencyCategory = ceil($this->getAverageOrdersPerMonth() / 1 * 20);
		$frequencyCategory = $frequencyCategory > 20 ? 20 : $frequencyCategory;
		

		// Don't factor in their Frequency rating if they are a new customer.
		if($orderCountCategory < 4)
			$frequencyCategory = 0;
		
	
		// At 2 communications per order they get no points... at 0 communciations per order they get full points.
		$nuisanceCategory = ceil($this->getAverageCommunicationsPerOrder() / 2 * 20);
		$nuisanceCategory = $nuisanceCategory > 20 ? 20 : $nuisanceCategory;
		$nuisanceCategory = abs(20 - $nuisanceCategory);
		

		
		$totalPointsOutOf100 = $totalSpendCategory + $avgOrderTotalCategory + $orderCountCategory + $frequencyCategory + $nuisanceCategory;
		
		$custRating = ceil($totalPointsOutOf100 / 10);
		
		// Only new customers have a rating of 0;
		if($custRating < 1)
			$custRating = 1;
			
		return $custRating;
	}
	
	// A Static method to update the User rating in the Database.
	static function updateUserRating($userID){
		
		$userID = intval($userID);
		
		$dbCmd = new DbCmd();
		$userControlObj = new UserControl($dbCmd);
		
		if(!$userControlObj->LoadUserByID($userID))
			throw new Exception("Error in method updateUserRating");
			
		$newUserRating = $userControlObj->calculateCustomerRating();
		
		$dbCmd->UpdateQuery("users", array("Rating"=>$newUserRating), "ID=$userID");
	}

	
	// End of Properties ------------------------------------------
	
	
	
	#----- Private Methods below ------#
	
	
	protected function _PopulateUserHashForDB(){
		
		// Unless is is a "N"ormal (or credit card billing type) we can't enroll the user into the loyaly program.
		if($this->_BillingType != "N")
			$this->_LoyaltyProgram = "N";
		
		$this->_dbUserArr["Email"] = $this->_Email;
		$this->_dbUserArr["Password"] = $this->_Password;
		$this->_dbUserArr["Name"] = $this->_Name;
		$this->_dbUserArr["Company"] = $this->_Company;
		$this->_dbUserArr["Address"] = $this->_Address;
		$this->_dbUserArr["AddressTwo"] = $this->_AddressTwo;
		$this->_dbUserArr["City"] = $this->_City;
		$this->_dbUserArr["State"] = $this->_State;
		$this->_dbUserArr["Zip"] = $this->_Zip;
		$this->_dbUserArr["Country"] = $this->_Country;
		$this->_dbUserArr["ResidentialFlag"] = $this->_ResidentialFlag;
		$this->_dbUserArr["Phone"] = $this->_Phone;
		$this->_dbUserArr["PhoneSearch"] = $this->_PhoneSearch;
		$this->_dbUserArr["Newsletter"] = $this->_Newsletter;
		$this->_dbUserArr["Hint"] = $this->_Hint;
		$this->_dbUserArr["CustomerNotes"] = $this->_CustomerNotes;
		$this->_dbUserArr["HearAbout"] = $this->_HearAbout;
		$this->_dbUserArr["CopyrightTemplates"] = $this->_CopyrightTemplates;
		$this->_dbUserArr["LoyaltyProgram"] = $this->_LoyaltyProgram;
		$this->_dbUserArr["LoyaltyHiddenAtReg"] = $this->_LoyaltyHiddenAtReg;
		$this->_dbUserArr["CopyrightHiddenAtReg"] = $this->_CopyrightHiddenAtReg;
		$this->_dbUserArr["AffiliateName"] = $this->_AffiliateName;
		$this->_dbUserArr["AffiliateDiscount"] = $this->_AffiliateDiscount;
		$this->_dbUserArr["BillingType"] = $this->_BillingType;
		$this->_dbUserArr["BillingStatus"] = $this->_BillingStatus;
		$this->_dbUserArr["AccountType"] = $this->_AccountType;
		$this->_dbUserArr["AccountStatus"] = $this->_AccountStatus;
		$this->_dbUserArr["AddressLocked"] = $this->_AddressLocked;
		$this->_dbUserArr["DowngradeShipping"] = $this->_DowngradeShipping;
		$this->_dbUserArr["PasswordUpdateRequired"] = $this->_PasswordUpdateRequired;
		$this->_dbUserArr["CreditLimit"] = $this->_CreditLimit;
		$this->_dbUserArr["SingleOrderLimit"] = $this->_SingleOrderLimit;
		$this->_dbUserArr["MonthsChargesLimit"] = $this->_MonthsChargesLimit;
		$this->_dbUserArr["DateCreated"] = date("YmdHis", $this->_DateCreated);  //Convert back to Mysql timestamps before storing in the DB
		$this->_dbUserArr["DateLastUsed"] = date("YmdHis", $this->_DateLastUsed);
		$this->_dbUserArr["AffiliateExpires"] = empty($this->_AffiliateExpires) ? DbCmd::convertUnixTimeStmpToMysql(time()) : DbCmd::convertUnixTimeStmpToMysql($this->_AffiliateExpires);
	}
	

	// Before a new user can be added to the DB... or it can be updated, we want to make sure certain properties have been set
	protected function _CheckIfUserCanBeSavedToDB(){
		
		$this->_CheckForEmptyProperty("_Email");
		$this->_CheckForEmptyProperty("_Password");
		$this->_CheckForEmptyProperty("_Name");
		$this->_CheckForEmptyProperty("_Address");
		$this->_CheckForEmptyProperty("_City");
		$this->_CheckForEmptyProperty("_State");
		$this->_CheckForEmptyProperty("_Zip");
		$this->_CheckForEmptyProperty("_Country");
		$this->_CheckForEmptyProperty("_Phone");
		
		if($this->_BillingType == "C" && empty($this->_CreditLimit))
			$this->_ErrorMessage("Corporate billing can not be enabled unless a credit limit has been set." );
			
		if($this->_BillingType == "N" && empty($this->_MonthsChargesLimit))
			$this->_ErrorMessage("Credit Card Customers must have a Monthly Charges Limit set." );

		if($this->_BillingType == "N" && empty($this->_SingleOrderLimit))
			$this->_ErrorMessage("Credit Card Customers must have a Sinlge Order Limit set." );
	}
	
	// Pass in a property name... (the protected variable).  It will throw an error if it is empty
	protected function _CheckForEmptyProperty($PropertyName){
		
		$Str = "";
		if(!eval('$Str = $this->' . $PropertyName . '; return true;'))
			$this->_ErrorMessage("Illegal property name in _CheckForEmptyProperty: " . $PropertyName);
			
		if(empty($Str))
			$this->_ErrorMessage("The property has not been set yet:" . $PropertyName );
	}
	
	

	protected function _EnsureUserDataIsLoaded(){
		if( !$this->_UserLoadedOK )
			$this->_ErrorMessage("User Data has not been loaded yet.");
	
	}

	protected function _ErrorMessage($Msg){
		throw new Exception($Msg);
	}

	// Takes a digit or an array or digits
	protected function _ValidateDigit($Num){
	
		if(!is_array($Num))
			$Num = array($Num);
	
		foreach($Num as $ThisNum)
			if(!preg_match("/^\d{1,12}$/", $ThisNum))
				$this->_ErrorMessage("Value is not a digit: " . $ThisNum);
	}

	protected function _ValidateEmail($email){

		if(!preg_match("/^\w+([-.]\w+)*\@\w+([-.]\w+)*\.\w+$/", $email))
			$this->_ErrorMessage("The email address is not valid.");

	}

	// Make sure they are not trying put something in the database that is too large
	protected function _ValidateStringSize($str, $MaxLength){

		if(strlen($str) > $MaxLength)
			$this->_ErrorMessage("The length is too long in: $str");

	}

}



?>
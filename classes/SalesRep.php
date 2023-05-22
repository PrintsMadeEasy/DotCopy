<?


class SalesRep extends UserControl {

	protected  $_dbCmd;

	protected $_IsSalesRepFlag;  			// The Sales Rep table has a foreign key to the "users table
						// This lets us know if a row has been created or not in the reseller table for the given user ID.
					
	protected $_UserDataLoaded;			// Let's us know if we have called the method to load the User Data
	
	// These private fields should match the columns in the DB
	protected $_ParentSalesRep;
	protected $_CommissionPercent;
	protected $_FixedAmntNewCustomer;
	protected $_CanAddSubSalesReps;
	protected $_PaymentsSuspended;
	protected $_AccountDisabled;
	protected $_MonthsExpires;
	protected $_IsAnEmployee;
	protected $_HaveReceivedW9;
	protected $_AddressIsVerified;
	protected $_EmailIsVerified;
	protected $_DateCreated;
	protected $_W9Name;
	protected $_W9Company;
	protected $_W9Address;
	protected $_W9City;
	protected $_W9State;
	protected $_W9Zip;
	protected $_W9TIN;
	protected $_W9TINtype;
	protected $_W9BusinessType;
	protected $_W9BusinessTypeOther;
	protected $_W9Exempt;
	protected $_W9AccountNumbers;
	protected $_W9Coments;
	protected $_W9DateSigned;
	protected $_W9FiledByUserID;

	
	protected $_dbSalesRepArr = array();


	###-- Constructor --###
	
	// Will initialize User Data and Reseller data
	// The function will turn 
	function SalesRep(DbCmd $dbCmd){

		$this->_dbCmd = $dbCmd;
		
		$this->_InitUserData();
		$this->_InitSalesRepData();
	}


	function _InitSalesRepData(){
	
		$this->_IsSalesRepFlag = false;  
		$this->_UserDataLoaded = false;  
		
		// Set defaults. 
		$this->_ParentSalesRep = -1;  // Negative 1 means that it isn't set yet.
		$this->_CommissionPercent = "";
		$this->_FixedAmntNewCustomer = null;
		$this->_CanAddSubSalesReps = true;
		$this->_PaymentsSuspended = true; // Start out with suspended payments until their email address is at least verified
		$this->_AccountDisabled = false;
		$this->_MonthsExpires = 120;  // Default, expires in 10 years
		$this->_IsAnEmployee = false;  // By default they are not an employee.  Employees will make up a small percentate of Sales Reps
		$this->_HaveReceivedW9 = false;  // When they first sign up, we have not received a W9
		$this->_AddressIsVerified = false;
		$this->_EmailIsVerified = false;
		$this->_W9Name = "";
		$this->_W9Company = "";
		$this->_W9Address = "";
		$this->_W9City = "";
		$this->_W9State = "";
		$this->_W9Zip = "";
		$this->_W9TIN = "";
		$this->_W9TINtype = "";
		$this->_W9BusinessType = "";
		$this->_W9BusinessTypeOther = "";
		$this->_W9Exempt = false;
		$this->_W9AccountNumbers = "";
		$this->_W9Coments = "";
		$this->_W9DateSigned = null;
		$this->_W9FiledByUserID = "";
	}
	


	#-- BEGIN Static Methods --------------#
	

	

	// Any time we make a change to a Sales rep... such as the Percentage rate, we should record the change
	// Just pass in a text file... use line breaks if you want with "\n" to separate multiple changes
	// This is just a notepad type of thing, very versatile and informal
	function RecordChangesToSalesRep(DbCmd $dbCmd, $SalesRepUserID, $UserIDWhoChanged, $TextFile){
		
		WebUtil::EnsureDigit($SalesRepUserID);
		WebUtil::EnsureDigit($UserIDWhoChanged);
		
		$dbCmd->Query("SELECT COUNT(*) FROM salesreps WHERE UserID=$SalesRepUserID");
		if($dbCmd->GetValue() == 0)
			throw new Exception("The SalesREP UserID does not exist.");
		
		$InsertArr["SalesUserID"] = $SalesRepUserID;
		$InsertArr["WhoChangedUserID"] = $UserIDWhoChanged;
		$InsertArr["Description"] = $TextFile;
		$InsertArr["Date"] = date("YmdHis");
		$dbCmd->InsertQuery("salesrepchangelog", $InsertArr);
	}



	
	#-- BEGIN  Public Methods -------------#


	// Returns false if the user is not a Sales Rep  
	// Returns true if they are
	// You must ensure that the $UserID is valid before creating this object.
	// It will load the User Data and the Releller data (if it exists) all at the same time
	function LoadSalesRep($UserID){

		$this->_ValidateDigit($UserID);
	
		// Try to load the User information
		// If the user exist... this method should return false as well
		if(!$this->LoadUserByID($UserID))
			$this->_ErrorMessage("A sales rep is trying to be opened and the UserID does not exist.");
	
		$this->_UserDataLoaded = true;
			
		// If there is an entry in the Reseller table for this user... then try to load it
		$this->_dbCmd->Query( "SELECT *, UNIX_TIMESTAMP(DateCreated) as DateStart, 
						UNIX_TIMESTAMP(W9DateSigned) as W9Signed FROM salesreps WHERE UserID=" . $UserID );

		if( $this->_dbCmd->GetNumRows() == 0 ){
			$this->_IsSalesRepFlag = false;
			
			return false;
		}
		else{
			$ThisRow = $this->_dbCmd->GetRow(); 
			
			if($ThisRow["CanAddSubSalesReps"] == "Y")
				$this->_CanAddSubSalesReps = true;
			else if($ThisRow["CanAddSubSalesReps"] == "N")
				$this->_CanAddSubSalesReps = false;
			else
				throw new Exception("Problem with field CanAddSubSalesReps");
	
			if($ThisRow["PaymentsSuspended"] == "Y")
				$this->_PaymentsSuspended = true;
			else if($ThisRow["PaymentsSuspended"] == "N")
				$this->_PaymentsSuspended = false;
			else
				throw new Exception("Problem with field PaymentsSuspended");
	
			if($ThisRow["AccountDisabled"] == "Y")
				$this->_AccountDisabled = true;
			else if($ThisRow["AccountDisabled"] == "N")
				$this->_AccountDisabled = false;
			else
				throw new Exception("Problem with field AccountDisabled");
				
			if($ThisRow["IsAnEmployee"] == "Y")
				$this->_IsAnEmployee = true;
			else if($ThisRow["IsAnEmployee"] == "N")
				$this->_IsAnEmployee = false;
			else
				throw new Exception("Problem with field IsAnEmployee");
				
			if($ThisRow["HaveReceivedW9"] == "Y")
				$this->_HaveReceivedW9 = true;
			else if($ThisRow["HaveReceivedW9"] == "N")
				$this->_HaveReceivedW9 = false;
			else
				throw new Exception("Problem with field HaveReceivedW9");	
				
			if($ThisRow["AddressIsVerified"] == "Y")
				$this->_AddressIsVerified = true;
			else if($ThisRow["AddressIsVerified"] == "N")
				$this->_AddressIsVerified = false;
			else
				throw new Exception("Problem with field AddressIsVerified");
				
			if($ThisRow["EmailIsVerified"] == "Y")
				$this->_EmailIsVerified = true;
			else if($ThisRow["EmailIsVerified"] == "N")
				$this->_EmailIsVerified = false;
			else
				throw new Exception("Problem with field EmailIsVerified");

			if($ThisRow["W9Exempt"] == "Y")
				$this->_W9Exempt = true;
			else if($ThisRow["W9Exempt"] == "N")
				$this->_W9Exempt = false;
			else
				throw new Exception("Problem with field W9Exempt");

			$this->_ParentSalesRep = $ThisRow["ParentSalesRep"];
			$this->_CommissionPercent = $ThisRow["CommissionPercent"];
			$this->_FixedAmntNewCustomer = $ThisRow["FixedAmntNewCustomer"];
			$this->_MonthsExpires = $ThisRow["MonthsExpires"];
			$this->_DateCreated = $ThisRow["DateStart"];
			$this->_W9Name = $ThisRow["W9Name"];
			$this->_W9Company = $ThisRow["W9Company"];
			$this->_W9Address = $ThisRow["W9Address"];
			$this->_W9City = $ThisRow["W9City"];
			$this->_W9State = $ThisRow["W9State"];
			$this->_W9Zip = $ThisRow["W9Zip"];
			$this->_W9TIN = $ThisRow["W9TIN"];
			$this->_W9TINtype = $ThisRow["W9TINtype"];
			$this->_W9BusinessType = $ThisRow["W9BusinessType"];
			$this->_W9BusinessTypeOther = $ThisRow["W9BusinessTypeOther"];
			$this->_W9AccountNumbers = $ThisRow["W9AccountNumbers"];
			$this->_W9Coments = $ThisRow["W9Coments"];
			$this->_W9DateSigned = $ThisRow["W9Signed"];
			$this->_W9FiledByUserID = $ThisRow["W9FiledByUserID"];

			
			// A link exists between the user and the "salesrep" table.
			$this->_IsSalesRepFlag = true;
			
			return true;
		}
	}


	
	// Will either update or insert the record for a sales rep
	// The method LoadSalesRep must have been loaded prior to calling this function
	function SaveSalesRep(){
	
		$this->_EnsureUserDataIsLoaded();
		$this->_CheckIfSalesRepCanBeSavedToDB();
		
		// This will fail and abort if the User data was not loaded correctly
		$this->UpdateUser();

		$this->_PopulateSalesRepHashForDB();
		
		// If a relational row exists between the "users" and "resellers" table... then we can just update.  Otherwise insert a link.
		if($this->_IsSalesRepFlag){
		
			// We don't ever want the Date Created to change after they have been added for the first time.
			if(isset($this->_dbSalesRepArr["DateCreated"]))
				unset($this->_dbSalesRepArr["DateCreated"]);
		
			$this->_dbCmd->UpdateQuery( "salesreps", $this->_dbSalesRepArr, "UserID=" . $this->_ID );
			
		}
		else{
		
			// Add the date that this record was inserted.
			$this->_dbSalesRepArr["DateCreated"] = date("YmdHis");
			
			$this->_dbCmd->InsertQuery( "salesreps", $this->_dbSalesRepArr );
			$this->_IsSalesRepFlag = true;  // Now the link does exist after the insert.
		}
	}
	
	

	// A sales rep must be load first before calling this method.
	// Will return true or false depending on whether the new percent can be changed for the Sales rep that is loaded.
	// If the SalesRep is at the root... then you can always change the percent up to 100%.
	// If the SalesRep belongs to another SalesRep, then you can not put the percent any greater than what the parent has.
	// You can also not decrease the percent any lower than the highest percent of any children belonging to the sales rep.
	function CheckIfPercentageCanBeChanged($NewPercent){
	
		$this->_EnsureUserIsSalesRep();
		
		$this->_EnsurePercentValueIsValid($NewPercent);
		
		// Check For Max values
		if($this->CheckIfPercentIsGreaterThanParent($NewPercent))
			return false;
		
		// Check For Minimum Values
		// $this->_ID comes from the UserControl class that we are extending.
		$this->_dbCmd->Query( "SELECT CommissionPercent FROM salesreps WHERE ParentSalesRep=" . $this->_ID );
		
		// If there are no sub reps then we know that any percentate is cool. The percenage is not valid below 0% anyway
		if($this->_dbCmd->GetNumRows() == 0)
			return true;
			
		while($ChildPercent = $this->_dbCmd->GetValue())
			if($ChildPercent > $NewPercent)
				return false;
		
		return true;
	}
	
	function CheckIfPercentIsGreaterThanParent($percent){

		if($this->_ParentSalesRep == -1)
			throw new Exception("A parent Sales Rep must be set before calling the method CheckIfPercentIsGreaterThanParent.");
			
		$this->_EnsurePercentValueIsValid($percent);
		
		// If the SalesRep is at the root then you can go up to 100% all of the time.
		if($this->_ParentSalesRep == 0)
			return false;

		$this->_dbCmd->Query( "SELECT CommissionPercent FROM salesreps WHERE UserID=" . $this->_ParentSalesRep );
		$ParentPercent = $this->_dbCmd->GetValue();

		if($percent > $ParentPercent)
			return true;
		else
			return false;
	}
	
	// Returns an array of Coupon ID's that are associated with this Sales Rep
	function GetCouponIDsBelongingToSalesRep(){
		
		$retArr = array();
		$this->_dbCmd->Query( "SELECT ID FROM coupons WHERE SalesLink=" . $this->_ID);
		while($thisCouponID = $this->_dbCmd->GetValue())
			$retArr[] = $thisCouponID;
		return $retArr;
	}
	
	// Send the User an email containing a hash of their User ID
	// It instructs them to return to the website and paste in the Code to fully activate their account.
	function SendEmailVerificationEmail(){
		$this->_EnsureUserIsSalesRep();
		
		$abrieviatedCompanyName = Domain::getAbreviatedNameForDomainID($this->_DomainID);
		$domainKey = Domain::getDomainKeyFromID($this->_DomainID);
		
		$Subject = "$domainKey Email Verification";
		$Body = "Dear " . $this->_Name . ",\n\nWe need to verify that your email address is 
legitimate before we start sending any payments.  
Your email verification code is... \n" .  $this->getUserIDhashForVerification() . "\n\n
Copy and paste this code into the verification box within the $domainKey website. 
Click on My Account -> Sales Commission System -> Payment History\n\n  
You will continue to see the email verification box on the bottom of that screen until you submit the verification code.
Feel free to ask Customer Service for assistance.\n\nThank You,\nThe $abrieviatedCompanyName Sales Team";
	
		$domainEmailConfig = new DomainEmails($this->_DomainID);
		
		WebUtil::SendEmail($domainEmailConfig->getEmailNameOfType(DomainEmails::SALES), $domainEmailConfig->getEmailAddressOfType(DomainEmails::SALES), $this->_Name, $this->_Email, $Subject, $Body);
	}
	
	function getUserIDhashForVerification(){
	
		$this->_EnsureUserDataIsLoaded();
		
		$hash = md5($this->_ID);
		
		// Return a digest of the MD5 Algothym... just reshuffles a bit to make it less obvious how we make the code. 
		// Hash is 20 bytes
		return substr($hash, 4, 12) . substr($hash, 8, 2) . substr($hash, 2, 1) . substr($hash, 20, 5);
	}
	
	// Call this method whenever you like.... it may possibly suspend the Sales Rep's account based on the current situation
	// If it is passed the Address Verifcation Deadline... of if this Sales Rep has earned over a certain amount within the Year and the W9 has not been received
	// It will also Save the SalesRep info to the database.... so make sure not to put anything funny in the object before calling this method if you don't want it to get saved.

	function PossiblySuspendPayments(){
	
		$this->_EnsureUserIsSalesRep();
		
		// If they are already suspended... then no need to check any further
		if($this->CheckIfPaymentsSuspended())
			return;

		// If their address hasn't been verified yet and it is past the deadline, then suspend payments for this rep
		if(!$this->getAddressIsVerified() && ($this->getAddressVerficationDeadlineTimestamp() < time())){
			$this->setPaymentsSuspended(true);
			$this->SaveSalesRep();
			return;
		}
		
		// If we have received their W9 (or they are an employee) then we have no other reaons to put their account into suspension
		if($this->getHaveReceivedW9() || $this->getIsAnEmployee())
			return;
		
		$SalesCommissionsObj = new SalesCommissions($this->_dbCmd, $this->_DomainID);
		$SalesCommissionsObj->SetUser($this->_ID);
		$SalesCommissionsObj->SetMonthYear("ALL", date("Y"));
		
		$TotalCommissionsThisYear = $SalesCommissionsObj->GetTotalCommissionsWithinPeriodForUser($this->_ID, "All", "GoodAndSuspended");
	
		// If they earn more than 500 in a year and their W-9 is not received, then suspend their payments
		if($TotalCommissionsThisYear >= 500.00){
			$this->setPaymentsSuspended(true);
			$this->SaveSalesRep();
			return;
		}
	}

	
	#-- END  Public Methods -------------#

	
	
	// GET Properties
	function CheckIfSalesRep(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_IsSalesRepFlag;
	}
	function CheckIfCanAddSubSalesReps(){
		$this->_EnsureUserIsSalesRep();
		return $this->_CanAddSubSalesReps;
	}
	function CheckIfPaymentsSuspended(){
		$this->_EnsureUserIsSalesRep();
		return $this->_PaymentsSuspended;
	}
	function CheckIfAccountDisabled(){
		$this->_EnsureUserIsSalesRep();
		return $this->_AccountDisabled;
	}	
	function getParentSalesRep(){
		$this->_EnsureUserIsSalesRep();
		return $this->_ParentSalesRep;
	}
	function getMonthsExpires(){
		$this->_EnsureUserIsSalesRep();
		return $this->_MonthsExpires;
	}
	function getDateCreated(){
		$this->_EnsureUserIsSalesRep();
		return $this->_DateCreated;
	}
	function getCommissionPercent(){
		$this->_EnsureUserIsSalesRep();
		return $this->_CommissionPercent;
	}
	function getNewCustomerCommission(){
		$this->_EnsureUserIsSalesRep();
		return $this->_FixedAmntNewCustomer;
	}
	function getIsAnEmployee(){
		$this->_EnsureUserIsSalesRep();
		return $this->_IsAnEmployee;
	}
	function getHaveReceivedW9(){
		$this->_EnsureUserIsSalesRep();
		return $this->_HaveReceivedW9;
	}
	function getAddressIsVerified(){
		$this->_EnsureUserIsSalesRep();
		return $this->_AddressIsVerified;
	}
	function getEmailIsVerified(){
		$this->_EnsureUserIsSalesRep();
		return $this->_EmailIsVerified;
	}
	
	
	// returns the timestamp that is 90 days past when they were first registered
	// We will begin to suspend payments if we have not received a form back by this time.
	function getAddressVerficationDeadlineTimestamp(){
		$this->_EnsureUserIsSalesRep();
		return $this->_DateCreated + (60 * 60 * 24 * 90);
	}
	// The coupon code is based upon the user ID
	// So the person does not have to be a Sales Rep yet for us to know what the coupon code will be
	// The Sales Rep can add additional coupons if they wish... but this is a default coupon that all Sales Reps are entitled to.
	function getDefaultSalesCouponCode(){
		$this->_EnsureUserDataIsLoaded();
		return "S" . $this->_ID;
	}
	


	function getW9Name(){
		$this->_EnsureUserIsSalesRep();
		return $this->_W9Name;
	}
	function getW9Company(){
		$this->_EnsureUserIsSalesRep();
		return $this->_W9Company;
	}
	function getW9Address(){
		$this->_EnsureUserIsSalesRep();
		return $this->_W9Address;
	}
	function getW9City(){
		$this->_EnsureUserIsSalesRep();
		return $this->_W9City;
	}
	function getW9State(){
		$this->_EnsureUserIsSalesRep();
		return $this->_W9State;
	}
	function getW9Zip(){
		$this->_EnsureUserIsSalesRep();
		return $this->_W9Zip;
	}
	function getW9TIN(){
		$this->_EnsureUserIsSalesRep();
		return $this->_W9TIN;
	}
	function getW9TINtype(){
		$this->_EnsureUserIsSalesRep();
		return $this->_W9TINtype;
	}
	function getW9BusinessType(){
		$this->_EnsureUserIsSalesRep();
		return $this->_W9BusinessType;
	}
	function getW9BusinessTypeOther(){
		$this->_EnsureUserIsSalesRep();
		return $this->_W9BusinessTypeOther;
	}
	function getW9Exempt(){
		$this->_EnsureUserIsSalesRep();
		return $this->_W9Exempt;
	}
	function getW9AccountNumbers(){
		$this->_EnsureUserIsSalesRep();
		return $this->_W9AccountNumbers;
	}
	function getW9Coments(){
		$this->_EnsureUserIsSalesRep();
		return $this->_W9Coments;
	}
	function getW9DateSigned(){
		$this->_EnsureUserIsSalesRep();
		return $this->_W9DateSigned;
	}
	function getW9FiledByUserID(){
		$this->_EnsureUserIsSalesRep();
		return $this->_W9FiledByUserID;
	}





	// SET Properties
	function setCanAddSubSalesReps($x){
		if(!is_bool($x))
			throw new Exception("The method setCanAddSubSalesReps must take a bool parameter");
		$this->_CanAddSubSalesReps = $x;
	}
	function setPaymentsSuspended($x){
		if(!is_bool($x))
			throw new Exception("The method setPaymentsSuspended must take a bool parameter");
		$this->_PaymentsSuspended = $x;
	}
	function setAccountDisabled($x){
		if(!is_bool($x))
			throw new Exception("The method setAccountDisabled must take a bool parameter");
		$this->_AccountDisabled = $x;
	}
	
	// Passing in 0 means that the SalesRep doesn't have a parent... it is the root.
	function setParentSalesRep($x){
		$this->_ValidateDigit($x);
		
		if($this->_IsSalesRepFlag)
			throw new Exception("You can not change the SalesRep parent after the SalesRep has been created.");
		
		// Make sure that the UserID belongs to a SalesRep.
		if($x <> 0){
			$this->_dbCmd->Query("SELECT CanAddSubSalesReps FROM salesreps WHERE UserID=$x");

			if($this->_dbCmd->GetNumRows() == 0)
				throw new Exception("The parent SalesRep that you are trying to set does not exist.");
			
			if($this->_dbCmd->GetValue() == "N")
				throw new Exception("The Parent Sales rep does not have permmissions to add Sub Reps.");
		}
		
		$this->_ParentSalesRep = $x;
	}	
	function setMonthsExpires($x){
		$this->_ValidateDigit($x);
		$this->_MonthsExpires = $x;
	}	
	function setIsAnEmployee($x){
		if(!is_bool($x))
			throw new Exception("The method setIsAnEmployee must take a bool parameter");
		$this->_IsAnEmployee = $x;
	}
	
	// When we Record that a W9 is received... the action may cause the Sales Reps payments to stop being suspended
	// ... but only if their Email Address AND Regular Address have been verified already.
	function setHaveReceivedW9($x){
		if(!is_bool($x))
			throw new Exception("The method setHaveReceivedW9 must take a bool parameter");
			
		if($this->_EmailIsVerified && $this->_AddressIsVerified)
			$this->setPaymentsSuspended(false);

		$this->_HaveReceivedW9 = $x;
	}
	
	// When we Record that an Address is Verified... the action may cause the Sales Reps payments to stop being suspended
	// ... but only if their Email Address has been verified already.
	function setAddressIsVerified($x){
		if(!is_bool($x))
			throw new Exception("The method setAddressIsVerified must take a bool parameter");
			
		if($this->_EmailIsVerified)
			$this->setPaymentsSuspended(false);
		
		$this->_AddressIsVerified = $x;
	}
	
	// Whenever we verify the email address we will also make sure that the Users payments are not suspended any more.
	// This should happen relatively quickly after the account sign up
	function setEmailIsVerified($x){
		if(!is_bool($x))
			throw new Exception("The method setAddressIsVerified must take a bool parameter");
			
		$this->setPaymentsSuspended(false);
		
		$this->_EmailIsVerified = $x;
	}
	

	
	function setCommissionPercent($x){
		$this->_EnsurePercentValueIsValid($x);
		$this->_EnsureUserDataIsLoaded();
		
		// Checking whether the percent is valid differs whether we are saving a New SalesRep... or changing the percentate of an existing SalesRep.
		if($this->_IsSalesRepFlag){
			if(!$this->CheckIfPercentageCanBeChanged($x))
				throw new Exception("The Commission percent can not be set on this sales rep because the value is not within range.  Try checking the data with the method CheckIfPercentageCanBeChanged before setting this property");
		}
		else{
			// For new Sales rep we don't need to check for minimum percents because, by default, new Sales Reps don't have any children.
		
			if($this->_ParentSalesRep == -1)
				throw new Exception("A parent Sales Rep must be set before calling setCommissionPercent.");

			if($this->CheckIfPercentIsGreaterThanParent($x))
				throw new Exception("The commission percent that you are trying to set is greater than the parent.");
		}
		
		$this->_CommissionPercent = $x;
	}
	
	
	function setNewCustomerCommission($x){
	
		$this->_EnsureUserDataIsLoaded();
		
		if(!empty($x)){
			if(!preg_match("/^(\d+(\.\d{1,2})?)?$/", $x))
				throw new Exception("Error with Commision amount in method setNewCustomerCommission");
			
			$x = number_format($x, 2);
		}
		
		
		$this->_FixedAmntNewCustomer = $x;
	}




	// You can not set the W-9 details until after the User has been registered as a Sales Rep
	function setW9Name($x){
		$this->_EnsureUserIsSalesRep();
		$this->_ValidateStringSize($x, 30);
		$this->_W9Name = $x;
	}
	function setW9Company($x){
		$this->_EnsureUserIsSalesRep();
		$this->_ValidateStringSize($x, 60);
		$this->_W9Company = $x;
	}
	function setW9Address($x){
		$this->_ValidateStringSize($x, 70);
		$this->_EnsureUserIsSalesRep();
		$this->_W9Address = $x;
	}
	function setW9City($x){
		$this->_EnsureUserIsSalesRep();
		$this->_ValidateStringSize($x, 30);
		$this->_W9City = $x;
	}
	function setW9State($x){
		$this->_EnsureUserIsSalesRep();
		$this->_ValidateStringSize($x, 30);
		$this->_W9State = strtoupper($x);
	}
	function setW9Zip($x){
		$this->_EnsureUserIsSalesRep();
		$this->_ValidateStringSize($x, 30);
		$this->_W9Zip = $x;
	}
	function setW9TIN($x){
		$this->_EnsureUserIsSalesRep();
		
		$x = preg_replace("/\s/", "", $x);
		$x = preg_replace("/-/", "", $x);
		$x = preg_replace("/\//", "", $x);
		
		$this->_ValidateStringSize($x, 12);
		$this->_W9TIN = $x;
	}
	function setW9TINtype($x){
		$this->_EnsureUserIsSalesRep();
		$this->_ValidateStringSize($x, 1);
		if($x <> "S" && $x <> "E")	// SSN or EIN
			throw new Exception("Illegal TIN type.");
		$this->_W9TINtype = $x;
	}
	function setW9BusinessType($x){
		$this->_EnsureUserIsSalesRep();
		$this->_ValidateStringSize($x, 1);
		if($x <> "I" && $x <> "C" && $x <> "P" && $x <> "O")	// Individual, Corporation, Partnership, Other
			throw new Exception("Illegal TIN type.");
		$this->_W9BusinessType = $x;
	}
	function setW9BusinessTypeOther($x){
		$this->_EnsureUserIsSalesRep();
		$this->_ValidateStringSize($x, 30);
		$this->_W9BusinessTypeOther = $x;
	}
	function setW9Exempt($x){
		if(!is_bool($x))
			throw new Exception("The method setW9Exempt must take a bool parameter");
		$this->_EnsureUserIsSalesRep();
		$this->_W9Exempt = $x;
	}
	function setW9AccountNumbers($x){
		$this->_EnsureUserIsSalesRep();
		$this->_ValidateStringSize($x, 255);		
		$this->_W9AccountNumbers = $x;
	}
	function setW9Coments($x){
		$this->_EnsureUserIsSalesRep();
		$this->_ValidateStringSize($x, 255);
		$this->_W9Coments = $x;
	}
	// Send in a Unix time Stamp
	function setW9DateSigned($x){
		$this->_EnsureUserIsSalesRep();
		$this->_W9DateSigned = $x;
	}
	function setW9FiledByUserID($x){
		$this->_EnsureUserIsSalesRep();
		$this->_W9FiledByUserID = $x;
	}

	

	// End of Properties ------------------------------------------
	
	
	
	#----- Private Methods below ------#
	
	
	function _PopulateSalesRepHashForDB(){
	
		if($this->_CanAddSubSalesReps)
			$this->_dbSalesRepArr["CanAddSubSalesReps"] = "Y";
		else
			$this->_dbSalesRepArr["CanAddSubSalesReps"] = "N";

		if($this->_PaymentsSuspended)
			$this->_dbSalesRepArr["PaymentsSuspended"] = "Y";
		else
			$this->_dbSalesRepArr["PaymentsSuspended"] = "N";

		if($this->_AccountDisabled)
			$this->_dbSalesRepArr["AccountDisabled"] = "Y";
		else
			$this->_dbSalesRepArr["AccountDisabled"] = "N";

		if($this->_IsAnEmployee)
			$this->_dbSalesRepArr["IsAnEmployee"] = "Y";
		else
			$this->_dbSalesRepArr["IsAnEmployee"] = "N";
		
		if($this->_HaveReceivedW9)
			$this->_dbSalesRepArr["HaveReceivedW9"] = "Y";
		else
			$this->_dbSalesRepArr["HaveReceivedW9"] = "N";
			
		if($this->_AddressIsVerified)
			$this->_dbSalesRepArr["AddressIsVerified"] = "Y";
		else
			$this->_dbSalesRepArr["AddressIsVerified"] = "N";			

		if($this->_EmailIsVerified)
			$this->_dbSalesRepArr["EmailIsVerified"] = "Y";
		else
			$this->_dbSalesRepArr["EmailIsVerified"] = "N";	

		if($this->_W9Exempt)
			$this->_dbSalesRepArr["W9Exempt"] = "Y";
		else
			$this->_dbSalesRepArr["W9Exempt"] = "N";

		$this->_dbSalesRepArr["ParentSalesRep"] = $this->_ParentSalesRep;
		$this->_dbSalesRepArr["CommissionPercent"] = $this->_CommissionPercent;
		$this->_dbSalesRepArr["FixedAmntNewCustomer"] = $this->_FixedAmntNewCustomer;
		$this->_dbSalesRepArr["MonthsExpires"] = $this->_MonthsExpires;
		$this->_dbSalesRepArr["UserID"] = $this->_ID;
		
		$this->_dbSalesRepArr["W9Name"] = $this->_W9Name;
		$this->_dbSalesRepArr["W9Company"] = $this->_W9Company;
		$this->_dbSalesRepArr["W9Address"] = $this->_W9Address;
		$this->_dbSalesRepArr["W9City"] = $this->_W9City;
		$this->_dbSalesRepArr["W9State"] = $this->_W9State;
		$this->_dbSalesRepArr["W9Zip"] = $this->_W9Zip;
		$this->_dbSalesRepArr["W9TIN"] = $this->_W9TIN;
		$this->_dbSalesRepArr["W9TINtype"] = $this->_W9TINtype;
		$this->_dbSalesRepArr["W9BusinessType"] = $this->_W9BusinessType;
		$this->_dbSalesRepArr["W9BusinessTypeOther"] = $this->_W9BusinessTypeOther;
		$this->_dbSalesRepArr["W9AccountNumbers"] = $this->_W9AccountNumbers;
		$this->_dbSalesRepArr["W9Coments"] = $this->_W9Coments;
		$this->_dbSalesRepArr["W9FiledByUserID"] = $this->_W9FiledByUserID;
		
		if(empty($this->_W9DateSigned))
			$this->_dbSalesRepArr["W9DateSigned"] = null;
		else
			$this->_dbSalesRepArr["W9DateSigned"] = date("YmdHis", $this->_W9DateSigned);
		
	}
	
	
	
	// Before a new user can be added to the DB... or it can be updated, we want to make sure certain properties have been set
	function _CheckIfSalesRepCanBeSavedToDB(){	
	
		if($this->_ParentSalesRep == -1)
			throw new Exception("The parent sales rep must be set before saving");
			
		$this->_CheckForEmptyProperty("_CommissionPercent");
		
		// If we have marked this Sales Rep as having received the W9... then the following fields are mandatory
		if($this->_HaveReceivedW9){

			$this->_CheckForEmptyProperty("_W9Name");
			$this->_CheckForEmptyProperty("_W9Address");
			$this->_CheckForEmptyProperty("_W9City");
			$this->_CheckForEmptyProperty("_W9State");
			$this->_CheckForEmptyProperty("_W9Zip");
			$this->_CheckForEmptyProperty("_W9TIN");
			$this->_CheckForEmptyProperty("_W9BusinessType");


			// If the Business Type is Other... Make sure that they have entered a Description
			// Otherwise make sure that they haven't entered a description.
			if($this->_W9BusinessType == "O"){
				if(empty($this->_W9BusinessTypeOther))
					throw new Exception("If the business type is other, then you must enter a description");
			}
			else{
				if(!empty($this->_W9BusinessTypeOther))
					throw new Exception("You may only have a business description if the business type is set to Other.");	
			}
		}
	}

	function _EnsureUserDataIsLoaded(){
		if( !$this->_UserDataLoaded )
			$this->_ErrorMessage("User Data has not been loaded yet.");
			
	}
	function _EnsureUserIsSalesRep(){
	
		$this->_EnsureUserDataIsLoaded();
		
		if( !$this->_IsSalesRepFlag )
			$this->_ErrorMessage("User is not a Sales Rep.");
			
	}

	function _EnsurePercentValueIsValid($PercentVal){
		if(!preg_match("/^\d+(\.\d{1,2})?$/", $PercentVal))
			throw new Exception("The percent value is not correct.");
			
		if($PercentVal > 100)
			throw new Exception("The percent value can not exceed 100%.");
	}
	// Make sure they are not trying put something in the database that is too large
	function _ValidateStringSize($str, $MaxLength){

		if(strlen($str) > $MaxLength)
			$this->_ErrorMessage("The length is too long in: $str");

	}
	
}



?>
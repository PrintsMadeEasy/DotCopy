<?

class Reseller extends UserControl {

	protected $_dbCmd;
	
	protected $_RelationalRowCreatedFlag;  	// The Reseller table has a foreign key to the "users table
						// This lets us know if a row has been created or not in the reseller table for the given user ID.
						
						
	// These private fields should match the columns in the DB
	protected $_rAttention;
	protected $_rCompany;
	protected $_rAddress;
	protected $_rAddressTwo;
	protected $_rCity;
	protected $_rState;
	protected $_rZip;
	protected $_rCountry;
	protected $_rPhone;
	
	protected $_InvoiceMsg1;
	protected $_InvoiceMsg2;
	protected $_InvoiceMsg3;
	
	protected $_LogoImage; // will hold binary data for the image
	
	protected $_dbResellerArr = array();
	



	###-- Constructor --###
	
	// Will initialize User Data and Reseller data
	// The function will turn 
	function Reseller(DbCmd $dbCmd){

		$this->_dbCmd = $dbCmd;
		
		$this->_InitUserData();
		$this->_InitResellerData();
	}


	function _InitResellerData(){
	
		$this->_RelationalRowCreatedFlag = false;  
		
		// Set defaults.  
		$this->_rAttention = "";
		$this->_rCompany = "";
		$this->_rAddress = "";
		$this->_rAddressTwo = "";
		$this->_rCity = "";
		$this->_rState = "";
		$this->_rZip = "";
		$this->_rCountry = "";
		$this->_rPhone = "";
		
		$this->_InvoiceMsg1 = "";
		$this->_InvoiceMsg2 = "";
		$this->_InvoiceMsg3 = "";
		
		$this->_LogoImage = "";
	}


	
	#-- BEGIN  Public Methods -------------#


	// Returns false if the user is not a reseller  
	// Returns true if they are
	// You must ensure that the $UserID is valid before creating this object.
	// It will load the User Data and the Releller data (if it exists) all at the same time
	function LoadReseller($UserID, $authenticateUserforDomain = true){
		
		$this->_ValidateDigit($UserID);
	
		// Try to load the User information
		// If the user exist... this method should return false as well
		if(!$this->LoadUserByID($UserID, $authenticateUserforDomain))
			$this->_ErrorMessage("A reseller is trying to be opened and the UserID does not exist.");
	
			
		// If there is an entry in the Reseller table for this user... then try to load it
		$this->_dbCmd->Query( "SELECT * FROM resellers WHERE UserID=" . $UserID );
		
		if( $this->_dbCmd->GetNumRows() == 0 ){
			$this->_RelationalRowCreatedFlag = false;
		}
		else{
		
			$ThisRow = $this->_dbCmd->GetRow(); 
	
			$this->_rAttention = $ThisRow["rAttention"];
			$this->_rCompany = $ThisRow["rCompany"];
			$this->_rAddress = $ThisRow["rAddress"];
			$this->_rAddressTwo = $ThisRow["rAddressTwo"];
			$this->_rCity = $ThisRow["rCity"];
			$this->_rState = $ThisRow["rState"];
			$this->_rZip = $ThisRow["rZip"];
			$this->_rCountry = $ThisRow["rCountry"];
			$this->_rPhone = $ThisRow["rCountry"];
			$this->_InvoiceMsg1 = $ThisRow["InvoiceMsg1"];
			$this->_InvoiceMsg2 = $ThisRow["InvoiceMsg2"];
			$this->_InvoiceMsg3 = $ThisRow["InvoiceMsg3"];
			$this->_LogoImage = $ThisRow["LogoImage"];
	
			
			// A link exists between the user and the "reseller" table.
			$this->_RelationalRowCreatedFlag = true;
		}

			
		// Now return true or false... depending on whether the user is a reseller or not
		if($this->getAccountType() == "R")
			return true;
		else
			return false;

	}


	

	
	// Will save the user data back to the DB
	// Data must have been loaded prior to calling this function
	function UpdateReseller(){
	
		$this->_EnsureUserDataIsLoaded();
		$this->_CheckIfResellerCanBeSavedToDB();
		
		// This will fail and abort if the User data was not loaded correctly
		$this->UpdateUser();

		$this->_PopulateResellerHashForDB();
		
		// If a relational row exists between the "users" and "resellers" table... then we can just update.  Otherwise insert a link.
		if($this->_RelationalRowCreatedFlag){
			$this->_dbCmd->UpdateQuery( "resellers", $this->_dbResellerArr, "UserID=" . $this->_ID );
		}
		else{
			$this->_dbCmd->InsertQuery( "resellers", $this->_dbResellerArr );
			$this->_RelationalRowCreatedFlag = true;  // Now the link does exist after the insert.
		}
		
	}
	
	
	// Pass in a reference to a template object.  It will set all of the variables on the HTML template with User Data
	function SetResellerTemplateVariables( &$TemplateObj ){

		// If no reseller information has been saved yet, then pre-default their information with the User Account
		if(empty($this->_rAddress)){

			$TemplateObj->set_var(array(

				"R_ATTENTION"=>"",
				"R_COMPANY"=>WebUtil::htmlOutput($this->getCompany()),
				"R_ADDRESS"=>WebUtil::htmlOutput($this->getAddress()),
				"R_ADDRESS_TWO"=>WebUtil::htmlOutput($this->getAddressTwo()),
				"R_CITY"=>WebUtil::htmlOutput($this->getCity()),
				"R_STATE"=>WebUtil::htmlOutput($this->getState()),
				"R_ZIP"=>WebUtil::htmlOutput($this->getZip()),
				"R_PHONE"=>WebUtil::htmlOutput($this->getPhone())
				));
		}
		else{

			$TemplateObj->set_var(array(
				"R_ATTENTION"=>WebUtil::htmlOutput($this->_rAttention),
				"R_COMPANY"=>WebUtil::htmlOutput($this->_rCompany),
				"R_ADDRESS"=>WebUtil::htmlOutput($this->_rAddress),
				"R_ADDRESS_TWO"=>WebUtil::htmlOutput($this->_rAddressTwo),
				"R_CITY"=>WebUtil::htmlOutput($this->_rCity),
				"R_STATE"=>WebUtil::htmlOutput($this->_rState),
				"R_ZIP"=>WebUtil::htmlOutput($this->_rZip),
				"R_PHONE"=>WebUtil::htmlOutput($this->_rPhone)
				));
		}
			
			
		$TemplateObj->set_var(array(
			"INV_MESSAGE_1"=>WebUtil::htmlOutput($this->_InvoiceMsg1),
			"INV_MESSAGE_2"=>WebUtil::htmlOutput($this->_InvoiceMsg2),
			"INV_MESSAGE_3"=>WebUtil::htmlOutput($this->_InvoiceMsg3)
			));


		if($this->_AccountType == "R")
			$TemplateObj->set_var(array("RESELLER_YES"=>"checked","RESELLER_NO"=>""));
		else
			$TemplateObj->set_var(array("RESELLER_YES"=>"","RESELLER_NO"=>"checked"));

	}
	
	#-- END  Public Methods -------------#
	
	
	
	// GET Properties
	function getResellerAttention(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_rAttention;
	}
	function getResellerCompany(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_rCompany;
	}
	function getResellerAddress(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_rAddress;
	}	
	function getResellerAddressTwo(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_rAddressTwo;
	}
	function getResellerCity(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_rCity;
	}
	function getResellerState(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_rState;
	}
	function getResellerZip(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_rZip;
	}
	function getResellerCountry(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_rCountry;
	}
	function getResellerPhone(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_rPhone;
	}
	function getInvoiceMessage1(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_InvoiceMsg1;
	}	
	function getInvoiceMessage2(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_InvoiceMsg2;
	}
	function getInvoiceMessage3(){
		$this->_EnsureUserDataIsLoaded();
		return $this->_InvoiceMsg3;
	}
	function getLogoImage(){
		return $this->_LogoImage;
	}




	// SET Properties
	function setResellerAttention($x){
		$this->_ValidateStringSize($x, 30);
		$this->_rAttention = $x;
	}	
	function setResellerCompany($x){
		$this->_ValidateStringSize($x, 60);
		$this->_rCompany = $x;
	}	
	function setResellerAddress($x){
		$this->_ValidateStringSize($x, 70);
		$this->_rAddress = $x;
	}
	function setResellerAddressTwo($x){
		$this->_ValidateStringSize($x, 30);
		$this->_rAddressTwo = $x;
	}
	function setResellerCity($x){
		$this->_ValidateStringSize($x, 30);
		$this->_rCity = $x;
	}
	function setResellerState($x){
		if($this->_rCountry == "US"){
			$this->_ValidateStringSize($x, 2);
			$x = strtoupper($x);
		}
		else
			$this->_ValidateStringSize($x, 30);
		
		$this->_rState = $x;
	}
	function setResellerZip($x){
		$this->_ValidateStringSize($x, 30);
		$this->_rZip = $x;
	}
	function setResellerCountry($x){
		$this->_ValidateStringSize($x, 30);
		$this->_rCountry = $x;
	}
	function setResellerPhone($x){
		$this->_ValidateStringSize($x, 30);
		$this->_rPhone = $x;
	}
	function setInvoiceMessage1($x){
		$this->_ValidateStringSize($x, 200);
		$this->_InvoiceMsg1 = $x;
	}
	function setInvoiceMessage2($x){
		$this->_ValidateStringSize($x, 200);
		$this->_InvoiceMsg2 = $x;
	}
	function setInvoiceMessage3($x){
		$this->_ValidateStringSize($x, 200);
		$this->_InvoiceMsg3 = $x;
	}
	function setLogoImage($x){
		$this->_LogoImage = $x;
	}

	
	// Only the reseller can set the account type.
	// The parent class "UserControl" shouldn't have the power to do this because they don't have permission to validate other data
	// set to 'R'eseller   or  'N'ormal
	function setAccountType($x){
		if($x <> "R" && $x <> "N")
			$this->_ErrorMessage("Account Type must be a character, R or N");
		$this->_AccountType = $x;
	}
	
	// End of Properties ------------------------------------------
	
	
	
	#----- Private Methods below ------#
	
	
	function _PopulateResellerHashForDB(){
		$this->_dbResellerArr["UserID"] = $this->_ID;
		$this->_dbResellerArr["rAttention"] = $this->_rAttention;
		$this->_dbResellerArr["rCompany"] = $this->_rCompany;
		$this->_dbResellerArr["rAddress"] = $this->_rAddress;
		$this->_dbResellerArr["rAddressTwo"] = $this->_rAddressTwo;
		$this->_dbResellerArr["rCity"] = $this->_rCity;
		$this->_dbResellerArr["rState"] = $this->_rState;
		$this->_dbResellerArr["rZip"] = $this->_rZip;
		$this->_dbResellerArr["rCountry"] = $this->_rCountry;
		$this->_dbResellerArr["rPhone"] = $this->_rPhone;
		$this->_dbResellerArr["InvoiceMsg1"] = $this->_InvoiceMsg1;
		$this->_dbResellerArr["InvoiceMsg2"] = $this->_InvoiceMsg2;
		$this->_dbResellerArr["InvoiceMsg3"] = $this->_InvoiceMsg3;
		$this->_dbResellerArr["LogoImage"] = $this->_LogoImage;
	}
	
	
	
	// Before a new user can be added to the DB... or it can be updated, we want to make sure certain properties have been set
	function _CheckIfResellerCanBeSavedToDB(){
	

		// If they are a reseller, then make sure that the following fields have not been left out
		if($this->_AccountType == "R"){
			$this->_CheckForEmptyProperty("_rAttention");
			$this->_CheckForEmptyProperty("_rAddress");
			$this->_CheckForEmptyProperty("_rCity");
			$this->_CheckForEmptyProperty("_rState");
			$this->_CheckForEmptyProperty("_rZip");
			$this->_CheckForEmptyProperty("_rCountry");
			$this->_CheckForEmptyProperty("_rPhone");
			$this->_CheckForEmptyProperty("_InvoiceMsg1");
			$this->_CheckForEmptyProperty("_InvoiceMsg2");
			$this->_CheckForEmptyProperty("_InvoiceMsg3");
		}

	}


}



?>
<?

// This class gets/sets and manages the default shipping address(s) for users 
// this is helpful during the checkout process.
class UserShippingAddresses {

	
	private $dbCmd;
	private $userID;

	function __construct($customerID){

		$this->dbCmd = new DbCmd();
		
		if(!UserControl::CheckIfUserIDexists($this->dbCmd, $customerID))
			throw new Exception("Error in constructor with Customer ID.");
		
		$this->userID = $customerID;
		
	}



	// If the user doesn't have any addresses saved yet,
	// Then the system will return the "account address"
	function getDefaultShippingAddress(){
		
		$this->dbCmd->Query("SELECT * FROM usershippingaddresses WHERE UserID=" . intval($this->userID) . " AND  IsDefault='Y'"); 
		
		if($this->dbCmd->GetNumRows() == 1){
			$aR = $this->dbCmd->GetRow();
			return $this->getMailingAddressObjFromDatabaseRow($aR);
		}
		else{
			$uC = new UserControl($this->dbCmd);
			$uC->LoadUserByID($this->userID, false);
		
			$mailingObj = new MailingAddress($uC->getName(), $uC->getCompany(), $uC->getAddress(), $uC->getAddressTwo(), $uC->getCity(), $uC->getState(), $uC->getZip(), $uC->getCountry(), true, $uC->getPhone());
			return $mailingObj;
		}
	}
	
	// Private Function: Pass in a row from a database result.
	private function getMailingAddressObjFromDatabaseRow(array $aR){

		$mailingAddressObj = new MailingAddress($aR["Name"], $aR["Company"], $aR["Address"], $aR["AddressTwo"], $aR["City"], $aR["State"], $aR["Zip"], $aR["Country"], ($aR["ResidentialFlag"] == "Y" ? true : false), $aR["Phone"]);
		return $mailingAddressObj;
	}
	
	// Returns an empty array in case there are no addresses saved on the user's account.
	function getAllUserAddresses(){
	
		$returnArr = array();
		$this->dbCmd->Query("SELECT * FROM usershippingaddresses WHERE UserID=" . intval($this->userID));

		while($row = $this->dbCmd->GetRow()){
			$returnArr[] = $this->getMailingAddressObjFromDatabaseRow($row);
		}
		
		return $returnArr;
	}
	
	function checkIfAddressIsDefault($addressSignature){
		
		if(strlen($addressSignature) != 32)
			throw new Exception("The Address signature is incorrect");
			
		$this->dbCmd->Query("SELECT IsDefault FROM usershippingaddresses WHERE UserID=" . intval($this->userID) . " AND RequestSignature='".DbCmd::EscapeLikeQuery($addressSignature)."'");
		if($this->dbCmd->GetValue() == "Y")
			return true;
		else
			return false;
	}
	
	
	function addNewAddress(MailingAddress $addressObj){
		
		// Don't add duplicate addresses
		$this->dbCmd->Query("SELECT COUNT(*) FROM usershippingaddresses WHERE UserID=" . intval($this->userID) . " AND RequestSignature='".DbCmd::EscapeLikeQuery($addressObj->getSignatureOfFullAddress())."'");
		if($this->dbCmd->GetValue() != 0)
			return;
			
		// If this is the first address which the user is adding, then it must be set to the default address.
		$isFirstTimeAddress = true;
		$this->dbCmd->Query("SELECT COUNT(*) FROM usershippingaddresses WHERE UserID=" . intval($this->userID));
		if($this->dbCmd->GetValue() > 0)
			$isFirstTimeAddress = false;
	
		$insertArr["UserID"] = $this->userID;
		$insertArr["IsDefault"] = $isFirstTimeAddress ? "Y" : "N";
		$insertArr["RequestSignature"] = $addressObj->getSignatureOfFullAddress();
		$insertArr["Name"] = $addressObj->getAttention();
		$insertArr["Company"] = $addressObj->getCompanyName();
		$insertArr["Address"] = $addressObj->getAddressOne();
		$insertArr["AddressTwo"] = $addressObj->getAddressTwo();
		$insertArr["City"] = $addressObj->getCity();
		$insertArr["State"] = $addressObj->getState();
		$insertArr["Zip"] = $addressObj->getZipCode();
		$insertArr["Country"] = $addressObj->getCountryCode();
		$insertArr["ResidentialFlag"] = $addressObj->isResidential() ? "Y" : "N";
		$insertArr["Phone"] = $addressObj->getPhoneNumber();
		
		$this->dbCmd->InsertQuery("usershippingaddresses", $insertArr);

	}
	
	function deleteAddress($addressSignature){
		if(strlen($addressSignature) != 32)
			throw new Exception("The Address signature is incorrect");
			
		$this->dbCmd->Query("DELETE FROM usershippingaddresses WHERE UserID=" . intval($this->userID) . " AND RequestSignature='".DbCmd::EscapeLikeQuery($addressSignature)."'");
	
		// In case we deleted the default address, we need to set a new default.
		$this->dbCmd->Query("SELECT COUNT(*) FROM usershippingaddresses WHERE UserID=" . intval($this->userID) . " AND  IsDefault='Y'"); 
		if($this->dbCmd->GetValue() != 0)
			return;
			
		// This will set the first address on the users account (almost by random)
		// If the user doesn't have any more addresses, running this query won't hurt.
		$this->dbCmd->Query("UPDATE usershippingaddresses SET IsDefault='Y' WHERE UserID=" . intval($this->userID) . " LIMIT 1");
		
	}
	
	function setDefaultAddress($addressSignature){
		
		if(strlen($addressSignature) != 32)
			throw new Exception("The Address signature is incorrect");
			
		// If the user doesn't have an address matching that signature, then we need to throw an exception.
		$this->dbCmd->Query("SELECT COUNT(*) FROM usershippingaddresses WHERE UserID=" . intval($this->userID) . " AND RequestSignature='".DbCmd::EscapeLikeQuery($addressSignature)."'");
		if($this->dbCmd->GetValue() == 0)
			throw new Exception("You can't set a default address for a user if the address doesn't already exist.");
		
		// First set all of the addresses so they are not the default.
		$this->dbCmd->Query("UPDATE usershippingaddresses SET IsDefault='N' WHERE UserID=" . intval($this->userID));
		
		// Then set the default on the address signature (that we are sure exists)
		$this->dbCmd->Query("UPDATE usershippingaddresses SET IsDefault='Y' WHERE UserID=" . intval($this->userID) . " AND RequestSignature='".DbCmd::EscapeLikeQuery($addressSignature)."'");
	}



}







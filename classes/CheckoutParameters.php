<?

// This is a way to make parameters sticky for entry on the Checkout page.
// We may want a certain shipping choice, coupon code, or other information to be pre-selected
// If nothing has been set ahead of time, then it will just return default values, or fetch info from the DB
// You must make sure that the session has been started before calling these methods.
class CheckoutParameters {

	private $_userIdHasBeenSet;
	private $_shippingAddressObj;
	private $_userControlObj;
	

	function __construct(){
		
		$this->_userIdHasBeenSet = false;
		
	}
	
	function SetUserID($userID){
		$this->_shippingAddressObj = new UserShippingAddresses($userID);
		$dbCmd = new DbCmd();
		$this->_userControlObj = new UserControl($dbCmd);
		$this->_userControlObj->LoadUserByID($userID, false);
		
		$this->_userIdHasBeenSet = true;
	}
	
	function _EnsureUserControlObjectIsSet(){
		if(!$this->_userIdHasBeenSet)
			throw new Exception("The UserControl Object must be set before calling a Get method in CheckoutParameters");
	}


	// -----------   Set Properties ------------------------------------------------------------- //

	function ClearShippingSettings(){
		
		WebUtil::SetSessionVar("Checkout_ShippingMethod", "");
		WebUtil::SetSessionVar("Checkout_S_Name", "");
		WebUtil::SetSessionVar("Checkout_S_Company", "");
		WebUtil::SetSessionVar("Checkout_S_Address", "");
		WebUtil::SetSessionVar("Checkout_S_AddressTwo", "");
		WebUtil::SetSessionVar("Checkout_S_City", "");
		WebUtil::SetSessionVar("Checkout_S_State", "");
		WebUtil::SetSessionVar("Checkout_S_Zip", "");
		WebUtil::SetSessionVar("Checkout_S_Country", "");
		WebUtil::SetSessionVar("Checkout_S_Residential", "R");
		WebUtil::SetSessionVar("Checkout_S_ShippingInstructions", "");
		
	}
	
	function SetShippingInstructions($x){
		WebUtil::SetSessionVar('Checkout_S_ShippingInstructions', $x);
	}
	function SetCouponCode($x){
		WebUtil::SetSessionVar('Checkout_Coupon', WebUtil::FilterData($x, FILTER_SANITIZE_STRING_ONE_LINE));
	}
	function SetShippingChoiceID($ShippingChoiceID){
		WebUtil::SetSessionVar('Checkout_ShippingMethod', $ShippingChoiceID);
	}
	function SetShippingName($x){
		WebUtil::SetSessionVar('Checkout_S_Name', WebUtil::FilterData($x, FILTER_SANITIZE_STRING_ONE_LINE));
	}
	function SetShippingCompany($x){
		WebUtil::SetSessionVar('Checkout_S_Company', WebUtil::FilterData($x, FILTER_SANITIZE_STRING_ONE_LINE));
	}
	function SetShippingAddress($x){
		WebUtil::SetSessionVar('Checkout_S_Address', WebUtil::FilterData($x, FILTER_SANITIZE_STRING_ONE_LINE));
	}
	function SetShippingAddressTwo($x){
		WebUtil::SetSessionVar('Checkout_S_AddressTwo', WebUtil::FilterData($x, FILTER_SANITIZE_STRING_ONE_LINE));
	}
	function SetShippingCity($x){
		WebUtil::SetSessionVar('Checkout_S_City', WebUtil::FilterData($x, FILTER_SANITIZE_STRING_ONE_LINE));
	}
	function SetShippingState($x){
		WebUtil::SetSessionVar('Checkout_S_State', WebUtil::FilterData($x, FILTER_SANITIZE_STRING_ONE_LINE));
	}
	function SetShippingZip($x){
		WebUtil::SetSessionVar('Checkout_S_Zip', WebUtil::FilterData($x, FILTER_SANITIZE_STRING_ONE_LINE));
	}
	function SetShippingCountry($x){
		WebUtil::SetSessionVar('Checkout_S_Country', WebUtil::FilterData($x, FILTER_SANITIZE_STRING_ONE_LINE));
	}
	function SetShippingResidentialFlag($x){
		if(!is_bool($x))
			throw new Exception("Residential flags must be boolean.");
		WebUtil::SetSessionVar('Checkout_S_Residential', ($x ? "R" : "C"));
	}
	function SetBillingName($x){
		WebUtil::SetSessionVar('Checkout_B_Name', WebUtil::FilterData($x, FILTER_SANITIZE_STRING_ONE_LINE));
	}
	function SetBillingCompany($x){
		WebUtil::SetSessionVar('Checkout_B_Company', WebUtil::FilterData($x, FILTER_SANITIZE_STRING_ONE_LINE));
	}
	function SetBillingAddress($x){
		WebUtil::SetSessionVar('Checkout_B_Address', WebUtil::FilterData($x, FILTER_SANITIZE_STRING_ONE_LINE));
	}
	function SetBillingAddressTwo($x){
		WebUtil::SetSessionVar('Checkout_B_AddressTwo', WebUtil::FilterData($x, FILTER_SANITIZE_STRING_ONE_LINE));
	}
	function SetBillingCity($x){
		WebUtil::SetSessionVar('Checkout_B_City', WebUtil::FilterData($x, FILTER_SANITIZE_STRING_ONE_LINE));
	}
	function SetBillingState($x){
		WebUtil::SetSessionVar('Checkout_B_State', WebUtil::FilterData($x, FILTER_SANITIZE_STRING_ONE_LINE));
	}
	function SetBillingZip($x){
		WebUtil::SetSessionVar('Checkout_B_Zip', WebUtil::FilterData($x, FILTER_SANITIZE_STRING_ONE_LINE));
	}
	function SetBillingCountry($x){
		WebUtil::SetSessionVar('Checkout_B_Country', WebUtil::FilterData($x, FILTER_SANITIZE_STRING_ONE_LINE));
	}


	// -----------   Get Properties ------------------------------------------------------------- //
	function GetShippingInstructions(){
		return WebUtil::GetSessionVar('Checkout_S_ShippingInstructions');
	}
	function GetCouponCode(){
		return WebUtil::GetSessionVar('Checkout_Coupon', "");
	}

	function GetShippingChoicesID(){
		return WebUtil::GetSessionVar('Checkout_ShippingMethod', "NULL");
	}
	function GetShippingName(){
		$this->_EnsureUserControlObjectIsSet();

		$addressObj = $this->_shippingAddressObj->getDefaultShippingAddress();
		return WebUtil::GetSessionVar('Checkout_S_Name', $addressObj->getAttention());
	}
	function GetShippingCompany(){
		$this->_EnsureUserControlObjectIsSet();
		
		$addressObj = $this->_shippingAddressObj->getDefaultShippingAddress();
		return WebUtil::GetSessionVar('Checkout_S_Company', $addressObj->getCompanyName());
	}
	function GetShippingAddress(){
		$this->_EnsureUserControlObjectIsSet();

		$addressObj = $this->_shippingAddressObj->getDefaultShippingAddress();
		return WebUtil::GetSessionVar('Checkout_S_Address', $addressObj->getAddressOne());
	}
	function GetShippingAddressTwo(){
		$this->_EnsureUserControlObjectIsSet();
		
		$addressObj = $this->_shippingAddressObj->getDefaultShippingAddress();
		return WebUtil::GetSessionVar('Checkout_S_AddressTwo', $addressObj->getAddressTwo());
	}
	function GetShippingCity(){
		$this->_EnsureUserControlObjectIsSet();
		
		$addressObj = $this->_shippingAddressObj->getDefaultShippingAddress();
		return WebUtil::GetSessionVar('Checkout_S_City', $addressObj->getCity());
	}
	function GetShippingState(){
		$this->_EnsureUserControlObjectIsSet();

		$addressObj = $this->_shippingAddressObj->getDefaultShippingAddress();
		return WebUtil::GetSessionVar('Checkout_S_State', $addressObj->getState());
	}
	function GetShippingZip(){
		$this->_EnsureUserControlObjectIsSet();
		
		$addressObj = $this->_shippingAddressObj->getDefaultShippingAddress();
		return WebUtil::GetSessionVar('Checkout_S_Zip', $addressObj->getZipCode());
	}
	function GetShippingCountry(){
		$this->_EnsureUserControlObjectIsSet();

		$addressObj = $this->_shippingAddressObj->getDefaultShippingAddress();
		return WebUtil::GetSessionVar('Checkout_S_Country', $addressObj->getCountryCode());
	}
	function GetShippingResidentialFlag(){
		$this->_EnsureUserControlObjectIsSet();
	
		$addressObj = $this->_shippingAddressObj->getDefaultShippingAddress();

		$residentialFlag = WebUtil::GetSessionVar('Checkout_S_Residential', null);
		if($residentialFlag == "R" || $residentialFlag == "C"){
			if($residentialFlag == "R")
				return true;
			else 
				return false;
		}
		else{
			return $addressObj->isResidential();
		}

	}

	
	// ------------ Billing uses the User Account Details by default -----------------------
	function GetBillingName(){
		$this->_EnsureUserControlObjectIsSet();
		return WebUtil::GetSessionVar('Checkout_B_Name', $this->_userControlObj->getName());
	}
	function GetBillingCompany(){
		$this->_EnsureUserControlObjectIsSet();
		return WebUtil::GetSessionVar('Checkout_B_Company', $this->_userControlObj->getCompany());
	}
	function GetBillingAddress(){
		$this->_EnsureUserControlObjectIsSet();
		return WebUtil::GetSessionVar('Checkout_B_Address', $this->_userControlObj->getAddress());
	}
	function GetBillingAddressTwo(){
		$this->_EnsureUserControlObjectIsSet();
		return WebUtil::GetSessionVar('Checkout_B_AddressTwo', $this->_userControlObj->getAddressTwo());
	}
	function GetBillingCity(){
		$this->_EnsureUserControlObjectIsSet();
		return WebUtil::GetSessionVar('Checkout_B_City', $this->_userControlObj->getCity());
	}
	function GetBillingState(){
		$this->_EnsureUserControlObjectIsSet();
		return WebUtil::GetSessionVar('Checkout_B_State', $this->_userControlObj->getState());
	}
	function GetBillingZip(){
		$this->_EnsureUserControlObjectIsSet();
		return WebUtil::GetSessionVar('Checkout_B_Zip', $this->_userControlObj->getZip());
	}
	function GetBillingCountry(){
		$this->_EnsureUserControlObjectIsSet();
		return WebUtil::GetSessionVar('Checkout_B_Country', $this->_userControlObj->getCountry());
	}
	
	// Pass in a flag as TRUE if you want it to be ready for output to HTML.
	function getShippingDescription($htmlFlag = false){
	
		$returnStr = "";
		if($this->GetShippingCompany() != "")
			$returnStr .= $this->GetShippingCompany() . "\nAttention: ";
		$returnStr .= $this->GetShippingName() . "\n";
		$returnStr .= $this->GetShippingAddress() . "\n";
		if($this->GetShippingAddressTwo() != "")
			$returnStr .= $this->GetShippingAddressTwo() . "\n";
		$returnStr .= $this->GetShippingCity() . ", " . $this->GetShippingState() . "\n";
		$returnStr .= $this->GetShippingZip();

		if($htmlFlag){
			$returnStr = WebUtil::htmlOutput($returnStr);
			$returnStr = preg_replace("/\n/", "<br>", $returnStr);
		}
		return $returnStr;
	}
	
	
	function setShippingVariablesFromPostVariables(){
	
		$this->SetShippingInstructions(WebUtil::GetInput("shippingInstructions", FILTER_SANITIZE_STRING_MULTI_LINE));
		$this->SetShippingName(WebUtil::GetInput("s_name", FILTER_SANITIZE_STRING_ONE_LINE));
		$this->SetShippingCompany(WebUtil::GetInput("s_company", FILTER_SANITIZE_STRING_ONE_LINE));
		$this->SetShippingAddress(WebUtil::GetInput("s_address", FILTER_SANITIZE_STRING_ONE_LINE));
		$this->SetShippingAddressTwo(WebUtil::GetInput("s_address2", FILTER_SANITIZE_STRING_ONE_LINE));
		$this->SetShippingCity(WebUtil::GetInput("s_city", FILTER_SANITIZE_STRING_ONE_LINE));
		$this->SetShippingState(WebUtil::GetInput("s_state", FILTER_SANITIZE_STRING_ONE_LINE));
		$this->SetShippingZip(WebUtil::GetInput("s_zip", FILTER_SANITIZE_STRING_ONE_LINE));
		$this->SetShippingCountry(WebUtil::GetInput("s_country", FILTER_SANITIZE_STRING_ONE_LINE));
		$this->SetShippingResidentialFlag(WebUtil::GetInput("s_resi", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "Y" ? true : false );
	}
	function setBillingVariablesFromPostVariables(){
		
		$this->SetBillingName(WebUtil::GetInput("b_name", FILTER_SANITIZE_STRING_ONE_LINE));
		$this->SetBillingCompany(WebUtil::GetInput("b_company", FILTER_SANITIZE_STRING_ONE_LINE));
		$this->SetBillingAddress(WebUtil::GetInput("b_address", FILTER_SANITIZE_STRING_ONE_LINE));
		$this->SetBillingAddressTwo(WebUtil::GetInput("b_address2", FILTER_SANITIZE_STRING_ONE_LINE));
		$this->SetBillingCity(WebUtil::GetInput("b_city", FILTER_SANITIZE_STRING_ONE_LINE));
		$this->SetBillingState(WebUtil::GetInput("b_state", FILTER_SANITIZE_STRING_ONE_LINE));
		$this->SetBillingZip(WebUtil::GetInput("b_zip", FILTER_SANITIZE_STRING_ONE_LINE));
		$this->SetBillingCountry(WebUtil::GetInput("b_country", FILTER_SANITIZE_STRING_ONE_LINE));
	}
	
	// Returns a Mailing Address Object with the shipping address inside.
	function getShippingAddressMailingObj(){
	
		$mailingAddressObj = new MailingAddress($this->GetShippingName(), $this->GetBillingCompany(), $this->GetShippingAddress(), $this->GetShippingAddressTwo(), $this->GetShippingCity(), $this->GetShippingState(), $this->GetShippingZip(), $this->GetShippingCountry(), $this->GetShippingResidentialFlag(), "");
		
		if(!$mailingAddressObj->addressInitialized())
			$mailingAddressObj = $this->_shippingAddressObj->getDefaultShippingAddress();
	
		return $mailingAddressObj;
		
	}

}



?>
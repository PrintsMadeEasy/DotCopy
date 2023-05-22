<?

require_once("library/Boot_Session.php");


$t = new Templatex();


$dbCmd = new DbCmd();


// Make sure they are logged in.  If they are not it will redirect them to a Secure Login Page.
// The boolean flag of TRUE in the constructor says that after signing in (or signing up) The transfer URL should start with "https"
$AuthObj = new Authenticate("login_secure");
$UserID = $AuthObj->GetUserID();

$UserControlObj = new UserControl($dbCmd);
$UserControlObj->LoadUserByID($UserID);

$userShippingAddressesObj = new UserShippingAddresses($UserID);

UserControl::updateDateLastUsed($UserID);





$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if($action){
	
	WebUtil::checkFormSecurityCode();
	
	if($action == "addAddress"){

		$attention = WebUtil::GetInput("attention", FILTER_SANITIZE_STRING_ONE_LINE);
		$company = WebUtil::GetInput("company", FILTER_SANITIZE_STRING_ONE_LINE);
		$address = WebUtil::GetInput("address", FILTER_SANITIZE_STRING_ONE_LINE);
		$addressTwo = WebUtil::GetInput("addressTwo", FILTER_SANITIZE_STRING_ONE_LINE);
		$city = WebUtil::GetInput("city", FILTER_SANITIZE_STRING_ONE_LINE);
		$state = WebUtil::GetInput("state", FILTER_SANITIZE_STRING_ONE_LINE);
		$zip = WebUtil::GetInput("zip", FILTER_SANITIZE_STRING_ONE_LINE);
		$country = WebUtil::GetInput("country", FILTER_SANITIZE_STRING_ONE_LINE);
		$residential = WebUtil::GetInput("residential", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
		$phone = WebUtil::GetInput("phone", FILTER_SANITIZE_STRING_ONE_LINE);

		$mailingAddressObj = new MailingAddress($attention, $company, $address, $addressTwo, $city, $state, $zip, $country, ($residential == "C" ? false : true), $phone);
		
		$userShippingAddressesObj->addNewAddress($mailingAddressObj);
		
		
		VisitorPath::addRecord("Shipping Addresses", "Add");
		header("Location: " . WebUtil::FilterURL("shipping_addresses.php?nocache=" . time()));
		exit;
	}
	else if($action == "removeAddress"){
		
		$addressSignature = WebUtil::GetInput("addressSignature", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

		$userShippingAddressesObj->deleteAddress($addressSignature);
		
		VisitorPath::addRecord("Shipping Addresses", "Remove");
		header("Location: " . WebUtil::FilterURL("shipping_addresses.php?nocache=" . time()));
		exit;
	}
	else if($action == "setDefault"){

		$addressSignature = WebUtil::GetInput("addressSignature", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
		
		$userShippingAddressesObj->setDefaultAddress($addressSignature);
		
		VisitorPath::addRecord("Shipping Addresses", "Default");
		header("Location: " . WebUtil::FilterURL("shipping_addresses.php?nocache=" . time()));
		exit;
	}
	else{
		throw new Exception("Illegal action");
	}
}



$t->set_file("origPage", "shipping_addresses-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


// Don't record visitor paths when the page reloads after doing an action.
$nocache = WebUtil::GetInput("nocache", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if(empty($nocache))
	VisitorPath::addRecord("Shipping Addresses");
	
	
// Show the users account address.
$t->set_var("ACCOUNT_NAME", WebUtil::htmlOutput($UserControlObj->getName()));
$t->set_var("ACCOUNT_COMPANY", WebUtil::htmlOutput($UserControlObj->getCompany()));
$t->set_var("ACCOUNT_ADDRESS_ONE", WebUtil::htmlOutput($UserControlObj->getAddress()));
$t->set_var("ACCOUNT_ADDRESS_TWO", WebUtil::htmlOutput($UserControlObj->getAddressTwo()));
$t->set_var("ACCOUNT_CITY", WebUtil::htmlOutput($UserControlObj->getCity()));
$t->set_var("ACCOUNT_STATE", WebUtil::htmlOutput($UserControlObj->getState()));
$t->set_var("ACCOUNT_ZIP", WebUtil::htmlOutput($UserControlObj->getZip()));
$t->set_var("ACCOUNT_COUNTRY", WebUtil::htmlOutput($UserControlObj->getCountry()));
$t->set_var("ACCOUNT_PHONE", WebUtil::htmlOutput($UserControlObj->getPhone()));
	


$t->set_block("origPage","AddressRowBL","AddressRowBLout");

$userShippingAddressArr = $userShippingAddressesObj->getAllUserAddresses();

foreach($userShippingAddressArr as $thisShippingAddressObj){

	$t->set_var("NAME", WebUtil::htmlOutput($thisShippingAddressObj->getAttention()));
	$t->set_var("COMPANY", WebUtil::htmlOutput($thisShippingAddressObj->getCompanyName()));
	$t->set_var("ADDRESS_ONE", WebUtil::htmlOutput($thisShippingAddressObj->getAddressOne()));
	$t->set_var("ADDRESS_TWO", WebUtil::htmlOutput($thisShippingAddressObj->getAddressTwo()));
	$t->set_var("CITY", WebUtil::htmlOutput($thisShippingAddressObj->getCity()));
	$t->set_var("STATE", WebUtil::htmlOutput($thisShippingAddressObj->getState()));
	$t->set_var("ZIP", WebUtil::htmlOutput($thisShippingAddressObj->getZipCode()));
	$t->set_var("COUNTRY", WebUtil::htmlOutput($thisShippingAddressObj->getCountryCode()));
	$t->set_var("RESIDENTIAL", $thisShippingAddressObj->isResidential() ? "Y" : "N");
	$t->set_var("ADDRESS_SIGNATURE", $thisShippingAddressObj->getSignatureOfFullAddress());
	$t->set_var("PHONE", WebUtil::htmlOutput($thisShippingAddressObj->getPhoneNumber()));

	$t->set_var("DEFAULT", $userShippingAddressesObj->checkIfAddressIsDefault($thisShippingAddressObj->getSignatureOfFullAddress()) ? "Y" : "N");
	
	$t->parse("AddressRowBLout","AddressRowBL",true);
}

if(empty($userShippingAddressArr)){
	$t->discard_block("origPage", "ShippingAddressesBL");
}
else{
	$t->discard_block("origPage", "EmptyAddressesBL");
}
	


$t->pparse("OUT","origPage");




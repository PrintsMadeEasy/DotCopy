<?

require_once("library/Boot_Session.php");



$user_sessionID =  WebUtil::GetSessionID();

WebUtil::RunInSecureModeHTTPS();

$t = new Templatex();


$dbCmd = new DbCmd();


// Make sure they are logged in.  If they are not it will redirect them to a Secure Login Page.
// The boolean flag of TRUE in the constructor says that after signing in (or signing up) The transfer URL should start with "https"
$AuthObj = new Authenticate("login_secure");
$UserID = $AuthObj->GetUserID();


// Make sure that the shopping cart is not empty.  Maybe they hit their back button after placing an order.
$ProductIDinShoppingCartArr = ProjectGroup::GetProductIDlistFromGroup($dbCmd, $user_sessionID, "shoppingcart");
if(sizeof($ProductIDinShoppingCartArr) == 0)
	WebUtil::PrintError("Your Shopping Cart is empty. Your order may have already gone through. Please visit your Order History within Customer Service to check on the status of your order(s)." );


$UserControlObj = new UserControl($dbCmd);
$UserControlObj->LoadUserByID($UserID, true);

$checkoutParamsObj = new CheckoutParameters();
$checkoutParamsObj->SetUserID($UserID);

$shippingMethodObj = new ShippingMethods();
$couponObj = new Coupons($dbCmd);


$addressSuggestionsArr = array();


if(WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES)){
	if(WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "saveShippingAddressAndCouponCode"){

		$couponCodeFromForm = WebUtil::GetInput("couponCode", FILTER_SANITIZE_STRING_ONE_LINE);
		
		
		VisitorPath::addRecord("Confirm Shipping Address");
		
		if($UserControlObj->getAddressLocked() == "Y"){
			
			if(WebUtil::GetInput("s_address", FILTER_SANITIZE_STRING_ONE_LINE) != $checkoutParamsObj->GetShippingAddress()
				|| WebUtil::GetInput("s_address2", FILTER_SANITIZE_STRING_ONE_LINE) != $checkoutParamsObj->GetShippingAddressTwo()
				|| WebUtil::GetInput("s_city", FILTER_SANITIZE_STRING_ONE_LINE) != $checkoutParamsObj->GetShippingCity()
				|| WebUtil::GetInput("s_state", FILTER_SANITIZE_STRING_ONE_LINE) != $checkoutParamsObj->GetShippingState()
				|| WebUtil::GetInput("s_zip", FILTER_SANITIZE_STRING_ONE_LINE) != $checkoutParamsObj->GetShippingZip()
			){
				WebUtil::PrintError("You can not change the shipping address because your address has been locked.", true);
			}

			
		}
		
		// Record all of the Post Data after the user submits the form into the Session Variables.
		$checkoutParamsObj->setShippingVariablesFromPostVariables();
		$checkoutParamsObj->SetCouponCode($couponCodeFromForm);
		
		
		if(!empty($couponCodeFromForm))
			$validateCouponErrorMessage = $couponObj->CheckIfCouponIsOKtoUse($couponCodeFromForm, $UserID, "shoppingcart", $user_sessionID);
		else 
			$validateCouponErrorMessage = null;
		
		// We are saving both the Shipping Address and the Coupon.
		// If both of them are valid, then we can redirect to the next stage of the checkout process.
		$shippingAddressObj = $checkoutParamsObj->getShippingAddressMailingObj();
	
	
		$AddressVerificationErrorFlag = true;
		
		// Catch any communication errors with the server.
		try{
			$AddressVerificationErrorFlag = !$shippingMethodObj->isAddressValid($shippingAddressObj);
		}
		catch (ExceptionCommunicationError $e){
			WebUtil::PrintError($e->getMessage(), true);
		}
		
		
		// Cache Results of Error so that this script doesn't have to call the UPS API twice on the same script.
		if($AddressVerificationErrorFlag)
			$addressSuggestionsArr = $shippingMethodObj->getAddressSuggestions();
		
		
		// If the Shipping Address is OK.... and the coupon is Blank (or the coupon is valid)
		// ... then we can redirect the user to the next step.
		if(!$AddressVerificationErrorFlag && empty($validateCouponErrorMessage)){
			
			$templateIDforCheckout = WebUtil::GetInput("templateIdForCheckout", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
			
			if(!empty($templateIDforCheckout)){
				header("Location: " . WebUtil::FilterURL("./checkout.php?TemplateID=$templateIDforCheckout&nocache=" . time()));
			}
			else{
				header("Location: " . WebUtil::FilterURL("./checkout.php?nocache=" . time()));
			}
			
			
			exit;
		}
		
		
	}
	else{
		throw new Exception("Illegal action");
	}
}



$t->set_file("origPage", "checkout_shipping-template.html");



if($checkoutParamsObj->GetShippingCountry() == "US"){
	
	// Bad things can happen with the API to UPS if the Zip code is not formated correctly
	// In the United States we want to make sure that there are exactly 5 digits for the ZipCode
	$oldZip = $checkoutParamsObj->GetShippingZip();
	
	$checkoutParamsObj->SetShippingZip(WebUtil::FilterUnitedStatesZipCode($oldZip));
	

	// Puerto Rico (and others will suceed as an Address Verification with UPS... but will fail on the TimeInTransit API
	// We can't let it come through as a State Abbrieviation
	if(strtoupper($checkoutParamsObj->GetShippingState()) == "PR" || strtoupper($checkoutParamsObj->GetShippingState()) == "AE"){
		$t->set_var("INTL_STATE_CODE", strtoupper($checkoutParamsObj->GetShippingState())); 
		$checkoutParamsObj->SetShippingState("");
	}
	else{
		$t->discard_block("origPage", "IntlStateCodeMessageBL");
	}	
}
else{
	$t->discard_block("origPage", "IntlStateCodeMessageBL");
}




// Only show them the Postage Mailing Message when they have at least one product in their shopping cart.
if(!ShoppingCart::checkIfProductInShoppingCartHasMailingServices())
	$t->discard_block("origPage", "PostageMessage");



// If the Address is locked then disable the shipping and billing address
if($UserControlObj->getAddressLocked() == "Y"){
	$t->set_var("DISABLED_SHIPPING", "true"); 
}
else{
	$t->discard_block("origPage", "ShippingAddressLockedBL");
	$t->set_var("DISABLED_SHIPPING", "false"); 
}



$shippingAddressObj = $checkoutParamsObj->getShippingAddressMailingObj();


// Catch any communication errors with the server.
// We may have already cached an Address Validation Error if the Form Action failed to change the address.
if(empty($addressSuggestionsArr)){

	try{
		$AddressVerificationErrorFlag = !$shippingMethodObj->isAddressValid($shippingAddressObj);
	}
	catch (ExceptionCommunicationError $e){
		WebUtil::PrintError($e->getMessage(), true);
	}
	
	// returns an array of MailingAddressSuggestion objects.
	if($AddressVerificationErrorFlag)
		$addressSuggestionsArr = $shippingMethodObj->getAddressSuggestions();
}
else{
	
	// There is an Error if the Address Suggestions where already populated.
	$AddressVerificationErrorFlag = true;
}



// If there was an Address Error, then show the customer a list of suggestions.
if($AddressVerificationErrorFlag){
	
	
	$t->allowVariableToContainBrackets("UPS_SUGGESTIONS_1");
	$t->allowVariableToContainBrackets("UPS_SUGGESTIONS_2");
	
	
	// There may have been an address validation error but that doesn't necessarily mean that we got back any suggestions
	if(empty($addressSuggestionsArr)){
		
		VisitorPath::addRecord("Shipping Error", "Address Verification Error: No Suggestions");

		$t->set_var(array("UPS_SUGGESTIONS_1"=>"<font class='SmallBody'>Sorry, no suggestions were found.<br>If you are sure that your shipping address is correct, please contact customer service.</font>"));
		$t->set_var(array("UPS_SUGGESTIONS_2"=>""));
	}
	else{
		
		VisitorPath::addRecord("Shipping Error", ("Address Verification: " . sizeof($addressSuggestionsArr) . " suggestions from UPS."));

		// This array will contain a list of alternate suggestions.
		$suggestionHTML = "";

		// We are going to split the reults into 2 columns
		$column_1 = '';
		$column_2 = '';
		$ColumnFlag = true;

		// Loop through all of UPS's suggestions
		foreach($addressSuggestionsArr as $thisMailingAddressSuggestion){
			
			$tempStr = "<font class='addressErrorName'>City: </font><font class='addressErrorValue'>" . $thisMailingAddressSuggestion->city . "</font><br>";
			$tempStr .= "<font class='addressErrorName'>State: </font><font class='addressErrorValue'>" . $thisMailingAddressSuggestion->state . "</font><br>";

			if($thisMailingAddressSuggestion->postalLow == $thisMailingAddressSuggestion->postalHigh)
				$tempStr .= "<font class='addressErrorName'>Zip: </font><font class='addressErrorValue'>" . $thisMailingAddressSuggestion->postalHigh;
			else
				$tempStr .= "<font class='addressErrorName'>Zip: </font><font class='addressErrorValue'>(" . $thisMailingAddressSuggestion->postalLow . " - " . $thisMailingAddressSuggestion->postalHigh. ")</font>";


			if($ColumnFlag){
				$column_1 .= $tempStr . "<hr class='addressErrorLineSeparator' />";
				$ColumnFlag = false;
			}
			else{
				$column_2 .= $tempStr . "<hr class='addressErrorLineSeparator' />";
				$ColumnFlag = true;
			}
		}

		$t->set_var(array("UPS_SUGGESTIONS_1"=>$column_1));
		$t->set_var(array("UPS_SUGGESTIONS_2"=>$column_2));
	}
	
}
else{
	
	// Since the shipping address is valid, erase the block of HTML for showing Address Validation results
	$t->discard_block("origPage", "AddressValidationErrorBL");
}


// Erase the block for the international shipping address error since they are shipping inside of the U.S.
if($checkoutParamsObj->GetShippingCountry() == "US" || !$AddressVerificationErrorFlag){
	$t->discard_block("origPage","hideInternationalShippingAddressError");
}

	


$t->set_var("S_NAME", WebUtil::htmlOutput($checkoutParamsObj->GetShippingName()));
$t->set_var("S_COMPANY", WebUtil::htmlOutput($checkoutParamsObj->GetShippingCompany()));
$t->set_var("S_ADDRESS", WebUtil::htmlOutput($checkoutParamsObj->GetShippingAddress()));
$t->set_var("S_ADDRESS_TWO", WebUtil::htmlOutput($checkoutParamsObj->GetShippingAddressTwo()));
$t->set_var("S_CITY", WebUtil::htmlOutput($checkoutParamsObj->GetShippingCity()));
$t->set_var("S_STATE", WebUtil::htmlOutput($checkoutParamsObj->GetShippingState()));
$t->set_var("S_ZIP", WebUtil::htmlOutput($checkoutParamsObj->GetShippingZip()));
$t->set_var("S_COUNTRY", WebUtil::htmlOutput($checkoutParamsObj->GetShippingCountry()));

$t->set_var("COUNTRIES_DROPDOWN", Widgets::buildSelect(Status::GetUPScountryCodesArr(), array($checkoutParamsObj->GetShippingCountry())));
$t->allowVariableToContainBrackets("COUNTRIES_DROPDOWN");


if($checkoutParamsObj->GetShippingResidentialFlag())
	$t->set_var(array("S_RESI_YES"=>"checked", "S_RESI_NO"=>""));
else 
	$t->set_var(array("S_RESI_YES"=>"", "S_RESI_NO"=>"checked"));

	
$couponCode = $checkoutParamsObj->GetCouponCode();

// If there is not already a Checkout Coupon to be automatically inserted...
// See if there is a coupon to be automatically inserted for the Sales Rep
if(empty($couponCode))
	$couponCode = WebUtil::GetSessionVar("SalesRepCouponSession", WebUtil::GetCookie("SalesRepCouponCookie"));


$t->set_var("COUPON_CODE", WebUtil::htmlOutput($couponCode));


if(!empty($couponCode)){
	$validateCouponErrorMessage = $couponObj->CheckIfCouponIsOKtoUse($couponCode, $UserID, "shoppingcart", $user_sessionID);
	
	if(!empty($validateCouponErrorMessage)){
		VisitorPath::addRecord("Coupon Validation Failure", $couponCode);
		$t->set_var("COUPON_CODE_ERROR_MESSAGE", WebUtil::htmlOutput($validateCouponErrorMessage));
	}
	else{
		VisitorPath::addRecord("Coupon Validation Success", $couponCode);
		$t->discard_block("origPage", "CouponCodeErrorBL");
	}
	
}
else{
	
	$t->discard_block("origPage", "CouponCodeErrorBL");
}


$shippingInstructions = $checkoutParamsObj->GetShippingInstructions();
$t->set_var("SHIPPING_INSTRUCTIONS", WebUtil::htmlOutput($shippingInstructions));



// Record Parallel Arrays for Javascript "Saved Shipping Addresses"
$shipping_Names = array();
$shipping_Companies = array();
$shipping_AddressOnes = array();
$shipping_AddressTwos = array();
$shipping_Cities = array();
$shipping_States = array();
$shipping_Zips = array();
$shipping_Countries = array();
$shipping_Residentials = array();
$shipping_Signatures = array();
$shipping_Phones = array();

$userShippingAddressesObj = new UserShippingAddresses($UserID);
$userShippingAddressArr = $userShippingAddressesObj->getAllUserAddresses();

foreach($userShippingAddressArr as $addressObj){
	$shipping_Names[] = WebUtil::htmlOutput($addressObj->getAttention());
	$shipping_Companies[] = WebUtil::htmlOutput($addressObj->getCompanyName());
	$shipping_AddressOnes[] = WebUtil::htmlOutput($addressObj->getAddressOne());
	$shipping_AddressTwos[] = WebUtil::htmlOutput($addressObj->getAddressTwo());
	$shipping_Cities[] = WebUtil::htmlOutput($addressObj->getCity());
	$shipping_States[] = WebUtil::htmlOutput($addressObj->getState());
	$shipping_Zips[] = WebUtil::htmlOutput($addressObj->getZipCode());
	$shipping_Countries[] = WebUtil::htmlOutput($addressObj->getCountryCode());
	$shipping_Residentials[] = ($addressObj->isResidential() ? "Y" : "N");
	$shipping_Signatures[] = WebUtil::htmlOutput($addressObj->getSignatureOfFullAddress());
	$shipping_Phones[] = WebUtil::htmlOutput($addressObj->getPhoneNumber());
}


$t->set_var("SHIPPING_NAMES_JSON", json_encode($shipping_Names));
$t->set_var("SHIPPING_COMPANIES_JSON", json_encode($shipping_Companies));
$t->set_var("SHIPPING_ADDRESS_JSON", json_encode($shipping_AddressOnes));
$t->set_var("SHIPPING_ADDRESS2_JSON", json_encode($shipping_AddressTwos));
$t->set_var("SHIPPING_CITIES_JSON", json_encode($shipping_Cities));
$t->set_var("SHIPPING_STATES_JSON", json_encode($shipping_States));
$t->set_var("SHIPPING_ZIPS_JSON", json_encode($shipping_Zips));
$t->set_var("SHIPPING_COUNTRIES_JSON", json_encode($shipping_Countries));
$t->set_var("SHIPPING_RESIDENTIAL_JSON", json_encode($shipping_Residentials));
$t->set_var("SHIPPING_SIGNATURES_JSON", json_encode($shipping_Signatures));
$t->set_var("SHIPPING_PHONES_JSON", json_encode($shipping_Phones));



VisitorPath::addRecord("Checkout Shipping Address");


$t->pparse("OUT","origPage");



?>
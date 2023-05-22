<?

require_once("library/Boot_Session.php");



$user_sessionID =  WebUtil::GetSessionID();

WebUtil::RunInSecureModeHTTPS();

$t = new Templatex();


$dbCmd = new DbCmd();

$UserControlObj = new UserControl($dbCmd);


// The weight could take a while to calculate on a mass import
set_time_limit(300);


// Make sure they are logged in.  If they are not it will redirect them to a Secure Login Page.
// The boolean flag of TRUE in the constructor says that after signing in (or signing up) The transfer URL should start with "https"
$AuthObj = new Authenticate("login_secure");
$UserID = $AuthObj->GetUserID();

$UserControlObj->LoadUserByID($UserID);

$PaymentInvoiceObj = new PaymentInvoice();



UserControl::updateDateLastUsed($UserID);


// Make sure that the shopping cart is not empty.  Maybe they hit their back button after placing an order.
$ProductIDinShoppingCartArr = ProjectGroup::GetProductIDlistFromGroup($dbCmd, $user_sessionID, "shoppingcart");
if(sizeof($ProductIDinShoppingCartArr) == 0)
	WebUtil::PrintError("Your Shopping Cart is empty. Your order may have already gone through. Please visit your Order History within Customer Service to check on the status of your order(s)." );




$checkoutParamsObj = new CheckoutParameters();
$checkoutParamsObj->SetUserID($UserID);

$shippingMethodObj = new ShippingMethods();


if(WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES)){
	if(WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "changeshippingaddress"){

		VisitorPath::addRecord("Change Shipping Address");
		
		// Record the new Checkout Details into the users session.
		// They must have clicked on the Change Shipping Address Button.
		$rememberShippingChoiceID = WebUtil::GetInput("shippingChoiceID", FILTER_SANITIZE_INT);
		
		// If the shipping method does not come in the URL then it could indicate a problem with Address Matching
		// in this case just use the shipping method in our last Session Save.
		if(!empty($rememberShippingChoiceID))
			$checkoutParamsObj->SetShippingChoiceID($rememberShippingChoiceID);

			
		// Record all of the Post Data after the user submits the form into the Session Variables.
		$checkoutParamsObj->setShippingVariablesFromPostVariables();
		$checkoutParamsObj->setBillingVariablesFromPostVariables();

		header("Location: " . WebUtil::FilterURL("./checkout.php?nocache=" . time()));
		exit;
	}
	else
		throw new Exception("Illegal action");
}



$t->set_file("origPage", "checkout-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

if($checkoutParamsObj->GetShippingCountry() == "US"){

	// Bad things can happen with the API to UPS if the Zip code is not formated correctly
	// In the United States we want to make sure that there are exactly 5 digits for the ZipCode
	$oldZip = $checkoutParamsObj->GetShippingZip();
	$checkoutParamsObj->SetShippingZip(WebUtil::FilterUnitedStatesZipCode($oldZip));
	
	// Puerto Rico (and others will suceed as an Address Verification with UPS... but will fail on the TimeInTransit API
	// We can't let it come through as a State Abbrieviation
	if(strtoupper($checkoutParamsObj->GetShippingState()) == "PR" || strtoupper($checkoutParamsObj->GetShippingState()) == "AE"){
		VisitorPath::addRecord("Shipping Error", ("State Code: " . strtoupper($checkoutParamsObj->GetShippingState())));
		$t->set_var("INTL_STATE_CODE", strtoupper($checkoutParamsObj->GetShippingState())); 
		$checkoutParamsObj->SetShippingState("");
	}
	else{
		$t->discard_block("origPage", "IntlStateCodeMessageBL");
	}	
}
else{
	VisitorPath::addRecord("Shipping Error", "International");
	$t->discard_block("origPage", "IntlStateCodeMessageBL");
}




// Add 10 mintues to give them a little buffer room to fill in the form, etc.
$currentTimeStamp = (time() + 600);


// Check to make sure that the BillingStatus is in good standing for this cutomer
if($UserControlObj->getBillingStatus() != "G"){
	WebUtil::PrintError("Your billing status has a problem.  Please contact customer service.", true);
	exit;
}






if(ShoppingCart::checkIfProductInShoppingCartHasMailingServices()){
	$t->set_var("SOME_PRODUCTS_MAILED_FLAG", "true"); 
}
else{
	// Only show them the Postage Mailing Message when they have at least one product in their shopping cart.
	$t->discard_block("origPage", "PostageMessage");
	$t->set_var("SOME_PRODUCTS_MAILED_FLAG", "false"); 
}

	
	
if(ShoppingCart::checkIfProductInShoppingCartWillBeShipped()){
	$t->set_var("SOME_PRODUCTS_SHIPPED_FLAG", "true"); 
}
else{
	$t->set_var("SOME_PRODUCTS_SHIPPED_FLAG", "false"); 
}

	
	


// If the Address is locked then disable the shipping and billing address
if($UserControlObj->getAddressLocked() == "Y"){
	$t->set_var("DISABLED_SHIPPING", "true"); 
	$t->set_var("DISABLED_BILLING", "true"); 
}
else{
	$t->discard_block("origPage", "ShipAndBillAddressLockedBL");
	$t->set_var("DISABLED_SHIPPING", "false"); 
	$t->set_var("DISABLED_BILLING", "false"); 
}


// Show them the credit card entry or tell them that corporate invoicing has been enabled.
$checkoutType = WebUtil::GetInput("checkoutType", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

if($checkoutType == "paypal"){
	
	$paypalToken = WebUtil::GetSessionVar("PaypalToken");
	$paypalPayerId = WebUtil::GetSessionVar("PaypalPayerID");
		
	
	if(empty($paypalToken) || empty($paypalPayerId)) {
		throw new Exception("Empty Paypal Token or Payer ID");
	}
	
	$t->set_var("PAYMENT_TYPE", "P");
	
	// Make sure that Javascript doesn't attempt to validate the credit card.
	$t->set_var("BILLING_CREDITCARD_FLAG", "false"); 

	$t->discard_block("origPage", "CreditCardEntryBL");
	$t->discard_block("origPage", "PositiveBalanceAvailableBL");
	$t->discard_block("origPage", "InvoicePaymentBL");

}
else if($UserControlObj->getBillingType() == "C"){
	$t->set_var("BILLING_CREDITCARD_FLAG", "false"); 
	$t->discard_block("origPage", "CreditCardEntryBL");
	$t->discard_block("origPage", "PositiveBalanceAvailableBL");
	$t->discard_block("origPage", "PaypalPaymentBL");
	
	$t->set_var("PAYMENT_TYPE", "C");
	
	// Get the Credit Usage and statment closing day to show to the user.
	$PaymentInvoiceObj->LoadCustomerByID($UserID);	
	$CreditUsageLeft = $UserControlObj->getCreditLimit() - $PaymentInvoiceObj->GetCurrentCreditUsage();
	$t->set_var("CREDIT_USAGE", Widgets::GetPriceFormat($CreditUsageLeft)); 
	$t->set_var("STATEMENT_CLOSING_DAY", date("jS", mktime(3, 3, 3, 3, $PaymentInvoiceObj->GetStatementClosingDay() , 2005))); 
}
else if($UserControlObj->getBillingType() == "N"){
	
	$t->set_var("PAYMENT_TYPE", "N");

	// Find out if the customer has a positive credit with us... even though they don't have corporate billing enabled.
	$UseCreditCardInstead = WebUtil::GetInput("UseCreditCardInstead", FILTER_SANITIZE_STRING_ONE_LINE);

	$PaymentInvoiceObj->LoadCustomerByID($UserID);		
	$CreditUsageByCustomer = $PaymentInvoiceObj->GetCurrentCreditUsage();

	// A negative creditUsage means a positive balance.
	if($CreditUsageByCustomer < 0 && empty($UseCreditCardInstead)){
	
		$t->set_var("POSITIVE_CREDIT_AMOUNT", number_format(abs($CreditUsageByCustomer), 2));
		$t->set_var("BILLING_CREDITCARD_FLAG", "false");
		$t->discard_block("origPage", "CreditCardEntryBL");
	
	}
	else{
		$t->set_var("BILLING_CREDITCARD_FLAG", "true"); 
		$t->discard_block("origPage", "PositiveBalanceAvailableBL");
	}

	$t->discard_block("origPage", "InvoicePaymentBL");
	$t->discard_block("origPage", "PaypalPaymentBL");
}
else{
	throw new Exception("Illegal billing type.  Please report this error to customer service.");
}


$shippingAddressObj = $checkoutParamsObj->getShippingAddressMailingObj();


// Catch any communication errors with the server.
try{
	$AddressVerificationErrorFlag = !$shippingMethodObj->isAddressValid($shippingAddressObj);
}
catch (ExceptionCommunicationError $e){
	WebUtil::PrintError($e->getMessage(), true);
}


// If there was an Address Error, then show the customer a list of suggestions.
if($AddressVerificationErrorFlag){
	
	// returns an array of MailingAddressSuggestion objects.
	$addressSuggestionsArr = $shippingMethodObj->getAddressSuggestions();
	

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
		$column_1 = '<img src="./images/transparent.gif" border="0" width="1" height="5"><br>';
		$column_2 = '<img src="./images/transparent.gif" border="0" width="1" height="5"><br>';
		$ColumnFlag = true;

		// Loop through all of UPS's suggestions
		foreach($addressSuggestionsArr as $thisMailingAddressSuggestion){
			

			$tempStr = "<a class='UpsSuggestion' href=\"javascript:changeUpsSuggestion('".addslashes($thisMailingAddressSuggestion->city )."', '".addslashes($thisMailingAddressSuggestion->state )."', '".addslashes($thisMailingAddressSuggestion->postalHigh )."');\">";
			$tempStr .= "<span class='UpsItemTitle'>City:</span>&nbsp;&nbsp;<span class='UpsData'>" . $thisMailingAddressSuggestion->city . "</span><br>";
			$tempStr .= "<span class='UpsItemTitle'>State:</span>&nbsp;&nbsp;<span class='UpsData'>" . $thisMailingAddressSuggestion->state . "</span><br>";

			if($thisMailingAddressSuggestion->postalLow == $thisMailingAddressSuggestion->postalHigh)
				$tempStr .= "<span class='UpsItemTitle'>Zip:</span>&nbsp;&nbsp;<span class='UpsData'>" . $thisMailingAddressSuggestion->postalHigh . "</span>";
			else
				$tempStr .= "<span class='UpsItemTitle'>Zip:</span>&nbsp;&nbsp;(<span class='UpsData'>" . $thisMailingAddressSuggestion->postalLow . "</span> - <span class='UpsData'>" . $thisMailingAddressSuggestion->postalHigh. "</span>)";

			$tempStr .= "</a>";

			if($ColumnFlag){
				$column_1 .= $tempStr . "<br><img src='./images/line-soft-dotted-blue.gif' width='162' height='9'><br>";
				$ColumnFlag = false;
			}
			else{
				$column_2 .= $tempStr . "<br><img src='./images/line-soft-dotted-blue.gif' width='162' height='9'><br>";
				$ColumnFlag = true;
			}
		}

		$t->set_var(array("UPS_SUGGESTIONS_1"=>$column_1));
		$t->set_var(array("UPS_SUGGESTIONS_2"=>$column_2));
	}
	
	
	// Since the Address is not valid, don't let the user select Shipping Choices
	$t->discard_block("origPage", "ShippingChoicesBL");
}
else{
	
	// Since the shipping address is valid, erase the block of HTML for showing Address Validation results
	$t->discard_block("origPage","hideAddressValidationBL");
}


// Erase the block for the international shipping address error since they are shipping inside of the U.S.
if($checkoutParamsObj->GetShippingCountry() == "US" || !$AddressVerificationErrorFlag){
	
	$t->discard_block("origPage","hideInternationalShippingAddressError");
}

	




// Look for any Error on Variable Data Projects.  Don't let them place an order with errors.
$dbCmd->Query("SELECT COUNT(*) FROM shoppingcart INNER JOIN projectssession ON projectssession.ID = shoppingcart.ProjectRecord 
		WHERE shoppingcart.SID='$user_sessionID' AND shoppingcart.DomainID=".Domain::oneDomain()." 
		AND (projectssession.VariableDataStatus = 'D' || projectssession.VariableDataStatus = 'A' || projectssession.VariableDataStatus = 'L' || projectssession.VariableDataStatus = 'I')");

$variableDataErrorCount = $dbCmd->GetValue();
if($variableDataErrorCount){
	WebUtil::PrintError("You have Project(s) in your Shopping Cart with a Variable Data Error. Please fix all errors before continuing. If you need assitance, please Save your project(s) and contact Customer Service" , true);
}


$loyaltyObj = new LoyaltyProgram(Domain::getDomainIDfromURL());
$totalLoyaltyShippingDiscounts = 0;
$loyaltyShippingDiscArr = array();

$shippingChoicesObj = new ShippingChoices();
$shippingPricesObj = new ShippingPrices();

$eventSchedulerObj = new EventScheduler();
$eventObj = new Event();

// Build a list of shipping methods.... only if we don't have an address error
if(!$AddressVerificationErrorFlag){


	$t->set_block("origPage","ShippingDisplayBL","ShippingDisplayBLout");

	
	// Find out if at least one of the Products has Saturday Shipping as an option.
	// If so, then we want all products to have saturday delivery options.
	$saturdayDeliveryFlag = TimeEstimate::isSaturdayDeliveryPossibleForAnyInProductList($ProductIDinShoppingCartArr, $currentTimeStamp);

	
	$product_counter = 0;  //Product counter needed by Javascript

	$domainAddressObj = new DomainAddresses(Domain::oneDomain());
	$shipFromAddressObj = $domainAddressObj->getDefaultProductionFacilityAddressObj();
	$shipToAddressObj = $checkoutParamsObj->getShippingAddressMailingObj();
	
	
	// There could be a communication error trying to get the Time/Tranist details from the server when the Shipping Choices tries to figure out what methods are available to the destination.
	try{
		$shippingPricesObj->setShippingAddress($shipFromAddressObj, $shipToAddressObj);
		
		$shippingChoicesArr = array_keys($shippingChoicesObj->getAvailableShippingChoices($shipFromAddressObj, $shipToAddressObj));
	}
	catch (ExceptionCommunicationError $e){
		WebUtil::PrintError($e->getMessage(), true);
	}
	catch (ExceptionInvalidShippingAddress $e ){
		WebUtil::WebmasterError("Error with UPS Time in Transit Server.  Address Verification passed but Address Verification failed inside of the TimeInTransit API failed: " . $UPStimeTransitResponseObj->GetErrorDescription() . "---  City: " . $checkoutParamsObj->GetShippingCity()  . " State: " . $checkoutParamsObj->GetShippingState() . " Zip: " . $checkoutParamsObj->GetShippingZip() . " UserID: " . $UserID . "\n\n\n");
		WebUtil::PrintError("An error occurred with your shipping address. It is not available to any of the destinations that we ship to.  Please visit the Shopping Cart, save your work, and ask Customer Service for assistance.", true);
	}
	
	
	if(empty($shippingChoicesArr)){
		$checkoutParamsObj->ClearShippingSettings();
		WebUtil::WebmasterError("No shipping choices were available on the checkout screen: " . $shipToAddressObj->toString());
		WebUtil::PrintError("We don't have any shipping methods available to your Shipping Address, which defaults to the address saved within your account settings.  If you are sure that you entered the address correctly, please save your project(s) within your shopping cart and ask Customer Service for assistance.", true);
	}
	
	
	
	
	foreach($ProductIDinShoppingCartArr as $thisProductID){
		
		$timeEstObj = new TimeEstimate($thisProductID);
		
		$productObj = new Product($dbCmd, $thisProductID);
		
		if($productObj->getDomainID() != $UserControlObj->getDomainID())
			WebUtil::PrintError("You are signed into an account from another domain. If you want to place an order at this domain you must create an account here.", TRUE);

		// Get a new project info Object based on a product ID
		// We need this to give us a description of the product being shipped
		$projectInfoObj = new ProjectInfo($dbCmd, $thisProductID);

		$quantityOfProduct = ProjectGroup::GetDetailsOfProductInGroup($dbCmd, "quantity", $user_sessionID, $thisProductID, "shoppingcart");
		$weightOfProduct = ProjectGroup::GetDetailsOfProductInGroup($dbCmd, "CustomerWeight", $user_sessionID, $thisProductID, "shoppingcart");

		// Add to our total Loyalty shippping savings (only if the user has enrolled).
		if($UserControlObj->getLoyaltyProgram() == "Y"){
			
			// Don't add to the shipping discounts for products with mailing services.
			if(!$productObj->hasMailingService())
				$totalLoyaltyShippingDiscounts += $loyaltyObj->getLoyaltyDiscountShipping($weightOfProduct);
			
			// Add to an array, so that we can show an itemized list of shipping savings per product.
			$loyaltyShippingDiscArr[] = $loyaltyObj->getLoyaltyDiscountShipping($weightOfProduct);
		}
		
		// Create hidden inputs that will be used to hold the price totals and arrival dates of each product/shipping combination.  Javascript can access them to calculate shipping totols
		$HiddenInputHTML = "";
		foreach($shippingChoicesArr as $thisShippingChoiceID){

			// If none of the Products will be able to ship on Saturday... then skip that Shipping Choice.
			if($shippingChoicesObj->checkIfShippingChoiceHasSaturdayDelivery($thisShippingChoiceID) && !$saturdayDeliveryFlag)
				continue;

				
			// Get the shipping cost for each method (for all projects having the same product ID).
			$shippingPriceForProduct = $shippingPricesObj->getShippingPriceForCustomer($thisProductID, $thisShippingChoiceID, $weightOfProduct);
			
			$HiddenInputHTML .= '<input type="hidden" name="ship_' . $thisShippingChoiceID . '_' . $product_counter . '" value="' . WebUtil::htmlOutput($shippingPriceForProduct) . '">' . "\n";

			//Show what the estimates arival times for each product/shipping combo
			$timeEstObj->setShippingChoiceID($thisShippingChoiceID);
			$arrivalTimeStamp = $timeEstObj->getArrivalTimeStamp($currentTimeStamp, $shipFromAddressObj, $checkoutParamsObj->getShippingAddressMailingObj());
			$arrivalDate = TimeEstimate::formatTimeStamp($arrivalTimeStamp);

			$HiddenInputHTML .= '<input type="hidden" name="arrival_' . $thisShippingChoiceID . '_' . $product_counter . '" value="' . WebUtil::htmlOutput($arrivalDate) . '">' . "\n";
		}

		$t->set_var("HIDDEN_SHIPPING_FIELDS", $HiddenInputHTML);
		$t->allowVariableToContainBrackets("HIDDEN_SHIPPING_FIELDS");

		$t->set_var(array(
			"DESCRIPTION"=>$projectInfoObj->getOrderDescription($quantityOfProduct), 
			"WEIGHT"=>$weightOfProduct, 
			"NUM"=>$product_counter
			));

		$product_counter++;

		$t->parse("ShippingDisplayBLout","ShippingDisplayBL",true);
	}
	

	
	// We want to use the Shipping Method that is stored within our CheckoutParamsObj.
	// If that isn't set then use the default Shipping Choice ID to the destination.
	$selectedShippingChoiceID = $checkoutParamsObj->GetShippingChoicesID();
	if(!in_array($selectedShippingChoiceID, $shippingChoicesArr))		
		$selectedShippingChoiceID = $shippingChoicesObj->getDefaultShippingChoiceIDtoDestination($shipFromAddressObj, $shipToAddressObj);

	
	// Build the radio buttons for selecting the different shipping methods
	$RadioBtnHTML = "";
	foreach($shippingChoicesArr as $thisShippingChoiceID){

		// If none of the Products will be able to ship on Saturday... then skip that Shipping Choice.
		if($shippingChoicesObj->checkIfShippingChoiceHasSaturdayDelivery($thisShippingChoiceID) && !$saturdayDeliveryFlag)
			continue;
	
		$CheckedShp = ($selectedShippingChoiceID == $thisShippingChoiceID ? "checked" : "");
		$RadioBtnHTML .= '<input type="radio" name="shippingmethod" value="' . $thisShippingChoiceID . '" onClick="UpdateShipping();" ' . $CheckedShp . '><a href="javascript:ShippingLink(\'' . $thisShippingChoiceID . '\')" class="ShippingChoice">' .  WebUtil::htmlOutput($shippingChoicesObj->getShippingChoiceName($thisShippingChoiceID)) . '</a><br>';
	}
	$t->set_var("SHIPPING_METHODS", $RadioBtnHTML);
	$t->allowVariableToContainBrackets("SHIPPING_METHODS");


	// Now we want to show a description of all scheduled delays for the next 8 days
	$eventSignatureTitlesArr = $eventSchedulerObj->getEventsByLookAheadDays(8, $ProductIDinShoppingCartArr, EventScheduler::DELAYS_PRODUCTION_OR_TRANSIT, EventScheduler::EVENT_SIGNATURE_TITLE);
	
	$eventStr = "";
	foreach($eventSignatureTitlesArr as $thisSignatureTitle){
		if(!empty($eventStr))
			$eventStr .= "<br>";
		$eventTimeStamp = $eventSchedulerObj->getEventSignatureDateFromCached($thisSignatureTitle, EventScheduler::EVENT_SIGNATURE_TITLE);
		$eventStr .= WebUtil::htmlOutput($eventObj->getEventTitleFromTitleSignature($thisSignatureTitle) . " - " . date("D, M jS", $eventTimeStamp));
	}

	
	if(!empty($eventStr))
		$eventStr = "<i>Scheduled Delays:</i>&nbsp;&nbsp;&nbsp;" . $eventStr;
		
	$t->set_var("EVENTS", $eventStr);
	$t->allowVariableToContainBrackets("EVENTS");
}



$subtotal = ShoppingCart::GetShopCartSubTotal($dbCmd, $user_sessionID, 0);


$usingLoyaltyDiscountInsteadOfAffiliateDisc = false;

// Find out if there is an affiliate discount for this user.
$discount_for_user = 0;
if($UserControlObj->getAffiliateName() != "" || ($UserControlObj->getLoyaltyProgram() == "Y" && $loyaltyObj->getLoyaltyDiscountSubtotalPercentage() > 0)){
	$discount_for_user = $UserControlObj->getAffiliateDiscount();

	// Their permanent discount may have expired
	if($UserControlObj->getAffiliateExpires() < time())
		$discount_for_user = 0;
		
		
	// Find out if there is a loyalty discount available for the subtotal
	// If so, we want to find out which discount is greater... the permanent discount, or the loyalty discount.
	$loyaltyDiscPercent = $loyaltyObj->getLoyaltyDiscountSubtotalPercentage() / 100; 
	if($UserControlObj->getLoyaltyProgram() != "Y")
		$loyaltyDiscPercent = 0;
	
	if($loyaltyDiscPercent > $discount_for_user){
		$discount_for_user = $loyaltyDiscPercent;
		$usingLoyaltyDiscountInsteadOfAffiliateDisc = true;
	}
		
	if($discount_for_user > 0){
	
		// The permanent Discount can be altered by certain Product Options for which the Discount does not apply.
		$discDoesntApplyToThisAmnt = ProjectGroup::GetTotalFrmGrpPermDiscDoesntApply($dbCmd, $user_sessionID, "session");
		$discount_for_user = ShoppingCart::GetAdjustedPermanentDiscount($dbCmd, $subtotal, $discDoesntApplyToThisAmnt, $discount_for_user * 100) / 100;
	}
}



$DiscountPrice = 0;

if($discount_for_user > 0){

	$DiscountPrice = $subtotal - ShoppingCart::GetShopCartSubTotal($dbCmd, $user_sessionID, $discount_for_user);

	if($usingLoyaltyDiscountInsteadOfAffiliateDisc)
		$DiscountMessage = ($discount_for_user * 100) . "% discount for " . $UserControlObj->getCompanyOrName();
	else
		$DiscountMessage = ($discount_for_user * 100) . "% discount for " . $UserControlObj->getAffiliateName();

	$t->set_var("PERM_DISCOUNT_JS", $DiscountPrice);  //Javascript Calulates the GrandTotal dynamically.
	$t->set_var("PERM_DISCOUNT_COMMAS", number_format($DiscountPrice,2));
	$t->set_var("DISCOUNT_MESSAGE", $DiscountMessage);
}
else{
	$t->set_var("PERM_DISCOUNT_COMMAS", "");
	$t->set_var("PERM_DISCOUNT_JS", "0");
	$t->discard_block("origPage", "PermDiscountBL");
}


$subtotal = number_format($subtotal, 2);


// Set our original Shipping Method... we need to know if they change it within the Flash App
// Add slashes instead of HTML special characters because the values are going into javascript
$t->set_var("SHIP_CITY_JS", addslashes(strtoupper($checkoutParamsObj->GetShippingCity())));
$t->set_var("SHIP_STATE_JS", addslashes(strtoupper($checkoutParamsObj->GetShippingState())));
$t->set_var("SHIP_ZIP_JS", addslashes(strtoupper($checkoutParamsObj->GetShippingZip())));
$t->set_var("SHIP_COUNTRY_JS", strtoupper($checkoutParamsObj->GetShippingCountry()));
$t->set_var("SHIP_RESI_JS", ($checkoutParamsObj->GetShippingResidentialFlag() ? "Y" : "N"));




// Create an array of expiration dates... always make the drop down have 10 years in addition to the current year
// The key is the 2 digit year.  The value is the four digit year 
$TwoDigitYear = date("y");
$FourDigitYear = date("Y");
$DropCreditCardExpArr = array();

for($i=0; $i<11; $i++){

	// Make sure that there is always a leading 0 for single digits
	$This2DigitYear = "" . ($TwoDigitYear + $i);
	if(strlen($This2DigitYear) == 1)
		$This2DigitYear = "0" . $This2DigitYear;


	$DropCreditCardExpArr[$This2DigitYear] = $FourDigitYear + $i;
}
$t->set_var("CREDIT_EXP_OPTIONS", Widgets::buildSelect($DropCreditCardExpArr, array($TwoDigitYear)));
$t->allowVariableToContainBrackets("CREDIT_EXP_OPTIONS");

$t->set_var(array(
	"SUBTOTAL"=>$subtotal, 
	"SALESTAX"=>Constants::GetSalesTaxConstant($checkoutParamsObj->GetShippingState()),
	"SALES_TAX_AMOUNT"=> number_format(Constants::GetSalesTaxConstant($checkoutParamsObj->GetShippingState()) * $subtotal, 2),
	"SUBTOTAL_NO_COMMA"=>preg_replace("/,/", "", $subtotal)
	));
	


// So that we can prevent an expired card from being submited
$t->set_var(array(
	"CURRENT_MONTH"=>date("n"), 
	"CURRENT_YEAR"=>date("y")
	));


$AutomaticCoupon = $checkoutParamsObj->GetCouponCode();

// If there is not already a Checkout Coupon to be automatically inserted...
// See if there is a coupon to be automatically inserted for the Sales Rep
if(empty($AutomaticCoupon))
	$AutomaticCoupon = WebUtil::GetSessionVar("SalesRepCouponSession", WebUtil::GetCookie("SalesRepCouponCookie"));


	
	
$couponCodeObj = new Coupons($dbCmd);

if(empty($AutomaticCoupon) || $couponCodeObj->CheckIfCouponIsOKtoUse($AutomaticCoupon, $UserID, "shoppingcart", $user_sessionID) != ""){
	$t->discard_block("origPage", "CouponDetail");
	$t->discard_block("origPage", "CouponShippingDiscount");

	$t->set_var("COUPON_SHIPPING_DISCOUNT",0);
	$t->set_var("COUPON_CODE_NAME", "");
	
	// If we don't have a Coupon, then let's see if we have any permanent discount to use.
	$t->set_var("COMBINED_DISCOUNT_JS", $DiscountPrice);
	
}
else {
	
	$couponCodeObj->SetUser($UserID);
	$couponCodeObj->LoadCouponByCode($AutomaticCoupon);
	
	$t->set_var("COUPON_CODE_NAME",strtoupper(WebUtil::htmlOutput($AutomaticCoupon)));
	$t->set_var("COUPON_PERCENTAGE_DISCOUNT", $couponCodeObj->GetCouponDiscountPercentForSubtotal());
	$t->set_var("COUPON_SUBTOTAL_DISCOUNT", number_format(($couponCodeObj->GetCouponDiscountPercentForSubtotal() / 100 * $subtotal), 2));
	$t->set_var("COUPON_SHIPPING_DISCOUNT", number_format($couponCodeObj->GetCouponShippingDiscount(), 2));
	
	// If the coupon is OK to use... then the discount is 
	$t->set_var("COMBINED_DISCOUNT_JS", ($couponCodeObj->GetCouponDiscountPercentForSubtotal() / 100 * $subtotal));
	
	
	if($couponCodeObj->GetCouponShippingDiscount() == 0)
		$t->discard_block("origPage", "CouponShippingDiscount");
		
	// If the Automatic Coupon is OK... then it means that their can't be a permanent discount.
	$t->discard_block("origPage", "PermDiscountBL");
}


// Find out if the user as any coupons activated on their account.  
// If so, we won't want to take away the ability to use coupon codes for the user.
$dbCmd->Query("SELECT COUNT(*) FROM  couponactivation WHERE UserID=" . intval($UserID));
$couponActCount = $dbCmd->GetValue();


// If the customer is used to using a coupon on EVERY SINGLE order... make sure that we don't hide the coupon box.
$dbCmd->Query("SELECT CouponID from orders WHERE UserID=".intval($UserID)." ORDER BY ID DESC LIMIT 1");
$lastCouponUsed = $dbCmd->GetValue();


// If someone clicked on a reminder email, then they may have been offered a coupon code.
$ReferalTracking = WebUtil::GetSessionVar("ReferralSession", WebUtil::GetCookie("ReferralCookie"));
if(preg_match("/^em-/", $ReferalTracking))
	$reminderEmailClicked = true;
else
	$reminderEmailClicked = false;


// Find out if the user clicked on a Email Nofification (or Spam :)
$EmailNotifyJobHistoryID = WebUtil::GetSessionVar("EmailNotifyJobHistoryID", WebUtil::GetCookie( EmailNotifyMessages::getClickCookieName(Domain::getDomainKeyFromID(Domain::getDomainIDfromURL())), 0));

// The CouponDynamic may be set within ArtWork alley if their shopping cart qualifies.
$dynamicCouponCode = WebUtil::GetCookie("CouponDynamic");

if(empty($AutomaticCoupon) && empty($couponActCount) && empty($dynamicCouponCode) && !$reminderEmailClicked && empty($EmailNotifyJobHistoryID) && empty($lastCouponUsed) && !VisitorPath::checkIfVisitorHasGoneThroughLabel("Coupon Page")){
	$t->discard_block("origPage", "CouponEntryBL");
}



$t->set_var("CHECKOUT_COUPON", $AutomaticCoupon);


// On the live site the Navigation bar needs to have hard-coded links to jump out of SLL mode... like http://www.example.com
// Also flash plugin can not have any non-secure source or browser will complain.. nead to change plugin to https:/
if(Constants::GetDevelopmentServer()){
	$t->set_var("SECURE_FLASH","");
	$t->set_var("HTTPS_FLASH","");
	$t->set_var("LIVESERVER","false");
}
else{
	$t->set_var("SECURE_FLASH","TRUE");
	$t->set_var("HTTPS_FLASH","s");
	$t->set_var("LIVESERVER","true");
}


$ua = new UserAgent();

// If they are on a PC with Internet Explorer we can show them a more fancy BUY button with flash animation
// We need 2-way communication (in case of an error).  Currently macs do not support 2 way communication yet
if (preg_match("/Windows/i", $ua->platform) && preg_match("/MSIE/", $ua->browser)){
	$t->set_block("origPage","BuyButtonMAC","BuyButtonMACout");
	$t->set_var(array("BuyButtonMACout"=>""));
}
else{
	$t->set_block("origPage","BuyButtonPC","BuyButtonPCout");
	$t->set_var(array("BuyButtonPCout"=>""));
}




if($AddressVerificationErrorFlag){
	// On an Address error with the shipping address... we don't wan't the billing address to be able to type into.
	$t->set_var("DISABLED_BILLING", "cover");
}
else{

	// If their Zipcode is not in this list, then they are considered to be rural... so show them a message that prices are increasing
	if(!$shippingMethodObj->isDestinationRural($shippingAddressObj))
		$t->discard_block("origPage", "HideRuralMessageBL");
		

}


// Don't show the Paypal button unless a cookie is set.
if(WebUtil::GetCookie("ShowPaypalButton") == "yes")
	$t->discard_block("origPage","CheckoutWithoutPaypal");
else 
	$t->discard_block("origPage","PaypalButton");

	
	
$shippingInstructions = $checkoutParamsObj->GetShippingInstructions();
$shipInstrMultiLine = "";
$shippingInstArr = preg_split("/\n/", $shippingInstructions);
foreach($shippingInstArr as $thisLine){
	if(!empty($shipInstrMultiLine))
		$shipInstrMultiLine .= "<br>";
	$shipInstrMultiLine .= WebUtil::htmlOutput($thisLine);
}

$t->set_var("SHIPPING_INSTRUCTIONS", $shipInstrMultiLine);
$t->allowVariableToContainBrackets("SHIPPING_INSTRUCTIONS");


$t->set_var("LOYALTY_SHIPPING_DISCOUNT_TOTAL", $totalLoyaltyShippingDiscounts);
$t->set_var("PRODUCT_ID_ARR", json_encode($ProductIDinShoppingCartArr));
$t->set_var("LOYALTY_SHIP_PRODUCT_DISCOUNTS_JSON", json_encode($loyaltyShippingDiscArr));



$t->set_var("SHIPPING_ADDRESS_HTML", $checkoutParamsObj->getShippingDescription(true));
$t->allowVariableToContainBrackets("SHIPPING_ADDRESS_HTML");


$t->set_var("B_NAME", WebUtil::htmlOutput($checkoutParamsObj->GetBillingName()));
$t->set_var("B_COMPANY", WebUtil::htmlOutput($checkoutParamsObj->GetBillingCompany()));
$t->set_var("B_ADDRESS", WebUtil::htmlOutput($checkoutParamsObj->GetBillingAddress()));
$t->set_var("B_ADDRESS_TWO", WebUtil::htmlOutput($checkoutParamsObj->GetBillingAddressTwo()));
$t->set_var("B_CITY", WebUtil::htmlOutput($checkoutParamsObj->GetBillingCity()));
$t->set_var("B_STATE", WebUtil::htmlOutput($checkoutParamsObj->GetBillingState()));
$t->set_var("B_ZIP", WebUtil::htmlOutput($checkoutParamsObj->GetBillingZip()));
$t->set_var("B_COUNTRY", WebUtil::htmlOutput($checkoutParamsObj->GetBillingCountry()));

$t->set_var("S_NAME", WebUtil::htmlOutput($checkoutParamsObj->GetShippingName()));
$t->set_var("S_COMPANY", WebUtil::htmlOutput($checkoutParamsObj->GetShippingCompany()));
$t->set_var("S_ADDRESS", WebUtil::htmlOutput($checkoutParamsObj->GetShippingAddress()));
$t->set_var("S_ADDRESS_TWO", WebUtil::htmlOutput($checkoutParamsObj->GetShippingAddressTwo()));
$t->set_var("S_CITY", WebUtil::htmlOutput($checkoutParamsObj->GetShippingCity()));
$t->set_var("S_STATE", WebUtil::htmlOutput($checkoutParamsObj->GetShippingState()));
$t->set_var("S_ZIP", WebUtil::htmlOutput($checkoutParamsObj->GetShippingZip()));
$t->set_var("S_COUNTRY", WebUtil::htmlOutput($checkoutParamsObj->GetShippingCountry()));

$t->set_var("S_RESIDENTIAL_FLAG", WebUtil::htmlOutput($checkoutParamsObj->GetShippingResidentialFlag() ? "Y" : "N"));

$t->set_var("S_SHIPPING_CHOICE_ID", WebUtil::htmlOutput($checkoutParamsObj->GetShippingChoicesID()));


// So Javascript knows if there has been an Address Error
if($AddressVerificationErrorFlag)
	$t->set_var("SHIPPING_ERROR_FLAG_JS","true");
else
	$t->set_var("SHIPPING_ERROR_FLAG_JS","false");


$onlineCsrArr = ChatCSR::getCSRsOnline(Domain::getDomainIDfromURL(), array(ChatThread::TYPE_Checkout));
if(empty($onlineCsrArr))
	$t->set_var("CHAT_ONLINE", "false");
else 
	$t->set_var("CHAT_ONLINE", "true");
	
	
VisitorPath::addRecord("Checkout Screen");

$t->pparse("OUT","origPage");



?>
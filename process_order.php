<?

require_once("library/Boot_Session.php");



$user_sessionID =  WebUtil::GetSessionID();


//The weight could take a while to calculate on a mass import or to communicate with the bank... give it at least 2 minutes
set_time_limit(120);

WebUtil::checkFormSecurityCode();



// Collect data from the URL
$b_name = WebUtil::GetInput("b_name", FILTER_SANITIZE_STRING_ONE_LINE);
$b_company = WebUtil::GetInput("b_company", FILTER_SANITIZE_STRING_ONE_LINE);
$b_address = WebUtil::GetInput("b_address", FILTER_SANITIZE_STRING_ONE_LINE);
$b_address2 = WebUtil::GetInput("b_address2", FILTER_SANITIZE_STRING_ONE_LINE);
$b_city = WebUtil::GetInput("b_city", FILTER_SANITIZE_STRING_ONE_LINE);
$b_state = WebUtil::GetInput("b_state", FILTER_SANITIZE_STRING_ONE_LINE);
$b_zip = WebUtil::GetInput("b_zip", FILTER_SANITIZE_STRING_ONE_LINE);
$b_country = WebUtil::GetInput("b_country", FILTER_SANITIZE_STRING_ONE_LINE);

$s_name = WebUtil::GetInput("s_name", FILTER_SANITIZE_STRING_ONE_LINE);
$s_company = WebUtil::GetInput("s_company", FILTER_SANITIZE_STRING_ONE_LINE);
$s_address = WebUtil::GetInput("s_address", FILTER_SANITIZE_STRING_ONE_LINE);
$s_address2 = WebUtil::GetInput("s_address2", FILTER_SANITIZE_STRING_ONE_LINE);
$s_city = WebUtil::GetInput("s_city", FILTER_SANITIZE_STRING_ONE_LINE);
$s_state = WebUtil::GetInput("s_state", FILTER_SANITIZE_STRING_ONE_LINE);
$s_zip = WebUtil::GetInput("s_zip", FILTER_SANITIZE_STRING_ONE_LINE);
$s_country = WebUtil::GetInput("s_country", FILTER_SANITIZE_STRING_ONE_LINE);
$s_resi = WebUtil::GetInput("s_resi", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "Y");

$ponumber = WebUtil::GetInput("ponumber", FILTER_SANITIZE_STRING_ONE_LINE);
$shippingChoiceID = WebUtil::GetInput("shippingmethod", FILTER_SANITIZE_INT);
$cardtype = WebUtil::GetInput("cardtype", FILTER_SANITIZE_STRING_ONE_LINE);
$cardnumber = WebUtil::GetInput("cardnumber", FILTER_SANITIZE_STRING_ONE_LINE);
$monthexpiration = WebUtil::GetInput("monthexpiration", FILTER_SANITIZE_INT);
$yearexpiration = WebUtil::GetInput("yearexpiration", FILTER_SANITIZE_INT);
$couponcode = WebUtil::GetInput("couponcode", FILTER_SANITIZE_STRING_ONE_LINE);

// "C" = Corporate, "N" = Normal (or credit card), "P" = Paypal
$paymentType = WebUtil::GetInput("paymenttype", FILTER_SANITIZE_STRING_ONE_LINE);

// The HTML designers may want a template ID to be used within the User Confirmation page.
$templateIdForConfirmation = WebUtil::GetInput("templateIdForConfirmation", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);


$paypalToken = WebUtil::GetSessionVar("PaypalToken");
$paypalPayerId = WebUtil::GetSessionVar("PaypalPayerID");
		

// The FILTER_SANITIZE_INT removes the Zero prefix.
if(strlen($yearexpiration) == 1)
	$yearexpiration = "0" . $yearexpiration;
if(strlen($monthexpiration) == 1)
	$monthexpiration = "0" . $monthexpiration;
	

if(!in_array($paymentType, array("C", "N", "P")))
	throw new Exception("Illegal payment type submitted on the Process order screen.");
	

$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();
$dbCmd3 = new DbCmd();

$UserControlObj = new UserControl($dbCmd2);

$PaymentInvoiceObj = new PaymentInvoice();
$PaymentsObj = new Payments(Domain::getDomainIDfromURL());

$loyaltyObj = new LoyaltyProgram(Domain::getDomainIDfromURL());

$couponObj = new Coupons($dbCmd);


$AuthObj = new Authenticate(Authenticate::login_general);
$UserID = $AuthObj->GetUserID();

$UserControlObj->LoadUserByID($UserID);


$CheckoutParamsObj = new CheckoutParameters();
$CheckoutParamsObj->SetUserID($UserID);


// If the customer's account has a locked address... then always override the data coming in from the URL
// We want to use the address that is stored in the user account instead.
// If the Address is locked then both the shipping and billing are disabled.
if($UserControlObj->getAddressLocked() == "Y"){

	// Disable billing address
	$b_name = $UserControlObj->getName();
	$b_company = $UserControlObj->getCompany();
	$b_address = $UserControlObj->getAddress();
	$b_address2 = $UserControlObj->getAddressTwo();
	$b_city = $UserControlObj->getCity();
	$b_state = $UserControlObj->getState();
	$b_zip = $UserControlObj->getZip();
	$b_country = $UserControlObj->getCountry();

	// Disable the shipping address
	$s_name = $UserControlObj->getName();
	$s_company = $UserControlObj->getCompany();
	$s_address = $UserControlObj->getAddress();
	$s_address2 = $UserControlObj->getAddressTwo();
	$s_city = $UserControlObj->getCity();
	$s_state = $UserControlObj->getState();
	$s_zip = $UserControlObj->getZip();
	$s_country = $UserControlObj->getCountry();
	$s_resi = $UserControlObj->getResidentialFlag();
}




// States in the U.S. should be captilized and only 2 letters
if($b_country == "US")
	$b_state = WebUtil::CapitilizeUnitedState($b_state);
if($s_country == "US")
	$s_state = WebUtil::CapitilizeUnitedState($s_state);
	


// In case they try to process the order, but gets declined... then we want to remember billing info so they don't have to retype
$CheckoutParamsObj->SetShippingChoiceID($shippingChoiceID);

$CheckoutParamsObj->SetShippingName($s_name);
$CheckoutParamsObj->SetShippingCompany($s_company);
$CheckoutParamsObj->SetShippingAddress($s_address);
$CheckoutParamsObj->SetShippingAddressTwo($s_address2);
$CheckoutParamsObj->SetShippingCity($s_city);
$CheckoutParamsObj->SetShippingState($s_state);
$CheckoutParamsObj->SetShippingZip($s_zip);
$CheckoutParamsObj->SetShippingCountry($s_country);
$CheckoutParamsObj->SetShippingResidentialFlag($s_resi == "Y" ? true : false );

$CheckoutParamsObj->SetBillingName($b_name);
$CheckoutParamsObj->SetBillingCompany($b_company);
$CheckoutParamsObj->SetBillingAddress($b_address);
$CheckoutParamsObj->SetBillingAddressTwo($b_address2);
$CheckoutParamsObj->SetBillingCity($b_city);
$CheckoutParamsObj->SetBillingState($b_state);
$CheckoutParamsObj->SetBillingZip($b_zip);
$CheckoutParamsObj->SetBillingCountry($b_country);




// If the user is a reseller, make sure that they are not trying to ship internationally
// We have to show the prices on the invoice to international customers... and resellers by default hide their prices.
$resellerObj = new Reseller($dbCmd);
if($resellerObj->LoadReseller($UserID) && $s_country != "US")
	WebUtil::PrintError("Resellers are not able to ship outside of the U.S.  The reason is that an invoice (with prices) needs to be displayed on the outside of the shipping container.  Resellers, by default, have their prices hidden.  You will need to ship the package to yourself first, and then re-ship internationally.", true);


$WebamsterErrorURL = "";

// Make sure that the shipping method is valid
$shippingChoiceObj = new ShippingChoices();


// In case the user only has mailing jobs selected... we want to select the chepeast shipping choice.
// The shipping methods make no difference in the backend because Mailing Batches are handeled differently.
// However, we don't want them to confuse production when their are looking through a list of expedted orderes to get out.
if(ProjectGroup::checkIfOnlyMailingJobsInGroup($dbCmd, $user_sessionID, "shoppingcart")){
	$shippingChoiceID = $shippingChoiceObj->getLowestShippingChoicePriorityWithCheapestPrice();
}




if(!$shippingChoiceObj->checkIfShippingChoiceIDExists($shippingChoiceID)){
	$URLerrorMessage = "You forgot to choose a shipping method or the shipping method is invalid."; 
	$WebamsterErrorURL .= "\nAn Invalid Shipping Character was sent on the process order screen: $shippingChoiceID";
}

// Make sure that the fields coming in from the Form have all been completed.
$URLerrorMessage = "";
if (empty($b_name) || empty($b_address) || empty($b_city) || empty($b_state) || empty($b_zip) || empty($b_country)){
	$URLerrorMessage = "Your billing address is not complete.  Please ensure that you have correctly filled in your Name, Address, City, State, and Postal Code.";
	$WebamsterErrorURL .= "\nBilling Address not complete on process order screen.\n\nFields:\n$b_name : $b_address : $b_city : $b_state : $b_zip : $b_country";
}


if (empty($s_name) || empty($s_address) || empty($s_city) || empty($s_state) || empty($s_zip) || empty($s_country)){
	$URLerrorMessage = "Your shipping address is not complete.  Please ensure that you have correctly filled in your Name, Address, City, State, and Postal Code.";
	$WebamsterErrorURL .= "\nShipping Address not complete on process order screen.\n\nFields:\n$s_name : $s_address : $s_city : $s_state : $s_zip : $s_country";
}
if(empty($shippingChoiceID)){
	$URLerrorMessage = "You forgot to choose a shipping method."; 
	$WebamsterErrorURL .= "\nShipping Method not submitted to the process order screen.";
}

//Ensure that the credit card is supplied (if it is not a Paypal Transaction).
if($paymentType != "P"){
	if(empty($cardtype) || empty($cardnumber) || empty($monthexpiration) || empty($yearexpiration) )  {
		$URLerrorMessage = "Your credit card and/or expiration date was not filled in correctly."; 
		$WebamsterErrorURL .= "\nCard type or expiration data not submitted to the process order screen.\n\nFields:\n$cardtype : $cardnumber : $monthexpiration : $yearexpiration";
	}
}

if($paymentType == "P")
	$cardtype = "Paypal";


if(!empty($WebamsterErrorURL)){
	$ua = new UserAgent();
	WebUtil::WebmasterError($WebamsterErrorURL . "\n\n\nUserID: " . $UserID . "\nEmail: " . $UserControlObj->getEmail(). "\nName: " . $UserControlObj->getName()  . "\n\nBrowser:" . $ua->browser . " Version: " . $ua->version . " OS: " . $ua->platform);
}
if(!empty($URLerrorMessage))
	WebUtil::PrintError($URLerrorMessage, true);




$FullNamePartsHash = UserControl::GetPartsFromFullName($b_name);
$firstname = $FullNamePartsHash["First"];
$lastname = $FullNamePartsHash["Last"];


if (empty($firstname))
	WebUtil::PrintError("It does not look like you entered a name within your Billing Address. If you continue to have problems then contact Customer Service.", true);



// Clean dashes and spaces out of the credit card number
$cardnumber = preg_replace("/\s+/", "", $cardnumber);
$cardnumber = preg_replace("/-/", "", $cardnumber);



$userEmailAddress = $UserControlObj->getEmail();
$userPhone = $UserControlObj->getPhone();
$userName = $UserControlObj->getName();
$AffiliateName = $UserControlObj->getAffiliateName();
$AffiliateDiscount = $UserControlObj->getAffiliateDiscount();
$AffiliateExpires = $UserControlObj->getAffiliateExpires();


$totalLoyaltyShippingDiscounts = 0;
$totalLoyaltySubtotalDiscounts = 0;
$loyaltySubtotalDiscountFlag = false;

// Get the Subtotal from the shopping cart
$SubTotalWithoutDiscount = ShoppingCart::GetShopCartSubTotal($dbCmd, $user_sessionID, 0);


// Find out if there is an affiliate discount for this user and if it has expired.
$discount_for_user = 0;
if(!empty($AffiliateName) || ($UserControlObj->getLoyaltyProgram() == "Y" && $loyaltyObj->getLoyaltyDiscountSubtotalPercentage() > 0)){
	$discount_for_user = $AffiliateDiscount;

	// Now check to see if the offer has expired
	if($AffiliateExpires < time())
		$discount_for_user = 0;

		
	// Find out if there is a loyalty discount available for the subtotal
	// If so, we want to find out which discount is greater... the permanent discount, or the loyalty discount.
	$loyaltyDiscPercent = $loyaltyObj->getLoyaltyDiscountSubtotalPercentage() / 100; 
	if($UserControlObj->getLoyaltyProgram() != "Y")
		$loyaltyDiscPercent = 0;
	
	if($loyaltyDiscPercent > $discount_for_user){
		$discount_for_user = $loyaltyDiscPercent;
		$loyaltySubtotalDiscountFlag = true;
	}
		
	// Find out if the permanent discount has to be adjusted because there are options for which a permanent discount doesn't apply on this order.
	if($discount_for_user > 0){
	
		// The permanent Discount can be altered by certain Product Options for which the Discount does not apply.
		$discDoesntApplyToThisAmnt = ProjectGroup::GetTotalFrmGrpPermDiscDoesntApply($dbCmd, $user_sessionID, "session");
		$discount_for_user = ShoppingCart::GetAdjustedPermanentDiscount($dbCmd, $SubTotalWithoutDiscount, $discDoesntApplyToThisAmnt, $discount_for_user * 100) / 100;
	}
}



$couponShippingHandelingDiscount = 0;

$couponcode = trim($couponcode);
$couponcode = preg_replace("/\s/", "", $couponcode);


if (!empty($couponcode)){

	//Function will return a String.  Empty string means the coupon is good
	$ValidateCouponResponse = $couponObj->CheckIfCouponIsOKtoUse($couponcode, $UserID, "shoppingcart", $user_sessionID, $cardnumber);
	
	if(!empty($ValidateCouponResponse))
		WebUtil::PrintError("You have submitted an invalid coupon. Go back and try a different one. Error Reason: ". $ValidateCouponResponse, true);

	//Discounts are internally used with a decimal
	$discount_for_user = $couponObj->GetCouponDiscountPercentForSubtotal() / 100;
	$couponID = $couponObj->GetCouponID();
	$couponShippingHandelingDiscount = $couponObj->GetCouponShippingDiscount();
	
	// In case the coupon needs to assign a SalesRep to the customer so they can begin earning commissions.
	SalesCommissions::PossiblyAssociateSalesRepFromCouponCode($dbCmd, $couponcode, $AuthObj);

}
else{
	$couponID = 0;
}




// The order summary will be shown to users on the auto-confirm email.. ex. 100 Business Cards.
$order_summary = "";



// Will return a unique list of Product ID's sitting in the shopping cart
$ProductIDinShoppingCartArr = ProjectGroup::GetProductIDlistFromGroup($dbCmd, $user_sessionID, "shoppingcart");

if(sizeof($ProductIDinShoppingCartArr) == 0)
	WebUtil::PrintError("Your Shopping Cart is empty. Your order may have already gone through. Please visit your 'Order History' within Customer Service.", true);



foreach($ProductIDinShoppingCartArr as $thisProductID){
	
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
		$totalLoyaltyShippingDiscounts += $loyaltyObj->getLoyaltyDiscountShipping($weightOfProduct);
	}
	
	$order_summary .= WebUtil::htmlOutput($projectInfoObj->getOrderDescription($quantityOfProduct)) . "<br>";
}


$ShippingCost = ShippingPrices::getTotalShippingPriceForGroup($user_sessionID, "shoppingcart", $shippingChoiceID, $CheckoutParamsObj->getShippingAddressMailingObj());


// If a coupon gave a S&H discount then subtract it from the shipping Quote... never go below $0 though.
$ShippingCost -= ($couponShippingHandelingDiscount + $totalLoyaltyShippingDiscounts);
if($ShippingCost < 0)
	$ShippingCost = 0;

$SubTotal = ShoppingCart::GetShopCartSubTotal($dbCmd, $user_sessionID, $discount_for_user);
$SubtotalTax = ShoppingCart::GetShopCartTax($dbCmd, $user_sessionID, $discount_for_user, $s_state);
$ShippingTax = round(Constants::GetSalesTaxConstant($s_state) * $ShippingCost, 2);

$SalesTax = $SubtotalTax + $ShippingTax;

$GrandTotal = $SalesTax + $ShippingCost + $SubTotal;



// If the user saved money off of the subtotal as a result of the loyalty program, record how much that savings is.
if($loyaltySubtotalDiscountFlag){
	$totalLoyaltySubtotalDiscounts = number_format(($SubTotalWithoutDiscount - $SubTotal), 2, ".", "");
}




// Check to make sure that the BillingStatus is in good standing for this cutomer
if($UserControlObj->getBillingStatus() != "G")
	WebUtil::PrintError("Your billing status has a problem. Please contact customer service.", true);




//  Charge the credit card through AUTHORIZE.NET
#################################################################

// Find out what the next order number would be.
// Technically we should lock the tables to do this... but honestly I don't really care if Authorize.net gets the correct Invoice number or not
// The chances that they will actually use the invoice number are really slim.... and then combine that with the chances that locking the table would have prevented a mixup... it is not worth the overhead.
$dbCmd->Query("SELECT MAX(ID) FROM orders");
$nextOrderNumberInSystem = $dbCmd->GetValue();
$nextOrderNumberInSystem += 1;


// Concatenate Address and Address 2.
$BillAddress = $b_address . " " . $b_address2;
$BillAddress = trim($BillAddress);

$OrderInfoHash["Card_Num"] = $cardnumber;
$OrderInfoHash["Exp_Date"] = $monthexpiration . $yearexpiration;
$OrderInfoHash["First_Name"] = $firstname;
$OrderInfoHash["Last_Name"] = $lastname;
$OrderInfoHash["Address"] = $CheckoutParamsObj->GetBillingAddress();
$OrderInfoHash["City"] = $CheckoutParamsObj->GetBillingCity();
$OrderInfoHash["State"] = $CheckoutParamsObj->GetBillingState();
$OrderInfoHash["Zip"] = $CheckoutParamsObj->GetBillingZip();
$OrderInfoHash["Country"] = $CheckoutParamsObj->GetBillingCountry();
$OrderInfoHash["Email"] = $userEmailAddress;
$OrderInfoHash["Phone"] = $userPhone;
$OrderInfoHash["InvoiceNum"] = $nextOrderNumberInSystem;



$orderBillingType = $paymentType;

if(!in_array($orderBillingType, array("N", "P", "C")))
	throw new Exception("Error with Order Billing Type.");
	
// If a customer has a positive Credit with the company then we would prefer to force them into Corporate Billing Status for this transaction
// But the customer may have overriden this system and instead prefered to use a credit card.
// We will know if they have overriden the option to use their Credit Card over their available credit because the Card Type will not be "Billed".
$forceCorporateBillingForOrderFlag = false;

$PaymentInvoiceObj->LoadCustomerByID($UserID);		
$CreditUsageByCustomer = $PaymentInvoiceObj->GetCurrentCreditUsage();

if($orderBillingType == "N" && $CreditUsageByCustomer < 0 ){
	

	if($cardtype == "Billed"){

		$forceCorporateBillingForOrderFlag = true;
		$orderBillingType = "C";
	}
}





// Do not let the Let Orders Totals exceed the User's Maximum.  This is to keep people from issuing chargebacks on their credit cards.
if($orderBillingType == "N"){

	if($GrandTotal > $UserControlObj->getSingleOrderLimit())
		WebUtil::PrintError("We can not process a credit card transaction over \$" . number_format($UserControlObj->getSingleOrderLimit()) . ".  Contact Customer Service to change this limit or arrange an alternative.  Many times customers will mail in a check to establish a positive balance and/or create a 'Corporate Billed Account'.", true);


	$mySQLtimestamp_now = date("YmdHis");
	$mySQLtimestamp_OneMonthAgo = date("YmdHis", mktime (0,0,0,(date("n") - 1),date("j"),date("Y")));

	// Make sure the total amount of Credit Card Authorizations within 1 month does not exceeed a certain amount.
	$dbCmd->Query("SELECT SUM(ChargeAmount) FROM charges INNER JOIN orders ON orders.ID = charges.OrderID 
			WHERE ChargeType='A' AND orders.DomainID=".Domain::getDomainIDfromURL()." AND orders.CardNumber='". $cardnumber . "' AND charges.StatusDate BETWEEN $mySQLtimestamp_OneMonthAgo AND $mySQLtimestamp_now");
	$oneMonthAuths = $dbCmd->GetValue();
	
	if($GrandTotal + $oneMonthAuths > $UserControlObj->getMonthsChargesLimit())
		WebUtil::PrintError("Within the past couple of months you have authorized a lot of charges. We can not process this level of activity through a credit card.  Contact Customer Service to change this limit or arrange an alternative. Many times customers will mail in a check to establish a positive balance and/or create a 'Corporate Billed Account'.", true);

}



$PaymentsObj->LoadCustomerByID($UserID, $forceCorporateBillingForOrderFlag);


if($orderBillingType == "P") {
	$PaymentsObj->setPaypalTokenPayerID($paypalToken,$paypalPayerId);
	// In case someone is using Paypal it will override the billing type preferences on their account.
	$PaymentsObj->setBillingTypeOverride($paymentType);
}
	
$PaymentsObj->SetBillingInfo($OrderInfoHash);

$PaymentsObj->SetTransactionAmounts($GrandTotal, $SalesTax, $ShippingCost);

// Execute the transaction
$tranasctionSuccess = $PaymentsObj->AuthorizeFunds();

if(!$tranasctionSuccess){
	
	//OK, so the transaction did not go through... lets find out if an error occured
	if($PaymentsObj->GetTransactionStatus() == 'E'){

		// Don't notify the webmaster if the error is reason is explainable. 
		if(!$PaymentsObj->CheckIfErrorIsExplainable()){
			VisitorPath::addRecord("Checkout Error", "Unexplainable");
			WebUtil::CommunicationError("Unexplainable Payment Error: Payment Type ( $orderBillingType ) " . $PaymentsObj->GetErrorReason());
		}
		else{
			VisitorPath::addRecord("Checkout Error", $PaymentsObj->GetErrorReason());
		}
			
		WebUtil::PrintError($PaymentsObj->GetErrorReason(), true);
	}
	else if($PaymentsObj->GetTransactionStatus() == 'D'){
		VisitorPath::addRecord("Checkout Declined", $PaymentsObj->GetErrorReason());
		WebUtil::PrintError($PaymentsObj->GetErrorReason(), true);
	}
	else{
		VisitorPath::addRecord("Checkout Error", "Unknown Transaction Status");
		WebUtil::WebmasterError("An unknown transaction status was returned.");
		WebUtil::PrintError("An error occurred making a secure connection to our payment institution. The payment gateway, Authorize.net, may be experiencing difficulties. This problem has been reported to the webmaster. Your credit card has not been charged. You should save your project(s) and then try again later.  Visit your shopping cart to save your project(s).", true);
	}
}


WebUtil::OutputCompactPrivacyPolicyHeader();



// Make sure to do this before wiping out out main tracking codes for ReferralSession and ReferralCookie
// If the User clicked on a link provided by a Sales Rep, then this will link them together.
// It will not hurt to call this even if they were just asscociated to the Sales Reb by a coupon (higher up in this script)
SalesCommissions::PossiblyAssociateSalesRepFromSessionData($dbCmd, $AuthObj);



// Check and see if we stored a referral into the user session 
// We do that if they come to /log.php when linking to the site
// If we don't have any refferal data from the session, then see if we can grab it out of a Permanent Cookie
$ReferalTracking = WebUtil::GetSessionVar("ReferralSession", WebUtil::GetCookie("ReferralCookie"));
$ReferralDate = WebUtil::GetSessionVar("ReferralDateSession", WebUtil::GetCookie("ReferralDateCookie"));

// Temporary hack becuase of a typo.
if(empty($ReferralDate)){
	$ReferralDate = WebUtil::GetCookie("ReferraDatelCookie");
}

// Find out if the user clicked on a Email Nofification (or Spam :)
$EmailNotifyJobHistoryID = WebUtil::GetSessionVar("EmailNotifyJobHistoryID", WebUtil::GetCookie( EmailNotifyMessages::getClickCookieName(Domain::getDomainKeyFromID(Domain::getDomainIDfromURL())), 0));


if(empty($ReferralDate))
	$ReferralDate = null;

// wipe it out to avoid double tracking.
WebUtil::SetSessionVar("ReferralSession", "");
WebUtil::SetCookie('ReferralCookie', "", 10);
 
WebUtil::SetSessionVar("ReferralDateSession", "");
WebUtil::SetCookie('ReferralDateCookie', "", 10);

WebUtil::SetSessionVar("EmailNotifyJobHistoryID", "");
WebUtil::SetCookie('EmailNotifyJobHistoryID', "", 10);


// Regeneration Advertising is meant to capture visitors after they click on banner advertisement.  Companies like SteelHouse media offer a safety net for people who don't complete a purhase on their first attempt.
$regenerationTrackingCode = WebUtil::GetSessionVar("RegenTrackingCodeSession", WebUtil::GetCookie("RegenTrackingCodeCookie"));
if(!empty($regenerationTrackingCode)){
	WebUtil::SetSessionVar("RegenTrackingCodeSession", "");
	WebUtil::SetCookie('RegenTrackingCodeCookie', "", 10);
}
else{
	$regenerationTrackingCode = null;
}


// If we don't have a tracking code set.... then see if we have one set within a Sales Rep Cookie.
// We track the Referal Code the same in the orders table if it is a Sales Rep or a Company account.
if(empty($ReferalTracking)){

	$ReferalTracking = WebUtil::GetSessionVar("SalesRepReferralSession", WebUtil::GetCookie("SalesRepReferralCookie"));

	// wipe it out to avoid double tracking.
	WebUtil::SetSessionVar('SalesRepReferralSession', "");
	WebUtil::SetCookie('SalesRepReferralCookie', "", 10);
}




// Now we want to record the Referer URL from when the user clicked on an banner Advertisement.
$bannerRefererURL = WebUtil::GetSessionVar("BannerRefererURLsession", WebUtil::GetCookie("BannerRefererURLcookie"));

// wipe it out to avoid double tracking.
WebUtil::SetSessionVar('BannerRefererURLsession', "");
WebUtil::SetCookie('BannerRefererURLcookie', "", 10);


// Wipe out any Promo Specials
WebUtil::SetSessionVar('PromoSpecial', "");
WebUtil::SetCookie('PromoSpecial', "", 10);





//   If it got to this point then it looks like the credit card transaction is approved.  ----##
//   Record the order into our system now.												----##
######################################################################################################


$timestamp = time();
$mysql_timestamp = date("YmdHis", $timestamp);


// The P.O. is inserted into the Invoice Note
if(empty($ponumber))
	$TheInvoiceNote = "";
else
	$TheInvoiceNote = "P.O. # " . $ponumber;




// Record information into the "orders table" 
$insertArr = array();
$insertArr["UserID"] = $UserID;

$insertArr["BillingName"] = $CheckoutParamsObj->GetBillingName();
$insertArr["BillingCompany"] = $CheckoutParamsObj->GetBillingCompany();
$insertArr["BillingAddress"] = $CheckoutParamsObj->GetBillingAddress();
$insertArr["BillingAddressTwo"] = $CheckoutParamsObj->GetBillingAddressTwo();
$insertArr["BillingCity"] = $CheckoutParamsObj->GetBillingCity();
$insertArr["BillingState"] = $CheckoutParamsObj->GetBillingState();
$insertArr["BillingZip"] = $CheckoutParamsObj->GetBillingZip();
$insertArr["BillingCountry"] = $CheckoutParamsObj->GetBillingCountry();

$insertArr["ShippingName"] = $CheckoutParamsObj->GetShippingName();
$insertArr["ShippingCompany"] = $CheckoutParamsObj->GetShippingCompany();
$insertArr["ShippingAddress"] = $CheckoutParamsObj->GetShippingAddress();
$insertArr["ShippingAddressTwo"] = $CheckoutParamsObj->GetShippingAddressTwo();
$insertArr["ShippingCity"] = $CheckoutParamsObj->GetShippingCity();
$insertArr["ShippingState"] = $CheckoutParamsObj->GetShippingState();
$insertArr["ShippingZip"] = $CheckoutParamsObj->GetShippingZip();
$insertArr["ShippingCountry"] = $CheckoutParamsObj->GetShippingCountry();
$insertArr["ShippingResidentialFlag"] = $CheckoutParamsObj->GetShippingResidentialFlag() ? "Y" : "N";
$insertArr["ShippingInstructions"] = $CheckoutParamsObj->GetShippingInstructions();

$insertArr["ShippingChoiceID"] = $shippingChoiceID;



$insertArr["ShippingTax"] = "0.00";  // Just set to $0 for now, a function call later will update this
$insertArr["ShippingQuote"] = $ShippingCost;
$insertArr["InvoiceNote"] = $TheInvoiceNote;
$insertArr["CardType"] = $cardtype;
$insertArr["CardNumber"] = $cardnumber;
$insertArr["MonthExpiration"] = $monthexpiration;
$insertArr["YearExpiration"] = $yearexpiration;
$insertArr["DateOrdered"] = $mysql_timestamp;
$insertArr["Referral"] = $ReferalTracking;
$insertArr["EmailNotifyJobHistoryID"] = $EmailNotifyJobHistoryID;
$insertArr["RegenTrackingCode"] = $regenerationTrackingCode;
$insertArr["ReferralDate"] = $ReferralDate;
$insertArr["BannerReferer"] = $bannerRefererURL;
$insertArr["AffiliateSource"] = WebUtil::GetSessionVar("AffiliateSource", WebUtil::GetCookie("AffiliateSource"));
$insertArr["AffiliateIdentifier"] = WebUtil::GetSessionVar("AffiliateIdentifier", WebUtil::GetCookie("AffiliateIdentifier"));
$insertArr["IPaddress"] = WebUtil::getRemoteAddressIp();
$insertArr["CouponID"] = $couponID;
$insertArr["BillingType"] = $orderBillingType;
$insertArr["DomainID"] = Domain::getDomainIDfromURL();
$insertArr["LocationID"] = MaxMind::getLocationIdFromIPaddress(WebUtil::getRemoteAddressIp());
$insertArr["ISPname"] = MaxMind::getIspFromIPaddress(WebUtil::getRemoteAddressIp());
$insertArr["SessionID"] = WebUtil::GetSessionID();

// Find out if the customer has placed an order in the past.
$dbCmd->Query("SELECT COUNT(*) FROM orders WHERE UserID=$UserID");
$previousOrderCount = $dbCmd->GetValue();

if($previousOrderCount == 0)
	$insertArr["FirstTimeCustomer"] = "Y";
else 
	$insertArr["FirstTimeCustomer"] = "N";

// Create a new order in the system.
$order_number = $dbCmd->InsertQuery("orders",  $insertArr);

if($orderBillingType == "P") 
	Order::saveThirdPartyInvoiceToOrder($order_number, $PaymentsObj->getThirdPartyInvoice() );

// Record the charge information into the database for the order that was just inserted
$PaymentsObj->setOrderIdLink($order_number);
$PaymentsObj->CreateNewTransactionRecord(0);

// Get all of the information out of the shopping cart and the project table.
$dbCmd->Query("SELECT ProjectRecord FROM shoppingcart where SID=\"". $user_sessionID . "\" AND shoppingcart.DomainID=".Domain::getDomainIDfromURL());

// Create Projects Ordered by copying info from the Projects Sessions
while ($projectSessionID = $dbCmd->GetValue()){

	$projectSessionObj = ProjectSession::getObjectByProjectID($dbCmd2, $projectSessionID);
	$projectOrderedObj = new ProjectOrdered($dbCmd2);
	
	$projectOrderedObj->copyProject($projectSessionObj);
	$projectOrderedObj->setOrderID($order_number);
	
	// If the user has sent a coupon code, then the coupon object should have already been intialized by this point in the script.
	// Coupons allow some projects in an order to have discounts, while others may not.
	// Otherwise use the permanent discount for the user... if they don't have one of those then set the discount to 0 for the project
	if (!empty($couponcode)){
		$projectOrderedObj->setCustomerDiscount($couponObj->GetDiscountPercentForProject($projectSessionID)/100);
		
		// Save a production note (if any)
		$projectOrderedObj->setNotesProduction($couponObj->GetProductionNote());
	}
	else if(!empty($discount_for_user)){
		$projectOrderedObj->setCustomerDiscount($discount_for_user);
	}
	else{
		$projectOrderedObj->setCustomerDiscount(0);
	}
	
	$projectOrderedID = $projectOrderedObj->createNewProject();
	
	// Make sure any image that is in the imagessession table is moved into the imagessaved table
	ArtworkLib::SaveImagesInSession($dbCmd2, "projectssession", $projectSessionID, ImageLib::GetImagesSavedTableName($dbCmd2), ImageLib::GetVectorImagesSavedTableName($dbCmd2));

	ProjectHistory::RecordProjectHistory($dbCmd2, $projectOrderedID, "N", 0);

	//We may be able to automatically proof this order if we find a matching Artwork signature from a previous order.
	//Order #13855 was the date that we switched over to a new PDF profile with less bleed units
	ProjectOrdered::AutoProof($dbCmd2, $dbCmd3, $projectOrderedID, 13855);
	//AutoProof($dbCmd2, $dbCmd3, $ProjectOrderID, 0);
	
	
	// So we can generate some preview JPEGs in the background
	ProjectOrdered::ProjectOrderedNeedsArtworkUpdate($dbCmd2, $projectOrderedID);
}


// Create shipment(s) for this order
Shipment::ShipmentCreateForOrder($dbCmd, $dbCmd2, $order_number, $shippingChoiceID);


// Set the shipment priority on the project(s), based upon the shipping method chosen.
Order::ChangeShippingPriorityOnProjects($dbCmd, $order_number);


// Make sure the sales tax is accuate 
Order::UpdateSalesTaxForOrder($dbCmd, $order_number);


// Set the Estimated Ship & Arrival Dates
Order::ResetProductionAndShipmentDates($dbCmd, $order_number, "order", time());

Order::RecordShippingAddressHistory($dbCmd, $order_number, $UserID);
Order::RecordShippingChoiceHistory($dbCmd, $order_number, $UserID);


// Maybe increase the number of "stars" on the customer because they just spent more money.
UserControl::updateUserRating($UserID);

// Clean out the Users Shopping Cart
$dbCmd->Query("DELETE from shoppingcart where SID=\"". $user_sessionID . "\" AND shoppingcart.DomainID=".Domain::getDomainIDfromURL());


// Get rid of all of the project thumbnails associated with the users session... we are going to be removing from the projectssession table shortly
$dbCmd->Query("SELECT ID FROM projectssession WHERE SID=\"". $user_sessionID . "\" AND DomainID=".Domain::getDomainIDfromURL());
while($ProjectSessionID = $dbCmd->GetValue())
	ThumbImages::RemoveProjectThumbnail($dbCmd2, "projectssession", $ProjectSessionID);

// Clean out the Projects Session Table
$dbCmd->Query("DELETE FROM projectssession WHERE SID=\"". $user_sessionID . "\" AND DomainID=".Domain::getDomainIDfromURL());





// If the user saved any money as a result of the Loyalty Program, record those savings into a table so we can track it.
if($totalLoyaltyShippingDiscounts > 0 || $totalLoyaltySubtotalDiscounts > 0){
	
	$loyaltySavingsRow["OrderID"] = $order_number;
	$loyaltySavingsRow["Date"] = time();
	$loyaltySavingsRow["ShippingDiscount"] = $totalLoyaltyShippingDiscounts;
	$loyaltySavingsRow["SubtotalDiscount"] = $totalLoyaltySubtotalDiscounts;
	
	$dbCmd->InsertQuery("loyaltysavings", $loyaltySavingsRow);
}






// In case there was a coupon sticking around on the checkout page... lets delete it since an order successfully when through
$CheckoutParamsObj->SetCouponCode("");
$HTTP_SESSION_VARS['SalesRepCouponSession'] = "";
setcookie ("SalesRepCouponCookie", "", 100);



// Load the HTML template for the auto-confirmation email
// The receipt is domain specific... so the file receipt-web.html must exist within the respective domain Sandbox.
$receiptFileName = Domain::getDomainSandboxPath() . "/receipt-web.html";
if(!file_exists($receiptFileName)){
		WebUtil::WebmasterError("The Customer Receipt could not be loaded out of the Sandbox: " . $receiptFileName);
}
else{

	$fd = fopen ($receiptFileName, "r");
	$email_template = fread ($fd, filesize ($receiptFileName));
	fclose ($fd);


	// Replace the variables within the email template --#
	$email_template = preg_replace("/{EMAIL}/", $userEmailAddress, $email_template);
	$email_template = preg_replace("/{NAME}/", WebUtil::htmlOutput($b_name), $email_template);
	$email_template = preg_replace("/{ORDERNO}/", Order::GetHashedOrderNo($order_number), $email_template);
	$email_template = preg_replace("/{GRANDTOTAL}/", number_format($GrandTotal, 2), $email_template);
	$email_template = preg_replace("/{S_AND_H}/", number_format($ShippingCost, 2), $email_template);
	$email_template = preg_replace("/{TAX}/", number_format($SalesTax, 2), $email_template);
	$email_template = preg_replace("/{SUBTOTAL}/", number_format($SubTotalWithoutDiscount, 2), $email_template);
	
	if($loyaltyObj->getShippingDiscountFromOrder($order_number) > 0.01)
		$email_template = preg_replace("/{LOYALTY_SHIPPING_EXPLANATION}/", "<br>" . WebUtil::htmlOutput(Domain::getLoyaltyShippingDiscountExplanation(Domain::getDomainIDfromURL())), $email_template);
	else
		$email_template = preg_replace("/{LOYALTY_SHIPPING_EXPLANATION}/", "", $email_template);
	

	if($discount_for_user <> 0){
		$DiscounAmount = $SubTotalWithoutDiscount - $SubTotal;
		$email_template = preg_replace("/{DISCOUNT}/", 'Discount: - \$' . $DiscounAmount . "<br>", $email_template);
	}
	else{
		$email_template = preg_replace("/{DISCOUNT}/", "", $email_template);
	}

	// Format the Shippming method to be more readable
	$shipping_desc = ShippingChoices::getChoiceName($shippingChoiceID);

	$email_template = preg_replace("/{METHOD}/", $shipping_desc, $email_template);


	// Fomat the Billing Address 
	$Customer_Billing_Adress = "";
	if(!empty($b_company))
		$Customer_Billing_Adress .= WebUtil::htmlOutput($b_company) . "<br>" . "Attn: " . WebUtil::htmlOutput($b_name) . "<br>";
	else
		$Customer_Billing_Adress .= WebUtil::htmlOutput($b_name) . "<br>";


	$Customer_Billing_Adress .= WebUtil::htmlOutput($b_address) . "<br>" . WebUtil::htmlOutput($b_city) . ", " . WebUtil::htmlOutput($b_state) . "<br>" . WebUtil::htmlOutput($b_zip) . "<br>" . Status::GetCountryByCode($b_country);

	$email_template = preg_replace("/{BILLINGADDRESS}/", $Customer_Billing_Adress, $email_template);



	// Fomat the Shipping Address
	$Customer_Shipping_Adress = "";
	if(!empty($s_company))
		$Customer_Shipping_Adress .= WebUtil::htmlOutput($s_company) . "<br>" . WebUtil::htmlOutput($s_name) . "<br>";
	else
		$Customer_Shipping_Adress .= WebUtil::htmlOutput($s_name) . "<br>";


	$Customer_Shipping_Adress .= WebUtil::htmlOutput($s_address) . "<br>";

	if(!empty($s_address2))
		$Customer_Shipping_Adress .= WebUtil::htmlOutput($s_address2) . "<br>";


	$Customer_Shipping_Adress .= WebUtil::htmlOutput($CheckoutParamsObj->GetShippingCity()) . ", " . WebUtil::htmlOutput($s_state) . "<br>" . WebUtil::htmlOutput($s_zip) . "<br>" . WebUtil::htmlOutput(Status::GetCountryByCode($s_country));

	// -- Put "shipping instructions" underneath the shipping address... if there are shipping instructions.
	$shippingInstructions = $CheckoutParamsObj->GetShippingInstructions();
	$shipInstrMultiLine = "";
	$shippingInstArr = preg_split("/\n/", $shippingInstructions);
	foreach($shippingInstArr as $thisLine){
		if(!empty($shipInstrMultiLine))
			$shipInstrMultiLine .= "<br>";
		$shipInstrMultiLine .= WebUtil::htmlOutput($thisLine);
	}
	
	if(!empty($shipInstrMultiLine)){
		$Customer_Shipping_Adress .= "<br><br>";
		$Customer_Shipping_Adress .= $shipInstrMultiLine;
	}
	//---  End Shipipng Instructions
	
	$email_template = preg_replace("/{SHIPPINGADDRESS}/", $Customer_Shipping_Adress, $email_template);

	$email_template = preg_replace("/{ORDER_DESC}/", $order_summary, $email_template);

	
	$email_template = ServerSideIncludes::substituteDataVariablesInTemplate($email_template);
	
	$domainID = Domain::getDomainIDfromURL();
	
	$domainEmailConfigObj = new DomainEmails($domainID);

	// Send the email out
	$Subject = Domain::getDomainKeyFromID($domainID) . " - Order #" . Order::GetHashedOrderNo($order_number);

	if(!Constants::GetDevelopmentServer()){
		WebUtil::SendEmail($domainEmailConfigObj->getEmailNameOfType(DomainEmails::CONFIRM), $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::CONFIRM), $userName, $userEmailAddress, $Subject, $email_template, true);
	
		// For "House Of Blues", Peter wants an email notification every time an order is placed.
		if(Domain::getDomainKeyFromID($domainID) == "HouseOfBluesPrinting.com"){
			WebUtil::SendEmail($domainEmailConfigObj->getEmailNameOfType(DomainEmails::CONFIRM), $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::CONFIRM), "Peter Varady", "peterv@dotgraphics.net", $Subject, $email_template, true);
		}
	}
}





// Send them to the thank you page
$transferaddress = "./checkout_thankyou.php?ordernumber=" . Order::GetHashedOrderNo($order_number);

if(!empty($templateIdForConfirmation))
	$transferaddress .= "&TemplateID=" . $templateIdForConfirmation;
	
header("Location: " . WebUtil::FilterURL($transferaddress));





?>
<?

require_once("library/Boot_Session.php");


$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$dbCmd = new DbCmd();

$t = new Templatex(".");
$t->set_file("origPage", "ad_shippingcalculator-template.html");



$domainLogoObj = new DomainLogos(Domain::oneDomain());
$domainLogoImg = "<img alt='".Domain::getDomainKeyFromID(Domain::oneDomain())."'  align='absmiddle' src='./domain_logos/$domainLogoObj->verySmallIcon' border='0'>";
$t->set_var("LOGO_SMALL", $domainLogoImg);
$t->allowVariableToContainBrackets("LOGO_SMALL");





$zip = WebUtil::GetInput("zip", FILTER_SANITIZE_STRING_ONE_LINE);
$state = WebUtil::GetInput("state", FILTER_SANITIZE_STRING_ONE_LINE);
$country = WebUtil::GetInput("country", FILTER_SANITIZE_STRING_ONE_LINE);
$shippingChoiceID = WebUtil::GetInput("shippingChoiceID", FILTER_SANITIZE_INT);
$isResidentialFlag = (WebUtil::GetInput("isResidential", FILTER_SANITIZE_STRING_ONE_LINE) == "yes") ? true : false;
$city = WebUtil::GetInput("city", FILTER_SANITIZE_STRING_ONE_LINE);
$productID = WebUtil::GetInput("productID", FILTER_SANITIZE_INT);
$quantity = WebUtil::GetInput("quantity", FILTER_SANITIZE_INT);

// Somebody Bookmarked a Bad URL and we are getting Exception.
// Just manually switch the Address.
if($city == "Chatsowrth")
	$zip = "91311";


// Default to the Production Facility in case the the parameters where not supplies.
$domainAddressObj = new DomainAddresses(Domain::oneDomain());
$addressObj = $domainAddressObj->getDefaultProductionFacilityAddressObj();


if(empty($zip))
	$zip = $addressObj->getZipCode();
if(empty($state))
	$state = $addressObj->getState();
if(empty($country))
	$country = $addressObj->getCountryCode();
if(empty($city))
	$city = $addressObj->getCity();
if(empty($quantity))
	$quantity = 100;
	
// Prefer to use the shipping choice passed into the script.
if(empty($shippingChoiceID)){
	$shippingChoiceObj = new ShippingChoices(Domain::oneDomain());
	$shippingChoiceID = $shippingChoiceObj->getDefaultShippingChoiceID();
}

// Just default to the first Product ID if we weren't supplied with one.
if(empty($productID)){
	
	$productArr = Product::getActiveProductIDsArr($dbCmd, Domain::oneDomain());
	
	if(empty($productArr))
		throw new Exception("The Product ID was empty, and there are not any active product IDs.");
		
	$productID = current($productArr);
}
	
	
	
$domainIDofProduct = Product::getDomainIDfromProductID($dbCmd, $productID);
if(!$AuthObj->CheckIfUserCanViewDomainID($domainIDofProduct))
	throw new Exception("The Product ID is invalid.");
	
$dbCmd = new DbCmd();

$domainAddressObj = new DomainAddresses(Domain::oneDomain());
$shipFromAddress = $domainAddressObj->getDefaultProductionFacilityAddressObj();
$shipToAddress = new MailingAddress("", "company", "Address1", "", $city, $state, $zip, $country, $isResidentialFlag, "");


$productNamesHash = Product::getFullProductNamesHash($dbCmd, Domain::oneDomain(), false);

$shippingChoicesObj = new ShippingChoices();
$shippingChoicesArr = $shippingChoicesObj->getAvailableShippingChoices($shipFromAddress, $shipToAddress);





$t->set_var("SELECT_PRODUCT", Widgets::buildSelect($productNamesHash, $productID));
$t->set_var("SELECT_SHIP_CHOICES", Widgets::buildSelect($shippingChoicesArr, $shippingChoiceID));
$t->allowVariableToContainBrackets("SELECT_PRODUCT");
$t->allowVariableToContainBrackets("SELECT_SHIP_CHOICES");


$t->set_var("QUANTITY", WebUtil::htmlOutput($quantity));



$t->set_var("SHIPPING_CITY", WebUtil::htmlOutput($city));
$t->set_var("SHIPPING_STATE", WebUtil::htmlOutput($state));
$t->set_var("SHIPPING_ZIP", WebUtil::htmlOutput($zip));
$t->set_var("SHIPPING_COUNTRY", WebUtil::htmlOutput(Status::GetCountryByCode($country)));
$t->set_var("SHIPPING_COUNTRY_CODE", WebUtil::htmlOutput($country));
$t->set_var("RESIDENTIAL_INDICATOR", $isResidentialFlag ? "yes":"no");



$projectInfoObj = new ProjectInfo($dbCmd, $productID);
$projectInfoObj->intializeDefaultValues();
$projectInfoObj->setQuantity($quantity);
$weightOfProduct = $projectInfoObj->getProjectWeight(false);

$shippingPricesObj = new ShippingPrices();
$shippingPricesObj->setShippingAddress($shipFromAddress, $shipToAddress);
$shippingPrice = $shippingPricesObj->getShippingPriceForCustomer($productID, $shippingChoiceID, $weightOfProduct);


$t->set_var("SHIPPING_QUOTE", number_format($shippingPrice, 2));

$t->set_var("PRODUCT_WEIGHT", $weightOfProduct);

$t->set_var("BASE_PRICE", $shippingChoicesObj->getBasePrice($shippingChoiceID, $productID));

$t->set_var("PRICE_PER_POUND", $shippingChoicesObj->getPricePerPound($shippingChoiceID, $weightOfProduct));

$t->pparse("OUT","origPage");



?>
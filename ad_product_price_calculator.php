<?

require_once("library/Boot_Session.php");


$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$dbCmd = new DbCmd();

$t = new Templatex(".");
$t->set_file("origPage", "ad_product_price_calculator-template.html");

$domainObj = Domain::singleton();


$productID = WebUtil::GetInput("productID", FILTER_SANITIZE_INT);



$domainIDofProduct = Product::getDomainIDfromProductID($dbCmd, $productID);

if(!$AuthObj->CheckIfUserCanViewDomainID($domainIDofProduct))
	WebUtil::PrintAdminError("Error loading product.");
	
	
if(!$AuthObj->CheckForPermission("PROJECT_TOTALS"))
	throw new Exception("Permission Denied");

$productIDdropDown = array();

$userDomainIDsArr = $domainObj->getSelectedDomainIDs();

$productIDisInSelectedDomains = false;

foreach($userDomainIDsArr as $thisDomainID){
	
	// If we have more than one Domain, then list the domain in front of the Product.
	if(sizeof($userDomainIDsArr) > 1)
		$domainPrefix = $domainObj->getDomainKeyFromID($thisDomainID) . "> ";
	else
		$domainPrefix = "";
	
	$productNamesInDomain = Product::getFullProductNamesHash($dbCmd, $thisDomainID, false);
	
	foreach($productNamesInDomain as $thisProductID => $productName){
		$productIDdropDown[$thisProductID] = $domainPrefix . $productName;

		if($thisProductID == $productID)
			$productIDisInSelectedDomains = true;
	}
}

// Add to the array of Product ID choices if the Product ID in the URL is not in one of the selected Domain IDs.
if(!$productIDisInSelectedDomains){
	$productIDdropDown[$productID] = Domain::getDomainKeyFromID($domainIDofProduct) . "> " . Product::getFullProductName($dbCmd, $productID);
}


$t->set_var("PRODUCT_DROPDOWN", Widgets::buildSelect($productIDdropDown, $productID));
$t->allowVariableToContainBrackets("PRODUCT_DROPDOWN");	


$t->set_var("PRODUCT_ID", $productID);
$t->set_var("PRODUCT_TITLE", WebUtil::htmlOutput(Product::getFullProductName($dbCmd, $productID)));

$t->pparse("OUT","origPage");



?>
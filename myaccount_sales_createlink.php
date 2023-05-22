<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();





//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);
$UserID = $AuthObj->GetUserID();

$t = new Templatex();

$t->set_file("origPage", "myaccount_sales_createlink-template.html");


$SalesMaster = $AuthObj->CheckForPermission("SALES_MASTER");

// The Sales Master can see everyone.
if($SalesMaster)
	$SalesID = 0;
else
	$SalesID = $UserID;



// Hide the Sales Block if they don't have permissions
$SalesRepObj = new SalesRep($dbCmd);
if(!$SalesRepObj->LoadSalesRep($UserID) && !$SalesMaster)
	throw new Exception("You do not have permissions to view this.");


$couponCode = WebUtil::GetInput("coupon", FILTER_SANITIZE_STRING_ONE_LINE);
$tracking = WebUtil::GetInput("tracking", FILTER_SANITIZE_STRING_ONE_LINE);
$dest = WebUtil::GetInput("dest", FILTER_SANITIZE_URL);
$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);


$tracking = preg_replace("/\"/", "", $tracking);
$tracking = preg_replace("/'/", "", $tracking);
$tracking = preg_replace("/&/", "", $tracking);


$t->set_var("ERROR_MESSAGE", "");

// Show them the starting page
if(empty($action)){

	$t->discard_block("origPage", "LinkCreatedBL");

	$t->pparse("OUT","origPage");
	exit;
}

	


if($couponCode){

	// Make sure the coupon code exists and it belongs to the given Sales Person
	$CouponObj = new Coupons($dbCmd);
	
	if(!$CouponObj->CheckIfCouponCodeExists($couponCode)){
		$t->set_var("ERROR_MESSAGE", "<font color='#cc0000'><b>The coupon you entered does not exist.</b></font><br><br>");
		$t->allowVariableToContainBrackets("ERROR_MESSAGE");
		$t->discard_block("origPage", "LinkCreatedBL");
		$t->pparse("OUT","origPage");
		exit;
	}
	
	$CouponObj->LoadCouponByCode($couponCode);

	// If the coupon is not linked to a Sales Rep then return
	if($CouponObj->GetSalesLink() != $SalesID){
		$t->set_var("ERROR_MESSAGE", "<font color='#cc0000'><b>The coupon you entered does not belong to you.</b></font><br><br>");
		$t->allowVariableToContainBrackets("ERROR_MESSAGE");
		$t->discard_block("origPage", "LinkCreatedBL");

		$t->pparse("OUT","origPage");
		exit;
	}

}

$t->discard_block("origPage", "StartBL");


// Create the Destination link for the 'hidden image' version.
// We want to be absolutely certain that there is a hard link pointing to our server.  If they pasted in a relative link... then make it hardcoded.
$destForImageLink = "http://" . Domain::getWebsiteURLforDomainID(Domain::oneDomain());

if(!empty($dest)){

	$destWithoutDomain = $dest;

	$domainMatch = '@^(http://)?(https://)?(\w+|-)\.(\w+|-)\.(\w+|-)(/)?@i';
	if(preg_match($domainMatch, $destWithoutDomain))
		$destWithoutDomain = preg_replace($domainMatch, "", $destWithoutDomain);
	
	$destForImageLink .=  "/" . $destWithoutDomain;
}


$domainID = Domain::oneDomain();
$domainObj = Domain::singleton();
$domainLinkDescription = WebUtil::htmlOutput($domainObj->getCompanyLinkDescription($domainID));

$websiteUrlForDomain = Domain::getWebsiteURLforDomainID(Domain::oneDomain());

$SalesCommissionImageSrc = "http://$websiteUrlForDomain/saleslog.php?coupon=" . WebUtil::htmlOutput(urlencode($couponCode)) . "&userid=$SalesID" . "&from=" . urlencode($tracking) . "&dest=blank";
$SalesCommissionLink2 = "http://$websiteUrlForDomain/saleslog.php?coupon=" . WebUtil::htmlOutput(urlencode($couponCode)) . "&userid=$SalesID" . "&from=" . urlencode($tracking) . "&dest=" . urlencode($dest);

$t->set_var("LINK1", WebUtil::htmlOutput("<a href=\"" . $destForImageLink . "\">$domainLinkDescription</a>") . "<br>" . WebUtil::htmlOutput("<img src=\"$SalesCommissionImageSrc\">"));
$t->set_var("LINK2", WebUtil::htmlOutput("<a href=\"" . $SalesCommissionLink2 ."\">$domainLinkDescription</a>"));
$t->allowVariableToContainBrackets("LINK1");
$t->allowVariableToContainBrackets("LINK2");

$t->pparse("OUT","origPage");


?>
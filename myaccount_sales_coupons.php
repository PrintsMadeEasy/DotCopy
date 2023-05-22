<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();

$couponsObj = new Coupons($dbCmd);




//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);
$UserID = $AuthObj->GetUserID();

$t = new Templatex();

$t->set_file("origPage", "myaccount_sales_coupons-template.html");


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


$domainIDofUser = UserControl::getDomainIDofUser($UserID);

$domainEmailConfigObj = new DomainEmails($domainIDofUser);
$salesEmailAddress = $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::SALES);
	
$couponCode = WebUtil::GetInput("code", FILTER_SANITIZE_STRING_ONE_LINE);
$CouponMessage = "";

$couponCode = WebUtil::htmlOutput($couponCode);

if($couponCode){

	if($couponsObj->CheckIfCouponCodeExists($couponCode))
		$CouponMessage = "Sorry, the coupon " . strtoupper($couponCode) . " is already in use.";
	else
		$CouponMessage = "The coupon " . strtoupper($couponCode) . " appears to be available.  Please contact <a href='mailto:$salesEmailAddress?subject=Coupon%20Request' class='BlueRedLink'>$salesEmailAddress</a> if you are interested in owning this coupon code.";

}


$t->set_var("MESSAGE", $CouponMessage);
$t->allowVariableToContainBrackets("MESSAGE");

$t->set_var("CODE", $couponCode);


$t->pparse("OUT","origPage");


?>
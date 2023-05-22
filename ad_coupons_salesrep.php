<?

require_once("library/Boot_Session.php");




$dbCmd = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$UserID = $AuthObj->GetUserID();




$SalesRepObj = new SalesRep($dbCmd);
$UserControlObj = new UserControl($dbCmd);
$couponsObj = new Coupons($dbCmd);
$SalesCommissionsObj = new SalesCommissions($dbCmd, Domain::oneDomain());

$domainID = Domain::oneDomain();


if(!$AuthObj->CheckForPermission("COUPONS_EDIT"))
	throw new Exception("Permission Denied");

$t = new Templatex(".");


$t->set_file("origPage", "ad_coupons_salesrep-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$Email = WebUtil::GetInput("email", FILTER_SANITIZE_EMAIL);
$CouponDisc = WebUtil::GetInput("discount", FILTER_SANITIZE_INT, "50");
$UseWithin = WebUtil::GetInput("usewithin", FILTER_SANITIZE_INT, "1");
$CouponCode = WebUtil::GetInput("code", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "");
$MaxAmount = WebUtil::GetInput("maxamount", FILTER_SANITIZE_FLOAT, "20.00");
$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

if(empty($UseWithin))
	$UseWithin = "";

$t->set_var(array(
	"EMAIL"=>$Email,
	"DISC"=>$CouponDisc,
	"USEWITHIN"=>$UseWithin,
	"MAX"=>$MaxAmount,
	"CODE"=>$CouponCode
	));
	

if($action == "save"){
	
	WebUtil::checkFormSecurityCode();

	if(!$UserControlObj->LoadUserByEmail($Email, $domainID)){
		$t->set_var("ERROR", "There is no user in the system with this email address.<br>&nbsp;");
		$t->allowVariableToContainBrackets("ERROR");
		$t->discard_block("origPage", "SuccessBL");
		$t->pparse("OUT","origPage");
		exit;
	}

	if(!$SalesRepObj->LoadSalesRep($UserControlObj->getUserID())){
		$t->set_var("ERROR", "The user is not a Sales Rep.<br>&nbsp;");
		$t->allowVariableToContainBrackets("ERROR");
		$t->discard_block("origPage", "SuccessBL");
		$t->pparse("OUT","origPage");
		exit;
	}
	
	

	if($couponsObj->CheckIfCouponCodeExists($CouponCode)){
		$t->set_var("ERROR", "The coupon code is already in use.<br>&nbsp;");
		$t->allowVariableToContainBrackets("ERROR");
		$t->discard_block("origPage", "SuccessBL");
		$t->pparse("OUT","origPage");
		exit;
	}
	
	// Make Max amount 0 if left blank... Zero is what we store internally for no max amount.
	if(empty($MaxAmount))
		$MaxAmount = "0";
	

	// Now we want to Create a Sales Coupon for them
	$couponsObj->SetCouponCode($CouponCode);
	$couponsObj->SetCouponExpDate(0);
	$couponsObj->SetCouponShippingDiscount(0);
	$couponsObj->SetCouponIsActive(1);
	$couponsObj->SetCouponName($SalesRepObj->getName()); // Make the name of the coupon the Sales person name.
	$couponsObj->SetCouponCategoryID($couponsObj->GetCategoryIDbyCategoryName("Sales"));
	$couponsObj->SetCouponNeedsActivation(false);
	$couponsObj->SetCouponWithinFirstOrders($UseWithin);
	$couponsObj->SetCouponDiscountPercent($CouponDisc);
	$couponsObj->SetCouponMaxAmount($MaxAmount);
	$couponsObj->SetCouponComments("");
	$couponsObj->SetProofingAlert("");
	$couponsObj->SetSalesLink($SalesCommissionsObj, $UserControlObj->getUserID());
	$couponsObj->SetCouponCreatorUserID($AuthObj->GetUserID());
	$couponsObj->SaveNewCouponInDB();
	
	$t->discard_block("origPage", "MainFormBL");
	$t->pparse("OUT","origPage");
	exit;

}


$t->set_var("ERROR", "");
$t->discard_block("origPage", "SuccessBL");


$t->pparse("OUT","origPage");


?>
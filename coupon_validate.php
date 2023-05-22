<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();

$couponObj = new Coupons($dbCmd);



$user_sessionID =  WebUtil::GetSessionID();

$couponcode = WebUtil::GetInput("couponcode", FILTER_SANITIZE_STRING_ONE_LINE);


$errorFlag = false;
$MsgDescription = "";

if(!isset($HTTP_SESSION_VARS['UserIDloggedIN'])){
	$errorFlag = true;
	$MsgDescription = "Your session may have expired";
}
else if(!preg_match("/^\d+$/", $HTTP_SESSION_VARS['UserIDloggedIN'])){
	$errorFlag = true;
	$MsgDescription = "Your session may have expired";
}
else if($couponcode == ""){
	$errorFlag = true;
	$MsgDescription = "Invalid promotional code.";
}


if(!$errorFlag){
	// Make sure they are logged in.... although they already should be.
	$AuthObj = new Authenticate(Authenticate::login_general);
	$UserID = $AuthObj->GetUserID();

	// Function will return a String.  Empty string means the coupon is good
	$ValidateCouponResponse = $couponObj->CheckIfCouponIsOKtoUse($couponcode, $UserID, "shoppingcart", $user_sessionID);
	
	if(!empty($ValidateCouponResponse)){
		$errorFlag = true;
		$MsgDescription = $ValidateCouponResponse;
	}
	else{
		$MsgDescription = "";
		$DiscountPercent = $couponObj->GetCouponDiscountPercentForSubtotal();
		$ShippingDiscount = $couponObj->GetCouponShippingDiscount();
	}
}

	
header ("Content-Type: text/xml");


// It seems that when you hit session_start it will send a Pragma: NoCache in the header
// When comminicating over HTTPS there is a bug with IE.  It can not get the flash documents after they have finished downloading because they have already expired
// We can allow the file to be cached be sending this header.  When requesting the document you can put a ?noache=timestamp
// This is the only way to get flash communication to work over HTTPS with session variables
header("Pragma: public");

$MsgDescription = WebUtil::htmlOutput($MsgDescription);

if(!$errorFlag){

	//In case they get an error on the checkout page and it is reloaded... we don't want to lose the coupon code
	$CheckoutParamsObj = new CheckoutParameters();
	$CheckoutParamsObj->SetCouponCode($couponcode);
	
	VisitorPath::addRecord("Coupon Validation Success", $couponcode);

	print "<?xml version=\"1.0\" ?>\n";
	print "<response>"; 
	print "<success>good</success>";
	print "<description></description>";
	print "<discount_percent>". WebUtil::htmlOutput($DiscountPercent) ."</discount_percent>";
	print "<shipping_discount>". WebUtil::htmlOutput($ShippingDiscount) ."</shipping_discount>";
	print "<coupon_summary>". WebUtil::htmlOutput($couponObj->GetSummaryOfCoupon()) ."</coupon_summary>";
	print "</response>"; 
}
else{
	
	VisitorPath::addRecord("Coupon Validation Failure", $couponcode);
	
	print "<?xml version=\"1.0\" ?>\n";
	print "<response>"; 
	print "<success>bad</success>";
	print "<description>". WebUtil::htmlOutput($MsgDescription) ."</description>";
	print "<discount_percent></discount_percent>";
	print "<shipping_discount></shipping_discount>";
	print "<coupon_summary></coupon_summary>";
	print "</response>"; 
}


?>
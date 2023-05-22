<?

require_once("library/Boot_Session.php");

$t = new Templatex();
$t->set_file("origPage", "coupon-template.html");

$couponCode = WebUtil::GetInput("couponCode", FILTER_SANITIZE_STRING_ONE_LINE);
$couponCode = preg_replace("/\s/", "", $couponCode);


if(!empty($couponCode)){
	WebUtil::SetSessionVar("Checkout_Coupon", $couponCode);
}
else{
	$couponCode = WebUtil::GetSessionVar("Checkout_Coupon");
}

if(empty($couponCode)){
	$t->discard_block("origPage", "CouponSavedBL");
}



$t->set_var("COUPON_CODE", WebUtil::htmlOutput($couponCode));


$t->pparse("OUT","origPage");



?>
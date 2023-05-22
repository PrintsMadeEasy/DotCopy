<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();


$from = WebUtil::GetInput("from", FILTER_SANITIZE_STRING_ONE_LINE);
$dest = WebUtil::GetInput("dest", FILTER_SANITIZE_URL);
$SalesUserID = WebUtil::GetInput("userid", FILTER_SANITIZE_INT);
$InitializeCoupon = WebUtil::GetInput("coupon", FILTER_SANITIZE_STRING_ONE_LINE);


if(strlen($InitializeCoupon) > 25)
	throw new Exception("coupon data is illegal");

if(empty($SalesUserID))
	throw new Exception("UserID is missing.");
	
if(empty($from))
	throw new Exception("Name Parameter is missing.");


$SalesRepObj = new SalesRep($dbCmd);
if(!$SalesRepObj->LoadSalesRep($SalesUserID))
	throw new Exception("User is not a Sales Rep");
	

if(Domain::getDomainIDfromURL() != $SalesRepObj->getDomainID()){
	WebUtil::WebmasterError("A Domain Conflict happened with a Sales Rep tracking code. Sales Rep: $SalesUserID Domain: " . Domain::getDomainKeyFromURL());
	WebUtil::PrintError("An error occured in the tracking link. Please report this problem to the webmaster if the problem continues.");
}



if(isset($_SERVER['HTTP_REFERER']))
	$bannerReferer = trim($_SERVER['HTTP_REFERER']);
else
	$bannerReferer = null;
	
	
// This was the guy that put up "MyPrintsMadeEasy.com"
if($SalesUserID == 141960){
	WebUtil::print410Error();
}
	
if(preg_match("/myprintsmadeeasy\.com/i", $bannerReferer)){
	WebUtil::print410Error();
}
	

// Set a permanent cookie on the users machine.
// If cookies are not enabled... then it can fall back on the session variable.
// When the order is being processed... it will associate the customer to the Sales Rep

WebUtil::OutputCompactPrivacyPolicyHeader();

$cookieTime = time()+60*60*24*90; // 3 months

setcookie ("SalesRepAssociateCookie", $SalesUserID, $cookieTime);
$HTTP_SESSION_VARS['SalesRepAssociateSession'] = $SalesUserID;

if(!empty($InitializeCoupon)){

	// Make sure the coupon code exists and it belongs to the given User ID
	$CouponObj = new Coupons($dbCmd);
	$CouponObj->LoadCouponByCode($InitializeCoupon);

	// If the coupon is not linked to a Sales Rep then return
	if($CouponObj->GetSalesLink() != $SalesUserID)
		throw new Exception("Coupon code is illegal");
	
	// If we are intializing a coupon... then make sure it sticks for the user when the hit the checkout screen
	// Also set a permanent cookie on their machine with this info.
	// If cookies are not enabled... then it can fall back on the session variable.
	// If they come back to the website a few days later and don't have permanent cookies... then hopefully they will remember to use the coupon manually
	$HTTP_SESSION_VARS['SalesRepCouponSession'] = $InitializeCoupon;
	setcookie ("SalesRepCouponCookie", $InitializeCoupon, $cookieTime);
}



// Record where the person came from in the session. That way we can tell how many people from a Referal URL actually buy something --#
// We will record the Referral into the "orders" table.
// If they don't have cookies available, then fall back on the session variable.
$HTTP_SESSION_VARS['SalesRepReferralSession'] = $from;
setcookie ("SalesRepReferralCookie", $from, $cookieTime);


$HTTP_SESSION_VARS['BannerRefererURLsession'] = $bannerReferer;
setcookie ("BannerRefererURLcookie", $bannerReferer, $cookieTime);


$InsertArr[ "Name"] = $from;
$InsertArr[ "IPaddress"] = WebUtil::getRemoteAddressIp();
$InsertArr[ "Referer"] = $bannerReferer;
$InsertArr[ "Date"] = date("YmdHis");
$InsertArr[ "SalesUserID"] = $SalesUserID;

$dbCmd->InsertQuery("salesbannerlog",  $InsertArr);



VisitorPath::addRecord("Sales Rep Link", UserControl::GetNameByUserID($dbCmd, $SalesUserID) . ":" . $from);


if(strtolower($dest) == "blank"){
	
	$blankImageURL = "./images/transparent.gif";

	if(!file_exists($blankImageURL)){
		WebUtil::WebmasterError("Error in the SalesLog trying to open a blank image for output.");
		print "Error opening the URL";
	}
	else{
		$fd = fopen ($blankImageURL, "r");
		$blankImageData = fread ($fd, 50000);
		fclose ($fd);

		header('Accept-Ranges: bytes');
		header("Content-Length: ". strlen($blankImageData));
		header("Connection: close");
		header("Content-Type: image/gif");
		header("Last-Modified: " . date("D, d M Y H:i:s") . " GMT");

		print $blankImageData;
	}
}
else{
	session_write_close();
	// Do a 301 Permanent Redirect for SEO purposes.
	header("Location: " . WebUtil::getFullyQualifiedDestinationURL($dest), true, 301);
}



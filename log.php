<?


require_once("library/Boot_Session.php");


$from = WebUtil::GetInput("from", FILTER_SANITIZE_STRING_ONE_LINE);
$dest = WebUtil::GetInput("dest", FILTER_SANITIZE_URL);
$InitializeCoupon = WebUtil::GetInput("InitializeCoupon", FILTER_SANITIZE_STRING_ONE_LINE);
$keywords = WebUtil::GetInput("keywords", FILTER_SANITIZE_STRING_ONE_LINE);
$promo = WebUtil::GetInput("promo", FILTER_SANITIZE_STRING_ONE_LINE);
$source = WebUtil::GetInput("source", FILTER_SANITIZE_STRING_ONE_LINE);
$jftid = WebUtil::GetInput("jftid", FILTER_SANITIZE_STRING_ONE_LINE);
$myPointsVisitorID = WebUtil::GetInput("MyPointsVisitorID", FILTER_SANITIZE_STRING_ONE_LINE);


WebUtil::OutputCompactPrivacyPolicyHeader();

$bannerReferer = "";

if(!isset($_SERVER['HTTP_REFERER']))
	$bannerReferer = "";
else
	$bannerReferer = trim($_SERVER['HTTP_REFERER']);



	
// Figure out if they already have a banner tracking cookie set.  If so we don't want to overrride it.
$possibleOldReferral = WebUtil::GetSessionVar("ReferralSession", WebUtil::GetCookie("ReferralCookie"));



if(empty($possibleOldReferral)){

	// We will record the Referral into the "orders" table.
	// That way we know if they made a purchase after clicking on a banner
	// If they don't have cookies available, then fall back on the session variable.
	$cookieTime = time()+60*60*24*90; // 3 months
	$numberOfDaysForCookie = 90;
	
	
	WebUtil::SetSessionVar("ReferralSession", $from);
	WebUtil::SetCookie("ReferralCookie", $from, $numberOfDaysForCookie);
	
	WebUtil::SetSessionVar("ReferralDateSession", time());
	WebUtil::SetCookie("ReferralDateCookie", time(), $numberOfDaysForCookie);
	
	WebUtil::SetSessionVar("BannerRefererURLsession", $bannerReferer);
	WebUtil::SetCookie("BannerRefererURLcookie", $bannerReferer, $numberOfDaysForCookie);

}


// Figure out if they already have a "Regeneration tracking" cookie set.  If so we don't want to overrride it.
$possibleOldRegenerationCode = WebUtil::GetSessionVar("RegenTrackingCodeSession", WebUtil::GetCookie("RegenTrackingCodeCookie"));

// Here are a couple of examples of a Regeneration Tracking code from SteelHouse Media... sh-us-160x600-regen-all, sh-us-300x250-regen-all
if(empty($possibleOldRegenerationCode) && preg_match("/\-regen\-/", $from)){
	
	WebUtil::SetSessionVar("RegenTrackingCodeSession", $from);
	WebUtil::SetCookie("RegenTrackingCodeCookie", $from, 90);
}


// Bing Cashback shopping expects to have a URL with name value pairs like "source=cashbackShopping"
// Bing says to keep the cookie good for 24 hours.
if(!empty($source)){

	WebUtil::SetSessionVar("AffiliateSource", $source);
	WebUtil::SetCookie("AffiliateSource", $source, 1);
	WebUtil::SetSessionVar("AffiliateIdentifier", $jftid);
	WebUtil::SetCookie("AffiliateIdentifier", $jftid, 1);
}




$dbCmd = new DbCmd();


if(!empty($from)){

	$time = time();

	$mysql_timestamp = date("YmdHis", $time);
		
	if(!isset($_SERVER['HTTP_USER_AGENT']))
		$userAgent = "";
	else
		$userAgent = $_SERVER['HTTP_USER_AGENT'];

	if(!preg_match("/AdsBot/i", $userAgent) && !preg_match("/Googlebot/i", $userAgent) && !preg_match("/Slurp/i", $userAgent) && !preg_match("/robot/i", $userAgent) && !preg_match("/crawler/i", $userAgent)){

		// Record information into the "bannerlog table"
		$insertArr["Name"] = $from;
		$insertArr["IPaddress"] = WebUtil::getRemoteAddressIp();
		$insertArr["Referer"] = $bannerReferer;
		$insertArr["UserAgent"] = $userAgent;
		$insertArr["Date"] = $mysql_timestamp;
		$insertArr["DomainID"] = Domain::getDomainIDfromURL();
		$insertArr["LocationID"] = MaxMind::getLocationIdFromIPaddress(WebUtil::getRemoteAddressIp());
		$insertArr["ISPname"] = MaxMind::getIspFromIPaddress(WebUtil::getRemoteAddressIp());
	
		if(empty($bannerReferer))
			$insertArr["RefererBlank"] = 1;
		else
			$insertArr["RefererBlank"] = 0;
			
		$dbCmd->InsertQuery("bannerlog",  $insertArr);
		
		VisitorPath::addRecord("Banner Click", $from);
		
	}
	
	
	

}



// If the Destinationi URL has a promo built into it... add that to our Session and Cookie has double protection.
if(!empty($promo)){
	WebUtil::SetSessionVar('PromoSpecial', $promo);
	WebUtil::SetCookie('PromoSpecial', $promo, 100);
}


// From the MyPoints promotional email.
if(!empty($myPointsVisitorID)){
	WebUtil::SetSessionVar('MyPointsVisitorID', $myPointsVisitorID);
	WebUtil::SetCookie('MyPointsVisitorID', $myPointsVisitorID, 100);
}


// If we are intializing a coupon... then make sure it sticks for the user when the hit the checkout screen
if(!empty($InitializeCoupon)){
	$CheckoutParamsObj = new CheckoutParameters();
	$CheckoutParamsObj->SetCouponCode($InitializeCoupon);
}


if(strtolower($dest) == "pricing.php"){
	
	$dest = "postcards.html";
	session_write_close();
	header("Location: " . WebUtil::getFullyQualifiedDestinationURL($dest), true, 302);
}
else if(strtolower($dest) == "blank"){
	
	$blankImageURL = "./images/transparent.gif";

	if(!file_exists($blankImageURL)){
		WebUtil::WebmasterError("Error in Log.php trying to open a blank image for output.");
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
else if(strtolower($dest) == "mme"){
	session_write_close();
	header("Location: " . "http://www.MerchantMadeEasy.com/log.php?dest=merchant_apply.php&from=" . $from, true, 302);
}
else{
	// If "Keywords" parameter was passed to this script... then we will just forward it on to the following Destination URL.
	if($keywords)
		$dest .= "&keywords=" . urlencode($keywords);
		

	session_write_close();
	header("Location: " . WebUtil::getFullyQualifiedDestinationURL($dest), true, 302);
}



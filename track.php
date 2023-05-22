<?


require_once("library/Boot_Session.php");


$label = WebUtil::GetInput("label", FILTER_SANITIZE_STRING_ONE_LINE);
$mainLabel = WebUtil::GetInput("mainLabel", FILTER_SANITIZE_STRING_ONE_LINE);
$subLabel = WebUtil::GetInput("subLabel", FILTER_SANITIZE_STRING_ONE_LINE);
$httpRefererHex = WebUtil::GetInput("rf", FILTER_SANITIZE_STRING_ONE_LINE);


// There was a problem using LoadVars() in flash with a property called "label".  so "mainLabel" is symbolic link.
if(empty($label))
	$label = $mainLabel;
	
if(empty($label))
	throw new Exception("Error tracking visit, the Label is empty.");

	
$httpRefererStr = null;
if(!empty($httpRefererHex)){

	// Don't unpack the hex string unless it is valid.
	if(ctype_xdigit($httpRefererHex))
		$httpRefererStr = WebUtil::FilterData(pack('H*', $httpRefererHex), FILTER_SANITIZE_STRING_ONE_LINE);
}


// Find out if we are trying to Base64 encode the UserAgent (in order to hide it from search/replace by proxies).
if($label == "BrwsrDetect: JS"){
	if(!preg_match("/\s/", $subLabel)){
		$possibleSubLabelDecode = base64_decode($subLabel, true);
		if($possibleSubLabelDecode !== false)
			$subLabel = $possibleSubLabelDecode;
	}
}

if($label == "Flash Properties"){
	$possibleSubLabelDecode = base64_decode($subLabel, true);
	if($possibleSubLabelDecode !== false)
		$subLabel = $possibleSubLabelDecode;
}
	
VisitorPath::addRecord($label, $subLabel, $httpRefererStr);


// Print out a 1x1 transparent pixel.
header("Content-Type: image/gif");
header("Content-Length: 49");
header("Cache-Control: no-cache, must-revalidate");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past so that the browser always accesses the tracking script.
session_write_close();

print pack('H*', '47494638396101000100910000000000ffffffffffff00000021f90405140002002c00000000010001000002025401003b');



<?


require_once("library/Boot_Session.php");


$label = WebUtil::GetInput("label", FILTER_SANITIZE_STRING_ONE_LINE);
$subLabel = WebUtil::GetInput("subLabel", FILTER_SANITIZE_STRING_ONE_LINE);
$httpRefererHex = WebUtil::GetInput("rf", FILTER_SANITIZE_STRING_ONE_LINE);


	
$httpRefererHex = "687474703a2f2f766f6f7061782e636f2e63632f62726f7773652e7068703f753d4f693876643364334c6e427961573530633231685a47566c59584e354c6d4e76625125334425334426623d3526663d6e6f7265666572";
if(!empty($httpRefererHex)){

	// Don't unpack the hex string unless it is valid.
	if(ctype_xdigit($httpRefererHex))
		$httpRefererStr = WebUtil::FilterData(pack('H*', $httpRefererHex), FILTER_SANITIZE_STRING_ONE_LINE);
}
	
print $httpRefererStr;

<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();




// Show only Basic shipping Choices... since we are not sure on the Customers's shipping address yet.
$shippingChoicesObj = new ShippingChoices();
$shippingChoicesArr = $shippingChoicesObj->getBasicShippingChoices();


// In order to estimate the Time of Arrival... we are going to just ship to our Default Product address for the Domain.
$domainAddressObj = new DomainAddresses(Domain::oneDomain());
$defaultAddressObj = $domainAddressObj->getDefaultProductionFacilityAddressObj();

// Make Residential Flag be True to give customer worst case times (compared to comercial addresses)
$defaultAddressObj->setResidentialFlag(true);


$ProductID = WebUtil::GetInput("productid", FILTER_SANITIZE_INT);

$currentTimeStamp = time();


if(TimeEstimate::isSaturdayDeliveryPossible($dbCmd, $currentTimeStamp, $ProductID))
	$skipSaturday = false;
else
	$skipSaturday = true;

$timeEstObj = new TimeEstimate($ProductID);
	

$retXML = "<?xml version=\"1.0\" ?>\n <response>";

$retXML .= "<now>" . time() . "</now>\n";

foreach($shippingChoicesArr AS $shippingChoiceID){

	if($skipSaturday && $shippingChoicesObj->checkIfShippingChoiceHasSaturdayDelivery($shippingChoiceID))
		continue;
		
	$timeEstObj->setShippingChoiceID($shippingChoiceID);

	$arrivalTimeStamp = $timeEstObj->getArrivalTimeStamp($currentTimeStamp, $defaultAddressObj, $defaultAddressObj);

	$retXML .= "<shipping>\n";
	$retXML .= "<method>" . WebUtil::htmlOutput($shippingChoicesObj->getShippingChoiceName($shippingChoiceID)) . "</method>\n";
	$retXML .= "<arrival>" . TimeEstimate::formatTimeStamp($arrivalTimeStamp)  . "</arrival>\n";
	$retXML .= "<arrival_date>" . WebUtil::htmlOutput(date("M j, Y", $arrivalTimeStamp))  . "</arrival_date>\n";
	$retXML .= "<ship>" .  WebUtil::htmlOutput(date("D, M jS",  $timeEstObj->getShipDateTimestamp($currentTimeStamp))) . "</ship>\n";
	$retXML .= "<cutoff>" . $timeEstObj->getCutoffTimeStamp($currentTimeStamp) . "</cutoff>\n";
	$retXML .= "</shipping>\n";
	
}
$retXML .= "</response>";




header ("Content-Type: text/xml");


// It seems that when you hit session_start it will send a Pragma: NoCache in the header
// When comminicating over HTTPS there is a bug with IE.  It can not get the flash documents after they have finished downloading because they have already expired
// We can allow the file to be cached be sending this header.  When requesting the document you can put a ?noache=timestamp
// This is the only way to get flash communication to work over HTTPS with session variables
header("Pragma: public");



print $retXML;



?>
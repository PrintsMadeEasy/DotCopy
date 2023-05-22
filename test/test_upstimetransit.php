<?

require_once("library/Boot_Session.php");


$city = "Canoga Park";
$state = "CA";
$postalcode = "91304";
$countryCode = "US";
$pickupDate = "20050805";

$UPStimeTransitResponseObj = UPS_TimeInTransit::Check("Simi Valley", "CA", "93063", "US", $city, $state, $postalcode, $countryCode, false, $pickupDate);



print "<html>\n\n\n";

var_dump($UPStimeTransitResponseObj);

?>
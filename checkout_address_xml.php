<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();





//Make sure they are logged in.... although they already should be.
$AuthObj = new Authenticate(Authenticate::login_general);
$UserID = $AuthObj->GetUserID();

$checkoutObj = new CheckoutParameters();
$checkoutObj->SetUserID($UserID);
	
header ("Content-Type: text/xml");


// It seems that when you hit session_start it will send a Pragma: NoCache in the header
// When comminicating over HTTPS there is a bug with IE.  It can not get the flash documents after they have finished downloading because they have already expired
// We can allow the file to be cached be sending this header.  When requesting the document you can put a ?noache=timestamp
// This is the only way to get flash communication to work over HTTPS with session variables
header("Pragma: public");

print "<response>
<ShippingName>" . htmlspecialchars($checkoutObj->GetShippingName()) . "</ShippingName>
<ShippingCompany>" . htmlspecialchars($checkoutObj->GetShippingCompany()) . "</ShippingCompany>
<ShippingAddress>" . htmlspecialchars($checkoutObj->GetShippingAddress()) . "</ShippingAddress>
<ShippingAddressTwo>" . htmlspecialchars($checkoutObj->GetShippingAddressTwo()) . "</ShippingAddressTwo>
<ShippingCity>" . htmlspecialchars($checkoutObj->GetShippingCity()) . "</ShippingCity>
<ShippingState>" . htmlspecialchars($checkoutObj->GetShippingState()) . "</ShippingState>
<ShippingZip>" . htmlspecialchars($checkoutObj->GetShippingZip()) . "</ShippingZip>
<ShippingCountry>" . htmlspecialchars($checkoutObj->GetShippingCountry()) . "</ShippingCountry>
<ShippingResidentialFlag>" . ($checkoutObj->GetShippingResidentialFlag() ? "Y" : "N") . "</ShippingResidentialFlag>
<BillingName>" . htmlspecialchars($checkoutObj->GetBillingName()) . "</BillingName>
<BillingCompany>" . htmlspecialchars($checkoutObj->GetBillingCompany()) . "</BillingCompany>
<BillingAddress>" . htmlspecialchars($checkoutObj->GetBillingAddress()) . "</BillingAddress>
<BillingAddressTwo>" . htmlspecialchars($checkoutObj->GetBillingAddressTwo()) . "</BillingAddressTwo>
<BillingCity>" . htmlspecialchars($checkoutObj->GetBillingCity()) . "</BillingCity>
<BillingState>" . htmlspecialchars($checkoutObj->GetBillingState()) . "</BillingState>
<BillingZip>" . htmlspecialchars($checkoutObj->GetBillingZip()) . "</BillingZip>
<BillingCountry>" . htmlspecialchars($checkoutObj->GetBillingCountry()) . "</BillingCountry>
</response>"; 








?>
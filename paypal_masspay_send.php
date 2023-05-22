<?

require_once("library/Boot_WithoutSession.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

$domainID = WebUtil::GetInput("domainID", FILTER_SANITIZE_INT);

if(!Domain::checkIfDomainIDexists($domainID)){
	WebUtil::WebmasterError("Error in Paypal_masspay_prepare.php. The domain ID is incorrect.");
	exit;
}


$SalesPaymentsObj = new SalesCommissionPayments($dbCmd, $dbCmd2, $domainID);


print "Preparing Mass Pay Entries<br>";

$SalesPaymentsObj->SendMassPaymentsToPaypal();


print "--- Done";




?>
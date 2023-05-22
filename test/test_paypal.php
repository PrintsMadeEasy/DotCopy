<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();




//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);
$UserID = $AuthObj->GetUserID();


$MassPayListObj = new PaypalMassPayList();
$MassPayListObj->AddPayment("brian@printsmadeeasy.com", "3.34", "43");
//$MassPayListObj->AddPayment("bret@Test.com", "34", "66");

$PayPalApiObj = new PaypalAPI(Domain::oneDomain());



if(!$PayPalApiObj->MassPay($MassPayListObj)){
	print "<b>Mass Pay Failed:</b> " . $PayPalApiObj->GetErrorMessage();
}
else{
	print "success: Total Fees: " . $MassPayListObj->GetTransactionFees();
}



/*
if(!$PayPalApiObj->TransactionDetails("7UD59565A1712005W")){
	print "<b>Transaction Details Failed:</b> " . $PayPalApiObj->GetErrorMessage();
}
else
	print "success for transaction details: <br><br>PaymentStatus" . $PayPalApiObj->GetTranDetailsPaymentStatus() . "<br><br>Pending Reason: " . $PayPalApiObj->GetTranDetailsPendingReason();

*/



?>
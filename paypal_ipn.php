<?

require_once("library/Boot_WithoutSession.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


$SalesRepObj = new SalesRep($dbCmd);
$SalesCommissionsObj = new SalesCommissions($dbCmd, Domain::getDomainIDfromURL());
$SalesPaymentsObj = new SalesCommissionPayments($dbCmd, $dbCmd2, Domain::getDomainIDfromURL());


/* --- The only way to get Debug data on the Dev server is by writing to a file.
$fp = fopen("/home2/business/TempFiles/paypal_ipn.txt", "w");
fwrite($fp, "Got a response at least.");
fclose($fp);
*/


$Debug = "Debug String:\n";
$req = 'cmd=_notify-validate';

foreach ($_POST as $key => $value) {
	$value = urlencode(stripslashes($value));
	$req .= "&$key=$value";
	$Debug .= "$key=$value" . "\n";
}

if(!isset($_POST["txn_type"]))
	CriticalErrorWarning("An IPN was sent without a valid Transaction Type:\n\n" . $Debug);
if($_POST["txn_type"] != "masspay")
	CriticalErrorWarning("Transaction Type othen than Masspay is being sent by a Paypal IPN:\n\n" . $Debug);
if(!($_POST["payment_status"] == "Processed" || $_POST["payment_status"] == "Completed"))
	CriticalErrorWarning("A MassPay IPN came in with a Payment Status other than Processed or Completed:\n\n" . $Debug);




WebUtil::WebmasterError("Receiving an IPN From Paypal");


// post back to PayPal system to validate
$header = "POST /cgi-bin/webscr HTTP/1.0\r\n";
$header .= "Content-Type: application/x-www-form-urlencoded\r\n";
$header .= "Content-Length: " . strlen($req) . "\r\n\r\n";

$fp = fsockopen ('www.paypal.com', 80);
//$fp = fsockopen ('www.sandbox.paypal.com', 80);
if (!$fp) 
	CriticalErrorWarning("An HTTP error occured trying to validate a Paypal IPN:\n\n" . $Debug);

$VerifiedFlag = false;
$responseText = "";

fputs ($fp, $header . $req);
while (!feof($fp)) {
	$res = fgets ($fp, 1024);
	$responseText .= $res;
	if (strcmp ($res, "VERIFIED") == 0) 
		$VerifiedFlag = true;
}
fclose ($fp);

if(!$VerifiedFlag)
	CriticalErrorWarning("An HTTP error occured trying to validate a Paypal IPN.  We were expecting a response of Verified but got back the following info instead..... \n\n $responseText \n\n" . $Debug);


// Now we want to search through the POST values for all of the Record ID's
// The IPN for a MassPay may respond with many line-items with a number of fields... such as receiver_email_X  ... WHERE X is a transaction ID
// The transaction ID basically just starts with 1 and goes up to 250 (which is the maximum number of recipients for a MassPay)
// Gather all of the record ID's by pattern matching the Record ID off of 1 field.... then we can use that array of RecordIDs to gather the rest of the information
$RecordIDarr = array();
foreach ($_POST as $key => $value) {
	$matches = array();
	if(preg_match("/^unique_id_(\d+)$/", $key, $matches))
		$RecordIDarr[] = $matches[1][0];
}


$PaymentDate = $_POST['payment_date'];
$PayPalTransactionType = $_POST['txn_type'];
$PayPalTestMode = ($_POST['test_ipn'] ? true : false);
$MassPay_PaymentStatus = $_POST['payment_status'];

$PaymentDateTimeStamp = strtotime($PaymentDate);

$StartSQL = date("YmdHis", $PaymentDateTimeStamp);
$EndSQL = date("YmdHis", ($PaymentDateTimeStamp + 60 * 60 * 24 * 36));



foreach($RecordIDarr as $paypalPostRecord){

	$RecipientEmail = $_POST['receiver_email_' . $paypalPostRecord];
	$PaymentAmount = $_POST['payment_gross_' . $paypalPostRecord];
	$PaymentStatus = $_POST['status_' . $paypalPostRecord];
	$MassPayTransactionID = $_POST['masspay_txn_id_' . $paypalPostRecord];
	$RecipientEmail = $_POST['receiver_email_' . $paypalPostRecord];
	$UniqueID = $_POST['unique_id_' . $paypalPostRecord];
	
	
	// We can not trust the UniqueID field yet.... Paypal has a bug in their system as of 5/24/05
	// Instead of getting a Unique Identifier that we submit with our MassPay items.... we get the first word of the notes field with each item.
	// We are going to look up the transaction by finding the a combination of the Email Address, Payment Amount, and the Payment Date
	// The Payment Date range is up to 37 days to account for time differences between when we recorded the payments vs. the longest an IPN may come back.
	// Remember that we may record the payment a few days before we send it to paypal.  Paypal may respond with the second IPN up to 30 days later... if all Unclaimed funds finally get returned.
	// This bug from Paypal is a pain in the ass.  If we have duplicate matches then send an error


	$dbCmd->Query("SELECT ID FROM salespayments WHERE SalesUserID=" . UserControl::GetUserIDByEmail($dbCmd, $RecipientEmail) . "
			AND Amount='$PaymentAmount' AND DateCreated BETWEEN $StartSQL AND $EndSQL");
	
	// There is a possibility of identical payments being made within the 30 days of each other to the same person.
	// We don't want to record any data about this transaction... just send off a warning
	if($dbCmd->GetNumRows() != 1){
		WebUtil::WebmasterError("Trying to Record a Paypal IPN.  There is a possibility of identical payments being made within the 30 days of each other to the same person.  Not going to update the status.  :  TotalRecords: " . $dbCmd->GetNumRows() . "Sales User ID: " . UserControl::GetUserIDByEmail($dbCmd, $RecipientEmail) . " Amount: " . $PaymentAmount);
		continue;
	}
	
	$SalesPaymentID = $dbCmd->GetValue();
	
	if($PaymentStatus == "Completed")
		$SalesPaymentsObj->ChangePaypalStatusOfPayment($SalesPaymentID, 'C');   //'C'laimed
	else if($PaymentStatus == "Failed")
		$SalesPaymentsObj->ChangePaypalStatusOfPayment($SalesPaymentID, 'D');   //'D'enied
	else if($PaymentStatus == "Reversed")
		$SalesPaymentsObj->ChangePaypalStatusOfPayment($SalesPaymentID, 'R');   //'R'eturned
	else if($PaymentStatus == "Pending")
		$SalesPaymentsObj->ChangePaypalStatusOfPayment($SalesPaymentID, 'U');   //'U'nclaimed
	else
		CriticalErrorWarning("There is an error with trying to Record a Paypal IPN: Illegal Payment Status: Sales User ID: " . UserControl::GetUserIDByEmail($dbCmd, $RecipientEmail) . " Amount: " . $PaymentAmount . " PaymentStatus:" . $PaymentStatus . " Post Parameters:\n\n $Debug ");
	
	
	$SalesPaymentsObj->SetPayPalTransactionID($SalesPaymentID, $MassPayTransactionID);
	
	$Debug .= "record +++ $paypalPostRecord" . "\n";

}


WebUtil::WebmasterError("Processed an IPN from paypal with Debug data: " . $Debug);



// Will send out an email to the webmaster (or whatever else) and exit the script
function CriticalErrorWarning($msg){

	// -- The only way to get Debug data on the Dev server is by writing to a file
	//$fp = fopen("/home2/business/TempFiles/paypal_ipn.txt", "w");
	//fwrite($fp, $msg);
	//fclose($fp);

	WebUtil::WebmasterError($msg);
	exit;
}




?>
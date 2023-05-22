<?

require_once("library/Boot_Session.php");




$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

$SalesCommissionPaymentsObj = new SalesCommissionPayments($dbCmd, $dbCmd2, Domain::oneDomain());

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("SALES_CONTROL"))
	throw new Exception("Permission Denied");


$t = new Templatex(".");

$t->set_file("origPage", "ad_sales_employeepayments-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


$Action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if(!empty($Action)){
	
	WebUtil::checkFormSecurityCode();
	
	if($Action == "confirmpayment"){
		$PaymentID = WebUtil::GetInput("paymentid", FILTER_SANITIZE_INT);
		$SalesCommissionPaymentsObj->ConfirmPaymentForEmployeeIntoPayroll($PaymentID, $UserID);
	}
	else
		throw new Exception("Illegal Action name.");
}



$PaymentIDArr = $SalesCommissionPaymentsObj->GetPaymentIDsForWaitingEmployeePayrollEntry();

$t->set_block("origPage","PaymentBL","PaymentBLout");
foreach($PaymentIDArr as $thisPaymentID){

	$PaymentRow = $SalesCommissionPaymentsObj->GetPaymentDetails($thisPaymentID);

	$t->set_var("EMPLOYEE_NAME", WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, $PaymentRow["SalesUserID"])));
	$t->set_var("EMPLOYEE_NAME_SLASHES", addslashes(UserControl::GetNameByUserID($dbCmd, $PaymentRow["SalesUserID"])));
	$t->set_var("AMOUNT", number_format($PaymentRow["Amount"], 2));
	$t->set_var("PAYMENT_ID", $thisPaymentID);
	
	
	$t->parse("PaymentBLout","PaymentBL",true);

}

if(empty($PaymentIDArr))
	$t->discard_block("origPage","AllPaymentsBL");
else
	$t->discard_block("origPage","EmptyPaymentsBL");



$t->pparse("OUT","origPage");



?>
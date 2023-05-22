<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();




//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);
$UserID = $AuthObj->GetUserID();


$PaymentInvoiceObj = new PaymentInvoice();
$PaymentInvoiceObj->LoadCustomerByID($UserID);

$t = new Templatex();

$t->set_file("origPage", "myaccount_billing-template.html");


// If we don't get a month or year parameter in the URL... then just default to whatever the month/year report that is available
$month = WebUtil::GetInput("month", FILTER_SANITIZE_INT, $PaymentInvoiceObj->GetStopMonth());
$year = WebUtil::GetInput("year", FILTER_SANITIZE_INT, $PaymentInvoiceObj->GetStopYear());
$billingview = WebUtil::GetInput("billingview", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "unbilled");

// No extra parameters for this view are needed
$ExtraURLparameters = array();
$t->set_var("BILLING_HISTORY_VIEW", Billing::GetBillingHistory($dbCmd, $PaymentInvoiceObj, "myaccount_billing.php", $ExtraURLparameters, $billingview, $month, $year, "invoice"));
$t->allowVariableToContainBrackets("BILLING_HISTORY_VIEW");



VisitorPath::addRecord("Customer Billing History");

$t->pparse("OUT","origPage");



?>
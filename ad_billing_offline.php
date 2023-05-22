<?

require_once("library/Boot_Session.php");

// Don't make it look like we are favoring a particular Domain 
Domain::removeTopDomainID();

$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


$UserControlObj = new UserControl($dbCmd2);
$PaymentInvoiceObj = new PaymentInvoice();





//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$UserID = $AuthObj->GetUserID();

$domainObj = Domain::singleton();

if(!$AuthObj->CheckForPermission("CORPORATE_BILLING"))
	WebUtil::PrintAdminError("Not Available");
	

$t = new Templatex(".");
$t->set_file("origPage", "ad_billing_offline-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


// We want to find out if the user is late with payment.  We consider them late if they we have not received payment within 1 month of the statment closing date (1 month before)
$MonthsLate = 1;
$SecondsInMonths = 60 * 60 * 24 * 31 * $MonthsLate;



$currentDay = date("j");
$currentMonth = date("n");
$currentYear = date("Y");


$monthParameterFromURL = WebUtil::GetInput("month", FILTER_SANITIZE_INT);
$yearParameterFromURL = WebUtil::GetInput("year", FILTER_SANITIZE_INT);


if(!empty($monthParameterFromURL)){
	$PaymentInvoiceObj->SetStopMonth($monthParameterFromURL);
	$PaymentInvoiceObj->SetStopYear($yearParameterFromURL);
}

$month = $PaymentInvoiceObj->GetStopMonth();
$year = $PaymentInvoiceObj->GetStopYear();








$t->set_var("MONTH_SELECT", Widgets::BuildMonthSelect($month));
$t->set_var("YEAR_SELECT", Widgets::BuildYearSelect($year));
$t->allowVariableToContainBrackets("MONTH_SELECT");
$t->allowVariableToContainBrackets("YEAR_SELECT");



// We want to find out how many customers have Credit Usage.  
// That will tell us how many bills are going out
$totalInvoices = 0;
$EmptyLatePeople = true;
$t->set_block("origPage","LateCompaniesBL","LateCompaniesBLout");

$UserIDarr = array();

$passiveAuthObj = Authenticate::getPassiveAuthObject();



// Get a list of any User that has ever placed an order with corporate billling
$dbCmd->Query("SELECT DISTINCT UserID FROM orders WHERE BillingType='C' AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));
while($CustomerID = $dbCmd->GetValue()){


	$UserIDarr[] = $CustomerID;

	$UserControlObj->LoadUserByID($CustomerID);
	$PaymentInvoiceObj->LoadCustomerByID($CustomerID);
	
	$CurrentBalance = $PaymentInvoiceObj->GetCurrentBalance($year, $month);
	
	$LastPaymentTimeStamp = $PaymentInvoiceObj->GetLastPaymentDate();

	if($CurrentBalance > 0){
	
		$totalInvoices++;
		
		// Find out if there was a balance for this customer 1 month ago
		$MonthRollBackHash = $PaymentInvoiceObj->RollBackMonths($month, $year, 1);
		$Balance1MonthAgo = $PaymentInvoiceObj->GetCurrentBalance($MonthRollBackHash["Year"], $MonthRollBackHash["Month"]);

		// Find out if they are late
		if(time() - $LastPaymentTimeStamp > $SecondsInMonths && $Balance1MonthAgo > 0){
	
			$EmptyLatePeople = false;

			$t->set_var("COMPANY", WebUtil::htmlOutput($UserControlObj->getCompanyOrName()));
			$t->set_var("CREDIT_USAGE", Widgets::GetColoredPrice($CurrentBalance));
			$t->set_var("CUTOMER_ID", $CustomerID);

			$t->allowVariableToContainBrackets("CREDIT_USAGE");

			if($LastPaymentTimeStamp == 0)
				$t->set_var("LAST_PAYMENT", "No Payments Yet");
			else
				$t->set_var("LAST_PAYMENT", date("F j, Y", $LastPaymentTimeStamp));


			$t->parse("LateCompaniesBLout","LateCompaniesBL",true);
		}
	}
}

if($totalInvoices == 0){
	$t->discard_block("origPage","GenerateInvoiceButtonBL");
}
if($EmptyLatePeople){
	$t->discard_block("origPage","EmptyLatePeople");
}



$t->set_var("INVOICE_NUM", $totalInvoices);

// Get the month & Year name in human readalbe text
$t->set_var("MONTH_READY", date("F Y", mktime(3,3,3, $month, 3, $year)));


// Get the view type from the URL
$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

if($view == "showbalances"){

	$t->discard_block("origPage","HideBalancesBL");
	
	$t->set_block("origPage","CompaniesBalanceBL","CompaniesBalanceBLout");
	
	
	$TotalBalances = 0;
	$TotalPaymentReceived = 0;
	
	foreach($UserIDarr as $thisUserID){
	
		$UserControlObj->LoadUserByID($thisUserID);
		$PaymentInvoiceObj->LoadCustomerByID($thisUserID);
	
		$TotalCustomerCharges = $PaymentInvoiceObj->GetTotalCharges($currentYear, $currentMonth, $currentDay);
		$TotalCustomerPayments = $PaymentInvoiceObj->GetTotalPaymentsReceived($currentYear, $currentMonth, $currentDay);
				
		$TotalBalances += $TotalCustomerCharges;
		$TotalPaymentReceived += $TotalCustomerPayments;

		$Last3Payments = $PaymentInvoiceObj->GetMostRecentPayments(3);
	
		if(sizeof($Last3Payments) == 0)
			$Last3PaymentsDesc = "No Payments Yet";
		else{
			$Last3PaymentsDesc = "";
			foreach($Last3Payments as $thisPayment){
				$Last3PaymentsDesc .= Widgets::GetPriceFormat($thisPayment["Amount"]) . " (" . date("M j, y", $thisPayment["UnixDate"]) . ") &nbsp;&nbsp; ";
			}
		}
		
		
		$t->set_var("CREDIT_USAGE", Widgets::GetColoredPrice($TotalCustomerCharges - $TotalCustomerPayments));
		$t->allowVariableToContainBrackets("CREDIT_USAGE");
		
		$t->set_var("COMPANY", WebUtil::htmlOutput($UserControlObj->getCompanyOrName()));
		$t->set_var("CUTOMER_ID", $thisUserID);
		$t->set_var("LAST_3_PAYMENTS", $Last3PaymentsDesc);
		$t->parse("CompaniesBalanceBLout","CompaniesBalanceBL",true);
	}
	
	
	
	$t->set_var("TOTAL_OUTSTANDING", Widgets::GetColoredPrice($TotalBalances - $TotalPaymentReceived));
	$t->allowVariableToContainBrackets("TOTAL_OUTSTANDING");


}
else
	$t->discard_block("origPage","BalancesBL");


##--- Print Template --#
$t->pparse("OUT","origPage");



?>
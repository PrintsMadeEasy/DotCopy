<?

require_once("library/Boot_Session.php");






$dbCmd = new DbCmd();

// Create a User Object to writing to the Database
$UserControlObj = new UserControl($dbCmd);
$PaymentInvoiceObj = new PaymentInvoice();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$UserID = $AuthObj->GetUserID();



if(!$AuthObj->CheckForPermission("CORPORATE_BILLING"))
	WebUtil::PrintAdminError("Not Available");


// Get parameters from the URL
$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "getID");
$customerid = WebUtil::GetInput("customerid", FILTER_SANITIZE_INT);
$billingTypeOverride = WebUtil::GetInput("billingTypeOverride", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

// Sometimes the UserID has a U prefix.
$customerid = preg_replace("/u/i", "", $customerid);



$t = new Templatex(".");


$t->set_file("origPage", "ad_billing_receive-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


// Put in a javascript command to focus on any element within the Form... it will make it quick and easy to enter information this way
$t->set_var("JS_FOCUS_COMMAND", "");



if($view == "getID"){
	$t->discard_block("origPage", "PaymentBL");
	$t->discard_block("origPage", "MessageBL");
	$t->discard_block("origPage", "PaymentReceivedBL");
	
	
	$t->set_var("JS_FOCUS_COMMAND", "document.forms['customeridform'].customerid.focus();");
	
}
else if($view == "getPayment"){

	// First Make sure that the User ID is valid
	if(!$UserControlObj->LoadUserByID($customerid)){
	
		$t->discard_block("origPage", "PaymentBL");
		
		$t->set_var("THE_MESSAGE", "<div align='center'><br>The user does not exist.<br><br><a href='". WebUtil::FilterURL($_SERVER['PHP_SELF']) . "'>Start Over</a></div></html>");
		$t->allowVariableToContainBrackets("THE_MESSAGE");
	}
	else{
	
	
	
		// Make sure that the customer has corporate invoicing enabled.
		if($UserControlObj->getBillingType() != "C" && !$billingTypeOverride){
			$t->discard_block("origPage", "PaymentBL");
			
			$overrideURL = "./ad_billing_receive.php?view=getPayment&billingTypeOverride=true&customerid=" . $customerid;

			$t->set_var("THE_MESSAGE", "<div align='center'><br>This user does not have corporate billing enabled on the account.  Double check the Account ID that was entered.  <br><br>If you are sure that it is correct, then it is possible that this user was billed by corporate invoicing at one point and is now changed to a different billing type. <br><br> If you are sure that you want to enter a payment for this customer, <a href='" . WebUtil::FilterURL($overrideURL) . "' class='blueredlink'>click here</a> to override this warning.<br><br><a href='". WebUtil::FilterURL($_SERVER['PHP_SELF']) . "'>Start Over?</a></div></html>");
			$t->allowVariableToContainBrackets("THE_MESSAGE");
		}
		else{

			$PaymentInvoiceObj->LoadCustomerByID($customerid);

			$t->discard_block("origPage", "MessageBL");

			$t->set_var("CUSTOMER_NAME", WebUtil::htmlOutput($UserControlObj->getCompanyOrName()));
			$t->set_var("CUSTOMER_BALANCE", Widgets::GetColoredPrice($PaymentInvoiceObj->GetCurrentCreditUsage()));
			$t->allowVariableToContainBrackets("CUSTOMER_BALANCE");
			
			$t->set_var("CUSTOMERID", $customerid);
			$t->set_var("THE_MESSAGE", "");
			$t->set_var("JS_FOCUS_COMMAND", "document.forms['amountform'].paymentamount.focus();");
		}
	}
	
	$t->discard_block("origPage", "CustomerIDbl");
	$t->discard_block("origPage", "PaymentReceivedBL");
	


}
else if($view == "savePayment"){

	if(!$UserControlObj->LoadUserByID($customerid, TRUE))
		throw new Exception("Problem loading the customer");
		
	WebUtil::checkFormSecurityCode();

	$PaymentInvoiceObj->LoadCustomerByID($customerid);
	
	$paymentNotes = WebUtil::GetInput("paymentnotes", FILTER_SANITIZE_STRING_ONE_LINE);
	$paymentAmount = WebUtil::GetInput("paymentamount", FILTER_SANITIZE_FLOAT);
	
	$PaymentInvoiceObj->SetPaymentReceived($paymentAmount, $paymentNotes, $UserID);
	
	// We want to redirect to this page...  That way the user will not accidently reload the page and cause a duplicate payment to be entered
	// Just be really careful when it comes to entering money.
	header("Location: " . WebUtil::FilterURL($_SERVER['PHP_SELF']) . "?customerid=$customerid&view=showPaymentSaved");
	exit;
	
}
else if($view == "showPaymentSaved"){

	if(!$UserControlObj->LoadUserByID($customerid))
		throw new Exception("Problem loading the customer");


	$PaymentInvoiceObj->LoadCustomerByID($customerid);
	
	
	$Last5Payments = $PaymentInvoiceObj->GetMostRecentPayments(5);

	$t->set_block("origPage","PaymentAmounts","PaymentAmountsOut");
	foreach($Last5Payments as $ThisPayment){
	
		$t->set_var("PAYMENT_AMOUNT", Widgets::GetColoredPrice($ThisPayment["Amount"]));
		$t->set_var("PAYMENT_DATE", date("M j, Y g:i a", $ThisPayment["UnixDate"]));
		
		$t->allowVariableToContainBrackets("PAYMENT_AMOUNT");
		
		$t->parse("PaymentAmountsOut","PaymentAmounts",true);
	}

	
	$t->discard_block("origPage", "CustomerIDbl");
	$t->discard_block("origPage", "PaymentBL");

	$t->set_var("CUSTOMER_NAME", WebUtil::htmlOutput($UserControlObj->getCompanyOrName()));
	$t->set_var("BALANCE", Widgets::GetColoredPrice($PaymentInvoiceObj->GetCurrentCreditUsage()));
	$t->allowVariableToContainBrackets("BALANCE");
	
	$t->set_var("JS_FOCUS_COMMAND", "document.forms['PaymentReceivedForm'].NextPaymentBtn.focus();");
	
	$t->set_var("THE_MESSAGE", "");
}
else{

	print "<html><div align='center'><br><br><br><a href='". $_SERVER['PHP_SELF'] . "'>Start Over</a></div></html>";
}




$t->pparse("OUT","origPage");




?>
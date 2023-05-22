<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();
$dbCmd3 = new DbCmd();

$SalesRepObj = new SalesRep($dbCmd2);
$SalesCommissionsObj = new SalesCommissions($dbCmd2, Domain::oneDomain());
$SalesPaymentsObj = new SalesCommissionPayments($dbCmd2, $dbCmd3, Domain::oneDomain());




// Make this script be able to run for a while
set_time_limit(800);

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("SALES_CONTROL"))
	throw new Exception("permission denied");


$Action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if(!empty($Action)){
	
	WebUtil::checkFormSecurityCode();
	
	if($Action == "verifyaddress"){

		$SalesUserID = WebUtil::GetInput("salesuserid", FILTER_SANITIZE_INT);
		
		// Mark the Sales Rep that we have received their Address Verification
		$SalesRepObj->LoadSalesRep( $SalesUserID );
		$SalesRepObj->setAddressIsVerified(true);
		$SalesRepObj->SaveSalesRep();
		
		SalesRep::RecordChangesToSalesRep($dbCmd, $SalesUserID, $UserID, "Address was verified.");
		
		// If the user has any payments suspended, then we need to release them.
		$SalesCommissionsObj->ReleaseSuspendedPayments($SalesUserID);
	}
	else if($Action == "retrycharges"){
	
		$SalesUserID = WebUtil::GetInput("salesuserid", FILTER_SANITIZE_INT);
		
		$SalesPaymentsObj->RetryReturnedOrDeniedPaypalPayments($SalesUserID);
		
	}
	else if($Action == "retrymasspay"){
	
		$SalesPaymentsObj->RetryMassPayError(WebUtil::GetInput("masspayid", FILTER_SANITIZE_INT));

	}
	else if($Action == "manuallyconfirmmasspay"){
	
		$SalesPaymentsObj->ManuallyCompleteMassPayError(WebUtil::GetInput("masspayid", FILTER_SANITIZE_INT), $UserID);
	}
	else if($Action == "ShowInactiveItems"){
		$HTTP_SESSION_VARS['ShowInactiveSalesItems'] = true;
	}
	else if($Action == "HideInactiveItems"){
		$HTTP_SESSION_VARS['ShowInactiveSalesItems'] = false;
	}
	else
		throw new Exception("Illegal Action");
	
	
	header("Location: ./ad_sales_management.php");
	exit;
}


// We store a session variable for our preferences.... if we want to see Inactive Sales Items over 90 days old
if (!isset($HTTP_SESSION_VARS['ShowInactiveSalesItems']))
	$HTTP_SESSION_VARS['ShowInactiveSalesItems'] = false;
$ShowInactiveItems = $HTTP_SESSION_VARS['ShowInactiveSalesItems'];


// Make a Minimum Mysql Time Stamp... if we are viewing old entries then just set the minimum date to something really far behind to make sure we get everything
if($ShowInactiveItems){
	$MinAddressVerifiedTimeStamp = date("YmdHis", mktime(1, 1, 1, 1, 1, 2002));
	$MinW9TimeStamp = date("YmdHis", mktime(1, 1, 1, 1, 1, 2002));
	$MinReturnedPaymentsTimeStamp = date("YmdHis", mktime(1, 1, 1, 1, 1, 2002));
	$MinEmailVerifyTimeStamp = date("YmdHis", mktime(1, 1, 1, 1, 1, 2002));
}
else{
	$MinAddressVerifiedTimeStamp = date("YmdHis", (time() - (60 * 60 * 24 * 75))); // 75 days
	$MinW9TimeStamp = date("YmdHis", (time() - (60 * 60 * 24 * 360)));  // 360 Days
	$MinReturnedPaymentsTimeStamp = date("YmdHis", (time() - (60 * 60 * 24 * 90)));  // 90 Days
	$MinEmailVerifyTimeStamp = date("YmdHis", (time() - (60 * 60 * 24 * 90)));  // 90 Days
}



$t = new Templatex(".");

$t->set_file("origPage", "ad_sales_management-template.html");


$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


// Set the radio button for our preference if we should show inactive items or not
if($ShowInactiveItems)
	$t->set_var(array("SHOW_INACTIVE_YES"=>"checked", "SHOW_INACTIVE_NO"=>""));
else
	$t->set_var(array("SHOW_INACTIVE_YES"=>"", "SHOW_INACTIVE_NO"=>"checked"));



// Find out all of the Users that have not had their address verified 
$AddressNotVerifiedPeopleFound = false;
$t->set_block("origPage","addressBL","addressBLout");

$dbCmd->Query("SELECT UserID FROM salesreps INNER JOIN users ON salesreps.UserID = users.ID 
				WHERE AddressIsVerified='N' AND users.DomainID = ".Domain::oneDomain()." AND 
				salesreps.DateCreated > $MinAddressVerifiedTimeStamp ORDER BY salesreps.DateCreated DESC");
while($thisSalesUserID = $dbCmd->GetValue()){
	$AddressNotVerifiedPeopleFound = true;
		
	$SalesRepObj->LoadSalesRep($thisSalesUserID);
	
	
	$t->set_var("SALES_NAME", WebUtil::htmlOutput($SalesRepObj->getName()));
	$t->set_var("SALES_NAME_JS", addslashes($SalesRepObj->getName()));
	$t->set_var("USERID", $thisSalesUserID);
	$t->set_var("PHONE", WebUtil::htmlOutput($SalesRepObj->getPhone()));
	$t->set_var("EMAIL", $SalesRepObj->getEmail());
	$t->set_var("SUSPEND_DATE", date("n/j/y", $SalesRepObj->getAddressVerficationDeadlineTimestamp()));
	
	$t->set_var("SALES_ADDRESS", addslashes($SalesRepObj->getBothAddresses()) . '\n' . addslashes($SalesRepObj->getCity()) . ', ' . $SalesRepObj->getState() . " " . $SalesRepObj->getZip() );
	
	$t->parse("addressBLout","addressBL",true);

}
if(!$AddressNotVerifiedPeopleFound){
	$t->set_block("origPage","HideAddressVerificationsBL","HideAddressVerificationsBLout");
	$t->set_var("HideAddressVerificationsBLout", "All Sales Reps have had their addresses verified.");
	
	// Hide the button for generating the mailers.
	$t->discard_block("origPage","HideAddressFormButtonBL");
	
}





// Find out all of the Users that have not had their W-9's received 
$W9NotReceivedPeopleFound = false;
$t->set_block("origPage","w9BL","w9BLout");

$dbCmd->Query("SELECT UserID FROM salesreps INNER JOIN users ON salesreps.UserID = users.ID 
			WHERE HaveReceivedW9='N' AND IsAnEmployee='N' AND users.DomainID = ".Domain::oneDomain()."
			AND salesreps.DateCreated > $MinW9TimeStamp ORDER BY salesreps.DateCreated DESC");
while($thisSalesUserID = $dbCmd->GetValue()){
	$W9NotReceivedPeopleFound = true;
		
	$SalesRepObj->LoadSalesRep($thisSalesUserID);
	
	$SalesCommissionsObj->SetUser($thisSalesUserID);
	$SalesCommissionsObj->SetDateRangeForAll();
	
	$t->set_var("SALES_NAME", WebUtil::htmlOutput($SalesRepObj->getName()));
	$t->set_var("USERID", $thisSalesUserID);
	$t->set_var("PHONE", WebUtil::htmlOutput($SalesRepObj->getPhone()));
	$t->set_var("EMAIL", $SalesRepObj->getEmail());
	
	$t->set_var("TOTAL_COMMISIONS", '$' . $SalesCommissionsObj->GetTotalCommissionsWithinPeriodForUser($thisSalesUserID, "All", "GoodAndSuspended"));
	
	
	$t->parse("w9BLout","w9BL",true);

}
if(!$W9NotReceivedPeopleFound){
	$t->set_block("origPage","HideW9BL","HideW9BLout");
	$t->set_var("HideW9BLout", "We have received W-9's for all Sales Reps.");
}






// Find out all of the Users that have not had their email addresses verified 
$t->set_block("origPage","emailVerifyBL","emailVerifyBLout");
$dbCmd->Query("SELECT UserID FROM salesreps INNER JOIN users ON salesreps.UserID = users.ID 
				WHERE EmailIsVerified='N' AND users.DomainID = ".Domain::oneDomain()." AND 
				salesreps.DateCreated > $MinEmailVerifyTimeStamp ORDER BY salesreps.DateCreated DESC");
while($thisSalesUserID = $dbCmd->GetValue()){
	$W9NotReceivedPeopleFound = true;
		
	$SalesRepObj->LoadSalesRep($thisSalesUserID);
	
	$t->set_var("SALES_NAME", WebUtil::htmlOutput($SalesRepObj->getName()));
	$t->set_var("USERID", $thisSalesUserID);
	$t->set_var("PHONE", WebUtil::htmlOutput($SalesRepObj->getPhone()));
	$t->set_var("EMAIL", $SalesRepObj->getEmail());

	
	$t->parse("emailVerifyBLout", "emailVerifyBL", true);

}
if($dbCmd->GetNumRows() == 0)
	$t->discard_block("origPage","HideEmailVerificationBL");








// Find out if any users have Returned or Denied Payments
$ReturnedPayments = false;
$t->set_block("origPage","ReturnPaymentBL","ReturnPaymentBLout");
$dbCmd->Query("SELECT DISTINCT(SalesUserID) FROM salespayments 
				INNER JOIN users ON salespayments.SalesUserID = users.ID 
				WHERE (PayPalStatus='R' OR PayPalStatus='D') 
				AND users.DomainID = ".Domain::oneDomain()." AND 
				salespayments.DateCreated > $MinReturnedPaymentsTimeStamp");
while($thisSalesUserID = $dbCmd->GetValue()){
	$ReturnedPayments = true;
	
	$SalesRepObj->LoadSalesRep($thisSalesUserID);
	$t->set_var("RETURNED_NAME", WebUtil::htmlOutput($SalesRepObj->getName()));
	$t->set_var("SALES_ID", $thisSalesUserID);

	$t->parse("ReturnPaymentBLout","ReturnPaymentBL",true);
}
if(!$ReturnedPayments)
	$t->discard_block("origPage","HideReturnedPaymentsBL");






// Find out if there are any Mass Pay Errors
$MassPayErrors = false;
$t->set_block("origPage","MassPayErrorBL","MassPayErrorBLout");
$dbCmd->Query("SELECT *, UNIX_TIMESTAMP(LastStatusChange) AS LastDate FROM paypalmasspay WHERE MassPayStatus='E' AND DomainID=" . Domain::oneDomain());
while($row = $dbCmd->GetRow()){
	$MassPayErrors = true;
	
	$t->set_var("MASSPAY_DATE", date("n/j/y, g:i a", $row["LastDate"]));
	$t->set_var("MASSPAY_AMOUNT", '$' . number_format($row["PaymentAmount"], 2));
	$t->set_var("MASSPAY_ERROR", WebUtil::htmlOutput($row["ErrorMessage"]));
	$t->set_var("MASSPAY_ID", $row["ID"]);

	$t->parse("MassPayErrorBLout","MassPayErrorBL",true);
}
if(!$MassPayErrors || !$AuthObj->CheckForPermission("SALES_MASTER"))
	$t->discard_block("origPage","HideMassPayErrorsBL");







// Only the Sales Master should have the ability to generate End of Year W-9 Reports
if(!$AuthObj->CheckForPermission("SALES_MASTER"))
	$t->discard_block("origPage","HideSalesMasterBL");
else{

	// Show a Year Selection Drop Down
	$t->set_var("YEAR_SELECT", Widgets::BuildYearSelect((date("Y") - 1)));
	$t->allowVariableToContainBrackets("YEAR_SELECT");
}

	

$t->pparse("OUT","origPage");




?>
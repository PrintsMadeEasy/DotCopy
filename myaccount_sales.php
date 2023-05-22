<?

require_once("library/Boot_Session.php");




$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();




//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);
$UserID = $AuthObj->GetUserID();


$UserControlObj = new UserControl($dbCmd);
$UserControlObj->LoadUserByID($UserID);


// For the Date Selection Control
$NavigationObj = new Navigation();
// We want the "Next" Link to disapear when we are on the current month/year
$NavigationObj->SetStopMonth(date("n"));
$NavigationObj->SetStopYear(date("Y"));


$t = new Templatex();

$t->set_file("origPage", "myaccount_sales-template.html");


$SalesMaster = $AuthObj->CheckForPermission("SALES_MASTER");

$SalesRepObj = new SalesRep($dbCmd);
if(!$SalesRepObj->LoadSalesRep($UserID) && !$SalesMaster)
	WebUtil::PrintError("You do not have permissions to view this.");



// Will be displayed to the user in Red Text at the top of the page.
$messageToUser = "";


$Action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if(!empty($Action)){
	if($Action == "validateemail"){

		$EmailVerifyCode = WebUtil::GetInput("emailverify", FILTER_SANITIZE_STRING_ONE_LINE);
		
		if(empty($EmailVerifyCode))
			WebUtil::PrintError("You forgot to enter the email verification code before clicking on submit.", true);
		else if($EmailVerifyCode <> $SalesRepObj->getUserIDhashForVerification())
			WebUtil::PrintError("The code that you have entered does not match what we sent to you in the email.", true);
		else{
			$SalesRepObj->setEmailIsVerified(true);
			$SalesRepObj->SaveSalesRep();
			
			SalesRep::RecordChangesToSalesRep($dbCmd, $UserID, $UserID, "Email address was verified.");
			
			WebUtil::PrintError("Your email address has been successfully verified.", true);
		}
	}
	else{
		throw new Exception("Illegal Action");
	}
	
	
	header("Location: ./myaccount_sales.php");
	exit;
}




$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "report");

$baseTabUrl = "./myaccount_sales.php?";
$TabsObj = new Navigation();
$TabsObj->AddTab("report", "Sales Report", $baseTabUrl . "view=report");
$TabsObj->AddTab("payments", "Payment History", $baseTabUrl . "view=payments");
$TabsObj->AddTab("coupons", "Coupon Report", $baseTabUrl . "view=coupons");
$TabsObj->AddTab("linking", "Website Linking", $baseTabUrl . "view=linking");
$TabsObj->AddTab("info", "Getting Started", $baseTabUrl . "view=info");
$TabsObj->AddTab("terms", "Terms & Conditions", $baseTabUrl . "view=terms");

$t->set_var("NAV_BAR_HTML", $TabsObj->GetTabsHTML($view));
$t->allowVariableToContainBrackets("NAV_BAR_HTML");


// The Sales Master can see everyone.
// If the Sales Master is also a Sales person... then he or she will be mixed in the crowd.
if($SalesMaster)
	$SalesID = 0;
else
	$SalesID = $UserID;


$salesRepIDfromURL = WebUtil::GetInput("salesrep", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

WebUtil::EnsureDigit($salesRepIDfromURL, false);

// If a Sales Rep ID is passed into the URL... check and see if that matches our Session Variable.
// If it does not then update the session variable.
if($salesRepIDfromURL !== null){

	if(WebUtil::GetSessionVar("SalesRepIDview") != $salesRepIDfromURL)
		WebUtil::SetSessionVar("SalesRepIDview", $salesRepIDfromURL);
		
	$SalesRepID = $salesRepIDfromURL;

}
else{

	// If we are looking at a Sub rep, Get their UserID from the URL.
	// By default... we make the Sales Rep ID ourselves (if the another ID isn't already stored within the session..
	$SalesRepID = WebUtil::GetSessionVar("SalesRepIDview", $SalesID);

}



$SalesCommissionsObj = new SalesCommissions($dbCmd, Domain::oneDomain());
$SalesCommissionsObj->SetUser($SalesID);


// Make a Sales Commission object and set the User ID (to what could possibly be a sub-rep)
// Useful if you want to get reports (as if you were that person).  EX: Use for drilling down on sub-reps to see their coupon reports, etc.
$SalesCommissions_sub_Obj = new SalesCommissions($dbCmd, Domain::oneDomain());
$SalesCommissions_sub_Obj->SetUser($SalesRepID);

// Default our month/year to the current day
$month = WebUtil::GetInput("month", FILTER_SANITIZE_INT, date("n"));
$year = WebUtil::GetInput("year", FILTER_SANITIZE_INT, date("Y"));

$SalesCommissionsObj->SetMonthYear($month, $year);
$SalesCommissions_sub_Obj->SetMonthYear($month, $year);


// Show the name of the percent we are looking at, and their commission rate... unless of course we are at the root.
if($SalesRepID == 0){
	$t->set_var("SALES_NAME", "Root");
	$t->set_var("SALES_PERCENT", "100");
}
else{
	$SalesRepObj2 = new SalesRep($dbCmd);
	if(!$SalesRepObj2->LoadSalesRep($SalesRepID))
		throw new Exception("Illegal Sales RepID.");
	
	$t->set_var("SALES_NAME", $SalesRepObj2->getName());
	$t->set_var("SALES_PERCENT", $SalesRepObj2->getCommissionPercent());
}


$t->set_var("MONTH", $month);
$t->set_var("YEAR", $year);
$t->set_var("SALES_REP", $SalesRepID);
$t->set_var("VIEW", $view);
$t->set_var("MESSAGE", WebUtil::htmlOutput($messageToUser));




#-- On the live site the Navigation bar needs to have hard-coded links to jump out of SLL mode... like http://www.example.com
#-- Also flash plugin can not have any non-secure source or browser will complain.. nead to change plugin to https:/
if(Constants::GetDevelopmentServer()){
	$t->set_var("SECURE_FLASH","");
	$t->set_var("HTTPS_FLASH","");
}
else{
	$t->set_var("SECURE_FLASH","TRUE");
	$t->set_var("HTTPS_FLASH","s");
}

// Make sure we have https://bla in our browser
WebUtil::RunInSecureModeHTTPS();


if($view == "info"){
	
	$t->discard_block("origPage", "SalesReportBL");
	$t->discard_block("origPage", "PaymentsBL");
	$t->discard_block("origPage", "CouponReportBL");
	$t->discard_block("origPage", "TermsBL");
	$t->discard_block("origPage", "LinkingBL");
	
	
	
	if($SalesMaster){
		$t->set_var("SALESREP_CODE", "SALESMASTER");
		$t->set_var("COMMISSION_RATE", "100");
	}
	else{
		$t->set_var("SALESREP_CODE", $SalesRepObj2->getDefaultSalesCouponCode());
		$t->set_var("COMMISSION_RATE", $SalesRepObj2->getCommissionPercent());
	}
	
	if(!$SalesMaster)
		if(!$SalesRepObj->CheckIfCanAddSubSalesReps())
			$t->discard_block("origPage", "SiteReferralDesignsBL");
	
}
else if($view == "report"){
	
	$t->discard_block("origPage", "InfoBL");
	$t->discard_block("origPage", "PaymentsBL");
	$t->discard_block("origPage", "CouponReportBL");
	$t->discard_block("origPage", "TermsBL");
	$t->discard_block("origPage", "LinkingBL");

	if(!$SalesMaster)
		if(!$SalesRepObj->CheckIfCanAddSubSalesReps())
			$t->discard_block("origPage", "InviteSubRepBL");

	// If we don't detect any of the values coming in the URL... for the radio button selection... then see if they are in a cookie
	$OrderDetailsView = WebUtil::GetInput("orderdetails", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
	$SubRepDetailsView = WebUtil::GetInput("subrepdetails", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
	
	if(empty($OrderDetailsView) && isset($_COOKIE["SalesOrderDetailsView"]))
		$OrderDetailsView = $_COOKIE["SalesOrderDetailsView"];
	if(empty($SubRepDetailsView) && isset($_COOKIE["SalesSubRepDetailsView"]))
		$SubRepDetailsView = $_COOKIE["SalesSubRepDetailsView"];

	// If we still don't see a value, then default to "hide"
	if(empty($OrderDetailsView))
		$OrderDetailsView = "hide";
	if(empty($SubRepDetailsView))
		$SubRepDetailsView = "hide";

	// Set a cookie on the users machine so we always remember their favorite setting for the radio buttons
	WebUtil::OutputCompactPrivacyPolicyHeader();
	$DaysToRemember = 300;
	$cookieTime = time()+60*60*24 * $DaysToRemember;
	setcookie ("SalesOrderDetailsView", $OrderDetailsView, $cookieTime);
	setcookie ("SalesSubRepDetailsView", $SubRepDetailsView, $cookieTime);



	$t->set_var("SUBREP_DETAIL_VIEW", $SubRepDetailsView);	
	$t->set_var("ORDER_DETAIL_VIEW", $OrderDetailsView);	
	

	$NavigationObj->SetNameValuePairs(array("view"=>$view, "salesrep"=>$SalesRepID, "orderdetails"=>$OrderDetailsView, "subrepdetails"=>$SubRepDetailsView));
	$NavigationObj->SetBaseURL("./myaccount_sales.php");
	$t->set_var("DATE_SELECT", $NavigationObj->GetYearMonthSelectorHTML($month, $year));
	$t->allowVariableToContainBrackets("DATE_SELECT");

	// Make sure the SalesRepID belongs to the User viewing this page.
	// $SalesRepID could be the same as the UserID if we are looking at our own orders.
	if(!$SalesCommissionsObj->CheckIfSalesRepBelongsToUser($SalesRepID)){
		WebUtil::PrintError("The Sales Rep ID is not correct.");
	}
	
	// Don't show the Sales Chain Background or Block if there is only 1 person involved.
	if(sizeof($SalesCommissionsObj->GetChainFromSubRepToUser($SalesRepID)) == 1){
		$t->set_block("origPage","SalesChainBL","SalesChainBLout");
		$t->set_var("SalesChainBLout", "&nbsp;");
	}
	else{
		$t->set_var("SALES_CHAIN", $SalesCommissionsObj->BuildSalesChainLinks($SalesRepID));
	}
	
	$t->allowVariableToContainBrackets("SALES_CHAIN");
	
	
	// Erase the parts of the "Totals" HTML depending on if they have Sub Reps, or if we are a Sales master
	// Also gets rid of one of the radio button choices
	if(!$SalesMaster && !$SalesCommissionsObj->CheckIfSalesRepHasSubReps($SalesID)){
		$t->discard_block("origPage", "CommissionsSubRepsBL");
		$t->discard_block("origPage", "SubRepRadiosBL");
	}

	
	$TotalCommissionsEarned = $SalesCommissionsObj->GetTotalCommissionsWithinPeriodForUser($SalesRepID, "All", "GoodAndSuspended");
	$TotalCommissionsFromOwnedOrders = $SalesCommissionsObj->GetTotalCommissionsWithinPeriodForUser($SalesRepID, "OwnedOrders", "GoodAndSuspended");
	
	$TotalOrderCountWithSubReps = $SalesCommissionsObj->GetNumOrdersFromSalesRep($SalesRepID, true);
	$TotalOrderCountDirect = $SalesCommissionsObj->GetNumOrdersFromSalesRep($SalesRepID, false);
	
	
	// Don't show the Order Totals if they are not the Sales Master..
	// And if we are at the root and the Sales Master then don't show the order totalsbecause it will take to long to calculate ... and we can always get the info from the Month Summary
	if(!$SalesMaster || $SalesRepID == 0){
		$t->discard_block("origPage", "OrderSubtotalsBL");
		$t->discard_block("origPage", "VendorSubtotalsBL");
	}
	else{
		$t->set_var("ORDER_SUBTOTALS_DIRECT", '$' . number_format($SalesCommissionsObj->GetOrderTotalsWithinPeriod($SalesRepID, false, "CustomerSubotals")), 2);
		$t->set_var("VENDOR_SUBTOTALS_DIRECT", '$' . number_format($SalesCommissionsObj->GetOrderTotalsWithinPeriod($SalesRepID, false, "VendorSubtotals")), 2);

		$t->set_var("ORDER_SUBTOTALS", '$' . number_format($SalesCommissionsObj->GetOrderTotalsWithinPeriod($SalesRepID, true, "CustomerSubotals")), 2);
		$t->set_var("VENDOR_SUBTOTALS", '$' . number_format($SalesCommissionsObj->GetOrderTotalsWithinPeriod($SalesRepID, true, "VendorSubtotals")), 2);
		
		$profitFromRep = $SalesCommissionsObj->GetOrderTotalsWithinPeriod($SalesRepID, true, "CustomerSubotals") - $SalesCommissionsObj->GetOrderTotalsWithinPeriod($SalesRepID, true, "VendorSubtotals") - $TotalCommissionsEarned;
		$t->set_var("PROFIT_FROM_REP", '$' . number_format($profitFromRep, 2));
	}
	
	
	

	
	$t->set_var("MY_ORDERS_COUNT", $TotalOrderCountDirect);
	$t->set_var("SUB_REPS_ORDERS_COUNT", ($TotalOrderCountWithSubReps - $TotalOrderCountDirect));
	
	$t->set_var("TOTLAL_ORDERS", $TotalOrderCountWithSubReps);
	
	$t->set_var("MY_COMM_TOTAL", '$' . number_format($TotalCommissionsFromOwnedOrders), 2);
	
	$t->set_var("TOTLAL_COMMISSIONS", '$' . number_format($TotalCommissionsEarned, 2));
	
	$t->set_var("SUB_REPS_COMM_TOTAL", '$' . number_format(($TotalCommissionsEarned - $TotalCommissionsFromOwnedOrders), 2, '.', ''));


	

	// Show the amount of money that is being suspended for a particular user (unless we are at the root)
	if($SalesRepID == 0){
		$t->discard_block("origPage", "SuspendedPaymentBL");
	}
	else{
		$TotalCommissionsSuspended = $SalesCommissionsObj->GetTotalCommissionsWithinPeriodForUser($SalesRepID, "All", "Suspended");
		if($TotalCommissionsSuspended == 0)
			$t->discard_block("origPage", "SuspendedPaymentBL");
		else
			$t->set_var("SUSPENDED_AMOUNT", '$' . $TotalCommissionsSuspended);
	}



	// Show how many new/repeat customers there are.
	$totalCustomers = $SalesCommissionsObj->GetCustomerCountsWithinPeriod($SalesRepID, false, "all");
	$newCustomers = $SalesCommissionsObj->GetCustomerCountsWithinPeriod($SalesRepID, false, "new");
	$repeatCustomers = $totalCustomers - $newCustomers;

	// prevent division by zero
	if($totalCustomers > 0)
		$repeatCustomerPercentage = $repeatCustomers / $totalCustomers;
	else
		$repeatCustomerPercentage = 0;

	$t->set_var("CUSTOMERS_TOTAL", $totalCustomers);
	$t->set_var("CUSTOMERS_NEW", $newCustomers);
	$t->set_var("CUSTOMERS_REPEAT", $repeatCustomers . "&nbsp;&nbsp;&nbsp;(" . round($repeatCustomerPercentage * 100) . "%)");


	// If the radio button is selected to show the order Details.
	if($OrderDetailsView == "show"){
		// Set the Radio Buttons for the details Show or Hide
		$t->set_var("ORDERS_SHOW", "checked");
		$t->set_var("ORDERS_HIDE", "");	
		
		$OrderDetailsArr = $SalesCommissionsObj->GetOrderInfoWithinPeriodForUser($SalesRepID);
		
		$t->set_block("origPage","OrderRowBL","OrderRowBLout");
		
		foreach($OrderDetailsArr as $thisOrderHash){
		
			$dbCmd->Query("SELECT BillingName, BillingCompany, UserID FROM orders WHERE ID=". $thisOrderHash["OrderID"]);
			$OrdRow = $dbCmd->GetRow();
			
			if(empty($OrdRow["BillingCompany"]))
				$DispName = $OrdRow["BillingName"];
			else
				$DispName = $OrdRow["BillingCompany"];
		
			$t->set_var("ORD_NUM", $thisOrderHash["OrderID"]);
			$t->set_var("ORD_DT", date("n/j/y", $thisOrderHash["OrderDate"]));
			$t->set_var("ORD_NAM", WebUtil::htmlOutput($DispName));
			$t->set_var("ORD_TOT", '$' . number_format($thisOrderHash["SubtotalAfterDiscount"] + $thisOrderHash["OrderDiscount"], 2));
			$t->set_var("ORD_DISC", '$' . $thisOrderHash["OrderDiscount"]);
			$t->set_var("COM_PCNT", $thisOrderHash["CommissionPercentAfterDiscount"] . '%');
			$t->set_var("COM_AMNT", '$' . $thisOrderHash["CommissionEarned"]);
			$t->set_var("CUSTOMER_ID", $OrdRow["UserID"]);
			
			
			$t->parse("OrderRowBLout","OrderRowBL",true);
		}
		
		if(sizeof($OrderDetailsArr) == 0){
			$t->set_block("origPage","OrderDetailsBL","OrderDetailsBLout");
			$t->set_var("OrderDetailsBLout", "<br><br><b>No Orders during this period.");
		}
	}
	else if($OrderDetailsView == "hide"){
		$t->set_var("ORDERS_SHOW", "");
		$t->set_var("ORDERS_HIDE", "checked");	
		$t->discard_block("origPage", "OrderDetailsBL");
	}
	else
		throw new Exception("Illegal orderdetails view");
	

	
	if($SubRepDetailsView == "show"){
		// Set the Radio Buttons for the details Show or Hide
		$t->set_var("SUBREP_SHOW", "checked");
		$t->set_var("SUBREP_HIDE", "");	
		

		$SubRepsArr = $SalesCommissionsObj->GetSubReps($SalesRepID);
		
		$t->set_block("origPage","SubRepRowBL","SubRepRowBLout");
		
		// Find out if we have permissions to Edit the Sales Rep... if so we need to add an extra column
		if($SalesMaster || $SalesRepID == $UserID)
			$t->set_var("EDIT_COLUMN_HEADER", "<td width='100' class='body'>&nbsp;</td>");
		else
			$t->set_var("EDIT_COLUMN_HEADER", "");
			
		$t->allowVariableToContainBrackets("EDIT_COLUMN_HEADER");
		
		$SalesRepObj = new SalesRep($dbCmd);
		
		foreach($SubRepsArr as $thisSubRepID){
		
			$SalesRepObj->LoadSalesRep($thisSubRepID);
			
			$t->set_var("SUBREP_ID", $thisSubRepID);
			$t->set_var("SUBREP_NAME", WebUtil::htmlOutput($SalesRepObj->getName()));
			$t->set_var("SUBREP_ORDERS", $SalesCommissionsObj->GetNumOrdersFromSalesRep($thisSubRepID, true)	);
			$t->set_var("SUBREP_COMM", '$' . $SalesCommissionsObj->GetTotalCommissionsWithinPeriodForUser($thisSubRepID, "All", "GoodAndSuspended"));
			$t->set_var("SUBREP_PERC", $SalesRepObj->getCommissionPercent());
			

			// Find out if we have permissions to Edit the Sales Rep... if so we need to add an extra column
			if($SalesMaster || $SalesRepID == $UserID)
				$t->set_var("EDIT_COLUMN", "<td width='100' class='body'><a class='BlueRedLink' href='javascript:EditRep($thisSubRepID)'>Edit</a></td>");
			else
				$t->set_var("EDIT_COLUMN", "");
				
			$t->allowVariableToContainBrackets("EDIT_COLUMN");
			
			$t->parse("SubRepRowBLout","SubRepRowBL",true);
		}
		
		if(sizeof($SubRepsArr) == 0){
			$t->set_block("origPage","SubRepDetailsBL","SubRepDetailsBLout");
			$t->set_var("SubRepDetailsBLout", "<br><br><b>No Sub Reps.");
		}


	}
	else if($SubRepDetailsView == "hide"){
		$t->set_var("SUBREP_SHOW", "");
		$t->set_var("SUBREP_HIDE", "checked");
		$t->discard_block("origPage", "SubRepDetailsBL");
	}
	else
		throw new Exception("Illegal subrepdetails view");


}
else if($view == "coupons"){

	$t->discard_block("origPage", "InfoBL");
	$t->discard_block("origPage", "PaymentsBL");
	$t->discard_block("origPage", "SalesReportBL");
	$t->discard_block("origPage", "TermsBL");
	$t->discard_block("origPage", "LinkingBL");

	$NavigationObj->SetNameValuePairs(array("view"=>$view, "salesrep"=>$SalesRepID));
	$NavigationObj->SetBaseURL("./myaccount_sales.php");
	$t->set_var("DATE_SELECT", $NavigationObj->GetYearMonthSelectorHTML($month, $year, true));
	$t->allowVariableToContainBrackets("DATE_SELECT");

	if($month == "ALL")
		$t->set_var("REPORT_PERDIOD", "All of $year");
	else
		$t->set_var("REPORT_PERDIOD", date("F", mktime(1, 1, 1, $month, 1, $year)) . " $year");
		

	// If we are the Sales Master then we don't wan't to see any coupons that belong to us.
	if($SalesRepID == 0)
		$CouponIDarr = array();
	else
		$CouponIDarr = $SalesRepObj2->GetCouponIDsBelongingToSalesRep();


	$CouponObj = new Coupons($dbCmd);

	$t->set_block("origPage","ActiveCouponsBL","ActiveCouponsBLout");
	foreach($CouponIDarr as $thisCouponID){
	
		$CouponObj->LoadCouponByID($thisCouponID);
		
		if($CouponObj->GetCouponExpDate() < time())
			$t->set_var("COUPON_NAME", "<s>" . $CouponObj->GetCouponCode() . "</s>");
		else
			$t->set_var("COUPON_NAME", $CouponObj->GetCouponCode());
	
	
		$t->set_var("CUST_DISC", $CouponObj->GetCouponDiscountPercent() . '%');
		
		if($CouponObj->GetCouponMaxAmount() == 0)
			$t->set_var("MAX", "No limit");
		else
			$t->set_var("MAX", '$' . number_format($CouponObj->GetCouponMaxAmount(), 2));
	
		
		$t->set_var("USAGE", WebUtil::htmlOutput($CouponObj->GetUsageDescription()));
		
		$t->parse("ActiveCouponsBLout","ActiveCouponsBL",true);
	}
	
	if(empty($CouponIDarr)){
		$t->set_block("origPage","EmptyActiveCouponsBL","EmptyActiveCouponsBLout");
		$t->set_var("EmptyActiveCouponsBLout", "<font class='body'>There are currently no coupons activated for your account.</font>");
	}


	$emptyCouponsWithinPeriod = true;
	$t->set_block("origPage","CouponPeriodReportBL","CouponPeriodReportBLout");
	foreach($CouponIDarr as $thisCouponID){
	
		$CouponObj->LoadCouponByID($thisCouponID);
		$t->set_var("COUPON_NAME", $CouponObj->GetCouponCode());
		$t->set_var("COUPON_ID", $thisCouponID);
		
		$t->set_var("ORD_COUNT", $SalesCommissions_sub_Obj->GetNumOrdersFromSalesRep($SalesRepID, false, $thisCouponID));
		
		$t->set_var("COMM_GENERATED", $SalesCommissions_sub_Obj->GetTotalCommissionsWithinPeriodForUser($SalesRepID, "All", "GoodAndSuspended", $thisCouponID));
		
		$t->parse("CouponPeriodReportBLout","CouponPeriodReportBL",true);
		$emptyCouponsWithinPeriod = false;
	}
	
	if($emptyCouponsWithinPeriod){
		$t->set_block("origPage","EmptyCouponPeriodReportBL","EmptyCouponPeriodReportBLout");
		$t->set_var("EmptyCouponPeriodReportBLout", "<font class='body'>No coupons were used during this period.</font>");
	
	}
	
	
	// Don't show the Sales Chain Background or Block if there is only 1 person involved.
	if(sizeof($SalesCommissionsObj->GetChainFromSubRepToUser($SalesRepID)) == 1)
		$t->discard_block("origPage","SalesChain4BL");
	else
		$t->set_var("SALES_CHAIN", $SalesCommissionsObj->BuildSalesChainLinks($SalesRepID));

	$t->allowVariableToContainBrackets("SALES_CHAIN");
		
	// Get a list of any Sub-Reps for the Sales Person
	$t->set_block("origPage","CouponSubReps","CouponSubRepsout");
	$SubRepsArr = $SalesCommissionsObj->GetSubReps($SalesRepID);
	foreach($SubRepsArr as $SubRepID){
		$t->set_var("SUBREP_ID", $SubRepID);
		$t->set_var("SUBREP_NAME", WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, $SubRepID)));
		$t->parse("CouponSubRepsout","CouponSubReps",true);
	}
	if(empty($SubRepsArr))
		$t->discard_block("origPage", "EmptyCouponSubReps");


		
}
else if($view == "payments"){

	$NavigationObj->SetNameValuePairs(array("view"=>$view, "salesrep"=>$SalesRepID));
	$NavigationObj->SetBaseURL("./myaccount_sales.php");
	
	$t->set_var("DATE_SELECT", $NavigationObj->GetYearMonthSelectorHTML($month, $year, true));
	$t->allowVariableToContainBrackets("DATE_SELECT");
	
	if($month == "ALL")
		$t->set_var("REPORT_PERDIOD", "All of $year");
	else
		$t->set_var("REPORT_PERDIOD", date("F", mktime(1, 1, 1, $month, 1, $year)) . " $year");

	
	// Make sure the SalesRepID belongs to the User viewing this page.
	// $SalesRepID could be the same as the UserID if we are looking at our own orders.
	if(!$SalesCommissionsObj->CheckIfSalesRepBelongsToUser($SalesRepID)){
		WebUtil::PrintError("The Sales Rep ID is not correct.");
	}
	
	// Don't show the Sales Chain Background or Block if there is only 1 person involved.
	if(sizeof($SalesCommissionsObj->GetChainFromSubRepToUser($SalesRepID)) == 1){
		$t->set_block("origPage","SalesChain2BL","SalesChain2BLout");
		$t->set_var("SalesChain2BLout", "&nbsp;");
	}
	else{
		$t->set_var("SALES_CHAIN", $SalesCommissionsObj->BuildSalesChainLinks($SalesRepID));
	}
	
	$t->allowVariableToContainBrackets("SALES_CHAIN");

	
	$SalesPaymentsObj = new SalesCommissionPayments($dbCmd, $dbCmd2, Domain::oneDomain());
	
	$PaymentsArr = $SalesPaymentsObj->GetPaymentsWithinPeriodForUser($SalesRepID, $month, $year);


	$t->set_block("origPage","PaymentItemBL","PaymentItemBLout");
	foreach($PaymentsArr as $PaymentRow){
	
		// If we are retrying a payment, or the payment was denied, then cross out the amount so they won't think we miss-added the total
		if($PaymentRow["PayPalStatus"] == "T" || $PaymentRow["PayPalStatus"] == "D" || $PaymentRow["PayPalStatus"] == "R")
			$amnt = '<s>$' . number_format($PaymentRow["Amount"], 2) . "</s>";
		else
			$amnt = '$' . number_format($PaymentRow["Amount"], 2);
		
	
		$t->set_var("PAYMENT_DATE", date("n/j/y", $PaymentRow["DateCreatedUnix"]));
		$t->set_var("PAYMENT_STATUS", $PaymentRow["StatusDesc"]);
		$t->set_var("PAYMENT_AMOUNT", $amnt);
	
		$t->parse("PaymentItemBLout","PaymentItemBL",true);
	}
	
	if(empty($PaymentsArr)){
		$t->set_block("origPage","EmptyPaymentsBL","EmptyPaymentsBLout");
		$t->set_var("EmptyPaymentsBLout", "No payments have been made within this period.");
	}
	else{
	
		$t->set_var("TOTAL_PAID", '$' . $SalesPaymentsObj->GetPaymentTotalsWithinPeriodForUser($SalesRepID, $month, $year));
		$t->set_var("SALES_REP_NAME", WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, $SalesRepID)));
	}

	

	// Get a list of any Sub-Reps for the Sales Person
	$t->set_block("origPage","PaymentSubRepBL","PaymentSubRepBLout");
	$SubRepsArr = $SalesCommissionsObj->GetSubReps($SalesRepID);
	foreach($SubRepsArr as $SubRepID){
		$t->set_var("SUBREP_ID", $SubRepID);
		$t->set_var("SUBREP_NAME", WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, $SubRepID)));
		$t->parse("PaymentSubRepBLout","PaymentSubRepBL",true);
	}
	if(empty($SubRepsArr))
		$t->discard_block("origPage", "EmptyPaymentSubReps");
	

	// Find out how much commissions have been earned... but have not had payments issued yet
	// If we are SalesMaster then it will return all pending payments in the system
	$PendingPaymentsAmnt = $SalesPaymentsObj->GetTotalPendingPaymentsForUser($SalesRepID);
	if($PendingPaymentsAmnt == 0)
		$t->set_var("PENDING_PAYMENTS", "");
	else if($SalesRepID == 0)
		$t->set_var("PENDING_PAYMENTS", "<br><font class='SmallBody'>Pending Payments for All Sales Reps: \$" . number_format($PendingPaymentsAmnt, 2) . "</font>"); 
	else
		$t->set_var("PENDING_PAYMENTS", "<br><font class='SmallBody'>Commissions that have not been paid yet: \$" . number_format($PendingPaymentsAmnt, 2) . "</font>");

	$t->allowVariableToContainBrackets("PENDING_PAYMENTS");
		
	if($SalesMaster || $SalesRepObj2->getHaveReceivedW9() || $SalesRepObj2->getIsAnEmployee())
		$t->discard_block("origPage", "W9notReceivedBL");

	if($SalesMaster || $SalesRepObj2->getAddressIsVerified())
		$t->discard_block("origPage", "AddressNotVerifiedBL");
	else
		$t->set_var("RETURN_TIME", date("n/j/y", $SalesRepObj2->getAddressVerficationDeadlineTimestamp()));

	if($SalesMaster || $SalesRepObj2->getEmailIsVerified())
		$t->discard_block("origPage", "EmailNotVerifiedBL");
	else
		$t->set_var("SALES_EMAIL", $SalesRepObj2->getEmail());


	$t->set_var("ORDER_DETAIL_VIEW", "");
	$t->set_var("SUBREP_DETAIL_VIEW", "");


	$t->discard_block("origPage", "SalesReportBL");
	$t->discard_block("origPage", "InfoBL");
	$t->discard_block("origPage", "CouponReportBL");
	$t->discard_block("origPage", "TermsBL");
	$t->discard_block("origPage", "LinkingBL");
	

}
else if($view == "terms"){
	$t->discard_block("origPage", "SalesReportBL");
	$t->discard_block("origPage", "InfoBL");
	$t->discard_block("origPage", "CouponReportBL");
	$t->discard_block("origPage", "PaymentsBL");
	$t->discard_block("origPage", "LinkingBL");
}
else if($view == "linking"){
	$t->discard_block("origPage", "SalesReportBL");
	$t->discard_block("origPage", "InfoBL");
	$t->discard_block("origPage", "CouponReportBL");
	$t->discard_block("origPage", "PaymentsBL");
	$t->discard_block("origPage", "TermsBL");
	
	// Process Passed Report Parameters--#
	$ReportPeriodIsDateRange = WebUtil::GetInput( "PeriodType", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES ) == "DATERANGE";
	$TimeFrameSel = WebUtil::GetInput( "TimeFrame", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "TODAY"  );

	$DateMessage = "";

	$date = getdate();
	$startday= WebUtil::GetInput( "DateRangeStartDay", FILTER_SANITIZE_INT, "1" );
	$startmonth= WebUtil::GetInput( "DateRangeStartMonth", FILTER_SANITIZE_INT, $date["mon"] );
	$startyear= WebUtil::GetInput( "DateRangeStartYear", FILTER_SANITIZE_INT, $date["year"] );
	$endday= WebUtil::GetInput( "DateRangeEndDay", FILTER_SANITIZE_INT, $date["mday"] );
	$endmonth= WebUtil::GetInput( "DateRangeEndMonth", FILTER_SANITIZE_INT, $date["mon"] );
	$endyear= WebUtil::GetInput( "DateRangeEndYear", FILTER_SANITIZE_INT, $date["year"] );

	// Format the dates that we want for MySQL for the date range ----#
	if( $ReportPeriodIsDateRange )
	{
		$start_timestamp = mktime (0,0,0,$startmonth,$startday,$startyear);
		$end_timestamp = mktime (23,59,59,$endmonth,$endday,$endyear);

		if(  $start_timestamp >  $end_timestamp  )	
			$DateMessage = "Invalid Date Range Specified - Unable to Generate Report";
	}
	else
	{
		$ReportPeriod = Widgets::GetTimeFrame( $TimeFrameSel );
		$start_timestamp = $ReportPeriod[ "STARTDATE" ];
		$end_timestamp = $ReportPeriod[ "ENDDATE" ];
	}

	$start_mysql_timestamp = date("YmdHis", $start_timestamp);
	$end_mysql_timestamp  = date("YmdHis", $end_timestamp);


	#---- Setup date range selections and and type -----##
	$t->set_var( "PERIODTYPETIMEFRAME", $ReportPeriodIsDateRange ? null : "CHECKED" );
	$t->set_var( "PERIODISTIMEFRAME", $ReportPeriodIsDateRange ? "false" : "true" );
	$t->set_var( "TIMEFRAMESELS", Widgets::BuildTimeFrameSelect( $TimeFrameSel ));
	$t->set_var( "PERIODTYPEDATERANGE", $ReportPeriodIsDateRange ? "CHECKED" : null );
	$t->set_var( "DATERANGESELS", Widgets::BuildDateRangeSelect( $start_timestamp, $end_timestamp, "D" ));
	$t->set_var( "DATE_MESSAGE", $DateMessage );

	$t->allowVariableToContainBrackets("TIMEFRAMESELS");
	$t->allowVariableToContainBrackets("DATERANGESELS");
	$t->allowVariableToContainBrackets("PERIODTYPEDATERANGE");


	$t->set_block("origPage","ClickThroughBL","ClickThroughBLout");

	$EmptyClicks = true;

	// Get a unique list of names from our salesbannerlog
	$dbCmd->Query("SELECT DISTINCT Name FROM salesbannerlog WHERE SalesUserID=$SalesRepID");
	while($BannerName = $dbCmd->GetValue()){

		// Do a nested query to figure out how many clicks each name received
		$dbCmd2->Query("SELECT COUNT(*) FROM salesbannerlog WHERE Name=\"" . DbCmd::EscapeSQL($BannerName)  . "\"
				AND (Date BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") AND SalesUserID=$SalesRepID");
		$BannerClicks = $dbCmd2->GetValue();

		if($BannerClicks == 0)
			continue;

		// Find out the unique visitors from this banner --#
		$dbCmd2->Query("Select DISTINCT IPaddress FROM salesbannerlog WHERE Name=\"" . DbCmd::EscapeSQL($BannerName)  . "\"
				AND (Date BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") AND SalesUserID=$SalesRepID");
		$BannerVisitors = $dbCmd2->GetNumRows();


		// Figure out how many purchases each name received --#
		$dbCmd2->Query("SELECT COUNT(*) FROM orders INNER JOIN users ON orders.UserID = users.ID WHERE users.SalesRep=$SalesRepID AND orders.Referral=\"" . DbCmd::EscapeSQL($BannerName)  . "\"
				AND (orders.DateOrdered BETWEEN " . $start_mysql_timestamp . " AND " . $end_mysql_timestamp . ") ");
		$BannerPurchases = $dbCmd2->GetValue();
		

		$retentionPercent = round($BannerPurchases / $BannerClicks * 100, 1);
		$AverageClicksPerPerson = round($BannerClicks/$BannerVisitors,1);

		$t->set_var(array(
			"CT_NAME"=>WebUtil::htmlOutput($BannerName), 
			"CT_CLICK"=>$BannerClicks, 
			"CT_PURCHASE"=>$BannerPurchases, 
			"CT_PERCENT"=>$retentionPercent, 
			"CT_VISITORS"=>$BannerVisitors, 
			"CT_CLICKSPERSON"=>$AverageClicksPerPerson
			));

		$t->parse("ClickThroughBLout","ClickThroughBL",true);
		
		$EmptyClicks = false;
	}
	
	if(!empty($DateMessage))
		$t->discard_block("origPage","VIEW_BannersBL");
	else if($EmptyClicks){
		$t->set_block("origPage","VIEW_BannersBL","VIEW_BannersBLout");
		$t->set_var( "VIEW_BannersBLout", "<br><br>There were no clicks during this period" );
	}
	

	// Don't show the Sales Chain Background or Block if there is only 1 person involved.
	if(sizeof($SalesCommissionsObj->GetChainFromSubRepToUser($SalesRepID)) == 1)
		$t->discard_block("origPage","SalesChain3BL");
	else
		$t->set_var("SALES_CHAIN", $SalesCommissionsObj->BuildSalesChainLinks($SalesRepID));

	$t->allowVariableToContainBrackets("SALES_CHAIN");
		
	// Get a list of any Sub-Reps for the Sales Person
	$t->set_block("origPage","WebsiteLinkingSubReps","WebsiteLinkingSubRepsout");
	$SubRepsArr = $SalesCommissionsObj->GetSubReps($SalesRepID);
	foreach($SubRepsArr as $SubRepID){
		$t->set_var("SUBREP_ID", $SubRepID);
		$t->set_var("SUBREP_NAME", WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, $SubRepID)));
		$t->parse("WebsiteLinkingSubRepsout","WebsiteLinkingSubReps",true);
	}
	if(empty($SubRepsArr))
		$t->discard_block("origPage", "EmptyWebsiteLinkingSubReps");

}
else{
	WebUtil::PrintError("Illegal View type");
}


// To preven a Javascript error..  The Var is not set on the other pages.
if($view != "linking")
	$t->set_var( "PERIODISTIMEFRAME", "false");


$t->pparse("OUT","origPage");


?>
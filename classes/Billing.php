<?

class Billing {
	
	
	// This is a little bit messy.  I still haven't figured out a good way to implement the MVC architecture yet.
	// Call this function to get the HTML for "Corporate billing history".
	// The HTML may be implanted within Admin user screens of the "My Account" area for the customer
	static function GetBillingHistory(DbCmd $dbCmd, &$PaymentInvoiceObj, $scriptName, $additionURLnameValueArr, $billingview, $month, $year, $OrderLink){
	
	
		$currentDay = date("j");
		$currentMonth = date("n");
		$currentYear = date("Y");
	
	
		$t_v = new Templatex(".","keep");
	
		##--- Set up the HTMl template --#
		$t_v->set_file(array("BillingPage"=>"./billing_history-template.html"));
	
	
		if($billingview == "billed" && !$PaymentInvoiceObj->CheckIfDateIsValidForMonthReport($year, $month)){
			$ErrorMessage = "This date is too far in the future. To see the latest activity on your account click on Unbilled Activity.";
			WebUtil::PrintError($ErrorMessage );
			exit;
		}
		
		// Determine what should happen when a user clicks on the Order Number.  They may either be shown and invoice or taken to the order screen
	
		$t_v->set_block("BillingPage","InvoiceLinkBL","InvoiceLinkBLout");
		$t_v->set_block("BillingPage","OrderLinkBL","OrderLinkBLout");
		if($OrderLink == "invoice"){
			$t_v->set_var("OrderLinkBLout", "");
			$t_v->parse("InvoiceLinkBLout","InvoiceLinkBL",true);
		}
		else if($OrderLink == "orderpage"){
			$t_v->set_var("InvoiceLinkBLout", "");
			$t_v->parse("OrderLinkBLout","OrderLinkBL",true);
		}
		else
			throw new Exception("Problem with the OrderLink type in the function call to Billing::GetBillingHistory");
	
	
	
		$additionURLparameters = "";
		foreach($additionURLnameValueArr as $thisKey => $thisVal){
			$additionURLparameters .= "&$thisKey=" . urlencode($thisVal);
		}
		
	
		// Build the tabs
		$baseTabUrl = "./" . $scriptName . "?x=x" . $additionURLparameters . "&billingview=";
		$t_vabsObj = new Navigation();
		$t_vabsObj->AddTab("billed", "Month Statements", ($baseTabUrl . "billed"));
		$t_vabsObj->AddTab("unbilled", "Unbilled Activity", ($baseTabUrl . "unbilled"));
		$t_v->set_var("NAV_BAR_HTML", $t_vabsObj->GetTabsHTML($billingview));
		
		$t_v->allowVariableToContainBrackets("NAV_BAR_HTML");
	
	
	
		if($billingview == "unbilled"){
			$t_v->discard_block("BillingPage", "DateSelectBlock");
			$t_v->discard_block("BillingPage", "BilledSummaryTable");
	
			// If we are looking at unbilled activity... then go up to the day.
			$month = $currentMonth;
			$year = $currentYear;		
			$DayParameter = $currentDay; 
		}
		else if($billingview == "billed"){
			$t_v->discard_block("BillingPage", "UnbilledMessageBL");
			$t_v->discard_block("BillingPage", "UnBilledSummaryTable");
	
			// Put in the Date Selection Control
			$NavigationObj = new Navigation();
	
			// Just becuase next month has "pending" payments doesn't mean that we can show them a month bill
			// Take away the ability to navigate to the following month if it is not past the Statement closing day (stop 1 month early)
			$NavigationObj->SetStopMonth($PaymentInvoiceObj->GetStopMonth());
			$NavigationObj->SetStopYear($PaymentInvoiceObj->GetStopYear());
	
	
			// We want to merge our "bilingview" paramter into the list of NameValue pairs that are to be included already for the date control
			$additionURLnameValueArr = array_merge($additionURLnameValueArr, array("billingview"=>$billingview));
	
			$NavigationObj->SetNameValuePairs($additionURLnameValueArr);
	
			$NavigationObj->SetBaseURL("./" . $scriptName);
			$t_v->set_var("DATE_SELECT", $NavigationObj->GetYearMonthSelectorHTML($month, $year));
			$t_v->allowVariableToContainBrackets("DATE_SELECT");
	
			// Leave off the day parameter... let it default to the month and year combo.
			$DayParameter = "";
	
	
		}
		else{
			$ErrorMessage = "Illegal Billing View type";
			WebUtil::PrintError($ErrorMessage );
			exit;
		}
	
		$StartingBalance = $PaymentInvoiceObj->GetStartingBalance($year, $month, $DayParameter);
		$PaymentsReceived = $PaymentInvoiceObj->GetMonthPaymentsReceived($year, $month, $DayParameter);
		$NewBalance = $PaymentInvoiceObj->GetCurrentBalance($year, $month, $DayParameter);
		$NewCharges = $PaymentInvoiceObj->GetMonthCharges($year, $month, $DayParameter);
	
		$t_v->set_var("NEW_BALANCE", Widgets::GetColoredPrice($NewBalance));
		$t_v->set_var("PREV_BALANCE", Widgets::GetColoredPrice($StartingBalance));
		$t_v->set_var("PAY_ACTIVITY", Widgets::GetColoredPrice($PaymentsReceived));
		$t_v->set_var("NEW_CHARGES", Widgets::GetColoredPrice($NewCharges));
	
	
		$OrderInfoArr = $PaymentInvoiceObj->GetMonthOrderHistory($year, $month, $DayParameter);
		$PaymentReceivedArr = $PaymentInvoiceObj->GetMonthPaymentHistory($year, $month, $DayParameter);
		$AdjustmentHistoryArr = $PaymentInvoiceObj->GetMonthAdjustmentsHistory($year, $month, $DayParameter);
	
		$t_v->set_block("BillingPage","OrdersBL","OrdersBLout");
		$OrdersTotal = 0;
		foreach($OrderInfoArr as $t_vhisOrderRow){
	
			$t_v->set_var("ORDER_NUM", WebUtil::htmlOutput(Order::GetHashedOrderNo($t_vhisOrderRow["OrderID"])));
			$t_v->set_var("ORDER_ID", $t_vhisOrderRow["OrderID"]);
			$t_v->set_var("ORDER_DATE",  date("M j, Y", $t_vhisOrderRow["DateOrdered"]));
			$t_v->set_var("ORDER_NOTES",  WebUtil::htmlOutput($t_vhisOrderRow["InvoiceNote"]));
	
			$t_v->set_var("ORDER_AMOUNT", '$' . WebUtil::htmlOutput($t_vhisOrderRow["OrderTotal"]));
	
			if($t_vhisOrderRow["Pending"])
				$t_v->set_var("PENDING", "<font class='SmallBody'>(pending)</font>");
			else
				$t_v->set_var("PENDING", "");
	
			$OrdersTotal += $t_vhisOrderRow["OrderTotal"];
	
	
			$t_v->parse("OrdersBLout","OrdersBL",true);
		}
		$t_v->set_var("ORDERS_TOTAL", Widgets::GetColoredPrice($OrdersTotal));
	
	
	
		$t_v->set_block("BillingPage","PaymentsBL","PaymentsBLout");
		$PaymentsTotal = 0;
		foreach($PaymentReceivedArr as $t_vhisPaymentRow){
	
			$t_v->set_var("PAYMENT_DATE", date("M j, Y", $t_vhisPaymentRow["UnixDate"]));
			$t_v->set_var("PAYMENT_NOTES", WebUtil::htmlOutput($t_vhisPaymentRow["Notes"]));
			$t_v->set_var("PAYMENT_AMOUNT", Widgets::GetColoredPrice($t_vhisPaymentRow["Amount"]));
			$PaymentsTotal += $t_vhisPaymentRow["Amount"];
	
			$t_v->parse("PaymentsBLout","PaymentsBL",true);
		}
		$t_v->set_var("PAYMENTS_TOTAL", Widgets::GetColoredPrice($PaymentsTotal));
	
	
	
		$t_v->set_block("BillingPage","AdjustmentBL","AdjustmentBLout");
		$AdjustmentsTotal = 0;
		foreach($AdjustmentHistoryArr as $t_vhisAdjustmentRow){
	
			$t_v->set_var("ADJUSTMENT_DATE", date("M j, Y", $t_vhisAdjustmentRow["DateCreated"]));
			$t_v->set_var("ADJUSTMENT_NOTES", WebUtil::htmlOutput($t_vhisAdjustmentRow["Description"]));
			$t_v->set_var("ADJUSTMENT_AMOUNT", Widgets::GetColoredPrice($t_vhisAdjustmentRow["Amount"]));
			$t_v->set_var("ADJUSTMENT_ORDERID", Order::GetHashedOrderNo($t_vhisAdjustmentRow["OrderID"]));
			$AdjustmentsTotal += $t_vhisAdjustmentRow["Amount"];
	
			$t_v->parse("AdjustmentBLout","AdjustmentBL",true);
		}
		$t_v->set_var("ADJUSTMENTS_TOTAL", Widgets::GetColoredPrice($AdjustmentsTotal));
	
	
		// Hide the various report blocks if there are no results to display inside.
		if(sizeof($PaymentReceivedArr) == 0)
			$t_v->discard_block("BillingPage", "HidePaymentsBL");
		if(sizeof($OrderInfoArr) == 0)
			$t_v->discard_block("BillingPage", "HideOrdersBL");
		if(sizeof($AdjustmentHistoryArr) == 0)
			$t_v->discard_block("BillingPage", "HideAdjustmentsBL");
	
	
		if(sizeof($PaymentReceivedArr) == 0 && sizeof($OrderInfoArr) == 0 && sizeof($AdjustmentHistoryArr) == 0)
			$t_v->set_var("EMPTY_MESSAGE", "<br><br><b>There has been no activity during this period.</b><br><br><br><br>");
		else
			$t_v->set_var("EMPTY_MESSAGE", "");
	
	
		$t_v->set_var("CLOSING_DATE", date("M j, Y", mktime(1,0,0, $month, $PaymentInvoiceObj->GetStatementClosingDay(), $year)));
	
		$t_v->set_var("DUEDATE", date("M j, Y", mktime(1,0,0, ($month+1), $PaymentInvoiceObj->GetDueDay(), $year)));
	
	
		return $t_v->finish($t_v->parse("OUT", "BillingPage"));
	
	}
}

?>
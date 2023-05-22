<?

// This class should be used for corporate invoicing... if they are not using a credit card.
// It will work like a payment gateway and accept or decline requests for authorizing funds, based upon the users credit limit and usage
// Provides methods for getting billing history and usage amounts, that can be used for generating invoices, etc.
class PaymentInvoice {


	private $_dbCmd;
	private $_UserControlObj;
	private $_TransactionStatusChar;
	private $_ErrorReason;
	private $_ResponseReasonText;
	private $_AuthorizeAmount;
	private $_statementClosingDay;
	private $_dueDay;
	private $_CurrentYear;
	private $_CurrentMonth;
	private $_CurrentDay;
	private $_StopYear;
	private $_StopMonth;
	
	
	###-- Constructor --###
	function PaymentInvoice(){

		$this->_dbCmd = new DbCmd();
		$dbCmd2 = new DbCmd();
		$this->_UserControlObj = new UserControl($dbCmd2);
		
		$this->_AuthorizeAmount = "";
		
		// The statementClosingDate should not have any charges on that date.
		// It should include all charges from the day before... ranging back to the previous month's StatementClosingDate
		$this->_statementClosingDay = 23;
		$this->_dueDay = 17;
		
		$this->_CurrentYear = date("Y");
		$this->_CurrentMonth = date("n");
		$this->_CurrentDay = date("j");
		
		
		// Will basically tell us... what is the last month and year that is ready to have a full month report generated
		if($this->_CurrentDay < $this->_statementClosingDay){

			$OneMonthBack = $this->RollBackMonths($this->_CurrentMonth, $this->_CurrentYear, 1);

			$this->_StopYear = $OneMonthBack["Year"];
			$this->_StopMonth = $OneMonthBack["Month"];	
		}
		else{
			$this->_StopMonth = $this->_CurrentMonth;
			$this->_StopYear = $this->_CurrentYear;
		}
	

	}

	function LoadCustomerByID($UserID){	
		$this->_UserControlObj->LoadUserByID($UserID);
	}
	
	function GetStatementClosingDay(){
		return $this->_statementClosingDay;
	}
	function GetDueDay(){
		return $this->_dueDay;
	}
	
	function GetStopMonth(){
		return $this->_StopMonth;
	}
	
	function GetStopYear(){
		return $this->_StopYear;
	
	}
	
	function SetStopMonth($month){
		$month = intval($month);
		if($month < 1 || $month > 12 )
			throw new Exception("Illegal Month set");
		return $this->_StopMonth = $month;
	}
	
	function SetStopYear($year){
		$year = intval($year);
		if($year < 2000 || $year > 3000 )
			throw new Exception("Illegal Year set");
		return $this->_StopYear = $year;
	
	}
	
	// Consdiers the current date, and where we are with relation to the Statement Closing Day
	// Pass in a Year and Month and it will tell you a full month report is ready or not
	function CheckIfDateIsValidForMonthReport($year, $month){

		// First check that it is not a month/year in the future
		$PastMonthAndYearFlag = $month > $this->_CurrentMonth && $this->_CurrentYear == $year;
		$PastYearFlag = $year > $this->_CurrentYear;

		if($PastMonthAndYearFlag || $PastYearFlag)
			return false;
			
		// Now check if we are in the same month/year... but not past the Statement Closing Day
		if($this->_CurrentYear == $year && $this->_CurrentMonth == $month && $this->_CurrentDay < $this->_statementClosingDay){

			return false;
		}
			
		return true;
	}



	#-- If PHP 4.3 supported interfaces, I would probably enforce all PaymentGateways / Corporate Billing Classes to support the following methods

	##---- Begin Gateway Interface ---- ##

	function AuthorizeOnly(){
	
		if($this->_AuthorizeAmount == 0)
			throw new Exception("You can not AuthorizeOnly with a zero payment");

		$this->_EnsureTransactionInfoHasBeenSet();
		
		if( ($this->_AuthorizeAmount + $this->GetCurrentCreditUsage()) > $this->_UserControlObj->getCreditLimit() ){
			$this->_ErrorReason = "It looks like you have gone over your credit limit with us.  Please contact Customer Service for assistance.";
			$this->_ResponseReasonText = "Over Credit Limit";
			$this->_TransactionStatusChar = "D";
			return false;
		}
		else{
			$this->_ErrorReason = "";
			$this->_ResponseReasonText = "";
			$this->_TransactionStatusChar = "A";
			return true;
		}

	}
	
	// For purposes of our invoice system there is no need to caputres the funds at a later time
	// Before we authorize something through our invoice system... we just want to make sure they are not over their credit limit.
	function AuthorizeCapture(){
		return $this->AuthorizeOnly();
	}
	
	// Credits and Prior Auth captures will always work with our invoice system, unlike other Payment Gateways
	function PriorAuthCapture($TransactionID){
		$this->_ErrorReason = "";
		$this->_ResponseReasonText = "";
		$this->_TransactionStatusChar = "A";
		return true;
	}
	function Credit($TransactionID){
		$this->_ErrorReason = "";
		$this->_ResponseReasonText = "";
		$this->_TransactionStatusChar = "A";
		return true;
	}	

	

	// Not used by our PaymentInvoice class.  We are not communicating with another server for coporate billing, so we don't need a "receipt".
	// However the Payment class wants some record of a transaction with whatever gateway that it is using.
	// Let's just always send back "111"... which is just an arbitrary number.  
	function GetTransactionID(){
		return 111;
	}
	
	// Return 'A'accepted, 'D'eclined, or 'E'rror
	function GetTransactionStatus(){
		return $this->_TransactionStatusChar;
	}
	
	// Returns an HTML string, with complete sentence(s) ... ready to be returned directly to the user
	function GetErrorReason(){
		return $this->_ErrorReason;
	}
	
	// Not used by our PaymentInvoice class
	function GetApprovalCode(){
		return 111;
	}
	// A 1 character description of an error or declined reasoning.
	// It is not really used by our corporate invoice system so just return an "X" character for now.
	function GetResponseReasonCode(){
		return "X";
	}
	// A short description of the error or declined reasoning
	function GetResponseReasonText(){
		return $this->_ResponseReasonText;
	}
	
	
	##---- END Gateway Interface ---- ##





	// Gets the Total amount of orders from the begining of time, up till the specifiec date
	// Includes "Pending" orders and "Balance Adjustments" (including refunds).
	// If "Day" is omitted, then it will go up to the StatementClosingDate in the given Month/Year
	function GetTotalCharges($Year, $Month, $Day=""){
		
		$this->_EnsureUserHasBeenLoaded();

		// To make things easier... just make the start time in 1990... to simulate begining of time.  The Internet was around then ... so no orders should have been placed
		$StartTimeStamp = $this->_TimeStampStart(1990, 1, 1);
		$EndTimeStamp = $this->_TimeStampEnd($Year, $Month, $Day);
		
		return $this->_GetCorporateOrderTotalsPlusBalanceAdjustments($StartTimeStamp, $EndTimeStamp);		
	}


	// Gets the Total amount of Payments received from the begining of time, up till the specifiec date
	// If "Day" is omitted, then it will go up to the StatementClosingDate in the given Month/Year
	function GetTotalPaymentsReceived($Year, $Month, $Day=""){
	
		$this->_EnsureUserHasBeenLoaded();
		
		$EndMysqlTimeStamp = $this->_GetMysqlTimeStamp($this->_TimeStampEnd($Year, $Month, $Day));
		$UserID = $this->_UserControlObj->getUserID();
		
		$this->_dbCmd->Query("SELECT SUM(Amount) FROM paymentsreceived WHERE CustomerID=$UserID AND Date < $EndMysqlTimeStamp");
		$total = $this->_dbCmd->GetValue();
		if(empty($total))
			$total = 0;
			
		return $this->_FormatPrice($total);
	}
	

	// Will return the charges made in the current billing month.
	// Includes "Pending" orders and "Balance Adjustments" (including refunds).
	// If "Day" is omitted, then it will go back to the StatementClosingDate in the given Month/Year ... and look back 1 month prior
	function GetMonthCharges($Year, $Month, $Day=""){
		
		$this->_EnsureUserHasBeenLoaded();
	
		$StartTimeStamp = $this->_TimeStampStart($Year, $Month, $Day);
		$EndTimeStamp = $this->_TimeStampEnd($Year, $Month, $Day);

		return $this->_GetCorporateOrderTotalsPlusBalanceAdjustments($StartTimeStamp, $EndTimeStamp);
	}


	// Will return the payments received in the current month... ranging back to the StatmentClosingDate
	// If "Day" is omitted, then it will go back to the StatementClosingDate in the given Month/Year ... and look back 1 month prior
	function GetMonthPaymentsReceived($Year, $Month, $Day=""){
		
		$this->_EnsureUserHasBeenLoaded();
		
		$StartMysqlTimeStamp = $this->_GetMysqlTimeStamp($this->_TimeStampStart($Year, $Month, $Day));
		$EndMysqlTimeStamp = $this->_GetMysqlTimeStamp($this->_TimeStampEnd($Year, $Month, $Day));
		$UserID = $this->_UserControlObj->getUserID();
		
		$this->_dbCmd->Query("SELECT SUM(Amount) FROM paymentsreceived WHERE CustomerID=$UserID AND Date BETWEEN $StartMysqlTimeStamp AND $EndMysqlTimeStamp");
		$total = $this->_dbCmd->GetValue();
		if(empty($total))
			$total = 0;
			
		return $this->_FormatPrice($total);
	}



	
	// The starting balance shows how much credit is being used up, from the end or the PRIOR month (or last closing date).
	// If day is specified... then it gets the StartingBalance from the most recent closing date
	// If the balance is greater than 0, then they are behind with their payments
	function GetStartingBalance($Year, $Month, $Day=""){

		$this->_EnsureUserHasBeenLoaded();
		
		$OneMonthBack = $this->RollBackMonths($Month, $Year, 1);

		// If the day is less than our closing Day... or we did not specifify a day... 
		// they we want the Closing Date balance from 1 month prior
		if(empty($Day) || $Day < $this->_statementClosingDay){
			$Yr = $OneMonthBack["Year"];
			$Mnt = $OneMonthBack["Month"];
		}
		else{
			$Yr = $Year;
			$Mnt = $Month;
		}

		$TotalCharges = $this->GetTotalCharges($Yr, $Mnt);
		$TotalPayments = $this->GetTotalPaymentsReceived($Yr, $Mnt);
	
		
		return $this->_FormatPrice(($TotalCharges - $TotalPayments));
	}
	
	// Returns what the current balance (or credit usage) on any given day.
	function GetCurrentBalance($Year, $Month, $Day=""){
	
		$Total = $this->GetStartingBalance($Year, $Month, $Day);
		$Total += $this->GetMonthCharges($Year, $Month, $Day);
		$Total -= $this->GetMonthPaymentsReceived($Year, $Month, $Day);
		
		return $Total;
	}


	// Returns an array of hashes with 1 month's (or less) worth of billing charges for orders.
	// In includes "Pending" orders
	// If "Day" is omitted, then it will go from the StatementClosingDate in the given Month/Year to the StatementClosingDate of the previous month
	// If a "Day" is specified... then it will go from the current Day/Month/Year back to the most recent StatementClosingDate
	function GetMonthOrderHistory($Year, $Month, $Day=""){
		
		$this->_EnsureUserHasBeenLoaded();

		$OrdIDarr =& $this->_GetCorporateOrderIDsWithinTimeRange($this->_TimeStampStart($Year, $Month, $Day), $this->_TimeStampEnd($Year, $Month, $Day));

		$RetArr = array();

		foreach($OrdIDarr as $thisOrdID){
		
			// Skip this order if there are no projects inside
			if(!Order::CheckForActiveProjectWithinOrder($this->_dbCmd, $thisOrdID))
				continue;
				
			if(Order::CheckIfOrderComplete($this->_dbCmd, $thisOrdID))
				$PendingFlag = false;
			else
				$PendingFlag = true;
		
			$this->_dbCmd->Query("SELECT *, UNIX_TIMESTAMP(DateOrdered) AS UnixDateOrdered FROM orders WHERE ID=$thisOrdID");
			$OrderRow = $this->_dbCmd->GetRow();
			
			$OrderTotal = Order::GetGrandTotalOfOrder($this->_dbCmd, $thisOrdID);
			
			$RetArr[] = array( "OrderID"=>$thisOrdID, "DateOrdered"=>$OrderRow["UnixDateOrdered"], 
						"Pending"=>$PendingFlag, "OrderTotal"=>$OrderTotal, "InvoiceNote"=>$OrderRow["InvoiceNote"] );
		}
	
		return $RetArr;
		
	}

	// Returns an array of hashes with 1 month's (or less) worth of Balance Adjustments... related to orders with "Corporate Billing".
	// If "Day" is omitted, then it will go from the StatementClosingDate in the given Month/Year to the StatementClosingDate of the previous month
	// If a "Day" is specified... then it will go from the current Day/Month/Year back to the most recent StatementClosingDate
	function GetMonthAdjustmentsHistory($Year, $Month, $Day=""){
		
		$this->_EnsureUserHasBeenLoaded();

		$StartTimeStamp = $this->_TimeStampStart($Year, $Month, $Day);
		$EndTimeStamp = $this->_TimeStampEnd($Year, $Month, $Day);
		
		$RetArr = array();
		
		$BalanceAdjstArr =& $this->_GetCorporateBalanceAdjustmentIDsWithinTimeRange($StartTimeStamp, $EndTimeStamp);
		foreach($BalanceAdjstArr as $bID){
		
			$this->_dbCmd->Query("SELECT *, UNIX_TIMESTAMP(DateCreated) AS UnixDateCreated FROM balanceadjustments WHERE ID=$bID");
			$BalanceAdjustRow = $this->_dbCmd->GetRow();
			
			$RetArr[] = array( "ID"=>$bID,
						"OrderID"=>$BalanceAdjustRow["OrderID"], 
						"DateCreated"=>$BalanceAdjustRow["UnixDateCreated"], 
						"Description"=>$BalanceAdjustRow["Description"], 
						"Amount"=>$BalanceAdjustRow["CustomerAdjustment"] );
		}
		
		return $RetArr;
	}


	// Returns a hash with 1 month's worth of Payments received
	// If "Day" is omitted, then it will go from the StatementClosingDate in the given Month/Year to the StatementClosingDate of the previous month
	// If a "Day" is specified... then it will go from the current Day/Month/Year back to the most recent StatementClosingDate
	function GetMonthPaymentHistory($Year, $Month, $Day=""){
		
		$this->_EnsureUserHasBeenLoaded();

		$StartTime = $this->_GetMysqlTimeStamp($this->_TimeStampStart($Year, $Month, $Day));
		$EndTime = $this->_GetMysqlTimeStamp($this->_TimeStampEnd($Year, $Month, $Day));
		$UserID = $this->_UserControlObj->getUserID();
		
		$RetArr = array();
		
		$this->_dbCmd->Query("SELECT *, UNIX_TIMESTAMP(Date) AS UnixDate FROM paymentsreceived WHERE CustomerID=$UserID AND DATE BETWEEN $StartTime AND $EndTime ORDER BY ID DESC");
		while($PaymentRow = $this->_dbCmd->GetRow())
			$RetArr[] = $PaymentRow;
		
		return $RetArr;
		
	}


	// Returns a hash with the most recent payment received
	// You can specify how many you want to see... defaults to the last 10
	function GetMostRecentPayments($NumberOfPayments=10){
		
		$this->_ValidateDigit($NumberOfPayments);
		$this->_EnsureUserHasBeenLoaded();
		$UserID = $this->_UserControlObj->getUserID();
		
		$RetArr = array();
		
		$this->_dbCmd->Query("SELECT *, UNIX_TIMESTAMP(Date) AS UnixDate FROM paymentsreceived WHERE CustomerID=$UserID ORDER BY Date DESC LIMIT $NumberOfPayments");
		while($PaymentRow = $this->_dbCmd->GetRow())
			$RetArr[] = $PaymentRow;
		
		return $RetArr;	
	}

	// Returns what the outstanding balance from this cusotmer is at the current moment in time
	function GetCurrentCreditUsage(){
	
		$TotalCharges = $this->GetTotalCharges($this->_CurrentYear, $this->_CurrentMonth, $this->_CurrentDay);
		$TotalPayments = $this->GetTotalPaymentsReceived($this->_CurrentYear, $this->_CurrentMonth, $this->_CurrentDay);
		
		return $this->_FormatPrice(($TotalCharges - $TotalPayments));
	}
	
	// Returns the date of the last payment in a unix timestamp
	// A negative balance adjustment (or a refund) also counts as a payment
	// The method will return the time stamp of whatever is more recent... The last payment or the last refund.
	// Returns Zero if no payment has been made
	function GetLastPaymentDate(){
	
		$LastRefundDate = 0;
	
		// Get the most recent date of a balance adjustment from the customer if it was a refund.
		$query = "SELECT UNIX_TIMESTAMP(BA.DateCreated) AS Date FROM balanceadjustments AS BA INNER JOIN orders ON BA.OrderID = orders.ID
				WHERE BA.VendorID=0 AND BA.CustomerAdjustment < 0  AND orders.UserID = " . $this->_UserControlObj->getUserID() . " ORDER BY BA.ID DESC LIMIT 1"; 

		$this->_dbCmd->Query($query);
		
		if($this->_dbCmd->GetNumRows() == 1)
			$LastRefundDate = $this->_dbCmd->GetValue();
	
		$PaymentRow = $this->GetMostRecentPayments(1);

		if(sizeof($PaymentRow) == 0)
			return $LastRefundDate;
		else{
			if($PaymentRow[0]["UnixDate"] > $LastRefundDate)
				return $PaymentRow[0]["UnixDate"];
			else
				return $LastRefundDate;
		}
	}


	// Pass in a Month, Year, and the amount of months to roll back.  
	// It will return a hash with an integer for the new year and month 
	function RollBackMonths($Month, $Year, $RollBackMonthsAmount){

		$TimeSmp = mktime(5, 5, 5, ($Month - $RollBackMonthsAmount), 5, $Year);
		return array("Year"=> date("Y", $TimeSmp), "Month"=>date("n", $TimeSmp));

	}	



	#---------  Set Methods below ------#

	// Set the price of the amount we are trying to authorize for the customer
	function SetAuthorizationAmount($Amount){
		$this->_AuthorizeAmount = $this->_FormatPrice($Amount);
	}

	// Will record that a payment was received for a customer.
	// A description is optional... pass in a blank string if there is none.
	// We also need to record the Employee that records this.
	function SetPaymentReceived($Amount, $notes, $EmployeeUserID){
		
		$this->_EnsureUserHasBeenLoaded();
		
		$notes = trim($notes);
		$Amount = $this->_FormatPrice($Amount);
		
		if($Amount < 0 && empty($notes))
			throw new Exception("Negative Payments must be accompanied by a note explaining why.");
		
		if(strlen($notes) > 250)
			throw new Exception("The Notes field is too long.");
		
		$InsertArr = array();
		$InsertArr["CustomerID"] = $this->_UserControlObj->getUserID();
		$InsertArr["Amount"] = $this->_FormatPrice($Amount);
		$InsertArr["Notes"] = $notes;
		$InsertArr["EmployeeUserID"] = $EmployeeUserID;
		$InsertArr["Date"] = $this->_GetMysqlTimeStamp(time());
		
		$this->_dbCmd->InsertQuery("paymentsreceived", $InsertArr);
	}







	#---------  Private Methods below ------#




	function _EnsureTransactionInfoHasBeenSet(){
			
		if(empty($this->_AuthorizeAmount))
			throw new Exception("Transaction amount has not been set.");
			
		$this->_EnsureUserHasBeenLoaded();
	}
	
	function _EnsureUserHasBeenLoaded(){
			
		if(!$this->_UserControlObj->CheckIfUserDataLoaded())
			throw new Exception("A customer has not been loaded yet.");
	}


	// Returns a decimal number without any commas in the thousands place
	// Also Ensures the number is in a correct format
	function _FormatPrice($ThePrice){

		$FormatedPrice = number_format($ThePrice, 2, '.', '');
		
		if(!preg_match("/^-?((\d+\.\d+)|0)$/", $FormatedPrice))
			throw new Exception("A price format is not correct.  The amount is: " . $ThePrice);
			
		return $FormatedPrice;
	}
	
	// Takes a digit or an array or digits
	function _ValidateDigit($Num){

		if(!is_array($Num))
			$Num = array($Num);
	
		foreach($Num as $ThisNum)
			if(!preg_match("/^\d+$/", $ThisNum))
				throw new Exception("Value is not a digit. :" . $ThisNum );
	}
	
	
	// If we don't specify a day... then we want to use the StatementClosingDate in the current month
	// If we specify a day... then it will use that day to make the time stamp.
	// Returns a UnixTimeStamp
	function _TimeStampEnd($Year, $Month, $Day=""){

		// If the day is empty... then use the Statment Closing Day
		// Otherwise add one to the day.  This will make is cover the whole day itself, rather than just the 1st second or morning.
		if(empty($Day))
			$Day = $this->_statementClosingDay;
		else
			$Day += 1;
		
		$this->_ValidateDigit(array($Year, $Month, $Day));

		return mktime(0, 0, 0, $Month, $Day, $Year);
	
	}

	// If we don't specify a day... the StatementClosingDate from the previous month is returned
	// If a day is specified, then this method returns the closest StatementClosingDate (Looking Backwards)
	// Always falls on a StatmentClosingDate
	// Returns a UnixTimeStamp
	function _TimeStampStart($Year, $Month, $Day=""){

		// If the day is less than our closing Day... then we need to roll back the month/Year
		if(empty($Day) || $Day < $this->_statementClosingDay){

			$OneMonthBack = $this->RollBackMonths($Month, $Year, 1);

			$Year = $OneMonthBack["Year"];
			$Month = $OneMonthBack["Month"];	
		}
		
		$this->_ValidateDigit(array($Year, $Month));

		return mktime(0, 0, 0, $Month, $this->_statementClosingDay, $Year);
	
	}
	
	function _GetMysqlTimeStamp($UnixTimeStamp){
		return date("YmdHis", $UnixTimeStamp);
	}
	

	// We are only searching on orders that have a 'C'orporate billing type.
	// Return the OrderID Array by reference
	function &_GetCorporateOrderIDsWithinTimeRange($StartUnixTimeStamp, $EndUnixTimeStamp){

		$StartTime = $this->_GetMysqlTimeStamp($StartUnixTimeStamp);
		$EndTime = $this->_GetMysqlTimeStamp($EndUnixTimeStamp);
		$UserID = $this->_UserControlObj->getUserID();

		$OrdIDarr = array();
		$this->_dbCmd->Query("SELECT ID FROM orders WHERE BillingType='C' AND UserID=$UserID AND DateOrdered BETWEEN $StartTime AND $EndTime ORDER BY ID DESC");
		while($OrdID = $this->_dbCmd->GetValue())
			$OrdIDarr[] = $OrdID;
		
		return $OrdIDarr;
	}
	
	// Balance adjustments are not always created in the same month that the order is placed.
	function &_GetCorporateBalanceAdjustmentIDsWithinTimeRange($StartUnixTimeStamp, $EndUnixTimeStamp){

		$StartTime = $this->_GetMysqlTimeStamp($StartUnixTimeStamp);
		$EndTime = $this->_GetMysqlTimeStamp($EndUnixTimeStamp);
		$UserID = $this->_UserControlObj->getUserID();

		$BalanceIDarr = array();

		$query = "SELECT BA.ID FROM balanceadjustments AS BA INNER JOIN orders ON BA.OrderID = orders.ID
				WHERE orders.UserID = $UserID AND orders.BillingType='C' AND BA.VendorID=0 AND 
				BA.DateCreated BETWEEN $StartTime AND $EndTime ORDER BY BA.DateCreated DESC";

		$this->_dbCmd->Query($query);
		while($bID = $this->_dbCmd->GetValue())
			$BalanceIDarr[] = $bID;
		
		return $BalanceIDarr;
	}

	// Returns the Customer Adjustment for the given AdjustmentID.
	function _GetCustomerAdjustmentTotal($BalanceAdjustmentID){

		$this->_dbCmd->Query("SELECT CustomerAdjustment FROM balanceadjustments WHERE ID=$BalanceAdjustmentID");
		return $this->_FormatPrice($this->_dbCmd->GetValue());

	}	

	// Will return a total representing all of the Order Totals plus any balance adjustments/ refunds
	// It only looks for orders or balance adjustments that belong to the user having "corporate billing".
	function _GetCorporateOrderTotalsPlusBalanceAdjustments($StartTimeStamp, $EndTimeStamp){

		$Total = 0;
		
		// Add up the Order Totals
		$OrdIDarr =& $this->_GetCorporateOrderIDsWithinTimeRange($StartTimeStamp, $EndTimeStamp);
		foreach($OrdIDarr as $thisOrdID){
			// Skip this order if there are no projects inside
			//if(!Order::CheckForActiveProjectWithinOrder($this->_dbCmd, $thisOrdID))
			//	continue;
			$Total += Order::GetGrandTotalOfOrder($this->_dbCmd, $thisOrdID);
		}

		// Add up any Balance adjustments
		$BalanceAdjstArr =& $this->_GetCorporateBalanceAdjustmentIDsWithinTimeRange($StartTimeStamp, $EndTimeStamp);
		foreach($BalanceAdjstArr as $bID)
			$Total += $this->_GetCustomerAdjustmentTotal($bID);
			
		return $this->_FormatPrice($Total);
	}	



	
}






?>
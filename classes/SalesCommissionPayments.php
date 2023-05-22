<?


class SalesCommissionPayments {

	private $_dbCmd;
	private $_dbCmd2;

	private $_salesCommissionsObj;
	private $_paypalMassPayListObj;
	private $_paypalAPIObj;
	
	private $_paypalMassPayEntriesArr = array();
	
	private $_domainID;


	###-- Constructor --###
	// 2 database drivers are needed so we can avoid using many Temp Arrays in PHP
	function SalesCommissionPayments(DbCmd $dbCmd, DbCmd $dbCmd2, $domainID){
		
		if(!Domain::checkIfDomainIDexists($domainID))
			throw new Exception("Error with Domain in SalesCommissionPayments");
			
		$this->_domainID = $domainID;

		$this->_dbCmd = $dbCmd;
		$this->_dbCmd2 = $dbCmd2;
		
		$this->_salesCommissionsObj = new SalesCommissions($this->_dbCmd2, $this->_domainID);
		$this->_paypalMassPayListObj = new PaypalMassPayList();
		$this->_paypalAPIObj = new PaypalAPI($domainID);

		// This is a multi-dim array used to track all of the Sales Commission ID's and amounts
		$this->_paypalMassPayEntriesArr = array();
	}
	
	
	#-- BEGIN Public Methods --#
	
	// Returns an array of Basic Payment details for the given user within the time period.
	// Pass in at month and year as integers.... or the month can also be the string "ALL" to get payments for the whole year
	// If the UserID is 0 (also known as the SalesMaster) then return an empty array
	function GetPaymentsWithinPeriodForUser($UserID, $month, $year){
	
		if($UserID == 0)
			return array();
	
		if(!$this->_salesCommissionsObj->CheckIfSalesRep($UserID))
			$this->_ThrowErrorWithAlert("The given user is not a Sales Rep: $UserID");
			
		if($month == "ALL"){
			$StartSQL = date("YmdHis", mktime(1, 1, 1, 1, 1, $year));
			$EndSQL = date("YmdHis", mktime(1, 1, 1, 13, 1, $year));
		}
		else{
			$StartSQL = date("YmdHis", mktime(1, 1, 1, $month, 1, $year));
			$EndSQL = date("YmdHis", mktime(1, 1, 1, ($month+1), 1, $year));
		}
		

		$SalesPaymentsIDarr = array();
		$this->_dbCmd->Query("SELECT salespayments.ID FROM salespayments INNER JOIN users ON salespayments.SalesUserID = users.ID 
							 WHERE salespayments.SalesUserID=$UserID AND users.DomainID = $this->_domainID AND
							 salespayments.DateCreated BETWEEN $StartSQL AND $EndSQL ORDER BY salespayments.DateCreated ASC");
		while($PaymentID = $this->_dbCmd->GetValue())
			$SalesPaymentsIDarr[] = $PaymentID;
			
		$retArr = array();
		
		foreach($SalesPaymentsIDarr as $PaymentID)
			$retArr[] = $this->GetPaymentDetails($PaymentID);
			
		return $retArr;
	}
	
	function GetPaymentTotalsWithinPeriodForUser($UserID, $month, $year){
		if($UserID == 0)
			return 0;
			
		if(!$this->_salesCommissionsObj->CheckIfSalesRep($UserID))
			$this->_ThrowErrorWithAlert("The given user is not a Sales Rep: $UserID");
	
		if($month == "ALL"){
			$StartSQL = date("YmdHis", mktime(1, 1, 1, 1, 1, $year));
			$EndSQL = date("YmdHis", mktime(1, 1, 1, 13, 1, $year));
		}
		else{
			$StartSQL = date("YmdHis", mktime(1, 1, 1, $month, 1, $year));
			$EndSQL = date("YmdHis", mktime(1, 1, 1, ($month+1), 1, $year));
		}

		
		// Any status is OK to total up... as long has it doesn't have  'T'rying Again or 'D'enied paypal status
		$this->_dbCmd->Query("SELECT SUM(Amount) FROM salespayments INNER JOIN users ON salespayments.SalesUserID = users.ID  
					 WHERE salespayments.SalesUserID=$UserID AND users.DomainID = $this->_domainID 
					AND salespayments.DateCreated BETWEEN $StartSQL AND $EndSQL AND
					(PayPalMassPayLink=0 OR (PayPalStatus != 'T' AND PayPalStatus != 'D'))
					");
	
		return number_format($this->_dbCmd->GetValue(), 2, '.', '');

	}
	
	// Returns a total of sales commissions that have not had payments issued for them yet
	// If you pass in 0, then it will return the total amount of pending payments for all users
	function GetTotalPendingPaymentsForUser($UserID){
		
		if($UserID != 0)	
			if(!$this->_salesCommissionsObj->CheckIfSalesRep($UserID))
				$this->_ThrowErrorWithAlert("The given user is not a Sales Rep: $UserID");

		// Total up any Good or Suspended payements, we don't want to include Expired payments	
		if($UserID != 0){
			$this->_dbCmd->Query("SELECT SUM(CommissionEarned) FROM salescommissions 
					 			INNER JOIN users ON salescommissions.UserID = users.ID  
					 			WHERE salescommissions.UserID=$UserID AND users.DomainID=$this->_domainID AND
					 			PaymentLink=0 AND (CommissionStatus='S' OR CommissionStatus='G')");
		}
		else{
			$this->_dbCmd->Query("SELECT SUM(CommissionEarned) FROM salescommissions 
								INNER JOIN users ON salescommissions.UserID = users.ID  
								WHERE PaymentLink=0 AND users.DomainID=$this->_domainID AND 
								(CommissionStatus='S' OR CommissionStatus='G')");
		}
		return number_format($this->_dbCmd->GetValue(), 2, '.', '');

	}
	
	// Pass in a SalesPaymentID... it will return a Hash containing the details for the payment.
	function GetPaymentDetails($SalesPaymentID){

		$this->_dbCmd->Query("SELECT salespayments.ID, SalesUserID, Amount, PayPalMassPayLink, PayPalStatus, PayPalTransactionID, EmployeePaymentStatus, EmployeePaymentRecordedBy, 
					UNIX_TIMESTAMP(PayPalStatusDateChanged) as PayPalStatusDateUnix,
					UNIX_TIMESTAMP(EmployeePaymentRecordedOn) as EmployeePaymentRecordedUnix,
					UNIX_TIMESTAMP(salespayments.DateCreated) as DateCreatedUnix
					FROM salespayments INNER JOIN users ON users.ID = salespayments.SalesUserID 
					WHERE salespayments.ID=$SalesPaymentID AND users.DomainID = $this->_domainID");

		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("The given SalesPayment ID does not exist: $SalesPaymentID");
		
		$row = $this->_dbCmd->GetRow();
		
		
		
		// We need to come up with a 1 line description for the payment status.
		// Will take into account whether we are an Employee or get Commissions directly from Paypal
		$StatusDesc = "Unknown";
		
		if($row["PayPalMassPayLink"] == 0){
			if($row["EmployeePaymentStatus"] == "N")
				$StatusDesc = "Waiting for entry into Payroll.";
			else if($row["EmployeePaymentStatus"] == "R")
				$StatusDesc = "Recorded into Payroll on " . date("n/j/y", $row["EmployeePaymentRecordedUnix"] . ".");
		}
		else{
			// Find out what the status of the MassPay transaction is.
			// If we have contacted Paypal successfully but never received an IPN for some reason... then the Payment status could get stuck on New
			// If this happens... then we have no way to get the transaction IDs and receive status updates.
			$this->_dbCmd->Query("SELECT MassPayStatus FROM paypalmasspay WHERE ID=" . $row["PayPalMassPayLink"]);
			$MassPayStatus = $this->_dbCmd->GetValue();
			if($MassPayStatus == "A" || $MassPayStatus == "M")
				$NewPaymentStatus = "Payment was submitted through Paypal.";
			else
				$NewPaymentStatus = "New Payment.  Will notify Paypal soon.";
		
		
			if($row["PayPalStatus"] == "N")
				$StatusDesc = $NewPaymentStatus;
			else if($row["PayPalStatus"] == "C")
				$StatusDesc = "Payment has been issued through Paypal.";
			else if($row["PayPalStatus"] == "U")
				$StatusDesc = "We are waiting for you to accept payment through Paypal.  Please disregard this message if your funds have already been claimed.";
			else if($row["PayPalStatus"] == "R")
				$StatusDesc = "You did not claim your funds through Paypal within 30 days so your payment was returned.  Please contact Customer Service when you are ready for us to resend.";
			else if($row["PayPalStatus"] == "D")
				$StatusDesc = "It appears that you have denied your payment.";
			else if($row["PayPalStatus"] == "T")
				$StatusDesc = "This payment was returned.  We will resend it.";
		}
		
		$row["StatusDesc"] = $StatusDesc;
		
		return $row;
	}
	

	// This will create entries for MassPayments... for those users who get paid through Paypal
	// It will not start communication with the PayPal API... it will simply mark payments as ready to go
	// It will create multiple Mass Pay entries if the number of Recipients exceeds the Maxiumum of 250
	// It will also create multiple transactions if any recipient exceeds $10,000 for a single commission payment
	// Will gather all payments in the system that are unpaid... regardless of date created
	function CreateMassPayEntries(){
	
		// Get a unique list of users ID's who have payments ready for sending to Paypal
		$SalesUserIDarr = array();
		$this->_dbCmd->Query("SELECT DISTINCT(SC.UserID) FROM 
					(salescommissions AS SC INNER JOIN salesreps AS SR on SC.UserID = SR.UserID)
					INNER JOIN users ON users.ID =  SC.UserID
					WHERE SR.IsAnEmployee='N' AND SC.CommissionStatus='G' 
					AND SC.PaymentLink=0 AND users.DomainID = $this->_domainID");
		while($thisSalesUserID = $this->_dbCmd->GetValue())
			$SalesUserIDarr[] = $thisSalesUserID;
		
		// In case there are no payments ready for MassPay
		if(sizeof($SalesUserIDarr) == 0)
			return;
			
		// Use this Object for tracking our MassPay Total and Fees
		$this->_paypalMassPayListObj->ClearStack();
		
		// We need to make 1 MassPay entry for every user
		// Unless the user has aquired more than 10K worth of commissions in 1 period... in which case they may have multiple MassPay entries
		foreach($SalesUserIDarr as $thisSalesUserID){
		
			$this->_dbCmd->Query("SELECT salescommissions.ID, CommissionEarned FROM salescommissions INNER JOIN users ON users.ID = salescommissions.UserID 
						WHERE salescommissions.UserID=$thisSalesUserID AND users.DomainID = $this->_domainID
						AND CommissionStatus='G' AND PaymentLink=0");
			
			$SalesCommissionIDarr = array();
			$SalesCommTotal = 0;
			while($row = $this->_dbCmd->GetRow()){
				
				if(($SalesCommTotal + $row["CommissionEarned"]) >= 10000.00){
				
					// Send what we have so far... because the last Commission total will push us over 10K
					$this->_AddMassPayEntry($thisSalesUserID, $SalesCommTotal, $SalesCommissionIDarr);
					
					// Wipe out the array and keep going for this user
					$SalesCommissionIDarr = array();
				}
		
				$SalesCommissionIDarr[] = $row["ID"];
				$SalesCommTotal += $row["CommissionEarned"];
			}
			
			if($SalesCommTotal != 0)
				$this->_AddMassPayEntry($thisSalesUserID, $SalesCommTotal, $SalesCommissionIDarr);
		}
		
		$this->_CloseMassPayEntries();

	}
	
	
	// Similar to CreateMasspayEntries ... but it creates entries for money owed to Employees
	// We can not pay employees with a W-9... it all has to go through Payroll
	function CreateEmployeeSalesPayments(){
	
		
		// Get a unique list of users ID's who have payments ready for recording into the Payroll
		$SalesUserIDarr = array();
		$this->_dbCmd->Query("SELECT DISTINCT(SC.UserID) FROM 
					(salescommissions AS SC INNER JOIN salesreps AS SR on SC.UserID = SR.UserID)
					INNER JOIN users ON users.ID = SC.UserID
					WHERE SR.IsAnEmployee='Y' AND SC.CommissionStatus='G' AND SC.PaymentLink=0 
					AND users.DomainID = $this->_domainID");
		while($thisSalesUserID = $this->_dbCmd->GetValue())
			$SalesUserIDarr[] = $thisSalesUserID;
		
		// Go through Each Employee and Collect all of the payments for them
		foreach($SalesUserIDarr as $thisSalesUserID){
		
			// Get an Array of all SalesCommissions ID's for this user... needing payment
			$SalesCommIDsArr = array();
			$this->_dbCmd->Query("SELECT salescommissions.ID FROM salescommissions INNER JOIN users ON users.ID = salescommissions.UserID 
								WHERE salescommissions.UserID=$thisSalesUserID AND PaymentLink=0 AND CommissionStatus='G' AND users.DomainID=$this->_domainID");
			while($thisSalesComID = $this->_dbCmd->GetValue())
				$SalesCommIDsArr[] = $thisSalesComID;
			
			// We are not locking the tables here... another way is to just use the array we gathered to keep the following operations atomic
			// Gather the SUM of the commission payments 
			$this->_dbCmd->Query("SELECT SUM(CommissionEarned) FROM salescommissions INNER JOIN users ON users.ID = salescommissions.UserID 
								WHERE (" . $this->_GetOrClauseForSQL($SalesCommIDsArr) . ") AND users.DomainID=$this->_domainID");
			$TotalPaymentForUser = number_format($this->_dbCmd->GetValue(), 2, '.', '');
			
			$SalesPaymentTable["SalesUserID"] = $thisSalesUserID;
			$SalesPaymentTable["Amount"] = $TotalPaymentForUser;
			$SalesPaymentTable["PayPalMassPayLink"] = 0;
			$SalesPaymentTable["EmployeePaymentStatus"] = "N";
			$SalesPaymentTable["EmployeePaymentRecordedBy"] = 0;  // Nobody has recorded the payment into Payroll yet
			$SalesPaymentTable["DateCreated"] = date("YmdHis");
			$SalesPaymentID = $this->_dbCmd->InsertQuery("salespayments", $SalesPaymentTable);
			
			// Now update all of the Sales Commission Entries and point to the Sales Payment that we just created
			$this->_dbCmd->UpdateQuery("salescommissions", array("PaymentLink"=>$SalesPaymentID), $this->_GetOrClauseForSQL($SalesCommIDsArr));
		}	
	}
	


	// Generally you can call the method CreateMasspayEntries() a few days ahead of calling the method SendMassPaymentsToPaypal()
	// After the entries have been created you can immediatelly call this method.
	// It will send an email to the administrator and tell them how much money needs to be in the Paypal system in order to prepare for the Mass Payment
	function WarnAdministratorOfNeededFundsInPaypal(){
	
		$Total = 0;
		$this->_dbCmd->Query("SELECT SUM(PaymentAmount) FROM paypalmasspay WHERE MassPayStatus='N' AND DomainID=$this->_domainID"); 
		$Total += $this->_dbCmd->GetValue();
		$this->_dbCmd->Query("SELECT SUM(FeeAmount) FROM paypalmasspay WHERE MassPayStatus='N' AND DomainID=$this->_domainID"); 
		$Total += $this->_dbCmd->GetValue();
		$Total = number_format($Total, 2);
		
		$message = "Make sure that you have enough funds to cover the Paypal MassPay that is about to go out in the next couple of days.  You need at least \$$Total in the account.";
		
		$domainEmailConfigObj = new DomainEmails($this->_domainID);
		
		WebUtil::SendEmail($domainEmailConfigObj->getEmailNameOfType(DomainEmails::PAYPAL), $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::PAYPAL), $domainEmailConfigObj->getEmailNameOfType(DomainEmails::PAYPAL), $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::PAYPAL), "Paypal Funds Required: \$$Total", $message);
	
	}
	
	// If a Paypal user does not register for an account and claim their money within 30 days... 
	// then the payment will be marked as Returned. 
	// Or they may refuse to accept payments, in which case the payment may come back as Denied.
	// Calling this method will mark any Returned Commission Payments as Unpaid so that they will go out on the next MassPay session
	// It will also keep a record of the Payment that was returned... and let them know we are tyring it again.
	function RetryReturnedOrDeniedPaypalPayments($UserID){
	
		if(!$this->_salesCommissionsObj->CheckIfSalesRep($UserID))
			$this->_ThrowErrorWithAlert("The given user is not a Sales Rep: $UserID");
			
		$this->_dbCmd->Query("SELECT salespayments.ID FROM salespayments INNER JOIN users ON users.ID = salespayments.SalesUserID 
								WHERE SalesUserID=$UserID AND ( PayPalStatus='R' OR PayPalStatus='D') AND users.DomainID=$this->_domainID");
		while($PaymentID = $this->_dbCmd->GetValue()){
		
			// Mark the current row as 'T'rying again to let us know that another attempt will be made
			$this->_dbCmd2->UpdateQuery("salespayments", array("PayPalStatus"=>'T'), "ID=$PaymentID");
			
			// Set the payment Link back to 0 for any sales commissions that were linked to this "Returned" payment
			// Having the payment Link as 0 will mean that it is automatically picked up on the next round of MassPayments
			$this->_dbCmd2->UpdateQuery("salescommissions", array("PaymentLink"=>0), "PaymentLink=$PaymentID");
		}
	
	}
	
	// Let's say our system does a MassPay and it comes back with an error... because of a timeout... Our system will flag an "unknown error"
	// At that point we must log into Paypal and find out if the tranaction actually went through or not
	// If we find out that it did... then we can call this method to let the system know that the transaction went through OK
	function ManuallyCompleteMassPayError($MassPayID, $UserID){

		$this->_EnsurePayPalMassPayIDisInError($MassPayID);
			
		$UpdateArr["MassPayStatus"] = "M";  // Manually Approved or Completed
		$UpdateArr["ManuallyAcceptedUserID"] = $UserID;
		$UpdateArr["LastStatusChange"] = date("YmdHis"); // mysql timestamp
		
		$this->_dbCmd->UpdateQuery("paypalmasspay", $UpdateArr, "ID=$MassPayID");
   
	}
	
	// Similar to the method ManuallyCompleteMassPayError 
	// but if we find out that the transaction did not go through (because of an error)...
	// then calling this method will try to contact paypal again to issue the Mass Payment
	// Be careful though... before you do this the person should make sure the payment did not go through to Paypal or we may pay people double commissions
	function RetryMassPayError($MassPayID){

		$this->_EnsurePayPalMassPayIDisInError($MassPayID);
		
		$this->SendMassPayment($MassPayID);

	}
	
	// Returns an Array of payment ID's... all which have entries in the payments table... but have not been recorded into payroll yet.

	function GetPaymentIDsForWaitingEmployeePayrollEntry(){
		
		$retArray = array();

		$this->_dbCmd->Query("SELECT salespayments.ID FROM salespayments INNER JOIN users ON users.ID = salespayments.SalesUserID 
								WHERE EmployeePaymentStatus='N' AND users.DomainID=$this->_domainID ORDER BY SalesUserID");
		while($x = $this->_dbCmd->GetValue())
			$retArray[] = $x;
		return $retArray;
	}
	
	// Will mark that Sales Commissions for Employees have been entered into the Payroll system
	// Pass in a salespayments ID.   Will fail critically if the salespayment ID belongs to a Paypal user instead of an employee
	function ConfirmPaymentForEmployeeIntoPayroll($SalesPaymentID, $UserIDwhoIsRecording){
	
		// Make sure that the SalesPayment ID belongs to an Employee
		$this->_dbCmd->Query("SELECT PayPalMassPayLink FROM salespayments INNER JOIN users ON users.ID = salespayments.SalesUserID 
								 WHERE salespayments.ID=$SalesPaymentID AND users.DomainID=$this->_domainID");
		if($this->_dbCmd->GetNumRows() == 0)
			$this->_ThrowErrorWithAlert("Calling the method ConfirmPaymentForEmployeeIntoPayroll and the SalesPaymentID does not exist: $SalesPaymentID");
		if($this->_dbCmd->GetValue() != 0)
			$this->_ThrowErrorWithAlert("Calling the method ConfirmPaymentForEmployeeIntoPayroll and the PayPalMassPayLink must be 0.");
		
		$UpdateArr["EmployeePaymentStatus"] = "R";  // Mark as recorded
		$UpdateArr["EmployeePaymentRecordedBy"] = $UserIDwhoIsRecording;
		$UpdateArr["EmployeePaymentRecordedOn"] = date("YmdHis"); // mysql timestamp
		$this->_dbCmd->UpdateQuery("salespayments", $UpdateArr, "ID=$SalesPaymentID");	
	}
	
	
	// May change the status to 1 of the 4 choices... 'C'laimed, 'U'nclaimed, 'R'eturned, 'D'enied
	// There are certain rules though... such as... you can not change the status to Returned if the status is already Claimed
	// If the OldStatus (currently recorded in the DB) matches the New Status... then the method will return harmlessly without updating the timestamp.
	function ChangePaypalStatusOfPayment($SalesPaymentID, $NewStatusChar){
	
		// Make sure that the SalesPayment ID belongs to a Paypal Payment
		$this->_dbCmd->Query("SELECT PayPalMassPayLink FROM salespayments INNER JOIN users ON users.ID = salespayments.SalesUserID 
								 WHERE salespayments.ID=$SalesPaymentID AND users.DomainID=$this->_domainID");
		if($this->_dbCmd->GetNumRows() == 0)
			$this->_ThrowErrorWithAlert("Calling the method ChangePaypalStatusOfPayment and the SalesPaymentID does not exist: $SalesPaymentID");
		$row = $this->_dbCmd->GetRow();
		if($row["PayPalMassPayLink"] == 0)
			$this->_ThrowErrorWithAlert("Calling the method ChangePaypalStatusOfPayment and the PayPalMassPayLink can not be 0.");
		
		$OldMassPayStatus = $row["PayPalStatus"];
		
		// If the states match the return without changing anything
		if($OldMassPayStatus == $NewStatusChar)
			return;

		if($NewStatusChar == "C"){
			if($OldMassPayStatus != "U" && $OldMassPayStatus != "N")
				$this->_ThrowErrorWithAlert("Cannot change to a Claimed status within MassPay unless the status is currently Unclaimed or New. OldStatus: $OldMassPayStatus");
		}
		else if($NewStatusChar == "U"){
			if($OldMassPayStatus != "N")
				$this->_ThrowErrorWithAlert("Cannot change to an Unclaimed status within MassPay unless the status is currently New. OldStatus: $OldMassPayStatus");
		}
		else if($NewStatusChar == "R"){
			if($OldMassPayStatus != "U" && $OldMassPayStatus != "N")
				$this->_ThrowErrorWithAlert("Cannot change to an Returned status within MassPay unless the status is currently Unclaimed or New. OldStatus: $OldMassPayStatus");
		}
		else if($NewStatusChar == "D"){
			if($OldMassPayStatus != "U" && $OldMassPayStatus != "N")
				$this->_ThrowErrorWithAlert("Cannot change to an Declined status within MassPay unless the status is currently Unclaimed or New. OldStatus: $OldMassPayStatus");
		}
		else
			$this->_ThrowErrorWithAlert("Illegail NewStatusChar with method call to ChangePaypalStatusOfPayment");
		
		
		$UpdateArr["PayPalStatus"] = $NewStatusChar; 
		$UpdateArr["PayPalStatusDateChanged"] = date("YmdHis"); 
		$this->_dbCmd->UpdateQuery("salespayments", $UpdateArr, "ID=$SalesPaymentID");	
		
	}
	
	// We don't know what the transaction ID is until we get the IPN back from PayPal after issuing the MassPayment
	// If we get a Denied or Failed response then the TransactionID will be blank.
	function SetPayPalTransactionID($SalesPaymentID, $PayPalTransactionID){
	
		$this->_dbCmd->Query("SELECT PayPalMassPayLink FROM salespayments INNER JOIN users ON users.ID = salespayments.SalesUserID 
								 WHERE salespayments.ID=$SalesPaymentID AND users.DomainID=$this->_domainID");
		if($this->_dbCmd->GetValue() == 0)
			$this->_ThrowErrorWithAlert("Trying to call the method SetPayPalTransactionID on a SalesPaymentID that belongs to an employee");

		$UpdateArr["PayPalTransactionID"] = $PayPalTransactionID; 
		$this->_dbCmd->UpdateQuery("salespayments", $UpdateArr, "ID=$SalesPaymentID");
	}
	function GetPayPalTransactionID($SalesPaymentID){
	
		$this->_dbCmd->Query("SELECT PayPalTransactionID FROM salespayments INNER JOIN users ON users.ID = salespayments.SalesUserID 
								 WHERE salespayments.ID=$SalesPaymentID AND PayPalMassPayLink != 0 AND users.DomainID=$this->_domainID");
		if($this->_dbCmd->GetNumRows() == 0)
			$this->_ThrowErrorWithAlert("Trying to call the method GetPayPalTransactionID on a SalesPaymentID that doesnt exist or belongs to an employee");

		$TransID = $this->_dbCmd->GetValue();
		if(!$TransID)
			return "";
		return $TransID;
	}
	
	
	// Will look for any 'N'ew MassPayments within the paypalmasspay table and attempt to send them off to Paypal
	// If there are no payments then it is benign to call this method
	function SendMassPaymentsToPaypal(){
	
		$MassPayIDarr = array();
		$this->_dbCmd->Query("SELECT ID FROM paypalmasspay WHERE MassPayStatus='N' AND DomainID=$this->_domainID");
		while($thisID = $this->_dbCmd->GetValue())
			$MassPayIDarr[] = $thisID;
			
		foreach($MassPayIDarr as $thisID)
			$this->SendMassPayment($thisID);

	}

	// Sends a Single MassPay to Paypal based off of the ID in our DB
	// You may only send a Mass Payment if the status is 'N'ew or 'E'rror.  It may be in 'E'rror if we are retyring a charge
	function SendMassPayment($MassPayID){
	
		$this->_dbCmd->Query("SELECT COUNT(*) FROM paypalmasspay WHERE ID=$MassPayID AND (MassPayStatus='N' OR MassPayStatus='E') 
								AND DomainID=$this->_domainID");
		if($this->_dbCmd->GetValue() == 0)
			$this->_ThrowErrorWithAlert("You can not send this Mass Payment because the status is not correct: $MassPayID");
			
		// Fill up a Collection Object for holding all payments that will be sent to Paypal
		$this->_paypalMassPayListObj->ClearStack();
		
		$this->_dbCmd->Query("SELECT * FROM salespayments WHERE PayPalMassPayLink=$MassPayID");
		while($row = $this->_dbCmd->GetRow()){
			$SalesEmail = UserControl::GetEmailByUserID($this->_dbCmd2, $row["SalesUserID"]);
			
			$this->_paypalMassPayListObj->AddPayment($SalesEmail, $row["Amount"], $row["ID"]);
		}
		
		// First update the database and say that there was an error.
		// If the mass payment goes through or the server doesnt crash... etc. then it will get updated with the new sucess or error message
		// Start out with an error... let it prove otherwise.
		$UpdateArr["MassPayStatus"] = 'E';
		$UpdateArr["ErrorMessage"] = "Starting out with an Error.  Communication with PayPal has not started yet.";
		$UpdateArr["LastStatusChange"] = date("YmdHis"); // mysql timestamp
		$this->_dbCmd->UpdateQuery("paypalmasspay", $UpdateArr, "ID=$MassPayID");

	
		// Try to send the payment through paypal.  It will return true or false whether it goes through or not.
		if($this->_paypalAPIObj->MassPay($this->_paypalMassPayListObj)){
			$UpdateArr["MassPayStatus"] = 'A';
			$UpdateArr["ErrorMessage"] = "";
			$UpdateArr["LastStatusChange"] = date("YmdHis"); // mysql timestamp
			$UpdateArr["PaypalTimestamp"] = date("YmdHis", $this->_paypalAPIObj->GetTransactionTimeStamp()); 
			$this->_dbCmd->UpdateQuery("paypalmasspay", $UpdateArr, "ID=$MassPayID");
		}
		else{
			$UpdateArr["MassPayStatus"] = 'E';
			$UpdateArr["ErrorMessage"] = $this->_paypalAPIObj->GetErrorMessage();
			$UpdateArr["LastStatusChange"] = date("YmdHis"); // mysql timestamp
			$UpdateArr["PaypalTimestamp"] = date("YmdHis", $this->_paypalAPIObj->GetTransactionTimeStamp()); 
			$this->_dbCmd->UpdateQuery("paypalmasspay", $UpdateArr, "ID=$MassPayID");
			
			WebUtil::WebmasterError("An error occured while sending out a PayPal Mass Payment.");
		}
	}
	
	
	##--- Private Methods Below ---##


	// Pass in an Array of Sales Commission ID's for a particular user
	// Make sure that the total of the Sales Commission ID's does not exceed $10K before insterting into this method... 
	// that is the limit of what Paypal will accept per entry
	// This method will ensure that the total amount of entries for a single MassPay does not exceed 250... which is a limit set by Paypal
	function _AddMassPayEntry($SalesUserID, $Amount, array $SalesCommIDarr){
	
		if($Amount > 10000.00)
			throw new Exception("You can not add an amount greater than $10K within the method _AddMassPayEntry");
			
		if($Amount == 0)
			throw new Exception("You can not add an amount equal to $0.00 within the method _AddMassPayEntry");
	
		$domainIDofSalesRep = UserControl::getDomainIDofUser($SalesUserID);
		if($domainIDofSalesRep != $this->_domainID)
			throw new Exception("Domain Conflict in method _AddMassPayEntry");
			
		$this->_paypalMassPayEntriesArr[] = array("SalesUserID"=>$SalesUserID, "Amount"=>$Amount, "SalesCommissionIDArr"=>$SalesCommIDarr);
	
		// The email address and SalesID is not important to us in this context.  
		// We are just using this object for tracking our Total Payment and Paypal Fees.
		$this->_paypalMassPayListObj->AddPayment("dummy@email.com", $Amount, 0);  
	
		if(sizeof($this->_paypalMassPayEntriesArr) == 250)
			$this->_CloseMassPayEntries();
	
	}
	
	// Let this object know that no more Mass Payments will be added.
	// So write the MassPay to the Database and clear out our internal array
	function _CloseMassPayEntries(){
	
		if(sizeof($this->_paypalMassPayEntriesArr) == 0)
			return;
		
		
		// Create a new entry in the MassPay table
		$MassPayInsertArr["MassPayStatus"] = "N";
		$MassPayInsertArr["LastStatusChange"] = date("YmdHis");
		$MassPayInsertArr["ManuallyAcceptedUserID"] = 0;
		$MassPayInsertArr["PaymentAmount"] = $this->_paypalMassPayListObj->GetTotalAmount();
		$MassPayInsertArr["FeeAmount"] = $this->_paypalMassPayListObj->GetTransactionFees();
		$MassPayInsertArr["ErrorMessage"] = "";
		$MassPayInsertArr["DomainID"] = $this->_domainID;
		$PayPalMassPayID = $this->_dbCmd->InsertQuery("paypalmasspay", $MassPayInsertArr);
	
		// Add all of the entries for our individual Sales Payments
		foreach($this->_paypalMassPayEntriesArr as $thisMassPayEntry){
		
			$PaymentInsertArr["SalesUserID"] = $thisMassPayEntry["SalesUserID"];
			$PaymentInsertArr["Amount"] = $thisMassPayEntry["Amount"];
			$PaymentInsertArr["PayPalMassPayLink"] = $PayPalMassPayID;
			$PaymentInsertArr["PayPalStatus"] = "N";
			$PaymentInsertArr["PayPalStatusDateChanged"] = date("YmdHis");
			$PaymentInsertArr["DateCreated"] = date("YmdHis");
			$SalesPaymentID = $this->_dbCmd->InsertQuery("salespayments", $PaymentInsertArr);
	
			// For each sales commission entry.... we need to add a link to our new Sales Payment ID
			foreach($thisMassPayEntry["SalesCommissionIDArr"] as $thisCommissionID){
				$this->_dbCmd->UpdateQuery("salescommissions", array("PaymentLink"=>$SalesPaymentID), "ID=$thisCommissionID");
			}
		
		}

		
		// Wipe the slate clean... in case there is more than 1 MassPayment coming through
		$this->_paypalMassPayEntriesArr = array();
		$this->_paypalMassPayListObj->ClearStack();
	}

	
	// Before we retry, cancel, etc a MassPayment ID... we want to make sure that the status is currently in Error
	function _EnsurePayPalMassPayIDisInError($MassPayID){

		$this->_dbCmd->Query("SELECT COUNT(*) FROM paypalmasspay WHERE ID=$MassPayID AND MassPayStatus='E' AND DomainID=$this->_domainID");
		if($this->_dbCmd->GetValue() == 0)
			$this->_ThrowErrorWithAlert("The Masspayment ID does not exist or is not in Error: $MassPayID");
	
	}
	
	function _ThrowErrorWithAlert($errMsg){
		WebUtil::WebmasterError($errMsg);
		exit($errMsg);
	}

	// Pass in an array of ID numbers and it will return an OR clause...  Example:  ID=12 OR ID=23 OR ID=25
	function _GetOrClauseForSQL($idArr){
	
		$OrClause = "";
		
		foreach($idArr as $thisID)
			$OrClause .= "salescommissions.ID=$thisID OR ";
	
		if(!empty($OrClause))
			$OrClause = substr($OrClause, 0, -3);
			
		// Set it to something ridiculouse to that the query gets no results... if they array is empty.
		if(empty($OrClause))
			$OrClause = "ID=9999999999";
			
		return $OrClause;
	}
}

	
?>
<?php


// You May NOT put more than 250 payments in a single batch or the script will fail critically.
class PaypalMassPayList {

	private $_returnArr = array();
	
	// Constructor
	function PaypalMassPayList(){
		$this->ClearStack();
	}
	function ClearStack(){
		$this->_returnArr = array();
	}
	
	// SalesPaymentID refers to the ID of the salespayments table
	function AddPayment($ReceiverEmail, $Amount, $SalesPaymentID){	
	
		if(!WebUtil::ValidateEmail($ReceiverEmail))
			throw new Exception("The email is invalid: " . $ReceiverEmail);
		
		if(!preg_match("/^((\d+\.\d{1,2})|\d+)$/", $Amount))
			throw new Exception("The Amount for the mass pay is not in proper format: $Amount");
		
		$Amount = number_format($Amount, 2, '.', '');  // Always has 2 decimals with no commas for the thousands place
		
		if($Amount == 0)
			throw new Exception("You can not add a MassPay item with an amount of Zero");
			
		if($Amount > 10000)
			throw new Exception("You can not add a MassPay item with an amount greater than Ten Thousand Dollars.");
	
		$this->_returnArr[] = array("ReceiverEmail"=>$ReceiverEmail, "Amount"=>$Amount, "SalesPaymentID"=>$SalesPaymentID);
	}
	
	function GetMassPayArray(){
		
		if(sizeof($this->_returnArr) > 250)
			throw new Exception("Size limit has exceeded 250 payments within a single batch.");
		
		return $this->_returnArr;
	}


	// The rule is 2% with a cap of $1.00 for each payment of the MassPay batch.
	// Will total up all of the payments for the entire Mass Pay Batch
	function GetTransactionFees(){
	
		$total = 0;
		foreach($this->_returnArr as $thisArr){
		
			$percentAmount = 0.02 * $thisArr["Amount"];
			if($percentAmount > 1.0)
				$total += 1.0;
			else
				$total += $percentAmount;
		}
			
		return number_format(round($total, 2), 2, '.', '');
	}
	
	
	// Returns the total amount of commissions that we will be sending out
	function GetTotalAmount(){
	
		$total = 0;
		foreach($this->_returnArr as $thisArr)
			$total += $thisArr["Amount"];
			
		return number_format(round($total, 2), 2, '.', '');
	}
	
}


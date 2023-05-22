<?php

class BalanceAdjustment {
	
	private $dbCmd;
	private $orderID;
	private $domainID;
	private $customerAdjustmentType;
	
	private $paymentObj;
	private $paymentGatewayErrorFlag;
	
	const CUSTOMER_ADJST_AUTH_ONLY = "A";
	const CUSTOMER_ADJST_AUTH_CAPTURE = "C";
	const CUSTOMER_ADJST_REFUND_AUTH = "F";
	const CUSTOMER_ADJST_REFUND_CAPTURE = "R";
	
	
	private $customerAdjustmentTypesArr = array();
	
	function __construct($orderID){
		
		$orderID = intval($orderID);
		
		$this->dbCmd = new DbCmd();
		
		if(!Order::checkIfOrderIDexists($orderID))
			throw new Exception("The order ID does not exist.");
		
		$this->orderID = $orderID;
		$this->domainID = Order::getDomainIDFromOrder($this->orderID);
		
		$this->customerAdjustmentTypesArr = array(self::CUSTOMER_ADJST_AUTH_ONLY, self::CUSTOMER_ADJST_AUTH_CAPTURE, self::CUSTOMER_ADJST_REFUND_AUTH ,self::CUSTOMER_ADJST_REFUND_CAPTURE);
		$this->paymentGatewayErrorFlag = false; 
		
		$this->paymentObj = new Payments($this->domainID);
		$this->paymentObj->LoadBillingInfoByOrderID($this->orderID);
	}

	
	
	// ----- BEGIN Static Methods ---------------------
	
	// Positive Numbers mean we are taking money from the customer
	// Negative Numbers mean we are refunding
	static function getCustomerAdjustmentsTotalFromOrder(DbCmd $dbCmd, $orderno){
		
		$orderno = intval($orderno);
		
		$dbCmd->Query("SELECT SUM(CustomerAdjustment) FROM balanceadjustments WHERE OrderID=$orderno");
		$totalBalanceAdjustments = $dbCmd->GetValue();
		
		return number_format($totalBalanceAdjustments, 2, '.', '');
	}
	
	
	// This will only include Adjustments which have been added for "Auth Only".
	// Both Positive and Negative (refunds) can not be added with "Auth Only" after an order is completed.  
	// The reason is that when the order completes it goes through the Capture Process... and that is the only time.
	// You may still have Postive and Negative values returned from this method after the order is completed though.
	// Positive Numbers mean we are taking money from the customer
	// Negative Numbers mean we are refunding
	static function getCustomerAdjustmentsTotalFromOrder_AuthOnly(DbCmd $dbCmd, $orderno){
		
		$orderno = intval($orderno);
		
		$dbCmd->Query("SELECT SUM(CustomerAdjustment) FROM balanceadjustments WHERE OrderID=$orderno 
						AND (CustomerAdjustmentType='".self::CUSTOMER_ADJST_AUTH_ONLY."' OR CustomerAdjustmentType='".self::CUSTOMER_ADJST_REFUND_AUTH."')");
		$totalBalanceAdjustments = $dbCmd->GetValue();
		
		return number_format($totalBalanceAdjustments, 2, '.', '');
	}
	
	// This will only include Adjustments which have been Authorized and Captured in the same transaction.... or the Refund was issued through Authorize.net.
	// It is possible to do an Auth/Capture/Refund to Auth.net before an order has been completed (even though Auths still exist waiting to be captured).
	// Positive Numbers mean we are taking money from the customer
	// Negative Numbers mean we are refunding
	static function getCustomerAdjustmentsTotalFromOrder_Transmitted(DbCmd $dbCmd, $orderno){
		
		$orderno = intval($orderno);
		
		$dbCmd->Query("SELECT SUM(CustomerAdjustment) FROM balanceadjustments WHERE OrderID=$orderno 
					AND (CustomerAdjustmentType='".self::CUSTOMER_ADJST_AUTH_CAPTURE."' OR CustomerAdjustmentType='".self::CUSTOMER_ADJST_REFUND_CAPTURE."')");
		$totalBalanceAdjustments = $dbCmd->GetValue();
		
		return number_format($totalBalanceAdjustments, 2, '.', '');
	}
	
	
	// Postive Numbers means the company is losing money and the vendors are getting paid more.
	// Negative Numbers means the company will have more profit.
	static function getVendorAdjustmentsTotalFromOrder(DbCmd $dbCmd, $orderno){
		
		$orderno = intval($orderno);
		
		$dbCmd->Query("SELECT SUM(VendorAdjustment) FROM balanceadjustments WHERE OrderID=$orderno");
		$totalBalanceAdjustments = $dbCmd->GetValue();
		
		return number_format($totalBalanceAdjustments, 2, '.', '');
	}
	// ----- END Static Methods ---------------------
	
	
	
	// Returns an Array of BalanceAdjustment ID's for customers on the given order.
	// Retursn an Empty array() if there are no adjustments.
	function getCustomerAdjustmentIDsFromOrder(){
		$this->dbCmd->Query("SELECT ID FROM balanceadjustments WHERE OrderID = $this->orderID AND VendorID == 0");
		return $this->dbCmd->GetValueArr();
	}
	
	// Returns an Array of BalanceAdjustment ID's for customers on the given order.
	// Retursn an Empty array() if there are no adjustments.
	function getVendorAdjustmentIDsFromOrder(){
		$this->dbCmd->Query("SELECT ID FROM balanceadjustments WHERE OrderID = $this->orderID AND VendorID > 0");
		return $this->dbCmd->GetValueArr();
	}
	
	// This works for both customer and vendor adjustments.
	// A negative Amount for a customer menas we are giving the customer a refund.
	// A negative Amount for a vendor means that the company is making more profit and the vendor is getting less.
	function getAmountFromBalanceAdjustmentID($adjustmentID){
		
		$adjustmentID = intval($adjustmentID);
		
		$this->dbCmd->Query("SELECT CustomerAdjustment, VendorAdjustment, VendorID, CustomerAdjustmentType FROM balanceadjustments WHERE ID=$adjustmentID AND OrderID = $this->orderID");
		if($this->dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method getAmountFromBalanceAdjustmentID. The Adjustment ID does not exist.");
			
		$row = $this->dbCmd->GetRow();
		$customerAdjustAmnt = $row["CustomerAdjustment"];
		$vendorAdjustAmnt = $row["VendorAdjustment"];
		$vendorID = $row["VendorID"];
		$customerAdjustmentType = $row["CustomerAdjustmentType"];
		
		// Figure out if it is a Vendor Adjustment of a Customer Adjustment with some extra error checking.
		if(!empty($vendorID)){
			return $vendorAdjustAmnt;
		}
		else{
			if(!in_array($customerAdjustmentType, $this->customerAdjustmentTypesArr))
				throw new Exception("Error in method getAmountFromBalanceAdjustmentID. The Customer Adjustment Type is incorrect: $customerAdjustmentType");
			return $customerAdjustAmnt;
		}
	}
	
	// Returns a status code with one of the 4 possible Customer Adjustment type constants.
	function getCustomerAdjustmentType($adjustmentID){
		
		$adjustmentID = intval($adjustmentID);
		
		$this->dbCmd->Query("SELECT CustomerAdjustmentType FROM balanceadjustments WHERE ID=$adjustmentID AND OrderID = $this->orderID");
		
		if($this->dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method getCustomerAdjustmentType. The Adjustment ID does not exist.");
		
		$adjustmentType = $this->dbCmd->GetValue();
			
		if(!in_array($adjustmentType, $this->customerAdjustmentTypesArr))
			throw new Exception("Error in method getCustomerAdjustmentType. The Customer Adjustment Type is incorrect: $adjustmentType");
			
		return $adjustmentType;
	}
	
	
	
	// You can charge a customer (With Capture) whether or not an order is complete or not.
	// Returns TRUE or FALSE depending on the payment gateway response.
	// If FALSE, you can subsequently call getPaymentGatewayErrorReason()
	public function chargeCustomerWithCapture($chargeAmount, $userID, $chargeDescription){
		
		$this->paymentGatewayErrorFlag = false;
		
		$chargeAmount = WebUtil::FilterData($chargeAmount, FILTER_SANITIZE_FLOAT);
		$chargeAmount = number_format($chargeAmount, 2, '.', '');
		
		if(Math::checkIfFloatIsZero($chargeAmount))
			throw new Exception("You can not to a balance adjustment with a value of zero.");
		
		if($chargeAmount < 0)
			throw new Exception("Negative Balance Adjustments are not allowed in this method. Try doing a refund instead.");
		
			
		// We are not going to charge any freight or tax for a positive balance adjustment.  I guess technically we should, but we are not doing so on refunds
		$this->paymentObj->SetTransactionAmounts($chargeAmount, 0, 0);
		
		if(!$this->paymentObj->AuthorizeAndCapture()){
			$this->paymentGatewayErrorFlag = true;
			return false;
		}
		else{
			
			// The new charge was approved
			// Create a new record internally.  0 means this transaction is not linked to another charge ID.  For example Refunds are linked to capture ID's and Captures are linked to Auth IDs.
			$this->paymentObj->setOrderIdLink($this->orderID);
			$this->paymentObj->CreateNewTransactionRecord(0);
			
			$insertArr[ "VendorAdjustment"] = null;
			$insertArr[ "CustomerAdjustment"] = $chargeAmount;
			$insertArr[ "CustomerAdjustmentType"] = self::CUSTOMER_ADJST_AUTH_CAPTURE;
			$insertArr[ "OrderID"] = $this->orderID;
			$insertArr[ "VendorID"] = 0;
			$insertArr[ "FromUserID"] = intval($userID);
			$insertArr[ "Description"] = $chargeDescription;
			$insertArr[ "DateCreated"] = time();
			$this->dbCmd->InsertQuery("balanceadjustments",  $insertArr);
					
			return true;
		}
	}
	
	
	
	// Returns TRUE or FALSE depending on the payment gateway response.
	// If FALSE, you can subsequently call getPaymentGatewayErrorReason()
	public function chargeCustomerAuthOnly($chargeAmount, $userID, $chargeDescription){
		
		$this->paymentGatewayErrorFlag = false;
		
		$chargeAmount = WebUtil::FilterData($chargeAmount, FILTER_SANITIZE_FLOAT);
		$chargeAmount = number_format($chargeAmount, 2, '.', '');
		
		
		if(Math::checkIfFloatIsZero($chargeAmount))
			throw new Exception("You can not to a balance adjustment with a value of zero.");
		
		if($chargeAmount < 0)
			throw new Exception("Negative Balance Adjustments are not allowed in this method. Try doing a refund instead.");
		
		if(Order::CheckIfOrderComplete($this->dbCmd, $this->orderID))
			throw new Exception("You not charge a customer with Auth-Only if the order has already been completed.");
		
		// Get the new totals of the order after the change to the options
		$orderGrandTotal = Order::GetGrandTotalOfOrder($this->dbCmd, $this->orderID);
		$orderTaxTotal = Order::GetTotalFromOrder($this->dbCmd, $this->orderID, "customertax");
		$orderFreightTotal = Order::GetCustomerShippingQuote($this->dbCmd, $this->orderID);
		
		$previousSoftBalanceAdjustments = self::getCustomerAdjustmentsTotalFromOrder_AuthOnly($this->dbCmd, $this->orderID);
		
		$toalAmountOfAuthsNeededOnOrder = $orderGrandTotal + $previousSoftBalanceAdjustments + $chargeAmount;
		
		if(!$this->paymentObj->AuthorizeNewAmountForOrder($this->orderID, $toalAmountOfAuthsNeededOnOrder, $orderTaxTotal, $orderFreightTotal)){
			$this->paymentGatewayErrorFlag = true;
			return false;
		}
		else{
			
			$insertArr[ "VendorAdjustment"] = null;
			$insertArr[ "CustomerAdjustment"] = $chargeAmount;
			$insertArr[ "CustomerAdjustmentType"] = self::CUSTOMER_ADJST_AUTH_ONLY;
			$insertArr[ "OrderID"] = $this->orderID;
			$insertArr[ "VendorID"] = 0;
			$insertArr[ "FromUserID"] = intval($userID);
			$insertArr[ "Description"] = $chargeDescription;
			$insertArr[ "DateCreated"] = time();
			$this->dbCmd->InsertQuery("balanceadjustments",  $insertArr);
			
			return true;
		}
	}
	
	public function getPaymentGatewayErrorReason(){
		
		if(!$this->paymentGatewayErrorFlag)
			throw new Exception("You can not call this method unless the payment gateway has returned False.");
			
		return $this->paymentObj->GetErrorReason();
	}
	
	
	// Does not return a Success or Failure.  If there is a problem then it will show up in the Credit Card Errors list.
	public function refundMoneyToCustomer($amount, $userID, $refundDescription){
		
		$amount = WebUtil::FilterData($amount, FILTER_SANITIZE_FLOAT);
		$amount = number_format($amount, 2, ".", "");
		
		if(Math::checkIfFloatIsZero($amount))
			throw new Exception("You can not do a refund of Zero.");
		
		if($amount < 0)
			throw new Exception("Even thow you are refunding money, you must still pass in a positive amount to this method.");
		
		// Regardless if the Order has been completed or not...
		// Throw an exception if the amount being refunded is greater than the Grand Total of the order plus any balance adjustments (which may include previous refunds).
		$totalChargesFromOrder = Order::GetGrandTotalOfOrder($this->dbCmd, $this->orderID);
		$totalChargesFromOrder += self::getCustomerAdjustmentsTotalFromOrder($this->dbCmd, $this->orderID);
		
		// Round to prevent float comparison errors.
		if(Math::checkIfFirstFloatParamIsGreater($amount, $totalChargesFromOrder))
			throw new Exception("Can not refund a balance adjustment because the amount is greater than the order total (plus balance adjustments on Order $this->orderID Amount: $amount Total Charges: $totalChargesFromOrder");
		
		if(Order::CheckIfOrderComplete($this->dbCmd, $this->orderID)){
			
			$this->paymentObj->RefundMoneyToOrder($amount, $this->orderID);
			
			$insertArr[ "VendorAdjustment"] = null;
			$insertArr[ "CustomerAdjustment"] = (-1 * $amount);
			$insertArr[ "CustomerAdjustmentType"] = self::CUSTOMER_ADJST_REFUND_CAPTURE;
			$insertArr[ "OrderID"] = $this->orderID;
			$insertArr[ "VendorID"] = 0;
			$insertArr[ "FromUserID"] = intval($userID);
			$insertArr[ "Description"] = $refundDescription;
			$insertArr[ "DateCreated"] = time();
			$this->dbCmd->InsertQuery("balanceadjustments",  $insertArr);
		}
		else{
	
			// Prefer to do all "hard refunds" first (if any Hard Balance Adjustments were done before the order was complete).
			// Otherwise we have offset all "Auth Balance Adjustments" before they get captured.
			$amountOfHardCaptures = self::getCustomerAdjustmentsTotalFromOrder_Transmitted($this->dbCmd, $this->orderID);
			
			// Because the order has not been completed yet... this value should never be negative.
			// In order to have more balance adjustments going back to the customer than we did balance adjustements taking out... the order has to be captured first.
			if(Math::checkIfFirstFloatParamIsLess($amountOfHardCaptures, 0))
				throw new Exception("Error trying to refund money to a customer. There is currently a negative balance adjustment before the order has been completed.  This should never happen. OrderID: $this->orderID Amount: $amount Hard Captures: $amountOfHardCaptures");
			
			$amountOfSoftCaptures = self::getCustomerAdjustmentsTotalFromOrder_AuthOnly($this->dbCmd, $this->orderID);
				
			// Before an order has been completed... You can never create a negative balance adjustments that is greater than all of the positive adjustments.
			if(Math::checkIfFirstFloatParamIsGreater($amount, ($amountOfHardCaptures + $amountOfSoftCaptures)))
				throw new Exception("The order has not been completed yet so any Refund Balance Ajustement has to be less than all of the positive adjustmentsOrderID: $this->orderID Amount: $amount All Captures So Far: " . ($amountOfHardCaptures + $amountOfSoftCaptures));
			
			
			$totalLeftToRefund = $amount;

			// See if we have any Hard Captures (greater than Zero)
			if(Math::checkIfFirstFloatParamIsGreater($amountOfHardCaptures, 0)){
				
				// If we have more left to refund than we have done captures on... then we will have some left over to do with Auth-Only Balance Adjustment Refunds
				if(Math::checkIfFirstFloatParamIsGreater($totalLeftToRefund, $amountOfHardCaptures)){
					$amount_for_this_hard_refund = $amountOfHardCaptures;
					$totalLeftToRefund -= $amountOfHardCaptures;
				}
				else{
					$amount_for_this_hard_refund = $totalLeftToRefund;
					$totalLeftToRefund = 0;
				}
				
				$this->paymentObj->RefundMoneyToOrder($amount_for_this_hard_refund, $this->orderID);
				
				$insertArr[ "VendorAdjustment"] = null;
				$insertArr[ "CustomerAdjustment"] = (-1 * $amount_for_this_hard_refund);
				$insertArr[ "CustomerAdjustmentType"] = self::CUSTOMER_ADJST_REFUND_CAPTURE;
				$insertArr[ "OrderID"] = $this->orderID;
				$insertArr[ "VendorID"] = 0;
				$insertArr[ "FromUserID"] = intval($userID);
				$insertArr[ "Description"] = $refundDescription;
				$insertArr[ "DateCreated"] = time();
				$this->dbCmd->InsertQuery("balanceadjustments",  $insertArr);
			}
			
			
			// Find out if we need to do any soft Refunds.
			// It the amount is greater than Zero
			if(Math::checkIfFirstFloatParamIsGreater($totalLeftToRefund, 0)){
				
				// We don't have to communicate with the bank for this. 
				// When the order completes there will just be less money captured.
				
				$insertArr[ "VendorAdjustment"] = null;
				$insertArr[ "CustomerAdjustment"] = (-1 * $totalLeftToRefund);
				$insertArr[ "CustomerAdjustmentType"] = self::CUSTOMER_ADJST_REFUND_AUTH;
				$insertArr[ "OrderID"] = $this->orderID;
				$insertArr[ "VendorID"] = 0;
				$insertArr[ "FromUserID"] = intval($userID);
				$insertArr[ "Description"] = $refundDescription;
				$insertArr[ "DateCreated"] = time();
				$this->dbCmd->InsertQuery("balanceadjustments",  $insertArr);
			}
		}
	}
	
	
	// Vendor Adjustments do not need to communicate with a gateway.
	// If an order has not been completed you can also make adjustments in the "itemized" list.
	// After an order has been completed you don't have any choice but to perform a balanceadjustment.
	public function giveMoneyToVendor($chargeAmount, $userID, $vendorID, $chargeDescription){
		
		$chargeAmount = WebUtil::FilterData($chargeAmount, FILTER_SANITIZE_FLOAT);
		$chargeAmount = number_format($chargeAmount, 2, '.', '');
		
		if(Math::checkIfFloatIsZero($chargeAmount))
			return;
		
		if($chargeAmount < 0)
			throw new Exception("Negative Balance Adjustments are not allowed in this method. Try taking money from a vendor instead.");
			
		$vendorID = intval($vendorID);
		
		$vendorIDsInOrder = Order::getVendorIDsInOrder($this->dbCmd, $this->orderID);

		if(!in_array($vendorID, $vendorIDsInOrder))
			throw new Exception("The Vendor ID does not exist within the order.");
			
		$insertArr[ "VendorAdjustment"] = $chargeAmount;
		$insertArr[ "CustomerAdjustment"] = null;
		$insertArr[ "CustomerAdjustmentType"] = null;
		$insertArr[ "OrderID"] = $this->orderID;
		$insertArr[ "VendorID"] = $vendorID;
		$insertArr[ "FromUserID"] = intval($userID);
		$insertArr[ "Description"] = $chargeDescription;
		$insertArr[ "DateCreated"] = time();
		$this->dbCmd->InsertQuery("balanceadjustments",  $insertArr);
	}
	
	// Vendor Adjustments do not need to communicate with a gateway.
	// If an order has not been completed you can also make adjustments in the "itemized" list.
	// After an order has been completed you don't have any choice but to perform a balanceadjustment.
	public function takeMoneyFromVendor($chargeAmount, $userID, $vendorID, $chargeDescription){
		
		$chargeAmount = WebUtil::FilterData($chargeAmount, FILTER_SANITIZE_FLOAT);
		$chargeAmount = number_format($chargeAmount, 2, '.', '');
		
		if(Math::checkIfFloatIsZero($chargeAmount))
			return;
		
		if($chargeAmount < 0)
			throw new Exception("Negative Balance Adjustments are not allowed. If you are giving a negative adjustment that means the company is getting more profit.  Nonetheless you should pass in a postiive value to this method if you want to do that.");
			
		$vendorID = intval($vendorID);
		
		$vendorIDsInOrder = Order::getVendorIDsInOrder($this->dbCmd, $this->orderID);

		if(!in_array($vendorID, $vendorIDsInOrder))
			throw new Exception("The Vendor ID does not exist within the order: $vendorID");
			
		$insertArr[ "VendorAdjustment"] = (-1 * $chargeAmount);
		$insertArr[ "CustomerAdjustment"] = null;
		$insertArr[ "CustomerAdjustmentType"] = null;
		$insertArr[ "OrderID"] = $this->orderID;
		$insertArr[ "VendorID"] = $vendorID;
		$insertArr[ "FromUserID"] = intval($userID);
		$insertArr[ "Description"] = $chargeDescription;
		$insertArr[ "DateCreated"] = time();
		$this->dbCmd->InsertQuery("balanceadjustments",  $insertArr);
	}
	
	
}


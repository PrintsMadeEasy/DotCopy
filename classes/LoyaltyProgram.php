<?php

class LoyaltyProgram {
	
	private $domainID;
	private $domainKey;
	private $dbCmd;
	
	function __construct($domainID){
		
		if(!Domain::checkIfDomainIDexists($domainID)){
			throw new Exception("DomainError: Error in LoyaltyProgram Domain ID.");
		}
		
		$this->domainID = $domainID;
		$this->domainKey = Domain::getDomainKeyFromID($domainID);
		$this->dbCmd = new DbCmd();
	}
	
	function retryRefund($loyaltyID){
		
		$this->dbCmd->Query("SELECT ID FROM charges WHERE LoyaltyChargeID=" . intval($loyaltyID));
		$chargeID = $this->dbCmd->GetValue();
		
		if(empty($chargeID))
			throw new Exception("The loyalty charge ID does not exist.");
			
		$this->dbCmd->Query("SELECT * FROM loyaltycharges WHERE ID=" . intval($loyaltyID));
		$row = $this->dbCmd->GetRow();
		
		if(empty($row["RefundChargeLink"]))
			throw new Exception("The given loyalty charge ID does not belong to a Refund type.");
			
		$creditCardObj = $this->getCreditCardObjFromDatabaseRow($row);
		
		$paymentObj = new Payments($this->domainID);
		
		$paymentObj->LoadCustomerByID($row["UserID"]);
		$invoiceNumber = "L" . $loyaltyID;
		$paymentObj->setBillingTypeOverride("N");
		$paymentObj->setCreditCardInfo($creditCardObj, $invoiceNumber);
		
		$paymentObj->RetryChargingCard($chargeID);
		
	}
	
	function refundMoneyToUser($userID, $refundAmount){
		$this->ensureUserIDisValid($userID);
		
		if(!preg_match("/^(\d+|\d+\.\d+)$/", $refundAmount))
			throw new Exception("Refund amount is not correct.");
			
		$totalChargesFromUser = $this->getLoyaltyChargeTotalsFromUser($userID);
		$totalRefundsToUser = $this->getRefundsTotalsToUser($userID);
		$customerBalance = floatval($totalChargesFromUser) - floatval($totalRefundsToUser);
	


		if(Math::checkIfFirstFloatParamIsGreater($refundAmount, $customerBalance))
			throw new Exception("The refund amount can not exceed the balance.");

		$dbCmd2 = new DbCmd();
		
		// We may have to split the refunds over multiple charges.
		$refundTotalPieces = 0;
			
		// Get a list of Loyalty Charge ID's that do not have the total amount refunded.
		// There can be multiple refunds against a single charge.
		$this->dbCmd->Query("SELECT * FROM loyaltycharges WHERE UserID=" . $userID . " AND ChargeAmount > 0 ORDER BY ID DESC");
		while($row = $this->dbCmd->GetRow()){
			
			$loyaltyChargeID = $row["ID"];
			$loyaltyChargeAmnt = $row["ChargeAmount"];
				
			$dbCmd2->Query("SELECT SUM(RefundAmount) FROM loyaltycharges WHERE RefundChargeLink=$loyaltyChargeID");
			$totalRefundsOnThisCharge = $dbCmd2->GetValue();
			
			$amountThatCanBeRefundedAgainstThisCharge = $loyaltyChargeAmnt - $totalRefundsOnThisCharge;
			
			// Skip over Loyalty Charge ID's which have already been refunded.  Remember that sometimes there can be partial refunds against a Charge ID.
			// We are trying to find out if there is any left-over balance on this Loyalty Charge that is able to be refunded.
			if(Math::checkIfFirstFloatParamIsLess($totalRefundsOnThisCharge, $loyaltyChargeAmnt)){
				
				// Find out how much more is left (that we need to refund to the customer).
				$refundBalance = $refundAmount - $refundTotalPieces;
				
				// If we have refunded the total amount that was requested, then stop trying to refund more transactions.
				if($refundBalance < 0.01)
					break;
				
				// This will decide if we can refund the full Charge Amount... or if we need to break it into pieces.
				// For example.  Assume someone has 2 charges for $20 (totalling $40).  And if an employee says we are going to refund $15... then one of the $20 charges will get a partial refund.
				if(Math::checkIfFirstFloatParamIsLessOrEqual($amountThatCanBeRefundedAgainstThisCharge, $refundBalance))
					$amountToRefundOnThisChargeLink = $amountThatCanBeRefundedAgainstThisCharge;
				else 
					$amountToRefundOnThisChargeLink = $refundBalance;
				
				$refundTotalPieces += $amountToRefundOnThisChargeLink;
					
				
				// Insert the "refund record" into our database before we attempt to contact the payment gateway
				// We will allow "retries" on refunds that do not go through.
				// We will start out by making a copy of the original charge row (since most of the info is the same).
				$refundInsert = $row;
				unset($refundInsert["ID"]);
				
				// Refunds do not have a ChargeAmount field... they use the RefundAmount field.
				$refundInsert["ChargeAmount"] = null;
				$refundInsert["RefundAmount"] = number_format($amountToRefundOnThisChargeLink, 2, ".", "");
				$refundInsert["RefundChargeLink"] = $loyaltyChargeID;
				$refundInsert["Date"] = time();
				
				$loyaltyRefundID = $dbCmd2->InsertQuery("loyaltycharges", $refundInsert);
				
				
				// Now attempt to contact the payment gateway and put the refund through.
				$creditCardObj = $this->getCreditCardObjFromDatabaseRow($row);
				
				
				$invoiceNumber = "L" . $loyaltyChargeID;
		
				// Do an Auth/Capture.  If it goes though, then create an entry within the database.
				$paymentObj = new Payments($this->domainID);
				
				$paymentObj->LoadCustomerByID($userID);
				$paymentObj->setBillingTypeOverride("N");
				$paymentObj->setCreditCardInfo($creditCardObj, $invoiceNumber);
				$paymentObj->SetTransactionAmounts($amountToRefundOnThisChargeLink, 0, 0);
				
				
				$dbCmd2->Query("SELECT ID, TransactionID FROM charges WHERE (ChargeType='C' OR ChargeType='B') AND LoyaltyChargeID=$loyaltyChargeID");
				if($dbCmd2->GetNumRows() == 0)
					throw new Exception("Can not find a Loyalty Charge Record for Loyalty ChargeID $loyaltyChargeID");
				$chargeRow = $dbCmd2->GetRow();
				
				$chargeTransactionID = $chargeRow["TransactionID"];
				$chargeIDofCapture = $chargeRow["ID"];
				
				
				// Fire the transaction to authorize net
				$paymentObj->RefundPayment($chargeTransactionID);
				$paymentObj->_CollectPaymentResponses();

				// This the transaction against the Refund Entry in our "loyaltycharges" table (not the original charge ID).
				$paymentObj->setLoyaltyIdLink($loyaltyRefundID);
				$paymentObj->setOrderIdLink(0);
				
				// The $chargeIDofCapture is what associated the refund entry to the original charge (within the "charges" table).  
				// We also associate refunds against charges within the "loyaltycharges" table through the use of the "RefundChargeLink" column.
				$paymentObj->CreateNewTransactionRecord($chargeIDofCapture);
				
			}
			
		}
		
	}
	
	// Returns a CreditCard object if there a card available (with a valid expiration).
	// Otherwise it returns NULL
	function getCreditCardForNextLoyaltyCharge($userID){
		$this->ensureUserIDisValid($userID);
		
		// If there is already a loyalty charge... use the CC# that was used for the last one.
		// Otherwise, use the the CCnumber from the last order.
		$this->dbCmd->Query("SELECT MAX(ID) FROM loyaltycharges WHERE UserID=" . intval($userID) . " AND ChargeAmount > 0");
		$lastLoyalityChargeID = $this->dbCmd->GetValue();
		
		if(!empty($lastLoyalityChargeID)){
			$this->dbCmd->Query("SELECT * FROM loyaltycharges WHERE ID=" . intval($lastLoyalityChargeID));
			$row = $this->dbCmd->GetRow();
			
			// If the last loyalty charge hasn't expired, then return the credit card object.
			if(!$this->isCreditCardExpired($row["MonthExpiration"], $row["YearExpiration"])){
				return $this->getCreditCardObjFromDatabaseRow($row);
			}
		}
		
		// If the CC Num is expired, or there isn't an existing loyalty charge...
		// Then look for info on the last order within the system.
		$this->dbCmd->Query("SELECT MAX(ID) FROM orders WHERE UserID=" . intval($userID) . " AND BillingType='N'");
		$lastOrderID = $this->dbCmd->GetValue();
		
		// If the user hasn't placed an order, there is deffinetly no credit card info.
		if(empty($lastOrderID))
			return null;
			
		$this->dbCmd->Query("SELECT * FROM orders WHERE ID=" . intval($lastOrderID));
		$row = $this->dbCmd->GetRow();
		
		if(!$this->isCreditCardExpired($row["MonthExpiration"], $row["YearExpiration"])){
			return $this->getCreditCardObjFromDatabaseRow($row);
		}
		
		return null;
	}
	
	private function getCreditCardObjFromDatabaseRow($row){
		
		$creditCardObj = new CreditCard();
		$creditCardObj->setCardType($row["CardType"]);
		$creditCardObj->setCardNumber($row["CardNumber"]);
		$creditCardObj->setMonthExpiration($row["MonthExpiration"]);
		$creditCardObj->setYearExpiration($row["YearExpiration"]);
		$creditCardObj->setBillingName($row["BillingName"]);
		$creditCardObj->setBillingCompany($row["BillingCompany"]);
		$creditCardObj->setBillingAddress($row["BillingAddress"]);
		$creditCardObj->setBillingAddressTwo($row["BillingAddressTwo"]);
		$creditCardObj->setBillingCity($row["BillingCity"]);
		$creditCardObj->setBillingState($row["BillingState"]);
		$creditCardObj->setBillingZip($row["BillingZip"]);
		$creditCardObj->setBillingCountry($row["BillingCountry"]);
		
		return $creditCardObj;
	}
	
	private function isCreditCardExpired($monthExpires, $yearExpiration){
		
		$monthExpires = intval($monthExpires);
		$yearExpiration = intval($yearExpiration);
		
		if(strlen($yearExpiration) == 2)
			$yearExpiration = intval("20" . $yearExpiration);
			
		if($yearExpiration > date("Y", time()))
			return false;
			
		if($yearExpiration == date("Y", time())){
			if($monthExpires >= date("n", time()))
				return false;
		}
		
		return true;
	}
	
	
	// Return TRUE if it is time to charge the customer for their loyalty enrollment.
	// It will return FALSE if the user was already charged today.
	// This should be called on a daily Cron.  Better to call it multiple times per day in case the script crashes half-way through. (it won't double charge people).
	// It is supposed to charge people after placing their first order, and then on a monthly interval (from the time their first order was placed).
	// If the user created their account on the 31st of the month... but we are in February... it will charge them on the last day of the month.
	function shouldCustomerBeChargedToday($userID){
		
		$this->ensureUserIDisValid($userID);
		
		// Users who are not enrolled in the program should not be charged.
		$this->dbCmd->Query("SELECT LoyaltyProgram FROM users WHERE ID=" . intval($userID));
		if($this->dbCmd->GetValue() != "Y")
			return false;
		
		// Make sure that the customer wasn't charged within the past week
		$this->dbCmd->Query("SELECT COUNT(*) FROM loyaltycharges USE INDEX(loyaltycharges_UserID)
						WHERE UserID=" . intval($userID) . " AND Date > '" . DbCmd::FormatDBDateTime(time() - (60 * 60 * 24 * 7)) . "'");		
		if($this->dbCmd->GetValue() > 0)
			return false;
			
		// Find out if the user has a "Declined" charge from today.  That is different than having an "Error".
		// Since this script runs a few times each day there is no point in attempting more authorizations.
		$this->dbCmd->Query("SELECT MissedReason FROM loyaltymissedcharges WHERE UserID=" . intval($userID) . " 
						AND Date BETWEEN '".DbCmd::FormatDBDateTime(mktime(0,0,0,date("n"), date("j"), date("Y")))."' 
									 AND '".DbCmd::FormatDBDateTime(mktime(23,59,59,date("n"), date("j"), date("Y")))."'");
		if($this->dbCmd->GetValue() == "D")
			return false;
		
		// Until the user has placed at least one order... we will not have a credit card on file.
		$this->dbCmd->Query("SELECT UNIX_TIMESTAMP(DateOrdered) FROM orders WHERE UserID=" . intval($userID) . " ORDER BY ID ASC LIMIT 1");
		if($this->dbCmd->GetNumRows() == 0)
			return false;
			
		$dayOfMonthFromFirstOrder = intval(date("j", $this->dbCmd->GetValue()));
			
		// If the user has placed an order... and they are a loyalty member... and they have not paid an enrollment fee yet...
		//... then they should be charged today (regardless of the day that the first order was placed).
		// For example, if they place their order at 11:59 PM, we may not have a cron script catch it on the same day.  We would want the cron to catch it the following day.
		$this->dbCmd->Query("SELECT COUNT(*) FROM loyaltycharges
						WHERE UserID=" . intval($userID));	
		$totalLoyaltyChargesByUser = $this->dbCmd->GetValue();
		
		$this->dbCmd->Query("SELECT COUNT(*) FROM loyaltymissedcharges
						WHERE UserID=" . intval($userID));	
		$totalMissedChargesByUser = $this->dbCmd->GetValue();
		
		if($totalLoyaltyChargesByUser == 0 && $totalMissedChargesByUser == 0)
			return true;
			
		
		$currentDay = intval(date("j", time()));
		
		// If the first order happened on the 15th (and it is the 15th today) then they should be charged today.
		if($dayOfMonthFromFirstOrder == $currentDay)
			return true;
			
		// It is possible that someone could have placed their first order on the 31st, and this month does not have that many days.
		// Find out if it is the last day of the month today.  If so, find out if the "day of first order" is greater than the last day of the month (today).
		$daysInCurrentMonth = intval(date("t", time())); // returns a number between 28 & 31
		if($currentDay == $daysInCurrentMonth){
			
			// In this spot... we know we are on the last day of the month
			// Now let's find out if the original order date had a month-day greater than the number of days possible in this month.
			if($dayOfMonthFromFirstOrder > $currentDay)
				return true;
		}
		
		return false;
	}
	
	private function insertMissedChargeRecord($userID, $statusReason, $descReason){
		
		$this->ensureUserIDisValid($userID);
		
		$missedChargeinsertArr["UserID"] = $userID;
		$missedChargeinsertArr["DomainID"] = $this->domainID;
		$missedChargeinsertArr["Date"] = time();
		$missedChargeinsertArr["ChargeAmount"] = $this->getMontlyFee($userID);
		$missedChargeinsertArr["MissedReason"] = $statusReason;
		$missedChargeinsertArr["MissedReasonDesc"] = $descReason;
		
		$this->dbCmd->InsertQuery("loyaltymissedcharges", $missedChargeinsertArr);
	}
	
	// Returns false is the transaction does not go through.
	function chargeCustomerLoyaltyEnrollment($userID){
		
		$this->ensureUserIDisValid($userID);
		
		if(!$this->shouldCustomerBeChargedToday($userID))
			throw new Exception("Error in chargeCustomerLoyaltyEnrollment, not ready to be charged.");
			
			
		// Find out if the the user has corporate billing.
		$this->dbCmd->Query("SELECT COUNT(*) FROM orders WHERE UserID=" . intval($userID) . " AND BillingType='N'");
		if($this->dbCmd->GetValue() == 0){
			$this->insertMissedChargeRecord($userID, "L", "Billing type doesn't use a Credit Card.");
			return false;
		}
			
		$creditCardObj = $this->getCreditCardForNextLoyaltyCharge($userID);


		// If the credit card has expired, we can't charge the customer.
		// We want to record into the DB why we were not able to charge the customer.
		if(empty($creditCardObj)){
			
			// Don't add multiple "missed charge" records within the same day
			$this->dbCmd->Query("SELECT COUNT(*) FROM loyaltymissedcharges USE INDEX(loyaltymissedcharges_UserID)
							WHERE UserID=" . intval($userID) . " AND Date > '" . DbCmd::FormatDBDateTime(time() - (60 * 60 * 24 * 2)) . "'");		
			if($this->dbCmd->GetValue() > 0)
				return false;
			
			// "eXpired" is going to happen a lot.  No need to fill up the DB with a description.
			$this->insertMissedChargeRecord($userID, "X", ""); 
			return false;
		}
		
		
		// Set the invoice number to the auto-increment of the loyalty charge.
		$loyaltyRowInsert["UserID"] = $userID;
		$loyaltyRowInsert["DomainID"] = $this->domainID;
		$loyaltyRowInsert["ChargeAmount"] = $this->getMontlyFee($userID);
		$loyaltyRowInsert["RefundAmount"] = null;
		$loyaltyRowInsert["CardType"] = $creditCardObj->getCardType();
		$loyaltyRowInsert["CardNumber"] = $creditCardObj->getCardNumber();
		$loyaltyRowInsert["MonthExpiration"] = $creditCardObj->getMonthExpiration();
		$loyaltyRowInsert["YearExpiration"] = $creditCardObj->getYearExpiration();
		$loyaltyRowInsert["BillingName"] = $creditCardObj->getBillingName();
		$loyaltyRowInsert["BillingCompany"] = $creditCardObj->getBillingCompany();
		$loyaltyRowInsert["BillingAddress"] = $creditCardObj->getBillingAddress();
		$loyaltyRowInsert["BillingAddressTwo"] = $creditCardObj->getBillingAddressTwo();
		$loyaltyRowInsert["BillingCity"] = $creditCardObj->getBillingCity();
		$loyaltyRowInsert["BillingState"] = $creditCardObj->getBillingState();
		$loyaltyRowInsert["BillingZip"] = $creditCardObj->getBillingZip();
		$loyaltyRowInsert["BillingCountry"] = $creditCardObj->getBillingCountry();
		$loyaltyRowInsert["Date"] = time();
		
		$loyaltyInsertID = $this->dbCmd->InsertQuery("loyaltycharges", $loyaltyRowInsert);
		$invoiceNumber = "L" . $loyaltyInsertID;
		
		// Do an Auth/Capture.  If it goes though, then create an entry within the database.
		$paymentObj = new Payments($this->domainID);
		
		$paymentObj->LoadCustomerByID($userID);
		$paymentObj->setBillingTypeOverride("N");
		$paymentObj->setCreditCardInfo($this->getCreditCardForNextLoyaltyCharge($userID), $invoiceNumber);
		$paymentObj->SetTransactionAmounts($this->getMontlyFee($userID), 0, 0);
		
		
		if(!$paymentObj->AuthorizeAndCapture()){
			$this->insertMissedChargeRecord($userID, $paymentObj->GetTransactionStatus(), $paymentObj->GetErrorReason());
			
			// Delete the loyalty charge row (if the charge was declined (or failed to connect).
			$this->dbCmd->Query("DELETE FROM loyaltycharges WHERE ID=" . intval($loyaltyInsertID));
			
			return false;
		}
		else{
			$paymentObj->setLoyaltyIdLink($loyaltyInsertID);
			$paymentObj->CreateNewTransactionRecord(0);
			
			return true;
		}
		
	}
	
	
	
	// Decide whether to display a loyalty option to the customer
	// UserID is not required, but pass it in if you have it.
	static function displayLoyalityOptionForVisitor($userID = null){
		
		//if(!Domain::getLoyaltyDiscountFlag(Domain::getDomainIDfromURL()))
			return false;
		
		$userID = intval($userID);
		$dbCmd = new DbCmd();
			
		// If a user has ever been enrolled in the past, make sure that they always have the option to re-enroll.
		if(!empty($userID)){
			
			// Don't allow the loyalty program to customers with "corporate billing".
			$dbCmd->Query("SELECT BillingType FROM users WHERE ID=" . intval($userID));
			if($dbCmd->GetValue() != "N")
				return false;
	
			$dbCmd->Query("SELECT COUNT(*) FROM loyaltycharges WHERE UserID=" . intval($userID));
			if($dbCmd->GetValue() > 0)
				return true;

			$dbCmd->Query("SELECT LoyaltyProgram FROM users WHERE ID=" . intval($userID));
			if($dbCmd->GetValue() == "Y")
				return true;

		}
		
		if(IPaccess::checkIfUserIpHasSomeAccess())
			return true;
		
		$userIpAddress = WebUtil::getRemoteAddressIp();
		
		$maxMindObj = new MaxMind();
		if(!$maxMindObj->loadIPaddressForLocationDetails($userIpAddress))
			return false;
		
		if($maxMindObj->checkIfOpenProxy())
			return false;
			
		// Find out how many sessions have been establised at all domains (starting more than 3 days ago).
		// If the loyalty program turns out to greatly assist our customer retention, we don't want to reveal program details to a competitor.
		$daysBackToStartCountingVisits = 7;
		$dbCmd->Query("SELECT COUNT(*) FROM visitorsessiondetails USE INDEX(visitorsessiondetails_IPaddress)
						WHERE IPaddress = '" . DbCmd::EscapeLikeQuery($userIpAddress) . "'
						AND DateStarted < '" . DbCmd::FormatDBDateTime(time() - (60 * 60 * 24 * $daysBackToStartCountingVisits)) . "'");
		$totalSessionsByIP = $dbCmd->GetValue();
		
		if($totalSessionsByIP > 3)
			return false;	
			
		return true;
	}

	
	// Returns a dollar amount to discount from shipping.
	function getLoyaltyDiscountShipping($weightOfShipmentInPounds){
			
		$weightOfShipmentInPounds = ceil($weightOfShipmentInPounds);

		if(in_array($this->domainKey, array("BusinessCards24.com"))){
			
			// Find out the cheapeast shipping method for the domain.
			// Then find out what the cost would be, based upon the weight of the shipment.
			$shippingChoicesObj = new ShippingChoices($this->domainID);
			$cheapestShippingChoiceID = $shippingChoicesObj->getLowestShippingChoicePriorityWithCheapestPrice();
			
			$shippingBasePrice = $shippingChoicesObj->getBasePrice($cheapestShippingChoiceID);
			$shippingPricePerPound = $shippingChoicesObj->getPricePerPound($cheapestShippingChoiceID, $weightOfShipmentInPounds);
			
			$lowestShippingPrice = $shippingBasePrice + ($shippingPricePerPound * $weightOfShipmentInPounds);
			
			return number_format($lowestShippingPrice, 2, '.', '');
		}

		return 0;
	}
	
	// Returns an amount to charge he customer to be enrolled within the program.
	// If you supply a UserID it will use the first fee.  That will allow us to change prices in the future without disrupting previous agreements.
	function getMontlyFee($userID = null){
		
		if(!empty($userID)){
			$this->ensureUserIDisValid($userID);
			
			$this->dbCmd->Query("SELECT ChargeAmount FROM loyaltycharges WHERE 
								UserID=" . intval($userID) . " AND ChargeAmount > 0
								ORDER BY ID ASC LIMIT 1");
			$lastChargeAmount = $this->dbCmd->GetValue();
			
			if(!empty($lastChargeAmount))
				return $lastChargeAmount;
		}
		

		if(in_array($this->domainKey, array("BusinessCards24.com"))){
			return "19.24";
		}
		else if(in_array($this->domainKey, array("Postcards.com"))){
			return "25.24";
		}

		return 0;
	}
	
	// Returns a number between 0 and 100.  100 means a 100% discount.
	function getLoyaltyDiscountSubtotalPercentage(){
		
		if(in_array($this->domainKey, array("Postcards.com"))){
			return 20;
		}

		return 0;
	}
	
	
	function getCountOfSuccessfulChargesFromUser($userID){
		
		$this->ensureUserIDisValid($userID);
		
		$this->dbCmd->Query("SELECT COUNT(*) FROM loyaltycharges WHERE UserID=" . intval($userID) . " AND ChargeAmount > 0");
		return $this->dbCmd->GetValue();
	}

	function getCountOfFailedChargesFromUser($userID){
		
		$this->ensureUserIDisValid($userID);
		
		$this->dbCmd->Query("SELECT COUNT(*) FROM loyaltymissedcharges WHERE UserID=" . intval($userID));
		return $this->dbCmd->GetValue();
	}
	
	
	static function getLoyaltyChargesWithinDateRange(array $domainIDarr, $startTimeStamp, $endTimeStamp){
		
		self::ensureDomainIDsValid($domainIDarr);
		self::ensureDateTimeStamps($startTimeStamp, $endTimeStamp);
		
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT SUM(ChargeAmount) FROM loyaltycharges 
							WHERE " . DbHelper::getOrClauseFromArray("DomainID", $domainIDarr) . " 
							AND (Date BETWEEN '".DbCmd::FormatDBDateTime($startTimeStamp)."' AND '".DbCmd::FormatDBDateTime($endTimeStamp)."')");
		
		return number_format($dbCmd->GetValue(), 2, '.', '');
	}
	
	static function getLoyaltyRefundsWithinDateRange(array $domainIDarr, $startTimeStamp, $endTimeStamp){
		
		self::ensureDomainIDsValid($domainIDarr);
		self::ensureDateTimeStamps($startTimeStamp, $endTimeStamp);
		
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT SUM(RefundAmount) FROM loyaltycharges 
							WHERE " . DbHelper::getOrClauseFromArray("DomainID", $domainIDarr) . " 
							AND (Date BETWEEN '".DbCmd::FormatDBDateTime($startTimeStamp)."' AND '".DbCmd::FormatDBDateTime($endTimeStamp)."')");
		
		return number_format($dbCmd->GetValue(), 2, '.', '');
	}
	
	static function getShippingSavingsWithinDateRange(array $domainIDarr, $startTimeStamp, $endTimeStamp){
		
		self::ensureDomainIDsValid($domainIDarr);
		self::ensureDateTimeStamps($startTimeStamp, $endTimeStamp);
		
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT SUM(ShippingDiscount) FROM loyaltysavings 
							INNER JOIN orders ON loyaltysavings.OrderID = orders.ID
							WHERE " . DbHelper::getOrClauseFromArray("DomainID", $domainIDarr) . " 
							AND (DateOrdered BETWEEN '".DbCmd::FormatDBDateTime($startTimeStamp)."' AND '".DbCmd::FormatDBDateTime($endTimeStamp)."')");
		
		return number_format($dbCmd->GetValue(), 2, '.', '');
	}
	
	static function getSubtotalSavingsWithinDateRange(array $domainIDarr, $startTimeStamp, $endTimeStamp){
		
		self::ensureDomainIDsValid($domainIDarr);
		self::ensureDateTimeStamps($startTimeStamp, $endTimeStamp);
		
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT SUM(SubtotalDiscount) FROM loyaltysavings 
							INNER JOIN orders ON loyaltysavings.OrderID = orders.ID
							WHERE " . DbHelper::getOrClauseFromArray("DomainID", $domainIDarr) . " 
							AND (DateOrdered BETWEEN '".DbCmd::FormatDBDateTime($startTimeStamp)."' AND '".DbCmd::FormatDBDateTime($endTimeStamp)."')");
		
		return number_format($dbCmd->GetValue(), 2, '.', '');
	}
	
	
	static function getTotalSavingsWithinDateRange(array $domainIDarr, $startTimeStamp, $endTimeStamp){
		
		$totalAmnt = self::getShippingSavingsWithinDateRange($domainIDarr, $startTimeStamp, $endTimeStamp) + self::getSubtotalSavingsWithinDateRange($domainIDarr, $startTimeStamp, $endTimeStamp);
		return number_format($totalAmnt, 2, '.', ''); 
	}
	

	function getShippingDiscountFromOrder($orderID){
		$this->dbCmd->Query("SELECT ShippingDiscount FROM loyaltysavings WHERE OrderID=" . intval($orderID));
		return number_format($this->dbCmd->GetValue(), 2, '.', '');
	}
	function getSubtotalDiscountFromOrder($orderID){
		$this->dbCmd->Query("SELECT SubtotalDiscount FROM loyaltysavings WHERE OrderID=" . intval($orderID));
		return number_format($this->dbCmd->GetValue(), 2, '.', '');
	}

	function getLoyaltyChargeTotalsFromUser($userID){
		
		$this->ensureUserIDisValid($userID);
		$this->dbCmd->Query("SELECT SUM(ChargeAmount) FROM loyaltycharges WHERE UserID=" . intval($userID));
		
		return number_format($this->dbCmd->GetValue(), 2, '.', '');
	}
	function getLoyaltyChargeCountsFromUser($userID){
		
		$this->ensureUserIDisValid($userID);
		$this->dbCmd->Query("SELECT COUNT(*) FROM loyaltycharges WHERE UserID=" . intval($userID) . " AND ChargeAmount > 0");
		
		return $this->dbCmd->GetValue();
	}
	
	function getRefundsErrorTotal($userID){
		
		$this->ensureUserIDisValid($userID);
		$this->dbCmd->Query("SELECT ID FROM loyaltycharges WHERE UserID=" . intval($userID) . " AND RefundAmount > 0");
		$loyaltyRefundIDsArr = $this->dbCmd->GetValueArr();
		
		if(empty($loyaltyRefundIDsArr))
			return "0";
		
		$this->dbCmd->Query("SELECT SUM(ChargeAmount) FROM charges USE INDEX(charges_LoyaltyChargeID) 
							WHERE Status != 'A' AND (" . DbHelper::getOrClauseFromArray("LoyaltyChargeID", $loyaltyRefundIDsArr) . ")");
	
		return number_format($this->dbCmd->GetValue(), 2, '.', '');
	}
	
	function getRefundsTotalsToUser($userID){
		
		$this->ensureUserIDisValid($userID);
		$this->dbCmd->Query("SELECT SUM(RefundAmount) FROM loyaltycharges WHERE UserID=" . intval($userID));
		
		return number_format($this->dbCmd->GetValue(), 2, '.', '');
	}
	
	function getShippingSavingsForUser($userID){
		
		$this->ensureUserIDisValid($userID);
		$this->dbCmd->Query("SELECT SUM(ShippingDiscount) FROM loyaltysavings 
							INNER JOIN orders ON loyaltysavings.OrderID = orders.ID 
							WHERE orders.UserID=" . intval($userID));
		
		return number_format($this->dbCmd->GetValue(), 2, '.', '');
	}
	
	function getSubtotalSavingsForUser($userID){
		
		$this->ensureUserIDisValid($userID);
		$this->dbCmd->Query("SELECT SUM(SubtotalDiscount) FROM loyaltysavings 
							INNER JOIN orders ON loyaltysavings.OrderID = orders.ID 
							WHERE orders.UserID=" . intval($userID));
		
		return number_format($this->dbCmd->GetValue(), 2, '.', '');
	}
	
	function getTotalSavingsForUser($userID){
		$totalAmnt = $this->getShippingSavingsForUser($userID) + $this->getSubtotalSavingsForUser($userID);
		return number_format($totalAmnt, 2, '.', '');
	}
	
	private function ensureUserIDisValid($userID){
		
		if(!UserControl::CheckIfUserIDexists($this->dbCmd, $userID))
			throw new Exception("User does not exist.");
			
		if($this->domainID != UserControl::getDomainIDofUser($userID))
			throw new Exception("The domain ID does match the user.");
	}
	
	private static function ensureDomainIDsValid(array $domainIDarr){
		
		foreach($domainIDarr as $thisDomainId){
			if(!Domain::checkIfDomainIDexists($thisDomainId))
				throw new Exception("The domain ID does not exist.");
		}
		
		if(empty($domainIDarr))
			throw new Exception("The domain collection can not be empty.");
	}
	
	private static function ensureDateTimeStamps($startTimeStamp, $endTimeStamp){
		if(intval($endTimeStamp) <= intval($startTimeStamp))
			throw new Exception("Date range is invalid.");
	}

	
}


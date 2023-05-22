<?php

class CustomerWorth {
	
	
	private $dbCmd;
	private $trackingCode;
	private $couponIDarr;
	private $artworkKeywords;
	private $companyOrName;
	
	private $customerIDsArr;
	private $productIDsArr;
	
	private $acqSpanStart;
	private $acqSpanEnd;
	
	private $endPeriodDate;
	private $endPeriodDaysFromAcqDate;
	
	private $incBalanceAdjustmentsFlag;
	private $incShippingAndHandling;
	
	private $orderIDsUntilEndPeriodArr_Cache;
	private $orderIDsFirstTimeArr_Cache;
	
	private $domainIDsArr;
	
	private $userIDsFetchedFlag;
	
	private $includeNomadFactorFlag;
	private $nomadPercentage;

	function __construct(array $domainIDsArr){
		
		$this->customerIDsArr = array();
		$this->couponIDarr = array();
		
		$this->dbCmd = new DbCmd();
	
		$this->incBalanceAdjustmentsFlag = true;
		$this->incShippingAndHandling = true;
		
		$this->userIDsFetchedFlag = false;
		$this->includeNomadFactorFlag = false;
		
		// We figure that a customer is worth x% more than we can calculate because of computers without cookies... and "tell a friend" etc.
		$this->nomadPercentage = 0.2;
		
		// Default to the Domain in the URL if not provided.
		if(empty($domainIDsArr))
			$domainIDsArr = array(Domain::getDomainIDfromURL());
			
		foreach($domainIDsArr as $thisDomainID){
			if(!Domain::checkIfDomainIDexists($thisDomainID))
				throw new Exception("Illegal Domain ID: " . $thisDomainID);
		}
		
		$this->domainIDsArr = $domainIDsArr;
	}
	
	// Right now this only affects getAverageProfitByEndPeriod()
	function setNomadFactor($flag){
		if(!is_bool($flag))
			throw new Exception("The flag must be boolean");
			
		$this->includeNomadFactorFlag = $flag;
	}
	
	
	// This is the period that we want to search for new customers within.
	// For example... We may set this for a week period (about 1 year in the past).  
	// Then we will find out how much those new customers have spent  until present time.
	function setNewCustomerAcquisitionPeriod($startDate, $endDate){
		
		$this->userIDsFetchedFlag = false;
		
		$startDate = intval($startDate);
		$endDate = intval($endDate);
		
		if($startDate > $endDate)
			throw new Exception("The acquistion start date can not fall after the end date.");
			
		$this->acqSpanStart = $startDate;
		$this->acqSpanEnd = $endDate;
		
		
	}
	
	// Will look within the NewCustomerAcquisitionPeriod for new customers who bought with matching tracking code.
	// You can also use wildcards.  For example to look for all new google customes within the period... pass in a tracking code like "g-*"
	function setTrackingCodeSearch($bannerTrackingCode){
		
		$this->userIDsFetchedFlag = false;
		
		$this->trackingCode = $bannerTrackingCode;
	}
	
	// New Customers must use a matching coupon code to be included.
	// If the user has multiple Domains selected... then there could be many Coupon IDs with a matching code name.
	function setCouponCode($couponCode){
		
		$this->userIDsFetchedFlag = false;
		
		$this->couponIDarr = array();
		
		$domainObj = Domain::singleton();
		$selectedDomainIDs = $domainObj->getSelectedDomainIDs();
		
		foreach($selectedDomainIDs as $thisDomainID){
			$couponID = Coupons::getCouponIdFromCouponCode($thisDomainID, $couponCode);
			if(!empty($couponID))
				$this->couponIDarr[] = $couponID;
		}
	}
	
	// New Customers must have the following keywords within their artwork file to be included.
	function setArtworkKeywordSearch($artworkKeywords){
		
		$this->userIDsFetchedFlag = false;
		
		$this->artworkKeywords = $artworkKeywords;
		
	}
	
	// Only new customers with a matching name (within the NewCustomerAcquisitionPeriod) will be included.
	function setCompanyOrCustomerName($billingNameOrCompanyName){
		
		$this->userIDsFetchedFlag = false;
		
		$this->companyOrName = $billingNameOrCompanyName;
	}
	
	// Limits users IDs within a period that have ordered any of the products within the aquisition period.
	function setProductIdLimitArr($productIDarr){
		
		$this->userIDsFetchedFlag = false;
		
		$filteredArr = array();
		
		foreach($productIDarr as $thisProductID)
			$filteredArr[] = intval($thisProductID);
			
		$this->productIDsArr = $filteredArr;
	}

	
	// Looks to see what customers were worth by by the End Date
	// You can not have the End Period date happen before the "endDate" of the NewCustomerAcquisitionPeriod
	function setEndPeriodDate($date){

		$this->userIDsFetchedFlag = false;
		
		$this->endPeriodDate = intval($date);
	}
	
	// Part of the problem with using "setEndPeriodDate" is that if you round up new customers over a 3 month period (between Jan & March)
	// ... then you want to see what they are worth by the end of April. Some of the customers from January will have had a lot more time to reorder thant he ones aquired in March.
	// So this method instead looks at each and every Customer's acquistion date and looks at their customer worth X days into the future
	function setEndPeriodDaysFromAcqDate($numberOfDaysLater){
		$this->userIDsFetchedFlag = false;
		$this->endPeriodDaysFromAcqDate = abs(intval($numberOfDaysLater));
	}
	
	// To call this method you must first set the Acquistion Dates.
	// It will set the end period X number of days from the End Acquisition Date
	function setEndPeriodByDays($numOfDays){
		
		$this->userIDsFetchedFlag = false;
		
		$numOfDays = intval($numOfDays);
		if($numOfDays < 0)
			throw new Exception("Number of days is illegal");
			
		if(empty($this->acqSpanEnd))
			throw new Exception("You must set the Acquistion Dates before calling this method.");

		// Increase the unix timestamp of the end period by the amount of days we want to look ahead.
		$this->endPeriodDate += $numOfDays + 60 * 60 * 24 + $this->acqSpanEnd; 
	}
	
	
	function setBalanceAdjustmentsInclusionFlag($flag){
		
		$this->userIDsFetchedFlag = false;
		
		if(!is_bool($flag))
			throw new Exception("Parameter has to be bool");
			
		$this->incBalanceAdjustmentsFlag = $flag;
	}
	
	function setShippingHandlingProfitInclusionFlag($flag){
		
		$this->userIDsFetchedFlag = false;
		
		if(!is_bool($flag))
			throw new Exception("Parameter has to be bool");
			
		$this->incShippingAndHandling = $flag;
	}
	
	function getUserIDsFromAcquisitionTimeSpan(){
		
		// In case any parameters have changed, we need to re-run the query.
		if(!$this->userIDsFetchedFlag)
			$this->setCustomerIDs();
		
		return $this->customerIDsArr;
	}
	
	function getUserCountInAcqSpan(){
		
		// In case any parameters have changed, we need to re-run the query.
		if(!$this->userIDsFetchedFlag)
			$this->setCustomerIDs();
			
		return sizeof($this->customerIDsArr);
	}
	
	
	function getRevenueTotalOnFirstOrder(){
		
		$orderIDs = $this->getOrderIDsOfFirstOrders();
		
		$totalRevenue = 0;
		
		foreach($orderIDs as $thisOrderID)
			$totalRevenue += $this->getRevenueTotalFromOrderID($thisOrderID);
		
		return $totalRevenue;
	}
	
	function getProfitTotalOnFirstOrders(){
		
		$orderIDs = $this->getOrderIDsOfFirstOrders();
		
		$totalProfit = 0;
		
		foreach($orderIDs as $thisOrderID)
			$totalProfit += $this->getProfitFromOrderID($thisOrderID);
		
		return $totalProfit;
	}
	
	function getDiscountTotalOnFirstOrders(){
		
		$allOrderIDs = $this->getOrderIDsOfFirstOrders();
		
		$totalDiscounts = 0;
		
		foreach($allOrderIDs as $thisOrderID)
			$totalDiscounts += $this->getDiscountFromOrderID($thisOrderID);
			
		return $totalDiscounts;
	}

	function getRevenueTotalByEndPeriod(){
		
		$allOrderIDs = $this->getOrderIDsUntilEndPeriod();
		
		$totalRevenue = 0;
		
		foreach($allOrderIDs as $thisOrderID)
			$totalRevenue += $this->getRevenueTotalFromOrderID($thisOrderID);
		
		return $totalRevenue;
	}

	function getProfitTotalByEndPeriod(){
		
		$allOrderIDs = $this->getOrderIDsUntilEndPeriod();
		
		$totalProfit = 0;
		
		foreach($allOrderIDs as $thisOrderID)
			$totalProfit += $this->getProfitFromOrderID($thisOrderID);
			
		return $totalProfit;
	}
	
	function getDiscountTotalByEndPeriod(){
		
		$allOrderIDs = $this->getOrderIDsUntilEndPeriod();
		
		$totalDiscounts = 0;
		
		foreach($allOrderIDs as $thisOrderID)
			$totalDiscounts += $this->getDiscountFromOrderID($thisOrderID);
			
		return $totalDiscounts;
	}
	

	
	function getAverageRevenueByEndPeriod(){
		
		$totalUsers = $this->getUserCountInAcqSpan();
		
		if(empty($totalUsers))
			return 0;
			
		return number_format($this->getRevenueTotalByEndPeriod() / $totalUsers, 2, '.', '');
	}
	
	function getAverageRevenueOnFirstOrder(){
		
		$totalUsers = $this->getUserCountInAcqSpan();
		
		if(empty($totalUsers))
			return 0;
			
		return number_format($this->getRevenueTotalOnFirstOrder() / $totalUsers, 2, '.', '');
	}
	
	function getAverageDiscountOnFirstOrder(){
		
		$totalUsers = $this->getUserCountInAcqSpan();
		
		if(empty($totalUsers))
			return 0;
			
		return number_format($this->getDiscountTotalOnFirstOrders() / $totalUsers, 2, '.', '');
	}
	
	function getAverageProfitByEndPeriod(){
		
		$totalUsers = $this->getUserCountInAcqSpan();
		
		if(empty($totalUsers))
			return 0;
			
		$avgProfit = $this->getProfitTotalByEndPeriod() / $totalUsers;
		
		if($this->includeNomadFactorFlag)
			$avgProfit = $avgProfit + ($avgProfit * $this->nomadPercentage);
		
		return number_format($avgProfit, 2, '.', '');
	}
	
	function getNomadPercentage(){
		return $this->nomadPercentage;
	}
	function setNomadPercentage($x){
		$this->nomadPercentage = floatval($x);
	}
	
	function getAverageProfitOnFirstOrder(){
		
		$totalUsers = $this->getUserCountInAcqSpan();
		
		if(empty($totalUsers))
			return 0;
			
		return number_format($this->getProfitTotalOnFirstOrders() / $totalUsers, 2, '.', '');
	}
	

	// Returns the profit from an order depending on flags set in this method.
	// Does not include profit from S&H unless the order has been shipped.
	// ... otherwise it will skew numbers for profit if freight charges have not been imported.
	private function getProfitFromOrderID($orderID){
		
		$orderID = intval($orderID);
		
		// No profit exists from an empty order.
		if(!Order::CheckForActiveProjectWithinOrder($this->dbCmd, $orderID))
			return 0;
		
		$orderProfit = 0;
		
		// Profit needs discounts and vendor totals subtracted.
		$orderProfit += Order::GetTotalFromOrder($this->dbCmd, $orderID, "customersubtotal");
		$orderProfit -= Order::GetTotalFromOrder($this->dbCmd, $orderID, "customerdiscount");
		$orderProfit -= Order::GetTotalFromOrder($this->dbCmd, $orderID, "vendorsubtotal");
		
		
		// Postive Customer Adjustments means more profit.
		// Negative Vendor Adjustments means more profit.
		if($this->incBalanceAdjustmentsFlag){
			$orderProfit += BalanceAdjustment::getCustomerAdjustmentsTotalFromOrder($this->dbCmd, $orderID);
			$orderProfit -= BalanceAdjustment::getVendorAdjustmentsTotalFromOrder($this->dbCmd, $orderID);
		}
		
		// Do not include profit from S&H unless the order has been shipped.
		// We will skew number for profit if we don't have freight charges yet.
		// It is better to just omit S&H profit from uncompleted orders because profit should be relatively small... and orders get completed relatively soon.
		if($this->incShippingAndHandling){
			
			$freightChargesFromOrder = Shipment::GetFreightChargesFromOrderIfAllShipmentsValid($this->dbCmd, $orderID);
			
			if(!empty($freightChargesFromOrder)){
				$orderProfit += Order::GetCustomerShippingQuote($this->dbCmd, $orderID);
				$orderProfit -= $freightChargesFromOrder;
			}
		}
		
		return $orderProfit;
	}
	
	private function getDiscountFromOrderID($orderID){
		
		$orderID = intval($orderID);
		
		// No profit exists from an empty order.
		if(!Order::CheckForActiveProjectWithinOrder($this->dbCmd, $orderID))
			return 0;
		
		return Order::GetTotalFromOrder($this->dbCmd, $orderID, "customerdiscount");

	}
	
	private function getRevenueTotalFromOrderID($orderID){
		
		$orderID = intval($orderID);
		
		// No Revenue exists from an empty order.
		if(!Order::CheckForActiveProjectWithinOrder($this->dbCmd, $orderID))
			return 0;
		
		$totalRevenue = 0;
		
		// If we give a discount to a customer, it means less revenue.
		$totalRevenue += Order::GetTotalFromOrder($this->dbCmd, $orderID, "customersubtotal");
		$totalRevenue -= Order::GetTotalFromOrder($this->dbCmd, $orderID, "customerdiscount");
		
		// Postive Customer Adjustments means more profit.
		// Don't include Vendor adjustments with Revenue because that only affects profit.
		if($this->incBalanceAdjustmentsFlag){
			$totalRevenue += BalanceAdjustment::getCustomerAdjustmentsTotalFromOrder($this->dbCmd, $orderID);
		}
		
		if($this->incShippingAndHandling){
			$totalRevenue += Order::GetCustomerShippingQuote($this->dbCmd, $orderID);
		}
		
		return $totalRevenue;
	}
	
	function getOrderIDsOfFirstOrders(){
		
		// In case any parameters have changed, we need to re-run the query.
		if(!$this->userIDsFetchedFlag)
			$this->setCustomerIDs();
			
		if(!empty($this->orderIDsFirstTimeArr_Cache))
			return $this->orderIDsFirstTimeArr_Cache ;
			
		$userIDsArr = $this->getUserIDsFromAcquisitionTimeSpan();
		
		$orderIDarr = array();
		
		foreach($userIDsArr as $thisUserID){
			
			$this->dbCmd->Query("SELECT ID FROM orders WHERE UserID=$thisUserID ORDER BY ID ASC LIMIT 1");
			$orderIDarr[] = $this->dbCmd->GetValue();
			
		}
		
		$this->orderIDsFirstTimeArr_Cache = $orderIDarr;
		
		return $orderIDarr;
	}
	
	function getOrderIDsUntilEndPeriod(){
		
		// In case any parameters have changed, we need to re-run the query.
		if(!$this->userIDsFetchedFlag)
			$this->setCustomerIDs();
			
		if(!empty($this->orderIDsUntilEndPeriodArr_Cache))
			return $this->orderIDsUntilEndPeriodArr_Cache;
			
		$userIDsArr = $this->getUserIDsFromAcquisitionTimeSpan();
		
		$orderIDarr = array();
		
		foreach($userIDsArr as $thisUserID){
			
			$ordersForThisUserArr = array();
			
			if(!empty($this->endPeriodDate)){
				$this->dbCmd->Query("SELECT orders.ID FROM orders USE INDEX(orders_UserID) 
									WHERE UserID=$thisUserID AND DateOrdered 
									BETWEEN  '" . DbCmd::FormatDBDateTime($this->acqSpanStart) . "' AND '" . DbCmd::FormatDBDateTime($this->endPeriodDate) . "'");
				$ordersForThisUserArr = $this->dbCmd->GetValueArr();
			}
			else{
				
				// We have to figure out the end date based upon the customer's first date and end our "end period days".
				$this->dbCmd->Query("SELECT UNIX_TIMESTAMP(DateOrdered) as DateOrdered FROM orders USE INDEX(orders_UserID) 
									WHERE UserID=$thisUserID ORDER BY ID ASC LIMIT 1");
				$customerAcqDate = $this->dbCmd->GetValue() - 10; // Take off a few seconds just to make sure that the BETWEEN SQL clause below picks up our first order.
				
				$endDateForCustomer = $customerAcqDate + (60 * 60 * 24 * $this->endPeriodDaysFromAcqDate);
				 
				$this->dbCmd->Query("SELECT orders.ID FROM orders USE INDEX(orders_UserID) 
									WHERE UserID=$thisUserID AND 
									DateOrdered BETWEEN  '" . DbCmd::FormatDBDateTime($customerAcqDate) . "' AND '" . DbCmd::FormatDBDateTime($endDateForCustomer) . "'");
				$ordersForThisUserArr = $this->dbCmd->GetValueArr();
			}
			
			$orderIDarr = array_merge($orderIDarr, $ordersForThisUserArr);
			
		}
		
		$this->orderIDsUntilEndPeriodArr_Cache = $orderIDarr;
		
		return $orderIDarr;
	}
	
	private function setCustomerIDs(){
		
		if(empty($this->acqSpanStart) || empty($this->acqSpanEnd))
			throw new Exception("The acuistion timespan must be set before calling this method.");
			
		if(empty($this->endPeriodDaysFromAcqDate) && empty($this->endPeriodDate))
			throw new Exception("You must set the End Period Date or End Period Days before calling this method. : $this->endPeriodDaysFromAcqDate : $this->endPeriodDate");
			
		if(!empty($this->endPeriodDaysFromAcqDate) && !empty($this->endPeriodDate))
			throw new Exception("You can not have an end Period Date and a End Period Days (from acquisition) date set at the same time.  It has to be one or the other.");

		
		if(!empty($this->endPeriodDate)){
			if($this->endPeriodDate < $this->acqSpanEnd)
				throw new Exception("The End Period Date can not happen before the End Acquistion Date.");
		}
		
		// Wipe out order ID cache since the cusotmer IDs are going to change.
		$this->orderIDsFirstTimeArr_Cache = array();
		$this->orderIDsUntilEndPeriodArr_Cache = array();
		
			
		$query = "SELECT DISTINCT orders.UserID FROM orders USE INDEX (orders_DateOrdered) ";
		$query .= " INNER JOIN projectsordered ON projectsordered.OrderID = orders.ID ";
		$query .= " WHERE DateOrdered BETWEEN '" . DbCmd::FormatDBDateTime($this->acqSpanStart) . "' AND '" . DbCmd::FormatDBDateTime($this->acqSpanEnd) . "' ";
		$query .= " AND FirstTimeCustomer = 'Y' ";
		$query .= " AND " . DbHelper::getOrClauseFromArray("orders.DomainID", $this->domainIDsArr);
		
		if(!empty($this->artworkKeywords))
			$query .= " AND ArtworkFile LIKE \"%".DbCmd::EscapeLikeQuery($this->artworkKeywords)."%\" ";

		if(!empty($this->productIDsArr))
			$query .= " AND " . DbHelper::getOrClauseFromArray("projectsordered.ProductID", $this->productIDsArr);
			
		if(!empty($this->couponIDarr))
			$query .= " AND " . DbHelper::getOrClauseFromArray("CouponID", $this->couponIDarr);
		
		if(!empty($this->trackingCode)){
			
			// Replace astrisks with wildcards.
			$trackCodeSQL = DbCmd::EscapeLikeQuery($this->trackingCode);
			$trackCodeSQL = preg_replace("/\\*/", "%", $trackCodeSQL);
			
			$query .= " AND orders.Referral LIKE \"".$trackCodeSQL."\" ";
		}
		
		if(!empty($this->companyOrName)){
			
			// Replace astrisks with wildcards.
			$cmnyOrName = DbCmd::EscapeLikeQuery($this->companyOrName);
			$cmnyOrName = preg_replace("/\\*/", "%", $cmnyOrName);
			
			$query .= " AND (BillingCompany LIKE \"".$cmnyOrName."\" OR BillingName LIKE \"".$cmnyOrName."\") ";
		}
		
		
	
		$this->dbCmd->Query($query);
		
		$this->customerIDsArr = $this->dbCmd->GetValueArr();
		
		$this->userIDsFetchedFlag = true;

	}
	

	
	
	
}


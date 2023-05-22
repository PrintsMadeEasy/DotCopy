<?


class SalesCommissions {

	private $_dbCmd;

	
	// These private fields should match the columns in the DB
	private $_userID;
	
	private $_startTimeStamp;
	private $_endTimeStamp;
	private $_maxProjectSubTotal;
	private $_domainID;


	###-- Constructor --###
	function SalesCommissions(DbCmd $dbCmd, $domainID){

		if(!Domain::checkIfDomainIDexists($domainID))
			throw new Exception("Error with Domain in SalesCommissionPayments");
			
		$this->_domainID = $domainID;
			
		$this->_dbCmd = $dbCmd;
		$this->_userID = -1; // Negative 1 means the UserID has not been set.  0 Means the Sales Master
		$this->_startTimeStamp = "";
		$this->_endTimeStamp = "";
		
		
		// The Order total for the Sales Rep will not include Projects which have totals over this amount.
		// If the _maxProjectSubTotal is $100 and there are 3 projects with subtotals of $50, $180, and $120.  The Order total will appear to the Sales Rep as $250;
		$this->_maxProjectSubTotal = 200;
	}
	
	
	#-- BEGIN Static Functions --#
	
	// If there is already a Sales Rep asigned... it will not override
	// Will skip over harmlessly if the coupon is not a valid Sales Rep code
	// However.. you MUST ensure that the CouponCode and CustomerID are valid in the system before passing them to this method.
	static function PossiblyAssociateSalesRepFromCouponCode(DbCmd $dbCmd, $CouponCode, &$AuthObj){
	
		$CustomerID = $AuthObj->GetUserID();
		
		WebUtil::EnsureDigit($CustomerID);
	
		// First make sure they don't already have a sales Rep code assigned.
		$dbCmd->Query("SELECT SalesRep FROM users WHERE ID=" . $CustomerID);
		if($dbCmd->GetNumRows() == 0)
			$this->_ErrorMessage("The CustomerID is invalid in PossiblyAssociateSalesRepFromCouponCode.");
		
		// If their is already a Sales Rep assigned to the user then we can not override that.  1 Sales rep per customer
		if($dbCmd->GetValue() != 0)
			return;
	
			
		// Don't let a member in the system get linked to a Sales Rep.  They could be using a login link (placing an order over the phone).
		if(IPaccess::checkIfUserIpHasSomeAccess())
			return;
	
		
		$CouponObj = new Coupons($dbCmd);
		$CouponObj->LoadCouponByCode($CouponCode);
		
		// If the coupon is not linked to a Sales Rep then return
		if(!$CouponObj->GetSalesLink())
			return;
			
		// Make sure the customer is not yet an existing customer
		// But it is OK for a Sales Rep to be linked to themselves if they were already a customer.
		$dbCmd->Query("SELECT COUNT(*) FROM orders WHERE UserID=" . $CustomerID);
		if($CouponObj->GetSalesLink() != $CustomerID && $dbCmd->GetValue() != 0)
			return;
		

		// Make sure that a Member of (Admin Customer Service, etc) will not become a Sales Rep to someone else
		// The only exception would be if the Member is trying to associate themselves as their own sales Rep.
		if($AuthObj->CheckIfBelongsToGroup("MEMBER") && $CustomerID != $CouponObj->GetSalesLink())
			return;
		
		$domainIDofUser = UserControl::getDomainIDofUser($CustomerID);
		
		if($CouponObj->getDomainID() != $domainIDofUser){
			WebUtil::WebmasterError("Domain Conflict in PossiblyAssociateSalesRepFromCouponCode");
			throw new Exception("Domain Conflict with coupon code.");
		}
			
		// Create a new object of this class (even though we are in a Static Method)
		$SalesCommissionObj = new SalesCommissions($dbCmd, $domainIDofUser);

		$SalesCommissionObj->AsscociateSalesRepWithCustomer($CouponObj->GetSalesLink(), $CustomerID);
	}
	

	// If there is already a Sales Rep asigned... it will not override
	// You MUST ensure that the  CustomerID are valid in the system before passing them to this method.
	// Will look into the Session Data and Cookies to see if the user clicked on a link provided by a Sales Rep
	function PossiblyAssociateSalesRepFromSessionData(DbCmd $dbCmd, &$AuthObj){
	
		$CustomerID = $AuthObj->GetUserID();
	
		WebUtil::EnsureDigit($CustomerID);
		
	
		// First make sure they don't already have a sales Rep code assigned.
		$dbCmd->Query("SELECT SalesRep FROM users WHERE ID=" . $CustomerID);
		if($dbCmd->GetNumRows() == 0)
			$this->_ErrorMessage("The CustomerID is invalid in PossiblyAssociateSalesRepFromCouponCode.");
		
		// If their is already a Sales Rep assigned to the user then we can not override that.  1 Sales rep per customer
		if($dbCmd->GetValue() != 0)
			return;
			
		
		// Don't let a member in the system get linked to a Sales Rep.  They could be using a login link (placing an order over the phone).
		if(IPaccess::checkIfUserIpHasSomeAccess())
			return;
	
	
		// First try to get out of the session, then try to get from a cookie if that fails.
		$SalesUserID = WebUtil::GetSessionVar("SalesRepAssociateSession", WebUtil::GetCookie("SalesRepAssociateCookie"));
		$SalesUserID = intval($SalesUserID);
		if(empty($SalesUserID))
			return;
		
		
		// Make sure the customer is not yet an existing customer
		// But it is OK for a Sales Rep to be linked to themselves if they were already a customer.
		$dbCmd->Query("SELECT COUNT(*) FROM orders WHERE UserID=" . $CustomerID);
		if($SalesUserID != $CustomerID && $dbCmd->GetValue() != 0)
			return;

		
		// Make sure that a Member of (Admin Customer Service, etc) will not become a Sales Rep to someone else
		// The only exception would be if the Member is trying to associate themselves as their own sales Rep.
		if($AuthObj->CheckIfBelongsToGroup("MEMBER") && $CustomerID != $SalesUserID)
			return;
			
		// Figure out if they already have a banner tracking cookie set from the Main Website.  
		// If so we don't want to overrride it.  This would mean that the parent company pays Google for a click...
		// ... then that customer is about the checkout and they see a coupon code box... so they type in "example.com coupon"
		// Then maybe we expire the coupon for this illegimate use... but they could still get linked to the sales rep just be visiting their page.
		$possibleMainCompanyReferral = WebUtil::GetSessionVar("ReferralSession", WebUtil::GetCookie("ReferralCookie"));
		
		// We start all of our "Email referals with "em-" ... lik "em-ReOrder-Y-Lnk-6mnthto1yr"
		// It is OK for a Sales Rep to get credit if we send them a reminder for a "dead account"
		// I am exluding User 34836 (Bret Piere) because he is using the CPC system a lot and is getting affected by customer clicking on the "PrintsMadeEasy" google ad instead of typing in the URL.
		if(!empty($possibleMainCompanyReferral) && !preg_match("/^em\-/", $possibleMainCompanyReferral) && $SalesUserID != 34836)
			return;
			

		$domainIDofUser = UserControl::getDomainIDofUser($CustomerID);
		
		// Create a new object of this class (even though we are in a Static Method)
		$SalesCommissionObj = new SalesCommissions($dbCmd, $domainIDofUser);
		$SalesCommissionObj->AsscociateSalesRepWithCustomer($SalesUserID, $CustomerID);

	}
	

	
	// Should be run whenever and order is completed... If the order belongs to a customer who has a sales rep, then it will distribute the Sales Commissions accordingly
	// Can only be run 1 time per order.  Calling it a second time will result with an error.
	static function PossiblyGiveSalesCommissionsForOrder(DbCmd $dbCmd, $OrderID){
		
		$domainIDofOrder = Order::getDomainIDFromOrder($OrderID);
		
		// Create a new object of this class (even though we are in a Static Method)
		$SalesCommissionObj = new SalesCommissions($dbCmd, $domainIDofOrder);
		$SalesCommissionObj->CreateSalesRepRecordsFromOrder($OrderID);
	}
	
	

	
	#-- BEGIN  Public Methods -------------#


	// Pass in a UserID of 0 to be the Sales Master
	function SetUser($UserID){
	
		if($UserID != 0 && !$this->CheckIfSalesRep($UserID))
			$this->_ErrorMessage("The Method SetUser was called on a User that is not a Sales Rep.");
			
			
		if(!empty($UserID)){
			$domainIDofUser = UserControl::getDomainIDofUser($UserID);
			
			if($domainIDofUser != $this->_domainID){
				WebUtil::WebmasterError("Domain conflict in SalesCommissions set User");
				$this->_ErrorMessage("Domain conflict with Sales::SetUser");
			}
		}
	
		$this->_userID = $UserID;
	}
	function SetDateRange($StartY, $StartM, $StartD, $EndY, $EndM, $EndD){
		$this->_startTimeStamp = date("YmdHis", mktime(0, 0, 0, $StartM, $StartD, $StartY));
		$this->_endTimeStamp = date("YmdHis", mktime(0, 0, 0, $EndM, ($EndD + 1), $EndY));
	}
	function SetDateRangeByTimeStamp($startUnixTimeStamp, $endUnixTimeStamp){
		$this->_startTimeStamp = date("YmdHis", $startUnixTimeStamp);
		$this->_endTimeStamp = date("YmdHis", $endUnixTimeStamp);
	}
	function SetDateRangeForAll(){
		$this->_startTimeStamp = date("YmdHis", mktime(0, 0, 0, 1, 1, 2000));
		$this->_endTimeStamp = date("YmdHis", mktime(0, 0, 0, 1, 1, 2020));
	}
	
	// Use this as an alternative to the method SetDateRange
	// Pass in the value of "ALL" for month if you want to set the date range over the entire year.
	// If month is not "ALL" then both the year and month need to be integers
	function SetMonthYear($Month, $Year){
	
		WebUtil::EnsureDigit($Year);
	
		if($Month == "ALL"){
			$this->_startTimeStamp = date("YmdHis", mktime(0, 0, 0, 0, 0, $Year));
			$this->_endTimeStamp = date("YmdHis", mktime(0, 0, 0, 0, 0, ($Year + 1)));
		}
		else{
			WebUtil::EnsureDigit($Month);
			if($Month < 1 || $Month > 12)
				throw new Exception("Month value is out of range");
				
			$this->_startTimeStamp = date("YmdHis", mktime(0, 0, 0, $Month, 1, $Year));
			$this->_endTimeStamp = date("YmdHis", mktime(0, 0, 0, ($Month + 1), 1, $Year));
		}
		
		

		

	}
	
	// The method SetUser must be called before calling this method.  The user that is previously set is the user that the "Commissions Earned" will be returned for.
	// For example... you can find out how much commission a user earned off of one of his sub-reps... that will probably be less than what the sub-rep earned himself
	// Set the SubRepID to the same User ID that was set in SetUser to get all commissions owned for that person
	// This Method Returns a dollar amount
	// Throws an error if the $SubRepID is not a SubRep of the UserID or the $SubRep does not match the UserID
	// If the UserID of 0 (or the Sales Master) has been set before calling this method... then it will always return the commissions that the "SubRepID" made personally.
	// If the SubRepID is also 0, then it will get the total amount of commissions paid out within the period.
	// All of the parameters above may be used in combination with the $view parameter... which can be "SubReps", "All", or "OwnedOrders" ... basically did the commissions come from a sub rep... or from direct customers... or from both
	// $CommissionStatus can be Good, Suspended, GoodAndSuspended, or Expired .... A Good status may or may not mean that a payment has been made.
	// Pass in a coupon ID if you want the commissions to be limited to any customers that were linked into the Sales Rep by that particular coupon... even if the coupon was first used in a different period.
	function GetTotalCommissionsWithinPeriodForUser($SubRepID, $view, $CommissionStatus, $CouponID = null){
	
		$this->_EnsureDateRangeHasBeenSet();
		$this->_EnsureThatUserHasBeenSet();
		
		if(!$this->CheckIfSalesRep($SubRepID) && $SubRepID != 0)
			$this->_ErrorMessage("The Given SubRepID does not exist.");
		
		if(!empty($SubRepID)){
			$domainIDofSubRep = UserControl::getDomainIDofUser($SubRepID);
			
			if($domainIDofSubRep != $this->_domainID)
				$this->_ErrorMessage("Domain conflict in GetTotalCommissionsWithinPeriodForUser");

		}
			
		$CommissionEarnedForUserID = $this->_userID;
		if($this->_userID == 0)
			$CommissionEarnedForUserID = $SubRepID;
					
		
		$query = " FROM salescommissions INNER JOIN orders ON salescommissions.OrderID = orders.ID WHERE ";
		
		// If the SubRepID is the Sales Master, then we want to show all commissions earned... otherwise, limit the commission payments earned to a particular user.
		if($SubRepID != 0)
			$query .= "salescommissions.UserID = $CommissionEarnedForUserID  AND ";
		
		if($CommissionStatus == "Good")
			$query .= "CommissionStatus='G' ";
		else if($CommissionStatus == "Suspended")
			$query .= "CommissionStatus='S' ";
		else if($CommissionStatus == "GoodAndSuspended")
			$query .= "(CommissionStatus='S' OR CommissionStatus='G') ";
		else if($CommissionStatus == "Expired")
			$query .= "CommissionStatus='E' ";
		else
			$this->_ErrorMessage("Illegal CommissionStatus in method GetTotalCommissionsWithinPeriodForUser");
		
		
		$query .= "AND orders.DomainID= " . $this->_domainID . " AND ";
		
		
		// If we have a coupon ID to limit the commissions too.
		if($CouponID){
			
			$CouponID = intval($CouponID);
			
			$this->_dbCmd->Query("SELECT DomainID FROM coupons WHERE ID=$CouponID");
			if($this->_dbCmd->GetValue() != $this->_domainID)
				$this->_ErrorMessage("Domain Conflict with coupon ID.");
			
			// First gather a list of all customers that have used this coupon.
			$UserIDarr = array();
			$this->_dbCmd->Query("SELECT DISTINCT UserID FROM orders WHERE CouponID=$CouponID");
			while($uid = $this->_dbCmd->GetValue())
				$UserIDarr[] = $uid;
			
			// Now loop through all of the user ID's and find any Order ID's from the user within the date range that we want the report for
			$OrdIDarr = array();
			foreach($UserIDarr as $uid){
				$this->_dbCmd->Query("SELECT ID FROM orders WHERE UserID=$uid AND DomainID=$this->_domainID AND DateOrdered BETWEEN " . $this->_startTimeStamp . " AND " . $this->_endTimeStamp);
				while($oid = $this->_dbCmd->GetValue())
					$OrdIDarr[] = $oid;
			}
			
			if(!empty($OrdIDarr)){
				$OrderIDorClause = "";
				foreach($OrdIDarr as $oid)
					$OrderIDorClause .= "OrderID=" . $oid . " OR ";
				
				$query .=  "(" . substr($OrderIDorClause, 0, -3) . ") AND ";
			}
			else{
				// Since there are no sales commissions in this period resulting from the given coupon being used, make the query something ridiculous to make sure no commissions are found.
				$query .= "OrderID=9999999999 AND ";
			}
		}
		
		
		if($view == "SubReps" || $view == "All"){
		
			$SubRepIDsArr = $this->GetSubReps($SubRepID, false);
		
			
			// Create a (possibly Giant) OR clause for the SQL query that limits to our SalesRep List
			// It includes the entire list of Sales Reps that branch out from underneath the given SubRepID
			$OrClause = "";
			foreach($SubRepIDsArr as $thisRepID)
				$OrClause .= "BaseSalesRep=" . $thisRepID . " OR ";
				
			if($view == "All")
				$OrClause .= "BaseSalesRep=" . $SubRepID . " OR ";

			$NoSubReps = false;
			if(empty($OrClause))
				$NoSubReps = true;	
			else
				$query .=  "(" . substr($OrClause, 0, -3) . ") AND ";
			
			// If we are only interested in totals From The SubReps... and there are no SubReps, then the total should be 0.
			// Set the Where clause to something ridiculous to ensure there are no matches.
			if($view == "SubReps" && $NoSubReps)
				$query .= " BaseSalesRep=99999999 AND ";
					
		}
		else if($view == "OwnedOrders"){
			$query .= "BaseSalesRep=" . $SubRepID . " AND ";
		}
		else
			$this->_ErrorMessage("Illegal view in method GetTotalCommissionsWithinPeriodForUser");
			
		
		$query .= "OrderCompletedDate BETWEEN " . $this->_startTimeStamp . " AND " . $this->_endTimeStamp;
		

		$this->_dbCmd->Query("SELECT SUM(CommissionEarned) " . $query);
		if($this->_dbCmd->GetNumRows() == 0)
			return $this->_FormatPrice(0);
		else
			return $this->_FormatPrice($this->_dbCmd->GetValue());
	
	}
	
	
	// Returns the number of order from the Base Sales Rep that are "good" or "suspended", not expired
	// Do not need to have a UserID set before calling this method, it has no effect.
	// The the SalesRepID is 0, and $IncludeSubRepsOrders = false then this method will always return 0
	// ... if $IncludeSubRepsOrders = true then this method will return the number of orders within the date range for all Sales Reps.
	// If a CouponID is provided, then it will gather the number of orders within the period that used the given CouponID
	function GetNumOrdersFromSalesRep($SalesRepID, $IncludeSubRepsOrders = false, $CouponID = null){
		$this->_EnsureDateRangeHasBeenSet();
		$this->_EnsureUserIDisSalesRep($SalesRepID);
			
		$query = "SELECT DISTINCT OrderID FROM salescommissions AS SC INNER JOIN orders ON SC.OrderID = orders.ID WHERE ";
		
		if($IncludeSubRepsOrders){
		
			$SubRepIDsArr = $this->GetSubReps($SalesRepID, false);

			$OrClause = "";
			foreach($SubRepIDsArr as $thisRepID)
				$OrClause .= "SC.BaseSalesRep=" . $thisRepID . " OR ";
				
			$OrClause .= "SC.BaseSalesRep=" . $SalesRepID;

			$query .=  "(" . $OrClause . ") AND ";			
		}
		else
			$query .= "SC.BaseSalesRep=" . $SalesRepID . " AND ";
			
		$query .= "(CommissionStatus='S' OR CommissionStatus='G') AND ";
		$query .= "SC.OrderCompletedDate BETWEEN " . $this->_startTimeStamp . " AND " . $this->_endTimeStamp;
		
		$query .= " AND orders.DomainID=" . $this->_domainID;
		
		if($CouponID)
			$query .= " AND orders.CouponID= $CouponID";
			
		$query .= " AND orders.DomainID= $this->_domainID";
		
		$this->_dbCmd->Query($query);

		return $this->_dbCmd->GetNumRows();
	}
	
	
	// Returns an array that goes from the Given SalesRepID back to the user that has been set within the method SetUser
	// The first element in the array is the SalesRepID passed into the method, the last element is the UserID that is set, or 0 which is root.
	function GetChainFromSubRepToUser($SalesRepID){
	
		$this->_EnsureThatUserHasBeenSet();
		$this->_EnsureUserIDisSalesRep($SalesRepID);
		
		$retArr = array();
		$retArr[] = $SalesRepID;
		
		$CurrentUserID = $SalesRepID;
		while($CurrentUserID != 0 && $CurrentUserID != $this->_userID){
		
			$this->_dbCmd->Query("SELECT ParentSalesRep FROM salesreps 
								INNER JOIN users ON salesreps.UserID = users.ID 
								WHERE UserID=$CurrentUserID AND users.DomainID=$this->_domainID");
			$CurrentUserID = $this->_dbCmd->GetValue();
			$retArr[] = $CurrentUserID;
		}
		
		if(!in_array($this->_userID, $retArr))
			$this->_ErrorMessage("The Given SalesRepID does not belong to the user that was set.");
			
		return $retArr;
	}
	
	// The UserID must be set before calling this method.
	// This method will return the HTML with Javascript Links for changing the Sales Rep ID.
	// It will start at the Current UserID that is set... and to to the right with every Sub Rep separated by an arrow
	function BuildSalesChainLinks($SalesRepID){
	
		$SalesChainArr = array_reverse($this->GetChainFromSubRepToUser($SalesRepID));
		
		$retHTML = "";
		
		// Don't show any Sales chain link if we are not at a sub-level
		if(sizeof($SalesChainArr) == 1)
			return "&nbsp;";
		
		for($i=0; $i<sizeof($SalesChainArr); $i++){
		
			if($SalesChainArr[$i] == 0)
				$PersonsName = "Root";
			else{
				$this->_dbCmd->Query("SELECT Name FROM users WHERE ID=" . $SalesChainArr[$i]);
				$PersonsName = $this->_dbCmd->GetValue();
			}
			
			// Don't Show a Hyperlink on the last node in the list.
			if($i+1 == sizeof($SalesChainArr))
				$retHTML .= WebUtil::htmlOutput($PersonsName);
			else
				$retHTML .= "<a class='BlueRedLink' href='javascript:ChangeSalesRepID(".$SalesChainArr[$i].")'><nobr>" . WebUtil::htmlOutput($PersonsName) . "</nobr></a>";
		
			// Show an Arrow if there are more Sales Reps in the list comming.
			if($i+1 < sizeof($SalesChainArr))
				$retHTML .= " &gt; ";
			
		}
		
		return $retHTML;
	}
	
	// Returns an array of Sub reps that belong to the given user.
	// If a UserID of 0 (the root) has been given... then calling this method will return all Sales Reps at the highest level.
	// Only returns the first level of sales reps by default... If, false it will return all Sales reps within the tree branching out from the user.
	function GetSubReps($UserID, $FirstLevel = true){
		
		$this->_EnsureUserIDisSalesRep($UserID);
		
		$retArr = array();
		
		// You need to limit to the Domain ID... because there will be 1 Sales Master (user ID 0) for each domain.
		$this->_dbCmd->Query("SELECT UserID FROM salesreps 
								INNER JOIN users ON salesreps.UserID = users.ID 
								WHERE ParentSalesRep=$UserID AND users.DomainID=$this->_domainID");
		while($thisSubRep = $this->_dbCmd->GetValue())
			$retArr[] = $thisSubRep;
			
		// Recursively Merge all of the Sub-reps throughout the tree into our return array;
		if(!$FirstLevel)
			foreach($retArr as $thisRepID)
				$retArr = array_merge($retArr, $this->GetSubReps($thisRepID, false));

		return $retArr;
	}
	
	// Tells you if the SalesRepID is a Sub Rep of the user currently set.
	// You belong to yourself too.
	function CheckIfSalesRepBelongsToUser($SalesRepID){

		$this->_EnsureThatUserHasBeenSet();
		$this->_EnsureUserIDisSalesRep($SalesRepID);
		
		if($SalesRepID == $this->_userID)
			return true;
			
		$SubRepsArr = $this->GetSubReps($this->_userID, false);
		
		if(in_array($SalesRepID, $SubRepsArr))
			return true;
		else
			return false;
	}
	
	function CheckIfSalesRepHasSubReps($UserID){
	
		$this->_EnsureUserIDisSalesRep($UserID);
	
		$this->_dbCmd->Query("SELECT COUNT(*) FROM salesreps 
								INNER JOIN users ON salesreps.UserID = users.ID 
								WHERE ParentSalesRep=$UserID AND users.DomainID=$this->_domainID");						
		if($this->_dbCmd->GetValue() == 0)
			return false;
		else
			return true;
	
	}
	
	// Returns an array of hashes for all orders within the period... having the given SalesRepID has the base Sales Rep.
	// The Commission Info returned is for the UserID that has previously been set.
	// The method SetDateRange and SetUser must be called before calling this method.
	// If the UserID of 0 (or the Sales Master) has been set before calling this method... then it will always return the commissions that the "SubRepID" made personally.
	// Does not return Expired Sales commissions... Just "Good" and "Suspended" entries
	function GetOrderInfoWithinPeriodForUser($SalesRepID){
	
		$this->_EnsureDateRangeHasBeenSet();
		$this->_EnsureThatUserHasBeenSet();


		$CommissionEarnedForUserID = $this->_userID;
		if($this->_userID == 0)
			$CommissionEarnedForUserID = $SalesRepID;
	
		$this->_dbCmd->Query("SELECT *, UNIX_TIMESTAMP(OrderCompletedDate) AS OrderDate FROM salescommissions 
					INNER JOIN orders ON salescommissions.OrderID = orders.ID 
					WHERE salescommissions.UserID=" . $CommissionEarnedForUserID . " AND BaseSalesRep=$SalesRepID AND (CommissionStatus='S' OR CommissionStatus='G') AND 
					orders.DomainID=$this->_domainID  AND 
					salescommissions.OrderCompletedDate BETWEEN " . $this->_startTimeStamp . " AND " . $this->_endTimeStamp);

		$retArr = array();
		
		while($row = $this->_dbCmd->GetRow()){
		
			// Get rid of the columns that aren't important
			unset($row["ID"]);
			unset($row["UserID"]);
			unset($row["BaseSalesRep"]);
		
			$retArr[] = $row;
		}
		
		return $retArr;
	
	}
	
	// Get the Order Totals for the given Sales Rep that are "good" or "suspended", not expired
	// You don't need to set the UserID before calling this method.
	// $ReportType can be "CustomerSubotals" or "VendorSubtotals"
	function GetOrderTotalsWithinPeriod($SalesRepID, $IncludeSubRepsOrdersFlag, $ReportType){
		$this->_EnsureDateRangeHasBeenSet();
		$this->_EnsureUserIDisSalesRep($SalesRepID);
		
		$query = "SELECT DISTINCT OrderID FROM salescommissions 
				INNER JOIN orders ON salescommissions.OrderID = orders.ID 
				WHERE orders.DomainID=$this->_domainID AND ";
		
		if($IncludeSubRepsOrdersFlag){
		
			$SubRepIDsArr = $this->GetSubReps($SalesRepID, false);

			$OrClause = "";
			foreach($SubRepIDsArr as $thisRepID)
				$OrClause .= "BaseSalesRep=" . $thisRepID . " OR ";
				
			$OrClause .= "BaseSalesRep=" . $SalesRepID;

			$query .=  "(" . $OrClause . ") AND ";			
		}
		else
			$query .= "BaseSalesRep=" . $SalesRepID . " AND ";
			
		$query .= "(CommissionStatus='S' OR CommissionStatus='G') AND ";
		$query .= "salescommissions.OrderCompletedDate BETWEEN " . $this->_startTimeStamp . " AND " . $this->_endTimeStamp;
		
		$this->_dbCmd->Query($query);
		
		$OrderIDarr = array();
		while($thisOrderID = $this->_dbCmd->GetValue())
			$OrderIDarr[] = $thisOrderID;
		
		// The column SubtotalAfterDiscount is a bit redundant... because it may exist for multiple Sales Reps.
		// This algorythm is a bit inefficient... but it is still better than than trying to calculate the Totals From the orders table.
		$OrderTotals = 0;
		foreach($OrderIDarr as $thisOrderID){
			
			if($ReportType == "CustomerSubotals"){
				$this->_dbCmd->Query("SELECT SubtotalAfterDiscount FROM salescommissions WHERE OrderID=$thisOrderID LIMIT 1");
				$OrderTotals += $this->_dbCmd->GetValue();
			}
			else if($ReportType == "VendorSubtotals"){
				$OrderTotals += Order::GetTotalFromOrder($this->_dbCmd, $thisOrderID, "vendorsubtotal");
			}
			else
				$this->_ErrorMessage("Illegal Report Type in the method call GetOrderTotalsWithinPeriod");
		}
		
		return $this->_FormatPrice($OrderTotals);
	}
	



	// Get the number of customers for the given Sales Rep that are "good" or "suspended", not expired
	// You don't need to set the UserID before calling this method.
	// $ReportType can be "all", "new", or "repeat"
	function GetCustomerCountsWithinPeriod($SalesRepID, $IncludeSubRepsOrdersFlag, $ReportType){
		$this->_EnsureDateRangeHasBeenSet();
		$this->_EnsureUserIDisSalesRep($SalesRepID);
		
		$query = "SELECT DISTINCT OrderID FROM salescommissions 
				INNER JOIN orders ON salescommissions.OrderID = orders.ID 
				WHERE orders.DomainID=$this->_domainID AND ";
		
		if($IncludeSubRepsOrdersFlag){
		
			$SubRepIDsArr = $this->GetSubReps($SalesRepID, false);

			$BaseRepOrClause = "";
			foreach($SubRepIDsArr as $thisRepID)
				$BaseRepOrClause .= "BaseSalesRep=" . $thisRepID . " OR ";
				
			$BaseRepOrClause .= "BaseSalesRep=" . $SalesRepID;

			$BaseRepOrClause .=  "(" . $BaseRepOrClause . ") AND ";			
		}
		else
			$BaseRepOrClause = "BaseSalesRep=" . $SalesRepID . " AND ";
		
		$query .= $BaseRepOrClause;
		$query .= "(CommissionStatus='S' OR CommissionStatus='G') AND ";
		$query .= "salescommissions.OrderCompletedDate BETWEEN " . $this->_startTimeStamp . " AND " . $this->_endTimeStamp;
		
		$this->_dbCmd->Query($query);
		
		$OrderIDarr = array();
		while($thisOrderID = $this->_dbCmd->GetValue())
			$OrderIDarr[] = $thisOrderID;
			
		
		// Now we need to build a unique list of Customer ID's from the list of order IDs
		$customerIDarr = array();
		foreach($OrderIDarr as $thisOrderID){
			$this->_dbCmd->Query("SELECT UserID FROM orders WHERE ID=$thisOrderID");
			$customerIDarr[] = $this->_dbCmd->GetValue();
		}
		
		$customerIDarr = array_unique($customerIDarr);

			
		if($ReportType == "all"){
			return sizeof($customerIDarr);
		}
		else if($ReportType == "new" || $ReportType == "repeat"){
		
			// Let's start out by getting the number of new customers
			// the number of repeat customers can be figured out by taking the difference of that to the total number of customers
			$newCustomerCount = 0;
			
			foreach($customerIDarr as $thisCustomerID){
				$this->_dbCmd->Query("SELECT COUNT(*) FROM salescommissions AS SalesCm 
							INNER JOIN orders ON orders.ID = SalesCm.OrderID 
							WHERE orders.UserID=$thisCustomerID AND SalesCm.OrderCompletedDate < " . $this->_endTimeStamp . "
							AND BaseSalesRep = SalesCm.UserID");
			
				if($this->_dbCmd->GetValue() == 1)
					$newCustomerCount++;
			}
			
			if($ReportType == "new")
				return $newCustomerCount;
			else if($ReportType == "repeat")
				return sizeof($customerIDarr) - $newCustomerCount;
			else 
				throw new Exception("Illegal report type specified in method GetCustomerCountsWithinPeriod");
			
		}
		else{
			$this->_ErrorMessage("Illegal Report Type in the method call GetCustomerCountsWithinPeriod");
			exit;
		}


	}
	


	
	// Returns the Percentages for each Sales Rep and goes back all the way to the root.
	// For Example: If SalesRep A has SalesRep B as a Sub-Reb and B has SalesRep C as a Sub-Rep  ... 
	// ... and A gets 12% but they give 10% of that to B ... and B gives 7% to C
	//   ... then this method will return a 7% for SalesRep C, 3% for SalesRep B, and 2% for SalesRep A
	// All percentages throughout the list will always add up to the percentage set by the Sales Rep at the root.
	// SetUser must be called before calling this method.   The Bottom most SalesRep within the heirarchy will be the first element in the array
	// returns a Hash... the key to the hash is the UserID and the Value is the percent
	function GetCurrentPercentageHeirarchy($SalesRepID){
	
		$SalesRepChainArr = $this->GetChainFromSubRepToUser($SalesRepID);
		
		$percentageSum = 0;
		
		$RetHash = array();
		
		foreach($SalesRepChainArr as $thisSalesRepID){
		
			// We don't want to include the Root User ID
			if($thisSalesRepID == 0)
				continue;
		
			$this->_dbCmd->Query("SELECT CommissionPercent FROM salesreps WHERE UserID=$thisSalesRepID");
			$thisRepCommission = $this->_dbCmd->GetValue();
			
			$RetHash["$thisSalesRepID"] = ($thisRepCommission - $percentageSum);
			
			$percentageSum += ($thisRepCommission - $percentageSum);			
		}
		
		return $RetHash;
	}
	
	// Does almost the same thing as GetCurrentPercentageHeirarchy ... except it gets the percentage rates that were locked in.
	// When a Sales Rep aquires a customer... that commission rate that they aquire the customer for does not change ever... even if their commission rate is increased or decreased.
	// All of the Parent Sales Reps also have their rates locked in at the time the customer was aquired
	// Throws an error if the CustomerID does not have a Sales Rep
	function GetLockedPercentageHeirarchy($CustomerID){
	
		WebUtil::EnsureDigit($CustomerID);
		
		$this->_dbCmd->Query("SELECT SalesUserID, CommissionPercent FROM salesrepsrateslocked WHERE CustomerUserID=$CustomerID");
		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("The CustomerID in GetLockedPercentageHeirarchy does not have a SalesRep.");
			
		$RetHash = array();
		while($row = $this->_dbCmd->GetRow())
			$RetHash[$row["SalesUserID"]] = $row["CommissionPercent"];
		
		return $RetHash;
	}
	
	// Will Throw an error if the Customer already has a Sales Rep.
	// Records the sales rep with the User and also Locks in All of the rates for Sales Reps associtated with this customer.
	function AsscociateSalesRepWithCustomer($SalesRepID, $CustomerID){
	
		$this->_EnsureUserIDisSalesRep($SalesRepID);
		
		if($this->CheckIfCustomerHasSalesRep($CustomerID))
			$this->_ErrorMessage("This customer already has a sales rep for AsscociateSalesRepWithCustomer.");
			
		$customerDomainID = UserControl::getDomainIDofUser($CustomerID);

		if($customerDomainID != $this->_domainID){
			WebUtil::WebmasterError("Error in AsscociateSalesRepWithCustomer. Domain conflict.");
			$this->_ErrorMessage("Error in AsscociateSalesRepWithCustomer. Domain conflict.");
		}
			
		$this->_dbCmd->Query("SELECT MonthsExpires FROM salesreps WHERE UserID=$SalesRepID");
		$MonthsExpires = $this->_dbCmd->GetValue();
		
		// If the commission expires after a certain period... then add the number of months to our current date.
		// Month Expires of Zero means it never expires.
		if($MonthsExpires != 0)
			$MysqlExpireTimeStamp = date("YmdHis", mktime(1, 0, 0, (date("n") + $MonthsExpires), 1, date("Y")));
		else
			$MysqlExpireTimeStamp = null;
		
		
		// Record the SalesRep entry into the users table
		$UpdateArr["SalesRep"] = $SalesRepID;
		$UpdateArr["SalesRepExpiration"] = $MysqlExpireTimeStamp;
		$this->_dbCmd->UpdateQuery("users", $UpdateArr, "ID=$CustomerID");
		
		// Temporarily Override the User That is currently set in this Object with the Root UserID, which is Zero.
		// The userID is used by other methods... It could be different than the root.
		// We want to put back whatever UserID was there when we are done gathering the Sales Rep list
		$TempUserID = $this->_userID;
		$this->_userID = 0;
		$SalesRepChainArr = $this->GetCurrentPercentageHeirarchy($SalesRepID);
		$this->_userID = $TempUserID;
		
		// Now we want to "Lock In" the rates for all of all of the Sales Reps that are associated with this Customer.
		foreach($SalesRepChainArr as $ThisSalesRepID => $ThisPercentage){
			$InsertArr["CustomerUserID"] = $CustomerID;
			$InsertArr["SalesUserID"] = $ThisSalesRepID;
			$InsertArr["CommissionPercent"] = $ThisPercentage;
			$this->_dbCmd->InsertQuery("salesrepsrateslocked", $InsertArr);
		}
	}
	
	
	// Does not need to have a UserID set
	// Will not hurt to call this method even if the customer doesn't have a Sales Rep
	// Looks up order information and records an entry for every sales rep in the chain.  It will even record Expired Entries
	// It can only be called 1 time on an order...  Calling it a second time will throw an error
	function CreateSalesRepRecordsFromOrder($OrderID){
	
		WebUtil::EnsureDigit($OrderID);
	
		if(Order::getDomainIDFromOrder($OrderID) != $this->_domainID){
			WebUtil::WebmasterError("Domain Conflict in method CreateSalesRepRecordsFromOrder");
			throw new Exception("Domain Conflict in method CreateSalesRepRecordsFromOrder");
		}
		
		// At 40% discount for an order... the sales rep will get nothing.
		// 40% is set as the CAP right now... At 20% discount the sales person would get 1/2 of their normal commission, etc.
		$DiscountPercentCap = 40;
	
		// Make sure we are not trying to call this method twice on the same order.
		$this->_dbCmd->Query("SELECT COUNT(*) FROM salescommissions WHERE OrderID=$OrderID");
		if($this->_dbCmd->GetValue() > 0)
			$this->_ErrorMessage("The method CreateSalesRepRecordsFromOrder can not be called twice for the same order.");
		
		// Find out if there is a Sales Rep Associated with the User That Placed the order.
		$this->_dbCmd->Query("SELECT UserID FROM orders WHERE ID=$OrderID");
		if($this->_dbCmd->GetNumRows() == 0)
			$this->_ErrorMessage("The order ID $OrderID does not exist in the method call CreateSalesRepRecordsFromOrder");
		$CustomerID = $this->_dbCmd->GetValue();
		
		$this->_dbCmd->Query("SELECT SalesRep, UNIX_TIMESTAMP(SalesRepExpiration) as ExpireDate FROM users WHERE ID=$CustomerID");
		$CustomerRow = $this->_dbCmd->GetRow();

		// If there is no sales rep, then don't record anything
		if($CustomerRow["SalesRep"] == 0)
			return;

		// A NULL expiration date in the DB means that it never expires.
		if(empty($CustomerRow["ExpireDate"]))
			$Expired = false;
		else{
			if($CustomerRow["ExpireDate"] < time())
				$Expired = true;
			else
				$Expired = false;
		}


		$SalesRepObj = new SalesRep($this->_dbCmd);
		$SalesRepObj->LoadSalesRep($CustomerRow["SalesRep"]);

		// If we have disabled a Sales Rep for some reason... then we do not want to record any Sales Commissions... for anyone in the chain.
		if($SalesRepObj->CheckIfAccountDisabled())
			return;		
		
		$OrderSubtotal = Order::GetTotalFromOrder($this->_dbCmd, $OrderID, "customersubtotal");
		$OrderDiscount = Order::GetTotalFromOrder($this->_dbCmd, $OrderID, "customerdiscount");
		
		
	
		
		// Find out the total of Product Options that don't apply for sales reps.
		// Also, anytime a Project Total exceed a certain Limit... take that off of the Order Total.  
		$this->_dbCmd->Query("SELECT ID FROM projectsordered WHERE OrderID=" . $OrderID);
		while($thisProjectID = $this->_dbCmd->GetValue())
			$projectIDarr[] = $thisProjectID ;

		foreach($projectIDarr as $thisProjectID){

			$projectOrderedObj = ProjectOrdered::getObjectByProjectID($this->_dbCmd, $thisProjectID);

			$projectSubtotal = $projectOrderedObj->getCustomerSubTotal() - round($projectOrderedObj->getCustomerSubTotal() * $projectOrderedObj->getCustomerDiscount(), 2);
			
			$subtractOptionsDontApply = $projectOrderedObj->getAmountOfSubtotalThatIsDiscountExempt();
			
			// Subtract the Options that aren't able to be commissioned.
			$OrderSubtotal -= $subtractOptionsDontApply;
			
			// After subtracting the options that commissions can't be earned on... find out if we are still over the MaxProject Limit... in which case don't let it exceed that amount.
			if(($projectSubtotal - $subtractOptionsDontApply) > $this->_maxProjectSubTotal)
				$OrderSubtotal -= ($projectSubtotal - $subtractOptionsDontApply) - $this->_maxProjectSubTotal;
		}

		
		// Make sure that the Discount doesn't exceed the order total.
		// If it does, then just make the discount 100%
		if($OrderDiscount > $OrderSubtotal)
			$OrderDiscount = $OrderSubtotal;
		
		
		$DiscountPercent = round($OrderDiscount / $OrderSubtotal * 100, 2);
	
		$PercentageOfNormalCommission = 0;
		if($DiscountPercent < $DiscountPercentCap)
			$PercentageOfNormalCommission = ($DiscountPercentCap - $DiscountPercent) / $DiscountPercentCap;
			
		// Get a Record of all Sales Reps and their percentages that are locked in.
		$SalesRepChainArr = $this->GetLockedPercentageHeirarchy($CustomerID);

		foreach($SalesRepChainArr as $ThisSalesRepID => $SalesRepPercentage){
		
			$SalesRepObj->LoadSalesRep($ThisSalesRepID);

			// Before we record the Sales Commission... we need to figure out if we should put their account on Payment Suspension
			// This could happen if their Address has not been verified within a certain time period... or if they have made a certain amount of money and we haven't received the W9 yet
			$SalesRepObj->PossiblySuspendPayments();
			
			
			$CommissionPercentAfterDiscount = round($SalesRepPercentage * $PercentageOfNormalCommission, 2);
			
			
			$commssionsEarned = $this->_FormatPrice($CommissionPercentAfterDiscount / 100 * ($OrderSubtotal - $OrderDiscount));
			
			// Find out if we are supposed to pay a fixed amount for new customers.
			// This is not heirarchial though.  Only apply this to the Sales Rep who is associated directly to the order.
			// A fixed amount for New Customers overrides any commission percentage.  The commission percentage would only be executed on future orders.
			$newCustomerCommissionAmnt = $SalesRepObj->getNewCustomerCommission();
			if($ThisSalesRepID == $CustomerRow["SalesRep"] && !empty($newCustomerCommissionAmnt)){
			
				// Now make sure there are no sales Rep Records for this Sales Rep on any order ID's (belonging to the Same Customer) before this order ID.
				$this->_dbCmd->Query("SELECT COUNT(*) FROM orders WHERE UserID=$CustomerID AND ID < $OrderID");
				$precedingOrderCount = $this->_dbCmd->GetValue();
				
				if($precedingOrderCount == 0)
					$commssionsEarned = $this->_FormatPrice($SalesRepObj->getNewCustomerCommission());
			}
			

			
				
			// Now figure out if we should pay this Sales Rep... The commission may have expired, or payments to their account could be suspended.
			// If it is expired then we don't need to worry about recording a Suspended status because they won't get paid anyway.
			if($Expired)
				$CommissionStatus = "E";
			else if($SalesRepObj->CheckIfPaymentsSuspended())
				$CommissionStatus = "S";
			else
				$CommissionStatus = "G";

			// Insert a Record within the Sales Commission table for every sales rep in the chain, linked to the order.
			$InsertArr["OrderID"] = $OrderID;
			$InsertArr["UserID"] = $ThisSalesRepID;
			$InsertArr["BaseSalesRep"] = $CustomerRow["SalesRep"];
			$InsertArr["OrderSubtotal"] = $OrderSubtotal;
			$InsertArr["OrderDiscount"] = $OrderDiscount;
			$InsertArr["SubtotalAfterDiscount"] = $this->_FormatPrice($OrderSubtotal - $OrderDiscount);
			$InsertArr["CommissionPercentNormal"] = $SalesRepPercentage;
			$InsertArr["CommissionPercentAfterDiscount"] = $CommissionPercentAfterDiscount;
			$InsertArr["CommissionEarned"] = $commssionsEarned;
			$InsertArr["CommissionStatus"] = $CommissionStatus;
			$InsertArr["PaymentDate"] = null;
			$InsertArr["PaymentLink"] = 0;
			$InsertArr["OrderCompletedDate"] = date("YmdHis");  
			
			$this->_dbCmd->InsertQuery("salescommissions", $InsertArr);
		}
	}
	

	function CheckIfSalesRep($UserID){
	
		WebUtil::EnsureDigit($UserID);
	
		$this->_dbCmd->Query("SELECT COUNT(*) FROM salesreps 
				INNER JOIN users ON salesreps.UserID = users.ID 
				WHERE salesreps.UserID=$UserID AND users.DomainID=$this->_domainID");
		
		if($this->_dbCmd->GetValue() == 0)
			return false;
		else
			return true;
	}

	// Returns True or false depending on whether the given customer ID has a SalesRep or not
	function CheckIfCustomerHasSalesRep($CustomerID){
	
		WebUtil::EnsureDigit($CustomerID);
	
		$this->_dbCmd->Query("SELECT SalesRep FROM users WHERE ID=$CustomerID");
		if($this->_dbCmd->GetNumRows() == 0)
			$this->_ErrorMessage("The customer ID does not exist for method call CheckIfCustomerHasSalesRep. ");
		
		// A Zero for the SalesRep means that there is no Sales Rep for this user yet.
		if($this->_dbCmd->GetValue() == 0)
			return false;
		else
			return true;
	}
	
	
	// Release All Suspended payments for the SalesPerson if their Paypments are not Suspended
	// Does not hurt to call this if the Sales Person has their Payments set to "suspended"... it won't pay them.
	// If you really want to release their payments... then take their Payments off of Suspension before calling this method.
	function ReleaseSuspendedPayments($SalesRepID){

		$this->_EnsureUserIDisSalesRep($SalesRepID);
		
		$SalesRepObj = new SalesRep($this->_dbCmd);
		$SalesRepObj->LoadSalesRep($SalesRepID);
		
		if(!$SalesRepObj->CheckIfPaymentsSuspended())
			$this->_dbCmd->Query("UPDATE salescommissions SET CommissionStatus='G' WHERE CommissionStatus='S' AND UserID=$SalesRepID");
	}
	
	
	#-- END  Public Methods -------------#

	
	




	
	
	#----- Private Methods below ------#
	

	
	function _ErrorMessage($Str){
		exit($Str);
	}
	
	function _EnsureUserIDisSalesRep($UserID){
	
		// The SalesMaster uses the UserID of 0.
		if($UserID == 0)
			return;
	
		if(!$this->CheckIfSalesRep($UserID))
			$this->_ErrorMessage("The User is not a Sales Rep." );
			
		$domainIDofUser = UserControl::getDomainIDofUser($UserID);
		
		if($domainIDofUser != $this->_domainID)
			$this->_ErrorMessage("The Sales Rep ID has a Domain Conflict." );
	}
	function _EnsureThatUserHasBeenSet(){
	
		if($this->_userID == -1)
			$this->_ErrorMessage("The UserID must be set before calling this method." );
	}
	function _EnsureDateRangeHasBeenSet(){
	
		if(empty($this->_startTimeStamp))
			$this->_ErrorMessage("The Date Range must be set before calling this method." );
	}
	
	// Returns a decimal number without any commas in the thousands place
	// Also Ensures the number is in a correct format
	function _FormatPrice($ThePrice){
	
		$FormatedPrice = number_format($ThePrice, 2, '.', '');
		
		if(!preg_match("/^((\d+\.\d+)|0)$/", $FormatedPrice))
			throw new Exception("A price format is not correct for the Sales Commission.  The amount is: " . $ThePrice);
			
		return $FormatedPrice;
	}
}



?>
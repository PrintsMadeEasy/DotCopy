<?php



class OrderQuery {

	private $_dbCmd; 
	
	private $_Vendor_Limit;
	private $_Status_Limit;
	private $_Product_LimitArr;
	private $_Shipping_Limit;
	private $_OrderStart_Marker;
	private $_OrderEnd_Marker;
	private $_EstShipDate;
	private $_EstPrintDate;
	private $_EstArrivalDate;
	private $_ProjectOptions;
	private $_PropertiesHaveBeenSet;
	private $_Priority;
	private $_RePrintFlag;
	private $_ProjectHistorySearch;
	private $_MinProjectQuantity;
	private $_MaxProjectQuantity;
	
	private $_limitToSelectedDomainIDs;
	
	private $_sessionVariableNamesArr;


	// --------------   Constructor  -----------------
	function __construct(DbCmd $dbCmd){
		$this->_dbCmd =& $dbCmd;
		$this->_PropertiesHaveBeenSet = false;
		$this->_limitToSelectedDomainIDs = true; // be default we will limit products to only those that have been selected in the user's session.
		$this->_Product_LimitArr = array();
		
		$this->_sessionVariableNamesArr = array('limit_status', 'limit_product', 'limit_shipping', 'limit_vendor', 
								'limit_startdate', 'limit_enddate', 
								'limit_estship', 'limit_estarriv', 'limit_estprint',
								'limit_options', 'limit_priority', 'limit_reprint', 'limit_history', 
								'limit_minquan', 'limit_maxquan');
	}


	// Pass in a project list (array of ProductIDs)
	// This function will return the Sum of Unit Quantities
	static function getTotalQuantityByProjectList(DbCmd $dbCmd, $projectIDarr){
	
		$totalQuantity = 0;
		
		foreach($projectIDarr as $ProjectOrderID){
			
			$dbCmd->Query("SELECT Quantity FROM projectsordered WHERE ID=" . intval($ProjectOrderID));
			if($dbCmd->GetNumRows() == 0)
				throw new Exception("Error in function call OrderQuery::getTotalQuantityByProjectList getting the quantity");
			
			$totalQuantity += $dbCmd->GetValue();
		}
		
		return $totalQuantity;
	}
	
	// By default this object limits products to only those selected.
	// Pass in FALSE if you want the order query to all domains that the user has permission to see.
	function limitToOnlySelectedDomains($boolFlag){
		
		$this->_limitToSelectedDomainIDs = $boolFlag;
	}
	
	
	// Get the total amount of open orders in the system --#
	// Pass in a Vendor ID to limit by vendor... Pass in NULL string if you dont care
	// Pass in an array of Status characters like ... "H", "P", "B", etc to count only the orders having that status.  Pass in an empty array if you dont care.
	// Pass in a Product ID of 0 if you don't care about which product it is, otherwise use a specific productID
	// ... It is possible to use multiple Product ID's as well, just pass in an array of Product IDs.
	// Pass in a pipe delimeted list of Shipping Choice Priority codes like "1|2|3"   to limit order counts to only projects belonging to order with a Shipping Choice having that priority.  Or an empty string if you don't care.
	// Pass in the timestamp of a date range for when the project was ordered.. pass in NULL string if you dont want to limit by date
	// Pass in the timestamp of a the cutoff time for when the order should have been shipped.  If the EstShipDate is BEFORE this time then we WILL count that project.  Pass in a NULL string if you dont care,
	// Project Options will do keyword matching within the ProductOptions column in the projectsordered table... Pass in an empty string if you don't care.
	function GetOrderCount($vendorID, $statusChars, $productIDs, $shippingChoicePriorities, $OrderDateStart, $OrderDateEnd, $EstArrivalDate, $EstShipDate, $EstPrintDate, $ProjectOptions, $Priority, $rePrintFlag, $projectHistSearch, $minProjectQuan, $maxProjectQuan){

		
		$productIDinputArr = $this->_getProductIDInputArr($productIDs);


		$this->_Vendor_Limit = $vendorID;
		$this->_Status_Limit = $statusChars;
		$this->_Product_LimitArr = $productIDinputArr;
		$this->_Shipping_Limit = $shippingChoicePriorities;
		$this->_OrderStart_Marker = $OrderDateStart;
		$this->_OrderEnd_Marker = $OrderDateEnd;
		$this->_EstShipDate = $EstShipDate;
		$this->_EstArrivalDate = $EstArrivalDate;
		$this->_EstPrintDate = $EstPrintDate;
		$this->_ProjectOptions = $ProjectOptions;
		$this->_Priority = $Priority;
		$this->_RePrintFlag = $rePrintFlag;
		$this->_ProjectHistorySearch = $projectHistSearch;
		$this->_MinProjectQuantity = $minProjectQuan;
		$this->_MaxProjectQuantity = $maxProjectQuan;
		
		
		
		$this->_PropertiesHaveBeenSet = true;

		// The WHERE clause may make reference to the "orders" and "projecthistory" tables... so we have to join them here
		$query = "SELECT PO.ID FROM (projectsordered AS PO ";
		$query .= " INNER JOIN orders on PO.OrderID = orders.ID) ";
		$query .= " INNER JOIN projecthistory on PO.ID = projecthistory.ProjectID ";
		$query .= " WHERE PO.ID > 0 ";

		// This method call may return a string with addition SQL commands such as... "AND VendorID1=2"
		$query .= $this->GetAdditionSqlAndClause();
		$this->_dbCmd->Query($query);
		$projectIDsArr = $this->_dbCmd->GetValueArr();
		

		return sizeof(array_unique($projectIDsArr));
	}


	// Stored the search Criteria into the session 
	function SetLimitersInSession(){
	
		$this->_EnsurePropertiesHaveBeenSet();
		
		WebUtil::SetSessionVar('limit_status', $this->_Status_Limit);
		WebUtil::SetSessionVar('limit_product', $this->_Product_LimitArr);
		WebUtil::SetSessionVar('limit_shipping', $this->_Shipping_Limit);
		WebUtil::SetSessionVar('limit_vendor', $this->_Vendor_Limit);
		WebUtil::SetSessionVar('limit_startdate', $this->_OrderStart_Marker);
		WebUtil::SetSessionVar('limit_enddate', $this->_OrderEnd_Marker);
		WebUtil::SetSessionVar('limit_estship', $this->_EstShipDate);
		WebUtil::SetSessionVar('limit_estprint', $this->_EstPrintDate);
		WebUtil::SetSessionVar('limit_estarriv', $this->_EstArrivalDate);
		WebUtil::SetSessionVar('limit_options', $this->_ProjectOptions);
		WebUtil::SetSessionVar('limit_priority', $this->_Priority);
		WebUtil::SetSessionVar('limit_reprint', $this->_RePrintFlag);
		WebUtil::SetSessionVar('limit_history', $this->_ProjectHistorySearch);
		WebUtil::SetSessionVar('limit_minquan', $this->_MinProjectQuantity);
		WebUtil::SetSessionVar('limit_maxquan', $this->_MaxProjectQuantity);
		
	}


	// Just sets the Properties of search criteria into the object
	function SetProperties($vendorID, $statusChars, $productIDs, $shippingChoicePriorities, $OrderDateStart, $OrderDateEnd, $EstArrivalDate, $EstShipDate, $EstPrintDate, $ProjectOptions, $Priority, $rePrintFlag, $projectHistSearch, $minProjectQuan, $maxProjectQuan){

		$productIDinputArr = $this->_getProductIDInputArr($productIDs);
		
		// Put parameters from this function into our Object memory
		$this->_Vendor_Limit = $vendorID;
		$this->_Status_Limit = $statusChars;
		$this->_Product_LimitArr = $productIDinputArr;
		$this->_Shipping_Limit = $shippingChoicePriorities;
		$this->_OrderStart_Marker = $OrderDateStart;
		$this->_OrderEnd_Marker = $OrderDateEnd;
		$this->_EstArrivalDate = $EstArrivalDate;
		$this->_EstShipDate = $EstShipDate;
		$this->_EstPrintDate = $EstPrintDate;
		$this->_ProjectOptions = $ProjectOptions;
		$this->_Priority = $Priority;
		$this->_RePrintFlag = $rePrintFlag;
		$this->_ProjectHistorySearch = $projectHistSearch;
		$this->_MinProjectQuantity = $minProjectQuan;
		$this->_MaxProjectQuantity = $maxProjectQuan;
		
		$this->_PropertiesHaveBeenSet = true;
	}
	
	
	
	// Based upon our properties that have been set for this class... this method will return a pipe-delimited list of ProjectID's that match the criteria
	// Pass in a Project limit to prevent too large of a list... Or pass in 0 if you don't care how many projects are in the list.
	// Pass in a maximum page limit and the quantity per page (from the PDF profile) to make sure the project list does not go over that amount of pages
	// 	QuantityPerPage is dependent upon the PDF profile... for example... 100 business cards will take less parent sheets than 100 postcards.
	//	If the max pages is set low... it will always return at least 1 project... even if that project has more pages than MaxPages allows.
	// Pass in 0 for either maxPages or quantityPerPage if you don't want to restrict the list to a particular page count.
	// The list of Project ID's returned will be sorted with the most expedited shipping methods on top... 2nd sorted by urgen, 3rd by oldes projects first..
	function GetPipeDelimitedProjectIDlist($projectCap, $maxPages, $quantityPerPage){
		
		WebUtil::EnsureDigit($projectCap);
		WebUtil::EnsureDigit($maxPages);
		WebUtil::EnsureDigit($quantityPerPage);
	
		$this->_EnsurePropertiesHaveBeenSet();
	
		

		// The WHERE clause may make reference to the "orders" and "projecthistory" tables... so we have to join them here
		$query = "SELECT DISTINCT PO.ID AS ProjID, orders.ShippingChoiceID FROM (projectsordered AS PO ";
		$query .= " INNER JOIN orders on PO.OrderID = orders.ID) ";
		$query .= " INNER JOIN projecthistory on PO.ID = projecthistory.ProjectID ";
		$query .= " WHERE PO.ID > 0 ";

		//This method call may return a string with addition SQL commands such as... "AND VendorID1=2"
		$query .= $this->GetAdditionSqlAndClause();
		
		// Priority right now is 'U'rgent and 'N'ormal... so ordering by Priority DESC means that the Urgent orders go to the top of the stack.
		$query .= " ORDER BY Quantity ASC, ShippingPriority ASC, Priority DESC, PO.ID ASC";

		$this->_dbCmd->Query($query);
		
		$projectIDArr = array();
		while($row = $this->_dbCmd->GetRow())
			$projectIDArr[] = $row["ProjID"];

		
		$projectCounter = 0;
		
		$projectArrRet = array();
		
		$totalPagesSoFar = 0;
		foreach($projectIDArr as $thisPID){
		
			$projectCounter++;
			
			if($projectCap != 0 && $projectCounter > $projectCap)
				break;
		
			$this->_dbCmd->Query("SELECT Quantity FROM projectsordered WHERE ID=$thisPID");	
			
			// Always Add 1 because we need a place for the reorder card.
			$quantity = $this->_dbCmd->GetValue() + 1;
			
			// We always print a few extra cards... since they might not all fit evenly for the quantity needed
			if($quantityPerPage != 0)
				$totalPagesSoFar += ceil($quantity/$quantityPerPage);
			
			if($totalPagesSoFar > $maxPages && $maxPages != 0){
				
				// Make sure there is at least 1 project
				if(sizeof($projectArrRet) == 0)
					$projectArrRet[] = $thisPID;
				
				break;
			}
			else{
				$projectArrRet[] = $thisPID;
			}
		
		}
		
		
		$ProjectList = "";
		foreach($projectArrRet as $pid)
			$ProjectList .= $pid . "|";
		
		return $ProjectList;	
	}
	

	// Looks into the session variables and looks limiters.. If it finds them... set into the memory of this object

	function ParseLimitersInSession(){
		

		$this->_Vendor_Limit = WebUtil::GetSessionVar('limit_vendor', "");
		$this->_OrderStart_Marker = WebUtil::GetSessionVar('limit_startdate', "");
		$this->_OrderEnd_Marker = WebUtil::GetSessionVar('limit_enddate', "");
		$this->_EstArrivalDate = WebUtil::GetSessionVar('limit_estarriv', "");
		$this->_EstShipDate = WebUtil::GetSessionVar('limit_estship', "");
		$this->_EstPrintDate = WebUtil::GetSessionVar('limit_estprint', "");
		$this->_Status_Limit = WebUtil::GetSessionVar('limit_status', "");
		$this->_Shipping_Limit = WebUtil::GetSessionVar('limit_shipping', "");
		$this->_ProjectOptions = WebUtil::GetSessionVar('limit_options', "");
		$this->_Priority = WebUtil::GetSessionVar('limit_priority', "");
		$this->_RePrintFlag = WebUtil::GetSessionVar('limit_reprint', "");
		$this->_ProjectHistorySearch = WebUtil::GetSessionVar('limit_history', "");
		$this->_MinProjectQuantity = WebUtil::GetSessionVar('limit_minquan', "");
		$this->_MaxProjectQuantity = WebUtil::GetSessionVar('limit_maxquan', "");
		
	
		// Get the Product Limit array from the session.
		$this->_Product_LimitArr = WebUtil::GetSessionVar('limit_product', "");
		
		// In case we don't have any Product Limit set... then it may be because we have just started a new session.
		// In this case... let's see if there is a cookie to remember our setting from last time.
		if(empty($this->_Product_LimitArr)){
		
			$productListLimitFromCookie = WebUtil::GetCookie("LimitProductsListCookie", "");
			
			if(!empty($productListLimitFromCookie))
				$this->_Product_LimitArr = $this->_getProductIDInputArr($productListLimitFromCookie);
			else	
				$this->_Product_LimitArr = array();
		}
		
		// Now filter only Products in the selected domain pool.		
		$productIDsInSelectedDomains = Product::getActiveProductIDsInUsersSelectedDomains();
	
		$this->_Product_LimitArr = array_intersect($productIDsInSelectedDomains, $this->_Product_LimitArr);
	}

	
	function GetAdditionSqlAndClause(){
		
		$SQL_WHERE_clause = "";
		
		// Split the characters into arrays 
		$Status_Limit_Arr = preg_split('//', $this->_Status_Limit, -1, PREG_SPLIT_NO_EMPTY);

		// Build a Nested Where clause for the available statuses
		if(sizeof($Status_Limit_Arr) > 0){
			$SQL_WHERE_clause .= " AND ( ";

			for($i=0; $i<sizeof($Status_Limit_Arr); $i++){
				if($i==0)
					$SQL_WHERE_clause .= " PO.Status='" . $Status_Limit_Arr[$i] . "' ";
				else
					$SQL_WHERE_clause .= " OR PO.Status='" . $Status_Limit_Arr[$i] . "' ";
			}
			$SQL_WHERE_clause .= " ) ";
		}

		
		if(!empty($this->_Product_LimitArr)){
			$SQL_WHERE_clause .= "AND ( ";

			foreach($this->_Product_LimitArr as $thisProdID)
					$SQL_WHERE_clause .= "PO.ProductID=" . $thisProdID . " OR ";
			
			// Get rid of the trailing "OR " from the last loop
			$SQL_WHERE_clause = substr($SQL_WHERE_clause, 0, -3);
			
			$SQL_WHERE_clause .= ") ";
		}

		
		
		if($this->_Priority)
			$SQL_WHERE_clause .= "AND PO.Priority='" . $this->_Priority . "' ";
		

		$Shipping_Limit_Arr = array();
		
		// Split the Shipping Choice Priority Codes  (which are pipe separated integers) into an array.
		// Later on we changed the shipping limit into priorities (1-5).  So if we don't find pipe symbols separating the values, then just split the string so there is one array per character.
		if(!empty($this->_Shipping_Limit)){
			
			if(preg_match("/\|/", $this->_Shipping_Limit))
				$Shipping_Limit_Arr = explode('|', $this->_Shipping_Limit);
			else
				$Shipping_Limit_Arr = preg_split('//', $this->_Shipping_Limit, -1, PREG_SPLIT_NO_EMPTY);
		}
	

		// Build a Nested Where clause for the available shipping methods
		if(sizeof($Shipping_Limit_Arr) > 0){
			$SQL_WHERE_clause .= " AND ( ";

			for($i=0; $i<sizeof($Shipping_Limit_Arr); $i++){
				if($i != 0)
					$SQL_WHERE_clause .= " OR ";
					
				$SQL_WHERE_clause .= " PO.ShippingPriority='" . $Shipping_Limit_Arr[$i] . "' ";
			}
			$SQL_WHERE_clause .= " ) ";
		}


		// Limit the query to a particular vendor if we specified in the parameters
		if(!empty($this->_Vendor_Limit))
			$SQL_WHERE_clause .= " AND (" . Product::GetVendorIDLimiterSQL($this->_Vendor_Limit) . ") ";

		// Now limit the Query based on a date ordered range
		if(!empty($this->_OrderStart_Marker) || !empty($this->_OrderEnd_Marker)){

			// If the start Date is NULL then set is to someting really small so that is doesn't interfere..  if No end date exists then set it to something really Big
			if($this->_OrderStart_Marker == "")
				$start_orderdate_timestamp = date("YmdHis", mktime (0,0,0,12,32,1997));
			else
				$start_orderdate_timestamp = date("YmdHis", $this->_OrderStart_Marker);

			if($this->_OrderEnd_Marker == "")
				$end_orderdate_timestamp = date("YmdHis", mktime (0,0,0,12,32,2030));
			else
				$end_orderdate_timestamp = date("YmdHis", $this->_OrderEnd_Marker);

			$SQL_WHERE_clause .= " AND (orders.DateOrdered BETWEEN " . $start_orderdate_timestamp . " AND " . $end_orderdate_timestamp . ") ";

		}



		// Now limit the Query based on a date ordered range
		if(!empty($this->_EstShipDate)){

			$estShipDate_timestamp = date("YmdHis", $this->_EstShipDate);

			$SQL_WHERE_clause .= " AND PO.EstShipDate <  $estShipDate_timestamp ";

		}
		
		
		// If we are limited to what orders arrived on a certain day... then make it between 12AM to 11:59PM
		if(!empty($this->_EstArrivalDate)){

			$startArrivDate_timestamp = date("YmdHis", mktime (0,0,0,date("n", $this->_EstArrivalDate),date("j", $this->_EstArrivalDate),date("Y", $this->_EstArrivalDate)));
			$endArrivDate_timestamp = date("YmdHis", mktime (23,59,59,date("n", $this->_EstArrivalDate),date("j", $this->_EstArrivalDate),date("Y", $this->_EstArrivalDate)));

			// There were some bugs with Mysql being slow on BETWEEN statments when a join is used.... not sure if the problem exists in this version.
			$SQL_WHERE_clause .= " AND PO.EstArrivalDate > $startArrivDate_timestamp AND PO.EstArrivalDate < $endArrivDate_timestamp ";
		}
		
		
		
		if(!empty($this->_EstPrintDate)){

			$estPrintDate_timestamp = date("YmdHis", $this->_EstPrintDate);

			$SQL_WHERE_clause .= " AND PO.EstPrintDate <  $estPrintDate_timestamp ";

		}
		
		
		// Find out if we are also search through Project Options
		if(!empty($this->_ProjectOptions))
			$SQL_WHERE_clause .= " AND PO.OptionsAlias like \"%". DbCmd::EscapeSQL($this->_ProjectOptions) . "%\" ";
		
		
		// RePrint flags can either be Null, Y, or N.
		if(!empty($this->_RePrintFlag)){
			
			if($this->_RePrintFlag == "Y")
				$SQL_WHERE_clause .= " AND PO.RePrintLink != 0 ";
			else if($this->_RePrintFlag == "N")
				$SQL_WHERE_clause .= " AND PO.RePrintLink = 0 ";
			else
				throw new Exception("Error in Order Report Lib: GetAdditionSqlAndClause:  If a reprint flag is set then it must either be 'Y' or 'N'");
		}
		
		
		// Find out if we are also search through Project Options
		if(!empty($this->_ProjectHistorySearch))
			$SQL_WHERE_clause .= " AND projecthistory.Note like \"%". DbCmd::EscapeSQL($this->_ProjectHistorySearch) . "%\" ";
		
		
		
		// Possibly Restrict by Quantity
		if(!empty($this->_MinProjectQuantity))
			$SQL_WHERE_clause .= " AND PO.Quantity >= " . intval($this->_MinProjectQuantity) . " ";
		if(!empty($this->_MaxProjectQuantity))
			$SQL_WHERE_clause .= " AND PO.Quantity <= " . intval($this->_MaxProjectQuantity) . " ";
		

		// Limit to the Domains that the Admin as selected.
		if($this->_limitToSelectedDomainIDs){
			$domainObj = Domain::singleton();
			$SQL_WHERE_clause .= " AND " . DbHelper::getOrClauseFromArray("PO.DomainID", $domainObj->getSelectedDomainIDs());
		}
		else{
			$passiveAuthObj = Authenticate::getPassiveAuthObject();
			$SQL_WHERE_clause .= " AND " . DbHelper::getOrClauseFromArray("PO.DomainID", $passiveAuthObj->getUserDomainsIDs());
		}
			
		return $SQL_WHERE_clause;
	}


	// Pass in a string of integers separated by pipe symbols EX "5|12|" and it will return each integer in an array
	function GetProjectArrayFromProjectList($ProjectListStr){
		
		$retArr = array();
		
		$MasterList = split("\|", $ProjectListStr);
		
		foreach($MasterList as $ProjID){
			if(preg_match("/^\d+$/", $ProjID))
				$retArr[] = $ProjID;
		}
		
		return $retArr;
	}

	



	// Returns TRUE if the order query is limited by start and end dates.
	function CheckForOrderStartEndDateLimiters(){
		if(!empty($this->_OrderStart_Marker) && !empty($this->_OrderEnd_Marker))
			return true;
		else
			return false;
	}




	// Returns true or false depending on wheter the user has any open order limiters set within their session variables
	// True means that a limiter is active
	function CheckForOpenOrderLimiter(){
		
		foreach($this->_sessionVariableNamesArr as $thisCheckValue){
			if(WebUtil::GetSessionVar($thisCheckValue))
				return true;
		}
		
		return false;
	}

	// Will clear out any session variables that have limiters set
	function ClearOpenOrderLimiters(){

		foreach($this->_sessionVariableNamesArr as $resetSessionVarName)
			WebUtil::SetSessionVar($resetSessionVarName, "");
			
	}
	
	// If no product Limit is set, then an array will be empty.
	// If you selected Products from another Domain... and then changed the "Selected Domain"... this will exclude Product IDs which are not in the selected Domain pool. 
	function getProductLimit(){
		
		$this->ParseLimitersInSession();
		

		// Make sure that an array is always returned.
		if(empty($this->_Product_LimitArr))
			$productLimitArr = array();
		else
			$productLimitArr = $this->_Product_LimitArr;
			
		return $productLimitArr;
	}
	
	// Returns an Array of Status Limiters... otherwise returns an empty array
	function getStatusLimitArr(){
		
		$this->ParseLimitersInSession();
		
		// Make sure that an array is always returned.
		if(empty($this->_Status_Limit))
			$statusArr = array();
		else
			$statusArr = preg_split('//', $this->_Status_Limit, -1, PREG_SPLIT_NO_EMPTY);
			
		return $statusArr;
	}
	
	
	// Pass in 0 or "" if you want to remove a limiter for the Product
	function setProductLimit($x){
		
		$productLimitArr = $this->_getProductIDInputArr($x);
		
		WebUtil::SetSessionVar('limit_product', $productLimitArr);
		
		// Also remember the selected product in a cookie.
		$productIDstr = implode("|", $productLimitArr);
		$DaysToRemember = 300;
		$cookieTime = time()+60*60*24 * $DaysToRemember;
		setcookie ("LimitProductsListCookie", $productIDstr, $cookieTime);	
	
	}
	
	function _EnsurePropertiesHaveBeenSet(){
	
		if(!$this->_PropertiesHaveBeenSet)
			throw new Exception("The properties have not been set yet for the class OrderReport ");
	}
	
	// Can take a single product ID as a string
	// or product IDs in an array
	// or a string of multiple products ID's separated by pipe symbols EX. 2121|3353|
	// This method will validate the input 
	// Return an array of product ID's or an emtpy array.
	function _getProductIDInputArr($productIDs){
		
		$returnArr = array();
		
		// If the product limit is a string of multiple product IDs separated by pipe symbols, then convert to an array
		if(!is_array($productIDs) && preg_match("/\|/", $productIDs)){
			$productLimiterArr = split("\|", $productIDs);
			
			foreach($productLimiterArr as $x){
				if(preg_match("/^\d+$/", $x))
					$returnArr[] = $x;
			}
		}
		else if(!is_array($productIDs)){
			// Conver the single product ID into a 1 element array
			WebUtil::EnsureDigit($productIDs, false);
			
			if(empty($productIDs))
				$returnArr = array();
			else
				$returnArr = array($productIDs);
		}
		else if(is_array($productIDs)){
			
			// Validate that all entries are numeric
			foreach($productIDs as $x){
				WebUtil::EnsureDigit($x);
				if(empty($x))
					throw new Exception("If you are passing in multipe Product IDs, then there may not be any empty elements");
				$returnArr[] = $x;
			}
		}
		
		
		return $returnArr;

	
	}
	


}

?>

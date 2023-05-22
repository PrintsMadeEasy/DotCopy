<?

require_once("library/Boot_Session.php");

// Don't make it look like we are favoring a particular Domain 
Domain::removeTopDomainID();

$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();
$dbCmd3 = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("OPEN_ORDERS"))
	WebUtil::PrintAdminError("Not Available");


$domainObj = Domain::singleton();

set_time_limit(5*60);

$navHistoryObj = new NavigationHistory($dbCmd2);

$shippingMethodsObj = new ShippingMethods(0);

$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if($action){

	if($action == "addlimiter"){
	
		//Set the limiterers within session variables
		$orderQryObj = new OrderQuery($dbCmd);

		$orderQryObj->SetProperties(	WebUtil::GetInput("l_vendor", FILTER_SANITIZE_INT), 
										WebUtil::GetInput("l_status", FILTER_SANITIZE_STRING_ONE_LINE), 
										WebUtil::GetInput("l_product", FILTER_SANITIZE_STRING_ONE_LINE), 
										WebUtil::GetInput("l_shipping", FILTER_SANITIZE_STRING_ONE_LINE), 
										WebUtil::GetInput("l_startdate", FILTER_SANITIZE_INT), 
										WebUtil::GetInput("l_enddate", FILTER_SANITIZE_INT), 
										WebUtil::GetInput("l_estarriv", FILTER_SANITIZE_INT), 
										WebUtil::GetInput("l_estship", FILTER_SANITIZE_INT), 
										WebUtil::GetInput("l_estprint", FILTER_SANITIZE_INT), 
										"", 
										WebUtil::GetInput("l_priority", FILTER_SANITIZE_STRING_ONE_LINE), 
										WebUtil::GetInput("l_reprint", FILTER_SANITIZE_STRING_ONE_LINE), 
										WebUtil::GetInput("l_history", FILTER_SANITIZE_STRING_ONE_LINE), 
										WebUtil::GetInput("l_minquan", FILTER_SANITIZE_INT), 
										WebUtil::GetInput("l_maxquan", FILTER_SANITIZE_INT));
		$orderQryObj->SetLimitersInSession();

		header("Location: ./ad_openorders.php");
		exit;
	
	}
	else if($action == "changeProductLimiter"){
	
		$domainIDsOfUser = $AuthObj->getUserDomainsIDs();
		
		//Set the limiterers within session variables
		$orderQryObj = new OrderQuery($dbCmd);
		
		$productLimit = WebUtil::GetInput("l_product", FILTER_SANITIZE_STRING_ONE_LINE);
		
		// Multi-select HTML list menu creates an array.
		$productArr = WebUtil::GetInputArr("productlimitselect", FILTER_SANITIZE_INT);
		

		if(isset($productArr)){
			foreach($productArr as $thisProdID){
				$productLimit .= "|" . $thisProdID;
				
				// If they have selected All products then the number "0" would have been selected
				// If we get this option in the URL, then it overrides any other product limitations.
				if(empty($thisProdID)){
					$productLimit = "";
					break;
				}
				

				$domainIDofProduct = Product::getDomainIDfromProductID($dbCmd, $thisProdID);
				if(!in_array($domainIDofProduct, $domainIDsOfUser))
					throw new Exception("Error setting Product ID limiter: $thisProdID : for User: $UserID");

			}
		}
		


		$orderQryObj->setProductLimit($productLimit);

		// Don't do a redirect, because we need to set cookies in the user's browser through headers.
		print "<html>\n<script>\ndocument.location='" . WebUtil::FilterURL(WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL)) . "';\n</script>\n</html>";
		exit;
	}
	else if($action == "removelimiter"){
	
		$orderQryObj = new OrderQuery($dbCmd);

		//Wipe out session variables
		$orderQryObj->ClearOpenOrderLimiters();

		header("Location: ./ad_openorders.php");
		exit;
	}
	else if($action == "completeorder"){
		
		WebUtil::checkFormSecurityCode();
	
		$projectid = WebUtil::GetInput("projectid", FILTER_SANITIZE_INT);
	
		Order::ManuallyCompleteOrder($dbCmd, $projectid, $UserID);

		header("Location: ./ad_project.php?projectorderid=" . $projectid . "&nocache=". time());
	}
	else if($action == "shippingexport"){
	
		// Update the Shipping database
		ShippingTransfers::UpdateShippingExport($dbCmd);

		header("Location: ./ad_home.php");
		exit;
	}
	else if($action == "changeResultsPerPage"){
	
		$resultPerPage = WebUtil::GetInput("resultPerPage", FILTER_SANITIZE_INT);
		
		if($resultPerPage < 50 || $resultPerPage > 1000)
			throw new Exception("Illegal results per page value");
			
		
		// Remember the setting
		WebUtil::SetSessionVar("OpenOrderMaxResults", $resultPerPage);
		$DaysToRemember = 300;
		$cookieTime = time()+60*60*24 * $DaysToRemember;
		setcookie ("OpenOrderMaxResults", $resultPerPage, $cookieTime);
	}
	else if($action == "importshipments"){
	
		// This could take a while
		set_time_limit(1200);

		$ErrorString = ShippingTransfers::GetShippingImport($dbCmd, $dbCmd3, $UserID);

		// If we get back a sting from running this function then it means there was an error
		if(!empty($ErrorString)){
			WebUtil::WebmasterError($ErrorString);
			exit($ErrorString . "<br><br><br>Return to <a href='./ad_home.php'>Admin Home</a>");
		}
		else{

			// A lot of orders have been completed.. so update the shipping export table now.
			ShippingTransfers::UpdateShippingExport($dbCmd);
			
			$returl = WebUtil::GetInput("returl", FILTER_SANITIZE_URL);

			// Redirect to the order page that they just came from
			// Instead of doing a header redirect, use a javascript redirect since our above function already started printing to the browser.
			print "<html><script>document.location='./$returl';</script></html>";
			exit;
		}

	}
	else
		throw new Exception("Undefined action.");

}


$offset = WebUtil::GetInput("offset", FILTER_SANITIZE_INT);

$t = new Templatex(".");

$t->set_file("origPage", "ad_openorders-template.html");


$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());



//Create a new order report object...
//We will use this to detect if there are any limiters on the open projects.
$orderQryObj = new OrderQuery($dbCmd);

// Check and see if the list of open orders is being limited.. If so, then show them a link to remove the restrictions
if(!$orderQryObj->CheckForOpenOrderLimiter()){
	$t->set_block("origPage","OrderLimiterBL","OrderLimiterBLout");
	$t->set_var(array("OrderLimiterBLout"=>""));
	
	$OpenOrderLimiterSQL = " AND " . DbHelper::getOrClauseFromArray("PO.DomainID", $domainObj->getSelectedDomainIDs());
	
	$t->set_var("DATE_LABEL", "Ordered On");
}
else{
	//Get an SQL WHERE clause to define the restrtictions that are set in the session
	$orderQryObj->ParseLimitersInSession();
	$OpenOrderLimiterSQL = $orderQryObj->GetAdditionSqlAndClause();
	
	$t->set_var("DATE_LABEL", "Last Status Change");
	
}




// Try and get the max results from the session var and a cookie.... then default to 50.
// Since the cookie may not be remembed until the page reloads.  Override the cookie value if we got an action.
$resultPerPage = WebUtil::GetInput("resultPerPage", FILTER_SANITIZE_INT);
if(!empty($resultPerPage))
	$NumberOfResultsToDisplay = $resultPerPage;
else
	$NumberOfResultsToDisplay = WebUtil::GetCookie("OpenOrderMaxResults", 50);

	
$t->set_var("RESULTS_PER_PAGE_OPTIONS", Widgets::buildSelect(array("50"=>50, "100"=>100, "200"=>200, "300"=>300, "400"=>400, "500"=>500, "1000"=>"1,000"), $NumberOfResultsToDisplay));
$t->allowVariableToContainBrackets("RESULTS_PER_PAGE_OPTIONS");




######---------------- For the Orders Still Pending -----------------------------#########

$t->set_block("origPage","TaskMemoBL","TaskMemoBLout");
$t->set_block("origPage","ordersBL","ordersBLout");

// We want to to make a WHERE query that includes every status except for Canceled and Finished
// We could do this easier with a NOT EQUALS query... except the JOIN is very ineficient doing it this way.  Try using the EXPLAIN command in Mysql to see how many ROWS get joined each way
$WhereClause = "";

$statusLimiterArr = $orderQryObj->getStatusLimitArr();

// Without a limiter of some kind we must restrict "F"inished and "C"anceled orders to keep the list from beeing too large.	
if(empty($statusLimiterArr) && !$orderQryObj->CheckForOrderStartEndDateLimiters()){
	$StatusArr = ProjectStatus::getStatusCharactersNotFinishedOrCanceled();
}
else if(sizeof($statusLimiterArr) == 1 && $statusLimiterArr[0] == "C"){
	// We can't have an empty status array error... so just put a matching status.
	$StatusArr = array("C");
}
else{
	$StatusArr = ProjectStatus::getStatusCharactersNotCanceled();
}

	

foreach($StatusArr as $StatusChar)
	$WhereClause .= " PO.Status='" . $StatusChar . "' OR ";


// Strip off the last 3 characters which is "OR ";
if(!empty($WhereClause))
	$WhereClause = substr($WhereClause, 0, -3);


$counter = 0;

$query = "Select PO.ID FROM (orders 
		INNER JOIN projectsordered AS PO ON orders.ID = PO.OrderID) 
		INNER JOIN projecthistory ON projecthistory.ProjectID = PO.ID
		WHERE (" . $WhereClause . ") ";
$query .= $OpenOrderLimiterSQL;


// Just be extra safe (in case code changes by mistake above.... that we don't open up the a domain security hole.
// We always need at least one Domain ID in the Query.
if(!preg_match("/DomainID/", $query))
	$query = " AND " . DbHelper::getOrClauseFromArray("PO.DomainID", $domainObj->getSelectedDomainIDs());
	

//If this user in the VENDOR group... then we want to set a variable to limit the open orders.
if($AuthObj->CheckIfBelongsToGroup("VENDOR"))
	$query .= " AND (" . Product::GetVendorIDLimiterSQL($UserID) . ") AND PO.Status !=\"N\"";


$query .= " ORDER BY orders.DateOrdered DESC, PO.ID DESC";



$dbCmd->Query($query);

$projectIDarr = $dbCmd->GetValueArr();

$projectIDarr = array_unique($projectIDarr);

if(sizeof($projectIDarr) > 20000)
	WebUtil::PrintAdminError("The number of records that you requested exceeded 20,000. Please try and narrow your criteria or report this error to the Webmaster.");


$rowBackgroundColor=false;
$empty_orders=true;
$lastOrderID = 0;
$checkBoxCounter = 0;  //Used for counting the chexboxes within Javascript
$ShippingTypesArr_JS = "";
$OrderStatusArr_JS = "";
$ProductOptionsArr_JS = "";
$ProductQuantitiesArr_JS = "";

$resultCounter = 0;
$resultsDisplayed = 0;


$productIDsInListArr = array();

foreach($projectIDarr as $thisProjectID){

	$resultCounter++;
	
	if(($resultCounter < ($offset + 1)) || ($resultCounter > ($NumberOfResultsToDisplay + $offset)))
		continue;
		
	$resultsDisplayed++;
	
	
	$queryByProjectID = "SELECT 
			O.ID As OrderID, P.OrderDescription, UNIX_TIMESTAMP(O.DateOrdered) AS DateOrdered, P.ID AS ProjectID, P.RePrintReason, P.CustomerDiscount, O.ShippingQuote, P.OptionsAlias, 
			P.Status, P.ProductID, O.BillingType, O.ShippingChoiceID, P.NotesAdmin, P.NotesProduction, O.UserID, P.Quantity, P.Priority, O.CouponID, UNIX_TIMESTAMP(EstArrivalDate) AS EstArrivalDate, AffiliateSource
			FROM orders AS O 
			INNER JOIN projectsordered AS P ON O.ID = P.OrderID 
			WHERE P.ID = $thisProjectID";

	$dbCmd3->Query($queryByProjectID);
	$row = array();
	$row = $dbCmd3->GetRow();


	$empty_orders=false;

	$order_number = $row["OrderID"];	
	$orderBillingType =  $row["BillingType"];	
	$OrderDescription = $row["OrderDescription"];
	$date_ordered = $row["DateOrdered"];
	$project_id = $row["ProjectID"];
	$status = $row["Status"];
	$OptionsAlias = $row["OptionsAlias"];
	$productID = $row["ProductID"];
	$shippingChoiceID = $row["ShippingChoiceID"];
	$NotesAdmin = $row["NotesAdmin"];
	$NotesProduction = $row["NotesProduction"];
	$OrderUserID = $row["UserID"];
	$projectQuantity = $row["Quantity"];
	$priority = $row["Priority"];
	$RePrintReason = $row["RePrintReason"];
	$CustomerDiscPerc = $row["CustomerDiscount"];
	$ShippingQuote = $row["ShippingQuote"];
	$OrderCouponID = $row["CouponID"];
	$arrivalTimeStamp = $row["EstArrivalDate"];
	$affiliateSource = $row["AffiliateSource"];
	
	
	$productionProductID = Product::getProductionProductIDStatic($dbCmd2, $productID);
	$productIDsInListArr[] = $productionProductID;
	
	
	// If the database has a flag set to filter certain Option Choices from a long list.
	$OptionsAlias = Product::filterOptionDescriptionForList($dbCmd2, $productID, $OptionsAlias);
	

	//If we are limiting the open order list then we want to show the last status change instead of the date it was ordered
	// Don't do this on Finished orders though... because we expect them to stay stuck on the last status date.
	if($orderQryObj->CheckForOpenOrderLimiter() && $status != "F"){
		$LastStatusTimeStamp = ProjectHistory::GetLastStatusDate($dbCmd2, $project_id);
		
		
		// We want to show an error if the status has not changed within a couple of days.
		// Show from present time back to the last status change count how many days separate it.
		// However, don't count Saturdays or Sundays.
		$timeStampDifferenceFromNow = time() - $LastStatusTimeStamp;
		$daysBetweenThenAndNow = ceil($timeStampDifferenceFromNow / (60 * 60 * 24));
		
		$numberOfSaturdayOrSunBetween = 0;
		for($k=0; $k < $daysBetweenThenAndNow; $k++ ){
			$dayName = strtoupper(date("D", mktime(1, 1, 1, date("n"), (date("j") - $k), date("Y"))));
			if($dayName == "SAT" || $dayName == "SUN")
				$numberOfSaturdayOrSunBetween++;
		}
		
		$numberOfWeekDaysDifference = $daysBetweenThenAndNow - $numberOfSaturdayOrSunBetween;
		
		$fontSize = null;
		
		if($numberOfWeekDaysDifference > 4){
			$fontSize = 17;
			$fontColor = "663333";
		}
		else if($numberOfWeekDaysDifference > 3){
			$fontSize = 15;
			$fontColor = "663333";
		}
		else if($numberOfWeekDaysDifference > 2){
			$fontSize = 13;
			$fontColor = "333333";
		}
		else if($numberOfWeekDaysDifference > 1){
			$fontSize = 11;
			$fontColor = "666666";
		}
		
		if($fontSize)
			$date_ordered = "<font style='font-size:". $fontSize . "px; color:#" . $fontColor . ";'><b>" . date("D, jS, g:iA", $LastStatusTimeStamp) . "</b></font><br><font class='reallySmallbody'>($numberOfWeekDaysDifference workdays)</font>";
		else
			$date_ordered = date("D, jS, g:ia", $LastStatusTimeStamp);
	}
	else{
		$date_ordered = date("D, jS, g:ia", $date_ordered);
	}
		
	$custUserControlObj = new UserControl($dbCmd2);
	$custUserControlObj->LoadUserByID($OrderUserID);
	

	$salesRepID =  $custUserControlObj->getSalesRepID();
	$userDisplay = WebUtil::htmlOutput($custUserControlObj->getCompanyOrName());

	$t->set_var("ORDER_DATE", $date_ordered);
	$t->allowVariableToContainBrackets("ORDER_DATE");

	// Replace variables in HTML template
	$t->set_var(array(
		"ORDERNO"=>$order_number,
		"CUSTID"=>$OrderUserID,
		"CUSTOMER"=>$userDisplay, 
		"PROJECTID"=>$project_id));




	// Alternate the row colors if we are on a different order --#
	if($lastOrderID <> $order_number){
	

		if($rowBackgroundColor){
			$rowcolor = "#FFFFFF";
			$rowBackgroundColor = false;
		}
		else{
			$rowcolor = "#DDFFDD";
			$rowBackgroundColor = true;
		}

		$lastOrderID = $order_number;

		$order_number_display = $order_number;
		
		
		// Only show the Domain Logo if the user has more than 1 domain selected.
		if(sizeof($domainObj->getSelectedDomainIDs()) > 1){
			$domainLogoObj = new DomainLogos($custUserControlObj->getDomainID());
			$domainLogoImg = "<img alt='".Domain::getDomainKeyFromID($custUserControlObj->getDomainID())."'   src='./domain_logos/$domainLogoObj->verySmallIcon' border='0'>";
		}
		else{
			$domainLogoImg = "";
		}
		
		
		// If the customer used a coupon on this order and they have permissions to view details on the order screen... then show them the coupon name.
		if(!empty($OrderCouponID) && $AuthObj->CheckForPermission("DISCOUNT_DETAIL_OPENORDERS")){
			$dbCmd2->Query("SELECT Code FROM coupons WHERE ID=$OrderCouponID");
			$cpnCodeName = $dbCmd2->GetValue();
			$t->set_var("CPN", "<br><img src='./images/transparent.gif' width='3' height='3'><br><font class='reallysmallbody'><font color='#993333'>&nbsp;<b>Coupon:</b>&nbsp;&nbsp;</font>" . WebUtil::htmlOutput($cpnCodeName) . "</font>");	
		}
		else{
			$t->set_var("CPN", "");
		}
		
		$t->allowVariableToContainBrackets("CPN");
		
		
		// Show a signal in case a package has a downgraded shipping method.
		// This would mean that the shipping method associated with a package does not match that which the customer selected on checkout.
		if(Shipment::checkIfShippingMethodDoesntMatchDefaultInShippingChoice($dbCmd2, $order_number) && $AuthObj->CheckForPermission("DISCOUNT_DETAIL_OPENORDERS"))
			$t->set_var("SHP", "<br><img src='./images/transparent.gif' width='3' height='3'><br><font class='reallysmallbody'><font color='#990000'>&nbsp;Shipment Modified</font></font>");
		else
			$t->set_var("SHP", "");
			
		$t->allowVariableToContainBrackets("SHP");
		
		
		// Show if there are any special shipping instructions
		$shippingInstructions = Order::getShippingInstructions($dbCmd2, $order_number);
		$shipInstrMultiLine = "";
		$shippingInstArr = preg_split("/\n/", $shippingInstructions);
		foreach($shippingInstArr as $thisLine){
			if(!empty($shipInstrMultiLine))
				$shipInstrMultiLine .= "<br>";
			$shipInstrMultiLine .= WebUtil::htmlOutput($thisLine);
		}
		
		if(!empty($shipInstrMultiLine)){
			$shipInstrMultiLine = "<br><font class='ReallySmallBody'><u><font color='#CC0000'>Shipping Instructions</font></u><br>" . $shipInstrMultiLine . "</font>";
		}
		
		$t->set_var("S_I", $shipInstrMultiLine);
		$t->allowVariableToContainBrackets("S_I");
		
		
		// Only show the Sales Rep details on the first order number... no sense repeating the details on every single project.
		if(!empty($salesRepID) && $AuthObj->CheckForPermission("SALES_MASTER"))
			$t->set_var("SALESREP", "<br><img src='./images/transparent.gif' width='3' height='3'><br><font class='reallysmallbody'><font color='#669933'>&nbsp;Rep: <a href='./ad_users_search.php?customerid=" . $salesRepID . "' class='blueredlink'><font style='font-size:9px;'>" . WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd2, $salesRepID)) . "</font></a></font></font>");
		else
			$t->set_var("SALESREP", "");
			
		$t->allowVariableToContainBrackets("SALESREP");
			
			
		if($AuthObj->CheckForPermission("CUSTOMER_RATING_OPENORDERS")){

			$customerRating = $custUserControlObj->getUserRating();
			
			$custRatingImgHTML = "<img src='./images/star-rating-" . $customerRating . ".gif' width='74' height='13'>";

			if($customerRating > 0){

				$custRatingDivHTML = "<br><div id='Dcust" . $order_number . "' style='display:inline; cursor: hand; width:74px;' class='hiddenDHTMLwindow' onMouseOver='CustInf($order_number, $OrderUserID, true, \"".WebUtil::getFormSecurityCode()."\")' onMouseOut='CustInf($order_number, $OrderUserID, false, \"".WebUtil::getFormSecurityCode()."\")'>$custRatingImgHTML<span class='hiddenDHTMLwindow' style='visibility:hidden; left:75px; top:-15' id='Scust" . $order_number . "'></span></div><img src='./images/transparent.gif' width='10' height='1'>$domainLogoImg";
				
				$t->set_var("CUST_RATING", $custRatingDivHTML );

			}
			else{
				$t->set_var("CUST_RATING", "<br>" . $custRatingImgHTML . "<img src='./images/transparent.gif' width='10' height='1'>$domainLogoImg");
			}
		}
		else{
			$t->set_var("CUST_RATING", $domainLogoImg);
		}
		
		$t->allowVariableToContainBrackets("CUST_RATING");
		
		
		
		
		// Show an icon from the affiliate
		$affiliateHTML = "";
		if($AuthObj->CheckForPermission("MARKETING_REPORT")){
			if($affiliateSource == "cashbackShopping")
				$affiliateHTML = "<br><img src='./images/icon-bing-cashback.png'>";
		}
		
		if($orderBillingType == "P")
			$affiliateHTML .= "<br><font style='color:#6699CC; font-size:8px;'>Paypal</font>";
		
		$t->allowVariableToContainBrackets("AFFILIATE");
		$t->set_var("AFFILIATE", $affiliateHTML);
	
		if($AuthObj->CheckForPermission("TASKS_MEMOS_OPENORDERS")){
		
			// For Tasks
			$dbCmd2->Query("SELECT COUNT(*) FROM tasks WHERE AttachedTo='user' AND RefID=$OrderUserID");
			$taskCount = $dbCmd2->GetValue();
			if($taskCount == 0)
				$t->set_var("T_COUNT", "");
			else
				$t->set_var("T_COUNT", "(<b>$taskCount</b>)");

			// For Memos
			$t->set_var("MEMO", CustomerMemos::getLinkDescription($dbCmd2, $OrderUserID, true));

			// For Customer Service
			$t->set_var("CS", CustService::GetCustServiceLinkForUser($dbCmd2, $OrderUserID, true));
		
			// For messages linked to this customer.
			$t->set_var("MSG", "<a href=\"javascript:SendInternalMessage('user', $OrderUserID, false);\" class='reallysmlllink'>Msg</a>");
		
			
			$t->allowVariableToContainBrackets("T_COUNT");
			$t->allowVariableToContainBrackets("MEMO");
			$t->allowVariableToContainBrackets("CS");
			$t->allowVariableToContainBrackets("MSG");
		
		
			$t->parse("TaskMemoBLout","TaskMemoBL",false);
		}
		else{
			$t->set_var("TaskMemoBLout", "");
		}
			
	}
	else{
		//no sense in repeating the main order ID for multiple projects.  Show carrot signs to indicate it is a group
		$order_number_display = "";

		for($j=0; $j<=  (strlen($order_number) +2) ; $j++)
			$order_number_display .= "&nbsp;";
		
		$t->set_var("AFFILIATE", "");
		$t->set_var("SALESREP", "");
		$t->set_var("CPN", "");
		$t->set_var("SHP", "");
		$t->set_var("CUST_RATING", "");
		$t->set_var("TaskMemoBLout", "");
		$t->set_var("T_COUNT", "");
		$t->set_var("M_COUNT", "");
		$t->set_var("S_I", "");


	}


	$shippingPriority = ShippingChoices::getPriofityOfShippignChoiceID($shippingChoiceID);
	$ShippingTypesArr_JS .= "'$shippingPriority',";


	// Create strings for use with javascript. They will be used for the check boxes on open orders query form.
	// We are using the Option and Choice Name aliases.  The Production operator should be able to group products together regardless of what the customers see.
	$OrderStatusArr_JS .= "'$status',";
	$ProductQuantitiesArr_JS .= $projectQuantity . ",";
	$ProductOptionsArr_JS .= ProjectInfo::GetProductOptionsJavascriptArray($productionProductID, $OptionsAlias, $checkBoxCounter);


	if($priority == "U")
		$OrderStatus = "<font class='SmallBody'><font color='#CC0000'><b>Urgent</b></font></font><br>&nbsp; ";
	else
		$OrderStatus = "";

	// Format the Status into HTML --#
	$OrderStatus .= ProjectStatus::GetProjectStatusDescription($status, true);
	
	// If the Status is "Waiting for Reply" then we want to show who put it there --#
	if($status == "W" || $status == "H"){
		$dbCmd2->Query("SELECT users.Name FROM users INNER JOIN projecthistory AS PH ON PH.UserID = users.ID WHERE (PH.ProjectID=$project_id AND Note != 'Customer%') ORDER BY PH.ID DESC LIMIT 1");
		$OrderStatus .= "<br><font class='reallysmallbody'>" . WebUtil::htmlOutput($dbCmd2->GetValue()) . "</font>";
	}

	
	// Find out if there are any messages with the order
	if(!empty($NotesAdmin) && $AuthObj->CheckForPermission("VIEW_ADMIN_MESSAGES")){
		
		$NotesAdmin = preg_replace("/\n/", '<br>', $NotesAdmin);
			
		$t->set_var(array("ADMIN_MSG"=> "<br><font class='SmallBody' style='color:#0000dd;'><b><u>Admin Note:</u></b><br>" . WebUtil::htmlOutput($NotesAdmin) . "</font><br><br>"));
		$t->allowVariableToContainBrackets("ADMIN_MSG");
	}
	else{
		$t->set_var(array("ADMIN_MSG"=>""));
	}
	
	// Production Message   --------------------#
	if(!empty($NotesProduction)){
		
		$NotesProduction = preg_replace("/\n/", '<br>', $NotesProduction);

		$t->set_var(array("PROD_MSG"=> "<br><font class='SmallBody' style='color:#dd0000;'><b><u>Production:</u></b><br>" . WebUtil::htmlOutput($NotesProduction) . "</font><br><br>"));		
		$t->allowVariableToContainBrackets("PROD_MSG");
	}
	else{
		$t->set_var(array("PROD_MSG"=>""));
	}

	// Find out if we can give them a link directly into the artwork
	if($AuthObj->CheckForPermission("EDIT_ARTWORK") || $AuthObj->CheckForPermission("ARTWORK_PREVIEW_POPUPS")){
		
		if($AuthObj->CheckForPermission("ARTWORK_PREVIEW_POPUPS")){
			$previewSpanHTML = "<span style='visibility:hidden; position:absolute; left:170px; top:60' id='artwPreviewSpan" . $project_id . "'></span>";
			$hrefMouseOver = " onMouseOver='showArtPrev(" . $project_id . ", true);' onMouseOut='showArtPrev(" . $project_id . ", false);'";
			
			
			// Find out if we have previewed this email recently
			//$lastPreviewedDate = $navHistoryObj->getDateOfLastVisit($UserID, "ArtPreview", $project_id);
			$lastPreviewedDate = null;
			
			if($lastPreviewedDate){
				$t->set_var("PREVIEWED", "<br>&nbsp;<font class='ReallySmallBody' style='color:333399'><b>Viewed:</b> " . WebUtil::htmlOutput(LanguageBase::getTimeDiffDesc($lastPreviewedDate)) . " ago.</font>");
				$t->allowVariableToContainBrackets("PREVIEWED");
				$artworkBoldStart = "";
				$artworkBoldEnd = "";
			}
			else{
				$t->set_var("PREVIEWED", "");
				$artworkBoldStart = "<b><font style='font-size:16px;'>";
				$artworkBoldEnd = "</font></b>";
			}
			
		}
		else{
			$artworkBoldStart = "";
			$artworkBoldEnd = "";
			$previewSpanHTML = "";
			$hrefMouseOver = "";
			
			$t->set_var("PREVIEWED", "");
		}
		
		if($AuthObj->CheckForPermission("EDIT_ARTWORK"))
			$artHref = "./ad_proof_artwork.php?projectid=$project_id";
		else
			$artHref = "#";
		
			
		$artworkLink = " - <a href='$artHref' class='BlueRedLink' $hrefMouseOver >" . $artworkBoldStart . "A" . $artworkBoldEnd  . "</a>" . $previewSpanHTML;
		
		$t->set_var("ART", $artworkLink);
		$t->allowVariableToContainBrackets("ART");

	}
	else{
		$t->set_var(array("ART"=>""));
		$t->set_var("PREVIEWED", "");
	}





	// If the user has permissions to view Order Details within a pop-up window then integrate the <span> and href hover commands on the order number.
	if($AuthObj->CheckForPermission("ORDER_SUMMARY_POPUPS")){

		$orderPrevSpanHTML = "<span style='visibility:hidden; position:absolute; left:90px; top:-40' id='ordInf" . $project_id . "'></span>";
		$orderNumhrefMouseOver = " onMouseOver='OrdInf(" . $project_id . ", " . $order_number . ", true);' onMouseOut='OrdInf(" . $project_id . ", " . $order_number . ", false);'";
	}
	else{
		$orderPrevSpanHTML = "";
		$orderNumhrefMouseOver = "";
	
	}
	$t->set_var("ORD_SUM_SPAN", $orderPrevSpanHTML);
	$t->set_var("ORD_SUM_HOVER", $orderNumhrefMouseOver);

	
	$t->allowVariableToContainBrackets("ORD_SUM_SPAN");
	$t->allowVariableToContainBrackets("ORD_SUM_HOVER");






	$checkBoxCounter++;

	
	
	// Format the display of the Shipping Type 
	$shippingDisplay = ShippingChoices::getHtmlChoiceName($shippingChoiceID);

	// The higher the priority, the larger the font size.
	$shippingPriority = ShippingChoices::getPriofityOfShippignChoiceID($shippingChoiceID);
	if($shippingPriority == ShippingChoices::URGENT_PRIORITY || $shippingPriority == ShippingChoices::HIGH_PRIORITY)
		$shippingChoiceFontSize = "14px";
	else if($shippingPriority == ShippingChoices::ELEVATED_PRIORITY || $shippingPriority == ShippingChoices::MEDIUM_PRIORITY)
		$shippingChoiceFontSize = "13px";
	else if($shippingPriority == ShippingChoices::NORMAL_PRIORITY || $shippingPriority == ShippingChoices::NORMAL_PRIORITY)
		$shippingChoiceFontSize = "12px";
	else
		throw new Exception("An undefined Priority Code was used for a Shipping Choice");
		
	$shippingDisplay = "<font style='font-size:$shippingChoiceFontSize'>" . $shippingDisplay . "</font>";
	
	
	// Don't show shipping methods on Products with Mailing services.
	if(Product::checkIfProductHasMailingServices($dbCmd2, $productID))
		$shippingDisplay = "<font style='font-size:11px; color:#df30b0; font-weight:bold;'>Mailing Service</font>";
		

	$OrderDisplaySummary = $OrderDescription . "&nbsp;&nbsp; - &nbsp;&nbsp;$shippingDisplay" . (empty($OptionsAlias) ? "" : "<br>(" . $OptionsAlias . ")");

	$t->set_var("ORDER_SUMMARY", $OrderDisplaySummary);
	$t->set_var("STATUS", $OrderStatus);
	
	
	$t->allowVariableToContainBrackets("STATUS");
	$t->allowVariableToContainBrackets("ORDER_SUMMARY");
	
	
	// Replace variables in HTML template
	$t->set_var(array(
		"ORDERNODSP"=>$order_number_display,  
		"ROW_COLOR"=>$rowcolor
	));
	

	
	// If this order is a reprint, or we are giving a discount on the order then we need to show it.
	if((!empty($RePrintReason) || $CustomerDiscPerc > 0 || $ShippingQuote == 0) && $AuthObj->CheckForPermission("DISCOUNT_DETAIL_OPENORDERS")){
		
		// Show the type of reprint that it is.
		$prices = "Disc: " . round($CustomerDiscPerc * 100) . "%  &nbsp;&nbsp;&nbsp;&nbsp; S&amp;H: \$" . number_format($ShippingQuote, 2);
		
		if(!empty($RePrintReason))
			$discDesc = "RP: " . WebUtil::htmlOutput(Status::GetReprintReasonString($RePrintReason)) . "<br>" . $prices;
		else
			$discDesc = $prices;
			
		$t->set_var("DISCOUNT", "<br><font class='reallysmallbody'><font color='#996666'>" . $discDesc . "</font></font>");
	}
	else{
		$t->set_var("DISCOUNT", "");
	}
	
	$t->allowVariableToContainBrackets("DISCOUNT");
	
	
	
	
	// If the order is finished... Try and show a tracking number.
	if($status == "F"){
		$trackingNumbers = Order::BuildTrackingNumbersForProject($dbCmd2, $project_id, true);
		
		// Find out what type of carrier shipping method was used for the Shipment.
		$shipmentIDarr = Shipment::GetShipmentsIDsWithinProject($dbCmd2, $project_id);
		
		if(!empty($shipmentIDarr)){
			$firstShipmentID = current($shipmentIDarr);
			
			$shippingMethodCode = Shipment::getShippingMethodCodeOfShipment($dbCmd2, $firstShipmentID);
			
			$trackingNumbers .= "<br><font color='#000000'>" . WebUtil::htmlOutput($shippingMethodsObj->getShippingMethodName($shippingMethodCode, true)) . "</font>";
	
			$t->set_var("TRACK", "<font class='ReallySmallBody'>" . $trackingNumbers . "</font><br>");
		}
		else{
			$t->set_var("TRACK", "");
		}
	}
	else{
		$t->set_var("TRACK", "");
	}
	
	
	$t->set_var("ARRIV", "<font class='ReallySmallBody'><u>Arrive:</u> " .  TimeEstimate::formatTimeStamp($arrivalTimeStamp) . "</font>");

	$t->allowVariableToContainBrackets("TRACK");
	$t->allowVariableToContainBrackets("ARRIV");


	$t->parse("ordersBLout","ordersBL",true);
	
	$counter++;
}


// The DOM in javascript will only treat checkboxes as an array if there are multiple items have then same 'name'; 
// Therefore we need to insert a hidden input if we only have 1 checkbox... this is to keep the Javascript running conistently.
if($counter == 1)
	$t->set_var("EMPTY_CHECKBOX_FOR_JS_ARRAY", "<input type='hidden' name='chkbx' value=''>");
else
	$t->set_var("EMPTY_CHECKBOX_FOR_JS_ARRAY", "");


$t->allowVariableToContainBrackets("EMPTY_CHECKBOX_FOR_JS_ARRAY");

// Close off the end of the Javascript arrays
$ShippingTypesArr_JS .= "''";
$OrderStatusArr_JS .= "''";
$ProductQuantitiesArr_JS .= "0";

// So that Javascript can know when it should stop looping
$t->set_var(array(
	"SHIPPING_TYPES_ARR"=>$ShippingTypesArr_JS, 
	"PROJECTS_STATUS_ARR"=>$OrderStatusArr_JS, 
	"PROJECTS_QUANTITIES_ARR"=>$ProductQuantitiesArr_JS));

$t->set_var("PROD_OPTIONS_ARR", $ProductOptionsArr_JS);

$t->allowVariableToContainBrackets("PROD_OPTIONS_ARR");

$theTime = time();
$current_day_number = date("j", $theTime);
$current_month_number = date("n", $theTime);
$current_year_number = date("Y", $theTime);


//$exportBoxes = FunctionCallToGetTotalShipmentsby vendo;
$exportBoxes = 0;

// Display how many boxes are set for shipping export
if($exportBoxes <> 0)
	$t->set_var("TOTAL_EXPORT_BOXES", $exportBoxes . " boxes");
else
	$t->set_var("TOTAL_EXPORT_BOXES", "");

if($checkBoxCounter <> 0)
	$t->set_var("TOTAL_PROJECTS", "Total of " . $resultCounter . " Projects");
else
	$t->set_var("TOTAL_PROJECTS", "");






// Set this block so that we can erase the multi-page navigation bar if there aren't multiple pages 
$t->set_block("origPage","MultiPageBL","MultiPageBLout");
$t->set_block("origPage","SecondMultiPageBL","SecondMultiPageBLout");


// This means that we have multiple pages of search results
if($resultCounter > $NumberOfResultsToDisplay){


	// What are the name/value pairs AND URL  for all of the subsequent pages 
	$NV_pairs_URL = "";
	$BaseURL = "./ad_openorders.php";

	// Get a the navigation of hyperlinks to all of the multiple pages 
	$NavigateHTML = Navigation::GetNavigationForSearchResults($BaseURL, $resultCounter, $NV_pairs_URL, $NumberOfResultsToDisplay, $offset);

	$t->set_var(array("RESULT_DESC"=>$resultCounter, "OFFSET"=>$offset, "START_RESULTS_DISP"=>($offset+1), "END_RESULTS_DISP"=>($offset+$resultsDisplayed)));
	$t->set_var("NAVIGATE", $NavigateHTML);
	$t->allowVariableToContainBrackets("NAVIGATE");

	
	$t->parse("MultiPageBLout","MultiPageBL",true);
	$t->parse("SecondMultiPageBLout","SecondMultiPageBL",true);
}
else{
	$t->set_var(array("NAVIGATE"=>""));
	$t->set_var(array("MultiPageBLout"=>""));
	$t->set_var(array("SecondMultiPageBLout"=>""));
}





if($empty_orders){
	$t->set_block("origPage","EmptyOrdersBL","EmptyOrdersBLout");
	$t->set_var(array("EmptyOrdersBLout"=>"<font class='LargeBody'>There are no orders matching your criteria.</font>"));
	
	$t->discard_block("origPage", "EmptyOrdersBL2");
	$t->discard_block("origPage", "EmptyQueryFormLinkBL");
	
}



$productIDsInListArr = array_unique($productIDsInListArr);

// Create a Down menu of Product Choices.
$productHash = array();
foreach ($productIDsInListArr as $thisProductID){
	if(!Product::checkIfProductIDisActive($dbCmd, $thisProductID))
		continue;
	$productHash[$thisProductID] = Product::getFullProductName($dbCmd, $thisProductID);
}




$productDropDown = Widgets::buildSelect($productHash, array(), "productid", "AdminDropDown", "ChangeProductOptions(this.value)" );

$t->set_var("PRODUCT_DROP_DOWN", $productDropDown);
$t->allowVariableToContainBrackets("PRODUCT_DROP_DOWN");





// Output compressed HTML
WebUtil::print_gzipped_page_start();

$t->pparse("OUT","origPage");

WebUtil::print_gzipped_page_end();



?>

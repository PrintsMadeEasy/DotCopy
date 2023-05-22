<?php


require_once("library/Boot_Session.php");

// The home page should not be favoring 1 particular domain (if there are multiple selected). 
Domain::removeTopDomainID();


$domainObj = Domain::singleton();

$dbCmd = new DbCmd();

// Start a new http session

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("HOME_SCREEN"))
	WebUtil::PrintAdminError("Not Available");




$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if($action){
	
	WebUtil::checkFormSecurityCode();

	if($action == "closemessages"){

		$threadids = WebUtil::GetInputArr("threadids", FILTER_SANITIZE_INT);
	
		foreach($threadids as $thisThreadID){
			
			$messageThreadObj = new MessageThread();
			$messageThreadObj->setUserID($UserID);
			
			if(!empty($thisThreadID)){
				
				$messageThreadObj->loadThreadByID($thisThreadID);
			
				$messageThreadObj->markMessageAllMessagesReadForUser();
			}
		}
		
		header("Location: ./ad_home.php?");
		exit;			
	}
	else if($action == "changeMemoDaysResults"){
	
		$resultPerPage = WebUtil::GetInput("memoDaysCounts", FILTER_SANITIZE_INT);
		
		$resultPerPage = intval($resultPerPage);

		// Remember the setting
		$DaysToRemember = 300;
		$cookieTime = time()+60*60*24 * $DaysToRemember;
		setcookie ("MemoDayCountResults", $resultPerPage, $cookieTime);
		
		header("Location: ./ad_home.php?");
		exit;
	}
	else if($action == "changeTaskPageResults"){
		
			$resultPerPage = WebUtil::GetInput("taskPageCounts", FILTER_SANITIZE_INT);
			
			$resultPerPage = intval($resultPerPage);
	
			// Remember the setting
			$DaysToRemember = 300;
			$cookieTime = time()+60*60*24 * $DaysToRemember;
			setcookie ("TaskPageCountResults", $resultPerPage, $cookieTime);
			
			header("Location: ./ad_home.php?");
			exit;
	}
	else{
		throw new Exception("undefined action");
	}

}


$t = new Templatex(".");

$t->set_file("origPage", "ad_home-template.html");



$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());



// Find out the timestamp for the begining of today so that we can find out how many open orders there are today.
$BegginingOfTodayTimeStamp = mktime (0,0,1,date("n"),date("j"),date("Y"));
$EndingOfTodayTimeStamp = mktime (23,59,59,date("n"),date("j"),date("Y")); 
$BegginingOfYeserdayTimeStamp = mktime (0,0,1,date("n"),(date("j") - 1),date("Y"));  //Just subtract 1 from the day
$DayBeforeYeserdayTimeStamp = mktime (0,0,1,date("n"),(date("j") - 2),date("Y")); 
$ThreeDayTimeStamp = mktime (0,0,1,date("n"),(date("j") - 3),date("Y")); 
$FourDayTimeStamp = mktime (0,0,1,date("n"),(date("j") - 4),date("Y")); 
$FiveDayTimeStamp = mktime (0,0,1,date("n"),(date("j") - 5),date("Y")); 
$SixDayTimeStamp = mktime (0,0,1,date("n"),(date("j") - 6),date("Y")); 
$SevenDayTimeStamp = mktime (0,0,1,date("n"),(date("j") - 7),date("Y")); 
$EightDayTimeStamp = mktime (0,0,1,date("n"),(date("j") - 8),date("Y")); 


// writed "Mon" or "Sun" for the description under "Order by Day"
$t->set_var(array(
	"2_DAYS_AGO_DAY"=>date("D", $DayBeforeYeserdayTimeStamp), 
	"3_DAYS_AGO_DAY"=>date("D", $ThreeDayTimeStamp),
	"4_DAYS_AGO_DAY"=>date("D", $FourDayTimeStamp),
	"5_DAYS_AGO_DAY"=>date("D", $FiveDayTimeStamp),
	"6_DAYS_AGO_DAY"=>date("D", $SixDayTimeStamp),
	"7_DAYS_AGO_DAY"=>date("D", $SevenDayTimeStamp),
	"8_DAYS_AGO_DAY"=>date("D", $EightDayTimeStamp)
	));


// Make the cuttoff time near the end of today.. that way any time estimate will show up as due today.
$TodayCuttofTime = $EndingOfTodayTimeStamp;



//Basically all statuses that are not finished or canceled.
$openOrderStatuses = ""; 
$openOrderStatusArr = ProjectStatus::getStatusCharactersNotFinishedOrCanceled();
foreach($openOrderStatusArr as $s)
	$openOrderStatuses .= $s;


$notCanceledStatuses = "";
$notCanceledStatusArr = ProjectStatus::getStatusCharactersNotCanceled();
foreach($notCanceledStatusArr as $s)
	$notCanceledStatuses .= $s;
	


$t->set_var("ALLOPEN_STATUSES", $openOrderStatuses);


$orderQryObj = new OrderQuery($dbCmd);

$limitToProductIDarr = $orderQryObj->getProductLimit();
$limitToProductIDstr = implode("|", $limitToProductIDarr);

$t->set_var("LIMIT_PRODUCT", $limitToProductIDstr);


$productIDdropDown = array("0" => "All Products");

$userDomainIDsArr = $domainObj->getSelectedDomainIDs();

foreach($userDomainIDsArr as $thisDomainID){
	
	// If we have more than one Domain, then list the domain in front of the Product.
	if(sizeof($userDomainIDsArr) > 1)
		$domainPrefix = $domainObj->getDomainKeyFromID($thisDomainID) . "> ";
	else
		$domainPrefix = "";
	
	$productNamesInDomain = Product::getFullProductNamesHash($dbCmd, $thisDomainID, false);
	
	foreach($productNamesInDomain as $productID => $productName)
		$productIDdropDown[$productID] = $domainPrefix . $productName;
}



// If there is no product limit... then in an array of "Zero" which will select the All Product Choice
if(empty($limitToProductIDarr))
	$productIDselected = array("0");
else
	$productIDselected = $limitToProductIDarr;

$t->set_var("PRODUCT_DROPDOWN", Widgets::buildSelect($productIDdropDown, $productIDselected));
$t->allowVariableToContainBrackets("PRODUCT_DROPDOWN");

// If this user in the VENDOR group... then we want to set a variable to limit the reports to him/her.
if($AuthObj->CheckIfBelongsToGroup("VENDOR"))
	$VendorLimiter = $UserID;
else
	$VendorLimiter = "";




// Show a message on how many products are beeing displayed.
if(sizeof($productIDselected) == 1 && current($productIDselected) == 0)
	$t->set_var("PRODUCTS_DISPLAYED", "<font class='SmallBody' style='color:#006600'>All Products Displayed</font>");
else
	$t->set_var("PRODUCTS_DISPLAYED", "<font class='SmallBody' style='color:#770066; font-weight:bold; font-size:13px;'>" . sizeof($productIDselected)  . " " . LanguageBase::GetPluralSuffix(sizeof($productIDselected), "Product ", "Products ") . " Displayed</font>");
	
$t->allowVariableToContainBrackets("PRODUCTS_DISPLAYED");



if(sizeof($productIDdropDown) < 6)
	$sizeProductDropDwn = 6;
else if(sizeof($productIDdropDown) > 25)
	$sizeProductDropDwn = 26;
else
	$sizeProductDropDwn = (sizeof($productIDdropDown) + 1);

$t->set_var("PRODUCT_DROP_DOWN_SIZE", $sizeProductDropDwn);


$expeditedShippingPriorities = implode("|", ShippingChoices::getExpeditedShippingPriorities());

$t->set_var("EXPEDITED_SHIPPING_PRIORITIES", $expeditedShippingPriorities);


// Set Variables in HTML for pure order status ... regardless of shipping method 
$t->set_var("NEW_CNT", $orderQryObj->GetOrderCount($VendorLimiter, "N", $limitToProductIDarr, "", "", "", "", "", "", "", "", "", "", 0, 0));
$t->set_var("PROOF_CNT", $orderQryObj->GetOrderCount($VendorLimiter, "P", $limitToProductIDarr, "", "", "", "", "", "", "", "", "", "", 0, 0));
$t->set_var("QUEUED_CNT", $orderQryObj->GetOrderCount($VendorLimiter, "Q", $limitToProductIDarr, "", "", "", "", "", "", "", "", "", "", 0, 0));
$t->set_var("PRINT_CNT", $orderQryObj->GetOrderCount($VendorLimiter, "T", $limitToProductIDarr, "", "", "", "", "", "", "", "", "", "", 0, 0));
$t->set_var("BOX_CNT", $orderQryObj->GetOrderCount($VendorLimiter, "B", $limitToProductIDarr, "", "", "", "", "", "", "", "", "", "", 0, 0));
$t->set_var("HOLD_CNT", $orderQryObj->GetOrderCount($VendorLimiter, "H", $limitToProductIDarr, "", "", "", "", "", "", "", "", "", "", 0, 0));
$t->set_var("OFFSET_CNT", $orderQryObj->GetOrderCount($VendorLimiter, "D", $limitToProductIDarr, "", "", "", "", "", "", "", "", "", "", 0, 0));
$t->set_var("PLATED_CNT", $orderQryObj->GetOrderCount($VendorLimiter, "E", $limitToProductIDarr, "", "", "", "", "", "", "", "", "", "", 0, 0));
$t->set_var("WAITING_CNT", $orderQryObj->GetOrderCount($VendorLimiter, "W", $limitToProductIDarr, "", "", "", "", "", "", "", "", "", "", 0, 0));
$t->set_var("ARTWORKHELP_CNT", $orderQryObj->GetOrderCount($VendorLimiter, "L", $limitToProductIDarr, "", "", "", "", "", "", "", "", "", "", 0, 0));

// For the Expedited counts.
$t->set_var("NEW_CNT_EXP", $orderQryObj->GetOrderCount($VendorLimiter, "N", $limitToProductIDarr, $expeditedShippingPriorities, "", "", "", "", "", "", "", "", "", 0, 0));
$t->set_var("PROOF_CNT_EXP", $orderQryObj->GetOrderCount($VendorLimiter, "P", $limitToProductIDarr, $expeditedShippingPriorities, "", "", "", "", "", "", "", "", "", 0, 0));
$t->set_var("QUEUED_CNT_EXP", $orderQryObj->GetOrderCount($VendorLimiter, "Q", $limitToProductIDarr, $expeditedShippingPriorities, "", "", "", "", "", "", "", "", "", 0, 0));
$t->set_var("PRINT_CNT_EXP", $orderQryObj->GetOrderCount($VendorLimiter, "T", $limitToProductIDarr, $expeditedShippingPriorities, "", "", "", "", "", "", "", "", "", 0, 0));
$t->set_var("BOX_CNT_EXP", $orderQryObj->GetOrderCount($VendorLimiter, "B", $limitToProductIDarr, $expeditedShippingPriorities, "", "", "", "", "", "", "", "", "", 0, 0));
$t->set_var("PLATED_CNT_EXP", $orderQryObj->GetOrderCount($VendorLimiter, "E", $limitToProductIDarr, $expeditedShippingPriorities, "", "", "", "", "", "", "", "", "", 0, 0));
$t->set_var("HOLD_CNT_EXP", $orderQryObj->GetOrderCount($VendorLimiter, "H", $limitToProductIDarr, $expeditedShippingPriorities, "", "", "", "", "", "", "", "", "", 0, 0));
$t->set_var("OFFSET_CNT_EXP", $orderQryObj->GetOrderCount($VendorLimiter, "D", $limitToProductIDarr, $expeditedShippingPriorities, "", "", "", "", "", "", "", "", "", 0, 0));



// Make the font color red if there is an artwork problem
$ArtWorkProblemCnt = $orderQryObj->GetOrderCount($VendorLimiter, "A", $limitToProductIDarr, "", "", "", "", "", "", "", "", "", "", 0, 0);
$t->set_var("ART_CNT", $ArtWorkProblemCnt);
if($ArtWorkProblemCnt > 0){
	$t->set_var("ARTPROBLEM_START", "<font color='#CC0000'>");
	$t->set_var("ARTPROBLEM_END", "</font>");
}
else{
	$t->set_var("ARTPROBLEM_START", "");
	$t->set_var("ARTPROBLEM_END", "");
}

$t->allowVariableToContainBrackets("ARTPROBLEM_START");
$t->allowVariableToContainBrackets("ARTPROBLEM_END");

// Make the font color red if there is a Variable Data Error
$VarDataProblemCnt = $orderQryObj->GetOrderCount($VendorLimiter, "V", $limitToProductIDarr, "", "", "", "", "", "", "", "", "", "", 0, 0);
$t->set_var("VARDATA_CNT", $VarDataProblemCnt);
if($VarDataProblemCnt > 0){
	$t->set_var("VARDATA_START", "<font color='#CC0000'><b>");
	$t->set_var("VARDATA_END", "</b></font>");
}
else{
	$t->set_var("VARDATA_START", "<!-- ");
	$t->set_var("VARDATA_END", " -->");
}


$t->allowVariableToContainBrackets("VARDATA_START");
$t->allowVariableToContainBrackets("VARDATA_END");


// Set Variables in HTML for order status ... having expidited shipping Past the Estimated Shipping Time
$t->set_var("NEW_PST", $orderQryObj->GetOrderCount($VendorLimiter, "N", $limitToProductIDarr, "", "", "", "", $TodayCuttofTime, "", "", "", "", "", 0, 0));
$t->set_var("PROOF_PST", $orderQryObj->GetOrderCount($VendorLimiter, "P", $limitToProductIDarr, "", "", "", "", $TodayCuttofTime, "", "", "", "", "", 0, 0));
$t->set_var("QUEUED_PST", $orderQryObj->GetOrderCount($VendorLimiter, "Q", $limitToProductIDarr, "", "", "", "", $TodayCuttofTime, "", "", "", "", "", 0, 0));
$t->set_var("PRINT_PST", $orderQryObj->GetOrderCount($VendorLimiter, "T", $limitToProductIDarr, "", "", "", "", $TodayCuttofTime, "", "", "", "", "", 0, 0));
$t->set_var("BOXED_PST", $orderQryObj->GetOrderCount($VendorLimiter, "B", $limitToProductIDarr, "", "", "", "", $TodayCuttofTime, "", "", "", "", "", 0, 0));
$t->set_var("PLATED_PST", $orderQryObj->GetOrderCount($VendorLimiter, "E", $limitToProductIDarr, "", "", "", "", $TodayCuttofTime, "", "", "", "", "", 0, 0));
$t->set_var("OFFSET_PST", $orderQryObj->GetOrderCount($VendorLimiter, "D", $limitToProductIDarr, "", "", "", "", $TodayCuttofTime, "", "", "", "", "", 0, 0));
$t->set_var("ALL_PST", $orderQryObj->GetOrderCount($VendorLimiter, $openOrderStatuses, $limitToProductIDarr, "", "", "", "", $TodayCuttofTime, "", "", "", "", "", 0, 0));


// Due to ship today with expedited Shipping.
$t->set_var("PROOF_PST_EXP", $orderQryObj->GetOrderCount($VendorLimiter, "P", $limitToProductIDarr, $expeditedShippingPriorities, "", "", "", $TodayCuttofTime, "", "", "", "", "", 0, 0));
$t->set_var("QUEUED_PST_EXP", $orderQryObj->GetOrderCount($VendorLimiter, "Q", $limitToProductIDarr, $expeditedShippingPriorities, "", "", "", $TodayCuttofTime, "", "", "", "", "", 0, 0));
$t->set_var("PRINT_PST_EXP", $orderQryObj->GetOrderCount($VendorLimiter, "T", $limitToProductIDarr, $expeditedShippingPriorities, "", "", "", $TodayCuttofTime, "", "", "", "", "", 0, 0));
$t->set_var("BOXED_PST_EXP", $orderQryObj->GetOrderCount($VendorLimiter, "B", $limitToProductIDarr, $expeditedShippingPriorities, "", "", "", $TodayCuttofTime, "", "", "", "", "", 0, 0));
$t->set_var("PLATED_PST_EXP", $orderQryObj->GetOrderCount($VendorLimiter, "E", $limitToProductIDarr, $expeditedShippingPriorities, "", "", "", $TodayCuttofTime, "", "", "", "", "", 0, 0));
$t->set_var("OFFSET_PST_EXP", $orderQryObj->GetOrderCount($VendorLimiter, "D", $limitToProductIDarr, $expeditedShippingPriorities, "", "", "", $TodayCuttofTime, "", "", "", "", "", 0, 0));


// Set Variables in HTML for order status ... having expidited shipping Past the Estimated Printing Time
$t->set_var("NEW_PPT", $orderQryObj->GetOrderCount($VendorLimiter, "N", $limitToProductIDarr, "", "", "", "", "", $TodayCuttofTime, "", "", "", "", 0, 0));
$t->set_var("PROOF_PPT", $orderQryObj->GetOrderCount($VendorLimiter, "P", $limitToProductIDarr, "", "", "", "", "", $TodayCuttofTime, "", "", "", "", 0, 0));
$t->set_var("QUEUED_PPT", $orderQryObj->GetOrderCount($VendorLimiter, "Q", $limitToProductIDarr, "", "", "", "", "", $TodayCuttofTime, "", "", "", "", 0, 0));
$t->set_var("PRINT_PPT", $orderQryObj->GetOrderCount($VendorLimiter, "T", $limitToProductIDarr, "", "", "", "", "", $TodayCuttofTime, "", "", "", "", 0, 0));
$t->set_var("BOXED_PPT", $orderQryObj->GetOrderCount($VendorLimiter, "B", $limitToProductIDarr, "", "", "", "", "", $TodayCuttofTime, "", "", "", "", 0, 0));
$t->set_var("OTHER_PPT", $orderQryObj->GetOrderCount($VendorLimiter, "SHDALWV", $limitToProductIDarr, "", "", "", "", "", $TodayCuttofTime, "", "", "", "", 0, 0));


// Set Variables in HTML for order status ... having an Urgent Status
$t->set_var("NEW_UGT", $orderQryObj->GetOrderCount($VendorLimiter, "N", $limitToProductIDarr, "", "", "", "", "", "", "", "U", "", "", 0, 0));
$t->set_var("PROOF_UGT", $orderQryObj->GetOrderCount($VendorLimiter, "P", $limitToProductIDarr, "", "", "", "", "", "", "", "U", "", "", 0, 0));
$t->set_var("QUEUED_UGT", $orderQryObj->GetOrderCount($VendorLimiter, "Q", $limitToProductIDarr, "", "", "", "", "", "", "", "U", "", "", 0, 0));
$t->set_var("PRINT_UGT", $orderQryObj->GetOrderCount($VendorLimiter, "T", $limitToProductIDarr, "", "", "", "", "", "", "", "U", "", "", 0, 0));
$t->set_var("BOXED_UGT", $orderQryObj->GetOrderCount($VendorLimiter, "B", $limitToProductIDarr, "", "", "", "", "", "", "", "U", "", "", 0, 0));
$t->set_var("PLATED_UGT", $orderQryObj->GetOrderCount($VendorLimiter, "E", $limitToProductIDarr, "", "", "", "", "", "", "", "U", "", "", 0, 0));
$t->set_var("OFFSET_UGT", $orderQryObj->GetOrderCount($VendorLimiter, "D", $limitToProductIDarr, "", "", "", "", "", "", "", "U", "", "", 0, 0));
$t->set_var("OTHER_UGT", $orderQryObj->GetOrderCount($VendorLimiter, "SHDALWV", $limitToProductIDarr, "", "", "", "", "", "", "", "U", "", "", 0, 0));



// Show counts for Reprints
$t->set_var("REPRINT_CNT", $orderQryObj->GetOrderCount($VendorLimiter, $openOrderStatuses, $limitToProductIDarr, "", "", "", "", "", "", "", "", "Y", "", 0, 0));

// Show counts for Defective orders
$t->set_var("DEFECTIVE_CNT", $orderQryObj->GetOrderCount($VendorLimiter, $openOrderStatuses, $limitToProductIDarr, "", "", "", "", "", "", "", "", "", "Defective", 0, 0));

// Show counts for Canceled
$t->set_var("CANCELED_CNT", $orderQryObj->GetOrderCount($VendorLimiter, "C", $limitToProductIDarr, "", $SevenDayTimeStamp, $EndingOfTodayTimeStamp, "", "", "", "", "", "", "", 0, 0));

// Set timestamps on Limiter Links 
$t->set_var(array(	
			"8_DAY_STARTTIME"=>$EightDayTimeStamp,
			"7_DAY_STARTTIME"=>$SevenDayTimeStamp,
			"6_DAY_STARTTIME"=>$SixDayTimeStamp,
			"5_DAY_STARTTIME"=>$FiveDayTimeStamp,
			"4_DAY_STARTTIME"=>$FourDayTimeStamp,
			"3_DAY_STARTTIME"=>$ThreeDayTimeStamp,
			"DAY_BEFORE_YESTERDAY_START_TIME"=>$DayBeforeYeserdayTimeStamp, 
			"YESTERDAY_START_TIME"=>$BegginingOfYeserdayTimeStamp, 
			"YESTERDAY_START_TIME"=>$BegginingOfYeserdayTimeStamp,
			"TODAY_START_TIME"=>$BegginingOfTodayTimeStamp, 
			"TODAY_END_TIME"=>$EndingOfTodayTimeStamp, 
			"CUTTOF_TIME"=>$TodayCuttofTime));




$t->set_var("TOTAL_ORDERS", $orderQryObj->GetOrderCount($VendorLimiter, $notCanceledStatuses, $limitToProductIDarr, "", $BegginingOfTodayTimeStamp, $EndingOfTodayTimeStamp, "", "", "", "", "", "", "", 0, 0));
$t->set_var("TOTAL_YESTERDAY", $orderQryObj->GetOrderCount($VendorLimiter, $notCanceledStatuses, $limitToProductIDarr, "", $BegginingOfYeserdayTimeStamp, $BegginingOfTodayTimeStamp, "", "", "", "", "", "", "", 0, 0));
$t->set_var("TOTAL_DAYBEFORE", $orderQryObj->GetOrderCount($VendorLimiter, $notCanceledStatuses, $limitToProductIDarr, "", $DayBeforeYeserdayTimeStamp, $BegginingOfYeserdayTimeStamp, "", "", "", "", "", "", "", 0, 0));
$t->set_var("TOTAL_3DAYSAGO", $orderQryObj->GetOrderCount($VendorLimiter, $notCanceledStatuses, $limitToProductIDarr, "", $ThreeDayTimeStamp, $DayBeforeYeserdayTimeStamp, "", "", "", "", "", "", "", 0, 0));


$t->set_var("TOTAL_OPEN", $orderQryObj->GetOrderCount($VendorLimiter, $openOrderStatuses, $limitToProductIDarr, "", "", "", "", "", "", "", "", "", "", 0, 0));

if(!$AuthObj->CheckForPermission("EXTENDED_HOMEPAGE_HISTORY")){
	$t->discard_block("origPage", "LongOrderHistoryBL");
}
else{
	$t->set_var("TOTAL_4DAYSAGO", $orderQryObj->GetOrderCount($VendorLimiter, $notCanceledStatuses, $limitToProductIDarr, "", $FourDayTimeStamp, $ThreeDayTimeStamp, "", "", "", "", "", "", "", 0, 0));
	$t->set_var("TOTAL_5DAYSAGO", $orderQryObj->GetOrderCount($VendorLimiter, $notCanceledStatuses, $limitToProductIDarr, "", $FiveDayTimeStamp, $FourDayTimeStamp, "", "", "", "", "", "", "", 0, 0));
	$t->set_var("TOTAL_6DAYSAGO", $orderQryObj->GetOrderCount($VendorLimiter, $notCanceledStatuses, $limitToProductIDarr, "", $SixDayTimeStamp, $FiveDayTimeStamp, "", "", "", "", "", "", "", 0, 0));
	$t->set_var("TOTAL_7DAYSAGO", $orderQryObj->GetOrderCount($VendorLimiter, $notCanceledStatuses, $limitToProductIDarr, "", $SevenDayTimeStamp, $SixDayTimeStamp, "", "", "", "", "", "", "", 0, 0));
	$t->set_var("TOTAL_8DAYSAGO", $orderQryObj->GetOrderCount($VendorLimiter, $notCanceledStatuses, $limitToProductIDarr, "", $EightDayTimeStamp, $SevenDayTimeStamp, "", "", "", "", "", "", "", 0, 0));

}






if(!$AuthObj->CheckForPermission("ORDER_LIST_ARRIVAL_DATES")){
	$t->discard_block("origPage", "ArrivalDatesCountBL1");
	$t->discard_block("origPage", "ArrivalDatesCountBL2");
}
else{
	$t->set_var("ARRIVE_TODAY", $orderQryObj->GetOrderCount($VendorLimiter, $notCanceledStatuses, $limitToProductIDarr, "", "", "", $BegginingOfTodayTimeStamp, "", "", "", "", "", "", 0, 0));
	$t->set_var("ARRIVE_YEST", $orderQryObj->GetOrderCount($VendorLimiter, $notCanceledStatuses, $limitToProductIDarr, "", "", "", $BegginingOfYeserdayTimeStamp, "", "", "", "", "", "", 0, 0));
	$t->set_var("ARRIVE_2", $orderQryObj->GetOrderCount($VendorLimiter, $notCanceledStatuses, $limitToProductIDarr, "", "", "", $DayBeforeYeserdayTimeStamp, "", "", "", "", "", "", 0, 0));
	$t->set_var("ARRIVE_3", $orderQryObj->GetOrderCount($VendorLimiter, $notCanceledStatuses, $limitToProductIDarr, "", "", "", $ThreeDayTimeStamp, "", "", "", "", "", "", 0, 0));
	$t->set_var("ARRIVE_4", $orderQryObj->GetOrderCount($VendorLimiter, $notCanceledStatuses, $limitToProductIDarr, "", "", "", $FourDayTimeStamp, "", "", "", "", "", "", 0, 0));
	$t->set_var("ARRIVE_5", $orderQryObj->GetOrderCount($VendorLimiter, $notCanceledStatuses, $limitToProductIDarr, "", "", "", $FiveDayTimeStamp, "", "", "", "", "", "", 0, 0));
}



if($AuthObj->CheckForPermission("CREDIT_CARD_ERRORS")){

	// Find out how many credit card charge errors there are --#
	$t->set_var("CREDIT_ERRORS", Order::GetNumberOfCredtitCardErrors($dbCmd));
}
else{
	$t->set_block("origPage","HideNotificationBL","HideNotificationBLout");
	$t->set_var("HideNotificationBLout","&nbsp;");
}


if($AuthObj->CheckForPermission("CHAT_SEARCH")){
	
	// Find out how many open, new, or waiting chat threads there are.
	$t->set_var("CHAT_COUNT", ChatThread::getOpenChatThreadsBySelectedDomains());
}
else{
	$t->discard_block("origPage","HideChatSearchBL");
}


if($AuthObj->CheckForPermission("VIEW_CUSTOMER_SERVICE")){

	
	$t->set_var("MY_OPEN_CSITEMS", CustService::GetCustomerServiceCount($dbCmd, "MY", $UserID, true));
	$t->set_var("UNASSIGNED_OPEN_CSITEMS", CustService::GetCustomerServiceCount($dbCmd, "UNASSIGNED", $UserID, true));
	$t->set_var("OTHERS_OPEN_CSITEMS", CustService::GetCustomerServiceCount($dbCmd, "OTHERS", $UserID, true));
	$t->set_var("PHONE_OPEN_CSITEMS", CustService::GetCustomerServiceCount($dbCmd, "PHONE", $UserID, true));
	$t->set_var("HELP_OPEN_CSITEMS", CustService::GetCustomerServiceCount($dbCmd, "HELP", $UserID, true));
	
	// Find out if we had any trouble getting payment through with Paypal
	$t->set_var("MASSPAY_ERRORS", "");
	if($AuthObj->CheckForPermission("SALES_MASTER")){
		$dbCmd->Query("SELECT COUNT(*) FROM paypalmasspay WHERE MassPayStatus='E' AND " . DbHelper::getOrClauseFromArray("DomainID", $userDomainIDsArr));
		if($dbCmd->GetValue() != 0)
			$t->set_var("MASSPAY_ERRORS", "<br><a href='./ad_sales_management.php' class='blueredlink'><font color='#CC0000'>PayPal MassPay Error</font></a>");
	
		$t->allowVariableToContainBrackets("MASSPAY_ERRORS");
	}
	
	// Find out how many returned packages there are.
	$ReturnedPackagesObj = new ReturnedPackages($dbCmd);
	$t->set_var("RETURNED_PACKAGES_COUNT", $ReturnedPackagesObj->GetCountOfReturnedPackages());
	
}
else{
	$t->set_block("origPage","CustomerServiceNotificationBL","CustomerServiceNotificationBLout");
	$t->set_var("CustomerServiceNotificationBLout","&nbsp;");
}
	

//---------------- For the Unread Messages ----------------------------

$t->set_block("origPage","messagesBL","messagesBLout");

$messageThreadCollectionObj = new MessageThreadCollection();
$messageThreadCollectionObj->setUserID($UserID);
$messageThreadCollectionObj->loadThreadCollection(true);

$messageDisplay = new MessageDisplay();
$empty_message  = $messageDisplay->displayAsUnreadHTML($t, $messageThreadCollectionObj);


//---------------- For the Unfinished Tasks --------------------------

$taskOffset = WebUtil::GetInput("taskOffset", FILTER_SANITIZE_INT);

$tasksShowBeforeReminder = WebUtil::GetCookie("TasksShowBeforeReminder", "true") == "false" ? false : true;

if($tasksShowBeforeReminder)
	$t->set_var("SHOW_FUTURE_TASKS", "checked");
else
	$t->set_var("SHOW_FUTURE_TASKS", "");

$NumberOfTasksToDisplay  = WebUtil::GetCookie("TaskPageCountResults",10);
$t->set_var("TASKS_PER_PAGE_DROPDOWN", Widgets::buildSelect(array("10"=>10, "20"=>20, "50"=>50, "100"=>100, "500"=>500, "10000"=>"All"), $NumberOfTasksToDisplay));
$t->allowVariableToContainBrackets("TASKS_PER_PAGE_DROPDOWN");

$taskCollectionObj = new TaskCollection();
$taskCollectionObj->limitShowTasksBeforeReminder($tasksShowBeforeReminder);
$taskCollectionObj->limitUserID($UserID);

// Multi-page operation
$taskResultCounter = $taskCollectionObj->getRecordCount();
$taskObjectsArr = $taskCollectionObj->getTaskObjectsArr($NumberOfTasksToDisplay, $taskOffset);
$taskResultsDisplayed = $taskCollectionObj->getResultsDisplayedCount();

// Display task in HTML with defined colors and templates
$tasksDisplayObj = new TasksDisplay($taskObjectsArr);
$tasksDisplayObj->setTemplateFileName("tasks-template.html");
$tasksDisplayObj->setReturnURL("ad_home.php");

$tasksDisplayObj->displayAsHTML($t);


// Multi page display bar ///

// Set this block so that we can erase the multi-page navigation bar if there aren't multiple pages 
$t->set_block("origPage","MultiPageBL","MultiPageBLout");

// This means that we have multiple pages of search results
if($taskResultCounter > $NumberOfTasksToDisplay){
	
	// What are the name/value pairs AND URL  for all of the subsequent pages 
	$taskNV_pairs_URL = "";
	$taskBaseURL = "./ad_home.php";

	// Get a the navigation of hyperlinks to all of the multiple pages 
	$NavigateHTML = Navigation::GetNavigationForSearchResults($taskBaseURL, $taskResultCounter, $taskNV_pairs_URL, $NumberOfTasksToDisplay, $taskOffset, "taskOffset");

	$t->set_var(array("TASK_NAVIGATE"=>$NavigateHTML, "TASK_RESULT_DESC"=>$taskResultCounter, "TASK_START_RESULTS_DISP"=>($taskOffset+1), "TASK_END_RESULTS_DISP"=>($taskOffset+$taskResultsDisplayed)));
	$t->parse("MultiPageBLout","MultiPageBL",true);
	
	$t->allowVariableToContainBrackets("TASK_NAVIGATE");
}
else{
	$t->set_var(array("TASK_NAVIGATE"=>""));
	$t->set_var(array("MultiPageBLout"=>""));	
}






if($empty_message){
	$t->set_block("origPage","EmptyMessageBL","EmptyMessageBLout");
	$t->set_var("EmptyMessageBLout", "");
}




$t->set_var("SERVER_TIME", date("D, M j, g:i a"));





// Show Mailing Batches (if the user has permissions to see it).
$mailingBatchesHTML = "";

if($AuthObj->CheckForPermission("MAILING_BATCHES_VIEW")){

	if(WebUtil::GetCookie("HideMailingBatches") == "yes"){
		$mailingBatchesHTML = "<input type=\"button\" name=\"xx\" value=\"Show Mailing Batches\" class=\"AdminButton\" onMouseOver=\"this.style.background='#ffeeaa';\" onMouseOut=\"this.style.background='#FFFFDD';\" onClick=\"ShowMailingBatches(true);\"><br>&nbsp;";
	}
	else{
		$mailingBatchesHTML = "<input type=\"button\" name=\"xx\" value=\"Hide Mailing Batches\" class=\"AdminButton\" onMouseOver=\"this.style.background='#ffeeaa';\" onMouseOut=\"this.style.background='#FFFFDD';\" onClick=\"ShowMailingBatches(false);\"><br>&nbsp;";
		$mailingBatchesHTML .= MailingBatch::getMailingBatchTableForOpenBatches($dbCmd, $UserID, $AuthObj);
	}
}

if(!empty($mailingBatchesHTML))
	$mailingBatchesHTML .= "<br><br>";

$t->set_var("MAILING_BATCHES", $mailingBatchesHTML);

$t->allowVariableToContainBrackets("MAILING_BATCHES");




if(!$AuthObj->CheckForPermission("DEAD_ACCOUNTS"))
	$t->discard_block("origPage", "DeadAccountsLinkBL");




// Find out if we have any events in the 3 days.
$eventObj = new Event();
$eventSchedulerObj = new EventScheduler();



// We want to include all Production Product ID's for which the user has selected in their domain list.
// Only do this if the user has selected less than 10 domains.  In the future it may be too much work if we have 100's of domains with dozens of products.
// Product delays are not that common... they are more important for the people with access to few domains... who may be outsourcing their Production Piggyback anyway.
if(sizeof($domainObj->getSelectedDomainIDs()) < 10)
	$allProductionProductIDs = Product::getAllProductionProductIDsInUsersSelectedDomains();
else
	$allProductionProductIDs = array();


// Find out if this day has an event
for($i=0; $i<3; $i++){

	$timeStampDay = mktime (1,1,1, date("n"), (date("j") + $i), date("Y"));

	// Build a list of events
	$eventHTML = "";
	$delayTransit = false;
	$delayProduction = false;

	$eventTitleSingatures = $eventSchedulerObj->getEventsInDay($timeStampDay, $allProductionProductIDs, EventScheduler::DELAYS_DONT_CARE, EventScheduler::EVENT_SIGNATURE_TITLE);

	foreach($eventTitleSingatures as $thisTitleSignature){
		
		$eventTitle = $eventObj->getEventTitleFromTitleSignature($thisTitleSignature);
		
		$productIDofEventTitle = $eventObj->getProductIdFromTitleSignature($thisTitleSignature);

		$eventTitle = WebUtil::htmlOutput($eventTitle);
		
		$spanXposition = $i * -140;
		
		$eventTitle = "<div id='ScheduleDiv" . $thisTitleSignature . "' class='hiddenDHTMLwindow' >
						<a class='BlueRedLink' href='javascript:showSchedule(\"". $thisTitleSignature ."\", true)'>$eventTitle</a>
						<span style='background-color:#999999; visibility:hidden; left:".$spanXposition."px; top:-280' id='ScheduleSpan" . $thisTitleSignature . "'  >
						</span></div>";
		
		// If this Event is attached to a certain Product. Then Prefix the product name in front.
		if(!empty($productIDofEventTitle))
			$eventTitle = "<font class='ReallySmallBody'><u>" . WebUtil::htmlOutput(Product::getRootProductName($dbCmd, $productIDofEventTitle)) . "<u></font><br>" . $eventTitle;
		
		$eventHTML .= $eventTitle;
		
		// Show Delay Icons.
		if($eventObj->checkIfTitleSignatureHasTransitDelay($thisTitleSignature))
			$eventHTML .= '&nbsp;&nbsp;<img src="./images/icon-truck.png">';
		if($eventObj->checkIfTitleSignatureHasProductionDelay($thisTitleSignature))
			$eventHTML .= '&nbsp;&nbsp;<img src="./images/icon-proudctionDelay.png">';
		
			
		// Add a Line separator
		$eventHTML .= "<img src='./images/admin/calendar_event_line.png'><br><img src='./images/transparent.gif' width='1' height='4'><br>";
	}



	if(empty($eventHTML))
		$eventHTML = "Empty";


	if($i==0){
		$t->set_var("EVENTS_TODAY", $eventHTML);
	}
	else if($i==1){
		$t->set_var("EVENTS_TOMORROW", $eventHTML);
	}
	else if($i==2){
		$t->set_var("EVENTS_DAY_THREE", $eventHTML);
		$t->set_var("DAY_THREE_NAME", date("l", $timeStampDay));
	}
	
	$t->allowVariableToContainBrackets("EVENTS_TODAY");
	$t->allowVariableToContainBrackets("EVENTS_TOMORROW");
	$t->allowVariableToContainBrackets("EVENTS_DAY_THREE");
}



if($AuthObj->CheckForPermission("TESTIMONIALS"))
	$t->set_var("USER_COMMENTS_PENDING", CustomerTestimonials::getPendingCount($domainObj->getSelectedDomainIDs()));
else
	$t->discard_block("origPage", "UserFeedbackLinkBL");


// If they have permissions to see Memo Categories.
if($AuthObj->CheckForPermission("MEMOS_SALES_FORCE_REPORTS")){

  	
  	// 9 is the default value for new users.
	$memoCountsSelection = WebUtil::GetCookie("MemoDayCountResults", 9);

	$t->set_var("SMC_DAYS_DROPDOWN", Widgets::buildSelect(array("5"=>5, "6"=>6, "7"=>7, "8"=>8, "9"=>9, "10"=>10, "20"=>20, "30"=>30, "40"=>40, "50"=>50, "60"=>60, "70"=>70, "80"=>80, "90"=>90), $memoCountsSelection));
	$t->allowVariableToContainBrackets("SMC_DAYS_DROPDOWN");
	
	if($memoCountsSelection>90) 
		throw new Exception("Invalid number days selected."); // Prevents calling infinite number of queries

	$t->set_block("origPage","MemoRowBL","MemoRowBLout");

	// Today = 0, Yesterday = 1 etc. can be dynamically replicated as many times as needed
	for($day = 0; $day < $memoCountsSelection; $day++) {
		
		$dayNum = date("j") - $day;
		
		$beginMemoTimeStamp  = mktime ( 0, 0, 1, date("n"), $dayNum, date("Y"));
		$endMemoTimeStamp = mktime (23,59,59, date("n"), $dayNum, date("Y"));

		$dayText = date("D", $beginMemoTimeStamp);

		if($day == 0) {
			$dayText = "Today";
			$dayExt  = "";
		}
		else if($day == 1) {
			$dayText = "Yesterday";
			$dayExt  = "";
		}
		else if(($day) % 7 == 0) {
			$dayExt = floor(($day+1)/7) ." wk";
		}
		else if(($day - 1) % 7 == 0) {
			$dayExt = floor(($day+1)/7) ." wk yst";
		}
		else {
			$dayExt = "";
		}

		$t->set_var("DAY_DESC", $dayText . " " . $dayExt);
		$t->set_var("START_MEMO_TIME", $beginMemoTimeStamp);
		$t->set_var("END_MEMO_TIME", $endMemoTimeStamp);


		

		// Get the counts of the given Memo Types (in the array) between the start and end times.
		$sMCCountArr = CustomerMemos::getMemoCountArrByTimeRange($dbCmd, array("T","W","S","P","V","D"), $beginMemoTimeStamp ,$endMemoTimeStamp);
		
		$t->set_var(array(
			"MEM_NOT_INT"=>  $sMCCountArr["T"],
			"MEM_MESG"=>     $sMCCountArr["S"],
			"MEM_WARM"=>     $sMCCountArr["W"],
			"MEM_LOOK_GOOD"=>$sMCCountArr["P"],
			"MEM_VERY_INT"=> $sMCCountArr["V"],
			"MEM_CLOSED"=>   $sMCCountArr["D"]
			));


		$t->parse("MemoRowBLout","MemoRowBL",true);
		
		
		// Eventually, this variable will contain the BeginTimeStamp value of the last iteration of the loop
		$firstMemoTimeStamp = $beginMemoTimeStamp;	
	}
		
	// We are also measuring "total memo counts" up until the present.
	$lastMemoTimeStamp = time();

	// Totals line
	$t->set_var("TOT_START_MEMO_TIME", $firstMemoTimeStamp);
	$t->set_var("TOT_END_MEMO_TIME",   $lastMemoTimeStamp);	


	$sMCCountArr = CustomerMemos::getMemoCountArrByTimeRange($dbCmd, array("T","W","S","P","V","D"), $firstMemoTimeStamp, $lastMemoTimeStamp);
	

	$t->set_var("TOT_MEM_NOT_INT",  $sMCCountArr["T"]);
	$t->set_var("TOT_MEM_MESG",     $sMCCountArr["S"]);
	$t->set_var("TOT_MEM_WARM",     $sMCCountArr["W"]);
	$t->set_var("TOT_MEM_LOOK_GOOD",$sMCCountArr["P"]);
	$t->set_var("TOT_MEM_VERY_INT", $sMCCountArr["V"]);
	$t->set_var("TOT_MEM_CLOSED",   $sMCCountArr["D"]);



}
else{
	$t->discard_block("origPage", "MemosCountBL");

}




if(!$AuthObj->CheckIfBelongsToGroup("FRANCHISE_OWNER"))
	$t->discard_block("origPage", "MonthReportLinkBL");
if(!$AuthObj->CheckIfBelongsToGroup("FRANCHISE_OWNER"))
	$t->discard_block("origPage", "InvoicesLinkBL");

	
	

// Check to see if there are shipments waiting from UPS to be imported
$numberToImport = ShippingTransfers::CheckIfImportIsEmpty();
if($numberToImport > 0 && $AuthObj->CheckForPermission("IMPORT_SHIPMENTS"))
	$t->set_var("IMPORT_SHIPMENTS", "<br><a href='./ad_openorders.php?form_sc=".WebUtil::getFormSecurityCode()."&action=importshipments&returl=ad_home.php'  class='BlueRedLink'>Complete Orders ($numberToImport)</a><br>");
else
	$t->set_var("IMPORT_SHIPMENTS", "");

$t->allowVariableToContainBrackets("IMPORT_SHIPMENTS");




// Output compressed HTML
WebUtil::print_gzipped_page_start();

$t->pparse("OUT","origPage");

WebUtil::print_gzipped_page_end();






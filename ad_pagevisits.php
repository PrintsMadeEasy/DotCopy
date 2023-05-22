<?

require_once("library/Boot_Session.php");


// Don't make it look like we are favoring a particular Domain 
Domain::removeTopDomainID();

$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


$LimitResults = WebUtil::GetInput("limitresults", FILTER_SANITIZE_INT, "40");
$PageName = WebUtil::GetInput("pagename", FILTER_SANITIZE_STRING_ONE_LINE, "all");
$userOverride = WebUtil::GetInput("userOverride", FILTER_SANITIZE_INT);




// Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();



$superAdmingIDs = $AuthObj->GetUserIDsInGroup(array("MEMBER", "SUPERADMIN"));
$adminDArr = $AuthObj->GetUserIDsInGroup(array("MEMBER", "ADMIN"));
$productionDArr = $AuthObj->GetUserIDsInGroup(array("MEMBER", "PRODUCTION"));
$superCSArr = $AuthObj->GetUserIDsInGroup(array("MEMBER", "SUPERCS"));
$CSArr = $AuthObj->GetUserIDsInGroup(array("MEMBER", "CS"));
$vendorArr = $AuthObj->GetUserIDsInGroup(array("MEMBER", "VENDOR"));

$superAdmingIDs = UserControl::filterUserIDsNotInUserDomainPool($dbCmd, $UserID, $superAdmingIDs);
$adminDArr = UserControl::filterUserIDsNotInUserDomainPool($dbCmd, $UserID, $adminDArr);
$productionDArr = UserControl::filterUserIDsNotInUserDomainPool($dbCmd, $UserID, $productionDArr);
$superCSArr = UserControl::filterUserIDsNotInUserDomainPool($dbCmd, $UserID, $superCSArr);
$CSArr = UserControl::filterUserIDsNotInUserDomainPool($dbCmd, $UserID, $CSArr);
$vendorArr = UserControl::filterUserIDsNotInUserDomainPool($dbCmd, $UserID, $vendorArr);


if($AuthObj->CheckForPermission("PAGE_VISITS_USER_OVERRIDE") && !empty($userOverride)){
	
	$domainIDofUser = UserControl::getDomainIDofUser($userOverride);
	
	if(!in_array($domainIDofUser, $AuthObj->getUserDomainsIDs()))
		throw new Exception("This User is not Available.");

	// Make sure that Admins can't view other Admins... or SuperAdmins.
	if($AuthObj->CheckIfBelongsToGroup("SUPERADMIN")){
		if(in_array($userOverride, $superAdmingIDs))
			throw new Exception("You can't view the Page Visits for another Super Admin");
	}
	else if($AuthObj->CheckIfBelongsToGroup("ADMIN")){
		if(in_array($userOverride, $superAdmingIDs) || in_array($userOverride, $adminDArr))
			throw new Exception("You can't view the Page Visits for another Admin");
	}

	$userIDforPageVisits = $userOverride;
}
else{
	$userIDforPageVisits = $UserID;
}
	


$t = new Templatex(".");

$t->set_file("origPage", "ad_pagevisits-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());



$navHistoryObj = new NavigationHistory($dbCmd);

$limitSelectHTML = Widgets::buildSelect(array("50"=>"50", "100"=>"100", "200"=>"200", "500"=>"500", "1000"=>"1000"), $LimitResults);

$pagesSelectHTML = Widgets::buildSelect(array_merge(array("all"=>"All"), $navHistoryObj->getArrayNavPages()), $PageName);


$t->set_var("NUM_RESULTS_SEL", $limitSelectHTML);
$t->set_var("PAGE_NAMES_SEL", $pagesSelectHTML);
$t->allowVariableToContainBrackets("NUM_RESULTS_SEL");
$t->allowVariableToContainBrackets("PAGE_NAMES_SEL");


$pageVisitsItemsArr = $navHistoryObj->getLastPageVisitsIDs($userIDforPageVisits, $LimitResults, $PageName);


if(empty($pageVisitsItemsArr)){
	$t->set_var("EMPTY_RESTUTS_MSG", "No Page History Yet.");
	$t->discard_block("origPage","EmptyResultsBL");
}
else{

	$t->set_var("EMPTY_RESTUTS_MSG", "");

	
	$t->set_block("origPage","itemsBL","itemsBLout");
	
	// Find out what was the time at midnight... so we can figure out what "yesterday" means
	$LastMidnightTimeStamp = mktime(0, 0, 1, date('n'), date("j"), date('Y'));
	
	$lastDay = null;
	
	foreach($pageVisitsItemsArr as $thisPageVisitID){
		
		$navHistoryObj->loadNavHistoryID($thisPageVisitID);
		
		$pageLink = $navHistoryObj->getHyperLinkOfPageVisit();
		$pageDescription = $navHistoryObj->getDescriptionOfPageVisit();
		$timestamp = $navHistoryObj->getTimeStampOfVisit();

		// Show an blank Row if the Days Change.
		if(empty($lastDay))
			$lastDay = date("D", $timestamp);
		
		if($lastDay != date("D", $timestamp)){
			
			$lastDay = date("D", $timestamp);
		
			// Find out how many days ago this was
			$daysAgo = ceil(($LastMidnightTimeStamp - $timestamp) / (60 * 60 * 24));
			
			if($daysAgo == 1)
				$pastDescription = "(Yesterday)";
			else
				$pastDescription = "<br>(" . $daysAgo . " days ago)";
		
			$t->set_var("DESC", "&nbsp;");
			$t->set_var("DATE", "&nbsp;<br><font color='#990000'><b>" . date("l", $timestamp) . "</b> $pastDescription</font>");
			
			$t->allowVariableToContainBrackets("DATE");
			$t->allowVariableToContainBrackets("DESC");
			
			$t->parse("itemsBLout","itemsBL",true);
		}



		// There might not be a hyper link associated with the Page
		if(!empty($pageLink))
			$pageHistoryHTML = "<a href='$pageLink' class='adminOrderLink2'>" . WebUtil::htmlOutput($pageDescription) . "</a><br>";
		else
			$pageHistoryHTML = WebUtil::htmlOutput($pageDescription) . "<br>";
	
		$t->set_var("DESC", $pageHistoryHTML);
		$t->set_var("DATE", date("D m/d g:i a", $timestamp));
		$t->allowVariableToContainBrackets("DESC");
		
		$t->parse("itemsBLout","itemsBL",true);	
	}
}


if($AuthObj->CheckForPermission("PAGE_VISITS_USER_OVERRIDE")){
	
	$userIDsArr = array();

	if($AuthObj->CheckIfBelongsToGroup("SUPERADMIN")){

		$userIDsArr = array_unique(array_merge($adminDArr, $productionDArr, $superCSArr, $CSArr, $vendorArr));
		
		WebUtil::array_delete($userIDsArr, $superAdmingIDs);
	}
	else if($AuthObj->CheckIfBelongsToGroup("ADMIN") || $AuthObj->CheckIfBelongsToGroup("PRODUCT_MANAGER")){

		$userIDsArr = array_unique(array_merge($superCSArr, $CSArr));
		
		WebUtil::array_delete($userIDsArr, $adminDArr);
		WebUtil::array_delete($userIDsArr, $superAdmingIDs);
	}

	
	$optionList = array("0" => "Myself");
	
	foreach($userIDsArr as $thisID)
		$optionList[$thisID] = WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, $thisID));
	
	$t->set_var("USER_DROP_DOWN", Widgets::buildSelect($optionList, $userIDforPageVisits));
	$t->allowVariableToContainBrackets("USER_DROP_DOWN");
	
}
else{
	$t->discard_block("origPage","UserOverrideBL");
}


$t->pparse("OUT","origPage");


?>
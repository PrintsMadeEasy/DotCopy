<?

require_once("library/Boot_Session.php");

// Don't make it look like we are favoring a particular Domain 
Domain::removeTopDomainID();

$email = WebUtil::GetInput("email", FILTER_SANITIZE_EMAIL);
$username = WebUtil::GetInput("username", FILTER_SANITIZE_STRING_ONE_LINE);
$phone = WebUtil::GetInput("phone", FILTER_SANITIZE_STRING_ONE_LINE);
$customerid = WebUtil::GetInput("customerid", FILTER_SANITIZE_INT);
$similarToCustomerID = WebUtil::GetInput("similarToCustomerID", FILTER_SANITIZE_INT);
$offset = WebUtil::GetInput("offset", FILTER_SANITIZE_INT);



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();
$dbCmd3 = new DbCmd();

$domainObj = Domain::singleton();



$PaymentInvoiceObj = new PaymentInvoice();

// Sometimes the UserID has a U prefix.
$customerid = preg_replace("/u/i", "", $customerid);

WebUtil::EnsureDigit($customerid, false);



$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("USER_SEARCH"))
	WebUtil::PrintAdminError("Not Available");


// If this person is a vendor then we want to restrict the users to them.
if($AuthObj->CheckIfBelongsToGroup("VENDOR"))
	$VendorRestriction = $UserID;
else
	$VendorRestriction = "";



// Strip out any dashes, paranthesis, etc
if(!empty($phone))
	$phoneSearch = WebUtil::FilterPhoneNumber($phone);
else 
	$phoneSearch = $phone;





// Find out if we are doing a search... if all of our search parameters are empty then we are not searching
if(empty($email) && empty($phoneSearch) && empty($username) && empty($customerid) && empty($similarToCustomerID))
	$SearchingFlag = false;
else
	$SearchingFlag = true;
	
	
if($SearchingFlag){
	if(empty($email) && empty($phoneSearch) && empty($customerid) && empty($similarToCustomerID)){
		$userNameTemp = $username;
		$userNameTemp = preg_replace("/\s/", "", $userNameTemp);
		$userNameTemp = preg_replace("/\\*/", "", $userNameTemp);
		if(strlen($userNameTemp) < 3)
			WebUtil::PrintAdminError("If you are doing a search for users you must use at least 3 characters. Otherwise too many search results will be returned.");
	}
}



// Keep a record of what people are searching for.
if(!empty($customerid))
	NavigationHistory::recordPageVisit($dbCmd, $UserID, "UserID", $customerid);
if(!empty($email))
	NavigationHistory::recordPageVisit($dbCmd, $UserID, "UserEmail", $email);
if(!empty($phoneSearch))
	NavigationHistory::recordPageVisit($dbCmd, $UserID, "UserPhone", $phoneSearch);
if(!empty($username))
	NavigationHistory::recordPageVisit($dbCmd, $UserID, "UserName", $username);



//Setup variable
$NumberOfResultsToDisplay = 150;

$t = new Templatex(".");

$t->set_file("origPage", "ad_users_search-template.html");

// Get the Header HTML
$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$t->set_block("origPage","usersBL","usersBLout");


$phoneSearchSQL = DbCmd::EscapeLikeQuery($phoneSearch);
$phoneSearchSQL = preg_replace("/\*/", "%", $phoneSearchSQL);

$companyNameSQL = "";
$usernameSQL = "";

// If the User Name search begins with "c:" ... then it means we want to search by company name instead of by personal name.
if(preg_match("/^c:/i", $username)){
	$companyNameSQL = preg_replace("/^c:/i", "", $username);
	$companyNameSQL = DbCmd::EscapeLikeQuery($companyNameSQL);
	$companyNameSQL = preg_replace("/\*/", "%", $companyNameSQL);
}
else if(!empty($username)){
	$usernameSQL = DbCmd::EscapeLikeQuery($username);
	$usernameSQL = preg_replace("/\*/", "%", $usernameSQL);
}


// Consturct an SQL query based on the parameters that come in the URL
$query = "SELECT ID, Email, Name, Company, Address, AddressTwo, City, 
		State, Zip, Country, Phone, UNIX_TIMESTAMP(DateCreated) AS DateCreated, DomainID, 
		UNIX_TIMESTAMP(DateLastUsed) AS DateLastUsed, Password, AffiliateDiscount, 
		AccountType, BillingType, SalesRep, UNIX_TIMESTAMP(SalesRepExpiration) AS SalesRepExpiration FROM users WHERE ID > 0 ";   //The WHERE clause here doesnt do much but it makes life easier below to create the dynamic query.
if(!empty($customerid))
	$query .= "AND ID=$customerid ";
if(!empty($email))
	$query .= "AND Email LIKE '" . DbCmd::EscapeSQL($email) . "' ";
if(!empty($phoneSearch))
	$query .= "AND PhoneSearch LIKE '" . $phoneSearchSQL . "' ";
if(!empty($usernameSQL))
	$query .= "AND (Name LIKE '" . $usernameSQL . "') ";
if(!empty($companyNameSQL))
	$query .= "AND (Company LIKE '" . $companyNameSQL . "') ";
if(!empty($similarToCustomerID)){
	$similarIDsArr = UserControl::GetSimilarCustomerIDsByUserID($dbCmd, $similarToCustomerID);
	$orClause = "";
	foreach($similarIDsArr as $thisUserID)
		$orClause .= " ID=$thisUserID OR";
	$orClause = substr($orClause, 0, -2);
	$query .= "AND ($orClause) ";
}


// In case we don't have any search criteria.... then make the query do something ridiculous so that no results are found.  Otherwise it would find everything
if(empty($SearchingFlag))
	$query .= " AND ID=234234234234 ";

	
$query .= " AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs());
	
$query .= "ORDER BY DateCreated DESC";

$dbCmd->Query($query);

$totalQueryResults = $dbCmd->GetNumRows();

$empty_users = true;

$resultCounter = 0;

while($row = $dbCmd->GetRow()){
	
	$CustomerID = $row["ID"];

	//If this person is a vendor then searching for users will take a little bit longer
	if(!empty($VendorRestriction)){
		
		$dbCmd2->Query("SELECT COUNT(*) FROM projectsordered INNER JOIN orders ON projectsordered.OrderID = orders.ID 
				WHERE (" . Product::GetVendorIDLimiterSQL($UserID) . ") AND orders.UserID=" . $CustomerID);

		$OrderCount = $dbCmd2->GetValue();

		// If their are no projects for this vendor, belonging to this customer ID.. then don't give the vendor permissions to see
		if($OrderCount == 0)
			continue;
	}



	// If we have many matches we only want to display those which are on the current page.  This is figured out by the offset parameter passed in the URL.
	if(($resultCounter >= $offset) && ($resultCounter < ($NumberOfResultsToDisplay + $offset))){
	
		$CustomerEmail = $row["Email"];
		$UserFullName = $row["Name"];
		$Company = $row["Company"];
		$UserAddress = $row["Address"];
		$UserAddress2 = $row["AddressTwo"];
		$City = $row["City"];
		$State = $row["State"];
		$Zip = $row["Zip"];
		$Country = $row["Country"];
		$PhoneNum = $row["Phone"];
		$DateCreated = $row["DateCreated"];
		$DateLastUsed = $row["DateLastUsed"];
		$Password = $row["Password"];
		$AffiliateDiscount = $row["AffiliateDiscount"];
		$AccountType = $row["AccountType"];
		$BillingType = $row["BillingType"];
		$SalesRepID = $row["SalesRep"];
		$SalesRepExpiration = $row["SalesRepExpiration"];
		$domainIDofUser = $row["DomainID"];

		
		
		$empty_users = false;

		$UserFullName = WebUtil::htmlOutput($UserFullName);
		if(!empty($Company))
			$UserFullName .= "<br><b>" . WebUtil::htmlOutput($Company) . "</b>";

		
		// Concatenate the Address 2 field with a comma
		if(!empty($UserAddress2))
			$UserAddress .= ", " . $UserAddress2;


		$DateCreated = date("M j, Y", $DateCreated);
		$DateLastUsed = date("M j, Y", $DateLastUsed);


		$t->set_var("NAME_AND_COMPANY", $UserFullName);
		$t->allowVariableToContainBrackets("NAME_AND_COMPANY");
		
		$t->set_var("ADDRESS", WebUtil::htmlOutput($UserAddress));
		$t->set_var("CITY", WebUtil::htmlOutput($City));
		$t->set_var("STATE", WebUtil::htmlOutput($State));
		$t->set_var("ZIP", WebUtil::htmlOutput($Zip));
		$t->set_var("PHONE", WebUtil::htmlOutput($PhoneNum));
		$t->set_var("CREATED", $DateCreated);
		$t->set_var("LASTUSED", $DateLastUsed);
		$t->set_var("CUSTOMERID", $CustomerID);
		$t->set_var("COUNTRY", WebUtil::htmlOutput(Status::GetCountryByCode($Country)));
		
		// Only show the Domain ICON if the user has more than 1 domain selected.
		if(sizeof($domainObj->getSelectedDomainIDs()) > 1){
			$domainLogoObj = new DomainLogos($domainIDofUser);
			$t->set_var("DOMAIN_SICON", "<img alt='".Domain::getDomainKeyFromID($domainIDofUser)."'  src='./domain_logos/$domainLogoObj->verySmallIcon'>");
			$t->allowVariableToContainBrackets("DOMAIN_SICON");
		}
		else{
			$t->set_var("DOMAIN_SICON", "");
		}
		
		
		if(!empty($VendorRestriction))
			$t->set_var("EMAIL", "");
		else
			$t->set_var("EMAIL", WebUtil::htmlOutput($CustomerEmail));
		
		
		//Make sure if they have permissions to see other's password..
		//Also... we should not be able to view passwords of other members
		if($AuthObj->CheckForPermission("VIEW_LOGIN_LINK") && !$AuthObj->CheckIfUserIDisMember($CustomerID)){
			
			$domainWebsiteURL = Domain::getWebsiteURLforDomainID($domainIDofUser);
			
			// An extra urlencode is needed for the login link since it will be traveling though the <a href="javascript">
			$t->set_var(array("LOGIN_LINK"=>urlencode("https://$domainWebsiteURL/signin_xml.php?redirect=home&email=".WebUtil::htmlOutput($CustomerEmail)."&pwe=" . md5($Password . Authenticate::getSecuritySaltBasic()))));
			$t->set_var(array("HIDELOGIN_START"=>"", "HIDELOGIN_END"=>""));
		}
		else{
			$t->set_var(array("LOGIN_LINK"=>""));
			$t->set_var(array("HIDELOGIN_START"=>" <!-- ", "HIDELOGIN_END"=>" --> "));
			
		}
		
		$t->allowVariableToContainBrackets("LOGIN_LINK");
		$t->allowVariableToContainBrackets("HIDELOGIN_START");
		$t->allowVariableToContainBrackets("HIDELOGIN_END");


		//See if they have permission view account Details
		if($AuthObj->CheckForPermission("CUSTOMER_ACCOUNT")){
		
			if($BillingType == "N")
				$AccountDesc = "(normal)";
			else
				$AccountDesc = "(<b>Corporate</b>)";
				
			if($AccountType == "R")
				$AccountDesc .= "<br><b>Reseller</b>"; 
		
			$t->set_var(array("ACCOUNT"=>"<br><a href=\"./ad_users_account.php?returl=" . urlencode(WebUtil::FilterURL($_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING'])) . "&retdesc=User+Search+Page&view=general&customerid=$CustomerID\" class='BlueRedLink'>Account $AccountDesc</a>"));
			
			$t->allowVariableToContainBrackets("ACCOUNT");
		}
		else
			$t->set_var(array("ACCOUNT"=>""));
		
		
		// Find out if the user is a Sales Rep
		$dbCmd2->Query("SELECT COUNT(*) FROM salesreps WHERE UserID=$CustomerID");
		$IsSalesRep = $dbCmd2->GetValue();
		if($IsSalesRep){
			$salesRepObj = new SalesRep($dbCmd2);
			$salesRepObj->LoadSalesRep($CustomerID);
			$salesRepParentName = ($salesRepObj->getParentSalesRep() == 0) ? "Root" : (WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd2, $salesRepObj->getParentSalesRep())));
			
			if($AuthObj->CheckForPermission("VIEW_SALESREP_PERCENTAGES"))
				$salesRepPercentage = " at " . $salesRepObj->getCommissionPercent() . "%";
			else
				$salesRepPercentage = "";
			
			if($salesRepObj->getParentSalesRep() == 0)
				$linkToParent = "";
			else
				$linkToParent = "<a href='./ad_users_search.php?customerid=" . $salesRepObj->getParentSalesRep() . "'><img src='./images/arrow-button-right-up-u.png' onMouseOver='this.src=\"./images/arrow-button-right-up-d.png\";' onMouseOut='this.src=\"./images/arrow-button-right-up-u.png\";' border='0' align='absmiddle'></a>";
			
			$t->set_var("SALESREP", "<br><font class='reallysmallbody'><font color='#cc3366'>User is a Sales Rep" . $salesRepPercentage . "<br><nobr>Parent:<font color='#333333'> $salesRepParentName $linkToParent</font></nobr></font></font>");
			$t->allowVariableToContainBrackets("SALESREP");
		}
		else{
			$t->set_var("SALESREP", "");
		}
		
		
		// Find out if the user Belongs to a Sales Rep
		if(!empty($SalesRepID)){
			
			$SalesRepName = WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd2, $SalesRepID));
			
			// If the sales rep has no expiration then the time is NULL... in which the UnixTimestamp will be far in the past.
			if($SalesRepExpiration < time() && $SalesRepExpiration > (mktime(1, 1, 1, 1, 1, 2000)))
				$SalesRepDesc = "Expired Sales Rep";
			else
				$SalesRepDesc = "Belongs to Sales Rep";
				
			$t->set_var("BELONGS_TO_REP", "<br><font class='reallysmallbody'><font color='#cc3333'>$SalesRepDesc:</font> $SalesRepName</font>");
			$t->allowVariableToContainBrackets("BELONGS_TO_REP");
		}
		else{
			$t->set_var("BELONGS_TO_REP", "");
		}
			
		
		
		
		
		//See if they have permission to issue coupons for users
		if($AuthObj->CheckForPermission("COUPONS_VIEW"))
			$t->set_var(array("COUPON_ACTIVATION"=>"<a href=\"javascript:CouponActivation('$CustomerID');\" class='BlueRedLink'>Coupon Activation</a>"));
		else
			$t->set_var(array("COUPON_ACTIVATION"=>""));
		
		$t->allowVariableToContainBrackets("COUPON_ACTIVATION");
		
		
		if($AffiliateDiscount == "")
			$AffiliateDiscount = 0;
		else
			$AffiliateDiscount = round($AffiliateDiscount * 100);
		
		
		if($AuthObj->CheckForPermission("CUSTOMER_DISCOUNT")){
			if($AffiliateDiscount <> 0)
				$t->set_var(array("DISCOUNT"=>"<br><b><a href='javascript:CustDiscount($CustomerID)' class='BlueRedLink'>Discount - $AffiliateDiscount%</a></b>"));
			else
				$t->set_var(array("DISCOUNT"=>"<br><a href='javascript:CustDiscount($CustomerID)' class='BlueRedLink'>Discount</a>"));
		}
		else{
			$t->set_var(array("DISCOUNT"=>""));
		}
		
		$t->allowVariableToContainBrackets("DISCOUNT");
		
		$ProjectCount = Order::GetProjectCountByUser($dbCmd2, $CustomerID, $VendorRestriction);
		
		

		if($AuthObj->CheckForPermission("VIEW_CUSTOMER_SERVICE"))
			$CustomerServiceLink = "<br>" . CustService::GetCustServiceLinkForUser($dbCmd2, $CustomerID, false);
		else
			$CustomerServiceLink = "";
		
		
		// Show them a link into the users Saved Projets
		$TotalSavedProjects = ProjectSaved::GetCountOfSavedProjects($dbCmd2, $CustomerID);
		if($AuthObj->CheckForPermission("SAVED_PROJECT_OVERRIDE"))
			$SavedProjectsLink = "<br><a class='BlueRedLink' href='javascript:SaveProj(\"".Domain::getWebsiteURLforDomainID(Domain::getDomainIDfromURL())."\", $CustomerID);'>Saved Projects ($TotalSavedProjects)</a>";
		else
			$SavedProjectsLink = "";


		$t->set_var("SAVED_PROJECTS_LINK", $SavedProjectsLink);
		$t->set_var("CUSTOMER_SERVICE_LINK", $CustomerServiceLink);
		$t->set_var("PROJECT_COUNT", $ProjectCount);
		
		$t->allowVariableToContainBrackets("CUSTOMER_SERVICE_LINK");
		$t->allowVariableToContainBrackets("SAVED_PROJECTS_LINK");
		
		
		
		$PaymentInvoiceObj->LoadCustomerByID($CustomerID);
		
		$CreditUsageByCustomer = $PaymentInvoiceObj->GetCurrentCreditUsage();
		
		$CustomerCredit = "";
		
		// If the credit Usage is negative... then it means that they have a postitive balance.
		// In this case show a signal that they have credit with us.
		if($CreditUsageByCustomer < 0)
			$CustomerCredit = "<font color='#006600'><b>Credit: \$" . abs($CreditUsageByCustomer) . "</b></font>";
		
		// If they have permissions to view customer account information... then the Customer Credit link should go into the corporate billing section.
		if(!empty($CustomerCredit) && $AuthObj->CheckForPermission("CUSTOMER_ACCOUNT")){
			$CustomerCredit = "<a href=\"./ad_users_account.php?&returl=" . urlencode(WebUtil::FilterURL($_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING'])) . "&retdesc=User+Search+Page&view=billinghistory&customerid=$CustomerID\" class='BlueRedLink'>" . $CustomerCredit . "</a>";
		}

		
		if(!empty($CustomerCredit))
			$CustomerCredit = "<br>" . $CustomerCredit;
			

		$t->set_var("OFFLINE_PAYMENTS", $CustomerCredit);
		
		$t->allowVariableToContainBrackets("OFFLINE_PAYMENTS");
		
		
		if($totalQueryResults < 30){
		
			$custUserControlObj = new UserControl($dbCmd2);
			$custUserControlObj->LoadUserByID($CustomerID);

			$customerRating = $custUserControlObj->getUserRating();

			$custRatingImgHTML = "<img src='./images/star-rating-" . $customerRating . ".gif' width='74' height='13'>";

			if($customerRating > 0)
				$customerRating = "<div id='Dcust" . $CustomerID . "' style='display: inline; cursor: hand; width:74px;' class='hiddenDHTMLwindow' onMouseOver='CustInf($CustomerID, ". $CustomerID .", true, \"".WebUtil::getFormSecurityCode()."\")' onMouseOut='CustInf($CustomerID, ". $CustomerID .", false, \"".WebUtil::getFormSecurityCode()."\")'>$custRatingImgHTML<span class='hiddenDHTMLwindow' style='visibility:hidden; left:75px; top:-15' id='Scust" . $CustomerID . "'></span></div>";
			else
				$customerRating = $custRatingImgHTML;
			
			$t->set_var("CUST_RATE", "<br>" . $customerRating);
			
			$t->allowVariableToContainBrackets("CUST_RATE");
		}
		else{
			$t->set_var("CUST_RATE", "");
		}

		
		if($AuthObj->CheckForPermission("CHAT_SEARCH")){
			
			$userChatCount = ChatThread::getChatCountByUser($dbCmd2, $CustomerID);
			
			if($userChatCount > 0)
				$t->set_var("USER_CHATS", "&nbsp;&nbsp;<a href='ad_chat_search.php?user_id=$CustomerID' class='BlueRedLinkRecSM'>Chats: $userChatCount</a>");
			else
				$t->set_var("USER_CHATS", "");
			
			$t->allowVariableToContainBrackets("USER_CHATS");
		}
		else{
			$t->set_var("USER_CHATS", "");
		}
		
		
		
		
		
		$similarAccounts = UserControl::GetCountOfSimlarAccountsToUserID($dbCmd2, $CustomerID);
		if($similarAccounts)
			$t->set_var("SIMILAR_ACCOUNTS", "<br><a href='./ad_users_search.php?similarToCustomerID=$CustomerID'><font style='font-size:16px; font-weight:bold;' color='#cc0000'>" . ($similarAccounts + 1) . " Similar Accounts</font></a>");
		else
			$t->set_var("SIMILAR_ACCOUNTS", "");

		$t->allowVariableToContainBrackets("SIMILAR_ACCOUNTS");


		// For Tasks
		$taskCollectionObj = new TaskCollection();
		$taskCollectionObj->limitAttachedToName("user");
		$taskCollectionObj->limitAttachedToID($CustomerID);
		$taskCollectionObj->limitUserID($UserID);
		$taskCount = $taskCollectionObj->getAttachedToRecordCount();
			
		if($taskCount == 0)
			$t->set_var("T_COUNT", "");
		else
			$t->set_var("T_COUNT", "(<b>$taskCount</b>)");


		$t->allowVariableToContainBrackets("T_COUNT");

		$t->set_var("MEMO", CustomerMemos::getLinkDescription($dbCmd2, $CustomerID, false));
		$t->allowVariableToContainBrackets("MEMO");

		$t->parse("usersBLout","usersBL",true);
	}

	$resultCounter++;
}






// Set this block so that we can erase the multi-page navigation bar if there aren't multiple pages 
$t->set_block("origPage","MultiPageBL","MultiPageBLout");
$t->set_block("origPage","SecondMultiPageBL","SecondMultiPageBLout");

// This means that we have multiple pages of search results
if($resultCounter > $NumberOfResultsToDisplay){


	// What are the name/value pairs AND URL  for all of the subsequent pages 
	$NV_pairs_URL = "email=$email&username=$username&";
	$BaseURL = "./ad_users_search.php";

	// Get a the navigation of hyperlinks to all of the multiple pages 
	$NavigateHTML = Navigation::GetNavigationForSearchResults($BaseURL, $resultCounter, $NV_pairs_URL, $NumberOfResultsToDisplay, $offset);

	$t->set_var(array("NAVIGATE"=>$NavigateHTML, "RESULT_DESC"=>$resultCounter, "OFFSET"=>$offset));
	$t->allowVariableToContainBrackets("NAVIGATE");
	
	$t->parse("MultiPageBLout","MultiPageBL",true);
	$t->parse("SecondMultiPageBLout","SecondMultiPageBL",true);
}
else{
	$t->set_var(array("NAVIGATE"=>""));
	$t->set_var(array("MultiPageBLout"=>""));
	$t->set_var(array("SecondMultiPageBLout"=>""));
}


if($empty_users){
	if($SearchingFlag)
		$t->set_var("usersBLout", "No users found.<br><br>");
	else
		$t->set_var("usersBLout", "");
}





if(!empty($customerid)){
	
	$t->set_var(array("USER_ID"=>"U" . $customerid));

	$domainIDofUser = UserControl::getDomainIDofUser($customerid);
	if(!$AuthObj->CheckIfUserCanViewDomainID($domainIDofUser))
		throw new Exception("The User ID does not exist.");
	
		
	$taskCollectionObj = new TaskCollection();
	$taskCollectionObj->limitShowTasksBeforeReminder(true);
	$taskCollectionObj->limitUserID($UserID);
	$taskCollectionObj->limitAttachedToName("user");
	$taskCollectionObj->limitAttachedToID($customerid);
	$taskCollectionObj->limitUncompletedTasksOnly(true);
	$taskObjectsArr = $taskCollectionObj->getTaskObjectsArr();
	
	$tasksDisplayObj = new TasksDisplay($taskObjectsArr);
	$tasksDisplayObj->setTemplateFileName("tasks-template.html");
	$tasksDisplayObj->setReturnURL("ad_users_search.php?customerid=" . $customerid);
	$tasksDisplayObj->displayAsHTML($t);		

		
	

	// Find out if any messages are linked to this User
		
	$t->set_block("origPage","MessageThreadBL","MessageThreadBLout");

	// Extract Inner HTML blocks out of the Block we just extracted.
	$t->set_block ( "MessageThreadBL", "CloseMessageLinkBL", "CloseMessageLinkBLout" );
	
	$messageThreadCollectionObj = new MessageThreadCollection();	
	
	$messageThreadCollectionObj->setUserID($UserID);
	$messageThreadCollectionObj->setRefID($customerid);
	$messageThreadCollectionObj->setAttachedTo("user");
	
	$messageThreadCollectionObj->loadThreadCollection();
	
	$messageThreadCollection = $messageThreadCollectionObj->getThreadCollection();
	
	foreach ($messageThreadCollection as $messageThreadObj) {
		
		$messageThreadHTML = MessageDisplay::generateMessageThreadHTML($messageThreadObj);
				
		$t->set_var(array(
			"MESSAGE_BLOCK"=>$messageThreadHTML,
			"THREAD_SUB"=>WebUtil::htmlOutput($messageThreadObj->getSubject()),
			"THREADID"=>$messageThreadObj->getThreadID()
			));
			
		$t->allowVariableToContainBrackets("MESSAGE_BLOCK");

		// Discard the inner blocks (for the Close Message Link) if there are no unread messages.
		$unreadMessageIDs = $messageThreadObj->getUnReadMessageIDs();
		$t->set_var("UNREAD_MESSAGE_IDS", implode("|", $unreadMessageIDs));
		
		if(!empty($unreadMessageIDs))
			$t->parse("CloseMessageLinkBLout", "CloseMessageLinkBL", false );
		else
			$t->set_var("CloseMessageLinkBLout", "");		
				
		$t->parse("MessageThreadBLout","MessageThreadBL", true);	
	}	
	
	if(sizeof($messageThreadCollection) == 0)
		$t->discard_block("origPage", "HideMessagesBlock");
		
		
}
else{
	$t->set_var("USER_ID", "");
	$t->set_var("TASKS", "");
	$t->discard_block("origPage", "HideMessagesBlock");
}


if(!empty($email))
	$t->set_var(array("SEARCH_EMAIL"=>WebUtil::htmlOutput($email)));
else
	$t->set_var(array("SEARCH_EMAIL"=>""));

if(!empty($phone))
	$t->set_var(array("SEARCH_PHONE"=>WebUtil::htmlOutput($phone)));
else
	$t->set_var(array("SEARCH_PHONE"=>""));


if(!empty($username))
	$t->set_var(array("SEARCH_NAME"=>WebUtil::htmlOutput($username)));
else
	$t->set_var(array("SEARCH_NAME"=>""));



$t->set_var(array("WELCOME_MESSAGE"=>"Users"));


$t->pparse("OUT","origPage");



?>
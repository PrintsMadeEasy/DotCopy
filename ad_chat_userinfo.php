<?

require_once("library/Boot_Session.php");

$dbCmd = new DbCmd();

$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$chatID = WebUtil::GetInput("chat_id", FILTER_SANITIZE_INT);

$chatObj = new ChatThread();
$chatObj->loadChatThreadById($chatID);

$UserID = $AuthObj->GetUserID();

$customerUserID = $chatObj->getCustomerUserId();

if(!$AuthObj->CheckForPermission("CHAT_SYSTEM"))
	WebUtil::PrintAdminError("This URL is not available.");
	
if(empty($customerUserID))
	throw new Exception("The chat object doesn't have a User assigned.");


$t = new Templatex(".");
$t->set_file("origPage", "ad_chat_userinfo-template.html");

$custUserControlObj = new UserControl($dbCmd);
$custUserControlObj->LoadUserByID($customerUserID);

$userDisplay = WebUtil::htmlOutput($custUserControlObj->getCompanyOrName());


$t->set_var("CUSTOMER", $userDisplay);
$t->set_var("CUSTID", $customerUserID);



if($AuthObj->CheckForPermission("CUSTOMER_RATING_OPENORDERS")){

	$customerRating = $custUserControlObj->getUserRating();
	
	$custRatingImgHTML = "<img src='./images/star-rating-" . $customerRating . ".gif' width='74' height='13'>";

	if($customerRating > 0){

		$custRatingDivHTML = "<br><div id='Dcust" . $customerUserID . "' style='display:inline; cursor: hand; width:74px;' class='hiddenDHTMLwindow' onMouseOver='CustInf($customerUserID, $customerUserID, true, \"".WebUtil::getFormSecurityCode()."\")' onMouseOut='CustInf($customerUserID, $customerUserID, false, \"".WebUtil::getFormSecurityCode()."\")'>$custRatingImgHTML<span class='hiddenDHTMLwindow' style='visibility:hidden; left:75px; top:-15' id='Scust" . $customerUserID . "'></span></div><img src='./images/transparent.gif' width='10' height='1'>";
		
		$t->set_var("CUST_RATING", $custRatingDivHTML );

	}
	else{
		$t->set_var("CUST_RATING", "<br>" . $custRatingImgHTML . "<img src='./images/transparent.gif' width='10' height='1'>");
	}
}
else{
	$t->set_var("CUST_RATING", "");
}

$t->allowVariableToContainBrackets("CUST_RATING");






// For Tasks
$dbCmd->Query("SELECT COUNT(*) FROM tasks WHERE AttachedTo='user' AND RefID=$customerUserID");
$taskCount = $dbCmd->GetValue();
if($taskCount == 0)
	$t->set_var("T_COUNT", "");
else
	$t->set_var("T_COUNT", "(<b>$taskCount</b>)");

// For Memos
$t->set_var("MEMO", CustomerMemos::getLinkDescription($dbCmd, $customerUserID, true));

// For Customer Service
$t->set_var("CS", CustService::GetCustServiceLinkForUser($dbCmd, $customerUserID, true));

// For messages linked to this customer.
$t->set_var("MSG", "<a href=\"javascript:SendInternalMessage('user', $customerUserID, false);\" class='reallysmlllink'>Msg</a>");


$t->allowVariableToContainBrackets("T_COUNT");
$t->allowVariableToContainBrackets("MEMO");
$t->allowVariableToContainBrackets("CS");
$t->allowVariableToContainBrackets("MSG");





$t->pparse("OUT","origPage");


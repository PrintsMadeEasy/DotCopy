<?

require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


$customerUserID = WebUtil::GetInput("customerid", FILTER_SANITIZE_INT);

if(!$customerUserID)
	throw new Exception("Error with URL, no customer ID defined");

$refreshParent = WebUtil::GetInput("refreshParent", FILTER_SANITIZE_STRING_ONE_LINE);


$customerMemoObj = new CustomerMemos($dbCmd);
$customerMemoObj->setCustomerID($customerUserID);


$custUserObj = new UserControl($dbCmd);
$custUserObj->LoadUserByID($customerUserID);


// Keep a record of the visit to this page by the user.
NavigationHistory::recordPageVisit($dbCmd, $UserID, "Memo", $customerUserID);

$t = new Templatex(".");


$t->set_file("origPage", "ad_customermemos-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

// We want to reload the parent window after submitting a new message
$jsCommand = "";

if(WebUtil::GetInput("action", FILTER_SANITIZE_STRING_ONE_LINE) == "savemessage"){

	WebUtil::checkFormSecurityCode();
	
	$customerMemoObj->enterCustomerMemo(WebUtil::GetInput("message", FILTER_SANITIZE_STRING_ONE_LINE), $UserID, WebUtil::GetInput("reaction", FILTER_SANITIZE_STRING_ONE_LINE));
	
	// Clear out the lock when a memo gets saved.
	CustomerMemos::clearLockOnMemo($dbCmd, $customerUserID, $UserID);
	
	if($refreshParent)
		$jsCommand = "<script>window.opener.location = window.opener.location; self.close(); \n </script>";
	else
		$jsCommand = "<script>self.close();</script>";
	
}
else if(WebUtil::GetInput("action", FILTER_SANITIZE_STRING_ONE_LINE) == "removelock"){


	// When someone is going to close their browser without saving a message... javascript should fire off a request to have the lock cleared.
	CustomerMemos::clearLockOnMemo($dbCmd, $customerUserID, $UserID);


	header ("Content-Type: text/xml");
	// It seems that when you hit session_start it will send a Pragma: NoCache in the header
	// When comminicating over HTTPS there is a bug with IE.  It can not get the documents after they have finished downloading because they have already expired
	// We can allow the file to be cached be sending this header.  When requesting the document you can put a ?noache=timestamp
	header("Pragma: public");


	print "<?xml version=\"1.0\" ?>
		<response>
		<success>good</success>
		</response>";

	exit;
}
else{
	
	// Anytime someone opens a customer memo window they should aquire a read lock on it.
	CustomerMemos::lockMemoForViewing($dbCmd, $customerUserID, $UserID);
}




// Find out if 2 people are trying to view the Same Customer memo at the same time.
$lockedByUserIDsArr = CustomerMemos::getUserLocks($dbCmd, $customerUserID, $UserID);

if(empty($lockedByUserIDsArr)){
	$t->discard_block("origPage", "LockecMessageBL");
}
else{

	// Give a single Name or Multiple names
	if(sizeof($lockedByUserIDsArr) == 1){
		$lockedMessage = "<b>" . WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, current($lockedByUserIDsArr))) . "</b> is ";
	}
	else{
		
		$lockedMessage = "";
		
		$nameCounter = 1;
		
		foreach($lockedByUserIDsArr as $thisUserID){
			
			if($nameCounter == sizeof($lockedByUserIDsArr) && sizeof($lockedByUserIDsArr) == 2)
				$lockedMessage .= " and ";
			else if($nameCounter == sizeof($lockedByUserIDsArr))
				$lockedMessage .= ", and ";
			else if($nameCounter > 1)
				$lockedMessage .= ", ";
			
			$lockedMessage .= "<b>" . WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, $thisUserID)) . "</b>";
			
			$nameCounter++;
		}
	

		$lockedMessage .= " are ";
	}

	$t->set_var("LOCKED_MESSAGE", $lockedMessage);
	$t->allowVariableToContainBrackets("LOCKED_MESSAGE");
}


$custReactionDropDownArr = array();

$custServReacTypes = CustomerMemos::getCustomerServiceReactionTypes();
foreach($custServReacTypes as $thisReactionChar)
	$custReactionDropDownArr[$thisReactionChar] = CustomerMemos::getReactionDescription($thisReactionChar);

// Give the Sales Force people more choices for Memo Reactions.
if($AuthObj->CheckForPermission("MEMOS_SALES_FORCE_ADD")){

	$salesReacTypes = CustomerMemos::getSalesForceReactionTypes();
	foreach($salesReacTypes as $thisReactionChar)
		$custReactionDropDownArr[$thisReactionChar] = "Sales: " . CustomerMemos::getReactionDescription($thisReactionChar);
}


// Default to "No Reaction"
$t->set_var("CUST_REACTION", Widgets::buildSelect($custReactionDropDownArr, "N"));
$t->allowVariableToContainBrackets("CUST_REACTION");



// Format a Description of the User's Account
$custDetailsText = $custUserObj->getAccountDescriptionText(true);
$custDetailsArr = split("\n", $custDetailsText);
$custInfoDisplay = "";
foreach($custDetailsArr as $thisCustInfoLine){
	
	if(preg_match("/Shipping can Downgrade/i", $thisCustInfoLine))
		continue;
	if(preg_match("/Address Locked/i", $thisCustInfoLine))
		continue;

	$custInfoDisplay.= WebUtil::htmlOutput($thisCustInfoLine) . "<br>";
}

$t->set_var("CUST_INFO", $custInfoDisplay);
$t->allowVariableToContainBrackets("CUST_INFO");




$t->set_var("REFRESH_PARENT", $refreshParent);

$t->set_var("NAME_OR_COMPANY", WebUtil::htmlOutput(UserControl::GetCompanyOrNameByUserID($dbCmd, $customerUserID)));

$t->set_var("MEMOS", $customerMemoObj->getHTMLblockOfMemosForCustomer());
$t->allowVariableToContainBrackets("MEMOS");

$t->set_var("CUSTOMERID", $customerUserID);


$t->set_var("JAVASCRIPT_COMMAND", $jsCommand);
$t->allowVariableToContainBrackets("JAVASCRIPT_COMMAND");

$t->pparse("OUT","origPage");



?>
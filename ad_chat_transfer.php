<?

require_once("library/Boot_Session.php");


$threadId = WebUtil::GetInput("chatThread", FILTER_SANITIZE_NUMBER_INT);

$dbCmd = new DbCmd();

$domainObj = Domain::singleton();

// Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("CHAT_SYSTEM"))
	WebUtil::PrintAdminError("This URL is not available.");
	

$chatOjb = new ChatThread();
$chatOjb->loadChatThreadById($threadId);


$t = new Templatex(".");

$t->set_file("origPage", "ad_chat_transfer-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$t->set_block("origPage", "CsrBL", "CsrBLout");


$getCsrsOnline = ChatCSR::getCSRsOnline($chatOjb->getDomainId(), array());



foreach($getCsrsOnline as $thisCsrId){

	$chatCsrOjb = ChatCSR::singleton($thisCsrId);
	
	$t->set_var("CSR_REAL_NAME", WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, $thisCsrId)));
	$t->set_var("CSR_PEN_NAME", WebUtil::htmlOutput($chatCsrOjb->getPenName($chatOjb->getDomainId())));
	
	$csrStatus = "Available";
	if($chatCsrOjb->isCsrFull())
		$csrStatus = "Full";
		
	if($chatCsrOjb->getChatThreadsOpen() > $chatCsrOjb->getChatThreadLimit())
		$csrStatus = "Overloaded";
		
	$csrStatus .= " " . $chatCsrOjb->getChatThreadsOpen() . "/" . $chatCsrOjb->getChatThreadLimit();
		
	// Don't allow people to assign chat threads to themselves.  Also don't allow assignments after a chat thread has closed.
	if($chatOjb->getCsrUserId() == $thisCsrId || $chatOjb->isChatThreadClosed()){
		$t->set_var("ROW_CLASS", "assignedRow");
		$t->set_var("COMMAND", "");
	}
	else{
		$t->set_var("ROW_CLASS", "unassignedRow");
		$t->set_var("COMMAND", "<a href='javascript:AssignToCsr($thisCsrId);'>Assign</a>");
	}
	
	$t->allowVariableToContainBrackets("COMMAND");

	
	$t->set_var("CSR_STATUS", WebUtil::htmlOutput($csrStatus));
	

	$t->parse("CsrBLout", "CsrBL", true);
}


// If there are no CSR's online, show a message.
if(empty($getCsrsOnline)){
	$t->discard_block("origPage", "CSRresults");
}
else{
	$t->discard_block("origPage", "EmptyResults");
}

$t->pparse("OUT","origPage");






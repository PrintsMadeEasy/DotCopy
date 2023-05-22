<?

require_once("library/Boot_Session.php");



// Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("CHAT_SYSTEM"))
	WebUtil::PrintAdminError("This URL is not available.");

$dbCmd = new DbCmd();


$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$chatID = WebUtil::GetInput("chat_id", FILTER_SANITIZE_INT);
$fileID = WebUtil::GetInput("file_id", FILTER_SANITIZE_INT);


$chatOjb = new ChatThread();

$t = new Templatex(".");

$t->set_file("origPage", "ad_chat_thread-template.html");



if(!empty($action)){
	
	
	if($action == "reassign"){
		
		WebUtil::checkFormSecurityCode();
		
		$newCsrUserID = WebUtil::GetInput("csr_user_id", FILTER_SANITIZE_INT);
		
		$chatCsrObj = ChatCSR::singleton($newCsrUserID);
		if($chatCsrObj->isCsrOffline()){
			WebUtil::PrintAdminError("You can not assign a chat thread to a CSR who is offline.");
		}
		
		$chatOjb->loadChatThreadById($chatID);
		
		if($chatOjb->getStatus() == ChatThread::STATUS_Closed)
			WebUtil::PrintAdminError("You can not transfer the chat thread after the status has been closed.");
		
		$chatOjb->assignChatToCsr($newCsrUserID);
		
		NavigationHistory::recordPageVisit($dbCmd, $UserID, "ChatTrans", $chatID);
		NavigationHistory::recordPageVisit($dbCmd, $newCsrUserID, "ChatAssign", $chatID);
		
		WebUtil::SetSessionVar("LastChatTransfered", $chatID);
		
		session_write_close();
		header("Location: " . WebUtil::FilterURL("ad_chat_thread.php?chat_id=$chatID") . "&nocaching=" . time(), true, 302);
		exit;
	}
	else if($action == "download"){
		
		$chatOjb->loadChatThreadById($chatID);
		
		$binaryData = $chatOjb->getFileData($fileID);
		
		// Using "Content-Disposition" in the header forces the user to download the attachment instead of trying to render it within the browser.
	    header('Content-Description: File Transfer');
	    header('Content-Type: application/octet-stream');
		header('Content-Disposition: attachment; filename='.$chatOjb->getFileName($fileID));
	    header('Content-Transfer-Encoding: binary');
	    header('Expires: 0');
	    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
	    header('Pragma: public');
	    header('Content-Length: ' . strlen($binaryData));
		
		print $binaryData;
		exit;
	}
	else{
		throw new Exception("Illegal Action");
	}
}


$chatOjb->loadChatThreadById($chatID, true);

$t->set_var("CHAT_ID", $chatID);	
$t->set_var("CHAT_TYPE", WebUtil::htmlOutput(ChatThread::getChatTypeDesc($chatOjb->getChatType())));	


// In case there are multiple windows all open about the same time... we want to acknowledge a "ping" from the CSR has quick as possible.
if($chatOjb->getCsrUserId() == $AuthObj->GetUserID()){
	$chatOjb->pingFromCsr();
}


// Find out if the chat window was opened in the past few seconds for the same ID
// If so we want to close it.  That is the only way to avoid multiple pop-ups if there are multiple browser windows open.
if(ChatCSR::checkIfChatThreadWasOpenedByCsrRecently($chatID)){
	print "<html>\nDuplicate Chat Pop-up C" . $chatID . " <br> Attempting to close it.<script>self.close();</script>\n</html>";
	exit();
}
ChatCSR::markChatThreadOpenedRecently($chatID);



// We want to record the user viewing a Chat Thread.
// However, we don't want to do that if someone just-reassigned the chat (and the page re-loaded)
if(WebUtil::GetSessionVar("LastChatTransfered") != $chatID){
	// Don't reord the page visit unless there is already more than one CS reply
	// The first CS reply gets counted as another page visit for "new chat thread".
	if($chatOjb->getTotalCustomerMessages() > 0)
		NavigationHistory::recordPageVisit($dbCmd, $UserID, "ChatView", $chatID);
}
else{
	// The next time they view the chat thread, we will record it in their navigation history.
	WebUtil::SetSessionVar("LastChatTransfered", 0);
}


// We want to get a list of domain names that the CSR has permission for (not including the domain of this thread).
// That will let us show a Javascript warning if the user accidently types in the email address belonging to another domain (force of habbit).
$otherDomainIDsArr = $AuthObj->getUserDomainsIDs();
WebUtil::array_delete($otherDomainIDsArr, $chatOjb->getDomainId());

$otherDomainKeysArr = array();
foreach($otherDomainIDsArr as $thisDomainId){
	$otherDomainKeysArr[] = Domain::getDomainKeyFromID($thisDomainId);
}
$t->set_var("OTHER_DOMAIN_KEYS_JSON", json_encode($otherDomainKeysArr));


$domainLogoObj = new DomainLogos($chatOjb->getDomainId());
$domainLogoImg = "<img alt='".Domain::getDomainKeyFromID($chatOjb->getDomainId())."' title='".Domain::getDomainKeyFromID($chatOjb->getDomainId())."' align='absmiddle'   src='./domain_logos/$domainLogoObj->verySmallIcon' border='0'>";
$t->set_var("DOMAIN_LOGO", $domainLogoImg);	
$t->allowVariableToContainBrackets("DOMAIN_LOGO");
$t->set_var("DOMAIN_NAME", Domain::getDomainKeyFromID($chatOjb->getDomainId()));	

$chatSubjectListMenu = Widgets::buildSelect(ChatThread::getChatSubjects(), $chatOjb->getSubject());

$t->set_var("CHAT_SUBJECT_LIST", $chatSubjectListMenu);
$t->allowVariableToContainBrackets("CHAT_SUBJECT_LIST");	

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$t->pparse("OUT","origPage");





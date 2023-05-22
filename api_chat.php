<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

$domainObj = Domain::singleton();

$passiveAuthObj = Authenticate::getPassiveAuthObject();
if($passiveAuthObj->CheckIfLoggedIn() && $passiveAuthObj->CheckIfUserIDisMember($passiveAuthObj->GetUserID()))
	$userIsCsrFlag = true;
else
	$userIsCsrFlag = false;

$user_sessionID =  WebUtil::GetSessionID();

$api_version = WebUtil::GetInput("api_version", FILTER_SANITIZE_STRING_ONE_LINE);
$command = WebUtil::GetInput("command", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$chatID = WebUtil::GetInput("chat_id", FILTER_SANITIZE_INT);
$isTyping = WebUtil::GetInput("is_typing", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);


header ("Content-Type: text/xml");

// It seems that when you hit session_start it will send a Pragma: NoCache in the header
// When comminicating over HTTPS there is a bug with IE.  It can not get the flash documents after they have finished downloading because they have already expired
// We can allow the file to be cached be sending this header.  When requesting the document you can put a ?noache=timestamp
// This is the only way to get flash communication to work over HTTPS with session variables
header("Pragma: public");


function _PrintXMLError($ErrorMessage){
	session_write_close();
	$errorXML = "<?xml version=\"1.0\" ?>\n<response>\n<api_version>1.1</api_version>\n<result>ERROR</result>\n<error_message>" . WebUtil::htmlOutput($ErrorMessage) . "</error_message>\n</response>";
	print $errorXML;
	exit;
}

function _authenticateChat($chatID){

	$chatObj = new ChatThread();
	
	if(!$chatObj->authenticateChatThread($chatID)){
		_PrintXMLError("The chat thread is not available.");
	}
}


if($api_version != "1.1"){
	_PrintXMLError("The API Version is invalid.");
}


if($command == "get_messages"){

	_authenticateChat($chatID);
	
	$chatObj = new ChatThread();
	$chatObj->loadChatThreadById($chatID);
	
	
	if($userIsCsrFlag){
		// Don't aknowledge a ping from the CSR if they are not the one assigned to the thread.
		// They may be overseeing the converstaion, or possibly transfered the user and then forgot.
		if($passiveAuthObj->GetUserID() == $chatObj->getCsrUserId()){
			$chatObj->pingFromCsr();	
			$chatObj->setCsrIsTyping($isTyping == "true" ? true : false);
		}
	}
	else{
		$chatObj->pingFromCustomer();
		
		$chatObj->setCustomerIsTyping($isTyping == "true" ? true : false);
		
		// If a customer polls the server they will be updated with a status which says the chat is closed.
		// At that point, the javascript should stop polling the server.  However, we want to mark that within our visitor path system.
		if($chatObj->isChatThreadClosed()){
			VisitorPath::addRecord("Chat", "Finished: " . LanguageBase::getTimeDiffDesc($chatObj->getStartDate(), $chatObj->getClosedDate(), true));
		}
	}
		
	
		
		
	// Each ping may come with receipt acknowlegments of previously downloaded message ID's
	// The list of receipts will be message ID's separated by pipe symbols.
	$receiptAckStr = WebUtil::GetInput("message_acks", FILTER_SANITIZE_STRING_ONE_LINE);
	$receiptAckArr = split("\|", $receiptAckStr);
	
	foreach($receiptAckArr as $thisMsgId)
		$chatObj->acknowledgeMessageReceived($thisMsgId);
	
	$lastMessageIdReceived = WebUtil::GetInput("lastMessageId", FILTER_SANITIZE_INT);
	$messageIDs = $chatObj->getMessageIdsInChatThread($lastMessageIdReceived);

	$messageObjArr = array();
	foreach($messageIDs as $thisMsgId){
		$messageObjArr[] = $chatObj->getMessageObj($thisMsgId);
	}
	
	session_write_close();
	
	print "<?xml version=\"1.0\" ?>
	<response>
	<result>OK</result>
	<api_version>1.1</api_version>
	<error_message></error_message>
	<chat_thread_id>".$chatID."</chat_thread_id>\n";
	
	if($userIsCsrFlag)
		print "<is_csr>true</is_csr>\n";
	else 
		print "<is_csr>false</is_csr>\n";
		
	
		
	print "<status>".htmlspecialchars($chatObj->getStatus())."</status>\n";	
	
	print "<subject>".htmlspecialchars($chatObj->getSubject())."</subject>\n";	
	
	print "<chat_type>".htmlspecialchars($chatObj->getChatType())."</chat_type>\n";	
	
	print "<csr_assigned_id>".htmlspecialchars($chatObj->getCsrUserId())."</csr_assigned_id>\n";
	print "<csr_is_typing>".($chatObj->getCsrIsTyping() ? "true" : "false")."</csr_is_typing>\n";
	print "<customer_is_typing>".($chatObj->getCustomerIsTyping() ? "true" : "false")."</customer_is_typing>\n";
	
	if($chatObj->getCsrUserId() != 0){
		$chatCsrOjb = ChatCSR::singleton($chatObj->getCsrUserId());
		print "<csr_assigned_p_name>".htmlspecialchars($chatCsrOjb->getPenName($chatObj->getDomainId()))."</csr_assigned_p_name>\n";
		print "<csr_assigned_photo>".htmlspecialchars($chatCsrOjb->getPhotoId($chatObj->getDomainId()))."</csr_assigned_photo>\n";
	}
	else{
		print "<csr_assigned_p_name></csr_assigned_p_name>\n";
		print "<csr_assigned_photo>0</csr_assigned_photo>\n";
	}
	
	
	
	if($chatObj->getStatus() == ChatThread::STATUS_Closed)
		print "<closed_reason>".htmlspecialchars($chatObj->getClosedReason())."</closed_reason>\n";
	else 
		print "<closed_reason></closed_reason>\n";	
	
	print "<customer_id>".htmlspecialchars($chatObj->getCustomerUserId())."</customer_id>\n";
	
	if($chatObj->getCustomerUserId())
		print "<customer_name>".htmlspecialchars(UserControl::GetNameByUserID($dbCmd, $chatObj->getCustomerUserId()))."</customer_name>\n";
	
	if($userIsCsrFlag){	
		$chatCsrOjb = ChatCSR::singleton($passiveAuthObj->GetUserID());	
		print "<allow_please_wait>".($chatObj->getAllowPleaseWait() ? "true" : "false")."</allow_please_wait>\n";
		print "<customers_waiting>".$chatCsrOjb->getNumberOfCustomersWaiting($passiveAuthObj->getUserDomainsIDs())."</customers_waiting>\n";
	}
	else{ 
		// If the user is a customer... we will estimate the wait time
		if($chatObj->getStatus() == ChatThread::STATUS_Waiting)
			print "<estimated_wait>".$chatObj->estimateWaitTimeSeconds()."</estimated_wait>\n";
	}
		
	print "<chat_messages>\n";
	
	$xml = "";
	foreach($messageObjArr as $thisMessageObj){

		// Skip over messages which are intended for only CSR's (if a general user is calling this thread)
		if($thisMessageObj->forCsrOnlyFlag && !$userIsCsrFlag)
			continue;
		
		$xml .= "<message>\n";
		$xml .= "<message_id>" . intval($thisMessageObj->id) . "</message_id>\n";
		
		// Hide these message nodes from regular users.
		if($userIsCsrFlag){
			$xml .= "<csr_id>" . htmlspecialchars($thisMessageObj->csrUserID) . "</csr_id>\n";
			$xml .= "<csr_name>" . htmlspecialchars(UserControl::GetNameByUserID($dbCmd, $thisMessageObj->csrUserID)) . "</csr_name>\n";
			$xml .= "<csr_only>" . htmlspecialchars($thisMessageObj->forCsrOnlyFlag ? "true" : "false") . "</csr_only>\n";
		}
		
		if($thisMessageObj->csrUserID){
			$chatCsrOjb = ChatCSR::singleton($thisMessageObj->csrUserID);
			$xml .= "<csr_p_name>".htmlspecialchars($chatCsrOjb->getPenName($chatObj->getDomainId()))."</csr_p_name>\n";
		}
		else{
			$xml .= "<csr_p_name></csr_p_name>\n";
		}

		$xml .= "<date>" . htmlspecialchars($thisMessageObj->dateTimeStamp) . "</date>\n";
		$xml .= "<file_id>" . htmlspecialchars($thisMessageObj->fileAttachmentID) . "</file_id>\n";
		$xml .= "<file_name>" . htmlspecialchars($thisMessageObj->fileName) . "</file_name>\n";
		$xml .= "<file_size>" . htmlspecialchars($thisMessageObj->fileSize) . "</file_size>\n";
		$xml .= "<message_text>" . htmlspecialchars($thisMessageObj->message) . "</message_text>\n";
		$xml .= "<person_type>" . htmlspecialchars($thisMessageObj->personType) . "</person_type>\n";
		$xml .= "</message>\n";
	}
	print $xml;
	print "</chat_messages>\n</response>";
}
else if($command == "send_message"){

	_authenticateChat($chatID);
	
	$chatObj = new ChatThread();
	$chatObj->loadChatThreadById($chatID);
	
	if($chatObj->getStatus() == ChatThread::STATUS_Closed)
		_PrintXMLError("You message was not received because the chat session is closed.");
	//else if($chatObj->getStatus() == ChatThread::STATUS_New || $chatObj->getStatus() == ChatThread::STATUS_Waiting)
	//	_PrintXMLError("You message was not received because the chat session has not been assigned to a customer service representative yet.");
	
	$messageText = WebUtil::GetInput("message_text", FILTER_UNSAFE_RAW);
	$messagePrivateChar = WebUtil::GetInput("private", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
	
	// Reverse the Flag.  If the flag is "N"ot private... then it means it is "TRUE", visible for customer.
	$messagePrivateFlag = ($messagePrivateChar == "N" ? true : false);
	
	if($userIsCsrFlag){
		if($chatObj->getTotalCsrMessages() == 0)
			NavigationHistory::recordPageVisit($dbCmd, $passiveAuthObj->GetUserID(), "ChatNew", $chatID);
			
		$chatObj->addCsrMessage($messageText, $messagePrivateFlag, $passiveAuthObj->GetUserID());
	}
	else{
		$chatObj->addCustomerMessage($messageText);
	}
	

	session_write_close();
	
	print "<?xml version=\"1.0\" ?>
	<response>
	<result>OK</result>
	<api_version>1.1</api_version>
	<error_message></error_message>
	<chat_thread_id>".intval($chatID)."</chat_thread_id>
	</response>
	";
}
else if($command == "change_subject"){

	_authenticateChat($chatID);
	
	$chatObj = new ChatThread();
	$chatObj->loadChatThreadById($chatID);
	
	if(!$userIsCsrFlag)
		_PrintXMLError("End users can not change the subject.");
		
		
	$chatObj->changeSubject(WebUtil::GetInput("chatSubject", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES));
	
	session_write_close();
	
	print "<?xml version=\"1.0\" ?>
	<response>
	<result>OK</result>
	<api_version>1.1</api_version>
	<error_message></error_message>
	<chat_thread_id>".intval($chatID)."</chat_thread_id>
	<subject>".htmlspecialchars($chatObj->getSubject())."</subject>
	</response>
	";
}
else if($command == "allow_please_wait"){

	_authenticateChat($chatID);
	
	$chatObj = new ChatThread();
	$chatObj->loadChatThreadById($chatID);
	
	if(!$userIsCsrFlag)
		_PrintXMLError("End users can not change the please wait setting.");
		
	$allowStr = WebUtil::GetInput("allowFlag", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
	$allowFlag = false;
	if($allowStr == "true")
		$allowFlag = true;
		
	$chatObj->setAllowPleaseWait($allowFlag);
	
	session_write_close();
	
	print "<?xml version=\"1.0\" ?>
	<response>
	<result>OK</result>
	<api_version>1.1</api_version>
	<error_message></error_message>
	<chat_thread_id>".intval($chatID)."</chat_thread_id>
	</response>
	";
}
else if($command == "close_chat_thread"){

	_authenticateChat($chatID);
	
	$chatObj = new ChatThread();
	$chatObj->loadChatThreadById($chatID);
		
	if(!$chatObj->isChatThreadClosed()){
	
		if($userIsCsrFlag){
			$closeStatus = ChatThread::CLOSE_REASON_CsrTerminated;
		}
		else{
			$closeStatus = ChatThread::CLOSE_REASON_CustomerTerminated;
		}
		
		$chatObj->closeChatThread($closeStatus);
		
		if(!$userIsCsrFlag){
			VisitorPath::addRecord("Chat", "Finished By Customer: " . LanguageBase::getTimeDiffDesc($chatObj->getStartDate(), $chatObj->getClosedDate(), true));
		}
		else{
			NavigationHistory::recordPageVisit($dbCmd, $passiveAuthObj->GetUserID(), "ChatEnd", $chatID);
		}
	}
	
	session_write_close();
	
	print "<?xml version=\"1.0\" ?>
	<response>
	<result>OK</result>
	<api_version>1.1</api_version>
	<error_message></error_message>
	<chat_thread_id>".intval($chatID)."</chat_thread_id>
	</response>
	";
}
else if($command == "set_csr_status_online"){

	if(!$userIsCsrFlag)
		_PrintXMLError("End users can not change the status.");
		
	$AuthObj = new Authenticate(Authenticate::login_ADMIN);
	$chatCsrOjb = ChatCSR::singleton($AuthObj->GetUserID());

	$chatCsrOjb->changeStatusToOnline();
		
	session_write_close();
	
	print "<?xml version=\"1.0\" ?>
	<response>
	<result>OK</result>
	<api_version>1.1</api_version>
	<error_message></error_message>
	</response>
	";
}
else if($command == "set_csr_status_offline"){

	if(!$userIsCsrFlag)
		_PrintXMLError("End users can not change the status.");
		
	$AuthObj = new Authenticate(Authenticate::login_ADMIN);	
	$chatCsrOjb = ChatCSR::singleton($AuthObj->GetUserID());
		
	$chatCsrOjb->changeStatusToOffline();

	session_write_close();
	
	print "<?xml version=\"1.0\" ?>
	<response>
	<result>OK</result>
	<api_version>1.1</api_version>
	<error_message></error_message>
	</response>
	";
}
else if($command == "get_csr_status"){

	if(!$userIsCsrFlag)
		_PrintXMLError("End users can not change the status.");
		
	$AuthObj = new Authenticate(Authenticate::login_ADMIN);
	$chatCsrOjb = ChatCSR::singleton($AuthObj->GetUserID());
	
	// Build a pipe delimeted list of open chat threads by the CSR
	$chatIdList = implode("|", $chatCsrOjb->getChatThreadIdsOpenByCsr());
	
	// Find out if CSR chat pop-up stopped polling the server (maybe it was closed)?
	$delinquentIdList = implode("|", $chatCsrOjb->getDelinquentThreadIdsByCsr());
	
	// Find out if CSR has any active or waiting chat threads that they haven't responded to yet.
	// That is a quicker way to know if a pop-up window should be launched (versus relying on delinquency).
	$openIdListNoResponseArr = $chatCsrOjb->getOpenChatThreadsByCsrWithoutAcknowledgement();
	$tempArr = array();
	
	foreach($openIdListNoResponseArr as $thisChatID){
		if(!ChatCSR::checkIfChatThreadWasOpenedByCsrRecently($thisChatID))
			$tempArr[] = $thisChatID;
	}
	$openIdListNoResponseArr = $tempArr;
	
	
	
	session_write_close();
	
	// Make sure that the browser can cache multiple requests.
	// Just set the "Expires" timestamp a day in the future.
	// We rely on javascript to break caching by sending in unique URL's every time they want a new status update.
	// This is meant to protect the server from getting tons of requests when there are multiple browser windows open.
	header('Expires: ' . gmdate('D, d M Y H:i:s', time()+(60*60*24)) . ' GMT');
	header("Cache-Control: store, cache");
	
	print "<?xml version=\"1.0\" ?>
	<response>
	<result>OK</result>
	<api_version>1.1</api_version>
	<error_message></error_message>\n";
	print "<csr_full>".htmlspecialchars($chatCsrOjb->isCsrFull() ? "true" : "false")."</csr_full>\n";	
	print "<csr_offline>".htmlspecialchars($chatCsrOjb->isCsrOffline() ? "true" : "false")."</csr_offline>\n";	
	print "<csr_available>".htmlspecialchars($chatCsrOjb->isCsrAvailable() ? "true" : "false")."</csr_available>\n";
	print "<csr_open_threads>".htmlspecialchars($chatCsrOjb->getChatThreadsOpen())."</csr_open_threads>\n";	
	print "<csr_thread_limit>".htmlspecialchars($chatCsrOjb->getChatThreadLimit())."</csr_thread_limit>\n";	
	print "<open_chat_threads>".htmlspecialchars($chatIdList)."</open_chat_threads>\n";	
	print "<delinquent_chat_threads>".htmlspecialchars($delinquentIdList)."</delinquent_chat_threads>\n";	
	print "<open_threads_no_response>".htmlspecialchars(implode("|", $openIdListNoResponseArr))."</open_threads_no_response>\n";	
	print "<customers_waiting>".$chatCsrOjb->getNumberOfCustomersWaiting($AuthObj->getUserDomainsIDs())."</customers_waiting>\n";
	print "</response>
	";
}
else{

	_PrintXMLError("Invalid API command.");
}



	
?>
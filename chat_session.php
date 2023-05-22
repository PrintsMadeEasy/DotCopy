<?

require_once("library/Boot_Session.php");


$passiveAuthObj = Authenticate::getPassiveAuthObject();
if($passiveAuthObj->CheckIfLoggedIn() && $passiveAuthObj->CheckIfUserIDisMember($passiveAuthObj->GetUserID()))
	WebUtil::PrintError("Only customers may use the chat system (from the website front-end).  If you are trying to test the chat system, try using another browser.");


$dbCmd = new DbCmd();
$domainObj = Domain::singleton();

$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$chatID = WebUtil::GetInput("chat_id", FILTER_SANITIZE_INT);
$chatType = WebUtil::GetInput("chat_type", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$fileID = WebUtil::GetInput("file_id", FILTER_SANITIZE_INT);

$chatOjb = new ChatThread();

$t = new Templatex();
$t->set_file("origPage", "chat_session-template.html");	



$t->set_var("CHAT_TYPE", $chatType);

if(!empty($action)){
	if($action == "create"){
		
		// If the user still has an open chat thread... then just show them the thread which is currently open.
		if($chatOjb->doesCustomerHaveOpenChatThread()){
			
			$chatID = $chatOjb->getChatThreadIdOpen();
			
			$t->set_var("CHAT_ID", $chatID);	
			
			VisitorPath::addRecord("Chat", "Re-established Chat Session in New Window");
			
			$t->discard_block("origPage", "ChatSystemClosedBL");
			$t->pparse("OUT","origPage");
			exit();
		}
		
		// If there are not any CSRs online, then we have to let the customer know that the chat system is currently offline.
		$csrsOnlineArr = ChatCSR::getCSRsOnline(Domain::getDomainIDfromURL(), array($chatType));
		if(empty($csrsOnlineArr)){
			
			VisitorPath::addRecord("Chat", "System Offline: " . ChatThread::getChatTypeDesc($chatType));
			
			$t->set_var("CHAT_ID", 0);
			
			$t->discard_block("origPage", "ChatThreadBL");
			$t->pparse("OUT","origPage");
			exit();
		}
		
		
		$chatID = $chatOjb->createChatThread($chatType);
		
		$t->set_var("CHAT_ID", $chatID);	
		
		VisitorPath::addRecord("Chat", "Started: " . ChatThread::getChatTypeDesc($chatType));
	
		$t->discard_block("origPage", "ChatSystemClosedBL");
		$t->pparse("OUT","origPage");
		exit();
			
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

// When the user creats a new chat thread, it should happen with a "action" parameter and then EXIT the script.
VisitorPath::addRecord("Chat", "Opened in Another Browser Window");

$chatOjb->loadChatThreadById($chatID, true);

$t->set_var("CHAT_ID", $chatID);	

$t->discard_block("origPage", "ChatSystemClosedBL");

$t->pparse("OUT","origPage");





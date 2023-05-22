<?

require_once("library/Boot_Session.php");

$dbCmd = new DbCmd();

$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

$message = WebUtil::GetInput("message", FILTER_UNSAFE_RAW);
$subject = WebUtil::GetInput("subject", FILTER_SANITIZE_STRING_ONE_LINE);
$to = WebUtil::GetInput("to", FILTER_SANITIZE_STRING_ONE_LINE);
$windowtype = WebUtil::GetInput("windowtype", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$refid = WebUtil::GetInput("refid", FILTER_SANITIZE_INT);
$attachedto = WebUtil::GetInput("attachedto", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$replyto = WebUtil::GetInput("replyto", FILTER_SANITIZE_INT);
$unreadmessages = WebUtil::GetInput("unreadmessages", FILTER_SANITIZE_STRING_ONE_LINE);
$doNotRefresh = WebUtil::GetInput("doNotRefresh", FILTER_SANITIZE_STRING_ONE_LINE);


WebUtil::checkFormSecurityCode();

// Find out if we are replying to a message thread or if we are adding to a new one 
if(!empty($replyto)) {

	// this var is a pipe separated string of message ids.. split it into pieces
	$msgIDArr = split("\|", $unreadmessages);
	
	$messageThreadObj = new MessageThread();
	$messageThreadObj->setUserID($UserID);	
	$messageThreadObj->loadThreadByID($replyto);
		
	if(!$messageThreadObj->checkThreadPermission())
		throw new Exception("No permission to save message");
	
	// When we are replying, assume the user wants to close the open messages.
	foreach($msgIDArr as $thisMsgID){
		if(preg_match("/^\d+$/", $thisMsgID))
			$messageThreadObj->markMessageAsRead($thisMsgID, $UserID);
	}
	
	// Reply To contains the thread ID that we are replying to. 
	$messageObj = new Message($dbCmd);
	$messageObj->setMessage($message);
	$messageObj->setUserID($UserID);
		
	$newMessageID = $messageThreadObj->addMessageToThread($messageObj);

	$thisThreadID = $replyto;
}
else{
	$toListArr = split("\|", $to);

	// Get an array filled with all of the UserIDs that we want to be able to send messages to
	$recipientIDsArr = array($UserID);  //The user that sends the message is included, obviously.

	$UserControlObj = new UserControl($dbCmd);
	foreach($toListArr as $thisTo){
		
		if(empty($thisTo))
			continue;
		
		// This will make sure that the UserID exist, and that they have domain permissions to send a message.
		if($UserControlObj->LoadUserByID($thisTo))
			array_push($recipientIDsArr, $thisTo);

	}


	// Create a new message thread
	$messageThreadObj = new MessageThread($dbCmd);

	$messageThreadObj->setAttachedTo($attachedto);
	$messageThreadObj->setRefID($refid);
	$messageThreadObj->setSubject($subject);
	$messageThreadObj->setIncludeUserIDArr($recipientIDsArr);
	
	$newThreadID = $messageThreadObj->createNewThread();
	
	// Add the message to the thread that was just created
	$messageObj = new Message($dbCmd);
	$messageObj->setMessage($message);
	$messageObj->setUserID($UserID);

	$newMessageID = $messageThreadObj->addMessageToThread($messageObj);

	$thisThreadID = $newThreadID;
}


// Keep a record of the visit to this page by the user.
NavigationHistory::recordPageVisit($dbCmd, $UserID, "MsgSent", $thisThreadID);

if($windowtype == "popup"){

	if(empty($doNotRefresh)){
	
		print "
			<html>
			<script>
			window.opener.location = window.opener.location;
			self.close();
			</script>
			</html>

		";
	}
	else{
		print "
			<html>
			<script>
			self.close();
			</script>
			</html>
		";
	}

}	
else{
	$returl = "ad_message_display.php?thread=" . $thisThreadID;
	header("Location: " . WebUtil::FilterURL($returl) . "&nocache=". time());
	
}

?>
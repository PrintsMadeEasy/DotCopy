<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

$threadID = WebUtil::GetInput("threadid", FILTER_SANITIZE_INT);

$t = new Templatex(".");

$t->set_file("origPage", "ad_message_reply-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


$messageThreadObj = new MessageThread();
$messageThreadObj->setUserID($UserID);
$messageThreadObj->loadThreadByID($threadID);


if(!$messageThreadObj->checkThreadPermission())
	throw new Exception("No permission to replay to message");

$messageThreadHTML = MessageDisplay::generateMessageThreadHTML($messageThreadObj);

$t->set_var(array(
	"THREADID"=>$messageThreadObj->getThreadID(),
	"SUBECT"=>WebUtil::htmlOutput($messageThreadObj->getSubject()),
	"MESSAGE_THREAD"=>$messageThreadHTML
	));

$t->allowVariableToContainBrackets("MESSAGE_THREAD");

// A pipe separated list of message IDs unread by this user... so that when we reply, we know only to cose the messages that the user has seen.  The List could be old.
$t->set_var(array("UNREADMESSAGES"=>WebUtil::getPipeDelimatedListFromArray($messageThreadObj->getUnReadMessageIDs())));


// Build a drop down menu that shows who is included within the thread and if they have read the message or not
$threadUserIDsArr = MessageThread::getUserIDsInThread($threadID);
$peopleInThreadList = array("People included within this message thread...", "----------------------------------------------------");
foreach($threadUserIDsArr as $thisUserID){

	//Find out if this particular person in the thread has not read it yet
	$messageThreadObj->setUserID($thisUserID);
	$messageIDsUnread = $messageThreadObj->getUnReadMessageIDs();
	
	if(sizeof($messageIDsUnread) <> 0)
		$unreadMessage = "     . . . . . . (open)";
	else
		$unreadMessage = "";
		
	$peopleInThreadList[] .= UserControl::GetNameByUserID($dbCmd, $thisUserID) . $unreadMessage;
}


$peopleInThreadDropDown = Widgets::buildSelect($peopleInThreadList, array(), "usersinthread");
$t->set_var(array("PEOPLE_IN_THREAD"=>$peopleInThreadDropDown));
$t->allowVariableToContainBrackets("PEOPLE_IN_THREAD");

$t->pparse("OUT","origPage");




?>
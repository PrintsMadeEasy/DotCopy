<?

require_once("library/Boot_Session.php");


$thread = WebUtil::GetInput("thread", FILTER_SANITIZE_INT);
$keywords = WebUtil::GetInput("keywords", FILTER_SANITIZE_STRING_ONE_LINE);
$returl = WebUtil::GetInput("returl", FILTER_SANITIZE_URL);



$dbCmd = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();



if (!preg_match("/^\d+$/", $thread)){
	WebUtil::PrintAdminError("There was an error with the URL.");
}


$t = new Templatex(".");
$t->set_file("origPage", "ad_message_display-template.html");
$t->set_block ( "origPage", "subjectLinkBL", "subjectLinkBLout" );

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");
	
$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$messageThreadObj = new MessageThread($dbCmd);
$messageThreadObj->setUserID($UserID);
$messageThreadObj->loadThreadByID($thread);	

if(!$messageThreadObj->checkThreadPermission())
	throw new Exception("No permission to view thread");

$messageThreadHTML = MessageDisplay::generateMessageThreadHTML($messageThreadObj);

$t->set_var(array("MESSAGE_BLOCK"=>$messageThreadHTML));	
$t->set_var(array("REFID"=>$messageThreadObj->getRefID()));
$t->set_var(array("LINKURL"=>$messageThreadObj->getLinkURL()));
$t->set_var(array("LINKSUBJECT"=>WebUtil::htmlOutput($messageThreadObj->getLinkSubject())));
$t->set_var(array("SUBJECT"=>WebUtil::htmlOutput($messageThreadObj->getSubject())));

$t->allowVariableToContainBrackets("MESSAGE_BLOCK");

if($messageThreadObj->getRefID() == 0) 
	$t->set_var("subjectLinkBLout", "");
 else	
	$t->parse("subjectLinkBLout", "subjectLinkBL", false );	


// Keep a record of the visit to this page by the user.
NavigationHistory::recordPageVisit($dbCmd, $UserID, "MsgView", $thread);

// Display a different link up top depending on how they got to this page.
if(!empty($keywords)){
	$NavLink = '&nbsp;<br>';
	$NavLink .= "<a href='javascript:history.back();'>";
	$NavLink .= "Back To Search Page</a> <font class='SmallBody'>(Keywords = &acute;<i>" . WebUtil::htmlOutput($keywords). "</i>&acute;)<br>&nbsp;<br>";
}
else{
	$NavLink = '';
}


// If we don't pass in a return URL through the URL then we will just return them to the current message they are viewing.
if($returl == ""){
	$returl = "ad_message_display.php?thread=" . $thread;
}

$returl = WebUtil::FilterURL($returl);

$ReturnURLencoded = urlencode($returl);

$t->set_var(array("NAVLINK"=>$NavLink));
$t->set_var(array("THREADID"=>$thread));
$t->set_var(array("RETURL_ENCODED"=>$ReturnURLencoded));
$t->allowVariableToContainBrackets("NAVLINK");


$unreadMessageIDs = $messageThreadObj->getUnReadMessageIDs();

//-- A pipe separated list of message IDs unread by this user... so that when we reply, we know only to cose the messages that the user has seen.  The List could be old.
$t->set_var(array("UNREADMESSAGES"=>WebUtil::getPipeDelimatedListFromArray($unreadMessageIDs)));



// Don't show the user a "Close" Link if they have read all of the messages.
if(empty($unreadMessageIDs))
	$t->discard_block("origPage", "CloseMessageLinkBL");



//Build a drop down menu that shows who is included within the thread and if they have read the message or not
$ThreadUserIDsArr = MessageThread::getUserIDsInThread($thread);
$PeopleInThreadList = array("People included within this message thread...", "----------------------------------------------------");
foreach($ThreadUserIDsArr as $thisUserID){

	//Find out if this particular person in the thread has not read it yet
	$messageThreadObj->setUserID($thisUserID);
	$MessageIDsUnread = $messageThreadObj->getUnReadMessageIDs();
	
	if(sizeof($MessageIDsUnread) <> 0)
		$UnreadMessage = "     ****";
	else
		$UnreadMessage = "";
		
	$PeopleInThreadList[] .= UserControl::GetNameByUserID($dbCmd, $thisUserID) . $UnreadMessage;
}
$PeopleInThreadDropDown = Widgets::buildSelect($PeopleInThreadList, array(), "usersinthread");
$t->set_var(array("PEOPLE_IN_THREAD"=>$PeopleInThreadDropDown));
$t->allowVariableToContainBrackets("PEOPLE_IN_THREAD");


$taskCollectionObj = new TaskCollection();
$taskCollectionObj->limitShowTasksBeforeReminder(true);
$taskCollectionObj->limitUserID($UserID);
$taskCollectionObj->limitAttachedToName("message");
$taskCollectionObj->limitAttachedToID($thread);
$taskCollectionObj->limitUncompletedTasksOnly(true);
$taskObjectsArr = $taskCollectionObj->getTaskObjectsArr();

$tasksDisplayObj = new TasksDisplay($taskObjectsArr);
$tasksDisplayObj->setTemplateFileName("tasks-template.html");
$tasksDisplayObj->setReturnURL("ad_message_display.php?thread=" . $thread );
$tasksDisplayObj->displayAsHTML($t);		



$t->pparse("OUT","origPage");





?>
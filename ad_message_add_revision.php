<?

require_once("library/Boot_Session.php");

$messageID = WebUtil::GetInput("messageid", FILTER_SANITIZE_INT);
$doNotRefresh = WebUtil::GetInput("doNotRefresh", FILTER_SANITIZE_STRING_ONE_LINE);

$dbCmd = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

$t = new Templatex(".");

$t->set_file("origPage", "ad_message_add_revision-template.html");
$t->set_block("origPage", "messageRevisionBL", "messageRevisionBLout");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());




$messageObj = new Message($dbCmd);
$messageObj->loadMessageByID($messageID);


if($messageObj->getUserID() != $UserID)
	throw new Exception("You can't add a message revision on a message that isn't yours.");

$threadID = $messageObj->getThreadID();

$messageThreadObj = new MessageThread();
$messageThreadObj->setUserID($UserID);
$messageThreadObj->loadThreadByID($threadID);
$threadSubject = $messageThreadObj->getSubject();

$t->set_var("THREAD_SUBJECT", $threadSubject);	
$t->set_var("OLD_MESSAGE", WebUtil::htmlOutput($messageObj->getMessageText()));				
$t->set_var("MESSAGE_ID", $messageID);	
$t->set_var("THREAD_ID", $threadID);	

$t->parse("messageRevisionBLout", "messageRevisionBL", true );

$t->set_var("DONT_REFRESH", $doNotRefresh);

$t->pparse("OUT","origPage");

?>
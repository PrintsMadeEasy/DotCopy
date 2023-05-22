<?

require_once("library/Boot_Session.php");


$passiveAuthObj = Authenticate::getPassiveAuthObject();
if($passiveAuthObj->CheckIfLoggedIn() && $passiveAuthObj->CheckIfUserIDisMember($passiveAuthObj->GetUserID()))
	$isAdmin = true;
else
	$isAdmin = false;


$dbCmd = new DbCmd();
$domainObj = Domain::singleton();

$chatID = WebUtil::GetInput("chat_id", FILTER_SANITIZE_INT);

$chatOjb = new ChatThread();

if(!$chatOjb->authenticateChatThread($chatID))
	throw new Exception("The Chat ID is not valid.");

$t = new Templatex(".");
$t->set_file("origPage", "chat_fileupload_wrapper-template.html");	


$t->set_var("CHAT_ID", $chatID);

$t->pparse("OUT","origPage");





<?

require_once("library/Boot_Session.php");



$t = new Templatex();

$t->set_file("origPage", "customer_service-template.html");

$onlineCsrArr = ChatCSR::getCSRsOnline(Domain::getDomainIDfromURL(), array(ChatThread::TYPE_Support));
if(empty($onlineCsrArr))
	$t->set_var("CHAT_ONLINE", "false");
else 
	$t->set_var("CHAT_ONLINE", "true");

$t->pparse("OUT","origPage");


?>
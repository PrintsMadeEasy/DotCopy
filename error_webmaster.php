<?

require_once("library/Boot_Session.php");


$message = WebUtil::GetInput("message", FILTER_SANITIZE_STRING);

WebUtil::WebmasterError($message);

WebUtil::PrintError($message );


?>
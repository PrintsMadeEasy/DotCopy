<?

require_once("library/Boot_Session.php");

$message = WebUtil::GetInput("message", FILTER_SANITIZE_STRING_ONE_LINE);
$redirect = WebUtil::GetInput("redirect", FILTER_SANITIZE_URL);

$t = new Templatex();


$t->set_file("origPage", "artwork_message-template.html");

$redirect = WebUtil::FilterURL($redirect);

$t->set_var("REDIRECT", WebUtil::htmlOutput($redirect));


VisitorPath::addRecord("Artwork Message", $message);

if($message == "notext"){

	// We may want to use this script to have multiple message

}
else{
	WebUtil::PrintError("There is a problem with the URL.");
}


$t->pparse("OUT","origPage");



?>
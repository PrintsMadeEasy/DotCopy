<?

require_once("library/Boot_Session.php");

$redirect = WebUtil::GetInput("redirect", FILTER_SANITIZE_URL);
$windowtype = WebUtil::GetInput("windowtype", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$projectrecord = WebUtil::GetInput("projectrecord", FILTER_SANITIZE_INT);
$viewType = WebUtil::GetInput("viewtype", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

$t = new Templatex();


if($windowtype == "popup")
	$t->set_file("origPage", "artwork_update_small-template.html");
else
	$t->set_file("origPage", "artwork_update-template.html");

$t->set_var("REDIRECT", $redirect);
$t->set_var("PROJECT_RECORD", $projectrecord);
$t->set_var("VIEW_TYPE", WebUtil::htmlOutput($viewType));

$t->pparse("OUT","origPage");


?>
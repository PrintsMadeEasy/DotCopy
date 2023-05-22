<?


require_once("library/Boot_Session.php");



$viewtype = WebUtil::GetInput("viewtype", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

$t = new Templatex();

if($viewtype == "popup")
	$t->set_file("origPage", "terms_popup-template.html");
else
	$t->set_file("origPage", "terms-template.html");


$t->pparse("OUT","origPage");


?>
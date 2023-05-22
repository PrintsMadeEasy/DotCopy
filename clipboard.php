<?

require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);


$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$projectid = WebUtil::GetInput("projectid", FILTER_SANITIZE_INT);
$sidenumber = WebUtil::GetInput("sidenumber", FILTER_SANITIZE_INT);
$reloadparent = WebUtil::GetInput("reloadparent", FILTER_SANITIZE_STRING_ONE_LINE);


ProjectBase::EnsurePrivilagesForProject($dbCmd, $view, $projectid);



$t = new Templatex(".");

$t->set_file("origPage", "clipboard_frameset.html");


if($reloadparent == "true")
	$reloadparent_JS = "true";
else
	$reloadparent_JS = "false";

$t->set_var(array(
	"PROJECTID"=>$projectid,
	"VIEW"=>$view,
	"SIDENUMBER"=>$sidenumber,
	"RELOAD_PARENT"=>$reloadparent_JS
	));


$t->pparse("OUT","origPage");


?>
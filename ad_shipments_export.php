<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();




// Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


$t = new Templatex(".");

$t->set_file("origPage", "ad_shipments_export-template.html");


$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");


$t->pparse("OUT","origPage");


?>
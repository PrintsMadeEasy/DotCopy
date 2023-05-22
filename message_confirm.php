<?

require_once("library/Boot_Session.php");


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);
$UserID = $AuthObj->GetUserID();

$dbCmd = new DbCmd();
$userControlObj = new UserControl($dbCmd);
$userControlObj->LoadUserByID($UserID);

$personsName = $userControlObj->getName();
$personsEmail = $userControlObj->getEmail();

$t = new Templatex();


$t->set_file("origPage", "message_confirm-template.html");


$t->set_var("NAME", WebUtil::htmlOutput($personsName));
$t->set_var("EMAIL", WebUtil::htmlOutput($personsEmail));


VisitorPath::addRecord("Customer Service Message Confirmation");

$t->pparse("OUT","origPage");



<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


$SalesRepObj = new SalesRep($dbCmd);

$SalesID = WebUtil::GetInput("SalesID", FILTER_SANITIZE_INT);

if(!$SalesRepObj->LoadSalesRep( $SalesID ))
	throw new Exception("Error with Sales ID");


$t = new Templatex(".");

$t->set_file("origPage", "ad_sales_resendemailverify-template.html");


$SalesRepObj->SendEmailVerificationEmail();

$t->set_var("SALES_NAME", WebUtil::htmlOutput($SalesRepObj->getName()));


$t->pparse("OUT","origPage");


?>
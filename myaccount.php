<?

require_once("library/Boot_Session.php");


WebUtil::RunInSecureModeHTTPS();

$dbCmd = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);
$UserID = $AuthObj->GetUserID();


$UserControlObj = new UserControl($dbCmd);
$UserControlObj->LoadUserByID($UserID);

$message = WebUtil::GetInput("message", FILTER_SANITIZE_STRING_ONE_LINE);


$t = new Templatex();

$t->set_file("origPage", "myaccount-template.html");


$t->set_var("WELCOME_MESSAGE", WebUtil::htmlOutput($UserControlObj->getName()));

if($message == "ok"){
	$t->set_var("MESSAGE", "Your information has been saved.");
}
else{
	$t->set_var("MESSAGE", "");
}


// Hide the "Billing History" link if they are not a corporate user
if($UserControlObj->GetBillingType() != "C")
	$t->discard_block("origPage", "BillingHistoryBL");


// Hide the Sales Block if they don't have permissions
$SalesRepObj = new SalesRep($dbCmd);

if(!$SalesRepObj->LoadSalesRep($UserID) && !$AuthObj->CheckForPermission("SALES_MASTER"))
	$t->discard_block("origPage", "SalesBL");

if($SalesRepObj->CheckIfSalesRep()){
	if($SalesRepObj->CheckIfAccountDisabled())
		$t->discard_block("origPage", "SalesBL");
}

$t->pparse("OUT","origPage");


?>
<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();






$t = new Templatex();


$t->set_file("origPage", "myaccount_sales_terms-template.html");


// If they are logged in... then we want to find out if they are an employee.
// If they are... then show them different Terms and Conditions.
// If they are not logged in... then just show the user the standard Terms page.


$AuthObj = Authenticate::getPassiveAuthObject();
if($AuthObj->CheckIfLoggedIn()){

	$UserID = $AuthObj->GetUserID();
	

	$SalesRepObj = new SalesRep($dbCmd);

	if($SalesRepObj->LoadSalesRep($UserID))
		$t->discard_block("origPage", "NotAnEmployeeBL");
	else
		$t->discard_block("origPage", "IsAnEmployeeBL");
}
else{

	$t->discard_block("origPage", "IsAnEmployeeBL");
}


$t->pparse("OUT","origPage");


?>
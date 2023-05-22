<?

require_once("library/Boot_Session.php");




$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);

if(!$AuthObj->CheckForPermission("VIEW_CUSTOMER_SERVICE"))
	throw new Exception("Permission Denied");



$ProjectID = WebUtil::GetInput("projectid", FILTER_SANITIZE_INT);

if(!empty($ProjectID))
	ProjectBase::EnsurePrivilagesForProject($dbCmd, "ordered", $ProjectID);

$t = new Templatex(".");


$t->set_file("origPage", "ad_savenewproject-template.html");

$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

if($action == "save"){

	WebUtil::checkFormSecurityCode();
	
	// Get the User ID from this order
	$dbCmd->Query("SELECT UserID FROM orders INNER JOIN projectsordered as PO on orders.ID = PO.OrderID WHERE PO.ID=$ProjectID ");
	if($dbCmd->GetNumRows() == 0)

		throw new Exception("Illegal ProjectID");
	$CustomerID = $dbCmd->GetValue();
	

	$NewSavedProjectID = ProjectSaved::SaveProjectForUser($dbCmd, $ProjectID, "proof", $CustomerID, WebUtil::GetInput("notes", FILTER_SANITIZE_STRING_ONE_LINE));

	// Reload the Parent Window and close this pop-up
	print "<html><script>window.opener.location = window.opener.location; self.close();</script></html>";
	exit;

}
else if($action == "thumbnail"){
	
	// Because it could take a while to generate the thumbnail... we don't want the page just sitting there
	// Otherwise the user could click the submit button multiple times.

	$t->discard_block("origPage", "FormBL");
	$t->set_var("NOTES", urlencode(WebUtil::GetInput("notes", FILTER_SANITIZE_STRING_ONE_LINE)));

}
else{
	$t->discard_block("origPage", "GeneratingThumbnailBL");
}



$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$t->set_var("PROJECTID", $ProjectID);


$t->set_var("PROJECTID", $ProjectID);

$t->pparse("OUT","origPage");


?>
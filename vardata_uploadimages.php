<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();




//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);


//The user ID that we want to use for the Saved Project might belong to somebody else;
$theUserID = ProjectSaved::GetSavedProjectOverrideUserID($AuthObj);

// Make sure the path to the templates is coming from the Domain of the User. We may be looking at the saved projects from another domain in the URL.
if($theUserID != $AuthObj->GetUserID()){
	$domainIDofUser = UserControl::getDomainIDofUser($theUserID);
	Domain::enforceTopDomainID($domainIDofUser);
	$t->setSandboxPathByDomainID($domainIDofUser);
}


// If the Customer ID is coming in the URL then it means we are probably in Proof mode
// So make sure they have admin rights for the image.
$customerUserID = WebUtil::GetInput("customeruserid", FILTER_SANITIZE_INT);

if(!empty($customerUserID)){

	$AuthObj->EnsureMemberSecurity();
	if(!$AuthObj->CheckForPermission("EDIT_ARTWORK"))
		throw new Exception("Permission Denied");

	$theUserID = $customerUserID;
}


$UserControlObj = new UserControl($dbCmd);
if(!$UserControlObj->LoadUserByID($theUserID, TRUE))
	throw new Exception("The User ID does not exist.");


$t = new Templatex();

$t->set_file("origPage", "vardata_uploadimages-template.html");




$t->set_var("CUSTOMER_USERID", $theUserID);



VisitorPath::addRecord("Variable Data (Variable Images PopUp)");

$t->pparse("OUT","origPage");




?>
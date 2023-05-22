<?

require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$UserID = $AuthObj->GetUserID();



if(!$AuthObj->CheckForPermission("VIEW_CUSTOMER_SERVICE"))
	throw new Exception("Permission Denied");


// There must always be a category ID... but there may or may not be a message ID.
$categoryid = WebUtil::GetInput("categoryid", FILTER_SANITIZE_INT);
$messageid = WebUtil::GetInput("message", FILTER_SANITIZE_INT);

// Root folder is the default ... 0
if(empty($categoryid))
	$categoryid = "0";



$t = new Templatex(".");


$t->set_file("origPage", "ad_cannedmsg_tree-template.html");



// If we don't find the Javascript file for showing the tree on disk... then we want to generate it
if(!file_exists(DomainPaths::getPdfSystemPathOfDomainInURL() . "/cs_cannedmsg_tree_items.js")){

	$CategoryObj = new CategoryControl($dbCmd2, "cannedmsgcategories", "ID", "CategoryName", "PID");

	CannedMessages::GenerateTree_JSON($dbCmd, $CategoryObj);
}

$t->set_var("TREE_JAVASCRIPT_PATH", DomainPaths::getPdfWebPathOfDomainInURL() . "/cs_cannedmsg_tree_items.js");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$t->set_var(array(
	"CATEGORYID"=>$categoryid,
	"MESSAGEID"=>$messageid,
	"TIME"=>time()
	));


$t->pparse("OUT","origPage");



?>
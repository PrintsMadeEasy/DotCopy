<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();




//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);


//The user ID that we want to use for the Saved Project might belong to somebody else;
$theUserID = ProjectSaved::GetSavedProjectOverrideUserID($AuthObj);

$UserControlObj = new UserControl($dbCmd);
if(!$UserControlObj->LoadUserByID($theUserID, TRUE))
	throw new Exception("The User ID does not exist.");

	
// Make sure the path to the templates is coming from the Domain of the User. We may be looking at the saved projects from another domain in the URL.
if($theUserID != $AuthObj->GetUserID()){
	$domainIDofUser = UserControl::getDomainIDofUser($theUserID);
	Domain::enforceTopDomainID($domainIDofUser);
}
	

$customerUserID = WebUtil::GetInput("customeruserid", FILTER_SANITIZE_INT);

$imageID = WebUtil::GetInput("imageid", FILTER_SANITIZE_INT);


// If the Customer ID coming in the URL doesn't match the person viewing the page... then they better be an administrator
if($customerUserID != $theUserID){
	
	$AuthObj->EnsureMemberSecurity();
	
	if(!$AuthObj->CheckForPermission("EDIT_ARTWORK"))
		throw new Exception("Permission Denied");

	$theUserID = $customerUserID;
}


if(!in_array(UserControl::getDomainIDofUser($theUserID), $AuthObj->getUserDomainsIDs()))
	throw new Exception("The user of variable images does not belong to this domain.");


$dbCmd->Query("SELECT UserID FROM variableimagepointer WHERE ID=$imageID");
$actualCustomerID = $dbCmd->GetValue();

if($actualCustomerID != $customerUserID)
	throw new Exception("Permission Denied to preview this image.");



$variableImageObj = new VariableImages($dbCmd, $customerUserID);

$imageHash = $variableImageObj->getImageByID($imageID);



header('Accept-Ranges: bytes');
header("Content-Length: ". strlen($imageHash["ImageData"]));
header("Connection: close");
header("Content-Type: " . $imageHash["ContentType"]);
header("Last-Modified: " . date("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-cache, must-revalidate");

print $imageHash["ImageData"];


?>
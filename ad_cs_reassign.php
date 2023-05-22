<?

require_once("library/Boot_Session.php");



$domainObj = Domain::singleton();

$dbCmd = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("VIEW_CUSTOMER_SERVICE"))
	throw new Exception("Permission Denied");



$threadid = WebUtil::GetInput("threadid", FILTER_SANITIZE_INT);
$save = WebUtil::GetInput("save", FILTER_SANITIZE_STRING_ONE_LINE);
$csid = WebUtil::GetInput("csid", FILTER_SANITIZE_INT);


if(!preg_match("/^\d+$/",$threadid))
	throw new Exception("Error with URL");
$threadid = intval($threadid);

$dbCmd->Query("SELECT Ownership FROM csitems WHERE ID=$threadid AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));
if($dbCmd->GetNumRows() == 0)
	throw new Exception("Error with ThreadID");


$currentOwner = $dbCmd->GetValue();



// If we are saving... then write to the database and close the window
if(!empty($save)){
	
	WebUtil::checkFormSecurityCode();
	
	$csid = intval($csid);
	
	if(!empty($csid)){
		$domainIDofUserToReassign = UserControl::getDomainIDofUser($csid);
		
		if(!$AuthObj->CheckIfUserCanViewDomainID($domainIDofUserToReassign))
			throw new Exception("Can not reassign Domain ID of the user.");
	}

	$updateArr["Ownership"] = $csid;
	$dbCmd->UpdateQuery("csitems", $updateArr, "ID=$threadid");
	
	print "<html><script>window.opener.location = window.opener.location; self.close();</script></html>";
	exit;
}



$t = new Templatex(".");


$t->set_file("origPage", "ad_cs_reassign-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

// Make a drop down list of customer service agents --#
$CSuserIDArr = $AuthObj->GetUserIDsInGroup(array("MEMBER", "CS"));

// Filter out only users that this user has permission to see for his domains.
$CSuserIDArr = UserControl::filterUserIDsNotInUserDomainPool($dbCmd, $UserID, $CSuserIDArr);

$NamesCSarr = array();

// First choice is unassigned always
$NamesCSarr["0"] = "Unassigned";
	
// We don't want to include ourselves in the list for "others".
foreach($CSuserIDArr as $ThisUserID)
	$NamesCSarr["$ThisUserID"] = UserControl::GetNameByUserID($dbCmd, $ThisUserID);

	
$CSR_dropdown = Widgets::buildSelect($NamesCSarr, array($currentOwner));


$t->set_var("REASSIGN", $CSR_dropdown);
$t->set_var("THREADID", $threadid);

$t->allowVariableToContainBrackets("REASSIGN");

$t->pparse("OUT","origPage");



?>
<?

require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


$attachedto = WebUtil::GetInput("attachedto", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$refid = WebUtil::GetInput("refid", FILTER_SANITIZE_INT);
$preselected = WebUtil::GetInput("preselected", FILTER_SANITIZE_STRING_ONE_LINE);
$doNotRefresh = WebUtil::GetInput("doNotRefresh", FILTER_SANITIZE_STRING_ONE_LINE);


// Make an array from the preselected userIDs.   They are separated by pipe symbols
$PreSelectedArr = split("\|", $preselected);


$t = new Templatex(".");

$t->set_file("origPage", "ad_message_create-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$t->set_var(array(
	"ATTACHEDTO"=>$attachedto,
	"REFID"=>$refid
	));




// Get an array filled with all of the UserIDs that we want to be able to send messages to 
$AddressListUserIDArr = array();

$AddressListUserIDArr = array_merge($AddressListUserIDArr, $AuthObj->GetUserIDsInGroup(array("MEMBER", "ADMIN")));
$AddressListUserIDArr = array_merge($AddressListUserIDArr, $AuthObj->GetUserIDsInGroup(array("MEMBER", "CS")));

// Vendors should not have the ability to send messages to other vendors
if(!$AuthObj->CheckIfBelongsToGroup("VENDOR")){

	$AddressListUserIDArr = array_merge($AddressListUserIDArr, $AuthObj->GetUserIDsInGroup(array("MEMBER", "VENDOR")));
}

$AddressListUserIDArr = array_unique($AddressListUserIDArr);


// Filter out only users that this user has permission to see for his domains.
$AddressListUserIDArr = UserControl::filterUserIDsNotInUserDomainPool($dbCmd, $UserID, $AddressListUserIDArr);


// Make a new array.. this time filter out the User who is logged in.  They shouldn't be able to send a message to themselves 
$UserIDArr = array();
foreach($AddressListUserIDArr as $ThisUserID){
	if($UserID <> $ThisUserID){
		array_push($UserIDArr, $ThisUserID);
	}
	
}


$domainObj = Domain::singleton();


// We want to organize the list by Company name (which is how we track departments)
// sorted by name on secondary.
$userTitlesArr = array();

foreach($UserIDArr as $ThisPersonUserID){
	
	$dbCmd->Query("SELECT Company,Name FROM users WHERE ID=$ThisPersonUserID 
					AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));
	

	// Skip people that don't belong to a domain within the users "Selected Domains".
	if($dbCmd->GetNumRows() == 0){
		continue;
	}
	else{
		$row = $dbCmd->GetRow();
		$companyName = $row["Company"];
		$userName = $row["Name"];
	}

		
	$userTitlesArr["$ThisPersonUserID"] = $companyName . $userName;
}

// Sort by the Company name... while maintaining the positions of the User ID's.
asort($userTitlesArr);


$UserIDArr = array_keys($userTitlesArr);



$ToListArr = array();

// Get the Name and company of each user ID --#
foreach($UserIDArr as $ThisPersonUserID){

	
	$dbCmd->Query("SELECT Name, Company, DomainID FROM users WHERE ID=$ThisPersonUserID
					AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));
	$row = $dbCmd->GetRow();
	
	$domainName = "";
	
	if($dbCmd->GetNumRows() == 0)
		continue;


	$domainIDofUserWritingMessage = UserControl::getDomainIDofUser($UserID);
	
	// If we are able to address this message to someone outside of our own domain... then show an indication what domain that person belongs to.
	if($domainIDofUserWritingMessage != $row["DomainID"])
		$domainName = " [" . Domain::getAbreviatedNameForDomainID($row["DomainID"]) . "]";

	
	$ToListArr[$ThisPersonUserID] = $row["Company"] . " - " . $row["Name"] . $domainName;
}


$t->set_var("TO_LIST",Widgets::buildSelect($ToListArr, $PreSelectedArr));
$t->allowVariableToContainBrackets("TO_LIST");

$t->set_var("DONT_REFRESH", $doNotRefresh);

$t->pparse("OUT","origPage");


?>
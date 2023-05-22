<?

require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


$fromfilter = WebUtil::GetInput("fromfilter", FILTER_SANITIZE_STRING_ONE_LINE);
$subjectfilter = WebUtil::GetInput("subjectfilter", FILTER_SANITIZE_STRING_ONE_LINE);
$bodyfilter = WebUtil::GetInput("bodyfilter", FILTER_SANITIZE_STRING_ONE_LINE);
$filteraction = WebUtil::GetInput("filteraction", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();



$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if($action){
	
	WebUtil::checkFormSecurityCode();

	if($action == "emailfilterdelete"){
	
		$filterid = WebUtil::GetInput("filterid", FILTER_SANITIZE_INT);
	
		$dbCmd->Query("DELETE FROM csemailfilters WHERE ID=$filterid AND DomainID=" . Domain::oneDomain());

		header("Location: ./" . WebUtil::FilterURL(WebUtil::GetInput("returl", FILTER_SANITIZE_URL)));
		exit;
	}
	else if($action == "addnewfilter"){
	
		$filteraction = WebUtil::GetInput("filteraction", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
	
		$matches = array();
		
		// We made need to disect the filter action and extract a value from it.. Like a userID
		if($filteraction == "junk"){
			$ActionChar = "J";
			$ActionValue = "";
		}
		else if($filteraction == "spam"){
			$ActionChar = "S";
			$ActionValue = "";
		}
		else if($filteraction == "phone"){
			$ActionChar = "P";
			$ActionValue = "";
		}
		else if(preg_match_all("/^assign_(\d+)/", $filteraction, $matches)){
			$ActionChar = "A";
			$ActionValue = $matches[1][0];
		}
		else
			throw new Exception("invalid filteraction");



		$insertArr["FilterFrom"] = $fromfilter;
		$insertArr["FilterSubject"] = $subjectfilter;
		$insertArr["FilterBodyText"] = $bodyfilter;
		$insertArr["Action"] = $ActionChar;
		$insertArr["ActionValue"] = $ActionValue;
		$insertArr["DomainID"] = Domain::oneDomain();
		$dbCmd->InsertQuery("csemailfilters", $insertArr);
	
	}
	else{
		throw new Exception("Undefined Action");
	}

}




$t = new Templatex(".");


$t->set_file("origPage", "ad_emailfilter-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


// Create an array of shipment methods that will be used to fill up the drop down list
$DropDownActions = array(
	"blank"=>"",
	"junk"=>"Close as Junk Mail",
	"spam"=>"Mark as Spam",
	"phone"=>"Status to Phone Message"
	);


// Now add options to automatically assign messages to all customer service reps
$CsUserIDArr = $AuthObj->GetUserIDsInGroup(array("MEMBER", "CS"));

// Filter out only users that this user has permission to see for his domains.
$CsUserIDArr = UserControl::filterUserIDsNotInUserDomainPool($dbCmd, $UserID, $CsUserIDArr);

foreach($CsUserIDArr as $thisUserID)
	$DropDownActions["assign_" . $thisUserID] = "Assign to: " . UserControl::GetNameByUserID($dbCmd, $thisUserID);



$StatusDropDownMenu = Widgets::buildSelect($DropDownActions, array("blank"));
$t->set_var("ACTION_LIST", $StatusDropDownMenu);

$t->allowVariableToContainBrackets("ACTION_LIST");



$t->set_block("origPage","FiltersBL","FiltersBLout");


$dbCmd->Query("SELECT ID, FilterFrom, FilterSubject, FilterBodyText, Action, ActionValue
		FROM csemailfilters WHERE DomainID=".Domain::oneDomain()." ORDER BY ID DESC");

$counter=0;
while($row = $dbCmd->GetRow()){

	$ActionChar = $row["Action"];
	$ActionVal = $row["ActionValue"];

	if($ActionChar == "J")
		$ActionDesc = "Junk";
	else if($ActionChar == "S")
		$ActionDesc = "Spam";
	else if($ActionChar == "P")
		$ActionDesc = "Phone Message";
	else if($ActionChar == "A")
		$ActionDesc = "Assign To: " . WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd2, $ActionVal));
	else
		throw new Exception("Error with ActionChar in email filter");
	

	$t->set_var(array(
		"FROM"=>WebUtil::htmlOutput($row["FilterFrom"]),
		"SUBJECT"=>WebUtil::htmlOutput($row["FilterSubject"]),
		"BODY"=>WebUtil::htmlOutput($row["FilterBodyText"]),
		"ACTION"=>$ActionDesc,
		"FILTERID"=>$row["ID"]
		));


	$t->parse("FiltersBLout","FiltersBL",true);

	$counter++;
}


if($counter == 0)
	$t->set_var(array("FiltersBLout"=>"<tr><td class='body'>No Filters Running Yet</td></tr>"));


$t->pparse("OUT","origPage");



?>
<?

require_once("library/Boot_Session.php");


$projectrecord = WebUtil::GetInput("projectrecord", FILTER_SANITIZE_INT);
$returnurl = WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL);


$dbCmd = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);



//The user ID that we want to use for the Saved Project might belong to somebody else;
$UserID = ProjectSaved::GetSavedProjectOverrideUserID($AuthObj);


if(!ProjectSaved::CheckIfUserOwnsSavedProject($dbCmd, $projectrecord, $UserID))
	WebUtil::PrintError("Invalid Project Number.");




$Action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if(!empty($Action)){
	
	WebUtil::checkFormSecurityCode();
	
	if($Action == "savenote"){

		$ProjectNote = WebUtil::GetInput("notemessage", FILTER_SANITIZE_STRING_ONE_LINE);
		if(strlen($ProjectNote) > 199)
			throw new Exception("The note is too long.");

		$dbCmd->UpdateQuery("projectssaved", array("Notes"=>$ProjectNote), "ID=$projectrecord");
		
		if(!empty($returnurl)){
			
			header("Location: " . WebUtil::FilterURL($returnurl));
			exit;
		}
		else{
			print "<html><script>window.opener.location = window.opener.location; self.close();</script></html>";
			exit;
		}

	}
	else{
		throw new Exception("Illegal Action");
	}
	
}




$dbCmd->Query("SELECT Notes FROM projectssaved WHERE ID=$projectrecord");

$t = new Templatex();

$t->set_file("origPage", "SavedProjectNotes-template.html");

	
// Make sure the path to the templates is coming from the Domain of the User. We may be looking at the saved projects from another domain in the URL.
if($UserID != $AuthObj->GetUserID()){
	$domainIDofUser = UserControl::getDomainIDofUser($UserID);
	Domain::enforceTopDomainID($domainIDofUser);
	$t->setSandboxPathByDomainID($domainIDofUser);
}


$t->set_var("NOTES", WebUtil::htmlOutput($dbCmd->GetValue()));
$t->set_var("PROJECTID", $projectrecord);
$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

VisitorPath::addRecord("Saved Projects Notes");

$t->pparse("OUT","origPage")

?>
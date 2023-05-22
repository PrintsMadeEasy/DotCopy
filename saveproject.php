<?

require_once("library/Boot_Session.php");


$from = WebUtil::GetInput("from", FILTER_SANITIZE_STRING_ONE_LINE);
$addproject = WebUtil::GetInput("addproject", FILTER_SANITIZE_INT);
$templateNumber = WebUtil::GetInput("TemplateNumber", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$templateID = WebUtil::GetInput("TemplateID", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

if(empty($templateNumber))
	$templateNumber = $templateID;


$dbCmd = new DbCmd();



$user_sessionID =  WebUtil::GetSessionID();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);

//The user ID that we want to use for the Saved Project might belong to somebody else;
$UserID = ProjectSaved::GetSavedProjectOverrideUserID($AuthObj);

if(!ProjectSession::CheckIfUserOwnsProjectSession($dbCmd, $addproject))
	WebUtil::PrintError("It appears this project is not available anymore.  Your session may have expired.");



//Use the function in the custom library for saving this project.
$NewSavedProjectID = ProjectSaved::SaveProjectForUser($dbCmd, $addproject, "shoppingcart", $UserID, "No notes yet.");


VisitorPath::addRecord("Saved a Project from Shopping Cart");

session_write_close();

$templateNameValuePair = "";
if(!empty($templateNumber))
	$templateNameValuePair = "&TemplateID=$templateNumber";

//if($from == "shoppingcart")
	header("Location: ./shoppingcart.php?message=saved$templateNameValuePair", true, 302);
//else
//	header("Location: ./SavedProjects.php?");




?>
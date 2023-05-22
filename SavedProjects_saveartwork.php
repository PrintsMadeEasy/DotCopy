<?

require_once("library/Boot_Session.php");



$projectrecord = WebUtil::GetInput("projectrecord", FILTER_SANITIZE_INT);
$returnurl = WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL);
$tempalteNumber = WebUtil::GetInput("TemplateNumber", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);


$dbCmd = new DbCmd();


$user_sessionID =  WebUtil::GetSessionID();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);

//The user ID that we want to use for the Saved Project might belong to somebody else;
$UserID = ProjectSaved::GetSavedProjectOverrideUserID($AuthObj);



if(!ProjectSaved::CheckIfUserOwnsSavedProject($dbCmd, $projectrecord, $UserID))
	WebUtil::PrintError("Invalid Project Number.");


// This should always be set... but just in case the browser was sitting idle for a long time.
if(!isset($HTTP_SESSION_VARS['draw_xml_document']))
	WebUtil::PrintError("Your session may have expired.");


$ProjectSessionIDsThatAreSavedArr = ShoppingCart::GetProjectSessionIDsInShoppingCartThatAreSaved($dbCmd, $projectrecord, $user_sessionID);


$projectSavedObj = ProjectBase::getProjectObjectByViewType($dbCmd, "saved", $projectrecord);



// Get the Old Artwork File so that we can see if anything has changed
$OldArtworkFile = $projectSavedObj->getArtworkFile();
$OldArtHash = md5($OldArtworkFile);
$NewArtHash = md5( ArtworkInformation::FilterArtworkXMLfile($HTTP_SESSION_VARS['draw_xml_document']) );


if($OldArtHash <> $NewArtHash){
	
	$projectSavedObj->setArtworkFile($HTTP_SESSION_VARS['draw_xml_document']);
	$projectSavedObj->updateDatabase();
	
	// In case they uploaded an image onto the Artwork... it will move it from the "session" table into the "saved" table
	ArtworkLib::SaveImagesInSession($dbCmd, "saved", $projectrecord, ImageLib::GetImagesSavedTableName($dbCmd), ImageLib::GetVectorImagesSavedTableName($dbCmd));

	// If the user has added a backside in the Artwork File... then let that change this to a double-sided design.
	$projectSavedObj->changeProjectOptionsToMatchArtworkSides();
	$projectSavedObj->updateDatabase();
	
	// Mailing Service Options may cause the Permit Style to change (within the artwork)
	// Some funny things can happen in the editor still... Because javascript it trying to create a backside within the Flash App.
	// Sometimes a double sided card will only have 1 side, etc.  This will ensure the the artwork matches for sure.
	ProjectBase::ChangeArtworkBasedOnProjectOptions($dbCmd, $projectrecord, "saved");


	// Will copy over any information into the Shopping Cart (if they are linked)
	foreach($ProjectSessionIDsThatAreSavedArr as $projectSessionID){
		
		$projectSessionObj = ProjectBase::getProjectObjectByViewType($dbCmd, "session", $projectSessionID);
		$projectSessionObj->copyProject($projectSavedObj);
		$projectSessionObj->updateDatabase();
	}
	
	ArtworkLib::discoverNewEmailAddressesInSession($HTTP_SESSION_VARS['draw_xml_document']);

}


// In case this Project has variable data... changing the artwork could affect the configuration
// Don't go through the extra work of parsing the Data file if there is a Data Error.  Changing the artwork won't solve that error
if($projectSavedObj->isVariableData() && ($projectSavedObj->getVariableDataStatus() != "D" || $projectSavedObj->getQuantity() == 0))
	VariableDataProcess::SetVariableDataStatusForProject($dbCmd, $projectrecord, "saved");



// Refresh Project Object in case anything was changed the Artwork outside of this object (in a function call above).
$projectSavedObj->loadByProjectID($projectrecord);


// If this Project has an option on it that affects Sides... and a Side 1 & 2 is available... and the artwork has a "Blank Backside"... then remove the back.
if($projectSavedObj->possiblyDeleteBacksideIfBlank())
	$projectSavedObj->updateDatabase();


VisitorPath::addRecord("Save Artwork From Saved Projects");

$tempalteNumberParam = "";
if(!empty($tempalteNumber))
	$tempalteNumberParam = "&TemplateNumber=$tempalteNumber";
	
session_write_close();
header("Location: " . WebUtil::FilterURL("./artwork_update.php?projectrecord=$projectrecord&viewtype=saved$tempalteNumberParam&nocache= ".time() ."&redirect=" . urlencode("SavedProjects.php?action=updatesavedprojectsthumbnail&projectrecord=$projectrecord&returnurl=" . urlencode($returnurl))), true, 302);
 
?>
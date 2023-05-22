<?

require_once("library/Boot_Session.php");

$projectrecord = WebUtil::GetInput("projectrecord", FILTER_SANITIZE_INT);
$returnurl = WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL);
$tempalteNumber = WebUtil::GetInput("TemplateNumber", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);



$dbCmd = new DbCmd();



$user_sessionID =  WebUtil::GetSessionID();



if(!ProjectSession::CheckIfUserOwnsProjectSession($dbCmd, $projectrecord)){
	WebUtil::WebmasterError("Session Expired: " . $user_sessionID . ", ProjectID: " . $projectrecord . " IPaddress: " . WebUtil::getRemoteAddressIp(), "Shopping Cart Project Auth Error");
	WebUtil::PrintError("We are sorry, your session may have expired.");
}


$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "session", $projectrecord);

// If this parameter is sent in the URL... then we want to update the shopping cart (and thumbnail) with the artwork currently stored in the project.
// Otherwsie we are looking to update the project with artwork data in the session... that was stored from within the editor.
if(!WebUtil::GetInput("UseExistingArtwork", FILTER_SANITIZE_STRING_ONE_LINE)){

	// This should always be set... but just in case the browser was sitting idle for a long time.
	if(!isset($HTTP_SESSION_VARS['draw_xml_document'])){
		WebUtil::WebmasterError("Session Expired: " . $user_sessionID . ", ProjectID: " . $projectrecord . " IPaddress: " . WebUtil::getRemoteAddressIp(), "Shopping Cart Empty Artwork");
		WebUtil::PrintError("We are sorry, your session has expired.  You will have to re-submit your artwork.");
	}

	// Get the Old Artwork File so that we can see if anything has changed
	$OldArtworkFile = $projectObj->getArtworkFile();
	$OldArtHash = md5($OldArtworkFile);
	$NewArtHash = md5( ArtworkInformation::FilterArtworkXMLfile($HTTP_SESSION_VARS['draw_xml_document']) );


	if($OldArtHash != $NewArtHash){

		$projectObj->setArtworkFile($HTTP_SESSION_VARS['draw_xml_document']);
		$projectObj->updateDatabase();

		// If any projects in the shopping cart are shown as being "saved" then we need to let them know the information may not be saved
		ProjectSaved::ClearSavedIDLinksByViewType($dbCmd, "projectssession", $projectrecord);
		
		
		ArtworkLib::discoverNewEmailAddressesInSession($HTTP_SESSION_VARS['draw_xml_document']);
		
	}
	
	
	// In case this Project has variable data... changing the artwork could affect the configuration
	// Don't go through the extra work of parsing the Data file if there is a Data Error.  Changing the artwork won't solve that error
	// Also check if the data file is empty (0 quantity)... in which case we may want the system to initialize any unmapped fields..
	if($projectObj->isVariableData() && ($projectObj->getVariableDataStatus() != "D" || $projectObj->getQuantity() == 0))
		VariableDataProcess::SetVariableDataStatusForProject($dbCmd, $projectrecord, "session");
}


// In case the database changed, we need to refesh the object.
$projectObj->loadByProjectID($projectrecord);



// Make sure that the project is in the shopping cart... but there should only be 1 for this project record
$dbCmd->Query( "DELETE FROM shoppingcart WHERE ProjectRecord=$projectrecord AND shoppingcart.DomainID=".Domain::oneDomain() );

// Now Insert project into shopping cart
$mysql_timestamp = date("YmdHis", time());
$thisInsertID = $dbCmd->InsertQuery("shoppingcart", array("ProjectRecord"=>$projectrecord, "SID"=>$user_sessionID, "DateLastModified"=>$mysql_timestamp, "DomainID"=>Domain::oneDomain()));


// If the user has added a backside in the Artwork File... then let that change this to a double-sided design.
$projectObj->changeProjectOptionsToMatchArtworkSides();
$projectObj->updateDatabase();

// If this Project has an option on it that affects Sides... and a Side 1 & 2 is available... and the artwork has a "Blank Backside"... then remove the back.
if($projectObj->possiblyDeleteBacksideIfBlank())
	$projectObj->updateDatabase();
	


// Mailing Service Options may cause the Permit Style to change (within the artwork)
// Some funny things can happen in the editor still... Because javascript it trying to create a backside within the Flash App.
// Sometimes a double sided card will only have 1 side, etc.  This will ensure the the artwork matches for sure.
ProjectBase::ChangeArtworkBasedOnProjectOptions($dbCmd, $projectrecord, "projectssession");




// If there was an artwork mismatch warning message (from Upload Artwork)... then clear it as soon as their Artwork is re-saved.
if(WebUtil::GetSessionVar(("ArtworkDimensionMismatch" . $projectrecord)))
	WebUtil::SetSessionVar( ("ArtworkDimensionMismatch" . $projectrecord), null);

VisitorPath::addRecord("Save Artwork From Shopping Cart");

$tempalteNumberParam = "";
if(!empty($tempalteNumber))
	$tempalteNumberParam = "&TemplateNumber=$tempalteNumber";

session_write_close();
header("Location: " . WebUtil::FilterURL("./artwork_update.php?projectrecord=$projectrecord&viewtype=session$tempalteNumberParam&nocache=" . time() . "&redirect=" . urlencode("shoppingcart.php?action=updatesessionprojectsthumbnail&projectrecord=$projectrecord&returnurl=" . urlencode($returnurl) . "&IgnoreEmptyTextAlert=" . WebUtil::GetInput("IgnoreEmptyTextAlert", FILTER_SANITIZE_STRING_ONE_LINE))), true, 302);

?>
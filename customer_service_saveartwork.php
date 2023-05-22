<?

require_once("library/Boot_Session.php");



$projectrecord = WebUtil::GetInput("projectrecord", FILTER_SANITIZE_INT);
$returnurl = WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL);


$dbCmd = new DbCmd();



//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);
$UserID = $AuthObj->GetUserID();


if(!ProjectOrdered::CheckIfUserOwnsOrderedProject($dbCmd, $projectrecord, $UserID))
	WebUtil::PrintError("An Error occured, your session has expired or the order number is wrong.");


$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "ordered", $projectrecord);


// This should always be set... but just in case the browser was sitting idle for a long time.
if(!isset($HTTP_SESSION_VARS['draw_xml_document']))
	WebUtil::PrintError("Your session may have expired.");


// Before we save the new artwork, we need to be sure that the status did not change to printed while the user was editiing
// If it has been printed, then show an error page to the user 
$dbCmd->Query("SELECT Status FROM projectsordered WHERE ID=$projectrecord");
$CurrentProjectStatus = $dbCmd->GetValue();

if($CurrentProjectStatus <> "N" && $CurrentProjectStatus <> "P" && $CurrentProjectStatus <> "H" && $CurrentProjectStatus <> "W")
	WebUtil::PrintError("We are sorry, the changes were not saved because your order has already been printed.");

	



// Only reset the status to New if it is currently proofed.   Otherwise we want to leave the order on hold or whatever.
if($CurrentProjectStatus == "P")
	ProjectOrdered::ChangeProjectStatus($dbCmd, "N", $projectrecord);


// We want to record if the customer modified the design.
// However sometimes the customer can be really picky and modify it like 50 times...
// in which case we don't want the project history to grow extremely long
// Look for the last project history on this project... if we find the last is the customer modifing the design
// then get rid of it... because we are going to put in a new one the the most recent date
$dbCmd->Query("SELECT ID, Note FROM projecthistory WHERE ProjectID=$projectrecord ORDER BY ID DESC LIMIT 1");
$pHistoryRow = $dbCmd->GetRow();
if($pHistoryRow["Note"] == "Customer modified the design.")
	$dbCmd->Query("DELETE FROM projecthistory WHERE ID=" . $pHistoryRow["ID"]);

ProjectHistory::RecordProjectHistory($dbCmd, $projectrecord, "Customer modified the design.", $UserID);


// Reset the Estimated Ship & Arrival Dates
Order::ResetProductionAndShipmentDates($dbCmd, $projectrecord, "project", time());



ArtworkLib::SaveArtXMLfile($dbCmd, "customerservice", $projectrecord, $HTTP_SESSION_VARS['draw_xml_document']);


// Some funny things can happen in the editor (since it is running on the client's machine).
// Sometimes a double sided card will only have 1 side, etc.  This will ensure the the artwork matches for sure.
ProjectBase::ChangeArtworkBasedOnProjectOptions($dbCmd, $projectrecord, "customerservice");


// In case they uploaded an image onto the Artwork... it will move it from the "session" table into the "saved" table
ArtworkLib::SaveImagesInSession($dbCmd, "customerservice", $projectrecord, ImageLib::GetImagesSavedTableName($dbCmd), ImageLib::GetVectorImagesSavedTableName($dbCmd));


// If there is Data File error... don't go through the extra work of parsing the Data file and error checking 
// Saving a new artwork File won't change anything
if($projectObj->isVariableData() && $projectObj->getVariableDataStatus() != "D")
	VariableDataProcess::SetVariableDataStatusForProject($dbCmd, $projectrecord, "ordered");

// Copy over the Artwork to the corresponding SavedProject ID... if the link exists
ProjectOrdered::CloneOrderForSavedProject($dbCmd, $projectrecord);

VisitorPath::addRecord("Save Artwork From Order History");

session_write_close();
header("Location: " . WebUtil::FilterURL($returnurl . "&message=artwork"), true, 302);

?>
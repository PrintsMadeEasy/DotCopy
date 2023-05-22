<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();



// Pass in these parameters if you want the new project to default to them
$prodid = WebUtil::GetInput("prodid", FILTER_SANITIZE_INT);
$srchEngineTemplateID = WebUtil::GetInput("srchEngineTemplateID", FILTER_SANITIZE_INT);
$returnurl = WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL);




if(empty($prodid) && empty($srchEngineTemplateID))
	throw new Exception("A required number is missing when trying to start a new project.");



$user_sessionID =  WebUtil::GetSessionID();



// If a search engine ID comes in... we will use the Product ID inside of the Template
if(!empty($srchEngineTemplateID)){

	// make sure that the Template belongs to the correct product ID
	$dbCmd->Query("SELECT ProductID FROM artworksearchengine WHERE ID=" . intval($srchEngineTemplateID));
	$prodid = $dbCmd->GetValue();
}


if(!Product::checkIfProductIDisActive($dbCmd, $prodid))
	WebUtil::PrintError("The Product ID is invalid.");

$passiveAuthObj = new Authenticate(Authenticate::login_passive);
if(!$passiveAuthObj->CheckIfUserCanViewDomainID(Product::getDomainIDfromProductID($dbCmd, $prodid)))
	WebUtil::PrintError("The Product ID is invalid.");

$newProjectSessionID = ProjectSession::CreateNewDefaultProjectSession($dbCmd, $prodid, $user_sessionID);


// Create new Project Object and copy over data from the URL into it.
$projectObj = ProjectSession::getObjectByProjectID($dbCmd, $newProjectSessionID);

// Extract the Project Info Object (which hold Options/Choices/Quantities) and set it to paramters coming from the URL.
$projectInfoObj = $projectObj->getProjectInfoObject();
ProjectInfo::setProjectOptionsFromURL($dbCmd, $projectInfoObj);

// Record the quantity and Option selection back into the project.
$projectObj->setProjectInfoObject($projectInfoObj);

// In case the User passed in Parameters (through the URL to this script)... to make the Project Double-Sided... this will add a backside to the default options.
$projectObj->changeArtworkSidesBasedOnProjectOptions();

$projectObj->updateDatabase();

VisitorPath::addRecord("New Project Created", $projectObj->getProductTitleWithExt());


if(!empty($srchEngineTemplateID))
	header("Location: " . WebUtil::getFullyQualifiedDestinationURL("/product_loadtemplate.php?newtemplatetype=template_searchengine&newtemplateid=" . $srchEngineTemplateID . "&projectrecord=" . $newProjectSessionID . "&cancelurl=" . urlencode($returnurl) . "&nocache=". time()), true, 302);
else
	header("Location: " . WebUtil::getFullyQualifiedDestinationURL("/templates.php?projectrecord=" . $newProjectSessionID . "&productid=" . $prodid . "&nocache=" . time()), true, 302);



	
	
	
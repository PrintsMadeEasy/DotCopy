<?

require_once("library/Boot_Session.php");


$projectrecord = WebUtil::GetInput("projectrecord", FILTER_SANITIZE_INT);
$newtemplatetype = WebUtil::GetInput("newtemplatetype", FILTER_SANITIZE_STRING_ONE_LINE);
$editorType = WebUtil::GetInput("editorType", FILTER_SANITIZE_STRING_ONE_LINE);
$editorTemplateId = WebUtil::GetInput("editorTemplateId", FILTER_SANITIZE_STRING_ONE_LINE);
$newtemplateid = WebUtil::GetInput("newtemplateid", FILTER_SANITIZE_INT);
$continueurl = WebUtil::GetInput("continueurl", FILTER_SANITIZE_URL);
$cancelurl = WebUtil::GetInput("cancelurl", FILTER_SANITIZE_URL);
$ProductID = WebUtil::GetInput("productid", FILTER_SANITIZE_INT);
$templateIdForArtworkUpdate = WebUtil::GetInput("templateIdForArtworkUpdate", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

$dbCmd = new DbCmd();

$user_sessionID =  WebUtil::GetSessionID();


// Set this variable.  We will check to make sure it gets set on the following page.  If we cant find it then the person might not have cookies enabled.
$HTTP_SESSION_VARS['initialized'] = 1;


// We can either load a template into an existing Project... or we can create a new Project
if(empty($projectrecord)){
	
		if(empty($ProductID))
			throw new Exception("Error loading Template. The Project Record and the Product ID are both empty.");
	
		try {
			$productObj = Product::getProductObj($dbCmd, $ProductID, true);
		}
		catch (Exception $e){
			WebUtil::PrintError("The Product ID is not available.");
		}
		
		if($productObj->getProductStatus() != "G"){
			WebUtil::PrintError("This product has been discontinued.");
		}
			
		$projectrecord = ProjectSession::CreateNewDefaultProjectSession($dbCmd, $ProductID, $user_sessionID);
}
else{
	if(!ProjectSession::CheckIfUserOwnsProjectSession($dbCmd, $projectrecord))
		WebUtil::PrintError("There is a problem with the URL or your session has expired. If you continue to receive this message, check to ensure that your privacy settings are set at Medium within your browser. Please report this problem to Customer Service if it continues.");
}


$projectObj = ProjectSession::getObjectByProjectID($dbCmd, $projectrecord);


// The variable called $newtemplate that is in the URL is a number that correlates to the Artwork template
// Each Artwork template belongs to a specific product ID.  We want to check that this matches the user has currently selected so that no one tries to tamper with the URL
// After we open the artwork template we are then going to make a copy of it and place it into the users project.
if($newtemplatetype == "template_category"){
	$query = "SELECT ArtFile, ProductID FROM artworkstemplates where ArtworkID=$newtemplateid";
	$TemplateType = "C";
}
else if($newtemplatetype == "template_searchengine"){
	$query = "SELECT ArtFile, ProductID FROM artworksearchengine WHERE ID=$newtemplateid";
	$TemplateType = "S";
}
else{
	WebUtil::PrintError("The Template Type parameter is invalid.");
}


$dbCmd->Query($query);

if($dbCmd->GetNumRows() == 0)
	WebUtil::PrintError("The given Template ID was not found.");

$row = $dbCmd->GetRow();

$ArtWorkTemplate = $row["ArtFile"];
$templateProductID = $row["ProductID"];



// Make sure that the user has domain permissions for this Template
$productObj = Product::getProductObj($dbCmd, $projectObj->getProductID());
$productIDforTempaltes = $productObj->getProductIDforTemplates();

$domainIDofProduct = Product::getDomainIDfromProductID($dbCmd, $templateProductID);

$passiveAuthObj = Authenticate::getPassiveAuthObject();
if($productIDforTempaltes != $templateProductID && !$passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofProduct))
	throw new Exception("Error, the Template ID was not found.");



// If the Product ID belonging to the tempalte does not belong to the same Product ID of the project... then we need to convert the Artwork
if($projectObj->getProductID() != $templateProductID){

	// Will take care of resizing and repositioning based upon the changes to the Canvas Width/Height
	$artworkConversionObj = new ArtworkConversion($dbCmd);
	$artworkConversionObj->setFromArtwork($templateProductID, $ArtWorkTemplate);
	$artworkConversionObj->setToArtwork($projectObj->getProductID());
	

	// Overwrite the Artwork XML file with the converted version.
	$ArtWorkTemplate = $artworkConversionObj->getConvertedArtwork();
}

//print "<html><br>\n\n";
//var_dump($ArtWorkTemplate);

// Get the default Product Artwork (which may have Text Layers for Mailing, etc.) and Merge the template on top.
// That will also allow the User to Specify a Double-Sided Template in the UserID of artwork setup... and have only the Front-Side merged on top.
$defaultProductArtwork = $productObj->getDefaultProductArtwork();

$artworkMergeObj = new ArtworkMerge($dbCmd);
$artworkMergeObj->setBottomArtwork($defaultProductArtwork);
$artworkMergeObj->setTopArtwork($ArtWorkTemplate);

$ArtWorkTemplate = $artworkMergeObj->getMergedArtwork();



//print "<html><br>\n\n";
//var_dump($defaultProductArtwork);

// Update the DB with the new Artwork template and record what template we are using
$projectObj->setArtworkFile($ArtWorkTemplate, false);
$projectObj->setFromTemplateID($newtemplateid);
$projectObj->setFromTemplateArea($TemplateType);

$projectObj->changeProjectOptionsToMatchArtworkSides();
$projectObj->updateDatabase();



// Destroy temporary session variables that were used for switching sides since we have a new template
$HTTP_SESSION_VARS['SwitchSides'] = "";
$HTTP_SESSION_VARS['TempXMLholder'] = "";
$HTTP_SESSION_VARS['SideNumber'] = 0;


// If there was an artwork mismatch warning message (from Upload Artwork)... then clear it as soon as another template gets loaded.
if(WebUtil::GetSessionVar(("ArtworkDimensionMismatch" . $projectrecord)))
	WebUtil::SetSessionVar( ("ArtworkDimensionMismatch" . $projectrecord), null);




$productObj = new Product($dbCmd, $projectObj->getProductID());

// Either take user to the editor, or directly to the shopping cart.
if($productObj->getArtworkIsEditable()){
	
	VisitorPath::addRecord("Template Pick");
	
	if($editorType == "fields")
		$redirectionURL = "./edit_artwork_fields.php?projectrecord=$projectrecord&TemplateID=$editorTemplateId&view=projectssession&templateIdForArtworkUpdate=$templateIdForArtworkUpdate&continueUrl=" . urlencode($continueurl) . "&cancelUrl=" . urlencode($cancelurl) . "&nocache=" . time();
	else
		$redirectionURL = "./edit_artwork.php?projectrecord=$projectrecord&TemplateID=$editorTemplateId&editorview=projectssession&templateIdForArtworkUpdate=$templateIdForArtworkUpdate&newtemplate=yes&continueurl=" . urlencode($continueurl) . "&cancelurl=" . urlencode($cancelurl) . "&nocache=" . time();

	header("Location: " . WebUtil::FilterURL($redirectionURL), true, 302);
}
else{
	
	// Set the artwork in the session for the thumbnail to update.
	WebUtil::SetSessionVar("draw_xml_document", $projectObj->getArtworkFile());
	
	VisitorPath::addRecord("Template Pick", "Product Not Editable");
	$redirectionURL = "./shoppingcart_saveartwork.php?projectrecord=$projectrecord&returnurl=" . urlencode($continueurl) . "&sessID=".WebUtil::GetSessionID()."&nocache=" . time();
	header("Location: " . WebUtil::FilterURL($redirectionURL), true, 302);
}






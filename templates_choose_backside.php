<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

$tabview = WebUtil::GetInput("tabview", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "general"); 
$offset = WebUtil::GetInput("offset", FILTER_SANITIZE_INT); 
//Setup variable
$NumberOfResultsToDisplay = 30;

$offset = intval($offset);

$ua = new UserAgent();

$projectrecord = WebUtil::GetInput("projectrecord", FILTER_SANITIZE_INT);
$continueurl = WebUtil::GetInput("continueurl", FILTER_SANITIZE_URL);
$editingToolURL = WebUtil::GetInput("editingToolURL", FILTER_SANITIZE_URL);
$editorview = WebUtil::GetInput("editorview", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);


$curentURL = "templates_choose_backside.php?projectrecord=$projectrecord&editorview=$editorview&tabview=$tabview&offset=$offset";

$curentURL = WebUtil::FilterURL($curentURL);

$user_sessionID =  WebUtil::GetSessionID();


ProjectBase::EnsurePrivilagesForProject($dbCmd, $editorview, $projectrecord);



if($editorview == "customerservice" || $editorview == "ordered")
	throw new Exception("You can not view the template back-sides for a project which has already been ordered.");


$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $editorview, $projectrecord);

$productObj = Product::getProductObj($dbCmd, $projectObj->getProductID());

$artworkVarsObj = new ArtworkVarsMapping($projectObj->getArtworkFile(), $projectObj->getVariableDataArtworkConfig());


if($editorview == "customerservice"){
	if(!in_array($projectObj->getStatusChar(), array("N", "P", "W", "H")))
		WebUtil::PrintError("You can not add a backside to you postcard after the product has been printed.");
}


$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if($action){

	if($action == "addback"){
	
		$templateid = WebUtil::GetInput("templateid", FILTER_SANITIZE_INT);

		$dbCmd->Query("SELECT ArtFile, ProductID FROM artworkstemplates WHERE ArtworkID=$templateid");
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("The template ID was not found.");
		$row = $dbCmd->GetRow();
		$templateArt = $row["ArtFile"];
		$templateProductID = $row["ProductID"];
		
		
		$productIDforTempaltes = $productObj->getProductIDforTemplates();
		
		$domainIDofProduct = Product::getDomainIDfromProductID($dbCmd, $templateProductID);
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		if($productIDforTempaltes != $templateProductID && !$passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofProduct))
			throw new Exception("Error, the Template ID was not found.");

		if($productObj->getArtworkSidesCount() < 2)
			throw new Exception("You can't choose a backside on a Product which only has 1 side.");
			


		// If the Product ID belonging to the tempalte does not belong to the same Product ID of the project... then we need to convert the Artwork
		if($projectObj->getProductID() != $templateProductID){

			// Will take care of resizing and repositioning based upon the changes to the Canvas Width/Height
			$artworkConversionObj = new ArtworkConversion($dbCmd);			
			$artworkConversionObj->setFromArtwork($templateProductID, $templateArt);
			$artworkConversionObj->setToArtwork($projectObj->getProductID());

			// Overwrite the Artwork XML file with the converted version.
			$templateArt = $artworkConversionObj->getConvertedArtwork();

		}
					
		

		
		$ArtworkInfoProjectObj = new ArtworkInformation($projectObj->getArtworkFile());
		$ArtworkInfoTemplateObj = new ArtworkInformation($templateArt);
		
		// Now we have 2 Artwork Objects
		// Put the tempalte object onto the second side of the Project Artwork
		$ArtworkInfoProjectObj->SideItemsArray[1] = unserialize(serialize($ArtworkInfoTemplateObj->SideItemsArray[0]));
		
		
		
		// Get the default Product Artwork (which may have Text Layers for Mailing, etc.) and Merge the template on top.
		$defaultProductArtwork = $productObj->getDefaultProductArtwork();
		
		$artworkMergeObj = new ArtworkMerge($dbCmd);
		
		$artworkMergeObj->setTopArtwork($ArtworkInfoProjectObj->GetXMLdoc());
		$artworkMergeObj->setBottomArtwork($defaultProductArtwork);
		
		// We are going to merge the new Template Backside Artwork onto the Product Default backside (maybe with mailing fields).
		//$artworkMergeObj->setSideTargets(1, 1);
		$mergedArtworkObj = new ArtworkInformation($artworkMergeObj->getMergedArtwork());
		
		// Replace the merged backside artwork side on the Artwork from the Project.
		$ArtworkInfoProjectObj->SideItemsArray[1] = unserialize(serialize($mergedArtworkObj->SideItemsArray[1]));
		
		$projectObj->setArtworkFile($ArtworkInfoProjectObj->GetXMLdoc(), false);
		
		
		// The Product Options may need to be updated if we had a Single-Sided Project and then switched to double-sided.
		$projectObj->changeProjectOptionsToMatchArtworkSides();
		
		$projectObj->updateDatabase();
		
		// Destroy any session variables that may have contained a temporary copy of the backside
		$HTTP_SESSION_VARS['SwitchSides'] = "";
		$HTTP_SESSION_VARS['TempXMLholder'] = "";
		$HTTP_SESSION_VARS['SideNumber'] = 0;
	
		VisitorPath::addRecord("Template Backside Picked");
		
		// If there is an editing tool URL supplied... then direct them to that area, otherwise, reload theparent window.
		if(!empty($editingToolURL)){
			header("Location: ". WebUtil::FilterURL($editingToolURL));
			exit;
		}
		else{
		
			// Now reload the parent window and close the pop-up window
			// Make sure to remove the sidenumber=1 value to make sure the artwork is loaded fresh from the DB
			print "<html>
				<script>
					var parentLocation = window.opener.location;
					var newParentLoc = parentLocation + '&nocache=" . time() . "';
					newParentLoc = newParentLoc.replace(/sidenumber=\d+/, '');
					window.opener.location = newParentLoc;
					self.close();
				</script>
				</html>";
			exit;
		}
	}
	else{
		throw new Exception("Undefined Action");
	}

}





$t = new Templatex();

$t->set_file("origPage", "templates_choose_backside-template.html");



$t->set_var("PROJECTRECORD", $projectrecord);
$t->set_var("VIEWTYPE", $editorview);
$t->set_var("CURRENTURL_ENCODED", urlencode($curentURL));
$t->set_var("CONTINUE_URL", WebUtil::FilterURL($continueurl));
$t->set_var("EDITING_TOOL_URL_ENCODED", urlencode(WebUtil::FilterURL($editingToolURL)));






$productIDforTempaltes = $productObj->getProductIDforTemplates();







// There are 3 Special Names you can put on "Template Categories".  BACK-STA,  BACK-VAR, & BACK-BULK
// Respectively... products with "No Variable Data (static)", Products with "Variable Data" and Products with "Mailing Services".  Mailing services override Varaible Data (because mailing services already have variable data capabilities).
// Find out if the Product ID (used for templates) has a template category by that name... and if so show the user an appropriate message.

$dbCmd->Query("SELECT COUNT(*) FROM templatecategories WHERE ProductID=$productIDforTempaltes AND CategoryName='BACK-STA'");
$staticBacksideTemplatesFlag = $dbCmd->GetValue() > 0 ? true : false;

$dbCmd->Query("SELECT COUNT(*) FROM templatecategories WHERE ProductID=$productIDforTempaltes AND CategoryName='BACK-VAR'");
$vardataBacksideTemplatesFlag = $dbCmd->GetValue() > 0 ? true : false;

$dbCmd->Query("SELECT COUNT(*) FROM templatecategories WHERE ProductID=$productIDforTempaltes AND CategoryName='BACK-BULK'");
$bulkmailBacksideTemplatesFlag = $dbCmd->GetValue() > 0 ? true : false;



// Hide Blocks of HTML depending on what type of product this is.
if($productObj->hasMailingService()){
	$t->discard_block("origPage", "GeneralBacksideDescBL");
	$t->discard_block("origPage", "VariablesDataDescBL");
}
else if($productObj->isVariableData()){
	

	$t->discard_block("origPage", "GeneralBacksideDescBL");
	

	// If there are Bulk Mail templates and this is also Variable Data product... then they should see a description for bulk mail.
	if($tabview != "bulk")

		$t->discard_block("origPage", "BulkMailDescBL");

}
else{
	$t->discard_block("origPage", "BulkMailDescBL");
	$t->discard_block("origPage", "VariablesDataDescBL");

}







$ArtworkObj = new ArtworkInformation($projectObj->getArtworkFile());
if((sizeof($ArtworkObj->SideItemsArray) > 1) && (sizeof($ArtworkObj->SideItemsArray[1]->layers) > 0))
	$t->set_var("BACKSIDE", "true");
else
	$t->set_var("BACKSIDE", "false");




$t->set_block("origPage","TemplatePreviewBL","TemplatePreviewBLout");




// If this is Variable Data... (But Not Mailing Services)... the user should be able to see both choices of BulkMail Backsides and Regular.
// Create a "Tabs" block so that the user can switch between whatever style they choose.
// Variable Data Customers may want to print the addresses (bulk mail), but send them out by themselves (to be certain they were mailed).
if($productObj->isVariableData() && !$productObj->hasMailingService() && $bulkmailBacksideTemplatesFlag && $vardataBacksideTemplatesFlag){
	

	if($tabview == "general"){

		$backsideName = "BACK-VAR";
		$t->discard_block("origPage", "BulkMailDescBL");
	}
	else if($tabview == "bulk"){
		$backsideName = "BACK-BULK";
		$t->discard_block("origPage", "GeneralDescBL");
	}
	else
		throw new Exception("Error with tab view.");

	// Build the tabs
	$TabsObj = new Navigation();
	$TabsObj->AddTab("general", "General", "templates_choose_backside.php?projectrecord=$projectrecord&editorview=$editorview&tabview=general");
	$TabsObj->AddTab("bulk", "Bulk Mail", "templates_choose_backside.php?projectrecord=$projectrecord&editorview=$editorview&tabview=bulk");
	$t->set_var("NAV_BAR_HTML", $TabsObj->GetTabsHTML($tabview));
	$t->allowVariableToContainBrackets("NAV_BAR_HTML");

}
else if($productObj->hasMailingService() && $bulkmailBacksideTemplatesFlag){
	$backsideName = "BACK-BULK";
	$t->set_var("NAV_BAR_HTML", "");
}
else if($productObj->isVariableData() && $vardataBacksideTemplatesFlag){
	$backsideName = "BACK-VAR";
	$t->set_var("NAV_BAR_HTML", "");
}
else if($staticBacksideTemplatesFlag && !$productObj->hasMailingService()){

	$backsideName = "BACK-STA";
	$t->set_var("NAV_BAR_HTML", "");
}
else{

	throw new Exception("There are no backside templates configured yet.");
}



$dbCmd->Query("SELECT CategoryID FROM templatecategories where ProductID=$productIDforTempaltes AND CategoryName='$backsideName'");
$tempalteCategoryID = $dbCmd->GetValue();
if(!$tempalteCategoryID)
	throw new Exception("A category has not been defined for BACK yet.");



$resultCounter = 0;

// Get all preview images for the BACK sides
$dbCmd->Query("SELECT ArtworkID FROM artworkstemplates WHERE CategoryID=$tempalteCategoryID ORDER BY IndexID ASC");
while($artworkID = $dbCmd->GetValue()){
	
	$resultCounter++;

	// Only display results on given Page Number
	if(!(($resultCounter > $offset) && ($resultCounter <= ($NumberOfResultsToDisplay + $offset))))
		continue;

	$t->set_var("TEMPLATEID", $artworkID);
	
	
	// Now get the preview ID 
	$dbCmd2->Query("SELECT ID FROM artworkstemplatespreview WHERE TemplateID=$artworkID");
	$previewID = $dbCmd2->GetValue();
	

	
	$ImagePeviewFileName = "./image_preview/" . ThumbImages::GetTemplatePreviewName($previewID, "template_category");
	
	$t->set_var("TEMPLATE_IMG", $ImagePeviewFileName);
	
	
	$t->parse("TemplatePreviewBLout","TemplatePreviewBL", true);
	
	
}

if($resultCounter == 0)
	$t->set_var("TemplatePreviewBLout", "Sorry, there are no templates available.");
	


// Set this block so that we can erase the multi-page navigation bar if there aren't multiple pages 
$t->set_block("origPage","MultiPageBL","MultiPageBLout");
$t->set_block("origPage","SecondMultiPageBL","SecondMultiPageBLout");


// This means that we have multiple pages of search results
if($resultCounter > $NumberOfResultsToDisplay){


	// What are the name/value pairs AND URL  for all of the subsequent pages 
	$NV_pairs_URL = "tabview=$tabview&projectrecord=$projectrecord&editorview=$editorview&";
	$BaseURL = "./templates_choose_backside.php";

	// Get a the navigation of hyperlinks to all of the multiple pages 
	$NavigateHTML = Navigation::GetNavigationForSearchResults($BaseURL, $resultCounter, $NV_pairs_URL, $NumberOfResultsToDisplay, $offset);

	$t->set_var(array("NAVIGATE"=>$NavigateHTML, "RESULT_DESC"=>$resultCounter, "OFFSET"=>$offset));
	$t->allowVariableToContainBrackets("NAVIGATE");
	
	$t->parse("MultiPageBLout","MultiPageBL",true);
	$t->parse("SecondMultiPageBLout","SecondMultiPageBL",true);
}
else{
	$t->set_var(array("NAVIGATE"=>""));
	$t->set_var(array("MultiPageBLout"=>""));
	$t->set_var(array("SecondMultiPageBLout"=>""));
}


VisitorPath::addRecord("Template Choose Backside");


$t->pparse("OUT","origPage");





?>
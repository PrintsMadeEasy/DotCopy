<?

require_once("library/Boot_Session.php");

$offset = WebUtil::GetInput("offset", FILTER_SANITIZE_INT);
$admin = WebUtil::GetInput("admin", FILTER_SANITIZE_STRING_ONE_LINE);
$returnurl = WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL);
$templateNumber = WebUtil::GetInput("TemplateNumber", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$templateID = WebUtil::GetInput("TemplateID", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

if(empty($templateNumber))
	$templateNumber = $templateID;

$returnurl = WebUtil::FilterURL($returnurl);

// Thumbnails may take a long time to generate on very large artworks.  Give it 3 mintues.
set_time_limit(180);



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();



$user_sessionID =  WebUtil::GetSessionID();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);

//The user ID that we want to use for the Saved Project might belong to somebody else;
$UserID = ProjectSaved::GetSavedProjectOverrideUserID($AuthObj);

//If we are overriding somebody else's UserID then we are an administrator... so put them into admin mode automatically
if(ProjectSaved::CheckForSavedProjectOverride())
	$admin = "yes";


// Record in the DB the last time that the user has used their account.
// But don't do that if an Admin is doing an override to check out the saved project.
if($AuthObj->GetUserID() == $UserID)
	UserControl::updateDateLastUsed($UserID);
	

// Set this variable.  We will check to make sure it gets set on editor to ensure session is functioning
$HTTP_SESSION_VARS['initialized'] = 1;

$mysql_timestamp = date("YmdHis");


$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if($action){

	if($action == "copysavedproject"){

		$projectrecord = WebUtil::GetInput("projectrecord", FILTER_SANITIZE_INT);

		if(!ProjectSaved::CheckIfUserOwnsSavedProject($dbCmd, $projectrecord, $UserID))
			WebUtil::PrintError("It appears this project is not available anymore. Your session may have expired.");


		$originalProjectObj = ProjectSaved::getObjectByProjectID($dbCmd, $projectrecord); 
		
		$newProjectObj = new ProjectSaved($dbCmd);
		$newProjectObj->copyProject($originalProjectObj);
		
		
		// We also want to append - Copy to the project notes.... However if somebody is not taking care of their project notes we don't want to add "copy" label for no reason
		// 1) don't add the copy label if there are no notes for the project... 2) don't add the copy label if the note already ends in - copy
		if($newProjectObj->getNotes() != "No notes yet." && !preg_match("/- Copy$/", $newProjectObj->getNotes()))
			$newProjectObj->setNotes($newProjectObj->getNotes() . " - Copy");
		
		
		$newProjectID = $newProjectObj->createNewProject($UserID);

		ThumbImages::MarkThumbnailForProjectAsCopy($dbCmd, "projectssaved", $newProjectID);

		VisitorPath::addRecord("Saved Projects Copy Project");
		
		header("Location: ". WebUtil::FilterURL(WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL)));
		exit;

	
	}
	else if($action == "LoadIntoShoppingCart"){
		
		WebUtil::checkFormSecurityCode();
	
		$multisavedprojects = WebUtil::GetInput("multisavedprojects", FILTER_SANITIZE_STRING_ONE_LINE);
	
		if(empty($multisavedprojects))
			WebUtil::PrintError("No Projects were selected.");

		// If we are trying to load saved project(s) into the shopping cart.
		// Show them the temporary page... for updating shoppting cart with animation
		// It will send them to the shopping cart when completed.
		$t = new Templatex();
		$t->set_file("origPage", "artwork_update-template.html");
		$t->set_var("REDIRECT", "loadproject.php?multisavedprojects=" . $multisavedprojects);

		VisitorPath::addRecord("Saved Projects Load To Shopping Cart");
		
		// Print out Template
		$t->pparse("OUT","origPage");
		exit;
	
	}
	else if($action == "overridesavedprojects"){

		$customerID = WebUtil::GetInput("userid", FILTER_SANITIZE_INT);

		//The website should still work in secure mode, but they may get little complaints from tracking software or flash
		//WebUtil::BreakOutOfSecureMode();

		$AuthObj = new Authenticate(Authenticate::login_general);

		ProjectSaved::SetSavedProjectOverride($AuthObj, $customerID);

		header("Location: ./SavedProjects.php");
		exit;
	}
	else if($action == "clearoverridesavedprojects"){

		ProjectSaved::ClearSavedProjectOverride();

		header("Location: ./SavedProjects.php");
		exit;
	}
	else if($action == "deletesavedproject"){
		
		WebUtil::checkFormSecurityCode();

		$projectrecord = WebUtil::GetInput("projectrecord", FILTER_SANITIZE_INT);

		//The user ID that we want to use for the Saved Project might belong to somebody else;
		$UserIDoverride = ProjectSaved::GetSavedProjectOverrideUserID($AuthObj);

		if(!ProjectSaved::CheckIfUserOwnsSavedProject($dbCmd, $projectrecord, $UserIDoverride))
			WebUtil::PrintError("It appears this project is not available anymore. Your session may have expired.");


		//Unassociate the Saved Project ID from any other tables
		ProjectSaved::ClearSavedIDLinksByViewType($dbCmd, "saved", $projectrecord);

		ThumbImages::RemoveProjectThumbnail($dbCmd, "projectssaved", $projectrecord);

		// Delete the Project from the saved table.
		$dbCmd->Query("DELETE from projectssaved where ID=$projectrecord");

		header("Location: ". WebUtil::FilterURL(WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL)));
	}
	else if($action == "updatesavedprojectsthumbnail"){

		$projectrecord = WebUtil::GetInput("projectrecord", FILTER_SANITIZE_INT);
		
		if(!ProjectSaved::CheckIfUserOwnsSavedProject($dbCmd, $projectrecord, $UserID))
			WebUtil::PrintError("It appears this project is not available anymore. Your session may have expired.");
		

		// Make sure that the same thumbnail is not generated more than once.
		// It is possible that the person could hit there back button many times repeatadely and cause lots of thumbnails to start loading at once, overloading the server
		if(ThumbImages::checkIfThumbnailCanBeUpdated($dbCmd, "saved", $projectrecord)){
			ThumbImages::markThumbnailAsUpdating($dbCmd, "saved", $projectrecord);
			ThumbImages::CreateThumnailImage($dbCmd, $projectrecord, "saved");
		}


		// Copy over any thumbnails from the Saved Project to any items it is linked to within the shopping cart.
		$ProjectSessionIDsThatAreSavedArr = ShoppingCart::GetProjectSessionIDsInShoppingCartThatAreSaved($dbCmd, $projectrecord, $user_sessionID);
		foreach($ProjectSessionIDsThatAreSavedArr as $ProjectSessionID){
			ThumbImages::CopyProjectThumbnail($dbCmd, "projectssaved", $projectrecord, "projectssession", $ProjectSessionID);
			$dbCmd->Query("UPDATE projectssession SET DateLastModified=$mysql_timestamp WHERE ID=$ProjectSessionID");
		}


		
		// Find out if the domain Sandbox has included an ArtworkMessage template.  If not, don't redirect there.
		if(file_exists(Domain::getDomainSandboxPath() . "/artwork_message-template.html")){
			
			// Now that the artwork is updated we want to scan it to look and see if they haven't used the fonts within our editing tool
			$ArtworkInfoObj = new ArtworkInformation(ArtworkLib::GetArtXMLfile($dbCmd, 'saved', $projectrecord));
			
			if(!WebUtil::GetInput("IgnoreEmptyTextAlert", FILTER_SANITIZE_STRING_ONE_LINE) && $ArtworkInfoObj->checkForEmptyTextOnNonVectorArtwork()){
				header("Location: ./artwork_message.php?message=notext&redirect=". urlencode(WebUtil::FilterURL($returnurl)));
				exit;
			}
		}

		header("Location: ". WebUtil::FilterURL($returnurl));
		exit;
	}
	else{
		throw new Exception("Illegal action passed.");
	}

}







$t = new Templatex();


// Make sure the path to the templates is coming from the Domain of the User. We may be looking at the saved projects from another domain in the URL.
if($UserID != $AuthObj->GetUserID()){
	$domainIDofUser = UserControl::getDomainIDofUser($UserID);
	Domain::enforceTopDomainID($domainIDofUser);
	$t->setSandboxPathByDomainID($domainIDofUser);
}


$t->set_file("origPage", "SavedProjects-template.html");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

$t->set_block("origPage","ShoppingCartIconBL","ShoppingCartIconBLout");
$t->set_block("origPage","WarningBL","WarningBLout");
$t->set_block("origPage","ProductDoesHaveThumbnailBackgroundImageBL","ProductDoesHaveThumbnailBackgroundImageBLout");
$t->set_block("origPage","ProductHasNoThumbnailBackgroundImageBL","ProductHasNoThumbnailBackgroundImageBLout");
// Nested Buttons Blocks within Shopping Cart Row
$t->set_block("origPage","ViewArtworkButtonBL","ViewArtworkButtonBLout");
$t->set_block("origPage","ViewArtworkWithDataMergeButtonBL","ViewArtworkWithDataMergeButtonBLout");
$t->set_block("origPage","ViewArtworkButtonWithMergeSmallDataBL","ViewArtworkButtonWithMergeSmallDataBLout"); // If there is a small variable data file then the user should just see a standard "PDF proof button", but displays with data merged always.
$t->set_block("origPage","ConfigVariableDataButtonBL","ConfigVariableDataButtonBLout");
$t->set_block("origPage","MakeCopyButtonBL","MakeCopyButtonBLout");
$t->set_block("origPage","AddToShoppingCartButtonBL","AddToShoppingCartButtonBLout");



$t->set_block("origPage","itemsBL","itemsBLout");


$print_empty_projects_message = true;

$NumberOfResultsToDisplay = 20;

// Default to seraching both artwork files and project notes.
$SearchOptionType = WebUtil::GetInput("searchoptions", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "B");
if(empty($SearchOptionType))
	$SearchOptionType = "B";

$SearchOptionsArr = array(
	"B"=>"Artwork & Project Notes",
	"A"=>"Artwork data only",
	"N"=>"Project Notes only"
	);

$SearchOptionsDropDownMenu = Widgets::buildSelect($SearchOptionsArr, array($SearchOptionType));
$t->set_var("SEARCH_OPTIONS", $SearchOptionsDropDownMenu);
$t->allowVariableToContainBrackets("SEARCH_OPTIONS");


if(!array_key_exists($SearchOptionType, $SearchOptionsArr))
	throw new Exception("Illegal Search Option Type");

$keywords = WebUtil::GetInput("keywords", FILTER_SANITIZE_STRING_ONE_LINE);
$t->set_var("KEYWORDS", WebUtil::htmlOutput($keywords));
$t->set_var("KEYWORDS_ENCODED", urlencode($keywords));
$t->set_var("SEARCHOPTIONS_CHAR", $SearchOptionType);

// Make the "Template ID" sticky.
$templateNameValuePair = "";
if(!empty($templateNumber))
	$templateNameValuePair = "&TemplateID=$templateNumber";

$CurrentURL = "./SavedProjects.php?offset=$offset&keywords=" . urlencode($keywords) . "&searchoptions=$SearchOptionType" . $templateNameValuePair;
$CurrentURL = WebUtil::FilterURL($CurrentURL);

$currentURLencoded = urlencode($CurrentURL);
$t->set_var("CURRENTURL_ENCODED", $currentURLencoded);
$t->set_var("CURRENTURL_HTML", WebUtil::htmlOutput($CurrentURL));


// After an artwork has been saved within the editing too... we want it to take them to the 1st page... basically, remove the offset
// The reason is that a newly saved project will aways rise to the top of an list sorted by date
$savedArtworkLandingPageEncoded = urlencode("./SavedProjects.php?keywords=" . urlencode($keywords) . "&searchoptions=$SearchOptionType");
$savedArtworkLandingPageEncoded = WebUtil::FilterURL($savedArtworkLandingPageEncoded);

$t->set_var("SAVE_ARTWORK_LANDING_ENCODED", $savedArtworkLandingPageEncoded);


$SearchWhereClause = "";

// This should be limited in the HTML already
if(strlen($keywords) > 300)
	throw new Exception("The maximum limit of search criteria has been exceeded.");

if(!empty($keywords)){

	$KeywordsPartsArr = split(",", $keywords);
	for($i=0; $i<sizeof($KeywordsPartsArr); $i++)
		$KeywordsPartsArr[$i] = trim($KeywordsPartsArr[$i]);
	
	$ArtworkOrClause = "";
	$NotesOrClause = "";
	
	foreach($KeywordsPartsArr as $thisSearchTerm){
		if(empty($thisSearchTerm))
			continue;
		
		// They should not be trying to search on really long stuff... or it gould be a hacker trying to overload our database
		if(strlen($thisSearchTerm) > 30)
			$thisSearchTerm = "xyzasdff";
		
		if($SearchOptionType == "B" || $SearchOptionType == "A")
			$ArtworkOrClause .= "ArtworkFile LIKE \"%" . DbCmd::EscapeLikeQuery($thisSearchTerm) . "%\" OR ";
			
		if($SearchOptionType == "B" || $SearchOptionType == "N")
			$NotesOrClause .= "Notes LIKE \"%" . DbCmd::EscapeLikeQuery($thisSearchTerm) . "%\" OR ";
	}

	// Strip off the last 3 characters, which are "OR "
	if(!empty($ArtworkOrClause))
		$ArtworkOrClause = substr($ArtworkOrClause, 0, -3);
	if(!empty($NotesOrClause))
		$NotesOrClause = substr($NotesOrClause, 0, -3);
	
	
	// Prepare some SQL that we can just append to our usual query below... to limit to our search terms.
	if(!empty($ArtworkOrClause) && !empty($NotesOrClause))
		$SearchWhereClause = " AND (($ArtworkOrClause) OR ($NotesOrClause)) ";
	else if(!empty($ArtworkOrClause))
		$SearchWhereClause = " AND ($ArtworkOrClause) ";
	else if(!empty($NotesOrClause))
		$SearchWhereClause = " AND ($NotesOrClause) ";
	else
		throw new Exception("Problem with searching.  Null Search string encountered");
}


$projectIDsArr = array();
$productIDsArr = array();
$ActiveProductsIDarr = Product::getActiveProductIDsArr($dbCmd, Domain::oneDomain());

// Get all of the information out of the projectssaved and the project table.
$dbCmd->Query("SELECT ID AS SavedProjectID, UNIX_TIMESTAMP(DateLastModified) AS DateLastModified, Notes, OrderDescription, 
		OptionsDescription, ProductID, ArtworkFile FROM projectssaved 
		WHERE UserID=\"$UserID\" $SearchWhereClause AND DomainID=" . Domain::oneDomain() . " ORDER BY DateLastModified DESC");

$resultCounter = 0;


while ($row = $dbCmd->GetRow()){

	$ProductID = $row["ProductID"];

	// Depreciate old products
	if(!Product::checkIfProductIDisActive($dbCmd2, $ProductID))
		continue;
		

	// If we have many matches we only want to display those which are on the current page.  This is figured out by the offset parameter passed in the URL.
	if(($resultCounter >= $offset) && ($resultCounter < ($NumberOfResultsToDisplay + $offset))){

		$SavedProjectID = $row["SavedProjectID"];
		$DateLastModified = $row["DateLastModified"];
		$Notes = $row["Notes"];
		$OrderDescription = $row["OrderDescription"];
		$OptionsDescription = $row["OptionsDescription"];
		

		
		$ArtworkFile = $row["ArtworkFile"];


		$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd2, "saved", $SavedProjectID);

		$projectIDsArr[] = $SavedProjectID;
		$productIDsArr[] = $projectObj->getProductID();
		
		
		$prodObj = $projectObj->getProductObj();

		// clean the slate for inner block.
		$t->set_var("WarningBLout", "");
		



		// Create a Hash about this Project that can be piped into a JSON encoded string.
		$projectOptionsHash = $projectObj->getOptionsAndSelectionsWithoutHiddenChoices();
		
		$optionNames_JsonArr = array_keys($projectOptionsHash);
		$optionChoices_JsonArr = array_values($projectOptionsHash);
		

		$t->set_var("PROJECT_QUANTITY", $projectObj->getQuantity());
		$t->set_var("PROJECTRECORD", $SavedProjectID);
		
		
		$t->set_var("PRODUCT_ID", $ProductID);
		
		if($projectObj->isVariableData())
			$t->set_var("PROJECT_VARIABLE_DATA_FLAG", "true");
		else
			$t->set_var("PROJECT_VARIABLE_DATA_FLAG", "false");
			
			
		$t->set_var("PROJECT_OPTIONS_JSON", json_encode($optionNames_JsonArr));
		$t->set_var("PROJECT_CHOICES_JSON", json_encode($optionChoices_JsonArr));
		$t->set_var("QUANTITY_CHOICES_ARR", implode(",", $prodObj->getQuantityChoices()));

		
		
		// Figure out how many "non-hidden" choices there are for each of the options and store in a paralell array
		$optionChoiceCountsNotHiddenArr = array();
	
		foreach(array_keys($projectOptionsHash) as $thisOptionName){	
			$optionDetailObj = $prodObj->getOptionDetailObj($thisOptionName);
			$optionChoiceCountsNotHiddenArr[] = sizeof($optionDetailObj->getChoiceNamesArrNotHiddenArr());
		}
		
		$t->set_var("PROJECT_CHOICE_COUNTS_NOT_HIDDEN_JSON", implode(",", $optionChoiceCountsNotHiddenArr));
		
		
		
		
		
			// If the variable Data flags have any of these statuses... then it is an error.
		if(($projectObj->isVariableData()) && ($projectObj->getVariableDataStatus() == "D" || $projectObj->getVariableDataStatus() == "A" || $projectObj->getVariableDataStatus() == "L" || $projectObj->getVariableDataStatus() == "I"))
			$varialbeDataHasErrorsFlag = true;
		else
			$varialbeDataHasErrorsFlag = false;




		// On variable data projects... it will read "Modify Artwork / Data".  On static products it is just Edit Artwork
		// It takes them to different places 
		if($projectObj->isVariableData()){

			$varDataConfigureLink = "./vardata_editdata.php?projectrecord=$SavedProjectID&editorview=saved&continueurl=$savedArtworkLandingPageEncoded&cancelurl=$savedArtworkLandingPageEncoded&returnurl=$savedArtworkLandingPageEncoded";
			$varDataConfigureLink = WebUtil::FilterURL($varDataConfigureLink);
			
			$t->parse("ConfigVariableDataButtonBLout","ConfigVariableDataButtonBL", false);
			

			// We are only showing the Error color as red for now.  Ignore warnings
			if($varialbeDataHasErrorsFlag){
				$t->set_var("WARNINGMSG", WebUtil::htmlOutput($projectObj->getVariableDataMessage()));
				$t->set_var("WARNINGCOLOR", "#FFDDDD");
				$t->set_var("SEVERITY", "Variable Data Notice");
				
				$t->parse("WarningBLout","WarningBL",true);
			}
	

		}
		else{


			$varDataConfigureLink = "";

			$t->set_var("EDIT_IMAGE", "editartwork");
			$t->set_var("EDIT_PROJECT_LINK", "edit_artwork.php");
			$t->set_var("ConfigVariableDataButtonBLout", "");
		}





		// If it is a Variable Data Project and the quantity is less than 1000, then the "View Artwork" button should include the "Merge Feature"... instead of having a separate button for each.
		if($projectObj->isVariableData() && $projectObj->getQuantity() > 1000){

			// However, if there is an error... don't show the Merge button.  Just show the View Artwork (single) button only.
			if($varialbeDataHasErrorsFlag){
				$t->set_var("ViewArtworkWithDataMergeButtonBLout", "");
			}
			else{
				// If this is a variable data project without any errors, then give them a link to view the PDF doc with data merge (in addition to a single PDF proof.
				$t->parse("ViewArtworkWithDataMergeButtonBLout","ViewArtworkWithDataMergeButtonBL", false);
			}
			
			$t->parse("ViewArtworkButtonBLout","ViewArtworkButtonBL", false);
			$t->set_var("ViewArtworkButtonWithMergeSmallDataBLout", "");	

		}
		else if($projectObj->isVariableData()){

			// If it is Variable Data under 1,000 qty, then never show the Merge option button.
			// The View Artwork button will be an Merge (as long as there are now errors)
			if($varialbeDataHasErrorsFlag){
				$t->parse("ViewArtworkButtonBLout","ViewArtworkButtonBL", false);
				$t->set_var("ViewArtworkButtonWithMergeSmallDataBLout", "");	
			}
			else{
				// If this is a variable data project without any errors, then give them a link to view the PDF doc with data merge.
				$t->parse("ViewArtworkButtonWithMergeSmallDataBLout","ViewArtworkButtonWithMergeSmallDataBL", false);
				$t->set_var("ViewArtworkButtonBLout", "");
			}
			
			// As long as it is under 1,000 quantity we will never show a button for the Variable Data with Link button.
			$t->set_var("ViewArtworkWithDataMergeButtonBLout", "");
		}
		else{

			// Just a normal project then.  No Variable Data here.
			$t->parse("ViewArtworkButtonBLout","ViewArtworkButtonBL", false);
			$t->set_var("ViewArtworkButtonWithMergeSmallDataBLout", "");	
			$t->set_var("ViewArtworkWithDataMergeButtonBLout", "");

		}
		
		$t->parse("MakeCopyButtonBLout","MakeCopyButtonBL", false);





		// If their is a login error for the Variable Data project we want to automatically check every time the page loads.  If they are logged in it will recalculate the status
		if($projectObj->isVariableData() && $projectObj->getVariableDataStatus() == "L")
			VariableDataProcess::SetVariableDataStatusForProject($dbCmd2, $SavedProjectID, "saved");

		$dateOnlyModified = date("M j, Y", $DateLastModified);
		$timeOnlyModified = date("g:i a", $DateLastModified);

		$DateLastModifiedDisplay = date("M j, Y", $DateLastModified);
		$DateLastModifiedDisplay .= "<br>";
		$DateLastModifiedDisplay .= date("g:i a", $DateLastModified);

		// Construct a URL that can be used to download a thumbnail for the project 
		// Run the MD5 command on the artwork file.  The signature of the artwork file will change whenever the artwork changes....   We are caching the download of the thumbnail.   By changing the sigature in the download URL the image will not be cached anymore until the artwork changes again
		// It would be convienient to just use the "DateModified" instead of doing the whole md5 caclculation... but sometimes we save the artwork without updating the dateModified... like Clipboard... or the Saved Project link
		// As an added security check will will also take the MD5() of the UserID so we can compare that when fetching the download.  Just using a number is not good enough becasue someone could to a reverse lookup.  So instead we will add some salt to make sure it is unique.
		$ThumbnailImage = "./thumbnail_download.php?id=" . $SavedProjectID . "&projecttype=projectssaved&sc=" . md5($UserID . Constants::getGeneralSecuritySalt() ) . "&modified=" . md5($ArtworkFile);

		// This parameter should be passed in the URL manually.  It will allow them to download the XML file manually
		if($admin == "yes"){
			$AdminHTML = "";
			if($AuthObj->CheckForPermission("MANUAL_EDIT_ARTWORK"))
				$AdminHTML .= "<br><input type=\"button\" name=\"Generate\" value=\"Manual Edit\" class=\"AdminButton\" onClick=\"javascript:makeNewWindow3('./draw_artwork_edit_manual.php?projectrecord=" . $SavedProjectID . "&viewtype=saved');\">";
			$AdminHTML .= "<br><img src='./images/transparent.gif' width='5' height='5'><br><input type=\"button\" name=\"Generate\" value=\"Clipboard\" class=\"AdminButton\" onClick=\"javascript:makeNewWindow4('./clipboard.php?projectid=" . $SavedProjectID . "&view=saved');\">";
			$AdminHTML .= "<br><input type=\"button\" name=\"Generate\" value=\"Copy Art\" class=\"AdminButton\" onClick=\"CopyToClipboard(this, 'saved', " . $SavedProjectID . ");\">";
			$t->set_var("ADMIN_VIEW", $AdminHTML);

			$t->allowVariableToContainBrackets("ADMIN_VIEW");


			// This will allow "view artwork" to show the details for building the artowork.  each layer is separated
			$t->set_var("FORCE_DETAILS", "&forcedetails=yes");
		}
		else{
			$t->set_var("ADMIN_VIEW", "");
			$t->set_var("FORCE_DETAILS", "");
		}
		
	
		
		// If this Saved Project has a tempalte note on it... then we want to show the link to the tempalte underneath the note
		if(preg_match("/^template_\w+/i", $Notes)){
			// Strip off the prefex 'template_';
			$TemplateName = substr($Notes, strlen("template_"));
			
			$templateLink = "<br>" .  WebUtil::htmlOutput("http://".Domain::getWebsiteURLforDomainID($projectObj->getDomainID())."/loadproject.php?usertemplate=". $UserID ."&identify=" . $TemplateName );
		}
		else{
			$templateLink = "";
		}
		

		// Find out if the Saved project is also in the Shopping Cart... If so, show them the shopping card animation
		if(sizeof(ShoppingCart::GetProjectSessionIDsInShoppingCartThatAreSaved($dbCmd2, $SavedProjectID, $user_sessionID)) > 0){
			$t->parse("ShoppingCartIconBLout","ShoppingCartIconBL",false);
			$t->set_var("AddToShoppingCartButtonBLout", "");
		}
		else{
			$t->set_var("ShoppingCartIconBLout", ""); 
			$t->parse("AddToShoppingCartButtonBLout","AddToShoppingCartButtonBL",false);
		}

			
		$t->set_var(array("DATE"=>$DateLastModifiedDisplay,
					"IMAGE"=>$ThumbnailImage,
					"DATE_MODIFIED"=>$dateOnlyModified,
					"TIME_MODIFIED"=>$timeOnlyModified,
					"PRICE"=>number_format($projectObj->getProjectInfoObject()->getCustomerSubTotal(), 2)));
		
		
		$t->set_var(array(
					"PRODUCT_TITLE"=>WebUtil::htmlOutput($prodObj->getProductTitle()),
					"PRODUCT_TITLE_EXT"=>WebUtil::htmlOutput($prodObj->getProductTitleExtension())
					));
		
		// If this product does not have a thumbnail background photo... then the thumbnail image may just be the artwork, without any border or any description.
		// So we are going to delete different block depending on whether there is a background thumbnail image or not.  This will give an HTML artist the most flexibility.
		if($prodObj->checkIfThumbnailBackSaved()){
			$t->parse("ProductDoesHaveThumbnailBackgroundImageBLout","ProductDoesHaveThumbnailBackgroundImageBL", false);
			$t->set_var("ProductHasNoThumbnailBackgroundImageBLout", "");
		}
		else{
			$t->parse("ProductHasNoThumbnailBackgroundImageBLout","ProductHasNoThumbnailBackgroundImageBL", false);
			$t->set_var("ProductDoesHaveThumbnailBackgroundImageBLout", "");
		}
		
		

					

		$t->set_var("INFO_TABLE", $projectObj->getProjectDescriptionTable("smallbody", $varDataConfigureLink));
		$t->set_var("NOTES", WebUtil::htmlOutput($Notes));
		$t->set_var("TEMPLATE_LINK", $templateLink);

		
		$t->parse("itemsBLout","itemsBL",true);

		$t->allowVariableToContainBrackets("DATE");
		$t->allowVariableToContainBrackets("INFO_TABLE");
		$t->allowVariableToContainBrackets("TEMPLATE_LINK");
		
		$print_empty_projects_message = false;
	}

	$resultCounter++;
}


// Set this block so that we can erase the multi-page navigation bar if there aren't multiple pages
$t->set_block("origPage","MultiPageBL","MultiPageBLout");
$t->set_block("origPage","SecondMultiPageBL","SecondMultiPageBLout");

// This means that we have multiple pages of search results
if($resultCounter > $NumberOfResultsToDisplay){

	$SearchOptionType = WebUtil::GetInput("searchoptions", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "B");

	// What are the name/value pairs AND URL  for all of the subsequent pages
	$NV_pairs_URL = "searchoptions=$SearchOptionType&keywords=" . urlencode($keywords) . "&";
	$BaseURL = "./SavedProjects.php";

	// Get a the navigation of hyperlinks to all of the multiple pages
	$NavigateHTML = Navigation::GetNavigationForSearchResults($BaseURL, $resultCounter, $NV_pairs_URL, $NumberOfResultsToDisplay, $offset);


	$t->set_var(array("NAVIGATE"=>$NavigateHTML, 
				"RESULT_DESC"=>$resultCounter, 
				"OFFSET"=>$offset));
				
	$t->allowVariableToContainBrackets("NAVIGATE");
	
	$t->parse("MultiPageBLout","MultiPageBL",true);
	$t->parse("SecondMultiPageBLout","SecondMultiPageBL",true);
}
else{
	$t->set_var("NAVIGATE", "");
	$t->set_var("MultiPageBLout", "");
	$t->set_var("SecondMultiPageBLout", "");
	$t->set_var("OFFSET", "");
	$t->set_var("RESULT_DESC", $resultCounter);
}


if($print_empty_projects_message){

	// This is in case they deleted the last project on a subsequent page.  The offset doesn't exist anymore and so we don't want them to see an empty saved projects message
	if($resultCounter >= $NumberOfResultsToDisplay){
		header("Location: ./SavedProjects.php");
		exit;
	}
	

	if(empty($keywords)){
		$t->set_block("origPage","emptycartBL","emptycartBLout");
		$t->set_var(array("emptycartBLout"=>"<img src=\"images/savedprojects-empty.gif?".time()."\">", "CHECKOUT"=>'&nbsp;'));
		$t->discard_block("origPage", "NoMatchingSearchResultsMessageBL");
	}
	else{
		$t->set_block("origPage","EmptySearchResultsPart1_BL","EmptySearchResultsPart1_BLout");
		$t->set_var("EmptySearchResultsPart1_BLout", "&nbsp;");
		$t->set_block("origPage","EmptySearchResultsPart2_BL","EmptySearchResultsPart2_BLout");
		$t->set_var("EmptySearchResultsPart2_BLout", "<br><br><br><br><img src='./images/NoSearchResults.jpg'><br><br><br><br>");
		$t->discard_block("origPage", "NoSavedProjectsYetMessageBL");
	}
}
else{
	// Since they have saved projects... we are not going to show them the tip for "Visit your order history".
	$t->discard_block("origPage", "OrderHistoryBL");
	$t->discard_block("origPage", "NoSavedProjectsYetMessageBL");
	$t->discard_block("origPage", "NoMatchingSearchResultsMessageBL");
	
}

if($resultCounter == 1)
	$t->set_var(array("PLURAL"=>""));
else
	$t->set_var(array("PLURAL"=>"s"));



// No point in showing them the "search form" if there are less than 2 projects 
if($resultCounter < 2 && $resultCounter > 0 && empty($keywords)){
	$t->set_block("origPage","SearchEngineBL","SearchEngineBLout");
	$t->set_var(array("SearchEngineBLout"=>""));
	
}


if(empty($keywords) && $resultCounter > 0)
	$t->discard_block("origPage", "SearchWarningBL");



$loyaltyObj = new LoyaltyProgram(Domain::getDomainIDfromURL());

$UserControlObj = new UserControl($dbCmd);
$UserControlObj->LoadUserByID($UserID);

if($UserControlObj->getLoyaltyProgram() == "Y"){
	$t->set_var("LOYALTY_SUBTOTAL_DISCOUNT", $loyaltyObj->getLoyaltyDiscountSubtotalPercentage());
	
	// Just come up with an arbitrary weight to see if there is a shipping discount.
	if($loyaltyObj->getLoyaltyDiscountShipping(99) > 0)
		$t->set_var("LOYALTY_SHIPPING_DISCOUNT_FLAG", "true");
	else 
		$t->set_var("LOYALTY_SHIPPING_DISCOUNT_FLAG", "false");
}
else{
	$t->set_var("LOYALTY_SUBTOTAL_DISCOUNT", "0");
	$t->set_var("LOYALTY_SHIPPING_DISCOUNT_FLAG", "false");
}


// Hide the block of HTML for releasing the Override Lock
// Or display the name of the customer we are overriding control of
if(ProjectSaved::CheckForSavedProjectOverride()){
	$t->set_var("OVERRIDE_NAME", "<b>" . WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, $UserID)) . "</b>");
	$t->allowVariableToContainBrackets("OVERRIDE_NAME");
}
else{
	$t->set_block("origPage","SavedProjectOverrideBL","SavedProjectOverrideBLout");
	$t->set_var("SavedProjectOverrideBLout", "");
}

$t->set_var("PROJECT_IDS_ARR", implode(",", $projectIDsArr));
$t->set_var("PRODUCT_IDS_ARR", implode(",", $productIDsArr));

// Hide the special Javascript Block used for administrators
if($admin != "yes"){
	$t->set_block("origPage","JavascriptAdminBL","JavascriptAdminBLout");
	$t->set_var("JavascriptAdminBLout", "");
}

VisitorPath::addRecord("Saved Projects");

// Because the Thumbnail Images come from a PHP script, we want to release the session lock as soon as possible (before the User get the HTML to download thumbnails).
session_write_close();


$t->pparse("OUT","origPage");



?>
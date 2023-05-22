<?

require_once("library/Boot_Session.php");






$dbCmd = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$UserID = $AuthObj->GetUserID();



if(!$AuthObj->CheckForPermission("EDIT_CONTENT"))
	throw new Exception("Permission Denied");



$editContentItemID = WebUtil::GetInput("editContentItemID", FILTER_SANITIZE_INT);
$artworkTemplateID = WebUtil::GetInput("artworkTemplateID", FILTER_SANITIZE_INT);
$searchTerm = WebUtil::GetInput("searchTerm", FILTER_SANITIZE_STRING_ONE_LINE);


// Keep the last search term stick (when the browser gets closed)
if(empty($searchTerm))
	$searchTerm = WebUtil::GetSessionVar("ContentTempalteSearchPhrase", "");
else
	WebUtil::SetSessionVar("ContentTempalteSearchPhrase", $searchTerm);


$errorMessage = "";



$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

if(!empty($action)){
	

	if($action == "selecttemplate"){
		
		if(empty($artworkTemplateID))
			throw new Exception("The Artwork Template ID can not be empty when trying to select one.");
		
		
		print "Template was selected OK";
		exit;
	}
}










$t = new Templatex(".");

$t->set_file("origPage", "ad_contentTemplateSelect-template.html");


$contentItemObj = new ContentItem($dbCmd);

$contentItemObj->loadContentByID($editContentItemID);

$productID = $contentItemObj->getProductIdLink();

if(empty($productID))
	throw new Exception("The Content Category that this content item belongs to has to be associated with a product.");


$productObj = Product::getProductObj($dbCmd, $productID);

$productIDSharedForTemplates = $productObj->getProductIDforTemplates();

$contentCategoryID = $contentItemObj->getContentCategoryID();




// Get an array of search Engine ID's based upon the keywords.
if(empty($searchTerm))
	$TemplateIDArr = array();
else
	$TemplateIDArr = ArtworkTemplate::GetSearchResultsForTempaltes($dbCmd, $searchTerm, "", $productIDSharedForTemplates);



$t->set_block("origPage","TemplateSelectionBl","TemplateSelectionBlout");


$emptySearchResults = true;
foreach($TemplateIDArr as $thisTemplateID){


	// Make sure that this template wasn't already picked (within the same content Item).  This keeps us from creating duplicates.
	$dbCmd->Query("SELECT COUNT(*) FROM contenttemplates INNER JOIN contentitems ON contenttemplates.ContentItemID = contentitems.ID WHERE contentitems.ContentCategoryID=$contentCategoryID AND contenttemplates.TemplateID=$thisTemplateID");
	if($dbCmd->GetValue() != 0)
		continue;


	$dbCmd->Query("SELECT artworkstemplatespreview.ID, Width, Height FROM artworkstemplatespreview 
					INNER JOIN products ON products.ID = artworkstemplatespreview.ProductID 
						WHERE products.DomainID=".Domain::oneDomain()." AND SearchEngineID =" . $thisTemplateID . " ORDER BY ID ASC LIMIT 1");

	// Just because a tempalte ID exists doesn't mean that the Preview images also do.
	if($dbCmd->GetNumRows() == 0)
		continue;
	
	$emptySearchResults = false;
	
	$row = $dbCmd->GetRow();
	
	$templatePreviewID = $row["ID"];
	$imgWidth = $row["Width"];
	$imgHeight = $row["Height"];


	// Show the template preview image.
	$ImagePeviewFileName = ThumbImages::GetTemplatePreviewName($templatePreviewID, "template_searchengine");
	$imageHtml = "<img border='1' style='border-color:#000000' src='./image_preview/" . WebUtil::htmlOutput($ImagePeviewFileName) . "' width='" . $imgWidth . "' height='" . $imgHeight . "'>";

	$t->set_var("TEMPLATE_PREVIEW_IMAGE", $imageHtml);
	$t->allowVariableToContainBrackets("TEMPLATE_PREVIEW_IMAGE");
	
	$t->set_var("TEMPLATE_ID", $thisTemplateID);
	
	
	$t->parse("TemplateSelectionBlout","TemplateSelectionBl",true);
}




$t->set_var("CONTENT_ITEM_ID", $editContentItemID);
$t->set_var("SEARCH_TERM", WebUtil::htmlOutput($searchTerm));



// If there are no search terms then don't show any text in the body.
// Otherwise delete the block of HTML that says no results found... or the template block.
if(empty($searchTerm)){
	$t->discard_block("origPage","EmptyTemplateChoicesBL");
	$t->discard_block("origPage","NoResultsFoundBl");
}
else{

	if($emptySearchResults)
		$t->discard_block("origPage","EmptyTemplateChoicesBL");
	else
		$t->discard_block("origPage","NoResultsFoundBl");
}


$t->pparse("OUT","origPage");





?>
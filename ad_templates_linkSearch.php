<?

require_once("library/Boot_Session.php");


$linkTemplateID = WebUtil::GetInput("linkTemplateID", FILTER_SANITIZE_INT);
$linkTemplateArea = WebUtil::GetInput("linkTemplateArea", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$selectTemplateID = WebUtil::GetInput("selectTemplateID", FILTER_SANITIZE_INT);
$selectTemplateArea = WebUtil::GetInput("selectTemplateArea", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$searchProductId = WebUtil::GetInput("searchProductId", FILTER_SANITIZE_INT);
$categoryID = WebUtil::GetInput("categoryID", FILTER_SANITIZE_INT);
$keywords = WebUtil::GetInput("keywords", FILTER_SANITIZE_STRING_ONE_LINE);

$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);


$dbCmd = new DbCmd();

$t = new Templatex(".");

$t->set_file("origPage", "ad_templates_linkSearch-template.html");


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("MANAGE_TEMPLATES"))
	WebUtil::PrintAdminError("Not Available");


$templateLinksObj = new TemplateLinks();
	
$linkTemplateView = $templateLinksObj->getViewTypeFromTemplateArea($linkTemplateArea);

// Make sure the user has permission to edit tempaltes on this domain.
$templateProductID = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $linkTemplateID, $linkTemplateView);
$domainIDofTemplate = Product::getDomainIDfromProductID($dbCmd, $templateProductID);

if(!$AuthObj->CheckIfUserCanViewDomainID($domainIDofTemplate))
	throw new Exception("User can not edit these templates.");

$productObj = new Product($dbCmd, $searchProductId);

if($productObj->getProductIDforTemplates() != $searchProductId)
	throw new Exception("The search template ProductID must be able to create its own templates");
	
if($productObj->getDomainID() != $domainIDofTemplate)
	throw new Exception("The Domain IDs must match between the Product ID search and the source template.");

	
$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if(!empty($action)){
	
	WebUtil::checkFormSecurityCode();
	
	if($action == "linkTemplate"){
		
		$templateLinksObj->linkTemplatesTogether($UserID, $linkTemplateArea, $linkTemplateID, $selectTemplateArea, $selectTemplateID);

		header("Location: " . WebUtil::FilterURL("./ad_templates_link.php?templateID=" . $linkTemplateID . "&templateArea=" . $linkTemplateArea . "&nocache=". time()));
		exit;
	}
	else if($action == "autoTransfer"){
		
		// Convert the artwork from the template that we are trying to replicate into a new product ID.
		$sourceTempalteArt = ArtworkLib::GetArtXMLfile($dbCmd, $linkTemplateView, $linkTemplateID);
		
		$artworkConversionObj = new ArtworkConversion($dbCmd);
		$artworkConversionObj->stretchTemplateImages(false);
		$artworkConversionObj->setFromArtwork($templateProductID, $sourceTempalteArt);
		$artworkConversionObj->setToArtwork($searchProductId);
	
		
		$InsertArr["ArtFile"] = $artworkConversionObj->getConvertedArtwork();
		$InsertArr["ProductID"] = $searchProductId;
		$InsertArr["Sort"] = "M";
		$newSearchEngineID = $dbCmd->InsertQuery("artworksearchengine", $InsertArr);
		
		// Insert the auto-keyword from the transfer
		$autoTransferKeyword = "T" . $linkTemplateArea . $linkTemplateID;
		$keywordInsert["TempKw"] = $autoTransferKeyword;
		$keywordInsert["TemplateID"] = $newSearchEngineID;
		$dbCmd->InsertQuery("templatekeywords", $keywordInsert);
		
		// Link the templates together.
		$templateLinksObj->linkTemplatesTogether($UserID, $linkTemplateArea, $linkTemplateID, TemplateLinks::TEMPLATE_AREA_SEARCH_ENGINE, $newSearchEngineID);
		
		// Now redirect to the editing tool for the new template.
		// Make sure that the continue URL takes people back to the search engine results page afterwards so they can use the clipboard or add other keywords.
		$redirectURL = "ad_templates_artwork.php?templateid=". $newSearchEngineID ."&productidforreturn=". $searchProductId ."&editorview=template_searchengine&searchkeywords=" . urlencode($autoTransferKeyword);
		
		header("Location: " . WebUtil::FilterURL($redirectURL));
		exit;
	}
	else{
		throw new Exception("Illegal action");
	}
	
}


if($view == "categorySearch"){
	
	$t->set_block("origPage","TemplateResultBL","TemplateResultBLout");
	
	$dbCmd->Query("SELECT ArtworkID FROM artworkstemplates WHERE CategoryID=".intval($categoryID)." AND ProductID=$searchProductId ORDER BY IndexID ASC");
	$templateIdsArr = $dbCmd->GetValueArr();
	
	$counter = 0;
	foreach($templateIdsArr as $thisTemplateID){
		
		if($thisTemplateID == $linkTemplateID && $linkTemplateArea == TemplateLinks::TEMPLATE_AREA_CATEGORY)
			continue;
		
		$t->set_var("TEMP_AREA", TemplateLinks::TEMPLATE_AREA_CATEGORY);
		$t->set_var("TEMP_ID", $thisTemplateID);
		
		// Get the Thumbnail Image
		$thumbPeviewFileName = "./image_preview/" . ThumbImages::GetTemplatePreviewFnameAdmin($dbCmd, $thisTemplateID, $templateLinksObj->getViewTypeFromTemplateArea(TemplateLinks::TEMPLATE_AREA_CATEGORY));
		$t->set_var("THUMBNAIL_PHOTO", WebUtil::htmlOutput($thumbPeviewFileName));
		
		$t->parse("TemplateResultBLout", "TemplateResultBL", true);
		
		$counter++;
	}
	
	$t->discard_block("origPage", "DefaultDescriptionBL");
	
	if(empty($counter))
		$t->set_var("TemplateResultBLout", "No Templates Available in This Category");
}
else if($view == "keywordSearch"){
	
	$t->set_block("origPage","TemplateResultBL","TemplateResultBLout");

	$templateIdsArr = ArtworkTemplate::GetSearchResultsForTempaltes($dbCmd, $keywords, "", $searchProductId);
	
	$counter = 0;
	foreach($templateIdsArr as $thisTemplateID){
		
		if($thisTemplateID == $linkTemplateID && $linkTemplateArea == TemplateLinks::TEMPLATE_AREA_SEARCH_ENGINE)
			continue;
		
		$t->set_var("TEMP_AREA", TemplateLinks::TEMPLATE_AREA_SEARCH_ENGINE);
		$t->set_var("TEMP_ID", $thisTemplateID);
		
		// Get the Thumbnail Image
		$thumbPeviewFileName = "./image_preview/" . ThumbImages::GetTemplatePreviewFnameAdmin($dbCmd, $thisTemplateID, $templateLinksObj->getViewTypeFromTemplateArea(TemplateLinks::TEMPLATE_AREA_SEARCH_ENGINE));
		$t->set_var("THUMBNAIL_PHOTO", WebUtil::htmlOutput($thumbPeviewFileName));
		
		$t->parse("TemplateResultBLout", "TemplateResultBL", true);
		
		$counter++;
	}
	
	$t->discard_block("origPage", "DefaultDescriptionBL");
	
	if(empty($counter))
		$t->set_var("TemplateResultBLout", "No Templates Available With Matching Keywords");
}
else if($view == "default"){
	
	$t->set_block("origPage","TemplateResultBL","TemplateResultBLout");
	

	$keywordList = "";
	
	if($linkTemplateArea == TemplateLinks::TEMPLATE_AREA_SEARCH_ENGINE){
		
		$dbCmd->Query("SELECT TempKw FROM templatekeywords 
				INNER JOIN artworksearchengine on templatekeywords.TemplateID = artworksearchengine.ID 
				WHERE templatekeywords.TemplateID=" . intval($linkTemplateID) . " ORDER BY TempKw ASC");
	
		// Don't use the GetValue() method in case somone enters '0' for a keyword.
		$kwCounter = 0;
		while($thisRow = $dbCmd->GetRow()){
			if(strtolower($thisRow["TempKw"]) == "all")
				continue;
			$keywordList .= $thisRow["TempKw"] . " ";
			
			$kwCounter++;
			
			if($kwCounter > 8)
				break;
		}
	}

	$keywordList = trim($keywordList);

	$templateIdsArr = array();
	
	// In case we have done an auto-tranfer in the past... make sure that shows up first
	$autoTransferTemplateIdArr = ArtworkTemplate::GetSearchResultsForTempaltes($dbCmd, ("T" . $linkTemplateArea . $linkTemplateID), "", $searchProductId);
	
	if(!empty($keywordList))
		$templateIdsArr = ArtworkTemplate::GetSearchResultsForTempaltes($dbCmd, $keywordList, "", $searchProductId);
	
	$templateIdsArr = array_merge($autoTransferTemplateIdArr, $templateIdsArr);
	
	$counter = 0;
	foreach($templateIdsArr as $thisTemplateID){
		
		if($thisTemplateID == $linkTemplateID)
			continue;
		
		$t->set_var("TEMP_AREA", TemplateLinks::TEMPLATE_AREA_SEARCH_ENGINE);
		$t->set_var("TEMP_ID", $thisTemplateID);
		
		// Get the Thumbnail Image
		$thumbPeviewFileName = "./image_preview/" . ThumbImages::GetTemplatePreviewFnameAdmin($dbCmd, $thisTemplateID, $templateLinksObj->getViewTypeFromTemplateArea(TemplateLinks::TEMPLATE_AREA_SEARCH_ENGINE));
		$t->set_var("THUMBNAIL_PHOTO", WebUtil::htmlOutput($thumbPeviewFileName));
		
		$counter++;
		
		if($counter > 30)
			break;
		
		$t->parse("TemplateResultBLout", "TemplateResultBL", true);
	}
	
	
	if(empty($counter)){
		$t->set_var("TemplateResultBLout", "");
		$t->discard_block("origPage", "DefaultDescriptionBL");

	}
	
}
else{
	throw new Exception("Error with View Type.");
}




$templateCategoriesArr = array("0"=>" --- Category Search --- ");
$dbCmd->Query("SELECT CategoryName, CategoryID FROM templatecategories where ProductID=".intval($searchProductId)." ORDER BY IndexID ASC");
while ($row = $dbCmd->GetRow())
	$templateCategoriesArr[$row["CategoryID"]] = $row["CategoryName"];

$t->set_var("CATEGORY_SELECT", Widgets::buildSelect($templateCategoriesArr, array($categoryID)));
$t->allowVariableToContainBrackets("CATEGORY_SELECT");


$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());
$t->set_var("PRODUCT_NAME", WebUtil::htmlOutput($productObj->getProductTitleWithExtention()));

$t->set_var("KEYWORDS", $keywords);

$t->set_var("LINK_TEMPLATE_AREA", $linkTemplateArea);
$t->set_var("LINK_TEMPLATE_ID", $linkTemplateID);
$t->set_var("SEARCH_PRODUCT_ID", $searchProductId);

$t->pparse("OUT","origPage");




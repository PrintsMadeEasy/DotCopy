<?

require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$UserID = $AuthObj->GetUserID();



if(!$AuthObj->CheckForPermission("EDIT_CONTENT"))
	throw new Exception("Permission Denied");


$contentItemObj = new ContentItem($dbCmd);


// Get input from the URL (and set any Default values for "new entries")



$categoryID = WebUtil::GetInput("categoryID", FILTER_SANITIZE_INT);
$editContentItemID = WebUtil::GetInput("editContentItemID", FILTER_SANITIZE_INT);
$contentTitle = WebUtil::GetInput("contentTitle", FILTER_SANITIZE_STRING_ONE_LINE);
$metaTitle = WebUtil::GetInput("metaTitle", FILTER_SANITIZE_STRING_ONE_LINE);
$metaDescription = WebUtil::GetInput("metaDescription", FILTER_SANITIZE_STRING_ONE_LINE);
$imageAlign = WebUtil::GetInput("imageAlign", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "T");
$imageHyperlink = WebUtil::GetInput("imageHyperlink", FILTER_SANITIZE_URL);
$descriptionFormat = WebUtil::GetInput("descriptionFormat", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "text");
$contentDescription = WebUtil::GetInput("contentDescription", FILTER_UNSAFE_RAW);
$contentFooter = WebUtil::GetInput("contentFooter", FILTER_UNSAFE_RAW);
$footerFormat = WebUtil::GetInput("footerFormat", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "text");
$activeContent = WebUtil::GetInput("activeContent", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "Y");
$contentLinks = WebUtil::GetInput("contentLinks", FILTER_UNSAFE_RAW);
$htmlHeader = WebUtil::GetInput("htmlHeader", FILTER_UNSAFE_RAW);


$viewType = WebUtil::GetInput("viewType", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$saved = WebUtil::GetInput("saved", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);


$errorMessage = "";



$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

if(!empty($action)){
	
	WebUtil::checkFormSecurityCode();

	if($action == "save"){

		$existingTitleID = $contentItemObj->checkIfContentTitleExists($contentTitle);

		// Detect what will cause an error within our Object and report to to the user.
		if((!empty($existingTitleID) && $viewType == "new") || (!empty($existingTitleID) && $existingTitleID != $editContentItemID  && $viewType == "edit"))
			$errorMessage .= "* The Content Title is already in use.  Content Titles must be unique.<br>";

		$linksErrorMsg = $contentItemObj->setLinks($contentLinks);
		if(!empty($linksErrorMsg))
			$errorMessage .= "* " . $linksErrorMsg . "<br>";


		$allDirectivesArr = $contentItemObj->getAllDirectivesFromAllLinks();
		if(in_array("NOITEMS", $allDirectivesArr))
			$errorMessage .= "* You can not add the directive [NOITEMS] on a Content Item link itself.<br>";


		$imageBinaryData = null;

		// If they are uploading a file... Check it for errors.
		if(isset($_FILES["image"]["size"]) && !empty($_FILES["image"]["size"])){
		
			// Open the file from POST data 
			$imageBinaryData = fread(fopen($_FILES["image"]["tmp_name"], "r"), filesize($_FILES["image"]["tmp_name"]));
				
			$imgError = $contentItemObj->checkImage($imageBinaryData);
			
			if(!empty($imgError))
				$errorMessage .= "* " . $imgError . "<br>";
		}
	




		if(empty($errorMessage)){

			// If we are editing.. then load the existing details into memory.
			if($viewType == "edit")
				$contentItemObj->loadContentByID($editContentItemID);
				

			$contentItemObj->setContentCategoryID($categoryID);
			$contentItemObj->setTitle($contentTitle);
			$contentItemObj->setDescription($contentDescription);
			$contentItemObj->setMetaTitle($metaTitle);
			$contentItemObj->setMetaDescription($metaDescription);
			$contentItemObj->setFooter($contentFooter);
			$contentItemObj->setDescriptionFormat($descriptionFormat);
			$contentItemObj->setFooterFormat($footerFormat);
			$contentItemObj->setImageHyperlink($imageHyperlink);
			$contentItemObj->setImageAlign($imageAlign);
			$contentItemObj->setImage($imageBinaryData);
			$contentItemObj->setLinks($contentLinks);
			$contentItemObj->setHeaderHTML($htmlHeader);
			$contentItemObj->setActive($activeContent == "N" ? false : true);
			


			if($viewType == "new"){

				$editContentItemID = $contentItemObj->insertNewContentItem($UserID);
			}
			else if($viewType == "edit"){

				$contentItemObj->updateContentItem($UserID);
			}
			else{
				throw new Exception("Illegal ViewType for content Category");
			}

			// After Saving Content (whether new or edit)... we are alway going to redirect back to the GET Url for editing.
			// That will give them the option to refresh the page (without the Do you want to re-post data).
			// We only do this if there is no error though.
			header("Location: ./ad_contentItem.php?viewType=edit&editContentItemID=" . $editContentItemID . "&saved=yes&nocaching=" . time());
			exit;

		}
	}
	else if($action == "removeImage"){
	
		ContentItem::RemoveImage($dbCmd, $editContentItemID);
	}
	else if($action == "removeContentItem"){
	
		// Show an error message if the content category category is not childless
		$contentItemObj = new ContentItem($dbCmd);
		$contentItemObj->loadContentByID($editContentItemID);
		if($contentItemObj->checkForChildrenWithinContentItem())
			WebUtil::PrintAdminError("You must remove all Content Templates within this Content Item prior to deleting.");
	
		$categoryID = $contentItemObj->getContentCategoryID();

		ContentItem::RemoveContent($dbCmd, $editContentItemID);
		
		header("Location: ./ad_contentCategory.php?viewType=edit&editCategoryID=" . $categoryID . "&nocache=" . time());
		exit;

	}
	else{
		throw new Exception("Illegal Action called while editing a Content Category.");
	}
}





// Keep people from switching domains after they are already editing.
if(!empty($editContentItemID)){
	Domain::enforceTopDomainID(ContentItem::getDomainIDofContentItem($dbCmd, $editContentItemID));
}






$t = new Templatex(".");

$t->set_file("origPage", "ad_contentItem-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


if($viewType == "new"){

	// When we are adding a new Content Item... we may or may not be provided with the category ID.
	if(!empty($categoryID))
		$contentItemObj->setContentCategoryID($categoryID);

	$t->set_var("NEW_OR_EDIT", "new");
	$t->set_var("ITEM_EDIT_ID", "");
	
	$t->discard_block("origPage","LiveContentLinkBL");
	
	$t->set_var("OTHER_CONTENT_ITEMS", "");

}
else if($viewType == "edit"){


	// Keep a record of the visit to this page by the user.
	NavigationHistory::recordPageVisit($dbCmd, $UserID, "CntItem", $editContentItemID);



	$t->set_var("NEW_OR_EDIT", "edit");
	$t->set_var("ITEM_EDIT_ID", $editContentItemID);
	

	$contentItemObj->loadContentByID($editContentItemID);
	
	
	$categoryID = $contentItemObj->getContentCategoryID();
	
	
	$t->set_var("LIVE_CONTENT_LINK", $contentItemObj->getURLforContent(true));
	
	
	
	// Only Erase our HTTP inputs if there hasn't been an Error message.
	// Otherwise we would erase anything that was typed in by the user.
	// We should give them the chance to correct the error.
	
	if(empty($errorMessage)){
		$contentTitle = $contentItemObj->getTitle();
		$metaTitle = $contentItemObj->getMetaTitle();
		$metaDescription = $contentItemObj->getMetaDescription();
		$imageAlign = $contentItemObj->getImageAlign();
		$imageHyperlink = $contentItemObj->getImageHyperlink();
		$descriptionFormat = $contentItemObj->descriptionIsHTMLformat() ? "html" : "text";
		$footerFormat = $contentItemObj->footerIsHTMLformat() ? "html" : "text";
		$activeContent = $contentItemObj->checkIfActive() ? "Y" : "N";
		$contentDescription = $contentItemObj->getDescription();
		$contentFooter = $contentItemObj->getFooter();
		$contentLinks = $contentItemObj->getLinksForEditing();
		$htmlHeader = $contentItemObj->getHeaderHTML();
	}
	
	
	
	// Build a Drop down menu that links to other Content Items within the same category.
	$dropDownArr = array();
	
	$contentItemIDs = ContentCategory::GetContentItemsWithinCategory($dbCmd, $categoryID);
	
	foreach($contentItemIDs as $thisItemID){
	
		$contentCatTitle = ContentItem::GetContentItemTitle($dbCmd, $thisItemID);
		
		$cntItemObj = new ContentItem($dbCmd);
		$cntItemObj->loadContentByID($thisItemID);
		
		if(!$cntItemObj->checkIfActive())
			continue;
		
		$dropDownArr[$thisItemID] = $contentCatTitle;
	}
	
	$dropDrownMenu = Widgets::buildSelect($dropDownArr, $editContentItemID, "contentTitles", "AdminDropDown", "ChangeContentItem(this.value)");
	$t->set_var("OTHER_CONTENT_ITEMS", "<font class='reallysmallbody'>Jump to another Content Item in this Category.</font><br>" . $dropDrownMenu);
	$t->allowVariableToContainBrackets("OTHER_CONTENT_ITEMS");
	
	
}
else{

	throw new Exception("Illegal View Type");
}






// Build the Drop down menu that shows what content Category the Content Item belongs to.
// If we don't have a category ID yet... then make the first choice tell them to select a category.
$contentCategoriesArr = ContentCategory::getAllCategories($dbCmd);

if(empty($categoryID)){
	$contenCatChoicesArr = array("0"=>"Select a Category");
	$contenCatChoicesArr = array_merge($contenCatChoicesArr, $contentCategoriesArr);
	$categoryID = "0";
	
	$t->discard_block("origPage", "ContentCategoryLinkBL");
	$t->discard_block("origPage", "categoryDisabledBL");
	
	
}
else{
	
	
	$contenCatChoicesArr = $contentCategoriesArr;
	

	
	$contentCategoryObj = new ContentCategory($dbCmd);
	$contentCategoryObj->loadContentByID($categoryID);
	

	// So that we can tell Javscript whether this Content Item is linked up with Product ID.
	$productIDlink = $contentCategoryObj->getProductID();
	if(empty($productIDlink))
		$t->set_var("EMPTY_PRODUCT_FLAG_JS", "true");
	else
		$t->set_var("EMPTY_PRODUCT_FLAG_JS", "false");
	
	if($contentCategoryObj->checkIfActive())
		$t->discard_block("origPage", "categoryDisabledBL");
	
	$t->set_var("CONTENT_CATEGORY_NAME", WebUtil::htmlOutput($contentCategoryObj->getTitle()));
}


$t->set_var("CONTENT_CATEGORY_ID", $categoryID);


$contenCategoryDropDown = Widgets::buildSelect($contenCatChoicesArr, $categoryID);


$t->set_var("CONTENT_CATEGORY_DROPDOWN", $contenCategoryDropDown);
$t->allowVariableToContainBrackets("CONTENT_CATEGORY_DROPDOWN");


// Show the "Saved Successfully" message if there was no error message
if($saved != "yes")
	$t->discard_block("origPage", "SavedMessageBL");


if($descriptionFormat == "html"){
	$t->set_var(array(
		"DESC_FORMAT_TEXT_CHECKED"=>"",
		"DESC_FORMAT_HTML_CHECKED"=>"checked"
		));
}
else if($descriptionFormat == "text"){
	$t->set_var(array(
		"DESC_FORMAT_TEXT_CHECKED"=>"checked",
		"DESC_FORMAT_HTML_CHECKED"=>""
		));
}
else{
	throw new Exception("Error with Description format.");
}




if($footerFormat == "html"){
	$t->set_var(array(
		"FOOTER_FORMAT_TEXT_CHECKED"=>"",
		"FOOTER_FORMAT_HTML_CHECKED"=>"checked"
		));
}
else if($footerFormat == "text"){
	$t->set_var(array(
		"FOOTER_FORMAT_TEXT_CHECKED"=>"checked",
		"FOOTER_FORMAT_HTML_CHECKED"=>""
		));
}
else{
	throw new Exception("Error with Footer format.");
}



if($activeContent == "Y"){
	$t->set_var(array(
		"ACTIVE_NO_CHECKED"=>"",
		"ACTIVE_YES_CHECKED"=>"checked"
		));
}
else if($activeContent == "N"){
	$t->set_var(array(
		"ACTIVE_NO_CHECKED"=>"checked",
		"ACTIVE_YES_CHECKED"=>""
		));
}
else{
	throw new Exception("Error with active content format.");
}


// Build the drop down for the Image Alignment
$imageAlignmentArr = array("T"=>"Top", "TL"=>"Top Left", "TR"=>"Top Right", "B"=>"Bottom", "BL"=>"Bottom Left", "BR"=>"Bottom Right");
$imageAlignmentDropDown = Widgets::buildSelect($imageAlignmentArr, $imageAlign);


if(!empty($errorMessage))
	$errorMessage = "Error!<br>" . $errorMessage . "<br><br>";



if($viewType == "edit" && $contentItemObj->checkIfImageStored()){

	$t->set_var("CURRENT_IMAGE", "<img src='" . WebUtil::htmlOutput($contentItemObj->getURLforImage(true)) . "'>");
	$t->allowVariableToContainBrackets("CURRENT_IMAGE");
}
else{
	$t->discard_block("origPage","ImageUploadedBL");
}




if($viewType == "new"){
	$t->discard_block("origPage","TimeStampBL");
}
else{
	$t->set_var("CREATED_BY", WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, $contentItemObj->getCreatedByUserID())));
	$t->set_var("LAST_EDITED_BY", WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, $contentItemObj->getLastEditedByUserID())));
	$t->set_var("CREATED_ON", date("F j, Y, g:i a", $contentItemObj->getCreatedOnDate()));
	$t->set_var("LAST_EDITED_ON", date("F j, Y, g:i a", $contentItemObj->getLasteEditedOnDate()));
}



$t->set_var("CONTENT_TITLE", WebUtil::htmlOutput($contentTitle));
$t->set_var("META_TITLE", WebUtil::htmlOutput($metaTitle));
$t->set_var("META_DESCRIPTION", WebUtil::htmlOutput($metaDescription));
$t->set_var("IMAGE_HYPERLINK", WebUtil::htmlOutput($imageHyperlink));
$t->set_var("CONTENT_DESCRIPTION", WebUtil::htmlOutput($contentDescription));
$t->set_var("CONTENT_FOOTER", WebUtil::htmlOutput($contentFooter));
$t->set_var("MESSAGE", $errorMessage);
$t->set_var("IMAGE_ALIGN_DROPDOWN", $imageAlignmentDropDown);
$t->set_var("ITEM_EDIT_ID", $editContentItemID);
$t->set_var("CONTENT_LINKS", WebUtil::htmlOutput($contentLinks));
$t->set_var("HTML_HEADER", WebUtil::htmlOutput($htmlHeader));

$t->allowVariableToContainBrackets("IMAGE_ALIGN_DROPDOWN");
$t->allowVariableToContainBrackets("MESSAGE");







// Show a list of Template Contents (within this Content Item).... if there are any.
if(empty($editContentItemID)){
	$t->discard_block("origPage","TemplateListBL");

}
else{
	$contentTemplateIDs = ContentItem::GetContentTemplatesIDsWithin($dbCmd, $editContentItemID);

	if(empty($contentTemplateIDs)){
		$t->discard_block("origPage","TemplateListBL");
	
	}
	else{
	
		$t->set_block("origPage","ContentTemplateBL","ContentTemplateBLout");


		$contentTemplateObj = new ContentTemplate($dbCmd);

		// Loop through all of the Content ID's and create links to each Content Item
		foreach($contentTemplateIDs as $thisContentTemplateID){
		
			// Just in case someone deleted a Template or something.
			if(!$contentTemplateObj->loadContentByID($thisContentTemplateID))
				continue;
			
			$contentTemplateObj->preferTemplateSize("small");
			
			$imageHtml = "<img border='1' style='border-color:#000000' src='" . WebUtil::htmlOutput($contentTemplateObj->getURLforImage(true)) . "'>";
			
			$templateTitle = $contentTemplateObj->getTitle();
			
			$activeIndicator = "";
			if(!$contentTemplateObj->checkIfActive())
				$activeIndicator = "<font class='SmallBody' style='color=\"#cc0000\"'><i>inactive - </i></font> ";
			
			if(empty($templateTitle))
				$templateTitle = $activeIndicator . "<font color='#FF00FF'><b>Title is Missing</b>";
			else
				$templateTitle = $activeIndicator . "<a href='./ad_contentTemplate.php?viewType=edit&editContentTemplateID=" . $thisContentTemplateID . "'>" . WebUtil::htmlOutput($templateTitle) . "</a>";
			
			
			$t->set_var("TEMPLATE_TITLE", $templateTitle);
			$t->allowVariableToContainBrackets("TEMPLATE_TITLE");
			
			$t->set_var("CONTENT_TEMPLATE_KB", round($contentTemplateObj->getDescriptionBytes() / 1024, 1));
			$t->set_var("CONTENT_TEMPLATE_DESC", WebUtil::htmlOutput($contentTemplateObj->getShortDescription()));
			$t->set_var("CONTENT_TEMPLATE_ID", $thisContentTemplateID);
			$t->set_var("CONTENT_TEMPLATE_IMAGE", $imageHtml);
			$t->allowVariableToContainBrackets("CONTENT_TEMPLATE_IMAGE");
			
			$t->set_var("LAST_EDITED_BY", WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, $contentTemplateObj->getLastEditedByUserID())));
			$t->set_var("LAST_EDITED_ON", date("F j, Y, g:i a", $contentTemplateObj->getLasteEditedOnDate()));
			
			
			$linksArr = $contentTemplateObj->getLinksArr(false, true);
			$linksStr = "";
			
			foreach($linksArr as $linkSubject => $linkURL){
				
				// Make sure they don't do an XSS injection
				$linkURL = preg_replace("/(\<|\>)/", "", $linkURL);
				
				if(!empty($linksStr))
					$linksStr .= " - ";
				$linksStr .= "<nobr><a href='$linkURL' target='top'>".WebUtil::htmlOutput($linkSubject)."</a></nobr>";
			}
			
			if(!empty($linksStr))
				$linksStr = "<b>LINKS:</b> " . $linksStr;
			
			$t->set_var("TEMPLATE_LINKS", $linksStr);
			$t->allowVariableToContainBrackets("TEMPLATE_LINKS");
			

			$t->parse("ContentTemplateBLout","ContentTemplateBL",true);

		}
	}
}







$t->pparse("OUT","origPage");





?>
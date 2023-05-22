<?require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$UserID = $AuthObj->GetUserID();



if(!$AuthObj->CheckForPermission("EDIT_CONTENT"))
	throw new Exception("Permission Denied");


$contentCategoryObj = new ContentCategory($dbCmd);


// Get input from the URL (and set any Default values for "new entries")
$editCategoryID = WebUtil::GetInput("editCategoryID", FILTER_SANITIZE_INT);
$contentTitle = WebUtil::GetInput("contentTitle", FILTER_SANITIZE_STRING_ONE_LINE);
$parentLinkDesc = WebUtil::GetInput("parentLinkDesc", FILTER_SANITIZE_STRING_ONE_LINE);
$parentHyperlink = WebUtil::GetInput("parentHyperlink", FILTER_SANITIZE_URL);
$imageAlign = WebUtil::GetInput("imageAlign", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "T");
$imageHyperlink = WebUtil::GetInput("imageHyperlink", FILTER_SANITIZE_URL);
$productLink = WebUtil::GetInput("productLink", FILTER_SANITIZE_INT, "0");
$descriptionFormat = WebUtil::GetInput("descriptionFormat", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "text");
$activeContent = WebUtil::GetInput("activeContent", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "Y");
$contentDescription = WebUtil::GetInput("contentDescription", FILTER_UNSAFE_RAW);
$contentLinks = WebUtil::GetInput("contentLinks", FILTER_UNSAFE_RAW);
$htmlHeader = WebUtil::GetInput("htmlHeader", FILTER_UNSAFE_RAW);

$viewType = WebUtil::GetInput("viewType", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$saved = WebUtil::GetInput("saved", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);


$errorMessage = "";


$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

if(!empty($action)){
	
	WebUtil::checkFormSecurityCode();
	

	if($action == "save"){

		$existingTitleID = $contentCategoryObj->checkIfContentTitleExists($contentTitle);

		// Detect what will cause an error within our Object and report to to the user.
		if((!empty($existingTitleID) && $viewType == "new") || (!empty($existingTitleID) && $existingTitleID != $editCategoryID  && $viewType == "edit"))
			$errorMessage .= "* The Category Title is already in use.  Category Titles must be unique.<br>";

		$linksErrorMsg = $contentCategoryObj->setLinks($contentLinks);
		if(!empty($linksErrorMsg))
			$errorMessage .= "* " . $linksErrorMsg . "<br>";


		$imageBinaryData = null;

		// If they are uploading a file... Check it for errors.
		if(isset($_FILES["image"]["size"]) && !empty($_FILES["image"]["size"])){
		
			// Open the file from POST data 
			$imageBinaryData = fread(fopen($_FILES["image"]["tmp_name"], "r"), filesize($_FILES["image"]["tmp_name"]));
				
			$imgError = $contentCategoryObj->checkImage($imageBinaryData);
			
			if(!empty($imgError))
				$errorMessage .= "* " . $imgError . "<br>";
		}
	




		if(empty($errorMessage)){

			// If we are editing.. then load the existing details into memory.
			if($viewType == "edit")
				$contentCategoryObj->loadContentByID($editCategoryID);
				
			$contentCategoryObj->setTitle($contentTitle);
			$contentCategoryObj->setDescription($contentDescription);
			$contentCategoryObj->setDescriptionFormat($descriptionFormat);
			$contentCategoryObj->setImageHyperlink($imageHyperlink);
			$contentCategoryObj->setImageAlign($imageAlign);
			$contentCategoryObj->setProductID($productLink);
			$contentCategoryObj->setParentHyperlink($parentHyperlink);
			$contentCategoryObj->setParentLinkDesc($parentLinkDesc);
			$contentCategoryObj->setImage($imageBinaryData);
			$contentCategoryObj->setLinks($contentLinks);
			$contentCategoryObj->setHeaderHTML($htmlHeader);
			$contentCategoryObj->setActive($activeContent == "N" ? false : true);
			


			if($viewType == "new"){

				$editCategoryID = $contentCategoryObj->insertNewContentCategory($UserID);
			}
			else if($viewType == "edit"){
			
				if(empty($productLink) && $contentCategoryObj->countOfContentTemplatesUnder() > 0)
					WebUtil::PrintAdminError("You can't change the Product Link to 'No Association' unless all Content Templates have been removed.");
			

				$contentCategoryObj->updateContentCategory($UserID);
			}
			else{
				throw new Exception("Illegal ViewType for content Category");
			}

			// After Saving Content (whether new or edit)... we are alway going to redirect back to the GET Url for editing.
			// That will give them the option to refresh the page (without the Do you want to re-post data).
			// We only do this if there is no error though.
			header("Location: ./ad_contentCategory.php?viewType=edit&editCategoryID=" . $editCategoryID . "&saved=yes&nocaching=" . time());
			exit;

		}
	}
	else if($action == "removeImage"){
	
		ContentCategory::RemoveImage($dbCmd, $editCategoryID);
	}
	else if($action == "removeContentCategory"){
	
		// Show an error message if the content category category is not childless
		$contentCategoryObj = new ContentCategory($dbCmd);
		$contentCategoryObj->loadContentByID($editCategoryID);
		if($contentCategoryObj->checkForChildrenWithinCategory())
			WebUtil::PrintAdminError("You must remove all Content Items within this Category prior to deleting this category.");
	
		ContentCategory::RemoveContent($dbCmd, $editCategoryID);
		
		header("Location: ./ad_contentCategoryList.php?nocache=" . time());
		exit;

	}
	else{
		throw new Exception("Illegal Action called while editing a Content Category.");
	}
}




// Keep people from switching domains after they are already editing.
if(!empty($editCategoryID)){
	Domain::enforceTopDomainID(ContentCategory::getDomainIDofContentCategory($dbCmd, $editCategoryID));
}





$t = new Templatex(".");

$t->set_file("origPage", "ad_contentCategory-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


if($viewType == "new"){

	$t->set_var("NEW_OR_EDIT", "new");
	$t->set_var("CATEGORY_EDIT_ID", "");
	
	$t->discard_block("origPage","LiveContentLinkBL");

}
else if($viewType == "edit"){

	// Keep a record of the visit to this page by the user.
	NavigationHistory::recordPageVisit($dbCmd, $UserID, "CntCat", $editCategoryID);

	$t->set_var("NEW_OR_EDIT", "edit");
	$t->set_var("CATEGORY_EDIT_ID", $editCategoryID);
	

	$contentCategoryObj->loadContentByID($editCategoryID);
	
	
	$t->set_var("LIVE_CONTENT_LINK", $contentCategoryObj->getURLforContent(true));
	
	
	
	// Only Erase our HTTP inputs if there hasn't been an Error message.
	// Otherwise we would erase anything that was typed in by the user.
	// We should give them the chance to correct the error.
	
	if(empty($errorMessage)){
		$contentTitle = $contentCategoryObj->getTitle();
		$parentLinkDesc = $contentCategoryObj->getParentLinkDesc();
		$parentHyperlink = $contentCategoryObj->getParentHyperlink();
		$imageAlign = $contentCategoryObj->getImageAlign();
		$imageHyperlink = $contentCategoryObj->getImageHyperlink();
		$productLink = $contentCategoryObj->getProductID();
		$descriptionFormat = $contentCategoryObj->descriptionIsHTMLformat() ? "html" : "text";
		$contentDescription = $contentCategoryObj->getDescription();
		$activeContent = $contentCategoryObj->checkIfActive() ? "Y" : "N";
		$contentLinks = $contentCategoryObj->getLinksForEditing();
		$htmlHeader = $contentCategoryObj->getHeaderHTML();
	}
	
	
}
else{

	throw new Exception("Illegal View Type");
}




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


// Get a list of Product ID's ... this will be used for for Content/Tempalte puposes
$productIDarr = Product::getActiveProductIDsArr($dbCmd, Domain::oneDomain());
$productIDofTemplates = array("0"=>"No Product Relation");

foreach($productIDarr as $productID){

	$productObj = Product::getProductObj($dbCmd, $productID);

	$productIDofTemplates["$productID"] = $productObj->getProductTitleWithExtention();
}

$productsDropDown = Widgets::buildSelect($productIDofTemplates, $productLink);


if(!empty($errorMessage))
	$errorMessage = "Error!<br>" . $errorMessage . "<br><br>";



if($viewType == "edit" && $contentCategoryObj->checkIfImageStored()){

	$t->set_var("CURRENT_IMAGE", "<img src='" . WebUtil::htmlOutput($contentCategoryObj->getURLforImage(true)) . "'>");
	$t->allowVariableToContainBrackets("CURRENT_IMAGE");
}
else{
	$t->discard_block("origPage","ImageUploadedBL");
}




$t->set_var("CONTENT_TITLE", WebUtil::htmlOutput($contentTitle));
$t->set_var("PARENT_LINK_DESC", WebUtil::htmlOutput($parentLinkDesc));
$t->set_var("PARENT_HYPERLINK", WebUtil::htmlOutput($parentHyperlink));
$t->set_var("IMAGE_HYPERLINK", WebUtil::htmlOutput($imageHyperlink));
$t->set_var("CONTENT_DESCRIPTION", WebUtil::htmlOutput($contentDescription));
$t->set_var("MESSAGE", $errorMessage);
$t->set_var("IMAGE_ALIGN_DROPDOWN", $imageAlignmentDropDown);
$t->set_var("PRODUCT_RELATION", $productsDropDown);
$t->set_var("CONTENT_LINKS", WebUtil::htmlOutput($contentLinks));
$t->set_var("HTML_HEADER", WebUtil::htmlOutput($htmlHeader));

$t->allowVariableToContainBrackets("IMAGE_ALIGN_DROPDOWN");
$t->allowVariableToContainBrackets("PRODUCT_RELATION");
$t->allowVariableToContainBrackets("MESSAGE");

if(empty($editCategoryID)){
	$t->discard_block("origPage","ContentListBL");

}
else{
	$contentItemIDs = ContentCategory::GetContentItemsWithinCategory($dbCmd, $editCategoryID);

	if(empty($contentItemIDs)){
		$t->discard_block("origPage","ContentListBL");
	
	}
	else{
	
		$t->set_block("origPage","ContentItemBL","ContentItemBLout");


		$contentItemObj = new ContentItem($dbCmd);

		// Loop through all of the Content ID's and create links to each Content Item
		foreach($contentItemIDs as $thisContentID){
		
			$contentItemObj->loadContentByID($thisContentID);

			$contentItemKiloBytes = round($contentItemObj->getDescriptionBytes() / 1024, 1);

			$contentTemplatesKiloBytes = round($contentItemObj->getBytesOfContentTemplatesUnder() / 1024);
			
			$totalTemplatesUnder = $contentItemObj->countOfContentTemplatesUnder();
			
			if($totalTemplatesUnder > 0)
				$avgTemplatesKiloBytes = round($contentTemplatesKiloBytes / $totalTemplatesUnder, 1);
			else
				$avgTemplatesKiloBytes = 0;
				
				
			$activeIndicator = "";
			if(!$contentItemObj->checkIfActive())
				$activeIndicator = "<font class='SmallBody' style='color=\"#cc0000\"'><i>inactive - </i></font> ";
			$t->set_var("ACTIVE_CONTENT_ITEM", $activeIndicator);
			$t->allowVariableToContainBrackets("ACTIVE_CONTENT_ITEM");
				
			$t->set_var("TOTAL_TEMPLATES_KB", $contentTemplatesKiloBytes);
			$t->set_var("AVG_TEMPLATES_KB", $avgTemplatesKiloBytes);
			
			$t->set_var("CONTENT_ITEM_TITLE", WebUtil::htmlOutput($contentItemObj->getTitle()));
			$t->set_var("CONTENT_ITEM_ID", $thisContentID);
			$t->set_var("CONTENT_TEMPLATES_COUNT", $totalTemplatesUnder);
			$t->set_var("CONTENT_ITEM_KB", $contentItemKiloBytes);
			
			
			$linksArr = $contentItemObj->getLinksArr(false, true);
			$linksStr = "";
			
			foreach($linksArr as $linkSubject => $linkURL){
				
				// Make sure they don't do an XSS injection
				$linkURL = preg_replace("/(\<|\>)/", "", $linkURL);
				
				if(!empty($linksStr))
					$linksStr .= " - ";
				$linksStr .= "<nobr><a href='$linkURL' target='top'>" . WebUtil::htmlOutput($linkSubject) . "</a></nobr>";
			}
			
			if(!empty($linksStr))
				$linksStr = "<b>LINKS:</b> " . $linksStr . "<br>";
				
			$t->set_var("ITEM_LINKS", $linksStr);
			$t->allowVariableToContainBrackets("ITEM_LINKS");
			
			
			
			$t->parse("ContentItemBLout","ContentItemBL",true);

		}
	}
}




$t->pparse("OUT","origPage");





?>
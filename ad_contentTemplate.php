<?
require_once("library/Boot_Session.php");






$dbCmd = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$UserID = $AuthObj->GetUserID();



if(!$AuthObj->CheckForPermission("EDIT_CONTENT"))
	throw new Exception("Permission Denied");


$contentTemplateObj = new ContentTemplate($dbCmd);


// Get input from the URL (and set any Default values for "new entries")
$contentItemID = WebUtil::GetInput("contentItemID", FILTER_SANITIZE_INT);
$contentTitle = WebUtil::GetInput("contentTitle", FILTER_SANITIZE_STRING_ONE_LINE);
$editContentTemplateID = WebUtil::GetInput("editContentTemplateID", FILTER_SANITIZE_INT);
$descriptionFormat = WebUtil::GetInput("descriptionFormat", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "text");
$contentDescription = WebUtil::GetInput("contentDescription", FILTER_UNSAFE_RAW);
$artworkTemplateID = WebUtil::GetInput("artworkTemplateID", FILTER_SANITIZE_INT);
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
	
		if(!empty($contentTitle)){

			$existingTitleID = $contentTemplateObj->checkIfContentTitleExists($contentTitle);

			// Detect what will cause an error within our Object and report to to the user.
			if((!empty($existingTitleID) && $viewType == "new") || (!empty($existingTitleID) && $existingTitleID != $editContentTemplateID  && $viewType == "edit"))
				$errorMessage .= "* The Content Title is already in use.  Content Titles must be unique.<br>";
		}

		if(empty($contentDescription))
			$errorMessage .= "* The content description can not be left blank.<br>";

		
		$linksErrorMsg = $contentTemplateObj->setLinks($contentLinks);
		if(!empty($linksErrorMsg))
			$errorMessage .= "* " . $linksErrorMsg . "<br>";

		$allDirectivesArr = $contentTemplateObj->getAllDirectivesFromAllLinks();
		if(in_array("NOITEMS", $allDirectivesArr))
			$errorMessage .= "* You can not add the directive [NOITEMS] on a Content Item because it is above Templates in the hierarchy.<br>";
		if(in_array("NOTEMPLATES", $allDirectivesArr))
			$errorMessage .= "* You can not add the directive [NOTEMPLATES] on a Content Template itself.<br>";


		if(empty($errorMessage)){

			// If we are editing.. then load the existing details into memory.
			if($viewType == "edit")
				$contentTemplateObj->loadContentByID($editContentTemplateID);

				
			$contentTemplateObj->setTitle($contentTitle);
			$contentTemplateObj->setLinks($contentLinks);
			$contentTemplateObj->setContentItemID($contentItemID);
			$contentTemplateObj->setDescription($contentDescription);
			$contentTemplateObj->setDescriptionFormat($descriptionFormat);
			$contentTemplateObj->setTemplateID($artworkTemplateID);
			$contentTemplateObj->setHeaderHTML($htmlHeader);
			$contentTemplateObj->setActive($activeContent == "N" ? false : true);


			if($viewType == "new"){

				$editContentTemplateID = $contentTemplateObj->insertNewContentTemplate($UserID);
			}
			else if($viewType == "edit"){

				$contentTemplateObj->updateContentTemplate($UserID);
			}
			else{
				throw new Exception("Illegal ViewType for content Category");
			}

			// After Saving Content (whether new or edit)... we are alway going to redirect back to the GET Url for editing.
			// That will give them the option to refresh the page (without the Do you want to re-post data).
			// We only do this if there is no error though.
			header("Location: ./ad_contentTemplate.php?viewType=edit&editContentTemplateID=" . $editContentTemplateID . "&saved=yes&nocaching=" . time());
			exit;

		}
	}
	else if($action == "changeTemplateImage"){

		$contentTemplateObj = new ContentTemplate($dbCmd);
		$contentTemplateObj->loadContentByID($editContentTemplateID);
		
		$contentTemplateObj->setTemplateID($artworkTemplateID);
		
		$contentTemplateObj->updateContentTemplate($UserID);
		
		
			
		header("Location: ./ad_contentTemplate.php?viewType=edit&editContentTemplateID=" . $editContentTemplateID . "&saved=yes&nocaching=" . time());
		exit;

	}
	else if($action == "removeContentTemplate"){

		$contentTemplateObj = new ContentTemplate($dbCmd);
		$contentTemplateObj->loadContentByID($editContentTemplateID);
		
		$contentTitle = $contentTemplateObj->getTitle();
		$contentItemID = $contentTemplateObj->getContentItemID();

		ContentTemplate::RemoveContent($dbCmd, $editContentTemplateID);
		
		header("Location: ./ad_contentItem.php?viewType=edit&editContentItemID=" .  $contentItemID  . "&nocache=" . time());
		exit;

	}
	else{
		throw new Exception("Illegal Action called while editing a Content Template.");
	}
}





// Keep people from switching domains after they are already editing.
if(!empty($editContentTemplateID)){
	Domain::enforceTopDomainID(ContentTemplate::getDomainIDofContentTemplate($dbCmd, $editContentTemplateID));
}






$t = new Templatex(".");

$t->set_file("origPage", "ad_contentTemplate-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


if($viewType == "new"){

	// When we are adding a new Content Item... we may or may not be provided with the category ID.
	if(!empty($contentItemID))
		$contentTemplateObj->setContentItemID($contentItemID);


	// Show error messages if we are trying to add a new category and it does not exist.	
	if(empty($contentItemID))
		throw new Exception("The content Item ID is missing.");
	if(empty($artworkTemplateID))
		throw new Exception("The content Template ID is missing.");
		
		
	// For new templates default the content description to the keywords stored on the template.
	$contentDescription = "";
	
	$dbCmd->Query("SELECT TempKw FROM templatekeywords WHERE TemplateID = $artworkTemplateID");
	while($thisKeword = $dbCmd->GetValue())
		$contentDescription .= $thisKeword . " ";
		
		
	$contentTemplateObj->setTemplateID($artworkTemplateID);
	

	$t->set_var("NEW_OR_EDIT", "new");
	$t->set_var("ITEM_EDIT_ID", "");
	
	$t->discard_block("origPage","LiveContentLinkBL");

}
else if($viewType == "edit"){

	// Keep a record of the visit to this page by the user.
	NavigationHistory::recordPageVisit($dbCmd, $UserID, "CntTemp", $editContentTemplateID);


	$t->set_var("NEW_OR_EDIT", "edit");
	$t->set_var("ITEM_EDIT_ID", $editContentTemplateID);
	

	$contentTemplateObj->loadContentByID($editContentTemplateID);
	
	$contentTitle = $contentTemplateObj->getTitle();	
	$contentItemID = $contentTemplateObj->getContentItemID();
	$artworkTemplateID = $contentTemplateObj->getTemplateID();
	
	
	$t->set_var("LIVE_CONTENT_LINK", $contentTemplateObj->getURLforContent(true));
	
	
	
	// Only Erase our HTTP inputs if there hasn't been an Error message.
	// Otherwise we would erase anything that was typed in by the user.
	// We should give them the chance to correct the error.
	
	if(empty($errorMessage)){
		$descriptionFormat = $contentTemplateObj->descriptionIsHTMLformat() ? "html" : "text";
		$contentDescription = $contentTemplateObj->getDescription();
		$contentLinks = $contentTemplateObj->getLinksForEditing();
		$htmlHeader = $contentTemplateObj->getHeaderHTML();
		$activeContent = $contentTemplateObj->checkIfActive() ? "Y" : "N";
	}
	
	
}
else{

	throw new Exception("Illegal View Type");
}


$contentTemplateObj->preferTemplateSize("big");

// Show the Preview Image for the given Tempalte ID
if($viewType == "edit" && $contentTemplateObj->checkIfImageStored()){

	$websiteUrlForDomain = Domain::getWebsiteURLforDomainID(Domain::oneDomain());
	
	$imageHtml = "<img border='1' style='border-color:#000000' src='".WebUtil::htmlOutput($contentTemplateObj->getURLforImage(true)) . "'><br><br><a href='javascript:ChangeTemplateImage();' class='BlueRedLink'>Change Template Image</a>";

	$t->set_var("TEMPLATE_PREVIEW_IMAGE", $imageHtml);
	$t->allowVariableToContainBrackets("TEMPLATE_PREVIEW_IMAGE");
}
else{

	if($viewType == "new")
		$t->set_var("TEMPLATE_PREVIEW_IMAGE", "");
	else
		$t->set_var("TEMPLATE_PREVIEW_IMAGE", "Error, the Tempalte Image does not exist anywmore.");
}



$t->set_var("CONTENT_TITLE", WebUtil::htmlOutput($contentTitle));
$t->set_var("CONTENT_ITEM_ID", $contentItemID);
$t->set_var("ARTWORK_TEMPLATE_ID", $artworkTemplateID);
$t->set_var("CONTENT_LINKS", WebUtil::htmlOutput($contentLinks));
$t->set_var("HTML_HEADER", WebUtil::htmlOutput($htmlHeader));


$contentItemObj = new ContentItem($dbCmd);
$contentItemObj->loadContentByID($contentItemID);

if($contentItemObj->checkActiveParent() && $contentItemObj->checkIfActive())
	$t->discard_block("origPage", "parentDisabledBL");

	
$t->set_var("CONTENT_ITEM_TITLE", WebUtil::htmlOutput($contentItemObj->getTitle()));



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




if(!empty($errorMessage))
	$errorMessage = "Error!<br>" . $errorMessage . "<br><br>";



if($viewType == "new"){
	$t->discard_block("origPage","TimeStampBL");
}
else{
	$t->set_var("CREATED_BY", WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, $contentTemplateObj->getCreatedByUserID())));
	$t->set_var("LAST_EDITED_BY", WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, $contentTemplateObj->getLastEditedByUserID())));
	$t->set_var("CREATED_ON", date("F j, Y, g:i a", $contentTemplateObj->getCreatedOnDate()));
	$t->set_var("LAST_EDITED_ON", date("F j, Y, g:i a", $contentTemplateObj->getLasteEditedOnDate()));
}



$t->set_var("CONTENT_DESCRIPTION", WebUtil::htmlOutput($contentDescription));
$t->set_var("MESSAGE", $errorMessage);
$t->set_var("ITEM_EDIT_ID", $editContentTemplateID);



$t->pparse("OUT","origPage");





?>
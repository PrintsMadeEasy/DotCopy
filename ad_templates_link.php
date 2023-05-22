<?

require_once("library/Boot_Session.php");


$templateID = WebUtil::GetInput("templateID", FILTER_SANITIZE_INT);
$templateArea = WebUtil::GetInput("templateArea", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

$dbCmd = new DbCmd();

$t = new Templatex(".");

$t->set_file("origPage", "ad_templates_link-template.html");


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

if(!$AuthObj->CheckForPermission("MANAGE_TEMPLATES"))
	WebUtil::PrintAdminError("Not Available");


$templateLinksObj = new TemplateLinks();
	
$templateView = $templateLinksObj->getViewTypeFromTemplateArea($templateArea);


// Make sure the user has permission to edit tempaltes on this domain.
$templateProductID = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $templateID, $templateView);
$domainIDofTemplate = Product::getDomainIDfromProductID($dbCmd, $templateProductID);

if(!$AuthObj->CheckIfUserCanViewDomainID($domainIDofTemplate))
	throw new Exception("User can not edit these templates.");

	
$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if(!empty($action)){
	
	WebUtil::checkFormSecurityCode();
	
	if($action == "removeLink"){
		
		$templateArea_Link = WebUtil::GetInput("templateArea_Link", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
		$templateID_link = WebUtil::GetInput("templateID_link", FILTER_SANITIZE_INT);
		
		$templateLinksObj->removeLinkBetweenTemplates($templateArea, $templateID, $templateArea_Link, $templateID_link);

		header("Location: " . WebUtil::FilterURL("./ad_templates_link.php?templateID=" . $templateID . "&templateArea=" . $templateArea . "&nocache=". time()));
		exit;
	}
	else{
		throw new Exception("Illegal action");
	}
	
}
	
// Get the Artwork for this Saved Project
$ArtworkXMLstring = ArtworkLib::GetArtXMLfile($dbCmd, $templateView, $templateID);
$ArtworkInfoObj = new ArtworkInformation($ArtworkXMLstring);




// Display all of the Text Fields on the Current Tempalte
$t->set_block("origPage","TextFieldsOnTemplateBL","TextFieldsOnTemplateBLout");

$textFieldCounter = 0;

for($i=0; $i<sizeof($ArtworkInfoObj->SideItemsArray); $i++){

	foreach ($ArtworkInfoObj->SideItemsArray[$i]->layers as $LayerObj) {

		if($LayerObj->LayerType == "text"){

			// Text layers which are shadows to other layers should not be listed as a Quick Edit Field
			if($ArtworkInfoObj->CheckIfTextLayerIsShadowToAnotherLayer($i, $LayerObj->level))
				continue;
				
			if(empty($LayerObj->LayerDetailsObj->field_name))
				continue;

			$textFieldCounter++;
			
			$t->set_var("TEXT_FIELD_NAME", WebUtil::htmlOutput($LayerObj->LayerDetailsObj->field_name));
			$t->set_var("TEXT_FIELD_COUNTER", $textFieldCounter);


			
			$t->parse("TextFieldsOnTemplateBLout","TextFieldsOnTemplateBL",true);
		}
	}
}

if(!$textFieldCounter)
	$t->set_var("TextFieldsOnTemplateBLout", WebUtil::htmlOutput("No quick-edit fields were found on this template."));


	
// Display all of the Existing Template Links	
$templatesLinksHash = $templateLinksObj->getTemplatesLinkedToThis($templateArea, $templateID);

$t->set_block("origPage","TemplateLinkBL","TemplateLinkBLout");

$totalTemplateLinks = 0;
$lastProductIdInLoop = 0;


foreach($templatesLinksHash as $thisTemplateID => $thisTemplateArea){

	// Get a list Quick Edit fields on this template.
	$thisTempalteArtObj = new ArtworkInformation(ArtworkLib::GetArtXMLfile($dbCmd, $templateLinksObj->getViewTypeFromTemplateArea($thisTemplateArea), $thisTemplateID));
	
	$quickEditFields = array();
	for($i=0; $i<sizeof($thisTempalteArtObj->SideItemsArray); $i++){
		foreach ($thisTempalteArtObj->SideItemsArray[$i]->layers as $LayerObj) {
			
			if($LayerObj->LayerType != "text" || empty($LayerObj->LayerDetailsObj->field_name))
				continue;
	
			$quickEditFields[] = $LayerObj->LayerDetailsObj->field_name;
		}
	}

	$quickEditFields = array_unique($quickEditFields);
	
	$fieldsInCommon = $templateLinksObj->getTextFieldsInCommon($thisTemplateArea, $thisTemplateID, $templateArea, $templateID);
	
	$leftOverTextFields = array_diff($quickEditFields, $fieldsInCommon);
	
	$productIDofThisTemplate = $templateLinksObj->getProductIdOfTemplate($thisTemplateArea, $thisTemplateID);
	
	if($lastProductIdInLoop != $productIDofThisTemplate){
		$productObj = new Product($dbCmd, $productIDofThisTemplate, false);
		$t->set_var("PRODUCT_NAME", WebUtil::htmlOutput($productObj->getProductTitleWithExtention()));
		$lastProductIdInLoop = $productIDofThisTemplate;
	}
	else{
		$t->set_var("PRODUCT_NAME", "");
	}

	
	// Get the Thumbnail Image
	$thumbPeviewFileName = "./image_preview/" . ThumbImages::GetTemplatePreviewFnameAdmin($dbCmd, $thisTemplateID, $templateLinksObj->getViewTypeFromTemplateArea($thisTemplateArea));
	$t->set_var("THUMBNAIL_PHOTO", WebUtil::htmlOutput($thumbPeviewFileName));
	
	
	
	// List all of the fields in commmon (separated by a line break);
	$fieldsInCommonHTML = "";
	foreach($fieldsInCommon as $thisCommonField){
		if(!empty($fieldsInCommonHTML))
			$fieldsInCommonHTML .= "<br>";
			
		$fieldsInCommonHTML .= WebUtil::htmlOutput($thisCommonField);
	}
	

	
	// List all of the fields that exist on the tempalte in our current loop... but are not in common with the tempalte we are currently viewing.
	$extraFieldsHTML = "";
	foreach($leftOverTextFields as $thisExtraField){
		if(!empty($extraFieldsHTML))
			$extraFieldsHTML .= "<br>";
			
		$extraFieldsHTML .= WebUtil::htmlOutput($thisExtraField);
	}
	
	
	$t->set_var("TEMP_AREA", $thisTemplateArea);
	$t->set_var("TEMP_ID", $thisTemplateID);
	
	
	$t->set_var("LINKED_FIELDS", $fieldsInCommonHTML);
	$t->set_var("UNLINKED_FIELDS", $extraFieldsHTML);
	$t->allowVariableToContainBrackets("LINKED_FIELDS");
	$t->allowVariableToContainBrackets("UNLINKED_FIELDS");
	
	$totalTemplateLinks++;
	
	$t->parse("TemplateLinkBLout","TemplateLinkBL",true);
}



if($totalTemplateLinks == 0)
	$t->set_var("TemplateLinkBLout", WebUtil::htmlOutput("There are links associated with this template."));


// Build a Selection Menu of Products that create their own templats so that we have a choice to link-up with them.
$productIDofTemplatesArr = array("0" => "Choose a Product to Link With");
$allActiveProductIDs = Product::getActiveProductIDsArr($dbCmd, Domain::oneDomain());

foreach($allActiveProductIDs as $thisActiveProductID){

	$productObj = new Product($dbCmd, $thisActiveProductID, false);
	if($productObj->getProductIDforTemplates() != $thisActiveProductID)
		continue;
		
	$productIDofTemplatesArr[$thisActiveProductID] = $productObj->getProductTitleWithExtention();
	
}


$t->set_var("PRODUCT_LINK_SELECT", Widgets::buildSelect($productIDofTemplatesArr, array()));
$t->allowVariableToContainBrackets("PRODUCT_LINK_SELECT");

$t->set_var("TEMPLATE_AREA", $templateArea);
$t->set_var("TEMPLATE_ID", $templateID);


$t->set_var("PRODUCT_LINK_COUNT", $templateLinksObj->getNumberOfProductsLinkedToThis($templateArea, $templateID));

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


$t->pparse("OUT","origPage");


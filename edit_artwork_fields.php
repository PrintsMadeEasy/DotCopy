<?

require_once("library/Boot_Session.php");


$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$projectrecord = WebUtil::GetInput("projectrecord", FILTER_SANITIZE_INT);
$continueurl = WebUtil::GetInput("continueUrl", FILTER_SANITIZE_URL);
$cancelurl = WebUtil::GetInput("cancelUrl", FILTER_SANITIZE_URL);
$templateIdForArtworkUpdate = WebUtil::GetInput("templateIdForArtworkUpdate", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

if(empty($continueurl))
	$continueurl = "shoppingcart.php";
if(empty($cancelurl))
	$cancelurl = "shoppingcart.php";


$dbCmd = new DbCmd();



$user_sessionID =  WebUtil::GetSessionID();


$CurrentURL = "edit_artwork_fields.php?editorview=$view&projectrecord=$projectrecord&continueUrl=" . urlencode($continueurl) . "&cancelUrl=" . urlencode($cancelurl) . "&templateIdForArtworkUpdate=" . urlencode($templateIdForArtworkUpdate);
$CurrentURL = WebUtil::FilterURL($CurrentURL);

// If we can't see a session variable here then maybe the user doesn't have cookies enabled. 
if (!isset($HTTP_SESSION_VARS['initialized']))
	$HTTP_SESSION_VARS['initialized'] = 0;

// We initialized this variable on the previous page.
if ($HTTP_SESSION_VARS['initialized'] <> 1) {
	WebUtil::PrintError("It appears that your browser has been sitting idle for too long. Try going 'Back' and re-trying the operation.  If this error occurred while you were actively using the website for the first time, you may have session cookies enabled in your browser.  Make sure that your privacy settings are set to 'Medium'.");
}

$t = new Templatex();


ProjectBase::EnsurePrivilagesForProject($dbCmd, $view, $projectrecord);

$projectObj = ProjectBase::getprojectObjectByViewType($dbCmd, $view, $projectrecord);

$productObj = Product::getProductObj($dbCmd, $projectObj->getProductID());

$xmlDocument = ArtworkLib::GetArtXMLfile($dbCmd, $view, $projectrecord);
$ArtworkInfoObj = new ArtworkInformation($xmlDocument);


// If the Product does not have editable artwork, then the user should be redirected out of here.
if(!$productObj->getArtworkIsEditable()){
	session_write_close();
	header("Location: " . WebUtil::FilterURL($cancelurl), true, 302);
	exit;
}


$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

if(!empty($action)){

	WebUtil::checkFormSecurityCode();
	
	if($action == "save"){
		for ($sidenumber = 0; $sidenumber < sizeof($ArtworkInfoObj->SideItemsArray); $sidenumber++){
			
			// Loop through each Layer on the side that we are viewing.  It is looping through the global variable created when the XML doc was parsed.
			for ($layerNumber = 0; $layerNumber < sizeof($ArtworkInfoObj->SideItemsArray[$sidenumber]->layers); $layerNumber++) {
				
				if($fieldName = $ArtworkInfoObj->SideItemsArray[$sidenumber]->layers[$layerNumber]->LayerType != "text")
					continue;
				
				$fieldName = $ArtworkInfoObj->SideItemsArray[$sidenumber]->layers[$layerNumber]->LayerDetailsObj->field_name;
				$fieldOrder = $ArtworkInfoObj->SideItemsArray[$sidenumber]->layers[$layerNumber]->LayerDetailsObj->field_order;
			
				// If we have a field name and a field order value... then look for name/value pairs within the form post.
				if(!empty($fieldName) && !empty($fieldOrder)){
					
					$fieldData = WebUtil::GetInput("input_s_" . $sidenumber . "_f_" . $fieldOrder, FILTER_UNSAFE_RAW);
					if($fieldData === null){
						continue;
					}
					
					$fieldData = preg_replace("/\n/", "!br!", $fieldData);
					$fieldData = preg_replace("/\r/", "", $fieldData);
					
					$ArtworkInfoObj->SideItemsArray[$sidenumber]->layers[$layerNumber]->LayerDetailsObj->message = $fieldData;
				}
			}
		}
		
		$newXMLfile = $ArtworkInfoObj->GetXMLdoc();
		
		// Update the session variable.  The redirection URL will save it within the database.
		WebUtil::SetSessionVar("draw_xml_document", $newXMLfile);
		
		if($view == "session" || $view == "projectssession" ){
			header("Location: " . WebUtil::FilterURL("shoppingcart_saveartwork.php?projectrecord=$projectrecord&TemplateNumber=$templateIdForArtworkUpdate&returnurl=" . urlencode($continueurl) . "&nocache=" . time()));
		}
		else if($view == "saved" || $view == "projectssaved" ){
			header("Location: " . WebUtil::FilterURL("SavedProjects_saveartwork.php?projectrecord=$projectrecord&TemplateNumber=$templateIdForArtworkUpdate&returnurl=" . urlencode($continueurl) . "&nocache=" . time()));
		}
		else if($view == "ordered" || $view == "customerservice" || $view == "projectsordered"){
			header("Location: " . WebUtil::FilterURL("customer_service_saveartwork.php?projectrecord=$projectrecord&TemplateNumber=$templateIdForArtworkUpdate&returnurl=" . urlencode($continueurl) . "&nocache=" . time()));
		}

		exit;
	}
}



$t->set_file("origPage", "edit_artwork_fields-template.html");


// If the user is looking at a Saved Project, then check if there is a Saved Project Override.
// Make sure the path to the templates is coming from the Domain of the User. 
// We may be looking at the saved projects from another domain in the URL.
if($projectObj->getViewTypeOfProject() == "saved"){
	
	//The user ID that we want to use for the Saved Project might belong to somebody else;
	$AuthObj = new Authenticate(Authenticate::login_general);
	$UserIDofOverride = ProjectSaved::GetSavedProjectOverrideUserID($AuthObj);
	
	if($UserIDofOverride != $AuthObj->GetUserID()){
		$domainIDofUser = UserControl::getDomainIDofUser($UserIDofOverride);
		Domain::enforceTopDomainID($domainIDofUser);
		$t->setSandboxPathByDomainID($domainIDofUser);
	}
}


$t->set_var("SESSION_ID", $user_sessionID);

$t->set_block("origPage","TextFieldBL","TextFieldBLout");
$t->set_block("origPage","ArtworkSidesBL","ArtworkSidesBLout");


$fieldCounter = 0;
	
	
for ($sidenumber = 0; $sidenumber < sizeof($ArtworkInfoObj->SideItemsArray); $sidenumber++){
	
	$LayersSorted = array();
	$sortedLayerCounter = 0;
	
	// Loop through each Layer on the side that we are viewing.  It is looping through the global variable created when the XML doc was parsed.
	foreach ($ArtworkInfoObj->SideItemsArray[$sidenumber]->layers as $LayerObj) {
	
		if($LayerObj->LayerType == "text"){
	
			// Place all of the layer objects into a 2D array.
			// We are going to sort this array based on the "Layer Level"
			$LayersSorted[$sortedLayerCounter][0] = $LayerObj->LayerDetailsObj->field_order;
			$LayersSorted[$sortedLayerCounter][1] = $LayerObj->LayerDetailsObj;
	
			$sortedLayerCounter++;
		}
	}
	
	// This function will sort 2-D array based on the "Field Order"
	WebUtil::array_qsort2($LayersSorted, 0);
	
	// Clean the Slate for the nested block (or inner block) of HTML
	$t->set_var("TextFieldBLout", "");
	
	
	// Create the HTML input boxes for them to enter their information (out of Expert Mode) 
	for($j=0; $j<sizeof($LayersSorted); $j++){
	
		// Only display an field box in HTML if there is a field name 
		if(!empty($LayersSorted[$j][1]->field_name)){
	
			$fieldCounter++;
	
			// !br! is a special code we were using within the flash program for line breaks. 
			$LayersSorted[$j][1]->message = preg_replace("/!br!/", "\r\n", $LayersSorted[$j][1]->message);
	
			$t->set_var("FIELD_NAME_HTML", WebUtil::htmlOutput($LayersSorted[$j][1]->field_name));
			$t->set_var("FIELD_DATA_HTML", WebUtil::htmlOutput($LayersSorted[$j][1]->message));
			$t->set_var("FIELD_ORDER", WebUtil::htmlOutput($LayersSorted[$j][1]->field_order));
			
			$t->set_var("SIDE_NUMBER", $sidenumber);
			
			$t->parse("TextFieldBLout","TextFieldBL",true);
		}
	}
	
	$t->set_var("SIDE_NUMBER", $sidenumber);
	$t->set_var("SIDE_NAME", WebUtil::htmlOutput($ArtworkInfoObj->SideItemsArray[$sidenumber]->description));
	
	$t->parse("ArtworkSidesBLout","ArtworkSidesBL",true);
}

if($fieldCounter == 0){
	$t->discard_block("origPage", "EditFieldsBL");
}
else{
	$t->discard_block("origPage", "NoFieldsBL");
}






// SEt these Variables... Which may be used by Javascript for Creating a Backside.
$t->set_var("ARTWORK_WIDTH", WebUtil::htmlOutput($ArtworkInfoObj->SideItemsArray[0]->contentwidth));
$t->set_var("ARTWORK_HEIGHT", WebUtil::htmlOutput($ArtworkInfoObj->SideItemsArray[0]->contentheight));
$t->set_var("ARTWORK_DPI", WebUtil::htmlOutput($ArtworkInfoObj->SideItemsArray[0]->dpi));
$t->set_var("ARTWORK_ZOOM", WebUtil::htmlOutput($ArtworkInfoObj->SideItemsArray[0]->initialzoom));
$t->set_var("ARTWORK_ROTATE_CANVAS", WebUtil::htmlOutput($ArtworkInfoObj->SideItemsArray[0]->rotatecanvas));
$t->set_var("ARTWORK_SCALE", WebUtil::htmlOutput($ArtworkInfoObj->SideItemsArray[0]->scale));
$t->set_var("ARTWORK_FOLD_HORIZ", WebUtil::htmlOutput($ArtworkInfoObj->SideItemsArray[0]->folds_horizontal));
$t->set_var("ARTWORK_FOLD_VERT", WebUtil::htmlOutput($ArtworkInfoObj->SideItemsArray[0]->folds_vertical));
$t->set_var("ARTWORK_BACK_IMAGE", WebUtil::htmlOutput($ArtworkInfoObj->SideItemsArray[0]->backgroundimage));
$t->set_var("ARTWORK_BACK_X_COORD", WebUtil::htmlOutput($ArtworkInfoObj->SideItemsArray[0]->background_x));
$t->set_var("ARTWORK_BACK_Y_COORD", WebUtil::htmlOutput($ArtworkInfoObj->SideItemsArray[0]->background_y));
$t->set_var("ARTWORK_BACK_WIDTH", WebUtil::htmlOutput($ArtworkInfoObj->SideItemsArray[0]->background_width));
$t->set_var("ARTWORK_BACK_HEIGHT", WebUtil::htmlOutput($ArtworkInfoObj->SideItemsArray[0]->background_height));
$t->set_var("ARTWORK_BACKGROUND_COLOR", WebUtil::htmlOutput($ArtworkInfoObj->SideItemsArray[0]->background_color));

$t->set_var("PROJECT_MAILING_FLAG", ($productObj->hasMailingService() ? "true" : "false"));

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());
$t->set_var("TEMPLATE_ID_ARTWORK_UPDATE", $templateIdForArtworkUpdate);

$t->set_var("SIDE_COUNT", sizeof($ArtworkInfoObj->SideItemsArray));


// Erase blocks of Javascript.  Depending on wheter this is an order which has already been placed... or it it is in the session
if($view == "customerservice"){

	$t->discard_block("origPage", "SavedProjectEditArtworkWarningBL");
	$t->discard_block("origPage", "SavedProjectIsInShoppingCartBL");
	$t->discard_block("origPage", "ShoppingCartDesignIsAlsoSaved");
	
}
else if($view == "saved"){

	// If this project is Saved and it is Linked to an Order that has Status of being "in process"...
	// then we want to show them a warning telling them to visit their order history if they need to make changes
	// Some people think that they can edit their Saved Project after the order has been placed and it will fix the artwork on order.
	// Find out if the arwork link exists and if the status is not finished or canceled.
	$dbCmd->Query("SELECT COUNT(*) FROM projectsordered WHERE SavedID=$projectrecord AND Status != 'F' AND Status != 'C'");
	if($dbCmd->GetValue() == 0)
		$t->discard_block("origPage", "SavedProjectEditArtworkWarningBL");
	
	$t->discard_block("origPage", "ShoppingCartDesignIsAlsoSaved");
	
	if(sizeof(ShoppingCart::GetProjectSessionIDsInShoppingCartThatAreSaved($dbCmd, $projectrecord, $user_sessionID)) == 0)
		$t->discard_block("origPage", "SavedProjectIsInShoppingCartBL");
}
else{

	$t->discard_block("origPage", "SavedProjectEditArtworkWarningBL");
	$t->discard_block("origPage", "SavedProjectIsInShoppingCartBL");
	
	// This is a design in the shopping cart.  Find out if it is also saved
	if(!ProjectSaved::CheckIfProjectIsSaved($dbCmd, $projectrecord))
		$t->discard_block("origPage", "ShoppingCartDesignIsAlsoSaved");
}





$t->set_var(array(
	"PROJECT_RECORD"=>$projectrecord, 
	"PRODUCTNAME_WITH_SLASHES"=>addslashes($projectrecord), 
	"PRODUCTNAME"=>WebUtil::htmlOutput($projectObj->getProductTitle())));

// So that if someone clicks on "insert image", we can tell them how the image will be scaled
$ArtworkDPI = $ArtworkInfoObj->SideItemsArray[0]->dpi;
$PixelsWide = $ArtworkInfoObj->SideItemsArray[0]->contentwidth / 96 * $ArtworkDPI;
$PixelsHigh = $ArtworkInfoObj->SideItemsArray[0]->contentheight / 96 * $ArtworkDPI;
$t->set_var(array("DPI"=>$ArtworkDPI,
		"PIXELS_WIDE"=>$PixelsWide, 
		"PIXELS_HIGH"=>$PixelsHigh));


// Figure out how big the bleed and safe zones are.
$bleedUnits = ImageLib::ConvertPicasToFlashUnits($productObj->getArtworkBleedPicas());
$t->set_var("GUIDE_MARGIN", $bleedUnits);
$t->set_var("BLEED_PIXELS", ($bleedUnits / 96 * $ArtworkDPI));


// So that someone can return to step 2 for choosing another template without losing their place.
$t->set_var("VIEW", $view);
$t->set_var("CURRENTURL", $CurrentURL);
$t->set_var("CURRENTURL_ENCODED", urlencode($CurrentURL));
$t->set_var("CONTINUEURL", WebUtil::htmlOutput($continueurl));
$t->set_var("CONTINUEURL_ENCODED", urlencode($continueurl));
$t->set_var("CANCELURL", WebUtil::htmlOutput($cancelurl));
$t->set_var("CANCELURL_ENCODED", urlencode($cancelurl));


$t->set_var("PRODUCT_TITLE", WebUtil::htmlOutput($productObj->getProductTitle()));
$t->set_var("PRODUCT_TITLE_EXT", WebUtil::htmlOutput($productObj->getProductTitleExtension()));


// For the WebtrackingSoftware
$t->set_var("TRACK_PRODUCTID", $projectObj->getProductID());
$t->set_var("PRODUCT_ID", $projectObj->getProductID());



$onlineCsrArr = ChatCSR::getCSRsOnline(Domain::getDomainIDfromURL(), array(ChatThread::TYPE_Artwork));
if(empty($onlineCsrArr))
	$t->set_var("CHAT_ONLINE", "false");
else 
	$t->set_var("CHAT_ONLINE", "true");



VisitorPath::addRecord("Edit Artwork Fields");

$t->pparse("OUT","origPage");




<?

require_once("library/Boot_Session.php");



// The Editing Tool is really really particular about not being able to refresh the page, etc.
// There is some kind of bug between IE and the Flash Player.  For some reason, reloading the page causes the "fscommand" not to work
// We have to be certain the the user is visiting a unique URL every time the page is loaded
//WebUtil::EnsureGetURLdoesNotGetCached();


$editorview = WebUtil::GetInput("editorview", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$admin = WebUtil::GetInput("admin", FILTER_SANITIZE_STRING_ONE_LINE);
$sidenumber = WebUtil::GetInput("sidenumber", FILTER_SANITIZE_STRING_ONE_LINE);
$projectrecord = WebUtil::GetInput("projectrecord", FILTER_SANITIZE_INT);
$newtemplate = WebUtil::GetInput("newtemplate", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$continueurl = WebUtil::GetInput("continueurl", FILTER_SANITIZE_URL);
$cancelurl = WebUtil::GetInput("cancelurl", FILTER_SANITIZE_URL);
$templateIdForArtworkUpdate = WebUtil::GetInput("templateIdForArtworkUpdate", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

if(empty($continueurl))
	$continueurl = "shoppingcart.php";
if(empty($cancelurl))
	$cancelurl = "shoppingcart.php";


WebUtil::EnsureDigit($sidenumber, false, "Side Number is incorrect for swtiching sides.");


// If people are running a version of Netscape older than 6.2 then we have to redirect them to a software update page
$ua = new UserAgent();

if ( ( preg_match("/Netscape/i", $ua->browser)) && ( $ua->version < 6.2 ) ) {
	header("Location: ./incompatible.html");
	exit;
}


if(!empty($newtemplate)){
	if($newtemplate != "yes")
		throw new Exception("Error with URL.");
}



$dbCmd = new DbCmd();



$user_sessionID =  WebUtil::GetSessionID();

//The website should still work in secure mode, but they may get little complaints from tracking software or flash
//WebUtil::BreakOutOfSecureMode();

$CurrentURL = "edit_artwork.php?editorview=$editorview&projectrecord=$projectrecord&newtemplate=$newtemplate&continueurl=" . urlencode($continueurl) . "&cancelurl=" . urlencode($cancelurl);
$CurrentURL = WebUtil::FilterURL($CurrentURL);

// If we can't see a session variable here then maybe the user doesn't have cookies enabled. 
if (!isset($HTTP_SESSION_VARS['initialized']))
	$HTTP_SESSION_VARS['initialized'] = 0;

// We initialized this variable on the previous page.
if ($HTTP_SESSION_VARS['initialized'] <> 1) {
	WebUtil::PrintError("It appears that your browser has been sitting idle for too long. Try going 'Back' and re-trying the operation.  If this error occurred while you were actively using the website for the first time, you may have session cookies enabled in your browser.  Make sure that your privacy settings are set to 'Medium'.");
}

$t = new Templatex();


ProjectBase::EnsurePrivilagesForProject($dbCmd, $editorview, $projectrecord);

$projectObj = ProjectBase::getprojectObjectByViewType($dbCmd, $editorview, $projectrecord);

$productObj = Product::getProductObj($dbCmd, $projectObj->getProductID());


// If the Product does not have editable artwork, then the user should be redirected out of here.
if(!$productObj->getArtworkIsEditable()){
	session_write_close();
	header("Location: " . WebUtil::FilterURL($cancelurl), true, 302);
	exit;
}

$t->set_file("origPage", "edit_artwork-template.html");


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


// Set up some Template blocks so that we can erase chunks of HTML that we don't want
$t->set_block("origPage","continueNewBL","continueNewBLout");
$t->set_block("origPage","continueEditBL","continueEditBLout");
$t->set_block("origPage","ChooseNewTemplateBL","ChooseNewTemplateBLout");



$productIDforTempaltes = $productObj->getProductIDforTemplates();
$dbCmd->Query("SELECT COUNT(*) FROM artworkstemplates WHERE ProductID=$productIDforTempaltes");
$TotalTemplateCategories = $dbCmd->GetValue();
$dbCmd->Query("SELECT COUNT(*) FROM artworksearchengine WHERE ProductID=$productIDforTempaltes");
$TotalTemplateSearchEngine = $dbCmd->GetValue();
$NumberOfTemplates = $TotalTemplateCategories + $TotalTemplateSearchEngine;

// If they are coming from step 2 then this parameter should be present in the URL.  This is the ID of the art template that they want to start their project with.
if(!empty($newtemplate)){


	// If there is only 1 Template then no point in showing the user the button "Choose Another Template";
	if($NumberOfTemplates < 2)
		$t->set_var("ChooseNewTemplateBLout", "");
	else
		$t->parse("ChooseNewTemplateBLout","ChooseNewTemplateBL",true);
	

	$t->set_var("continueEditBLout", "");  //Erase the HTML for "the cancel button".  Instead their will be a button for selecting a new template
	$t->parse("continueNewBLout","continueNewBL",true);


	// In case they are a mac user this will show them a continue button insead of a Save/Cancel
	$macState = "continue";
	

}
else{
	// This means we are editing an exsisting artwork file

	$t->set_var("continueNewBLout", "");  //Erase the HTML for continuing to shopping cart (if new project)
	$t->set_var("ChooseNewTemplateBLout", "");  //Erase choose another template button.
	$t->parse("continueEditBLout","continueEditBL",true);

	// In case they are a mac user this will show them a save/cancel button insead of a Save/Cancel
	$macState = "save";

}





// Set these session variables so that the flash program will know where to download the XML file from
WebUtil::SetSessionVar('editor_View', $editorview);
WebUtil::SetSessionVar('editor_ArtworkID', $projectrecord);


// This parameter comes in the url.
// If there is no side number yet then it means that this is the first time they are viewing.  Therefore the XML doc comes from the database
// Otherwise we are switching sides and the flash program should have uploaded its XML into a temporary session variable.  That variable is not set in this file
if($sidenumber == ""){
	$sidenumber = 0;
	
	WebUtil::SetSessionVar('SwitchSides', "");

	// If this is the first time the side is being displayed then we need to get the xml doc out of the database.
	// That way we will find out which text layers have a field name --#
	if($editorview == "customerservice" || $editorview == "saved" || $editorview == "projectssession" || $editorview == "session"){
		$xmlDocument = ArtworkLib::GetArtXMLfile($dbCmd, $editorview, $projectrecord);
	}
	else{
		WebUtil::PrintError("There is a problem with the viewtype. Please report this problem to Customer Service.");
	}
	
}
else{
	$HTTP_SESSION_VARS['SwitchSides'] = $sidenumber;

	//If we have just switched sides then it means the xml doc is in the session variable.. not the database.
	// draw_downloadxml.php will pull it out of this session variable.
	$xmlDocument = WebUtil::GetSessionVar('TempXMLholder');
	
	// If we are trying to switch to a side view and don't have something in our TempXMLholder... then we should  extract from the Database as a backup.
	if(empty($xmlDocument)){
		if($editorview == "customerservice" || $editorview == "saved" || $editorview == "projectssession" || $editorview == "session"){
			$xmlDocument = ArtworkLib::GetArtXMLfile($dbCmd, $editorview, $projectrecord);
		}
	}
	
	if(empty($xmlDocument)){
		WebUtil::PrintError("There was a problem downloading the artwork. The Artwork file is missing.  Please report this problem to Customer Service.");
	}

}


// Make sure that we have all of the necessary SWF files on disk... generated by MING
ArtworkLib::WriteSWFimagesToDisk($dbCmd, $xmlDocument);


// This will store the side number that the person should be viewing on the server
// The reason is that I can't get Flash to reliably detect javascript variables on all different browsers at startup
// It might be a little more messy but flash's "onLoad" method works relaibly with server varaibles.
// You can pass variables to the movie in the html like... "mymovie.swf?name=value" but then it won't cache the flash player.
$HTTP_SESSION_VARS['SideNumber'] = $sidenumber;


// We also want to store the type of machine the user is using.  This is so that we can have access it it through the Flash application.
// We are going to get the value in flash at the same time that we get the Side Number
if (strstr($_SERVER['HTTP_USER_AGENT'],'MSIE'))
	$HTTP_SESSION_VARS['UserAgent'] = "MSIE";

else
	$HTTP_SESSION_VARS['UserAgent'] = "";





// Find out if there is an Artwork Mismatch warning.  This would happen if they tried to print directly from their File... but the dimensions don't match.
// Instead of taking them to the Shopping Cart, we take them to the editing tool so they can see for themeselves how far off they were.
$artworkMismatchWarningMsg = WebUtil::GetSessionVar(("ArtworkDimensionMismatch" . $projectrecord));
if(!empty($artworkMismatchWarningMsg))
	$t->set_var("ART_MISMATCH_MSG", $artworkMismatchWarningMsg);
else
	$t->discard_block("origPage", "ArtworkMismatchWarningBL");





$ArtworkInfoObj = new ArtworkInformation($xmlDocument);

// Check and make sure that the Side Exists on the Artwork... If not redirect them back to Side 0.. This should never happen acutally..
// This error may occur rarely by funny quirks with the flash player, still unexplained... but if we let it slip by this point it causes bigger problems.
if(!isset($ArtworkInfoObj->SideItemsArray[$sidenumber])){

	$HTTP_SESSION_VARS['SideNumber'] = 0;
	$HTTP_SESSION_VARS['SwitchSides'] = "";
	
	// Make sure Side 0 exists... otherwise this script may redirect itself into an endless loop.
	if(!isset($ArtworkInfoObj->SideItemsArray[0]))
		WebUtil::PrintError("A problem occured downloading your artwork. Contact Customer Service if the problem persists.");
	
	session_write_close();
	header("Location: " . WebUtil::FilterURL("./edit_artwork.php?projectrecord=$projectrecord&newtemplate=$newtemplate&sidenumber=0&editorview=$editorview"));
	exit;
}


// In case the Artwork should have a layer of shapes drawn on top of the Artwork... like an area on an envelope where the rectangle for a window will be punched out.
ProjectBase::SetShapesObjectsInSessionVar($dbCmd, $projectObj, $ArtworkInfoObj);


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

$t->set_block("origPage","FieldNamesBL","FieldNamesBLout");
$t->set_block("origPage","NoFieldNamesBL","NoFieldNamesBLout");


// Create the HTML input boxes for them to enter their information (out of Expert Mode) 
$fieldCounter = 0;
for($j=0; $j<sizeof($LayersSorted); $j++){

	// Only display an field box in HTML if there is a field name 
	if(!empty($LayersSorted[$j][1]->field_name)){

		$fieldCounter++;

		// !br! is a special code we were using within the flash program for line breaks. 
		$LayersSorted[$j][1]->message = preg_replace("/!br!/", "\r\n", $LayersSorted[$j][1]->message);

		// Measure how many line breaks are in the message so that we know how high to make the input box.
		$out = array();
		$NumberLineBreaks = preg_match_all("/\r/", $LayersSorted[$j][1]->message, $out);
		if(!$NumberLineBreaks)
			$inputBoxHeight = 1;
		else
			$inputBoxHeight = 3;
		
		if($LayersSorted[$j][1]->message == "")
				$inputBoxHeight = 2;
		

		// We are adding an "a_" before the field name because HTML wont let us start an input box with Number.  It has to start with something from the alphabet.
		$EditFieldNames_HTML = "<textarea wrap=\"VIRTUAL\" rows=\"$inputBoxHeight\" class=\"QuickEditField\" id=\"a_". WebUtil::htmlOutput($LayersSorted[$j][1]->field_order) ."\" name=\"a_". WebUtil::htmlOutput($LayersSorted[$j][1]->field_order) ."\" onKeyUp=\"UpdateTextLayerInFlash(" . WebUtil::htmlOutput($LayersSorted[$j][1]->field_order) . ");\">" . WebUtil::htmlOutput($LayersSorted[$j][1]->message)  . "</textarea>";

		$t->set_var(array("FIELDESC"=>WebUtil::htmlOutput($LayersSorted[$j][1]->field_name), "FIELDINPUT"=>$EditFieldNames_HTML, "FIELD_ORDER"=>WebUtil::htmlOutput($LayersSorted[$j][1]->field_order), "FIELD_ID"=>("a_" . WebUtil::htmlOutput($LayersSorted[$j][1]->field_order))));
		$t->parse("FieldNamesBLout","FieldNamesBL",true);
		
		$t->allowVariableToContainBrackets("FIELDINPUT");
	}

}

if($fieldCounter == 0){
	$t->set_var(array("NoFieldNamesBLout"=>"", 
			"FieldNamesBLout"=>"<tr><td colspan='2' class='body' align='center'><br>Use the Editing Tool below to customize your design.<br><br>Double-click on text fields to edit.<br>&nbsp;</td></tr>"));

	// Delete the block, which contains the link for showing special symbols.
	$t->set_block("origPage","SpecialSymbolsBL","SpecialSymbolsBLout");
	$t->set_var("SpecialSymbolsBLout", "");
}
else{
	// Display the header for the quick fields.
	$t->parse("NoFieldNamesBLout","NoFieldNamesBL",true);
}





// It seems now that the only browsers that don't support 2-way flash communication are Opera on the Mac platform
if (preg_match("/Opera/i", $ua->browser) && preg_match("/Macintosh/i", $ua->platform)){
	$macUser = "yes";

	// Hide the quick edit fields and HTML buttons
	$t->set_block("origPage","WindowsUserBL","WindowsUserBLout");
	$t->set_var("WindowsUserBLout", "");	
}
else{
	$macState = "";
	$macUser = "";
	
	// Hide the "Save Mode" HTML.. we are on a Windows machine so we can do more stuff
	$t->set_block("origPage","MacUserBL","MacUserBLout");
	$t->set_var(array("MacUserBLout"=>""));
}


$t->set_var("MACSTATE", $macState);
$t->set_var("MACUSER", $macUser);
$t->set_var("NEWTEMPLATE", $newtemplate);







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



// There are 3 Special Names you can put on "Template Categories".  BACK-STA,  BACK-VAR, & BACK-BULK
// Respectively... products with "No Variable Data (static)", Products with "Variable Data" and Products with "Mailing Services".  Mailing services override Varaible Data (because mailing services already have variable data capabilities).
// Find out if the Product ID (used for templates) has a template category by that name... and if so show the user an appropriate message.

$dbCmd->Query("SELECT COUNT(*) FROM templatecategories WHERE ProductID=$productIDforTempaltes AND CategoryName='BACK-STA'");
$staticBacksideTemplatesFlag = $dbCmd->GetValue() > 0 ? true : false;

$dbCmd->Query("SELECT COUNT(*) FROM templatecategories WHERE ProductID=$productIDforTempaltes AND CategoryName='BACK-VAR'");
$vardataBacksideTemplatesFlag = $dbCmd->GetValue() > 0 ? true : false;

$dbCmd->Query("SELECT COUNT(*) FROM templatecategories WHERE ProductID=$productIDforTempaltes AND CategoryName='BACK-BULK'");
$bulkmailBacksideTemplatesFlag = $dbCmd->GetValue() > 0 ? true : false;


if($productObj->hasMailingService() && $bulkmailBacksideTemplatesFlag){
	$t->discard_block("origPage", "BacksideTemplatesStaticBL");
	$t->discard_block("origPage", "BacksideTemplatesVariableDataBL");
}
else if($productObj->isVariableData() && $vardataBacksideTemplatesFlag){
	$t->discard_block("origPage", "BacksideTemplatesStaticBL");
	$t->discard_block("origPage", "BacksideTemplatesBulkMailBL");
}
else if($staticBacksideTemplatesFlag && !$productObj->hasMailingService() && !$productObj->isVariableData()){
	$t->discard_block("origPage", "BacksideTemplatesBulkMailBL");
	$t->discard_block("origPage", "BacksideTemplatesVariableDataBL");
}
else{
	// This will get rid or the outer block surrounding all of the BackSide Mailing Flags
	$t->discard_block("origPage", "BacksideTemplatesBL");
}


// If they are editing their artwork after the order has been placed... then we don't want to show them a message to add a backside.
if($editorview == "customerservice")
	$t->discard_block("origPage", "BacksideTemplatesBL");





// Erase blocks of Javascript.  Depending on wheter this is an order which has already been placed... or it it is in the session
if($editorview == "customerservice"){

	$t->set_block("origPage","JS_PROJECT_ORDERED_BL","JS_PROJECT_ORDERED_BLout");
	$t->parse("JS_PROJECT_ORDERED_BLout","JS_PROJECT_ORDERED_BL",true);

	$t->set_block("origPage","JS_PROJECT_SESSION_BL","JS_PROJECT_SESSION_BLout");
	$t->set_var(array("JS_PROJECT_SESSION_BLout"=>""));

	$t->set_block("origPage","JS_PROJECT_SAVED_BL","JS_PROJECT_SAVED_BLout");
	$t->set_var(array("JS_PROJECT_SAVED_BLout"=>""));
	
	$t->discard_block("origPage", "SavedProjectEditArtworkWarningBL");
	$t->discard_block("origPage", "SavedProjectIsInShoppingCartBL");
	$t->discard_block("origPage", "ShoppingCartDesignIsAlsoSaved");
	
	
	// Now we have to find out if it is before or after the cutoff time of the shipment
	// We want to show them a countdown timer, if possible, to show how much time they have left to modify their artwork
	
	// Take away 15 seconds since it may take some time to load the page, etc.
	$CurrentTimeStamp = time() + 15;

	if($projectObj->getCutOffTime() > $CurrentTimeStamp){
		$t->discard_block("origPage", "CutOffTimeWarningBL");
		$t->set_var("CUTOFF_TIME", date("D, M jS, g:i a",  $projectObj->getCutOffTime()) . " PST");
		
		$TotalSecondsLeft = $projectObj->getCutOffTime() - $CurrentTimeStamp;
		$HoursLeft = floor($TotalSecondsLeft / 3600);
		$MinutesLeft = floor(($TotalSecondsLeft - $HoursLeft*3600) / 60);
		$SecondsLeft = floor($TotalSecondsLeft - $MinutesLeft*60 - $HoursLeft*3600);
		
		$t->set_var("HOURS_LEFT", $HoursLeft);
		$t->set_var("MINUTES_LEFT", $MinutesLeft);
		$t->set_var("SECONDS_LEFT", $SecondsLeft);
	}
	else{
		// It looks like we will miss cutoff time if the changes are saved.  Show them what the new Arrival Date would be

		$t->discard_block("origPage", "CountdownTimerBL");
		
		$shippingChoiceID = Order::getShippingChoiceIDfromOrder($dbCmd, $projectObj->getOrderID());
		
		$timeEstObj = new TimeEstimate($projectObj->getProductID());
		$timeEstObj->setShippingChoiceID($shippingChoiceID);
		
		$domainAddressObj = new DomainAddresses(Domain::oneDomain());
		$shipFromAddressObj = $domainAddressObj->getDefaultProductionFacilityAddressObj();
		$shipToAddressObj = Order::getShippingAddressObject($dbCmd, $projectObj->getOrderID());
		
		$arrivalTimeStamp = $timeEstObj->getArrivalTimeStamp(time(), $shipFromAddressObj, $shipToAddressObj);

		$t->set_var("NEW_ARRIVAL_DATE", TimeEstimate::formatTimeStamp($arrivalTimeStamp));
	}
	
}
else if($editorview == "saved"){

	$t->set_block("origPage","JS_PROJECT_SAVED_BL","JS_PROJECT_SAVED_BLout");
	$t->parse("JS_PROJECT_SAVED_BLout","JS_PROJECT_SAVED_BL",true);

	$t->set_block("origPage","JS_PROJECT_SESSION_BL","JS_PROJECT_SESSION_BLout");
	$t->set_var(array("JS_PROJECT_SESSION_BLout"=>""));

	$t->set_block("origPage","JS_PROJECT_ORDERED_BL","JS_PROJECT_ORDERED_BLout");
	$t->set_var(array("JS_PROJECT_ORDERED_BLout"=>""));
	
	// If this project is Saved and it is Linked to an Order that has Status of being "in process"...
	// then we want to show them a warning telling them to visit their order history if they need to make changes
	// Some people think that they can edit their Saved Project after the order has been placed and it will fix the artwork on order.
	// Find out if the arwork link exists and if the status is not finished or canceled.
	$dbCmd->Query("SELECT COUNT(*) FROM projectsordered WHERE SavedID=$projectrecord AND Status != 'F' AND Status != 'C'");
	if($dbCmd->GetValue() == 0)
		$t->discard_block("origPage", "SavedProjectEditArtworkWarningBL");
	
	$t->discard_block("origPage", "CountdownTimerBL");
	$t->discard_block("origPage", "CutOffTimeWarningBL");
	
	$t->discard_block("origPage", "ShoppingCartDesignIsAlsoSaved");
	
	if(sizeof(ShoppingCart::GetProjectSessionIDsInShoppingCartThatAreSaved($dbCmd, $projectrecord, $user_sessionID)) == 0)
		$t->discard_block("origPage", "SavedProjectIsInShoppingCartBL");
}
else{
	$t->set_block("origPage","JS_PROJECT_SESSION_BL","JS_PROJECT_SESSION_BLout");
	$t->parse("JS_PROJECT_SESSION_BLout","JS_PROJECT_SESSION_BL",true);
	
	$t->set_block("origPage","JS_PROJECT_ORDERED_BL","JS_PROJECT_ORDERED_BLout");
	$t->set_var(array("JS_PROJECT_ORDERED_BLout"=>""));

	$t->set_block("origPage","JS_PROJECT_SAVED_BL","JS_PROJECT_SAVED_BLout");
	$t->set_var(array("JS_PROJECT_SAVED_BLout"=>""));
	
	$t->discard_block("origPage", "CountdownTimerBL");
	$t->discard_block("origPage", "CutOffTimeWarningBL");
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
$ArtworkDPI = $ArtworkInfoObj->SideItemsArray[$sidenumber]->dpi;
$PixelsWide = $ArtworkInfoObj->SideItemsArray[$sidenumber]->contentwidth / 96 * $ArtworkDPI;
$PixelsHigh = $ArtworkInfoObj->SideItemsArray[$sidenumber]->contentheight / 96 * $ArtworkDPI;
$t->set_var(array("DPI"=>$ArtworkDPI,
		"PIXELS_WIDE"=>$PixelsWide, 
		"PIXELS_HIGH"=>$PixelsHigh));


// Figure out how big the bleed and safe zones are.
$bleedUnits = ImageLib::ConvertPicasToFlashUnits($productObj->getArtworkBleedPicas());
$t->set_var("GUIDE_MARGIN", $bleedUnits);
$t->set_var("BLEED_PIXELS", ($bleedUnits / 96 * $ArtworkDPI));


// So that someone can return to step 2 for choosing another template without losing their place.
$t->set_var("SIDE_NUMBER", $sidenumber);
$t->set_var("EDITORVIEW", $editorview);
$t->set_var("CURRENTURL", $CurrentURL);
$t->set_var("CURRENTURL_ENCODED", urlencode($CurrentURL));
$t->set_var("CONTINUEURL", $continueurl);
$t->set_var("CONTINUEURL_ENCODED", urlencode($continueurl));
$t->set_var("CANCELURL", $cancelurl);
$t->set_var("CANCELURL_ENCODED", urlencode($cancelurl));
$t->set_var("TEMPLATE_ID_ARTWORK_UPDATE", $templateIdForArtworkUpdate);

$t->set_var("PRODUCT_TITLE", WebUtil::htmlOutput($productObj->getProductTitle()));
$t->set_var("PRODUCT_TITLE_EXT", WebUtil::htmlOutput($productObj->getProductTitleExtension()));


// For the WebtrackingSoftware
$t->set_var("TRACK_PRODUCTID", $projectObj->getProductID());
$t->set_var("PRODUCT_ID", $projectObj->getProductID());


// If we manually put this parameter in the URL then it means we want admin editing writes within the SoftwareProgram 
// Keep the javascript to initialize the Admin a secret 
if($admin == "yes"){
	$adminJS = '
		var FirstTimeLoop = true;
		function StartAdmin(){
			if(FirstTimeLoop){
				FirstTimeLoop = false;
				setTimeout("StartAdmin()",2000);
				return;
			}
			var movieObj3 = window.document.drawtech;
			if(movieObj3.PercentLoaded() == 100){
				movieObj3.SetVariable("/adminswitch:Password", "go");
				movieObj3.TPlay("/adminswitch");
			}
			else{
				setTimeout("StartAdmin()",1000);
				return;
			}
		}
		StartAdmin();
	';
}
else{
	$adminJS = '';
}
$t->set_var("JS_LEVEL", $adminJS);
$t->allowVariableToContainBrackets("JS_LEVEL");



$onlineCsrArr = ChatCSR::getCSRsOnline(Domain::getDomainIDfromURL(), array(ChatThread::TYPE_Artwork));
if(empty($onlineCsrArr))
	$t->set_var("CHAT_ONLINE", "false");
else 
	$t->set_var("CHAT_ONLINE", "true");



VisitorPath::addRecord("Edit Artwork");

$t->pparse("OUT","origPage");



?>
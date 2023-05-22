<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();




$projectrecord = WebUtil::GetInput("projectrecord", FILTER_SANITIZE_INT);
$editorview = WebUtil::GetInput("editorview", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$returnurl = WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL);
$forwardDesc = WebUtil::GetInput("forwardDesc", FILTER_SANITIZE_STRING_ONE_LINE);
$curentURL = WebUtil::getFullyQualifiedDestinationURL("vardata_modify.php?projectrecord=$projectrecord&editorview=$editorview&returnurl=" . urlencode($returnurl) . "&forwardDesc=" . urlencode($forwardDesc), WebUtil::checkIfInSecureMode()) ;

$user_sessionID =  WebUtil::GetSessionID();


ProjectBase::EnsurePrivilagesForProject($dbCmd, $editorview, $projectrecord);


$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $editorview, $projectrecord);

if(!$projectObj->isVariableData())
	WebUtil::PrintError("This is not a variable data project.");


$artworkVarsObj = new ArtworkVarsMapping($projectObj->getArtworkFile(), $projectObj->getVariableDataArtworkConfig());


if($editorview == "customerservice"){
	if(!in_array($projectObj->getStatusChar(), array("N", "P", "W", "H")))
		WebUtil::PrintError("You can not edit your Data File after the product has been printed.");
}


$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if($action){

	// We don't want people to change the status on a mailing batch because there could be a lot of money associated with Postage, etc.  There is not an easy way to issolate a Project once it has been grouped with others, printed, etc.
	if(in_array($editorview, ProjectBase::arrayProjectOrderedViewTypes()) && MailingBatch::checkIfProjectBatched($dbCmd, $projectrecord))
		WebUtil::PrintError("You can not modify variables on a Project which has been already included within a Mailing Batch.");


	if($action == "setmapping"){
	
		$variableName = WebUtil::GetInput("varname", FILTER_SANITIZE_STRING_ONE_LINE);
		$position = WebUtil::GetInput("position", FILTER_SANITIZE_INT);
		
		$artworkVarsObj->setFieldPosition($variableName, $position);
		
		$projectObj->setVariableDataArtworkConfig($artworkVarsObj->getVarMappingInXML());
		$projectObj->updateDatabase();

	}
	else if($action == "removevariable"){
	
		$variableName = WebUtil::GetInput("varname", FILTER_SANITIZE_STRING_ONE_LINE);

		$artworkVarsObj->removeVariableFromMapping($variableName);
		
		$projectObj->setVariableDataArtworkConfig($artworkVarsObj->getVarMappingInXML());
		$projectObj->updateDatabase();
	}
	else
		throw new Exception("Undefined Action");


	// If there is Data File error... don't go through the extra work of parsing the Data file and error checking 
	if($projectObj->getVariableDataStatus() != "D"){
		VariableDataProcess::SetVariableDataStatusForProject($dbCmd, $projectrecord, $editorview);
		
		// Will refresh object in case the funciton call above changed anything
		$projectObj->loadByProjectID($projectrecord);
	}
		


	// In case the it is Saved... this will update the Shoppingcart, etc.
	ProjectOrdered::ProjectChangedSoBreakOrCloneAssociates($dbCmd, $editorview, $projectrecord);
	

	// In proof mode... we want the parent window to refresh every time an action is created
	if($editorview == "proof")
		$reloadParentCommand = "&reloadparent=true";
	else
		$reloadParentCommand = "";
	

	header("Location: " . WebUtil::FilterURL($returnurl . $reloadParentCommand));
	exit;

}




// If their is a login error for the Variable Data project we want to automatically check every time the page loads.  If they are logged in it will recalculate the status
if($projectObj->getVariableDataStatus() == "L")
	VariableDataProcess::SetVariableDataStatusForProject($dbCmd, $projectrecord, $editorview);


$t = new Templatex();

$t->set_file("origPage", "vardata_modify-template.html");
$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());
	


$t->set_var("CONTINUE_LINK", $returnurl);
$t->set_var("CONTINUE_LINK_ENCODED", urlencode($returnurl));
$t->set_var("PROJECTRECORD", $projectrecord);
$t->set_var("VIEWTYPE", $editorview);
$t->set_var("FORWARDDESC_ENCODED", urlencode($forwardDesc));
$t->set_var("CURRENTURL_ENCODED", urlencode($curentURL));


$customerIDFromOrder = null;
$thumbnailSecurityCheck = "";

if($editorview == "saved"){
	$t->set_var("CONTINUE_DESC", "Return to Saved Projects");
	
	//The user ID that we want to use for the Saved Project might belong to somebody else;
	$AuthObj = new Authenticate(Authenticate::login_general);	
	$UserID = ProjectSaved::GetSavedProjectOverrideUserID($AuthObj);

	// Make sure the path to the templates is coming from the Domain of the User. We may be looking at the saved projects from another domain in the URL.
	if($UserID != $AuthObj->GetUserID()){
		$domainIDofUser = UserControl::getDomainIDofUser($UserID);
		Domain::enforceTopDomainID($domainIDofUser);
		$t->setSandboxPathByDomainID($domainIDofUser);
	}
	
	
	// Keep people from doing a Reverse MD5 lookup to see what is contained in the Security hash.
	$thumbnailSecurityCheck = md5($UserID . Constants::getGeneralSecuritySalt() );
	$dateModified = $projectObj->getDateLastModified();
	
	if(sizeof(ShoppingCart::GetProjectSessionIDsInShoppingCartThatAreSaved($dbCmd, $projectrecord, $user_sessionID)) == 0)
		$t->discard_block("origPage", "SavedProjectIsInShoppingCartBL");
	$t->discard_block("origPage", "ShoppingCartDesignIsAlsoSaved");
	
}
else if($editorview == "projectssession" || $editorview == "session"){

	// We may override what the link says within the URL
	if(!empty($forwardDesc))
		$t->set_var("CONTINUE_DESC", WebUtil::htmlOutput($forwardDesc));
	else
		$t->set_var("CONTINUE_DESC", "Return to Shopping Cart");
	
	$thumbnailSecurityCheck = $user_sessionID;
	$dateModified = $projectObj->getDateLastModified();
	
	if(!ProjectSaved::CheckIfProjectIsSaved($dbCmd, $projectrecord))
		$t->discard_block("origPage", "ShoppingCartDesignIsAlsoSaved");
	$t->discard_block("origPage", "SavedProjectIsInShoppingCartBL");

}
else if($editorview == "customerservice"){
	$t->set_var("CONTINUE_DESC", "Return to Order History");
	
	$dateModified = "";
	$thumbnailSecurityCheck = "";
	

	// We don't have thumbails for projects that have been ordered.
	$t->discard_block("origPage", "HideThumbnailOnly");
	
	$t->discard_block("origPage", "ShoppingCartDesignIsAlsoSaved");
	$t->discard_block("origPage", "SavedProjectIsInShoppingCartBL");
	$t->discard_block("origPage", "AddVariableImagesLinkBL");

}
else if($editorview == "proof"){
	$t->set_var("CONTINUE_DESC", "Close This Window");
	
	$dateModified = "";
	$thumbnailSecurityCheck = "";
	

	// We don't have thumbails for projects that have been ordered.
	$t->discard_block("origPage", "HideThumbnailBLandButtons");
	
	$t->discard_block("origPage", "ShoppingCartDesignIsAlsoSaved");
	$t->discard_block("origPage", "SavedProjectIsInShoppingCartBL");
	
	
	// Since we are in Proof mode... Figure out what the Customer ID is
	$customerIDFromOrder = Order::GetUserIDFromOrder($dbCmd, $projectObj->getOrderID());
	
	$t->discard_block("origPage", "AddVariableImagesLinkBL");

}
else{
	throw new Exception("Illegal View Type in vardata_modify.php");
}


// Construct a URL that can be used to download a thumbnail for the project
$ThumbnailImage = "./thumbnail_download.php?id=" . $projectrecord . "&projecttype=$editorview&sc=" . $thumbnailSecurityCheck . "&modified=" . md5($projectObj->getArtworkFile());
$t->set_var("IMAGE", $ThumbnailImage);



$productObj = $projectObj->getProductObj();
if($productObj->checkIfThumbnailBackSaved())
	$t->discard_block("origPage", "ProductHasNoThumbnailBackgroundImageBL");
else 
	$t->discard_block("origPage", "ProductDoesHaveThumbnailBackgroundImageBL");

	

	
$t->set_var("PRODUCT_TITLE", WebUtil::htmlOutput($productObj->getProductTitle()));	
$t->set_var("PRODUCT_TITLE_EXT", WebUtil::htmlOutput($productObj->getProductTitleExtension()));	
	
	
// ---------  Setup the Artwork File Area

if($artworkVarsObj->checkForEmptyVariablesInArtwork()){


	$t->discard_block("origPage", "ExtraMappedVarsBL");
	$t->discard_block("origPage", "UnMappedVarsBL");
	$t->discard_block("origPage", "ArtworkVariablesOrderBL");
	$t->discard_block("origPage", "OutOfRangeMappedVarsBL");
	
	
	// For Postcards we have a link showing them to add default variables to the backside (for mailing).
	if($projectObj->getProductID() != 79)

		$t->discard_block("origPage", "PostcardBacksideTemplates");
	
}
else if($artworkVarsObj->checkForUnmappedFields()){

	$t->discard_block("origPage", "ExtraMappedVarsBL");
	$t->discard_block("origPage", "NoArtworkVariablesBL");
	$t->discard_block("origPage", "ArtworkVariablesOrderBL");
	$t->discard_block("origPage", "OutOfRangeMappedVarsBL");
	
	$unMappedArtworkVarsArr = $artworkVarsObj->getUnMappedVariableNamesArr();
	
	

	$t->set_block("origPage","ArtworkVarsBL","ArtworkVarsBLout");

	
	$openPositionsArr = $artworkVarsObj->getOpenPositionsWithinColumnRange();
	
	

	foreach($unMappedArtworkVarsArr as $thisArtworkVar){
	
		
		$selectArr = array("0"=>"Choose");
		foreach($openPositionsArr as $pos)
			$selectArr[$pos] = $pos;
	
		$t->set_var("SELECT_OPTIONS", Widgets::buildSelect($selectArr, array(0)));
		$t->allowVariableToContainBrackets("SELECT_OPTIONS");
		
		$t->set_var("ARTWORKVARNAME", $thisArtworkVar);
	
		$t->parse("ArtworkVarsBLout","ArtworkVarsBL",true);
	}
	
}
else if($artworkVarsObj->checkForExtraMappedFields()){

	$t->discard_block("origPage", "UnMappedVarsBL");
	$t->discard_block("origPage", "NoArtworkVariablesBL");
	$t->discard_block("origPage", "ArtworkVariablesOrderBL");
	$t->discard_block("origPage", "OutOfRangeMappedVarsBL");

	
	$extraMappedArtworkVarsArr = $artworkVarsObj->getExtraMappedFieldsArr();
	
	
	$t->set_block("origPage","ArtworkVarsBL","ArtworkVarsBLout");
	
	
	foreach($extraMappedArtworkVarsArr as $thisArtworkVar){
	
		$t->set_var("ARTWORKVARNAME", WebUtil::htmlOutput($thisArtworkVar));
	
		$t->parse("ArtworkVarsBLout","ArtworkVarsBL",true);
	}
}
else if($artworkVarsObj->checkForMappedFieldsOutsideOfColumnSpace()){

	$t->discard_block("origPage", "ExtraMappedVarsBL");
	$t->discard_block("origPage", "UnMappedVarsBL");
	$t->discard_block("origPage", "NoArtworkVariablesBL");
	$t->discard_block("origPage", "ArtworkVariablesOrderBL");

	
	$outOfRangeMappedArtworkVarsArr = $artworkVarsObj->getMappedFieldsOutsideOfAvailableColumns();
	
	
	$t->set_block("origPage","ArtworkVarsBL","ArtworkVarsBLout");
	
	$openPositionsArr = $artworkVarsObj->getOpenPositionsWithinColumnRange();
	
	foreach($outOfRangeMappedArtworkVarsArr as $thisArtworkVar){
	
		$selectArr = array("0"=>"Choose");
		foreach($openPositionsArr as $pos)
			$selectArr[$pos] = $pos;
	
		$t->set_var("SELECT_OPTIONS", Widgets::buildSelect($selectArr, array(0)));
		$t->allowVariableToContainBrackets("SELECT_OPTIONS");
		
		$t->set_var("ARTWORKVARNAME", WebUtil::htmlOutput($thisArtworkVar));
	
		$t->parse("ArtworkVarsBLout","ArtworkVarsBL",true);
	}
}
else{
	// This means all of the fields have been mapped

	$t->discard_block("origPage", "ExtraMappedVarsBL");
	$t->discard_block("origPage", "UnMappedVarsBL");
	$t->discard_block("origPage", "NoArtworkVariablesBL");
	$t->discard_block("origPage", "OutOfRangeMappedVarsBL");
	

	$MappedArtworkVarsArr = $artworkVarsObj->getMappedVariableNamesArr();
	
	
	$t->set_block("origPage","ArtworkVarsBL","ArtworkVarsBLout");
	
	$totalColumns = $artworkVarsObj->getTotalColumns();
	
	$selectionArr = array();
	for($i=1; $i <= $totalColumns; $i++)
		$selectionArr[$i] = $i;
	
	
	foreach($MappedArtworkVarsArr as $thisArtworkVar){
	
		$variablePosition = $artworkVarsObj->getFieldPosition($thisArtworkVar);
	
		$t->set_var("SELECT_OPTIONS", Widgets::buildSelect($selectionArr, array($variablePosition)));
		$t->allowVariableToContainBrackets("SELECT_OPTIONS");
		
		$t->set_var("ARTWORKVARNAME", WebUtil::htmlOutput($thisArtworkVar));
		$t->set_var("ARTWORKVARNAME_ENCODED", urlencode($thisArtworkVar));
		
		
		
		// Find out if there are any Data Alteration Objects associated with this Variable Name.
		// If so show a signal that something is inside.
		if( $artworkVarsObj->checkIfVariableHasDataAlteration($thisArtworkVar) || $artworkVarsObj->checkIfVariableHasSizeRestriction($thisArtworkVar))
			$t->set_var("EXTRAS", "<font style='font-size:10px;'><font style='text-decoration:none;'><font color='#cc0000'>*</font> </font>Extra</font>");	
		else
			$t->set_var("EXTRAS", "<font style='font-size:10px;'>Extra</font>");
			
		$t->allowVariableToContainBrackets("EXTRAS");
		
	
		$t->parse("ArtworkVarsBLout","ArtworkVarsBL",true);
	}
	
	
	$t->set_var("VAR_COUNT", sizeof($MappedArtworkVarsArr));
	
	
	if(sizeof($MappedArtworkVarsArr) == 1){
		$t->discard_block("origPage", "MultiVariableBL");
		$t->discard_block("origPage", "MultiArtworkVarOrder");	
	}
	else{
		$t->discard_block("origPage", "SingleVariableBL");
		$t->discard_block("origPage", "SingleArtworkVarOrder");
	}
}



// ---------  Setup the Data File Section

// If the status other than 'D'ata error means that there is some data that has been uploaded
if($projectObj->getVariableDataFirstLine() == ""){

	$t->discard_block("origPage", "OnlyOneColumnDataFile");
	$t->discard_block("origPage", "MultiColumnDataFile");
	$t->discard_block("origPage", "WarningBL");
	$t->discard_block("origPage", "DataErrorBL");	
	$t->discard_block("origPage", "CsvDownloadBL");	
}
else if($projectObj->getVariableDataStatus() != "D"){

	$t->discard_block("origPage", "NoDataFileYetBL");
	
	// Only get the first line for the data file.  It could be a giant data file
	// So we don't want to parse that file every time the page loads.
	// Just put the 1 liner into a VariableData object... the 1 liner is treated the same as a complete file
	$variableDataObj = new VariableData();
	$variableDataObj->loadDataByTextFile($projectObj->getVariableDataFirstLine());
	
	$columnCount = $variableDataObj->getNumberOfFields();
	
	// Line count is directly tied to quantity
	$lineCount = $projectObj->getQuantity();

	$t->set_var("LINE_NUMBER", $lineCount);

	$t->set_var("PLURAL_IS", LanguageBase::GetPluralSuffix($lineCount, "is", "are"));
	$t->set_var("PLURAL_S", LanguageBase::GetPluralSuffix($lineCount, "", "s"));
	


	if($columnCount == 1){
		$t->discard_block("origPage", "MultiColumnDataFile");
		
		$t->set_var("FIRST_DATA_ELEMENT", WebUtil::htmlOutput($projectObj->getVariableDataFirstLine()));
		
	}
	else{
		$t->discard_block("origPage", "OnlyOneColumnDataFile");
		
		$t->set_var("COLUMN_NUMBER", $columnCount);
		
		// this 2day array will only have the first line in it
		$dataFileArr = $variableDataObj->getVariableDataArr();
		
		$t->set_block("origPage","DataSampleBL","DataSampleBLout");
		
		$counter = 1;
		foreach($dataFileArr[0] as $ColumnVal){
		
			$t->set_var("COLUMN_NO", $counter);
			$t->set_var("DATA_ELEMENT", WebUtil::htmlOutput($ColumnVal));
		
			$counter++;
			
			$t->parse("DataSampleBLout","DataSampleBL",true);
		}
	}
	
	
	$t->discard_block("origPage", "DataErrorBL");	

}
else{
	
	// For Data Errors

	$t->discard_block("origPage", "OnlyOneColumnDataFile");
	$t->discard_block("origPage", "MultiColumnDataFile");
	$t->discard_block("origPage", "NoDataFileYetBL");
	$t->discard_block("origPage", "WarningBL");
	$t->discard_block("origPage", "CsvDownloadBL");	
	
	$t->set_var("ERRORMSG", WebUtil::htmlOutput($projectObj->getVariableDataMessage()));
	
}



$t->set_var("CUSTOMER_USERID", "");


// Setup the Variable Images Section
if($projectObj->hasVariableImages()){

	// If there is a Variable Data Image missing or the user isn't logged in... then show the error message
	if($projectObj->getVariableDataStatus() == "L" || $projectObj->getVariableDataStatus() == "I")
		$t->set_var("VARIABLEIMAGE_ERRORMSG", WebUtil::htmlOutput($projectObj->getVariableDataMessage()));
	else
		$t->discard_block("origPage", "VariableImageErrorBL");
		
	
	$passiveAuthObj = Authenticate::getPassiveAuthObject();
	if($passiveAuthObj->CheckIfLoggedIn()){

		$dbCmd->Query("SELECT COUNT(*) FROM variableimagepointer WHERE UserID=" . ProjectSaved::GetSavedProjectOverrideUserID($passiveAuthObj));
		$imageCount = $dbCmd->GetValue();
		
		if($imageCount == 0){
			$ImageMessage = "No variable images have been uploaded yet.";
		}
		else if($imageCount == 1){
			$ImageMessage = "Only 1 variable image has been uploaded into your account.  You should probably be uploading many more.  
						Did you know that you can upload hundreds or even thousands of images all at once?";
		}
		else{
			$ImageMessage = "There are a total of $imageCount variable images uploaded and stored within your account. ";
			$ImageMessage .= "<a href='javascript:VariableDataImagesUploaded();' class='blueredlink'>Click here</a> to see a list of these images.";
			$ImageMessage .= "<br>&nbsp;";
		}
	
		$t->set_var("IMAGES_UPLOADED_MSG", "<br>$ImageMessage");
		$t->allowVariableToContainBrackets("IMAGES_UPLOADED_MSG");
	}
	else{
		$t->set_var("IMAGES_UPLOADED_MSG", "");
	}
	
	$t->allowVariableToContainBrackets("IMAGES_UPLOADED_MSG");
	
	
	// If There is a Customer ID for an order because we are in proof mode.  Then append some data to the URL for other pages that may get loaded off of this page
	if($customerIDFromOrder)
		$t->set_var("CUSTOMER_USERID", "customeruserid=" . $customerIDFromOrder);
		
	
	$t->set_var("VARIMAGE_CHANGE_DESC", "Remove Variable Images from this Project");
	
}
else{

	$t->set_var("VARIMAGE_CHANGE_DESC", "Add Variable Images to this Project");

	$t->discard_block("origPage", "VariableImageBL");
}

	
	



// If there are any Artwork Errors we don't want to show them a Warning Message Too
if($projectObj->getVariableDataStatus() == "A")
	$t->discard_block("origPage", "WarningBL");
else if($projectObj->getVariableDataStatus() == "W")
	$t->set_var("WARNINGMSG", WebUtil::htmlOutput($projectObj->getVariableDataMessage()));
else
	$t->discard_block("origPage", "WarningBL");


// So javascript can know if a PDF with merge is possible.  Only "G"ood or "W"arning
if($projectObj->getVariableDataStatus() == "W" || $projectObj->getVariableDataStatus() == "G")
	$t->set_var("ERROR_FLAG", "false");
else
	$t->set_var("ERROR_FLAG", "true");
	

$t->set_var("VARIABLE_DATA_STATUS", $projectObj->getVariableDataStatus());

// Set a message that will fit into quotes for javascript.  Put a newline break after every period.
$t->set_var("VARIABLE_DATA_MESSAGE_JS", preg_replace("/\.\s/", "\.\\\\n", addslashes($projectObj->getVariableDataMessage())));


// If we are in Proof Mode... then extract the Javascript and Body from the template and place it within
// a generic administrative template.
if($editorview == "proof"){

	// Because this is an Admin Page, we need to change the directory root so that the template doesn't try to come out of a domain sandbox.
	$t->setDirectoryRoot(".");
	$t->set_file("adminPage", "ad_vardata_modify-template.html");
	
	$t->set_var("PROJECT_NUMBER", $projectrecord);
	
	$t->allowVariableToContainBrackets("OriginalTempalteOut");
	$t->parse("OriginalTempalteOut", "origPage");
	
	
	$t->set_var("BodySectionBL", "");
	$t->set_var("JavaScriptSectionBL", "");
	

	if(WebUtil::GetInput("reloadparent", FILTER_SANITIZE_STRING_ONE_LINE))
		$t->set_var("JS_RELOAD_PARENT", "RefreshParentWindow();");
	else
		$t->set_var("JS_RELOAD_PARENT", "");


 	$t->pparse("OUT","adminPage");


}
else{
	
	VisitorPath::addRecord("Variable Data (Advanced Mode)");

	$t->pparse("OUT","origPage");
}




?>
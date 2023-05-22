<?


require_once("library/Boot_Session.php");






$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);
$UserID = $AuthObj->GetUserID();





$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$projectid = WebUtil::GetInput("projectid", FILTER_SANITIZE_INT);
$sidenumber = WebUtil::GetInput("sidenumber", FILTER_SANITIZE_INT);
$active_ids = WebUtil::GetInput("active_ids", FILTER_SANITIZE_STRING_ONE_LINE);
$clipboard_ids = WebUtil::GetInput("clipboard_ids", FILTER_SANITIZE_STRING_ONE_LINE);
$command = WebUtil::GetInput("command", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$returl = WebUtil::GetInput("returl", FILTER_SANITIZE_URL);
$transfer_ids = WebUtil::GetInput("transfer_ids", FILTER_SANITIZE_STRING_ONE_LINE);
$attribute = WebUtil::GetInput("attribute", FILTER_SANITIZE_STRING_ONE_LINE);
$doNotShowEyeCandy = WebUtil::GetInput("doNotShowEyeCandy", FILTER_SANITIZE_STRING_ONE_LINE);



if($command != "copyall")
	WebUtil::checkFormSecurityCode();

	
	
// If the variable "doNotShowEyeCandy" does not come in the URL then we should show eye candy
// The eye candy is just to keep the user busy while the thumbnail finished generating... somtimes it can take a while
// We do not need to show Eye candy on some of the clipboard commands because they will never require a thumbanil to generate, it will happen really quick
// Also in proof mode we do not need to wait for thumbnails to generate... because they are generated in the background.
if(!$doNotShowEyeCandy && $view != "proof" && !in_array($command, array("copyclip", "remove", "copyall"))){

	// The new URL will have the parameter doNotShowEyeCandy this time around... to keep it from going in a loop
	$newActionURL = $_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING'] . "&doNotShowEyeCandy=true";
	
	$eyecandyURL = "artwork_update.php?projectrecord=" . intval($projectid) . "&viewtype=" . urlencode($projectid) . "&windowtype=popup&redirect=" . urlencode($newActionURL);
	
	header("Location: " . WebUtil::FilterURL($eyecandyURL));
	exit;
}



ProjectBase::EnsurePrivilagesForProject($dbCmd, $view, $projectid);



//Set this variable to true... if we want the opener page to reload..  This may be useful if we are updating a thumnail image or something.
$reloadparent = "false";


if($command == "copyclip"){

	$ActiveIDArr = GetIDArrFromStr($active_ids);
	
	$ProductID = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $projectid, $view);
	
	$ArtworkXMLstring = ArtworkLib::GetArtXMLfile($dbCmd, $view, $projectid);
	$ArtworkInfoObj = new ArtworkInformation($ArtworkXMLstring);

	if($sidenumber > sizeof($ArtworkInfoObj->SideItemsArray) || !preg_match("/^\d+$/", $sidenumber))
		throw new Exception("Problem with the Side Number");


	$ArtworkInfoObj->orderLayersByLayerLevel($sidenumber);

	// Reverse the array so that it will match up to the Layer stacking order on the user Interface.
	// We want the highest level to appear first in the list (top of the HTML stack)... which is reversed to the actual stacking.
	$LayersSorted = array_reverse($ArtworkInfoObj->SideItemsArray[$sidenumber]->layers);


	// Loop through all of the layers for the current side.  If we find a match on any of the level ID's, then copy it to the clipboard session variable.
	foreach($LayersSorted as $ThisLayer){
		if(in_array($ThisLayer->level, $ActiveIDArr)){
			Clipboard::AddToClipBoard($ProductID, $ThisLayer);

			// If the layer has a Shadow, then copy the shadow across too.
			if($ThisLayer->LayerType == "text" && $ArtworkInfoObj->CheckIfTextLayerHasShadow($sidenumber, $ThisLayer->level)){
			
				$shadowLayerLevel = $ArtworkInfoObj->GetLayerLevelOfShadowLayer($sidenumber, $ThisLayer->level);
				
				Clipboard::AddToClipBoard($ProductID, $ArtworkInfoObj->GetLayerObject($sidenumber, $shadowLayerLevel));
			}
		}
	}
	
	// Destroy any complete artwork file from our session.  We are making changes to the layers;
	Clipboard::ClearArtworkCopyFromSession();
	
}
else if($command == "copydoc"){

	// Reverse the clipboard IDs so that they will copy onto the document in the same sequence as the layer stacking.
	$ClipboardIDArr = array_reverse(GetIDArrFromStr($clipboard_ids));
	$ClipBArr = Clipboard::GetClipBoardArray();
	
	$ArtworkXMLstring = ArtworkLib::GetArtXMLfile($dbCmd, $view, $projectid);
	$ArtworkInfoObj = new ArtworkInformation($ArtworkXMLstring);

	if($sidenumber > sizeof($ArtworkInfoObj->SideItemsArray) || !preg_match("/^\d+$/", $sidenumber))
		throw new Exception("Problem with the Side Number");

	$ProductID = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $projectid, $view);

	// Copy layers from the clipboard to the active document
	foreach($ClipboardIDArr as $ThisClipboardLayerID){
	

		// Just be safe in case the artwork is changed in a second window or the session expired or something.
		if(!isset($ClipBArr[$ThisClipboardLayerID]))
			continue;
	
		// Don't copy over Shadow layers directly... they will be moved across with the parent when the time is right.
		if(Clipboard::CheckIfClipboardIDisShadowToAnotherLayer($ThisClipboardLayerID))
			continue;
		
		// If the Product ID does not match, then we need to do an Artwork Conversion
		if($ProductID != $ClipBArr[$ThisClipboardLayerID]["ProductID"]){
		
			$artworkConversionObj = new ArtworkConversion($dbCmd);
			$artworkConversionObj->setFromArtwork($ClipBArr[$ThisClipboardLayerID]["ProductID"]);
			$artworkConversionObj->setToArtwork($ProductID);
			$artworkConversionObj->setScalePercentagesFromArtworks();
		}
		else{
			$artworkConversionObj = null;
		}
		
		// If the clipboard layer has a shadow, then we need to copy the shadow over too
		if(Clipboard::CheckIfClipboardIDHasShadow($ThisClipboardLayerID)){

			$shadowLevelClipboardID = Clipboard::GetClipboardIDofShadow($ThisClipboardLayerID);
				
			// LayerObj is Passed by Reference. Convert the Layer Dimensions/Coordinates if Products are different.
			if($artworkConversionObj)
				$artworkConversionObj->resizeAndRepositionLayer($ClipBArr[$shadowLevelClipboardID]["LayerObj"]);

			$newLayerLevelOfShadow = $ArtworkInfoObj->AddLayerObjectToSide($sidenumber, $ClipBArr[$shadowLevelClipboardID]["LayerObj"]);

			// Since the layer level may have changed after putting it back into the document
			// we need to update the Shadow Link with the new Layer Level
			$ClipBArr[$ThisClipboardLayerID]["LayerObj"]->LayerDetailsObj->shadow_level_link = $newLayerLevelOfShadow;
		}

		// LayerObj is Passed by Reference. Convert the Layer Dimensions/Coordinates if Products are different.
		if($artworkConversionObj)
			$artworkConversionObj->resizeAndRepositionLayer($ClipBArr[$ThisClipboardLayerID]["LayerObj"]);

		$ArtworkInfoObj->AddLayerObjectToSide($sidenumber, $ClipBArr[$ThisClipboardLayerID]["LayerObj"]);

	}


	// Now Store the Artwork File back in the DB 
	ArtworkLib::SaveArtXMLfile($dbCmd, $view, $projectid, $ArtworkInfoObj->GetXMLdoc());

	// Record into the Project History if this is a project that has been ordered.
	if(in_array($view, array("proof", "admin", "projectsordered", "customerservice")))
		ProjectHistory::RecordProjectHistory($dbCmd, $projectid, "Clipboard: Layer(s) Copied to Document", $UserID);


	ThumbImages::CreateThumnailImage($dbCmd, $projectid, $view);

	MaybeCloneSavedProjectFromOrder($dbCmd, $view, $projectid);

	$reloadparent = "true";

	// Destroy any complete artwork file from our session.  We are making changes to the layers;
	Clipboard::ClearArtworkCopyFromSession();

}
else if($command == "copyattribute"){

	$ActiveIDArr = GetIDArrFromStr($active_ids);

	$ArtworkXMLstring = ArtworkLib::GetArtXMLfile($dbCmd, $view, $projectid);
	$ArtworkInfoObj = new ArtworkInformation($ArtworkXMLstring);

	// The Attribute comes in the URL like...   color:#CCCCCC
	// We want to break it apart at the colon.
	$attArr = split(":", $attribute);
	if(sizeof($attArr) <> 2)
		throw new Exception("Error with attribute in URL");

	
	$attributeType = $attArr[0];
	$attributeValue = $attArr[1];
	

	// Copy the attribute to all of the layers specified	
	foreach($ActiveIDArr as $ThisActiveID){
		$ArtworkInfoObj->ChangeAttribute($sidenumber, $ThisActiveID, $attributeType, $attributeValue);
		
		// Find out if the Layer has a shadow associated with it.
		// We don't want to copy over a color attribute though... color is controlled separately on the shadow
		if($attributeType != "color" && $ArtworkInfoObj->CheckIfTextLayerHasShadow($sidenumber, $ThisActiveID)){
			$shadowLinkLevel = $ArtworkInfoObj->GetLayerLevelOfShadowLayer($sidenumber, $ThisActiveID);
			$ArtworkInfoObj->ChangeAttribute($sidenumber, $shadowLinkLevel, $attributeType, $attributeValue);
		}
	}


	// Now Store the Artwork File back in the DB 
	ArtworkLib::SaveArtXMLfile($dbCmd, $view, $projectid, $ArtworkInfoObj->GetXMLdoc());

	// Record into the Project History if this is a project that has been ordered.
	if(in_array($view, array("proof", "admin", "projectsordered", "customerservice")))
		ProjectHistory::RecordProjectHistory($dbCmd, $projectid, "Clipboard: Single Attribute Transfered", $UserID);

	ThumbImages::CreateThumnailImage($dbCmd, $projectid, $view);
	
	MaybeCloneSavedProjectFromOrder($dbCmd, $view, $projectid);

	$reloadparent = "true";

	// Destroy any complete artwork file from our session.  We are making changes to the layers;
	Clipboard::ClearArtworkCopyFromSession();
	
}
else if($command == "remove"){

	$ClipboardIDArr = GetIDArrFromStr($clipboard_ids);
	
	// Loop through all of the layers for the current side.  If we find a match on any of the level ID's, then copy it to the clipboard session variable.
	foreach($ClipboardIDArr as $ThisClipboardID)
		Clipboard::RemoveFromClipBoard($ThisClipboardID);

	// Destroy any complete artwork file from our session.  We are making changes to the layers;
	Clipboard::ClearArtworkCopyFromSession();	
}
else if($command == "copyall"){

	// We want to purge the existing clipboard and copy the current artwork to clipboard
	// The all sides should be copied.... front.. back etc.
	Clipboard::PurgeClipboard();

	$ProductID = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $projectid, $view);

	$ArtworkXMLstring = ArtworkLib::GetArtXMLfile($dbCmd, $view, $projectid);
	$ArtworkInfoObj = new ArtworkInformation($ArtworkXMLstring);
	
	for($i=0; ($i < sizeof($ArtworkInfoObj->SideItemsArray)); $i++){

		$ArtworkInfoObj->orderLayersByLayerLevel($i);
		
		// Reverse the array so that it will match up to the Layer stacking order on the user Interface.
		// We want the highest level to appear first in the list (top of the HTML stack)... which is reversed to the actual stacking.
		$LayersSorted = array_reverse($ArtworkInfoObj->SideItemsArray[$i]->layers);

		// Loop through all of the layers for the current side.  If we find a match on any of the level ID's, then copy it to the clipboard session variable.
		foreach($LayersSorted as $ThisLayer)
			Clipboard::AddToClipBoard($ProductID, $ThisLayer);
	}



	// This will set the AtworkXML in our session.  
	// It is a special session variable that will allow us to import the complete document somewhere else
	Clipboard::CopyArtworkToSession($ArtworkXMLstring, $ProductID, $view, $projectid);

	header ("Content-Type: text/xml");
	// It seems that when you hit session_start it will send a Pragma: NoCache in the header
	// When comminicating over HTTPS there is a bug with IE.  It can not get the documents after they have finished downloading because they have already expired
	// We can allow the file to be cached be sending this header.  When requesting the document you can put a ?noache=timestamp
	header("Pragma: public");

	print "<?xml version=\"1.0\" ?>
		<response> 
		<success>good</success>
		<description></description>
		</response>"; 
	exit;


	
}
else if($command == "importartwork"){

	$ProductID = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $projectid, $view);

	// If we are going to import a complete Artwork XML file that is stored in the session...
	// First make sure it is still there... maybe the session expired or something.. It will fail silently on an error
	if(Clipboard::CheckIfArtworkIsCopiedToSession()){
	
		$ProductIDfromCopy = Clipboard::GetArtworkCopyProductIDFromSession();
	
		$ArtworkFileOnClipboard = Clipboard::GetArtworkCopyFromSession();
		
		// If the Product IDs do not match then we need to do an artwork conversion.
		if($ProductIDfromCopy != $ProductID){
			$artworkConversionObj = new ArtworkConversion($dbCmd);
			$artworkConversionObj->setFromArtwork($ProductIDfromCopy, $ArtworkFileOnClipboard);
			$artworkConversionObj->setToArtwork($ProductID);
			$artworkConversionObj->removeBacksideFlag(false);
			$ArtworkFileOnClipboard = $artworkConversionObj->getConvertedArtwork();
		}

		// Now Store the Artwork File back in the DB 
		ArtworkLib::SaveArtXMLfile($dbCmd, $view, $projectid, $ArtworkFileOnClipboard);

		// Record into the Project History if this is a project that has been ordered.
		if(in_array($view, array("proof", "admin", "projectsordered", "customerservice")))
			ProjectHistory::RecordProjectHistory($dbCmd, $projectid, "Clipboard: Artwork Imported", $UserID);

		ThumbImages::CreateThumnailImage($dbCmd, $projectid, $view);

		// In case double-sided artwork was imported onto a single-sided project
		// It will also clone over to the SavedProject if needed
		ProjectBase::ChangeArtworkBasedOnProjectOptions($dbCmd, $projectid, $view);
		
		
		// In case we are importing from a Saved Project, we want to establish and artwork Link between the two
		// If the SavedID = 0 ... than means no link
		if($view == "proof"){
			
			$UpdateHash = array();
			$UpdateHash["SavedID"] = Clipboard::GetSavedProjectLinkForArtworkImport();
			
			$dbCmd->UpdateQuery( "projectsordered", $UpdateHash, "ID=$projectid");
		}

	}
	
	
	
	$reloadparent = "true";

}
else if($command == "apply"){


	$ArtworkXMLstring = ArtworkLib::GetArtXMLfile($dbCmd, $view, $projectid);
	$ArtworkInfoObj = new ArtworkInformation($ArtworkXMLstring);
	$ClipBArr = Clipboard::GetClipBoardArray();

	$LinksArray = split("\|", $transfer_ids);
	foreach($LinksArray as $ThisLink){
		if(preg_match("/^\d+-\d+$/", $ThisLink)){
		
			$LinkInfoArr = split("-", $ThisLink);
			$ClipID = $LinkInfoArr[0];
			$ActiveID = $LinkInfoArr[1];
			
			if(isset($ClipBArr[$ClipID]))
				$ArtworkInfoObj->TransferAttributes($sidenumber, $ActiveID, $ClipBArr[$ClipID]["LayerObj"]);		
		}
	}
	
	// Now Store the Artwork File back in the DB 
	ArtworkLib::SaveArtXMLfile($dbCmd, $view, $projectid, $ArtworkInfoObj->GetXMLdoc());

	// Record into the Project History if this is a project that has been ordered.
	if(in_array($view, array("proof", "admin", "projectsordered", "customerservice")))
		ProjectHistory::RecordProjectHistory($dbCmd, $projectid, "Clipboard: Attributes Transfered", $UserID);

	ThumbImages::CreateThumnailImage($dbCmd, $projectid, $view);
	
	MaybeCloneSavedProjectFromOrder($dbCmd, $view, $projectid);

	$reloadparent = "true";

	// Destroy any complete artwork file from our session.  We are making changes to the layers;
	Clipboard::ClearArtworkCopyFromSession();
}
else if($command == "replace"){

	// Reverse the clipboard IDs so that they will copy onto the document in the same sequence as the layer stacking.
	$ClipboardIDArr = array_reverse(GetIDArrFromStr($clipboard_ids));
	$ActiveIDArr = array_reverse(GetIDArrFromStr($active_ids));

	$ArtworkXMLstring = ArtworkLib::GetArtXMLfile($dbCmd, $view, $projectid);
	$ArtworkInfoObj = new ArtworkInformation($ArtworkXMLstring);


	$ClipBArr = Clipboard::GetClipBoardArray();

	// Make sure we are not trying modify a side that does not exist
	if(!preg_match("/^\d+$/", $sidenumber) || $sidenumber > sizeof($ArtworkInfoObj->SideItemsArray)){
		print "Problem with the Side Number";
		exit;
	}
	
	$ProductID = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $projectid, $view);

	// Keep a record of the Layer Levels of the ones that we will be replacing.
	// That way when we replace graphics on top we will prefer to keep the layer stacking order
	$graphicLayerLevelsReplacedArr = array();

	// Get Rid of the layers on the active document
	foreach($ActiveIDArr as $ThisActiveLayerID){
		
		$layerObjToBeReplaced = $ArtworkInfoObj->GetLayerObject($sidenumber, $ThisActiveLayerID);
		
		if($layerObjToBeReplaced->LayerType == "graphic")
			$graphicLayerLevelsReplacedArr[] = $ThisActiveLayerID;
		
		$ArtworkInfoObj->RemoveLayerFromArtworkObj($sidenumber, $ThisActiveLayerID);
	}
	
	$clipBoardGraphicCounter = 0;
	
	// Copy layers from the clipboard to the active document
	foreach($ClipboardIDArr as $ThisClipboardLayerID){

		// Just be safe in case the artwork is changed in a second window or the session expired or something.
		if(!isset($ClipBArr[$ThisClipboardLayerID]))
			continue;

		// Don't copy over Shadow layers directly... they will be moved across with the parent when the time is right.
		if(Clipboard::CheckIfClipboardIDisShadowToAnotherLayer($ThisClipboardLayerID))
			continue;


		// If the Product ID does not match, then we need to do an Artwork Conversion
		if($ProductID != $ClipBArr[$ThisClipboardLayerID]["ProductID"]){
		
			$artworkConversionObj = new ArtworkConversion($dbCmd);
			$artworkConversionObj->setFromArtwork($ClipBArr[$ThisClipboardLayerID]["ProductID"]);
			$artworkConversionObj->setToArtwork($ProductID);
			$artworkConversionObj->setScalePercentagesFromArtworks();
		}
		else{
			$artworkConversionObj = null;
		}


		// If the clipboard layer has a shadow, then we need to copy the shadow over too
		if(Clipboard::CheckIfClipboardIDHasShadow($ThisClipboardLayerID)){

			$shadowLevelClipboardID = Clipboard::GetClipboardIDofShadow($ThisClipboardLayerID);

			// LayerObj is Passed by Reference. Convert the Layer Dimensions/Coordinates if Products are different.
			if($artworkConversionObj)
				$artworkConversionObj->resizeAndRepositionLayer($ClipBArr[$shadowLevelClipboardID]["LayerObj"]);

			$newLayerLevelOfShadow = $ArtworkInfoObj->AddLayerObjectToSide($sidenumber, $ClipBArr[$shadowLevelClipboardID]["LayerObj"]);

			// Since the layer level may have changed after putting it back into the document
			// we need to update the Shadow Link with the new Layer Level
			$ClipBArr[$ThisClipboardLayerID]["LayerObj"]->LayerDetailsObj->shadow_level_link = $newLayerLevelOfShadow;
		}

		// LayerObj is Passed by Reference. Convert the Layer Dimensions/Coordinates if Products are different.
		if($artworkConversionObj)
			$artworkConversionObj->resizeAndRepositionLayer($ClipBArr[$ThisClipboardLayerID]["LayerObj"]);

		$newLayerLevel = $ArtworkInfoObj->AddLayerObjectToSide($sidenumber, $ClipBArr[$ThisClipboardLayerID]["LayerObj"]);
		
		
		// As long as we are replacing the Same or Less graphics on the Active document 
		// ... (relative to the number of Graphics on the clipboard)
		// then we can keep the same layer levels (and stacking order).
		$newlyAddedLayerObj = $ArtworkInfoObj->GetLayerObject($sidenumber, $newLayerLevel);
		if($newlyAddedLayerObj->LayerType == "graphic"){
			
			if($clipBoardGraphicCounter < sizeof($graphicLayerLevelsReplacedArr)){
				if($ArtworkInfoObj->CheckIfLayerLevelAvailable($sidenumber, $graphicLayerLevelsReplacedArr[$clipBoardGraphicCounter]))
					$ArtworkInfoObj->ChangeLayerLevel($sidenumber, $newLayerLevel, $graphicLayerLevelsReplacedArr[$clipBoardGraphicCounter]);
			}
		
			$clipBoardGraphicCounter++;
		}

	}

	// Now Store the Artwork File back in the DB 
	ArtworkLib::SaveArtXMLfile($dbCmd, $view, $projectid, $ArtworkInfoObj->GetXMLdoc());

	// Record into the Project History if this is a project that has been ordered.
	if(in_array($view, array("proof", "admin", "projectsordered", "customerservice")))
		ProjectHistory::RecordProjectHistory($dbCmd, $projectid, "Clipboard: Layer(s) Replaced on Document", $UserID);

	ThumbImages::CreateThumnailImage($dbCmd, $projectid, $view);
	
	MaybeCloneSavedProjectFromOrder($dbCmd, $view, $projectid);

	$reloadparent = "true";

	// Destroy any complete artwork file from our session.  We are making changes to the layers;
	Clipboard::ClearArtworkCopyFromSession();
}
else if($command == "layerpermission"){

	$layerLevel = WebUtil::GetInput("layerlevel", FILTER_SANITIZE_INT);
	$layerType = WebUtil::GetInput("layertype", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
	

	$ArtworkXMLstring = ArtworkLib::GetArtXMLfile($dbCmd, $view, $projectid);
	$ArtworkInfoObj = new ArtworkInformation($ArtworkXMLstring);


	if(!isset($ArtworkInfoObj->SideItemsArray[$sidenumber]))
		throw new Exception("Error in clipboard action layerpermission with Side Number.");
			

	for($j=0; $j < sizeof($ArtworkInfoObj->SideItemsArray[$sidenumber]->layers); $j++){

		if($ArtworkInfoObj->SideItemsArray[$sidenumber]->layers[$j]->level == $layerLevel){

			// Make a reference to the Permission Object within the Layer to keep code shorter.
			$permissionObj =& $ArtworkInfoObj->SideItemsArray[$sidenumber]->layers[$j]->LayerDetailsObj->permissions;
			
			if($layerType == "text" || $layerType == "graphic"){

				$permissionObj->position_x_locked = (WebUtil::GetInput("position_x_locked", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "yes");
				$permissionObj->position_y_locked = (WebUtil::GetInput("position_y_locked", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "yes");
				$permissionObj->size_locked = (WebUtil::GetInput("size_locked", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "yes");
				$permissionObj->deletion_locked = (WebUtil::GetInput("deletion_locked", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "yes");
				$permissionObj->rotation_locked = (WebUtil::GetInput("rotation_locked", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "yes");
				$permissionObj->not_selectable = (WebUtil::GetInput("selectable_no", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "yes");
				$permissionObj->not_transferable = (WebUtil::GetInput("transferable_no", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "yes");
			}
			
			if($layerType == "text"){
				$permissionObj->color_locked = (WebUtil::GetInput("color_locked", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "yes");
				$permissionObj->font_locked = (WebUtil::GetInput("font_locked", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "yes");
				$permissionObj->alignment_locked = (WebUtil::GetInput("alignment_locked", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "yes");
				$permissionObj->data_locked = (WebUtil::GetInput("data_locked", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "yes");
			}
			else if($layerType == "graphic"){
				$permissionObj->always_on_top = (WebUtil::GetInput("always_on_top", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES) == "yes");
			}
			else{
				throw new Exception("Illegal Layer Type on the layer permissions.");
			}
			
		}
	}
	
	ArtworkLib::SaveArtXMLfile($dbCmd, $view, $projectid, $ArtworkInfoObj->GetXMLdoc());

	// Record into the Project History if this is a project that has been ordered.
	if(in_array($view, array("proof", "admin", "projectsordered", "customerservice")))
		ProjectHistory::RecordProjectHistory($dbCmd, $projectid, "Clipboard: Changed Layer Permissions", $UserID);

	MaybeCloneSavedProjectFromOrder($dbCmd, $view, $projectid);

	$reloadparent = "false";
}
else{
	throw new Exception("Error With Command.");
}


// Adding/Removing text layers could affect the Variable Data Status.
// However, Templates do not have Variable Data statuses.
if(!in_array($view, array("template_category", "template_searchengine")))
	VariableDataProcess::SetVariableDataStatusForProject($dbCmd, $projectid, $view);


header("Location: ". WebUtil::FilterURL($returl) . "&reloadparent=" . $reloadparent );




// If we are dealing with a project that has already been ordered... then we would want to call this funtion
// It checks the view type to see what it should do
function MaybeCloneSavedProjectFromOrder(DbCmd $dbCmd, $ViewType, $ProjectRecordID){

	if($ViewType == "proof" || $ViewType == "projectsordered" || $ViewType == "admin"){
		ProjectOrdered::CloneOrderForSavedProject($dbCmd, $ProjectRecordID);
	}

}


function GetIDArrFromStr($str){

	$retArr = array();
	
	$xArr = split("\|", $str);
	
	foreach($xArr as $ThisID){
		if(preg_match("/^\d+$/", $ThisID))
			$retArr[] = $ThisID;
	}


	return $retArr;
	
}




?>
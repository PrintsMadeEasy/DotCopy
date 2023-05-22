<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();


if(!isset($HTTP_SESSION_VARS['editor_ArtworkID'])){
	$HTTP_SESSION_VARS['editor_ArtworkID'] = "";
}
if(!isset($HTTP_SESSION_VARS['editor_View'])){
	$HTTP_SESSION_VARS['editor_View'] = "";
}
if(!isset($HTTP_SESSION_VARS['TempXMLholder'])){
	$HTTP_SESSION_VARS['TempXMLholder'] = "";
}
if(!isset($HTTP_SESSION_VARS['SwitchSides'])){
	$HTTP_SESSION_VARS['SwitchSides'] = "";
}


$editorView = $HTTP_SESSION_VARS['editor_View'];
$RecordID = $HTTP_SESSION_VARS['editor_ArtworkID'];

$recordIdOverride = WebUtil::GetInput("RecordId", FILTER_SANITIZE_INT);

if(!empty($recordIdOverride)){
	$editorView = "template_searchengine";
	$RecordID = $recordIdOverride;
}



//If we are switching sides then just get the artwork out of the temporary session var
//otherwise we can extract it from the datbase
// Don't check if the value is empty because the side number could be "0" zero
if(preg_match("/^\d+$/", $HTTP_SESSION_VARS['SwitchSides'])){
	$artworkXMLfile = $HTTP_SESSION_VARS['TempXMLholder'];
	
	// If we are trying to switch to a side view and don't have something in our TempXMLholder... then we should try to extract it from the Database.
	if(empty($artworkXMLfile)){
		try{
			ProjectBase::EnsurePrivilagesForProject($dbCmd, $editorView, $RecordID);
		}
		catch (ExceptionPermissionDenied $e){
			print "<?xml version=\"1.0\" ?>\n<problem>".WebUtil::htmlOutput($e->getMessage())."</problem>";
		}
		catch (Exception $e){
			print "<?xml version=\"1.0\" ?>\n<problem>".WebUtil::htmlOutput("An unknown exception occured.")."</problem>";
		}
		
		$artworkXMLfile = ArtworkLib::GetArtXMLfile($dbCmd, $editorView, $RecordID);
	}
}
else if($editorView == "projectssession" || $editorView == "session" || $editorView == "template_category" || $editorView == "proof" || $editorView == "customerservice" || $editorView == "saved" || $editorView == "template_searchengine"){
	
	try{
		ProjectBase::EnsurePrivilagesForProject($dbCmd, $editorView, $RecordID);
	}
	catch (ExceptionPermissionDenied $e){
		print "<?xml version=\"1.0\" ?>\n<problem>".WebUtil::htmlOutput($e->getMessage())."</problem>";
	}
	catch (Exception $e){
		print "<?xml version=\"1.0\" ?>\n<problem>".WebUtil::htmlOutput("An unknown exception occured.")."</problem>";
	}
	
	$artworkXMLfile = ArtworkLib::GetArtXMLfile($dbCmd, $editorView, $RecordID);
	
	
	if($editorView == "template_category" || $editorView == "template_searchengine"){
		
		$productID = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $RecordID, $editorView);
		$productObj = new Product($dbCmd, $productID, false);
		
		$defaultProductArtwork = $productObj->getDefaultProductArtwork();
		
		$ArtworkObj = new ArtworkInformation($defaultProductArtwork);
		
		for($i=0; $i<sizeof($ArtworkObj->SideItemsArray); $i++) {
			$ArtworkObj->RemoveLayerTypeFromSide($i, "text");
			$ArtworkObj->RemoveLayerTypeFromSide($i, "graphic");
		}
		
		$artworkMergeObj = new ArtworkMerge($dbCmd);
		$artworkMergeObj->setBottomArtwork($ArtworkObj->GetXMLdoc());
		$artworkMergeObj->setTopArtwork($artworkXMLfile);
		
		$artworkXMLfile = $artworkMergeObj->getMergedArtwork();
	}
	
	// For Saved Projects and Shopping card projects we want to make sure that the artwork matches the total number of possible sides.
	// If the artwork only has one side on it... then we want to add the default artwork from the backside.
	// This is an easier way for people to "choose a backside" on their artwork... because there will always be a "Back" tab.
	// If the backside is blank... when they save the artwork it will automatically delete the backside.
	if($editorView == "projectssession" || $editorView == "saved" ){
		
		$productID = ProjectBase::getProductIDquick($dbCmd, $editorView, $RecordID);
		$productObj = new Product($dbCmd, $productID, false);
		
		$defaultProductArtObj = new ArtworkInformation($productObj->getDefaultProductArtwork());
		$artInfoObj = new ArtworkInformation($artworkXMLfile);
		
		$defaultSidesCount = sizeof($defaultProductArtObj->SideItemsArray);
		$artworkSidesCount = sizeof($artInfoObj->SideItemsArray);
		$diffSideCount = $defaultSidesCount - $artworkSidesCount;
		
		if($diffSideCount < 0)
			$diffSideCount = 0;
			
		if($diffSideCount > 0){
			
			for($i=0; $i< $diffSideCount; $i++){
				
				$sideNumberToCopy = $artworkSidesCount + $i;
				
				$artInfoObj->SideItemsArray[$sideNumberToCopy] = $defaultProductArtObj->SideItemsArray[$sideNumberToCopy];
			}
			
			// Get the new Artwork Doc with the missing sides merged in.
			$artworkXMLfile = $artInfoObj->GetXMLdoc();
		}
		
		
	}
}
else{
	$artworkXMLfile = "<?xml version=\"1.0\" ?>\n<problem>There was a problem downloading the artwork template.\nThe View type is invalid\n\nYou will have to start this project over again.  If the problem persists contact...\n the Webmaster</problem>";
}


header ("Content-Type: text/xml");


// It seems that when you hit session_start it will send a Pragma: NoCache in the header
// When comminicating over HTTPS there is a bug with IE.  It can not get the flash documents after they have finished downloading because they have already expired
// We can allow the file to be cached be sending this header.  When requesting the document you can put a ?noache=timestamp
// This is the only way to get flash communication to work over HTTPS with session variables
header("Pragma: public");
session_write_close();


// If the Record Override is being used... then we want to put the Kewords into the bottom node.
if(!empty($recordIdOverride)){
	
	$dbCmd->Query("SELECT TempKw FROM templatekeywords WHERE TemplateID=" . intval($recordIdOverride));
	$keywordArr = $dbCmd->GetValueArr();
	
	$keywordStr = implode(" ", $keywordArr);
	$keywordStr = htmlspecialchars($keywordStr);
	
	$artworkXMLfile = preg_replace("/<content>/", "<content>\n<keywords>$keywordStr</keywords>\n", $artworkXMLfile);
	
}

#-- Send artwork back to the browser
if(!empty($artworkXMLfile)){
	print $artworkXMLfile;
}
else{
	print "<?xml version=\"1.0\" ?>\n<problem>There was a problem downloading the artwork template.\n\nYou will have to start this project over again.  If the problem persists contact...\n the Webmaster</problem>";
	WebUtil::WebmasterError("There was a problem downloading the artwork template.");
}



?>
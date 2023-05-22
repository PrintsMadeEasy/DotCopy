<?


// Use the function call to the contstants GetThumbnailImageTables() to get a list of all thumbnail image tables
// The most recent tables will always be listed on top.
// Because we could eventually have millions and millions of records... we can't hold all of the thumbnail images in 1 single table
// For one the database file could exceed a maximum file size limit on the operating system.  Also it is not practical to keep downloading backups of thumbnail images no longer needed
// The first thumbnail table listed in the array is the "active" table that we will delete from and write to.
// All of the other tables are permanently locked and we will not delete any thing from them or write any additional information... so once we move to a new table we can backup the old one, burn it to a DVD and protect it for life.  Then we just download backups on the "active" table.
// When we want to fetch a thumbnail image we will first start looking in the "active" thumbnail table.  If we can't find it, then look and the next most recent table, and so on and so on.  Eventually we should locate our thumbnail image, and if not, throw a permanent error.
// It is possible that we could have multiple thumbnail images for a single saved project, stored within multiple thumbanil image tables.  No big deal because we will stop looking once we find the first thumbnail image in the most recent table.
class ThumbImages {


	###-- Constructor --###
	function ThumbImages(){

		// Nothing yet
	}


	// Just returns the most recent image thumbnail table.
	function GetActiveThumbnailImageTable(DbCmd $dbCmd){

		$ThumbImageTableArr = ThumbImages::GetThumbnailImageTables($dbCmd);
		return $ThumbImageTableArr[0];
	}



	// In case a project is deleted... this should be called PRIOR to removing the project itself
	// Will not remove a thumbnail image unless it is in the "active" thumbnail table
	function RemoveProjectThumbnail(DbCmd $dbCmd, $tableName, $tableID){

		if($tableName != "projectssession" && $tableName != "projectssaved")
			throw new Exception("Illegal table name in method call to RemoveProjectThumbnail");

		$dbCmd->Query("DELETE FROM " . ThumbImages::GetActiveThumbnailImageTable($dbCmd) . " WHERE RefTableName='$tableName' AND RefTableID=$tableID");
	}






	// Returns an array of all thumbnail image tables.
	// The most recent tables MUST ALWAYS be listed on top
	function GetThumbnailImageTables(DbCmd $dbCmd){

		$dbCmd->Query("SELECT TableNameWithSuffix FROM tablerotations WHERE RootTableName='thumbnailimages'");
		$activeThumbnailTable = $dbCmd->GetValue();

		$matches = array();
		preg_match_all("/thumbnailimages_(\d+)/", $activeThumbnailTable, $matches);

		$suffixForThumailImageTable = $matches[1][0];

		// This is kind of a sample of what will be returned.   We do not have a table called thumbnailimages_1
		// So we are going to start at whatever the active table name is... and then count back down to 2
			/*
			return array(
					"thumbnailimages_3",
					"thumbnailimages_2",
					"thumbnailimages"
				);
			*/

		$retArr = array();

		for($i=$suffixForThumailImageTable; $i>=2; $i--)
			$retArr[] = "thumbnailimages_" . $i;

		$retArr[] = "thumbnailimages";

		return $retArr;
	}




	// Returns the image data in binary format.
	// If a thumbnail does not exist... then we just return a blank string.
	function &GetProjectThumbnailImage(DbCmd $dbCmd, $tableName, $tableID){

		if($tableName != "projectssession" && $tableName != "projectssaved")
			throw new Exception("Illegal table name in method call to GetProjectThumbnailImage");

		$ThumbImageTableArr = ThumbImages::GetThumbnailImageTables($dbCmd);

		foreach($ThumbImageTableArr as $thisThumbTable){

			$dbCmd->Query("SELECT ThumbImage FROM $thisThumbTable WHERE RefTableName='$tableName' AND RefTableID=$tableID");
			if($dbCmd->GetNumRows() > 0){
				$thumbBinData = $dbCmd->GetValue();
				return $thumbBinData;
			}
		}
		$returnNothing = "";
		return $returnNothing;
	}

	// Returns a unix timestamp of the last time the thumbnail was updated for a project
	// If a thumbnail does not exist... then it returns the timestamp of an arbitrary date, which we randomly pick as 1/1/05 ... just to make sure the default thumbnail continues to be cached.
	function GetProjectThumbnailLastUpdated(DbCmd $dbCmd, $tableName, $tableID){

		if($tableName != "projectssession" && $tableName != "projectssaved")
			throw new Exception("Illegal table name in method call to GetProjectThumbnailLastUpdated");


		$ThumbImageTableArr = ThumbImages::GetThumbnailImageTables($dbCmd);

		foreach($ThumbImageTableArr as $thisThumbTable){

			$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateModified) FROM $thisThumbTable WHERE RefTableName='$tableName' AND RefTableID=$tableID");
			if($dbCmd->GetNumRows() > 0)
				return $dbCmd->GetValue();
		}

		return mktime(1, 1, 1, 1, 1, 2005);
	}

	// The project must exist before calling this method
	// A thumnail may not exist before calling this method.
	function MarkThumbnailForProjectAsCopy(DbCmd $dbCmd, $tableName, $tableID){

		if($tableName != "projectssession" && $tableName != "projectssaved")
			throw new Exception("Illegal table name in method call to GetProjectThumbnailLastUpdated");

		$dbCmd->Query("SELECT ProductID FROM $tableName WHERE ID = $tableID");
		if($dbCmd->GetNumRows() != 1)
			throw new Exception("No record exist within the method call MarkThumbnailForProjectAsCopy.  Make sure the project has been created before calling this method");
		$ProductID = $dbCmd->GetValue();

		$dbCmd->Query("SELECT COUNT(*) FROM " . ThumbImages::GetActiveThumbnailImageTable($dbCmd) . " WHERE RefTableName='$tableName' AND RefTableID=$tableID");
		if($dbCmd->GetValue() != 0)
			throw new Exception("A thumnail image already exists before calling the method MarkThumbnailForProjectAsCopy.");


		$productObj = new Product($dbCmd, $ProductID);
		
		$thumBinaryData = $productObj->getThumbnailCopyIconJPG();

		$InsertRow["ThumbImage"] = $thumBinaryData;
		$InsertRow["RefTableName"] = $tableName;
		$InsertRow["RefTableID"] = $tableID;
		$InsertRow["FileSize"] = strlen($thumBinaryData);
		$InsertRow["DateModified"] = date("YmdHis");
		$dbCmd->InsertQuery( ThumbImages::GetActiveThumbnailImageTable($dbCmd), $InsertRow);
	}








	// Will Create a new thumnail image based on the artwork file currently stored in the DB
	// It will figure out if it is a template, saved project etc and handle the situation
	// Currently we are not generating thumbnails for "order"... it will benign to call this function with a ProjectOrder ID
	function CreateThumnailImage(DbCmd $dbCmd, $ProjectRecordID, $view){

		// Thumbnail images are different for Templates verses Projects
		if($view == "template_category" || $view == "template_searchengine"){

			//This will create the thumbnail image as well as the preview images for the customer
			ThumbImages::CreateTemplatePreviewImages($dbCmd, $view, $ProjectRecordID);
		}
		else if($view == "projectssession" || $view == "saved" || $view == "session"){

			$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $view, $ProjectRecordID);

			$ProductID = $projectObj->getProductID();

			$ArtworkXMLstring = $projectObj->getArtworkFile();
			$ArtworkInfoObj = new ArtworkInformation($ArtworkXMLstring);



			// In case an artwork has a sweet spot (like in the case of double-sided envelopes... we need to check for a sweet spot
			// This is mainly used to crop out a smaller portion on irregular shaped artworks.
			$previewProductObj = Product::getProductObj($dbCmd, $ProductID, false);


			// Only do an Artwork conversion if there is a sweet spot defined for this product.
			if($previewProductObj->checkIfArtworkHasSweetSpot()){
			
				// Make a copy of the artwork and make the new size of the canvas the same size of the sweet spot.
				// Delete all layers... so we will be copying the SweetSpot from the source artwork onto a blank canvas at the size we want.
				$artworkInfoCopyTo = unserialize(serialize($ArtworkInfoObj));
				$artworkInfoCopyTo->SideItemsArray[0]->markerimage = null;
				$artworkInfoCopyTo->SideItemsArray[0]->contentwidth = round($previewProductObj->getArtworkSweetSpotWidth() * 96);
				$artworkInfoCopyTo->SideItemsArray[0]->contentheight = round($previewProductObj->getArtworkSweetSpotHeight() * 96);
				$artworkInfoCopyTo->SideItemsArray[0]->layers = array();
			
				$artworkConversionObj = new ArtworkConversion($dbCmd);
				$artworkConversionObj->setFromArtwork($ProductID, $ArtworkXMLstring);
				$artworkConversionObj->setToArtwork(0, $artworkInfoCopyTo->GetXMLdoc());
				$artworkConversionObj->removeBacksideFlag(true);
				$convertedArtworkFile = $artworkConversionObj->getConvertedArtwork();

				$ArtworkInfoObj = new ArtworkInformation($convertedArtworkFile);
			}



			// Get a JPEG image of the 1st side of the artwork... We are setting the Max Width/Height to 300... We will resize the thumbnail more precisily
			// ... in a the function ThumbImages::FormatProductThumbnail below.   No thumanail will ever need to be over 300 pixels wide... so we just set it to 300 to make subsequent function call deal with smaller image files.
			$tmpfname = ArtworkLib::GetArtworkImageWithText($dbCmd, 0, $ArtworkInfoObj, "V", 0, 0, false, false, array(), 300, 300);


			// Make sure that we have permission to modify it with system comands
			chmod ($tmpfname, 0666);


			// Format the thumnail
			// Pass in the file name of the Temp File.  This function will delete it when it is done.
			$ThumbImageData =& ThumbImages::FormatProductThumbnail($dbCmd, $ProductID, $tmpfname);


			// Get a table name for the database... based upon our "view" parameters
			if($view == "projectssession" || $view == "session")
				$TableName = "projectssession";
			else if($view == "saved")
				$TableName = "projectssaved";
			else
				throw new Exception("error in function call CreateThumnailImage");

			
			$activeThumbnailTable = ThumbImages::GetActiveThumbnailImageTable($dbCmd);

			// Find out if there is already a record in the "Active" Thumbnail Table... If not insert a new row.
			$dbCmd->Query("SELECT ID FROM " . $activeThumbnailTable . " WHERE RefTableName='$TableName' AND RefTableID=$ProjectRecordID");
			$ExistingThumbnailID = $dbCmd->GetValue();
			
			if(!$ExistingThumbnailID){
				$InsertRow["ThumbImage"] = $ThumbImageData;
				$InsertRow["RefTableName"] = $TableName;
				$InsertRow["RefTableID"] = $ProjectRecordID;
				$InsertRow["FileSize"] = strlen($ThumbImageData);
				$InsertRow["DateModified"] = date("YmdHis");
				$dbCmd->InsertQuery( $activeThumbnailTable, $InsertRow);
			}
			else{
				$dbCmd->UpdateQuery( $activeThumbnailTable, array("ThumbImage"=>$ThumbImageData, "DateModified"=>date("YmdHis")), "ID=$ExistingThumbnailID");
			}

		}
	}

	// Will copy a Thumbnail from one area into another...
	// For example... When we are loading a Saved Project into a user's session... There is no need to generate a new thumbnail, it can just be copied over.
	function CopyProjectThumbnail(DbCmd $dbCmd, $FromTableName, $FromTableID, $ToTableName, $ToTableID){

		$ThumbImageData =& ThumbImages::GetProjectThumbnailImage($dbCmd, $FromTableName, $FromTableID);
		
		$activeThumbnailTable = ThumbImages::GetActiveThumbnailImageTable($dbCmd);

		// Find out if there is already a record in the "Active" Thumbnail Table... If not insert a new row.
		$dbCmd->Query("SELECT ID FROM " . $activeThumbnailTable . " WHERE RefTableName='$ToTableName' AND RefTableID=$ToTableID");
		$ExistingThumbnailID = $dbCmd->GetValue();
		
		if(!$ExistingThumbnailID){
			$InsertRow["ThumbImage"] = $ThumbImageData;
			$InsertRow["RefTableName"] = $ToTableName;
			$InsertRow["RefTableID"] = $ToTableID;
			$InsertRow["FileSize"] = strlen($ThumbImageData);
			$InsertRow["DateModified"] = date("YmdHis");
			$dbCmd->InsertQuery( $activeThumbnailTable, $InsertRow);
		}
		else{
			$dbCmd->UpdateQuery( $activeThumbnailTable, array("ThumbImage"=>$ThumbImageData, "DateModified"=>date("YmdHis")), "ID=$ExistingThumbnailID");
		}

	}


	// Will open a JPEG file off of disc, resize it, and then return the image data.
	function &FormatProductThumbnail(DbCmd $dbCmd, $productID, $tmpfname){

		$productObj = new Product($dbCmd, $productID, false);
		
		$thumbWidthPixels = $productObj->getThumbWidth();
		$thumbHeightPixels = $productObj->getThumbHeight();
		
		$backgroundStartX = $productObj->getThumbOverlayX();
		$backgroundStartY = $productObj->getThumbOverlayY();
		
		// Just in case something weird happened with old products before we moved to keeping details in our product database... we don't want to resize to zero pixels.
		if(empty($thumbWidthPixels))
			$thumbWidthPixels = 100;
		if(empty($thumbHeightPixels))
			$thumbHeightPixels = 100;
		if(empty($backgroundStartX))
			$backgroundStartX = 0;
		if(empty($backgroundStartY))
			$backgroundStartY = 0;


		// Get Width and Height of Temp Image
		$imageDimHash = ImageLib::GetDimensionsFromImageFile($tmpfname);
		$ImageWidth = $imageDimHash["Width"];
		$ImageHeight = $imageDimHash["Height"];


		// shink to correct height and width
		$resizeCommand = Constants::GetUpperLimitsShellCommand(150, 25) . Constants::GetPathToImageMagick() . "mogrify -quality 90 -geometry " . $thumbWidthPixels . "x" . $thumbHeightPixels . "! " . $tmpfname;
		system($resizeCommand);


		// Find out if the aspect ratios are reversed.
		// This would mean that they have saved a Portrait Layout when the product was originally Horizontal, in which case we would rotate by 90 degrees to get it fitting on the thumbnail.
		$aspectRatioArtwork = 1 - $ImageWidth / $ImageHeight;
		$aspectRatioThumnail = 1 - $thumbWidthPixels / $thumbHeightPixels;
		
		if(($aspectRatioArtwork < 0 && $aspectRatioThumnail > 0) || ($aspectRatioArtwork > 0 && $aspectRatioThumnail < 0)){
			$rotateCommand = Constants::GetUpperLimitsShellCommand(150, 25) . Constants::GetPathToImageMagick() . "mogrify -quality 90 -rotate 90 " . $tmpfname;
			system($rotateCommand);
		}
		
		
		// If the Background Thumbnail has not been saved... then we will just return the Image by itself... otherwise merge the artwork on top of the thumbnail background.
		if($productObj->checkIfThumbnailBackSaved()){
		
			$backgroundThumbnailJPEG = $productObj->getThumbnailBackJPG();
			
			// Create a temporary file on disk and put the JPEG inside
			$backgroundTempImg = FileUtil::newtempnam(Constants::GetTempDirectory(), "ThbBack", ".jpg", time());
			$fp = fopen($backgroundTempImg, "w");
			fwrite($fp, $backgroundThumbnailJPEG);
			fclose($fp);
		
			// Place the generated image ontop of the background
			$mergeTogetherCommand = Constants::GetUpperLimitsShellCommand(150, 25) . Constants::GetPathToImageMagick() . "composite -quality 87 -geometry +" . $backgroundStartX . "+" . $backgroundStartY . " " . $tmpfname . " " . $backgroundTempImg . " " . $tmpfname;
			system($mergeTogetherCommand);
			
			@unlink($backgroundTempImg);
		}


		$fd = fopen ($tmpfname, "r");
		$ImageFileData = fread ($fd, 5000000);
		fclose ($fd);

		// Delete the temporary file
		unlink($tmpfname);

		return $ImageFileData;
	}


	// will create a new thumbnail image as well as a preview image for customers to browser through
	// will delete any old Preview images/thumbnails if they exist.   The file name of the images is based off of an auto-increment... generated from the database --#
	function CreateTemplatePreviewImages(DbCmd $dbCmd, $view, $TemplateID){

		$TemplateID = intval($TemplateID);

		if($view == "template_category"){
			$ArtworkPreviewColumnName = "TemplateID";

			// Get the name of the category that the template is in
			$dbCmd->Query("SELECT CategoryName FROM templatecategories INNER JOIN
					artworkstemplates ON artworkstemplates.CategoryID = templatecategories.CategoryID
					WHERE artworkstemplates.ArtworkID = $TemplateID" );
			$categoryName = $dbCmd->GetValue();
		}
		else if($view == "template_searchengine"){
			$ArtworkPreviewColumnName = "SearchEngineID";
			$categoryName = "";
		}
		else
			throw new Exception("Error With view type in function CreateTemplatePreviewImages");


		$TemplateProductID = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $TemplateID, $view);


		// Loop through all of the previews associated with this template
		// Put into a Temp array to avoid doing a nested query with a second DB object
		$oldPreviewIDarr = array();
		$dbCmd->Query("SELECT ID FROM artworkstemplatespreview WHERE $ArtworkPreviewColumnName=$TemplateID");
		while($oldPreviewID = $dbCmd->GetValue())
			$oldPreviewIDarr[] = $oldPreviewID;

		foreach($oldPreviewIDarr as $oldPreviewID){

			// We need to delete the old preview image from disk... because we will be making a new one.
			// Use the @ to prevent a warning if the file does not exist.
			$ImagePeviewFileName = ThumbImages::GetTemplatePreviewName($oldPreviewID, $view);
			@unlink(Constants::GetTempImageDirectory () . "/" . $ImagePeviewFileName);

			// We need to delete the old thumnail image from disk... because we will be making a new one.
			$ImagePeviewFileName = ThumbImages::GetTemplatePreviewFnameAdmin($dbCmd, $TemplateID, $view);
			@unlink(Constants::GetTempImageDirectory () . "/" . $ImagePeviewFileName);

			// Clean out any existing preview images associated with the template because we are going to put in the new ones.
			$dbCmd->Query("DELETE FROM artworkstemplatespreview WHERE ID=$oldPreviewID");
		}

		$adminThumbnailHasBeenGenerated = false;

		$ArtworkXMLstring = ArtworkLib::GetArtXMLfile($dbCmd, $view, $TemplateID);

		// A single Product may automatically create Previews for other Products
		$productObj = Product::getProductObj($dbCmd, $TemplateProductID);
		$multiplePreviewProductIDs = $productObj->getMultipleTemplatePreviewsArr();

		// If there are not multiple Preview Product ID's, then we will just create previews for this product only.
		if(empty($multiplePreviewProductIDs))
			$multiplePreviewProductIDs = array($productObj->getProductIDforTemplates());
		
		
		foreach($multiplePreviewProductIDs as $PreviewProductID){


			// In case an artwork has a sweet spot (like in the case of double-sided envelopes... we need to check for a sweet spot
			// This is mainly used to crop out a smaller portion on irregular shaped artworks.
			$previewProductObj = Product::getProductObj($dbCmd, $PreviewProductID);
			$templatePreviewScale = $previewProductObj->getTemplatePreviewScale($categoryName);


			// If this template is creating Templates Previews for another Product ID
			// Then we need to convert the Artwork for the specific product
			if($TemplateProductID != $PreviewProductID){

				$artworkConversionObj = new ArtworkConversion($dbCmd);
				$artworkConversionObj->setFromArtwork($TemplateProductID, $ArtworkXMLstring);
				$artworkConversionObj->setToArtwork($PreviewProductID);
				$artworkConversionObj->removeBacksideFlag(false);
				$convertedArtworkFile = $artworkConversionObj->getConvertedArtwork();

				$ArtworkInfoObj = new ArtworkInformation($convertedArtworkFile);
			}
			else{
				
				$ArtworkInfoObj = new ArtworkInformation($ArtworkXMLstring);
				
				// If this Product Definition is trying to restrict to an Artwork Sweet Spot, then converion the layers and define a new canvas area.
				if($previewProductObj->checkIfArtworkHasSweetSpot() && $previewProductObj->getTemplatePreviewSweetSpot()){
				
					// Make a copy of the artwork and make the new size of the canvas the same size of the sweet spot.
					// Delete all layers... so we will be copying the SweetSpot from the source artwork onto a blank canvas at the size we want.
					$artworkInfoCopyTo = unserialize(serialize($ArtworkInfoObj));
					$artworkInfoCopyTo->SideItemsArray[0]->contentwidth = round($previewProductObj->getArtworkSweetSpotWidth() * 96);
					$artworkInfoCopyTo->SideItemsArray[0]->contentheight = round($previewProductObj->getArtworkSweetSpotHeight() * 96);
					$artworkInfoCopyTo->SideItemsArray[0]->layers = array();
				
					$artworkConversionObj = new ArtworkConversion($dbCmd);
					$artworkConversionObj->setFromArtwork($TemplateProductID, $ArtworkXMLstring);
					$artworkConversionObj->setToArtwork(0, $artworkInfoCopyTo->GetXMLdoc());
					$artworkConversionObj->removeBacksideFlag(false);
					$convertedArtworkFile = $artworkConversionObj->getConvertedArtwork();
	
					$ArtworkInfoObj = new ArtworkInformation($convertedArtworkFile);
				}
				
				
			}

			$SideCountTracker = 0;
			foreach ($ArtworkInfoObj->SideItemsArray as $SideObj) {
				
				// Don't save preview images for the backsides if it is blank.
				if($SideCountTracker > 0 && sizeof($SideObj->layers) == 0)
					continue;

				// Never show bleed/safe lines on the template previews.
				$showBleedSafeLines = false;

				// Generate the JPEG Image.. The function will return a path to the temporary JPEG for us
				$tmpfname = ArtworkLib::GetArtworkImageWithText($dbCmd, $SideCountTracker, $ArtworkInfoObj, "V", 0, 0, $showBleedSafeLines, false, array());
				
				// If the canvas has been rotated then we need to rotate the preview image too
				if($SideObj->rotatecanvas <> 0){
					$rotateCommand = Constants::GetUpperLimitsShellCommand(150, 25) . Constants::GetPathToImageMagick() . "mogrify -quality 90 -rotate " . $SideObj->rotatecanvas . " " . $tmpfname;
					system($rotateCommand);
				}

				// Get Width and Height of Temp Image
				$imageDimHash = ImageLib::GetDimensionsFromImageFile($tmpfname);
				$ImageWidth_Actual = $imageDimHash["Width"];
				$ImageHeight_Actual = $imageDimHash["Height"];


				// see draw_"display_artwork_image.php" for a better explanation of what DPI ratio is used for.
				$dpi_Ratio = $SideObj->dpi / 96;
				$ImageWidth_Actual = $ImageWidth_Actual / $dpi_Ratio;
				$ImageHeight_Actual = $ImageHeight_Actual / $dpi_Ratio;

				// Calculate what the new dimensions will be according to the TemplateScaling factor the administrator has set.
				$newWidth = intval($templatePreviewScale * 0.01 *  $ImageWidth_Actual);
				$newHeight = intval($templatePreviewScale * 0.01 *  $ImageHeight_Actual);

				$resizeCommand = Constants::GetUpperLimitsShellCommand(150, 25) . Constants::GetPathToImageMagick() . "mogrify -quality 85 -geometry " . $newWidth . "x" . $newHeight . "! " . $tmpfname;
				system($resizeCommand);
				
				// If there is a template preview mask... then we want to merge that on top of the JPEG Preview
				if($previewProductObj->getTemplatePreviewMaskPNG()){
					$previewMaskOnDisk = $previewProductObj->getTemplatePreviewMaskPNG(true);

					// If the canvas has been rotated then we need to rotate the preview mask before laying it on top of the preview image.
					if($SideObj->rotatecanvas <> 0){
						$rotateCommand = Constants::GetUpperLimitsShellCommand(150, 25) . Constants::GetPathToImageMagick() . "mogrify -quality 90 -rotate " . $SideObj->rotatecanvas . " " . $previewMaskOnDisk;
						system($rotateCommand);
					}
					
					$xCoord = "+0";
					$yCoord = "+0";
					system(Constants::GetUpperLimitsShellCommand(150, 25) . Constants::GetPathToImageMagick() . "composite -quality 90 -geometry " . $xCoord . $yCoord . " " . $previewMaskOnDisk . " " . $tmpfname . " " . $tmpfname);
					unlink($previewMaskOnDisk);
				}
				
				
				$isLandscapeOrSquareFlag = ($newWidth >= $newHeight) ? true : false;
				$isPortraitFlag = ($newHeight > $newWidth) ? true : false;

				
				// If the Product Setup has a background image stored... then we need to merge our Thumbnail Preview on top of the background JPEG
				// We can use different template background images depending on whether the Image Preview (after canvas rotation) has a portrait or landscape orientation.
				// ---------------- Landcape Templates -------------------------
				if($previewProductObj->checkIfTempPrevBackLandscapeJPGSaved() && $isLandscapeOrSquareFlag){
					$tmpBackImage = FileUtil::newtempnam(Constants::GetTempDirectory(), "TEMPLATE_PREV_BACK", ".jpg", time());
					file_put_contents($tmpBackImage, $previewProductObj->getTempPrevBackLandscapeJPG());
					
					$xCoord = ($previewProductObj->getTempPrevBackLandscapeOverlayX() < 0 ? "-" : "+") . abs($previewProductObj->getTempPrevBackLandscapeOverlayX());
					$yCoord = ($previewProductObj->getTempPrevBackLandscapeOverlayY() < 0 ? "-" : "+") . abs($previewProductObj->getTempPrevBackLandscapeOverlayY());
					system(Constants::GetUpperLimitsShellCommand(70, 20) . Constants::GetPathToImageMagick() . "composite -quality 90 -geometry " . $xCoord . $yCoord . " " . $tmpfname . " " . $tmpBackImage . " " . $tmpfname);
					unlink($tmpBackImage);
				}
				// ---------------- Portrait Templates -------------------------
				if($previewProductObj->checkIfTempPrevBackPortraitJPGSaved() && $isPortraitFlag){
					$tmpBackImage = FileUtil::newtempnam(Constants::GetTempDirectory(), "TEMPLATE_PREV_BACK", ".jpg", time());
					file_put_contents($tmpBackImage, $previewProductObj->getTempPrevBackPortraitJPG());
					
					$xCoord = ($previewProductObj->getTempPrevBackPortraitOverlayX() < 0 ? "-" : "+") . abs($previewProductObj->getTempPrevBackPortraitOverlayX());
					$yCoord = ($previewProductObj->getTempPrevBackPortraitOverlayY() < 0 ? "-" : "+") . abs($previewProductObj->getTempPrevBackPortraitOverlayY());
					system(Constants::GetUpperLimitsShellCommand(70, 20) . Constants::GetPathToImageMagick() . "composite -quality 90 -geometry " . $xCoord . $yCoord . " " . $tmpfname . " " . $tmpBackImage . " " . $tmpfname);
					unlink($tmpBackImage);
				}
				
				
				// Automatically add "horizontal" and "vertical" keywords depending on the aspect ratio... based upon the front-side
				if($SideCountTracker == 0){
					if($isLandscapeOrSquareFlag){
						ArtworkTemplate::AddKeywordsToTemplate($dbCmd, "horizontal", $TemplateID);
						$dbCmd->Query("DELETE FROM templatekeywords WHERE TemplateID=".intval($TemplateID)." AND TempKw LIKE '" . DbCmd::EscapeLikeQuery("vertical")  . "'");
					}
					if($isPortraitFlag){
						ArtworkTemplate::AddKeywordsToTemplate($dbCmd, "vertical", $TemplateID);
						$dbCmd->Query("DELETE FROM templatekeywords WHERE TemplateID=".intval($TemplateID)." AND TempKw LIKE '" . DbCmd::EscapeLikeQuery("horizontal")  . "'");
					}
				}




				// Insert Template Preview Info into the DB
				$insertArr["SideName"] = $SideObj->description;
				$insertArr[$ArtworkPreviewColumnName] = $TemplateID;
				$insertArr["Width"] = $newWidth;
				$insertArr["Height"] = $newHeight;
				$insertArr["ProductID"] = $PreviewProductID;
				$NewPreviewID = $dbCmd->InsertQuery("artworkstemplatespreview", $insertArr);

				// Get the file off of the disk into binary memory.
				$ImageContentsResized = file_get_contents($tmpfname);

				// Put the preview for the customer on the disk --##
				$ImagePeviewFileName = ThumbImages::GetTemplatePreviewName($NewPreviewID, $view);
				$fp = fopen(Constants::GetTempImageDirectory () . "/" . $ImagePeviewFileName, "w");
				fwrite($fp, $ImageContentsResized);
				fclose($fp);

				// If This tempalte is creating multiple previews for other Product ID's... we don't want to create admin thumbnails for others.
				// Only create it for the default Product ID view.
				if($productObj->getdefaultProductIDforTemplatePreview() == $PreviewProductID){

					$adminThumbnailHasBeenGenerated = true;

					// Figure out how big to make the thumbnail photo for the administrator
					if($newWidth > $newHeight){
						$thumb_width = 90;
						$thumb_height = round(90 * $newHeight / $newWidth);
					}
					else{
						$thumb_height = 90;
						$thumb_width = round(90 * $newWidth / $newHeight);
					}

					// This resize command will create a thumnail for us.
					$resizeCommand = Constants::GetUpperLimitsShellCommand(150, 30) . Constants::GetPathToImageMagick() . "mogrify -quality 90 -geometry " . $thumb_width . "x" . $thumb_height . "! " . $tmpfname;
					system($resizeCommand);

					// Get the thumnail off of disk
					$fd = fopen ($tmpfname, "r");
					$ImageThumbnail = fread ($fd, 5000000);
					fclose ($fd);

					// Put the thumbnail on disk for the administrator... but only do it for the first side. --#
					if($SideCountTracker == 0){
						$ThumbFileName = ThumbImages::GetTemplatePreviewFnameAdmin($dbCmd, $TemplateID, $view);
						$fp = fopen(Constants::GetTempImageDirectory () . "/" . $ThumbFileName, "w");
						fwrite($fp, $ImageThumbnail);
						fclose($fp);
					}
				}

				unlink($tmpfname);


				$SideCountTracker++;
			}
		}

		if(!$adminThumbnailHasBeenGenerated)
			throw new Exception("No Product ID to use for the Admin Thumbanil was found.");
	}


	// contructs the file name used for template thmb image for the admin
	// The thumbanil name corresponds to the first Preview ID belonging to the template
	// Different filenames depending on whether it is a search category or a search engine
	function GetTemplatePreviewFnameAdmin(DbCmd $dbCmd, $TemplateID, $view){

		if($view == "template_category")
			$TemplateColumn="TemplateID";
		else if($view == "template_searchengine")
			$TemplateColumn="SearchEngineID";
		else
			throw new Exception("Error in function call GetTemplatePreviewFnameAdmin");



		// The The PreviewTemplate ID (for the first side only) belonging to this template
		$dbCmd->Query("SELECT ID FROM artworkstemplatespreview WHERE $TemplateColumn =$TemplateID ORDER BY ID ASC LIMIT 1");
		$PreviewID = $dbCmd->GetValue();

		if($view == "template_category")
			return "thmb_" . $PreviewID . ".jpg";
		else if($view == "template_searchengine")
			return "th_s_" . $PreviewID . ".jpg";
		else
			throw new Exception("Illegal view type in method GetTemplatePreviewFnameAdmin");
	}

	// contructs the file name used for template preview images (for the customer to see)
	// Different filenames depending on whether it is a search category or a search engine
	function GetTemplatePreviewName($PreviewID, $view){

		if($view == "template_category")
			return "preview_" . $PreviewID . ".jpg";
		else if($view == "template_searchengine")
			return "se_" . $PreviewID . ".jpg";
		else{
			print "Error in function call GetTemplatePreviewName";
			exit;
		}
	}



	// Sometimes changing the Product Options requires a new thumbnail to be generated. (wuch as a Window on Envelope, or "hole punches" on letterhead).
	// Pass in the Old ProjectInfo Object and a project info object that you want to change to.
	// This method will return true or false.
	function CheckIfProjectThumbnailNeedsUpdate(DbCmd $dbCmd, $oldProjectInfoObj, $newProjectInfoObj){


		// If the Product ID's are different, (such as from a Product conversion)... then the thumbnail image will have to be udpated.
		if($oldProjectInfoObj->getProductID() != $newProjectInfoObj->getProductID())
			return true;	
		
		$pdfProfileObjOld = new PDFprofile($dbCmd, $oldProjectInfoObj->getProductID(), $oldProjectInfoObj->getOptionsDescription());
		$pdfProfileObjNew = new PDFprofile($dbCmd, $newProjectInfoObj->getProductID(), $newProjectInfoObj->getOptionsDescription());
		
		
		// We generate our Thumbnail images with shapes that exist on the Proof profile.
		$pdfProfileObjOld->loadProfileByName("Proof");
		$pdfProfileObjNew->loadProfileByName("Proof");
		
		// Get the signatures of the Shapes containers beteen both of the PDF Objects.
		// If they are not equal, then it means we need a new thumbnail image generated.
		$oldShapesSignature = md5(serialize($pdfProfileObjOld->getLocalShapeContainer()) . serialize($pdfProfileObjOld->getGlobalShapeContainer()));
		$newShapesSignature = md5(serialize($pdfProfileObjNew->getLocalShapeContainer()) . serialize($pdfProfileObjNew->getGlobalShapeContainer()));

		if($oldShapesSignature  != $newShapesSignature)
			return true;
		else
			return false;
	}


	// Someone could overload our server really easily by hitting the refresh button many times while a thumbnail is being updated.
	// This is a Static function that will tell us if the thumbnail has already been updated for the Current Artwork.
	// Returns TRUE is you can generate a new Thumbnail Image, FALSE if there is no need to generate a new thumbnail for this project.
	function checkIfThumbnailCanBeUpdated(DbCmd $dbCmd, $view, $projectID){

		if(!preg_match("/^\d+$/", $projectID))
			throw new Exception("The Project ID is not a digit in method checkIfThumbnailCanBeUpdated.");

		if($view == "session")
			$tableName = "projectssession";
		else if($view == "saved")
			$tableName = "projectssaved";
		else
			throw new Exception("Illegal Viewtype in method checkIfThumbnailCanBeUpdated");


		$dbCmd->Query("SELECT ThumbnailCheck, UNIX_TIMESTAMP(DateLastModified) AS DateModified
					FROM " . $tableName . " WHERE ID=$projectID");

		if($dbCmd->GetNumRows() != 1)
			throw new Exception("The Project ID does not exist in Method checkIfThumbnailCanBeUpdated.");

		$row = $dbCmd->GetRow();

		// We have updated the thumbnail already if the ThumbnailCheck (a string)  matches the Unix TimeStamp of the date this Project was last saved.
		if($row["ThumbnailCheck"] == $row["DateModified"])
			return false;
		else
			return true;

	}

	// Call this Static method right before you start updating the Thumbanil for the project.
	// That way we will know not to generate the thumbnail more than once.
	function markThumbnailAsUpdating(DbCmd $dbCmd, $view, $projectID){

		if(!preg_match("/^\d+$/", $projectID))
			throw new Exception("The Project ID is not a digit in method markThumbnailAsUpdating.");

		if($view == "session")
			$tableName = "projectssession";
		else if($view == "saved")
			$tableName = "projectssaved";
		else
			throw new Exception("Illegal Viewtype in method checkIfThumbnailCanBeUpdated");

		$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateLastModified) AS DateModified
					FROM " . $tableName . " WHERE ID=$projectID");

		if($dbCmd->GetNumRows() != 1)
			throw new Exception("The Project ID does not exist in Method markThumbnailAsUpdating.");

		$dateModifiedTimeStamp = $dbCmd->GetValue();

		$dbCmd->UpdateQuery($tableName, array("ThumbnailCheck"=>$dateModifiedTimeStamp), ("ID=" . $projectID));

	}


}



?>
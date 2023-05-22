<?php

class ArtworkLib {
	
	// Load the Artwork XML document from database.
	// We are trying to cut down the View Types to just "session", "saved", "ordered", "template_category", and "template_searchengine"
	// There are a number of view types scattered arround the website though that are depcrediated.
	static function GetArtXMLfile(DbCmd $dbCmd, $view, $projectrecord, $authenticateDomainFlag = true){
	
		$projectrecord = intval($projectrecord);
	
		if($view == "proof" || $view == "admin" || $view == "projectsordered" || $view == "customerservice" || $view == "ordered")
			$query = "SELECT ArtworkFile, ArtworkFileModified, ProductID FROM projectsordered WHERE ID=$projectrecord";
		else if($view == "saved" || $view == "projectssaved")
			$query = "SELECT ArtworkFile, ProductID FROM projectssaved WHERE ID=$projectrecord";
		else if($view == "projectssession" || $view == "session")
			$query = "SELECT ArtworkFile, ProductID FROM projectssession WHERE ID=$projectrecord";
		else if($view == "template_category")
			$query = "SELECT ArtFile, ProductID FROM artworkstemplates where ArtworkID=$projectrecord";
		else if($view == "template_searchengine")
			$query = "SELECT ArtFile, ProductID FROM artworksearchengine where ID=$projectrecord";
		else
			throw new Exception("A valid View Type was not received properly for artfile.");
	
		$dbCmd->Query($query);
		
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("The artwork file was not found");
		
		$row = $dbCmd->GetRow();
	
		if($view == "proof" || $view == "admin" || $view == "projectsordered" || $view == "customerservice" || $view == "ordered"){
			$OrginalArtwork = $row["ArtworkFile"];
			$EditedArtwork = $row["ArtworkFileModified"];
		}
		else if($view == "template_category" || $view == "template_searchengine"){
			$OrginalArtwork = $row["ArtFile"];
			$EditedArtwork = "";
		}
		else{
			$OrginalArtwork = $row["ArtworkFile"];
			$EditedArtwork = "";
		}
		
		if($authenticateDomainFlag){
			// Ensure domain privelages... so that an administrator can't get the artwork out of a domain they don't belong to.
			$domainIDofProduct = Product::getDomainIDfromProductID($dbCmd, $row["ProductID"]);
			
			$passiveAuthObj = Authenticate::getPassiveAuthObject();
			if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofProduct))
				throw new Exception("Error in method GetArtXMLfile. The Project Record is not authorized for the users's domain.");
		}
	
		// If the $EditedArtwork is populated then it means we are viewing an artwork which has already been ordered
		// That holds the changes that the administrator made.  We should use that artwork file instead
		if(!empty($EditedArtwork))
			return $EditedArtwork;
		else
			return $OrginalArtwork;
	
	}
	
	
	static function SaveArtXMLfile(DbCmd $dbCmd, $view, $projectrecord, $ArtworkFile, $filterArtwork=true){
	
		$projectrecord = intval($projectrecord);
	
		if($filterArtwork)	
			$ArtworkFile = ArtworkInformation::FilterArtworkXMLfile($ArtworkFile);
	
		if($view == "proof" || $view == "admin" || $view == "projectsordered" || $view == "customerservice"){
			$ArtworkTableInfo["Table"] = "projectsordered";
			$ArtworkTableInfo["Column"] = "ArtworkFileModified";
			$ArtworkTableInfo["ColumnKey"] = "ID";
			
			//We need to keep the signature updated at all times
			ProjectOrdered::UpdateArtworkSignature($dbCmd, $projectrecord, $ArtworkFile);
		}
		else if($view == "saved"){
			$ArtworkTableInfo["Table"] = "projectssaved";
			$ArtworkTableInfo["Column"] = "ArtworkFile";
			$ArtworkTableInfo["ColumnKey"] = "ID";
		}
		else if($view == "projectssession" || $view == "session"){
			$ArtworkTableInfo["Table"] = "projectssession";
			$ArtworkTableInfo["Column"] = "ArtworkFile";
			$ArtworkTableInfo["ColumnKey"] = "ID";
		}
		else if($view == "template_category"){
			$ArtworkTableInfo["Table"] = "artworkstemplates";
			$ArtworkTableInfo["Column"] = "ArtFile";
			$ArtworkTableInfo["ColumnKey"] = "ArtworkID";
		}
		else if($view == "template_searchengine"){
			$ArtworkTableInfo["Table"] = "artworksearchengine";
			$ArtworkTableInfo["Column"] = "ArtFile";
			$ArtworkTableInfo["ColumnKey"] = "ID";
		}
		else{
			print "Error With View Table type in function call ArtworkLib::SaveArtXMLfile";
			exit;
		}
		
		
		// Ensure Domain Permissions on Product.
		$dbCmd->Query("SELECT ProductID FROM " . $ArtworkTableInfo["Table"] . " WHERE " . $ArtworkTableInfo["ColumnKey"] . " = $projectrecord");
		$productID = $dbCmd->GetValue();
		$domainIDofProduct = Product::getDomainIDfromProductID($dbCmd, $productID);
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofProduct))
			throw new Exception("Error in method SaveArtXMLfile. The Project Record does not exist.");
	
	
		$updateArr[$ArtworkTableInfo["Column"]] = $ArtworkFile;
		$qualify = $ArtworkTableInfo["ColumnKey"] . " =" . $projectrecord;
		$dbCmd->UpdateQuery($ArtworkTableInfo["Table"], $updateArr, $qualify);
		
	
		
		// Find out if we can extract any new email addresses from the session.
		if($view == "projectssession" || $view == "session" || $view == "saved"){
			ArtworkLib::discoverNewEmailAddressesInSession($ArtworkFile);
		}

		
		

		
		
	}
	
	static function discoverNewEmailAddressesInSession($ArtworkFile, $overrideTimeStamp = null){
		
		$dbCmd = new DbCmd();
		
		$mArr = array();
		if(preg_match_all("/(\w+([-.]\w+)*\@\w+([-.]\w+)*\.\w+)/", $ArtworkFile, $mArr)){
			$emailsExtractedArr = $mArr[1];
			
			foreach($emailsExtractedArr as $thisEmail){
				$thisEmail = strtolower($thisEmail);
				
				// Make sure that it doesn't already exist.
				$dbCmd->Query("SELECT COUNT(*) FROM emailsdiscoverysessions WHERE Email = '$thisEmail'");
				if($dbCmd->GetValue() != 0)
					continue;
				
				if(empty($overrideTimeStamp))
					$overrideTimeStamp = time();
					
				$insertEmail["Email"] = $thisEmail;
				$insertEmail["DiscoveryDate"] = $overrideTimeStamp;
				$insertEmail["DomainID"] = Domain::getDomainIDfromURL();
				$dbCmd->InsertQuery("emailsdiscoverysessions", $insertEmail);
				
			}
		}
	}
	
	
	
	// Sets the DPI for the Artwork.... for all sides
	static function ChangeArtworkDPI(DbCmd $dbCmd, $view, $projectrecord, $DPI){
		
		$ArtworkXMLstring = ArtworkLib::GetArtXMLfile($dbCmd, $view, $projectrecord);
		$ArtworkInfoObj = new ArtworkInformation($ArtworkXMLstring);	
	
		#-- Now loop through all sides and all layers within each side --#
		for($i=0; $i<sizeof($ArtworkInfoObj->SideItemsArray); $i++)
			$ArtworkInfoObj->SideItemsArray[$i]->dpi = $DPI;
		
		ArtworkLib::SaveArtXMLfile($dbCmd, $view, $projectrecord, $ArtworkInfoObj->GetXMLdoc());
	}
	
	

	// This function will generate a PDF document of the Artwork with the given parameters and then convert the PDF into a JPEG
	// This is the most accurate way to create a JPEG of the artwork
	// See the description of the function PdfDoc::GetArtworkPDF to see what the parameters are for.
	// The additional parameters to this function are the $maxWidth and $maxHeight (optional).  It will resize the image exactly to that height/width without exceeding either either one.... while maintaining the aspect ratio of the native image.
	// returns the temporary file name of the JPEG file on disk... so make sure to delete it when you are done.
	// $shapeObjectsArr is an array of Shape Objects that should be drawn on top of the artwork.  For examples a Window Envelope may have a Rectangle drawn where the Window is to be punched out.
	// This function implements compenent-level disk caching... So that if text changes on the Front of the Artwork (or something changes on the Back)... it won't keep it this method from caching the Background Image on the front.
	static function GetArtworkImageWithText(DbCmd $dbCmd, $SideNumber, &$ArtworkInfoObj, $bleedtype, $bleedunits, $rotatecanvas, $showBleedSafeLines, $ShowCutLine, $shapeObjectsArr, $maxWidth=0, $maxHeight=0, $imageQuality=0){
	
		$pdfArtworkDoc = PdfDoc::GetArtworkPDF($dbCmd, $SideNumber, $ArtworkInfoObj, $bleedtype, $bleedunits, $rotatecanvas, $showBleedSafeLines, $ShowCutLine, $shapeObjectsArr);
		
		$tmpfnamePDF = FileUtil::newtempnam(Constants::GetTempDirectory(), "PDFIMG", ".pdf", time());
		
		// Put the PDF doc into the temp file
		$fp = fopen($tmpfnamePDF, "w");
		fwrite($fp, $pdfArtworkDoc);
		fclose($fp);
		
		chmod ($tmpfnamePDF, 0666);
		
	
		// We want to get rid of the extension ".pdf" and create a file name that ends in .jpg as well
		$tmpfnameJPEG = substr($tmpfnamePDF, 0, -4) . ".jpg";
		
	
		if($maxWidth == 0 && $maxHeight == 0){
			$geometryCommand = "";
			
			// Ignore compiler warning (unused var) ????
			if(empty($geometryCommand))
				$geometryCommand = "";
		}
		else{
			if($maxWidth == 0 || $maxHeight == 0)
				throw new Exception("Error in function call ArtworkLib::GetArtworkImageWithText.  If you have the maxHeight or maxWidth set to 0... then both of them need to be 0.");
			
			$geometryCommand = " -geometry " . $maxWidth . "x" . $maxHeight;
		}
		
		if(!empty($imageQuality)){
			if($imageQuality < 1 || $imageQuality > 100)
				throw new Exception("exit error in function ArtworkLib::GetArtworkImageWithText. Image quality has to be between 1 and 100.");
				
			$qualityCommand = " -quality " . $imageQuality . " ";
		}
		else{
			$qualityCommand = "";
		}
	
	
		$dpi = $ArtworkInfoObj->SideItemsArray[$SideNumber]->dpi;
		
		$convertPDFcommand = Constants::GetUpperLimitsShellCommand(230, 30) . Constants::GetPathToImageMagick() . "convert -density " .  $dpi . " " .  $tmpfnamePDF . "[0] " . $geometryCommand . $qualityCommand . " -colorspace RGB " . $tmpfnameJPEG;
	
		$systemResult = WebUtil::mysystem($convertPDFcommand);
	
		if(!file_exists($tmpfnameJPEG)){
			WebUtil::WebmasterError($convertPDFcommand . "\n\n" . $systemResult);
			WebUtil::WebmasterError("Error converting PDF file in function ArtworkLib::GetArtworkImageWithText\n\n" . serialize($ArtworkInfoObj) . "\n\n\n" . $convertPDFcommand);
			WebUtil::PrintError("An unknown error occurred with an image conversion.  This is more than likely related to a Font which was not correctly embedded within your artwork. If you uploaded a PDF file, try converting all 'Fonts' to 'Outlines'.  You may also consider uploading an EPS file for a different approach.  Sometimes PDF files have fonts specified inside with empty/invisible text.  Contact Customer Service if you continue to have problems.");
		}
		
		unlink($tmpfnamePDF);
		
		return $tmpfnameJPEG;
	}
		

	// Define this function with a return by reference
	// This will get the Art file out of the database and build a JPEG file
	// Pass in the last parameter as false if you don't want to check if the person is logged in
	// If you set the allowMarkerImageFlag to TRUE and the artwork has a marker image (for irregular shaped canvases)... then it will merge the Marker Image on top (usually for showing the bleed/safe lines)
	// IncludeVector images should be set or True to False... ... You may not want the background image to include PDF files if the PDFlib software is going to lay the PDF file on top itself. 
	static function &GetArtworkImage(DbCmd $dbCmd, $ArtworkInfoImgObj, $sidenumber, $allowMarkerImageFlag, $BleedPixels, $includeVectorImages){
	
		// In PHP 5 objects are passed by reference, so make sure ot make a copy of this object.
		// When we call RemoveLayerTypeFromSide we will end up affecting the $ArtworkInfoImgObj that calls this method.
		$ArtworkInfoImgObj = unserialize(serialize($ArtworkInfoImgObj));

		if(!isset($ArtworkInfoImgObj->SideItemsArray[$sidenumber]))
			throw new Exception("There is a problem with the artwork file.  One of the sides appears to be missing: " . $sidenumber);
	
	
		$dpi_Ratio = $ArtworkInfoImgObj->SideItemsArray[$sidenumber]->dpi / 96;


		// Get rid of all Text layers
		$ArtworkInfoImgObj->RemoveLayerTypeFromSide($sidenumber, "text");
	
		$ArtworkInfoImgObj->orderLayersByLayerLevel($sidenumber);
		$LayersSorted = $ArtworkInfoImgObj->SideItemsArray[$sidenumber]->layers;
		
		$ContentWidthXMLcoords = $ArtworkInfoImgObj->SideItemsArray[$sidenumber]->contentwidth;
		$ContentHeightXMLcoords = $ArtworkInfoImgObj->SideItemsArray[$sidenumber]->contentheight;
	
		// Adjust our image size based on the DPI... and bleed pixels 
		$ContentWidthPixels = $ContentWidthXMLcoords * $dpi_Ratio;
		$ContentHeightPixels = $ContentHeightXMLcoords * $dpi_Ratio;
	
		// Our Image has may go past the boundaries of the artwork if we want bleed pixels 
		$ContentWidthPixels += $BleedPixels * 2;
		$ContentHeightPixels += $BleedPixels * 2;
	
	
	
		// Let's get the Artwork Object's Side 
		// Then we will run that through the MD5 hash and see if we have already generated the background.
		// If any layers gets added, moved, resized... it will change the value of the MD5 hash and the temporary file will not exist on Disk
		$sideObjForCaching = $ArtworkInfoImgObj->SideItemsArray[$sidenumber];
		
		
		$parametersStr = $BleedPixels . ($includeVectorImages ? "WithVectImages" : "NoVect") . ($allowMarkerImageFlag ? "allowMarker" : "NoMarker");
		
		$allLayersSignature = md5(serialize($sideObjForCaching) . $parametersStr);
		
		
		// To Keep the Inodes from filling up with files too quickly, we want to organize as many subdirectories as possible.
		$imageCacheSubDir = Constants::GetImageCacheDirectory() . "/JPEG_Backgrounds"; 
		if(!file_exists($imageCacheSubDir)){
			mkdir($imageCacheSubDir);
			chmod($imageCacheSubDir, 0777);
		}
		
		$imageCacheSubDir = Constants::GetImageCacheDirectory() . "/JPEG_Backgrounds/Side" . $sidenumber . "_Bleed" . round($BleedPixels); 
		if(!file_exists($imageCacheSubDir)){
			mkdir($imageCacheSubDir);
			chmod($imageCacheSubDir, 0777);
		}
		
		
		$imageLayersFileName = $imageCacheSubDir . "/" . $allLayersSignature;
		
		
	
		// If we have already generated artwork for this particular scenario... then just open the file from Disk and return it.
		if(file_exists($imageLayersFileName) && filesize($imageLayersFileName) > 0){
	
			$fd = fopen ($imageLayersFileName, "r");
			$ImageBinaryData = fread ($fd, filesize ($imageLayersFileName));
			fclose ($fd);
	
			return $ImageBinaryData;
		}
	
	
		// It will return a temporary file name for a JPEG file used as the background.  All other images get pasted on top of the background.
		$BackgroundImageFileName = ImageLib::CreateWhiteJPEGimage(round($ContentWidthPixels), round($ContentHeightPixels), $imageLayersFileName);
	
		chmod($BackgroundImageFileName, 0777);
		
	
		// Paste all of the Layer images on top of our background 
		for($j=0; $j<sizeof($LayersSorted); $j++){
	
			$LayerObj = $LayersSorted[$j];
			
			if($LayerObj->LayerType == "graphic"){
			
				if(!$includeVectorImages && !empty($LayerObj->LayerDetailsObj->vector_image_id))
					continue;
	
	
				$NewCoordsHash = ImageLib::TranslateCoordinates($ContentWidthXMLcoords, $ContentHeightXMLcoords, $LayerObj->x_coordinate, $LayerObj->y_coordinate, $dpi_Ratio, $BleedPixels);
				$X_coordinate = $NewCoordsHash["Xcoord"];
				$Y_coordinate = $NewCoordsHash["Ycoord"];
	
	
				$LayerWidth = round($LayerObj->LayerDetailsObj->width * $dpi_Ratio);
				$LayerHeight = round($LayerObj->LayerDetailsObj->height * $dpi_Ratio);
	
	
				// This function call will load the Image from the Database and return a temporary file name.
				$SingleImageFile = ImageLib::LoadImageByID($dbCmd, $LayerObj->LayerDetailsObj->imageid, $LayerWidth, $LayerHeight, $LayerObj->rotation);
	
	
				// The layer width and height may have changed if the graphic was rotated
				$DimHash = ImageLib::GetDimensionsFromImageFile($SingleImageFile);
				$LayerWidth = $DimHash["Width"];
				$LayerHeight = $DimHash["Height"];
	
				// Another translatation of coodinates.
				// The registration point of the graphics are exactly in the center of the layer. We want it to start from the upper-left hand edge of the graphic for use with the GD library.
				$X_coordinate -= $LayerWidth/2;
				$Y_coordinate -= $LayerHeight/2;
	
				// For the Image Magick Command line... it needs a plus or minus
				$Xsign = ($X_coordinate >= 0) ? "+" : "-";
				$Ysign = ($Y_coordinate >= 0) ? "+" : "-";
	
				// Paste the image on top
				$LayerImageCmd = Constants::GetUpperLimitsShellCommand(150, 30) . Constants::GetPathToImageMagick() . "composite -quality 100 -geometry $Xsign".abs(round($X_coordinate))."$Ysign".abs(round($Y_coordinate))." " . $SingleImageFile . " $BackgroundImageFileName $BackgroundImageFileName";
				system($LayerImageCmd);
	
	
			}
	
		}
		
		// If the Artwork has an image mask we need to lay it on top of the artwork
		// Mask Images usually accompany Marker Images
		// The Mask image is usually a white rectangle with the irregular shape cut out in the center (transparent)
		// This will lay on top of everything (except the Marker Image).  It is a way to crop off everything around the irregular shape boundary.
		// Mask images are not seen in the editing tool, but are used when generating a PDF document, etc.
		if($ArtworkInfoImgObj->SideItemsArray[$sidenumber]->maskimage){
			
			$MaskWidthPixels =  $ArtworkInfoImgObj->SideItemsArray[$sidenumber]->maskimage->width * $dpi_Ratio;
			$MaskHeightPixels = $ArtworkInfoImgObj->SideItemsArray[$sidenumber]->maskimage->height * $dpi_Ratio;
			
			$MaskImageFileTempName = ImageLib::LoadImageByID($dbCmd, $ArtworkInfoImgObj->SideItemsArray[$sidenumber]->maskimage->imageid, $MaskWidthPixels, $MaskHeightPixels, 0);
	
			// Get the X/Y coordinates in pixels for the placement of the Mask image.  
			// We do not need to translate our registration point from the center of the graphic (like graphic layers)
			// --- that really was an design error... but the Marker Images, Mask Images, and Background Images all have a registration point at the top-left instead of the very center.  It should be consistent with graphic layers one way or the other.
			$MaskCoordsHash = ImageLib::TranslateCoordinates($ContentWidthXMLcoords, $ContentHeightXMLcoords, $ArtworkInfoImgObj->SideItemsArray[$sidenumber]->maskimage->x_coordinate, $ArtworkInfoImgObj->SideItemsArray[$sidenumber]->maskimage->y_coordinate, $dpi_Ratio, $BleedPixels);
			$Mask_Coord_X = $MaskCoordsHash["Xcoord"];
			$Mask_Coord_Y = $MaskCoordsHash["Ycoord"];
	
			// For the Image Magick Command line... it needs a plus or minus
			$Xsign = ($Mask_Coord_X >= 0) ? "+" : "-";
			$Ysign = ($Mask_Coord_Y >= 0) ? "+" : "-";
	
			// Paste the Mask on top
			$MaskImageCmd = Constants::GetUpperLimitsShellCommand(150, 30) . Constants::GetPathToImageMagick() . "composite -quality 100 -geometry $Xsign".abs(round($Mask_Coord_X))."$Ysign".abs(round($Mask_Coord_Y))." " . $MaskImageFileTempName . " $BackgroundImageFileName $BackgroundImageFileName";
			system($MaskImageCmd);
		}
		
	
		// Marker Images are usually used in place of Bleed/Safe lines
		// This Basically works the same as Mask Images (in code above)
		if($allowMarkerImageFlag && $ArtworkInfoImgObj->SideItemsArray[$sidenumber]->markerimage){
			
			$MarkerWidthPixels =  $ArtworkInfoImgObj->SideItemsArray[$sidenumber]->markerimage->width * $dpi_Ratio;
			$MarkerHeightPixels = $ArtworkInfoImgObj->SideItemsArray[$sidenumber]->markerimage->height * $dpi_Ratio;
			
			$MarkerImageFileTempName = null;
			
			// We may be using the Path to an Image on disk... or an ImageID
			if($ArtworkInfoImgObj->SideItemsArray[$sidenumber]->markerimage->imagepath){
				
				
				$fullPathMarkerImage = Constants::GetWebserverBase() .  $ArtworkInfoImgObj->SideItemsArray[$sidenumber]->markerimage->imagepath;
				if(!file_exists($fullPathMarkerImage)){
					if(!file_exists($ArtworkInfoImgObj->SideItemsArray[$sidenumber]->markerimage->imagepath))
						throw new Exception("The Marker Image does not exist on Disk: " . $ArtworkInfoImgObj->SideItemsArray[$sidenumber]->markerimage->imagepath);
					else
						$fullPathMarkerImage = $ArtworkInfoImgObj->SideItemsArray[$sidenumber]->markerimage->imagepath;
				}
				

			
				// We may need to Resize the Marker Image on disk... if we do this we need to make a copy first so we don't destroy the original.
				$DimHash = ImageLib::GetDimensionsFromImageFile($fullPathMarkerImage);
	
				if($DimHash["Width"] != $MarkerWidthPixels || $DimHash["Height"] != $MarkerHeightPixels){
	
					// Create a new temporary file Name
					$MarkerImageFileTempName = tempnam (Constants::GetTempDirectory(), "MARK");
	
					// Open the Image Data 
					$fd = fopen ($fullPathMarkerImage, "r");
					$BinaryData = fread ($fd, filesize ($fullPathMarkerImage));
					fclose ($fd);
	
					// Make the Copy 
					$fp = fopen($MarkerImageFileTempName, "w");
					fwrite($fp, $BinaryData);
					fclose($fp);
					
					// Resize to proper dimensions.
					$resizeCommand = Constants::GetUpperLimitsShellCommand(150, 30) . Constants::GetPathToImageMagick() . "mogrify -geometry " . $MarkerWidthPixels . "x" . $MarkerHeightPixels . "! " . $MarkerImageFileTempName;
					system($resizeCommand);
					
					$fullPathMarkerImage = $MarkerImageFileTempName;
				}
			}
			else if($ArtworkInfoImgObj->SideItemsArray[$sidenumber]->markerimage){
				$MarkerImageFileTempName = ImageLib::LoadImageByID($dbCmd, $ArtworkInfoImgObj->SideItemsArray[$sidenumber]->markerimage->imageid, $MarkerWidthPixels, $MarkerHeightPixels, 0);
				$fullPathMarkerImage = $MarkerImageFileTempName;
			}
			else{
				throw new Exception("A marker Image must contain a Valid Image ID or a Math to an Image on disk.");
			}
			
			$MarkerCoordsHash = ImageLib::TranslateCoordinates($ContentWidthXMLcoords, $ContentHeightXMLcoords, $ArtworkInfoImgObj->SideItemsArray[$sidenumber]->markerimage->x_coordinate, $ArtworkInfoImgObj->SideItemsArray[$sidenumber]->markerimage->y_coordinate, $dpi_Ratio, $BleedPixels);
			$Marker_Coord_X = $MarkerCoordsHash["Xcoord"];
			$Marker_Coord_Y = $MarkerCoordsHash["Ycoord"];
	
			$Xsign = ($Marker_Coord_X >= 0) ? "+" : "-";
			$Ysign = ($Marker_Coord_Y >= 0) ? "+" : "-";
	
			$MarkerImageCmd = Constants::GetUpperLimitsShellCommand(150, 30) . Constants::GetPathToImageMagick() . "composite -quality 100 -geometry $Xsign".abs(round($Marker_Coord_X))."$Ysign".abs(round($Marker_Coord_Y))." " . $fullPathMarkerImage . " $BackgroundImageFileName $BackgroundImageFileName";
			system($MarkerImageCmd);
			
		}
	
	
	
	
		$fd = fopen ($BackgroundImageFileName, "r");
		$ImageBinaryData = fread ($fd, filesize ($BackgroundImageFileName));
		fclose ($fd);
	
	
		$availableDiskSpace = disk_free_space(Constants::GetImageCacheDirectory());
		if(!Constants::GetDevelopmentServer() && $availableDiskSpace < 1000000000){
			WebUtil::WebmasterError("Error in function GetArtworkImage. Less than 1 gig of space is left on image caching disk. Image will therefore not be cached.");
			@unlink($BackgroundImageFileName);
		}
	
	
		return $ImageBinaryData;
	}

	
	
	// Looks for any session images within the Artwork XML file and then Save them
	// Tell what area to search for the artwork file in... it will make sure all of the "projectssession" images go to the "saved" table 
	// with moveToTableName, tell what table the image shoudl be moved into... if any images are found in the session
	static function SaveImagesInSession(DbCmd $dbCmd, $view, $projectrecord, $moveToRasterTableName, $moveToVectorTableName){
	
		WebUtil::EnsureDigit($projectrecord);
	
		$ArtworkXMLstring = ArtworkLib::GetArtXMLfile($dbCmd, $view, $projectrecord);
		$ArtworkInfoObj = new ArtworkInformation($ArtworkXMLstring);
	
		// Now loop through all sides and all layers within each side
		// We are going to extract all of the image ID's
		// If an image ID is saved in the session then we are going to move it to the "saved" table
		foreach($ArtworkInfoObj->SideItemsArray as $SideObj){
	
			foreach($SideObj->layers as $LayerObj){
	
				if($LayerObj->LayerType == "graphic"){
	
					$dbCmd->Query("SELECT TableName, Record FROM imagepointer WHERE ID=" . $LayerObj->LayerDetailsObj->imageid);
					$rowPointer = $dbCmd->GetRow();
					$ImageTableName = $rowPointer["TableName"];
					$ImageSessionRecordID = $rowPointer["Record"];
	
					if($ImageTableName == "imagessession"){
					
						$dbCmd->Query("SELECT BinaryData, FileSize, FileType, DateUploaded 
								FROM imagessession WHERE ID = $ImageSessionRecordID");
						$imageSessionRow = $dbCmd->GetRow();
						
						$newImageRecordID = $dbCmd->InsertQuery($moveToRasterTableName, $imageSessionRow);
						
						$dbCmd->Query("UPDATE imagepointer set Record=$newImageRecordID, TableName=\"$moveToRasterTableName\" 
									WHERE ID=" . $LayerObj->LayerDetailsObj->imageid);
						
						$dbCmd->Query("DELETE FROM imagessession WHERE ID=$ImageSessionRecordID");
					}
				}
				
				
				// There could possibly by a Vector Image ID also.
				if(!empty($LayerObj->LayerDetailsObj->vector_image_id)){
				
					$dbCmd->Query("SELECT TableName, Record FROM vectorimagepointer WHERE ID=" . $LayerObj->LayerDetailsObj->vector_image_id);
					$rowPointer = $dbCmd->GetRow();
					$VectorImageTableName = $rowPointer["TableName"];
					$VectorImageSessionRecordID = $rowPointer["Record"];
	
					if($VectorImageTableName == "vectorimagessession"){
					
						// Get the Session Vector Image Data
						$dbCmd->Query("SELECT PDFbinaryData, DateUploaded, EmbeddedText, OrigFileSize, OrigFileType, OrigFileName, PDFfileSize, PicasWidth, PicasHeight
								FROM vectorimagessession WHERE ID = $VectorImageSessionRecordID");
						$vectorImageSessionRow = $dbCmd->GetRow();
						
						// Insert the Session Vector Image into the new table.
						$newVectorImageRecordID = $dbCmd->InsertQuery($moveToVectorTableName, $vectorImageSessionRow);
						unset($vectorImageSessionRow); // The hash may have a few megs of memory... and we don't need it any longer.
						
						
						// There are 2 big blob fields in this table... don't move them over (in one query) or it can exceed MySQL's packet sizes.
						$dbCmd->Query("SELECT OrigFormatBinaryData FROM vectorimagessession WHERE ID = $VectorImageSessionRecordID");
						$origBinaryData = $dbCmd->GetValue();
						$dbCmd->UpdateQuery($moveToVectorTableName, array("OrigFormatBinaryData" => $origBinaryData), ("ID=" . $newVectorImageRecordID));
						
						
						// Update the Pointer Table
						$dbCmd->UpdateQuery("vectorimagepointer", array("Record" => $newVectorImageRecordID, "TableName" => $moveToVectorTableName), ("ID=" . $LayerObj->LayerDetailsObj->vector_image_id));
						
						
						// Delete the Session Row now that it has been moved.
						$dbCmd->Query("DELETE FROM vectorimagessession WHERE ID=$VectorImageSessionRecordID");
					}
				
				}
				
			}
		}
	}
	
	// Pass in an artwork file... this function will make sure that all SWF files needed for the images will be written to disk so the editing tool can access them.
	static function WriteSWFimagesToDisk(DbCmd $dbCmd, &$ArtworkFile){
		
		$ImageIDarr = ArtworkLib::GetImageIDsFromArtwork($ArtworkFile);
		
		foreach($ImageIDarr as $ThisImageID)
			ImageLib::WriteMINGswfToDisk($dbCmd, $ThisImageID);
	
	}

	// Returns an Array of Image ID's inside of an artwork file 
	static function GetImageIDsFromArtwork(&$ArtworkFile){
			
	
		$reg1 = "/<imageid>(\d+)<\/imageid>/i";
		$reg2 = "/<backgroundimage>(\d+)<\/backgroundimage>/i";
		
	
		$retArray = array();
		$m = array();
		
		if(preg_match_all($reg1, $ArtworkFile, $m))
			$retArray = array_merge($retArray, $m[1]);
			
		if(preg_match_all($reg2, $ArtworkFile, $m)){
		
			//Filter out any '0's in the artwork.  If we don't have a background... then the flash program puts a 0
			$BackgroundImagesArr = $m[1];
			
			foreach($BackgroundImagesArr as $ThisKey => $ThisID){
				if($ThisID == "0")
					unset($BackgroundImagesArr[$ThisKey]);
			}
			
			$retArray = array_merge($retArray, $BackgroundImagesArr);
			
		}
		
		
		
		return array_unique($retArray);
	}
		
}

?>

<?

require_once("library/Boot_Session.php");


$ProductID = WebUtil::GetInput( "productid", FILTER_SANITIZE_INT);
$returnUrl = WebUtil::GetInput( "returnUrl", FILTER_SANITIZE_URL);


// Allow 7 mintues for uploading.
set_time_limit(420);
ini_set("memory_limit", "512M");


$dbCmd = new DbCmd();

$user_sessionID =  WebUtil::GetSessionID();

//WebUtil::BreakOutOfSecureMode();

if(empty($returnUrl)){
	$current_URL = "product_uploadartwork.php?productid=" . $ProductID;
}
else{
	$current_URL = $returnUrl;
}

$current_URL_encoded = urlencode($current_URL);
	

try {
	$productObj = Product::getProductObj($dbCmd, $ProductID, true);
}
catch (Exception $e){
	WebUtil::PrintError("The Product ID is not available.");
}

if(!Product::checkIfProductIDisActive($dbCmd, $ProductID)){
	WebUtil::PrintError("This product has been discontinued.");
}
	
	
$ArtworkXMLstring = $productObj->getDefaultProductArtwork();
$ArtworkInfoObj = new ArtworkInformation($ArtworkXMLstring);


// Sometimes the Artwork File would be totally empty
// It was causing some serious server issues because it was trying to resize images to negative dimensions, etc.
// Make sure the content Width/Height are numbers greater than 0... indications that the artwork file is not corrupt
if(!isset($ArtworkInfoObj->SideItemsArray[0]) || !preg_match("/^\d+(\.\d+)?$/", $ArtworkInfoObj->SideItemsArray[0]->contentwidth) ||!preg_match("/^\d+(\.\d+)?$/", $ArtworkInfoObj->SideItemsArray[0]->contentheight))
	throw new Exception("A Product Artwork Error occurred. The Width/Height is invalid. $ProductID");

// Figure out the artwork dimensions
$ArtworkDPI = $productObj->getArtworkDPI();
$PixelsWide = round($ArtworkInfoObj->SideItemsArray[0]->contentwidth / 96 * $ArtworkDPI);
$PixelsHigh = round($ArtworkInfoObj->SideItemsArray[0]->contentheight / 96 * $ArtworkDPI);

if($PixelsWide <= 1 || $PixelsHigh <= 1)
	throw new Exception("A Product Artwork Error occurred. The pixel size must be greater than 1 $ProductID");

// Bleed Pixels depend upon the DPI.
$BleedPixels = round($productObj->getArtworkBleedPicas() / 72 * $ArtworkDPI);

$TotalWidthPixels = $PixelsWide + $BleedPixels*2;
$TotalHeightPixels = $PixelsHigh + $BleedPixels*2;

// Total number of pixels the artwork is allowed to be mismatched by before we show a warning message.
// It should be 1% of the distance.
$MisMatchVarianceWidth = ceil($TotalWidthPixels * 0.01);
$MisMatchVarianceHeight = ceil($TotalHeightPixels * 0.01);




// Destroy temporary session variables that were used for switching sides since we have a new template
$HTTP_SESSION_VARS['SwitchSides'] = "";
$HTTP_SESSION_VARS['TempXMLholder'] = "";
$HTTP_SESSION_VARS['SideNumber'] = 0;

$Action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if(!empty($Action)){
	if($Action == "upload"){
		
		// Should they go to the editor, or directly to the shopping cart after uploading.
		$destination = WebUtil::GetInput("destination", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
		
		if($destination != "direct" && $destination != "editor" && $destination != "cart")
			$destination = "direct";

		
		// In case there is an error or something, we want it to remember their choice where they want to go after uploading
		$HTTP_SESSION_VARS['AfterUploadDestination'] = $destination;
		
		if(!isset($_FILES["jpegfront"]))
			throw new Exception("File Fields seem to be missing.");
		
		
		// The jpegback image is not available on all products.  If jpegback comes in the URL then do some extra error checking.
		if(isset($_FILES["jpegback"])){

			if($_FILES["jpegfront"]["size"] == 0 && $_FILES["jpegback"]["size"] == 0)
				WebUtil::PrintError("You forgot to choose a file for uploading.");

			if($_FILES["jpegfront"]["size"] == 0 && $_FILES["jpegback"]["size"] != 0)
				WebUtil::PrintError("You are trying to upload a back side without a front.  You must always upload a front side.");
			
			if($_FILES["jpegback"]["size"] > 15728640)
				WebUtil::PrintError("The maximum file size you are permitted to upload is 15 Megabytes. You attempted to upload an image that was " . round($_FILES["imagefile"]["size"]/1024/1024,2) . " MB for the back-side. Generally it is not necessary to upload an image this large.  Ensure that your DPI setting is not too high.  If necessary, break your image into pieces.");

		}
		else{
			if($_FILES["jpegfront"]["size"] == 0)
				WebUtil::PrintError("You forgot to choose a file for uploading." );
				
			if($_FILES["jpegfront"]["size"] > 15728640)
				WebUtil::PrintError("The maximum file size you are permitted to upload is 15 Megabytes. You attempted to upload an image that was " . round($_FILES["imagefile"]["size"]/1024/1024,2) . " MB for the front-side. Generally it is not necessary to upload an image this large.  Ensure that your DPI setting is not too high.  If necessary, break your image into pieces.");
				
		}
		


		// Read the binary data from the temporary file .
		$frontSideBinaryData = fread(fopen($_FILES["jpegfront"]["tmp_name"], "r"), filesize($_FILES["jpegfront"]["tmp_name"]));

		
		// This will take the file data and insert the Image into the DB.  
		// If the image is a PDF or EPS file, it will insert the Vector Image into the DB as well as a rasterized version.
		// The function will return a hash with 2 possible Image ID's.. and a possible error message in case the conversion/insertion did not succeed.
		$frontImageInsertHash = ImageLib::InsertRasterAndVectorImageIntoDB($dbCmd, "projectssession", $frontSideBinaryData, $_FILES["jpegfront"]["name"], $ArtworkDPI, "");

		unset($frontSideBinaryData);

		if(!empty($frontImageInsertHash["ErrorMsg"]))
			WebUtil::PrintError($frontImageInsertHash["ErrorMsg"]);





		// Flags to tell us if the user tried to upload artwork for either the front or the back that does not match our Product Requirements.
		$frontDimensionsMismatchFlag = false;
		$backDimensionsMismatchFlag = false;
		
		$fontSideRotate90Flag = false;
		$backSideRotate90Flag = false;

		
		// Make sure the height x width of the customers artwork is exact.
		// We are more relaxed if they are going to the editor... since they can see the bleed/safe lines... so we don't need to enforce dimentions 
		$ImageDimArr_front = ImageLib::GetDimensionsFromRasterImageID($dbCmd, $frontImageInsertHash["RasterImageID"]);

		// If the user has uploaded a Vector Image... then get the Pixel Dimensions from the Picas Value stored in the DB.
		if(!empty($frontImageInsertHash["VectorImageID"]))
			$ImageDimArr_front = ImageLib::GetPixelDimensionsFromVectorImageID($frontImageInsertHash["VectorImageID"], $ArtworkDPI);
		
		// Anything more than 1% off is considered an artwork mismatch
		if(abs($TotalWidthPixels - $ImageDimArr_front["Width"]) > $MisMatchVarianceWidth || abs($TotalHeightPixels - $ImageDimArr_front["Height"]) > $MisMatchVarianceHeight){
			
			if(abs($TotalWidthPixels - $ImageDimArr_front["Height"]) > $MisMatchVarianceWidth || abs($TotalHeightPixels - $ImageDimArr_front["Width"]) > $MisMatchVarianceHeight){
				$frontDimensionsMismatchFlag = true;
			}
			else{
				$fontSideRotate90Flag = true;
			}
		}


		
		unlink($_FILES["jpegfront"]["tmp_name"]);


		$BackRasterImageID = "";
		$BackVectorImageID = "";
		$backImageInsertHash = array();
		
		// If a backside was uploaded, then add that to the artwork file too.
		if(isset($_FILES["jpegback"]) && $_FILES["jpegback"]["size"] != 0){


			// Read the binary data from the temporary file .
			$backSideBinaryData = fread(fopen($_FILES["jpegback"]["tmp_name"], "r"), filesize($_FILES["jpegback"]["tmp_name"]));


			// This will take the file data and insert the Image into the DB.  
			// If the image is a PDF or EPS file, it will insert the Vector Image into the DB as well as a rasterized version.
			// The function will return a hash with 2 possible Image ID's.. and a possible error message in case the conversion/insertion did not succeed.
			$backImageInsertHash = ImageLib::InsertRasterAndVectorImageIntoDB($dbCmd, "projectssession", $backSideBinaryData, $_FILES["jpegback"]["name"], $ArtworkDPI, "");

			unset($backSideBinaryData);

			if(!empty($backImageInsertHash["ErrorMsg"]))
				WebUtil::PrintError("On the Back-side Image:" . $backImageInsertHash["ErrorMsg"]);


			// Make sure the height x width of the customers artwork is exact for the back too.
			// We are more relaxed if they are going to the editor... since they can see the bleed/safe lines... so we don't need to enforce dimentions 
			$ImageDimArr_back = ImageLib::GetDimensionsFromRasterImageID($dbCmd, $backImageInsertHash["RasterImageID"]);

			// If the user has uploaded a Vector Image... then get the Pixel Dimensions from the Picas Value stored in the DB.
			if(!empty($backImageInsertHash["VectorImageID"]))
				$ImageDimArr_back = ImageLib::GetPixelDimensionsFromVectorImageID($backImageInsertHash["VectorImageID"], $ArtworkDPI);
			
			
			if(abs($TotalWidthPixels - $ImageDimArr_back["Width"]) > $MisMatchVarianceWidth || abs($TotalHeightPixels - $ImageDimArr_back["Height"]) > $MisMatchVarianceHeight){
				
				if(abs($TotalWidthPixels - $ImageDimArr_back["Height"]) > $MisMatchVarianceWidth || abs($TotalHeightPixels - $ImageDimArr_back["Width"]) > $MisMatchVarianceHeight){
					$backDimensionsMismatchFlag = true;
				}
				else{
					$backSideRotate90Flag = true;
				}
			}



			unlink($_FILES["jpegback"]["tmp_name"]);

			$BackRasterImageID = $backImageInsertHash["RasterImageID"];
			$BackVectorImageID = $backImageInsertHash["VectorImageID"];

		}
		
		
		$imageDimensionWarningFlag = false;
		$imageDimentionMisMatchMsg = null;
		
		// If they want to print directly from their Artwork files... then we need to take them to the editing tool if there are any Artwork Dimension Mis-matches.
		if($destination == "direct"){				
			
			// The warning message will depend on which sides are mis-matched and if those mis-matched sides are vector-based or not.
			$warningSidesDescription = "";
			$warningVectorInformation = "";

			if($frontDimensionsMismatchFlag && $backDimensionsMismatchFlag){

				$warningSidesDescription = "Front and Back sides";
				$warningDoPlural = "do not";

				if(!empty($frontImageInsertHash["VectorImageID"]) && !empty($BackVectorImageID))
					$warningVectorInformation = "Both the front and the back appear to be in 'Vector Format' so you can use the editing tool to resize/re-position the artwork files on the Product Canvas without losing any print quality. ";
				else if(!empty($frontImageInsertHash["VectorImageID"]) && empty($BackVectorImageID))
					$warningVectorInformation = "The Front-side of your artwork appears to be in 'Vector Format', but the Back-side does not. You can use the editing tool to resize the Front-side of your artwork on the Product Canvas without losing any print quality.  Increasing the size of the Back-side may result in an insufficient DPI. ";
				else if(empty($frontImageInsertHash["VectorImageID"]) && !empty($BackVectorImageID))
					$warningVectorInformation = "The Back-side of your artwork appears to be in 'Vector Format', but the Front-side does not. You can use the editing tool to resize the Back-side of your artwork on the Product Canvas without losing any print quality.  Increasing the size of the Front-side may result in an insufficient DPI. ";
				else if(empty($frontImageInsertHash["VectorImageID"]) && !empty($BackVectorImageID))
					$warningVectorInformation = "Neither the Front nor Back side of your artwork appears to be in 'Vector Format' (PDF or EPS).  If you want to print directly from your artwork file then a Vector-based format is recommended to achieve the highest print-quality.   Since the artwork files that you uploaded do not match our dimensions exactly, you can use the editing tool to resize or reposition your files on the Product Canvas.  Keep in mind that increasing the size of images may result in an insufficient DPI (since these files are not in 'Vector Format'). ";

			}
			else if($frontDimensionsMismatchFlag || $backDimensionsMismatchFlag){

				if($frontDimensionsMismatchFlag)
					$warningSidesDescription = "Front-side";
				else
					$warningSidesDescription = "Back-side";

				$warningDoPlural = "does not";

				if( ($frontDimensionsMismatchFlag && !empty($frontImageInsertHash["VectorImageID"])) || ($backDimensionsMismatchFlag && !empty($BackVectorImageID)) )
					$warningVectorInformation = "However, your artwork appears to be in 'Vector Format' (PDF or EPS), which means that you can use the editing tool to resize or reposition your files on the Product Canvas without losing any print quality. ";
				else
					$warningVectorInformation = "The artwork that you uploaded is not in 'Vector Format' (PDF or EPS).  If you want to print directly from your file then a Vector-based format is recommended to achieve the highest print-quality.  Since the artwork files that you uploaded do not match our dimensions exactly, you can use the editing tool to resize or reposition your files on the Product Canvas. ";
			}

			
			// Set a Session Variable with text that will be displayed above the editing tool on the following screen.
			if($frontDimensionsMismatchFlag || $backDimensionsMismatchFlag){
			
				$imageDimensionWarningFlag = true;
				
				$imageDimentionMisMatchMsg = "You attempted to upload a graphic file for the " . $warningSidesDescription . " of this product that " . $warningDoPlural . " match the required Artwork Dimensions.<br><br>";
				$imageDimentionMisMatchMsg .= $warningVectorInformation . "<br><br>";
				$imageDimentionMisMatchMsg .= "If you have any doubts over the quality or layout of the finished product then you may consider uploading new files that meet our Artwork Dimensions precisely.  You can also contact Customer Service for assistance.<br><br>We highly recommend  printing out a proof of your artwork (on your home/office printer) once the project is inside of the Shopping Cart.";


				
			}
		}



		// Erase any layers on the artwork file
		$ArtworkInfoObj->SideItemsArray[0]->layers = array();

		// If they are uploading a backside then we need to make a blank backside by copying the front
		if(!empty($BackRasterImageID)){
			
			// Copy the information that makes up the front side... we are going to copy it for the back
			$FrontSideCopy = unserialize(serialize($ArtworkInfoObj->SideItemsArray[0]));

			//Now put the copy back into the artwork object
			$ArtworkInfoObj->SideItemsArray[1] = $FrontSideCopy;
			
			// Make sure that the names of the sides are correctly described
			$ArtworkInfoObj->SideItemsArray[0]->description = "Front";
			$ArtworkInfoObj->SideItemsArray[1]->description = "Back";
		}
		else{
			if(isset($ArtworkInfoObj->SideItemsArray[1]))
				unset($ArtworkInfoObj->SideItemsArray[1]);
		}
		
		// If they are printing directly from their artwork, make sure it covers up to the edge of the bleed lines (assuming there are no Artwork Mismatches)
		// This is only meant to take care of people who are off by a tiny bit (1% or less)
		// Otherwise, Just use the default image/size width at the given DPI and place it in the center of the canvas.
		if($destination == "direct" && !$frontDimensionsMismatchFlag){
			$FlashImageWidth_front = round(($PixelsWide + $BleedPixels * 2) * 96 / $ArtworkDPI);
			$FlashImageHeight_front = round(($PixelsHigh + $BleedPixels * 2) * 96 / $ArtworkDPI);
		}
		else{
			$FlashImageWidth_front = round($ImageDimArr_front["Width"] * 96 / $ArtworkDPI);
			$FlashImageHeight_front = round($ImageDimArr_front["Height"] * 96 / $ArtworkDPI);
		}
		
		
		$ArtworkInfoObj->AddGraphicToArtwork(0, $frontImageInsertHash["RasterImageID"], $frontImageInsertHash["VectorImageID"], 0, 0, 0, $FlashImageWidth_front, $FlashImageHeight_front);
		
		// If there is a backside, then at a graphic layer to it.
		if(!empty($BackRasterImageID)){
		
			if($destination == "direct" && !$backDimensionsMismatchFlag){
				$FlashImageWidth_back = round(($PixelsWide + $BleedPixels * 2) * 96 / $ArtworkDPI);;
				$FlashImageHeight_back = round(($PixelsHigh + $BleedPixels * 2) * 96 / $ArtworkDPI);
			}
			else{
				$FlashImageWidth_back = round($ImageDimArr_back["Width"] * 96 / $ArtworkDPI);
				$FlashImageHeight_back = round($ImageDimArr_back["Height"] * 96 / $ArtworkDPI);
			}
			
			$ArtworkInfoObj->AddGraphicToArtwork(1, $BackRasterImageID, $BackVectorImageID, 0, 0, 0, $FlashImageWidth_back, $FlashImageHeight_back);
		}
		
		
		// Create a New Project Record to store the Artwork in.
		// Then change product options based upon the number of artwork sides.
		$projectrecord = ProjectSession::CreateNewDefaultProjectSession($dbCmd, $ProductID, $user_sessionID);
		
		$projectObj = ProjectBase::getprojectObjectByViewType($dbCmd, "session", $projectrecord);		
		
		
		
		// Get the default Product Artwork (which may have Text Layers for Mailing, etc.) and Merge the template on top.
		// That will also allow the User to Specify a Double-Sided Template in the UserID of artwork setup... and have only the Front-Side merged on top.
		$defaultProductArtwork = $productObj->getDefaultProductArtwork();
		
		$artworkMergeObj = new ArtworkMerge($dbCmd);
		$artworkMergeObj->setBottomArtwork($defaultProductArtwork);
		$artworkMergeObj->setTopArtwork($ArtworkInfoObj->GetXMLdoc());
		
		$ArtWorkMerged = $artworkMergeObj->getMergedArtwork();
		
		
		$artworkObj_merged = new ArtworkInformation($ArtWorkMerged);
		
		// Rotate the Front Side or the Backside if it was flagged.
		// Loop through all of the image layers until we find a graphic with the ImageID matching
		if($fontSideRotate90Flag){
			for($i=0; $i<sizeof($artworkObj_merged->SideItemsArray[0]->layers); $i++){
			
				if($artworkObj_merged->SideItemsArray[0]->layers[$i]->LayerType != "graphic")
					continue;
							
				if($artworkObj_merged->SideItemsArray[0]->layers[$i]->LayerDetailsObj->imageid == $frontImageInsertHash["RasterImageID"]){
					$artworkObj_merged->SideItemsArray[0]->layers[$i]->rotation = "90";
					
					// Swap the Widths with the Heights
					$tempLayerHeight = $artworkObj_merged->SideItemsArray[0]->layers[$i]->LayerDetailsObj->height;
					$tempLayerWidth = $artworkObj_merged->SideItemsArray[0]->layers[$i]->LayerDetailsObj->width;
					
					$artworkObj_merged->SideItemsArray[0]->layers[$i]->LayerDetailsObj->height = $tempLayerWidth;
					$artworkObj_merged->SideItemsArray[0]->layers[$i]->LayerDetailsObj->width = $tempLayerHeight;
					$artworkObj_merged->SideItemsArray[0]->layers[$i]->LayerDetailsObj->originalheight = $tempLayerWidth;
					$artworkObj_merged->SideItemsArray[0]->layers[$i]->LayerDetailsObj->originalwidth = $tempLayerHeight;
				}
			}
		}
		
		if($backSideRotate90Flag){
			for($i=0; $i<sizeof($artworkObj_merged->SideItemsArray[1]->layers); $i++){
			
				if($artworkObj_merged->SideItemsArray[1]->layers[$i]->LayerType != "graphic")
					continue;
							
				if($artworkObj_merged->SideItemsArray[1]->layers[$i]->LayerDetailsObj->imageid == $backImageInsertHash["RasterImageID"]){
					$artworkObj_merged->SideItemsArray[1]->layers[$i]->rotation = "270";
					
					// Swap the Widths with the Heights
					$tempLayerHeight = $artworkObj_merged->SideItemsArray[1]->layers[$i]->LayerDetailsObj->height;
					$tempLayerWidth = $artworkObj_merged->SideItemsArray[1]->layers[$i]->LayerDetailsObj->width;
					
					$artworkObj_merged->SideItemsArray[1]->layers[$i]->LayerDetailsObj->height = $tempLayerWidth;
					$artworkObj_merged->SideItemsArray[1]->layers[$i]->LayerDetailsObj->width = $tempLayerHeight;
					$artworkObj_merged->SideItemsArray[1]->layers[$i]->LayerDetailsObj->originalheight = $tempLayerWidth;
					$artworkObj_merged->SideItemsArray[1]->layers[$i]->LayerDetailsObj->originalwidth = $tempLayerHeight;
				}
			}
		}
		
		
		
		// Update the DB with the new Artwork template and record what template we are using
		$projectObj->setArtworkFile($artworkObj_merged->GetXMLdoc(), true);
		$projectObj->setFromTemplateID(0);
		$projectObj->setFromTemplateArea("P"); // u'P'loaded
		
		$projectObj->changeProjectOptionsToMatchArtworkSides();
		$projectObj->updateDatabase();
		
	

		
		WebUtil::SetSessionVar( ("ArtworkDimensionMismatch" . $projectrecord), $imageDimentionMisMatchMsg);

		// Now figure out where to re-direct the customer
		$locationToShoppingCart = "shoppingcart_saveartwork.php?projectrecord=$projectrecord&IgnoreEmptyTextAlert=true&UseExistingArtwork=true&returnurl=" . urlencode("shoppingcart.php") . "&nocache=" . time();
		$locationToEditor = "edit_artwork.php?projectrecord=$projectrecord&editorview=projectssession&cancelurl=" . $current_URL_encoded . "&nocache=" . time();

		if($destination == "direct" && !$imageDimensionWarningFlag)
			header("Location: ". WebUtil::FilterURL($locationToShoppingCart), true, 302);
		else if($destination == "cart")
			header("Location: ". WebUtil::FilterURL($locationToShoppingCart), true, 302);
		else
			header("Location: ". WebUtil::FilterURL($locationToEditor), true, 302);
			
		exit;
	}
	else
		throw new Exception("Illegal Action name.");
}




// Set this variable.  We will check to make sure it gets set on the following page.  If we cant find it then the person might not have cookies enabled.
$HTTP_SESSION_VARS['initialized'] = 1;


$t = new Templatex();

$t->set_file("origPage", "product_uploadartwork-template.html");

VisitorPath::addRecord("Upload Artwork Screen", $productObj->getProductTitleWithExtention());


// Zero will bring up the default Template Category.
$t->set_var("CATEGORYTEMPLATE", "0");



// Double the bleed pixels because we are always dealing with bleed on both sides... top/bottom  left/right
$BleedPixels *= 2;

$t->set_var("DPI", $ArtworkDPI);
$t->set_var("PIXELS_TOTAL", ($PixelsWide + $BleedPixels) . " px. x " . ($PixelsHigh + $BleedPixels) . " px.");
$t->set_var("INCHES_TOTAL", round(($PixelsWide + $BleedPixels) / $ArtworkDPI, 2) . "&quot; x " . round(($PixelsHigh + $BleedPixels) / $ArtworkDPI, 2) . "&quot;");
$t->set_var("PIXELS_TRIM", ($PixelsWide) . " px. x " . ($PixelsHigh) . " px.");
$t->set_var("INCHES_TRIM", round($PixelsWide / $ArtworkDPI, 2) . "&quot; x " . round($PixelsHigh / $ArtworkDPI, 2) . "&quot;");
$t->set_var("INCHES_SAFE", round(($PixelsWide - $BleedPixels) / $ArtworkDPI, 2) . "&quot; x " . round(($PixelsHigh - $BleedPixels) / $ArtworkDPI, 2) . "&quot;");
$t->set_var("PIXELS_SAFE", ($PixelsWide - $BleedPixels) . " px. x " . ($PixelsHigh - $BleedPixels) . " px.");
$t->set_var("PRODUCTID", $ProductID);
$t->set_var("PRODUCT_TITLE", WebUtil::htmlOutput($productObj->getProductTitleWithExtention()));
$t->set_var("BLEED_PIXELS", $BleedPixels);
$t->set_var("BLEED_PICAS", $productObj->getArtworkBleedPicas());
$t->set_var("PRODUCT_NAME_EXT", WebUtil::htmlOutput($productObj->getProductTitleWithExtention()));
$t->set_var("PRODUCTNAME_WITH_SLASHES", addslashes($productObj->getProductTitle()));
$t->set_var("ARTWORK_SIDE_COUNT", ($productObj->getArtworkSidesCount()));

// If the user did not provide a message to embed into the Artwork Upload Image Template... then erase the block of HTML that shows it.
if(!$productObj->getArtworkImageUploadEmbedMsg())
	$t->discard_block("origPage", "AutomaticUploadArtworkTemplateJpegBL");

// If they haven't provided a Custom URL for the Artwork Template... then remove that block of HTML.
if(!$productObj->getArtworkCustomUploadTemplateURL())
	$t->discard_block("origPage", "CustomUploadArtworkTemplateBL");


$t->set_var("ARTWORK_TEMPLATE_URL", $productObj->getArtworkCustomUploadTemplateURL());
$t->set_var("CURRENTURL_ENCODED", $current_URL_encoded);
$t->set_var("CURRENTURL", WebUtil::htmlOutput($current_URL));




// By Setting this flash variable... it will determine how the Nav bar displays ---#
// We need to send this information through Session Vars and XML because at the moment there is no other reliable way to send information to the Flash Object... at least on Macs and Safari browsers
// Although we could change the URL for the SWF file by inserting name value pairs... that would cause a new file to download for each unique combo.
$HTTP_SESSION_VARS['Template_StarUp'] = "upload";
$HTTP_SESSION_VARS['Template_NavView'] = "upload";


$ClientInfo = new UserAgent();
if($ClientInfo->platform != 'Windows'){
	$t->discard_block("origPage", "EyeCandyBL");
	$t->set_var("IFWINDOWS","false");
}
else
	$t->set_var("IFWINDOWS","true");



$destinationLocation = WebUtil::GetInput("destination", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if(empty($destinationLocation)){
	$destinationLocation = WebUtil::GetSessionVar("AfterUploadDestination");
	if(empty($destinationLocation)){
		$destinationLocation = "editor";
	}
}
	
// Set the Default Radio button choice
if($destinationLocation == "direct"){
	$t->set_var("RADIO_EDITOR", "");
	$t->set_var("RADIO_DIRECT", "checked");
	$t->set_var("RADIO_CART", "");
}
else if($destinationLocation == "cart"){
	$t->set_var("RADIO_EDITOR", "");
	$t->set_var("RADIO_DIRECT", "");
	$t->set_var("RADIO_CART", "checked");
}
else{
	$t->set_var("RADIO_EDITOR", "checked");
	$t->set_var("RADIO_DIRECT", "");
	$t->set_var("RADIO_CART", "");
}


$t->pparse("OUT","origPage");


?>
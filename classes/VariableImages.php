<?

// This class is responsible for saving Images to the DB and fetching them and some other various things
// The images are for Variable Images... used on Postcards etc.
// The images must belong to a particular user... so you must be logged in in order to save, retrieve, check, etc.


class VariableImages {

	private $_userID;
	private $_errorMessage;
	private $_dbCmd;

	

	// Constructor
	function VariableImages(DbCmd $dbCmd, $userID){
		$this->_userID = $userID;
		$this->_errorMessage = "";
		$this->_dbCmd = $dbCmd;
	}
	
	// Static function to get VariableImage Object based on a Project Objec.
	function getVariableImageObjectFromProject(DbCmd $dbCmd, &$projectObj){
	
		// We need to get the UserID that this project belongs to because Variable Data Images must belong to a user
		if($projectObj->getViewTypeOfProject() == "ordered"){
			$customerID = Order::GetUserIDFromOrder($dbCmd, $projectObj->getOrderID());
		}
		else if($projectObj->getViewTypeOfProject() == "saved"){
			$customerID = $projectObj->getSavedProjectUserID();
		}
		else if($projectObj->getViewTypeOfProject() == "session"){
			
			// If they are not logged in, there is no way they would be allowed to generate a Variable Data PDF with Variable Images
			// So it is save to Get the UserID here.
			$AuthObj = new Authenticate(Authenticate::login_general);

			//The user ID that we want to use for the Saved Project might belong to somebody else;
			$customerID = ProjectSaved::GetSavedProjectOverrideUserID($AuthObj);
		}
		else{
			throw new Exception("Illegal Project Type in function VariableImages->getVariableImageObjectFromProject");
		}
	
		$variableImagesObj = new VariableImages($dbCmd, $customerID);
		
		return $variableImagesObj;
	}
	
	
	// Insert Image by passing Binary Data in memory
	// Include the file name from the customer... this is how we will reference it in the future.
	// We are only storing JPEG and PNG images in the Database... so you must convert the image before this function or will fail with an error
	// The image must be error checked and ready for use in our system
	// However the customer file name must retain its native extension... We may store a JPEG in the DB but the customer file name ends in .tif
	// If the same Customer File Name already exists (and it is in the Active Binary table)... the old one will get removed and the new one will replace it

	// If the file name already exists and the Binary Table is protected from a previous rotation... it will not get removed.  Instead the new image will get inserted and the Pointer table will point to the new image.
	// Returns the Image ID that has been inserted or updated

	function insertImage(&$binaryData, $fileMimeType, $custmerFileName, $imageWidth, $imageHeight){
		
		WebUtil::EnsureDigit($imageWidth);
		WebUtil::EnsureDigit($imageHeight);
	
		$insertArr["BinaryData"] = $binaryData;
		$insertArr["DateUploaded"] = date("YmdHis");
		$insertArr["FileSize"] = strlen($binaryData);
		$insertArr["FileType"] = $fileMimeType;
		$insertArr["PixelWidth"] = $imageWidth;
		$insertArr["PixelHeight"] = $imageHeight;


		// Find out what binary table is currently active
		// They get rotated once they become too full.  The old ones are Read only
		$this->_dbCmd->Query("SELECT TableNameWithSuffix FROM tablerotations WHERE RootTableName='variableimages'");
		$activeTableName = $this->_dbCmd->GetValue();


		if($this->checkIfImageExists($custmerFileName)){

			// Now find out what Table the current Image is stored in.
			$imageID = $this->getImageIDbyCustomerFileName($custmerFileName);
			$this->_dbCmd->Query("SELECT TableName, Record AS RecordID FROM variableimagepointer WHERE ID=$imageID");
			$row = $this->_dbCmd->GetRow();
			$storedTableName = $row["TableName"];
			$recordID = $row["RecordID"];

			// If the Binary Table that Image is stored in matches The Active binary table then we can just overwrite
			if($storedTableName == $activeTableName){
			
				$this->_dbCmd->UpdateQuery($activeTableName, $insertArr, "ID=$recordID");
			}
			else{
			
				$newRecordID = $this->_dbCmd->InsertQuery($activeTableName, $insertArr);
				
				$this->_dbCmd->UpdateQuery("variableimagepointer", array("TableName"=>$activeTableName, "Record"=>$newRecordID), "ID=$imageID");
			}
			
			return $imageID;
		}
		else{

			$newRecordID = $this->_dbCmd->InsertQuery($activeTableName, $insertArr);
		
			$pointerArr["TableName"] = $activeTableName;
			$pointerArr["Record"] = $newRecordID;
			$pointerArr["FileName"] = $this->cleanCustomerFileName($custmerFileName);
			$pointerArr["UserID"] = $this->_userID;
			
			
			$newImageID = $this->_dbCmd->InsertQuery("variableimagepointer", $pointerArr);

			return $newImageID;
		}
	
	}
	
	// Returns the Image ID in our Database that corresponds to the Name of the Image that the customer Uploaded
	function getImageIDbyCustomerFileName($fileName){

		$fileName = $this->cleanCustomerFileName($fileName);
		
		$fileName = DbCmd::EscapeSQL($fileName);

		$this->_dbCmd->Query("SELECT ID FROM variableimagepointer WHERE UserID=" . $this->_userID . " AND FileName like '$fileName'");
		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method call getImageIDbyCustomerFileName.  The File does not exist.  Make sure to check if it exists before calling this method.");
	
		return $this->_dbCmd->GetValue();
	}
	
	
	// Returns a hash with 2 elements... the file type and the Binary Data
	function getImageByID($imageID){
		WebUtil::EnsureDigit($imageID);
		
		$this->_dbCmd->Query("SELECT TableName, Record FROM variableimagepointer WHERE ID=$imageID");
		if($this->_dbCmd->GetNumRows() == 0){
			WebUtil::WebmasterError("The Image ID:$imageID does not exist within the function getVariableImageByID");
			throw new Exception("The Image ID does not exist within getVariableImageByID");
		}
		
		$row = $this->_dbCmd->GetRow();
		
		$this->_dbCmd->Query("SELECT BinaryData, FileType FROM " . $row["TableName"] . " WHERE ID=" . $row["Record"]);
		if($this->_dbCmd->GetNumRows() == 0){
			WebUtil::WebmasterError("The Image Pointed To by ImageID:$imageID does not exist within the function getVariableImageByID");
			throw new Exception("The Image Pointed to by the Image ID does not exist within getVariableImageByID");
		}
		
		
		$row2 = $this->_dbCmd->GetRow();
		
		return array("ContentType"=>$row2["FileType"], "ImageData"=>$row2["BinaryData"]);
	
	}
	
	// returns true or false whether the file name has been successfully uploaded for the user
	// Just pass in the file name... not the path.   Should include the extention... like .jpg or .png 
	// It is not case sensitive
	function checkIfImageExists($fileName){
		
		$fileName = $this->cleanCustomerFileName($fileName);
		
		$fileName = DbCmd::EscapeSQL($fileName);
			
		$this->_dbCmd->Query("SELECT COUNT(*) FROM variableimagepointer WHERE UserID=" . $this->_userID . " AND FileName like '$fileName'");

		if($this->_dbCmd->GetValue() == 0)
			return false;
		else
			return true;
	}
	
	

	// Pass in a reference to a Artwork Configuration Object and a Variable Data object

	// Will fail critically if there are any Artwork Configuration errors... or an empty data file, etc. Make sure they are good before hand
	// Will see what variables are intended having Variable Images and correlate that to the datafile

	// It will correlate the data file to Variable images stored in the database
	// If one or more images is missing then the method will return false.
	// You can get an English version of the Error message by calling the method getErrorMessage()
	// If there are multiple Images missing it will stop on the first image that it find missing.
	// For very large data files this may be processor intensive, so use the method conservatively
	// returns true if there is an Error, false if no errors
	function checkForVariableImageErrors(&$artworkVarsMappingObj, &$variableDataObj){
		
		if($artworkVarsMappingObj->checkForMappingErrors())
			throw new Exception("You can not have any Artwork Mapping errors when calling the method checkForVariableImageErrors");
			
		if($variableDataObj->checkIfError())
			throw new Exception("There can not be any errors with the Variable Data object when calling the method checkForVariableImageErrors.");		
		
		if(!$artworkVarsMappingObj->checkForVariableImageTag())
			throw new Exception("The Artwork Mapping Object must have some variable images before calling the method checkForVariableImageErrors");

		$this->_errorMessage = "";
			
		// Get an Array of all Variables in the Artwork that represent a Variable Image
		$variableImagesNamesArr = $artworkVarsMappingObj->getVariableImageNamesArr();
		
		// We are going to loop through each Variable Name and correlate it to The column in the data file
		// Then look at the Database to make sure each image exists
		foreach($variableImagesNamesArr as $thisVariableImageName){

			$fieldPosition = $artworkVarsMappingObj->getFieldPosition($thisVariableImageName);
			
			$numRows = $variableDataObj->getNumberOfLineItems();
				
			for($i=0; $i<$numRows; $i++){
			
				$columnsArr = $variableDataObj->getVariableRowByLineNo($i);
				
				// This should be the name of the Customers image they specify within their Excel file
				$customerFileNameOfImage = $columnsArr[($fieldPosition - 1)];
				
				$customerFileNameOfImage = trim($customerFileNameOfImage);

		
				// Now we check to see if that image exists in the DB for this user
				if(!$this->checkIfImageExists($customerFileNameOfImage)){

					// Search for some kind of a file extension
					if($i == 0 && (!preg_match("/\.\w{2,4}$/", $customerFileNameOfImage) || strlen($customerFileNameOfImage) > 50))		
						$this->_errorMessage = "The Variable \"$thisVariableImageName\" needs to be mapped to a column in your Data File that contains a list of File Names (for variable images).";
					else if(!preg_match("/\.\w{2,4}$/", $customerFileNameOfImage) || strlen($customerFileNameOfImage) > 50)
						$this->_errorMessage = "There is a problem with the File Name on Line #" . ($i+1) . ", Column #$fieldPosition within your Data File.";
					else if(preg_match("/(\/|\\\\)/", $customerFileNameOfImage))
						$this->_errorMessage = "There is a problem with the image name on Line #" . ($i+1) . ", Column #$fieldPosition within your Data File.  There is a backward or foward slash in the name.  Do not include a path to the image, just the image name.";
					else if($i == 0)	
						$this->_errorMessage = "The Variable Name \"$thisVariableImageName\" is mapped to column #$fieldPosition within your Data File.  It does not look like you have uploaded the corresponding images yet.";
					else
						$this->_errorMessage = "There is a problem with the image on Line #" . ($i+1) . ", Column #$fieldPosition within your Data File.  It looks like the image has not been uploaded yet.";

					return true;
				}
			
			}
	
	
		}

		return false;

	}
	
	
	// The rule is that no Variable Image may take up more than 1/2 of the canvas area
	// Otherwise the PDF file size would grow too large and take too long to generate
	// It will also ensure that no variable images are spilling over the Bleed/Safe lines
	function checkForVariableImageSizeAndPositionErrors($artworkInfoObj, &$artworkVarsMappingObj, &$variableDataObj){
	
		if($this->checkForVariableImageErrors($artworkVarsMappingObj, $variableDataObj))
			throw new Exception("Error in Method checkForVariableImageSizeAndPositionErrors.  This object must be free of Variable Image Errors before calling this method.");
	
		$this->_errorMessage = "";
		
		$variableDataProcessObj = new VariableDataProcess($artworkInfoObj->GetXMLdoc(), $artworkVarsMappingObj, $variableDataObj);
		
		$ArtworkObjWithOnlyImages = $artworkInfoObj;
		$variableDataProcessObj->removeAnyLayerNotForVariableImages($ArtworkObjWithOnlyImages);

		foreach($ArtworkObjWithOnlyImages->SideItemsArray as $thisSideObj){
			
			$dpi = $thisSideObj->dpi;
	
			foreach($thisSideObj->layers as $thisLayerObj){
				
				// Get the Variable Name for this variable image
				$textStringFromVariableImageLayer = $thisLayerObj->LayerDetailsObj->message;
				$thisVariableImageName = $variableDataProcessObj->getVariableNameOfVariableImage($textStringFromVariableImageLayer);
			
				// Find out what column this variable is mapped to
				$fieldPosition = $artworkVarsMappingObj->getFieldPosition($thisVariableImageName);

				$numRows = $variableDataObj->getNumberOfLineItems();

				for($i=0; $i<$numRows; $i++){

					$columnsArr = $variableDataObj->getVariableRowByLineNo($i);

					// This should be the name of the Customers image they specify within their Excel file
					$customerFileNameOfImage = $columnsArr[($fieldPosition - 1)];
					$customerFileNameOfImage = trim($customerFileNameOfImage);
					
					$originalImageDimensions = $this->getNativeWidthHeightOfImage($customerFileNameOfImage);
					
					// After any attributes and DPI is applies... how big will this variable image end up being in Pixels
					$modifiedPixelDimensions = $this->getHeightWidthForVariableImage($originalImageDimensions["PixelWidth"], $originalImageDimensions["PixelHeight"], $dpi, $textStringFromVariableImageLayer);
					
					$pixelAreaOfVariableImage = $modifiedPixelDimensions["Width"] * $modifiedPixelDimensions["Height"];
					
					$pixelAreaOfCanvasArea = ($thisSideObj->contentwidth / 96 * $dpi) * ($thisSideObj->contentheight / 96 * $dpi);
					
					// Are limit is actually set to 55% just to give them a little extra room for Bleed Area, etc.
					if($pixelAreaOfVariableImage / $pixelAreaOfCanvasArea > 0.55){

						$this->_errorMessage = "There is a variable image occupying more than 50% of the canvas area.  
										Variable images may not take up too much room on your design.  
										This is due to file size and processor limitations.  
										If you are not sure which image file is doing this, you should consider putting in Maximum Height/Width attributes for your image variable(s).  
										Read the Variable Image Guide for more information on doing this. ";
						$this->_errorMessage = preg_replace("/(\n|\r|\t|\s+)/", " ", $this->_errorMessage);
						return true;
					}
					
				}
			}
			
		
		}
		
		return false;
	
	
	}


	
	function getErrorMessage(){
	
		return $this->_errorMessage;
	}
	

	// Prevent Tampering
	function cleanCustomerFileName($fileName){
	
		$fileName = trim($fileName);
		
		// Prevent Tampering
		$fileName = preg_replace("/(\r|\n)/", "", $fileName);
		
		// Get rid of foward slashes
		$fileName = preg_replace("/\//", "", $fileName);
		
		// Get rid of backs slashes
		$fileName = preg_replace("/\\\\/", "", $fileName);
		
		return $fileName;
	}
	

	// Get the Origigal Height and Width of the Image as it was uploaded by the customer.
	// Returns a hash like array("PixelWidth"=>100, "PixelHeight"=>230)
	function getNativeWidthHeightOfImage($fileName){

		$ImageID = $this->getImageIDbyCustomerFileName($fileName);
		
		$this->_dbCmd->Query("SELECT Record, TableName FROM variableimagepointer WHERE ID=" . $ImageID);
		$row = $this->_dbCmd->GetRow();
		$this->_dbCmd->Query("SELECT PixelWidth, PixelHeight FROM " . $row["TableName"] . " WHERE ID=" . $row["Record"]);
		$row2 = $this->_dbCmd->GetRow();
		
		return $row2;
	}



	// Static Method
	// Based upon the Attributes for a variable Image... this method will return a hash containing the height and width that we should resize an image to (in pixels)
	function getHeightWidthForVariableImage($origWidth, $origHeight, $dpi, $textStringFromLayer){
	
		WebUtil::EnsureDigit($origWidth, false);
		WebUtil::EnsureDigit($origHeight, false);
		WebUtil::EnsureDigit($dpi);
		
		// In case of something weird happening (which has happened on rare occasions with large variable image orders...
		// Don't let a division by zero happen.
		if(empty($origWidth) || $origWidth < 1)
			$origWidth = 1;
		if(empty($origHeight) || $origHeight < 1)
			$origHeight = 1;
		
		if(empty($dpi))
			throw new Exception("Problem in the method getHeightWidthForVariableImage.  The DPI is incorrect.");
	
		// Remember that Attributes for a Variable Image are in Picas... 1/72 of an inch.. so they pixels are dependent upon the DPI.
		
		$maxWidth = VariableImages::getAttributeOfVariableImage($textStringFromLayer, "MaxWidth");
		$maxHeight = VariableImages::getAttributeOfVariableImage($textStringFromLayer, "MaxHeight");
		$exactWidth = VariableImages::getAttributeOfVariableImage($textStringFromLayer, "ExactWidth");
		$exactHeight = VariableImages::getAttributeOfVariableImage($textStringFromLayer, "ExactHeight");
		
		// Keep people from accidently typing in a number and resizing out of control... basically 12 inches Tall or Wide
		if($exactWidth > 72 * 12)
			$exactWidth = 72 * 12;
		if($exactHeight > 72 * 12)
			$exactHeight = 72 * 12;
		
		
		$pixelWidth = $origWidth;
		$pixelHeight = $origHeight;
		
		$wouldBeMaxHeight = $maxHeight / 72 * $dpi;
		$wouldBeMaxWidth = $maxWidth / 72 * $dpi;
		
		$getHeightRatio = $origHeight / $origWidth;
		$getWidthRatio = $origWidth / $origHeight;
		
		// If we know what height and width we want to resize to, then don't worry about keeping things proportional
		if($exactWidth && $exactHeight){
			$pixelWidth = $exactWidth / 72 * $dpi;
			$pixelHeight = $exactHeight / 72 * $dpi;
		}
		else if($exactWidth && $maxHeight){
		
			$pixelWidth = $exactWidth / 72 * $dpi;
			$newHeight = round($pixelWidth * $getHeightRatio);
			
			if($wouldBeMaxHeight < $newHeight)
				$pixelHeight = $wouldBeMaxHeight;
			else
				$pixelHeight = $newHeight;
		}
		else if($exactHeight && $maxWidth){
		
			$pixelHeight = $exactHeight / 72 * $dpi;
			$newWidth = round($pixelHeight * $getWidthRatio);
			
			if($wouldBeMaxWidth < $newWidth)
				$pixelWidth = $wouldBeMaxWidth;
			else
				$pixelWidth = $newWidth;
		}
		else if($exactWidth){
		
			$pixelWidth = $exactWidth / 72 * $dpi;			
			$pixelHeight = round($pixelWidth * $getHeightRatio);
		}
		else if($exactHeight){
		
			$pixelHeight = $exactHeight / 72 * $dpi;		
			$pixelWidth = round($pixelHeight * $getWidthRatio);
		}
		else if($maxWidth && $maxHeight){
		
			$maxWidth_W = 0;
			$maxWidth_H = 0;
			$maxHeight_W = 0;
			$maxHeight_H = 0;
		
			// Keep them proportional
			// But one of them may override the other on
			if($wouldBeMaxWidth < $origWidth){
				$maxWidth_W = $wouldBeMaxWidth;
				$maxWidth_H = round($wouldBeMaxWidth * $getHeightRatio);
			}
			
			if($wouldBeMaxHeight < $origHeight){
				$maxHeight_W = round($wouldBeMaxHeight * $getWidthRatio);
				$maxHeight_H = $wouldBeMaxHeight;
			}
			
			if(empty($maxWidth_W) && empty($maxHeight_W)){
				$pixelWidth = $origWidth;
				$pixelHeight = $origHeight;
			}
			else if(empty($maxHeight_W)){
				$pixelWidth = $maxWidth_W;
				$pixelHeight = $maxWidth_H;
			}
			else if(empty($maxWidth_W)){
				$pixelWidth = $maxHeight_W;
				$pixelHeight = $maxHeight_H;
			}
			else{
				// Well it looks like both of them hit the limit
				// Take the less of the 2
				if($maxWidth_W < $maxHeight_W){
					$pixelWidth = $maxWidth_W;
					$pixelHeight = $maxWidth_H;
				}
				else{
					$pixelWidth = $maxHeight_W;
					$pixelHeight = $maxHeight_H;
				}
			
			}
		
		
		}
		else if($maxWidth){
			if($wouldBeMaxWidth < $origWidth){
				$pixelWidth = $wouldBeMaxWidth;
				$pixelHeight = round($wouldBeMaxWidth * $getHeightRatio);
			}
		}
		else if($maxHeight){
			if($wouldBeMaxHeight < $origHeight){
				$pixelWidth = round($wouldBeMaxHeight * $getWidthRatio);
				$pixelHeight = $wouldBeMaxHeight;
			}
		}
	
	
		if($pixelWidth == 0 || $pixelHeight == 0)
			throw new Exception("Error in method getHeightWidthForVariableImage.  The Pixel Height or width was returned as 0");

	
		return array("Width"=>$pixelWidth, "Height"=>$pixelHeight);
	}



	// Static Function
	// This function will return an attribute for a particular Variable Image
	// A Text Layer is a place holder for where Variable Images will go.  So the text layer may have something like...
	// {IMAGE:HeadShot} (Aligntment:Right) (Valign:Middle) (MaxHeight:343)
	// The text layer may have the curly bracets for the Variable name and then the items in parenthesis represent how the image should be integrated
	// All other text within the layers will be removed... because the entire text layer will be replaced by an image
	// If one of the attributes is spelled incorrectly or is missing, then the default value will be returned... Like the default alignment is "left" for example.  There are no errors for incorrect attributes
	// Just pass in the complete text string within a layer and the Attribute you wish to extract from it
	// Not case sensitive
	function getAttributeOfVariableImage($textStringFromLayer, $attributeType){
	
		$m = array();
	
		if($attributeType == "Alignment"){
			if(preg_match("/\(\s*Alignment\s*:\s*(right|left|center)\s*\)/i", $textStringFromLayer, $m))
				return strtoupper($m[1]);
			return "LEFT";
		}
		else if($attributeType == "VerticalAlign"){
			if(preg_match("/\(\s*Vertical\s*Align\s*:\s*(top|middle|bottom)\s*\)/i", $textStringFromLayer, $m))
				return strtoupper($m[1]);
			return "BOTTOM";
		}
		else if($attributeType == "MaxWidth"){
			if(preg_match("/\(\s*Max\s*Width\s*:\s*(\d+)\s*\)/i", $textStringFromLayer, $m))
				return strtoupper($m[1]);
			return 0;
		}
		else if($attributeType == "MaxHeight"){
			if(preg_match("/\(\s*Max\s*Height\s*:\s*(\d+)\s*\)/i", $textStringFromLayer, $m))
				return strtoupper($m[1]);
			return 0;
		}
		else if($attributeType == "ExactWidth"){
			if(preg_match("/\(\s*Exact\s*Width\s*:\s*(\d+)\s*\)/i", $textStringFromLayer, $m))
				return strtoupper($m[1]);
			return 0;
		}
		else if($attributeType == "ExactHeight"){
			if(preg_match("/\(\s*Exact\s*Height\s*:\s*(\d+)\s*\)/i", $textStringFromLayer, $m))
				return strtoupper($m[1]);
			return 0;
		}
		else
			throw new Exception("Incorrect attribute in method getAttributeOfVariableImage");	
	}



	
}
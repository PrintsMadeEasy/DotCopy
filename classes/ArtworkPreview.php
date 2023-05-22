<?


// Controls writing Artwork previews to disk and loading them.

class ArtworkPreview {

	private $_dbCmd;
	private $_baseDirectory;
	private $_maxImageWidth;
	private $_maxImageHeight;
	private $_imageQuality;

	// Constructor
	function ArtworkPreview(DbCmd $dbCmd){

		$this->_dbCmd = $dbCmd;
		
		$this->_imageQuality = 70;
		$this->_maxImageWidth = 350;
		$this->_maxImageHeight = 500;
		
		$this->_baseDirectory = Constants::GetMingBase() . "/ArtPreview";
	}
	
	function setMaxImageHeight($pixels){
		
		$pixels = intval($pixels);
		if($pixels < 1)
			throw new Exception("The pixel value must be greater than zero.");
			
		$this->_maxImageHeight = $pixels;
	}
	function setMinImageWidth($pixels){
		
		$pixels = intval($pixels);
		if($pixels < 1)
			throw new Exception("The pixel value must be greater than zero.");
			
		$this->_maxImageWidth = $pixels;
	}
	
	function cleanOldPreviews(){

		// Setup Variable.  How long should we keep preview images for?
		$daysOldToDeletePreviewImages = 70;
		
		$parentDirectoryHandle = opendir($this->_baseDirectory . "/ordered");
		
		if(!$parentDirectoryHandle)
			throw new Exception("Directory does not exist for cleanOldPreviews.");

		while (false !== ($directoryName = readdir($parentDirectoryHandle))) {
			
			$possibleDirectory = $this->_baseDirectory . "/ordered/" . $directoryName;

			if(!is_dir($possibleDirectory))
				continue;
				
			// Now go through sub-directories looking for files.
			$subDirectoryHandle = opendir($possibleDirectory);
				
			if(!$subDirectoryHandle)
				throw new Exception("Sub-directory does not exist for cleanOldPreviews.");
				
			while (false !== ($fileName = readdir($subDirectoryHandle))) {

				$fullPathToFile = $possibleDirectory . "/" . $fileName;
				
				if(is_dir($fullPathToFile))
					continue;
				
				$dateStampOfFile = filemtime($fullPathToFile);
	
				if((time() - 60*60*24*$daysOldToDeletePreviewImages) > $dateStampOfFile){
					print "Removing: " . $fullPathToFile . " - " . date("n/j/Y", $dateStampOfFile) . "<br>\n";
					@unlink($fullPathToFile);
				}
			}
			closedir($subDirectoryHandle);
		}
		closedir($parentDirectoryHandle);
	}
	
	
	
	// If a thumbanil exists then there will be 1 or more preview images (Front, Back).
	// If no preview images exist then it will return an empty array.
	// PathType can be "system", "relative", or "none"
	function getArrayOfFileNames($projectID, $viewType, $pathType){
	
		$retArr = array();
		
		
		// We have to loop through every file in the directory and to a regex on it.
		// However, the "ls" and "find" commands in linux do the same thing through the "readdir" API anyway.
		
		$this->_createSubDirectoryIfNeeded($projectID, $viewType);
		if ($handle = opendir($this->getDirectoryOfArtworkPreview($projectID, $viewType))) {
			
		    /* This is the correct way to loop over the directory. */
		    while (false !== ($fileName = readdir($handle))) {
		    	
				if(preg_match("/^" . $projectID . "_.*/", $fileName)){
					
					if($pathType == "system")
						$retArr[] = $this->getDirectoryOfArtworkPreview($projectID, $viewType) . "/" . $fileName;
					else if($pathType == "relative")
						$retArr[] = "/ming/ArtPreview/ordered/" . $this->getSubDirectoryNameFromProjectID($projectID) . "/" . $fileName; 
					else if($pathType == "none")
						$retArr[] = $fileName;
					else
						throw new Exception("Illegal PathType in method ArtworkPreview->getArrayOfFileNames");
				}
		    }

		    closedir($handle);
		}
		
		return $retArr;
	}
	
	// Will find out if the artwork preview needs to be updated... and if so it will regenerate the thumbnail image.
	function updateArtworkPreview($projectID, $viewType){
	
		
		$fileNamesOfPreviewsArr = $this->calculateFileNamesForArtworkPreviews($projectID, $viewType, "system");
		
		$notExistsFlag = false;
		
		foreach($fileNamesOfPreviewsArr as $thisFname){
		
			if(!file_exists($thisFname))
				$notExistsFlag = true;
		}
		
		// If the required files are not already on disk then erase any previous ones for the projects and build new ones.
		if($notExistsFlag){
			
			// create directories to hold the files (if needed)
			$this->_createSubDirectoryIfNeeded($projectID, $viewType);
		
			// Get rid of any of the old files (before an artwork modification occured).
			$this->flushExistingImagesForProject($projectID, $viewType);
			
			
			$availableDiskSpace = disk_free_space ($this->_baseDirectory);
			
			if(!Constants::GetDevelopmentServer() && $availableDiskSpace < 1000000000){
				WebUtil::WebmasterError("Error in method ArtworkPreview->updateArtworkPreview. Less than 1 gig of space is left on disk. Image Preview not saved.");
				return;
			}
			
			
			
			$projObj = ProjectBase::getProjectObjectByViewType($this->_dbCmd, $viewType, $projectID, false);
			$artObj = new ArtworkInformation($projObj->getArtworkFile());
			
			for($sideCounter = 0; $sideCounter < sizeof($fileNamesOfPreviewsArr); $sideCounter++){
			
				$previewJPGtempName = ArtworkLib::GetArtworkImageWithText($this->_dbCmd, $sideCounter, $artObj, "V", 0, 0, false, false, array(), $this->_maxImageWidth, $this->_maxImageHeight, $this->_imageQuality);
			
				chmod ($previewJPGtempName, 0666);
				
				$averageTextRotation = $artObj->getAverageRotationOfTextLayers($sideCounter);

				// If the average rotation of text layers is in 1 direction... then rotate the preview images to match to people aren't breaking their necks.
				if($averageTextRotation != 0){
					$averageTextRotation = -1 * $averageTextRotation;
					$rotateCommand = Constants::GetUpperLimitsShellCommand(150, 30) . Constants::GetPathToImageMagick() . "mogrify -quality " . $this->_imageQuality . " -rotate " . $averageTextRotation . " " . $previewJPGtempName;
					system($rotateCommand);
				}
				
				
				// Move our temp file into a new file name by reading its data into memory and deleting the old temp name.
				$fd = fopen ($previewJPGtempName, "r");
				$ImageBinaryData = fread ($fd, filesize ($previewJPGtempName));
				fclose ($fd);
				@unlink($previewJPGtempName);
				
				
				$fp = fopen($fileNamesOfPreviewsArr[$sideCounter], "w");
				fwrite($fp, $ImageBinaryData);
				fclose($fp);
			}
		
		}
	}
	
	
	function _createSubDirectoryIfNeeded($projectID, $viewType){
	
		WebUtil::EnsureDigit($projectID, true, "Error with projectID in method _createSubDirectoryIfNeeded");
	
		if(!file_exists($this->_baseDirectory)){
			mkdir($this->_baseDirectory);
			chmod($this->_baseDirectory, 0777);
		}
	
	
		if($viewType == "ordered"){
		

			$imageCacheSubDir = $this->_baseDirectory . "/ordered"; 
			if(!file_exists($imageCacheSubDir)){
				mkdir($imageCacheSubDir);
				chmod($imageCacheSubDir, 0777);
			}
				
				
			$imageCacheSubDir .= "/" . $this->getSubDirectoryNameFromProjectID($projectID);

			if(!file_exists($imageCacheSubDir)){
				mkdir($imageCacheSubDir);
				chmod($imageCacheSubDir, 0777);
			}
		
		}
		else{
			throw new Exception("Illegal view type in method _createSubDirectoryIfNeeded");
		}
	
	}
	
	// To Keep the Inodes from filling up with files too quickly, we want to organize as many subdirectories as possible.
	// We don't ever want more than 10,000 files in a directory.
	function getSubDirectoryNameFromProjectID($projectID){
	
		return (ceil($projectID / 10000) * 10) . "K";
	}
	
	// returns the unix file path to the directory holding the project ID... or if it doesn't exist it will return the the directory where it should be located.
	function getDirectoryOfArtworkPreview($projectID, $viewType){
	
		WebUtil::EnsureDigit($projectID, true, "Error with projectID in method getDirectoryOfArtworkPreview");
	

		if($viewType == "ordered"){
			return $this->_baseDirectory . "/ordered/" . $this->getSubDirectoryNameFromProjectID($projectID); 
		
		}
		else{
			throw new Exception("Illegal view type in method getDirectoryOfArtworkPreview");
		}

	}
	
	
	// This is a fairly expensive method to call because it will calculate a hash off of the artwork file.
	// Will return an array of 1 or more file names.
	// PathType can be "system", or "none"
	function calculateFileNamesForArtworkPreviews($projectID, $viewType, $pathType){
	
		$projObj = ProjectBase::getProjectObjectByViewType($this->_dbCmd, $viewType, $projectID, false);
		
		$sideCount = $projObj->getArtworkSidesCount();
		
		$retArr = array();
		
		$artMD5 = md5($projObj->getArtworkFile());
		
		for($i=0; $i<$sideCount; $i++){
		
			$theFileName = $projectID . "_" . $i . "_" . $artMD5 . ".jpg";
			
			if($pathType == "system")
				$retArr[$i] = $this->getDirectoryOfArtworkPreview($projectID, $viewType) . "/" . $theFileName;
			else if($pathType == "none")
				$retArr[$i] = $theFileName;
			else
				throw new Exception("Error in method calculateFileNamesForArtworkPreviews with path type.");
		}
		
		return $retArr;
	}
	
	
	// If the MD5 changes for the artwork... then we will have to generate new preview images.
	// This will delete existing images belonging to the given project so it should be called right before making the new ones.
	function flushExistingImagesForProject($projectID, $viewType){

		$existingNamesArr = $this->getArrayOfFileNames($projectID, $viewType, "system");
		
		foreach($existingNamesArr as $thisFileName)
			@unlink($thisFileName);
	}




}



?>

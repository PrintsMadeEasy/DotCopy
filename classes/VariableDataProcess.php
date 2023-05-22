<?


// Combines functionality between Artwork and Data files

class VariableDataProcess {

	private $_variableDataObj;
	private $_artworkVarsMappingObj;
	private $_objectInitialized;
	private $_artworkXML;
	private $_lineCouter; // Will be used to advance upon the next line item in the Data File.
	
	
	// Constructor
	// The variable data object is passed by reference
	function VariableDataProcess($artworkFileXML, $artworkVarsMappingObj, &$variableDataObj){
	
		$this->_objectInitialized = false;
		$this->_artworkXML = $artworkFileXML;
		$this->_artworkVarsMappingObj = $artworkVarsMappingObj;
		$this->_variableDataObj = $variableDataObj;
		$this->_lineCouter = 0;
	
	}
	
	function resetCounter(){
		$this->_lineCouter = 0;
	}
	
	
	// returns how many lines are in the Data file
	function getTotalQuantity(){
	
		$this->_ensureObjectsSet();
		
		return $this->_variableDataObj->getNumberOfLineItems();
	
	}
	
	// Returns an Artwork with all varaibles replaced
	// Return False when the file hits the end.
	function getNextArtwork(){
	
		$artFile = $this->getArtworkByLineNumber($this->_lineCouter);
		
		$this->_lineCouter++;
		
		return $artFile;
	
	}
	
	// Returns an Artwork with all varaibles replaced
	// Return false if the Line number does not exist in the data file.
	// $lineNo in passed into this function is 1 based.... starting at Row 1
	function getArtworkByLineNumber($lineNo){
	
		$this->_ensureObjectsSet();
	
		$lineNo--;
		
		if($lineNo < 0)
			throw new Exception("The line number is out of range in the method call getArtworkByLineNumber.");
	
		if($lineNo > $this->getTotalQuantity())
			return false;
		
		$artworkReturn = $this->_artworkXML;
		
		$dataRow = $this->_variableDataObj->getVariableRowByLineNo($lineNo);
		
		// Cycle through every column for the current Data Row
		for($i=0; $i < sizeof($dataRow); $i++){

	
			// Get the Variable Name in the corresponding position
			// The Variable names are 1-based
			$artworkVarName = $this->_artworkVarsMappingObj->getVarNameByFieldPosition(($i+1));
			
			if(empty($artworkVarName))
				continue;
			
			$dataVar = $dataRow[$i];
			
			
			
			// Find out if there are any extra Data manipulations put on this Variable
			// Also the Data Cell must meet the given criteria for this substitution to happen.  Right now it is just ensuring that the column data must not be BLANK.
			if($this->_artworkVarsMappingObj->checkIfVariableHasDataAlteration($artworkVarName)){
			
				$dataAlterObj = $this->_artworkVarsMappingObj->getDataAlterationObjectForVariable($artworkVarName);
				
				if($dataAlterObj->checkIfDataMeetsCriteria($dataVar)){
				
				
					// Encode into HTML entities because the Data we may be removing or adding is within a Text layers and the text will already be encoded.
					// otherwise someone could remove a ">" or something and corrupt the XML file.
					$dataToAddBefore = WebUtil::htmlOutput($dataAlterObj->getDataAddBefore());
					$dataToAddAfter = WebUtil::htmlOutput($dataAlterObj->getDataAddAfter());

					$dataToRemoveBefore = WebUtil::htmlOutput($dataAlterObj->getDataRemoveBefore());
					$dataToRemoveAfter = WebUtil::htmlOutput($dataAlterObj->getDataRemoveAfter());


					// If this Data Alteration on this variable is meant to remove any data that comes before or after the Variable within the text layer.... then we will get rid of it and just leave the Variable Name in brackets
					// White spaces or line breaks should not prevent a "removal" from happening.
					if(!empty($dataToRemoveBefore))
						$artworkReturn = preg_replace("/"  . preg_quote($dataToRemoveBefore) . "(\s|\r|\n)*" . "\{$artworkVarName\}/", ("{" . $artworkVarName . "}"), $artworkReturn);

					if(!empty($dataToRemoveAfter))
						$artworkReturn = preg_replace("/\{$artworkVarName\}" . "(\s|\r|\n)*" . preg_quote($dataToRemoveAfter) . "/", ("{" . $artworkVarName . "}"), $artworkReturn);


					// Add data to Before or after the variable name.
					if(!empty($dataToAddBefore))
						$artworkReturn = preg_replace("/\{$artworkVarName\}/", ($dataToAddBefore . "{" . $artworkVarName . "}"), $artworkReturn);
					if(!empty($dataToAddAfter))
						$artworkReturn = preg_replace("/\{$artworkVarName\}/", ("{" . $artworkVarName . "}" . $dataToAddAfter), $artworkReturn);
				}
			
			}
			
			// Replace special characters for our Artwork XML file
			$dataVar = preg_replace("/&/", "&amp;", $dataVar);
			$dataVar = preg_replace("/</", "&lt;", $dataVar);
			$dataVar = preg_replace("/>/", "&gt;", $dataVar);
			$dataVar = preg_replace("/'/", "&apos;", $dataVar);
			$dataVar = preg_replace("/\"/", "&quot;", $dataVar);

			$artworkReturn = preg_replace("/\{$artworkVarName\}/", $dataVar, $artworkReturn);
			
		}
		
		return $artworkReturn;

	}
	
	
	
	// ------  Private Methods ---------
	function _ensureObjectsSet(){
	
		if($this->_variableDataObj->checkIfError() || $this->_artworkVarsMappingObj->checkForMappingErrors())
			throw new Exception("Both the VariableData Object and ArtworkMapping Object must be free of errors before calling this method.");
	
	}



	// ---------   Static function  ---------
	// Will Analyze the Artwork File and Data File for a Variable Data project and set the Status or warning message
	// If there is a large Data file... it could take a while to process.  Use this function conservatively
	static function SetVariableDataStatusForProject(DbCmd $dbCmd, $projectID, $viewType){

		$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $viewType, $projectID);
		
		if(!$projectObj->isVariableData())
			return;
		
		$artworkFile = $projectObj->getArtworkFile();

		$artworkInfoObj = new ArtworkInformation($artworkFile);

		$artworkVarsObj = new ArtworkVarsMapping($artworkFile, $projectObj->getVariableDataArtworkConfig());
		$varDataObj = new VariableData();
	
		// Find out if Variable Images has been selected as a product option
		$supposedToHaveVarImagesFlag = $projectObj->hasVariableImages();
		
		
		
		// Automatically Map Variables into the Open positions.
		if($artworkVarsObj->checkForUnmappedFields()){
			
			$unMappedVarsArr = $artworkVarsObj->getUnMappedVariableNamesArr();
			
			$openPositionsArr = $artworkVarsObj->getOpenPositionsWithinColumnRange();
			
			$j=0;
			foreach($unMappedVarsArr as $thisVarName){
				$artworkVarsObj->setFieldPosition($thisVarName, $openPositionsArr[$j]);
				$j++;
			}

			$projectObj->setVariableDataArtworkConfig($artworkVarsObj->getVarMappingInXML());
			$projectObj->updateDatabaseWithRawData();
		}
		
		
		// If an Artwork Variable is missing from the Artwork... then we want to automatically remove it from our Artwork Varialbe Config
		if($artworkVarsObj->checkForExtraMappedFields()){
			
			$extraMappedVarsArr = $artworkVarsObj->getExtraMappedFieldsArr();
			
			foreach($extraMappedVarsArr as $thisVarName)
				$artworkVarsObj->removeVariableFromMapping($thisVarName);

			$projectObj->setVariableDataArtworkConfig($artworkVarsObj->getVarMappingInXML());
			$projectObj->updateDatabaseWithRawData();
		}
		
		
		
		// If we there are variable images on this product, then make sure that the artwork file does not exceed 200 DPI
		// Otherwise it could put a strain on the server trying to generate the PDF files.
		if($supposedToHaveVarImagesFlag){

			$dpiChanged = false;
			for($i=0; $i < sizeof($artworkInfoObj->SideItemsArray); $i++){

				if($artworkInfoObj->SideItemsArray[$i]->dpi > 200){
					$artworkInfoObj->SideItemsArray[$i]->dpi = 200;
					$dpiChanged = true;
				}
			}
			// If the DPI changed then safe the new artwork file to the database.
			if($dpiChanged){
				$projectObj->setArtworkFile($artworkInfoObj->GetXMLdoc(), false);
				$projectObj->updateDatabaseWithRawData();
				
				$artworkFile = $projectObj->getArtworkFile();
				$artworkInfoObj = new ArtworkInformation($artworkFile);
			}
		}
		
		$userLoggedIn = false;
		
		// If we are in Proof mode, then we should check against Variable Images on the user's behalf
		// Otherwise we need to check if the user is logged in
		
		if($viewType == "proof" || $viewType == "admin"){
		
			$customerUserID = Order::GetUserIDFromOrder($dbCmd, $projectObj->getOrderID());
			$variableImageObj = new VariableImages($dbCmd, $customerUserID);
			$userLoggedIn = true;
			
			// To avoid a compiler warning that the variable was not used???
			if($userLoggedIn)
				$userLoggedIn = $userLoggedIn;
			else
				$userLoggedIn = $userLoggedIn;
		}
		else{

			// If this project is supposed to have variable images... we need to check and make sure that images exist within the DB
			// Variable Images belong to a particular user, we must ensure that they are logged in
			$passiveAuthObj = Authenticate::getPassiveAuthObject();

			if($passiveAuthObj->CheckIfLoggedIn()){
				// We also might have a Saved Project Override happening... so the UserID could be different than the person actually logged in.
				$variableImageObj = new VariableImages($dbCmd, ProjectSaved::GetSavedProjectOverrideUserID($passiveAuthObj));		
				$userLoggedIn = true;
			}
		}

		
		if(!$varDataObj->loadDataByTextFile($projectObj->getVariableDataFile())){
			$projectObj->setVariableDataStatus("D"); // Data Error
			$projectObj->setVariableDataMessage($varDataObj->getErrorMessageShort());
		}
		else if($artworkVarsObj->checkForMappingErrors()){
			$projectObj->setVariableDataStatus("A"); // Artwork Error
			$projectObj->setVariableDataMessage("You need to configure variables within your Artwork File.");
		}
		else if($supposedToHaveVarImagesFlag && !$artworkVarsObj->checkForVariableImageTag()){
			$projectObj->setVariableDataStatus("A"); // Artwork Error
			$projectObj->setVariableDataMessage("This project needs to have variable images configured.  Start out by placing a special image variable within the artwork like \"{IMAGE:CustomPhoto}\".");
		}
		else if($supposedToHaveVarImagesFlag && !$userLoggedIn){
			$projectObj->setVariableDataStatus("L"); // Login Error
			$projectObj->setVariableDataMessage("You must be signed in before you can configure variable images.  Please sign in or register for an account.");
		}
		else if($supposedToHaveVarImagesFlag && $variableImageObj->checkForVariableImageErrors($artworkVarsObj, $varDataObj)){
			$projectObj->setVariableDataStatus("I"); // Variable Image Error
			$projectObj->setVariableDataMessage($variableImageObj->getErrorMessage());
		}
		else if($supposedToHaveVarImagesFlag && $variableImageObj->checkForVariableImageSizeAndPositionErrors($artworkInfoObj, $artworkVarsObj, $varDataObj)){
			$projectObj->setVariableDataStatus("I"); // Variable Image Error
			$projectObj->setVariableDataMessage($variableImageObj->getErrorMessage());
		}
		else{
			$projectObj->setVariableDataStatus("G"); // Good
			$projectObj->setVariableDataMessage("");
		}
		
		if($viewType == "proof" || $viewType == "admin" || $viewType == "customerservice"){

			// If the Variable Data status is not 'G'ood or 'W'arning, then is must mean there is some kind of error
			// Change the status on the entire project to 'V'ariable Data Status Error.
			if($projectObj->getVariableDataStatus() != "G" && $projectObj->getVariableDataStatus() != "W"){
				
				// Make sure that we do not change a 'F'inished or 'C'anceled Order back to Open
				if($projectObj->getStatusChar() != "F" && $projectObj->getStatusChar() != "C")
					$projectObj->setStatusChar("V");
				
			}
			else{
			
				// If it does have 'G'ood or 'W'arning then we should analyze if the status previously had a 'V'ariable data problem
				// then we can put it back to new... so they can proof it.
				if($projectObj->getStatusChar() == "V")
					$projectObj->setStatusChar("N");
			}
		}
		
		$projectObj->updateDatabase();
		
	}
	
	
	// Static Function
	// Will return the Variable name of that is inside of the Text layer
	// For example... at text layer may have inside of it something like {IMAGE:HeadShot} (Aligntment:Right) (Valign:Middle) (MaxHeight:343)
	// This method will just return "IMAGE:HeadShot"
	// If a variable image does not exist... this function will fail critically
	function getVariableNameOfVariableImage($textStringFromLayer){

		$m = array();
		if(preg_match("/{(IMAGE:(\w|\d)+)}/i", $textStringFromLayer, $m))
			return $m[1];
		else
			throw new Exception("Error in method call getVariableNameOfVariableImage.  A Variable Image does not exist.");
	}


	
	// Pass in Artwork Object by reference
	// It will remove any text layers which contain a Variable inside
	// Text layers indicating a Variable Image are also Removed
	function removeVariableLayersFromArtwork(&$artworkInfoObj){
	
	
		$sideCounter = 0;
		foreach($artworkInfoObj->SideItemsArray as $thisSideObj){
			foreach($thisSideObj->layers as $thisLayerObj){
				if($thisLayerObj->LayerType == "text"){
					if(preg_match("/{\w+}/", $thisLayerObj->LayerDetailsObj->message))
						$artworkInfoObj->RemoveLayerFromArtworkObj($sideCounter, $thisLayerObj->level);
						
					if(preg_match("/{IMAGE:(\w|\d)+}/", $thisLayerObj->LayerDetailsObj->message))
						$artworkInfoObj->RemoveLayerFromArtworkObj($sideCounter, $thisLayerObj->level);
				}
			}
			$sideCounter++;
		}

		$artworkInfoObj->unsetDeletedLayers();
	}
	

	// Pass in Artwork Object by reference
	// It will remove any text layers that is not an indicator (place holder for variable images)
	// Removes all Image Layers too
	function removeAnyLayerNotForVariableImages(&$artworkInfoObj){
	
	
		$sideCounter = 0;
		foreach($artworkInfoObj->SideItemsArray as $thisSideObj){
			foreach($thisSideObj->layers as $thisLayerObj){
				if($thisLayerObj->LayerType == "text"){
					if(!preg_match("/{IMAGE:(\w|\d)+}/i", $thisLayerObj->LayerDetailsObj->message))
						$artworkInfoObj->RemoveLayerFromArtworkObj($sideCounter, $thisLayerObj->level);
				}
				else if($thisLayerObj->LayerType == "graphic"){
					$artworkInfoObj->RemoveLayerFromArtworkObj($sideCounter, $thisLayerObj->level);
				}
			}
			$sideCounter++;
		}
		
		$artworkInfoObj->unsetDeletedLayers();

	}

	
	// Pass in Artwork Object by reference
	// Only keeps text layers which have variables in them... deletes all other text layers
	// Text layers indicating a Variable Image are also Removed
	function removeNonVariableLayersFromArtwork(&$artworkInfoObj){	
	
		$sideCounter = 0;
		foreach($artworkInfoObj->SideItemsArray as $thisSideObj){
			foreach($thisSideObj->layers as $thisLayerObj){
				if($thisLayerObj->LayerType == "text"){
					if(!preg_match("/{\w+}/", $thisLayerObj->LayerDetailsObj->message))
						$artworkInfoObj->RemoveLayerFromArtworkObj($sideCounter, $thisLayerObj->level);
				}
			}
			$sideCounter++;
		}
		
		$artworkInfoObj->unsetDeletedLayers();
	}
	


	
	
}
<?

// Will hold positioning information of Project Artworks within the Sub-Profiles.
class GangGrouping {

	private $_rows;
	private $_columns;
	private $_totalCount;
	private $_pdfProfileObj;
	
	// If we are in Preview Mode, then the Project ID's are fake
	// We want to generate a PDF preview for admins configuring Super PDF profiles.
	private $_previewModeFlag = false;
	
	// The key is the projectID, the value is the count of spaces.
	private $_projectIDarr = array();

	private $optionLimiter;
	

	##--------------   Constructor  ------------------##

	// Option Limiter is the "needle"... and the actual Project Options are the Haystack.
	// An example of an Option Limiter could be "Glossy".  PDF profiles also take Product Options as a parameter to draw additional shapes.   
	function GangGrouping(DbCmd $dbCmd, $pdfProfileID, $optionLimiter){

		$productIDofProfile = PDFprofile::getProductIDfromProfileID($dbCmd, $pdfProfileID);

		$this->optionLimiter = $optionLimiter;
		
		if(empty($optionLimiter))
			$optionLimiter = "ALL";
		$this->_pdfProfileObj = new PDFprofile($dbCmd, $productIDofProfile, $optionLimiter);
		
		$this->_pdfProfileObj->loadProfileByID($pdfProfileID);
		
		$this->_rows = $this->_pdfProfileObj->getRows();
		$this->_columns = $this->_pdfProfileObj->getColumns();
		$this->_totalCount = $this->_rows * $this->_columns;
	}

	
	function getPDFprofileName(){
	
		return $this->_pdfProfileObj->getProfileNameLoaded();
	}
	
	function setPreviewMode($flag){
		if(!is_bool($flag))
			throw new Exception("The parmaeter is not boolean.");
		$this->_previewModeFlag = $flag;
	}
	function getPreviewMode(){
		return $this->_previewModeFlag;
	}
	
	function getOptionLimiter(){
		return $this->optionLimiter;
	}
	
	function getProductID(){
		return $this->_pdfProfileObj->getProductID();
	}
	
	
	
	
	// If there is a matrix Object defined then it will try and sort the projects so that they are not split between rows/columns.
	// This is good to keep similar jobs in the same row to color consistency and/or slitting machine organization.
	function reOrganizeSequenceForRowColBreaks(){
	
		$matrixObj = $this->_pdfProfileObj->getCoverSheetMatrix();
		
		if(!$matrixObj)
			return;
	
		// Sort ahead of time to ensure consistent results.
		ksort($this->_projectIDarr);
	
		// Sort the projects so that the projects with the most slots are shown first.
		// Serialize and unserialize to be sure that they are true copies... and not pointers to the same array.
		$projectArrCopy = unserialize(serialize($this->_projectIDarr));
		$projectArrCopyTwo = unserialize(serialize($this->_projectIDarr));
		
		arsort($projectArrCopy, SORT_NUMERIC);
		arsort($projectArrCopyTwo, SORT_NUMERIC);
		
		
		$currentPositionCounter = 1;
		
		// Will contain the sequence of ProjectID's that we want to order by after figuring out how to optimally fill the slots.
		$newProjectIDsequenceArr = array();
		
		// Keep track of what ProjectID's were taken out of sequence (so we don't include them twice.
		$projectIDsSkippedAheadArr = array();
		
		
		foreach($projectArrCopy as $thisPid => $projectCount){
		
			// Find out if we already used this ProjectID to fill the Gap for another 
			if(in_array($thisPid, $projectIDsSkippedAheadArr))
				continue;
		
			$matrixRowNum = $matrixObj->getRowNumberFromValue($currentPositionCounter);
			$matrixColNum = $matrixObj->getColumnNumberFromValue($currentPositionCounter);
			
			
			$sequencedSpacesAhead = $matrixObj->getSequencedPositionsAhead($matrixRowNum, $matrixColNum);
			
			// Add 1 because the sequenced spaces ahead does not include the current Slot we are on.
			if($projectCount <= ($sequencedSpacesAhead + 1)){
				
				$newProjectIDsequenceArr[] = $thisPid;	
			}
			else{
		
				// Let's find out if there are any other projects to fill the Gap until the next row or column break.
				// That way the larger project can fit on its own line.
				$spacesLeftToFill = $sequencedSpacesAhead + 1;
				
				foreach($projectArrCopyTwo as $projectIDtoFill => $projectIDspacesTaken){

					if(in_array($projectIDtoFill, $newProjectIDsequenceArr))
						continue;
				
					if($spacesLeftToFill == 0)
						break;
						
					if($projectIDspacesTaken <= $spacesLeftToFill){

						$newProjectIDsequenceArr[] = $projectIDtoFill;
						
						$currentPositionCounter += $projectIDspacesTaken;
						
						$spacesLeftToFill -= $projectIDspacesTaken;
						
						$projectIDsSkippedAheadArr[] = $projectIDtoFill;
					
					}
				}
			
				// Maybe some projects we added before (and maybe not).  We have to include this project regardless upon this loop.
				$newProjectIDsequenceArr[] = $thisPid;
			}
			

			$currentPositionCounter += $projectCount;
		}
		
		
		// Now that we have reorganized the order in which we want the Projects added to this Grouping Object.  Replace the original array.
		$tempArr = array();
		foreach($newProjectIDsequenceArr as $thisProjectID)
			$tempArr[$thisProjectID] = $this->_projectIDarr[$thisProjectID];
		
		$this->_projectIDarr = $tempArr;
	
	}
	
	
	
	// If there are a total of 9 slots (3x3)... this will tell you what project ID belongs at the value 1-9.
	// Returns Zero if there is no project defined there.
	function getProjectIDfromNumber($slotNum){
	
		if($slotNum > $this->_totalCount)
			throw new Exception("Error in method GangGrouping->getProjectIDfromNumber. the slot number is out of range.");
	
		if($slotNum > $this->_getUsedSpaces())
			return 0;
		
		$counter = 0;
		
		foreach($this->_projectIDarr as $thisPid => $spaces){
			
			$counter += $spaces;
			
			if($slotNum <= $counter)
				return $thisPid;	
		}

		
		throw new Exception("An error occured in method GangGrouping->getProjectIDfromNumber.");
	}
	
	
	// Returns a list of all ProjectID's stored in this object.

	function getProjectIDarr(){
	
		return array_keys($this->_projectIDarr);
	}
	
	
	function getProjectCount(){
		return sizeof($this->_projectIDarr);
	}
	
	function getSpacesUsedByProjectID($projectID){
		
		if(!isset($this->_projectIDarr[$projectID]))
			return 0;
		else
			return $this->_projectIDarr[$projectID];
	
	}
	
	

	// Returns Zero if there is no project defined there.
	// $row and $col should be 1-based.
	function getProjectIDfromRowColumn($row, $col){

		if($row > $this->_rows || $row < 1)
			throw new Exception("Error in method GangGrouping->getProjectIDfromRowColumn.  The row value is out of place.");
		if($col > $this->_columns || $col < 1)
			throw new Exception("Error in method GangGrouping->getProjectIDfromRowColumn.  The row value is out of place.");
		
		// Reverse the order of rows... since PDF's plot from the bottom left.  we want to go from the top-right.
		$row = $this->_rows + 1 - $row;
		
		// Mix up the seqeunce to match our matrix order.
		$matrixObj = $this->_pdfProfileObj->getCoverSheetMatrix();
		if($matrixObj)
			$position = $matrixObj->getMatrixOrderValueAt($row, $col);
		else
			$position = ($row - 1) * $this->_columns + $col;	
		
		return $this->getProjectIDfromNumber($position);
	}
	
	// If there are 2 columns and 4 rows, then this will returna  number between 1 & 8.
	function getPositionNumberFromMatrix($row, $col){
		
		// Reverse the order of rows... since PDF's plot from the bottom left.  we want to go from the top-right.
		$row = $this->_rows + 1 - $row;
		
		$matrixObj = $this->_pdfProfileObj->getCoverSheetMatrix();
		
		if($matrixObj)
			return $matrixObj->getMatrixOrderValueAt($row, $col);
		else
			return ($row - 1) * $this->_columns + $col;
	
	}
	
	
	// Project ID's can take up more than 1 space.
	// Sometimes you want to know if the Project is the first in the group.
	// For example:  If projectID takes three spots 2,3,4 ... this method will return false for 3 and 4, but true for 2.
	function checkIfProjectIsFirst($row, $col){

		$countOfProjectOccupyBefore = $this->getCountOfProjectOccupyBeforePosition($row, $col);
		
		if($countOfProjectOccupyBefore == 1)
			return true;
		else
			return false;
	}
	
	
	// Based upon a matrix order... it will tell us the project sequence order.
	// For example... if a project takes up 4 spaces... and we are on 3 of 4 ... This project will return 3... because 2 other spaces exist before this one.
	// If there is no space occupied at this position... then it will return 0.
	function getCountOfProjectOccupyBeforePosition($row, $col){
	
		
		$projectIDAtPosition = $this->getProjectIDfromRowColumn($row, $col);
		$valueForProjectID = $this->getPositionNumberFromMatrix($row, $col);
		
		if($projectIDAtPosition == 0)
			return false;
			
		$countBefore = 0;
	
		for($i=1; $i<= $this->_rows; $i++){
			for($j=1; $j <= $this->_columns; $j++){
			
				$thisPid = $this->getProjectIDfromRowColumn($i, $j);
				
				if($thisPid == $projectIDAtPosition){
					$valueAtPosition = $this->getPositionNumberFromMatrix($i, $j);
					
					if($valueAtPosition <= $valueForProjectID)
						$countBefore++;
				}
			}
		}
		
		return $countBefore;
	}
	
	
	
	
	// Add a ProjectID to this Object and pass in how many spaces it should occupy.

	function addProject($projectID, $spaces){
	
		if($spaces > $this->getFreeSpaces())
			throw new Exception("Error in method GangGrouping->addProject ... not enough free spaces.");
			
		$projectArr = array_keys($this->_projectIDarr);
		
		if(in_array($projectID, $projectArr))
			throw new Exception("Error in method GangGrouping->addProject ... The project ID already exists in this Group.");
		
		$this->_projectIDarr[$projectID] = $spaces;
		
	}
	
	
	function getFreeSpaces(){
		
		return $this->_totalCount - $this->_getUsedSpaces();
	}
	
	
	function _getUsedSpaces(){
	
		$usedSpaces = 0;
		
		
		foreach($this->_projectIDarr as $x)
			$usedSpaces += $x;
		
		return $usedSpaces;
	}
	
	
	// If we are in Preview Mode, then this will return the default Product Artwork... 
	// ... plus some text layers to assist with debugging the Super Profile Layout.
	function getPreviewArtworkForProduct(){
		
		if(!$this->_previewModeFlag)
			throw new Exception("Error in method getPreviewArtworkForProduct. You have to be in Preview Mode to get the Artwork");
		
		$dbCmd = new DbCmd();
		$productObj = new Product($dbCmd, $this->getProductID(), false);
		$defaultArtworkObj = new ArtworkInformation($productObj->getDefaultProductArtwork());
		
		// Put the GangGrouping Preview Message in the center (0,0 is the center point).
		$layerItemObj = new LayerItem();
		$layerItemObj->LayerType = "text";
		$layerItemObj->level = 5000;
		$layerItemObj->rotation = 0;
		$layerItemObj->x_coordinate = 0;
		$layerItemObj->y_coordinate = 0;
		
		$textLayerObj = new TextItem();
		$textLayerObj->align = "center";
		$textLayerObj->font = "Decker";
		$textLayerObj->size = "20";
		$textLayerObj->color = "#000000";
		$textLayerObj->field_order = 1;
		$textLayerObj->message = $productObj->getProductTitleWithExtention();
		$layerItemObj->LayerDetailsObj = $textLayerObj;
		$defaultArtworkObj->AddLayerObjectToSide(0, $layerItemObj);
		
		$optionLimiter = $this->getOptionLimiter();
		if(!empty($optionLimiter)){
			
			$layer2Obj = new LayerItem();
			$layer2Obj->LayerType = "text";
			$layer2Obj->level = 5001;
			$layer2Obj->rotation = 0;
			$layer2Obj->x_coordinate = 0;
			$layer2Obj->y_coordinate = 18;
		
			$text2Obj = new TextItem();
			$text2Obj->align = "center";
			$text2Obj->font = "Decker";
			$text2Obj->size = "25";
			$text2Obj->color = "#669933";
			$text2Obj->field_order = 1;
			$text2Obj->message = $optionLimiter;
			$layer2Obj->LayerDetailsObj = $text2Obj;
			$defaultArtworkObj->AddLayerObjectToSide(0, $layer2Obj);
		}

		return $defaultArtworkObj->GetXMLdoc();
	}
	

}

?>
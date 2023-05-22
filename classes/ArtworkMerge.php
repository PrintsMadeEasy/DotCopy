<?

// Will take one artwork and place it on top of another.
// Returned artwork maintains the DPI Side Descriptions, etc of the Bottom artwork.
// Does not remove any sides from the bottom artwork.
// If there are multiple sides on the Bottom artwork it will try to copy all of matching sides from the Top
// If side number from the top artwork does not match one on the bottom then it will not be copied.
// ... However, you can override this by manually specifiying a target Side number for the bottom and the top.
// This will not convert any dimensions... therfore if the aspect ratios are different, it is up to you to run it through the ArtworkConversion class first.
// ... However, you can use this to adjust the height/width scaling off all layers on the side... and optionally specify and Offset of X/Y coordinates from the center
class ArtworkMerge {

	private $_topArtObj;
	private $_bottomArtObj;
	
	private $_bottomArtworkInitialized;
	private $_topArtworkInitialized;
	
	private $_removeBacksideFlag;
	
	private $_targetSideNumbersSetFlag;
	private $_targetSideNumberBottom;
	private $_targetSideNumberTop;
	
	private $_bottomArtWidthChange;
	private $_bottomArtHeightChange;
	private $_topArtWidthChange;
	private $_topArtHeightChange;
	
	private $_bottomArtYcoordOffset;
	private $_bottomArtXcoordOffset;
	private $_topArtYcoordOffset;
	private $_topArtXcoordOffset;
	
	private $_stretchTemplateImagesFlag;
	private $_stretchUserImagesFlag;
	
	private $_artworkConversionObj;
	
	private $_dbCmd;



	//  Constructor 
	function ArtworkMerge(DbCmd $dbCmd){

		$this->_bottomArtworkInitialized = false;
		$this->_topArtworkInitialized = false;
		
		$this->_targetSideNumbersSetFlag = false;
		
		$this->_artworkConversionObj = new ArtworkConversion($dbCmd);
		
		// We are not going to convert dimensions... so just make everythig proportional (unless we override it).
		$this->_artworkConversionObj->setScalePercentages(1, 1, 1, 1);
		$this->_artworkConversionObj->dontScaleArtworkAutomatically(true);
		
		$this->_bottomArtWidthChange = 0;
		$this->_bottomArtHeightChange = 0;
		$this->_topArtWidthChange = 0;
		$this->_topArtHeightChange = 0;
		
		$this->_bottomArtYcoordOffset = 0;
		$this->_bottomArtXcoordOffset = 0;
		$this->_topArtYcoordOffset = 0;
		$this->_topArtXcoordOffset = 0;

		$this->_stretchTemplateImagesFlag = true;
		$this->_stretchUserImagesFlag = false;
		
		$this->_removeBacksideFlag = false;
		
		$this->_dbCmd = $dbCmd;
		
	}
	


	function setBottomArtwork($artworkFile){
		
		$this->_bottomArtObj = new ArtworkInformation($artworkFile);
		$this->_bottomArtworkInitialized = true;
		
	}
	
	
	function setTopArtwork($artworkFile){
		
		$this->_topArtObj = new ArtworkInformation($artworkFile);
		$this->_topArtworkInitialized = true;
		
	}
	
	
	// optional to call this method.
	// Pass in a value to change the width or height.... 0 means no change to the defaults.
	// You can pass in positive or negative integers... For example... "-10", "10" will shrink the width by ten percent and increase the height by 10 percent.
	// If you CHANGE the Bottom Artwork Proportions..... IT WILL NOT Affect the size of the Canvas area... the resulting Canvas after merging 2 artworks will always be the size of the original Bottom canvas

	// .., change the Artwork proportions will simply affect the layer placements (from the Center of the canvas).
	function changeBottomArtworkProportions($widthPercent, $heightPercent){
	
		if(!preg_match("/^-?\d+$/", $widthPercent) || !preg_match("/^-?\d+$/", $heightPercent))
			throw new Exception("Illegal Width or Height percentage change in ArtworkMerge->changeBottomArtworkProportions");
	
		if($widthPercent < -95 || $heightPercent < -95)
			throw new Exception("Error in method changeBottomArtworkProportions... you can not change the bottom proportions less than Negative 95 percent or it would start to becom invisible.");
	
		$this->_bottomArtWidthChange = $widthPercent;
		$this->_bottomArtHeightChange = $heightPercent;
	}
	
	function changeTopArtworkProportions($widthPercent, $heightPercent){
	
		if(!preg_match("/^-?\d+$/", $widthPercent) || !preg_match("/^-?\d+$/", $heightPercent))
			throw new Exception("Illegal Width or Height percentage change in ArtworkMerge->changeTopArtworkProportions");
	
		if($widthPercent < -95 || $heightPercent < -95)
			throw new Exception("Error in method changeTopArtworkProportions... you can not change the bottom proportions less than Negative 95 percent or it would start to becom invisible.");
	
		$this->_topArtWidthChange = $widthPercent;
		$this->_topArtHeightChange = $heightPercent;
	}
	
	
	function offsetBottomArtworkCoords($xCoord, $yCoord){
	
		if(!preg_match("/^-?\d+$/", $xCoord) || !preg_match("/^-?\d+$/", $yCoord))
			throw new Exception("Illegal X or Y coordinate Offset in ArtworkMerge->offsetBottomArtworkCoords");
	
		$this->_bottomArtYcoordOffset = $yCoord;
		$this->_bottomArtXcoordOffset = $xCoord;
	}
	
	function offsetTopArtworkCoords($xCoord, $yCoord){
	
		if(!preg_match("/^-?\d+$/", $xCoord) || !preg_match("/^-?\d+$/", $yCoord))
			throw new Exception("Illegal X or Y coordinate Offset in ArtworkMerge->offsetTopArtworkCoords");
	
		$this->_topArtYcoordOffset = $yCoord;
		$this->_topArtXcoordOffset = $xCoord;
	}
	
	
	// You can manually set target Side Numbers between the Top and the Bottom artworks
	// If the side numbers don't exist then an error will happen.
	// The remaining bottom artwork Sides (if Any) will not be changed.
	function setSideTargets($bottomArtworkSideNumber, $topArtworkSideNumber){
		
		$this->_ensureArtworksHaveBeenSet();
		
		if(!preg_match("/^\d+$/", $bottomArtworkSideNumber))
			throw new Exception("Error in ArtworkMerge->setSideTargets, bottom not an integer");
			
		if(!preg_match("/^\d+$/", $topArtworkSideNumber))
			throw new Exception("Error in ArtworkMerge->setSideTargets, top not an integer");
			
		if(!isset($this->_bottomArtObj->SideItemsArray[$bottomArtworkSideNumber]))
			throw new Exception("Error in ArtworkMerge->setSideTargets, Target Side number is not defined for the Bottom Artwork");

		if(!isset($this->_topArtObj->SideItemsArray[$topArtworkSideNumber]))
			throw new Exception("Error in ArtworkMerge->setSideTargets, Target Side number is not defined for the Top Artwork");
		
		$this->_targetSideNumberBottom = $bottomArtworkSideNumber;
		$this->_targetSideNumberTop = $topArtworkSideNumber;
		$this->_targetSideNumbersSetFlag = true;
	}


	
	function _ensureArtworksHaveBeenSet(){
		if(!$this->_bottomArtworkInitialized || !$this->_topArtworkInitialized)
			throw new Exception("You must set both the Top Artwork and the Bottom Artwork in the Class ArtworkMerge");
	}
	
	// By Default it will NOT remove the backside during a conversion.
	// Set this flag to true if you want to ensure the Final Artwork only has one side.  
	function removeBacksideFlag($x){		
		$this->_removeBacksideFlag = $x;
	}
	
	
	// See the "ArtworkConversion" Class for definitions on what these methods do.
	function stretchTemplateImages($x){		
		$this->_stretchTemplateImagesFlag = $x;
		
		$this->_artworkConversionObj->stretchTemplateImages($x);
	}
	function stretchUserImages($x){		
		$this->_stretchUserImagesFlag = $x;
		
		$this->_artworkConversionObj->stretchUserImages($x);
	}


	function getMergedArtwork(){
	
		$this->_ensureArtworksHaveBeenSet();
		
		$bottomArtworkCopyXML = $this->_bottomArtObj->GetXMLdoc();
		$topArtworkCopyXML = $this->_topArtObj->GetXMLdoc();
		
		
		// If the Artwork Proportions are supposed to be changed... do on each artwork before the actual merge occurs.
		if($this->_bottomArtWidthChange || $this->_bottomArtHeightChange){
		
			$artConvertObj = new ArtworkConversion($this->_dbCmd);
		
			$artConvertObj->setScalePercentages(100, 100, (100 + $this->_bottomArtWidthChange), (100 + $this->_bottomArtHeightChange));
			$artConvertObj->dontScaleArtworkAutomatically(true);
			$artConvertObj->stretchTemplateImages($this->_stretchTemplateImagesFlag);
			$artConvertObj->stretchUserImages($this->_stretchUserImagesFlag);
			
			// The "From artwork" is our original... and the "to artwork" is the original (but with all of the layers removed)
			$artConvertObj->setFromArtwork(0, $bottomArtworkCopyXML);
			
			// Create a blank "to artwork"
			$blankBottomArtworkObj = new ArtworkInformation($bottomArtworkCopyXML);
			
			for($i=0; $i<sizeof($blankBottomArtworkObj->SideItemsArray); $i++)
				$blankBottomArtworkObj->SideItemsArray[$i]->layers = array();
			
			$artConvertObj->setToArtwork(0, $blankBottomArtworkObj->GetXMLdoc());
			
			$bottomArtworkCopyXML = $artConvertObj->getConvertedArtwork();
		}
				

		// If the Artwork Proportions are supposed to be changed... do on each artwork before the actual merge occurs.
		if($this->_topArtWidthChange || $this->_topArtHeightChange){
		
			$artConvertObj = new ArtworkConversion($this->_dbCmd);
		
			$artConvertObj->setScalePercentages(100, 100, (100 + $this->_topArtWidthChange), (100 + $this->_topArtHeightChange));
			$artConvertObj->dontScaleArtworkAutomatically(true);
			$artConvertObj->stretchTemplateImages($this->_stretchTemplateImagesFlag);
			$artConvertObj->stretchUserImages($this->_stretchUserImagesFlag);
			
			// The "From artwork" is our original... and the "to artwork" is the original (but with all of the layers removed)
			$artConvertObj->setFromArtwork(0, $topArtworkCopyXML);
			
			// Create a blank "to artwork"
			$blankTopArtworkObj = new ArtworkInformation($topArtworkCopyXML);
			
			for($i=0; $i<sizeof($blankTopArtworkObj->SideItemsArray); $i++)
				$blankTopArtworkObj->SideItemsArray[$i]->layers = array();
			
			$artConvertObj->setToArtwork(0, $blankTopArtworkObj->GetXMLdoc());
			
			$topArtworkCopyXML = $artConvertObj->getConvertedArtwork();
		}
		
	

		$bottomArtworkCopyObj = new ArtworkInformation($bottomArtworkCopyXML);
		$topArtworkCopyObj = new ArtworkInformation($topArtworkCopyXML);

		
		if($this->_bottomArtYcoordOffset || $this->_bottomArtXcoordOffset){
			
			for($i=0; $i<sizeof($bottomArtworkCopyObj->SideItemsArray); $i++){
				
				for($j=0; $j<sizeof($bottomArtworkCopyObj->SideItemsArray[$i]->layers); $j++){
					$bottomArtworkCopyObj->SideItemsArray[$i]->layers[$j]->x_coordinate += $this->_bottomArtXcoordOffset;
					$bottomArtworkCopyObj->SideItemsArray[$i]->layers[$j]->y_coordinate += $this->_bottomArtYcoordOffset;
				}
			}
		
		}
		

		if($this->_topArtYcoordOffset || $this->_topArtXcoordOffset){
			
			for($i=0; $i<sizeof($topArtworkCopyObj->SideItemsArray); $i++){
				
				for($j=0; $j<sizeof($topArtworkCopyObj->SideItemsArray[$i]->layers); $j++){
					$topArtworkCopyObj->SideItemsArray[$i]->layers[$j]->x_coordinate += $this->_topArtXcoordOffset;
					$topArtworkCopyObj->SideItemsArray[$i]->layers[$j]->y_coordinate += $this->_topArtYcoordOffset;
				}
			}
		}
		



		
		$retXML = "";
		
		// If we have explicity set Target Side numbers then we want to check that they still exist...... maybe the artwork was changed.
		if($this->_targetSideNumbersSetFlag){

			if(!isset($topArtworkCopyObj->SideItemsArray[$this->_targetSideNumberTop]) || !isset($bottomArtworkCopyObj->SideItemsArray[$this->_targetSideNumberBottom]) )
				throw new Exception("Error in method ArtworkMerge->getMergedArtwork.  The side number is not defined. Maybe you changed the artwork after setting the Target Side Numbers?");
	
	
			// Erase all sides and copy back the one that we want.
			$bottomArtworkTargetSide = $bottomArtworkCopyObj->SideItemsArray[$this->_targetSideNumberBottom];
			$bottomArtworkCopyObj->SideItemsArray = array();
			$bottomArtworkCopyObj->SideItemsArray[0] = $bottomArtworkTargetSide;
		
			$this->_artworkConversionObj->setToArtwork(0, $bottomArtworkCopyObj->GetXMLdoc());
			
				
			// Erase all sides and copy back the one that we want.
			$topArtworkTargetSide = $topArtworkCopyObj->SideItemsArray[$this->_targetSideNumberTop];
			$topArtworkCopyObj->SideItemsArray = array();
			$topArtworkCopyObj->SideItemsArray[0] = $topArtworkTargetSide;
		
			$this->_artworkConversionObj->setFromArtwork(0, $topArtworkCopyObj->GetXMLdoc());
		
			
			$mergedSidesXML = $this->_artworkConversionObj->getConvertedArtwork();
			
			$megedArtworkObj = new ArtworkInformation($mergedSidesXML);
			
			
			// Create another copy of the Bottom Artwork Object
			$anotherBottomArtworkObj = unserialize(serialize($this->_bottomArtObj));
			
			// Replace the side on our bottom artwork "target side" with our Merged Object (always has just one side).
			$anotherBottomArtworkObj->SideItemsArray[$this->_targetSideNumberBottom] = $megedArtworkObj->SideItemsArray[0];
		
			$retXML = $anotherBottomArtworkObj->GetXMLdoc();

		}
		else{
		
			$this->_artworkConversionObj->setToArtwork(0, $bottomArtworkCopyObj->GetXMLdoc());
			$this->_artworkConversionObj->setFromArtwork(0, $topArtworkCopyObj->GetXMLdoc());
			
			$retXML = $this->_artworkConversionObj->getConvertedArtwork();
		}
		
			
		
		// If we have explicity set Target Side numbers then put the From Artwork Back to any original Sides (not matching our target number).
		// We could have altered the layers on "untargeted sides" if we had offset X/Y coordinates or changed the proporations
		if($this->_targetSideNumbersSetFlag && sizeof($this->_bottomArtObj->SideItemsArray) > 1){
		
			$returnArtworkObj = new ArtworkInformation($retXML);
			
			for($i=0; $i<sizeof($returnArtworkObj->SideItemsArray); $i++){
			
				if($i != $this->_targetSideNumberBottom)
					$returnArtworkObj->SideItemsArray[$i] = $this->_bottomArtObj->SideItemsArray[$i];
			}
			
			$retXML = $returnArtworkObj->GetXMLdoc();
		}
		
		
		// Remove backsides (if any)
		if($this->_removeBacksideFlag){
			$returnArtworkObj = new ArtworkInformation($retXML);
			
			if(isset($returnArtworkObj->SideItemsArray[1]))
				unset($returnArtworkObj->SideItemsArray[1]);
			
			$retXML = $returnArtworkObj->GetXMLdoc();
		}


		return $retXML;

	}
	

	
}

?>

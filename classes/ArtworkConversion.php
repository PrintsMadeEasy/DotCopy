<?

// This Class takes care converting Artwork from 1 product into the artwork for another
class ArtworkConversion {

	private $_dbCmd;
	private $_dpiSetting;
	private $_removeOutOfBoundLayersFlag;
	private $_removeBacksideFlag;
	private $_copyOnlySideNumbers = array();
	private $_stretchTemplateImagesFlag;
	private $_stretchUserImagesFlag;

	private $_sweetSpotCanvasWidth_From;
	private $_sweetSpotCanvasHeight_From;
	private $_sweetSpotXcoord_From;
	private $_sweetSpotYcoord_From;
	

	private $_sweetSpotCanvasWidth_To;
	private $_sweetSpotCanvasHeight_To;
	private $_sweetSpotXcoord_To;
	private $_sweetSpotYcoord_To;
	

	private $_toArtObj;
	private $_fromArtObj;
	
	private $_fromArtworkInitialized;
	private $_toArtworkInitialized;
	
	private $_proportionalScale;
	private $_widthScale;
	private $_heightScale;
	
	private $_doNotAttemptToScaleArtworkAutoFlag;
	
	private $_scalingParametersIntialized;
	
	private $_productSweetSpots = array();
	

	//  Constructor 
	function ArtworkConversion(DbCmd $dbCmd){

		$this->_dbCmd = $dbCmd;
		
		$this->_removeOutOfBoundLayersFlag = false;
		
		$this->_stretchTemplateImagesFlag = true;
		$this->_stretchUserImagesFlag = false;
		
		$this->_fromArtworkInitialized = false;
		$this->_toArtworkInitialized = false;
		$this->_scalingParametersIntialized = false;
		$this->_doNotAttemptToScaleArtworkAutoFlag = false;

		// By default we do not want to remove the backside on the artwork we are copying to.
		$this->_removeBacksideFlag = false;

	}
	
	

	// Pass in an Artwork File you want to transfer Artwork From.
	// Leave out the second parameter if you want to use a blank template for the product
	// Using the default (blank template) can be useful if you are only interested in using the "Scaling" and will transfer layers using another function
	// The ProductID is optional if you are passing in an Artwork File (and don't care about Sweet Spots)... in this case just set the Product ID to 0.
	function setFromArtwork($productID, $artworkFile = ""){
	
		WebUtil::EnsureDigit($productID);
		
		if(empty($artworkFile)){
			$productObj = new Product($this->_dbCmd, $productID, false);
			$artworkFile = $productObj->getDefaultProductArtwork();
		}
		
		
		// Only set the Sweet Spots within the Artwork conversion object if the Product ID has been provided to this method and the Product itself has a sweet spot.
		if(!empty($productID)){
			
			$productObj = new Product($this->_dbCmd, $productID, false);
			
			if($productObj->checkIfArtworkHasSweetSpot()){
				$this->setSweetSpotOnFromArtwork(	round($productObj->getArtworkSweetSpotWidth() * 96), 
									round($productObj->getArtworkSweetSpotHeight() * 96), 
									round($productObj->getArtworkSweetSpotX() * 96), 
									round($productObj->getArtworkSweetSpotY() * 96));
			}
		}
		else{
			$this->setSweetSpotOnFromArtwork(0,0,0,0);
		}
		
		$this->_fromArtObj = new ArtworkInformation($artworkFile);
		$this->_fromArtworkInitialized = true;
		
	}
	
	
	// Pass in an Artwork File you want to transfer Artwork From.
	// Leave out the second parameter if you want to use a blank template for the product
	// Using the default (blank template) can be useful when creating a new Project Artwork, based on the Artwork from another Product/Project
	// If there is Layer information on the TO template it will not get erased... instead the layers from the FROM artwork will just get copied on top.
	// The ProductID is optional if you are passing in an Artwork File (and don't care about Sweet Spots)... in this case just set the Product ID to 0.
	function setToArtwork($productID, $artworkFile = ""){
		
		WebUtil::EnsureDigit($productID);
		
		if(empty($artworkFile)){
			$productObj = new Product($this->_dbCmd, $productID, false);
			$artworkFile = $productObj->getDefaultProductArtwork();
			
			// Make sure that we remove the backside on the transfer if this product only supports 1 side.
			if($productObj->getArtworkSidesCount() == 1)
				$this->_removeBacksideFlag = true;
		}
		
		// Only set the Sweet Spots within the Artwork conversion object if the Product ID has been provided to this method and the Product itself has a sweet spot.
		if(!empty($productID)){
			$productObj = Product::getProductObj($this->_dbCmd, $productID);
			
			if($productObj->checkIfArtworkHasSweetSpot()){
				$this->setSweetSpotOnToArtwork(	round($productObj->getArtworkSweetSpotWidth() * 96), 
									round($productObj->getArtworkSweetSpotHeight() * 96), 
									round($productObj->getArtworkSweetSpotX() * 96), 
									round($productObj->getArtworkSweetSpotY() * 96));
			}
		}
		else{
			$this->setSweetSpotOnToArtwork(0,0,0,0);
		}
		
		$this->_toArtObj = new ArtworkInformation($artworkFile);
		$this->_toArtworkInitialized = true;

	}
	
	// Default is there is no restriction.. it copies all sides.
	// Pass in an array of Side Number that should be transfered.
	// An empty array means to copy everything over.  array(1) means only copy the backside over
	function setSideNumberToCopy($arr){
	
		if(!is_array($arr) && preg_match("/^\d+$/", $arr))
			$arr = array($arr);
		else if(!is_array($arr))
			throw new Exception("Bad Parameter set within function call setSideNumberToCopy");
		
		$this->_copyOnlySideNumbers = array();
		
		foreach($arr as $thisSideNum){
			WebUtil::EnsureDigit($thisSideNum);
			$this->_copyOnlySideNumbers[] = $thisSideNum;
		}
	}
	
	
	// If you don't specify a DPI setting to use after the transfer, it will use whatever is in the "From Artwork"

	// By default this value is NULL... you can pass an integer into this method or a NULL value
	function setDPIafterTransfer($x){	
		WebUtil::EnsureDigit($x, false);	
		$this->_dpiSetting = $x;
	}
	

	// By Default it will NOT remove the backside during a conversion.
	// Set this flag to true if you want to ensure the Final Artwork only has one side.  
	// If there is anything on the back of the FromArtwork artwork it will be ignored.
	// If there is anything on the back of the ToArtwork then it will be deleted.
	function removeBacksideFlag($x){		
		$this->_removeBacksideFlag = $x;
	}
	
	// Set this flag to true if you want to remove any layers that fall outside of the canvas area (from the original artwork)  
	function removeOutOfBoundLayers($x){		
		$this->_removeOutOfBoundLayersFlag = $x;
	}
	
	// By default this is set to true.
	// This means that any image uploaded Through the Tempalte system will stretch out to fill the same space on the "Tranfer To" artwork
	// Most images uploaded through the template area have been put there for Background images, etc. 
	// If the first artwork is more of a square and "tranfer to" artwork is more of a rectangle, then it will distort the images to fill the background accordingly
	// If the image was not uploaded through the template system then it means the customer may have uploaded their own logo or something and we don't want to distort that.
	function stretchTemplateImages($x){		
		$this->_stretchTemplateImagesFlag = $x;
	}
	
	// By default this is set to FALSE
	// Works the same as stretchTemplateImages (except this is for user-uploaded images);
	function stretchUserImages($x){		
		$this->_stretchUserImagesFlag = $x;
	}

	
	
	// Pass in the Width/Height of Each Artwork.  This will affect how layers are transformed as they switch from one artwork to another.
	function setScalePercentages($oldWidth, $oldHeight, $newWidth, $newHeight){
	
		$this->_widthScale = $newWidth / $oldWidth;
		$this->_heightScale = $newHeight / $oldHeight;

		// If both ratios are greater than 1.. then we are definetly upsizing, if both are less than 1 then we are downsizing
		// In any case (including 1 side upsizing and the other downsizing) we take the smallest ratio
		// If one is greater than 1 and the other is less than 1, we take smaller value
		$this->_proportionalScale = ($this->_widthScale < $this->_heightScale) ? $this->_widthScale : $this->_heightScale;
		
		$this->_scalingParametersIntialized = true;
	}
	
	function dontScaleArtworkAutomatically($x){
		
		if(!is_bool($x))
			throw new Exception("Error in fucntion dontScaleArtworkAutomatically, must be boolean.");
		
		$this->_doNotAttemptToScaleArtworkAutoFlag = $x;
	}
	
	
	function _ensureArtworksHaveBeenSet(){
		if(!$this->_fromArtworkInitialized || !$this->_fromArtworkInitialized)
			throw new Exception("You must set both the From Artwork and the To Artwork in the Class ArtworkConversion");
	}
	
	
	// Will Set the Scale Percentages based upon the Artwork Files that have been set
	// Takes into account if we have Sweet spots set on the From or To Artworks.
	// Pass in a Side Number if you want to analyze the dimenions on something other than the first side
	// In general (if there is a backside) the dimensions should match the front
	// If you do pass in a Side number then that side must exist on Both of the Artworks otherwise this method will fail
	function setScalePercentagesFromArtworks($SideNumer = 0){
		$this->_ensureArtworksHaveBeenSet();
	
		if($SideNumer && !isset($this->_fromArtObj->SideItemsArray[$SideNumer]))
			throw new Exception("SideNumer: $SideNumer does not exist on the FROM artwork");
		if($SideNumer && !isset($this->_toArtObj->SideItemsArray[$SideNumer]))
			throw new Exception("SideNumer: $SideNumer does not exist on the TO artwork");
		


		// If the "From" artwork or the "To" artwork has a Sweet Spot, then use those dimensions instead of the actual canvas width/height
		if($this->checkIfSweetSpotOnFromArtwork()){
			$oldWidth = $this->_sweetSpotCanvasWidth_From;
			$oldHeight = $this->_sweetSpotCanvasHeight_From;
		}
		else{
			$oldWidth = $this->_fromArtObj->SideItemsArray[$SideNumer]->contentwidth;
			$oldHeight = $this->_fromArtObj->SideItemsArray[$SideNumer]->contentheight;
		}
	
		
		if($this->checkIfSweetSpotOnToArtwork()){
			$newWidth = $this->_sweetSpotCanvasWidth_To;
			$newHeight = $this->_sweetSpotCanvasHeight_To;
		}
		else{
			$newWidth = $this->_toArtObj->SideItemsArray[$SideNumer]->contentwidth;
			$newHeight = $this->_toArtObj->SideItemsArray[$SideNumer]->contentheight;
		}



		$this->setScalePercentages($oldWidth, $oldHeight, $newWidth, $newHeight);
	
	}
	
	// Pass in a Layer Object By Reference (Graphic or Text)
	// It will Change the Coordinates and Sizing Values according to the parameters set in this object.
	function resizeAndRepositionLayer(&$layerObj){
	
		$this->_ensureArtworksHaveBeenSet();
	
		if(!$this->_scalingParametersIntialized)
			throw new Exception("Error in method call resizeAndRepositionLayer... the Scaling Parameters have not been intialized.");


		// If the "From" artwork has a Sweet Spot, then adjust the coordinates of the layer before doing the conversion
		if($this->checkIfSweetSpotOnFromArtwork()){
			$layerObj->x_coordinate += $this->_sweetSpotXcoord_From;
			$layerObj->y_coordinate += $this->_sweetSpotYcoord_From;
		}


		// Coordinates are not proportional... If a layer was at the top-left of the FROM artwork, it should appear at the top-left on the TO artwork
		$layerObj->x_coordinate *= $this->_widthScale;
		$layerObj->y_coordinate *= $this->_heightScale;

		// Perform and Scaling and Sizing according to whether it is a Text layer or a Graphic
		if($layerObj->LayerType == "text"){

			// Do not scale Barcodes.  They could become unreadable.
			// Also if the font size is locked within the permissions, do not scale.
			if(!ArtworkInformation::CheckIfFontIsBarcode($layerObj->LayerDetailsObj->font) && !$layerObj->LayerDetailsObj->permissions->size_locked){
				$newFontSize = $layerObj->LayerDetailsObj->size * $this->_proportionalScale;
				$newFontSize = round($newFontSize, 1);

				$layerObj->LayerDetailsObj->size = $newFontSize;
			}
		}
		else if($layerObj->LayerType == "graphic"){

			// Do not change the size of a graphic if the size permissions flag has been locked.
			if(!$layerObj->LayerDetailsObj->permissions->size_locked){

				$imageID = $layerObj->LayerDetailsObj->imageid;
				
				$imageFromTemplateFlag = ImageLib::CheckIfImageBelongsToTemplateCollection($this->_dbCmd, $imageID);

				// If this Image ID was uploaded through the Template System (like a background graphic), then we want it to fill up the background on the artwork that we are copying it to.
				if(($this->_stretchTemplateImagesFlag && $imageFromTemplateFlag) || ($this->_stretchUserImagesFlag && !$imageFromTemplateFlag)){
					$layerObj->LayerDetailsObj->width *= $this->_widthScale;
					$layerObj->LayerDetailsObj->height *= $this->_heightScale;
				}
				else{
					$layerObj->LayerDetailsObj->width *= $this->_proportionalScale;
					$layerObj->LayerDetailsObj->height *= $this->_proportionalScale;
				}
			}
		}
		else if($layerObj->LayerType != "deleted"){
			throw new Exception("Error with layer type in method getConvertedArtwork");
		}
		

		// Perform coordinate shifts If a sweet spot is defined on an artwork that we are transfering artwork to.
		if($this->checkIfSweetSpotOnToArtwork()){
			$layerObj->x_coordinate -= $this->_sweetSpotXcoord_To;
			$layerObj->y_coordinate -= $this->_sweetSpotYcoord_To;
		}

	}

	
	// This method will return an XML file converted From 1 Artwork to Another (You must set both artworks before calling this Method)
	// In general, artwork is moved from the center of one design into the center of another.
	// It will scale the layers proportionally based upon the most narrow leg (height or width)... 
	// .... this will ensure that no layers inside of the old Canvas area will hang outside of the new canvas area.
	function getConvertedArtwork(){
	
		$this->_ensureArtworksHaveBeenSet();

		// We may need to make some changes to the Artworks during this method's routing.  
		// Keep a copy so we can put it back to the way it was after we gather the info that we need.
		// The serialization is needed to do a Deep Copy, since there are Objects inside that would be copied by reference.
		$fromArtworkCopy = unserialize(serialize($this->_fromArtObj));
		$toArtworkCopy = unserialize(serialize($this->_toArtObj));


		if(sizeof($this->_fromArtObj->SideItemsArray) == 2 && !$this->_removeBacksideFlag){
			
			// Create a Backside if there isn't one already.
			if(!isset($this->_toArtObj->SideItemsArray[1])){
				
				$this->_toArtObj->SideItemsArray[1] = $this->_toArtObj->SideItemsArray[0];
			
				// We just Made a copy of the Front... onto the back... so change the description to say "Back" now
				$this->_toArtObj->SideItemsArray[1]->description = $this->_fromArtObj->SideItemsArray[1]->description;
		
				// Get rid of all layers on the back (if there are any)
				$this->_toArtObj->SideItemsArray[1]->layers = array();
			}
		}
		else{
			if(isset($this->_toArtObj->SideItemsArray[1]) && $this->_removeBacksideFlag)
				unset($this->_toArtObj->SideItemsArray[1]);
		}

		
		for($i=0; $i < sizeof($this->_toArtObj->SideItemsArray); $i++){

			if(!empty($this->_dpiSetting))
				$this->_toArtObj->SideItemsArray[$i]->dpi = $this->_dpiSetting;

			// We can't transfer anything on to the new artwork if the corresponding side is not defined on the FROM artwork
			if(!isset($this->_fromArtObj->SideItemsArray[$i]))
				continue;
		
			// There may be 1 or more sides that we don't want transfered.
			if(!empty($this->_copyOnlySideNumbers)){
				if(!in_array($i, $this->_copyOnlySideNumbers))
					continue;
			}
			


			// Set the Scaling parameter for the given side that we are on.
			// This is based on different size Canvas Areas... if the canvasas are indetical then all values will just be 1
			if(!$this->_doNotAttemptToScaleArtworkAutoFlag)
				$this->setScalePercentagesFromArtworks($i);
			
			
			// Make sure the layers are sorted by Layer Level... so when we paste them on top of the new artwork they will be stacked in the same way.
			$this->_fromArtObj->SideItemsArray[$i]->orderLayersByLayerLevel();
		
			// Copy all of the Layers From the "From" artwork to the "to" artwork
			for($j=0; $j<sizeof($this->_fromArtObj->SideItemsArray[$i]->layers); $j++){
			
				// We may want to skip layers if they are out of the canvas area
				if($this->_removeOutOfBoundLayersFlag && $this->_fromArtObj->CheckIfLayerIsOutsideOfCanvas($i, $this->_fromArtObj->SideItemsArray[$i]->layers[$j]->level))
					continue;
			
				// If a layer is not transferable then we should not be able to copy it across.
				if($this->_fromArtObj->SideItemsArray[$i]->layers[$j]->LayerDetailsObj->permissions->not_transferable)
					continue;
				
				$layerIsEitherAnOrdinaryTextLayerOrGraphic = true;
			
				
				// Shadows on Text layers can be a bit tricky
				// If the Text Layer has a Shadow that belongs to it then we need to get the shadow and insert that Layer First... then put the current layer on top
				// If this text layer is a shadow layer belonging to another text layer... Skip the loop.  When we finally come around on the Parent Layer... it will pick up its shadow
				// After the new layers are put into the TO artwork, the will get assigned new Layer Levels... this is how Shadow Layers are linked together, so we need to update the correlations.
				if($this->_fromArtObj->SideItemsArray[$i]->layers[$j]->LayerType == "text"){
					
					$thisLayerLevel = $this->_fromArtObj->SideItemsArray[$i]->layers[$j]->level;
				
					if($this->_fromArtObj->CheckIfTextLayerHasShadow($i, $thisLayerLevel)){
						$layerLevelOfShadow = $this->_fromArtObj->GetLayerLevelOfShadowLayer($i, $thisLayerLevel);
						$shadowLayerObj = $this->_fromArtObj->GetLayerObject($i, $layerLevelOfShadow);
						
						// Add the Shadow Layer
						$newShadowLayerLevel = $this->_toArtObj->AddLayerObjectToSide($i, $shadowLayerObj);
						$newShadowLayerNumber = $this->_toArtObj->GetLayerID($i, $newShadowLayerLevel);
						
						// Add its Parent Layer
						$newParentLayerLevel = $this->_toArtObj->AddLayerObjectToSide($i, $this->_fromArtObj->SideItemsArray[$i]->layers[$j]);
						$newParentLayerNumber = $this->_toArtObj->GetLayerID($i, $newParentLayerLevel);
						
						// Link the New Parent to the new Shadow
						$this->_toArtObj->SideItemsArray[$i]->layers[$newParentLayerNumber]->LayerDetailsObj->shadow_level_link = $newShadowLayerLevel;
					
						$this->resizeAndRepositionLayer($this->_toArtObj->SideItemsArray[$i]->layers[$newShadowLayerNumber]);
						$this->resizeAndRepositionLayer($this->_toArtObj->SideItemsArray[$i]->layers[$newParentLayerNumber]);
					
						$layerIsEitherAnOrdinaryTextLayerOrGraphic = false;
					}
					else if($this->_fromArtObj->CheckIfTextLayerIsShadowToAnotherLayer($i, $thisLayerLevel)){
						
						// Don't transfer the shadow directly... just skip it.  It will get picked up when the parent to this shadow is handeled.
						continue;
					}
					else{
						// Just an ordinary Text Layer
					
					}
				
				}
				
				// Save Lines of Code by Handeling Ordinary Text Layers and Graphics together.
				// Text layers with shadows have already been inserted.
				if($layerIsEitherAnOrdinaryTextLayerOrGraphic){

					$newLayerLevel = $this->_toArtObj->AddLayerObjectToSide($i, $this->_fromArtObj->SideItemsArray[$i]->layers[$j]);
					$newLayerNumber = $this->_toArtObj->GetLayerID($i, $newLayerLevel);

					$this->resizeAndRepositionLayer($this->_toArtObj->SideItemsArray[$i]->layers[$newLayerNumber]);
				}
			}
		}

		$returnXML = $this->_toArtObj->GetXMLdoc();
		
		// Reset the Member Variables back to their original state before we began our processing in this method.
		$this->_toArtObj = $toArtworkCopy;
		$this->_fromArtObj = $fromArtworkCopy;
		
		return $returnXML;
	}

	
	

	// Set a "Sweet Spot" for a From or To artwork if it it is not a Rectangular Canvas area
	// For example... on Double-Sided Envelopes the Canvas area is really large.
	// The most important information is within the Center position of the design.
	// Since the registration point is in the center of the canvas area... changing the Canvas Width/Height will crop off a lot of stuff automatically

	// Then the Vertical and Horizontal coordinate shifts will take care of the final positioning.
	// These Measurements are for Transfering TO another one.
	// ...... If you are transfering FROM another Artwork TO one with a sweet spot defined then the reverse measurements for the Horizontal/Vertical shift
	// Values should be in Flash coordinates (96 dpi)
	function setSweetSpotOnFromArtwork($width, $height, $xCoord, $yCoord){
		$width = intval($width);
		$height = intval($height);
		$xCoord = intval($xCoord);
		$yCoord = intval($yCoord);
		
		if(($width != 0 && $height == 0) || ($width == 0 && $height != 0))
			throw new Exception("Error in method setSweetSpotOnFromArtwork.  You can't set the width to a positive value and the height to zero, or visa versa.");
	
		$this->_sweetSpotCanvasWidth_From = $width;
		$this->_sweetSpotCanvasHeight_From = $height;
		$this->_sweetSpotXcoord_From = $xCoord;
		$this->_sweetSpotYcoord_From = $yCoord;
	}
	// Values should be in Flash coordinates (96 dpi)
	function setSweetSpotOnToArtwork($width, $height, $xCoord, $yCoord){
		$width = intval($width);
		$height = intval($height);
		$xCoord = intval($xCoord);
		$yCoord = intval($yCoord);
		
		if(($width != 0 && $height == 0) || ($width == 0 && $height != 0))
			throw new Exception("Error in method setSweetSpotOnFromArtwork.  You can't set the width to a positive value and the height to zero, or visa versa.");
	
		$this->_sweetSpotCanvasWidth_To = $width;
		$this->_sweetSpotCanvasHeight_To = $height;
		$this->_sweetSpotXcoord_To = $xCoord;
		$this->_sweetSpotYcoord_To = $yCoord;
	}
	
	function checkIfSweetSpotOnFromArtwork(){
		if($this->_sweetSpotCanvasWidth_From != 0 || $this->_sweetSpotCanvasHeight_From != 0)
			return true;
		else
			return false;
	}
	function checkIfSweetSpotOnToArtwork(){
		if($this->_sweetSpotCanvasWidth_To != 0 || $this->_sweetSpotCanvasHeight_To != 0)
			return true;
		else
			return false;
	}

	
}

?>

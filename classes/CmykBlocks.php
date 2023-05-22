<?
// Stores a collection of CMYK blocks.
// Provides functionality to draw the entire collection onto a Shape Container.
class CMYKblocksContainer {

	private $_cymkContainerArr = array();   // Keeps an array of CMYKblocks Objects
	
	function CMYKblocksContainer(){
		$this->_cymkContainerArr = array();	
	}
	
	
	
	// Add a CMYK blocks object to out internal array.  It will also make sure that it is not adding a duplicate.
	function addCMYKblocksObj(CMYKblocks $cmykBlocksObj){

		$signatureBeingAdded = md5(serialize($cmykBlocksObj));
	
		foreach($this->_cymkContainerArr as $thisCMYKblockCheck){
			
			if($signatureBeingAdded == md5(serialize($thisCMYKblockCheck)))
				return;
		}
	
		$this->_cymkContainerArr[] = $cmykBlocksObj;
	}
		

	// returns an array of CMYKblocks Objects
	// returns an empty array if none defined.
	function getCMYKblocksCollection(){
	
		return $this->_cymkContainerArr;
	
	}
	
	// CMYK blocks are basically the same as regular Rectangle Shape Objects... but with 4 different colors... as well as sized/spaced systematically.
	function drawAllCMYKblocksOntoShapeContainer(ShapeContainer &$shapeContainerObject, $sideNumber){
		
		foreach($this->_cymkContainerArr as $thisCMYKblockObj){
			if($sideNumber == $thisCMYKblockObj->getSideNumber()){
				$thisCMYKblockObj->drawCMYKblocksOnShapeContainer($shapeContainerObject, $sideNumber);
			}
		}
	}
	
	function getSideNumbers(){
		
		$sideNumbersArr = array();
		
		foreach($this->_cymkContainerArr as $thisCMYKblockObj){
			$sideNumbersArr[] = $thisCMYKblockObj->getSideNumber();
		}
		
		$sideNumbersArr = array_unique($sideNumbersArr);
		sort($sideNumbersArr);

		return $sideNumbersArr;
	}

}


// Contains values describing how to draw a series of CMYK blocks for printing.
// Also provides functionality to draw those colored rectangles into a Shape Container Object.
class CMYKblocks {

	private $_groupWidth;
	private $_groupHeight;
	private $_spaceBetweenGroups;
	private $_totalGroups;
	private $_rotation;
	private $_startXcoord;
	private $_startYcoord;
	private $_shapeContainer;
	private $_ID;
	private $_cymkContainer = array();   // Keeps track on added blocks raw data
	private $_sideNumber;
	
	// Constructor
	function CMYKblocks(){
		
		// Default the width of the CMYK block is 3/4"
		$this->_groupWidth = 0.75;
		$this->_groupHeight = 0.3;
		
		$this->_startXcoord = 0.2;
		$this->_startYcoord = 0.2;
		
		$this->_rotation = 0;
		
		$this->_totalGroups = 1;
		
		$this->_sideNumber = 0;

		$this->_spaceBetweenGroups = 0.3;
	}
	
	
	// ------------  GET Methods -------------------------------
	function getCmykBlockID() {
		return $this->_ID;
	}
	function getGroupWidth() {
		return $this->_groupWidth;
	}
	function getSideNumber() {
		return $this->_sideNumber;
	}
	function getGroupHeight(){
		return $this->_groupHeight;
	}
	function getStartXcoord(){
		return $this->_startXcoord;
	}
	function getStartYcoord(){
		return $this->_startYcoord;
	}
	function getTotalGroups(){
		return $this->_totalGroups;
	}
	function getRotation(){
		return $this->_rotation;
	}
	function getSpacingBetweenGroups(){
		return $this->_spaceBetweenGroups;
	}
	
	
	// ------------  SET Methods -------------------------------
	function setCmykBlockID($x){

		$this->_ID = intval($x);
	}
	function setGroupWidth($x) {
		if(empty($x))
			throw new Exception("0 is not a valid parameter for setGroupWidth");	
		$this->_groupWidth = $x;
	}
	function setGroupHeight($x){
		if(empty($x))
			throw new Exception("0 is not a valid parameter for setGroupHeight");	
		$this->_groupHeight = $x;
	}
	function setSideNumber($x) {
		$this->_sideNumber = intval($x);
	}
	function setStartXcoord($x){
		$this->_startXcoord = $x;
	}
	function setStartYcoord($x){
		$this->_startYcoord = $x;
	}
	function setTotalGroups($x){
		$x = intval($x);
		if(empty($x))
			throw new Exception("0 is not a valid parameter for setTotalGroups");	
		$this->_totalGroups = $x;
	}
	function setRotation($x){
		$x = intval($x);
		if($x >= 360 || $x < 0)
			throw new Exception("Error in method CMYKblocks->setRotation.");
		$this->_rotation = $x;
	}
	function setSpacingBetweenGroups($x){
		$this->_spaceBetweenGroups = $x;
	}
	
	

	

	// Pass in a Shapes Container Object by reference.
	// The CMYK class will a series of CMYK rectangles to the Shape Container Object.
	function drawCMYKblocksOnShapeContainer(ShapeContainer &$shapeContainerObject, $sideNumberToDrawShapesOn){
	

		$this->_shapeContainer = $shapeContainerObject;
		
		for($i=0; $i<$this->_totalGroups; $i++){	
			
			$nextXcoord = $this->_getXCoordOfNextBox($i, 0);
			$nextYcoord = $this->_getYCoordOfNextBox($i, 0);
			$this->_drawSingleBox($sideNumberToDrawShapesOn, $nextXcoord, $nextYcoord, "c");
			
			$nextXcoord = $this->_getXCoordOfNextBox($i, 1);
			$nextYcoord = $this->_getYCoordOfNextBox($i, 1);
			$this->_drawSingleBox($sideNumberToDrawShapesOn, $nextXcoord, $nextYcoord, "m");
			
			$nextXcoord = $this->_getXCoordOfNextBox($i, 2);
			$nextYcoord = $this->_getYCoordOfNextBox($i, 2);
			$this->_drawSingleBox($sideNumberToDrawShapesOn, $nextXcoord, $nextYcoord, "y");

			$nextXcoord = $this->_getXCoordOfNextBox($i, 3);
			$nextYcoord = $this->_getYCoordOfNextBox($i, 3);
			$this->_drawSingleBox($sideNumberToDrawShapesOn, $nextXcoord, $nextYcoord, "k");	

			$nextXcoord = $this->_getXCoordOfNextBox($i, 4);
			$nextYcoord = $this->_getYCoordOfNextBox($i, 4);
			$this->_drawSingleBox($sideNumberToDrawShapesOn, $nextXcoord, $nextYcoord, "g");	
		}
	}
	
	
	
	function _drawSingleBox($sideNumberToDrawShapesOn, $xCoord, $yCoord, $colorType){
	
		$widthOfSingleBox = round($this->_convertInchesToPicas($this->_groupWidth / 5), 2);
		$heightOfSingleBox = round($this->_convertInchesToPicas($this->_groupHeight), 2);
		
		$rectObj = new ArtworkRectangle($widthOfSingleBox, $heightOfSingleBox, $xCoord, $yCoord);
		
		if($colorType == "c")
			$rectObj->setFillColorCMYK(100, 0, 0, 0);
		else if($colorType == "m")
			$rectObj->setFillColorCMYK(0, 100, 0, 0);
		else if($colorType == "y")
			$rectObj->setFillColorCMYK(0, 0, 100, 0);
		else if($colorType == "k")
			$rectObj->setFillColorCMYK(0, 0, 0, 100);
		else if($colorType == "g")
			$rectObj->setFillColorCMYK(50, 50, 50, 0);
			
		$rectObj->setLineStyle("none");
		$rectObj->setRotation($this->getRotation());
		
		$this->_shapeContainer->addShapeObject($sideNumberToDrawShapesOn, $rectObj);
	}
	
	// Get the X coordinate of the next box to be drawn, taken into consideration how the CMYK block is rotated.
	function _getXCoordOfNextBox($groupNum, $boxNumer){
		
		$startX = $this->_convertInchesToPicas($this->_startXcoord);
	
		return $startX + (cos(deg2rad($this->getRotation())) * $this->_getXcoordDistanceFromStart($groupNum, $boxNumer));
	}
	
	function _getYCoordOfNextBox($groupNum, $boxNumer){
	
		$startY = $this->_convertInchesToPicas($this->_startYcoord);
		
		return $startY + (sin(deg2rad($this->getRotation())) * $this->_getXcoordDistanceFromStart($groupNum, $boxNumer));
	}
	
	
	// Based upon the Box(cmyk) and the group we are in... what is the distance from the Start X coordinate (if the Rotation was Zero).
	// This is useful to figure out X/Y Coorinates for each Box unit, taking rotation into consideration.
	function _getXcoordDistanceFromStart($groupNum, $boxNumer){
	
		$widthOfSingleBox = $this->_convertInchesToPicas($this->_groupWidth / 5);
		
		$boxClusterDistance = $boxNumer * $widthOfSingleBox;
		$gapDistancesTotal = $this->_convertInchesToPicas($this->_spaceBetweenGroups * $groupNum);
		$previousGroupWidthTotals = $this->_convertInchesToPicas($this->_groupWidth * $groupNum);
		
		return $boxClusterDistance + $gapDistancesTotal + $previousGroupWidthTotals;
	}
	
	function _convertInchesToPicas($x){
		return $x * 72;
	}
	
}



?>
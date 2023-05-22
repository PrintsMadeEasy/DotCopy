<?

// Contains an array of ArtworkShape Objects
// Also contains shapes for multipe sides.
class ShapeContainer {

	private $_canvasWidthArr  = array();
	private $_canvasHeightArr = array();
	private $_shapeObjectsArr = array();
	
	// Constructor
	function ShapeContainer(){
	}
	

	// Side number is Zero Based
	function addShapeObject($sideNumber, $shapeObject){
	
		$sideNumber = intval($sideNumber);
	
		if(!isset($this->_shapeObjectsArr[$sideNumber]))
			$this->_shapeObjectsArr[$sideNumber] = array();
		
		// Prevent the User from adding duplicate Shape Objects.
		$signatureBeingAdded = md5(serialize($shapeObject));
		
		foreach($this->_shapeObjectsArr[$sideNumber] as $thisShapeObjCheck){
			if($signatureBeingAdded == md5(serialize($thisShapeObjCheck)))
				return;
		}
		
		array_push($this->_shapeObjectsArr[$sideNumber], $shapeObject);
	}

	
	// Setting the Canvas Height/Width is optional.
	// Some application may require Canvas/Height Width if the shapes are meant to be created "stand alone".
	// Other applications may just use the the Shape Container to store coordinates and draw on top of an existing canvas.
	
	// Side number is Zero Based
	function setCanvasWidth($sideNumber, $x){
	
		if(!preg_match("/^\d+(\.\d+)?$/", $x))
			throw new Exception("CanvasWidth is not correct.");
		$this->_canvasWidthArr[$sideNumber] = $x;
	}
	// Side number is Zero Based
	function setCanvasHeight($sideNumber, $x){
		if(!preg_match("/^\d+(\.\d+)?$/", $x))
			throw new Exception("CanvasHeight is not correct.");
		$this->_canvasHeightArr[$sideNumber] = $x;
	}
	
	function getSideNumbers(){
		return array_keys($this->_shapeObjectsArr);
	}
	
	
	// Side number is Zero Based
	// If no shapes exist have been set for the side then this method will just return an empty array.
	function getShapeObjectsArr($sideNumber){
		if(!isset($this->_shapeObjectsArr[$sideNumber]))
			return array();
		return $this->_shapeObjectsArr[$sideNumber];
	}
	function getCanvasWidth($sideNumber){
		
		$lastProjectIDloaded = WebUtil::GetSessionVar("LastProofProjectID", "none");
		
		if(!isset($this->_canvasWidthArr[$sideNumber]))
			throw new Exception("Side Number: $sideNumber does not exist in the method getCanvasWidth. LastProjectID: $lastProjectIDloaded VarDump:\n\n" . var_export($this, true));
		return $this->_canvasWidthArr[$sideNumber];
	}
	function getCanvasHeight($sideNumber){
		if(!isset($this->_canvasHeightArr[$sideNumber]))
			throw new Exception("Side Number: $sideNumber does not exist in the method getCanvasHeight");
		return $this->_canvasHeightArr[$sideNumber];
	}

	

	
}

// This should contain enough information to draw a shape on top of a PDF artwork or in the editing tool
// Another Class should inherit from it to create a rectangle, elipse, polygon, etc. 
// This could be useful to show a customer a proof of an artwork where a Window may be punched out on an Envelope or somthing
class ArtworkShape {


	protected $xCoord; 			// X and Y coordinates should be in Picas
	protected $yCoord;
	protected $rotation;

	protected $lineStyle = "dashed";	// should be a string like "solid", "dashed", "dotted", "none"
	protected $lineThickness = 0.5;	// an decimal like 1.5, 6, ect... in Picas
	protected $shapeName;			// Must be set by the class that inherits.  That way we can find out what type of shape we are dealing with.
	protected $fillColorRGB = "#888899";	// Hex Value for the color inside the shape.  (By default start with Bluish-Grey
	protected $lineColor = "#000000";	// Hex Value for the line surrounding the shape. (default to black)
	protected $lineAlpha = 100;  		// Alpha channel, must be a nubmer between 0 and 100... Zero is invisible. 
	protected $fillAlpha = 100;
	protected $fillColorCMYK = array();
	protected $fillColorIsCMYKflag = false;
	protected $optionsLimiter;
	protected $remarks;
	protected $shapeID;
	
	
	
	function __construct(){
		throw new Exception("Error in the contructor for ArtworkShape. You can not create an object directly. This class must be extended.");
	}
	function setXcoord($x){
		$this->xCoord = floatval($x);
	}
	function setYcoord($x){
		$this->yCoord = floatval($x);
	}
	function setRotation($x){
		$x = intval($x);
		if($x <0 || $x>=360)
			throw new Exception("Error setting Shape Rotation.");
		$this->rotation = $x;
	}
	function setLineStyle($x){
		if($x != "solid" && $x != "dashed" && $x != "dotted" && $x != "none")
			throw new Exception("Line Style is not correct.");
		$this->lineStyle = $x;
	}
	function setLineThickness($x){
		$this->lineThickness = floatval($x);
	}
	function setFillColorRGB($x){
		$this->fillColorRGB = strtoupper($x);
		
		$this->fillColorIsCMYKflag = false;
	}
	
	// Each value is an integer 0 to 100
	function setFillColorCMYK($c, $m, $y, $k){
		$this->fillColorCMYK = array("c"=>$c, "m"=>$m, "y"=>$y, "k"=>$k);
		$this->fillColorIsCMYKflag = true;
	}

	function setLineColor($x){
		$this->lineColor = strtoupper($x);
	}
	function setLineAlpha($x){
		$x = intval($x);
		if($x < 0 || $x > 100)
			throw new Exception("Line Alpha value must be between 0 and 100");
		$this->lineAlpha = $x;
	}
	function setFillAlpha($x){
		$x = intval($x);
		if($x < 0 || $x > 100)
			throw new Exception("Fill Alpha value must be between 0 and 100");
		$this->fillAlpha = $x;
	}

	function setOptionsLimiter($x){
	
		if(strlen($x) > 200)
			throw new Exception("Error in Method ArtworkShape->setOptionsLimiter, can not exceed 255 chars.");
			
		$this->optionsLimiter = $x;
	}
	
	function setRemarks($x) {
	
		if(strlen($x) > 200)
			throw new Exception("Error in Method ArtworkShape->setRemarks, can not exceed 255 chars.");
			
		$this->remarks = $x;
	}
	function setShapeID($x){
		
		$this->shapeID = intval($x);
	}
	
	
	// Getter Methods
	function fillColorIsCMYK(){
		return $this->fillColorIsCMYKflag;
	}
	function getShapeName(){
		throw new Exception("Error in method ArtworkShape->getShapeName. This method must be overridden by the class that inherits.");
	}
	function getXCoord(){
		return $this->xCoord;
	}
	function getYCoord(){
		return $this->yCoord;
	}
	function getRotation(){
		return $this->rotation;
	}
	function getLineStyle(){
		return $this->lineStyle;
	}
	function getLineThickness(){
		return $this->lineThickness;
	}
	function getFillColorRGB(){
		return $this->fillColorRGB;
	}
	function getLineColor(){
		return $this->lineColor;
	}
	function getLineAlpha(){
		return $this->lineAlpha;
	}
	function getFillAlpha(){
		return $this->fillAlpha;
	}
	function getOptionsLimiter(){
		return $this->optionsLimiter;
	}
	function getRemarks(){
		return $this->remarks;
	}
	function getShapeID(){
		return $this->shapeID;
	}
	
	function getFillColorCMYK($colorType){
	
		if(empty($this->fillColorCMYK))
			throw new Exception("Error in method getFillColorCMYK. No values have been set.");
	
		if(strtoupper($colorType) == "C")
			return $this->fillColorCMYK["c"];
		else if(strtoupper($colorType) == "M")
			return $this->fillColorCMYK["m"];
		else if(strtoupper($colorType) == "Y")
			return $this->fillColorCMYK["y"];
		else if(strtoupper($colorType) == "K")
			return $this->fillColorCMYK["k"];
		else
			throw new Exception("Error in method getFillColorCMYK. The color type is undefined.");
	
	}
	

	function fillTemplateWithCommonShapeVars(Template $t) {
	
		$t->set_var("SHAPE_ID",$this->getShapeID());
		$t->set_var("SHAPE_REMARKS",$this->getRemarks());
		$t->set_var("SHAPE_OPTIONS_LIMITER",$this->getOptionsLimiter());
		$t->set_var("SHAPE_X",$this->getXCoord());
		$t->set_var("SHAPE_Y",$this->getYCoord());
		$t->set_var("SHAPE_ROTATION",$this->getRotation());
		$t->set_var("SHAPE_LINE_COLOR",$this->getLineColor());
		$t->set_var("SHAPE_LINE_ALPHA",$this->getLineAlpha());
		$t->set_var("SHAPE_FILL_COLOR",$this->getFillColorRGB());
		$t->set_var("SHAPE_FILL_ALPHA",$this->getFillAlpha());
		$t->set_var("SHAPE_LINE_THICKNESS",$this->getLineThickness());	
		$t->set_var("LINE_STYLE_OPTIONS",Widgets::buildSelect(array("solid"=>"Solid", "dashed"=>"Dashed"),$this->getLineStyle()));
		
		$t->allowVariableToContainBrackets("LINE_STYLE_OPTIONS");
		
		// Shape Specific Fields
		if($this->getShapeName() == "circle"){
			
			$t->set_var("SHAPE_SHAPE_VALUE_1",$this->getRadius());
			$t->set_var("SHAPE_SHAPE_VALUE_2",null);
		}
		else if($this->getShapeName() == "rectangle"){
		
			$t->set_var("SHAPE_SHAPE_VALUE_1",$this->getWidth());
			$t->set_var("SHAPE_SHAPE_VALUE_2",$this->getHeight());
		}
	}

}

class ArtworkCircle extends ArtworkShape{

	private $radius;		// Radius should be in Picas

	// constructor
	// X/Y coordinate are in Picas... they specify the center point location of the circle.
	function ArtworkCircle($radius, $xCoord, $yCoord){

		if(!preg_match("/^\d+(\.\d+)?$/", $radius))
			throw new Exception("Cicle Radius is not correct.");

		$this->setRadius($radius);
		$this->setXcoord($xCoord);
		$this->setYcoord($yCoord);
		
		// Allthough a Rotation setting in a circle is useless, most shapes can use a rotation.
		// Having rotation on a circle is benign, so it is safe to keep in the base class.
		$this->setRotation(0);

	}
	

	function getShapeName(){
		return "circle";
	}
	
	function setRadius($x){
		if(!preg_match("/^\d+(\.\d+)?$/", $x))
			throw new Exception("Cicle Radius is not correct.");
		$this->radius = $x;
	}
	
	function getRadius(){
		return $this->radius;
	}
	
	
}


class ArtworkRectangle extends ArtworkShape{

	private $height;		// Height and Width should be in Picas
	private $width;
	
	// constructor
	function ArtworkRectangle($width, $height, $xCoord, $yCoord){

		if(!preg_match("/^\d+(\.\d+)?$/", $width))
			throw new Exception("Rectangle Width is not correct.");
		if(!preg_match("/^\d+(\.\d+)?$/", $height))
			throw new Exception("Rectangle Height is not correct.");

		$this->height = $height;
		$this->width = $width;
		$this->setXcoord($xCoord);
		$this->setYcoord($yCoord);
		$this->setRotation(0);
	}

	function getShapeName(){
		return "rectangle";
	}

	function getWidth(){
		return $this->width;
	}
	function getHeight(){
		return $this->height;
	}
}

?>
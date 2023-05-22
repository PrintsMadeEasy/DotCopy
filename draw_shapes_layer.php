<?

require_once("library/Boot_Session.php");





// We can specify which side to receive the shapes layer for... front, back, etc.   SideNumber is Zero based
$sidenumber = WebUtil::GetInput("sidenumber", FILTER_SANITIZE_INT);



// There should be a Shape Objects sent in a session variable 
// The movie clip height and width equals the content area of the canvas area.
// This script will create an SWF file which the editing tool can download and place on top of the complete canvas area.
// This is useful to draw something like the Rectangle for the window portion of an Envelope... or maybe circles where a page will get hole-punched


$shapeContainerFromSession = WebUtil::GetSessionVar("ShapeContainerSesssionVar");

$shapeContainerObj = null;
if(!empty($shapeContainerFromSession))
	$shapeContainerObj = unserialize($shapeContainerFromSession);



// The canvas height and Width are set in units at 96 DPI
if($shapeContainerObj){
	$canvasWidth = $shapeContainerObj->getCanvasWidth($sidenumber);
	$canvasHeight = $shapeContainerObj->getCanvasHeight($sidenumber);
	$ShapeObjectsArr = $shapeContainerObj->getShapeObjectsArr($sidenumber);
}
else{
	// default values in case of an unknown failure
	$canvasWidth = 100;
	$canvasHeight = 100;
	$ShapeObjectsArr = array();
}

/*
// for Debugging you can try to create new shapes here and see them output in the browser window by calling this script directly
$ShapeObjectsArr = array(new ArtworkRectangle(40, 20, 0, 0));
$circleObj = new ArtworkCircle(130, 100, 100);
$circleObj->setFillColorRGB("#330000");
$circleObj->setLineColor("#00FF00");
$circleObj->setFillAlpha(70);
array_push($ShapeObjectsArr, $circleObj);
*/

$m = new SWFMovie();
$m->setDimension($canvasWidth, $canvasHeight);
$m->setRate(12.0);


foreach($ShapeObjectsArr as $thisShapeObj){

	// LineStyle will go unused since MING does not support line styles yet
	//$thisShapeObj->getLineStyle()

	// Line thickness in the shape object is in Picas which is 72 DPI
	// We need to convert to Flash's 96 DPI
	$lineThickness = $thisShapeObj->getLineThickness() / 72 * 96;

	$s = new SWFShape();
	
	$lineColorCode = $thisShapeObj->getLineColor();
	$lineColorObj = ColorLib::getRgbValues($lineColorCode, false);
	
	$fillColorCode = $thisShapeObj->getFillColorRGB();
	$fillColorObj = ColorLib::getRgbValues($fillColorCode, false);
	
	$lineAlphaVal = $thisShapeObj->getLineAlpha();
	$fillAlphaVal = $thisShapeObj->getFillAlpha();
	
	// Convert between 0 and 100 to 0 and 255
	$lineAlphaVal = $lineAlphaVal * 255 / 100;
	$fillAlphaVal = $fillAlphaVal * 255 / 100;

	// Convert Coordinates from Picas to Flash
	$xCoord = round($thisShapeObj->getXCoord() / 72 * 96);
	$yCoord = round($thisShapeObj->getYCoord() / 72 * 96);


	if($thisShapeObj->getShapeName() == "rectangle"){
	
		$width = round($thisShapeObj->getWidth() / 72 * 96);
		$height = round($thisShapeObj->getHeight() / 72 * 96);

		// Draw a black Line
		$s->setLine($lineThickness, $lineColorObj->red, $lineColorObj->green, $lineColorObj->blue, $lineAlphaVal);

		// Alpha channel is the 4th parameter... Make is slightly opaque
		$f = $s->addFill($fillColorObj->red, $fillColorObj->green, $fillColorObj->blue, $fillAlphaVal);
		$s->setRightFill($f);
		
		
		// Coordinates in the Shape object are measured from the bottom up, we measure from the top down in flash.
		// We have to Account for the rotation of rectangles.
		$startXcoord = $xCoord;
		$startYcoord = $canvasHeight - $yCoord;
		
		
		// If the Rotation is 0... then X will be the full Width... the Y coord will be Zero.
		$widthRotatedX = cos(deg2rad($thisShapeObj->getRotation())) * $width;
		$widthRotatedY = sin(deg2rad($thisShapeObj->getRotation())) * $width;
		
		// If the Rotation is 0... then X will be Zero... the Y coord will be full height.
		$heightRotatedX = cos(deg2rad($thisShapeObj->getRotation())) * $height;
		$heightRotatedY = sin(deg2rad($thisShapeObj->getRotation())) * $height;
		
		
		$s->movePenTo($startXcoord, $startYcoord);
		$s->drawLine($widthRotatedX, $widthRotatedY);
		$s->drawLine($heightRotatedX, -($heightRotatedY));
		$s->drawLine(-($widthRotatedX), $widthRotatedY);
		$s->drawLine($heightRotatedX, ($heightRotatedY));
	}
	else if($thisShapeObj->getShapeName() == "circle"){
	
		$radius = round($thisShapeObj->getRadius() / 72 * 96);

		$lineColor = array($lineColorObj->red, $lineColorObj->green, $lineColorObj->blue);
		$fillColor = array($fillColorObj->red, $fillColorObj->green, $fillColorObj->blue);

		$circleObj = $m->add(drawCircle($radius, $lineThickness, $lineColor, $fillColor, $lineAlphaVal, $fillAlphaVal));
		
		// Coordinates in the Shape object our measured from the bottom up, we measure from the top down in flash
		$circleObj->moveTo($xCoord, ($canvasHeight - $yCoord));
	}


	$m->add($s);

}


$m->nextFrame();

// For Some reason this Cach Control is needed or IE won't display the Flash Movie in the browser... It asks to download the file to your computer.
header("Pragma: public");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header('Content-type: application/x-shockwave-flash');
session_write_close();
$m->output();
exit;








/* --- Example of how to Draw a Red Square
$s = new SWFShape();
$f = $s->addFill(r, g, b [,a]);
$s->setRightFill($f);
$s->movePenTo(-200, -200);
$s->drawLine(400, 0);
$s->drawLine(0, 400);
$s->drawLine(-400, 0);
$s->drawLine(0, -400);

$m = new SWFMovie();
$m->setDimension(320, 240);
$m->setRate(12.0);
$m->add($s);
$m->nextFrame();

header('Content-type: application/x-shockwave-flash');
$m->output();
*/



//Flash draws all curves, including circles using the very fast drawCurve function which draws 
//quadratic spline curves. Using these curves a realistic circle can be rendered using 8 segments. 
//To do this the control points are placed on the vertices of an octogon. 

/*
$black = array(0,0,0);
$blue = array(0,0,255);
$circle=$movie->add(drawCircle(10,$black,$blue,100,50));
$circle->moveTo(50,100);
*/


function drawCircle($r, $lineThickness, $lineColorArr, $fillColorArr, $lineColorArr_alpha, $fillColorArr_alpha){
	
	$circle = new SWFShape();
	

	$circle->setLine($lineThickness, $lineColorArr[0], $lineColorArr[1], $lineColorArr[2], $lineColorArr_alpha);
	$circle->setRightFill($fillColorArr[0], $fillColorArr[1], $fillColorArr[2], $fillColorArr_alpha);

	$circle->movePen(0,-$r);
	
	$c = ($r/(1+sqrt(2)));
	$d = ($r/(2+sqrt(2)));
	
	$circle->drawCurve($c, 0, $d, $d);
	$circle->drawCurve($d, $d, 0, $c);
	$circle->drawCurve(0, $c, -$d, $d);
	$circle->drawCurve(-$d, $d, -$c, 0);
	$circle->drawCurve(-$c, 0, -$d, -$d);
	$circle->drawCurve(-$d, -$d, 0, -$c);
	$circle->drawCurve(0, -$c, $d, -$d);
	$circle->drawCurve($d, -$d, $c, 0);
	
	return $circle;
}


?>
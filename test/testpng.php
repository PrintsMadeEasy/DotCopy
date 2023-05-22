<?

if(!array_key_exists("imagetype", $_GET))
	$imagetype = "";
else 
	$imagetype = trim($_GET["imagetype"]);


$imgPtr  = imagecreatetruecolor (150, 30); /* Create a blank image */
$bgc = imagecolorallocate ($imgPtr, 255, 255, 255);
$tc  = imagecolorallocate ($imgPtr, 0, 0, 0);
imagefilledrectangle ($imgPtr, 0, 0, 150, 30, $bgc);


if($imagetype == "jpeg"){
	imagestring ($imgPtr, 1, 5, 5, "JPEG Image", $tc);
	imagejpeg ($imgPtr);
}

else{
	imagestring ($imgPtr, 1, 5, 5, "PNG Image", $tc);
	imagepng ($imgPtr);
}



ImageDestroy($imgPtr);


?>
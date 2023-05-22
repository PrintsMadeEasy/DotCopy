<?php

$im = imagecreatetruecolor (400, 100);
$black = imagecolorallocate ($im, 0, 0, 0);
$white = imagecolorallocate ($im, 255, 255, 255);


imagettftext ($im, 20, 0, 10, 20, $white, "/home/printsma/public_html/fonts/Apology.ttf", "Testing... This font should be a little");

Header( "Content-Type: image/jpeg");
imagejpeg ($im);
imagedestroy ($im);
?>

<?php

$im = imagecreate (50, 100)
    or die ("Cannot Initialize new GD image stream");
$background_color = imagecolorallocate ($im, 255, 255, 255);
$text_color = imagecolorallocate ($im, 233, 14, 91);
imagestring ($im, 1, 5, 5,  "A Simple Text String", $text_color);


header ("Content-type: image/png");
imagepng ($im);
?>

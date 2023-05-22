<?php

/*
  $s = new SWFShape();
  $s->setLine(4, 0x7f, 0, 0);
  $s->setRightFill($s->addFill(0xff, 0, 0));
  $s->movePenTo(10, 10);
  $s->drawLineTo(310, 10);
  $s->drawLineTo(310, 230);
  $s->drawCurveTo(10, 230, 10, 10);

  $m = new SWFMovie();
  $m->setDimension(320, 240);
  $m->setRate(12.0);
  $m->add($s);
  $m->nextFrame();

  header('Content-type: application/x-shockwave-flash');
  $m->output();

*/

  $s = new SWFShape();
  
  $fd = fopen ("./test_jpg.jpg", "r");
  $imageContents = fread ($fd, 5000000);
  fclose ($fd);
  
  $bitMapObj = new SWFBitmap($imageContents);

  $f = $s->addFill(  $bitMapObj);

  $s->setRightFill($f);

  $s->drawLine(250, 0);
  $s->drawLine(0, 94);
  $s->drawLine(-250, 0);
  $s->drawLine(0, -94);

  $m = new SWFMovie();
  $m->setDimension(1000,376);
  $m->add($s);

  header('Content-type: application/x-shockwave-flash'); 
  $m->output();
?>

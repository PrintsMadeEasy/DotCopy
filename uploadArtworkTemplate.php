<?

require_once("library/Boot_Session.php");


$ProductID = WebUtil::GetInput( "productid", FILTER_SANITIZE_INT);


$dbCmd = new DbCmd();


try {
	$productObj = Product::getProductObj($dbCmd, $ProductID, true);
}
catch (Exception $e){
	WebUtil::PrintError("The Product ID is not available.");
}

if(!Product::checkIfProductIDisActive($dbCmd, $ProductID)){
	WebUtil::PrintError("This product has been discontinued.");
}



$user_sessionID =  WebUtil::GetSessionID();


$t = new Templatex();

$t->set_file("origPage", "uploadArtworkTemplate-template.html");



$t->set_var("PRODUCTID", $ProductID);
$t->set_var("DPI", $productObj->getArtworkDPI());
$t->set_var("BLEED_PICAS", $productObj->getArtworkBleedPicas());
$t->set_var("PRODUCT_TITLE", WebUtil::htmlOutput($productObj->getProductTitleWithExtention()));


if($productObj->getArtworkBleedPicas() == 0)
	$t->discard_block("origPage", "BleedUnitsBL");


// Make sure that This Product supports an Automatic Template Image.
$artworkEmbedMessage = $productObj->getArtworkImageUploadEmbedMsg();

if(empty($artworkEmbedMessage))
	$artworkEmbedMessage = "Remove all guide lines before uploading.";

// Create a FileName based upon stuff that could cause the image to change.
$uniqueImageName = md5($productObj->getArtworkDPI() . $productObj->getArtworkImageUploadEmbedMsg() . $productObj->getArtworkCanvasWidth() . $productObj->getArtworkCanvasHeight() . $productObj->getArtworkBleedPicas());
$uniqueImageName = "ArtworkTemplate_" . substr($uniqueImageName, 0, 9) . ".jpg";


$pathOnDiskJpegName = DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $uniqueImageName;
$pathThroughWeb = DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $uniqueImageName;



// Find out if the image is already written to disk.... then don't worry about creating the image.
if(file_exists($pathOnDiskJpegName)){

	$t->set_var("UPLOAD_ARTWORK_TEMPLATE_IMAGE_URL", $pathThroughWeb);
	$t->pparse("OUT","origPage");
	exit;
}


// Create a new/blank PDF doc that we will paste the master pages and write the variable data onto 
$pdf = pdf_new();

// Sets the license key, author, font paths, etc.
PdfDoc::setParametersOnNewPDF($pdf);

PDF_begin_document($pdf, "", "");


$fullCanvasWidthPicas = $productObj->getArtworkCanvasWidth() * 72 + $productObj->getArtworkBleedPicas() * 2;
$fullCanvasHeightPicas = $productObj->getArtworkCanvasHeight() * 72 + $productObj->getArtworkBleedPicas() * 2;

pdf_begin_page($pdf, $fullCanvasWidthPicas, $fullCanvasHeightPicas);


// The Green line Around the Perimeter
pdf_setcolor($pdf, "both", "RGB", 0, 0.8, 0, 0);
pdf_setlinewidth ( $pdf, 0.8);
pdf_rect($pdf, 0, 0, $fullCanvasWidthPicas, $fullCanvasHeightPicas );
pdf_stroke($pdf);



// Don't draw the Cut and Safe zones if there is no bleed.
if($productObj->getArtworkBleedPicas() != 0){

	$bleedPicas = $productObj->getArtworkBleedPicas();

	// Red Cut Line
	pdf_setcolor($pdf, "both", "RGB", 0.8, 0, 0, 0);
	pdf_setlinewidth ( $pdf, 0.4);
	pdf_rect($pdf, $bleedPicas, $bleedPicas, $fullCanvasWidthPicas - $bleedPicas * 2, $fullCanvasHeightPicas - $bleedPicas * 2);
	pdf_stroke($pdf);


	// Blue Safe Zone
	pdf_setcolor($pdf, "both", "RGB", 0, 0, 0.8, 0);
	pdf_setlinewidth ( $pdf, 0.4);
	pdf_rect($pdf, $bleedPicas * 2, $bleedPicas * 2, $fullCanvasWidthPicas - $bleedPicas * 4, $fullCanvasHeightPicas - $bleedPicas * 4);
	pdf_stroke($pdf);
}



$heightOfImageInPixels = $productObj->getArtworkCanvasHeight() * $productObj->getArtworkDPI();
$widthOfImageInPixels = $productObj->getArtworkCanvasWidth() * $productObj->getArtworkDPI();

// We would like to Write the Embedded Message in the Center of the Image.
// But if the image (pixels) is way to large... then the Customer can't see the image from there browser.
// If it is really large then, we will try writing the message 450 Pixels From the Top.

if($heightOfImageInPixels > 600)
	$yCoordinateToPrintText = $fullCanvasHeightPicas - (450 / $productObj->getArtworkDPI() * 72);
else
	$yCoordinateToPrintText = $fullCanvasHeightPicas / 2;


// Print the Message at 35 points... but make sure that it stays within the Total Width.
// If not we will shrink the Font-Size down.
// We want to start the X coodinate 60 pixels from the Left side of the "Safe Line".
$xCoordToPrintTextStart = ($productObj->getArtworkBleedPicas()) * 2 + (60 / $productObj->getArtworkDPI() * 72);

// The Right marin... that we don't want to exceed.
$xCoordToPrintTextEnd = $fullCanvasWidthPicas - $xCoordToPrintTextStart;


// If the Artwork is really tiny... they we don't want to write any text.
if($xCoordToPrintTextStart < $xCoordToPrintTextEnd && $widthOfImageInPixels > 300){

	$DeckerFont = pdf_findfont($pdf, "Decker", "winansi", 1);
	pdf_setfont ( $pdf, $DeckerFont, 35);
	pdf_setcolor($pdf, "both", "rgb", 0, 0, 0, 0);

	$sizeRestrictionLimit = $xCoordToPrintTextEnd - $xCoordToPrintTextStart;
	
	$pdfTextParameter = 'boxsize {'.$sizeRestrictionLimit.' 1000} position {0 0} fitmethod auto';

	PDF_fit_textline($pdf, $artworkEmbedMessage, $xCoordToPrintTextStart, $yCoordinateToPrintText, $pdfTextParameter);
	
}


pdf_end_page($pdf);

PDF_end_document($pdf, "");

$pdfData = pdf_get_buffer($pdf);



// Safe the PDF document to Disk
$tmpfnamePDF = FileUtil::newtempnam(Constants::GetTempDirectory(), "PDFIMG", ".pdf", time());

// Put the PDF doc into the temp file
$fp = fopen($tmpfnamePDF, "w");
fwrite($fp, $pdfData);
fclose($fp);

chmod ($tmpfnamePDF, 0666);



// Now Convert the PDF document to an Image.
$convertPDFcommand = Constants::GetUpperLimitsShellCommand(230, 45) . Constants::GetPathToImageMagick() . "convert -density " .  $productObj->getArtworkDPI() . " " .  $tmpfnamePDF . "[0] -quality 95 -colorspace RGB " . $pathOnDiskJpegName;

$systemResult = WebUtil::mysystem($convertPDFcommand);

if(!file_exists($pathOnDiskJpegName)){
	WebUtil::WebmasterError("Error creating a JPEG Upload Template From PDF file. Convert Command: " . $convertPDFcommand);
	WebUtil::PrintError("An unknown error occurred with an image template.");
}

// Get Rid rid of the PDF... it was just used to make the JPEG.
unlink($tmpfnamePDF);



$t->set_var("UPLOAD_ARTWORK_TEMPLATE_IMAGE_URL", $pathThroughWeb);

$t->pparse("OUT","origPage");

?>
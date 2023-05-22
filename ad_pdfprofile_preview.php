<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("EDIT_PDF_PROFILES"))
		throw new Exception("You don't have permission to edit a Product.");


$profileID = WebUtil::GetInput("profileID", FILTER_SANITIZE_INT);

$productIDofProfile = PDFprofile::getProductIDfromProfileID($dbCmd, $profileID);
if(empty($productIDofProfile))
	throw new Exception("Error, the PDF profile ID does not exist.");

$domainIDofProduct = Product::getDomainIDfromProductID($dbCmd, $productIDofProfile);

$productObj = new Product($dbCmd, $productIDofProfile, true);


$ArtworkInfoObj = new ArtworkInformation($productObj->getDefaultProductArtwork());

$pdfProfileObj = new PDFprofile($dbCmd, $productIDofProfile);
$pdfProfileObj->loadProfileByID($profileID);

$pdfParameterObj = new PDFparameters($pdfProfileObj);
$pdfParameterObj->setShowCutLine(true);
$pdfParameterObj->setShowBleedSafe(true);
$pdfParameterObj->setOrderno("PDF Profile ID: $profileID");
$pdfParameterObj->setPdfSideNumber(0);
$pdfParameterObj->setBleedtype("N");

$PDFobjectsArr = array($pdfParameterObj);


// This will generate the PDF document and return its contents 
$data = PdfDoc::GeneratePDFsheet($dbCmd, $PDFobjectsArr, $ArtworkInfoObj, "#cc9966", "openmode=none", false);

$PDFfilename = "PDF_Preview_" . substr(md5($profileID . "abcdefg" . time()), 0, 15) . ".pdf";



// Put PDF on disk
$fp = fopen(DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $PDFfilename, "w");
fwrite($fp, $data);
fclose($fp);



$fileDownloadLocation = WebUtil::FilterURL(DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $PDFfilename);


header("Location: " . $fileDownloadLocation);
exit;









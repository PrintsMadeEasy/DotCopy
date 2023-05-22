<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();


// PDFs may take a long time to generate on very large artworks, especially with Variable Images.  Give it 6 mintues.
set_time_limit(9060);


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


$projectrecord = WebUtil::GetInput("projectrecord", FILTER_SANITIZE_INT);
$showDataMerge = WebUtil::GetInput("showDataMerge", FILTER_SANITIZE_STRING_ONE_LINE);
$viewType = WebUtil::GetInput("viewtype", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$pdfProfile = WebUtil::GetInput("pdfprofile", FILTER_SANITIZE_STRING_ONE_LINE);


ProjectBase::EnsurePrivilagesForProject($dbCmd, $viewType, $projectrecord);


// This script will only be used to display proofs of an artwork for a project that was ordered... or a template preview
// Proofs for a customer are a lot more intensive because they can have different PDF settings, like stretch, tile, etc.
if(strtoupper($viewType) == "PROOF"){

	// This array will contain a list of Objects used to generate a side of a PDF document...  EX:  There may be one for "Front" and one for "Back"
	// Contains any bleed settings, stretch, tile, natural.
	$paramsPDFobjectsArr = PDFparameters::GetPDFparametersForProjectOrderedArr($dbCmd, $projectrecord, $pdfProfile);

	// Find out if the user want another profile
	if(!empty($pdfProfile)){
		for($i=0; $i<sizeof($paramsPDFobjectsArr); $i++){
			$paramsPDFobjectsArr[$i]->changeProfileName($pdfProfile);

			//If we are in proof mode, then we don't want to see a border around the artwork for tile bleeds
			if(strtoupper($pdfProfile) == "PROOF" && $paramsPDFobjectsArr[$i]->getBleedtype() == "T")
				$paramsPDFobjectsArr[$i]->setShowCutLine(false);
			else
				$paramsPDFobjectsArr[$i]->setShowCutLine(true);


			// If there is no bleed... then we don't want to show any bleed/safe lines
			if($paramsPDFobjectsArr[$i]->getBleedtype() == "V")
				$paramsPDFobjectsArr[$i]->setShowBleedSafe(false);

			else
				$paramsPDFobjectsArr[$i]->setShowBleedSafe(true);

			// Radio buttons on the Project page may override the value of showing the cut/safe lines
			if(WebUtil::GetInput("showlines", FILTER_SANITIZE_STRING_ONE_LINE) == "no"){
				$paramsPDFobjectsArr[$i]->setShowBleedSafe(false);
				$paramsPDFobjectsArr[$i]->setShowCutLine(false);
			}
		}
	}


	$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "ordered", $projectrecord);

	$ArtworkInfoObj = new ArtworkInformation($projectObj->getArtworkFile());


	if($showDataMerge && !$projectObj->isVariableData())
		throw new Exception("You can not show the data merge if the project does not contain variable data");
	if($showDataMerge && !($projectObj->getVariableDataStatus() == "G" || $projectObj->getVariableDataStatus() == "W")){
		?>
		<html>
		<body bgcolor="#6699CC" leftmargin="20" topmargin="20" marginwidth="20" marginheight="20" >
		<br><br><br><br>
		<font size="2" face="arial"><font color="#FFFFFF">

			A PDF with data merge can not be displayed if there are any Data Errors or configuration problems.  <a href='javascript:self.close()' >Click here</a> close this window.

		</font></font>

		<br><br><br><br>
		</body>
		</html>
		<?

		exit;
	}


	// This will generate the PDF document and return its contents
	// $showDataMerge comes in the url 
	if($projectObj->isVariableData() && $showDataMerge){
	
		// Flush Spaces upon progress to keep the browser from timing out on large files.
		if($projectObj->checkIfVariableDataIsLarge())
			$outputProgress = "space";
		else
			$outputProgress = "none";
	
		$data = PdfDoc::generateVariableDataPDF($dbCmd, $projectrecord, "ordered", false, $paramsPDFobjectsArr, "#FFFFFF", "openmode=none", $outputProgress);
	}
	else{
		$data = PdfDoc::GeneratePDFsheet($dbCmd, $paramsPDFobjectsArr, $ArtworkInfoObj, "#FFFFFF", "openmode=none", $projectObj->isVariableData());
	}


	// Get a file name for the PDF based off of the Project Information
	$PDFfilename = PdfDoc::GetFileNameForPDF($projectObj, "");
}
else if($viewType == "template_category" || $viewType == "template_searchengine"){

	$artworkXML = ArtworkLib::GetArtXMLfile($dbCmd, $viewType, $projectrecord);
	$ArtworkInfoObj = new ArtworkInformation($artworkXML);

	$TemplateProductID = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $projectrecord, $viewType);
	
	$productionProductID = Product::getProductionProductIDStatic($dbCmd, $TemplateProductID);
	
	$profileObj = new PDFprofile($dbCmd,$productionProductID, "");
	$profileObj->loadProfileByName("Proof");
	
	// For each artwork side... set the bleed parameters to 'natural' with no rotation
	$PDFobjectsArr = array();
	$counter = 0;
	foreach($ArtworkInfoObj->SideItemsArray as $ArtworkSide){

		$parametersPDFobj = new PDFparameters($profileObj);

		$parametersPDFobj->setOrderno("");
		$parametersPDFobj->setPdfSideNumber($counter);
		$parametersPDFobj->setBleedtype("N"); // Natural
		$parametersPDFobj->setSideDescription($ArtworkSide->description);
		$parametersPDFobj->setShowBleedSafe(true);
		$parametersPDFobj->setShowCutLine(true);
		$parametersPDFobj->setRotatecanvas(0);

		array_push($PDFobjectsArr, $parametersPDFobj);

		$counter++;
	}

	// Don't show the bookmarks Tab if the artwork is single sided
	if(sizeof($PDFobjectsArr) == 1)
		$ViewPreferences = "openmode=none";
	else
		$ViewPreferences = "";
	
	$data = PdfDoc::GeneratePDFsheet($dbCmd, $PDFobjectsArr, $ArtworkInfoObj, "#FFFFFF", "destination={type=fitwindow} $ViewPreferences", false);

	// Make the file name hashed from the project record... chop it down to 20 characters.
	$PDFfilename = substr(md5($projectrecord), 0, 20) . ".pdf";

}
else{
	throw new Exception("Illegal view type in ad_display_pdf.php");
}


// Put PDF on disk --##
$fp = fopen(DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $PDFfilename, "w");
fwrite($fp, $data);
fclose($fp);




// Redirect to the  PDF document
print "<html>\n\n<script>document.location = '" .  WebUtil::FilterURL(DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $PDFfilename . "?nocache=" . time()) . "';</script>\n\n</html>";


?>
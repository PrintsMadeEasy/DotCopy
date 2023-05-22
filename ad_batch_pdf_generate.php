<?

require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();




$projectlist = WebUtil::GetInput("projectlist", FILTER_SANITIZE_STRING_ONE_LINE);
$pdf_type = WebUtil::GetInput("pdf_type", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$pdf_profile = WebUtil::GetInput("pdf_profile", FILTER_SANITIZE_STRING_ONE_LINE);


$MasterList = split("\|", $projectlist);
$projectIDarr = array();

foreach($MasterList as $OrderID){

	if(preg_match("/^\d+$/", $OrderID)){
		//If it is just a pure number then it is a projecct ID
		array_push($projectIDarr, $OrderID);
	}
	else{
		if(!empty($OrderID))
			throw new Exception("Error, there is an invalid Project ID ... $OrderID");
	}
}

if(!ProjectOrdered::validateDomainPermissionOnProjectArr($dbCmd, $projectIDarr))
	throw new Exception("Error in batch PDF generate. One of the Project IDs is invalid.");


// Make this script be able to run for over an hour, if needed.
set_time_limit(5000);

// This will combine all orders into a single file and contain the exact quality... example 1000 cards may take 50 pages..  100 cards, only 5 pages.
if($pdf_type == "singlefilepdf"){

	$FileNamePrefix = "BatchWindow";

	$batchID = date("Y:m:d:H:i:s");
	$PDFfileName = PdfDoc::GenerateSingleFilePDF($dbCmd, $projectIDarr, $pdf_profile, $FileNamePrefix, $batchID);

	// Redirect to the  PDF document
	print "<html>\n\n<script>document.location = '" .  WebUtil::FilterURL(DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $PDFfileName) . "';</script>\n\n</html>";
	
}
// This option will write each Project to its own file and follow a certain naming sequence for the files
else if($pdf_type == "multifiles"){


	// Gather All of the pre-generated PDF files at put them in a ZIP File 
	// Get a string that we can use for a prefix to the file names of the PDF's... lets use the time..hours-minutes-seconds
	// This will allow the Batch to be grouped together
	//$PDF_prefix = date("G-i-s_");
	$PDF_prefix = "";

	// The name of the Tar file should be unique
	$TarFilePrefix = date("G-i-s_");


	$PDFfilenamesArr = array();  //Will contain a list of all filenames that are written to disk


	// Write all of the regular PDF files to disk
	foreach($projectIDarr as $ProjectOrderID){
		
		ProjectBase::EnsurePrivilagesForProject($dbCmd, "ordered", $ProjectOrderID);

		// This array will contain a list of Objects used to generate a side of a PDF document...  EX:  There may be one for "Front" and one for "Back"
		$PDFobjectsArr = PDFparameters::GetPDFparametersForProjectOrderedArr($dbCmd, $ProjectOrderID, $pdf_profile);

			
		// Come up with a good description for this Order... Order Numbers, Options, Shipping Method, etc.
		$thisProjectObj = ProjectOrdered::getObjectByProjectID($dbCmd, $ProjectOrderID);
		$orderDescription = $thisProjectObj->getOrderID() . " - P" . $ProjectOrderID . " >> " . $thisProjectObj->getOrderDescription() . " - (" . $thisProjectObj->getOptionsAliasStr(true) . ") ";
		$orderDescription .= " >> Est. Ship Date:" . date("n/j/y", $thisProjectObj->getEstShipDate()) . " - " . ShippingChoices::getChoiceName(Order::getShippingChoiceIDfromOrder($dbCmd, $thisProjectObj->getOrderID()));
	
		$artInfoObj = new ArtworkInformation($thisProjectObj->getArtworkFile());
		
		for($i=0; $i < sizeof($PDFobjectsArr); $i++){
			
			// If there is a custom color palette, write those colors on the PDF in the order area.
			if(isset($artInfoObj->SideItemsArray[$i])){
				
				$colorDefArr = $artInfoObj->GetColorDescriptions($i);
				
				if(!empty($colorDefArr))
					$orderDescription .= " >> Palette: " . implode(", ", $colorDefArr);
			}
			$PDFobjectsArr[$i]->setOrderno($orderDescription);
		}

		

		// Skip over projects which don't have pre-generated PDF files yet
		if(sizeof($PDFobjectsArr) <> 0){
			$ProjectPDFfilename = WritePDFfiletoDisk($dbCmd, $ProjectOrderID, $PDFobjectsArr, $PDF_prefix);
			array_push($PDFfilenamesArr, $ProjectPDFfilename);
		}

		//Make the loop wait 1/10 of a second between PDF files
		usleep(100000);
	}

	if(sizeof($PDFfilenamesArr) == 0)
		throw new Exception("No PDF settings recorded");


	if(Constants::GetDevelopmentServer()){

		// Since it is the development server... Redirect to the first PDF document.  I don't have the TAR command on this system
		header("Location: " . WebUtil::FilterURL($PDFfilenamesArr[0]));

	}
	else{

		$TarFileName = DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $TarFilePrefix . ".tar";
		$TarFileWebLoation = DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $TarFilePrefix . ".tar";

		$UnixTarCommand = Constants::GetTarFileListCommand($TarFileName , $PDFfilenamesArr);
		system($UnixTarCommand);

		// Make sure that we have permission to modify it with system comands
		chmod ($TarFileName, 0666);

		// Redirect to the tar file we just created
		header("Location: " . WebUtil::FilterURL($TarFileWebLoation));

	}

}
else{
	throw new Exception("Problem with PDF type");
}









// Writes the appropriate file to the disk and returns the new file name
function WritePDFfiletoDisk(DbCmd $dbCmd, $projectrecord, $PDFobjectsArr, $PDF_prefix){

	// Get the Artwork file from the DB
	$xmlString = ArtworkLib::GetArtXMLfile($dbCmd, "proof", $projectrecord);

	// Parse the xml document and populate and Object will all the info we need
	$ArtworkInfoObj = new ArtworkInformation($xmlString);


	// Get a file name for the PDF based off of the Project Information
	$projectObj = ProjectOrdered::getObjectByProjectID($dbCmd, $projectrecord);

	// This will generate the PDF document and return its contents 
	$data = PdfDoc::GeneratePDFsheet($dbCmd, $PDFobjectsArr, $ArtworkInfoObj, "#FFFFFF", "", $projectObj->isVariableData());

	$PDFfilename = PdfDoc::GetFileNameForPDF($projectObj, $PDF_prefix);

	// Put PDF on disk
	$fp = fopen(DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $PDFfilename, "w");
	fwrite($fp, $data);
	fclose($fp);

	return DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $PDFfilename;
}


?>
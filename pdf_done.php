<?

require_once("library/Boot_Session.php");




// PDFs may take a long time to generate on very large artworks, especially with Variable Images.  Give it an hour.
set_time_limit(3600);


$dbCmd = new DbCmd();

$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$projectid = WebUtil::GetInput("projectid", FILTER_SANITIZE_INT);
$showDataMerge = WebUtil::GetInput("showDataMerge", FILTER_SANITIZE_STRING_ONE_LINE);
$mailbtchid = WebUtil::GetInput("mailbtchid", FILTER_SANITIZE_INT);
$pdf_profile = WebUtil::GetInput("pdf_profile", FILTER_SANITIZE_STRING_ONE_LINE);


if(empty($mailbtchid) && empty($projectid))
	throw new Exception("There is a missing parameter in pdf_done.php");


$t = new Templatex();

$t->set_file("origPage", "./pdf_done-template.html");

if($view == "proof"){

	// Make sure they are logged in and are an administrator.
	$AuthObj = new Authenticate(Authenticate::login_ADMIN);
	if(!$AuthObj->CheckForPermission("PROOF_ARTWORK"))
		throw new Exception("permission denied.");
		
	ProjectBase::EnsurePrivilagesForProject($dbCmd, $view, $projectid);
}
else if($view == "mailingbatch"){

	// Make sure they are logged in and are an administrator to generate artworks for an administrator.
	$AuthObj = new Authenticate(Authenticate::login_ADMIN);
	
}
else{
	ProjectBase::EnsurePrivilagesForProject($dbCmd, $view, $projectid);
}




// If we don't specify a profile, then default to the "Proof" pdf profile.
if(empty($pdf_profile))
	$pdf_profile = "Proof";




if(!empty($projectid)){
	$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $view, $projectid);
	$ArtworkInfoObj = new ArtworkInformation($projectObj->getArtworkFile());



	if($showDataMerge && !$projectObj->isVariableData())
		WebUtil::PrintError("You can not show the data merge if the project does not contain variable data");
	if($showDataMerge && !($projectObj->getVariableDataStatus() == "G" || $projectObj->getVariableDataStatus() == "W"))
		WebUtil::PrintError("A PDF with data merge can not be displayed if there are any Data Errors or configuration problems.");


	$profileObj = new PDFprofile($dbCmd,$projectObj->getProductID(), $projectObj->getOptionsAliasStr());
	$profileObj->loadProfileByName($pdf_profile);




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

		// Find out if this is a project that has already been ordered... and if the PDF settings are saved.
		if($projectObj->getViewTypeOfProject() == "ordered"){

			if(ProjectOrdered::CheckIfBleedSettingsAreSaved($dbCmd, $projectid)){

				$PDFobjArr2 = PDFparameters::GetPDFparametersForProjectOrderedArr($dbCmd, $projectid, $pdf_profile);

				$bleedTypeSelected = $PDFobjArr2[$counter]->getBleedtype();
				$canvasRotation = $PDFobjArr2[$counter]->getRotatecanvas();

				$parametersPDFobj->setBleedtype($bleedTypeSelected);
				$parametersPDFobj->setRotatecanvas($canvasRotation);

				// for Tile bleed... don't show the cut line so that we can clearly see the intersection.
				if($bleedTypeSelected == "T")
					$parametersPDFobj->setShowCutLine(false);

				// If there is no bleed... then we don't want to show any bleed/safe lines so that you can clearly see where the artwork is chopped off.
				if($bleedTypeSelected == "V")
					$parametersPDFobj->setShowBleedSafe(false);
			}
		}

		array_push($PDFobjectsArr, $parametersPDFobj);

		$counter++;
	}



	// Don't show the bookmarks Tab if the artwork is single sided
	if(sizeof($PDFobjectsArr) == 1)
		$ViewPreferences = "openmode=none";
	else
		$ViewPreferences = "";



	// Make the file name hashed from the project record... chop it down to 20 characters.
	$PDFfilename = substr(md5($projectid . Constants::getGeneralSecuritySalt() . md5($projectObj->getArtworkFile())), 0, 20) . ".pdf";


	// Redirect to the  PDF document
	// A cron job will need to delete the proofs every 2 hours or so to keep the disk from getting full
	
	// ------ For Debugging on the live server... so it won't redirect to the PDF on errors.... but it doens't disturb the rest of the users on the website.
	//if(WebUtil::getRemoteAddressIp() != "69.63.86.138")
		$t->set_var("PDFLOCATION", DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $PDFfilename . "?nocache=" . time());
	//else
	//	$t->set_var("PDFLOCATION", "");
	
}





// To generate Mailing Batches... we want to show output progress.
if(!empty($mailbtchid)){
	
	// Make sure they are logged in and are an administrator to generate artworks for an administrator.
	$AuthObj = new Authenticate(Authenticate::login_ADMIN);

	$UserID = $AuthObj->GetUserID();
	
	if(!MailingBatch::checkIfUserHasPermissionToSeeBatchID($dbCmd, $mailbtchid))
		throw new Exception("User does not have permission to generate a PDF for this Mailing Batch: $mailbtchid");
	
	$mailBatchObj = new MailingBatch($dbCmd, $UserID);
	
	$mailBatchObj->loadBatchByID($mailbtchid);

	$PDFfilename = $mailBatchObj->getFileNameOfPDFforBatch();

	

	// ------ For Debugging on the live server... so it won't redirect to the PDF on errors.... but it doens't disturb the rest of the users on the website.

	//if(WebUtil::getRemoteAddressIp() != "69.63.86.138")
		$t->set_var("PDFLOCATION", DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $PDFfilename . "?nocache=" . time());
	//else
	//	$t->set_var("PDFLOCATION", "");
	
	$t->discard_block("origPage", "RegularBL");
	
	$t->pparse("OUT","origPage");

	// Print the page to the browser as we have it... There may be other data flushed out while the PDF is being generated (if it has a large data file)
	Constants::FlushBufferOutput();
	sleep(1);
	
	$projectSortingObj = $mailBatchObj->getProjectSortingObj();

	$mailingBatchPDF =& PdfDoc::generatePDFforMailing($dbCmd, $projectSortingObj, $pdf_profile, "", "javascript", ("Mailing Batch ID: " . $mailbtchid) );


	// Put PDF on disk
	$fp = fopen(DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $PDFfilename, "w");
	fwrite($fp, $mailingBatchPDF);
	fclose($fp);

}

// If there is a Large Data file for variable data then it may flush data out to the browser to keep the browser from timing out and to give a percentage of progress
else if($projectObj->isVariableData() && $showDataMerge && $projectObj->checkIfVariableDataIsLarge()){
	
	VisitorPath::addRecord("PDF Preview With Merge");
	
	$t->discard_block("origPage", "RegularBL");
	
	$t->pparse("OUT","origPage");

	// Print the page to the browser as we have it... There may be other data flushed out while the PDF is being generated (if it has a large data file)
	Constants::FlushBufferOutput();

	$outputProgress = "javascript";
	$data = PdfDoc::generateVariableDataPDF($dbCmd, $projectid, $view, false, $PDFobjectsArr, "#FFFFFF", "", $outputProgress);

	// Put PDF on disk
	$fp = fopen(DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $PDFfilename, "w");
	fwrite($fp, $data);
	fclose($fp);

	exit;


}
else{

	VisitorPath::addRecord("PDF Preview");
	
	// This could be a Variable Data file with Merge... or not... but we are not flushing progress to the browser.

	$t->discard_block("origPage", "LargeVariableDataBL");


	if($projectObj->isVariableData() && $showDataMerge){
		$outputProgress = "none";
		$data = PdfDoc::generateVariableDataPDF($dbCmd, $projectid, $view, false, $PDFobjectsArr, "#FFFFFF", "", $outputProgress);
	}
	else{
		$data = PdfDoc::GeneratePDFsheet($dbCmd, $PDFobjectsArr, $ArtworkInfoObj, "#FFFFFF", "destination={type=fitwindow} $ViewPreferences", $projectObj->isVariableData());
	}
	
	
	// Put PDF on disk
	$fp = fopen(DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $PDFfilename, "w");
	fwrite($fp, $data);
	fclose($fp);
	

	$t->pparse("OUT","origPage");
	
	exit;
}









?>
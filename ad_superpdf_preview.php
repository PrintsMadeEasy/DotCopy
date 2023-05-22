<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();




if(!$AuthObj->CheckForPermission("EDIT_SUPER_PDF_PROFILE"))
		throw new Exception("You don't have permission to delete a Super PDF profile.");


$superPDFProfileID = WebUtil::GetInput("superPDFProfileID", FILTER_SANITIZE_INT);


	
// Make sure that the user doesn't switch domains after they have selected a Super Profile ID
// If we don't do this, it isn't harmful, but it could be confusing to the user.
if(!empty($superPDFProfileID)){
	
	$domainIDofSuperProfile = SuperPDFprofile::getDomainIDofSuperProfile($superPDFProfileID);
	
	if(!$AuthObj->CheckIfUserCanViewDomainID($domainIDofSuperProfile))
		throw new Exception("The Product belongs to another domain or it can't be viewed.");
		
	Domain::enforceTopDomainID($domainIDofSuperProfile);
}	
	


$superProfileObj = new SuperPDFprofile();
$superProfileObj->loadSuperPDFProfileByID($superPDFProfileID);

$subProfileIdsArr = $superProfileObj->getSubPDFProfileIDs();


if(empty($subProfileIdsArr))
	WebUtil::PrintAdminError("Can not generate a preview because there are no Sub Profiles");
	

$gangRunObj = new GangRun($dbCmd);
$gangRunObj->initializeNewGangRun(1, $superPDFProfileID, false, true);

$gangRunObj->setBatchNotes("This is a preview of the Super PDF Profile notes");

$gangRunObj->setMaterialDesc("Preview of the Material Description");

$gangRunObj->setPreviewMode(true);


// We are just going to make fake project IDs... starting at 1, and incrementing for each slot number.
$projectIDcounter = 1;

foreach($subProfileIdsArr as $thisSubProfileID){
	
	$productIDofProfile = PDFprofile::getProductIDfromProfileID($dbCmd, $thisSubProfileID);
	
	$subProfileObj = new PDFprofile($dbCmd, $productIDofProfile);
	$subProfileObj->loadProfileByID($thisSubProfileID);
	$numberOfSlots = $subProfileObj->getQuantity();

	for($i=0; $i<$numberOfSlots; $i++){
		$gangRunObj->manuallySetProjectIDdetails($projectIDcounter, 1, $thisSubProfileID);
		$projectIDcounter++;
	}
	
}




$superPDFprofilePreviewData = $gangRunObj->getPrevewPDF();


$PDFfilename = "PDF_Super_Preview_" . substr(md5($superPDFProfileID . "abffcdefg" . time()), 0, 10) . ".pdf";

// Put PDF on disk
$fp = fopen(DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $PDFfilename, "w");
fwrite($fp, $superPDFprofilePreviewData);
fclose($fp);


$fileDownloadLocation = WebUtil::FilterURL(DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $PDFfilename);


header("Location: " . $fileDownloadLocation);
exit;

?>

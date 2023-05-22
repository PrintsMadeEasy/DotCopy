<?

require_once("library/Boot_Session.php");




// PDFs may take a long time to generate on very large artworks, especially with Variable Images. .
set_time_limit(3600);
ini_set("memory_limit", "512M");

$dbCmd = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("GANG_RUNS"))
	throw new Exception("Permission Denied");

$current_action = WebUtil::GetInput("current_action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "ShowGangsList");



$projectIDlist = WebUtil::GetInput("projectlist", FILTER_SANITIZE_STRING_ONE_LINE);
$superPDFprofileID = WebUtil::GetInput("superpdfprofileID", FILTER_SANITIZE_INT);
$sheet_quantity = WebUtil::GetInput("sheet_quantity", FILTER_SANITIZE_INT);
$side_count = WebUtil::GetInput("side_count", FILTER_SANITIZE_STRING_ONE_LINE);
$quantity_preference = WebUtil::GetInput("quantity_preference", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$materialDesc = WebUtil::GetInput("materialDesc", FILTER_SANITIZE_STRING_ONE_LINE);
$batchNotes = WebUtil::GetInput("batchNotes", FILTER_SANITIZE_STRING_ONE_LINE);
$printingPressID = WebUtil::GetInput("printingPressID", FILTER_SANITIZE_INT);
$confirmedBy = WebUtil::GetInput("confirmedBy", FILTER_SANITIZE_STRING_ONE_LINE);
$downloadFileType = WebUtil::GetInput("downloadFileType", FILTER_SANITIZE_STRING_ONE_LINE);
$force_quantity = WebUtil::GetInput("force_quantity", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);


$projectIDarr = WebUtil::getArrayFromPipeDelimetedString($projectIDlist);
$gangRunID = WebUtil::GetInput("gangRunID", FILTER_SANITIZE_INT);





// In the future the we should be getting the Printing Press IDs out of a Multi-select list or something.
// For now let's just use all Printing Presses in the users selected domains.
//$possiblPrintingPressesArr = WebUtil::GetInputArr("possiblPrintingPressesArr", FILTER_SANITIZE_INT);
$possiblPrintingPressesArr = array_keys(PrintingPress::getPrintingPressList($AuthObj->getUserDomainsIDs()));


$t = new Templatex(".");


$t->set_file("origPage", "ad_gangRun-template.html");





// Fill in Variables within the Title Bar.
if(!empty($gangRunID)){

	$gangObj = new GangRun($dbCmd);
	$gangObj->loadGangRunID($gangRunID);
	
	
	// Possibly update the Database with new parameters on this GangRun
	if($current_action == "UpdateGangRun"){
	
		$gangObj->setPrintingPressID($printingPressID);
		$gangObj->setMaterialDesc($materialDesc);
		$gangObj->setBatchNotes($batchNotes);
		
		$gangObj->updateGangRun();
	
	}
	
	
	$isDuplexFlag = $gangObj->getDuplexFlag();
	
	$force_quantity = ($gangObj->getForcedQuantity() ? "Y" : "N");
	
	$sheet_quantity = $gangObj->getSheetQuantity();
	
	$t->set_var("SIZE_PREFERENCE", "");

	$t->set_var("GANG_ID_TITLE", " G" . $gangRunID);
	
}
else{

	if($side_count == "single")
		$isDuplexFlag = false;
	else if($side_count == "double")
		$isDuplexFlag = true;
	else
		throw new Exception("Error with Duplex Flag.  Must be a string 'single' or 'double'");


	if($quantity_preference == "small")
		$preferBigRuns = true;
	else if($quantity_preference == "large")
		$preferBigRuns = false;
	else
		throw new Exception("Error with quantity_preference.  Must be a sting 'small' or 'large'");
		
		
	if($preferBigRuns)
		$t->set_var("SIZE_PREFERENCE", "Large");
	else
		$t->set_var("SIZE_PREFERENCE", "Short");
		
	
	$t->set_var("GANG_ID_TITLE", "");
}




if($force_quantity != "Y")
	$t->set_var("SHEET_QUANTITY", GangRun::translateSheetQuantity($sheet_quantity));
else
	$t->set_var("SHEET_QUANTITY", "Forced Quantity: " . $sheet_quantity);



if($isDuplexFlag)
	$t->set_var("SIDES", "Duplex");
else
	$t->set_var("SIDES", "Single-Sided");



if($current_action == "ShowGangsList" || $current_action == "StartGangRun"){
	
	$gangBuilderObj = new GangRunBuilder($dbCmd, $possiblPrintingPressesArr, $sheet_quantity, $isDuplexFlag, ($force_quantity == "Y" ? true: false));

	$gangBuilderObj->preferBigRunsFirst($preferBigRuns);

	// If the user has selected something other than automatic... set the Profile preference.
	if($superPDFprofileID != "auto")
		$gangBuilderObj->setProfileMandatory($superPDFprofileID);

}



if($current_action == "ShowGangsList"){

	$tempArr = array();

	// Make sure that all Projects have Proofed status.
	foreach($projectIDarr as $thisProjectID){

		$projectStatus = ProjectOrdered::GetProjectStatus($dbCmd, $thisProjectID);

		if(in_array($projectStatus, array("P", "D")))
			$tempArr[] = $thisProjectID;
	}
	
	$projectIDarr = $tempArr;
		
	if(empty($projectIDarr))
		gangRunErrorMessage($t, "Can not start a Gang Run with these ProjectID's because none of them have a status of Proofed.");



	// Ads the Project ID's in the pool to query from... it will return False (and subsequently print an error) if one of the Project ID's inside does not have a consistent status.
	if(!$gangBuilderObj->setProjectIDsInPool($projectIDarr))
		gangRunErrorMessage($t, "An unknown error occured creating a GangRun.  All projects should have a status of Proofed.");


	
	$gangBuilderObj->gatherGangs();


	if($gangBuilderObj->getNumberOfGangRuns() == 0)
		gangRunErrorMessage($t, "None of the projects are able to fit in a Gang Run.  Close this window and try again.");



	$gangObjectsArr = $gangBuilderObj->getGangObjectsArr();


	$t->set_block("origPage","GangBL","GangBLout");

	$counter = 1;
	foreach($gangObjectsArr as $thisGangObj){

		$t->set_var("SCORE", $thisGangObj->getHTMLscoreDesc());
		$t->allowVariableToContainBrackets("SCORE");
		
		$t->set_var("PROFILE_DESC", $thisGangObj->getHTMLprofileDesc());
		$t->allowVariableToContainBrackets("PROFILE_DESC");

		$t->set_var("OPTIONS", $thisGangObj->getOptionsHTML());
		$t->allowVariableToContainBrackets("OPTIONS");
		
		$t->set_var("SUPERPROFILEID", $thisGangObj->getSuperProfileID());
		
		// Build a new pip e delimited list... since we may have filtered out some Project IDs.
		$projectListStr = WebUtil::getPipeDelimetedStringFromArr($thisGangObj->getProjectIDlist());

		$t->set_var("PROJECTLIST", $projectListStr);
		
		$t->set_var("FORCE_QUANTITY", $force_quantity);

		$t->set_var("COUNTER", $counter);
		$counter++;

		$t->parse("GangBLout","GangBL",true);
	}
	
	

	
	$t->set_var("SHEETCOUNT", $sheet_quantity);
	$t->set_var("SIDECOUNT", $side_count);
	$t->set_var("QUANTITYPREF", $quantity_preference);

	$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());

	$t->discard_block("origPage", "ConfirmBlock");
	$t->discard_block("origPage", "ErrorMessage");
	$t->discard_block("origPage", "ProgressBL");
	$t->discard_block("origPage", "GangRunIDbl");
	$t->discard_block("origPage", "DownloadBlock");
	$t->discard_block("origPage", "SuyperProfileName");
	
	
	$t->pparse("OUT","origPage");

	
}
else if($current_action == "StartGangRun"){


	// Ads the Project ID's in the pool to query from... it will return False (and subsequently print an error) if one of the Project ID's inside does not have a consistent status.
	if(!$gangBuilderObj->setProjectIDsInPool($projectIDarr))
		gangRunErrorMessage($t, "Can not start a Gang Run with these ProjectID's because one or more of them have a status which is not 'Proofed' or 'For Offset'.  Maybe a status was changed on one of the projects (by another person) since you first generated this pop-up window?");

	
	$gangBuilderObj->gatherGangs();


	if($gangBuilderObj->getNumberOfGangRuns() == 0)
		gangRunErrorMessage($t, "None of the projects are able to fit in a Gang Run.  Maybe a the status was changed on one of the projects (by another person) since you first generated this pop-up window?  Close this window and try again.");



	$gangObjectsArr = $gangBuilderObj->getGangObjectsArr();
	
	$gangObj = current($gangObjectsArr);
	
	
	$gangObj->setMaterialDesc($materialDesc);
	
	// Make the Batch Notes default to the Super PDF Profile Notes.
	$gangObj->setBatchNotes($batchNotes);
	$gangObj->setPrintingPressID($printingPressID);
	
	
	$t->discard_block("origPage", "ConfirmBlock");
	$t->discard_block("origPage", "ErrorMessage");
	$t->discard_block("origPage", "GangResults");
	$t->discard_block("origPage", "GangRunIDbl");
	$t->discard_block("origPage", "DownloadBlock");
	$t->discard_block("origPage", "SuyperProfileName");
	
	
	$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());
	
	$t->pparse("OUT","origPage");
	
	Constants::FlushBufferOutput();
	
	
	// The output has already processed down to the closing </html> tab.
	// Now we are going to save the project... which will output javascript at the bottom (showing progress percentages).
	
	$gangID = $gangObj->saveNewGangRun($UserID, false, "javascript");

	print "\n<script>document.location = './ad_gangRun.php?current_action=ShowGangRunID&form_sc={FORM_SECURITY_CODE}&gangRunID=" . $gangID . "';</script>\n";

}
else if($current_action == "ShowGangRunID"){


	$gangObj = new GangRun($dbCmd);

	if(!$gangObj->loadGangRunID($gangRunID))
		gangRunErrorMessage($t, "An unknown error has occured.  gangRunID: " . $gangRunID . " can not be loaded.");
	
	$t->set_var("PROFILE_DESC", $gangObj->getHTMLprofileDesc());
	$t->allowVariableToContainBrackets("PROFILE_DESC");

	$t->set_var("OPTIONS", $gangObj->getOptionsHTML());
	$t->allowVariableToContainBrackets("OPTIONS");
	
	$t->set_var("PDF_FILES", $gangObj->getPDFfilesHTML());
	$t->allowVariableToContainBrackets("PDF_FILES");
	
	$t->set_var("GANGID", $gangRunID);
	
	$t->set_var("SUPER_PDF_PROFILE_NAME", WebUtil::htmlOutput($gangObj->getSuperProfileObj()->getSuperPDFProfileName()));
	
	$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());
	
	
	$t->discard_block("origPage", "ConfirmBlock");
	$t->discard_block("origPage", "ErrorMessage");
	$t->discard_block("origPage", "ProgressBL");
	$t->discard_block("origPage", "GangResults");
	$t->discard_block("origPage", "DownloadBlock");
	
	header("Connection: close");  // To keep the thumbnails from timing out.
	$t->pparse("OUT","origPage");
	
}
else if($current_action == "UpdateGangRun"){


	WebUtil::checkFormSecurityCode();
	
	$gangObj = new GangRun($dbCmd);

	if(!$gangObj->loadGangRunID($gangRunID))
		gangRunErrorMessage($t, "An unknown error has occured.  gangRunID: " . $gangRunID . " can not be loaded.");
	
	$t->set_var("PROFILE_DESC", $gangObj->getHTMLprofileDesc());
	$t->allowVariableToContainBrackets("PROFILE_DESC");

	$t->set_var("OPTIONS", $gangObj->getOptionsHTML());
	$t->allowVariableToContainBrackets("OPTIONS");
	
	$t->set_var("PDF_FILES", $gangObj->getPDFfilesHTML());
	$t->allowVariableToContainBrackets("PDF_FILES");
	
	$t->set_var("GANGID", $gangRunID);
	
	
	$t->discard_block("origPage", "ConfirmBlock");
	$t->discard_block("origPage", "ErrorMessage");
	$t->discard_block("origPage", "GangRunIDbl");
	$t->discard_block("origPage", "GangResults");
	$t->discard_block("origPage", "DownloadBlock");
	$t->discard_block("origPage", "SuyperProfileName");
	
	$t->pparse("OUT","origPage");
	
	
	print "\n<script>";
	print "ShowCurrentTask('<b>Status:</b> Updating Gang Run');";
	print "</script>\n";

	Constants::FlushBufferOutput();
	
	sleep(1);
	
	// If we are updating the Notes, or CardStock... then the front side and cover has to be updated because the PDF file will be different.
	$gangObj->updateFrontSidePDFinDB("javascript", true);
	$gangObj->updateCoverSheetPDFinDB("javascript", true);
	
	print "\n<script>document.location = './ad_gangRun.php?current_action=ShowGangRunID&form_sc={FORM_SECURITY_CODE}&gangRunID=" . $gangRunID . "';</script>\n";
	
	
}
else if($current_action == "CancelGangRun"){

	WebUtil::checkFormSecurityCode();

	$gangObj = new GangRun($dbCmd);

	if(!$gangObj->loadGangRunID($gangRunID))
		gangRunErrorMessage($t, "An unknown error has occured for CancelGangRun.  gangRunID: " . $gangRunID . " can not be loaded.");
		
	$gangObj->cancelGangRun($UserID);
	
	
	
	// Replace Gang ID with Title.
	$t->set_block("origPage","GangRunTitleBL","GangRunTitleBLout");
	$t->set_var("GangRunTitleBLout", "Gang Run G" . $gangRunID . " Canceled");
	
	
	$t->set_var("CONFIRM_MESSAGE", "Gang Run G" . $gangRunID . " has been canceled. <br><br><a href='javascript:self.close()' class='BlueRedLink'>Click here</a> to close this window. <script>setTimeout('self.close()', 1000);</script>");
	$t->allowVariableToContainBrackets("CONFIRM_MESSAGE");
	
	$t->discard_block("origPage", "ErrorMessage");
	$t->discard_block("origPage", "ProgressBL");
	$t->discard_block("origPage", "GangResults");
	$t->discard_block("origPage", "GangRunIDbl");
	$t->discard_block("origPage", "DownloadBlock");
	$t->discard_block("origPage", "SuyperProfileName");
	
	$t->pparse("OUT","origPage");
		
}
else if($current_action == "ConfirmGangRun"){

	WebUtil::checkFormSecurityCode();
	
	$gangObj = new GangRun($dbCmd);

	if(!$gangObj->loadGangRunID($gangRunID))
		gangRunErrorMessage($t, "An unknown error has occured for ConfirmGangRun.  gangRunID: " . $gangRunID . " can not be loaded.");
		
	$confirmationError = $gangObj->confirmGangRun($gangObj->getGangCode(), $confirmedBy, $UserID);
	
	if(!empty($confirmationError))
		gangRunErrorMessage($t, $confirmationError);
	
	
	// Replace Gang ID with Title.
	$t->set_block("origPage","GangRunTitleBL","GangRunTitleBLout");
	$t->set_var("GangRunTitleBLout", "<b>Gang Run G" . $gangRunID . " Confirmed</b>");
	
	
	$t->set_var("CONFIRM_MESSAGE", "<b>Gang Run G" . $gangRunID . " has been confirmed.</b><br><br>The statuses on all projects have been changed to 'Printed'. <br><br><a href='javascript:self.close()' class='BlueRedLink'>Click here</a> to close this window.");
	$t->allowVariableToContainBrackets("CONFIRM_MESSAGE");
	
	
	$t->discard_block("origPage", "ErrorMessage");
	$t->discard_block("origPage", "ProgressBL");
	$t->discard_block("origPage", "GangResults");
	$t->discard_block("origPage", "GangRunIDbl");
	$t->discard_block("origPage", "DownloadBlock");
	$t->discard_block("origPage", "SuyperProfileName");
	
	$t->pparse("OUT","origPage");
		
}
else if($current_action == "saveFileToDisk"){


	$gangObj = new GangRun($dbCmd);

	if(!$gangObj->loadGangRunID($gangRunID))
		gangRunErrorMessage($t, "An unknown error has occured for ViewPDF.  gangRunID: " . $gangRunID . " can not be loaded.");
	
	
	
	// Replace Gang ID with Title.
	$t->set_block("origPage","GangRunTitleBL","GangRunTitleBLout");
	$t->set_var("GangRunTitleBLout", "<b>Gang Run G" . $gangRunID . " Preparing File Download " . $downloadFileType . "</b>");
	
	
	$t->discard_block("origPage", "ConfirmBlock");
	$t->discard_block("origPage", "ErrorMessage");
	$t->discard_block("origPage", "GangResults");
	$t->discard_block("origPage", "GangRunIDbl");
	$t->discard_block("origPage", "DownloadBlock");
	$t->discard_block("origPage", "SuyperProfileName");
	
	
	$t->pparse("OUT","origPage");
	
	
	
	print "\n<script>";
	print "ShowCurrentTask('<b>Status:</b> Preparing File for Download.');";
	print "</script>\n";

	Constants::FlushBufferOutput();
	
	sleep(1);
	
	
	
	$theFileName = $gangObj->getFileForDownload("javascript", $downloadFileType);
	
	print "\n<script>";
	print "ShowCurrentTask('<b>Status:</b> Redirecting to " . addslashes($theFileName) . "');";
	print "</script>\n";
	
	Constants::FlushBufferOutput();
	
	sleep(1);
	
	print "\n<script>document.location = './ad_gangRun.php?current_action=DownloadFile&form_sc={FORM_SECURITY_CODE}&downloadFileType=" . $downloadFileType . "&gangRunID=" . $gangRunID . "';</script>\n";
}	
else if($current_action == "DownloadFile"){

	$gangObj = new GangRun($dbCmd);

	if(!$gangObj->loadGangRunID($gangRunID))
		gangRunErrorMessage($t, "An unknown error has occured for ViewPDF.  gangRunID: " . $gangRunID . " can not be loaded.");
	
	
	
	// Replace Gang ID with Title.
	$t->set_block("origPage","GangRunTitleBL","GangRunTitleBLout");
	$t->set_var("GangRunTitleBLout", "<b>Gang Run G" . $gangRunID . " File Download " . $downloadFileType . "</b>");
	
	
	$t->discard_block("origPage", "ConfirmBlock");
	$t->discard_block("origPage", "ErrorMessage");
	$t->discard_block("origPage", "GangResults");
	$t->discard_block("origPage", "GangRunIDbl");
	$t->discard_block("origPage", "ProgressBL");
	$t->discard_block("origPage", "SuyperProfileName");
	
	
	
	$theFileName = $gangObj->getFileForDownload("javascript", $downloadFileType);
	
	$fullPath = DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $theFileName;
	
	$t->set_var("DOWNLOAD_FILENAME", WebUtil::htmlOutput($theFileName));
	$t->set_var("DOWNLOAD_BASE", DomainPaths::getPdfWebPathOfDomainInURL());
	
	
	$t->pparse("OUT","origPage");
	
	Constants::FlushBufferOutput();
	
	sleep(1);
	
	
	
	print "\n<script>document.location = '" . $fullPath . "';</script>\n";

}
else{

	throw new Exception("Illegal Action type called.");
}









function gangRunErrorMessage(&$templateObj, $errorMessage){

	$templateObj->set_var("ERROR_MESSAGE", WebUtil::htmlOutput($errorMessage));

	$templateObj->discard_block("origPage", "ConfirmBlock");
	$templateObj->discard_block("origPage", "GangResults");
	$templateObj->discard_block("origPage", "ProgressBL");
	$templateObj->discard_block("origPage", "GangRunIDbl");
	$templateObj->discard_block("origPage", "DownloadBlock");
	$templateObj->pparse("OUT","origPage");
	exit;

}



?>
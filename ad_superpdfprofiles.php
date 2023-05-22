<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();




if(!$AuthObj->CheckForPermission("EDIT_SUPER_PDF_PROFILE"))
		throw new Exception("You don't have permission to delete a Super PDF profile.");



$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$superPDFProfileID = WebUtil::GetInput("superPDFProfileID", FILTER_SANITIZE_INT);


if(empty($view))
	$view = "start";


	
// Make sure that the user doesn't switch domains after they have selected a Super Profile ID
// If we don't do this, it isn't harmful, but it could be confusing to the user.
if(!empty($superPDFProfileID)){
	
	$domainIDofSuperProfile = SuperPDFprofile::getDomainIDofSuperProfile($superPDFProfileID);
	
	if(!$AuthObj->CheckIfUserCanViewDomainID($domainIDofSuperProfile))
		throw new Exception("The Product belongs to another domain or it can't be viewed.");
		
	Domain::enforceTopDomainID($domainIDofSuperProfile);
}	
	


// Super PDF profiles belong specifically to a domain.
Domain::oneDomain();



if($action){
	
	WebUtil::checkFormSecurityCode();

	if($action == "SaveNewProfile"){
		
		$superProfileObj = new SuperPDFprofile($dbCmd);
		
		$superProfileObj->setSheetWidth(WebUtil::GetInput("superPDFProfileWidth", FILTER_SANITIZE_FLOAT));
		$superProfileObj->setSheetHeight(WebUtil::GetInput("superPDFProfileHeight", FILTER_SANITIZE_FLOAT));
		$superProfileObj->setBarcodeX(WebUtil::GetInput("superPDFProfileBarcodeX", FILTER_SANITIZE_FLOAT));
		$superProfileObj->setBarcodeY(WebUtil::GetInput("superPDFProfileBarcodeY", FILTER_SANITIZE_FLOAT));
		$superProfileObj->setSuperPDFProfileNotes(WebUtil::GetInput("profileNotes", FILTER_SANITIZE_STRING_ONE_LINE));
		$superProfileObj->setBarcodeRotateDeg(WebUtil::GetInput("superPDFProfileBarcodeRotation", FILTER_SANITIZE_NUMBER_INT));
		$superProfileObj->setPrintingPressIDs(WebUtil::GetInputArr("printingPressIDs", FILTER_SANITIZE_STRING_ONE_LINE));
		
		
		
		$superPDFProfileID = $superProfileObj->addSuperPDFProfile(WebUtil::GetInput("SuperPDFProfileName", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES));
		
		// Redirect to the Edit page for the new Profile we just created.
		header("Location: ./" . WebUtil::FilterURL("ad_superpdfprofiles.php?view=editSuperPDFProfile&superPDFProfileID=" . $superPDFProfileID));
		exit;

	}
	else if( $action == "UpdateProfile"){
		
		$superProfileObj = new SuperPDFprofile($dbCmd);
			

		$superProfileObj->loadSuperPDFProfileByID($superPDFProfileID);
		
		$superProfileObj->setSheetWidth(WebUtil::GetInput("superPDFProfileWidth", FILTER_SANITIZE_FLOAT));
		$superProfileObj->setSheetHeight(WebUtil::GetInput("superPDFProfileHeight", FILTER_SANITIZE_FLOAT));
		$superProfileObj->setBarcodeX(WebUtil::GetInput("superPDFProfileBarcodeX", FILTER_SANITIZE_FLOAT));
		$superProfileObj->setBarcodeY(WebUtil::GetInput("superPDFProfileBarcodeY", FILTER_SANITIZE_FLOAT));
		$superProfileObj->setSuperPDFProfileNotes(WebUtil::GetInput("profileNotes", FILTER_SANITIZE_STRING_ONE_LINE));
		$superProfileObj->setBarcodeRotateDeg(WebUtil::GetInput("superPDFProfileBarcodeRotation", FILTER_SANITIZE_INT));
		$superProfileObj->setPrintingPressIDs(WebUtil::GetInputArr("printingPressIDs", FILTER_SANITIZE_STRING_ONE_LINE));
		
		// Loop through all of our known PDF sub profiles... and extract the Coorinates
		foreach ($superProfileObj->getSubPDFProfileIDs() as $thisSubProfileID){
			$superProfileObj->setXcoordOfSubPDFprofile($thisSubProfileID, WebUtil::GetInput("pdfSubProfileX_" . $thisSubProfileID, FILTER_SANITIZE_FLOAT));
			$superProfileObj->setYcoordOfSubPDFprofile($thisSubProfileID, WebUtil::GetInput("pdfSubProfileY_" . $thisSubProfileID, FILTER_SANITIZE_FLOAT));
			$superProfileObj->setOptionsLimiterOfSubPDFprofile($thisSubProfileID, WebUtil::GetInput("pdfOptionLimiter_" . $thisSubProfileID, FILTER_SANITIZE_STRING_ONE_LINE));
		}
		
		// Find out if the user is adding a new Sub Profile.
		$newSubProfileID = WebUtil::GetInput("newSubPdfProfile", FILTER_SANITIZE_INT);
		if(!empty($newSubProfileID))
			$superProfileObj->addSubPDFProfile($newSubProfileID);
		
		$superProfileObj->updateSuperPDFProfile();

		
		// Redirect back to where they were just at.
		header("Location: ./" . WebUtil::FilterURL("ad_superpdfprofiles.php?view=editSuperPDFProfile&superPDFProfileID=" . $superPDFProfileID));
		exit;

	}
	else if($action == "CopyProfile"){

		$superProfileObj = new SuperPDFprofile($dbCmd);
		$superProfileObj->loadSuperPDFProfileByID($superPDFProfileID);
		
		// Get the ID of the new Super Profile.
		$newSuperProfileID = $superProfileObj->copySuperProfile($superProfileObj->getSuperPDFProfileName() . "_COPY");

		// Redirect back to where they were just at.
		header("Location: ./" . WebUtil::FilterURL("ad_superpdfprofiles.php?view=editSuperPDFProfile&superPDFProfileID=" . $newSuperProfileID));
		exit;
		
	}	
	else if($action == "DeleteSubPDFprofile"){

		$superProfileObj = new SuperPDFprofile($dbCmd);
		$superProfileObj->loadSuperPDFProfileByID($superPDFProfileID);
		$superProfileObj->removeSubPDFprofile($superPDFProfileID, WebUtil::GetInput("subPdfProfileID", FILTER_SANITIZE_INT));

		// Redirect back to where they were just at.
		header("Location: ./" . WebUtil::FilterURL("ad_superpdfprofiles.php?view=editSuperPDFProfile&superPDFProfileID=" . $superPDFProfileID));
		exit;
		
	}	
	else if($action == "DeleteSuperProfile"){
		
		if(!$AuthObj->CheckForPermission("DELETE_SUPER_PDF_PROFILE"))
			throw new Exception("You don't have permission to delete a Super PDF profile.");

		SuperPDFprofile::removeSuperPDFprofile($superPDFProfileID);

		header("Location: ./" . WebUtil::FilterURL("ad_superpdfprofiles.php?view=start"));
		exit;
		
	}	
	else if($action == "MoveSuperProfileUp"){
		
		SuperPDFprofile::moveSuperProfilePosition($superPDFProfileID, true);

		header("Location: ./" . WebUtil::FilterURL("ad_superpdfprofiles.php?view=start"));
		exit;
	}	
	else if($action == "MoveSuperProfileDown"){
		
		SuperPDFprofile::moveSuperProfilePosition($superPDFProfileID, false);

		header("Location: ./" . WebUtil::FilterURL("ad_superpdfprofiles.php?view=start"));
		exit;
	}
	else if($action == "MoveSubProfileUp"){
		
		$subProfileID = WebUtil::GetInput("subPDFProfileID", FILTER_SANITIZE_INT);
		
		SuperPDFprofile::moveSubProfilePosition($superPDFProfileID, $subProfileID, true);

		header("Location: ./" . WebUtil::FilterURL("ad_superpdfprofiles.php?view=editSuperPDFProfile&superPDFProfileID=" . $superPDFProfileID));
		exit;
	}	
	else if($action == "MoveSubProfileDown"){
		
		$subProfileID = WebUtil::GetInput("subPDFProfileID", FILTER_SANITIZE_INT);
		
		SuperPDFprofile::moveSubProfilePosition($superPDFProfileID, $subProfileID, false);

		header("Location: ./" . WebUtil::FilterURL("ad_superpdfprofiles.php?view=editSuperPDFProfile&superPDFProfileID=" . $superPDFProfileID));
		exit;
	}	
	else if($action == "ChangeSuperProfileName"){
		
		$newSuperProfileName = WebUtil::GetInput("newSuperProfileName", FILTER_SANITIZE_STRING_ONE_LINE);
		
		if(!preg_match("/^\w{3,60}$/", $newSuperProfileName))
			WebUtil::PrintAdminError("Error setting Profie Name.  The Super PDF Profile Name must be between 3 and 60 characters and it may not contain any spaces or special characters.");
		
		// Dont allow Names that match an existing profile (from a different Profile ID. Not case sensitive check. 
		$allProfileNamesUpperCaseArr = SuperPDFprofile::getAllProfileNames($dbCmd, Domain::oneDomain());
		foreach($allProfileNamesUpperCaseArr as $existingProfileID => $existingProfileName){
			if($superPDFProfileID != $existingProfileID && strtoupper($newSuperProfileName) == strtoupper($existingProfileName)){
				WebUtil::PrintAdminError("The Super Profile name has already been taken: " . WebUtil::htmlOutput($newSuperProfileName));
			}
		}
		
		$superPDFProfileObj = new SuperPDFprofile();
		$superPDFProfileObj->loadSuperPDFProfileByID($superPDFProfileID);
		$superPDFProfileObj->setSuperPDFProfileName($newSuperProfileName);
		$superPDFProfileObj->updateSuperPDFProfile();

		header("Location: ./" . WebUtil::FilterURL("ad_superpdfprofiles.php?view=editSuperPDFProfile&superPDFProfileID=" . $superPDFProfileID));
		exit;
	}	
	else{
		throw new Exception("Undefined Action");
	}
}







// ------------------------------ Build HTML  ----------------------------------



$t = new Templatex(".");

$t->set_file("origPage", "ad_superpdfprofiles-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


// Build a Drop down menu showing all of the Super Profile Choices
$editProfilesDropDown = array("listLabel"=>"Choose Profile to Edit", "mainMenu"=>"< Back to Main Menu");

$allSuperProfilesNamesArr = SuperPDFprofile::getAllProfileNames($dbCmd, Domain::oneDomain());

foreach ($allSuperProfilesNamesArr as $profKey => $profValue)
	$editProfilesDropDown[$profKey] = $profValue;
	
$t->set_var("SUPER_PROFILES_LIST", Widgets::buildSelect($editProfilesDropDown, "listLabel"));
$t->allowVariableToContainBrackets("SUPER_PROFILES_LIST");


// If we are not editing a super pdf profile... then show them links to select a one.
if($view == "start"){

	$t->discard_block("origPage", "MainSuperPDFprofileForm");
	$t->discard_block("origPage", "HidePreviewProfileLink");
	
	
	$t->set_var("EXISTING_PROFILE_NAMES_ARRAY","0");

	$t->set_var("ENABLE_PROFILE_NAMES_CHECK","false");
	
	$t->set_block("origPage","existingSuperProfileNameBL","existingSuperProfileNameBLout"); 

	$superPDFProfileObj = new SuperPDFprofile();
	
	// Build list of Existing SuperPDFProfiles.
	$superPDFProfileNamesArr = SuperPDFprofile::getAllProfileNames($dbCmd, Domain::oneDomain());

	
	// Extract Inner HTML blocks for the Arrow buttons out of the the Block we just extracted for the row.
	$t->set_block ( "existingSuperProfileNameBL", "upLink", "upLinkout" );
	$t->set_block ( "existingSuperProfileNameBL", "downLink", "downLinkout" );
	
	$counter = 0;
	foreach($superPDFProfileNamesArr as $superProfileID => $thisSuperPDFProfileName) {
	
		$superPDFProfileObj->loadSuperPDFProfileByID($superProfileID);
	
		$t->set_var("SUPER_PROFILE_ID", $superPDFProfileObj->getSuperPDFProfileID());
		$t->set_var("SUPER_PROFILE_NAME", WebUtil::htmlOutput($thisSuperPDFProfileName));
		$t->set_var("SUPER_PROFILE_WIDTH", $superPDFProfileObj->getSheetWidth());
		$t->set_var("SUPER_PROFILE_HEIGHT", $superPDFProfileObj->getSheetHeight());
		$t->set_var("SUPER_PROFILE_NOTES", WebUtil::htmlOutput($superPDFProfileObj->getSuperPDFProfileNotes()));
		
		
		// Show a list of Printers for this Super Profile.
		$printerList = "";
		foreach ($superPDFProfileObj->getPrintingPressNames() as $thisPrintingName){
			if($printerList != "")
				$printerList .= "<br>";
			$printerList .= WebUtil::htmlOutput($thisPrintingName);
		}
		$t->set_var("PRINTERS", $printerList);
		$t->allowVariableToContainBrackets("PRINTERS");
		
		
		// Discard the inner blocks... for example.. if we are on the First record then discard the link for the button to go up.  
		// ... If we are on the last record don't show a down button.
		// Parse the nested block.  Make sure to set the 3rd parameter to FALSE to keep the block from growing inside of the loop.
		// Also, clear the output of the block we aren't using.
		if(sizeof($superPDFProfileNamesArr) == 1){
			$t->set_var("upLinkout", "");
			$t->set_var("downLinkout", "");
		}
		else if ($counter == 0){
			$t->parse("downLinkout", "downLink", false );
			$t->set_var("upLinkout", "");
		}
		else if(($counter+1) == sizeof($superPDFProfileNamesArr)){
			$t->parse ( "upLinkout", "upLink", false );
			$t->set_var("downLinkout", "");
		}
		else{
			$t->parse ( "upLinkout", "upLink", false );
			$t->parse("downLinkout", "downLink", false );
		}
		
		
		$t->parse("existingSuperProfileNameBLout","existingSuperProfileNameBL",true); 	
	
		$counter++;
	}
	
	if(empty($superPDFProfileNamesArr))
		$t->discard_block("origPage", "ExistingSuperProfilesBL");
		
	$t->set_var("SUB_PDF_PROFILE_IDS_ARRAY", "");
	$t->set_var("SUB_PDF_PROFILE_IDS_ALL_PRODUCTS_ARRAY", "");
	$t->set_var("SUB_PDF_PROFILE_NOTES_ALL_PRODUCTS_ARRAY", "");
	
	// Only Show them the drop down for switching PDF profiles when they are not on the start page.
	$t->discard_block("origPage", "SuperProfilesListBL");
		
}
else if($view == "editSuperPDFProfile"){

	$t->discard_block("origPage", "SuperProfileStartBL");
	$t->discard_block("origPage", "NewSuperProfileNameBL");
	$t->discard_block("origPage", "NewSuperProfileNameBLHelpIcon");
	
	
	// Show them the "Save Changes" button instead of the "Save New"
	$t->discard_block("origPage", "SaveNewButtonBL");
	
	$t->set_var("ENABLE_PROFILE_NAMES_CHECK","false");
	$t->set_var("EXISTING_PROFILE_NAMES_ARRAY","0");
	
	$superPDFProfileObj = new SuperPDFprofile();

	$superPDFProfileObj->loadSuperPDFProfileByID($superPDFProfileID);	
	
	$t->set_var("SUPER_PROFILE_ID", $superPDFProfileObj->getSuperPDFProfileID());
	$t->set_var("SUPER_PROFILE_NAME", $superPDFProfileObj->getSuperPDFProfileName());
	$t->set_var("SUPER_PROFILE_NOTES", WebUtil::htmlOutput($superPDFProfileObj->getSuperPDFProfileNotes()));
	$t->set_var("SUPER_PROFILE_WIDTH", $superPDFProfileObj->getSheetWidth());
	$t->set_var("SUPER_PROFILE_HEIGHT", $superPDFProfileObj->getSheetHeight());
	$t->set_var("SUPER_PROFILE_BARCODE_X", $superPDFProfileObj->getBarCodeX());
	$t->set_var("SUPER_PROFILE_BARCODE_Y", $superPDFProfileObj->getBarCodeY());
	$t->set_var("SUPER_PROFILE_BARCODE_ROTATION_VALUES", Widgets::buildSelect(array("0"=>"0", "90"=>"90", "180"=>"180", "270"=>"270"),$superPDFProfileObj->getBarcodeRotateDeg()));
	$t->allowVariableToContainBrackets("SUPER_PROFILE_BARCODE_ROTATION_VALUES");
	
	$t->set_var("PRINTING_PRESS_LIST", Widgets::buildSelect(PrintingPress::getPrintingPressList(Domain::oneDomain()), $superPDFProfileObj->getPrintingPressIDs()));
	$t->allowVariableToContainBrackets("PRINTING_PRESS_LIST");
	
	$t->set_var("FORM_SUBMIT_ACTION", "UpdateProfile");
	
	$t->set_block("origPage","AttachedPDFProfilesBL","AttachedPDFProfilesBLout");

	// Extract Inner HTML blocks for the Arrow buttons out of the the Block we just extracted for the row.
	$t->set_block ( "AttachedPDFProfilesBL", "upLink", "upLinkout" );
	$t->set_block ( "AttachedPDFProfilesBL", "downLink", "downLinkout" );
	
	$subPDFProfileIDsArr = $superPDFProfileObj->getSubPDFProfileIDs();
	
	// Don't allow previews when no Sub Profiles have been attached.
	if(empty($subPDFProfileIDsArr))
		$t->discard_block("origPage", "HidePreviewProfileLink");
	
	$counter = 0;
	foreach($subPDFProfileIDsArr as $thisPDFProfileID) {
		
		$t->set_var("PDFPROFILE_X" ,$superPDFProfileObj->getXcoordOfSubProfile($thisPDFProfileID)); 	
		$t->set_var("PDFPROFILE_Y" ,$superPDFProfileObj->getYcoordOfSubProfile($thisPDFProfileID));	
		$t->set_var("OPTION_LIMITER" ,$superPDFProfileObj->getOptionsLimiterOfSubPDFprofile($thisPDFProfileID));	
		$t->set_var("PDFPROFILE_ID", $thisPDFProfileID); 	
		
		$pdfProfileObj = $superPDFProfileObj->getSubPDFprofileObj($thisPDFProfileID);

		$t->set_var("PDF_PROFILE_PRODUCT", WebUtil::htmlOutput(Product::getFullProductName($dbCmd, $pdfProfileObj->getProductID()))); 
		$t->set_var("PDFPROFILE_NAME", WebUtil::htmlOutput($pdfProfileObj->getProfileName())); 	
		
		
		// Discard the inner blocks depending on the Position of Sorting.
		// For example.. if we are on the First record then discard the link for the button to go up ... If we are on the last record don't show a down button.
		// Parse the nested block.  Make sure to set the 3rd parameter to FALSE to keep the block from growing inside of the loop.
		// Also, clear the output of the block we aren't using.
		if(sizeof($subPDFProfileIDsArr) == 1){
			$t->set_var("upLinkout", "");
			$t->set_var("downLinkout", "");
		}
		else if ($counter == 0){
			$t->parse("downLinkout", "downLink", false );
			$t->set_var("upLinkout", "");
		}
		else if(($counter+1) == sizeof($subPDFProfileIDsArr)){
			$t->parse ( "upLinkout", "upLink", false );
			$t->set_var("downLinkout", "");
		}
		else{
			$t->parse ( "upLinkout", "upLink", false );
			$t->parse("downLinkout", "downLink", false );
		}
		
		
		$t->parse("AttachedPDFProfilesBLout","AttachedPDFProfilesBL",true); 
		
		$counter++;
	}
	
	if(empty($subPDFProfileIDsArr))
		$t->discard_block("origPage", "NoExistingPDFprofilesBL");
	
	// Make a Drop down list showing All Sub PDF profiles (that haven't already been added)
	// Profile Names may be the same if the Productd IDs are different.  So we need to show on the labels what product name the PDF Profile is from.
	// We are not going to include "Proof" PDF profiles either.
	$allProductIDs = Product::getActiveProductIDsArr($dbCmd, Domain::oneDomain());

	$allSubPdfProfileDescriptionsAndIdsArr = array();
	
	$subProfileDropDownList = array(""=> "Choose");
	foreach ($allProductIDs as $thisProductID) {
		
		// If another domain is doing the Production for this product, then we should not be able to choose PDF profiles configured on this domain.
		$productionProductID = Product::getProductionProductIDStatic($dbCmd, $thisProductID);
		if($productionProductID != $thisProductID)
			continue;
		
		$pdfProfileObj = new PDFprofile($dbCmd, $productionProductID);
		$subProfileNamesArr = $pdfProfileObj->getProfileNames();
		
		foreach ($subProfileNamesArr as $subProfID => $subProfName){
			if(strtoupper($subProfName) == "PROOF")
				continue;
			
			$pdfProfileObj->loadProfileByID($subProfID);
			
			$allSubPdfProfileDescriptionsAndIdsArr[$subProfID] = $pdfProfileObj->getProfileNotes();
				
			if(in_array($subProfID, $subPDFProfileIDsArr))
				continue;
				
			$subProfileDropDownList[$subProfID] = Product::getFullProductName($dbCmd, $thisProductID) . " : " . $subProfName;
		}
	}
	
	// Make Parallel Arrays for Javascript for the profile notes against each Profile ID.
	$allSubProfileIDs_JS = implode(", ", array_keys($allSubPdfProfileDescriptionsAndIdsArr));
	
	$allSubProfileNotes_JS = "";
	foreach($allSubPdfProfileDescriptionsAndIdsArr as $profileNotes){
		if(!empty($allSubProfileNotes_JS))
			$allSubProfileNotes_JS .= ", ";
		$allSubProfileNotes_JS .= "\"" . addslashes($profileNotes) . "\"";
	}
	
	$t->set_var("SUB_PDF_PROFILE_IDS_ALL_PRODUCTS_ARRAY", $allSubProfileIDs_JS);
	$t->set_var("SUB_PDF_PROFILE_NOTES_ALL_PRODUCTS_ARRAY", $allSubProfileNotes_JS);
	
	
	$t->set_var("NEW_SUB_PROFILE_LIST", Widgets::buildSelect($subProfileDropDownList, ""));
	$t->allowVariableToContainBrackets("NEW_SUB_PROFILE_LIST");
	
	// So javascript has a record of the IDs currently saved to the Super PDF pofileand can do validation on inputs
	$javascripArrPdfSubProfiles = implode($subPDFProfileIDsArr, "\", \"");
	if(!empty($subPDFProfileIDsArr))
		$javascripArrPdfSubProfiles = "\"" . $javascripArrPdfSubProfiles . "\"" ;
	$t->set_var("SUB_PDF_PROFILE_IDS_ARRAY", $javascripArrPdfSubProfiles);
	
	if(!$AuthObj->CheckForPermission("DELETE_SUPER_PDF_PROFILE"))
		$t->discard_block("origPage", "DeleteProfileLink");

	
}
else if($view == "newSuperPDFProfile"){

	$t->discard_block("origPage", "SuperProfileStartBL");
	$t->discard_block("origPage", "ExistingSuperProfileNameBL");
	$t->discard_block("origPage", "HidePreviewProfileLink");
	
	// Get rid of the section for Editing Sub PDF Profiles until the user has saved the "new" form and created a SuperProfileID.
	$t->discard_block("origPage", "SubPDFprofilesSectionBL");

	// Show them the "Save New" button instead of the "Save Changes"
	$t->discard_block("origPage", "SaveChangesButtonBL");
	
	$t->set_var("SUPER_PROFILE_ID", "");
	$t->set_var("SUPER_PROFILE_NAME","");
	$t->set_var("SUPER_PROFILE_NOTES","");
	$t->set_var("SUPER_PROFILE_WIDTH", "");
	$t->set_var("SUPER_PROFILE_HEIGHT", "");
	$t->set_var("SUPER_PROFILE_BARCODE_X", "");
	$t->set_var("SUPER_PROFILE_BARCODE_Y", "");
	$t->set_var("SUPER_PROFILE_BARCODE_ROTATION_VALUES", Widgets::buildSelect(array("0"=>"0", "90"=>"90", "180"=>"180", "270"=>"270"),0));
	$t->allowVariableToContainBrackets("SUPER_PROFILE_BARCODE_ROTATION_VALUES");
	
	$t->set_var("FORM_SUBMIT_ACTION", "SaveNewProfile");
	
	
	$t->set_var("PRINTING_PRESS_LIST", Widgets::buildSelect(PrintingPress::getPrintingPressList(Domain::oneDomain()), array()));
	$t->allowVariableToContainBrackets("PRINTING_PRESS_LIST");
	

	// Show an Array of Existing Profile Names so we can show validation in Javascript on duplicate entries.
	$t->set_var("ENABLE_PROFILE_NAMES_CHECK","true");
	
	$profileNames_JS_Arr = "";
	$allExistingSuperProfileNames = SuperPDFprofile::getAllProfileNames($dbCmd, Domain::oneDomain());
	foreach ($allExistingSuperProfileNames as $thisSuperProfName){
		if(!empty($profileNames_JS_Arr))
			$profileNames_JS_Arr .= ",";
		
		$profileNames_JS_Arr .= "\"" . strtoupper($thisSuperProfName) . "\"";
	}
	
	$t->set_var("EXISTING_PROFILE_NAMES_ARRAY",$profileNames_JS_Arr);
	
	$t->set_var("SUB_PDF_PROFILE_IDS_ARRAY", "");
	$t->set_var("SUB_PDF_PROFILE_IDS_ALL_PRODUCTS_ARRAY", "");
	$t->set_var("SUB_PDF_PROFILE_NOTES_ALL_PRODUCTS_ARRAY", "");

	
}
else{
	throw new Exception("Illegal View Type");
}









$t->pparse("OUT","origPage");




?>

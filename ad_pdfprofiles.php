<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("EDIT_PDF_PROFILES"))
		throw new Exception("You don't have permission to edit a Product.");



$editProduct = WebUtil::GetInput("editProduct", FILTER_SANITIZE_INT);
$profileID = WebUtil::GetInput("profileID", FILTER_SANITIZE_INT);
$shapeID = WebUtil::GetInput("shapeID", FILTER_SANITIZE_INT);
$cmykBlockID = WebUtil::GetInput("cmykBlockID", FILTER_SANITIZE_INT);
$view = WebUtil::GetInput("view", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);


// Make sure that the user doesn't switch domains after they have selected a Product ID.
// If we don't do this, it isn't harmful, but it could be confusing to the user.
if(!empty($editProduct)){
	
	$editProduct = intval($editProduct);
	
	$domainIDofProduct = Product::getDomainIDfromProductID($dbCmd, $editProduct);
	
	if(!$AuthObj->CheckIfUserCanViewDomainID($domainIDofProduct))
		throw new Exception("The Product belongs to another domain or it can't be viewed.");
		
	Domain::enforceTopDomainID($domainIDofProduct);
	
	PDFprofile::createProofProfileIfDoesNotExist($dbCmd, $editProduct);
}



if($action){
	
	WebUtil::checkFormSecurityCode();

	if($action == "deleteProfile"){
	
		// Don't let the user delete a PDF profile if there are other Super PDF Profiles linking to it.
		$superProfilesArr = SuperPDFprofile::getSuperProfileIDsUsingThisSubPDFprofile($profileID);
		if(!empty($superProfilesArr)){
			
			$errorMessage = "You can not delete this PDF Profile because it is being used by the following Super PDF Profiles.<br><br>";
			
			foreach($superProfilesArr as $thisSuperProfileID){
				$superProfileObj = new SuperPDFprofile();
				$superProfileObj->loadSuperPDFProfileByID($thisSuperProfileID);
				
				$errorMessage .= WebUtil::htmlOutput($superProfileObj->getSuperPDFProfileName()) . "<br>";
			}
			
			WebUtil::PrintAdminError($errorMessage, TRUE);
		}
		
		$productIDofProfile = PDFprofile::getProductIDfromProfileID($dbCmd, $profileID);

		// Just in case the Profile Was already deleted.		
		if($productIDofProfile){
		
			// Make sure the user can't delete the 'Proof' Profile.  This is a requirment for all products.
			$profileObj = new PDFprofile($dbCmd, $productIDofProfile);
			$profileObj->loadProfileByID($profileID);

			if($profileObj->getProfileName() == "Proof")
				throw new Exception("Error deleting Profile. You can not remove the Proof Profile.");

			// Delete Profile
			PDFprofile::deleteProfileByID($dbCmd, $profileID);
		}

		// Redirect back to main PDFProfile page
		header("Location: ./" . WebUtil::FilterURL("ad_pdfprofiles.php?view=start&editProduct=" . $editProduct));
		exit;
	}	
	else if($action == "deleteShape"){
		
		// Delete Shape
		PDFprofile::deleteShapeByID($dbCmd, $shapeID, $profileID);
		
		// Redirect back to the origin page 
		header("Location: ./" . WebUtil::FilterURL("ad_pdfprofiles.php?view=editProfile&editProduct=" . $editProduct . "&profileID=" . $profileID));
		exit;
	}
	else if($action == "copyProfile"){
		
		// Save a new Profile by copying an existing one... just add _copy to the end.
		$profileObj = new PDFprofile($dbCmd, $editProduct);
		$profileObj->loadProfileByID($profileID);
		
		$newPDFprofileID = $profileObj->saveCopyOfThisProfile($profileObj->getProfileName() . "_COPY");
		
		// Redirect back to the origin page 
		header("Location: ./" . WebUtil::FilterURL("ad_pdfprofiles.php?view=editProfile&editProduct=" . $editProduct . "&profileID=" . $newPDFprofileID));
		exit;
	}
	else if($action == "changeProfileName"){
		
		$newProfileName = WebUtil::GetInput("newProfileName", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
		
		$profileObj = new PDFprofile($dbCmd, $editProduct);
		$profileObj->loadProfileByID($profileID);
		$profileObj->setProfileName($newProfileName);
		
		if($profileObj->checkIfPDFprofileExists($newProfileName))
			WebUtil::PrintAdminError("Can not change Profile Name. It already exists: " . WebUtil::htmlOutput($newProfileName));
		
		$profileObj->updatePDFprofile();
		
		header("Location: ./" . WebUtil::FilterURL("ad_pdfprofiles.php?view=editProfile&editProduct=" . $editProduct . "&profileID=" . $profileID));
		exit;
	}
	
	else if($action == "deleteCmykBlock"){
		
		// Delete CmykBlock
		PDFprofile::deleteCmykBlockByID($dbCmd, $cmykBlockID, $profileID);
		
		// Redirect back to the origin page 
		header("Location: ./" . WebUtil::FilterURL("ad_pdfprofiles.php?view=editProfile&editProduct=" . $editProduct . "&profileID=" . $profileID));
		exit;
	}
	else if($action == "savePDFprofile" || $action == "saveNewPDFprofile") {
	
		$profileObj = new PDFprofile($dbCmd, $editProduct);
		
		// If we are saving an existing profile... load the existing values into memory before replacing with form data.
		if($action == "savePDFprofile")
			$profileObj->loadProfileByID($profileID);
		
		$profileObj->setProfileNotes(WebUtil::GetInput("profileNotes", FILTER_SANITIZE_STRING_ONE_LINE));
		$profileObj->setPageWidth(WebUtil::GetInput("sheetWidth", FILTER_SANITIZE_FLOAT));
		$profileObj->setPageHeight(WebUtil::GetInput("sheetHeight", FILTER_SANITIZE_FLOAT));
		$profileObj->setUnitWidth(WebUtil::GetInput("unitWidth", FILTER_SANITIZE_FLOAT));
		$profileObj->setUnitHeight(WebUtil::GetInput("unitHeight", FILTER_SANITIZE_FLOAT));
		$profileObj->setForceAspectRatio(WebUtil::GetInput("forceAspectRatio", FILTER_SANITIZE_STRING_ONE_LINE) == "Y" ? true : false);
		$profileObj->setRows(WebUtil::GetInput("rows", FILTER_SANITIZE_INT));
		$profileObj->setColumns(WebUtil::GetInput("columns", FILTER_SANITIZE_INT));
		$profileObj->setDisplayCoverSheet(WebUtil::GetInput("coverSheet", FILTER_SANITIZE_STRING_ONE_LINE) == "Y" ? true : false);
		$profileObj->setDisplaySummarySheet(WebUtil::GetInput("summarySheet", FILTER_SANITIZE_STRING_ONE_LINE) == "Y" ? true : false);
		$profileObj->setShowOutsideBorder(WebUtil::GetInput("showOutsideBorder", FILTER_SANITIZE_STRING_ONE_LINE) == "Y" ? true : false);
		$profileObj->setShowCropMarks(WebUtil::GetInput("showCropMarks", FILTER_SANITIZE_STRING_ONE_LINE) == "Y" ? true : false);
		$profileObj->setHspacing(WebUtil::GetInput("horizonalSpacing", FILTER_SANITIZE_FLOAT));
		$profileObj->setVspacing(WebUtil::GetInput("verticalSpacing", FILTER_SANITIZE_FLOAT));
		$profileObj->setBleedUnits(WebUtil::GetInput("bleedUnits", FILTER_SANITIZE_FLOAT));
		$profileObj->setGapr(WebUtil::GetInput("extraGapRows", FILTER_SANITIZE_INT));
		$profileObj->setGapc(WebUtil::GetInput("extraGapColumns", FILTER_SANITIZE_INT));
		$profileObj->setGapsizeV(WebUtil::GetInput("extraGapVertical", FILTER_SANITIZE_FLOAT));
		$profileObj->setGapsizeH(WebUtil::GetInput("extraGapHorizontal", FILTER_SANITIZE_FLOAT));
		$profileObj->setLmargin(WebUtil::GetInput("leftMargin", FILTER_SANITIZE_FLOAT));
		$profileObj->setBmargin(WebUtil::GetInput("bottomMargin", FILTER_SANITIZE_FLOAT));
		$profileObj->setLabelx(WebUtil::GetInput("labelX", FILTER_SANITIZE_FLOAT));
		$profileObj->setLabely(WebUtil::GetInput("labelY", FILTER_SANITIZE_FLOAT));
		$profileObj->setLabelrotate(WebUtil::GetInput("labelRotate", FILTER_SANITIZE_INT));
		$profileObj->setExtraQuantityMinimumStart(WebUtil::GetInput("extraMinQuantity", FILTER_SANITIZE_INT));
		$profileObj->setExtraQuantityPercentage(WebUtil::GetInput("extraPercent", FILTER_SANITIZE_FLOAT));
		$profileObj->setExtraQuantityMaximum(WebUtil::GetInput("extraMaxLimit", FILTER_SANITIZE_INT));
		$profileObj->setRotatecanvas(WebUtil::GetInput("rotation", FILTER_SANITIZE_INT));


		// If there are any existing shapes or CMYK blocks loaded in the PDFprofile Object, get rid of them so we can add fresh ones back in.
		$profileObj->removeAllLocalShapes();
		$profileObj->removeAllGlobalShapes();
		$profileObj->removeAllCmykBlocks();


		
		// ------ Add Coversheet Matrix ----------------
	
		$matrixArr = array();
		$matrixError = false;
		$totalMatrixValuesArr = array();
		for($col=1; $col<=WebUtil::GetInput("columns", FILTER_SANITIZE_INT); $col++) {

			for($row=1; $row<=WebUtil::GetInput("rows", FILTER_SANITIZE_INT); $row++){
				$matrixValue = WebUtil::GetInput("matrix_". $row . "_" . $col, FILTER_SANITIZE_INT);
				$matrixArr[($row - 1)][($col - 1)] = $matrixValue;
				$totalMatrixValuesArr[] = $matrixValue;
			}
		}
		
		
		for($mt = 1; $mt <= WebUtil::GetInput("rows", FILTER_SANITIZE_INT)*WebUtil::GetInput("columns", FILTER_SANITIZE_INT); $mt++) {
			if(!in_array($mt,$totalMatrixValuesArr))
				$matrixError = true;
		}
	

		if(!$matrixError) {	
			$matrixObj =  new MatrixOrder();
			$matrixObj->setMatrix($matrixArr);
			$profileObj->setCoverSheetMatrix($matrixObj);
		}
		else{
			$profileObj->setCoverSheetMatrix(null);
		}
		

		// ----------------------- Add/Update Shapes and CMYK Blocks ---------------------


		
		// Loop trough all existing shapes and add them back into the Object
		// New Shapes being added will have the highest counter ID... and the values will not be empty.
		$counter=1;
		while(true){
		
			// Break when we come accross our first variable not sent.
			// Javascript validation will make sure that someone didn't accidentaly forget to enter a value.
			$shapeExistenceChecker = WebUtil::GetInput("Shape_ShapeValue1_" . $counter, FILTER_SANITIZE_STRING_ONE_LINE);
			if(empty($shapeExistenceChecker))
				break;
				
			
			// Store some Form Inputs in Temp Vars to make some "new Shape" code easier to read.
			$shapeType = WebUtil::GetInput("Shape_ShapeType_" . $counter, FILTER_SANITIZE_STRING_ONE_LINE);
			$xCoord = WebUtil::GetInput("Shape_X_" . $counter, FILTER_SANITIZE_FLOAT);
			$yCoord = WebUtil::GetInput("Shape_Y_" . $counter, FILTER_SANITIZE_FLOAT);
			$shapeSpecificValue1 = WebUtil::GetInput("Shape_ShapeValue1_" . $counter, FILTER_SANITIZE_FLOAT);
			$shapeSpecificValue2 = WebUtil::GetInput("Shape_ShapeValue2_" . $counter, FILTER_SANITIZE_FLOAT);
				
			if($shapeType == "C")
				$newShapeObj = new ArtworkCircle($shapeSpecificValue1, $xCoord, $yCoord);
			else if($shapeType == "R")
				$newShapeObj = new ArtworkRectangle($shapeSpecificValue1, $shapeSpecificValue2, $xCoord, $yCoord);
			else
				throw new Exception("The Shape type has not been defined yet.");
			
			
			$newShapeObj->setRotation(WebUtil::GetInput("Shape_Rotation_" . $counter, FILTER_SANITIZE_INT));
			$newShapeObj->setLineStyle(WebUtil::GetInput("Shape_LineStyle_" . $counter, FILTER_SANITIZE_STRING_ONE_LINE));
			$newShapeObj->setLineThickness(WebUtil::GetInput("Shape_LineThickness_" . $counter, FILTER_SANITIZE_FLOAT));
			$newShapeObj->setFillColorRGB(WebUtil::GetInput("Shape_FillColor_" . $counter, FILTER_SANITIZE_STRING_ONE_LINE));
			$newShapeObj->setLineColor(WebUtil::GetInput("Shape_LineColor_" . $counter, FILTER_SANITIZE_STRING_ONE_LINE));
			$newShapeObj->setLineAlpha(WebUtil::GetInput("Shape_LineAlpha_" . $counter, FILTER_SANITIZE_STRING_ONE_LINE));
			$newShapeObj->setFillAlpha(WebUtil::GetInput("Shape_FillAlpha_" . $counter, FILTER_SANITIZE_FLOAT));
			$newShapeObj->setOptionsLimiter(WebUtil::GetInput("Shape_OptionLimiter_" . $counter, FILTER_SANITIZE_STRING_ONE_LINE));
			$newShapeObj->setRemarks(WebUtil::GetInput("Shape_Remarks_" . $counter, FILTER_SANITIZE_STRING_ONE_LINE));
			
			
			if(WebUtil::GetInput("Shape_globalOrLocal_" . $counter, FILTER_SANITIZE_STRING_ONE_LINE) == "L")
				$profileObj->addLocalShapeObj(WebUtil::GetInput("Shape_SideNum_" . $counter, FILTER_SANITIZE_STRING_ONE_LINE), $newShapeObj);
			else if(WebUtil::GetInput("Shape_globalOrLocal_" . $counter, FILTER_SANITIZE_STRING_ONE_LINE) == "G")
				$profileObj->addGlobalShapeObj(WebUtil::GetInput("Shape_SideNum_" . $counter, FILTER_SANITIZE_STRING_ONE_LINE), $newShapeObj);
			else
				throw new Exception("The Shape Global/Local parameter is wrong.");

			$counter++;			
		}
		
		
		
		// Loop trough all existing CMYK blocks and add them back into the Object
		// New CMYK blocks being added will have the highest counter ID... and the values will not be empty.
		$counter=1;
		while(true){
		
			// Break when we come accross our first variable not sent.
			// Javascript validation will make sure that someone didn't accidentaly forget to enter a value.
			$cmykExistenceChecker = WebUtil::GetInput("CMYKblock_BlockWidth_" . $counter, FILTER_SANITIZE_FLOAT);
			if(empty($cmykExistenceChecker))
				break;
	
			$cmykBlockObj = new CMYKblocks();
			
			$cmykBlockObj->setGroupWidth(WebUtil::GetInput("CMYKblock_BlockWidth_" . $counter, FILTER_SANITIZE_FLOAT));
			$cmykBlockObj->setGroupHeight(WebUtil::GetInput("CMYKblock_BlockHeight_" . $counter, FILTER_SANITIZE_FLOAT));
			$cmykBlockObj->setStartXcoord(WebUtil::GetInput("CMYKblock_X_" . $counter, FILTER_SANITIZE_FLOAT));
			$cmykBlockObj->setStartYcoord(WebUtil::GetInput("CMYKblock_Y_" . $counter, FILTER_SANITIZE_FLOAT));
			$cmykBlockObj->setTotalGroups(WebUtil::GetInput("CMYKblock_NumBlocks_" . $counter, FILTER_SANITIZE_INT));
			$cmykBlockObj->setRotation(WebUtil::GetInput("CMYKblock_Rotation_" . $counter, FILTER_SANITIZE_INT));
			$cmykBlockObj->setSpacingBetweenGroups(WebUtil::GetInput("CMYKblock_Spacing_" . $counter, FILTER_SANITIZE_FLOAT));
			$cmykBlockObj->setSideNumber(WebUtil::GetInput("CMYK_SideNum_" . $counter, FILTER_SANITIZE_INT));
		
			$profileObj->addCmykBlock($cmykBlockObj);
		
			$counter++;
		}

	
	
	
		// Update exisiting Profile or Add a New one.
		if($action == "savePDFprofile")
			$profileObj->updatePDFprofile();
		if($action == "saveNewPDFprofile")
			$profileObj->addPDFprofile(WebUtil::GetInput("profileName", FILTER_SANITIZE_STRING_ONE_LINE));

		

		header("Location: ./" . WebUtil::FilterURL("ad_pdfprofiles.php?view=editProfile&editProduct=" . $editProduct . "&profileID=" . $profileObj->getProfileID()));
		exit;
	}
	else if($action == "setDefaultProfile") {
	
		$newDefaultProfileName = WebUtil::GetInput("defaultProfile", FILTER_SANITIZE_STRING_ONE_LINE);
	
		$profileObj = new PDFprofile($dbCmd, $editProduct);
		$profileObj->loadProfileByName($newDefaultProfileName);
		$profileObj->configureThisProfileToBeTheDefault();
	
		header("Location: ./" . WebUtil::FilterURL("ad_pdfprofiles.php?view=start&editProduct=" . $editProduct));
		exit;
	}
	else{
		throw new Exception("Undefined Action");
	}
}







// ------------------------------ Build HTML  ----------------------------------


$productObj = new Product($dbCmd, $editProduct, true);

$t = new Templatex(".");

$t->set_file("origPage", "ad_pdfprofiles-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());


$t->set_var("PRODUCTID", $editProduct);
$t->set_var("FULL_PRODUCT_NAME", WebUtil::htmlOutput($productObj->getProductTitleWithExtention()));



// Shape Objects can be drawn on the Front or the Back of an Artwork...  But there could be many more sides to an Artwork (like 6 page booklets).
// Build a Drop down List so the user can see the Side Descriptions.
// Internally Side Numbers are Zero Based.
$sideDescriptionsOptionsList = array();
for($i=1; $i<=$productObj->getArtworkSidesCount(); $i++)
	$sideDescriptionsOptionsList[$i-1] = $productObj->getArtworkSideDescription($i);



// Build a list of Product IDs (excluding the current one)
$allProductsExceptThisOneArr = $productListArr = Product::getActiveProductIDsArr($dbCmd, Domain::oneDomain());
WebUtil::array_delete($allProductsExceptThisOneArr, $editProduct);

$productListArr = Product::getAllProductIDsArr($dbCmd, Domain::oneDomain());

$editAnotherProductDropDown = array();

	if(!Product::checkIfProductIDisActive($dbCmd, $editProduct))
		$editAnotherProductDropDown[$editProduct] = Product::getFullProductName($dbCmd, $editProduct);

foreach($productListArr as $thisProdID)
	$editAnotherProductDropDown[$thisProdID] = Product::getFullProductName($dbCmd, $thisProdID);

$t->set_var("SELECT_ANOTHER_PRDODUCT_LIST", Widgets::buildSelect($editAnotherProductDropDown, $editProduct));
$t->allowVariableToContainBrackets("SELECT_ANOTHER_PRDODUCT_LIST");




// If we are not editing a product... then show them links to select a product.
if($view == "start"){

	$t->discard_block("origPage", "MainPDFprofileForm");
	
	
	// We can't save profiles if there is a Production PiggyBack.
	if(Product::getProductionProductIDStatic($dbCmd, $editProduct) == $editProduct){
		$t->discard_block("origPage", "CantChangeVendorBL");
		
		// Allow the Radio buttons for selecting Default Profile
		$t->set_var("HIDE_RADIO_BUTTON_START", "");
		$t->set_var("HIDE_RADIO_BUTTON_END", "");
		
		
	}
	else{
		$t->discard_block("origPage", "CreateNewProfileBL");
		$t->discard_block("origPage", "DefaultProfileDescriptionBL");
		
		$productionPiggyBackID = Product::getProductionProductIDStatic($dbCmd, $editProduct);
		$domainIDOfPiggyBack = Product::getDomainIDfromProductID($dbCmd, $productionPiggyBackID);
		$domainIDOfProduct = Product::getDomainIDfromProductID($dbCmd, $editProduct);
		
		$domainPrefix = "";
		if($domainIDOfPiggyBack != $domainIDOfProduct)
			$domainPrefix = Domain::getAbreviatedNameForDomainID($domainIDOfPiggyBack) . ">> ";
			
		$productionPiggyBackName = Product::getFullProductName($dbCmd, Product::getProductionProductIDStatic($dbCmd, $editProduct));
		
		$t->set_var("PRODUCTION_PIGGYBACK_NAME",WebUtil::htmlOutput($domainPrefix . $productionPiggyBackName));
		
		// Hide the Radio buttons for selecting Default Profile
		$t->set_var("HIDE_RADIO_BUTTON_START", "<!-- ");
		$t->set_var("HIDE_RADIO_BUTTON_END", " -->");
		$t->allowVariableToContainBrackets("HIDE_RADIO_BUTTON_START");
		$t->allowVariableToContainBrackets("HIDE_RADIO_BUTTON_END");
	}
	
	
	$t->set_var("EXISTING_PROFILE_NAMES_ARRAY","0");
	$t->set_var("ENABLE_PROFILE_NAMES_CHECK","false");
	
	
	$t->set_block("origPage","existingProfileNameBL","existingProfileNameBLout"); 

	$profileObj = new PDFprofile($dbCmd, $editProduct);
	
	// Build list of Existing Profiles.
	$profileNamesArr = $profileObj->getProfileNames();
	foreach($profileNamesArr as $thisProfileName) {
	
		// Only the Proof Profile can be manipulated when there is a Production Piggyback.
		if($thisProfileName != "Proof" && Product::getProductionProductIDStatic($dbCmd, $editProduct) != $editProduct)
			continue;
		
		$profileObj->loadProfileByName($thisProfileName);
	
		$t->set_var("PROFILE_ID",   $profileObj->getProfileID());
		$t->set_var("PROFILE_NAME", WebUtil::htmlOutput($thisProfileName));
		
		
		if($thisProfileName ==  $profileObj->getDefaultProfileName()) 
			$t->set_var("DEFAULT_PROFILE_CHECKED","checked");
		else
			$t->set_var("DEFAULT_PROFILE_CHECKED","");
				
		
		$t->set_var("SHEET_SIZE_WIDTH", $profileObj->getPagewidth());
		$t->set_var("SHEET_SIZE_HEIGHT", $profileObj->getPageheight());
		$t->set_var("QUANTITY", $profileObj->getQuantity());
		$t->set_var("ROWS", $profileObj->getRows());
		$t->set_var("COLS", $profileObj->getColumns());
		$t->set_var("PROFILE_NOTES", WebUtil::htmlOutput($profileObj->getProfileNotes()));
		


		$t->parse("existingProfileNameBLout","existingProfileNameBL",true); 	
	}
	
	
	$t->set_var("DEFAULT_PROFILE_NAME",$profileObj->getDefaultProfileName());
		
	if(empty($profileNamesArr))
		$t->discard_block("origPage", "ExistingProfilesBL");

			
	
}
else if($view == "editProfile"){

	$t->discard_block("origPage", "ProfileStartBL");
	$t->discard_block("origPage", "newPDFprofileLabel");
	$t->discard_block("origPage", "NewProfileHiddenInputs");
	$t->discard_block("origPage", "NewProfileNameBL");
	$t->discard_block("origPage", "NewProfileNameHintIconBL");
	$t->discard_block("origPage", "CantChangeVendorBL");
	
	
	

	$t->set_var("EXISTING_PROFILE_NAMES_ARRAY","0");
	$t->set_var("ENABLE_PROFILE_NAMES_CHECK","false");
	
	
	$profileObj = new PDFprofile($dbCmd, $editProduct);

	$profileObj->loadProfileByID($profileID);

	$t->set_var("PROFILE_ID",$profileID);
	$t->set_var("PROFILE_NAME", $profileObj->getProfileName());
	$t->set_var("PROFILE_NOTES",WebUtil::htmlOutput($profileObj->getProfileNotes()));
	$t->set_var("SHEET_WIDTH" ,$profileObj->getPageWidth());
	$t->set_var("SHEET_HEIGHT",$profileObj->getPageHeight());
	$t->set_var("UNIT_WIDTH"  ,$profileObj->getUnitWidth());
	$t->set_var("UNIT_HEIGHT" ,$profileObj->getUnitHeight());
	$t->set_var("ROWS"  ,$profileObj->getRows());
	$t->set_var("COLUMNS" ,$profileObj->getColumns());
	$t->set_var("HORIZONTAL_SPACING"  ,$profileObj->getHspacing());
	$t->set_var("VERTICAL_SPACING"  ,$profileObj->getVspacing());
	$t->set_var("BLEED_UNITS"  ,$profileObj->getBleedUnits());
	$t->set_var("EXTRA_GAP_ROWS",$profileObj->getGapr());
	$t->set_var("EXTRA_GAP_COLUMNS", $profileObj->getGapc());
	$t->set_var("EXTRA_GAP_HORIZONTAL",$profileObj->getGapsizeH());
	$t->set_var("EXTRA_GAP_VERTICAL",$profileObj->getGapsizeV());
	$t->set_var("LEFT_MARGIN",$profileObj->getLmargin());
	$t->set_var("BOTTOM_MARGIN",$profileObj->getBmargin());
	$t->set_var("LABEL_X",$profileObj->getLabelx());
	$t->set_var("LABEL_Y",$profileObj->getLabely());
	$t->set_var("LABEL_ROTATION",$profileObj->getLabelrotate());
	$t->set_var("EXTRA_MIN_QUANTITY",$profileObj->getExtraQuantityMinimumStart());
	$t->set_var("EXTRA_PERCENT",$profileObj->getExtraQuantityPercentage());
	$t->set_var("EXTRA_MAX_LIMIT",$profileObj->getExtraQuantityMaximum());
	$t->set_var("PRODUCT_SETUP_BLEED", $productObj->getArtworkBleedPicas());
	


	// Build Matrix Coversheet.
	// Extract Inner Block before MatrixRowBL is extracted.
	$t->set_block ( "origPage", "MatrixValueBL", "MatrixValueBLout" );
	$t->set_block ( "origPage", "MatrixRowBL", "MatrixRowBLout" );
		
	$matrixObj = $profileObj->getCoverSheetMatrix();


	if($matrixObj) {
		$t->discard_block("origPage", "EmptyMatrixBlockInit");
		$t->discard_block("origPage", "EmptyMatrixBlockLink");
	}


	for($row = 1; $row <= $profileObj->getRows(); $row++) {	

		// Clear Inner Block (columns) for this row.
		$t->set_var("MatrixValueBLout", "");

		for($col = 1; $col <=$profileObj->getColumns(); $col++) {

			if($matrixObj)
				$matrixValue = $matrixObj->getMatrixOrderValueAt($row, $col);
			else
				$matrixValue = "";
				
			$t->set_var("MATRIX_COL", $col);
			$t->set_var("MATRIX_ROW", $row);
			$t->set_var("MATRIX_VALUE", $matrixValue);
			

			$t->parse ( "MatrixValueBLout", "MatrixValueBL", true );
		}
		
		$t->parse ( "MatrixRowBLout", "MatrixRowBL", true );
	}
	

	// ------ Set Radio Buttons --------------------
	// =================================
	if($profileObj->getForceAspectRatio())
		$t->set_var(array("FORCE_UNIT_SIZE_ASPECT_RATIO_YES"=>"checked", "FORCE_UNIT_SIZE_ASPECT_RATIO_NO"=>""));
	else
		$t->set_var(array("FORCE_UNIT_SIZE_ASPECT_RATIO_YES"=>"", "FORCE_UNIT_SIZE_ASPECT_RATIO_NO"=>"checked"));
	// =================================
	if($profileObj->getDisplayCoverSheet())
		$t->set_var(array("DISPLAY_COVERSHEET_YES"=>"checked", "DISPLAY_COVERSHEET_NO"=>""));
	else
		$t->set_var(array("DISPLAY_COVERSHEET_YES"=>"", "DISPLAY_COVERSHEET_NO"=>"checked"));
	// =================================
	if($profileObj->getDisplaySummarySheet())
		$t->set_var(array("DISPLAY_SUMMARY_SHEET_YES"=>"checked", "DISPLAY_SUMMARY_SHEET_NO"=>""));
	else
		$t->set_var(array("DISPLAY_SUMMARY_SHEET_YES"=>"", "DISPLAY_SUMMARY_SHEET_NO"=>"checked"));
	// =================================
	if($profileObj->getShowOutsideBorder())
		$t->set_var(array("SHOW_OUTSIDE_BORDER_YES"=>"checked", "SHOW_OUTSIDE_BORDER_NO"=>""));
	else
		$t->set_var(array("SHOW_OUTSIDE_BORDER_YES"=>"", "SHOW_OUTSIDE_BORDER_NO"=>"checked"));
	// =================================
	if($profileObj->getShowCropMarks())
		$t->set_var(array("SHOW_CROP_MARKS_YES"=>"checked", "SHOW_CROP_MARKS_NO"=>""));
	else
		$t->set_var(array("SHOW_CROP_MARKS_YES"=>"", "SHOW_CROP_MARKS_NO"=>"checked"));
	// =================================
	
	
	// ------ Set Drop Down Menus ---------------------
	$t->set_var("ROTATION_VALUES", Widgets::buildSelect(array("0"=>"0", "90"=>"90", "180"=>"180", "270"=>"270"),$profileObj->getRotatecanvas()));
	$t->allowVariableToContainBrackets("ROTATION_VALUES");
	
	// Don't let the user see a Delete Link if the Profile name is "Proof".
	if($profileObj->getProfileName() == "Proof")
		$t->discard_block("origPage", "DeleteProfileLink");
		
		
	// The size of the Artwork stored in the Product Settings.
	$t->set_var("PRODUCT_WIDTH" , $productObj->getArtworkCanvasWidth());
	$t->set_var("PRODUCT_HEIGHT", $productObj->getArtworkCanvasHeight());
	
	
	// In some cases you may want the PDF profile to have slightly different Unit Sizes than the Product.
	// But just in case, warn the user if they don't match.
	if($productObj->getArtworkCanvasWidth() == $profileObj->getUnitWidth() && $productObj->getArtworkCanvasHeight() == $profileObj->getUnitHeight())
		$t->discard_block("origPage", "ProductSizeMismatchBL");
		
	
	// Warn the user if the Bleed Setting on the PDF profile is larger than what is saved on the Product.
	// It should be allowed... although it is not guaranteed that the artwork conforms... but you could get lucky if the extra real-estate is there on the Image.
	if($productObj->getArtworkBleedPicas() >= $profileObj->getBleedUnits())
		$t->discard_block("origPage", "BleedSettingLargerThanProductMsgBL");
		

	// Default to the First Side for new Shapes being added.
	$t->set_var("NEW_SHAPE_SIDE_LIST", Widgets::buildSelect($sideDescriptionsOptionsList, 0));
	$t->allowVariableToContainBrackets("NEW_SHAPE_SIDE_LIST");

	// Start off with Zero... the First existing Shape will Get a Counter of 1...
	// ... that is unless there are no existing shapes, in which case our "new shapes form" will get the counter of 1.
	// New Shapes always get the highest counter to make them get inserted at the end.
	$shapeCounter = 0; 

	$t->set_block("origPage","RectangleShapeBL","RectangleShapeBLout");
	$t->set_block("origPage","CircleShapeBL","CircleShapeBLout");
	
	$circlesFoundFlag = false;
	$rectanglesFoundFlag = false;
	
	// ---------------------------- Local Shapes ----------------------------------------------
	$shapeContainerObjLocal = $profileObj->getShapeContainerObjLocal_fromDatabase();
	$sideNumbersArr = $shapeContainerObjLocal->getSideNumbers();
	foreach($sideNumbersArr as $thisSideNumber){
			
		$localShapesArr = $shapeContainerObjLocal->getShapeObjectsArr($thisSideNumber);
			
		// Make the shapes match the stacking order that stuff gets plotted on the PDF.
		$localShapesArr = array_reverse($localShapesArr);
		
		foreach($localShapesArr as $thisShapeObj) {
		
			$shapeCounter++; 
			$t->set_var("SHAPE_COUNTER",$shapeCounter); 	

			$thisShapeObj->fillTemplateWithCommonShapeVars($t);

			$t->set_var("SHAPE_SIDE_OPTIONS",Widgets::buildSelect($sideDescriptionsOptionsList, $thisSideNumber));
			$t->allowVariableToContainBrackets("SHAPE_SIDE_OPTIONS");
			
			$t->set_var(array("LOCAL_CHECKED"=>"checked", "GLOBAL_CHECKED"=>""));
			


			// Since we have 2 shape blocks Set... parse out the Correct HTML block depdending on our Shape Type.
			if($thisShapeObj->getShapeName() == "rectangle"){
				$rectanglesFoundFlag = true;
				$t->parse("RectangleShapeBLout","RectangleShapeBL",true); 
			}
			else if($thisShapeObj->getShapeName() == "circle"){
				$t->parse("CircleShapeBLout","CircleShapeBL",true);
				$circlesFoundFlag = true;
			}
			else{
				throw new Exception("Undefined Local Shape Type.");
			}
		}
	}

	// ---------------------------- Global Shapes ----------------------------------------------
	$shapeContainerObjGlobal = $profileObj->getShapeContainerObjGlobal_fromDatabase();
	
	$sideNumbersArr = $shapeContainerObjGlobal->getSideNumbers();
	foreach($sideNumbersArr as $thisSideNumber){
			
		$globalShapesArr = $shapeContainerObjGlobal->getShapeObjectsArr($thisSideNumber);
			
		// Make the shapes match the stacking order that stuff gets plotted on the PDF.
		$globalShapesArr = array_reverse($globalShapesArr);
		
		foreach($globalShapesArr as $thisShapeObj) {
		
			$shapeCounter++; 
			$t->set_var("SHAPE_COUNTER",$shapeCounter); 	

			$thisShapeObj->fillTemplateWithCommonShapeVars($t);

			$t->set_var("SHAPE_SIDE_OPTIONS",Widgets::buildSelect($sideDescriptionsOptionsList, $thisSideNumber));
			$t->allowVariableToContainBrackets("SHAPE_SIDE_OPTIONS");
			
			$t->set_var(array("LOCAL_CHECKED"=>"", "GLOBAL_CHECKED"=>"checked"));


			if($thisShapeObj->getShapeName() == "rectangle"){
				$rectanglesFoundFlag = true;
				$t->parse("RectangleShapeBLout","RectangleShapeBL",true); 
			}
			else if($thisShapeObj->getShapeName() == "circle"){
				$t->parse("CircleShapeBLout","CircleShapeBL",true);
				$circlesFoundFlag = true;
			}
			else{
				throw new Exception("Undefined Local Shape Type.");
			}
		}
	}


	
	
	// New Shapes get added after our existing ones.
	$shapeCounter++; 
	$t->set_var("MAX_SHAPE_COUNTER", $shapeCounter);
	

	if(!$rectanglesFoundFlag)
		$t->discard_block("origPage", "AllRectangleShapesBL");	
	if(!$circlesFoundFlag)
		$t->discard_block("origPage", "AllCircleShapesBL");
	if(!$circlesFoundFlag && !$rectanglesFoundFlag)
		$t->discard_block("origPage", "AllExistingShapesBL");
		



	$cmykCounter = 0;
	$cmykBlocksObj = $profileObj->getCmykBlocksObjects();
	
	$t->set_block("origPage","ExistingCMYKgroupBL","ExistingCMYKgroupBLout");
	
	foreach($cmykBlocksObj as $thisCMYKblocksObj) {
	
		$cmykCounter++; 
		
		$t->set_var("CMYK_SIDE_OPTIONS",Widgets::buildSelect($sideDescriptionsOptionsList, $thisCMYKblocksObj->getSideNumber()));
		$t->allowVariableToContainBrackets("CMYK_SIDE_OPTIONS");
		
		$t->set_var("CMYK_BLOCK_COUNTER",$cmykCounter); 	
		$t->set_var("CMYK_X", $thisCMYKblocksObj->getStartXcoord());
		$t->set_var("CMYK_Y", $thisCMYKblocksObj->getStartYcoord());		
		$t->set_var("CMYK_BLOCKWIDTH", $thisCMYKblocksObj->getGroupWidth());
		$t->set_var("CMYK_BLOCKHEIGHT", $thisCMYKblocksObj->getGroupHeight());
		$t->set_var("CMYK_NUMBLOCKS", $thisCMYKblocksObj->getTotalGroups());
		$t->set_var("CMYK_BLOCKROTATION", $thisCMYKblocksObj->getRotation());
		$t->set_var("CMYK_BLOCKSPACING", $thisCMYKblocksObj->getSpacingBetweenGroups());
		$t->set_var("CMYK_BLOCK_ID", $thisCMYKblocksObj->getCmykBlockID());
	
		$t->parse("ExistingCMYKgroupBLout","ExistingCMYKgroupBL",true); 	
	}	
				
	if(empty($cmykCounter))
		$t->discard_block("origPage", "AllExistingCMYKgroupsBL");
		

	// New CMYK blocks get added after our exising ones.
	$cmykCounter++; 
	$t->set_var("MAX_CMYK_COUNTER", $cmykCounter);
	
	

	
	
}
else if($view == "newProfile"){

	$t->discard_block("origPage", "ProfileStartBL");
	$t->discard_block("origPage", "editPDFprofileLabel");
	$t->discard_block("origPage", "EditProfileHiddenInputs");
	$t->discard_block("origPage", "ExistingProfileNameBL");
	$t->discard_block("origPage", "CantChangeVendorBL");
		
	$profileObj = new PDFprofile($dbCmd, $editProduct);	

	// Store current Profile Names for not caseSensitive JS Validation 
	$existingProfileNames = $profileObj->getProfileNames();
		
	$existingProfileNamesJSArr = "";	
	foreach($existingProfileNames as $thisProfileName)	
		$existingProfileNamesJSArr .= "'" . strtoupper($thisProfileName) ."',";	

	$existingProfileNamesJSArr = substr($existingProfileNamesJSArr, 0, -1);
	
	$t->set_var("EXISTING_PROFILE_NAMES_ARRAY",$existingProfileNamesJSArr);
	$t->set_var("ENABLE_PROFILE_NAMES_CHECK","true");
		
	// Set default values 
	$t->set_var("PROFILE_NOTES","");
	$t->set_var("SHEET_WIDTH" ,"0");
	$t->set_var("SHEET_HEIGHT","0");
	$t->set_var("FORCE_UNIT_SIZE_ASPECT_RATIO_NO", "checked");
	$t->set_var("ROWS"  ,"0");
	$t->set_var("COLUMNS" ,"0");
	$t->set_var("ROTATION_VALUES", Widgets::buildSelect(array("0"=>"0", "90"=>"90", "180"=>"180", "270"=>"270"),"0"));
	$t->allowVariableToContainBrackets("ROTATION_VALUES");
	$t->set_var("DISPLAY_COVERSHEET_NO", "checked");
	$t->set_var("DISPLAY_SUMMARY_SHEET_NO", "checked");	
	$t->set_var("SHOW_CROP_MARKS_YES", "checked");
	$t->set_var("SHOW_OUTSIDE_BORDER_YES", "checked");
	$t->set_var("HORIZONTAL_SPACING"  ,"0");
	$t->set_var("VERTICAL_SPACING"  ,"0");
	$t->set_var("BLEED_UNITS"  ,$productObj->getArtworkBleedPicas());
	$t->set_var("EXTRA_GAP_ROWS", "0");
	$t->set_var("EXTRA_GAP_COLUMNS", "0");
	$t->set_var("EXTRA_GAP_HORIZONTAL", "0");
	$t->set_var("EXTRA_GAP_VERTICAL", "0");
	$t->set_var("LEFT_MARGIN", "0");
	$t->set_var("BOTTOM_MARGIN", "0");
	$t->set_var("LABEL_X", "0");
	$t->set_var("LABEL_Y", "0");
	$t->set_var("LABEL_ROTATION", "0");
	$t->set_var("EXTRA_MIN_QUANTITY", "0");
	$t->set_var("EXTRA_PERCENT", "0");
	$t->set_var("EXTRA_MAX_LIMIT", "0");
	
	// Set some of the default values to what is stored in the Product.
	$t->set_var("PRODUCT_SETUP_BLEED", $productObj->getArtworkBleedPicas());
	$t->set_var("UNIT_WIDTH" , $productObj->getArtworkCanvasWidth());
	$t->set_var("UNIT_HEIGHT", $productObj->getArtworkCanvasHeight());
	
	// Take away the warning messages (only valid after Profile is Saved).
	$t->discard_block("origPage", "ProductSizeMismatchBL");
	$t->discard_block("origPage", "BleedSettingLargerThanProductMsgBL");
	

	$t->set_var("NEW_SHAPE_SIDE_LIST", Widgets::buildSelect($sideDescriptionsOptionsList, 0));
	$t->allowVariableToContainBrackets("NEW_SHAPE_SIDE_LIST");
	
	// Since there are not any existing Shape or CMYK objects in New Profiles,
	// Make sure "new shapes" and "new cmykblocks" have a counter of 1... since they are the last (and only) input fields in the form.
	$t->set_var("MAX_SHAPE_COUNTER", "1");
	$t->set_var("MAX_CMYK_COUNTER", "1");
	
	// Because we are making a new profile... there are no existing shapes or CMYK blocks yet.
	$t->discard_block("origPage", "AllExistingShapesBL");
	$t->discard_block("origPage", "AllExistingCMYKgroupsBL");
	
	// New Profiles are not able to define a Coversheet Matrix yet.
	$t->discard_block("origPage", "MatrixOrderBlock");
	
	
	
}
else{
	throw new Exception("Illegal View Type");
}




// Build the tabs
// Call to a library function because other Controller Scripts are also calling building the same tabs.
$mainTabsObj = new Navigation();
Widgets::buildMainTabsForProductSetupScreen($mainTabsObj, $editProduct);
$t->set_var("MAIN_PRODUCT_NAV_BAR", $mainTabsObj->GetTabsHTML("pdfprofile"));
$t->allowVariableToContainBrackets("MAIN_PRODUCT_NAV_BAR");




$t->pparse("OUT","origPage");




?>

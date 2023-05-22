<?

// Products may have multiple PDF profiles so that they may print on different sizes of Paper, or with different configurations.


class PDFprofile {

	private $_productID;
	private $_profileName;
	private $_pdfprofileID;
	private $_profileNotes;
	
	private $_pagewidth;
	private $_pageheight;
	private $_unitwidth;
	private $_unitheight;
	
	private $_profileNameArr = array();
	private $_profileUpdate  = array();
	
	private $_rows;
	private $_columns;
	private $_rotatecanvas;
	private $_bleedunits;
	private $_force_aspectratio;
	private $_gapr;
	private $_gapc;
	private $_gapsizeH;
	private $_gapsizeV;
	private $_hspacing;
	private $_vspacing;
	private $_lmargin;
	private $_bmargin;
	private $_labelx;
	private $_labely;
	private $_label_rotate;
	private $_display_coversheet;
	private $_display_summarysheet;
	private $_showCropMarks;
	private $_showOutsideBorder;
	private $_printExtraQuantityPercent;
	private $_printExtraQuantityMax;
	private $_printExtraQuantityMinStart;
	private $_coverSheetMatrix;
	private $_pDFProfileLoadedFlag; 
	private $_projectOptionsDescription;
	private $_profileRemarks;
	private $_cmykBlocksContainerObj;
	

	// Local Shape Container hold shapes that will be repeated in the same location for every artwork unit.  Like a Circle in the center of every business card.
	// Global shape Container hold shapes that are drawn 1 time on the entire parent sheet.   Like a rectangle on the top-left portion of the sheet (which contains 20 business cards).

	private $_shapeContainerObjLocal;
	private $_shapeContainerObjGlobal;
	
	
	
	##--------------   Constructor  ------------------##
	//-----------------------------------------------------------------------------------------------------------------
	// The parameter $projectOptionsDescription can be useful in certain cases where a Product Options causes additional shapes to be drawn on the PDF.
	//	For example... On a Product like Envelopes, there may be an option for a "Clear Window" (for the address to show through).    
	//	If that Product Option "Window" was selected, the PDF will be generated with a Rectangle drawn over the portion to be punched out.
	//-----------------------------------------------------------------------------------------------------------------
	// By default, All shapes will be included on the Profile, regardless of Project Options.
	// If you pass in a blank string (or null), then only Shapes without Options Limiters will be included on the Profile.
	// If you pass in an Options description.. then only shapes with matching Options Limiters will be included within the shape container that gets returned...
	// .... however, shapes defined without an Option Limiter will ALWAYS be returned.
	// Normally you would pass in an a string directly from the database (OrojectsOrdered->OptionsDescriptions) to this method whenever creating a new Object.  But you might be creating Objects of PDF profiles without having any affiliation with a Customer Order. 
	// Example.   	Project Options may read.... "(Style - Double-Sided, Card Stock - Linen, Window - Yes)".
	// 		The individual shapes may have a fairly short Option Limiter stored within it... such as "Window - Yes". The Option Limiter on the Shape is the "needle"... and the Options string passed into this method is the "haystack".
	// Because you may define an unlimited number of Shape Objects on a profile with different Option Limiters, there is good flexibility for having very project-specific shapes showing up.
	// This will always load the Production Product ID (If the Product has been Piggy Backed).
	function PDFprofile(DbCmd $dbCmd, $productID, $projectOptionsDescription = "ALL"){
	
		$this->_dbCmd = $dbCmd;	
		

		$this->_productID = $productID;
		

		$this->_projectOptionsDescription = $projectOptionsDescription;
		
		$this->_shapeContainerObjLocal  = new ShapeContainer();
		$this->_shapeContainerObjGlobal = new ShapeContainer();
		$this->_cmykBlocksContainerObj  = new CMYKblocksContainer();
		
		$this->_pDFProfileLoadedFlag = false;
		
		
		// These are acceptable defaults.
		// Other variables like "Page Width", "Rows", "Columns" are not acceptable to have a 'Zero value'.
		$this->_rotatecanvas = 0; 
		$this->_bleedunits = 0; 
		$this->_gapr = 0; 
		$this->_gapc = 0; 
		$this->_gapsizeH = 0; 
		$this->_gapsizeV = 0; 
		$this->_hspacing = 0; 
		$this->_vspacing = 0; 
		$this->_lmargin = 0; 
		$this->_bmargin = 0; 
		$this->_labelx = 0; 
		$this->_labely = 0; 
		$this->_label_rotate = 0;  
	}
	
	
	
	
	
	
	
	
	// -------------------------  BEGIN Static Methods ----------------------------------------
	
	
	// Static Method.  Remove the Profile by the Unique ID.
	static function deleteProfileByID(DbCmd $dbCmd, $profileID){

		$productIDofProfile = PDFprofile::getProductIDfromProfileID($dbCmd, $profileID);
		
		// If the Profile ID doesn't exist... then maybe someone already deleted it?
		if(empty($productIDofProfile))
			return;
	
		$dbCmd->Query("DELETE FROM pdfprofiles WHERE ID = " . intval($profileID));
			
		PDFprofile::EnsureThereIsDefaultProfile($dbCmd, $productIDofProfile);
	}
	
	// Static Method.  Remove the Shape by the ID, use ProfileID for better control
	static function deleteShapeByID(DbCmd $dbCmd, $shapeID, $profileID){
	
		$dbCmd->Query("DELETE FROM pdfprofileshapes WHERE ID = " . intval($shapeID) . " AND PDFProfileID=" . intval($profileID));
	}
	
	// Static Method.  Removes the CMYKBlock by the ID
	static function deleteCmykBlockByID(DbCmd $dbCmd, $cmykBlockID, $profileID){
		
		$dbCmd->Query("DELETE FROM pdfprofilecmykblocks WHERE ID = " . intval($cmykBlockID) . " AND PDFProfileID=" . intval($profileID));
	}
	

	
	// Static Method
	// We want there to be a Default Profile at all times.
	// There should always be a "Proof" profile... but we would prefer than it is never selected as the default.
	// Call this method whenever adding a deleting a Profile.
	static function EnsureThereIsDefaultProfile(DbCmd $dbCmd, $productID){
	
		// In case we deleted the Default Profile.  Try to select the first Profile that is not called "Proof".
		$profileObj = new PDFprofile($dbCmd, $productID);
		
		$profileNamesArr = $profileObj->getProfileNames();

		// If there are no Profile saved for this Product, then no point in continuing.
		if(sizeof($profileNamesArr) == 0)
			return;

		
		// If there is only 1 profile, then we don't have a choice, but to make that the default (even if it is "Proof").
		if(sizeof($profileNamesArr) == 1){
		
			// Don't worry about updating the DB if the Default is already saved.
			if($profileObj->getDefaultProfileName() == null){
				$profileObj->loadProfileByName(current($profileNamesArr));
				$profileObj->configureThisProfileToBeTheDefault();
			}
			
			return;
		}
		
		
		
		// We must have more than 1 profile at this point.
		// If ther Default Profile is set to "Proof"... or a Default has not been saved yet... then select the First Profile that is not "Proof".
		// Otherwise leave things the way they are.
		if($profileObj->getDefaultProfileName() == "Proof" || $profileObj->getDefaultProfileName() == null){
		
			foreach($profileNamesArr as $thisProfileName){

				if($thisProfileName == "Proof")
					continue;

				// Save Default and Exit upon the First Profile that is not "Proof"
				$profileObj->loadProfileByName($thisProfileName);
				$profileObj->configureThisProfileToBeTheDefault();
				return;
			}
		}
	}
	
	
	
	// Static Method.  Returns NULL is there ProfileID does not exist.

	static function getProductIDfromProfileID(DbCmd $dbCmd, $profileID){
		
		$dbCmd->Query("SELECT ProductID FROM pdfprofiles WHERE ID = " . intval($profileID));
		return $dbCmd->GetValue();
	
	}
	
	
	// Static Method.  Create a "Proof" profile if not exists.
	// The "Proof" profile is a special name.  Every Product should have a Proof profile so the customers have something to look at to preview their artwork.
	// You can all this when creating new Products.
	// Start out with a Page Size slightly larger than the Product's artwork.  The PDF Profile can always be edited later.
	static function createProofProfileIfDoesNotExist(DbCmd $dbCmd, $productID){
	
	
		if(!Product::checkIfProductIDexists($dbCmd, $productID))
			throw new Exception("Error in method createProofProfileIfDoesNotExist. The Product ID does not exist.");
		
		$productObj = new Product($dbCmd, $productID);
		$profileObj = new PDFprofile($dbCmd, $productID);
		
		
		if($profileObj->checkIfPDFprofileExists("Proof"))
			return;
		
		$profileObj->setProfileNotes("To preview the artwork.");
		
		// Give a 1" border around the Artwork.
		$profileObj->setPageWidth($productObj->getArtworkCanvasWidth() + 1);
		$profileObj->setPageHeight($productObj->getArtworkCanvasHeight() + 1);
		$profileObj->setUnitWidth($productObj->getArtworkCanvasWidth());
		$profileObj->setUnitHeight($productObj->getArtworkCanvasHeight());
		$profileObj->setBleedUnits($productObj->getArtworkBleedPicas());
		$profileObj->setRows(1);
		$profileObj->setColumns(1);
		$profileObj->setLmargin(0.5);
		$profileObj->setBmargin(0.5);
		$profileObj->setLabely(-4); // Set the Label off of the canvas area so that it won't get shown.
		$profileObj->setExtraQuantityMaximum(0);
		$profileObj->setExtraQuantityMinimumStart(0);
		$profileObj->setExtraQuantityPercentage(0);
		$profileObj->setShowCropMarks(true);
		$profileObj->setShowOutsideBorder(true);
		
		$profileObj->addPDFprofile("Proof");	
	}
	
	
	static function checkIfPDFprofileIDexists(DbCmd $dbCmd, $pdfProfileID){
		
		$dbCmd->Query("SELECT COUNT(*) FROM pdfprofiles WHERE ID = " . intval($pdfProfileID));		
		
		if($dbCmd->GetValue() == 0)
			return false;
		else
			return true;
		
	}
	
	
	
	
	
	
	
	
	
	
	
	// -------------------------  BEGIN Public Methods ----------------------------------------
	
	
	
	
	// Make sure to check if the Profile Name exists before trying to load it.
	function loadProfileByName($profileName){
	
		$this->_pDFProfileLoadedFlag = false;
					
		$this->_dbCmd->Query("SELECT ID FROM pdfprofiles WHERE ProductID = " . $this->_productID . " AND ProfileName LIKE '" . DbCmd::EscapeLikeQuery($profileName) . "'");		
		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("The profile does not exist: " . $profileName . " for Product: " . $this->_productID . ". Make sure to use the method checkIfPDFprofileExists() before calling loadProfileByName");
		
		$profileID = $this->_dbCmd->GetValue();
		
		$this->loadProfileByID($profileID);	
	}

	function loadProfileByID($pdfProfileID){
	
		$this->_pDFProfileLoadedFlag = false;
		
		// Just in case we are re-using the object to load multiple Profile IDs... start off with a clean slate each time.
		$this->_shapeContainerObjLocal  = new ShapeContainer();
		$this->_shapeContainerObjGlobal = new ShapeContainer();
		$this->_cmykBlocksContainerObj  = new CMYKblocksContainer();
				
		$this->_dbCmd->Query("SELECT * FROM pdfprofiles WHERE ID = ". intval($pdfProfileID));	

		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method loadProfileByID.  The ProfileID was not found.");
		
		$row = $this->_dbCmd->GetRow();	
		
		$this->_pdfprofileID 			= $row["ID"];
		$this->_profileName			= $row["ProfileName"];
		$this->_profileNotes			= $row["ProfileNotes"];
		$this->_productID 			= $row["ProductID"];
		$this->_rows 				= $row["RowsX"];
		$this->_columns 			= $row["ColumnsY"];
		$this->_rotatecanvas 			= $row["RotateCanvas"];
		$this->_bleedunits 			= $row["BleedUnits"];
		$this->_gapr 				= $row["Gapr"];
		$this->_gapc 				= $row["Gapc"];
		$this->_gapsizeH 			= $row["GapSizeH"];
		$this->_gapsizeV 			= $row["GapSizeV"];
		$this->_pagewidth 			= $row["PageWidth"];
		$this->_pageheight 			= $row["PageHeight"];
		$this->_hspacing 			= $row["HSpacing"];
		$this->_vspacing 			= $row["VSpacing"];
		$this->_lmargin 			= $row["LMargin"];
		$this->_bmargin 			= $row["BMargin"];
		$this->_unitwidth 			= $row["UnitWidth"];
		$this->_unitheight 			= $row["UnitHeight"];
		$this->_labelx 				= $row["LabelX"];
		$this->_labely 				= $row["LabelY"];
		$this->_label_rotate 			= $row["Label_Rotate"];
		$this->_printExtraQuantityPercent 	= $row["PrintExtraQuantityPercent"];
		$this->_printExtraQuantityMax 		= $row["PrintExtraQuantityMax"];
		$this->_printExtraQuantityMinStart	= $row["PrintExtraQuantityMinStart"];
		
		$this->_display_coversheet 		= $row["Display_Coversheet"] == "Y" ? true : false;
		$this->_display_summarysheet 		= $row["Display_Summarysheet"] == "Y" ? true : false;
		$this->_showOutsideBorder 		= $row["ShowOutsideBorder"] == "Y" ? true : false;
		$this->_showCropMarks 			= $row["ShowCropMarks"] == "Y" ? true : false;
		$this->_force_aspectratio 		= $row["Force_AspectRatio"] == "Y" ? true : false;

	
		
		$this->_pDFProfileLoadedFlag = true;



		// Add a CoverSheet Matrix to our Object... if one has been defined.
		if(empty($row["MatrixDefinition"])){
			$this->_coverSheetMatrix = null;
		}
		else{
			// Create a Matrix object out of the string definition stored in the DB.
			$matrixObj = new MatrixOrder();
			$matrixObj->setMatrixByStringFormat($row["MatrixDefinition"]);
			
			$this->_coverSheetMatrix = $matrixObj;
		}

		


		// Load all CMYK Block definitions linked to this profile.
		// Order the results by the Coordinates.  That is a good way to make sure that the CMYK blocked always appear on the front end in the same order (to perceive as editing).
		$this->_dbCmd->Query("SELECT * FROM pdfprofilecmykblocks WHERE PDFProfileID = " . $this->_pdfprofileID . " ORDER BY StartXCoord, StartYCoord");				
		while($row = $this->_dbCmd->GetRow()) {

			// Create new CMYK block Objects and add them into our CMYK Container Object.
			$cmykBlockObj = new CMYKblocks();
			
			$cmykBlockObj->setGroupWidth($row["GroupWidth"]);
			$cmykBlockObj->setGroupHeight($row["GroupHeight"]);
			$cmykBlockObj->setStartXcoord($row["StartXCoord"]);
			$cmykBlockObj->setStartYcoord($row["StartYCoord"]);
			$cmykBlockObj->setSpacingBetweenGroups($row["SpacingBetweenGroups"]);
			$cmykBlockObj->setTotalGroups($row["TotalGroups"]);
			$cmykBlockObj->setRotation($row["Rotation"]);
			$cmykBlockObj->setCmykBlockID($row["ID"]);
			$cmykBlockObj->setSideNumber($row["SideNumber"]);
			
			// Add to our collection of CMYK blocks.
			$this->_cmykBlocksContainerObj->addCMYKblocksObj($cmykBlockObj);				
		}
		


		// Load all shapes from the database that are linked to this profile.
		// Order the results by the Coordinates.  That is a good way to make sure that the Shapes always appear on the front end in the same order.
		// Ordering by the Remarks let's people manage the stacking order by including a Prefix.
		$this->_dbCmd->Query("SELECT * FROM pdfprofileshapes WHERE PDFProfileID = " . $this->_pdfprofileID . " ORDER BY GlobalLocal, Side, Remarks DESC, ShapeType, Xcoord, Ycoord");	

		while($row = $this->_dbCmd->GetRow()) {

			if($row["ShapeType"]=="C") 	
				$shapeObj = new ArtworkCircle($row["ShapeSpecificValue1"], $row["Xcoord"], $row["Ycoord"]);
			else if($row["ShapeType"]=="R") 
				$shapeObj = new ArtworkRectangle($row["ShapeSpecificValue1"], $row["ShapeSpecificValue2"], $row["Xcoord"], $row["Ycoord"]);
			else
				throw new Exception("Error in PDFprofile trying to load shapes. This type of shape has not been defined yet.");
				

			// Set Properties common to all Shapes (existing in the Base Class)
			$shapeObj->setShapeID($row["ID"]);
			$shapeObj->setFillColorRGB($row["FillColorRGB"]);
			$shapeObj->setLineColor($row["LineColor"]);
			$shapeObj->setLineStyle($row["LineStyle"]);
			$shapeObj->setLineThickness($row["LineThickness"]);
			$shapeObj->setLineAlpha($row["LineAlpha"]);
			$shapeObj->setFillAlpha($row["FillAlpha"]);
			$shapeObj->setRemarks($row["Remarks"]);
			$shapeObj->setOptionsLimiter($row["OptionsLimiter"]);
			$shapeObj->setRotation($row["Rotation"]);



			if($row["GlobalLocal"]=="L")
				$this->_shapeContainerObjLocal->addShapeObject($row["Side"],  $shapeObj);
			else if($row["GlobalLocal"]=="G")
				$this->_shapeContainerObjGlobal->addShapeObject($row["Side"], $shapeObj);
			else
				throw new Exception("Error in PDFprofile trying to load shapes. The location is neither set to Local or Global.");
		}
	}
	
	function getProfileNameLoaded(){
		
		if(!$this->_pDFProfileLoadedFlag)
			throw new Exception("Error in method getProfileNameLoaded. The Profile must be loaded before calling this method.");
			
		return $this->_profileName;
	}
	
	// Returns an array of Profile Names stored on this Product ID... or an empty array if no profiles defined yet.
	// The key to the Array is the ID of the Profile Name in the DB.
	function getProfileNames(){
		
		$retArr = array();
		
		$this->_dbCmd->Query("SELECT ID, ProfileName FROM pdfprofiles WHERE ProductID = " . $this->_productID . " ORDER BY ProfileName");
		
		while($row = $this->_dbCmd->GetRow())
			$retArr[$row["ID"]] = $row["ProfileName"];
		
		return $retArr;
	}


	function checkIfPDFprofileLoaded(){
	
		return $this->_pDFProfileLoadedFlag;
	}
	
	function checkIfPDFprofileExists($profileName){
		
		return in_array($profileName, $this->getProfileNames());
	}
	
	
	// Returns NULL if the default profile has not been set yet, or if no profiles have been saved yet.
	function getDefaultProfileName(){
	
		// Get the Default Profile
		$this->_dbCmd->Query("SELECT ProfileName from pdfprofiles WHERE DefaultProfile='Y' AND ProductID=" . $this->_productID . " LIMIT 1");
		return $this->_dbCmd->GetValue();
	}


	
	// Call this method after calling any/all of the setter methods.
	function updatePDFprofile() {
			
		$this->_ensurePDFprofileLoaded();
		
		$this->_fillDBfields();
				
		$this->_dbCmd->UpdateQuery("pdfprofiles",$this->_profileUpdate, "ID = $this->_pdfprofileID");			
				
		// Every time we Update this Profile, we will be removing all data from the linking tables, associated with this profile.
		$this->_dbCmd->Query("DELETE FROM pdfprofileshapes WHERE PDFProfileID = $this->_pdfprofileID");
		$this->_dbCmd->Query("DELETE FROM pdfprofilecmykblocks WHERE PDFProfileID = $this->_pdfprofileID");
		// ... then we will write all data back.
		// That keeps us from having to track additional relational IDs.
		$this->_writeShapeObjectsToDatabase();
		$this->_writeCMYKDefinitionsToDatabase();
	}
	


	// Set all of the "setter" methods... then call this method with the name of the Profile ou want to add.
	// Make sure to check if the profile exists first.
	function addPDFprofile($profileName) {
	
		$profileName = trim($profileName);
		
		// Make sure that nobody can change the Case on our special "Proof" profile.
		if(strtoupper($profileName) == "PROOF")
			$profileName = "Proof";
				
		if($this->_pDFProfileLoadedFlag)
			 throw new Exception("You can't add Profiles if a ProfileName already has been set.");
		
		if($this->checkIfPDFprofileExists($profileName))
			throw new Exception("Error with method addPDFprofile. The Profile Name already exists.");
			
		if(!preg_match("/^\w{3,60}$/", $profileName))
			throw new Exception("Error Creating a new Profile.  The Profile Name must be between 3 and 60 characters and it may not contain any spaces or special characters.");
	
		// All fields were set before by the user or loaded from the Database
		$this->_fillDBfields();
		
		$this->_profileUpdate["ProfileName"] 	= $profileName;
		$this->_profileUpdate["ProductID"] 	= $this->_productID;

		$this->_pdfprofileID = $this->_dbCmd->InsertQuery("pdfprofiles",$this->_profileUpdate);

		// Write to the "Linking" tables associated with this Profile.
		$this->_writeShapeObjectsToDatabase();
		$this->_writeCMYKDefinitionsToDatabase();
		
		
		// Just in Case this is our First Profile Added... it will be selected as the Default.
		// If we are adding a second profile (Not "Proof"), then that one will automatically be selected.
		PDFprofile::EnsureThereIsDefaultProfile($this->_dbCmd, $this->_productID);
		
		$this->_pDFProfileLoadedFlag = true;
	}
	
	
	
	// This updates the database immediately!!!
	// Similar to a "Default Printer" in windows... we need a default Profile for estimating "impressions" and printer speeds for a product.
	function configureThisProfileToBeTheDefault() {
		
		if(!$this->_pDFProfileLoadedFlag)
			throw new Exception("You can not call the method setThisProfileToBeTheDefaultForProduct until a the Profile has been loaded.");
		
		$this->_dbCmd->UpdateQuery("pdfprofiles", array("DefaultProfile"=>"N"), "ProductID = " . $this->_productID);			
		$this->_dbCmd->UpdateQuery("pdfprofiles", array("DefaultProfile"=>"Y"), "ID=" . $this->_pdfprofileID);			
	}
	
	
	// A profile must be currently loaded.
	// This will save a copy of it to the database and return the new PDF profile ID.
	// Pass in the new PDF profile name.  You must ensure that the name is not already taken... so maybe adding a suffix (like "_copy") to the existing profile name.
	function saveCopyOfThisProfile($newProfileName){
		
		$this->_ensurePDFprofileLoaded();
		
		if($this->checkIfPDFprofileExists($newProfileName))
			throw new Exception("The method saveCopyOfThisProfile is trying to save a Profile Name which already exists.");
		
		// Tweak some of the Private Members ... run an insert.  
		// Then we can "reload" our current PDF Profile ID fresh from the DB when we are done.
		$currentPdfProfileID = $this->_pdfprofileID;
		
		$this->_pDFProfileLoadedFlag = false;
		
		$this->addPDFprofile($newProfileName);
		
		$newPdfProfileID = $this->_pdfprofileID;
		
		// Now load this object back to its original state.
		$this->loadProfileByID($currentPdfProfileID);
		
		// Return the Profile ID that we just created by doing a copy.
		return $newPdfProfileID;
		
	}
	
	
	
	
	
	
	
	
	
	
	
	// ----------------------------------- Getter Methods --------------------------------------------------
	
	function getProfileID(){
		$this->_ensurePDFprofileLoaded();
		return $this->_pdfprofileID;
	}
	function getProductID(){
		$this->_ensurePDFprofileLoaded();
		return $this->_productID;
	}
	
	
	// Returns an array of CMYKblocks Objects
	// Empty array if no values.
	function getCmykBlocksObjects(){
		return $this->_cmykBlocksContainerObj->getCMYKblocksCollection();
	}
	function getProfileNotes(){
		$this->_ensurePDFprofileLoaded();
		return $this->_profileNotes;
	}	
	function getProfileName(){
		$this->_ensurePDFprofileLoaded();
		return $this->_profileName;
	}
	function getQuantity() {
		$this->_ensurePDFprofileLoaded();
		return ($this->_rows * $this->_columns);
	}	
	function getRows() {
		$this->_ensurePDFprofileLoaded();
		return $this->_rows;
	}
	function getColumns() {
		$this->_ensurePDFprofileLoaded();
		return $this->_columns;
	}
	function getRotatecanvas() {
		$this->_ensurePDFprofileLoaded();
		return $this->_rotatecanvas;
	}
	function getBleedUnits() {
		$this->_ensurePDFprofileLoaded();
		return $this->_bleedunits;
	}
	function getGapr() {
		$this->_ensurePDFprofileLoaded();
		return $this->_gapr;
	}
	function getGapc() {
		$this->_ensurePDFprofileLoaded();
		return $this->_gapc;
	}
	function getGapsizeH() {
		$this->_ensurePDFprofileLoaded();
		return $this->_gapsizeH;
	}
	function getGapsizeV() {
		$this->_ensurePDFprofileLoaded();
		return $this->_gapsizeV;
	}
	function getPagewidth() {
		$this->_ensurePDFprofileLoaded();
		return $this->_pagewidth;
	}
	function getPageheight() {
		$this->_ensurePDFprofileLoaded();
		return $this->_pageheight;
	}
	function getHspacing() {
		$this->_ensurePDFprofileLoaded();
		return $this->_hspacing;
	}
	function getVspacing() {
		$this->_ensurePDFprofileLoaded();
		return $this->_vspacing;
	}
	function getLmargin() {
		$this->_ensurePDFprofileLoaded();
		return $this->_lmargin;
	}
	function getBmargin() {
		$this->_ensurePDFprofileLoaded();
		return $this->_bmargin;
	}
	function getUnitWidth() {
		$this->_ensurePDFprofileLoaded();
		return $this->_unitwidth;
	}
	function getUnitHeight() {
		$this->_ensurePDFprofileLoaded();
		return $this->_unitheight;
	}
	function getLabelx() {
		$this->_ensurePDFprofileLoaded();
		return $this->_labelx;
	}
	function getLabely() {
		$this->_ensurePDFprofileLoaded();
		return $this->_labely;
	}
	function getLabelrotate() {
		$this->_ensurePDFprofileLoaded();
		return $this->_label_rotate;
	}
	

	// returns true or false
	function getForceAspectRatio() {
		$this->_ensurePDFprofileLoaded();
		return $this->_force_aspectratio;
	}
	// returns true/false
	function getDisplayCoverSheet() {
		$this->_ensurePDFprofileLoaded();
		return $this->_display_coversheet;
	}
	// returns true/false
	function getDisplaySummarySheet() {
		$this->_ensurePDFprofileLoaded();
		return $this->_display_summarysheet;
	}

	// returns true/false
	function getShowOutsideBorder(){
		$this->_ensurePDFprofileLoaded();
		return $this->_showOutsideBorder;
	}
	// returns true/false
	function getShowCropMarks(){
		$this->_ensurePDFprofileLoaded();
		return $this->_showCropMarks;
	}
	// returns NULL if the coversheet matrix has not been defined for this Profile.
	// If the Rows/Columns withing our internal PDF profile do not match the rows/cols in the Matrix definition... 
	// .... then wipe out the Matrix. It will have to be redefined by the user. 
	function getCoverSheetMatrix(){
		
		if($this->_coverSheetMatrix){
			if($this->_coverSheetMatrix->getNumberRows() != $this->_rows || $this->_coverSheetMatrix->getNumberColumns() != $this->_columns)
				$this->_coverSheetMatrix = null;
		}
		
		return $this->_coverSheetMatrix;
	}
	
	
	function getExtraQuantityPercentage(){
		$this->_ensurePDFprofileLoaded();
		return $this->_printExtraQuantityPercent;
	}
	function getExtraQuantityMaximum(){
		$this->_ensurePDFprofileLoaded();
		return $this->_printExtraQuantityMax;
	}
	function getExtraQuantityMinimumStart(){
		$this->_ensurePDFprofileLoaded();
		return $this->_printExtraQuantityMinStart;
	}
	
	
	
	// Returns the total canvas area (including bleed) for this artwork unit (in Picas).
	function getUnitCanvasAreaPicas(){
	
		$areaWidth = $this->_unitwidth * 72 + $this->_bleedunits;
		$areaHeight = $this->_unitheight * 72 + $this->_bleedunits;
		
		return $areaWidth * $areaHeight;
	}
	
	
	// Returns the Shapes as they were added to the Database.
	// Does not filter out by Project Options description or mix CMYK Blocks etc.
	// This is mainly used for administering the Profile, during the Setup phase.
	function getShapeContainerObjLocal_fromDatabase(){
		
		$this->_ensurePDFprofileLoaded();
		
		return $this->_shapeContainerObjLocal;	
	}
	function getShapeContainerObjGlobal_fromDatabase(){
		
		$this->_ensurePDFprofileLoaded();
		
		return $this->_shapeContainerObjGlobal;	
	}
	
	
	// These method will return the Shape Container as it is meant for printing.
	// This gives us the ability to filter out shapes by Project Options
	// We may also mix in additional shapes that are infered from CMYK blocks, etc.	
	function getLocalShapeContainer(){
		return $this->_getShapeContainerForProduction("LOCAL");
	}
	

	function getGlobalShapeContainer(){
		return $this->_getShapeContainerForProduction("GLOBAL");
	}
	











	// --------------------------- Setter Methods ----------------------------------

	function setProfileName($profileName) {
			
		if(!preg_match("/^\w{3,60}$/", $profileName))
			throw new Exception("Can't set Profile Name... it must be between 3 and 60 characters and it may not contain any spaces or special characters.");
		
		$this->_profileName = $profileName;
	}
	
	function setProfileNotes($x) {
		if(strlen($x) > 250)
			throw new Exception("Error in Method PDFprofiles->setProductNotes, can not exceed 250 chars.");
		$this->_profileNotes = $x;
	}
	
	function setRows($x) {
		$x=intval($x);
		if($x==0)
			throw new Exception("$x is not a valid parameter for setRows");
		$this->_rows = $x;
	}
	function setColumns($x) {	
		$x=intval($x);
		if($x==0)
			throw new Exception("$x is not a valid parameter for setColumns");
		$this->_columns = $x;
	}
	function setRotatecanvas($x) {
		$x=intval($x);
		if(!in_array($x, array("0", "90", "180", "270")))
			throw new Exception("$x is not a valid parameter for setRotatecanvas");
		$this->_rotatecanvas = $x;
	}
	function setBleedUnits($x) {
		$this->_bleedunits = floatval($x);
	}
	function setGapr($x) {
		$x=intval($x);
		$this->_gapr = $x;
	}
	function setGapc($x) {
		$x=intval($x);
		$this->_gapc = $x;
	}
	function setGapsizeH($x) {
		$x = floatval($x);
		$this->_gapsizeH = $x;
	}
	function setGapsizeV($x) {
		$x = floatval($x);
		$this->_gapsizeV = $x;
	}
	function setPagewidth($x) {
		$x = floatval($x);
		if(!$x>0)
			throw new Exception("$x is not a valid parameter for setPagewidth");
		$this->_pagewidth = $x;
	}
	function setPageheight($x) {
		$x = floatval($x);
		if(!$x>0)
			throw new Exception("$x is not a valid parameter for setPageheight");
		$this->_pageheight = $x;
	}
	function setHspacing($x) {
		$x = floatval($x);
		$this->_hspacing = $x;
	}
	function setVspacing($x) {
		$x = floatval($x);
		$this->_vspacing = $x;
	}
	function setLmargin($x) {
		$x = floatval($x);
		$this->_lmargin = $x;
	}
	function setBmargin($x) {
		$x = floatval($x);
		$this->_bmargin = $x;
	}
	function setUnitWidth($x) {
		$x = floatval($x);
		if(!$x>0)
			throw new Exception("$x is not a valid parameter for setUnitWidth");
		$this->_unitwidth = $x;
	}
	function setUnitHeight($x) {
		$x = floatval($x);
		if(!$x>0)
			throw new Exception("$x is not a valid parameter for setUnitHeight");
		$this->_unitheight = $x;
	}
	function setLabelx($x) {
		$x = floatval($x);
		$this->_labelx = $x;
	}
	function setLabely($x) {
		$x = floatval($x);
		$this->_labely = $x;
	}
	function setLabelrotate($x) {
		$x=intval($x);
		if($x >= 360 || $x < 0)
			throw new Exception("Error in method setLabelrotate, value must be less than 360 ");
		$this->_label_rotate = $x;
	}
	function setDisplayCoverSheet($x) {
		if(!is_bool($x))
			throw new Exception("Error in method setDisplayCoverSheet, must be boolean.");
		$this->_display_coversheet = $x;
	}
	function setDisplaySummarySheet($x) {
		if(!is_bool($x))
			throw new Exception("Error in method setDisplaySummarySheet, must be boolean.");
		$this->_display_summarysheet = $x;
	}

	function setShowOutsideBorder($x){
		if(!is_bool($x))
			throw new Exception("Error in method setShowOutsideBorder, must be boolean.");	
		$this->_showOutsideBorder = $x;
	}
	function setShowCropMarks($x){
		if(!is_bool($x))
			throw new Exception("Error in method setShowCropMarks, must be boolean.");	
		$this->_showCropMarks = $x;
	}
	function setForceAspectRatio($x) {
		if(!is_bool($x))
			throw new Exception("Error in method setForceAspectRatio, must be boolean.");
			
		$this->_force_aspectratio = $x;
	}
	function setExtraQuantityPercentage($x) {
		$x = WebUtil::FilterData($x, FILTER_SANITIZE_FLOAT);
		$this->_printExtraQuantityPercent = $x;
	}
	function setExtraQuantityMaximum($x) {
		$x = intval($x);
		$this->_printExtraQuantityMax = $x;
	}
	function setExtraQuantityMinimumStart($x) {
		$x = intval($x);
		$this->_printExtraQuantityMinStart = $x;
	}
	
	
	// Set to Null if you don't want a coversheet matrix
	// Make sure to do all of your Error checking in Javascript or with the Controller Script before trying to create a Matrix Object from user input.
	function setCoverSheetMatrix(MatrixOrder $matrixObj = NULL) {

		if(empty($matrixObj)){
			$this->_coverSheetMatrix = null;
			return;
		}
				
		$this->_coverSheetMatrix = $matrixObj;
		
	}




	// Removes all shapes from this Object. It does not Remove entries from the DB until this PDFprofile's method "updatePDFprofile" is called.
	// This can be useful before adding all of the shapes back in from a Form Submission.
	function removeAllLocalShapes(){
		$this->_shapeContainerObjLocal  = new ShapeContainer();
	}
	function removeAllGlobalShapes(){
		$this->_shapeContainerObjGlobal  = new ShapeContainer();
	}
	function removeAllCmykBlocks(){
		$this->_cmykBlocksContainerObj  = new CMYKblocksContainer();
	}
	
	
	
	



	// Pass in a shape Object and the Side it is meant to be drawn on.
	function addLocalShapeObj($sideNumber, ArtworkShape $newShapeObj) {

		$this->_shapeContainerObjLocal->addShapeObject($sideNumber, $newShapeObj);
	}
	
	function addGlobalShapeObj($sideNumber, ArtworkShape $newShapeObj) {

		$this->_shapeContainerObjGlobal->addShapeObject($sideNumber, $newShapeObj);
	}
	
	
	// Will store the CMYKblocks Object to our CMYK Container.
	// Will draw the colored rectangles on our Global Shape container only when called by get method
	function addCmykBlock(CMYKblocks $cmykBlocksObj) {
	
		$this->_cmykBlocksContainerObj->addCMYKblocksObj($cmykBlocksObj);
	}
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	// --------------------- Private Methods -------------------------
	
	function _ensurePDFprofileLoaded(){
		
		if(!$this->_pDFProfileLoadedFlag)
			throw new Exception("A PDF profile must be loaded before calling this method.");
	}
	
	
	

	// This method is private to keep programmers from having to remember to pass in a "GlOBAL/LOCAL" string.
	// Instead, the public method names gives programmers a hint how to use them... so they don't need to read comments like these.
	function _getShapeContainerForProduction($globalOrLocal){
		
		$this->_ensurePDFprofileLoaded();
		

		if($globalOrLocal == "GLOBAL")
			$shapeContainerReferenceObj = $this->_shapeContainerObjGlobal;
		else if($globalOrLocal == "LOCAL")
			$shapeContainerReferenceObj = $this->_shapeContainerObjLocal;
		else
			throw new Exception("Error in method getShapeContainer. The first parameter must be set to either Global or Local.");

		
		// Create a new blank Shape container and then we will start selectively including shapes into it.
		$returnShapeContainerObj = new ShapeContainer();
		
		$sideNumbersArr = $shapeContainerReferenceObj->getSideNumbers();

		foreach($sideNumbersArr as $thisSideNumber){
		
			$shapeObjectsArr = $shapeContainerReferenceObj->getShapeObjectsArr($thisSideNumber);

			foreach($shapeObjectsArr as $thisShapeObj){

				$shapeOptionLimiter = $thisShapeObj->getOptionsLimiter();
			
				if($this->_projectOptionsDescription == "ALL"){

					$returnShapeContainerObj->addShapeObject($thisSideNumber, $thisShapeObj);
				}
				else if(empty($this->_projectOptionsDescription)){

					// If a blank options description is provided.. then only shapes without an option limiter are returned.
					if(empty($shapeOptionLimiter))
						$returnShapeContainerObj->addShapeObject($thisSideNumber, $thisShapeObj);
				}
				else{
					// case insensitive search to see if the Option limiter is part of the full options description.
					if(empty($shapeOptionLimiter) || stristr($this->_projectOptionsDescription, $shapeOptionLimiter) !== false) 
						$returnShapeContainerObj->addShapeObject($thisSideNumber, $thisShapeObj);
				}
			}
			
		}
		
		$sideNumbersArr = $this->_cmykBlocksContainerObj->getSideNumbers();

		foreach($sideNumbersArr as $thisSideNumber){

			// Only the Global Shapes Container will contain all of the CMYK blocks.
			if($globalOrLocal == "GLOBAL"){
				$this->_cmykBlocksContainerObj->drawAllCMYKblocksOntoShapeContainer($returnShapeContainerObj, $thisSideNumber);
			}
		}
			
		return $returnShapeContainerObj;
	}



	// Common fields that are delivered to the DB, regardless of "adding" or "updating".
	function _fillDBfields(){

		if(empty($this->_rows))
			throw new Exception("Error saving PDF profile to database, Rows have not been set.");
		if(empty($this->_columns))
			throw new Exception("Error saving PDF profile to database, Columns have not been set.");
		if(empty($this->_pagewidth))
			throw new Exception("Error saving PDF profile to database, PageWidth have not been set.");
		if(empty($this->_pageheight))
			throw new Exception("Error saving PDF profile to database, PageHeight have not been set.");
		if(empty($this->_unitwidth))
			throw new Exception("Error saving PDF profile to database, UnitWidth have not been set.");
		if(empty($this->_unitheight))
			throw new Exception("Error saving PDF profile to database, UnitHeight have not been set.");	
		

		$this->_profileUpdate["ProfileName"] 		= $this->_profileName;
		$this->_profileUpdate["ProfileNotes"]		= $this->_profileNotes;
		$this->_profileUpdate["RowsX"]             	= $this->_rows;
		$this->_profileUpdate["ColumnsY"]        	= $this->_columns;
		$this->_profileUpdate["RotateCanvas"] 		= $this->_rotatecanvas;
		$this->_profileUpdate["BleedUnits"] 		= $this->_bleedunits;
		$this->_profileUpdate["Force_AspectRatio"] 	= $this->_force_aspectratio ? "Y" : "N";
		$this->_profileUpdate["Gapr"] 				= $this->_gapr;
		$this->_profileUpdate["Gapc"] 				= $this->_gapc;
		$this->_profileUpdate["GapSizeH"] 			= $this->_gapsizeH;
		$this->_profileUpdate["GapSizeV"] 			= $this->_gapsizeV;
		$this->_profileUpdate["PageWidth"] 			= $this->_pagewidth;
		$this->_profileUpdate["PageHeight"] 		= $this->_pageheight;
		$this->_profileUpdate["HSpacing"] 			= $this->_hspacing;
		$this->_profileUpdate["VSpacing"] 			= $this->_vspacing;
		$this->_profileUpdate["LMargin"] 			= $this->_lmargin;
		$this->_profileUpdate["BMargin"] 			= $this->_bmargin;
		$this->_profileUpdate["UnitWidth"] 			= $this->_unitwidth;
		$this->_profileUpdate["UnitHeight"] 		= $this->_unitheight;
		$this->_profileUpdate["LabelX"] 			= $this->_labelx;
		$this->_profileUpdate["LabelY"] 			= $this->_labely;
		$this->_profileUpdate["Label_Rotate"] 		= $this->_label_rotate;
		$this->_profileUpdate["Display_Coversheet"] 		= $this->_display_coversheet ? "Y" : "N";
		$this->_profileUpdate["Display_Summarysheet"]   	= $this->_display_summarysheet ? "Y" : "N";
		$this->_profileUpdate["ShowOutsideBorder"]  = $this->_showOutsideBorder ? "Y" : "N";
		$this->_profileUpdate["ShowCropMarks"]   	= $this->_showCropMarks ? "Y" : "N";
		$this->_profileUpdate["PrintExtraQuantityPercent"]	= $this->_printExtraQuantityPercent;
		$this->_profileUpdate["PrintExtraQuantityMax"] 		= $this->_printExtraQuantityMax;
		$this->_profileUpdate["PrintExtraQuantityMinStart"] = $this->_printExtraQuantityMinStart;
				
		
		// Save the Matrix as a String into the Database (if there is one).
		// Call the "getter method" to be sure there are no mis-match errors before writing to the DB.
		$this->_coverSheetMatrix = $this->getCoverSheetMatrix();
		
		if($this->_coverSheetMatrix)
			$this->_profileUpdate["MatrixDefinition"] = $this->_coverSheetMatrix->getMatrixDefinitionInStringFormat();
		else
			$this->_profileUpdate["MatrixDefinition"] = null;

	}
	
	
	
	// Writes both Local and Global Shapes to the database.
	function _writeShapeObjectsToDatabase(){
	
		// Get a list of all Side numbers that have shapes defined for them.
		$sideNumbersArr = $this->_shapeContainerObjGlobal->getSideNumbers();
		
		foreach($sideNumbersArr as $thisSideNumber){
		
			// Write each Shape to the database for the given side number.
			
			// Global
			$globalShapesArr = $this->_shapeContainerObjGlobal->getShapeObjectsArr($thisSideNumber);
			foreach($globalShapesArr as $thisShapeObj)
				$this->_writeSingleShapeToDatabase($thisSideNumber, $thisShapeObj, "G");
		}
		
		
		$sideNumbersArr = $this->_shapeContainerObjLocal->getSideNumbers();
		
		foreach($sideNumbersArr as $thisSideNumber){
			
			// Write each Shape to the database for the given side number.
			
			// Local
			$localShapesArr = $this->_shapeContainerObjLocal->getShapeObjectsArr($thisSideNumber);
			foreach($localShapesArr as $thisShapeObj)
				$this->_writeSingleShapeToDatabase($thisSideNumber, $thisShapeObj, "L");
		}
		
	}
	
	function _writeSingleShapeToDatabase($sideNumber, &$shapeObj, $globalLocal){

		if(!in_array($globalLocal, array("L", "G")))
			throw new Exception("Error in method _writeSingleShapeToDatabase.  The Global Local Parameter is incorrect.");
		
		
		$shapeHash["PDFProfileID"] = $this->_pdfprofileID;
		$shapeHash["Side"] = $sideNumber;
		$shapeHash["GlobalLocal"] = $globalLocal;
		
		// Prepare Common Shapes Fields (as defined in the Base Class).
		$shapeHash["Remarks"] = $shapeObj->getRemarks();
		$shapeHash["OptionsLimiter"] = $shapeObj->getOptionsLimiter();
		$shapeHash["FillColorRGB"] = $shapeObj->getFillColorRGB();
		$shapeHash["LineColor"] = $shapeObj->getLineColor();
		$shapeHash["LineStyle"] = $shapeObj->getLineStyle();
		$shapeHash["LineThickness"] = $shapeObj->getLineThickness();
		$shapeHash["FillAlpha"] = $shapeObj->getFillAlpha();
		$shapeHash["LineAlpha"] = $shapeObj->getLineAlpha();
		$shapeHash["Xcoord"] = $shapeObj->getXCoord();
		$shapeHash["Ycoord"] = $shapeObj->getYCoord();
		$shapeHash["Rotation"] = $shapeObj->getRotation();
		

		// Prepare Shape Specific Fields
		if($shapeObj->getShapeName() == "circle"){
			$shapeHash["ShapeType"] = "C";
			$shapeHash["ShapeSpecificValue1"] = $shapeObj->getRadius();
			$shapeHash["ShapeSpecificValue2"] = null;
		}
		else if($shapeObj->getShapeName() == "rectangle"){
			$shapeHash["ShapeType"] = "R"; 
			$shapeHash["ShapeSpecificValue1"] = $shapeObj->getWidth();
			$shapeHash["ShapeSpecificValue2"] = $shapeObj->getHeight();
		}
		else{
			throw new Exception("Error in method _writeSingleShapeToDatabase. The Shape type has not been defined yet.");
		}
		
		
		$this->_dbCmd->InsertQuery("pdfprofileshapes", $shapeHash);
	}
	
	
		
	// Insert cmyk block definitions to the database
	function _writeCMYKDefinitionsToDatabase() {
	
		$cmykObjectsArr = $this->_cmykBlocksContainerObj->getCMYKblocksCollection();

		foreach($cmykObjectsArr as $thisCMYKblocksObj) {	

			$cmykHash["PDFProfileID"] = $this->_pdfprofileID;
			$cmykHash["GroupWidth"] = $thisCMYKblocksObj->getGroupWidth();
			$cmykHash["GroupHeight"] = $thisCMYKblocksObj->getGroupHeight();
			$cmykHash["StartXCoord"] = $thisCMYKblocksObj->getStartXcoord();
			$cmykHash["StartYCoord"] = $thisCMYKblocksObj->getStartYcoord();
			$cmykHash["TotalGroups"] = $thisCMYKblocksObj->getTotalGroups();
			$cmykHash["Rotation"] = $thisCMYKblocksObj->getRotation();
			$cmykHash["SideNumber"] = $thisCMYKblocksObj->getSideNumber();
			$cmykHash["SpacingBetweenGroups"] = $thisCMYKblocksObj->getSpacingBetweenGroups();

			$this->_dbCmd->InsertQuery("pdfprofilecmykblocks", $cmykHash);
		}
	}
	

}



?>
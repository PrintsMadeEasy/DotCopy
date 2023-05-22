<?

// This class is meant to organize multiple PDF profiles and plot them together on a single master PDF profile.
// 

class SuperPDFprofile {

	private $_dbCmd;
	private $_superProfileName;
	private $_sheetWidth;
	private $_sheetHeight;
	private $_barcodeX;
	private $_barcodeY;
	private $_barcodeRotateDeg;
	private $_superProfileNotes;
	private $_superProfileID;
	private $_domainID;
	private $_subProfilesArr = array();
	private $_allProfileNamesArr = array();
	private $_printingPressIDs = array();
	private $_cachedPDFprofileObjArr = array();

	##--------------   Constructor  ------------------##

	// Thin constructor can also be called just to add a new profile when the profile name is not yet in the database 
	function SuperPDFprofile(){

		$this->_dbCmd = new DbCmd();	
		$this->_superProfileID = null;
	}
	
	// Returns NULL if the Super PDF profile ID does not exist.
	static function getDomainIDofSuperProfile($superProfileID){
		$dbCmd = new DbCmd();
		
		$dbCmd->Query("SELECT DomainID FROM superpdfprofiles WHERE ID=" . intval($superProfileID));
		return $dbCmd->GetValue();
	}
	
	
	// Will move the sorting sequence up or down relative to where it was (compared to other SuperPDF profiles).
	static function moveSuperProfilePosition($superPDFprofileID, $moveForward = true){
		
		$dbCmd = new DbCmd();
		$allProfileIDs = array_keys(SuperPDFprofile::getAllProfileNames($dbCmd, Domain::oneDomain()));
		
		if(!in_array($superPDFprofileID, $allProfileIDs))
			throw new Exception("Error in method moveSubProfilePosition. The ID does not exist.");
			
		$arrayShifted = WebUtil::arrayMoveElement($allProfileIDs, $superPDFprofileID, $moveForward);
		
		for($newSortPos=0; $newSortPos<sizeof($arrayShifted); $newSortPos++)
			$dbCmd->UpdateQuery("superpdfprofiles", array("Sort"=>$newSortPos+1), "ID=" . $arrayShifted[$newSortPos]);
	}
	
	// Will move the sorting sequence up or down relative to where it was (compared to other Sub PDF profiles).
	static function moveSubProfilePosition($superPDFprofileID, $subProfileID, $moveForward = true){
		
		$superProfilObj = new SuperPDFprofile();
		$superProfilObj->loadSuperPDFProfileByID($superPDFprofileID);
		$allSubProfileIDs = $superProfilObj->getSubPDFProfileIDs();
		
		if(!in_array($subProfileID, $allSubProfileIDs))
			throw new Exception("Error in method moveSubProfilePosition. The ID does not exist.");
			
		$arrayShifted = WebUtil::arrayMoveElement($allSubProfileIDs, $subProfileID, $moveForward);
		
		$dbCmd = new DbCmd();
		for($newSortPos=0; $newSortPos<sizeof($arrayShifted); $newSortPos++)
			$dbCmd->UpdateQuery("superpdfprofilessub", array("Sort"=>$newSortPos+1), ("PDFProfileID=" . $arrayShifted[$newSortPos] . " AND SuperPDFProfileID=" .$superPDFprofileID) );
	}
	

	static function removeSubPDFprofile($superPDFprofileID, $subPdfProfileID){
		
		$domainIDofSuperProfile = self::getDomainIDofSuperProfile($superPDFprofileID);
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofSuperProfile))
			throw new Exception("Error remvoing Sub PDF Profile Domain.");
		
		$dbCmd = new DbCmd();
		$dbCmd->Query("DELETE FROM superpdfprofilessub 
						WHERE SuperPDFProfileID=" . intval($superPDFprofileID) . " AND 
						PDFProfileID=" . intval($subPdfProfileID));
	}
	
	static function removeSuperPDFprofile($superPDFprofileID){
		
		$domainIDofSuperProfile = self::getDomainIDofSuperProfile($superPDFprofileID);
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofSuperProfile))
			throw new Exception("Error remvoing SuperPDF PDF Profile Domain.");
		
		$dbCmd = new DbCmd();
		$dbCmd->Query("DELETE FROM superpdfprofiles WHERE ID=" . intval($superPDFprofileID));
		$dbCmd->Query ( "DELETE FROM superpdfprintingpressids WHERE SuperPDFProfileID=" . intval($superPDFprofileID) );
		$dbCmd->Query ( "DELETE FROM superpdfprofilessub WHERE SuperPDFProfileID=" . intval($superPDFprofileID) );
	}
	
	// returns an array of Super Profiles currently using this SubProfileID inside. 
	// returns an empty array if none exists.
	static function getSuperProfileIDsUsingThisSubPDFprofile($subPDFprofileID){
		
		$dbCmd = new DbCmd();
		
		if(!PDFprofile::checkIfPDFprofileIDexists($dbCmd, $subPDFprofileID))
			throw new Exception("Error in method getSuperProfileIDsUsingThisSubPDFprofile. The Sub Profile ID does not exist.");
			
		$productIDofSubProfile = PDFprofile::getProductIDfromProfileID($dbCmd, $subPDFprofileID);
		
		$domainIDofSubProfile = Product::getDomainIDfromProductID($dbCmd, $productIDofSubProfile);
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofSubProfile))
			throw new Exception("Error getting Sub Profile IDs under Super Profile with Domain.");
		
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT DISTINCT SuperPDFProfileID FROM superpdfprofilessub 
						INNER JOIN superpdfprofiles ON superpdfprofiles.ID = superpdfprofilessub.SuperPDFProfileID 
						WHERE PDFProfileID=" . intval($subPDFprofileID));
		
		$retArr = array();
		while($x = $dbCmd->GetValue())
			$retArr[] = $x;
		
		return $retArr;
	}
	
	// setter methods //
	
	function setSuperPDFProfileName($profileName) {
		
		if(empty($this->_superProfileID))
			throw new Exception("Error in method setSuperPDFProfileName. The Super Profile has not been loaded yet.");
			
		if(!preg_match("/^\w{3,60}$/", $profileName))
			throw new Exception("Error setting Profie Name.  The Super PDF Profile Name must be between 3 and 60 characters and it may not contain any spaces or special characters.");
		
		// Dont allow Names that match an existing profile (from a different Profile ID. Not case sensitive check. 
		$allProfileNamesUpperCaseArr = SuperPDFprofile::getAllProfileNames($this->_dbCmd, Domain::oneDomain());
		foreach($allProfileNamesUpperCaseArr as $existingProfileID => $existingProfileName){
			if($existingProfileID != $this->_superProfileID && strtoupper($profileName) == strtoupper($existingProfileName)){
				throw new Exception("Error in method setSuperPDFProfileName. The Super Profile name has already been taken.");
			}
		}

		$this->_superProfileName = $profileName;
	}
	
	function setSheetWidth($x) {
		$x = floatval($x);
		if($x<=0.001)
			throw new Exception("$x is not a valid parameter for setSheetWidth");
		$this->_sheetWidth = $x;	
	}
	
	function setSheetHeight($x) {
		$x = floatval($x);
		if($x<=0.001)
			throw new Exception("$x is not a valid parameter for setSheetHeight");
		$this->_sheetHeight = $x;	
	}
	
	function setBarcodeX($x) {
		$x = floatval($x);
		$this->_barcodeX = $x;	
	}
	
	function setBarcodeY($x) {
		$x = floatval($x);
		$this->_barcodeY = $x;	
	}	
	
	function setBarcodeRotateDeg($x) {
		$x=intval($x);
		if(!in_array($x, array("0", "90", "180", "270")))
			throw new Exception("$x is not a valid parameter for setBarcodeRotateDeg");
		$this->_barcodeRotateDeg = $x;	
	}
	
	// Takes an array of printing press ids
	function setPrintingPressIDs(array $printingPressIDs) {
		
		foreach($printingPressIDs as $thisPressID){
			if(!PrintingPress::checkIfPrintingPressIDExists($thisPressID))
				throw new Exception("Error in method setPrintingPressIDs, the printing press does not exist.");
		}
		$this->_printingPressIDs = $printingPressIDs;	
	}	
	
	function setSuperPDFProfileNotes($x) {
		if(strlen($x) > 200)
			throw new Exception("Error in Method SuperPDFprofiles->getSuperPDFProfileNotes, can not exceed 200 chars.");
		$this->_superProfileNotes = $x;
	}
	
	function setXcoordOfSubPDFprofile($pdfProfileID, $xCoordinate){
		
		foreach($this->_subProfilesArr as $subKey => $thisSubProfHash){
			
			if($thisSubProfHash["PDFProfileID"] == $pdfProfileID){
				$this->_subProfilesArr[$subKey]["Xcoord"] = $xCoordinate;
				return;
			}
		}
		throw new Exception("Error in method setXcoordOfSubPDFprofile. The Sub PDF Profile ID was not found.");

	}
	function setYcoordOfSubPDFprofile($pdfProfileID, $yCoordinate){
		
		foreach($this->_subProfilesArr as $subKey => $thisSubProfHash){
			
			if($thisSubProfHash["PDFProfileID"] == $pdfProfileID){
				$this->_subProfilesArr[$subKey]["Ycoord"] = $yCoordinate;
				return;
			}
		}

		throw new Exception("Error in method setYcoordOfSubPDFprofile. The Sub PDF Profile ID was not found.");
	}
	
	function setOptionsLimiterOfSubPDFprofile($pdfProfileID, $optionsLimiter){
		
		foreach($this->_subProfilesArr as $subKey => $thisSubProfHash){
			
			if($thisSubProfHash["PDFProfileID"] == $pdfProfileID){
				$this->_subProfilesArr[$subKey]["OptionsLimiter"] = $optionsLimiter;
				return;
			}
		}

		throw new Exception("Error in method setOptionsLimiterOfSubPDFprofile. The Sub PDF Profile ID was not found.");
	}
	
	// getter methods  //
	
	function getSheetWidth(){
		return $this->_sheetWidth;
	}

	function getSheetHeight(){
		return $this->_sheetHeight;
	}

	function getBarCodeX(){
		return $this->_barcodeX;
	}

	function getBarCodeY(){
		return $this->_barcodeY;
	}

	function getBarCodeRotateDeg(){
		return $this->_barcodeRotateDeg;
	}


	function getSubPDFProfileIDs() {
		
		if(empty($this->_superProfileID))
			throw new Exception("Error in method getSubPDFProfileIDs. The Super Profile has not been loaded yet.");
	
		$retArr = array();

		foreach($this->_subProfilesArr as $subProfHash)
			$retArr[] = $subProfHash["PDFProfileID"];
		
		return $retArr;
	}
	
	// Returns an array of Printing Press IDs which are authorized to print with this profile.
	function getPrintingPressIDs(){	
		return $this->_printingPressIDs;
	}
	
	function getSuperPDFProfileID() {
		return $this->_superProfileID;
	}
	
	function getSuperPDFProfileNotes() {
		return $this->_superProfileNotes;
	}
	
	function getSuperPDFProfileName() {
		return $this->_superProfileName;
	}
	
	
	
	

	// Return a list of all SuperProfile Names
	// The order is important because it defines the priority of which GangRuns will try to fill up before moving on.
	// The key to the array is the ID of the SuperPDFprofile in the DB.
	// Pass in a single Domain ID or an array of Domain IDs to restrict the list to that.
	static function getAllProfileNames(DbCmd $dbCmd, $domainIDarr) {

		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		if(!is_array($domainIDarr))
			$domainIDarr = array($domainIDarr);
			
		foreach($domainIDarr as $thisDomainID){	
			if(!$passiveAuthObj->CheckIfUserCanViewDomainID($thisDomainID))
				throw new Exception("Error in getAllProfileNames with Domain Permissions.");
		}
		
	
		$allProfileNamesArr = array();	

		$dbCmd->Query("SELECT ID, SuperPDFProfileName FROM superpdfprofiles WHERE ". DbHelper::getOrClauseFromArray("DomainID", $domainIDarr)." ORDER BY Sort ASC");			
		while ($row = $dbCmd->GetRow()) 
			$allProfileNamesArr[$row["ID"]] = $row["SuperPDFProfileName"];	

		return $allProfileNamesArr;
	}
	
	// Gets the Name of the SuperPDFprofile by its ID.  Will fail if the ID does not exist.
	static function getSuperProfileNameByID($superProfileID) {

		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT SuperPDFProfileName FROM superpdfprofiles WHERE ". DbHelper::getOrClauseFromArray("DomainID", $passiveAuthObj->getUserDomainsIDs())." AND ID=" . intval($superProfileID));			
		$profileName = $dbCmd->GetValue();
		
		if(empty($profileName))
			throw new Exception("Error in method SuperProfile->getSuperProfileNameByID. The ProfileID does not exist.");

		return $profileName;
	}
	
	// Gets the ID of the SuperPDFprofile by its Name.  Will fail if the Name does not exist.
	static function getSuperProfileIDbyName($superProfileName, $domainID) {

		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		$domainID = intval($domainID);
		
		if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainID))
			throw new Exception("Error in method getSuperProfileIDbyName. The domain is incorrect.");
		
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT ID FROM superpdfprofiles WHERE DomainID=$domainID AND SuperPDFProfileName=\"" . DbCmd::EscapeSQL($superProfileName) . "\"");			
		$profileID = $dbCmd->GetValue();
		
		if(empty($profileID))
			throw new Exception("Error in method SuperProfile->getSuperProfileIDbyName. The Profile Name does not exist.");

		return $profileID;
	}
	
	// Return a list of all SuperProfile Names that are linked to the an array of Printing Press ID's that you pass in
	// The key to the array is the SuperProfileID
	static function getProfilesLinkedToPrintingPresses(DbCmd $dbCmd, array $printingPressIDsArr, array $domainIDs) {

		foreach($printingPressIDsArr as $thisPressID){
			if(!PrintingPress::checkIfPrintingPressIDExists($thisPressID))
				throw new Exception("Error in SuperPDFprofile->getProfilesLinkedToPrintingPresses. One of the printing presses does not exist.");
		}
		
		// Authenticate Domains passed into this method.
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		$allUserDomains = $passiveAuthObj->getUserDomainsIDs();
		
		foreach($domainIDs as $thisDomain){
			if(!in_array($thisDomain, $allUserDomains))
				throw new Exception("Error in method getProfilesLinkedToPrintingPresses with domains.");
		}
		
		$dbCmd->Query("SELECT superpdfprofiles.ID as SuperProfileID, SuperPDFProfileName FROM superpdfprofiles 
						INNER JOIN superpdfprintingpressids ON superpdfprintingpressids.SuperPDFProfileID = superpdfprofiles.ID
						WHERE ". DbHelper::getOrClauseFromArray("DomainID", $domainIDs)." AND " . implode($printingPressIDsArr,  " OR ") . " 
						ORDER BY superpdfprofiles.Sort ASC");			
		
		$profileNamesArr = array();	
		while ($row = $dbCmd->GetRow()) 
			$profileNamesArr[$row["SuperProfileID"]] = $row["SuperPDFProfileName"];

		return $profileNamesArr;
	}

	
	// Returns an array... the Key is the PrintingPress ID... the value is the Printer Name.
	function getPrintingPressNames(){
	
		$retArr = array();
		
		foreach($this->_printingPressIDs as $thisPressID){
			
			// In case some printers were deleted since this Object was last saved to the DB... we will just filter them out silently.
			if(PrintingPress::checkIfPrintingPressIDExists($thisPressID))
				$retArr[$thisPressID] = PrintingPress::getPrintingPressNameQuick($thisPressID);
		}
	
		return $retArr;
	}

	// Returns the total amount of picas area, based upon Parent Sheet Dimensions.
	function getParentSheetPicasArea(){

		return $this->_sheetWidth * 72 * $this->_sheetHeight * 72;
	}



	
	
	// Returns the total number of slots on this SuperPDFprofile for the given Product ID.
	// If will take into account that there may be more than 1 sub PDFprofile with a matching ProductID.
	function getTotalSpacesForProduct($productID){
	
		$retValue = 0;
		
		foreach($this->_subProfilesArr as $thisSubProfileArr){
			
			$pdfProfileObj = $this->getSubPDFprofileObj($thisSubProfileArr["PDFProfileID"]);
			
			if($productID == $pdfProfileObj->getProductID())
				$retValue += $pdfProfileObj->getQuantity();
		}
		
		return $retValue;
	}
	
	// Returns the total number of slots on this SuperPDFprofile.
	// If there are 4 spaces for Postcards and 10 spaces for Business Cards, then this method will return 14.
	function getTotalSpacesForAllProducts(){
	
		$retValue = 0;
		
		foreach($this->_subProfilesArr as $thisSubProfileArr){
			$pdfProfileObj = $this->getSubPDFprofileObj($thisSubProfileArr["PDFProfileID"]);
			$retValue += $pdfProfileObj->getQuantity();
		}
		
		return $retValue;
	}
	
	
	// Returns an array of all Product IDs which are mapped to this Super PDF Profile.
	// Since there can be more than 1 sub PDF profile mapped to a Super PDF Profile, this will only return a unique list of Product IDs.
	// The Product IDs are always mapped to "Product Product IDs".
	function getProductIDsInSuperProfile(){
		
		if(empty($this->_superProfileID))
			throw new Exception("Error in method getProductIDsInSuperProfile. The Super Profile has not been loaded yet.");
	
		$retArr = array();
	
		foreach($this->_subProfilesArr as $thisSubProfileArr){
			$prodID = PDFprofile::getProductIDfromProfileID($this->_dbCmd, $thisSubProfileArr["PDFProfileID"]);
			
			if(empty($prodID))
				throw new Exception("Error in method getProductIDsInSuperProfile. The PDFprofile ID does not exist.");
				
			$retArr[] = $prodID;
		}
				
		return array_unique($retArr);
	}
	
	// The second parameter is boolean.
	// On the backside the xCoordinate has to be transposed so that it will line up with the front side.
	function getXcoordOfSubProfile($pdfProfileID, $frontSideFlag = true){
	
		foreach($this->_subProfilesArr as $thisSubProfileArr){
		
			if($thisSubProfileArr["PDFProfileID"] == $pdfProfileID){
				
				if($frontSideFlag){
					return $thisSubProfileArr["Xcoord"];
				}
				else{
					$pdfSubProfileObj = $this->getSubPDFprofileObj($pdfProfileID);
					
					return ($this->_sheetWidth - $pdfSubProfileObj->getPagewidth() - $thisSubProfileArr["Xcoord"]);
				}
			}
		}
		
		throw new Exception("Error in method getXcoordOfSubProfile.... the given PDFProfileID was not found: " . $pdfProfileID);		
	}
	
	function getYcoordOfSubProfile($pdfProfileID){
	
		foreach($this->_subProfilesArr as $thisSubProfileArr){
		
			if($thisSubProfileArr["PDFProfileID"] == $pdfProfileID)
				return $thisSubProfileArr["Ycoord"];
		}
		
		throw new Exception("Error in method getYcoordOfSubProfile.... the given PDFProfileID was not found: " . $pdfProfileID);
			
	}
	
	function getOptionsLimiterOfSubPDFprofile($pdfProfileID){
	
		foreach($this->_subProfilesArr as $thisSubProfileArr){
		
			if($thisSubProfileArr["PDFProfileID"] == $pdfProfileID)
				return $thisSubProfileArr["OptionsLimiter"];
		}
		
		throw new Exception("Error in method getOptionsLimiterOfSubPDFprofile.... the given PDFProfileID was not found: " . $pdfProfileID);
			
	}

	
	
	// Creates the PDF_Profile object and returns it (based upon the given PDF PRofile ID ID).
	// Caches the Object creation of the PDF Sub Profile... so you can call this many times without worrying about performance.
	function getSubPDFprofileObj($pdfProfileID){
	
		foreach($this->_subProfilesArr as $thisSubProfileArr){
		
			if($thisSubProfileArr["PDFProfileID"] == $pdfProfileID){
			
				if(array_key_exists($pdfProfileID, $this->_cachedPDFprofileObjArr))
					return $this->_cachedPDFprofileObjArr[$pdfProfileID];

				// We haven't created a PDF profile Object yet... so create one and cache it.
				$prodID = PDFprofile::getProductIDfromProfileID($this->_dbCmd, $pdfProfileID);
				$productionProductID = Product::getProductionProductIDStatic($this->_dbCmd, $prodID);
				
				$pdfProfileObj = new PDFprofile($this->_dbCmd, $productionProductID);
				
				$pdfProfileObj->loadProfileByID($pdfProfileID);
				$this->_cachedPDFprofileObjArr[$pdfProfileID] = $pdfProfileObj;
				
				return $pdfProfileObj;
			}
		}
		
		throw new Exception("Error in method getSubPDFprofile.... the given PDFProfileID was not found: " . $pdfProfileID);	
	}
	
	
	// Pass in a Name for the Super Profile that you want after the Object is copied.
	// Returns the new Super Profile ID.
	function copySuperProfile($newSuperProfileName){
		
		if(empty($this->_superProfileID))
			throw new Exception("Error in method copySuperProfile. The Super Profile has not been loaded yet.");		
		
		// Tweak some of the Private Members ... run an insert.  
		// Then we can "reload" our current Super Profile ID fresh from the DB when we are done.
		$currentSuperProfileID = $this->_superProfileID;
		
		$this->_superProfileID = null;
		
		$newSuperProfileID = $this->addSuperPDFProfile($newSuperProfileName);
		
		// The Update is needed as well.  That is because normally when we add a Super PDF Profile, it does not expect there to be any Sub Profiles yet. 
		$this->_superProfileID = $newSuperProfileID;
		$this->_superProfileName = $newSuperProfileName;
		$this->updateSuperPDFProfile();
		
		// Make the Copy stay at the same Sort Level
		$this->_dbCmd->Query("SELECT Sort FROM superpdfprofiles WHERE ID =" . $currentSuperProfileID);
		$originalSortValue = $this->_dbCmd->GetValue();
		$this->_dbCmd->UpdateQuery("superpdfprofiles", array("Sort"=>$originalSortValue), "ID=$newSuperProfileID");
		
		
		// Now load this object back to its original state.
		$this->loadSuperPDFProfileByID($currentSuperProfileID);
		
		// Return the Profile ID that we just created by doing a copy.
		return $newSuperProfileID;
		
	}
	
	



	
	function loadSuperPDFProfileByID($superProfileIDtoLoad) {
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
	
		$this->_dbCmd->Query("SELECT * FROM superpdfprofiles WHERE ID=" . intval($superProfileIDtoLoad) . " AND ". DbHelper::getOrClauseFromArray("DomainID", $passiveAuthObj->getUserDomainsIDs()));
		if($this->_dbCmd->GetNumRows() == 0 )
			throw new Exception("Error in method loadSuperPDFProfileByID, record does not exist.");
		
		$row = $this->_dbCmd->GetRow();

		$this->_superProfileID 	 	= $row['ID'];
		$this->_superProfileName 	= $row['SuperPDFProfileName'];
		$this->_sheetWidth 	 		= $row['SheetWidth'];
		$this->_sheetHeight 	 	= $row['SheetHeight'];
		$this->_barcodeX 	 		= $row['BarcodeX'];
		$this->_barcodeY 	 		= $row['BarcodeY'];
		$this->_barcodeRotateDeg 	= $row['BarcodeRotateDeg'];
		$this->_superProfileNotes	= $row['Notes'];
		$this->_domainID 			= $row['DomainID'];
		
		
		$tempPrintingPressArr = array();
		$this->_dbCmd->Query("SELECT PrintingPressID FROM superpdfprintingpressids WHERE SuperPDFProfileID = " . $this->_superProfileID);			
		$tempPrintingPressArr = $this->_dbCmd->GetValueArr();
			
		
		// Make sure that the Printing Presses are still defined for the Domain of the Super PDF profile.
		$this->_printingPressIDs = array();
		$domainEquipmentObj = new DomainEquipment($this->_domainID);
		
		foreach($tempPrintingPressArr as $thisPrintingPressID){
			if(in_array($thisPrintingPressID, $domainEquipmentObj->printingPressesArr))
				$this->_printingPressIDs[] = $thisPrintingPressID;
		}


		// Get all of the Sub PDF profiles within the Super Profile
		$this->_subProfilesArr = array();
		$this->_dbCmd->Query("SELECT * FROM superpdfprofilessub WHERE SuperPDFProfileID = " . $this->_superProfileID . " ORDER BY Sort ASC");			
		while ($row = $this->_dbCmd->GetRow()) {

			$subProfileHash["PDFProfileID"] = $row['PDFProfileID'];			
			$subProfileHash["Xcoord"]       = $row['Xcoord'];
			$subProfileHash["Ycoord"]       = $row['Ycoord'];
			$subProfileHash["OptionsLimiter"]       = $row['OptionsLimiter'];

			$this->_subProfilesArr[] = $subProfileHash;
	
		}	

	}
	
	
	
	function _insertPrintingPressIDs() {

		foreach ($this->_printingPressIDs as $printingPressID) {

			$printingPressArr["SuperPDFProfileID"] = $this->_superProfileID;
			$printingPressArr["PrintingPressID"] = $printingPressID;
		
			$this->_dbCmd->InsertQuery("superpdfprintingpressids", $printingPressArr);
		}	
	}

	function _fillDBFields() {

		if(empty($this->_sheetWidth))
			throw new Exception("SheetWidth not set");
		if(empty($this->_sheetHeight))
			throw new Exception("SheetHeight not set");
			
		$this->_superPDFProfilesDatabase = array();
		$this->_superPDFProfilesDatabase['SheetWidth'] = $this->_sheetWidth;
		$this->_superPDFProfilesDatabase['SheetHeight'] = $this->_sheetHeight;
		$this->_superPDFProfilesDatabase['BarcodeX'] = $this->_barcodeX;
		$this->_superPDFProfilesDatabase['BarcodeY'] = $this->_barcodeY;
		$this->_superPDFProfilesDatabase['BarcodeRotateDeg'] = $this->_barcodeRotateDeg;
		$this->_superPDFProfilesDatabase['Notes'] = $this->_superProfileNotes;

	}
	
	function addSuperPDFProfile($profileName) {

		// Dont allow Names that match an existing. Not case sensitive check. 
		$allProfileNamesUpperCaseArr = SuperPDFprofile::getAllProfileNames($this->_dbCmd, Domain::oneDomain());
		foreach($allProfileNamesUpperCaseArr as $k => $v)
			$allProfileNamesUpperCaseArr[$k] = strtoupper($v);
		
		if(in_array(strtoupper($profileName), $allProfileNamesUpperCaseArr))
			throw new Exception("The SuperProfile name $profileName already exist within the list of profile names in class SuperPDFprofile");	

		if(!preg_match("/^\w{3,60}$/", $profileName))
			throw new Exception("Error Creating a new Profile.  The Super PDF Profile Name must be between 3 and 60 characters and it may not contain any spaces or special characters.");
		
		$this->_fillDBFields();
		$this->_superPDFProfilesDatabase['SuperPDFProfileName'] = $profileName;
		
		
		$newDbEntry = $this->_superPDFProfilesDatabase;
		
		// For Sorting, make the new Super Profile start off in last place.
		$newDbEntry["Sort"] = sizeof($allProfileNamesUpperCaseArr) + 1;
		$newDbEntry["DomainID"] = Domain::oneDomain();
		
		$this->_superProfileID = $this->_dbCmd->InsertQuery ( "superpdfprofiles", $newDbEntry );

		$this->_insertPrintingPressIDs();
		
		return $this->_superProfileID;
	}	

	function updateSuperPDFProfile() {

		$this->_fillDBFields();

		if(empty($this->_superProfileID))
			throw new Exception("You can't update a PDF profile because it hasn't been loaded yet.");
		
		$this->_superPDFProfilesDatabase['SuperPDFProfileName'] = $this->_superProfileName;
			
		$this->_dbCmd->UpdateQuery ( "superpdfprofiles", $this->_superPDFProfilesDatabase, "ID = $this->_superProfileID" );

		$this->_dbCmd->Query ( "DELETE FROM superpdfprintingpressids WHERE SuperPDFProfileID = $this->_superProfileID" );
		$this->_insertPrintingPressIDs();
		
		$this->_dbCmd->Query ( "DELETE FROM superpdfprofilessub WHERE SuperPDFProfileID = $this->_superProfileID" );
		
		$sortCounter = 1;
		foreach($this->_subProfilesArr as $singleSub){
			$singleSub["SuperPDFProfileID"] = $this->_superProfileID;
			$singleSub["Sort"] = $sortCounter;
			$this->_dbCmd->InsertQuery("superpdfprofilessub", $singleSub);
			$sortCounter++;
		}

	}

	// Will add a new sub PDF profile into the Super PDF profile.
	// It will default the Coordinates to 0 and give the lowest priority.  The user can edit those values after the profile has been added.
	function addSubPDFProfile($pdfProfileID) {
		
		$pdfProfileID = intval($pdfProfileID);
		
		if(in_array($pdfProfileID, $this->getSubPDFProfileIDs()))
			throw new Exception("Error in Method addSubPDFprofile. The PDF profile ID already exists: " . $pdfProfileID);
		
		if(!PDFprofile::checkIfPDFprofileIDexists($this->_dbCmd, $pdfProfileID))
			throw new Exception("Error in method addSubPDFProfile. The PDF profile ID does not exist.");
			
		$productID = PDFprofile::getProductIDfromProfileID($this->_dbCmd, $pdfProfileID);
		
		$domainIDofProduct = Product::getDomainIDfromProductID($this->_dbCmd, $productID);
		
		if($domainIDofProduct != $this->_domainID)
			throw new Exception("Error in method addSubPDFProfile. Trying to add a Sub Profile from another domain.");

		$newSubProfileHash = array("PDFProfileID"=>$pdfProfileID, "Xcoord"=>0, "Ycoord"=>0, "OptionsLimiter"=>"");
		$this->_subProfilesArr[] = $newSubProfileHash;
		
	}
}



?>

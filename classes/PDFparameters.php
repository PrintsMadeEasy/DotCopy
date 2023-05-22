<?


// This class will contain all parameters required to generate a PDF doc.
// Getter methods will provide access into the PDFprofile object
// This class provides getting/setting of other parameters that may not be in the PDFprofile object.

class PDFparameters {


	private $_orderno;
	private $_mailingServicesFlag;
	private $_pdf_sidenumber;
	private $_showCutLine;
	private $_rotatecanvas;
	private $_bleedtype;
	private $_showBleedSafe;
	private $_sidedescription;
	private $_pdfProfileObj;


	##--------------   Constructor  ------------------##
	
	// Pass in a PDF profile object.  It must already be intialized with the Profile Name
	function PDFparameters($profileObj){
	
		if(!$profileObj->checkIfPDFprofileLoaded())
			throw new Exception("A PDF profile must be loaded before calling the contructor for PDFparameters");
		
		$this->_pdfProfileObj = $profileObj;

	}

	##----------------- Setter Methods ----------------------##

	function setOrderno($x) {
		$this->_orderno = $x;
	}
	
	// _pdf_sidenumber is 0 based;
	function setPdfSideNumber($x) {
		if(!preg_match("/^\d+$/", $x))
			throw new Exception("setPdfSidenumber must be numeric");
		$this->_pdf_sidenumber = $x;
	}
	function setShowCutLine($x) {
		if(!is_bool($x))
			throw new Exception("setShowCutLine must be a bool");
		$this->_showCutLine = $x;
	}
	function setShowBleedSafe($x) {
		if(!is_bool($x))
			throw new Exception("setShowBleedSafe must be a bool");
		$this->_showBleedSafe = $x;
	}
	function setRotatecanvas($x) {
		if(!preg_match("/^\d+$/", $x))
			throw new Exception("setRotatecanvas must be numeric");
		$this->_rotatecanvas = $x;
	}
	function setBleedtype($x) {
		//'N'atural, 'S'tretch, 'T'ile, 'V'oid (or nothing)
		if($x <> "N" && $x <> "S" && $x <> "T" && $x <> "V")
			throw new Exception("illegal bleed type in class PDFparameters:" . $x);
		$this->_bleedtype = $x;
	}
	function setSideDescription($x) {
		$this->_sidedescription = $x;
	}

	function setMailingServices($x) {
		if(!is_bool($x))
			throw new Exception("Error in method setMailingServices.");
		$this->_mailingServicesFlag = $x;
	}
	function changeProfileName($x) {
		$this->_pdfProfileObj->loadProfileByName($x);
	}



	##-----------------  Getter  Methods  -------------------##

	// The following are unique to the PDFparameters class 
	function getOrderno() {
		return $this->_orderno;
	}
	function getPdfSideNumber() {
		if($this->_pdf_sidenumber === null)
			throw new Exception("Error in PDFparameters.getPdfSideNumber The side number is being requested but it hasn't been set.");
		return $this->_pdf_sidenumber;
	}
	function getShowCutLine() {
		return $this->_showCutLine;
	}
	function getShowBleedSafe() {
		return $this->_showBleedSafe;
	}
	
	// If the rotate number comes in as 180 (for flipping the backside)... then we will just be adding it to the default rotate parameter of the Profile
	// It is a combination of a Manual (or a specific rotation) with the rotation of the profile
	// Pass in a boolean True if you want it to calculate additional rotation based upon if it is back or front

	// ... for example... if the canvas is rotated by 90 degrees... the backside will have to be rotated by 270
	// ... if the canvas has a rotation at 0, then the back can stay at 0 (unless of course it is a vertical card and there is a manual rotation already)
	// _pdf_sidenumber is 0 Based
	function getRotatecanvas($checkSideNumberForAdditionalRotation = false) {
		
		$retVal = $this->_rotatecanvas + $this->_pdfProfileObj->getRotatecanvas();
		
		if($checkSideNumberForAdditionalRotation){
			if(($this->_pdf_sidenumber % 2) != 0){
				if($this->_pdfProfileObj->getRotatecanvas() == 90 || $this->_pdfProfileObj->getRotatecanvas() == 270)
					$retVal += 180;
			}
		}
		
		if($retVal >= 360)
			$retVal -= 360;
		
		return $retVal;
	}
	function getBleedtype() {
		return $this->_bleedtype;
	}
	function getSideDescription() {
		return $this->_sidedescription;
	}



	// the following methods just pass through to the PDFprofile class
	function getProfileName() {
		return $this->_pdfProfileObj->getProfileName();
	}
	function getQuantity() {
		return $this->_pdfProfileObj->getQuantity();
	}
	function getRows() {
		return $this->_pdfProfileObj->getRows();
	}
	function getColumns() {
		return $this->_pdfProfileObj->getColumns();
	}
	function getBleedUnits() {
		return $this->_pdfProfileObj->getBleedUnits();
	}
	function getForceAspectRatio() {
		return $this->_pdfProfileObj->getForceAspectRatio();
	}
	function getShapeContainerObjGlobal() {
		return $this->_pdfProfileObj->getGlobalShapeContainer();
	}
	function getShapeContainerObjLocal() {
		return $this->_pdfProfileObj->getLocalShapeContainer();
	}
	function getGapr() {
		return $this->_pdfProfileObj->getGapr();
	}
	function getGapc() {
		return $this->_pdfProfileObj->getGapc();
	}
	function getGapsizeH() {
		return $this->_pdfProfileObj->getGapsizeH();
	}
	function getGapsizeV() {
		return $this->_pdfProfileObj->getGapsizeV();
	}
	function getPagewidth() {
		return $this->_pdfProfileObj->getPagewidth();
	}
	function getPageheight() {
		return $this->_pdfProfileObj->getPageheight();
	}
	function getHspacing() {
		return $this->_pdfProfileObj->getHspacing();
	}
	function getVspacing() {
		return $this->_pdfProfileObj->getVspacing();
	}
	function getLmargin() {
		return $this->_pdfProfileObj->getLmargin();
	}
	function getBmargin() {
		return $this->_pdfProfileObj->getBmargin();
	}
	function getUnitWidth() {
		return $this->_pdfProfileObj->getUnitWidth();
	}
	function getUnitHeight() {
		return $this->_pdfProfileObj->getUnitHeight();
	}
	function getLabelx() {
		return $this->_pdfProfileObj->getLabelx();
	}
	function getLabely() {
		return $this->_pdfProfileObj->getLabely();
	}
	function getLabelrotate() {
		return $this->_pdfProfileObj->getLabelrotate();
	}
	function getDisplayCoverSheet() {
		return $this->_pdfProfileObj->getDisplayCoverSheet();
	}
	function getDisplaySummarySheet() {
		return $this->_pdfProfileObj->getDisplaySummarySheet();
	}
	function getLocalShapeContainer(){
		return $this->_pdfProfileObj->getLocalShapeContainer();
	}
	function getGlobalShapeContainer(){
		return $this->_pdfProfileObj->getGlobalShapeContainer();
	}
	
	// Some profiles never ever want crop marks or an outline... even though we generally would show them in a particular situation (on most profiles)  
	function getShowOutsideBorder(){
		return $this->_pdfProfileObj->getShowOutsideBorder();
	}
	function getShowCropMarks(){
		return $this->_pdfProfileObj->getShowCropMarks();
	}
	function getCoverSheetMatrix(){
		return $this->_pdfProfileObj->getCoverSheetMatrix();
	}
	function getExtraQuantityPercentage(){
		return $this->_pdfProfileObj->getExtraQuantityPercentage();
	}
	function getExtraQuantityMaximum(){
		return $this->_pdfProfileObj->getExtraQuantityMaximum();
	}
	function getExtraQuantityMinimumStart(){
		return $this->_pdfProfileObj->getExtraQuantityMinimumStart();
	}


	function getUnitCanvasAreaPicas(){
		return $this->_pdfProfileObj->getUnitCanvasAreaPicas();
	}
	
	
	// This Flag is needed... because PDF generation will show different markings on the Coversheet (and omit re-order cards)
	// This value should be set when this Object is created, by analyzing the Product
	function getMailingServices(){
		return $this->_mailingServicesFlag;
	}


	// Static Method for erasing any PDF generated settings on a Project which as been ordered.
	static function ClearPDFsettingsForProjectOrdered(DbCmd $dbCmd, $projectOrderedID){
		
		$dbCmd->Query("DELETE FROM pdfgenerated WHERE ProjectRef=" . intval($projectOrderedID));
	
	}


	// Static Method for saving PDF parameters on a Project that has been ordered
	static function SavePDFsettingsForProjectOrdered(DbCmd $dbCmd, $projectOrderedID, $bleedparams){

		// We received bleedparams through the parameters... It should have a bleed setting for every side of the artwork separated by PIPE symbols... EX: stretch-0|tile-180|none-0|
		// There may be 1 or more.  Every entry represents an a side... EX: front an back
		// The number after the dash means if we are going to rotate by 180 degrees or not... may just be 0 or 180
		$BleedParamsArr = array();
		$TempArr = split("\|", $bleedparams);

		foreach($TempArr as $BS){
			$matches = array();
			if(preg_match_all("/^(\w)-(\d+)$/", $BS, $matches)){
				$BleedType = $matches[1][0];
				$RotateType = $matches[2][0];
				array_push($BleedParamsArr, array("bleedtype"=>$BleedType, "rotatetype"=>$RotateType));
			}
		}

		if(empty($BleedParamsArr))
			throw new Exception("No bleed settings found in function call SavePDFsettings");

		$projectObj = ProjectOrdered::getObjectByProjectID($dbCmd, $projectOrderedID);
		$ArtworkInfoObj = new ArtworkInformation($projectObj->getArtworkFile());

		// Delete any previous settings for this Side and Project number... we may be doing an update --#
		$dbCmd->Query("DELETE FROM pdfgenerated WHERE ProjectRef=$projectOrderedID");


		// Loop through Each Side (specified by the PDF bleed parameters);
		$SideCounter = 0;
		foreach($BleedParamsArr as $CurrentSettingHash){

			$ThisBleedSetting = $CurrentSettingHash["bleedtype"];
			$RotateValue = $CurrentSettingHash["rotatetype"];

			if(!isset($ArtworkInfoObj->SideItemsArray[$SideCounter]))
				throw new Exception("There was an error creating a PDF setting object on side: " . $SideCounter);

			$insertArr["ProjectRef"] = $projectOrderedID;
			$insertArr["SideNumber"] = $SideCounter;
			$insertArr["SideName"] = $ArtworkInfoObj->SideItemsArray[$SideCounter]->description;
			$insertArr["BleedType"] = $ThisBleedSetting;
			$insertArr["Rotate"] = $RotateValue;

			$dbCmd->InsertQuery("pdfgenerated",  $insertArr);

			$SideCounter++;
		}
	}


	// Static Method
	// Will return a list of the PDFparameters Objects.  Maybe 1 or 2 usually.  Front Side, Back Side
	// The Profile is optional.  If you omit it, it will use the default profile
	static function GetPDFparametersForProjectOrderedArr(DbCmd $dbCmd, $projectrecord, $profileName = ""){

		// Make sure that we always have PDF parameters stored for this project... even if we have to reset the paramters to the default of "Natural";
		$bleedSettingsAlreadySavedFlag = ProjectOrdered::CheckIfBleedSettingsAreSaved($dbCmd, $projectrecord);
		if(!$bleedSettingsAlreadySavedFlag)
			ProjectOrdered::SetBleedSettingsToNaturalOnProject($dbCmd, $projectrecord);
		
		$PDFobjectsArr = array();

		$dbCmd->Query("SELECT ProductID, OrderID, OptionsAlias FROM projectsordered WHERE ID=$projectrecord");
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Project ID does not exist in function call GetPreGeneratedPDFobjectsArr");
		$row = $row=$dbCmd->GetRow();
		$productID = $row["ProductID"];
		$orderID = $row["OrderID"];
		$optionsAlias = $row["OptionsAlias"];
		

		// Proof Profiles alwasy come out of the Product Itself.. not the Production Product ID.
		if(strtoupper($profileName) != "PROOF")
			$productID = Product::getProductionProductIDStatic($dbCmd, $productID);
		
		$dbCmd2 = new DbCmd();
		
		// Based upon the Product ID that we are printing... we will give our PDF Parameters Object instructions whether this Print Job is going to be mailed.
		$productObj = new Product($dbCmd2, $productID, false);
		$mailingServicesFlag = $productObj->hasMailingService();

		$dbCmd->Query("SELECT * FROM pdfgenerated WHERE ProjectRef=$projectrecord ORDER BY SideNumber ASC");
		while($row=$dbCmd->GetRow()){
			
			

			$profileObj = new PDFprofile($dbCmd2, $productID, $optionsAlias);
			if(empty($profileName))
				$profileObj->loadProfileByName($profileObj->getDefaultProfileName());
			else
				$profileObj->loadProfileByName($profileName);

			//  Put the PDF profile into our PDF parameters object
			$parametersPDFobj = new PDFparameters($profileObj);

			$parametersPDFobj->setOrderno($orderID . " - " . "P" . $projectrecord);
			$parametersPDFobj->setPdfSideNumber($row["SideNumber"]);
			$parametersPDFobj->setBleedtype($row["BleedType"]);
			$parametersPDFobj->setSideDescription($row["SideName"]);
			$parametersPDFobj->setShowBleedSafe(false);
			$parametersPDFobj->setShowCutLine(false);
			$parametersPDFobj->setMailingServices($mailingServicesFlag);

			$parametersPDFobj->setRotatecanvas($row["Rotate"]);

			// Add the object to the Array --#
			array_push($PDFobjectsArr, $parametersPDFobj);
		}

		return $PDFobjectsArr;
	}
	
	// Copy the PDF settings from 1 project to another.
	// Returns TRUE if there were settings able to be copied over... false otherwise.
	static function CopyPDFsettingsToNewProject(DbCmd $dbCmd, DbCmd $dbCmd2, $FromProjectID, $ToProjectID){

		// First get rid of any PDF settings for the new Project
		$dbCmd->Query("DELETE FROM pdfgenerated WHERE ProjectRef=" . intval($ToProjectID));
	
		//Copy over the PDF settings from the old project into the new one
		$dbCmd->Query("SELECT * FROM pdfgenerated WHERE ProjectRef=" . intval($FromProjectID) ." ORDER BY SideNumber ASC");
		if($dbCmd->GetNumRows() == 0)
			return false;
		
	
		while($copyPDFrow = $dbCmd->GetRow()){
			unset($copyPDFrow["ID"]);
			unset($copyPDFrow["DateCreated"]);
			
			$copyPDFrow["ProjectRef"] = $ToProjectID;
			
			$dbCmd2->InsertQuery("pdfgenerated", $copyPDFrow);
		}
		
		return true;
	}
		
}




?>
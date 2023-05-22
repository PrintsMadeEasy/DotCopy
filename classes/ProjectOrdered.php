<?
class ProjectOrdered extends ProjectBase {

	private $_projectIDloadedFlag;

	// Anything after $_db_ should match the field in the Database
	
	protected $_db_OrderID;
	protected $_db_VendorID1;
	protected $_db_VendorID2;
	protected $_db_VendorID3;
	protected $_db_VendorID4;
	protected $_db_VendorID5;
	protected $_db_VendorID6;
	protected $_db_RePrintLink;
	protected $_db_RePrintReason;
	protected $_db_ArtworkFile; 
	protected $_db_ArtworkFileModified;
	protected $_db_Status; 
	protected $_db_CustomerSubtotal; 
	protected $_db_VendorSubtotal1;
	protected $_db_VendorSubtotal2;
	protected $_db_VendorSubtotal3;
	protected $_db_VendorSubtotal4;
	protected $_db_VendorSubtotal5;
	protected $_db_VendorSubtotal6;
	protected $_db_CustomerTax;
	protected $_db_CustomerDiscount;
	protected $_db_NotesProduction;
	protected $_db_NotesAdmin;
	protected $_db_EstShipDate;
	protected $_db_EstPrintDate;
	protected $_db_EstArrivalDate;
	protected $_db_SavedID;
	protected $_db_Priority;
	protected $_db_ShippingPriority;
	protected $_db_ArtworkSignature;
	protected $_db_CutOffTime;
	protected $_db_VariableDataArtworkConfigOriginal;
	protected $_db_VariableDataFileOriginal;
	protected $_db_CopyrightPurchase;



	//  Constructor 
	function ProjectOrdered(DbCmd $dbCmd){
		$this->_dbCmd = $dbCmd;
	
		$this->_projectIDloadedFlag = false;
	}
	
	
	// Static methods

	static function CheckIfUserOwnsOrderedProject(DbCmd $dbCmd, $projectID, $userID){
	
		WebUtil::EnsureDigit($projectID);
	
		$dbCmd->Query("SELECT orders.UserID as UserID, orders.DomainID as OrderDomainID, projectsordered.DomainID as ProjectDomainID 
					FROM orders INNER JOIN projectsordered ON orders.ID = projectsordered.OrderID 
					WHERE projectsordered.ID=$projectID");
		
		if($dbCmd->GetNumRows() == 0)
			return false;
		
		$row = $dbCmd->GetRow();
		$dbUserID = $row["UserID"];
		$orderDomainID = $row["OrderDomainID"];
		$projectDomainID = $row["ProjectDomainID"];
	
		if($dbUserID != $userID)
			return false;
			
		if($orderDomainID != $projectDomainID){
			WebUtil::WebmasterError("Domain IDs not matching in CheckIfUserOwnsOrderedProject: user:$dbUserID order domain:$orderDomainID projectdomain:$projectDomainID");
			return false;
		}
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
	
		if(!$passiveAuthObj->CheckIfUserCanViewDomainID($orderDomainID))
			return false;
		
		return true;
	}
	
	// Return TRUE if the project is marked as urgent.
	static function CheckIfUrgent(DbCmd $dbCmd, $projectID){
	
		WebUtil::EnsureDigit($projectID);
	
		$dbCmd->Query("SELECT Priority FROM projectsordered WHERE ID=$projectID");
		if($dbCmd->GetValue() == "U")
			return true;
			
		return false;
	}

	// We need to total up all of the Vendor Columns for the given project
	// We have between 1 and 6 Vendors with each project.
	// Pass in a Vendor ID if you want to restrict the totals to them only
	// Does not include Canceled Projects
	static function GetVendorSubtotalFromProject(DbCmd $dbCmd, $projectID, $vendorIDLimiter = null){
		
		WebUtil::EnsureDigit($projectID);
	
		$vendorSubTotals = 0;
		
		for($i=1; $i<=6; $i++){
			$vQry = "SELECT VendorSubtotal" . $i . " FROM projectsordered WHERE Status != 'C' AND ID=" . $projectID;
			if($vendorIDLimiter)
				$vQry .= " AND VendorID" . $i . " = " . $vendorIDLimiter;
			$dbCmd->Query($vQry);
	
			if($dbCmd->GetNumRows() == 1)
				$vendorSubTotals += $dbCmd->GetValue();
		}
		
		return number_format($vendorSubTotals, 2, '.', '');
	}
	
	
	// Returns an array of VendorID's for a given project
	static function GetUniqueVendorIDsFromOrder(DbCmd $dbCmd, $projectOrderedID){
		
		WebUtil::EnsureDigit($projectOrderedID);
	
		$totalVendorIDsArr = array();
	
		// There are currently 6 Vendor IDs associated with a single project
		for($i=1; $i<=6; $i++){
	
			$dbCmd->Query("SELECT DISTINCT VendorID". $i . "  
				FROM projectsordered WHERE Status != 'C' AND OrderID=$projectOrderedID AND
				VendorID". $i . " >0");
	
			while($thisVendorID = $dbCmd->GetValue()){
				if(!in_array($thisVendorID, $totalVendorIDsArr))
					$totalVendorIDsArr[] = $thisVendorID;
			}
		}
		
		return $totalVendorIDsArr;
	}

	
	static function ChangeProjectStatus(DbCmd $dbCmd, $NewStatus, $projectrecord){
	
		WebUtil::EnsureDigit($projectrecord);
		
		$domainID = ProjectBase::getDomainIDofProjectRecord($dbCmd, "ordered", $projectrecord);
		
		if(empty($domainID))
			throw new Exception("Can't change Project Status because the Project ID doesn't exist.");
			
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainID))
			throw new Exception("Can't change Project Status because the Project ID is not correct.");
	
		// Don't let anyone change a Status from "Finished" to something else
		$dbCmd->UpdateQuery("projectsordered", array("Status"=>$NewStatus), "ID=$projectrecord AND Status != 'F'");
	
	}
	
	
	
	// Update the signature of the Artwork for this order.  We use this to detect re-orders for auto-proofing, etc.
	static function UpdateArtworkSignature(DbCmd $dbCmd, $ProjectOrderID, $ArtworkFile){
	
		$dbCmd->Query("UPDATE projectsordered SET ArtworkSignature = \"" . md5($ArtworkFile) . "\" WHERE ID =" . intval($ProjectOrderID));
	}	
		
		
	// If it can not find a matching artwork signature, then returns FALSE
	// Otherwise Copy over the PDF settings and mark the project as proofed and return TRUE.
	// MinOrderNumber decides how far back we want to look
	static function AutoProof(DbCmd $dbCmd, DbCmd $dbCmd2, $ProjectOrderID, $MinOrderNumber){
	
		//Get the artwork signature out of the project we want to auto-proof
		$dbCmd->Query("SELECT ArtworkSignature FROM projectsordered WHERE ID=$ProjectOrderID");
		$ThisArtworkSignature = $dbCmd->GetValue();
	
		$dbCmd->Query("SELECT po.ID FROM projectsordered AS po 
				INNER JOIN orders AS ord ON po.OrderID = ord.ID 
				WHERE po.ArtworkSignature='$ThisArtworkSignature' AND ord.ID > $MinOrderNumber
					AND (po.Status != 'N' AND po.Status != 'C' AND po.Status != 'H') 
				ORDER BY ord.DateOrdered DESC LIMIT 1
				");
	
		
		if($dbCmd->GetNumRows() == 0)
			return false;
		else
			$CopyProjectID = $dbCmd->GetValue();
		
		
		// If the Product ID is going to be Mailed... then we don't want to AutoProof it.
		$thisProductID = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $CopyProjectID, "ordered");
		
		if(Product::checkIfProductHasVariableData($dbCmd, $thisProductID))
			return false;
		
		
		if(!PDFparameters::CopyPDFsettingsToNewProject($dbCmd, $dbCmd2, $CopyProjectID, $ProjectOrderID))
			return false;
	
		//Mark this has proofed
		ProjectOrdered::ChangeProjectStatus($dbCmd, "P", $ProjectOrderID);
		
		ProjectHistory::RecordProjectHistory($dbCmd, $ProjectOrderID, "Auto Proofed - P" . $CopyProjectID, 0);
		
		return true;
	}

	
	
		

	/**
	 * A Static function for creating an object of this class and returning it
	 * @return ProjectOrdered
	 */
	static function getObjectByProjectID(DbCmd $dbCmd, $projectID, $initializeProjectInfoObjFlag = true){
		$projectObject = new ProjectOrdered($dbCmd);
		$projectObject->loadByProjectID($projectID, $initializeProjectInfoObjFlag);
		return $projectObject;
	}

	
	
	static function GetProjectStatus(DbCmd $dbCmd, $projectrecord){
	
		WebUtil::EnsureDigit($projectrecord);
	
		$dbCmd->Query("SELECT Status FROM projectsordered WHERE ID =" . $projectrecord);
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Error in function call ProjectOrdered::GetProjectStatus");
		return $dbCmd->GetValue();
	}

	
	// If the Artwork is changed on a Project, or maybe the Variable mapping changes...
	// Then we want to clone those to a Saved Project (if it is an ordered project)
	// Or break Saved Project Links if we are in the Shopping cart, etc.
	static function ProjectChangedSoBreakOrCloneAssociates(DbCmd $dbCmd, $viewType, $projectrecord){
	
	
		$user_sessionID =  WebUtil::GetSessionID();
	
		$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $viewType, $projectrecord);
	
	
		// We may need to copy over information from the Saved Project into the shopping cart
		if($viewType == "saved"){
		
			$ProjectSessionIDsThatAreSavedArr = ShoppingCart::GetProjectSessionIDsInShoppingCartThatAreSaved($dbCmd, $projectrecord, $user_sessionID);
	
			foreach($ProjectSessionIDsThatAreSavedArr as $projectSessionID){
	
				$projectSessionObj = ProjectBase::getProjectObjectByViewType($dbCmd, "session", $projectSessionID);
				$projectSessionObj->copyProject($projectObj);
				$projectSessionObj->updateDatabase();
			}
		}
		else if($viewType == "projectssession" || $viewType == "session"){
		
			// If any projects in the shopping cart are shown as being "saved" then we need to let them know the information may not be saved
			ProjectSaved::ClearSavedIDLinksByViewType($dbCmd, "projectssession", $projectrecord);
		}
		else if($viewType == "customerservice" || $viewType == "proof"){
		
			// Copy over information into any Saved Projects... if a link exists.
			ProjectOrdered::CloneOrderForSavedProject($dbCmd, $projectrecord);
		}
		else{
			throw new Exception("Illegal view type called in function ProjectOrdered::ProjectChangedSoBreakOrCloneAssociates.");
		}
	}
	
	
	// Will record into the DB that the projects ordered...needs the artwork Preview image updated
	// We don't want to wait.  Let a cron job look in the DB instead
	static function ProjectOrderedNeedsArtworkUpdate(DbCmd $dbCmd, $projectID){
		
		$InsertHash["ProjectOrderedID"] = intval($projectID);
		$dbCmd->InsertQuery( "projectorderedartworkupdate", $InsertHash );
	}

	
	// Will inspect the projectsordered.  If it finds a SavedID link then it will copy over the artwork and project info information to the corresponding Saved Project
	// You would want to chang this anytime that you make a change to product options or the artwork
	static function CloneOrderForSavedProject(DbCmd $dbCmd, $ProjectOrderID){
		
		$SavedIDlink = ProjectOrdered::GetSavedIDLinkFromProjectOrdered($dbCmd, $ProjectOrderID);
		
		if($SavedIDlink <> 0){
	
			$projectOrderedObj = ProjectBase::getProjectObjectByViewType($dbCmd, "ordered", $ProjectOrderID);
		
			$projectSavedObj = ProjectBase::getProjectObjectByViewType($dbCmd, "saved", $SavedIDlink);
		
			$projectSavedObj->copyProject($projectOrderedObj);
		
			$projectSavedObj->updateDatabase();
			
			// So the Thumbnail can update in the background.
			ProjectSaved::ProjectSavedNeedsAnUpdate($dbCmd, $SavedIDlink);
		}
	
	}
	
	
	// Will check if a PDF settings has been saved for every side of the Artwork 
	// returns true or false 
	static function CheckIfBleedSettingsAreSaved(DbCmd $dbCmd, $projectOrderID){
	
		$projectOrderID = intval($projectOrderID);
	
		// Find out how many PDF sides have been pregenerated
		$dbCmd->Query("SELECT count(*) FROM pdfgenerated where ProjectRef=" . $projectOrderID);
		$PreGeneratedCount = $dbCmd->GetValue();
	
		// Now Get the Artfile
		$ArtFile = ArtworkLib::GetArtXMLfile($dbCmd, "proof", $projectOrderID);
	
		// Instead of parsing the XML doc to check the number of sides.... lets just find out how many Side Tags there are using preg_match_all
		$countArr = array();
		preg_match_all ("/<side>/", $ArtFile, $countArr);
		$numberOfArtworkSides = sizeof($countArr[0]);
	
		if($numberOfArtworkSides == $PreGeneratedCount)
			return true;
		else
			return false;
	}

	static function SetBleedSettingsToNaturalOnProject(DbCmd $dbCmd, $projectOrderID){
	
		$ArtFile = ArtworkLib::GetArtXMLfile($dbCmd, "proof", $projectOrderID, false);
	
		$ArtworkInfoObj = new ArtworkInformation($ArtFile);
	
		$dbCmd->Query("DELETE FROM pdfgenerated WHERE ProjectRef=$projectOrderID");
	
		$sideCounter = 0;
		foreach($ArtworkInfoObj->SideItemsArray as $SideObj){
			
			$pdfSettingsArr["ProjectRef"] = $projectOrderID;
			$pdfSettingsArr["SideNumber"] = $sideCounter;
			$pdfSettingsArr["SideName"] = $SideObj->description;
			$pdfSettingsArr["DateCreated"] = time();
			$pdfSettingsArr["BleedType"] = "N";
			$pdfSettingsArr["Rotate"] = "0";
			
			$dbCmd->InsertQuery("pdfgenerated", $pdfSettingsArr);
		
			$sideCounter++;
		}
	}
	
	
	// Returns an Int if a link exits.... 0, if not
	static function GetSavedIDLinkFromProjectOrdered(DbCmd $dbCmd, $ProjectOrderID){
		
		// First find out if a Saved ID link Exists
		$dbCmd->Query("SELECT SavedID FROM projectsordered WHERE ID=$ProjectOrderID");
		return $dbCmd->GetValue();
	}
	
	// If you change the Option or Choice Alias name, then call this to refresh the values in the Database
	// Make sure that you only call this when a change occurs to avoid uncessary overhead.
	static function refreshOptionAliasesOnOpenOrders($productID){
		
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT ID FROM projectsordered WHERE Status !='F' AND Status != 'C' AND ProductID=" . intval($productID));
		
		$projectIDarr = $dbCmd->GetValueArr();
		
		foreach($projectIDarr as $pid){
			
			$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "ordered", $pid);
			$newOptionAliasStr = $projectObj->getOptionsAliasStr();
			
			$dbCmd->UpdateQuery("projectsordered", array("OptionsAlias"=>$newOptionAliasStr), "ID=$pid");
		}
	}
	

	// Pass in an array of Project IDs, it will make sure that the user has permissiont to see every single one, or returns FALSE
	static function validateDomainPermissionOnProjectArr(DbCmd $dbCmd, array $projectIDArr){
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		$allDomainsOfUserArr = $passiveAuthObj->getUserDomainsIDs();
		
		if(!$passiveAuthObj->CheckIfLoggedIn() || !$passiveAuthObj->CheckIfUserIDisMember($passiveAuthObj->GetUserID()))
			throw new Exception("Error in method validatePermissionOnProjectArr. The user must be a member.");
		
		foreach($projectIDArr as $thisProjectID){
			
			$thisProjectID = intval($thisProjectID);
			
			$domainIDofProject = ProjectBase::getDomainIDofProjectRecord($dbCmd, "ordered", $thisProjectID);
			
			if(!in_array($domainIDofProject, $allDomainsOfUserArr))
				return false;
		}
		
		return true;
		
	}

	
	
	function getViewTypeOfProject(){
		return "ordered";
	}
	
	// You may not want to initialize a ProjectInfo Object(in order to increase performance).  
	// However without a ProjectInfo object intialized some of the methods will not work
	function loadByProjectID($projectID, $initializeProjectInfoObjFlag = true){
	
		if(!preg_match("/^\d+$/", $projectID))
			throw new Exception("The Project ID is not a digit: -$projectID-");
	
		$this->_dbCmd->Query("SELECT *, 
					UNIX_TIMESTAMP(EstShipDate) AS ShipDate, 
					UNIX_TIMESTAMP(EstPrintDate) AS PrintDate,
					UNIX_TIMESTAMP(EstArrivalDate) AS ArrivalDate, 
					UNIX_TIMESTAMP(CutOffTime) AS CutOff 
					FROM projectsordered WHERE ID=$projectID");
		
		if($this->_dbCmd->GetNumRows() != 1)
			throw new Exception("The Project ID does not exist: $projectID");
		
		$row = $this->_dbCmd->GetRow();
		
		$this->_db_OrderID = $row["OrderID"];
		$this->_db_VendorID1 = $row["VendorID1"];
		$this->_db_VendorID2 = $row["VendorID2"];
		$this->_db_VendorID3 = $row["VendorID3"];
		$this->_db_VendorID4 = $row["VendorID4"];
		$this->_db_VendorID5 = $row["VendorID5"];
		$this->_db_VendorID6 = $row["VendorID6"];
		$this->_db_RePrintLink = $row["RePrintLink"];
		$this->_db_RePrintReason = $row["RePrintReason"];
		$this->_db_ArtworkFile = $row["ArtworkFile"];
		$this->_db_ArtworkFileModified = $row["ArtworkFileModified"];
		$this->_db_Status = $row["Status"];
		$this->_db_CustomerSubtotal = $row["CustomerSubtotal"];
		$this->_db_VendorSubtotal1 = $row["VendorSubtotal1"];
		$this->_db_VendorSubtotal2 = $row["VendorSubtotal2"];
		$this->_db_VendorSubtotal3 = $row["VendorSubtotal3"];
		$this->_db_VendorSubtotal4 = $row["VendorSubtotal4"];
		$this->_db_VendorSubtotal5 = $row["VendorSubtotal5"];
		$this->_db_VendorSubtotal6 = $row["VendorSubtotal6"];
		$this->_db_CustomerTax = $row["CustomerTax"];
		$this->_db_CustomerDiscount = $row["CustomerDiscount"];
		$this->_db_NotesProduction = $row["NotesProduction"];
		$this->_db_NotesAdmin = $row["NotesAdmin"];
		$this->_db_EstShipDate = $row["ShipDate"];
		$this->_db_EstPrintDate = $row["PrintDate"];
		$this->_db_EstArrivalDate = $row["ArrivalDate"];
		$this->_db_SavedID = $row["SavedID"];
		$this->_db_Priority = $row["Priority"];
		$this->_db_ShippingPriority = $row["ShippingPriority"];
		$this->_db_ArtworkSignature = $row["ArtworkSignature"];
		$this->_db_CutOffTime = $row["CutOff"];
		$this->_db_CopyrightPurchase = $row["CopyrightPurchase"];

		
		// These private member variables are declared in the Super Class... they are in common with all type of projects
		$this->_db_OrderDescription = $row["OrderDescription"];
		$this->_db_OptionsDescription = $row["OptionsDescription"];
		$this->_db_OptionsAlias = $row["OptionsAlias"];
		$this->_db_VariableDataStatus = $row["VariableDataStatus"];
		$this->_db_VariableDataMessage = $row["VariableDataMessage"];	
		$this->_db_VariableDataArtworkConfig = $row["VariableDataArtworkConfig"];
		$this->_db_VariableDataFirstLine = $row["VariableDataFirstLine"];
		$this->_db_ProductID = $row["ProductID"];
		$this->_db_Quantity = $row["Quantity"];
		$this->_db_ArtworkSides = $row["ArtworkSides"];
		$this->_db_FromTemplateID = $row["FromTemplateID"];
		$this->_db_FromTemplateArea = $row["FromTemplateArea"];
		$this->_db_VariableDataArtworkConfigOriginal = $row["VariableDataArtworkConfigOriginal"];
		$this->_db_DomainID = $row["DomainID"];
		
		$this->_projectRecordID = $projectID;
		
		
		// Load the variable data from a separate table since it can be quite large.
		if($this->isVariableData()){
			$this->_dbCmd->Query("SELECT VariableDataOriginal, VariableDataModified FROM variabledataordered WHERE ProjectID=$projectID");
			if($this->_dbCmd->GetNumRows() > 0){
				$row = $this->_dbCmd->GetRow();
				$this->_db_VariableDataFileOriginal = $row["VariableDataOriginal"];
				$this->_db_VariableDataFile = $row["VariableDataModified"];
			}
		}
		
		if($initializeProjectInfoObjFlag){
			// Create a new ProjectInfo Object based on the Product ID
			$this->_projectInfoObj = new ProjectInfo($this->_dbCmd, $this->_db_ProductID, false);
			$this->_projectInfoClassInitializedFlag = true;

			// Merge the Quantity/Options from the current Project info our ProjectInfo Object
			$this->_projectInfoObj->setQuantity($this->_db_Quantity);
			$this->_projectInfoObj->setOptionChoicesFromDelimitedString($this->_db_OptionsDescription);
		}
		else{
			$this->_projectInfoClassInitializedFlag = false;
		}
		
		$this->_projectIDloadedFlag = true;
	}
	

	
	// Should only be used when editing an existing project
	// Don't use this method when you intend to "Create New Project"
	function updateDatabase(){
	
		if(!$this->_projectIDloadedFlag)
			throw new Exception("You must load a Project By ID before calling this method: updateDatabase");
		
		$this->_prepareCommonDatabaseFields();
	
		$specialDBfieldsHash = $this->_getProprietoryDBfields();
		
		// $this->_dbHash is set in the Super Class
		$this->_dbHash = array_merge($this->_dbHash, $specialDBfieldsHash);

		// Special fields that we want coming through the ProjectInfo Object instead of what was recorded in the database
		$this->_dbHash["CustomerSubtotal"] = $this->_projectInfoObj->getCustomerSubTotal();
		
		for($i=1; $i<= 6; $i++){
			$this->_dbHash[("VendorSubtotal" . $i)] = $this->_projectInfoObj->getVendorSubTotal($i);
			$this->_dbHash[("VendorID" . $i)] = $this->_projectInfoObj->getVendorID($i);
		}


		$this->_dbCmd->UpdateQuery("projectsordered", $this->_dbHash, ("ID=" . $this->_projectRecordID));
		
		
		$this->updateVariableDataFile();
	}
	
	
	// By default when a Project is loaded the Options/Prices will be merged with the current data in the Product Object
	// If you open up a project and then resave it with the method updateDatabase() ... it is possible the subtotal could change as well as the options
	// Call this method if you want the original Data put back into the database... even if prices/options have since changed
	function updateDatabaseWithRawData(){
	
		if(!$this->_projectIDloadedFlag)
			throw new Exception("You must load a Project By ID before calling this method: updateDatabaseWithRawData");
		
		$this->_prepareCommonDatabaseFields();
		
		$specialDBfieldsHash = $this->_getProprietoryDBfields();
		
		// $this->_dbHash is set in the Super Class
		$updateArr = array_merge($this->_dbHash, $specialDBfieldsHash);


		// Anything else that we want to override with Data specifically from the DB, not through ProjectInfo
		// Some of the things have been overridden from within _getProprietoryDBfields
		$updateArr["OrderDescription"] = $this->_db_OrderDescription;
		$updateArr["OptionsDescription"] = $this->_db_OptionsDescription;
		$updateArr["OptionsAlias"] = $this->_db_OptionsAlias;

	
		$this->_dbCmd->UpdateQuery("projectsordered", $updateArr, ("ID=" . $this->_projectRecordID));

		$this->updateVariableDataFile();
		
	}
	
	// Update the variable data file within a separate table. 
	// Only call this on existing projects when you are updating
	private function updateVariableDataFile(){

		if(empty($this->_projectRecordID))
			throw new Exception("Error in method updateVariableDataFile. The Project Record has not been set.");
		
		if($this->isVariableData()){
			
			// Figure out if we need to insert a new record into the external variable data table... or update the existing record.
			$this->_dbCmd->Query("SELECT COUNT(*) FROM variabledataordered WHERE ProjectID=" . $this->_projectRecordID);
			if($this->_dbCmd->GetValue() == 0){
					
				$varDataArr = array();
				$varDataArr["VariableDataOriginal"] = $this->_db_VariableDataFileOriginal;
				$varDataArr["VariableDataModified"] = $this->_db_VariableDataFile;
				$varDataArr["ProjectID"] = $this->_projectRecordID;
				
				$this->_dbCmd->InsertQuery("variabledataordered", $varDataArr);
			}
			else{
				$varDataArr = array();
				$varDataArr["VariableDataOriginal"] = $this->_db_VariableDataFileOriginal;
				$varDataArr["VariableDataModified"] = $this->_db_VariableDataFile;
				
				$this->_dbCmd->UpdateQuery("variabledataordered", $varDataArr, "ProjectID=" . $this->_projectRecordID);
			}
		}
	}
	// Returns a new ProjectID that was inserted into the database
	function createNewProject(){
	
		$this->_ensureProjectInfoIntialized();
		
		if(!$this->_db_OrderID)
			throw new Exception("The Order ID must be set before calling createNewProject");
		
		$this->_prepareCommonDatabaseFields();
		
		$specialDBfieldsHash = $this->_getProprietoryDBfields();
		
		$this->_dbHash = array_merge($this->_dbHash, $specialDBfieldsHash);
		
		for($i=1; $i<= 6; $i++){
			$this->_dbHash[("VendorSubtotal" . $i)] = $this->_projectInfoObj->getVendorSubTotal($i);
			$this->_dbHash[("VendorID" . $i)] = $this->_projectInfoObj->getVendorID($i);
		}
		

		$newProjectID = $this->_dbCmd->InsertQuery("projectsordered", $this->_dbHash);
		
		$this->_projectRecordID = $newProjectID;
		
		$this->updateVariableDataFile();
		
		return $newProjectID;
	}

	// If there is a modified version, give em that one... otherwise give them the original
	function getArtworkFile(){
		if(empty($this->_db_ArtworkFileModified))
			return $this->_db_ArtworkFile;
		else
			return $this->_db_ArtworkFileModified;
	}
	

	// Generally you shouldn't need to call getOriginalArtworkFile or getModifiedArtworkFile
	function getOriginalArtworkFile(){
		return $this->_db_ArtworkFile;
	}
	function getModifiedArtworkFile(){
		return $this->_db_ArtworkFileModified;
	}
	

	function resetArtworkSignature(){
		$this->_db_ArtworkSignature = md5($this->getArtworkFile());
	}
	
	// In case this Project has Choices Selected which cause Extra Production Alerts... this method will return a string with the extra alerts
	// If there are no alerts this method will return a blank string.
	// This is different than the regular Production Alerts manually saved to a Project ID.  It should be considered an addition.
	// If there are multiple Production Alerts associated with multiple Choices, this method will return all of the alerts separated by a space (and a Newline character).
	function getExtraProductionAlert(){
	
		$optionsArr = array_keys($this->getProductOptionsArray());
	
		$retStr = "";
	
		// Loop through all of the selected Option Choices and see if any of them have an alert.
		foreach($optionsArr as $thisOptionName){
			
			$choiceObj = $this->getSelectedChoiceObj($thisOptionName);
			
			if(!empty($choiceObj->productionAlert)){
			
				if(!empty($retStr))
					$retStr .= " \n";
				
				
				$retStr .= $choiceObj->productionAlert;
			}
		}
		
		return $retStr;
	}
	


	// Pass in another Project Opject and it will copy everything from it into the current object
	// Extracts Product Options, Artwork, etc.
	function copyProject(ProjectBase $projectObj){
	
		$this->setProjectInfoObject($projectObj->getProjectInfoObject());

		$this->_db_CustomerSubtotal = $projectObj->getCustomerSubTotal();
		$this->setVendorSubtotalsByArr($projectObj->getVendorSubtotalsArr());
		$this->setVendorIDsByArr($projectObj->getVendorIDArr());

		$this->setFromTemplateID($projectObj->getFromTemplateID());
		$this->setFromTemplateArea($projectObj->getFromTemplateArea());
		
		$this->_db_VariableDataStatus = $projectObj->getVariableDataStatus();
		$this->_db_VariableDataMessage = $projectObj->getVariableDataMessage();
		$this->_db_VariableDataFirstLine = $projectObj->getVariableDataFirstLine();

		$this->_db_ArtworkSides = $projectObj->getArtworkSidesCount();
		$this->_db_DomainID = $projectObj->getDomainID();
		$this->_db_ProductID = $projectObj->getProductID();
		

		// Copy different compenents if we know the subclass that it is coming from
		if($projectObj->getViewTypeOfProject() == "ordered"){
			$this->_db_ArtworkFile = $projectObj->getOriginalArtworkFile();
			$this->_db_ArtworkFileModified = $projectObj->getModifiedArtworkFile();

			$this->_db_VariableDataArtworkConfig = $projectObj->getModifiedVariableDataArtworkConfig();
			$this->_db_VariableDataArtworkConfigOriginal = $projectObj->getOriginalVariableDataArtworkConfig();
			$this->_db_VariableDataFile = $projectObj->getModifiedVariableDataFile();
			$this->_db_VariableDataFileOriginal = $projectObj->getOriginalVariableDataFile();

			// Since we know it if coming from another ProjectOrdered Subclass... get the original Subtotals
			// ... instead of going through the ProjectInfo Object
			$this->_db_CustomerSubtotal = $projectObj->getCustomerSubtotal_DB();
			$this->setVendorSubtotalsByArr($projectObj->getVendorSubtotals_DB_Arr());
			$this->setVendorIDsByArr($projectObj->getVendorID_DB_Arr());
			
			
			$this->setRePrintLink($projectObj->getRePrintLink());
			$this->setRePrintReason($projectObj->getRePrintReason());
			$this->setStatusChar($projectObj->getStatusChar());
			$this->setCustomerTax($projectObj->getCustomerTax());
			$this->setCustomerDiscount($projectObj->getCustomerDiscount());
			$this->setNotesProduction($projectObj->getNotesProduction());
			$this->setNotesAdmin($projectObj->getNotesAdmin());
			$this->setEstShipDate($projectObj->getEstShipDate());
			$this->setEstPrintDate($projectObj->getEstPrintDate());
			$this->setEstArrivalDate($projectObj->getEstArrivalDate());
			$this->setSavedID($projectObj->getSavedID());
			$this->setPriority($projectObj->getPriority());
			$this->setShippingPriority($projectObj->getShippingPriority());			
			$this->setCutOffTime($projectObj->getCutOffTime());
			$this->setCopyrightPurchase("N");  // If someone purchased copyrights on a template from one project.. we don't want that copied across.

		}
		else if($projectObj->getViewTypeOfProject() == "session"){
			
			$this->_db_ArtworkFile = $projectObj->getArtworkFile();
			$this->_db_ArtworkFileModified = "";
			
			$this->_db_VariableDataArtworkConfig = "";
			$this->_db_VariableDataArtworkConfigOriginal = $projectObj->getVariableDataArtworkConfig();
			$this->_db_VariableDataFile = "";
			$this->_db_VariableDataFileOriginal = $projectObj->getVariableDataFile();


			$this->setRePrintLink(0);
			$this->setRePrintReason("");
			$this->setStatusChar("N"); // New Project if we are copying from a Project Session
			$this->setCustomerTax("0.00");
			$this->setCustomerDiscount(0);
			$this->setNotesProduction("");
			$this->setNotesAdmin("");
			$this->setEstShipDate(0);
			$this->setEstPrintDate(0);
			$this->setEstArrivalDate(0);
			$this->setSavedID($projectObj->getSavedID());
			$this->setPriority("N");  // Always start off with a 'N'ormal priority
			$this->setShippingPriority("3");  // Just set it to the last... let the order function adjust it.	
			$this->setCutOffTime(0);
			$this->setCopyrightPurchase("N");
			
		}
		else
			throw new Exception("There is no definition for copying a project from this Subclass yet");
		
		$this->resetArtworkSignature();
	}
	


	
	
	
	
	
	
	// --------------  Setter methods for DB fields propietory to this SubClass


	
	// Always update the modified version... the original should remain untouched
	// By Default the Artwork file will be filtered for Possibly Bad stuff
	// If you know the artwork is good then you might not want to filter it for performance reasons.
	function setArtworkFile($x, $filterArtworkFile = true){
		if($filterArtworkFile)
			$x = ArtworkInformation::FilterArtworkXMLfile($x);
		$this->_db_ArtworkFileModified = $x;
	}
	
	// This should only really be used when creating the original Order
	function setOriginalArtworkFile($x){
		$this->_db_ArtworkFile = $x;
	}

	function setOrderID($x){
		if(!preg_match("/^\d+$/", $x))
			throw new Exception("The Order Number must be a number");
		$this->_db_OrderID = $x;
	}
	

	// Set multiple vendor IDs by passing in an array of Vendor IDs
	// The array should be Zero based with a total of 6 elements.
	// There could be missing gaps in the array indexes in case VendorID #3 is defined but But VendorID #2 is not.
	function setVendorIDsByArr($xArr){
		if(!is_array($xArr))
			throw new Exception("Error in method call setVendorIDsByArr, parameter is not an array");
		if(empty($xArr))
			throw new Exception("Error in method call setVendorIDsByArr, array is empty");
		if(sizeof($xArr) > 6)
			throw new Exception("Error in method call setVendorIDsByArr, array has more than 6 elements");
		
		for($i=1; $i<=6; $i++){
			if(!isset($xArr[$i-1]))
				$newVendorID = null;
			else
				$newVendorID = $xArr[$i-1];
			
			eval('$this->setVendorID' . $i . "('$newVendorID');");
		}
	}
	function setVendorID1($x){
		if(!preg_match("/^\d+$/", $x) && $x != null)
			throw new Exception("The Vendor ID 1 must be a Number");
		$this->_db_VendorID1 = $x;
	}
	function setVendorID2($x){
		if(!preg_match("/^\d+$/", $x) && $x != null)
			throw new Exception("The Vendor ID 2 must be a Number");
		$this->_db_VendorID2 = $x;
	}
	function setVendorID3($x){
		if(!preg_match("/^\d+$/", $x) && $x != null)
			throw new Exception("The Vendor ID 3 must be a Number");
		$this->_db_VendorID3 = $x;
	}
	function setVendorID4($x){
		if(!preg_match("/^\d+$/", $x) && $x != null)
			throw new Exception("The Vendor ID 4 must be a Number");
		$this->_db_VendorID4 = $x;
	}
	function setVendorID5($x){
		if(!preg_match("/^\d+$/", $x) && $x != null)
			throw new Exception("The Vendor ID 5 must be a Number");
		$this->_db_VendorID5 = $x;
	}
	function setVendorID6($x){
		if(!preg_match("/^\d+$/", $x) && $x != null)
			throw new Exception("The Vendor ID 6 must be a Number");
		$this->_db_VendorID6 = $x;
	}


	// Set multiple vendor IDs by passing in an array of Vendor IDs
	function setVendorSubtotalsByArr($xArr){

		if(!is_array($xArr))
			throw new Exception("Error in method call setVendorSubtotalssByArr, parameter is not an array");
		if(empty($xArr))
			throw new Exception("Error in method call setVendorSubtotalssByArr, array is empty");
		if(sizeof($xArr) > 6)
			throw new Exception("Error in method call setVendorSubtotalssByArr, array has more than 6 elements");
		
		for($i=1; $i<=6; $i++){
			if(!isset($xArr[$i-1]))
				$newVendorSubtotal = null;
			else
				$newVendorSubtotal = $xArr[$i-1];
			
			$this->setVendorSubtotalByNumber($i, $newVendorSubtotal);
		}
	}
	
	
	function setVendorSubtotalByNumber($vendorNumber, $vendorSubtotal){
		
		if($vendorNumber < 1 || $vendorNumber > 6 )
			throw new Exception("Error with method setVendorSubtotalByNumber.  The Vendor Number is out of range.");
		
		if($vendorSubtotal === null)
			$vendorSubtotal = 0;
			
		if(!preg_match("/^\d+(\.\d+)?$/", $vendorSubtotal))
			throw new Exception("Error in method setVendorSubtotalByNumber.  Price format not correct for Vendor Num: " . $vendorNumber . " : " . $vendorSubtotal);
		
		$vendorSubtotal = number_format($vendorSubtotal, 2, '.', '');

		eval('$this->_db_VendorSubtotal' . $vendorNumber . " = '$vendorSubtotal';");
	}


	function setRePrintLink($x){
		if(!preg_match("/^\d+$/", $x))
			throw new Exception("The Reprint Linkmust be a Number");
		$this->_db_RePrintLink = $x;
	}
	function setRePrintReason($x){
		if(strlen($x) != 1 && strlen($x) != 0)
			throw new Exception("The reprint reason must be a single character");
		$this->_db_RePrintReason = $x;
	}
	function setStatusChar($x){
		if(strlen($x) != 1)
			throw new Exception("The Status Char must be a single character");
		$this->_db_Status = $x;
	}
	
	// Customer Tax is not calculated by ProjectInfo
	// It should be calculated as a function of an order because we need to know what state it is shipping to
	function setCustomerTax($x){
		if(!preg_match("/^\d+\.\d+$/", $x))
			throw new Exception("The customer tax in not in proper format");
		$this->_db_CustomerTax = $x;
	}
	
	function setCustomerDiscount($x){
		if(!preg_match("/^\d+\.\d+$/", $x) && !preg_match("/^0$/", $x) && !preg_match("/^\d+$/", $x))
			throw new Exception("The customer discount in not in proper format");
		$this->_db_CustomerDiscount = $x;
	}
	function setNotesProduction($x){
		if(strlen($x) > 200)
			throw new Exception("The Production notes can not exceed 200 characters");
		$this->_db_NotesProduction = $x;
	}
	function setNotesAdmin($x){
		if(strlen($x) > 200)
			throw new Exception("The Admin notes can not exceed 200 characters");
		$this->_db_NotesAdmin = $x;
	}
	function setEstShipDate($x){
		if(!preg_match("/^\d+$/", $x))
			throw new Exception("ShipDate must be a Unix Timestamp");
		$this->_db_EstShipDate = $x;
	}
	function setEstPrintDate($x){
		if(!preg_match("/^\d+$/", $x))
			throw new Exception("PrintDate must be a Unix Timestamp");
		$this->_db_EstPrintDate = $x;
	}
	function setEstArrivalDate($x){
		if(!preg_match("/^\d+$/", $x))
			throw new Exception("Arrival Date must be a Unix Timestamp");
		$this->_db_EstArrivalDate = $x;
	}
	function setSavedID($x){
		if(empty($x))
			$x = 0;
			
		if(!preg_match("/^\d+$/", $x))
			throw new Exception("Saved ID must be a Number");
		$this->_db_SavedID = $x;
	}
	function setPriority($x){
		if($x != "U" && $x != "N")
			throw new Exception("Priority must be 'N'ormal or 'U'rgent");
		$this->_db_Priority = $x;
	}
	function setCopyrightPurchase($x){
		if($x != "Y" && $x != "N")
			throw new Exception("Copyright Purchase must be 'Y'es or 'N'o");
		$this->_db_CopyrightPurchase = $x;
	}
	function setShippingPriority($x){
		if(strlen($x) > 1)
			throw new Exception("You can not set the shipping priority with a string length greater than 1.");
		$this->_db_ShippingPriority = $x;
	}
	
	function setCutOffTime($x){
		if(!preg_match("/^\d+$/", $x))
			throw new Exception("Cut off time must be a Unix Timestamp");
		$this->_db_CutOffTime = $x;
	}


	// This should only really be used when creating the original Order
	function setVariableDataArtworkConfigOriginal($x){
		$this->_ensureProjectInfoIntialized();
		
		if(!$this->_projectInfoObj->isVariableData())
			throw new Exception("You can not setVariableDataArtworkConfig on a product that does not have variable data");
		
		$this->_db_VariableDataArtworkConfigOriginal = $x;
	}
	function setVariableDataFileOriginal($x){
		$this->_ensureProjectInfoIntialized();
		
		if(!$this->_projectInfoObj->isVariableData())
			throw new Exception("You can not setVariableDataFile on a product that does not have variable data");
		
		$this->_db_VariableDataFileOriginal = $x;
	}
	




	
	
	// --------------   Returns Database fields propietory to this SubClass
	
	
	function getOrderID(){
		return $this->_db_OrderID;
	}
	
	function getUserID(){
		$this->_dbCmd->Query("SELECT UserID FROM orders WHERE ID = " . $this->_db_OrderID);
		$userID = $this->_dbCmd->GetValue();
		return $userID;
	}
	
	
	// Array is 0 based with 6 elements.  It contains all of the Vendors that have duties for this Project Ordered
	// There could be missing gaps in the array indexes in case Vendor#3 is not defined but Vendor#4 is defined.
	function getVendorID_DB_Arr(){
	
		$retArr = array();
		
		if(!empty($this->_db_VendorID1))
			$retArr[0] = $this->_db_VendorID1;
		if(!empty($this->_db_VendorID2))
			$retArr[1] = $this->_db_VendorID2;
		if(!empty($this->_db_VendorID3))
			$retArr[2] = $this->_db_VendorID3;
		if(!empty($this->_db_VendorID4))
			$retArr[3] = $this->_db_VendorID4;
		if(!empty($this->_db_VendorID5))
			$retArr[4] = $this->_db_VendorID5;
		if(!empty($this->_db_VendorID6))
			$retArr[5] = $this->_db_VendorID6;
		
		return $retArr;
	}
	// These VendorID's come for what is in the Database Table
	// Calling the method "getVendorIDx" from the Base Class goes through the ProjectInfo Object 
	// ... which gets an up-to-date Vendor ID for the Product (which may be different)
	function getVendorID_DB_1(){
		return $this->_db_VendorID1;
	}
	function getVendorID_DB_2(){
		return $this->_db_VendorID2;
	}
	function getVendorID_DB_3(){
		return $this->_db_VendorID3;
	}
	function getVendorID_DB_4(){
		return $this->_db_VendorID4;
	}
	function getVendorID_DB_5(){
		return $this->_db_VendorID5;
	}
	function getVendorID_DB_6(){
		return $this->_db_VendorID6;
	}
	

	
	function getRePrintLink(){
		return $this->_db_RePrintLink;
	}
	function getRePrintReason(){
		return $this->_db_RePrintReason;
	}
	function getStatusChar(){
		return $this->_db_Status;
	}
	
	// Gets data directly from the DB field, rather than going through the ProjectInfo Object
	function getCustomerSubtotal_DB(){
		return $this->_db_CustomerSubtotal;
	}

	// Array is 0 based with 6 elements. 
	function getVendorSubtotals_DB_Arr(){
		return array($this->_db_VendorSubtotal1, $this->_db_VendorSubtotal2, $this->_db_VendorSubtotal3, $this->_db_VendorSubtotal4, $this->_db_VendorSubtotal5, $this->_db_VendorSubtotal6);
	}
	function getVendorSubtotal_DB_1(){
		return $this->_db_VendorSubtotal1;
	}
	function getVendorSubtotal_DB_2(){
		return $this->_db_VendorSubtotal2;
	}
	function getVendorSubtotal_DB_3(){
		return $this->_db_VendorSubtotal3;
	}
	function getVendorSubtotal_DB_4(){
		return $this->_db_VendorSubtotal4;
	}
	function getVendorSubtotal_DB_5(){
		return $this->_db_VendorSubtotal5;
	}
	function getVendorSubtotal_DB_6(){
		return $this->_db_VendorSubtotal6;
	}


	// Customer Tax is not calculated by ProjectInfo
	// It should be calculated as a function of an order.
	function getCustomerTax(){
		return $this->_db_CustomerTax;
	}

	function getCustomerDiscount(){
		return $this->_db_CustomerDiscount;
	}
	function getNotesProduction(){
		return $this->_db_NotesProduction;
	}
	function getNotesAdmin(){
		return $this->_db_NotesAdmin;
	}
	function getEstShipDate(){
		return $this->_db_EstShipDate;
	}
	function getEstPrintDate(){
		return $this->_db_EstPrintDate;
	}
	function getEstArrivalDate(){
		return $this->_db_EstArrivalDate;
	}
	function getSavedID(){
		return $this->_db_SavedID;
	}
	function getPriority(){
		return $this->_db_Priority;
	}
	function getCopyrightPurchase(){
		return $this->_db_CopyrightPurchase;
	}
	function getShippingPriority(){
		return $this->_db_ShippingPriority;
	}
	function getArtworkSignature(){
		return $this->_db_ArtworkSignature;
	}
	function getCutOffTime(){
		return $this->_db_CutOffTime;
	}
	


	// Projecs Ordered never indicate a Copy... but for Polymorphism, keep this method to match the other types of projects.
	function checkIfArtworkCopied(){
		return false;
	}
	function checkIfArtworkTransfered(){
		return false;
	}


	// Override the Base Class's definition for this method
	// We save the Original & Modified version of the files on an order
	function getVariableDataArtworkConfig(){
		$this->_ensureProjectInfoIntialized();
			
		if(!empty($this->_db_VariableDataArtworkConfig))
			return $this->_db_VariableDataArtworkConfig;
		
		return $this->_db_VariableDataArtworkConfigOriginal;
	}
	function getVariableDataFile(){
		$this->_ensureProjectInfoIntialized();
			
		if(!empty($this->_db_VariableDataFile))
			return $this->_db_VariableDataFile;
		
		return $this->_db_VariableDataFileOriginal;
	}
	

	// Generally you shouldn't need to call getOriginalArtworkFile or getModifiedArtworkFile
	function getOriginalVariableDataArtworkConfig(){
		return $this->_db_VariableDataArtworkConfigOriginal;
	}
	function getModifiedVariableDataArtworkConfig(){
		return $this->_db_VariableDataArtworkConfig;
	}
	
	function getOriginalVariableDataFile(){
		return $this->_db_VariableDataFileOriginal;
	}
	function getModifiedVariableDataFile(){
		return $this->_db_VariableDataFile;
	}
	
	
	// This just returns the Order Date... we don't record modification times.
	function getDateLastModified(){
	
		if(!$this->_projectIDloadedFlag)
			throw new Exception("Error in method getDateLastModified. The Project must be loaded first.");
		
		$this->_dbCmd->Query("SELECT UNIX_TIMESTAMP(DateOrdered) AS Date FROM orders WHERE ID = " . $this->_db_OrderID);
		return $this->_dbCmd->GetValue();
	}
	


	// You can search for a Vendor Name and it will return the Vendor Number (inside of this Project Object).  This is 1-based 1-6.
	// If the project does not have a matching vendor name... it will return 0.
	function getVendorNumberByName($vendorNameSearch){
		
		$vendorNameSearch = strtoupper(trim($vendorNameSearch));
	
		$vendorIDarr = $this->getVendorID_DB_Arr();
		
		for($i=0; $i<6; $i++){
		
			if(!isset($vendorIDarr[$i]) || empty($vendorIDarr[$i]))
				continue;
		
			$vendorName = UserControl::GetCompanyOrNameByUserID($this->_dbCmd, $vendorIDarr[$i]);
		
			if(strtoupper($vendorName) == $vendorNameSearch)
				return $i + 1;
			
		}
		
		return 0;
	}


	

	// Returns a hash of fields that are proprietory to this Sub Class
	function _getProprietoryDBfields(){
	
		$returnHash = array();

		$returnHash["OrderID"] = $this->_db_OrderID;
		$returnHash["VendorID1"] = $this->_db_VendorID1;
		$returnHash["VendorID2"] = $this->_db_VendorID2;
		$returnHash["VendorID3"] = $this->_db_VendorID3;
		$returnHash["VendorID4"] = $this->_db_VendorID4;
		$returnHash["VendorID5"] = $this->_db_VendorID5;
		$returnHash["VendorID6"] = $this->_db_VendorID6;
		$returnHash["RePrintLink"] = $this->_db_RePrintLink;
		$returnHash["RePrintReason"] = $this->_db_RePrintReason;
		$returnHash["ArtworkFile"] = $this->_db_ArtworkFile;
		$returnHash["ArtworkFileModified"] = $this->_db_ArtworkFileModified;
		$returnHash["Status"] = $this->_db_Status;
		$returnHash["CustomerSubtotal"] = $this->_db_CustomerSubtotal;
		$returnHash["VendorSubtotal1"] = $this->_db_VendorSubtotal1;
		$returnHash["VendorSubtotal2"] = $this->_db_VendorSubtotal2;
		$returnHash["VendorSubtotal3"] = $this->_db_VendorSubtotal3;
		$returnHash["VendorSubtotal4"] = $this->_db_VendorSubtotal4;
		$returnHash["VendorSubtotal5"] = $this->_db_VendorSubtotal5;
		$returnHash["VendorSubtotal6"] = $this->_db_VendorSubtotal6;
		$returnHash["CustomerTax"] = $this->_db_CustomerTax;
		$returnHash["CustomerDiscount"] = $this->_db_CustomerDiscount;
		$returnHash["NotesProduction"] = $this->_db_NotesProduction;
		$returnHash["NotesAdmin"] = $this->_db_NotesAdmin;
		$returnHash["EstShipDate"] = date("YmdHis", $this->_db_EstShipDate);
		$returnHash["EstPrintDate"] = date("YmdHis", $this->_db_EstPrintDate);
		$returnHash["EstArrivalDate"] = date("YmdHis", $this->_db_EstArrivalDate);
		$returnHash["SavedID"] = $this->_db_SavedID;
		$returnHash["Priority"] = $this->_db_Priority;
		$returnHash["CopyrightPurchase"] = $this->_db_CopyrightPurchase;
		$returnHash["ShippingPriority"] = $this->_db_ShippingPriority;
		$returnHash["ArtworkSignature"] = $this->_db_ArtworkSignature;
		$returnHash["CutOffTime"] = date("YmdHis", $this->_db_CutOffTime);
		$returnHash["VariableDataArtworkConfigOriginal"] = $this->_db_VariableDataArtworkConfigOriginal;

		
		return $returnHash;
	
	}
	



}




?>
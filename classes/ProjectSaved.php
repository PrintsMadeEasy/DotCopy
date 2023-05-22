<?

class ProjectSaved extends ProjectBase {

	private $_projectIDloadedFlag;

	// Anything after $_db_ should match the field in the Database
	
	protected $_db_UserID = 0; 
	protected $_db_ArtworkFile;  
	protected $_db_DateLastModified; 
	protected $_db_Notes;
	protected $_db_ThumbnailCheck;
	protected $_db_ArtworkCopied;
	protected $_db_ArtworkTransfered;


	//  Constructor
	function ProjectSaved(DbCmd $dbCmd){
		$this->_dbCmd = $dbCmd;
		
		$this->_projectIDloadedFlag = false;
	}
	
	// Static methods
	
	// Will return a Project Object if it finds a match of the Saved Projects notes belonging to the given user ID.
	// Will fail with an error message if a match is not found, so make sure you know the combo is good.
	// The Actual notes must begin with the prefix "TEMPLATE_"... this keeps someone from hacking into other peoples saved projects
	static function GetProjectObjFromSavedNotes(DbCmd $dbCmd, $userIDofSavedProject, $notesSearch){
	
		WebUtil::EnsureDigit($userIDofSavedProject);
	
		// $usertemplate is the UserID belonging to the master project owner
		// Now loop through all of the Saved Projects with the user ID and see if there is a matching Saved Project Description
		$dbCmd->Query("SELECT ID, Notes FROM projectssaved where UserID='$userIDofSavedProject'");
		
		// Hold a Hash of the DB results.
		$tempArray = array();
		while($row=$dbCmd->GetRow())
			$tempArray[$row["ID"]] = $row["Notes"];
	
		
		foreach($tempArray as $thisProjectID => $projectNotes){
		
			// Keep the prefix "TEMPLATE_" to keep hackers from guessing saved projects from another persons files
			if(strtoupper($projectNotes) == strtoupper("TEMPLATE_" . $notesSearch)){
				$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "saved", $thisProjectID);
				return $projectObj;
			}
		}
	
	
		WebUtil::PrintError("No template was found with the description: $notesSearch");
		exit;
	}
	
	// A Static function for creating an object of this class and returning it
	static function getObjectByProjectID(DbCmd $dbCmd, $projectID, $initializeProjectInfoObjFlag = true){
		$projectObject = new ProjectSaved($dbCmd);
		$projectObject->loadByProjectID($projectID, $initializeProjectInfoObjFlag);
		return $projectObject;
	}
	

	function getViewTypeOfProject(){
		return "saved";
	}
	
	static function CheckIfUserOwnsSavedProject(DbCmd $dbCmd, $projectID, $userID){
	
		$projectID = intval($projectID);
		
		// If the UserID has been overridden by a Administrator... then use that Domain ID instead of the Domain ID from the URL.
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		$passiveAuthObj->EnsureLoggedIn();
		
		$userIDOverride = ProjectSaved::GetSavedProjectOverrideUserID($passiveAuthObj);
		
		if($userIDOverride != $passiveAuthObj->GetUserID())
			$domainID = UserControl::getDomainIDofUser($userIDOverride);
		else
			$domainID = Domain::getDomainIDfromURL();
		
	
		$dbCmd->Query("SELECT UserID FROM projectssaved WHERE ID=$projectID AND DomainID=" . intval($domainID));
		$dbUserID = $dbCmd->GetValue();
	
		if($dbUserID != $userID)
			return false;
		else
			return true;
	}

	static function CheckIfProjectIsSaved(DbCmd $dbCmd, $projectID){
	
		WebUtil::EnsureDigit($projectID);
	
		$dbCmd->Query("SELECT SavedID FROM projectssession WHERE ID=$projectID");
		$savedID = $dbCmd->GetValue();
		
		return !(empty($savedID));
	}

	// Returns an array of Project Ordered ID's that have been ordered... and not completed/canceled.   And that are linked to the Saved Project.
	static function GetProjectOrderedIDsLinkedToSavedProject(DbCmd $dbCmd, $SavedProjectID){

		WebUtil::EnsureDigit($SavedProjectID);

		$ReturnArr = array();
		$dbCmd->Query("SELECT ID FROM projectsordered WHERE SavedID=$SavedProjectID AND Status != 'F' AND Status != 'C'");
		while($x = $dbCmd->GetValue())
			$ReturnArr[] = $x;

		return $ReturnArr;
	}

	
	
	// If we delete a Saved project... then we want to unassociate all of the links to the project within the projectssession and ordered tables.
	// If we save a project in the shopping cart, then we would want to remove the link to the Saved project
	static function ClearSavedIDLinksByViewType(DbCmd $dbCmd, $ViewType, $ProjectRecord){
	
		if($ViewType == "projectssession" || $ViewType == "session"){
	
			//Just get rid of the Saved ID from this particular session project
			$dbCmd->UpdateQuery( "projectssession", array("SavedID"=>0), "ID=$ProjectRecord" );
			
		}
		else if($ViewType == "saved"){
			// If we are dealing with a saved project, then get rid of links in both the projectsordered table and projectssession
			
			$dbCmd->UpdateQuery( "projectssession", array("SavedID"=>0), "SavedID=$ProjectRecord" );
			$dbCmd->UpdateQuery( "projectsordered", array("SavedID"=>0), "SavedID=$ProjectRecord" );
		}
		else if($ViewType == "customerservice" || $ViewType == "admin" || $ViewType == "proof" || $ViewType == "ordered"){
			//We don't need to remove any Saved ID links if the artwork is edited from within customer service
		}
		else{
			throw new Exception("Invalid View type in function ProjectSaved::ClearSavedIDLinksByViewType");
		}
	}
	
	
	// Will record into the DB that the projects saved...need a thumbnail update.
	// If the administrator is saveing changes to the proof... and it affecsts a Saved Project, we don't want to wait.  Let a cron job look in the DB instead
	static function ProjectSavedNeedsAnUpdate(DbCmd $dbCmd, $SavedID){
		
		$InsertHash["ProjectSavedID"] = intval($SavedID);
		$dbCmd->InsertQuery( "projectsavedneedsupdate", $InsertHash );
	}
	

	static function GetCountOfSavedProjects(DbCmd $dbCmd, $UserID){
	
		$UserID = intval($UserID);
		
		$dbCmd->Query("SELECT count(*) FROM projectssaved WHERE UserID=$UserID");
		return $dbCmd->GetValue();
	}

	
	
	// Call this function if we want to display the Saved Projects on behalf of another user
	static function SetSavedProjectOverride($AuthObj, $UserIDtoOverride){
	
		$UserIDtoOverride = intval($UserIDtoOverride);
		
		$dbCmd = new DbCmd();
		if(!UserControl::CheckIfUserIDexists($dbCmd, $UserIDtoOverride))
			throw new Exception("Error in saved project override. User doesn't exist.");
			
		$domainIDofUser = UserControl::getDomainIDofUser($UserIDtoOverride);
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofUser))
			throw new Exception("Can not override the UserID because the user doesn't exist.");
		
		if(!$AuthObj->CheckForPermission("SAVED_PROJECT_OVERRIDE"))
			throw new Exception("User does not have permission to override a saved Project. U$UserIDtoOverride");
			
		WebUtil::SetSessionVar("OverrideSavedProjects", $UserIDtoOverride);	
	}
	
	// Call this function if we want to display the Saved Projects on behalf of another user
	static function ClearSavedProjectOverride(){
	
		WebUtil::SetSessionVar("OverrideSavedProjects", "");	
		
	}
	
	// Returns true or false whether the Saved project UserID has been overrideen
	static function CheckForSavedProjectOverride(){
	
		$savedProjectOverrideID  = WebUtil::GetSessionVar("OverrideSavedProjects");

		if(preg_match("/^\d+$/", $savedProjectOverrideID))
			return true;
		else
			return false;
	
	}
	
	// Will return a UserID for the Saved Projects that we want to override
	// If the Session variable has not been set or the User does not have permissions... then the person's own user ID will be returned instead
	static function GetSavedProjectOverrideUserID(Authenticate $AuthObj){
	
		$savedProjectOverrideID  = WebUtil::GetSessionVar("OverrideSavedProjects");
		
		if($AuthObj->CheckForPermission("SAVED_PROJECT_OVERRIDE") && preg_match("/^\d+$/", $savedProjectOverrideID)){
			return $savedProjectOverrideID;
		}
		else{
			return $AuthObj->GetUserID();
		}
	}
	
	
	
	static function SaveProjectForUser(DbCmd $dbCmd, $ProjectRecordID, $SaveFromArea, $userID, $notes){
	
		WebUtil::EnsureDigit($ProjectRecordID);
		WebUtil::EnsureDigit($userID);

	
		if($SaveFromArea == "shoppingcart")
			$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "session", $ProjectRecordID);
		else if($SaveFromArea == "proof")
			$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "ordered", $ProjectRecordID);
		else
			throw new Exception("Illegal SaveFromArea in function call to SaveProjectForUser.");
		
		
		if($SaveFromArea == "shoppingcart"){
			//  Move all of images from the imagessession into the imagessaved
			ArtworkLib::SaveImagesInSession($dbCmd, "projectssession", $ProjectRecordID, ImageLib::GetImagesSavedTableName($dbCmd), ImageLib::GetVectorImagesSavedTableName($dbCmd));
		}
	
		
		$projectSavedObj = new ProjectSaved($dbCmd);
		
		$projectSavedObj->copyProject($projectObj);
		
		$SavedProjectID = $projectSavedObj->createNewProject($userID);

		
	
		
		if($SaveFromArea == "shoppingcart"){
			// Mark it as saved within the shopping cart
			$dbCmd->Query("UPDATE projectssession set SavedID=$SavedProjectID where ID=$ProjectRecordID");
			
			// Copy over the Thumbnail Image
			ThumbImages::CopyProjectThumbnail($dbCmd, "projectssession", $ProjectRecordID, "projectssaved", $SavedProjectID);
		
		}
		else if($SaveFromArea == "proof"){
			// Create link between the Saved Project and the order.
			$dbCmd->Query("UPDATE projectsordered SET SavedID=$SavedProjectID where ID=$ProjectRecordID");
		
			ThumbImages::CreateThumnailImage($dbCmd, $SavedProjectID, "saved");
		}
		else{
			throw new Exception("Illegal SaveFromArea in function call to SaveProjectForUser.");
		}
	
	
		return $SavedProjectID;
	}

	





	
	
	// You may not want to initialize a ProjectInfo Object(in order to increase performance).  
	// However without a ProjectInfo object intialized some of the methods will not work
	function loadByProjectID($projectID, $initializeProjectInfoObjFlag = true){

		if(!preg_match("/^\d+$/", $projectID))
			throw new Exception("The Project ID is not a digit.");
	
		$this->_dbCmd->Query("SELECT *, UNIX_TIMESTAMP(DateLastModified) AS DateModified 
					FROM projectssaved WHERE ID=$projectID");
		
		if($this->_dbCmd->GetNumRows() != 1)
			throw new Exception("The Project ID does not exist: $projectID");
		
		$row = $this->_dbCmd->GetRow();
		
		$this->_db_UserID = $row["UserID"];
		$this->_db_ArtworkFile = $row["ArtworkFile"];
		$this->_db_DateLastModified = $row["DateModified"];
		$this->_db_Notes = $row["Notes"];
		
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
		$this->_db_ThumbnailCheck = $row["ThumbnailCheck"];
		$this->_db_ArtworkCopied = $row["ArtworkCopied"];
		$this->_db_DomainID = $row["DomainID"];
		$this->_projectRecordID = $projectID;
		
		
		// Load the variable data from a separate table since it can be quite large.
		if($this->isVariableData()){
			$this->_dbCmd->Query("SELECT VariableDataFile FROM variabledatasaved WHERE ProjectID=$projectID");
			$this->_db_VariableDataFile = $this->_dbCmd->GetValue();
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
		
		$this->_fillInRemainingDBfields();

		$this->_dbCmd->UpdateQuery("projectssaved", $this->_dbHash, ("ID=" . $this->_projectRecordID));
		
		$this->updateVariableDataFile();

	}
	
	function _fillInRemainingDBfields(){
	
		// $this->_dbHash is set in the Super Class
		$this->_dbHash["UserID"] = $this->_db_UserID;
		$this->_dbHash["ArtworkFile"] = $this->_db_ArtworkFile;
		$this->_dbHash["DateLastModified"] = date("YmdHis");
		$this->_dbHash["Notes"] = $this->_db_Notes;
		$this->_dbHash["ThumbnailCheck"] = $this->_db_ThumbnailCheck;
		$this->_dbHash["ArtworkCopied"] = $this->_db_ArtworkCopied;
		$this->_dbHash["ArtworkTransfered"] = $this->_db_ArtworkTransfered;
	
	}
	
	
	// Update the variable data file within a separate table. 
	// Only call this on existing projects.
	private function updateVariableDataFile(){

		if(empty($this->_projectRecordID))
			throw new Exception("Error in method updateVariableDataFile. The Project Record has not been set.");
		
		if($this->isVariableData()){
			
			// Figure out if we need to insert a new record into the external variable data table... or update the existing record.
			$this->_dbCmd->Query("SELECT COUNT(*) FROM variabledatasaved WHERE ProjectID=" . $this->_projectRecordID);
			if($this->_dbCmd->GetValue() == 0){
				
				$varDataArr = array();
				$varDataArr["VariableDataFile"] = $this->_db_VariableDataFile;
				$varDataArr["ProjectID"] = $this->_projectRecordID;
				
				$this->_dbCmd->InsertQuery("variabledatasaved", $varDataArr);
			}
			else{
				$varDataArr = array();
				$varDataArr["VariableDataFile"] = $this->_db_VariableDataFile;
				
				$this->_dbCmd->UpdateQuery("variabledatasaved", $varDataArr, "ProjectID=" . $this->_projectRecordID);
			}
		}
	}
	
	
	// By default when a Project is loaded the Options/Prices will be merged with the current data in the Product Object
	// If you open up a project and then resave it with the method updateDatabase() ... it is possible the subtotal could change as well as the options
	// Call this method if you want the original Data put back into the database... even if prices/options have since changed
	function updateDatabaseWithRawData(){
	
		if(!$this->_projectIDloadedFlag)
			throw new Exception("You must load a Project By ID before calling this method: updateDatabaseWithRawData");
	
		$updateArr = array();
		$updateArr["UserID"] = $this->_db_UserID;
		$updateArr["ArtworkFile"] = $this->_db_ArtworkFile;
		$updateArr["DateLastModified"] = date("YmdHis", $this->_db_DateLastModified);
		$updateArr["Notes"] = $this->_db_Notes;
		$updateArr["ThumbnailCheck"] = $this->_db_ThumbnailCheck;
		$updateArr["ArtworkCopied"] = $this->_db_ArtworkCopied;
		$updateArr["ArtworkTransfered"] = $this->_db_ArtworkTransfered;
		
		
		// Common to all Subclasses
		$updateArr["OrderDescription"] = $this->_db_OrderDescription;
		$updateArr["OptionsDescription"] = $this->_db_OptionsDescription;
		$updateArr["OptionsAlias"] = $this->_db_OptionsAlias;
		$updateArr["ProductID"] = $this->_db_ProductID;
		$updateArr["Quantity"] = $this->_db_Quantity;
		$updateArr["FromTemplateID"] = $this->_db_FromTemplateID;
		$updateArr["FromTemplateArea"] = $this->_db_FromTemplateArea;
		
		$this->_dbCmd->UpdateQuery("projectssaved", $updateArr, ("ID=" . $this->_projectRecordID));
		
		$this->updateVariableDataFile();
	}

	function getNotes(){
		return $this->_db_Notes;
	}

	function getDateLastModified(){
		return $this->_db_DateLastModified;
	}
	function getArtworkFile(){
		return $this->_db_ArtworkFile;
	}
	
	
	
	// Returns True if the Artwork is a "Copy" from another project.
	// The only way to get the flag to turn back to FALSE is to save the artwork.
	function checkIfArtworkCopied(){
	
		if($this->_db_ArtworkCopied == "Y")
			return true;
		else
			return false;
	}
	function checkIfArtworkTransfered(){
	
		if($this->_db_ArtworkTransfered == "Y")
			return true;
		else
			return false;
	}
	
	
	// By Default the Artwork file will be filtered for Possibly Bad stuff
	// If you know the artwork is good then you might not want to filter it for performance reasons.
	function setArtworkFile($x, $filterArtworkFile = true){
		if($filterArtworkFile)
			$x = ArtworkInformation::FilterArtworkXMLfile($x);
		$this->_db_ArtworkFile = $x;
		
		// The artwork has been udpated, so the artwork isn't a copy any more.
		$this->_db_ArtworkCopied = "N";
		$this->_db_ArtworkTransfered = "N";
	}
	function getSavedProjectUserID(){
		return $this->_db_UserID;
	}
	
	
	function setArtworkTransferedStatus($boolFlag){
	
		if($boolFlag)
			$this->_db_ArtworkTransfered = "Y";
		else
			$this->_db_ArtworkTransfered = "N";
	
	}
	
	function setDateLastModified(){
		return $this->_db_DateLastModified = time();
	}
	function setNotes($x){
		$x = preg_replace("/(\n|\r)/", "", $x);
		if(strlen($x) >= 200)
			$x = substr($x, 0, 200);
		$this->_db_Notes = $x;
	}


	// Pass in another Project Opject and it will copy everything from it into the current object
	// Extracts Product Options, Artwork, etc.
	function copyProject($projectObj){
		
		$this->setProjectInfoObject($projectObj->getProjectInfoObject());
	
		$this->_db_CustomerSubtotal = $projectObj->getCustomerSubTotal();

		$this->setFromTemplateID($projectObj->getFromTemplateID());
		$this->setFromTemplateArea($projectObj->getFromTemplateArea());
		
		$this->_db_VariableDataStatus = $projectObj->getVariableDataStatus();
		$this->_db_VariableDataMessage = $projectObj->getVariableDataMessage();
		$this->_db_VariableDataFirstLine = $projectObj->getVariableDataFirstLine();
		$this->_db_VariableDataArtworkConfig = $projectObj->getVariableDataArtworkConfig();
		$this->_db_VariableDataFile = $projectObj->getVariableDataFile();
		$this->_db_ArtworkSides = $projectObj->getArtworkSidesCount();
		$this->_db_DomainID = $projectObj->getDomainID();
		$this->_db_ProductID = $projectObj->getProductID();

		$this->_db_ArtworkFile = $projectObj->getArtworkFile();
		
		$this->_db_ArtworkCopied = "Y";

		// Copy different compenents if we know the subclass that it is coming from
		if($projectObj->getViewTypeOfProject() == "saved"){
			$this->_db_Notes = $projectObj->getNotes();
		}
	
	}
	
	
	
	// Returns a new ProjectID that was inserted into the database
	function createNewProject($userID){
	
		$this->_ensureProjectInfoIntialized();
		
		$this->_db_UserID = intval($userID);
		
		$this->_prepareCommonDatabaseFields();
		
		$this->_fillInRemainingDBfields();
		
		$newProjectID = $this->_dbCmd->InsertQuery("projectssaved", $this->_dbHash);
		
		$this->_projectRecordID = $newProjectID;
		
		$this->updateVariableDataFile();
		
		return $newProjectID;
	}
	


}




?>
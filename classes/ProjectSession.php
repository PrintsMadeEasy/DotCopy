<?

class ProjectSession extends ProjectBase {

	private $_projectIDloadedFlag;

	// Anything after $_db_ should match the field in the Database
	
	protected $_db_SID = 0;   // Keeps track of the user's Sesion ID
	protected $_db_ArtworkFile;  
	protected $_db_DateLastModified; 
	protected $_db_CustomerSubtotal; 
	protected $_db_SavedID;
	protected $_db_ThumbnailCheck;
	protected $_db_ArtworkCopied;
	protected $_db_ArtworkTransfered;


	//  Constructor
	function ProjectSession(DbCmd $dbCmd){
		$this->_dbCmd = $dbCmd;
		
		$this->_projectIDloadedFlag = false;
	}
	
	
	// A Static function for creating an object of this class and returning it
	static function getObjectByProjectID(DbCmd $dbCmd, $projectID, $initializeProjectInfoObjFlag = true){
		$projectObject = new ProjectSession($dbCmd);
		$projectObject->loadByProjectID($projectID, $initializeProjectInfoObjFlag);
		return $projectObject;
	}
	
	// Static Function
	// This method will return a Project Session object, intialized with default values.
	// If there is a matching User ID for Artwork setup with a saved Project Note matching... it will return a copy of that project.
	// Intializes the Project Session Object with Default Artwork (could come from Artwork Setup in Saved Project for the Product).
	/*
	 * @return ProjectSession
	 */
	static function getNewDefaultProjectSessionObj(DbCmd $dbCmd, $productID){

		$productID = intval($productID);
		
		if(!Product::checkIfProductIDexists($dbCmd, $productID))
			throw new Exception("Error in method getNewDefaultProjectSessionObj.  The ProductID does not exist: " . $productID);

		$productObj = new Product($dbCmd, $productID);
		
		if($productObj->getDomainID() != Domain::oneDomain())
			throw new Exception("Can not load the Product ID for creating a new project because it is outside of the domain.");
	
		$savedProjectID = $productObj->getSavedProjectIDofInitialize();
		
		if(!empty($savedProjectID) && ProjectBase::getDomainIDofProjectRecord($dbCmd, "saved", $savedProjectID) != Domain::oneDomain())
			throw new Exception("Can not create a new projectsession because the SavedProject Initialize Belongs to another Domain.");

		if(!empty($savedProjectID)){
			
			$projectSavedObj = new ProjectSaved($dbCmd);
			
			$projectSavedObj->loadByProjectID($savedProjectID);
			
			$newProjectSessionObj = new ProjectSession($dbCmd);
			
			$newProjectSessionObj->copyProject($projectSavedObj);
			
			// Make sure that it doesn't show as "Saved for User" when they first put it in their shopping cart.
			$newProjectSessionObj->setSavedID(0);
			
			// Record where the intial artwork came from.
			$newProjectSessionObj->setFromTemplateArea("U");
			$newProjectSessionObj->setFromTemplateID($savedProjectID);
		
		}
		else{
			$newProjectSessionObj = new ProjectSession($dbCmd);
		
			$projInfObj = new ProjectInfo($dbCmd, $productID);
			$projInfObj->intializeDefaultValues();
			
			$newProjectSessionObj->setProjectInfoObject($projInfObj);
			
			// Since we can not initialize the Project... we must set the variable data file to blank.
			// Therefore there must be a data error.
			if($newProjectSessionObj->isVariableData()){
				$newProjectSessionObj->setVariableDataStatus("D");
				$newProjectSessionObj->setVariableDataMessage("Your mailing list has not been uploaded yet.");
			}
			
			// Set the intial artwork to "N"othing.  We created this project out of thin air.
			$newProjectSessionObj->setFromTemplateArea("N");
			$newProjectSessionObj->setFromTemplateID(0);

		}
		


		$newProjectSessionObj->setArtworkFile($productObj->getDefaultProductArtwork());

		return $newProjectSessionObj;
	
	}
	
	function getViewTypeOfProject(){
		return "session";
	}
	
	// You may not want to initialize a ProjectInfo Object(in order to increase performance).  
	// However without a ProjectInfo object intialized some of the methods will not work
	function loadByProjectID($projectID, $initializeProjectInfoObjFlag = true){
	
		if(!preg_match("/^\d+$/", $projectID))
			throw new Exception("The Project ID is not a digit.");
	
		$this->_dbCmd->Query("SELECT *, UNIX_TIMESTAMP(DateLastModified) AS DateModified 
					FROM projectssession WHERE ID=$projectID");
		
		if($this->_dbCmd->GetNumRows() != 1)
			throw new Exception("The Project ID does not exist: $projectID");
		
		$row = $this->_dbCmd->GetRow();
		
		$this->_db_SID = $row["SID"];
		$this->_db_ArtworkFile = $row["ArtworkFile"];
		$this->_db_DateLastModified = $row["DateModified"];
		$this->_db_CustomerSubtotal = $row["CustomerSubtotal"];
		$this->_db_SavedID = $row["SavedID"];
		
		// These private member variables are declared in the Super Class... they are in common with all type of projects
		$this->_db_OrderDescription = $row["OrderDescription"];
		$this->_db_OptionsDescription = $row["OptionsDescription"];
		$this->_db_OptionsAlias = $row["OptionsAlias"];
		$this->_db_VariableDataStatus = $row["VariableDataStatus"];
		$this->_db_VariableDataMessage = $row["VariableDataMessage"];
		$this->_db_VariableDataArtworkConfig = $row["VariableDataArtworkConfig"];
		$this->_db_VariableDataFirstLine = $row["VariableDataFirstLine"];
		$this->_db_VariableDataFile = $row["VariableDataFile"];
		$this->_db_ProductID = $row["ProductID"];
		$this->_db_Quantity = $row["Quantity"];
		$this->_db_ArtworkSides = $row["ArtworkSides"];
		$this->_db_FromTemplateID = $row["FromTemplateID"];
		$this->_db_FromTemplateArea = $row["FromTemplateArea"];
		$this->_db_ThumbnailCheck = $row["ThumbnailCheck"];
		$this->_db_ArtworkCopied = $row["ArtworkCopied"];
		$this->_db_ArtworkTransfered = $row["ArtworkTransfered"];
		$this->_db_DomainID = $row["DomainID"];
		$this->_projectRecordID = $projectID;
		
		if($initializeProjectInfoObjFlag){
			// Create a new ProjectInfo Object based on the Product ID
			$this->_projectInfoObj = new ProjectInfo($this->_dbCmd, $this->_db_ProductID);
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
		
		if(empty($this->_db_SID))
			throw new Exception("The session ID is missing.");
		
		$this->_prepareCommonDatabaseFields();
		
		$this->_fillInRemainingDBfields();

		$this->_dbCmd->UpdateQuery("projectssession", $this->_dbHash, ("ID=" . $this->_projectRecordID));

	}
	
	
	private function _fillInRemainingDBfields(){
	
		// $this->_dbHash is set in the Super Class
		
		$this->_dbHash["SID"] = $this->_db_SID;
		$this->_dbHash["ArtworkFile"] = $this->_db_ArtworkFile;
		$this->_dbHash["DateLastModified"] = date("YmdHis");
		$this->_dbHash["SavedID"] = $this->_db_SavedID;
		$this->_dbHash["CustomerSubtotal"] = $this->_projectInfoObj->getCustomerSubTotal();
		$this->_dbHash["ThumbnailCheck"] = $this->_db_ThumbnailCheck;
		$this->_dbHash["ArtworkCopied"] = $this->_db_ArtworkCopied;
		$this->_dbHash["ArtworkTransfered"] = $this->_db_ArtworkTransfered;
		
		// The Project Session is the only table that stores the variable data inside of the same container.
		// Saved and Ordered projects stored the varaible data files in another table, since they can grow to be quite large.
		$this->_dbHash["VariableDataFile"] = $this->_db_VariableDataFile;
	
	}
	
	// By default when a Project is loaded the Options/Prices will be merged with the current data in the Product Object
	// If you open up a project and then resave it with the method updateDatabase() ... it is possible the subtotal could change as well as the options
	// Call this method if you want the original Data put back into the database... even if prices/options have since changed
	function updateDatabaseWithRawData(){
	
		if(!$this->_projectIDloadedFlag)
			throw new Exception("You must load a Project By ID before calling this method: updateDatabaseWithRawData");
	
		$updateArr = array();
		$updateArr["SID"] = $this->_db_SID;
		$updateArr["ArtworkFile"] = $this->_db_ArtworkFile;
		$updateArr["DateLastModified"] = date("YmdHis", $this->_db_DateLastModified);
		$updateArr["CustomerSubtotal"] = $this->_db_CustomerSubtotal;
		$updateArr["SavedID"] = $this->_db_SavedID;
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
		
		$this->_dbCmd->UpdateQuery("projectssession", $updateArr, ("ID=" . $this->_projectRecordID));

	}

	
	function getArtworkFile(){
		return $this->_db_ArtworkFile;
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
	
	
	function setArtworkTransferedStatus($boolFlag){
	
		if($boolFlag)
			$this->_db_ArtworkTransfered = "Y";
		else
			$this->_db_ArtworkTransfered = "N";
	
	}
	

	function getSavedID(){
		return $this->_db_SavedID;
	}
	function getDateLastModified(){
		return $this->_db_DateLastModified;
	}
	
	function getSessionID(){
		return $this->_db_SID;
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
	

	// Gets data directly from the DB field, rather than going through the ProjectInfo Object
	function getCustomerSubtotal_DB(){
		return $this->_db_CustomerSubtotal;
	}
	
	
	// Returns a new ProjectID that was inserted into the database
	function createNewProject($user_sessionID){

		$this->_ensureProjectInfoIntialized();
		
		$this->_db_SID = $user_sessionID;
		
		$this->_prepareCommonDatabaseFields();
		
		$this->_fillInRemainingDBfields();
		
		// This is really the only place that a Domain ID is acutally "set"
		// From here on... this Domain ID will be copied into "Saved Projects" and "Projects Ordered".
		// There is no mechanism to create a ProjectSaved or ProjectOrdered from scratch.
		$this->_dbHash["DomainID"] = Domain::getDomainIDfromURL();
		
		
		return $this->_dbCmd->InsertQuery("projectssession", $this->_dbHash);
	}
	
	function setSessionID($x){
		if(strlen($x) > 32)
			throw new Exception("The session ID is out of range");
		if(empty($x))
			throw new Exception("The session ID is missing");
		
		$this->_db_SID = $x;
	}

	function setSavedID($x){
		if(!preg_match("/^\d+$/", $x))
			throw new Exception("The Saved ID is missing or incorrect.");
		
		$this->_db_SavedID = $x;
	}
	
	function setDateLastModified(){
		return $this->_db_DateLastModified = time();
	}



	// Pass in another Project Opject and it will copy everything from it into the current object
	// Extracts Product Options, Artwork, etc.
	function copyProject(ProjectBase $projectObj){
	
		$this->setProjectInfoObject($projectObj->getProjectInfoObject());
	
		$this->_db_DateLastModified = time();
		$this->_db_ArtworkFile = $projectObj->getArtworkFile();
		$this->_db_CustomerSubtotal = $projectObj->getCustomerSubTotal();

		$this->setFromTemplateID($projectObj->getFromTemplateID());
		$this->setFromTemplateArea($projectObj->getFromTemplateArea());

		$this->_db_VariableDataStatus = $projectObj->getVariableDataStatus();
		$this->_db_VariableDataMessage = $projectObj->getVariableDataMessage();
		$this->_db_VariableDataArtworkConfig = $projectObj->getVariableDataArtworkConfig();
		$this->_db_VariableDataFirstLine = $projectObj->getVariableDataFirstLine();
		$this->_db_VariableDataFile = $projectObj->getVariableDataFile();
		$this->_db_ProductID = $projectObj->getProductID();
		
		$this->_db_ArtworkSides = $projectObj->getArtworkSidesCount();
		$this->_db_DomainID = $projectObj->getDomainID();
		
		// If we are copying a Saved Project, then move over the SavedID so we know they are linked.
		if($projectObj->getViewTypeOfProject() == "saved"){
			$this->_db_SavedID = $projectObj->getProjectID();

			// If we are copying from a Saved Project... we don't want to show a Copy has been made.  This is probably moving a Saved Project into the shopping cart.
			// We only want to show the Copy Flag when we are going the Session - Session copy
			$this->_db_ArtworkCopied = "N";
		}
		else if($projectObj->getViewTypeOfProject() == "ordered"){
			$this->_db_SavedID = $projectObj->getSavedID();
			
			// If we are copying from an order... we don't keep thumbnails with orders... and chances are they want to put it in their shopping cart.  So don't show them a "Copied" icon.
			$this->_db_ArtworkCopied = "N";
		}
		else if($projectObj->getViewTypeOfProject() == "session"){
			$this->_db_SavedID = $projectObj->getSavedID();
			
			// In this case, we do want to show a "Copy" notification... Session to Session copy.
			$this->_db_ArtworkCopied = "Y";
		}
		else
			throw new Exception("There is no definition for copying a project from this Subclass yet");
	}
	
	
	
	// returns true or false
	static function CheckIfUserOwnsProjectSession(DbCmd $dbCmd, $projectID){
	
		WebUtil::EnsureDigit($projectID);
	
		$dbCmd->Query("SELECT SID,DomainID FROM projectssession WHERE ID=$projectID");
		
		if($dbCmd->GetNumRows() == 0)
			return false;

		$row = $dbCmd->GetRow();

		$dbSID = $row["SID"];
		$projectDomainID = $row["DomainID"];
	
		// The only people that can see project sessions... 
		// ...are when the Domain from the URL in the browser matches the Domain ID in which the Project was saved.
		// This goes for Admins too.
		$domainIDfromURL = Domain::getDomainIDfromURL();		

		if($domainIDfromURL != $projectDomainID)
			return false;
	
		$user_sessionID = WebUtil::GetSessionID();
	
		if($dbSID != $user_sessionID){
			$visitorPathObj = new VisitorPath();
			$previousSessionIDsWithDatesArr = $visitorPathObj->getPreviousSessionIDsWithDates($user_sessionID);
			$previousSessionIDsArr = array_keys($previousSessionIDsWithDatesArr);
			
			if(in_array($user_sessionID, $previousSessionIDsArr)){
				WebUtil::WebmasterError("Project ID: $projectID was validated from a previous SessionID chained to this SID: " . $user_sessionID . " \nThe URL being accessed is: " . $_SERVER['REQUEST_URI'] . "\nIP Address: " . WebUtil::getRemoteAddressIp(), "Project Re-Authenticated");
				return true;
			}
			
			WebUtil::WebmasterError("Project ID: $projectID was not authenticated SID: " . $user_sessionID . " \nThe URL being accessed is: " . $_SERVER['REQUEST_URI'] . "\nIP Address: " . WebUtil::getRemoteAddressIp(), "Project Not Authorized");
			
			return false;
		}
	

		
		return true;
	
	}
	
	// Pass in a Product ID and a UserSession ID.
	// This function will create a new Project Session with default options and return its ID.
	static function CreateNewDefaultProjectSession(DbCmd $dbCmd, $prodid, $user_sessionID){
	
		// Creates a new Project Session object with Default values.  However, it does not save it to the Database.
		$newProjectSessionObj = ProjectSession::getNewDefaultProjectSessionObj($dbCmd, $prodid);
		
		$productObj = new Product($dbCmd, $prodid);
		if($productObj->isVariableData()){
			$newProjectSessionObj->setVariableDataStatus("L"); // Login Error
			$newProjectSessionObj->setVariableDataMessage("You must be signed in before you can configure variable images.  Please sign in or register for an account.");
		}

		$newProjectID = $newProjectSessionObj->createNewProject($user_sessionID);
		
		// For "free business card" customers... make sure that they have a default quantity of 100
		if($prodid == 73){
			$checkoutParamsObj = new CheckoutParameters();
			
			if(strtoupper($checkoutParamsObj->GetCouponCode()) == "FIRSTONEFREE"){
				
				// Default to the lowest shipipng method.
				$shippingChoicesObj = new ShippingChoices(1);
				$shippingChoicesArr = array_keys($shippingChoicesObj->getActiveShippingChoices());
				
				$checkoutParamsObj->SetShippingChoiceID(array_pop($shippingChoicesArr));
			
				$projectObj = new ProjectSession($dbCmd);
				$projectObj->loadByProjectID($newProjectID, true);
				$projectObj->setQuantity(100);
				$projectObj->updateDatabase();
			}
		}
	

		// Now the that the project is saved... we can let the Variable Data class set the Variable Data status on the project, depending intial data files and config.
		if($newProjectSessionObj->isVariableData())
			VariableDataProcess::SetVariableDataStatusForProject($dbCmd, $newProjectID, "session");

	
		return $newProjectID;
	}
	
	

	
	
	


}




?>
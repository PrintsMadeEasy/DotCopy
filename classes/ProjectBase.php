<?


// This is the Base Class that is inherited by ProjectSession, ProjectSaved, ProjectOrdered, etc.
class ProjectBase {

	protected $_projectInfoClassInitializedFlag = false;
	protected $_projectInfoObj;
	protected $_projectRecordID = 0; // A record corresponding to an ID in the DB.
	
	// Everything after the $_db_ should match a field in the databases
	// Subclasses have their own special fields, but should follow this format
	// These are just the fields in common with Projects in all SubClasses
	protected $_db_FromTemplateID = 0;   // TemplateID and TemplateArea indicate what template the user chose.
	protected $_db_FromTemplateArea = "N"; // If is came from the category selection or search engine, or "N"othing is the default or "S"earch Engine, "C"ategory, "U"ser Template
	protected $_db_ProductID;
	protected $_db_Quantity;
	protected $_db_OrderDescription;
	protected $_db_OptionsDescription;
	protected $_db_OptionsAlias;
	protected $_db_VariableDataStatus;
	protected $_db_VariableDataMessage;
	protected $_db_VariableDataArtworkConfig;
	protected $_db_VariableDataFirstLine;
	protected $_db_VariableDataFile;
	protected $_db_ArtworkSides;
	protected $_db_DomainID;
	
	protected $is_variableDataFlag_Cache;


	protected $_dbCmd;

	// The hash that will be passed to the database Object for saving
	protected $_dbHash = array();
	

	//  Constructor 
	function ProjectBase(){

	}
	
	/**
	 * Creating an object of the derived class and returns it
	 * @return ProjectBase
	 */
	static function getObjectByProjectID(DbCmd $dbCmd, $projectID, $initializeProjectInfoObjFlag = true){
		print $initializeProjectInfoObjFlag; // to avoid IDE compiler warning.
		throw new Exception("You Must override this method: getObjectByProjectID");
	}
	
	
	
	// Will Print an error if the given Project ID does not belong to the person viewing the script.
	// It also protects against Cross Domain hacking by administrators.
	// This also works for protecting the Admin access to the tempalte system from cross domain hacking by other Admins.
	static function EnsurePrivilagesForProject(DbCmd $dbCmd, $view, $projectRecord){
	
		WebUtil::EnsureDigit($projectRecord);
	
		if($view == "session" || $view == "projectssession"){
			if(!ProjectSession::CheckIfUserOwnsProjectSession($dbCmd, $projectRecord))
				throw new ExceptionPermissionDenied("It appears this project is not available anymore.  Your session may have expired.");
			
		}
		else if(in_array($view, array("template_category", "template_searchengine", "ordered", "projectsordered", "customerservice", "admin", "proof"))){
	
			$passiveAuthObj = Authenticate::getPassiveAuthObject();
			
			if(!$passiveAuthObj->CheckIfLoggedIn())
				throw new ExceptionPermissionDenied("Please sign into your account before visiting this page.");
				
			if(!$passiveAuthObj->CheckIfUserIDisMember($passiveAuthObj->GetUserID())){
			
				if($view == "template_category" || $view == "template_searchengine" )
					throw new Exception("Template administration requires special privelages.");
				
				// Regular users must have UserIDs matching identical to what is in the DB.
				if(!ProjectOrdered::CheckIfUserOwnsOrderedProject($dbCmd, $projectRecord, $passiveAuthObj->GetUserID()))
					throw new ExceptionPermissionDenied("It appears this project is not available anymore.  Your session may have expired.");	
			}
			else{
				// Will get the Domain ID of either Tempaltes, or Projects.
				$domainID = self::getDomainIDofProjectRecord($dbCmd, $view, $projectRecord);
				
				if(empty($domainID) || !$passiveAuthObj->CheckIfUserCanViewDomainID($domainID))
					throw new ExceptionPermissionDenied("It appears this project is not available anymore.  Your session may have expired.");
			}
			
		}
		else if($view == "saved"){
	
			$AuthObj = new Authenticate(Authenticate::login_general);
	
			//The user ID that we want to use for the Saved Project might belong to somebody else;
			$UserID = ProjectSaved::GetSavedProjectOverrideUserID($AuthObj);
	
			if(!ProjectSaved::CheckIfUserOwnsSavedProject($dbCmd, $projectRecord, $UserID))
				throw new ExceptionPermissionDenied("It appears this project is not available anymore.  Your session may have expired.");
		}
		else{
			throw new Exception("Error View Type in function call ProjectBase::EnsurePrivilagesForProject: $view");
		}
	
	}


	
	// Returns the Product ID associated with the given project record and view
	static function GetProductIDFromProjectRecord(DbCmd $dbCmd, $ProjectRecord, $View){
	
		$ProjectRecord = intval($ProjectRecord);
		
		if($View == "saved" || $View == "projectssaved"){
			$DBtable = "projectssaved";
			$Identify = "ID";
		}
		else if($View == "admin" || $View == "proof" || $View == "ordered"){
			$DBtable = "projectsordered";
			$Identify = "ID";
		}
		else if($View == "projectssession" || $View == "session"){
			$DBtable = "projectssession";
			$Identify = "ID";
		}
		else if($View == "template_category"){
			$DBtable = "artworkstemplates";
			$Identify = "ArtworkID";
		}
		else if($View == "template_searchengine"){
			$DBtable = "artworksearchengine";
			$Identify = "ID";
		}
		else{
			print "Error with ViewType in function ProjectBase::GetProductIDFromProjectRecord";
			exit;
		}
		
	
		$dbCmd->Query("SELECT ProductID FROM $DBtable WHERE $Identify = $ProjectRecord");
		$productID = $dbCmd->GetValue();
		
		if(empty($productID))
			throw new Exception("Empty ProductID found in function call ProjectBase::GetProductIDFromProjectRecord : Table: $DBtable : Record : $ProjectRecord ");
	
		return $productID;
	}
		
	// This function will dynamically load Product Specific function (if they exists) 
	// It will update the artwork file based upon how the product options are set
	static function ChangeArtworkBasedOnProjectOptions(DbCmd $dbCmd, $projectrecord, $view){

		$projectObj = ProjectBase::getprojectObjectByViewType($dbCmd, $view, $projectrecord);

		$artsigBefore = md5($projectObj->getArtworkFile());
		
		// Add or remove sides from the artwork based upon the selected options.
		$projectObj->changeArtworkSidesBasedOnProjectOptions();

		$artsigAfter = md5($projectObj->getArtworkFile());

		// Update the Database only if the artwork was changed based upon the options
		if($artsigBefore != $artsigAfter){

			// If there are Saved Projects links between live orders or that in shopping carts... then break the links since the artwork is different now.
			if($view == "projectssession" || $view == "session" || $view == "saved")
				ProjectSaved::ClearSavedIDLinksByViewType($dbCmd, $view, $projectrecord);
		}

		// Based upon the options & corresponding choices of the product... we may be doing certain search and replace routines.	
		$projectObj->searchReplaceArtworkBasedOnProductOptions();
		
		$artsigAfterSearchReplace = md5($projectObj->getArtworkFile());
		
		if($artsigAfterSearchReplace != $artsigAfter || $artsigBefore != $artsigAfter)
			$projectObj->updateDatabase();

		// Copy over the Artwork to the corresponding SavedProject ID... if the link exists
		if($view == "admin" || $view == "proof" || $view == "projectsordered" || $view == "customerservice"){
			
			// This may require a thumbnail image update, so we need to be not to call this unless necessary.
			if($artsigAfterSearchReplace != $artsigAfter || $artsigBefore != $artsigAfter)
				ProjectOrdered::CloneOrderForSavedProject($dbCmd, $projectrecord);
		}
	}

	// Let's us know if the user is not using any vector text on their artwork.  
	// In the future we may had a field to the Product Database to say whether we should check certain products.
	// ... For example, this is an important check on business cards, but it may not be so important to check on a large poster.
	static function checkForEmptyTextWarning($dbCmd, $projectrecord, $view){
		
		$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $view, $projectrecord, FALSE);
		$artObj = new ArtworkInformation($projectObj->getArtworkFile());
		
		return $artObj->checkForEmptyTextOnNonVectorArtwork();
	}

	// Define which view types define what sub-classed Project Types.
	// We haven't been structured in older code so there are extra view types that mean the same thing
	// We are trying to depcreciate to only 4 choices... "admin", "ordered", "saved", "session"

	// Returns an Array of View Types for the Projects Ordered Class
	static function arrayProjectOrderedViewTypes(){
		return array("admin", "proof", "ordered", "projectsordered", "customerservice");
	}
	// Returns an Array of View Types for the Projects Session Class
	static function arrayProjectSessionViewTypes(){
		return array("session", "projectssession");
	}
	// Returns an Array of View Types for the Projects Session Class
	static function arrayProjectSavedViewTypes(){
		return array("saved", "projectssaved");
	}
	
	// Return the table name based upon a view type... and complains upon an error.
	static function getTableNameByViewType($viewType){
	
		if(in_array($viewType, ProjectBase::arrayProjectOrderedViewTypes()))
			return "projectsordered";
		else if(in_array($viewType, ProjectBase::arrayProjectSavedViewTypes()))
			return "projectssaved";
		else if(in_array($viewType, ProjectBase::arrayProjectSessionViewTypes()))
			return "projectssession";
		else
			throw new Exception("Illegal view type in method getTableNameByViewType... View: " . $viewType);	
	}
	
	// Method for creating an object of the appropriate subclass based upon a view type
	// Set ProjectInfoFlag to false if you want a Quicker start up.  You might not need the Product Object and other options parsed
	/**
	 * @return ProjectBase
	 */
	static function getProjectObjectByViewType(DbCmd $dbCmd, $viewType, $projectID, $initializeProjectInfoObjFlag = true){
		

		if(in_array($viewType, ProjectBase::arrayProjectOrderedViewTypes()))
			return ProjectOrdered::getObjectByProjectID($dbCmd, $projectID, $initializeProjectInfoObjFlag);
		else if(in_array($viewType, ProjectBase::arrayProjectSavedViewTypes()))
			return ProjectSaved::getObjectByProjectID($dbCmd, $projectID, $initializeProjectInfoObjFlag);
		else if(in_array($viewType, ProjectBase::arrayProjectSessionViewTypes()))
			return ProjectSession::getObjectByProjectID($dbCmd, $projectID, $initializeProjectInfoObjFlag);
		else
			throw new Exception("Illegal view type in method getProjectObjectByViewType... View: " . $viewType);	
	}
	
	
	// Method for getting the Quantity of a Project.  The performace is a lot faster than creating a new Project Object
	static function getProjectQuantity(DbCmd $dbCmd, $viewType, $projectID){
	
		WebUtil::EnsureDigit($projectID, true, "Error in method ProjectBase::getProjectQuantity ... Project ID must be a digit.");
		
		$dbCmd->Query("SELECT quantity FROM " . ProjectBase::getTableNameByViewType($viewType) . " WHERE ID=" . $projectID);
		
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Error in Method ProjectBase::getProjectQuantity.  The ProjectID does not exist: $projectID");
		
		return $dbCmd->GetValue();
	}
	
	// Returns NULL if the Project doesn't exist.
	static function getDomainIDofProjectRecord(DbCmd $dbCmd, $viewType, $projectID){
		
		// For Templates, we have to corrlate Domains by Product IDs
		if($viewType == "template_category" || $viewType == "template_searchengine"){
			$productid = ProjectBase::GetProductIDFromProjectRecord($dbCmd, $projectID, $viewType);
			return Product::getDomainIDfromProductID($dbCmd, $productid);
		}
		else{
			$dbCmd->Query("SELECT DomainID FROM " . ProjectBase::getTableNameByViewType($viewType) . " WHERE ID=" . intval($projectID));
			
			return $dbCmd->GetValue();	
		}
	}
	
	
	// Method for getting the Quantity of a Project.  The performace is a lot faster than creating a new Project Object
	static function getProductIDquick(DbCmd $dbCmd, $viewType, $projectID){
	
		WebUtil::EnsureDigit($projectID, true, "Error in method ProjectBase::getProductIDquick ... Project ID must be a digit.");
		
		$dbCmd->Query("SELECT ProductID FROM " . ProjectBase::getTableNameByViewType($viewType) . " WHERE ID=" . $projectID);
		
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Error in Method ProjectBase::getProductIDquick.  The ProjecID does not exist: $projectID");
		
		return $dbCmd->GetValue();
	}
	
	
	
	// Call this function right before displaying the editing tool
	// Some Products May have Shapes that are meant to lay on top of the canvas
	// For Example and Envelope may have an option for a Window and we want to draw a rectangle where it will be punched out
	// Or maybe a letter will have hole-punches or something like that.
	// The script draw_shapes_layer.php will automatically be called by the editor (which generates a Flash Movie Clip) on the fly
	// The movie clip will be attached to every product regardless if it has shapes on it or not.  Sometimes it will just be an empty movie clip
	// We could generate a new ArtwortInfoObj from the Project Object, but that would be less performance (if we already have an ArtworkObj created).
	static function SetShapesObjectsInSessionVar(DbCmd $dbCmd, ProjectBase $projectObj, &$ArtworkInfoObj){
		
	
		$PDFprofileObj = new PDFprofile($dbCmd, $projectObj->getProductID(), $projectObj->getOptionsAliasStr());
		
		// If for some reason the Proof profile name does not exist for this profile, then just return without doing anything... but that should never happen.
		if(!$PDFprofileObj->checkIfPDFprofileExists("Proof"))
			return;
			
	
		$PDFprofileObj->loadProfileByName("Proof");
	
		$shapesContainerObj = $PDFprofileObj->getLocalShapeContainer();
		
		// We want to set the Width/Height of the Canvas Area for each side of our artwork
		$sideCounter = 0;
	
		foreach($ArtworkInfoObj->SideItemsArray as $thisSideObj){
			$shapesContainerObj->setCanvasWidth($sideCounter, $thisSideObj->contentwidth);
			$shapesContainerObj->setCanvasHeight($sideCounter, $thisSideObj->contentheight);
		
			$sideCounter++;
		}
		
		WebUtil::SetSessionVar("ShapeContainerSesssionVar", serialize($shapesContainerObj));
	
	}
	
	
	
	
	// If a project Object was serialized and then unserialized the Database connection object will be destroyed
	// You can reset the Object with this method.  
	function refreshDatabaseObject(DbCmd $dbCmd){
		$this->_dbCmd = $dbCmd;
	}
	

	// Returns a Clone of this Object.
	// The serialize/unserialize is needed to do a Deep Copy, since the Project Info Object inside is copied by reference.
	function cloneThisProjectObject(){

		$newProjectObj = unserialize(serialize($this));
		$newProjectObj->refreshDatabaseObject($this->_dbCmd);
		return $newProjectObj;
	}

	
	// This method will convert this project Object (which will be a subclass of this class) into another Product ID and return a Copy of a new ProjectObj
	// Will convert the Artwork and deal with any transitions between Variable Data and Not Variable data
	// If you are using a ProjectSaved then you get back ProjectSaved, etc.
	// Returns a new ProjectObj (by copy)
	function convertToProductID($newProductID){

		if(!$this->_projectInfoObj->initialized() || empty($this->_projectRecordID))
			throw new Exception("You can not call the method convertToProductID unless the object has been intialized");
				
		WebUtil::EnsureDigit($newProductID);
		
		// Make a copy of this Object.
		$newProjectObj = $this->cloneThisProjectObject();
		
		// Get a new Project Info Object based upon the new Product ID
		$newProjectInfoObj = new ProjectInfo($this->_dbCmd, $newProductID);
		$newProjectInfoObj->intializeDefaultValues();
		$newProjectObj->setProjectInfoObject($newProjectInfoObj);
		
		// Find out if any of the Product Options Match... if so, then keep the same options.
		// For Example... converting a Double-Sided Envelopes (with window) to a Single-Sided Envelope should keep the windows.
		$selectedOptionsFromOldProject = $this->getOptionsAndSelections();
		$newProjectOptions = $newProjectInfoObj->getProductOptionsArray();
		
		foreach($selectedOptionsFromOldProject as $oldOptionName => $oldOptionChoice){
		
			// Skip over Options that do not exist in the New Project
			if(!isset($newProjectOptions["$oldOptionName"]))
				continue;
			
			// Verify that the Choice is available for the given option before setting it.
			if(in_array($oldOptionChoice, $newProjectOptions["$oldOptionName"]))
				$newProjectObj->setOptionChoice($oldOptionName, $oldOptionChoice);
		}
		
		
		// If the Old project had Variable Data and the new one does not... we need to clean out the fields for the new one.
		// If the new Project has variable data and the old one does not, then we need to set default Status, etc.
		// If both projects are variable data, then we can just transfer over the Data, configurations, and status without a problem.
		if($this->isVariableData() && !$newProjectObj->isVariableData()){
	
			$newProjectObj->_db_VariableDataStatus = null;
			$newProjectObj->_db_VariableDataMessage = null;
			$newProjectObj->_db_VariableDataArtworkConfig = null;
			$newProjectObj->_db_VariableDataFirstLine = null;
			$newProjectObj->_db_VariableDataFile = null;

			// The Subclass for Projects Ordered have extra properties for Variable Data files which keep track of the original
			if($newProjectObj->getViewTypeOfProject() == "ordered"){
				$newProjectObj->_db_VariableDataArtworkConfigOriginal = null;
				$newProjectObj->_db_VariableDataFileOriginal = null;
			}

		}
		else if(!$this->isVariableData() && $newProjectObj->isVariableData()){

			// In case there is a Saved Project initialize... use data from that for the Initial Varaible Data.
			$defaultProjectObj = ProjectSession::getNewDefaultProjectSessionObj($this->_dbCmd, $newProjectObj->getProductID());

			// Start out with a Data Error and no quantity yet.
			$newProjectObj->setQuantity($defaultProjectObj->getQuantity());
			$newProjectObj->_db_VariableDataStatus = $defaultProjectObj->getVariableDataStatus();  	
			$newProjectObj->_db_VariableDataMessage = $defaultProjectObj->getVariableDataMessage();
			$newProjectObj->_db_VariableDataArtworkConfig = $defaultProjectObj->getVariableDataArtworkConfig();
			$newProjectObj->_db_VariableDataFirstLine = $defaultProjectObj->getVariableDataFirstLine();
			$newProjectObj->_db_VariableDataFile = $defaultProjectObj->getVariableDataFile();
		}
		else if($this->isVariableData() && $newProjectObj->isVariableData()){
			// If both projects are variable data then the data file would have been transfered
			// In this case, make sure the quantities also match.
			$newProjectObj->setQuantity($this->getQuantity());
		}

		// If the Product is in the Same Family of Products (by looking at the shared Production ID)
		// Then we want to transfer the Backsides across (if there is one).
		// We don't want to transfer the Backside of a Business Card to the Back of a Postcard or Envelope though.
		if(Product::checkIfProductsShareTemplateCollection($this->_dbCmd, $this->getProductID(), $newProductID) || Product::checkIfProductsShareProduction($this->_dbCmd, $this->getProductID(), $newProductID) || strtoupper($this->getProductTitle()) == strtoupper($newProjectObj->getProductTitle()))
			$keepBacksideFlag = true;
		else
			$keepBacksideFlag = false;

		
		// Will take care of resizing and repositioning based upon the changes to the Canvas Width/Height
		$artworkConversionObj = new ArtworkConversion($this->_dbCmd);
		$artworkConversionObj->setFromArtwork($this->getProductID(), $this->getArtworkFile());
		$artworkConversionObj->setToArtwork($newProductID);
		
		if($keepBacksideFlag)
			$artworkConversionObj->removeBacksideFlag(false);
		else
			$artworkConversionObj->removeBacksideFlag(true);

		$newArtworkFile = $artworkConversionObj->getConvertedArtwork();
		$newProjectObj->setArtworkFile($newArtworkFile);
		
		
		// Perform operations specific to subclasses
		if($this->getViewTypeOfProject() == "saved"){
			$newProjectObj->setDateLastModified();
			$newProjectObj->setNotes("Artwork Copied From " . $this->getProductTitle());
		}
		else if($this->getViewTypeOfProject() == "session"){
		
			$newProjectObj->setDateLastModified();
			
			// If the old Project was linked to a Saved Project, the new one should not be.
			$newProjectObj->setSavedID(0);
		}
		else if($this->getViewTypeOfProject() == "ordered"){
		
			// We must also convert the Artwork for the Original Artwork.
			// This is not too harmful... since if you Revert ProductID's the Original Artwork should get restored identically through the same conversion process
			$artworkConversionObj->setFromArtwork($this->getProductID(), $this->getOriginalArtworkFile());
			$originalArtworkConverted = $artworkConversionObj->getConvertedArtwork();
			$newProjectObj->setOriginalArtworkFile($originalArtworkConverted);
			
			// Destroy any Saved Project links
			$newProjectObj->setSavedID(0);
			
			// Make sure the Artwork Signature is Updated.
			$newProjectObj->resetArtworkSignature();
		}
		else{
			throw new Exception("Illegal View type in method convertToProductID");
		}
		
		return $newProjectObj;	
	}
	
	
	// The subclasses must define a "view type" of the object...  Can be "ordered", "saved", "session" at the moment.
	function getViewTypeOfProject(){
		
		throw new Exception("You Must override this method: getViewTypeOfProject");
	}
	
	// This method should be extended by the Sub class and take care of inserting a new project into the Database
	// It should return the new Project ID
	function createNewProject(){
		throw new Exception("You Must override this method: createNewProject");
	}

	// You can either Set the Project Info Object manually, or you can load ProjectInfo by ProjectRecordID
	// Loading by the Project ID should be overridden by one of the sub classes
	function setProjectInfoObject(ProjectInfo $projInfObj){
	
		if(!$projInfObj->initialized())
			throw new Exception("You can not set an ProjectInfo Object unless it has already been intialized");
	
		$this->_projectInfoObj = $projInfObj;
		$this->_projectInfoClassInitializedFlag = true;
		
		$this->_db_ProductID = $projInfObj->getProductID();
	}
	

	/**
	 * Returns a Product Object belonging to the Object of this SubClass
	 *
	 * @return Product
	 */
	function getProductObj(){
		return $this->_projectInfoObj->getProductObj();
	}

	// You may not want to initialize a ProjectInfo Object(in order to increase performance).  
	// However without a ProjectInfo object intialized some of the methods will not work
	function loadByProjectID($projectID, $initializeProjectInfoObjFlag = true){
	
		print $initializeProjectInfoObjFlag; // Just to keep the IDE from giving a warning.
		throw new Exception("You Must override this method: loadByProjectID");
	}
	

	
	// Should only be used when editing an existing project
	// Don't use this method in any Sub-Classes when "Making a Copy" or "Creating New Project"
	// Be sure to read the notes on the method updateDatabaseWithRawData()
	function updateDatabase(){
	
		throw new Exception("You Must override this method: updateDatabase.");
	}
	
	
	// By default when a Project is loaded the Options/Prices will be merged with the current data in the Product Object
	// If you open up a project and then resave it with the method updateDatabase() ... it is possible the subtotal could change as well as the options
	// Call this method if you want the original Data put back into the database... even if prices/options have since changed
	function updateDatabaseWithRawData(){
	
		throw new Exception("You Must override this method: updateDatabaseWithRawData.");
	}
	
	function getProjectID(){
		if(empty($this->_projectRecordID))
			throw new Exception("No ProjectID has been defined yet in the method getProjectID");
		return $this->_projectRecordID;
	}
	
	
	// Getting the Artwork file may be different depending on the type of subclass that extends ProjectBase
	// On projects that have been ordered we always keep an Original Version of the artwork file and save any changes to a Modified Artwork field
	function getArtworkFile(){
		throw new Exception("You Must override this method: getArtworkFile.");
	}
	function setArtworkFile(){
		throw new Exception("You Must override this method: setArtworkFile.");
	}
	
	
	
	// Will copy details from another Project into this object
	function copyProject($projectObj){
		throw new Exception("You Must override this method: copyProject.");
	}


	// Basically any pre-processing, common to the sub-classes, before a database save is made
	protected function _prepareCommonDatabaseFields(){
		
		$this->_ensureProjectInfoIntialized();
		
		if($this->isVariableData()){
			if(empty($this->_db_VariableDataStatus))
				throw new Exception("You must define a variable data status for this product before saving to the DB.");
		}
			
		$this->_dbHash["ProductID"] = $this->_projectInfoObj->getProductID();
		$this->_dbHash["Quantity"] = $this->_projectInfoObj->getQuantity();
		$this->_dbHash["OrderDescription"] = $this->_projectInfoObj->getOrderDescription();
		$this->_dbHash["OptionsDescription"] = $this->_projectInfoObj->getOptionsDescription();
		$this->_dbHash["OptionsAlias"] = $this->_projectInfoObj->getOptionsAliasStr();
		$this->_dbHash["VariableDataStatus"] = $this->_db_VariableDataStatus;
		$this->_dbHash["VariableDataMessage"] = $this->_db_VariableDataMessage;
		$this->_dbHash["VariableDataArtworkConfig"] = $this->_db_VariableDataArtworkConfig;
		$this->_dbHash["VariableDataFirstLine"] = $this->_db_VariableDataFirstLine;
		$this->_dbHash["FromTemplateID"] = $this->_db_FromTemplateID;
		$this->_dbHash["FromTemplateArea"] = $this->_db_FromTemplateArea;
		$this->_dbHash["ArtworkSides"] = $this->_db_ArtworkSides;
		$this->_dbHash["DomainID"] = $this->_db_DomainID;
	}
	
	
	// will Exit if the Option or Choice name does not exist
	// An example might be something like setOptionChoice("Card Stock", "Glossy")
	function setOptionChoice($optionName, $choiceName){
		$this->_ensureProjectInfoIntialized();
		
		if(!$this->_projectInfoObj->setOptionChoice($optionName, $choiceName))
			throw new Exception("The given Option and Choice does not exist: " . WebUtil::htmlOutput($optionName) . " : " . WebUtil::htmlOutput($choiceName));
	}

	
	// These getter methods do not fetch directly from the database fields
	// Instead they go through the Middle layer "ProjectInfo"
	// It is a good way for this Class to return default values... 
	// ... in case some options were changed since the last time options where written to the database.
	function getProductID(){
	
		$this->_ensureProjectInfoIntialized();
	
		return $this->_projectInfoObj->getProductID();
	}
	

	
	
	// These Vendor ID's will get the current Vendor Price / Vendor ID for the given Product
	// These methods are really just pass-through methods for the ProjectInfo Object
	// ProjectOdered keeps a record of the Vendor Price / Vendor ID at the time the order was placed within the DB.
	// Keep in mind that calling these Vendor methods in the Base Class could return an up-to-date Vendor Price for the Product...
	// ... which could be different than something that was previously ordered.  See the ProjectOrdered class for extended methods which can retrieve stuff from its database.
	function getVendorIDArr(){

		$this->_ensureProjectInfoIntialized();
		return $this->_projectInfoObj->getVendorIDArr();
	}
	function getVendorID1() {
		$this->_ensureProjectInfoIntialized();
		return $this->_projectInfoObj->getVendorID1();
	}
	function getVendorID2() {
		$this->_ensureProjectInfoIntialized();
		return $this->_projectInfoObj->getVendorID2();
	}
	function getVendorID3() {
		$this->_ensureProjectInfoIntialized();
		return $this->_projectInfoObj->getVendorID3();
	}
	function getVendorID4() {
		$this->_ensureProjectInfoIntialized();
		return $this->_projectInfoObj->getVendorID4();
	}
	function getVendorID5() {
		$this->_ensureProjectInfoIntialized();
		return $this->_projectInfoObj->getVendorID5();
	}
	function getVendorID6() {
		$this->_ensureProjectInfoIntialized();
		return $this->_projectInfoObj->getVendorID6();
	}
		
	// For the Vendor Subtotals
	function getVendorSubtotalsCombined(){
		$this->_ensureProjectInfoIntialized();
		return $this->_projectInfoObj->getVendorSubtotalsCombined();
	}

	function getVendorSubtotalsArr(){
		$this->_ensureProjectInfoIntialized();
		return $this->_projectInfoObj->getVendorSubtotalsArr();
	}
	function getVendorSubTotal1(){
		$this->_ensureProjectInfoIntialized();
		return $this->_projectInfoObj->getVendorSubTotal1();
	}
	function getVendorSubTotal2(){
		$this->_ensureProjectInfoIntialized();
		return $this->_projectInfoObj->getVendorSubTotal2();
	}
	function getVendorSubTotal3(){
		$this->_ensureProjectInfoIntialized();
		return $this->_projectInfoObj->getVendorSubTotal3();
	}
	function getVendorSubTotal4(){
		$this->_ensureProjectInfoIntialized();
		return $this->_projectInfoObj->getVendorSubTotal4();
	}
	function getVendorSubTotal5(){
		$this->_ensureProjectInfoIntialized();
		return $this->_projectInfoObj->getVendorSubTotal5();
	}
	function getVendorSubTotal6(){
		$this->_ensureProjectInfoIntialized();
		return $this->_projectInfoObj->getVendorSubTotal6();
	}
	
	function checkIfArtworkCopied(){
		throw new Exception("Error in ProjectBase->checkIfArtworkCopied. You must override this method.");
	}
	function checkIfArtworkTransfered(){
		throw new Exception("Error in ProjectBase->checkIfArtworkTransfered. You must override this method.");
	}
	
	function getQuantity(){
	
		$this->_ensureProjectInfoIntialized();
	
		return $this->_projectInfoObj->getQuantity();
	}	
	function getProductTitle() {
		$this->_ensureProjectInfoIntialized();
	
		return $this->_projectInfoObj->getProductTitle();
	}
	function getProductTitleWithExt(){
		$this->_ensureProjectInfoIntialized();
	
		return $this->_projectInfoObj->getProductTitleWithExt();
	}
	
	function getCustomerSubTotal(){
		$this->_ensureProjectInfoIntialized();
	
		return $this->_projectInfoObj->getCustomerSubTotal();
	}
		
	
	// Pass in TRUE if you don't want this method to round up to the nearest pound
	function getWeight($precision = false){
	
		$this->_ensureProjectInfoIntialized();
	
		return $this->_projectInfoObj->getProjectWeight($precision);
	}
	// Pass in a string number to get a Desciption of a quantity other than the quantity currently selected
	function getOrderDescription($quantityOverride = ""){
	
		$this->_ensureProjectInfoIntialized();
	
		return $this->_projectInfoObj->getOrderDescription($quantityOverride);
	}
	function getOptionsDescription(){
	
		$this->_ensureProjectInfoIntialized();
	
		return $this->_projectInfoObj->getOptionsDescription();
	}
	function getOptionsAliasStr($hideInLongListsOptions_Flag = false){
	
		$this->_ensureProjectInfoIntialized();
	
		return $this->_projectInfoObj->getOptionsAliasStr($hideInLongListsOptions_Flag);
	}
	
	// Pass in an option name like "Card Stock" and it will return what choice is selected... like "Glossy".
	function getSelectedChoice($optionName){
	
		$this->_ensureProjectInfoIntialized();

		$selectedChoice = $this->_projectInfoObj->getSelectedChoice($optionName);
		if(empty($selectedChoice))
			throw new Exception("The Option Name does not exist within the method call getSelectedChoice: " . WebUtil::htmlOutput($optionName));
		
		return $selectedChoice;
	}
	
	// Returns a Choice Object for the given Option.
	// This can be a little more useful than just a Choice Name because it contains the full Choice Object which includes Price adjustments
	// returns NULL if the Option Does not exist.
	function getSelectedChoiceObj($optionName) {
	
		$this->_ensureProjectInfoIntialized();

		$selectedChoiceObj = $this->_projectInfoObj->getSelectedChoiceObj($optionName);
		if(empty($selectedChoiceObj))
			throw new Exception("The Option Name does not exist within the method call getSelectedChoiceObj: " . WebUtil::htmlOutput($optionName));
		
		return $selectedChoiceObj;
	
	}
	
	
	
	// Returns a 2D array.  They key to the first level of the array is the Option Name.  The value is an array of Choices
	function getProductOptionsArray(){
	
		$this->_ensureProjectInfoIntialized();
		
		return $this->_projectInfoObj->getProductOptionsArray();
	
	}

	// Returns an array... the key is the Option Name... the value is the Choice that is selected
	function getOptionsAndSelections(){
	
		$this->_ensureProjectInfoIntialized();
		
		return $this->_projectInfoObj->getOptionsAndSelections();
	
	}
	// Just like getOptionsAndSelections ... but it contains the Option Alias and Choice Alias
	function getOptionsAliasesAndSelections(){
	
		$this->_ensureProjectInfoIntialized();
		
		return $this->_projectInfoObj->getOptionsAliasesAndSelections();
	
	}
	
	
	function getOptionsAndSelectionsWithoutHiddenChoices(){
		
		$retArr = array();
		
		foreach ($this->getOptionsAndSelections() as $OptionName => $ChoiceName){ 
		
			// Don't show Hidden Choices
			if($this->getProductObj()->getChoiceDetailObj($OptionName, $ChoiceName)->hideOptionAllFlag)
				continue;
					
			$retArr[$OptionName] = $ChoiceName;
		}
		return $retArr;
	}
	

	/**
	 * Returns a Project Info Object.
	 *
	 * @return ProjectInfo
	 */
	function getProjectInfoObject(){
	
		$this->_ensureProjectInfoIntialized();
	
		return $this->_projectInfoObj;
	}
	
	// returns true or false depending on whether the project has variable data
	function isVariableData(){
		
		if(empty($this->_db_ProductID))
			throw new Exception("Error in method isVariableData. Method was called before the Product ID was set.");
	
		return Product::checkIfProductHasVariableData($this->_dbCmd, $this->_db_ProductID);

	}
	
	// Returns true or false depending on whether the project is supposed to have variable Images.
	// The project has to have variable data as a pre-cursor to having variable images.
	function hasVariableImages(){
		
		if(!$this->isVariableData())
			return false;
			
		$projectOptionsArr = $this->getOptionsAndSelections();
			
		$productObj = $this->getProductObj();
			
		foreach ($projectOptionsArr as $OptionName => $ChoiceName){ 
		
			if(!$productObj->getOptionObject($OptionName)->variableImageController)
				continue;
			
			if($productObj->getChoiceDetailObj($OptionName, $ChoiceName)->variableImageFlag)
				return true;
			else
				return false;
		}
		
		return false;
	}
	


	
	// Common getter methods (for all Sub Classes) for data variables linked directly to the database
	function getFromTemplateID(){
		return $this->_db_FromTemplateID;
	}
	function getFromTemplateArea(){
		return $this->_db_FromTemplateArea;
	}
	function getVariableDataStatus(){
		$this->_ensureProjectInfoIntialized();
		
		return $this->_db_VariableDataStatus;
	}
	function getVariableDataMessage(){
		$this->_ensureProjectInfoIntialized();
			
		return $this->_db_VariableDataMessage;
	}
	function getVariableDataArtworkConfig(){
		$this->_ensureProjectInfoIntialized();
			
		return $this->_db_VariableDataArtworkConfig;
	}
	function getVariableDataFirstLine(){
		$this->_ensureProjectInfoIntialized();
			
		return $this->_db_VariableDataFirstLine;
	}
	function getVariableDataFile(){
		$this->_ensureProjectInfoIntialized();
			
		return $this->_db_VariableDataFile;
	}
	
	function getArtworkSidesCount(){
		return $this->_db_ArtworkSides;
	}
	
	// Returns 1 for Simplex, 2 for Duplex.
	function getArtworkSideCountFromXML(){
		
		$artworkFile = $this->getArtworkFile();
		
		// Instead of parsing the XML doc to check the number of sides.... lets just find out how many Side Tags there are using preg_match_all
		$countArr = array();
		preg_match_all ("/<side>/", $artworkFile, $countArr);
		$numberOfArtworkSides = sizeof($countArr[0]);
		
		return $numberOfArtworkSides;
	}
	
	
	function getDomainID(){
		return $this->_db_DomainID;
	}
	

	// returns True if the variable data file is over 1,000 lines
	function checkIfVariableDataIsLarge(){
		$this->_ensureProjectInfoIntialized();

		if($this->getQuantity() > 1000)
			return true;
		else
			return false;
	}


	
	
	// Gets data directly from the DB field, rather than going through the ProjectInfo Object
	function getOriginalOrderDescription(){
		return $this->_db_OrderDescription;
	}
	function getOriginalOptionsDescription(){
		return $this->_db_OptionsDescription;
	}
	function getOriginalOptionsAlias(){
		return $this->_db_OptionsAlias;
	}
	
	

	function getDateLastModified(){
		throw new Exception("Error, the method getDateLastModified must be overridden.");
	}
	
	
	
	
	#### ------- Common Setter Methods for fields directly tied to the Database
	
	function setQuantity($x){

		if(!preg_match("/^\d+$/", $x))
			throw new Exception("setQuantity must be a number");

		$this->_ensureProjectInfoIntialized();
		
		$this->_projectInfoObj->setQuantity($x);
	}
	
	function setFromTemplateID($x){
		if(!preg_match("/^\d+$/", $x))
			throw new Exception("FromTemplateID must be a number");
		
		$this->_db_FromTemplateID = $x;
	}
	
	// If is came from the category selection or search engine, or "N"othing is the default or "S"earch Engine, u'P'loaded,  "C"ategory, "U"ser Template
	function setFromTemplateArea($x){
		if($x != "N" && $x != "C" && $x != "U" && $x != "S" && $x != "P")
			throw new Exception("FromTemplateArea area is not correct.");
		
		$this->_db_FromTemplateArea = $x;
	}
	
	function setVariableDataStatus($x){
		$this->_ensureProjectInfoIntialized();
		
		// Good, Artwork Error, Warning, Data Error, Login Error, Image Error (from variable images)
		// A login error may happen if they are using Variable Images and they are not logged in.
		// There is no way to check if the images exist for the user or not.
		if($x != "G" && $x != "A" && $x != "W" && $x != "D" && $x != "L" && $x != "I")
			throw new Exception("Illegal Status character in setVariableDataStatus: $x");
			
		if(!$this->_projectInfoObj->isVariableData()){
			if($x !== null)
				throw new Exception("You can not set the status on a product that does not have variable data");
		}
	
		$this->_db_VariableDataStatus = $x;
	}
	
	function setVariableDataMessage($x){
		$this->_ensureProjectInfoIntialized();
		
		if(!$this->_projectInfoObj->isVariableData())
			throw new Exception("You can not set the variable data message on a product that does not have variable data");
		
		if(strlen($x) > 65530)
			throw new Exception("Variable data message can not exceed 65530 characters");
		
		$this->_db_VariableDataMessage = $x;
	}
	function setVariableDataArtworkConfig($x){
		$this->_ensureProjectInfoIntialized();
		
		if(!$this->_projectInfoObj->isVariableData()){
			if($x !== null)
				throw new Exception("You can not setVariableDataArtworkConfig on a product that does not have variable data");
		}
		
		$this->_db_VariableDataArtworkConfig = $x;
	}
	function setVariableDataFirstLine($x){
		$this->_ensureProjectInfoIntialized();
		
		if(!$this->_projectInfoObj->isVariableData()){
			if($x !== null)
				throw new Exception("You can not setVariableDataFirstLine on a product that does not have variable data");
		}
		
		$this->_db_VariableDataFirstLine = $x;
	}
	function setVariableDataFile($x){
		$this->_ensureProjectInfoIntialized();
		
		if(!$this->_projectInfoObj->isVariableData()){
			if($x !== null)
				throw new Exception("You can not setVariableDataFile on a product that does not have variable data");
		}
		
		$this->_db_VariableDataFile = $x;
	}
	

	

	// This returns an HTML table describing the Project Quantities and descriptions
	// Ideally we should put this method in a ProjectDecorator table... but there aren't enough methods to warrant that yet
	// pass in a string representing a CSS class for going in the HTML
	// Optional, pass in a URL if you want the Quantity to be a Link.  This is useful for Variable Data projects to link them into the config screen.
	function getProjectDescriptionTable($CSSval, $urlForQuantity = "", $showOptionsCost = false){

		$this->_ensureProjectInfoIntialized();

		$retHTML = "<table width='100%' cellpadding='2' cellspacing='0'>";

		if(!empty($urlForQuantity)){
			$quantityLinkStart = "<a class='BlueRedLink' href='". WebUtil::htmlOutput($urlForQuantity) . "'>";
			$quantityLinkEnd = "</a>";
		}
		else{
			$quantityLinkStart = "";
			$quantityLinkEnd = "";
		}

		$retHTML .= "<tr><td width='25%' class='$CSSval' valign='top' nowrap><b>" . $quantityLinkStart . "Quantity" . $quantityLinkEnd . "</b>&nbsp;&nbsp;</td>
				<td width='75%' class='$CSSval'>" . $quantityLinkStart . $this->getQuantity() . $quantityLinkEnd . "</td></tr>";

		foreach ($this->getOptionsAndSelections() as $OptionName => $ChoiceName){ 
		
			// Don't show Hidden Choices
			if($this->getProductObj()->getChoiceDetailObj($OptionName, $ChoiceName)->hideOptionAllFlag)
				continue;
				
			$ChoiceName = WebUtil::htmlOutput($ChoiceName);
			
			// We don't Want Selected Choices to Break between dashes... like single-sided
			$ChoiceName = preg_replace("/((\w|\-|\d)+)/", "<nobr>\\1<nobr>", $ChoiceName);
		
			$retHTML .= "<tr><td width='25%' class='$CSSval' valign='top' nowrap><nobr>
					<b><nobr>" . WebUtil::htmlOutput($OptionName) . "</nobr></b>
					&nbsp;&nbsp;</td>
					<td width='75%' class='$CSSval'>" . $ChoiceName . "</td>";
					
			if($showOptionsCost)
				$retHTML .= "<td width='15%' class='$CSSval' align='right'>\$" . WebUtil::htmlOutput($this->getCustomerProductOptionTotal($OptionName)) . "</td>";
					
			$retHTML .=		"</tr>";
		}
		
		$retHTML .= "</table>";

		return $retHTML;
	}
	
	
	// If this Project has an Administrative Option set... Like for "Customer Assistance"
	// This will reset the Option/Choice back to the first Option Choice which as a "Hidden Flag" set.
	// If this particular product does not have that option then it will be benign to call this method.
	// Return TRUE is the option was changed on the Project, False otherwise.
	function clearAdminOptionIfSet(){

		$this->_ensureProjectInfoIntialized();
		
		$optionsAndSelectionArr = $this->getOptionsAndSelections();
		
		$adminOptionResetFlag = false;
		
		foreach($optionsAndSelectionArr as $thisOptionName => $thisChoiceName){
			
			$thisOptionObj = $this->getProductObj()->getOptionDetailObj($thisOptionName);
			$thisChoiceObj = $this->getProductObj()->getChoiceDetailObj($thisOptionName, $thisChoiceName);
			
			// In case the Product Coniguration was changed since the order was placed.
			if(empty($thisOptionObj))
				continue;
				
			// Don't bother trying to change choices on options which are not Admin.
			if(!$thisOptionObj->adminOptionController)
				continue;
				
			// If the Product Choices were changed since the order was placed, then we want to reset the Choice
			// But if it is an Admin Option and the selected Choice is already hidden, then we don't need to change anything.
			if(!empty($thisChoiceObj) && $thisChoiceObj->hideOptionAllFlag)
				continue;
				
			// Now set the Choice on first Hidden Option that we come across.
			foreach($thisOptionObj->choices as $subChoiceObj){
				
				if($subChoiceObj->hideOptionAllFlag){
					
					$adminOptionResetFlag = true;
					
					$this->setOptionChoice($thisOptionName, $subChoiceObj->ChoiceName);
					break;
				}
			}
		}
		

		return $adminOptionResetFlag;
	}


	// Looks for Product Options that have a "Discount Exemption" on them.
	// It will Total of all of the "Customer Charges" on those choices and return that amount that should be exempt from receiving discounts.
	function getAmountOfSubtotalThatIsDiscountExempt(){
		
		$this->_ensureProjectInfoIntialized();
		
		$optionNames = array_keys($this->getOptionsAndSelections());
		$productObj = $this->_projectInfoObj->getProductObj();
		
		$returnAmount = 0;
		
		foreach($optionNames as $thisOptionName){
			
			$productOptionObject = $productObj->getOptionObject($thisOptionName);
						
			if($productOptionObject->couponDiscountExempt)
				$returnAmount += $this->getCustomerProductOptionTotal($thisOptionName);
		}
		
		return $returnAmount;
	}
	

	// Pass in a an OptionName like "Postage Type" and it will tell you the Customer Subtotal for that option specifically
	// If that Product option does not exist then this function will return 0.
	function getCustomerProductOptionTotal($optionName){

		$this->_ensureProjectInfoIntialized();

		$projectOptionsArr = array_keys($this->getOptionsAndSelections());

		if(in_array($optionName, $projectOptionsArr))
			return $this->_projectInfoObj->getCustomerSubTotal($optionName);

		return 0;
	}
	
	
	// Will look at this product's  Project  Options and see if the artwork needs to be altered
	// This is useful to change the artwork when the user changes their option.
	// For example... you could have Postage Bulk Mail Marks change automatically... or you can even change the size of graphics within the artwork definition.
	function searchReplaceArtworkBasedOnProductOptions(){

		$this->_ensureProjectInfoIntialized();

		$prodObj = $this->_projectInfoObj->getProductObj();
		
		$productOptionsObjectsArr = $prodObj->getProductOptionsArr();
		
		$artworkXML = $this->getArtworkFile();
		
		foreach($productOptionsObjectsArr as $thisProductOptionObj){
		
			if(!$thisProductOptionObj->artworkSearchReplaceController)
				continue;
			
			$choiceObj = $this->getSelectedChoiceObj($thisProductOptionObj->optionName);
			
			$searchReplaceArr = $choiceObj->getSearchAndReplaceRoutines();
			
			// Do a case-insensitive Search/Replace
			foreach($searchReplaceArr as $thisSearchReplaceHash)
				$artworkXML = preg_replace("/" . preg_quote($thisSearchReplaceHash["Search"]) . "/i", $thisSearchReplaceHash["Replace"], $artworkXML);
		}
		
		$this->setArtworkFile($artworkXML);
	}
		
		

	// The artwork is the one with authority.
	// If the Artwork has 2 sides, it will Change the Project Options to DoubleSided (and indirectly update the Price).
	// In case there isn't a matching Product Option for actual artwork sides... this method will not do anything.
	function changeProjectOptionsToMatchArtworkSides(){

		$this->_ensureProjectInfoIntialized();

		$artInfoObj = new ArtworkInformation($this->getArtworkFile());
		
		$numberOfSides = sizeof($artInfoObj->SideItemsArray);
		
		// Store the count of artwork side in the DB. This can allow to to query the DB quickly to estimate "printer impressions" and other thigns.
		$this->_db_ArtworkSides = $numberOfSides;
		
		$prodObj = $this->getProductObj();
		
		// Look for a Product Option Controller that affects the number of sides.
		// If we find one... then select the "Option/Choice" on this project that matches the number of Sides of the current artwork.
		$optionName = null;
		$choiceName = null;
		
		$optionsArr = $prodObj->getProductOptionsArr();
		foreach($optionsArr as $optCheckObj){
			
			if($optCheckObj->artworkSidesController){
				
				$optionName = $optCheckObj->optionName;
			
				$optionChoicesArr = $optCheckObj->choices;
				
				foreach($optionChoicesArr as $thisChoiceObj){
				
					if($thisChoiceObj->artworkSideCount == $numberOfSides){
						$choiceName = $thisChoiceObj->ChoiceName;
						break;
					}
				}
			}
		}
		
		// Only select the Option/Choice on this Project if we found a match.
		if(!empty($optionName) && !empty($choiceName))
			$this->setOptionChoice($optionName, $choiceName);
		
	}



	// Will look at the selected Option/Choices on this project and add/remove artwork sides accordingly.
	// If this Product Does not have an Artwork Sides Controller then this method won't change anything.
	function changeArtworkSidesBasedOnProjectOptions(){
	
		$this->_ensureProjectInfoIntialized();
		
		$prodObj = $this->getProductObj();
		
		$artInfoObj = new ArtworkInformation($this->getArtworkFile());
		
		
		
		// Make sure that there are not more sides on this Artwork than there are sides defined within the product definition.
		// Could happen from an error in the Customers browser... since we don't have control over what their application uploads.
		$sideDiff = sizeof($artInfoObj->SideItemsArray) - $prodObj->getArtworkSidesCount();
		if($sideDiff > 0){
			for($i=0; $i < $sideDiff; $i++)
				array_pop($artInfoObj->SideItemsArray);
			
			// Update the artwork file within our local object since the side counts of changed.
			$this->setArtworkFile($artInfoObj->GetXMLdoc());
		}
		
		$numberOfSides = sizeof($artInfoObj->SideItemsArray);
		
		// Store the count of artwork side in the DB. This can allow to to query the DB quickly to estimate "printer impressions" and other thigns.
		$this->_db_ArtworkSides = $numberOfSides;
		
		
		$optionName = null;
		
		$optionsArr = $prodObj->getProductOptionsArr();
		foreach($optionsArr as $optCheckObj){
			
			if($optCheckObj->artworkSidesController){
				$optionName = $optCheckObj->optionName;
				break;
			}
		}
		
		// There is no Artwork sides controller, therefore we don't know how to change the artwork.
		if(empty($optionName))
			return;
			
			
		$selectedChoiceObj = $this->getSelectedChoiceObj($optionName);
		if(empty($selectedChoiceObj))
			return;
		
		// By default, new options have a Zero side count... They aren't allowed to "update" the choice parameters on the Product Option until the user changes the value greater than zero... but there is still a chance this could be zero.
		if($selectedChoiceObj->artworkSideCount == 0)
			return;
			
		$changeSideCountTo = $selectedChoiceObj->artworkSideCount;
		

		// If the Number of sides on the artwork matches what the Artwork Sides Controller specifies... then just return without changing anything.
		if($numberOfSides == $changeSideCountTo)
			return;


		// If we have more sides on our artowrk than the Option/Choice Controller has.. then get rid of the extra sides.
		$sideDiff = sizeof($artInfoObj->SideItemsArray) - $changeSideCountTo;
		if($sideDiff > 0){
			for($i=0; $i < $sideDiff; $i++)
				array_pop($artInfoObj->SideItemsArray);
				
			$this->_db_ArtworkSides = sizeof($artInfoObj->SideItemsArray);
				
			// Update the Artwork XML in our local project object.  No point in going further since the side counts should be perfect now.
			$this->setArtworkFile($artInfoObj->GetXMLdoc());
			return;
		}
		
		
		// If Option/Choice controller says we should have more sides than the artwork currently has.... then add the remaining sides using the "default artwork" of the product.
		$defaultProductArtworkObj = new ArtworkInformation($prodObj->getDefaultProductArtwork());
		
		for($i=0; $i<$changeSideCountTo; $i++){
		
			// Copy the side from the default Product Artwork onto the Missing side our Active Artwork.
			// The Default Product Artwork will always have a Side Definition for all possible sides.
			if(!isset($artInfoObj->SideItemsArray[$i]))				
				$artInfoObj->SideItemsArray[$i] = $defaultProductArtworkObj->SideItemsArray[$i];

		}
		
		// Update the Artwork XML in our local project object. 
		$this->setArtworkFile($artInfoObj->GetXMLdoc());
		
		$this->_db_ArtworkSides = sizeof($artInfoObj->SideItemsArray);

	}
	
	
	

	// If this Product has an option/choice on it that affects the number of Sides... 

	// ... and there is a Side 1 & 2 is available... and the artwork has a "Blank Backside"... then remove the back.

	// Returns TRUE or FALSE depending on whether the backside was deleted.
	function possiblyDeleteBacksideIfBlank(){
		
		$productObj = $this->getProductObj();
		
		if($productObj->getArtworkSidesCount() == 2){
		
			$side1FoundFlag = false;
			$side2FoundFlag = false;
		
			$productOptionsArr = $this->getProductOptionsArray();
			
			foreach(array_keys($productOptionsArr) as $thisOptionName){
			
				$optionObj = $this->_projectInfoObj->getOptionObj($thisOptionName);
				
				// If this option is not a "controller" for affecting the number of artwork sides... then there is no point in checking the values inside of the choices.
				if(!$optionObj->artworkSidesController)
					continue;
				
				$choicesObjArr = $optionObj->choices;
				
				foreach($choicesObjArr as $thisChoiceObj){
				
					if($thisChoiceObj->artworkSideCount == 1)
						$side1FoundFlag = true;
					else if($thisChoiceObj->artworkSideCount == 2)
						$side2FoundFlag = true;
				}
			}
			
			
			// If this Product has a controller with choices that can create 1 side or 2 sides... then see if the backside is empty.
			if($side1FoundFlag && $side2FoundFlag){
			
				$artInfoObj = new ArtworkInformation($this->getArtworkFile());
				
				if(sizeof($artInfoObj->SideItemsArray) == 2 && sizeof($artInfoObj->SideItemsArray[1]->layers) == 0){
					
					// Pop of the last side (which will be the back).
					array_pop($artInfoObj->SideItemsArray);
					
					// Update the Artwork XML in our local project object. 
					$this->setArtworkFile($artInfoObj->GetXMLdoc());
					
					// If the Project Options were Double-Sided... that option will get changed to "Single-Sided"
					$this->changeProjectOptionsToMatchArtworkSides();
					
					return true;
				}
			}
		}
		
		return false;
	}


	
	
	####----- Private Methods below --------#
	
	protected function _ensureProjectInfoIntialized(){
		if(!$this->_projectInfoClassInitializedFlag)
			throw new Exception("The Product must be initialized before calling this method. _ensureProjectInfoIntialized");
	}
	
}




?>
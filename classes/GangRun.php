<?


// This class is meant to organize multiple PDF profiles and plot them together on a single master PDF profile.
// 

class GangRun {


	private $_gangRunID;
	private $_superProfileID;
	private $_sheetQuantity;
	private $_duplexFlag;
	private $_squarePicasUsedOnFront;
	private $_squarePicasUnique;
	private $_confirmedBy;
	
	private $_materialDesc;
	private $_batchNotes;
	
	private $_printingPressID;
	private $_frontThumbnailJPG;
	private $_backThumbnailJPG;
	private $_gangRunStatus;
	private $_frontStatus;
	private $_backStatus;
	private $_coverStatus;
	private $_timeStampCreated;
	private $_lastUpdated;

	private $_dbCmd;
	private $_superProfileObj;
	private $_thumbMaxWidth;
	private $_thumbMaxHeight;
	
	private $_forceQuantityFlag;

	private $_projectPositions = array();
	private $_pdfGeneratedArr = array();

	// If we are in Preview Mode, then the Project ID's are fake
	// We want to generate a PDF preview for admins configuring Super PDF profiles.
	private $_previewModeFlag = false;

	##--------------   Constructor  ------------------##

	function GangRun(DbCmd $dbCmd){
	
		$this->_dbCmd = $dbCmd;

		$this->_squarePicasUsedOnFront = 0;
		$this->_squarePicasUnique = 0;
		
		$this->_thumbMaxWidth = 200;
		$this->_thumbMaxHeight = 200;
		
		
		// The default of a gang run is to have no Printer associated with it... but this should be discouraged.
		// When you start a new gangrun you should know where it will be printed.
		$this->_printingPressID = 0;
		
	}


	// STATIC
	// Returns a human readable sheet quantity... unless it is not defined, then it returns just the number.
	static function translateSheetQuantity($quantity){
	
		$quantity = intval($quantity);
		
		if($quantity == 1000)
			return "1K";
		else if($quantity == 2000)
			return "2K";
		else if($quantity == 3000)
			return "3K";
		else if($quantity == 5000)
			return "5K";
		else if($quantity == 10000)
			return "10K";
		else if($quantity == 15000)
			return "15K";
		else if($quantity == 20000)
			return "20K";
		else if($quantity == 25000)
			return "25K";
		else if($quantity == 50000)
			return "50K";
		else
			return $quantity;
	
	}
	
	// Static
	// Cleans out completed gang runs so the DB doesn't get full.  The Thumbnails and PDF's can be re-generated later if needed.
	static function cleanOutCompletedJobs(DbCmd $dbCmd){
		$dbCmd->Query("UPDATE gangruns SET FrontPDF=null WHERE GangRunStatus='C'");
		$dbCmd->Query("UPDATE gangruns SET BackPDF=null WHERE GangRunStatus='C'");
		$dbCmd->Query("UPDATE gangruns SET CoverPDF=null WHERE GangRunStatus='C'");
		$dbCmd->Query("UPDATE gangruns SET FrontThumbJPG=null WHERE GangRunStatus='C'");
		$dbCmd->Query("UPDATE gangruns SET BackThumbJPG=null WHERE GangRunStatus='C'");
		$dbCmd->Query("OPTIMIZE TABLE gangruns");
	}
	
	function initializeNewGangRun($sheetQuantity, $superProfileID, $isDuplex, $forceQuantityFlag = false){
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		$domainIDofSuperProfile = SuperPDFprofile::getDomainIDofSuperProfile($superProfileID);
		if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofSuperProfile))
			throw new Exception("Error in initializeNewGangRun. Can not initialize a new gang run because the Super PDF profile may not exist.");
		
		$this->_superProfileID = $superProfileID;
		$this->_superProfileObj = new SuperPDFprofile();
		$this->_superProfileObj->loadSuperPDFProfileByID($superProfileID);
		
		$this->_duplexFlag = $isDuplex;
		
		if(!preg_match("/^\d+$/", $sheetQuantity))
			throw new Exception("Error in GangRun->createNewGangRun... Sheet quantity must be numeric.");
			
		if(empty($sheetQuantity))
			throw new Exception("Error in GangRun->createNewGangRun... Sheet be greater that Zero.");
			
		$this->_sheetQuantity = $sheetQuantity;
		
		$this->_timeStampCreated = time();
		
		$this->_gangRunStatus = "P";		
		
		$this->_confirmedBy = "";
		$this->_materialDesc = "";
		$this->_batchNotes = $this->_superProfileObj->getSuperPDFProfileNotes(); // Default the Notes to the Super Profile Notes.
			
		$this->_forceQuantityFlag = $forceQuantityFlag;

	}
	
	
	// "G"enerated means that the PDF file has been generated and stored within the DB.
	// "X" means that the PDF file does not exist (like a Backside on a Simplex Run).
	// "D"ownloading is a status that occurs when a Client signals they are going to start downloading the file
	// "R"eceived Status indicates that the client program acknowledges receipt of the file.
	// "A"rtwork generation needed... means that the Batch was saved but the the Artwork hasn't been generated... A Cron Job can work in the background generating artworks.
	// "S"tarting to Generate means that the server is going to start working on this file right now.  It lets you know when its turn is next ... this technique keeps a multi-threaded cron from trying work on it twice.  Also, in case of an error it will sit on "S" instead of trying to keep repeating the mistake.
	function getFrontStatus(){
		$this->_ensureThatGangInitialized();
		return $this->_frontStatus;
	}
	function getBackStatus(){
		$this->_ensureThatGangInitialized();
		return $this->_backStatus;
	}
	function getCoverStatus(){
		$this->_ensureThatGangInitialized();
		return $this->_coverStatus;
	}
	
	
	function setPreviewMode($flag){
		if(!is_bool($flag))
			throw new Exception("The parmaeter is not boolean.");
		$this->_previewModeFlag = $flag;
	}
	function getPreviewMode(){
		return $this->_previewModeFlag;
	}
	
	
	function getPrintingPressID(){
		$this->_ensureThatGangInitialized();
		
		$availablePrinterIDs = array_keys($this->_superProfileObj->getPrintingPressNames());
		
		// In case the printer was deleted after the GangRun was saved to the DB... this will silently disconnect it (without writing to the DB).
		if(!in_array($this->_printingPressID, $availablePrinterIDs))
			return 0;
		
		return $this->_printingPressID;
	}
	
	
	function setPrintingPressID($thisPressID){
		$this->_ensureThatGangInitialized();
		
		$thisPressID = intval($thisPressID);
		
		$availablePrinterIDs = array_keys($this->_superProfileObj->getPrintingPressNames());
		
		if($thisPressID != 0 && !in_array($thisPressID, $availablePrinterIDs))
			throw new Exception("Error in method GangRun->setPrintingPressID, the printing press does not exist anymore.");

		
		$this->_printingPressID = $thisPressID;
	}
	
	
	// By default, New GangRuns saved to the DB start out with a "P"ending Status.
	// May have a "C"onfirmed status in which it is marked as printed and movis into a "History Queue" of Old Jobs.
	// A flag of "O"ld means that the row still exists... but all of the Binary Blob data was deleted (it could always be regenerated if needed).
	function getGangRunStatus(){
		
		if(empty($this->_gangRunID))
			throw new Exception("Error in method GangRun->getGangRunStatus. You can not check the GangRun Status until after it has been saved to the DB.");
		
		return $this->_gangRunStatus;
	
	}
	
	
	function getFileNameForFrontPDF(){
	
		$frontDesc = $this->_duplexFlag ? "FRONT" : "SINGLE";
	
		return GangRun::translateSheetQuantity($this->_sheetQuantity) . "_" . SuperPDFprofile::getSuperProfileNameByID($this->_superProfileID) . "_" . $this->getTimeStampString() . "_" . $frontDesc . ".pdf";
	}
	function getFileNameForBackPDF(){
		return GangRun::translateSheetQuantity($this->_sheetQuantity) . "_" . SuperPDFprofile::getSuperProfileNameByID($this->_superProfileID) . "_" . $this->getTimeStampString() . "_BACK.pdf";
	}
	function getFileNameForCoverPDF(){
		return SuperPDFprofile::getSuperProfileNameByID($this->_superProfileID) . "_" . $this->getTimeStampString() . "_COVER.pdf";
	}
	
	

	// Returns True or False depending on whether the ID exists.
	function loadGangRunID($gangRunID){
	
		$gangRunID = intval($gangRunID);
	
		$this->_dbCmd->Query("SELECT SuperProfileID, SheetCount, DuplexFlag, UNIX_TIMESTAMP(CreatedOn) AS CreatedOn, UNIX_TIMESTAMP(LastUpdated) AS LastUpdated, ConfirmedBy,
					PrintingPressID, GangRunStatus, FrontStatus, BackStatus, CoverStatus, MaterialDesc, BatchNotes, ForceQuantityFlag
					FROM gangruns WHERE ID=" . $gangRunID);
		
		if($this->_dbCmd->GetNumRows() != 1)
			return false;
		
		$row = $this->_dbCmd->GetRow();

		// We correlate Gang Runs with Domain IDs based upon the Super PDF Profile ID that is linked to the Gang Run.
		$domainIDofSuperProfile = SuperPDFprofile::getDomainIDofSuperProfile($row["SuperProfileID"]);
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofSuperProfile))
			return false;
		
		$this->_superProfileID = $row["SuperProfileID"];
		$this->_sheetQuantity = $row["SheetCount"];
		$this->_duplexFlag = $row["DuplexFlag"] == "Y" ? true : false;
		$this->_timeStampCreated = $row["CreatedOn"];
		$this->_confirmedBy = $row["ConfirmedBy"];
		$this->_printingPressID = $row["PrintingPressID"];
		$this->_gangRunStatus = $row["GangRunStatus"];
		$this->_frontStatus = $row["FrontStatus"];
		$this->_backStatus = $row["BackStatus"];
		$this->_coverStatus = $row["CoverStatus"];
		$this->_lastUpdated = $row["LastUpdated"];
		$this->_materialDesc = $row["MaterialDesc"];
		$this->_batchNotes = $row["BatchNotes"];
		$this->_forceQuantityFlag = ($row["ForceQuantityFlag"] == "Y" ? true : false);
		
		
		$this->_gangRunID = $gangRunID;
		

		$this->_superProfileObj = new SuperPDFprofile();
		$this->_superProfileObj->loadSuperPDFProfileByID($this->_superProfileID);
		
		
		// Now load all of the projects associated with this GangRun.
		$this->_dbCmd->Query("SELECT DISTINCT(ProjectID) FROM gangrunlinks WHERE GangRunID=" .  $this->_gangRunID . " ORDER BY Sequence ASC");
		
		$projectArr = array();
		while($pid = $this->_dbCmd->GetValue())
			$projectArr[] = $pid;
			
		$allUserDomainIDs = $passiveAuthObj->getUserDomainsIDs();
		
		foreach($projectArr as $pid){
			
			// Since Gang Runs can mix products from multiple domains, make sure that the user can view all Project IDs in the GangRun.
			$domainIDofProject = ProjectBase::getDomainIDofProjectRecord($this->_dbCmd, "ordered", $pid);
			if(!in_array($domainIDofProject, $allUserDomainIDs))
				return false;
			
			// If we are not able to load a project from a previous gang run then something may have changed within a Sub PDF profile.... such as narrowing the Col/Row values.
			// We want to record in the Project History that this project couldn't be loaded.
			if(!$this->addProject($pid)){
				
				// Don't do this on completed gang runs though.  We don't want to break links and add messages if we are just browsing archives or something.	
				if($this->_gangRunStatus != "C"){
					
					// Set Project the project on hold until someone can figure out the problem.
					ProjectOrdered::ChangeProjectStatus($this->_dbCmd, "H", $pid);
					ProjectHistory::RecordProjectHistory($this->_dbCmd, $pid, ("Error Loading with GangRun G" . $this->_gangRunID . ". Maybe a PDF Profile was changed and this project no longer fits?"), 0);
					ProjectHistory::RecordProjectHistory($this->_dbCmd, $pid, "H", 0);
					
					// Break this project out of the gang run.
					$this->_dbCmd->Query("DELETE FROM gangrunlinks WHERE GangRunID=" . $this->_gangRunID . " AND ProjectID=" . $pid);
				}
			}
		}
		
		// If there are no more projects left (after possibly removing some)... then cancel the whole GangRun
		$this->_dbCmd->Query("SELECT COUNT(*) FROM gangrunlinks WHERE GangRunID=" . $this->_gangRunID);
		if($this->_dbCmd->GetValue() == 0){
			$this->cancelGangRun(0);
			return false;
		}
		
		return true;
	}
	
	
	// Updates the Data... it does not save ThumbNail Images or PDF files to the Row because that should have been saved when the Gang was created (and it shouldn't change).
	// There is a lot of other stuff which shouldn't change from the time it was created either.
	function updateGangRun(){
	
		if(empty($this->_gangRunID))
			throw new Exception("Error in method GangRun->updateGangRun.. You can update the GangRun until after it has been saved to the DB.");
			
		
		$updateDBArr["ConfirmedBy"] = $this->_confirmedBy;
		$updateDBArr["GangRunStatus"] = $this->_gangRunStatus;
		$updateDBArr["FrontStatus"] = $this->_frontStatus;
		$updateDBArr["BackStatus"] = $this->_backStatus;
		$updateDBArr["CoverStatus"] = $this->_coverStatus;
		$updateDBArr["MaterialDesc"] = $this->_materialDesc;
		$updateDBArr["BatchNotes"] = $this->_batchNotes;
		$updateDBArr["PrintingPressID"] = $this->_printingPressID;
		$updateDBArr["LastUpdated"] = time();
		
		$this->_dbCmd->UpdateQuery("gangruns", $updateDBArr, "ID=" . $this->_gangRunID);
		
	}
	
	
	// Saves Gang Run to the DB and returns the GangRunID.
	// Set the flag to delay image processing if you don't want to wait for the Images to generate... Let a Cron Job do that stuff in the background.
	// If PDF processing is required you can flush output progress to the browser periodically to keep it from timing out.  "spaces" is the default with is just blank spaces... You might want to try "javascript" for real-time updates in percentages back to the browser.
	function saveNewGangRun($userID, $delayImageProcessing = true, $flushProgressOutput = "spaces"){
	
		if(!empty($this->_gangRunID))
			throw new Exception("Error in Method GanRun->saveNewGangRun.  A GangRunID already exists.");
	
		$this->_ensureThatGangInitialized();
		
		
		// Generate the Files before saving to the DB... just in case there is a crash while generated the files we don't want the batch left in limbo.
		if(!$delayImageProcessing){

			$coverSheetPDF =& $this->getCoverSheetPDF($flushProgressOutput);
			
			if($this->_duplexFlag){
				$backSidePDF =& $this->getBackSidePDF($flushProgressOutput);
				$this->_backThumbnailJPG =& $this->_convertPDFtoThumbnailJPG($backSidePDF); 
			}
			
			// Mark this artworks as "G"enerated (Except for the Front)
			// We want to generate the front afterwards so that we know the GangID ... and can draw the BarCode
			$this->_frontStatus = "A";
			$this->_coverStatus = "G";
			$this->_backStatus = "G";
		}
		else{
			// Marks as "A"twork generated needed so that a cron job can process in the background.
			$this->_frontStatus = "A";
			$this->_coverStatus = "A";
			$this->_backStatus = "A";
		}
		
		
		
		// If the GangRun is not duplex then it means it will never need a status here.
		if(!$this->_duplexFlag)
			$this->_backStatus = "X";
		
	
		$insertDBArr["SuperProfileID"] = $this->_superProfileID;
		$insertDBArr["SheetCount"] = $this->_sheetQuantity;
		$insertDBArr["DuplexFlag"] = $this->_duplexFlag ? "Y" : "N";
		$insertDBArr["CreatedOn"] = $this->_timeStampCreated;
		$insertDBArr["PrintingPressID"] = $this->_printingPressID;
		$insertDBArr["ConfirmedBy"] = $this->_confirmedBy;
		$insertDBArr["GangRunStatus"] = $this->_gangRunStatus;
		$insertDBArr["FrontStatus"] = $this->_frontStatus;
		$insertDBArr["BackStatus"] = $this->_backStatus;
		$insertDBArr["CoverStatus"] = $this->_coverStatus;
		$insertDBArr["LastUpdated"] = time();
		$insertDBArr["CreatedOn"] = $this->_timeStampCreated;
		$insertDBArr["MaterialDesc"] = $this->_materialDesc;
		$insertDBArr["BatchNotes"] = $this->_batchNotes;
		$insertDBArr["ForceQuantityFlag"] = ($this->_forceQuantityFlag ? "Y" : "N");
		
		
		if(!$delayImageProcessing){
			$insertDBArr["FrontThumbJPG"] = $this->_frontThumbnailJPG;
			$insertDBArr["BackThumbJPG"] = $this->_backThumbnailJPG;
		}
		
		$newGangID = $this->_dbCmd->InsertQuery("gangruns", $insertDBArr);
	
		
		// Update the Linking Table (associating ProjectIDs included in this GangRun).
		// Use the Sequence counter so that when the gangrun is subequently reloaded... they will get put back in the same order.  
		// Important because Products may span across multiple sub-profiles and Project IDs are not allowed to be split over 2 separate Sub PDF profiles.
		$sequenceCounter = 0;
		
		$projectIDarr = $this->getProjectIDlist();
		foreach($projectIDarr as $thisProjectID){
			$sequenceCounter++;
			$this->_dbCmd->InsertQuery("gangrunlinks", array("GangRunID"=>$newGangID, "ProjectID"=>$thisProjectID, "Sequence"=>$sequenceCounter));
		}
	

		$this->_gangRunID = $newGangID;
		
		
		
		// Make all of the projects have a "Plated" status.
		$projectIDarr = $this->getProjectIDlist();
		foreach($projectIDarr as $thisProjID){
			ProjectOrdered::ChangeProjectStatus($this->_dbCmd, "E", $thisProjID);
			ProjectHistory::RecordProjectHistory($this->_dbCmd, $thisProjID, ("Plated in GangRun G" . $this->_gangRunID), $userID);
		}


		// Now Save the PDF Blobs to the newly created row.
		// Saves the Blob data in chunks to avoid LargeBlock transfers in a single query.
		if(!$delayImageProcessing){

			$this->_dbCmd->UpdateQuery("gangruns", array("CoverPDF"=>$coverSheetPDF), "ID=" . $this->_gangRunID);

			if($this->_duplexFlag)
				$this->_dbCmd->UpdateQuery("gangruns", array("BackPDF"=>$backSidePDF), "ID=" . $this->_gangRunID);
				
			// Now Generate the Front-Side (because we know the GangID and can draw a barcode on it.).
			$this->updateFrontSidePDFinDB($flushProgressOutput);
		}


		
		return $this->_gangRunID;

	}
	
	
	// This method is mainly used to generate artwork in the background.... So the operator doesn't have to wait.
	// For that reason we will flush buffer output as "spaces" to keep the command line broswer from timing out in the cron.
	function generateArtworkWhereNeeded($flushProgressOutput = "spaces"){
	
		if(empty($this->_gangRunID))
			throw new Exception("Error in method GangRun->generateArtworkWhereNeeded.. You can't generate artwork until a GangID has been loaded.");
			
		
		if($this->_frontStatus == "A")
			$this->updateFrontSidePDFinDB($flushProgressOutput);
		
		if($this->_backStatus == "A")
			$this->updateBackSidePDFinDB($flushProgressOutput);
		
		if($this->_coverStatus == "A")
			$this->updateCoverSheetPDFinDB($flushProgressOutput);
	
	
	}
	
	

	
	

	
	

	// Returns the JPEG binary data.

	// Fetches BlobData on Demand to cut down on unecessary proccessing (if it never gets used).
	function &getFrontThumbnail(){
		
		if(empty($this->_gangRunID))
			throw new Exception("Error in method GangRun->getFrontThumbnail.. Can't call this until after the Gang Object has been saved to the DB.");
	
		if(!empty($this->_frontThumbnailJPG))
			return $this->_frontThumbnailJPG;
		
		$this->_dbCmd->Query("SELECT FrontThumbJPG FROM gangruns WHERE ID=" . $this->_gangRunID);
	
		$this->_frontThumbnailJPG = $this->_dbCmd->GetValue();
		
		return $this->_frontThumbnailJPG;
	}
	function &getBackThumbnail(){
		
		if(empty($this->_gangRunID))
			throw new Exception("Error in method GangRun->getBackThumbnail.. Can't call this until after the Gang Object has been saved to the DB.");
	
		if(!empty($this->_backThumbnailJPG))
			return $this->_backThumbnailJPG;
		
		$this->_dbCmd->Query("SELECT BackThumbJPG FROM gangruns WHERE ID=" . $this->_gangRunID);
		
		$this->_backThumbnailJPG = $this->_dbCmd->GetValue();
		
		return $this->_backThumbnailJPG;
	}
	

	
	function getLastUpdated(){
		return $this->_lastUpdated;
	}
	
	function getTimeStampCreated(){
		return $this->_timeStampCreated;
	}
	
	// For extra security we use portions of MD5 timestamp (that the date was created).  Both to make it not cache the result, and also to make it hard for someone to download artworks that they are not supposed to. 
	function getTimeStampString(){
		
		$timeStampMd5 = md5($this->_timeStampCreated);
		
		return substr($timeStampMd5, 5, 3) . date("i", $this->_timeStampCreated) . substr($timeStampMd5, 4, 3) . substr($timeStampMd5, 2, 1) . substr($timeStampMd5, 22, 1);
	}
	
	
	// Returns a blank string if we are able to confirm... otherwise returns an error message.
	// If it is Able to confirm.... It will also Update the database.
	// The confirmation code right now is pretty simple and almost useless, but in the future we may require a more crypti confirmation code that can't be guessed to ensure Operator honesty.
	function confirmGangRun($confirmCode, $confirmName, $userID){
	
		if(empty($this->_gangRunID))
			throw new Exception("Error in method GangRun->confirmGangRun.. You can't confirm a GangRun until the GangID has been loaded.");
	
		$confirmCode = trim($confirmCode);
		$confirmName = trim($confirmName);
		
		if(strlen($confirmName) > 30)
			$confirmName = substr($confirmName, 0, 30);
		
		if(empty($confirmCode))
			return "You forgot to submit a confirmation code."; 

		if(empty($confirmName))
			return "You forgot to submit your name for confirmation."; 
			
		if(strtoupper($confirmCode) != strtoupper($this->getGangCode()))
			return "The Confirmation code does not match this Gang Run Object.";
		
		
		$projectIDarr = $this->getProjectIDlist();
		
		
		// Make sure that all of them have a "Plated" status.  It is OK to have a project with a Canceled Status though.
		foreach($projectIDarr as $thisProjID){
		
			$projectStatus = ProjectOrdered::GetProjectStatus($this->_dbCmd, $thisProjID);
			
			if(!in_array($projectStatus, array("E", "C", "F")))
				return "Can not confirm this GangRun because P" . $thisProjID . " has a status of " . ProjectStatus::GetProjectStatusDescription($projectStatus);	
		}
		
		
		
		// As long as the order hasn't been cancled, chang the status to Printed.
		foreach($projectIDarr as $thisProjID){
		
			$projectStatus = ProjectOrdered::GetProjectStatus($this->_dbCmd, $thisProjID);
			
			if(in_array($projectStatus, array("C", "F")))
				continue;
				
			ProjectOrdered::ChangeProjectStatus($this->_dbCmd, "T", $thisProjID);
			
			ProjectHistory::RecordProjectHistory($this->_dbCmd, $thisProjID, ("GangRun G" . $this->_gangRunID . " confirmed Printed by " . $confirmName), $userID);
		}
		
		
		$this->_confirmedBy = $confirmName;
		
		// Mark this Gang Run as "C"onfirmed.
		$this->_gangRunStatus = "C";
		
		// Update the Database.
		$this->updateGangRun();
		
		return "";
		
	}
	
	// Returns any Plated status back to "P"roofed.
	// It will also delete the Entry in the GangRun table.
	// If some of the Jobs made there way into the Shop and were confirmed as Printed (manually) ... some type of partial mix-up... then it is OK to cancel a batch to make it go away.
	function cancelGangRun($userID){
	
		$this->_ensureThatGangInitialized();
		
		
		$projectIDarr = $this->getProjectIDlist();
		
		foreach($projectIDarr as $thisProjID){
		
			$projectStatus = ProjectOrdered::GetProjectStatus($this->_dbCmd, $thisProjID);
			
			// Plat'E'd
			if($projectStatus != "E")
				continue;
	
			ProjectOrdered::ChangeProjectStatus($this->_dbCmd, "P", $thisProjID);
			
			ProjectHistory::RecordProjectHistory($this->_dbCmd, $thisProjID, ("GangRun G" . $this->_gangRunID . " was Canceled."), $userID);
			ProjectHistory::RecordProjectHistory($this->_dbCmd, $thisProjID, "P", $userID);
		}
		
		$this->_dbCmd->Query("DELETE FROM gangruns WHERE ID=" . $this->_gangRunID);
		
		$this->_dbCmd->Query("DELETE FROM gangrunlinks WHERE GangRunID=" . $this->_gangRunID);
		
		$this->_gangRunID = null;
	}
	
	
	// A special code (usually printed as a barcode) to indentify this batch.
	function getGangCode(){
		
		if(empty($this->_gangRunID))
			return "G-undefined";
	
		return "G" . $this->_gangRunID;
	}

	

	
	// returns True if the Project was able to be added... False otherise.
	function addProject($projectID){
	
		$this->_ensureThatGangInitialized();
		
		$projectObj = ProjectBase::getProjectObjectByViewType($this->_dbCmd, "ordered", $projectID);
		
		// Our PDF profiles are based upon the Production Product ID.
		$productID = Product::getProductionProductIDStatic($this->_dbCmd, $projectObj->getProductID());
		
		$projectQuantity = $projectObj->getQuantity();
		
		$sideCount = $projectObj->getArtworkSidesCount();
		
		if($projectObj->isVariableData())
			return false;
		
		
		// If this is a Single-Sided gang run... Double-sided jobs can not be printed with it.
		if(!$this->_duplexFlag && $sideCount > 1)
			return false;
		
		
		if(!$this->_forceQuantityFlag){
		
			// If the project quantity is not evenly divisible by the Sheet Count of the gang run... then it isn't possible for this project to be added.
			// For Example:  You can't print a 100 card business card run on a 500 sheet count run.  And you can't print an order for 750 card on a 500 quantity sheet run... 
			// ....but you can print a 500 card order on 100 sheet count run because it can take up 5 spaces.
			if($projectQuantity % $this->_sheetQuantity)
				return false;

			$numberOfSpacesForProject = $projectQuantity / $this->_sheetQuantity;
		}
		else{
			// If the Project can fit in the number of spaces evenly for the Gang Run sheet quantity... then just have it fill up the remainder (so we may have to throw some away).
			$numberOfSpacesForProject = ceil($projectQuantity / $this->_sheetQuantity);
		}
		
		
		// Based on the Product Number and the number of spaces that this Project requires, this method call will tell us if there is room for it in one of the PDF Sub Profiles (emedded within the SuperPDFprofile).
		// The Product Options may also prevent a Project from being included on Sub Profiles.
		$pdfProfileIDtoPutProjectIn = $this->_getPDFprofileIDthatHasRoom($productID, $numberOfSpacesForProject, $projectObj->getOptionsAliasStr());
		
		if(empty($pdfProfileIDtoPutProjectIn))
			return false;
		
		
		// Looks like we can add the project.  Create a 2D array entry
		$arrayNum = sizeof($this->_projectPositions);
		
		$this->_projectPositions[$arrayNum] = array();
		
		$this->_projectPositions[$arrayNum]["ProjectID"] = $projectID;
		$this->_projectPositions[$arrayNum]["ProductID"] = $productID;
		$this->_projectPositions[$arrayNum]["PDFprofileID"] = $pdfProfileIDtoPutProjectIn;
		$this->_projectPositions[$arrayNum]["NumSpaces"] = $numberOfSpacesForProject;
		$this->_projectPositions[$arrayNum]["IsDuplexFlag"] = ($sideCount > 1) ? true : false;
	
		$subPDFprofileObj = $this->_superProfileObj->getSubPDFprofileObj($pdfProfileIDtoPutProjectIn);
		$canvasAreaForProduct = $subPDFprofileObj->getUnitCanvasAreaPicas();
		
		$this->_squarePicasUsedOnFront += $canvasAreaForProduct * $numberOfSpacesForProject;
		
		
		// Find out how many unique Picas are beeing utilized.  If the Run is Duplex and the Project is single-sided... then only count the front side.
		// If the artwork is double-sided then count the area of both the front and the back.
		// If a project is occupying more than 1 spot... only count the first spot.
		if($this->_duplexFlag){
		
			if($sideCount > 1){
				$this->_squarePicasUnique += $canvasAreaForProduct * 2;
				$this->_projectPositions[$arrayNum]["NumSpacesOnBack"] = $numberOfSpacesForProject;
			}
			else{
				$this->_squarePicasUnique += $canvasAreaForProduct;
				$this->_projectPositions[$arrayNum]["NumSpacesOnBack"] = 0;
			}
		}
		else{
			$this->_projectPositions[$arrayNum]["NumSpacesOnBack"] = 0;
			$this->_squarePicasUnique += $canvasAreaForProduct;
		}
		
		return true;
		
		
	}
	
	// If you don't want the system to figure out the details automatically from a Project ID
	// This can be useful for generating a Super PDF profile Preview
	// You must have intricate knowledge of how the Super PDF profile is constucted before calling this method or you could cause weird errors.
	function manuallySetProjectIDdetails($projectID, $spacesForProject, $subPDFprofileID){
		
		// Looks like we can add the project.  Create a 2D array entry
		$arrayNum = sizeof($this->_projectPositions);
		$this->_projectPositions[$arrayNum] = array();
		
		$this->_projectPositions[$arrayNum]["ProjectID"] = $projectID;
		$this->_projectPositions[$arrayNum]["ProductID"] = PDFprofile::getProductIDfromProfileID($this->_dbCmd, $subPDFprofileID);
		$this->_projectPositions[$arrayNum]["PDFprofileID"] = $subPDFprofileID;
		$this->_projectPositions[$arrayNum]["NumSpaces"] = intval($spacesForProject);
		$this->_projectPositions[$arrayNum]["IsDuplexFlag"] = false;
	}
	
	// Returns a unique list of ProjectID's in this GangObject.
	// Pass in a product ID if you want to limit the ProjectIDs to those
	function getProjectIDlist($productID = null){
	
		$projectListArr = array();

		foreach($this->_projectPositions as $thisProjectPos){
			
			if(!empty($productID) && $productID != $thisProjectPos["ProductID"])
				continue;
			
			$projectListArr[] = $thisProjectPos["ProjectID"];
			
		}

		return $projectListArr;
	}
	
	
	// Boolean... check to see if the run is full so you don't waste resources in a large project list.
	function fullRun(){
	
		if($this->getTotalSpacesOccupiedOnFront() == $this->_superProfileObj->getTotalSpacesForAllProducts())
			return true;
		else
			return false;
	}
	
	
	function getSuperProfileID(){
		return $this->_superProfileID;
	}

	function getSuperProfileObj(){
		return $this->_superProfileObj;
	}
	
	
	function getForcedQuantity(){
		return $this->_forceQuantityFlag;
	}
	
	

	

	// At 1,000 quantity it doesn't matter if we are wasting paper...  It matters if we are careless with aluminum plates.
	// At 20,000 impressions we start to care less about wasting a plate and more about wasting paper.
	// This method returns a score between 1 and 100.  
	// At 20,000 Quantity... the "Fullness" affects the economic rating with full force... the Efficiency does nothing.
	// At 1,000 Quantity the "Efficiency" has a direct effect on the economic rating and the "Fullness" factor has no weight.
	// This is a sliding X scale between each of these points... somewhere in the middle is averegaing the 2 scores but giving more weight to one over the other.
	function getEcomicRating(){
	
		$this->_ensureThatGangInitialized();
		
		
		// If we have a Sheet Quantity of 0... we are forcing a Gang Run (1 per slot) without knowing the actual quantity that will be run.
		// In this case, let's just say how full the page is.
		if($this->_sheetQuantity == 0)
			return $this->getFullness();
		
		
	
		$lowQuan = 50;
		$highQuan = 5000;
		
		
		$quanDifference = $highQuan - $lowQuan;
		
		$fullnessMultiplier = ($this->_sheetQuantity - $lowQuan ) / $quanDifference;
		$efficiencyMultiplier = ($highQuan -  $this->_sheetQuantity) / $quanDifference;
		
		// The multipliers can never exceed 100% or go lower than 0%
		if($fullnessMultiplier > 1)
			$fullnessMultiplier = 1;
		else if($fullnessMultiplier < 0)
			$fullnessMultiplier = 0;
		if($efficiencyMultiplier > 1)
			$efficiencyMultiplier = 1;
		else if($efficiencyMultiplier < 0)
			$efficiencyMultiplier = 0;
		
		$fullnessValue = $this->getFullness();
		$efficiencyValue = $this->getEfficiency();
			
		// Both of these will be a number between 1 and 100.
		$fullnessPoints = round($fullnessMultiplier * $fullnessValue);
		$efficiencyPoints = round($efficiencyMultiplier * $efficiencyValue);
		
		
		// If both ratings were 100% ... what is the highest score we could achieve based upon our multipliers (based upon quantity)
		$possibleFullnessPoints = round($fullnessMultiplier * 100);
		$possibleEfficiencyPoints = round($efficiencyMultiplier * 100);
		
		$totalPointsEarned = $fullnessPoints + $efficiencyPoints;
		$totalPointsPossible = $possibleFullnessPoints + $possibleEfficiencyPoints;
		

		$economicPoints = round($totalPointsEarned / $totalPointsPossible * 100);
		
		return $economicPoints;
	}
	
	
	
	// Get back number between 1 and 5 telling us how economical the batch is.  (1 is the best).
	// It is unlikely that we could ever fill up 100% of a sheet because of margins and stuff, so this will take that into account.
	function getEconomicGrade(){
	
		$economicRating = $this->getEcomicRating();
		
		if($economicRating >= 75)
			return 1;
		else if($economicRating >= 60)
			return 2;
		else if($economicRating >= 40)
			return 3;
		else if($economicRating >= 20)
			return 4;
		else
			return 5;
	}
	
	
	// Returns an image representing the economic grade.
	function getEconomicGradeImage($path){
		
		$grade = $this->getEconomicGrade();
		
		return $path . "gang_grade_" . $grade . ".gif";
	
	}
	

	
	
	// returns an integer between 0 and 100 representing a percentage of fullness.
	// Wasting paper becomes a big deal on larger orders... where card stock is thrown away.
	// On short runs... waste plates is important... but throwing away some scrap paper isn't so important.
	// Therefore the Fullness does not take into account if we are printing "blank backsides"... it is just a waste of a plate. (Duplex is not factorered in).
	// This method "getFullness()" is meant to provide most of its meaning in the larger quantity ranges... in which wasting plates in not an issue.
	// It meausres fullness of Square Inches... not percentage of Slots occupied.
	function getFullness(){
	
		$this->_ensureThatGangInitialized();
		
		$fullNessValue = round($this->_squarePicasUsedOnFront / $this->_superProfileObj->getParentSheetPicasArea() * 100);
		
		// this could happen if someone mistakenly set the Product Size much bigger than the what is able to fit on the canvas
		if($fullNessValue > 100)
			$fullNessValue = 100;
		
		return $fullNessValue;
	}
	
	
	// Returns a number between 1 and 100 indicating how much paper will be waste (including Scap area and margins).
	function getPaperWasted(){
	
		return 100 - $this->getFullness();
	
	}
	
	// Returns a number between 1 and 100 indicating how much aluminum plates will be wasted.
	function getPlatesWasted(){
	
		return 100 - $this->getEfficiency();
	
	}
	
	
	// returns an integer between 0 and 100 representing a percentage of efficiency (combining the front and the back).
	// If there are 10 cards in a slot on a duplex run... and only 1 single-slot is used (blank backside) is in the gang... then it is 5% efficiency.
	// If one order is taking up the entire front and backside of the entire gang run (with 10 slots)... then it is a 10% efficiency.
	// One very large double-sided job... hogging all of the spaces would be 10% efficient.
	function getEfficiency(){
	
		$this->_ensureThatGangInitialized();
	
		$totalPrintableArea = $this->_superProfileObj->getParentSheetPicasArea();
	
		if($this->_duplexFlag)
			$totalPrintableArea *= 2;
			
		$efficiencyValue = round($this->_squarePicasUnique / $totalPrintableArea * 100);

		// this could happen if someone mistakenly set the Product Size much bigger than the what is able to fit on the canvas
		if($efficiencyValue > 100)
			$efficiencyValue = 100;
		
		return $efficiencyValue;
	}
	
	
	function getDuplexFlag(){
	
		$this->_ensureThatGangInitialized();
	
		return $this->_duplexFlag;
	
	}
	
	function getSheetQuantity(){
	
		$this->_ensureThatGangInitialized();
	
		return $this->_sheetQuantity;
	}
	
	
	

	// If you haven't saved the GangRun to the DB... then you can possibly switch to Simplex

	// You should call this after adding Projects to the GangRun.
	// The reason is that you could Specify a Duplex run... but the Backside is totally empty.
	function possiblySwitchToSimplex(){

		
		if(!empty($this->_gangRunID))
			throw new Exception("Error in method GangRun->possiblySwitchToSimplex. You can't try switching to simplex after a GangRunID has been created.");
		
		$countOnBack = $this->getTotalSpacesOccupiedOnBack();
		
		if(empty($countOnBack))
			$this->_duplexFlag = false;
	
	}

	
	

	
	
	// Sets up the PDF parameter object... which is passed into the PDF generation object for controlling rows/cols, spacing, etc.
	function getPDFparameterObject($pdfProfileID){
	
		$this->_ensureThatGangInitialized();
		
		$pdfProfileObj = $this->_superProfileObj->getSubPDFprofileObj($pdfProfileID);
		
		$pdfParamObj = new PDFparameters($pdfProfileObj);
		
		
		$pdfParamObj->setShowBleedSafe(false);
		$pdfParamObj->setShowCutLine(false);
		$pdfParamObj->setRotatecanvas(0);
		$pdfParamObj->setBleedtype("N");
		
		return $pdfParamObj;
	}
	
	
	
	function getFileForDownload($flushProgressOutput, $pdfType){
	
		if($pdfType == "Front"){
			$pdfFile =& $this->getFrontSidePDF($flushProgressOutput);
			$this->_writePDFfileToDisk($pdfFile, $this->getFileNameForFrontPDF());
			return $this->getFileNameForFrontPDF();
		}
		else if($pdfType == "Back"){
			$pdfFile =& $this->getBackSidePDF($flushProgressOutput);
			$this->_writePDFfileToDisk($pdfFile, $this->getFileNameForBackPDF());
			return $this->getFileNameForBackPDF();
		}
		else if($pdfType == "Cover"){
			$pdfFile =& $this->getCoverSheetPDF($flushProgressOutput);
			$this->_writePDFfileToDisk($pdfFile, $this->getFileNameForCoverPDF());
			return $this->getFileNameForCoverPDF();
		}
		else if($pdfType == "Tar"){
			return $this->createTarBall($flushProgressOutput);
		}
		else{
			throw new Exception("Illegal File type in method GangRun->getFileForDownload.");
		}
	}
	

	
	// Generates the PDF and returns binary data.
	// Once the PDF is generated... it will stay in memory... so you can call this method repeatadly afterwards if needed.
	function &getFrontSidePDF($flushProgressOutput, $forceGeneration = false){
	
		if(empty($this->_projectPositions))
			throw new Exception("You can not generate a Front Side PDF until there is at least one Project ID in this GangRun.");
		
		if(!isset($this->_pdfGeneratedArr["FRONT"]) || $forceGeneration){
			
			// If the gang run ID has not been saved to the DB... then it means we have to generate the PDF from scratch.
			if(empty($this->_gangRunID) || $this->_frontStatus == 'A' || $forceGeneration){
				$this->_generatePDFgangByType("FRONT", $flushProgressOutput);
			}
			else{
				$this->_dbCmd->Query("SELECT FrontPDF FROM gangruns WHERE ID=" . $this->_gangRunID);
				$this->_pdfGeneratedArr["FRONT"] = $this->_dbCmd->GetValue();
				
				// If the database was cleared out, we need to re-generate
				if(empty($this->_pdfGeneratedArr["FRONT"]))
					$this->_generatePDFgangByType("FRONT", $flushProgressOutput);
			}
		}
		
		return $this->_pdfGeneratedArr["FRONT"];	
	}
	
	// Generates the PDF and returns a binary data.
	// Once the PDF is generated... it will stay in memory... so you can call this method repeatadly afterwards if needed.
	function &getBackSidePDF($flushProgressOutput, $forceGeneration = false){
	
		if(!$this->_duplexFlag)
			throw new Exception("Error in method GangRun->getBackSide.  You can't call this method unless the duplex flag has been set.");
			
		if(empty($this->_projectPositions))
			throw new Exception("You can not generate a Front Side PDF until there is at least one Project ID in this GangRun.");
		
		if(!isset($this->_pdfGeneratedArr["BACK"]) || $forceGeneration){
			
			// If the gang run ID has not been saved to the DB... then it means we have to generate the PDF from scratch.
			if(empty($this->_gangRunID) || $this->_backStatus == 'A' || $forceGeneration){
				$this->_generatePDFgangByType("BACK", $flushProgressOutput);
			}
			else{
				$this->_dbCmd->Query("SELECT BackPDF FROM gangruns WHERE ID=" . $this->_gangRunID);
				$this->_pdfGeneratedArr["BACK"] = $this->_dbCmd->GetValue();
				
				// If the database was cleared out, we need to re-generate
				if(empty($this->_pdfGeneratedArr["BACK"]))
					$this->_generatePDFgangByType("BACK", $flushProgressOutput);
			}
		}
		
		
		return $this->_pdfGeneratedArr["BACK"];
	}
	
	
	// Generates the PDF and returns binary data.
	// Once the PDF is generated... it will stay in memory... so you can call this method repeatadly afterwards if needed.
	function &getCoverSheetPDF($flushProgressOutput, $forceGeneration = false){

		if(empty($this->_projectPositions))
			throw new Exception("You can not generate a CoverSheet PDF until there is at least one Project ID in this GangRun.");
		
		
		if(!isset($this->_pdfGeneratedArr["COVER"]) || $forceGeneration){
			
			// If the gang run ID has not been saved to the DB... then it means we have to generate the PDF from scratch.
			if(empty($this->_gangRunID) || $this->_coverStatus == 'A' || $forceGeneration){
				$this->_generatePDFgangByType("COVER", $flushProgressOutput);
			}
			else{
				$this->_dbCmd->Query("SELECT CoverPDF FROM gangruns WHERE ID=" . $this->_gangRunID);
				$this->_pdfGeneratedArr["COVER"] = $this->_dbCmd->GetValue();
				
				// If the database was cleared out, we need to re-generate
				if(empty($this->_pdfGeneratedArr["COVER"]))
					$this->_generatePDFgangByType("COVER", $flushProgressOutput);
			}
		}
					
		return $this->_pdfGeneratedArr["COVER"];
	}
	
	// Returns a Preview of the Gang Run for the Super PDF profile.
	function &getPrevewPDF(){
	
		$this->_generatePDFgangByType("PREVIEW", "none");

		return $this->_pdfGeneratedArr["PREVIEW"];	
	}

	function getFileNameTarBall(){
		return "GangRun_" . $this->getTimeStampString() . ".tar";
	}
	
	
	// Creates a TarBall of the Front (maybe Back) and the Cover PDF file
	// Returns the FileName.
	// $flushProgressOuput only works when a PDF files needs to be generated.
	// returns the FileName (without path).
	// If the TarBall is still sitting on Disk... then it will not try to regenerate it.  Let the Cron clean out the TarBalls.
	function createTarBall($flushProgressOutput){
	

		$TarFileName = DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $this->getFileNameTarBall();
		
		if(file_exists($TarFileName))
			return $this->getFileNameTarBall();
	
		
		$PDFfilenamesArr = array();
		
		// Front
		$frontSidePDF =& $this->getFrontSidePDF($flushProgressOutput);
		$this->_writePDFfileToDisk($frontSidePDF, $this->getFileNameForFrontPDF());
		$PDFfilenamesArr[] = DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $this->getFileNameForFrontPDF();
		
		// (Maybe) Back
		if($this->_duplexFlag){
			$backSidePDF =& $this->getBackSidePDF($flushProgressOutput);
			$this->_writePDFfileToDisk($backSidePDF, $this->getFileNameForBackPDF());
			$PDFfilenamesArr[] = DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $this->getFileNameForBackPDF();
		}
		
		// Cover
		$coverPDF =& $this->getCoverSheetPDF($flushProgressOutput);
		$this->_writePDFfileToDisk($coverPDF, $this->getFileNameForCoverPDF());
		$PDFfilenamesArr[] = DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $this->getFileNameForCoverPDF();
		

		$UnixTarCommand = Constants::GetTarFileListCommand($TarFileName , $PDFfilenamesArr);
		system($UnixTarCommand);
		chmod ($TarFileName, 0666);
		
		return $this->getFileNameTarBall();
	}


	
	
	
	// Returns the total number of spaces being used on the Front-Side
	// This method counts the slots if a project is taking up more than one.
	function getTotalSpacesOccupiedOnFront($productID = null){
	
		$totalSpacesUsed = 0;
		
		foreach($this->_projectPositions as $thisProjectPos){
		
			if(!empty($productID) && $productID != $thisProjectPos["ProductID"])
				continue;
		
			$totalSpacesUsed += $thisProjectPos["NumSpaces"];
		}
		
		return $totalSpacesUsed;
	}
	
	
	// Returns the total number of spaces being used on the Back-Side
	// This method counts the slots if a project is taking up more than one.
	// If this is a Duplex Run and the artwork is Single-Sided... The the spaces on the Back do not get counted.
	function getTotalSpacesOccupiedOnBack($productID = null){
	
		$totalSpacesUsed = 0;
		
		foreach($this->_projectPositions as $thisProjectPos){
		
			if(!empty($productID) && $productID != $thisProjectPos["ProductID"])
				continue;
		
			$totalSpacesUsed += $thisProjectPos["NumSpacesOnBack"];
		}
		
		return $totalSpacesUsed;
	}
	
	
	
	
	function getHTMLscoreDesc(){
	
		$scoreHTML = "<img src='" . $this->getEconomicGradeImage("./images/") . "'> - Score: " . $this->getEcomicRating() . "%";
		$scoreHTML .= "<br>";
		$scoreHTML .= "Paper Wasted " . $this->getPaperWasted() . "%<br>";
		$scoreHTML .= "Plates Wasted " . $this->getPlatesWasted() . "%";
		
		return $scoreHTML;
	}

	
	function getHTMLprofileDesc(){
		
		$this->_ensureThatGangInitialized();
		
		$retHTML = "<b><font color='#990000'>" . WebUtil::htmlOutput(SuperPDFprofile::getSuperProfileNameByID($this->getSuperProfileID())) . "</font></b><br><img src='./images/transparent.gif' width='5' height='8'><br>";
		
		$productIDs = $this->_superProfileObj->getProductIDsInSuperProfile();

		$frontSlots = "";
		$backSlots = "";

		foreach($productIDs as $thisProductID){

			if(!empty($frontSlots))
				$frontSlots .= "<br>";				

			$frontSlots .= "<font style='font-size:12px;'><u>FRONT</u></font><br>" . $this->_getHTMLSlotsDesc($thisProductID, true);
		}
		
		
		if($this->_duplexFlag){
			
			$frontSlots .= "<br>";
		
			foreach($productIDs as $thisProductID){

				if(!empty($backSlots))
					$backSlots .= "<br>";

				$backSlots .= "<img src='' width='5' height='5'><br><font style='font-size:12px;'><u>BACK</u></font><br>" . $this->_getHTMLSlotsDesc($thisProductID, false);
			}
		}
		
		
		$retHTML .= $frontSlots . $backSlots;
		
		
		
		return $retHTML;
	}
	
	function getOptionsHTML(){
	
		$this->_ensureThatGangInitialized();
		
		$paperTypeBoxStyle = "style='width:250px; height: 24px; font-size: 15px; font-face:arial; font-weight:bold; color:#000066;'";
		$batchNotesBoxStyle = "style='width:250px; height: 24px; font-size: 15px; font-face:arial; font-weight:bold; color:#880000;'";
		
		$retHTML = "<b>Paper Type:</b><br><input  type='text' name='materialDesc' $paperTypeBoxStyle value='" . WebUtil::htmlOutput($this->_materialDesc) . "'><br>";
		$retHTML .= "Batch Notes:<br><input type='text' name='batchNotes' $batchNotesBoxStyle value='" . WebUtil::htmlOutput($this->_batchNotes) . "'><br>";
		
		
		$pressNamesArr = $this->_superProfileObj->getPrintingPressNames();
		
		// On new Runs... (not saved to the DB)... if there is only 1 printing press available, then make that the default... otherwise make "no printer" the default.
		if(empty($this->_gangRunID) && sizeof($pressNamesArr) == 1)
			$printingPressIDselected = key($pressNamesArr);
		else
			$printingPressIDselected = $this->_printingPressID;
		
		$printerDropDown = array("0"=>"No Printer");
		foreach($pressNamesArr as $pressID => $pressName)
			$printerDropDown[$pressID] = $pressName;
			
		$printerDropDownHTML = Widgets::buildSelect($printerDropDown, $printingPressIDselected, "printingPressID");
		
		$retHTML .= $printerDropDownHTML;		
		
		return $retHTML;
	}
	
	
	function getPDFfilesHTML(){
	
		if(empty($this->_gangRunID))
			throw new Exception("Error in Method GanRun->getPDFfilesHTML.  A GangRunID must be loaded before calling this method.");
		
		$retHTML = "<img src='./images/icon-zip.gif' width='16' height='16' align='absmiddle' border='0'> <a class='BlueRedLinkRecord' href='javascript:DownloadZip(" . $this->_gangRunID . ");'><font style='font-size:10px;'>" . WebUtil::htmlOutput($this->getFileNameTarBall()) . "</font></a><br><br>";
		$retHTML .= "<img src='./images/icon-pdf.gif' width='16' height='16' align='absmiddle' border='0'> <a class='BlueRedLinkRecord' href='javascript:ShowGangRunCover(" . $this->_gangRunID . ");'><font style='font-size:10px;'>" . WebUtil::htmlOutput($this->getFileNameForCoverPDF()) . "</font></a><br><br>";
		

		$retHTML .= $this->_getPDFfileLinkThumbnail($this->getFileNameForFrontPDF(), "Front");
		
	
		if($this->_duplexFlag)
			$retHTML .= $this->_getPDFfileLinkThumbnail($this->getFileNameForBackPDF(), "Back");
	
		return $retHTML;
	}
	
	private function _getPDFfileLinkThumbnail($fileName, $sideDesc){
		
		return "<div id='gangRun".$sideDesc."Div" . $this->_gangRunID . "' class='hiddenDHTMLwindow' onMouseOver='showGangRunThumbWindow(" . $this->_gangRunID . ", true, \"".$sideDesc."\");' onMouseOut='showGangRunThumbWindow(" . $this->_gangRunID . ", false, \"".$sideDesc."\");'>
				<img src='./images/icon-pdf.gif' width='16' height='16' align='absmiddle' border='0'> <a class='BlueRedLinkRecord' href='javascript:ShowGangRun".$sideDesc."(" . $this->_gangRunID . ");'><font style='font-size:10px;'>" . WebUtil::htmlOutput($fileName) . "</font></a>
				<span style='visibility:hidden; left:0px; top:20' id='gangRun".$sideDesc."Span" . $this->_gangRunID . "'>
				<table width='1' cellpadding='0' cellspacing='0' border='1' style='border-color:#000000;  filter:progid:DXImageTransform.Microsoft.Alpha( Opacity=0, FinishOpacity=90, Style=1, StartX=0, FinishX=50, StartY=0, FinishY=50)'>
				<tr><td><img src='./ad_printerQueue_thumbnail.php?gangRunID=" . $this->_gangRunID . "&fileDescType=".$sideDesc."'></td></tr>
				</table></span></div>&nbsp;";
	}
	
	
	function getMaterialDesc(){
	
		$this->_ensureThatGangInitialized();
	
		return $this->_materialDesc;
	
	}
	
	function getBatchNotes(){
	
		$this->_ensureThatGangInitialized();
	
		return $this->_batchNotes;
	
	}
	
	function setMaterialDesc($x){
	
		$this->_ensureThatGangInitialized();
		
		if(strlen($x) > 255)
			$x = substr($x, 0, 255);
		
		$this->_materialDesc = $x;
	}
	
	function setBatchNotes($x){
	
		$this->_ensureThatGangInitialized();

		if(strlen($x) > 255)
			$x = substr($x, 0, 255);
		
		$this->_batchNotes = $x;
	}
	
	
	
	
	function updateFrontSidePDFinDB($flushProgressOutput, $forceGeneration = false){
	
		if(empty($this->_gangRunID))
			throw new Exception("Error in method updateFrontSidePDFinDB. The GangrunID has not been initialized yet.");
	
		// Let the DB know we are about to start generating the file.
		$this->_dbCmd->UpdateQuery("gangruns", array("FrontStatus"=>"S", "LastUpdated"=>time()), "ID=" . $this->_gangRunID);

		// Generate the Artwork and then save to DB.
		$frontSidePDF =& $this->getFrontSidePDF($flushProgressOutput, $forceGeneration);
		$this->_frontThumbnailJPG =& $this->_convertPDFtoThumbnailJPG($frontSidePDF); 

		$this->_frontStatus = "G";

		$this->_dbCmd->UpdateQuery("gangruns", array("FrontThumbJPG"=>$this->_frontThumbnailJPG, 
								"FrontStatus"=>$this->_frontStatus,
								"LastUpdated"=>time()), 
									"ID=" . $this->_gangRunID);
		$this->_dbCmd->UpdateQuery("gangruns", array("FrontPDF"=>$frontSidePDF), "ID=" . $this->_gangRunID);
	
	}
	
	function updateBackSidePDFinDB($flushProgressOutput, $forceGeneration = false){
	
		if(empty($this->_gangRunID))
			throw new Exception("Error in method updateBackSidePDFinDB. The GangrunID has not been initialized yet.");
	
		// Let the DB know we are about to start generating the file.
		$this->_dbCmd->UpdateQuery("gangruns", array("BackStatus"=>"S", "LastUpdated"=>time()), "ID=" . $this->_gangRunID);

		// Generate the Artwork and then save to DB.
		$backSidePDF =& $this->getBackSidePDF($flushProgressOutput, $forceGeneration);
		$this->_backThumbnailJPG =& $this->_convertPDFtoThumbnailJPG($backSidePDF); 

		$this->_backStatus = "G";

		$this->_dbCmd->UpdateQuery("gangruns", array("BackThumbJPG"=>$this->_backThumbnailJPG, 
								"BackStatus"=>$this->_backStatus,
								"LastUpdated"=>time()), 
									"ID=" . $this->_gangRunID);
		$this->_dbCmd->UpdateQuery("gangruns", array("BackPDF"=>$backSidePDF), "ID=" . $this->_gangRunID);
	}
	
	function updateCoverSheetPDFinDB($flushProgressOutput, $forceGeneration = false){

		if(empty($this->_gangRunID))
			throw new Exception("Error in method updateCoverSheetPDFinDB. The GangrunID has not been initialized yet.");

		// Let the DB know we are about to start generating the file.
		$this->_dbCmd->UpdateQuery("gangruns", array("CoverStatus"=>"S", "LastUpdated"=>time()), "ID=" . $this->_gangRunID);

		// Generate the Artwork and then save to DB.
		$coverSheetPDF =& $this->getCoverSheetPDF($flushProgressOutput, $forceGeneration);
	
		$this->_coverStatus = "G";

		$this->_dbCmd->UpdateQuery("gangruns", array( "CoverStatus"=>$this->_coverStatus,
								"LastUpdated"=>time()), 
									"ID=" . $this->_gangRunID);
		$this->_dbCmd->UpdateQuery("gangruns", array("CoverPDF"=>$coverSheetPDF), "ID=" . $this->_gangRunID);
	}
	
	
	// ---------------------------------------   Private Methods ------------------------------------------


	private function _getHTMLSlotsDesc($productID, $frontFlag){
		
		$this->_ensureThatGangInitialized();
		
		$productIDsInSuperProfile = $this->_superProfileObj->getProductIDsInSuperProfile();
		
		// Now build a list of how many Slots are empty for each product.
		// Front Side is always a given.
		$slotsHTML = "";
		foreach($productIDsInSuperProfile as $thisProductID){
		
			if($productID != $thisProductID)
				continue;

			if($frontFlag)
				$spacesUsed = $this->getTotalSpacesOccupiedOnFront($thisProductID);
			else
				$spacesUsed = $this->getTotalSpacesOccupiedOnBack($thisProductID);

			if(!empty($slotsHTML))
				$slotsHTML .= "<br>";
				

			$slotsHTML .= WebUtil::htmlOutput(Product::getRootProductName($this->_dbCmd, $productID)) . ": <font color='#000000'>" . $spacesUsed . " / " . $this->_superProfileObj->getTotalSpacesForProduct($thisProductID) . "</font>";
		}

		return $slotsHTML;
	}
	


	// Returns a Gang Grouping Object by the PDF profile ID.
	// It is possible that a wasted Gang Run may have all spaces empty for a sub PDF profile... in which case a GangGrouping object would be empty.
	private function _getGangGroupingObj($pdfProfileID){
	
		$gangGroupObj = new GangGrouping($this->_dbCmd, $pdfProfileID, $this->_superProfileObj->getOptionsLimiterOfSubPDFprofile($pdfProfileID));
		
		// Let the Gang Grouping Object know if we are in Preview Mode.
		$gangGroupObj->setPreviewMode($this->_previewModeFlag);
		
		// It is possible that there are multiple PDFprofiles IDs with matching Product IDs
		foreach($this->_projectPositions as $thisProjectPos){
			if($thisProjectPos["PDFprofileID"] == $pdfProfileID)
				$gangGroupObj->addProject($thisProjectPos["ProjectID"], $thisProjectPos["NumSpaces"]);
		}
		
		return $gangGroupObj;
	}


	// If the file already exists it will not try to re-write it.
	private function _writePDFfileToDisk(&$pdfData, $fileName){
	
		if(file_exists($fileName))
			return;
	
		$fp = fopen(DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $fileName, "w");
		fwrite($fp, $pdfData);
		fclose($fp);
	}




	// Pass in a Product ID and the number of spaced you need.
	// It will turn a PDF profile ID (if it still has room has room.
	// There can be multiple PDF profiles with the same Product ID
	// But Projects should not be split between different Sub-PDF Profiles.
	// Every Sub PDF profile may have an Options Limiter set on it.  So pass in the Options Alias of the Project for a possible restriction.
	private function _getPDFprofileIDthatHasRoom($productID, $numSpaces, $optionsAliasOfProject){

		$pdfProfileIDs = $this->_superProfileObj->getSubPDFProfileIDs();
		foreach($pdfProfileIDs as $pdfProfID){
			
			$optionsLimiterOnSubProfile = $this->_superProfileObj->getOptionsLimiterOfSubPDFprofile($pdfProfID);
			
			// If there is no Options Limiter saved on the Sub Profile, then we shouldn't try to limit.
			if(!empty($optionsLimiterOnSubProfile)){
				
				// Make sure that the Options Limiter of Profile is found somewhere inside of the Option Alias of the Project (case insensitive)
				if(!preg_match("/" . preg_quote($optionsLimiterOnSubProfile) . "/i", $optionsAliasOfProject))
					continue;
			}
			
			$pdfProfileObj = $this->_superProfileObj->getSubPDFprofileObj($pdfProfID);

			if($pdfProfileObj->getProductID() == $productID){
				
				$spacesLeftOnPDFprofile = $pdfProfileObj->getQuantity() - $this->_getTotalSpacesUsedOnPDFprofile($pdfProfID);
				
				if( $spacesLeftOnPDFprofile >= $numSpaces)
					return $pdfProfID;
			}
		}
		
		return null;
	}
	
	
	// Tells us how many spaces are used on a given PDF profile ID.
	// If no Projects have been added to this GangRun yet, then it will return 0
	private function _getTotalSpacesUsedOnPDFprofile($pdfProfileID){
		
		$totalSpacesTakenOnPDFprofile = 0;
		
		foreach($this->_projectPositions as $thisProjectPos){
			
			if($pdfProfileID == $thisProjectPos["PDFprofileID"])
				$totalSpacesTakenOnPDFprofile += $thisProjectPos["NumSpaces"];
		}
		
		return $totalSpacesTakenOnPDFprofile;
	}
	
	
	// Returns the JPG binary data by reference from a Binary PDF file.
	private function &_convertPDFtoThumbnailJPG(&$pdfFile){	
	
		$pdfOnDisk = tempnam (Constants::GetTempDirectory(), "GANGTHMB");
		
		// Put PDF in the temp file 
		$fp = fopen($pdfOnDisk, "w");
		fwrite($fp, $pdfFile);
		fclose($fp);

		// Make sure that we have permission to modify it with system comands 
		chmod ($pdfOnDisk, 0666);
	
		// Put brackets after the file name to specify the page number.  We are always interested in just the first page.
		$ConverImageCmd = Constants::GetUpperLimitsShellCommand(250, 45) . Constants::GetPathToImageMagick() . "mogrify -format jpeg -colorspace RGB -quality 80 -resize " . $this->_thumbMaxWidth . "x" . $this->_thumbMaxHeight . " " . $pdfOnDisk . "[0]";
		system($ConverImageCmd);
		
		ImageLib::OverwriteOriginalFileAfterImageMagickConversion($pdfOnDisk, "jpeg");
		
		$fd = fopen ($pdfOnDisk, "r");
		$ImageBinaryData = fread ($fd, filesize ($pdfOnDisk));
		fclose ($fd);
		
		unlink($pdfOnDisk);
		
		if($ImageBinaryData)
			return $ImageBinaryData;
		else
			return $ImageBinaryData;
	}
	
	
	
	// Generates the PDF (by type) and stores the PDF binary data within an array in this object.
	private function _generatePDFgangByType($pdfType, $flushProgressOutput){
			
		if($pdfType == "FRONT"){
			$sideNumber = 0;
			$frontSideFlag = true;
			$coverSheetFlag = false;
			$GangRunColorCodeObj = ColorLib::getRgbValues("#6699CC", false);  // Front Sides always have a Blue Color
		}
		else if($pdfType == "BACK"){
			$sideNumber = 1;
			$frontSideFlag = false;
			$coverSheetFlag = false;
			$GangRunColorCodeObj = ColorLib::getRgbValues("#CC9966", false);  // Orangy
		}
		else if($pdfType == "COVER"){
			$sideNumber = 0;
			$frontSideFlag = true;
			$coverSheetFlag = true;
			$GangRunColorCodeObj = ColorLib::getRgbValues("#66CC99", false);  // Greenish Teal	
		}
		else if($pdfType == "PREVIEW"){
			$sideNumber = 0;
			$frontSideFlag = true;
			$coverSheetFlag = false;
			$GangRunColorCodeObj = ColorLib::getRgbValues("#66CC33", false);  // Greenish Teal	
		}
		else{
			throw new Exception("Error in method _generatePDFgangByType.  The PDF type parameter is invalid.");
		}
		
	
		$superProfilePDFProfileIDs = $this->_superProfileObj->getSubPDFProfileIDs();
		
		
		$pdf = pdf_new();
		// Sets the license key, author, font paths, etc.
		PdfDoc::setParametersOnNewPDF($pdf);
		PDF_begin_document($pdf, "", "");
		
		

		// The Key is the ProjectID for all of these arrays.
		// All of these arrays are organized the same.
		$pdfPdiObjectsArr = array();
		$pdfPageObjectsArr = array();
		$PDFvirtualFilesArr = array();
		
		foreach($superProfilePDFProfileIDs as $thisPDFProfileID){
		
			$gangGroupingObj = $this->_getGangGroupingObj($thisPDFProfileID);
			
			$pdfParameterObj = $this->getPDFparameterObject($thisPDFProfileID);
			$pdfParameterObj->setPdfSideNumber($sideNumber);
			$pdfParameterObj->setSideDescription($pdfType);
			
			// For Previews, we want to see the Bleed, Safe, and Cut lines.
			if($pdfType == "PREVIEW"){
				$pdfParameterObj->setShowBleedSafe(true);
				$pdfParameterObj->setShowCutLine(true);
			}
	
			$gangPortionPDF = PdfDoc::GenerateGangSheet($this->_dbCmd, $pdfParameterObj, $sideNumber, $coverSheetFlag, $gangGroupingObj, "", $flushProgressOutput, $GangRunColorCodeObj);
			
			// Put the PDF data into a PDF lib virtual file
			$PFV_FileName = "/pfv/pdf/gangPorition" . $thisPDFProfileID;
			PDF_create_pvf( $pdf, $PFV_FileName, $gangPortionPDF, "");

			$PDI_completeDocumentObj = PDF_open_pdi($pdf, $PFV_FileName, "", 0);
			$PDI_PageObj = PDF_open_pdi_page($pdf, $PDI_completeDocumentObj, 1, "");

			$pdfPdiObjectsArr[$thisPDFProfileID] = $PDI_completeDocumentObj;
			$pdfPageObjectsArr[$thisPDFProfileID] = $PDI_PageObj;
			$PDFvirtualFilesArr[$thisPDFProfileID] = $PFV_FileName;
			
		}
		
		
		// Make a new page and paste all of the sub-profiles on top.
		pdf_begin_page($pdf, PdfDoc::inchesToPDF($this->_superProfileObj->getSheetWidth()), PdfDoc::inchesToPDF($this->_superProfileObj->getSheetHeight()));
	
	
		foreach($superProfilePDFProfileIDs as $thisPDFProfileID){
			
			$xCoordPicas = PdfDoc::inchesToPDF($this->_superProfileObj->getXcoordOfSubProfile($thisPDFProfileID, $frontSideFlag));
			$yCoordPicas = PdfDoc::inchesToPDF($this->_superProfileObj->getYcoordOfSubProfile($thisPDFProfileID));
		
			PDF_fit_pdi_page($pdf, $pdfPdiObjectsArr[$thisPDFProfileID], $xCoordPicas, $yCoordPicas, "");
		}
		
		
		
		// Draw the Barcode on the Front Page Only.
		if($pdfType == "FRONT" || $pdfType == "COVER" || $pdfType == "PREVIEW"){
			$shortBarcodeFont = pdf_findfont($pdf, "C128XS", "winansi", 1);
			$deckerFont = pdf_findfont($pdf, "Decker", "winansi", 1);

			pdf_save($pdf);
			pdf_translate($pdf, PdfDoc::inchesToPDF($this->_superProfileObj->getBarCodeX()), PdfDoc::inchesToPDF($this->_superProfileObj->getBarCodeY()));
			pdf_rotate($pdf, $this->_superProfileObj->getBarCodeRotateDeg());
			pdf_setfont($pdf, $shortBarcodeFont, 14);
			
			// Only show the Barcode on the Front Sheet.
			if($pdfType == "FRONT")
				pdf_show_xy($pdf, WebUtil::GetBarCode128($this->getGangCode()), 0, 0);
			
			pdf_setfont($pdf, $deckerFont, 8);
			
			
			pdf_show_xy($pdf, ("Quantity: " . GangRun::translateSheetQuantity($this->_sheetQuantity) . ",   TimeStamp: " . date("d-H-i-s", $this->_timeStampCreated)),  PdfDoc::inchesToPDF(1.2), 0);
			
			
			pdf_show_xy($pdf, ($this->_materialDesc),  PdfDoc::inchesToPDF(3.6), 0);
			
			if(!empty($this->_batchNotes)){
				pdf_setcolor($pdf, "both", "rgb", 0.6, 0, 0, 0);
				pdf_show_xy($pdf, ("Notes: " . $this->_batchNotes),  PdfDoc::inchesToPDF(5.5), 0);
			}
			
			pdf_restore($pdf);
		}
		
		
		
		
		pdf_end_page($pdf);


		// Get rid of the Virtual Filenames
		foreach($PDFvirtualFilesArr as $thisPFVfileName)
			PDF_delete_pvf($pdf, $thisPFVfileName);

		// Discard the Page objects.
		foreach($pdfPageObjectsArr as $pageObj)
			PDF_close_pdi_page($pdf, $pageObj);

		// Discard the PDI objects.
		foreach($pdfPdiObjectsArr as $thisPDIObj)
			PDF_close_pdi($pdf, $thisPDIObj);



		PDF_end_document($pdf, "");

		$pdfData = pdf_get_buffer($pdf);
		
		$this->_pdfGeneratedArr[$pdfType] = $pdfData;
		
	}

	

	private function _ensureThatGangInitialized(){
	
		if(empty($this->_superProfileID))
			throw new Exception("This GangRun Object has not been initialized yet.  It has to be loaded from the DB or it can be created new by specifying a sheet count and profileName.");
	}


}



?>
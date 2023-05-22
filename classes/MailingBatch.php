<?

class MailingBatch {


	private $_errorMessage;
	private $_dbCmd;
	private $_batchID;
	private $_userID;
	
	private $_createdOn;
	private $_createdBy;
	
	private $_originalDataFile;
	private $_importedDataFile;
	
	private $_importedDataLoadedFlag;
	private $_originalDataLoadedFlag;
	
	private $_originalLineCount;
	private $_importedLineCount;
	
	private $_projectIDsInBatch = array();
	
	private $_columnNames = array();
	private $_columnTrans = array();
	private $_columnsAndTransArr = array();
	
	private $_validSinglePostageRateLow;
	private $_validSinglePostageRateHigh;
	
	private $_minimumCombinedQuantity;
	



	###-- Constructor --###
	function MailingBatch(DbCmd $dbCmd, $userID){
		
		if(!preg_match("/^\d+$/", $userID))
			throw new Exception("Error in MailingBatch constructer, UserID must be digit.");

		$this->_dbCmd = $dbCmd;
		$this->_errorMessage = "";
		$this->_batchID = 0;
		$this->_userID = $userID;
		
		$this->_originalDataFile = "";
		$this->_importedDataFile = "";
		
		// Don't load the CSV files until something requests them... otherwise we could be storing Data in memory that never gets used.
		$this->_importedDataLoadedFlag = false;
		$this->_originalDataLoadedFlag = false;
		
		$this->_minimumCombinedQuantity = 200;
		
		$this->_projectIDsInBatch = array();
		
		
		// Define what is a valid range for postage.  It should never be more than $5 piece or less than 10 cents.
		$this->_validSinglePostageRateLow = 0.10;
		$this->_validSinglePostageRateHigh = 5.0;
		
		
		// Define the names of the fields used for importing/exporting from the mailing software.
		$this->_columnNames = array("Address1", "Address2", "City", "State", "Zip", "Postnet", "Country", "CIN", "Sort", "Zip4", "Postage", "ErrorCode", "BatchID", "ProjectID", "LineNum");
		
		// Define which fields are mandatory... meaning that they will throw an error upon trying to create a batch.
		$this->_columnNamesMandatory = array("Address1", "City", "State", "Zip", "Postnet", "CIN");
		
		// Define Translations for the ColumnNames to help us locate stuff in customer-specific artwork.  This will account for semantic variations and language differences.
		// You don't have to include the Column name... that is implicit.
		$this->_columnTrans = array();
		$this->_columnTrans["Address1"] = array("Address", "AddressOne", "Direcion", "StreetAddress");
		$this->_columnTrans["Address2"] = array("Suite", "AddressTwo", "Mailbox", "Address2", "AptNo", "Apt", "StreetAddress2");
		$this->_columnTrans["City"] = array("Cuidad");
		$this->_columnTrans["State"] = array("Province", "Estado");
		$this->_columnTrans["Zip"] = array("Postal", "PostalCode", "ZipCode");
		$this->_columnTrans["Postnet"] = array("Postnet111111", "11111postnet");
		$this->_columnTrans["Country"] = array("Pais");
		$this->_columnTrans["CIN"] = array("Tray");
		
		
		// this member variable _columnsAndTransArr will be a 2 array... the second dimension will be an array of all of the translations for the column name ... and the column name will be included in that array 
		$this->_columnsAndTransArr = array();
		foreach($this->_columnNames as $thisColName){
			if(array_key_exists($thisColName, $this->_columnTrans))
				$this->_columnsAndTransArr[$thisColName] = array_merge(array($thisColName),  $this->_columnTrans[$thisColName]);
			else
				$this->_columnsAndTransArr[$thisColName] = array($thisColName);
		}
		
		
		$this->_originalLineCount = 0;
		$this->_importedLineCount = 0;
	}
	
	// Looks at all "Production Piggyback IDs" in the Batch.
	// If there is even one Product ID that belongs to a domain which a user doesn't have permission to see, then it will return false.
	static function checkIfUserHasPermissionToSeeBatchID(DbCmd $dbCmd, $batchID){

		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		if(!$passiveAuthObj->CheckIfLoggedIn())
			return false;
			
			
		if(!$passiveAuthObj->CheckIfBelongsToGroup("MAIL_ADMIN") && !$passiveAuthObj->CheckIfBelongsToGroup("MAIL_ASSISTANT") && !$passiveAuthObj->CheckIfBelongsToGroup("MAIL_PRODUCTION"))
			return false;

			
		$dbCmd->Query("SELECT DISTINCT ProductID FROM mailingbatchlinks 
					INNER JOIN projectsordered ON projectsordered.ID = mailingbatchlinks.ProjectID 
					WHERE MailingBatchID=" . intval($batchID));
		if($dbCmd->GetNumRows() == 0)
			return false;
			
		$productIDarr = $dbCmd->GetValueArr();
		
		foreach($productIDarr as $thisProductID){
			$productionProductID = Product::getProductionProductIDStatic($dbCmd, $thisProductID);
			$domainIDofProductionProduct = Product::getDomainIDfromProductID($dbCmd, $productionProductID);
			
			if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofProductionProduct))
				return false;
		}
		
		return true;
	}
	
	
	// Will get a list of Open Batches and return an HTML table with all of the links inside, etc.
	static function getMailingBatchTableForOpenBatches(DbCmd $dbCmd, $userID, Authenticate $AuthObj){
	
		$batchIDarr = array();
		
		$dbCmd->Query("SELECT ID FROM mailingbatch WHERE BatchStatus = 'OPEN' ORDER BY ID DESC");
		while($thisBatchID = $dbCmd->GetValue())
			$batchIDarr[] = $thisBatchID;
			
		$filteredBatchList = array();
		
		foreach($batchIDarr as $thisBatchID){
			
			if(!self::checkIfUserHasPermissionToSeeBatchID($dbCmd, $thisBatchID))
				continue;
				
			$filteredBatchList[] = $thisBatchID;
		}
		
		return MailingBatch::getMailingBatchHTMLtable($dbCmd, $userID, $filteredBatchList, $AuthObj);
	}
	
	
	
	// Pass in an Array of Batch ID's and it will return an HTML table.
	static function getMailingBatchHTMLtable(DbCmd $dbCmd, $userID, $batchIDarr, &$AuthObj){
	
		$retHTML = "
			<table width=\"100%\" border=\"0\" cellspacing=\"0\" cellpadding=\"0\" bgcolor=\"#CC99CC\">
			  <tr>
			    <td>
			    <table width=\"100%\" border=\"0\" cellspacing=\"2\" cellpadding=\"4\">";


		$counter = 0;
		foreach($batchIDarr as $thisBatchID){
		
			$batchObj = new MailingBatch($dbCmd, $userID);
			$batchObj->loadBatchByID($thisBatchID);
			
			// On the first row... show a header.
			if($counter == 0)
				$retHTML .= $batchObj->getMailingBatchHTMLrow(true, $AuthObj);
			else
				$retHTML .= $batchObj->getMailingBatchHTMLrow(false, $AuthObj);
			
			
			$counter++;
		}


		$retHTML .= "
			</table>
			</td>
			  </tr>
			</table>
				<script>			
					function CompleteMailingBatch(mailBatchID){
						if(confirm(\"Please confirm that this mailing batch was comlpeted. \\nThis includes all paperwork, sorting, etc.\"))
							document.location = \"./ad_actions.php?form_sc=".WebUtil::getFormSecurityCode()."&action=CompleteMailingBatch&returl=ad_home.php&batchID=\" + mailBatchID;
					}
					function DownloadOriginalCSVfromBatch(mailBatchID){
						document.location = \"./ad_actions.php?form_sc=".WebUtil::getFormSecurityCode()."&action=DownloadOriginalCSVmailing&returl=ad_home.php&batchID=\" + mailBatchID;
					}

					function DownloadImportedCSVfromBatch(mailBatchID){

						document.location = \"./ad_actions.php?form_sc=".WebUtil::getFormSecurityCode()."&action=DownloadImportedCSVmailing&returl=ad_home.php&batchID=\" + mailBatchID;
					}
					function DownloadMergedCSVfromBatch(mailBatchID){
						document.location = \"./ad_mailinglist_downloadMerged.php?batchID=\" + mailBatchID;
					}
					
					
					function ImportMailingData(batchID){
						newWindow = window.open(\"ad_mailinglist_import.php?batchID=\" + batchID, \"MailingListImport\", \"height=290,width=540,directories=no,location=no,menubar=no,scrollbars=no,status=no,toolbar=no,resizable=no\");
						newWindow.focus();
					}
					function ShowMailingBatchPDF(batchID, profileName){
						var pdfURL = \"./pdf_launch.php?forward=pdf_done.php%3Fmailbtchid=\" + batchID + \"%26view=mailingbatch%26pdf_profile=\" + profileName;
						newWindow = window.open(pdfURL, \"MailingListPDF\", \"height=650,width=540,directories=no,location=no,menubar=no,scrollbars=yes,status=yes,toolbar=yes,resizable=yes\");
						newWindow.focus();
					}
					function CancelMailingBatch(mailBatchID){

						if(confirm(\"***********************************\\nAre you sure that you want to Cancel this batch?\\nAll Projects will have their statuses changed back to 'Proofed'.\\n***********************************\\n\\n\\n\\n\\n\\n\\n\\n\"))
							document.location = \"./ad_actions.php?form_sc=".WebUtil::getFormSecurityCode()."&action=CancelMailingBatch&returl=ad_home.php&batchID=\" + mailBatchID;
					}
				</script>
				<form name='batchWindowMailingsForm' method='post' action='./ad_batchwindow_new.php'>
				<input type='hidden' name='form_sc' value='".WebUtil::getFormSecurityCode()."'>
				<input type='hidden' name='projectlist' value=''>
				<input type='hidden' name='viewtype' value='admin'>
				</form>
		";
		
		return $retHTML;
	}
	
	
	
	// Returns TRUE if a the ProjectID belongs to a batch.
	static function checkIfProjectBatched(DbCmd $dbCmd, $projectID){

		if(!preg_match("/^\d+$/", $projectID))
			throw new Exception("Error in function checkIfProjectBatched, must be digit.");
			
		$dbCmd->Query("SELECT COUNT(*) FROM mailingbatchlinks INNER JOIN mailingbatch ON mailingbatchlinks.MailingBatchID = mailingbatch.ID 
				WHERE mailingbatch.BatchStatus != \"DELETED\" AND mailingbatchlinks.ProjectID=" . $projectID);
		
		if($dbCmd->GetValue() == 0)
			return false;
		else
			return true;
	}
	
	
	// Returns the Batch ID that the Project Belongs to...  if it does not belong to a batch then it throws an error.
	static function getBatchIDofProject(DbCmd $dbCmd, $projectID){
		
		if(!preg_match("/^\d+$/", $projectID))
			throw new Exception("Error in function getBatchIDofProject, must be digit.");
			
		$dbCmd->Query("SELECT mailingbatchlinks.MailingBatchID FROM mailingbatchlinks INNER JOIN mailingbatch ON mailingbatchlinks.MailingBatchID = mailingbatch.ID 
				WHERE mailingbatch.BatchStatus != \"DELETED\" AND mailingbatchlinks.ProjectID=" . $projectID);
		
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Error in function getBatchIDofProject.  The Project does not have a Mailing Batch ID.");
		
		return $dbCmd->GetValue();
	}
	
	
	
	// Pass in one of our Column names... and a ArtworkVarsObj (already extracted from a valid Project ID).  
	// This method will return the field position within VariableData mapping for the project.
	// This can be useful for Language differences, and variations.
	// The positions are 1-based.  If this method return 0 (Zero) then it means that a compatible mapping was not found. 
	function getFieldMappingPositionOfVariable($artworkVarsObj, $columnName){
	
		if(!in_array($columnName, $this->_columnNames))
			throw new Exception("Error in method getFieldMappingPositionOfVariable... the Column name does not match one of our internal deffinitions.");
			
		if(!in_array($columnName, array_keys($this->_columnTrans)))
			throw new Exception("Error in method getFieldMappingPositionOfVariable... the Column name does not have a Translation defined.  Maybe it is not meant to be found in a customer's artwork?. <b>". $columnName . "</b>");
	
		
		$variablesConfiguredArr = $artworkVarsObj->getVariableNamesArrFromArtwork();
		
		// Loop through every possible variation that might exist for defining a column name.  Return the position number upon the first match.
		foreach($this->_columnsAndTransArr[$columnName] as $thisColName){

			foreach($variablesConfiguredArr as $thisVarConfigured){

				if(strtoupper(trim($thisVarConfigured)) == strtoupper($thisColName))
					return $artworkVarsObj->getFieldPosition($thisVarConfigured);
			}
		}
		
		return 0;
	}
	
	
	// Returns a Variable Data sorting object.
	// When a Mail List is imported... it will not in sorted sequence (just with a "sorted column"). 
	function getProjectSortingObj(){
		
		if(empty($this->_batchID))
			throw new Exception("Error in method MailingBatch->getProjectSortingObj ... the BatchID has not loaded yet.");	
			
		if($this->_importedLineCount == 0)
			throw new Exception("Error in method MailingBatch->getProjectSortingObj ... The line count is 0.");
		
		$projectIDsArr = $this->getUniqueListOfProjectsWithinBatch();
		
		// Index is the Line Number (Zero-based)...the value is the sorting column belonging to the line.
		$sortingPointerArr = array();
		
		$dataArr =& $this->getImportedDataArray();
		
		for($i=0; $i<sizeof($dataArr); $i++){
		
			if(!isset($dataArr[$i]["Sort"]) || !isset($dataArr[$i]["ProjectID"]))
				throw new Exception("Error in method MailingBatch->getProjectSortingObj.  One of the required fields... 'Sort', 'ProjectID', or 'LineNum' column was not found on master line number: " . $i);
			
			$sortingPointerArr[strval($i)] = $dataArr[$i]["Sort"];
		}
		
		// Sort based upon the sorting value in the array... but without changing the Indexes
		asort($sortingPointerArr);
		
	
		$variableDataSortObj = new VariableDataSorting();
		
		foreach(array_keys($sortingPointerArr) as $thisIndex ){
		
			$projectID = $dataArr[intval($thisIndex)]["ProjectID"];
			$lineNumProject = $dataArr[intval($thisIndex)]["LineNum"];
			
			if(!in_array($projectID, $projectIDsArr))
				throw new Exception("Error getting a Project Sorting Object.  The Project ID is no longer found.  Did the Column Selections on this object code change after the Data file was imported?");
			
			$variableDataSortObj->addRecord($projectID, $lineNumProject);
		}
		
		return $variableDataSortObj;
	}
	
	
	
	// Returns TRUE if the batch was able to be created... FALSE otherwise.
	// If False, check the ErrorMessage to see what the reason is.
	// If True, get the BatchID after this method call.
	function createNewBatchInDB($projectIDarr){
		
		$this->_errorMessage = "An unknown error occured trying to make a new batch.";
		
		$this->_batchID = 0;
		
		$projectIDarr = array_unique($projectIDarr);
		
		// Have the highest project numbers on top... so it will match the open order list.
		rsort($projectIDarr);
		
		// Record what "Options in Common for Production" are found on the first ProjectID... the remaining much much.
		$optionsSelectedOnFirstProjectArr = array();
		
		// Combine all projects into one single CSV mailer... but we are going to standardise on mailing fields being in a consistent order.
		$newBatchCSV = $this->getCSVheaderLine();
		
		
		// Make a new BatchID... on an error it will go to waste... on success we can update it.  We do this because it is an Auto-Increment ID.
		$newBatchID = $this->_dbCmd->InsertQuery("mailingbatch", array("BatchStatus"=>"OPEN", "CreatedBy"=>$this->_userID));
		
		$totalLineCount = 0;
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		$productIDsForProductionArr = array();
		
		foreach($projectIDarr as $thisProjectID){
		
			$projectObj = ProjectOrdered::getObjectByProjectID($this->_dbCmd, $thisProjectID);
			
			$artworkVarsObj = new ArtworkVarsMapping($projectObj->getArtworkFile(), $projectObj->getVariableDataArtworkConfig());
		
			$thisProductID = $projectObj->getProductID();
			
			
			$productObj = Product::getProductObj($this->_dbCmd, $thisProductID, false);
			
			if(!$productObj->hasMailingService()){
				$this->_errorMessage = "Can not create new batch because P" . $thisProjectID . " does not have mailing services available.";
				$this->_dbCmd->Query("DELETE FROM mailingbatch WHERE ID=$newBatchID");
				return false;
			}
			
			
			
			$productionProductID = Product::getProductionProductIDStatic($this->_dbCmd, $thisProductID);
			$domainIDofProductionPiggyBack = Product::getDomainIDfromProductID($this->_dbCmd, $productionProductID);
			
			if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofProductionPiggyBack)){
				$this->_errorMessage = "Can not create new batch because P" . $thisProjectID . " is outside of your domain.";
				$this->_dbCmd->Query("DELETE FROM mailingbatch WHERE ID=$newBatchID");
				return false;
			}
			
			
			if($projectObj->getStatusChar() != "P"){
				$this->_errorMessage = "Can not create new batch because P" . $thisProjectID . " does not have a status of 'Proofed'.";
				$this->_dbCmd->Query("DELETE FROM mailingbatch WHERE ID=$newBatchID");
				return false;
			}
			
			
			$optionsInCommon = $productObj->getOptionsInCommonForProduction();
			$selectedProjectOptions = $projectObj->getOptionsAliasesAndSelections();
			
			
			// This first Project in the list is the one that the remainder must match up to.
			if(empty($productIDsForProductionArr)){
				
				$productIDsForProductionArr = Product::getAllProductIDsSharedForProduction($this->_dbCmd, $thisProductID);
			
			
				foreach($optionsInCommon as $thisOptionName){
				
					if(!array_key_exists($thisOptionName, $selectedProjectOptions)){
						$this->_errorMessage = "Can not create new batch because P" . $thisProjectID . " is missing a required Product Option: " . $thisOptionName;
						$this->_dbCmd->Query("DELETE FROM mailingbatch WHERE ID=$newBatchID");
						return false;
					}
					else{
						$optionsSelectedOnFirstProjectArr[$thisOptionName] = $selectedProjectOptions[$thisOptionName];
					}
				}
				
			}
				
			
			if(!in_array($thisProductID, $productIDsForProductionArr)){	
				$this->_errorMessage = "Can not create new batch because P" . $thisProjectID . " has a Product ID that is not compatible with other orders in this batch.";
				$this->_dbCmd->Query("DELETE FROM mailingbatch WHERE ID=$newBatchID");
				return false;
			}
			
			
			// Make sure that the Product Options are compatible within the batch.  For example a "Glossy" postcard shouldn't be run in the same batch with "non-glossy".
			foreach($optionsSelectedOnFirstProjectArr as $thisOptionName => $optionChoiceOnFirstProject){
			
				if(!array_key_exists($thisOptionName, $selectedProjectOptions)){
					$this->_errorMessage = "Can not create new batch because P" . $thisProjectID . " is missing a required Product Option: " . $thisOptionName;
					$this->_dbCmd->Query("DELETE FROM mailingbatch WHERE ID=$newBatchID");
					return false;
				}
				
				if($selectedProjectOptions[$thisOptionName] != $optionChoiceOnFirstProject){
					$this->_errorMessage = "Can not create new batch because P" . $thisProjectID . " has a selected option for \"" . $thisOptionName . "\" > \"" . $selectedProjectOptions[$thisOptionName] . "\" which does not match other projects in this batch.";
					$this->_dbCmd->Query("DELETE FROM mailingbatch WHERE ID=$newBatchID");
					return false;
				}	
			}
			
	
			
			// Now loop through the array that tells us how the CSV should be organized.
			// Translate our column definitions into columns positions within the Project's Variable data mappings.
			$colTranslations = array();
			
			foreach($this->_columnNames as $thisMailingCol){
			
				// Once we hit the "Sort" column... we don't expect that column (or anything beyond) to be an Artwork Variable.
				if($thisMailingCol == "Sort")
					break;

				$colNumInProject = $this->getFieldMappingPositionOfVariable($artworkVarsObj, $thisMailingCol);

				
				if($colNumInProject == 0 && in_array($thisMailingCol, $this->_columnNamesMandatory)){
					$this->_errorMessage = "Can not create new batch because P" . $thisProjectID . " seems to be missing a required mailing address field: " . $thisMailingCol;
					$this->_dbCmd->Query("DELETE FROM mailingbatch WHERE ID=$newBatchID");
					return false;
				}
				
				// Even through it is not mandatory... if it is not in the customer's file, then we don't need to try and get the field later on.
				if($colNumInProject != 0)
					$colTranslations[$thisMailingCol] = $colNumInProject;
			}
			
			
			
			$variableDataObj = new VariableData();
			$variableDataObj->loadDataByTextFile($projectObj->getVariableDataFile());



			$lineItems = $variableDataObj->getNumberOfLineItems();
		
			for($i=0; $i<$lineItems; $i++){
			
				$lineItemArr = array();
			
				foreach($this->_columnNames as $thisMailingCol){
				
					if(array_key_exists($thisMailingCol, $colTranslations)){
						$thisField = $variableDataObj->getVariableElementByLineAndColumnNo($i, ($colTranslations[$thisMailingCol] - 1)); // Column names are Zero based like the Row number in this method call.
					}
					else{	
						if($thisMailingCol == "BatchID")
							$thisField = $newBatchID;
						else if($thisMailingCol == "ProjectID")
							$thisField = "P" . $thisProjectID;
						else if($thisMailingCol == "LineNum")
							$thisField = strval($i + 1);
						else	
							$thisField = "";
					}
					
					
					// These values may have already been imported from a previous try at importing a mail file.  We don't want to put them into the original CSV file if we try creating a new batch.
					if(in_array($thisMailingCol, array("Postnet", "CIN")))
						$thisField = "";
					
					// Make sure that 2 letter State codes are uppercase.
					if($thisMailingCol == "State" && strlen($thisField) == 2)
						$thisField = strtoupper($thisField);
					
					// Don't import 4 digit zip codes into the mailing program.  If there are only 5 digits they will figure out the 4.  If you give them the wrong Zip+4 the software will complain
					if($thisMailingCol == "Zip" && strlen($thisField) > 5 && preg_match("/^\d+/", $thisField))
						$thisField = substr($thisField, 0, 5);
					
						
					$lineItemArr[] = $thisField;
				}
				
				$newBatchCSV .= $this->_getCSVline($lineItemArr) . "\n";
				$totalLineCount++;
			}
		}
		
		
		// The post office has a minimum quantity before an Indicia imprint can be used.
		if($totalLineCount < $this->_minimumCombinedQuantity){
			$this->_errorMessage = "Can not create new batch. There must be a combined quantity of at least " . $this->_minimumCombinedQuantity . ".  You attempted to create a batch with a combined quantity of " . $totalLineCount . ".";
			$this->_dbCmd->Query("DELETE FROM mailingbatch WHERE ID=$newBatchID");
			return false;
		}
		
		
		$this->_projectIDsInBatch = $projectIDarr;
		
		// Update the CSV file on our Batch Row in the DB.
		$this->_dbCmd->UpdateQuery("mailingbatch", array("AddressDataOriginal"=>$newBatchCSV, "OriginalLineCount"=>$totalLineCount, "CreatedOn"=>time()), "ID=$newBatchID");
		

		// Now update the linking table which tells us which projects are linked to this batch ID.
		// Also record the Project history.
		foreach($projectIDarr as $thisProjectID){
			
			$this->_dbCmd->InsertQuery("mailingbatchlinks", array("ProjectID"=>$thisProjectID, "MailingBatchID"=>$newBatchID));
			
			ProjectOrdered::ChangeProjectStatus($this->_dbCmd, "G", $thisProjectID);
			ProjectHistory::RecordProjectHistory($this->_dbCmd, $thisProjectID, "G", $this->_userID);	
		}
		
		return true;
	}
	
	
	
	function loadBatchByID($btchID){
	
		$this->_batchID = 0;
	
		if(!preg_match("/^\d+$/", $btchID))
			throw new Exception("Error in function loadBatchByID, must be digit.");	

		if(!self::checkIfUserHasPermissionToSeeBatchID($this->_dbCmd, $btchID))
			throw new Exception("User doesn't have permission to see the Batch");
		
		// Just in case we are loading a new Batch ID from within the same object... this will invalidate any cached copies of the data.
		$this->_importedDataLoadedFlag = false;
		$this->_originalDataLoadedFlag = false;
		
		$this->_originalDataFile = "";
		$this->_importedDataFile = "";
		
		$this->_projectIDsInBatch = array();
		
		
		$this->_dbCmd->Query("SELECT OriginalLineCount, ImportedLineCount, UNIX_TIMESTAMP(CreatedOn) AS CreatedOn FROM mailingbatch WHERE ID=" . $btchID);
		
		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Error in function loadBatchByID, The BatchID does not exist: " . $btchID);
		
		$row = $this->_dbCmd->GetRow();
		
		$this->_originalLineCount = $row["OriginalLineCount"];
		$this->_importedLineCount = $row["ImportedLineCount"];
		$this->_createdOn = $row["CreatedOn"];
		
		
		// Keep a reference to the BatchID which is loaded.
		$this->_batchID = $btchID;
		
	
	}
	
	// We don't actually delete the batch... just change its status to DELETED
	// this will also change all of the statuses back to "proofed" at the project level
	function cancelBatch(){
		
		if(empty($this->_batchID))
			throw new Exception("Error in method MailingBatch->cancelBatch ... the BatchID has not loaded yet.");
			
			
		$projectIDarr = $this->getUniqueListOfProjectsWithinBatch();
		
		foreach($projectIDarr as $thisProjID){
			ProjectOrdered::ChangeProjectStatus($this->_dbCmd, "P", $thisProjID);
			ProjectHistory::RecordProjectHistory($this->_dbCmd, $thisProjID, "Mailing Batch " . $this->_batchID . " was canceled", $this->_userID);
		}
		
			
		$this->_dbCmd->UpdateQuery("mailingbatch", array("BatchStatus"=>"DELETED"), ("ID=" . $this->_batchID));
		
		$this->_batchID = 0;
	}
	
	

	
	// Will Change the Batch to a completed Status... and it will Also update the Variable Data list on the Project and change the quantity.
	// Order completion should be called after running this method... to ensure that if the mailing decreases, the customer does not pay for it.
	// Pass in Output Progress to have messages sent back to the client... which will keep the browser from timing out, etc.
	function batchComplete($btchID, $outputProgress = "none"){
		
		if(empty($this->_batchID))
			throw new Exception("Error in method MailingBatch->batchComplete ... the BatchID has not loaded yet.");
			
		if(!in_array($outputProgress, array("none", "html")))
			throw new Exception("Illegal Output Progress in method batchComplete");

		$batchStatus = $this->_getBatchStatus();
		
		if($batchStatus != "OPEN")
			throw new Exception("Error in method MailingBatch->batchComplete ... the Batch Status must be set to OPEN in order to call this method.  Maybe the batch was already completed.  Hit BACK in your browser and try refreshing the page to see if the batch was already completed.");
	
		if($this->_importedLineCount == 0)
			throw new Exception("Error in method MailingBatch->batchComplete ... The mailing list has not been imported yet.");

		
		$importData =& $this->getImportedDataArray();
		
	
		$projectIDarr = $this->getUniqueListOfProjectsWithinBatch();
		
		$countOfNonAdjusted = 0;
		$profitOfNonAdjusted = 0;
		
		
		// Keep Track of the postage.  The key is the Project ID.
		$projectPostageArr = array();
		
		foreach($projectIDarr as $thisProjectID)
			$projectPostageArr[$thisProjectID] = 0;
		
		// Every 100 Lines of Data will have some output sent to the browser.
		$outputCounterLimit = 100;
		$outputCounter = 0;
		
		
		
		// Construct the start of the message that will get sent out when the batch completes.
		$emailMessage = "Batch # " . $btchID . " was adjusted.  Profit will not show in the backend reports.\n--------------------------------------\n";
		
		$productIDofProduction = $this->getProductionProductIDofBatch();
		$productObj = Product::getProductObj($this->_dbCmd, $productIDofProduction, false);
		
		// Build a String of Options and Selections that are in Common
		$optionsStr = $productObj->getProductTitleWithExtention() . "\n";
		$optionsInCommon = $this->getOptionsInCommonForBatch();
		
		foreach($optionsInCommon as $thisOptionName => $thisOptionValue)
			$optionsStr .= $thisOptionName . " > " . WebUtil::htmlOutput($thisOptionValue) . "\n";
			
		$emailMessage .= $optionsStr . "--------------------------------------\n\n";


		
			

		foreach($projectIDarr as $thisProjectID){
		
			$projectObj = ProjectOrdered::getObjectByProjectID($this->_dbCmd, $thisProjectID);

			$variableDataObj = new VariableData();
			$variableDataObj->loadDataByTextFile($projectObj->getVariableDataFile());
			
			$projectLineItems = $variableDataObj->getNumberOfLineItems();
			
			
			if($outputProgress == "html"){
				print "<br><br><br>\nWorking On P" . $thisProjectID . "<hr>\n
				                                                                                                                                                                                                                                                                        
				                                                                                                                                                                                                                                                                            
				                                                                                                                                                                                                                                                                            
				";
				Constants::FlushBufferOutput();	
			}
			
			// Hold line numbers (Zero based) that will tell us what line numbers should be removed from the Project Line.
			$linesToDeleteInProjectArr = array();
			
			// After we filter our file... this will contain the resulting CSV data.
			$newCSVfileForProject = "";
			
			
			// Now go through each line in the variable data file and see if we can find a corresponding match
			// Remember that not every Line from the Projects's Variable data object must be present within the "imported data"... the reason is that some of the Bad Addresses may have been deleted...
			// We don't have to delete "Bad Address Lines" from the project Object at this point.... that will be done at completion time.
			for($projectRowNum=0; $projectRowNum < $projectLineItems; $projectRowNum++){
				
				$lineFoundInImportData = false;
			
				for($importedRowNum=0; $importedRowNum < $this->_importedLineCount; $importedRowNum++){			
					if($importData[$importedRowNum]["ProjectID"] == $thisProjectID && $importData[$importedRowNum]["LineNum"] == ($projectRowNum + 1)){
					
						$lineFoundInImportData = true;
					
						// If the Postage Rate looks valid... then update its tally for the given project
						if(preg_match("/^\d+\.\d+$/", $importData[$importedRowNum]["Postage"]))
							$projectPostageArr[$thisProjectID] += $importData[$importedRowNum]["Postage"];
						
						
						// Possibly send data to the browser.
						$outputCounter++;
						if($outputCounter % $outputCounterLimit == 0){
						
							$outputCounter = 0;
							
							if($outputProgress == "html"){
								print " ... " . round(100 * $projectRowNum / $projectLineItems) . "% ...\n";
								Constants::FlushBufferOutput();	
							}
						}
					}
				}
				
				if(!$lineFoundInImportData)
					$linesToDeleteInProjectArr[] = $projectRowNum;
			}
			
			
			print " ... 100% ...\n";
			Constants::FlushBufferOutput();	
			
		
			// Now go through our lines from the Project File and leave out "deleted" line numbers as we add to our CSV file.
			for($projectRowNum=0; $projectRowNum < $projectLineItems; $projectRowNum++){
				if(!in_array($projectRowNum, $linesToDeleteInProjectArr))
					$newCSVfileForProject .= $this->_getCSVline($variableDataObj->getVariableRowByLineNo($projectRowNum)) . "\n";
					
				// Possibly send data to the browser.
				$outputCounter++;
				if($outputCounter % $outputCounterLimit == 0){

					$outputCounter = 0;

					if($outputProgress == "html"){
						print " o ";
						Constants::FlushBufferOutput();	
					}
				}
			}
			
			// Write our CSV data to disk.
			$tmpCSVfile = FileUtil::newtempnam(Constants::GetTempDirectory(), "CSV", ".csv", time());
			$fp = fopen($tmpCSVfile, "w");
			fwrite($fp, $newCSVfileForProject);
			fclose($fp);
			
			
			// Put the CSV file into a Variable Data Object.
			// Destruct the old one to save on memory.
			unset($variableDataObj);
			
			$variableDataObj = new VariableData();
			$variableDataObj->loadDataByCSVfile($tmpCSVfile);

			@unlink($tmpCSVfile);
			
			
			$newProjectQuantity = $variableDataObj->getNumberOfLineItems();

			$oldProjectQuantity = $projectObj->getQuantity();
			
			$totalOfVendorCharges = $projectObj->getVendorSubtotalsCombined();
			
			$averageUnitCost = $totalOfVendorCharges / $oldProjectQuantity;
			
			
			
			$adjustedQuantity = $this->getAdjustedProjectQuantity($oldProjectQuantity, $newProjectQuantity);
			
			// the adjusted quantity is always greater or equal to the actual quantity that is CASS certified.
			$nonAdjustedQuantity = $adjustedQuantity - $newProjectQuantity;
			
			$countOfNonAdjusted += $nonAdjustedQuantity;
			
			$profitOfNonAdjusted += round($averageUnitCost * $nonAdjustedQuantity, 1);
			
			
			
			// We don't want to update the variable data file or it could give away the true "Adjusted Quantity".
			//$projectObj->setVariableDataFile($variableDataObj->getVariableDataText());
			
			$projectObj->setQuantity($adjustedQuantity);
			$projectObj->updateDatabase();
			
			
			$emailMessage .= "P" . $thisProjectID . " - Original Quantity: $oldProjectQuantity - Actual: $newProjectQuantity --- Adjusted: $adjustedQuantity -- Extra Profit: \$" . round($averageUnitCost * $nonAdjustedQuantity) . "\n";
			
			
			// Find out if postage rate seems to be valid by taking an average.
			// Record errors into the Project History if we can't find a "Postage Vendor" for this project or if the new postage cost doesn't seem valid.
			$averagePostageRate = $projectPostageArr[$thisProjectID] / $newProjectQuantity;
			if($averagePostageRate > $this->_validSinglePostageRateLow && $averagePostageRate < $this->_validSinglePostageRateHigh){
			
				$getVendorNumberForPostage = $projectObj->getVendorNumberByName("Postage");
				
				if(empty($getVendorNumberForPostage)){
					ProjectHistory::RecordProjectHistory($this->_dbCmd, $thisProjectID, "Could not update the Postage Total on this project because a valid Postage Vendor was not found.", $this->_userID);
				}
				else{
					// Get a fresh project object from the database.
					$newProjectObj = ProjectOrdered::getObjectByProjectID($this->_dbCmd, $thisProjectID);
				
					// Save the new Vendor total to the database for the "Postage Vendor"
					$newProjectObj->setVendorSubtotalByNumber($getVendorNumberForPostage, $projectPostageArr[$thisProjectID]);
			
					// Use Update with Raw data so that it won't calculate the Vendor Total automatically.
					$newProjectObj->updateDatabaseWithRawData();
				}
			}
			else{
				ProjectHistory::RecordProjectHistory($this->_dbCmd, $thisProjectID, "Could not update the Postage Total on this project because postage rates were not properly imported.", $this->_userID);
			}
			
		}
		
		
		$mesageSubject = "Postage/Handling Adjustments: \$" . round($profitOfNonAdjusted);

		
		$emailContactsForReportsArr = Constants::getEmailContactsForServerReports();
		foreach($emailContactsForReportsArr as $thisEmailContact){
			WebUtil::SendEmail("Batch Postage", Constants::GetMasterServerEmailAddress(), "", $thisEmailContact, $mesageSubject, $emailMessage);
		}

		
		$this->_dbCmd->UpdateQuery("mailingbatch", array("BatchStatus"=>"COMPLETE"), ("ID=" . $btchID));
	}
	
	
	function getAdjustedProjectQuantity($originalQuantity, $newQuantity){

		$quantityLoss = $originalQuantity - $newQuantity;

		if($quantityLoss < 0)
			$quantityLoss = 0;

		$adjustedQuantityLoss = round($quantityLoss / 4);
		
		return $originalQuantity - $adjustedQuantityLoss;
	}
	
	
	// Returns the Status Character of one of the projects in the Batch.  It doesn't matter which one because all projects will be the same.
	function GetProjectStatusOfBatch(){
	
		if(empty($this->_batchID))
			throw new Exception("Error in method MailingBatch->getStatusCharOfBatch ... the BatchID has not been created or loaded yet.");
		
		$this->_dbCmd->Query("SELECT ProjectID FROM mailingbatchlinks WHERE MailingBatchID=" . $this->_batchID . " LIMIT 1");

		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method MailingBatch->getStatusCharOfBatch... the Batch ID does not have any projects inside of it. BatchID: " . $this->_batchID);

		$projectID = $this->_dbCmd->GetValue();
 	
		$projectObj = ProjectOrdered::getObjectByProjectID($this->_dbCmd, $projectID, false);
	
		return $projectObj->getStatusChar();
		
	}
	
	
	// You can match different Product ID's together... as long as they share the same Production Product ID.  This will return what the ProductID of the batch is.
	function getProductionProductIDofBatch(){
	
		if(empty($this->_batchID))
			throw new Exception("Error in method MailingBatch->getStatusCharOfBatch ... the BatchID has not been created or loaded yet.");
		
		$this->_dbCmd->Query("SELECT ProjectID FROM mailingbatchlinks WHERE MailingBatchID=" . $this->_batchID . " LIMIT 1");

		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method MailingBatch->getProductionProductIDofBatch... the Batch ID does not have any projects inside of it. BatchID: " . $this->_batchID);

		$projectID = $this->_dbCmd->GetValue();
 	

		
		$firstProductID = ProjectBase::getProductIDquick($this->_dbCmd, "ordered", $projectID);
	
		$firstProductObj = Product::getProductObj($this->_dbCmd, $firstProductID, false);
	
		return $firstProductObj->getProductionProductID();
	}
	
	
	
	
	// Returns a UnixTimestamp of the last Status Change for the Batch.  It basically just tracks one of the Projects arbitrarily.  It doesn't matter which one because all projects will be the same.
	function getTimestampOfLastStatusChange(){
	
		if(empty($this->_batchID))
			throw new Exception("Error in method MailingBatch->getStatusCharOfBatch ... the BatchID has not been created or loaded yet.");
		
		$this->_dbCmd->Query("SELECT ProjectID FROM mailingbatchlinks WHERE MailingBatchID=" . $this->_batchID . " LIMIT 1");
		$projectID = $this->_dbCmd->GetValue();
		
		return ProjectHistory::GetLastStatusDate($this->_dbCmd, $projectID);		
	}
	
	// Returns a Hash with Options and Selections that are in common for the batch... like .... array("Postage Type"=>"First Class", "Card Stock"=>"Glossy")
	function getOptionsInCommonForBatch(){
	
		if(empty($this->_batchID))
			throw new Exception("Error in method MailingBatch->getOptionsInCommonForBatch ... the BatchID has not been created or loaded yet.");
		
		
 		// Get the first Project Number in the Batch.
 		$this->_dbCmd->Query("SELECT ProjectID FROM mailingbatchlinks WHERE MailingBatchID=" . $this->_batchID . " LIMIT 1");
 		$projectID = $this->_dbCmd->GetValue();

 		// Get the Selected Options from the Project and compare it to the PRODUCT object to see what was is selected.
 		$projectObj = ProjectOrdered::getObjectByProjectID($this->_dbCmd, $projectID);
 		$productIDofBatch = $projectObj->getProductID();
 		$optionsSelectedHash = $projectObj->getOptionsAliasesAndSelections();

 		$productObject = Product::getProductObj($this->_dbCmd, $productIDofBatch, false);
		$optionsInCommonArr = $productObject->getOptionsInCommonForProduction();
 
		$retHash = array();
		
		foreach($optionsSelectedHash as $thisOptionName => $thisOptionValue){
			if(in_array($thisOptionName, $optionsInCommonArr))
				$retHash[$thisOptionName] = $thisOptionValue;
		}
		
		return $retHash;		
	}
	
	function getUniqueListOfProjectsWithinBatch(){
		
		if(empty($this->_batchID))
			throw new Exception("Error in method MailingBatch->getUniqueListOfProjectsWithinBatch ... the BatchID has not been created or loaded yet.");
		
		// Only load stuff from the DB if someone is requesting it.
		if(empty($this->_projectIDsInBatch)){
		
			$this->_dbCmd->Query("SELECT DISTINCT ProjectID FROM mailingbatchlinks WHERE MailingBatchID=" . $this->_batchID . " ORDER BY ProjectID ASC");
			while($thisProjID = $this->_dbCmd->GetValue())
				$this->_projectIDsInBatch[] = $thisProjID;
		}
		
		return $this->_projectIDsInBatch;
	}
	
	function getBatchID(){
	
		if(empty($this->_batchID))
			throw new Exception("Error in method MailingBatch->getBatchID ... the BatchID has not been created or loaded yet.");
		
		return $this->_batchID;
	}
	
	
	function dateCreated(){
	
		if(empty($this->_batchID))
			throw new Exception("Error in method MailingBatch->createdOn ... the BatchID has not been created or loaded yet.");
	
		return $this->_createdOn;
	}
	
	function getErrorMessage(){
	
		return $this->_errorMessage;
	}
	
	
	function getOriginalLineCount(){
	
		if(empty($this->_batchID))
			throw new Exception("Error in method MailingBatch->getOriginalLineCount ... you can not call this method unless a Batch ID has been loaded.");
		
		return $this->_originalLineCount;
	}
	
	function getImportedLineCount(){
	
		if(empty($this->_batchID))
			throw new Exception("Error in method MailingBatch->getImportedLineCount ... you can not call this method unless a Batch ID has been loaded.");
		
		return $this->_importedLineCount;
	}
	


	function getFileNameOfPDFforBatch(){
	
		if(empty($this->_batchID))
			throw new Exception("Error in method MailingBatch->getOriginalDataFile ... you can not call this method unless a Batch ID has been loaded.");
	
		
		$optionInCommonArr = $this->getOptionsInCommonForBatch();

		if(isset($optionInCommonArr["Card Stock"]))
			$cardstockDesc = strtoupper(preg_replace("/\s/", "", $optionInCommonArr["Card Stock"]));
		else
			$cardstockDesc = "CARDSTOCK_UNKOWN";

	
		$productID = $this->getProductionProductIDofBatch();
		$productName = Product::getFullProductName($this->_dbCmd, $productID);
		
		
		
		// Try and extract the dimensions out of the Product Title
		$productTitleDesc = "";
		$match = array();
		if(preg_match("/\(\d+(x|\.|\s|\d)+\)/", $productName, $match))
			$productTitleDesc = preg_replace("/(\s|\.|\(|\))/", "", $match[0]);
		
		if(empty($productTitleDesc))
			$productTitleDesc = "DIMESNIONS_UNKNOWN";


		return "MailBatch_" . $this->_batchID . "--" . $productTitleDesc . "_" . $cardstockDesc . "_DOUBLE.pdf";
	}
	
	
	// Returns a CSV of the imported data
	// Exits if the no data has been imported... so make sure to check the line count first.
	function getOriginalDataFile(){
		
		if(empty($this->_batchID))
			throw new Exception("Error in method MailingBatch->getOriginalDataFile ... you can not call this method unless a Batch ID has been loaded.");
	
		// If we haven't fetched the Data from the DB, then do it now.
		if(!$this->_originalDataLoadedFlag){
			$this->_dbCmd->Query("SELECT AddressDataOriginal FROM mailingbatch WHERE ID=" . $this->_batchID);
			$this->_originalDataFile = $this->_dbCmd->GetValue();
			$this->_originalDataLoadedFlag = true;
		}
	
		return $this->_originalDataFile;
	}
	
	
	// Returns a CSV of the imported data
	// Exits if the no data has been imported... so make sure to check the line count first.
	function getImportedDataFile(){
		
		if(empty($this->_batchID))
			throw new Exception("Error in method MailingBatch->getImportedDataFile ... you can not call this method unless a Batch ID has been loaded.");
	
		if($this->_importedLineCount == 0)
			throw new Exception("Error in method MailingBatch->getImportedDataFile ... The line count is 0... check the Line Count before calling this method.");
	
		// If we haven't fetched the Data from the DB, then do it now.
		if(!$this->_importedDataLoadedFlag){
			$this->_dbCmd->Query("SELECT AddressDataImported FROM mailingbatch WHERE ID=" . $this->_batchID);
			$this->_importedDataFile = $this->_dbCmd->GetValue();
			$this->_importedDataLoadedFlag = true;
		}	
		return $this->_importedDataFile;
	}
	
	
	// Returns a 2-D array with the imported data.

	// The index is the Line Number (zero based). 
	function &getImportedDataArray(){

		$importedCSVfile = $this->getImportedDataFile();
		
		// Create a temporary file on disk 
		$tmpCSVfile = FileUtil::newtempnam(Constants::GetTempDirectory(), "CSV", ".csv", time());

		// Put CSV text into a temp file 
		$fp = fopen($tmpCSVfile, "w");
		fwrite($fp, $importedCSVfile);
		fclose($fp);
		
		$csv = new ParseCSV($tmpCSVfile);
		$csv->SkipEmptyRows(true);
		$csv->TrimFields(true);
		
		$lineCount = 0;
		$retArr = array();
		
		while ($elementsArr = $csv->NextLine()){
		
			$retArr[$lineCount] = array();
		
			for($i=0; $i<sizeof($elementsArr); $i++){
			
				$colName = $this->getFieldNameByColumnNumber($i+1);
				
				// If the column is for the ProjectID. Strip of the Prefix or the Project Number. Ex: P34367
				if($colName == "ProjectID")
					$dataValue = preg_replace("/^P/i", "", trim($elementsArr[$i]));
				else
					$dataValue = $elementsArr[$i];
				
				$retArr[$lineCount][$colName] = $dataValue;
			}
			
			$lineCount++;
		}
		
		@unlink($tmpCSVfile);
		
		return $retArr;
	}
	
	


	
	// return TRUE if the data was able to be imported, FALSE otherwise.
	// If false, check the Error message to find out the cause.
	// Will Also save to the DB.
	// $flushProgressOutput should be a string "none", "space", "javascript"
	function importData($csvTempFile, $flushProgressOutput){

		if(empty($this->_batchID))
			throw new Exception("Error in method MailingBatch->importData ... you can not call this method unless a Batch ID has been loaded.");

	
		$this->_errorMessage = "An unknown error occured trying to import mailing data.";
		

		// Every 200 lines of data... flush buffer output.
		$progressOutputCounter = 200;
		
	
		// Send a message to the browser
		if($flushProgressOutput == "javascript"){
			print "\n<script>";
			print "ShowCurrentTask('<b>Status:</b> CSV file is being parsed.');";
			print "</script>\n";
			Constants::FlushBufferOutput();
		}
	
	
		// A 2D array... every row will contain a hash with the data for each line.
		$importDataParsedArr = array();
		
		// We want to keep track of the project Count coming back from the Imported File (since some addresses could have been deleted).
		// The key to the array is the projectID.
		$projectCountsArr = array();
		
		// Keep Track of the postage.  The key is the Project ID.
		$projectPostageArr = array();
		
		$projectIDlistInBatch = $this->getUniqueListOfProjectsWithinBatch();
		
		foreach($projectIDlistInBatch as $thisProjID){
			$projectCountsArr[$thisProjID] = 0;
			$projectPostageArr[$thisProjID] = 0;
		}
		
		

		if(!file_exists($csvTempFile)){
			$this->_errorMessage = "Could not open the CSV file.  Are you sure that you attached it?";
			return false;
		}


		$csv = new ParseCSV($csvTempFile);
		$csv->SkipEmptyRows(true);
		$csv->TrimFields(true);

		$parsedLineCount = 0;
		$actualLineCount = 1;
		


		while ($elementsArr = $csv->NextLine()){
			
			if(sizeof($elementsArr) != sizeof($this->_columnNames)){
				$this->_errorMessage = "An error occured importing the CSV file.  The number of columns on Row# " . $actualLineCount . " did not match the requirement.  There are supposed to be " . sizeof($this->_columnNames) . " columns on each row.";
				@unlink($csvTempFile);
				return false;
			}
			
			$batchIDcolumn = $this->getPositionByColumn("BatchID") - 1;
			
			// If we are on row 1... it could be a header row... defining the column names.  We will know if it is... based upon whether the BatchID is an integer or not.
			if($actualLineCount == 1){
				if(!preg_match("/^\d+$/", trim($elementsArr[$batchIDcolumn]))){
					$actualLineCount++;
					continue;
				}
			}
			
			
			// Skip lines with Errors.
			$errorCodeColumn = $this->getPositionByColumn("ErrorCode") - 1;
			$errorCode = trim($elementsArr[$errorCodeColumn]);
			if(!empty($errorCode)){
				$actualLineCount++;
				continue;
			}
	

			
			// Find out if we are on an empty row.
			// If we are then we should skip this line.
			$emptyRow = true;
			foreach($elementsArr as $thisElement){
				$thisElement = trim($thisElement);
				if(!empty($thisElement))
					$emptyRow = false;
			}
			if($emptyRow){
				$actualLineCount++;
				continue;
			}
					
			
			// Maybe flush out the progress (for large files that take a while to process)
			if($actualLineCount % $progressOutputCounter == 0){

				if($flushProgressOutput == "none"){
					// don't do anything
				}
				else if($flushProgressOutput == "space"){
					print " ";
					Constants::FlushBufferOutput();
				}
				else if($flushProgressOutput == "javascript"){
					print "\n<script>";
					print "DataPercent('Working: ', " . ceil( $actualLineCount / $this->_originalLineCount * 100 ) . ");";
					print "</script>\n";
					Constants::FlushBufferOutput();
				}
				else if($flushProgressOutput == "xml_progress"){
					print "<percent>" . ceil( $actualLineCount / $this->_originalLineCount * 100 ) . "</percent>\n";
					Constants::FlushBufferOutput();
				}
				else{
					throw new Exception("Illegal flushProgressOutput type.");
				}
			}
			

			$importDataParsedArr[$parsedLineCount] = array();
			
			
			// Record the data from the row into our current line number.
			for($i=0; $i<sizeof($elementsArr); $i++){
					
				$colName = $this->getFieldNameByColumnNumber($i+1);

				// If the column is for the ProjectID. Strip of the Prefix or the Project Number. Ex: P34367
				if($colName == "ProjectID")
					$dataValue = preg_replace("/^P/i", "", trim($elementsArr[$i]));
				else
					$dataValue = $elementsArr[$i];
				
				$importDataParsedArr[$parsedLineCount][$colName] = $dataValue;
			}
			
			
			if(!isset($importDataParsedArr[$parsedLineCount]["BatchID"]) || !isset($importDataParsedArr[$parsedLineCount]["ProjectID"])){
				$this->_errorMessage = "An error occured importing the CSV file.  The BatchID on Row# " . $actualLineCount . " did not seem to have a BatchID or a ProjectID set.";
				@unlink($csvTempFile);
				return false;
			}
			
			
			$batchIDFromParsedRow = $importDataParsedArr[$parsedLineCount]["BatchID"];
			if($batchIDFromParsedRow != $this->_batchID){
				$this->_errorMessage = "An error occured importing the CSV file.  The BatchID on Row# " . $actualLineCount . " did not the BatchID that this mailer was intended for.";
				@unlink($csvTempFile);
				return false;
			}
			
			

			$projectIDFromParsedRow = $importDataParsedArr[$parsedLineCount]["ProjectID"];
			
			if(!preg_match("/^\d+$/", $projectIDFromParsedRow)){
				$this->_errorMessage = "An error occured importing the CSV file.  The Project Number on Row# " . $actualLineCount . " was not numerical.";
				@unlink($csvTempFile);
				return false;
			}
		
			
			// Make sure that the BatchID matches the correlating ProjectID.
			$this->_dbCmd->Query("SELECT COUNT(*) FROM mailingbatchlinks WHERE MailingBatchID=" . $this->_batchID . " AND ProjectID=" . $projectIDFromParsedRow);
			$projectFindResults = $this->_dbCmd->GetValue();
			
			if(empty($projectFindResults)){
				$this->_errorMessage = "An error occured importing the CSV file.  The Project Number on Row# " . $actualLineCount . " did not match the BatchID that this Project ID was associated with.";
				@unlink($csvTempFile);
				return false;
			}
			

			// Make sure the the Line number exists for the correlating Project.
			if(!preg_match("/^\d+$/", $importDataParsedArr[$parsedLineCount]["LineNum"])){
				$this->_errorMessage = "An error occured importing the CSV file.  The LineNumber for the project must be numberical on Row# " . $actualLineCount . ".";
				@unlink($csvTempFile);
				return false;
			}
	
			// Make sure that the line number for the ProjectID ID is not out of range.
			$projectQuantity = ProjectBase::getProjectQuantity($this->_dbCmd, "ordered", $projectIDFromParsedRow);

			if($projectQuantity < $importDataParsedArr[$parsedLineCount]["LineNum"]){
				$this->_errorMessage = "An error occured importing the CSV file.  The LineNumber for the project is out of range on Row# " . $actualLineCount . ".  This value can not exceed the total quantity on P" . $projectIDFromParsedRow .  ", which is " . $projectQuantity . ".";
				@unlink($csvTempFile);
				return false;
			}
			
			// Make sure that the Sorting Field is not null.... that is a really important field.
			if(empty($importDataParsedArr[$parsedLineCount]["Sort"])){
				$this->_errorMessage = "An error occured importing the CSV file.  The Sort Column can not be left null on Line Number: " . $actualLineCount . ".  All mailing addresses need a sequence when importing.";
				@unlink($csvTempFile);
				return false;
			}


			// Make sure that some of the critical Address components have not been left blank.
			if(empty($importDataParsedArr[$parsedLineCount]["Address1"]) || empty($importDataParsedArr[$parsedLineCount]["City"]) || empty($importDataParsedArr[$parsedLineCount]["State"]) || empty($importDataParsedArr[$parsedLineCount]["Zip"])){
				$this->_errorMessage = "An error occured importing the CSV file.  One of the required Address Fields was left blank on Line# " . $actualLineCount . ".";
				@unlink($csvTempFile);
				return false;
			}
			
			// Increment the count of each ProjectID.
			$projectCountsArr[$projectIDFromParsedRow]++;
			
			$parsedLineCount++;
			$actualLineCount++;
		}
		
		
		@unlink($csvTempFile);
		
		
		// Make sure that the imported addresses do not take down a ProjectCount to 0.
		foreach($projectCountsArr as $projectID => $newProjectQuantity){
			
			if($newProjectQuantity == 0){
				
				// If it reduces the Project down to Zero Quantity. Break the project out of the batch.
				$tempArrRemove = array();
				foreach($projectIDlistInBatch as $x){
					if($x == $projectID)
						continue;
					$tempArrRemove[] = $x;
				}
				$projectIDlistInBatch = $tempArrRemove;
				
				// Reset the Status to Proofed and leave a note in the Project History.
				ProjectOrdered::ChangeProjectStatus($this->_dbCmd, "P", $projectID);
				ProjectHistory::RecordProjectHistory($this->_dbCmd, $projectID, ("Removed from Mailing Batch: " . $this->_batchID . ". Address correction(s) reduced quantity to 0."), $this->_userID);	
				
				// That got it out the Temp Array... now we need to get it out of the Batch in the Database.
				$this->_dbCmd->Query("DELETE FROM mailingbatchlinks WHERE MailingBatchID=" . $this->_batchID . " AND ProjectID=" . $projectID);
				
				
				// Add to the Admin Note to help spot it in the order list.
				$projectOrderedObj = ProjectBase::getProjectObjectByViewType($this->_dbCmd, "ordered", $projectID);
				$adminNotes = $projectOrderedObj->getNotesAdmin();
				if(!preg_match("/Broke out of Mailing Batch/i", $adminNotes)){
					$adminNotes = "Broke out of Mailing Batch due to Address Correction. " . $adminNotes;
					$projectOrderedObj->setNotesAdmin($adminNotes);
					$projectOrderedObj->updateDatabaseWithRawData();
				}
			}
		}
		
		// In case all Projects have been removed from address corrections, delete the mailing batch.
		if(empty($projectIDlistInBatch)){
			$this->_dbCmd->Query("DELETE FROM mailingbatch WHERE ID=" . $this->_batchID);
			$this->_errorMessage = "No projects are left in the mailing batch because Address Corrections reduced all project quantities to Zero.";
			return false;
		}
		
		
		$this->_importedLineCount = sizeof($importDataParsedArr);


		// Send a message to the browser
		if($flushProgressOutput == "javascript"){
			print "\n<script>";
			print "ShowCurrentTask('<b>Status:</b> Updating mailing fields within order(s).');";
			print "</script>\n";
			Constants::FlushBufferOutput();
		}
	

		
		$lineCounterFromProjectRow = 0;
		
		// We want to transfer the updated information from the Imported data back into the Variable Data file.
		foreach($projectIDlistInBatch as $thisProjectID){
		
			$projectObj = ProjectOrdered::getObjectByProjectID($this->_dbCmd, $thisProjectID);
			
			$artworkVarsObj = new ArtworkVarsMapping($projectObj->getArtworkFile(), $projectObj->getVariableDataArtworkConfig());
			
			$variableDataObj = new VariableData();
			$variableDataObj->loadDataByTextFile($projectObj->getVariableDataFile(), $artworkVarsObj->getTotalColumns());
			
			$projectLineItems = $variableDataObj->getNumberOfLineItems();
			
			
			// Now go through each line in the variable data file and see if we can find a corresponding match
			// Remember that not every Line from the Projects's Variable data object must be present within the "imported data"... the reason is that some of the Bad Addresses may have been deleted...
			// We don't have to delete "Bad Address Lines" from the project Object at this point.... that will be done at completion time.
			for($projectRowNum=0; $projectRowNum < $projectLineItems; $projectRowNum++){
			
				for($importedRowNum=0; $importedRowNum < $this->_importedLineCount; $importedRowNum++){
					
					if($importDataParsedArr[$importedRowNum]["ProjectID"] == $thisProjectID && $importDataParsedArr[$importedRowNum]["LineNum"] == ($projectRowNum + 1)){
					
					
						$lineCounterFromProjectRow++;
		
						// Maybe flush out the progress (for large files that take a while to process)
						if($lineCounterFromProjectRow % $progressOutputCounter == 0){

							if($flushProgressOutput == "none"){
								// don't do anything
							}
							else if($flushProgressOutput == "space"){
								print " ";
								Constants::FlushBufferOutput();
							}
							else if($flushProgressOutput == "javascript"){
								print "\n<script>";
								print "DataPercent('Database Updated: ', " . ceil( $lineCounterFromProjectRow / $this->_originalLineCount * 100 ) . ");";
								print "</script>\n";
								Constants::FlushBufferOutput();
							}
							else if($flushProgressOutput == "xml_progress"){
								print "<percent>" . ceil( $lineCounterFromProjectRow / $this->_originalLineCount * 100 ) . "</percent>\n";
								Constants::FlushBufferOutput();
							}
							else{
								throw new Exception("Illegal flushProgressOutput type.");
							}
						}
			
					
						// If the Postage Rate looks valid... then update its tally for the given project
						if(preg_match("/^\d+\.\d+$/", $importDataParsedArr[$importedRowNum]["Postage"]))
							$projectPostageArr[$thisProjectID] += $importDataParsedArr[$importedRowNum]["Postage"];
						
							
						// Now Update all of the Data fields for the given Project Line.
						foreach($this->_columnNames as $thisMailingCol){

							// Once we hit the "Sort" column... we don't expect that column (or anything beyond) to be an Artwork Variable.
							if($thisMailingCol == "Sort")
								break;

							// Find out if we should append the Zip+4 to the Zip column.
							if($thisMailingCol == "Zip"){
								if(strlen($importDataParsedArr[$importedRowNum]["Zip"]) < 9)
									$dataValue = $importDataParsedArr[$importedRowNum]["Zip"] . "-" . $importDataParsedArr[$importedRowNum]["Zip4"];
								else
									$dataValue = $importDataParsedArr[$importedRowNum]["Zip"];
							}
							else{
								$dataValue = $importDataParsedArr[$importedRowNum][$thisMailingCol];
							}

							$colNumInProject = $this->getFieldMappingPositionOfVariable($artworkVarsObj, $thisMailingCol);

							// A colNumInProject means that we don't have that variable defined within the Artwork Variables on the project.
							if($colNumInProject == 0 )
								continue;

							// Set the new Value into the Project's Variable Data object.
							$variableDataObj->setVariableElementByLineAndColumnNo($projectRowNum, ($colNumInProject - 1), $dataValue);
						}
					}
				}
			}
			
			// Update the Project's Variable Data into the database with the updated Address information.
			$projectObj->setVariableDataFile($variableDataObj->getVariableDataText());
			$projectObj->updateDatabase();
		}
		
		

		
		$this->_importedDataFile =& $this->_getCSVdata($importDataParsedArr);
		
		$this->_importedDataLoadedFlag = true;
		
		$dbArr = array();
		$dbArr["AddressDataImported"] = $this->_importedDataFile;
		$dbArr["ImportedLineCount"] = $this->_importedLineCount;
		
		$this->_dbCmd->UpdateQuery("mailingbatch", $dbArr, ("ID=" . $this->_batchID));
		
		
		
		// Record that the mailing list was imported into each Project's history.
		foreach($projectIDlistInBatch as $thisProjectID){
		
			$originalProjectQuantity = ProjectBase::getProjectQuantity($this->_dbCmd, "ordered", $thisProjectID);
			$newProjectQuantity = $projectCountsArr[$thisProjectID];
			
			$adjustedNewQuantity = $this->getAdjustedProjectQuantity($originalProjectQuantity, $newProjectQuantity);
			
			if($originalProjectQuantity != $adjustedNewQuantity)
				$quantityChangeStr = "Original Quantity: " . $originalProjectQuantity . " -> New Quantity: " . $adjustedNewQuantity . ". (Project Quantity updates when order is completed.)";
			else
				$quantityChangeStr = "No quantity change.";
			
			ProjectOrdered::ChangeProjectStatus($this->_dbCmd, "Y", $thisProjectID);
			ProjectHistory::RecordProjectHistory($this->_dbCmd, $thisProjectID, ("Mailing List CASS Certified. " . $quantityChangeStr), $this->_userID);	
		
		
			// Find out if postage rate seems to be valid by taking an average.  A few bug numbers could have sneaked in, or if the data column wasn't mapped right, etc.
			$averagePostageRate = round(($projectPostageArr[$thisProjectID] / $newProjectQuantity), 3);
			
			if($averagePostageRate > $this->_validSinglePostageRateLow && $averagePostageRate < $this->_validSinglePostageRateHigh)
				ProjectHistory::RecordProjectHistory($this->_dbCmd, $thisProjectID, ("Average Postage Rate: \$" . $averagePostageRate), $this->_userID);
	
		}

		
		$this->_errorMessage = "";
		
		return true;

	
	}
	
	
	// returns a Field name by column number... like Pass in 1 and get back "Name".
	// If the column number hasn't been defined this method will return "ERROR".
	// THIS method is 1 based... not Zero based.
	function getFieldNameByColumnNumber($colNum){
		
		$colNum -= 1;
		
		if(!isset($this->_columnNames[$colNum]))
			return "ERROR";
		else
			return $this->_columnNames[$colNum];
	
	}
	
	// THIS method is 1 based... not Zero based.
	function getPositionByColumn($colName){
		
		for($i=0; $i<sizeof($this->_columnNames); $i++){
			if($colName == $this->_columnNames[$i])
				return ($i + 1);
		}
		
		throw new Exception("An error occured in the method MailingBatch->getPositionByColumn.  The field name " . $colName . " has not been defined.");
	}
	
	// Returns an array of Columns that must exist inside of a variable artwork.
	// It does not include the columns variations (or translations)... run these column names through the method $this->getFieldMappingPositionOfVariable to find out if there is a match.
	function getMandatoryColumnNames(){
		return $this->_columnNamesMandatory;
	}
	

	// Will return a 2D array.  They key to the array is the standard column name... like Address1, City.
	// The second dimension will be an array of translations for the column name ... and the column name will be included in that array 
	function getAllColumnNamesWithTranslations(){
		return $this->_columnsAndTransArr;
	}
	
	
	function _getBatchStatus(){
	
		if(empty($this->_batchID))
			throw new Exception("Error in method MailingBatch->_getBatchStatus ... the BatchID has not loaded yet.");

	
		$this->_dbCmd->Query("SELECT BatchStatus FROM mailingbatch WHERE ID=" . $this->_batchID);
		return $this->_dbCmd->GetValue();
	}
	
	
	// This is a Private Function... Pass in our private 2D array and this will return a CSV file (ascii data) by reference.
	// Pass in the second parameter as TRUE if you want to the Header row included (with column names)
	function &_getCSVdata(&$lineItemsArr){
		
		$retStr = "";
		
		foreach($lineItemsArr as $thisLine)
			$retStr .= $this->_getCSVline($thisLine) . "\n";
		
		return $retStr;
	}
	
	// Pass in a single array... does not include the newLine Character
	function _getCSVline($arr){

		$retStr = "";

		foreach($arr as $thisElement){

			// Fields with an embedded double quote must have the field surounded by double quotes and have all of the double quotes insided replaced by a pair of doubles
			// Fields with an embedded comma must be surrounded in double quotes.
			if(preg_match('/"/', $thisElement))
				$thisElement = '"' . preg_replace('/"/', '""', $thisElement) . '"';
			else if(preg_match("/,/", $thisElement))
				$thisElement = '"' . $thisElement . '"';

			$retStr .= $thisElement . ",";
		}

		// strip off the last comma
		if(!empty($retStr))
			$retStr = substr($retStr, 0, -1);
		
		return $retStr;

	}
	
	// Returns Data for the Top Row in a CSV file.  Name, Address, etc.
	function getCSVheaderLine(){
		
		$retStr = "";
		
		foreach($this->_columnNames as $thisCol)
			$retStr .= $thisCol . ",";
		$retStr = substr($retStr, 0, -1); // strip off the last comma
		$retStr .= "\n";
		
		return $retStr;
	
	}
	


	// get the HTML Row (between TR tags) for our loaded BatchID>
	// Pass in the First Parameter True if you want to see the column headers.
	// Pass in the second paramater True if you are on a Product Page (or something) and don't want to give users the ability to issue commands on behalf of the entire batch from that location. 
	function getMailingBatchHTMLrow($showHeaderRowFlag, $AuthObj){
	
		if(empty($this->_batchID))
			throw new Exception("Error in method MailingBatch->getMailingBatchHTMLtable ... the BatchID has not loaded yet.");	

		
		if($showHeaderRowFlag){
		
			$retHTML = "
				  <tr>
				    <td width=\"27%\" bgcolor=\"#555577\" class=\"Body\"><font class='#FFFFFF'><b>Mailing Batch ID</b></font></td>
				    <td width=\"25%\" bgcolor=\"#555577\" class=\"Body\"><font class='#FFFFFF'><b>Status</b></font></td>
				    <td width=\"24%\" bgcolor=\"#555577\" class=\"Body\"><font class='#FFFFFF'><b>Original List</b></font></td>
				    <td width=\"24%\" bgcolor=\"#555577\" class=\"Body\"><font class='#FFFFFF'><b>Imported List</b></font></td>
				  </tr>
			";
		}
		else{
			$retHTML = "";
		}
			
		
		$retHTML = "
			  <tr>
			    <td width=\"27%\" bgcolor=\"#EEEEFF\" class=\"SmallBody\"><b>Batch #:</b>: __MAILBATCHID__<br><b>Created On:</b>: __STARTDATE__<br>__PRODUCT_OPTIONS__ __URGENT_STATUS__</td>
			    <td width=\"25%\" bgcolor=\"#EEEEFF\" class=\"SmallBody\">__BATCHSTATUS__<br>Last Change: __LASTSTATUSDATE__<br>__BATHCOMMAND__</td>
			    <td width=\"24%\" bgcolor=\"#EEEEFF\" class=\"SmallBody\">__ORIGINALCSV__<br>Project Count: __PROJECTCOUNT__ <br>Original Quantity: __ORIGINALQUANTITY__</td>
			    <td width=\"24%\" bgcolor=\"#EEEEFF\" class=\"SmallBody\">__PDFMERGE____IMPORTEDCSV__<br><img src='./images/transparent.gif' width='5' height='5'><br>New Quantity: __NEWQUANTITY__<br><img src='./images/transparent.gif' width='5' height='5'>__IMPORTMAILINGLIST__</td>
			  </tr>
		";
		
	
		
		$projectStatus = ProjectStatus::GetProjectStatusDescription($this->GetProjectStatusOfBatch(), true);
		$dateCreatedOn = date("M j, G:i", $this->dateCreated());
		$lastStatusTimeStamp = date("M j, G:i", $this->getTimestampOfLastStatusChange());
		
		$projectIDarrInBatch = $this->getUniqueListOfProjectsWithinBatch();
		
		
		// Replace Variables in TABLE
		$retHTML = preg_replace("/__MAILBATCHID__/", $this->_batchID, $retHTML);
		$retHTML = preg_replace("/__BATCHSTATUS__/", $projectStatus, $retHTML);
		$retHTML = preg_replace("/__ORIGINALQUANTITY__/", $this->getOriginalLineCount(), $retHTML);
		$retHTML = preg_replace("/__STARTDATE__/", $dateCreatedOn, $retHTML);
		$retHTML = preg_replace("/__LASTSTATUSDATE__/", $lastStatusTimeStamp, $retHTML);
		
		
		if($AuthObj->CheckForPermission("MAILING_LIST_IMPORT"))
			$downloadLinkOriginalCSV = "<a class='blueredlink' href=\"javascript:DownloadOriginalCSVfromBatch(" . $this->_batchID . ");\">Original CSV File</a>";
		else
			$downloadLinkOriginalCSV = "";
	
		
		if($this->_importedLineCount > 0){
			$downloadLinkImportedCSV = "<a class='blueredlink' href=\"javascript:DownloadImportedCSVfromBatch(" . $this->_batchID . ");\">Download Imported CSV File</a>";
			$downloadLinkImportedCSV .= "<br><a class='blueredlink' href=\"javascript:DownloadMergedCSVfromBatch(" . $this->_batchID . ");\">Merged CSV File</a>";
			
			$downloadPDFlink = "";
			
			if($AuthObj->CheckForPermission("MAILING_ARTWORK_PRODUCTION"))
				$downloadPDFlink .= "<a class='blueredlink' href=\"javascript:ShowMailingBatchPDF(" . $this->_batchID . ", 'IgenProduction');\">Generate PDF File</a> ";
				
			if(!empty($downloadPDFlink))
				$downloadPDFlink .= "<br><img src='./images/transparent.gif' width='5' height='5'><br>";
		}
		else{
			$downloadLinkImportedCSV = "No Data Import Yet";
			$downloadPDFlink = "";
		}
		
		if(!$AuthObj->CheckForPermission("MAILING_LIST_IMPORT")){
			$downloadLinkImportedCSV = "";
		}
		
		
		
		$newQuantityDesc = $this->_importedLineCount;
		
		// Figure out how many pages this mailer will be, based upon the default PDF profile.

		if(!empty($this->_importedLineCount)){
			
			$defaultPDFprofile = new PDFprofile($this->_dbCmd,$this->getProductionProductIDofBatch());
			$defaultPDFprofile->loadProfileByName($defaultPDFprofile->getDefaultProfileName());
			
			$totalSheetCount = ceil($this->_importedLineCount / $defaultPDFprofile->getQuantity()) * 2 + 2; // Times by 2 because they are always double-sided... add 2 for coversheet.
			
			$newQuantityDesc .= "<br>Impressions: " . $totalSheetCount;
		}
		
		
		
		
		$pipedListProjects = "";
		$urgentProducts = 0;
		foreach($projectIDarrInBatch as $thisPID){
			$pipedListProjects .= $thisPID . "|";
			
			if(ProjectOrdered::CheckIfUrgent($this->_dbCmd, $thisPID))
				$urgentProducts++;
		}
		
		
		if($urgentProducts > 0)
			$retHTML = preg_replace("/__URGENT_STATUS__/", "<br><font color='#cc0000'>$urgentProducts Urgent", $retHTML);
		else
			$retHTML = preg_replace("/__URGENT_STATUS__/", "", $retHTML);
			

		
		$projectCountLink = "<a class='blueredlink' href='javascript: document.forms[\"batchWindowMailingsForm\"].target=\"mailBatch" . $this->_batchID . "\"; document.forms[\"batchWindowMailingsForm\"].projectlist.value=\"" . $pipedListProjects  . "\"; document.forms[\"batchWindowMailingsForm\"].submit();'>" . sizeof($projectIDarrInBatch) . "</a>";
		
		$retHTML = preg_replace("/__ORIGINALCSV__/", $downloadLinkOriginalCSV, $retHTML);
		$retHTML = preg_replace("/__IMPORTEDCSV__/", $downloadLinkImportedCSV, $retHTML);
		$retHTML = preg_replace("/__NEWQUANTITY__/", $newQuantityDesc, $retHTML);
		$retHTML = preg_replace("/__ORIGINALQUANTITY__/", $this->_originalLineCount, $retHTML);
		$retHTML = preg_replace("/__PROJECTCOUNT__/", $projectCountLink, $retHTML);
		$retHTML = preg_replace("/__PDFMERGE__/", $downloadPDFlink, $retHTML);
		
		
		$projectStatusOfBatch = $this->GetProjectStatusOfBatch();
		
		
		// If the Batch Status is Printed... then an administrator should have the option to "Complete" the batch.
		$batchCommand = "";
		if($this->_getBatchStatus() == "OPEN"){
			
			if($projectStatusOfBatch == "T" && $AuthObj->CheckForPermission("MAILING_BATCHES_COMPLETE"))
				$batchCommand .= "<a class='blueredlink'  href=\"javascript:CompleteMailingBatch(" . $this->_batchID . ");\"><font style='font-size:16px;'><b>Complete Batch</b></font></a><br><img src='./images/transparent.gif' width='6' height='6'><br>";

			// We should always have permission to Cancel a batch... as long as the Batch is still "OPEN".
			if($AuthObj->CheckForPermission("MAILING_BATCHES_CANCEL"))
				$batchCommand .= "<a class='blueredlink'  href=\"javascript:CancelMailingBatch(" . $this->_batchID . ");\">Cancel Batch</a>";
		}
	
		if(empty($batchCommand))
			$batchCommand = "Commands Unavailable";
		
		
		$retHTML = preg_replace("/__BATHCOMMAND__/", $batchCommand, $retHTML);

		
		// People should not be shown the button to Import new data if the batch has already been printed.
		// Also, don't show them the button if our flag was set to not let commands.
		if(!$AuthObj->CheckForPermission("MAILING_LIST_IMPORT") || !($projectStatusOfBatch == "G" || $projectStatusOfBatch == "Y"))
			$importDataButton = "";
		else
			$importDataButton = "<br><a href=\"javascript:ImportMailingData(" . $this->_batchID . ")\"><img src='./images/UploadMailingList-a.gif' width='131' height='21' border='0' onMouseOver=\"this.src='./images/UploadMailingList-b.gif';\" onMouseOut=\"this.src='./images/UploadMailingList-a.gif';\"></a>";

		$retHTML = preg_replace("/__IMPORTMAILINGLIST__/", $importDataButton, $retHTML);
		
		
		$productIDofProduction = $this->getProductionProductIDofBatch();
		$productObj = Product::getProductObj($this->_dbCmd, $productIDofProduction, false);
		
		// Build a String of Options and Selections that are in Common
		$optionsStr = $productObj->getProductTitleWithExtention() . "<br>";
		$optionsInCommon = $this->getOptionsInCommonForBatch();
		
		foreach($optionsInCommon as $thisOptionName => $thisOptionValue)
			$optionsStr .= WebUtil::htmlOutput($thisOptionName) . " &gt; " . WebUtil::htmlOutput($thisOptionValue) . "<br>";

		
		if(!empty($optionsStr))
			$optionsStr = "<img src='./images/transparent.gif' width='4' height='4'><br>" . $optionsStr;
			
		$optionsStr = "<font class='reallysmallbody'><font color='#336633'>" . $optionsStr . "</font></font>";
		
		
		$retHTML = preg_replace("/__PRODUCT_OPTIONS__/", $optionsStr, $retHTML);
	
		
		return $retHTML;

	}
	
	

	


}




?>

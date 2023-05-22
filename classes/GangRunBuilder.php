<?

class GangRunBuilder {



	private $_profilePriorityOverride;
	private $_duplexFlag;
	private $_sheetQuantity;
	
	private $_gangRunGatheredFlag;

	private $_superPDFprofileNamesArr = array();
	
	private $_gangObjectsArr = array();
	
	private $_preferLargeRunsFirst;
	
	private $_projectIDpoolArr = array();
	
	private $_gangObjectLimit;
	
	private $_limitMinQuantity;
	private $_limitMaxQuantity;
	
	private $_limitUrgentFlag;
	
	private $_limitProductIDarr = array();
	private $_limitOptionsArr = array();
	
	private $_projectIDarr = array();
	
	private $_forceQuantityFlag;
	


	##--------------   Constructor  ------------------##

	// An Array of printing press IDs is needed so that we can determine what PDF SuperProfiles may be tried for the gang build.
	// I think this is the right place to determine this.  When you make a new SuperPDFprofile you know it can only work on certain printing presses due to paper size, etc.
	// Also, I think printing presses should be associated with a location (such as a manufacturing plant).  
	// So you are saying when you start up a gang run... I am in charge of these printing presses... what do you have for me to print today.
	// The SuperPDFprofile does authentication on Domains... so you can before whatever Super PDF profiles are found for the printing presses, you can trust them.
	function GangRunBuilder(DbCmd $dbCmd, array $printingPressIDs, $sheetQuantity, $isDuplex, $forceQuantityFlag = false){
	
		$this->_dbCmd = $dbCmd;

		$this->_duplexFlag = $isDuplex;
		
		if(!preg_match("/^\d+$/", $sheetQuantity))
			throw new Exception("Error in GangRunBuilder contructor... Sheet quantity must be numeric.");
			
		$this->_sheetQuantity = $sheetQuantity;
		
		$this->_limitUrgentFlag = false;
		$this->_limitProductIDarr = array();
		$this->_limitMinQuantity = null;
		$this->_limitMaxQuantity = null;
		$this->_limitOptionsArr = array();
		
		$this->_preferLargeRunsFirst = false;
		
		$this->_gangObjectsArr = array();
		
		$this->_gangRunGatheredFlag = false;
		
		$this->_projectIDpoolArr = array();
		
		$this->_gangObjectLimit = 10;
		
		$this->_forceQuantityFlag = $forceQuantityFlag;
		
		foreach($printingPressIDs as $thisPressID){
			if(!PrintingPress::checkIfPrintingPressIDExists($thisPressID))
				throw new Exception("Error in GangRunBuilder contructor. One of the printing presses does not exist.");
		}
		if(empty($printingPressIDs))
			throw new Exception("Error in GangRunBuilder contructor. No printing presses were given to try the Gang Build on.");
			
		$domainObj = Domain::singleton();
		
		// The Key to the Array is the Super PDF Profile ID... and the Value is the Profile Name.
		$this->_superPDFprofileNamesArr = SuperPDFprofile::getProfilesLinkedToPrintingPresses($dbCmd, $printingPressIDs, $domainObj->getSelectedDomainIDs());
	}
	
	
	// Instead of letting the Database query all Proofed Projects... you can have it query from (limited to) the ProjectID's in this list
	// Returns FALSE if you are trying to build a gang from a list that has a Status or Not (Proofed or For Offset)
	function setProjectIDsInPool($projectIDarr){
		
		$retFlag = true;
	
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		$allDomainsForUser = $passiveAuthObj->getUserDomainsIDs();
		
		$dbCmd = new DbCmd();
		
		foreach($projectIDarr as $thisProjectID){
			
			$domainIDofProject = ProjectBase::getDomainIDofProjectRecord($dbCmd, "ordered", $thisProjectID);
			
			if(!in_array($domainIDofProject, $allDomainsForUser))
				throw new Exception("Error in GangRun Builder. One of the Project IDs does not exist. P$thisProjectID");
		
			$projectStatus = ProjectOrdered::GetProjectStatus($this->_dbCmd, $thisProjectID);
			
			if(!in_array($projectStatus, array("P", "D")))
				$retFlag = false;
		}
		
		$this->_projectIDpoolArr = $projectIDarr;
		
		return $retFlag;
	}
	
	
	// Set this method to gather the big jobs first.
	function preferBigRunsFirst($x){
	
		if(!is_bool($x))
			throw new Exception("Error in method GangRunBuilder->preferBigRunsFirst.  Must be boolean");
	
		$this->_preferLargeRunsFirst = $x;
	}
	
	
	function limitMinimumQuantity($x){
	
		$this->_limitMinQuantity = $x;
	}
	
	function limitMaximumQuantity($x){
	
		$this->_limitMaxQuantity = $x;
	}
	
	
	// To save on Processing Power... you can set a limit to the number of gangs that can be built up..
	function setGangObjectLimit($x){
	
		WebUtil::EnsureDigit($x, true, "Error in setGangObjectLimit, not numeric.");
	
		$this->_gangObjectLimit = $x;
	}
	
	
	
	function limitToUrgent($x){
	
		if(!is_bool($x))
			throw new Exception("Error in method GangRunBuilder->limitToUrgent.  Must be boolean");
	
		$this->_limitUrgentFlag = $x;
	}
	

	
	
	function limitToProductIDarr($arr){
	
		$this->_limitProductIDarr = $arr;
	}
	
	
	// Add as many product Option Limiters as you want by calling this method repeateadly.
	// It will increasingly make the list more restrictive... (AND clause... not an OR clause).
	// Should match exactly what you see on the open order list (what is stored in the DB.... like "Card Stock - Glossy"
	function addProductOptionLimit($optionsDescription){
	
		$optionsDescription = trim($optionsDescription);
		
		if(empty($optionsDescription))
			return;
	
		$this->_limitOptionsArr[] = $optionsDescription;
	}
	
	
	

	// Returns how many gang runs were able to be gathered... regardless of how optimized.
	function getNumberOfGangRuns(){
	
		if(!$this->_gangRunGatheredFlag)
			throw new Exception("Error in method GangRunBuilder->getNumberOfGangRuns ... you must call the method gatherGangs() first.");
		
		return sizeof($this->_gangObjectsArr);
	}
	
	
	
	function getGangObjectsArr(){
	
		return $this->_gangObjectsArr;
	}
	
	// Returns the highest score out of the bunch of gang runs.
	function getTopEconomicRatingInGangs(){

		if(!$this->_gangRunGatheredFlag)
			throw new Exception("Error in method GangRunBuilder->getTopEconomicRatingInGangs ... you must call the method gatherGangs() first.");
		
		if(sizeof($this->_gangObjectsArr) == 0)
			return 0;
		
		// The Array should have been sorted already... so the most encomic one is at top.
		return $this->_gangObjectsArr[0]->getEcomicRating();	
	}
	
	
	
	// Execute this method after all of the limiters have been set. 
	// It will query the database for the projectList.
	function gatherGangs(){


		$this->_gatherProjectIDs();
		
		$this->_gangRunGatheredFlag = true;
		
		if(sizeof($this->_projectIDarr) == 0)
			return;
		
		$remainingProjectIDs = $this->_projectIDarr;
		
		$sizeOfRemainingProjects = sizeof($remainingProjectIDs);
		$lastProjectCount = 0;
		
		// Keep going until the Project Count is not decreasing anymore.
		while($lastProjectCount != $sizeOfRemainingProjects){

			$lastProjectCount = sizeof($remainingProjectIDs);
		
			// Will hold the Efficiency and Fullness Averages of each GangRun test according to the profile ID (as the key).
			$economicRatingsArr = array();

			// Now start creating gang runs... starting with our first SuperProfileName (which has the highest priority) and keep going down the list until we run out of ProjectID's or Profiles.
			foreach(array_keys($this->_superPDFprofileNamesArr) as $thisProfileID){
			
				$gangObj = $this->_buildGang($thisProfileID, $remainingProjectIDs);
	
				$economicRatingsArr[$thisProfileID] = $gangObj->getEcomicRating();
			}
			
			// Sort in reverse orders... so that scored with 100, 98, etc. appear at the top of the list.
			arsort($economicRatingsArr, SORT_NUMERIC);
			
			
			// The more economical jobs are up on top... but in case two of the profiles have equal economic ratings... we want to use the profile with the highest priority.
			$lastProfileID = 0;
			$lastScore = 0;
			$matchingTopProfilesArr = array();
			foreach($economicRatingsArr as $thisProfileID => $thisScore){

				
				if(empty($lastScore))
					$lastScore = $thisScore;
				if(empty($lastProfileID))
					$lastProfileID = $thisProfileID;
				
				if($thisScore < $lastScore)
					break;
				else
					$matchingTopProfilesArr[] = $thisProfileID;
			}
			
			$bestProfileID = null;
			
			// Go through our list of PDF profiles (in order of importance and pick the first one that is in our top candidates of scoring.
			// Users can control the position of the Super PDF profiles through the database.
			foreach(array_keys($this->_superPDFprofileNamesArr) as $thisProfileID){
				if(in_array($thisProfileID, $matchingTopProfilesArr)){
					$bestProfileID = $thisProfileID;
					break;
				}
			}
	
			
			$bestGangObj = $this->_buildGang($bestProfileID, $remainingProjectIDs);
			
			// Stop once we can not longer make a Gang run with any of our Project ID's
			if($bestGangObj->getFullness() == 0)
				break;

			// Add this GangRun object to an array in memory.
			$this->_gangObjectsArr[] = $bestGangObj;
			
			
			if($this->_gangObjectLimit != 0 && sizeof($this->_gangObjectsArr) >= $this->_gangObjectLimit)
				break;
			
			
			// Get rid of any remaining projects that were accepted in our gang run.
			$projectsInGangArr = $bestGangObj->getProjectIDlist();
			
			foreach($projectsInGangArr as $thisProjectIDinGang)
				WebUtil::array_delete($remainingProjectIDs, $thisProjectIDinGang);
				
			$sizeOfRemainingProjects = sizeof($remainingProjectIDs);
			
			if($sizeOfRemainingProjects == 0)
				break;
		}
		
		$this->_sortGangsMostEconomic();
		
	}
	
	
	// Basically changes the priority of the PDF super profiles by bubbling the given profile to the very front of the stack.
	function setProfilePriorityOverride($superPDFprofileID){
	
		if(!in_array($superPDFprofileID, array_keys($this->_superPDFprofileNamesArr)))
			throw new Exception("Error in method GangRunBuilder->setProfilePriorityOverride ... The profile ID does not exist or it is not in the Pool of Available printing presses.");
			
		$newArray = array();
		
		$newArray[$superPDFprofileID] = $this->_superPDFprofileNamesArr[$superPDFprofileID];
		
		foreach($this->_superPDFprofileNamesArr as $thisProfileID => $thisProfileName){
		
			// Don't include the one that was just sent to the front of the pack.
			if($thisProfileID == $superPDFprofileID)
				continue;
			
			$newArray[$thisProfileID] = $thisProfileName;
		}
		
		$this->_superPDFprofileNamesArr = $newArray;
	
	}
	
	
	
	// Set the Profile so that this is the only option for building a gang run out of.
	function setProfileMandatory($superPDFprofileID){
	
		if(!in_array($superPDFprofileID, array_keys($this->_superPDFprofileNamesArr)))
			throw new Exception("Error in method GangRunBuilder->setProfileMandatory ... The profile ID does not exist or it is not in the Pool of Available printing presses.");
		
		$profileName = $this->_superPDFprofileNamesArr[$superPDFprofileID];
			
		$this->_superPDFprofileNamesArr = array();
		$this->_superPDFprofileNamesArr[$superPDFprofileID] = $profileName;
	}
	
	
	
	// Sorts the array of gang runs so that the most economic ones are in the first positions.
	private function _sortGangsMostEconomic(){
	
		$ratingsArr = array();
		$sequenceArr = array();
		
		$gangCounter = 0;
		foreach($this->_gangObjectsArr as $thisGangObj){
		
			$ratingsArr[$gangCounter] = $thisGangObj->getEcomicRating();
			$sequenceArr[$gangCounter] = $gangCounter + 1;
			
			$gangCounter++;
		}
		
		
		// Add a fractional to the economic rating.... only when there are matching values.
		// This extra value will never exceed 1... so it will never cross paths with another economic group.
		// This will keep Gangs in the same seqence... even if there economics match identically.
		$lastEconomicNum = 0;
		$economicSameCounter = 0;
		
		foreach($ratingsArr as $ratingKey => $ratingValue){
		
			if($lastEconomicNum == $ratingValue){
				$ratingsArr[$ratingKey] = $ratingValue - $economicSameCounter * 0.0001;
				$economicSameCounter++;
			}
			else{
				$lastEconomicNum = $ratingValue;
				$economicSameCounter =  0;
			}
		}
		
		
		arsort($ratingsArr, SORT_NUMERIC);
		
		$economicKeysSorted = array_keys($ratingsArr);
		
		$tempArr = array();
		
		foreach($economicKeysSorted as $thisKey)
			$tempArr[] = $this->_gangObjectsArr[$thisKey];
		
		$this->_gangObjectsArr = $tempArr;

	}
	
	
	// Private method.
	// Tries a building a GangRun using the supplied ProductID's and returns a GangRun object.
	// Stops when it has tried every project ID... or the gang sheet becomes totally full.
	private function _buildGang($superProfileID, $projectIDs){
	
		$gangObj = new GangRun($this->_dbCmd);
		
		$gangObj->initializeNewGangRun($this->_sheetQuantity, $superProfileID, $this->_duplexFlag, $this->_forceQuantityFlag);
		
		foreach($projectIDs as $thisProjectID){
			
			$gangObj->addProject($thisProjectID);
			
			if($gangObj->fullRun())
				break;
		}
		
		$gangObj->possiblySwitchToSimplex();
		
		return $gangObj;
	}
	
	


	
	// An internal method to gather all project ID's (based upon our limiters).  The order of the projectIDs in our array object is significant.
	// List is sorted in order by 1) Double-Sided orders, then Single Sided  2) Quantity Groups 3) Expedited Shipping 4) Urgent Orders 5) The Oldest Orders first.
	private function _gatherProjectIDs(){
	
		$domainObj = Domain::singleton();
		$query = "SELECT ID FROM projectsordered WHERE (Status = 'P' OR  Status = 'D') 
						AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs());
		
		// ProductID limiters
		if(!empty($this->_limitProductIDarr)){
		
			$query .= " AND (";
			
			$productOR = "";
			
			foreach($this->_limitProductIDarr as $thisProductID){
			
				if(!empty($productOR))
					$productOR .= " OR ";
				
				$productOR .= "ProductID=" . $thisProductID;
			}
			
			$query .= $productOR . ")";
		}
		
		
		// Product Option Limiters
		if(!empty($this->_limitOptionsArr)){
		
			$query .= " AND (";
			
			$optionsAND = "";
			
			foreach($this->_limitOptionsArr as $thisOptionsDesc){
			
				if(!empty($optionsAND))
					$optionsAND .= " AND ";
				
				$optionsAND .= "OptionsAlias LIKE \"%" . DbCmd::EscapeLikeQuery($thisOptionsDesc) . "%\"";
			}
			
			$query .= $optionsAND . ")";
		}
		
		
		if($this->_limitUrgentFlag)
			$query .= " AND Priority='U' ";
			
		
		// Quantity Limiters ... Don't use a BETWEEN clause if both are set because there are some Mysql Optimization problems with between and indexes at the moment.
		if(!empty($this->_limitMinQuantity)) 
			$query .= " AND Quantity > " . $this->_limitMinQuantity;
		if(!empty($this->_limitMaxQuantity)) 
			$query .= " AND Quantity < " . $this->_limitMaxQuantity;
			
		
		
		// Possibly limit the Projects to what is in our Pool of available ProjectID's.
		if(!empty($this->_projectIDpoolArr)){
		
			$query .= " AND ( ";
			
			$projectOrClause = "";
			
			foreach($this->_projectIDpoolArr as $thisProjectID){
			
				if(!empty($projectOrClause))
					$projectOrClause .= " OR ";
				
				$projectOrClause .= "ID=" . $thisProjectID;
			}
			$query .= $projectOrClause . ")";
		}
		
		
		
		// We are going to search and replace this to split this query into a couple of pieces.
		$query .= " {VARIALBE} ";
		
		
		if($this->_preferLargeRunsFirst)
			$quantitySorter = "Quantity DESC";
		else
			$quantitySorter = "Quantity ASC";
		
		
		
		// First we want to have the smallest quantities first.... and the oldest orders at the top of each group.
		// Priority is descending since we want "U"rgent to come before "N"ormal.
		// We want the most expensive shipping orders to be listed first (within a quantity group) because that is an indicator of expedited shipping.
		$query .= " ORDER BY " . $quantitySorter . ", ShippingPriority ASC, Priority DESC, ID ASC";
		
		
		
		
		// We want the double-sided orders followed by the single-sided orders.
		// It is up to the user of this object if they want to get only Single-Sided orders.... in which case the double-sided query below will always come up empty.
		$queryDuplex = preg_replace("/\{VARIALBE\}/", " AND ArtworkSides=2", $query);
		$queryRegular = preg_replace("/\{VARIALBE\}/", "", $query);
		
	
		$this->_dbCmd->Query($queryDuplex);
		
		$projectIDduplexArr = array();
		while($pid = $this->_dbCmd->GetValue())
			$projectIDduplexArr[] = $pid;
			
		
		$this->_dbCmd->Query($queryRegular);
		
		$projectIDregularArr = array();
		while($pid = $this->_dbCmd->GetValue())
			$projectIDregularArr[] = $pid;
			
		
		// Now store the project IDs in our object... all double-sided ones first, followed by single-sided.
		$projectArr = array();
		
		foreach($projectIDduplexArr as $thisProjID)
			$projectArr[] = $thisProjID;
		
		
		// Make sure to not add multiple project ID's... since there is not a single-sided query.
		foreach($projectIDregularArr as $thisProjID){	
			if(!in_array($thisProjID, $projectArr))
				$projectArr[] = $thisProjID;
		}
		
		
		
		$this->_projectIDarr = $projectArr;
		
	}

}





?>
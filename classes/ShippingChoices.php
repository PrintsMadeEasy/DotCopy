<?php

class ShippingChoices {
	
	private $_dbCmd;
	private $shippingMethodsObj;
	private $domainID;
	private $downgradeShippingFlag = false;
	private $shippingChoicesHash = array();
	private $priorityChoicesArr = array();
	private $quantityBreaksArr = array();
	private $shippingMethodsDowngrades = array();
	private $shippingMethods1to1Map = array();
	
	
	private static $dbCmdCache;
	private static $shippingChoiceHTMLcache = array();
	private static $shippingChoiceNamesCache = array();
	private static $shippingPrioritiesCache = array();
	private static $shippingMetodCodeDefaultCache = array();
	private static $shippingChoiceObjectsForDomainCache = array();
	private static $allExpeditedShippingChoiceIDsCache = array();
	private static $domainExpeditedShippingChoiceIDsCache = array();
	
	const URGENT_PRIORITY = "1";
	const HIGH_PRIORITY = "2";
	const ELEVATED_PRIORITY = "3";
	const MEDIUM_PRIORITY = "4";
	const NORMAL_PRIORITY = "5";
	
	
	const ADDRESS_TYPE_COMMERCIAL = "C";
	const ADDRESS_TYPE_RESIDENTIAL = "R";
	const ADDRESS_TYPE_NONE = "N";
	
	
	// Define the Column names matching our database to make code hinting easier as we manipulate the Hash in memory.
	private static $DB_ID = "ID";
	private static $DB_DOMAIN_ID = "DomainID";
	private static $DB_SHIP_CHOICE_NAME = "ShippingChoiceName";
	private static $DB_SHIP_CHOICE_ID = "ShippingChoiceID";
	private static $DB_TRANSIT_DAYS = "TransitDays";
	private static $DB_SEQUENCE = "Sequence";
	private static $DB_BASIC_SHIPPING_CHOICE = "BasicChoice";
	private static $DB_DEFAULT_CHOICE = "DefaultChoice";
	private static $DB_COLOR_CODE = "ColorCode";
	private static $DB_PRIORITY = "Priority";
	private static $DB_PRICE_INITIAL = "PriceInitial";
	private static $DB_PRICE_PER_POUND = "PricePerPound";
	private static $DB_PRODUCT_ID = "ProductID";
	private static $DB_WEIGHT = "Weight";
	private static $DB_SHIP_METHOD_CODE = "ShippingMethodCode";
	private static $DB_MIN_WEIGHT = "MinimumWeight";
	private static $DB_MAX_WEIGHT = "MaximumWeight";
	private static $DB_ALERT_MESSAGE = "AlertMessage";
	private static $DB_IS_DEFAULT = "IsDefault";
	private static $DB_RURAL_FEE = "RuralFee";
	private static $DB_EXTENDED_DISTANCE_FEE = "ExtendedDistanceFee";
	private static $DB_ADDRESS_TYPE = "AddressType";
	
	
	
	// Pass in a Domain Name for the Shipping Choices, or by default it will use whatever domain you are currently viewing.
	function __construct($domainID = NULL){

		$this->_dbCmd = new DbCmd();
		
		if(!empty($domainID)){
			if(!Domain::checkIfDomainIDexists($domainID))
				throw new Exception("Error in Shipping Choices contructor with DomainID.");
			$this->domainID = $domainID;
		}
		else{
			$this->domainID = Domain::oneDomain();
		}
		
		$domainObj = Domain::singleton();
		$this->downgradeShippingFlag = $domainObj->doesDomainDowngradeShipping($this->domainID);
		
		$this->shippingMethodsObj = new ShippingMethods($this->domainID);

		$this->priorityChoicesArr = array(self::URGENT_PRIORITY=>"Urgent", self::HIGH_PRIORITY=>"High", self::ELEVATED_PRIORITY=>"Elevated", self::MEDIUM_PRIORITY=>"Medium", self::NORMAL_PRIORITY=>"Normal");
	
		$this->loadShippingChoicesFromDB();
	}
	
	
	// You should figure out from the controller scripts if the user has permission to delete choices, etc.
	// There could be some kind of cross domain hacking.
	static function getDomainIDofShippingChoiceID($shippingChoiceID){
		
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT DomainID FROM shippingchoices WHERE ID=" . intval($shippingChoiceID));
		$domainID = $dbCmd->GetValue();
		
		if(empty($domainID))
			throw new Exception("Error in method getDomainIDofShippingChoiceID");
			
		return $domainID;
	}
	static function getDomainIDofShippingLinkID($shippingLinkID){
	
		$domainObj = Domain::singleton();
		$downgradeShipping = $domainObj->doesDomainDowngradeShipping(Domain::oneDomain());
		
		$dbCmd = new DbCmd();
		
		if($downgradeShipping)
			$dbCmd->Query("SELECT ShippingChoiceID FROM shippingchoicesdowngrades WHERE ID=" . intval($shippingLinkID));
		else
			$dbCmd->Query("SELECT ShippingChoiceID FROM shippingchoices1to1map WHERE ID=" . intval($shippingLinkID));
			
		$shippingChoiceID = $dbCmd->GetValue();
		
		if(empty($shippingChoiceID))
			throw new Exception("Error in method getDomainIDofShippingLinkID");
			
		return self::getDomainIDofShippingChoiceID($shippingChoiceID);
	}
	
	
	// This is similar to a Singleton pattern.  It creates a new Shipping Choices Object for each domain and retains it within the cache.
	// If you are looping through a giant list of Shipment or Order ID's... you don't want to incur the overhead of creating a new ShippingChoice object for every Domain of every order record.
	static function getShippingChoiceObjectFromCache($domainID){

		if(isset(self::$shippingChoiceObjectsForDomainCache[$domainID]))
			return self::$shippingChoiceObjectsForDomainCache[$domainID];
		
		if(!Domain::checkIfDomainIDexists($domainID))
			throw new Exception("Error in getShippingChoiceObjectFromCache with DomainID.");
			
		self::$shippingChoiceObjectsForDomainCache[$domainID] = new ShippingChoices($domainID);
		
		return self::$shippingChoiceObjectsForDomainCache[$domainID];
	}
	
	
	// Will move the sorting sequence up or down relative to where it was (compared to other SuperPDF profiles).
	static function moveShippingChoicePosition($shippingChoiceID, $moveForwardFlag, $domainID = NULL){
		
		$shippingChoiceObj = new ShippingChoices($domainID);
		
		$allShippingChoiceIDs = array_keys($shippingChoiceObj->getAllShippingChoices());
		
		if(!in_array($shippingChoiceID, $allShippingChoiceIDs))
			throw new Exception("Error in method moveShippingChoicePosition. The ID does not exist.");
			
		$arrayShifted = WebUtil::arrayMoveElement($allShippingChoiceIDs, $shippingChoiceID, $moveForwardFlag);
		
		$dbCmd = new DbCmd();
		for($newSortPos=0; $newSortPos<sizeof($arrayShifted); $newSortPos++)
			$dbCmd->UpdateQuery("shippingchoices", array("Sequence"=>$newSortPos+1), "ID=" . $arrayShifted[$newSortPos]);
	}
	
	
	// Will move the sorting sequence up or down relative to where it was (compared to other SuperPDF profiles).
	static function moveLinkedShippingMethodPosition($shippingMethodLinkID, $moveForwardFlag, $domainID = NULL){
	
		$shippingChoiceObj = new ShippingChoices($domainID);
		
		if(!$shippingChoiceObj->checkIfShippingMethodLinkIDexists($shippingMethodLinkID))
			throw new Exception("Error in method ShippingChoices->moveLinkedShippingMethodPosition");
		
		if(!$shippingChoiceObj->isShippingDowngrade())
			throw new Exception("Error in method moveLinkedShippingMethodPosition. Can not move the position of shipping linking with 1-to-1 shipping method linking");
			
		$shippingChoiceID = $shippingChoiceObj->getShippingChoiceIDfromShippingMethodLinkID($shippingMethodLinkID);
			
		$allShippingMethodLinkIDs = $shippingChoiceObj->getShippingMethodLinkIDs($shippingChoiceID);
		
		if(!in_array($shippingMethodLinkID, $allShippingMethodLinkIDs))
			throw new Exception("Error in method moveLinkedShippingMethodPosition. The Shipping Method Link ID does not exist for the given choice.");
			
		$arrayShifted = WebUtil::arrayMoveElement($allShippingMethodLinkIDs, $shippingMethodLinkID, $moveForwardFlag);

		$dbCmd = new DbCmd();
		for($newSortPos=0; $newSortPos<sizeof($arrayShifted); $newSortPos++)
			$dbCmd->UpdateQuery("shippingchoicesdowngrades", array("Sequence"=>$newSortPos+1), "ID='" . $arrayShifted[$newSortPos] . "'");
	}
	
	
	// Because this may show up in order lists it needs to rapidly return the color code from the DB
	// Will cache the results during the Script execution to cut down on DB access.
	// It may return something like "<font color='#990000'><b>One Day Early</b></font>"
	// Does not do any type of authentication to make sure that the Shipping Choice belongs to the right domain.
	static function getHtmlChoiceName($shippingChoiceID){
		
		if(isset(self::$shippingChoiceHTMLcache[$shippingChoiceID]))
			return self::$shippingChoiceHTMLcache[$shippingChoiceID];
			
		if(empty(self::$dbCmdCache))
			self::$dbCmdCache = new DbCmd();
		
		self::$dbCmdCache->Query("SELECT " . self::$DB_COLOR_CODE . ", " . self::$DB_SHIP_CHOICE_NAME . " FROM shippingchoices WHERE ID=" . intval($shippingChoiceID));
		if(self::$dbCmdCache->GetNumRows() == 0)
			throw new Exception("Error in method getHtmlChoiceName.");
			
		$row = self::$dbCmdCache->GetRow();
		
		$colorCode = $row[self::$DB_COLOR_CODE];
		$choiceName = WebUtil::htmlOutput($row[self::$DB_SHIP_CHOICE_NAME]);
		
		// Black Color codes are not bold.
		if($colorCode == "#000000")
			$returnValue = "<font color='#000000'>$choiceName</font>";
		else 
			$returnValue = "<font color='$colorCode'><b>$choiceName</b></font>";
		
		// Cache the result.
		self::$shippingChoiceHTMLcache[$shippingChoiceID] = $returnValue;
		
		return $returnValue;
	}
	
	// This does not put HTML special characters around the Choice Name like "getHTMLChoiceName"
	static function getChoiceName($shippingChoiceID){
		
		if(isset(self::$shippingChoiceNamesCache[$shippingChoiceID]))
			return self::$shippingChoiceNamesCache[$shippingChoiceID];

		if(empty(self::$dbCmdCache))
			self::$dbCmdCache = new DbCmd();
			
		self::$dbCmdCache->Query("SELECT " . self::$DB_SHIP_CHOICE_NAME . " FROM shippingchoices WHERE ID=" . intval($shippingChoiceID));
		$choiceName = self::$dbCmdCache->GetValue();
		
		if(empty($choiceName))
			throw new Exception("Error in method getChoiceName.");

		self::$shippingChoiceNamesCache[$shippingChoiceID] = $choiceName;
		
		return $choiceName;
	}
	
	
	// A static method to get the Priority code of the Shippent ID.
	// Caches results for quick access in case of a list.
	// Does not validate ShippingChoice against the domain.
	static function getPriofityOfShippignChoiceID($shippingChoiceID){
		
		if(isset(self::$shippingPrioritiesCache[$shippingChoiceID]))
			return self::$shippingPrioritiesCache[$shippingChoiceID];

		if(empty(self::$dbCmdCache))
			self::$dbCmdCache = new DbCmd();
			
		self::$dbCmdCache->Query("SELECT " . self::$DB_PRIORITY . " FROM shippingchoices WHERE ID=" . intval($shippingChoiceID));
		$priorityValue = self::$dbCmdCache->GetValue();
		
		if(empty($priorityValue))
			throw new Exception("Error in method getPriofityOfShippignChoiceID.");

		self::$shippingPrioritiesCache[$shippingChoiceID] = $priorityValue;
		
		return $priorityValue;
	}
	
	
	// A static method to get the Priority code of the Shippent ID.
	// Caches results for quick access in case of a list.
	// Does not validate ShippingChoice against the domain.
	// Returns NULL if the shipping choice is inactive.
	static function getStaticDefaultShippingMethodCode($shippingChoiceID){
		
		if(isset(self::$shippingMetodCodeDefaultCache[$shippingChoiceID]))
			return self::$shippingMetodCodeDefaultCache[$shippingChoiceID];

		if(empty(self::$dbCmdCache))
			self::$dbCmdCache = new DbCmd();
			
		// We are not sure if the Domain Downgrades Shipping.
		// We need to find that out first before we look into the Shipping Method codes linking table.
		$domainObj = Domain::singleton();
		
		self::$dbCmdCache->Query("SELECT " . self::$DB_DOMAIN_ID . " FROM shippingchoices WHERE ID=" . intval($shippingChoiceID));
		$domainID = self::$dbCmdCache->GetValue();
		
		if(empty($domainID))
			throw new Exception("Error in method getStaticDefaultShippingMethodCode");

		if($domainObj->doesDomainDowngradeShipping($domainID)){
			self::$dbCmdCache->Query("SELECT " . self::$DB_SHIP_METHOD_CODE . " FROM shippingchoicesdowngrades WHERE " . self::$DB_IS_DEFAULT . " = 'Y' AND ShippingChoiceID=" . intval($shippingChoiceID));
			$defaultShippingMethodCode = self::$dbCmdCache->GetValue();
		}
		else{
			self::$dbCmdCache->Query("SELECT " . self::$DB_SHIP_METHOD_CODE . " FROM shippingchoices1to1map WHERE ShippingChoiceID=" . intval($shippingChoiceID));
			$defaultShippingMethodCode = self::$dbCmdCache->GetValue();
		}
		
		self::$shippingMetodCodeDefaultCache[$shippingChoiceID] = $defaultShippingMethodCode;
		
		return $defaultShippingMethodCode;
	}
	
	
	
	// Returns an array if Priority Shipping Choice codes which represent "Expedited Shipping"
	static function getExpeditedShippingPriorities(){
		
		return array(self::URGENT_PRIORITY, self::HIGH_PRIORITY, self::ELEVATED_PRIORITY);
	}
	
	
	// Returns a Hash of available Shipping Priorites.  The key to the array is the Code, and the value is the english description.
	function getPriorityList(){
		
		return $this->priorityChoicesArr;
	}
	
	function getPriorityDescription($priorityCode){

		if(!array_key_exists($priorityCode, $this->priorityChoicesArr))
			throw new Exception("Error in method getPriorityDescription");
			
		return $this->priorityChoicesArr[$priorityCode];
	}
	
	function isShippingDowngrade(){
		return $this->downgradeShippingFlag;
	}
	
	
	private function loadShippingChoicesFromDB(){
		
		$this->shippingChoicesHash = array();
		$this->quantityBreaksArr = array();
		$this->shippingMethodsDowngrades = array();
		$this->shippingMethods1to1Map = array();
		
		
		// Load stuff out of the main table for shipping choices.
		$this->_dbCmd->Query("SELECT * FROM shippingchoices WHERE DomainID=" . $this->domainID . " ORDER BY Sequence ASC");
		while($row = $this->_dbCmd->GetRow())
			$this->shippingChoicesHash[] = $row;
			
		$shippingChoiceIDsArr = array_keys($this->getAllShippingChoices());
		
		// Load quantity breaks.
		foreach($shippingChoiceIDsArr as $thisChoiceID){
			$this->_dbCmd->Query("SELECT * FROM shippingpricesquantitybreaks WHERE ShippingChoiceID=$thisChoiceID ORDER BY Weight ASC");
			while($row = $this->_dbCmd->GetRow())
				$this->quantityBreaksArr[] = $row;
		}

		// For Shipping Downgrades (for some domains)
		foreach($shippingChoiceIDsArr as $thisChoiceID){
			$this->_dbCmd->Query("SELECT * FROM shippingchoicesdowngrades WHERE ShippingChoiceID=$thisChoiceID ORDER BY Sequence ASC");
			while($row = $this->_dbCmd->GetRow()){

				// In case the shipping method codes have been changed since the database was saved.
				if(!$this->shippingMethodsObj->doesShippingCodeExist($row["ShippingMethodCode"])){
					$dbCmd2 = new DbCmd();
					$dbCmd2->Query("DELETE FROM shippingchoicesdowngrades WHERE ShippingChoiceID=$thisChoiceID AND ShippingMethodCode='".DbCmd::EscapeSQL($row["ShippingMethodCode"])."'");
					continue;
				}

				$this->shippingMethodsDowngrades[] = $row;
			}
		}
		
		// For Shipping 1 to 1 mapping (for some domains)
		foreach($shippingChoiceIDsArr as $thisChoiceID){
			$this->_dbCmd->Query("SELECT * FROM shippingchoices1to1map WHERE ShippingChoiceID=$thisChoiceID");
			while($row = $this->_dbCmd->GetRow()){
				
				// In case the shipping method codes have been changed since the database was saved.
				if(!$this->shippingMethodsObj->doesShippingCodeExist($row["ShippingMethodCode"]))
					continue;
					
				$this->shippingMethods1to1Map[] = $row;
			}
		}
	}
	
	// Fails if the choice ID doesn't exist.
	// Returns the key for private array $this->shippingChoicesHash correlating to the matching shipping ID.
	private function getIndexOfShippingChoiceID($shippingChoiceID, $functionName){
			
		$interalKeys = array_keys($this->shippingChoicesHash);
		
		foreach($interalKeys as $thisKey){
			if($this->shippingChoicesHash[$thisKey][self::$DB_ID] == $shippingChoiceID)
				return $thisKey;
		}
		
		throw new Exception("Error in method <u><functionName style='font-weight:bold;'>$functionName</functionName></u>. The shipping choice ID was not found: $shippingChoiceID");
	}
	
	
	// Returns the chepeast shipping method for the domain.
	function getLowestShippingChoicePriorityWithCheapestPrice(){

		$this->_dbCmd->Query("SELECT ID FROM shippingchoices WHERE DomainID=" . $this->domainID . " ORDER BY " . self::$DB_PRIORITY . " DESC, " . self::$DB_PRICE_INITIAL . " ASC");	
		while($thisShippingChoiceID = $this->_dbCmd->GetValue()){
			if($this->isShippingChoiceActive($thisShippingChoiceID))
				return $thisShippingChoiceID;
		}
		
		throw new Exception("There are no active Shipping Choice ID's for this domain.");
	}
	
	
	// Adds a new shipping choice to the DB, refreshes the current object, and returns the new Shipping Choice ID.
	function addNewShippingChoice($shippingChoiceName, $daysInTransit){
		
		$shippingChoiceName = trim($shippingChoiceName);
		$daysInTransit = intval($daysInTransit);
		
		if(empty($shippingChoiceName))
			throw new Exception("Error with method addNewShippingChoice. The choice name cannot be empty.");
		if(empty($daysInTransit))
			throw new Exception("Error with method addNewShippingChoice. The number of transit days cannot be empty.");
			
		if($this->checkIfShippingChoiceNameExists($shippingChoiceName))
			throw new Exception("Error with method addNewShippingChoice. The choice name is already in use.");
			
		$this->_dbCmd->Query("SELECT MAX(Sequence) FROM shippingchoices WHERE DomainID=" . $this->domainID);
		$lastSequence = $this->_dbCmd->GetValue() + 1;
		
		$insertArr[self::$DB_DOMAIN_ID] = $this->domainID;
		$insertArr[self::$DB_SHIP_CHOICE_NAME] = $shippingChoiceName;
		$insertArr[self::$DB_TRANSIT_DAYS] = $daysInTransit;
		$insertArr[self::$DB_SEQUENCE] = $lastSequence;
		$insertArr[self::$DB_BASIC_SHIPPING_CHOICE] = "N";
		$insertArr[self::$DB_DEFAULT_CHOICE] = "N";
		$insertArr[self::$DB_COLOR_CODE] = "#000000";
		$insertArr[self::$DB_PRIORITY] = self::NORMAL_PRIORITY;
		$insertArr[self::$DB_PRICE_INITIAL] = "0.00";
		$insertArr[self::$DB_PRICE_PER_POUND] = "0.00";
		$insertArr[self::$DB_RURAL_FEE] = "0.00";
		$insertArr[self::$DB_EXTENDED_DISTANCE_FEE] = "0.00";
			
		$newShippingChoiceID = $this->_dbCmd->InsertQuery("shippingchoices", $insertArr);
		
		$this->loadShippingChoicesFromDB();
		
		return $newShippingChoiceID;
	}
	
	// Returns FALSE if the choice Name can not be updated because the name matches another Choice ID
	// Returns TRUE if it was able to be updated.
	function setChoiceName($shippingChoiceID, $choiceName){
		
		$choiceName = trim($choiceName);
		if(empty($choiceName))
			throw new Exception("Error in method setChoiceName. The Choice Name can not be left blank.");
			
		$indexKey = $this->getIndexOfShippingChoiceID($shippingChoiceID, "setChoiceName");
			
		if($this->checkIfShippingChoiceNameExists($choiceName) && $shippingChoiceID != $this->getShippingChoiceID($choiceName))
			return false;

		$this->shippingChoicesHash[$indexKey][self::$DB_SHIP_CHOICE_NAME] = $choiceName;
		
		return true;
	}
	
	function setDaysInTransit($shippingChoiceID, $daysInTransit){
		
		$daysInTransit = intval($daysInTransit);
		if(empty($daysInTransit))
			throw new Exception("Error in method setDaysInTransit. Days in Tranist can not be zero.");

		$indexKey = $this->getIndexOfShippingChoiceID($shippingChoiceID, "setDaysInTransit");
		$this->shippingChoicesHash[$indexKey][self::$DB_TRANSIT_DAYS] = $daysInTransit;
	}
	
	function setAsBasicChoice($shippingChoiceID, $booleanFlag){
		
		if(!is_bool($booleanFlag))
			throw new Exception("Error with boolean flag in setAsBasicChoice");
		
		$indexKey = $this->getIndexOfShippingChoiceID($shippingChoiceID, "setAsBasicChoice");
		$this->shippingChoicesHash[$indexKey][self::$DB_BASIC_SHIPPING_CHOICE] = $booleanFlag ? "Y" : "N";
		
	}
	
	// This will set the given Shipping Choice ID to the default and unset any others that were a default previously.
	function setAsDefaultChoice($shippingChoiceID){
		
		$indexKeyToChange = $this->getIndexOfShippingChoiceID($shippingChoiceID, "setAsDefaultChoice");
		
		foreach(array_keys($this->shippingChoicesHash) as $thisKey)
			$this->shippingChoicesHash[$thisKey][self::$DB_DEFAULT_CHOICE] = "N";
			
		$this->shippingChoicesHash[$indexKeyToChange][self::$DB_DEFAULT_CHOICE] = "Y";
	
	}
	
	function setColorCode($shippingChoiceID, $colorCode){
		
		if(!preg_match("/^#([0-9a-f]){6}$/i", $colorCode))
			throw new Exception("Error in method setColorCode. The color code is not in the right format.");
		
		$indexKey = $this->getIndexOfShippingChoiceID($shippingChoiceID, "setColorCode");
		$this->shippingChoicesHash[$indexKey][self::$DB_COLOR_CODE] = strtolower($colorCode);
	}
	
	function getColorCode($shippingChoiceID){
		
		$indexKey = $this->getIndexOfShippingChoiceID($shippingChoiceID, "getColorCode");
		return $this->shippingChoicesHash[$indexKey][self::$DB_COLOR_CODE];
	}
	
	function setPriority($shippingChoiceID, $priorityCode){
		
		if(!in_array($priorityCode, array_keys($this->priorityChoicesArr)))
			throw new Exception("Error in method ShippingChoices->setPriority. The priority is invalid.");
		
		$indexKey = $this->getIndexOfShippingChoiceID($shippingChoiceID, "setPriority");
		$this->shippingChoicesHash[$indexKey][self::$DB_PRIORITY] = $priorityCode;
	}
	
	function setInitialPrice($shippingChoiceID, $intialPrice){
		
		if(!preg_match("/^(\d+|\d+\.\d+)$/", $intialPrice))
			throw new Exception("Error in method setInitialPrice. The price is in the wrong format.");
		
		$intialPrice = number_format($intialPrice, 2, ".", "");
		
		$indexKey = $this->getIndexOfShippingChoiceID($shippingChoiceID, "setInitialPrice");
		$this->shippingChoicesHash[$indexKey][self::$DB_PRICE_INITIAL] = $intialPrice;
	}
	
	function setPricePerPound($shippingChoiceID, $pricePerPound){
		
		if(!preg_match("/^(\d+|\d+\.\d+)$/", $pricePerPound))
			throw new Exception("Error in method setPricePerPound. The price is in the wrong format.");
			
		$pricePerPound = number_format($pricePerPound, 2, ".", "");
		
		$indexKey = $this->getIndexOfShippingChoiceID($shippingChoiceID, "setPricePerPound");
		$this->shippingChoicesHash[$indexKey][self::$DB_PRICE_PER_POUND] = $pricePerPound;
		
	}
	
	function setRuralFee($shippingChoiceID, $fee){
		
		if(!preg_match("/^(\d+|\d+\.\d+)$/", $fee))
			throw new Exception("Error in method setRuralFee. The price is in the wrong format.");
			
		$fee = number_format($fee, 2, ".", "");
		
		$indexKey = $this->getIndexOfShippingChoiceID($shippingChoiceID, "setRuralFee");
		$this->shippingChoicesHash[$indexKey][self::$DB_RURAL_FEE] = $fee;
	}
	
	function setExtendedDistanceFee($shippingChoiceID, $fee){
		
		if(!preg_match("/^(\d+|\d+\.\d+)$/", $fee))
			throw new Exception("Error in method setExtendedDistanceFee. The price is in the wrong format.");
			
		$fee = number_format($fee, 2, ".", "");
		
		$indexKey = $this->getIndexOfShippingChoiceID($shippingChoiceID, "setExtendedDistanceFee");
		$this->shippingChoicesHash[$indexKey][self::$DB_EXTENDED_DISTANCE_FEE] = $fee;
	}
	
	// After calling the "setter" methods, you have to call this to write to the DB.
	function updateDatabase(){
		
		foreach($this->shippingChoicesHash as $thisShippingChoiceRow){
			$shippingChoiceID = $thisShippingChoiceRow[self::$DB_ID];
			unset($thisShippingChoiceRow[self::$DB_ID]);
			
			$this->_dbCmd->UpdateQuery("shippingchoices", $thisShippingChoiceRow, "ID=$shippingChoiceID");
		}
		

		foreach($this->quantityBreaksArr as $row){
			// Find out if we are adding or updating
			if(!isset($row[self::$DB_ID])){
				$this->_dbCmd->InsertQuery("shippingpricesquantitybreaks", $row);
			}
			else{
				$dbID = $row[self::$DB_ID];
				unset($row[self::$DB_ID]);
				$this->_dbCmd->UpdateQuery("shippingpricesquantitybreaks", $row, "ID=$dbID");
			}
		}
		foreach($this->shippingMethodsDowngrades as $row){
			// Find out if we are adding or updating
			if(!isset($row[self::$DB_ID])){
				$this->_dbCmd->InsertQuery("shippingchoicesdowngrades", $row);
			}
			else{
				$dbID = $row[self::$DB_ID];
				unset($row[self::$DB_ID]);
				$this->_dbCmd->UpdateQuery("shippingchoicesdowngrades", $row, "ID=$dbID");
			}
		}	
		foreach($this->shippingMethods1to1Map as $row){
			// Find out if we are adding or updating
			if(!isset($row[self::$DB_ID])){
				$this->_dbCmd->InsertQuery("shippingchoices1to1map", $row);
			}
			else{
				$dbID = $row[self::$DB_ID];
				unset($row[self::$DB_ID]);
				$this->_dbCmd->UpdateQuery("shippingchoices1to1map", $row, "ID=$dbID");
			}
		}
		
		// Reload all of the information that we just updated.
		// This is really only necessary because we may have Added new Shipping Links and need to refresh our Objects within "ID's" from the inserts in case we subsequently call this "update" method again within the same script execution.
		$this->loadShippingChoicesFromDB();
		
	}
	
	
	function setPricePerPoundQuantityBreak($shippingChoiceID, $weight, $pricePerPound){

		if(!$this->checkIfShippingChoiceIDExists($shippingChoiceID))
			throw new Exception("Error in method ShippingChoices->addPricePerPoundQuantityBreak");
			
		if(!preg_match("/^(\d+|\d+\.\d+)$/", $pricePerPound))
			throw new Exception("Error in method addPricePerPoundQuantityBreak. The price is in the wrong format.");
			
		if($weight == 0 || $weight == 1)
			throw new Exception("Error in method setPricePerPoundQuantityBreak. The weight value must be greater than or equal to 2.");
			
		// Prevent Duplicates. Either Add or Update
		$alreadyUpdated = false;
		foreach(array_keys($this->quantityBreaksArr) as $thisKey){
			if($this->quantityBreaksArr[$thisKey][self::$DB_WEIGHT] == $weight && $this->quantityBreaksArr[$thisKey][self::$DB_SHIP_CHOICE_ID] == $shippingChoiceID){
				
					$this->quantityBreaksArr[$thisKey][self::$DB_PRICE_PER_POUND] = $pricePerPound;
					$alreadyUpdated = true;
			}
		}
	
		// If we couldn't update, then insert a new weight quantity break.
		if(!$alreadyUpdated)
			$this->quantityBreaksArr[] = array(self::$DB_WEIGHT=>intval($weight), self::$DB_PRICE_PER_POUND=>$pricePerPound, self::$DB_SHIP_CHOICE_ID=>$shippingChoiceID);
	}
	
	function removePricePerPoundQuantityBreak($shippingChoiceID, $weight){
   
		if(!$this->checkIfShippingChoiceIDExists($shippingChoiceID))
			throw new Exception("Error in method ShippingChoices->addPricePerPoundQuantityBreak");
		
		$this->_dbCmd->Query("DELETE FROM shippingpricesquantitybreaks WHERE ShippingChoiceID=$shippingChoiceID AND Weight=" . intval($weight));
		$this->loadShippingChoicesFromDB();

	}
	

	
	// Returns an array of weight values that exist for Quantity breaks on the Shipping Choice ID
	// Returns an empty array if there are no quantity breaks.
	function getWeightValuesForQuantityBreaks($shippingChoiceID){
		
		if(!$this->checkIfShippingChoiceIDExists($shippingChoiceID))
			throw new Exception("Error in method ShippingChoices->getWeightValuesForQuantityBreaks");
		
		$retArr = array();
	
		foreach($this->quantityBreaksArr as $row){
			if($row[self::$DB_SHIP_CHOICE_ID] == $shippingChoiceID)
				$retArr[] = $row[self::$DB_WEIGHT];
		}
		
		return $retArr;
	}
	
	
	// the Base Price may be set to $5.95 and the price per pound is s $1.00, let's say.  So a box of 3 pounds will cost the customer $8.95
	// If we are giving away greeting cards for free, then we may want to charge the customer a lot more for the initial price.  This let's us override the default initial price of a shipping choice.
	function setBasePriceOverrideForProduct($shippingChoiceID, $productID, $intialPrice){
		
		if(!Product::checkIfProductIDexists($this->_dbCmd, $productID))
			throw new Exception("Error in method addBasePriceOverrideForProduct. Product ID does not exist.");
			
		if(!$this->checkIfShippingChoiceIDExists($shippingChoiceID))
			throw new Exception("Error in method addBasePriceOverrideForProduct. The shipping Choice doesn't exist.");
			
		if(!preg_match("/^(\d+|\d+\.\d+)$/", $intialPrice))
			throw new Exception("Error in method addBasePriceOverrideForProduct. The price is in the wrong format.");
			
		$intialPrice = number_format($intialPrice, 2, ".", "");
		
		// It doesn't matter if we are updating or adding.
		$this->_dbCmd->Query("DELETE FROM shippingpriceproductoverride WHERE ProductID=$productID AND ShippingChoiceID=$shippingChoiceID");
		$this->_dbCmd->InsertQuery("shippingpriceproductoverride", array(self::$DB_PRODUCT_ID=>$productID, self::$DB_SHIP_CHOICE_ID=>$shippingChoiceID, self::$DB_PRICE_INITIAL=>$intialPrice));
	}
	
	function removeBasePriceOverrideForProduct($shippingChoiceID, $productID){
		
		if(!Product::checkIfProductIDexists($this->_dbCmd, $productID))
			throw new Exception("Error in method removeBasePriceOverrideForProduct. Product ID does not exist.");
			
		if(!$this->checkIfShippingChoiceIDExists($shippingChoiceID))
			throw new Exception("Error in method removeBasePriceOverrideForProduct. The shipping Choice doesn't exist.");

		$this->_dbCmd->Query("DELETE FROM shippingpriceproductoverride WHERE ProductID=$productID AND ShippingChoiceID=$shippingChoiceID");
		$this->loadShippingChoicesFromDB();
	}
	
	// Products have the ability to override the default Base Price on any shipping method.
	// Pass in a Product ID if you want to search for an override value.
	// If the Product ID does not have a Price Override, then it will return the general Base Price for the Shipping Choice.
	function getBasePrice($shippingChoiceID, $productID=NULL){

		$indexKey = $this->getIndexOfShippingChoiceID($shippingChoiceID, "getBasePrice");
		$initialPrice = $this->shippingChoicesHash[$indexKey][self::$DB_PRICE_INITIAL];
			
		if(!empty($productID)){
			
			if(!Product::checkIfProductIDexists($this->_dbCmd, $productID))
				throw new Exception("Error in method ShippingChoice->getBasePrice. Product ID does not exist.");
			
			$this->_dbCmd->Query("SELECT PriceInitial FROM shippingpriceproductoverride WHERE ShippingChoiceID=$shippingChoiceID AND ProductID=" . intval($productID));
			if($this->_dbCmd->GetNumRows() != 0)
				return $this->_dbCmd->GetValue();
		}
		
		return $initialPrice;
	}
	
	// Returns an array of Product ID's which have overridden the Base Price
	function getProductIDsThatOverrideBasePrice($shippingChoiceID){

		if(!$this->checkIfShippingChoiceIDExists($shippingChoiceID))
			throw new Exception("Error in method ShippingChoices->getProductIDsThatOverrideBasePrice");
		
		$this->_dbCmd->Query("SELECT ProductID FROM shippingpriceproductoverride WHERE ShippingChoiceID=$shippingChoiceID");
		return $this->_dbCmd->GetValueArr();

	}
	
	
	// Will get the Price per pound for the given shipping choice.
	// If the weight parameter is supplied, then it will look for a new Price-per-Pound based upon the weight
	// For example, as the weight increased you may want to give customers a little break... at 200 pounds maybe you shouldn't charge the customer $1 per pound
	// It stops on the lowest possible match for quantity.  Returns the Base Price per pound if the weight is less (or null) than the any entry in the quantity break table.
	function getPricePerPound($shippingChoiceID, $weight = 0){
		
		$weight = intval($weight);
		
		$indexKey = $this->getIndexOfShippingChoiceID($shippingChoiceID, "getPricePerPound");
		
		$returnPrice = $this->shippingChoicesHash[$indexKey][self::$DB_PRICE_PER_POUND];


		if($weight){
			foreach($this->quantityBreaksArr as $row){
				if($row[self::$DB_WEIGHT] <= $weight && $row[self::$DB_SHIP_CHOICE_ID] == $shippingChoiceID){
					$returnPrice = $row[self::$DB_PRICE_PER_POUND];
				}
			}
		}
		
		return number_format($returnPrice, 2, ".", "");
	}
	
	function getRuralFee($shippingChoiceID){
		
		$indexKey = $this->getIndexOfShippingChoiceID($shippingChoiceID, "getRuralFee");
		
		return $this->shippingChoicesHash[$indexKey][self::$DB_RURAL_FEE];
	}
	function getExtendedDistanceFee($shippingChoiceID){
		
		$indexKey = $this->getIndexOfShippingChoiceID($shippingChoiceID, "getExtendedDistanceFee");
		
		return $this->shippingChoicesHash[$indexKey][self::$DB_EXTENDED_DISTANCE_FEE];
	}
	
	// Case insenstive way to check if the Shipping Choice Name is already taken.
	function checkIfShippingChoiceNameExists($shippingChoiceName){
		
		$shippingChoiceName = strtolower(trim($shippingChoiceName));
		
		foreach($this->shippingChoicesHash as $shippingChoiceRow){
			
			if(strtolower($shippingChoiceRow[self::$DB_SHIP_CHOICE_NAME]) == $shippingChoiceName)
				return true;
		}
		return false;
	}
	
	function checkIfShippingChoiceIDExists($shippingChoiceID){
		
		foreach($this->shippingChoicesHash as $shippingChoiceRow){
			
			if($shippingChoiceRow[self::$DB_ID] == $shippingChoiceID)
				return true;
		}
		return false;
	}
	
	// Returns the name of the shipping choice matching our internal ID
	// Gives an error if the ShippingID does not exist for the domain.
	function getShippingChoiceName($shippingChoiceID){
		
		$indexKey = $this->getIndexOfShippingChoiceID($shippingChoiceID, "getShippingChoiceName");
		
		return $this->shippingChoicesHash[$indexKey][self::$DB_SHIP_CHOICE_NAME];
	}
	
	// Works opposite to getShippingChoiceName
	// This is case sensitive. Gives an error if the Shipping Choice Name doesn't exist for the domain.
	function getShippingChoiceID($shippingChoiceName){
		
		foreach($this->shippingChoicesHash as $shippingChoiceRow){
			
			if($shippingChoiceRow[self::$DB_SHIP_CHOICE_NAME] == $shippingChoiceName)
				return $shippingChoiceRow[self::$DB_ID];
		}
		
		throw new Exception("Error in method getShippingChoiceID. Choice Name was not found.");
	}
	
	function getPriorityOfShippingChoice($shippingChoiceID){
		
		$indexKey = $this->getIndexOfShippingChoiceID($shippingChoiceID, "getPriorityOfShippingChoice");
		return $this->shippingChoicesHash[$indexKey][self::$DB_PRIORITY];
	}
	
	// Returns an array of ShippingChoiceIDs matching the Priority Type
	function getShippingChoiceIDsByPriority($priorityType, $filterInActiveChoices = true){
		
		if(!in_array($priorityType, array_keys($this->priorityChoicesArr)))
			throw new Exception("Error in method getShippingChoiceIDsByPriority");
		
		$retArr = array();
		
		foreach($this->shippingChoicesHash as $shippingChoiceRow){
			
			if($shippingChoiceRow[self::$DB_PRIORITY] == $priorityType){
				
				if($filterInActiveChoices && !$this->isShippingChoiceActive($shippingChoiceRow[self::$DB_ID]))
					continue;
					
				$retArr[] = $shippingChoiceRow[self::$DB_ID];
			}
		}
		
		return $retArr;
	}
	

	
	// Returns an integer repesenting the number of days in transit that was specified for this ShippingChoice.
	function getTransitDays($shippingChoiceID){
		
		$indexKey = $this->getIndexOfShippingChoiceID($shippingChoiceID, "getTransitDays");
		return $this->shippingChoicesHash[$indexKey][self::$DB_TRANSIT_DAYS];

	}
	
	// Returns True or False depending on whether the shipping choice is considered a basic one.
	// This is usefull to give customers hypothetical shipping times before we know there Delivery address.
	// For example, you might not want to include a specialty choice like "UPS Saturday Delivery Early" if we don't know if UPS can offer that method.
	// ... but almost all addresses have a 1 day & ground shipping option.
	function isShippingChoiceBasic($shippingChoiceID){
		
		if(in_array($shippingChoiceID, $this->getBasicShippingChoices(true)))
			return true;
		else
			return false;
	}
	
	// Returns an Array of Basic Shipping Choice IDs that are not disabled.
	function getBasicShippingChoices($includeInactiveMethods = false){
		
		$retArr = array();
		
		foreach($this->shippingChoicesHash as $shippingChoiceRow){
			
			if(!$includeInactiveMethods && !$this->isShippingChoiceActive($shippingChoiceRow[self::$DB_ID]))
				$includeChoice = false;
			else
				$includeChoice = true;
			
			if($shippingChoiceRow[self::$DB_BASIC_SHIPPING_CHOICE] == "Y" && $includeChoice)
				$retArr[] = $shippingChoiceRow[self::$DB_ID];
		}
		
		return $retArr;
	}
	
	// Returns TRUE or FALSE whether this should be the default choice on the checkout screen.
	function isDefaultShippingChoice($shippingChoiceID){
		
		$indexKey = $this->getIndexOfShippingChoiceID($shippingChoiceID, "isDefaultShippingChoice");
		
		if($this->shippingChoicesHash[$indexKey][self::$DB_DEFAULT_CHOICE] == "Y")
			return true;
		else
			return false;
	}
	
	// Returns NULL if there are no shipping choices yet.
	function getDefaultShippingChoiceID(){
		
		foreach($this->shippingChoicesHash as $row){
			if($row[self::$DB_DEFAULT_CHOICE] == "Y")
				return $row[self::$DB_ID];
		}
		return NULL;
	}
	
	// Returns an array of all Shipping Choices, regardles if they are disabled or not.
	// The key is the ShippingChoiceID and the value is the Shipping Choice name.
	function getAllShippingChoices(){
		
		$retArr = array();
		
		foreach($this->shippingChoicesHash as $shippingChoiceRow)
			$retArr[$shippingChoiceRow[self::$DB_ID]] = $shippingChoiceRow[self::$DB_SHIP_CHOICE_NAME];
		
		return $retArr;
	}
	
	// The key is the ShippingChoiceID and the value is the Shipping Choice name.
	function getActiveShippingChoices(){
		
		$retArr = array();
		
		$allChoices = $this->getAllShippingChoices();
		
		foreach($allChoices as $choiceID => $choiceName){
			if($this->isShippingChoiceActive($choiceID))
				$retArr[$choiceID] = $choiceName;
		}
		
		return $retArr;
	}
	
	// Returns a list of Shipping Choices that are both active and available to the Destination address.
	// The key is the ShippingChoiceID and the value is the Shipping Choice name.
	// If the Domain Type is One-to-One shipping method mapping... then it will figure out if the Shipping Choice is avaialable based on whether the ShippingMethod is available.
	// For Shipping downgrades: If the Default shipping method is not available to the detination, then none of the methods will be available (even if others methods can make it to the destination).
	// This will NOT filter out Saturday Delivery (even if the order planst to ship on Tuesday.  Manually filter out Saturday delivery choices if you need to.
	function getAvailableShippingChoices(MailingAddress $shipFromAddressObj, MailingAddress $shipToAddressObj){
		
		$retArr = array();
		
		$activeShippingChoices = $this->getActiveShippingChoices();
		
		try{
			$shippingMethodsPossibleArr = $this->shippingMethodsObj->getAllShippingMethodsPossible($shipFromAddressObj, $shipToAddressObj);
		}
		catch (ExceptionInvalidShippingAddress $e){
			
			WebUtil::WebmasterError("An Error occured in the method getAvailableShippingChoices. The Address does not appear to be valid. \n\n" . $shipToAddressObj->toString() . "\n\n" . $e->getMessage());
			$shippingMethodsPossibleArr = array();
		}
		
		foreach ($activeShippingChoices as $choiceID => $choiceName){
			
			$linkedShippignMethodCodesArr = $this->getLinkedShippingMethodsCodes($choiceID);
			
			$availableMethodFound = false;
			
			foreach($linkedShippignMethodCodesArr as $thisShippingCode){
				
				if(in_array($thisShippingCode, $shippingMethodsPossibleArr)){
					$availableMethodFound = true;
					break;
				}
			}
			
			// For shipping downgrades, make sure that the default shipping method is avaialble.
			// It doesn't matter if one of the other Linked Shipping Methods is available. The Default linked method must be available.
			if($this->downgradeShippingFlag && $availableMethodFound){
			
				$defaultShippingMethodCode = $this->getDefaultShippingMethodCode($choiceID);
				if(!in_array($defaultShippingMethodCode, $shippingMethodsPossibleArr))
					$availableMethodFound = false;	
			}
			
			if($availableMethodFound)
				$retArr[$choiceID] = $choiceName;
		}
	
		return $retArr;
	}
	
	
	// Will try to use the Default Shipping Choice stored in the DB.
	// If for some reason the default choice does have have a Shipping Method available to the destination then we default to the first choice in the list.
	// If there are no Choices available to the destination then this returns NULL.
	function getDefaultShippingChoiceIDtoDestination(MailingAddress $shipFromAddressObj, MailingAddress $shipToAddressObj){
		
		$allShippingChoicesIDsAvailable = array_keys($this->getAvailableShippingChoices($shipFromAddressObj, $shipToAddressObj));
		
		if(empty($allShippingChoicesIDsAvailable))
			return NULL;
		
		if(in_array($this->getDefaultShippingChoiceID(), $allShippingChoicesIDsAvailable))
			return $this->getDefaultShippingChoiceID();
			
		reset($allShippingChoicesIDsAvailable);
		return current($allShippingChoicesIDsAvailable);
	}
	
	
	// Pass in a Shipping Choice ID and this method will return an array Shipping Methods Codes that are linked to it.
	// It is possible for Shipping Downgrades to have many duplicate Shipping Codes linked to the same choice.  This will return an unique list of Shipping Codes
	// Returns an empty array if there are no links.   An empty array means that the shipping choice is disabled.
	// If the domain is configured for Shipping Downgrades then there may be many Shipping Methods returned
	// For one-to-one shipping linking there will be a maximum of one element in the array
	function getLinkedShippingMethodsCodes($shippingChoiceID){
		
		$shippingChoiceID = intval($shippingChoiceID);
		
		$retArr = array();
		
		if($this->downgradeShippingFlag){	
			foreach($this->shippingMethodsDowngrades as $row){
				if($row[self::$DB_SHIP_CHOICE_ID] == $shippingChoiceID)
					$retArr[] = $row[self::$DB_SHIP_METHOD_CODE];
			}
		}
		else{
			foreach($this->shippingMethods1to1Map as $row){
				if($row[self::$DB_SHIP_CHOICE_ID] == $shippingChoiceID)
					$retArr[] = $row[self::$DB_SHIP_METHOD_CODE];
			}
		}
	
		return $retArr;
	}
	
	// Returns an array of IDs that link Shipping Methods to the Shipping Choice
	// The order in which this Shipping Method LinkIDs are retrurned is significant.  The top Choices are ones we prefer for Shipping Downgrade
	// If this is 1-to-1 shipping ... then there will only be (at most) 1 link returned
	// For Shipping Downgrades there may be Zero or many.
	// If now IDs are returned, then it means that the Shipping Choice is not active.
	function getShippingMethodLinkIDs($shippingChoiceID){
		
		$shippingChoiceID = intval($shippingChoiceID);
		
		$retArr = array();
		
		if($this->downgradeShippingFlag){	
			foreach($this->shippingMethodsDowngrades as $row){
				if($row[self::$DB_SHIP_CHOICE_ID] == $shippingChoiceID)
					$retArr[] = $row[self::$DB_ID];
			}
		}
		else{
			foreach($this->shippingMethods1to1Map as $row){
				if($row[self::$DB_SHIP_CHOICE_ID] == $shippingChoiceID)
					$retArr[] = $row[self::$DB_ID];
			}
		}
	
		return $retArr;
	}
	
	// Returns the Shipping Method Code based on the ID in the database.
	// The method will figure out if it is for Shipping Downgrades or 1-to-1 shipping linking automatically.
	// There will be an error if the ShippingMethodLinkID doesn't exist.
	function getShippingMethodCodeFromLinkID($shippingMethodLinkID){
		
		if($this->downgradeShippingFlag){	
			foreach($this->shippingMethodsDowngrades as $row){
				if($row[self::$DB_ID] == $shippingMethodLinkID)
					return $row[self::$DB_SHIP_METHOD_CODE];
			}
		}
		else{
			foreach($this->shippingMethods1to1Map as $row){
				if($row[self::$DB_ID] == $shippingMethodLinkID)
					return $row[self::$DB_SHIP_METHOD_CODE];
			}
		}
		
		throw new Exception("Error in method getShippingMethodCodeFromLinkID. The link ID doesn't exist.");
	}
	
	// Mainly useful for 1-to-1 shipping method linking (not shipping downgrades).
	// ... the reason is that Shipping Downgrades may have Multiple Shipping Methods linked to each choice.
	// Will return all Shipping Method codes that have been linked to a shipping choice for the domain.
	function getUniqueListOfAllShippingMethodsCodesAlreadyLinked(){
		
		$retArr = array();
		
		if($this->downgradeShippingFlag){	
			foreach($this->shippingMethodsDowngrades as $row)
				$retArr[] = $row[self::$DB_SHIP_METHOD_CODE];
		}
		else{
			foreach($this->shippingMethods1to1Map as $row)
				$retArr[] = $row[self::$DB_SHIP_METHOD_CODE];
		}
	
		return array_unique($retArr);
		
	}
	
	
	// Returns true or false depending on whether there is a shipping method linked to the Choice
	function isShippingChoiceActive($shippingChoiceID){
		
		$linkedMethods = $this->getLinkedShippingMethodsCodes($shippingChoiceID);
		
		if(empty($linkedMethods))
			return false;
		else
			return true;
	}
	

	
	function linkShippingMethodToShippingChoice($shippingChoiceID, $shippingMethodCode){
		
		if(!in_array($shippingMethodCode, array_keys($this->shippingMethodsObj->getShippingMethodsHash())))
			throw new Exception("Error in method linkShippingMethodToShippingChoice. The Shipping Method code is not valid.");
		
		if(!$this->checkIfShippingChoiceIDExists($shippingChoiceID))
			throw new Exception("Error in method linkShippingMethodToShippingChoice. Choice ID does not exist.");
		
		if($this->downgradeShippingFlag){
			
			// Figure out the largest Sequence value so we can append our new link to the end.
			$maxSequence = 0;
			// Also figure out if there is a duplicate Shipping Method Code already linked.  If so, we want to copy that same Alert Message.
			$existingAlertMessage = "";
			foreach(array_keys($this->shippingMethodsDowngrades) as $thisKey){
				if($this->shippingMethodsDowngrades[$thisKey][self::$DB_SEQUENCE] > $maxSequence)
					$maxSequence = $this->shippingMethodsDowngrades[$thisKey][self::$DB_SEQUENCE];
					
				if($this->shippingMethodsDowngrades[$thisKey][self::$DB_SHIP_METHOD_CODE] == $shippingMethodCode && $this->shippingMethodsDowngrades[$thisKey][self::$DB_SHIP_CHOICE_ID] == $shippingChoiceID)
					$existingAlertMessage = $this->shippingMethodsDowngrades[$thisKey][self::$DB_ALERT_MESSAGE];
			}

			$this->shippingMethodsDowngrades[] = array(self::$DB_SHIP_CHOICE_ID=>$shippingChoiceID, self::$DB_SHIP_METHOD_CODE=>$shippingMethodCode, self::$DB_ADDRESS_TYPE => self::ADDRESS_TYPE_NONE,
														self::$DB_ALERT_MESSAGE=>$existingAlertMessage, self::$DB_MIN_WEIGHT=>"0", self::$DB_MAX_WEIGHT=>"0", self::$DB_IS_DEFAULT=>"N", self::$DB_SEQUENCE=>($maxSequence+1));

			// In case we are adding or first shipment link, we will need to have a default.
			$this->ensureShippingDowngradeLinkHasADefault($shippingChoiceID);
		}
		else{
			
			$tempArr = array();
			
			foreach(array_keys($this->shippingMethods1to1Map) as $thisKey){
				
				// Remove duplicates
				if($this->shippingMethods1to1Map[$thisKey][self::$DB_SHIP_CHOICE_ID] != $shippingChoiceID)
					$tempArr[] = $this->shippingMethods1to1Map[$thisKey];
				else
					$this->_dbCmd->Query("DELETE FROM shippingchoices1to1map WHERE " . self::$DB_SHIP_CHOICE_ID . "=$shippingChoiceID");
					
				if($this->shippingMethods1to1Map[$thisKey][self::$DB_SHIP_CHOICE_ID] != $shippingChoiceID && $this->shippingMethods1to1Map[$thisKey][self::$DB_SHIP_METHOD_CODE] == $shippingMethodCode)
					throw new Exception("Error in method linkShippingMethodToShippingChoice. The shipping method code is already linked to another choice.");
			}
	
			
			// Add the Shipping Method Code that was passed into this method
			$tempArr[] = array(self::$DB_SHIP_METHOD_CODE=>$shippingMethodCode, self::$DB_SHIP_CHOICE_ID=>$shippingChoiceID);
			
			$this->shippingMethods1to1Map = $tempArr;
		}
	}

	function removeShippingMethodLink($shippingMethodLinkID){
		
		if(!$this->checkIfShippingMethodLinkIDexists($shippingMethodLinkID))
			throw new Exception("Error in method removeShippingMethodLink. The Link ID does not exist.");
		
		$shippingChoiceID = $this->getShippingChoiceIDfromShippingMethodLinkID($shippingMethodLinkID);
			
		if($this->downgradeShippingFlag){
			
			$this->_dbCmd->Query("DELETE FROM shippingchoicesdowngrades WHERE ID=" . intval($shippingMethodLinkID));
			
			$this->loadShippingChoicesFromDB();
			
			// In case the default shipping method was removed, pick another one.
			$this->ensureShippingDowngradeLinkHasADefault($shippingChoiceID);
		}
		else{
			$this->_dbCmd->Query("DELETE FROM shippingchoices1to1map WHERE ID=" . intval($shippingMethodLinkID));
			
			$this->loadShippingChoicesFromDB();
		}
	}
	
	// Sets the Shipping Method to be the default Method.  
	// All of the other Shipping Method links for the Shipping Choice will not be the default.
	function setShippingDowngradeLinkDefault($shippingMethodLinkID){
		
		if(!$this->checkIfShippingMethodLinkIDexists($shippingMethodLinkID))
			throw new Exception("Error in method setShippingDowngradeLinkDefault. The Link ID does not exist.");
		
		if(!$this->downgradeShippingFlag)
			throw new Exception("Error in setShippingDowngradeLinkDefault. Wrong domain type.");
			
		$foundIndexKey = null;
		foreach(array_keys($this->shippingMethodsDowngrades) as $indexKey){
			$row = $this->shippingMethodsDowngrades[$indexKey];
			
			if($row[self::$DB_ID] == $shippingMethodLinkID)
				$foundIndexKey = $indexKey;
		}
		if($foundIndexKey === null)
			throw new Exception("Error in method setShippingDowngradeLinkDefault. The shipping method is not found.");
		
		$shippingChoiceID = $this->getShippingChoiceIDfromShippingMethodLinkID($shippingMethodLinkID);
			
		foreach (array_keys($this->shippingMethodsDowngrades) as $rowIndex){
			
			// Don't overwrite Default values of other Shipping Choice IDs
			if($this->shippingMethodsDowngrades[$rowIndex][self::$DB_SHIP_CHOICE_ID] != $shippingChoiceID)
				continue;
			
			if($rowIndex == $foundIndexKey){
				$this->shippingMethodsDowngrades[$rowIndex][self::$DB_IS_DEFAULT] = "Y";
				
				// Default Shipping Method links can't have a min max weight.  They must always be available.
				$this->shippingMethodsDowngrades[$rowIndex][self::$DB_MIN_WEIGHT] = 0;
				$this->shippingMethodsDowngrades[$rowIndex][self::$DB_MAX_WEIGHT] = 0;
				
				// The Default Shipping Method Link Can not have an Address Type Restriction
				$this->shippingMethodsDowngrades[$rowIndex][self::$DB_ADDRESS_TYPE] = self::ADDRESS_TYPE_NONE;
			}
			else{ 
				$this->shippingMethodsDowngrades[$rowIndex][self::$DB_IS_DEFAULT] = "N";
			}
		
		}
	}
	
	// Returns the default shipping Downgrade link for the Shipping Choice
	// If there are no Shipping Method links yet then this method will return NULL.
	// If there is 1 or Shipping Methods linked, there will always be a default.
	// For 1-to-1 shipping method linking, this will return the only Shipping Method Code. Returns NULL if the Shipping Choice is not active.
	function getDefaultShippingMethodCode($shippingChoiceID){
		
		if(!$this->checkIfShippingChoiceIDExists($shippingChoiceID))
			throw new Exception("Error in method getDefaultShippingMethodCode. Choice ID does not exist. $shippingChoiceID");
		
		
		if($this->downgradeShippingFlag){

			foreach($this->shippingMethodsDowngrades as $row){
				if($row[self::$DB_SHIP_CHOICE_ID] == $shippingChoiceID && $row[self::$DB_IS_DEFAULT] == "Y")
					return $row[self::$DB_SHIP_METHOD_CODE];
			}
		}
		else{
			foreach($this->shippingMethods1to1Map as $row){
				if($row[self::$DB_SHIP_CHOICE_ID] == $shippingChoiceID)
					return $row[self::$DB_SHIP_METHOD_CODE];
			}
		}
		
		return null;
	}
	
	
	// Returns Null if the Shipping Choice doees not have any Shipping Method Links created to it.
	function getDefaultShippingMethodLinkID($shippingChoiceID){
		
		if(!$this->checkIfShippingChoiceIDExists($shippingChoiceID))
			throw new Exception("Error in method getDefaultShippingMethodCode. Choice ID does not exist. $shippingChoiceID");
		
		
		if($this->downgradeShippingFlag){

			foreach($this->shippingMethodsDowngrades as $row){
				if($row[self::$DB_SHIP_CHOICE_ID] == $shippingChoiceID && $row[self::$DB_IS_DEFAULT] == "Y")
					return $row[self::$DB_ID];
			}
		}
		else{
			foreach($this->shippingMethods1to1Map as $row){
				if($row[self::$DB_SHIP_CHOICE_ID] == $shippingChoiceID)
					return $row[self::$DB_ID];
			}
		}
		
		return null;
	}
	
	
	
	// Saturday delivery is directly linked to whether the default shipping method offers Saturday Shipping
	// This method will fail if the shipping choice is not active.
	function checkIfShippingChoiceHasSaturdayDelivery($shippingChoiceID){
		
		if(!$this->checkIfShippingChoiceIDExists($shippingChoiceID))
			throw new Exception("Error in method checkIfShippingChoiceHasSaturdayDelivery. ChoiceID does not exist.");
	
		$defaultShippingMethodCode = $this->getDefaultShippingMethodCode($shippingChoiceID);
		if(!$defaultShippingMethodCode)
			throw new Exception("Error in method checkIfShippingChoiceHasSaturdayDelivery. Shipping Choice not found.");
			
		return $this->shippingMethodsObj->isSaturdayDelivery($defaultShippingMethodCode);
	}
	
	// Makes sure that there is always a Default Shipping Method linked to the choice
	// This should be called everytime we add a new Shipping Method link or remove one.
	// If the Default shipping method it changed, it will make sure that no Min or Max weight values exist on it.
	private function ensureShippingDowngradeLinkHasADefault($shippingChoiceID){
		
		if(!$this->checkIfShippingChoiceIDExists($shippingChoiceID))
			throw new Exception("Error in method ensureShippingDowngradeLinkHasADefault. Choice ID does not exist.");
		
		if(!$this->downgradeShippingFlag)
			throw new Exception("Error in ensureShippingDowngradeLinkHasADefault. Wrong domain type.");
			
		// If we already have a default, don't bother.
		if($this->getDefaultShippingMethodCode($shippingChoiceID))
			return;
			
		// Otherwise set the first default Shipping Method in this Shipping Choice that we come across.
		foreach(array_keys($this->shippingMethodsDowngrades) as $rowKey){
			if($this->shippingMethodsDowngrades[$rowKey][self::$DB_SHIP_CHOICE_ID] == $shippingChoiceID){
				$this->shippingMethodsDowngrades[$rowKey][self::$DB_IS_DEFAULT] = "Y";
				$this->shippingMethodsDowngrades[$rowKey][self::$DB_MIN_WEIGHT] = 0;
				$this->shippingMethodsDowngrades[$rowKey][self::$DB_MAX_WEIGHT] = 0;
				break;
			}
		}
	}
	
	
	

	
	// This alert will get printed on little Barcode labels before a package is ready to be boxed.
	// It will get show up when the given shipping Choice is selected and at the same time the ShippingMethod Code is matched.
	// For example, during downgrading shipping If the user chose Ground Shipping but the system picked Postal Service 1st class, we would want the operator to know to take that to a different shipping station.
	// We may also want a different message to be displayed in case the User chose 1 day shipping and the system picked USPS verses when the user chooses standard shipping.
		// Even though there may be many duplicate Shpping Method Codes linked to the same Choice IDs... there can only be one Alert Message per Shipping Method Code.
	function setAlertForShippingMethod($shippingChoiceID, $shippingMethodCode, $alertMessage){

		$alertMessage = trim($alertMessage);
		if(strlen($alertMessage) > 30)
			throw new Exception("Error in method setAlertForShippingMethod. The message cannot be greater than 30 characters.");
		
		$notFoundFlag = true;

		if($this->downgradeShippingFlag){
			
			// For Shipping Downgrades... set all of the Alert Message to the same thing (in case there are multiple duplicate Shipping Method Codes linked to the same choice).
			foreach(array_keys($this->shippingMethodsDowngrades) as $thisKey){
				if($this->shippingMethodsDowngrades[$thisKey][self::$DB_SHIP_METHOD_CODE] == $shippingMethodCode && $this->shippingMethodsDowngrades[$thisKey][self::$DB_SHIP_CHOICE_ID] == $shippingChoiceID){
					$notFoundFlag = false;
					$this->shippingMethodsDowngrades[$thisKey][self::$DB_ALERT_MESSAGE] = $alertMessage;
				}
			}
		}
		else{

			foreach(array_keys($this->shippingMethods1to1Map) as $thisKey){
				if($this->shippingMethods1to1Map[$thisKey][self::$DB_SHIP_METHOD_CODE] == $shippingMethodCode && $this->shippingMethods1to1Map[$thisKey][self::$DB_SHIP_CHOICE_ID] == $shippingChoiceID){
					$notFoundFlag = false;
					$this->shippingMethods1to1Map[$thisKey][self::$DB_ALERT_MESSAGE] = $alertMessage;
				}
			}
		}

		
		if($notFoundFlag)
			throw new Exception("Error in method setAlertForShippingMethod. The shipping code was not found.");
	}
	
	function setAddressTypeForShippingDowngradeLink($shippingMethodLinkID, $addressType){
		
		if($addressType != self::ADDRESS_TYPE_COMMERCIAL && $addressType != self::ADDRESS_TYPE_RESIDENTIAL && $addressType != self::ADDRESS_TYPE_NONE)
			throw new Exception("Error with method setAddressTypeForShippingDowngradeLink. The address type is incorrect.");
			
		if(!$this->downgradeShippingFlag)
			throw new Exception("Error in method setAddressTypeForShippingDowngradeLink. The domain type is wrong.");
			
		// You can't change the Address Type on the Default Shipping Method link.
		$shippingChoiceID = $this->getShippingChoiceIDfromShippingMethodLinkID($shippingMethodLinkID);
		if($this->getDefaultShippingMethodLinkID($shippingChoiceID) == $shippingMethodLinkID)
			return;
			
		foreach (array_keys($this->shippingMethodsDowngrades) as $thisKey){
			if($this->shippingMethodsDowngrades[$thisKey][self::$DB_ID] == $shippingMethodLinkID)
				$this->shippingMethodsDowngrades[$thisKey][self::$DB_ADDRESS_TYPE] = $addressType;
		}
	}
	
	
	function getAddressTypeForShippingDowngradeLink($shippingMethodLinkID){
		
		if(!$this->downgradeShippingFlag)
			throw new Exception("Error in method getAddressTypeForShippingDowngradeLink. The domain type is wrong.");
			
		foreach (array_keys($this->shippingMethodsDowngrades) as $thisKey){
			if($this->shippingMethodsDowngrades[$thisKey][self::$DB_ID] == $shippingMethodLinkID)
				return $this->shippingMethodsDowngrades[$thisKey][self::$DB_ADDRESS_TYPE];
		}
		throw new Exception("Error in method getAddressTypeForShippingDowngradeLink. Link Not Found");
	}
	
	
	
	function getShippingChoiceIDfromShippingMethodLinkID($shippingMethodLinkID){
		
		if($this->downgradeShippingFlag){
			foreach (array_keys($this->shippingMethodsDowngrades) as $thisKey){
				if($this->shippingMethodsDowngrades[$thisKey][self::$DB_ID] == $shippingMethodLinkID)
					return $this->shippingMethodsDowngrades[$thisKey][self::$DB_SHIP_CHOICE_ID];
			}
		}
		else{
			foreach (array_keys($this->shippingMethods1to1Map) as $thisKey){
				if($this->shippingMethods1to1Map[$thisKey][self::$DB_ID] == $shippingMethodLinkID)
					return $this->shippingMethods1to1Map[$thisKey][self::$DB_SHIP_CHOICE_ID];
			}
		}
		throw new Exception("Error in method getShippingChoiceIDfromShippingMethodLinkID");
	}
	


	function setWeightThresholdsOnShippingMethodLink($shippingMethodLinkID, $minWeight, $maxWeight){

		$minWeight = floatval($minWeight);
		$maxWeight = floatval($maxWeight);
	
		if($minWeight > $maxWeight)
			throw new Exception("Error in method setWeightThresholdsOnShippingMethodLink.  The minimum weight can not be greater than the maximum.");
		
			
		$shippingChoiceIDfromLink = $this->getShippingChoiceIDfromShippingMethodLinkID($shippingMethodLinkID);
		
		if(($this->getDefaultShippingMethodLinkID($shippingChoiceIDfromLink) == $shippingMethodLinkID) && (!empty($minWeight) || !empty($maxWeight)))
			throw new Exception("Error in method setWeightThresholdsOnShippingMethodLink. You can not set weight values on a default shipment method.");
			
		$notFound = true;
		
		foreach (array_keys($this->shippingMethodsDowngrades) as $thisKey){
			if($this->shippingMethodsDowngrades[$thisKey][self::$DB_ID] == $shippingMethodLinkID){
				
				$this->shippingMethodsDowngrades[$thisKey][self::$DB_MIN_WEIGHT] = $minWeight;
				$this->shippingMethodsDowngrades[$thisKey][self::$DB_MAX_WEIGHT] = $maxWeight;
				
				$notFound = false;
			}
		}
		
		if($notFound)
			throw new Exception("Error in method setWeightThresholdsOnShippingMethodLink. Selection not found.");
	}
	
	// Returns Zero if there is not a Minimum weight limit
	function getMinimumWeightForShippingMethodLink($shippingMethodLinkID){
		
		foreach (array_keys($this->shippingMethodsDowngrades) as $thisKey){
			if($this->shippingMethodsDowngrades[$thisKey][self::$DB_ID] == $shippingMethodLinkID)
				return $this->shippingMethodsDowngrades[$thisKey][self::$DB_MIN_WEIGHT];
		}
		throw new Exception("Error in method getMinimumWeightForShippingMethodLink");
	}
	// Returns Zero if there is not a Maximum weight limit.
	function getMaximumWeightForShippingMethodLink($shippingMethodLinkID){
		foreach (array_keys($this->shippingMethodsDowngrades) as $thisKey){
			if($this->shippingMethodsDowngrades[$thisKey][self::$DB_ID] == $shippingMethodLinkID)
				return $this->shippingMethodsDowngrades[$thisKey][self::$DB_MAX_WEIGHT];
		}
		throw new Exception("Error in method getMaximumWeightForShippingMethodLink");
	}
	
	
	// Even though there may be many duplicate Shpping Method Codes linked to the same Choice IDs... there can only be one Alert Message per Shipping Method Code.
	function getAlertMessageForShippingMethod($shippingChoiceID, $shippingMethodCode){

		if($this->downgradeShippingFlag){
			foreach (array_keys($this->shippingMethodsDowngrades) as $thisKey){
				if($this->shippingMethodsDowngrades[$thisKey][self::$DB_SHIP_CHOICE_ID] == $shippingChoiceID && $this->shippingMethodsDowngrades[$thisKey][self::$DB_SHIP_METHOD_CODE] == $shippingMethodCode)
					return $this->shippingMethodsDowngrades[$thisKey][self::$DB_ALERT_MESSAGE];
			}
		}
		else{
			foreach (array_keys($this->shippingMethods1to1Map) as $thisKey){
				if($this->shippingMethods1to1Map[$thisKey][self::$DB_SHIP_CHOICE_ID] == $shippingChoiceID && $this->shippingMethods1to1Map[$thisKey][self::$DB_SHIP_METHOD_CODE] == $shippingMethodCode)
					return $this->shippingMethods1to1Map[$thisKey][self::$DB_ALERT_MESSAGE];
			}
		}
		throw new Exception("Error in method getAlertMessageForShippingMethod");
	}
	

	function checkIfShippingMethodLinkIDexists($shippingMethodLinkID){

		if($this->downgradeShippingFlag){
			foreach (array_keys($this->shippingMethodsDowngrades) as $thisKey){
				if($this->shippingMethodsDowngrades[$thisKey][self::$DB_ID] == $shippingMethodLinkID)
					return true;
			}
		}
		else{
			foreach (array_keys($this->shippingMethods1to1Map) as $thisKey){
				if($this->shippingMethods1to1Map[$thisKey][self::$DB_ID] == $shippingMethodLinkID)
					return true;
			}
		}
		return false;
	}
	
	
	

}


?>

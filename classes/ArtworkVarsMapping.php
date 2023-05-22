<?

// This class is responsible for analyzing Variable names inside of an artwork file
// and mapping the variable names to positions that correspond to VariableData columns
// It builds and parses XML to be stored with a project so that we can keep a record of what has been mapped

class ArtworkVarsMapping {

	private $_parser; 
	private $_curTag;
	private $_attributes;
	private $_xmlfile;
	
	private $_artworkObj;

	private $_artworkVariablesArr = array();
	private $_mappedVariablesArr = array(); 
	private $_mappedVariablesPositionsArr = array(); 	// The key is the Variable name, the value is an 
							// Int representing its column position.  Starts at 1
	// An array of VariableDataFieldAlterData objects.
	// The key to the array is the Variable Name.
	private $_dataFieldManipulations = array();
	
	// An array of Size Restriction Objects
	// The key to the array is the Variable Name
	private $_dataFieldSizeRestrictions = array();
	
	private $_currentDataFieldVarParsing;

	// Constructor
	// Takes an Artwork file and parses it for Variable Names
	// Artwork is mandatory, xmlMapping will not be created until the user starts assigning variables to positions
	function ArtworkVarsMapping($artworkFile, $varMappingsXML = ""){

		if(!empty($varMappingsXML))
			$this->loadConfigurationByXML($varMappingsXML);
		
		$this->_artworkObj = new ArtworkInformation($artworkFile);
		
		$this->_setVariableNamesFromArtwork($artworkFile);

	}
	

	// Pass in a variable data configuration file (only used by this class)
	function loadConfigurationByXML($varMappingsXML){
	
		// Clear out existing mappings before we parse the XML.
		$this->_mappedVariablesArr = array();
		$this->_mappedVariablesPositionsArr = array();
	
		$this->_xmlfile = $varMappingsXML;
		$this->_parseXMLdoc();
	
	}
	
	
	// Returns a list of variable names that are found within the Artwork file.
	function getVariableNamesArrFromArtwork(){
		
		return $this->_artworkVariablesArr;
	}
	
	// Returns true if no variables are found in the artwork file
	function checkForEmptyVariablesInArtwork(){
	
		return empty($this->_artworkVariablesArr);
	}
	
	// Returns a list of Variable names that have been mapped to field positions
	// The order of the array corresponds directly to the mapping position.
	function getMappedVariableNamesArr(){
	
		$retArr = array();
		
		for($i=1; $i <= $this->_getMaxVariablePosition(); $i++){
			foreach($this->_mappedVariablesArr as $mappedVar){
				if($this->_mappedVariablesPositionsArr[$mappedVar] == $i){
					$retArr[] = $mappedVar;
					break;
				}
			}
		}
		
		return $retArr;
		
	}
	
	// Returns a list of variable names in the artwork that have not been mapped to a field position yet.
	function getUnMappedVariableNamesArr(){
	
		$retArr = array();
		
		foreach($this->_artworkVariablesArr as $artworkVar){
			if(!in_array($artworkVar, $this->_mappedVariablesArr))
				$retArr[] = $artworkVar;
		}
		
		return $retArr;
	}
	
	// Return True if it finds 1 or more Variable with the special tag for a variable image
	function checkForVariableImageTag(){
	
		$varImagesArr = $this->getVariableImageNamesArr();
		
		return !empty($varImagesArr);
	}
	
	// Returns a list of Varaible Names in the Artwork that are intended for Variable Image Substitution
	function getVariableImageNamesArr(){
	
		$retArr = array();
		
		foreach($this->_artworkVariablesArr as $artworkVar){
			if(preg_match("/^IMAGE:(\w|\d)+$/i", $artworkVar))
				$retArr[] = $artworkVar;
		}
		
		return $retArr;
	}


	
	// Returns a list of fields that were previously mapped, but have since disapeared in the Artwork file.
	function getExtraMappedFieldsArr(){
	
		$retArr = array();
		
		foreach($this->_mappedVariablesArr as $mappedVar){
			if(!in_array($mappedVar, $this->_artworkVariablesArr))
				$retArr[] = $mappedVar;
		}
		
		return $retArr;
	}
	
	// returns an array of Variables that have been mapped to a position outside of the availability
	function getMappedFieldsOutsideOfAvailableColumns(){
	
		$totalColumns = $this->getTotalColumns();
		
		$retArr = array();
		foreach($this->_mappedVariablesPositionsArr as $thisVarName => $thisPosition){
			if($thisPosition > $totalColumns)
				$retArr[] = $thisVarName;
			
		}
		
		return $retArr;
	}
	
	
	// Returns true only if all of the Variables in the Artwork file have been mapped to field positions
	function checkForMappingErrors(){
		return $this->checkForUnmappedFields() || $this->checkForExtraMappedFields() || $this->checkForMappedFieldsOutsideOfColumnSpace();
	}
	
	
	// Returns true if there are Variable names that have not been mapped to a particular column yet.
	function checkForUnmappedFields(){
		$unmappedCount = sizeof($this->getUnMappedVariableNamesArr());
		
		return $unmappedCount > 0 ? true : false;
	}
	
	// Returns true if there were fields mapped that have since disapeared in the artwork file.
	function checkForExtraMappedFields(){
		$mappedCount = sizeof($this->getExtraMappedFieldsArr());
		
		return $mappedCount > 0 ? true : false;
	}
	
	// Returns true if there were fields mapped that have since disapeared in the artwork file.
	function checkForMappedFieldsOutsideOfColumnSpace(){
		$count = sizeof($this->getMappedFieldsOutsideOfAvailableColumns());
		
		return $count > 0 ? true : false;
	}
	
	
	// Returns an integer corresponding to the variable name that is mapped.
	// If the variable has not been mapped yet, then it will return 0;
	// Field positions are 1 based.
	function getFieldPosition($varName){
		
		if(!isset($this->_mappedVariablesPositionsArr[$varName]))
			return 0;
		
		return $this->_mappedVariablesPositionsArr[$varName];
	}
	
	// Returns a variable name that is assigned to the given position
	// Returns a blank string if the position does not have a variable defined yet.
	function getVarNameByFieldPosition($positionNumber){
	
		if(!preg_match("/^\d+$/", $positionNumber))
			throw new Exception("The position must be a number");
		
		$retVal = "";
		
		foreach($this->_mappedVariablesPositionsArr as $varName => $varPosition){
			if($varPosition == $positionNumber)
				$retVal = $varName;
		}
	
		return $retVal;
	}

	// Returns the maxiumum number of positions for this artwork mapping
	// If the _getMaxVariablePosition is 5 lets say.... there can still be 20 unmapped fileds
	// Or there could be less artwork variables than there are Mapped fields, in which case it takes the greater of the two.
	// It will make sure there are exactly enough spaces to cover all UnMapped, Extra, and Mapped fields
	function getTotalColumns(){
	
		$numberOfEmptySlots = 0;
		for($i=1; $i <= $this->_getMaxVariablePosition(); $i++){
			$found = false;
			foreach($this->_mappedVariablesPositionsArr as $position){
				if($position == $i){
					$found = true;
					break;
				}
			}
			if(!$found)
				$numberOfEmptySlots++;
		}
		
		
		$numberOfUnmappedArtworkVars = sizeof($this->getUnMappedVariableNamesArr());
	
		return ($numberOfUnmappedArtworkVars - $numberOfEmptySlots + $this->_getMaxVariablePosition());
	}
	
	
	// Returns the highest column number which has been mapped
	// returns 0 if no variables have been mapped yet.
	function getMaxMappedPosition(){
	
		$maxPos = 0;
		
		foreach($this->_mappedVariablesPositionsArr as $thisPosition){
			if($thisPosition > $maxPos)
				$maxPos = $thisPosition;
		}
		
		return $maxPos;
	}

	
	// Will return an array of Positions within the column range that do not have positions assigned to variables yet
	function getOpenPositionsWithinColumnRange(){

		$retArr = array();
	
		for($i=1; $i <= $this->getTotalColumns(); $i++){
			if($this->getVarNameByFieldPosition($i) == "")
				$retArr[] = $i;
		}
		
		return $retArr;
	}
	
	

	
	// Reorganizes the order of the columns for the mapping position
	// EX: if you move a column from position 5 to position 2.. existing 2, 3, 4 will get pushed down
	// If the position value is greater than the number of variables in the Artwork, the script will fail.
	// Before the entire artwork has been mapped, there may be gaps in positions...  
	// You can have a variable in position 3 and have nothing in position 2 or 1 yet.
	function setFieldPosition($varName, $position){
	
		if(!preg_match("/^\d+$/", $position))
			throw new Exception("Position must be numeric");

		if(!in_array($varName, $this->_artworkVariablesArr))
			throw new Exception("The Variable does not exist: $varName in method call setFieldPosition");

		// A variable may be set to any position... as long as it does not exceed the maximum limit,
		// which is the greatest of any ....
		// 1) Total Artwork Variables, 2) Highest Mapped column, 3) Total Available Columns

		$maxColumnAllowed = ($this->getTotalColumns() > sizeof($this->_artworkVariablesArr)) ? 
						$this->getTotalColumns() : sizeof($this->_artworkVariablesArr);
		$maxColumnAllowed = ($maxColumnAllowed > $this->_getMaxVariablePosition()) ? $maxColumnAllowed : $this->_getMaxVariablePosition();

		if($maxColumnAllowed < $position)
			throw new Exception("Can not set a variable position outside of the column range");
			

		// If there is nothing already occupying the spot that we want to put the variable in
		// then it means we don't need to re organize any of the other fields.
		if($this->getVarNameByFieldPosition($position) == ""){
			
			if(!in_array($varName, $this->_mappedVariablesArr))
				$this->_mappedVariablesArr[] = $varName;
				
			$this->_mappedVariablesPositionsArr[$varName] = $position;
			
			return;
		}
		

		$oldPosition = $this->getFieldPosition($varName);
		$totalColumns = $this->getTotalColumns();
		
		foreach($this->getMappedVariableNamesArr() as $mappedVar){
	
			$varCurrentPosition = $this->_mappedVariablesPositionsArr[$mappedVar];

			// This loop is only for re-organizing the other variables
			if($varName == $mappedVar)
				continue;

			// Any variables above the position that we are moving to AND from will not get reorganized
			if($varCurrentPosition < $position && $varCurrentPosition < $oldPosition)
				continue;


			if($varCurrentPosition <= $position){
				
				// This means that are moving an variable to the right in position
				// EX.  A variable at position 2 is moved to position 6... so 3 moves to 2, 4 moves to 3, 5 moves to 4
				if($oldPosition < $position){
					$this->_mappedVariablesPositionsArr[$mappedVar] = $varCurrentPosition - 1;
					continue;
				}
			}
				
			// Don't reorganize anything after the position it was being moved from or moved to
			// The old Position will be 0 if it was never mapped before. 
			if($varCurrentPosition > $oldPosition && $varCurrentPosition > $position)
				break;

			// This is the new position that the variable will be mapped to after incrementing
			$newPositionForThisVar = $varCurrentPosition + 1;
			
			// Find out if there is a Gap looking 1 ahead
			// If there is a gap then we will fill it by incrementing the current Position
			// ... and nothing else needs to be pushed forward after that
			$stopPushingForwardFlag = false;
			if($this->getVarNameByFieldPosition($newPositionForThisVar) == "")
				$stopPushingForwardFlag = true;
			
			// In case we have past the limit of the stack... we may need to push it back in a circle
			// Or if we have past the point between the active range ... at which the Master variable was being moved from or to
			// We try to put this variable back in the position that the Master variable was moved From.
			if(($newPositionForThisVar > $totalColumns) || ($newPositionForThisVar > $oldPosition && $newPositionForThisVar > $position)){
				if($oldPosition != 0)
					$this->_mappedVariablesPositionsArr[$mappedVar] = $oldPosition;
				else
					$this->_mappedVariablesPositionsArr[$mappedVar] = $newPositionForThisVar;
			}
			else
				$this->_mappedVariablesPositionsArr[$mappedVar] = $newPositionForThisVar;
			
			if($stopPushingForwardFlag)
				break;
		}
		
		if(!in_array($varName, $this->_mappedVariablesArr))
			$this->_mappedVariablesArr[] = $varName;
		
		$this->_mappedVariablesPositionsArr[$varName] = $position;
	}
	
	
	// In order to remove a variable from the mapping...
	// the variable must be previously mapped and already absent within the artwork file.
	// All fields in the artwork file must be mapped before attempting to remove a mapped variable.
	function removeVariableFromMapping($varName){
	
		if(!in_array($varName, $this->getExtraMappedFieldsArr()))
			throw new Exception("The variable being deleted must exis within getExtraMappedFieldsArr()");
			
		if($this->checkForUnmappedFields())
			throw new Exception("All artwork variables must be mapped before calling getExtraMappedFieldsArr()");
	
		$mappedFieldsArrCopy = array();
		foreach($this->_mappedVariablesArr as $thisVarName){
			if($thisVarName != $varName)
				$mappedFieldsArrCopy[] = $thisVarName;
		}
		$this->_mappedVariablesArr = $mappedFieldsArrCopy;
		
		
		
		$mappedFieldsPositionArrCopy = array();
		foreach($this->_mappedVariablesPositionsArr as $thisVarName => $thisPosition){
			if($thisVarName != $varName)
				$mappedFieldsPositionArrCopy[$thisVarName] = $thisPosition;
		}
		$this->_mappedVariablesPositionsArr = $mappedFieldsPositionArrCopy;
		
		
		// Now assign any Mapped fields out of the column space into the open positions that were just created
		$outOfColumnSpaceArr = $this->getMappedFieldsOutsideOfAvailableColumns();
		
		$availablePositionsArr = $this->getOpenPositionsWithinColumnRange();
		
		$counter = 0;
		foreach($outOfColumnSpaceArr as $outsideVar){
		
			if(!($counter >= sizeof($availablePositionsArr))){
				// In case we deleted a variable in the middle/front of the stack we don't want to try and set its position
				if(in_array($outsideVar, $this->_artworkVariablesArr))
					$this->setFieldPosition($outsideVar, $availablePositionsArr[$counter]);
			}
		
			$counter++;
		}
		
	}
	
	
	// Returns an XML file describing the Variable that have been mapped, along with their position
	// Does not include unmapped variables or anything
	function getVarMappingInXML(){
		$returnXML = '<?xml version="1.0"?>' . "\n";
		$returnXML .= "<ArtworkMappings>\n"; 

		foreach($this->_mappedVariablesPositionsArr as $varName => $position )
			$returnXML .= "<VarMapping position=\"$position\">$varName</VarMapping>\n"; 
		
		
		// Now add any Variable Data Field Alterations
		// But don't add them if the Variable Name was already removed.
		// This is a way to keep These fields synced up with the Variables in the Positions.
		
		if(!empty($this->_dataFieldManipulations)){
		
			$returnXML .= "\n" . '<DataChanges>' . "\n";
			

			foreach($this->_dataFieldManipulations as $varName => $dataFieldAlterObj){
			
				// Make sure that varname exists within the field mappings.
				if(!isset($this->_mappedVariablesPositionsArr[$varName]))
					continue;
				
				$returnXML .= '<ChangedByVar VarName="' . WebUtil::htmlOutput($varName) . '">' . "\n";
				
				$returnXML .= '<Criteria>' . WebUtil::htmlOutput($dataFieldAlterObj->getCriteria()) . '</Criteria>' . "\n";
				$returnXML .= '<AddDataBefore>' . WebUtil::htmlOutput($dataFieldAlterObj->getDataAddBefore()) . '</AddDataBefore>' . "\n";
				$returnXML .= '<AddDataAfter>' . WebUtil::htmlOutput($dataFieldAlterObj->getDataAddAfter()) . '</AddDataAfter>';
				$returnXML .= '<RemoveDataBefore>' . WebUtil::htmlOutput($dataFieldAlterObj->getDataRemoveBefore()) . '</RemoveDataBefore>' . "\n";
				$returnXML .= '<RemoveDataAfter>' . WebUtil::htmlOutput($dataFieldAlterObj->getDataRemoveAfter()) . '</RemoveDataAfter>' . "\n";
				
				$returnXML .= '</ChangedByVar>' . "\n";
				
			}
			
			$returnXML .= '</DataChanges>' . "\n\n";
		}
		
		
		
		// Now add any Variable Data Size Restrictions 
		// But don't add them if the Variable Name was already removed.
		// This is a way to keep These fields synced up with the Variables in the Positions.
		if(!empty($this->_dataFieldSizeRestrictions)){
		
			$returnXML .= '<VariableSizeRestrictions>' . "\n";
			

			foreach($this->_dataFieldSizeRestrictions as $varName => $sizeRestrictionObj){
			
				// Make sure that varname exists within the field mappings.
				if(!isset($this->_mappedVariablesPositionsArr[$varName]))
					continue;
				
				// Check to make sure that object was not cleared out.
				if(!$sizeRestrictionObj->checkIfSizeRestrictionExists())
					continue;
	
				
				$returnXML .= '<SizeRestriction VarName="' . WebUtil::htmlOutput($varName) . '">' . "\n";
				
				$returnXML .= '<RestrictionType>' . $sizeRestrictionObj->getRestrictionType() . '</RestrictionType>' . "\n";
				$returnXML .= '<RestrictionLimit>' . $sizeRestrictionObj->getRestrictionLimit() . '</RestrictionLimit>' . "\n";
				$returnXML .= '<RestrictionAction>' . $sizeRestrictionObj->getRestrictionAction() . '</RestrictionAction>' . "\n";
				
				$returnXML .= '</SizeRestriction>' . "\n";
				
			}
			
			$returnXML .= '</VariableSizeRestrictions>' . "\n\n";
		}
		
		
		
		
		
		
		$returnXML .= '</ArtworkMappings>';
		
		return $returnXML;
	}
	
	
	// Fills up the _artworkVariablesArr with a unique list of {Variables} it finds
	// A variable must be in curly braces, with no spaces or special characters
	// Variables are case sensitive
	function _setVariableNamesFromArtwork($artworkFile){
	
		$retArray = array();

		$m = array();
		if(preg_match_all("/{(\w+)}/", $artworkFile, $m))
			$retArray = array_merge($retArray, $m[1]);

		// A variable like {IMAGE:Test1} is a special type of variable used for Images
		$m2 = array();
		if(preg_match_all("/{(IMAGE:(\w|\d)+)}/i", $artworkFile, $m2))
			$retArray = array_merge($retArray, $m2[1]);
		
		$retArray = array_unique($retArray);
		
		// The Variable Name {Sequence} is a special variable name.... it should not be assigned to a column in the data file
		// If this special variable is in the Artwork file then it will be automatically substitued with the corresponding row from the data file.
		$xArr = array();
		foreach($retArray as $thisVar){
			if(strtoupper($thisVar) != "SEQUENCE")
				$xArr[] = $thisVar;
		}
		
		$this->_artworkVariablesArr = $xArr;
		
	}

	// The maximum Variable position (that has been defined)
	function _getMaxVariablePosition(){
	
		$maxVariablePosition = 0;
		foreach($this->_mappedVariablesArr as $mappedVar){
			if($this->_mappedVariablesPositionsArr[$mappedVar] > $maxVariablePosition)
				$maxVariablePosition = $this->_mappedVariablesPositionsArr[$mappedVar];
		}
		
		return $maxVariablePosition;
	}
	
	

	// --------------------------  Methods below to parse the xml document ---------------------------------------
	function _parseXMLdoc(){

		// Define Event driven functions for XPAT processor.
		$this->_parser = xml_parser_create();
		xml_set_object($this->_parser, $this);
		xml_set_element_handler($this->_parser, "_startElement", "_endElement");
		xml_set_character_data_handler($this->_parser, "characterData");

		if (!xml_parse($this->_parser, $this->_xmlfile)) {
			die(sprintf("XML error: %s at line %d", xml_error_string(xml_get_error_code($this->_parser)), xml_get_current_line_number($this->_parser)));
		}

		xml_parser_free($this->_parser);
	}

	function _startElement($parserObj, $name, $attrs) {

		$this->_attributes = $attrs;

		$this->_curTag .= "^$name";
		
		
		
		// When we come across the following key it must have an attribute within telling it what is the Variable Name
		// Then we create a new Data Alteration object for that variable name.
		$dataChangeByVar_Key = "^ARTWORKMAPPINGS^DATACHANGES^CHANGEDBYVAR";
		
		if ($this->_curTag == $dataChangeByVar_Key){
		
			if(!isset($this->_attributes["VARNAME"]) || empty($this->_attributes["VARNAME"]))
				throw new Exception("The VarName attribute has not been defined for this Data Change Node: " . $this->_curTag);
		
			$this->_currentDataFieldVarParsing = $this->_attributes["VARNAME"];
			
			$this->_dataFieldManipulations[$this->_currentDataFieldVarParsing] = new VariableDataFieldAlterData();
		}
		
		
		$dataSizeRestrictionByVar_Key = "^ARTWORKMAPPINGS^VARIABLESIZERESTRICTIONS^SIZERESTRICTION";
		
		if ($this->_curTag == $dataSizeRestrictionByVar_Key){
		
			if(!isset($this->_attributes["VARNAME"]) || empty($this->_attributes["VARNAME"]))
				throw new Exception("The VarName attribute has not been defined for this Data Size Restriction Node: " . $this->_curTag);
		
			$this->_currentDataFieldVarParsing = $this->_attributes["VARNAME"];
			
			$this->_dataFieldSizeRestrictions[$this->_currentDataFieldVarParsing] = new VariableDataSizeRestrictions();
		}
		

		
	}

	function _endElement($parserObj, $name) {

		$caret_pos = strrpos($this->_curTag,'^');

		$this->_curTag = substr($this->_curTag, 0, $caret_pos);
		
	}

	function characterData($parserObj, $data) {

		// Define  possible tag structures with a carrot separating each tag name.

		$mapping_Key = "^ARTWORKMAPPINGS^VARMAPPING";
		
		$dataChangeCriteria_Key = "^ARTWORKMAPPINGS^DATACHANGES^CHANGEDBYVAR^CRITERIA";
		$dataChangeDataAddBefore_Key = "^ARTWORKMAPPINGS^DATACHANGES^CHANGEDBYVAR^ADDDATABEFORE";
		$dataChangeDataAddAfter_Key = "^ARTWORKMAPPINGS^DATACHANGES^CHANGEDBYVAR^ADDDATAAFTER";
		$dataChangeDataRemoveBefore_Key = "^ARTWORKMAPPINGS^DATACHANGES^CHANGEDBYVAR^REMOVEDATABEFORE";
		$dataChangeDataRemoveAfter_Key = "^ARTWORKMAPPINGS^DATACHANGES^CHANGEDBYVAR^REMOVEDATAAFTER";
		
		
		$dataSizeRestrictionType_Key = "^ARTWORKMAPPINGS^VARIABLESIZERESTRICTIONS^SIZERESTRICTION^RESTRICTIONTYPE";
		$dataSizeRestrictionLimit_Key = "^ARTWORKMAPPINGS^VARIABLESIZERESTRICTIONS^SIZERESTRICTION^RESTRICTIONLIMIT";
		$dataSizeRestrictionAction_Key = "^ARTWORKMAPPINGS^VARIABLESIZERESTRICTIONS^SIZERESTRICTION^RESTRICTIONACTION";
		
		

		if ($this->_curTag == $mapping_Key){
		
			if(!isset($this->_attributes["POSITION"]) || empty($this->_attributes["POSITION"]))
				throw new Exception("The position attribute has not been defined for this artwork mapping");
		
			$this->_mappedVariablesArr[] = $data;
			$this->_mappedVariablesPositionsArr[$data] = $this->_attributes["POSITION"];

		}
		
		// --------------------------  Data Field Manipulations ------------------------------
		
		else if ($this->_curTag == $dataChangeCriteria_Key){

			if(empty($this->_currentDataFieldVarParsing))
				throw new Exception("Error when trying to set a Data Field Alteration.  The Variable Name can not be left balnk.");


			$this->_dataFieldManipulations[$this->_currentDataFieldVarParsing]->setCriteria($data);
		}
		else if ($this->_curTag == $dataChangeDataAddBefore_Key){

			// You will notice that I am passing in the second paramters as "append" true.
			// this is because the cdata handler function parses HTML entities individually.
			// This key may be called several times during the same message if there are HTML character codes indside.
			
			$this->_dataFieldManipulations[$this->_currentDataFieldVarParsing]->setDataAddBefore($data, true);
		}
		else if ($this->_curTag == $dataChangeDataAddAfter_Key){

			$this->_dataFieldManipulations[$this->_currentDataFieldVarParsing]->setDataAddAfter($data, true);
		}
		else if ($this->_curTag == $dataChangeDataRemoveBefore_Key){

			$this->_dataFieldManipulations[$this->_currentDataFieldVarParsing]->setDataRemoveBefore($data, true);
		}
		else if ($this->_curTag == $dataChangeDataRemoveAfter_Key){

			$this->_dataFieldManipulations[$this->_currentDataFieldVarParsing]->setDataRemoveAfter($data, true);
		}
		
		
		// --------------------------  Data Field Size Restrictions ------------------------------
		
		else if ($this->_curTag == $dataSizeRestrictionType_Key){

			if(empty($this->_currentDataFieldVarParsing))
				throw new Exception("Error when trying to set a Data Size Restriction.  The Variable Name can not be left balnk.");

			$this->_dataFieldSizeRestrictions[$this->_currentDataFieldVarParsing]->setSizeRestrictionType($data);
		}
		else if ($this->_curTag == $dataSizeRestrictionLimit_Key){

			$this->_dataFieldSizeRestrictions[$this->_currentDataFieldVarParsing]->setSizeRestrictionLimit($data);
		}
		else if ($this->_curTag == $dataSizeRestrictionAction_Key){

			$this->_dataFieldSizeRestrictions[$this->_currentDataFieldVarParsing]->setSizeRestrictionAction($data);
		}
		
		
		
		
		

	}
	
	
	// --------------------------  Data Field Manipulations ------------------------------
	
	// Pass in a String for the Variable name and an object of the class "VariableDataFieldAlterData"
	function addVariableDataFieldAlteration($variableName, $varDataFieldAltObj){

		if(strlen($variableName) > 50)
			throw new Exception("Error with Variable Name in method addVariableDataFieldAlteration.");
		
		// In case we are doing an update ... get rid of the old Object.
		if(isset($this->_dataFieldManipulations[$variableName]))
			unset($this->_dataFieldManipulations[$variableName]);
		
		
		// Don't add it to our list unless there is something in it.
		if(!$varDataFieldAltObj->checkIfDataAlterationsAreEmpty())
			$this->_dataFieldManipulations[$variableName] = $varDataFieldAltObj;
	}
	
	

	// Returns true or false whether or not a Variable Name has a Data Alteration Object to go with it. 
	function checkIfVariableHasDataAlteration($variableName){
	
		// If the Variable Position has not been set... then obviously there is no Data Alter Object to go with it.
		if(!in_array($variableName, $this->_artworkVariablesArr))
			return false;
	
		if(isset($this->_dataFieldManipulations[$variableName]))
			return true;
		else
			return false;
	}
	
	function getDataAlterationObjectForVariable($variableName){
	
		if(!$this->checkIfVariableHasDataAlteration($variableName))
			throw new Exception("Error in Method getDataAlterationObjectForVariable.  You must check if an Alteration Object exists before calling this method.");
		
		return $this->_dataFieldManipulations[$variableName];
	}
	
	
	
	// --------------------------  Data Field Size Restrictions ------------------------------
	
	// Pass in a String for the Variable name and an object of the class "VariableDataFieldAlterData"
	function addDataFieldSizeRestriction($variableName, $varDataSizeRestrictObj){

		if(strlen($variableName) > 50)
			throw new Exception("Error with Variable Name in method addDataFieldSizeRestriction.");
		
		// In case we are doing an update ... get rid of the old Object.
		if(isset($this->_dataFieldSizeRestrictions[$variableName]))
			unset($this->_dataFieldSizeRestrictions[$variableName]);
		
		
		// Don't add it to our list unless there is something in it.
		if($varDataSizeRestrictObj->checkIfSizeRestrictionExists())
			$this->_dataFieldSizeRestrictions[$variableName] = $varDataSizeRestrictObj;
	}
	
	

	// Returns true or false whether or not a Variable Name has a Data Alteration Object to go with it. 
	function checkIfVariableHasSizeRestriction($variableName){
	
		// If the Variable Position has not been set... then obviosly there is no Data Size Object to go with it.
		if(!in_array($variableName, $this->_artworkVariablesArr))
			return false;
	
		if(isset($this->_dataFieldSizeRestrictions[$variableName]))
			return true;
		else
			return false;
	}
	
	function getSizeRestrictionObjectForVariable($variableName){
	
		if(!$this->checkIfVariableHasSizeRestriction($variableName))
			throw new Exception("Error in Method getSizeRestrictionObjectForVariable.  You must check if an Size Restriction Object exists before calling this method.");
		
		return $this->_dataFieldSizeRestrictions[$variableName];
	}
	
	
	// Pass in a Layer Level (stored in the Artwork XML)... it is a unique ID to the Text Layer
	// Side Number is Zero Based.
	// This method will always return a VariableDataSizeRestrictions Object.
	// The VariableDataSizeRestrictions Object may have empty values if there are no Sizing Restrictions though.
	// This method does not check if the Layer Level exists, or print any other type of errors.
	// In order for a Text Layer to be a assigned a VariableDataSizeRestrictions Object... 2 criteria must be met
	// 	1) The configuation must be saved into this Variable Data Mapping Object.
	//	2) That Variable must be the only data within the Text Layer... such as <text>{CompanyName}</text>
	function getSizeRestrictionObjFromLayerLevel($sideNumber, $layerLevel){
	
		$varSizeRestrictObj = new VariableDataSizeRestrictions();
	
		if(!isset($this->_artworkObj->SideItemsArray[$sideNumber]))
			return $varSizeRestrictObj;
		
		
		$sideObj = $this->_artworkObj->SideItemsArray[$sideNumber];
		
		for($i=0; $i<sizeof($sideObj->layers); $i++){
		
			if($sideObj->layers[$i]->level == $layerLevel && $sideObj->layers[$i]->LayerType == "text"){
			
				// Try to find a single {variable} within the text layer.  
				// If there is anything else but just the variable, we can not use any size attributes on the layer.
				$m = array();
				if(preg_match("/^\s*{(\w+)}\s*$/", $sideObj->layers[$i]->LayerDetailsObj->message, $m)){
					
					$variableName = $m[1];
					
					if($this->checkIfVariableHasSizeRestriction($variableName)){
					
						$varSizeRestrictObj = $this->getSizeRestrictionObjectForVariable($variableName);
						
						return $varSizeRestrictObj;
					}
				}	
			}
		}
		

		
		// Just in case we couldn't find a match on the Layer Level... or if that layer level doesn't have SizeRestrictions set on it... return an empyt object.
		return $varSizeRestrictObj;
	}
	

}


// Objects of this class will hold data telling us details on what to do with Variable Data text layers when a certain limit has been reached.
// Some examples could include... causing a line break after so many letters... or shrinking the text size after a Picas Width has been reached.
class VariableDataSizeRestrictions {

	private $_restrictionType;
	private $_restrictionLimit;
	private $_restrictionAction;
	
	function VariableDataSizeRestrictions(){
	
		$this->_restrictionType = null;
		$this->_restrictionLimit = null;
		$this->_restrictionAction = null;
	}
	
	
	
	// Call this method to see if a size restriction has been set on this object.
	function checkIfSizeRestrictionExists(){
	
		return !empty($this->_restrictionType);
	
	}
	
	
	function setSizeRestrictionType($restrictType){
	
		if(!in_array($restrictType, array("GREATER_THAN_PICAS_WIDTH")))
			throw new Exception("Error in Method VariableDataSizeRestrictions->setSizeRestrictionType.  The Restriction Type is invalid");
				
		$this->_restrictionType = $restrictType;
	}
	
	function setSizeRestrictionLimit($restrictLimit){
	
		if(!preg_match("/^\d+$/", $restrictLimit))
			throw new Exception("Error in Method VariableDataSizeRestrictions->setSizeRestrictionLimit.  The Restriction Limit must be a number.");
		
		$this->_restrictionLimit = $restrictLimit;
	}
	
	function setSizeRestrictionAction($restrictAction){
	
		if(!in_array($restrictAction, array("SHRINK_FONT_SIZE")))
			throw new Exception("Error in Method VariableDataSizeRestrictions->setSizeRestrictionAction.  The Restriction Action is invalid");
		
		$this->_restrictionAction = $restrictAction;	
	}
	
	
	// Right now we only have one Restriction Type, and Restriction Action... "GREATER_THAN_PICAS_WIDTH"
	// Possible Future Restriction Types could include... "LESS_THAN_PICAS_WIDTH", "GREATER_THAN_NUMBER_OF_WORDS", "GREATER_THAN_NUMBER_OF_CHARACTERS"
	function getRestrictionType(){
	
		if(empty($this->_restrictionType))
			throw new Exception("Error in method VariableDataSizeRestrictions->getRestrictionType().  Be sure to check if this object has been initailized first by calling the method checkIfSizeRestrictionExists().");
		
		return $this->_restrictionType;
	}

	function getRestrictionLimit(){
	
		if(empty($this->_restrictionLimit))
			throw new Exception("Error in method VariableDataSizeRestrictions->getRestrictionLimit().  The value must be a number... and it can not be Zero.  Be sure to check if this object has been initailized first by calling the method checkIfSizeRestrictionExists().");

		return $this->_restrictionLimit;
	}
	
	// Right now we only have one Restriction Type, and Restriction Action... "SHRINK_FONT_SIZE"
	// Possible Future Restriction Types could include... "LINE_BREAK_AT_NEXT_SPACE", "LINE_BREAK_AT_NEXT_CHARACTER", "GROW_FONT_SIZE"
	function getRestrictionAction(){
	
		if(empty($this->_restrictionAction))
			throw new Exception("Error in method VariableDataSizeRestrictions->getRestrictionAction()");
		
		return $this->_restrictionAction;
	}


}



// Based upon a Certain set of criteria... a Variable Data field may cause a manipulation to the data
// This class will contain data on what triggers the Data to be manipulated... and how it should be changed.
// For example... if someone puts data into an "Address2" column in their data file... we may want to add a line break before the data from the column is written.
class VariableDataFieldAlterData {
	

	private $_criteria;

	
	private $_dataAddBefore;
	private $_dataAddAfter;
	private $_dataRemoveBefore;
	private $_dataRemoveAfter;
	
	

	// Right now the only legal choice is a String "NOTBLANK"
	// In the future we could maybe do pattern matching based upon data in the field.
	function checkIfDataMeetsCriteria($fieldData){
	
		if($this->_criteria == "NOTBLANK"){
			
			$fieldData = trim($fieldData);
			
			if(!empty($fieldData))
				return true;
			else
				return false;
		}
		else{
		
			throw new Exception("Error in Method VariableDataFieldAlterData:checkIfDataMeetsCriteria. The Criteria Field must be set before calling this method.");
		}
	
	}
	
	function setCriteria($criteriaForField){

		if($criteriaForField == "NOTBLANK"){
			$this->_criteria = $criteriaForField;
		}
		else{
			throw new Exception("Error in Method VariableDataFieldAlterData:checkIfDataMeetsCriteria.");
		}
	}
	
	function getCriteria(){

		
		if(empty($this->_criteria))
			throw new Exception("Error in method VariableDataFieldAlterData:getCriteria.  The value has not been set yet.");

		return $this->_criteria;
	}
	
	
	// Returns true if all of the Data Manipulator fields are empty.

	// In which case so need to save the Variable Data alteration out into XML if there is nothing to be substituted.
	function checkIfDataAlterationsAreEmpty(){
		
		if(empty($this->_dataAddBefore) && empty($this->_dataAddAfter) && empty($this->_dataRemoveBefore) && empty($this->_dataRemoveAfter))
			return true;
		else
			return false;
	}


	// Set Methods
	function setDataAddBefore($data, $addTo = false){

		$data = $this->filterData($data);
		
		if($addTo)
			$this->_dataAddBefore .= $data;
		else
			$this->_dataAddBefore = $data;
			
	}
	function setDataAddAfter($data, $addTo = false){
		
		$data = $this->filterData($data);
		
		if($addTo)
			$this->_dataAddAfter .= $data;
		else
			$this->_dataAddAfter = $data;
			
	}
	function setDataRemoveBefore($data, $addTo = false){

		$data = $this->filterData($data);
		
		if($addTo)
			$this->_dataRemoveBefore .= $data;
		else
			$this->_dataRemoveBefore = $data;
			
	}
	function setDataRemoveAfter($data, $addTo = false){

		$data = $this->filterData($data);
		
		if($addTo)
			$this->_dataRemoveAfter .= $data;
		else
			$this->_dataRemoveAfter = $data;
	}
	
	
	// Get Methods
	function getDataAddBefore(){

		return $this->_dataAddBefore;
	}
	function getDataAddAfter(){

		return $this->_dataAddAfter;
	}
	function getDataRemoveBefore(){

		return $this->_dataRemoveBefore;
	}
	function getDataRemoveAfter(){

		return $this->_dataRemoveAfter;
	}

	private function filterData($data){
		
		$data = preg_replace("/</", "", $data);
		$data = preg_replace("/>/", "", $data);
		$data = preg_replace("/&/", "", $data);
		$data = preg_replace("/;/", "", $data);
		return $data;
	}
}

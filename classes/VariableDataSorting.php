<?

// This class is responsible storing the sorting sequence of multiple Variable Data projects... especially if the sorting is co-mingled.


class VariableDataSorting {

	// These are parallell arrays.
	private $_projectIDarr = array();
	private $_dataLineNumArr = array();


	// Constructor
	function VariableDataSorting(){
		$this->_projectIDarr = array();
		$this->_dataLineNumArr = array();
	}
	
	function addRecord($projectNumber, $lineNumber){
	
		if(!preg_match("/^\d+$/", $projectNumber))
			throw new Exception("The project number must be a digit in Class VariableDataSorting");
			
		if(!preg_match("/^\d+$/", $lineNumber))
			throw new Exception("The Line Number must be a digit in Class VariableDataSorting");
		
		$this->_projectIDarr[] = $projectNumber;
		$this->_dataLineNumArr[] = $lineNumber;
	
	}
	
	
	function getTotalRows(){
		return sizeof($this->_projectIDarr);
	}
	
	// $rowNum is 1 based.
	function getProjectIDAtRowNum($rowNum){

		if(!preg_match("/^\d+$/", $rowNum))
			throw new Exception("The Row Number must be a digit in VariableDataSorting->getProjectIDAtRowNum");
	
		if(!isset($this->_projectIDarr[($rowNum - 1)]))
			throw new Exception("The Row Number has not been defined in VariableDataSorting->getProjectIDAtRowNum");
		
		return $this->_projectIDarr[($rowNum - 1)];
	}
	
	// $rowNum is 1 based.
	function getDataLineNumberAtRowNum($rowNum){

		if(!preg_match("/^\d+$/", $rowNum))
			throw new Exception("The Row Number must be a digit in VariableDataSorting->getDataLineNumberAtRowNum");
	
		if(!isset($this->_dataLineNumArr[($rowNum - 1)]))
			throw new Exception("The Row Number has not been defined in VariableDataSorting->getDataLineNumberAtRowNum");
		
		return $this->_dataLineNumArr[($rowNum - 1)];
	}
	
	// Returns a unique list of all project ID's in this object, sorted from lowest to highest.
	function getUniqueProjectIDarr(){
		
		$retArr = array_unique($this->_projectIDarr);
		sort($retArr);
		
		return $retArr;
	}
	
	


	
}
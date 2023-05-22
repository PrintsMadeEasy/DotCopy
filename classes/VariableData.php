<?

// This class is responsible for taking input for Variable data.
// Either from a TextArea or an Excel File
// Parses the file, checks for errors... and is able to return an 2D array with all data

class VariableData {

	private $_errorFlag;
	private $_errorMessageShort;
	private $_errorMessageLong;
	private $_errorLine = 0;
	private $_lineItemsArr = array(); // a 2D array


	// Constructor
	function VariableData(){

		$this->_errorFlag = true;
		$this->_errorMessageShort = "Variable Data has not been loaded yet.";
		$this->_errorMessageLong = $this->_errorMessageShort;
	}
	
	// Returns true or false depending on whether there are errors with parsing the file
	// If false, call the getErrorMessage method.
	// Pass in an Integer for "enforceColumnsNumber" if you want to ensure that there is always a column position allocated... even if it doesn't exist in the original data file.   This is useful for unmapped data fields that we suddenly want to map data to.
	function loadDataByTextFile($dataFile, $enforceColumnsNumber = null){
	
		if(!empty($enforceColumnsNumber)){
			if(!preg_match("/^\d+$/", $enforceColumnsNumber))
				throw new Exception("Error in method VariableData->loadDataByTextFile.  The column number is not an integer");
			if($enforceColumnsNumber > 100)
				throw new Exception("Error in method VariableData->loadDataByTextFile.  The column number can not be greater than 100.");
		}
		
	
		if(empty($dataFile)){
			$this->_errorFlag = true;
			$this->_errorMessageShort = "No Data File has been imported yet.";
			$this->_errorMessageLong = $this->_errorMessageShort;
			return false;
		}
	
		$linesArr = split("\n", $dataFile);
	
		$currentColumnSize = 0;
		
		$lineCount = 1;
		foreach($linesArr as $thisLine){
		
			$thisLine = trim($thisLine);
			if(empty($thisLine))
				continue;
			
			$elementsArr = split("\^", $thisLine);
			
			
			// If we have passed in an enforceColumns value... then make sure that the size of the elements array meets those requirements.
			if(!empty($enforceColumnsNumber)){
			
				if(sizeof($elementsArr) < $enforceColumnsNumber){
				
					$currentColSize = sizeof($elementsArr);
					
					for($i = $currentColSize; $i < $enforceColumnsNumber; $i++)
						$elementsArr[$i] = "";
				}
			}
			
			
			$columnCount = sizeof($elementsArr);
			
			if($currentColumnSize == 0)
				$currentColumnSize = $columnCount;
			
			
			// This shouldn't really happen any more
			// Now the Uploading script will auatomatically add blank columns to match the maximum column count for all of the rows
			// There could be some old data files with unequal column counts stored in the DB.... 
			// .... it could also flag an unknown bug... so leave the column count check in for integrity.
			if($columnCount != $currentColumnSize){
			
				$this->_errorLine = $lineCount;
				
				$this->_errorFlag = true;
				
				$this->_errorMessageShort = "There is a problem on line #". $lineCount . " of your Data File";
				
				$this->_errorMessageLong = "This line has " . $columnCount . " column" . LanguageBase::GetPluralSuffix($columnCount, "", "s") . " and the number of columns in all preceding ";
				$this->_errorMessageLong .= "lines is " . $currentColumnSize . ".<br>The number of columns must be consistent throughout the entire data file.";
				$this->_errorMessageLong .= "<br><br>The line reads...<br><font class='SmallBody'><b>" . WebUtil::htmlOutput($thisLine) . "<b></font>";
				
				return false;
			}
			
			$this->_lineItemsArr[] = $elementsArr;
			$lineCount ++;
		}
		
		if(sizeof($this->_lineItemsArr) == 0){
		
			$this->_errorFlag = true;
			$this->_errorMessageShort = "The data file is empty.";
			$this->_errorMessageLong = $this->_errorMessageShort;
			return false;
		}
		
		$this->_errorFlag = false;
		$this->_errorMessageShort = "";
		$this->_errorMessageLong = "";
		return true;
	}


	// Pass in the path to a CSV temporary file on disk... be sure to remove the file after using this method.
	// Returns true or false depending on whether there are errors with parsing the file
	// If false, call the getErrorMessage method.
	function loadDataByCSVfile($csvTempFile){
	
		if(!file_exists($csvTempFile)){
			$this->_errorFlag = true;
			$this->_errorMessageShort = "No Data File has been imported yet.";
			$this->_errorMessageLong = $this->_errorMessageShort;
			return false;
		}


		$csv = new ParseCSV($csvTempFile);
		$csv->SkipEmptyRows(TRUE); // Will skip empty rows. TRUE by default. (Shown here for example only).
		$csv->TrimFields(TRUE); // Remove leading and trailing \s and \t. TRUE by default.

		$lineCount = 1;

		while ($elementsArr = $csv->NextLine()){
			$this->_lineItemsArr[] = $elementsArr;
			$lineCount ++;
		}
		
		if(sizeof($this->_lineItemsArr) == 0){
			$this->_errorFlag = true;
			$this->_errorMessageShort = "The data file is empty.";
			$this->_errorMessageLong = $this->_errorMessageShort;
			return false;
		}
		
		$this->_errorFlag = false;
		$this->_errorMessageShort = "";
		$this->_errorMessageLong = "";
		return true;
	}


	// Pass in a path to an Excel file on disk
	// will return false on an error, use getErrorMessageLong if so
	// Will populate contents of file in memory... you can output it in our data format with getVariableDataText()
	function loadDataByExcelFile($excel_file){

		
		$exc = new ExcelFileParser();
		
		$res = $exc->ParseFromFile($excel_file);
	
		$errorMessage = "";

		switch ($res) {
			case 0: break;
			case 1: $errorMessage = "Can't open file";
			case 2: $errorMessage = "File too small to be an Excel file";
			case 3: $errorMessage = "Error reading file header";
			case 4: $errorMessage = "Error reading file";
			case 5: $errorMessage = "This is not an Excel file or file stored in Excel < 5.0";
			case 6: $errorMessage = "File corrupted";
			case 7: $errorMessage = "No Excel data found in file";
			case 8: $errorMessage = "Unsupported file version.  Try saving to an older format of Excel of convert your document to CSV.";

			default:
				$errorMessage = "An unknown error has occured.";
		}
		
		
		if(!empty($errorMessage)){
		
			$this->_errorFlag = true;
			$this->_errorMessageShort = $errorMessage;
			$this->_errorMessageLong = $errorMessage;
			return false;
		}
		
		
		if(count($exc->worksheet['name']) == 0){
			$this->_errorFlag = true;
			$this->_errorMessageShort = "There are no worksheets within the Excel file.";
			$this->_errorMessageLong = $this->_errorMessageShort;
			return false;
		}
		
		

		// We are only going to process the first worksheet (0);
		$ws = $exc->worksheet['data'][0];

		if( !is_array($ws) || !isset($ws['max_row']) || !isset($ws['max_col']) ){
		
			$this->_errorFlag = true;
			$this->_errorMessageShort = "There is no information on the first Worksheet.";
			$this->_errorMessageLong = $this->_errorMessageShort;
			return false;
		}
		 

		
		
		// Process Excel File into memory
		// If the sheet is empty or another error occurs, our Internal array will already be wiped out.
		$this->_lineItemsArr = array();

		$rowCounter = -1;

		$numberOfRows = $ws['max_row'];
		$numberOfColumns = $ws['max_col'];

		 for( $i=0; $i <= $numberOfRows; $i++ ) {

			// Skip blank rows
		  	if(!isset($ws['cell'][$i]) || !is_array($ws['cell'][$i]) ) 
		  		continue;
		  	
			$rowCounter++;

			for( $j=0; $j<= $numberOfColumns; $j++ ) {

				// Check for an empty Cell
				if( !isset($ws['cell'][$i][$j]) ){
					$this->_lineItemsArr[$rowCounter][$j] = "";
					continue;
				}

				$data = $ws['cell'][$i][$j];

				switch ($data['type']) {
					// string
					case 0:
						$ind = $data['data'];
						
						if(!isset($exc->sst['unicode']) || !isset($exc->sst['data']) || !isset($exc->sst['data'][$ind])){
							$this->_lineItemsArr[$rowCounter][$j] = "";
							break;
						}
						
						if( $exc->sst['unicode'][$ind] )
							$s = WebUtil::FilterData($exc->sst['data'][$ind], FILTER_SANITIZE_STRING_ONE_LINE);
						else
							$s = $exc->sst['data'][$ind];
							
						$this->_lineItemsArr[$rowCounter][$j] = $s;
						
						break;
					// integer number
					case 1:
						$this->_lineItemsArr[$rowCounter][$j] = $data['data'];
						break;
					// float number
					case 2:
						$this->_lineItemsArr[$rowCounter][$j] = $data['data'];
						break;
					// date
					case 3:

						$ret = $data['data'];//str_replace ( " 00:00:00", "", gmdate("d-m-Y H:i:s",$exc->xls2tstamp($data[data])) );
						$this->_lineItemsArr[$rowCounter][$j] = $ret;
						break;
					case 4: //string
						break;
					case 5: //hlink	

						$this->_lineItemsArr[$rowCounter][$j] = WebUtil::FilterData($data['data'], FILTER_SANITIZE_STRING_ONE_LINE);

						break;

					default:
						// An unknown Data Type in the file
						$this->_lineItemsArr[$rowCounter][$j] = "";
						break;
				}
				
				// Make sure to remove any Carrot symbols.  They are a special character to this program
				$this->_lineItemsArr[$rowCounter][$j] = preg_replace("/\^/", "", $this->_lineItemsArr[$rowCounter][$j]);
				
				// Remove any Cariage Returns
				$this->_lineItemsArr[$rowCounter][$j] = preg_replace("/\r/", "", $this->_lineItemsArr[$rowCounter][$j]);
				
				// Convert Line breaks into a special sybmol for line breaks that we use ... !br!
				$this->_lineItemsArr[$rowCounter][$j] = preg_replace("/\n/", "!br!", $this->_lineItemsArr[$rowCounter][$j]);

			}
		
		}



		$this->_errorFlag = false;
		$this->_errorMessageShort = "";
		$this->_errorMessageLong = "";
		return true;

	}
	

	
	// Returns the number of fields (or columns) for the Variable Data import.
	function getNumberOfFields(){
		
		if($this->_errorFlag)
			throw new Exception("There can not be any errors when calling the method getNumberOfFields");
		
		return sizeof($this->_lineItemsArr[0]);
	}
	
	// How many projects will be generated from the variable data
	function getNumberOfLineItems(){
	
		if($this->_errorFlag)
			throw new Exception("There can not be any errors when calling the method getVariableDataArr");
		
		return sizeof($this->_lineItemsArr);
	}
	
	function getErrorMessageShort(){
	
		if(!$this->_errorFlag)
			throw new Exception("If there are no errors, then you can not get an error message");
			
		return $this->_errorMessageShort;
	}
	function getErrorMessageLong(){
	
		if(!$this->_errorFlag)
			throw new Exception("If there are no errors, then you can not get an error message");
			
		return $this->_errorMessageLong;
	}
	function getErrorLine(){
		if(!$this->_errorFlag)
			throw new Exception("If there are no errors, then you can not get a Line Number");
			
		return $this->_errorLine;
	}
	
	// Returns a 2D array, both indexes are indexed by numbers starting from 0
	function getVariableDataArr(){
	
		if($this->_errorFlag)
			throw new Exception("There can not be any errors when calling the method getVariableDataArr");
			
		return $this->_lineItemsArr;
	}
	
	// fails if the lineNumber exceeds the total number of lines in the Data File
	// $lineNumber is Zero based
	function getVariableRowByLineNo($lineNumber){
	
		if($this->_errorFlag)
			throw new Exception("There can not be any errors when calling the method getVariableRowByLineNo");
		
		if($lineNumber >= sizeof($this->_lineItemsArr))
			throw new Exception("The method getVariableRowByLineNo was called with an illegal row.");
	
		return $this->_lineItemsArr[$lineNumber];
	}
	
	
	// fails if the lineNumber exceeds the total number of lines in the Data File
	// $lineNumber is Zero based
	// Column is Zero Based
	function getVariableElementByLineAndColumnNo($lineNumber, $colNum){

		if($this->_errorFlag)
			throw new Exception("There can not be any errors when calling the method getVariableRowByLineNo");
		
		if($lineNumber >= sizeof($this->_lineItemsArr))
			throw new Exception("The method getVariableElementByLineAndColumnNo was called with an illegal row.");
		
		// If the column is mapped out of range.. then just return a blank string.
		if(!isset($this->_lineItemsArr[$lineNumber][$colNum]))
			return "";
	
		return $this->_lineItemsArr[$lineNumber][$colNum];
	}
	
	
	// fails if the lineNumber exceeds the total number of lines in the Data File
	// $lineNumber is Zero based
	// Column is Zero Based
	function setVariableElementByLineAndColumnNo($lineNumber, $colNum, $dataValue){

		if($this->_errorFlag)
			throw new Exception("There can not be any errors when calling the method setVariableElementByLineAndColumnNo");
		
		if($lineNumber >= sizeof($this->_lineItemsArr))
			throw new Exception("The method setVariableElementByLineAndColumnNo was called with an illegal row.");
		
		if(!isset($this->_lineItemsArr[$lineNumber][$colNum]))
			throw new Exception("The method setVariableElementByLineAndColumnNo was called with an illegal column.");
	
		$this->_lineItemsArr[$lineNumber][$colNum] = $dataValue;
	}
	
	
	// Returns the Text to be stored in the Project
	// It should be saved to the Database and it can be reloaded again by calling the method loadDataByTextFile
	function getVariableDataText(){
	
		if($this->_errorFlag)
			throw new Exception("There can not be any errors with the Variable data when trying to retrieve its contents in the funtion getVariableDataText");
		
		$retStr = "";
		
		foreach($this->_lineItemsArr as $thisLine){
		
			foreach($thisLine as $thisElement)
				$retStr .= $thisElement . "^";
			
			// strip off the last carrot
			$retStr = substr($retStr, 0, -1);
			
			$retStr .= "\n";
		}
		
		
		return $retStr;
	}

	// Returns the variable data in CSV format
	function getCSVfile(){
	
		if($this->_errorFlag)
			throw new Exception("There can not be any errors with the Variable data when trying to retrieve its contents in the CSV file.");
		
		$retStr = "";
		
		foreach($this->_lineItemsArr as $thisLine){
		
			foreach($thisLine as $thisElement){
			
				// Fields with an embedded double quote must have the field surounded by double quotes and have all of the double quotes insided replaced by a pair of doubles
				// Fields with an embedded comma must be surrounded in double quotes.
				if(preg_match('/"/', $thisElement))
					$thisElement = '"' . preg_replace('/"/', '""', $thisElement) . '"';
				else if(preg_match("/,/", $thisElement))
					$thisElement = '"' . $thisElement . '"';
			
				$retStr .= $thisElement . ",";
			}
			
			// strip off the last comma
			$retStr = substr($retStr, 0, -1);
			
			$retStr .= "\n";
		}
		
		
		return $retStr;
	}

	
	function checkIfError(){
		return $this->_errorFlag;
	}
	
}
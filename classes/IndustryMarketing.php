<?


//error_reporting (E_ALL);

// This is class is meant to open an excel file containing "Keyword Matching" parameters to check against "Company Names or DBA's" in hopes to associate a particular business with an industry (category).
// The format of the Excel file should have 7 columns.
// 		Keyword1 ^^^^ Keyword 1 is Whole ^^^^ Keyword2 ^^^^ Keyword 2 is Whole ^^^^ Negative Keyword  ^^^^ SIC Code ^^^^ Industry Category Name to be Returned

// Anything inside of the "Keyword is Whole" column will constitute a whole word check.  Enter nothing (blank) if you don't want whole words matched.  Whole Words mean Keyword1/2 must be at the start/end of a line or have space/appostrophe/dash in front or imediately after.
// This system does not try to find plural matches.  That is up to you to define in the data file.  For example you may want "bar" to define a place you drink at.  You wouldn't want it to find a match on "bars" because that could mean someone who does iron-working or makes candy bars.
// ... You can leave off certain characters to help find the plural form (if the phrase is sufficiently unique) .... like "butterfl" since it could be "butterflies" or "butterfly"
// The Negative Keywords column may have commas separating multiple Negative keywords.
// Keyword1 and Keyword2 may not use commas to define multiple possible matches... for multiple words you must create multiple lines for each word match
// It is possible to have Keyword1, Keyword2, and Negative Keyword all null... and just find matches based upon SIC codes.... 
// But you can not have Keyword1, Keyword2, and the SIC code all left blank. 
// Lines at the top of the file have the highest priority... as this Class will return the First Match that it comes across.

class IndustryMarketing {


	var $_keywordListLoadedFlag;
	var $_lastCheckFlag;
	var $_lastErrorMessage;
	var $_lastIndustryCategory;
	var $_dataFileError;
	var $_lineItemsArr = array();
	
	
	
	###-- Constructor --###
	function IndustryMarketing(){
		
		$this->_keywordListLoadedFlag = false;
		
		$this->_lastCheckFlag = false;
		$this->_lastErrorMessage = "The Method checkCompanyDetails() has not been called yet.";
		$this->_lastIndustryCategory = "ErrorCategoryLoaded";
		$this->_dataFileError = "Data File has not been intialized yet.";

			
	}
	

	
	
	// Pass in an abolute path to an Excel file on disk.
	// It will parse the file and load the keyword list into memory.
	// Returns False if there is an error... Call the Method getDataFileError() to see what the problem is.
	function loadKeywordList($excel_file){
	
		$this->_keywordListLoadedFlag = false;
	
		
		$exc = new ExcelFileParser();

		$res = $exc->ParseFromFile($excel_file);
		
		//error_reporting (E_ALL);

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
			case 8: $errorMessage = "Unsupported file version";

			default:
				$errorMessage = "An unknown error has occured.";
		}
		

		if(!empty($errorMessage)){
			$this->_dataFileError = "Error opening the Excel file: " . $errorMessage;
			return false;
		}

		if(count($exc->worksheet['name']) == 0){
			$this->_dataFileError = "There are no worksheets within the Excel file.";
			return false;
		}
		

		// We are only going to process the first worksheet (0);
		$ws = $exc->worksheet['data'][0];

		if( !is_array($ws) || !isset($ws['max_row']) || !isset($ws['max_col']) ){
			$this->_dataFileError = "There is no information on the first Worksheet.";
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
						if( $exc->sst['unicode'][$ind] )
							$s = convertUnicodeString($exc->sst['data'][$ind]);
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
						$ret = $data[data];//str_replace ( " 00:00:00", "", gmdate("d-m-Y H:i:s",$exc->xls2tstamp($data[data])) );
						$this->_lineItemsArr[$rowCounter][$j] = $ret;
						break;
					case 4: //string
						break;
					case 5: //hlink	
						$this->_lineItemsArr[$rowCounter][$j] = convertUnicodeString($data['data']);
						break;
					default:
						// An unknown Data Type in the file
						$this->_lineItemsArr[$rowCounter][$j] = "";
						break;
				}
				
				
				
				// Remove extra white space/line breaks at the beggining/end data
				$this->_lineItemsArr[$rowCounter][$j] = trim($this->_lineItemsArr[$rowCounter][$j]);
			}
			
			
			$keyword1 = $this->_lineItemsArr[$rowCounter][0];
			$keyword2 = $this->_lineItemsArr[$rowCounter][2];
			$sicCode = $this->_lineItemsArr[$rowCounter][5];
			$categoryName = $this->_lineItemsArr[$rowCounter][6];
			
			if($rowCounter > 0 && !empty($sicCode) && strlen($sicCode) < 4){
				$this->_dataFileError = "Error parsing Row #" . ($rowCounter+1) . ". if the SIC code is not blank then it must be at least 4 characters long.";
				return false;
			}
			
			if(empty($categoryName)){
				$this->_dataFileError = "Error parsing Row #" . ($rowCounter+1) . ". Column G (Category) can not be left blank.";
				return false;
			}
				
			if(empty($keyword1) && empty($keyword2) && empty($sicCode)){
				$this->_dataFileError = "Error parsing Row #" . ($rowCounter+1) . ". Keyword1, Keyword2, and the SIC code are all blank. At least one of them must have a value.";
				return false;
			}
		
		}
	
		$headerLineError = "";
		
		// We are going to check the header line.  This is to make sure that the format of the Excel File is what we are anticipating.
		if(strtoupper($this->_lineItemsArr[0][0]) != "KEYWORD1")
			$headerLineError = "Error with the data format. The header row on Colum1 should read 'Keyword1'.";
		if(strtoupper($this->_lineItemsArr[0][1]) != "WHOLE1")
			$headerLineError = "Error with the data format. The header row on Colum2 should read 'Whole1'.";
		if(strtoupper($this->_lineItemsArr[0][2]) != "KEYWORD2")
			$headerLineError = "Error with the data format. The header row on Colum3 should read 'Keyword2'.";
		if(strtoupper($this->_lineItemsArr[0][3]) != "WHOLE2")
			$headerLineError = "Error with the data format. The header row on Colum4 should read 'Whole2'.";
		if(strtoupper($this->_lineItemsArr[0][4]) != "NEGATIVE")
			$headerLineError = "Error with the data format. The header row on Colum5 should read 'Negative'.";
		if(strtoupper($this->_lineItemsArr[0][5]) != "SIC")
			$headerLineError = "Error with the data format. The header row on Colum6 should read 'SIC'.";
		if(strtoupper($this->_lineItemsArr[0][6]) != "CATEGORY")
			$headerLineError = "Error with the data format. The header row on Colum7 should read 'Category'.";
		
		if(!empty($headerLineError)){
			$this->_dataFileError = $headerLineError;
			return false;
		}
	
		$this->_dataFileError = "Data File was last loaded OK.";
		$this->_keywordListLoadedFlag = true;
		return true;
	
	}
	
	// If there was a problem loading the Excel File, then this will contain an error message explaning what the problem is.

	function getDataFileError(){
		return $this->_dataFileError;
	}
	
	
	
	// Pass in a company name and possibly an SIC code
	// It will return TRUE or FALSE depending on whether a match was found.
	// Call the method "getIndustryCategory" name or "getErrorMessage" subsequently after calling this method
	// If a keyword match is found on the Company Name (and there is not an SIC code specified in the Data File for that "keyword check")... 
	// ... this method will find a match no matter what SIC code is passed in here.
	function checkCompanyDetails($companyName, $sicCode = null){
	
		if(!$this->_keywordListLoadedFlag)
			exit("You can not call the method checkCompanyDetails() until a the Data File has been successfully loaded.");
			
		$companyName = trim($companyName);
		$sicCode = trim($sicCode);
		
		if(empty($companyName) && empty($sicCode)){
			$this->_lastIndustryCategory = "ErrorCategoryNotFound";
			$this->_lastErrorMessage = "The method checkCompanyDetails() was called with a blank Company Name and SIC code";
			$this->_lastCheckFlag = false;
			return false;
		}
		
		
		// Valid SIC codes are 2342 or 2342-34   // SIC codes may have a 2 digit extention
		if(!empty($sicCode) && !preg_match("/^\d{3,4}(\-\d{2})?$/", $sicCode)){
			$this->_lastIndustryCategory = "ErrorCategoryNotFound";
			$this->_lastErrorMessage = "An invalid SIC code was given: \"" . $sicCode . "\" for Company: " . $companyName;
			$this->_lastCheckFlag = false;
			return false;
		}
		
	
		// We are going to check if a company name exist within our Data File list.
		// Loop through every line in our Data File looking for a match.  Upon each line if we have something that "invalidates"... skip/continue to the next line.
		// Basically we are assuming the data is a match unless something invalidates it.
		
		foreach($this->_lineItemsArr as $thisLineArr){
			
			$keyword1 = preg_quote($thisLineArr[0]);
			$keyword1_IsWhole = (empty($thisLineArr[1]) ? false : true);
			$keyword2 = preg_quote($thisLineArr[2]);
			$keyword2_IsWhole = (empty($thisLineArr[3]) ? false : true);
			$negativeKeywords = preg_quote($thisLineArr[4]);
			$sicCodeFromDataFile = preg_quote($thisLineArr[5]);
			$categoryName = $thisLineArr[6];
		
			// We should never allow this to happen after parsing our Excel Data File.
			if(empty($keyword1) && empty($keyword2) && empty($sicCodeFromDataFile))
				continue;
			
			// WILDCARD is a special word that means "match anything" withing a regular expression
			$keyword1 = preg_replace("/WILDCARD/i", ".*", $keyword1);
			$keyword2 = preg_replace("/WILDCARD/i", ".*", $keyword2);
			
			if(!empty($keyword1)){
			
				// If the Keyword is meant to be whole... then the name must butt up against the start/end of the data line... or it must be next to a Space.
				if($keyword1_IsWhole){
					if(!preg_match("/(^|\s|-|_)" . $keyword1 . "($|\s|'|-|_)/i", $companyName))
						continue;
				}
				else{
					if(!preg_match("/" . $keyword1 . "/i", $companyName))
						continue;
				}
			}
			
			if(!empty($keyword2)){
				
				if($keyword2_IsWhole){
					if(!preg_match("/(^|\s|-|_)" . $keyword2 . "($|\s|'|-|_)/i", $companyName))
						continue;
				}
				else{
					if(!preg_match("/" . $keyword2 . "/i", $companyName))
						continue;
				}
			}
			
			// If a negative keword is found anywhere in the company name, then skip this line because it has been invalidated.
			if(!empty($negativeKeywords)){
				
				$negativeKeywordsArr = explode(",", $negativeKeywords);
				
				foreach($negativeKeywordsArr as $thisNegativeKeyword){
				
					$thisNegativeKeyword = trim($thisNegativeKeyword);
					$thisNegativeKeyword = preg_quote($thisNegativeKeyword);
					
					// Just in case someone put a comma after the last Negative Keyword.
					if(empty($thisNegativeKeyword))
						continue;
					
					// Doing a "continue 2" here ... meaning that we found a negative keyword match and we are going to skip to the next line number.
					if(preg_match("/" . $thisNegativeKeyword . "/i", $companyName))
						continue 2;
				
				}
			}
			
			// If the data file requires an SIC code for a match... but we don't know what the SIC code is for the company we are checking... then we can't use this line number.
			if(!empty($sicCodeFromDataFile) && empty($sicCode)){
				continue;
			}
			
			// In this case... the Data line requires an SIC code match...  and we know the SIC code of the company we are checking.
			if(!empty($sicCodeFromDataFile)){
				// SIC codes must be the ^entire$ data within the line. 
				if(!preg_match("/^" . $sicCode . "$/i", $sicCodeFromDataFile))
					continue;
			
			}
		
		
			// If we have gotten this far it means nothing has "invalidated" the current entry for the company name that we are checking... so therefore it is a match.
			$this->_lastIndustryCategory = $categoryName;
			$this->_lastErrorMessage = "There is an Error with the Error Message.  The last company check was OK.";
			$this->_lastCheckFlag = true;
			return true;
			
		}
		
		
		$this->_lastIndustryCategory = "ErrorCategoryNotFound";
		$this->_lastErrorMessage = "No Match:" . $companyName . (empty($sicCode) ? "" : (": SIC Code: " . $sicCode));
		$this->_lastCheckFlag = false;
		return false;
	
	}
	
	
	function getIndustryCategory(){
	
		if($this->_lastCheckFlag == false)
			exit("Can not call the method IndustryMarketing->getIndustryCategory if the last checkCompanyDetails has returned false.");
	
		return $this->_lastIndustryCategory;
	}

	function getErrorMessage(){
	
		if($this->_lastCheckFlag == true)
			exit("Can not call the method IndustryMarketing->getErrorMessage if the last checkCompanyDetails has returned true.");
			
		return $this->_lastErrorMessage;
	}

}

?>
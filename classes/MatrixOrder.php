<?

// This can be useful for us to specify how we want printed areas organized
// For example on Variable Data postcards we may want Slot 1 to start in the bottom left-hand corner... or maybe the top right.
// You might want to configure something like...
/*
	3 6 9             9  10 11 12
	2 5 8  or maybe   5   6  7  8
	1 4 7             1   2  3  4
*/
class MatrixOrder {


	private $_matrixInitializedFlag;

	private $_matrixArr = array();  // Will be a 2D array holding all of the positions.

	// Optionally takes a 2D array as the constructor.
	function MatrixOrder($initArr = null){
		
		$this->_matrixInitializedFlag = false;
		$this->_matrixArr = array();
		
		if($initArr)
			$this->setMatrix($initArr);
	}
	

	
	// Pass in a 2 Dimentional Array.
	// Using the first example at the top of this class you could pass in something like...
	// $arrayParameter = array(array(3,6,9), array(2,5,8), array(1,4,7));
	function setMatrix($xArr){
	
		$this->_matrixInitializedFlag = false;
	
		if(!is_array($xArr))
			throw new Exception("Error in Method setMatrix.  The parameter must be an array");
		if(empty($xArr))
			throw new Exception("Error in Method setMatrix.  The parameter must not contain an empty array.");
		
		$this->_matrixArr = $xArr;
		
		$this->initializeMatrix();
	}
	
	
	
	// You must call this method after setting up the positions
	// It will do error checking to make sure that no elements are missing/incorrect
	function initializeMatrix(){
	
		if(empty($this->_matrixArr))
			throw new Exception("Error in method initializeMatrix.  No elements yet.");
	
		$firstColSize = 0;
		for($i=0; $i<sizeof($this->_matrixArr); $i++){
			
			if(!is_array($this->_matrixArr[$i]))
				throw new Exception("Error in method initializeMatrix on row " . ($i+1) . ".  The second Dimension must also be an array.");

			if(empty($this->_matrixArr[$i]))
				throw new Exception("Error in method initializeMatrix.  The second Dimension must not be empty.");
		
			if($i==0)
				$firstColSize = sizeof($this->_matrixArr[$i]);
			
			if(sizeof($this->_matrixArr[$i]) != $firstColSize)
				throw new Exception("Error in method initializeMatrix.  Row " . ($i+1) . " does not match the column count of row 1.  The Matix must be 2 dimensional.");
		}
		
		$totalElements = sizeof($this->_matrixArr) * sizeof($this->_matrixArr[0]);
		
		// If we have 10 elements in the Matrix make sure that we have all 10 numbers specified... 1 through 10.
		$matrixCheckArr = array();
		
		for($x=1; $x <= $totalElements; $x++)
			$matrixCheckArr[$x] = false;
		

		for($i=0; $i < sizeof($this->_matrixArr); $i++){
		
			for($j=0; $j < sizeof($this->_matrixArr[$i]); $j++){
				
				if(!preg_match("/^\d+$/", $this->_matrixArr[$i][$j]))
					throw new Exception("Error in method initializeMatrix on Row:" . ($i+1) . " Col:" . ($j+1) . ". The value must be an integer.");
				
				$matrixCheckArr[($this->_matrixArr[$i][$j])] = true;
			}
		}
		
		for($x=1; $x <= $totalElements; $x++){
			if(!isset($matrixCheckArr[$x]) || $matrixCheckArr[$x] == false)
				throw new Exception("Error in Method initializeMatrix.  The total number of elements is: " . $totalElements . ".  However element number " . $x . " has not been set.");
		}

	
		$this->_matrixInitializedFlag = true;
	}
	
	// This is used for testing
	function isMatrixInitialized() {

		return $this->_matrixInitializedFlag;
	}
	
	
	// Example : If we are Matrix is setup with a 2D array... such as... array(array(4,8,12),array(3,7,11),array(2,6,10),array(1,5,9))
	//		Then this method will return a string like .... "(4,8,12)(3,7,11)(2,6,10)(1,5,9)"
	// This can be useful for saving the matrix state to the Database.
	function getMatrixDefinitionInStringFormat() {

		if(!$this->_matrixInitializedFlag)
			throw new Exception("Error in method getMatrixDefinitionInStringFormat. The matrix has not been initialized yet.");

		$arrString = "";

		for($row = 1; $row <= $this->getNumberRows(); $row++){

			$arrString  .= "(";

			for($col = 1; $col <= $this->getNumberColumns(); $col++)
				$arrString .= $this->getMatrixOrderValueAt($row, $col) . ",";		

			$arrString  = substr($arrString, 0, -1) . ")";
		}

		return $arrString;	
	}
	
	// Works almost in opposite of method getMatrixDefinitionInStringFormat().
	// Allows you to define the Matrix object by using a String.
	// This is good to restore a matrix object after the values were stored in a Database.
	// Pass in a string like... "(4,8,12)(3,7,11)(2,6,10)(1,5,9)"
	function setMatrixByStringFormat($arrString){
	
		$levelOneArr = array();

		$rowsArr = explode(")(", $arrString);
		foreach($rowsArr as $thisRowStr) {

			$secondDimArr = array();
			
			$valuesArr = explode(",",$thisRowStr);

			foreach($valuesArr as $digit){
				
				// Error check (to make sure there is a number), and at the same time extract the Digit (without trailing parenthesis, whitespaces, etc.)
				$matchDigitOnlyResults = array();
				if(!preg_match("/(\d+)/", $digit, $matchDigitOnlyResults))
					throw new Exception("Error in method setMatrixByStringFormat. One of the values is not numerical: " . $digit);
				
				array_push($secondDimArr,$matchDigitOnlyResults[1]);
			}

			array_push($levelOneArr,$secondDimArr);
		}
		
		$this->setMatrix($levelOneArr);
	}
	
	
	// The matrix is 2 dimensional... so it will return the product of the height/width
	function getTotalElements(){
		if(!$this->_matrixInitializedFlag)
			throw new Exception("The Matrix has to be initialized before calling the method getTotalElements");
			
		return sizeof($this->_matrixArr) * sizeof($this->_matrixArr[0]);
	
	}
	
	function getNumberRows(){
		if(!$this->_matrixInitializedFlag)
			throw new Exception("The Matrix has to be initialized before calling the method getNumberRows");
		
		return sizeof($this->_matrixArr);
	}
	
	function getNumberColumns(){
		if(!$this->_matrixInitializedFlag)
			throw new Exception("The Matrix has to be initialized before calling the method getNumberColumns");
		
		return sizeof($this->_matrixArr[0]);
	}
	
	
	// Pass in a Row, Column number and it will return the value that is assigned at that position
	// The Row and Col values are 1-based.
	// Will fail if the values are out of bounds.
	// If there are 2 columns and 4 rows, then this will returna  number between 1 & 8.
	function getMatrixOrderValueAt($row, $col){
		if(!$this->_matrixInitializedFlag)
			throw new Exception("The Matrix has to be initialized before calling the method getMatrixOrderValueAt");
		
		if(!preg_match("/^\d+$/", $row) || !preg_match("/^\d+$/", $col))
			throw new Exception("Error in method getMatrixOrderValueAt.  The row and column values must be integers.");
		
		$productOf = $row * $col;
		if($productOf > $this->getTotalElements() || $productOf < 1)
			throw new Exception("Error in method getMatrixOrderValueAt.  The row and column values are out of range. Total Elements: " . $this->getTotalElements()  . " Row: " . $row . " Col: " . $col);
		
		if(!isset($this->_matrixArr[($row-1)]) || !isset($this->_matrixArr[($row-1)][($col-1)]))
			throw new Exception("Error in method getMatrixOrderValueAt.  A value has not been defined for row: " . $row . " col: " . $col);
		
		return $this->_matrixArr[($row-1)][($col-1)];
	}
	
	
	// Returns the Row or Column Number that belongs to the given value
	// Will fail if the value has not been set within the matrix.
	// valueType must be a string "row" or "column"
	function getRowOrColumnFromValue($valueNumber, $valueType){

		if(!$this->_matrixInitializedFlag)
			throw new Exception("The Matrix has to be initialized before calling the method getRowOrColumnFromValue");

		if(!preg_match("/^\d+$/", $valueNumber))
			throw new Exception("Error in method getRowOrColumnFromValue.  The value must be an integer.");
			
		if($valueNumber > $this->getTotalElements())
			throw new Exception("Error in method getRowOrColumnFromValue.  The value number is greater than the total number of elements.");
		
		if($valueNumber == 0)
			throw new Exception("Error in method getRowOrColumnFromValue.  The value may not be Zero.");
		
		
		for($i=0; $i < sizeof($this->_matrixArr); $i++){
		
			for($j=0; $j < sizeof($this->_matrixArr[$i]); $j++){
				
				if($this->_matrixArr[$i][$j] == $valueNumber){
					
					if($valueType == "row")
						return $i + 1;
					else if($valueType == "column")
						return $j + 1;
					else
						throw new Exception("Illegal value type in method getRowOrColumnFromValue");
				}
			}
		}
		
		throw new Exception("Error in method getRowOrColumnFromValue");
		
	}
	
	
	function getRowNumberFromValue($valueNumber){
		return $this->getRowOrColumnFromValue($valueNumber, "row");
	}
	function getColumnNumberFromValue($valueNumber){
		return $this->getRowOrColumnFromValue($valueNumber, "column");
	}
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	
	// -----------------------------------   Sequencing Variables Below  --------------   
	// This helps detect paterns... for stuff numbered sequentially in rows/cols




	

	// Pass in a Row/Col position and this method will tell you how many positions will follow in sequence (before hitting a new row/column).

	// It will only look ahead for positions... such as 3 to 4 to 5.
	// Returns 0 if there is no more in sequence coming after the given row/col number.
	function getSequencedPositionsAhead($row, $col){
	
		$sequenceType = $this->checkIfPositionIsInSequence($row, $col);
		
		if($sequenceType == "row"){
		
			if($this->checkIfSequenceIsAscendingFromPosition($row, $col))
				return $this->getSequenceCountInRowAfterPosition($row, $col);
			else
				return $this->getSequenceCountInRowBeforePosition($row, $col);
		
		}
		else if($sequenceType == "column"){
		
			if($this->checkIfSequenceIsAscendingFromPosition($row, $col))
				return $this->getSequenceCountInColumnAfterPosition($row, $col);
			else
				return $this->getSequenceCountInColumnBeforePosition($row, $col);
	
		}
		else if($sequenceType == "none"){
			return 0;
		}
		else{
			throw new Exception("Error in method getSequencedPositionsAhead.  The Sequence Type is incorrect.");
		}	
	
	}

	
	// Check is there if there is a column or a row in common next to the given row/col position that is sequential.
	// Will look ahead and behind the current position and return a string "row", "column", or "none".
	// In case a number is in the middle  like the number 2 in the 4 piece matrix....
	// 	1, 4
	//	2, 3
	// The function will determine if there are more columns or rows in squence than the other... and return the greater of 2.
	// In the case of value #2 in this example... Since the number of columns and rows in sequence are equal... it would choose a "Column Sequence" because sequentially 1 comes before 2... 3 and 4 would be in their own sequential column.
	function checkIfPositionIsInSequence($row, $col){
		
		if(!$this->_matrixInitializedFlag)
			throw new Exception("The Matrix has to be initialized before calling the method checkIfPositionIsInSequence");
		
		$positionsBeforeInRow = $this->getSequenceCountInRowBeforePosition($row, $col);
		$positionsAfterInRow = $this->getSequenceCountInRowAfterPosition($row, $col);
		
		$positionsBeforeInCol = $this->getSequenceCountInColumnBeforePosition($row, $col);
		$positionsAfterInCol = $this->getSequenceCountInColumnAfterPosition($row, $col);

		
		if($positionsBeforeInRow == 0 && $positionsAfterInRow == 0 && $positionsBeforeInCol == 0 && $positionsAfterInCol == 0){
			return "none";
		}
		else if($positionsBeforeInRow != 0 && $positionsAfterInRow != 0){
			return "row";
		}
		else if($positionsBeforeInCol != 0 && $positionsAfterInCol != 0){
			return "column";
		}
		else if($positionsBeforeInRow != 0 || $positionsAfterInRow != 0){
		
			$rowCount = $positionsBeforeInRow + $positionsAfterInRow;
			$colCount = $positionsBeforeInCol + $positionsAfterInCol;
		
			// Find out if we have a column and a row competing for sequence.  This could only happen in a "corner".
			if($colCount != 0){
				
				if($rowCount > $colCount){
					// Since there are more sequential rows verses columns in this corner.
					return "row";
				}
				else if($rowCount < $colCount){
					return "column";
				}
				else{
					// The column and row count are equal, so find out which LineType (row or col) contains the value that is 1 less than current Positions's value.
					$currentMatrixValue = $this->getMatrixOrderValueAt($row, $col);
					
					if($currentMatrixValue == 1)
						throw new Exception("Some flaw of logic occured in method checkIfPositionIsInSequence.  Matix value #1 can not share a sequential row and a column");
						
					$precedingRowNum = $this->getRowOrColumnFromValue(($currentMatrixValue - 1), "row");
					$precedingColNum = $this->getRowOrColumnFromValue(($currentMatrixValue - 1), "column");
					
					if($precedingRowNum == ($currentMatrixValue - 1))
						return "row";
					else if($precedingColNum == ($currentMatrixValue - 1))
						return "column";
					else
						throw new Exception("A logic flaw happend in method checkIfPositionIsInSequence.");
				
				}
			}
			else{
				return "row";
			}
		
		}
		else if($positionsBeforeInCol != 0 || $positionsAfterInCol != 0){
			return "column";
		}
		else{
			return "none";
		}
	}
	
	
	

	// If the current Row or Column is in sequence it must be either ascending or descending.

	// We start counting from the Top-Left and then go right across the columns... moving down a row to the next line when the end is hit (like reading English).
	// 1,2,3,4  ... would be an "ascending row"... but "4,3,2,1" is descending.   Columns are the same way... Position Row:1 Col:1 is in the Top-Left corner... it is ascending if it is numbered vertically (lowest number on top).
	// Returns TRUE, or FALSE
	function checkIfSequenceIsAscendingFromPosition($row, $col){
	
		$sequenceType = $this->checkIfPositionIsInSequence($row, $col);
		$matrixValue = $this->getMatrixOrderValueAt($row, $col);
		
		if($sequenceType == "row"){
			
			$sequenceCountBefore = $this->getSequenceCountInRowBeforePosition($row, $col);
			$sequenceCountAfter = $this->getSequenceCountInRowAfterPosition($row, $col);
			
			if($sequenceCountBefore > 0){
			
				$thisMvalue = $this->getMatrixOrderValueAt(($row - 1), $col);
				
				if($thisMvalue < $matrixValue)
					return true;
				else
					return false;
			}
			else if($sequenceCountAfter > 0){
			
				$thisMvalue = $this->getMatrixOrderValueAt(($row + 1), $col);
				
				if($thisMvalue > $matrixValue)
					return true;
				else
					return false;
			}
			else{
				throw new Exception("Error with row in method checkIfSequenceIsAscendingFromPosition");
			}
		}
		else if($sequenceType == "column"){
		
			$sequenceCountBefore = $this->getSequenceCountInColumnBeforePosition($row, $col);
			$sequenceCountAfter = $this->getSequenceCountInColumnAfterPosition($row, $col);
			
			if($sequenceCountBefore > 0){
			
				$thisMvalue = $this->getMatrixOrderValueAt($row, ($col - 1));
				
				if($thisMvalue < $matrixValue)
					return true;
				else
					return false;
			}
			else if($sequenceCountAfter > 0){
			
				$thisMvalue = $this->getMatrixOrderValueAt($row, ($col + 1));
				
				if($thisMvalue > $matrixValue)
					return true;
				else
					return false;
			}
			else{
				throw new Exception("Error with column in method checkIfSequenceIsAscendingFromPosition");
			}
			
		}
		else if($sequenceType == "none"){
			throw new Exception("Error in method checkIfSequenceIsAscending.  You can not call this method unless the current position is in sequential row or column.");
		}
		else{
			throw new Exception("Error in method checkIfSequenceIsAscending because there is an undefined squence type.");
		}
	
	}
	
	
	// If the sequence follows a sequential line (in a row)... it will return the total number of elements in sequence within the row (before the current row position).
	// such  as ...   12, 3, 4, 5, 9  .... would return "2" for the value "5"... and return "1" for the value "4".   It would return 0 for 12, 3, and 9. 
	// It doesn't matter if the  Matrix values are increasing or decreasing... it simply returns the number of slots in front of or behind.... Row:1 Col:1 is always in the top-left corner.
	function getSequenceCountInRowBeforePosition($row, $col){
	
		if(!$this->_matrixInitializedFlag)
			throw new Exception("The Matrix has to be initialized before calling the method getSequenceCountInRowByPosition");
		
		$thisPosition = $this->getMatrixOrderValueAt($row, $col);
		
		$beforeCount = 0;
		$lastRowNum = $row;
		
		// Count backwards from the current position.
		while($lastRowNum > 1){
		
			$lastRowNum--;
		
			$xPos = $this->getMatrixOrderValueAt($lastRowNum, $col);
			
			if(abs($xPos - $thisPosition) == ($beforeCount + 1))
				$beforeCount++;
			else
				break;
		}
		
		return $beforeCount;
	}
	
	function getSequenceCountInRowAfterPosition($row, $col){
	
		if(!$this->_matrixInitializedFlag)
			throw new Exception("The Matrix has to be initialized before calling the method getSequenceCountInRowByPosition");
		
		$thisPosition = $this->getMatrixOrderValueAt($row, $col);
		
		$afterCount = 0;
		$lastRowNum = $row;
		
		// Count forward from the current position.
		while($lastRowNum < $this->getNumberRows()){
		
			$lastRowNum++;
		
			$xPos = $this->getMatrixOrderValueAt($lastRowNum, $col);
			
			if(abs($xPos - $thisPosition) == ($afterCount + 1))
				$afterCount++;
			else
				break;
		}
		
		return $afterCount;
	}
	function getSequenceCountInColumnBeforePosition($row, $col){
	
		if(!$this->_matrixInitializedFlag)
			throw new Exception("The Matrix has to be initialized before calling the method getSequenceCountInRowByPosition");
		
		$thisPosition = $this->getMatrixOrderValueAt($row, $col);
		
		$beforeCount = 0;
		$lastColNum = $col;
		
		// Count backwards from the current position.
		while($lastColNum > 1){
		
			$lastColNum--;
		
			$xPos = $this->getMatrixOrderValueAt($row, $lastColNum);
			
			if(abs($xPos - $thisPosition) == ($beforeCount + 1))
				$beforeCount++;
			else
				break;
		}
		
		return $beforeCount;
	}
	
	function getSequenceCountInColumnAfterPosition($row, $col){
	
		if(!$this->_matrixInitializedFlag)
			throw new Exception("The Matrix has to be initialized before calling the method getSequenceCountInRowByPosition");
		
		$thisPosition = $this->getMatrixOrderValueAt($row, $col);
		
		$afterCount = 0;
		$lastColNum = $col;
		
		// Count backwards from the current position.
		while($lastColNum < $this->getNumberColumns()){
		
			$lastColNum++;
		
			$xPos = $this->getMatrixOrderValueAt($row, $lastColNum);
			
			if(abs($xPos - $thisPosition) == ($afterCount + 1))
				$afterCount++;
			else
				break;
		}
		
		return $afterCount;
	}
	

	




}

?>
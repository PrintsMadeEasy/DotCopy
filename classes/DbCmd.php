<?php


//Database Command Class
class DbCmd {
	
	private $Connection;
	private $Result;
	private $QueryStr;
	
	//DbConnect object is passed on construction.
	// If you don't pass a DbConnection object then it will just use the default database for this system.
	function __construct(DbConnect $dbConnectObj = NULL) {

		 if($dbConnectObj == NULL)
		 	 $this->Connection = DbConnect::getDefaultConnection();
		 else
		 	$this->Connection = $dbConnectObj;
	}
	
	static function EscapeSQL($x) {
		return mysql_real_escape_string ( $x );
	}
	
	// Escapes SQL ... and ... adds slashes in front of the wildcard characters "%" and "_"
	static function EscapeLikeQuery($x) {
		
		$x = mysql_real_escape_string ( $x );
		$x = preg_replace("/%/", "\\%", $x);
		$x = preg_replace("/_/", "\\_", $x);
		
		return $x;
	}
	
	static function convertUnixTimeStmpToMysql($unixTimeStamp){
		return date("YmdHis", $unixTimeStamp);
		
	}
	
	//Execute passed query
	//Returns Result
	function Query($Query) {
		$this->QueryStr = $Query;
		return $this->FireSql();
	}
	
	// This Function will build and execute an Insert Query
	// $Fields is an associative array with
	// field names as index keys and the values are the insert values
	//Returns new ID
	function InsertQuery($TableName, array $Fields) {

		$this->QueryStr =& DbHelper::getInsertQueryStr($this->Connection->Handle, $TableName, $Fields);
		if ($this->FireSql())
			return mysql_insert_id ( $this->Connection->Handle );
			
		//Else error occurred - only return if duplicate key error occurred,
		//in which case return error string
		if ($this->GetLastErrorNum () == 1062)
			return "*DUPKEYERROR*"; 
		else
			throw new Exception("Error: Insert Query <BR>\n" . $this->GetLastErrorDesc () );
	}
	
	// This Function will build and execute an update Query
	// $Fields is an associative array with the
	// field names as index keys and the values are the insert values
	function UpdateQuery($TableName, array $Fields, $Qualifier) {
		
		$this->QueryStr =& DbHelper::getUpdateQueryStr($this->Connection->Handle, $TableName, $Fields, $Qualifier);
		$this->FireSql ();
	}
	
	//This function executes the setup query
	function FireSql() {
		
		$this->Result = mysql_query ( $this->QueryStr, $this->Connection->Handle );
		
		if ($this->Result == FALSE){
			$errorDesc = "Error: Query Failed<BR>\n";
			
			if(Constants::GetDevelopmentServer())
				$errorDesc .= $this->GetLastErrorDesc () . "\n\n<br><br>" . $this->QueryStr;
			
			throw new Exception($errorDesc);
		}
		return $this->Result;
	}
	
	// Get number of rows in current result set
	function GetNumRows() {
		return mysql_num_rows ( $this->Result );
	}
	
	// Position to Row in the current result set
	// If passed position not passed
	// RowNum should be between 0 and Total number of rows-1
	function SeekRow($RowNum) {
		if (! mysql_data_seek ( $this->Result, $RowNum ))
			throw new Exception("Error: Cannot seek to row $RowNum" . mysql_error ());
	}
	
	// Fetch the current row from the result set
	// Returns null if no more rows available
	// The row position is incremented following the fetch
	// Returns an associative array containing field names and values
	function GetRow() {
		return mysql_fetch_assoc ( $this->Result );
	}
	
	// Fetch the first value of the result set
	// The row position is incremented following the fetch
	// Returns the first value in the row
	// If there are no results it returns null
	function GetValue() {
		
		$result = mysql_fetch_array ( $this->Result );
		
		if (!isset($result[0]))
			return null; 
		else
			return $result[0];
	}
	
	// Similar to GetValue
	// However, if there are many rows it will return an array of all values in the first column.
	// If no rows exist, this will return an empty array.
	function GetValueArr(){
		
		$retArr = array();
		
		while($row = $this->GetRow())
			$retArr[] = current($row);
		
		return $retArr;
	}
	
	

	
	// Release the memory used by the SQL statment.
	function __destruct() {
		if($this->Result)
			@mysql_free_result ( $this->Result );
	}
	
	// Returns description of last error or empty string "" if no error occurred
	function GetLastErrorDesc() {
		return mysql_error ( $this->Connection->Handle );
	}
	
	// Returns number of last error or zero if no error occurred
	function GetLastErrorNum() {
		return mysql_errno ( $this->Connection->Handle );
	}
	

	
	//Format to DB datetime format
	static function FormatDBDateTime($timestamp) {
		return strftime ( "%Y-%m-%d %H:%M:%S", $timestamp );
	}

}


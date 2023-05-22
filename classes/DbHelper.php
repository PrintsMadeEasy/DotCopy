<?php

// This is Stuff that I could have put in DbCmd.
// however, someone may be creating many instances of DbCmd to fire off new queries from embedded objects in a loop.
// There is no point in embedding all of the functionality in DbCmd and causing long contruction times with Memory usage
// ... when the DbHelper can do it all from static methods.
class DbHelper {


    // A private constructor; prevents direct creation of object
    private function __construct() 
    {
        echo 'Do not construct. Only Static Methods here';
    }

	// Function encases value depending on type
	// If type is a string value, function puts quotes around it
	// Returns string
	// Function converts time and datetime to internal formats,
	// Also accepts timestamp values for date, time and datetime
	// Types supported are as listed
	// Pass by Reference... returns a copy of the variable by reference
	static function &QuoteValue($type, &$value) {
		if ($value === NULL || $value === "") {
			$returnValue = "NULL";
			return $returnValue;
		}
		
		$returnValue = null;
		
		switch ( $type) {
			
			// For dates... try to determine if this is in Mysql Date format or a UnixTimestamp
			// An integer 14 digits long is probably in mysql format like ... '20060628024527'
			// A unix timestamp will never go over 11 digits long (after 2040 or whenever) like in 2006 the current time is 1151487927
			

			case "datetime" :
			case "timestamp" :
				
				if (preg_match ( "/^\d{14}$/", $value )){
					$returnValue = "'" . $value . "'"; 
				}
				else if (preg_match ( "/^\d{10,11}$/", $value )){
					$returnValue = "'" . date ( "YmdHis", $value ) . "'"; 
				}
				else {
					// Convert from Unix time stamp to Mysql format.
					if (preg_match ( "/^\d{4}\-\d{2}\-\d{2}\s\d{2}:\d{2}:\d{2}$/", $value ))
						$returnValue = "'" . $value . "'"; 
					else
						throw new Exception("Error inserting DateTime or TimeStamp data type into the Database. It is not a valid date." );
				}
			
				break;
			
			case "date" :
				
				if (preg_match ( "/^\d{14}$/", $value ) || preg_match ( "/^\d{8}$/", $value )){
					$returnValue = "'" . $value . "'"; 
				}
				else if (preg_match ( "/^\d{10,11}$/", $value )){
					$returnValue = "'" . date ( "Ymd", $value ) . "'"; 
				}
				else {
					// Convert from Unix time stamp to Mysql format.
					if (preg_match ( "/^\d{4}\-\d{2}\-\d{2}$/", $value ))
						$returnValue = "'" . $value . "'"; 
					else
						throw new Exception("Error inserting Date data type into the Database. It is not a valid date: $value" );
				}
				
				break;
				
			case "time" :
				
				if (preg_match ( "/^\d{6}$/", $value )){
					$returnValue = "'" . $value . "'"; 
				}
				else if (preg_match ( "/^\d{10,11}$/", $value )){
					$returnValue = "'" . date ( "His", $value ) . "'"; 
				}
				else{
					// Convert from Unix time stamp to Mysql format.
					if (preg_match ( "/^\d{2}:\d{2}:\d{2}$/", $value ))
							$returnValue = "'" . $value . "'"; 
					else
						throw new Exception("Error inserting Time data type into the Database. It is not a valid date." );
				}
								
				break;
			
			case "string" :
			case "blob" :
			case "tinyblob" :
			case "mediumblob" :
			case "longblob" :	
			case "text" :
			case "tinytext" :	
			case "mediumtext" :
			case "longtext" :
			case "char" :
			case "varchar" :
				$returnValue = "'" . mysql_real_escape_string ( $value ) . "'";
				break;
			case "int" :
			case "integer" :
			case "tinyint" :
			case "mediumint" :
			case "bigint" :
			case "unsigned" :
				if (! preg_match ( "/^\-?\d{1,50}$/", $value ))
					throw new Exception("Error inserting Numeric data type into the Database. It is not a valid integer." );
				
				$returnValue = $value;
				break;
			case "real" :
			case "double" :
			case "float" :
				if (! preg_match ( "/^\-?\d{1,50}(\.\d{1,50})?$/", $value ))
					throw new Exception("Error inserting numeric data type into the Database. It is not a valid decimal number." );
				
				$returnValue = $value;
				break;
			default :
				throw new Exception("Undefined Type in Database Driver: $type : $value" );
		
		}
		
		return $returnValue;
	}
	
	//Get field types for table
	//Returns array with keys as field names, values as field types
	//Types are "string" "int", "timstampe", etc.
	static function &GetFieldTypes(&$dbConnection, $Table) {
		
		$field_names = array ();
		$columnsQuery = "SHOW COLUMNS FROM " . mysql_escape_string ( $Table );
		
		$res = mysql_query ( $columnsQuery, $dbConnection );
		if ($res == FALSE)
			throw new Exception("Error in method GetFieldTypes, the Table Name does not exist." );
		
		$fieldsArr = array ();
		while ( $fieldsArr = mysql_fetch_row ( $res ) ) {
			
			// Strip off any sizes... such as "int(11)" to just "int"
			$colTypeParts = explode ( "(", $fieldsArr [1] );
			$columnType = rtrim ( $colTypeParts [0], ")" );
			
			// The key is the column name... the value is the column type.
			$field_names [$fieldsArr [0]] = $columnType;
		}
		
		return $field_names;
	}
	
	
	// This Function will build a string for the Update Query and return it by reference.
	static function &getUpdateQueryStr(&$dbConnection, $TableName, array &$Fields, $Qualifier) {
		
		$FldTerms = "";
		$FieldInfo =& self::GetFieldTypes ($dbConnection, $TableName );
		
		for(reset ( $Fields ); $name = key ( $Fields ); next ( $Fields )) {
			if (! isset ( $FieldInfo [$name] ))
				throw new Exception("Error: Field $name can not be updated into the DB" );
			
			$escapedValue =& self::QuoteValue ($FieldInfo[$name], $Fields[$name]);
			$FldTerms .= $name . "=";
			$FldTerms .= $escapedValue . ",";
		}
		
		$FldTerms = rtrim ( $FldTerms, "," ); //Remove last comma
		
		$queryString = "UPDATE $TableName SET $FldTerms  WHERE $Qualifier";
		
		return $queryString;
	}
	
	
	// This Function will build and execute an Insert Query
	// $Fields is an associative array with
	// field names as index keys and the values are the insert values
	//Returns new ID
	static function &getInsertQueryStr(&$dbConnection, $TableName, array &$Fields) {
		
		$FldNames = "";
		$FldValues = "";
		$FieldInfo =& self::GetFieldTypes($dbConnection, $TableName );
		
		for(reset ( $Fields ); $name = key ( $Fields ); next ( $Fields )) {
			if (! isset ( $FieldInfo [$name] ))
				throw new Exception("Error: Field $name can not be inserted into the DB" );
			
			$escapedValue =& self::QuoteValue ( $FieldInfo [$name], $Fields [$name] );
			
			$FldNames .= $name . ",";
			$FldValues .= $escapedValue . ",";
		}
		
		$FldNames = rtrim ( $FldNames, "," ); //Remove last comma
		$FldValues = rtrim ( $FldValues, "," ); //Remove last comma
		

		$queryString = "INSERT INTO $TableName ($FldNames) VALUES ($FldValues)";
		
		return $queryString;
	}
	
	// And Pass in something like DBhelper::getOrClauseFromList("users.DomainID", array(1,2,3))
	// This method will return "(users.DomainID='1' OR users.DomainID='2' OR users.DomainID='3')
	// Returns Parantheses if the array is not empty.  Otherwise returns a blank string.
	static function getOrClauseFromArray($columnName, array $valuesArr){
		
		$retStr = "";
		
		foreach($valuesArr as $thisValue){
			if(!empty($retStr))
				$retStr .= " OR ";
				
			$retStr .= DbCmd::EscapeSQL($columnName) . '="'.DbCmd::EscapeSQL($thisValue).'"';
		}
		
		if(!empty($retStr))
			$retStr = " (" . $retStr . ") ";
		
		return $retStr;
	}
	
	// And Pass in something like DBhelper::getOrClauseFromList("users.DomainID", array(1,2,3))
	// This method will return "(users.DomainID != '1' AND users.DomainID != '2' AND users.DomainID != '3')
	// Returns Parantheses if the array is not empty.  Otherwise returns a blank string.
	static function getNegativeAndClauseFromArray($columnName, array $valuesArr){
		
		$retStr = "";
		
		foreach($valuesArr as $thisValue){
			if(!empty($retStr))
				$retStr .= " AND ";
				
			$retStr .= DbCmd::EscapeSQL($columnName) . ' != "'.DbCmd::EscapeSQL($thisValue).'"';
		}
		
		if(!empty($retStr))
			$retStr = " (" . $retStr . ") ";
		
		return $retStr;
	}
	
	// Converts an Array or Search queries to SQL.
	// Provide the Column Name... and a boolean flag depdending on whether it is an excusion or inclusion.
	static function getSQLfromSearchArr($searchArr, $SQLcolName, $inludeOrDontFlag) {
	
	
		$returnSQL = "";
	
		foreach($searchArr as $thisTrackingCodeSearch){
	
			$thisTrackingCodeSearch = DbCmd::EscapeLikeQuery($thisTrackingCodeSearch);
	
			// We use Astrics for wildcards in the BannerClick User
			$thisTrackingCodeSearch = preg_replace("/\*/", "%", $thisTrackingCodeSearch);
	
			// On the Second iteration of this loop... it will append OR to the last SQL clause
			if(!empty($returnSQL)){
				
				if($inludeOrDontFlag)
					$returnSQL .= " OR ";
				else
					$returnSQL .= " AND ";
	
			}
				
			// We don't want to search on just a wild card... because that could fail if the field is left null.
			if($thisTrackingCodeSearch != "%"){
				
				if($inludeOrDontFlag)
					$returnSQL .= $SQLcolName . " LIKE \"$thisTrackingCodeSearch\"";
				else
					$returnSQL .= $SQLcolName . " NOT LIKE \"$thisTrackingCodeSearch\"";
			}
			
		}
		
		if(!empty($returnSQL)){
			$returnSQL = "(" . $returnSQL . ")";
		}
		else{
			// Just in case someone typed in a bunch of empty pipe symbols... we dont' want the SQL to be empty.
			$returnSQL = "ID > 0";
		}
		
		return $returnSQL;
		
	
	}
	
}





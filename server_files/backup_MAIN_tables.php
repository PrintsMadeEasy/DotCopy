<?php

include 'db_login.php';

$link = mysql_connect (getDatabaseHost(), getUserName(), getPasword()); 
mysql_select_db (getDatabseName(), $link); 

$fileNameSuffix = "_" . date("M") . "-" . date("j") . "-" . date("y");

print "\n";
print "Mysql User: " . getUserName() . " Database Host: " . getDatabaseHost() . "\n-----------------\n\n";


$query = "SELECT RootTableName, TableNameWithSuffix FROM tablerotations"; 
$result = mysql_query ($query, $link); 

if (!$result) {
    $message  = 'Invalid query: ' . mysql_error() . "\n";
    $message .= 'Whole query: ' . $query;
    die($message);
}

$binarTables_RootNames = array();
$binarTables_TablesInUse = array();

while ($row = mysql_fetch_array($result)) {
	
	$binarTables_RootNames[] = $row[0];
	$binarTables_TablesInUse[] = $row[1];
}
mysql_free_result($result);


// --------   Now get a list of all Tables ------

$query = "SHOW TABLES"; 
$result = mysql_query ($query, $link); 

if (!$result) {
    $message  = 'Invalid query: ' . mysql_error() . "\n";
    $message .= 'Whole query: ' . $query;
    die($message);
}

$mainTableNames = array();

while ($row = mysql_fetch_array($result)) {
	
	$thisTableName =  $row[0];
	
	$dontBackupTheseTables = array("imagessession", "imagessession_2", "vectorimagessession", "imagesclean", "imagesdeleted", "vectorimagesclean", "vectorimagesdeleted");

	if(in_array($thisTableName, $dontBackupTheseTables))
		continue;


	
	// Find out if the current table name is a binary table.
	$tableIsBinary = false;
	
	foreach($binarTables_RootNames as $thisRootBinaryTable){
		
		// Make sure the table prefix is followed by either an underscore or a "end of line".
		// For example. "variableimages" or "variableimages_23", or "messageattachments_23"
		// However... we don't want to match "messageattachmentspointer"
		if(preg_match("/^".preg_quote($thisRootBinaryTable)."(_|$)/", $thisTableName)){
			$tableIsBinary = true;
			break;
		}
	}
	

	if(!$tableIsBinary){
	    $mainTableNames[] = $thisTableName;
	}
   
}
mysql_free_result($result);





print "\n\n\n";
print "Binary Tables\n---------------\n";
foreach ($binarTables_TablesInUse as $thisBinaryTableName){
	print $thisBinaryTableName . "\n";
}


print "\n\n\n";






// -------- Now lock the tables until we have finished dumping each table

$query = "FLUSH TABLES WITH READ LOCK"; 
$result = mysql_query ($query, $link); 

if (!$result) {
    $message  = 'Invalid query: ' . mysql_error() . "\n";
    $message .= 'Whole query: ' . $query;
    die($message);
}

print "Tables are locked ....\n\n";

print "Main Tables\n---------------\n";
$allMainTablenames = array();
foreach ($mainTableNames as $thisMainTableName){
	print $thisMainTableName . "\n";
	$fileNameRoot = getBackupDir(). "/" . $thisMainTableName . $fileNameSuffix . ".dump";
	$fileNameCompressed = $fileNameRoot . ".gz";
	system("mysqldump --opt -u ".getUserName()." -p".getPasword()." --tables --max_allowed_packet=100M ".getDatabseName()." $thisMainTableName  | gzip > $fileNameCompressed");
	
	$allMainTablenames[] = $fileNameCompressed;
	break;
}


$query = "UNLOCK TABLES"; 
$result = mysql_query ($query, $link); 

if (!$result) {
    $message  = 'Invalid query: ' . mysql_error() . "\n";
    $message .= 'Whole query: ' . $query;
    die($message);
}
print "Tables are un-locked ....\n";






?>
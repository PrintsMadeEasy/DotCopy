<?php
class TableRotations {
	
	// Calling this function will rotate binary tables if they get over the size limit
	// Pass in a Root Table name if you want to only check one specific table... otherwise it will check all of them.
	static function PossiblyRotateBinaryTables(DbCmd $dbCmd, $onlyCheckThisTable = ""){
	
	
	
		// ----------------------------------------------------  BEGIN SETUP ------------------------------------------------------------------------
	
		// Create a hash of all of the binary tables that may need to get rotated
		// We don't want the tables to get too big (since they hold binary data) or they will take too long to download and there are many applications that have a 2 gig file size limit still
		// The key to the array is the "root table name"... we will keep apending to the root table name in order to give our multiple revisions
		// 	For example... imagestemplate_2, imagestemplate_3, imagestemplate_4, imagessaved_2, imagessaved_4, etc.
		// You will see the variable name in brackets {TABLENUM} that will be replaced by whatever the next revision is.  It could be used 1 or more times within the table definitions.
		$TABLE_DEFINITIONS = array(
	
			"imagestemplate"=>"	CREATE TABLE IF NOT EXISTS imagestemplate_{TABLENUM} (
						ID int(11) NOT NULL AUTO_INCREMENT,
						BinaryData longblob,
						DateUploaded DATETIME,
						FileSize int(11),
						FileType varchar(100),
						PRIMARY KEY (ID)) 
						MAX_ROWS=4294967295 AVG_ROW_LENGTH = 350000
					",
	
			"imagessaved"=>"	CREATE TABLE IF NOT EXISTS imagessaved_{TABLENUM} (
						ID int(11) NOT NULL AUTO_INCREMENT,
						BinaryData longblob,
						DateUploaded DATETIME,
						FileSize int(11),
						FileType varchar(100),
						PRIMARY KEY (ID),
	
						INDEX imagessaved_{TABLENUM}_DateUploaded (DateUploaded)
						) 
						MAX_ROWS=4294967295 AVG_ROW_LENGTH = 350000
					",
					
	
			"vectorimagestemplate"=>"CREATE TABLE IF NOT EXISTS vectorimagestemplate_{TABLENUM} (
						ID int(11) NOT NULL AUTO_INCREMENT,
						PDFbinaryData longblob,
						OrigFormatBinaryData longblob,
						DateUploaded DATETIME,
						EmbeddedText text,
						OrigFileSize int(11),
						OrigFileType varchar(100),
						OrigFileName varchar(100),
						PDFfileSize int(11),
						PicasWidth varchar(20),
						PicasHeight varchar(20),
						PRIMARY KEY (ID),
	
						INDEX vectorimagestemplate_{TABLENUM}_DateUploaded (DateUploaded)
						MAX_ROWS=4294967295 AVG_ROW_LENGTH = 350000
					",
	
			"vectorimagessaved"=>"	CREATE TABLE IF NOT EXISTS vectorimagessaved_{TABLENUM} (
						ID int(11) NOT NULL AUTO_INCREMENT,
						PDFbinaryData longblob,
						OrigFormatBinaryData longblob,
						DateUploaded DATETIME,
						EmbeddedText text,
						OrigFileSize int(11),
						OrigFileType varchar(100),
						OrigFileName varchar(100),
						PDFfileSize int(11),
						PicasWidth varchar(20),
						PicasHeight varchar(20),
						PRIMARY KEY (ID),
	
						INDEX vectorimagessaved_{TABLENUM}_DateUploaded (DateUploaded)
						)
						MAX_ROWS=4294967295 AVG_ROW_LENGTH = 350000
					",
	
			"thumbnailimages"=>"	CREATE TABLE IF NOT EXISTS thumbnailimages_{TABLENUM} (
						ID int(11) NOT NULL AUTO_INCREMENT,
						ThumbImage blob,
						RefTableName varchar(50),
						RefTableID int(11),
						FileSize int(11),
						DateModified DATETIME,
						PRIMARY KEY (ID),
	
						INDEX thumbnailimages_{TABLENUM}_RefTableName (RefTableName),
						INDEX thumbnailimages_{TABLENUM}_RefTableID (RefTableID),
						INDEX thumbnailimages_{TABLENUM}_DateModified (DateModified)
						)
						MAX_ROWS=4294967295 AVG_ROW_LENGTH = 350000
					",
					
			"variableimages"=>"	CREATE TABLE IF NOT EXISTS variableimages_{TABLENUM} (
						ID int(11) NOT NULL AUTO_INCREMENT,
						BinaryData longblob,
						DateUploaded DATETIME,
						FileSize int(11),
						FileType varchar(100),
						PRIMARY KEY (ID),
	   
						INDEX variableimages_{TABLENUM}_DateUploaded (DateUploaded)
						)
						MAX_ROWS=4294967295 AVG_ROW_LENGTH = 350000	
					",
	
			"messagesattachments"=>" CREATE TABLE IF NOT EXISTS messagesattachments_{TABLENUM} (
					   ID int(11),
					   BinaryData longblob,
					   Filename varchar(100),
					   FileSize int(11),
					   DateAdded datetime,
					  
					   PRIMARY KEY (ID),
					   INDEX messagesattachment_DateAdded (DateAdded),
					   )
					   MAX_ROWS=4294967295 AVG_ROW_LENGTH = 350000	
				   ",
					
			"chatattachments"=>" CREATE TABLE IF NOT EXISTS chatattachments_{TABLENUM} (
					   ID int(11) NOT NULL AUTO_INCREMENT,
					   BinaryData longblob,
					   FileName varchar(70),
					   FileType varchar(50),
					   FileSize int(11),
					
					   PRIMARY KEY (ID)
					   )
					   MAX_ROWS=4294967295 AVG_ROW_LENGTH = 350000	
				   "			
		);

		
		// Define the Query for each table that will give us the file size. (minus the table name)
		$TABLE_SIZE_CHECKS_SQL = array(
						"imagestemplate" => "SELECT SUM(FileSize) ",
						"imagessaved" => "SELECT SUM(FileSize) ",
						"thumbnailimages" => "SELECT SUM(FileSize) ",
						"variableimages" => "SELECT SUM(FileSize) ",
						"vectorimagestemplate" => "SELECT SUM(PDFfileSize) + SUM(OrigFileSize) ",
						"vectorimagessaved" => "SELECT SUM(PDFfileSize) + SUM(OrigFileSize)  ",
						"chatattachments" => "SELECT SUM(FileSize)  ",
						"messagesattachments" => "SELECT SUM(FileSize) "
						);
		
		// Define the size Limit of Each table (in Megbytes) that will cause a table to make a rotation.
		// this is not exact because it doesn't know the size after the MySQL dump occurs.. containing Indexes, etc.
		$TABLE_SIZE_LIMIT = array(
						"imagestemplate"=>1000,
						"imagessaved"=>3500,
						"thumbnailimages"=>1800,
						"variableimages"=>1800,
						"vectorimagestemplate"=>1000,
						"vectorimagessaved"=>3500,
						"chatattachments"=>3500,
						"messagesattachments"=>3500
						);
		
		
		
		// ----------------------------------------------------  END SETUP ------------------------------------------------------------------------
		
		
		
		
		
		
		
		
		
		if($onlyCheckThisTable != ""){
		
			// Erase the table we created above and filter though all entries until we find the one we want
		
			$TableDefinitionsCopy = $TABLE_DEFINITIONS;
			$TABLE_DEFINITIONS = array();
			
			foreach($TableDefinitionsCopy as $rootTable => $tableDefinition ){
			
				if($rootTable == $onlyCheckThisTable)
					$TABLE_DEFINITIONS[$rootTable] = $tableDefinition;
			}
			
			if(empty($TABLE_DEFINITIONS)){
				
				WebUtil::WebmasterError("There was a problem filtering the table definitions in the fucntion TableRotations::PossiblyRotateBinaryTables.  The table name: $onlyCheckThisTable was not found.");
				throw new Exception("A problem with filtering the Table Definitions");
			}
		
		}
	
	
		print "Start Table Rotation Check";
	
	
		$tableListArr = array();
	
		$dbCmd->Query("SHOW TABLES");
		while($tblNam = $dbCmd->GetValue())
			$tableListArr[] = $tblNam;
	
	
	
		// Loop through all of the root table names and find out which table version is currently in use
		// Then find out if the table in Use is too large and needs to be rotated
		foreach($TABLE_DEFINITIONS as $rootTable => $tableDefinition ){
	
			print "Checking Root Table: $rootTable <br>";
			flush();
	
			$dbCmd->Query("SELECT TableNameWithSuffix FROM tablerotations WHERE RootTableName='$rootTable'");
	
			if($dbCmd->GetNumRows() == 0){
				$errMsg = "Error with server_table_rotations.php ... The Root table name does not exist: $rootTable";
				WebUtil::WebmasterError($errMsg);
				exit($errMsg);
			}
	
			$tableInUse = $dbCmd->GetValue();
	
			print "Table in use: $tableInUse <br>";
	
			
			if(!isset($TABLE_SIZE_CHECKS_SQL[$rootTable])){
				$errMsg = "Error with server_table_rotations.php ... The Root table was dot defined in the Size Checks SQL: $rootTable";
				WebUtil::WebmasterError($errMsg);
				exit($errMsg);
			}
			
			if(!isset($TABLE_SIZE_LIMIT[$rootTable])){
				$errMsg = "Error with server_table_rotations.php ... The Root table was dot defined in the Size LIMIT SQL: $rootTable";
				WebUtil::WebmasterError($errMsg);
				exit($errMsg);
			}
				
			
	
			$dbCmd->Query( $TABLE_SIZE_CHECKS_SQL[$rootTable] . " FROM $tableInUse");
			$currentTableSize = $dbCmd->GetValue();
	
			// Change number of bytes into megabytes 
			$currentTableSize = round($currentTableSize / 1024 / 1024);
	
	
			print "The Table Size for  Table: $rootTable is about $currentTableSize MB<br><br>";
			flush();
	
			if($currentTableSize > $TABLE_SIZE_LIMIT[$rootTable]){
	
				print "The Table $tableInUse needs to be rotated now.<br><br>";
				flush();
	
				WebUtil::SendEmail(Constants::GetMasterServerEmailName(), Constants::GetMasterServerEmailAddress(), "Webmaster", Constants::GetAdminEmail(), "Start Table Rotation: $tableInUse", "About to start the table rotation for $tableInUse .   The current size of the backup is approximately $currentTableSize MB.  Make sure you get a second email shortly (within a couple of hours) indicating that the backup procedure succeeded, otherwise you may need to investigate.");
	
				// Now start putting a suffixes to the end of the Root Table name and see if that table already exist
				// Keep going until we find a new table available.
				$currentSuffix = 2;
	
				while(true){
	
					$newTableName = $rootTable . "_" . $currentSuffix;
	
					if(!in_array($newTableName, $tableListArr))
						break;
					else
						$currentSuffix++;
				}
	
	
				// Substitue the number for the suffix into the variable for the table definition.
				$TableCreationSQL = preg_replace("/\{TABLENUM\}/", $currentSuffix, $tableDefinition);
	
				// Create the table within the DB
				$dbCmd->Query($TableCreationSQL);
	
	
				// Now update the Pointer table so we know where to start saving the new binary data to.
				$dbCmd->UpdateQuery("tablerotations", array("TableNameWithSuffix"=>$newTableName), "RootTableName='$rootTable'");
	
	
				// This will write to a small table... 99% of the time this table will be completely empty
				// Because of database replication, we will have access to this entry on the development server
				// That way the development server will know it has to finalize and protect the table.
				// The dev server will in-turn send an HTTP request to the live server when it is done with the backup, and then the entry within the table will be emptied.
				$dbCmd->InsertQuery("tablerotationbackups", array("TableName"=>$tableInUse));
	
			}
	
		}
	
	}
}

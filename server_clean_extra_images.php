<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


WebUtil::SendEmail(Constants::GetMasterServerEmailName(), Constants::GetMasterServerEmailAddress(), Constants::GetAdminName(), Constants::GetAdminEmail(), "Clean Images Starting", ("Starting to clean images. " . "\n" . date("F j, Y, g:i a")));


// Make this script be able to run for 8 hours.  As of 4/30/06 it is only taking about 15 mintues.
set_time_limit(28800);
ini_set("memory_limit", "700M");




// What image tables are we going to filter out 
// We don't want to filter any tables which have already been closed (or archieved)
$RasterImageCleanTablesArr = array("imagessession", ImageLib::GetImagesSavedTableName($dbCmd), ImageLib::GetImagesTemplateTableName($dbCmd));

$VectorImageCleanTablesArr = array("vectorimagessession", ImageLib::GetVectorImagesSavedTableName($dbCmd), ImageLib::GetVectorImagesTemplateTableName($dbCmd));



//  Script will clean out any images that are not found within all artwork files on the system
// It may take a long time to run.. but the script should never run out of memory because we are writing to a temporary table in the DB for all image ID's that we find



print "Cleaning out extra images... <br>please be patient.<br><br>";
flush();


// Get rid of all previous images moved to the deleted table.   If we have a problem we would know about it right away (within a day or 2)
// That would give us enough time to roll back changes from a previous DELETED Table...  So we don't need to keep multiple batches of Deleted images basically.
$dbCmd->Query("DELETE FROM imagesdeleted");
$dbCmd->Query("DELETE FROM vectorimagesdeleted");


// Get the MAX image pointer ID... We need a marker point... in case somebody uploads an image while this process is going on
$dbCmd->Query("SELECT MAX(ID) FROM imagepointer WHERE TableName !='imagesdeleted'");
$MaxRasterImagePointerID = $dbCmd->GetValue();
$dbCmd->Query("SELECT MAX(ID) FROM vectorimagepointer WHERE TableName !='vectorimagesdeleted'");
$MaxVectorImagePointerID = $dbCmd->GetValue();


$mysql_timestamp = date("YmdHis", time());

// Create a 2-D hash for setup... 
// It will be used to hold the table/columnNames of all artwork files on the server
// It is important to keep this syncronized with ALL artwork files.  If we leave a tablename/column out of here we could run into problems with missing images
$ArtworkLocationsArr = array();
$ArtworkLocationsArr[] = array("table"=>"projectsordered", "column"=>"ArtworkFile", "qualifier"=>"ID");
$ArtworkLocationsArr[] = array("table"=>"projectsordered", "column"=>"ArtworkFileModified", "qualifier"=>"ID");
$ArtworkLocationsArr[] = array("table"=>"projectssaved", "column"=>"ArtworkFile", "qualifier"=>"ID");
$ArtworkLocationsArr[] = array("table"=>"projectssession", "column"=>"ArtworkFile", "qualifier"=>"ID");
$ArtworkLocationsArr[] = array("table"=>"artworkstemplates", "column"=>"ArtFile", "qualifier"=>"ArtworkID");
$ArtworkLocationsArr[] = array("table"=>"artworksearchengine", "column"=>"ArtFile", "qualifier"=>"ID");



// This table is used to contain all images ID's we ever find within all artwork files on the system
// Clean it out because we are going to populate it in the loop below
$dbCmd->Query("DELETE FROM imagesclean");
$dbCmd->Query("DELETE FROM vectorimagesclean");

// Now we want to verify that all tables and columns exist
foreach($ArtworkLocationsArr as $locArr){

	// Verify that the current table exists
	$dbCmd->Query("SHOW TABLES");
	$tableFound = false;

	while($ThisTblNm = $dbCmd->GetValue()){
		if($locArr["table"] == $ThisTblNm)
			$tableFound = true;
	}
	
	if(!$tableFound){
		$ErrorMessage = "Clean Images is failing because a table does not match." . $locArr["table"];
		WebUtil::WebmasterError($ErrorMessage);
		exit;
	}
	
	// Verify that the current column exists
	$dbCmd->Query("DESC " . $locArr["table"]);
	$columnFound = false;
	while($ThisTblDescription = $dbCmd->GetRow()){
		if($locArr["column"] == $ThisTblDescription["Field"])
			$columnFound = true;
	}
	if(!$columnFound){
		$ErrorMessage = "Clean Images is failing because a column is missing." . $locArr["table"] . ":" . $locArr["column"];
		WebUtil::WebmasterError($ErrorMessage);
		exit;
	}


	$dbCmd->Query("DESC " . $locArr["table"]);
	$QualifierFound = false;
	while($ThisTblDescription = $dbCmd->GetRow()){
		if($locArr["qualifier"] == $ThisTblDescription["Field"])
			$QualifierFound = true;
	}
	if(!$QualifierFound){
		$ErrorMessage = "Clean Images is failing because a QualifierFound column is missing." . $locArr["table"] . ":" . $locArr["column"] . ":" . $locArr["qualifier"];
		WebUtil::WebmasterError($ErrorMessage);
		exit;
	}

	print "<font size='-4'><br><br>New Database location<br>Column: <b>" . $locArr["column"] . "</b> Table: <b>" . $locArr["table"] . "</b><br>";
	
	$counter = 0;
	
	//    Now Extraxt the ArtworkXML files
	$dbCmd->Query("SELECT " . $locArr["qualifier"] . ", " . $locArr["column"] . " FROM " . $locArr["table"] . " ");
	while($thisArworkFileEntry = $dbCmd->GetRow()){

		print "ID : " . $thisArworkFileEntry[$locArr["qualifier"]] . " : ";

		// Keep writing to the browser every once in a while to keep it alive.
		$counter++;
		if($counter >= 500){
			flush();
			$counter = 0;
		}

		$RasterImageIDarr = ArtworkLib::GetImageIDsFromArtwork($thisArworkFileEntry[$locArr["column"]]);
		
		
		foreach ($RasterImageIDarr as $ThisImageID) {
			//keep adding any images we find to the table
			$DBinsertArr["ImageID"] = $ThisImageID; 
			$dbCmd2->InsertQuery("imagesclean", $DBinsertArr);
		}
		
		
		$VectorImageIDarr = ImageLib::GetVectorImageIDsFromArtwork($thisArworkFileEntry[$locArr["column"]]);
		
		foreach ($VectorImageIDarr as $ThisImageID) {
			//keep adding any images we find to the table
			$DBinsertArr["ImageID"] = $ThisImageID; 
			$dbCmd2->InsertQuery("vectorimagesclean", $DBinsertArr);
		}
		
		
	}

}


// This should not happen... but just to be extra safe in case of a failure above
$dbCmd->Query("SELECT count(*) FROM imagesclean");
$RasterCleanedCount = $dbCmd->GetValue();
if($RasterCleanedCount == 0){
	$ErrorMessage = "Can not proceed because there does not seem to be any raster images to compare against";
	WebUtil::WebmasterError($ErrorMessage);
	exit;
}

$dbCmd->Query("SELECT count(*) FROM vectorimagesclean");
$VectorCleanedCount = $dbCmd->GetValue();
if($VectorCleanedCount == 0){
	$ErrorMessage = "Can not proceed because there does not seem to be any vector images to compare against";
	WebUtil::WebmasterError($ErrorMessage);
	exit;
}





// Restrict the following query to the tables that we want to filter
$RasterTableSearchSQL = "";
foreach($RasterImageCleanTablesArr as $ThisTable)
	$RasterTableSearchSQL .= " TableName = '$ThisTable' OR";
$RasterTableSearchSQL = substr($RasterTableSearchSQL, 0, -2);


// Restrict the following query to the tables that we want to filter
$VectorTableSearchSQL = "";
foreach($VectorImageCleanTablesArr as $ThisTable)
	$VectorTableSearchSQL .= " TableName = '$ThisTable' OR";
$VectorTableSearchSQL = substr($VectorTableSearchSQL, 0, -2);






// ----------------   BEGIN Raster Block  -----------------------
// ------------

$ExtraRasterImgIDArr = array();
$counter = 0;

// Do a nested query... we want to add to the array $ExtraRasterImgIDArr if we find an image in the pointer table that does not exist in any of the ImageID's found in the artwork files.
$dbCmd->Query("SELECT ID FROM imagepointer WHERE ID <= $MaxRasterImagePointerID AND ( $RasterTableSearchSQL ) ORDER BY ID ASC");
while($thisImgID = $dbCmd->GetValue()){

	$dbCmd2->Query("SELECT count(*) FROM imagesclean WHERE ImageID=$thisImgID");
	if($dbCmd2->GetValue() == 0)
		$ExtraRasterImgIDArr[]=$thisImgID;


	// Keep writing to the browser every once in a while to keep it alive.
	$counter++;
	if($counter >= 1000){
		print "<br>hit another 1000 image Raster IDs\n";
		flush();
		$counter = 0;
	}
}


if(!Constants::GetDevelopmentServer()){
	// Pop off the last 300 Extra ImageIDs.  There could be a case where they upload an image but don't save their artwork yet
	// This will give us some extra comfort room
	for($x=0; $x<300; $x++){
		//just in case there are not a lot of images yet
		if(sizeof($ExtraRasterImgIDArr) > 1)
			$nothing = array_pop($ExtraRasterImgIDArr);
	}
}



// ------------
// ----------------   END Raster Block  -----------------------














// ----------------   BEGIN Vector Block  -----------------------
// ------------
$ExtraVectorImgIDArr = array();
$counter = 0;

// Do a nested query... we want to add to the array $ExtraVectorImgIDArr if we find an image in the pointer table that does not exist in any of the ImageID's found in the artwork files.
$dbCmd->Query("SELECT ID FROM vectorimagepointer WHERE ID <= $MaxVectorImagePointerID AND ( $VectorTableSearchSQL ) ORDER BY ID ASC");
while($thisImgID = $dbCmd->GetValue()){

	$dbCmd2->Query("SELECT count(*) FROM vectorimagesclean WHERE ImageID=$thisImgID");
	if($dbCmd2->GetValue() == 0)
		$ExtraVectorImgIDArr[]=$thisImgID;


	// Keep writing to the browser every once in a while to keep it alive.
	$counter++;
	if($counter >= 1000){
		print "<br>hit another 1000 Vector image IDs\n";
		flush();
		$counter = 0;
	}
}


if(!Constants::GetDevelopmentServer()){
	// Pop off the last 300 Extra ImageIDs.  There could be a case where they upload an image but don't save their artwork yet
	// This will give us some extra comfort room
	for($x=0; $x<300; $x++){
		//just in case there are not a lot of images yet
		if(sizeof($ExtraVectorImgIDArr) > 1)
			$nothing = array_pop($ExtraVectorImgIDArr);
	}
}



// ------------
// ----------------   END Raster Block  -----------------------














// ----------------   BEGIN Raster Block  -----------------------
// ------------

print "<br><br>Extra Raster Images<hr>";
foreach($ExtraRasterImgIDArr as $thisID)
	print $thisID . " - "; 
print "<br><br>Removing Extra Raster Images<hr>";


foreach($ExtraRasterImgIDArr as $ThisExtraImageID){
	
	// Now Get all image ID's From the image pointer table.. unless the image record points to a deleted record
	$dbCmd->Query("SELECT TableName, Record FROM imagepointer WHERE ID=$ThisExtraImageID");
	$row = $dbCmd->GetRow();
	$thisOldTableName = $row["TableName"];
	$thisOldRecordID = $row["Record"];

	// Get Make sure that the imageID is not pointing to a record that does not exist.  
	// Should happen, but on my DEV server things were messy... and so was the beggining stages of the company
	$dbCmd->Query("SELECT count(*) FROM $thisOldTableName WHERE ID=$thisOldRecordID");
	$EntryCheck = $dbCmd->GetValue();


	if($EntryCheck == 1){
	
		// Get info from the old image so that we can move it into the deleted table
		$dbCmd->Query("SELECT * FROM $thisOldTableName WHERE ID=$thisOldRecordID");
		$OldImageInfo = $dbCmd->GetRow();
		
		$DeletedEntryInsertArr = array();
		$DeletedEntryInsertArr["BinaryData"] = $OldImageInfo["BinaryData"];
		$DeletedEntryInsertArr["FileSize"] = $OldImageInfo["FileSize"]; 
		$DeletedEntryInsertArr["FileType"] = $OldImageInfo["FileType"]; 
		$DeletedEntryInsertArr["DateDeleted"] = $mysql_timestamp; 
		$DeletedID = $dbCmd->InsertQuery("imagesdeleted", $DeletedEntryInsertArr);

		print "Tab: $thisOldTableName : Rec: $thisOldRecordID : ImgID: $ThisExtraImageID : Del: $DeletedID  ----- ";
		
		$UpdatePointerHash["Record"]=$DeletedID;
		$UpdatePointerHash["TableName"]="imagesdeleted";
		$dbCmd->UpdateQuery("imagepointer", $UpdatePointerHash, " ID=$ThisExtraImageID");
		
		// Now delete the image from the original table it was stored in
		$dbCmd->Query("DELETE FROM $thisOldTableName WHERE ID=$thisOldRecordID");
	}
	else if($EntryCheck == 0){
		// Get rid of the pointer since it points to nothing
		$dbCmd->Query("DELETE FROM imagepointer WHERE ID=$ThisExtraImageID");
		
		print "deleting image pointer ID without an image: $thisOldTableName : Rec: $thisOldRecordID : ImgID: $ThisExtraImageID  ----- ";
	}
}

// ------------
// ----------------   END Raster Block  -----------------------








// ----------------   BEGIN Vector Block  -----------------------
// ------------

print "<br><br>Extra Vector Images<hr>";
foreach($ExtraVectorImgIDArr as $thisID)
	print $thisID . " - "; 
print "<br><br>Removing Extra Vector Images<hr>";

foreach($ExtraVectorImgIDArr as $ThisExtraImageID){
	
	// Now Get all image ID's From the image pointer table.. unless the image record points to a deleted record
	$dbCmd->Query("SELECT TableName, Record FROM vectorimagepointer WHERE ID=$ThisExtraImageID");
	$row = $dbCmd->GetRow();
	$thisOldTableName = $row["TableName"];
	$thisOldRecordID = $row["Record"];

	// Get Make sure that the imageID is not pointing to a record that does not exist.  
	// Should happen, but on my DEV server things were messy... and so was the beggining stages of PME
	$dbCmd->Query("SELECT count(*) FROM $thisOldTableName WHERE ID=$thisOldRecordID");
	$EntryCheck = $dbCmd->GetValue();


	if($EntryCheck == 1){
	
		// Get info from the old image so that we can move it into the deleted table
		$dbCmd->Query("SELECT * FROM $thisOldTableName WHERE ID=$thisOldRecordID");
		$OldImageInfo = $dbCmd->GetRow();
		
		$DeletedEntryInsertArr = array();
		
		if($thisOldTableName == "vectorimagessession"){
			$DeletedEntryInsertArr["SID"] = $OldImageInfo["SID"];
		}

		
		$DeletedEntryInsertArr["PDFbinaryData"] = $OldImageInfo["PDFbinaryData"];
		$DeletedEntryInsertArr["DateUploaded"] = $OldImageInfo["DateUploaded"];
		$DeletedEntryInsertArr["EmbeddedText"] = $OldImageInfo["EmbeddedText"]; 
		$DeletedEntryInsertArr["OrigFileSize"] = $OldImageInfo["OrigFileSize"]; 
		$DeletedEntryInsertArr["OrigFileType"] = $OldImageInfo["OrigFileType"]; 
		$DeletedEntryInsertArr["OrigFileName"] = $OldImageInfo["OrigFileName"]; 
		$DeletedEntryInsertArr["PDFfileSize"] = $OldImageInfo["PDFfileSize"]; 
		$DeletedEntryInsertArr["PicasWidth"] = $OldImageInfo["PicasWidth"]; 
		$DeletedEntryInsertArr["PicasHeight"] = $OldImageInfo["PicasHeight"]; 
		$DeletedEntryInsertArr["DateDeleted"] = $mysql_timestamp; 
		$DeletedID = $dbCmd->InsertQuery("vectorimagesdeleted", $DeletedEntryInsertArr);
		
		
		// Too keep the query from becoming too large... update the other blob after the first insert.
		$dbCmd->UpdateQuery("vectorimagesdeleted", array("OrigFormatBinaryData" => $OldImageInfo["OrigFormatBinaryData"]), " ID=$DeletedID");
		

		print "Tab: $thisOldTableName : Rec: $thisOldRecordID : ImgID: $ThisExtraImageID : Del: $DeletedID  ----- ";
		
		$UpdatePointerHash["Record"]=$DeletedID;
		$UpdatePointerHash["TableName"]="vectorimagesdeleted";
		$dbCmd->UpdateQuery("vectorimagepointer", $UpdatePointerHash, " ID=$ThisExtraImageID");
		
		// Now delete the image from the original table it was stored in
		$dbCmd->Query("DELETE FROM $thisOldTableName WHERE ID=$thisOldRecordID");
	}
	else if($EntryCheck == 0){
		// Get rid of the pointer since it points to nothing
		$dbCmd->Query("DELETE FROM vectorimagepointer WHERE ID=$ThisExtraImageID");
		
		print "deleting image pointer ID without an image: $thisOldTableName : Rec: $thisOldRecordID : ImgID: $ThisExtraImageID  ----- ";
	}
}

// ------------
// ----------------   END Vector Block  -----------------------







// Optimize Tables to remove big blocks of wasted space... since there could have been lots of deleting going on.
// Restrict the following query to the tables that we want to filter
foreach($RasterImageCleanTablesArr as $ThisTable){
	

	print "<br><br><b>Optimizing Table:</b> $ThisTable <br>";
	flush();

	
	$dbCmd->Query("OPTIMIZE TABLE $ThisTable");
}


foreach($VectorImageCleanTablesArr as $ThisTable){

	print "<br><br><b>Optimizing Table:</b> $ThisTable <br>";
	flush();
	
	$dbCmd->Query("OPTIMIZE TABLE $ThisTable");
}

















print "<br><br<br>DONE</font>";


// Send out an email notifying that the operation is completed
$message = "Clean Images completed: " . date("F j, Y, g:i a") . " total raster deleted = " . sizeof($ExtraRasterImgIDArr) . "total vector deleted = " .  sizeof($ExtraVectorImgIDArr); 
WebUtil::SendEmail("PME Server", Constants::GetMasterServerEmailAddress(), Constants::GetAdminName(), Constants::GetAdminEmail(), "Clean Images Completed", $message);

?>
<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

$imageid = WebUtil::GetInput("imageid", FILTER_SANITIZE_INT);

$dbCmd->Query("SELECT * FROM imagepointer WHERE ID=$imageid");

if($dbCmd->GetNumRows() == 1){

	$ThisResult = $dbCmd->GetRow();
	$TableName = $ThisResult["TableName"];
	$DeletedRecordID = $ThisResult["Record"];

	if($TableName != "imagesdeleted"){
		print "File has not been deleted";
		exit;
	}
}
else{
	print "File has not been deleted";
	exit;
}


$mysql_timestamp = date("YmdHis", time());	

// Get info from the old image so that we can move it into the deleted table
$dbCmd->Query("SELECT * FROM imagesdeleted WHERE ID=$DeletedRecordID");
$OldImageInfo = $dbCmd->GetRow();

$EntryInsertArr["BinaryData"] = $OldImageInfo["BinaryData"];
$EntryInsertArr["FileSize"] = $OldImageInfo["FileSize"]; 
$EntryInsertArr["FileType"] = $OldImageInfo["FileType"]; 
$EntryInsertArr["DateUploaded"] = $mysql_timestamp; 
$NewSavedID = $dbCmd->InsertQuery(ImageLib::GetImagesSavedTableName($dbCmd), $EntryInsertArr);

print "Tab: $TableName : DeletedRec: $DeletedRecordID : ImgPointer: $imageid : Del: $NewSavedID  ----- ";

$UpdatePointerHash["Record"]=$NewSavedID;
$UpdatePointerHash["TableName"]=ImageLib::GetImagesSavedTableName($dbCmd);
$dbCmd->UpdateQuery("imagepointer", $UpdatePointerHash, " ID=$imageid");

// Now delete the image from the original table it was stored in
//$dbCmd->Query("DELETE FROM imagesdeleted WHERE ID=$DeletedRecordID");
	
	
?>
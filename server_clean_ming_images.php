<?

require_once("library/Boot_Session.php");
set_time_limit(2500);


$dbCmd = new DbCmd();


$dbCmd->Query("SELECT ID, TableName FROM imagepointer");

print "Deleting Extra MING images<br>";
flush();

while($row = $dbCmd->GetRow()){

	// We don't want to delete Ming images that came from a template because they are used so frequently.
	if(!preg_match("/imagestemplate/", $row["TableName"])){

		$MingFileName = Constants::GetMingBase() . "/" . ImageLib::GetMingFileName( $row["ID"] );

		if(file_exists($MingFileName)){
			print "ID:" . $row["ID"] . "<br>\n";
			flush();
			
			@unlink($MingFileName);
		}
	}
}


print "Done with Ming Images";
flush();


print "<br><br><br>Cleaning Extra Preview Images<hr>\n";

$artPreviewObj = new ArtworkPreview($dbCmd);
$artPreviewObj->cleanOldPreviews();

?>
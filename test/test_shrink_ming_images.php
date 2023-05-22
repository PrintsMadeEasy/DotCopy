<?

require_once("library/Boot_Session.php");
set_time_limit(9500);


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


$dbCmd->Query("SELECT ID, TableName FROM imagepointer");

print "Shrinking Template MING images<br>";
flush();

while($row = $dbCmd->GetRow()){

	// We don't want to delete Ming images that came from a template because they are used so frequently.
	if(!preg_match("/imagestemplate/", $row["TableName"])){
			continue;
	}
	
	
	$MingFileName = Constants::GetMingBase() . "/" . ImageLib::GetMingFileName( $row["ID"] );

	if(file_exists($MingFileName)){
		$mingSize = filesize($MingFileName);
		$savedTime = filemtime($MingFileName);
		
		$fileSizeInMegs = round(($mingSize / 1024 / 1024), 2);
		
		if($fileSizeInMegs < 0.9)
			continue;
		
		print "ID:" . $row["ID"] . " Size: " . $fileSizeInMegs . " MB Date:" . date("n j, Y", $savedTime) . "<br>\n";
		ImageLib::WriteMINGswfToDisk($dbCmd2, $row["ID"], true, 800);
		flush();
		
		sleep(1);
		
	
	}
}


print "Done with Ming Images";
flush();


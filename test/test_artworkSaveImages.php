<?

require_once("library/Boot_Session.php");

set_time_limit(5000);

$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();
$dbCmd3 = new DbCmd();




print "Trying to fix the Images in Session.<br>";

$dbCmd->Query("SELECT ID FROM projectsordered WHERE ID > 672453");
while($projectID = $dbCmd->GetValue()){

	ArtworkLib::SaveImagesInSession($dbCmd2, "projectsordered", $projectID, ImageLib::GetImagesSavedTableName($dbCmd2), ImageLib::GetVectorImagesSavedTableName($dbCmd2));
	
	print $projectID . "<br>";
	flush();
}

print "<hr>Done.";


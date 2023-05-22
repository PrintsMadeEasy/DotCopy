<?

require_once("library/Boot_Session.php");


set_time_limit(50000);


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();



$counter = 0;
$dbCmd->Query("SELECT ID FROM projectssession ORDER BY ID ASC");

while($pid = $dbCmd->GetValue()){
	
	
	$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd2, "session", $pid, false);
	$artworkSides = $projectObj->getArtworkSideCountFromXML();
	
	$dbCmd2->UpdateQuery("projectssession", array("ArtworkSides"=>$artworkSides), "ID=$pid");
	
	$counter++;
	
	if($counter > 100){
		print $pid . " - ";
		$counter = 0;
		flush();
	}
}

print "<br><br>projects Session done<hr>";

$counter = 0;
$dbCmd->Query("SELECT ID FROM projectssaved ORDER BY ID ASC");

while($pid = $dbCmd->GetValue()){
	
	
	$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd2, "saved", $pid, false);
	$artworkSides = $projectObj->getArtworkSideCountFromXML();
	
	$dbCmd2->UpdateQuery("projectssaved", array("ArtworkSides"=>$artworkSides), "ID=$pid");
	
	$counter++;
	
	if($counter > 100){
		print $pid . " - ";
		$counter = 0;
		flush();
	}
}

print "<br><br>projects Saved done<hr>";

$counter = 0;
$dbCmd->Query("SELECT ID FROM projectsordered ORDER BY ID ASC");

while($pid = $dbCmd->GetValue()){
	
	
	$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd2, "ordered", $pid, false);
	$artworkSides = $projectObj->getArtworkSideCountFromXML();
	
	$dbCmd2->UpdateQuery("projectsordered", array("ArtworkSides"=>$artworkSides), "ID=$pid");
	
	$counter++;
	
	if($counter > 100){
		print $pid . " - ";
		$counter = 0;
		flush();
	}
}

print "<hr>Totally done...";


?>

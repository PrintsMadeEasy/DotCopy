<?

require_once("library/Boot_Session.php");


set_time_limit(50000);


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();






print "<br><br>Projects Saved<hr>\n";
flush();

$counter = 0;

$dbCmd->Query("SELECT ID FROM projectssaved ORDER BY ID ASC");
while($projectID = $dbCmd->GetValue()){
	
	$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd2, "saved", $projectID);
	
	$optionsDesc = $projectObj->getOptionsDescription();
	$aliasDesc = $projectObj->getOptionsAliasStr();
	
	$dbCmd2->UpdateQuery("projectssaved", array("OptionsAlias"=>DbCmd::EscapeSQL($aliasDesc), "OptionsDescription"=>DbCmd::EscapeSQL($optionsDesc)), "ID=$projectID");

	if($counter > 100){
		print $projectID . " - ";
		$counter = 0;
		flush();
	}
	
	$counter++;
}


print "<br><br>Projects Session<hr>\n";
flush();


$counter = 0;

$dbCmd->Query("SELECT ID FROM projectssession ORDER BY ID ASC");
while($projectID = $dbCmd->GetValue()){
	
	$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd2, "session", $projectID);
	
	$optionsDesc = $projectObj->getOptionsDescription();
	$aliasDesc = $projectObj->getOptionsAliasStr();
	
	$dbCmd2->UpdateQuery("projectssession", array("OptionsAlias"=>DbCmd::EscapeSQL($aliasDesc), "OptionsDescription"=>DbCmd::EscapeSQL($optionsDesc)), "ID=$projectID");

	if($counter > 50){
		print $projectID . " - ";
		$counter = 0;
		flush();
	}
	
	$counter++;
}


$counter = 0;

print "Doing Projects Ordered<hr>\n";
flush();


$dbCmd->Query("SELECT ID FROM projectsordered ORDER BY ID DESC");
while($projectID = $dbCmd->GetValue()){
	
	print $projectID . "<br>";
	flush();
	
	$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd2, "ordered", $projectID);
	
	$optionsDesc = $projectObj->getOptionsDescription();
	$aliasDesc = $projectObj->getOptionsAliasStr();
	
	$dbCmd2->UpdateQuery("projectsordered", array("OptionsAlias"=>DbCmd::EscapeSQL($aliasDesc), "OptionsDescription"=>DbCmd::EscapeSQL($optionsDesc)), "ID=$projectID");

	if($counter > 100){
		print $projectID . " - ";
		$counter = 0;
		flush();
	}
	
	$counter++;
}





print "<hr>DONE";



?>

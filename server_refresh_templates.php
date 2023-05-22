<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();




// Make this script be able to run for a while  --#
set_time_limit(20000);

print "Starting to generate images<br><br>";


 flush();


//throw new Exception("Need an admin override to start the script.");

/*
$dbCmd->Query("SELECT ArtworkID FROM artworkstemplates_oldpme WHERE ProductID = 73");
while($TemplateID = $dbCmd->GetValue()){

	print $TemplateID . "<br>";
	
	flush();

	ThumbImages::CreateThumnailImage($dbCmd2, $TemplateID, "template_category");
}




$dbCmd->Query("SELECT ID FROM artworksearchengine_oldpme WHERE ProductID=73");
while($TemplateID = $dbCmd->GetValue()){

	print $TemplateID . "<br>";
	
	flush();

	ThumbImages::CreateThumnailImage($dbCmd2, $TemplateID, "template_searchengine");
}

*/
	

print "<b>Mission Complete</b>";



?>
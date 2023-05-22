<?

require_once("library/Boot_Session.php");




$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();





// Make this script be able to run for a while 
set_time_limit(20000);


print "Starting to generate thumbnails for Projectssaved<br><br>";

flush();

throw new Exception("Needs admin override to start this script.");

/*

$dbCmd->Query("SELECT ID FROM projectssaved WHERE ID < 971 ORDER BY ID DESC ");
while($SavedID = $dbCmd->GetValue()){

	print $SavedID . " - ";
	
	flush();

	ThumbImages::CreateThumnailImage($dbCmd2, $SavedID, "saved");
}


print "<br><br><br>Starting to generate thumbnails for Projects Session<br><br>";


flush();



$dbCmd->Query("SELECT ID FROM projectssession ORDER BY ID DESC");
while($ProjectssessionID = $dbCmd->GetValue()){

	print $ProjectssessionID . " - ";
	
	flush();

	ThumbImages::CreateThumnailImage($dbCmd2, $ProjectssessionID, "projectssession");
}


	

print "<br><br><b>Mission Complete</b>";

*/


?>
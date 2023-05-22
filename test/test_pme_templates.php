<?

require_once("library/Boot_Session.php");




$dbCmd = new DbCmd();

$dbCmd->Query("SELECT ID from artworksearchengine WHERE ProductID=73 ORDER BY ID ASC");
while($thisId = $dbCmd->GetValue()){
	
	print $thisId . "\n";
}

print "\n\ndone";



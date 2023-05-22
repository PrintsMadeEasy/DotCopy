<?

require_once("library/Boot_Session.php");


//throw new Exception("Remove this Exit statement.");

set_time_limit(5000);

$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

print "Gathering Emails<br><br>";
$csvFile = "";
$counter = 0;
$dbCmd->Query("SELECT * FROM users ORDER BY ID DESC");
while($row = $dbCmd->GetRow()){
	
	
	//$csvFile .= FileUtil::csvEncodeLineFromArr(array($row["Email"], $row["Name"], $row["Company"], "", (trim($row["Address"] . " " . $row["AddressTwo"])), $row["City"], $row["State"], $row["Zip"], $row["Country"])) . "\n";
	$csvFile .= FileUtil::csvEncodeLineFromArr(array($row["Email"], $row["Name"], $row["Company"])) . "\n";
	
	if($counter > 2000){
		print "-";
		flush();
		$counter = 0;
	}
}

file_put_contents((Constants::GetTempDirectory() . "/EmailList2.csv"), $csvFile);

print "<hr>done";



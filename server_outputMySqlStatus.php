<?

require_once("library/Boot_Session.php");

set_time_limit(5000);

$dbCmd = new DbCmd();

$addToLog = date("M j Y - H:i") . "\n-------------------------------\n";

$dbCmd->Query("SHOW STATUS");
while($row = $dbCmd->GetRow()){
	
	$variableName = $row["Variable_name"];
	$variableValue = $row["Value"];
	
	if(preg_match("/^Com_/", $variableName))
		continue;
	if(preg_match("/^Slave/", $variableName))
		continue;
	
	$addToLog .= $variableName . "\t\t" . $variableValue . "\n";
	
}

$dbCmd->Query("SHOW PROCESSLIST");
while($row = $dbCmd->GetRow()){
	$addToLog .= $row["User"] . "\t\t" .  $row["Host"] . "\t\t" .  $row["db"] . "\t\t" .  $row["Command"] . "\t\t" .  $row["Time"] . "\t\t" .  $row["State"] . "\t\t" .  $row["Info"] . "\n";
}
//$dbCmd->Query("SHOW INNODB STATUS");
//while($row = $dbCmd->GetRow()){
//	$addToLog .= $row["Status"] . "\n";
//}

$addToLog .= "\n\n\n\n\n\n\n";


file_put_contents("/home/printsma/MySqlStatus.txt", $addToLog, FILE_APPEND);

print "<hr><hr>ALL Done";





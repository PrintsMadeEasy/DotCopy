<?

require_once("library/Boot_Session.php");


set_time_limit(5000);


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();



$counter = 0;

print "<hr>Saved<hr>";


$dbCmd->Query("SELECT ID, VariableDataFile FROM projectssaved");
while($row = $dbCmd->GetRow()){
	
	if(empty($row["VariableDataFile"]))
		continue;
	
	$dbCmd2->InsertQuery("variabledatasaved", array("ProjectID"=>$row["ID"], "VariableDataFile"=>$row["VariableDataFile"]));
	
	if($counter > 100){
		print $row["ID"] . " - ";
		$counter = 0;
		flush();
	}
	
	$counter++;
}


print "<hr>Ordered<hr>";


$dbCmd->Query("SELECT ID, VariableDataFileOriginal, VariableDataFile FROM projectsordered");
while($row = $dbCmd->GetRow()){
	
	if(empty($row["VariableDataFileOriginal"]))
		continue;
	
	$dbCmd2->InsertQuery("variabledataordered", array("ProjectID"=>$row["ID"], "VariableDataOriginal"=>$row["VariableDataFileOriginal"], "VariableDataModified"=>$row["VariableDataFile"]));
	
	if($counter > 100){
		print $row["ID"] . " - ";
		$counter = 0;
		flush();
	}
	
	$counter++;
}

print "<hr>DONE";

?>

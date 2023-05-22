<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();



$id = WebUtil::GetInput("id", FILTER_SANITIZE_INT);
$command = WebUtil::GetInput("command", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);


if($command == "slave"){

	if(!preg_match("/^\d+$/", $id))
		throw new Exception("illegal ID");
	$id = intval($id);
		
	$InsertArr["MasterID"] = 0;
	$InsertArr["SlaveID"] = $id;
	$dbCmd->InsertQuery("replicationcheck", $InsertArr);
}
else if($command == "master"){

	if(!preg_match("/^\d+$/", $id))
		throw new Exception("illegal ID");
	$id = intval($id);

	$InsertArr["MasterID"] = $id;
	$InsertArr["SlaveID"] = 0;
	$dbCmd->InsertQuery("replicationcheck", $InsertArr);
}
else if($command == "check_sync"){

	$dbCmd->Query("SELECT MAX(MasterID) AS MasterID, MAX(SlaveID) AS SlaveID FROM replicationcheck");
	$row = $dbCmd->GetRow();
	$MaxMasterID = $row["MasterID"];
	$MaxSlaveID = $row["SlaveID"];


	if($MaxMasterID - $MaxSlaveID > 10  || $MaxSlaveID - $MaxMasterID > 10){
		$ErrorMessage = "Database replication seems to have stopped.  Max MasterID=$MaxMasterID  ..  Max SlaveID=$MaxSlaveID";
		WebUtil::WebmasterError($ErrorMessage);
	}
	else{
		print "Sync OK";
	}
}
else{
	throw new Exception("Illegal command");
}

?>
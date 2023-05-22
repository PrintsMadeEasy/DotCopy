<?

class ProjectHistory {


	// Static methods

	// Returns a Unix Time stamp for date of the last status change
	static function GetLastStatusDate(DbCmd $dbCmd, $ProjectOrderedID){

		WebUtil::EnsureDigit($ProjectOrderedID);

		$dbCmd->Query("SELECT UNIX_TIMESTAMP(Date) AS LastDate FROM projecthistory WHERE ProjectID=$ProjectOrderedID ORDER BY ID DESC LIMIT 1");
		if($dbCmd->GetNumRows() == 0)
			return 0;
		return $dbCmd->GetValue();
	}




	//  Keep track of status changes... such as printed, transferered, proofed, etc.
	//  If the note is only 1 character... then it assumes it is a status character
	//  Pass in a UserID of 0 if you want to show it as modified by the system.
	static function RecordProjectHistory(DbCmd $dbCmd, $ProjectRecordID, $note, $UserID){
	
		$ProjectRecordID = intval($ProjectRecordID);
		$UserID = intval($UserID);
	
		// The the status is only 1 character then convert the status into a readable desciption 
		// If it is longer than 1 character then assume it is a description 
		if(strlen($note) == 1)
			$note = ProjectStatus::GetProjectStatusDescription($note);
	
	
		$InsertRow["ProjectID"] = $ProjectRecordID;
		$InsertRow["Note"] = $note;
		$InsertRow["UserID"] = $UserID;
	
		$dbCmd->InsertQuery("projecthistory",  $InsertRow);
	}
	



}

?>
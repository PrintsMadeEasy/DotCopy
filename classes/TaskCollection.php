<?

class TaskCollection {
	
	private $_dbCmd;
	private $_userID;
	private $_showTasksBeforeReminder;
	private $_attachedToName;
	private $_attachedToID;
	private $_limitStartTime;
	private $_limitEndTime;
	private $_keywords;
	private $_showCompletedFlag;
	private $_startRecord;
	private $_recordsToGo;
	private $_resultsDisplayedCount;
	
	
	// Constructor //
	
	function TaskCollection() {
		
		$this->_dbCmd = new DbCmd();
	}
	
	// Setter methods //
	
	function limitShowTasksBeforeReminder($x) {
		if (! is_bool ($x))
			throw new Exception("$x is not a valid parameter for limitShowTasksBeforeReminder, use true or false" );
		$this->_showTasksBeforeReminder = $x;
	}
	
	function limitUncompletedTasksOnly($x) {
		if (! is_bool ($x))
			throw new Exception("$x is not a valid parameter for limitUncompletedTasksOnly, use true or false" );
		$this->_showCompletedFlag = $x;
	}
	
	function limitUserID($x) {
		$x = intval ( $x );
		if ($x == 0)
			throw new Exception("$x is not a valid UserID" );
		$this->_userID = $x;
	}
		

	// Input: Unix Timestamp
	function limitStartTime($x) { 
		$x = intval ( $x );
		$this->_limitStartTime = $x;
	}	
	// Input: Unix Timestamp
	function limitEndTime($x) {
		$x = intval ( $x );
		$this->_limitEndTime = $x;
	}
	
	function limitKeywords($x) {
		$this->_keywords = $x;
	}
	
	function limitAttachedToName($x) {
		$this->_attachedToName = $x;
	}
	
	function limitAttachedToID($x) {
		$x = intval ( $x );
		$this->_attachedToID = $x;
	}
	
	// Getter methods //
	
	function getTaskObjectsArr($resultsPerPage = 0, $offset = 0) {
		
		$offset = intval ( $offset );
		if ($offset < 0)
			throw new Exception("Offset must be zero or greater" );
			
		$resultsPerPage = intval ( $resultsPerPage );
		if ($resultsPerPage < 0)
			throw new Exception("ResultsPerPage should be greater than zero or zero for all results" );
		
		$this->_startRecord = $offset;
		$this->_recordsToGo = $resultsPerPage;
		
			

		// GET all tasks belonging to the user which have not been completed yet --#.
		$this->_dbCmd->Query ( "SELECT ID FROM tasks" . $this->_WhereClause () );

		$resultsDisplayedCount = 0;

		$taskObjectsArr = array();
		
		$taskIDArr = array();
		while ($taskID = $this->_dbCmd->GetValue()) 
			$taskIDArr[] = $taskID;
		
		// Loads only ID's that are displayed
		foreach($taskIDArr as $taskID){

			$taskObj = new Task ($this->_dbCmd);
			$taskObj->loadTaskByID ($taskID);

			$taskObjectsArr[] = $taskObj;

			$resultsDisplayedCount++;
		}

		$this->_resultsDisplayedCount = $resultsDisplayedCount;
		
		return $taskObjectsArr;
	}
	
	function getResultsDisplayedCount() {
		return $this->_resultsDisplayedCount;
	}
	
	function getRecordCount() {
		$this->_dbCmd->Query ( "SELECT COUNT(*) FROM tasks" . $this->_WhereClause () );
		return $this->_dbCmd->GetValue ();
	}
	
	function getAttachedToRecordCount() {
		$this->_dbCmd->Query ( "SELECT COUNT(*) FROM tasks WHERE AttachedTo = \"" . DbCmd::EscapeSQL ( $this->_attachedToName ) . "\" 
								AND RefID=\"" . DbCmd::EscapeSQL ( $this->_attachedToID ) . "\"
								AND AssignedTo=" . intval($this->_userID));
		return $this->_dbCmd->GetValue ();
	}
	
	
	// Implementation //
	
	function _WhereClause() {
		
		if (empty ( $this->_userID ))
			throw new Exception("The UserID is not set for Tasks" );
		
		$taskReminderQry = "";
		if (! $this->_showTasksBeforeReminder)
			$taskReminderQry = " AND (ReminderDate < NOW() OR ReminderDate IS NULL)";
		
		$attachedToQuery = "";
		if (! empty ( $this->_attachedToName ) && ! empty ( $this->_attachedToID ))
			$attachedToQuery = " AND AttachedTo = \"" . DbCmd::EscapeSQL ( $this->_attachedToName ) . "\" AND RefID=\"" . DbCmd::EscapeSQL ( $this->_attachedToID ) . "\" ";
			
		$dateQuery = "";
		if (! empty ( $this->_limitStartTime ) && ! empty ( $this->_limitEndTime ))
			$dateQuery = " AND DateCreated BETWEEN \"" . DbCmd::convertUnixTimeStmpToMysql($this->_limitStartTime) . "\" AND \"" .  DbCmd::convertUnixTimeStmpToMysql($this->_limitEndTime) . "\" ";
		
		$keywordsQuery = "";
		if (! empty ( $this->_keywords ))
			$keywordsQuery = " AND Task LIKE \"%" . DbCmd::EscapeLikeQuery ( $this->_keywords ) . "%\" ";
		
		$showCompletedQuery = "";
		if (! $this->_showCompletedFlag)
			$showCompletedQuery = " AND Completed='N' ";
		
		$limitRecords = "";
		if (! empty ( $this->_recordsToGo ))
			$limitRecords = " LIMIT " . $this->_startRecord . "," . $this->_recordsToGo;
		

		return " WHERE AssignedTo=" . $this->_userID . $showCompletedQuery . $taskReminderQry . $attachedToQuery . $dateQuery . $keywordsQuery . " ORDER BY DateCreated DESC" . $limitRecords;
	}
	
}


?>
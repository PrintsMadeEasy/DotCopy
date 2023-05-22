<?

class Task {
	
	private $_dbCmd;
	private $_taskID;
	private $_attachment;
	private $_refID;
	private $_desc;
	private $_priority;
	private $_dateCreated;
	private $_reminderDate;
	private $_completed;
	private $_dateCompleted;
	private $_createdBy;
	private $_assignedTo;
	private $_taskArray = array ( );
	
	function Task($dbCmd) {
		
		$this->_dbCmd     = $dbCmd;
		$this->_taskID    = 0;
		$this->_completed = false;
	}
		
	// Setter methods //
	
	function setTaskID($x) {
		$x = intval ( $x );
		if ($x == 0)
			throw new Exception("$x is not a valid TaskID" );
		$this->_taskID = $x;
	}
	
	function setAttachment($x) {
		if (! in_array ( $x, array ("project", "order", "message", "csitem", "user", "chat", "" ) ))
			throw new Exception("$x is not a valid parameter for setAttachment, valid parameters are: project, order, message, csitem, user, chat" );
		$this->_attachment = $x;
	}
	
	function setReferenceID($x) {
		$x = intval ( $x );
		if ($x < 0)
			throw new Exception("$x is not a valid ReferenceID" );
		$this->_refID = $x;
	}
	
	function setDescription($x) {
		$this->_desc = $x;
	}
	
	function setPriority($x) {
		$x = strtoupper($x);	
		if (! in_array ( $x, array ("HIGH", "NORMAL" ) ))
			throw new Exception("$x is not a valid parameter for setPriority, use 'HIGH' or 'NORMAL'" );
		$this->_priority = $x == "NORMAL" ? "N" : "H";
	}
	
	function setReminderDate($x) {
		if (! preg_match ( "/^[0-9]{10}$/", $x ))
			throw new Exception("$x is an invalid unixtimestamp for setReminderDate" );
		$this->_reminderDate = $x;	
	}
	
	function setDateCompleted() {
		$this->_dateCompleted = time ();
	}
	
	function setStatusCompleted($x) {
		if (! is_bool ($x))
			throw new Exception("$x is not a valid parameter for setCompleted, use true or false" );
		$this->_completed = $x;
	}
	
	function setCreatedByUserID($x) {
		$x = intval ( $x );
		if ($x == 0)
			throw new Exception("$x is not a valid CreatedByUserID" );
		$this->_createdBy = $x;
	}
	
	function setAssignedToUserID($x) {
		$x = intval ( $x );
		if ($x == 0)
			throw new Exception("$x is not a valid AssignedToUserID" );
		$this->_assignedTo = $x;
	}
	
	// Getter methods //
	
	function getTaskID() {
		return $this->_taskID;
	}
	
	function getAttachment() {
		return $this->_attachment;
	}
	
	function getReferenceID() {
		return $this->_refID;
	}
	
	function getDescriptions() {
		return $this->_desc;
	}
	
	function getPriority() {
		return $this->_priority;
	}
	
	function getCreationDate() {
		return $this->_dateCreated;
	}
	
	function getReminderDate() {
		return $this->_reminderDate;
	}
			
	function getStatusCompleted() {
		return $this->_completed ;
	}
	
	function getDateCompleted() {
		return $this->_dateCompleted ;
	}
	
	function getCreatedByUserID() {
		return $this->_createdBy;
	}
	
	function getAssignedToUserID() {
		return $this->_assignedTo;
	}
	
	function hasAttachment() {		
		return  ( in_array ( $this->_attachment, array ("project", "order", "message", "csitem", "user", "chat") ));		
	}
	
	function isTaskPastDue() {
		return ($this->_reminderDate > time ());
	}
	
	
	function getTimeReminderDescription($reminderDate) {

		if (! preg_match ( "/^[0-9]{10}$/", $reminderDate ))
			throw new Exception("$reminderDate is an invalid unix timestamp for getTimeReminderDescription" );

		$reminderDescription = "";

		if ($reminderDate && $reminderDate > time ()) {

			$reminderHour = date ( "G", $reminderDate );
			$reminderMinutes = date ( "i", $reminderDate );

			$dayOnlyFlag = false;

			if ($reminderHour == 0 && $reminderMinutes < 10)
				$dayOnlyFlag = true;

			$daysUntil = (round ( ($reminderDate - time ()) / (60 * 60 * 24) ));
			if (empty ( $daysUntil ))
				$daysUntil = 1;

			$timeUnilDesc = $daysUntil;

			if (date ( "m", $reminderDate ) == date ( "m" ) && date ( "j", $reminderDate ) == date ( "j" ) && date ( "Y", $reminderDate ) == date ( "Y" ))
				$timeUnilDesc = "Today";

			if ($dayOnlyFlag) {
				$reminderDescription = date ( "M jS ", $reminderDate ) . " " . $timeUnilDesc;
			} else {
				$reminderDescription = date ( "n/j/y g:i a", $reminderDate ) . " " . $timeUnilDesc;	
			}

		} else if ($reminderDate && $reminderDate < time ()) {

			if (date ( "m", $reminderDate ) == date ( "m" ) && date ( "j", $reminderDate ) == date ( "j" ) && date ( "Y", $reminderDate ) == date ( "Y" ) && (date ( "G", $reminderDate ) == 0)) {
				$reminderDescription = "Today";
			} else {
				$reminderDescription = LanguageBase::getTimeDiffDesc ( $reminderDate ) . " ago";
			}
		}

		return $reminderDescription;
	}
	
	function getLinkURL() {

		if ($this->hasAttachment()) {
		
			if ($this->_attachment == "project") {
				$link = "./ad_project.php?projectorderid=";
			} else if ($this->_attachment == "order") {
				$link = "./ad_order.php?orderno=";
			} else if ($this->_attachment == "message") {
				$link = "./ad_message_display.php?thread=";
			} else if ($this->_attachment  == "csitem") {
				$link = "./ad_cs_home.php?view=search&csitemid=";
			} else if ($this->_attachment  == "chat") {
				$link = "./ad_chat_search.php?chat_id=";
			} else if ($this->_attachment  == "user") {
				$link = "./ad_users_search.php?customerid=";
			}
	
			return $link . $this->_refID;
		}
		return "";
	}
	
	function getLinkSubject() {
	
		if ($this->hasAttachment()) {
		
			if ($this->_attachment == "project") {
				$taskPostLinkText = "Project - P";
			} else if ($this->_attachment == "order") {
				$taskPostLinkText = "Order #";
			} else if ($this->_attachment == "message") {
				$taskPostLinkText = "Msg # ";
			} else if ($this->_attachment  == "csitem") {
				$taskPostLinkText = "CS - ";
			} else if ($this->_attachment  == "chat") {
				$taskPostLinkText = "Chat - C";
			} else if ($this->_attachment  == "user") {
				$taskPostLinkText = "User: U";
			}
		
			return  $taskPostLinkText . $this->_refID;
		} 
		return "";
	}

	
	// Implementation //

	function _fillDBFields() {
		
		if (empty ( $this->_assignedTo ))
			throw new Exception("AssignedTo ID is not set" );
		
		if (empty ( $this->_createdBy ))
			throw new Exception("UserID is not set" );
			
		if (empty ( $this->_priority ))
			throw new Exception("Priority is not set" );
		
		$this->_taskDatabase ["DateCreated"] = $this->_dateCreated;
		$this->_taskDatabase ["AttachedTo"] = $this->_attachment;
		$this->_taskDatabase ["RefID"] = $this->_refID;
		$this->_taskDatabase ["Task"] = DbCmd::EscapeSQL ( $this->_desc );
		$this->_taskDatabase ["Priority"] = $this->_priority;
		$this->_taskDatabase ["ReminderDate"] = $this->_reminderDate;
		$this->_taskDatabase ["Completed"] = $this->_completed == false ? "N" : "Y";
		$this->_taskDatabase ["DateCompleted"] = $this->_dateCompleted;
		$this->_taskDatabase ["CreatedBy"] = $this->_createdBy;
		$this->_taskDatabase ["AssignedTo"] = $this->_assignedTo;
	}
		
	function loadTaskByID($taskID) {

		$this->_dbCmd->Query ( "SELECT ID, UNIX_TIMESTAMP(DateCreated) AS DateCreated, AttachedTo, RefID, Completed, Task, Priority, UNIX_TIMESTAMP(ReminderDate) AS ReminderDate, UNIX_TIMESTAMP(DateCompleted) AS DateCompleted, CreatedBy, AssignedTo 
					FROM tasks WHERE ID = " . $taskID . " LIMIT 1" );

		$row = $this->_dbCmd->GetRow ();

		if(empty($row ["ID"]))
			throw new Exception("Error: No TaskID found in database");

		$this->_taskID = $row ["ID"];
		$this->_dateCreated = $row ["DateCreated"];
		$this->_attachment = $row ["AttachedTo"];
		$this->_refID = $row ["RefID"];
		$this->_desc = htmlspecialchars ( stripslashes ( $row ["Task"] ) );
		$this->_priority = $row ["Priority"];
		$this->_reminderDate = $row ["ReminderDate"];
		$this->_completed = $row ["Completed"]  == "N" ? false : true;
		$this->_dateCompleted = $row ["DateCompleted"];
		$this->_createdBy = $row ["CreatedBy"];
		$this->_assignedTo = $row ["AssignedTo"];
		
		// Ensure permission to load a task
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		if(!$passiveAuthObj->CheckIfLoggedIn() || $passiveAuthObj->GetUserID() != $this->_assignedTo)
			throw new Exception("Error: No TaskID found in database");
	}
	
	function addNewTask() {
		
		$this->_dateCreated = time ();
		$this->_fillDBFields ();
		$this->_taskID = $this->_dbCmd->InsertQuery ( "tasks", $this->_taskDatabase );
	}
	
	function updateTask() {
	
		if (empty ( $this->_taskID ))
			throw new Exception("Set TaskID before updateTask." );
		
		$this->_fillDBFields ();
		$this->_dbCmd->UpdateQuery ( "tasks", $this->_taskDatabase, "ID = $this->_taskID" );
	}
}



?>
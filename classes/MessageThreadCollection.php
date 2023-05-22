<?php

class MessageThreadCollection {
	
	private $_dbCmd;
	private $_userID;
	private $_startRecord;
	private $_recordsToGo;
	private $_limitStartTime;
	private $_limitEndTime;
	private $_keywords;
	private $_refID;
	private $_attachedTo;
	private $_totalMessagesInThreads;	
	private $_threadsArr = array();

	function __construct() {
		
		$this->_dbCmd = new DbCmd();
	}
	
	function __destruct() {
	
	}
	
	public function setUserID($userID) {
		$this->_userID = intval($userID);
	}
		
	public function setLimitStartTime($limitStartTime) {
		$this->_limitStartTime = intval($limitStartTime);
	}
	
	public function setLimitEndTime($limitEndTime) {
		$this->_limitEndTime = intval($limitEndTime);
	}
	
	public function setStartRecord($startRecord) {
		$startRecord = intval($startRecord);
		$this->_startRecord = $startRecord;
	}
	
	public function setRecordsToGo($recordsToGo) {
		$recordsToGo = intval($recordsToGo);
		$this->_recordsToGo = $recordsToGo;
	}

	public function setKeywords($keywords) {

		// Clean up the Keywords
		$keywords = preg_replace("/\'/", "", $keywords);  
		$keywords = preg_replace('/"/', "", $keywords);  
		$keywords = preg_replace('/\\\/', "", $keywords); 
		$keywords = preg_replace('/</', "", $keywords); 
		$keywords = preg_replace('/>/', "", $keywords); 
		$keywords = DbCmd::EscapeSQL($keywords);
		
		$this->_keywords = $keywords;
	}
	
	public function setAttachedTo($attachedTo) {
		
		if (! in_array ( $attachedTo, array ("project", "order", "message", "csitem", "user","") ))
			throw new Exception("$attachedTo is not a valid parameter for setAttachedTo, valid parameters are: project, order, message, csitem, user" );
			
		$this->_attachedTo = $attachedTo;
	}
	
	public function setRefID($refID) {
		$this->_refID = intval($refID);
	}
	
	public function getUserID() {
		return $this->_userID;
	}
	
	public function getLimitStartTime() {
		return $this->_limitStartTime;
	}
	
	public function getLimitEndTime() {
		return $this->_limitEndTime;
	}
	
	public function getRecordsToGo() {
		return $this->_recordsToGo;
	}
	
	public function getStartRecord() {
		return $this->_startRecord;
	}
	
	public function getThreadCollection() {
		return $this->_threadsArr;
	}
		
	public function getKeywords() {
		return $this->_keywords;
	}
	
	public function getThreadCountsDisplayed() {
		return sizeof($this->_threadsArr);
	}
	
	public function getTotalMessagesInThreadsCount() {
		return $this->_totalMessagesInThreads;
	}
	
	public function getAttachedTo() {
		return $this->_attachedTo;
	}
	
	public function getRefID() {
		return $this->_refID;
	}
	
	public function getTotalThreadCount() {
		
		$this->_dbCmd->Query("SELECT COUNT(DISTINCT(ThreadID)) FROM msgsdata WHERE msgsdata.UserID = $this->_userID " . $this->threadSelectionBaseWhereClause());
		return $this->_dbCmd->GetValue();
	}
	
	private function threadSelectionBaseWhereClause() {
	
		if(!empty($this->_limitStartTime) && empty($this->_limitEndTime)) 
			throw new Exception("If you set StartTime, you also have to set EndTime.");
		
		if(empty($this->_limitStartTime) && !empty($this->_limitEndTime)) 
			throw new Exception("If you set EndTime, you also have to set StartTime.");
		
		$baseQuery = "";
		
		if(!empty($this->_limitStartTime) && !empty($this->_limitEndTime)) 	
			$baseQuery .= " AND (msgsdata.DateCreated BETWEEN " . date("YmdHis", $this->_limitStartTime) . " AND " . date("YmdHis", $this->_limitEndTime) . ")";
		
		
		// Make the keyword list into an array
		$keywordList = split(" ", $this->_keywords);
		
		if(!empty($this->_keywords)) {
			
			for($i = 0; $i < count($keywordList); $i++) {
				
				$baseQuery .= " AND msgsdata.Message LIKE \"%" . DbCmd::EscapeLikeQuery($keywordList[$i]) . "%\"";
			}
		}
		return $baseQuery;
	}

	// Pass in true if you only like unread messages
	// If attachedTo is set to a value, the result will only contain the attached threads
	public function loadThreadCollection($unreadOnly = false) {
				
		if( (!empty($this->_refID)) && (empty($this->_attachedTo)) ) 
			throw new Exception("Specify an attachedTo for RefID $this->_refID");	
		
		$threadIDList = array();
		$this->_threadsArr = array(); 
		$this->_totalMessagesInThreads = 0;
		$makeArrUnique = false;
		
		if($unreadOnly) {

			$query = "SELECT ThreadID FROM msgsdata 
				INNER JOIN msgsunread ON msgsdata.ID = msgsunread.MessageID
				WHERE msgsunread.UserID=$this->_userID ORDER BY DateCreated DESC";			

				$makeArrUnique = true;
		}
		else {
			
			$query = "SELECT DISTINCT(msgsdata.ThreadID) FROM msgsdata INNER JOIN msgsgroups ON msgsgroups.ThreadID = msgsdata.ThreadID 
					WHERE msgsgroups.UserID = " . $this->_userID . $this->threadSelectionBaseWhereClause() . " 
					ORDER BY msgsdata.DateCreated DESC";
			
			if($this->_recordsToGo > 0) 
				$query .= " LIMIT $this->_startRecord, $this->_recordsToGo";
		}

		// If we find an attached item, overwrite previous query with this one
		if(!empty($this->_attachedTo)) {
			
			if(empty($this->_refID))
				throw new Exception("Set valid RefID for attached item $this->_attachedTo");
				
			//The join will make sure that the user has permissions to see the thread
			$query = "SELECT DISTINCT MT.ThreadID FROM msgsthreads AS MT 
					INNER JOIN msgsgroups AS MG ON MT.ThreadID = MG.ThreadID WHERE MT.AttachedTo='$this->_attachedTo' 
					AND MT.RefID=$this->_refID AND MG.UserID=$this->_userID";		
		}
		
		
		$this->_dbCmd->Query($query);
		while ($row = $this->_dbCmd->GetRow()) 
			$threadIDList[] = $row["ThreadID"];
		
		// To get around the Sorting issue in Mysql when DISTINCT ThreadID is used.	
		if($makeArrUnique) 
			$threadIDList = array_unique($threadIDList);
		
		foreach($threadIDList as $thisThreadID) {
			
			$messageThreadObj = new MessageThread($this->_dbCmd);
			
			$messageThreadObj->SetUserID($this->_userID);
			$messageThreadObj->setAttachedTo($this->_attachedTo);
			$messageThreadObj->setRefID($this->_refID);
			$messageThreadObj->loadThreadByID($thisThreadID);		

			$this->_threadsArr[] = $messageThreadObj; 
			
			$this->_totalMessagesInThreads++;
		}		
	}

	
}	

?>

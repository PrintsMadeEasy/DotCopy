<?php

class MessageRevision {

	private $_dbCmd;
	private $_revisionTextIsSet;
	private $_revisionText;
	private $_userID;
	
	function __construct() {
	
		$this->_dbCmd = new DbCmd();
		$this->_revisionTextIsSet = false;
	}
	


	public function setUserID($userID) {
		
		$userID = intval($userID);
		if(empty($userID))
			throw new Exception("UserID not valid in MessageRevision::setUserID");
			
		$this->_userID = $userID;
	}
	
	public function setRevisionText($revisionText) {
	
		$this->_revisionTextIsSet = true;
		$this->_revisionText = $revisionText;	
	}
	
	public function isRevisionTextSet () {
		
		return $this->_revisionTextIsSet;
	}
	
	// Returns 0 if the Message has not been revised e.g. if nothing changed.
	public function reviseMessageByID($messageID) {

		$messageID = intval($messageID);
			
		if(empty($this->_userID))
			throw new Exception("Set UserID before calling reviseMessageByID");
		if(!$this->_revisionTextIsSet)
			throw new Exception("You have to set the RevisionText before calling reviseMessageByID.");
		
		$messageObj = new Message($this->_dbCmd);
		$messageObj->loadMessageByID($messageID);
		
		$messageUser = $messageObj->getUserID();
	
		if($messageUser != $this->_userID)
			throw new Exception("User $this->_userID is not allowed to revise MessageID $messageID.");
		
		$messageText = $messageObj->getMessageText();

		$newRevisionID = 0; 
		if(md5($messageText) != md5($this->_revisionText)) {
		
			// Add old Message to MsgHistory	
			$insertArr = array();
			$insertArr["MessageID"] = $messageID;
			$insertArr["Message"]   = $messageText; // Old Text
			$insertArr["DateCreated"] = $messageObj->getDateCreated();
			
			$newRevisionID = $this->_dbCmd->InsertQuery("msghistory", $insertArr );
		
			$messageObj->setMessage($this->_revisionText); // New Text
			$messageObj->updateMessage();
		}
			
		return $newRevisionID;
	}
	
	// Returns an array of the revision history order by last revision first
	public function getRevisionHistoryArr($messageID) {
		
		$messageID = intval($messageID);
		
		Message::ensurePermissionsOnMessageID($messageID);
	
		$revisionHistory = array();

		$this->_dbCmd->Query("SELECT Message, UNIX_TIMESTAMP(DateCreated) AS DateCreated FROM msghistory WHERE MessageID = $messageID ORDER BY DateCreated DESC");
		while ($row = $this->_dbCmd->GetRow()) {
			
			$singleRevision = array();
			$singleRevision["Message"] = $row["Message"];
			$singleRevision["DateCreated"] = $row["DateCreated"];
			
			$revisionHistory[] = $singleRevision;
		}
		return $revisionHistory;
	}

	// Returns the number count of revisions of the specified message.
	static function getCountOfMessageRevisions($messageID) {
		
		$messageID = intval($messageID);
			
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT COUNT(*) FROM msghistory WHERE MessageID = $messageID");
		return $dbCmd->GetValue();
	}
	

	
}

?>

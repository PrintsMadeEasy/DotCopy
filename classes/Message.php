<?php

class Message {
	
	private $_dbCmd;
	private $_userID;
	private $_message;
	private $_isRead;
	private $_dateCreated;
	private $_threadID;
	private $_ID;
			
	function __construct($dbCmd) {
		
		$this->_dbCmd = $dbCmd;
	}


	// Returns the Thread ID beloging to the Message ID
	static function getThreadIdFromMessaseId($messageID){
		
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT ThreadID FROM msgsdata WHERE ID=" . intval($messageID));
		$threadID = $dbCmd->GetValue();
		
		if(empty($threadID))
			throw new Exception("Error in method getThreadIdFromMessaseId");
			
		return $threadID;
		
	}
	static function ensurePermissionsOnMessageID($messageID){
		
		$threadID = self::getThreadIdFromMessaseId($messageID);
		
		$passivAuthObj = Authenticate::getPassiveAuthObject();
		$userIDloggedIn = $passivAuthObj->GetUserID();
		
		$userIDsInThread = MessageThread::getUserIDsInThread($threadID);
		
		if(!in_array($userIDloggedIn, $userIDsInThread))
			throw new Exception("User does not have permissions to see Message ID.");
	}
	
	public function getDateCreated() {
		return $this->_dateCreated;
	}
	
	public function getID() {
		return $this->_ID;
	}
	
	public function getMessageText() {
		return $this->_message;
	}
	
	public function getThreadID() {
		return $this->_threadID;
	}
	
	public function getUserID() {
		return $this->_userID;
	}

	public function isRead() {
		return $this->_isRead;
	}
	
	public function setMessage($message) {
		
		$this->_message = $message;
	}
	
	public function setThreadID($threadID) {
		
		$threadID = intval ($threadID);
		
		if (empty($threadID))
			throw new Exception("Thread ID can not be empty" );
			
		$this->_threadID = $threadID;
	}
	
	public function setUserID($userID) {
		
		$userID = intval($userID);
		
		if (empty($userID))
			throw new Exception("User ID can not be empty" );
			
		$this->_userID = $userID;
	}
		
	public function loadMessageByID($messageId, $authenticateFlag = true) {
		
		$messageId = intval ( $messageId );
		
		if (empty($messageId))
			exit ("ID is not valid for loadMessageByID");

		$this->_ID = null;
		
		if($authenticateFlag)
			self::ensurePermissionsOnMessageID($messageId);
		
		$this->_dbCmd->Query("SELECT ID, Message, ThreadID, UserID, UNIX_TIMESTAMP(DateCreated) AS DateCreated FROM msgsdata WHERE ID = $messageId");
		
		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Error Loading Message ID");
		
		$row = $this->_dbCmd->GetRow();

		$this->_ID = $row["ID"];
		$this->_message     = $row["Message"];
		$this->_dateCreated = $row["DateCreated"];
		$this->_threadID    = $row["ThreadID"];
		$this->_userID      = $row["UserID"];
		
		$passivAuthObj = Authenticate::getPassiveAuthObject();
		$userIDloggedIn = $passivAuthObj->GetUserID();
						
		$this->_dbCmd->Query("SELECT COUNT(*) FROM msgsunread WHERE MessageID = $this->_ID AND UserID = $userIDloggedIn");
		$msgCount = $this->_dbCmd->GetValue();
	
		if(empty($msgCount))
			$this->_isRead = true;
		else
			$this->_isRead = false;
	}
	
	private function fillDBFields() {
		
		if (empty ( $this->_userID ))
			throw new Exception("UserID is not set" );
			
		if (empty ( $this->_threadID ))
			throw new Exception("ThreadID is not set" );
			
		$this->_messageDatabase["Message"]  = $this->_message;
		$this->_messageDatabase["ThreadID"] = $this->_threadID;
		$this->_messageDatabase["UserID"]   = $this->_userID;
	}
	

	public function addMessage() {
	
		$this->fillDBFields();
		$this->_messageDatabase["DateCreated"] = time();
		$this->_ID = $this->_dbCmd->InsertQuery("msgsdata", $this->_messageDatabase );
		return $this->_ID;
	}
		
	public function updateMessage() {
		
		if (empty ( $this->_ID ))
			throw new Exception("A Message must be loaded before trying to update." );
						
		$this->fillDBFields();
		$this->_dbCmd->UpdateQuery("msgsdata", $this->_messageDatabase, "ID = $this->_ID" );
	}

	
	public function getAttachmentCount() {
		
		$this->_ID = intval ( $this->_ID );
		if (empty($this->_ID))
			exit ("ID is not valid for getAttachmentCount");
		
		$this->_dbCmd->Query("SELECT COUNT(*) FROM messagesattachmentspointer WHERE TableName != 'deleted' AND MessageID=$this->_ID");
		return $this->_dbCmd->GetValue();		
	}
	
	public function userIsMessageOwner($userID) {
		
		if(empty($this->_ID))
			throw new Exception("The Thread ID must be loaded first.");
		
		if($userID != $this->_userID)
			return false;
		else
			return true;	
	}
	
	public function removeAttachment($attachmentID) {
		
		if(empty($this->_ID))
			throw new Exception("The Thread ID must be loaded first.");
		
		// Don't delete the attachment data unless it is within the Active Table (which rotates as size increases).
		// Older tables are archived, which could cause database replication to fail.
		if($this->getCurrentAttachmentsTableName() == $this->getAttachmentsTableByID($attachmentID)) 
			$this->_dbCmd->Query("DELETE FROM " . $this->getAttachmentsTableByID($attachmentID) . " WHERE ID= " . $this->getAttachmentPointerID($attachmentID));
		
		// Don't update the Pointer Table until after the Message Data has been deleted. Authentication is done in method calls above to make sure that the attachment ID belongs to the current message. 
		$this->_dbCmd->UpdateQuery("messagesattachmentspointer", array("TableName"=>"deleted"), ("ID=" . intval($attachmentID) . " AND MessageID=" . $this->_ID));
	
	}
	
	public function getAttachmentBinaryData($attachmentID) {
		
		if(empty($this->_ID))
			throw new Exception("The Thread ID must be loaded first.");
		
	    $this->_dbCmd->Query("SELECT BinaryData FROM " . $this->getAttachmentsTableByID($attachmentID) . " WHERE ID=" . $this->getAttachmentPointerID($attachmentID));
	    $binData = $this->_dbCmd->GetValue();

	    return $binData;
	}

	public function getAttachmentIDs() {
		
		if(empty($this->_ID))
			throw new Exception("The Thread ID must be loaded first.");

		$this->_dbCmd->Query("SELECT ID from messagesattachmentspointer where TableName != 'deleted' AND MessageID = $this->_ID order by ID");
		$attachmentIDArr = $this->_dbCmd->GetValueArr();
		
		return $attachmentIDArr;
	}
	
	function getAttatchmentFileName($attachmentID) {
		
		if(empty($this->_ID))
			throw new Exception("The Thread ID must be loaded first.");
		
		$this->_dbCmd->Query("SELECT Filename FROM " . $this->getAttachmentsTableByID($attachmentID) . "  WHERE ID = " . $this->getAttachmentPointerID($attachmentID));
		return $this->_dbCmd->GetValue();	
	}
	
	function getAttatchmentFileSize($attachmentID) {
		
		if(empty($this->_ID))
			throw new Exception("The Thread ID must be loaded first.");
		
		$this->_dbCmd->Query("SELECT FileSize FROM " . $this->getAttachmentsTableByID($attachmentID) . " WHERE ID = " . $this->getAttachmentPointerID($attachmentID));
		return $this->_dbCmd->GetValue();	
	}	
	
	function getAttachmentDate($attachmentID) {
			
		if(empty($this->_ID))
			throw new Exception("The Thread ID must be loaded first.");
			
		$this->_dbCmd->Query("SELECT UNIX_TIMESTAMP(DateAdded) AS UploadDate FROM " . $this->getAttachmentsTableByID($attachmentID) . " WHERE ID = " . $this->getAttachmentPointerID($attachmentID));
		return $this->_dbCmd->GetValue();	
	}
	
	// This will calculate a secure and consistant name that can be written to disk, that users can download.
	function getSecuredAttachmentFileName($attachmentID){
		
		if(empty($this->_ID))
			throw new Exception("The Thread ID must be loaded first.");
		
		$partialMD5 = substr( MD5( $attachmentID . Constants::getGeneralSecuritySalt() ),12,10);
				
		return "msgAtch_" . $partialMD5. "_" . FileUtil::CleanFileName($this->getAttatchmentFileName($attachmentID));
	}
		
	public function addAttachment($binaryData, $originalFileName) {

		if(empty($this->_ID))
			throw new Exception("The Thread ID must be loaded first.");
			
		if(empty($binaryData))
			exit ("No binary data was supplied for the attachment.");	
						
		$binaryTableName = $this->getCurrentAttachmentsTableName();		
		$attachmentID = $this->_dbCmd->InsertQuery("messagesattachmentspointer", array("MessageID"=>$this->_ID, "TableName"=>$binaryTableName));
  		
		$messageAttachment["DateAdded"]     = time();
		$messageAttachment["BinaryData"]    = $binaryData;
		$messageAttachment["Filename"]      = FileUtil::CleanFileName($originalFileName); 
		$messageAttachment["FileSize"]      = strlen($binaryData);
			
		$binTablePointerID = $this->_dbCmd->InsertQuery($binaryTableName, $messageAttachment);
		
		// Now update the pointer table with the Message Attachment we just inserted.
		$this->_dbCmd->UpdateQuery("messagesattachmentspointer", array("BinaryTablePointerID"=>$binTablePointerID), ("ID=$attachmentID"));

		return $attachmentID;
	}
	

	// Since Binary tables may grow large and be rotated frequently, this table keeps track of which one is currently active (not archieved)
	private function getCurrentAttachmentsTableName() {
		
		$this->_dbCmd->Query("SELECT TableNameWithSuffix FROM tablerotations WHERE RootTableName='messagesattachments'");
		$tableName = $this->_dbCmd->GetValue();
		
		if(empty($tableName))
			throw new Exception("Empty Table name in function Message::getCurrentAttachmentsTableName");
		
		return $tableName ;
	}

	// There could be many binary attachment tables, this one will return the table name that the attachment ID is inside of.
	private function getAttachmentsTableByID($attachmentID){
		
		if(empty($this->_ID))
			throw new Exception("The Thread ID must be loaded first.");
		
		$this->_dbCmd->Query("SELECT TableName FROM messagesattachmentspointer WHERE TableName != 'deleted' 
								AND ID = " . intval($attachmentID) . " AND MessageID=" . $this->_ID);

		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method getAttachmentsTableByID. The Attachment does not exist or has been deleted.");
								
		return $this->_dbCmd->GetValue();
	}
	
	// Similar to getAttachmentsTableByID ... but it returns the Unique ID in the attachment table (that is pointed to by the attachment ID)
	private function getAttachmentPointerID($attachmentID){
		
		if(empty($this->_ID))
			throw new Exception("The Thread ID must be loaded first.");
		
		$this->_dbCmd->Query("SELECT BinaryTablePointerID FROM messagesattachmentspointer WHERE TableName != 'deleted' 
								AND ID = " . intval($attachmentID) . " AND MessageID=" . $this->_ID);
								
		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method getAttachmentPointerID. The Attachment does not exist or has been deleted.");
								
		return $this->_dbCmd->GetValue();
	}
}

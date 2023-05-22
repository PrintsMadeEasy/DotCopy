<?php

class MessageThread {
	
	private $_dbCmd;
	private $_threadID;
	private $_refID;
	private $_userID;
	private $_subject;
	private $_attachedTo;

	private $_messages = array();
	private $_includeUserIDArr = array();	
	
	
	function __construct() {
		
		$this->_dbCmd = new DbCmd();
	}
	
	function __destruct() {
	
	}
	
	// Returns an array of users IDs in the Thread.
	// Will fail if the Thread ID does not exist.
	static function getUserIDsInThread($threadID){
		
		$threadID = intval($threadID);
		
		$dbCmd = new DbCmd();
		
		$dbCmd->Query("SELECT DISTINCT UserID FROM msgsgroups WHERE ThreadID=" . $threadID);
		
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method getUserIDsInThread. The Thread ID does not exist, or it doesn't have any users.");
		
		return $dbCmd->GetValueArr();
	}

	public function setUserID($userID) {
		$userID = intval($userID);
		if ($userID == 0)
			throw new Exception("$userID is not a valid UserID" ); 
		$this->_userID = $userID;
	}
	
	public function setAttachedTo($attachedTo) {
		
		if (! in_array ( $attachedTo, array ("project", "order", "message", "csitem", "user", "chat", "") ))
			throw new Exception("$attachedTo is not a valid parameter for setAttachedTo, valid parameters are: project, order, message, chat, csitem, user" );
	
		$this->_attachedTo = $attachedTo;
	}
		
	public function setRefID($refID) {
		$refID = intval($refID);
		$this->_refID = $refID;
	}
	
	public function setSubject($subject) {
		$this->_subject = $subject;
	}
	
	
	public function setIncludeUserIDArr($includeUserIDArr) {
		
		$this->_includeUserIDAr = array();
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		
		foreach($includeUserIDArr AS $thisUserID){
			
			$domainIDofUserID = UserControl::getDomainIDofUser($thisUserID);
			
			// Make sure that they can't add users which do not have permission to see the thread within the domain pool.
			// This will mean that Aministrators may be able to send messages to a person in another domain... but that person can create new messages back.
			// However, once a person has been included with in a thread, they can continue replying to the thread as much as they want.
			// So, from a security point of view, it is all about stopping illigitimate users from being included in the beggining.
			if($passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofUserID))
				$this->_includeUserIDArr[] = intval($thisUserID);
		}
		
		$this->_includeUserIDArr = array_unique($this->_includeUserIDArr);
	}
	
	public function getUserID() {
		return $this->_userID;
	}
	
	public function getAttachedTo() {
		return $this->_attachedTo;
	}
	
	public function getIncludeUserIDArr() {
		
		if(empty($this->_threadID))
			throw new Exception("Load Thread before calling getIncludeUserIDArr");
			
		return $this->_includeUserIDArr;
	}
	
	public function getRefID() {
		return $this->_refID;
	}
	
	public function getSubject() {
		return $this->_subject;
	}
	
	public function getThreadID() {
		return $this->_threadID;
	}
	
	public function getMessageCount() {
		return sizeof($this->_messages);
	}

	// Returns an array of message objects
	public function getMessages() {
		return $this->_messages;
	}
	
	public function getThreadStartDate(){
		
		if(empty($this->_threadID))
			throw new Exception("ThreadID is not set for getThreadStartDate");

		$this->_dbCmd->Query("SELECT UNIX_TIMESTAMP(DateCreated) AS DateCreated FROM msgsdata WHERE ThreadID = ".$this->_threadID." ORDER BY DateCreated ASC LIMIT 1");
		return $this->_dbCmd->GetValue();
	}

	public function getLastMessageDate(){
		
		if(empty($this->_threadID))
			throw new Exception("ThreadID is not set for getThreadStartDate");
		
		$this->_dbCmd->Query("SELECT UNIX_TIMESTAMP(DateCreated) AS DateCreated FROM msgsdata WHERE ThreadID = ".$this->_threadID." ORDER BY DateCreated DESC LIMIT 1");
		return $this->_dbCmd->GetValue();
	}
	

	public function checkThreadPermission(){
	
		if(empty($this->_threadID))
			throw new Exception("ThreadID is not set for checkThreadPermission");
		if(empty($this->_userID))
			throw new Exception("UserID is not set for checkThreadPermission");
			
		$this->_dbCmd->Query("SELECT ID FROM msgsgroups WHERE ThreadID = $this->_threadID AND UserID = $this->_userID");
		$msgID = $this->_dbCmd->GetValue();
	
		if(empty($msgID))
			return false;
		else
			return true;
	}
	
	// Starts a New thread ... you should be able to attach the thread to a particular item and also specify what users are included
	public function createNewThread(){
		
		if(empty($this->_includeUserIDArr))
			throw new Exception("setIncludeUserIDArr is not allowed empty in createNewThread");
		
		if(empty($this->_refID))
			$this->_refID = 0;
	
		$insertArr 				 = array();	
		$insertArr["Subject"]    = $this->_subject;
		$insertArr["RefID"]      = $this->_refID;
		$insertArr["AttachedTo"] = $this->_attachedTo;
	
		$this->_threadID = $this->_dbCmd->InsertQuery("msgsthreads", $insertArr);
	
		// Specify who is part of this thread
		$this->assignThreadToGroup();
	
		return $this->_threadID;
	}
	

	
	// Loads the Thread from the database
	public function loadThreadByID($threadID) {

		$threadID = intval($threadID);
		
		if(empty($threadID))
			throw new Exception("threadID is not a valid parameter to loadThreadByID");
		if(empty($this->_userID))
			throw new Exception("The user ID has not set in loadThreadByID");	
		
		$this->_dbCmd->Query("SELECT * FROM msgsthreads WHERE ThreadID = " . $threadID);
		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Error in MessageThread->LoadThreadByID");
		
		$row = $this->_dbCmd->GetRow();
			
		// Don't assign the Thread ID to our object until we know it is good.
		$this->_threadID = $threadID;
		$this->_refID 		= $row["RefID"];
		$this->_subject 	= $row["Subject"];
		$this->_attachedTo  = $row["AttachedTo"];

		$this->_includeUserIDArr = self::getUserIDsInThread($this->_threadID);
		
		if(!in_array($this->_userID, $this->_includeUserIDArr ))
			throw new Exception("Can not load Load Message Thread, User ID is not included.");
		
		$messageIDs = $this->getMessageIDsInThread();
		
		$this->_messages = array(); 
		foreach ($messageIDs as $thisMessageID){
			
			$messageObj = new Message($this->_dbCmd);	
			
			// The user has already been authenticated for the entire thread. No need to authenticate the user for each message too.
			$messageObj->loadMessageByID($thisMessageID, false);
			
			$this->_messages[] = $messageObj;
		}

	}	
	
	public function getUserIDWhoSentLastMessage() {
		
		if(empty($this->_threadID))
			throw new Exception("ThreadID is not set for getUserIDWhoSentLastMessage");
		
		$this->_dbCmd->Query("SELECT users.Name FROM users
				INNER JOIN msgsdata ON users.ID = msgsdata.UserID 
				WHERE msgsdata.ThreadID=$this->_threadID
				ORDER BY msgsdata.DateCreated DESC LIMIT 1");
	
		return $this->_dbCmd->GetValue();	
	}
	
	// Append a message to an existing thread ID, returns 0 if the UserID is not in the thread
	public function addMessageToThread(Message $messageObj) {
		
		if(empty($this->_threadID))
			throw new Exception("ThreadID is not set for addMessageToThread");
		
		$userID 		 = $messageObj->getUserID();
		$usersIDsInGroup = self::getUserIDsInThread($this->_threadID);
		
		$messageID_JustInserted = 0;
		
		if(in_array($userID, $usersIDsInGroup)) {
			
			$messageObj->setThreadID($this->_threadID);
	
			$messageID_JustInserted = $messageObj->addMessage();
					
			// It is a new message, so make sure that everyone in the group knows it
			$this->markMessageAsUnreadWithinGroup($messageID_JustInserted);
			
			// Since this person sent the message... then we will mark that they have also read it.
			$this->markMessageAsRead($messageID_JustInserted, $userID);		
		}
		
		return $messageID_JustInserted;			
	}

	
	// This will allow you to add new people into the thread 1 by 1.
	static function addUserToMessageThread($userID, $threadID) {
		
		$threadObj = new MessageThread();
		$threadObj->loadThreadByID($threadID);
		
		$newUserArr = $this->_includeUserIDArr;
		$newUserArr[] = $userID;
		
		$this->setIncludeUserIDArr($newUserArr);
		
		$this->assignThreadToGroup();
	}
	
	
	
	// Returns an array of Message IDs belonging to the thread... and that the user may see  --#
	private function getMessageIDsInThread() {

		if(empty($this->_threadID))
			throw new Exception("Thread ID is not set for getMessageIDsInThread");
		
		$this->_dbCmd->Query("SELECT ID FROM msgsdata WHERE ThreadID = ". $this->_threadID . " ORDER BY ID DESC");

		return $this->_dbCmd->GetValueArr();
	}
		

	// Will return a list of Message IDs that are unread by the UserID set in this object.
	public function getUnReadMessageIDs(){

		if(empty($this->_userID))
			throw new Exception("UserID is not set for getUnReadMessageIDs");
		if(empty($this->_threadID))
			throw new Exception("Thread ID is not set for getUnReadMessageIDs");
	
		$messageIDArr = $this->getMessageIDsInThread();
	
		$retArr = array();
		
		foreach($messageIDArr as $thisMsgID){
			if(!$this->checkIfMessageIsRead($thisMsgID))
				$retArr[] = $thisMsgID;
		}
		return $retArr;
	}
	
	
	
	
	// add an entry to the readMessages table.  It is harmless to call this on a message which has already been read 
	public function markMessageAsRead($messageID, $userID){
			
		$messageID = intval($messageID);
		$userID = intval($userID);
		
		if(empty($messageID))
			throw new Exception("MessageID is not valid for markMessageAsRead");
	
		if(empty($userID))
			throw new Exception("userID is not valid for markMessageAsRead");
	
		$messageID = intval($messageID);
		$userID = intval($userID);	
		
		$this->_dbCmd->Query("DELETE FROM msgsunread WHERE MessageID = $messageID AND UserID = $userID");
	}
	
	
	
	// Instead of marking a particular message as read... it will mark any/all messages as read within a thread.
	// Generally you don't want to use this method for closing a message.
	// ... The reason is that new messages may have arrived while the user was sitting on the page. We wouldn't want to close those because the user may have no idea about them.
	// But this can be usefull for a admins to close large bulks of open messages in extreme cases (if they don't even want to open the message thread).
	public function markMessageAllMessagesReadForUser(){

		if(empty($this->_threadID))
			throw new Exception("Thread ID is not set for markMessageAllMessagesReadForUser");
		if(empty($this->_userID))
			throw new Exception("UserID is not set for markMessageAllMessagesReadForUser");

		$msgArr = $this->getUnReadMessageIDs();
		
		foreach($msgArr as $thisMsgID)
			$this->_dbCmd->Query("DELETE FROM msgsunread WHERE MessageID = $thisMsgID AND UserID = " . $this->_userID);
	}
	

		
	private function assignThreadToGroup() {
	
		if(empty($this->_threadID))
			throw new Exception("Thread ID is not set for assignThreadToGroup");
		
		// Clear out old associations before we put new ones back in.
		$this->_dbCmd->Query("DELETE FROM msgsgroups WHERE ThreadID = " . $this->_threadID);
	
		foreach ($this->_includeUserIDArr as $thisUserID) {
			$insertArr["ThreadID"] = $this->_threadID;
			$insertArr["UserID"]   = $thisUserID;
	
			$this->_dbCmd->InsertQuery("msgsgroups",  $insertArr);
		}
	}
	

	private function checkIfMessageIsRead($messageID){
	
		$messageID = intval($messageID);
		
		if(empty($messageID))
			throw new Exception("MessageID is not valid for markMessageAsRead");
		if(empty($this->_userID))
			throw new Exception("UserID is not set for checkIfMessageIsRead");
			
		$this->_dbCmd->Query("Select COUNT(*) FROM msgsunread WHERE MessageID = $messageID AND UserID = $this->_userID");
	
		if($this->_dbCmd->GetValue() == 0)
			return true;
		else
			return false;
	}
	

	// Will find out which users belong to a thread... and then mark this message as unread for everyone
	private function markMessageAsUnreadWithinGroup($messageID) {
	
		$messageID = intval($messageID);

		if(empty($messageID))
			throw new Exception("$messageID is not valid for markMessageAsUnreadWithinGroup");
		if(empty($this->_threadID))
			throw new Exception("ThreadID is not set for markMessageAsUnreadWithinGroup");
			
		// For each User that belongs to the thread... make a new entry within the UnRead table
		foreach ($this->_includeUserIDArr as $thisUserID) 
			$this->_dbCmd->InsertQuery("msgsunread",  array("MessageID"=>$messageID, "UserID"=>$thisUserID));

	}
	

	public function getLinkSubject() {
	
		$subject = "";
		
		if($this->_attachedTo == "order"){
			$subject = "Order #";
		}
		else if($this->_attachedTo == "project"){
			$subject = "Project P";
		}
		else if($this->_attachedTo == "csitem"){
			$subject = "CS Item # ";
		}
		else if($this->_attachedTo == "chat"){
			$subject = "Chat - C";
		}
		else if($this->_attachedTo == "user"){
			$subject = "User: U";
		}
		else{
			$subject = "";
		}
		return $subject;
	}
	
	public function getLinkURL() {
		
		$URL = "";
		
		if($this->_attachedTo == "order"){
			$URL = "./ad_order.php?orderno=";
		}
		else if($this->_attachedTo == "project"){
			$URL = "./ad_project.php?projectorderid=";
		}
		else if($this->_attachedTo == "csitem"){
			$URL = "./ad_cs_home.php?view=search&csitemid=";
		}
		else if($this->_attachedTo == "chat"){
			$URL = "./ad_chat_search.php?chat_id=";
		}
		else if($this->_attachedTo == "user"){
			$URL = "./ad_users_search.php?customerid=";
		}
		else{
			$URL = "";
		}
		return $URL;
	}
	

	
}

?>

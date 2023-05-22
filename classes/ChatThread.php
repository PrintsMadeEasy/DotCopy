<?php

class ChatThread {
	
	private $dbCmd;
	private $id;
	private $domainID;
	private $csrUserID;
	private $customerUserID;
	private $sessionID;
	private $previousSessionID;
	private $startSessionID;
	private $orderID;
	private $orderLinkSource;
	private $attachmentsCount;
	private $totalCsrMessages;
	private $totalCustomerMessages;
	private $firstCsrMsg;
	private $firstCustomerMsg;
	private $lastCustomerMsg;
	private $lastCsrMsg;
	private $startDate;
	private $closedDate;
	private $status;
	private $chatType;
	private $subject;
	private $closedReason;
	private $ipAddressCustomer;
	private $allowPleaseWait;
	private $customerIsTyping;
	private $csrIsTyping;
	
	private $secondsBeforePleaseWaitMessage;
	private $transferInProgressMessage;
	
	private $avgChatDurationSeconds;
	
	const TIMEOUT_FROM_PING = 45;
	
	const STATUS_New = "N";
	const STATUS_Waiting = "W";
	const STATUS_Active = "A";
	const STATUS_Closed = "C";

	const SUBJECT_Unknown = "U";
	const SUBJECT_WebsiteProblemGeneral = "W";
	const SUBJECT_PaymentProblem = "P";
	const SUBJECT_PricingQuestion = "R";
	const SUBJECT_LowPriceComparison = "L";
	const SUBJECT_Copyrights = "Y";
	const SUBJECT_CouponIssue = "C";
	const SUBJECT_ArtworkAssistance = "A";
	const SUBJECT_EditingTool = "G";
	const SUBJECT_MailingServices = "M";
	const SUBJECT_VariableData = "V";
	const SUBJECT_ArrivalTimes = "T";
	const SUBJECT_ShippingIssue = "S";
	const SUBJECT_LoyaltyProgram = "N";
	const SUBJECT_QualityIssue = "Q";
	const SUBJECT_ColorIssue = "O";
	const SUBJECT_ReturnOrRefund = "F";
	const SUBJECT_TemplateOrCustomDesign = "E";
	const SUBJECT_Other = "H";
	const SUBJECT_OrderModification = "D";
	const SUBJECT_ReprintRequired = "I";
	
	const CLOSE_REASON_CsrTimeout = "C";
	const CLOSE_REASON_CustomerTimeout = "U";
	const CLOSE_REASON_CsrTerminated = "T";
	const CLOSE_REASON_CustomerTerminated = "X";
	const CLOSE_REASON_CsrsWentOffDuty = "D";
	
	const ORDERLINK_SessionHistory = "S";
	const ORDERLINK_ChatText = "T";
	const ORDERLINK_OrderHistory = "H";
	
	const TYPE_AutoVisitorPath = "A";
	const TYPE_Support = "S";
	const TYPE_Artwork = "G";
	const TYPE_Checkout = "C";
	const TYPE_OrderHistory = "H";
	
	function __construct(){
		$this->dbCmd = new DbCmd();
		$this->chatThreadLoadedFlag = false;
		$this->secondsBeforePleaseWaitMessage = 70;
		
		// We will provide an English description for "Transfer in progress" because you are already assigned to a new CSR.
		// For now, we are just tricking the user that we monitor the "people in line"
		// For new chat threads (that haven't assigned been assigned to a CSR)... we will provide send a number through Ajax showing number of people waiting in line
		// ... that way we can leave it up to the Ajax chat client if they want to show that details, or provide a description in their own language.
		$this->transferInProgressMessage = "[Chat Transfer Status] There are 0 people waiting in front of you. \nYou are next in line, please stand by.";
		
		$this->avgChatDurationSeconds = 300;
		
	}
	

	
	static function getChatTypesArr(){
		
		return array(self::TYPE_AutoVisitorPath, self::TYPE_Artwork, self::TYPE_Checkout, self::TYPE_OrderHistory, self::TYPE_Support);
	}
	
	static function getChatTypeDesc($chatType){

		if($chatType == self::TYPE_AutoVisitorPath)
			return "Auto Start Visitor Path";
		else if($chatType == self::TYPE_Support)
			return "Customer Support";
		else if($chatType == self::TYPE_OrderHistory)
			return "Order History";
		else if($chatType == self::TYPE_Artwork)
			return "Artwork Help";
		else if($chatType == self::TYPE_Checkout)
			return "Checkout";
		else
			throw new Exception("Illegal Chat Type Description");
	}
	
	static function getOpenChatThreadsBySelectedDomains(){
		$domainObj = Domain::singleton();
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT COUNT(*) FROM chatthread USE INDEX(chatthread_Status) 
						WHERE (Status='".DbCmd::EscapeSQL(ChatThread::STATUS_Active)."' OR Status='".DbCmd::EscapeSQL(ChatThread::STATUS_Waiting)."' OR Status='".DbCmd::EscapeSQL(ChatThread::STATUS_New)."') 
						AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));
		return $dbCmd->GetValue();
	}
	
	function loadChatThreadById($id, $authFlag = true){
		
		$id = intval($id);
		
		if(empty($id))
			throw new Exception("The chat ID is empty.");
		
		if($authFlag){
			if(!$this->authenticateChatThread($id))
				throw new Exception("The chat thread can not be authenticated.");
		}
		
		$this->dbCmd->Query("SELECT *
						, UNIX_TIMESTAMP(StartDate) as UnixStartDate 
						, UNIX_TIMESTAMP(FirstCsrMsg) as UnixFirstCsrMsg 
						, UNIX_TIMESTAMP(FirstCustomerMsg) as UnixFirstCustomerMsg 
						, UNIX_TIMESTAMP(LastCustomerMsg) as UnixLastCustomerMsg 
						, UNIX_TIMESTAMP(LastCsrMsg) as UnixLastCsrMsg 
						, UNIX_TIMESTAMP(ClosedDate) as UnixClosedDate 
						FROM chatthread WHERE ID=$id");
		
		if($this->dbCmd->GetNumRows() == 0)
			throw new Exception("The Chat ID does not exist.");
						
		$row = $this->dbCmd->GetRow();
		
		$this->id = $row["ID"];
		$this->domainID = $row["DomainID"]; 
		$this->csrUserID = $row["CsrUserID"];
		$this->status = $row["Status"];
		$this->chatType = $row["ChatType"];
		$this->subject = $row["Subject"];
		$this->closedReason = $row["ClosedReason"];
		$this->ipAddressCustomer = $row["CustomerIpAddress"];
		$this->customerUserID = $row["CustomerUserID"];
		$this->sessionID = $row["SessionID"];
		$this->previousSessionID = $row["PreviousSessionID"];
		$this->startSessionID = $row["StartSessionID"];
		$this->orderID = $row["OrderID"];
		$this->orderLinkSource = $row["OrderLinkSource"];
		$this->attachmentsCount = $row["AttachmentsCount"];
		$this->totalCsrMessages = $row["TotalCsrMessages"];
		$this->totalCustomerMessages = $row["TotalCustomerMessages"];
		$this->allowPleaseWait = $row["AllowPleaseWait"] == "Y" ? true : false;
		$this->customerIsTyping = $row["CustomerIsTyping"];
		$this->csrIsTyping = $row["CsrIsTyping"];
		
		// In MySQL, UNIX_TIMESTAMP() does its work on NULL values.
		// Don't set the properities with the Unix Timestamp values unless you are sure that the date field has been set.
		$this->firstCsrMsg = null;
		$this->firstCustomerMsg = null;
		$this->lastCustomerMsg = null;
		$this->lastCsrMsg = null;
		$this->closedDate = null;
		$this->startDate = null;
		
		if(!empty($row["StartDate"]))
			$this->startDate = $row["UnixStartDate"];
		if(!empty($row["FirstCsrMsg"]))
			$this->firstCsrMsg = $row["UnixFirstCsrMsg"];
		if(!empty($row["FirstCustomerMsg"]))
			$this->firstCustomerMsg = $row["UnixFirstCustomerMsg"];
		if(!empty($row["LastCustomerMsg"]))
			$this->lastCustomerMsg = $row["UnixLastCustomerMsg"];
		if(!empty($row["LastCsrMsg"]))
			$this->lastCsrMsg = $row["UnixLastCsrMsg"];
		if(!empty($row["ClosedDate"]))
			$this->closedDate = $row["UnixClosedDate"];
		
	}
	
	function setCustomerIsTyping($isTypingFlag){
		$this->ensureChatLoaded();
		
		if(!is_bool($isTypingFlag))
			throw new Exception("The parameter must be boolean.");
		
		$this->customerIsTyping = $isTypingFlag ? 1 : 0;
		
		$this->dbCmd->UpdateQuery("chatthread", array("CustomerIsTyping"=>$this->customerIsTyping), "ID=" . $this->id);
	}
	
	function setCsrIsTyping($isTypingFlag){
		$this->ensureChatLoaded();
		
		if(!is_bool($isTypingFlag))
			throw new Exception("The parameter must be boolean.");
			
		$this->csrIsTyping = $isTypingFlag ? 1 : 0;
		
		$this->dbCmd->UpdateQuery("chatthread", array("CsrIsTyping"=>$this->csrIsTyping), "ID=" . $this->id);
	}
	
	function getCustomerIsTyping(){
		$this->ensureChatLoaded();
		
		if($this->isChatThreadClosed())
			return false;
			
		return ($this->customerIsTyping ? true : false);
	}
	function getCsrIsTyping(){
		$this->ensureChatLoaded();
		
		if($this->isChatThreadClosed())
			return false;
			
		return ($this->csrIsTyping ? true : false);
	}
	
	
	// The file ID must exist within one of the messages on the chat thread.
	// This will return the binary data.
	function getFileData($fileId){
		
		$this->ensureChatLoaded();
		$this->ensureFileAttachmentExistsOnThread($fileId);
		
		$this->dbCmd->Query("SELECT BinaryTableName, BinaryTableID FROM chatattachmentspointer WHERE ID=" . intval($fileId));
		if($this->dbCmd->GetNumRows() == 0)
			throw new Exception("The Chat File pointer does not exist.");
		$row = $this->dbCmd->GetRow();
		
		$this->dbCmd->Query("SELECT BinaryData FROM " . DbCmd::EscapeSQL($row["BinaryTableName"]) . " WHERE ID=" . intval($row["BinaryTableID"]));
		$fileData = $this->dbCmd->GetValue();
		
		return $fileData;
	}
	function getFileName($fileId){
		
		$this->ensureChatLoaded();
		$this->ensureFileAttachmentExistsOnThread($fileId);
		
		$this->dbCmd->Query("SELECT BinaryTableName, BinaryTableID FROM chatattachmentspointer WHERE ID=" . intval($fileId));
		if($this->dbCmd->GetNumRows() == 0)
			throw new Exception("The Chat File pointer does not exist.");
		$row = $this->dbCmd->GetRow();
		
		$this->dbCmd->Query("SELECT FileName FROM " . DbCmd::EscapeSQL($row["BinaryTableName"]) . " WHERE ID=" . intval($row["BinaryTableID"]));
		return $this->dbCmd->GetValue();
	}
	
	function getFileType($fileId){
		
		$this->ensureChatLoaded();
		$this->ensureFileAttachmentExistsOnThread($fileId);
		
		$this->dbCmd->Query("SELECT BinaryTableName, BinaryTableID FROM chatattachmentspointer WHERE ID=" . intval($fileId));
		if($this->dbCmd->GetNumRows() == 0)
			throw new Exception("The Chat File pointer does not exist.");
		$row = $this->dbCmd->GetRow();
		
		$this->dbCmd->Query("SELECT FileType FROM " . DbCmd::EscapeSQL($row["BinaryTableName"]) . " WHERE ID=" . intval($row["BinaryTableID"]));
		return $this->dbCmd->GetValue();
	}
	
	private function ensureFileAttachmentExistsOnThread($fileId){
		
		$this->dbCmd->Query("SELECT COUNT(*) FROM chatmessages WHERE ChatThreadID=" . intval($this->id) . " AND FileAttachmentID=" . intval($fileId));
		if($this->dbCmd->GetValue() != 1)
			throw new Exception("The File attachment does not exist on this thread.");
	}
	
	function getDomainId(){
		$this->ensureChatLoaded();
		return $this->domainID;
	}
	function getChatId(){
		$this->ensureChatLoaded();
		return $this->id;
	}
	
	private function ensureChatLoaded(){
		if(empty($this->id))
			throw new Exception("The chat thread must be loaded before calling this method.");
	}
	
	// There can be multiple chat threads in a single session. 
	// However, there should not be 2 chat threads from a customer running concurrently.
	// This method only works if it is called by a client HTTP thread.  It will fail if the User is logged in as a Memember (a CSR).
	function doesCustomerHaveOpenChatThread(){
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		if($passiveAuthObj->CheckIfLoggedIn() && $passiveAuthObj->CheckIfUserIDisMember($passiveAuthObj->GetUserID()))
			throw new Exception("CSR's or Members of the system should not call the method doesCustomerHaveOpenChatThread.");
			
		$this->dbCmd->Query("SELECT COUNT(*) FROM chatthread USE INDEX(chatthread_SessionID) WHERE SessionID='".WebUtil::GetSessionID()."' AND Status!='".self::STATUS_Closed."'");
		$openChatCount = $this->dbCmd->GetValue();
		
		if($openChatCount > 1)
			throw new Exception("There is more than one active Chat thread within the same session.");
		
		if($openChatCount == 0)
			return false;
		else
			return true;
	}
	
	// Only works for the Customer.
	function getChatThreadIdOpen(){
		
		if(!$this->doesCustomerHaveOpenChatThread())
			throw new Exception("You can not call this method unless the customer has an open chat thread.");
			
		$this->dbCmd->Query("SELECT ID FROM chatthread USE INDEX(chatthread_SessionID) WHERE SessionID='".WebUtil::GetSessionID()."' AND Status!='".self::STATUS_Closed."' LIMIT 1");
		return $this->dbCmd->GetValue();
	}
	
	// Creates a new chat thread in the DB and returns the ID.
	function createChatThread($chatType){
		
		if(!in_array($chatType, self::getChatTypesArr()))
			throw new Exception("The chat type is invalid.");
		
		if(!empty($this->id))
			throw new Exception("You can not create a new chat thread with an exsiting Chat Thread object.");
			
		if($this->doesCustomerHaveOpenChatThread())
			throw new Exception("There is already a chat thread open for the customer.");
			
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		if($passiveAuthObj->CheckIfLoggedIn() && $passiveAuthObj->CheckIfUserIDisMember($passiveAuthObj->GetUserID()))
			throw new Exception("Members can not create new chat threads, only customers can.");
			
		$customerUserId = 0;
		if($passiveAuthObj->CheckIfLoggedIn())
			$customerUserId = $passiveAuthObj->GetUserID();
			
		// Fall back on Session cookies if a permanent cookie is not found.
		$previousSessionId = WebUtil::GetCookie("PreviousChatSession", WebUtil::GetSessionVar("PreviousChatSession"));
		
		// If there is a Previous Chat Sesssion, then it will have its own "StartSession" which has possibly been propogating.
		// Otherwise, this will be the first Chat Thread, so it will use the user's current Sessions.
		if(!empty($previousSessionId)){
			$previousChatIdsArr = $this->getChatThreadsInSession($previousSessionId);
			$this->dbCmd->Query("SELECT StartSessionID FROM chatthread WHERE ID=". intval(current($previousChatIdsArr)));
			$startSessionId = $this->dbCmd->GetValue();
		}
		else{
			$startSessionId = WebUtil::GetSessionID();
		}	

		$this->domainID = Domain::getDomainIDfromURL(); // Because chat threads can only be started by customers, it is safe to get the DomainID from the URL.	
		$this->csrUserID = 0;

		// Only make the default status go to "New" if it is an auto-start. That is because we want to resever the CSR as soon as possible.
		// For people that choose to launch a chat window for "support", it should go directly into "waiting".
		if($chatType == self::TYPE_AutoVisitorPath)
			$this->status = self::STATUS_New;
		else
			$this->status = self::STATUS_Waiting;
			
		$this->chatType = $chatType;
		$this->subject = self::SUBJECT_Unknown;
		$this->closedReason = null;
		$this->ipAddressCustomer = WebUtil::getRemoteAddressIp();
		$this->customerUserID = $customerUserId;
		$this->sessionID = WebUtil::GetSessionID();
		$this->previousSessionID = $previousSessionId;
		$this->startSessionID = $startSessionId;
		$this->orderID = null;
		$this->orderLinkSource = null;
		$this->attachmentsCount = 0;
		$this->totalCsrMessages = 0;
		$this->totalCustomerMessages = 0;
		$this->firstCsrMsg = null;
		$this->firstCustomerMsg = null;
		$this->lastCustomerMsg = null;
		$this->lastCsrMsg = null;
		$this->closedDate = null;
		$this->startDate = time();
		$this->allowPleaseWait = false;
		
		$newChat["DomainID"] = $this->domainID; 
		$newChat["CsrUserID"] = $this->csrUserID;
		$newChat["Status"] = $this->status;
		$newChat["ChatType"] = $this->chatType;
		$newChat["Subject"] = $this->subject;
		$newChat["ClosedReason"] = $this->closedReason;
		$newChat["CustomerIpAddress"] = $this->ipAddressCustomer;
		$newChat["CustomerUserID"] = $this->customerUserID;
		$newChat["SessionID"] = $this->sessionID;
		$newChat["PreviousSessionID"] = $this->previousSessionID;
		$newChat["StartSessionID"] = $this->startSessionID;
		$newChat["OrderID"] = $this->orderID;
		$newChat["OrderLinkSource"] = $this->orderLinkSource;
		$newChat["AttachmentsCount"] = $this->attachmentsCount;
		$newChat["TotalCsrMessages"] = $this->totalCsrMessages;
		$newChat["TotalCustomerMessages"] = $this->totalCustomerMessages;
		$newChat["FirstCsrMsg"] = $this->firstCsrMsg;
		$newChat["FirstCustomerMsg"] = $this->firstCustomerMsg;
		$newChat["LastCustomerMsg"] = $this->lastCustomerMsg;
		$newChat["LastCsrMsg"] = $this->lastCsrMsg;
		$newChat["ClosedDate"] = $this->closedDate;
		$newChat["StartDate"] = $this->startDate;
		$newChat["AllowPleaseWait"] = $this->allowPleaseWait ? "Y" : "N";
		
		$this->id = $this->dbCmd->InsertQuery("chatthread", $newChat);

		// Add an entry to our openinstances table so we can keep track of stuff with a heartbeat indicator.
		$this->dbCmd->InsertQuery("chatopeninstances", array("LastCustomerPing"=>time(), "LastCsrPing"=>time(), "FirstPingByCsrFlag"=>0, "ChatThreadID" => intval($this->id)));
		
		// Now that we have started a new thread, we should set a cookie on the user's computer.  That way if they get disconnected right now, we will have a record of this session.
		WebUtil::SetCookie("PreviousChatSession", WebUtil::GetSessionID(), 500);
		WebUtil::SetSessionVar("PreviousChatSession", WebUtil::GetSessionID());
		
		// As soon as a chat thread turns to "waiting" status, we want to try and assign the chat thread to a CSR (if available).
		if($this->status == self::STATUS_Waiting){
			
			$this->assignChatThreadsToAvailableCSRs();
			
			// Find out if the status just got changed.
			$this->dbCmd->Query("SELECT CsrUserID FROM chatthread WHERE ID=" . $this->id);
			$this->csrUserID = $this->dbCmd->GetValue();
		}
		
		return $this->id;
	}
	
	function changeSubject($newSubject){
		
		$this->ensureChatLoaded();
		
		if(!array_key_exists($newSubject, self::getChatSubjects()))
			throw new Exception("Illegal Subject for Chat Thread.");
			
		$this->subject = $newSubject;
		$this->dbCmd->UpdateQuery("chatthread", array("Subject"=>$this->subject), ("ID=" . $this->id));
	}
	
	function getSubject(){
		$this->ensureChatLoaded();
		return $this->subject;
	}
	
	function getMessageObj($msgID){
		$this->ensureChatLoaded();
		
		$this->dbCmd->Query("SELECT *, UNIX_TIMESTAMP(Date) as DateUnix FROM chatmessages WHERE ID=" . intval($msgID) . " AND ChatThreadID=" . $this->id);
		if($this->dbCmd->GetNumRows() == 0)
			throw new Exception("The Message ID does not exist for this chat thread.");
		$row = $this->dbCmd->GetRow();
		
		$mObj = new ChatMessage();
		$mObj->chatThreadID = $this->id;
		$mObj->id = $row["ID"];
		$mObj->csrUserID = $row["CsrUserID"];
		$mObj->dateTimeStamp = $row["DateUnix"];
		$mObj->fileAttachmentID = $row["FileAttachmentID"];
		$mObj->forCsrOnlyFlag = $row["ForCsrOnly"];
		$mObj->message = $row["Message"];
		$mObj->personType = $row["PersonType"];
		$mObj->receiptAck = $row["ReceiptAck"];
		$mObj->fileName = "";
		$mObj->fileSize = "";
		
		
		if(!empty($mObj->fileAttachmentID)){
			
			$this->dbCmd->Query("SELECT * FROM chatattachmentspointer WHERE ID=" . $mObj->fileAttachmentID . " AND ChatThreadID=" . $this->id );
			if($this->dbCmd->GetNumRows() != 1)
				throw new Exception("The file attachment ID is not valid.");
			$row = $this->dbCmd->GetRow();
			
			$binaryTableName = $row["BinaryTableName"];
			$binaryTableID = $row["BinaryTableID"];
			
			$this->dbCmd->Query("SELECT FileName, FileSize FROM " . $binaryTableName . " WHERE ID=" . $binaryTableID);
			if($this->dbCmd->GetNumRows() != 1)
				throw new Exception("The file attachment ID pointer is not valid.");
			$row = $this->dbCmd->GetRow();	
			
			$mObj->fileName = $row["FileName"];
			$mObj->fileSize = $row["FileSize"];
		}
		
		
		return $mObj;
	}
	
	// The key to the array is the Chat Subject ID, the value is the description.
	static function getChatSubjects(){
		$a = array();
		$a[self::SUBJECT_ArrivalTimes] = "Arrival Times";
		$a[self::SUBJECT_ArtworkAssistance] = "Artwork Assistance";
		$a[self::SUBJECT_ColorIssue] = "Color Issue";
		$a[self::SUBJECT_Copyrights] = "Copyrights";
		$a[self::SUBJECT_CouponIssue] = "Coupon Issue";
		$a[self::SUBJECT_EditingTool] = "Editing Tool";
		$a[self::SUBJECT_LowPriceComparison] = "Low Price Comparison";
		$a[self::SUBJECT_MailingServices] = "Mailing Services";
		$a[self::SUBJECT_OrderModification] = "Order Modification";
		$a[self::SUBJECT_Other] = "Other";
		$a[self::SUBJECT_PaymentProblem] = "Payment Problem";
		$a[self::SUBJECT_PricingQuestion] = "Pricing Question";
		$a[self::SUBJECT_QualityIssue] = "Quality Issue";
		$a[self::SUBJECT_ReprintRequired] = "Reprint Required";
		$a[self::SUBJECT_ReturnOrRefund] = "Return or Refund";
		$a[self::SUBJECT_ShippingIssue] = "Shipping Issue";
		$a[self::SUBJECT_LoyaltyProgram] = "Super Shipping Saver";
		$a[self::SUBJECT_TemplateOrCustomDesign] = "Template or Custom Design";
		$a[self::SUBJECT_Unknown] = "Unknown";
		$a[self::SUBJECT_VariableData] = "Variable Data";
		$a[self::SUBJECT_WebsiteProblemGeneral] = "Website Problem General";
		return $a;
		
	}
	
	static function getSubjectDesc($subjectChar){
		$chatSubjects = self::getChatSubjects();
		if(!array_key_exists($subjectChar, $chatSubjects))
			return "Undefined Subject";
		else
			return $chatSubjects[$subjectChar];
	}
	static function getClosureDesc($closedChar){

		if($closedChar == self::CLOSE_REASON_CsrsWentOffDuty)
			return "CSR Went Off Duty";
		else if($closedChar == self::CLOSE_REASON_CsrTerminated)
			return "CSR Ended Chat Thread";
		else if($closedChar == self::CLOSE_REASON_CsrTimeout)
			return "CSR Connection Timed Out";
		else if($closedChar == self::CLOSE_REASON_CustomerTerminated)
			return "Customer Terminated";
		else if($closedChar == self::CLOSE_REASON_CustomerTimeout)
			return "Customer Timed Out";
		else
			return "Undefined Closure";

	}
	
	// When you link an order to a chat thread, we need to know how the association came about.
	// Did a user click on a chat button in their order history, or did they paste in a order with hash code.
	function linkToOrder($orderId, $orderLinkSource){
		$this->ensureChatLoaded();
		
		if(!in_array($orderLinkSource, array(self::ORDERLINK_ChatText, self::ORDERLINK_OrderHistory)))
			throw new Exception("Illegal order link source in chat thread.");
		
		$orderId = intval($orderId);
		
		// In case of some kind of a mistake... don't throw an exception.
		// Just don't allow the thread to become linked up.
		if(!Order::checkIfOrderIDexists($orderId))
			return;
		if(Order::getDomainIDFromOrder($orderId) != $this->domainID)
			return;
			
		$this->orderID = $orderId;
		$this->dbCmd->UpdateQuery("chatthread", array("OrderID"=>$this->orderID, "OrderLinkSource"=>$orderLinkSource), ("ID=" . $this->id));
	}
	
	// When an order is placed you can call this method.
	// If there is a chat thread with the same session ID, then we can assume the latest chat thread lead to this order being placed.
	// We are going to look back in the chain of Chat sessions (by cookies)... looking for the last chat thread.
	// Also makes sure not to link by OrderID if that chat thread is already linked to an Order.  For example, they have have opened a chat from their order history before placing a repeat order.
	function possiblyLinkSomeChatIdToOrderBySessionHistory($orderId){
		
		$previousSessionId = WebUtil::GetCookie("PreviousChatSession", WebUtil::GetSessionVar("PreviousChatSession"));
		if(empty($previousSessionId))
			return;
			
		$chatThreadIds = $this->getChatThreadsInSession($previousSessionId);
		if(empty($chatThreadIds))
			return;
		
		$domainIdOfOrderToLink = Order::getDomainIDFromOrder($orderId);
		
		// Get the last chat thread ID (which is the most recent).
		$lastestChatThread = array_pop($chatThreadIds);
		
		$this->dbCmd->Query("SELECT DomainID, OrderID FROM chatthread WHERE ID=" . intval($lastestChatThread));
		if($this->dbCmd->GetNumRows() == 0)
			return;
		$row = $this->dbCmd->GetRow();

		$domainIdOfLastChat = $row["DomainID"];
		$orderIdOfLastChat = $row["OrderID"];
		
		// This shouldn't ever happen. It could indicate some type of a security problem with cross-domain sessions.
		if($domainIdOfOrderToLink != $domainIdOfLastChat){
			WebUtil::WebmasterError("Trying to link a chat from a different domain. OrderID: $orderId", "Illegal Chat Domain Order Link");
			return;
		}
		
		// Don't override an order link. A previous chat may have been linked by a customer service inquiry.
		if(!empty($orderIdOfLastChat))
			return;

		$this->dbCmd->UpdateQuery("chatthread", array("OrderID"=>$orderId, "OrderLinkSource"=>self::ORDERLINK_SessionHistory), ("ID=" . intval($lastestChatThread)));
	}
	
	// There can be Zero or Many Chat Threads during a PHP session.
	// Pass in a PHP session ID and this method will return an array of Chat Thread IDs or an empty array if none exist.
	// The older that thread ID's will be listed first in the return array.
	function getChatThreadsInSession($sessionID){
		
		$sessionID = trim($sessionID);
		if(strlen($sessionID) < 12)
			throw new Exception("PHP Session is not valid");
			
		$this->dbCmd->Query("SELECT ID FROM chatthread WHERE SessionID='" . DbCmd::EscapeLikeQuery($sessionID) . "' ORDER BY ID ASC");
		$returnArr = $this->dbCmd->GetValueArr();
			
		return $returnArr;
	}
	
	// Returns an array of Message IDs in the ChatThread
	// Pass in the last Message ID if you want to only return the new messages (after that point)
	// That is useful for the AJAX clients which don't need to download the entire thread each time... just the latest ones.
	// Message ID's are returned with the oldest ID's on top.
	function getMessageIdsInChatThread($lastMessageId = 0){
		
		$this->ensureChatLoaded();
		
		$this->dbCmd->Query("SELECT ID FROM chatmessages WHERE ChatThreadID='" . $this->id . "' ORDER BY ID ASC");
		$allChatMessageIds = $this->dbCmd->GetValueArr();
		
		if(empty($lastMessageId)){
			// Return all messages in the chat thread
			return $allChatMessageIds;
		}
		else{
			
			if(!in_array($lastMessageId, $allChatMessageIds))
				throw new Exception("The method getMessageIdsInChatThread is being called a with a lastMessageId that isn't in the thread.");
				
			$returnArr = array();
			
			$lastMessageIdFound = false;
			foreach($allChatMessageIds as $thisChatMessageID){
					
				if($lastMessageIdFound)
					$returnArr[] = $thisChatMessageID;
				
				if($thisChatMessageID == $lastMessageId)
					$lastMessageIdFound = true;
			}
			
			return $returnArr;
		}
	}
	

	
	// Will throw an Exception if a CSR or a customer is unable to view the ChatThread.
	function authenticateChatThread($id){
		
		WebUtil::EnsureDigit($id);
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		if($passiveAuthObj->CheckIfLoggedIn() && $passiveAuthObj->CheckIfUserIDisMember($passiveAuthObj->GetUserID())){
			if($this->doesCsrHavePermissionToView($id, $passiveAuthObj->GetUserID()))
				return true;
			else
				throw new Exception("The CSR does not have permission to view this chat thread.");
		}

		// So now we know that this HTTP thread run by a general website user.
		$this->dbCmd->Query("SELECT SessionID FROM chatthread WHERE ID=" . intval($id));
		$sessionIDofChatThread = $this->dbCmd->GetValue();
		
		if($sessionIDofChatThread == WebUtil::GetSessionID())
			return true;
			
		if($passiveAuthObj->CheckIfLoggedIn())
			$userIDofCustomer = $passiveAuthObj->GetUserID();
		else
			$userIDofCustomer = null;
			
		// Get a list of every single Chat Thread ID this user has been part of.
		// If the user only has one active Chat thread in the current session... there will be a Session Variable PreviousChatSession set... and possibly a cookie.
		// If they are logged in... then we can use utilize their UserID to include chat threads from different machines, as long as they were logged in
		$allChatThreadIdsFromUser = $this->getChatThreadIdChain(WebUtil::GetCookie("PreviousChatSession", WebUtil::GetSessionVar("PreviousChatSession")), $userIDofCustomer);
		
		if(in_array($id, $allChatThreadIdsFromUser))
			return true;
			
		return false;
	}
	
	function doesCsrHavePermissionToView($id, $csrUserID){
		
		$this->dbCmd->Query("SELECT DomainID FROM chatthread WHERE ID=" . intval($id));
		$chatDomainID = $this->dbCmd->GetValue();
		
		if(empty($chatDomainID))
			throw new Exception("There is not a Domain ID for this chat thread.");
			
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		$domainIDsForUser = $passiveAuthObj->getUserDomainsIDs($csrUserID);
		
		if(!$passiveAuthObj->CheckIfUserIDisMember($csrUserID))
			throw new Exception("The CSR is not a member in the system.");
		
		if(!in_array($chatDomainID, $domainIDsForUser))
			return false;
		else
			return true;
	}
	
	
	// Should be called from chat client on an interval. (i.e. heartbeat).
	function pingFromCustomer(){
		$this->ensureChatLoaded();
		$this->dbCmd->UpdateQuery("chatopeninstances", array("LastCustomerPing"=>time()), ("ChatThreadID=" . intval($this->id)));
		
		// If someone loses their internet connection, chances are the other party still has theirs.
		// Use the Ping from the other party to test activity on the other end (instead of using a Cron).
		// Don't check the CSR ping if it hasn't been assigned to a CSR yet.
		if(!$this->isChatThreadClosed() && !empty($this->csrUserID)){
			$this->dbCmd->Query("SELECT UNIX_TIMESTAMP(LastCsrPing) FROM chatopeninstances WHERE ID=" . intval($this->id));
			$lastCsrPing = $this->dbCmd->GetValue();
			if(time() - $lastCsrPing > self::TIMEOUT_FROM_PING){
				$this->closeChatThread(self::CLOSE_REASON_CsrTimeout);
				
				// In case the CSR closed their browser without "logging off", we should set their status to offline to be careful.
				$chatCsrObj = ChatCSR::singleton($this->csrUserID);
				$chatCsrObj->changeStatusToOffline();
				
				NavigationHistory::recordPageVisit($this->dbCmd, $this->csrUserID, "ChatEnd", $this->id);
			}
		}
		
		// If the customer is waiting... find out if all of the CSR's went offline.
		// The customer could have been waiting while CSR's were 'online'... and then the CSR changed their chat status.
		// If all CSR's chagne to "offline"... the user shoudl be booted out of the chat system.
		if($this->status == self::STATUS_Waiting){
			$csrsOnlineArr = ChatCSR::getCSRsOnline($this->domainID, array($this->chatType));
			if(empty($csrsOnlineArr)){
				$this->closeChatThread(self::CLOSE_REASON_CsrsWentOffDuty);
			}
		}
	}
	function pingFromCsr(){
		$this->ensureChatLoaded();
		
		if($this->status == self::STATUS_Closed)
			return;
		
		// FirstPingByCsrFlag let's us know that a CSR has pinged it through an API at least once.
		// When we transfer an active chat thread we can set to back to 0 to speed up the time it takes to launch the pop-up window (don't rely on "delinguent threads" to launch pop-up).
		// We can't use the "LastCsrPing" time becaue we have to start it out with a real date.  We can't measure against the LastPingDate if we don't know how long the customer was in "waiting" status.
		$this->dbCmd->UpdateQuery("chatopeninstances", array("LastCsrPing"=>time(), "FirstPingByCsrFlag"=>1), ("ChatThreadID=" . intval($this->id)));
	
		// Find out the time the time the last CSR was sent. We may may need to send an auto-reply.
		if($this->allowPleaseWait && !empty($this->lastCsrMsg) && (time() - $this->lastCsrMsg) > $this->secondsBeforePleaseWaitMessage){
			$this->sendCsrPleaseWaitMessage();
		}
		
		// This works slightly different than pings from Customers.
		// In case the server goes down... it could disconnect both parties simultaneously.
		// Whenever the CSR is connected it will try and clean up all of its open Chat Threads.
		// It might be a little more inefficient, but whenever the CSR re-connects to the chat module it will be sure to clean it up.
		$chatCsrObj = ChatCSR::singleton($this->csrUserID);
		$openChatThreadsByCsr = $chatCsrObj->getChatThreadIdsOpenByCsr();
		
		foreach($openChatThreadsByCsr as $thisChatThreadId){
			$this->dbCmd->Query("SELECT UNIX_TIMESTAMP(LastCustomerPing) FROM chatopeninstances WHERE ID=" . intval($thisChatThreadId));
			$lastCustomerPing = $this->dbCmd->GetValue();
			if(time() - $lastCustomerPing > self::TIMEOUT_FROM_PING){
				$secondChatThreadObj = new ChatThread();
				$secondChatThreadObj->loadChatThreadById($thisChatThreadId, false);
				$secondChatThreadObj->closeChatThread(self::CLOSE_REASON_CustomerTimeout);
			}
		}
		
		
	}
	
	// returns true or false
	function getAllowPleaseWait(){
		$this->ensureChatLoaded();
		return $this->allowPleaseWait;
	}

	function setAllowPleaseWait($allowFlag){
		$this->ensureChatLoaded();
		
		if(!is_bool($allowFlag))
			throw new Exception("Only boolean allowed");
			
		if($allowFlag)
			$this->allowPleaseWait = true;
		else
			$this->allowPleaseWait = false;
			
		$this->dbCmd->UpdateQuery("chatthread", array("AllowPleaseWait" => ($this->allowPleaseWait ? "Y" : "N")), "ID=$this->id");
	}
	
	// Returns TRUE is both the CSR and the End User have not closed the chat session.
	function isChatThreadClosed(){
		$this->ensureChatLoaded();
		
		if($this->status == self::STATUS_Closed)
			return true;
		else
			return false;
	}
	
	// When a message has been sent, the other client must respond letting us know that they got it.
	function acknowledgeMessageReceived($messageID){
		$this->ensureChatLoaded();
		
		// The update query should be WHERE'ed against both the MessageID and the ChatThreadID to make sure that the message ID is authentic.
		$this->dbCmd->UpdateQuery("chatmessages", array("ReceiptAck"=>"1"), ("ID=" . intval($messageID) . " AND ChatThreadID=" . intval($this->id)));
	}
	
	function isAttachmentTypeLegal($fileName){
		$fileName = self::filterFileAttachmentName($fileName);
		
		if(FileUtil::CheckIfFileNameIsLegal($fileName))
			return true;
		else 
			return true;		
	}
	
	private function cleanMessageForDb($msg){
		// Make sure that back slashes are removed in chat messages to avoid javascript hacks.
		$msg = preg_replace("/\\\\/", "", $msg);
		$msg = preg_replace("/[<>]/", "", $msg);
		
		// Temporarily replace line breaks with a place holder while we strip out all ASCII characters Below 32.
		$msg = preg_replace("/(\n)/", "LINEBREAK", $msg);
		$msg = WebUtil::FilterData($msg, FILTER_SANITIZE_STRING_ONE_LINE);
		$msg = preg_replace("/LINEBREAK/", "<br>", $msg);
		
		$maxMessageLength = 15 * 1024;
		if(strlen($msg) > $maxMessageLength)
			$msg = substr($msg, 0, $maxMessageLength);
		
		return $msg;
	}
	
	// Will save the message to the database immediately
	function addCustomerMessage($msg){
		
		$this->ensureChatLoaded();

		if($this->isChatThreadClosed())
			throw new Exception("Thread must be open before adding a new message");
			
		$insertArr["ChatThreadID"] = $this->id;
		$insertArr["CsrUserID"] = null;
		$insertArr["PersonType"] = "C"; // C = Customer, E = Employee
		$insertArr["ReceiptAck"] = 0;
		$insertArr["ForCsrOnly"] = 0;
		$insertArr["Date"] = time();
		$insertArr["Message"] = $this->cleanMessageForDb($msg);
		$newMessageID = $this->dbCmd->InsertQuery("chatmessages", $insertArr);
		
		// If we don't have any customer messages yet... then we have to set the Start Date.
		if($this->totalCustomerMessages == 0){
			$this->firstCustomerMsg = time();
			$this->dbCmd->UpdateQuery("chatthread", array("FirstCustomerMsg"=>$this->firstCustomerMsg), ("ID=" . $this->id));
			
			// Change from a "new" status to a "waiting" status upon the first message. A chat session will become after a CSR responds.
			if($this->status == self::STATUS_New){
				$this->status = self::STATUS_Waiting; 
				$this->dbCmd->UpdateQuery("chatthread", array("FirstCustomerMsg"=>$this->firstCustomerMsg, "Status"=>$this->status), ("ID=" . $this->id));
			}
			
			// As soon as a chat thread turns to "waiting" status, we want to try and assign the chat thread to a CSR (if available).
			$this->assignChatThreadsToAvailableCSRs();
		}
		
		if(empty($this->customerUserID)){
			$passiveAuthObj = Authenticate::getPassiveAuthObject();
			if($passiveAuthObj->CheckIfLoggedIn()){
				$this->customerUserID = $passiveAuthObj->GetUserID();
				$this->dbCmd->UpdateQuery("chatthread", array("CustomerUserID"=>$this->customerUserID), ("ID=" . $this->id));
			}
		}

		
		// Increment the message counter
		$this->totalCustomerMessages++;
		$this->lastCustomerMsg = time();
		$this->dbCmd->UpdateQuery("chatthread", array("TotalCustomerMessages"=>$this->totalCustomerMessages, "LastCustomerMsg"=>$this->lastCustomerMsg), ("ID=" . $this->id));

		return $newMessageID;
	}
	
	// Will save the message to the database immediately
	// Sometimes CSRs may want to post something on a chat thread without letting the customer see it.
	// They may do this before transfering the Chat ownership... such as a brief summary.
	function addCsrMessage($msg, $visibleByCustomer = true, $overrideCsrUserId = null){
		
		$this->ensureChatLoaded();
		
		if($this->isChatThreadClosed())
			throw new Exception("Thread must be active before adding a new message");
			
		// If this thread isn't assigned to a CSR yet... assign it to the first CSR who sends a message.
		if(empty($this->csrUserID)){
			
			if(!empty($overrideCsrUserId)){
				$this->csrUserID = intval($overrideCsrUserId);
			}
			else{
				$passiveAuthObj = Authenticate::getPassiveAuthObject();
				if(!$passiveAuthObj->CheckIfUserIDisMember($passiveAuthObj->GetUserID()))
					throw new Exception("The user must be a member to take control of the chat thread.");
					
				if(!$this->doesCsrHavePermissionToView($this->id, $passiveAuthObj->GetUserID()))
					throw new Exception("CSR does not have permission to see this Chat thread for the domain.");
				
				$this->csrUserID = $passiveAuthObj->GetUserID();
			}
			
			// Before assigning the CSR to the thread... make sure that we up date the "Ping" time.  
			// The reason is that the customer may "poll" before the CSR has "polled". If the system sees that a CSR's has been an assigned with a stale "ping" timestamp it will disconnect.
			$this->dbCmd->UpdateQuery("chatopeninstances", array("LastCsrPing"=>time(), "FirstPingByCsrFlag"=>0), ("ChatThreadID=" . $this->id));
					
			// Update the CSR with a Database Query (instead of calling the method $this->assignChatToCsr()) ... because this method does not require a chat thread to be loaded.
			$chatCsrObj = ChatCSR::singleton($this->csrUserID);
			$this->dbCmd->UpdateQuery("chatthread", array("CsrUserID"=>$this->csrUserID), ("ID=" . $this->id));
			$chatCsrObj->updateStatusOfCsr();
		}
		
			
		if(!empty($overrideCsrUserId))
			$csrUserId = intval($overrideCsrUserId);
		else
			$csrUserId = $this->csrUserID;
			
		$insertArr["ChatThreadID"] = $this->id;
		$insertArr["CsrUserID"] = $csrUserId;
		$insertArr["PersonType"] = "E"; // C = Customer, E = Employee
		$insertArr["ReceiptAck"] = 0;
		$insertArr["ForCsrOnly"] = ($visibleByCustomer ? "0" : "1");
		$insertArr["Date"] = time();
		$insertArr["Message"] = $this->cleanMessageForDb($msg);
		$newMessageID = $this->dbCmd->InsertQuery("chatmessages", $insertArr);
		
		// If we don't have any CSR messages yet... then we have to set the Start Date.
		if($this->totalCsrMessages == 0){
			$this->firstCsrMsg = time();
			$this->status = self::STATUS_Active; // A Chat becomes active as soon as a CSR responds to the client.
			$this->dbCmd->UpdateQuery("chatthread", array("FirstCsrMsg"=>$this->firstCsrMsg, "Status"=>$this->status), ("ID=" . $this->id));
		}
		
		// Increment the message counter
		$this->totalCsrMessages++;
		$this->lastCsrMsg = time();
		$this->dbCmd->UpdateQuery("chatthread", array("TotalCsrMessages"=>$this->totalCsrMessages, "LastCsrMsg"=>$this->lastCsrMsg), ("ID=" . $this->id));
		
		return $newMessageID;
	}
	
	// Adds a random "Please Wait" message (but tries not to repeat the last one).
	function sendCsrPleaseWaitMessage(){
		
		$this->ensureChatLoaded();
		
		if(empty($this->csrUserID))
			return;
			
		// Make sure that the last CSR message came from the person that is assigned to the thread
		// If someone gets transfered to a new CSR we don't want it saying "I am still working on this".
		$this->dbCmd->Query("SELECT CsrUserID FROM chatmessages WHERE ChatThreadID=" . intval($this->id) . " AND PersonType='E' ORDER BY ID DESC LIMIT 1");
		$lastCsrUserWhoResponded = $this->dbCmd->GetValue();
		
		// If there is a CSR User assigned to this thread... but they aren't the last person that responded, then it must be a transfer in process.
		if($lastCsrUserWhoResponded != $this->csrUserID){
			$newMessageID = $this->addCsrMessage($this->transferInProgressMessage);
			// A "Pleast Wait ID" of 1 always means they are waiting to be transfered.
			$this->dbCmd->UpdateQuery("chatmessages", array("PleaseWaitMessageID"=>1), ("ID=" . $newMessageID));
			return;
		}

			
		$chatCSRObj = ChatCSR::singleton($this->csrUserID);
	
		$pleaseWaitMessagesArr = $chatCSRObj->getPleaseWaitMessages($this->domainID);
		

		// Get all message ID's within this chat thread. We want to try and avoid sending the same "please wait" message 2 times in a row.
		$pleaseWaitMessagesUsedArr = array();
		$allMessageIdsInThisChatThread = $this->getMessageIdsInChatThread();
		
		foreach($allMessageIdsInThisChatThread as $thisMessageId){
			
			$this->dbCmd->Query("SELECT PleaseWaitMessageID FROM chatmessages WHERE ID=" . intval($thisMessageId));
			$plsWtMsgId = $this->dbCmd->GetValue();
			if(!empty($plsWtMsgId))
				$pleaseWaitMessagesUsedArr[] = $plsWtMsgId;
		}

		// This will find all "Please Wait" message ID's that haven't been used yet.
		$plsWaitMesagesNotUsedYetArr = array_diff(array_keys($pleaseWaitMessagesArr), $pleaseWaitMessagesUsedArr);
		
	
		// If there are unused messages, this pick the first one.
		if(!empty($plsWaitMesagesNotUsedYetArr)){
			$pleaseWaitMessageId = current($plsWaitMesagesNotUsedYetArr);
		}
		else{
			// Count the number of times each one of the Please Wait messages was used. We would prefer to use the one which has been used the least
			$messagesUsedCount = array_count_values($pleaseWaitMessagesUsedArr);
			
			// If the value is one here, then it means all of the messages have been used an equal amount of time.
			// In that case, we want to roll back to the first Pls Wait messages used.
			if(sizeof(array_unique($messagesUsedCount)) == 1){
				
				// The oldest messages are on the top, so this will give us the first value used.
				$pleaseWaitMessageId = $pleaseWaitMessagesUsedArr[0];
			}
			else{
				// Othwerise take the message which has the lowest count.
				asort($messagesUsedCount);
				$pleaseWaitMessageId = key($messagesUsedCount);
			}
			
		}

		// Add the text for the CSR to the chat thread. After it returns us the new Message ID, update the message row with an ID of the Pls Wait message.		
		$newMessageID = $this->addCsrMessage($pleaseWaitMessagesArr[$pleaseWaitMessageId]);
		
		$this->dbCmd->UpdateQuery("chatmessages", array("PleaseWaitMessageID"=>$pleaseWaitMessageId), ("ID=" . $newMessageID));
		
	}
	
	
	function addAttachmentFromCustomer($binaryData, $fileName, $attachmentNote = ""){
		
		$this->ensureChatLoaded();
		
		$fileName = self::filterFileAttachmentName($fileName);
		
		if(!$this->isAttachmentTypeLegal($fileName))
			throw new Exception("You must ensure that the attachment type is legal before calling this method.");
		
		$attachmentID = $this->insertAttachmentIntoDb($binaryData, $fileName);
		
		// The Attachment Note is basically a message.  After we have the new message ID, we can update the Message row with the attachment ID.
		$newMsgId = $this->addCustomerMessage($attachmentNote);
		
		$this->dbCmd->UpdateQuery("chatmessages", array("FileAttachmentID"=>$attachmentID), ("ID=" . $newMsgId));
		
		$this->increaseAttachmentCount();
	}
	
	function addAttachmentFromCsr($binaryData, $fileName, $attachmentNote = "", $overrideUserId = null){
		
		$this->ensureChatLoaded();
		
		if(empty($this->csrUserID))
			throw new Exception("A CSR must be assigned to this thread before calling this method.");
		
		$fileName = self::filterFileAttachmentName($fileName);
		
		if(!$this->isAttachmentTypeLegal($fileName))
			throw new Exception("You must ensure that the attachment type is legal before calling this method.");
			
		$attachmentID = $this->insertAttachmentIntoDb($binaryData, $fileName);
		
		// The Attachment Note is basically a message.  After we have the new message ID, we can update the Message row with the attachment ID.
		$newMsgId = $this->addCsrMessage($attachmentNote, true, $overrideUserId);
		
		$this->dbCmd->UpdateQuery("chatmessages", array("FileAttachmentID"=>$attachmentID), ("ID=" . $newMsgId));
		
		$this->increaseAttachmentCount();
	}
	
	private function increaseAttachmentCount(){
		$this->attachmentsCount++;
		$this->dbCmd->UpdateQuery("chatthread", array("AttachmentsCount"=>$this->attachmentsCount), ("ID=" . $this->id));
	}
	
	// Inserts the attachment into the DB based upon current table rotations.  
	// Return the attachment pointer ID.
	private function insertAttachmentIntoDb($binaryData, $fileName){
		
		$fileName = self::filterFileAttachmentName($fileName);
		
		$binaryTableName = self::getChatAttachmentsTableName();
		
		$insertArr["BinaryData"] = $binaryData;
		$insertArr["FileName"] = $fileName;
		$insertArr["FileSize"] = strlen($binaryData);
		$insertArr["FileType"] = FileUtil::getMimeTypeByFileNameStr($fileName);
		
		$binaryTableID = $this->dbCmd->InsertQuery($binaryTableName, $insertArr);
				
		$pointerArr["BinaryTableID"] =  $binaryTableID;
		$pointerArr["ChatThreadID"] =  $this->id;
		$pointerArr["BinaryTableName"] = $binaryTableName;
		
		$pointerTableID = $this->dbCmd->InsertQuery("chatattachmentspointer", $pointerArr);
		
		return $pointerTableID;
	}
	
	// Since Binary tables may grow large and be rotated frequently, this table keeps track of which one is currently active (not archieved)
	// Find out the active binary table. Tables are rotated to cap out file size.
	static function getChatAttachmentsTableName(){
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT TableNameWithSuffix FROM tablerotations WHERE RootTableName='chatattachments'");
		$tableName = $dbCmd->GetValue();
		
		if(empty($tableName))
			throw new Exception("Empty Table name in function ImageLib::GetChatAttachmentsTableName");
		
		return $tableName ;
	}
	

	static function getChatCountByUser($dbCmd, $userID){

		$dbCmd->Query("SELECT COUNT(*) FROM chatthread WHERE CustomerUserID=" . intval($userID));
		return $dbCmd->GetValue();
	}
	
	
	// Returns the number of customers which are waiting ahead in the queue.
	// If all CSR's are occupied, but there there are no other customers waiting in front, this method will return 0.
	// If there are no CSR's online, this method will return 0.
	function getNumberOfCustomersWaitingAhead(){
		$this->ensureChatLoaded();
		
		if($this->status != self::STATUS_Waiting)
			return 0;
			
		$csrsOnline = ChatCSR::getCSRsOnline($this->domainID, array($this->chatType));
		if(empty($csrsOnline))
			return 0;
		
		$this->dbCmd->Query("SELECT ID FROM chatthread WHERE Status='" . ChatThread::STATUS_Waiting . "' AND DomainID=" . intval($this->domainID));
		$chatIDsWaiting = $this->dbCmd->GetValueArr();
		
		$returnCount = 0;
		
		// Chat ID's (which are lower than the current Chat Thread ID)... mean that they were started first, and therefore first in line.
		foreach($chatIDsWaiting as $thisChatWaitingID){
			if($thisChatWaitingID < $this->id)
				$returnCount++;
		}
		
		return $returnCount;
	}
	
	// Returns number of seconds the user can expect to wait, based upon the number of CSR's online, and people waiting in front.
	function estimateWaitTimeSeconds(){
		
		$this->ensureChatLoaded();
		
		if($this->status != self::STATUS_Waiting)
			return 0;
			
		$numberOfPeopleInQueue = $this->getNumberOfCustomersWaitingAhead();
		$csrsAvailable = ChatCSR::getCSRsOnline($this->domainID, array($this->chatType));
		
		if(empty($csrsAvailable))
			return 0;
			
		return round($numberOfPeopleInQueue * $this->avgChatDurationSeconds / sizeof($csrsAvailable));
	}
	
	
	
	// Call this method every time that a chat is closed, or when a new CSR comes online.
	// This method will assign a chat thread to the next CSR, when available.
	// It works for all domains.  A chat thread does not need to be loaded.
	function assignChatThreadsToAvailableCSRs(){
		
		$this->dbCmd->Query("SELECT ID,DomainID,ChatType,CsrUserID FROM chatthread WHERE Status='" . ChatThread::STATUS_Waiting . "'");
		
		// Store results in a temp array so that we can re-use the database handle below.
		$chatRowsWaitingArr = array();
		while($row = $this->dbCmd->GetRow()){
			// You can have a Chat Thread in "waiting" status, even if it is assigned to a CSR.
			// The doesn't change to "active" until the CSR types the first reply.
			if(empty($row["CsrUserID"]))
				$chatRowsWaitingArr[] = $row;
		}
		
		foreach($chatRowsWaitingArr as $thisChatRow){
			
			// Find out if there are any "dead chat threads" from customers who got tired of waiting.
			$this->dbCmd->Query("SELECT UNIX_TIMESTAMP(LastCustomerPing) FROM chatopeninstances WHERE ID=" . intval($thisChatRow["ID"]));
			$lastCustomerPing = $this->dbCmd->GetValue();
			if(time() - $lastCustomerPing > self::TIMEOUT_FROM_PING){
				$secondChatThreadObj = new ChatThread();
				$secondChatThreadObj->loadChatThreadById($thisChatRow["ID"], false);
				$secondChatThreadObj->closeChatThread(self::CLOSE_REASON_CustomerTerminated);
				continue;
			}
			
			$csrsOnline = ChatCSR::getCSRsAvailable($thisChatRow["DomainID"], array($thisChatRow["ChatType"]));
			
			// If there are no CSR's online for the given Domain ID, we can't assign a chat thread.
			if(empty($csrsOnline))
				continue;
				
			// Prefer the CSR's with the least amount of chat threads open.
			$maxOpenChatCount = 0;
			$minOpenChatCount = 1000;
			
			foreach($csrsOnline as $thisCsrId){
	
				$chatCsrObj = ChatCSR::singleton($thisCsrId);
				$openThreadsByCsr = sizeof($chatCsrObj->getChatThreadIdsOpenByCsr());
	
				if($openThreadsByCsr < $minOpenChatCount)
					$minOpenChatCount = $openThreadsByCsr;
				if($openThreadsByCsr > $maxOpenChatCount)
					$maxOpenChatCount = $openThreadsByCsr;
			}
		
			
			
			// Only filter the array of "online CSR's" if we know that some have more open chat threads than others.
			if($maxOpenChatCount != $minOpenChatCount){
				$filterArr = array();
				
				foreach($csrsOnline as $thisCsrId){
					$chatCsrObj = ChatCSR::singleton($thisCsrId);
					$openThreadsByCsr = sizeof($chatCsrObj->getChatThreadIdsOpenByCsr());
					
					if($openThreadsByCsr > $minOpenChatCount)
						continue;
					$filterArr[] = $thisCsrId;
				}
				
				$csrsOnline = $filterArr;
			}
			

			// Make sure not to favor a certain CSR, just because their auto-increment ID is lower.
			shuffle($csrsOnline);
			$newCsrUserId = current($csrsOnline);
			
			// Before assigning the CSR to the thread... make sure that we up date the "Ping" time.  
			// The reason is that the customer may "poll" before the CSR has "polled". If the system sees that a CSR's has been an assigned with a stale "ping" timestamp it will disconnect.
			$this->dbCmd->UpdateQuery("chatopeninstances", array("LastCsrPing"=>time(), "FirstPingByCsrFlag"=>0), ("ChatThreadID=" . intval($thisChatRow["ID"])));
					
			// Update the CSR with a Database Query (instead of calling the method $this->assignChatToCsr()) ... because this method does not require a chat thread to be loaded.
			$chatCsrObj = ChatCSR::singleton($newCsrUserId);
			$this->dbCmd->UpdateQuery("chatthread", array("CsrUserID"=>$newCsrUserId), ("ID=" . $thisChatRow["ID"]));
			
			// Send a greeting message from the CSR.  However, don't do this for "Auto Visitor Paths" chat types.  The reason is that we have already fooled them with AI.
			if($thisChatRow["ChatType"] != self::TYPE_AutoVisitorPath && $this->totalCsrMessages == 0){
				$chatCsrObj = ChatCSR::singleton($newCsrUserId);
				$greetingMessage = $chatCsrObj->getGreetingMessage($thisChatRow["DomainID"]);
				
				$chatThreadObj = new ChatThread();
				$chatThreadObj->loadChatThreadById($thisChatRow["ID"]);
				
				if(!empty($greetingMessage)){
					$chatThreadObj->addCsrMessage($greetingMessage, true);

					// Technically, the CSR hasn't added a message yet, just because they sent an auto-greeting.
					$this->totalCsrMessages = 0;
					$this->dbCmd->UpdateQuery("chatthread", array("TotalCsrMessages"=>$this->totalCsrMessages), ("ID=" . $thisChatRow["ID"]));
				}
			}
			
			// Maybe the CSR will not have a "full" status after the new assignment.
			$chatCsrObj->updateStatusOfCsr();
		}
	}

	
	function assignChatToCsr($csrUserId){
		
		$this->ensureChatLoaded();
		
		if(!$this->doesCsrHavePermissionToView($this->id, $csrUserId))
			throw new Exception("The CSR does not have permission to view this Chat Thread");
			
		$chatCsrObj = ChatCSR::singleton($csrUserId);
		if($chatCsrObj->isCsrOffline())
			throw new Exception("You can not transfer a Chat to a CSR who is offline.");
			
		// Set the "first ping" flag back to 0/false.  That will help the pop-up launch as soon as possible for the new CSR.
		$this->dbCmd->UpdateQuery("chatopeninstances", array("FirstPingByCsrFlag"=>0), ("ChatThreadID=" . $this->id));
			
		$this->csrUserID = $csrUserId;
		$this->dbCmd->UpdateQuery("chatthread", array("CsrUserID"=>$this->csrUserID), ("ID=" . $this->id));
		
		// Maybe the CSR will not have a "full" status after the new assignment.
		$chatCsrObj->updateStatusOfCsr();
	}
	
	function getCsrUserId(){
		$this->ensureChatLoaded();
		return $this->csrUserID;
	}
	// Returns null if we don't know the customer's User ID.  They may not have created an account yet.
	function getCustomerUserId(){
		$this->ensureChatLoaded();
		return $this->customerUserID;
	}
	// This returns the Session ID of the user... not the CSR. CSR's also have session ID's... but we don't record them into the DB.
	function getSessionId(){
		$this->ensureChatLoaded();
		return $this->sessionID;
	}
	// When a user establishes a new session. There may be a cookie set on their machine which contains the session ID used during their last Chat.
	function getPreviousSessionID(){
		$this->ensureChatLoaded();
		return $this->previousSessionID;
	}
	

	// Pass in the earliest PHP Session ID... it will return an array of SessionID's that had active chat threads going forward.
	// The oldest sessions will be listed in the beginning of the array.
	function getSessionIdChainForward($startFromSessionId){
		
		$startFromSessionId = trim($startFromSessionId);
		if(strlen($startFromSessionId) < 12)
			throw new Exception("PHP Session is not valid");
			
		$this->dbCmd->Query("SELECT COUNT(*) FROM chatthread WHERE SessionID='" . DbCmd::EscapeLikeQuery($startFromSessionId) . "'");
		if($this->dbCmd->GetValue() < 1)
			throw new Exception("The start PHP Session is not valid: $startFromSessionId");
			
		$sessionIdsArr = array();
		$sessionIdsArr[] = $startFromSessionId;
		
		$nextSessionIdLink = $startFromSessionId;
		while(true){
			
			$this->dbCmd->Query("SELECT DISTINCT SessionID FROM chatthread WHERE PreviousSessionID='" . DbCmd::EscapeLikeQuery($nextSessionIdLink) . "' AND SessionID != '".DbCmd::EscapeLikeQuery($nextSessionIdLink)."'");
			if($this->dbCmd->GetNumRows() == 0)
				break;
				
			$nextSessionIdLink = $this->dbCmd->GetValue();
			$sessionIdsArr[] = $nextSessionIdLink;
		}
		
		return $sessionIdsArr;
	}
	
	// Similar to getSessionIdChain. This will return a list of all ChatThreadId's from a user based upon Session ID's linked through cookies of return visits.
	// Pass in a parameter of "aggregateByUserId" if you want to intermingle Sessions with UserIDs.
	// It is possible that the user could have used 2 separate computers with different Session chains... but they were logged in on both machines.
	// Pass in 1 sessionID of the user that had an active chat thread in it.
	// ... If you want to provide a chat history list for the user (and they don't have an active chat in this session)... pass in a session ID which had an active Chat thread (look for a cookie).
	// The latest chat threads are listed at the end of the array.
	function getChatThreadIdChain($sessionIdOfUser, $aggregateByUserId = null){
		
		$allStartSessionIdsArr = array();
		
		if(!empty($aggregateByUserId)){
			$this->dbCmd->Query("SELECT StartSessionID FROM chatthread WHERE CustomerUserID=" . intval($aggregateByUserId));
			$allStartSessionIdsArr = $this->dbCmd->GetValueArr();
		}
		
		if(!empty($sessionIdOfUser)){
			$this->dbCmd->Query("SELECT StartSessionID FROM chatthread WHERE SessionID='" . DbCmd::EscapeSQL($sessionIdOfUser) . "'");
			$allStartSessionIdsArr = array_merge($this->dbCmd->GetValueArr(), $allStartSessionIdsArr);
		}
		
		$allStartSessionIdsArr = array_unique($allStartSessionIdsArr);	
		
		// Build up a complete list of Session IDs from this User that have been daisy chained together.
		$allSessionIdsFromUserArr = array();
		foreach ($allStartSessionIdsArr as $thisStartSessionID){
			$allSessionIdsFromUserArr = array_merge($allSessionIdsFromUserArr, $this->getSessionIdChainForward($thisStartSessionID));
		}
		
		// Now get a list of all Chat IDs from each one of the sessions.
		$allChatThreadIds = array();
		foreach($allSessionIdsFromUserArr as $thisSessionFromUser){
			$allChatThreadIds = array_merge($allChatThreadIds, $this->getChatThreadsInSession($thisSessionFromUser));
		}
		
		$allChatThreadIds = array_unique($allChatThreadIds);
		sort($allChatThreadIds);
		
		return $allChatThreadIds;
	}
	
	
	// We can automatically link chats to order ID's if we sense an order hash, etc.
	function getOrderId(){
		$this->ensureChatLoaded();
		return $this->orderID;
	}
	function getAttachmentsCount(){
		$this->ensureChatLoaded();
		return $this->attachmentsCount;
	}
	function getTotalCsrMessages(){
		$this->ensureChatLoaded();
		return $this->totalCsrMessages;
	}
	function getTotalCustomerMessages(){
		$this->ensureChatLoaded();
		return $this->totalCustomerMessages;
	}
	
	// Returns Unix Timestamp
	function getStartDate(){
		$this->ensureChatLoaded();
		return $this->startDate;
	}
	
	// Returns the unix timestamp of First CSR Message in the Thread.
	function getDateFirstCsrMsg(){
		$this->ensureChatLoaded();
		return $this->firstCsrMsg;
	}
	// Returns the unix timestamp of First Customer Message in the Thread.
	function getDateFirstCustomerMsg(){
		$this->ensureChatLoaded();
		return $this->firstCustomerMsg;
	}
	// Returns Unix Timestamp
	function getDateLastCsrMsg(){
		$this->ensureChatLoaded();
		return $this->lastCsrMsg;
	}
	// Returns Unix Timestamp
	function getDateLastCustomerMsg(){
		$this->ensureChatLoaded();
		return $this->lastCustomerMsg;
	}
	// Returns Unix Timestamp
	function getClosedDate(){
		$this->ensureChatLoaded();
		
		if($this->status != self::STATUS_Closed)
			throw new Exception("You can't get the closed date until the status is set to closed.");
		
		return $this->closedDate;
	}
	
	function getStatus(){
		$this->ensureChatLoaded();
		return $this->status;
	}
	

	function closeChatThread($closedReason){
		$this->ensureChatLoaded();
		
		if(!in_array($closedReason, array(self::CLOSE_REASON_CsrTerminated, self::CLOSE_REASON_CsrTimeout, self::CLOSE_REASON_CsrsWentOffDuty, self::CLOSE_REASON_CustomerTerminated, self::CLOSE_REASON_CustomerTimeout)))
			throw new Exception("The closed reason is not valid");
		
			
		// If the CSR purposefully closes the chat thread, then show the Sign-off message to the user (if the CSR has one saved)..
		if($closedReason == self::CLOSE_REASON_CsrTerminated){
			$chatCsrObj = ChatCSR::singleton($this->csrUserID);
			$signOffMessage = $chatCsrObj->getSignOffMessage($this->domainID);
			
			if(!empty($signOffMessage))
				$this->addCsrMessage($signOffMessage, true);
		}
			
		if($this->status == self::STATUS_Closed)
			return;
			
		$this->status = self::STATUS_Closed;
		$this->closedReason = $closedReason;
		
		// If there is a timeout, the "closed date" should be the last ping time from that user.
		if($this->closedReason == self::CLOSE_REASON_CustomerTimeout){
			$this->dbCmd->Query("SELECT UNIX_TIMESTAMP(LastCustomerPing) AS LastCustomerPing FROM chatopeninstances WHERE ChatThreadID=" . intval($this->id));
			$this->closedDate = $this->dbCmd->GetValue();
		}
		else if($this->closedReason == self::CLOSE_REASON_CsrTimeout){
			$this->dbCmd->Query("SELECT UNIX_TIMESTAMP(LastCsrPing) AS LastCsrPing FROM chatopeninstances WHERE ChatThreadID=" . intval($this->id));
			$this->closedDate = $this->dbCmd->GetValue();
		}
		else{
			$this->closedDate = time();
		}
		
		
		$this->dbCmd->UpdateQuery("chatthread", array("Status"=>$this->status, "ClosedDate"=>$this->closedDate, "ClosedReason"=>$this->closedReason), ("ID=" . $this->id));
		
		$this->dbCmd->Query("DELETE FROM chatopeninstances WHERE ChatThreadID=" . intval($this->id));
		
		// Update the CSR status.  They may become available after this chat thread was closed.
		if(!empty($this->csrUserID)){
			$chatCsrObj = ChatCSR::singleton($this->csrUserID);
			$chatCsrObj->updateStatusOfCsr();
			
			// If the CSR has just become available, they may be able to take a new chat thread on.
			$this->assignChatThreadsToAvailableCSRs();
		}
	}
	
	function getChatType(){
		return $this->chatType;
	}
	
	function getClosedReason(){
		
		if($this->status != self::STATUS_Closed)
			throw new Exception("The status must be closed before calling this method.");
		
		return $this->closedReason;
		
	}

	private static function filterFileAttachmentName($fileName){
		
		$cleanFileName = FileUtil::CleanFileName($fileName);
		
		if(empty($cleanFileName))
			throw new Exception("The file name can not be empty");
			
		return $cleanFileName;
	}
}


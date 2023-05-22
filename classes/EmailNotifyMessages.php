<?php


class EmailNotifyMessages {
	
	private $dbCmd;
	private $domainID  = 0;
	private $messageID = 0;
	private $body      = "";
	private $subject   = "";
	private $headers   = "";
	private $fromName  = "";
	private $fromEmail   = "";
	private $isHTML    = false;
	private $userID    = 0;
	private $createdByUserID    = 0;
	private $lastEditedByUserID = 0;	
	private $lastEditedOnDate = null;
	private $createdOnDate  = null;
	private $isActive       = false;
	private $dateRangeStart = null;
	private $dateRangeEnd   = null;
	private $messageDatabase = array();
	private $dataLoadedFlag = false;
	

	function __construct($domainID = null){
		
		if(empty($domainID)){
			$this->domainID = Domain::oneDomain();
		}
		else {
			if(!Domain::checkIfDomainIDexists($domainID))
				throw new Exception("Domain ID does not exist in EmailNotifyMessages");
				
			$this->domainID = $domainID;
		}
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		if(!$passiveAuthObj->CheckIfUserCanViewDomainID($this->domainID))
			throw new Exception("The user can not view the domain.");
		
		
		$this->dbCmd = new DbCmd();
		
		 srand(microtime()*1000000);
	}
	
	function loadMessageForToday(){
		
		// First priority Date Range... otherwise get one without a date range.
		
		$todaysDate = date("Y-m-d", time());	
		$todaysDateALL = EmailNotifyMessages::getSubstituteYearALL() . date("-m-d", time());	
		
		$this->dbCmd->Query( "SELECT ID FROM emailnotifymessages WHERE DomainID=$this->domainID AND Active='Y' AND (((DateRangeStart<='$todaysDate') AND (DateRangeEnd>='$todaysDate')) OR ((DateRangeStart<='$todaysDateALL') AND (DateRangeEnd>='$todaysDateALL')))" );
		$messageOfTodayIDsArr = $this->dbCmd->GetValueArr();
		
		if(empty($messageOfTodayIDsArr)) 
			throw new Exception("No active messages available to send !");	
		
		$randomMessageID   = rand(0, (sizeof($messageOfTodayIDsArr) -1));
		$selectedMessageId = $messageOfTodayIDsArr[$randomMessageID];
		
		$this->loadMessageByID($selectedMessageId);	
	}

	function setUserID($userID) {

		$userID = intval($userID);
		
		if(empty($userID))
			throw new Exception("Invalid UserID.");	
		
			$this->userID = $userID;
	}

	function setMessage($message){
		
		$this->body = $message;
	}

	function setFromName($fromName){
		
		$this->fromName = $fromName;
	}
	
	function setFromEmail($fromEmail){
		
		$this->fromEmail = $fromEmail;
	}
	
	function setSubject($subject) {
	
		$this->subject = $subject;
	}
	
	function setTypeHTML($html=true) {		
		
		$this->isHTML = $html ? true : false;
	}

	function setActive($active=true) {
		
		$this->isActive = $active ? true : false;
	}
	
	// Takes UnixTimestamp
	function setDateRange($startDate, $endDate) {

		$this->dateRangeStart = $startDate;
		$this->dateRangeEnd  = $endDate;
	}
	
	function getFromName(){
		
		return $this->fromName;
	}
	
	function getFromEmail(){
		
		return $this->fromEmail;
	}

	function getDateRangeStart() {

		return $this->dateRangeStart;
	}
	
	function getDateRangeEnd() {

		return $this->dateRangeEnd;
	}
	
	
	function getMessage() {
	
		return $this->body;
	}
	
	function getMessageID() {

		return $this->messageID;
	}
	
	static function getMessageListIds(array $domainIDsArr, $dateRangeStart, $dateRangeEnd, $activeInactive) {

		$activeInactive = intval($activeInactive);
		
		if(empty($domainIDsArr))
			throw new Exception("You must pass in an array of one or more Domain IDs.");
			
		foreach($domainIDsArr as $thisDomainID){
			if(!Domain::checkIfDomainIDexists($thisDomainID))
				throw new Exception("This Domain does not exist: $thisDomainID");
		}
		
		$selectDateMySQL = "";
		if(!empty($dateRangeStart))
			$selectDateMySQL = " (CreatedOnDate > '" . date("YmdHis", $dateRangeStart) . "' AND CreatedOnDate < '" . date("YmdHis", $dateRangeEnd + 86400) ."') AND ";
		
		$activeInactiveSQL = "";
		if($activeInactive==1)
			$activeInactiveSQL = " Active = 'Y' AND ";	
		if($activeInactive==2)
			$activeInactiveSQL = " Active = 'N' AND ";	
			
		$dbCmd = new DbCmd();		
			
		$dbCmd->Query("SELECT ID FROM emailnotifymessages WHERE $selectDateMySQL $activeInactiveSQL". DbHelper::getOrClauseFromArray("DomainID", $domainIDsArr) . " ORDER BY CreatedOnDate DESC");
		$messageIdArr = $dbCmd->GetValueArr();
		
		return $messageIdArr;	
	}
	
	static function getDomainIdFromMessageID($messageID){
		
		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT DomainID FROM emailnotifymessages WHERE ID = " . intval($messageID));
		if($dbCmd->GetNumRows() == 0)
			throw new Exception("The message ID does not exist.");
			
		return $dbCmd->GetValue();
	}
	
	
	function getCreatorUserID() {
		
		if(!$this->dataLoadedFlag)
			throw new Exception("Message not loaded");

		return $this->createdByUserID;
	}
	
	function getCreationDate() {
		
		if(!$this->dataLoadedFlag)
			throw new Exception("Message not loaded");

		return $this->createdOnDate;
	}
	
	
	function getLastUsedDate() {
		
		$this->dbCmd->Query("SELECT UNIX_TIMESTAMP(Date) AS DateUsed FROM emailnotifyhistory WHERE MessageID = $this->messageID ORDER BY Date DESC LIMIT 1");
		return $this->dbCmd->GetValue();
	}
	
	
	function getLastEditorUserID() {
		
		if(!$this->dataLoadedFlag)
			throw new Exception("Message not loaded");

		return $this->lastEditedByUserID;
	}
	
	function getLastEditedDate() {
		
		if(!$this->dataLoadedFlag)
			throw new Exception("Message not loaded");

		return $this->lastEditedOnDate;
	}
		
	function getSubject() {
	
		return $this->subject;
	}
	
	function getIsActive() {
	
		return $this->isActive;
	}

	function getIsHTML() {
	
		return $this->isHTML;
	}
	
	// To keep Date Fields in the database we define a year that acts a "ALL"
	static function getSubstituteYearALL() {
		return 2003;
	}
	
	
	
	function loadMessageByID($messageID){

		$messageID = intval($messageID);
		
		if(empty($messageID))
			throw new Exception("Invalid MessageID.");	
		
		$this->dbCmd->Query("SELECT FromName,FromEmail,ID,Body,Subject,HTMLText,Active,UNIX_TIMESTAMP(DateRangeStart) AS DateRangeStartUnix,UNIX_TIMESTAMP(DateRangeEnd) AS DateRangeEndUnix,UNIX_TIMESTAMP(LastEditedOnDate) AS LastEditedOnDateUnix,UNIX_TIMESTAMP(CreatedOnDate) AS CreatedOnDateUnix,CreatedByUserID,LastEditedByUserID FROM emailnotifymessages WHERE ID = $messageID");
		
		if($this->dbCmd->GetNumRows() == 0)
			throw new Exception("Error Loading Message");

		$row = $this->dbCmd->GetRow();
		
		$this->messageID        = $row["ID"];
		$this->body             = $row["Body"];
		$this->subject          = $row["Subject"];
		$this->isHTML           = $row["HTMLText"] == "H" ? true : false; 
		$this->isActive         = $row["Active"]   == "Y" ? true : false;
		$this->dateRangeStart   = $row["DateRangeStartUnix"];
		$this->dateRangeEnd     = $row["DateRangeEndUnix"];
		$this->lastEditedOnDate = $row["LastEditedOnDateUnix"];
		$this->createdOnDate    = $row["CreatedOnDateUnix"];
		$this->createdByUserID  = $row["CreatedByUserID"];	
		$this->lastEditedByUserID = $row["LastEditedByUserID"];	
		$this->fromName			= $row["FromName"];	
		$this->fromEmail		= $row["FromEmail"];	
			
		$this->dataLoadedFlag = true;
	}
	
	private function fillDBFields() {
	
		if(empty($this->body))
			throw new Exception("Body is empty.");	

		if(empty($this->subject))
			throw new Exception("Subject is empty.");		
			
		if(empty($this->userID))
			throw new Exception("Invalid UserID.");	
			
		$this->messageDatabase = array();	
		
		$this->messageDatabase["Body"] = $this->body;
		$this->messageDatabase["Subject"] = $this->subject;
		$this->messageDatabase["HTMLText"] = $this->isHTML ? "H" : "T"; 
		$this->messageDatabase["Active"] = $this->isActive ? "Y" : "N";
		$this->messageDatabase["DateRangeStart"] = $this->dateRangeStart;
		$this->messageDatabase["DateRangeEnd"]   = $this->dateRangeEnd;
		$this->messageDatabase["LastEditedOnDate"] = time();
		$this->messageDatabase["LastEditedByUserID"] = $this->userID;
		$this->messageDatabase["FromName"]  = $this->fromName;	
		$this->messageDatabase["FromEmail"] = $this->fromEmail;	
	}
	
	function saveNewMessage() {
		
		if(!empty($this->messageID))
			throw new Exception("MessageID must be empty.");

		$this->fillDBFields();		
		
		$this->messageDatabase["CreatedOnDate"] = time();
		$this->messageDatabase["CreatedByUserID"] = $this->userID;		
		$this->messageDatabase["DomainID"] = $this->domainID;
		
		$this->messageID = $this->dbCmd->InsertQuery("emailnotifymessages", $this->messageDatabase);
		
		return $this->messageID;
	}
	
	function updateMessage(){
		
		if(empty($this->messageID))
			throw new Exception("MessageID is not set.");
		
		$this->fillDBFields();
		$this->dbCmd->UpdateQuery("emailnotifymessages", $this->messageDatabase, "ID = $this->messageID");
	}
	
	static function uploadPicture($messageID, $binaryData, $originalFileName) {

		if(empty($messageID))
			throw new Exception("No messageID");
			
		if(empty($binaryData))
			exit ("No binary data was supplied for the attachment.");	
			
		$tempImageName = FileUtil::newtempnam(Constants::GetTempDirectory(), "EMAILIMG", "");
		
		// Put image data into the temp file 
		$fp = fopen($tempImageName, "w");
		fwrite($fp, $binaryData);
		fclose($fp);

		$ImageFormat = ImageLib::GetImageFormatFromFile($tempImageName);

		if(!in_array($ImageFormat, array("JPEG", "PNG", "GIF")))
			throw new Exception("Illegal File Type for image upload.");
		
		system(Constants::GetUpperLimitsShellCommand(150, 30) . Constants::GetPathToImageMagick() . "mogrify -format jpeg -colorspace RGB -quality 80 " . $tempImageName);
		$tempImageName .= ".jpeg";
		
		$imageDim = ImageLib::GetDimensionsFromImageFile($tempImageName);

		$actualWidth  = $imageDim["Width"];
		$actualHeight = $imageDim["Height"];
		$maxWidth = 150;
		
		if($actualWidth > $maxWidth)
			system(Constants::GetUpperLimitsShellCommand(150, 30) . Constants::GetPathToImageMagick() . "mogrify -quality 80 -geometry " . $maxWidth . "x" . $actualHeight . " " . $tempImageName);
		
		$thumbBinaryData = file_get_contents($tempImageName);
		unlink($tempImageName);

		$dbCmd = new DbCmd();	
			
		$messagePicture["DateAdded"]     = time();
		$messagePicture["BinaryData"]    = $binaryData;
		$messagePicture["BinaryThumb"]   = $thumbBinaryData;
		$messagePicture["Width"]    	 = $actualWidth;
		$messagePicture["Height"]   	 = $actualHeight;
		$messagePicture["Filename"]      = FileUtil::CleanFileName($originalFileName); 
		$messagePicture["FileSize"]      = strlen($binaryData);
		$messagePicture["MessageID"]     = $messageID;
			
		$dbCmd->InsertQuery("emailnotifypictures", $messagePicture);
	}
	
	static function deletePicture($pictureId) {
	
		$pictureId = intval($pictureId);
					
		$dbCmd = new DbCmd();
		
		$dbCmd->Query("SELECT MessageID FROM emailnotifypictures WHERE ID = $pictureId");
		$messageId = strtoupper($dbCmd->GetValue());
		
		$dbCmd->Query("SELECT Body FROM emailnotifymessages WHERE ID = $messageId");
		$body = strtoupper($dbCmd->GetValue());
		
		if(!preg_match("/{PICTURE". $pictureId ."}/", $body))
			$dbCmd->Query("DELETE FROM emailnotifypictures WHERE ID = $pictureId");		
	}
	
	function getMessagePictureIds(){
		
		if(empty($this->messageID))
			throw new Exception("MessageID is not set.");
		
		$this->dbCmd->Query( "SELECT ID FROM emailnotifypictures WHERE MessageID = $this->messageID ORDER BY ID" );
		$pictureIdArr = $this->dbCmd->GetValueArr();
		
		return $pictureIdArr;
	}
	
	static function getSecuredPictureLink($pictureID, $thumb = FALSE){
		
		$pictureID = intval($pictureID);
		
		if(empty($pictureID))
			throw new Exception("PictureID not set.");
		
		$dbCmd = new DbCmd();	
			
		$dbCmd->Query( "SELECT Filename FROM emailnotifypictures where ID = $pictureID");
		$pictureFilename = $dbCmd->GetValue();
		
		if($thumb)
			$partialMD5 = substr( MD5( $pictureFilename . $pictureID . Constants::getGeneralSecuritySalt() ),12,10);
		else	
			$partialMD5 = substr( MD5( $pictureFilename . $pictureID . Constants::getGeneralSecuritySalt() ), 4,10);
		
		$pictureLink = "esPic_" . $partialMD5. "_" . FileUtil::CleanFileName($pictureFilename);
		
		$pathToFileOnDisk = Constants::GetFileAttachDirectory() . "/" . $pictureLink;
		
		if(!file_exists($pathToFileOnDisk)) {
			
			if($thumb)
				$dbCmd->Query("SELECT BinaryThumb FROM emailnotifypictures WHERE ID = $pictureID");	
			else 
				$dbCmd->Query("SELECT BinaryData FROM emailnotifypictures WHERE ID = $pictureID");	
			
	    	file_put_contents($pathToFileOnDisk, $dbCmd->GetValue());
		}
			
		return $pictureLink;
	}
	
	static function getPictureWidth($pictureID){
	
		$pictureID = intval($pictureID);
		if(empty($pictureID))
			throw new Exception("PictureID not set.");
			
		$dbCmd = new DbCmd();	
		$dbCmd->Query( "SELECT Width FROM emailnotifypictures where ID = $pictureID");
		return $dbCmd->GetValue();
	}

	static function getPictureHeight($pictureID){
	
		$pictureID = intval($pictureID);
		if(empty($pictureID))
			throw new Exception("PictureID not set.");
			
		$dbCmd = new DbCmd();	
		$dbCmd->Query( "SELECT Height FROM emailnotifypictures where ID = $pictureID");
		return $dbCmd->GetValue();
	}
	
	static function getIndexOfDomain($domainKey)
	{	
		$domainKey    = strtoupper($domainKey);
		$domainKeyLen = strlen($domainKey);
		
		if((intval($domainKeyLen/2)*2) == $domainKeyLen) {
			
			$trackIndex = ord(substr($domainKey,($domainKeyLen/2)-1,1))-65;
		}
		else {
			$trackIndex = ord(substr($domainKey,($domainKeyLen/2)+1,1))-65;	
		}
		
		// Numbers 0..9 -> index 0 .. 9
		if(($trackIndex>=-17) && ($trackIndex<=-8))
			$trackIndex = $trackIndex + 17;
		
		if(($trackIndex<0) || ($trackIndex>26))
			$trackIndex = 0;

		return $trackIndex;
	}	

	private function getTrackURLOfDomain($domainKey) 
	{	
		$trackRequestURIArr = array(
		"tracker.php", "main/track.php", "tracking.php", "gettrc.php", "traco.php",  
		"tracker.asp", "main/gettrack.asp", "scripts/tracking.asp", "gettrc.asp", "traco.asp",  
		"tracker.cgi", "main/track.cgi", "main/gettracking.cgi", "gettrc.cgi", "traco.cgi",  
		"tracker.cfm", "scripts/track.cfm", "tracking.cfm", "scripts/gettrc.cfm", "gettraco.cfm",  
		"tracker.aspx", "scripts/track.aspx", "tracking.aspx", "main/gettrc.aspx", "main/traco.aspx",  
		"gettrac.php", "tracode.php");
		
		$trackQueryStringArr = array(
		"t=", "track=", "track=", "tc=", "trc=",  
		"ct=", "track=", "track=", "tmc=", "trco=",  
		"t=", "tracker=", "track=", "tc=", "trc=",  
		"t=", "track=", "tracking=", "trc=", "trco=",  
		"t=", "track=", "track=", "tracking=", "traco=",  
		"tc=", "trc=");
		
		if(Constants::GetDevelopmentServer())
		return "emailNotify_tracking.php?id=";
			else		
		return $trackRequestURIArr[self::getIndexOfDomain($domainKey)]."?".$trackQueryStringArr[self::getIndexOfDomain($domainKey)];
	}

	private function getUnsubscribeURLOfDomain($domainKey) {	
		
		$unsubscribeRequestURIArr = array(
		"unsubscribe.php", "main/unsub.php", "optout.php", "setuns.php", "unsuco.php",  
		"unsubscribe.asp", "main/setunsub.asp", "scripts/unsubscr.asp", "optout.asp", "unsubme.asp",  
		"unsubscribe.cgi", "main/unsub.cgi", "main/optout.cgi", "optout.cgi", "unsu.cgi",  
		"unsubscribe.cfm", "scripts/unsubs.cfm", "optout.cfm", "scripts/gettrc.cfm", "setunsub.cfm",  
		"unsubscribe.aspx", "scripts/unsub.aspx", "optout.aspx", "main/unsubscribe.aspx", "main/optout.aspx",  
		"unsub.php", "unsubcode.php");
		
		$unsubscribeQueryStringArr = array(
		"emailid=", "id=", "eid=", "email=", "email=",  
		"email=", "uid=", "uid=", "emailid=", "emailid=",  
		"u=", "emailid=", "emailunid=", "mailid=", "email=",  
		"email=", "unsubid=", "msid=", "eid=", "eid=",  
		"u=", "mid=", "usidemail=", "unemail=", "idemail=",  
		"email=", "si=");
		
		if(Constants::GetDevelopmentServer())
		return "emailNotify_unsubscribe.php?email=";
			else
		return $unsubscribeRequestURIArr[self::getIndexOfDomain($domainKey)]."?".$unsubscribeQueryStringArr[self::getIndexOfDomain($domainKey)];
	}

	private function getOrderURLOfDomain($domainKey) {	
		
		$orderRequestURIArr = array(
		"order.php", "main/ord.php", "deal.php", "getorder.php", "ordernow.php",  
		"ordernow.asp", "main/order.asp", "scripts/order.asp", "optout.asp", "order.asp",  
		"buynow.cgi", "main/buy.cgi", "main/buy.cgi", "dobuy.cgi", "order.cgi",  
		"order.cfm", "scripts/buynow.cfm", "buynow.cfm", "scripts/toporder.cfm", "buynow.cfm",  
		"offer.aspx", "scripts/ordernow.aspx", "getit.aspx", "main/myorder.aspx", "main/order.aspx",  
		"order.php", "offer.php");
		
		$orderQueryStringArr = array(
		"id=", "id=", "oid=", "emailid=", "emailid=",  
		"emailid=", "oid=", "uid=", "emailno=", "emailid=",  
		"o=", "custid=", "order=", "mailid=", "offer=",  
		"o=", "emailid=", "oid=", "eid=", "oidd=",  
		"is=", "orderid=", "ordemail=", "oid=", "oiemail=",  
		"oid=", "oi=");
		
		if(Constants::GetDevelopmentServer())
		return "emailNotify_recordOrderClick.php?id=";
			else
		return $orderRequestURIArr[self::getIndexOfDomain($domainKey)]."?".$orderQueryStringArr[self::getIndexOfDomain($domainKey)];
	}
	
	static function getClickCookieName($domainKey) 
	{	
		// TODO: Create unique Cookie names that are not in use yet
		$clickCookieNameArr = array(
		"OrderInfoClick", "ClickOrderMail", "MailClick", "IncomingOrder", "IdFromMessage",  
		"OrderInfoClick", "ClickOrderMail", "MailClick", "IncomingOrder", "IdFromMessage",  
		"OrderInfoClick", "ClickOrderMail", "MailClick", "IncomingOrder", "IdFromMessage",  
		"OrderInfoClick", "ClickOrderMail", "MailClick", "IncomingOrder", "IdFromMessage",  
		"OrderInfoClick", "ClickOrderMail", "MailClick", "IncomingOrder", "IdFromMessage",  
		"CookieEmailClick", "CookieVar");
		
		if(Constants::GetDevelopmentServer())
		return "EmailNotifyJobHistoryID";
			else		
		return $clickCookieNameArr[self::getIndexOfDomain($domainKey)];
	}
	
	
	function getEmailHeaders() {
		
		return $this->headers;
	}
	
	function getEmailSourceWithInline($domainID)
	{	
		if(empty($this->messageID))
			throw new Exception("No messageID");
				
		$htmlMessage = $this->getMessage();		
		$textMessage = strip_tags($htmlMessage);  
			
		$MimeObj = new Mail_mime();
		
		$this->dbCmd->Query("SELECT ID,BinaryData,Filename FROM emailnotifypictures WHERE MessageID = $this->messageID");
		while($row = $this->dbCmd->GetRow()) {
	
			$imagePregLabel = "/{Picture" . $row[ "ID" ]. "}/";
			
			if(preg_match($imagePregLabel, $htmlMessage)) {
				
				$cleanPictureFilename =  FileUtil::CleanFileName($row[ "Filename" ]);
				
				$pathToFileOnDisk = Constants::GetFileAttachDirectory() . "/" . $cleanPictureFilename;
				
				file_put_contents($pathToFileOnDisk, $row[ "BinaryData" ]);
				$MimeObj->addHTMLImage($pathToFileOnDisk,"image/gif");	
				$htmlMessage = preg_replace($imagePregLabel, ("cid:" . $MimeObj->getLastHTMLImageCid()), $htmlMessage);
			
				@unlink($pathToFileOnDisk);
			}				
		}	
		

		$domainKey = Domain::getDomainKeyFromID($domainID);
		
		$htmlMessage = preg_replace("/{TRACK}/","http://" . Domain::getWebsiteURLforDomainID($domainID) . "/" . $this->getTrackURLOfDomain($domainKey) . "\n" . "{TRACK_ID}", $htmlMessage);		
		$htmlMessage = preg_replace("/{UNSUBSCRIBE}/","http://" . Domain::getWebsiteURLforDomainID($domainID) . "/" . $this->getUnsubscribeURLOfDomain($domainKey) . "\n" .  "{UNSUBSCRIBE_EMAIL}", $htmlMessage);
		$htmlMessage = preg_replace("/{ORDERLINK}/","http://" . Domain::getWebsiteURLforDomainID($domainID) . "/" . $this->getOrderURLOfDomain($domainKey) . "\n" . "{ORDERCODE}", $htmlMessage);
	
		$MimeObj->setTXTBody($textMessage);
		$MimeObj->setHTMLBody($htmlMessage);
		$MimeObj->setSubject($this->getSubject());
		
		$body    = $MimeObj->get();
		
		$domainEmailConfigObj = new DomainEmails($domainID);
		
		if(Constants::GetDevelopmentServer())
			$MimeObj->setFrom($domainEmailConfigObj->getEmailAddressOfType(DomainEmails::REMINDER));
		else 
			$MimeObj->setFrom($domainEmailConfigObj->getEmailNameOfType(DomainEmails::REMINDER) ." <" . $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::REMINDER) . ">");
			
		$MimeObj->setSubject($this->getSubject());
		$this->headers = $MimeObj->headers();


		$this->headers["Message-Id"] =  "<" . substr(md5(uniqid(microtime())), 0, 15) . "@" . $domainKey . ">";
				
		// Outlook doesn't recognize it as an inline (but as attachemnt) if we have an @domain.com in the Content-ID: !!! mine.php generates cid@domian.com other mailers just cid
		$body = preg_replace("/%40" . $domainKey . "/i","",$body);
	
		return $body;
	}

	function getJobCountOfMessage() {
		
		if(empty($this->messageID))
			return 0;
		
		$this->dbCmd->Query("SELECT COUNT(*) AS JobCount FROM emailnotifyjob WHERE MessageID = $this->messageID");
		return $this->dbCmd->GetValue();	
	}

	function getTrackingCountOfMessage() {
		
		if(empty($this->messageID))
			return 0;
		
		$this->dbCmd->Query("SELECT COUNT(*) AS TrackingSum FROM emailnotifytracking WHERE MessageID = $this->messageID");
		return $this->dbCmd->GetValue();	
	}
	
	function getEmailCountOfMessage() {

		if(empty($this->messageID))
			return 0;
		
		$this->dbCmd->Query("SELECT COUNT(*) AS EmailCount FROM emailnotifyhistory WHERE MessageID = $this->messageID");
		return $this->dbCmd->GetValue();	
	}
	
	function getClickCountOfMessage() {

		if(empty($this->messageID))
			return 0;
						
		$this->dbCmd->Query("SELECT COUNT(*) AS EmailCount FROM emailnotifyorders WHERE MessageID = $this->messageID");
		return $this->dbCmd->GetValue();	
	}	

	function getOrderCountOfMessage() {

		if(empty($this->messageID))
			return 0;
						
		$this->dbCmd->Query("SELECT COUNT(*) As OrderCount from emailnotifyhistory, orders WHERE (emailnotifyhistory.ID = orders.EmailNotifyJobHistoryID) AND emailnotifyhistory.MessageID=" . $this->messageID);
		return $this->dbCmd->GetValue();	
	}	
	
	function getDomainId() {
		
		return $this->domainID;
	}
	
		
	/*	//  Copy Arrays and it will generate the htaccess Rewrite Rules
	 
		$trackRequestURIArr = array(	
		"tracker.php", "main/track.php", "tracking.php", "gettrc.php", "traco.php",  
		"tracker.asp", "main/gettrack.asp", "scripts/tracking.asp", "gettrc.asp", "traco.asp",  
		"tracker.cgi", "main/track.cgi", "main/gettracking.cgi", "gettrc.cgi", "traco.cgi",  
		"tracker.cfm", "scripts/track.cfm", "tracking.cfm", "scripts/gettrc.cfm", "gettraco.cfm",  
		"tracker.aspx", "scripts/track.aspx", "tracking.aspx", "main/gettrc.aspx", "main/traco.aspx",  
		"gettrac.php", "tracode.php");
		
		$trackQueryStringArr = array(
		"t=", "track=", "track=", "tc=", "trc=",  
		"ct=", "track=", "track=", "tmc=", "trco=",  
		"t=", "tracker=", "track=", "tc=", "trc=",  
		"t=", "track=", "tracking=", "trc=", "trco=",  
		"t=", "track=", "track=", "tracking=", "traco=",  
		"tc=", "trc=");
	
		$unsubscribeRequestURIArr = array(
		"unsubscribe.php", "main/unsub.php", "optout.php", "setuns.php", "unsuco.php",  
		"unsubscribe.asp", "main/setunsub.asp", "scripts/unsubscr.asp", "optout.asp", "unsubme.asp",  
		"unsubscribe.cgi", "main/unsub.cgi", "main/optout.cgi", "optout.cgi", "unsu.cgi",  
		"unsubscribe.cfm", "scripts/unsubs.cfm", "optout.cfm", "scripts/gettrc.cfm", "setunsub.cfm",  
		"unsubscribe.aspx", "scripts/unsub.aspx", "optout.aspx", "main/unsubscribe.aspx", "main/optout.aspx",  
		"unsub.php", "unsubcode.php");
		
		$unsubscribeQueryStringArr = array(
		"emailid=", "id=", "eid=", "email=", "email=",  
		"email=", "uid=", "uid=", "emailid=", "emailid=",  
		"u=", "emailid=", "emailunid=", "mailid=", "email=",  
		"email=", "unsubid=", "msid=", "eid=", "eid=",  
		"u=", "mid=", "usidemail=", "unemail=", "idemail=",  
		"email=", "si=");

		$orderRequestURIArr = array(
		"order.php", "main/ord.php", "deal.php", "getorder.php", "ordernow.php",  
		"ordernow.asp", "main/order.asp", "scripts/order.asp", "optout.asp", "order.asp",  
		"buynow.cgi", "main/buy.cgi", "main/buy.cgi", "dobuy.cgi", "order.cgi",  
		"order.cfm", "scripts/buynow.cfm", "buynow.cfm", "scripts/toporder.cfm", "buynow.cfm",  
		"offer.aspx", "scripts/ordernow.aspx", "getit.aspx", "main/myorder.aspx", "main/order.aspx",  
		"order.php", "offer.php");
		
		$orderQueryStringArr = array(
		"id=", "id=", "oid=", "emailid=", "emailid=",  
		"emailid=", "oid=", "uid=", "emailno=", "emailid=",  
		"o=", "custid=", "order=", "mailid=", "offer=",  
		"o=", "emailid=", "oid=", "eid=", "oidd=",  
		"is=", "orderid=", "ordemail=", "oid=", "oiemail=",  
		"oid=", "oi=");
		
		for ($i=0; $i<27; $i++) {
				$RU = str_replace(".","\\.",$trackRequestURIArr[$i]);
				print"#" . intval($i+1). ":  /".$trackRequestURIArr[$i]."?".$trackQueryStringArr[$i]."2709  ->  /dot/emailNotify_tracking.php?id=2709\n";
				print "RewriteCond %{REQUEST_URI} ^/dot/".$RU."$\n";
				print "RewriteCond %{QUERY_STRING} ^".$trackQueryStringArr[$i]."(\d+)$\n";
				print "RewriteRule ^.*$ /dot/emailNotify_tracking.php?id=%1 [L]\n\n";
			}
	
		print "\n\n\n";
			
		for ($i=0; $i<27; $i++) {
			$RU = str_replace(".","\\.",$unsubscribeRequestURIArr[$i]);
			print"#" . intval($i+1). ":  /".$unsubscribeRequestURIArr[$i]."?".$unsubscribeQueryStringArr[$i]."me@buy.biz  ->  /dot/emailNotify_unsubscribe.php?email=me@buy.biz\n";
			print "RewriteCond %{REQUEST_URI} ^/dot/".$RU."$\n";
			print "RewriteCond %{QUERY_STRING} ^".$unsubscribeQueryStringArr[$i]."(.*)$\n";
			print "RewriteRule ^.*$ /dot/emailNotify_unsubscribe.php?email=%1 [L]\n\n";
		}

		print "\n\n\n";
	
		for ($i=0; $i<27; $i++) {
			$RU = str_replace(".","\\.",$orderRequestURIArr[$i]);
			print"#" . intval($i+1). ":  /".$orderRequestURIArr[$i]."?".$orderQueryStringArr[$i]."Y2hyaXN0aWFuQGFzeW54LmNvbSYxNw==  ->  /dot/emailNotify_recordOrderClick.php?id=Y2hyaXN0aWFuQGFzeW54LmNvbSYxNw==\n";
			print "RewriteCond %{REQUEST_URI} ^/dot/".$RU."$\n";
			print "RewriteCond %{QUERY_STRING} ^".$orderQueryStringArr[$i]."(.*)$\n";
			print "RewriteRule ^.*$ /dot/emailNotify_recordOrderClick.php?id=%1 [L]\n\n";
		}
	*/	
			
		
}
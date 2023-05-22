<?php

class CheckBouncedEmails {
		
	private $domainID;
	private $loginUserName;
	private $loginPassword;
	private $mailServer;
	
	function __contruct(){	
				
	}
	
	public function startCheckBounce() {
			
		$allDomainIdsArr = Domain::getAllDomainIds();

		foreach($allDomainIdsArr as $thisDomainId){
	
			$pop3 = new POP3("", 60);
			
			$domainEmailObj = new DomainEmails($thisDomainId);
	
			$this->loginUserName = $domainEmailObj->getUserOfType(DomainEmails::EMAILNOTI); 
			$this->loginPassword = $domainEmailObj->getPassOfType(DomainEmails::EMAILNOTI); 
			$this->mailServer    = $domainEmailObj->getHostOfType(DomainEmails::EMAILNOTI); 
			$this->domainId      = $thisDomainId;
		
			$this->downloadFromServer($pop3);
		}
	}
	
	private function downloadFromServer(&$pop3){
	
		if(empty($this->mailServer))
			return;
	
		if (!$pop3->connect($this->mailServer)) {
			$this->mailFetchStatus(("Oops, ... ") . $pop3->ERROR );
			exit;
		}
	
		$count = $pop3->login($this->loginUserName, $this->loginPassword);
	
		if (($count == false || $count == -1) && $pop3->ERROR != '') {
			$this->mailFetchStatus(("Login Failed: ...") . ' ' . $pop3->ERROR );
			exit;
		}
	
		if ($count == 0) {
			$this->mailFetchStatus(("Login OK ... : Inbox EMPTY"));
			$pop3->quit();
			exit;
		}
		else {
			$this->mailFetchStatus(("Login OK ... : Inbox contains [") . $count . ("] messages"));
		}
	
		#-- Retrieve the messages from the server and process
		$this->getEmailMessages($count, $pop3);
		$this->mailFetchStatus(("Closing POP ..."));
		$pop3->quit();
		$this->mailFetchStatus(("Done .... "));
	}

	private function getEmailMessages($count, &$pop3){
	
		$retryCount  = 0;
		$max_retries = 3;
	
		for ($i=1; $i <= $count; $i++) {
			
			$this->mailFetchStatus(("Fetching message ") . "$i" );
			set_time_limit(20); // 20 seconds per message max
	
			$message = "";
			$messArray = $pop3->get($i);
	
			while ( (!$messArray) or (gettype($messArray) != "array")) {
	
				if($retryCount > $max_retries){
					$this->mailFetchStatus(("Timeout"));
					exit;
				}

				$retryCount++;

				$this->mailFetchStatus(("Oops, ") . $pop3->ERROR);
				$this->mailFetchStatus(("Server error...Disconnect"));

				$pop3->quit();

				$this->mailFetchStatus(("Reconnect from dead connection"));

				if (!$pop3->connect($this->mailServer)) {
					$this->mailFetchStatus(("Oops, ") . $pop3->ERROR );
					$this->mailFetchStatus(("Saving UIDL"));

					continue;
				}

				$count = $pop3->login($this->loginUserName, $this->loginPassword);

				if (($count == false || $count == -1) && $pop3->ERROR != '') {
					$this->mailFetchStatus(("Login Failed:") . ' ' . $pop3->ERROR );
					$this->mailFetchStatus(("Saving UIDL"));
					continue;
				}

				$this->mailFetchStatus(("Refetching message ") . "$i" );

				$messArray = $pop3->get($i);
			}
	
			foreach($messArray as $line )
				 $message .= $line;
	
			// Put the EMAIL message into a stucture so that we can get all of the parts
			// We are using the MIME decode function from Pear
			$params['include_bodies'] = TRUE;
			$params['decode_bodies']  = TRUE;
			$params['decode_headers'] = TRUE;
			
			$decoder = new Mail_mimeDecode($message);
			$messageStructure = $decoder->decode($params);
	
			// In case they forgot to put in a subject we don't want our program to crash
			if(!isset($messageStructure->headers["subject"]))
				$messageStructure->headers["subject"] = "No Subject";
	
			$this->removeBounced($messageStructure);
		}
	
		// We can cycle through and delete all of them.  It is safe to do this because messages are not actually deleted until the "quit" command is issued.	
		for ($i=1; $i <= $count; $i++) 
			$pop3->delete($i);		
	}
	
	private function removeBounced($messageStructure){
	
		if(!preg_match("/(DELIVERY FAILED)/", strtoupper($messageStructure->headers["subject"]))) 		
			exit;

		$body = $messageStructure->body;
		
	 	$emails = array();
		preg_match_all("/(\w+([-.]\w+)*\@\w+([-.]\w+)*\.\w+)/", $body, $emails);
		$bouncedEmail = $emails[1][0];	
	    
		// Determine fail codes in body
		$errorCodes = array(451, 452, 501, 503, 504, 550, 551, 552, 554, 557);
		foreach ($errorCodes AS $error) {	
			if(preg_match("/(: $error )/", $body)) 
				$failerror = $error;
		}
		    
		// only certain fail codes result in a permanent removal from the list. If the email doesn't exist nothing happens
		if(in_array($failerror, array(550, 551, 554))) 	
			EmailNotifyCollection::removeEmail($bouncedEmail);	
		
		/*
		// fail codes:			
	
		451 Requested action aborted: local error in processing
			Your ISP mail server indicates that the mailing has been interrupted, usually due to overloading from too many messages or a transient failure in which the message sent is valid, but some temporary event prevents the successful sending of the message. Sending in the future may be successful.
		
		452 Requested action not taken: insufficient system storage
		
		452 too many messages error
			Some mail servers have the option to reduce the number of concurrent connections and also the number of messages sent per connection. If you have a lot of messages queued up, it could go over the maximum number of messages per connection. To see if this is the case, you can try submitting only a few messages at a time to that domain and then continue increasing the number until you find the maximum number accepted by the server.
		  
		501 Syntax Error in parameters or arguments
			Indicates possible poor or an intermittent drop in network line connection that caused your mail client to send an erroneous command to the mail server.
		
		503 Server encountered bad sequence of commands
			Indicates that your ISP mail server did not recognized a command sent that is erroneous. Some temporary event prevents the successful sending of the message or an intermittent drop in network line connection that caused your mail client to send an erroneous command; sending in the future may be successful.
		
		504 Command parameter not implemented
			Indicates that your ISP mail server did not recognized a command sent.
		 
		550 Requested action not taken, mailbox unavailable
			Indicates that your recipient's email address was not recognized by your ISP mail server, or mailbox not found or cannot access it.
		
		551 User not local, please try <forward-path> or Invalid Address: Relay request denied
			Indicates that the recipient's email address has changed and your ISP mail server is forwarding it back to you and/or your ISP; SMTP mail server does not accept email when neither the sender nor the recipient is a local user. This feature was implemented to protect the mail server from being used by spammers to relay their messages.
		 
		552 Requested mail action aborted: exceeded storage allocation ISP mail server indicates probable overloading from too many messages.
		
		554 Transaction failed or Permanent Failure
			A permanent failure is one which is not likely to be resolved by resending the message in its current form and some change to the message and/or destination must be made for successful delivery.
		
		557 Too many duplicate messages: Resource temporarily unavailable Indicates that there is some kind of anti-spam system on the mail server.
		
		*/
	}
	
	private function mailFetchStatus($msg) {
	
		echo  $msg . "<br>\n";
		flush();
	}
}
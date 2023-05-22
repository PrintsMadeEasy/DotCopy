<?php

class MessageDisplay {

	function __construct() {

	}
	
	function __destruct() {
	
	}

	public function displayAsOverviewHTML($t, MessageThreadCollection $messageThreadCollectionObj) {
	
		$empty_message = true;
		
		$keywords = $messageThreadCollectionObj->getKeywords();	
		
		$messageThreadCollection = $messageThreadCollectionObj->getThreadCollection();
		
		$t->set_block ( "messagesBL", "subjectLinkBL", "subjectLinkBLout" );
		
		foreach ($messageThreadCollection as $thisMessageThread) {
	
			$empty_message = false; 
			
			$t->set_var(array(
				"ID"=>$thisMessageThread->getThreadID(), 
				"START"=>date("M j, Y g:i a",$thisMessageThread->getThreadStartDate()), 
				"COUNT"=>$thisMessageThread->getMessageCount(), 
				"SUBJECT"=>WebUtil::htmlOutput($thisMessageThread->getSubject()), 
				"REFID"=>$thisMessageThread->getRefID(),
				"LINKURL"=>$thisMessageThread->getLinkURL(),
				"LINKSUBJECT"=>$thisMessageThread->getLinkSubject()
				));
				
			if($thisMessageThread->getRefID() == 0) 
				$t->set_var("subjectLinkBLout", "");
			 else	
				$t->parse("subjectLinkBLout", "subjectLinkBL", false );	
				
			$messageObj = $thisMessageThread->getMessages();
				
			$messages = "";
			foreach ($messageObj as $thisMessage) 
				$messages .= $this->formatMessageResult($thisMessage->getMessageText(), $keywords, true);
			
			$t->set_var(array(
				"SUMMARY"=>'<br><br>' . $messages 
				));

				
			$t->allowVariableToContainBrackets("SUMMARY");
			
			$t->parse("messagesBLout","messagesBL",true);
		}	
		
		return $empty_message;
	}

	public function displayAsUnreadHTML($t, MessageThreadCollection $messageThreadCollectionObj) {
			
		$messageThreadCollection = $messageThreadCollectionObj->getThreadCollection();
		
		$rowBackgroundColor = true;
	
		$empty_message = true;
		
		$t->set_block ( "messagesBL", "subjectLinkBL", "subjectLinkBLout" );
		
		foreach ($messageThreadCollection as $thisMessageThread) {

			$empty_message = false;
			
			if($rowBackgroundColor){
				$rowcolor = "#FFfff9";
				$rowBackgroundColor = false;
			}
			else{
				$rowcolor = "#FFEEEE";
				$rowBackgroundColor = true;
			}
		
			$t->set_var(array(
				"ID"=>$thisMessageThread->getThreadID(), 
				"RECEIVED"=>date("M j, Y g:i a", $thisMessageThread->getLastMessageDate()),
				"FROM"=>$thisMessageThread->getUserIDWhoSentLastMessage(), 
				"START"=>date("M j, Y g:i a", $thisMessageThread->getThreadStartDate()), 
				"COUNT"=>$thisMessageThread->getMessageCount(), 
				"SUBJECT"=>WebUtil::htmlOutput($thisMessageThread->getSubject()), 
				"REFID"=>$thisMessageThread->getRefID(),
				"LINKURL"=>$thisMessageThread->getLinkURL(),
				"LINKSUBJECT"=>$thisMessageThread->getLinkSubject(),
				"ROW_COLOR"=>$rowcolor
				));
				
			if($thisMessageThread->getRefID() == 0) 
				$t->set_var("subjectLinkBLout", "");
			 else	
				$t->parse("subjectLinkBLout", "subjectLinkBL", false );	
		
			$t->parse("messagesBLout","messagesBL",true);
		}		
		return $empty_message;
	}
	
	static function generateMessageThreadHTML(MessageThread $messageThreadObj) {
			
		$dbCmd = new DbCmd();
			
		$tt = new Templatex( ".", "keep" );
		$tt->set_file ("messageThreadHTML", "./messagethread-template.html");
		$tt->set_block("messageThreadHTML", "messageThreadBL", "messageThreadBLout");

		$tt->set_block("messageThreadBL", "showRevisionLinkBL", "showRevisionLinkBLout");
		$tt->set_block("messageThreadBL", "makeRevisionLinkBL", "makeRevisionLinkBLout");

		$messageThreadsArr = $messageThreadObj->getMessages();
			
		foreach($messageThreadsArr as $thisMessage) {

			if($thisMessage->isRead())
				$row_color="#888888";
			else 
				$row_color="#996666"; //It is a new message

			$messageText = WebUtil::htmlOutput($thisMessage->getMessageText());
			$messageText = preg_replace("/\n/", "<br>", $messageText);
			
			// Some old messages have some HTML mixed in.  This is just correcing the issue on the fly, instead of writing a back-dating script to fix.
			$messageText = preg_replace("/&lt;br&gt;/", "", $messageText);
					
			$tt->set_var("ROW_COLOR", $row_color);
			$tt->set_var("USER_NAME", WebUtil::htmlOutput(UserControl::getNameByUserID($dbCmd, $thisMessage->getUserID())));				
			$tt->set_var("DATE_CREATED", date("M j, Y g:i a", $thisMessage->getDateCreated()));				
			$tt->set_var("MESSAGE_TEXT", $messageText);				
			$tt->set_var("MESSAGE_ID", $thisMessage->getID());				

			$countOfRevisions = MessageRevision::getCountOfMessageRevisions($thisMessage->getID());			
			if($countOfRevisions > 0) {
				$tt->set_var("REVISION_COUNT", $countOfRevisions);		
				$tt->parse("showRevisionLinkBLout", "showRevisionLinkBL", false );	
			} else	
				$tt->set_var("showRevisionLinkBLout", "");
			
			// Only the user who wrote the message can revise it
			if($messageThreadObj->getUserID() == $thisMessage->getUserID()) 	
				$tt->parse("makeRevisionLinkBLout", "makeRevisionLinkBL", false );
			 else	
				$tt->set_var("makeRevisionLinkBLout", "");
			
			// Anyone can view attachments... but if there are none... then only the Message Owner should see a link to add a new attachment.
			$attachmentLinkURL = "javascript:OpenMessageAttachmentPopup(".$thisMessage->getID().",false)";
			if($thisMessage->getAttachmentCount() > 0){
				$tt->set_var("ATTACHMENTS_DISP", "<a class='messageRevisionLink' href='$attachmentLinkURL'><img src='./images/paperClip-u.png' align='absmiddle' border='0'> " . $thisMessage->getAttachmentCount() . "</a>");	
				$tt->allowVariableToContainBrackets("ATTACHMENTS_DISP");
			}
			else{
				if($messageThreadObj->getUserID() == $thisMessage->getUserID()) 
					$tt->set_var("ATTACHMENTS_DISP", "<a class='messageRevisionLink' href='$attachmentLinkURL'>Attachment</a>");	
				else 
					$tt->set_var("ATTACHMENTS_DISP", "");
			}
			
				
			
			$tt->parse ( "messageThreadBLout", "messageThreadBL", true );
		}	
			
		$messageThreadHTML = "No Messages";
		if (sizeof($messageThreadsArr) != 0)
			$messageThreadHTML = $tt->finish ( $tt->parse ( "OUT", "messageThreadHTML" ));
			
		return $messageThreadHTML;	
	}
	
	public function getSearchResultDescription(MessageThreadCollection $messageThreadCollectionObj) {
		
		
		$threadsCount = $messageThreadCollectionObj->getThreadCountsDisplayed();
		$matchesCount = $messageThreadCollectionObj->getTotalMessagesInThreadsCount();
		$keywords     = $messageThreadCollectionObj->getKeywords();
		
		$searchResultDesc = "";
		if(($matchesCount > 1) && !empty($keywords))
			$searchResultDesc .= "$matchesCount Matches found in ";
		else if(($matchesCount == 1) && !empty($keywords)) {
			$searchResultDesc .= "$matchesCount Match found in ";
		}
		
		if($threadsCount > 1)
			$searchResultDesc .= "$threadsCount Threads";
		else
			$searchResultDesc .= "$threadsCount Thread";
	
			return $searchResultDesc;
	}
	
	

	
	// Takes the whole message with the keyword we are searching for and returns a nice description for us.
	// Kind of like a Google Snippets to display in search results.
	// Parameter #1 is the entire message
	// Parameter #2 is the keyword we are formating the result around
	// Parameter #3 is a flag that will make the message bold.
	private function formatMessageResult($message, $keywords, $bold){
	
		$Offset_length = 45;
	
		$message = preg_replace("/<[^>]*>/", "", $message);  //Gets rid of all HTML tags that may be lurking in a message.
		
		$message = WebUtil::htmlOutput($message);

	
		$keywordList = split(" ", $keywords);
	
		$returnMessage = "";
	
		if(!empty($keywords)){
			for($i=0; $i<count($keywordList); $i++){
	
				$pos = strpos(strtolower($message), strtolower($keywordList[$i]));
				if (is_integer($pos)) {
	
					$find_another_word = true;
	
					while($find_another_word){
	
						$start_from = $pos - $Offset_length;  //This means that we want to grab the first (so many) characters before the matching keyword.
						if($start_from < 0)
							$start_from = 0;
	
						$end_on = strlen($keywordList[$i]) + $Offset_length;
						if($end_on > strlen($message) - $pos)
							$end_on = strlen($message) - $pos;
	
						$Extract_From_Begining = $Offset_length;
						if($Extract_From_Begining > $pos)
							$Extract_From_Begining = $pos;
	
						$returnMessage .= "...&nbsp;&nbsp;&nbsp;";
						$returnMessage .= substr($message, $start_from, $Extract_From_Begining);
						$returnMessage .= "<font color='#990000'>" . substr($message, $pos, strlen($keywordList[$i])) . "</font>";
						$returnMessage .= substr($message, ($pos + strlen($keywordList[$i])), $end_on);
						$returnMessage .= "&nbsp;&nbsp;&nbsp;...<br>";
	
						// Now get the rest of the message after the keyword and find out if there are any more occurrences.
						$remaining_message = substr($message, ($pos + strlen($keywordList[$i])));
	
						$new_pos = strpos(strtolower($remaining_message), strtolower($keywordList[$i]));
						if (is_integer($new_pos) && $new_pos <> 0) {
							$find_another_word = true;
							$pos = $pos + $new_pos + strlen($keywordList[$i]);
						}
						else{
							$find_another_word = false;
						}
	
					}
				}
			}
		}
		else{
	
			if($bold){
				$message = "<font color='#000099'><i>" . $message . "</i></font>";
			}
	
			// If they are not doing a search by keyword, then we just will show them the first message in the thread
			$returnMessage = $message . "<br><br>";
		}	
		return $returnMessage;
	}
	

	
}

?>

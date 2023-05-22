<?php

require_once("library/Boot_Session.php");

$chatID = WebUtil::GetInput("chatID", FILTER_SANITIZE_INT);
$password= WebUtil::GetInput("password", FILTER_SANITIZE_INT);

set_time_limit(1000);

// The password is the timestamp
// Make sure that the other party knows what to do.
if(abs(intval($password) - time()) > 100){
	exit("...");
}

$dbCmd = new DbCmd();

$dbCmd->Query("SELECT COUNT(*) FROM chatthread WHERE ID=$chatID");
if($dbCmd->GetValue() == 0){
	exit("Done");
}

print "<html><body>\n";

$dbCmd->Query("SELECT FileAttachmentID, PleaseWaitMessageID, Message FROM chatmessages 
				WHERE ChatThreadID=$chatID ORDER BY ID ASC");

while($row = $dbCmd->GetRow()){
	
	if(!empty($row["FileAttachmentID"]) || !empty($row["PleaseWaitMessageID"]) )
		continue;
		
	$message = $row["Message"];
	
	$message = preg_replace('/\<br\>/', " ", $message);
	
	$lastChar = substr($message, -1);
	
	if(!in_array($lastChar, array(".", "!", "?"))){
		$message .= ". ";
	}
	
	print "<div>" . htmlentities($message) . "</div>\n";
	
	
	
}

// This will give a link to the next one.
print "<a href='http://www.printsmadeeasy.com/chat_dump_online.php?chatID=" . ($chatID+1) . "'><u>&nbsp;&nbsp;</u></a>";

print "</body></html>";



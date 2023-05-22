<?

require_once("library/Boot_Session.php");

$dbCmd = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

$messageID = WebUtil::GetInput("messageid", FILTER_SANITIZE_INT);
$threadID = WebUtil::GetInput("threadid", FILTER_SANITIZE_INT);
$revisedMessage = WebUtil::GetInput("revisedmessage", FILTER_UNSAFE_RAW);
$windowtype = WebUtil::GetInput("windowtype", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
$doNotRefresh = WebUtil::GetInput("doNotRefresh", FILTER_SANITIZE_STRING_ONE_LINE);


WebUtil::checkFormSecurityCode();


$messageRevisionObj = new MessageRevision();
$messageRevisionObj->setRevisionText($revisedMessage);
$messageRevisionObj->setUserID($UserID);
$messageRevisionObj->reviseMessageByID($messageID);

// Keep a record of the visit to this page by the user.
// NavigationHistory::recordPageVisit($dbCmd, $UserID, "MsgRevised", $messageID);

if($windowtype == "popup"){

	if(empty($doNotRefresh)){
	
		print "
			<html>
			<script>
			window.opener.location = window.opener.location;
			self.close();
			</script>
			</html>

		";
	}
	else{
		print "
			<html>
			<script>
			self.close();
			</script>
			</html>
		";
	}
}	
else{
	$returl = "ad_message_display.php?thread=" . $threadID;
	header("Location: " . WebUtil::FilterURL($returl) . "&nocache=". time());
}


?>
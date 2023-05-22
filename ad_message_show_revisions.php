<?

require_once("library/Boot_Session.php");


$messageID = WebUtil::GetInput("messageid", FILTER_SANITIZE_INT);

$dbCmd = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();

$t = new Templatex(".");

$t->set_file("origPage", "ad_message_show_revisions-template.html");
$t->set_block("origPage", "messageRevisionBL", "messageRevisionBLout");

$messageRevisionObj = new MessageRevision();
$revisionArr = $messageRevisionObj->getRevisionHistoryArr($messageID);

$revisionVersion = sizeof($revisionArr);

foreach($revisionArr as $thisRevision) {		
	
	if($revisionVersion == 1)
		$revisionDesc = "Original Message";
	else
		$revisionDesc = "Revision #$revisionVersion";
	
	$t->set_var("REVISION_TEXT", WebUtil::htmlOutput($thisRevision["Message"]));				
	$t->set_var("REVISION_DATE", date("M j, Y g:i a", $thisRevision["DateCreated"]));	
	$t->set_var("REVISION_NO", WebUtil::htmlOutput($revisionDesc));

	$revisionVersion--;
			
	$t->parse("messageRevisionBLout", "messageRevisionBL", true );
}

$t->pparse("OUT","origPage");

?>
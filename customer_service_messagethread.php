<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

$t = new Templatex();

$t->set_file("origPage", "customer_service_messagethread_template.html");

$orderno = WebUtil::GetInput("orderno", FILTER_SANITIZE_INT);

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_general);

if(!Order::CheckIfUserHasPermissionToSeeOrder($orderno))
	throw new Exception("Order Number does not exist.");

$Messages_HTML = "";


$dbCmd->Query("SELECT CustomerEmail,ID FROM csitems WHERE OrderRef=$orderno ORDER BY DateCreated DESC");

while($row = $dbCmd->GetRow()){

	$customer_email = $row["CustomerEmail"];
	$csThreadID = $row["ID"];

	// Get a hash containing the information of the message thread
	$MessageHash = CustService::GetMessagesFromCSitem($dbCmd2, $csThreadID);

	// Pass the hash in to get an HTML table for placing on the page
	$MessageTable = CustService::FormatMessagesForCSItem($MessageHash, "#EEEEFF", "#FFEEEE", false);

	$Messages_HTML .= $MessageTable;

	// Get a hash with all of the attachments
	$attachmentHash = CustService::GetAttachmentsFromCSitem($dbCmd2, $csThreadID);
	$attachmentDisplay = CustService::FormatAttachmentsForCSItem($attachmentHash);


	// If there are attachments to show then format a table around them
	if(!empty($attachmentDisplay)){

		$Messages_HTML .= "<br><b>&nbsp;Attachments</b>";
		$Messages_HTML .= "<table cellpadding='1' cellspacing='0' border='1' bgcolor='#FFFFFF'><tr><td class='SmallBody'>";
		$Messages_HTML .= $attachmentDisplay;
		$Messages_HTML .= "</td></tr></table>&nbsp;<br>";

	}
}

VisitorPath::addRecord("Customer Service Message Thread");

$t->set_var("EMAIL_ADDRESS", $customer_email);
$t->set_var("MESSAGES", $Messages_HTML);
$t->allowVariableToContainBrackets("MESSAGES");

$t->pparse("OUT","origPage");


?>
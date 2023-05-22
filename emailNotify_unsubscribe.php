<?

require_once("library/Boot_Session.php");

$email = WebUtil::GetInput("email", FILTER_SANITIZE_EMAIL);

$domainIdfromURL = Domain::getDomainIDfromURL();


$dbCmd = new DbCmd();

if(!WebUtil::ValidateEmail($email))
	WebUtil::PrintError("We are sorry, but there has been a problem with the link you clicked on.");

$t = new Templatex();
$t->set_file("origPage", "emailNotify_unsubscribe-template.html");



// Prevent someone from trying lots of different email addresses in a dictionary attack in order to harvest our entire list.
$dbCmd->Query("SELECT COUNT(*) FROM ipaddresswrongaccess WHERE IPaddress LIKE '" . DbCmd::EscapeLikeQuery(WebUtil::getRemoteAddressIp()) . "' 
					AND AccessType='EmlUn' AND Date > DATE_ADD(NOW(), INTERVAL -3 DAY )");
$wrongAccessCount = $dbCmd->GetValue();
if($wrongAccessCount > 20){
	throw new Exception("The Email Unsubscription has a brute force attack going on.");
}

// Find out if the email address is in our collection.  If not... increment the "brute force" counter and show them an error page letting them know that the Address isn't in our list.
$dbCmd->Query("SELECT COUNT(*) FROM emailnotifycollection WHERE Email LIKE '" . DbCmd::EscapeLikeQuery($email) . "'");
if($dbCmd->GetValue() == 0) {
	$t->discard_block("origPage",   "SuccessMessageBL"); 
	
	$dbCmd->InsertQuery("ipaddresswrongaccess", array("IPaddress"=>WebUtil::getRemoteAddressIp(), "AccessType"=>"EmlUn", "Date"=>time()));
} 
else {

	$t->discard_block("origPage",  "ErrorMessageBL"); 

	$insertArr["Email"]    = $email;
	$insertArr["DomainID"] = $domainIdfromURL;		
	$insertArr["Date"]     = time(); 
	$insertArr["IP"]       = WebUtil::getRemoteAddressIp();
			
	$dbCmd->Query("SELECT COUNT(*) FROM emailunsubscriptions WHERE Email LIKE '" . DbCmd::EscapeLikeQuery($email) . "' AND DomainID=$domainIdfromURL");		

	// Add it only once
	if($dbCmd->GetValue() == 0)	
		$dbCmd->InsertQuery("emailunsubscriptions", $insertArr);
}

VisitorPath::addRecord("EmailNotification Unsubscribe");

$t->pparse("OUT","origPage");

?>
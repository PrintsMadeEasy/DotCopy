<?

require_once("library/Boot_Session.php");


$email = WebUtil::GetInput("email", FILTER_SANITIZE_STRING_ONE_LINE);
$unsubscribe = WebUtil::GetInput("unsubscribe", FILTER_SANITIZE_STRING_ONE_LINE);

$domainIDfromURL = Domain::oneDomain();


$dbCmd = new DbCmd();


if(empty($email) || !WebUtil::ValidateEmail($email))
	WebUtil::PrintError("We are sorry, but there has been a problem with the link you clicked on. The format of your email address is invalid. Please visit Customer Service if you need assistance.");


$t = new Templatex();


$t->set_file("origPage", "newsletter_unsubscribe-template.html");

$email = DbCmd::EscapeSQL($email);

$t->set_var("EMAIL", WebUtil::htmlOutput($email));

// If they clicked on the unsubscribe button 
if(!empty($unsubscribe)){

	$dbCmd->Query("UPDATE users SET Newsletter=\"N\" WHERE Email LIKE \"". DbCmd::EscapeLikeQuery($email) . "\" AND DomainID=$domainIDfromURL");
	
	$dbCmd->InsertQuery("emailunsubscribe", array("EmailAddress"=>strtolower($email), "DomainID"=>$domainIDfromURL, "Date"=>time(), "AffilliatUnsubscibeStatus"=>"W"));
	
	$t->discard_block("origPage", "messageBL");
}
else{

	$t->discard_block("origPage", "confirmBL");
}

VisitorPath::addRecord("Newsletter Unsubscribe");

$t->pparse("OUT","origPage");


?>
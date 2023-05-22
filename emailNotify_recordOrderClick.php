<?

require_once("library/Boot_Session.php");

$emailJob = base64_decode(WebUtil::GetInput("id", FILTER_SANITIZE_STRING_ONE_LINE));

WebUtil::OutputCompactPrivacyPolicyHeader();

if(empty($emailJob)) 
	throw new Exception("Empty ID");

if(!(preg_match("/\\|/", $emailJob))) 
	throw new Exception("Manipulited ID");
	
$emailJobArr        = explode("|", $emailJob, 3);
$email              = strtolower($emailJobArr[0]);
$emailHistoryId  = intval($emailJobArr[1]);

if(sizeof($emailJobArr)==3)
	$redirURL = $emailJobArr[2];
else 	
	$redirURL = "/";

if(Domain::getDomainIDfromURL() != EmailNotifyJob::getDomainIdOfHistoryId($emailHistoryId))
	throw new Exception("DomainID doesn't match");

if($email != EmailNotifyJob::getEmailOfHistoryId($emailHistoryId))
	throw new Exception("Email doesn't match");
	

WebUtil::SetSessionVar("EmailNotifyJobHistoryID", $emailHistoryId);

VisitorPath::addRecord("Email Click", $emailHistoryId);


// We use a cookie name that is different by domain
WebUtil::SetCookie(EmailNotifyMessages::getClickCookieName(Domain::getDomainKeyFromID(Domain::getDomainIDfromURL())), $emailHistoryId);
	
EmailNotifyJob::recordClick($email, $emailHistoryId);
	
// Go to homepage, or orderpage
header("Location: ". WebUtil::FilterURL($redirURL));

?>
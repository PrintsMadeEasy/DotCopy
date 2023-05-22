<?

require_once("library/Boot_Session.php");

// Don't make it look like we are favoring a particular Domain 
Domain::removeTopDomainID();

$dbCmd = new DbCmd();

$ReturnedPackagesObj = new ReturnedPackages($dbCmd);



//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

if(!empty($action)){

	WebUtil::checkFormSecurityCode();
	
	if($action == "closenotification")
		$ReturnedPackagesObj->CloseNotificationOfReturnedPackage(WebUtil::GetInput("notificationid", FILTER_SANITIZE_INT), $UserID);
	else if($action == "newmessage")
		$ReturnedPackagesObj->AddMessageToNotification(WebUtil::GetInput("notificationid", FILTER_SANITIZE_INT), $UserID, WebUtil::GetInput("message", FILTER_SANITIZE_STRING_ONE_LINE));
	else if($action == "MarkPackageAsReturned"){
		if(!$ReturnedPackagesObj->MarkOrderAsReturned(WebUtil::GetInput("projectid", FILTER_SANITIZE_INT), $UserID))
			WebUtil::PrintAdminError($ReturnedPackagesObj->GetErrorMessage());
	}
	else
		throw new Exception("Illegal Action");
		

	// If we provide a ReturnURL within the URL then direct the user back to that location... otherise just go back to the returned packages screen
	if(WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL))
		header("Location: " . WebUtil::FilterURL(WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL)));
	else
		header("Location: ./ad_returnedpackages.php");
	exit;
}
	



$t = new Templatex(".");

$t->set_file("origPage", "ad_returnedpackages-template.html");

$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());



$NotificationIDarr = $ReturnedPackagesObj->GetOpenReturnedPackages();

$t->set_block("origPage","itemsBL","itemsBLout");
foreach($NotificationIDarr as $ThisNotificationID){

	$t->set_var("ORDERNO", $ReturnedPackagesObj->GetOrderNumberFromNotificationID($ThisNotificationID));
	$t->set_var("COMPANY_OR_NAME", WebUtil::htmlOutput($ReturnedPackagesObj->GetCustomerNameFromNotificationID($ThisNotificationID)));
	$t->set_var("MESSAGES", $ReturnedPackagesObj->GetMessagesFromNotificationID($ThisNotificationID));
	$t->set_var("RETURNED_PACKAGE_COMM", $ReturnedPackagesObj->GetCommandLinksFromNotificationID($ThisNotificationID));
	$t->allowVariableToContainBrackets("MESSAGES");
	$t->allowVariableToContainBrackets("RETURNED_PACKAGE_COMM");
	
	$t->parse("itemsBLout","itemsBL",true);
}
if(sizeof($NotificationIDarr) == 0){
	$t->set_block("origPage","EmptyItemsBL","EmptyItemsBLout");
	$t->set_var("EmptyItemsBLout", "No returned packages at this time.");
}


$t->pparse("OUT","origPage");



?>
<?

require_once("library/Boot_Session.php");


$redirect = WebUtil::GetInput("redirect", FILTER_SANITIZE_URL);
$sessiononly = WebUtil::GetInput("sessiononly", FILTER_SANITIZE_STRING_ONE_LINE);
$clearcart = WebUtil::GetInput("clearcart", FILTER_SANITIZE_STRING_ONE_LINE);



$websiteUrlForDomain = Domain::getWebsiteURLforDomainID(Domain::oneDomain());

$user_sessionID =  WebUtil::GetSessionID();

VisitorPath::addRecord("Logout");

WebUtil::SetSessionVar("UserIDloggedIN", "");
WebUtil::SetCookie("SelectedDomains", null);


// Don't destroy their permanent cookies if they are doing a SessionOnlyLogout
if(empty($sessiononly)){

	// If they have any permanent cookies set on their machine to remember the login.. then get rid of them --#
	Authenticate::ClearPermanentCookie();
}
else{
	//Set this... so that we don't try to automatically log back in within secure areas
	$HTTP_SESSION_VARS["SessionOnly"] = true;
}



if(!empty($clearcart)){
	$dbCmd->Query("DELETE from shoppingcart where SID=\"". DbCmd::EscapeSQL($user_sessionID) . "\" AND shoppingcart.DomainID=".Domain::oneDomain());
	$dbCmd = new DbCmd();
}

session_destroy();

// Don't do a header redirect or the permanent cookies might not be sent to the browser.
if(!empty($redirect)){
	print "<html><script>document.location='".WebUtil::FilterURL($redirect)."';</script></html>";
}
else{
	print "<html><script>document.location='".WebUtil::FilterURL("http://$websiteUrlForDomain/logout_confirm.php?")."';</script></html>";
}





?>

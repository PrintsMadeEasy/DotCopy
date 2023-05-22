<?

require_once("library/Boot_Session.php");

$transferaddress = WebUtil::GetInput("transferaddress", FILTER_SANITIZE_URL);


$dbCmd = new DbCmd();

WebUtil::RunInSecureModeHTTPS();

// This is minor attempt to make sure that a browser is making this requests to our lost password finder...  and not an automated bot.
// Set the session variable on this page (because it is the only place that shows a link to the password finder page)
// We expect this session variable to be initialized on the lost password finder page.
WebUtil::SetSessionVar("PasswordRetrievalAttempts", 1);

$t = new Templatex();

$t->set_file("origPage", "signin-template.html");



if(empty($transferaddress))
	$transferaddress = "./";
	

$t->set_var(array("PAGE_LOCATION"=>$transferaddress, 
		"PAGE_LOCATION_ENCODED"=>urlencode($transferaddress),
		"FORM_SECURITY_CODE"=>WebUtil::getFormSecurityCode()));


// On the live site the Navigation bar needs to have hard-coded links to jump out of SLL mode... like http://www.example.com
// Also flash plugin can not have any non-secure source or browser will complain.. nead to change plugin to https:/
if(Constants::GetDevelopmentServer()){
	$t->set_var("SECURE_FLASH","");
	$t->set_var("HTTPS_FLASH","");
}
else{
	$t->set_var("SECURE_FLASH","TRUE");
	$t->set_var("HTTPS_FLASH","s");
}


VisitorPath::addRecord("Login Screen");

$t->pparse("OUT","origPage");


?>
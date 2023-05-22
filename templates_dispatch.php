<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();




$user_sessionID =  WebUtil::GetSessionID();


// This script was directed to by ".htaccess"
// When somone accesses a URL like "/templates/mortgages/"
// Apache will direct the URL to here send whatever comes after the 1st level directory in as a parameter to "area".
// For this example.. if you tried to access that URL this script would get executed and the parameter area would contain "mortgage//"
// There can be a lot of extra crap... back slashes, spaces, trailing in the end... so we will scrape that stuff off.


$fromArea = WebUtil::GetInput("area", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

$matches = array();
if(preg_match("/(\w+)/", $fromArea, $matches))
	$fromArea = strtolower($matches[1]);
else
	$fromArea = "";


$domainWebsiteURL = Domain::getWebsiteURLforDomainID(Domain::getDomainIDfromURL());
	
if($fromArea == "mortgage" || $fromArea == "mortgages"){
	// For Bret
	header("Location: http://$domainWebsiteURL/saleslog.php?coupon=&userid=34836&from=Mortgage+URL&dest=http%3A%2F%2F".$domainWebsiteURL."%2Ftemplates.php%3Fkeywords%3Dmortgage%26categorytemplate%3D108%26projectrecord%3D1092075%26offset%3D%26productIDview%3D78");
	exit;
}
else{
	WebUtil::PrintError("Unfortunately we do not have a template area matching the URL that you entered. Try retyping the URL again. If you continue to have problem please send an email to the webmaster.");
}



?>
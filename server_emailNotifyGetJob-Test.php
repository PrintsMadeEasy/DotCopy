<?php

require_once("library/Boot_Session.php");

/*
// Do some authentication to make sure that people can't hack. Don't even suggest to them that this page exits !		
if(Constants::GetDevelopmentServer()){
	
	$domainIP = Domain::getIpAddressForDomainID();

	if($domainIP != WebUtil::getRemoteAddressIp()) {
		header("HTTP/1.0 404 Not Found");
		exit;
	}
}
*/

$domainID = Domain::getDomainIDfromURL();

$xmlJobObj = new EmailNotifyJob($domainID);

$XML = $xmlJobObj->getJobXML(1); 
header('Content-Length: ' . strlen($XML));  
print $XML;
	


// Bang4Buck (satelite) calls this script from https://www.bang4buckprinting.com/server_getEmailNotifyJob.php?token=xxxx
// PME (satelite) calls this script from https://www.printsmadeeasy.com/server_getEmailNotifyJob.php?token=xxxx
// PC (satelite) calls this script from https://www.postcards.com/server_getEmailNotifyJob.php?token=xxxx
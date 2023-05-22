<?php
                   
require_once("library/Boot_Session.php"); 

set_time_limit(300);


function checkPop3($domainName) {

	$error_number = ""; $error = 0;
	$socket = @fsockopen($domainName, 110, $error_number, $error, 15);
	
	if(empty($socket))
		return false;

    $welcome = fread($socket,256);
    fputs($socket, "quit\r\n");
    fclose($socket);
    
    if (preg_match("/OK+ /", $welcome )) // Minimum answer from Pop3 is OK+ 
		return true;
	else 
		return false;
}


function checkSmtp($domainName) {

	$error_number = ""; $error = 0;
	$socket = @fsockopen($domainName, 25, $error_number, $error, 15);
	
	if(empty($socket))
		return false;

    $welcome = fread($socket,256);
    fputs($socket, "quit\r\n");
    fclose($socket);

    if (preg_match("/220 /", $welcome )) // Minimum answer from SMTP is 220
		return true;
	else 
		return false;
}


function checkHttp($domainName) {
	
	$error_number = ""; $error = 0;
	$socket = @fsockopen($domainName, 80, $error_number, $error, 15);
	
	if(!$socket)
		return false;
		
	$answer = "";
    $header = "GET / HTTP/1.1\r\n";
    $header .= "Host: $domainName\r\n";
    $header .= "Connection: Close\r\n\r\n";
    fwrite($socket, $header);
    
    while (!feof($socket)) {
    	$answer .= fgets($socket, 1024);
        if (preg_match("/http/", $answer )) { 
        	fclose($socket);
        	return true;
        }
    }
    fclose($socket);

	if (preg_match("/http/", $answer ))
		return true;
	else 
		return false;
}


$allDomainIdsArr = Domain::getAllDomainIDs();

$errorText = "";

foreach($allDomainIdsArr as $domainId) {

	$website = Domain::getWebsiteURLforDomainID($domainId);
	
	// Skip Domains that are under development (IP)
	if (preg_match("/(\d{1,3})\.(\d{1,3})\.(\d{1,3})\.(\d{1,3})/", $website ))
		continue;
		
	if(preg_match("/MarketingEngine/i", $website))
		continue;
	if(preg_match("/DotCopyShop/i", $website))
		continue;
	if(preg_match("/AmazingGrass/i", $website))
		continue;
		
	$webStatus  = checkHttp($website);
	$domainName = str_replace("www.", "", $website);
	$mailStatus = checkPop3("mail.".$domainName);
	$smtpStatus = checkSmtp("mail.".$domainName);

	
	if($webStatus && $mailStatus && $smtpStatus) 
		$statusText = "OK: $website:80 and mail.$domainName:110 and mail.$domainName:25";
	
	if(!$webStatus && $mailStatus && $smtpStatus) 
		$statusText = "$website:80 DOWN and mail.$domainName:110 OK and mail.$domainName:25 OK";
	
	if($webStatus && !$mailStatus && $smtpStatus) 
		$statusText = "$website:80 OK but mail.$domainName:110 DOWN and mail.$domainName:25 OK";
		
	if(!$webStatus && !$mailStatus && $smtpStatus) 
		$statusText = "$website:80 DOWN and mail.$domainName:110 DOWN and mail.$domainName:25 OK";
		
		
	if($webStatus && $mailStatus && !$smtpStatus) 
		$statusText = "$website:80 OK and mail.$domainName:110 OK and mail.$domainName:25 DOWN";
	
	if(!$webStatus && $mailStatus && !$smtpStatus) 
		$statusText = "$website:80 DOWN and mail.$domainName:110 OK and mail.$domainName:25 DOWN";
	
	if($webStatus && !$mailStatus && !$smtpStatus) 
		$statusText = "$website:80 OK but mail.$domainName:110 DOWN and mail.$domainName:25 DOWN";
		
	if(!$webStatus && !$mailStatus && !$smtpStatus) 
		$statusText = "$website:80 DOWN and mail.$domainName:110 DOWN and mail.$domainName:25 DOWN";

		
	if(!$webStatus || !$mailStatus || !$smtpStatus) 
		print "<font color='FF0000'>Down</font><br><br>\n";	
	else 	
		print " ";
	
	if(!$webStatus || !$mailStatus || !$smtpStatus)
		$errorText .= $statusText . "\n";
				
	flush();
}

if(!empty($errorText)){
	WebUtil::WebmasterError("Error server_CheckProxies: " . $errorText, "Proxy Alert");
	WebUtil::SendEmail("Proxy Check", "Server@PrintsMadeEasy.com", "Christian Nuesch", "Christian@PrintsMadeEasy.com", "Proxy Alert", $errorText);
}
	
?>
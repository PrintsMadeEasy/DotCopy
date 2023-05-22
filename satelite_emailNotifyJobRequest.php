<?php

//This will run on the satelite server once a day calling http://www.bang4BuckPrinting.com/server_emailNotifyGetJob.php

$url = "http://localhost/dot/xmltest.xml"; // "http://www.bang4BuckPrinting.com/server_emailNotifyGetJob.php"

$url = str_replace("http://","",$url);

$page = $url;
$url  = @explode("/",$url);
$url  = $url[0];
$page = str_replace($url,"",$page);  		
$ip   = gethostbyname($url);
     
$errno  = 0; $errstr = "";
 
$socket = fsockopen($ip, 80, $errno, $errstr, 60);

$send  = "GET $page HTTP/1.1\r\n";
$send .= "User-Agent: Email Satelite\r\n";
$send .= "Host: $url\r\n";
$send .= "Connection: Close\r\n\r\n";

$http = null;
fputs($socket, $send);

while (!feof($socket)) 
	$http .= fgets($socket, 4096);

fclose($socket);
     
$http = @explode("\r\n\r\n",$http,2);
       
if ($http[1]){
	$xml = $http[1];
}else{
	$xml = "";
}     

// var_dump($xml);

// Overwrite old XML file and Reset Counter
$fx = fopen("emailjob.xml", "w");
fwrite($fx, $xml);
fclose($fx);

$fp = fopen("emailposition.txt", "w");
fwrite($fp, "0");
fclose($fp);

?>
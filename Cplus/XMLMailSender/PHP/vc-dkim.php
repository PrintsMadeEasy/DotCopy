<?
	require 'VCDKIM.php' ;
	
	$selector = base64_decode($_POST['s']); 
	$domain = base64_decode($_POST['d']); 
	$subject  = base64_decode($_POST['subject']); 


	$headersEncoded  = str_replace(" ","+",$_POST['header']); 
	$headers = "";
	for ($i=0; $i < ceil(strlen($headersEncoded)/256); $i++)
	   $headers = $headers . base64_decode(substr($headersEncoded,$i*256,256)); 
	
	$bodyEncoded = str_replace(" ","+",$_POST['body']);
	$body = "";
	for ($i=0; $i < ceil(strlen($bodyEncoded)/256); $i++)
	   $body = $body . base64_decode(substr($bodyEncoded,$i*256,256)); 
	
	$dkimobj = new VCDKIM($domain,$selector);
	$DKIM    = $dkimobj->addDkimToHeaders($headers,$subject,$body);
	echo base64_encode($DKIM);	
?>
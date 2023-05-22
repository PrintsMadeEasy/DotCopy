<?
	require 'VCDKIM-Test.php' ;
	
	// Input body outbut sha1 of body
	
	$bodyEncoded = str_replace(" ","+",$_POST['body']);
	$body = "";
	for ($i=0; $i < ceil(strlen($bodyEncoded)/256); $i++)
	   $body = $body . base64_decode(substr($bodyEncoded,$i*256,256)); 
	
	
	$dkimobj = new VCDKIM("","");
	echo $dkimobj->sha1DKIM($body);	
?>
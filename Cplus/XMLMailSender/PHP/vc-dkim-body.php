<?
	require 'VCDKIM-Test.php' ;
	
	$signedEncoded = str_replace(" ","+",$_POST['body']);
	$body = "";
	for ($i=0; $i < ceil(strlen($signedEncoded)/256); $i++)
	   $body = $body . base64_decode(substr($signedEncoded,$i*256,256)); 
	
	
//	$body ="Test message";


	$dkimobj = new VCDKIM("","B24");
	echo base64_encode($dkimobj->simpleBody($body));	
?>
<?
	require 'VCDKIM-Test.php' ;
	
	$selector = base64_decode($_POST['s']); 
	
	$signedEncoded = str_replace(" ","+",$_POST['tobesigned']);
	$to_be_signed = "";
	for ($i=0; $i < ceil(strlen($signedEncoded)/256); $i++)
	   $to_be_signed = $to_be_signed . base64_decode(substr($signedEncoded,$i*256,256)); 
	

//	$selector = "B24";	
//	$to_be_signed ="Test message";


	$dkimobj = new VCDKIM("",$selector);
	echo base64_encode($dkimobj->signOnlyDKIM($to_be_signed));	
?>
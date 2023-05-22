<?
	require 'VCDKIM-Test.php' ;

	
	$body ="Order# H603-534640  -- msg[542228]";


	$dkimobj = new VCDKIM("","B24");
	echo $dkimobj->QP($body);	
?>
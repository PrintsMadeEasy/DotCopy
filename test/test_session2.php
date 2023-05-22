<?
require_once("library/Boot_Session.php");



if(!isset($HTTP_SESSION_VARS['testinitialized'])){

	print "Error Occured";
}
else{
	print "It worked";
}


?>


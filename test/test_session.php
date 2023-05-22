<?

require_once("library/Boot_Session.php");

if(!isset($HTTP_SESSION_VARS['brian'])){
	$HTTP_SESSION_VARS['brian'] = "This info is coming from the session variable.";
	print "nothing yet.. try reloading the page now.";
}
else{
	print $HTTP_SESSION_VARS['brian'];
}


?>
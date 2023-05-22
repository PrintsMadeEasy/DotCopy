<?
require_once("library/Boot_Session.php");




if(WebUtil::GetCookie("TestingCookie") != "yes"){

	print "Error Occured";
}
else{
	print "It worked";
}


?>


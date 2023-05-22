<?php


// It is very common for PHP scripts to call session_start() at the beggining.
// ... in which case you don't want to be repetitive on all of the files.
// But on some scripts you want to boot up your contstants file without starting a new session.
// An example of this is when you want to do custom header manipulation and don't want the webserver trying to send out a session cookie.
// Call Boot_Session.php or Boot_WithoutSession.php to your liking.

if(file_exists("../classes/autoload.php")){
	require_once("../classes/Domain.php");
	require_once("../classes/ExceptionHandler.php");
	require_once("../classes/WebUtil.php");
	require_once("../classes/autoload.php");
	require_once("../constants/Constants.php");
	require_once("../classes/ExceptionHandler.php");
}
else{
	require_once("classes/Domain.php");
	require_once("classes/ExceptionHandler.php");
	require_once("classes/WebUtil.php");
	require_once("classes/autoload.php");
	require_once("constants/Constants.php");
	require_once("classes/ExceptionHandler.php");
}
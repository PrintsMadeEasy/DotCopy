<?

require_once("library/Boot_Session.php");



header ("Content-Type: text/xml");


// It seems that when you hit session_start it will send a Pragma: NoCache in the header
// When comminicating over HTTPS there is a bug with IE.  It can not get the flash documents after they have finished downloading because they have already expired
// We can allow the file to be cached be sending this header.  When requesting the document you can put a ?noache=timestamp
// This is the only way to get flash communication to work over HTTPS with session variables
header("Pragma: public");

$sideNumber = WebUtil::GetSessionVar("SideNumber", "0");

$userAgent = WebUtil::GetSessionVar("UserAgent");

// In case the editing tool hasn't set the session variable... try to get a default value.
if(empty($userAgent)){
	if (isset($_SERVER['HTTP_USER_AGENT']) && strstr($_SERVER['HTTP_USER_AGENT'],'MSIE'))
		$userAgent = "MSIE";
}

session_write_close();

// Let flash know what the Current Side Number we should be viewing.
// This session var should be set by step3 everytime the page is loaded.
print "<?xml version=\"1.0\" ?>\n<response><side>" . $sideNumber ."</side><useragent>$userAgent</useragent></response>";



?>

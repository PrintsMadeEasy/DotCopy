<?


require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();
$userIsLoggedInFlag = Authenticate::CheckIfVisitorLoggedIn($dbCmd);

// Close the session lock as soon as possible.
session_write_close();

header ("Content-Type: text/xml");


#-- It seems that when you hit session_start it will send a Pragma: NoCache in the header
#-- When comminicating over HTTPS there is a bug with IE.  It can not get the flash documents after they have finished downloading because they have already expired
#-- We can allow the file to be cached be sending this header.  When requesting the document you can put a ?noache=timestamp
#-- This is the only way to get flash communication to work over HTTPS with session variables
header("Pragma: public");


print "<?xml version=\"1.0\" ?>\n";

if($userIsLoggedInFlag)
	print "<logged_on>yes</logged_on>";
else
	print "<logged_on>no</logged_on>";

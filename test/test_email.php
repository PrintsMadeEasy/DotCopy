<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();




print "sending email...<br><br>";

$emailAddress = "Brian@PrintsMadeEasy.com";
$EmailMessage = "This is a test message.";


WebUtil::SendEmail("PrintsMadeEasy.com", "Test@PrintsMadeEasy.com", "Email Test", $emailAddress, "Test Email", $EmailMessage, false);

	
// output to browser
print "email sent<br><br>";
?>

<?

require_once("library/Boot_Session.php");

$mailObj = new Mail();
$password = $mailObj->AddNewPop3Account("Christian", "vinylbanners.com");
print "The pop3 password is " . $password;

?>
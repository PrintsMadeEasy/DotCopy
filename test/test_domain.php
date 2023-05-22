<?

require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


$domainObj = Domain::singleton();


$domainObj->getDomainKeyFromURL();


?>

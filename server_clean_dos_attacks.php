<?

require_once("library/Boot_Session.php");

$dbCmd = new DbCmd();


$dbCmd->Query("DELETE FROM dosattack WHERE DateCreated < '".DbCmd::FormatDBDateTime(time() - (60 * 60 * 24))."'");
$dbCmd->Query("OPTIMIZE TABLE dosattack");

print "done";



<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();

// Make this script be able to run for a while 
set_time_limit(20000);

TableRotations::PossiblyRotateBinaryTables($dbCmd);

print "Finish Table Rotation Check";

?>
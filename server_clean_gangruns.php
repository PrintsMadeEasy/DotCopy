<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();

set_time_limit(500);

print "starting to clean gang runs.<br>\n";
flush();


GangRun::cleanOutCompletedJobs($dbCmd);

print "Done cleaning gang runs";

?>
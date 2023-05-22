<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();


$tableNameFinalized = WebUtil::GetInput("tablename", FILTER_SANITIZE_STRING_ONE_LINE);
$backupFileName = WebUtil::GetInput("filename", FILTER_SANITIZE_STRING_ONE_LINE);
$MaxIDofTable = WebUtil::GetInput("maxid", FILTER_SANITIZE_INT);


print "Finalizing Table: " . $tableNameFinalized;



// The DEV server should have backed up and protected the table.  So delete the entry from this table
$dbCmd->Query("DELETE FROM tablerotationbackups WHERE TableName='". DbCmd::EscapeSQL($tableNameFinalized)."'");

WebUtil::SendEmail(Constants::GetMasterServerEmailName(), Constants::GetMasterServerEmailAddress(), "Webmaster", Constants::GetAdminEmail(), "Finished Table Rotation: $tableNameFinalized", "Finalized the backup for $tableNameFinalized .  \n\nDownload the file name \n$backupFileName \nfrom the Development server.   After it has finished downloading be sure to write it to DVD to absolutely protect it.  Make sure to store it with the other finalized Binary Backup DVD's.\n\n****** BE SURE to Update the file \"backuplist_BinaryTables\" (located in unix_files on my computer) and upload it to the Development server.  \nThe development server needs to start backing up the new database table that is being written to.  The suffix to the table name is 1 greater than the table we have just rotated.  Otherwise the system will keep backing up the old table (that we just rotated).");



print "Finished Finalizing Table";

?>
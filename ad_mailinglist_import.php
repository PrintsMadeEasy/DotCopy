<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();


// Make this script be able to run for a while
set_time_limit(90000);
ini_set("memory_limit", "512M");



//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();



if(!$AuthObj->CheckForPermission("MAILING_BATCHES_CREATE"))
	WebUtil::PrintAdminError("Not Available");

$batchID = WebUtil::GetInput("batchID", FILTER_SANITIZE_INT);
$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);



$t = new Templatex(".");

$t->set_file("origPage", "ad_mailinglist_import-template.html");

$t->set_var("BATCHID", $batchID);



if(empty($action))
	$t->discard_block("origPage", "ProgressBL");
else if($action == "uploadfile")
	$t->discard_block("origPage", "UploadFormBL");
else
	throw new Exception("Illegal action was sent.");


$t->pparse("OUT","origPage");
Constants::FlushBufferOutput();
sleep(1);




if($action == "uploadfile"){


	$mailingBatchObj = new MailingBatch($dbCmd, $UserID);
	$mailingBatchObj->loadBatchByID($batchID);

	if($_FILES['csvfile']['size'] == 0 ) {
	
		print "\n<script>";
		print "ShowError('" . addslashes("You forgot to choose a file for uploading.") . "');";
		print "</script>\n";
		exit;
	}
		
		
	$file_params = pathinfo($_FILES['csvfile']['name']);

	
	if(strtoupper($file_params["extension"]) != "CSV"){
		print "\n<script>";
		print "ShowError('" . addslashes("Only CSV files are accepted for import.  You tried to upload a ." . strtoupper($file_params["extension"]) . " file.") . "');";
		print "</script>\n";
		exit;
	}
	
	
	if(!$mailingBatchObj->importData($_FILES['csvfile']['tmp_name'], "javascript")){
		print "\n<script>";
		print "ShowError('" . addslashes($mailingBatchObj->getErrorMessage()) . "');";
		print "</script>\n";
		exit;
	}
		
	
	// Then the list was successfully imported.
	print 	"
		<script>
		window.opener.location = window.opener.location;
		</script>
		";
		
		print "\n<script>";
		print "ShowCurrentTask('<b>Mailing List was Imported Successfully</b><br>Pop-up window closing...');";
		print "</script>\n";
		
		print "\n<script>";
		print "ShowProgress('');";
		print "</script>\n";

		print "\n<script>";
		print "ClosePopUpDelayed();";
		print "</script>\n";
		
		exit;
}




?>
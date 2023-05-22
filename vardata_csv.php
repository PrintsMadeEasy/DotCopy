<?

require_once("library/Boot_Session.php");

// Give it at most 5 minutes to download a large data file
set_time_limit(300);



$dbCmd = new DbCmd();





$projectrecord = WebUtil::GetInput("projectrecord", FILTER_SANITIZE_INT);
$editorview = WebUtil::GetInput("editorview", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);

$user_sessionID =  WebUtil::GetSessionID();


ProjectBase::EnsurePrivilagesForProject($dbCmd, $editorview, $projectrecord);





$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $editorview, $projectrecord);

if(!$projectObj->isVariableData())
	throw new Exception("This project does not contain variable data.");

$varDataObj = new VariableData();

$varDataObj->loadDataByTextFile($projectObj->getVariableDataFile());

$csvFile = $varDataObj->getCSVfile();

// Make the file name hashed from the project record... chop it down to 10 characters.
$downloadfileName = "DataFile_" . substr(md5($projectrecord), 7, 10) . ".csv";


// Put on disk
$fp = fopen(DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $downloadfileName, "w");
fwrite($fp, $csvFile);
fclose($fp);


$fileDownloadLocation = WebUtil::FilterURL(DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $downloadfileName . "?nocache=" . time());


$t = new Templatex();

$t->set_file("origPage", "vardata_csv-template.html");

$t->set_var("FILELOCATION_JS", addslashes($fileDownloadLocation));
$t->pparse("OUT","origPage");


?>
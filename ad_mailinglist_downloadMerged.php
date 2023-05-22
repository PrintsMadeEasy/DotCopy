<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();


// Make this script be able to run for a while
set_time_limit(90000);
ini_set("memory_limit", "100M");



//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();



if(!$AuthObj->CheckForPermission("MAILING_LIST_IMPORT"))
	WebUtil::PrintAdminError("Not Available");

$batchID = WebUtil::GetInput("batchID", FILTER_SANITIZE_INT);
$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);



$mailingBatchObj = new MailingBatch($dbCmd, $UserID);
$mailingBatchObj->loadBatchByID($batchID);


if($mailingBatchObj->getImportedLineCount() == 0)
	throw new Exception("You must have a Line Count with the imported list before downloading a merged list.");



$variableDataSortingObj = $mailingBatchObj->getProjectSortingObj();

$totalDataLines = $variableDataSortingObj->getTotalRows();

$projectOrderedIDarr = $variableDataSortingObj->getUniqueProjectIDarr();



// This will hold an array for the first line of data.  It will the Column Headers (or Variable Names).
$headerLineArr = array();

// Cache all of the Project Data files in an Array before we start cycling through each row number.
$variableDataObjArr = array();
foreach($projectOrderedIDarr as $thisProjectID){

	$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "ordered", $thisProjectID);
	
	$variableDataObj = new VariableData();
	$variableDataObj->loadDataByTextFile($projectObj->getVariableDataFile());
	
	$variableDataObjArr[$thisProjectID] = $variableDataObj;
	
	// Use the First Project to extract the header line.
	if(empty($headerLineArr)){
		$artworkVarsObj = new ArtworkVarsMapping($projectObj->getArtworkFile(), $projectObj->getVariableDataArtworkConfig());
		$headerLineArr = $artworkVarsObj->getMappedVariableNamesArr();
	}
}


$allDataLines2dArr = array();
$allDataLines2dArr[] = $headerLineArr;


for($i=1; $i<=$totalDataLines; $i++){
	
	
	// Based upon the sequence in the CASS certified list... extract the data from the appropriate Project, and Line number.
	$projectIDonThisLine = $variableDataSortingObj->getProjectIDAtRowNum($i);
	$varDataLineNumberInsideProject = $variableDataSortingObj->getDataLineNumberAtRowNum($i);
	
	$variableDataObj = new VariableData();
	
	// The method getVariableRowByLineNo takes a 0 based array, whereas the variable data sorting object is 1 based.
	$dataRow = $variableDataObjArr[$projectIDonThisLine]->getVariableRowByLineNo(($varDataLineNumberInsideProject - 1));
	
	// Add to our 2D array
	$allDataLines2dArr[] = $dataRow;
	
}

$csvData = "";

foreach($allDataLines2dArr as $thisLine){

	foreach($thisLine as $thisElement){
	
		// Fields with an embedded double quote must have the field surounded by double quotes and have all of the double quotes insided replaced by a pair of doubles
		// Fields with an embedded comma must be surrounded in double quotes.
		if(preg_match('/"/', $thisElement))
			$thisElement = '"' . preg_replace('/"/', '""', $thisElement) . '"';
		else if(preg_match("/,/", $thisElement))
			$thisElement = '"' . $thisElement . '"';
	
		$csvData .= $thisElement . ",";
	}
	
	// strip off the last comma
	$csvData = substr($csvData, 0, -1);
	
	$csvData .= "\n";
}



$downloadfileName = "MergedData_BatchID_" . $batchID . "_" . substr(md5($batchID . "extraSequrE"), 10, 20) . ".csv";

// Put on disk
$fp = fopen(DomainPaths::getPdfSystemPathOfDomainInURL() . "/" . $downloadfileName, "w");
fwrite($fp, $csvData);
fclose($fp);

$fileDownloadLocation = WebUtil::FilterURL(DomainPaths::getPdfWebPathOfDomainInURL() . "/" . $downloadfileName);




header("Location: " . WebUtil::FilterURL($fileDownloadLocation));




<?

require_once("library/Boot_Session.php");

// Give it at most 5 minutes to download a large data file
set_time_limit(300);



$dbCmd = new DbCmd();





$projectrecord = WebUtil::GetInput("projectrecord", FILTER_SANITIZE_INT);
$editorview = WebUtil::GetInput("editorview", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);


// We may want to padd extra columns on the end of our data file.
// For example when we are generating a DHMTL table we want blank rows and columns so that people can add stuff easily.
$padcolumns = WebUtil::GetInput("padcolumns", FILTER_SANITIZE_INT);
$padrows = WebUtil::GetInput("padrows", FILTER_SANITIZE_INT);



$user_sessionID =  WebUtil::GetSessionID();


ProjectBase::EnsurePrivilagesForProject($dbCmd, $editorview, $projectrecord);





$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, $editorview, $projectrecord);

if(!$projectObj->isVariableData())
	throw new Exception("This project does not contain variable data.");



$artworkVarsObj = new ArtworkVarsMapping($projectObj->getArtworkFile(), $projectObj->getVariableDataArtworkConfig());

$MappedArtworkVarsArr = array();

// Don't show the Column names if there are mapping errors.
if(!$artworkVarsObj->checkForMappingErrors())
	$MappedArtworkVarsArr = $artworkVarsObj->getMappedVariableNamesArr();
	
	


$varDataObj = new VariableData();

$varDataObj->loadDataByTextFile($projectObj->getVariableDataFile());


// If there is no data... Trying to get the Number of Fields from our VariableData object will fail.
if($projectObj->getQuantity() == 0){
	$numberOfColumns = 0;
	$numberOfRows = 0;
}
else{
	$numberOfColumns = $varDataObj->getNumberOfFields();
	$numberOfRows = $varDataObj->getNumberOfLineItems();
}
	
$numberOfColumnsPadded = $numberOfColumns + $padcolumns;
$numberOfRowsPadded = $numberOfRows + $padrows;


// We want to show at least on blank column.
if($numberOfColumnsPadded == 0)
	$numberOfColumnsPadded = 1;
if($numberOfRowsPadded == 0)
	$numberOfRowsPadded = 1;
	


// In case their are more Artwork Variables than there are Columns of Data... we need to show the greater of the 2.
if(sizeof($MappedArtworkVarsArr) > $numberOfColumnsPadded)
	$numberOfColumnsPadded = sizeof($MappedArtworkVarsArr);

$retXML = "<?xml version=\"1.0\" encoding=\"iso-8859-1\"?>\n";

$retXML .= "<rows>\n";





$retXML .= '<head>';

for($j=0; $j < $numberOfColumnsPadded; $j++){
	

	if(isset($MappedArtworkVarsArr[$j]))
		$colHeader = WebUtil::htmlOutput($MappedArtworkVarsArr[$j]);
	else
		$colHeader = "";
	
        $retXML .= '<column width="100" type="edtxt" align="left" color="white" sort="na">' . $colHeader . '</column>';
}
        
$retXML .= '
        <settings>
            <colwidth>px</colwidth>
        </settings>
    </head>
    ';



for($i=0; $i < $numberOfRowsPadded; $i++){

	$retXML .= "<row id=\"" . ($i + 1) . "\">\n";
	

	
	for($j=0; $j < $numberOfColumnsPadded; $j++){
	
		if($j < $numberOfColumns && $i < $numberOfRows){
		
			$dataLineArr = $varDataObj->getVariableRowByLineNo($i);
		
			$retXML .= "<cell>" . htmlspecialchars($dataLineArr[$j]) . "</cell>";
		}
		else{
			$retXML .= "<cell></cell>";
		}
	}
	

	
	$retXML .= "</row>\n";
}

$retXML .= "</rows>\n";



header ("Content-Type: text/xml");


// It seems that when you hit session_start it will send a Pragma: NoCache in the header
// When comminicating over HTTPS there is a bug with IE.  It can not get the flash documents after they have finished downloading because they have already expired
// We can allow the file to be cached be sending this header.  When requesting the document you can put a ?noache=timestamp
// This is the only way to get flash communication to work over HTTPS with session variables
header("Pragma: public");


print $retXML;


?>
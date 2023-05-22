<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();

$reportFileName = "business-cards-report.csv";

$csvText = file_get_contents($reportFileName);
$csvArr = split("\n", $csvText);

$outputFile = "";
foreach($csvArr as $thisCsvLine){
	if(!preg_match("/business\scards,Exact/", $thisCsvLine))
		continue;
	print $thisCsvLine . "<br>-<br>";
	$outputFile .= $thisCsvLine . "\n";
}

file_put_contents("new_" . $reportFileName, $outputFile);

print "done";
<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();

$reportFileName = "Labor-Day.csv";


$outputFile = "";
$csv1 = new ParseCSV($reportFileName);

$counter = 0;
while ($row = $csv1->NextLine()){
	
	$counter++;	
	//if(!preg_match("/business\scards,Exact/", $thisCsvLine))
	//	continue;
	
	if(sizeof($row) < 10)
		continue;
		
		
	$sequenceNum = $row[0];
	$endorse = $row[1];
	$name = $row[2];
	$address = $row[3];
	$city = $row[4];
	$state = $row[5];
	$zip5 = $row[6];
	$zip4 = $row[7];
	$dp = $row[8];
	$checkDigit = $row[9];
	
	// Make sure this is only 1 space
	$name = preg_replace("/\s+/", " ", $name);
	
	
	$greetingName = "";
	
	$greetingParts = split(" ", $name);
print sizeof($greetingParts);
	$currentCharCounerBeforeLineBreak = 0;
	$totalLineBreaksCreated = 0;
	
	for($i=0; $i<sizeof($greetingParts); $i++){
		
		if($currentCharCounerBeforeLineBreak > 3 && $totalLineBreaksCreated < 2){
			$greetingName .= "!br!";
			$currentCharCounerBeforeLineBreak = 0;
			$totalLineBreaksCreated++;
		}
		else if($i > 0){
			$greetingName .= " ";
		}
		print $greetingParts[$i];
		
		if($i < (sizeof($greetingParts) - 1))
			print "|******|";
		
		$greetingName .= $greetingParts[$i];
		
		$currentCharCounerBeforeLineBreak += strlen($greetingParts[$i]);
	}
print "<br>\n";	
	
	$greetingName = trim($greetingName);
	
	// Make sure there are leading zeros in front of the 5 digit zip code.
	if(strlen($zip5 == 3))
		$zip5 = "00" . $zip5;
	else if(strlen($zip5 == 4))
		$zip5 = "0" . $zip5;
	
	// Make sure there are leading zeros in front of the 4 digit zip code.
	if(strlen($zip4 == 2))
		$zip4 = "00" . $zip4;
	else if(strlen($zip4 == 3))
		$zip4 = "0" . $zip4;
		
	
	$barcode = $zip5 . $zip4 . $dp . $checkDigit;
	
	$outputFile .= FileUtil::csvEncodeLineFromArr(array($name, $greetingName, $address, $city, $state, $zip5, $zip4, $barcode, $endorse, $sequenceNum)) . "\n";

}

file_put_contents("new_" . $reportFileName, $outputFile);

print "done";
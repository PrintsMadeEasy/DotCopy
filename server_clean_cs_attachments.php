<?

// This script will clean out Customer Service attachments older than X number of day.

$numberOfDaysBackToCheck = 180;
$directoryName = "/home/printsma/public_html/customer_attachments";


set_time_limit(28800);

$numberOfDaysTimeStamp = time() - (60 * 60 * 24 * $numberOfDaysBackToCheck);

@$dir = opendir($directoryName); 

if(!$dir)
	throw new Exception("This is not a directory.");

while($entry = readdir($dir)){ 

	if($entry == ".." || $entry == ".") 
		continue;

	$fullFileNamePath = $directoryName.'/'.$entry;

	$last_modified = filemtime($fullFileNamePath);
	$last_modified_str= date("Y-m-d", $last_modified);
	
	if(filesize($fullFileNamePath) > (1024 * 1024 * 40)){
		echo "<h1>Big File: " . $fullFileNamePath.'=>'.$last_modified_str . " Size: " . round(filesize($fullFileNamePath) / (1024 * 1024), 1) . " megs </h1>";
		echo "<BR>";
		@unlink($fullFileNamePath);
	}

	if($numberOfDaysTimeStamp > $last_modified)  {
		echo $fullFileNamePath.'=>'.$last_modified_str;
		echo "<BR>";
		@unlink($fullFileNamePath);
	}
	else{
		echo "NOT OLD ENOUGH: ";
		echo $fullFileNamePath.'=>'.$last_modified_str;
		echo "<BR>";

	}

}



?>
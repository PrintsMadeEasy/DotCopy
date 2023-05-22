<?

require_once("library/Boot_Session.php");

// This script will clean out PDF Previews older than 5 days

$numberOfDaysBackToCheck = 3;


set_time_limit(28800);


$domainObj = Domain::singleton();
$domainIDsArr = $domainObj->getAllDomainIDs();

foreach($domainIDsArr as $thisDomainID){

	$directoryName = Domain::getDomainSandboxPath(Domain::getDomainKeyFromID($thisDomainID));
	$directoryName .= "/previews";
	
	print "<u>$directoryName</u><br><br><br>";
	
	@$dir = opendir($directoryName); 
	
	if(!$dir)
		continue;
	
	while($entry = readdir($dir)){ 
	
		if($entry == ".." || $entry == ".") 
			continue;
	
		$fullFileNamePath = $directoryName.'/'.$entry;
	
		$last_modified = filemtime($fullFileNamePath);
		$last_modified_str= date("Y-m-d", $last_modified);
		$lastModifiedDaysAgo = round((time() - $last_modified) / (60 * 60 * 24));
	
		if($numberOfDaysBackToCheck < $lastModifiedDaysAgo)  {
			echo $fullFileNamePath.'=>'.$last_modified_str;
			echo $last_modified_str;
			echo "<BR>";
			@unlink($fullFileNamePath);
		}
		else{
			echo "NOT OLD ENOUGH: ";
			echo $fullFileNamePath.'=>'.$last_modified_str;
			echo "<BR>";
	
		}
	
	}
}



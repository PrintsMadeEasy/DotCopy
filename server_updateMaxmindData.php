<?

require_once("library/Boot_Session.php");

set_time_limit(5000);

$dbCmd = new DbCmd();

exit("Re-Create Tables by Drop/Insert before importing.");

if(Constants::GetDevelopmentServer())
	$pathToCSVfile = "C:\\downloads\MaxMind Geo IPs\\GeoIP-134_20090801\\GeoIPCity-134-Location.csv";
else 
	$pathToCSVfile = "/home/printsma/programs/MaxMindGEOIPs/GeoIPCity-134-Location.csv";

$csv1 = new ParseCSV($pathToCSVfile);

$counter = 0;
while ($row = $csv1->NextLine()){
	
	$counter++;	

	//if($counter > 1000)
	//	break;	

	
	// Skip the header lines (which don't have an integer in Column A.
	if(!preg_match("/^\d+$/", $row[0]))
		continue;
		
	$insertLocationRow["LocationID"] = $row[0];
	$insertLocationRow["Country"] = $row[1];
	$insertLocationRow["Region"] = $row[2];
	$insertLocationRow["City"] = $row[3];
	$insertLocationRow["PostalCode"] = $row[4];
	$insertLocationRow["Latitude"] = $row[5];
	$insertLocationRow["Longitude"] = $row[6];
	$insertLocationRow["MetroCode"] = $row[7];
	$insertLocationRow["AreaCode"] = $row[8];

	$dbCmd->InsertQuery("maxmindlocationdetails", $insertLocationRow);
		
	if($counter > 3000){
		print $row[0] . "-<br>\n";
		flush();
		$counter = 0;
	}
}




print "<hr><hr>Going on to Location IDs.<hr><hr>";


if(Constants::GetDevelopmentServer())
	$pathToCSVfile = "C:\\downloads\MaxMind Geo IPs\\GeoIP-134_20090801\\GeoIPCity-134-Blocks.csv";
else 
	$pathToCSVfile = "/home/printsma/programs/MaxMindGEOIPs/GeoIPCity-134-Blocks.csv";

$csv2 = new ParseCSV($pathToCSVfile);

$lastStartIPnumber = 0;

$recordCounter = 0;
$counter = 0;
while ($row = $csv2->NextLine()){
	
	$counter++;	

	//if($counter > 1000)
	//	break;	

	// Skip the header lines (which don't have an integer in Column A.
	if(!preg_match("/^\d+$/", $row[0]))
		continue;
		
	$recordCounter++;
	
	if($lastStartIPnumber >= $row[0])
		throw new Exception("The Start IP address is out of sequence on Row: $counter");
	$lastStartIPnumber = $row[0];
		

	$insertIPLocationBlock["Counter"] = $recordCounter;
	$insertIPLocationBlock["StartIP"] = $row[0];
	$insertIPLocationBlock["EndIP"] = $row[1];
	$insertIPLocationBlock["LocationID"] = $row[2];

	$dbCmd->InsertQuery("maxmindlocationids", $insertIPLocationBlock);
		
	if($counter > 3000){
		print $row[0] . "-<br>\n";
		flush();
		$counter = 0;
	}
}



print "<hr><hr>Going on to ISPs.<hr><hr>";





if(Constants::GetDevelopmentServer())
	$pathToCSVfile = "C:\\downloads\MaxMind Geo IPs\\GeoIP-124_20090801\\GeoIPISP.csv";
else 
	$pathToCSVfile = "/home/printsma/programs/MaxMindGEOIPs/GeoIPISP.csv";

$csv3 = new ParseCSV($pathToCSVfile);

$lastStartIPnumber = 0;

$recordCounter = 0;
$counter = 0;
while ($row = $csv3->NextLine()){
	
	$counter++;	
	

	//if($counter > 1000)
	//	break;	

	// Skip the header lines (which don't have an integer in Column A.
	if(!preg_match("/^\d+$/", $row[0]))
		continue;
		
	$recordCounter++;
	
	if($lastStartIPnumber >= $row[0])
		throw new Exception("The Start IP address is out of sequence on Row: $counter");
	$lastStartIPnumber = $row[0];
		

	$insertISP["Counter"] = $recordCounter;
	$insertISP["StartIP"] = $row[0];
	$insertISP["EndIP"] = $row[1];
	$insertISP["ISPname"] = $row[2];

	$dbCmd->InsertQuery("maxmindisps", $insertISP);
		
	if($counter > 1000){
		print $row[0] . "-<br>\n";
		flush();
		$counter = 0;
	}
}



print "<hr><hr>ALL Done";





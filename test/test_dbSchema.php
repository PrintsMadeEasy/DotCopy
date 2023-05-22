<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$dbCmd->Query("SHOW TABLES");
$tablesArr = $dbCmd->GetValueArr();


foreach($tablesArr as $thisTableName){

	// Don't show Binary Tables
	if(preg_match("/^imagessaved/", $thisTableName))
		continue;
	if(preg_match("/^imagestemp/", $thisTableName))
		continue;
	if(preg_match("/^imagessession/", $thisTableName))
		continue;	
	if(preg_match("/^vectorimagessess/", $thisTableName))
		continue;
	if(preg_match("/^vectorimagestemp/", $thisTableName))
		continue;
	if(preg_match("/^vectorimagessaved/", $thisTableName))
		continue;
	if(preg_match("/^thumbnailimages/", $thisTableName))
		continue;
	if(preg_match("/^messagesattachments/", $thisTableName))
		continue;
	if(preg_match("/^gangruns/", $thisTableName))
		continue;	
	if(preg_match("/^bannerlog/", $thisTableName))
		continue;	
	if(preg_match("/^imagesclean/", $thisTableName))
		continue;	
	if(preg_match("/^imagesdeleted/", $thisTableName))
		continue;	
	if(preg_match("/^shippingaddresscache_ups/", $thisTableName))
		continue;	
	if(preg_match("/^shippingtransitcache_ups/", $thisTableName))
		continue;	
	if(preg_match("/^vectorimagesclean/", $thisTableName))
		continue;	
	if(preg_match("/^vectorimagesdeleted/", $thisTableName))
		continue;	
	if(preg_match("/^variableimages/", $thisTableName))
		continue;	
		

	//print $thisTableName . "<br>";
	//continue;
		
	print "\n\n\n\n\n\n\n" . $thisTableName . "\n---------------------------------------\n";
	
	
	
	$dbCmd2->Query("SHOW COLUMNS FROM " . mysql_escape_string ( $thisTableName ));
	
	while($columnsArr = $dbCmd2->GetRow()){
		
		
		
		
		$totalColSize = strlen($columnsArr["Field"]);
		$maxColsizeName = 35;
		$diffColName = $maxColsizeName - $totalColSize;

		
		print $columnsArr["Field"];
		
		for($i=0; $i<=$diffColName; $i++){
			print " ";
		}
		
		
		
		
		
		
		$columnsArr["Type"] = preg_replace("/timestamp\(14\)/", "timestamp", $columnsArr["Type"]);
		
		$totalTypeSize = strlen($columnsArr["Type"]);
		$maxTypesizeName = 20;
		$diffTypeSize = $maxTypesizeName - $totalTypeSize;
		
		
		print $columnsArr["Type"];
		
		for($i=0; $i<=$diffTypeSize; $i++){
			print " ";
		}
	
	
	
	
	

		$totalNullSize = strlen($columnsArr["Null"]);
		$maxNullsizeName = 15;
		$diffNullSize = $maxNullsizeName - $totalNullSize;
		
		print $columnsArr["Null"];
		
		for($i=0; $i<=$diffNullSize; $i++){
			print " ";
		}
	
	
	

	

		$totalKeySize = strlen($columnsArr["Key"]);
		$maxKeysizeName = 10;
		$diffKeySize = $maxKeysizeName - $totalKeySize;
		
		print $columnsArr["Key"];
		
		for($i=0; $i<=$diffKeySize; $i++){
			print " ";
		}
		



		$columnsArr["Default"] = preg_replace("/CURRENT_TIMESTAMP/", "", $columnsArr["Default"]);

		$totalDefaultSize = strlen($columnsArr["Default"]);
		$maxDefaultizeName = 10;
		$diffDefaultSize = $maxDefaultizeName - $totalDefaultSize;
		
		print $columnsArr["Default"];
		
		for($i=0; $i<=$diffKeySize; $i++){
			print " ";
		}
		
		
		
		
	

		$totalExtraSize = strlen($columnsArr["Extra"]);
		$maxExtraizeName = 10;
		$diffExtraSize = $maxExtraizeName - $totalExtraSize;
		
		print $columnsArr["Extra"];
		
		for($i=0; $i<=$diffExtraSize; $i++){
			print " ";
		}
		
				
		

		
		
		print "\n";
		
		
		
		
		
		
		
		
	
	}

}





?>
<?

require_once("library/Boot_Session.php");

set_time_limit(50000);

//throw new Exception("Remove this Exit statement.");

$copyFromProductID = 172;
$copyToProductID = 181;


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();
$dbCmd3 = new DbCmd();


print "Search Engine<br><br>";


$dbCmd->Query("SELECT * FROM artworksearchengine WHERE ProductID=$copyFromProductID ORDER BY ID ASC");
while($row = $dbCmd->GetRow()){
	
	$templateID = $row["ID"];
	$tempalteArtfile = $row["ArtFile"];
	$productID = $row["ProductID"];
	$tempalteSort = $row["Sort"];
	
	$tempalteArtfile = preg_replace("/The Smiths/i", "Frosty Snowman", $tempalteArtfile);
	
	$newTemplateID = $dbCmd2->InsertQuery("artworksearchengine", array("ArtFile"=>$tempalteArtfile, "ProductID"=>$copyToProductID, "Sort"=>$tempalteSort));
	
	
	$dbCmd2->Query("SELECT * FROM templatekeywords WHERE TemplateID=$templateID");
	while($row = $dbCmd2->GetRow()){
		$dbCmd3->InsertQuery("templatekeywords", array("TempKw"=>$row["TempKw"], "TemplateID"=>$newTemplateID));
	}
	
	

	ThumbImages::CreateTemplatePreviewImages($dbCmd2, "template_searchengine", $newTemplateID);
		
	print "TID: $templateID : ";
	flush();


}

print "<hr>done";



?>
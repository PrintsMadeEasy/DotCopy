<?

require_once("library/Boot_Session.php");


//throw new Exception("Remove this Exit statement.");

set_time_limit(5000);

$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

/*
$dbCmd->Query("SELECT ArtworkID, ArtFile FROM artworkstemplates WHERE ProductID=78 OR ProductID=79");
while($row = $dbCmd->GetRow()){

	$ArtworkInfoObj = new ArtworkInformation($row["ArtFile"]);
	
	print $ArtworkInfoObj->SideItemsArray[0]->background_width . "<br>";
	
	$ArtworkInfoObj->SideItemsArray[0]->background_width = 592;
	$ArtworkInfoObj->SideItemsArray[0]->background_height = 400;

	$ArtworkInfoObj->SideItemsArray[0]->background_x = "-296";
	$ArtworkInfoObj->SideItemsArray[0]->background_y = "-200";
	
	
	$dbCmd2->Query("UPDATE artworkstemplates SET ArtFile=\"" . DbCmd::EscapeSQL($ArtworkInfoObj->GetXMLdoc()) . "\" WHERE ArtworkID=" . $row["ArtworkID"]);

}
*/

print "Search Engine<br><br>";

$lastTemplateID = 0;
$dbCmd->Query("SELECT ID, SearchEngineID FROM artworkstemplatespreview WHERE ProductID=228 ORDER BY ID ASC");
while($row = $dbCmd->GetRow()){
	
	$previewID = $row["ID"];
	$tempalteID = $row["SearchEngineID"];

	if($tempalteID != $lastTemplateID){
		$lastTemplateID = $tempalteID;
		
		$ImagePeviewFileName = ThumbImages::GetTemplatePreviewName($previewID, "template_searchengine");
		
		if(file_exists(Constants::GetTempImageDirectory () . "/" . $ImagePeviewFileName)){
			if(!unlink(Constants::GetTempImageDirectory () . "/" . $ImagePeviewFileName))
				print "--- couldn't remove $ImagePeviewFileName <br>";
		}
		

		
		if(!file_exists(Constants::GetTempImageDirectory () . "/" . $ImagePeviewFileName)){
			print "----";	
			ThumbImages::CreateTemplatePreviewImages($dbCmd2, "template_searchengine", $tempalteID);
			
			print "TID: $tempalteID : ";
			flush();
		}
	}
	else {
		
		print "xxxx <br>";
	}
}

print "<hr>done";



?>
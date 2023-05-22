<?

require_once("library/Boot_Session.php");

set_time_limit(50000);

//throw new Exception("Remove this Exit statement.");


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


print "Search Engine<br><br>";

$counter = 0;

$dbCmd->Query("SELECT ID FROM artworksearchengine ORDER BY ID ASC");
while($row = $dbCmd->GetRow()){
	
	$TemplateID = $row["ID"];
	
	$dbCmd2->Query("SELECT * FROM artworkstemplatespreview WHERE SearchEngineID = $TemplateID ORDER BY ID ASC LIMIT 1");
	$previewRow = $dbCmd2->GetRow();
	
	$isLandscapeOrSquareFlag = true;
	if($previewRow["Width"] < $previewRow["Height"])
		$isLandscapeOrSquareFlag = false;
	


	if($isLandscapeOrSquareFlag){
		ArtworkTemplate::AddKeywordsToTemplate($dbCmd2, "horizontal", $TemplateID);
		$dbCmd2->Query("DELETE FROM templatekeywords WHERE TemplateID=".intval($TemplateID)." AND TempKw LIKE '" . DbCmd::EscapeLikeQuery("vertical")  . "'");
	}
	else{
		ArtworkTemplate::AddKeywordsToTemplate($dbCmd2, "vertical", $TemplateID);
		$dbCmd2->Query("DELETE FROM templatekeywords WHERE TemplateID=".intval($TemplateID)." AND TempKw LIKE '" . DbCmd::EscapeLikeQuery("horizontal")  . "'");
	}

		
	print "$counter : TID: $TemplateID : " . ($isLandscapeOrSquareFlag ? "landscape" : "portrait");
	flush();
	
	
	$counter++;

}

print "<hr>done";



?>
<?

require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();
$dbCmd3 = new DbCmd();



/*
print "Starting to Copy Over the Single Sided Envelopes from Categories<br><br>";
flush();

$dbCmd->Query("SELECT ArtworkID, ArtFile, ProductID FROM artworkstemplates WHERE ProductID=80");
while($row = $dbCmd->GetRow()){


	// Will take care of resizing and repositioning based upon the changes to the Canvas Width/Height
	$artworkConversionObj = new ArtworkConversion($dbCmd2);
	$artworkConversionObj->setFromArtwork($row["ProductID"], $row["ArtFile"]);
	$artworkConversionObj->setToArtwork(82);
	$newArtworkFile = $artworkConversionObj->getConvertedArtwork();

	$dbCmd2->Query("SELECT CategoryID FROM templatecategories WHERE ProductID=82 LIMIT 1");
	$newCategoryID = $dbCmd2->GetValue();

	$dbCmd2->Query("SELECT MAX(IndexID) FROM artworkstemplates WHERE CategoryID=$newCategoryID");
	$maxIndex = ($dbCmd2->GetValue() + 1);


	$insertArr = array();
	$insertArr["ArtFile"] = $newArtworkFile;
	$insertArr["ProductID"] = 82;
	$insertArr["CategoryID"] = $newCategoryID;
	$insertArr["IndexID"] = $maxIndex;
	$insertArr["ZoomFactor"] = "50";

	$newArtworkTempalteID = $dbCmd2->InsertQuery("artworkstemplates", $insertArr);


	print $row["ArtworkID"] . "<br>\n";
	flush();
	
	ThumbImages::CreateTemplatePreviewImages($dbCmd2, "template_category", $newArtworkTempalteID);
}

print "Starting to Copy Over the Single Sided Envelopes from Categories<br><br>";
flush();


$dbCmd->Query("SELECT ID, ArtFile, ProductID, Sort FROM artworksearchengine WHERE ProductID=80");
while($row = $dbCmd->GetRow()){

	// Will take care of resizing and repositioning based upon the changes to the Canvas Width/Height
	$artworkConversionObj = new ArtworkConversion($dbCmd2);
	$artworkConversionObj->setFromArtwork($row["ProductID"], $row["ArtFile"]);
	$artworkConversionObj->setToArtwork(82);
	$newArtworkFile = $artworkConversionObj->getConvertedArtwork();


	$insertArr = array();
	$insertArr["ArtFile"] = $newArtworkFile;
	$insertArr["ProductID"] = 82;
	$insertArr["Sort"] = "Q";
	$insertArr["ZoomFactor"] = "50";

	$newArtworkTempalteID = $dbCmd2->InsertQuery("artworksearchengine", $insertArr);


	print "UPDATE templatekeywords SET TemplateID=$newArtworkTempalteID WHERE TemplateID=" . $row["ID"] . "<br>";

	$dbCmd2->Query("UPDATE templatekeywords SET TemplateID=$newArtworkTempalteID WHERE TemplateID=" . $row["ID"]);
	
	$insertKeyArr = array();
	$insertKeyArr["TemplateID"] = $newArtworkTempalteID;
	$insertKeyArr["TempKw"] = "converted";
	$dbCmd2->InsertQuery("templatekeywords", $insertKeyArr);

	print $row["ID"] . "<br>\n";
	flush();
	
	ThumbImages::CreateTemplatePreviewImages($dbCmd2, "template_searchengine", $newArtworkTempalteID);
}

*/







/*

$dbCmd->Query("SELECT ArtworkID, ArtFile, ProductID FROM artworkstemplates");
while($row = $dbCmd->GetRow()){

	$ArtworkInfoObj = new ArtworkInformation($row["ArtFile"]);

	if($row["ProductID"] == 80){

var_dump($row);
print "\n";

		$oldPreviewIDarr = array();
		$dbCmd2->Query("SELECT ID FROM artworkstemplatespreview WHERE TemplateID=" . $row["ArtworkID"]);
		while($oldPreviewID = $dbCmd2->GetValue())
			$oldPreviewIDarr[] = $oldPreviewID;
		
		foreach($oldPreviewIDarr as $oldPreviewID){

			// We need to delete the old preview image from disk... because we will be making a new one.
			// Use the @ to prevent a warning if the file does not exist.
			$ImagePeviewFileName = ThumbImages::GetTemplatePreviewName($oldPreviewID, "template_category");
			@unlink(Constants::GetTempImageDirectory () . "/" . $ImagePeviewFileName);

			// We need to delete the old thumnail image from disk... because we will be making a new one.
			$ImagePeviewFileName = ThumbImages::GetTemplatePreviewFnameAdmin($dbCmd2, $row["ArtworkID"], "template_category");
			@unlink(Constants::GetTempImageDirectory () . "/" . $ImagePeviewFileName);

			// Clean out any existing preview images associated with the template because we are going to put in the new ones.
			$dbCmd2->Query("DELETE FROM artworkstemplatespreview WHERE ID=$oldPreviewID");
		}
		
		$dbCmd2->Query("DELETE FROM artworkstemplates WHERE ArtworkID=" . $row["ArtworkID"]);
		
		print "Removed another ProductID 80<br>";

	}
	else if($row["ProductID"] == 82){
		ThumbImages::CreateTemplatePreviewImages($dbCmd3, "template_category", $row["ArtworkID"]);
	}
	else{
		//$dbCmd2->Query("UPDATE artworkstemplatespreview SET ProductID=\"" . $row["ProductID"] . "\" WHERE TemplateID=" . $row["ArtworkID"]);
	}

	print $row["ArtworkID"] . "<br>\n";
	flush();
}
*/

print "<html>\nSearch Engine\n\n<br><br>";


$dbCmd->Query("SELECT ID, ArtFile FROM artworksearchengine WHERE ArtFile LIKE \"%comany%\"");
while($row = $dbCmd->GetRow()){

	$artFile = $row["ArtFile"];
	$artID = $row["ID"];
	
	//if($row["ProductID"] == 80){
	
/*	
		$oldPreviewIDarr = array();
		$dbCmd2->Query("SELECT ID FROM artworkstemplatespreview WHERE SearchEngineID=" . $row["ID"]);
		while($oldPreviewID = $dbCmd2->GetValue())
			$oldPreviewIDarr[] = $oldPreviewID;
		
		foreach($oldPreviewIDarr as $oldPreviewID){

			// We need to delete the old preview image from disk... because we will be making a new one.
			// Use the @ to prevent a warning if the file does not exist.
			$ImagePeviewFileName = ThumbImages::GetTemplatePreviewName($oldPreviewID, "template_searchengine");
			@unlink(Constants::GetTempImageDirectory () . "/" . $ImagePeviewFileName);

			// We need to delete the old thumnail image from disk... because we will be making a new one.
			$ImagePeviewFileName = ThumbImages::GetTemplatePreviewFnameAdmin($dbCmd2, $row["ID"], "template_searchengine");
			@unlink(Constants::GetTempImageDirectory () . "/" . $ImagePeviewFileName);

			// Clean out any existing preview images associated with the template because we are going to put in the new ones.
			$dbCmd2->Query("DELETE FROM artworkstemplatespreview WHERE ID=$oldPreviewID");
		}
		
		$dbCmd2->Query("DELETE FROM artworksearchengine WHERE ID=" . $row["ID"]);
		
		print "Removed another ProductID 80 from search engine.<br>";
*/	

	$artFile = preg_replace("/comany/i", "Company", $artFile);
	
	$dbCmd2->UpdateQuery("artworksearchengine", array("ArtFile"=>$artFile), "ID=$artID");

	print $artID . "<br>\n";
	flush();

}





?>
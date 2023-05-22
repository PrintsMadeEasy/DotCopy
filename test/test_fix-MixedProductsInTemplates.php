<?


require_once("library/Boot_Session.php");


$dbCmd = new DbCmd();



// There was a case where certain templates had artworks from different product on it.  This was a nightmare when orders were placed etc.
// This script is kind of a hack.  It will convert the artworks to the proper IDs.  
// You have to manually do searches in the DB to find the mixed up ID's and then paste them in here.


// You may do a search for something like ...
// SELECT ID FROM artworksearchengine WHERE ProductID=xxx AND ArtFile like "%contentwidth>576%";

// That will find a ProductID's with a content with mismatched.... (that of a PostCard).


$viewType = "template_searchengine";
$convertFromProductID = "xxxx";
$convertToProductID = "xxxx";


/*

$templateIDarr = array(
"6137",
"6263",
"6264",
"6266",
"6268",
"6269",
"6270",
"6349",
"7426");

*/


foreach($templateIDarr as $thisTemplateID){

	$originalArt = ArtworkLib::GetArtXMLfile($dbCmd, $viewType, $thisTemplateID);


	// Use the Artwork Conversion Class to Transfer the Layers from our Source to our Target.
	$artworkConversionObj = new ArtworkConversion($dbCmd);
	

	// A lot of times we want to remove the Backside during an Artwork conversion... don't do that here.
	$artworkConversionObj->removeBacksideFlag(false);

	

	$artworkConversionObj->setFromArtwork($convertFromProductID, $originalArt);
	$artworkConversionObj->setToArtwork($convertToProductID);
	
	$convertedArtXML = $artworkConversionObj->getConvertedArtwork();

	ArtworkLib::SaveArtXMLfile($dbCmd, $viewType, $thisTemplateID, $convertedArtXML);
	
	print "Finished Converting Template ID:" . $thisTemplateID . "<br>";
	
}



?>	
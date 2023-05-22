<?

require_once("library/Boot_Session.php");

// Make this script be able to run for a while
set_time_limit(1000);




$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();


$projectIDsArr = array();

$vectorImagesHash = array();

$dbCmd->Query("SELECT ID, ProductID FROM projectsordered WHERE Status = 'P' || Status = 'Q' || Status = 'H'");
while($row = $dbCmd->GetRow()){

	$projectID = $row["ID"];
	$ProductID = $row["ProductID"];

	//print "Checking projectID: $projectID <br>";
	
	// Depreciate Old Products
	if(!Product::checkIfProductIDisActive($dbCmd2, $ProductID))
		continue;
	
	$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd2, "ordered", $projectID);
	
	
	$ArtworkInfoObj = new ArtworkInformation($projectObj->getArtworkFile());
	
	
	
	for($SideNumber = 0; $SideNumber < sizeof($ArtworkInfoObj->SideItemsArray); $SideNumber++){
	
		//$ArtworkInfoObj->orderLayersByLayerLevel($SideNumber);
		$LayersSorted = $ArtworkInfoObj->SideItemsArray[$SideNumber]->layers;


		// If there are Vector Images in the Artwork... they should be drawn on top of the background image.
		// Stretch and Tile bleed types have no effect on Vector Images.
		for($j=0; $j<sizeof($LayersSorted); $j++){

			$LayerObj = $LayersSorted[$j];

			if($LayerObj->LayerType == "graphic" && !empty($LayerObj->LayerDetailsObj->vector_image_id)){
				$projectIDsArr[] = $projectID;
				
				if(!isset($vectorImagesHash[$projectID]))
					$vectorImagesHash[$projectID] = array();
					
				$vectorImagesHash[$projectID][] = $LayerObj->LayerDetailsObj->vector_image_id;
				
				continue(2);
			}
		}
	}
}


$projectIDsArr = array_unique($projectIDsArr);

foreach($projectIDsArr as $thisProjectID){
	
	$vectorImageList = "";
	
	foreach($vectorImagesHash[$thisProjectID] as $thisVectorImageID){
		$vectorImageList .= " - " . $thisVectorImageID;
	}

	print "<a href='./ad_proof_artwork.php?projectid=$thisProjectID' target='top'><b>A" . $thisProjectID  . "</b></a> VectorImageID(s): $vectorImageList <br><br>";


}







?>
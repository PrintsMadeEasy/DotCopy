<?

require_once("library/Boot_Session.php");



$start_timestamp = mktime (0,0,0, 1, 1, 2007);
$end_timestamp = mktime (0,0,0, 1, 2, 2007);




$dbCmd = new DbCmd();

// Make this script be able to run for a while
set_time_limit(4000);


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("MARKETING_REPORT"))
	WebUtil::PrintAdminError("Not Available");
	

$domainObj = Domain::oneDomain();


// Type in a bit of layer text that you want to match... it will clone that layer object over to other artworks in the saved projects.
$layerTextToCopyOver = "CompanySearch";
$layerSideToCopy = 1;


$sourceArtworkObj = new ArtworkInformation(ArtworkLib::GetArtXMLfile($dbCmd, "saved", 476275));
$backSideObj = $sourceArtworkObj->SideItemsArray[$layerSideToCopy];
$sourceLayerObj = null;

foreach($backSideObj->layers as $thisLayerObj){
	if($thisLayerObj->LayerType == "text"){
		if(preg_match("/".preg_quote($layerTextToCopyOver)."/", $thisLayerObj->LayerDetailsObj->message)){
			$sourceLayerObj = $sourceArtworkObj->GetLayerObject(1, $thisLayerObj->level);
			break;
		}
	}
}

//var_dump($sourceLayerObj);

if(!empty($sourceLayerObj)){

	$dbCmd->Query("SELECT ID FROM projectssaved WHERE Notes LIKE '%Postcard%' AND UserID=6");
	$savedProjectsID = $dbCmd->GetValueArr();
	
	print "Saved Projects to Copy On To: " . sizeof($savedProjectsID) . "<hr>";
	
	foreach($savedProjectsID as $thisSavedProjectID){
	
		//if($thisSavedProjectID != 919373)
		//	continue;
			
		$projectObj = ProjectBase::getProjectObjectByViewType($dbCmd, "saved", $thisSavedProjectID);
		$destArtworkObj = new ArtworkInformation($projectObj->getArtworkFile());
	
		
		$alreadyFound = false;
		// Keep us from adding it twice.
		foreach($destArtworkObj->SideItemsArray[$layerSideToCopy]->layers as $thisTestLayerObj){
			if($thisTestLayerObj->LayerType == "text"){
				if(preg_match("/".preg_quote($layerTextToCopyOver)."/", $thisTestLayerObj->LayerDetailsObj->message)){
					$alreadyFound = true;
					continue;
				}
			}
		}
		
		if(!$alreadyFound){
			print "Adding to Project: " . $thisSavedProjectID . "<br>";
			$destArtworkObj->AddLayerObjectToSide(1, $sourceLayerObj);
			$projectObj->setArtworkFile($destArtworkObj->GetXMLdoc());
			$projectObj->updateDatabaseWithRawData();
		}
		
		
	}
}

print "done";



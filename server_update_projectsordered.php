<?

require_once("library/Boot_Session.php");



$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();
$dbCmd3 = new DbCmd();

// Make this script be able to run for 15 mintues if needed 
set_time_limit(900);


// We don't want the administrator to wait for the Thumnails to update when they are making "saves".
// So every few mintues we update thumnails through the cron

// Do a join so that if the Saved Project is deleted it will not try to create a thumbnail

$dbCmd->Query("SELECT DISTINCT PSNU.ProjectSavedID FROM projectsavedneedsupdate AS PSNU INNER JOIN projectssaved on PSNU.ProjectSavedID = projectssaved.ID ");
$savedProjectIDarr = array();

while($ThisProjectID = $dbCmd->GetValue()){
	$savedProjectIDarr[] = $ThisProjectID;
}


foreach($savedProjectIDarr as $thisSavedProjectID){

	print $thisSavedProjectID . "<br>";
	Constants::FlushBufferOutput();


	// In case the server gets overloaded... you could have multiple cron jobs attacking the same project list (because they are stored in an array before processing).
	$dbCmd->Query("SELECT COUNT(*) FROM projectsavedneedsupdate WHERE ProjectSavedID=" . $thisSavedProjectID);
	$countOfProjects = $dbCmd->GetValue();
	if($countOfProjects == 0)
		continue;
	
	// Delete the entry before the Thumbnail is actually created.  That way.. if there is an error of some kind... it won't hang up everything that follows.
	$dbCmd->Query("DELETE FROM projectsavedneedsupdate WHERE ProjectSavedID=" . $thisSavedProjectID);

	ThumbImages::CreateThumnailImage($dbCmd2, $thisSavedProjectID, "saved");
	
	print "Saved Project Updated: " . $thisSavedProjectID . "<br>";
	Constants::FlushBufferOutput();
}





$dbCmd->Query("SELECT DISTINCT ProjectOrderedID FROM projectorderedartworkupdate ORDER BY ID ASC");
$projectIDarr = array();

while($ThisProjectID = $dbCmd->GetValue())
	$projectIDarr[] = $ThisProjectID;

$orderIDarr = array();


foreach($projectIDarr as $thisProjectOrderedID){

	$startTime = microtime(true);

	
	// In case the server gets overloaded... you could have multiple cron jobs attacking the same project list (because they are stored in an array before processing).
	$dbCmd->Query("SELECT COUNT(*) FROM projectorderedartworkupdate WHERE ProjectOrderedID=" . $thisProjectOrderedID);
	$countOfProjects = $dbCmd->GetValue();
	if($countOfProjects == 0)
		continue;
		
		
	// Delete the entry before the image is actually created.  That way.. if there is an error of some kind... it won't hang up everything that follows.... and will help in the case of a server overload.
	$dbCmd->Query("DELETE FROM projectorderedartworkupdate WHERE ProjectOrderedID=" . $thisProjectOrderedID);
	
	// Make a unique list of order numbers
	$dbCmd->Query("SELECT OrderID FROM projectsordered WHERE ID=" . $thisProjectOrderedID);
	$orderNo = $dbCmd->GetValue();
	if(!in_array($orderNo, $orderIDarr))
		$orderIDarr[] = $orderNo;
		
	$artworkPreviewObj = new ArtworkPreview($dbCmd2);
	
	
	// If the product name has "custom" on it... then make the artwork preview larger because it is probably a form.
	$productIDofOrder = ProjectBase::GetProductIDFromProjectRecord($dbCmd2, $thisProjectOrderedID, "ordered");
	$productName = Product::getFullProductName($dbCmd2, $productIDofOrder);
	
	if(preg_match("/custom/i", $productName)){
		$artworkPreviewObj->setMaxImageHeight(1000);
		$artworkPreviewObj->setMinImageWidth(750);
	}
	
	$artworkPreviewObj->updateArtworkPreview($thisProjectOrderedID, "ordered");
	
	print "Project Ordered Preview Updated: " . $thisProjectOrderedID . "<br>";
	Constants::FlushBufferOutput();
	
	$endTime = microtime(true);
	
	$projectDuration = round($endTime - $startTime, 1);
	
	if($projectDuration > 35){
		// Let the webmaster know if a preview image takes too long to generate.
		WebUtil::WebmasterError("P" . $thisProjectOrderedID . " took $projectDuration seconds to generate preview images.", "Big Preview Image");	
	}
	
	sleep(1);
}




// Possibly charge customers for Copyright Templates
foreach($orderIDarr as $thisOrderNumber){

	$copyrightChargesObj = new CopyrightCharges($dbCmd, $thisOrderNumber);

	
	if($copyrightChargesObj->orderShouldBeChargedForCopyright()){
		$emailNotify = "Order ID: $thisOrderNumber :: will be charged for Copyrights on Template.";
		$subjectYes = " - Yes";
	}
	else{
		$emailNotify = "Order ID: $thisOrderNumber :: will NOT be charged.";
		$subjectYes = "";
	}	
	
	//WebUtil::SendEmail("Copyright Notify", Constants::GetMasterServerEmailAddress(), "", Constants::GetAdminEmail(), ("Copyright Templates" . $subjectYes), $emailNotify, true);
	

	$copyrightChargesObj->possiblyChargeAndSendEmail();


}



print "done";


?>
<?

require_once("library/Boot_Session.php");

$dbCmd = new DbCmd();

set_time_limit(50000);

exit("must override");

// ---------------------------------   Done with Manual Search and Replaces ------------------------------------
$dbCmd->Query("SELECT DISTINCT Name FROM bannerlog WHERE Name LIKE '%pc_savethedate'");
$fixTheseTrackingCodesArr = $dbCmd->GetValueArr();


foreach($fixTheseTrackingCodesArr as $thisCodeToFix){
	
	$tableStr = "<table width='1000'>";
	
	$newTrackingCode = preg_replace("/pc_savethedate/", "std_general", $thisCodeToFix);
	$dbCmd->UpdateQuery("bannerlog", array("Name"=>$newTrackingCode), "Name LIKE '" . DbCmd::EscapeSQL($thisCodeToFix) . "'");
	$dbCmd->UpdateQuery("orders", array("Referral"=>$newTrackingCode), "Referral LIKE '" . DbCmd::EscapeSQL($thisCodeToFix) . "'");
	//usleep(40000);
	
	$tableStr .= "<tr><td width='500'>" . $thisCodeToFix . "</td><td>" . $newTrackingCode . "</td></tr>\n";
	
	$tableStr .= "</table>";
	print $tableStr;
	flush();
}

print "<hr>Done Updating Local Database<hr>";




$domainID = 1;

$debug = true;

$adGroupServiceObj = new AdWordsAdGroupService();
$criterionServiceObj = new AdWordsCriterionService();
$campaignServiceObj = new AdWordsCampaignService();


$clientLogin = WebUtil::GetInput("clientLogin", FILTER_SANITIZE_EMAIL);
if(empty($clientLogin))
	exit("Forget a client login.");

$campaignServiceObj->setClientEmail($clientLogin);
$adGroupServiceObj->setClientEmail($clientLogin);
$criterionServiceObj->setClientEmail($clientLogin);


$campaignObjArr = $campaignServiceObj->getActiveAdWordsCampaigns();

foreach($campaignObjArr as $thisCampaignObj){
	
	$campaignID = $thisCampaignObj->id;
	
	//if(!preg_match("/francisco/i", $thisCampaignObj->name))
	//	continue;
	
	print "<br><br><u>Campaign Name: " . htmlspecialchars($thisCampaignObj->name) . "</u><br>";
	
	$adsGroupsArr = $adGroupServiceObj->getActiveAdGroups($campaignID);
	
	
	// Get all criterion ID's so we can get all of the stats in a Single API call.
	$adGroupIdsArr = array();
	foreach($adsGroupsArr as $thisAdGroupObj)
		$adGroupIdsArr[] = $thisAdGroupObj->id;
	
	
	//$adGroupStatsArr = $adGroupServiceObj->getAdGroupStats($campaignID, $adGroupIdsArr, $startTimeStamp, $endTimeStamp);
	
	
	$adGroupCounter = 0;
	foreach($adsGroupsArr as $thisAdGroupObj){
		
		
		if(!preg_match("/Save\s*The\s*Date/i", $thisAdGroupObj->name))
			continue;
		
		//if($thisAdGroupObj->name != "(Broad) PC-General")
		//	continue;
		
		//if($thisAdGroupObj->id != 1222493324)
		//	continue;
		
		$adGroupCounter++;
		
		
		print "AdGroup: " .$thisAdGroupObj->name . "<br>";
		
		
		
		$criteriaObjArr = $criterionServiceObj->getAllCriteria($thisAdGroupObj->id);
		
		// Get all criterion ID's so we can get all of the stats in a Single API call.
		$criteriaIDsArr = array();
		
		$updateCriteraObjArr = array();
		
		foreach($criteriaObjArr as $criteriaObj){
			
			// The Adwords API will fail if you are requesting stats on negative keyword
			if($criteriaObj->paused == "true" || $criteriaObj->negative == "true" ){
				continue;
			}

			if(preg_match("/pc_savethedate/i", $criteriaObj->destinationUrl) || preg_match("/dest=SaveTheDate/i", $criteriaObj->destinationUrl)){
				
				print "Need to update Tracking Code: " . htmlspecialchars($criteriaObj->destinationUrl) . "<br>";
				$newDestURL = preg_replace("/pc_savethedate/i", "std_general", $criteriaObj->destinationUrl);
				$newDestURL = preg_replace("/dest=SaveTheDate/i", "dest=Save-The-Date-Postcards", $newDestURL);
				
				print "New URL: " . htmlspecialchars($newDestURL) . "<br><br>";
				
				$criteriaObj->destinationUrl = $newDestURL;
				
				$updateCriteraObjArr[] = $criteriaObj;
			}
		}
		
		
		var_dump($updateCriteraObjArr);
		
		$criterionServiceObj->updateCriteria($updateCriteraObjArr);
		
		
		
	}
	
}

print "<hr>All Done<hr>";
		





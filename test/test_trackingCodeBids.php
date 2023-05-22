<?

require_once("library/Boot_Session.php");

$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$dbCmd = new DbCmd();

set_time_limit(50000);


$startTimeStamp = time() - (60 * 60 * 24 * 16);
$endTimeStamp = time() - (60 * 60 * 24 * 8);

$minimumShortTermClicksToMeasureConversion = 80;

$domainID = 1;

$cpcObj = new CostPerClickEstimate($domainID);
$cpcObj->setBreakEvenDayCount(300);
$cpcObj->setMinimumOrderShortTerm(5);
$cpcObj->setMinimumOrderMidTerm(20);
$cpcObj->setMinimumOrderLongTerm(40);
$cpcObj->setMinimumClicksToMeasureConversions($minimumShortTermClicksToMeasureConversion);

$cpcObj->setDateRangeShortTerm($startTimeStamp, $endTimeStamp);
$cpcObj->setCustomerWorthEndDays_Short(7);

$cpcObj->setDateRangeMidTerm((time() - (60 * 60 * 24 * 100)), (time() - (60 * 60 * 24 * 16)));
$cpcObj->setCustomerWorthEndDays_Mid(15);

$cpcObj->setDateRangeLongTerm((time() - (60 * 60 * 24 * 450)), (time() - (60 * 60 * 24 * 301)));
$cpcObj->setNomadPercentage(0.35);

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


// Figure out the default CPC bid for the AdGroup depending on the highest CPC for the Criteria.
$adGroupDefaultPefectCpcBid = 5.00; // In case we have no clicks... this will be our default.
$highestPerfectCpcTargetForCriteria = 0;
	

//$campaignObjArr = $campaignServiceObj->getCampaignList(array(35476214));
$campaignObjArr = $campaignServiceObj->getActiveAdWordsCampaigns();


foreach($campaignObjArr as $thisCampaignObj){
	
	$campaignID = $thisCampaignObj->id;
	
	//if($thisCampaignObj->name != "Hawaii")
	//	continue;
	
	print "<br><br><u>Campaign Name: " . htmlspecialchars($thisCampaignObj->name) . "</u><br>";
	
	$adsGroupsArr = $adGroupServiceObj->getActiveAdGroups($campaignID);
	
	
	// Get all criterion ID's so we can get all of the stats in a Single API call.
	$adGroupIdsArr = array();
	foreach($adsGroupsArr as $thisAdGroupObj)
		$adGroupIdsArr[] = $thisAdGroupObj->id;
	
	
	$adGroupStatsArr = $adGroupServiceObj->getAdGroupStats($campaignID, $adGroupIdsArr, $startTimeStamp, $endTimeStamp);
	
	
	$adGroupCounter = 0;
	foreach($adsGroupsArr as $thisAdGroupObj){
		
		//if($thisAdGroupObj->name != "(Broad) PC-General")
		//	continue;
		
		//if($thisAdGroupObj->id != 1222493324)
		//	continue;
		
		$adGroupCounter++;
		
		//if($adGroupCounter > 40)
		//	continue;
		
		
		if($debug)
			print "Campaign: " . htmlspecialchars($thisCampaignObj->name) . " AdGroup: <b><u>" . htmlspecialchars($thisAdGroupObj->name) . "</u></b><br>";
			
		$isExactMatchAdGroup = false;
		$isPhraseMatchAdGroup = false;
		$isBroadMatchAdGroup = false;
		
		if(preg_match("/\(Exact\)/i", $thisAdGroupObj->name)){
			$isExactMatchAdGroup = true;
			//print "<b>Exact Match Ad Group</b><br>";
		}
		else if(preg_match("/\(Phrase\)/i", $thisAdGroupObj->name)){
			$isPhraseMatchAdGroup = true;
			//print "<b>Phrase Match Ad Group</b><br>";
		}
		else if(preg_match("/\(Broad\)/i", $thisAdGroupObj->name)){
			$isBroadMatchAdGroup = true;
			//print "<b>Broad Match Ad Group</b><br>";
		}
		
		
		
		//if(!$isBroadMatchAdGroup)
		//	continue;
			
		
		$updateCriteraObjArr = array();
		
		$dbCmd->Query("SELECT COUNT(*) FROM googleapiupdates WHERE AdGroupID=" . intval($thisAdGroupObj->id) . " AND Date > DATE_ADD(NOW(), INTERVAL -5 DAY) ");
		if($dbCmd->GetValue() > 0){
			print "Not updating Bid because AdGroup ID ". intval($thisAdGroupObj->id) . " has been updated within the past 5 days.<br>";
			continue;
		}
		
		
		
		$criteriaObjArr = $criterionServiceObj->getAllCriteria($thisAdGroupObj->id);
		
		// Get all criterion ID's so we can get all of the stats in a Single API call.
		$criteriaIDsArr = array();
		
		foreach($criteriaObjArr as $criteriaObj){
			
			// The Adwords API will fail if you are requesting stats on negative keyword
			if($criteriaObj->paused == "true" || $criteriaObj->negative == "true" ){
				continue;
			}
				
			$criteriaIDsArr[] = $criteriaObj->id;
		}
		
		// Fetch all of the stats in one request to cut down on API expenses.
		$criteriaStatsArr = $criterionServiceObj->getCriterionStats($thisAdGroupObj->id, $criteriaIDsArr, $startTimeStamp, $endTimeStamp);
	
	
		
		foreach($criteriaObjArr as $criteriaObj){
			
			if($criteriaObj->paused == "true" || $criteriaObj->negative == "true"){
				//print "Criteria Paused, Skipping: " . $criteriaObj->text . "<br>";
				continue;
			}
				
			// Loop through our Criteria Stats looking for the Corresponding ID of the Criteria Obj that we are on.
			$matchingCriteriaStatsObj = null;
			foreach($criteriaStatsArr as $thisCriteriaStatsObj){
				if($thisCriteriaStatsObj->id == $criteriaObj->id){
					$matchingCriteriaStatsObj = $thisCriteriaStatsObj;
					break;
				}
			}
			
			if($matchingCriteriaStatsObj == null){
				print "Errror... not Crtieria Stats Detected.<br>";
				WebUtil::WebmasterError("No Criteria Stats ID was detected.");
				continue;
			}
	
			
			$currentCostPerClickAvg = $matchingCriteriaStatsObj->getCostPerClick();
			$currentMaxCPC = $criteriaObj->maxCpc;
			
			// In case we don't have any click data... then get the Click Data for the entire AdGroup
			if($currentCostPerClickAvg < 0.01){
				foreach($adGroupStatsArr as $thisAdGroupStatsObj){
					if($thisAdGroupStatsObj->id == $thisAdGroupObj->id){
						$currentCostPerClickAvg = $thisAdGroupStatsObj->getCostPerClick();
						break;
					}
				}
			}
			// In case we don't have a CPC on the keyword... get the CPC from the AdGroup
			if($currentMaxCPC < 0.01){
				$currentMaxCPC = $thisAdGroupObj->keywordMaxCpc;
			}
			
			if($debug){
				print "Keyword: <u>" . $criteriaObj->text . "</u> : ";
				if($criteriaObj->type == "Exact")
					print "<font color='#336699'>[Exact]</font>";
				else if($criteriaObj->type == "Phrase")
					print "<font color='#CC3366'>&quot;Phrase&quot;</font>";
				else if($criteriaObj->type == "Broad")
					print "<font color='#66CC33'><i>Broad</i></font>";
				else 
					print $criteriaObj->type;
					
				print "<br>";
				print "Current Max CPC Bid: <font color='#990000'>\$" . number_format($currentMaxCPC, 2) . "</font> Last CPC Average: \$" .  number_format($currentCostPerClickAvg, 2) . " Total clicks: " . $matchingCriteriaStatsObj->clicks . "<br>";
				flush();
			}
			
			// Get the Tracking Code out of the Destination URL.
			$matches = array();
			$trackingCode = null;
			if(preg_match("/from=(g(\w|\-|\d)+)/i", $criteriaObj->destinationUrl, $matches)){
				$trackingCode = $matches[1];
			}
			
			if(empty($trackingCode)){
				print "Error No Tracking Code Found from URL : " . WebUtil::htmlOutput($criteriaObj->destinationUrl) . "<br>";
				WebUtil::WebmasterError("No Tracking Code was Found on Campaign: $campaignID AdGroup: " . $thisAdGroupObj->id . " Criteria: " . $criteriaObj->text . " : " . $criteriaObj->type . ":DestURL - " . $criteriaObj->destinationUrl);
				continue;
			}
			
			
			$cpcObj->setTrackingCode($trackingCode);
			$newCpcBid = $cpcObj->getCostPerClickRecommendation($currentMaxCPC, $currentCostPerClickAvg);
			
			
			$criteriaObj->maxCpc = $newCpcBid;
			
			$updateCriteraObjArr[] = $criteriaObj;
			
			if($debug){
print "--- $trackingCode ----<br>";
				$widenedTrackingCodeForConversionRate = $cpcObj->getWidenedTrackingCodeByBannerClicks($trackingCode, $minimumShortTermClicksToMeasureConversion, $startTimeStamp, $endTimeStamp);		
				if(empty($widenedTrackingCodeForConversionRate)){
					$widenedTrackingCodeForConversionRate = "Not-Enough-Clicks";
					$clicksFromWidenedTrackingCode = "N/A";
				}
				else{
					$clicksFromWidenedTrackingCode = $cpcObj->getBannerClicksFromTrackingCodeSearch($widenedTrackingCodeForConversionRate, $startTimeStamp, $endTimeStamp);
				}
				
				print "Widened Code For Conversion Rate: ( " . WebUtil::htmlOutput($widenedTrackingCodeForConversionRate) . " ) clicks: $clicksFromWidenedTrackingCode <br>";
				print "New Cpc Bid: <font color='#009900'>\$" . number_format($newCpcBid, 2) . "</font><br>";
				print "<br>";
				flush();
			}
			
			$newPerfectCpcBid = $cpcObj->getCostPerClickEstimatePerfect();
			
			if($newPerfectCpcBid > $highestPerfectCpcTargetForCriteria)
				$highestPerfectCpcTargetForCriteria = $newPerfectCpcBid;
			
		}
		
		if($debug)
			print "<br>";
		
		if(empty($highestPerfectCpcTargetForCriteria)){
			if($debug)
				print "Ad Group will use default bid of \$" . $adGroupDefaultPefectCpcBid . " because there is no Keyword Data<br>";
		}
		else{
			$adGroupDefaultPefectCpcBid = $highestPerfectCpcTargetForCriteria;
		}
		
		// Loop through our AdGroup Stats looking for the Corresponding ID of the AdGroup Obj that we are on.
		$matchingAdGroupStatsObj = null;
		foreach($adGroupStatsArr as $thisAdGroupStatsObj){
			if($thisAdGroupStatsObj->id == $thisAdGroupObj->id){
				$matchingAdGroupStatsObj = $thisAdGroupStatsObj;
				break;
			}
		}
		
		if($matchingAdGroupStatsObj == null){
			WebUtil::WebmasterError("No AdWords Stats ID was detected on AdGroup ID: " . $thisAdGroupObj->id);
			continue;
		}
		
	
		$adGroupDefaultBidRecommended = $cpcObj->getCostPerClickRecommendation($thisAdGroupObj->keywordMaxCpc, $matchingAdGroupStatsObj->getCostPerClick(), $adGroupDefaultPefectCpcBid);
		
		if($debug){
			print "Updating Default Bid on AdGroup to \$" . $adGroupDefaultBidRecommended . " using a Perfect CPC Bid of: \$" . $adGroupDefaultPefectCpcBid . "<br>";
			print "<hr><br><br>";
			
			flush();
		}
		
		
		// Update all of the Bid Prices for the Criteria Objects belonging to this adGroup.
		$criterionServiceObj->updateCriteria($updateCriteraObjArr);
		
		// Update the default Max CPC for the entire AdGroup
		$thisAdGroupObj->keywordMaxCpc = $adGroupDefaultPefectCpcBid;
		$adGroupServiceObj->updateAdGroupList(array($thisAdGroupObj));
		
		
		// Make sure that we don't update the same AdGroup Pricing too close to each other or it could cause the Max / Avg spread to widen.
		$dbCmd->InsertQuery("googleapiupdates", array("AdGroupID"=>$thisAdGroupObj->id, "Date"=>time()));
	}
}


$trackingLog = $cpcObj->getTrackingCodeLog(true);
$domainEmailConfigObj = new DomainEmails($domainID);
WebUtil::SendEmail($domainEmailConfigObj->getEmailNameOfType(DomainEmails::WEBMASTER), $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::WEBMASTER), $domainEmailConfigObj->getEmailNameOfType(DomainEmails::WEBMASTER), $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::WEBMASTER), "Tracking Code Log", $trackingLog, true);
if($debug){
	print $trackingLog;
	print "\n\n\n\n\n\n\n<br><br><hr>Done";
}







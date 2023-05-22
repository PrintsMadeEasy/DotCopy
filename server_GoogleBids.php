<?

require_once("library/Boot_Session.php");

$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$dbCmd = new DbCmd();

set_time_limit(500000);


$startTimeStamp = time() - (60 * 60 * 18); // Go for 5 hours in the past.
$endTimeStamp = time() - (60 * 60 * 18);


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



//$campaignObjArr = $campaignServiceObj->getCampaignList(array(1768675763));
$campaignObjArr = $campaignServiceObj->getActiveAdWordsCampaigns();


foreach($campaignObjArr as $thisCampaignObj){
	
	$campaignID = $thisCampaignObj->id;
	
	//if(!preg_match("/York/i", $thisCampaignObj->name))
	//	continue;
	
	print "<br><br><font color='#aa0000'><b><u>Campaign Name: " . htmlspecialchars($thisCampaignObj->name) . "</u></b></font>, Campaign ID: $campaignID<br>";
	
	$adsGroupsArr = $adGroupServiceObj->getActiveAdGroups($campaignID);
	
	
	// Get all criterion ID's so we can get all of the stats in a Single API call.
	$adGroupIdsArr = array();
	foreach($adsGroupsArr as $thisAdGroupObj)
		$adGroupIdsArr[] = $thisAdGroupObj->id;
	
	
	
	
	$adGroupCounter = 0;
	foreach($adsGroupsArr as $thisAdGroupObj){
		
		//if($thisAdGroupObj->name != "(Broad) PC-General")
		//	continue;
		
		//if($thisAdGroupObj->id != 1222493324)
		//	continue;
		
		$adGroupCounter++;
		
		/*
		if($adGroupCounter > 40)
			continue;
		*/
		
		if($debug)
			print "<br>AdGroup: <b>" . htmlspecialchars($thisAdGroupObj->name) . "</b><br>\n";
			

		if(!preg_match("/^(\\(Exact\\)\\s)?BC-/i", $thisAdGroupObj->name) && !preg_match("/^(\\(Exact\\)\\s)?PC-/i", $thisAdGroupObj->name)){
			if($debug)
				print "<h3>This Ad Group is not included for Bid Adjustments: " . htmlspecialchars($thisAdGroupObj->name) . "</h3></br>";
			continue;
		}

		
		$updateCriteraObjArr = array();


		$dbCmd->Query("SELECT COUNT(*) FROM googleapiupdates WHERE AdGroupID=" . intval($thisAdGroupObj->id) . " AND Date > DATE_ADD(NOW(), INTERVAL -10 HOUR) ");
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
			
// Only bidding one Exact Match for now.
			if($criteriaObj->type != "Exact")
				continue;
				
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
			$averagePosition = round($matchingCriteriaStatsObj->averagePosition, 2);
			
			/*
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
			*/

			
			// Initialize a variable for Default CPC bid.
			$newCpcBid = "3.00";
			
			$defaultBidTaken = true;
			
			// Treat ads with Zero impressions special.
			if($matchingCriteriaStatsObj->impressions == 0){
				
				if(preg_match("/^business\scards$/", $criteriaObj->text)){
					$newCpcBid = "3.60";
				}
				else{
					if($criteriaObj->type == "Phrase") 
						$newCpcBid = "2.50";
					else 
						$newCpcBid = "2.90";
				}
				
			}
			else{
			
				$defaultBidTaken = false;
				
				// The ideal position in Adwords that we are shooting for.
				$idealAveragePosition = 4;
				
				// This value will be positive if our average position is lower than the ideal position... and negative if our Avg. Position is too high.
				$distanceToIdealPosition = $averagePosition - $idealAveragePosition;
				
				$absoluteDistanceToIdealSpot = abs($distanceToIdealPosition);
			
				$percentChangeToMaxCPCbid = 0;
				
				if($absoluteDistanceToIdealSpot < 0.1)
					$percentChangeToMaxCPCbid = 0;
				else if($absoluteDistanceToIdealSpot < 0.2)
					$percentChangeToMaxCPCbid = 0.01;
				else if($absoluteDistanceToIdealSpot < 0.3)
					$percentChangeToMaxCPCbid = 0.02;
				else if($absoluteDistanceToIdealSpot < 0.4)
					$percentChangeToMaxCPCbid = 0.03;
				else if($absoluteDistanceToIdealSpot < 0.5)
					$percentChangeToMaxCPCbid = 0.04;
				else if($absoluteDistanceToIdealSpot < 0.6)
					$percentChangeToMaxCPCbid = 0.05;
				else if($absoluteDistanceToIdealSpot < 0.7)
					$percentChangeToMaxCPCbid = 0.06;
				else if($absoluteDistanceToIdealSpot < 0.8)
					$percentChangeToMaxCPCbid = 0.07;
				else if($absoluteDistanceToIdealSpot < 1)
					$percentChangeToMaxCPCbid = 0.08;
				else
					$percentChangeToMaxCPCbid = 0.09;
					
				if($distanceToIdealPosition < 0)
					$negativeOrPositiveChange = -1;
				else
					$negativeOrPositiveChange = 1;
			

				// If we are moving down... we want to reduce the weight by 50%.
				// We want to me more agressive with rising... and less agressive with falling.
				if($distanceToIdealPosition < 0)
					$percentChangeToMaxCPCbid *= 0.5;

				
	//$percentChangeToMaxCPCbid = 0.15;
	//$negativeOrPositiveChange = -1;
										
					
				$newCpcBid = $currentMaxCPC + ($percentChangeToMaxCPCbid * $currentMaxCPC * $negativeOrPositiveChange);
				$newCpcBid = round($newCpcBid, 2);
				
			}
	
			
			$changeInCpcBid = $newCpcBid - $currentMaxCPC;
			
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
			}
			
			
			$changeBidPrice = true;
			
			
			
			// Don't make CPC changes under 1 nickel.
			if(abs($changeInCpcBid) < 0.04){
				
				$changeBidPrice = false;
				
				if($debug){
					print "<br><font color='#0000CC'><b>Skipping (it's close enough)...</b></font>";
				}
			}
			
			
			if($changeInCpcBid < -0.051 && $matchingCriteriaStatsObj->impressions == 0){
				
				$changeBidPrice = false;
				
				if($debug){
					print "<br><font color='#00aa00'><b>Bid Price is already high with low impressions, skipping...</b></font>";
				}
			}
			
			if($debug){
					
				print "<br>";
				print "Current Max CPC: <font color='#990000'>\$" . number_format($currentMaxCPC, 2) . "</font> Last CPC Average: \$" .  number_format($currentCostPerClickAvg, 2) . " Total clicks: " . $matchingCriteriaStatsObj->clicks . " Impressions: " . $matchingCriteriaStatsObj->impressions . " Avg. Pos: $averagePosition New Max CPC: <font color='#000CC0'><b>\$" .  number_format($newCpcBid, 2) . "</b></font>";
				
				if(!$changeBidPrice && $defaultBidTaken)
					print "&nbsp;&nbsp;<font color='#331199'>(Bid is OK)</font>";
				else if($defaultBidTaken)
					print "&nbsp;&nbsp;<font color='#996600'>(Default Bid Used)</font>";
				else if(!$changeBidPrice)
					print "&nbsp;&nbsp;<font color='#aa0099'>(skipped)</font>"; 
				else 
					print "&nbsp;&nbsp;<font color='#008833'>(Bid Adjusted)</font>";
					
				print "<br>";
				
				flush();
			}
			
			
			// Don't change the Bid price unless the flag is set.
			if(!$changeBidPrice)
				continue;
						
			$criteriaObj->maxCpc = $newCpcBid;
			
			$updateCriteraObjArr[] = $criteriaObj;
		

			
		}

		
		// Update all of the Bid Prices for the Criteria Objects belonging to this adGroup.
		$criterionServiceObj->updateCriteria($updateCriteraObjArr);
		

		// Make sure that we don't update the same AdGroup Pricing too close to each other or it could cause the Max / Avg spread to widen.
		$dbCmd->InsertQuery("googleapiupdates", array("AdGroupID"=>$thisAdGroupObj->id, "Date"=>time()));
	}
}

/*
$domainEmailConfigObj = new DomainEmails($domainID);
WebUtil::SendEmail($domainEmailConfigObj->getEmailNameOfType(DomainEmails::WEBMASTER), $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::WEBMASTER), $domainEmailConfigObj->getEmailNameOfType(DomainEmails::WEBMASTER), $domainEmailConfigObj->getEmailAddressOfType(DomainEmails::WEBMASTER), "Tracking Code Log", $trackingLog, true);
*/

if($debug){
	print "\n\n\n\n\n\n\n<br><br><hr>Done";
}







<?php
require_once("library/Boot_Session.php");

set_time_limit(50000);

$startTimeStamp = time() - (60 * 60 * 24 * 8);
$endTimeStamp = time() - (60 * 60 * 24 * 1);

$campaignServiceObj = new AdWordsCampaignService();
$adGroupServiceObj = new AdWordsAdGroupService();
$criterionServiceObj = new AdWordsCriterionService();
$textAdsServiceObj = new AdWordsAdService();
$skipcampaign = WebUtil::GetInput("skipcampaign", FILTER_SANITIZE_INT);
$clientLogin = WebUtil::GetInput("clientLogin", FILTER_SANITIZE_EMAIL);
if(empty($clientLogin))
	exit("Forget a client login.");

$campaignServiceObj->setClientEmail($clientLogin);
$adGroupServiceObj->setClientEmail($clientLogin);
$criterionServiceObj->setClientEmail($clientLogin);
$textAdsServiceObj->setClientEmail($clientLogin);

$campaignsArr = $campaignServiceObj->getActiveAdWordsCampaigns();

$allExactMatchKeywordsArr = array();
$allPhraseMatchKeywordsArr = array();

$firstCampaign = true;

for($i=0; $i<sizeof($campaignsArr); $i++){
	
	if($i < $skipcampaign)
		continue;
		
	//if($i > 3)
	//	continue;
	
	$campaignID = $campaignsArr[$i]->id;
	
	// Get rid of the "New Campign Prefix"
	if(preg_match("/^New\sCampaign\-/", $campaignsArr[$i]->name)){
		
		$newCampaignName = preg_replace("/^New\sCampaign\-/", "", $campaignsArr[$i]->name);
		print $newCampaignName . "<br>";
		
		$campaignsArr[$i]->name = $newCampaignName;
		
		$campaignServiceObj->updateCampaignList(array($campaignsArr[$i]));
		
		continue;
	}
	
	//if($campaignsArr[$i]->name != "CA, Los Angeles")
	//	continue;
	
	print "<b><u>" . $campaignsArr[$i]->name . " (" . ($i+1) . " of " . sizeof($campaignsArr) . ")</u></b><br>";
	
	$isCityCampign = false;
	$isStateCampaign = false;
	$isCountryCampaign = false;
		
	if(preg_match("/Country Campaign/i", $campaignsArr[$i]->name)){
		$isCountryCampaign = true;
		print "<b>Country Campaign</b><br>";
	}
	else if(preg_match("/\w+,/i", $campaignsArr[$i]->name)){
		$isCityCampign = true;
		print "<b>City Campaign</b><br>";
	}
	else{
		$isStateCampaign = true;
		print "<b>State Campaign</b><br>";
	}

		
	
	$adGroupUpdateArr = array();
	$adGroupCreateArr = array();
	
	
	
	$activeAdGroupsArr = $adGroupServiceObj->getActiveAdGroups($campaignsArr[$i]->id);
	
	
	// Get a list of Ad Group Names.
	$adGroupsNamesArr = array();
	foreach($activeAdGroupsArr as $thisAdGroupObj){
		$adGroupsNamesArr[$thisAdGroupObj->id] = $thisAdGroupObj->name;
		
/*		
		// Build a list of Exact Match and Phrase Matches for the entire campaign.
		// We don't want to do this for every single City because they will all be the same.
		if($firstCampaign && preg_match("/\(Exact\)/", $thisAdGroupObj->name)){
			
			$exactMatchCriteriaObjArr = $criterionServiceObj->getAllCriteria($thisAdGroupObj->id);
			
			foreach($exactMatchCriteriaObjArr as $thisCriteriaObj){
				$allExactMatchKeywordsArr[] = $thisCriteriaObj->text;
			}
		}
	
		// Build a list of Exact Match and Phrase Matches for the entire campaign.
		// We don't want to do this for every single City because they will all be the same.
		if($firstCampaign && preg_match("/\(Phrase\)/", $thisAdGroupObj->name)){

			$phraseMatchCriteriaObjArr = $criterionServiceObj->getAllCriteria($thisAdGroupObj->id);

			foreach($phraseMatchCriteriaObjArr as $thisCriteriaObj){
				$allPhraseMatchKeywordsArr[] = $thisCriteriaObj->text;
			}
		}
*/
	}
	
	$firstCampaign = false;
	
	//var_dump($allExactMatchKeywordsArr);
	//var_dump($allPhraseMatchKeywordsArr);
	
	//continue;
	
	foreach($activeAdGroupsArr as $thisAdGroupObj){
		
		$criteriaUpdateArr = array();
		
	//	print $thisAdGroupObj->name . "<br>";
		
		// Any time that the "match type" is not found in the Prefix of the AdGroup name... we need to set the AdGroup to "Exact Match".
		if(!preg_match("/\(Exact\)/", $thisAdGroupObj->name) && !preg_match("/\(Phrase\)/", $thisAdGroupObj->name) && !preg_match("/\(Broad\)/", $thisAdGroupObj->name)){
			$thisAdGroupObj->name = "(Exact) " . $thisAdGroupObj->name;
			$adGroupUpdateArr[] = $thisAdGroupObj;
			continue;
		}
		

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
		
		
		
		$nameWithoutMatch = preg_replace("/^\(\w+\)\s/", "", $thisAdGroupObj->name);
		
//if($nameWithoutMatch == "BC-3-General")
	//continue;

		//if($isPhraseMatchAdGroup){
		if(false){

			// If we don't have any Criteria in an Phrase Match Group.
			// Get the criteria from the corresponding Exact Match Ad Group.
			//--------------------------------------------------------------
			$phraseMatchCriteriaObjArr = $criterionServiceObj->getAllCriteria($thisAdGroupObj->id);
			

			if(sizeof($phraseMatchCriteriaObjArr) == 0){

				$exactMatchAdGroupID = array_search("(Exact) " . $nameWithoutMatch, $adGroupsNamesArr);
				
				if($exactMatchAdGroupID){
					$exactMatchCriteriaObjArr = $criterionServiceObj->getAllCriteria($exactMatchAdGroupID);
				
					// Extract only the "Phrase Match" criteria and convert the AdGroup IDs.
					$newPhraseGroupCriteriaObjArr = array();
					
					foreach($exactMatchCriteriaObjArr as $thisExactMatchCriteriaObj){
						if($thisExactMatchCriteriaObj->type == "Phrase"){
							$thisExactMatchCriteriaObj->paused = "False";
							$thisExactMatchCriteriaObj->adGroupId = $thisAdGroupObj->id;
							
							$newPhraseGroupCriteriaObjArr[] = $thisExactMatchCriteriaObj;
						}
					}
					
					print "Adding " . sizeof($newPhraseGroupCriteriaObjArr) . " Keywords for Phrase Match<br>";
					$criterionServiceObj->addCriteria($newPhraseGroupCriteriaObjArr);
					
				}
			}
			

					
			
			// If we don't have any Ads running from a Phrase Match Group.
			// Get the Ads from the corresponding Exact Match Ad Group.
			//--------------------------------------------------------------
			$phraseTextAdsObjArr = $textAdsServiceObj->getActiveAds(array($thisAdGroupObj->id));

			if(sizeof($phraseTextAdsObjArr) == 0){

				$exactMatchAdGroupID = array_search("(Exact) " . $nameWithoutMatch, $adGroupsNamesArr);
				
				if($exactMatchAdGroupID){
					$exactMatchTextAdsObjArr = $textAdsServiceObj->getActiveAds(array($exactMatchAdGroupID));
//var_dump($exactMatchTextAdsObjArr);
					// Extract only the "Phrase Match" criteria and convert the AdGroup IDs.
					$newTextAdsObjArr = array();
					
					foreach($exactMatchTextAdsObjArr as $thisTextAdObj){

						$thisTextAdObj->adGroupId = $thisAdGroupObj->id;
						$newTextAdsObjArr[] = $thisTextAdObj;

					}
					
					print "Adding " . sizeof($newTextAdsObjArr) . " new Text Ads for Phrase Match<br>";
//var_dump($newTextAdsObjArr);
					$textAdsServiceObj->addAds($thisAdGroupObj->id, $newTextAdsObjArr);
					
				}
			}
		}
		
		/*
		$positiveKeywordsForBroadMatchArr = array();
		
		// Filter out the negative Keywords depending on the AdGroup Type
		$negativeExactMatchWords = array();
		$negativePhraseMatchWords = array();
		
		
		if($nameWithoutMatch == "BC-3-General"){
			$positiveKeywordsForBroadMatchArr = array("business card", "business cards", "business card", "businesscard", "businesscards");
			
			foreach($allExactMatchKeywordsArr as $thisExactMatchWords){
				if(preg_match("/business/i", $thisExactMatchWords))
					$negativeExactMatchWords[] = $thisExactMatchWords;
			}
			foreach($allPhraseMatchKeywordsArr as $thisPhraseMatchWords){
				if(preg_match("/business/i", $thisPhraseMatchWords))
					$negativePhraseMatchWords[] = $thisPhraseMatchWords;
			}
		}
		else if($nameWithoutMatch == "PC-General"){
			$positiveKeywordsForBroadMatchArr = array("post card", "post cards", "postcard", "postcards");
			
			foreach($allExactMatchKeywordsArr as $thisExactMatchWords){
				if(preg_match("/post/i", $thisExactMatchWords))
					$negativeExactMatchWords[] = $thisExactMatchWords;
			}
			foreach($allPhraseMatchKeywordsArr as $thisPhraseMatchWords){
				if(preg_match("/post/i", $thisPhraseMatchWords))
					$negativePhraseMatchWords[] = $thisPhraseMatchWords;
			}
		}
		else if($nameWithoutMatch == "PC-Save The Date"){
			
			
			foreach($allExactMatchKeywordsArr as $thisExactMatchWords){
				if(preg_match("/date/i", $thisExactMatchWords))
					$negativeExactMatchWords[] = $thisExactMatchWords;
			}
			foreach($allPhraseMatchKeywordsArr as $thisPhraseMatchWords){
				if(preg_match("/date/i", $thisPhraseMatchWords))
					$negativePhraseMatchWords[] = $thisPhraseMatchWords;
			}
			
			$positiveKeywordsForBroadMatchArr = array("save the date", "save-the-date", "date postcards");
		}
			
		*/

		/*
		if($isPhraseMatchAdGroup){
			
			

			$phraseMatchDestinationURL = "";
			$phraseMaxCPC = 0;
			$phraseMatchCriteriaObjArr = $criterionServiceObj->getAllCriteria($thisAdGroupObj->id);
			for($x=0; $x<sizeof($phraseMatchCriteriaObjArr); $x++){
				
				// Get the Destination URL and CPC from any of the "Positive Search Terms"... that is in common with the others that we will be using.
				if($phraseMatchCriteriaObjArr[$x]->negative == "false"){
					$phraseMatchDestinationURL = $phraseMatchCriteriaObjArr[$x]->destinationUrl;
					$phraseMaxCPC = $phraseMatchCriteriaObjArr[$x]->maxCpc;
				}
			}
			
			// Now add the negative Exact Matches
			$newCriteriaObjArr = array();
			foreach($negativeExactMatchWords as $thisNegativeExactMatchWord){
				
				$newCriterObj = new AdWordsCriteriaKeyword();
				$newCriterObj->text = $thisNegativeExactMatchWord;
				$newCriterObj->adGroupId = $thisAdGroupObj->id;
				$newCriterObj->criterionType = "Keyword";
				$newCriterObj->maxCpc = $broadMaxCPC;
				$newCriterObj->destinationUrl = $broadDestinationURL;
				$newCriterObj->negative = "True";
				$newCriterObj->paused = "False";
				$newCriterObj->status = "Active";
				$newCriterObj->type = "Exact";
				$newCriterObj->firstPageCpc = 0;
				$newCriterObj->id = 0;
				$newCriterObj->qualityScore = 0;
	//print "------" . htmlspecialchars($thisNegativeExactMatchWord) . "<br>";			
			//	$criterionServiceObj->addCriteria(array($newCriterObj));
	//print "---------------------<br>";	
				
				$newCriteriaObjArr[] = $newCriterObj;
				
				// Add keywords in clumps of 50 at a time.
				if(sizeof($newCriteriaObjArr) > 50){
					
					print "Adding 50 more negative Exact Match<br>";
					
					usleep(500000);
					$newCriteriaReturnArr = $criterionServiceObj->addCriteria($newCriteriaObjArr);
					if(empty($newCriteriaReturnArr)){
						print "Error, can not add new Criteria.\n\n<br>";
						var_dump($newCriteriaObjArr);
						exit;
					}
					$newCriteriaObjArr = array();
				}
			}
			
			$newCriteriaReturnArr = $criterionServiceObj->addCriteria($newCriteriaObjArr);
			if(empty($newCriteriaReturnArr)){
				print "Error, can not add new Criteria.\n\n<br>";
				var_dump($newCriteriaObjArr);
				exit;
			}
		}
		
		*/
	
		//if($isBroadMatchAdGroup){
		if(false){
print "Is Broad Match: Name: $nameWithoutMatch <br>";			
			//if($nameWithoutMatch != "PC-General")
			//	continue;
		

			
			//var_dump($positiveKeywordsForBroadMatchArr);
			//var_dump($negativeExactMatchWords);
			//var_dump($negativePhraseMatchWords);
			



			// First Delete all of the Criteria that currently exists.
			$broadDestinationURL = "";
			$broadMaxCPC = 0;
			$deleteCriteriaIdArr = array();
			$broadMatchCriteriaObjArr = $criterionServiceObj->getAllCriteria($thisAdGroupObj->id);
			for($x=0; $x<sizeof($broadMatchCriteriaObjArr); $x++){
				$broadMatchCriteriaObjArr[$x]->status = "Deleted";
				
				// Get the Destination URL and CPC from any of the "Positive Search Terms"... that is in common with the others that we will be using.
				if($broadMatchCriteriaObjArr[$x]->negative == "false"){
					$broadDestinationURL = $broadMatchCriteriaObjArr[$x]->destinationUrl;
					$broadMaxCPC = $broadMatchCriteriaObjArr[$x]->maxCpc;
				}
				
	
				//if($broadMatchCriteriaObjArr[$x]->negative == "true"){
//print "<hr>Trying to Remove Criteria: " . htmlspecialchars($broadMatchCriteriaObjArr[$x]->text)  . "<hr>";

					$deleteCriteriaIdArr[] = $broadMatchCriteriaObjArr[$x]->id;
				//}
				//else{
				//	print "Criteria Not True..........<br>";
				//}
			}
			
			$criterionServiceObj->removeCriteria($thisAdGroupObj->id, $deleteCriteriaIdArr);
//var_dump($broadMatchCriteriaObjArr);			

			//continue;
print "dest: " . $broadDestinationURL . "<br>";
print "cpc: " . $broadMaxCPC . "<br>";
flush();

			print "Deleted the Broad Match Criteria -------------<br>";
			
			$newCriteriaObjArr = array();
			foreach($positiveKeywordsForBroadMatchArr as $thisPositiveKeyword){
				$newCriterObj = new AdWordsCriteriaKeyword();
				$newCriterObj->text = $thisPositiveKeyword;
				$newCriterObj->adGroupId = $thisAdGroupObj->id;
				$newCriterObj->criterionType = "Keyword";
				$newCriterObj->maxCpc = $broadMaxCPC;
				$newCriterObj->destinationUrl = $broadDestinationURL;
				$newCriterObj->negative = "False";
				$newCriterObj->paused = "False";
				$newCriterObj->status = "Active";
				$newCriterObj->type = "Broad";
				$newCriterObj->firstPageCpc = 0;
				$newCriterObj->id = 0;
				$newCriterObj->qualityScore = 0;
				
				//$criterionServiceObj->addCriteria(array($newCriterObj));
				
				$newCriteriaObjArr[] = $newCriterObj;
			}
			
			$newCriteriaReturnArr = $criterionServiceObj->addCriteria($newCriteriaObjArr);
			if(empty($newCriteriaReturnArr)){
				print "Error, can not add new Criteria.\n\n<br>";
				var_dump($newCriteriaObjArr);
				exit;
			}
			
			print "Adding Postive Broad Match Criteria: " . sizeof($newCriteriaObjArr) . "  -------------<br>";
			
			// Now add the negative Exact Matches
			$newCriteriaObjArr = array();
			foreach($negativeExactMatchWords as $thisNegativeExactMatchWord){
				
				$newCriterObj = new AdWordsCriteriaKeyword();
				$newCriterObj->text = $thisNegativeExactMatchWord;
				$newCriterObj->adGroupId = $thisAdGroupObj->id;
				$newCriterObj->criterionType = "Keyword";
				$newCriterObj->maxCpc = $broadMaxCPC;
				$newCriterObj->destinationUrl = $broadDestinationURL;
				$newCriterObj->negative = "True";
				$newCriterObj->paused = "False";
				$newCriterObj->status = "Active";
				$newCriterObj->type = "Exact";
				$newCriterObj->firstPageCpc = 0;
				$newCriterObj->id = 0;
				$newCriterObj->qualityScore = 0;
	//print "------" . htmlspecialchars($thisNegativeExactMatchWord) . "<br>";			
			//	$criterionServiceObj->addCriteria(array($newCriterObj));
	//print "---------------------<br>";	
				
				$newCriteriaObjArr[] = $newCriterObj;
				
				// Add keywords in clumps of 50 at a time.
				if(sizeof($newCriteriaObjArr) > 50){
					
					print "Adding 50 more negative Exact Match<br>";
					
					usleep(500000);
					$newCriteriaReturnArr = $criterionServiceObj->addCriteria($newCriteriaObjArr);
					if(empty($newCriteriaReturnArr)){
						print "Error, can not add new Criteria.\n\n<br>";
						var_dump($newCriteriaObjArr);
						exit;
					}
					$newCriteriaObjArr = array();
				}
			}
			
			$newCriteriaReturnArr = $criterionServiceObj->addCriteria($newCriteriaObjArr);
			if(empty($newCriteriaReturnArr)){
				print "Error, can not add new Criteria.\n\n<br>";
				var_dump($newCriteriaObjArr);
				exit;
			}
	//var_dump($newCriteriaObjArr);		
			print "Added Negative Exact Match Criteria: " . sizeof($newCriteriaObjArr) . " -------------<br>";
			
			// Now add the negative Phrase Matches
			$newCriteriaObjArr = array();
			foreach($negativePhraseMatchWords as $thisNegativePhraseMatchWord){
				$newCriterObj = new AdWordsCriteriaKeyword();
				$newCriterObj->text = $thisNegativePhraseMatchWord;
				$newCriterObj->adGroupId = $thisAdGroupObj->id;
				$newCriterObj->criterionType = "Keyword";
				$newCriterObj->maxCpc = $broadMaxCPC;
				$newCriterObj->destinationUrl = $broadDestinationURL;
				$newCriterObj->negative = "True";
				$newCriterObj->paused = "False";
				$newCriterObj->status = "Active";
				$newCriterObj->type = "Phrase";
				$newCriterObj->firstPageCpc = 0;
				$newCriterObj->id = 0;
				$newCriterObj->qualityScore = 0;
				
				//$criterionServiceObj->addCriteria(array($newCriterObj));
				
				$newCriteriaObjArr[] = $newCriterObj;
				
				// Add keywords in clumps of 50 at a time.
				if(sizeof($newCriteriaObjArr) > 50){
					
					print "Adding 50 more negative Phrase Match<br>";
					usleep(500000);
					$newCriteriaReturnArr = $criterionServiceObj->addCriteria($newCriteriaObjArr);
					if(empty($newCriteriaReturnArr)){
						print "Error, can not add new Criteria.\n\n<br>";
						var_dump($newCriteriaObjArr);
						exit;
					}
					$newCriteriaObjArr = array();
				}
			}
			$newCriteriaReturnArr = $criterionServiceObj->addCriteria($newCriteriaObjArr);
			if(empty($newCriteriaReturnArr)){
				print "Error, can not add new Criteria.\n\n<br>";
				var_dump($newCriteriaObjArr);
				exit;
			}
			
			//print "Added Negative Phrase Match Criteria: " . sizeof($newCriteriaObjArr) . "  -------------<br>";
			
			//$criterionServiceObj->addCriteria($newCriteriaObjArr);
			
	//var_dump($newCriteriaObjArr);
			
			//$criterionServiceObj->addCriteria($newCriteriaObjArr);
			
			
//if($nameWithoutMatch == "BC-3-General" || $nameWithoutMatch == "PC-General" || $nameWithoutMatch == "PC-Save The Date")
//	continue;
/*
			

			// If we don't have any Criteria in an Broad Match Group.
			// Get the criteria from the corresponding Exact Match Ad Group.
			//--------------------------------------------------------------
			$broadMatchCriteriaObjArr = $criterionServiceObj->getAllCriteria($thisAdGroupObj->id);
			
			
			// We want to make sure that for every Exact Match criteria... we have a negative embedded match within the Broad Match AdGroup
			$exactMatchAdGroupID = array_search("(Exact) " . $nameWithoutMatch, $adGroupsNamesArr);
			
			$correspondingExactMatchesArr = array();
			
			if($exactMatchAdGroupID){
				$exactMatchCriteriaObjArr = $criterionServiceObj->getAllCriteria($exactMatchAdGroupID);
			}
			


			
			if(sizeof($broadMatchCriteriaObjArr) == 0){

				$exactMatchAdGroupID = array_search("(Exact) " . $nameWithoutMatch, $adGroupsNamesArr);
				
				if($exactMatchAdGroupID){
					$exactMatchCriteriaObjArr = $criterionServiceObj->getAllCriteria($exactMatchAdGroupID);
				
					// Extract only the "Phrase Match" criteria and convert the AdGroup IDs.
					$newBroadGroupCriteriaObjArr = array();
					
					foreach($exactMatchCriteriaObjArr as $thisExactMatchCriteriaObj){
						if($thisExactMatchCriteriaObj->type == "Broad"){
							$thisExactMatchCriteriaObj->paused = "False";
							$thisExactMatchCriteriaObj->adGroupId = $thisAdGroupObj->id;
							
							$newBroadGroupCriteriaObjArr[] = $thisExactMatchCriteriaObj;
						}
					}
					
					print "Adding " . sizeof($newBroadGroupCriteriaObjArr) . " Keywords for Broad Match<br>";
					$criterionServiceObj->addCriteria($newBroadGroupCriteriaObjArr);
					
				}
			}
			

					
			
			// If we don't have any Ads running from a Phrase Match Group.
			// Get the Ads from the corresponding Exact Match Ad Group.
			//--------------------------------------------------------------
			$broadTextAdsObjArr = $textAdsServiceObj->getActiveAds(array($thisAdGroupObj->id));

			if(sizeof($broadTextAdsObjArr) == 0){

				$exactMatchAdGroupID = array_search("(Exact) " . $nameWithoutMatch, $adGroupsNamesArr);
				
				if($exactMatchAdGroupID){
					$exactMatchTextAdsObjArr = $textAdsServiceObj->getActiveAds(array($exactMatchAdGroupID));
//var_dump($exactMatchTextAdsObjArr);
					// Extract only the "Phrase Match" criteria and convert the AdGroup IDs.
					$newTextAdsObjArr = array();
					
					foreach($exactMatchTextAdsObjArr as $thisTextAdObj){

						$thisTextAdObj->adGroupId = $thisAdGroupObj->id;
						$newTextAdsObjArr[] = $thisTextAdObj;

					}
					
					print "Adding " . sizeof($newTextAdsObjArr) . " new Text Ads for Broad Match<br>";
//var_dump($newTextAdsObjArr);
					$textAdsServiceObj->addAds($thisAdGroupObj->id, $newTextAdsObjArr);
					
				}
			}
*/
		}
		
		//$criteriaObjArr = $criterionServiceObj->getAllCriteria($thisAdGroupObj->id);
		$criteriaObjArr = array();
		
		//var_dump($criteriaObjArr);
		/*
		foreach($criteriaObjArr as $thisCriteriaObj){
			
			if(($isCityCampign || $isStateCampaign) && $thisCriteriaObj->paused == "false" && ($thisCriteriaObj->type == "Phrase" || $thisCriteriaObj->type == "Broad")){
				$thisCriteriaObj->paused = "true";
				$criteriaUpdateArr[] = $thisCriteriaObj;
				print "Going to Pause " . $thisCriteriaObj->text . " Type: " . $thisCriteriaObj->type . "<br>";
			}
		}
		
		if(sizeof($criteriaUpdateArr) > 0){
			print "Updating " . sizeof($criteriaUpdateArr) . " Criterion for AdGroup ID ".  $thisCriteriaObj->id . ".<br>";
			$criterionServiceObj->updateCriteria($criteriaUpdateArr);
			continue;
		}
		*/
		/*
		if($isBroadMatchAdGroup){
			
			if($nameWithoutMatch != "BC-3-General")
				continue;
				
			$newCriterObj = new AdWordsCriteriaKeyword();
			$newCriterObj->text = "business cards";
			$newCriterObj->adGroupId = $thisAdGroupObj->id;
			$newCriterObj->criterionType = "Keyword";
			$newCriterObj->maxCpc = 1.00;
			$newCriterObj->destinationUrl = "http://www.printsmadeesy.com/log.php?from=g-error";
			$newCriterObj->negative = "true";
			$newCriterObj->paused = "false";
			$newCriterObj->status = "Active";
			$newCriterObj->type = "Exact";
			$newCriterObj->firstPageCpc = 0;
			$newCriterObj->id = 0;
			$newCriterObj->qualityScore = 0;
			print "Adding Broad Exact Negative<br>";
			
			$returnArr = $criterionServiceObj->addCriteria(array($newCriterObj));
			if(empty($returnArr))
				print "Empty Return...<br>";
			else{
				var_dump($returnArr);
			}
			
			$newCriterObj = new AdWordsCriteriaKeyword();
			$newCriterObj->text = "business cards";
			$newCriterObj->adGroupId = $thisAdGroupObj->id;
			$newCriterObj->criterionType = "Keyword";
			$newCriterObj->maxCpc = 1.00;
			$newCriterObj->destinationUrl = "http://www.printsmadeesy.com/log.php?from=g-error";
			$newCriterObj->negative = "true";
			$newCriterObj->paused = "false";
			$newCriterObj->status = "Active";
			$newCriterObj->type = "Phrase";
			$newCriterObj->firstPageCpc = 0;
			$newCriterObj->id = 0;
			$newCriterObj->qualityScore = 0;
			
			print "Adding Broad Phrase Negative<br>";
			$criterionServiceObj->addCriteria(array($newCriterObj));
		}
		
		if($isPhraseMatchAdGroup){
			
			if($nameWithoutMatch != "BC-3-General")
				continue;
				
			$newCriterObj = new AdWordsCriteriaKeyword();
			$newCriterObj->text = "business cards";
			$newCriterObj->adGroupId = $thisAdGroupObj->id;
			$newCriterObj->criterionType = "Keyword";
			$newCriterObj->maxCpc = 1.00;
			$newCriterObj->destinationUrl = "http://www.printsmadeesy.com/log.php?from=g-error";
			$newCriterObj->negative = "true";
			$newCriterObj->paused = "false";
			$newCriterObj->status = "Active";
			$newCriterObj->type = "Exact";
			$newCriterObj->firstPageCpc = 0;
			$newCriterObj->id = 0;
			$newCriterObj->qualityScore = 0;
			
			print "Adding Phrase Exact Negative<br>";
			$criterionServiceObj->addCriteria(array($newCriterObj));
			

		}
		*/
		
		// Only add new Match Types based on 1 Match Type... otherwise we could get duplicate.
	//	if($isExactMatchAdGroup){
			
			if($nameWithoutMatch != "BC-3-General")
				continue;
			/*
			$criteriaObjArr = $criterionServiceObj->getAllCriteria($thisAdGroupObj->id);
//var_dump($criteriaObjArr);			
			//$businessCardsExactMatch = false;
			//$businessCardExactMatch = false;
			
			$removeCriteriaIdsArr = array();
			foreach($criteriaObjArr as $thisCriteriaObj){
				if($thisCriteriaObj->text == "business cards in los angeles")
					$removeCriteriaIdsArr[] = $thisCriteriaObj->id;
				else if($thisCriteriaObj->text == "los angeles business cards")
					$removeCriteriaIdsArr[] = $thisCriteriaObj->id;
				else if($thisCriteriaObj->text == "los angeles business card")
					$removeCriteriaIdsArr[] = $thisCriteriaObj->id;
				else if($thisCriteriaObj->text == "business card in los angeles")
					$removeCriteriaIdsArr[] = $thisCriteriaObj->id;
			}
			
			print "Removing Criteria: " . sizeof($removeCriteriaIdsArr) . "<br>";
			$criterionServiceObj->removeCriteria($thisAdGroupObj->id, $removeCriteriaIdsArr);
			*/
			/*
			// Print out all of the Exact Match Terms.
			foreach($criteriaObjArr as $thisCriteriaObj){
				
				if($thisCriteriaObj->paused == "false" && $thisCriteriaObj->type == "Exact" && $thisCriteriaObj->text == "business cards"){
					$businessCardsExactMatch = true;
				}
				
				if($thisCriteriaObj->paused == "true" && $thisCriteriaObj->type == "Exact" && $thisCriteriaObj->text == "business cards"){
					print "Un-pausing Exact Match Business cards<br>";
					$thisCriteriaObj->paused = "false";
					$criterionServiceObj->updateCriteria(array($thisCriteriaObj));
					
					
				}
				else if($thisCriteriaObj->paused == "false" && $thisCriteriaObj->type == "Exact" && $thisCriteriaObj->text == "business card"){
					$businessCardExactMatch = true;
				}
				
			}
			*/
/*
[business cards]
[business card]
 */			
			/*
			if(!$businessCardsExactMatch){
				print "<b>[Business Cards] Not Found</b><br>";
			}
			if(!$businessCardExactMatch){
				print "<b>[Business Cards] Not Found</b><br>";
			}
			*/

			
			/*
			if(!in_array("(Phrase) " . $nameWithoutMatch, $adGroupsNamesArr)){
				print "Phrase Match Missing for $nameWithoutMatch <br>";
				$thisAdGroupObj->name = "(Phrase) " . $nameWithoutMatch;
				$adGroupCreateArr[] = $thisAdGroupObj;
				continue;
			}
			*/
			
			/*
			if(!in_array("(Broad) " . $nameWithoutMatch, $adGroupsNamesArr)){
				print "Broad Match Missing for $nameWithoutMatch <br>";
				$thisAdGroupObj->name = "(Broad) " . $nameWithoutMatch;
				$adGroupCreateArr[] = $thisAdGroupObj;
				continue;
			}
			*/
	//	}
		
	}
/*
	print "Updating " . sizeof($adGroupUpdateArr) . " ad groups.<br>";
	if(sizeof($adGroupUpdateArr) > 0){
		$adGroupServiceObj->updateAdGroupList($adGroupUpdateArr);
	}
	
	print "Adding " . sizeof($adGroupCreateArr) . " ad groups.<br>";
	if(sizeof($adGroupCreateArr) > 0){
		$adGroupServiceObj->addAdGroupList($campaignID, $adGroupCreateArr);
	}
	*/
	
	print "<br><br>";
	flush();
}





//$campaignStatsArr = $campaignServiceObj->getCampaignStats(array(35476214, 35476124), mktime(1,1,1, 3, 1, 2009), mktime(1,1,1, 3, 31, 2009));
//var_dump($campaignStatsArr);




//$adtest = new AdWordsAdGroupService();
//$groupStatsArr = $adtest->getAdGroupStats(35476214, array(1222490624), mktime(1,1,1, 7, 10, 2008), mktime(1,1,1, 4, 15, 2009));
//var_dump($groupStatsArr);



//$adtest = new AdWordsAdGroupService();
//$adGroupsArr = $adtest->getAdGroupList(array(1222490624));
//var_dump($adGroupsArr);
//var_dump($adGroupsArr);
/*
for($i=0; $i<sizeof($adGroupsArr); $i++){
	
	print $adGroupsArr[$i]->getAdGroupXml();
	
	$adGroupsArr[$i]->name = "Test Add";
	//$adGroupsArr[$i]->keywordMaxCpc = "1.00";
	print "\n\n\n\n";
}

$newAdGroupsArr = $adtest->addAdGroupList(35476214, $adGroupsArr);

var_dump($newAdGroupsArr);
*/
//$adtest = new AdWordsAdGroupService();
//$adGroupsArr = $adtest->getActiveAdGroups(35476214);
//var_dump($adGroupsArr);

/*
$adwordsAdObj = new AdWordsAdService();
$criteriaArr = $criteriaObj->getCriteria(1222490624, array(115447517, 365338408));
*/
/*
$adsArr = $adwordsAdObj->getActiveAds(array(1222490624));
//var_dump($adsArr);
$adsIdsArr = array();
for($i=0; $i<sizeof($adsArr); $i++){
	
	$adsIdsArr[] = $adsArr[$i]->id;
}
print "\n\n\n\n";
print "<br><br>";
var_dump($adsIdsArr);
print "<br><br>\n\n";
$adStatsArr = $adwordsAdObj->getAdStats(1222490624, $adsIdsArr, mktime(1,1,1, 7, 10, 2008), mktime(1,1,1, 4, 15, 2009));

var_dump($adStatsArr);

*/
/*
$criterionServiceObj = new AdWordsCriterionService();
$criteriaObjArr = $criterionServiceObj->getAllCriteria(1222493324);
$criteriaIdArr = array();
var_dump($criteriaObjArr);

foreach($criteriaObjArr as $thisCriteriaObj){
	$criteriaIdArr[] = $thisCriteriaObj->id;
}



$criteraStatsObjArr = $criterionServiceObj->getCriterionStats(1222493324, $criteriaIdArr, $startTimeStamp, $endTimeStamp);
var_dump($criteraStatsObjArr);
*/
/*
$criteriaIdArr = array();

for($i=0; $i<sizeof($criteriaArr); $i++){
	
	if(preg_match("/^Test/", $criteriaArr[$i]->text)){
		$criteriaIdArr[] = $criteriaArr[$i]->id;
		print "Removing: " .$criteriaArr[$i]->text . " Match: " . $criteriaArr[$i]->type . "<br>";
	}
	
	//$criteriaArr[$i]->text = "Test " . $i;
	//$criteriaArr[$i]->paused = "true";
	//$criteriaArr[$i]->type = "Exact";
	//$criteriaArr[$i]->destinationUrl = "http://www.PrintsMadeEasy.com/greetings";
	//$adGroupsArr[$i]->keywordMaxCpc = "1.00";
	//print "\n\n\n\n";
}
*/

//$newCriteriaArr = $criteriaObj->addCriteria($criteriaArr);
//var_dump($newCriteriaArr);

//$criteriaObj->removeCriteria(1222490624, $criteriaIdArr);

//var_dump($criteriaIdArr);

//print "<br><br>CTR: " . $groupStatsObj->getClickThroughRate();
//print "<br><br>CPC: " . $groupStatsObj->getCostPerClick();


print "<hr>Done!<hr>";

?>
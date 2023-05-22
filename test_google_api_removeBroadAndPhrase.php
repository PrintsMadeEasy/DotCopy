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
		
	//if($i > 2)
	//	continue;
	
	$campaignID = $campaignsArr[$i]->id;
	

	
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
	
	$adGroupCounter = 0;
	foreach($activeAdGroupsArr as $thisAdGroupObj){
		
		
		$adGroupCounter++;
		
		//if($adGroupCounter > 2)
		//	continue;
		
		$criteriaUpdateArr = array();
		
		print $thisAdGroupObj->name . "<br>";
		flush();
		
		$criteriaObjArr = $criterionServiceObj->getAllCriteria($thisAdGroupObj->id);
		
		foreach($criteriaObjArr as $thisCriteriaObj){
			if($thisCriteriaObj->type == "Phrase" || $thisCriteriaObj->type == "Broad"){
				
				if($thisCriteriaObj->status == "Active" || $thisCriteriaObj->paused != "True"){
					
					$thisCriteriaObj->paused = "True";
					
					$criteriaUpdateArr[] = $thisCriteriaObj;
				}
			}
		}
		
		//var_dump($criteriaUpdateArr);
		
		$criterionServiceObj->updateCriteria($criteriaUpdateArr);
	}
		
	
	print "<br><br>";
	flush();
}




print "<hr>Done!<hr>";





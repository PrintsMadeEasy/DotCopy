<?php
require_once("library/Boot_Session.php");

exit("Script is disabled right now.");

//$campignServiceObj = new AdWordsCampaignService();

//$campaignsArr = $campignServiceObj->getActiveAdWordsCampaigns();
//$campaignsArr = $campignServiceObj->getCampaignList(array(35476214, 35476124, 35473874));
//$campaignsArr = $campignServiceObj->getCampaignList(array(35476214));
//var_dump($campaignsArr);
/*
for($i=0; $i<sizeof($campaignsArr); $i++){
	
	print $campaignsArr[$i]->getCampaignXml();
	
	$campaignsArr[$i]->budgetAmount = 1000;
}
*/
//$campignServiceObj->updateCampaignList($campaignsArr);


//$campaignStatsArr = $campignServiceObj->getCampaignStats(array(35476214, 35476124), mktime(1,1,1, 3, 1, 2009), mktime(1,1,1, 3, 31, 2009));
//var_dump($campaignStatsArr);

$startTimeStamp = time() - (60 * 60 * 24 * 8);
$endTimeStamp = time() - (60 * 60 * 24 * 1);


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

$criterionServiceObj = new AdWordsCriterionService();
$criteriaObjArr = $criterionServiceObj->getAllCriteria(1222493324);
$criteriaIdArr = array();
var_dump($criteriaObjArr);

foreach($criteriaObjArr as $thisCriteriaObj){
	$criteriaIdArr[] = $thisCriteriaObj->id;
}



$criteraStatsObjArr = $criterionServiceObj->getCriterionStats(1222493324, $criteriaIdArr, $startTimeStamp, $endTimeStamp);
var_dump($criteraStatsObjArr);

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


?>
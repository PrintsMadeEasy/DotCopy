<?
class GoogleCsvReport {
	
	private $fileName;
	
	private $matchType_Broad;
	private $matchType_Phrase;
	private $matchType_Exact;
	
	private $keywordMatchesArr = array();
	private $keywordNegativesArr = array();
	
	private $adGroupNameMatchesArr = array();
	private $adGroupNameNegativesArr = array();
	
	private $campaignNameMatchesArr = array();
	private $campaignNameNegativesArr = array();
	
	private $accountNameMatchesArr = array();
	private $accountNameNegativesArr = array();
	
	private $destinationUrlMatchesArr = array();
	private $destinationUrlNegativesArr = array();
	
	private $minimumDate;
	private $maximumDate;
	
	private $adDistribution_Content;
	private $adDistribution_Search;
	
	private $adDistribution_SearchParnter_Google;
	private $adDistribution_SearchParnter_Other;
	

	function __construct($fileName){
		$this->fileName = $fileName;
		
		if(!file_exists($this->fileName))
			throw new Exception("The file name does not exist.");
			
		$this->matchType_Broad = false;
		$this->matchType_Phrase = false;
		$this->matchType_Exact = false;
		
		$this->minimumDate = null;
		$this->maximumDate = null;
		
		$this->adDistribution_Content = false;
		$this->adDistribution_Search = false;
		$this->adDistribution_SearchParnter_Google = false;
		$this->adDistribution_SearchParnter_Other = false;
	}
	
	private function ensureParametersAreSet(){
		if(!$this->matchType_Broad && !$this->matchType_Phrase && !$this->matchType_Exact)
			throw new Exception("You must specify one or more match types, Exact, Phrase, or Broad");
			
		if(!$this->adDistribution_Content && !$this->adDistribution_Search)
			throw new Exception("You must specify if you want to search in the Content Network, Search, or Both.");
			
		if($this->adDistribution_Search && (!$this->adDistribution_SearchParnter_Google && !$this->adDistribution_SearchParnter_Other))
			throw new Exception("If you are searching within the Search Network... you must specify whether youw ant to search through just Google, or their Partners affiliates like AOL.");
	}
	
	
	// The more match types that you set to TRUE... the more likely you are to match a report row.
	function setExactMatch($matchTypeFlag){
		if(!is_bool($matchTypeFlag))
			throw new Exception("Match type must be boolean");
		$this->matchType_Exact = $matchTypeFlag;
	}
	function setPhraseMatch($matchTypeFlag){
		if(!is_bool($matchTypeFlag))
			throw new Exception("Match type must be boolean");
		$this->matchType_Phrase = $matchTypeFlag;
	}
	function setBroadMatch($matchTypeFlag){
		if(!is_bool($matchTypeFlag))
			throw new Exception("Match type must be boolean");
		$this->matchType_Broad = $matchTypeFlag;
	}
	
	
	
	function setAdDistributionContent($boolFlag){
		if(!is_bool($boolFlag))
			throw new Exception("Flat must be boolean");
		$this->adDistribution_Content = $boolFlag;
	}
	function setAdDistributionSearch($boolFlag){
		if(!is_bool($boolFlag))
			throw new Exception("Flat must be boolean");
		$this->adDistribution_Search = $boolFlag;
	}
	function setAdDistributionSearchPartnerGoogle($boolFlag){
		if(!is_bool($boolFlag))
			throw new Exception("Flat must be boolean");
		$this->adDistribution_SearchParnter_Google = $boolFlag;
	}
	
	function setAdDistributionSearchPartnerOther($boolFlag){
		if(!is_bool($boolFlag))
			throw new Exception("Flat must be boolean");
		$this->adDistribution_SearchParnter_Other = $boolFlag;
	}
	
	
	
	
	
	// Pass in an array of regular expressions that may trigger a match on the keyword, AdGroup Name, Campaign Name, or Destination URL.
	// The more values you pass into the array... the more chances you have of getting a match.
	function setKeywordsMatches(array $regularExpressionsArr){
		$this->keywordMatchesArr = $regularExpressionsArr;
	}
	function setAdGroupNameMatches(array $regularExpressionsArr){
		$this->adGroupNameMatchesArr = $regularExpressionsArr;
	}
	function setCampaignNameMatches(array $regularExpressionsArr){
		$this->campaignNameMatchesArr = $regularExpressionsArr;
	}
	function setAccountNameMatches(array $regularExpressionsArr){
		$this->accountNameMatchesArr = $regularExpressionsArr;
	}
	function setDestinationUrlMatches(array $regularExpressionsArr){
		$this->destinationUrlMatchesArr = $regularExpressionsArr;
	}
	
	
	// Pass in an array of regular expressions that may cause an entry not be counted... based upon keyword, AdGroup Name, Campaign Name, or Destination URL.
	// The more values you pass into the array... the more likely you will be to exclude an entry from the results.
	function setNegativeKeywordsMatches(array $regularExpressionsArr){
		$this->keywordNegativesArr = $regularExpressionsArr;
	}
	function setNegativeAdGroupNameMatches(array $regularExpressionsArr){
		$this->adGroupNameNegativesArr = $regularExpressionsArr;
	}
	function setNegativeCampaignNameMatches(array $regularExpressionsArr){
		$this->campaignNameNegativesArr = $regularExpressionsArr;
	}
	function setNegativeAccountNameMatches(array $regularExpressionsArr){
		$this->accountNameNegativesArr = $regularExpressionsArr;
	}
	function setNegativeDestinationUrlMatches(array $regularExpressionsArr){
		$this->destinationUrlNegativesArr = $regularExpressionsArr;
	}
	
	
	
	
	// This will return an array of AdWordsStatsRecord objects.
	// The key to the array will be the date.
	// Does daily reports.  It is hard to do this from Google because their CSV reports have duplicate keywords listed without sequence, between multiple clients and campaigns.
	function getReport(){
		
		$this->ensureParametersAreSet();

		$handle = @fopen($this->fileName, "r");
		if(!$handle)
			exit("Can not open the file.");
		
		$retArr = array();
			
		$counter = 0;
			
		while (!feof($handle)) {
			
			$thisLine = fgets($handle, 4096);
			
			//It looks like Google has a lot of NULL characters in their string.
			$thisLine = str_replace(str_split("\0\x0B"), '', $thisLine);
			
			$counter++;

			//if($counter > 100)
			//	break;

			$linePartsArr = FileUtil::csvExplode($thisLine);

			
			// Line #6 should contain the column names from a Google Report.  Make sure the columns match up to what we are expecting.
			if($counter == 7){
				
				$columnsDefinitionsArr = array("Date","Account","Customer Id","Campaign","Ad Group","Keyword","Keyword Matching","Est. First Page Bid","Ad Distribution","Ad Distribution: with search partners","Current Maximum CPC","Keyword Destination URL","Impressions","Clicks","CTR","Avg CPC","Avg CPM","Cost","Avg Position");
			
				
				if(sizeof($linePartsArr) < sizeof($columnsDefinitionsArr)){
					$thisLineSnipet = " Snipet: " . WebUtil::FilterData( substr($thisLine, 0, 100), FILTER_SANITIZE_STRING_ONE_LINE) . " ... ";
					throw new Exception("The number of columns we are expectecting is " . sizeof($columnsDefinitionsArr) . ". The Google Report had " . sizeof($linePartsArr) . " columns on line number 7. " . $thisLineSnipet);
				}
			
				for($i=0; $i<sizeof($columnsDefinitionsArr);$i++){
		
					if($columnsDefinitionsArr[$i] != trim($linePartsArr[$i]))
						throw new Exception("Column #" . ($i+1). "is supposed to be <u>" . htmlspecialchars($columnsDefinitionsArr[$i]) . "</u> ... but we read <u>" . htmlspecialchars($linePartsArr[$i]) . "</u> from the Google Report.");
				}
			}
			
		
		    $dateMatch = array();
			if(!preg_match("@^(\d+/\d+/\d+)@", $thisLine, $dateMatch))
				continue;
		
			$dateStr = $dateMatch[1];
			
			// Just in case it has a 2 digit year instead of 4 digits.
			$dateHash = date_parse($dateStr);
			$dateStr = $dateHash["month"] . "/" . $dateHash["day"] . "/" . $dateHash["year"];
			

			$accountName = trim($linePartsArr[1]);
			//$customerID = trim($linePartsArr[2]);
			$campaignName = trim($linePartsArr[3]);
			$adGroupName = trim($linePartsArr[4]);
			$keyword = trim($linePartsArr[5]);
			$matchType = trim($linePartsArr[6]);
			//$estFirstPageBid = trim(preg_replace('/\$/', '', $linePartsArr[7]));
			$adDistribution = trim($linePartsArr[8]);
			$adDistributionSearchType = trim($linePartsArr[9]);
			//$currentMaxCPC = trim(preg_replace('/\$/', '', $linePartsArr[10]));
			$destinationURL = trim($linePartsArr[11]);
			$impressions = intval(trim($linePartsArr[12]));
			$clicks = intval(trim($linePartsArr[13]));
			//$clickThroughRate = floatval(trim($linePartsArr[14]));
			//$averageCPC = floatval(preg_replace('/\$/', '', trim($linePartsArr[15])));
			//$averageCPM = floatval(preg_replace('/\$/', '', trim($linePartsArr[16])));
			$cost = floatval(preg_replace('/\$/', '', trim($linePartsArr[17])));
			$averagePosition = floatval(trim($linePartsArr[18]));
			
		
			
			// If we don't find the row in our list of "positive matches"... or we find at least one negative match... then we don't count the row.
			// If no regular expressions were provided for keywords, AgGroups, etc... then consider we found a match.  It means we don't care.
			$keywordMatchFound = false;
			if(empty($this->keywordMatchesArr))
				$keywordMatchFound = true;
			foreach($this->keywordMatchesArr as $thisRegEx){
				if(preg_match("@$thisRegEx@i", $keyword))
					$keywordMatchFound = true;
			}
			//---------------
			$adGroupNameMatchFound = false;
			if(empty($this->adGroupNameMatchesArr))
				$adGroupNameMatchFound = true;
			foreach($this->adGroupNameMatchesArr as $thisRegEx){
				if(preg_match("@$thisRegEx@i", $adGroupName))
					$adGroupNameMatchFound = true;
			}
			//---------------
			$campaignNameMatchFound = false;
			if(empty($this->campaignNameMatchesArr))
				$campaignNameMatchFound = true;
			foreach($this->campaignNameMatchesArr as $thisRegEx){
				if(preg_match("@$thisRegEx@i", $campaignName))
					$campaignNameMatchFound = true;
			}
			//---------------
			$accountNameMatchFound = false;
			if(empty($this->accountNameMatchesArr))
				$accountNameMatchFound = true;
			foreach($this->accountNameMatchesArr as $thisRegEx){
				if(preg_match("@$thisRegEx@i", $accountName))
					$accountNameMatchFound = true;
			}

			//---------------
			$destUrlMatchFound = false;
			if(empty($this->destinationUrlMatchesArr))
				$destUrlMatchFound = true;
			foreach($this->destinationUrlMatchesArr as $thisRegEx){
				if(preg_match("@$thisRegEx@i", $destinationURL))
					$destUrlMatchFound = true;
			}
			
			
			
			// Now go through all of our negative regular expressions to see if they trigger a match on the row.
			foreach($this->keywordNegativesArr as $thisRegEx){
				if(preg_match("@$thisRegEx@i", $keyword))
					$keywordMatchFound = false;
			}
			//---------------
			foreach($this->adGroupNameNegativesArr as $thisRegEx){
				if(preg_match("@$thisRegEx@i", $adGroupName))
					$adGroupNameMatchFound = false;
			}
			//---------------
			foreach($this->campaignNameNegativesArr as $thisRegEx){
				if(preg_match("@$thisRegEx@i", $campaignName))
					$campaignNameMatchFound = false;
			}
			//---------------
			foreach($this->accountNameNegativesArr as $thisRegEx){
				if(preg_match("@$thisRegEx@i", $accountName))
					$accountNameMatchFound = false;
			}
			//---------------
			foreach($this->destinationUrlNegativesArr as $thisRegEx){
				if(preg_match("@$thisRegEx@i", $destinationURL))
					$destUrlMatchFound = false;
			}
			
		
			if(!$keywordMatchFound || !$adGroupNameMatchFound || !$campaignNameMatchFound || !$destUrlMatchFound || !$accountNameMatchFound)
				continue;
				
				
			// Filter out the Content Network campaigns versus pure search campaigns
			if($adDistribution == "Search Only"){
				if(!$this->adDistribution_Search)
					continue;
			}
			else if($adDistribution == "Content Only"){
				if(!$this->adDistribution_Content)
					continue;
			}
			else{
				throw new Exception("The Ad Distribution Type is unknown: " . htmlspecialchars($adDistribution));
			}

			
			// Filter out BM, PM, and EM based upon query paramters.
			if($matchType == "Exact"){
				if(!$this->matchType_Exact)
					continue;
			}
			else if($matchType == "Phrase"){
				if(!$this->matchType_Phrase)
					continue;
			}
			else if($matchType == "Broad"){
				if(!$this->matchType_Broad)
					continue;
			}
			else {
				throw new Exception("Unknown Match Type within Google CSV report:'" . htmlspecialchars($matchType) . "'");
			}



				
			// If we are doing a Search Campaign... we need to specify wheter we are searching through just google.com ... or the partner networks as well.
			if($this->adDistribution_Search){
				if($adDistributionSearchType == "Google Search"){
					if(!$this->adDistribution_SearchParnter_Google)
						continue;
				}
				else if($adDistributionSearchType == "Search Partners"){
					if(!$this->adDistribution_SearchParnter_Other)
						continue;
				}
				else{ 
					throw new Exception("Unknown ad Distribution search parther.");
				}
			}
			
//print $thisLine . "\n\n";				
			// If we don't already have an entry for the required date... then create a new AdWordsStatsRecord object.
			if(!array_key_exists($dateStr, $retArr))
				$retArr[$dateStr] = new AdWordsStatsRecord();
			
			$retArr[$dateStr]->clicks += $clicks;
			$retArr[$dateStr]->cost += $cost;
			$retArr[$dateStr]->impressions += $impressions;
			
			// The Average Position depends upon the weight of clicks.
			// Prevent Division by Zero
			if($retArr[$dateStr]->clicks == 0){
				// Since there are no previous clicks... the average position of the current row will get 100% of the weight.
				$retArr[$dateStr]->averagePosition = $averagePosition;
			}
			else if($clicks == 0){
				// Since the current row has no clicks... there shoudn't be any change in the Average Position.
				$retArr[$dateStr]->averagePosition = $retArr[$dateStr]->averagePosition;
			}
			else{
				// The weight of the click entry should never go more than 100%.
				// To make sure that is the case... always divide by the larger number.
				if($clicks > $retArr[$dateStr]->clicks)
					$weightOfThisEntry = $retArr[$dateStr]->clicks / $clicks;
				else 
					$weightOfThisEntry = $clicks / $retArr[$dateStr]->clicks;
					
				$avgPositionDifference = $averagePosition - $retArr[$dateStr]->averagePosition;
				$avgPositionAdjusted = $avgPositionDifference * $weightOfThisEntry;
				
				$retArr[$dateStr]->averagePosition += $avgPositionAdjusted;
				
			}
			
			//$thisLine = preg_replace("/,/", "-", $thisLine);
			//print preg_replace("/\t/", ",", $thisLine);
		}
		
		fclose($handle);
		
		return $this->fillInMissingDateKeysAndSort($retArr);
	}
	
	// If there is only 1 day in the report... then the first and last Report day will be the same.
	// If there are no days in the report... then both the first and last will be null.
	// The format is like ... mm/dd/yy ... '1/12/09' or '1/1/09'
	function getFirstReportDay(){
		return $this->minimumDate;	
	}
	function getLastReportDay(){
		return $this->maximumDate;	
	}
	
	// This method will look for the missing holes within dates between max and min values.
	// The date strings are stored within the keys or the array
	// It will also sort the array accending by date.
	private function fillInMissingDateKeysAndSort(array $arrayToSort){
	
		if(sizeof($arrayToSort) == 1){
			$this->minimumDate = key($arrayToSort);
			$this->maximumDate = key($arrayToSort);
		}
			
		
		if(sizeof($arrayToSort) <= 1)
			return $arrayToSort;
		
		$dateKeys = array_keys($arrayToSort);
		
		$dateRanges = array();
		
		// Build up an associative array.  The keys to the array are the unix timestamp equivalent.
		// The values are the date strings.
		foreach($dateKeys as $thisDateString){
			$dateHash = date_parse($thisDateString);
			$unixTimeStamp = mktime(1, 1, 1, $dateHash["month"], $dateHash["day"], $dateHash["year"]);
			
			$dateRanges[] = $unixTimeStamp;
		}
		
		// Sort based upon the unix timestamps, going acsending.
		sort($dateRanges);
		
		reset($dateRanges);
		$firstTimeStamp = current($dateRanges);
		end($dateRanges);
		$lastTimeStamp = current($dateRanges);
		
		$this->minimumDate = date("n/j/Y", $firstTimeStamp);
		$this->maximumDate = date("n/j/Y", $lastTimeStamp);
		
		$arrayForReturn = array();
		
		$nextTimeStamp = $firstTimeStamp;
		while($nextTimeStamp <= $lastTimeStamp){
			
			$dateString = date("n/j/Y", $nextTimeStamp);

			// If the Date String does not exist in the original array... then create an empty stats record.
			if(!array_key_exists($dateString, $arrayToSort)){
				$arrayForReturn[$dateString] = new AdWordsStatsRecord();
				$arrayForReturn[$dateString]->clicks = 0;
				$arrayForReturn[$dateString]->cost = 0;
				$arrayForReturn[$dateString]->averagePosition = 0;
				$arrayForReturn[$dateString]->impressions = 0;
			}
			else{
				$arrayForReturn[$dateString] = $arrayToSort[$dateString];
			}
				
			
			// advance timestamp 24 hours.
			$nextTimeStamp += (60 * 60 * 24);
		
		}
		
		return $arrayForReturn;
	}
}





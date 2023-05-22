<?php

class ArtworkTemplate {

	// This function will return an array containing a list of template id's matching the keywords.
	// Keywords are sent in as a string.  A space separates each keyword --#
	// Will group the results like following...
	// --- a) 	If the search does not find a match on all keywords it will try to find a match on individual keywords
	//		If there are multiple keywords the priority is always given to the template with the most keyword matches.
	// --- b)	Next priority will be given to the sort column insdide the tempaltesearch engine
	//		It is a 1 character string such as "M"
	// If the Kewords is a blank string ""... then the function will return a list of template IDs that do not have any keywords associated with them
	// Keywords_AND contains another search phase much be matched in addition to the first keyword.
	// Pass in Exact match as false if you don't care about specific matches.... every keyword in the phrase can trigger a template match....  TRUR means all keywords from the search phrase must exist with the template.
	static function GetSearchResultsForTempaltes($dbCmd, $Keywords, $Keywords_AND, $productid, $ExactMatch=false){
		
		$retArr = array();
		
		// If there are no kewords then we want to return a list of tempalate IDs that do not have any keywords associated with them
		if($Keywords == ""){
		
			// Get a list of template IDs found in the keyword table... then compare them with the list of TemplateID's in the actual table.. the difference is template ID's without any keywords.
			
			$TotalTemplatesArr = array();
			$dbCmd->Query( "SELECT ID FROM artworksearchengine WHERE ProductID=$productid ORDER BY Sort ASC, ID ASC");
			while($ThisID = $dbCmd->GetValue())
				$TotalTemplatesArr[] = $ThisID;
		
			$TemplatesWithKeywordsArr = array();
			$dbCmd->Query( "SELECT DISTINCT tk.TemplateID FROM templatekeywords AS tk INNER JOIN artworksearchengine AS ae ON tk.TemplateID = ae.ID WHERE ae.ProductID=$productid");
			while($ThisID = $dbCmd->GetValue())
				$TemplatesWithKeywordsArr[] = $ThisID;
				
			$ResultsArr = array_diff($TotalTemplatesArr, $TemplatesWithKeywordsArr);
			
			foreach($ResultsArr as $ThisID){
				$retArr[] = $ThisID;
			}
		}
		else{
		
			$KewordArr = WebUtil::GetKeywordArr($Keywords);
			$secondaryKewordArr = WebUtil::GetKeywordArr($Keywords_AND);
	
			// If we are not doing an exact match... then we also want to strip off "s" or "ies" if we find them... 
			// We will also leave the "s" just in case, so we will be adding a duplicate keyword to search on basically. ... if we find "ies" replace it with "y"
			// The reason for the duplicates... we can't be certain that we can safely remove an "s" but most of the time we need to.  EX: "Hypnosis", "plummers", "paties", "butterflies"
			if(!$ExactMatch){
				$KeywordArrCopy = $KewordArr;
				foreach($KeywordArrCopy as $thisKwd){
					if(substr($thisKwd, -1) == "s")
						$KewordArr[] = substr($thisKwd, 0, -1);
					
					//Make sure we don't try to substring on to little of a string
					if(strlen($thisKwd) > 3){
						$WordSuffix3 = substr($thisKwd, -3);
						$WordSuffix2 = substr($thisKwd, -2);
						
						if($WordSuffix3 == "ies")
							$KewordArr[] = substr($thisKwd, 0, -3) . "y";	//parties -> party
						else if($WordSuffix3 == "ing"){
							$KewordArr[] = substr($thisKwd, 0, -3);		//surfing -> surf
							$KewordArr[] = substr($thisKwd, 0, -3) . "e";	//landscaping -> landscape
						}
						else if($WordSuffix2 == "es")
							$KewordArr[] = substr($thisKwd, 0, -2);		//beaches -> beach
						else if($WordSuffix2 == "ed"){
							$KewordArr[] = substr($thisKwd, 0, -2);		//checkered -> checker
							$KewordArr[] = substr($thisKwd, 0, -1);		//tiled  ->  tile
						}
							
					}
				}
			}
	
			// If we are sorting with an AND term... then find out which template ID's have all of those terms in common.
			$matchingTemplateIDsOnSeconaryTermArr = array();
			if(!empty($secondaryKewordArr)){
				
				// Loop through all of the Template Keywords... successfully merging template ID's (which could make the array smaller on each iteration).
				$secondaryCounter = 0;
				foreach($secondaryKewordArr as $thisSecondKeyword){
					
					$dbCmd->Query( "SELECT DISTINCT tk.TemplateID FROM artworksearchengine AS artsch INNER JOIN templatekeywords AS tk ON artsch.ID = tk.TemplateID WHERE artsch.ProductID=$productid AND tk.TempKw LIKE \"" . DbCmd::EscapeSQL($thisSecondKeyword) . "\"" );
					$templateIDsWithThisKeywordArr = $dbCmd->GetValueArr();
					
					if($secondaryCounter == 0)
						$matchingTemplateIDsOnSeconaryTermArr = $templateIDsWithThisKeywordArr;
					else 
						$matchingTemplateIDsOnSeconaryTermArr = array_intersect($matchingTemplateIDsOnSeconaryTermArr, $templateIDsWithThisKeywordArr);
					
					$secondaryCounter++;
				}				
			}
			
			// Build a WHERE SQL clause out of the keyword list
			$SQLwhere = "";
			foreach($KewordArr as $thisKeyword)
				$SQLwhere .= " tk.TempKw LIKE \"" . DbCmd::EscapeSQL($thisKeyword) . "\" OR";
	
			// strip of the last 2 chars which should be "OR" 
			$SQLwhere = substr($SQLwhere, 0, -2);
		
	
			// First we want to get a unique list of template ID's that have any of the matching keywords in them
			// Also, sort by the template Sort column
			$dbCmd->Query( "SELECT DISTINCT artsch.ID as ID, artsch.Sort as Sort FROM artworksearchengine AS artsch INNER JOIN templatekeywords AS tk ON artsch.ID = tk.TemplateID WHERE artsch.ProductID=$productid AND ( $SQLwhere ) ORDER BY artsch.Sort DESC, artsch.ID DESC" );
			$TemplateMatchesArr = array();
			while($row = $dbCmd->GetRow()){
				
				// Skip templates which don't meet our secondary requirement.
				if(!empty($secondaryKewordArr)){
					if(!in_array($row["ID"], $matchingTemplateIDsOnSeconaryTermArr))
						continue;
				}
				
				$TemplateMatchesArr[$row["ID"] . " "] = $row["Sort"];
			}
	
			// Now get an array containing the number of keyword matches for each tempalte..
			// The key to the array is a "string" from of the template ID. and the value is the number of keyword matches
			$TempSortArr = array();
			
			

			
			foreach(array_keys($TemplateMatchesArr) as $thisTempID){
				$dbCmd->Query( "SELECT COUNT(*) FROM templatekeywords AS tk WHERE tk.TemplateID=$thisTempID AND ( $SQLwhere )" );
				$TempSortArr["$thisTempID"] = $dbCmd->GetValue();
			}
	
			// First sort by the number of keyword matches... if the number of keywords match... then sort by the Template Sort letter
			array_multisort($TempSortArr, SORT_DESC,  $TemplateMatchesArr, SORT_ASC);
	
			// We appended a blank space to each key value to make the multisort work above... just convert back to INTs before sending the template ID's back.
			foreach($TempSortArr as $TemplateIDKey => $NumResults ){
	
				// If we are looking for exact phrase matches... then make sure that there are the right number of keywords found
				if($ExactMatch){
					if($NumResults >= sizeof($KewordArr))
						$retArr[] = intval($TemplateIDKey);
				}
				else{
					$retArr[] = intval($TemplateIDKey);
				}
			}
			
		}
		
		
		return $retArr;
	}
	
	
	// Pass in a string of keywords... each word separted by a space
	// The function will not let you add duplicate keywords for the same template
	static function AddKeywordsToTemplate($dbCmd, $Keywords, $TemplateID){
	
		$KewordArr = WebUtil::GetKeywordArr($Keywords);
		$TemplateID = intval($TemplateID);
	
		foreach($KewordArr as $thisKeyword){
		
			$dbCmd->Query( "SELECT COUNT(*) FROM templatekeywords WHERE TemplateID=$TemplateID AND TempKw LIKE \"". DbCmd::EscapeSQL($thisKeyword) ."\"" );
			if($dbCmd->GetValue() == 0){
				$InsertArr["TempKw"]=$thisKeyword;
				$InsertArr["TemplateID"]=$TemplateID;
				$dbCmd->InsertQuery( "templatekeywords", $InsertArr );
			}
		}
	
	
	}
	
	
	
	static function LogTemplateKeywordSearch($dbCmd, $Keywords, $productid){
	
		$KeyWordStr = WebUtil::AlphabetizeKeywordSting($Keywords);
		
		// Make sure that it is not over the size limit
		if(strlen($KeyWordStr) >= 249)
			$KeyWordStr = substr($KeyWordStr, 0, 249);
			
		if(!empty($KeyWordStr)){
			$InsertArr["Keywords"] = $KeyWordStr;
			$InsertArr["ProductID"] = intval($productid);
			$dbCmd->InsertQuery( "templaterequests", $InsertArr );
		}
	}
	
}

?>

<?php

class VisitorPathQuery {
	
	private $_dbCmd;
	
	private $labelLimitersArr = array();
	private $labelInvalidatorsArr = array();
	
	private $pathLimitersArr = array();
	private $pathInvalidatorsArr = array();
	
	private $userIdLimiters = array();
	private $referrerKewordsLimiters = array();
	private $sessionIdLimiters = array();
	private $userAgentLimiters = array();
	private $ipAddressLimiters = array();
	private $minimumSessionDuration;
	private $maximumSessionDuration;
	private $minNodes;
	private $maxNodes;
	private $domainIDsArr = array();
	private $startTimeStamp;
	private $endTimeStamp;
	private $expandedLabelNamesArr = array();
	private $referralTypesArr = array();

	private $showNodesArr = array();
	
	private $visitorPathObj;
	
	// Often Time we may get the SessionIDs in multiple calls.  We can cache the results... as long as the search criteria doesn't change.
	private $sessionIDsCacheArr = array();
	private $sessionIDsCache_Flag = false;  // need a flag set... because having an empty array is also a valid "Cache Value".
	
	private $sessionIDsThroughPathsArr = array();
	private $labelCountsCacheArr = array();
	private $labelVisitorsCacheArr = array();
	private $hiddenLabelsCacheArr = array();
	
	
	
	function __construct() {
	
		$this->_dbCmd = new DbCmd();
		$this->clearLimiters();
		$this->visitorPathObj = new VisitorPath();

	}
	
	function setQueryParametersFromURL(){
		
		$this->clearLimiters();
		

		// 2 parallel arrays
		$mainLabelFilters = explode("|", WebUtil::GetInput("fMnLbl", FILTER_SANITIZE_STRING_ONE_LINE));
		$detailLabelFilters = explode("|", WebUtil::GetInput("fDtLbl", FILTER_SANITIZE_STRING_ONE_LINE));
		
		// 2 parallel arrays
		$mainLabelInvalidators = explode("|", WebUtil::GetInput("iMnLbl", FILTER_SANITIZE_STRING_ONE_LINE));
		$detailLabelInvalidators = explode("|", WebUtil::GetInput("iDtLbl", FILTER_SANITIZE_STRING_ONE_LINE));

		// 4 parallel arrays.
		$mainPathFiltersSource = explode("|", WebUtil::GetInput("fMnPthS", FILTER_SANITIZE_STRING_ONE_LINE));
		$detailPathFiltersSource = explode("|", WebUtil::GetInput("fDtPthS", FILTER_SANITIZE_STRING_ONE_LINE));
		$mainPathFiltersTarget = explode("|", WebUtil::GetInput("fMnPthT", FILTER_SANITIZE_STRING_ONE_LINE));
		$detailPathFiltersTarget = explode("|", WebUtil::GetInput("fDtPthT", FILTER_SANITIZE_STRING_ONE_LINE));
		
		// 4 parallel arrays.
		$mainPathInvalidatorsSource = explode("|", WebUtil::GetInput("iMnPthS", FILTER_SANITIZE_STRING_ONE_LINE));
		$detailPathInvalidatorsSource = explode("|", WebUtil::GetInput("iDtPthS", FILTER_SANITIZE_STRING_ONE_LINE));
		$mainPathInvalidatorsTarget = explode("|", WebUtil::GetInput("iMnPthT", FILTER_SANITIZE_STRING_ONE_LINE));
		$detailPathInvalidatorsTarget = explode("|", WebUtil::GetInput("iDtPthT", FILTER_SANITIZE_STRING_ONE_LINE));
			
		
		// Remaining Filters
		$userAgentFilters = explode("|", WebUtil::GetInput("fUsrAg", FILTER_SANITIZE_STRING_ONE_LINE));
		$ipAddressFilters = explode("|", WebUtil::GetInput("fIpAd", FILTER_SANITIZE_STRING_ONE_LINE));
		$referrerKeywordsFilters = explode("|", WebUtil::GetInput("fRefKw", FILTER_SANITIZE_STRING_ONE_LINE));
		$sessionIDsArr = explode("|", WebUtil::GetInput("sessionIDs", FILTER_SANITIZE_STRING_ONE_LINE));
		$userIDsArr = explode("|", WebUtil::GetInput("userIDs", FILTER_SANITIZE_STRING_ONE_LINE));
		$showNodesArr = explode("|", WebUtil::GetInput("showNds", FILTER_SANITIZE_STRING_ONE_LINE));
		$expandLabelsArr = explode("|", WebUtil::GetInput("expandLabels", FILTER_SANITIZE_STRING_ONE_LINE));
		$domainIDsArr = explode("|", WebUtil::GetInput("domainIDs", FILTER_SANITIZE_STRING_ONE_LINE));
		$refTypesArr = explode("|", WebUtil::GetInput("referrerType", FILTER_SANITIZE_STRING_ONE_LINE));

		
		
		// Filter out arrays with empty elements.
		// Also reset the arrays so they are Zero based if any of the nodes where empty.
		$sessionIDsArr = array_values(array_diff($sessionIDsArr, array("")));
		$userIDsArr = array_values(array_diff($userIDsArr, array("")));
		$expandLabelsArr = array_values(array_diff($expandLabelsArr, array("")));
		$userAgentFilters = array_values(array_diff($userAgentFilters, array("")));
		$ipAddressFilters = array_values(array_diff($ipAddressFilters, array("")));
		$referrerKeywordsFilters = array_values(array_diff($referrerKeywordsFilters, array("")));
		$domainIDsArr = array_values(array_diff($domainIDsArr, array("")));
		$refTypesArr = array_values(array_diff($refTypesArr, array("")));
		$showNodesArr = array_values(array_diff($showNodesArr, array("")));

			
		$start_timestamp = WebUtil::GetInput("startTime", FILTER_SANITIZE_INT);
		$end_timestamp = WebUtil::GetInput("endTime", FILTER_SANITIZE_INT);
		$start_timestamp = empty($start_timestamp) ? "" : $start_timestamp;
		$end_timestamp = empty($end_timestamp) ? "" : $end_timestamp;
		
		$minSessionMinutes = WebUtil::GetInput("minSess", FILTER_SANITIZE_INT);
		$maxSessionMinutes = WebUtil::GetInput("maxSess", FILTER_SANITIZE_INT);
		$minSessionMinutes = empty($minSessionMinutes) ? "" : $minSessionMinutes;
		$maxSessionMinutes = empty($maxSessionMinutes) ? "" : $maxSessionMinutes;
		
		$minNodes = WebUtil::GetInput("minNodes", FILTER_SANITIZE_INT);
		$maxNodes = WebUtil::GetInput("maxNodes", FILTER_SANITIZE_INT);
		
		$minNodes = empty($minNodes) ? "" : $minNodes;
		$maxNodes = empty($maxNodes) ? "" : $maxNodes;

		
		if(sizeof($mainLabelFilters) != sizeof($detailLabelFilters))
			throw new Exception("The sizes do no match for the Label Filters. Must be paralell arrays.");
		if(sizeof($mainLabelInvalidators) != sizeof($detailLabelInvalidators))
			throw new Exception("The sizes do no match for the Label Invalidators. Must be paralell arrays.");
		if(sizeof($mainPathFiltersSource) != sizeof($detailPathFiltersSource) || sizeof($mainPathFiltersSource) != sizeof($mainPathFiltersTarget) || sizeof($mainPathFiltersSource) != sizeof($detailPathFiltersTarget))
			throw new Exception("The sizes do no match for the Path Filters. Must be paralell arrays.");
		if(sizeof($mainPathInvalidatorsSource) != sizeof($detailPathInvalidatorsSource) || sizeof($mainPathInvalidatorsSource) != sizeof($mainPathInvalidatorsTarget) || sizeof($mainPathInvalidatorsSource) != sizeof($detailPathInvalidatorsTarget))
			throw new Exception("The sizes do no match for the Path Invalidators. Must be paralell arrays.");
		
		if(!empty($start_timestamp) && $start_timestamp > $end_timestamp)
			throw new Exception("Error with Timestamp. The value is out of range.");
			

		// Filter out domains that the user doesn't have permission to see.
		$authObj = new Authenticate(Authenticate::login_ADMIN);
		$filteredDomainsArr = array();
		foreach($domainIDsArr as $thisDomainId){
			
			if($authObj->CheckIfUserCanViewDomainID($thisDomainId))
				$filteredDomainsArr[] = $thisDomainId;
		}
		$this->domainIDsArr = $filteredDomainsArr;
			
		
		// Set the values in our Object
		for($i=0; $i<sizeof($mainLabelFilters); $i++)
			$this->addLabelLimiter($mainLabelFilters[$i], $detailLabelFilters[$i]);

		for($i=0; $i<sizeof($mainLabelInvalidators); $i++)
			$this->addLabelInvalidator($mainLabelInvalidators[$i], $detailLabelInvalidators[$i]);
			
		for($i=0; $i<sizeof($mainPathFiltersSource); $i++)
			$this->addPathLimiters($mainPathFiltersSource[$i], $detailPathFiltersSource[$i], $mainPathFiltersTarget[$i], $detailPathFiltersTarget[$i]);
			
		for($i=0; $i<sizeof($mainPathInvalidatorsSource); $i++)
			$this->addPathInvalidators($mainPathInvalidatorsSource[$i], $detailPathInvalidatorsSource[$i], $mainPathInvalidatorsTarget[$i], $detailPathInvalidatorsTarget[$i]);
			
			

		$this->limitToUserIDs($userIDsArr);
		$this->limitToMaximumSessionDuration($maxSessionMinutes * 60);
		$this->limitToMinimumSessionDuration($minSessionMinutes * 60);
		
		$this->limitToMinimumNodes($minNodes);
		$this->limitToMaximumNodes($maxNodes);
		
		$this->showNodes($showNodesArr);		
		
		$this->userAgentLimiters = $userAgentFilters;
		$this->ipAddressLimiters = $ipAddressFilters;
		$this->referrerKewordsLimiters = $referrerKeywordsFilters;
		$this->expandedLabelNamesArr = $expandLabelsArr;
		$this->limitToReferralTypes($refTypesArr);
		$this->sessionIdLimiters = $sessionIDsArr;

		$this->startTimeStamp = $start_timestamp;
		$this->endTimeStamp = $end_timestamp;
		
		
	}
	
	// Returns an array of Domain IDs that were either Set in the Object (or sent through the URL).
	// This object makes sure that the user has permission to see the Domains.
	// If no domains were sent in the URL, or the object, then this method will return all of the domains the user selected within cookies.
	function getDomainIDs(){
		
		if(empty($this->domainIDsArr)){
			$domainObj = Domain::singleton();
			return $domainObj->getSelectedDomainIDs();
		}
		else{
			return $this->domainIDsArr;
		}
		
	}
	
	// Returns a Query string to go after a question mark in the URL.
	function getUrlQueryString(){

		$chartQueryString = "startTime=" . $this->getStartTimeStamp() . "&endTime=" . $this->getEndTimeStamp();
		$chartQueryString .= "&minSess=" .  ($this->getMinSessionDurationSeconds() / 60) . "&maxSess=" . ($this->getMaxSessionDurationSeconds() / 60);
		$chartQueryString .= "&minNodes=" .  $this->getMinimumNodes()  . "&maxNodes=" . $this->getMaximumNodes();
		
		$chartQueryString .= "&fUsrAg=" . implode("|", array_map("urlencode", $this->getUserAgentLimiters()));
		$chartQueryString .= "&fIpAd=" . implode("|", array_map("urlencode", $this->getIpAddressLimiters()));
		$chartQueryString .= "&fRefKw=" . implode("|", array_map("urlencode", $this->getReferrerKewordsLimiters()));
		$chartQueryString .= "&expandLabels=" . implode("|", array_map("urlencode", $this->getExpandedLabelNames()));
		$chartQueryString .= "&userIDs=" . implode("|", array_map("urlencode", $this->getUserIDlimiters()));
		$chartQueryString .= "&sessionIDs=" . implode("|", array_map("urlencode", $this->getSessionIdLimiters()));
		$chartQueryString .= "&domainIDs=" . implode("|", array_map("urlencode", $this->getDomainIDs()));
		$chartQueryString .= "&showNds=" . implode("|", array_map("urlencode", $this->getNodesShown()));
		$chartQueryString .= "&referrerType=" . implode("|", array_map("urlencode", $this->getReferralTypes()));

		
		
		// 2 parallel arrays.
		$chartQueryString .= "&fMnLbl=" . implode("|", array_map("urlencode", $this->getMainLabelLimiters()));
		$chartQueryString .= "&fDtLbl=" . implode("|", array_map("urlencode", $this->getDetailLabelLimiters()));
		
		// 2 parallel arrays.
		$chartQueryString .= "&iMnLbl=" . implode("|", array_map("urlencode", $this->getMainLabelInvalidators()));
		$chartQueryString .= "&iDtLbl=" . implode("|", array_map("urlencode", $this->getDetailLabelInvalidators()));
		
		// 4 parallel arrays.
		$chartQueryString .= "&fMnPthS=" . implode("|", array_map("urlencode", $this->getPathLimitersSourceMain()));
		$chartQueryString .= "&fDtPthS=" . implode("|", array_map("urlencode", $this->getPathLimitersSourceDetail()));
		$chartQueryString .= "&fMnPthT=" . implode("|", array_map("urlencode", $this->getPathLimitersTargetMain()));
		$chartQueryString .= "&fDtPthT=" . implode("|", array_map("urlencode", $this->getPathLimitersTargetDetail()));
		
		// 4 parallel arrays.
		$chartQueryString .= "&iMnPthS=" . implode("|", array_map("urlencode", $this->getPathInvalidatorsSourceMain()));
		$chartQueryString .= "&iDtPthS=" . implode("|", array_map("urlencode", $this->getPathInvalidatorsSourceDetail()));
		$chartQueryString .= "&iMnPthT=" . implode("|", array_map("urlencode", $this->getPathInvalidatorsTargetMain()));
		$chartQueryString .= "&iDtPthT=" . implode("|", array_map("urlencode", $this->getPathInvalidatorsTargetDetail()));
		


		return $chartQueryString;
	}
	
	// Saves sapce with IE's 2048 limit on URL length.
	// Also encoding the parameters prevents some bugs with Graph Viz rendering certain characters within an HTML href.
	function getUrlQueryEncoded(){
	
		return base64_encode(gzdeflate($this->getUrlQueryString(), 9));
	}
	
	
	// --------------  Label Limiters ------------
	
	// The following 2 methods return parallel arrays to each other.
	function getMainLabelLimiters(){
		$retArr = array();
		foreach($this->labelLimitersArr as $limitHash)
			$retArr[] = $limitHash["label"];
		return $retArr;
	}
	function getDetailLabelLimiters(){
		$retArr = array();
		foreach($this->labelLimitersArr as $limitHash)
			$retArr[] = $limitHash["detail"];
		return $retArr;
	}
	
	
	// --------------  Label Invalidators ------------
	
	// The following 2 methods return parallel arrays to each other.
	function getMainLabelInvalidators(){
		$retArr = array();
		foreach($this->labelInvalidatorsArr as $limitHash)
			$retArr[] = $limitHash["label"];
		return $retArr;
	}
	function getDetailLabelInvalidators(){
		$retArr = array();
		foreach($this->labelInvalidatorsArr as $limitHash)
			$retArr[] = $limitHash["detail"];
		return $retArr;	
	}
	
	
	
	
	// --------  Path Limiters --------------------
	
	// The following 4 methods return parallel arrays to each other.
	// See comments on addPathLimiters to see what the path limiters are for.
	function getPathLimitersSourceMain(){
		$retArr = array();
		foreach($this->pathLimitersArr as $limitHash)
			$retArr[] = $limitHash["SourceMainLabel"];
		return $retArr;
	}
	// Parellel to the main limiters. Will include empty entries if Detailed Label was not defined.
	function getPathLimitersSourceDetail(){
		$retArr = array();
		foreach($this->pathLimitersArr as $limitHash)
			$retArr[] = $limitHash["SourceDetailLabel"];
		return $retArr;
	}
	function getPathLimitersTargetMain(){
		$retArr = array();
		foreach($this->pathLimitersArr as $limitHash)
			$retArr[] = $limitHash["TargetMainLabel"];
		return $retArr;
	}
	function getPathLimitersTargetDetail(){
		$retArr = array();
		foreach($this->pathLimitersArr as $limitHash)
			$retArr[] = $limitHash["TargetDetailLabel"];
		return $retArr;
	}
	
	
	
	
	// --------  Path Invalidators --------------------
	
	// The following 4 methods return parallel arrays to each other.
	// See comments on addPathInvalidators to see what the path limiters are for.
	function getPathInvalidatorsSourceMain(){
		$retArr = array();
		foreach($this->pathInvalidatorsArr as $limitHash)
			$retArr[] = $limitHash["SourceMainLabel"];
		return $retArr;
	}
	// Parellel to the main limiters. Will include empty entries if Detailed Label was not defined.
	function getPathInvalidatorsSourceDetail(){
		$retArr = array();
		foreach($this->pathInvalidatorsArr as $limitHash)
			$retArr[] = $limitHash["SourceDetailLabel"];
		return $retArr;
	}
	function getPathInvalidatorsTargetMain(){
		$retArr = array();
		foreach($this->pathInvalidatorsArr as $limitHash)
			$retArr[] = $limitHash["TargetMainLabel"];
		return $retArr;
	}
	function getPathInvalidatorsTargetDetail(){
		$retArr = array();
		foreach($this->pathInvalidatorsArr as $limitHash)
			$retArr[] = $limitHash["TargetDetailLabel"];
		return $retArr;
	}
	
	
	
	
	

	
	function getUserIDlimiters(){
		return $this->userIdLimiters;
	}
	
	function getExpandedLabelNames(){
		return $this->expandedLabelNamesArr;
	}
	
	function getSessionIdLimiters(){
		return $this->sessionIdLimiters;
	}
	
	
	function getStartTimeStamp(){
		return $this->startTimeStamp;
	}
	
	function getEndTimeStamp(){
		return $this->endTimeStamp;
	}
	
	function getMaxSessionDurationSeconds(){
		return $this->maximumSessionDuration;
	}
	
	function getMinimumNodes(){
		return $this->minNodes;
	}
	function getMaximumNodes(){
		return $this->maxNodes;
	}
	
	function getMinSessionDurationSeconds(){
		return $this->minimumSessionDuration;
	}
	
	function getUserAgentLimiters(){
		return $this->userAgentLimiters;
	}
	function getIpAddressLimiters(){
		return $this->ipAddressLimiters;
	}
	
	function getReferrerKewordsLimiters(){
		return $this->referrerKewordsLimiters;
	}
	
	function getNodesShown(){
		return $this->showNodesArr;
	}
	
	// Returns an array of Nodes that existed in from the results of query, but they were not present within the $this->showNodesArr
	// It only returns "Main Label" names.
	function getHiddenNodes(){
		
		if(!empty($this->hiddenLabelsCacheArr))
			return $this->hiddenLabelsCacheArr;
			
		$retArr = array();
	
		$mainLabelNames = array_keys($this->getMainLabelCounts());
	
		foreach($mainLabelNames as $thisMainLabel){
			if(!in_array($thisMainLabel, $this->showNodesArr))
				$retArr[] = $thisMainLabel;
		}
		
		$this->hiddenLabelsCacheArr = $retArr;
		
		return $retArr;
	}

	function clearLimiters(){
		$this->labelLimitersArr = array();
		$this->labelInvalidatorsArr = array();
		$this->pathLimitersArr = array();
		$this->pathInvalidatorsArr = array();
		$this->userIdLimiters = array();
		$this->showNodesArr = array();
		$this->sessionIdLimiters = array();
		$this->userAgentLimiters = array();
		$this->ipAddressLimiters = array();
		$this->referrerKewordsLimiters = array();
		$this->expandedLabelNamesArr = array();
		$this->referralTypesArr = array("O", "B", "L", "U", "S");
		$this->minimumSessionDuration = null;
		$this->maximumSessionDuration = null;
		$this->startTimeStamp = null;
		$this->endTimeStamp = null;
		$this->domainIDsArr = array();
		
		$this->clearSessionCache();
	}
	
	private function clearSessionCache(){

		$this->sessionIDsCacheArr = array();
		$this->sessionIDsCache_Flag = false;
		
		$this->sessionIDsThroughPathsArr = array();
		$this->labelCountsCacheArr = array();
		$this->labelVisitorsCacheArr = array();
		$this->hiddenLabelsCacheArr = array();

	}
	
	function limitToDomainIDs($domainIDsArr){
		
		// Filter out domains that the user doesn't have permission to see.
		$authObj = new Authenticate(Authenticate::login_ADMIN);
		$filteredDomainsArr = array();
		foreach($domainIDsArr as $thisDomainId){
			
			if($authObj->CheckIfUserCanViewDomainID($thisDomainId))
				$filteredDomainsArr[] = $thisDomainId;
		}
		$this->domainIDsArr = $filteredDomainsArr;
		
	}
	
	
	// Pass in an array of user IDs
	function limitToUserIDs(array $userIDs){
		
		// Filter input
		$tempArr = array();
		foreach($userIDs as $userID){
			
			// User ID's can have "U" has a prefix.
			$userID = preg_replace("/^u/i", "", $userID);
			
			$userID = intval($userID);
			if(!empty($userID))
				$tempArr[] = $userID;
		}
		
		$this->userIdLimiters = $tempArr;
		
		$this->clearSessionCache();
	}
	
	function limitToSessionIDs(array $sessionIDs){
		
		// Filter input
		$tempArr = array();
		foreach($sessionIDs as $sessID){
			if(!empty($sessID))
				$tempArr[] = $sessID;
		}
		
		$this->sessionIdLimiters = $tempArr;
		
		$this->clearSessionCache();
	}
	
	// The more often you call this method with different label names... the more difficult it will be to find a visitor path.
	// It successively adds limiters for node names that must exist within a Visitor Path in order to find a match.
	function addLabelLimiter($mainLabelName, $labelDetail = null){
		
		$mainLabelName = trim(preg_replace("/\s-\s/", "", $mainLabelName));
		
		// Start and End are reseved label names.
		if($mainLabelName == "Start" || $mainLabelName == "End")
			return;
		
		if(strlen($mainLabelName) > 255 || strlen($labelDetail) > 255)
			throw new Exception("Erorr with Label Name Lengths.");
			
		if(empty($mainLabelName))
			return;
			
		// Find out if the parameter was already included... so we don't make a URL's too long.
		foreach($this->labelLimitersArr as $labelHash){
			if($labelHash["label"] == $mainLabelName && $labelHash["detail"] == $labelDetail)
				return;
		}
			
		$this->labelLimitersArr[] = array("label"=>$mainLabelName, "detail"=>$labelDetail);
		
		$this->clearSessionCache();
	}
	
	
	// Keep calling this method to successively create label limiters that invalidate a visitor path from being included.
	// For example... pass in "home" if you want to make sure that all Visitor Paths going trough "home" do not get returned in the query.
	function addLabelInvalidator($mainLabelName, $labelDetail = null){
		
		$mainLabelName = trim(preg_replace("/\s-\s/", "", $mainLabelName));
		
		// Start and End are reseved label names.
		if($mainLabelName == "Start" || $mainLabelName == "End")
			return;
		
		if(strlen($mainLabelName) > 255 || strlen($labelDetail) > 255)
			throw new Exception("Erorr with Label Name Lengths.");
			
		if(empty($mainLabelName))
			return;
			
		// Find out if the parameter was already included... so we don't make a URL's too long.
		foreach($this->labelInvalidatorsArr as $labelHash){
			if($labelHash["label"] == $mainLabelName && $labelHash["detail"] == $labelDetail)
				return;
		}
			
		$this->labelInvalidatorsArr[] = array("label"=>$mainLabelName, "detail"=>$labelDetail);
		
		$this->clearSessionCache();
	}
	
	
	
	// If you add a limiter it means that if a user's session doesn't pass directly between those 2 labels, then it won't be included.
	// The more Path Limiters that you add, the more likely you are to find matches.
	// The Detail is left blank if you don't want to restrict based on the Detail Label.
	function addPathLimiters($sourceMainLabel, $sourceDeailLabel, $targetMainLabel, $targetDetailLabel){
		
		// Space Dash Space ... is what we separate Main Labels from Details on.
		$sourceMainLabel = trim(preg_replace("/\s-\s/", "", $sourceMainLabel));
		$targetDetailLabel = trim(preg_replace("/\s-\s/", "", $targetDetailLabel));
		
		if(empty($sourceMainLabel) || empty($targetMainLabel))
			return;
			
		// Find out if the parameter was already included... so we don't make a URL's too long.
		foreach($this->pathLimitersArr as $labelHash){
			if($labelHash["SourceMainLabel"] == $sourceMainLabel && $labelHash["SourceDetailLabel"] == $sourceDeailLabel && $labelHash["TargetMainLabel"] == $targetMainLabel && $labelHash["TargetDetailLabel"] == $targetDetailLabel)
				return;
		}
		
		$entryHash = array("SourceMainLabel"=>$sourceMainLabel, "SourceDetailLabel"=>$sourceDeailLabel, "TargetMainLabel"=>$targetMainLabel, "TargetDetailLabel"=>$targetDetailLabel);
			
		$this->pathLimitersArr[] = $entryHash;
	}
	
	// The more path invalidators you add, the harder it will become to find matches.
	// If a user's session DOES pass directly between those 2 lables, then it won't be included.
	// The Detail is left blank if you don't want to restrict based on the Detail Label.
	function addPathInvalidators($sourceMainLabel, $sourceDeailLabel, $targetMainLabel, $targetDetailLabel){
		
		$sourceMainLabel = preg_replace("/\s-\s/", "", $sourceMainLabel);
		$targetDetailLabel = preg_replace("/\s-\s/", "", $targetDetailLabel);
		
		if(empty($sourceMainLabel) || empty($targetMainLabel))
			return;
		
		// Find out if the parameter was already included... so we don't make a URL's too long.
		foreach($this->pathInvalidatorsArr as $labelHash){
			if($labelHash["SourceMainLabel"] == $sourceMainLabel && $labelHash["SourceDetailLabel"] == $sourceDeailLabel && $labelHash["TargetMainLabel"] == $targetMainLabel && $labelHash["TargetDetailLabel"] == $targetDetailLabel)
				return;
		}
			
		$entryHash = array("SourceMainLabel"=>$sourceMainLabel, "SourceDetailLabel"=>$sourceDeailLabel, "TargetMainLabel"=>$targetMainLabel, "TargetDetailLabel"=>$targetDetailLabel);
			
		$this->pathInvalidatorsArr[] = $entryHash;
	}
	
	
	
	// Pass in an array of user agents. The more user agents you include, the better chance of finding matches.
	// You can use "*" wildcard matches.
	function limitUserAgents(array $userAgents){
			
		$this->userAgentLimiters = $userAgents;
		
		$this->clearSessionCache();
	}
	
	function limitIpAddresses(array $ipAddressesArr){
			
		$this->ipAddressLimiters = $ipAddressesArr;
		
		$this->clearSessionCache();
	}
	
	// Pass in an array of user keyword limiters to search for within the HTTP Referrer field.
	// You can use "*" wildcard matches.
	function limitReferrerByKeywords(array $keywordsArr){
			
		$this->referrerKewordsLimiters = $keywordsArr;
		
		$this->clearSessionCache();
	}
	
	function setExpandedLabelNames(array $labelNamesArr){
			
		$this->expandedLabelNamesArr = $labelNamesArr;
	}
	
	
	function limitDateRange($startTimestamp, $endTimestamp){
		
		$startTimestamp = intval($startTimestamp);
		$endTimestamp = intval($endTimestamp);
		
		if(empty($startTimestamp) && empty($endTimestamp))
			return;
		
		if($startTimestamp > $endTimestamp)
			throw new Exception("The date range is invalid within method getSessionIDs");
			
		if(empty($startTimestamp) || empty($endTimestamp))
			throw new Exception("If you are limiting the Date Range, both values must be populated.");
			
		$this->startTimeStamp = $startTimestamp;
		$this->endTimeStamp = $endTimestamp;
	
		$this->clearSessionCache();
	}
	
	// Pass in the number of seconds required for the user to spend on the site.
	function limitToMinimumSessionDuration($minSessionSeconds){
		
		$this->minimumSessionDuration = intval($minSessionSeconds);
		
		$this->clearSessionCache();
		
	}
	function limitToMaximumSessionDuration($maxSessionSeconds){
		
		$this->maximumSessionDuration = intval($maxSessionSeconds);
		
		$this->clearSessionCache();
	}
	
	// Pass number of nodes within a users session that you want to limit the count to.
	function limitToMinimumNodes($minNodes){
		
		$this->minNodes = intval($minNodes);
		
		$this->clearSessionCache();
		
	}
	function limitToMaximumNodes($maxNodes){
		
		$this->maxNodes = intval($maxNodes);
		
		$this->clearSessionCache();
	}
	

	function limitToReferralTypes($reffTypesArr){

		
		foreach($reffTypesArr as $thisRefType){
			if(!in_array($thisRefType, array("O", "B", "L", "U", "S")))
				throw new Exception("Illegal Referaly times");
		}
		
		$this->referralTypesArr = $reffTypesArr;
		
		$this->clearSessionCache();
	}
	
	
	function getReferralTypes(){
		
		return $this->referralTypesArr;

	}
	
	function showNodes(array $nodeNamesArr){
		sort($nodeNamesArr);
		$this->showNodesArr = $nodeNamesArr;
	}
	
	// Pass in a start and end timestamp.
	// Depending on the limiters that were set in this object, this method will return an array of Session ID's during the period that match.
	function getSessionIDs(){
		
		if(empty($this->userIdLimiters) && empty($this->sessionIdLimiters) && empty($this->startTimeStamp) && empty($this->ipAddressLimiters))
			throw new Exception("Query is too broad. If you don't supply a date range, then you must either supply a list of User ID's or Session ID's to limit the query to.");
		
		if($this->sessionIDsCache_Flag)
			return $this->sessionIDsCacheArr;
			
		$useIndexClause = "";
		if(!empty($this->startTimeStamp))
			$useIndexClause = "USE INDEX (visitorsessiondetails_DateStarted) ";
		
		$query = "SELECT visitorsessiondetails.SessionID FROM visitorsessiondetails $useIndexClause
				INNER JOIN visitorlog ON visitorsessiondetails.SessionID = visitorlog.SessionID 
				WHERE " . DbHelper::getOrClauseFromArray("DomainID", $this->getDomainIDs());
		
		if(!empty($this->startTimeStamp))
			$query .= " AND DateStarted BETWEEN '" . DbCmd::convertUnixTimeStmpToMysql($this->startTimeStamp) . "' AND '" . DbCmd::convertUnixTimeStmpToMysql($this->endTimeStamp) . "' "; 
		
		if(!empty($this->userIdLimiters))
			$query .= " AND " . DbHelper::getOrClauseFromArray("UserID", $this->userIdLimiters);
		
		if(!empty($this->sessionIdLimiters))
			$query .= " AND " . DbHelper::getOrClauseFromArray("visitorsessiondetails.SessionID", $this->sessionIdLimiters);
			
		if(!empty($this->referralTypesArr))
			$query .= " AND " . DbHelper::getOrClauseFromArray("visitorsessiondetails.ReferralType", $this->referralTypesArr);
			
		if(!empty($this->ipAddressLimiters)){
			$query .= " AND " . DbHelper::getOrClauseFromArray("visitorsessiondetails.IPaddress", $this->ipAddressLimiters);
		}

		if(!empty($this->userAgentLimiters)){
			
			$uaSqlLimit = "";
			
			foreach($this->userAgentLimiters as $thisUserAgent){
				
				if(empty($thisUserAgent))
					continue;
					
				if(!empty($uaSqlLimit))
					$uaSqlLimit .= " OR ";
					
				$thisUserAgent = DbCmd::EscapeLikeQuery($thisUserAgent);
				
				// Turn the * astric character into a MySQL wildcard.
				$thisUserAgent = preg_replace("/\\*/", "%", $thisUserAgent);
					
				$uaSqlLimit .= " UserAgent LIKE '%" . $thisUserAgent . "%' ";
			}
			
			if(!empty($uaSqlLimit))
				$query .= " AND (" . $uaSqlLimit . ") ";
		}
		
		
		
		if(!empty($this->referrerKewordsLimiters)){
			
			$refKwSqlLimit = "";
			
			foreach($this->referrerKewordsLimiters as $thisRefKw){
				
				if(empty($thisRefKw))
					continue;
					
				if(!empty($refKwSqlLimit))
					$refKwSqlLimit .= " OR ";
					
				$thisRefKw = DbCmd::EscapeLikeQuery($thisRefKw);
				
				// Turn the * astric character into a MySQL wildcard.
				$thisRefKw = preg_replace("/\\*/", "%", $thisRefKw);
					
				$refKwSqlLimit .= " RefURL LIKE '%" . $thisRefKw . "%' ";
			}
			
			if(!empty($refKwSqlLimit))
				$query .= " AND (" . $refKwSqlLimit . ") ";
		}
			
		
		
		if(!empty($this->maximumSessionDuration))
			$query .= " AND (UNIX_TIMESTAMP(DateLastAccess) - UNIX_TIMESTAMP(DateStarted) < $this->maximumSessionDuration ) ";
			
		if(!empty($this->minimumSessionDuration))
			$query .= " AND (UNIX_TIMESTAMP(DateLastAccess) - UNIX_TIMESTAMP(DateStarted) > $this->minimumSessionDuration ) ";
		

		$this->_dbCmd->Query($query);
		
		$sessionIDsArr =  $this->_dbCmd->GetValueArr();	
		
		// Remove duplicate ID's from the result set with PHP.
		// Using a DISTINCT clause in MySQL will forc Temp Table.
		// That was having a serious consequence on performance on regular users of the site. They had "locked" queries on their "Page visits" while a big query finished in the backend-reports.
		// This is a big limitation of MyISAM.  InnoDB is not supposed to lock up the other read queries during  "tmp table" operation.
		$sessionIDsArr = array_unique($sessionIDsArr);
		
		/*
		// Limit to only people which have visited PME and BC24 together.
		$filterArr = array();
		foreach($sessionIDsArr as $thisSessionID){
			
			$this->_dbCmd->Query("SELECT IPaddress FROM visitorsessiondetails WHERE SessionID='".DbCmd::EscapeLikeQuery($thisSessionID)."'");
			$thisIPaddress = $this->_dbCmd->GetValue();
			

			$this->_dbCmd->Query("SELECT DomainID FROM visitorsessiondetails WHERE IPaddress='".DbCmd::EscapeLikeQuery($thisIPaddress)."'");
			$totalDomains = $this->_dbCmd->GetValueArr();
			
			if(in_array(1, $totalDomains) && in_array(10, $totalDomains))
				$filterArr[] = $thisSessionID;
		}
		
		$sessionIDsArr = $filterArr;
		
		*/
		
		// Filter based upon search criteria stored in this VisiterQuery object.
		// We have to programatically filter our intial Session Pool, since there is no fancy way to do so in SQL.
		$sessionIDsArr = $this->filterSessionIdsByLabelLimiters($sessionIDsArr);
		$sessionIDsArr = $this->filterSessionIdsByLabelInvalidators($sessionIDsArr);
		$sessionIDsArr = $this->filterSessionIdsByPathLimiters($sessionIDsArr);
		$sessionIDsArr = $this->filterSessionIdsByPathInvalidators($sessionIDsArr);
		$sessionIDsArr = $this->filterSessionNodeCounts($sessionIDsArr);

			
		$this->sessionIDsCacheArr = $sessionIDsArr;
		$this->sessionIDsCache_Flag = true;
		
		return $sessionIDsArr;
	}
	
	// Pass in an array of session IDs.
	// This will return on the session IDs that meet fall within the minimum and maximum node requirements.
	function filterSessionNodeCounts(array $sessionIDsToFilter){
		
		if(empty($this->minNodes) && empty($this->maxNodes))
			return $sessionIDsToFilter;
			
		$returnSessionsArr = array();
		
		foreach($sessionIDsToFilter as $thisSessionID){
			
			$this->_dbCmd->Query("SELECT COUNT(*) FROM visitorlog WHERE SessionID='".DbCmd::EscapeSQL($thisSessionID)."'");
			$countOfNodes = $this->_dbCmd->GetValue();
			
			if(!empty($this->minNodes)){
				if($countOfNodes < $this->minNodes)
					continue;
			}
			if(!empty($this->maxNodes)){
				if($countOfNodes > $this->maxNodes)
					continue;
			}
			
			$returnSessionsArr[] = $thisSessionID;
		}
		
		return $returnSessionsArr;
	}
	
	// Pass in an array of session IDs.
	// This will return on the session IDs that meet the "Label Filter" criteria stored in this object
	// Then returns the Session ID array (possibly smaller).
	// Label Filters AND Label Invalidators do not care if Nodes are "hidden" or not.
	function filterSessionIdsByLabelLimiters(array $sessionIDsToFilter){

		if(empty($this->labelLimitersArr))
			return $sessionIDsToFilter;
			
		$returnSessionsArr = array();
		
		foreach($sessionIDsToFilter as $thisSessionID){
					
			$allLabelsFoundFlag = true;
			
			// The the Session ID doesn't go through all of the Labels in our Filter... then we can't include it.
			foreach($this->labelLimitersArr as $labelHash){
				
				$thisMainLabelName = $labelHash["label"];
				$thisDetailLabelName = $labelHash["detail"];
				
				$thisMainLabelName = DbCmd::EscapeLikeQuery($thisMainLabelName);
				// Allow the * to be a wildcard.
				$thisMainLabelName = preg_replace("/\\*/", "%", $thisMainLabelName);
				
				$query = "SELECT COUNT(*) FROM visitorlog WHERE SessionID='".DbCmd::EscapeSQL($thisSessionID)."' AND VisitLabel LIKE '".$thisMainLabelName."'";
				
				if(!empty($thisDetailLabelName)){
					$thisDetailLabelName = DbCmd::EscapeLikeQuery($thisDetailLabelName);
					// Allow the * to be a wildcard.
					$thisDetailLabelName = preg_replace("/\\*/", "%", $thisDetailLabelName);
					$query .= " AND VisitLabelDetail LIKE '".$thisDetailLabelName."'";
				}
				
				$this->_dbCmd->Query($query);
				$labelCount = $this->_dbCmd->GetValue();
				
				if($thisMainLabelName == "Banner Click"){
					//if($labelCount <= 1){
					if($labelCount == 0){
						$allLabelsFoundFlag = false;
						break;
					}
				}
				else{
					if($labelCount == 0){
						$allLabelsFoundFlag = false;
						break;
					}
				}
			}
			
			if($allLabelsFoundFlag)
				$returnSessionsArr[] = $thisSessionID;
		}
		
		return $returnSessionsArr;

	}
	
	// Pass in an array of session IDs.
	// This will return on the session IDs that meet the "Label Filter" criteria stored in this object
	// Then returns the Session ID array (possibly smaller).
	// Label Filters AND Label Invalidators do not care if Nodes are "hidden" or not.
	function filterSessionIdsByLabelInvalidators(array $sessionIDsToFilter){

		if(empty($this->labelInvalidatorsArr))
			return $sessionIDsToFilter;
			
		$returnSessionsArr = array();
		
		foreach($sessionIDsToFilter as $thisSessionID){
					
			$invalidatorOrClause = "";
			
			foreach($this->labelInvalidatorsArr as $labelHash){
				
				$thisMainLabelName = $labelHash["label"];
				$thisDetailLabelName = $labelHash["detail"];
				
				$thisMainLabelName = DbCmd::EscapeLikeQuery($thisMainLabelName);
				// Allow the * to be a wildcard.
				$thisMainLabelName = preg_replace("/\\*/", "%", $thisMainLabelName);
				
				if(!empty($invalidatorOrClause))
					$invalidatorOrClause .= " OR ";

				$invalidatorOrClause .= "(";
					
				$invalidatorOrClause .= "VisitLabel LIKE '".$thisMainLabelName."' ";
				
				if(!empty($thisDetailLabelName)){
					$thisDetailLabelName = DbCmd::EscapeLikeQuery($thisDetailLabelName);
					// Allow the * to be a wildcard.
					$thisDetailLabelName = preg_replace("/\\*/", "%", $thisDetailLabelName);
					$invalidatorOrClause .= " AND VisitLabelDetail LIKE '".DbCmd::EscapeSQL($thisDetailLabelName)."'";
				}
				
				$invalidatorOrClause .= ")";
				
			}

			// If there is even one record that matches any of our invalidator labels... then we can't add to our returned Session IDs.
			$this->_dbCmd->Query("SELECT COUNT(*) FROM visitorlog WHERE SessionID='".DbCmd::EscapeSQL($thisSessionID)."' AND ( $invalidatorOrClause )");
			$invalidRowsCount = $this->_dbCmd->GetValue();
			
			if($invalidRowsCount == 0)
				$returnSessionsArr[] = $thisSessionID;
		}
		
		return $returnSessionsArr;

	}
	
	
	// Pass in an array of session IDs.
	// This will return an array of IP addresses that match up to the session IDs
	function getIpAddressesFromSessionIDs(array $sessionIDs){

		$returnArr = array();
		foreach($sessionIDs as $thisSessionID){
			$this->_dbCmd->Query("SELECT IPaddress FROM visitorsessiondetails WHERE SessionID='".DbCmd::EscapeSQL($thisSessionID)."'");
			$returnArr[] = $this->_dbCmd->GetValue();
		}
		
		return $returnArr;
	}
	
	
	// Returns the First Node in the Users's session that is not "hidden".
	// If all of the Labels are hidden, then it will return NULL.
	// Otherwise, returns a hash with 2 labels... array("label"=>$mainLabelName, "detail"=>$labelDetail); 
	function getStartNode($sessionID){
		
		// We won't find a Start Node if all of the nodes are hidden.
		if(empty($this->showNodesArr))
			return null;
		
		$this->_dbCmd->Query("SELECT VisitLabel, VisitLabelDetail FROM visitorlog WHERE SessionID='".DbCmd::EscapeSQL($sessionID)."' 
							AND ".DbHelper::getOrClauseFromArray("VisitLabel", $this->showNodesArr)." ORDER BY ID ASC LIMIT 1");	
		$row = $this->_dbCmd->GetRow();
			
		if(empty($row))
			return null;	
			
		$retHash['label'] = $row["VisitLabel"];
		$retHash['detail'] = $row["VisitLabelDetail"];
		
		return $retHash;
	}
	
	// Returns the Last Node in the Users's session that is not "hidden".
	// If all of the Labels are hidden, then it will return NULL.
	// Otherwise, returns a hash with 2 labels... array("label"=>$mainLabelName, "detail"=>$labelDetail); 
	function getEndNode($sessionID){
		
		// We won't find a Start Node if all of the nodes are hidden.
		if(empty($this->showNodesArr))
			return null;
		
		$this->_dbCmd->Query("SELECT VisitLabel, VisitLabelDetail FROM visitorlog WHERE SessionID='".DbCmd::EscapeSQL($sessionID)."' 
							AND ".DbHelper::getOrClauseFromArray("VisitLabel", $this->showNodesArr)." ORDER BY ID DESC LIMIT 1");	
		$row = $this->_dbCmd->GetRow();
			
		if(empty($row))
			return null;	
			
		$retHash['label'] = $row["VisitLabel"];
		$retHash['detail'] = $row["VisitLabelDetail"];
		
		return $retHash;
	}
	
	// Pass in an array of session IDs.
	// This will return on the session IDs that meet the "Path Filter" criteria stored in this object
	// Then returns the Session ID array (possibly smaller).
	function filterSessionIdsByPathLimiters(array $sessionIDsToFilter){

		if(empty($this->pathLimitersArr))
			return $sessionIDsToFilter;
			
		$returnSessionsArr = array();
		
		foreach($sessionIDsToFilter as $thisSessionID){
				
			$allPathLimtersFoundFlag = true;

			// If the the Main Label source or Main Label target is hidden for any of the Path Limiters...
			// ... then it is impossible to find a matching pathway for the limiter.	
			foreach($this->pathLimitersArr as $labelHash){
				
				if($labelHash["SourceMainLabel"] != "Start" && !in_array($labelHash["SourceMainLabel"], $this->showNodesArr) ){
						$allPathLimtersFoundFlag = false;
						break;	
				}
				
				if($labelHash["TargetMainLabel"] != "End" && !in_array($labelHash["TargetMainLabel"], $this->showNodesArr) ){
						$allPathLimtersFoundFlag = false;
						break;	
				}
			}
				
			
			foreach($this->pathLimitersArr as $labelHash){
	
				// In case we invalidated the Session before checking out each path limiter.
				if(!$allPathLimtersFoundFlag)
					break;
	
				$thisMainLabelSource = $labelHash["SourceMainLabel"];
				$thisDetailLabelSource = $labelHash["SourceDetailLabel"];
				$thisMainLabelTarget = $labelHash["TargetMainLabel"];
				$thisDetailLabelTarget = $labelHash["TargetDetailLabel"];

				
				// If the Main Label is "Start" or "End"... then we will have a match for every session.
				// These are special cases.
				if($thisMainLabelSource == "Start" || $thisMainLabelTarget == "End"){
					
					if($thisMainLabelSource == "Start")
						$tailEndLabelHash = $this->getStartNode($thisSessionID);
					else 
						$tailEndLabelHash = $this->getEndNode($thisSessionID);	
						

					// If the $tailEndLabelHash is empty... then it means all nodes are hidden within the session.
					// So if the Path Limiter is Start->End ... and all nodes are Hidden... then it means we found a match.
					if($thisMainLabelSource == "Start" && $thisMainLabelTarget == "End"){
						if(empty($tailEndLabelHash)){
							continue;
						}
					}
					
					// In case all of the nodes are hidden... then we won't have any Start or Endnodes... so therfore it is impossible for the session to go through the path.
					if(empty($tailEndLabelHash)){
						$allPathLimtersFoundFlag = false;
						break;
					}
					
					$tailEndVisitLabel = $tailEndLabelHash["label"];
					$tailEndVisitDetail = $tailEndLabelHash["detail"];
					
					
					// If we are at the "End Node"... then reverse the Source and the Target Nodes
					if($thisMainLabelTarget == "End"){
						$tempMain = $thisMainLabelSource;
						$tempDet = $thisDetailLabelSource;
						$thisMainLabelSource = $thisMainLabelTarget;
						$thisDetailLabelSource = $thisDetailLabelTarget;
						$thisMainLabelTarget = $tempMain;
						$thisDetailLabelTarget = $tempDet;
					}
					
					// If we can't match the Main Label then no point in Checking the Detailed Label.
					if($tailEndVisitLabel != $thisMainLabelTarget){
						$allPathLimtersFoundFlag = false;
						break;
					}
						
					// Find out if we are required to match the Detailed label as well.
					if(empty($thisDetailLabelTarget)){
						// We found a match for a starting path... but we have to continue checking other paths (if set).
						continue;
					}
					else{
						// Find out if the Prefix matches on our Tail-End label.							
						if(!preg_match("/^".preg_quote($thisDetailLabelTarget)."/", $tailEndVisitDetail)){
							$allPathLimtersFoundFlag = false;
							break;
						}
					}
					
					// If we are on a Tail Node... such as "Start" or "End"...  there is no point in going further in the Script.  We don't actually have any "real nodes" in the database labeled with "Start" or "End".
					continue;
				}
				
				
				
				$thisPathWasFoundInSessionFlag = false;
				
				// Find out which Log ID's have a matching Source.
				$query = "SELECT ID FROM visitorlog WHERE SessionID='".DbCmd::EscapeSQL($thisSessionID)."' AND VisitLabel = '".DbCmd::EscapeSQL($thisMainLabelSource)."'";
				if(!empty($thisDetailLabelSource))
					$query .= " AND VisitLabelDetail ='".DbCmd::EscapeSQL($thisDetailLabelSource)."'";
					
				$this->_dbCmd->Query($query);
				$logIDsArr = $this->_dbCmd->GetValueArr();
				
				foreach($logIDsArr as $thisLogID){
			
					// Get the next log entry that is not hidden.
					if(empty($this->showNodesArr)){
						$row = null;
					}
					else{
						$this->_dbCmd->Query("SELECT VisitLabel, VisitLabelDetail FROM visitorlog WHERE 
											SessionID='".DbCmd::EscapeSQL($thisSessionID)."' AND ID > $thisLogID 
											AND ".DbHelper::getOrClauseFromArray("VisitLabel", $this->showNodesArr)." 
											ORDER BY ID ASC LIMIT 1");
						$row = $this->_dbCmd->GetRow();
					}
					
					// In case we hit the end of the road for the given session.
					if(empty($row))
						continue;
						
					$nextPageVisitLabel = $row["VisitLabel"];
					$nextPageVisitDetail = $row["VisitLabelDetail"];
					
					// If we can't match the Main Label, no sense in checking the Label Detail (if needed).
					if($nextPageVisitLabel != $thisMainLabelTarget)
						continue;
					
					// Find out if we are required to match the Detailed label as well.
					if(empty($thisDetailLabelTarget)){
						
						// We found at least 1 pathway in the session that matched... so stop digging through the individual log IDs.
						$thisPathWasFoundInSessionFlag = true;
						break;
					}
					else{
						
						// for the Detail label... we only need to get a match on the prefix.. it is OK if more data comes after on the Detail Label in the database.
						if(preg_match("/^".preg_quote($thisDetailLabelTarget)."/", $nextPageVisitDetail)){
							// We found at least 1 pathway in the session that matched... so stop digging through the individual log IDs.
							$thisPathWasFoundInSessionFlag = true;
							break;
						}
					}
				}
				
				// If we could not find at least 1 matching pathway in the session, then we can not include the Session ID.
				if(!$thisPathWasFoundInSessionFlag){
					$allPathLimtersFoundFlag = false;
					break;
				}
			}
			
			if($allPathLimtersFoundFlag)
				$returnSessionsArr[] = $thisSessionID;
		} 
		
		return $returnSessionsArr;

	
	}
	
	// Pass in an array of session IDs.
	// This will return on the session IDs that meet the "Path Filter" criteria stored in this object
	// If the a Session goes through any of the Invalid Paths... then it can't be included.
	// Then returns the Session ID array (possibly smaller).
	function filterSessionIdsByPathInvalidators(array $sessionIDsToFilter){

		if(empty($this->pathInvalidatorsArr))
			return $sessionIDsToFilter;
			
		$returnSessionsArr = array();

		foreach($sessionIDsToFilter as $thisSessionID){

			// Even if we don't find a matching Invalidator... we have to keep looping through the rest of them.
			// Only once we are sure that a session hasn't hit any of the Invalid Paths can we include the Session.
			$sessionDoesntCrossAnyInvalidPathsFlag = true;
			
			foreach($this->pathInvalidatorsArr as $labelHash){
				
				$thisMainLabelSource = $labelHash["SourceMainLabel"];
				$thisDetailLabelSource = $labelHash["SourceDetailLabel"];
				$thisMainLabelTarget = $labelHash["TargetMainLabel"];
				$thisDetailLabelTarget = $labelHash["TargetDetailLabel"];

				
				// If the the Main Label source or Main Label target is hidden, don't worry about checking... we know it can't find anything.
				// So go onto seeing if the next Path Invalidator will knock out the session.
				if($thisMainLabelSource != "Start" && !in_array($thisMainLabelSource, $this->showNodesArr))
						continue;	
				
				if($thisMainLabelTarget != "End" && !in_array($thisMainLabelTarget, $this->showNodesArr))
						continue;	
				
				
				// If the Main Label is "Start" or "End"... then we will have a match for every session.
				// These are special cases.
				if($thisMainLabelSource == "Start" || $thisMainLabelTarget == "End"){
					
					if($thisMainLabelSource == "Start")
						$tailEndLabelHash = $this->getStartNode($thisSessionID);
					else 
						$tailEndLabelHash = $this->getEndNode($thisSessionID);	

					
					// If the $tailEndLabelHash is empty... then it means all nodes are hidden within the session.
					// So if the Path Invalidator is Start->End ... and all nodes are Hidden... then it means that the path is invalid.
					if($thisMainLabelSource == "Start" && $thisMainLabelTarget == "End"){
						if(empty($tailEndLabelHash)){
							$sessionDoesntCrossAnyInvalidPathsFlag = false;
							break; // Break out of searching for more path invalidators since we already know the session is invalid.
						}
					}
						
					// In case all of the nodes are hidden... then we won't have any Start or Endnodes.
					// But that also means that there is no way for the Path to be invalid.
					if(empty($tailEndLabelHash))
						continue;

					$tailEndVisitLabel = $tailEndLabelHash["label"];
					$tailEndVisitDetail = $tailEndLabelHash["detail"];
					
					// If we are at the "End Node"... then reverse the Source and the Target Nodes
					if($thisMainLabelTarget == "End"){
						$tempMain = $thisMainLabelSource;
						$tempDet = $thisDetailLabelSource;
						$thisMainLabelSource = $thisMainLabelTarget;
						$thisDetailLabelSource = $thisDetailLabelTarget;
						$thisMainLabelTarget = $tempMain;
						$thisDetailLabelTarget = $tempDet;
					}
	
					// If we can't match the Main Label then no point in Checking the Detailed Label.
					if($tailEndVisitLabel != $thisMainLabelTarget)
						continue;
				
					// Find out if we are required to match the Detailed label as well.
					if(empty($thisDetailLabelTarget)){			
						$sessionDoesntCrossAnyInvalidPathsFlag = false;
						break; // Break out of searching for more path invalidators since we already know the session is invalid.
					}
					else{
						// Find out if the Prefix matches on our Tail-End label.							
						if(preg_match("/^".preg_quote($thisDetailLabelTarget)."/", $tailEndVisitDetail)){
							$sessionDoesntCrossAnyInvalidPathsFlag = false;
							break; // Break out of searching for more path invalidators since we already know the session is invalid.
						}
					}
					
					// If we are on a Tail Node... such as "Start" or "End"...  there is no point in going further in the Script.  We don't actually have any "real nodes" in the database labeled with "Start" or "End".
					continue;
				}
				


				// Find out which Log ID's have a matching Source.
				$query = "SELECT ID FROM visitorlog WHERE SessionID='".DbCmd::EscapeSQL($thisSessionID)."' AND VisitLabel = '".DbCmd::EscapeSQL($thisMainLabelSource)."'";
				if(!empty($thisDetailLabelSource))
					$query .= " AND VisitLabelDetail ='".DbCmd::EscapeSQL($thisDetailLabelSource)."'";
					
				$this->_dbCmd->Query($query);
				$logIDsArr = $this->_dbCmd->GetValueArr();

				
				foreach($logIDsArr as $thisLogID){
					
					// Get the next Log entry that is not hidden.
					if(empty($this->showNodesArr)){
						$row = null;
					}
					else{
						$this->_dbCmd->Query("SELECT VisitLabel, VisitLabelDetail FROM visitorlog WHERE 
											SessionID='".DbCmd::EscapeSQL($thisSessionID)."' AND ID > $thisLogID 
											AND ".DbHelper::getOrClauseFromArray("VisitLabel", $this->showNodesArr)." 
											ORDER BY ID ASC LIMIT 1");
						$row = $this->_dbCmd->GetRow();
					}
					
					if(empty($row))
						continue;
						
					$nextPageVisitLabel = $row["VisitLabel"];
					$nextPageVisitDetail = $row["VisitLabelDetail"];
					
					// If we can't match the Main Label then no point in Checking the Detailed Label.
					// There may be Other Invalid paths for the session ID though.
					if($nextPageVisitLabel != $thisMainLabelTarget)
						continue;
					
					// Find out if we are required to match the Detailed label as well.
					if(empty($thisDetailLabelTarget)){
						
						// Since we don't have to make sure the Target Detail matches....
						// We know that this Session has come across an invalid path. 
						// All that we need is one matching Path to deam the Session Invalid
						$sessionDoesntCrossAnyInvalidPathsFlag = false;
						break 2; // Break out of searching for more path invalidators since we already know the session is invalid.
					}
					else{
						
						// for the Detail label... we only need to get a match on the prefix.
						// Even if more data comes after on the Detail Label in the database, that doesn't matter.  The path is still invalid.
						if(preg_match("/^".preg_quote($thisDetailLabelTarget)."/", $nextPageVisitDetail)){
							$sessionDoesntCrossAnyInvalidPathsFlag = false;
							break 2; // Break out of searching for more path invalidators since we already know the session is invalid.
						}
					}
				}
			}
			
			if($sessionDoesntCrossAnyInvalidPathsFlag)
				$returnSessionsArr[] = $thisSessionID;
		} 
		
		return $returnSessionsArr;
	
	}
	
	
	
	// This will return a unique list of Label Names within the Query Parameters
	// If you don't set the Exploaded Label Names paramter in this object... it will not explode the list to include all Sub-Label Detailed Variations (such as keywords searched for within the Template Collection).
	// set an array of "Main Label Names" that you want the list to be exploded on (before calling this method.
	// For example, if you want to see all of the Template Keywords... Include the array entry "Template SearchEngine" and it will include the Sub Label names appended to the end of the Main Label with a dash between.
	// ... what it might return ... array("Template SearchEngine - ProductID:Plumber business cards", "Template SearchEngine - ProductID:real estate")
	function getUniqueLabelNames($expandLabelDetailsIfSetInObject = true){
		
		$sessionIDsArr = $this->getSessionIDs();
		
		if(empty($sessionIDsArr))
			return array();


		// If we we expanding Labels... don't merge them into the return array until the end
		// Otherwise Filtering the array on further iterations.
		$labelsExpandedArr = array();

		$this->_dbCmd->Query("SELECT DISTINCT VisitLabel FROM visitorlog WHERE " . DbHelper::getOrClauseFromArray("SessionID", $sessionIDsArr));
		$labelNamesArr = $this->_dbCmd->GetValueArr();
		
		// Make sure that the user wants to "see" the Labels.
		$labelNamesArr = array_diff($labelNamesArr, $this->getHiddenNodes());
		
		if($expandLabelDetailsIfSetInObject){
			
			// Now Find out if we want to explode any of the entries.
			// If so... find out if it matches a Label Name in our unique array.  If so delete the main label name and expand it with the SubLabels Entries.
			foreach ($this->expandedLabelNamesArr as $thisLabelNameToExpand){
				
				if(in_array($thisLabelNameToExpand, $labelNamesArr)){
					
					WebUtil::array_delete($labelNamesArr, $thisLabelNameToExpand);
					
					$this->_dbCmd->Query("SELECT DISTINCT CONCAT_WS(' - ', VisitLabel, VisitLabelDetail) FROM visitorlog WHERE " . DbHelper::getOrClauseFromArray("SessionID", $sessionIDsArr) . " AND VisitLabel = '" . DbCmd::EscapeSQL($thisLabelNameToExpand) . "'");
					$labelsExpandedArr = array_merge($labelsExpandedArr, $this->_dbCmd->GetValueArr());
				}
			}
			
			$labelNamesArr = array_merge($labelNamesArr, $labelsExpandedArr);
		}
		
		return $labelNamesArr;	
	}
	
	
	// This will return an array of every Main Label in Query Object... and the number of times that the node has been accessed.
	// The Key is the main label name... the value is the count.  Every key will contain a value greater than or equal to 1.
	function getMainLabelCounts(){
		
		if(!empty($this->labelCountsCacheArr))
			return $this->labelCountsCacheArr;
			
		$sessionIDsArr = $this->getSessionIDs();
		
		if(empty($sessionIDsArr))
			return array();
			
		$this->_dbCmd->Query("SELECT VisitLabel FROM visitorlog WHERE " . DbHelper::getOrClauseFromArray("SessionID", $this->getSessionIDs()));
		while($labelName = $this->_dbCmd->GetValue()){
			if(!isset($this->labelCountsCacheArr[$labelName]))
				$this->labelCountsCacheArr[$labelName] = 1;
			else
				$this->labelCountsCacheArr[$labelName]++;
		}
		
		ksort($this->labelCountsCacheArr);
		
		return $this->labelCountsCacheArr;
	}
	
	// Similar to getMainLabelCounts... but it returns the number of Vistors that accessed a label instead of the number of times it was accessed.
	function getMainLabelVisitors(){
		
		if(!empty($this->labelVisitorsCacheArr))
			return $this->labelVisitorsCacheArr;
			
		$mainLabelNamesArr = array_keys($this->getMainLabelCounts());
			
		foreach($mainLabelNamesArr as $thisLabelName){
			
			$this->_dbCmd->Query("SELECT COUNT(DISTINCT SessionID) FROM visitorlog WHERE VisitLabel=\"".DbCmd::EscapeSQL($thisLabelName)."\" AND " . DbHelper::getOrClauseFromArray("SessionID", $this->getSessionIDs()));
			while($visitorCount = $this->_dbCmd->GetValue()){
				$this->labelVisitorsCacheArr[$thisLabelName] = $visitorCount;
			}
		}
		return $this->labelVisitorsCacheArr;
	}
	
	
	
	// This will return a number telling you how many times the label has been accessed in the Session Pool.
	// Pass in a label detail if you want to restrict further.
	function getLabelsCount($mainLabelName, $labelDetail){
			
		$sessionIDsArr = $this->getSessionIDs();
		
		if(empty($sessionIDsArr))
			return 0;
		
		$query = "SELECT COUNT(*) FROM visitorlog WHERE VisitLabel='".DbCmd::EscapeSQL($mainLabelName)."' AND " . DbHelper::getOrClauseFromArray("SessionID", $this->getSessionIDs());			
		if(!empty($labelDetail))
			$query .= " AND VisitLabelDetail='".DbCmd::EscapeSQL($labelDetail)."'";
		
		$this->_dbCmd->Query($query);
			
		return $this->_dbCmd->GetValue();

	}
	// Same thing as getLabelsCount, but returns the number of visitors.
	function getVisitorCount($mainLabelName, $labelDetail){
			
		$sessionIDsArr = $this->getSessionIDs();
		
		if(empty($sessionIDsArr))
			return 0;
		
		$query = "SELECT COUNT(DISTINCT SessionID) FROM visitorlog WHERE VisitLabel='".DbCmd::EscapeSQL($mainLabelName)."' AND " . DbHelper::getOrClauseFromArray("SessionID", $this->getSessionIDs());			
		if(!empty($labelDetail))
			$query .= " AND VisitLabelDetail='".DbCmd::EscapeSQL($labelDetail)."'";
		
		$this->_dbCmd->Query($query);
			
		return $this->_dbCmd->GetValue();

	}
	
	
	
	// Pass in a Label Name (the label source) and this method will return an array of Label Names that have been linked to (by the source).
	// If the parameter to this method is set to TRUE... then it will return links to "Sub Label Details".  Otherwise, it will just show the Parent Label.
	// This does NOT return a UNIQUE list.  That is important because you can get the COUNT of links pointing there by analyzing the number of matching entries.
	// Pass in an empty (or NULL) label detail if you don't want to restrict the "Link Source".
	// For expanded Labels... set an array in this object of Main Label names that you want expanded for links pointed to at the label source.
	// For example... if the Main Label is "Home Page" .... and home page links to the "Template Search Engine" ... and you want to see exactly what Keywords the user linked to from the home page...
	// ... then you could include "Template Search Engine" within the array.
	// If you want to get the Label details... then it will separate the Main Label from the Label detail with a dash in between.
	// Pass in $showNumberOfPassagesInsteadOfVisitors FALSE if you want to count how many unique visitors went through the path instead of the number of times the path was used.
	function getSubsequentLinkedLabels($mainLabelName, $labelDetail, $showNumberOfPassagesInsteadOfVisitors = true){
			
		$linkedToArr = array();
			
		// Find all of the root nodes within the query parameters
		$sessionIDsArr = $this->getSessionIDs();
		
		foreach($sessionIDsArr as $thisSessionID){

			$query = "SELECT ID FROM visitorlog WHERE SessionID='".DbCmd::EscapeSQL($thisSessionID)."' AND VisitLabel = '".DbCmd::EscapeSQL($mainLabelName)."'";			
			if(!empty($labelDetail))
				$query .= " AND VisitLabelDetail='".DbCmd::EscapeSQL($labelDetail)."'";
			
			$this->_dbCmd->Query($query);
			$logIDsFromSource = $this->_dbCmd->GetValueArr();
			
			$labelsAlreadyReportedForSessionArr = array();
			
			// For each Source, figure out if there is another log ID coming after, (in the same session)
			// If so... it means that our Source Label (and possbily Label Detail) is linking to that.
			foreach($logIDsFromSource as $thisLogID){
				
				if(empty($this->showNodesArr)){
					$row = null;
				}
				else{
					$this->_dbCmd->Query("SELECT VisitLabel, VisitLabelDetail FROM visitorlog WHERE SessionID='".DbCmd::EscapeSQL($thisSessionID)."' AND ID > $thisLogID 
										AND ".DbHelper::getOrClauseFromArray("VisitLabel", $this->showNodesArr)." 
										ORDER BY ID ASC LIMIT 1");
					$row = $this->_dbCmd->GetRow();
				}
				
				if($row){
					
					$visitLabel = $row["VisitLabel"];			
					$visitLabelDetail = $row["VisitLabelDetail"];	

					if(in_array($visitLabel, $this->expandedLabelNamesArr) && !empty($visitLabelDetail))
						$visitDescription = $visitLabel . " - " . $visitLabelDetail;
					else
						$visitDescription = $visitLabel;
						
					// Find out if we should not show duplicate path ways for the current session.
					if(!$showNumberOfPassagesInsteadOfVisitors){
						if(!in_array($visitDescription, $labelsAlreadyReportedForSessionArr)){
							$linkedToArr[] = $visitDescription;
							$labelsAlreadyReportedForSessionArr[] = $visitDescription;
						}
					}
					else{
						$linkedToArr[] = $visitDescription;
					}
				}
			}
		}
		
		return $linkedToArr;
	}
	
	
	// Returns all of the Links names that have appeared first in a user's session.
	function getStartLinks(){
		
		// If there are no nodes displayed, then there won't be any start links.
		if(empty($this->showNodesArr))
			return array();
		
		// Find all of the root nodes within the query parameters
		$sessionIDsArr = $this->getSessionIDs();
		
		$startLinksArr = array();
		
		// Get the first link out of each session ID.
		foreach($sessionIDsArr as $thisSessionID){
			
			$this->_dbCmd->Query("SELECT VisitLabel, VisitLabelDetail FROM visitorlog WHERE SessionID='".DbCmd::EscapeSQL($thisSessionID)."' 
									AND ".DbHelper::getOrClauseFromArray("VisitLabel", $this->showNodesArr)." 
									ORDER BY ID ASC LIMIT 1");
			$row = $this->_dbCmd->GetRow();
			if($row){
				
				$visitLabel = $row["VisitLabel"];			
				$visitLabelDetail = $row["VisitLabelDetail"];	

				if(in_array($visitLabel, $this->expandedLabelNamesArr) && !empty($visitLabelDetail))
					$startLinksArr[] = $visitLabel . " - " . $visitLabelDetail;
				else
					$startLinksArr[] = $visitLabel;
			}
		}
		
		return $startLinksArr;
	}
	
	
	// Returns all of the Links names that have appeared as the last link in the user's session.
	function getEndLinks(){
		
		// If there are no nodes displayed, then there won't be any end links.
		if(empty($this->showNodesArr))
			return array();
		
		// Find all of the root nodes within the query parameters
		$sessionIDsArr = $this->getSessionIDs();
		
		$endLinksArr = array();
		
		// Get the first link out of each session ID.
		foreach($sessionIDsArr as $thisSessionID){
			
			$this->_dbCmd->Query("SELECT VisitLabel, VisitLabelDetail FROM visitorlog WHERE SessionID='".DbCmd::EscapeSQL($thisSessionID)."' 
									AND ".DbHelper::getOrClauseFromArray("VisitLabel", $this->showNodesArr)." 
									ORDER BY ID DESC LIMIT 1");
			$row = $this->_dbCmd->GetRow();
			if($row){
				
				$visitLabel = $row["VisitLabel"];			
				$visitLabelDetail = $row["VisitLabelDetail"];	

				if(in_array($visitLabel, $this->expandedLabelNamesArr) && !empty($visitLabelDetail))
					$endLinksArr[] = $visitLabel . " - " . $visitLabelDetail;
				else
					$endLinksArr[] = $visitLabel;
			}
		}
		
		return $endLinksArr;
	}
	
	
	
	
	// Returns an array of Session IDs
	// The key is the Session ID, and the Value is the number of times that session has gone through the path.
	// It does not include Session ID's with a Zero value.
	// Looks for which sessions have gone through the given path.
	// Pass in NULL for the label detail if you don't want to limit at a granual level.
	// You can Pass in the StartMain Label of "Start" (case sensitive) to look for start node paths... and "End" for the EndLabel name to look for paths that end.
	function getSessionIDsThatGoThroughPath($startMainLabelName, $startLabelDetail, $endMainLabelName, $endLabelDetail){
		
		// Make a digestive hash from all paramaters... so we can figure out if we already have the answer (for repeated calls.)
		$parametersSignature = md5($startMainLabelName . $startLabelDetail . $endMainLabelName . $endLabelDetail);
		
		if(array_key_exists($parametersSignature, $this->sessionIDsThroughPathsArr))
			return $this->sessionIDsThroughPathsArr[$parametersSignature];
		
		$sessionIDsArr = $this->getSessionIDs();
		
		
		$returnArr = array();
		
		foreach($sessionIDsArr as $thisSessionID){

			// If we are looking for Sessions that pass through the "Start" node... that is a special case. because every Session as a Start
			// ... (Unless all nodes are hidden).
			if($startMainLabelName == "Start"){
				
				// If none of the Nodes are shown... then we know that we can't find any starting labels.
				if(empty($this->showNodesArr)){
					$startLogID = null;
				}
				else {
					$query = "SELECT ID FROM visitorlog WHERE SessionID='".DbCmd::EscapeSQL($thisSessionID)."' 
							AND ".DbHelper::getOrClauseFromArray("VisitLabel", $this->showNodesArr)." 
							ORDER BY ID ASC LIMIT 1";	
	
					$this->_dbCmd->Query($query);
					$startLogID = $this->_dbCmd->GetValue();
				}
				
				// If the User doesn't have any Nodes displayed... then the only path which is possible for them to get a match on is "Start -> End".
				if(empty($startLogID)){
				
					if($startMainLabelName == "Start" && $endMainLabelName == "End"){
						$returnArr[$thisSessionID] = 1;
					}
					continue;
				}
				else{
					// Unlike other "Start Main Labels"... There can be only 1 Log ID in a Users Session that is the "real start" or beggining node.
					$logIDsFromSource = array($startLogID);
				}
			}
			else{
				$query = "SELECT ID FROM visitorlog WHERE SessionID='".DbCmd::EscapeSQL($thisSessionID)."' AND VisitLabel = '".DbCmd::EscapeSQL($startMainLabelName)."'";			
				if(!empty($startLabelDetail))
					$query .= " AND VisitLabelDetail='".DbCmd::EscapeSQL($startLabelDetail)."'";
					
				$this->_dbCmd->Query($query);
				$logIDsFromSource = $this->_dbCmd->GetValueArr();
			}
			

			
			// For each Source, figure out if there is another Log entry coming after, (in the same session)
			// If so... it means that our Source Label (and possbily Label Detail) is linking to that.
			// That means that the Session went though the given path.
			foreach($logIDsFromSource as $thisLogID){
				
				// For Start Paths... we want to know the Main Label and Detail Label of the first log ID.
				if($startMainLabelName == "Start"){
					$this->_dbCmd->Query("SELECT VisitLabel, VisitLabelDetail FROM visitorlog WHERE SessionID='".DbCmd::EscapeSQL($thisSessionID)."' AND ID = $thisLogID");
					$row = $this->_dbCmd->GetRow();
				}
				else{
					if(empty($this->showNodesArr)){
						$row = null;
					}
					else{
						$this->_dbCmd->Query("SELECT VisitLabel, VisitLabelDetail FROM visitorlog WHERE SessionID='".DbCmd::EscapeSQL($thisSessionID)."' AND ID > $thisLogID 
										 AND ". DbHelper::getOrClauseFromArray("VisitLabel", $this->showNodesArr) . " ORDER BY ID ASC LIMIT 1");
						$row = $this->_dbCmd->GetRow();
					}
				}
				

				
				// If we have an Empty Row... then it means there are no more Visit Labels ahead in the session.
				if(empty($row)){
					
					// If there are no more Log entries... and the method is trying to find out "End" paths... then we found a match.
					if($endMainLabelName == "End"){
						if(array_key_exists($thisSessionID, $returnArr))
							$returnArr[$thisSessionID]++;
						else
							$returnArr[$thisSessionID] = 1;
					}

					// We can't continue below because there are not Visit Labels to compare against.
					continue;
				}
					
					
				$visitLabel = $row["VisitLabel"];			
				$visitLabelDetail = $row["VisitLabelDetail"];	

				$foundFlag = false;
				
				if(!empty($endLabelDetail)){
					if($visitLabel == $endMainLabelName && $visitLabelDetail == $endLabelDetail)
						$foundFlag = true;
				}
				else{
					if($visitLabel == $endMainLabelName)
						$foundFlag = true;
				}
				
				if(!$foundFlag)
					continue;
					
				// So we found a match on the path.
				if(array_key_exists($thisSessionID, $returnArr))
					$returnArr[$thisSessionID]++;
				else
					$returnArr[$thisSessionID] = 1;
			}
		}
		
		$this->sessionIDsThroughPathsArr[$parametersSignature] = $returnArr;
		
		return $returnArr;
	}
	
	// Returns a number between 0 and 1.
	// Looks for which sessions have gone through the given path, and then returns the conversion rate of those that turn into sales.
	// Pass in NULL for the label detail if you don't want to limit to that.
	// Pass in a Global Conversion Flag of false if you want to do a Local Conversion.  It will tell you what percantage of Sessions that specifically go through that path end up making a purchase.
	function getPathConversionRate($startMainLabelName, $startLabelDetail, $endMainLabelName, $endLabelDetail, $globalConversionFlag = true){
		
		$totalSessionsCount = sizeof(($this->getSessionIDs()));

		$sessionIDsThroughPath = array_keys($this->getSessionIDsThatGoThroughPath($startMainLabelName, $startLabelDetail, $endMainLabelName, $endLabelDetail));
		
		$convertedSessions = 0;
		
		// Now let's find out which Sessions go through the "Order Completion" node.
		foreach($sessionIDsThroughPath as $thisSessionID){	
			if($this->visitorPathObj->checkIfOrderPlaced($thisSessionID))
				$convertedSessions++;
		}

		if($globalConversionFlag)
			return $convertedSessions / $totalSessionsCount;
		else 
			return $convertedSessions / sizeof($sessionIDsThroughPath);
	}
	
	
	
	
}
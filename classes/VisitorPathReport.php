<?php

// Check out http://www.graphviz.org to see syntax for the dot language.
class VisitorPathReport {
	
	private $_dbCmd;
	private $visitQueryObj;
	private $totalSessionsCount;
	
	function __construct() {
	
		$this->_dbCmd = new DbCmd();
		$this->totalSessionsCount = 0;

	}
	

	function setVisitorQueryObject(VisitorPathQuery $visitorPathQueryObj){
		
		$this->visitQueryObj = $visitorPathQueryObj;
		$this->totalSessionsCount = 0;
		
		
	}
	
	// Pass in a concatenated Label Description ... such as "Template Keywords - Plumber Business Cards"
	// It will strip off the anything before the first dash.... so in this case the method would return "Template Keywords"
	function getMainLabelNameFromFullLabelDesc($labelDescription){
		
		$labelApartsArr = split(" - ", $labelDescription, 2);

		return $labelApartsArr[0];

	}
	
	// Pass in a concatenated Label Description ... such as "Template Keywords - Plumber Business Cards"
	// It will strip off the anything after the first dash.... so in this case the method would return "Plumber Business Cards"
	function getDetailLabelNameFromFullLabelDesc($labelDescription){
		
		$labelApartsArr = split(" - ", $labelDescription, 2);
		
		if(sizeof($labelApartsArr) == 2){
			return $labelApartsArr[1];
		}
		else{
			return null;
		}
	}
	
	function getTotalSessionCount(){

		if(!empty($this->totalSessionsCount))
			return $this->totalSessionsCount;
			
		if(empty($this->visitQueryObj))
			throw new Exception("The visit query object has not been set yet.");
			
		$this->totalSessionsCount = sizeof($this->visitQueryObj->getSessionIDs());

		return $this->totalSessionsCount;
	}
	

	
	
	// Pass in an array of Main Label names that you want to have expanded (with all of the Sub Label Names).
	function getCumulativeReport(){
		
		if(empty($this->visitQueryObj))
			throw new Exception("The visit query object has not been set yet.");
			
		//$retStr = "digraph G {\n\t\n";
		$retStr = "";
				
		$uniqueLabelDescriptions = $this->visitQueryObj->getUniqueLabelNames();

		$totalSessionsCount = $this->getTotalSessionCount();
	
		$subGraphNodesArr = array();
		
	
		foreach($uniqueLabelDescriptions as $thisLabelDesc){
			
			$mainLabelName = $this->getMainLabelNameFromFullLabelDesc($thisLabelDesc);
			$detailLabelName = $this->getDetailLabelNameFromFullLabelDesc($thisLabelDesc);	
			
			
			$linksFromLabelArr_Passages = $this->visitQueryObj->getSubsequentLinkedLabels($mainLabelName, $detailLabelName, true);
			$linksFromLabelArr_Visitors = $this->visitQueryObj->getSubsequentLinkedLabels($mainLabelName, $detailLabelName, false);
			
			$uniqueLinkedFromLablesArr = array_unique($linksFromLabelArr_Passages);
			
			// Count how many links there are to each label has using the unique name as the "key" and the link count as the value.
			$labelCountsHash_Passages = array_count_values($linksFromLabelArr_Passages);
			$labelCountsHash_Visitors = array_count_values($linksFromLabelArr_Visitors);
			
			
			foreach($uniqueLinkedFromLablesArr as $thisLinkToLabel){
				
				$pathLinkedTo_mainLabel = $this->getMainLabelNameFromFullLabelDesc($thisLinkToLabel);
				$pathLinkedTo_detailLabel = $this->getDetailLabelNameFromFullLabelDesc($thisLinkToLabel);
				
				$colorOfPathCount = $this->getColorFromPercentage($labelCountsHash_Passages[$thisLinkToLabel], $totalSessionsCount);
				
				$globalPathConversionRate = $this->visitQueryObj->getPathConversionRate($mainLabelName, $detailLabelName, $pathLinkedTo_mainLabel, $pathLinkedTo_detailLabel, true);
				$localPathConversionRate = $this->visitQueryObj->getPathConversionRate($mainLabelName, $detailLabelName, $pathLinkedTo_mainLabel, $pathLinkedTo_detailLabel, false);

				// Now clone the Query Object... so we can set parameters temporarily and use the clones to build a new query string.
				$visitQueryClone_Limter = clone $this->visitQueryObj;
				$visitQueryClone_Invalidator = clone $this->visitQueryObj;
				
				$visitQueryClone_Limter->addPathLimiters($mainLabelName, $detailLabelName, $pathLinkedTo_mainLabel, $pathLinkedTo_detailLabel);
				$visitQueryClone_Invalidator->addPathInvalidators($mainLabelName, $detailLabelName, $pathLinkedTo_mainLabel, $pathLinkedTo_detailLabel);
					
				// On Windows there is a bug with GraphViz and the URL can not go over a certain length.  On Linux it is OK. Only the IE 2048 limit is left, which shouldn't be a problem.
				$filterLinkURL =  $this->getServerPrefix() . "/ad_visitorPathsChart.php?combinedChartParams=" . urlencode($visitQueryClone_Limter->getUrlQueryEncoded());
				$invalidatorLinkURL = $this->getServerPrefix() . "/ad_visitorPathsChart.php?combinedChartParams=" . urlencode($visitQueryClone_Invalidator->getUrlQueryEncoded());

				$labelHTML = $this->getPathLabelHtml($labelCountsHash_Visitors[$thisLinkToLabel], $labelCountsHash_Passages[$thisLinkToLabel], $colorOfPathCount, $globalPathConversionRate, $localPathConversionRate, $filterLinkURL, $invalidatorLinkURL);

			/*	
		
				if(preg_match("/^Home:/", $thisLabelDesc)){
					if(!isset($subGraphNodesArr["HomeNodes"]))
						$subGraphNodesArr["HomeNodes"] = array();
						
					$subGraphNodesArr["HomeNodes"][] = $thisLabelDesc;
				}

				else if(preg_match("/^Template/", $thisLabelDesc)){
					if(!isset($subGraphNodesArr["TemplateNodes"]))
						$subGraphNodesArr["TemplateNodes"] = array();
						
					$subGraphNodesArr["TemplateNodes"][] = $thisLabelDesc;
				}

				else if(preg_match("/^Content/", $thisLabelDesc)){
					if(!isset($subGraphNodesArr["Content"]))
						$subGraphNodesArr["Content"] = array();
						
					$subGraphNodesArr["Content"][] = $thisLabelDesc;
				}

				else if(preg_match("/Saved/", $thisLabelDesc)){
					if(!isset($subGraphNodesArr["Saved"]))
						$subGraphNodesArr["Saved"] = array();
						
					$subGraphNodesArr["Saved"][] = $thisLabelDesc;
				}
			*/


				$retStr .= "\t\"" . $thisLabelDesc . "\" -> \"" . $thisLinkToLabel . "\"" .  '[ label = <'.$labelHTML.'> color="'.$colorOfPathCount.'" ]' .  "\n";
			}
		}
		
		$startLinksArr = $this->visitQueryObj->getStartLinks();
		$endLinksArr = $this->visitQueryObj->getEndLinks();
		
		$startLinksCountsHash = array_count_values($startLinksArr);
		$endLinksCountsHash = array_count_values($endLinksArr);
		
		
		// Build Start Link Paths
		foreach(array_keys($startLinksCountsHash) as $startLinkDesc){
			
			$pathLinkedTo_mainLabel = $this->getMainLabelNameFromFullLabelDesc($startLinkDesc);
			$pathLinkedTo_detailLabel = $this->getDetailLabelNameFromFullLabelDesc($startLinkDesc);
			
			$colorOfPathCount = $this->getColorFromPercentage($startLinksCountsHash[$startLinkDesc], $totalSessionsCount);
			
			$globalPathConversionRate = $this->visitQueryObj->getPathConversionRate("Start", null, $pathLinkedTo_mainLabel, $pathLinkedTo_detailLabel, true);
			$localPathConversionRate = $this->visitQueryObj->getPathConversionRate("Start", null, $pathLinkedTo_mainLabel, $pathLinkedTo_detailLabel, false);

			// Now clone the Query Object... so we can set parameters temporarily and use the clones to build a new query string.
			$visitQueryClone_Limter = clone $this->visitQueryObj;
			$visitQueryClone_Invalidator = clone $this->visitQueryObj;
			
			$visitQueryClone_Limter->addPathLimiters("Start", null, $pathLinkedTo_mainLabel, $pathLinkedTo_detailLabel);
			$visitQueryClone_Invalidator->addPathInvalidators("Start", null, $pathLinkedTo_mainLabel, $pathLinkedTo_detailLabel);
				
			// On Windows there is a bug with GraphViz and the URL can not go over a certain length.  On Linux it is OK. Only the IE 2048 limit is left, which shouldn't be a problem.
			$filterLinkURL =  $this->getServerPrefix() . "/ad_visitorPathsChart.php?combinedChartParams=" . urlencode($visitQueryClone_Limter->getUrlQueryEncoded());
			$invalidatorLinkURL = $this->getServerPrefix() . "/ad_visitorPathsChart.php?combinedChartParams=" . urlencode($visitQueryClone_Invalidator->getUrlQueryEncoded());

			$labelHTML = $this->getPathLabelHtml($startLinksCountsHash[$startLinkDesc], $startLinksCountsHash[$startLinkDesc], $colorOfPathCount, $globalPathConversionRate, $localPathConversionRate, $filterLinkURL, $invalidatorLinkURL);
				
			$retStr .= "\t\"Start\" -> \"" . $startLinkDesc . "\"" .  '[ label = <'.$labelHTML.'> color="'.$colorOfPathCount.'" ]' .  "\n";
		}

		// Build End Link Paths
		foreach(array_keys($endLinksCountsHash) as $endLinkDesc){
			
			$pathLinkedFrom_mainLabel = $this->getMainLabelNameFromFullLabelDesc($endLinkDesc);
			$pathLinkedFrom_detailLabel = $this->getDetailLabelNameFromFullLabelDesc($endLinkDesc);
			
			$colorOfPathCount = $this->getColorFromPercentage($endLinksCountsHash[$endLinkDesc], $totalSessionsCount);
			
			$globalPathConversionRate = $this->visitQueryObj->getPathConversionRate($pathLinkedFrom_mainLabel, $pathLinkedFrom_detailLabel, "End", null, true);
			$localPathConversionRate = $this->visitQueryObj->getPathConversionRate($pathLinkedFrom_mainLabel, $pathLinkedFrom_detailLabel, "End", null, false);


			// Now clone the Query Object... so we can set parameters temporarily and use the clones to build a new query string.
			$visitQueryClone_Limter = clone $this->visitQueryObj;
			$visitQueryClone_Invalidator = clone $this->visitQueryObj;
			
			$visitQueryClone_Limter->addPathLimiters($pathLinkedFrom_mainLabel, $pathLinkedFrom_detailLabel, "End", null);
			$visitQueryClone_Invalidator->addPathInvalidators($pathLinkedFrom_mainLabel, $pathLinkedFrom_detailLabel, "End", null);
				
			// On Windows there is a bug with GraphViz and the URL can not go over a certain length.  On Linux it is OK. Only the IE 2048 limit is left, which shouldn't be a problem.
			$filterLinkURL =  $this->getServerPrefix() . "/ad_visitorPathsChart.php?combinedChartParams=" . urlencode($visitQueryClone_Limter->getUrlQueryEncoded());
			$invalidatorLinkURL = $this->getServerPrefix() . "/ad_visitorPathsChart.php?combinedChartParams=" . urlencode($visitQueryClone_Invalidator->getUrlQueryEncoded());

			$labelHTML = $this->getPathLabelHtml($endLinksCountsHash[$endLinkDesc], $endLinksCountsHash[$endLinkDesc], $colorOfPathCount, $globalPathConversionRate, $localPathConversionRate, $filterLinkURL, $invalidatorLinkURL);
			
			$retStr .= "\t\"" . $endLinkDesc . "\" -> End" .  '[ label = <'.$labelHTML.'> color="'.$colorOfPathCount.'" ]' .  "\n";		
			
		}
		
		
		// Figure out how many Sessions go directly from "Start ->to-> End" because there visits are all hidden.
		$hiddenSessionsArr = $this->visitQueryObj->getSessionIDsThatGoThroughPath("Start", null, "End", null);
		$hiddenSessionsCount = sizeof($hiddenSessionsArr);
		
		if($hiddenSessionsCount > 0){
			
			$colorOfPathCount = $this->getColorFromPercentage($hiddenSessionsCount, $totalSessionsCount);
			
			$globalPathConversionRate = $this->visitQueryObj->getPathConversionRate("Start", null, "End", null, true);
			$localPathConversionRate = $this->visitQueryObj->getPathConversionRate("Start", null, "End", null, false);

			// Now clone the Query Object... so we can set parameters temporarily and use the clones to build a new query string.
			$visitQueryClone_Limter = clone $this->visitQueryObj;
			$visitQueryClone_Invalidator = clone $this->visitQueryObj;
			
			$visitQueryClone_Limter->addPathLimiters("Start", null, "End", null);
			$visitQueryClone_Invalidator->addPathInvalidators("Start", null, "End", null);
				
			// On Windows there is a bug with GraphViz and the URL can not go over a certain length.  On Linux it is OK. Only the IE 2048 limit is left, which shouldn't be a problem.
			$filterLinkURL =  $this->getServerPrefix() . "/ad_visitorPathsChart.php?combinedChartParams=" . urlencode($visitQueryClone_Limter->getUrlQueryEncoded());
			$invalidatorLinkURL = $this->getServerPrefix() . "/ad_visitorPathsChart.php?combinedChartParams=" . urlencode($visitQueryClone_Invalidator->getUrlQueryEncoded());

			$labelHTML = $this->getPathLabelHtml($hiddenSessionsCount, $hiddenSessionsCount, $colorOfPathCount, $globalPathConversionRate, $localPathConversionRate, $filterLinkURL, $invalidatorLinkURL);
			
			$retStr .= "\tStart -> End" .  '[ label = <'.$labelHTML.'> style=dashed color="'.$colorOfPathCount.'" ]' .  "\n";		
		}

		
		// Build Start and End Nodes
		$retStr .= "Start [label=\"    ".$this->getStartLabelName()."    \" fontsize=\"18\" shape=\"Mdiamond\" color=\"#444422\" fontcolor=\"#ffffff\" fillcolor=\"#999999\" style=filled URL=\"" . $this->getServerPrefix() . "/ad_visitorPaths.php?combinedChartParams=" . urlencode($this->visitQueryObj->getUrlQueryEncoded()) . "\" ];\n";
		$retStr .= "End [label=\"   End   \" shape=\"Msquare\" fontsize=\"18\"  color=\"#444422\" fillcolor=\"#999999\" fontcolor=\"#ffffff\" style=filled URL=\"".$this->getServerPrefix()."/ad_visitorPaths.php?combinedChartParams=" . urlencode($this->visitQueryObj->getUrlQueryEncoded()) . "\" ]\n";
		
		
		// Create Decorations on the Nodes themselves.
		foreach($uniqueLabelDescriptions as $thisLabelDesc)
			$retStr .= $this->getLabelDecorationByLabelDesc($thisLabelDesc);
		
		
		// Finish off the Sub Graphs and put them back inside of the main graph.
		
		$subGraphNodesStr = "";
		foreach($subGraphNodesArr as $thisSubGraphName => $thisSubGraphArr){
			
			$distinctSubGraphNodesArr = array_unique($thisSubGraphArr);

			$subGraphNodesStr .= "subgraph cluster_$thisSubGraphName { color=black; ";
			foreach($distinctSubGraphNodesArr as $thisNodeName){
				$subGraphNodesStr .= "\"" . $thisNodeName .  "\"; ";
			}
			$subGraphNodesStr .= " }\n";
		}

		
		$finalStr = "digraph G {\n\t\n";
		//$rankStr .= "{ rank = source;  \"cluster_HomeNodes\";  \"cluster_TemplateNodes\"; } \n";
		//$retStr .= $rankStr;
		$retStr .= $subGraphNodesStr;
		
		
		$retStr .= "\n}";
		$finalStr .= $retStr;
//exit($finalStr);
		return $finalStr;
	}
	
	private function getStartLabelName(){
		
		return "Start (".$this->getTotalSessionCount().")";
	}
	
	

	private function getLabelDecorationByLabelDesc($thisLabelDesc){
		
		$mainLabel = $this->getMainLabelNameFromFullLabelDesc($thisLabelDesc);
		$detailLabel = $this->getDetailLabelNameFromFullLabelDesc($thisLabelDesc);
		
		// Clone the Query Object... so we can set parameters temporarily and use the clones to build a new query string.
		$visitQueryClone_Limter = clone $this->visitQueryObj;
		$visitQueryClone_Invalidator = clone $this->visitQueryObj;
			
		$visitQueryClone_Limter->addLabelLimiter($mainLabel, $detailLabel);
		$visitQueryClone_Invalidator->addLabelInvalidator($mainLabel, $detailLabel);
		
		// On Windows there is a bug with GraphViz and the URL can not go over a certain length.  On Linux it is OK. Only the IE 2048 limit is left, which shouldn't be a problem.
		$filterNodeURL =  $this->getServerPrefix() . "/ad_visitorPathsChart.php?combinedChartParams=" . urlencode($visitQueryClone_Limter->getUrlQueryEncoded());
		$invalidatorNodeURL = $this->getServerPrefix() . "/ad_visitorPathsChart.php?combinedChartParams=" . urlencode($visitQueryClone_Invalidator->getUrlQueryEncoded());
		
		// If the Label Description is a Main Label... then we will have a counter for how many times the node has been accessed.
		$mainLabelCountsArr = $this->visitQueryObj->getMainLabelCounts();
		$mainLabelVisitorsArr = $this->visitQueryObj->getMainLabelVisitors();
		
		// We don't want to add Filter and Invalidators for Single Sessions.
		if(empty($this->totalSessionsCount))
			$filterAndInvalidateHTMLlinks = "";
		else
			$filterAndInvalidateHTMLlinks = "<td href=\"$filterNodeURL\"><font color='#338833'>F</font></td><td href=\"$invalidatorNodeURL\"><font color='#883333'>X</font></td>";
		
		// If we are viewing a Node which has been expanded... then we won't be able to extract the counts out of our main labels.
		if(array_key_exists($thisLabelDesc, $mainLabelCountsArr)){
			
			$vstrCnt = $mainLabelVisitorsArr[$thisLabelDesc];
			$lblCnt = $mainLabelCountsArr[$thisLabelDesc];
			
			if($lblCnt == $vstrCnt)
				$countDescription = number_format($vstrCnt);
			else
				$countDescription = number_format($vstrCnt)." / ".number_format($lblCnt);
				
			$labelDescHTML = "label=<<table color=\"#FFFFFF\" cellspacing=\"0\" cellpadding=\"2\" cellborder=\"0\" border=\"0\" ><tr><td><font color='#000000'>".htmlspecialchars($thisLabelDesc)." (".$countDescription.")</font></td>$filterAndInvalidateHTMLlinks</tr></table>>";
		}
		else{ 
			
			// We have to do a DB query to get the count of a node which has been expanded (with Label Details).
			$lblCnt = $this->visitQueryObj->getLabelsCount($mainLabel, $detailLabel);
			$vstrCnt = $this->visitQueryObj->getVisitorCount($mainLabel, $detailLabel);
			
			if($lblCnt == $vstrCnt)
				$countDescription = number_format($lblCnt);
			else
				$countDescription = number_format($vstrCnt)." / ".number_format($lblCnt);
			
			$labelDescHTML = "label=<<table color=\"#FFFFFF\" cellspacing=\"0\" cellpadding=\"2\" cellborder=\"0\" border=\"0\" ><tr><td><font color='#008800'>".htmlspecialchars($thisLabelDesc)." (".$countDescription.")</font></td>$filterAndInvalidateHTMLlinks</tr></table>>";

		}

		if(preg_match("/^Home:/", $thisLabelDesc)){
			return "\"$thisLabelDesc\" [$labelDescHTML shape=\"house\" color=\"#663300\" fillcolor=\"#F4F2E8\" style=filled ];\n";
		}
		else if(preg_match("/^Template/", $thisLabelDesc)){
			return "\"$thisLabelDesc\" [$labelDescHTML shape=\"note\" color=\"#374771\" fillcolor=\"#EFF5F5\" style=filled ];\n";
		}
		else if(preg_match("/^Saved/", $thisLabelDesc)){
			return "\"$thisLabelDesc\" [$labelDescHTML shape=\"tab\" color=\"#68404F\" fillcolor=\"#F3EDEF\" style=filled ];\n";
		}
		else if(preg_match("/^Shopping Cart/", $thisLabelDesc)){
			return "\"$thisLabelDesc\" [$labelDescHTML shape=\"box3d\" color=\"#117711\" fillcolor=\"#ddFFdd\" style=filled ];\n";
		}
		else if(preg_match("/^Banner Click$/", $thisLabelDesc)){
			return "\"$thisLabelDesc\" [$labelDescHTML shape=\"doubleoctagon\" color=\"#661111\" fillcolor=\"#fff0f0\" style=filled ];\n";
		}
		else if(preg_match("/^Organic Link$/", $thisLabelDesc)){
			return "\"$thisLabelDesc\" [$labelDescHTML shape=\"tripleoctagon\" color=\"#666611\" fillcolor=\"#ffffdd\" style=filled ];\n";
		}
		
		
		
		else{
			return "\"$thisLabelDesc\" [$labelDescHTML shape=ellipse ];\n";
		}

	}
	
	private function getServerPrefix(){
		if(Constants::GetDevelopmentServer())
			return Constants::GetServerSSL() . "://" . "localhost/dot";
		else 
			return Constants::GetServerSSL() . "://" . Domain::getWebsiteURLforDomainID(Domain::getDomainIDfromURL());
	}
	
	function getPathLabelHtml($numberOfPathCounts_Visitors, $numberOfPathCounts_Passages, $colorOfPathCount, $globalPathConversionRate, $localPathConversionRate, $filterURL, $excludeURL){

		if($globalPathConversionRate == 0){
			$globalConversionDescription = "<font color=\"#999999\">---</font>";
		}
		else{ 
			// Figure out the number of purchases created by reversing the conversion raate.
			$totalPurchases = round($this->getTotalSessionCount() * $globalPathConversionRate);
			$globalConversionDescription = "<font color=\"#CCCCCC\">GC</font> " . (round(100 * $globalPathConversionRate,1)) . "% ($totalPurchases)";
		}
			
		if($localPathConversionRate == 0)
			$localConversionDescription = "<font color=\"#999999\">---</font>";
		else 
			$localConversionDescription = "<font color=\"#CCCCCC\">LC</font> " . (round(100 * $localPathConversionRate,1)) . "%";
			
			
		// We want a way to show how important the conversion rate is for the path.  A good way to do that is by comparing the Local Conversion perctange against the global conversion.
		if($localPathConversionRate != 0){
			$barGraphRightPercent = ($globalPathConversionRate * 100) / ($localPathConversionRate * 100);
			
			$barGraphRightPercent = ceil($barGraphRightPercent * 100);
		
			if($barGraphRightPercent > 100)
				$barGraphRightPercent = 100;
			
			$barGraphLeftPercent = 100 - $barGraphRightPercent;

		}
		
		// Don't compare the visitors to the number of passages is the figures are identical.
		if($numberOfPathCounts_Visitors == $numberOfPathCounts_Passages)
			$pathCount = number_format($numberOfPathCounts_Passages);
		else 
			$pathCount = number_format($numberOfPathCounts_Visitors). " / " . number_format($numberOfPathCounts_Passages);
			
			
		$labelHTML = "<table width=\"100\" align=\"right\" color=\"#cccccc\" cellspacing=\"0\" cellpadding=\"1\" cellborder=\"1\" border=\"0\" >";
		$labelHTML .= "<tr><td colspan=\"2\"><font color=\"$colorOfPathCount\">". $pathCount . "</font></td></tr>";
		
		if($globalPathConversionRate != 0 || $localPathConversionRate != 0){
			$labelHTML .= "<tr><td colspan=\"2\">".$globalConversionDescription."</td></tr>";
			$labelHTML .= "<tr><td colspan=\"2\">".$localConversionDescription."</td></tr>";
			
			$linkColorFilter = "#F6FFF6";
			$linkColorExcluce = "#FFf6f6";
		}
		else{
			$linkColorFilter = "#ddFFdd";
			$linkColorExcluce = "#FFdddd";
		}
		
		if($localPathConversionRate != 0)
			$labelHTML .= "<tr height=\"2\"><td colspan=\"2\"><table border=\"0\" cellpadding=\"0\"><tr height=\"2\"><td bgcolor=\"#ffff00\" height=\"1\" width=\"" . $barGraphLeftPercent . "%\"></td><td bgcolor=\"#aaaaaa\" height=\"1\" width=\"" . $barGraphRightPercent . "%\"></td></tr></table></td></tr>";
			
		$labelHTML .= "<tr><td href=\"$filterURL\" bgcolor=\"$linkColorFilter\"> F </td><td href=\"$excludeURL\" bgcolor=\"$linkColorExcluce\"> X </td></tr>";
		$labelHTML .= "</table>";
		
		return $labelHTML;
		
	}
	
	
	// Pass in a session ID.  It only gives the report for 1 user.
	// Pass in an array of Main Label names that you want to have expanded (with all of the Sub Label Names).
	// Pass in the second parameter FALSE if you want the Label Details concatenated after the Main Label Name with a dash and 2 spaces (" - ");
	function getSessionReport($sessionID, $conflateVisitLabel = true){

		$visitorPathObj = new VisitorPath();
		$visitorLogIds = array_keys($visitorPathObj->getVisitLabels($sessionID, $conflateVisitLabel));
		
		if(empty($visitorLogIds)){
			return "digraph SessionGraph {\n\t\"Start\" -> End;\n}\n";
		}
			
		$retStr = "digraph SessionGraph {\n\t\n";
		
		
		$uniqueLabelDescriptions = array();
		
		$isStartLabel = true;

		$totalCountOfVisitLogIDs = sizeof($visitorLogIds);
		$counter = 1;
		$i=0;
		while($i < sizeof($visitorLogIds)){
			
			$thisVisitID = $visitorLogIds[$i];
			
			$thisLabelDesc = $visitorPathObj->getVisitLabelDesc($thisVisitID, $conflateVisitLabel);
			
			$uniqueLabelDescriptions[] = $thisLabelDesc;
			
			if($conflateVisitLabel){
				$nextVisitLogID = $visitorPathObj->getNextVisitIdWithoutMatchingLabel($thisVisitID);
				
				// Figure out how far to skip ahead
				$i += $visitorPathObj->getCountOfMatchingVisitLabelsAhead($thisVisitID);
				
			}
			else{
				$nextVisitLogID = $visitorPathObj->getNextVisitLogIDInSession($thisVisitID);

				$i++;
			}
			
			if($isStartLabel){
				$retStr .= "\t\"Start\" -> \"" . $thisLabelDesc . '";' . "\n";
				$isStartLabel = false;
			}
			
			
			// Get the time spent on the current page... before going to the next link.
			// If we are conflating Label Details... then get the next label, regardless of time.
			$secondsOnPage = $visitorPathObj->getVisitIdDuration($thisVisitID, $conflateVisitLabel);
			
			$durationDesc = $this->getDurationDescription($secondsOnPage);
			
			if(empty($nextVisitLogID)){
				
				// If we are conflating links... they we may actually know how long a user was browsing for templates before they decided to leave.
				if(empty($secondsOnPage))
					$retStr .= "\t" . '"' . $thisLabelDesc . '" -> End;' . "\n";
				else 
					$retStr .= "\t" . '"' . $thisLabelDesc . '" -> End [ label = "'.$durationDesc.'     " ];' . "\n";
					
				break;
			}
				
			// Get the lable of the next visit.
			$nextLabelDesc = $visitorPathObj->getVisitLabelDesc($nextVisitLogID, $conflateVisitLabel);
				
			$retStr .= "\t " . '"' . $thisLabelDesc . '" -> "'. $nextLabelDesc . '" [ label = "(' . $counter .  ') '.$durationDesc.'     " fontcolor="'.$this->getColorFromProgressPercentage($counter, $totalCountOfVisitLogIDs).'"  color="'.$this->getColorFromProgressPercentage($counter, $totalCountOfVisitLogIDs).'" fontsize="'.$this->getFontSizeBasedOnSecondsOfNode($secondsOnPage).'"  ];' . "\n";

			$counter++;
		}
		
		$uniqueLabelDescriptions = array_unique($uniqueLabelDescriptions);
		
		// Create Decorations on the Nodes themselves.
		foreach($uniqueLabelDescriptions as $thisLabelDesc)
			$retStr .= $this->getLabelDecorationByLabelDesc($thisLabelDesc);
			
		
		
		$retStr .= "Start [label=\"    Start    \" fontsize=\"18\" shape=\"Mdiamond\" color=\"#444422\" fontcolor=\"#ffffff\" fillcolor=\"#996699\" style=filled  ];\n";
		$retStr .= "End [label=\"   End   \" shape=\"Msquare\" fontsize=\"18\"  color=\"#444422\" fillcolor=\"#996699\" fontcolor=\"#ffffff\" style=filled  ]\n";
		
		
		$retStr .= "\n}\n\n";

		return $retStr;
	}
	
	function getDurationDescription($numberOfSeconds){
		
			if($numberOfSeconds == 0){
				$durationDesc = "";
			}
			else if($numberOfSeconds < 60){
				$durationDesc = $numberOfSeconds . " sec.";
			}
			else if($numberOfSeconds < 300){
				$durationDesc = floor($numberOfSeconds / 60) . " min. " . ($numberOfSeconds % 60) . " sec.";
			}
			else{
				$durationDesc = round($numberOfSeconds / 60) . " min.";
			}
			return $durationDesc;
	}
	
	// returns a number like #FF0000
	// Pass in the number of sessions during the report... because that will give us a nice number to determine if all of the visitors funneled down one path.
	// It is possible to have more people clicking on a link then there are sessions... because people can click the same link multiple times within the same session.... so they will always show up very Bright Colored
	function getColorFromPercentage($numberToCalculate, $totalPossible){
		
		if(empty($totalPossible))
			$percentage = 0;
		else 
			$percentage = $numberToCalculate / $totalPossible * 100;
			
		if($percentage < 1)
			return "#eeeeee";
		else if($percentage < 3)
			return "#dddddd";
		else if($percentage < 5)
			return "#cccccc";
		else if($percentage < 7)
			return "#bbbbbb";
		else if($percentage < 10)
			return "#0099FF";
		else if($percentage < 20)
			return "#0099FF";
		else if($percentage < 30)
			return "#0066FF";
		else if($percentage < 40)
			return "#0000FF";
		else if($percentage < 50)
			return "#6600FF";
		else if($percentage < 60)
			return "#aa00FF";
		else if($percentage < 70)
			return "#ff00FF";
		else if($percentage < 80)
			return "#ff00aa";
		else if($percentage < 90)
			return "#ff0099";
		else if($percentage < 100)
			return "#ff0066";
		else if($percentage < 110)
			return "#ff3333";
		else
			return "#FF0000";
	}
	
	function getFontSizeBasedOnSecondsOfNode($secondsOnPage){
		if($secondsOnPage < 2)
			return "8";
		if($secondsOnPage < 5)
			return "10";
		if($secondsOnPage < 10)
			return "12";
		if($secondsOnPage < 20)
			return "14";
		if($secondsOnPage < 40)
			return "16";
		if($secondsOnPage < 60)
			return "18";
		if($secondsOnPage < 120)
			return "20";
		if($secondsOnPage < 240)
			return "22";
		if($secondsOnPage < 500)
			return "24";
		else
			return "26";
	}

	
	// returns a number like #FF0000
	// This is mainly used for Single Chart sessions.  You want to color code the arrows between successive visits.
	function getColorFromProgressPercentage($currentNodeCount, $totalNodesInSession){
		
		if($totalNodesInSession <= 4)
			return "#000000";
		

		$percentage = round($currentNodeCount / $totalNodesInSession * 100);

	
		// Between Zero and 33% we are going to go from pure Black to pure blue.
		// Between 33% and 66% we are going to add in a percentage of red.
		// Between 66% and 100% we are going to take out all of the blue.
		if($percentage <= 33){
			$bluePercentage = $percentage / 33;
			$redPercentage = 0;
			$greenPercentage = 0;
			
		}
		else if($percentage <= 66){
			
			$bluePercentage = 1;
			$redPercentage = ($percentage - 33) / 33;
			$greenPercentage = 0;
		}
		else{
			$greenPercentage = 0;
			$redPercentage = 1;
			$bluePercentage = (100 - $percentage) / 33;
		}
		
		$blueHex = dechex(round($bluePercentage * 255));
		$redHex = dechex(round($redPercentage * 255));
		$greenHex = dechex(round($greenPercentage * 255));
		
		if(strlen($blueHex) == 1)
			$blueHex = "0" . $blueHex;
		if(strlen($redHex) == 1)
			$redHex = "0" . $redHex;
		if(strlen($greenHex) == 1)
			$greenHex = "0" . $greenHex;
		
		return "#" . $redHex . $greenHex . $blueHex;
		
	}
	
}


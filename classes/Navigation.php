<?

// Methods of this class return HTML components to help with navigation.  Tabs, Date Ranges, etc.
class Navigation {


	private $_baseURL;
	private $_stopDay;
	private $_stopMonth;
	private $_stopYear;
	private $_tabsArr = array();
	private $_nameValuPairs = array();
	
	
	###-- Constructor --###
	function Navigation(){

		$this->_baseURL = "";
		
		// EX. If we are looking at a Month/Year selection component and we are on the last month
		// ... we should not have the ability to go to the "Next" month.
		// By default the we stop on the Current Month, Year... but you may want to override that
		$this->_stopDay = date("j");
		$this->_stopMonth = date("n");
		$this->_stopYear = date("Y");


	}
	
	// Set a base URL that may be used by a number of navigation controls
	// For example, a date Selector control may just add the parameters "&month=11&year=2203" to the end of the base URL
	function SetBaseURL($x){
		$this->_baseURL = WebUtil::FilterURL($x);
	}
	function SetStopDay($x){
		$this->_stopDay = intval($x);
	}	
	function SetStopMonth($x){
		$this->_stopMonth = intval($x);
	}
	function SetStopYear($x){
		$this->_stopYear = intval($x);
	}
	
	// This may be used to for passing hidden inputs into one of our controls... to forward information across
	function SetNameValuePairs($hash){

		$this->_nameValuPairs = $hash;
	}
	
	
	
	// Make sure that there is a trailing Ampersand for the Name Value pair... so that we can append "&" . "offset=xx"
	private static function ensureAmersandSuffixOnNV_Pair($NV_pairs_URL){
		
		if(empty($NV_pairs_URL))
			return "";
		
		if(substr($NV_pairs_URL, (strlen($NV_pairs_URL) -1), 1) == "&")
			return $NV_pairs_URL;
			
		return $NV_pairs_URL .= "&";
	}

	// This function will build the numbers with links for us..  If there are multiple pages.
	function GetNumberNavigation($BaseURL, $ResultsCount, $NV_pairs_URL, $NumberOfResultsToDisplay, $offset, $offsetParameterName){
	
		$offset = intval($offset);
		$ResultsCount = intval($ResultsCount);
		$NumberOfResultsToDisplay = intval($NumberOfResultsToDisplay);
		
		WebUtil::FilterURL($BaseURL);
		WebUtil::FilterURL($NV_pairs_URL);
		
		if(!preg_match("/^\w{1,15}$/", $offsetParameterName))
			throw new Exception("Error in method GetNumberNavigation. The offset parameter name is wrong.");

		$NV_pairs_URL = self::ensureAmersandSuffixOnNV_Pair($NV_pairs_URL);

		$retHTML = "";
	
		// If it exceeds this.. then an HTML drop down will be created instead of links.
		$MaxPagesForLinks = 15;
	
		$numberOfPages = ceil($ResultsCount/$NumberOfResultsToDisplay);
	
		
	
		if($numberOfPages <= $MaxPagesForLinks){
			for($i=0; $i< $numberOfPages; $i++){
			
				if($offset/$NumberOfResultsToDisplay == $i)
					$retHTML .= "&nbsp;<font class='PagingSelectedNumber'>" . ($i+1) . "</font>&nbsp;";
				else
					$retHTML .= "&nbsp;<a class='PagingNumber' href='". WebUtil::FilterURL($BaseURL) .  "?" . WebUtil::FilterURL($NV_pairs_URL) . $offsetParameterName ."=" . ($i * $NumberOfResultsToDisplay) ."'>" . ($i+1) . "</a>&nbsp;";
			}
		}
		else{
		
			$retHTML = "<select class='PagingSelectList' name='searchresults' onChange='document.location = this.value;'>";
		
			for($i=0; $i< $numberOfPages; $i++){
			
				if($offset/$NumberOfResultsToDisplay == $i)
					$Selected = "selected";
				else
					$Selected = "";
				
				
				$retHTML .= "<option $Selected value='". WebUtil::htmlOutput($BaseURL) .  "?" . WebUtil::htmlOutput($NV_pairs_URL) . WebUtil::htmlOutput($offsetParameterName) . "=" . ($i * $NumberOfResultsToDisplay) ."'>Page " . ($i+1) . "  </option>";
			}
			
			$retHTML .= "</select>";
		}
	
		return $retHTML;
	
	}
	
	
	
	static function GetNavigationForSearchResults($BaseURL, $ResultsCount, $NV_pairs_URL, $NumberOfResultsToDisplay, $offset, $offsetParameterName = "offset"){
	
		$offset = intval($offset);
		$ResultsCount = intval($ResultsCount);
		$NumberOfResultsToDisplay = intval($NumberOfResultsToDisplay);
		
		if(!preg_match("/^\w{1,15}$/", $offsetParameterName))
			throw new Exception("Error in method GetNavigationForSearchResults. The offset parameter name is wrong.");
		
		$NV_pairs_URL = WebUtil::FilterURL($NV_pairs_URL);
		$BaseURL = WebUtil::FilterURL($BaseURL);
	
		$NV_pairs_URL = self::ensureAmersandSuffixOnNV_Pair($NV_pairs_URL);
		
		
		// Find out if there was a name/value pair which tells us to switch the Template Number on paging.
		// That allows us to use one template on the initial view... but when we switch to the subsequent pages it will continue to use another template ID.
		$matches = array();
		if(preg_match("/TemplateSwitchOnPaging=(\w*)/", $NV_pairs_URL, $matches)){
			$newTemplateID = $matches[1];
			
			// Replace the old template ID with the new one.
			// If there isn't a template number yet... we have to add one.
			if(!preg_match("/TemplateNumber=(\w*)/", $NV_pairs_URL)){
				$NV_pairs_URL .= "TemplateNumber=" . $newTemplateID . "&";
			}
			else{
				$NV_pairs_URL = preg_replace("/TemplateNumber=(\w*)/", ("TemplateNumber=" . $newTemplateID . "&"), $NV_pairs_URL);
			}
			
			// Now get rid of the TemplateSwitch command (because we already did the switch)
			$NV_pairs_URL = preg_replace("/TemplateSwitchOnPaging=(\w*)&/", "", $NV_pairs_URL);
		}
		
		
		
		if($offset == 0){
			$NavigateHTML = Navigation::GetNumberNavigation($BaseURL, $ResultsCount, $NV_pairs_URL, $NumberOfResultsToDisplay, $offset, $offsetParameterName) . "&nbsp;&nbsp;&nbsp;<a class='PagingNext' href='". $BaseURL . "?" . $NV_pairs_URL . $offsetParameterName . "=" . $NumberOfResultsToDisplay ."'>Next &gt;</a>";
		}
		else if($offset < $ResultsCount - $NumberOfResultsToDisplay){
			$NavigateHTML = "<a class='PagingPrevious' href='" . $BaseURL . "?" . $NV_pairs_URL .$offsetParameterName . "=" . ($offset - $NumberOfResultsToDisplay) ."'>&lt; Previous</a>&nbsp;&nbsp;&nbsp;";
			$NavigateHTML .= Navigation::GetNumberNavigation($BaseURL, $ResultsCount, $NV_pairs_URL, $NumberOfResultsToDisplay, $offset, $offsetParameterName) . "&nbsp;&nbsp;&nbsp;<a class='PagingNext' href='" . $BaseURL . "?" . $NV_pairs_URL . $offsetParameterName . "=" . ($offset + $NumberOfResultsToDisplay) ."'>Next &gt;</a>";
		}
		else{
			// This means that we are on the last page of the results.
			$NavigateHTML = "<a class='PagingPrevious' href='" . $BaseURL . "?" . $NV_pairs_URL . $offsetParameterName . "=" . ($offset - $NumberOfResultsToDisplay) ."'>&lt; Previous</a>&nbsp;&nbsp;&nbsp;" . Navigation::GetNumberNavigation($BaseURL, $ResultsCount, $NV_pairs_URL, $NumberOfResultsToDisplay, $offset, $offsetParameterName) ;
		}
	
		return $NavigateHTML;
	}
	
	
	
	
	// Get Year Month selector... shows a drop down for Year and Month with a "GO" button
	// It also has a Previous/Next link to the right side.  
	// The "Next" link will be omitted if the current Month/Year is the date in use
	// If $GiveChoiceByYear is given.. then a radio button will be given to choose, searching by year only, or by month/year.  
	// If by Year only then the month parameter is sent in the URL as "ALL".
	function GetYearMonthSelectorHTML($SelectedMonth, $SelectedYear, $GiveChoiceByYear = false){
		
		if(empty($this->_baseURL))
			throw new Exception("A base URL must be set before calling GetYearMonthSelectorHTML");

		$SelectedMonth = intval($SelectedMonth);
		$SelectedYear = intval($SelectedYear);

		if($SelectedMonth == "ALL"){
		
			$prevMonth = "ALL";
			$nextMonth = "ALL";
			
			$prevYear = $SelectedYear-1;
			$nextYear = $SelectedYear+1;
		}
		else{
			if($SelectedMonth == "12"){
				$nextMonth = "1";
				$nextYear = $SelectedYear+1;
			}
			else{
				$nextMonth = $SelectedMonth + 1;
				$nextYear = $SelectedYear;	
			}
			if($SelectedMonth == "1"){
				$prevMonth = "12";
				$prevYear = $SelectedYear-1;
			}
			else{
				$prevMonth = $SelectedMonth - 1;
				$prevYear = $SelectedYear;	
			}
		}
		
		
		
		$NameValuePairs = "";
		foreach($this->_nameValuPairs as $thisKey => $thisVal)
			$NameValuePairs .= "&$thisKey=" . urlencode($thisVal);
		
		$PreviousURL = $this->_baseURL . "?month=" . $prevMonth . "&year=" . $prevYear . $NameValuePairs;
		$NextURL = $this->_baseURL . "?month=" . $nextMonth . "&year=" . $nextYear . $NameValuePairs;
		
		$PreviousURL = WebUtil::FilterURL($PreviousURL);
		$NextURL = WebUtil::FilterURL($NextURL);
		
		$retHTML = "<table cellpadding='0' cellspacing='0' border='0'>
				<form name='MonthYearSelectForm' method='get' action='" . $this->_baseURL ."'><input type='hidden' name='form_sc' value='".WebUtil::getFormSecurityCode()."'><input type='hidden' name='form_sc' value='".WebUtil::getFormSecurityCode()."'>";
		
		// Build hidden inputs for each name value pair we have made
		foreach($this->_nameValuPairs as $thisKey => $thisVal)
			$retHTML .= "\n<input type='hidden' name='" . WebUtil::htmlOutput($thisKey) . "' value='".WebUtil::htmlOutput($thisVal)."'>";
		

		$retHTML .= "<tr><td class='body'>";
				
		if($GiveChoiceByYear)
			$retHTML .= Widgets::BuildMonthSelectWithAllChoice( $SelectedMonth, "month" );
		else
			$retHTML .= Widgets::BuildMonthSelect( $SelectedMonth, "month" );
			
		$retHTML .= "&nbsp;";
		$retHTML .= Widgets::BuildYearSelect( $SelectedYear, "year" );
		$retHTML .= "&nbsp;&nbsp;&nbsp;";
		$retHTML .= '<input type="submit" name="xx" value="GO" class="AdminButton" onMouseOver="this.style.background=\'#ffeeaa\';" onMouseOut="this.style.background=\'#FFFFDD\';">';
		$retHTML .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
		
		$retHTML .= "<a class='BlueRedLink' href='" . $PreviousURL. "'>&lt; Previous</a>";
		
		// Don't show the next link if we are past the Stop Date
		$PastStopMonthYearFlag = $SelectedMonth >= $this->_stopMonth && $this->_stopYear == $SelectedYear;
		$PastStopYearFlag = $SelectedYear > $this->_stopYear;
		
		if(!($PastStopMonthYearFlag || $PastStopYearFlag))
			$retHTML .= "&nbsp;&nbsp;&nbsp;<a class='BlueRedLink' href='".$NextURL."'>Next &gt;</a>";	
		
		$retHTML .= "</td></tr></form></table>";
		
		return $retHTML;
	}
	
	


	// Get our standard tab block.
	// If the $ViewName from this method matches the $ViewName from the AddTab method, then that tab will be highlighted
	// The corner base will change the image that is used to give a different look
	function GetTabsHTML($ViewName, $CornerBase = "corner"){

		if(!preg_match("/^\w{1,15}$/", $CornerBase))
			throw new Exception("Error in method GetTabsHTML. The CornerBase parameter is wrong.");
		
		$RetHTML = "<table cellpadding='0' cellspacing='0' border='0'><tr>";

		foreach($this->_tabsArr as $ThisViewName => $ThisNavHash){

			$ThisNavLabel = WebUtil::htmlOutput(key($ThisNavHash));
			$ThisNavLink = current($ThisNavHash);

			if(strtoupper($ThisViewName) == strtoupper($ViewName)){
				$BackgroundColor = "#3366CC";
				$CornerColor = "blue";
			}
			else{
				$BackgroundColor = "#666666";
				$CornerColor = "grey";
			}

			$RetHTML .= "
			<td bgcolor='$BackgroundColor' valign='top'><img src='./images/transparent.gif' border='0' width='5' height='6'></td>
			<td bgcolor='$BackgroundColor' nowrap class='SmallBody'><img src='./images/transparent.gif' border='0' width='1' height='3'><br>
			&nbsp;<a href='$ThisNavLink'><font color='#FFFFFF' style='text-decoration:none;'>$ThisNavLabel</font></a>&nbsp;
			<br><img src='./images/transparent.gif' border='0' width='1' height='3'></td>
			<td bgcolor='$BackgroundColor' valign='top'><img src='./images/$CornerBase-small-$CornerColor.gif' border='0' width='9' height='6'></td>
			<td><img src='./images/transparent.gif' border='0' width='4' height='1'></td>
			";
		}
		$RetHTML .= "</tr></table>";

		return $RetHTML;
	}
	
	// The first tab to be added will be the one seen on the left... the last is seen on the right
	// Quotes are not allowed inside of URLs, so in case you need to pass in a Javascript command, be sure it is not vunerable to XSS attacks.
	function AddTab($ViewName, $TabLabel, $TabURL, $filterURL = true){
		
		if($filterURL)
			$TabURL = WebUtil::FilterURL($TabURL);
		
		$this->_tabsArr["$ViewName"] = array($TabLabel => $TabURL );

	}

	
}






?>
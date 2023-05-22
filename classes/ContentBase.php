<?


class ContentBase {

	
	protected $_Title;
	protected $_MetaDescription;
	protected $_MetaTitle;
	protected $_Description;
	protected $_DescriptionBytes;
	protected $_DescriptionFormat;
	protected $_Links;
	protected $_HeaderHTML;
	protected $_ActiveFlag;
	
	protected $_CreatedByUserID;
	protected $_LastEditedByUserID;
	protected $_CreatedOn;
	protected $_LastEditedOn;
	protected $_DomainID;
	
	// Override the constructor
	function ContentBase(){

		
	}
	
	function loadContentByTitle($categoryTitle){
		throw new Exception("Error in Method loadContentByTitle.  You must override this method.");
	}
	
	function checkIfContentTitleExists($contentTitle){
		throw new Exception("Error in Method loadContentByTitle.  You must override this method.");
	}
	
	function loadContentByID($contentID){
		throw new Exception("Error in Method loadContentByID.  You must override this method.");
	}
	
	function checkIfContentIDexists($contentID){
		throw new Exception("Error in Method checkIfContentIDexists.  You must override this method.");
	}
	
	function getFrontEndURL(){
		throw new Exception("Error in Method getFrontEndURL.  You must override this method.");
	}

	function getTitle(){
	
		$this->_ensureContentLoaded();
		
		$returnTitle = $this->_Title;
		
		// Prevention against XSS attacks from before the content titles were filtered on input in the DB.
		$returnTitle = preg_replace("/</", "", $returnTitle);
		$returnTitle = preg_replace("/>/", "", $returnTitle);
		
		return $returnTitle;
	}
	
	function getCreatedByUserID(){
		return $this->_CreatedByUserID;
	}
	function getCreatedOnDate(){
		return $this->_CreatedOn;
	}
	function getLastEditedByUserID(){
	
		if(empty($this->_LastEditedByUserID))
			return $this->_CreatedByUserID;
			
		return $this->_LastEditedByUserID;
	}
	function getLasteEditedOnDate(){
	
		if(empty($this->_LastEditedByUserID))
			return $this->_CreatedOn;
			
		return $this->_LastEditedOn;
	}
	
	
	// Pass in True if  you wan the method to convert HTML entities... and also replace line breaks with "<br>"
	// If the message format is in HTML... then setting this flag to true will not have an affect.
	function getDescription($replaceLineBeaksWithHTMLflag = false){
		$this->_ensureContentLoaded();
		
		if($this->descriptionIsHTMLformat() || !$replaceLineBeaksWithHTMLflag){
			return $this->_Description;
		}
		else{
		
			$retDesc = WebUtil::htmlOutput($this->_Description);
			
			$retDesc = preg_replace("/\n/", "<br/>\n", $retDesc);
			$retDesc = preg_replace("/\r/", "", $retDesc);
			
			return $retDesc;
		}
	}
	
	// Filter out stuff that isn't really "URL Encode Safe".
	function filterTitle($theTitle){
		$theTitle = preg_replace("/\&/", "and", $theTitle);
		$theTitle = preg_replace("/\?/", "", $theTitle);
		$theTitle = preg_replace("/\+/", "", $theTitle);
		$theTitle = preg_replace("/\=/", "", $theTitle);
		$theTitle = preg_replace("/\//", "", $theTitle);
		$theTitle = preg_replace("/\\\\/", "", $theTitle);
		$theTitle = preg_replace("/</", "", $theTitle);
		$theTitle = preg_replace("/>/", "", $theTitle);
		$theTitle = preg_replace("/\n/", "", $theTitle);
		$theTitle = preg_replace("/\r/", "", $theTitle);
		$theTitle = preg_replace("/\:/", "", $theTitle);
		$theTitle = preg_replace("/\;/", "", $theTitle);
		$theTitle = WebUtil::FilterData($theTitle, FILTER_SANITIZE_STRING_ONE_LINE);
		return $theTitle;
	}
	
	
	
	function checkIfActive(){
		return $this->_ActiveFlag;
	}

	function checkIfHeaderHTMLexists(){
		if(empty($this->_HeaderHTML))
			return false;
		else
			return true;
	}
	
	function getHeaderHTML(){
		return $this->_HeaderHTML;
	}
	
	

	function getHeaderHTMLwithLinks($hyperLinksArr){

		$text = $this->_HeaderHTML;
		
		// Get rid of the Title temporarily so it doesn't get turned into a link.
		// If the title is "Cheap Business Cards" then it might get turned into Cheap <a href''>Business Cards</a> and break out title into pieces.
		// So this will be temporary brackets around the term to try and destory hyper links from the ends.
		$text = preg_replace("/(".preg_quote($this->_Title).")/i", "%temp%\\1%temp%", $text);
		
		// Make sure there is a Space or quote before.... we don't want to do something like... <a>business card</a>s
		foreach($hyperLinksArr as $linkDesc => $linkURL){
			$linkURL = htmlspecialchars($linkURL);
			$text = preg_replace("/(\s|;|\n|^)(" . preg_quote($linkDesc) . ")(\s|,|\.|\&|\n|$)/i", "\\1<a href='$linkURL'>\\2</a>\\3", $text);
		}
		
		$text = preg_replace("/%temp%/", "", $text);
		
		return $text;
	}
	
	function descriptionIsHTMLformat(){
		
		$this->_ensureContentLoaded();
		
		if($this->_DescriptionFormat == "H")
			return true;
		else if($this->_DescriptionFormat == "T")
			return false;
		else
			throw new Exception("Illegal description format for content Item: " . $this->_DescriptionFormat);
		
	}
	
	
	function getDescriptionBytes(){
	
		$this->_ensureContentLoaded();
		return $this->_DescriptionBytes;
	}
	
	
	// Pass in an array of hyperlinks that you want created on the next. The Key to the array is what you want linked... and the value is the URL.
	// Put the entries in the array or order of importance... like... put "business cards" before "business" ... otherwise "business" will eat up the "business cards" phrase.
	function getFormattedDescWithLinks($hyperLinksArr){
	
		// If the format is HTML... then let the user control their own line breaks, ec.
		if(!$this->descriptionIsHTMLformat()){
	
			$text = WebUtil::htmlOutput($this->_Description);

			$text = preg_replace("/\n/", "<br/>\n", $text);
		}
		else{
			$text = $this->_Description;
		}
		

		// Get rid of the Title temporarily so it doesn't get turned into a link.
		// If the title is "Cheap Business Cards" then it might get turned into Cheap <a href''>Business Cards</a> and break out title into pieces.
		// So this will be temporary brackets around the term to try and destory hyper links from the ends.
		$text = preg_replace("/(".preg_quote($this->_Title).")/i", "%temp%\\1%temp%", $text);
		
		// Make sure there is a Space or quote before.... we don't want to do something like... <a>business card</a>s
		foreach($hyperLinksArr as $linkDesc => $linkURL){
			$linkURL = htmlspecialchars($linkURL);
			$text = preg_replace("/(\s|;|\n|^)(" . preg_quote($linkDesc) . ")(\s|,|\.|\&|$|\n)/i", "\\1<a href='$linkURL'>\\2</a>\\3", $text);
		}
		
		$text = preg_replace("/%temp%/", "", $text);
		
		
		return $text;
	}
	
	// Gets a short blurb of the description.
	function getShortDescription(){
		$this->_ensureContentLoaded();
		
		if($this->descriptionIsHTMLformat()){
			$displayText =  strip_tags($this->_Description);
		
		}
		else{
			$displayText =  $this->_Description;
		}
		
		// Get the first 200 characters of the description... then append 3 periods to the end.
		$displayText = substr($displayText, 0, 400);
		
		// Strip off the last set of characters ... up to the space.
		// This will keep incomplete words from showing up.
		$displayText = preg_replace("/\w+$/", " ", $displayText);
		$displayText .= "...";
		
		// Put &amp; back to just an &
		$displayText = Webutil::unhtmlentities($displayText);
		
		return $displayText;
	}








	function getURLforImage(){
		
		throw new Exception("Error in function getURLforImage ... must override this method.");
	
	}


	
	function getURLforContent(){
		
		throw new Exception("Error in function getURLforContent ... must override this method.");
	
	}	
	
	// Returns the date that the piece of content was last edited on.
	function getDateLastModified(){
	
		$this->_ensureContentLoaded();
		
		if(empty($this->_LastEditedOn))
			return $this->_CreatedOn;
		
		return $this->_LastEditedOn;
	}
	
	

	function checkIfImageStored(){

		throw new Exception("Error in function checkIfImageStored ... must override this method.");
	}
	
	
	// Returns the Binary Data for the Image
	function &getImage($imageSize){
	
		throw new Exception("Error in function checkIfImageStored ... must override this method.");
	}
	
	
	// Returns an array of links.
	// The key is the Link Subject.. the Value is the URL
	function getLinksArr($linkSubjectAllCaps = false, $removeDirectives = false, $ignoreLinksWithDirective = ""){
	
		$retArr = array();
	
		if(!empty($this->_Links)){
			// We separate the values in our Database by Double ^^ and Double ** symbols
			$linksArr = split("\*\*", $this->_Links);

			foreach($linksArr as $thisLinkItem){

				$linksItemArr = split("\^\^", $thisLinkItem);
				
				if($linkSubjectAllCaps)
					$linkSubject = strtoupper($linksItemArr[0]);
				else
					$linkSubject = $linksItemArr[0];
				
				if(!empty($ignoreLinksWithDirective) && preg_match("/\[" . preg_quote($ignoreLinksWithDirective) . "\]/i", $linkSubject))
					continue;
				
				if($removeDirectives)
					$linkSubject = trim(preg_replace("/\[(\w+)\]/", "", $linkSubject));

				$retArr[$linkSubject] = $linksItemArr[1];
			}
		}
		
		return $retArr;
	}
	
	

	// Returns lines of text (with newline characters)... each Link Description and URL is separated by double equals symbols.

	function getLinksForEditing(){
	
		$linksArr = $this->getLinksArr();
		
		$retStr = "";
		
		foreach($linksArr as $linkDesc => $linkURL)
			$retStr .= $linkDesc . " == " . $linkURL . "\n";
		
		return $retStr;
	}
	
	
	
	// ------------   SET Methods ------------------------------------------------------------------------


	function setTitle($x){
		
		$x = trim($x);
		
		$x = $this->filterTitle($x);
		
			
		if(strlen($x) > 250)
			throw new Exception("Setting a content  title can not have more than 250 characters.");
		
		$this->_Title = $x;
	}
		

	
	function setDescription($x){
		
		$x = trim($x);
	
		if(empty($x))
			throw new Exception("If you are setting the Content Description... it can not be left Null.");
		
		$this->_DescriptionBytes = strlen($x);
		
		$this->_Description = $x;
	}
	
	

	function setDescriptionFormat($x){
		
		$x = trim($x);
		
		if(strtoupper($x) == "HTML")
			$x = "H";
			
		if(strtoupper($x) == "TEXT")
			$x = "T";
	
		if(!in_array($x, array("H", "T")))
			throw new Exception("The content Description format can only be HTML or Text.");
		
		$this->_DescriptionFormat = $x;
	}
	

	function setHeaderHTML($x){
		return $this->_HeaderHTML = $x;
	}
	
	function setActive($flag){
		if(!is_bool($flag))
			throw new Exception("Flag must be boolean");
			
		$this->_ActiveFlag = $flag;
	}
	
	
	// Returns null if the links we able to be parsed, otherwise it returns an error message.
	function setLinks($data){
	
		if(empty($data)){
			$this->_Links = "";
			return null;
		}
	
	
		$linkBreaksArr = split("\n", $data);
		
		$encodedLinks = "";
		
		$counter = 1;
		
		foreach($linkBreaksArr as $thisLinksDesc){
			
			$thisLinksDesc = trim($thisLinksDesc);
			
			if(empty($thisLinksDesc))
				continue;
		
			$linkParts = split("==", $thisLinksDesc);
			
			if(sizeof($linkParts) != 2)
				return "Error on Link # " . $counter . ". Double Equal signs were not found.";
			
			$linkDesc = trim($linkParts[0]);
			$linkURL = trim($linkParts[1]);
			
			if(!preg_match("/^http(s)?:\/\//i", $linkURL))
				return "Error on Link # " . $counter . ". A valid (fully qualified) URL such as http://www.Domain.com was not found.";
				
			if(empty($linkDesc) || strlen($linkDesc) < 4)
				return "Error on Link # " . $counter . ". A Link was not found before double equals sign... or the length of the string was less than 4 characters.";
			
			
			// We may have some directives for the Link description
			$matches = array();
			if(preg_match_all("/\[((\w|\d|\s)+)\]/", $linkDesc, $matches)){
			
				foreach($matches[1] as $thisDirective){
					if(!in_array(strtolower($thisDirective), array("notemplates", "noitems", "nolinks")))
						return "Error on Link # " . $counter . ". The link directive was entered incorrectly .... [$thisDirective]";
				}
				
				// Now clean up the directives... make them all uppcase and put them in front of the description with a space.
				$linkDesc = preg_replace("/\[(\w|\s|\d)+\]/", "", $linkDesc);
				
				$directivesStr = "";
				
				$directivesArr = array();
				
				// Prevent Duplicates
				foreach($matches[1] as $thisDirective){
				
					$thisDirective = strtoupper($thisDirective);
					
					if(in_array($thisDirective, $directivesArr))
						continue;
					
					$directivesStr .= "[" . $thisDirective . "] ";
					
					$directivesArr[] = $thisDirective;
				}
			
				$linkDesc = $directivesStr . trim($linkDesc);
			
				
			}
			
			if(!empty($encodedLinks))
				$encodedLinks .= "**";
			
			$encodedLinks .= $linkDesc . "^^" . $linkURL;
			
			$counter++;
		}
	
		$this->_Links = $encodedLinks;
		
		return null;
	}
	
	function getDirectivesFromLinkSubject($str){
	
		$retArr = array();
		
		$matches = array();
		if(preg_match_all("/\[(\w+)\]/", $str, $matches)){
		
			foreach($matches[1] as $thisDirective)
				$retArr[] = $thisDirective;
		}
		
		return $retArr;
	}
	
	function getLinkSubjectWithoutDirectives($str){
	
		$str = preg_replace("/\[(\w+)\]/", "", $str);
		
		return trim($str);
	}
	
	
	function getAllDirectivesFromAllLinks(){
	
		$retArr = array();
	
		$linksArr = $this->getLinksArr(false, false);
		
		$linkSubjectsArr = array_keys($linksArr);
		
		foreach($linkSubjectsArr as $thisLinkSubject){
		
			$directivesArr = $this->getDirectivesFromLinkSubject($thisLinkSubject);
			
			$retArr = array_merge($retArr, $directivesArr);
				
		}
		
		return $retArr;
	}
	
	
	function getFooter(){
		throw new Exception("Error in Method getFooter.  You must override this method.");
	}
	function footerIsHTMLformat(){
		throw new Exception("Error in Method footerIsHTMLformat.  You must override this method.");
	}
	
	
	
	// ------------   PRIVATE Methods --------------------------------------------------------------------------
	
	
	protected function _ensureDigit($x){
		
		if(!preg_match("/^\d+$/", $x))
			throw new Exception("Not a digit somewhere in the Content System.");
	
	}
	
	
	
	protected function _ensureContentLoaded(){
	
		throw new Exception("Error in method _ensureContentLoaded ... this method must be overrided from the ContentBase class.");
	}


}



?>
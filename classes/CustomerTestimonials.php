<?php

class CustomerTestimonials {
	
	private $dbCmd;
	
	private $testimonialID;
	private $domainID;
	private $status;
	private $editedByUserID;
	private $createdByUserID;
	private $testimonialOriginal;
	private $testimonialModified;
	private $firstName;
	private $email;
	private $city;
	private $dateCreated;
	private $dateLastEdited;
	
	private $dbHash = array();
	
	private $statusChoices = array();
	
	const STATUS_PENDING = "P";
	const STATUS_APPROVED = "A";
	const STATUS_DELETED = "D";
	
	function __construct(){
		
		$this->domainID = Domain::oneDomain();
		
		$this->dbCmd = new DbCmd();
		
		$this->statusChoices = array(self::STATUS_APPROVED, self::STATUS_PENDING, self::STATUS_DELETED);
		
		$this->createdByUserID = 0;
		$this->editedByUserID = 0;
	}
	
	// Testimonials are returned with the most recent record first.
	// Pass in the offset count that you want to start... such has the 100th.... then pass in a limit of how many you want returned.
	static function getTopTestimonials($offset, $limit){
		
		$offset = intval($offset);
		$limit = intval($limit);
		
		if($limit <= 0)
			throw new Exception("The limit must be greater than or equal to zero.");
		if($offset < 0)
			throw new Exception("The offset must be greater than zero.");
		
		$dbCmd = new DbCmd();
		
		$dbCmd->Query("SELECT ID FROM customertestimonials WHERE DomainID=" . Domain::oneDomain() . " 
							AND Status='".self::STATUS_APPROVED."' ORDER BY ID DESC LIMIT " . intval($offset + $limit));
		$bottomGroupIDs = $dbCmd->GetValueArr();
		
		$returnIDarr = array();
		$counter = 0;
		
		foreach($bottomGroupIDs as $thisTestimonialID){
			
			if($counter >= $offset)
				$returnIDarr[] = $thisTestimonialID;
				
			$counter++;
		}
		
		return $returnIDarr;
		
	}
	
	
	static function getPendingCount(array $domainIDsArr){
		
		foreach($domainIDsArr as $thisDomainID){
			if(!Domain::checkIfDomainIDexists($thisDomainID))
				throw new Exception("Domain ID does not exist for testimonials.");
		}

		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT COUNT(*) FROM customertestimonials WHERE " . DbHelper::getOrClauseFromArray("DomainID", $domainIDsArr) . " 
							AND Status='".self::STATUS_PENDING."'");
		return $dbCmd->GetValue();
	}
	
	
	function loadTestimonialById($id){

		$this->dbCmd->Query("SELECT *, 
							UNIX_TIMESTAMP(DateCreated) as UnixDateCreated, 
							UNIX_TIMESTAMP(DateLastEdited) as UnixDateLastEdited
							FROM customertestimonials WHERE ID=" . intval($id) . " AND DomainID=" . $this->domainID);
		
		if($this->dbCmd->GetNumRows() == 0)
			throw new Exception("Error, the Testimonial ID does not exist: " . intval($id));
			
		$row = $this->dbCmd->GetRow();

		$this->testimonialID = $row["ID"];
		$this->editedByUserID = $row["EditedByUserID"];
		$this->createdByUserID = $row["CreatedByUserID"];
		$this->testimonialOriginal = $row["TestimonialOriginal"];
		$this->testimonialModified = $row["TestimonialModified"];
		$this->firstName = $row["FirstName"];
		$this->email = $row["Email"];
		$this->city = $row["City"];
		$this->status = $row["Status"];
		$this->dateCreated = $row["UnixDateLastEdited"];
		$this->dateLastEdited = $row["UnixDateLastEdited"];
		
	}


	// Returns the ID that was just created.
	function createNewRecord(){
		
		if(!empty($this->testimonialID))
			throw new Exception("You can't create a new record if you are editing");
			
		$this->checkValues();
		
		$this->fillCommonFields();
		
		return $this->dbCmd->InsertQuery("customertestimonials", $this->dbHash);
		
	}
	
	function updateTestimonial(){
		
		if(empty($this->testimonialID))
			throw new Exception("You can't update a testimonial if you are not editing");
		
		if(empty($this->editedByUserID))
			throw new Exception("If you are updating a testimonial, you must provide an Editor User ID.");

		if(empty($this->dateLastEdited))
			throw new Exception("If you are updating... you must supply a date last edited time stamp.");
			
		$this->checkValues();
		
		$this->fillCommonFields();
		
		$this->dbCmd->UpdateQuery("customertestimonials", $this->dbHash, "ID=" . $this->testimonialID);
		
	}
	
	private function fillCommonFields(){
		
		$this->dbHash = array();
		
		$this->dbHash["DomainID"] = $this->domainID;
		$this->dbHash["Status"] = $this->status;
		$this->dbHash["CreatedByUserID"] = $this->createdByUserID;
		$this->dbHash["EditedByUserID"] = $this->editedByUserID;
		$this->dbHash["FirstName"] = $this->firstName;
		$this->dbHash["Email"] = $this->email;
		$this->dbHash["City"] = $this->city;
		$this->dbHash["DateCreated"] = DbCmd::FormatDBDateTime($this->dateCreated);
		$this->dbHash["TestimonialOriginal"] = $this->testimonialOriginal;
		$this->dbHash["DateCreated"] = DbCmd::FormatDBDateTime($this->dateCreated);
		
		// No need to store identical values.
		if(md5($this->testimonialOriginal) == md5($this->testimonialModified))
			$this->dbHash["TestimonialModified"] = null;
		else
			$this->dbHash["TestimonialModified"] = $this->testimonialModified;
		
		// If we are creating a new testimonial, the DateLastEdited should be the time that we create it.
		if(empty($this->testimonialID))
			$this->dbHash["DateLastEdited"] = DbCmd::FormatDBDateTime($this->dateCreated);
		else
			$this->dbHash["DateLastEdited"] = DbCmd::FormatDBDateTime($this->dateLastEdited);
		
	}
	
	private function checkValues(){
		
		if(empty($this->testimonialOriginal))
			throw new Exception("Testimonial is missing");
		if(empty($this->firstName))
			throw new Exception("First Name is missing");
		if(empty($this->city))
			throw new Exception("City is missing.");
		if(empty($this->status))
			throw new Exception("Status is missing.");
		if(empty($this->dateCreated))
			throw new Exception("Date Created is missing.");
		if(empty($this->dateLastEdited))
			throw new Exception("Date Last Modified is missing.");

		
	}
	
	
	
	
	
	// ---- SET Methods ------------------------------
	
	
	function setFirstName($name){
		$this->firstName = ucwords(WebUtil::FilterData($name, FILTER_SANITIZE_STRING_ONE_LINE));
	}

	function setEmail($email){
		if(!WebUtil::ValidateEmail($email))
			throw new Exception("Illegal Email address.");
		$this->email = WebUtil::FilterData($email, FILTER_SANITIZE_EMAIL);
	}
	
	function setCity($cityName){
		$this->city = ucwords(WebUtil::FilterData($cityName, FILTER_SANITIZE_STRING_ONE_LINE));
	}
	
	function setStatus($statusChar){
		
		if(strlen($statusChar) != 1)
			throw new Exception("Illegal length on status character.");
		
		if(!in_array($statusChar, $this->statusChoices))
			throw new Exception("Illegal Status Type");
			
		$this->status = $statusChar;
	}

	function setEditedByUserID($userID){
		$this->editedByUserID = intval($userID);
	}

	function setCreatedByUserID($userID){
		
		if(!empty($this->testimonialID))
			throw new Exception("You can change the CreatedByUserID if you are editing");
		
		$this->createdByUserID = intval($userID);
	}

	function setDateLastEdited($timeStamp){
		
		$timeStamp = intval($timeStamp);
		
		$this->dateLastEdited = $timeStamp;
	}
	
	function setDateCreated($timeStamp){
		$this->dateCreated = intval($timeStamp);
	}
	
	// If are editing.. then it will set the "Modified" text.  Otherwise it will fill in the "Original" text.
	function setTestimonial($text){
		
		// Do extra filtering on the text.
		$textArr = split("\n", $text);
		
		$filteredText = "";
		
		foreach($textArr as $thisLine){
			
			// Don't put a line break on the last line.
			if(!empty($filteredText))
				$filteredText .= "\n";
				
			$filteredText .= WebUtil::FilterData($thisLine, FILTER_SANITIZE_STRING_ONE_LINE);
		}

		if(!empty($this->testimonialID))
			$this->testimonialModified = $filteredText;
		else
			$this->testimonialOriginal = $filteredText;
		
	}
	
	
	
	
	
	// ---- GET Methods ------------------------------
	
	
	function getFirstName(){
		return $this->firstName;
	}
	
	function getEmail(){
		return $this->email;
	}
	function getCity(){
		return $this->city;
	}
	
	
	function getStatus(){
		
		if(empty($this->status))
			return self::STATUS_PENDING;
			
		return $this->status;
	}

	function getEditedByUserID(){
		return $this->editedByUserID;
	}

	function getCreatedByUserID(){
		return $this->createdByUserID;
	}
	
	function getDateCreated(){
		return $this->dateCreated;
	}
	function getDateLastEdited(){
		return $this->dateLastEdited;
	}
	
	// If the testimonial has been modified it will return the latest version.
	// If you set $blockQuotesFlag to TRUE, then the special tags [START] and [END] will be replaced with <blockquote> tags.
	function getTestimonial($formatHTML = true, $blockQuotesFlag = true){
		
		if(!empty($this->testimonialModified) && !empty($this->testimonialModified))
			$returnTestimonial = $this->testimonialModified;
		else
			$returnTestimonial = $this->testimonialOriginal;
			
		if($blockQuotesFlag && !$formatHTML)
			throw new Exception("If you want to Block Quotes, then you must format as HTML too.");
			
			
		if($formatHTML){
			$returnTestimonial = WebUtil::htmlOutput($returnTestimonial);
			$returnTestimonial = preg_replace("/\n/", "<br/>\n", $returnTestimonial);
		}
			
		if(!$blockQuotesFlag || !preg_match("/\[START\]/", $returnTestimonial) || !preg_match("/\[END\]/", $returnTestimonial))
			return $returnTestimonial;
			
		// Make sure we have an equal number of [START] and [END] tags so that we don't break the HTML structure.
		$startMatchesArr = array();
		preg_match_all("/\[START\]/", $returnTestimonial, $startMatchesArr);
		$endMatchesArr = array();
		preg_match_all("/\[END\]/", $returnTestimonial, $endMatchesArr);
		
		if(sizeof($startMatchesArr[0]) != sizeof($endMatchesArr[0]))
			return $returnTestimonial;
			
		// Get rid of blank spaces in between Reply Tags.
		$returnTestimonial = preg_replace("/\[START\](\s|\n|<br\\/>)*/", "[START]", $returnTestimonial);
		$returnTestimonial = preg_replace("/(\s|\n|<br\\/>)*\[END\]/", "[END]", $returnTestimonial);
			
		$returnTestimonial = preg_replace("/\[START\]/", "<blockquote class='quote_feedback'>", $returnTestimonial);
		$returnTestimonial = preg_replace("/\[END\]/", "</blockquote>", $returnTestimonial);
		
		return $returnTestimonial;
	}
	
	function getTestimonialOriginal(){
		return $this->testimonialOriginal;
	}
	
	
	
	

	

}





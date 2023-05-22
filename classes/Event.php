<?php

// If you have Multiple Domains Selected
// And if you "Update" an Event... it will look for other signatures in your Selected Domain Pool and copy the changes over.
// But only if the event was already saved on that domain before.
// So if a user previously added an Event for 2 domain IDs... but later edited that event with 4 domain ID's selected
// Then it will only updatd the 2 Domain IDs.  It won't add the event for the other missing domains.
// If the user has less Domains selected now then when the Event was added... then it will break the associations (if the description changes)

// You can only set Product Delays on Product IDs that "define their own Production" (Production Piggy Back).
// If a reseller is using a Production Piggyback... then on his domain he will see a Production Delay from another Domain if it is linked (however he can't change it).

class Event {
	
	private $_dbCmd;
	
	private $eventID;
	private $productID;
	private $title;
	private $description;
	private $delaysTransit;
	private $delaysProduction;
	private $eventDate;
	private $descriptionSignature;
	private $startMinute;
	private $endMinute;
	private $userIdCreated;
	private $userIdEdited;
	private $createdOn;
	private $editedOn;
	private $db_EventSignature;
	private $db_TitleSignature;
	
	
	private $passiveAuthObj;
	private $eventLoadedFlag;
	private $userIsLookingAtEventFromAnotherDomain;
	
	
	function __construct() {
		
		$this->_dbCmd = new DbCmd();
		$this->passiveAuthObj = Authenticate::getPassiveAuthObject();
		$this->eventLoadedFlag = false;
		$this->userIsLookingAtEventFromAnotherDomain = false;
	}
	
	// You can only load an Event by a Detailed Event Signature.
	// "Title signatures" Can't be uniquely identified.  There may be many separate events with the Same Title on the same day.
	// Make sure that the event signature exists before calling this method
	// And you must also have at least 1 domain selected in your session that matches the signature.
	function loadEventBySignature($signature){
		
		$domainObj = Domain::singleton();

		$this->_dbCmd->Query("SELECT ID FROM eventschedule WHERE EventSignature='".DbCmd::EscapeSQL($signature)."' AND " . 
						DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()) . " LIMIT 1");

		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method loadEventBySignature");
		
		$this->loadEventByID($this->_dbCmd->GetValue());
	}
	
	function getEventTitleFromTitleSignature($titleSignature){
		
		$this->_dbCmd->Query("SELECT Title FROM eventschedule WHERE TitleSignature='".DbCmd::EscapeSQL($titleSignature)."' LIMIT 1");
		
		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method getEventTitleFromTitleSignature");
			
		return $this->_dbCmd->GetValue();
	}
	
	// The Title signature includes whether it delays Production or Transit.
	// So it is quick to figure out if there is a delay by doing an "indexed SQL query".
	// We don't have to check whether they have permission to see the Title... because you should only be checking titles you know are already in their selected Domain List.
	function checkIfTitleSignatureHasProductionDelay($titleSignature){
		
		$this->_dbCmd->Query("SELECT DelaysProduction FROM eventschedule WHERE TitleSignature='".DbCmd::EscapeSQL($titleSignature)."' LIMIT 1");
		
		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method checkIfTitleSignatureHasProductionDelay");
			
		if($this->_dbCmd->GetValue() == "Y")
			return TRUE;
		else
			return FALSE;
	}
	
	function checkIfTitleSignatureHasTransitDelay($titleSignature){
		
		$this->_dbCmd->Query("SELECT DelaysTransit FROM eventschedule WHERE TitleSignature='".DbCmd::EscapeSQL($titleSignature)."' LIMIT 1");
		
		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method checkIfTitleSignatureHasTransitDelay");
			
		if($this->_dbCmd->GetValue() == "Y")
			return TRUE;
		else
			return FALSE;
	}
	
	
	// The Product ID has been mashed up in the Title Signature.  So it is OK to check only one of the rows.
	// Returns 0 if there is not Product Attachment.
	function getProductIdFromTitleSignature($titleSignature){
		
		$this->_dbCmd->Query("SELECT ProductID FROM eventschedule WHERE TitleSignature='".DbCmd::EscapeSQL($titleSignature)."' LIMIT 1");
		
		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method getProductIdFromTitleSignature");
			
		return $this->_dbCmd->GetValue();
	}
	
	
	
	// Returns an array of Event Signatures (from a Title Signature).  You can have many events grouped into 1 title.
	// Only Event Signatures are returned if they belong to Domains which the user has selected.
	function getEventSignaturesFromTitleSignature($titleSignature){
		
		$domainObj = Domain::singleton();
		
		$this->_dbCmd->Query("SELECT DISTINCT EventSignature FROM eventschedule WHERE TitleSignature='".DbCmd::EscapeSQL($titleSignature)."' AND " . 
						DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()) . " ORDER BY StartMinute ASC, Description ASC");
		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method getEventSignaturesFromTitleSignature");
			
		return $this->_dbCmd->GetValueArr();
	}
	
	function loadEventByID($eventID){

		$this->_dbCmd->Query("SELECT *, 
					UNIX_TIMESTAMP(EventDate) AS EventDate, 
					UNIX_TIMESTAMP(CreatedOn) AS CreatedOn, 
					UNIX_TIMESTAMP(EditedOn) AS EditedOn 
					FROM eventschedule WHERE ID=" . intval($eventID));
		
		if($this->_dbCmd->GetNumRows() == 0)
			throw new Exception("Error in method loadEventByID");
			
		$row = $this->_dbCmd->GetRow();
		
		// If this is an event with a Production Delay. Then we will let a user load the event 
		// ...(but only if they have that Product in their domain pool with the same Production Product ID).
		if(!$this->passiveAuthObj->CheckIfUserCanViewDomainID($row["DomainID"])){
		
			if($this->checkIfEventBelongsToProductionProductdIDotherDomain($row["DomainID"], $row["ProductID"]))
				$this->userIsLookingAtEventFromAnotherDomain = TRUE;
			else
				throw new Exception("Error with Domain in loadEventByID");
		}
			
		
		$this->eventID = $row["ID"];
		$this->productID = $row["ProductID"];
		$this->description = $row["Description"];
		$this->delaysTransit = $row["DelaysTransit"] == "Y" ? true : false;
		$this->delaysProduction = $row["DelaysProduction"] == "Y" ? true : false;
		$this->eventDate = $row["EventDate"];
		$this->db_EventSignature = $row["EventSignature"];
		$this->db_TitleSignature = $row["TitleSignature"];
		$this->startMinute = $row["StartMinute"];
		$this->endMinute = $row["EndMinute"];
		$this->userIdCreated = $row["UserIdCreated"];
		$this->userIdEdited = $row["UserIdEdited"];
		$this->createdOn = $row["CreatedOn"];
		$this->editedOn = $row["EditedOn"];
		$this->title = $row["Title"];

		$this->eventLoadedFlag = true;

	}
	

	
	
	// Finds out if the user Does not have permission to view the given Domain ID
	// ... but they can see the event because they have a Product ID in their Domain which is needed for Production.
	function checkIfEventBelongsToProductionProductdIDotherDomain($domainID, $productID){
		
		if(!Domain::checkIfDomainIDexists($domainID))
			throw new Exception("Error in method checkIfEventBelongsToProductionProductdIDotherDomain with Domain");
		
		if(!Product::checkIfProductIDexists($this->_dbCmd, $productID))
			throw new Exception("Error in method checkIfEventBelongsToProductionProductdIDotherDomain with Product");


		// If they can view the Domain already then there is not a point in calling this method.
		if($this->passiveAuthObj->CheckIfUserCanViewDomainID($domainID))
			return false;
			
		$allProductIDsLinkedForProduction = Product::getAllProductIDsSharedForProduction($this->_dbCmd, $productID);
		
		foreach($allProductIDsLinkedForProduction as $thisProductID){
			
			$thisDomainID = Product::getDomainIDfromProductID($this->_dbCmd, $thisProductID);
			
			if($this->passiveAuthObj->CheckIfUserCanViewDomainID($thisDomainID))
				return true;
		}
		
		return false;
	}
	
	
	// Similar to Updateing... Deleted the all of the Events in the Selected Domain pool.
	function deleteEvent(){

		if(!$this->eventLoadedFlag)
			throw new Exception("Error in Event::updateEvent");
			
		if($this->userIsLookingAtEventFromAnotherDomain)
			throw new Exception("Do not have permission to delete event.");
			
		$domainObj = Domain::singleton();
		$selectedDomainIDs = $domainObj->getSelectedDomainIDs();
		
		// Don't forget to delete against the "old" event signature.
		foreach($selectedDomainIDs as $thisDomainID){
			$this->_dbCmd->Query("DELETE FROM eventschedule WHERE DomainID=$thisDomainID AND EventSignature='".$this->db_EventSignature."'");
		}
		
		$this->eventLoadedFlag = false;
	}
	
	
	function updateEvent(){
		
		if(!$this->eventLoadedFlag)
			throw new Exception("Error in Event::updateEvent");
			
		if($this->userIsLookingAtEventFromAnotherDomain)
			throw new Exception("Do not have permission to udpate event.");
			
		$this->verifyAllFields();
		
		$this->editedOn = mktime(12, 0, 0, date("n"), date("j"), date("Y"));
		$this->userIdEdited = $this->passiveAuthObj->GetUserID();
		
		$editRow = $this->getDBrowHash();
		
		// Update Event for every domain which the user has selected...
		// But only if the event was already saved on that domain before.
		// So if a user previously added a domain for 2 Ids... but later edited that event with 4 domain ID's selected
		// Then it will only updatd the 2 Domain IDs 
		// If the user has less Domains selected now then when the Event was added... then it will break the associations (if the description changes)
		$domainObj = Domain::singleton();
		$selectedDomainIDs = $domainObj->getSelectedDomainIDs();
		
		foreach($selectedDomainIDs as $thisDomainID){
			$editRow["DomainID"] = $thisDomainID;
			
			// Don't forget to update against the "old" event signature.
			$this->_dbCmd->UpdateQuery("eventschedule", $editRow, ("DomainID=$thisDomainID AND EventSignature='".$this->db_EventSignature."'"));
		}
		
	}
	
	function createEvent(){
		
		$this->verifyAllFields();
		
		$this->createdOn = mktime(12, 0, 0, date("n"), date("j"), date("Y"));
		$this->userIdCreated = $this->passiveAuthObj->GetUserID();
			
		$addRow = $this->getDBrowHash();
		
		// Insert Event for every domain which the user has selected.
		$domainObj = Domain::singleton();
		$selectedDomainIDs = $domainObj->getSelectedDomainIDs();
		
		foreach($selectedDomainIDs as $thisDomainID){
			
			// Don't Add Duplicates
			$this->_dbCmd->Query("SELECT COUNT(*) FROM eventschedule WHERE DomainID=$thisDomainID 
									AND EventSignature='".$this->getEventSignature()."'");
			if($this->_dbCmd->GetValue() != 0)
				continue;
			
			$addRow["DomainID"] = $thisDomainID;
			$this->_dbCmd->InsertQuery("eventschedule", $addRow);
		}
		
		$this->eventLoadedFlag = true;
	}
	
	private function getDBrowHash(){
		
		$retArr["ProductID"] = $this->productID;
		$retArr["DelaysTransit"] = $this->delaysTransit ? "Y" : "N";
		$retArr["DelaysProduction"] = $this->delaysProduction ? "Y" : "N";
		$retArr["EventDate"] = $this->eventDate;
		$retArr["EventSignature"] = $this->getEventSignature();
		$retArr["TitleSignature"] = $this->getTitleSignature();
		$retArr["Description"] = $this->description;
		$retArr["StartMinute"] = $this->startMinute;
		$retArr["EndMinute"] = $this->endMinute;
		$retArr["UserIdCreated"] = $this->userIdCreated;
		$retArr["UserIdEdited"] = $this->userIdEdited;
		$retArr["CreatedOn"] = $this->createdOn;
		$retArr["EditedOn"] = $this->editedOn;
		$retArr["Title"] = $this->title;
	
		return $retArr;
	}
	
	private function verifyAllFields(){
		
		if((empty($this->startMinute) && !empty($this->endMinute)) || (!empty($this->startMinute) && empty($this->endMinute)))
			throw new Exception("Error updating Event. If you provide a Start Minute you must also provide an end minute and visa versa.");
			
		if(empty($this->title))
			throw new Exception("Error in method Event:verifyAllFields. The Title was blank.");
			
		if(empty($this->eventDate))
			throw new Exception("Error in method Event:verifyAllFields. The event date was left blank.");
			
		if(!empty($this->startMinute) && ($this->delaysProduction || $this->delaysTransit))
			throw new Exception("Error in method Event:verifyAllFields. You can not put a time range on an event if you are also setting Production/Transit delays.");

	}
	
	function getEventID() {
		
		if(!$this->eventLoadedFlag)
			throw new Exception("Error in Event::getEventID");
		
		return $this->eventID;
	}
	
	function getProductID() {
		
		if(!$this->eventLoadedFlag)
			throw new Exception("Error in Event::getProductID");
		
		return $this->productID;
	}
	function getDescription() {
		
		if(!$this->eventLoadedFlag)
			throw new Exception("Error in Event::getDescription");
			
		return $this->description;
	}
	function getTitle() {
		
		if(!$this->eventLoadedFlag)
			throw new Exception("Error in Event::getTitle");
			
		return $this->title;
	}
	function getDelaysTransit() {
		
		if(!$this->eventLoadedFlag)
			throw new Exception("Error in Event::getDelaysTransit");
			
		return $this->delaysTransit;
	}
	function getDelaysProduction() {
		
		if(!$this->eventLoadedFlag)
			throw new Exception("Error in Event::getDelaysProduction");
			
		return $this->delaysProduction;
	}
	// Returns a Unix Timestamp.  Don't pay attention to the Hours, Minutes, or Seconds.
	function getEventDate() {
		
		if(!$this->eventLoadedFlag)
			throw new Exception("Error in Event::getEventDate");
			
		return $this->eventDate;
	}
	
	// If the user has selected Multiple Domains
	// And if the Event Signature Matches other events in that Pool.
	// Then you can consider the Event to be a "Single Event" and it will return all DomainID's that share the signature.
	function getDomainIDs($onlySelectedDomains = true) {
	
		if(!$this->eventLoadedFlag)
			throw new Exception("Error in Event::getDomainIDs");
		
		$domainObj = Domain::singleton();
			
		$query = "SELECT DISTINCT DomainID FROM eventschedule WHERE EventSignature='".DbCmd::EscapeSQL($this->db_EventSignature)."' ";
		if($onlySelectedDomains)
			$query .= " AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs());

		$this->_dbCmd->Query($query);
		return $this->_dbCmd->GetValueArr();
	}
	



	// Returns NULL if there is no Time Range.  End minute will also be Null.
	// If either the startMinute or the endMinute has a value set, so will the other.
	function getStartMinute() {
		
		if(!$this->eventLoadedFlag)
			throw new Exception("Error in Event::getStartMinute");
			
		return $this->startMinute;
	}
	function getEndMinute() {
		
		if(!$this->eventLoadedFlag)
			throw new Exception("Error in Event::getEndMinute");
		
		return $this->endMinute;
	}
	
	// Returns NULL if no Time Range, otherwise returns a description like 2:20 AM
	function getStartMinuteDisplay(){
		
		if(!$this->eventLoadedFlag)
			throw new Exception("Error in Event::getStartMinuteDisplay");
			
		if(empty($this->startMinute))
			return NULL;

		// Because we don't allow the Start Minute to be Zero.
		// Set it to 1 if someone was trying to do 12am.
		if($this->startMinute == 1)
			$actualStartMinute = 0;
		else
			$actualStartMinute = $this->startMinute;
		
		$begingDayTS = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
		
		return date("g:i a", ($begingDayTS + $actualStartMinute * 60));
	}
	function getEndMinuteDisplay(){
		
		if(!$this->eventLoadedFlag)
			throw new Exception("Error in Event::getEndMinuteDisplay");
			
		if(empty($this->endMinute))
			return NULL;

		$begingDayTS = mktime(0, 0, 0, date("m"), date("d"), date("Y"));
		
		return date("g:i a", ($begingDayTS + $this->endMinute * 60));
	}
	
	// 38 bit signature
	// This is useuful to figure out how to make copies for the other domains you have permission to see.
	function getEventSignature() {
	
		$date_sig = date("y", $this->eventDate) . date("m", $this->eventDate). date("d", $this->eventDate);
		
		$md5_sig = md5($this->productID . $this->description . $this->title . 
		($this->delaysProduction ? "Y" : "N") . ($this->delaysTransit ? "Y" : "N") . 
		$this->startMinute . $this->endMinute . $this->eventDate);
		
		return $date_sig . $md5_sig;
	}
	
	// Returns a 38 bit signature.
	// The signature does not include the Description or the Start/End Minute.  But it does include the "Event Date".
	function getTitleSignature() {
	
		$date_sig = date("y", $this->eventDate) . date("m", $this->eventDate). date("d", $this->eventDate);
		
		$md5_sig = md5($this->productID . $this->title . $this->eventDate .
		($this->delaysProduction ? "Y" : "N") . ($this->delaysTransit ? "Y" : "N"));
		
		return $date_sig . $md5_sig;
	}
	
	
	
	
	
	
	
	
	//--------------  Setter Methods --------------------
	
	
	
	
	function setProductID($productID = null) {
	
		if(!empty($productID)){
			
			if(!Product::checkIfProductIDexists($this->_dbCmd, $productID))
				throw new Exception("Error in Event::setProductID.  The Product ID does not exist:");
				
			// We only add Delays on Production Product IDs.  So convert to the Production Product ID (if it isn't already).
			$productionProductID = Product::getProductionProductIDStatic($this->_dbCmd, $productID);
			
			$domainIDofProduct = Product::getDomainIDfromProductID($this->_dbCmd, $productionProductID);
			
			if(!in_array($domainIDofProduct, $this->passiveAuthObj->getUserDomainsIDs()))
				throw new Exception("Error with Authentication in Event::setProductID.");
				
			$this->productID = $productionProductID;
		}
		else{
			$this->productID = 0;
		}
	}
	
	
	function setDescription($desc) {
		
		if(strlen($desc) > 255)
			throw new Exception("Error with Description length.");
			
		$this->description = $desc;
	}
	
	function setTitle($title) {
		
		$title = trim($title);
		
		if(empty($title))
			throw new Exception("Title Can not be set to empty.");
		
		if(strlen($title) > 255)
			throw new Exception("Error with Title length.");
			
		$this->title = $title;
	}
	
	function setDelaysTransit($flag) {
		
		if(!is_bool($flag))
			throw new Exception("Error with Boolean flag in setDelaysTransit");
			
		$this->delaysTransit = $flag;
	}
	
	function setDelaysProduction($flag) {
		if(!is_bool($flag))
			throw new Exception("Error with Boolean flag in setDelaysProduction");
			
		$this->delaysProduction = $flag;
	}
	
	// Returns a Unix Timestamp.  Don't pay attention to the Hours, Minutes, or Seconds.
	function setEventDate($unixTimeStamp_SecMinHour_DontMatter) {
		
		if(!preg_match("/^\d+$/", $unixTimeStamp_SecMinHour_DontMatter))
			throw new Exception("Error in method setEventDate. Must be a Unix TimeStamp");
			
		// Reformat the TimeStamp so that it always falls on 12 noon.
		$ut = $unixTimeStamp_SecMinHour_DontMatter;
		$this->eventDate = mktime(12, 0, 0, date("n",$ut), date("j",$ut), date("Y",$ut));
	}

	// Returns NULL if there is no Time Range.  End minute will also be Null.
	// If either the startMinute or the endMinute has a value set, so will the other.
	function setStartMinute($mintuesInDay_Total_1440) {
		
		if(empty($mintuesInDay_Total_1440)){
			$mintuesInDay_Total_1440 == NULL;
		}
		else{
			if(!preg_match("/^\d+$/", $mintuesInDay_Total_1440) || $mintuesInDay_Total_1440 > 1440)
				throw new Exception("Error in method setStartMinute");
		}

		$this->startMinute = $mintuesInDay_Total_1440;
	}
	function setEndMinute($mintuesInDay_Total_1440) {
	
		if(empty($mintuesInDay_Total_1440)){
			$mintuesInDay_Total_1440 = NULL;
		}
		else{
			if(!preg_match("/^\d+$/", $mintuesInDay_Total_1440) || $mintuesInDay_Total_1440 > 1440)
				throw new Exception("Error in method setEndMinute");
		}

		$this->endMinute = $mintuesInDay_Total_1440;
	}


	
}

?>

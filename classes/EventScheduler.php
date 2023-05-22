<?

// If you are trying to get any events which delay production/transit.
// First it looks at its own Domain.  If it finds one (for the given product)... it will return that.
// "For the Product" means if someone has something in their Shopping Cart and wanted to know if that product has any delays.
// The Product ID is always converted into the "Production Piggyback ID" when it looks for a delay.

// Instead of Returning Event IDs.  It is better to work with "Signatures".  That way you won't see a bunch of duplicates if an event if copied to multiple domains.
// "Event Title Signatures" are a litte different then "Event Signatures".  They allow you to group many different events together in on signature as long as the titles are the same.
class EventScheduler {

	private $_dbCmd; 

	private $delayTypesArr = array();
	
	private $eventDeatiledSignatureDateCache = array();
	private $eventTitleSignatureDateCache = array();
	
	
	const DELAYS_PRODUCTION = "1";
	const DELAYS_TRANSIT = "2";
	const DELAYS_PRODUCTION_OR_TRANSIT = "3";
	const DELAYS_PRODUCTION_AND_TRANSIT = "4";
	const DELAYS_DONT_CARE = "5";
	
	
	const EVENT_SIGNATURE_DETAILED = "1";
	const EVENT_SIGNATURE_TITLE = "2";
	


	// Set Product ID to '0' if it is not specific to a product
	function __construct(){
		
		$this->_dbCmd = new DbCmd();
		
		$this->delayTypesArr = array(1,2,3,4,5);

	}
	
	
	// Will return a hash like the following array("Year"=>"2008", "Month"=>"02", "Day"=>"12")
	// Representing the next Friday that is not a day off for a Shipping Carrier
	// This is useful to predict time-in-transit for carriers.   If we choose a pickup date of Friday we will get the most shipping options (like Saturday Delivery).
	static function getNextFridayNotShippingCarrierHoliday(){
		
		$eventSchedulObj = new EventScheduler();
		
		$twentyFourHours = 60*60*24;
		
		// Start out looking at tommorow.
		$pickupDate = time() + $twentyFourHours;
		
		while(true){
			
			if(strtoupper(date("D", $pickupDate)) != "FRI"){
				$pickupDate += $twentyFourHours;
				continue;
			}
				
			$year = date("Y", $pickupDate);
			$month = date("m", $pickupDate);
			$day = date("d", $pickupDate);
			
			$checkTimeStamp = mktime(12, 0, 0, $month, $day, $year);
			
			// Find out if the this date has a delay for shipping. If so keep looking for the next Friday.
			if($eventSchedulObj->checkIfDayHasEvent($checkTimeStamp, array(), self::DELAYS_TRANSIT)){
				$pickupDate += $twentyFourHours;
				continue;
			}
			
			return array("Year"=>$year, "Month"=>$month, "Day"=>$day);
			
		}
		
		throw new Exception("Error in method getNextFridayNotShippingCarrierHoliday");
	}
	
	
	// Whenever you call a "getEvents..." method it will return an array of Event Signatures
	// Those stay in cache (with the matching event dates)... until you call another "getEvents..." type of method.
	// Pass in a unixtimestamp to see if that Date has an event in it (based upon a previous method call).
	function checkForEventSignatureCachedForDate($unixTimeStamp_SecMinHour_DontMatter, $eventSignatureType){
		
		$ut = $unixTimeStamp_SecMinHour_DontMatter;
		
		if($eventSignatureType == self::EVENT_SIGNATURE_DETAILED)
			$checkArr =& $this->eventDeatiledSignatureDateCache;
		else if($eventSignatureType == self::EVENT_SIGNATURE_TITLE)
			$checkArr =& $this->eventTitleSignatureDateCache;
		else
			throw new Exception("Error in method checkForEventSignatureCachedForDate");
		
		foreach($checkArr as $eventDateCached){
			
			$evtDt = $eventDateCached;
			
			if(date("Y", $ut) == date("Y", $evtDt) && date("n", $ut) == date("n", $evtDt) && date("j", $ut) == date("j", $evtDt))
				return true;
		}
		
		return false;
	}
	
	
	// Returns NULL if there was an error and it was not in cache.
	function getEventSignatureDateFromCached($eventSignature, $eventSignatureType){
		
		if($eventSignatureType == self::EVENT_SIGNATURE_DETAILED)
			$checkArr =& $this->eventDeatiledSignatureDateCache;
		else if($eventSignatureType == self::EVENT_SIGNATURE_TITLE)
			$checkArr =& $this->eventTitleSignatureDateCache;
		else
			throw new Exception("Error in method getEventSignatureDateFromCached");
		
		if(isset($checkArr[$eventSignature]))
			return $checkArr[$eventSignature];

		throw new Exception("Error in method getEventSignatureDateFromCached.");

	}

	// Returns True if the Event Signature's "Event Date" is the same as the unix timestamp passed into this method.
	// You must have already called a "getEvents..." type of method before hand on this object for it to work.
	// It caches the results from the DB so you can call this many times in a loop if needed.
	function checkIfEventSignatureHasMatchingDateInCache($eventSignature, $unixTimeStamp_SecMinHour_DontMatter, $eventSignatureType){
		
		$ut = $unixTimeStamp_SecMinHour_DontMatter;
		
		if($eventSignatureType == self::EVENT_SIGNATURE_DETAILED)
			$checkArr =& $this->eventDeatiledSignatureDateCache;
		else if($eventSignatureType == self::EVENT_SIGNATURE_TITLE)
			$checkArr =& $this->eventTitleSignatureDateCache;
		else
			throw new Exception("Error in method checkIfEventSignatureHasMatchingDateInCache");
		
		// Find out if we already have the event date stored in cache.
		if(isset($checkArr[$eventSignature])){
			
			$evtDt = $checkArr[$eventSignature];
			
			if(date("Y", $ut) == date("Y", $evtDt) && date("n", $ut) == date("n", $evtDt) && date("j", $ut) == date("j", $evtDt))
				return true;
		}
		
		return false;
	}
	
	
	// Returns TRUE if there is at least 1 Event.
	// Global Events are always matched. Pass in an array of ProductIDs and it will look for a product delay (attached to its Production Product PiggybackID).
	// Only looks for Events in your "Selected Domains" unless the Product has a "Production Piggyback" linked to an event.
	function checkIfDayHasEvent($unixTimeStamp_SecMinHour_DontMatter, $productIDArr, $delays){
		
		$eventObjects = $this->getEventsInDay($unixTimeStamp_SecMinHour_DontMatter, $productIDArr, $delays, self::EVENT_SIGNATURE_DETAILED);
				
		if(sizeof($eventObjects) == 0)
			return false;
		else
			return true;
	
	}
	
	
	
	// Looks ahead X number or days starting from now.
	function getEventsByLookAheadDays($lookAheadDays, $productIDArr, $delays, $eventSignatureType){
	
		$NowTime = time();
		$EventYear = date("Y", $NowTime);
		$EventMonth = date("n", $NowTime);
		$EventDay = date("j", $NowTime);

		// Make sure that the start time stamp is very early in the morning.. and then end is late at night.
		$startEvntTime = mktime(0,1,1, $EventMonth, $EventDay, $EventYear);
		$endEvntTime = mktime(23,59,59, $EventMonth, ($EventDay + $lookAheadDays), $EventYear);
	
		return $this->getEventsByTimeRange($startEvntTime, $endEvntTime, $productIDArr, $delays, $eventSignatureType);
	}
	
	



	
	function getEventsInMonth($year, $month, $productIDArr, $delays, $eventSignatureType){
	
		$this->_EnsureNumberFormat("year", $year);
		$this->_EnsureNumberFormat("month", $month);

		$startEvntTime = mktime(0, 0, 0, $month, 1, $year);
		$endEvntTime = mktime(0, 0, 0, ($month + 1), 1, $year);
		
		return $this->getEventsByTimeRange($startEvntTime, $endEvntTime, $productIDArr, $delays, $eventSignatureType);
	}



	function getEventsInDay($unixTimeStamp_SecMinHour_DontMatter, $productIDArr, $delays, $eventSignatureType){
		
		$ut = $unixTimeStamp_SecMinHour_DontMatter;

		$startEvntTime = mktime(1, 0, 0, date("n",$ut), date("j",$ut), date("Y",$ut));
		$endEvntTime = mktime(23, 0, 0, date("n",$ut), date("j",$ut), date("Y",$ut));

		return $this->getEventsByTimeRange($startEvntTime, $endEvntTime, $productIDArr, $delays, $eventSignatureType);
	}
	
	

	// Returns a unique list of Event Signatures. They will be sorted with the oldest event dates first.
	// Pass in an array of ProductIDs and it will look for a product delay (attached to its Production Product PiggybackID).
	// Only looks for Events in your "Selected Domains"... 
	// ... unless the Production Piggy Back is linked to an Event ... and the product Domain (determined by the ProductIDs Array) uses that Production Piggy Back ID.
	private function getEventsByTimeRange($startUnixTimeStamp, $endUnixTimeStamp, $productIDArr, $delays, $eventSignatureType){
		
		if(!preg_match("/^\d{4,35}$/", $startUnixTimeStamp) || !preg_match("/^\d{4,35}$/", $endUnixTimeStamp))
			throw new Exception("Error with Time stamps");

		// Every time we get a new event collection we clear out the old cache.
		$this->eventDeatiledSignatureDateCache = array();
		$this->eventTitleSignatureDateCache = array();
			
		$startSQLtime = DbCmd::FormatDBDateTime($startUnixTimeStamp);
		$endSQLtime = DbCmd::FormatDBDateTime($endUnixTimeStamp);
			
		if(!in_array($delays, $this->delayTypesArr))
			throw new Exception("Error in method getEvents. The Delay Criteria is wrong.");
			
		if(!in_array($eventSignatureType, array(self::EVENT_SIGNATURE_DETAILED,self::EVENT_SIGNATURE_TITLE)))
			throw new Exception("Error in method getEvents. The Signature Type is wrong");
		
		$delaysSQL = $this->_BuildDelaySQL($delays);
		$domainObj = Domain::singleton();
		
		// Convert the Product ID list into a unique list of Production Product IDs
		$productIDArr = array_unique($productIDArr);
		
		$productionProductIDarr = array();
		
		foreach($productIDArr as $thisProductID){
			$productionProductIDarr[] = Product::getProductionProductIDStatic($this->_dbCmd, $thisProductID);
		}
		$productionProductIDarr = array_unique($productionProductIDarr);

		
		if($eventSignatureType == self::EVENT_SIGNATURE_DETAILED)
			$signatureType = "EventSignature";
		else if($eventSignatureType == self::EVENT_SIGNATURE_TITLE)
			$signatureType = "TitleSignature";
		else
			throw new Exception("Error in method getEvents. The Signature Type is wrong");
			
		$selectedDomainIDsArr = $domainObj->getSelectedDomainIDs();
		
		$query = "SELECT DISTINCT $signatureType, UNIX_TIMESTAMP(EventDate) AS EventDate FROM eventschedule WHERE
				(EventDate BETWEEN '$startSQLtime' AND '$endSQLtime') 
				$delaysSQL  
				AND (" . DbHelper::getOrClauseFromArray("DomainID", $selectedDomainIDsArr);
				
		if(!empty($productionProductIDarr))
			$query .= " OR " . DbHelper::getOrClauseFromArray("ProductID", $productionProductIDarr); 
				
		$query .= ") ORDER BY EventDate ASC"; 

		
		$this->_dbCmd->Query($query);
		
		$retArr = array();
		
		while($row = $this->_dbCmd->GetRow()){
			
			$retArr[] = $row[$signatureType];
			
			if($eventSignatureType == self::EVENT_SIGNATURE_DETAILED)
				$this->eventDeatiledSignatureDateCache[$row["EventSignature"]] = $row["EventDate"];
			else if($eventSignatureType == self::EVENT_SIGNATURE_TITLE)
				$this->eventTitleSignatureDateCache[$row["TitleSignature"]] = $row["EventDate"];
			else
				throw new Exception("Error in method getEvents. The Signature Type is wrong.");
		}
		
		
		
		// Now find out if the any of the Product IDs passed into this method have Production Product IDs outside of our list.
		$domainIDsFromProducts = array();
		foreach($productionProductIDarr as $thisProductID)
			$domainIDsFromProducts[] = Product::getDomainIDfromProductID($this->_dbCmd, $thisProductID);

		
		// Make a list of domain which weren't included in our first query
		$domainsToCheckArr = array();
		foreach($domainIDsFromProducts as $thisDomainID){
			if(!in_array($thisDomainID, $selectedDomainIDsArr))
				$domainsToCheckArr[] = $thisDomainID;
		}
		$domainsToCheckArr = array_unique($domainsToCheckArr);
		

		// If there are Product IDs outside of the Selected Domain Scope that could affect production, we should look for Delays.
		// This will make sure that if we add a "Global Delay for Production"... and we are producing a Product.
		// That any other domains will get an event returned with a the delay.... (even through the event is not product specific).
		// Thy won't be able to receive other events from the domain... just the global events affecting Production or Transit.
		if(!empty($domainsToCheckArr)){

			$delaysSQLforProductionCheck = $this->_BuildDelaySQL(self::DELAYS_PRODUCTION_OR_TRANSIT);
			
			$query = "SELECT DISTINCT $signatureType, UNIX_TIMESTAMP(EventDate) AS EventDate FROM eventschedule WHERE
					(EventDate BETWEEN '$startSQLtime' AND '$endSQLtime') 
					$delaysSQLforProductionCheck  
					AND " . DbHelper::getOrClauseFromArray("DomainID", $domainsToCheckArr);	
			$query .= " AND ProductID=0 ORDER BY EventDate ASC"; 
				
			$this->_dbCmd->Query($query);
			
			while($row = $this->_dbCmd->GetRow()){
				
				if(!in_array($row[$signatureType], $retArr))
					continue;
				
				$retArr[] = $row[$signatureType];
				
				if($eventSignatureType == self::EVENT_SIGNATURE_DETAILED)
					$this->eventDeatiledSignatureDateCache[$row["EventSignature"]] = $row["EventDate"];
				else if($eventSignatureType == self::EVENT_SIGNATURE_TITLE)
					$this->eventTitleSignatureDateCache[$row["TitleSignature"]] = $row["EventDate"];
				else
					throw new Exception("Error in method getEvents. The Signature Type is wrong.");
			}
		}

		return $retArr;
	}


	
	function _BuildDelaySQL($delays){
	
		if($delays == self::DELAYS_DONT_CARE)
			$delaysSQL = "";
		else if($delays == self::DELAYS_PRODUCTION)
			$delaysSQL = "AND DelaysProduction = 'Y' ";
		else if($delays == self::DELAYS_TRANSIT)
			$delaysSQL = "AND DelaysTransit = 'Y' ";
		else if($delays == self::DELAYS_PRODUCTION_OR_TRANSIT)
			$delaysSQL = "AND ( DelaysTransit = 'Y' OR DelaysProduction = 'Y' ) ";
		else if($delays == self::DELAYS_PRODUCTION_AND_TRANSIT)
			$delaysSQL = "AND ( DelaysTransit = 'Y' AND DelaysProduction = 'Y' ) ";
		else
			throw new Exception("Invalid Criteria for _BuildDelaySQL");
			
		return $delaysSQL;
	}

	function _EnsureNumberFormat($DateType, $Val){
	
		if($DateType == "year")
			if(!preg_match("/^\d{4}$/", $Val))
				throw new Exception("Year must be 4 digits");
		else if($DateType == "month")
			if(!preg_match("/^\d+$/", $Val))
				throw new Exception("Month must be  digit");
		else if($DateType == "day")
			if(!preg_match("/^\d+$/", $Val))
				throw new Exception("Day must be  digit");
		else if($DateType == "digit")
			if(!preg_match("/^\d+$/", $Val))
				throw new Exception("Day must be  digit");
		else
			throw new Exception("Invalid DateType");
	}
	
}

?>
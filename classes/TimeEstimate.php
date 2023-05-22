<?

class TimeEstimate {

	private $_dbCmd; 
	private $_productID;
	private $_shippingChoiceID; 
	private $_eventScheduleObj;
	private $_productObj;
	private $_shippingChoicesObj;
	private $_shippingMethodObj;
	


	// Set Product ID to '0' if it is not specific to a product
	function __construct($ProdID){
		
		$this->_dbCmd = new DbCmd();
		$this->_productID = $ProdID;
		
		$this->_productObj = new Product($this->_dbCmd, $ProdID);
		
		// Build an Event object for production and shipping delays/holidays
		$this->_eventScheduleObj = new EventScheduler();
		
		$this->_shippingChoicesObj = new ShippingChoices();
		$this->_shippingMethodObj = new ShippingMethods();
		


	}
	
	// Pass in a Unix Timestamp.  It will figure out how to format the date and Arrival Hour and Arrival Mintues
	// Because unix Timestamps are in seconds, this function will snap to the nearest 10 minutes.
	// Does not include the Arrival hour/mintues on the return string if they do not fall within range of a normal business day.
	// Pass in a string representing a PHP date format string. It should not include the Hours/Minutes (etc).  that will automatically be appended if the hours/minute arrival exist.
	static function formatTimeStamp($unixTimeStamp, $dateFormat = "D, M jS", $showHoursAndMinutesIfPossible = TRUE){
		
		$timeStampMinutes = date("i", $unixTimeStamp);

		// If the timestamp was set a little bit before the 60 mintue mark (just push it over the edge a little) to the next whole hour.
		// Otherwise it could show the arrival Day as the Day before (even though it was just 1 minute away).
		if($timeStampMinutes > 53){
			$addingMinutes = (60 - $timeStampMinutes + 1);
			$unixTimeStamp +=  $addingMinutes * 60;
		}
		
		$timeStampHour12 = date("g", $unixTimeStamp);
		$timeStampMinutes = date("i", $unixTimeStamp);
		$timeStampAmPm = date("A", $unixTimeStamp);
		
		$arrivalHourDesc = "";
		
		// Must be in range of a normal business day.
		if(self::getArrivalHourFromTimestamp($unixTimeStamp) && $showHoursAndMinutesIfPossible){
			
			// Snap to nearest 10 mintues.
			$timeStampMinutes = round(intval($timeStampMinutes) / 10) * 10;
			
			if($timeStampMinutes == 0)
				$timeStampMinutes = "00";
				
			// If for reason
			if($timeStampMinutes == 60)
				$timeStampMinutes = "50";

			
			$arrivalHourDesc = " " . $timeStampHour12 . ":" . $timeStampMinutes . " " . $timeStampAmPm;
		}
		

		return date($dateFormat, $unixTimeStamp) . $arrivalHourDesc;
	}
	
	// If the unix time stamp has an arrival hour, it will be returned in 24 hour format, otherwise this method returns NULL.
	// A timestamp has an arrival hour if it falls between 3 AM and 10 PM.
	static function getArrivalHourFromTimestamp($unixTimeStamp){
		
		$timeStampHour24 = date("H", $unixTimeStamp);
		
		if($timeStampHour24 > 3 && $timeStampHour24 < 22)
			return $timeStampHour24;
		else
			return NULL;
		
	}
	
	

	

	// Does the same thing as isSaturdayDeliveryPossible, but it takes an array of ProductIDs as the parameter.
	static function isSaturdayDeliveryPossibleForAnyInProductList(array $productIDarr, $timeStampOrderIsPlacedFrom){
		
		$dbCmd = new DbCmd();
		
		$saturdayDeliveryFlag = false;
	
		foreach($productIDarr as $thisProductID){
			
			if(TimeEstimate::isSaturdayDeliveryPossible($dbCmd, $timeStampOrderIsPlacedFrom, $thisProductID)){
				$saturdayDeliveryFlag = true;
				break;
			}
		}
		
		return $saturdayDeliveryFlag;
	}

	// A Static Method
	// Pass in a unix time stamp and the product ID.  
	// The function will return true or false depending on wheter a Saturday delivery option is available if the customer were to place their order at the timestamp.
	static function isSaturdayDeliveryPossible(DbCmd $dbCmd, $FromTimeStamp, $ProductID){

		// First check and see how many production days this product takes
		$timeEstObj = new TimeEstimate($ProductID);
		
		// Loop through all of our shipping choices, and look for the first saturday delivery option.
		// If there is no saturday deliveries available, then return false.
		$shippingChoiceIDwithSaturday = NULL;

		$shippingChoicesObj = new ShippingChoices();
	
		foreach(array_keys($shippingChoicesObj->getActiveShippingChoices()) as $thisShippingChoiceID){
		
			if($shippingChoicesObj->checkIfShippingChoiceHasSaturdayDelivery($thisShippingChoiceID)){
				$shippingChoiceIDwithSaturday = $thisShippingChoiceID;
				break;
			}
		}

		
		if(empty($shippingChoiceIDwithSaturday))
			return false;
		
		$timeEstObj->setShippingChoiceID($shippingChoiceIDwithSaturday);	

		$productionDays = $timeEstObj->productionDaysByShippingMethod();

		// Based on the Cutoff Hour, Figure out the soonest day that this product will "start" to be produced (even if it falls on a holiday or weekend)
		$cuttOffHour = $timeEstObj->getCuttofHourByShippingMethod();


		$currentHour = date("G", $FromTimeStamp);
		if($currentHour >= $cuttOffHour)
			$addDayAfterCuttoff = 1;
		else
			$addDayAfterCuttoff = 0;

		$dayTimeStamp = mktime (1, 1, 1, date("n", $FromTimeStamp) , (date("j", $FromTimeStamp) + $addDayAfterCuttoff), date("Y", $FromTimeStamp) );


		// Keep looping over each day the product will take in production
		// If we find a production delay/holiday/weekend on that day, then we will keep going until we find an open day
		$eventSchedulerObj = new EventScheduler();

		while($productionDays > 0){

			$dayDescription = strtoupper(date("D", $dayTimeStamp));

			// Advance the Date by 1 Day for the next loop
			$dayTimeStamp = mktime (1, 1, 1, date("n", $dayTimeStamp) , (date("j", $dayTimeStamp) + 1), date("Y", $dayTimeStamp) );

			$productArrForScheduler = array($ProductID);
			
			// If the Day that we are curently on falls on a Sat, Sun, or a holiday... then advance to the next day without subtracting a normal production day
			if( $dayDescription == "SAT" || $dayDescription == "SUN")
				continue;
			if( $eventSchedulerObj->checkIfDayHasEvent($dayTimeStamp, $productArrForScheduler, EventScheduler::DELAYS_PRODUCTION) )
				continue;

			$productionDays -= 1;
		}


		// If final day of production lands on a Friday, then Saturday shipping is available, otherwise it is not.
		$dayDescription = strtoupper(date("D", $dayTimeStamp));
		if( $dayDescription == "FRI")
			return true;
		else
			return false;
	}



	

	// Will return an integer representing the number of days that production will delay shipment based off of the shipping method
	// For example... "ground" shipping may cause the order to ship within 1 business day if placed by the cutoff time.... where 1 day orders will ship out the same day.
	// Another example...  if you want a "1 day" shipping to go out the same day the order is placed then return 0 in this function
	function productionDaysByShippingMethod(){

		if(empty($this->_shippingChoiceID))
			throw new Exception("Shipping Method has not been set yet.");
			
		
		// Get the default number of Production Days for the Product. Unless the default has been overriden by the Shipping Method.
		return $this->_productObj->getProductionDays($this->_shippingChoiceID);
	
	}
	

	// Will return an integer representing the number of days we will wait before an order is printed.... based off of the shipping method
	// Works almost identical to the method productionDaysByShippingMethod... except sometimes products need to be printed well before the item is ready for shipment.
	// Generally you want to set these values to 0 so that they are printed right away (especailly with digital printing).  
	// ... However, you may want to wait to have them printed a bit if we are trying to build up a reserve (can be useful to get volume for gang-run offset printing)
	function daysUntilPrintByShippingMethod(){

		if(empty($this->_shippingChoiceID))
			throw new Exception("Shipping Method has not been set yet.");

		// Right now the need isn't quite there.  I had hardcoded some return values (based upon product IDs) in the past.  So this method does work.
		// In the future if we need to enhance this... a good idea would be to add some new fields to the Product Database and maybe some checkmarks to ad_product_setup.php.
		// For instance... you may put on the setup screen that you want to print this product on Tuesday and Friday.
		// In this example... (if today was Wednesday)... this method would return 2... because we need to wait another 2 business days before starting to print.
		// Remember to factor the return value in this method with the value from $this->_GetSecondsThatAreIgnored()
		return 0;
	}



	//Returns the hour that is our cut-off.   (on a 24 hour clock)
	function getCuttofHourByShippingMethod(){

		if(empty($this->_shippingChoiceID))
			throw new Exception("Shipping Method has not been set yet.");


		// Get the default Cut-Off Hour for the Product. Unless the default has been overriden by the Shipping Method.
		return $this->_productObj->getCutOffHour($this->_shippingChoiceID);
	}




	// Returns a string describing which days are used for production, based on the product we are making
	// In the future, we may change this based upon country/domain ... or maybe some products are produced 7 days a week.
	function getProductionOffDays(){

		return "weekends";
	}


	// Returns a string describing which days are not used by the freight company
	function getTranistOffDays(){

		if(empty($this->_shippingChoiceID))
			throw new Exception("Shipping Method has not been set yet in method getTranistOffDays.");
		
		if($this->_shippingChoicesObj->checkIfShippingChoiceHasSaturdayDelivery($this->_shippingChoiceID))
			return "sunday";
		else
			return "weekends";
	

	}





	// Pass in a time stamp.  It will figure if the order was placed at that moment... when would it ship
	// Returns a hash, that contains the year, month and day.
	// Set $WithLeadingZeros to true if you want the month and day to always have 2 digits.
	// The "Print Date" and "Ship Date" are pretty closely related.   Some products are the same for both.
	// ... But some products need to have a print date ahead of the day it is meant to be shipped... like Envelopes need time to be sent to a factory for converting.
	// The parameter $shipOrPrintFlag must be a string "print" or "ship"
	function estimateShipOrPrintDate($shipOrPrintFlag, $FromTime, $WithLeadingZeros = false){
	
		if(empty($this->_shippingChoiceID))
			throw new Exception("Shipping Method has not been set yet.");

		// We may need to advance the time stamp if we are after the cut off time for today
		$FromTime = $this->getCutoffTimeStamp($FromTime);
	
		if($shipOrPrintFlag == "ship")
			$DaysRequired = $this->productionDaysByShippingMethod();
		else if($shipOrPrintFlag == "print")
			$DaysRequired = $this->daysUntilPrintByShippingMethod();
		else
			throw new Exception("Illegal $shipOrPrintFlag in method estimateShipOrPrintDate");
		
		while(true){
		
			// This will skip over any weekends, etc
			// We need to do this in addtion to using the getCutoffTimeStamp() function.
			// Becuase multiple Production days may push us into another weekend or Holiday
			
			$FromTime += $this->_GetSecondsThatAreIgnored($FromTime, $this->getProductionOffDays());
		
			// If this day has a production event... then skip ahead 24 hours
			$productArrForScheduler = array($this->_productID);
			if($this->_eventScheduleObj->checkIfDayHasEvent($FromTime, $productArrForScheduler, EventScheduler::DELAYS_PRODUCTION)){
				$FromTime += TWENTY_FOUR_HOURS;
				continue;
			}
		
			// If this product requires multiple days in production
			if($DaysRequired > 0){
				$FromTime += TWENTY_FOUR_HOURS;
				$DaysRequired--;
			}
			else{
				break;
			}
		}
	
		if($WithLeadingZeros)
			return array("year" => date("Y", $FromTime), "month" => date("m", $FromTime), "day"=> date("d", $FromTime), "UnixTimeStamp"=> $FromTime );
		else
			return array("year" => date("Y", $FromTime), "month" => date("n", $FromTime), "day"=> date("j", $FromTime), "UnixTimeStamp"=> $FromTime );
	}
	
	
	// Pass in the Year, Month, and Day that the shipment is leaving
	// Returns a hash that contains the year, month, and day that it is scheduled to arrive
	function estimateArrivalDate($ShipYear, $ShipMonth, $ShipDay){

		if(empty($this->_shippingChoiceID))
			throw new Exception("Shipping Method has not been set yet.");

		$transit_days = $this->_shippingChoicesObj->getTransitDays($this->_shippingChoiceID);
		
		$this->_EnsureNumberFormat("year", $ShipYear);
		$this->_EnsureNumberFormat("month", $ShipMonth);
		$this->_EnsureNumberFormat("day", $ShipDay);


		// Build a Unix Timestamp from our starting date.  Start it at 1AM just to keep it away from the boundary
		$shippingTimeStamp = mktime(1, 0, 0, $ShipMonth, $ShipDay, $ShipYear);
	
		while(true){
	
			// This will skip over any weekends, etc
			$shippingTimeStamp += $this->_GetSecondsThatAreIgnored($shippingTimeStamp, $this->getTranistOffDays());
	
			// If this day has a transit event... then skip ahead 24 hours
			$productArrForScheduler = array($this->_productID);
			if($this->_eventScheduleObj->checkIfDayHasEvent($shippingTimeStamp, $productArrForScheduler, EventScheduler::DELAYS_TRANSIT)){
				$shippingTimeStamp += TWENTY_FOUR_HOURS;
				continue;
			}
		
			// Keep adding 24 hours until we have accounted for all days in transit
			if($transit_days > 0){
				$shippingTimeStamp += TWENTY_FOUR_HOURS;
				$transit_days--;
			}
			else{
				break;
			}
		}

		return array("year" => date("Y", $shippingTimeStamp), "month" => date("n", $shippingTimeStamp), "day"=> date("j", $shippingTimeStamp) );
	}
	
	
	// Pass in a timestamp of the "current time"
	// Will return a timestamp of the cuttoff time
	function getCutoffTimeStamp($fromTimeStamp){
	
		if(empty($this->_shippingChoiceID))
			throw new Exception("Shipping Choice has not been set yet in method estimateCutoffTime");
	
		$this->_productObj->getCutOffHour($this->_shippingChoiceID);
			
		$cutoffHour = $this->_productObj->getCutOffHour($this->_shippingChoiceID);

		// Find out what hour the "From Time" is
		$hourFromTime = date("G", $fromTimeStamp);
		
		// If we have missed the cutt off time for today, then bump the timestamp up by 24 hours
		if($hourFromTime >= $cutoffHour)
			$fromTimeStamp += TWENTY_FOUR_HOURS;
		

		$Year = date("Y", $fromTimeStamp);
		$Month = date("n", $fromTimeStamp);
		$Day = date("j", $fromTimeStamp);
	
		// We may be stitting on a weekend or holiday... this will advance the date to the next production day if needed
		$cutDate = $this->getNextProductionDate($Year, $Month, $Day, $this->getProductionOffDays());
		

		return mktime($cutoffHour, 0, 0, $cutDate["month"], $cutDate["day"], $cutDate["year"]);
	}

		
	// Will return a unix timestamp.  Don't worry about parsing the Hours, minutes, seconds.  It is the date that is important.
	// We are just setting the Hours and Mintues to 12:30 am in the morning
	function getShipDateTimestamp($fromTimeStamp){

		if(empty($this->_shippingChoiceID))
			throw new Exception("Shipping Choice has not been set yet in method getShipDateTimestamp");
		
		$shipDateArr = $this->estimateShipOrPrintDate("ship", $fromTimeStamp);
		
		return mktime(0, 30, 0, $shipDateArr["month"], $shipDateArr["day"], $shipDateArr["year"]);
		
	}
	
	function getPrintDateTimestamp($fromTimeStamp){

		if(empty($this->_shippingChoiceID))
			throw new Exception("Shipping Choice has not been set yet in method getPrintDateTimestamp");
		
		$printDateArr = $this->estimateShipOrPrintDate("print", $fromTimeStamp);
		
		return mktime(0, 30, 0, $printDateArr["month"], $printDateArr["day"], $printDateArr["year"]);
			
	}
	
	
	// Pass in a timestamp of when the order is exactly placed.
	// This will return a timestamp of when that package plans on arriving to the destination (based on the shipping method set).
	// If you provide the Shipping To/From addresses then the time stamp may be more accurate (in terms of providing an Arrival Hour/minute).
	// If you don't provide Shipping Addressses then the Arrival hour will always be 12:30 am.
	function getArrivalTimeStamp($fromTimeStamp, MailingAddress $shipFromAddress = NULL, MailingAddress $shipToAddress = NULL){
		
		if(empty($this->_shippingChoiceID))
			throw new Exception("Shipping Choice has not been set yet in method getArrivalTimeStamp");
		
		$shipDateArr = $this->estimateShipOrPrintDate("ship", $fromTimeStamp);
		$arrivalDateHash = $this->estimateArrivalDate($shipDateArr["year"], $shipDateArr["month"], $shipDateArr["day"]);
		
		// If this is a Mailing Product... then the Arrival TimeStamp is actually the Ship Date (when we plan on taking it to the Post Office)
		if($this->_productObj->hasMailingService()){
			return mktime(0, 30, 0, $shipDateArr["month"], $shipDateArr["day"], $shipDateArr["year"]);
		}
		
		
		if(empty($shipFromAddress) && empty($shipToAddress)){
			
			return mktime(0, 30, 0, $arrivalDateHash["month"], $arrivalDateHash["day"], $arrivalDateHash["year"]);
		}
		else{
			
			if(empty($shipFromAddress) || empty($shipToAddress))
				throw new Exception("Error in method getArrivalTimeStamp. If you pass in a Shipipng Address you must have both Ship From/To.");
				
			$shippingMethodCode = $this->_shippingChoicesObj->getDefaultShippingMethodCode($this->_shippingChoiceID);
			
			if(empty($shippingMethodCode))
				throw new Exception("Error in method getArrivalTimeStamp, the Shipping Choice is empty.");
			
			$transitTimeObj = $this->_shippingMethodObj->getTransitTimes($shipFromAddress, $shipToAddress);
			
			$arrivalHour = $transitTimeObj->getArrivalHour($shippingMethodCode);
			$arrivalMinute = $transitTimeObj->getArrivalMinute($shippingMethodCode, 0);
			
			if(empty($arrivalHour))
				return mktime(0, 30, 0, $arrivalDateHash["month"], $arrivalDateHash["day"], $arrivalDateHash["year"]);
			else	
				return mktime($arrivalHour, $arrivalMinute, 0, $arrivalDateHash["month"], $arrivalDateHash["day"], $arrivalDateHash["year"]);

		}

		
	}
	
			
			
	



	// Pass in the month, day, and year
	// It will return the next day available for Production... It may return the same values that are passed in
	// It will skip over weekends... or sundays (whatever is specified in $ignoreDays)
	// It will also skip over any special events or holidays that affect shipping
	// Returns a hash with the Year, Month, and Day
	function getNextProductionDate($StartYear, $StartMonth, $StartDay, $ignoreDays){

		$this->_EnsureNumberFormat("year", $StartYear);
		$this->_EnsureNumberFormat("month", $StartMonth);
		$this->_EnsureNumberFormat("day", $StartDay);

		// Build a Unix Timestamp from our starting date.  Start it at 1AM just to keep it away from the boundary
		$productionTimeStamp = mktime(1, 0, 0, $StartMonth, $StartDay, $StartYear);
	
		while(true){
	
			// This will skip over any weekends, etc
			$productionTimeStamp += $this->_GetSecondsThatAreIgnored($productionTimeStamp, $ignoreDays);
	
			// If this day has a production event... then skip ahead 24 hours
			$productArrForScheduler = array($this->_productID);
			if($this->_eventScheduleObj->checkIfDayHasEvent($productionTimeStamp, $productArrForScheduler, EventScheduler::DELAYS_PRODUCTION)){
				$productionTimeStamp += TWENTY_FOUR_HOURS;
				continue;
			}
		
			break;
		}
	
		return array("year" => date("Y", $productionTimeStamp), "month" => date("n", $productionTimeStamp), "day"=> date("j", $productionTimeStamp) );
	}



	function setShippingChoiceID($shippingChoiceID){
	
		if(!$this->_shippingChoicesObj->checkIfShippingChoiceIDExists($shippingChoiceID))
			throw new Exception("Error in TimeEstimates->setShippingChoiceID");

		$this->_shippingChoiceID = $shippingChoiceID;
	}
	




	// Will return an integer describing how many seconds are skipped over
	// Pass in a start time stamp.. If it falls on a weekend or something.. we may need to add 48 hours
	private function _GetSecondsThatAreIgnored($TheTimeStamp, $ignoreDays){

		$RetSeconds = 0;

		while(true){
		
			$ThreeLetterDay = strtoupper(date("D", ($RetSeconds + $TheTimeStamp)));

			if($ignoreDays == "weekends"){
				if( $ThreeLetterDay == "SAT" || $ThreeLetterDay == "SUN" ){
					$RetSeconds += TWENTY_FOUR_HOURS;
					continue;
				}
			}
			else if($ignoreDays == "sunday"){
				if( $ThreeLetterDay == "SUN" ){
					$RetSeconds += TWENTY_FOUR_HOURS;
					continue;
				}
			}
			else if($ignoreDays <> "none")
				throw new Exception("Error With string ignoreDays");
			
			break;
		}
		
		return $RetSeconds;
	}




	private function _EnsureNumberFormat($DateType, $Val){
	
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




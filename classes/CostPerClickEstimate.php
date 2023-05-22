<?php

/*
 * The Conversion Rate will be based exclusively of of the Short Term Date Range
 * If the minimum order requirement is not met for in the short term... the system will keep wideing the tracking code.
 * If the tracking code gets widened all of the way, and it still can't reach the minimum order requirement... 
 * ... Then it will put the tracking code back to the original and try again from the Mid Date Range.  It will keep widening the code until the minimum order count is reached.
 * Still no orders on the Mid Term??? Tries starting again on the long term date ranges.
 * 
 * The only exception to this is when the number of clicks that a tracking code has received goes over a certain amount.
 * For example, if an ad is clicked on 1,000 times and doesn't get any orders... then the bid price will be reduced by X%.  (does not try widening tracking code to look for max Clicks).
 * 
 */

/*
Here is an example of how a tracking code got widened.
g-geo_bCty_Z5-3-em-bc_appointment (original tracking code)
g-geo_%Cty_Z5-3-em-bc_appointment
g-geo_%_Z5-3-em-bc_appointment
g-geo_%_Z5-%-em-bc_appointment
g-geo_%_Z%-%-em-bc_appointment
g-%-em-bc_appointment
g-%-em-bc_%
g-%-%-bc_%

*/

class CostPerClickEstimate {

	
	private $dbCmd;
	private $trackingCode;
	
	private $minimumOrders_short;
	private $minimumOrders_mid;
	private $minimumOrders_long;
	
	private $minimumClicksToMeasureConversions;
	
	private $dateStart_short;
	private $dateEnd_short;
	private $dateStart_mid;
	private $dateEnd_mid;
	private $dateStart_long;
	private $dateEnd_long;
	
	private $domainID;
	
	private $currencySymbol;
	
	private $nomadPercentage;
	
	private $percentageOfErrorForBidAdjustment;
	
	private $breakEvenAfterXdays;
	private $customerWorthEndDays_ShortTerm;
	private $customerWorthEndDays_MidTerm;
	
	private $profitPercentage;
	
	private $trackingCodeLog;

	private $log_trackingCode;
	private $log_trackCodeForConvRate;
	private $log_breakEvenDays;
	private $log_conversionRate;
	private $log_customerWorthAverage;
	private $log_customerWorthAverageAdjusted;
	private $log_customerWorthShortTerm;
	private $log_customerWorthMediumTerm;
	private $log_customerWorthLongTerm;
	private $log_cpcPerfectEstimate;
	private $log_trackCodeShortTermWorth;
	private $log_trackCodeMediumTermWorth;
	private $log_trackCodeLongTermWorth;
	
	private $maxCPCprice;
   
   
	function __construct($domainID){
		
		$this->dbCmd = new DbCmd();
		
		
		// If the caclulate bid Price is +/- this percentage against the current CPC bid... then no change will take place. 
		$this->percentageOfErrorForBidAdjustment = 0.03;
		
		// The Minimum Orders required by a tracking code before we can "trust" a customer worth average.
		// The system will try not to widen the tracking code any more than it has to in order to reach these minimum order requirements.
		$this->minimumOrders_short = 50;
		$this->minimumOrders_mid = 100;
		$this->minimumOrders_long = 200;
		
		// The amount of clicks required for us to be able to measure conversion rates.
		$this->minimumClicksToMeasureConversions = 200;
	
		$this->customerWorthEndDays_ShortTerm = -1;
		$this->customerWorthEndDays_MidTerm = -1;
		$this->breakEvenAfterXdays = -1;
		$this->profitPercentage = 0;
		
		// Never let a CPC exceed the following currency amount for the given domain.
		$this->maxCPCprice = 20.00;
		
		$domainID = intval($domainID);
		
		if(!Domain::checkIfDomainIDexists($domainID))
			throw new Exception("Domain ID does not exist");
			
			
		// We figure that a customer is worth x% more than we can calculate because of computers without cookies... and "tell a friend" etc.
		$this->nomadPercentage = 0.2;
		
		$this->domainID = $domainID;
		
		$this->currencySymbol = '$';
			
		$this->trackingCodeLog = "Cost Per Click Tracking Log for " . Domain::getDomainKeyFromID($domainID) . "\n=============================================\n";
			
	}
	
	// If our "Break Even" happens before the Short Term End period.... then we don't have to set the member variables in these methods.
	// Otherwise, we need to know how to many days we should add up customer worth for within a period.
	// We can't go off of the StartDate to present time because that isn't fair to the customers who placed their first order near the End Period acquisition date.
	// If we subtract current time() from the End Date... then it gives too much advantage to customers who placed their first order near the start period.
	function setCustomerWorthEndDays_Short($x){
		$this->customerWorthEndDays_ShortTerm = intval($x);
	}
	function setCustomerWorthEndDays_Mid($x){
		$this->customerWorthEndDays_MidTerm = intval($x);
	}
	
	function setNomadPercentage($x){
		$this->nomadPercentage = floatval($x);
	}
	
	function getTrackingCodeLog($htmlFormat = false){
		if(!$htmlFormat)
			return $this->trackingCodeLog;
			
		$returnLog = $this->trackingCodeLog;
		$returnLog = htmlspecialchars($returnLog);
		$returnLog = preg_replace("/\n/", "\n<br>\n", $returnLog);
		$returnLog = preg_replace("/\nWidened Code by Banner Clicks: (.*)\n/", "\nWidened Code by Banner Clicks: \n<br>&nbsp;&nbsp;&nbsp;\\1 \n", $returnLog);
		$returnLog = preg_replace("/\nWidened Code by Minimum Orders: (.*)\n/", "\n<br>Widened Code by Minimum Orders: \n<font style='font-size:11px;'>\\1</font>\n", $returnLog);
		$returnLog = preg_replace("/\n(.*NOT ENOUGH CLICKS.*)\n/", "\n<font style='font-size:12px; color:cc3355;'>\\1</font>\n", $returnLog);
		$returnLog = preg_replace("/\n(.*NOT ENOUGH ORDERS.*)\n/", "\n<font style='font-size:12px; color:33cc55;'>\\1</font>\n", $returnLog);
		$returnLog = preg_replace("/SHORT/", "<br>SHORT", $returnLog);
		$returnLog = preg_replace("/Mid/", "<br>Mid", $returnLog);
		$returnLog = preg_replace("/long/", "<br>long", $returnLog);
		$returnLog = preg_replace("/Basing/", "<br>Basing", $returnLog);
		$returnLog = preg_replace("/Final Average/", "<br>Final Average", $returnLog);
		$returnLog = preg_replace("/\nPerfect CPC Estimate: (.*)\n/", "\n<br>Perfect CPC Estimate:&nbsp;&nbsp;&nbsp;\\1\n", $returnLog);
		$returnLog = preg_replace("/\nLast CPC Average: (.*)\n/", "\nLast CPC Average:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\\1\n", $returnLog);
		$returnLog = preg_replace("/\nNew CPC Recommendation: (.*)\n/", "\nNew CPC Recommendation: <font style='color:#008800;'><b>\\1</b></font>\n", $returnLog);
		$returnLog = preg_replace("/\nLast CPC Bid: (.*)\n/", "\nLast CPC Bid:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;<font style='color:#880000;'><b>\\1</b></font>\n", $returnLog);
		$returnLog = preg_replace("/\nNomad Factor: (.*)\n/", "\nNomad Factor:&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;\\1\n", $returnLog);
		
		$returnLog = "<font face=\"Courier New, Courier, monospace\">$returnLog</font>";
		
		return $returnLog;
	}

	
	
	// If we could tell Google exactly what we want our averge CPC to be to reach our "Profit Percentage" by the break even date.
	function getCostPerClickEstimatePerfect(){
		
		if($this->breakEvenAfterXdays < 0)
			throw new Exception("You forgot to set the Break-Even days.");
			
		if(empty($this->trackingCode))
			throw new Exception("You forgot to set the Tracking Code days.");
			
		if(empty($this->minimumOrders_long) || empty($this->minimumOrders_mid) || empty($this->minimumOrders_short))
			throw new Exception("You forgot to set one or more of the Minimum Order Requirements.");
			
		if(empty($this->dateEnd_long) || empty($this->dateEnd_mid) || empty($this->dateEnd_short))
			throw new Exception("You forgot to set one or more of the Date Ranges");
			
			
		// If we have cached the result... then avoid doing any work.
		if($this->checkIfTrackingCodeIsCached()){
			$this->dbCmd->Query("SELECT CpcPerfectEstimate FROM costperclicklog USE INDEX(costperclicklog_Date)
						WHERE Date > DATE_ADD(NOW(), INTERVAL -24 HOUR) AND TrackingCode='".DbCmd::EscapeSQL($this->trackingCode)."' AND DomainID=$this->domainID");
			$perfectCPCaverage = $this->dbCmd->GetValue();
			return $perfectCPCaverage;
		}
			
		$trackingCodeConversionRate = $this->getConversionRateForTrackingCode();
		
		// If there are not enough clicks to measure the conversion rate.
		if($trackingCodeConversionRate === false || $trackingCodeConversionRate < 0.005){
			$this->trackingCodeLog .= "\n***** NOT ENOUGH CLICKS TO MEASURE CONVERSION RATE. Using Default CPC Bid: $this->currencySymbol" . $this->getDefaultCPCbidByTrackingCode() . "\n\n";
			$returnCPC = $this->getDefaultCPCbidByTrackingCode();
			
			$this->log_cpcPerfectEstimate = $returnCPC;
			return $returnCPC;
		}

		$customerWorthByBreakEvenDate = $this->getCustomerWorthByTrackingCode();	
		
		$this->log_customerWorthAverage = $customerWorthByBreakEvenDate;
		
		// If we want to make additional profit by the "Break Even Date", then we have to manipulate the Customer Worth figure.
		// We actually have to invert the sign of the percentage because if we want to make an extra 10% profit, then we have reduce the perceived worth so that our system will bid less to get the customer.
		$customerWorthByBreakEvenDate = $customerWorthByBreakEvenDate + (-1 * $this->profitPercentage);
		
		$this->log_customerWorthAverageAdjusted = $customerWorthByBreakEvenDate;
		
		// If we have a 5% converion rate then it means we will get 5 customers from 100 clicks.   Therefore we need 20 clicks to get a customer.
		// If a customer is worth $200, we should spend $200 for 20 clicks, or $10 per click.
		$numberOfClicksToBreakEven = 1 / $trackingCodeConversionRate;
		
		$returnCPC = round(($customerWorthByBreakEvenDate / $numberOfClicksToBreakEven), 2);
		
		$this->trackingCodeLog .= "Perfect CPC Estimate: $this->currencySymbol" . $returnCPC . "\n";
		
		$this->log_cpcPerfectEstimate = $returnCPC;
		return $returnCPC;
	}
	
	
	// If you set a Google CPC bid to $3.00... the average CPC bid will almost always be different, like $2.50
	// Not sure exactly what factors make up the difference... but we are going to return our calculated CPC based upon the percentage of difference.
	// Let's say the we set out CPC to $10.00 and the average CPC ends up being $5.00.  Therefore roughly assume that our average CPC ends up being 50%.
	// If our system (based upon conversions and customer worth) decided that the perfect CPC bid (or average) is supposed to be $7.00 ... then we know to tell google $14.00.
	// There could be some ocilation in the begining, but it should settle down eventually.
	// If you don't specify $overridePerfectCpcBid ... then this method will fetch the Perfect CPC bid using the logic in this class.
	function getCostPerClickRecommendation($lastCpcBid, $lastCpcAverage, $overridePerfectCpcBid = null){
	
		// Avoid a division by zero if the last average was less than a penny
		if($lastCpcAverage < 0.01){
			$percentageDifBetweenAverageAndExactBid = 1;   // Just make it 1 to 1.
		}
		else {
			$lastCpcBid = floatval($lastCpcBid);
			$lastCpcAverage = floatval($lastCpcAverage);
			$percentageDifBetweenAverageAndExactBid = $lastCpcBid / $lastCpcAverage;
		}
		
		
		// If we have overrided the Perfect CPC price... just return the estimate without logging to the DB or calculating anything else.
		if($overridePerfectCpcBid != null){
			return round((floatval($overridePerfectCpcBid) * $percentageDifBetweenAverageAndExactBid), 2);
		}
		
		$perfectCPCaverage = $this->getCostPerClickEstimatePerfect();
		
		$recomendedCpcBid = round(($perfectCPCaverage * $percentageDifBetweenAverageAndExactBid), 2);
		
		// Make sure the recomended CPC can not exceed the maximum.
		if($recomendedCpcBid > $this->maxCPCprice)
			return $this->maxCPCprice;
		
		// Log the CPC Request in the Database so we can cache the results and keep some history of our changes.
		if(!$this->checkIfTrackingCodeIsCached()){
			
			$this->trackingCodeLog .= "Last CPC Average: $this->currencySymbol" . $lastCpcAverage . "\nNew CPC Recommendation: $this->currencySymbol" . $recomendedCpcBid . "\nLast CPC Bid: $this->currencySymbol" . $lastCpcBid . "\n";
			
			$this->trackingCodeLog .= "Nomad Factor: " . round($this->nomadPercentage * 100) . "%\n";
				
			$logHash["TrackingCode"] = $this->log_trackingCode;
			$logHash["TrackCodeForConvRate"] = $this->log_trackCodeForConvRate;
			$logHash["MinOrdersToMeasureConversion"] = $this->minimumClicksToMeasureConversions;
			$logHash["BreakEvenDays"] = $this->log_breakEvenDays;
			$logHash["ConversionRate"] = $this->log_conversionRate;
			$logHash["CustomerWorthAverage"] = $this->log_customerWorthAverage;
			$logHash["CustomerWorthAverageAdjusted"] = $this->log_customerWorthAverageAdjusted;
			$logHash["CustomerWorthShortTerm"] = $this->log_customerWorthShortTerm;
			$logHash["CustomerWorthMediumTerm"] = $this->log_customerWorthMediumTerm;
			$logHash["CustomerWorthLongTerm"] = $this->log_customerWorthLongTerm;
			$logHash["CpcPerfectEstimate"] = $this->log_cpcPerfectEstimate;	
			$logHash["TrackCodeShortTermWorth"] = $this->log_trackCodeShortTermWorth;
			$logHash["TrackCodeMediumTermWorth"] = $this->log_trackCodeMediumTermWorth;
			$logHash["TrackCodeLongTermWorth"] = $this->log_trackCodeLongTermWorth;
			$logHash["DomainID"] = $this->domainID;

			$logHash["Date"] = time();
			$this->dbCmd->InsertQuery("costperclicklog", $logHash);
		}

		return $recomendedCpcBid;
	}
	
	
	
	// If we have requested details on the tracking code within the past 24 hours this method will return TRUE.
	function checkIfTrackingCodeIsCached(){
		
		if(empty($this->trackingCode))
			return false;
			
		$this->dbCmd->Query("SELECT COUNT(*) FROM costperclicklog USE INDEX(costperclicklog_Date)
						WHERE Date > DATE_ADD(NOW(), INTERVAL -24 HOUR) AND TrackingCode='".DbCmd::EscapeSQL($this->trackingCode)."' AND DomainID=$this->domainID");
		if($this->dbCmd->GetValue() > 0)
			return true;
		else
			return false;
	}

	
	// In the event that we do not have enough "click history" to measure converion rates... this will return the default CPC bid for a tracking code.
	function getDefaultCPCbidByTrackingCode(){

		if(empty($this->trackingCode))
			throw new Exception("The tracking code must be set before calling this method.");
			
		// For now just made the default bid different between the content newtwork and the rest.  In the future we can extend to many other options (mainly useful if there is not enough data.
		if(preg_match("/^g.*-lh_*/", $this->trackingCode))
			return 1.50;
		else if(preg_match("/std_*/", $this->trackingCode))
			return 0.50;
		else if(preg_match("/^gcn\-/", $this->trackingCode))
			return 2.00;
		else
			return 3.00;
		
	}
	
	// In case we don't have enough Data (maybe a new product) to measure the customer worth yet.
	// This will allow us to assign a default Customer Worth value based upon the tracking code.
	// This value should line up to whatever you set the "Break Even Days" to.
	function getDefaultCustomerWorthByTrackingCode(){
		
		if(empty($this->trackingCode))
			throw new Exception("The tracking code must be set before calling this method.");
		
		if(preg_match("bc_free", $this->trackingCode))
			return 10.50;
		else
			return 40;
			
			
	}
	
	// Will return a Conversion Rate by using a tracking code that is widened just enough in order to allow us sufficient clicks for measuring converion rates.
	// Conversion rate will only go off of the Short Term date range.  Therefore make sure that he short term range is sufficiently wide (a few days to a week)?
	// The system will try to match the "banner clicks" at the lowest level (narrowest tracking code).  Then it will begin widening (but not too far).
	// If we have hundreds of tracking codes in the system... we should try to get the conversion rate for each one separately (widening on a case-by-case basis).
	// This method will not let the tracking code eliminate the product category deliniation.  For example.  It will keep "Save the Date Postcards", versus widening to "all postcards".
	// returns FALSE if there is not enough data to meausre the converion rate.  To differentiate between a 0% conversion rate... make sure to compare the results of this method with ($result === false)
	function getConversionRateForTrackingCode(){

		if(empty($this->trackingCode))
			throw new Exception("The tracking code must be set before calling this method.");
			
		if(empty($this->dateEnd_short))
			throw new Exception("You forgot to set the Short Date Range");		
	
		$trackingCodeSearch = $this->getWidenedTrackingCodeByBannerClicks($this->trackingCode, $this->minimumClicksToMeasureConversions, $this->dateStart_short, $this->dateEnd_short);

		$this->log_trackCodeForConvRate = $trackingCodeSearch;

		if(empty($trackingCodeSearch)){
			
			// Figure out what the tracking code looks like after being widened to the max level.
			$widenedTrackingCodeForLog = $this->getTrackingCode();
			for($i=0; $i<20; $i++){
				
				$thisWidenedCode = $this->widenTrackingCodeForConverionRates($widenedTrackingCodeForLog);
				if(empty($thisWidenedCode))
					break;
				
				$widenedTrackingCodeForLog = $thisWidenedCode;
			}
			
			$bannerClicksForLog = $this->getBannerClicksFromTrackingCodeSearch($widenedTrackingCodeForLog, $this->dateStart_short, $this->dateEnd_short);
			
			$this->trackingCodeLog .= "Not enough short-term banner clicks after widening ( $widenedTrackingCodeForLog ). Minimum clicks : $this->minimumClicksToMeasureConversions  Between: " . date("n-j-Y", $this->dateStart_short) . " and " . date("n-j-Y", $this->dateEnd_short) . " Actual Clicks: $bannerClicksForLog \n";
			return false;
		}
			
		$orderCount = sizeof($this->getOrderIDsFromTrackingCodeSearch($trackingCodeSearch, $this->dateStart_short, $this->dateEnd_short));
		
		$bannerClicks = $this->getBannerClicksFromTrackingCodeSearch($trackingCodeSearch, $this->dateStart_short, $this->dateEnd_short);

		$conversionRateForTrackingCode = round(($orderCount / $bannerClicks), 4);
		
		$this->trackingCodeLog .= "Widened Code by Banner Clicks: " . $trackingCodeSearch . " has short-term conversion rate: " . (100 * $conversionRateForTrackingCode) . "% Clicks: $bannerClicks \n";
		
		$this->log_conversionRate = $conversionRateForTrackingCode;
		
		return $conversionRateForTrackingCode;
	}
	
	
	// The method getConversionRateForTrackingCode() returns tracking codes with Wildcard characters for SQL. 
	// This will return the tracking code that is save to search for within a regular expression like preg_match("/".$this->getConversionRateTrackingCodeRegEx."/", $someTrackingCode)
	// This can be helpful to compare against he Google API results.
	function getConversionRateTrackingCodeRegEx(){
		
		// Convert Dashses and other possible special language constructs used in regular expressions.
		$retCode = preg_quote($this->getConversionRateForTrackingCode());
		
		// Convert Wildcard Percentages into .* (in regular expression that means any character zero or more times.
		return preg_replace("/%/", ".*", $retCode);
	}
	
	
	// Will return what a customer is worth... based upon the tracking code... until the "break even days" value.
	// The larger the "break even" duration is... the more valuable the customer will become because of re-orders.
	function getCustomerWorthByTrackingCode(){

		if(empty($this->trackingCode))
			throw new Exception("The tracking code must be set before calling this method.");
			
		if($this->breakEvenAfterXdays < 0)
			throw new Exception("The Break Even duration has not been set yet.");
			
		$trackingCodeSearch_ShortTerm = $this->getWidenedTrackingCodeByMinOrders($this->trackingCode, $this->minimumOrders_short, $this->dateStart_short, $this->dateEnd_short);
		if(empty($trackingCodeSearch_ShortTerm)){
			$trackingCodeSearch_ShortTerm = "Not-Enough-Orders";
			$orderCountWidened_ShortTerm = 0;
		}
		else{
			$orderCountWidened_ShortTerm = sizeof($this->getOrderIDsFromTrackingCodeSearch($trackingCodeSearch_ShortTerm, $this->dateStart_short, $this->dateEnd_short));
		}
		
		$trackingCodeSearch_MidTerm = $this->getWidenedTrackingCodeByMinOrders($this->trackingCode, $this->minimumOrders_mid, $this->dateStart_mid, $this->dateEnd_mid);
		if(empty($trackingCodeSearch_MidTerm)){
			$trackingCodeSearch_MidTerm = "Not-Enough-Orders";
			$orderCountWidened_MidTerm = 0;
		}
		else{
			$orderCountWidened_MidTerm = sizeof($this->getOrderIDsFromTrackingCodeSearch($trackingCodeSearch_MidTerm, $this->dateStart_mid, $this->dateEnd_mid));
		}
		
		$trackingCodeSearch_LongTerm = $this->getWidenedTrackingCodeByMinOrders($this->trackingCode, $this->minimumOrders_long, $this->dateStart_long, $this->dateEnd_long);
		if(empty($trackingCodeSearch_LongTerm)){
			$trackingCodeSearch_LongTerm = "Not-Enough-Orders";
			$orderCountWidened_LongTerm = 0;
		}
		else{
			$orderCountWidened_LongTerm = sizeof($this->getOrderIDsFromTrackingCodeSearch($trackingCodeSearch_LongTerm, $this->dateStart_long, $this->dateEnd_long));
		}
		
		$this->trackingCodeLog .= "Widened Code by Minimum Orders: SHORT < $trackingCodeSearch_ShortTerm > $orderCountWidened_ShortTerm orders ($this->minimumOrders_short min orders), Mid < $trackingCodeSearch_MidTerm > $orderCountWidened_MidTerm orders ($this->minimumOrders_mid min orders), long < $trackingCodeSearch_LongTerm > $orderCountWidened_LongTerm orders ($this->minimumOrders_long min orders)\n";
		
		
		
		// Figure out if we have already calculated Short-Term Customer Worth on a widened Tracking Code recently.
		$this->dbCmd->Query("SELECT CustomerWorthShortTerm FROM costperclicklog USE INDEX(costperclicklog_Date)
					WHERE Date > DATE_ADD(NOW(), INTERVAL -24 HOUR) AND TrackCodeShortTermWorth='".DbCmd::EscapeSQL($trackingCodeSearch_ShortTerm)."' 
					AND DomainID=$this->domainID");
		$shortTermWorth = $this->dbCmd->GetValue();
		if($shortTermWorth == null){
			print "<font color='#330044'>Not Cached. Must calculate Short Term customer worth from TrackingCode: $trackingCodeSearch_ShortTerm</font><br>";
			flush();
			$shortTermWorth = $this->estimateShortTermCustomerWorth();
		}
			
print "<font color='#000066'>Short Term Worth: $shortTermWorth</font><br>";
flush();		
		// Figure out if we have already calculated Mid-Term Customer Worth on a widened Tracking Code recently.
		$this->dbCmd->Query("SELECT CustomerWorthMediumTerm FROM costperclicklog USE INDEX(costperclicklog_Date)
					WHERE Date > DATE_ADD(NOW(), INTERVAL -24 HOUR) AND TrackCodeMediumTermWorth='".DbCmd::EscapeSQL($trackingCodeSearch_MidTerm)."' 
					AND DomainID=$this->domainID");
		$midTermWorth = $this->dbCmd->GetValue();
		if($midTermWorth == null){
			print "<font color='#330044'>Not Cached. Must calculate Mid Term customer worth from TrackingCode: $trackingCodeSearch_MidTerm</font><br>";
			flush();
			$midTermWorth = $this->estimateMidTermCustomerWorth();
		}
		
print "<font color='#000099'>Mid Term Worth: $midTermWorth</font><br>";
flush();

		// Figure out if we have already calculated Long-Term Customer Worth on a widened Tracking Code recently.
		$this->dbCmd->Query("SELECT CustomerWorthLongTerm FROM costperclicklog USE INDEX(costperclicklog_Date)
					WHERE Date > DATE_ADD(NOW(), INTERVAL -24 HOUR) AND TrackCodeLongTermWorth='".DbCmd::EscapeSQL($trackingCodeSearch_LongTerm)."' 
					AND DomainID=$this->domainID");
		$longTermWorth = $this->dbCmd->GetValue();
		if($longTermWorth == null){
			print "<font color='#330044'>Not Cached. Must calculate Long Term customer worth from TrackingCode: $trackingCodeSearch_LongTerm</font><br>";
			flush();
			$longTermWorth = $this->estimateLongTermCustomerWorth();
		}

		
print "<font color='#0000cc'>Long Term Worth: $longTermWorth</font><br>";
flush();

		$this->log_customerWorthLongTerm = $longTermWorth;
		$this->log_customerWorthMediumTerm = $midTermWorth;
		$this->log_customerWorthShortTerm = $shortTermWorth;
		
		$this->log_trackCodeShortTermWorth = $trackingCodeSearch_ShortTerm;
		$this->log_trackCodeMediumTermWorth = $trackingCodeSearch_MidTerm;
		$this->log_trackCodeLongTermWorth = $trackingCodeSearch_LongTerm;
		
		// If we can not meet our minimum order requirements (for all date ranges)... then we have no choice but to return the default customer worth.
		if($shortTermWorth === false && $midTermWorth === false && $longTermWorth === false){
			$defaultWorth = $this->formatCustomerWorth(($this->getDefaultCustomerWorthByTrackingCode() + $this->getAdditionalCustomerWorthBasedUponTrackingCode()));
			$this->trackingCodeLog .= "\n!!!!! NOT ENOUGH ORDERS TO TRUST CUSTOMER WORTH AVERAGE. Returning default customer worth: $this->currencySymbol" . $defaultWorth . "\n\n";
			return $defaultWorth;
		}
		
		
		// Our Short-Term Customer Worth is the most important. It may reflect coupons, price changes, economic situation, changes to Shipping Algorythms, etc.
		// However, we don't want our numbers to get totally skewed by a single giant customer that we never see again.
		// ... therefore we want to averge the customer worth between long, medium and short.  But we give twice the weight to medium (over long), and 3 times the weight to short (over long).
		// This is not too radical since generally short term Customer Worth is based off of Maturity estimations using long-term and mid-term data.  Generally Mid Term customer worth will use some of the long term maturity data.
		// It is like steering a big ship and the Short Term data is meant to act as the rudder.

		if($shortTermWorth !== false && $midTermWorth !== false && $longTermWorth !== false){
			$averageWorth = $this->formatCustomerWorth((($shortTermWorth * 3) + ($midTermWorth * 2) + $longTermWorth) / 6);
			$this->trackingCodeLog .= "Basing customer worth on all 3 terms with most weight on SHORT ($this->currencySymbol" . $shortTermWorth . "), then Mid ($this->currencySymbol" . $midTermWorth. "), then long ($this->currencySymbol" . $longTermWorth . "). Final Average: $this->currencySymbol" . $averageWorth . "\n";
			return $averageWorth;
		}
		else if($midTermWorth !== false && $longTermWorth !== false){
			$averegeWorth = $this->formatCustomerWorth(($longTermWorth + ($midTermWorth * 2)) / 3);
			$this->trackingCodeLog .= "Basing customer worth on Mid and long term data.  Most weight on Mid ($this->currencySymbol" . $midTermWorth . "), then long ($this->currencySymbol" . $longTermWorth . "). Final Average: $this->currencySymbol" . $averegeWorth . "\n";
			return $averageWorth;
		}
		else if($shortTermWorth !== false && $longTermWorth !== false){
			$averegeWorth = $this->formatCustomerWorth(($longTermWorth + ($shortTermWorth * 3)) / 4);
			$this->trackingCodeLog .= "Basing customer worth on SHORT and long term data.  Most weight on SHORT ($this->currencySymbol" . $shortTermWorth . "), then long ($this->currencySymbol" . $longTermWorth . "). Final Average: $this->currencySymbol" . $averegeWorth . "\n";
			return $averageWorth;
		}
		else if($shortTermWorth !== false && $midTermWorth !== false){
			$averegeWorth = $this->formatCustomerWorth(($midTermWorth + ($shortTermWorth * 2)) / 3);
			$this->trackingCodeLog .= "Basing customer worth on SHORT and Mid term data.  Most weight on SHORT ($this->currencySymbol" . $shortTermWorth. "), then Mid ($this->currencySymbol" . $midTermWorth . "). Final Average: $this->currencySymbol" . $averegeWorth . "\n";
			return $averageWorth;
		}
		else if($shortTermWorth !== false){
			$averageWorth = $this->formatCustomerWorth($shortTermWorth);
			$this->trackingCodeLog .= "Basing customer worth on SHORT term data only.  Final Average: $this->currencySymbol" . $averageWorth . "\n";
			return $averageWorth;
		}
		else if($midTermWorth !== false){
			$averageWorth = $this->formatCustomerWorth($midTermWorth);
			$this->trackingCodeLog .= "Basing customer worth on Mid term data only.  Final Average: $this->currencySymbol" . $averageWorth . "\n";
			return $averageWorth;
		}
		else if($longTermWorth !== false){
			$averageWorth = $this->formatCustomerWorth($longTermWorth);
			$this->trackingCodeLog .= "Basing customer worth on long term data only.  Final Average: $this->currencySymbol" . $averageWorth . "\n";
			return $averageWorth;
		}
			
		throw new Exception("This part should not be reached in the code.");
			
	}
	
	private function formatCustomerWorth($customerWorthNumber){
		
		$customerWorthNumber = floatval($customerWorthNumber);
		
		// $this->getAdditionalCustomerWorthBasedUponTrackingCode() could be negative.
		// It is probably OK since sometimes we are getting customers at a loss... however, we should make note of it in our log file.
		if($customerWorthNumber < 0){
			$this->trackingCodeLog .= "Customer Worth Value is less than zero: $customerWorthNumber\n";
		}
			
		return number_format($customerWorthNumber, 2, '.', '');
		
	}
	
	// Returns FALSE (check with $var === false) there are not enough customers to trust the value.  "Sufficient empirical data".
	function estimateLongTermCustomerWorth(){

		if($this->breakEvenAfterXdays < 0)
			throw new Exception("You forgot to set the Break-Even days.");
		
		// We should always be able to estimate our customer worth from long-term data by the end of the break-even period.
		// This may not be possible from Short and Mid Term worth data because we may want to estimate what they will be worth 1 year later... (not possible from short & probably mid term).
		if($this->dateEnd_long + ($this->breakEvenAfterXdays * 60 * 60 * 24) > time())
			throw new Exception("A requirement of this module is that the Break Even days plus the Long Term End Date do not go past present time.");
		
		$trackingCodeSearch_LongTerm = $this->getWidenedTrackingCodeByMinOrders($this->trackingCode, $this->minimumOrders_long, $this->dateStart_long, $this->dateEnd_long);
		
		if(empty($trackingCodeSearch_LongTerm))
			return false;
			
		$customerWorthObj = new CustomerWorth(array($this->domainID));
		$customerWorthObj->setNewCustomerAcquisitionPeriod($this->dateStart_long, $this->dateEnd_long);
		$customerWorthObj->setEndPeriodDaysFromAcqDate($this->breakEvenAfterXdays);
		$customerWorthObj->setTrackingCodeSearch($trackingCodeSearch_LongTerm);
		$customerWorthObj->setNomadFactor(true);
		$customerWorthObj->setNomadPercentage($this->nomadPercentage);
		
		$returnWorth = $customerWorthObj->getAverageProfitByEndPeriod() + $this->getAdditionalCustomerWorthBasedUponTrackingCode();
		
		return $returnWorth;
	}
	
	// Returns FALSE (check with $var === false) there are not enough customers to trust the value.  "Sufficient empirical data".
	// If the Break-Even-Date plus the mid-term-end date happens before today... then it will try to estimate mid-term worth by gather data from long-term customer worth.
	// ... If it tries to do that... but there is not sufficient long-term-worth ... then it will return FALSE.
	function estimateMidTermCustomerWorth(){

		if($this->breakEvenAfterXdays < 0)
			exit("Break even days has not been set yet.");
		
		$trackingCodeSearch_MidTerm = $this->getWidenedTrackingCodeByMinOrders($this->trackingCode, $this->minimumOrders_mid, $this->dateStart_mid, $this->dateEnd_mid);
		
		if(empty($trackingCodeSearch_MidTerm))
			return false;
		
		$customerWorthObj = new CustomerWorth(array($this->domainID));
		$customerWorthObj->setNewCustomerAcquisitionPeriod($this->dateStart_mid, $this->dateEnd_mid);
		$customerWorthObj->setEndPeriodDaysFromAcqDate($this->customerWorthEndDays_MidTerm);
		$customerWorthObj->setTrackingCodeSearch($trackingCodeSearch_MidTerm);
		$customerWorthObj->setNomadFactor(true);
		$customerWorthObj->setNomadPercentage($this->nomadPercentage);
		
		$currentMidTermCustomerWorth = $customerWorthObj->getAverageProfitByEndPeriod();
			
		// If the Break Even (plus end date) happens before today... then we don't have to guess at the customer worth.
		if($this->checkIfEndDatePlusDaysUntilBreakEvenLessThanNow("mid")){
			$returnWorth = $currentMidTermCustomerWorth + $this->getAdditionalCustomerWorthBasedUponTrackingCode();
			return $returnWorth;
		}
		
		// Otherwise we need to estimate the MidTerm Customer Worth based ordering history in the past (from long term data).
		$trackingCodeSearch_LongTerm = $this->getWidenedTrackingCodeByMinOrders($this->trackingCode, $this->minimumOrders_long, $this->dateStart_long, $this->dateEnd_long);
		
		// If we don't have any Long Term orders yet... then we can only return the Mid Data that we currently have.  The number will probably rise, but we are not going to guess.
		if(empty($trackingCodeSearch_LongTerm)){
			$returnWorth = $customerWorthObj->getAverageProfitByEndPeriod() + $this->getAdditionalCustomerWorthBasedUponTrackingCode();
			return $returnWorth;
		}
			
		
		// If we get this far... then the user must set End Period Days within the Controller Script.
		if($this->customerWorthEndDays_MidTerm < 1)
			throw new Exception("You forgot to set the End Days for the mid-term customer worth.");
			
		
		$customerWorthObj->setNewCustomerAcquisitionPeriod($this->dateStart_long, $this->dateEnd_long);
		$customerWorthObj->setTrackingCodeSearch($trackingCodeSearch_LongTerm);
		$customerWorthObj->setEndPeriodDaysFromAcqDate($this->customerWorthEndDays_MidTerm);
		$longTermWorth_WithMidDays = $customerWorthObj->getAverageProfitByEndPeriod();
		
		$customerWorthObj->setEndPeriodDaysFromAcqDate($this->breakEvenAfterXdays);
		$longTermWorth_FullMatured = $customerWorthObj->getAverageProfitByEndPeriod();
		
		$longTermMaturityDifference = $longTermWorth_FullMatured - $longTermWorth_WithMidDays;
		
		$anticipatedMidTermWorthAfterMaturity = $currentMidTermCustomerWorth + $longTermMaturityDifference;
		
		$returnWorth = $anticipatedMidTermWorthAfterMaturity + $this->getAdditionalCustomerWorthBasedUponTrackingCode();
		
		return $returnWorth;
	}
	
	
	// Returns FALSE (check with $var === false) there are not enough customers to trust the value.  "Sufficient empirical data".
	// If the Break-Even-Date plus the short-term-end date happens before today... then it will try to estimate short-term worth by gather data from both long-term and mid-term customer worth.
	// ... If it tries to do that... but there is not sufficient long-term-worth OR mid-term-worth ... then it will return FALSE.
	function estimateShortTermCustomerWorth(){
		
		if($this->breakEvenAfterXdays < 0)
			exit("Break even days has not been set yet.");
		
		$trackingCodeSearch_ShortTerm = $this->getWidenedTrackingCodeByMinOrders($this->trackingCode, $this->minimumOrders_short, $this->dateStart_short, $this->dateEnd_short);
		
		if(empty($trackingCodeSearch_ShortTerm))
			return false;
			
		$customerWorthObj = new CustomerWorth(array($this->domainID));
		$customerWorthObj->setNewCustomerAcquisitionPeriod($this->dateStart_short, $this->dateEnd_short);
		$customerWorthObj->setEndPeriodDaysFromAcqDate($this->customerWorthEndDays_ShortTerm);
		$customerWorthObj->setTrackingCodeSearch($trackingCodeSearch_ShortTerm);
		$customerWorthObj->setNomadFactor(true);
		$customerWorthObj->setNomadPercentage($this->nomadPercentage);
		
		$currentShortTermCustomerWorth = $customerWorthObj->getAverageProfitByEndPeriod();
			
		// If the Break Even (plus short-term end date) happens before today... then we don't have to guess at the customer worth by looking for extra maturity.
		if($this->checkIfEndDatePlusDaysUntilBreakEvenLessThanNow("short")){
			$returnWorth = $currentShortTermCustomerWorth + $this->getAdditionalCustomerWorthBasedUponTrackingCode();
			return $returnWorth;
		}
		
		$trackingCodeSearch_MidTerm = $this->getWidenedTrackingCodeByMinOrders($this->trackingCode, $this->minimumOrders_mid, $this->dateStart_mid, $this->dateEnd_mid);
		$trackingCodeSearch_LongTerm = $this->getWidenedTrackingCodeByMinOrders($this->trackingCode, $this->minimumOrders_long, $this->dateStart_long, $this->dateEnd_long);
	
		// If we get this far... then the user must set End Period Days within the Controller Script.
		if($this->customerWorthEndDays_ShortTerm < 1)
			throw new Exception("You forgot to set the End Days for the short-term customer worth.");
	
		// Just initialize variables. These values won't get used.  I don't like to initialize variables in a conditional statement.
		$midTermExtraMaturityFromShortDays = 0;
		$longTermExtraMaturityFromShortDays = 0;
		
		// Estimate Mid Term Customer Maturity if the mid-term only had short days.  We can add that to our Short Term Data and predict customers will have similar habbits.
		if(!empty($trackingCodeSearch_MidTerm)){
			
			$customerWorthObj->setNewCustomerAcquisitionPeriod($this->dateStart_mid, $this->dateEnd_mid);
			$customerWorthObj->setTrackingCodeSearch($trackingCodeSearch_MidTerm);
			$customerWorthObj->setEndPeriodDaysFromAcqDate($this->customerWorthEndDays_ShortTerm);
			$midTermWorth_WithShortDays = $customerWorthObj->getAverageProfitByEndPeriod();
			
			$midTermExtraMaturityFromShortDays = $this->estimateMidTermCustomerWorth() - $midTermWorth_WithShortDays;
		}
		
		// Estimate Long Term Customer Maturity if the long-term only had short days.  We can add that to our Short Term Data and predict customers will have similar habbits.
		if(!empty($trackingCodeSearch_LongTerm)){
			
			$customerWorthObj->setNewCustomerAcquisitionPeriod($this->dateStart_long, $this->dateEnd_long);
			$customerWorthObj->setTrackingCodeSearch($trackingCodeSearch_LongTerm);
			$customerWorthObj->setEndPeriodDaysFromAcqDate($this->customerWorthEndDays_ShortTerm);
			$longTermWorth_WithShortDays = $customerWorthObj->getAverageProfitByEndPeriod();
			
			$longTermExtraMaturityFromShortDays = $this->estimateLongTermCustomerWorth() - $longTermWorth_WithShortDays;
		}
print "Effective Short Term Days Till end period: $this->customerWorthEndDays_ShortTerm <br>";
print "Short Term Worth: \$" . number_format($currentShortTermCustomerWorth) . "<br>";	
print "Extra Mid-Term Maturity: \$" . number_format($midTermExtraMaturityFromShortDays) . "<br>";	
print "Extra Long-Term Maturity: \$" . number_format($longTermExtraMaturityFromShortDays) . "<br>";	
		
		// ----- Now figure out if we can Average out the Maturity between Long & Mid Term... or maybe we can't use any maturity data.
		
		if(empty($trackingCodeSearch_MidTerm) && empty($trackingCodeSearch_LongTerm)){
			
			// If we don't have enough data in the Mid and Long term tracking codes... then we can't estimate customer worth maturity, just return whatever we have then.
			$returnWorth = $currentShortTermCustomerWorth + $this->getAdditionalCustomerWorthBasedUponTrackingCode();
			
			return $returnWorth;
			
		}
		else if(!empty($trackingCodeSearch_MidTerm) && !empty($trackingCodeSearch_LongTerm)){
			
			// We have order history on both tracking codes.  So therefore we are going to average the maturity between both and add that to our current Short Term data.
			$averageMaturity = ($longTermExtraMaturityFromShortDays + $midTermExtraMaturityFromShortDays) / 2;

			$returnWorth = $currentShortTermCustomerWorth + $averageMaturity + $this->getAdditionalCustomerWorthBasedUponTrackingCode();
			
			return $returnWorth;
		
		}
		else if(!empty($trackingCodeSearch_MidTerm)){
			
			$returnWorth = $currentShortTermCustomerWorth + $midTermExtraMaturityFromShortDays + $this->getAdditionalCustomerWorthBasedUponTrackingCode();
			
			return $returnWorth;

		}
		else if(!empty($trackingCodeSearch_LongTerm)){
			
			$returnWorth = $currentShortTermCustomerWorth + $longTermExtraMaturityFromShortDays + $this->getAdditionalCustomerWorthBasedUponTrackingCode();
			
			return $returnWorth;	
		}
		
		throw new Exception("Should not reach this area in the code.");
	}
	
	
	
	private function checkIfEndDatePlusDaysUntilBreakEvenLessThanNow($rangeType){
		
		if($this->breakEvenAfterXdays < 0)
			exit("Break even days has not been set yet.");
		
		if($rangeType == "short"){
			if(($this->dateEnd_short + (60 * 60 * 24 * $this->breakEvenAfterXdays)) < time())
				return true;
			else
				return false;
		}
		else if($rangeType == "mid"){
			if(($this->dateEnd_mid + (60 * 60 * 24 * $this->breakEvenAfterXdays)) < time())
				return true;
			else
				return false;
		}
		else if($rangeType == "long"){
			if(($this->dateEnd_long + (60 * 60 * 24 * $this->breakEvenAfterXdays)) < time())
				return true;
			else
				return false;
		}
		else{
			throw new Exception("Error with range type");
		}
		
	}
	
	
	// In case we make a change to something that will start bringing in additional revenue... we could add additional customer worth to our tracking codes so we don't have to wait for the changes to kick in.
	// Also, maybe there is a hidden value on customers that our website is not able to calculate.  Or maybe we are trying to figure if we sell the website in the future each "Save The Date Postcard Customer" we have will be worth an extra $15 or something?
	// The return value can be Negative. So be careful a calculated Customer Worth value never falls bellow zero or we may try to bid a negative CPC.
	function getAdditionalCustomerWorthBasedUponTrackingCode(){
		
		if(empty($this->trackingCode))
			throw new Exception("The tracking code must be set before calling this method.");
			
		// This will match business card tracking codes, regardless of search engine.
		if(preg_match("/bc_.*$/", $this->trackingCode))
			return 0;
		else 	
			return 0;
	}
	
	function setTrackingCode($trackingCode){
		
		$trackingCode = trim(WebUtil::FilterData($trackingCode, FILTER_SANITIZE_STRING_ONE_LINE));
		
		if(empty($trackingCode))
			throw new Exception("Tracking Code not not be left blank.");
			
		if(preg_match("/(%|\\*)/", $trackingCode))
			throw new Exception("wildcards are not permitted in the tracking code.");
			
		$this->trackingCode = $trackingCode;
		
		$this->log_trackingCode = $trackingCode;
		
		if(!$this->checkIfTrackingCodeIsCached()){
			// Everytime we set a new tracking code, we assume that our log will be filled later with details about the results of that tracking code.
			// This works good if you keep setting new tracking codes for reports (without destroying the object).
			$this->trackingCodeLog .= "\n\n\n\n\n\n\n--------------------------- Tracking Code Search -----------------\n" . $trackingCode . "\n------------------------------------------------------------------\n";
		}	

		
	}
	
	
	// Set the number of days that you want us to break even by (or make money).
	// To make money immediately, set to zero.
	function setBreakEvenDayCount($days){
		
		$this->breakEvenAfterXdays = intval($days);
		
		$this->log_breakEvenDays = $this->breakEvenAfterXdays;
	}
	
	// Let's say we want to grow the customer base significatnly (even willing to lose money).
	// In this case... we will never be able to break even based upon customer worth.
	// So if we wanted to be agressive, we could set the Profit Percentage to -0.10  (meaning negative ten percent).  We are willing to spend 10% more to aquire the customer (on advertising) than we get back after the Break Even Days. 
	// If the Customer Worth is estimated at $100 for a particular tracking code after 365 days.  We could set the Profit percentage to postive 25% (0.25) and theortically make $25 from each customer after 365 days.
	// This letter approach would have an adverse affect on Bid Prices by lowering 
	function setProfitPercentage($percentageFloat){
		
		$this->profitPercentage = floatval($percentageFloat);
	}
	
	function setMinimumOrderShortTerm($orderCount){
		$this->minimumOrders_short = intval($orderCount);
	}
	function setMinimumOrderMidTerm($orderCount){
		$this->minimumOrders_mid = intval($orderCount);
	}
	function setMinimumOrderLongTerm($orderCount){
		$this->minimumOrders_long = intval($orderCount);
	}
	function setMinimumClicksToMeasureConversions($minClicks){
		$this->minimumClicksToMeasureConversions = intval($minClicks);
	}
	function setDateRangeShortTerm($timeStampStart, $timeStampEnd){
		$this->checkDateRanges($timeStampStart, $timeStampEnd);
		$this->dateStart_short = $timeStampStart;
		$this->dateEnd_short = $timeStampEnd;
	}
	
	function setDateRangeMidTerm($timeStampStart, $timeStampEnd){
		$this->checkDateRanges($timeStampStart, $timeStampEnd);
		$this->dateStart_mid = $timeStampStart;
		$this->dateEnd_mid = $timeStampEnd;
	}
	
	function setDateRangeLongTerm($timeStampStart, $timeStampEnd){
		$this->checkDateRanges($timeStampStart, $timeStampEnd);
		$this->dateStart_long = $timeStampStart;
		$this->dateEnd_long = $timeStampEnd;
	}
	

	
	private function checkDateRanges($startTimeStamp, $endTimeStamp){
		
		$startTimeStamp = intval($startTimeStamp);
		$endTimeStamp = intval($endTimeStamp);
		
		if(empty($startTimeStamp) || empty($endTimeStamp))
			throw new Exception("The Timestamps can not be left blank.");
			
		if($startTimeStamp >= $endTimeStamp)
			throw new Exception("The Start timestamp can not be greater than the ending.");
	}
	
	function getTrackingCode(){
		return $this->trackingCode;
	}
	
	
	// Pass in a Tracking Code, a minimum order requirement, and a date range.
	// This method will return a tracking code (with the least amount of widening), in order to meet the goals.
	// If it can not meet the minimum order goal within the date range, then this method will return NULL.
	// Set a Max Level to a lower number if you want it to stop early.  Like if you set to "1" then it will only do the first iteration, and then return null afterwards.
	function getWidenedTrackingCodeByMinOrders($trackingCode, $minimumOrders, $timeStampStart, $timeStampEnd){
		
		$this->checkDateRanges($timeStampStart, $timeStampEnd);
		
		$minimumOrders = intval($minimumOrders);
		
		if($minimumOrders < 1)
			throw new Exception("In order to get a widened tracking code the minimum orders must be at least 1.");

		// Prevent an infinite loop in case there is a bug with the Widening algorythm.
		$maxIterations = 20;
		$loopCounter = 0;
		
		
		while(true){
			
			$loopCounter++;
			
			if(sizeof($this->getOrderIDsFromTrackingCodeSearch($trackingCode, $timeStampStart, $timeStampEnd)) >= $minimumOrders)
				return $trackingCode;
				
			if($loopCounter > $maxIterations)
				throw new Exception("Infinite loop detected in getWidenedTrackingCodeByMinOrders() method.");
			
			// Try going wider.
			$trackingCode = $this->widenTrackingCodeForCustomerWorth($trackingCode);
			
			if(empty($trackingCode))
				break;
		}
		
		// Even after widening all of the way we still can't meet the minimum .
		return null;
	}

	
	// Pass in a Tracking Code, a minimum Click requirement, and a date range.
	// This method will return a tracking code (with the least amount of widening), in order to meet the goals.
	// If it can not meet the minimum order goal within the date range, then this method will return NULL.
	// Set a Max Level to a lower number if you want it to stop early.  Like if you set to "1" then it will only do the first iteration, and then return null afterwards.
	function getWidenedTrackingCodeByBannerClicks($trackingCode, $minimumClicks, $timeStampStart, $timeStampEnd){
		
		$this->checkDateRanges($timeStampStart, $timeStampEnd);
		
		$minimumClicks = intval($minimumClicks);
		
		if($minimumClicks < 1)
			throw new Exception("In order to get a widened tracking code the minimum clicks must be at least 1.");

		// If we have cached the result... then avoid doing any work.
		$this->dbCmd->Query("SELECT TrackCodeForConvRate FROM costperclicklog USE INDEX(costperclicklog_Date)
					WHERE Date > DATE_ADD(NOW(), INTERVAL -24 HOUR) AND TrackingCode='".DbCmd::EscapeSQL($this->trackingCode)."' 
					AND MinOrdersToMeasureConversion=$minimumClicks AND DomainID=$this->domainID");
		$trackingCodeForConversion = $this->dbCmd->GetValue();
		if(!empty($trackingCodeForConversion))
			return $trackingCodeForConversion;

			
		// Prevent an infinite loop in case there is a bug with the Widening algorythm.
		$maxIterations = 20;
		$loopCounter = 0;
		
		while(true){
			
			$loopCounter++;
			
			if($this->getBannerClicksFromTrackingCodeSearch($trackingCode, $timeStampStart, $timeStampEnd) >= $minimumClicks)
				return $trackingCode;
				
			if($loopCounter > $maxIterations)
				throw new Exception("Infinite loop detected in getWidenedTrackingCodeByBannerClicks() method.");
			
			// Try going wider.
			$trackingCode = $this->widenTrackingCodeForConverionRates($trackingCode);
			
			// Unless we can't got any wider.
			if(empty($trackingCode))
				break;
		}
		
		// Even after widening all of the way we still can't meet the minimum.
		return null;
	}
	
	
	
	// Returns an Array of Order IDs that match the tracking code search within the date range.
	// It only returns the order ID's from first-time customers.
	function getOrderIDsFromTrackingCodeSearch($trackingCode, $timeStampStart, $timeStampEnd){
		
		$this->checkDateRanges($timeStampStart, $timeStampEnd);
		
		if(empty($trackingCode))
			throw new Exception("The tracking code can not be left empty.");
			
		// Make sure that Underscores are escaped (because they are WildCard characters in MySQL.
		$trackingCode = DbCmd::EscapeSQL(trim($trackingCode));
		$trackingCode = preg_replace("/_/", "\\_", $trackingCode);
			
		$this->dbCmd->Query("SELECT ID FROM orders USE INDEX (orders_DateOrdered) WHERE Referral LIKE '".$trackingCode."' 
								AND FirstTimeCustomer='Y'
								AND DateOrdered BETWEEN " . DbCmd::convertUnixTimeStmpToMysql($timeStampStart) . " AND " . DbCmd::convertUnixTimeStmpToMysql($timeStampEnd) . " 
								AND DomainID=" . $this->domainID);

		$firstTimeCustomerOrderIDs = $this->dbCmd->GetValueArr();
		
		return $firstTimeCustomerOrderIDs;
	}
	
	
	// Returns a count of how many times a banner code has been clicked on within the time rnage.
	function getBannerClicksFromTrackingCodeSearch($trackingCode, $timeStampStart, $timeStampEnd){
		
		$this->checkDateRanges($timeStampStart, $timeStampEnd);
		
		if(empty($trackingCode))
			throw new Exception("The tracking code can not be left empty.");
			
		// Make sure that Underscores are escaped (because they are WildCard characters in MySQL.
		$trackingCode = DbCmd::EscapeSQL(trim($trackingCode));
		$trackingCode = preg_replace("/_/", "\\_", $trackingCode);
			
		$this->dbCmd->Query("SELECT COUNT(*) FROM bannerlog USE INDEX (bannerlog_Date) WHERE Name LIKE '".$trackingCode."' 
								AND Date BETWEEN " . DbCmd::convertUnixTimeStmpToMysql($timeStampStart) . " AND " . DbCmd::convertUnixTimeStmpToMysql($timeStampEnd) . " 
								AND DomainID=" . $this->domainID);
		
		return $this->dbCmd->GetValue();
	}
	
	

	// In case the tracking code search did not a minimum requirement... 
	// This method will widen the tracking code seach by injecting wildcards using % percent signs for SQL.
	// Returns NULL if it is not able to widen the code anymore.
	// Widening a traffic code for the Customer Worth is different than widening for Conversion Rates.  For example... the converion rates between Miami and Los Angeles are probably the same... but the Customer Worth is different because they are in different zones.
	function widenTrackingCodeForCustomerWorth($trackingCodeToWiden){
		
		
		// First eliminate the difference between a State and Cities because they will probably share lots of customer worth similarities.
		// For example... change g-geo_bCty_Z4-3-em-bc_general to g-geo_%_Z4-3-em-bc_general
		$regex = "/geo_[a-zA-Z]+_Z/";
		if(preg_match($regex, $trackingCodeToWiden)){
			return preg_replace($regex, 'geo_%_Z', $trackingCodeToWiden);
		}
		
		
		// Next is to eliminate the Advertising Number, or advertising strategy ID.
		// For example... change g-geo_%_Z4-3-em-bc_general to g-geo_%_Z4-%-em-bc_general
		$regex = "/geo_%_Z(\d+)\-\d+\-/";
		if(preg_match($regex, $trackingCodeToWiden)){
			return preg_replace($regex, 'geo_%_Z${1}-%-', $trackingCodeToWiden);
		}
		
		
		// If the user found our comapny through phrase match, exact match, or broad match.. that shouldn't make much of a different to customer worth.
		// However, is something searches for "how to create the ugliest business card" you don't want that "broad match" mixed in with "exact match" customers for "business cards".
		// For Example... change g-geo_%_Z4-%-em-bc_general to g-geo_%_Z4-%-%-bc_general
		$regex = "/\-(pm|em|bm)\-/";
		if(preg_match($regex, $trackingCodeToWiden)){
			return preg_replace($regex, '-', $trackingCodeToWiden);
		}
		
		// Next is to eliminate the Shipping Zone number.  This is important to customer worth... but if we have to do it, then we have to do it.
		// For example... change g-geo_%_Z4-%-%-bc_general  to  g-geo_%_Z%-%-%-bc_general
		$regex = "/geo_%_Z\d+\-/";
		if(preg_match($regex, $trackingCodeToWiden)){
			return preg_replace($regex, 'geo_%_Z%-', $trackingCodeToWiden);
		}
		
		
		// Next get rid of all geographical references.  So the customer worth for g-all and g-geo are blended together.
		// For example... change g-geo_%_Z%-%-%-bc_general  to   g-%-%-bc_general
		$regex = "/geo_%_Z%\-%\-/";
		if(preg_match($regex, $trackingCodeToWiden)){
			return preg_replace($regex, '%-', $trackingCodeToWiden);
		}
		


	
		// Next thing is to strip off the keyword identifier from the product name.
		// For Example... change g-%-%-bc_general  to  g-%-%-bc_%
		$regex = "/\-([a-z]+)_\w+$/";
		if(preg_match($regex, $trackingCodeToWiden)){
			return preg_replace($regex, '-${1}_%', $trackingCodeToWiden);
		}
		
		
		
		// Otherwise Return NULL because we can't widen the tracking code anymore.
		return null;
	}
	
	
	
	
	// In case the tracking code search did not a minimum requirement... 
	// This method will widen the tracking code seach by injecting wildcards using % percent signs for SQL.
	// Returns NULL if it is not able to widen the code anymore.
	// Widening a traffic code for the Customer Worth is different than widening for Conversion Rates.  For example... the converion rates between Miami and Los Angeles are probably the same... but the Customer Worth is different because they are in different zones.
	function widenTrackingCodeForConverionRates($trackingCodeToWiden){
		
		// The Shipping Zone is not very important for the converion rates.
		// For example... change g-geo_bCty_Z4-3-em-bc_general to g-geo_bCty_Z%-3-em-bc_general
		$regex = "/_Z\d+\-/";
		if(preg_match($regex, $trackingCodeToWiden)){
			return preg_replace($regex, '_Z%-', $trackingCodeToWiden);
		}
		
		
		// Converion rates are only measured in the Short-Term.  So we may not have different advertising ID's 
		// However, if we do have different advertisign ID's... eliminate the Advertising Number, or advertising strategy ID.
		// For example... change g-geo_bCty_Z%-3-em-bc_general to g-geo_bCty_Z%-%-em-bc_general
		$regex = "/_Z%\-\d+\-/";
		if(preg_match($regex, $trackingCodeToWiden)){
			return preg_replace($regex, '_Z%-', $trackingCodeToWiden);
		}
		
		
		// Next thing is to strip off the keyword identifier from the product name.
		// Assume that any business cards variations have similar converion rates.
		// For Example... change g-%-pm-bc_general to g-%-pm-bc_%
		$regex = "/\-([a-z]+)_\w+$/";
		if(preg_match($regex, $trackingCodeToWiden)){
			return preg_replace($regex, '-${1}_%', $trackingCodeToWiden);
		}
		
		
		// Next is to eliminate the difference between a States, Big, and Small Cities.  
		// There is a big difference between Small and Big City conversion rates.  As the CTR rises, the Conversion Rates fall.
		// The difference between big and small cities seems to be more significant than differences in conversions between product identifies (like bc_%).
		// For example... change g-geo_bCty_Z%-%-em-bc_general  to  g-geo_%_Z%-%-em-bc_general
		$regex = "/geo_[a-zA-Z]+_Z/";
		if(preg_match($regex, $trackingCodeToWiden)){
			return preg_replace($regex, 'geo_%_Z', $trackingCodeToWiden);
		}

		
		// Next get rid of all geographical references.  So "geo" can be turned into "all"
		// For example... change g-geo_%_Z%-%-em-bc_general to g-%-em-bc_general
		$regex = "/geo_%_Z%\-/";
		if(preg_match($regex, $trackingCodeToWiden)){
			return preg_replace($regex, '%-', $trackingCodeToWiden);
		}


		// The last thing we want to get rid of is "phrase match", "exact match", and "broad match" because 
		// For Example... change g-%-pm-bc_% to g-%-%-bc_%
		$regex = "/\-%\-(pm|em|bm)\-/";
		if(preg_match($regex, $trackingCodeToWiden)){
			return preg_replace($regex, '-%-', $trackingCodeToWiden);
		}
		
	
		
		// Otherwise Return NULL because we can't widen the tracking code anymore.
		return null;
	}
}






/*

// Test the Tracking Code Widening algorythm by executing this Class in your browser on the Dev Server

require_once("../library/Boot_Session.php");
$cpcObj = new CostPerClickEstimate(1);
$trackCodeOriginal = "g-geo_bCty_Z5-3-em-bc_appointment";



print "<u>Starting Tracking Code Widening For Conversion Rates:</u><br>";
$trackCode = $trackCodeOriginal;
print $trackCode . "<br>";

$counter = 0;

while($trackCode = $cpcObj->widenTrackingCodeForConverionRates($trackCode)){
	print $trackCode . "<br>";
	
	$counter++; 
	
	if($counter > 100)
		break;
}

print "<br><br><br>";

print "<u>Starting Tracking Code Widening For Customer Worth:</u><br>";
$trackCode = $trackCodeOriginal;
print $trackCode . "<br>";

$counter = 0;

while($trackCode = $cpcObj->widenTrackingCodeForCustomerWorth($trackCode)){
	print $trackCode . "<br>";
	
	$counter++; 
	
	if($counter > 100)
		break;
}

*/













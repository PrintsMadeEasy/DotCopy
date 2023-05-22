<?php


class TransitTimes {


	private $_transitTimesArr = array();
	

	function __construct(){
	
	}
	
	
	// The Arrival hour is in military time 0-24.  If there is no guarantee on the arrival time, then just pass in 0.
	function addShippingMethod($shippingCode, $arrivalHour, $arrivalMinute, $transitDays){
	
		if(empty($arrivalMinute))
			$arrivalMinute = 0;
		if(empty($arrivalHour))
			$arrivalHour = 0;
			
		$transitDays = intval($transitDays);
		
		if(empty($transitDays))
			throw new Exception("Error adding a new shipping method to the TranistTimes object.");
		
		$this->_transitTimesArr["$shippingCode"] = array();
		$this->_transitTimesArr["$shippingCode"]["ArrivalHour"] = $arrivalHour;
		$this->_transitTimesArr["$shippingCode"]["ArrivalMinute"] = $arrivalMinute;
		$this->_transitTimesArr["$shippingCode"]["TransitDays"] = $transitDays;
		
	}
	
	// Takes another TransitTimes object and copies all of the information into this method.
	// If a matching ShippingCode exists on this Object, it will be overwritten.
	function mergeTransitTimesObject(TransitTimes $transitTimesObj){
		
		$shippingCodes = $transitTimesObj->getShippingCodesSet();
		
		foreach ($shippingCodes as $thisShippingCode){
			
			$newArrivalHour = $transitTimesObj->getArrivalHour($thisShippingCode);
			$newArrivalMinute = $transitTimesObj->getArrivalMinute($thisShippingCode);
			$newTransitDays = $transitTimesObj->getTransitDays($thisShippingCode);
			
			$this->addShippingMethod($thisShippingCode, $newArrivalHour, $newArrivalMinute, $newTransitDays);
		}
		
		
	}
	
	// returns an array of shipping methods characters that have been set within addShippingMethod
	function getShippingCodesSet(){
	
		return array_keys($this->_transitTimesArr);

	}
	

	// If the Shpping Code is not set interally (from our XML file)... then it will return whateve you put for the $defaultTransitDays
	// Check the return value to make sure that it isn't NULL.
	function getTransitDays($shippingCode, $defaultTransitDays = null){
		
		if(isset($this->_transitTimesArr["$shippingCode"]) && empty($defaultTransitDays))
			return $this->_transitTimesArr["$shippingCode"]["TransitDays"];
		else
			return $defaultTransitDays;		
	}
	
	// If the Tranist Time has been specifically set... it will return that, otherwise it will return the default value
	// If the Arrival Hour is past 17, or 5PM, it will return a Zero... Zero for Arrival hour means that we don't guarantee the hour of arrival
	function getArrivalHour($shippingCode, $defaultArrivalHour = null){
		$retTransitHour = "";
		if(isset($this->_transitTimesArr["$shippingCode"]))
			$retTransitHour = $this->_transitTimesArr["$shippingCode"]["ArrivalHour"];
		else
			$retTransitHour = $defaultArrivalHour;
		
		if(!empty($retTransitHour)){
			if($retTransitHour > 17)
				$retTransitHour = 0;
		}
		return $retTransitHour;
		
	}
	// If the Arrival Hour is past 17, or 5PM, it will return a blank string,
	function getArrivalMinute($shippingCode, $defaultArrivalMinute = null){

		$ThisArrivalHour = $this->getArrivalHour($shippingCode, 0);
		if(!empty($ThisArrivalHour))
			return $this->_transitTimesArr["$shippingCode"]["ArrivalMinute"];
		else
			return $defaultArrivalMinute;
			
	}
	



}
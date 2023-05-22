<?php

class AdWordsCampaign {

	public $id;
	public $budgetAmount;
	public $budgetPeriod;
	public $contentTargeting;
	public $endDay;
	public $startDay;
	public $name;
	public $status;
	
	public $scheduleObj;
	public $networkTargetingObj;
	public $geoTargetingObj;
	public $languageTargetingObj;
	public $conversionOptimizerSettingsObj;
	public $budgetOptimizerSettingsObj;
	
	function __construct() {
	
	}
	
	
	public function getCampaignXml(){
		
		$retXml = "";
		$retXml .= "<budgetAmount>" . AdWordsBase::convertLocalPriceToGoogleMicroUnits($this->budgetAmount) . "</budgetAmount>\n";
		$retXml .= "<budgetPeriod>$this->budgetPeriod</budgetPeriod>\n";
		$retXml .= "<contentTargeting>$this->contentTargeting</contentTargeting>\n";
		$retXml .= "<endDay>$this->endDay</endDay>\n";
		$retXml .= "<startDay>$this->startDay</startDay>\n";
		$retXml .= "<id>$this->id</id>\n";
		$retXml .= "<name>" . AdWordsBase::encodeTextForXml($this->name) . "</name>\n";
		$retXml .= "<status>$this->status</status>\n";
		$retXml .= "<budgetOptimizerSettings>
						<bidCeiling>" . AdWordsBase::convertLocalPriceToGoogleMicroUnits($this->budgetOptimizerSettingsObj->bidCeiling) . "</bidCeiling>
						<enabled>" . $this->budgetOptimizerSettingsObj->enabled . "</enabled>
						<takeOnOptimizedBids>" . $this->budgetOptimizerSettingsObj->takeOnOptimizedBids . "</takeOnOptimizedBids>
					</budgetOptimizerSettings>\n";
		$retXml .= "<conversionOptimizerSettings>
						<enabled>" . $this->conversionOptimizerSettingsObj->enabled . "</enabled>
						<maxCpaBidForAllAdGroups>" . AdWordsBase::convertLocalPriceToGoogleMicroUnits($this->conversionOptimizerSettingsObj->maxCpaBidForAllAdGroups) . "</maxCpaBidForAllAdGroups>
					</conversionOptimizerSettings>\n";
		
		$retXml .= "<languageTargeting>\n";
		foreach($this->languageTargetingObj->languagesArr as $thisLanguageCode)
			$retXml .= "<languages>" . $thisLanguageCode . "</languages>\n";
		$retXml .= "</languageTargeting>\n";
		
		$retXml .= "<networkTargeting>\n";
		foreach($this->networkTargetingObj->networkTypesArr as $thisNetworkType)
			$retXml .= "<networkTypes>" . $thisNetworkType . "</networkTypes>\n";
		$retXml .= "</networkTargeting>\n";
		

		$retXml .= $this->scheduleObj->getScheduleXml();
		
		$retXml .= $this->geoTargetingObj->getGeoTargetXml();
		
		return $retXml;
		
	}
}


class AdWordsCampaignSchedule {
	public $status;
	public $schedulingIntervalArr = array();
	
	public function getScheduleXml(){
		
		$retXml = "";
		$retXml .= "<schedule>\n";
		$retXml .= "	<status>" . $this->status . "</status>\n";
		foreach($this->schedulingIntervalArr as $thisScheduleObj){
			$retXml .= "<intervals>";
			$retXml .= "	<day>" . $thisScheduleObj->day . "</day>\n";
			$retXml .= "	<endHour>" . $thisScheduleObj->endHour . "</endHour>\n";
			$retXml .= "	<endMinute>" . $thisScheduleObj->endMinute . "</endMinute>\n";
			$retXml .= "	<multiplier>" . $thisScheduleObj->multiplier . "</multiplier>\n";
			$retXml .= "	<startHour>" . $thisScheduleObj->startHour . "</startHour>\n";
			$retXml .= "	<startMinute>" . $thisScheduleObj->startMinute . "</startMinute>\n";
			$retXml .= "</intervals>";
		}
		$retXml .= "</schedule>\n";
		
		return $retXml;
	}
		
}

class AdWordsCampaignNetworkTargeting {
	public $networkTypesArr = array();	
}


class AdWordsCampaignLanguageTargeting {
	public $languagesArr = array();
}

class AdWordsCampaignConversionOptimizerSettings {
	public $enabled;
	public $maxCpaBidForAllAdGroups;
}



class AdWordsCampaignBudgetOptimizerSettings {
	public $bidCeiling;
	public $enabled;
	public $takeOnOptimizedBids;
}











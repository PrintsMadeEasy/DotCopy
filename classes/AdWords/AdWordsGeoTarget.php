<?php

class AdWordsGeoTarget {

	public $cityTargetsObj;
	public $countryTargetsObj;
	public $metroTargetsObj;
	public $proximityTargetsObj;
	public $regionTargetsObj;
	public $targetAll;
	
	public function getGeoTargetXml(){
		
		$retXml = "";
		$retXml .= "<geoTargeting>\n";
		$retXml .= "	<targetAll>" . $this->targetAll . "</targetAll>\n";
		

		$retXml .= "	<cityTargets>\n";
		foreach($this->cityTargetsObj->citiesArr as $x)
			$retXml .= "	<cities>" . AdWordsBase::encodeTextForXml($x) . "</cities>\n";
		foreach($this->cityTargetsObj->excludedCitiesArr as $x)
			$retXml .= "	<excludedCities>" . AdWordsBase::encodeTextForXml($x) . "</excludedCities>\n";
		$retXml .= "	</cityTargets>\n";
	
		
		$retXml .= "	<countryTargets>\n";
		foreach($this->countryTargetsObj->countriesArr as $x)
			$retXml .= "	<countries>" . AdWordsBase::encodeTextForXml($x) . "</countries>\n";
		foreach($this->countryTargetsObj->excludedCountriesArr as $x)
			$retXml .= "	<excludedCountries>" . AdWordsBase::encodeTextForXml($x) . "</excludedCountries>\n";
		$retXml .= "	</countryTargets>\n";
		
		
		$retXml .= "	<metroTargets>\n";
		foreach($this->metroTargetsObj->metrosArr as $x)
			$retXml .= "	<metros>" . AdWordsBase::encodeTextForXml($x) . "</metros>\n";
		foreach($this->metroTargetsObj->excludedMetrosArr as $x)
			$retXml .= "	<excludedMetros>" . AdWordsBase::encodeTextForXml($x) . "</excludedMetros>\n";
		$retXml .= "	</metroTargets>\n";
	
		
		$retXml .= "	<regionTargets>\n";
		foreach($this->regionTargetsObj->regionsArr as $x)
			$retXml .= "	<regions>" . AdWordsBase::encodeTextForXml($x) . "</regions>\n";
		foreach($this->regionTargetsObj->excludedRegionsArr as $x)
			$retXml .= "	<excludedRegions>" . AdWordsBase::encodeTextForXml($x) . "</excludedRegions>\n";
		$retXml .= "	</regionTargets>\n";
	
		
		$retXml .= "	<proximityTargets>\n";
		foreach($this->proximityTargetsObj->circlesArr as $circleObj){
			$retXml .= "<circles>\n";
			$retXml .= "	<latitudeMicroDegrees>$circleObj->latitudeMicroDegrees</latitudeMicroDegrees>\n";
			$retXml .= "	<longitudeMicroDegrees>$circleObj->longitudeMicroDegrees</longitudeMicroDegrees>\n";
			$retXml .= "	<radiusMeters>$circleObj->radiusMeters</radiusMeters>\n";
			$retXml .= "</circles>\n";
		}
		$retXml .= "	</proximityTargets>\n";
	
		
		$retXml .= "</geoTargeting>\n";
		
		return $retXml;
	}
}

class AdWordsGeoTargetCities {
	public $citiesArr = array();
	public $excludedCitiesArr = array();
}

class AdWordsGeoTargetCountries {
	public $countriesArr = array();
	public $excludedCountriesArr = array();
}

class AdWordsGeoTargetMetros {
	public $metrosArr = array();
	public $excludedMetrosArr = array();
}

class AdWordsGeoTargetRegions {
	public $regionsArr = array();
	public $excludedRegionsArr = array();
}


class AdWordsGeoTargetProximities {
	public $circlesArr = array();
}

class AdWordsCircles {
	public $latitudeMicroDegrees;
	public $longitudeMicroDegrees;
	public $radiusMeters;
}






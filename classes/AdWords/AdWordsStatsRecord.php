<?php

// This is a container class for holding Stats from AdGroups or Campaigns
class AdWordsStatsRecord {

	public $id;
	public $averagePosition;
	public $clicks;
	public $conversionRate;
	public $conversions;
	public $cost;
	public $impressions;
	
	function __construct(){
		$this->clicks = null;
		$this->impressions = null;
		$this->cost = null;
	}
	
	
	
	// Some higher level processing of the raw data.

	function getClickThroughRate(){
		
		if($this->clicks === null || $this->impressions === null)
			throw new Exception("You can not call getClickThroughRate if the object has not been set.");
			
		if($this->impressions == 0)
			return null;
			
		return round($this->clicks / $this->impressions, 3);
	}
	
	function getCostPerClick(){
		
		if($this->cost === null || $this->clicks === null)
			throw new Exception("You can not call getCostPerClick if the object has not been set.");
			
		if($this->clicks == 0)
			return null;
			
		return round($this->cost / $this->clicks, 2);
	}
	function getAverageCPM(){
		
		if($this->cost === null || $this->clicks === null)
			throw new Exception("You can not call getAverageCPM if the object has not been set.");
			
		if($this->clicks == 0)
			return null;
			
		return round($this->cost / $this->impressions, 2);
	}

}


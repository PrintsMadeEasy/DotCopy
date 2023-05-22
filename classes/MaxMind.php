<?php

// Provides Location, ISP, Proxy, and other Fraud indication based upon IP addresses
// Run the script server_updateMaxmindData.php to import/update their information (from CSV file) into our database.
class MaxMind {
	
	private $dbCmd;
	
	private $locationID;
	
	private $country;
	private $region;
	private $city;
	private $postalCode;
	private $latitude;
	private $longitude;
	private $metroCode;
	private $areaCode;	
	
	
	function __construct() {
		
		$this->dbCmd = new DbCmd();
	}
	
	// Will return NULL if the IP address does come across a match.
	static function getLocationIdFromIPaddress($ipAddress){
		
		self::validateIPaddress($ipAddress);
		
		$dbCmd = new DbCmd();

		// Due to the nature of SQL... we can't use a between clause on 2 separate indexed columns and expect to get quick results.
		// We had to make sure that Start IP's were inserted into the database in sequence.
		$dbCmd->Query("SELECT Counter FROM maxmindlocationids WHERE StartIP > ".self::getNumericIP($ipAddress)." ORDER BY StartIP ASC LIMIT 1");
		if($dbCmd->GetNumRows() == 0)
			return null;
			
		$couterLocation = $dbCmd->GetValue();
		
		// We know the record we are looking for is possible one record lower.
		$couterLocation--;
		
		// In case we are on the first row.
		if($couterLocation == 0)
			return null;
		
		$dbCmd->Query("SELECT LocationID, EndIP FROM maxmindlocationids WHERE Counter=$couterLocation");
		if($dbCmd->GetNumRows() == 0)
			return null;
		
		$row = $dbCmd->GetRow();

		// We need to make sure that there is not a "hole" between IP ranges.  So we have to check the END IP of this record.
		if($row["EndIP"] < self::getNumericIP($ipAddress))
			return null;
		
		return $row["LocationID"];
	}
	
	// Will return NULL if the IP address does come across a match.
	static function getIspFromIPaddress($ipAddress){
		
		self::validateIPaddress($ipAddress);
			
		$dbCmd = new DbCmd();
		
		// Due to the nature of SQL... we can't use a between clause on 2 separate indexed columns and expect to get quick results.
		// We had to make sure that Start IP's were inserted into the database in sequence.
		$dbCmd->Query("SELECT Counter FROM maxmindisps WHERE StartIP > ".self::getNumericIP($ipAddress)." ORDER BY StartIP ASC LIMIT 1");
		if($dbCmd->GetNumRows() == 0)
			return null;
			
		$couterLocation = $dbCmd->GetValue();
		
		// We know the record we are looking for is possible one record lower.
		$couterLocation--;
		
		// In case we are on the first row.
		if($couterLocation == 0)
			return null;
		
		$dbCmd->Query("SELECT ISPname, EndIP FROM maxmindisps WHERE Counter=$couterLocation");
		if($dbCmd->GetNumRows() == 0)
			return null;
		
		$row = $dbCmd->GetRow();

		// We need to make sure that there is not a "hole" between IP ranges.  So we have to check the END IP of this record.
		if($row["EndIP"] < self::getNumericIP($ipAddress))
			return null;
		
		return $row["ISPname"];
	}
	
	static function getNumericIP($ipAddress){
		
		self::validateIPaddress($ipAddress);
		
		$ipParts = split("\.", $ipAddress);
		
		return (16777216 * $ipParts[0]) + (65536 * $ipParts[1]) + (256 * $ipParts[2]) + $ipParts[3];
	}
	
	static function validateIPaddress($ipAddress){
		if(!preg_match("/^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/", $ipAddress))
			throw new Exception("The IP address is invalid.");
	}
	
	// Returns True there the IP address has location details associated with it, otherwise returns false
	// It will return TRUE if there is a location ID... but the Location ID doesn't have any details in Location Row.
	// In this case, all of the fields will return NULL on subsequent method calls.
	function loadIPaddressForLocationDetails($ipAddress){
		
		// Wipe out previous data.
		$this->country = null;
		$this->region = null;
		$this->city = null;
		$this->postalCode = null;
		$this->latitude = null;
		$this->latitude = null;
		$this->longitude = null;
		$this->metroCode = null;
		$this->areaCode = null;
		
		
		$this->locationID = self::getLocationIdFromIPaddress($ipAddress);
		
		
		if($this->locationID){
			
			$this->dbCmd->Query("SELECT * FROM maxmindlocationdetails WHERE LocationID=" . intval($this->locationID));
			
			// If there is no Location records... then return be
			if($this->dbCmd->GetNumRows() == 0)
				return true;
			
			$row = $this->dbCmd->GetRow();
			
			$this->country = $row["Country"];
			$this->region = $row["Region"];
			$this->city = $row["City"];
			$this->postalCode = $row["PostalCode"];
			$this->latitude = $row["Latitude"];
			$this->longitude = $row["Longitude"];
			$this->metroCode = $row["MetroCode"];
			$this->areaCode = $row["AreaCode"];
			
			return true;
		}
		else{
			return false;
		}
		
	}
	
	function checkIfOpenProxy(){
		$this->ensureLocationIdHasBeenLoaded();
		if($this->country == "A1")
			return true;
		else
			return false;
	}
	function checkIfSateliteConnection(){
		$this->ensureLocationIdHasBeenLoaded();
		if($this->country == "A2")
			return true;
		else
			return false;
	}
	
	function getCountry(){
		$this->ensureLocationIdHasBeenLoaded();
		return $this->country;
	}
	function getRegion(){
		$this->ensureLocationIdHasBeenLoaded();
		return $this->region;
	}
	function getCity(){
		$this->ensureLocationIdHasBeenLoaded();
		return $this->city;
	}
	function getPostalCode(){
		$this->ensureLocationIdHasBeenLoaded();
		return $this->postalCode;
	}
	function getLatitude(){
		$this->ensureLocationIdHasBeenLoaded();
		return $this->latitude;
	}
	function getLongitude(){
		$this->ensureLocationIdHasBeenLoaded();
		return $this->longitude;
	}
	function getMetroCode(){
		$this->ensureLocationIdHasBeenLoaded();
		return $this->metroCode;
	}
	function getAreaCode(){
		$this->ensureLocationIdHasBeenLoaded();
		return $this->areaCode;
	}

	
	

	private function ensureLocationIdHasBeenLoaded(){
		if(!$this->locationID)
			throw new Exception("The Location ID of the IP address must be loaded (and it must be found) before you can call this method.  Are you sure that the IP address has a location ID in the MaxMind database?");
	}
	

	
}

?>

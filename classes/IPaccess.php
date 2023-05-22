<?
class IPaccess {

	private $_dbCmd;

	function __construct(DbCmd $dbCmd){

		$this->_dbCmd = $dbCmd;
	}
	
	
	// This method is not for checking security.  It is mainly used to tell if the user is a member in the system.
	// Just checking if they belong to the MEMBER group may not be enough because they could use a LOGIN link or something.
	// This is useuful to make sure an admin taking an order over the phone isn't giving away sales commissions.
	static function checkIfUserIpHasSomeAccess(){

		$dbCmd = new DbCmd();
		$dbCmd->Query("SELECT COUNT(*) FROM ipaccess WHERE IPaddress = '".DbCmd::EscapeSQL(WebUtil::getRemoteAddressIp())."'");
		$ipResult = $dbCmd->GetValue();
		
		if(empty($ipResult))
			return false;
		else
			return true;
	}
	
	
	// returns True is the user should be granted Access, false otherwise.
	// Pass in the UserID and the full IP Address.
	// Leave IP address blank to let the system figure it out.
	// We are not going to Authenticate User Domain permissions on this method for performance reasons...
	// ... it is expected that you verify them before calling this method.
	function isPermitted($userID, $ipAddress = null){
	
		if(empty($ipAddress))
			 $ipAddress = WebUtil::getRemoteAddressIp();

		$userID = intval($userID);
		
		$this->_verifyIPaddressFormat($ipAddress);

		$subNetsAvailableArr = $this->getSubnetsAvailableForUser($userID);

		if(in_array($this->_getSubnetFromIP($ipAddress), $subNetsAvailableArr))
			return true;
		
		$ipsAvailableArr = $this->getIPaddressesAvailableForUser($userID);
		
		// As long as the User has at least 1 IP address enabled for their account.  List some alternatives that are whitelisted.
		// We need to do this because of the load balancer switching IP addresses on people within the DOT Building.
		if(!empty($ipsAvailableArr)){
			if(in_array($ipAddress, array("74.62.46.166", "205.214.232.19")))
				return true;
		}
		
		if(in_array($ipAddress, $ipsAvailableArr))
			return true;
		
		return false;
		
	}
	
	
	// You should add the entry, regardless if they are permitted or not.
	// Pass in a UserID, the method will figure out the IP address itself.
	function addLogEntry($userID, $ipAddress = null){
		
		if(empty($ipAddress))
			 $ipAddress = WebUtil::getRemoteAddressIp();
			 
		// Polling the server for chat can really rack up the access counts.
		$scriptsToIgnore = array("api_chat_for_admin.js", "api_chat.php");
		foreach($scriptsToIgnore as $thisScriptToIgnore){
			if(preg_match("/".preg_quote($thisScriptToIgnore)."/", $_SERVER['SCRIPT_NAME']))
				return;
		}
	
		$userID = intval($userID);
		
		$this->_verifyIPaddressFormat($ipAddress);
	
		$insertArr["UserID"] = $userID;
		$insertArr["IPaddress"] = $ipAddress;
		$insertArr["IPsubnet"] = $this->_getSubnetFromIP($ipAddress);

		$this->_dbCmd->InsertQuery("iplogger", $insertArr); 
	
	}
	

	// Pass in a Subnet for which the user is allowed access
	// (basically the IP Address minus the last 3 numbers (and period).
	// This is security becomes weaker when you add a Subnet permission for a user
	// It is more secure to add a specific IP address.
	function addSubnetForUser($userID, $subNet){

		$this->_verifyIPaddressFormat($subNet);
		
		$this->verifyUserDomainPermission($userID);
		
		$insertArr["UserID"] = $userID;
		$insertArr["IPsubnet"] = $subNet;
		$insertArr["DateGranted"] = time();
	
		$this->_dbCmd->InsertQuery("ipaccess", $insertArr); 
	}
	
	// This is more secure than adding a Subnet for a user.
	// Unfortunately now days everyone has Dynamic IP addresses, which makes approach ineffective a lot of the time.
	function addIPaddressForUser($userID, $ipAddress){
	
		$this->_verifyIPaddressFormat($ipAddress);
		
		$this->verifyUserDomainPermission($userID);
		
		$insertArr["UserID"] = $userID;
		$insertArr["IPaddress"] = $ipAddress;
		$insertArr["DateGranted"] = time();
	
		$this->_dbCmd->InsertQuery("ipaccess", $insertArr); 
	}
	
	
	// Returns an array of Subnets for which the user has access to.
	function getSubnetsAvailableForUser($userID){
		
		$userID = intval($userID);
		
		$retArr = array();
		$this->_dbCmd->Query( "SELECT DISTINCT IPsubnet FROM ipaccess WHERE UserID = $userID");

		while($row = $this->_dbCmd->GetRow()){
			if(!empty($row["IPsubnet"]))
				$retArr[] = $row["IPsubnet"];
		}
		
		return $retArr;
	}
	
	// Returns an array of Subnets for which the user has access to.
	function getIPaddressesAvailableForUser($userID){
		
		$userID = intval($userID);
		
		$retArr = array();

		$this->_dbCmd->Query( "SELECT DISTINCT IPaddress FROM ipaccess WHERE UserID = $userID");
	
		while($row = $this->_dbCmd->GetRow()){
			if(!empty($row["IPaddress"]))
				$retArr[] = $row["IPaddress"];
		}
		
		return $retArr;
	}
	
	
	// Will not permit the user to have access through the subnet anymore (althrouh an IP Address permission may still permit)
	// Will not complain if you call this method and the subnet does not exist.
	function removeSubnetFromUser($userID, $subNet){
	
		$this->_verifyIPaddressFormat($subNet);
		
		$this->verifyUserDomainPermission($userID);

		$this->_dbCmd->Query( "DELETE FROM ipaccess WHERE UserID = $userID AND IPsubnet = '$subNet'");
	}
	
	// Will not permit the user to have access through the IP Address anymore (althrouh a subnet permission may still permit)
	// Will not complain if you call this method and the IP Address does not exist.
	function removeIPaddressFromUser($userID, $ipaddress){
	
		$this->_verifyIPaddressFormat($ipaddress);
		
		$this->verifyUserDomainPermission($userID);

		$this->_dbCmd->Query( "DELETE FROM ipaccess WHERE UserID = $userID AND IPaddress = '$ipaddress'");

	}
	
	
	// Returns a unique list of UserID's that have tried to make connections within the date range.
	function getUserIDsWithinPeriod($startTimeStamp, $endTimeStamp){
		
		$this->_verifyDateRange($startTimeStamp, $endTimeStamp);
		
		$domainObj = Domain::singleton();
		$retArr = array();

		$this->_dbCmd->Query( "SELECT DISTINCT UserID FROM iplogger USE INDEX (iplogger_AccessDate) INNER JOIN users ON users.ID = iplogger.UserID 
					WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." AND 
					AccessDate BETWEEN " . $this->_mysqlTimeStamp($startTimeStamp) . " AND " . $this->_mysqlTimeStamp($endTimeStamp) . " 
					ORDER BY users.Name ASC");
		while($uid = $this->_dbCmd->GetValue())
			$retArr[] = $uid;
		
		return $retArr;

	}
	
	
	// Pass in a user ID and a time range.
	// The method will return a unique array of SubNet Addresses Attempted by the user.
	function getSubnetLogsFromUser($userID, $startTimeStamp, $endTimeStamp){
		
		$this->_verifyDateRange($startTimeStamp, $endTimeStamp);
		
		$this->verifyUserDomainPermission($userID);
		

		$retArr = array();

		$this->_dbCmd->Query( "SELECT DISTINCT IPsubnet FROM iplogger USE INDEX (iplogger_AccessDate) WHERE UserID = $userID AND 
					AccessDate BETWEEN " . $this->_mysqlTimeStamp($startTimeStamp) . " AND " . $this->_mysqlTimeStamp($endTimeStamp));
		while($sub = $this->_dbCmd->GetValue())
			$retArr[] = $sub;
		
		return $retArr;
	}
	
	
	// Returns a count, for the number of times that the User has accessed the site with the given Subnet.
	function getSubnetAccessCount($userID, $subNet, $startTimeStamp, $endTimeStamp){

		$this->_verifyDateRange($startTimeStamp, $endTimeStamp);
		
		$this->verifyUserDomainPermission($userID);
		
		$this->_verifyIPaddressFormat($subNet);

		$this->_dbCmd->Query( "SELECT COUNT(*) FROM iplogger USE INDEX (iplogger_AccessDate) WHERE UserID = $userID AND IPsubnet = '$subNet' AND 
					AccessDate BETWEEN " . $this->_mysqlTimeStamp($startTimeStamp) . " AND " . $this->_mysqlTimeStamp($endTimeStamp));
		
		return $this->_dbCmd->GetValue();
	}
	
	
	
	// Returns a count, for the number of times that the User has accessed the site with the given IP Address.
	function getIPaccessCount($userID, $ipAddress, $startTimeStamp, $endTimeStamp){
	
		$this->_verifyDateRange($startTimeStamp, $endTimeStamp);
		
		$this->verifyUserDomainPermission($userID);
		
		$this->_verifyIPaddressFormat($ipAddress);

		$this->_dbCmd->Query( "SELECT COUNT(*) FROM iplogger USE INDEX (iplogger_AccessDate) WHERE UserID = $userID AND IPaddress = '$ipAddress' AND 
					AccessDate BETWEEN " . $this->_mysqlTimeStamp($startTimeStamp) . " AND " . $this->_mysqlTimeStamp($endTimeStamp));
		
		return $this->_dbCmd->GetValue();
	
	}

	
	// Returns a unique list of IP address belonging to the given user and within the Subnet
	// It does this within the given Time Range.
	function getIPsFromUserSubnet($userID, $subNet, $startTimeStamp, $endTimeStamp){
	
		$this->_verifyDateRange($startTimeStamp, $endTimeStamp);
		
		$this->verifyUserDomainPermission($userID);
		
		$this->_verifyIPaddressFormat($subNet);
	
		$retArr = array();

		$this->_dbCmd->Query( "SELECT DISTINCT IPaddress FROM iplogger USE INDEX (iplogger_AccessDate) WHERE UserID = $userID AND IPsubnet = '$subNet' AND 
					AccessDate BETWEEN " . $this->_mysqlTimeStamp($startTimeStamp) . " AND " . $this->_mysqlTimeStamp($endTimeStamp));
		while($ipAddress = $this->_dbCmd->GetValue())
			$retArr[] = $ipAddress;
		
		return $retArr;
	
	}

	
	// returns the part of an IP address

	// The last Period and 3 numbers will be missing.
	function _getSubnetFromIP($ipAddress){
	
		$this->_verifyIPaddressFormat($ipAddress);
		
		$matches = array();
		if(!preg_match("/^((\d{1,3}\.){3})/", $ipAddress, $matches))
			throw new Exception("Error getting the subnet address");
		$subNet = $matches[1];
		
		// Get rid of the trailing period
		return substr($subNet, 0, -1);
	}
	
	
	// Pass in a Unix Timestamp, will return it in MySQL Format
	private function _mysqlTimeStamp($unixTimeStamp){
		return date("YmdHis", $unixTimeStamp);
	}
	
	private function verifyUserDomainPermission($userIDtoCheck){
		
		$userIDtoCheck = intval($userIDtoCheck);
		
		$domainIDofUser = UserControl::getDomainIDofUser($userIDtoCheck);
		
		$passiveAuthObj = Authenticate::getPassiveAuthObject();
		if(!$passiveAuthObj->CheckIfUserCanViewDomainID($domainIDofUser))
			throw new Exception("Error Adding adjusting user's IP address.");
	}
	
	private function _verifyDateRange($startTimeStamp, $endTimeStamp){
		
		if(!preg_match("/^\d+$/", $startTimeStamp) || !preg_match("/^\d+$/", $endTimeStamp) )
			throw new Exception("Error in method _verifyDateRange.  The timestamps must be numeric.");
			
		if($startTimeStamp > $endTimeStamp)
			throw new Exception("Error in method _verifyDateRange.  The start timestamp can not be greater than the ending.");
	
	}
	
	

	private function _verifyIPaddressFormat($ipAdd){

		if(!preg_match("/^(\d{1,3}\.?){3,4}$/", $ipAdd))
			throw new Exception("Error in Address Format because it is missing some characters.");
	
	}
	
	

}



?>

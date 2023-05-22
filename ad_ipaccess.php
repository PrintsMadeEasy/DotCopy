<?

require_once("library/Boot_Session.php");


// Don't make it look like we are favoring a particular Domain 
Domain::removeTopDomainID();


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

$domainObj = Domain::singleton();



// This is the only page that we don't want to ensure Member security on.  So do a "General Login"
// If an administrator is out of town with a notebook computer... they won't be able to get logged in
// so they will need to type in this URL directly.
$AuthObj = new Authenticate(Authenticate::login_general);
$AuthObj->EnsureGroup("MEMBER");
$UserID = $AuthObj->GetUserID();


// Make sure that the user has permissions to see this page.
if(!$AuthObj->CheckForPermission("IP_ADDRESS_SECURITY")){
	WebUtil::WebmasterError("IP Access was visited by an unauthorized User: $UserID " . UserControl::GetNameByUserID($dbCmd, $UserID));
	throw new Exception("denied from UserID: " . $UserID);
}




$ipAccessObj = new IPaccess($dbCmd);



$action = WebUtil::GetInput("action", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES);
if($action){
	
	WebUtil::checkFormSecurityCode();

	if($action == "AddIPaddress"){

		$userRecord = WebUtil::GetInput("userRecord", FILTER_SANITIZE_INT);
		$ipAddress = WebUtil::GetInput("address", FILTER_SANITIZE_STRING_ONE_LINE);

		$ipAccessObj->addIPaddressForUser($userRecord, $ipAddress);	

		header("Location: " . WebUtil::FilterURL(WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL)));
		exit;
	}
	else if($action == "AddSubNet"){

		$userRecord = WebUtil::GetInput("userRecord", FILTER_SANITIZE_INT);
		$subNetAddress = WebUtil::GetInput("address", FILTER_SANITIZE_STRING_ONE_LINE);

		$ipAccessObj->addSubnetForUser($userRecord, $subNetAddress);	

		header("Location: " . WebUtil::FilterURL(WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL)));
		exit;
	}
	else if($action == "RemoveAccess"){

		$userRecord = WebUtil::GetInput("userRecord", FILTER_SANITIZE_INT);
		$subNetAddress = WebUtil::GetInput("subnet", FILTER_SANITIZE_STRING_ONE_LINE);
		$ipAddress = WebUtil::GetInput("ipaddress", FILTER_SANITIZE_STRING_ONE_LINE);
		
		if(!empty($ipAddress) && !empty($subNetAddress))
			throw new Exception("You can not remove an IP Address and an Subnet at the same time.");
		
		if(!empty($subNetAddress))
			$ipAccessObj->removeSubnetFromUser($userRecord, $subNetAddress);
		else if(!empty($ipAddress))
			$ipAccessObj->removeIPaddressFromUser($userRecord, $ipAddress);
		else
			throw new Exception("You must pass in an IP address or a subnet in order to remove access..");
		

		header("Location: " . WebUtil::FilterURL(WebUtil::GetInput("returnurl", FILTER_SANITIZE_URL)));
		exit;
	}
	else{
		throw new Exception("Undefined Action");
	}
}





$domainObj = Domain::singleton();

$t = new Templatex(".");
$t->set_file("origPage", "ad_ipaccess-template.html");


$t->set_var("HEADER", Widgets::GetHeaderHTML($dbCmd, $AuthObj));
$t->allowVariableToContainBrackets("HEADER");

$t->set_var("FORM_SECURITY_CODE", WebUtil::getFormSecurityCode());



#-- Start Report Date Parameters--#
$message = null;
$ReportPeriodIsDateRange = WebUtil::GetInput( "PeriodType", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES ) == "DATERANGE";
$TimeFrameSel = WebUtil::GetInput( "TimeFrame", FILTER_SANITIZE_WORD_OR_NUMBER_NO_SPACES, "TODAY"  );

$date = getdate();
$startday= WebUtil::GetInput( "DateRangeStartDay", FILTER_SANITIZE_INT, "1" );
$startmonth= WebUtil::GetInput( "DateRangeStartMonth", FILTER_SANITIZE_INT, $date["mon"] );
$startyear= WebUtil::GetInput( "DateRangeStartYear", FILTER_SANITIZE_INT, $date["year"] );
$endday= WebUtil::GetInput( "DateRangeEndDay", FILTER_SANITIZE_INT, $date["mday"] );
$endmonth= WebUtil::GetInput( "DateRangeEndMonth", FILTER_SANITIZE_INT, $date["mon"] );
$endyear= WebUtil::GetInput( "DateRangeEndYear", FILTER_SANITIZE_INT, $date["year"] );
$limitToProductID = WebUtil::GetInput("productlimit", FILTER_SANITIZE_INT);

// Format the dates that we want for MySQL for the date range
if( $ReportPeriodIsDateRange )
{
	$start_timestamp = mktime (0,0,0,$startmonth,$startday,$startyear);
	$end_timestamp = mktime (23,59,59,$endmonth,$endday,$endyear);
	
	if(  $start_timestamp >  $end_timestamp  )	
		$message = "Invalid Date Range Specified - Unable to Generate Report";
}
else
{
	$ReportPeriod = Widgets::GetTimeFrame( $TimeFrameSel );
	$start_timestamp = $ReportPeriod[ "STARTDATE" ];
	$end_timestamp = $ReportPeriod[ "ENDDATE" ];
}

$start_mysql_timestamp = date("YmdHis", $start_timestamp);
$end_mysql_timestamp  = date("YmdHis", $end_timestamp);

// Setup date range selections and and type
$t->set_var( "PERIODTYPETIMEFRAME", $ReportPeriodIsDateRange ? null : "CHECKED" );
$t->set_var( "PERIODISTIMEFRAME", $ReportPeriodIsDateRange ? "false" : "true" );
$t->set_var( "TIMEFRAMESELS", Widgets::BuildTimeFrameSelect( $TimeFrameSel ));
$t->set_var( "PERIODTYPEDATERANGE", $ReportPeriodIsDateRange ? "CHECKED" : null );
$t->set_var( "DATERANGESELS", Widgets::BuildDateRangeSelect( $start_timestamp, $end_timestamp, "D" ));
$t->set_var( "MESSAGE", $message );
$t->set_var( "STARTTIMESTAMP", $start_timestamp );
$t->set_var( "ENDTIMESTAMP", $end_timestamp );

$t->allowVariableToContainBrackets("TIMEFRAMESELS");
$t->allowVariableToContainBrackets("DATERANGESELS");
$t->allowVariableToContainBrackets("PERIODTYPEDATERANGE");

if( $message )
{
	//Error occurred - discontinue report generation
	$t->discard_block("origPage","EmptyAccessResults");
	
	$t->pparse("OUT","origPage");
	exit;
}


#-- End Report Date Parameters--#



$t->set_var("RETURN_URL_ENCODED", urlencode($_SERVER['PHP_SELF'] . "?" . $_SERVER['QUERY_STRING']) );





// Build a list of all Grants ....

$t->set_block("origPage","grantsBL","grantsBLout");

$counter = 0;

// Collect a list of Users and their permissions.
$dbCmd2->Query("SELECT *, UNIX_TIMESTAMP(DateGranted) AS DateGranted 
				FROM ipaccess INNER JOIN users ON users.ID = ipaccess.UserID 
				WHERE ".DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs())." 
				ORDER BY users.Name ASC");

// Hold results in a temporary array so that we can skip values in the DB loop if we have to remove.
$resultsArr = array();
while($row = $dbCmd2->GetRow())
	$resultsArr[] = $row;


foreach($resultsArr as $row){

	$ipAddress = $row["IPaddress"];
	$subnet = $row["IPsubnet"];
	$dateGranted = $row["DateGranted"];
	
	
	// The administrator can grant permissions that stick for 1 day.  After that the user must access the site at least once every (expiration time).
	$expirationOfPermission = 60 * 60 * 24 * 5; // 5 Days
	$adminPermissionLeasePeriod = 60 * 60 * 24 * 1; // 1 Day
	

	// If the IP Address is emtpy, then it is a Subnet permission.
	if(empty($ipAddress)){
		
		$subNetCountIn2Weeks = $ipAccessObj->getSubnetAccessCount($row["UserID"], $subnet, time() - $expirationOfPermission, time());
		
		if(time() - $dateGranted > $adminPermissionLeasePeriod && $subNetCountIn2Weeks == 0){
			$ipAccessObj->removeSubnetFromUser($row["UserID"], $subnet);
			continue;
		}	
	}
	else{
		
		$ipCountIn2Weeks = $ipAccessObj->getIPaccessCount($row["UserID"], $ipAddress, time() - $expirationOfPermission, time());
		
		if(time() - $dateGranted > $adminPermissionLeasePeriod && $ipCountIn2Weeks == 0){
			$ipAccessObj->removeIPaddressFromUser($row["UserID"], $ipAddress);
			continue;
		}
	}
	
	// Only show the Domain Logo if the user has more than 1 domain selected.
	if(sizeof($domainObj->getSelectedDomainIDs()) > 1){
		$domainLogoObj = new DomainLogos(UserControl::getDomainIDofUser($row["UserID"]));
		$domainLogoImg = "<img alt='".Domain::getDomainKeyFromID(UserControl::getDomainIDofUser($row["UserID"]))."'   src='./domain_logos/$domainLogoObj->verySmallIcon' border='0'>";
	}
	else{
		$domainLogoImg = "";
	}

	$t->set_var("GRANT_USER_IP", $ipAddress);
	$t->set_var("GRANT_USER_SUBNET", $subnet);
	$t->set_var("USERID", $row["UserID"]);


	$t->set_var("GRANT_USER_NAME", $domainLogoImg . " " . WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, $row["UserID"])));
	$t->allowVariableToContainBrackets("GRANT_USER_NAME");
	
	$t->parse("grantsBLout","grantsBL", true);
	
	$counter++;
}


if(empty($counter)){
	$t->set_block("origPage","EmptyPermissionsBL","EmptyPermissionsBLout");
	$t->set_var("EmptyPermissionsBLout", "No user permissions have been set yet.");
}









// Get a list of all UserIDs within the period that attempted to connect to the secure areas on the server.
$userIDaccessArr = $ipAccessObj->getUserIDsWithinPeriod($start_timestamp, $end_timestamp);



$t->set_block("origPage","IPlogBL","IPlogBLout");

$franchiseOwnersUserIDArr = $AuthObj->GetUserIDsInGroup(array("FRANCHISE_OWNER"));
$superAdmingIDs = $AuthObj->GetUserIDsInGroup(array("SUPERADMIN"));

foreach($userIDaccessArr as $thisUserID){

	// Franchise owners do not require IP Authentication... so skip over them in the list.
	// We still want Super Admins to see the counts though.
	if(in_array($thisUserID, $franchiseOwnersUserIDArr)){
		if(!in_array($UserID, $superAdmingIDs))
			continue;
	}

	// Get a List of subnets that this user connected From.
	$subnetArr = $ipAccessObj->getSubnetLogsFromUser($thisUserID, $start_timestamp, $end_timestamp);
	
	foreach($subnetArr as $thisSubnet){
	
		// Get a List of IP Addresses within the Subnet that the User Connected with.
		$ipAddressArr = $ipAccessObj->getIPsFromUserSubnet($thisUserID, $thisSubnet, $start_timestamp, $end_timestamp);
		
		// We should always have an IP address along with the Subnet.
		if(sizeof($ipAddressArr) == 0)
			throw new Exception("Error getting a list of IP Addresss from the subet: " . $thisSubnet);
		

		
		foreach($ipAddressArr as $thisIPaddress){
		

			$t->set_var("USER_COUNT", $ipAccessObj->getIPaccessCount($thisUserID, $thisIPaddress, $start_timestamp, $end_timestamp));
			
			$t->set_var("USER_IP", $thisIPaddress);
			
			// Only show the Domain Logo if the user has more than 1 domain selected.
			if(sizeof($domainObj->getSelectedDomainIDs()) > 1){
				$domainLogoObj = new DomainLogos(UserControl::getDomainIDofUser($thisUserID));
				$domainLogoImg = "<img alt='".Domain::getDomainKeyFromID(UserControl::getDomainIDofUser($thisUserID))."'   src='./domain_logos/$domainLogoObj->verySmallIcon' border='0'>";
			}
			else{
				$domainLogoImg = "";
			}
					
			
			$t->set_var("USER_NAME", $domainLogoImg . " " . WebUtil::htmlOutput(UserControl::GetNameByUserID($dbCmd, $thisUserID)));
			$t->allowVariableToContainBrackets("USER_NAME");
			
				
			// Show extra commands if the user is not validated.
			if($ipAccessObj->isPermitted($thisUserID, $thisIPaddress)){
				
				$t->set_var("ADD_COMMANDS", "");
				$t->set_var("ROWCOLOR", "#EEEEEE");
			}
			else{
			
				$addCommands = "<a class='blueredlink' href='javascript:AddIpAddress(\"$thisIPaddress\", \"$thisUserID\")'>Add IP Address</a>&nbsp;&nbsp;";
				$addCommands .= "<a class='blueredlink' href='javascript:AddSubnet(\"$thisSubnet\", \"$thisUserID\")'>Add Subnet</a>";
			
				$t->set_var("ADD_COMMANDS", $addCommands);
				$t->allowVariableToContainBrackets("ADD_COMMANDS");
				
				$t->set_var("ROWCOLOR", "#FFEEEE");
			}
		
		
			$t->parse("IPlogBLout","IPlogBL",true);
		
		}
	
	
	}
	

}

if(empty($userIDaccessArr)){
	$t->set_block("origPage","EmptyAccessResults","EmptyAccessResultsout");
	$t->set_var("EmptyAccessResultsout", "No activity during this period.");
}









$t->pparse("OUT","origPage");



?>
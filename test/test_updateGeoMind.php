<?

require_once("library/Boot_Session.php");


set_time_limit(5000);


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

/*
$dbCmd->Query("SELECT ID, IPaddress FROM bannerlog WHERE Date > '" . DbCmd::FormatDBDateTime(mktime(0, 0, 0, 8, 1, 2008)) . "' ");
while($row = $dbCmd->GetRow()){
	
	$bannerLogID = $row["ID"];
	$bannerLogIpAddress= $row["IPaddress"];
	
	$updateRow = array();
	$updateRow["LocationID"] = MaxMind::getLocationIdFromIPaddress($bannerLogIpAddress);
	$updateRow["ISPname"] = MaxMind::getIspFromIPaddress($bannerLogIpAddress);
	
	
	$dbCmd2->UpdateQuery("bannerlog", $updateRow, "ID=$bannerLogID");
	print $bannerLogID . "<br>\n";
	flush();

}


print "<hr>About to start orders<hr>";
$orderFixRow = array();
$orderFixRow["LocationID"] = null;
$orderFixRow["ISPname"] = null;
$dbCmd2->UpdateQuery("orders", $orderFixRow, "DateOrdered < '" . DbCmd::FormatDBDateTime(mktime(0, 0, 0, 8, 1, 2008)) . "'");

$counter = 0;
$dbCmd->Query("SELECT ID, IPaddress FROM orders WHERE DateOrdered >= '" . DbCmd::FormatDBDateTime(mktime(0, 0, 0, 8, 1, 2008)) . "' ");
while($row = $dbCmd->GetRow()){
	
	$ordersID = $row["ID"];
	$OrderIPAddress= $row["IPaddress"];
	
	$updateRow = array();
	$updateRow["LocationID"] = MaxMind::getLocationIdFromIPaddress($OrderIPAddress);
	$updateRow["ISPname"] = MaxMind::getIspFromIPaddress($OrderIPAddress);
	
	
	$dbCmd2->UpdateQuery("orders", $updateRow, "ID=$ordersID");
	
	$counter++;
	if($counter > 500){
		print $ordersID . "<br>\n";
		$counter = 0;
		flush();
	}
}
*/
print "<hr>About to start visitors<hr>";


$counter = 0;
$dbCmd->Query("SELECT ID, IPaddress FROM visitorsessiondetails WHERE DateStarted > '" . DbCmd::FormatDBDateTime(mktime(0, 0, 0, 8, 1, 2008)) . "' AND LocationID IS NULL");
while($row = $dbCmd->GetRow()){
	
	$visitorsID = $row["ID"];
	$visitorIPaddress= $row["IPaddress"];
	
	$updateRow = array();
	$updateRow["LocationID"] = MaxMind::getLocationIdFromIPaddress($visitorIPaddress);
	$updateRow["ISPname"] = MaxMind::getIspFromIPaddress($visitorIPaddress);
	
	
	$dbCmd2->UpdateQuery("visitorsessiondetails", $updateRow, "ID=$visitorsID");
	
	$counter++;
	if($counter > 500){
		print $visitorsID . "<br>\n";
		$counter = 0;
		flush();
	}
	
}



<?

require_once("library/Boot_Session.php");



$domainObj = Domain::singleton();


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();

//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);

$UserID = $AuthObj->GetUserID();



if(!$AuthObj->CheckForPermission("MARKETING_REPORT"))
	throw new Exception("Permission Denied");


// Make this script be able to run for a while
set_time_limit(1000);

$t = new Templatex(".");


$t->set_file("origPage", "ad_banner_abuse-template.html");


$start_timestamp = WebUtil::GetInput("starttime", FILTER_SANITIZE_INT);
$end_timestamp = WebUtil::GetInput("endtime", FILTER_SANITIZE_INT);

$BannerName = WebUtil::GetInput("bannername", FILTER_SANITIZE_STRING_ONE_LINE);

$t->set_var("BANNER_NAME", WebUtil::htmlOutput($BannerName));
$t->set_var("BANNER_NAME_ENCODED", urlencode($BannerName));

$t->set_var("START_DATE", date("F j, Y", $start_timestamp));
$t->set_var("END_DATE", date("F j, Y", $end_timestamp));
$t->set_var("STARTTIMESTAMP", $start_timestamp);
$t->set_var("ENDTIMESTAMP", $end_timestamp);


$EmptyList = true;


$t->set_block("origPage","AbuseBL","AbuseBLout");



$start_mysql_timestamp = date("YmdHis", $start_timestamp);
$end_mysql_timestamp  = date("YmdHis", $end_timestamp);


$dbCmd->Query("SELECT DISTINCT IPaddress FROM bannerlog WHERE Name=\"" . DbCmd::EscapeSQL($BannerName) . "\"
	AND Date BETWEEN $start_mysql_timestamp AND $end_mysql_timestamp AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));




$ipBannerLogArr = array();
$ipIndexCounter = 0;

$counter =0;
	
while($thisIPaddy = $dbCmd->GetValue()){
	
	$dbCmd2->Query("SELECT COUNT(*) FROM bannerlog WHERE IPaddress='".DbCmd::EscapeSQL($thisIPaddy)."'  AND Name=\"" . DbCmd::EscapeSQL($BannerName) . "\"
		AND Date BETWEEN $start_mysql_timestamp AND $end_mysql_timestamp AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));
	$clickNumber = $dbCmd2->GetValue();
	
	
	
	$dbCmd2->Query("SELECT COUNT(*) FROM orders WHERE IPaddress='".DbCmd::EscapeSQL($thisIPaddy)."'  AND Referral=\"" . DbCmd::EscapeSQL($BannerName) . "\"
		AND DateOrdered BETWEEN $start_mysql_timestamp AND $end_mysql_timestamp AND " . DbHelper::getOrClauseFromArray("DomainID", $domainObj->getSelectedDomainIDs()));
	$buys = $dbCmd2->GetValue();

	
	$conversionRate = round((100 * $buys / $clickNumber), 1);


	// Every 500 records print a period and flush the buffer to keep the browser from timing out.
	$counter++;
	if($counter > 500){	
		$counter = 0;
		print ". ";
		Constants::FlushBufferOutput();
	}


	// People may legitimately click on the ad a few times... so don't show a small amount

	if($clickNumber < 4)
		continue;
		
		
	$ipBannerLogArr[$ipIndexCounter] =  array();
	$ipBannerLogArr[$ipIndexCounter]["clicks"] = $clickNumber;
	$ipBannerLogArr[$ipIndexCounter]["buys"] = $buys;

	$ipBannerLogArr[$ipIndexCounter]["conversion"] = "%" . $conversionRate;
	$ipBannerLogArr[$ipIndexCounter]["ipAddress"] = $thisIPaddy;
	
	$ipIndexCounter++;
	
	$EmptyList = false;
	

}





if($EmptyList){
	$t->set_block("origPage","NoAbuseBL","NoAbuseBLout");
	$t->set_var("NoAbuseBLout", "<br><br>No abuse during this period.");
}
else{

	$clickTotal = 0;
	$buysTotal = 0;
	

	// This function will sort 2-D array based on the "Number of Clicks"
	WebUtil::array_qsort2($ipBannerLogArr, "clicks", SORT_DESC);

	foreach($ipBannerLogArr as $thisClicksArr){

		$t->set_var("IPADDRESS", $thisClicksArr["ipAddress"]);
		$t->set_var("IPADDRESS_DESC", $thisClicksArr["ipAddress"]);
		$t->set_var("CLICK_NUMBER", $thisClicksArr["clicks"]);
		$t->set_var("CONVERSION", $thisClicksArr["conversion"]);
		
		// Hide the hyper link for 0 buys.
		if($thisClicksArr["buys"] == 0)
			$t->set_var("BUYS", "</a>" . $thisClicksArr["buys"] . "<a>");
		else
			$t->set_var("BUYS", $thisClicksArr["buys"]);
			
		$t->allowVariableToContainBrackets("BUYS");
		
		$buysTotal += $thisClicksArr["buys"];
		$clickTotal += $thisClicksArr["clicks"];

		$t->parse("AbuseBLout","AbuseBL",true);
	}
	
	
	
	$TotalConversionRate = round((100 * $buysTotal / $clickTotal), 1);
	
	
	$t->set_var("IPADDRESS_DESC", "</a><b>Totals</b><a>");
	$t->set_var("IPADDRESS", "");
	$t->set_var("BUYS", "</a><b>" . $buysTotal . "</b><a>");
	$t->set_var("CLICK_NUMBER", "<b>" . $clickTotal . "</b>");
	$t->set_var("CONVERSION", ("<b>%" . $TotalConversionRate . "</b>"));
	
	
	$t->allowVariableToContainBrackets("IPADDRESS_DESC");
	$t->allowVariableToContainBrackets("BUYS");
	$t->allowVariableToContainBrackets("CLICK_NUMBER");
	$t->allowVariableToContainBrackets("CONVERSION");

	$t->parse("AbuseBLout","AbuseBL",true);
}



$t->pparse("OUT","origPage");




?>

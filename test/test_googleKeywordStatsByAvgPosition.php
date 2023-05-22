<?

require_once("library/Boot_Session.php");



$start_timestamp = mktime (0,0,0, 1, 1, 2007);
$end_timestamp = mktime (0,0,0, 1, 2, 2007);




$dbCmd = new DbCmd();

// Make this script be able to run for a while
set_time_limit(4000);


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("MARKETING_REPORT"))
	WebUtil::PrintAdminError("Not Available");
	



print "Date,Clicks,Impressions,Cost,Avg CPC,Avg. CPM,CTR,Avg. Position,Orders,New Customers,Conversion Rate,New Conversion Rate,Avg. S/H Per Order\n";

if(Constants::GetDevelopmentServer())
	$googleCsvReportObj = new GoogleCsvReport("keyword_report1.csv");
else 
	$googleCsvReportObj = new GoogleCsvReport("keyword_report.csv");

$googleCsvReportObj->setKeywordsMatches(array("^business cards online\$"));
//$googleCsvReportObj->setAdGroupNameMatches(array("business", "BC"));
//$googleCsvReportObj->setNegativeKeywordsMatches(array("^business\scard$","^business\scards$", "free"));
$googleCsvReportObj->setExactMatch(true);
$googleCsvReportObj->setPhraseMatch(false);
$googleCsvReportObj->setBroadMatch(false);
//$googleCsvReportObj->setAccountNameMatches(array("Country"));
//$googleCsvReportObj->setNegativeAccountNameMatches(array("Country"));
//$googleCsvReportObj->setCampaignNameMatches(array("State Campaign"));
$googleCsvReportObj->setAdDistributionSearch(true);
$googleCsvReportObj->setAdDistributionContent(false);
$googleCsvReportObj->setAdDistributionSearchPartnerGoogle(true);
$googleCsvReportObj->setAdDistributionSearchPartnerOther(true);

$statsReportArr = $googleCsvReportObj->getReport();

foreach($statsReportArr as $thisDate => $thisStatsReportObj){
	
	$dayOfReport = date_parse($thisDate);
	$startTimeStamp = mktime(0,0,0,$dayOfReport['month'], $dayOfReport['day'], $dayOfReport['year']);
	$endTimeStamp = mktime(0,0,0,$dayOfReport['month'], ($dayOfReport['day']+1), $dayOfReport['year']);

	/*
	$dbCmd->Query("SELECT COUNT(*) FROM orders WHERE 
						DateOrdered BETWEEN '".DbCmd::FormatDBDateTime($startTimeStamp)."' AND '".DbCmd::FormatDBDateTime($endTimeStamp)."'
						AND Referral LIKE 'g-%-em-bc_general' AND BannerReferer NOT LIKE '%google.com%'");
	*/
	$dbCmd->Query("SELECT COUNT(*) FROM orders WHERE 
						DateOrdered BETWEEN '".DbCmd::FormatDBDateTime($startTimeStamp)."' AND '".DbCmd::FormatDBDateTime($endTimeStamp)."'
						AND Referral LIKE 'g-%em-bc_online'");
	$totalOrders = $dbCmd->GetValue();
	
	$dbCmd->Query("SELECT COUNT(*) FROM orders WHERE 
						DateOrdered BETWEEN '".DbCmd::FormatDBDateTime($startTimeStamp)."' AND '".DbCmd::FormatDBDateTime($endTimeStamp)."'
						AND Referral LIKE 'g-%em-bc_online'
						AND FirstTimeCustomer='Y'");
	$newCustomers = $dbCmd->GetValue();
	
	$dbCmd->Query("SELECT SUM(ShippingQuote) FROM orders WHERE 
						DateOrdered BETWEEN '".DbCmd::FormatDBDateTime($startTimeStamp)."' AND '".DbCmd::FormatDBDateTime($endTimeStamp)."'
						AND Referral LIKE 'g-%em-bc_online'");
	$totalShipping = $dbCmd->GetValue();
	


	print $thisDate . ",";
	print $thisStatsReportObj->clicks . ",";
	print $thisStatsReportObj->impressions . ",";
	print $thisStatsReportObj->cost . ",";
	print $thisStatsReportObj->getCostPerClick() . ",";
	print $thisStatsReportObj->getAverageCPM() . ",";
	print $thisStatsReportObj->getClickThroughRate() . ",";
	print round($thisStatsReportObj->averagePosition,2) . ",";
	print $totalOrders . ",";
	print $newCustomers . ",";
	
	if($thisStatsReportObj->clicks > 0){
		print round($totalOrders/$thisStatsReportObj->clicks, 3) . ",";
		print round($newCustomers/$thisStatsReportObj->clicks, 3) . ",";
	}
	else{
		print "0,0,";
	}
	
	if($totalOrders == 0)
		print "0";
	else
		print round($totalShipping/$totalOrders, 2);

	print "\n";

	flush();
}





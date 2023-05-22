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
	



print "Date,Clicks,Unique IP Addresses,Avg Position,Orders,Impressions,Cost\n";

if(Constants::GetDevelopmentServer())
	$googleCsvReportObj = new GoogleCsvReport("keyword_report1.csv");
else 
	$googleCsvReportObj = new GoogleCsvReport("/home/printsma/google-report-7-2-10.csv");

$googleCsvReportObj->setKeywordsMatches(array("free"));
//$googleCsvReportObj->setDestinationUrlMatches(array("productIDview"));
$googleCsvReportObj->setExactMatch(true);
$googleCsvReportObj->setPhraseMatch(false);
$googleCsvReportObj->setBroadMatch(false);
//$googleCsvReportObj->setAccountNameMatches(array("Country"));
//$googleCsvReportObj->setCampaignNameMatches(array("California"));
$googleCsvReportObj->setAdDistributionSearch(true);
$googleCsvReportObj->setAdDistributionContent(false);
$googleCsvReportObj->setAdDistributionSearchPartnerGoogle(true);
$googleCsvReportObj->setAdDistributionSearchPartnerOther(true);

$statsReportArr = $googleCsvReportObj->getReport();

foreach($statsReportArr as $thisDate => $thisStatsReportObj){
	
	$dayOfReport = date_parse($thisDate);
	$startTimeStamp = mktime(0,0,0,$dayOfReport['month'], $dayOfReport['day'], $dayOfReport['year']);
	$endTimeStamp = mktime(0,0,0,$dayOfReport['month'], ($dayOfReport['day']+1), $dayOfReport['year']);


	$dbCmd->Query("SELECT COUNT(DISTINCT IPaddress) FROM bannerlog WHERE 
						Date BETWEEN '".DbCmd::FormatDBDateTime($startTimeStamp)."' AND '".DbCmd::FormatDBDateTime($endTimeStamp)."'
						AND Name LIKE 'g-%free%' AND Name NOT LIKE '%-bm-%' AND Name NOT LIKE '%-pm-%' AND Referer IS NOT NULL");
	$uniqueIpAddresses = $dbCmd->GetValue();
	
	$dbCmd->Query("SELECT COUNT(*) FROM orders WHERE 
						DateOrdered BETWEEN '".DbCmd::FormatDBDateTime($startTimeStamp)."' AND '".DbCmd::FormatDBDateTime($endTimeStamp)."'
						AND Referral LIKE 'g-%free%' AND Referral NOT LIKE '%-bm-%' AND Referral NOT LIKE '%-pm-%'");
	$totalOrders = $dbCmd->GetValue();


	print $thisDate . ",";
	print $thisStatsReportObj->clicks . ",";
	print $uniqueIpAddresses . ",";
	print round($thisStatsReportObj->averagePosition,2) . ",";
	print $totalOrders . ",";
	print $thisStatsReportObj->impressions . ",";
	print $thisStatsReportObj->cost . ",";

	
	
	/*
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
*/
	print "\n";

	flush();
}





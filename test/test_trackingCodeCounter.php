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
	

$domainObj = Domain::singleton();


print "Date,Unique Google Codes,Codes 1 click,Codes 2 clicks,Codes 3 clicks,Codes 4 clicks,Codes 5 clicks,Codes 6-10 clicks,Codes 11-20 clicks,Codes 21-30 clicks,Codes 31-40 clicks,Codes above 40 clicks\n";

for($i=0; $i<1180; $i++){

	$start_timestamp += (60 * 60 * 24);
	$end_timestamp += (60 * 60 * 24);

	// Do a nested query to figure out how many clicks each name received 
	$dbCmd->Query("SELECT DISTINCT Name FROM bannerlog WHERE (UserAgent NOT LIKE '%google%' AND UserAgent NOT LIKE '%slurp%' AND UserAgent NOT LIKE '%robot%' )
			AND (Date BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "')
			AND DomainID=1 AND Name LIKE 'g-%'");
	$uniqueBannerCodesArr = $dbCmd->GetValueArr();
	$uniqueBannerCount = sizeof($uniqueBannerCodesArr);
	
	
	
	$clicks1 = 0;
	$clicks2 = 0;
	$clicks3 = 0;
	$clicks4 = 0;
	$clicks5 = 0;
	$clicks6_10 = 0;
	$clicks11_20 = 0;
	$clicks21_30 = 0;
	$clicks31_40 = 0;
	$clicks41_50 = 0;
	$clicks51_100 = 0;
	$clicks_above_100 = 0;
	
	foreach($uniqueBannerCodesArr as $thisBannerCode){
		
		$dbCmd->Query("SELECT COUNT(*) FROM bannerlog WHERE (UserAgent NOT LIKE '%google%' AND UserAgent NOT LIKE '%slurp%' AND UserAgent NOT LIKE '%robot%' )
				AND (Date BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "')
				AND DomainID=1 AND Name='".DbCmd::EscapeSQL($thisBannerCode)."'");
		
		$thisBannerCount = $dbCmd->GetValue();
		
		if($thisBannerCount == 1)
			$clicks1++;
		else if($thisBannerCount == 2)
			$clicks2++;
		else if($thisBannerCount == 3)
			$clicks3++;
		else if($thisBannerCount == 4)
			$clicks4++;
		else if($thisBannerCount == 5)
			$clicks5++;
		else if($thisBannerCount > 5 && $thisBannerCount < 11)
			$clicks6_10++;
		else if($thisBannerCount > 10 && $thisBannerCount < 21)
			$clicks11_20++;
		else if($thisBannerCount > 20 && $thisBannerCount < 31)
			$clicks21_30++;
		else if($thisBannerCount > 30 && $thisBannerCount < 41)
			$clicks31_40++;
		else if($thisBannerCount > 40 && $thisBannerCount < 51)
			$clicks41_50++;
		else if($thisBannerCount > 50 && $thisBannerCount < 101)
			$clicks51_100++;
		else if($thisBannerCount > 100)
			$clicks_above_100++;


	}
	
	
	
	print date("n-j-Y", $start_timestamp) . "," . $uniqueBannerCount . "," . $clicks1 . "," . $clicks2 . "," . $clicks3 . "," . $clicks4 . "," . $clicks5 . "," . $clicks6_10 . "," . $clicks11_20 . "," . $clicks21_30 . "," . $clicks31_40 . "," . $clicks41_50 . "," . $clicks51_100 . "," . $clicks_above_100 ."\n";


	flush();
}







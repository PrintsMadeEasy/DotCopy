<?

require_once("library/Boot_Session.php");


$reportSpanDays = WebUtil::GetInput("reportSpanDays", FILTER_SANITIZE_INT);
$reportSpacingIncrementDays = WebUtil::GetInput("reportSpacingIncrementDays", FILTER_SANITIZE_INT);
$startMonth = WebUtil::GetInput("startMonth", FILTER_SANITIZE_INT);
$startDay = WebUtil::GetInput("startDay", FILTER_SANITIZE_INT);
$startYear = WebUtil::GetInput("startYear", FILTER_SANITIZE_INT);
$trackingCodeSearch = WebUtil::GetInput("trackingCodeSearch", FILTER_SANITIZE_STRING_ONE_LINE);


$start_timestamp1 = mktime (0,0,0, 6, 1, 2008);
$end_timestamp1 = mktime (0,0,0, 9, 30, 2008);

$start_timestamp2 = mktime (0,0,0, 4, 1, 2009);
$end_timestamp2 = mktime (0,0,0, 7, 30, 2009);


$dbCmd = new DbCmd();

// Make this script be able to run for a while
set_time_limit(4000);


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("MARKETING_REPORT"))
	WebUtil::PrintAdminError("Not Available");
	

$domainObj = Domain::singleton();


$bannerCodeSearchSQL = 'g-%em-%bc_general';


for($i=60; $i<250; $i++){
	
	

	// Do a nested query to figure out how many clicks each name received 
	$dbCmd->Query("SELECT COUNT(*) from bannerlog USE INDEX (bannerlog_Date) WHERE DomainID = 1
			 AND Name LIKE '$bannerCodeSearchSQL' 
			AND (Date BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp1) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp1) . "') AND RefererBlank=0 AND 
			(IPaddress LIKE '$i.%.%.%')");

	$BannerClicks = $dbCmd->GetValue();
	
	if($BannerClicks < 50){
		continue;
	}
	
	print "IP Address: " . $i . ".*.*.* <br>";
	print "<u>End 2008</u><br>";	
	print $BannerClicks . " &nbsp;&nbsp;&nbsp; - ";
	
	// Find out the unique visitors from this banner
	$dbCmd->Query("SELECT COUNT(DISTINCT IPaddress) from bannerlog USE INDEX (bannerlog_Date) WHERE DomainID = 1
			 AND Name LIKE '$bannerCodeSearchSQL' 
			AND (Date BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp1) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp1) . "') AND RefererBlank=0 AND 
			(IPaddress LIKE '$i.%.%.%')");
	$BannerVisitors = $dbCmd->GetValue();


	
	if($BannerVisitors > 0)
		$bannerVisitorRatioFirst = round($BannerClicks/$BannerVisitors,2);
	else 
		$bannerVisitorRatioFirst = "0";
	
	
	print "<b>" . $bannerVisitorRatioFirst  . "%</b>";
		
	print "<br>";
	print "<u>Mid 2009</u><br>";
		
	
	
	// Do a nested query to figure out how many clicks each name received 
	$dbCmd->Query("SELECT COUNT(*) from bannerlog USE INDEX (bannerlog_Date) WHERE DomainID = 1
			 AND Name LIKE '$bannerCodeSearchSQL' 
			AND (Date BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp2) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp2) . "') AND RefererBlank=0 AND 
			(IPaddress LIKE '$i.%.%.%')");
	$BannerClicks = $dbCmd->GetValue();
	
	print $BannerClicks . " &nbsp;&nbsp;&nbsp; - ";
	
	// Find out the unique visitors from this banner
	$dbCmd->Query("SELECT COUNT(DISTINCT IPaddress) from bannerlog USE INDEX (bannerlog_Date) WHERE DomainID = 1
			 AND Name LIKE '$bannerCodeSearchSQL' 
			AND (Date BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp2) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp2) . "') AND RefererBlank=0 AND 
			(IPaddress LIKE '$i.%.%.%')");
	$BannerVisitors = $dbCmd->GetValue();


	
	if($BannerVisitors > 0)
		$bannerVisitorRatioSecond = round($BannerClicks/$BannerVisitors,2);
	else 
		$bannerVisitorRatioSecond = "0";
		
	print "<b>" . $bannerVisitorRatioSecond  . "%</b>";
	
	
		
	if(($bannerVisitorRatioSecond - $bannerVisitorRatioFirst < 0.1) && $BannerClicks > 100)
		print "<font color=#00FF00>***</font><br>";
		
	if(($bannerVisitorRatioSecond - $bannerVisitorRatioFirst > 0.2) && $BannerClicks > 100)
		print "<font color=red>***</font><br>";
	
	
	
		
	print "<br><br>";
	
	flush();
}







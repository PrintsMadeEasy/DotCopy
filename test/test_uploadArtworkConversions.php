<?

require_once("library/Boot_Session.php");



$start_timestamp = mktime (0,0,0, 1, 5, 2007);
$end_timestamp = mktime (0,0,0, 1, 12, 2007);




$dbCmd = new DbCmd();

// Make this script be able to run for a while
set_time_limit(4000);


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("MARKETING_REPORT"))
	WebUtil::PrintAdminError("Not Available");
	



print "Date,New Users From Biz Card Upload Artwork, From Search Engine, From Category,From User Template,Google Biz Card Visitors\n";


while(true){
	
	$dbCmd->Query("SELECT COUNT(DISTINCT UserID) FROM orders INNER JOIN projectsordered ON projectsordered.OrderID = orders.ID WHERE 
						DateOrdered BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
						AND RePrintLink = 0
						AND FirstTimeCustomer='Y'
						AND ProductID='73'
						AND FromTemplateArea='P'
						AND (Referral LIKE 'g-%-em-bc%' OR Referral LIKE 'g-bc%-em%')
						AND orders.DomainID=1");
	$uploadArtworkOrders = $dbCmd->GetValue();
	
	$dbCmd->Query("SELECT COUNT(DISTINCT UserID) FROM orders INNER JOIN projectsordered ON projectsordered.OrderID = orders.ID WHERE 
						DateOrdered BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
						AND RePrintLink = 0
						AND FirstTimeCustomer='Y'
						AND ProductID='73'
						AND FromTemplateArea='S'
						AND (Referral LIKE 'g-%-em-bc%' OR Referral LIKE 'g-bc%-em%')
						AND orders.DomainID=1");
	$searchEngineOrders = $dbCmd->GetValue();
	
	$dbCmd->Query("SELECT COUNT(DISTINCT UserID) FROM orders INNER JOIN projectsordered ON projectsordered.OrderID = orders.ID WHERE 
						DateOrdered BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
						AND RePrintLink = 0
						AND FirstTimeCustomer='Y'
						AND ProductID='73'
						AND FromTemplateArea='C'
						AND (Referral LIKE 'g-%-em-bc%' OR Referral LIKE 'g-bc%-em%')
						AND orders.DomainID=1");
	$categoryOrders = $dbCmd->GetValue();
	
	$dbCmd->Query("SELECT COUNT(DISTINCT UserID) FROM orders INNER JOIN projectsordered ON projectsordered.OrderID = orders.ID WHERE 
						DateOrdered BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
						AND RePrintLink = 0
						AND FirstTimeCustomer='Y'
						AND ProductID='73'
						AND FromTemplateArea='U'
						AND (Referral LIKE 'g-%-em-bc%' OR Referral LIKE 'g-bc%-em%')
						AND orders.DomainID=1");
	$userTemplateOrders = $dbCmd->GetValue();

	$dbCmd->Query("SELECT COUNT(DISTINCT IPaddress) FROM bannerlog WHERE 
						Date BETWEEN '".DbCmd::FormatDBDateTime($start_timestamp)."' AND '".DbCmd::FormatDBDateTime($end_timestamp)."'
						AND (Name LIKE 'g-%-em-bc%' OR Name LIKE 'g-bc%-em%')
						AND DomainID=1");
	$bannerClicks = $dbCmd->GetValue();

	

	
	print date("n/j/y", $start_timestamp) . ",";
	print $uploadArtworkOrders . ",";
	print $searchEngineOrders . ",";
	print $categoryOrders . ",";
	print $userTemplateOrders . ",";
	print $bannerClicks;

	
	print "\n";
	

	// Advance by 1 week.
	$start_timestamp += 60*60*24*7; 
	$end_timestamp += 60*60*24*7; 

	usleep(500000);
	flush();
	
	if($start_timestamp > time())
		break;
}





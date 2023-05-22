<?

require_once("library/Boot_Session.php");







$dbCmd = new DbCmd();

// Make this script be able to run for a while
set_time_limit(4000);


//Make sure they are logged in.
$AuthObj = new Authenticate(Authenticate::login_ADMIN);
$UserID = $AuthObj->GetUserID();


if(!$AuthObj->CheckForPermission("MARKETING_REPORT"))
	WebUtil::PrintAdminError("Not Available");
	

$domainObj = Domain::singleton();



print "Date,Coupon usage";

print "\n";



$startMonth = 1;
$startDay = 1;
$spacingDays = 1;

for($i=1; $i<2000; $i++){
	
	
	$start_timestamp = mktime (0,0,0, $startMonth, ($startDay + $spacingDays * $i), 2007);
	$end_timestamp = mktime (0,0,0, $startMonth, ($startDay + $spacingDays + $spacingDays * $i), 2007);
	
	if($end_timestamp > time())
		break;
	
	print date("n/j/Y", $start_timestamp) . ",";


	$dbCmd->Query("SELECT COUNT(*) FROM orders USE INDEX (orders_DateOrdered) 
		INNER JOIN coupons ON coupons.ID = orders.CouponID WHERE orders.DomainID = 1
		AND (DateOrdered BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "') 
		AND coupons.Code LIKE 'OFFER21XA'");
	$couponCount = $dbCmd->GetValue();
	
	
	print $couponCount;

	
	
	print "\n";
	

	flush();
}







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
$shippingChoiceObj = new ShippingChoices(1);

print "Date,Normal,Medium,Elevated,High,Urgent\n";

for($i=0; $i<1080; $i++){

	$start_timestamp += (60 * 60 * 24);
	$end_timestamp += (60 * 60 * 24);


	$dbCmd->Query("SELECT COUNT(DISTINCT(orders.ID)) FROM orders
			INNER JOIN projectsordered ON projectsordered.OrderID = orders.ID
			WHERE orders.DomainID=1 AND ProductID=73 AND ProductID!=90 AND ProductID!=89  
			AND " . DbHelper::getOrClauseFromArray("ShippingChoiceID", $shippingChoiceObj->getShippingChoiceIDsByPriority(ShippingChoices::MEDIUM_PRIORITY)) . "
			AND orders.Referral NOT LIKE 'g-%-em-bc_general'
			AND (DateOrdered BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "')");
	$mediumShipping = $dbCmd->GetValue();
	
	$dbCmd->Query("SELECT COUNT(DISTINCT(orders.ID)) FROM orders
			INNER JOIN projectsordered ON projectsordered.OrderID = orders.ID
			WHERE orders.DomainID=1 AND ProductID=73 AND ProductID!=90 AND ProductID!=89  
			AND " . DbHelper::getOrClauseFromArray("ShippingChoiceID", $shippingChoiceObj->getShippingChoiceIDsByPriority(ShippingChoices::NORMAL_PRIORITY)) . "
			AND orders.Referral NOT LIKE 'g-%-em-bc_general'
			AND (DateOrdered BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "')");
	$normalShipping = $dbCmd->GetValue();

	$dbCmd->Query("SELECT COUNT(DISTINCT(orders.ID)) FROM orders
			INNER JOIN projectsordered ON projectsordered.OrderID = orders.ID
			WHERE orders.DomainID=1 AND ProductID=73 AND ProductID!=90 AND ProductID!=89  
			AND " . DbHelper::getOrClauseFromArray("ShippingChoiceID", $shippingChoiceObj->getShippingChoiceIDsByPriority(ShippingChoices::ELEVATED_PRIORITY)) . "
			AND orders.Referral NOT LIKE 'g-%-em-bc_general'
			AND (DateOrdered BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "')");
	$elevatedShipping = $dbCmd->GetValue();
	
	$dbCmd->Query("SELECT COUNT(DISTINCT(orders.ID)) FROM orders
			INNER JOIN projectsordered ON projectsordered.OrderID = orders.ID
			WHERE orders.DomainID=1 AND ProductID=73 AND ProductID!=90 AND ProductID!=89  
			AND " . DbHelper::getOrClauseFromArray("ShippingChoiceID", $shippingChoiceObj->getShippingChoiceIDsByPriority(ShippingChoices::HIGH_PRIORITY)) . "
			AND orders.Referral NOT LIKE 'g-%-em-bc_general'
			AND (DateOrdered BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "')");
	$highShipping = $dbCmd->GetValue();
	
	$dbCmd->Query("SELECT COUNT(DISTINCT(orders.ID)) FROM orders
			INNER JOIN projectsordered ON projectsordered.OrderID = orders.ID
			WHERE orders.DomainID=1 AND ProductID=73 AND ProductID!=90 AND ProductID!=89  
			AND " . DbHelper::getOrClauseFromArray("ShippingChoiceID", $shippingChoiceObj->getShippingChoiceIDsByPriority(ShippingChoices::URGENT_PRIORITY)) . "
			AND orders.Referral NOT LIKE 'g-%-em-bc_general'
			AND (DateOrdered BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "')");
	$urgentShipping = $dbCmd->GetValue();
	
	print date("n-j-Y", $start_timestamp) . "," . $normalShipping . "," . $mediumShipping  . "," . $elevatedShipping  . "," . $highShipping  . "," . $urgentShipping  . "\n";


	flush();
}







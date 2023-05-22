<?

require_once("library/Boot_Session.php");



$start_timestamp = mktime (0,0,0, 1, 1, 2005);
$end_timestamp = mktime (0,0,0, 1, 2, 2005);




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


print "Date,Average Production Days (medium/normal),Average Production Days (expedited/high),Order Count(medium/normal),Order Count(expedited/high)\n";

while(true){

	$start_timestamp += (60 * 60 * 24);
	$end_timestamp += (60 * 60 * 24);
	
	// Skip... unless it is a Tuesday.
	//if(date("D", $start_timestamp) != "Tue")
	//	continue;
		
	if($start_timestamp > time())
		break;
	
	$totalShipmentTimesSecondsToday_Normal = 0;
	$ordersCounted_Normal = 0;

	$dbCmd->Query("SELECT projectsordered.ID FROM orders
			INNER JOIN projectsordered ON projectsordered.OrderID = orders.ID
			WHERE orders.DomainID=1 AND ProductID=73
			AND 
				(
					" . DbHelper::getOrClauseFromArray("ShippingChoiceID", $shippingChoiceObj->getShippingChoiceIDsByPriority(ShippingChoices::MEDIUM_PRIORITY)) . "
					OR 
					" . DbHelper::getOrClauseFromArray("ShippingChoiceID", $shippingChoiceObj->getShippingChoiceIDsByPriority(ShippingChoices::NORMAL_PRIORITY)) . "
				)
			AND Status = 'F'
			AND Quantity >= 500
			AND (DateOrdered BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "')");
	$projectIDarr = $dbCmd->GetValueArr();
	
	
	foreach($projectIDarr as $thisProjectID){
		
		$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateOrdered) FROM orders
				INNER JOIN projectsordered ON projectsordered.OrderID = orders.ID
				WHERE projectsordered.ID = $thisProjectID LIMIT 1");
		$dateOrderedTimeStamp = $dbCmd->GetValue();
		
		// Make the Date Stamp snap to midnight/morning of the day.
		$dateOrderedTimeStamp = mktime(0,0,0, date("n", $dateOrderedTimeStamp), date("j", $dateOrderedTimeStamp), date("Y", $dateOrderedTimeStamp));
		
		
		$dbCmd->Query("SELECT UNIX_TIMESTAMP(Date) FROM projecthistory
				WHERE Note='Finished' AND
				ProjectID = $thisProjectID LIMIT 1");
		$dateFinishedTimeStamp = $dbCmd->GetValue();
		
		// Make the Date Stamp snap to midnight/morning of the day.
		$dateFinishedTimeStamp = mktime(0,0,0, date("n", $dateFinishedTimeStamp), date("j", $dateFinishedTimeStamp), date("Y", $dateFinishedTimeStamp));
		
		if($dateOrderedTimeStamp > 0 && $dateOrderedTimeStamp > 0 && $dateFinishedTimeStamp >= $dateOrderedTimeStamp){
			$ordersCounted_Normal++;
			$totalShipmentTimesSecondsToday_Normal += ($dateFinishedTimeStamp - $dateOrderedTimeStamp);
		}
	}
	
	$averageShipmentTimesToday_Normal = 0;
	if($ordersCounted_Normal > 0){
		$averageShipmentTimesToday_Normal = round(($totalShipmentTimesSecondsToday_Normal / $ordersCounted_Normal / 60 / 60 / 24), 1);
	}

	
	
	
	// ----------------------------------------------------------------------------
	
	
	
	$totalShipmentTimesSecondsToday_Expedited = 0;
	$ordersCounted_Expedited = 0;

	$dbCmd->Query("SELECT projectsordered.ID FROM orders
			INNER JOIN projectsordered ON projectsordered.OrderID = orders.ID
			WHERE orders.DomainID=1 AND ProductID=73
			AND 
				(
					" . DbHelper::getOrClauseFromArray("ShippingChoiceID", $shippingChoiceObj->getShippingChoiceIDsByPriority(ShippingChoices::URGENT_PRIORITY)) . "
					OR 
					" . DbHelper::getOrClauseFromArray("ShippingChoiceID", $shippingChoiceObj->getShippingChoiceIDsByPriority(ShippingChoices::HIGH_PRIORITY)) . "
					OR 
					" . DbHelper::getOrClauseFromArray("ShippingChoiceID", $shippingChoiceObj->getShippingChoiceIDsByPriority(ShippingChoices::ELEVATED_PRIORITY)) . "
				)
			AND Status = 'F'
			AND (DateOrdered BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "')");
	$projectIDarr = $dbCmd->GetValueArr();
	
	
	foreach($projectIDarr as $thisProjectID){
		
		$dbCmd->Query("SELECT UNIX_TIMESTAMP(DateOrdered) FROM orders
				INNER JOIN projectsordered ON projectsordered.OrderID = orders.ID
				WHERE projectsordered.ID = $thisProjectID LIMIT 1");
		$dateOrderedTimeStamp = $dbCmd->GetValue();
		
		// Make the Date Stamp snap to midnight/morning of the day.
		$dateOrderedTimeStamp = mktime(0,0,0, date("n", $dateOrderedTimeStamp), date("j", $dateOrderedTimeStamp), date("Y", $dateOrderedTimeStamp));
		
		$dbCmd->Query("SELECT UNIX_TIMESTAMP(Date) FROM projecthistory
				WHERE Note='Finished' AND
				ProjectID = $thisProjectID LIMIT 1");
		$dateFinishedTimeStamp = $dbCmd->GetValue();
		
		// Make the Date Stamp snap to midnight/morning of the day.
		$dateFinishedTimeStamp = mktime(0,0,0, date("n", $dateFinishedTimeStamp), date("j", $dateFinishedTimeStamp), date("Y", $dateFinishedTimeStamp));
		
		if($dateOrderedTimeStamp > 0 && $dateOrderedTimeStamp > 0 && $dateFinishedTimeStamp > $dateOrderedTimeStamp){
			$ordersCounted_Expedited++;
			$totalShipmentTimesSecondsToday_Expedited += ($dateFinishedTimeStamp - $dateOrderedTimeStamp);
		}
		
	}
	
	$averageShipmentTimesToday_Expedited = 0;
	if($ordersCounted_Expedited > 0){
		$averageShipmentTimesToday_Expedited = round(($totalShipmentTimesSecondsToday_Expedited / $ordersCounted_Expedited / 60 / 60 / 24), 1);
	}
	
	

	print date("n-j-Y", $start_timestamp) . "," . $averageShipmentTimesToday_Normal . "," . $averageShipmentTimesToday_Expedited  . "," . $ordersCounted_Normal . "," . $ordersCounted_Expedited . "\n";

	flush();
}







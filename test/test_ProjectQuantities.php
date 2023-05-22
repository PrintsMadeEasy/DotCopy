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


print "Date,New 20,New 100,New 500,New 1000,New 2000,New 2500,New 3000,New 5000,Repeat 20,Repeat 100,Repeat 500,Repeat 1000,Repeat 2000,Repeat 2500,Repeat 3000,Repeat 5000\n";

for($i=0; $i<1180; $i++){

	$start_timestamp += (60 * 60 * 24);
	$end_timestamp += (60 * 60 * 24);

	// Do a nested query to figure out how many clicks each name received 
	$dbCmd->Query("SELECT Quantity FROM projectsordered INNER JOIN orders ON orders.ID = projectsordered.OrderID WHERE FirstTimeCustomer='Y' AND Status != 'N'
			AND ProductID=73
			AND (DateOrdered BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "')
			AND orders.DomainID=1");
	$projectQuantitiesArr = $dbCmd->GetValueArr();

	$new_count_20 = 0;
	$new_count_100 = 0;
	$new_count_300 = 0;
	$new_count_500 = 0;
	$new_count_1000 = 0;
	$new_count_2000 = 0;
	$new_count_2500 = 0;
	$new_count_3000 = 0;
	$new_count_5000 = 0;
	
	foreach($projectQuantitiesArr as $thisQuantity){
		
		if($thisQuantity == 20)
			$new_count_20++;
		else if($thisQuantity == 100)
			$new_count_100++;
		else if($thisQuantity == 300)
			$new_count_300++;
		else if($thisQuantity == 500)
			$new_count_500++;
		else if($thisQuantity == 1000)
			$new_count_1000++;
		else if($thisQuantity == 2000)
			$new_count_2000++;
		else if($thisQuantity == 2500)
			$new_count_2500++;
		else if($thisQuantity == 3000)
			$new_count_3000++;
		else if($thisQuantity == 5000)
			$new_count_5000++;
	}
	
	
	// Do a nested query to figure out how many clicks each name received 
	$dbCmd->Query("SELECT Quantity FROM projectsordered INNER JOIN orders ON orders.ID = projectsordered.OrderID WHERE FirstTimeCustomer='N' AND Status != 'N'
			AND ProductID=73
			AND (DateOrdered BETWEEN '" . DbCmd::FormatDBDateTime($start_timestamp) . "' AND '" . DbCmd::FormatDBDateTime($end_timestamp) . "')
			AND orders.DomainID=1");
	$projectQuantitiesArr = $dbCmd->GetValueArr();

	$repeat_count_20 = 0;
	$repeat_count_100 = 0;
	$repeat_count_300 = 0;
	$repeat_count_500 = 0;
	$repeat_count_1000 = 0;
	$repeat_count_2000 = 0;
	$repeat_count_2500 = 0;
	$repeat_count_3000 = 0;
	$repeat_count_5000 = 0;
	
	foreach($projectQuantitiesArr as $thisQuantity){
		
		if($thisQuantity == 20)
			$repeat_count_20++;
		else if($thisQuantity == 100)
			$repeat_count_100++;
		else if($thisQuantity == 300)
			$repeat_count_300++;
		else if($thisQuantity == 500)
			$repeat_count_500++;
		else if($thisQuantity == 1000)
			$repeat_count_1000++;
		else if($thisQuantity == 2000)
			$repeat_count_2000++;
		else if($thisQuantity == 2500)
			$repeat_count_2500++;
		else if($thisQuantity == 3000)
			$repeat_count_3000++;
		else if($thisQuantity == 5000)
			$repeat_count_5000++;
	}
	

	
	print date("n-j-Y", $start_timestamp) . ",$new_count_20,$new_count_100,$new_count_300,$new_count_500,$new_count_1000,$new_count_2000,$new_count_3000,$new_count_5000,$repeat_count_20,$repeat_count_100,$repeat_count_300,$repeat_count_500,$repeat_count_1000,$repeat_count_2000,$repeat_count_3000,$repeat_count_5000\n";


	flush();
}







<?

require_once("library/Boot_Session.php");




$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();



// Make this script be able to run for at least 1 hour
set_time_limit(3600);

			

// All charges with an N status... meaning... Needs Capture
$dbCmd->Query("SELECT DISTINCT OrderID FROM charges WHERE ChargeType='N' ORDER BY OrderID ASC");

// We need to fill up a temporary Array first...
// Auth.net may take a while and we don't want to keep the DB connection open during the loop. 

$OrderIDArray = array();

while ($OrdID = $dbCmd->GetValue())
	$OrderIDArray[] = $OrdID;


foreach($OrderIDArray as $ThisOrderID){

	
	print $ThisOrderID . "<br>";
	flush();
	

	$PaymentsObj = new Payments(Order::getDomainIDFromOrder($ThisOrderID));

	
	$PaymentsObj->CaptureFundsForOrder($ThisOrderID);
	
	sleep(1);
}

print "<br><br>done";



?>
<?

require_once("library/Boot_Session.php");


set_time_limit(5000);


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();



$counter = 0;

print "Changing Shipping Priorities<hr>\n";
flush();


$dbCmd->Query("SELECT ID FROM orders ORDER BY ID ASC");
while($orderID = $dbCmd->GetValue()){
	
	Order::ChangeShippingPriorityOnProjects($dbCmd2, $orderID);
	
	if($counter > 100){
		print $orderID . " - ";
		$counter = 0;
		flush();
	}
	
	$counter++;
}



print "<hr>DONE";



?>

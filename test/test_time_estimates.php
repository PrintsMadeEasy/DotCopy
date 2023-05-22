<?

require_once("library/Boot_Session.php");





$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();



set_time_limit(900);

print "Creating New Time Object<hr>";

$productID = "73";
$timeEstObj = new TimeEstimate($productID);


$StartTime = mktime(9, 24, 0, 1, 13, 2005);


print "Start Time<br>";
print date("D F j, Y, g:i a", $StartTime);
print "<br><hr>";

$timeEstObj->setShippingChoiceID("3");


print "Est. Ship Day<br>";
$ShipDate = $timeEstObj->estimateShipOrPrintDate("ship", $StartTime);
var_dump($ShipDate);

print "<br><hr>";


print "Cut Off Time<br>";
$CutOff = $timeEstObj->EstimateCutoffTime($StartTime);
print date("D F j, Y, g:i a", $CutOff);

print "<br><hr>";


print "Arrival Time<br>";
var_dump($timeEstObj->estimateArrivalDate($ShipDate["year"], $ShipDate["month"], $ShipDate["day"]));

print "<br><hr>";
/*
$dbCmd->Query("SELECT ID, UNIX_TIMESTAMP(DateOrdered) AS DateOrdered FROM orders");
while($row = $dbCmd->GetRow()){
	print $row["ID"] . "<br>";
	flush();
	
	Order::ResetProductionAndShipmentDates($dbCmd2, $row["ID"], "order", $row["DateOrdered"]);

}
*/


print "<br>OK.. Mission Accomplished";


?>

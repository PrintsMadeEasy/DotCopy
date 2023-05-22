<?

require_once("library/Boot_Session.php");


set_time_limit(5000);


$dbCmd = new DbCmd();
$dbCmd2 = new DbCmd();




$dbCmd->Query("UPDATE users SET DomainID=4 WHERE ID=6");


print "Updated Donald Ducks Domain ID.<br><br>";




$dbCmd->Query("SELECT projectsordered.ID AS ProjectID, orders.ID AS OrderID FROM projectsordered INNER JOIN orders ON projectsordered.OrderID = orders.ID WHERE orders.UserID=6");
while($row = $dbCmd->GetRow()){
	
	$dbCmd2->UpdateQuery("projectsordered", array("DomainID"=>4), "ID=" . $row['ProjectID']);
	$dbCmd2->UpdateQuery("orders", array("DomainID"=>4), "ID=" . $row['OrderID']);

}


print "Updated Donald Ducks Domain IDs within Projects ordered.<br><br>";

flush();

$dbCmd->Query("UPDATE orders SET DomainID=4 WHERE UserID=6");
print "Updated Donald Ducks Domain IDs within Orders table.<br><br>";

$dbCmd->Query("UPDATE projectssaved SET UserID=6 WHERE ProductID=93");

$dbCmd->Query("UPDATE projectssaved SET DomainID=4 WHERE UserID=6");




print "Converting Brian's old Mailers<br><br>";
flush();

// Convert some of my previous orders into Donald Duck

$dbCmd->Query("UPDATE orders SET DomainID = 4 WHERE ID = 238302 OR ID = 238700 OR ID = 238703 OR ID = 238711 OR ID = 238959 OR ID = 239099 OR ID = 239332 OR ID = 239357 OR ID = 239723 OR ID = 240483 OR ID = 241018 OR ID = 241445 OR ID = 241827 OR ID = 242333");
$dbCmd->Query("UPDATE orders SET ShippingChoiceID=12 WHERE ID = 238302 OR ID = 238700 OR ID = 238703 OR ID = 238711 OR ID = 238959 OR ID = 239099 OR ID = 239332 OR ID = 239357 OR ID = 239723 OR ID = 240483 OR ID = 241018 OR ID = 241445 OR ID = 241827 OR ID = 242333");
$dbCmd->Query("UPDATE orders SET UserID = 6 WHERE ID = 238302 OR ID = 238700 OR ID = 238703 OR ID = 238711 OR ID = 238959 OR ID = 239099 OR ID = 239332 OR ID = 239357 OR ID = 239723 OR ID = 240483 OR ID = 241018 OR ID = 241445 OR ID = 241827 OR ID = 242333");
$dbCmd->Query("UPDATE projectsordered SET DomainID = 4 WHERE OrderID = 238302 OR OrderID = 238700 OR OrderID = 238703 OR OrderID = 238711 OR OrderID = 238959 OR OrderID = 239099 OR OrderID = 239332 OR OrderID = 239357 OR OrderID = 239723 OR OrderID = 240483 OR OrderID = 241018 OR OrderID = 241445 OR OrderID = 241827 OR OrderID = 242333");

print "<hr>DONE";






?>
